<?php
// payouts.php - Customer Payouts Queue & Approvals
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = get_logged_in_user();
$pdo = get_db_connection();

// Handle Approval / Action from Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_admin();

    $payoutId = (int)($_POST['payout_id'] ?? 0);
    $action = $_POST['action'];

    if ($payoutId > 0) {
        $stmtP = $pdo->prepare("SELECT * FROM payouts WHERE id = ?");
        $stmtP->execute([$payoutId]);
        $payout = $stmtP->fetch();

        if ($payout && $payout['status'] === 'pending') {
            try {
                $pdo->beginTransaction();

                if ($action === 'approve_and_pay') {
                    // 1. Mark payout as paid
                    $stmtApprove = $pdo->prepare("
                        UPDATE payouts 
                        SET status = 'paid', approved_by = ?, paid_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $stmtApprove->execute([$user['id'], $payoutId]);

                    // 2. Fetch Card & determine closure status
                    $stmtCard = $pdo->prepare("SELECT * FROM susu_cards WHERE id = ?");
                    $stmtCard->execute([$payout['card_id']]);
                    $card = $stmtCard->fetch();

                    $newStatus = ($card['spaces_filled'] >= $card['total_spaces']) ? 'completed' : 'closed_early';

                    $stmtCloseCard = $pdo->prepare("
                        UPDATE susu_cards 
                        SET status = ?, closed_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $stmtCloseCard->execute([$newStatus, $card['id']]);

                    // 3. Clear Customer Float Balance (already refunded in payout)
                    $stmtClearFloat = $pdo->prepare("UPDATE customers SET change_balance = 0.00 WHERE id = ?");
                    $stmtClearFloat->execute([$payout['customer_id']]);

                    // 4. Notify Collector
                    create_notification(
                        $payout['collector_id'],
                        'payout_paid',
                        "Payout Approved & Paid",
                        "Payout of " . format_money($payout['customer_payout']) . " was approved and paid. Card closed.",
                        "view_card.php?id=" . $payout['card_id']
                    );

                    $pdo->commit();
                    set_flash_message('success', "Payout of " . format_money($payout['customer_payout']) . " successfully approved and paid! Card closed.");

                } elseif ($action === 'reject') {
                    $stmtReject = $pdo->prepare("UPDATE payouts SET status = 'rejected', approved_by = ? WHERE id = ?");
                    $stmtReject->execute([$user['id'], $payoutId]);

                    create_notification(
                        $payout['collector_id'],
                        'payout_rejected',
                        "Payout Request Rejected",
                        "Payout request #{$payoutId} has been rejected by Admin.",
                        "request_payout.php"
                    );

                    $pdo->commit();
                    set_flash_message('info', "Payout request #{$payoutId} has been rejected.");
                }

                header('Location: payouts.php');
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                set_flash_message('error', 'Error processing payout: ' . $e->getMessage());
            }
        }
    }
}

// Fetch Pending Payouts
$stmtPending = $pdo->query("
    SELECT p.*, c.full_name as customer_name, c.account_number, c.phone,
           u.full_name as requested_by_name, sc.card_number, sc.spaces_filled, sc.daily_amount
    FROM payouts p
    JOIN customers c ON p.customer_id = c.id
    JOIN users u ON p.collector_id = u.id
    JOIN susu_cards sc ON p.card_id = sc.id
    WHERE p.status = 'pending'
    ORDER BY p.id ASC
");
$pendingPayouts = $stmtPending->fetchAll();

// Fetch Recent Settled Payouts (Miller's law chunking)
$stmtHistory = $pdo->query("
    SELECT p.*, c.full_name as customer_name, c.account_number,
           u.full_name as requested_by_name, admin.full_name as approved_by_name,
           sc.card_number, sc.spaces_filled
    FROM payouts p
    JOIN customers c ON p.customer_id = c.id
    JOIN users u ON p.collector_id = u.id
    LEFT JOIN users admin ON p.approved_by = admin.id
    JOIN susu_cards sc ON p.card_id = sc.id
    WHERE p.status IN ('paid', 'approved', 'rejected')
    ORDER BY p.id DESC LIMIT 15
");
$historyPayouts = $stmtHistory->fetchAll();

$pageTitle = "Customer Payouts";
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 section-card">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-steel_azure">Customer Payouts</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Manage cycle payouts, fee deductions, and cash disbursements.</p>
        </div>
        <div>
            <a href="request_payout.php" class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs sm:text-sm font-extrabold shadow-sm transition">
                + Request New Payout
            </a>
        </div>
    </div>

    <!-- Pending Payouts Section (Requires Attention) -->
    <div class="bg-white rounded-2xl border-2 border-silver-600 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-silver-600 flex items-center justify-between bg-platinum-800">
            <div>
                <h2 class="text-base font-black text-slate-800 flex items-center gap-2">
                    <span>Awaiting Admin Approval</span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= count($pendingPayouts) > 0 ? 'bg-pumpkin_spice text-white' : 'bg-platinum text-slate-500' ?>">
                        <?= count($pendingPayouts) ?>
                    </span>
                </h2>
                <p class="text-xs text-slate-500">Payout requests submitted by collectors for client cashout.</p>
            </div>
        </div>

        <div class="divide-y divide-silver-600/60">
            <?php if (empty($pendingPayouts)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon bg-emerald-50">✅</div>
                    <div class="empty-state-title">No Pending Requests</div>
                    <div class="empty-state-text">There are no pending payout requests requiring verification right now.</div>
                </div>
            <?php else: ?>
                <?php foreach ($pendingPayouts as $p): ?>
                    <div class="p-4 sm:p-5 hover:bg-platinum-900 transition flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-black text-sm sm:text-base text-slate-800"><?= htmlspecialchars($p['customer_name']) ?></span>
                                <span class="text-xs font-semibold text-slate-500 bg-platinum px-2 py-0.5 rounded">
                                    Card #<?= $p['card_number'] ?> &bull; <?= $p['spaces_filled'] ?>/31 spaces
                                </span>
                            </div>

                            <div class="text-xs text-slate-500 mt-1 flex flex-wrap gap-x-4">
                                <span>Requested by: <strong><?= htmlspecialchars($p['requested_by_name']) ?></strong></span>
                                <span>Date: <?= date('d M Y, h:i A', strtotime($p['created_at'])) ?></span>
                                <?php if (!empty($p['reason'])): ?>
                                    <span>Note: <em>"<?= htmlspecialchars($p['reason']) ?>"</em></span>
                                <?php endif; ?>
                            </div>

                            <!-- Financial Breakdown Chip -->
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                <span class="bg-platinum px-2.5 py-1 rounded-md text-slate-700">
                                    Gross Saved: <strong><?= format_money($p['total_saved']) ?></strong>
                                </span>
                                <span class="bg-red-50 text-red-700 border border-red-200 px-2.5 py-1 rounded-md">
                                    Fee Deducted: <strong>- <?= format_money($p['business_fee']) ?></strong>
                                </span>
                                <?php if ($p['change_refunded'] > 0): ?>
                                    <span class="bg-amber-50 text-pumpkin_spice border border-amber-200 px-2.5 py-1 rounded-md">
                                        Float Added: <strong>+ <?= format_money($p['change_refunded']) ?></strong>
                                    </span>
                                <?php endif; ?>
                                <span class="bg-emerald-50 text-emerald-800 border border-emerald-300 px-3 py-1 rounded-md font-black text-xs sm:text-sm">
                                    Cash to Hand Customer: <?= format_money($p['customer_payout']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Action Buttons (Hick's Law: Primary solid + Secondary outlined) -->
                        <div class="flex items-center gap-2 pt-2 lg:pt-0">
                            <?php if ($user['role'] === 'admin'): ?>
                                <form method="POST" action="payouts.php" onsubmit="return confirm('Confirm disbursement of <?= format_money($p['customer_payout']) ?> to <?= addslashes($p['customer_name']) ?>? This will close Card #<?= $p['card_number'] ?>.');">
                                    <input type="hidden" name="payout_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="action" value="approve_and_pay">
                                    <button type="submit" class="btn-touch bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black px-4 py-2 shadow-sm transition">
                                        ✓ Approve & Disburse Cash
                                    </button>
                                </form>
                                <form method="POST" action="payouts.php" onsubmit="return confirm('Reject this payout request?');">
                                    <input type="hidden" name="payout_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn-touch bg-white text-red-600 border border-red-300 hover:bg-red-50 text-xs font-bold px-3 py-2 transition">
                                        Reject
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs font-bold text-amber-600 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-lg">
                                    Pending Admin Verification
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payout History Table -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-silver-600/70">
            <h2 class="text-base font-bold text-slate-800">Completed Payout History</h2>
            <p class="text-xs text-slate-500">Historical records of closed cards and disbursements.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-platinum text-slate-600 font-semibold border-b border-silver-600/70">
                    <tr>
                        <th class="py-3 px-4">Date Paid</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Spaces</th>
                        <th class="py-3 px-4">Gross Saved</th>
                        <th class="py-3 px-4">Business Fee</th>
                        <th class="py-3 px-4">Amount Paid</th>
                        <th class="py-3 px-4">Approved By</th>
                        <th class="py-3 px-4 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-silver-600/50">
                    <?php if (empty($historyPayouts)): ?>
                        <tr>
                            <td colspan="8" class="text-center">
                                <div class="empty-state">
                                    <div class="empty-state-icon bg-slate-100">📋</div>
                                    <div class="empty-state-title">No Payout History</div>
                                    <div class="empty-state-text">Completed payout settlements will appear here.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historyPayouts as $hp): ?>
                            <tr class="hover:bg-platinum-800 transition">
                                <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                    <?= $hp['paid_at'] ? date('d M Y', strtotime($hp['paid_at'])) : date('d M Y', strtotime($hp['created_at'])) ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($hp['customer_name']) ?></div>
                                    <div class="text-[11px] text-slate-400"><?= htmlspecialchars($hp['account_number']) ?></div>
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-700 whitespace-nowrap">
                                    <?= $hp['spaces_filled'] ?> / 31 spaces
                                </td>
                                <td class="py-3 px-4 font-medium text-slate-600 whitespace-nowrap">
                                    <?= format_money($hp['total_saved']) ?>
                                </td>
                                <td class="py-3 px-4 font-bold text-pumpkin_spice whitespace-nowrap">
                                    <?= format_money($hp['business_fee']) ?>
                                </td>
                                <td class="py-3 px-4 font-extrabold text-emerald-600 whitespace-nowrap">
                                    <?= format_money($hp['customer_payout']) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                    <?= htmlspecialchars($hp['approved_by_name'] ?: 'N/A') ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold <?= $hp['status'] === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= strtoupper($hp['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
