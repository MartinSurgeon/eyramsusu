<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db_connection();

echo "=== EYRAM SUSU SYSTEM STATUS ===\n";
echo "Users Count:      " . $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() . "\n";
echo "Customers Count:  " . $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() . "\n";
echo "Cards Count:      " . $pdo->query("SELECT COUNT(*) FROM susu_cards")->fetchColumn() . "\n";
echo "Deposits Count:   " . $pdo->query("SELECT COUNT(*) FROM deposits")->fetchColumn() . "\n";
echo "Payouts Count:    " . $pdo->query("SELECT COUNT(*) FROM payouts")->fetchColumn() . "\n";
echo "Handovers Count:  " . $pdo->query("SELECT COUNT(*) FROM daily_handovers")->fetchColumn() . "\n\n";

echo "=== COLLECTOR BAGS (CASH IN HAND) ===\n";
$stmtCol = $pdo->query("SELECT id, full_name, role FROM users WHERE role = 'collector'");
while ($col = $stmtCol->fetch()) {
    $cash = get_collector_cash_in_hand($col['id']);
    echo "- " . $col['full_name'] . " (ID " . $col['id'] . "): " . format_money($cash) . " unsettled\n";
}

echo "\n=== ACTIVE CARDS ===\n";
$stmtCards = $pdo->query("
    SELECT sc.id, sc.card_number, sc.daily_amount, sc.spaces_filled, sc.total_saved, sc.status,
           c.full_name, c.account_number, c.change_balance
    FROM susu_cards sc
    JOIN customers c ON sc.customer_id = c.id
    ORDER BY sc.id ASC
");
while ($c = $stmtCards->fetch()) {
    echo "- " . $c['full_name'] . " (" . $c['account_number'] . "): Card #" . $c['card_number'] . 
         " [Plan: " . format_money($c['daily_amount']) . "], Spaces: " . $c['spaces_filled'] . "/31, Saved: " . 
         format_money($c['total_saved']) . ", Float: " . format_money($c['change_balance']) . " (" . $c['status'] . ")\n";
}

echo "\n=== PENDING PAYOUTS ===\n";
$stmtPay = $pdo->query("SELECT p.*, c.full_name FROM payouts p JOIN customers c ON p.customer_id = c.id WHERE p.status = 'pending'");
$pendingPays = $stmtPay->fetchAll();
if (empty($pendingPays)) {
    echo "No pending payouts.\n";
} else {
    foreach ($pendingPays as $p) {
        echo "- Payout #" . $p['id'] . " for " . $p['full_name'] . ": Net Payout = " . format_money($p['customer_payout']) . " (Fee: " . format_money($p['business_fee']) . ")\n";
    }
}

echo "\n=== PENDING HANDOVERS ===\n";
$stmtH = $pdo->query("SELECT h.*, u.full_name FROM daily_handovers h JOIN users u ON h.collector_id = u.id WHERE h.status = 'submitted'");
$pendingH = $stmtH->fetchAll();
if (empty($pendingH)) {
    echo "No pending handovers.\n";
} else {
    foreach ($pendingH as $h) {
        echo "- Handover #" . $h['id'] . " from " . $h['full_name'] . ": Expected " . format_money($h['expected_cash']) . ", Tendered " . format_money($h['cash_received']) . "\n";
    }
}
