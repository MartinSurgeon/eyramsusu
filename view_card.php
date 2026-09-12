<?php
// view_card.php - Visual 31-Space Susu Card Passbook
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = get_logged_in_user();
$pdo = get_db_connection();

$cardId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$cardId && isset($_GET['customer_id'])) {
    $activeCard = get_active_card_for_customer((int)$_GET['customer_id']);
    if ($activeCard) {
        $cardId = $activeCard['id'];
    }
}

if (!$cardId) {
    header('Location: customers.php');
    exit;
}

// Fetch Card and Customer
$stmt = $pdo->prepare("
    SELECT sc.*, c.full_name, c.account_number, c.phone, c.location, c.change_balance,
           u.full_name as collector_name
    FROM susu_cards sc
    JOIN customers c ON sc.customer_id = c.id
    LEFT JOIN users u ON c.assigned_collector_id = u.id
    WHERE sc.id = ?
");
$stmt->execute([$cardId]);
$card = $stmt->fetch();

if (!$card) {
    set_flash_message('error', 'Susu Card not found.');
    header('Location: customers.php');
    exit;
}

// Handle Undo / Cancel Deposit POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'undo_deposit') {
    $depositId = (int)($_POST['deposit_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Customer changed mind');

    $res = reverse_deposit($depositId, $reason, $user['id'], $user['role']);
    if ($res['success']) {
        set_flash_message('success', $res['message']);
        header("Location: view_card.php?id=" . $cardId);
        exit;
    } else {
        set_flash_message('error', $res['message']);
        header("Location: view_card.php?id=" . $cardId);
        exit;
    }
}

// Handle Adjust Card Daily Rate (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_card_rate') {
    if ($user['role'] !== 'admin') {
        set_flash_message('error', 'Only Administrators are authorized to adjust card daily rates.');
        header("Location: view_card.php?id=" . $cardId);
        exit;
    }

    // Check if card is already settled and paid out
    $stmtCheckPaid = $pdo->prepare("SELECT status FROM payouts WHERE card_id = ? AND status = 'paid' LIMIT 1");
    $stmtCheckPaid->execute([$cardId]);
    if ($stmtCheckPaid->fetchColumn()) {
        set_flash_message('error', 'Cannot adjust rate on a card that has already been settled and paid out.');
        header("Location: view_card.php?id=" . $cardId);
        exit;
    }

    $newDailyAmount = (float)($_POST['new_daily_amount'] ?? 0);
    $recalculateDeposits = !empty($_POST['recalculate_deposits']);
    $reason = trim($_POST['reason'] ?? '');

    if ($newDailyAmount <= 0) {
        set_flash_message('error', 'Please enter a valid daily savings rate greater than zero.');
        header("Location: view_card.php?id=" . $cardId);
        exit;
    }

    if (empty($reason)) {
        set_flash_message('error', 'Please provide an audit reason for adjusting this card rate.');
        header("Location: view_card.php?id=" . $cardId);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $oldRate = (float)$card['daily_amount'];
        $spacesFilled = (int)$card['spaces_filled'];

        if ($recalculateDeposits && $spacesFilled > 0) {
            // Update all deposit space records for this card
            $stmtUpdDep = $pdo->prepare("UPDATE deposits SET amount = ? WHERE card_id = ?");
            $stmtUpdDep->execute([$newDailyAmount, $cardId]);

            // Recalculate total saved based on filled spaces and new rate
            $newTotalSaved = round($spacesFilled * $newDailyAmount, 2);

            $stmtUpdCard = $pdo->prepare("UPDATE susu_cards SET daily_amount = ?, total_saved = ? WHERE id = ?");
            $stmtUpdCard->execute([$newDailyAmount, $newTotalSaved, $cardId]);
        } else {
            // Only update daily rate without modifying past deposit amounts
            $newTotalSaved = (float)$card['total_saved'];
            $stmtUpdCard = $pdo->prepare("UPDATE susu_cards SET daily_amount = ? WHERE id = ?");
            $stmtUpdCard->execute([$newDailyAmount, $cardId]);
        }

        // If there is a pending payout, recalculate its numbers
        $stmtPendingPayout = $pdo->prepare("SELECT id FROM payouts WHERE card_id = ? AND status = 'pending' LIMIT 1");
        $stmtPendingPayout->execute([$cardId]);
        if ($pendingPayoutId = $stmtPendingPayout->fetchColumn()) {
            $newFee = min($newDailyAmount, $newTotalSaved);
            $newCustomerPayout = max(0, $newTotalSaved - $newFee) + (float)$card['change_balance'];
            $stmtUpdPending = $pdo->prepare("
                UPDATE payouts 
                SET total_saved = ?, business_fee = ?, customer_payout = ? 
                WHERE id = ?
            ");
            $stmtUpdPending->execute([$newTotalSaved, $newFee, $newCustomerPayout, $pendingPayoutId]);
        }

        // Audit Trail
        $recalcNote = $recalculateDeposits ? "Recalculated {$spacesFilled} deposit space(s) to GH₵ " . number_format($newDailyAmount, 2) . " (New Total Saved: GH₵ " . number_format($newTotalSaved, 2) . ")" : "Past deposits unchanged";
        log_audit_event(
            $user['id'],
            'adjust_card_rate',
            "Adjusted Card #{$card['card_number']} rate for {$card['full_name']} (#{$card['account_number']}) from GH₵ " . number_format($oldRate, 2) . " to GH₵ " . number_format($newDailyAmount, 2) . ". {$recalcNote}. Reason: {$reason}"
        );

        // Notify Admin group
        create_notification(
            null,
            'info',
            "Card #{$card['card_number']} Rate Adjusted: {$card['full_name']}",
            "Admin {$user['full_name']} updated Card #{$card['card_number']} daily rate from GH₵ " . number_format($oldRate, 2) . " to GH₵ " . number_format($newDailyAmount, 2) . ". Reason: {$reason}",
            "view_card.php?id={$cardId}"
        );

        $pdo->commit();

        set_flash_message('success', "Card #{$card['card_number']} rate successfully updated to " . format_money($newDailyAmount) . "! " . ($recalculateDeposits ? "All {$spacesFilled} space(s) recalculated." : ""));
        header("Location: view_card.php?id=" . $cardId);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash_message('error', 'Error adjusting card rate: ' . $e->getMessage());
        header("Location: view_card.php?id=" . $cardId);
        exit;
    }
}

// Fetch all deposits recorded for this card, keyed by space_number
$depositsList = get_card_deposits($cardId);
$depositsBySpace = [];
foreach ($depositsList as $dep) {
    $depositsBySpace[$dep['space_number']] = $dep;
}

// Check for existing payout on this card
$stmtPayout = $pdo->prepare("
    SELECT p.*, admin.full_name as approved_by_name 
    FROM payouts p 
    LEFT JOIN users admin ON p.approved_by = admin.id 
    WHERE p.card_id = ? 
    ORDER BY p.id DESC LIMIT 1
");
$stmtPayout->execute([$cardId]);
$payout = $stmtPayout->fetch();

// Fetch all cards for this customer (Card History)
$customerCards = get_customer_card_history((int)$card['customer_id']);
$otherActiveCard = null;
foreach ($customerCards as $cItem) {
    if ($cItem['is_active'] && (int)$cItem['id'] !== $cardId) {
        $otherActiveCard = $cItem;
        break;
    }
}
$payoutCalc = calculate_payout_breakdown($cardId);

$pageTitle = "Susu Card #" . $card['card_number'] . " - " . $card['full_name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6 max-w-5xl mx-auto">

    <!-- Print-Only Passbook Header -->
    <div class="hidden print-passbook-header">
        <h1>Eyram Susu Savings</h1>
        <p>31-Space Passbook &bull; <?= htmlspecialchars($card['full_name']) ?> &bull; Card #<?= $card['card_number'] ?></p>
    </div>

    <!-- Top Navigation & Actions (HCI: Fitts's Law Accessible Placement) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 no-print">
        <a href="customers.php" class="text-xs font-bold text-cornflower_ocean hover:text-steel_azure inline-flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            <span>Customers Directory</span>
        </a>
        <div class="flex flex-wrap items-center gap-2">
            <button onclick="window.print()" class="btn-touch bg-white text-slate-700 hover:bg-platinum border border-silver-600 text-xs font-bold px-3 py-1.5 shadow-2xs rounded-xl inline-flex items-center gap-1.5">
                <i class="fa-solid fa-print text-xs"></i>
                <span>Print Passbook</span>
            </button>
            <?php if ($card['status'] === 'active'): ?>
                <a href="record_deposit.php?customer_id=<?= $card['customer_id'] ?>" class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-extrabold px-3.5 py-1.5 shadow-2xs rounded-xl inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Record Deposit</span>
                </a>
            <?php elseif ($otherActiveCard): ?>
                <a href="view_card.php?id=<?= $otherActiveCard['id'] ?>" class="btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white text-xs font-extrabold px-3.5 py-1.5 shadow-2xs rounded-xl inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-id-card text-xs"></i>
                    <span>View Active Card #<?= $otherActiveCard['card_number'] ?></span>
                </a>
            <?php elseif ($user['role'] === 'admin' || ($user['role'] === 'collector' && (int)$card['card_number'] >= 1)): ?>
                <button type="button" 
                        onclick="openNextCardModal(<?= (int)$card['customer_id'] ?>, <?= htmlspecialchars(json_encode($card['full_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($card['account_number']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($card['collector_name'] ?: 'Unassigned'), ENT_QUOTES, 'UTF-8') ?>, <?= (float)$card['daily_amount'] ?>, <?= (int)($card['card_number'] + 1) ?>)"
                        class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-extrabold px-4 py-1.5 shadow-2xs rounded-xl inline-flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-circle-plus text-xs"></i>
                    <span>+ Open Next Card (#<?= $card['card_number'] + 1 ?>)</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card History Switcher / Timeline Carousel (HCI: Fitts's & Jakob's Law) -->
    <?php if (!empty($customerCards)): ?>
        <div class="bg-white rounded-2xl border border-silver-600 shadow-xs p-3.5 sm:p-4 no-print">
            <div class="flex items-center justify-between mb-2.5">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-blue-50 text-steel_azure flex items-center justify-center text-xs">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <span class="text-xs font-black text-slate-800 uppercase tracking-wider">Passbook Cards History</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-steel_azure text-white">
                        <?= count($customerCards) ?> <?= count($customerCards) === 1 ? 'card' : 'cards' ?>
                    </span>
                </div>
                <span class="text-[11px] text-slate-400 font-medium hidden sm:inline">Select a card to inspect all 31 spaces</span>
            </div>

            <!-- Horizontal scrollable card tabs -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 pt-0.5 scrollbar-thin">
                <?php foreach ($customerCards as $cItem): 
                    $isSelected = ((int)$cItem['id'] === $cardId);
                    $badgeClass = '';
                    $badgeText = '';
                    if ($cItem['is_active']) {
                        $badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                        $badgeText = "Active ({$cItem['spaces_filled']}/{$cItem['total_spaces']})";
                    } elseif ($cItem['is_paid']) {
                        $badgeClass = 'bg-blue-50 text-steel_azure border-blue-200';
                        $badgeText = "Settled (" . format_money($cItem['customer_payout']) . ")";
                    } elseif ($cItem['is_pending_payout']) {
                        $badgeClass = 'bg-amber-50 text-amber-700 border-amber-200';
                        $badgeText = "Payout Pending";
                    } elseif ($cItem['is_completed']) {
                        $badgeClass = 'bg-purple-50 text-purple-700 border-purple-200';
                        $badgeText = "Completed (31/31)";
                    } else {
                        $badgeClass = 'bg-slate-100 text-slate-600 border-slate-200';
                        $badgeText = "Closed ({$cItem['spaces_filled']} sp)";
                    }
                ?>
                    <a href="view_card.php?id=<?= $cItem['id'] ?>"
                       class="btn-touch flex-shrink-0 px-3.5 py-2 rounded-xl text-xs transition flex items-center gap-2 <?= $isSelected ? 'bg-steel_azure text-white shadow-md ring-2 ring-steel_azure/30 font-black' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 font-bold' ?>">
                        <span>Card #<?= $cItem['card_number'] ?></span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold border <?= $isSelected ? 'bg-white/20 text-white border-white/30' : $badgeClass ?>">
                            <?= $badgeText ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Smart Action Banners: Completed / Ready for Cashout / Pending / Settled -->
    <?php if (($card['status'] === 'completed' || (int)$card['spaces_filled'] >= (int)$card['total_spaces']) && (!$payout || $payout['status'] === 'rejected')): ?>
        <!-- Ready for Cashout Banner -->
        <div class="p-5 rounded-2xl bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-100 border-2 border-emerald-400 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-emerald-500 text-white flex items-center justify-center text-xl flex-shrink-0 shadow-sm">
                    <i class="fa-solid fa-award"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-base font-black text-emerald-900">🎉 Susu Card Completed!</span>
                        <span class="px-2 py-0.5 text-[10px] font-black bg-emerald-600 text-white rounded-md uppercase">31/31 Spaces Filled</span>
                    </div>
                    <p class="text-xs text-emerald-800 mt-1 font-medium leading-relaxed">
                        Total Saved: <strong class="text-emerald-900"><?= format_money($card['total_saved']) ?></strong> &bull;
                        Business Fee: <strong><?= format_money($payoutCalc['business_fee'] ?? $card['daily_amount']) ?></strong> &bull;
                        Net Client Cashout: <strong class="text-emerald-950 font-black text-sm"><?= format_money($payoutCalc['customer_payout'] ?? ($card['total_saved'] - $card['daily_amount'])) ?></strong>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 flex-shrink-0">
                <?php if ($user['role'] === 'admin'): ?>
                    <button type="button" onclick="openCashoutModal()" 
                            class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs sm:text-sm font-extrabold px-5 py-2.5 shadow-md rounded-xl inline-flex items-center gap-2 cursor-pointer transition">
                        <i class="fa-solid fa-hand-holding-dollar text-sm"></i>
                        <span>Cash Out Now</span>
                    </button>
                <?php else: ?>
                    <a href="request_payout.php?card_id=<?= $cardId ?>" 
                       class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs sm:text-sm font-extrabold px-5 py-2.5 shadow-md rounded-xl inline-flex items-center gap-2 cursor-pointer transition">
                        <i class="fa-solid fa-paper-plane text-sm"></i>
                        <span>Request Payout for Client</span>
                    </a>
                <?php endif; ?>

                <?php if (($user['role'] === 'admin' || ($user['role'] === 'collector' && (int)$card['card_number'] >= 1)) && !$otherActiveCard): ?>
                    <button type="button" 
                            onclick="openNextCardModal(<?= (int)$card['customer_id'] ?>, <?= htmlspecialchars(json_encode($card['full_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($card['account_number']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($card['collector_name'] ?: 'Unassigned'), ENT_QUOTES, 'UTF-8') ?>, <?= (float)$card['daily_amount'] ?>, <?= (int)($card['card_number'] + 1) ?>)"
                            class="btn-touch bg-white hover:bg-slate-50 text-steel_azure border border-steel_azure text-xs font-bold px-4 py-2.5 rounded-xl transition inline-flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-circle-plus text-xs"></i>
                        <span>Open Next Card (#<?= $card['card_number'] + 1 ?>)</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($payout && $payout['status'] === 'pending'): ?>
        <!-- Payout Request Pending Approval Banner -->
        <div class="p-4 sm:p-5 rounded-2xl bg-amber-50 border-2 border-amber-300 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center text-lg flex-shrink-0 shadow-xs">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <div>
                    <div class="text-sm font-black text-amber-900">Payout Request Pending Approval</div>
                    <div class="text-xs text-amber-800 mt-0.5">
                        Amount: <strong class="text-amber-950 font-black"><?= format_money($payout['customer_payout']) ?></strong> &bull;
                        Requested on <?= date('M d, Y', strtotime($payout['created_at'])) ?>
                    </div>
                </div>
            </div>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="payouts.php" class="btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white text-xs font-extrabold px-4 py-2 rounded-xl shadow-xs inline-flex items-center gap-1.5 flex-shrink-0">
                    <span>Review & Approve Payout</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            <?php endif; ?>
        </div>

    <?php elseif ($payout && $payout['status'] === 'paid'): ?>
        <!-- Card Settled & Paid Out Banner -->
        <div class="p-4 sm:p-5 rounded-2xl bg-blue-50/80 border border-blue-200 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-steel_azure text-white flex items-center justify-center text-lg flex-shrink-0 shadow-xs">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="text-sm font-black text-steel_azure">Card Settled & Paid Out</div>
                    <div class="text-xs text-slate-600 mt-0.5">
                        Net Payout of <strong class="text-slate-800"><?= format_money($payout['customer_payout']) ?></strong> paid on <?= date('M d, Y', strtotime($payout['paid_at'])) ?><?= !empty($payout['approved_by_name']) ? ' by ' . htmlspecialchars($payout['approved_by_name']) : '' ?> &bull; Fee retained: <?= format_money($payout['business_fee']) ?>.
                    </div>
                </div>
            </div>
            <?php if (($user['role'] === 'admin' || ($user['role'] === 'collector' && (int)$card['card_number'] >= 1)) && !$otherActiveCard): ?>
                <button type="button" 
                        onclick="openNextCardModal(<?= (int)$card['customer_id'] ?>, <?= htmlspecialchars(json_encode($card['full_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($card['account_number']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($card['collector_name'] ?: 'Unassigned'), ENT_QUOTES, 'UTF-8') ?>, <?= (float)$card['daily_amount'] ?>, <?= (int)($card['card_number'] + 1) ?>)"
                        class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-extrabold px-4 py-2 rounded-xl shadow-xs inline-flex items-center gap-1.5 cursor-pointer flex-shrink-0">
                    <i class="fa-solid fa-circle-plus text-xs"></i>
                    <span>+ Open Next Card (#<?= $card['card_number'] + 1 ?>)</span>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Passbook Header Card (Jakob's Law: Physical Book Appearance) -->
    <div class="bg-white rounded-2xl border-2 border-silver-600 shadow-md p-5 sm:p-6 overflow-hidden relative">
        <div class="absolute top-0 right-0 left-0 h-2 bg-gradient-to-r from-steel_azure via-cornflower_ocean to-pumpkin_spice"></div>

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mt-1">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-steel_azure text-white uppercase tracking-wider">
                        Susu Card #<?= $card['card_number'] ?>
                    </span>
                    
                    <?php if ($card['status'] === 'active'): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                            Active (<?= $card['spaces_filled'] ?>/<?= $card['total_spaces'] ?> Filled)
                        </span>
                    <?php elseif ($card['status'] === 'completed'): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-300">
                            ✓ Completed 31 Spaces
                        </span>
                    <?php else: ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
                            Closed Early (<?= $card['spaces_filled'] ?> Spaces)
                        </span>
                    <?php endif; ?>
                </div>

                <h1 class="text-xl sm:text-2xl font-black text-slate-800 mt-2">
                    <?= htmlspecialchars($card['full_name']) ?>
                </h1>
                
                <div class="text-xs text-slate-500 mt-1 flex flex-wrap gap-x-4 gap-y-1">
                    <span><strong>Account:</strong> <?= htmlspecialchars($card['account_number']) ?></span>
                    <span><strong>Phone:</strong> <?= htmlspecialchars($card['phone']) ?></span>
                    <span><strong>Location:</strong> <?= htmlspecialchars($card['location'] ?: 'N/A') ?></span>
                    <span><strong>Collector:</strong> <?= htmlspecialchars($card['collector_name'] ?: 'Unassigned') ?></span>
                </div>
            </div>

            <!-- Financial Metrics Highlights -->
            <div class="flex flex-wrap sm:flex-nowrap gap-3 bg-platinum-800 p-3.5 rounded-xl border border-silver-600/70">
                <div class="text-left sm:text-right pr-3 border-r border-silver-600">
                    <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center justify-between gap-1.5">
                        <span>Agreed Daily Rate</span>
                        <?php if ($user['role'] === 'admin'): ?>
                            <button type="button" onclick="openAdjustRateModal()" 
                                    class="text-cornflower_ocean hover:text-steel_azure text-[10px] font-black inline-flex items-center gap-1 hover:underline cursor-pointer bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200 shadow-2xs"
                                    title="Adjust daily rate or recalculate deposits">
                                <i class="fa-solid fa-pen-to-square text-[9px]"></i>
                                <span>Edit</span>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="text-base sm:text-lg font-black text-steel_azure"><?= format_money($card['daily_amount']) ?></div>
                    <div class="text-[10px] text-slate-400">per space</div>
                </div>

                <div class="text-left sm:text-right pr-3 border-r border-silver-600">
                    <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Saved</div>
                    <div class="text-base sm:text-lg font-black text-emerald-600"><?= format_money($card['total_saved']) ?></div>
                    <div class="text-[10px] text-slate-400"><?= $card['spaces_filled'] ?> spaces paid</div>
                </div>

                <div class="text-left sm:text-right">
                    <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Change Float</div>
                    <div class="text-base sm:text-lg font-black <?= $card['change_balance'] > 0 ? 'text-pumpkin_spice' : 'text-slate-400' ?>">
                        <?= format_money($card['change_balance']) ?>
                    </div>
                    <div class="text-[10px] text-slate-400">towards next space</div>
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="mt-5 pt-4 border-t border-silver-600/60">
            <div class="flex items-center justify-between text-xs font-bold text-slate-600 mb-1.5">
                <span>Card Progress</span>
                <span class="text-steel_azure"><?= $card['spaces_filled'] ?> of <?= $card['total_spaces'] ?> Spaces Complete (<?= round(($card['spaces_filled'] / $card['total_spaces']) * 100) ?>%)</span>
            </div>
            <div class="w-full bg-silver-700 rounded-full h-3 overflow-hidden p-0.5">
                <?php $progress = min(100, round(($card['spaces_filled'] / $card['total_spaces']) * 100)); ?>
                <div class="bg-gradient-to-r from-cornflower_ocean via-emerald-500 to-emerald-600 h-2 rounded-full transition-all duration-500" style="width: <?= $progress ?>%"></div>
            </div>
        </div>

        <?php if ($card['status'] !== 'active' && !$otherActiveCard && ($user['role'] === 'admin' || ($user['role'] === 'collector' && (int)$card['card_number'] >= 1))): ?>
            <div class="mt-5 pt-4 border-t border-silver-600/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-amber-50/70 p-4 rounded-xl border border-amber-200">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-sm font-bold flex-shrink-0">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-xs sm:text-sm text-slate-800">
                            Start Next Savings Cycle for <?= htmlspecialchars($card['full_name']) ?>
                        </h4>
                        <p class="text-[11px] text-slate-500">
                            This card is closed. Open Card #<?= $card['card_number'] + 1 ?> to continue daily collections.
                        </p>
                    </div>
                </div>

                <button type="button" 
                        onclick="openNextCardModal(<?= (int)$card['customer_id'] ?>, <?= htmlspecialchars(json_encode($card['full_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($card['account_number']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($card['collector_name'] ?: 'Unassigned'), ENT_QUOTES, 'UTF-8') ?>, <?= (float)$card['daily_amount'] ?>, <?= (int)($card['card_number'] + 1) ?>)"
                        class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-extrabold px-4 py-2.5 rounded-xl shadow-xs transition flex items-center gap-2 cursor-pointer flex-shrink-0">
                    <i class="fa-solid fa-circle-plus text-xs"></i>
                    <span>+ Open Card #<?= $card['card_number'] + 1 ?></span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Visual 31-Space Susu Book Grid -->
    <div class="bg-white rounded-2xl border-2 border-silver-600 shadow-md p-5 sm:p-6">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-silver-600/70">
            <div>
                <h2 class="text-base sm:text-lg font-black text-slate-800">31-Space Savings Passbook</h2>
                <p class="text-xs text-slate-500">Each space represents one contribution of <?= format_money($card['daily_amount']) ?> (independent of calendar month).</p>
            </div>
            <div class="text-xs font-semibold text-slate-400 hidden sm:block">
                Spaces 1 – 31
            </div>
        </div>

        <!-- 31 Spaces Grid -->
        <div class="susu-book-grid">
            <?php for ($i = 1; $i <= (int)$card['total_spaces']; $i++): ?>
                <?php if (isset($depositsBySpace[$i])): 
                    $dep = $depositsBySpace[$i]; ?>
                    <!-- Paid Space -->
                    <div class="susu-space-box susu-space-paid">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-black text-emerald-800">#<?= $i ?></span>
                            <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] font-black shadow-xs">✓</span>
                        </div>
                        <div class="my-auto text-center">
                            <div class="text-[10px] text-slate-600 font-bold leading-tight">
                                <?= date('d M', strtotime($dep['deposit_date'])) ?>
                            </div>
                            <div class="text-[9px] text-slate-400 truncate">
                                <?= date('Y', strtotime($dep['deposit_date'])) ?>
                            </div>
                        </div>
                        <div class="text-center font-extrabold text-xs text-emerald-700 border-t border-emerald-200 pt-0.5">
                            <?= format_money($dep['amount']) ?>
                        </div>
                    </div>

                <?php elseif ($card['status'] === 'active' && $i === ($card['spaces_filled'] + 1)): ?>
                    <!-- Next Space to Fill -->
                    <a href="record_deposit.php?customer_id=<?= $card['customer_id'] ?>" 
                       class="susu-space-box susu-space-next hover:scale-105 cursor-pointer no-print"
                       title="Click to fill Space #<?= $i ?>">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-black text-pumpkin_spice">#<?= $i ?></span>
                            <span class="text-[10px] font-black text-pumpkin_spice">+</span>
                        </div>
                        <div class="my-auto text-center">
                            <span class="text-[10px] font-bold text-pumpkin_spice block leading-tight">Next Space</span>
                            <span class="text-[9px] text-slate-400">Click to pay</span>
                        </div>
                        <div class="text-center text-[10px] font-extrabold text-slate-500 border-t border-pumpkin_spice-800 pt-0.5">
                            <?= format_money($card['daily_amount']) ?>
                        </div>
                    </a>

                <?php else: ?>
                    <!-- Empty Future Space -->
                    <div class="susu-space-box susu-space-empty">
                        <span class="text-[11px] font-bold text-silver-400">#<?= $i ?></span>
                        <div class="my-auto text-center">
                            <span class="text-[10px] text-silver-400">&mdash;</span>
                        </div>
                        <div class="text-center text-[10px] font-medium text-silver-400 border-t border-silver-700 pt-0.5">
                            <?= format_money($card['daily_amount']) ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>

        <!-- Legend -->
        <div class="mt-6 pt-4 border-t border-silver-600/60 flex flex-wrap items-center justify-center gap-6 text-xs text-slate-500">
            <div class="flex items-center gap-1.5">
                <span class="w-3.5 h-3.5 rounded bg-emerald-50 border border-emerald-500 flex items-center justify-center text-[9px] font-bold text-emerald-700">✓</span>
                <span>Space Completed & Stamped</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3.5 h-3.5 rounded bg-pumpkin_spice-900 border border-pumpkin_spice"></span>
                <span>Next Space to Pay</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3.5 h-3.5 rounded bg-white border border-dashed border-silver"></span>
                <span>Empty Space Remaining</span>
            </div>
        </div>

    </div>

    <!-- Card Action Panel (Payout / Close Card or Start Next Card) -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm p-5 sm:p-6 no-print">
        <?php if ($card['status'] === 'active'): ?>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Customer Cashout & Cycle Closure</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Customer can cash out after 31 spaces or stop early. Business fee (1 contribution of <?= format_money($card['daily_amount']) ?>) will be deducted.
                    </p>
                </div>
                <div>
                    <?php if ($card['spaces_filled'] > 0): ?>
                        <a href="request_payout.php?card_id=<?= $card['id'] ?>" class="btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white text-xs sm:text-sm font-extrabold px-5 py-2.5 shadow-sm transition">
                            Request Customer Payout
                        </a>
                    <?php else: ?>
                        <button disabled class="btn-touch bg-platinum text-slate-400 cursor-not-allowed text-xs font-bold px-4 py-2">
                            No contributions yet to cash out
                        </button>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($payout): ?>
            <!-- Payout Summary Receipt -->
            <div class="p-4 bg-platinum-800 rounded-xl border border-silver-600">
                <div class="flex items-center justify-between pb-3 border-b border-silver-600/70">
                    <h3 class="text-sm font-bold text-slate-800">Final Payout Settlement Record</h3>
                    <span class="px-2.5 py-0.5 rounded text-xs font-bold <?= $payout['status'] === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                        <?= strtoupper($payout['status']) ?>
                    </span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3 text-xs">
                    <div>
                        <span class="text-slate-500">Gross Saved:</span>
                        <div class="font-extrabold text-slate-800"><?= format_money($payout['total_saved']) ?></div>
                    </div>
                    <div>
                        <span class="text-slate-500">Business Fee (1 space):</span>
                        <div class="font-extrabold text-pumpkin_spice">- <?= format_money($payout['business_fee']) ?></div>
                    </div>
                    <div>
                        <span class="text-slate-500">Change Refunded:</span>
                        <div class="font-extrabold text-slate-700">+ <?= format_money($payout['change_refunded']) ?></div>
                    </div>
                    <div>
                        <span class="text-slate-500">Net Customer Payout:</span>
                        <div class="font-black text-sm text-emerald-600"><?= format_money($payout['customer_payout']) ?></div>
                    </div>
                </div>

                <?php if (($user['role'] === 'admin' || ($user['role'] === 'collector' && (int)$card['card_number'] >= 1)) && !$otherActiveCard): ?>
                    <div class="mt-4 pt-3 border-t border-silver-600/60 flex justify-end">
                        <button type="button" 
                                onclick="openNextCardModal(<?= (int)$card['customer_id'] ?>, <?= htmlspecialchars(json_encode($card['full_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($card['account_number']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($card['collector_name'] ?: 'Unassigned'), ENT_QUOTES, 'UTF-8') ?>, <?= (float)$card['daily_amount'] ?>, <?= (int)($card['card_number'] + 1) ?>)"
                                class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-bold px-4 py-2 shadow-sm cursor-pointer inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-plus text-xs"></i>
                            <span>+ Open Card #<?= $card['card_number'] + 1 ?> for <?= htmlspecialchars($card['full_name']) ?></span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Complete History Log Table -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-silver-600/70">
            <h3 class="text-sm sm:text-base font-bold text-slate-800">Individual Deposit Logs</h3>
            <p class="text-xs text-slate-500">Chronological record of each space deposited and who collected it.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-platinum text-slate-600 font-semibold border-b border-silver-600/70">
                    <tr>
                        <th class="py-2.5 px-4">Space #</th>
                        <th class="py-2.5 px-4">Date Stamped</th>
                        <th class="py-2.5 px-4">Amount</th>
                        <th class="py-2.5 px-4">Collected By</th>
                        <th class="py-2.5 px-4">Handover Status</th>
                        <th class="py-2.5 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-silver-600/50">
                    <?php if (empty($depositsList)): ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                <div class="empty-state">
                                    <div class="empty-state-icon bg-slate-100 text-slate-400">
                                        <i class="fa-solid fa-receipt text-3xl"></i>
                                    </div>
                                    <div class="empty-state-title">No Deposits Yet</div>
                                    <div class="empty-state-text">No deposits stamped on this card yet.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($depositsList as $dep): ?>
                            <tr class="hover:bg-platinum-800">
                                <td class="py-2.5 px-4 font-bold text-steel_azure">
                                    Space #<?= $dep['space_number'] ?>
                                </td>
                                <td class="py-2.5 px-4 text-slate-600">
                                    <?= date('d M Y, h:i A', strtotime($dep['created_at'])) ?>
                                </td>
                                <td class="py-2.5 px-4 font-bold text-emerald-600">
                                    <?= format_money($dep['amount']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-slate-700">
                                    <?= htmlspecialchars($dep['collector_name']) ?>
                                </td>
                                <td class="py-2.5 px-4">
                                    <?php if ($dep['handover_id']): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                            Handed Over
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800">
                                            In Cash Bag
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-4 text-right">
                                    <?php 
                                        $canUndo = empty($dep['handover_id']) 
                                                   && $dep['deposit_date'] === date('Y-m-d')
                                                   && ($user['role'] === 'admin' || (int)$dep['collector_id'] === (int)$user['id']);
                                    ?>
                                    <?php if ($canUndo): ?>
                                        <button type="button" 
                                                onclick="openCancelDepositModal(<?= $dep['id'] ?>, '<?= htmlspecialchars(addslashes($card['full_name'])) ?>', '<?= format_money($dep['amount']) ?>', <?= $dep['space_number'] ?>)"
                                                class="btn-touch px-2.5 py-1 bg-red-50 hover:bg-red-600 hover:text-white text-red-700 border border-red-200 rounded-lg text-xs font-bold transition inline-flex items-center gap-1 cursor-pointer">
                                            <i class="fa-solid fa-rotate-left text-[10px]"></i>
                                            <span>Cancel</span>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-slate-300 text-xs">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Cancel Deposit Confirmation Modal (HCI: Hick's Law, Fitts's Law, Plain Language) -->
<div id="cancel_deposit_modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-opacity">
    <div class="bg-white rounded-2xl border border-silver-600 shadow-2xl max-w-md w-full overflow-hidden transform transition-all scale-95 duration-200" id="cancel_modal_box">
        
        <!-- Modal Header -->
        <div class="p-4 sm:p-5 bg-gradient-to-r from-red-600 to-rose-600 text-white flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <i class="fa-solid fa-rotate-left text-base"></i>
                <h3 class="font-extrabold text-base">Cancel Susu Deposit</h3>
            </div>
            <button type="button" onclick="closeCancelDepositModal()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer" title="Close">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="view_card.php?id=<?= $cardId ?>" class="p-5 sm:p-6 space-y-4">
            <input type="hidden" name="action" value="undo_deposit">
            <input type="hidden" id="cancel_deposit_id" name="deposit_id" value="">
            <input type="hidden" id="cancel_reason_input" name="reason" value="Customer changed mind">

            <!-- Confirmation Review Box -->
            <div class="p-3.5 bg-red-50 border border-red-200 rounded-xl space-y-1.5 text-xs text-red-900">
                <div class="font-bold flex items-center gap-1.5 text-red-800">
                    <i class="fa-solid fa-triangle-exclamation text-red-600"></i>
                    <span>This action will un-stamp the space and adjust balances:</span>
                </div>
                <div class="pt-1.5 border-t border-red-200/80 space-y-1">
                    <div class="flex justify-between">
                        <span class="text-slate-600">Client:</span>
                        <strong class="text-slate-800" id="cancel_customer_name">-</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Cash to Return:</span>
                        <strong class="text-red-700 text-sm" id="cancel_amount_display">-</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Space to Remove:</span>
                        <strong class="text-slate-800" id="cancel_space_display">-</strong>
                    </div>
                </div>
            </div>

            <!-- Quick 1-Tap Reason Selector (Fitts's Law: Large Thumb Targets) -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">
                    Select Reason for Cancellation:
                </label>
                <div class="space-y-2">
                    <button type="button" onclick="selectCancelReason('Customer changed mind', this)"
                            class="reason-btn w-full p-2.5 rounded-xl border-2 border-red-500 bg-red-50 text-red-800 font-bold text-xs text-left transition flex items-center justify-between cursor-pointer">
                        <span>Customer changed mind</span>
                        <i class="fa-solid fa-check text-xs text-red-600 check-icon"></i>
                    </button>
                    <button type="button" onclick="selectCancelReason('Wrong amount entered', this)"
                            class="reason-btn w-full p-2.5 rounded-xl border-2 border-silver-600 bg-white text-slate-700 font-semibold text-xs text-left hover:bg-platinum-800 transition flex items-center justify-between cursor-pointer">
                        <span>Wrong amount entered</span>
                        <i class="fa-solid fa-check text-xs text-red-600 check-icon hidden"></i>
                    </button>
                    <button type="button" onclick="selectCancelReason('Customer requested cash back', this)"
                            class="reason-btn w-full p-2.5 rounded-xl border-2 border-silver-600 bg-white text-slate-700 font-semibold text-xs text-left hover:bg-platinum-800 transition flex items-center justify-between cursor-pointer">
                        <span>Customer requested cash back</span>
                        <i class="fa-solid fa-check text-xs text-red-600 check-icon hidden"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Action Buttons (Hick's Law: 1 Primary CTA + 1 Secondary CTA) -->
            <div class="pt-3 border-t border-silver-600/60 flex items-center justify-between gap-3">
                <button type="button" onclick="closeCancelDepositModal()"
                        class="btn-touch px-4 py-2.5 bg-white hover:bg-platinum text-slate-600 border border-silver-600 text-xs font-bold rounded-xl transition cursor-pointer">
                    Keep Deposit
                </button>
                <button type="submit"
                        class="btn-touch px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-xs font-extrabold rounded-xl shadow-md transition flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-rotate-left text-xs"></i>
                    <span>Yes, Cancel Deposit</span>
                </button>
            </div>
        </form>

    <!-- CASHOUT CONFIRMATION MODAL (Admin Cashout Execution) -->
<div id="cashout_confirm_modal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs hidden" role="dialog" aria-modal="true" aria-labelledby="cashout_modal_title">
    <div class="bg-white rounded-3xl border border-silver-600 shadow-2xl max-w-lg w-full overflow-hidden transform transition-all scale-95 duration-200" id="cashout_modal_box">
        <div class="p-5 sm:p-6 bg-gradient-to-r from-emerald-600 to-teal-700 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-white/20 flex items-center justify-center text-xl flex-shrink-0 shadow-inner">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
                <div>
                    <h3 id="cashout_modal_title" class="font-black text-base sm:text-lg leading-tight">Confirm Susu Cashout & Settlement</h3>
                    <p class="text-xs text-white/80 mt-0.5">Card #<?= $card['card_number'] ?> &bull; <?= htmlspecialchars($card['full_name']) ?></p>
                </div>
            </div>
            <button type="button" onclick="closeCashoutModal()" class="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer" title="Close modal" aria-label="Close modal">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="request_payout.php" class="p-5 sm:p-6 space-y-4">
            <input type="hidden" name="card_id" value="<?= $cardId ?>">
            <input type="hidden" name="reason" value="Card completed 31 spaces - Full cycle payout">

            <!-- Itemized Breakdown Card (Financial Confidence & Transparency) -->
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 sm:p-5 space-y-2.5 text-xs sm:text-sm">
                <div class="flex items-center justify-between text-slate-600">
                    <span>Gross Savings (<?= $card['spaces_filled'] ?> of 31 spaces):</span>
                    <strong class="text-slate-900 font-bold"><?= format_money($card['total_saved']) ?></strong>
                </div>
                <div class="flex items-center justify-between text-red-600 font-semibold">
                    <span>Less: 1-Day Susu Management Fee:</span>
                    <strong>- <?= format_money($payoutCalc['business_fee'] ?? $card['daily_amount']) ?></strong>
                </div>
                <?php if (!empty($card['change_balance']) && (float)$card['change_balance'] > 0): ?>
                    <div class="flex items-center justify-between text-pumpkin_spice font-semibold">
                        <span>Plus: Customer Float Refunded:</span>
                        <strong>+ <?= format_money($card['change_balance']) ?></strong>
                    </div>
                <?php endif; ?>

                <!-- Net Cashout Highlight Pill -->
                <div class="pt-3 border-t-2 border-slate-200 flex items-center justify-between bg-emerald-50 -mx-4 sm:-mx-5 px-4 sm:px-5 py-3 rounded-b-xl mt-1 text-emerald-900">
                    <div>
                        <div class="text-[11px] uppercase tracking-wider font-extrabold text-emerald-700">Net Cash Disbursed to Client</div>
                        <div class="text-xs text-emerald-800 font-medium">To be given physically or via MoMo</div>
                    </div>
                    <div class="text-xl sm:text-2xl font-black text-emerald-700">
                        <?= format_money($payoutCalc['customer_payout'] ?? ($card['total_saved'] - $card['daily_amount'])) ?>
                    </div>
                </div>
            </div>

            <!-- Reassurance / Warning Banner -->
            <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl flex items-start gap-2.5 text-xs text-blue-900 font-medium">
                <i class="fa-solid fa-circle-info text-steel_azure text-sm mt-0.5 flex-shrink-0"></i>
                <span>Executing this cashout settles the card, records the 1-space fee retained by Eyram Susu, and archives all 31 spaces in the passbook history.</span>
            </div>

            <!-- Action CTAs (Hick's Law: 1 Primary + 1 Secondary) -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                <button type="button" onclick="closeCashoutModal()" class="btn-touch px-4 py-2.5 bg-white hover:bg-slate-100 text-slate-600 border border-slate-300 text-xs font-bold rounded-xl transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="btn-touch px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-black rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-circle-check text-sm"></i>
                    <span>Confirm & Disburse Cashout</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCashoutModal() {
    const modal = document.getElementById('cashout_confirm_modal');
    const box = document.getElementById('cashout_modal_box');
    if (!modal) return;
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    modal.classList.remove('hidden');
    setTimeout(() => {
        box.classList.remove('scale-95');
        box.classList.add('scale-100');
    }, 10);
}

function closeCashoutModal() {
    const modal = document.getElementById('cashout_confirm_modal');
    const box = document.getElementById('cashout_modal_box');
    if (!modal) return;
    box.classList.remove('scale-100');
    box.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 150);
}

function openCancelDepositModal(depositId, customerName, amount, spaceNumber) {
    document.getElementById('cancel_deposit_id').value = depositId;
    document.getElementById('cancel_customer_name').textContent = customerName;
    document.getElementById('cancel_amount_display').textContent = amount;
    document.getElementById('cancel_space_display').textContent = 'Space #' + spaceNumber;
    
    selectCancelReason('Customer changed mind', document.querySelector('.reason-btn'));

    const modal = document.getElementById('cancel_deposit_modal');
    const box = document.getElementById('cancel_modal_box');
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    modal.classList.remove('hidden');
    setTimeout(() => {
        box.classList.remove('scale-95');
        box.classList.add('scale-100');
    }, 10);
}

function closeCancelDepositModal() {
    const modal = document.getElementById('cancel_deposit_modal');
    const box = document.getElementById('cancel_modal_box');
    if (!modal) return;
    box.classList.remove('scale-100');
    box.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 150);
}

function selectCancelReason(reason, btn) {
    document.getElementById('cancel_reason_input').value = reason;
    document.querySelectorAll('.reason-btn').forEach(b => {
        b.className = 'reason-btn w-full p-2.5 rounded-xl border-2 border-silver-600 bg-white text-slate-700 font-semibold text-xs text-left hover:bg-platinum-800 transition flex items-center justify-between cursor-pointer';
        const icon = b.querySelector('.check-icon');
        if (icon) icon.classList.add('hidden');
    });
    if (btn) {
        btn.className = 'reason-btn w-full p-2.5 rounded-xl border-2 border-red-500 bg-red-50 text-red-800 font-bold text-xs text-left transition flex items-center justify-between cursor-pointer';
        const icon = btn.querySelector('.check-icon');
        if (icon) icon.classList.remove('hidden');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCancelDepositModal();
        closeCashoutModal();
        closeNextCardModal();
        closeAdjustRateModal();
    }
});

document.addEventListener('click', function(e) {
    const cancelModal = document.getElementById('cancel_deposit_modal');
    if (cancelModal && !cancelModal.classList.contains('hidden') && e.target === cancelModal) {
        closeCancelDepositModal();
    }
    const cashoutModal = document.getElementById('cashout_confirm_modal');
    if (cashoutModal && !cashoutModal.classList.contains('hidden') && e.target === cashoutModal) {
        closeCashoutModal();
    }
    const nextCardModal = document.getElementById('open_next_card_modal');
    if (nextCardModal && !nextCardModal.classList.contains('hidden') && e.target === nextCardModal) {
        closeNextCardModal();
    }
    const adjModal = document.getElementById('adjust_rate_modal');
    if (adjModal && !adjModal.classList.contains('hidden') && e.target === adjModal) {
        closeAdjustRateModal();
    }
});

// Next Card Rate Modal Functions
function openNextCardModal(customerId, customerName, accountNumber, collectorName, defaultAmount, nextCardNum) {
    const initials = (customerName || '--').split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
    const avatar = document.getElementById('onc_avatar');
    const nameEl = document.getElementById('onc_name');
    const accEl = document.getElementById('onc_account');
    const colEl = document.getElementById('onc_collector');
    const badgeEl = document.getElementById('onc_badge');
    const titleEl = document.getElementById('onc_modal_title');

    if (avatar) avatar.textContent = initials;
    if (nameEl) nameEl.textContent = customerName;
    if (accEl) accEl.textContent = 'A/C: ' + accountNumber;
    if (colEl) colEl.textContent = 'Collector: ' + collectorName;
    if (badgeEl && nextCardNum) badgeEl.textContent = 'Card #' + nextCardNum;
    if (titleEl && nextCardNum) titleEl.textContent = 'Open Next Susu Card #' + nextCardNum;

    document.getElementById('onc_customer_id').value = customerId;

    const def = parseFloat(defaultAmount) > 0 ? parseFloat(defaultAmount) : 20.00;
    const amountInput = document.getElementById('onc_daily_amount');
    if (amountInput) {
        amountInput.value = def;
        updateOncPreset(def);
        updateOncPreview();
    }

    const modal = document.getElementById('open_next_card_modal');
    const box = document.getElementById('onc_modal_box');
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    document.body.classList.add('overflow-hidden');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        box.classList.remove('scale-95');
        box.classList.add('scale-100');
    });
}

function closeNextCardModal() {
    const modal = document.getElementById('open_next_card_modal');
    const box = document.getElementById('onc_modal_box');
    if (!modal) return;
    document.body.classList.remove('overflow-hidden');
    box.classList.remove('scale-100');
    box.classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 150);
}

function updateOncPreset(amount) {
    document.querySelectorAll('.onc-preset-btn').forEach(btn => {
        const v = parseFloat(btn.getAttribute('data-amount'));
        if (v === parseFloat(amount)) {
            btn.classList.add('border-pumpkin_spice', 'bg-orange-50', 'text-pumpkin_spice');
            btn.classList.remove('border-silver-600', 'bg-white', 'text-slate-700');
        } else {
            btn.classList.remove('border-pumpkin_spice', 'bg-orange-50', 'text-pumpkin_spice');
            btn.classList.add('border-silver-600', 'bg-white', 'text-slate-700');
        }
    });
}

function updateOncPreview() {
    const amount = parseFloat(document.getElementById('onc_daily_amount').value) || 0;
    const hiddenInput = document.getElementById('onc_amount_hidden');
    const confirmBtn = document.getElementById('onc_confirm_btn');
    const preview = document.getElementById('onc_target_preview');
    const previewRate = document.getElementById('onc_preview_rate');
    const previewTotal = document.getElementById('onc_preview_total');

    if (hiddenInput) hiddenInput.value = amount > 0 ? amount : '';

    if (amount > 0) {
        const total = amount * 31;
        if (previewRate) previewRate.textContent = 'GH₵ ' + amount.toFixed(2);
        if (previewTotal) previewTotal.textContent = 'GH₵ ' + total.toFixed(2);
        if (preview) preview.style.display = 'flex';
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.style.opacity = '1';
            confirmBtn.style.cursor = 'pointer';
        }
    } else {
        if (preview) preview.style.display = 'none';
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.style.opacity = '0.5';
            confirmBtn.style.cursor = 'not-allowed';
        }
    }
}

// Adjust Rate Modal Functions (Admin Only)
function openAdjustRateModal() {
    const modal = document.getElementById('adjust_rate_modal');
    const box = document.getElementById('adjust_rate_modal_box');
    if (!modal) return;
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    document.body.classList.add('overflow-hidden');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        box.classList.remove('scale-95');
        box.classList.add('scale-100');
    });
}

function closeAdjustRateModal() {
    const modal = document.getElementById('adjust_rate_modal');
    const box = document.getElementById('adjust_rate_modal_box');
    if (!modal) return;
    document.body.classList.remove('overflow-hidden');
    box.classList.remove('scale-100');
    box.classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 150);
}

// Attach event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.onc-preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const v = parseFloat(btn.getAttribute('data-amount'));
            const input = document.getElementById('onc_daily_amount');
            if (input) input.value = v;
            updateOncPreset(v);
            updateOncPreview();
        });
    });

    const oncAmountInput = document.getElementById('onc_daily_amount');
    if (oncAmountInput) {
        oncAmountInput.addEventListener('input', () => {
            updateOncPreset(parseFloat(oncAmountInput.value));
            updateOncPreview();
        });
    }

    document.querySelectorAll('.adj-preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const v = parseFloat(btn.getAttribute('data-amount'));
            const input = document.getElementById('adj_daily_amount');
            if (input) input.value = v;
            document.querySelectorAll('.adj-preset-btn').forEach(b => {
                b.classList.remove('border-steel_azure', 'bg-blue-50', 'text-steel_azure');
                b.classList.add('border-silver-600', 'bg-white', 'text-slate-700');
            });
            btn.classList.add('border-steel_azure', 'bg-blue-50', 'text-steel_azure');
            btn.classList.remove('border-silver-600', 'bg-white', 'text-slate-700');
        });
    });
});
</script>

<!-- Open Next Susu Card Modal -->
<div id="open_next_card_modal"
     class="fixed inset-0 z-50 overflow-y-auto p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center min-h-screen"
     role="dialog" aria-modal="true" aria-labelledby="onc_modal_title">
    <div class="bg-white rounded-2xl border border-silver-600 shadow-2xl max-w-md w-full max-h-[92vh] flex flex-col overflow-hidden my-auto transform transition-all scale-95 duration-200"
         id="onc_modal_box">

        <!-- Modal Header (Pinned) -->
        <div class="p-3.5 sm:p-4 bg-gradient-to-r from-pumpkin_spice to-pumpkin_spice-600 text-white flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-address-card text-sm sm:text-base"></i>
                </div>
                <div>
                    <h3 id="onc_modal_title" class="font-extrabold text-xs sm:text-sm leading-tight">Open Next Susu Passbook</h3>
                    <p class="text-[10px] sm:text-[11px] text-white/70 mt-0.5">31-Space Savings Passbook</p>
                </div>
            </div>
            <button type="button" onclick="closeNextCardModal()"
                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-white/10 hover:bg-white/25 text-white flex items-center justify-center transition flex-shrink-0 cursor-pointer"
                    title="Close" aria-label="Close modal">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Form Wrapper with Scrollable Body & Sticky Footer -->
        <form id="onc_form" method="POST" action="start_new_card.php" class="flex-1 flex flex-col min-h-0 overflow-hidden m-0">
            <input type="hidden" id="onc_customer_id" name="customer_id" value="">
            <input type="hidden" id="onc_amount_hidden" name="daily_amount" value="">

            <!-- Scrollable Modal Body -->
            <div class="p-3.5 sm:p-5 space-y-3 sm:space-y-4 overflow-y-auto overscroll-contain flex-1">

                <!-- Customer Identity -->
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5 sm:p-3 flex items-center gap-2.5 sm:gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-steel_azure text-white font-black flex items-center justify-center text-xs sm:text-sm flex-shrink-0 shadow-xs"
                         id="onc_avatar">--</div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs sm:text-sm font-extrabold text-slate-800 truncate" id="onc_name">-</div>
                        <div class="text-[10px] sm:text-[11px] text-slate-500 font-mono font-semibold truncate" id="onc_account">-</div>
                        <div class="text-[9px] sm:text-[10px] text-slate-400 mt-0.5 truncate" id="onc_collector">-</div>
                    </div>
                    <span class="text-[9px] sm:text-[10px] font-black text-emerald-700 bg-emerald-100 border border-emerald-200 px-2 py-0.5 sm:py-1 rounded-lg whitespace-nowrap flex-shrink-0" id="onc_badge">
                        Next Cycle
                    </span>
                </div>

                <!-- Daily Rate Picker -->
                <div>
                    <label class="block text-[11px] sm:text-xs font-black text-slate-700 uppercase tracking-wider mb-2">
                        Agreed Daily Savings Rate (GH₵) *
                    </label>

                    <!-- 1-Tap Quick Presets: 4 compact columns on all screens -->
                    <div class="grid grid-cols-4 gap-1.5 sm:gap-2 mb-2.5">
                        <?php foreach ([10, 20, 50, 100] as $preset): ?>
                            <button type="button"
                                    class="onc-preset-btn py-2 text-xs font-extrabold rounded-xl border border-silver-600 bg-white text-slate-700 hover:border-pumpkin_spice hover:bg-orange-50 hover:text-pumpkin_spice transition active:scale-95 cursor-pointer"
                                    data-amount="<?= $preset ?>">
                                GH₵ <?= $preset ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Custom Amount Input -->
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 font-black text-xs sm:text-sm">GH₵</span>
                        <input type="number" id="onc_daily_amount" inputmode="numeric" step="1" min="1" max="9999"
                               class="w-full pl-11 pr-3 py-2 sm:py-2.5 rounded-xl border border-silver-600 focus:border-pumpkin_spice focus:ring-2 focus:ring-pumpkin_spice/30 outline-none text-sm font-black text-slate-800 transition"
                               placeholder="Type agreed daily rate">
                    </div>
                </div>

                <!-- Live 31-Space Target Calculator -->
                <div id="onc_target_preview"
                     class="bg-gradient-to-r from-pumpkin_spice/10 to-orange-50 border border-pumpkin_spice/20 rounded-xl px-3 py-2.5 sm:px-4 sm:py-3 flex items-center justify-between"
                     style="display:none">
                    <div>
                        <div class="text-[9px] sm:text-[10px] text-slate-500 font-bold uppercase tracking-wider">31 Spaces × <span id="onc_preview_rate">GH₵ 0.00</span></div>
                        <div class="text-base sm:text-lg font-black text-pumpkin_spice" id="onc_preview_total">GH₵ 0.00</div>
                        <div class="text-[9px] sm:text-[10px] text-slate-400 font-medium">Full Card Savings Target</div>
                    </div>
                    <i class="fa-solid fa-piggy-bank text-2xl sm:text-3xl text-pumpkin_spice/25"></i>
                </div>
            </div>

            <!-- Sticky Pinned Footer -->
            <div class="p-3 sm:p-4 bg-slate-50 border-t border-silver-600/70 flex items-center gap-2.5 flex-shrink-0">
                <button type="button" onclick="closeNextCardModal()"
                        class="flex-1 py-2.5 px-3 sm:px-4 bg-white text-slate-600 hover:bg-platinum-800 border border-silver-600 text-xs sm:text-sm font-bold rounded-xl transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="onc_confirm_btn"
                        class="flex-1 py-2.5 px-3 sm:px-4 bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs sm:text-sm font-extrabold rounded-xl shadow-sm transition flex items-center justify-center gap-1.5 sm:gap-2 cursor-pointer"
                        style="opacity:0.5;cursor:not-allowed" disabled>
                    <i class="fa-solid fa-circle-check text-xs sm:text-sm"></i>
                    <span>Confirm &amp; Open</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($user['role'] === 'admin'): ?>
<!-- Adjust Card Daily Rate Modal (Admin Only) -->
<div id="adjust_rate_modal"
     class="fixed inset-0 z-50 overflow-y-auto p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center min-h-screen"
     role="dialog" aria-modal="true" aria-labelledby="adjust_rate_modal_title">
    <div class="bg-white rounded-2xl border border-silver-600 shadow-2xl max-w-md w-full max-h-[92vh] flex flex-col overflow-hidden my-auto transform transition-all scale-95 duration-200"
         id="adjust_rate_modal_box">

        <!-- Header (Pinned) -->
        <div class="p-3.5 sm:p-4 bg-gradient-to-r from-steel_azure to-steel_azure-400 text-white flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-pen-ruler text-sm sm:text-base"></i>
                </div>
                <div>
                    <h3 id="adjust_rate_modal_title" class="font-extrabold text-xs sm:text-sm leading-tight">Adjust Agreed Daily Rate</h3>
                    <p class="text-[10px] sm:text-[11px] text-white/70 mt-0.5">Card #<?= $card['card_number'] ?> &bull; <?= htmlspecialchars($card['full_name']) ?></p>
                </div>
            </div>
            <button type="button" onclick="closeAdjustRateModal()"
                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-white/10 hover:bg-white/25 text-white flex items-center justify-center transition flex-shrink-0 cursor-pointer"
                    title="Close" aria-label="Close modal">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Form Body (Scrollable with Sticky Footer) -->
        <form method="POST" action="view_card.php?id=<?= $cardId ?>" class="flex-1 flex flex-col min-h-0 overflow-hidden m-0">
            <input type="hidden" name="action" value="adjust_card_rate">

            <div class="p-3.5 sm:p-5 space-y-3 sm:space-y-4 overflow-y-auto overscroll-contain flex-1">
                <!-- Context Banner -->
                <div class="p-2.5 sm:p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-900 leading-relaxed flex items-start gap-2 sm:gap-2.5">
                    <i class="fa-solid fa-circle-info text-blue-500 text-xs sm:text-sm mt-0.5 flex-shrink-0"></i>
                    <div class="text-[11px] sm:text-xs">
                        Use this tool to correct an agreed rate entered erroneously (e.g. client upgraded to GH₵ 20 but card was opened at GH₵ 10).
                    </div>
                </div>

                <!-- Current Rate Display -->
                <div class="flex items-center justify-between p-2.5 sm:p-3 bg-slate-50 border border-silver-600/70 rounded-xl">
                    <div>
                        <span class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Current Card Rate</span>
                        <span class="text-xs sm:text-sm font-black text-slate-700"><?= format_money($card['daily_amount']) ?> / space</span>
                    </div>
                    <div class="text-right">
                        <span class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Spaces Stamped</span>
                        <span class="text-xs sm:text-sm font-black text-steel_azure"><?= $card['spaces_filled'] ?> / <?= $card['total_spaces'] ?></span>
                    </div>
                </div>

                <!-- New Daily Rate Input -->
                <div>
                    <label class="block text-[11px] sm:text-xs font-black text-slate-700 uppercase tracking-wider mb-2">
                        New Agreed Daily Rate (GH₵) *
                    </label>

                    <!-- Quick Presets -->
                    <div class="grid grid-cols-4 gap-1.5 sm:gap-2 mb-2.5">
                        <?php foreach ([10, 20, 50, 100] as $preset): ?>
                            <button type="button"
                                    class="adj-preset-btn py-2 text-xs font-extrabold rounded-xl border border-silver-600 bg-white text-slate-700 hover:border-steel_azure hover:bg-blue-50 hover:text-steel_azure transition active:scale-95 cursor-pointer"
                                    data-amount="<?= $preset ?>">
                                GH₵ <?= $preset ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 font-black text-xs sm:text-sm">GH₵</span>
                        <input type="number" id="adj_daily_amount" name="new_daily_amount" step="1" min="1" max="9999" required
                               class="w-full pl-11 pr-3 py-2 sm:py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-steel_azure/30 outline-none text-sm font-black text-slate-800 transition"
                               value="<?= (float)$card['daily_amount'] == 10 ? '20' : (float)$card['daily_amount'] ?>">
                    </div>
                </div>

                <!-- Recalculate Past Deposits Checkbox -->
                <?php if ((int)$card['spaces_filled'] > 0): ?>
                    <div class="p-2.5 sm:p-3 bg-amber-50 border border-amber-200 rounded-xl space-y-1">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" name="recalculate_deposits" value="1" checked
                                   class="w-4 h-4 mt-0.5 rounded border-slate-300 text-pumpkin_spice focus:ring-pumpkin_spice accent-pumpkin_spice cursor-pointer">
                            <div class="text-[11px] sm:text-xs text-amber-950 font-bold">
                                Recalculate all <?= $card['spaces_filled'] ?> recorded space(s) to new rate
                                <p class="text-[10px] sm:text-[11px] text-amber-800 font-normal mt-0.5">
                                    Updates each past deposit record on this card to the new rate, adjusting Total Saved to match (e.g. <?= $card['spaces_filled'] ?> spaces × new rate).
                                </p>
                            </div>
                        </label>
                    </div>
                <?php endif; ?>

                <!-- Audit Reason Field -->
                <div>
                    <label class="block text-[11px] sm:text-xs font-bold text-slate-700 mb-1">
                        Audit Reason / Note *
                    </label>
                    <textarea name="reason" rows="2" required
                              class="w-full px-3 py-2 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs text-slate-800 transition"
                              placeholder="e.g. Client upgraded to GH₵ 20 plan on Card 2; admin omitted rate change at card opening.">Client upgraded to GH₵ 20 plan; corrected admin omission at card opening.</textarea>
                </div>
            </div>

            <!-- Sticky Pinned Footer -->
            <div class="p-3 sm:p-4 bg-slate-50 border-t border-silver-600/70 flex items-center gap-2.5 flex-shrink-0">
                <button type="button" onclick="closeAdjustRateModal()"
                        class="flex-1 py-2.5 px-3 sm:px-4 bg-white text-slate-600 hover:bg-platinum-800 border border-silver-600 text-xs sm:text-sm font-bold rounded-xl transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 py-2.5 px-3 sm:px-4 bg-steel_azure hover:bg-steel_azure-400 text-white text-xs sm:text-sm font-extrabold rounded-xl shadow-sm transition flex items-center justify-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-check text-xs"></i>
                    <span>Apply Adjustment</span>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
