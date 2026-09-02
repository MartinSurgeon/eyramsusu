<?php
// request_payout.php - Request Customer Payout & Close Card
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = get_logged_in_user();
$pdo = get_db_connection();
$error = '';

$cardId = isset($_GET['card_id']) ? (int)$_GET['card_id'] : 0;

// Fetch active cards available for payout
if ($user['role'] === 'collector') {
    $stmtCards = $pdo->prepare("
        SELECT sc.id, sc.card_number, sc.daily_amount, sc.spaces_filled, sc.total_spaces, sc.total_saved,
               c.full_name, c.account_number, c.change_balance
        FROM susu_cards sc
        JOIN customers c ON sc.customer_id = c.id
        WHERE c.assigned_collector_id = ? AND sc.status = 'active' AND sc.spaces_filled > 0
        ORDER BY c.full_name ASC
    ");
    $stmtCards->execute([$user['id']]);
} else {
    $stmtCards = $pdo->query("
        SELECT sc.id, sc.card_number, sc.daily_amount, sc.spaces_filled, sc.total_spaces, sc.total_saved,
               c.full_name, c.account_number, c.change_balance
        FROM susu_cards sc
        JOIN customers c ON sc.customer_id = c.id
        WHERE sc.status = 'active' AND sc.spaces_filled > 0
        ORDER BY c.full_name ASC
    ");
}
$availableCards = $stmtCards->fetchAll();

$breakdown = null;
if ($cardId > 0) {
    $breakdown = calculate_payout_breakdown($cardId);
}

// Handle Payout Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postCardId = (int)($_POST['card_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Customer requested payout');

    if ($postCardId <= 0) {
        $error = 'Please select a Susu Card.';
    } else {
        $calc = calculate_payout_breakdown($postCardId);
        if (!$calc) {
            $error = 'Invalid card or card not found.';
        } else {
            // Check if there is already a pending payout
            $stmtCheck = $pdo->prepare("SELECT id FROM payouts WHERE card_id = ? AND status = 'pending'");
            $stmtCheck->execute([$postCardId]);
            if ($stmtCheck->fetch()) {
                $error = 'A payout request is already pending for this card. Please check with the Admin.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmtInsert = $pdo->prepare("
                        INSERT INTO payouts (card_id, customer_id, collector_id, total_saved, business_fee, change_refunded, customer_payout, status, reason) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
                    ");
                    $stmtInsert->execute([
                        $postCardId,
                        $calc['card']['customer_id'],
                        $user['id'],
                        $calc['total_saved'],
                        $calc['business_fee'],
                        $calc['change_refunded'],
                        $calc['customer_payout'],
                        $reason
                    ]);

                    // Notify Admins
                    $custName = $calc['card']['full_name'];
                    $payoutAmount = format_money($calc['customer_payout']);
                    create_notification(
                        null,
                        'payout_requested',
                        "New Payout Request",
                        "Payout of {$payoutAmount} requested for {$custName} (Card #{$calc['card']['card_number']}).",
                        "payouts.php"
                    );

                    $pdo->commit();

                    set_flash_message('success', 'Payout request of ' . format_money($calc['customer_payout']) . ' submitted successfully! Awaiting Admin approval.');
                    header('Location: ' . ($user['role'] === 'admin' ? 'payouts.php' : 'collector_dashboard.php'));
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Error submitting request: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = "Request Payout";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-steel_azure">Request Customer Payout</h1>
            <p class="text-xs text-slate-500 mt-0.5">Calculates customer savings minus the 1-space business fee.</p>
        </div>
        <a href="<?= $user['role'] === 'admin' ? 'payouts.php' : 'collector_dashboard.php' ?>" class="text-xs font-bold text-cornflower_ocean hover:text-steel_azure">
            &larr; Back
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3.5 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="request_payout.php" class="bg-white rounded-2xl border-2 border-silver-600 shadow-md p-6 space-y-6">
        
        <div>
            <label for="card_select" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                Select Active Susu Card *
            </label>
            <select id="card_select" name="card_id" required onchange="window.location.href='request_payout.php?card_id=' + this.value"
                    class="w-full px-3.5 py-3 rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none text-xs sm:text-sm font-semibold text-slate-800 transition bg-white">
                <option value="">-- Choose Client Card --</option>
                <?php foreach ($availableCards as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($cardId === (int)$c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['full_name']) ?> (Card #<?= $c['card_number'] ?>) &bull; <?= $c['spaces_filled'] ?>/31 spaces &bull; <?= format_money($c['total_saved']) ?> saved
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="helper-text">Choose the customer's card to calculate their payout amount.</p>
        </div>

        <?php if ($breakdown): ?>
            <!-- Payout Calculation Summary Card (Peak-End Rule & Transparency) -->
            <div class="bg-platinum-800 rounded-2xl border-2 border-silver-600 p-5 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-silver-600">
                    <div>
                        <h3 class="font-extrabold text-slate-800 text-sm sm:text-base">
                            <?= htmlspecialchars($breakdown['card']['full_name']) ?>
                        </h3>
                        <span class="text-[11px] text-slate-500">
                            Card #<?= $breakdown['card']['card_number'] ?> &bull; <?= $breakdown['spaces_filled'] ?> of 31 spaces filled
                        </span>
                    </div>
                    <?php if ($breakdown['is_full_cycle']): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                            Full 31 Spaces
                        </span>
                    <?php else: ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
                            Early Stop (<?= $breakdown['spaces_filled'] ?> Spaces)
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Breakdown Math -->
                <div class="space-y-2 text-xs sm:text-sm">
                    <div class="flex items-center justify-between text-slate-700">
                        <span>Total Contributed (<?= $breakdown['spaces_filled'] ?> spaces):</span>
                        <strong class="font-bold text-slate-800"><?= format_money($breakdown['total_saved']) ?></strong>
                    </div>

                    <div class="flex items-center justify-between text-red-600 font-semibold">
                        <span>Less: Business Fee (1 contribution rate):</span>
                        <strong>- <?= format_money($breakdown['business_fee']) ?></strong>
                    </div>

                    <?php if ($breakdown['change_refunded'] > 0): ?>
                        <div class="flex items-center justify-between text-pumpkin_spice font-semibold">
                            <span>Plus: Customer Float Refunded:</span>
                            <strong>+ <?= format_money($breakdown['change_refunded']) ?></strong>
                        </div>
                    <?php endif; ?>

                    <!-- Summary Callout Banner (Peak-End Rule) -->
                    <div class="flex items-center justify-between text-base sm:text-lg font-black text-emerald-700 pt-3 border-t-2 border-silver-600 bg-emerald-50 -mx-5 px-5 py-3 rounded-b-lg mt-2">
                        <span>Customer Takes Home:</span>
                        <span class="text-xl"><?= format_money($breakdown['customer_payout']) ?></span>
                    </div>
                </div>

                <!-- Expandable: How is this calculated? (Tesler's Law) -->
                <button type="button" class="expandable-trigger" data-target="calc-explainer">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    How is this calculated?
                </button>
                <div id="calc-explainer" class="expandable-content">
                    <div class="text-[11px] text-slate-500 bg-white/70 p-3 rounded-lg border border-silver-600/70 space-y-1.5">
                        <p>📋 <strong>Susu Business Rule:</strong> When a customer completes their 31-space card (or stops early), the business keeps exactly <strong>one space</strong> as its fee.</p>
                        <p>💰 The fee equals the agreed daily rate: <strong><?= format_money($breakdown['daily_amount']) ?></strong>.</p>
                        <p>🔄 Any leftover change (float) that was building towards the next space is also returned to the customer.</p>
                        <p>📌 The card will be permanently closed upon Admin approval, and the customer may start a fresh card whenever they wish.</p>
                    </div>
                </div>
            </div>

            <div>
                <label for="reason" class="block text-xs font-bold text-slate-700 mb-1">Reason / Notes</label>
                <input type="text" id="reason" name="reason"
                       value="<?= $breakdown['is_full_cycle'] ? 'Completed all 31 spaces' : 'Customer requested early payout' ?>"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm text-slate-800 transition">
            </div>

            <!-- Submit CTA -->
            <div class="pt-2">
                <button type="submit" 
                        class="w-full btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white font-extrabold text-sm sm:text-base tracking-wide shadow-md hover:shadow-lg transition">
                    Submit Payout Request for Approval
                </button>
            </div>
        <?php endif; ?>

    </form>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
