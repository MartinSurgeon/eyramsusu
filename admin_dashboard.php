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
            <a href="add_customer.php" class="btn-touch bg-white hover:bg-platinum-800 text-steel_azure border-2 border-steel_azure text-xs sm:text-sm transition">
                + Add Customer
            </a>
            <!-- Primary CTA (Hick's Law: Dominant visual weight) -->
            <a href="record_deposit.php" class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs sm:text-sm shadow-md transition">
                + Record Deposit
            </a>
        </div>
    </div>

    <!-- Key Metrics Cards (Serial Position Effect: Action items first & last) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        
        <!-- 1st: Pending Payouts (Requires Attention - Serial Position: First) -->
        <div class="kpi-card">
            <div class="kpi-icon bg-orange-50 text-pumpkin_spice">⏳</div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Payouts</span>
            <div class="mt-2 text-xl sm:text-2xl font-extrabold <?= $stats['pending_payouts'] > 0 ? 'text-pumpkin_spice' : 'text-slate-700' ?>">
                <?= $stats['pending_payouts'] ?>
            </div>
            <a href="payouts.php" class="text-[11px] text-cornflower_ocean font-semibold hover:underline mt-1">Review payout requests &rarr;</a>
        </div>

        <!-- 2nd: Pending Handovers (Requires Attention) -->
        <div class="kpi-card">
            <div class="kpi-icon bg-amber-50 text-amber-600">📦</div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Handovers Awaiting</span>
            <div class="mt-2 text-xl sm:text-2xl font-extrabold <?= $stats['pending_handovers'] > 0 ? 'text-pumpkin_spice' : 'text-slate-700' ?>">
                <?= $stats['pending_handovers'] ?>
            </div>
            <a href="daily_handover.php" class="text-[11px] text-cornflower_ocean font-semibold hover:underline mt-1">Confirm cash settlement &rarr;</a>
        </div>

        <!-- 3rd: Today's Collections -->
        <div class="kpi-card">
            <div class="kpi-icon bg-emerald-50 text-emerald-600">💰</div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Collected Today</span>
            <div class="mt-2 text-xl sm:text-2xl font-extrabold text-emerald-600">
                <?= format_money($stats['today_collections']) ?>
            </div>
            <span class="text-[11px] text-slate-500 mt-1">Across all field collectors</span>
        </div>

        <!-- 4th: Active Susu Cards (Serial Position: Last — reference info) -->
        <div class="kpi-card">
            <div class="kpi-icon bg-blue-50 text-steel_azure">📋</div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Cards</span>
            <div class="mt-2 text-xl sm:text-2xl font-extrabold text-steel_azure">
                <?= $stats['active_cards'] ?>
            </div>
            <a href="customers.php" class="text-[11px] text-cornflower_ocean font-semibold hover:underline mt-1">View all customers &rarr;</a>
        </div>

    </div>

    <!-- Collectors Status & Cash Bag Table -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-silver-600/70 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="section-heading-icon bg-blue-50 text-steel_azure">👥</div>
                <div>
                    <h2 class="text-base font-bold text-slate-800">Collectors Live Cash Status</h2>
                    <p class="text-xs text-slate-500">Cash in Hand reflects collections awaiting end-of-day handover.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="collectors.php" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-50 text-steel_azure hover:bg-steel_azure hover:text-white border border-blue-200 transition shadow-xs flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span>Collectors</span>
                </a>
                <a href="daily_handover.php" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white border border-emerald-200 transition shadow-xs flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
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
                                    <div class="empty-state-icon bg-slate-100">👥</div>
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
                                    <a href="daily_handover.php?collector_id=<?= $col['id'] ?>" class="inline-flex items-center text-xs font-bold text-steel_azure hover:text-pumpkin_spice">
                                        Reconcile Cash
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
                <div class="section-heading-icon bg-emerald-50 text-emerald-600">📊</div>
                <div>
                    <h2 class="text-base font-bold text-slate-800">Recent Collections</h2>
                    <p class="text-xs text-slate-500">Live feed of recent space deposits recorded by collectors.</p>
                </div>
            </div>
            <a href="reports.php" class="text-xs font-bold text-cornflower_ocean hover:text-steel_azure">
                View Full Reports &rarr;
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
                                    <div class="empty-state-icon bg-emerald-50">💰</div>
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
                                    <a href="view_card.php?id=<?= $dep['card_id'] ?>" class="text-xs font-bold text-steel_azure hover:text-pumpkin_spice">
                                        View 31-Space Card &rarr;
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
