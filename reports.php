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
        
        <!-- KPI 1: Money In -->
        <div class="kpi-card relative group/kpi">
            <div class="flex items-center justify-between">
                <div class="kpi-icon bg-emerald-50 text-emerald-600 mb-0"><i class="fa-solid fa-arrow-down text-xs"></i></div>
                <div class="relative group/tip">
                    <button type="button" 
                            class="kpi-tip-btn btn-touch w-7 h-7 -mr-1 rounded-full text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 flex items-center justify-center transition cursor-pointer"
                            onclick="toggleKpiTip(this, event)"
                            title="Click or tap for explanation"
                            aria-label="Explanation of Money In"
                            aria-expanded="false"
                            aria-haspopup="dialog">
                        <i class="fa-solid fa-circle-info text-xs"></i>
                    </button>
                    <!-- Floating Tooltip Popover -->
                    <div class="kpi-tip-popover absolute left-[-10px] sm:left-0 lg:left-0 bottom-full mb-2.5 w-64 max-w-[calc(100vw-2.5rem)] p-3.5 bg-slate-900/95 backdrop-blur-md text-white rounded-xl shadow-2xl z-50 text-left border border-slate-700/80">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <div class="flex items-center gap-1.5 font-bold text-xs text-white">
                                <i class="fa-solid fa-sack-dollar text-emerald-400 text-xs"></i>
                                <span>Fresh Money Collected</span>
                            </div>
                            <button type="button" 
                                    class="sm:hidden text-slate-400 hover:text-white p-1 -mr-1 -mt-1 rounded-md transition cursor-pointer" 
                                    onclick="closeAllKpiTips(event)" 
                                    aria-label="Close tooltip">
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-300 leading-normal">
                            All physical cash paid by customers on this date across every space stamped. This cash goes directly into the cash drawer.
                        </p>
                        <div class="mt-2 pt-1.5 border-t border-slate-700/80 text-[10px] text-emerald-300 font-mono flex items-center gap-1">
                            <i class="fa-solid fa-calculator text-[9px]"></i>
                            <span>Sum of all space deposits today</span>
                        </div>
                        <div class="kpi-tip-arrow absolute top-full left-5 sm:left-5 -mt-1 border-4 border-transparent border-t-slate-900/95"></div>
                    </div>
                </div>
            </div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Money In (Collected)</span>
            <div class="text-xl sm:text-2xl font-black text-emerald-600 mt-1">
                <?= format_money($totalCollected) ?>
            </div>
            <span class="text-[11px] text-slate-500 mt-1"><?= count($allDeposits) ?> spaces stamped today</span>
        </div>

        <!-- KPI 2: Our Profit -->
        <div class="kpi-card relative group/kpi">
            <div class="flex items-center justify-between">
                <div class="kpi-icon bg-orange-50 text-pumpkin_spice mb-0"><i class="fa-solid fa-coins text-xs"></i></div>
                <div class="relative group/tip">
                    <button type="button" 
                            class="kpi-tip-btn btn-touch w-7 h-7 -mr-1 rounded-full text-slate-400 hover:text-pumpkin_spice hover:bg-orange-50 focus:outline-none focus:ring-2 focus:ring-pumpkin_spice/40 flex items-center justify-center transition cursor-pointer"
                            onclick="toggleKpiTip(this, event)"
                            title="Click or tap for explanation"
                            aria-label="Explanation of Our Profit"
                            aria-expanded="false"
                            aria-haspopup="dialog">
                        <i class="fa-solid fa-circle-info text-xs"></i>
                    </button>
                    <!-- Floating Tooltip Popover -->
                    <div class="kpi-tip-popover absolute right-[-10px] sm:right-0 lg:left-1/2 lg:-translate-x-1/2 bottom-full mb-2.5 w-64 max-w-[calc(100vw-2.5rem)] p-3.5 bg-slate-900/95 backdrop-blur-md text-white rounded-xl shadow-2xl z-50 text-left border border-slate-700/80">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <div class="flex items-center gap-1.5 font-bold text-xs text-white">
                                <i class="fa-solid fa-hand-holding-dollar text-amber-400 text-xs"></i>
                                <span>Company Commission</span>
                            </div>
                            <button type="button" 
                                    class="sm:hidden text-slate-400 hover:text-white p-1 -mr-1 -mt-1 rounded-md transition cursor-pointer" 
                                    onclick="closeAllKpiTips(event)" 
                                    aria-label="Close tooltip">
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-300 leading-normal">
                            The 1-day fee kept by the company for managing the Susu. It officially moves into profit when a completed card is cashed out.
                        </p>
                        <div class="mt-2 pt-1.5 border-t border-slate-700/80 text-[10px] text-amber-300 font-mono flex items-center gap-1">
                            <i class="fa-solid fa-calculator text-[9px]"></i>
                            <span>1 daily fee per completed card</span>
                        </div>
                        <div class="kpi-tip-arrow absolute top-full right-5 sm:right-5 lg:left-1/2 lg:-translate-x-1/2 -mt-1 border-4 border-transparent border-t-slate-900/95"></div>
                    </div>
                </div>
            </div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Our Profit (Commission)</span>
            <div class="text-xl sm:text-2xl font-black text-pumpkin_spice mt-1">
                <?= format_money($payoutStats['total_fees']) ?>
            </div>
            <span class="text-[11px] text-slate-500 mt-1">1-day fee kept when cards complete</span>
        </div>

        <!-- KPI 3: Money Out -->
        <div class="kpi-card relative group/kpi">
            <div class="flex items-center justify-between">
                <div class="kpi-icon bg-blue-50 text-steel_azure mb-0"><i class="fa-solid fa-arrow-up text-xs"></i></div>
                <div class="relative group/tip">
                    <button type="button" 
                            class="kpi-tip-btn btn-touch w-7 h-7 -mr-1 rounded-full text-slate-400 hover:text-steel_azure hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-steel_azure/40 flex items-center justify-center transition cursor-pointer"
                            onclick="toggleKpiTip(this, event)"
                            title="Click or tap for explanation"
                            aria-label="Explanation of Money Out"
                            aria-expanded="false"
                            aria-haspopup="dialog">
                        <i class="fa-solid fa-circle-info text-xs"></i>
                    </button>
                    <!-- Floating Tooltip Popover -->
                    <div class="kpi-tip-popover absolute left-[-10px] sm:left-0 lg:left-1/2 lg:-translate-x-1/2 bottom-full mb-2.5 w-64 max-w-[calc(100vw-2.5rem)] p-3.5 bg-slate-900/95 backdrop-blur-md text-white rounded-xl shadow-2xl z-50 text-left border border-slate-700/80">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <div class="flex items-center gap-1.5 font-bold text-xs text-white">
                                <i class="fa-solid fa-money-bill-transfer text-blue-400 text-xs"></i>
                                <span>Paid to Customers</span>
                            </div>
                            <button type="button" 
                                    class="sm:hidden text-slate-400 hover:text-white p-1 -mr-1 -mt-1 rounded-md transition cursor-pointer" 
                                    onclick="closeAllKpiTips(event)" 
                                    aria-label="Close tooltip">
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-300 leading-normal">
                            Actual cash handed back to clients who finished their 31 spaces today. This money leaves the cash drawer.
                        </p>
                        <div class="mt-2 pt-1.5 border-t border-slate-700/80 text-[10px] text-blue-300 font-mono flex items-center gap-1">
                            <i class="fa-solid fa-calculator text-[9px]"></i>
                            <span>Sum of payouts cashed out today</span>
                        </div>
                        <div class="kpi-tip-arrow absolute top-full left-5 sm:left-5 lg:left-1/2 lg:-translate-x-1/2 -mt-1 border-4 border-transparent border-t-slate-900/95"></div>
                    </div>
                </div>
            </div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Money Out (Paid to Clients)</span>
            <div class="text-xl sm:text-2xl font-black text-steel_azure mt-1">
                <?= format_money($payoutStats['total_payouts']) ?>
            </div>
            <span class="text-[11px] text-slate-500 mt-1">Cash given back to customers today</span>
        </div>

        <!-- KPI 4: Cash Left with Us Today -->
        <div class="kpi-card relative group/kpi">
            <div class="flex items-center justify-between">
                <div class="kpi-icon bg-slate-100 text-slate-700 mb-0"><i class="fa-solid fa-wallet text-xs"></i></div>
                <div class="relative group/tip">
                    <button type="button" 
                            class="kpi-tip-btn btn-touch w-7 h-7 -mr-1 rounded-full text-slate-400 hover:text-slate-800 hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-500/40 flex items-center justify-center transition cursor-pointer"
                            onclick="toggleKpiTip(this, event)"
                            title="Click or tap for explanation"
                            aria-label="Explanation of Cash Left with Us"
                            aria-expanded="false"
                            aria-haspopup="dialog">
                        <i class="fa-solid fa-circle-info text-xs"></i>
                    </button>
                    <!-- Floating Tooltip Popover -->
                    <div class="kpi-tip-popover absolute right-[-10px] sm:right-0 lg:right-0 bottom-full mb-2.5 w-64 max-w-[calc(100vw-2.5rem)] p-3.5 bg-slate-900/95 backdrop-blur-md text-white rounded-xl shadow-2xl z-50 text-left border border-slate-700/80">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <div class="flex items-center gap-1.5 font-bold text-xs text-white">
                                <i class="fa-solid fa-vault text-emerald-400 text-xs"></i>
                                <span>Today's Cash in Drawer</span>
                            </div>
                            <button type="button" 
                                    class="sm:hidden text-slate-400 hover:text-white p-1 -mr-1 -mt-1 rounded-md transition cursor-pointer" 
                                    onclick="closeAllKpiTips(event)" 
                                    aria-label="Close tooltip">
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-300 leading-normal">
                            How much physical money should remain in our cash drawer right now from today's transactions.
                        </p>
                        <div class="mt-2 pt-1.5 border-t border-slate-700/80 text-[10px] text-emerald-300 font-mono flex items-center gap-1">
                            <i class="fa-solid fa-calculator text-[9px]"></i>
                            <span>Money In minus Money Out</span>
                        </div>
                        <div class="kpi-tip-arrow absolute top-full right-5 sm:right-5 lg:right-5 -mt-1 border-4 border-transparent border-t-slate-900/95"></div>
                    </div>
                </div>
            </div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Cash Left with Us Today</span>
            <?php $netCash = $totalCollected - (float)$payoutStats['total_payouts']; ?>
            <div class="text-xl sm:text-2xl font-black <?= $netCash >= 0 ? 'text-emerald-700' : 'text-red-600' ?> mt-1">
                <?= format_money($netCash) ?>
            </div>
            <span class="text-[11px] text-slate-500 mt-1">Money In minus Money Out</span>
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

<script>
/**
 * KPI Tooltip Controller
 * Combines desktop hover/focus states with mobile tap-toggle and smart viewport boundary clamping.
 */
function toggleKpiTip(btn, event) {
    if (event) {
        event.stopPropagation();
    }
    
    const container = btn.closest('.group\\/tip');
    if (!container) return;
    const popover = container.querySelector('.kpi-tip-popover');
    if (!popover) return;
    
    const isCurrentlyOpen = popover.classList.contains('is-open');
    
    // Close any other open tooltip first
    closeAllKpiTips();
    
    if (!isCurrentlyOpen) {
        popover.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        clampPopoverToViewport(popover);
    }
}

function closeAllKpiTips(event) {
    if (event) {
        event.stopPropagation();
    }
    document.querySelectorAll('.kpi-tip-popover.is-open').forEach(pop => {
        pop.classList.remove('is-open');
        pop.style.marginLeft = '';
        pop.style.marginRight = '';
    });
    document.querySelectorAll('.kpi-tip-btn').forEach(btn => {
        btn.setAttribute('aria-expanded', 'false');
    });
}

function clampPopoverToViewport(popover) {
    if (!popover) return;
    popover.style.marginLeft = '';
    popover.style.marginRight = '';
    
    const rect = popover.getBoundingClientRect();
    const pad = 12;
    const vw = document.documentElement.clientWidth || window.innerWidth;
    
    if (rect.left < pad) {
        popover.style.marginLeft = (pad - rect.left) + 'px';
    } else if (rect.right > vw - pad) {
        popover.style.marginRight = (rect.right - (vw - pad)) + 'px';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Dismiss when tapping/clicking outside any tooltip
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.group\\/tip') && !e.target.closest('.kpi-tip-popover')) {
            closeAllKpiTips();
        }
    });

    // Dismiss when pressing Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllKpiTips();
        }
    });

    // Desktop hover boundary protection
    document.querySelectorAll('.group\\/tip').forEach(container => {
        container.addEventListener('mouseenter', () => {
            const popover = container.querySelector('.kpi-tip-popover');
            if (popover && !popover.classList.contains('is-open')) {
                clampPopoverToViewport(popover);
            }
        });
        container.addEventListener('mouseleave', () => {
            const popover = container.querySelector('.kpi-tip-popover');
            if (popover && !popover.classList.contains('is-open')) {
                popover.style.marginLeft = '';
                popover.style.marginRight = '';
            }
        });
    });

    // Re-clamp on window resize or device rotation
    window.addEventListener('resize', () => {
        const openTip = document.querySelector('.kpi-tip-popover.is-open');
        if (openTip) {
            clampPopoverToViewport(openTip);
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
