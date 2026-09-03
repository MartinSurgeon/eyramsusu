<?php
// reports.php - Daily Collections & Financial Reports
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = get_logged_in_user();
$pdo = get_db_connection();

$selectedDate = !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$collectorFilter = !empty($_GET['collector_id']) ? (int)$_GET['collector_id'] : 0;

// Fetch all active collectors for filter dropdown
$stmtCol = $pdo->query("SELECT id, full_name FROM users WHERE role = 'collector' ORDER BY full_name ASC");
$collectors = $stmtCol->fetchAll();

// Build query for deposits on selected date
$query = "
    SELECT d.*, c.full_name as customer_name, c.account_number, c.phone,
           u.full_name as collector_name, sc.card_number, sc.daily_amount
    FROM deposits d
    JOIN customers c ON d.customer_id = c.id
    JOIN users u ON d.collector_id = u.id
    JOIN susu_cards sc ON d.card_id = sc.id
    WHERE d.deposit_date = ?
";
$params = [$selectedDate];

if ($collectorFilter > 0) {
    $query .= " AND d.collector_id = ?";
    $params[] = $collectorFilter;
}

$query .= " ORDER BY d.id DESC";

$stmtDep = $pdo->prepare($query);
$stmtDep->execute($params);
$allDeposits = $stmtDep->fetchAll();

// Summary calculations for the date
$totalCollected = 0.00;
foreach ($allDeposits as $d) {
    $totalCollected += (float)$d['amount'];
}

// Pagination setup
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$pagedDeposits = paginate_array($allDeposits, $perPage, $page);
$deposits = $pagedDeposits['items'];

// Fetch payouts disbursed on this date
$stmtPay = $pdo->prepare("
    SELECT COALESCE(SUM(customer_payout), 0.00) as total_payouts,
           COALESCE(SUM(business_fee), 0.00) as total_fees
    FROM payouts 
    WHERE DATE(paid_at) = ? AND status = 'paid'
");
$stmtPay->execute([$selectedDate]);
$payoutStats = $stmtPay->fetch();

$pageTitle = "Daily Report - " . date('d M Y', strtotime($selectedDate));
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

    <!-- Top Bar & Date Filter -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 section-card no-print">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-steel_azure">Daily Collection Report</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Detailed ledger for <strong><?= date('l, d F Y', strtotime($selectedDate)) ?></strong>
            </p>
        </div>

        <form method="GET" action="reports.php" class="flex flex-wrap items-center gap-2">
            <div>
                <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>"
                       class="px-3.5 py-2 text-xs sm:text-sm rounded-xl border border-silver-600 focus:border-steel_azure outline-none transition font-semibold">
            </div>

            <?php if ($user['role'] === 'admin'): ?>
                <div>
                    <select name="collector_id" class="px-3.5 py-2 text-xs sm:text-sm rounded-xl border border-silver-600 focus:border-steel_azure outline-none transition bg-white font-semibold">
                        <option value="0">All Collectors</option>
                        <?php foreach ($collectors as $col): ?>
                            <option value="<?= $col['id'] ?>" <?= $collectorFilter === (int)$col['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($col['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white text-xs font-bold px-4 py-2 shadow-sm rounded-xl flex items-center gap-1.5">
                <i class="fa-solid fa-filter text-xs"></i>
                <span>Filter</span>
            </button>

            <button type="button" onclick="window.print()" class="btn-touch bg-white hover:bg-platinum text-slate-700 border border-silver-600 text-xs font-bold px-3 py-2 shadow-sm rounded-xl flex items-center gap-1.5">
                <i class="fa-solid fa-print text-xs"></i>
                <span>Print Sheet</span>
            </button>

            <?php if ($user['role'] === 'admin'): ?>
                <a href="export_customers.php" class="btn-touch bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 text-xs font-bold px-3 py-2 shadow-2xs rounded-xl flex items-center gap-1.5 transition" title="Export Customer List to CSV">
                    <i class="fa-solid fa-file-csv text-emerald-600 text-sm"></i>
                    <span>Export Clients CSV</span>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Print Header (Visible only when printed) -->
    <div class="hidden print-only text-center mb-6">
        <h1 class="text-2xl font-black text-slate-900">Eyram Susu Savings</h1>
        <p class="text-sm text-slate-600">Daily Collection Sheet &bull; Date: <?= date('d M Y', strtotime($selectedDate)) ?></p>
    </div>

    <!-- Financial KPIs for the selected date (Serial position: dominant metrics first & last) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        
        <div class="kpi-card">
            <div class="kpi-icon bg-emerald-50 text-emerald-600"><i class="fa-solid fa-sack-dollar"></i></div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Total Cash Collected</span>
            <div class="text-xl sm:text-2xl font-black text-emerald-600 mt-1">
                <?= format_money($totalCollected) ?>
            </div>
            <span class="text-[11px] text-slate-500 mt-1"><?= count($allDeposits) ?> individual spaces stamped</span>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon bg-orange-50 text-pumpkin_spice"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Business Fees Earned</span>
            <div class="text-xl sm:text-2xl font-black text-pumpkin_spice mt-1">
                <?= format_money($payoutStats['total_fees']) ?>
            </div>
            <span class="text-[11px] text-slate-500 mt-1">1-space fee from closed cards</span>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon bg-blue-50 text-steel_azure"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Payouts Disbursed</span>
            <div class="text-xl sm:text-2xl font-black text-steel_azure mt-1">
                <?= format_money($payoutStats['total_payouts']) ?>
            </div>
            <span class="text-[11px] text-slate-500 mt-1">Paid out to clients today</span>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon bg-slate-100 text-slate-700"><i class="fa-solid fa-scale-balanced"></i></div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Net Cash Position</span>
            <?php $netCash = $totalCollected - (float)$payoutStats['total_payouts']; ?>
            <div class="text-xl sm:text-2xl font-black <?= $netCash >= 0 ? 'text-emerald-700' : 'text-red-600' ?> mt-1">
                <?= format_money($netCash) ?>
            </div>
            <span class="text-[11px] text-slate-500 mt-1">Collections minus Payouts</span>
        </div>

    </div>

    <!-- Detailed Transactions Table -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-silver-600/70 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="section-heading-icon bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-list-check"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-800">Space Deposits Log</h2>
                    <p class="text-xs text-slate-500">Every space stamped and attributed to a collector.</p>
                </div>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-platinum text-slate-600"><?= $pagedDeposits['total'] ?> entries</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-platinum text-slate-600 font-semibold border-b border-silver-600/70">
                    <tr>
                        <th class="py-3 px-4">Time</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Account #</th>
                        <th class="py-3 px-4">Card & Space #</th>
                        <th class="py-3 px-4">Amount</th>
                        <th class="py-3 px-4">Collector</th>
                        <th class="py-3 px-4 text-right">Handover</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-silver-600/50">
                    <?php if (empty($deposits)): ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="empty-state">
                                    <div class="empty-state-icon bg-slate-100 text-slate-400">
                                        <i class="fa-solid fa-coins text-3xl"></i>
                                    </div>
                                    <div class="empty-state-title">No Collections Recorded</div>
                                    <div class="empty-state-text">No space deposits were logged for this selected date.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($deposits as $d): ?>
                            <tr class="hover:bg-platinum-800 transition">
                                <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                    <?= date('h:i A', strtotime($d['created_at'])) ?>
                                </td>
                                <td class="py-3 px-4 font-bold text-slate-800">
                                    <?= htmlspecialchars($d['customer_name']) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-500 font-mono">
                                    <?= htmlspecialchars($d['account_number']) ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-platinum text-steel_azure">
                                        Card #<?= $d['card_number'] ?> &bull; Space #<?= $d['space_number'] ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-extrabold text-emerald-600 whitespace-nowrap">
                                    <?= format_money($d['amount']) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-700 whitespace-nowrap">
                                    <?= htmlspecialchars($d['collector_name']) ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <?php if ($d['handover_id']): ?>
                                        <span class="text-[11px] font-bold text-emerald-700">Settled (#<?= $d['handover_id'] ?>)</span>
                                    <?php else: ?>
                                        <span class="text-[11px] font-bold text-pumpkin_spice">In Bag</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?= render_pagination($pagedDeposits['total'], $pagedDeposits['per_page'], $pagedDeposits['current']) ?>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
