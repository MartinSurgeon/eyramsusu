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
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $collectorId = !empty($_POST['assigned_collector_id']) ? (int)$_POST['assigned_collector_id'] : null;
    $newDailyAmount = isset($_POST['daily_amount']) && $_POST['daily_amount'] !== '' ? (float)$_POST['daily_amount'] : null;

    $accountDigits = preg_replace('/[^0-9]/', '', $accountNumber);
    $phoneDigits = preg_replace('/[^0-9]/', '', $phone);

    if ($customerId <= 0 || empty($accountNumber) || empty($fullName) || empty($phone)) {
        set_flash_message('error', 'Account Number, full name, and phone number are required.');
    } elseif ($accountNumber !== $accountDigits) {
        set_flash_message('error', 'Account Number must contain numbers only (digits 0-9).');
    } elseif ($phone !== $phoneDigits || strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
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

                // Update customer profile including account_number
                $stmtUpd = $pdo->prepare("
                    UPDATE customers 
                    SET account_number = ?, full_name = ?, phone = ?, location = ?, assigned_collector_id = ?
                    WHERE id = ?
                ");
                $stmtUpd->execute([$accountDigits, $fullName, $phoneDigits, $location, $collectorId, $customerId]);

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

// Search Query parameter support (server-side & URL fallback)
$searchQuery = trim($_GET['search'] ?? $_GET['q'] ?? '');

if ($searchQuery !== '') {
    $searchWildcard = '%' . $searchQuery . '%';
    if ($user['role'] === 'collector') {
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name as collector_name,
                   sc.id as card_id, sc.card_number, sc.daily_amount, sc.spaces_filled, sc.total_spaces, sc.total_saved, sc.status as card_status
            FROM customers c
            LEFT JOIN users u ON c.assigned_collector_id = u.id
            LEFT JOIN susu_cards sc ON c.id = sc.customer_id AND sc.status = 'active'
            WHERE c.assigned_collector_id = ? AND c.is_active = 1
              AND (c.full_name LIKE ? OR c.account_number LIKE ? OR c.phone LIKE ? OR c.location LIKE ?)
            ORDER BY c.full_name ASC
        ");
        $stmt->execute([$user['id'], $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name as collector_name,
                   sc.id as card_id, sc.card_number, sc.daily_amount, sc.spaces_filled, sc.total_spaces, sc.total_saved, sc.status as card_status
            FROM customers c
            LEFT JOIN users u ON c.assigned_collector_id = u.id
            LEFT JOIN susu_cards sc ON c.id = sc.customer_id AND sc.status = 'active'
            WHERE c.is_active = 1
              AND (c.full_name LIKE ? OR c.account_number LIKE ? OR c.phone LIKE ? OR c.location LIKE ? OR u.full_name LIKE ?)
            ORDER BY c.full_name ASC
        ");
        $stmt->execute([$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
    }
} else {
    // If collector, show their assigned customers by default
    if ($user['role'] === 'collector') {
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name as collector_name,
                   sc.id as card_id, sc.card_number, sc.daily_amount, sc.spaces_filled, sc.total_spaces, sc.total_saved, sc.status as card_status
            FROM customers c
            LEFT JOIN users u ON c.assigned_collector_id = u.id
            LEFT JOIN susu_cards sc ON c.id = sc.customer_id AND sc.status = 'active'
            WHERE c.assigned_collector_id = ? AND c.is_active = 1
            ORDER BY c.full_name ASC
        ");
        $stmt->execute([$user['id']]);
    } else {
        // Admin sees all customers
        $stmt = $pdo->query("
            SELECT c.*, u.full_name as collector_name,
                   sc.id as card_id, sc.card_number, sc.daily_amount, sc.spaces_filled, sc.total_spaces, sc.total_saved, sc.status as card_status
            FROM customers c
            LEFT JOIN users u ON c.assigned_collector_id = u.id
            LEFT JOIN susu_cards sc ON c.id = sc.customer_id AND sc.status = 'active'
            WHERE c.is_active = 1
            ORDER BY c.full_name ASC
        ");
    }
}

$allCustomers = $stmt->fetchAll();

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

    <!-- Search & Filter Card -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        
        <div class="p-4 sm:p-5 border-b border-silver-600/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <form method="GET" action="customers.php" class="w-full sm:max-w-md relative" id="customer_search_form">
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
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                <span>Total Registered:</span>
                <span class="px-2.5 py-0.5 rounded-full bg-steel_azure text-white font-extrabold text-xs" id="total_customers_count">
                    <?= $pagedData['total'] ?> clients
                </span>
            </div>
        </div>

        <?php if (!empty($searchQuery)): ?>
            <div id="search_filter_banner" class="px-4 py-2 bg-blue-50/70 border-b border-blue-100 flex items-center justify-between text-xs">
                <span class="text-steel_azure font-semibold">
                    Search results for <strong class="text-slate-800">"<?= htmlspecialchars($searchQuery) ?>"</strong> (<?= $pagedData['total'] ?> found)
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
                                    <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($c['full_name']) ?></div>
                                    <div class="text-[11px] font-semibold text-slate-400 font-mono"><?= htmlspecialchars($c['account_number']) ?></div>
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    <div class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-phone text-slate-400 text-[11px]"></i>
                                        <span><?= htmlspecialchars($c['phone']) ?></span>
                                    </div>
                                    <div class="text-xs text-slate-400 mt-0.5 flex items-center gap-1.5">
                                        <i class="fa-solid fa-location-dot text-slate-400 text-[11px]"></i>
                                        <span><?= htmlspecialchars($c['location'] ?: 'Not specified') ?></span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-slate-700 font-medium">
                                    <?= htmlspecialchars($c['collector_name'] ?: 'Unassigned') ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($c['card_id']): ?>
                                        <div class="font-bold text-steel_azure">
                                            <?= format_money($c['daily_amount']) ?> / space
                                        </div>
                                        <div class="text-[11px] text-emerald-600 font-semibold">
                                            <?= $c['spaces_filled'] ?> of <?= $c['total_spaces'] ?> spaces (<?= format_money($c['total_saved']) ?>)
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-amber-600 font-semibold">No active card</span>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <form method="POST" action="start_new_card.php" class="inline">
                                                    <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                                    <input type="hidden" name="daily_amount" value="<?= $c['daily_amount'] > 0 ? $c['daily_amount'] : 20.00 ?>">
                                                    <button type="submit" class="px-2 py-0.5 bg-pumpkin_spice-900 hover:bg-pumpkin_spice text-pumpkin_spice hover:text-white border border-pumpkin_spice text-[10px] font-bold rounded-md transition cursor-pointer">
                                                        + Open
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-bold <?= $c['change_balance'] > 0 ? 'text-pumpkin_spice' : 'text-slate-400' ?>">
                                        <?= format_money($c['change_balance']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <?php if ($c['card_id']): ?>
                                            <a href="record_deposit.php?customer_id=<?= $c['id'] ?>" class="btn-touch px-3 py-1.5 bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-bold rounded-xl shadow-2xs transition inline-flex items-center gap-1.5">
                                                <i class="fa-solid fa-plus text-xs"></i>
                                                <span>Deposit</span>
                                            </a>
                                            <a href="view_card.php?id=<?= $c['card_id'] ?>" class="btn-touch px-3 py-1.5 bg-white hover:bg-platinum text-steel_azure border border-steel_azure text-xs font-bold rounded-xl transition inline-flex items-center gap-1.5">
                                                <i class="fa-solid fa-id-card text-xs"></i>
                                                <span>Card</span>
                                            </a>
                                        <?php elseif ($user['role'] === 'admin'): ?>
                                            <form method="POST" action="start_new_card.php" class="inline">
                                                <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                                <input type="hidden" name="daily_amount" value="<?= $c['daily_amount'] > 0 ? $c['daily_amount'] : 20.00 ?>">
                                                <button type="submit" class="btn-touch px-3 py-1.5 bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-extrabold rounded-xl shadow-2xs transition inline-flex items-center gap-1.5 cursor-pointer">
                                                    <i class="fa-solid fa-circle-plus text-xs"></i>
                                                    <span>+ Open Card</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($user['role'] === 'admin'): ?>
                                            <button type="button" 
                                                    onclick='openEditCustomerModal(<?= htmlspecialchars(json_encode([
                                                        'id' => $c['id'],
                                                        'full_name' => $c['full_name'],
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
                                                    class="btn-touch px-2.5 py-1.5 bg-blue-50 hover:bg-steel_azure hover:text-white text-steel_azure border border-blue-200 text-xs font-bold rounded-xl transition inline-flex items-center gap-1.5"
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
                            <div class="font-extrabold text-sm text-slate-800"><?= htmlspecialchars($c['full_name']) ?></div>
                            <div class="text-[11px] text-slate-500 font-mono"><?= htmlspecialchars($c['account_number']) ?> &bull; <?= htmlspecialchars($c['phone']) ?></div>
                        </div>
                        <?php if ($c['card_id']): ?>
                            <span class="text-xs font-bold text-steel_azure bg-platinum px-2 py-0.5 rounded border border-silver-600">
                                <?= format_money($c['daily_amount']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($c['card_id']): ?>
                        <div class="mt-2 text-xs text-slate-600">
                            Saved: <strong class="text-emerald-700"><?= format_money($c['total_saved']) ?></strong> (<?= $c['spaces_filled'] ?>/<?= $c['total_spaces'] ?> spaces)
                            <?php if ($c['change_balance'] > 0): ?>
                                &bull; Float: <strong class="text-pumpkin_spice"><?= format_money($c['change_balance']) ?></strong>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3 flex items-center gap-2 pt-2 border-t border-silver-600/60">
                        <?php if ($c['card_id']): ?>
                            <a href="record_deposit.php?customer_id=<?= $c['id'] ?>" class="flex-1 btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-bold py-2 rounded-xl flex items-center justify-center gap-1.5 shadow-2xs">
                                <i class="fa-solid fa-plus text-xs"></i>
                                <span>Deposit</span>
                            </a>
                            <a href="view_card.php?id=<?= $c['card_id'] ?>" class="flex-1 btn-touch bg-white hover:bg-platinum text-steel_azure border border-steel_azure text-xs font-bold py-2 rounded-xl flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-id-card text-xs"></i>
                                <span>Card</span>
                            </a>
                        <?php elseif ($user['role'] === 'admin'): ?>
                            <form method="POST" action="start_new_card.php" class="flex-1">
                                <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="daily_amount" value="<?= $c['daily_amount'] > 0 ? $c['daily_amount'] : 20.00 ?>">
                                <button type="submit" class="w-full btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-extrabold py-2 rounded-xl flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer">
                                    <i class="fa-solid fa-circle-plus text-xs"></i>
                                    <span>+ Open Susu Card</span>
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($user['role'] === 'admin'): ?>
                            <button type="button" 
                                    onclick='openEditCustomerModal(<?= htmlspecialchars(json_encode([
                                        'id' => $c['id'],
                                        'full_name' => $c['full_name'],
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
<div id="edit_customer_modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden transition-opacity">
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

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <!-- Phone Number -->
                    <div>
                        <label for="edit_phone" class="block font-bold text-slate-700 mb-1">
                            Phone Number *
                            <span class="text-[10px] text-slate-400 font-normal ml-1">(Numbers only)</span>
                        </label>
                        <input type="tel" id="edit_phone" name="phone" required
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
                        <label for="edit_location" class="block font-bold text-slate-700 mb-1">Location / Market Stall</label>
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
    const phoneInput = document.getElementById('edit_phone');
    const phone = phoneInput.value.trim().replace(/[^0-9]/g, '');
    phoneInput.value = phone;
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
    if (!phone || phone.length < 10 || phone.length > 15) {
        alert('Phone number must contain numbers only (at least 10 digits, e.g. 0244123456).');
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
    document.getElementById('confirm_phone').textContent = phone;
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
