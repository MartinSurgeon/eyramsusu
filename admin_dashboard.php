<?php
// admin_dashboard.php - Business Management & Monitoring Center
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_admin();

$pageTitle = "Admin Dashboard";
$pdo = get_db_connection();
$stats = get_admin_dashboard_stats();

// Fetch collectors and their live cash bag
$stmtCollectors = $pdo->query("
    SELECT u.id, u.full_name, u.phone,
           COALESCE(SUM(CASE WHEN d.handover_id IS NULL THEN d.amount ELSE 0 END), 0.00) as cash_in_hand,
           COALESCE(SUM(CASE WHEN d.deposit_date = CURRENT_DATE THEN d.amount ELSE 0 END), 0.00) as today_collected
    FROM users u
    LEFT JOIN deposits d ON u.id = d.collector_id
    WHERE u.role = 'collector' AND u.is_active = 1
    GROUP BY u.id, u.full_name, u.phone
");
$collectors = $stmtCollectors->fetchAll();

// Fetch recent 7 deposits (Miller's law chunking)
$stmtRecent = $pdo->query("
    SELECT d.*, c.full_name as customer_name, c.account_number, u.full_name as collector_name, sc.card_number
    FROM deposits d
    JOIN customers c ON d.customer_id = c.id
    JOIN users u ON d.collector_id = u.id
    JOIN susu_cards sc ON d.card_id = sc.id
    ORDER BY d.id DESC LIMIT 7
");
$recentDeposits = $stmtRecent->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    
    <!-- Welcome Header & Top Action (Hick's Law: One Primary CTA) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 section-card">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-steel_azure">Admin Control Center</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Overview of daily susu collections, cash handovers, and active cards.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <!-- Secondary CTA (Hick's Law: Lower visual weight) -->
            <a href="add_customer.php" class="btn-touch bg-white hover:bg-platinum-800 text-steel_azure border-2 border-steel_azure text-xs sm:text-sm transition flex items-center gap-1.5">
                <i class="fa-solid fa-user-plus text-xs"></i>
                <span>Add Customer</span>
            </a>
            <!-- Primary CTA (Hick's Law: Dominant visual weight) -->
            <a href="record_deposit.php" class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs sm:text-sm shadow-md transition flex items-center gap-1.5">
                <i class="fa-solid fa-circle-plus text-xs"></i>
                <span>Record Deposit</span>
            </a>
        </div>
    </div>

    <!-- Key Metrics Cards (Serial Position Effect: Action items first & last) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        
        <!-- 1st: Pending Payouts (Requires Attention - Serial Position: First) -->
        <div class="kpi-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <div class="kpi-icon bg-orange-50 text-pumpkin_spice">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $stats['pending_payouts'] > 0 ? 'bg-orange-100 text-pumpkin_spice' : 'bg-slate-100 text-slate-500' ?>">
                        <?= $stats['pending_payouts'] > 0 ? 'Action Needed' : 'All Clear' ?>
                    </span>
                </div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Pending Payouts</span>
                <div class="mt-1 text-xl sm:text-2xl font-extrabold <?= $stats['pending_payouts'] > 0 ? 'text-pumpkin_spice' : 'text-slate-700' ?>">
                    <?= $stats['pending_payouts'] ?>
                </div>
            </div>
            <a href="payouts.php" class="btn-touch mt-3 w-full py-2 px-3 rounded-xl text-xs font-bold bg-orange-50 text-pumpkin_spice hover:bg-pumpkin_spice hover:text-white border border-orange-200 transition shadow-xs flex items-center justify-center gap-1.5">
                <span>Review Payouts</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <!-- 2nd: Pending Handovers (Requires Attention) -->
        <div class="kpi-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <div class="kpi-icon bg-amber-50 text-amber-600">
                        <i class="fa-solid fa-box-archive"></i>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $stats['pending_handovers'] > 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500' ?>">
                        <?= $stats['pending_handovers'] > 0 ? 'Awaiting Cash' : 'Settled' ?>
                    </span>
                </div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Handovers Awaiting</span>
                <div class="mt-1 text-xl sm:text-2xl font-extrabold <?= $stats['pending_handovers'] > 0 ? 'text-pumpkin_spice' : 'text-slate-700' ?>">
                    <?= $stats['pending_handovers'] ?>
                </div>
            </div>
            <a href="daily_handover.php" class="btn-touch mt-3 w-full py-2 px-3 rounded-xl text-xs font-bold bg-amber-50 text-amber-700 hover:bg-amber-600 hover:text-white border border-amber-200 transition shadow-xs flex items-center justify-center gap-1.5">
                <span>Confirm Handovers</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <!-- 3rd: Today's Collections -->
        <div class="kpi-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <div class="kpi-icon bg-emerald-50 text-emerald-600">
                        <i class="fa-solid fa-sack-dollar"></i>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Today</span>
                </div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Collected Today</span>
                <div class="mt-1 text-xl sm:text-2xl font-extrabold text-emerald-600">
                    <?= format_money($stats['today_collections']) ?>
                </div>
            </div>
            <a href="reports.php" class="btn-touch mt-3 w-full py-2 px-3 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white border border-emerald-200 transition shadow-xs flex items-center justify-center gap-1.5">
                <span>Daily Records</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <!-- 4th: Active Susu Cards (Serial Position: Last — reference info) -->
        <div class="kpi-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <div class="kpi-icon bg-blue-50 text-steel_azure">
                        <i class="fa-solid fa-id-card"></i>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-steel_azure">Passbooks</span>
                </div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Active Cards</span>
                <div class="mt-1 text-xl sm:text-2xl font-extrabold text-steel_azure">
                    <?= $stats['active_cards'] ?>
                </div>
            </div>
            <a href="customers.php" class="btn-touch mt-3 w-full py-2 px-3 rounded-xl text-xs font-bold bg-blue-50 text-steel_azure hover:bg-steel_azure hover:text-white border border-blue-200 transition shadow-xs flex items-center justify-center gap-1.5">
                <span>View Customers</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

    </div>

    <!-- Collectors Status & Cash Bag Table -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-silver-600/70 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="section-heading-icon bg-blue-50 text-steel_azure">
                    <i class="fa-solid fa-user-group"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-800">Collectors Live Cash Status</h2>
                    <p class="text-xs text-slate-500">Cash in Hand reflects collections awaiting end-of-day handover.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="collectors.php" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-50 text-steel_azure hover:bg-steel_azure hover:text-white border border-blue-200 transition shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-users-gear text-xs"></i>
                    <span>Collectors</span>
                </a>
                <a href="daily_handover.php" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white border border-emerald-200 transition shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-handshake text-xs"></i>
                    <span>Handovers</span>
                </a>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-platinum text-slate-600 font-semibold border-b border-silver-600/70">
                    <tr>
                        <th class="py-3 px-4">Collector Name</th>
                        <th class="py-3 px-4">Phone</th>
                        <th class="py-3 px-4">Collected Today</th>
                        <th class="py-3 px-4">Unsettled Cash in Hand</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-silver-600/50">
                    <?php if (empty($collectors)): ?>
                        <tr>
                            <td colspan="5" class="text-center">
                                <div class="empty-state">
                                    <div class="empty-state-icon bg-slate-100 text-slate-400">
                                        <i class="fa-solid fa-users text-2xl"></i>
                                    </div>
                                    <div class="empty-state-title">No Active Collectors</div>
                                    <div class="empty-state-text">No active collectors registered yet. Add collectors from the system settings.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($collectors as $col): ?>
                            <tr class="hover:bg-platinum-800 transition">
                                <td class="py-3 px-4 font-bold text-slate-800">
                                    <?= htmlspecialchars($col['full_name']) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    <?= htmlspecialchars($col['phone'] ?: 'N/A') ?>
                                </td>
                                <td class="py-3 px-4 text-slate-700 font-semibold">
                                    <?= format_money($col['today_collected']) ?>
                                </td>
                                <td class="py-3 px-4 font-bold <?= $col['cash_in_hand'] > 0 ? 'text-pumpkin_spice' : 'text-slate-500' ?>">
                                    <?= format_money($col['cash_in_hand']) ?>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <a href="daily_handover.php?collector_id=<?= $col['id'] ?>" class="btn-touch px-2.5 py-1 text-xs font-bold text-steel_azure hover:text-white hover:bg-steel_azure bg-blue-50 rounded-lg transition border border-blue-200 inline-flex items-center gap-1">
                                        <i class="fa-solid fa-scale-balanced text-[10px]"></i>
                                        <span>Reconcile Cash</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Collections Feed (Miller's Law Chunking) -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-silver-600/70 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="section-heading-icon bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-800">Recent Collections</h2>
                    <p class="text-xs text-slate-500">Live feed of recent space deposits recorded by collectors.</p>
                </div>
            </div>
            <a href="reports.php" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white border border-emerald-200 transition shadow-xs flex items-center gap-1.5">
                <span>Full Reports</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-platinum text-slate-600 font-semibold border-b border-silver-600/70">
                    <tr>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Card / Space</th>
                        <th class="py-3 px-4">Amount</th>
                        <th class="py-3 px-4">Collector</th>
                        <th class="py-3 px-4 text-right">Passbook</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-silver-600/50">
                    <?php if (empty($recentDeposits)): ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                <div class="empty-state">
                                    <div class="empty-state-icon bg-emerald-50 text-emerald-600">
                                        <i class="fa-solid fa-coins text-2xl"></i>
                                    </div>
                                    <div class="empty-state-title">No Deposits Yet</div>
                                    <div class="empty-state-text">Take the first deposit to see collections here.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentDeposits as $dep): ?>
                            <tr class="hover:bg-platinum-800 transition">
                                <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                    <?= date('d M Y', strtotime($dep['deposit_date'])) ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($dep['customer_name']) ?></div>
                                    <div class="text-[11px] text-slate-400"><?= htmlspecialchars($dep['account_number']) ?></div>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-platinum text-steel_azure">
                                        Card #<?= $dep['card_number'] ?> &bull; Space #<?= $dep['space_number'] ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-extrabold text-emerald-600 whitespace-nowrap">
                                    <?= format_money($dep['amount']) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                    <?= htmlspecialchars($dep['collector_name']) ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <a href="view_card.php?id=<?= $dep['card_id'] ?>" class="btn-touch px-2 py-1 text-xs font-bold text-steel_azure hover:text-white hover:bg-steel_azure bg-blue-50 rounded-lg transition border border-blue-200 inline-flex items-center gap-1">
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
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
