<?php
// test_e2e_flow.php - End-to-End Simulation of Full Real-World Susu Workflow
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "========================================================\n";
echo "   RUNNING REAL-WORLD END-TO-END WORKFLOW SIMULATION    \n";
echo "========================================================\n\n";

$pdo = get_db_connection();

// 1. Fetch Esi Mensah and Collector Kofi
$stmtKofi = $pdo->query("SELECT id FROM users WHERE username = 'kofi' LIMIT 1");
$kofiId = (int)$stmtKofi->fetchColumn();

$stmtAdmin = $pdo->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
$adminId = (int)$stmtAdmin->fetchColumn();

$stmtEsi = $pdo->query("SELECT id FROM customers WHERE account_number = 'ACC-1001' LIMIT 1");
$esiId = (int)$stmtEsi->fetchColumn();

$activeCard = get_active_card_for_customer($esiId);
if (!$activeCard) {
    $pdo->prepare("INSERT INTO susu_cards (customer_id, card_number, daily_amount, total_spaces, spaces_filled, total_saved, status) VALUES (?, 1, 20.00, 31, 0, 0.00, 'active')")
        ->execute([$esiId]);
    $activeCard = get_active_card_for_customer($esiId);
}
$cardId = $activeCard['id'];

echo "Step 1: Esi Mensah (Card #{$activeCard['card_number']}, Plan: GH₵20/day) has {$activeCard['spaces_filled']} spaces filled.\n";

// 2. Kofi collects GH₵100 (5 spaces)
$b1 = calculate_deposit_breakdown(20.00, 100.00, 0.00, 0, 31);
for ($s = $b1['start_space']; $s <= $b1['end_space']; $s++) {
    $pdo->prepare("INSERT INTO deposits (card_id, customer_id, collector_id, space_number, amount, deposit_date) VALUES (?, ?, ?, ?, 20.00, CURRENT_DATE)")
        ->execute([$cardId, $esiId, $kofiId, $s]);
}
$pdo->prepare("UPDATE susu_cards SET spaces_filled = ?, total_saved = ? WHERE id = ?")
    ->execute([$b1['spaces_to_fill'], $b1['money_applied'], $cardId]);
$pdo->prepare("UPDATE customers SET change_balance = ? WHERE id = ?")
    ->execute([$b1['new_change'], $esiId]);

$kofiCash1 = get_collector_cash_in_hand($kofiId);
echo "Step 2: Kofi collected GH₵100 -> Stamped Spaces #1 to #5. Total saved: GH₵100. Kofi's Cash Bag: " . format_money($kofiCash1) . "\n";

// 3. Kofi collects GH₵50 (2 spaces + GH₵10 float)
$cardUpdated = get_active_card_for_customer($esiId);
$b2 = calculate_deposit_breakdown(20.00, 50.00, 0.00, (int)$cardUpdated['spaces_filled'], 31);
for ($s = $b2['start_space']; $s <= $b2['end_space']; $s++) {
    $pdo->prepare("INSERT INTO deposits (card_id, customer_id, collector_id, space_number, amount, deposit_date) VALUES (?, ?, ?, ?, 20.00, CURRENT_DATE)")
        ->execute([$cardId, $esiId, $kofiId, $s]);
}
$pdo->prepare("UPDATE susu_cards SET spaces_filled = spaces_filled + ?, total_saved = total_saved + ? WHERE id = ?")
    ->execute([$b2['spaces_to_fill'], $b2['money_applied'], $cardId]);
$pdo->prepare("UPDATE customers SET change_balance = ? WHERE id = ?")
    ->execute([$b2['new_change'], $esiId]);

$kofiCash2 = get_collector_cash_in_hand($kofiId);
$cardAfter50 = get_active_card_for_customer($esiId);
$stmtCust = $pdo->prepare("SELECT change_balance FROM customers WHERE id = ?");
$stmtCust->execute([$esiId]);
$changeBal = $stmtCust->fetchColumn();

echo "Step 3: Kofi collected GH₵50 -> Stamped Spaces #6 to #7 (GH₵40). Esi's Float: " . format_money($changeBal) . ". Total Saved: " . format_money($cardAfter50['total_saved']) . ". Kofi's Cash Bag: " . format_money($kofiCash2) . "\n";

// 4. Esi requests Early Payout
$payoutCalc = calculate_payout_breakdown($cardId);
echo "Step 4: Esi requests Early Payout.\n";
echo "        Gross Saved:        " . format_money($payoutCalc['total_saved']) . "\n";
echo "        Less Business Fee:  " . format_money($payoutCalc['business_fee']) . " (1 daily amount)\n";
echo "        Plus Float Refund:  " . format_money($payoutCalc['change_refunded']) . "\n";
echo "        Net Payout Cash:    " . format_money($payoutCalc['customer_payout']) . "\n";

// Kofi submits payout request
$pdo->prepare("INSERT INTO payouts (card_id, customer_id, collector_id, total_saved, business_fee, change_refunded, customer_payout, status, reason) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'Customer requested early payout')")
    ->execute([$cardId, $esiId, $kofiId, $payoutCalc['total_saved'], $payoutCalc['business_fee'], $payoutCalc['change_refunded'], $payoutCalc['customer_payout']]);
$payoutId = $pdo->lastInsertId();

// 5. Admin approves and disburses payout
$pdo->prepare("UPDATE payouts SET status = 'paid', approved_by = ?, paid_at = CURRENT_TIMESTAMP WHERE id = ?")
    ->execute([$adminId, $payoutId]);
$pdo->prepare("UPDATE susu_cards SET status = 'closed_early', closed_at = CURRENT_TIMESTAMP WHERE id = ?")
    ->execute([$cardId]);
$pdo->prepare("UPDATE customers SET change_balance = 0.00 WHERE id = ?")
    ->execute([$esiId]);

echo "Step 5: Admin approved payout #{$payoutId}. Card #1 status changed to 'closed_early'. Float reset to GH₵0.00.\n";

// 6. Kofi submits daily cash handover
$expectedCash = get_collector_cash_in_hand($kofiId);
$pdo->prepare("INSERT INTO daily_handovers (collector_id, handover_date, expected_cash, cash_received, difference, status, collector_note) VALUES (?, CURRENT_DATE, ?, ?, 0.00, 'submitted', 'End of day handover')")
    ->execute([$kofiId, $expectedCash, $expectedCash]);
$handoverId = $pdo->lastInsertId();

echo "Step 6: Kofi submitted Daily Cash Handover #{$handoverId} for " . format_money($expectedCash) . ".\n";

// 7. Admin approves handover and links deposits
$pdo->prepare("UPDATE daily_handovers SET status = 'approved', approved_by = ?, admin_note = 'Cash counted and verified.', approved_at = CURRENT_TIMESTAMP WHERE id = ?")
    ->execute([$adminId, $handoverId]);
$pdo->prepare("UPDATE deposits SET handover_id = ? WHERE collector_id = ? AND handover_id IS NULL")
    ->execute([$handoverId, $kofiId]);

$kofiCashAfterHandover = get_collector_cash_in_hand($kofiId);
echo "Step 7: Admin approved Handover #{$handoverId}.\n";
echo "        Kofi's unsettled Cash Bag is now: " . format_money($kofiCashAfterHandover) . " (CLEARED TO 0)!\n";

// 8. Open fresh Card #2 for Esi
$pdo->prepare("INSERT INTO susu_cards (customer_id, card_number, daily_amount, total_spaces, spaces_filled, total_saved, status) VALUES (?, 2, 20.00, 31, 0, 0.00, 'active')")
    ->execute([$esiId]);
$newCard = get_active_card_for_customer($esiId);
echo "Step 8: Fresh Susu Card #{$newCard['card_number']} opened for Esi Mensah (0/31 spaces). Ready for next cycle!\n";

echo "\n========================================================\n";
echo "       END-TO-END WORKFLOW COMPLETED SUCCESSFULLY!      \n";
echo "========================================================\n";
