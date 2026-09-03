<?php
// record_deposit.php - Rapid Deposit Entry & Multi-Space Allocation
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = get_logged_in_user();
$pdo = get_db_connection();
$error = '';

$selectedCustomerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

// Fetch customers list for selection
if ($user['role'] === 'collector') {
    $stmtCust = $pdo->prepare("
        SELECT c.id, c.full_name, c.account_number, c.location, c.change_balance,
               sc.id as card_id, sc.card_number, sc.daily_amount, sc.spaces_filled, sc.total_spaces, sc.total_saved
        FROM customers c
        LEFT JOIN susu_cards sc ON c.id = sc.customer_id AND sc.status = 'active'
        WHERE c.assigned_collector_id = ? AND c.is_active = 1
        ORDER BY c.full_name ASC
    ");
    $stmtCust->execute([$user['id']]);
} else {
    $stmtCust = $pdo->query("
        SELECT c.id, c.full_name, c.account_number, c.location, c.change_balance,
               sc.id as card_id, sc.card_number, sc.daily_amount, sc.spaces_filled, sc.total_spaces, sc.total_saved
        FROM customers c
        LEFT JOIN susu_cards sc ON c.id = sc.customer_id AND sc.status = 'active'
        WHERE c.is_active = 1
        ORDER BY c.full_name ASC
    ");
}
$customers = $stmtCust->fetchAll();

// If customer is selected, get their active card details
$activeCustomer = null;
if ($selectedCustomerId > 0) {
    foreach ($customers as $c) {
        if ((int)$c['id'] === $selectedCustomerId) {
            $activeCustomer = $c;
            break;
        }
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $cashPaid = (float)($_POST['cash_paid'] ?? 0);
    $depositDate = !empty($_POST['deposit_date']) ? $_POST['deposit_date'] : date('Y-m-d');
    $collectorId = $user['role'] === 'collector' ? $user['id'] : (int)($_POST['collector_id'] ?? $user['id']);

    if ($customerId <= 0) {
        $error = 'Please select a customer.';
    } elseif ($cashPaid <= 0) {
        $error = 'Please enter a valid cash amount.';
    } else {
        // Fetch fresh customer & active card with lock
        try {
            $pdo->beginTransaction();

            $stmtLock = $pdo->prepare("SELECT * FROM customers WHERE id = ? FOR UPDATE");
            $stmtLock->execute([$customerId]);
            $cust = $stmtLock->fetch();

            $stmtCard = $pdo->prepare("SELECT * FROM susu_cards WHERE customer_id = ? AND status = 'active' FOR UPDATE");
            $stmtCard->execute([$customerId]);
            $card = $stmtCard->fetch();

            if (!$cust || !$card) {
                throw new Exception("This customer does not have an active Susu Card. Please open a card first.");
            }

            $dailyAmount = (float)$card['daily_amount'];
            $currentChange = (float)$cust['change_balance'];
            $currentSpaces = (int)$card['spaces_filled'];
            $totalSpaces = (int)$card['total_spaces'];

            // Calculate breakdown
            $breakdown = calculate_deposit_breakdown($dailyAmount, $cashPaid, $currentChange, $currentSpaces, $totalSpaces);

            if (!$breakdown['valid']) {
                throw new Exception($breakdown['message']);
            }

            $spacesToFill = $breakdown['spaces_to_fill'];
            $startSpace = $breakdown['start_space'];
            $endSpace = $breakdown['end_space'];
            $moneyApplied = $breakdown['money_applied'];
            $newChange = $breakdown['new_change'];

            // Insert individual space records into deposits table
            if ($spacesToFill > 0) {
                $stmtDep = $pdo->prepare("
                    INSERT INTO deposits (card_id, customer_id, collector_id, space_number, amount, deposit_date) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                for ($s = $startSpace; $s <= $endSpace; $s++) {
                    $stmtDep->execute([$card['id'], $customerId, $collectorId, $s, $dailyAmount, $depositDate]);
                }
            }

            // Update card progress
            $newSpacesFilled = $currentSpaces + $spacesToFill;
            $newTotalSaved = (float)$card['total_saved'] + $moneyApplied;
            $isComplete = ($newSpacesFilled >= $totalSpaces);
            $newStatus = $isComplete ? 'completed' : 'active';
            $closedAt = $isComplete ? date('Y-m-d H:i:s') : null;

            $stmtUpCard = $pdo->prepare("
                UPDATE susu_cards 
                SET spaces_filled = ?, total_saved = ?, status = ?, closed_at = ?
                WHERE id = ?
            ");
            $stmtUpCard->execute([$newSpacesFilled, $newTotalSaved, $newStatus, $closedAt, $card['id']]);

            // Update customer float balance
            $stmtUpCust = $pdo->prepare("UPDATE customers SET change_balance = ? WHERE id = ?");
            $stmtUpCust->execute([$newChange, $customerId]);

            $pdo->commit();

            $msg = "Deposit of " . format_money($cashPaid) . " recorded!";
            if ($spacesToFill > 0) {
                $msg .= " Stamped {$spacesToFill} space(s) (#{$startSpace} to #{$endSpace}).";
            }
            if ($newChange > 0) {
                $msg .= " Remaining change: " . format_money($newChange) . " stored in client float.";
            }
            if ($isComplete) {
                $msg .= " 🎉 Susu Card #{$card['card_number']} is now COMPLETED (31/31 spaces)!";
            }

            set_flash_message('success', $msg);
            header("Location: view_card.php?id={$card['id']}");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$pageTitle = "Record Deposit";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">

    <!-- Top Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-steel_azure">Record Susu Deposit</h1>
            <p class="text-xs text-slate-500 mt-0.5">Collect cash, stamp spaces, and store remaining change.</p>
        </div>
        <a href="<?= $user['role'] === 'admin' ? 'admin_dashboard.php' : 'collector_dashboard.php' ?>" class="text-xs font-bold text-cornflower_ocean hover:text-steel_azure inline-flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            <span>Back to Home</span>
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3.5 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-red-500 text-sm"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Main Deposit Entry Form -->
    <form method="POST" action="record_deposit.php" class="bg-white rounded-2xl border-2 border-silver-600 shadow-md p-6 space-y-6">
        
        <!-- Customer Selection -->
        <div>
            <label for="customer_select" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                1. Select Client *
            </label>
            <select id="customer_select" name="customer_id" required onchange="window.location.href='record_deposit.php?customer_id=' + this.value"
                    class="w-full px-3.5 py-3 rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none text-xs sm:text-sm font-semibold text-slate-800 transition bg-white">
                <option value="">-- Choose Customer --</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($activeCustomer && (int)$activeCustomer['id'] === (int)$c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['full_name']) ?> (<?= htmlspecialchars($c['account_number']) ?>) &bull; <?= format_money($c['daily_amount']) ?> / space
                        <?= $c['card_id'] ? " [{$c['spaces_filled']}/31 spaces]" : " [No Active Card]" ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="helper-text">Select the client who handed you cash for their susu contribution.</p>
        </div>

        <?php if ($activeCustomer): ?>
            <?php if (!$activeCustomer['card_id']): ?>
                <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                    ⚠️ This customer does not currently have an active Susu Card.
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="customers.php" class="font-bold underline ml-1">Open a new card here &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>

                <!-- Active Card Info Snapshot Card -->
                <div class="bg-platinum-800 p-4 rounded-xl border border-silver-600/80">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-700 pb-2 border-b border-silver-600">
                        <span>Card #<?= $activeCustomer['card_number'] ?> Status</span>
                        <a href="view_card.php?id=<?= $activeCustomer['card_id'] ?>" target="_blank" class="text-steel_azure hover:underline font-semibold inline-flex items-center gap-1">
                            <span>Open 31-Space Card</span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                        </a>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mt-2.5 text-xs">
                        <div>
                            <span class="text-slate-500 text-[11px]">Agreed Rate:</span>
                            <div class="font-extrabold text-steel_azure"><?= format_money($activeCustomer['daily_amount']) ?></div>
                        </div>
                        <div>
                            <span class="text-slate-500 text-[11px]">Spaces Filled:</span>
                            <div class="font-extrabold text-slate-800"><?= $activeCustomer['spaces_filled'] ?> / 31 spaces</div>
                        </div>
                        <div>
                            <span class="text-slate-500 text-[11px]">Total Saved:</span>
                            <div class="font-extrabold text-emerald-600"><?= format_money($activeCustomer['total_saved']) ?></div>
                        </div>
                        <div>
                            <span class="text-slate-500 text-[11px]">Customer Change:</span>
                            <div class="font-extrabold <?= $activeCustomer['change_balance'] > 0 ? 'text-pumpkin_spice' : 'text-slate-400' ?>">
                                <?= format_money($activeCustomer['change_balance']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hidden calculation fields for JS -->
                <input type="hidden" id="daily_amount" value="<?= $activeCustomer['daily_amount'] ?>">
                <input type="hidden" id="current_change" value="<?= $activeCustomer['change_balance'] ?>">
                <input type="hidden" id="spaces_filled" value="<?= $activeCustomer['spaces_filled'] ?>">
                <input type="hidden" id="total_spaces" value="<?= $activeCustomer['total_spaces'] ?>">

                <!-- Cash Paid Input & Presets (Hick's Law & Fitts's Law) -->
                <div>
                    <label for="cash_paid" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        2. Cash Received from Customer *
                    </label>

                    <!-- 1-Click Quick Space Buttons (Fitts's Law) -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                        <button type="button" data-mult="1" class="preset-btn rounded-xl bg-white hover:bg-platinum-800 text-slate-700 border-2 border-silver-600 font-extrabold text-xs py-2.5 px-2 transition flex flex-col items-center">
                            <span class="text-[11px] text-slate-500">1 Space</span>
                            <span class="text-steel_azure text-xs"><?= format_money($activeCustomer['daily_amount'] * 1) ?></span>
                        </button>
                        <button type="button" data-mult="2" class="preset-btn rounded-xl bg-white hover:bg-platinum-800 text-slate-700 border-2 border-silver-600 font-extrabold text-xs py-2.5 px-2 transition flex flex-col items-center">
                            <span class="text-[11px] text-slate-500">2 Spaces</span>
                            <span class="text-steel_azure text-xs"><?= format_money($activeCustomer['daily_amount'] * 2) ?></span>
                        </button>
                        <button type="button" data-mult="3" class="preset-btn rounded-xl bg-white hover:bg-platinum-800 text-slate-700 border-2 border-silver-600 font-extrabold text-xs py-2.5 px-2 transition flex flex-col items-center">
                            <span class="text-[11px] text-slate-500">3 Spaces</span>
                            <span class="text-steel_azure text-xs"><?= format_money($activeCustomer['daily_amount'] * 3) ?></span>
                        </button>
                        <button type="button" data-mult="5" class="preset-btn rounded-xl bg-white hover:bg-platinum-800 text-slate-700 border-2 border-silver-600 font-extrabold text-xs py-2.5 px-2 transition flex flex-col items-center">
                            <span class="text-[11px] text-slate-500">5 Spaces</span>
                            <span class="text-steel_azure text-xs"><?= format_money($activeCustomer['daily_amount'] * 5) ?></span>
                        </button>
                    </div>

                    <!-- Input Box -->
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-500 font-extrabold text-sm sm:text-base">GH₵</span>
                        <input type="number" step="<?= $activeCustomer['daily_amount'] ?>" min="<?= $activeCustomer['daily_amount'] ?>" id="cash_paid" name="cash_paid" required
                               class="w-full pl-14 pr-4 py-3.5 rounded-xl border-2 border-silver-600 focus:border-pumpkin_spice focus:ring-2 focus:ring-pumpkin_spice-800 outline-none text-base sm:text-lg font-black text-slate-800 transition"
                               placeholder="e.g. <?= number_format($activeCustomer['daily_amount'], 2) ?>">
                    </div>

                    <!-- Simple Inline Divisibility Warning (No Jargon) -->
                    <div id="remainder_error_msg" class="hidden mt-2 p-2.5 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-bold flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation text-red-500 text-sm flex-shrink-0"></i>
                        <span id="remainder_error_text"></span>
                    </div>
                </div>

                <!-- Real-Time Calculation Preview Card (Tesler's Law) -->
                <div id="calculation_preview" class="hidden bg-cornflower_ocean-900/60 border border-cornflower_ocean-700 p-4 rounded-xl space-y-2">
                    <div class="flex items-center justify-between text-xs font-extrabold text-steel_azure">
                        <span>Deposit Breakdown Preview</span>
                        <span id="preview_spaces" class="bg-steel_azure text-white px-2 py-0.5 rounded text-[11px]">0 spaces</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs pt-1">
                        <div>
                            <span class="text-slate-600">Spaces to Stamp:</span>
                            <div class="font-black text-slate-800" id="preview_range">-</div>
                        </div>
                        <div>
                            <span class="text-slate-600">Added to Savings:</span>
                            <div class="font-black text-emerald-700" id="preview_applied">GH₵ 0.00</div>
                        </div>
                        <div class="col-span-2 pt-1 border-t border-cornflower_ocean-800">
                            <span class="text-slate-600">New Customer Change Float:</span>
                            <div class="font-extrabold text-pumpkin_spice" id="preview_change">GH₵ 0.00</div>
                        </div>
                    </div>

                    <div id="preview_alert" class="hidden text-xs font-bold text-emerald-800 bg-emerald-100 p-2 rounded-lg mt-1"></div>
                </div>

                <!-- Date & Collector Info -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="deposit_date" class="block text-xs font-bold text-slate-700 mb-1">Collection Date</label>
                        <input type="date" id="deposit_date" name="deposit_date" value="<?= date('Y-m-d') ?>" required
                               class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm text-slate-700 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Collector Stamping</label>
                        <input type="text" readonly value="<?= htmlspecialchars($user['full_name']) ?> (<?= ucfirst($user['role']) ?>)"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 bg-platinum text-slate-600 outline-none text-xs sm:text-sm font-semibold">
                    </div>
                </div>

                <!-- Submit Action Button (Clean In-Flow inside card per user decision) -->
                <div class="pt-5 border-t border-silver-600/60">
                    <button type="submit" 
                            class="w-full btn-action-primary text-white font-extrabold text-base tracking-wide transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-circle-check text-base"></i>
                        <span>Confirm Cash & Stamp Spaces</span>
                    </button>
                </div>

            <?php endif; ?>
        <?php endif; ?>

    </form>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
