<?php
// customers.php - View & Search Customers
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = get_logged_in_user();
$pageTitle = "Customers Directory";
$pdo = get_db_connection();

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

        <div class="flex items-center gap-3">
            <?php if ($user['role'] === 'admin'): ?>
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
            <div class="w-full sm:max-w-md relative">
                <input type="text" id="customer_search" placeholder="Search by name, account number, or location..."
                       class="w-full pl-9 pr-4 py-2.5 text-xs sm:text-sm rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none transition">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            </div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                <span>Total Registered:</span>
                <span class="px-2.5 py-0.5 rounded-full bg-steel_azure text-white font-extrabold text-xs">
                    <?= $pagedData['total'] ?> clients
                </span>
            </div>
        </div>

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
                <tbody class="divide-y divide-silver-600/50">
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
                                        <span class="text-xs text-amber-600 font-medium">No active card</span>
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
        <div class="md:hidden divide-y divide-silver-600/50">
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
                        <div class="mt-3 flex items-center gap-2 pt-2 border-t border-silver-600/60">
                            <a href="record_deposit.php?customer_id=<?= $c['id'] ?>" class="flex-1 btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-bold py-2 rounded-xl flex items-center justify-center gap-1.5 shadow-2xs">
                                <i class="fa-solid fa-plus text-xs"></i>
                                <span>Deposit</span>
                            </a>
                            <a href="view_card.php?id=<?= $c['card_id'] ?>" class="flex-1 btn-touch bg-white hover:bg-platinum text-steel_azure border border-steel_azure text-xs font-bold py-2 rounded-xl flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-id-card text-xs"></i>
                                <span>View Card</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Controls -->
        <?= render_pagination($pagedData['total'], $pagedData['per_page'], $pagedData['current']) ?>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
