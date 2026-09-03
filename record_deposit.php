<?php
// record_deposit.php - Rapid Deposit Entry & Multi-Space Allocation
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = get_logged_in_user();
$pdo = get_db_connection();
$error = '';

$selectedCustomerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

function get_client_initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// Fetch customers list for selection
if ($user['role'] === 'collector') {
    $stmtCust = $pdo->prepare("
        SELECT c.id, c.full_name, c.account_number, c.phone, c.location, c.change_balance,
               sc.id as card_id, sc.card_number, sc.daily_amount, sc.spaces_filled, sc.total_spaces, sc.total_saved
        FROM customers c
        LEFT JOIN susu_cards sc ON c.id = sc.customer_id AND sc.status = 'active'
        WHERE c.assigned_collector_id = ? AND c.is_active = 1
        ORDER BY c.full_name ASC
    ");
    $stmtCust->execute([$user['id']]);
} else {
    $stmtCust = $pdo->query("
        SELECT c.id, c.full_name, c.account_number, c.phone, c.location, c.change_balance,
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

// Fetch any un-handed-over deposits recorded today for this active card
$cardTodayDeposits = [];
if ($activeCustomer && !empty($activeCustomer['card_id'])) {
    $stmtToday = $pdo->prepare("
        SELECT d.*, u.full_name as collector_name 
        FROM deposits d 
        JOIN users u ON d.collector_id = u.id 
        WHERE d.card_id = ? AND d.deposit_date = CURRENT_DATE() AND d.handover_id IS NULL
        ORDER BY d.space_number DESC
    ");
    $stmtToday->execute([$activeCustomer['card_id']]);
    $cardTodayDeposits = $stmtToday->fetchAll();
}

// Handle Undo / Cancel Deposit POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'undo_deposit') {
    $depositId = (int)($_POST['deposit_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Customer changed mind');

    $res = reverse_deposit($depositId, $reason, $user['id'], $user['role']);
    if ($res['success']) {
        set_flash_message('success', $res['message']);
        header("Location: record_deposit.php?customer_id=" . $res['customer_id']);
        exit;
    } else {
        $error = $res['message'];
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'undo_deposit')) {
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
        
        <!-- Customer Selection (HCI: Fitts's Law, Hick's Law, Miller's Law) -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                    1. Select Client *
                </label>
                <?php if ($activeCustomer): ?>
                    <button type="button" onclick="toggleClientPicker()" class="text-xs font-extrabold text-steel_azure hover:text-steel_azure-400 inline-flex items-center gap-1.5 transition cursor-pointer">
                        <i class="fa-solid fa-arrows-rotate text-[11px]"></i>
                        <span>Change Client</span>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Hidden input for form POST -->
            <input type="hidden" id="customer_select" name="customer_id" value="<?= $activeCustomer ? $activeCustomer['id'] : '' ?>" required>

            <?php if ($activeCustomer): ?>
                <!-- Selected Client Banner Card (Gestalt Proximity & Visual Confirmation) -->
                <div id="selected_client_card" class="bg-gradient-to-r from-blue-50/80 via-platinum-800 to-white p-3.5 sm:p-4 rounded-2xl border-2 border-steel_azure/30 shadow-2xs flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <!-- Initials Badge -->
                        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-gradient-to-br from-steel_azure to-cornflower_ocean text-white font-black text-sm sm:text-base flex items-center justify-center shadow-xs flex-shrink-0 font-heading">
                            <?= htmlspecialchars(get_client_initials($activeCustomer['full_name'])) ?>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-black text-sm sm:text-base text-slate-800 truncate">
                                    <?= htmlspecialchars($activeCustomer['full_name']) ?>
                                </h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-steel_azure/10 text-steel_azure border border-steel_azure/20">
                                    #<?= htmlspecialchars($activeCustomer['account_number']) ?>
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500 flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5 font-medium">
                                <?php if (!empty($activeCustomer['phone'])): ?>
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fa-solid fa-phone text-[10px] text-slate-400"></i>
                                        <?= htmlspecialchars($activeCustomer['phone']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($activeCustomer['location'])): ?>
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fa-solid fa-location-dot text-[10px] text-slate-400"></i>
                                        <?= htmlspecialchars($activeCustomer['location']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col items-end flex-shrink-0">
                        <span class="px-2.5 py-1 rounded-xl text-xs font-black bg-steel_azure text-white shadow-2xs">
                            <?= format_money($activeCustomer['daily_amount']) ?> <span class="text-[10px] font-normal opacity-80">/ space</span>
                        </span>
                        <button type="button" onclick="toggleClientPicker()" class="text-[11px] font-bold text-slate-500 hover:text-steel_azure mt-1.5 inline-flex items-center gap-1 cursor-pointer">
                            <i class="fa-solid fa-pen text-[9px]"></i>
                            <span>Change</span>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Searchable Client Combobox Picker -->
            <div id="client_picker_container" class="<?= $activeCustomer ? 'hidden' : '' ?> mt-2 bg-white rounded-2xl border-2 border-silver-600 shadow-sm overflow-hidden p-3.5 space-y-3">
                
                <!-- Instant Search Input Box -->
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="client_picker_search" 
                           placeholder="Type customer name, account number, or location..."
                           class="w-full pl-9 pr-8 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none text-xs sm:text-sm font-semibold text-slate-800 transition">
                    <button type="button" id="client_picker_clear" onclick="clearClientSearch()" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs" title="Clear">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="text-[10px] sm:text-[11px] text-slate-400 font-bold px-1 flex items-center justify-between uppercase tracking-wider">
                    <span>Available Clients (<?= count($customers) ?>)</span>
                    <span class="text-steel_azure font-semibold normal-case hidden sm:inline">Tap a client to select</span>
                </div>

                <!-- Scrollable Client Cards List (Miller's Law & Fitts's Law Touch Targets) -->
                <div class="max-h-64 overflow-y-auto space-y-2 pr-1" id="client_picker_list">
                    <?php foreach ($customers as $c): ?>
                        <div class="client-picker-item group p-3 rounded-xl border border-silver-600/80 hover:border-steel_azure bg-white hover:bg-platinum-800 cursor-pointer transition flex items-center justify-between gap-3 shadow-2xs"
                             data-id="<?= $c['id'] ?>"
                             data-search="<?= htmlspecialchars(strtolower($c['full_name'] . ' ' . $c['account_number'] . ' ' . ($c['phone'] ?? '') . ' ' . ($c['location'] ?? ''))) ?>"
                             onclick="selectClient(<?= $c['id'] ?>)">
                            
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-xl bg-slate-100 group-hover:bg-steel_azure group-hover:text-white text-steel_azure font-black text-xs flex items-center justify-center transition-colors flex-shrink-0 font-heading">
                                    <?= htmlspecialchars(get_client_initials($c['full_name'])) ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-black text-xs sm:text-sm text-slate-800 group-hover:text-steel_azure transition">
                                            <?= htmlspecialchars($c['full_name']) ?>
                                        </span>
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-600 border border-silver-600/50">
                                            #<?= htmlspecialchars($c['account_number']) ?>
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 truncate mt-0.5">
                                        <?= htmlspecialchars($c['location'] ?: 'Adaklu Waya') ?>
                                        <?php if (!empty($c['phone'])): ?> &bull; <?= htmlspecialchars($c['phone']) ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="text-right flex-shrink-0">
                                <div class="font-extrabold text-xs text-steel_azure">
                                    <?= format_money($c['daily_amount']) ?> <span class="text-[10px] text-slate-400 font-normal">/ space</span>
                                </div>
                                <div class="text-[10px] font-semibold mt-0.5">
                                    <?php if ($c['card_id']): ?>
                                        <span class="text-emerald-700"><?= $c['spaces_filled'] ?>/31 spaces</span>
                                    <?php else: ?>
                                        <span class="text-amber-700">No Active Card</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>

                    <div id="client_picker_empty" class="hidden py-8 text-center text-xs text-slate-400">
                        <i class="fa-solid fa-magnifying-glass text-2xl text-slate-300 mb-2"></i>
                        <p class="font-bold text-slate-600">No matching clients found</p>
                        <p class="text-[11px] mt-0.5">Try typing a different name, phone, or account number.</p>
                    </div>
                </div>

        </div>

        <?php if ($activeCustomer): ?>
            <?php if (!$activeCustomer['card_id']): ?>
                <!-- No Active Card Section (HCI: Hick's Law, Fitts's Law, Plain Language) -->
                <div class="rounded-2xl border-2 border-amber-300 bg-amber-50/70 p-5 sm:p-6 space-y-4">
                    
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0 text-lg">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <h4 class="font-black text-sm sm:text-base text-slate-800">
                                Active Card Required for <?= htmlspecialchars($activeCustomer['full_name']) ?>
                            </h4>
                            <p class="text-xs text-slate-600 mt-0.5 leading-relaxed">
                                Deposits cannot be stamped because this customer does not currently have an active 31-space Susu card.
                            </p>
                        </div>
                    </div>

                    <?php if ($user['role'] === 'admin'): ?>
                        <!-- Admin Action Box: Instant 1-Click Card Opener (Hick's Law: 1 Obvious Primary Action) -->
                        <div class="bg-white p-4 sm:p-5 rounded-xl border border-amber-200 shadow-xs space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black text-slate-700 uppercase tracking-wider">
                                    Open New 31-Space Card
                                </span>
                                <span class="text-[11px] text-slate-400 font-medium">31 spaces &bull; Card Cycle</span>
                            </div>

                            <div class="space-y-3">
                                <input type="hidden" name="redirect_to" value="record_deposit.php?customer_id=<?= $activeCustomer['id'] ?>">

                                <div>
                                    <label for="new_card_daily_amount" class="block text-xs font-bold text-slate-700 mb-1">
                                        Agreed Daily Savings Rate (GH₵) *
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500 font-black text-sm">GH₵</span>
                                        <input type="number" step="1" min="1" id="new_card_daily_amount" name="daily_amount"
                                               class="w-full pl-12 pr-4 py-2.5 rounded-xl border-2 border-silver-600 focus:border-pumpkin_spice outline-none text-base font-black text-slate-800 transition"
                                               placeholder="e.g. 20.00" value="20.00">
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-1">Amount the customer agrees to deposit for each space.</p>
                                </div>

                                <button type="submit" 
                                        formaction="start_new_card.php" formmethod="POST"
                                        class="w-full btn-touch bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs sm:text-sm py-3 rounded-xl shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
                                    <i class="fa-solid fa-plus-circle text-sm"></i>
                                    <span>Open Card & Continue Deposit</span>
                                </button>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Collector Action Box: Alert Admin via In-App Notification or 1-Tap WhatsApp -->
                        <?php
                            $stmtAdmin = $pdo->query("SELECT phone, full_name FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
                            $adminUser = $stmtAdmin ? $stmtAdmin->fetch() : null;
                            $adminPhone = $adminUser ? $adminUser['phone'] : '0553224837';
                            $cleanPhone = preg_replace('/^0/', '233', preg_replace('/\D/', '', $adminPhone));
                            $waText = urlencode("Hello " . ($adminUser['full_name'] ?? 'Admin') . ", customer {$activeCustomer['full_name']} (#{$activeCustomer['account_number']}) needs a new Susu Card opened so I can record their deposit.");
                            $waUrl = "https://wa.me/{$cleanPhone}?text={$waText}";
                        ?>

                        <div class="bg-white p-4 sm:p-5 rounded-xl border border-amber-200 shadow-xs space-y-3">
                            <p class="text-xs font-semibold text-slate-700">
                                As a field collector, you cannot issue cards directly. Alert the office administrator to open a new card:
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1">
                                <!-- In-App Alert Button (Fitts's Law: Large Thumb Target) -->
                                <button type="button" id="alert_admin_btn" onclick="sendAdminCardAlert(<?= $activeCustomer['id'] ?>)"
                                        class="btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white font-bold text-xs py-3 px-3 rounded-xl shadow-xs transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-bell text-xs"></i>
                                    <span id="alert_admin_btn_text">Alert Admin to Open Card</span>
                                </button>

                                <!-- 1-Tap WhatsApp to Admin (Familiar Pattern / Jakob's Law) -->
                                <a href="<?= $waUrl ?>" target="_blank" rel="noopener noreferrer"
                                   class="btn-touch bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-3 px-3 rounded-xl shadow-xs transition flex items-center justify-center gap-2">
                                    <i class="fa-brands fa-whatsapp text-sm"></i>
                                    <span>WhatsApp Admin (<?= htmlspecialchars($adminPhone) ?>)</span>
                                </a>
                            </div>

                            <div id="alert_feedback_msg" class="hidden text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 p-2.5 rounded-xl flex items-center gap-2">
                                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                                <span>Notification sent! The office administrator has been alerted to open this card.</span>
                            </div>
                        </div>

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

                <?php if (!empty($cardTodayDeposits)): ?>
                    <!-- Today's Stamped Spaces on this Card (HCI: Fitts's Law Undo Action) -->
                    <div class="bg-amber-50/70 border border-amber-200/90 rounded-2xl p-3.5 sm:p-4 space-y-2.5 shadow-2xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black uppercase tracking-wider text-amber-900 flex items-center gap-1.5">
                                <i class="fa-solid fa-clock-rotate-left text-xs text-amber-600"></i>
                                Today's Stamped Spaces (<?= count($cardTodayDeposits) ?>)
                            </span>
                            <span class="text-[11px] text-amber-700 font-semibold">Unsettled Cash</span>
                        </div>

                        <div class="space-y-2">
                            <?php foreach ($cardTodayDeposits as $depItem): ?>
                                <div class="bg-white p-3 rounded-xl border border-amber-200/80 flex items-center justify-between gap-3 shadow-2xs">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-7 h-7 rounded-xl bg-emerald-100 text-emerald-800 font-black text-xs flex items-center justify-center font-heading">
                                            #<?= $depItem['space_number'] ?>
                                        </span>
                                        <div>
                                            <div class="font-extrabold text-xs text-slate-800">
                                                <?= format_money($depItem['amount']) ?>
                                            </div>
                                            <div class="text-[10px] text-slate-400">
                                                <?= date('h:i A', strtotime($depItem['created_at'])) ?> &bull; <?= htmlspecialchars($depItem['collector_name']) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($user['role'] === 'admin' || (int)$depItem['collector_id'] === (int)$user['id']): ?>
                                        <button type="button" 
                                                onclick="openCancelDepositModal(<?= $depItem['id'] ?>, '<?= htmlspecialchars(addslashes($activeCustomer['full_name'])) ?>', '<?= format_money($depItem['amount']) ?>', <?= $depItem['space_number'] ?>)"
                                                class="btn-touch px-3 py-1.5 bg-red-50 hover:bg-red-600 hover:text-white text-red-700 border border-red-200 rounded-xl text-xs font-bold transition inline-flex items-center gap-1 cursor-pointer">
                                            <i class="fa-solid fa-rotate-left text-[11px]"></i>
                                            <span>Cancel / Undo</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

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

<script>
function toggleClientPicker() {
    const container = document.getElementById('client_picker_container');
    const searchInput = document.getElementById('client_picker_search');
    if (!container) return;

    if (container.classList.contains('hidden')) {
        container.classList.remove('hidden');
        if (searchInput) {
            searchInput.focus();
        }
    } else {
        container.classList.add('hidden');
    }
}

function selectClient(customerId) {
    if (!customerId) return;
    window.location.href = 'record_deposit.php?customer_id=' + customerId;
}

function clearClientSearch() {
    const searchInput = document.getElementById('client_picker_search');
    const clearBtn = document.getElementById('client_picker_clear');
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
        filterClients('');
    }
    if (clearBtn) {
        clearBtn.classList.add('hidden');
    }
}

function filterClients(query) {
    const q = query.trim().toLowerCase();
    const items = document.querySelectorAll('.client-picker-item');
    const emptyNotice = document.getElementById('client_picker_empty');
    const clearBtn = document.getElementById('client_picker_clear');
    let visibleCount = 0;

    if (clearBtn) {
        if (q.length > 0) {
            clearBtn.classList.remove('hidden');
        } else {
            clearBtn.classList.add('hidden');
        }
    }

    items.forEach(item => {
        const searchText = item.getAttribute('data-search') || '';
        if (!q || searchText.includes(q)) {
            item.classList.remove('hidden');
            visibleCount++;
        } else {
            item.classList.add('hidden');
        }
    });

    if (emptyNotice) {
        if (visibleCount === 0) {
            emptyNotice.classList.remove('hidden');
        } else {
            emptyNotice.classList.add('hidden');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('client_picker_search');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            filterClients(e.target.value);
        });
    }
});

function sendAdminCardAlert(customerId) {
    const btn = document.getElementById('alert_admin_btn');
    const btnText = document.getElementById('alert_admin_btn_text');
    const feedback = document.getElementById('alert_feedback_msg');

    if (!btn || !customerId) return;

    btn.disabled = true;
    if (btnText) btnText.textContent = 'Sending alert...';

    const formData = new FormData();
    formData.append('action', 'alert_admin_card');
    formData.append('customer_id', customerId);

    fetch('api_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            btn.className = 'btn-touch bg-emerald-600 text-white font-bold text-xs py-3 px-3 rounded-xl shadow-xs transition flex items-center justify-center gap-2 cursor-default';
            if (btnText) btnText.innerHTML = '<i class="fa-solid fa-check"></i> Admin Alerted';
            if (feedback) feedback.classList.remove('hidden');
        } else {
            btn.disabled = false;
            if (btnText) btnText.textContent = 'Alert Admin to Open Card';
            alert(data.error || 'Could not send alert. Please try WhatsApp.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        if (btnText) btnText.textContent = 'Alert Admin to Open Card';
        alert('Network error. Please use the WhatsApp button to alert admin.');
    });
}

function openCancelDepositModal(depositId, customerName, amount, spaceNumber) {
    document.getElementById('cancel_deposit_id').value = depositId;
    document.getElementById('cancel_customer_name').textContent = customerName;
    document.getElementById('cancel_amount_display').textContent = amount;
    document.getElementById('cancel_space_display').textContent = 'Space #' + spaceNumber;
    
    // Reset reason to default
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

// Close on Escape or outside click
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCancelDepositModal();
    }
});

document.addEventListener('click', function(e) {
    const modal = document.getElementById('cancel_deposit_modal');
    if (modal && !modal.classList.contains('hidden') && e.target === modal) {
        closeCancelDepositModal();
    }
});
</script>

<!-- Cancel Deposit Confirmation Modal (HCI: Hick's Law, Fitts's Law, Plain Language) -->
<div id="cancel_deposit_modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden transition-opacity">
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

        <form method="POST" action="record_deposit.php" class="p-5 sm:p-6 space-y-4">
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

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
