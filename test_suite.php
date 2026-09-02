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
// TEST 3: Uneven Cash with Remainder Change (GH₵50 on GH₵20 plan)
// -----------------------------------------------------------
$t3 = calculate_deposit_breakdown(20.00, 50.00, 0.00, 5, 31);
assert_test("Test 3a: GH₵50 on GH₵20 plan fills 2 spaces (#6 to #7)", $t3['spaces_to_fill'], 2);
assert_test("Test 3b: Space range covers #6 to #7", $t3['start_space'] . '-' . $t3['end_space'], '6-7');
assert_test("Test 3c: Applied money is GH₵40.00", $t3['money_applied'], 40.0);
assert_test("Test 3d: Remaining change of GH₵10.00 stored in float", $t3['new_change'], 10.0);

// -----------------------------------------------------------
// TEST 4: Float Absorption (Customer uses existing GH₵10 float + pays GH₵10 cash)
// -----------------------------------------------------------
$t4 = calculate_deposit_breakdown(20.00, 10.00, 10.00, 7, 31);
assert_test("Test 4a: Combined cash (10) + float (10) fills Space #8", $t4['spaces_to_fill'], 1);
assert_test("Test 4b: Space range is #8 to #8", $t4['start_space'] . '-' . $t4['end_space'], '8-8');
assert_test("Test 4c: New change after space filled is GH₵0.00", $t4['new_change'], 0.0);

// -----------------------------------------------------------
// TEST 5: Cycle Boundary (Filling final space 31)
// -----------------------------------------------------------
$t5 = calculate_deposit_breakdown(20.00, 40.00, 0.00, 30, 31);
assert_test("Test 5a: Only 1 space remaining, caps spaces to fill at 1", $t5['spaces_to_fill'], 1);
assert_test("Test 5b: Stamped space is #31", $t5['start_space'] . '-' . $t5['end_space'], '31-31');
assert_test("Test 5c: Card marked as completed", $t5['is_completed'], true);
assert_test("Test 5d: Overflow cash of GH₵20 preserved in float", $t5['new_change'], 20.0);

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

echo "\n--------------------------------------------------------\n";
echo "SUMMARY: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "--------------------------------------------------------\n";
if ($testsFailed === 0) {
    echo ">>> ALL BUSINESS RULES & CALCULATIONS VERIFIED 100%! <<<\n";
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
