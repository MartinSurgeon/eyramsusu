<?php
// request_payout.php - Request Customer Payout & Close Card (UI/UX & HCI Optimized)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = get_logged_in_user();
$pdo = get_db_connection();
$error = '';

$cardId = isset($_GET['card_id']) ? (int)$_GET['card_id'] : 0;

// Fetch all cards eligible for payout (both 31/31 completed and active with savings)
$availableCards = get_cards_eligible_for_payout($user['role'] === 'collector' ? $user['id'] : null);
$completedCards = array_filter($availableCards, fn($c) => $c['is_completed']);
$activeCards = array_filter($availableCards, fn($c) => !$c['is_completed']);

$breakdown = null;
if ($cardId > 0) {
    $breakdown = calculate_payout_breakdown($cardId);
}

// Handle Payout Request / Direct Cashout Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postCardId = (int)($_POST['card_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Customer payout');

    if ($postCardId <= 0) {
        $error = 'Please select a Susu Card.';
    } else {
        $calc = calculate_payout_breakdown($postCardId);
        if (!$calc) {
            $error = 'Invalid card or card not found.';
        } else {
            // Check if there is already a pending or paid payout
            $stmtCheck = $pdo->prepare("SELECT id, status FROM payouts WHERE card_id = ? AND status IN ('pending', 'paid')");
            $stmtCheck->execute([$postCardId]);
            $existingPayout = $stmtCheck->fetch();

            if ($existingPayout && $existingPayout['status'] === 'paid') {
                $error = 'This Susu Card has already been paid out and settled.';
            } elseif ($existingPayout && $existingPayout['status'] === 'pending' && $user['role'] !== 'admin') {
                $error = 'A payout request is already pending for this card. Please check with the Admin.';
            } else {
                try {
                    $pdo->beginTransaction();

                    if ($user['role'] === 'admin') {
                        // Direct Cashout for Admin
                        $stmtInsert = $pdo->prepare("
                            INSERT INTO payouts (card_id, customer_id, collector_id, total_saved, business_fee, change_refunded, customer_payout, status, reason, approved_by, paid_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, CURRENT_TIMESTAMP)
                        ");
                        $stmtInsert->execute([
                            $postCardId,
                            $calc['card']['customer_id'],
                            $calc['card']['assigned_collector_id'] ?? $user['id'],
                            $calc['total_saved'],
                            $calc['business_fee'],
                            $calc['change_refunded'],
                            $calc['customer_payout'],
                            $reason,
                            $user['id']
                        ]);

                        // Close card
                        $newStatus = ($calc['spaces_filled'] >= $calc['card']['total_spaces']) ? 'completed' : 'closed_early';
                        $stmtClose = $pdo->prepare("UPDATE susu_cards SET status = ?, closed_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmtClose->execute([$newStatus, $postCardId]);

                        // Clear customer change float
                        $stmtFloat = $pdo->prepare("UPDATE customers SET change_balance = 0.00 WHERE id = ?");
                        $stmtFloat->execute([$calc['card']['customer_id']]);

                        // Notify Collector
                        if (!empty($calc['card']['assigned_collector_id'])) {
                            create_notification(
                                $calc['card']['assigned_collector_id'],
                                'payout_paid',
                                "Payout Disbursed to Client",
                                "Admin paid out " . format_money($calc['customer_payout']) . " for {$calc['card']['full_name']} (Card #{$calc['card']['card_number']}).",
                                "view_card.php?id=" . $postCardId
                            );
                        }

                        $pdo->commit();
                        set_flash_message('success', "Payout of " . format_money($calc['customer_payout']) . " successfully paid out to {$calc['card']['full_name']}! Card closed.");
                        header('Location: payouts.php');
                        exit;

                    } else {
                        // Collector submits request for admin approval
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
                        header('Location: collector_dashboard.php');
                        exit;
                    }
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

$pageTitle = "Customer Payout & Cashout";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">

    <!-- Header (HCI: Jakob's Law) -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-steel_azure">Customer Payout & Cashout</h1>
            <p class="text-xs text-slate-500 mt-0.5">Disburse savings minus the 1-space Susu management fee.</p>
        </div>
        <a href="<?= $user['role'] === 'admin' ? 'payouts.php' : 'collector_dashboard.php' ?>" 
           class="btn-touch px-3.5 py-2 bg-white hover:bg-slate-100 text-slate-600 border border-slate-300 rounded-xl text-xs font-bold inline-flex items-center gap-1.5 transition shadow-2xs">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            <span>Back</span>
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-4 bg-red-50 border-2 border-red-200 text-red-700 rounded-2xl text-xs font-semibold flex items-center gap-3">
            <i class="fa-solid fa-circle-exclamation text-red-500 text-base flex-shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Payout Form with Zero-Scroll Selection & Immediate Action CTA -->
    <form id="payoutForm" method="POST" action="request_payout.php" class="space-y-5">
        <input type="hidden" id="selected_card_id" name="card_id" value="<?= $cardId > 0 ? $cardId : '' ?>" required>

        <!-- ============================================================
             HERO STATE: SELECTED CARD TILE (Hick's Law & Zero-Scroll CTA)
             When a card is chosen, the long picker list collapses and this
             tile appears at the very top with the breakdown immediately below!
             ============================================================ -->
        <div id="selectedCardHeroContainer" class="<?= $cardId > 0 ? '' : 'hidden' ?> bg-white rounded-3xl border-2 border-emerald-500 shadow-sm p-4 sm:p-5 space-y-3 transition-all duration-200">
            <div class="flex items-center justify-between pb-2.5 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-emerald-600 text-white text-xs font-black flex items-center justify-center">
                        <i class="fa-solid fa-check text-[11px]"></i>
                    </span>
                    <h2 class="text-xs font-black uppercase tracking-wider text-emerald-800">Selected Card to Cash Out</h2>
                </div>
                <button type="button" onclick="toggleCardPicker(true)" 
                        class="btn-touch px-3 py-1.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold inline-flex items-center gap-1.5 transition cursor-pointer shadow-2xs">
                    <i class="fa-solid fa-arrows-rotate text-xs text-steel_azure"></i>
                    <span>Change Card</span>
                </button>
            </div>

            <!-- Spacious Card Tile (Fitts's Law: min-h-[64px], p-4, rounded-2xl, Clear Spacing) -->
            <div class="min-h-[64px] p-4 rounded-2xl bg-gradient-to-r from-emerald-50/90 via-teal-50/40 to-slate-50 border border-emerald-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3.5">
                <div class="flex items-start sm:items-center gap-3.5 min-w-0">
                    <div id="heroCardAvatar" class="w-11 h-11 rounded-xl <?= ($breakdown && $breakdown['is_full_cycle']) ? 'bg-emerald-600' : 'bg-steel_azure' ?> text-white font-black flex items-center justify-center text-xs sm:text-sm flex-shrink-0 shadow-xs">
                        <?= $breakdown ? strtoupper(substr($breakdown['card']['full_name'], 0, 2)) : '' ?>
                    </div>
                    <div class="min-w-0 space-y-1">
                        <!-- Client Name & Account # in bold -->
                        <div class="flex items-center gap-2 flex-wrap">
                            <span id="heroClientName" class="font-black text-slate-900 text-sm sm:text-base tracking-tight truncate">
                                <?= $breakdown ? htmlspecialchars($breakdown['card']['full_name']) : '' ?>
                            </span>
                            <span id="heroAccountNumber" class="font-bold text-slate-900 bg-white border border-slate-300 px-2 py-0.5 rounded-lg text-xs font-mono">
                                #<?= $breakdown ? htmlspecialchars($breakdown['card']['account_number']) : '' ?>
                            </span>
                        </div>

                        <!-- Daily Susu Rate & Spaces filled indicator -->
                        <div class="flex items-center gap-2 flex-wrap text-xs text-slate-600">
                            <span id="heroCardNumber" class="font-bold text-slate-700">
                                Card #<?= $breakdown ? $breakdown['card']['card_number'] : '' ?>
                            </span>
                            <span class="text-slate-300">&bull;</span>
                            <span id="heroDailyRate" class="font-semibold">
                                <?= $breakdown ? format_money($breakdown['daily_amount']) . ' / day' : '' ?>
                            </span>
                            <span class="text-slate-300">&bull;</span>
                            <span id="heroSpacesBadge" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-black <?= ($breakdown && $breakdown['is_full_cycle']) ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-800 border border-amber-300' ?>">
                                <i class="fa-solid <?= ($breakdown && $breakdown['is_full_cycle']) ? 'fa-award' : 'fa-clock' ?> text-[10px]"></i>
                                <span><?= $breakdown ? ($breakdown['is_full_cycle'] ? '31/31 Spaces Filled' : $breakdown['spaces_filled'] . ' of 31 spaces filled') : '' ?></span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Prominent Net Cashout badge (GH₵ 600.00 Net) in high-contrast emerald -->
                <div class="flex items-center justify-between sm:justify-end gap-3 pt-2 sm:pt-0 border-t sm:border-t-0 border-emerald-100 flex-shrink-0">
                    <div id="heroNetBadge" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 text-white font-black text-sm sm:text-base shadow-xs whitespace-nowrap">
                        <i class="fa-solid fa-hand-holding-dollar text-sm opacity-85"></i>
                        <span><?= $breakdown ? format_money($breakdown['customer_payout']) . ' Net' : '' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
             SPACIOUS CARD PICKER (Visible when selecting or changing card)
             Fitts's Law: min-h-[64px], p-4, rounded-2xl, clear spacing
             ============================================================ -->
        <div id="cardPickerContainer" class="<?= $cardId > 0 ? 'hidden' : '' ?> bg-white rounded-3xl border-2 border-silver-600 shadow-sm p-4 sm:p-6 space-y-4 transition-all duration-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-steel_azure text-white text-xs font-black flex items-center justify-center">1</span>
                    <h2 class="text-sm sm:text-base font-black text-slate-800">Select Susu Card to Cash Out</h2>
                </div>
                
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-400">
                        <?= count($availableCards) ?> eligible
                    </span>
                    <button type="button" id="cardPickerKeepBtn" onclick="toggleCardPicker(false)" 
                            class="hidden btn-touch px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition cursor-pointer">
                        <i class="fa-solid fa-xmark mr-1"></i> Close
                    </button>
                </div>
            </div>

            <!-- Quick Filter & Search Box -->
            <div class="space-y-2.5">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="cardSearchInput" placeholder="Filter by client name, account #, or phone..."
                           class="w-full pl-9 pr-8 py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-semibold text-slate-800 placeholder-slate-400 focus:border-steel_azure focus:ring-2 focus:ring-steel_azure/20 outline-none transition bg-slate-50/50">
                    <button type="button" id="clearCardSearchBtn" onclick="clearCardSearch()" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs cursor-pointer">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>

                <!-- Segmented Filter Pills (Miller's Law Chunking) -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none text-xs">
                    <button type="button" onclick="setCardFilter('all', this)" class="card-filter-btn btn-touch px-3 py-1.5 rounded-xl font-bold bg-steel_azure text-white shadow-xs flex-shrink-0 cursor-pointer transition">
                        All (<?= count($availableCards) ?>)
                    </button>
                    <button type="button" onclick="setCardFilter('completed', this)" class="card-filter-btn btn-touch px-3 py-1.5 rounded-xl font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 flex-shrink-0 cursor-pointer transition">
                        🎯 Completed (<?= count($completedCards) ?>)
                    </button>
                    <button type="button" onclick="setCardFilter('active', this)" class="card-filter-btn btn-touch px-3 py-1.5 rounded-xl font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 flex-shrink-0 cursor-pointer transition">
                        ⏱️ Active Plans (<?= count($activeCards) ?>)
                    </button>
                </div>
            </div>

            <!-- Spacious Card Tiles List (Fitts's Law: min-h-[64px], p-4, rounded-2xl, Clear Spacing) -->
            <div id="cardTilesContainer" class="space-y-3 max-h-[420px] overflow-y-auto pr-1 scrollbar-thin" role="radiogroup" aria-label="Eligible Susu Cards">
                <?php if (empty($availableCards)): ?>
                    <div class="text-center py-8 text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                        <i class="fa-solid fa-box-open text-3xl mb-2 text-slate-300"></i>
                        <p class="text-xs font-semibold">No cards are currently eligible for payout.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($availableCards as $c): 
                        $isSelected = ($cardId === (int)$c['id']);
                        $initials = strtoupper(substr($c['full_name'], 0, 2));
                        $isCompleted = (bool)$c['is_completed'];
                        $searchHaystack = strtolower($c['full_name'] . ' ' . $c['account_number'] . ' ' . ($c['phone'] ?? '') . ' ' . ($c['card_number'] ?? ''));
                    ?>
                        <!-- Spacious Card Tile (Fitts's Law min-h-[64px], p-4, rounded-2xl) -->
                        <div class="card-tile min-h-[64px] p-4 rounded-2xl border-2 transition cursor-pointer select-none flex flex-col sm:flex-row sm:items-center justify-between gap-3.5 <?= $isSelected ? 'border-emerald-500 bg-emerald-50/50 ring-2 ring-emerald-500/20 shadow-sm' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/60 shadow-2xs' ?>"
                             data-id="<?= $c['id'] ?>"
                             data-completed="<?= $isCompleted ? '1' : '0' ?>"
                             data-search="<?= htmlspecialchars($searchHaystack) ?>"
                             onclick="selectPayoutCard(<?= $c['id'] ?>)">
                            
                            <!-- Client Identity & Progress -->
                            <div class="flex items-start sm:items-center gap-3.5 min-w-0">
                                <div class="w-11 h-11 rounded-xl <?= $isCompleted ? 'bg-emerald-600' : 'bg-steel_azure' ?> text-white font-black flex items-center justify-center text-xs sm:text-sm flex-shrink-0 shadow-xs">
                                    <?= $initials ?>
                                </div>
                                <div class="min-w-0 space-y-1">
                                    <!-- Client Name & Account # in bold -->
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-black text-slate-900 text-sm sm:text-base tracking-tight truncate">
                                            <?= htmlspecialchars($c['full_name']) ?>
                                        </span>
                                        <span class="font-bold text-slate-900 bg-slate-100 border border-slate-300 px-2 py-0.5 rounded-lg text-xs font-mono">
                                            #<?= htmlspecialchars($c['account_number']) ?>
                                        </span>
                                    </div>

                                    <!-- Daily Susu Rate & Spaces filled indicator -->
                                    <div class="flex items-center gap-2 flex-wrap text-xs text-slate-600">
                                        <span class="font-bold text-slate-700">Card #<?= $c['card_number'] ?></span>
                                        <span class="text-slate-300">&bull;</span>
                                        <span class="font-semibold"><?= format_money($c['daily_amount']) ?> / day</span>
                                        <span class="text-slate-300">&bull;</span>
                                        <?php if ($isCompleted): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                <i class="fa-solid fa-award text-[10px]"></i>
                                                <span>31/31 Spaces Filled</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                                <i class="fa-solid fa-clock text-[10px]"></i>
                                                <span><?= $c['spaces_filled'] ?> of <?= $c['total_spaces'] ?> spaces filled</span>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($c['is_pending']): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-black bg-purple-100 text-purple-800 border border-purple-300">
                                                <i class="fa-solid fa-hourglass text-[10px]"></i>
                                                <span>Pending Approval</span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Prominent Net Cashout badge (GH₵ 600.00 Net) in high-contrast emerald -->
                            <div class="flex items-center justify-between sm:justify-end gap-3 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100 flex-shrink-0">
                                <div class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 text-white font-black text-xs sm:text-sm shadow-xs whitespace-nowrap">
                                    <i class="fa-solid fa-hand-holding-dollar text-xs opacity-85"></i>
                                    <span><?= format_money($c['estimated_payout']) ?> Net</span>
                                </div>

                                <div class="card-radio-circle w-7 h-7 rounded-full border-2 flex items-center justify-center transition <?= $isSelected ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 bg-white text-transparent' ?>">
                                    <i class="fa-solid fa-check text-xs"></i>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div id="noSearchMatchMsg" class="hidden text-center py-8 text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                    <i class="fa-solid fa-magnifying-glass text-2xl mb-1 text-slate-300"></i>
                    <p class="text-xs font-semibold">No cards match your search term.</p>
                </div>
            </div>
        </div>

        <!-- ============================================================
             ITEMIZED FINANCIAL BREAKDOWN & SUBMIT ACTION CTA
             Directly visible underneath the selected card! No scroll required!
             ============================================================ -->
        <div id="breakdownSection" class="<?= $breakdown ? '' : 'hidden' ?> bg-platinum-800 rounded-3xl border-2 border-silver-600 p-5 sm:p-6 space-y-4 shadow-sm transition-all duration-200">
            <div class="flex items-center justify-between pb-3 border-b border-silver-600">
                <div>
                    <span class="w-6 h-6 rounded-full bg-steel_azure text-white text-xs font-black inline-flex items-center justify-center mr-1.5">2</span>
                    <h3 id="breakdownClientName" class="font-extrabold text-slate-800 text-sm sm:text-base inline">
                        <?= $breakdown ? htmlspecialchars($breakdown['card']['full_name']) : '' ?>
                    </h3>
                    <div id="breakdownCardMeta" class="text-[11px] text-slate-500 mt-0.5">
                        <?= $breakdown ? 'Card #' . $breakdown['card']['card_number'] . ' &bull; ' . $breakdown['spaces_filled'] . ' of 31 spaces completed' : '' ?>
                    </div>
                </div>
                <span id="breakdownCycleBadge" class="px-2.5 py-1 rounded-full text-xs font-black <?= ($breakdown && $breakdown['is_full_cycle']) ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-800 border border-amber-300' ?>">
                    <?= $breakdown ? ($breakdown['is_full_cycle'] ? 'Full 31 Spaces' : 'Early Stop (' . $breakdown['spaces_filled'] . ' sp)') : '' ?>
                </span>
            </div>

            <!-- Breakdown Math -->
            <div class="space-y-2.5 text-xs sm:text-sm">
                <div class="flex items-center justify-between text-slate-700">
                    <span id="breakdownTotalSavedLabel">Total Contributed <?= $breakdown ? '(' . $breakdown['spaces_filled'] . ' spaces)' : '' ?>:</span>
                    <strong id="breakdownTotalSavedVal" class="font-bold text-slate-900"><?= $breakdown ? format_money($breakdown['total_saved']) : 'GH₵ 0.00' ?></strong>
                </div>

                <div class="flex items-center justify-between text-red-600 font-semibold">
                    <span>Less: Business Fee (1 contribution rate):</span>
                    <strong id="breakdownBusinessFeeVal">- <?= $breakdown ? format_money($breakdown['business_fee']) : 'GH₵ 0.00' ?></strong>
                </div>

                <div id="breakdownFloatRow" class="flex items-center justify-between text-pumpkin_spice font-semibold <?= ($breakdown && $breakdown['change_refunded'] > 0) ? '' : 'hidden' ?>">
                    <span>Plus: Customer Float Refunded:</span>
                    <strong id="breakdownFloatVal">+ <?= $breakdown ? format_money($breakdown['change_refunded']) : 'GH₵ 0.00' ?></strong>
                </div>

                <!-- Summary Callout Banner (Peak-End Rule) -->
                <div class="flex items-center justify-between text-base sm:text-lg font-black text-emerald-900 pt-3 border-t-2 border-silver-600 bg-emerald-50 -mx-5 sm:-mx-6 px-5 sm:px-6 py-3.5 rounded-b-2xl mt-2">
                    <div>
                        <div class="text-[11px] uppercase tracking-wider font-extrabold text-emerald-700">Net Customer Disbursal</div>
                        <div class="text-xs text-emerald-800 font-medium">Final amount to pay customer</div>
                    </div>
                    <span id="breakdownNetPayoutVal" class="text-xl sm:text-2xl text-emerald-800 font-black">
                        <?= $breakdown ? format_money($breakdown['customer_payout']) : 'GH₵ 0.00' ?>
                    </span>
                </div>
            </div>

            <!-- Expandable: How is this calculated? (Tesler's Law) -->
            <button type="button" onclick="toggleExplainer()" class="flex items-center gap-1.5 text-xs text-steel_azure hover:text-steel_azure-400 font-bold transition cursor-pointer">
                <i class="fa-solid fa-circle-question text-xs"></i>
                <span>How is this calculated?</span>
            </button>
            <div id="calcExplainer" class="hidden">
                <div class="text-[11px] text-slate-500 bg-white/80 p-3.5 rounded-xl border border-silver-600/70 space-y-1.5">
                    <p><strong>Susu Business Rule:</strong> When a customer completes their 31-space card (or stops early), the business retains exactly <strong>one contribution space</strong> as its management fee.</p>
                    <p>Retained fee for this card: <strong id="retainedFeeExplainer"><?= $breakdown ? format_money($breakdown['business_fee']) : 'GH₵ 0.00' ?></strong>.</p>
                    <p>Any accumulated change balance (float) is returned in full to the client.</p>
                    <p>Once settled, all 31 spaces are archived permanently in the passbook history.</p>
                </div>
            </div>

            <!-- Notes / Reason -->
            <div>
                <label for="reason_input" class="block text-xs font-bold text-slate-700 mb-1">Reason / Reference Notes</label>
                <input type="text" id="reason_input" name="reason"
                       value="<?= ($breakdown && $breakdown['is_full_cycle']) ? 'Completed all 31 spaces' : 'Customer requested early payout' ?>"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-steel_azure outline-none text-xs sm:text-sm text-slate-800 transition bg-white">
            </div>

            <!-- Action CTA (Hick's Law: 1 Obvious Primary Button triggering Confirmation Modal) -->
            <div class="pt-2">
                <?php if ($user['role'] === 'admin'): ?>
                    <button type="button" onclick="openPayoutConfirmModal()"
                            class="w-full btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white font-black text-sm sm:text-base tracking-wide shadow-md hover:shadow-lg transition flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-hand-holding-dollar text-base"></i>
                        <span id="adminActionBtnText">Disburse Payout & Settle Card (<?= $breakdown ? format_money($breakdown['customer_payout']) : '' ?>)</span>
                    </button>
                    <p class="text-[11px] text-slate-400 text-center mt-2 font-medium">
                        <i class="fa-solid fa-shield-halved text-steel_azure mr-1"></i>
                        A confirmation modal will appear before funds are disbursed.
                    </p>
                <?php else: ?>
                    <button type="button" onclick="openPayoutConfirmModal()"
                            class="w-full btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white font-black text-sm sm:text-base tracking-wide shadow-md hover:shadow-lg transition flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-paper-plane text-sm"></i>
                        <span>Submit Payout Request for Admin Approval</span>
                    </button>
                    <p class="text-[11px] text-slate-400 text-center mt-2 font-medium">Office manager will be notified in real time to approve and disburse cash.</p>
                <?php endif; ?>
            </div>

        </div>

        <!-- Initial Placeholder when no card is selected -->
        <div id="noCardSelectedNotice" class="<?= $breakdown ? 'hidden' : '' ?> p-6 bg-slate-50 border-2 border-dashed border-slate-200 rounded-3xl text-center space-y-1">
            <i class="fa-solid fa-arrow-up text-slate-300 text-2xl mb-1"></i>
            <p class="text-xs font-bold text-slate-600">Please select a card from the list above.</p>
            <p class="text-[11px] text-slate-400">The payout breakdown and cashout action will appear here automatically without scrolling.</p>
        </div>

    </form>

</div>

<!-- ============================================================
     CONFIRMATION MODAL (Admin Cashout Execution & Collector Request)
     ============================================================ -->
<div id="payoutConfirmModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs hidden" role="dialog" aria-modal="true" aria-labelledby="payout_modal_title">
    <div class="bg-white rounded-3xl border border-silver-600 shadow-2xl max-w-lg w-full overflow-hidden transform transition-all scale-95 duration-200" id="payout_modal_box">
        <!-- Header -->
        <div class="p-5 sm:p-6 bg-gradient-to-r <?= $user['role'] === 'admin' ? 'from-emerald-600 to-teal-700' : 'from-steel_azure to-steel_azure-400' ?> text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-white/20 flex items-center justify-center text-xl flex-shrink-0 shadow-inner">
                    <i class="fa-solid <?= $user['role'] === 'admin' ? 'fa-hand-holding-dollar' : 'fa-clipboard-check' ?>"></i>
                </div>
                <div>
                    <h3 id="payout_modal_title" class="font-black text-base sm:text-lg leading-tight">
                        <?= $user['role'] === 'admin' ? 'Confirm Immediate Cashout' : 'Confirm Payout Request' ?>
                    </h3>
                    <p id="modalCardSubtitle" class="text-xs text-white/80 mt-0.5">
                        <?= $breakdown ? 'Card #' . $breakdown['card']['card_number'] . ' &bull; ' . htmlspecialchars($breakdown['card']['full_name']) : '' ?>
                    </p>
                </div>
            </div>
            <button type="button" onclick="closePayoutConfirmModal()" class="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer" title="Close modal" aria-label="Close modal">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <div class="p-5 sm:p-6 space-y-4">
            <!-- Client & Card Details -->
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2.5 text-xs sm:text-sm">
                <div class="flex items-center justify-between text-slate-600">
                    <span>Client Name:</span>
                    <strong id="modalClientName" class="text-slate-900 font-extrabold"><?= $breakdown ? htmlspecialchars($breakdown['card']['full_name']) : '' ?></strong>
                </div>
                <div class="flex items-center justify-between text-slate-600">
                    <span>Account Number:</span>
                    <strong id="modalAccountNum" class="font-mono text-slate-800">#<?= $breakdown ? htmlspecialchars($breakdown['card']['account_number']) : '' ?></strong>
                </div>
                <div class="flex items-center justify-between text-slate-600">
                    <span>Gross Savings Contributed:</span>
                    <strong id="modalGrossSaved" class="text-slate-900"><?= $breakdown ? format_money($breakdown['total_saved']) : '' ?></strong>
                </div>
                <div class="flex items-center justify-between text-red-600 font-semibold">
                    <span>Less Susu Management Fee:</span>
                    <strong id="modalFee">- <?= $breakdown ? format_money($breakdown['business_fee']) : '' ?></strong>
                </div>
                <div id="modalFloatRow" class="flex items-center justify-between text-pumpkin_spice font-semibold <?= ($breakdown && $breakdown['change_refunded'] > 0) ? '' : 'hidden' ?>">
                    <span>Plus Float Refunded:</span>
                    <strong id="modalFloatVal">+ <?= $breakdown ? format_money($breakdown['change_refunded']) : '' ?></strong>
                </div>

                <!-- Final Payout Box -->
                <div class="pt-3 border-t-2 border-slate-200 flex items-center justify-between bg-emerald-50 -mx-4 px-4 py-3 rounded-b-xl mt-1 text-emerald-900">
                    <div>
                        <div class="text-[10px] uppercase tracking-wider font-extrabold text-emerald-700">
                            <?= $user['role'] === 'admin' ? 'Net Cash Given to Client' : 'Requested Payout Amount' ?>
                        </div>
                        <div class="text-xs text-emerald-800 font-medium">To be paid in full</div>
                    </div>
                    <div id="modalNetPayout" class="text-xl sm:text-2xl font-black text-emerald-800">
                        <?= $breakdown ? format_money($breakdown['customer_payout']) : '' ?>
                    </div>
                </div>
            </div>

            <!-- Warning Notice -->
            <div class="p-3.5 bg-blue-50 border border-blue-200 rounded-xl flex items-start gap-2.5 text-xs text-blue-900 font-medium">
                <i class="fa-solid fa-circle-info text-steel_azure text-sm mt-0.5 flex-shrink-0"></i>
                <span>
                    <?php if ($user['role'] === 'admin'): ?>
                        Executing this cashout will immediately settle the card, decrement the customer's float, and permanently record this payout.
                    <?php else: ?>
                        Submitting this request alerts the office administrator to verify the card passbook and disburse the cash.
                    <?php endif; ?>
                </span>
            </div>

            <!-- Modal Action Buttons (Hick's Law: 1 Primary + 1 Secondary) -->
            <div class="pt-2 border-t border-slate-100 flex items-center justify-end gap-3">
                <button type="button" onclick="closePayoutConfirmModal()" class="btn-touch px-4 py-2.5 bg-white hover:bg-slate-100 text-slate-600 border border-slate-300 text-xs font-bold rounded-xl transition cursor-pointer">
                    Cancel
                </button>
                <button type="button" onclick="submitFinalPayoutForm()" class="btn-touch px-5 py-2.5 <?= $user['role'] === 'admin' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-steel_azure hover:bg-steel_azure-400' ?> text-white text-xs sm:text-sm font-black rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-circle-check text-sm"></i>
                    <span><?= $user['role'] === 'admin' ? 'Confirm & Settle Cashout' : 'Confirm & Submit Request' ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const availableCardsData = <?= json_encode(array_values($availableCards)) ?>;
const userRole = '<?= $user['role'] ?>';
let selectedCardId = <?= $cardId > 0 ? $cardId : 'null' ?>;
let currentFilter = 'all';

function formatMoney(amount) {
    return 'GH₵ ' + Number(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function toggleExplainer() {
    const el = document.getElementById('calcExplainer');
    if (el) el.classList.toggle('hidden');
}

/**
 * Fitts's Law & Zero Scroll Handler:
 * When a card is selected, collapse the multi-card picker and reveal
 * the Selected Card Hero Tile with breakdown and submit button immediately in view!
 */
function selectPayoutCard(id) {
    const card = availableCardsData.find(c => parseInt(c.id) === parseInt(id));
    if (!card) return;

    selectedCardId = parseInt(card.id);
    document.getElementById('selected_card_id').value = selectedCardId;

    // 1. Update Hero Tile
    updateHeroTile(card);

    // 2. Update Breakdown Section
    updateBreakdownSection(card);

    // 3. Update Confirmation Modal
    updateConfirmModal(card);

    // 4. View Transition: Hide picker, show Hero Tile & Breakdown (Zero Scroll required!)
    document.getElementById('cardPickerContainer').classList.add('hidden');
    document.getElementById('selectedCardHeroContainer').classList.remove('hidden');
    document.getElementById('breakdownSection').classList.remove('hidden');
    
    const noCardNotice = document.getElementById('noCardSelectedNotice');
    if (noCardNotice) noCardNotice.classList.add('hidden');

    // 5. Update browser URL silently without page reload
    if (window.history && window.history.replaceState) {
        window.history.replaceState(null, '', 'request_payout.php?card_id=' + selectedCardId);
    }

    // 6. Highlight active tile in picker list
    document.querySelectorAll('.card-tile').forEach(tile => {
        const tileId = parseInt(tile.getAttribute('data-id'));
        const circle = tile.querySelector('.card-radio-circle');
        if (tileId === selectedCardId) {
            tile.className = 'card-tile min-h-[64px] p-4 rounded-2xl border-2 transition cursor-pointer select-none flex flex-col sm:flex-row sm:items-center justify-between gap-3.5 border-emerald-500 bg-emerald-50/50 ring-2 ring-emerald-500/20 shadow-sm';
            if (circle) circle.className = 'card-radio-circle w-7 h-7 rounded-full border-2 flex items-center justify-center transition border-emerald-600 bg-emerald-600 text-white';
        } else {
            tile.className = 'card-tile min-h-[64px] p-4 rounded-2xl border-2 transition cursor-pointer select-none flex flex-col sm:flex-row sm:items-center justify-between gap-3.5 border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/60 shadow-2xs';
            if (circle) circle.className = 'card-radio-circle w-7 h-7 rounded-full border-2 flex items-center justify-center transition border-slate-300 bg-white text-transparent';
        }
    });
}

function toggleCardPicker(show) {
    const picker = document.getElementById('cardPickerContainer');
    const hero = document.getElementById('selectedCardHeroContainer');
    const keepBtn = document.getElementById('cardPickerKeepBtn');

    if (show) {
        picker.classList.remove('hidden');
        if (selectedCardId && keepBtn) {
            keepBtn.classList.remove('hidden');
        }
        picker.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        if (selectedCardId) {
            picker.classList.add('hidden');
            hero.classList.remove('hidden');
            if (keepBtn) keepBtn.classList.add('hidden');
        }
    }
}

function updateHeroTile(card) {
    const isCompleted = (card.is_completed == 1 || card.is_completed === true);
    const initials = (card.full_name || '').substring(0, 2).toUpperCase();

    const avatar = document.getElementById('heroCardAvatar');
    if (avatar) {
        avatar.textContent = initials;
        avatar.className = 'w-11 h-11 rounded-xl text-white font-black flex items-center justify-center text-xs sm:text-sm flex-shrink-0 shadow-xs ' +
            (isCompleted ? 'bg-emerald-600' : 'bg-steel_azure');
    }

    document.getElementById('heroClientName').textContent = card.full_name;
    document.getElementById('heroAccountNumber').textContent = '#' + card.account_number;
    document.getElementById('heroCardNumber').textContent = 'Card #' + card.card_number;
    document.getElementById('heroDailyRate').textContent = formatMoney(card.daily_amount) + ' / day';

    const spacesBadge = document.getElementById('heroSpacesBadge');
    if (spacesBadge) {
        if (isCompleted) {
            spacesBadge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300';
            spacesBadge.innerHTML = '<i class="fa-solid fa-award text-[10px]"></i> <span>31/31 Spaces Filled</span>';
        } else {
            spacesBadge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300';
            spacesBadge.innerHTML = '<i class="fa-solid fa-clock text-[10px]"></i> <span>' + card.spaces_filled + ' of ' + (card.total_spaces || 31) + ' spaces filled</span>';
        }
    }

    const totalSaved = parseFloat(card.total_saved) || 0;
    const dailyAmount = parseFloat(card.daily_amount) || 0;
    const changeBalance = parseFloat(card.change_balance) || 0;
    const fee = Math.min(dailyAmount, totalSaved);
    const payout = Math.max(0, totalSaved - fee) + changeBalance;

    const heroNetBadge = document.getElementById('heroNetBadge');
    if (heroNetBadge) {
        heroNetBadge.innerHTML = '<i class="fa-solid fa-hand-holding-dollar text-sm opacity-85"></i> <span>' + formatMoney(payout) + ' Net</span>';
    }
}

function updateBreakdownSection(card) {
    const isCompleted = (card.is_completed == 1 || card.is_completed === true);
    const totalSaved = parseFloat(card.total_saved) || 0;
    const dailyAmount = parseFloat(card.daily_amount) || 0;
    const changeBalance = parseFloat(card.change_balance) || 0;
    const fee = Math.min(dailyAmount, totalSaved);
    const payout = Math.max(0, totalSaved - fee) + changeBalance;

    document.getElementById('breakdownClientName').textContent = card.full_name;
    document.getElementById('breakdownCardMeta').textContent = 'Card #' + card.card_number + ' • ' + card.spaces_filled + ' of ' + (card.total_spaces || 31) + ' spaces completed';

    const cycleBadge = document.getElementById('breakdownCycleBadge');
    if (cycleBadge) {
        if (isCompleted) {
            cycleBadge.className = 'px-2.5 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-800 border border-emerald-300';
            cycleBadge.textContent = 'Full 31 Spaces';
        } else {
            cycleBadge.className = 'px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300';
            cycleBadge.textContent = 'Early Stop (' + card.spaces_filled + ' sp)';
        }
    }

    document.getElementById('breakdownTotalSavedLabel').textContent = 'Total Contributed (' + card.spaces_filled + ' spaces):';
    document.getElementById('breakdownTotalSavedVal').textContent = formatMoney(totalSaved);
    document.getElementById('breakdownBusinessFeeVal').textContent = '- ' + formatMoney(fee);

    const floatRow = document.getElementById('breakdownFloatRow');
    if (floatRow) {
        if (changeBalance > 0) {
            floatRow.classList.remove('hidden');
            document.getElementById('breakdownFloatVal').textContent = '+ ' + formatMoney(changeBalance);
        } else {
            floatRow.classList.add('hidden');
        }
    }

    document.getElementById('breakdownNetPayoutVal').textContent = formatMoney(payout);
    const retainedExplainer = document.getElementById('retainedFeeExplainer');
    if (retainedExplainer) retainedExplainer.textContent = formatMoney(fee);

    const reasonInput = document.getElementById('reason_input');
    if (reasonInput) {
        reasonInput.value = isCompleted ? 'Completed all 31 spaces' : 'Customer requested early payout';
    }

    const adminBtn = document.getElementById('adminActionBtnText');
    if (adminBtn) {
        adminBtn.textContent = 'Disburse Payout & Settle Card (' + formatMoney(payout) + ')';
    }
}

function updateConfirmModal(card) {
    const totalSaved = parseFloat(card.total_saved) || 0;
    const dailyAmount = parseFloat(card.daily_amount) || 0;
    const changeBalance = parseFloat(card.change_balance) || 0;
    const fee = Math.min(dailyAmount, totalSaved);
    const payout = Math.max(0, totalSaved - fee) + changeBalance;

    const sub = document.getElementById('modalCardSubtitle');
    if (sub) sub.textContent = 'Card #' + card.card_number + ' • ' + card.full_name;

    const name = document.getElementById('modalClientName');
    if (name) name.textContent = card.full_name;

    const acc = document.getElementById('modalAccountNum');
    if (acc) acc.textContent = '#' + card.account_number;

    const gross = document.getElementById('modalGrossSaved');
    if (gross) gross.textContent = formatMoney(totalSaved);

    const feeEl = document.getElementById('modalFee');
    if (feeEl) feeEl.textContent = '- ' + formatMoney(fee);

    const floatRow = document.getElementById('modalFloatRow');
    if (floatRow) {
        if (changeBalance > 0) {
            floatRow.classList.remove('hidden');
            document.getElementById('modalFloatVal').textContent = '+ ' + formatMoney(changeBalance);
        } else {
            floatRow.classList.add('hidden');
        }
    }

    const net = document.getElementById('modalNetPayout');
    if (net) net.textContent = formatMoney(payout);
}

function setCardFilter(filter, btn) {
    currentFilter = filter;
    
    document.querySelectorAll('.card-filter-btn').forEach(b => {
        b.className = 'card-filter-btn btn-touch px-3 py-1.5 rounded-xl font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 flex-shrink-0 cursor-pointer transition';
    });
    if (btn) {
        btn.className = 'card-filter-btn btn-touch px-3 py-1.5 rounded-xl font-bold bg-steel_azure text-white shadow-xs flex-shrink-0 cursor-pointer transition';
    }
    
    applyCardSearchAndFilter();
}

function clearCardSearch() {
    const input = document.getElementById('cardSearchInput');
    input.value = '';
    document.getElementById('clearCardSearchBtn').classList.add('hidden');
    applyCardSearchAndFilter();
    input.focus();
}

function applyCardSearchAndFilter() {
    const term = (document.getElementById('cardSearchInput').value || '').trim().toLowerCase();
    const clearBtn = document.getElementById('clearCardSearchBtn');
    if (term.length > 0) {
        clearBtn.classList.remove('hidden');
    } else {
        clearBtn.classList.add('hidden');
    }

    const tiles = document.querySelectorAll('.card-tile');
    let visibleCount = 0;

    tiles.forEach(tile => {
        const isCompleted = tile.getAttribute('data-completed') === '1';
        const searchData = tile.getAttribute('data-search') || '';

        let matchesFilter = true;
        if (currentFilter === 'completed' && !isCompleted) matchesFilter = false;
        if (currentFilter === 'active' && isCompleted) matchesFilter = false;

        let matchesSearch = true;
        if (term && !searchData.includes(term)) matchesSearch = false;

        if (matchesFilter && matchesSearch) {
            tile.classList.remove('hidden');
            visibleCount++;
        } else {
            tile.classList.add('hidden');
        }
    });

    const noMatchMsg = document.getElementById('noSearchMatchMsg');
    if (noMatchMsg) {
        if (visibleCount === 0 && tiles.length > 0) {
            noMatchMsg.classList.remove('hidden');
        } else {
            noMatchMsg.classList.add('hidden');
        }
    }
}

document.getElementById('cardSearchInput')?.addEventListener('input', applyCardSearchAndFilter);

function openPayoutConfirmModal() {
    const modal = document.getElementById('payoutConfirmModal');
    const box = document.getElementById('payout_modal_box');
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

function closePayoutConfirmModal() {
    const modal = document.getElementById('payoutConfirmModal');
    const box = document.getElementById('payout_modal_box');
    if (!modal) return;
    box.classList.remove('scale-100');
    box.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 150);
}

function submitFinalPayoutForm() {
    const form = document.getElementById('payoutForm');
    if (form) {
        form.submit();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePayoutConfirmModal();
    }
});

document.addEventListener('click', function(e) {
    const modal = document.getElementById('payoutConfirmModal');
    if (modal && !modal.classList.contains('hidden') && e.target === modal) {
        closePayoutConfirmModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
