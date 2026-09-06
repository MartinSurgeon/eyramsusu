<?php
// admin_dashboard.php - Business Management & Monitoring Center
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_admin();

$pageTitle = "Admin Dashboard";
$user = get_logged_in_user();
$pdo = get_db_connection();
$stats = get_admin_dashboard_stats();

// Fetch collectors and any staff/admin with active cash bags or collections
$stmtCollectors = $pdo->query("
    SELECT u.id, u.full_name, u.phone, u.role,
           COALESCE((SELECT SUM(amount) FROM deposits WHERE collector_id = u.id AND handover_id IS NULL), 0.00) as cash_in_hand,
           COALESCE((SELECT SUM(amount) FROM deposits WHERE collector_id = u.id AND deposit_date = CURRENT_DATE), 0.00) as today_collected
    FROM users u
    WHERE u.is_active = 1
      AND (u.role = 'collector' OR (SELECT COUNT(*) FROM deposits WHERE collector_id = u.id AND handover_id IS NULL) > 0 OR (SELECT COUNT(*) FROM deposits WHERE collector_id = u.id AND deposit_date = CURRENT_DATE) > 0)
    ORDER BY (cash_in_hand > 0) DESC, u.role ASC, u.full_name ASC
");
$collectors = $stmtCollectors->fetchAll();

// Fetch recent deposits with pagination (Miller's Law: 5 items per chunk)
$stmtRecent = $pdo->query("
    SELECT d.*, c.full_name as customer_name, c.account_number, u.full_name as collector_name, sc.card_number
    FROM deposits d
    JOIN customers c ON d.customer_id = c.id
    JOIN users u ON d.collector_id = u.id
    JOIN susu_cards sc ON d.card_id = sc.id
    ORDER BY d.id DESC
");
$allRecentDeposits = $stmtRecent->fetchAll();
$recentPage = max(1, (int)($_GET['recent_page'] ?? 1));
$pagedRecent = paginate_array($allRecentDeposits, 5, $recentPage);
$recentDeposits = $pagedRecent['items'];

$pendingTotal = $stats['pending_handovers'] + $stats['pending_payouts'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-5 sm:space-y-6" id="top">
    
    <!-- 1. Welcome Header & Top Actions (Hick's Law: One Primary, One Secondary CTA) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 section-card">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-black text-steel_azure">Admin Dashboard</h1>
                <?php if ($pendingTotal > 0): ?>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 border border-amber-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                        <span><?= $pendingTotal ?> items need review</span>
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-900 border border-emerald-300">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-xs"></i>
                        <span>All up to date</span>
                    </span>
                <?php endif; ?>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Welcome back, <strong><?= htmlspecialchars($user['full_name'] ?? 'Admin') ?></strong>. Here is a clear summary of your money, collectors, and customer savings.
            </p>
        </div>

        <!-- Thumb-Friendly Mobile Button Grid (Fitts's Law) -->
        <div class="grid grid-cols-2 gap-2.5 w-full sm:w-auto sm:flex sm:items-center">
            <!-- Secondary Action -->
            <a href="add_customer.php" class="btn-touch w-full sm:w-auto justify-center bg-white hover:bg-platinum text-steel_azure border-2 border-steel_azure font-bold text-xs sm:text-sm px-3 sm:px-4 py-2.5 rounded-xl transition inline-flex items-center gap-1.5 shadow-2xs">
                <i class="fa-solid fa-user-plus text-xs"></i>
                <span>Add Customer</span>
            </a>
            <!-- Primary Action -->
            <a href="record_deposit.php" class="btn-touch w-full sm:w-auto justify-center bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white font-black text-xs sm:text-sm px-3 sm:px-4 py-2.5 rounded-xl shadow-md transition inline-flex items-center gap-1.5">
                <i class="fa-solid fa-circle-plus text-xs"></i>
                <span>Record Deposit</span>
            </a>
        </div>
    </div>

    <!-- 2. Overall Business Totals (Seen at a Glance with Privacy Balance Masking & Quick Jump) -->
    <div class="bg-white rounded-2xl border-2 border-silver-600 shadow-sm p-3.5 sm:p-5 space-y-3.5 sm:space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-steel_azure/10 text-steel_azure flex items-center justify-center text-sm font-black shrink-0">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-sm sm:text-base font-black text-slate-800">Overall Business Totals</h2>
                        <span class="text-[10px] sm:text-xs font-extrabold px-2 py-0.5 rounded-full bg-platinum text-slate-700 border border-silver-600/80">
                            All-Time Totals
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 hidden sm:block">The total money collected, received in the office, and paid out since the start.</p>
                </div>
            </div>

            <!-- Header Action Controls: Jump to Today's Actions + Balance Privacy Mask Toggle -->
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <!-- Reveal & Jump Button for Today's Money & Actions (Fitts's & Hick's Law - Progressive Disclosure) -->
                <button type="button" id="toggleTodayActionsBtn" onclick="toggleTodayActions()" class="btn-touch flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold bg-emerald-50 text-emerald-800 hover:bg-emerald-600 hover:text-white border border-emerald-300 transition shadow-2xs min-h-[44px] cursor-pointer group" aria-expanded="false" aria-controls="today-actions">
                    <i class="fa-solid fa-calendar-day text-xs text-emerald-600 group-hover:text-white transition-colors"></i>
                    <span id="todayActionsBtnTitle">Today's Actions</span>
                    <span id="todayActionsBadge" class="text-[10px] font-black px-1.5 py-0.5 rounded-full bg-emerald-200/80 text-emerald-900 group-hover:bg-white group-hover:text-emerald-800 transition">Reveal</span>
                    <i id="todayActionsArrow" class="fa-solid fa-chevron-down text-[10px] text-emerald-700 group-hover:text-white group-hover:translate-y-0.5 transition-transform"></i>
                </button>

                <!-- Mask Feature Icon Functionality (Privacy Toggle: Hick's Law - Secondary Utility) -->
                <button type="button" id="toggleMaskBtn" onclick="toggleBalanceMasking()" class="btn-touch inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition shadow-2xs min-h-[44px] cursor-pointer" aria-label="Toggle balance privacy" title="Click to hide or show sensitive amounts">
                    <i id="maskIcon" class="fa-solid fa-eye text-sm text-steel_azure"></i>
                    <span id="maskBtnText" class="text-xs font-bold">Hide</span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
            
            <!-- Card 1: Total Cash Received in Office -->
            <div class="p-3 sm:p-4 rounded-xl bg-slate-50/80 border border-slate-200 hover:border-steel_azure/50 transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-blue-100 text-steel_azure flex items-center justify-center text-xs font-bold">
                            <i class="fa-solid fa-vault"></i>
                        </div>
                        <span class="text-[9px] sm:text-[10px] font-bold px-1.5 sm:px-2 py-0.5 rounded-full bg-blue-100 text-steel_azure border border-blue-200">
                            Office Drawer
                        </span>
                    </div>
                    <span class="text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider block mt-2">Cash Received</span>
                    <div class="mt-0.5 sm:mt-1 text-sm sm:text-lg lg:text-xl font-black text-steel_azure truncate maskable-balance" data-value="<?= htmlspecialchars(format_money($stats['overall_handovers'])) ?>" title="<?= format_money($stats['overall_handovers']) ?>">
                        <?= format_money($stats['overall_handovers']) ?>
                    </div>
                    <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 line-clamp-1">
                        <?= number_format($stats['approved_handovers_count']) ?> verified handovers
                    </p>
                </div>
                <a href="daily_handover.php" class="btn-touch mt-2.5 sm:mt-3 w-full py-1.5 px-2 rounded-lg text-[11px] sm:text-xs font-bold bg-white text-steel_azure hover:bg-steel_azure hover:text-white border border-blue-200 transition flex items-center justify-center gap-1 shadow-2xs">
                    <span>View Handovers</span>
                    <i class="fa-solid fa-arrow-right text-[9px]"></i>
                </a>
            </div>

            <!-- Card 2: Total Paid Out to Customers -->
            <div class="p-3 sm:p-4 rounded-xl bg-slate-50/80 border border-slate-200 hover:border-purple-300 transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center text-xs font-bold">
                            <i class="fa-solid fa-money-bill-transfer"></i>
                        </div>
                        <span class="text-[9px] sm:text-[10px] font-bold px-1.5 sm:px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 border border-purple-200">
                            Paid Out
                        </span>
                    </div>
                    <span class="text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider block mt-2">Paid to Clients</span>
                    <div class="mt-0.5 sm:mt-1 text-sm sm:text-lg lg:text-xl font-black text-purple-700 truncate maskable-balance" data-value="<?= htmlspecialchars(format_money($stats['overall_cashout'])) ?>" title="<?= format_money($stats['overall_cashout']) ?>">
                        <?= format_money($stats['overall_cashout']) ?>
                    </div>
                    <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 line-clamp-1">
                        across <?= number_format($stats['paid_cashouts_count']) ?> completed cards
                    </p>
                </div>
                <a href="payouts.php" class="btn-touch mt-2.5 sm:mt-3 w-full py-1.5 px-2 rounded-lg text-[11px] sm:text-xs font-bold bg-white text-purple-800 hover:bg-purple-700 hover:text-white border border-purple-200 transition flex items-center justify-center gap-1 shadow-2xs">
                    <span>View Cashouts</span>
                    <i class="fa-solid fa-arrow-right text-[9px]"></i>
                </a>
            </div>

            <!-- Card 3: Total Office Profit -->
            <div class="p-3 sm:p-4 rounded-xl bg-slate-50/80 border border-slate-200 hover:border-orange-300 transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-orange-100 text-pumpkin_spice flex items-center justify-center text-xs font-bold">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                        <span class="text-[9px] sm:text-[10px] font-bold px-1.5 sm:px-2 py-0.5 rounded-full bg-orange-100 text-pumpkin_spice border border-orange-200">
                            Profit
                        </span>
                    </div>
                    <span class="text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider block mt-2">Office Profit</span>
                    <div class="mt-0.5 sm:mt-1 text-sm sm:text-lg lg:text-xl font-black text-pumpkin_spice truncate maskable-balance" data-value="<?= htmlspecialchars(format_money($stats['overall_system_charges'])) ?>" title="<?= format_money($stats['overall_system_charges']) ?>">
                        <?= format_money($stats['overall_system_charges']) ?>
                    </div>
                    <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 line-clamp-1">
                        from card management fees
                    </p>
                </div>
                <a href="reports.php" class="btn-touch mt-2.5 sm:mt-3 w-full py-1.5 px-2 rounded-lg text-[11px] sm:text-xs font-bold bg-white text-pumpkin_spice hover:bg-pumpkin_spice hover:text-white border border-orange-200 transition flex items-center justify-center gap-1 shadow-2xs">
                    <span>View Profit</span>
                    <i class="fa-solid fa-arrow-right text-[9px]"></i>
                </a>
            </div>

            <!-- Card 4: Money Left with Office -->
            <div class="p-3 sm:p-4 rounded-xl bg-slate-50/80 border border-slate-200 hover:border-emerald-300 transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold">
                            <i class="fa-solid fa-scale-balanced"></i>
                        </div>
                        <span class="text-[9px] sm:text-[10px] font-bold px-1.5 sm:px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                            Net Left
                        </span>
                    </div>
                    <span class="text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider block mt-2">Money Left</span>
                    <div class="mt-0.5 sm:mt-1 text-sm sm:text-lg lg:text-xl font-black text-emerald-600 truncate maskable-balance" data-value="<?= htmlspecialchars(format_money($stats['overall_net_balance'])) ?>" title="<?= format_money($stats['overall_net_balance']) ?>">
                        <?= format_money($stats['overall_net_balance']) ?>
                    </div>
                    <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 line-clamp-1" title="Total collected: <?= format_money($stats['overall_gross_collections']) ?>">
                        minus money paid out
                    </p>
                </div>
                <a href="reports.php" class="btn-touch mt-2.5 sm:mt-3 w-full py-1.5 px-2 rounded-lg text-[11px] sm:text-xs font-bold bg-white text-emerald-700 hover:bg-emerald-600 hover:text-white border border-emerald-200 transition flex items-center justify-center gap-1 shadow-2xs">
                    <span>Full Ledger</span>
                    <i class="fa-solid fa-arrow-right text-[9px]"></i>
                </a>
            </div>

        </div>
    </div>

    <!-- 3. Today's Money & Actions (Progressive Disclosure: Revealed upon tabbing / clicking) -->
    <div id="today-actions" class="space-y-3 scroll-mt-20 hidden transition-all duration-300 ease-out transform opacity-0 translate-y-2">
        <div class="flex items-center justify-between gap-2 px-1">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center text-xs font-bold">
                    <i class="fa-solid fa-calendar-day"></i>
                </div>
                <div>
                    <h2 class="text-sm sm:text-base font-black text-slate-800">Today's Money &amp; Actions</h2>
                    <p class="text-xs text-slate-500">What has come in today and what needs your attention right now.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="toggleTodayActions(false)" class="btn-touch px-2.5 py-1 text-xs font-bold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-lg border border-slate-300 transition inline-flex items-center gap-1 shadow-2xs cursor-pointer min-h-[36px]" title="Hide Today's Actions to reduce clutter">
                    <i class="fa-solid fa-chevron-up text-[10px]"></i>
                    <span>Hide Cards</span>
                </button>
                <a href="#top" class="text-[11px] font-bold text-slate-400 hover:text-steel_azure inline-flex items-center gap-1 transition" title="Back to Overall Totals">
                    <i class="fa-solid fa-arrow-up text-[10px]"></i>
                    <span class="hidden sm:inline">Overall Totals</span>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
            
            <!-- Card 1: Money Collected Today -->
            <div class="kpi-card p-3 sm:p-4.5 flex flex-col justify-between bg-white hover:border-emerald-300 transition-all duration-200">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="kpi-icon bg-emerald-50 text-emerald-600 mb-0">
                            <i class="fa-solid fa-sack-dollar text-xs sm:text-sm"></i>
                        </div>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 sm:px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Today</span>
                        </span>
                    </div>
                    <span class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Money Collected</span>
                    <div class="mt-1 text-base sm:text-xl lg:text-2xl font-black text-emerald-600 truncate maskable-balance" data-value="<?= htmlspecialchars(format_money($stats['today_collections'])) ?>" title="<?= format_money($stats['today_collections']) ?>">
                        <?= format_money($stats['today_collections']) ?>
                    </div>
                    <p class="text-[10px] sm:text-[11px] text-slate-500 mt-1 line-clamp-1">
                        <?= number_format($stats['today_spaces_count']) ?> spaces marked today
                    </p>
                </div>
                <a href="reports.php" class="btn-touch mt-2.5 sm:mt-3 w-full py-2 px-2.5 sm:px-3 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white border border-emerald-200 transition-all duration-150 flex items-center justify-center gap-1 shadow-2xs group">
                    <span>See Today's List</span>
                    <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-0.5 transition-transform"></i>
                </a>
            </div>

            <!-- Card 2: Cash Still with Collectors -->
            <div class="kpi-card p-3 sm:p-4.5 flex flex-col justify-between bg-white hover:border-pumpkin_spice/40 transition-all duration-200">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="kpi-icon bg-orange-50 text-pumpkin_spice mb-0">
                            <i class="fa-solid fa-hand-holding-dollar text-xs sm:text-sm"></i>
                        </div>
                        <span class="text-[10px] font-bold px-1.5 sm:px-2 py-0.5 rounded-full <?= $stats['cash_in_field'] > 0 ? 'bg-orange-100 text-pumpkin_spice border border-orange-200' : 'bg-slate-100 text-slate-600' ?>">
                            <?= $stats['cash_in_field'] > 0 ? 'In Field Bags' : 'All Brought In' ?>
                        </span>
                    </div>
                    <span class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Cash with Collectors</span>
                    <div class="mt-1 text-base sm:text-xl lg:text-2xl font-black text-pumpkin_spice truncate maskable-balance" data-value="<?= htmlspecialchars(format_money($stats['cash_in_field'])) ?>" title="<?= format_money($stats['cash_in_field']) ?>">
                        <?= format_money($stats['cash_in_field']) ?>
                    </div>
                    <p class="text-[10px] sm:text-[11px] text-slate-500 mt-1 line-clamp-1">
                        <?= $stats['cash_in_field'] > 0 ? 'Waiting to be brought in' : 'All collector bags brought in' ?>
                    </p>
                </div>
                <a href="daily_handover.php" class="btn-touch mt-2.5 sm:mt-3 w-full py-2 px-2.5 sm:px-3 rounded-xl text-xs font-bold bg-orange-50 text-pumpkin_spice hover:bg-pumpkin_spice hover:text-white border border-orange-200 transition-all duration-150 flex items-center justify-center gap-1 shadow-2xs group">
                    <span>Receive Handover</span>
                    <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-0.5 transition-transform"></i>
                </a>
            </div>

            <!-- Card 3: Customer Money Being Saved -->
            <div class="kpi-card p-3 sm:p-4.5 flex flex-col justify-between bg-white hover:border-steel_azure/40 transition-all duration-200">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="kpi-icon bg-blue-50 text-steel_azure mb-0">
                            <i class="fa-solid fa-piggy-bank text-xs sm:text-sm"></i>
                        </div>
                        <span class="text-[10px] font-bold px-1.5 sm:px-2 py-0.5 rounded-full bg-blue-100 text-steel_azure border border-blue-200">
                            <?= number_format($stats['active_cards']) ?> Cards
                        </span>
                    </div>
                    <span class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Customer Savings</span>
                    <div class="mt-1 text-base sm:text-xl lg:text-2xl font-black text-steel_azure truncate maskable-balance" data-value="<?= htmlspecialchars(format_money($stats['total_saved_active'])) ?>" title="<?= format_money($stats['total_saved_active']) ?>">
                        <?= format_money($stats['total_saved_active']) ?>
                    </div>
                    <p class="text-[10px] sm:text-[11px] text-slate-500 mt-1 line-clamp-1">
                        in <strong class="text-slate-700"><?= number_format($stats['total_customers']) ?></strong> customer accounts
                    </p>
                </div>
                <a href="customers.php" class="btn-touch mt-2.5 sm:mt-3 w-full py-2 px-2.5 sm:px-3 rounded-xl text-xs font-bold bg-blue-50 text-steel_azure hover:bg-steel_azure hover:text-white border border-blue-200 transition-all duration-150 flex items-center justify-center gap-1 shadow-2xs group">
                    <span>View Customers</span>
                    <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-0.5 transition-transform"></i>
                </a>
            </div>

            <!-- Card 4: Tasks Waiting for You -->
            <div class="kpi-card p-3 sm:p-4.5 flex flex-col justify-between bg-white hover:border-purple-300 transition-all duration-200">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="kpi-icon <?= $pendingTotal > 0 ? 'bg-amber-50 text-amber-600' : 'bg-purple-50 text-purple-700' ?> mb-0">
                            <i class="fa-solid <?= $pendingTotal > 0 ? 'fa-bell' : 'fa-clipboard-check' ?> text-xs sm:text-sm"></i>
                        </div>
                        <span class="text-[10px] font-bold px-1.5 sm:px-2 py-0.5 rounded-full <?= $pendingTotal > 0 ? 'bg-amber-100 text-amber-800 border border-amber-300' : ($stats['completed_ready_cashout'] > 0 ? 'bg-purple-100 text-purple-800 border border-purple-200' : 'bg-slate-100 text-slate-500') ?>">
                            <?= $pendingTotal > 0 ? 'Attention' : ($stats['completed_ready_cashout'] > 0 ? 'Ready' : 'All Clear') ?>
                        </span>
                    </div>
                    <span class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Tasks Waiting</span>
                    <div class="mt-1 text-base sm:text-xl lg:text-2xl font-black <?= $pendingTotal > 0 ? 'text-amber-600' : 'text-slate-800' ?> truncate">
                        <?= $pendingTotal > 0 ? $pendingTotal . ' Pending' : number_format($stats['completed_ready_cashout']) . ' Ready' ?>
                    </div>
                    <p class="text-[10px] sm:text-[11px] text-slate-500 mt-1 line-clamp-1">
                        <?= $stats['pending_handovers'] ?> handovers &bull; <?= $stats['completed_ready_cashout'] ?> cashouts
                    </p>
                </div>
                <a href="<?= $stats['pending_handovers'] > 0 ? 'daily_handover.php' : 'payouts.php' ?>" class="btn-touch mt-2.5 sm:mt-3 w-full py-2 px-2.5 sm:px-3 rounded-xl text-xs font-bold bg-purple-50 text-purple-800 hover:bg-purple-700 hover:text-white border border-purple-200 transition-all duration-150 flex items-center justify-center gap-1 shadow-2xs group">
                    <span><?= $stats['pending_handovers'] > 0 ? 'Review Handovers' : 'Manage Cashouts' ?></span>
                    <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-0.5 transition-transform"></i>
                </a>
            </div>

        </div>
    </div>

    <!-- 4. Staff & Collector Cash (Responsive Hybrid: Thumb Cards on Mobile, Classic Table on Desktop) -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-silver-600/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="section-heading-icon bg-blue-50 text-steel_azure">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <h2 class="text-base font-black text-slate-800">Staff & Collector Cash</h2>
                    <p class="text-xs text-slate-500">Check who is currently holding money that has not been brought to the office yet.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="collectors.php" class="btn-touch w-full sm:w-auto justify-center px-3.5 py-2 rounded-xl text-xs font-bold bg-blue-50 text-steel_azure hover:bg-steel_azure hover:text-white border border-blue-200 transition shadow-2xs inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-users-gear text-xs"></i>
                    <span>Manage Collectors</span>
                </a>
            </div>
        </div>
        
        <!-- Mobile View: Touch Cards (Eliminates Horizontal Scrolling on Mobile) -->
        <div class="block sm:hidden divide-y divide-silver-600/50">
            <?php if (empty($collectors)): ?>
                <div class="p-6 text-center text-xs text-slate-400">
                    No active collectors found. Add collectors to track their money.
                </div>
            <?php else: ?>
                <?php foreach ($collectors as $col): ?>
                    <?php $isHoldingCash = ($col['cash_in_hand'] > 0); ?>
                    <div class="p-3.5 space-y-2.5 <?= $isHoldingCash ? 'bg-amber-50/40' : 'bg-white' ?>">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-bold text-sm text-slate-800 flex items-center gap-1.5 flex-wrap">
                                    <span><?= htmlspecialchars($col['full_name']) ?></span>
                                    <?php if ($col['role'] === 'admin'): ?>
                                        <span class="text-[10px] font-extrabold px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 border border-amber-300">Office / Admin</span>
                                    <?php else: ?>
                                        <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 border border-slate-200">Collector</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5 flex items-center gap-1">
                                    <i class="fa-solid fa-phone text-[10px] text-slate-400"></i>
                                    <span><?= htmlspecialchars($col['phone'] ?: 'No phone') ?></span>
                                </div>
                            </div>
                            <?php if ($isHoldingCash): ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-orange-100 text-pumpkin_spice border border-orange-200 shrink-0">
                                    <span class="w-1.5 h-1.5 rounded-full bg-pumpkin_spice animate-pulse"></span>
                                    <span>Needs Handover</span>
                                </span>
                            <?php else: ?>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 shrink-0">
                                    All Clear
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Mobile Stats Pill Box -->
                        <div class="grid grid-cols-2 gap-2 bg-platinum-800/80 p-2.5 rounded-xl border border-silver-600/60 text-xs">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Collected Today</span>
                                <span class="font-bold text-slate-700 mt-0.5 block"><?= format_money($col['today_collected']) ?></span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Cash in Hand</span>
                                <span class="font-black <?= $isHoldingCash ? 'text-pumpkin_spice' : 'text-slate-400' ?> mt-0.5 block"><?= format_money($col['cash_in_hand']) ?></span>
                            </div>
                        </div>

                        <!-- Mobile Full-Width Action Button (Fitts's Law >= 44px) -->
                        <a href="daily_handover.php?collector_id=<?= $col['id'] ?>" class="btn-touch w-full py-2.5 rounded-xl text-xs font-bold <?= $isHoldingCash ? 'text-white bg-pumpkin_spice hover:bg-pumpkin_spice-400 shadow-2xs' : 'text-steel_azure bg-blue-50 border border-blue-200' ?> transition flex items-center justify-center gap-1.5 min-h-[44px]">
                            <i class="fa-solid fa-scale-balanced text-xs"></i>
                            <span><?= $col['role'] === 'admin' ? 'Settle Office Cash' : 'Receive Cash' ?></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Desktop View: Classic Table (Hidden on Mobile) -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-platinum text-slate-600 font-semibold border-b border-silver-600/70">
                    <tr>
                        <th class="py-3 px-4">Name & Role</th>
                        <th class="py-3 px-4">Phone</th>
                        <th class="py-3 px-4">Collected Today</th>
                        <th class="py-3 px-4">Cash Still in Hand</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-silver-600/50">
                    <?php if (empty($collectors)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-8">
                                <div class="empty-state">
                                    <div class="empty-state-icon bg-slate-100 text-slate-400">
                                        <i class="fa-solid fa-users text-2xl"></i>
                                    </div>
                                    <div class="empty-state-title">No Collectors Found</div>
                                    <div class="empty-state-text">No active collectors registered yet. Add staff from the collector settings.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($collectors as $col): ?>
                            <?php $isHoldingCash = ($col['cash_in_hand'] > 0); ?>
                            <tr class="<?= $isHoldingCash ? 'bg-amber-50/40 hover:bg-amber-50/70' : 'hover:bg-platinum-800' ?> transition">
                                <td class="py-3.5 px-4 font-bold text-slate-800">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span><?= htmlspecialchars($col['full_name']) ?></span>
                                        <?php if ($col['role'] === 'admin'): ?>
                                            <span class="text-[10px] font-extrabold px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 border border-amber-300">
                                                Office / Admin
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 border border-slate-200">
                                                Collector
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600">
                                    <?= htmlspecialchars($col['phone'] ?: 'No phone') ?>
                                </td>
                                <td class="py-3.5 px-4 text-slate-700 font-semibold">
                                    <?= format_money($col['today_collected']) ?>
                                </td>
                                <td class="py-3.5 px-4 font-black <?= $isHoldingCash ? 'text-pumpkin_spice' : 'text-slate-400' ?>">
                                    <div class="flex items-center gap-1.5">
                                        <span><?= format_money($col['cash_in_hand']) ?></span>
                                        <?php if ($isHoldingCash): ?>
                                            <span class="w-2 h-2 rounded-full bg-pumpkin_spice animate-pulse" title="Needs to be handed over"></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <a href="daily_handover.php?collector_id=<?= $col['id'] ?>" class="btn-touch px-3.5 py-2 text-xs font-bold <?= $isHoldingCash ? 'text-white bg-pumpkin_spice hover:bg-pumpkin_spice-400' : 'text-steel_azure hover:text-white hover:bg-steel_azure bg-blue-50 border border-blue-200' ?> rounded-xl transition inline-flex items-center gap-1.5 shadow-2xs min-h-[38px]">
                                        <i class="fa-solid fa-scale-balanced text-[11px]"></i>
                                        <span><?= $col['role'] === 'admin' ? 'Settle Office Cash' : 'Receive Cash' ?></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. Recent Customer Deposits (Responsive Hybrid: Scannable Tiles on Mobile, Table on Desktop) -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-silver-600/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="section-heading-icon bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h2 class="text-base font-black text-slate-800">Recent Customer Payments</h2>
                    <p class="text-xs text-slate-500">Live list of customer savings deposits recorded by collectors.</p>
                </div>
            </div>
            <a href="reports.php" class="btn-touch w-full sm:w-auto justify-center px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white border border-emerald-200 transition shadow-2xs inline-flex items-center gap-1.5">
                <span>See All Records</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <!-- Mobile View: Touch Payment Tiles (Eliminates Horizontal Scrolling on Mobile) -->
        <div class="block sm:hidden divide-y divide-silver-600/50">
            <?php if (empty($recentDeposits)): ?>
                <div class="p-6 text-center text-xs text-slate-400">
                    No customer payments recorded yet.
                </div>
            <?php else: ?>
                <?php foreach ($recentDeposits as $dep): ?>
                    <div class="p-3.5 space-y-2 hover:bg-platinum-800 transition">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-bold text-xs sm:text-sm text-slate-800"><?= htmlspecialchars($dep['customer_name']) ?></div>
                                <div class="text-[11px] text-slate-400 font-mono mt-0.5"><?= htmlspecialchars($dep['account_number']) ?></div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-sm font-black text-emerald-600"><?= format_money($dep['amount']) ?></div>
                                <div class="text-[10px] text-slate-400 mt-0.5"><?= date('d M, h:i A', strtotime($dep['created_at'])) ?></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-2 pt-1 border-t border-silver-600/40">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-50 text-steel_azure border border-blue-200">
                                    Card #<?= $dep['card_number'] ?> &bull; Space #<?= $dep['space_number'] ?>
                                </span>
                                <span class="text-[10px] text-slate-500">by <?= htmlspecialchars($dep['collector_name']) ?></span>
                            </div>
                            <a href="view_card.php?id=<?= $dep['card_id'] ?>" class="btn-touch px-2.5 py-1 text-[11px] font-bold text-steel_azure hover:text-white hover:bg-steel_azure bg-blue-50 rounded-lg transition border border-blue-200 inline-flex items-center gap-1 shadow-2xs">
                                <span>Card</span>
                                <i class="fa-solid fa-arrow-right text-[9px]"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Desktop View: Classic Multi-Column Table (Hidden on Mobile) -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-platinum text-slate-600 font-semibold border-b border-silver-600/70">
                    <tr>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Card & Space</th>
                        <th class="py-3 px-4">Amount</th>
                        <th class="py-3 px-4">Collector</th>
                        <th class="py-3 px-4 text-right">Passbook</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-silver-600/50">
                    <?php if (empty($recentDeposits)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-8">
                                <div class="empty-state">
                                    <div class="empty-state-icon bg-emerald-50 text-emerald-600">
                                        <i class="fa-solid fa-coins text-2xl"></i>
                                    </div>
                                    <div class="empty-state-title">No Payments Yet</div>
                                    <div class="empty-state-text">Take the first customer payment to see it recorded here.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentDeposits as $dep): ?>
                            <tr class="hover:bg-platinum-800 transition">
                                <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                    <div class="font-bold text-slate-800"><?= date('d M Y', strtotime($dep['deposit_date'])) ?></div>
                                    <div class="text-[10px] text-slate-400 font-medium"><?= date('h:i A', strtotime($dep['created_at'])) ?></div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($dep['customer_name']) ?></div>
                                    <div class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($dep['account_number']) ?></div>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-blue-50 text-steel_azure border border-blue-200">
                                        Card #<?= $dep['card_number'] ?> &bull; Space #<?= $dep['space_number'] ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-black text-emerald-600 whitespace-nowrap">
                                    <?= format_money($dep['amount']) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                    <?= htmlspecialchars($dep['collector_name']) ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <a href="view_card.php?id=<?= $dep['card_id'] ?>" class="btn-touch px-3 py-1.5 text-xs font-bold text-steel_azure hover:text-white hover:bg-steel_azure bg-blue-50 rounded-xl transition border border-blue-200 inline-flex items-center gap-1 shadow-2xs">
                                        <span>View Card</span>
                                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?= render_pagination($pagedRecent['total'], 5, $pagedRecent['current'], 'recent_page') ?>
    </div>

    <!-- 6. Peak-End Rule: Closing Checklist & Reassurance Card (Stacked Thumb Grid on Mobile) -->
    <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-2xl p-4 sm:p-6 shadow-md">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3">
                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center text-lg sm:text-xl shrink-0">
                    <i class="fa-solid fa-shield-check"></i>
                </div>
                <div>
                    <h3 class="text-sm sm:text-base font-black text-white">Daily Office Checklist</h3>
                    <p class="text-xs text-slate-300 mt-0.5">
                        <?= $stats['cash_in_field'] > 0 
                            ? 'You have <strong class="text-amber-400 maskable-balance" data-value="' . htmlspecialchars(format_money($stats['cash_in_field'])) . '">' . format_money($stats['cash_in_field']) . '</strong> still with collectors. Receive their handovers before closing the office.' 
                            : 'All collector cash has been received. Everything is balanced for the day.' 
                        ?>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:flex sm:items-center gap-2 sm:gap-2.5 w-full md:w-auto shrink-0">
                <a href="daily_handover.php" class="btn-touch w-full sm:w-auto justify-center px-3.5 sm:px-4 py-2.5 rounded-xl text-xs font-bold bg-steel_azure hover:bg-steel_azure-400 text-white transition inline-flex items-center gap-1.5 shadow-sm min-h-[44px]">
                    <i class="fa-solid fa-scale-balanced text-xs"></i>
                    <span>Handovers</span>
                </a>
                <a href="payouts.php" class="btn-touch w-full sm:w-auto justify-center px-3.5 sm:px-4 py-2.5 rounded-xl text-xs font-bold bg-white/10 hover:bg-white/20 text-white border border-white/20 transition inline-flex items-center gap-1.5 min-h-[44px]">
                    <i class="fa-solid fa-money-bill-wave text-xs"></i>
                    <span>Cashouts</span>
                </a>
            </div>
        </div>
    </div>

</div>

<!-- Privacy Balance Masking Script (HCI / Fintech Security Standard) -->
<script>
/**
 * Eyram Susu - Privacy Balance Masking Feature
 * Allows admin to discreetly mask sensitive financial totals across the dashboard.
 * Persists user preference in localStorage for instant retrieval across logins.
 */
function toggleBalanceMasking() {
    var isCurrentlyMasked = localStorage.getItem('eyram_dashboard_masked') === 'true';
    var newMaskedState = !isCurrentlyMasked;
    try {
        localStorage.setItem('eyram_dashboard_masked', newMaskedState ? 'true' : 'false');
    } catch(e) {
        console.warn('localStorage not available', e);
    }
    applyBalanceMasking(newMaskedState);
}

function applyBalanceMasking(masked) {
    var elements = document.querySelectorAll('.maskable-balance');
    var maskIcon = document.getElementById('maskIcon');
    var maskBtnText = document.getElementById('maskBtnText');
    var toggleBtn = document.getElementById('toggleMaskBtn');

    elements.forEach(function(el) {
        var original = el.getAttribute('data-value') || el.textContent.trim();
        if (masked) {
            el.textContent = 'GH₵ ••••••';
            el.setAttribute('title', 'Amount hidden for privacy. Click eye icon to reveal.');
        } else {
            el.textContent = original;
            el.setAttribute('title', original);
        }
    });

    if (maskIcon) {
        if (masked) {
            maskIcon.className = 'fa-solid fa-eye-slash text-sm text-amber-600';
            if (maskBtnText) maskBtnText.textContent = 'Show';
            if (toggleBtn) {
                toggleBtn.setAttribute('title', 'Click to reveal sensitive amounts');
                toggleBtn.setAttribute('aria-label', 'Reveal balances');
                toggleBtn.classList.add('bg-amber-50', 'border-amber-300', 'text-amber-800');
                toggleBtn.classList.remove('bg-slate-100', 'border-slate-300', 'text-slate-700');
            }
        } else {
            maskIcon.className = 'fa-solid fa-eye text-sm text-steel_azure';
            if (maskBtnText) maskBtnText.textContent = 'Hide';
            if (toggleBtn) {
                toggleBtn.setAttribute('title', 'Click to hide sensitive amounts');
                toggleBtn.setAttribute('aria-label', 'Hide balances');
                toggleBtn.classList.remove('bg-amber-50', 'border-amber-300', 'text-amber-800');
                toggleBtn.classList.add('bg-slate-100', 'border-slate-300', 'text-slate-700');
            }
        }
    }
}

/**
 * Eyram Susu - Progressive Disclosure for Today's Money & Actions
 * Keeps daily cards collapsed to avoid visual overload. Tabbing or tapping
 * "Today's Actions" smoothly reveals the cards and glides down to them.
 */
function toggleTodayActions(forceState) {
    var container = document.getElementById('today-actions');
    var btn = document.getElementById('toggleTodayActionsBtn');
    var badge = document.getElementById('todayActionsBadge');
    var arrow = document.getElementById('todayActionsArrow');

    if (!container) return;

    var isHidden = container.classList.contains('hidden');
    var shouldReveal = (typeof forceState === 'boolean') ? forceState : isHidden;

    if (shouldReveal) {
        // Reveal container
        container.classList.remove('hidden');
        requestAnimationFrame(function() {
            container.classList.remove('opacity-0', 'translate-y-2');
            container.classList.add('opacity-100', 'translate-y-0');
        });

        if (btn) {
            btn.setAttribute('aria-expanded', 'true');
            btn.classList.remove('bg-emerald-50', 'text-emerald-800', 'border-emerald-300');
            btn.classList.add('bg-emerald-600', 'text-white', 'border-emerald-600');
        }
        if (badge) {
            badge.textContent = 'Hide';
            badge.className = 'text-[10px] font-black px-1.5 py-0.5 rounded-full bg-white/20 text-white';
        }
        if (arrow) {
            arrow.className = 'fa-solid fa-chevron-up text-[10px] text-white transition-transform';
        }

        // Smooth scroll down to the revealed cards
        setTimeout(function() {
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 80);
    } else {
        // Hide / Collapse container
        container.classList.remove('opacity-100', 'translate-y-0');
        container.classList.add('opacity-0', 'translate-y-2');
        setTimeout(function() {
            container.classList.add('hidden');
        }, 220);

        if (btn) {
            btn.setAttribute('aria-expanded', 'false');
            btn.classList.remove('bg-emerald-600', 'text-white', 'border-emerald-600');
            btn.classList.add('bg-emerald-50', 'text-emerald-800', 'border-emerald-300');
        }
        if (badge) {
            badge.textContent = 'Reveal';
            badge.className = 'text-[10px] font-black px-1.5 py-0.5 rounded-full bg-emerald-200/80 text-emerald-900 group-hover:bg-white group-hover:text-emerald-800 transition';
        }
        if (arrow) {
            arrow.className = 'fa-solid fa-chevron-down text-[10px] text-emerald-700 group-hover:text-white transition-transform';
        }
    }
}

// Auto-apply saved preferences & direct hash navigation on load
(function() {
    try {
        var isMasked = localStorage.getItem('eyram_dashboard_masked') === 'true';
        if (isMasked) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    applyBalanceMasking(true);
                });
            } else {
                applyBalanceMasking(true);
            }
        }
    } catch(e) {}

    // If page is loaded with #today-actions hash, automatically reveal
    if (window.location.hash === '#today-actions') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                toggleTodayActions(true);
            });
        } else {
            toggleTodayActions(true);
        }
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
