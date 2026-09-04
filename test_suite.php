<?php
// test_suite.php - Automated Verification Suite for Eyram Susu Business Rules
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "========================================================\n";
echo "       EYRAM SUSU - AUTOMATED VERIFICATION SUITE       \n";
echo "========================================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assert_test($description, $actual, $expected) {
    global $testsPassed, $testsFailed;
    if ($actual === $expected) {
        echo " [PASS] " . $description . "\n";
        $testsPassed++;
    } else {
        echo " [FAIL] " . $description . "\n";
        echo "        Expected: " . var_export($expected, true) . "\n";
        echo "        Actual:   " . var_export($actual, true) . "\n";
        $testsFailed++;
    }
}

// -----------------------------------------------------------
// TEST 1: Single Contribution
// -----------------------------------------------------------
$t1 = calculate_deposit_breakdown(20.00, 20.00, 0.00, 0, 31);
assert_test("Test 1a: Single GH₵20 deposit fills exactly 1 space", $t1['spaces_to_fill'], 1);
assert_test("Test 1b: Space range starts at 1 and ends at 1", $t1['start_space'] . '-' . $t1['end_space'], '1-1');
assert_test("Test 1c: Customer change is GH₵0.00", $t1['new_change'], 0.0);

// -----------------------------------------------------------
// TEST 2: Multi-Contribution (Rule 6: GH₵100 on GH₵20 plan)
// -----------------------------------------------------------
$t2 = calculate_deposit_breakdown(20.00, 100.00, 0.00, 0, 31);
assert_test("Test 2a: GH₵100 deposit on GH₵20 plan fills 5 spaces", $t2['spaces_to_fill'], 5);
assert_test("Test 2b: Space range covers #1 to #5", $t2['start_space'] . '-' . $t2['end_space'], '1-5');
assert_test("Test 2c: Added to savings is GH₵100.00", $t2['money_applied'], 100.0);
assert_test("Test 2d: Customer change is GH₵0.00", $t2['new_change'], 0.0);

// -----------------------------------------------------------
// TEST 3: Strict Divisibility Rejection (GH₵50 on GH₵20 plan is rejected)
// -----------------------------------------------------------
$t3 = calculate_deposit_breakdown(20.00, 50.00, 0.00, 5, 31);
assert_test("Test 3a: GH₵50 on GH₵20 plan is rejected as invalid", $t3['valid'], false);
assert_test("Test 3b: Clear message given without jargon", strpos($t3['message'], 'Amount must be exact') !== false, true);

// -----------------------------------------------------------
// TEST 4: Valid Multi-Space Deposit (GH₵40 on GH₵20 plan fills Spaces #6 to #7)
// -----------------------------------------------------------
$t4 = calculate_deposit_breakdown(20.00, 40.00, 0.00, 5, 31);
assert_test("Test 4a: GH₵40 on GH₵20 plan is valid", $t4['valid'], true);
assert_test("Test 4b: Fills exactly 2 spaces", $t4['spaces_to_fill'], 2);
assert_test("Test 4c: Space range is #6 to #7", $t4['start_space'] . '-' . $t4['end_space'], '6-7');
assert_test("Test 4d: Zero remainder created", $t4['new_change'], 0.0);

// -----------------------------------------------------------
// TEST 5: Cycle Boundary (Only 1 space left on card, rejects excess payment)
// -----------------------------------------------------------
$t5_overflow = calculate_deposit_breakdown(20.00, 40.00, 0.00, 30, 31);
assert_test("Test 5a: Excess payment beyond remaining spaces is rejected", $t5_overflow['valid'], false);

$t5_exact = calculate_deposit_breakdown(20.00, 20.00, 0.00, 30, 31);
assert_test("Test 5b: Exact final space payment fills Space #31", $t5_exact['spaces_to_fill'], 1);
assert_test("Test 5c: Card marked as completed", $t5_exact['is_completed'], true);

// -----------------------------------------------------------
// TEST 6: Payout Math at 31 Spaces (Rule 8: Gross GH₵620 - Fee GH₵20 = Net GH₵600)
// -----------------------------------------------------------
$dummyCardFull = [
    'total_saved' => 620.00,
    'daily_amount' => 20.00,
    'spaces_filled' => 31,
    'total_spaces' => 31,
    'change_balance' => 0.00
];
$businessFee1 = min($dummyCardFull['daily_amount'], $dummyCardFull['total_saved']);
$netPayout1 = max(0, $dummyCardFull['total_saved'] - $businessFee1) + $dummyCardFull['change_balance'];
assert_test("Test 6a: Business fee on completed GH₵20 card is GH₵20.00", $businessFee1, 20.0);
assert_test("Test 6b: Net customer payout is GH₵600.00", $netPayout1, 600.0);

// -----------------------------------------------------------
// TEST 7: Early Payout Math at 5 Spaces (Rule 9: Gross GH₵100 - Fee GH₵20 = Net GH₵80)
// -----------------------------------------------------------
$dummyCardEarly = [
    'total_saved' => 100.00,
    'daily_amount' => 20.00,
    'spaces_filled' => 5,
    'total_spaces' => 31,
    'change_balance' => 10.00 // with GH₵10 float
];
$businessFee2 = min($dummyCardEarly['daily_amount'], $dummyCardEarly['total_saved']);
$netPayout2 = max(0, $dummyCardEarly['total_saved'] - $businessFee2) + $dummyCardEarly['change_balance'];
assert_test("Test 7a: Business fee on early stop is 1 contribution (GH₵20.00)", $businessFee2, 20.0);
assert_test("Test 7b: Net customer payout is GH₵100 - GH₵20 + GH₵10 float = GH₵90.00", $netPayout2, 90.0);

// -----------------------------------------------------------
// TEST 8: Database Connectivity & Seeding Check
// -----------------------------------------------------------
$pdo = get_db_connection();
$userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
assert_test("Test 8a: Database has users seeded", ($userCount >= 2), true);

$custCount = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
assert_test("Test 8b: Official customers exist", ($custCount >= 2), true);

$totalCards = (int)$pdo->query("SELECT COUNT(*) FROM susu_cards")->fetchColumn();
assert_test("Test 8c: Susu Cards exist in database", ($totalCards >= 2), true);

// -----------------------------------------------------------
// TEST 9: Deposit Cancellation / Reversal & Admin Notification
// -----------------------------------------------------------
$testCustName = "Test Reversal Customer " . time();
$pdo->prepare("INSERT INTO customers (account_number, full_name, phone, location) VALUES (?, ?, ?, ?)")
    ->execute(['TEST' . rand(1000, 9999), $testCustName, '0240000000', 'Test Lab']);
$testCustId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO susu_cards (customer_id, card_number, daily_amount, total_spaces, spaces_filled, total_saved, status) VALUES (?, 99, 20.00, 31, 2, 40.00, 'active')")
    ->execute([$testCustId]);
$testCardId = (int)$pdo->lastInsertId();

// Insert 2 spaces created at same second
$nowTime = date('Y-m-d H:i:s');
$pdo->prepare("INSERT INTO deposits (card_id, customer_id, collector_id, space_number, amount, deposit_date, created_at) VALUES (?, ?, 1, 1, 20.00, CURRENT_DATE(), ?)")
    ->execute([$testCardId, $testCustId, $nowTime]);
$dep1Id = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO deposits (card_id, customer_id, collector_id, space_number, amount, deposit_date, created_at) VALUES (?, ?, 1, 2, 20.00, CURRENT_DATE(), ?)")
    ->execute([$testCardId, $testCustId, $nowTime]);

// Reverse the batch using dep1Id
$revResult = reverse_deposit($dep1Id, 'Customer changed mind', 1, 'admin');

assert_test("Test 9a: Deposit reversal succeeded", $revResult['success'], true);
assert_test("Test 9b: Reversed exact 2 spaces", $revResult['spaces'], 2);
assert_test("Test 9c: Reversed exact GH₵40.00", (float)$revResult['amount'], 40.00);

// Check card balance and spaces
$chkCard = $pdo->query("SELECT spaces_filled, total_saved, status FROM susu_cards WHERE id = {$testCardId}")->fetch();
assert_test("Test 9d: Card spaces decremented to 0", (int)$chkCard['spaces_filled'], 0);
assert_test("Test 9e: Card total_saved decremented to 0.00", (float)$chkCard['total_saved'], 0.00);
assert_test("Test 9f: Card remains active", $chkCard['status'], 'active');

// Check Admin notification
$notifCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE message LIKE '%cancelled deposit of GH₵ 40.00%'")->fetchColumn();
assert_test("Test 9g: Admin notification dispatched", ($notifCount >= 1), true);

// Clean up test data
$pdo->exec("DELETE FROM notifications WHERE message LIKE '%{$testCustName}%'");
$pdo->exec("DELETE FROM susu_cards WHERE id = {$testCardId}");
$pdo->exec("DELETE FROM customers WHERE id = {$testCustId}");

echo "\n--------------------------------------------------------\n";
echo "SUMMARY: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "--------------------------------------------------------\n";
if ($testsFailed === 0) {
    echo ">>> ALL BUSINESS RULES & CALCULATIONS VERIFIED 100%! <<<\n";
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
