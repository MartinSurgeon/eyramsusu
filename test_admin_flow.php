<?php
// test_admin_flow.php - Simulates Admin Operations
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db_connection();

echo "========================================================\n";
echo "           EXECUTING ADMIN OPERATIONS FLOW              \n";
echo "========================================================\n\n";

// 1. Fetch Admin ID
$adminId = (int)$pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
$kofiId = (int)$pdo->query("SELECT id FROM users WHERE username = 'kofi'")->fetchColumn();

echo "Step 1: Admin logs in (User ID: {$adminId}).\n";

// 2. Admin inspects dashboard stats
$stats = get_admin_dashboard_stats();
echo "Step 2: Admin Dashboard KPIs checked:\n";
echo "        - Today's Collections: " . format_money($stats['today_collections']) . "\n";
echo "        - Pending Payouts:     " . $stats['pending_payouts'] . "\n";
echo "        - Active Cards:        " . $stats['active_cards'] . "\n";

// 3. Admin reviews & approves Payout #1
$stmtP = $pdo->query("SELECT * FROM payouts WHERE status = 'pending' ORDER BY id ASC LIMIT 1");
$payout = $stmtP->fetch();

if ($payout) {
    echo "\nStep 3: Admin reviews Pending Payout #{$payout['id']} for Customer ID {$payout['customer_id']}:\n";
    echo "        - Gross Saved:        " . format_money($payout['total_saved']) . "\n";
    echo "        - Business Fee Taken: " . format_money($payout['business_fee']) . " (1 daily rate)\n";
    echo "        - Float Refunded:     " . format_money($payout['change_refunded']) . "\n";
    echo "        - Net Cash Disbursed: " . format_money($payout['customer_payout']) . "\n";

    // Approve & Disburse
    $pdo->prepare("UPDATE payouts SET status = 'paid', approved_by = ?, paid_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$adminId, $payout['id']]);

    // Close Card
    $stmtCard = $pdo->prepare("SELECT * FROM susu_cards WHERE id = ?");
    $stmtCard->execute([$payout['card_id']]);
    $c = $stmtCard->fetch();
    $newStatus = ($c['spaces_filled'] >= $c['total_spaces']) ? 'completed' : 'closed_early';

    $pdo->prepare("UPDATE susu_cards SET status = ?, closed_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$newStatus, $payout['card_id']]);

    // Clear float
    $pdo->prepare("UPDATE customers SET change_balance = 0.00 WHERE id = ?")
        ->execute([$payout['customer_id']]);

    echo "        -> [APPROVED & DISBURSED] Card #{$c['card_number']} closed ({$newStatus}). Float reset to GH₵0.00.\n";
}

// 4. Admin opens fresh Card #2 for Esi Mensah
$esiId = (int)$pdo->query("SELECT id FROM customers WHERE account_number = 'ACC-1001'")->fetchColumn();
$stmtNextCard = $pdo->prepare("
    INSERT INTO susu_cards (customer_id, card_number, daily_amount, total_spaces, spaces_filled, total_saved, status)
    VALUES (?, 2, 20.00, 31, 0, 0.00, 'active')
");
$stmtNextCard->execute([$esiId]);
$newCardId = $pdo->lastInsertId();
echo "\nStep 4: Admin opens fresh Susu Card #2 for Esi Mensah (ID: {$newCardId}, Plan: GH₵20.00, 0/31 spaces).\n";

// 5. Admin registers a new customer: Kwame Asante
$accountNum = 'ACC-' . str_pad((int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() + 1001, 4, '0', STR_PAD_LEFT);
$stmtNewCust = $pdo->prepare("
    INSERT INTO customers (account_number, full_name, phone, location, assigned_collector_id, change_balance)
    VALUES (?, 'Kwame Asante', '0245558899', 'Madina Market, Stall 12', ?, 0.00)
");
$stmtNewCust->execute([$accountNum, $kofiId]);
$kwameId = $pdo->lastInsertId();

$stmtKwameCard = $pdo->prepare("
    INSERT INTO susu_cards (customer_id, card_number, daily_amount, total_spaces, spaces_filled, total_saved, status)
    VALUES (?, 1, 50.00, 31, 0, 0.00, 'active')
");
$stmtKwameCard->execute([$kwameId]);
echo "\nStep 5: Admin registers new customer 'Kwame Asante' ({$accountNum}), assigned to Collector Kofi with a GH₵50.00/day 31-space card.\n";

// 6. Collector Kofi submits Daily Cash Handover of his bag
$kofiCash = get_collector_cash_in_hand($kofiId);
echo "\nStep 6: Kofi's unsettled Cash Bag is " . format_money($kofiCash) . ".\n";
$stmtH = $pdo->prepare("
    INSERT INTO daily_handovers (collector_id, handover_date, expected_cash, cash_received, difference, status, collector_note)
    VALUES (?, CURRENT_DATE, ?, ?, 0.00, 'submitted', 'End of day market handover to admin')
");
$stmtH->execute([$kofiId, $kofiCash, $kofiCash]);
$handoverId = $pdo->lastInsertId();
echo "        Kofi submitted Daily Cash Handover #{$handoverId} for " . format_money($kofiCash) . ".\n";

// 7. Admin verifies physical cash and approves settlement
$pdo->prepare("
    UPDATE daily_handovers 
    SET status = 'approved', approved_by = ?, admin_note = 'Cash counted, verified, exact match.', approved_at = CURRENT_TIMESTAMP 
    WHERE id = ?
")->execute([$adminId, $handoverId]);

$pdo->prepare("UPDATE deposits SET handover_id = ? WHERE collector_id = ? AND handover_id IS NULL")
    ->execute([$handoverId, $kofiId]);

$kofiCashAfter = get_collector_cash_in_hand($kofiId);
echo "\nStep 7: Admin approves Handover #{$handoverId}.\n";
echo "        -> Collector Kofi's cash in hand liability is now: " . format_money($kofiCashAfter) . " (CLEARED TO 0)!\n";

// 8. Admin reviews Daily Report
$reportDate = date('Y-m-d');
$totalToday = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0.00) FROM deposits WHERE deposit_date = CURRENT_DATE")->fetchColumn();
$totalFees = (float)$pdo->query("SELECT COALESCE(SUM(business_fee), 0.00) FROM payouts WHERE status = 'paid'")->fetchColumn();
$totalDisbursed = (float)$pdo->query("SELECT COALESCE(SUM(customer_payout), 0.00) FROM payouts WHERE status = 'paid'")->fetchColumn();

echo "\nStep 8: Admin checks Daily Report for {$reportDate}:\n";
echo "        - Total Collections Today: " . format_money($totalToday) . "\n";
echo "        - Total Business Fees Earned: " . format_money($totalFees) . "\n";
echo "        - Total Payouts Disbursed:   " . format_money($totalDisbursed) . "\n";
echo "        - Net Cash in Office:        " . format_money($totalToday - $totalDisbursed) . "\n";

echo "\n========================================================\n";
echo "       ADMIN OPERATIONS FLOW COMPLETED SUCCESSFULLY!    \n";
echo "========================================================\n";
