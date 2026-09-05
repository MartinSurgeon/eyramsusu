<?php
// customers.php - View & Search Customers
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = get_logged_in_user();
$pageTitle = "Customers Directory";
$pdo = get_db_connection();

// Handle Edit Customer POST request (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_customer') {
    require_admin();
    
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $accountNumber = trim($_POST['account_number'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    if (!in_array($gender, ['M', 'F'])) {
        $gender = null;
    }
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $collectorId = !empty($_POST['assigned_collector_id']) ? (int)$_POST['assigned_collector_id'] : null;
    $newDailyAmount = isset($_POST['daily_amount']) && $_POST['daily_amount'] !== '' ? (float)$_POST['daily_amount'] : null;

    $accountDigits = preg_replace('/[^0-9]/', '', $accountNumber);
    $phoneDigits = preg_replace('/[^0-9]/', '', $phone);

    if ($customerId <= 0 || empty($accountNumber) || empty($fullName)) {
        set_flash_message('error', 'Account Number and full name are required.');
    } elseif ($accountNumber !== $accountDigits) {
        set_flash_message('error', 'Account Number must contain numbers only (digits 0-9).');
    } elseif (!empty($phone) && ($phone !== $phoneDigits || strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15)) {
        set_flash_message('error', 'Phone number must contain numbers only (10 to 15 digits).');
    } else {
        // Check for duplicate account number on other customers
        $stmtCheck = $pdo->prepare("SELECT id, full_name FROM customers WHERE account_number = ? AND id != ? LIMIT 1");
        $stmtCheck->execute([$accountDigits, $customerId]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            set_flash_message('error', "Account Number '{$accountDigits}' is already in use by '{$existing['full_name']}'. Please enter a unique Account Number.");
        } else {
            try {
                $pdo->beginTransaction();

                // Update customer profile including account_number and gender
                $stmtUpd = $pdo->prepare("
                    UPDATE customers 
                    SET account_number = ?, full_name = ?, gender = ?, phone = ?, location = ?, assigned_collector_id = ?
                    WHERE id = ?
                ");
                $stmtUpd->execute([$accountDigits, $fullName, $gender, $phoneDigits, $location, $collectorId, $customerId]);

                // If new daily rate provided, update active card
                if ($newDailyAmount !== null && $newDailyAmount > 0) {
                    $stmtCardUpd = $pdo->prepare("
                        UPDATE susu_cards 
                        SET daily_amount = ? 
                        WHERE customer_id = ? AND status = 'active'
                    ");
                    $stmtCardUpd->execute([$newDailyAmount, $customerId]);
                }

                $pdo->commit();
                set_flash_message('success', 'Customer profile and Account Number updated successfully.');
                header('Location: customers.php' . (isset($_GET['page']) ? '?page=' . (int)$_GET['page'] : ''));
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                set_flash_message('error', 'Database error: ' . $e->getMessage());
            }
        }
    }
}

// Fetch collectors for assignment dropdown
$collectorsList = [];
if ($user['role'] === 'admin') {
    $stmtCol = $pdo->query("SELECT id, full_name FROM users WHERE role = 'collector' AND is_active = 1 ORDER BY full_name ASC");
    $collectorsList = $stmtCol->fetchAll();
}

// Compute overall customer metrics (for directory KPI summary cards)
if ($user['role'] === 'collector') {
    $metricsStmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT c.id) as total_customers,
            COUNT(DISTINCT CASE WHEN sc.id IS NOT NULL THEN c.id END) as active_card_customers
        FROM customers c
        LEFT JOIN susu_cards sc ON c.id = sc.customer_id AND sc.status = 'active'
        WHERE c.assigned_collector_id = ? AND c.is_active = 1
    ");
    $metricsStmt->execute([$user['id']]);
} else {
    $metricsStmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT c.id) as total_customers,
            COUNT(DISTINCT CASE WHEN sc.id IS NOT NULL THEN c.id END) as active_card_customers
        FROM customers c
        LEFT JOIN susu_cards sc ON c.id = sc.customer_id AND sc.status = 'active'
        WHERE c.is_active = 1
    ");
}
$customerMetrics = $metricsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_customers' => 0, 'active_card_customers' => 0];
$totalCustomersCount = (int)$customerMetrics['total_customers'];
$activeCardsCount = (int)$customerMetrics['active_card_customers'];
$noCardsCount = max(0, $totalCustomersCount - $activeCardsCount);
$activePercentage = $totalCustomersCount > 0 ? round(($activeCardsCount / $totalCustomersCount) * 100) : 0;

// Additional bento metrics: total saved across active cards & today's deposit count
if ($user['role'] === 'collector') {
    $bentoStmt = $pdo->prepare("
        SELECT COALESCE(SUM(sc.total_saved), 0) as total_saved_all
        FROM susu_cards sc
        JOIN customers c ON sc.customer_id = c.id
        WHERE sc.status = 'active' AND c.assigned_collector_id = ? AND c.is_active = 1
    ");
    $bentoStmt->execute([$user['id']]);
} else {
    $bentoStmt = $pdo->query("
        SELECT COALESCE(SUM(sc.total_saved), 0) as total_saved_all
        FROM susu_cards sc
        JOIN customers c ON sc.customer_id = c.id
        WHERE sc.status = 'active' AND c.is_active = 1
    ");
}
$bentoData = $bentoStmt->fetch(PDO::FETCH_ASSOC);
$totalSavedAll = (float)($bentoData['total_saved_all'] ?? 0);

// Collector distribution for contributors drawer (admin only)
$collectorDistribution = [];
if ($user['role'] === 'admin') {
    $distStmt = $pdo->query("
        SELECT u.id, u.full_name, u.phone,
               COUNT(c.id) as customer_count,
               COUNT(CASE WHEN sc.id IS NOT NULL THEN 1 END) as active_cards
        FROM users u
        LEFT JOIN customers c ON c.assigned_collector_id = u.id AND c.is_active = 1
        LEFT JOIN susu_cards sc ON sc.customer_id = c.id AND sc.status = 'active'
        WHERE u.role = 'collector' AND u.is_active = 1
        GROUP BY u.id, u.full_name, u.phone
        ORDER BY customer_count DESC
    ");
    $collectorDistribution = $distStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Status filter parameter support ('all', 'active', 'no_card')
$statusFilter = trim($_GET['status_filter'] ?? $_GET['filter'] ?? 'all');
if (!in_array($statusFilter, ['all', 'active', 'no_card'])) {
    $statusFilter = 'all';
}

// Search Query parameter support (server-side & URL fallback)
$searchQuery = trim($_GET['search'] ?? $_GET['q'] ?? '');

$whereClauses = ["c.is_active = 1"];
$params = [];

if ($user['role'] === 'collector') {
    $whereClauses[] = "c.assigned_collector_id = ?";
    $params[] = $user['id'];
}

if ($statusFilter === 'active') {
    $whereClauses[] = "(act_sc.id IS NOT NULL OR comp_sc.id IS NOT NULL)";
} elseif ($statusFilter === 'no_card') {
    $whereClauses[] = "(act_sc.id IS NULL AND comp_sc.id IS NULL)";
}

if ($searchQuery !== '') {
    $searchWildcard = '%' . $searchQuery . '%';
    if ($user['role'] === 'collector') {
        $whereClauses[] = "(c.full_name LIKE ? OR c.account_number LIKE ? OR c.phone LIKE ? OR c.location LIKE ?)";
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
    } else {
        $whereClauses[] = "(c.full_name LIKE ? OR c.account_number LIKE ? OR c.phone LIKE ? OR c.location LIKE ? OR u.full_name LIKE ?)";
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
    }
}

$whereSql = implode(" AND ", $whereClauses);

$sql = "
    SELECT c.*, u.full_name as collector_name,
           act_sc.id as active_card_id,
           act_sc.card_number as active_card_number,
           act_sc.daily_amount as active_daily_amount,
           act_sc.spaces_filled as active_spaces_filled,
           act_sc.total_spaces as active_total_spaces,
           act_sc.total_saved as active_total_saved,
           act_sc.status as active_card_status,
           comp_sc.id as completed_card_id,
           comp_sc.card_number as completed_card_number,
           comp_sc.daily_amount as completed_daily_amount,
           comp_sc.spaces_filled as completed_spaces_filled,
           comp_sc.total_spaces as completed_total_spaces,
           comp_sc.total_saved as completed_total_saved,
           comp_sc.status as completed_card_status,
           comp_p.id as completed_payout_id,
           comp_p.status as completed_payout_status,
           latest_sc.id as latest_card_id,
           latest_sc.card_number as latest_card_number,
           (SELECT COUNT(*) FROM susu_cards WHERE customer_id = c.id) as total_cards_count
    FROM customers c
    LEFT JOIN users u ON c.assigned_collector_id = u.id
    LEFT JOIN susu_cards act_sc ON act_sc.id = (
        SELECT id FROM susu_cards 
        WHERE customer_id = c.id AND status = 'active' 
        ORDER BY id DESC LIMIT 1
    )
    LEFT JOIN susu_cards comp_sc ON comp_sc.id = (
        SELECT sc2.id FROM susu_cards sc2
        WHERE sc2.customer_id = c.id 
          AND (sc2.status = 'completed' OR sc2.spaces_filled >= sc2.total_spaces)
          AND NOT EXISTS (
              SELECT 1 FROM payouts p_paid WHERE p_paid.card_id = sc2.id AND p_paid.status = 'paid'
          )
        ORDER BY sc2.id DESC LIMIT 1
    )
    LEFT JOIN payouts comp_p ON comp_p.card_id = comp_sc.id AND comp_p.status = 'pending'
    LEFT JOIN susu_cards latest_sc ON latest_sc.id = (
        SELECT id FROM susu_cards WHERE customer_id = c.id ORDER BY id DESC LIMIT 1
    )
    WHERE {$whereSql}
    ORDER BY c.full_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($allCustomers as &$c) {
    if (!empty($c['completed_card_id'])) {
        $c['card_id'] = $c['completed_card_id'];
        $c['card_number'] = $c['completed_card_number'];
        $c['daily_amount'] = (float)$c['completed_daily_amount'];
        $c['spaces_filled'] = (int)$c['completed_spaces_filled'];
        $c['total_spaces'] = (int)$c['completed_total_spaces'];
        $c['total_saved'] = (float)$c['completed_total_saved'];
        $c['card_status'] = 'completed';
        $c['is_completed_unpaid'] = true;
    } elseif (!empty($c['active_card_id'])) {
        $c['card_id'] = $c['active_card_id'];
        $c['card_number'] = $c['active_card_number'];
        $c['daily_amount'] = (float)$c['active_daily_amount'];
        $c['spaces_filled'] = (int)$c['active_spaces_filled'];
        $c['total_spaces'] = (int)$c['active_total_spaces'];
        $c['total_saved'] = (float)$c['active_total_saved'];
        $c['card_status'] = 'active';
        $c['is_completed_unpaid'] = false;
    } else {
        $c['card_id'] = null;
        $c['card_number'] = null;
        $c['daily_amount'] = 20.00;
        $c['spaces_filled'] = 0;
        $c['total_spaces'] = 31;
        $c['total_saved'] = 0.00;
        $c['card_status'] = null;
        $c['is_completed_unpaid'] = false;
    }
}
unset($c);

// Pagination setup
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$pagedData = paginate_array($allCustomers, $perPage, $page);
$customers = $pagedData['items'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    
    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 section-card">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-steel_azure">Customers Directory</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                <?= $user['role'] === 'admin' ? 'Manage all registered clients and their active Susu Cards.' : 'Clients assigned to you for daily collection.' ?>
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <?php if ($user['role'] === 'admin'): ?>
                <a href="export_customers.php" class="btn-touch px-3.5 py-2.5 bg-white hover:bg-platinum-800 text-steel_azure border border-steel_azure text-xs sm:text-sm font-bold rounded-xl shadow-2xs transition inline-flex items-center gap-1.5" title="Download Customer List as Excel-compatible CSV">
                    <i class="fa-solid fa-file-csv text-emerald-600 text-base"></i>
                    <span>Export CSV</span>
                </a>
                <a href="add_customer.php" class="btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white text-xs sm:text-sm shadow-sm transition flex items-center gap-1.5">
                    <i class="fa-solid fa-user-plus text-xs"></i>
                    <span>Register New Customer</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bento Grid: Customer Metrics KPI (Asymmetric Bento Layout) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">

        <!-- BENTO HERO: Total Registered Customers (Spans 2 cols) -->
        <a href="customers.php" 
           class="col-span-2 relative overflow-hidden p-5 sm:p-6 rounded-2xl <?= $statusFilter === 'all' && empty($searchQuery) ? 'bg-gradient-to-br from-steel_azure to-cornflower_ocean ring-2 ring-steel_azure/30 shadow-lg' : 'bg-gradient-to-br from-slate-800 to-slate-700 shadow-md hover:shadow-lg' ?> text-white group cursor-pointer transition-all duration-300">
            <!-- Decorative background pattern -->
            <div class="absolute top-0 right-0 w-40 h-40 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/2"></div>
            
            <div class="relative z-10 flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <i class="fa-solid fa-users text-lg"></i>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-white/60">Total Customers</span>
                    </div>
                    <div class="text-3xl sm:text-4xl font-black leading-none tracking-tight">
                        <?= number_format($totalCustomersCount) ?>
                    </div>
                    <div class="text-xs text-white/50 mt-1.5 font-medium">
                        <?= $activePercentage ?>% have active cards
                    </div>
                </div>
                
                <!-- Mini progress ring -->
                <div class="flex-shrink-0 relative w-16 h-16 sm:w-20 sm:h-20">
                    <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90">
                        <circle cx="18" cy="18" r="15.5" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>
                        <circle cx="18" cy="18" r="15.5" fill="none" stroke="rgba(255,255,255,0.7)" stroke-width="3" 
                                stroke-dasharray="<?= round($activePercentage * 97.4 / 100, 1) ?>, 97.4"
                                stroke-linecap="round" class="transition-all duration-700"/>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-xs sm:text-sm font-black text-white/90"><?= $activePercentage ?>%</span>
                    </div>
                </div>
            </div>
            
            <div class="relative z-10 mt-3 pt-3 border-t border-white/10 flex items-center justify-between text-[10px]">
                <span class="font-semibold px-2.5 py-1 rounded-full <?= $statusFilter === 'all' ? 'bg-white/20 text-white' : 'bg-white/10 text-white/60' ?>">
                    All (<?= $totalCustomersCount ?>)
                </span>
                <span class="text-white/40 flex items-center gap-1">
                    <i class="fa-solid fa-arrow-right text-[8px] group-hover:translate-x-1 transition-transform"></i>
                    View all
                </span>
            </div>
        </a>

        <!-- BENTO CELL: Active Card Customers -->
        <a href="customers.php?filter=active" 
           class="p-4 rounded-2xl <?= $statusFilter === 'active' ? 'bg-emerald-50 border-2 border-emerald-500 ring-2 ring-emerald-500/20 shadow-sm' : 'bg-white border border-silver-600 shadow-2xs hover:border-emerald-400 hover:shadow-sm' ?> group cursor-pointer transition-all duration-200 flex flex-col justify-between">
            <div>
                <div class="w-9 h-9 rounded-xl <?= $statusFilter === 'active' ? 'bg-emerald-500 text-white' : 'bg-emerald-50 text-emerald-600' ?> flex items-center justify-center text-sm group-hover:scale-110 transition-transform duration-300 mb-3">
                    <i class="fa-solid fa-id-card"></i>
                </div>
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active Card</div>
                <div class="text-2xl font-black <?= $statusFilter === 'active' ? 'text-emerald-700' : 'text-emerald-600' ?> leading-tight mt-0.5">
                    <?= number_format($activeCardsCount) ?>
                </div>
            </div>
            <div class="mt-3 pt-2 border-t <?= $statusFilter === 'active' ? 'border-emerald-200' : 'border-silver-600/50' ?>">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $statusFilter === 'active' ? 'bg-emerald-500 text-white' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
                    Active (<?= $activeCardsCount ?>)
                </span>
            </div>
        </a>

        <!-- BENTO CELL: Without Active Card -->
        <a href="customers.php?filter=no_card" 
           class="p-4 rounded-2xl <?= $statusFilter === 'no_card' ? 'bg-orange-50 border-2 border-pumpkin_spice ring-2 ring-pumpkin_spice/20 shadow-sm' : 'bg-white border border-silver-600 shadow-2xs hover:border-pumpkin_spice/50 hover:shadow-sm' ?> group cursor-pointer transition-all duration-200 flex flex-col justify-between">
            <div>
                <div class="w-9 h-9 rounded-xl <?= $statusFilter === 'no_card' ? 'bg-pumpkin_spice text-white' : 'bg-orange-50 text-pumpkin_spice' ?> flex items-center justify-center text-sm group-hover:scale-110 transition-transform duration-300 mb-3">
                    <i class="fa-solid fa-user-clock"></i>
                </div>
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">No Card</div>
                <div class="text-2xl font-black <?= $statusFilter === 'no_card' ? 'text-orange-700' : 'text-pumpkin_spice' ?> leading-tight mt-0.5">
                    <?= number_format($noCardsCount) ?>
                </div>
            </div>
            <div class="mt-3 pt-2 border-t <?= $statusFilter === 'no_card' ? 'border-orange-200' : 'border-silver-600/50' ?>">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $statusFilter === 'no_card' ? 'bg-pumpkin_spice text-white' : 'bg-orange-50 text-pumpkin_spice border border-orange-200' ?>">
                    Pending (<?= $noCardsCount ?>)
                </span>
            </div>
        </a>

        <!-- BENTO WIDE: Portfolio Total Saved + Contributors trigger -->
        <div class="col-span-2 md:col-span-4 p-4 rounded-2xl bg-white border border-silver-600 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-emerald-50 to-teal-50 text-emerald-600 flex items-center justify-center text-lg flex-shrink-0">
                    <i class="fa-solid fa-piggy-bank"></i>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Ongoing Customer Savings</span>
                    <div class="flex flex-wrap items-baseline gap-2 mt-0.5">
                        <div class="text-lg sm:text-xl font-black text-emerald-700 leading-tight">
                            <?= format_money($totalSavedAll) ?>
                        </div>
                        <span class="text-[11px] text-slate-500 font-medium">saved so far across <?= $activeCardsCount ?> active cards</span>
                    </div>
                </div>
            </div>
            <?php if ($user['role'] === 'admin' && !empty($collectorDistribution)): ?>
                <button type="button" onclick="toggleContributorsDrawer()" 
                        class="btn-touch px-4 py-2.5 bg-slate-50 hover:bg-steel_azure hover:text-white text-steel_azure border border-slate-200 hover:border-steel_azure text-xs font-bold rounded-xl transition-all duration-200 flex items-center gap-2 cursor-pointer group">
                    <i class="fa-solid fa-users-gear text-sm group-hover:scale-110 transition-transform"></i>
                    <span>Contributors (<?= count($collectorDistribution) ?>)</span>
                    <i class="fa-solid fa-chevron-right text-[10px] group-hover:translate-x-0.5 transition-transform"></i>
                </button>
            <?php endif; ?>
        </div>

    </div>

    <!-- Search & Filter Card -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        
        <div class="p-4 sm:p-5 border-b border-silver-600/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <form method="GET" action="customers.php" class="w-full sm:max-w-md relative" id="customer_search_form">
                <?php if ($statusFilter !== 'all'): ?>
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($statusFilter) ?>">
                <?php endif; ?>
                <input type="text" id="customer_search" name="search" value="<?= htmlspecialchars($searchQuery) ?>" 
                       autocomplete="off"
                       placeholder="Search name, account, phone, location... (Press /)"
                       class="w-full pl-9 pr-16 py-2.5 text-xs sm:text-sm rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none transition">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                
                <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5">
                    <i id="search_spinner" class="fa-solid fa-circle-notch fa-spin text-pumpkin_spice text-xs hidden"></i>
                    <button type="button" id="search_clear_btn" class="<?= empty($searchQuery) ? 'hidden' : '' ?> text-slate-400 hover:text-slate-700 text-xs p-1" title="Clear search">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <span class="hidden sm:inline-block text-[10px] font-mono text-slate-400 bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded">/</span>
                </div>
            </form>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-600 flex-wrap">
                <span>Showing:</span>
                <span class="px-2.5 py-0.5 rounded-full bg-steel_azure text-white font-extrabold text-xs" id="total_customers_count">
                    <?= $pagedData['total'] ?> clients
                </span>
            </div>
        </div>

        <?php if (!empty($searchQuery) || $statusFilter !== 'all'): ?>
            <div id="search_filter_banner" class="px-4 py-2.5 bg-blue-50/70 border-b border-blue-100 flex items-center justify-between text-xs flex-wrap gap-2">
                <span class="text-steel_azure font-semibold">
                    <?php if (!empty($searchQuery) && $statusFilter !== 'all'): ?>
                        Showing <strong class="text-slate-800"><?= $statusFilter === 'active' ? 'Clients with Active Card' : 'Clients without Active Card' ?></strong> matching <strong class="text-slate-800">"<?= htmlspecialchars($searchQuery) ?>"</strong> (<?= $pagedData['total'] ?> found)
                    <?php elseif (!empty($searchQuery)): ?>
                        Search results for <strong class="text-slate-800">"<?= htmlspecialchars($searchQuery) ?>"</strong> (<?= $pagedData['total'] ?> found)
                    <?php else: ?>
                        Filtered to: <strong class="text-slate-800"><?= $statusFilter === 'active' ? 'Clients with Active Card' : 'Clients without Active Card' ?></strong> (<?= $pagedData['total'] ?> clients)
                    <?php endif; ?>
                </span>
                <a href="customers.php" class="text-pumpkin_spice hover:underline font-bold inline-flex items-center gap-1">
                    <i class="fa-solid fa-xmark text-xs"></i>
                    <span>Reset Full Directory</span>
                </a>
            </div>
        <?php endif; ?>

        <div id="search_empty_notice" class="hidden py-10">
            <div class="empty-state">
                <div class="empty-state-icon bg-slate-100 text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-2xl"></i>
                </div>
                <div class="empty-state-title">No matching clients found</div>
                <div class="empty-state-text">Try searching with a different name, account number, or market stall location.</div>
            </div>
        </div>

        <!-- Desktop Customers Table -->
        <div class="overflow-x-auto hidden md:block">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-platinum text-slate-600 font-semibold border-b border-silver-600/70">
                    <tr>
                        <th class="py-3 px-4">Account / Name</th>
                        <th class="py-3 px-4">Phone & Location</th>
                        <th class="py-3 px-4">Collector</th>
                        <th class="py-3 px-4">Active Card</th>
                        <th class="py-3 px-4">Change Float</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-silver-600/50" id="customers_table_body">
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                <div class="empty-state">
                                    <div class="empty-state-icon bg-blue-50 text-steel_azure">
                                        <i class="fa-solid fa-users text-3xl"></i>
                                    </div>
                                    <div class="empty-state-title">No Customers Registered</div>
                                    <div class="empty-state-text">Start by registering your first customer to open a 31-space Susu Passbook.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr class="customer-row hover:bg-platinum-800 transition"
                                data-search="<?= htmlspecialchars($c['full_name'] . ' ' . $c['account_number'] . ' ' . $c['phone'] . ' ' . $c['location'] . ' ' . $c['collector_name']) ?>">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($c['full_name']) ?></span>
                                        <?php if (!empty($c['gender'])): ?>
                                            <span class="px-1.5 py-0.5 text-[9px] font-black rounded-md uppercase tracking-wider <?= $c['gender'] === 'F' ? 'bg-pink-50 text-pink-700 border border-pink-200' : 'bg-blue-50 text-blue-700 border border-blue-200' ?>" title="Gender: <?= $c['gender'] === 'F' ? 'Female' : 'Male' ?>">
                                                <?= htmlspecialchars($c['gender']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[11px] font-semibold text-slate-400 font-mono"><?= htmlspecialchars($c['account_number']) ?></div>
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    <div class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-phone text-slate-400 text-[11px]"></i>
                                        <span><?= htmlspecialchars($c['phone'] ?: '—') ?></span>
                                    </div>
                                    <div class="text-xs text-slate-400 mt-0.5 flex items-center gap-1.5">
                                        <i class="fa-solid fa-location-dot text-slate-400 text-[11px]"></i>
<td class="py-3 px-4 text-slate-700 font-medium">
                                    <?= htmlspecialchars($c['collector_name'] ?: 'Unassigned') ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($c['card_id'] && $c['card_status'] === 'completed'): ?>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                <i class="fa-solid fa-award text-[10px]"></i>
                                                <span>31/31 Completed</span>
                                            </span>
                                            <span class="text-xs font-black text-emerald-950">
                                                <?= format_money($c['total_saved']) ?>
                                            </span>
                                        </div>
                                        <div class="text-[11px] font-bold mt-0.5 <?= !empty($c['completed_payout_id']) ? 'text-purple-700' : 'text-emerald-700' ?>">
                                            <?= !empty($c['completed_payout_id']) ? '⏳ Payout Pending Approval' : '🎯 Ready for Cashout' ?>
                                        </div>
                                    <?php elseif ($c['card_id']): ?>
                                        <div class="font-bold text-steel_azure">
                                            <?= format_money($c['daily_amount']) ?> / space
                                        </div>
                                        <div class="text-[11px] text-emerald-600 font-semibold">
                                            <?= $c['spaces_filled'] ?> of <?= $c['total_spaces'] ?> spaces (<?= format_money($c['total_saved']) ?>)
                                        </div>
                                    <?php elseif ((int)($c['total_cards_count'] ?? 0) > 0): ?>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-xs text-blue-700 font-bold bg-blue-50 px-2 py-0.5 rounded-md border border-blue-200">
                                                Card Settled
                                            </span>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <button type="button"
                                                        onclick="openNewCardModal(<?= $c['id'] ?>, <?= htmlspecialchars(json_encode($c['full_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($c['account_number']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($c['collector_name'] ?: 'Unassigned'), ENT_QUOTES, 'UTF-8') ?>, 20.00)"
                                                        class="px-2 py-0.5 bg-pumpkin_spice-900 hover:bg-pumpkin_spice text-pumpkin_spice hover:text-white border border-pumpkin_spice text-[10px] font-bold rounded-md transition cursor-pointer">
                                                    + Next Card
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-amber-600 font-semibold">No card yet</span>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <button type="button"
                                                        onclick="openNewCardModal(<?= $c['id'] ?>, <?= htmlspecialchars(json_encode($c['full_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($c['account_number']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($c['collector_name'] ?: 'Unassigned'), ENT_QUOTES, 'UTF-8') ?>, 20.00)"
                                                        class="px-2 py-0.5 bg-pumpkin_spice-900 hover:bg-pumpkin_spice text-pumpkin_spice hover:text-white border border-pumpkin_spice text-[10px] font-bold rounded-md transition cursor-pointer">
                                                    + Open
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-bold <?= $c['change_balance'] > 0 ? 'text-pumpkin_spice' : 'text-slate-400' ?>">
                                        <?= format_money($c['change_balance']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <?php if ($c['card_id'] && $c['card_status'] === 'completed'): ?>
                                            <!-- Completed Card Actions (Hick's Law: Clear Primary CTA) -->
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <a href="request_payout.php?card_id=<?= $c['card_id'] ?>" class="btn-touch px-3 py-1.5 bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-black rounded-xl shadow-2xs transition inline-flex items-center gap-1.5">
                                                    <i class="fa-solid fa-hand-holding-dollar text-xs"></i>
                                                    <span>Cash Out</span>
                                                </a>
                                            <?php else: ?>
                                                <a href="request_payout.php?card_id=<?= $c['card_id'] ?>" class="btn-touch px-3 py-1.5 bg-steel_azure hover:bg-steel_azure-400 text-white text-xs font-black rounded-xl shadow-2xs transition inline-flex items-center gap-1.5">
                                                    <i class="fa-solid fa-paper-plane text-xs"></i>
                                                    <span>Request Payout</span>
                                                </a>
                                            <?php endif; ?>
                                            <a href="view_card.php?id=<?= $c['card_id'] ?>" class="btn-touch px-3 py-1.5 bg-white hover:bg-platinum text-steel_azure border border-steel_azure text-xs font-bold rounded-xl transition inline-flex items-center gap-1.5" title="View Passbook & All Spaces">
                                                <i class="fa-solid fa-id-card text-xs"></i>
                                                <span>Card</span>
                                            </a>
                                        <?php elseif ($c['card_id']): ?>
                                            <!-- Active Card Actions -->
                                            <a href="record_deposit.php?customer_id=<?= $c['id'] ?>" class="btn-touch px-3 py-1.5 bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-bold rounded-xl shadow-2xs transition inline-flex items-center gap-1.5">
                                                <i class="fa-solid fa-plus text-xs"></i>
                                                <span>Deposit</span>
                                            </a>
                                            <a href="view_card.php?id=<?= $c['card_id'] ?>" class="btn-touch px-3 py-1.5 bg-white hover:bg-platinum text-steel_azure border border-steel_azure text-xs font-bold rounded-xl transition inline-flex items-center gap-1.5">
                                                <i class="fa-solid fa-id-card text-xs"></i>
                                                <span>Card</span>
                                            </a>
                                        <?php else: ?>
                                            <!-- No Active Card -->
                                            <?php if (!empty($c['latest_card_id'])): ?>
                                                <a href="view_card.php?id=<?= $c['latest_card_id'] ?>" class="btn-touch px-3 py-1.5 bg-white hover:bg-platinum text-steel_azure border border-steel_azure text-xs font-bold rounded-xl transition inline-flex items-center gap-1.5" title="View Card History">
                                                    <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                                                    <span>History</span>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <button type="button"
                                                        onclick="openNewCardModal(<?= $c['id'] ?>, <?= htmlspecialchars(json_encode($c['full_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($c['account_number']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($c['collector_name'] ?: 'Unassigned'), ENT_QUOTES, 'UTF-8') ?>, 20.00)"
                                                        class="btn-touch px-3 py-1.5 bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-extrabold rounded-xl shadow-2xs transition inline-flex items-center gap-1.5 cursor-pointer">
                                                    <i class="fa-solid fa-circle-plus text-xs"></i>
                                                    <span>+ Open Card</span>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($user['role'] === 'admin'): ?>
                                            <button type="button" 
                                                    onclick='openEditCustomerModal(<?= htmlspecialchars(json_encode([
                                                        'id' => $c['id'],
                                                        'full_name' => $c['full_name'],
                                                        'gender' => $c['gender'] ?? '',
                                                        'account_number' => $c['account_number'],
                                                        'phone' => $c['phone'],
                                                        'location' => $c['location'],
                                                        'collector_id' => $c['assigned_collector_id'],
                                                        'collector_name' => $c['collector_name'] ?: 'Unassigned',
                                                        'card_id' => $c['card_id'],
                                                        'card_number' => $c['card_number'],
                                                        'daily_amount' => $c['daily_amount'],
                                                        'spaces_filled' => $c['spaces_filled'],
                                                        'total_spaces' => $c['total_spaces'],
                                                        'total_saved' => $c['total_saved']
                                                    ]), ENT_QUOTES, 'UTF-8') ?>)'
                                                    class="btn-touch px-2.5 py-1.5 bg-blue-50 hover:bg-steel_azure hover:text-white text-steel_azure border border-blue-200 text-xs font-bold rounded-xl transition inline-flex items-center justify-center gap-1.5"
                                                    title="Edit Customer & Plan">
                                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                                <span>Edit</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Customer Cards (Gestalt Similarity & Fitts's Law touch buttons) -->
        <div class="md:hidden divide-y divide-silver-600/50" id="customers_mobile_container">
            <?php foreach ($customers as $c): ?>
                <div class="customer-row p-4"
                     data-search="<?= htmlspecialchars($c['full_name'] . ' ' . $c['account_number'] . ' ' . $c['phone'] . ' ' . $c['location'] . ' ' . $c['collector_name']) ?>">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="font-extrabold text-sm text-slate-800"><?= htmlspecialchars($c['full_name']) ?></span>
                                <?php if (!empty($c['gender'])): ?>
                                    <span class="px-1.5 py-0.5 text-[9px] font-black rounded-md uppercase tracking-wider <?= $c['gender'] === 'F' ? 'bg-pink-50 text-pink-700 border border-pink-200' : 'bg-blue-50 text-blue-700 border border-blue-200' ?>" title="Gender: <?= $c['gender'] === 'F' ? 'Female' : 'Male' ?>">
                                        <?= htmlspecialchars($c['gender']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[11px] text-slate-500 font-mono"><?= htmlspecialchars($c['account_number']) ?> &bull; <?= htmlspecialchars($c['phone'] ?: 'No phone') ?></div>
                        </div>
                        <?php if ($c['card_id'] && $c['card_status'] === 'completed'): ?>
                            <span class="text-xs font-black text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-lg border border-emerald-300">
                                31/31 Completed
                            </span>
                        <?php elseif ($c['card_id']): ?>
                            <span class="text-xs font-bold text-steel_azure bg-platinum px-2 py-0.5 rounded border border-silver-600">
                                <?= format_money($c['daily_amount']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($c['card_id'] && $c['card_status'] === 'completed'): ?>
                        <div class="mt-2 text-xs text-slate-600 flex items-center justify-between">
                            <div>Total Saved: <strong class="text-emerald-700 font-black"><?= format_money($c['total_saved']) ?></strong></div>
                            <span class="text-[11px] font-bold text-emerald-700"><?= !empty($c['completed_payout_id']) ? '⏳ Pending' : '🎯 Ready for Cashout' ?></span>
                        </div>
                    <?php elseif ($c['card_id']): ?>
                        <div class="mt-2 text-xs text-slate-600">
                            Saved: <strong class="text-emerald-700"><?= format_money($c['total_saved']) ?></strong> (<?= $c['spaces_filled'] ?>/<?= $c['total_spaces'] ?> spaces)
                            <?php if ($c['change_balance'] > 0): ?>
                                &bull; Float: <strong class="text-pumpkin_spice"><?= format_money($c['change_balance']) ?></strong>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3 flex items-center gap-2 pt-2 border-t border-silver-600/60">
                        <?php if ($c['card_id'] && $c['card_status'] === 'completed'): ?>
                            <!-- Completed Card Mobile Actions -->
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="request_payout.php?card_id=<?= $c['card_id'] ?>" class="flex-1 btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-black py-2 rounded-xl flex items-center justify-center gap-1.5 shadow-2xs">
                                    <i class="fa-solid fa-hand-holding-dollar text-xs"></i>
                                    <span>Cash Out</span>
                                </a>
                            <?php else: ?>
                                <a href="request_payout.php?card_id=<?= $c['card_id'] ?>" class="flex-1 btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white text-xs font-black py-2 rounded-xl flex items-center justify-center gap-1.5 shadow-2xs">
                                    <i class="fa-solid fa-paper-plane text-xs"></i>
                                    <span>Request Payout</span>
                                </a>
                            <?php endif; ?>
                            <a href="view_card.php?id=<?= $c['card_id'] ?>" class="btn-touch px-3 bg-white hover:bg-platinum text-steel_azure border border-steel_azure text-xs font-bold py-2 rounded-xl flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-id-card text-xs"></i>
                                <span>Card</span>
                            </a>
                        <?php elseif ($c['card_id']): ?>
                            <!-- Active Card Mobile Actions -->
                            <a href="record_deposit.php?customer_id=<?= $c['id'] ?>" class="flex-1 btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-bold py-2 rounded-xl flex items-center justify-center gap-1.5 shadow-2xs">
                                <i class="fa-solid fa-plus text-xs"></i>
                                <span>Deposit</span>
                            </a>
                            <a href="view_card.php?id=<?= $c['card_id'] ?>" class="flex-1 btn-touch bg-white hover:bg-platinum text-steel_azure border border-steel_azure text-xs font-bold py-2 rounded-xl flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-id-card text-xs"></i>
                                <span>Card</span>
                            </a>
                        <?php else: ?>
                            <!-- No Active Card Mobile Actions -->
                            <?php if (!empty($c['latest_card_id'])): ?>
                                <a href="view_card.php?id=<?= $c['latest_card_id'] ?>" class="btn-touch px-3 bg-white hover:bg-platinum text-steel_azure border border-steel_azure text-xs font-bold py-2 rounded-xl flex items-center justify-center gap-1.5">
                                    <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                                    <span>History</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($user['role'] === 'admin'): ?>
                                <button type="button"
                                        onclick="openNewCardModal(<?= $c['id'] ?>, <?= htmlspecialchars(json_encode($c['full_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($c['account_number']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($c['collector_name'] ?: 'Unassigned'), ENT_QUOTES, 'UTF-8') ?>, <?= (float)($c['daily_amount'] > 0 ? $c['daily_amount'] : 20.00) ?>)"
                                        class="flex-1 w-full btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-extrabold py-2 rounded-xl flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer">
                                    <i class="fa-solid fa-circle-plus text-xs"></i>
                                    <span>+ Open Card</span>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($user['role'] === 'admin'): ?>
                            <button type="button" 
                                    onclick='openEditCustomerModal(<?= htmlspecialchars(json_encode([
                                        'id' => $c['id'],
                                        'full_name' => $c['full_name'],
                                        'gender' => $c['gender'] ?? '',
                                        'account_number' => $c['account_number'],
                                        'phone' => $c['phone'],
                                        'location' => $c['location'],
                                        'collector_id' => $c['assigned_collector_id'],
                                        'collector_name' => $c['collector_name'] ?: 'Unassigned',
                                        'card_id' => $c['card_id'],
                                        'card_number' => $c['card_number'],
                                        'daily_amount' => $c['daily_amount'],
                                        'spaces_filled' => $c['spaces_filled'],
                                        'total_spaces' => $c['total_spaces'],
                                        'total_saved' => $c['total_saved']
                                    ]), ENT_QUOTES, 'UTF-8') ?>)'
                                    class="px-3 py-2 bg-blue-50 hover:bg-steel_azure hover:text-white text-steel_azure border border-blue-200 text-xs font-bold rounded-xl transition inline-flex items-center justify-center gap-1.5"
                                    title="Edit Customer">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                <span>Edit</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Controls -->
        <div id="customers_pagination_container">
            <?= render_pagination($pagedData['total'], $pagedData['per_page'], $pagedData['current']) ?>
        </div>

    </div>

</div>

<script>
window.eyramConfig = {
    userRole: <?= json_encode($user['role']) ?>
};
</script>

<?php if ($user['role'] === 'admin'): ?>
<!-- Edit Customer & Daily Plan Modal -->
<div id="edit_customer_modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-opacity">
    <div class="bg-white rounded-2xl border border-silver-600 shadow-2xl max-w-lg w-full overflow-hidden transform transition-all scale-95 duration-200" id="edit_modal_box">
        
        <!-- Modal Header -->
        <div class="p-4 sm:p-5 bg-gradient-to-r from-steel_azure to-steel_azure-400 text-white flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <i class="fa-solid fa-user-pen text-base"></i>
                <h3 class="font-extrabold text-base">Edit Customer & Plan</h3>
            </div>
            <button type="button" onclick="closeEditCustomerModal()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition" title="Close">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Form -->
        <form id="edit_customer_form" method="POST" action="customers.php<?= isset($_GET['page']) ? '?page=' . (int)$_GET['page'] : '' ?>">
            <input type="hidden" name="action" value="edit_customer">
            <input type="hidden" id="edit_customer_id" name="customer_id" value="">

            <!-- Step 1: Input Fields -->
            <div id="edit_fields_step" class="p-5 sm:p-6 space-y-4 text-xs">
                
                <!-- Account Number & Active Card Info -->
                <div class="bg-platinum-800 p-3 rounded-xl border border-silver-600/70 flex items-center justify-between gap-4">
                    <div class="flex-1">
                        <label for="edit_account_number" class="block text-slate-600 font-bold uppercase text-[10px] tracking-wider mb-1">
                            Account Number * <span class="text-[10px] text-slate-400 font-normal lowercase">(numbers only)</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-hashtag text-xs"></i>
                            </span>
                            <input type="text" id="edit_account_number" name="account_number" required
                                   inputmode="numeric"
                                   pattern="[0-9]+"
                                   maxlength="20"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                   onkeypress="return event.charCode >= 48 && event.charCode <= 57"
                                   class="w-full pl-8 pr-3 py-1.5 rounded-lg border border-silver-600 focus:border-steel_azure focus:ring-1 focus:ring-steel_azure outline-none font-black text-steel_azure text-sm font-mono bg-white transition"
                                   placeholder="e.g. 0021">
                        </div>
                    </div>
                    <div id="edit_card_info_badge" class="hidden text-right flex-shrink-0">
                        <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">Active Card</span>
                        <div class="font-black text-emerald-700 text-xs" id="edit_card_display">-</div>
                    </div>
                </div>

                <!-- Full Name -->
                <div>
                    <label for="edit_full_name" class="block font-bold text-slate-700 mb-1">Full Name *</label>
                    <input type="text" id="edit_full_name" name="full_name" required
                           class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm text-slate-800 font-semibold transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <!-- Gender -->
                    <div>
                        <label for="edit_gender" class="block font-bold text-slate-700 mb-1">Gender</label>
                        <select id="edit_gender" name="gender" class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm text-slate-800 font-semibold transition bg-white">
                            <option value="">-- Select --</option>
                            <option value="F">Female (F)</option>
                            <option value="M">Male (M)</option>
                        </select>
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label for="edit_phone" class="block font-bold text-slate-700 mb-1">
                            Phone Number
                            <span class="text-[10px] text-slate-400 font-normal ml-1">(Numbers only)</span>
                        </label>
                        <input type="tel" id="edit_phone" name="phone"
                               inputmode="numeric"
                               pattern="[0-9]{10,15}"
                               maxlength="15"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                               onkeypress="return event.charCode >= 48 && event.charCode <= 57"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm text-slate-800 font-semibold font-mono transition"
                               placeholder="e.g. 0244123456">
                    </div>

                    <!-- Location -->
                    <div>
                        <label for="edit_location" class="block font-bold text-slate-700 mb-1">Location / Stall</label>
                        <input type="text" id="edit_location" name="location"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm text-slate-800 font-semibold transition">
                    </div>
                </div>

                <!-- Assigned Collector -->
                <div>
                    <label for="edit_collector_id" class="block font-bold text-slate-700 mb-1">Assigned Field Collector</label>
                    <select id="edit_collector_id" name="assigned_collector_id"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm text-slate-800 font-semibold transition bg-white">
                        <option value="">-- Unassigned (Office Direct) --</option>
                        <?php foreach ($collectorsList as $col): ?>
                            <option value="<?= $col['id'] ?>"><?= htmlspecialchars($col['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Susu Daily Rate Section -->
                <div id="edit_rate_section" class="p-3.5 bg-blue-50/60 rounded-xl border border-blue-200/80 space-y-1.5">
                    <label for="edit_daily_amount" class="block font-black text-steel_azure text-xs">
                        Agreed Daily Savings Rate (GH₵) *
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 font-black text-xs">GH₵</span>
                        <input type="number" step="1" min="1" id="edit_daily_amount" name="daily_amount"
                               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-sm font-black text-slate-800 transition bg-white">
                    </div>
                    <p class="text-[11px] text-slate-500 font-medium">New daily amount for future deposits.</p>
                </div>

                <!-- Modal Actions -->
                <div class="pt-3 border-t border-silver-600/60 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeEditCustomerModal()" class="btn-touch px-4 py-2 bg-white text-slate-600 hover:bg-platinum-800 border border-silver-600 text-xs font-bold rounded-xl transition">
                        Cancel
                    </button>
                    <button type="button" onclick="goToConfirmationStep()" class="btn-touch px-5 py-2 bg-steel_azure hover:bg-steel_azure-400 text-white text-xs font-black rounded-xl shadow-xs transition flex items-center gap-1.5">
                        <span>Continue</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Step 2: Confirmation Review -->
            <div id="edit_confirm_step" class="hidden p-5 sm:p-6 space-y-4 text-xs">
                
                <div class="p-3.5 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 text-sm flex-shrink-0"></i>
                    <span>Please confirm your changes before saving.</span>
                </div>

                <!-- Review Card -->
                <div class="bg-platinum-800 p-4 rounded-xl border border-silver-600 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Account Number:</span>
                        <span class="font-bold font-mono text-steel_azure" id="confirm_account">-</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Customer:</span>
                        <span class="font-extrabold text-slate-800" id="confirm_name">-</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Gender:</span>
                        <span class="font-bold text-slate-800" id="confirm_gender">-</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Phone:</span>
                        <span class="font-bold text-slate-800 font-mono" id="confirm_phone">-</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Location:</span>
                        <span class="font-bold text-slate-800" id="confirm_location">-</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Collector:</span>
                        <span class="font-bold text-steel_azure" id="confirm_collector">-</span>
                    </div>
                    <div class="pt-2 border-t border-silver-600/70 flex items-center justify-between">
                        <span class="text-slate-600 font-bold">Daily Rate:</span>
                        <span class="font-black text-sm text-pumpkin_spice" id="confirm_rate_change">-</span>
                    </div>
                </div>

                <!-- Confirmation Buttons -->
                <div class="pt-3 border-t border-silver-600/60 flex items-center justify-between">
                    <button type="button" onclick="backToEditFields()" class="btn-touch px-4 py-2 bg-white text-slate-600 hover:bg-platinum-800 border border-silver-600 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                        <i class="fa-solid fa-arrow-left text-xs"></i>
                        <span>Back to Edit</span>
                    </button>
                    <button type="submit" class="btn-touch px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black rounded-xl shadow-md transition flex items-center gap-1.5">
                        <i class="fa-solid fa-check text-xs"></i>
                        <span>Yes, Save Changes</span>
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
let currentOriginalRate = 0;
let originalAccountNumber = '';

function openEditCustomerModal(data) {
    document.getElementById('edit_customer_id').value = data.id;
    originalAccountNumber = data.account_number || '';
    const accInput = document.getElementById('edit_account_number');
    if (accInput) accInput.value = data.account_number || '';
    document.getElementById('edit_full_name').value = data.full_name || '';
    if (document.getElementById('edit_gender')) {
        document.getElementById('edit_gender').value = data.gender || '';
    }
    document.getElementById('edit_phone').value = data.phone || '';
    document.getElementById('edit_location').value = data.location || '';
    document.getElementById('edit_collector_id').value = data.collector_id || '';

    const cardBadge = document.getElementById('edit_card_info_badge');
    const cardDisplay = document.getElementById('edit_card_display');
    const rateSection = document.getElementById('edit_rate_section');
    const dailyInput = document.getElementById('edit_daily_amount');

    if (data.card_id) {
        currentOriginalRate = parseFloat(data.daily_amount) || 0;
        cardBadge.classList.remove('hidden');
        cardDisplay.textContent = `Card #${data.card_number} (${data.spaces_filled}/${data.total_spaces} spaces)`;
        rateSection.classList.remove('hidden');
        dailyInput.value = currentOriginalRate > 0 ? currentOriginalRate.toFixed(2) : '';
    } else {
        currentOriginalRate = 0;
        cardBadge.classList.add('hidden');
        rateSection.classList.add('hidden');
        dailyInput.value = '';
    }

    // Reset to step 1
    backToEditFields();

    // Show modal with smooth scale transition
    const modal = document.getElementById('edit_customer_modal');
    const box = document.getElementById('edit_modal_box');
    modal.classList.remove('hidden');
    setTimeout(() => {
        box.classList.remove('scale-95');
        box.classList.add('scale-100');
    }, 10);
}

function closeEditCustomerModal() {
    const modal = document.getElementById('edit_customer_modal');
    const box = document.getElementById('edit_modal_box');
    if (!modal) return;
    box.classList.remove('scale-100');
    box.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 150);
}

function goToConfirmationStep() {
    const accInput = document.getElementById('edit_account_number');
    const accountNum = accInput ? accInput.value.trim().replace(/[^0-9]/g, '') : '';
    if (accInput) accInput.value = accountNum;

    const name = document.getElementById('edit_full_name').value.trim();
    const genderSelect = document.getElementById('edit_gender');
    const gender = genderSelect ? genderSelect.value : '';
    const phoneInput = document.getElementById('edit_phone');
    const phone = phoneInput ? phoneInput.value.trim().replace(/[^0-9]/g, '') : '';
    if (phoneInput) phoneInput.value = phone;
    const location = document.getElementById('edit_location').value.trim() || 'Not specified';
    const collectorSelect = document.getElementById('edit_collector_id');
    const collectorText = collectorSelect.options[collectorSelect.selectedIndex]?.text || 'Unassigned';
    const newRate = parseFloat(document.getElementById('edit_daily_amount').value) || 0;

    if (!accountNum) {
        alert('Please enter an Account Number (numbers only).');
        return;
    }
    if (!name) {
        alert('Please enter customer full name.');
        return;
    }
    if (phone && (phone.length < 10 || phone.length > 15)) {
        alert('Phone number must contain between 10 and 15 digits (e.g. 0244123456).');
        return;
    }

    // Populate confirmation fields
    const confirmAccount = document.getElementById('confirm_account');
    if (confirmAccount) {
        if (originalAccountNumber && originalAccountNumber !== accountNum) {
            confirmAccount.innerHTML = `<span class="text-slate-400 line-through mr-1.5 font-mono">${originalAccountNumber}</span> <span class="text-pumpkin_spice font-black font-mono">#${accountNum}</span>`;
        } else {
            confirmAccount.textContent = '#' + accountNum;
        }
    }
    document.getElementById('confirm_name').textContent = name;
    const confirmGender = document.getElementById('confirm_gender');
    if (confirmGender) {
        confirmGender.textContent = gender === 'F' ? 'Female (F)' : (gender === 'M' ? 'Male (M)' : 'Not specified');
    }
    document.getElementById('confirm_phone').textContent = phone || '—';
    document.getElementById('confirm_location').textContent = location;
    document.getElementById('confirm_collector').textContent = collectorText;

    const rateConfirm = document.getElementById('confirm_rate_change');
    if (currentOriginalRate > 0 && newRate > 0) {
        if (Math.abs(currentOriginalRate - newRate) > 0.01) {
            rateConfirm.textContent = `Changing from GH₵ ${currentOriginalRate.toFixed(2)} to GH₵ ${newRate.toFixed(2)}`;
        } else {
            rateConfirm.textContent = `Remaining at GH₵ ${currentOriginalRate.toFixed(2)}`;
        }
    } else if (newRate > 0) {
        rateConfirm.textContent = `GH₵ ${newRate.toFixed(2)}`;
    } else {
        rateConfirm.textContent = 'No active card';
    }

    // Switch view
    document.getElementById('edit_fields_step').classList.add('hidden');
    document.getElementById('edit_confirm_step').classList.remove('hidden');
}

function backToEditFields() {
    document.getElementById('edit_confirm_step').classList.add('hidden');
    document.getElementById('edit_fields_step').classList.remove('hidden');
}

// Close on Escape or click outside
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditCustomerModal();
    }
});

document.addEventListener('click', function(e) {
    const modal = document.getElementById('edit_customer_modal');
    const box = document.getElementById('edit_modal_box');
    if (modal && !modal.classList.contains('hidden') && e.target === modal) {
        closeEditCustomerModal();
    }
});
</script>
<?php endif; ?>

<?php if ($user['role'] === 'admin'): ?>
<!-- Open New Susu Card Confirmation Modal -->
<div id="open_card_modal"
     class="fixed inset-0 z-50 overflow-y-auto flex items-start sm:items-center justify-center p-3 sm:p-4 pt-16 sm:pt-4 bg-slate-900/60 backdrop-blur-sm hidden"
     role="dialog" aria-modal="true" aria-labelledby="open_card_modal_title">
    <div class="bg-white rounded-2xl border border-silver-600 shadow-2xl max-w-md w-full overflow-hidden transform transition-all scale-95 duration-200 my-auto"
         id="open_card_modal_box">

        <!-- Modal Header -->
        <div class="p-4 bg-gradient-to-r from-pumpkin_spice to-pumpkin_spice-600 text-white flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-address-card text-base"></i>
                </div>
                <div>
                    <h3 id="open_card_modal_title" class="font-extrabold text-sm leading-tight">Open New Susu Passbook</h3>
                    <p class="text-[11px] text-white/70 mt-0.5">31-Space Savings Card</p>
                </div>
            </div>
            <button type="button" onclick="closeNewCardModal()"
                    class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/25 text-white flex items-center justify-center transition flex-shrink-0"
                    title="Close" aria-label="Close modal">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-4 sm:p-5 space-y-4">

            <!-- Customer Identity Review Card -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-steel_azure text-white font-black flex items-center justify-center text-sm flex-shrink-0 shadow-xs"
                     id="oc_avatar">--</div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-extrabold text-slate-800 truncate" id="oc_name">-</div>
                    <div class="text-[11px] text-slate-500 font-mono font-semibold" id="oc_account">-</div>
                    <div class="text-[10px] text-slate-400 mt-0.5" id="oc_collector">-</div>
                </div>
                <span class="text-[10px] font-black text-amber-700 bg-amber-100 border border-amber-200 px-2 py-1 rounded-lg whitespace-nowrap flex-shrink-0">
                    No Active Card
                </span>
            </div>

            <!-- Daily Contribution Picker -->
            <div>
                <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-2.5">
                    Daily Savings Amount (GH₵)
                </label>

                <!-- 1-Tap Quick Presets: 2 cols on mobile, 4 on desktop (Fitts's Law) -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                    <?php foreach ([10, 20, 50, 100] as $preset): ?>
                        <button type="button"
                                class="oc-preset-btn py-3 sm:py-2 text-sm sm:text-xs font-extrabold rounded-xl border border-silver-600 bg-white text-slate-700 hover:border-pumpkin_spice hover:bg-orange-50 hover:text-pumpkin_spice transition active:scale-95"
                                data-amount="<?= $preset ?>">
                            GH₵ <?= $preset ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Custom Amount Input -->
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 font-black text-sm">GH₵</span>
                    <input type="number" id="oc_daily_amount" inputmode="numeric" name="daily_amount"
                           step="1" min="1" max="9999"
                           class="w-full pl-12 pr-4 py-3 sm:py-2.5 rounded-xl border border-silver-600 focus:border-pumpkin_spice focus:ring-2 focus:ring-pumpkin_spice/30 outline-none text-base sm:text-sm font-black text-slate-800 transition"
                           placeholder="Or type a custom amount">
                </div>
            </div>

            <!-- Live 31-Space Target Calculator -->
            <div id="oc_target_preview"
                 class="bg-gradient-to-r from-pumpkin_spice/10 to-orange-50 border border-pumpkin_spice/20 rounded-xl px-4 py-3 flex items-center justify-between"
                 style="display:none">
                <div>
                    <div class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">31 Spaces × <span id="oc_preview_rate">GH₵ 0.00</span></div>
                    <div class="text-xl sm:text-lg font-black text-pumpkin_spice" id="oc_preview_total">GH₵ 0.00</div>
                    <div class="text-[10px] text-slate-400 font-medium">Total Savings Target</div>
                </div>
                <i class="fa-solid fa-piggy-bank text-4xl sm:text-3xl text-pumpkin_spice/25"></i>
            </div>

            <!-- Action Buttons -->
            <form id="open_card_form" method="POST" action="start_new_card.php">
                <input type="hidden" id="oc_customer_id" name="customer_id" value="">
                <input type="hidden" id="oc_amount_hidden" name="daily_amount" value="">
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 pt-1">
                    <button type="button" onclick="closeNewCardModal()"
                            class="flex-1 py-3 sm:py-2.5 px-4 bg-white text-slate-600 hover:bg-platinum-800 border border-silver-600 text-sm font-bold rounded-xl transition order-2 sm:order-1">
                        Cancel
                    </button>
                    <button type="submit" id="oc_confirm_btn"
                            class="flex-1 py-3 sm:py-2.5 px-4 bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-sm font-extrabold rounded-xl shadow-sm transition flex items-center justify-center gap-2 order-1 sm:order-2"
                            style="opacity:0.5;cursor:not-allowed" disabled>
                        <i class="fa-solid fa-circle-check text-sm"></i>
                        <span>Confirm &amp; Open Card</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openNewCardModal(customerId, customerName, accountNumber, collectorName, defaultAmount) {
    // Populate identity card
    const initials = (customerName || '--').split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
    const oc_avatar   = document.getElementById('oc_avatar');
    const oc_name     = document.getElementById('oc_name');
    const oc_account  = document.getElementById('oc_account');
    const oc_collector= document.getElementById('oc_collector');
    if (oc_avatar)    oc_avatar.textContent   = initials;
    if (oc_name)      oc_name.textContent     = customerName;
    if (oc_account)   oc_account.textContent  = 'A/C: ' + accountNumber;
    if (oc_collector) oc_collector.textContent = 'Collector: ' + collectorName;

    // Set hidden customer ID
    document.getElementById('oc_customer_id').value = customerId;

    // Set default daily amount
    const amountInput = document.getElementById('oc_daily_amount');
    if (amountInput) {
        amountInput.value = defaultAmount > 0 ? defaultAmount : '';
        updateOcPreset(defaultAmount);
        updateOcPreview();
    }

    // Highlight the matching preset button (if any)
    document.querySelectorAll('.oc-preset-btn').forEach(btn => {
        const v = parseFloat(btn.getAttribute('data-amount'));
        if (v === parseFloat(defaultAmount)) {
            btn.classList.add('border-pumpkin_spice', 'bg-orange-50', 'text-pumpkin_spice');
            btn.classList.remove('border-silver-600', 'bg-white', 'text-slate-700');
        } else {
            btn.classList.remove('border-pumpkin_spice', 'bg-orange-50', 'text-pumpkin_spice');
            btn.classList.add('border-silver-600', 'bg-white', 'text-slate-700');
        }
    });

    // Show modal
    const modal = document.getElementById('open_card_modal');
    const box   = document.getElementById('open_card_modal_box');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        box.classList.remove('scale-95');
        box.classList.add('scale-100');
    });
}

function closeNewCardModal() {
    const modal = document.getElementById('open_card_modal');
    const box   = document.getElementById('open_card_modal_box');
    if (!modal) return;
    box.classList.remove('scale-100');
    box.classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 150);
}

function updateOcPreset(amount) {
    document.querySelectorAll('.oc-preset-btn').forEach(btn => {
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

function updateOcPreview() {
    const amount      = parseFloat(document.getElementById('oc_daily_amount').value) || 0;
    const hiddenInput = document.getElementById('oc_amount_hidden');
    const confirmBtn  = document.getElementById('oc_confirm_btn');
    const preview     = document.getElementById('oc_target_preview');
    const previewRate = document.getElementById('oc_preview_rate');
    const previewTotal= document.getElementById('oc_preview_total');

    if (hiddenInput) hiddenInput.value = amount > 0 ? amount : '';

    if (amount > 0) {
        const total = amount * 31;
        if (previewRate)  previewRate.textContent  = 'GH₵ ' + amount.toFixed(2);
        if (previewTotal) previewTotal.textContent = 'GH₵ ' + total.toFixed(2);
        if (preview)      preview.style.display = 'flex';
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.style.opacity = '1';
            confirmBtn.style.cursor  = 'pointer';
        }
    } else {
        if (preview)    preview.style.display = 'none';
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.style.opacity = '0.5';
            confirmBtn.style.cursor  = 'not-allowed';
        }
    }
}

// Wire up preset buttons & custom input
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.oc-preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const v = parseFloat(btn.getAttribute('data-amount'));
            document.getElementById('oc_daily_amount').value = v;
            updateOcPreset(v);
            updateOcPreview();
        });
    });

    const amountInput = document.getElementById('oc_daily_amount');
    if (amountInput) {
        amountInput.addEventListener('input', () => {
            updateOcPreset(parseFloat(amountInput.value));
            updateOcPreview();
        });
    }

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        const modal = document.getElementById('open_card_modal');
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeNewCardModal();
        }
    });

    // Close on backdrop click
    const modal = document.getElementById('open_card_modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeNewCardModal();
        });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

