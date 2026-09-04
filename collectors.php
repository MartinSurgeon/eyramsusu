<?php
// collectors.php - Admin Collector CRUD Management
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_admin();

$pdo = get_db_connection();
$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create New Collector
    if ($action === 'create_collector') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($fullName) || empty($phone) || empty($username) || empty($password)) {
            $error = 'All fields (Full Name, Phone, Username, Password) are required.';
        } else {
            // Check username uniqueness
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtCheck->execute([$username]);
            if ($stmtCheck->fetch()) {
                $error = "Username '{$username}' is already taken. Please choose another.";
            } else {
                try {
                    $passHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO users (full_name, username, password_hash, role, phone, is_active) 
                        VALUES (?, ?, ?, 'collector', ?, 1)
                    ");
                    $stmtInsert->execute([$fullName, $username, $passHash, $phone]);

                    set_flash_message('success', "Collector '{$fullName}' registered successfully with username '{$username}'!");
                    header('Location: collectors.php');
                    exit;
                } catch (Exception $e) {
                    $error = 'Error registering collector: ' . $e->getMessage();
                }
            }
        }
    }

    // 2. Update Collector
    elseif ($action === 'update_collector') {
        $collectorId = (int)($_POST['collector_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $routeAction = $_POST['route_action'] ?? 'none';

        if ($collectorId <= 0 || empty($fullName) || empty($phone) || empty($username)) {
            $error = 'Collector ID, Full Name, Phone, and Username are required.';
        } else {
            // Check if username taken by another user
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmtCheck->execute([$username, $collectorId]);
            if ($stmtCheck->fetch()) {
                $error = "Username '{$username}' is already in use by another user.";
            } else {
                try {
                    $pdo->beginTransaction();

                    if (!empty($newPassword)) {
                        $passHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmtUp = $pdo->prepare("
                            UPDATE users 
                            SET full_name = ?, phone = ?, username = ?, password_hash = ?, is_active = ? 
                            WHERE id = ? AND role = 'collector'
                        ");
                        $stmtUp->execute([$fullName, $phone, $username, $passHash, $isActive, $collectorId]);
                    } else {
                        $stmtUp = $pdo->prepare("
                            UPDATE users 
                            SET full_name = ?, phone = ?, username = ?, is_active = ? 
                            WHERE id = ? AND role = 'collector'
                        ");
                        $stmtUp->execute([$fullName, $phone, $username, $isActive, $collectorId]);
                    }

                    // Route Reassignment Processing
                    $assignedMsg = '';
                    if ($routeAction === 'assign_unassigned') {
                        $stmtAssign = $pdo->prepare("UPDATE customers SET assigned_collector_id = ? WHERE assigned_collector_id IS NULL AND is_active = 1");
                        $stmtAssign->execute([$collectorId]);
                        $count = $stmtAssign->rowCount();
                        if ($count > 0) {
                            $assignedMsg = " and assigned {$count} previously unassigned client(s)";
                            create_notification($collectorId, 'customer_assigned', "Route Assignment", "{$count} unassigned clients assigned to your route.", "customers.php");
                        }
                    } elseif ($routeAction === 'transfer_from') {
                        $fromColId = (int)($_POST['transfer_from_collector_id'] ?? 0);
                        if ($fromColId > 0 && $fromColId !== $collectorId) {
                            $stmtTransfer = $pdo->prepare("UPDATE customers SET assigned_collector_id = ? WHERE assigned_collector_id = ?");
                            $stmtTransfer->execute([$collectorId, $fromColId]);
                            $count = $stmtTransfer->rowCount();
                            if ($count > 0) {
                                $assignedMsg = " and transferred {$count} client(s) to their route";
                                create_notification($collectorId, 'customer_assigned', "Route Transferred", "{$count} clients transferred to your route.", "customers.php");
                            }
                        }
                    } elseif ($routeAction === 'unassign_all') {
                        $stmtUnassign = $pdo->prepare("UPDATE customers SET assigned_collector_id = NULL WHERE assigned_collector_id = ?");
                        $stmtUnassign->execute([$collectorId]);
                        $count = $stmtUnassign->rowCount();
                        if ($count > 0) {
                            $assignedMsg = " and unassigned {$count} client(s)";
                        }
                    } elseif ($routeAction === 'specific_clients' && isset($_POST['selected_customer_ids']) && is_array($_POST['selected_customer_ids'])) {
                        $selectedIds = array_map('intval', $_POST['selected_customer_ids']);
                        if (!empty($selectedIds)) {
                            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                            $stmtSpecific = $pdo->prepare("UPDATE customers SET assigned_collector_id = ? WHERE id IN ($placeholders)");
                            $stmtSpecific->execute(array_merge([$collectorId], $selectedIds));
                            $count = count($selectedIds);
                            $assignedMsg = " and assigned {$count} selected client(s)";
                            create_notification($collectorId, 'customer_assigned', "Clients Assigned", "{$count} clients assigned to your route.", "customers.php");
                        }
                    }

                    $pdo->commit();

                    set_flash_message('success', "Collector '{$fullName}' updated successfully{$assignedMsg}!");
                    header('Location: collectors.php');
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Error updating collector: ' . $e->getMessage();
                }
            }
        }
    }

    // 3. Deactivate & Reassign Customers
    elseif ($action === 'deactivate_and_reassign') {
        $collectorId = (int)($_POST['collector_id'] ?? 0);
        $newCollectorId = !empty($_POST['new_collector_id']) ? (int)$_POST['new_collector_id'] : null;

        if ($collectorId <= 0) {
            $error = 'Invalid collector selected.';
        } else {
            try {
                $pdo->beginTransaction();

                // Reassign customers if another collector selected
                if ($newCollectorId > 0 && $newCollectorId !== $collectorId) {
                    $stmtReassign = $pdo->prepare("
                        UPDATE customers 
                        SET assigned_collector_id = ? 
                        WHERE assigned_collector_id = ?
                    ");
                    $stmtReassign->execute([$newCollectorId, $collectorId]);

                    $reassignedCount = $stmtReassign->rowCount();

                    // Notify new collector
                    create_notification(
                        $newCollectorId,
                        'customer_assigned',
                        "Route Reassignment",
                        "{$reassignedCount} clients transferred to your collection route.",
                        "customers.php"
                    );
                } else {
                    // Set customers to unassigned
                    $stmtReassign = $pdo->prepare("
                        UPDATE customers 
                        SET assigned_collector_id = NULL 
                        WHERE assigned_collector_id = ?
                    ");
                    $stmtReassign->execute([$collectorId]);
                }

                // Deactivate collector
                $stmtDeactivate = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'collector'");
                $stmtDeactivate->execute([$collectorId]);

                $pdo->commit();

                set_flash_message('success', "Collector deactivated and assigned clients transferred successfully!");
                header('Location: collectors.php');
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error deactivating collector: ' . $e->getMessage();
            }
        }
    }

    // 4. Reactivate Collector
    elseif ($action === 'reactivate_collector') {
        $collectorId = (int)($_POST['collector_id'] ?? 0);
        $routeAction = $_POST['reactivate_route_action'] ?? 'none';

        if ($collectorId > 0) {
            try {
                $pdo->beginTransaction();

                $stmtAct = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ? AND role = 'collector'");
                $stmtAct->execute([$collectorId]);

                $reassignedCount = 0;
                if ($routeAction === 'assign_unassigned') {
                    $stmtAssign = $pdo->prepare("UPDATE customers SET assigned_collector_id = ? WHERE assigned_collector_id IS NULL AND is_active = 1");
                    $stmtAssign->execute([$collectorId]);
                    $reassignedCount = $stmtAssign->rowCount();
                } elseif ($routeAction === 'transfer_from') {
                    $fromColId = (int)($_POST['reactivate_transfer_from_id'] ?? 0);
                    if ($fromColId > 0 && $fromColId !== $collectorId) {
                        $stmtTransfer = $pdo->prepare("UPDATE customers SET assigned_collector_id = ? WHERE assigned_collector_id = ?");
                        $stmtTransfer->execute([$collectorId, $fromColId]);
                        $reassignedCount = $stmtTransfer->rowCount();
                    }
                } elseif ($routeAction === 'specific_clients' && isset($_POST['reactivate_selected_customer_ids']) && is_array($_POST['reactivate_selected_customer_ids'])) {
                    $selectedIds = array_map('intval', $_POST['reactivate_selected_customer_ids']);
                    if (!empty($selectedIds)) {
                        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                        $stmtSpecific = $pdo->prepare("UPDATE customers SET assigned_collector_id = ? WHERE id IN ($placeholders)");
                        $stmtSpecific->execute(array_merge([$collectorId], $selectedIds));
                        $reassignedCount = count($selectedIds);
                    }
                }

                if ($reassignedCount > 0) {
                    create_notification($collectorId, 'customer_assigned', "Route Assignment on Reactivation", "{$reassignedCount} clients assigned to your route.", "customers.php");
                }

                $pdo->commit();

                $successMsg = "Collector reactivated successfully" . ($reassignedCount > 0 ? " with {$reassignedCount} client(s) assigned!" : "!");
                set_flash_message('success', $successMsg);
                header('Location: collectors.php');
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error reactivating collector: ' . $e->getMessage();
            }
        }
    }
}

// Fetch unassigned active customers
$stmtUnassigned = $pdo->query("
    SELECT id, full_name, account_number, phone, location 
    FROM customers 
    WHERE assigned_collector_id IS NULL AND is_active = 1
    ORDER BY full_name ASC
");
$unassignedCustomers = $stmtUnassigned->fetchAll();
$unassignedCount = count($unassignedCustomers);

// Fetch all collectors with statistics
$stmtAll = $pdo->query("
    SELECT u.*,
           COUNT(DISTINCT c.id) as assigned_clients,
           COALESCE(SUM(CASE WHEN d.handover_id IS NULL THEN d.amount ELSE 0 END), 0.00) as cash_in_hand,
           COALESCE(SUM(CASE WHEN d.deposit_date = CURRENT_DATE THEN d.amount ELSE 0 END), 0.00) as today_collected
    FROM users u
    LEFT JOIN customers c ON u.id = c.assigned_collector_id AND c.is_active = 1
    LEFT JOIN deposits d ON u.id = d.collector_id
    WHERE u.role = 'collector'
    GROUP BY u.id
    ORDER BY u.is_active DESC, u.full_name ASC
");
$collectors = $stmtAll->fetchAll();

// Pagination setup
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$pagedCollectors = paginate_array($collectors, $perPage, $page);
$displayCollectors = $pagedCollectors['items'];

// Active collectors for reassignment dropdown
$activeCollectors = array_filter($collectors, fn($col) => (int)$col['is_active'] === 1);

// Aggregated KPIs
$totalCollectors = count($collectors);
$activeCount = count($activeCollectors);
$totalCashInHands = array_sum(array_column($collectors, 'cash_in_hand'));
$totalAssignedClients = array_sum(array_column($collectors, 'assigned_clients'));

$pageTitle = "Collectors Management";
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

    <!-- Top Header Bar (Hick's Law: 1 Primary CTA) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 section-card">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl sm:text-2xl font-black text-steel_azure">Field Collectors</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-steel_azure text-white">
                    <?= $activeCount ?> active
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Manage agent accounts, cash in hand liabilities, and route client assignments.</p>
        </div>

        <button type="button" onclick="openAddModal()" 
                class="btn-action-primary text-xs sm:text-sm flex items-center gap-1.5">
            <i class="fa-solid fa-user-plus text-xs"></i>
            <span>Register New Collector</span>
        </button>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3.5 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Summary Row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        
        <div class="kpi-card flex flex-col justify-between">
            <div>
                <div class="kpi-icon bg-blue-50 text-steel_azure"><i class="fa-solid fa-users"></i></div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Active Field Agents</span>
                <div class="text-xl sm:text-2xl font-black text-steel_azure mt-1">
                    <?= $activeCount ?> <span class="text-xs text-slate-400 font-normal">/ <?= $totalCollectors ?></span>
                </div>
            </div>
            <span class="text-[11px] text-slate-400 mt-2">Authorized collectors</span>
        </div>

        <div class="kpi-card flex flex-col justify-between">
            <div>
                <div class="kpi-icon bg-orange-50 text-pumpkin_spice"><i class="fa-solid fa-sack-dollar"></i></div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Total Cash in Field</span>
                <div class="text-xl sm:text-2xl font-black <?= $totalCashInHands > 0 ? 'text-pumpkin_spice' : 'text-slate-600' ?> mt-1">
                    <?= format_money($totalCashInHands) ?>
                </div>
            </div>
            <a href="daily_handover.php" class="btn-touch mt-3 w-full py-1.5 px-3 rounded-xl text-xs font-bold bg-orange-50 text-pumpkin_spice hover:bg-pumpkin_spice hover:text-white border border-orange-200 transition shadow-2xs flex items-center justify-center gap-1.5">
                <span>View Handovers</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <div class="kpi-card flex flex-col justify-between">
            <div>
                <div class="kpi-icon bg-emerald-50 text-emerald-600"><i class="fa-solid fa-user-group"></i></div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Assigned Clients</span>
                <div class="text-xl sm:text-2xl font-black text-emerald-700 mt-1">
                    <?= $totalAssignedClients ?>
                </div>
            </div>
            <a href="customers.php" class="btn-touch mt-3 w-full py-1.5 px-3 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white border border-emerald-200 transition shadow-2xs flex items-center justify-center gap-1.5">
                <span>View Clients</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <div class="kpi-card flex flex-col justify-between">
            <div>
                <div class="kpi-icon bg-purple-50 text-purple-700"><i class="fa-solid fa-shield-halved"></i></div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mt-2">Audit Security</span>
                <div class="text-base sm:text-lg font-black text-slate-800 mt-1">
                    100% Protected
                </div>
            </div>
            <span class="text-[11px] text-slate-400 mt-2">History preserved</span>
        </div>

    </div>

    <!-- Collectors Table -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-silver-600/70 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="section-heading-icon bg-blue-50 text-steel_azure">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-800">Collectors Registry</h2>
                    <p class="text-xs text-slate-500">Overview of collector login status, customer count, and liability.</p>
                </div>
            </div>
            <span class="text-xs text-slate-500 font-semibold"><?= $totalCollectors ?> registered</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-platinum text-slate-600 font-semibold border-b border-silver-600/70">
                    <tr>
                        <th class="py-3 px-4">Collector Details</th>
                        <th class="py-3 px-4">Phone</th>
                        <th class="py-3 px-4">Assigned Clients</th>
                        <th class="py-3 px-4">Collected Today</th>
                        <th class="py-3 px-4">Unsettled Cash</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-silver-600/50">
                    <?php if (empty($displayCollectors)): ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="empty-state">
                                    <div class="empty-state-icon bg-blue-50 text-steel_azure">
                                        <i class="fa-solid fa-users text-3xl"></i>
                                    </div>
                                    <div class="empty-state-title">No Collectors Registered</div>
                                    <div class="empty-state-text">Add your first field collector to begin managing savings routes.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($displayCollectors as $col): ?>
                            <tr class="hover:bg-platinum-800 transition <?= (int)$col['is_active'] === 0 ? 'opacity-60 bg-slate-50' : '' ?>">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($col['full_name']) ?></div>
                                    <div class="text-[11px] text-slate-400 font-mono">@<?= htmlspecialchars($col['username']) ?></div>
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    <div class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-phone text-slate-400 text-[11px]"></i>
                                        <span><?= htmlspecialchars($col['phone'] ?: 'N/A') ?></span>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <a href="customers.php" class="inline-flex items-center gap-1 font-bold text-steel_azure hover:underline">
                                        <span><?= $col['assigned_clients'] ?> clients</span>
                                    </a>
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-700">
                                    <?= format_money($col['today_collected']) ?>
                                </td>
                                <td class="py-3 px-4 font-black <?= $col['cash_in_hand'] > 0 ? 'text-pumpkin_spice' : 'text-slate-400' ?>">
                                    <?= format_money($col['cash_in_hand']) ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ((int)$col['is_active'] === 1): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                            ● Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-slate-200 text-slate-600">
                                            Deactivated
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Edit Button -->
                                        <button type="button" 
                                                onclick='openEditModal(<?= json_encode($col) ?>)'
                                                class="btn-touch px-3 py-1.5 bg-white hover:bg-platinum text-steel_azure border border-steel_azure text-xs font-bold rounded-xl transition inline-flex items-center gap-1.5">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                            <span>Edit</span>
                                        </button>

                                        <?php if ((int)$col['is_active'] === 1): ?>
                                            <!-- Deactivate / Reassign Button -->
                                            <button type="button" 
                                                    onclick='openDeactivateModal(<?= htmlspecialchars(json_encode($col), ENT_QUOTES) ?>)'
                                                    class="btn-touch px-3 py-1.5 bg-white hover:bg-red-50 text-red-600 border border-red-300 text-xs font-bold rounded-xl transition inline-flex items-center gap-1.5">
                                                <i class="fa-solid fa-user-slash text-xs"></i>
                                                <span>Deactivate</span>
                                            </button>
                                        <?php else: ?>
                                            <!-- Reactivate Modal Trigger (Replaces browser confirm) -->
                                            <button type="button" 
                                                    onclick='openReactivateModal(<?= htmlspecialchars(json_encode($col), ENT_QUOTES) ?>)'
                                                    class="btn-touch px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition inline-flex items-center gap-1.5 shadow-2xs">
                                                <i class="fa-solid fa-rotate-left text-xs"></i>
                                                <span>Reactivate</span>
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

        <?= render_pagination($pagedCollectors['total'], $pagedCollectors['per_page'], $pagedCollectors['current']) ?>
    </div>

</div>

<!-- ============================================================
     MODAL 1: Register New Collector
     ============================================================ -->
<div id="add_modal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm hidden" role="dialog" aria-modal="true" aria-labelledby="add_modal_title">
    <div class="bg-white rounded-2xl border border-silver-600 shadow-2xl max-w-lg w-full overflow-hidden transform transition-all scale-95 duration-200 my-auto" id="add_modal_box">
        <!-- Header -->
        <div class="p-4 sm:p-5 bg-gradient-to-r from-steel_azure to-steel_azure-400 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-user-plus text-base"></i>
                </div>
                <div>
                    <h3 id="add_modal_title" class="font-extrabold text-sm sm:text-base leading-tight">Register New Field Collector</h3>
                    <p class="text-xs text-white/75 mt-0.5">Create an authorized mobile field agent account.</p>
                </div>
            </div>
            <button type="button" onclick="closeAddModal()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer" title="Close" aria-label="Close modal">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="collectors.php" class="p-5 sm:p-6 space-y-4">
            <input type="hidden" name="action" value="create_collector">

            <div>
                <label for="add_full_name" class="block text-xs font-bold text-slate-700 mb-1">Full Legal Name *</label>
                <input type="text" id="add_full_name" name="full_name" required placeholder="e.g. Yaw Boateng"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm font-semibold transition bg-slate-50/50 focus:bg-white">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="add_phone" class="block text-xs font-bold text-slate-700 mb-1">Mobile Phone Number *</label>
                    <input type="text" id="add_phone" name="phone" required placeholder="e.g. 0244123456"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm font-semibold transition bg-slate-50/50 focus:bg-white">
                </div>

                <div>
                    <label for="add_username" class="block text-xs font-bold text-slate-700 mb-1">Login Username *</label>
                    <input type="text" id="add_username" name="username" required placeholder="e.g. yaw"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm font-semibold transition bg-slate-50/50 focus:bg-white">
                </div>
            </div>

            <div>
                <label for="add_password" class="block text-xs font-bold text-slate-700 mb-1">Initial Password *</label>
                <input type="password" id="add_password" name="password" required placeholder="••••••••"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm font-semibold transition bg-slate-50/50 focus:bg-white">
                <p class="text-[11px] text-slate-400 mt-1">The collector will use this password to sign in to the mobile field app.</p>
            </div>

            <div class="pt-4 border-t border-silver-600 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeAddModal()" class="btn-touch bg-white text-slate-600 border border-silver-600 hover:bg-slate-50 text-xs font-bold px-4 py-2.5 rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-extrabold px-5 py-2.5 rounded-xl shadow-md transition flex items-center gap-1.5">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Register Collector</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     MODAL 2: Edit Collector Details & Route Management
     ============================================================ -->
<div id="edit_modal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm hidden" role="dialog" aria-modal="true" aria-labelledby="edit_modal_title">
    <div class="bg-white rounded-2xl border border-silver-600 shadow-2xl max-w-xl w-full overflow-hidden transform transition-all scale-95 duration-200 my-auto max-h-[92vh] flex flex-col" id="edit_modal_box">
        <!-- Header -->
        <div class="p-4 sm:p-5 bg-gradient-to-r from-steel_azure to-steel_azure-400 text-white flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-user-pen text-base"></i>
                </div>
                <div>
                    <h3 id="edit_modal_title" class="font-extrabold text-sm sm:text-base leading-tight">Edit Collector Profile & Routes</h3>
                    <p class="text-xs text-white/75 mt-0.5">Update agent credentials and customer route assignments.</p>
                </div>
            </div>
            <button type="button" onclick="closeEditModal()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer" title="Close" aria-label="Close modal">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="collectors.php" class="p-5 sm:p-6 space-y-5 overflow-y-auto flex-1">
            <input type="hidden" name="action" value="update_collector">
            <input type="hidden" id="edit_collector_id" name="collector_id" value="">

            <!-- Group 1: Profile & Credentials -->
            <div class="space-y-3.5">
                <div class="flex items-center gap-2 pb-2 border-b border-silver-600/70 text-steel_azure font-extrabold text-xs uppercase tracking-wider">
                    <i class="fa-solid fa-id-card"></i>
                    <span>Agent Profile & Credentials</span>
                </div>

                <div>
                    <label for="edit_full_name" class="block text-xs font-bold text-slate-700 mb-1">Full Legal Name *</label>
                    <input type="text" id="edit_full_name" name="full_name" required
                           class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm font-semibold transition bg-slate-50/50 focus:bg-white">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="edit_phone" class="block text-xs font-bold text-slate-700 mb-1">Mobile Phone Number *</label>
                        <input type="text" id="edit_phone" name="phone" required
                               class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm font-semibold transition bg-slate-50/50 focus:bg-white">
                    </div>

                    <div>
                        <label for="edit_username" class="block text-xs font-bold text-slate-700 mb-1">Login Username *</label>
                        <input type="text" id="edit_username" name="username" required
                               class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm font-semibold transition bg-slate-50/50 focus:bg-white">
                    </div>
                </div>

                <div>
                    <label for="edit_new_password" class="block text-xs font-bold text-slate-700 mb-1">Reset Password (Optional)</label>
                    <input type="password" id="edit_new_password" name="new_password" placeholder="Leave blank to keep existing password"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm font-semibold transition bg-slate-50/50 focus:bg-white">
                </div>

                <div class="flex items-center gap-2.5 p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700">
                    <input type="checkbox" id="edit_is_active" name="is_active" value="1" class="w-4 h-4 rounded text-steel_azure">
                    <label for="edit_is_active" class="cursor-pointer">Account is Active & Authorized</label>
                </div>
            </div>

            <!-- Group 2: Customer Route Assignment (UI/UX Plain Language + Hick's Law) -->
            <div class="space-y-3.5 pt-2">
                <div class="flex items-center justify-between pb-2 border-b border-silver-600/70">
                    <div class="flex items-center gap-2 text-steel_azure font-extrabold text-xs uppercase tracking-wider">
                        <i class="fa-solid fa-route"></i>
                        <span>Customer Route Assignment</span>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-steel_azure/10 text-steel_azure">
                        Currently Managing: <strong id="edit_assigned_count">0</strong> Clients
                    </span>
                </div>

                <!-- Font Awesome Styled Route Action Cards -->
                <div class="space-y-2" id="edit_route_options_group">
                    <!-- Option 1: Keep Current Route -->
                    <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-white hover:border-steel_azure cursor-pointer transition has-[:checked]:border-steel_azure has-[:checked]:bg-blue-50/50 has-[:checked]:ring-1 has-[:checked]:ring-steel_azure">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="route_action" value="none" checked onchange="toggleEditRouteContainers()" class="w-4 h-4 text-steel_azure">
                            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600 flex-shrink-0">
                                <i class="fa-solid fa-shield-halved text-xs"></i>
                            </div>
                            <div>
                                <div class="text-xs font-extrabold text-slate-800">Keep Current Route</div>
                                <div class="text-[11px] text-slate-400">No client reassignments will be made</div>
                            </div>
                        </div>
                    </label>

                    <?php if ($unassignedCount > 0): ?>
                    <!-- Option 2: Assign All Unassigned Clients -->
                    <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-white hover:border-steel_azure cursor-pointer transition has-[:checked]:border-steel_azure has-[:checked]:bg-blue-50/50 has-[:checked]:ring-1 has-[:checked]:ring-steel_azure">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="route_action" value="assign_unassigned" onchange="toggleEditRouteContainers()" class="w-4 h-4 text-steel_azure">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-700 flex-shrink-0">
                                <i class="fa-solid fa-user-plus text-xs"></i>
                            </div>
                            <div>
                                <div class="text-xs font-extrabold text-slate-800">Assign All Unassigned Clients</div>
                                <div class="text-[11px] text-slate-400">Hand over all unassigned customers to this collector</div>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1 flex-shrink-0">
                            <i class="fa-solid fa-circle-check text-[9px]"></i>
                            <span>+<?= $unassignedCount ?> Available</span>
                        </span>
                    </label>

                    <!-- Option 3: Pick Specific Unassigned Clients -->
                    <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-white hover:border-steel_azure cursor-pointer transition has-[:checked]:border-steel_azure has-[:checked]:bg-blue-50/50 has-[:checked]:ring-1 has-[:checked]:ring-steel_azure">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="route_action" value="specific_clients" onchange="toggleEditRouteContainers()" class="w-4 h-4 text-steel_azure">
                            <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-700 flex-shrink-0">
                                <i class="fa-solid fa-list-check text-xs"></i>
                            </div>
                            <div>
                                <div class="text-xs font-extrabold text-slate-800">Pick Specific Clients to Assign</div>
                                <div class="text-[11px] text-slate-400">Select individual customers from the unassigned list</div>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 flex-shrink-0">
                            Custom Pick
                        </span>
                    </label>
                    <?php endif; ?>

                    <!-- Option 4: Transfer Route From Another Collector -->
                    <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-white hover:border-steel_azure cursor-pointer transition has-[:checked]:border-steel_azure has-[:checked]:bg-blue-50/50 has-[:checked]:ring-1 has-[:checked]:ring-steel_azure">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="route_action" value="transfer_from" onchange="toggleEditRouteContainers()" class="w-4 h-4 text-steel_azure">
                            <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center text-amber-700 flex-shrink-0">
                                <i class="fa-solid fa-arrow-right-arrow-left text-xs"></i>
                            </div>
                            <div>
                                <div class="text-xs font-extrabold text-slate-800">Transfer All Clients from Another Agent</div>
                                <div class="text-[11px] text-slate-400">Take over another collector's full route portfolio</div>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200 flex items-center gap-1 flex-shrink-0">
                            <i class="fa-solid fa-users text-[9px]"></i>
                            <span>Transfer</span>
                        </span>
                    </label>

                    <!-- Option 5: Unassign All Clients -->
                    <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-white hover:border-red-300 cursor-pointer transition has-[:checked]:border-red-500 has-[:checked]:bg-red-50/50 has-[:checked]:ring-1 has-[:checked]:ring-red-500">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="route_action" value="unassign_all" onchange="toggleEditRouteContainers()" class="w-4 h-4 text-red-600">
                            <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center text-red-600 flex-shrink-0">
                                <i class="fa-solid fa-user-xmark text-xs"></i>
                            </div>
                            <div>
                                <div class="text-xs font-extrabold text-red-700">Unassign All Clients from this Agent</div>
                                <div class="text-[11px] text-slate-400">Clear customer route without deactivating the account</div>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Transfer From Collector Dropdown -->
                <div id="edit_transfer_container" class="hidden p-3.5 bg-blue-50 border border-blue-200 rounded-xl space-y-2">
                    <label for="edit_transfer_from_col_id" class="block text-xs font-bold text-slate-800 flex items-center gap-1.5">
                        <i class="fa-solid fa-user-tag text-steel_azure text-xs"></i>
                        <span>Transfer Route From:</span>
                    </label>
                    <select id="edit_transfer_from_col_id" name="transfer_from_collector_id"
                            class="w-full px-3 py-2 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs font-semibold bg-white">
                        <option value="">-- Select Source Collector --</option>
                        <?php foreach ($collectors as $c): ?>
                            <option value="<?= $c['id'] ?>" class="edit-transfer-col-opt" data-id="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['full_name']) ?> (<?= $c['assigned_clients'] ?> clients currently)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-slate-500">All customers assigned to the selected collector will be moved to this collector's route.</p>
                </div>

                <!-- Specific Unassigned Clients Picker -->
                <?php if ($unassignedCount > 0): ?>
                <div id="edit_specific_container" class="hidden p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <label class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                            <i class="fa-solid fa-list-check text-steel_azure text-xs"></i>
                            <span>Select Unassigned Clients:</span>
                        </label>
                        <div class="flex items-center gap-1.5 text-[11px]">
                            <button type="button" onclick="selectAllEditClients(true)" class="text-steel_azure font-bold hover:underline">Select All</button>
                            <span class="text-slate-300">|</span>
                            <button type="button" onclick="selectAllEditClients(false)" class="text-slate-500 font-medium hover:underline">Deselect</button>
                        </div>
                    </div>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        <input type="text" id="edit_client_filter" oninput="filterEditClients()" placeholder="Filter by client name or account #..."
                               class="w-full pl-8 pr-3 py-1.5 rounded-lg border border-silver-600 text-xs outline-none bg-white">
                    </div>
                    <div class="max-h-44 overflow-y-auto divide-y divide-slate-200 border border-slate-200 rounded-lg bg-white p-1" id="edit_clients_list">
                        <?php foreach ($unassignedCustomers as $uc): ?>
                            <label class="edit-client-item flex items-center justify-between p-2 hover:bg-slate-50 rounded cursor-pointer text-xs"
                                   data-search="<?= strtolower($uc['full_name'] . ' ' . $uc['account_number'] . ' ' . ($uc['location'] ?? '')) ?>">
                                <div class="flex items-center gap-2 min-w-0">
                                    <input type="checkbox" name="selected_customer_ids[]" value="<?= $uc['id'] ?>" class="edit-client-checkbox w-4 h-4 rounded text-steel_azure">
                                    <span class="font-bold text-slate-800 truncate"><?= htmlspecialchars($uc['full_name']) ?></span>
                                    <span class="text-[11px] font-mono text-slate-400">#<?= htmlspecialchars($uc['account_number']) ?></span>
                                </div>
                                <?php if (!empty($uc['location'])): ?>
                                    <span class="text-[10px] text-slate-400 ml-2 truncate max-w-[120px]"><?= htmlspecialchars($uc['location']) ?></span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="pt-4 border-t border-silver-600 flex items-center justify-end gap-2.5 flex-shrink-0">
                <button type="button" onclick="closeEditModal()" class="btn-touch bg-white text-slate-600 border border-silver-600 hover:bg-slate-50 text-xs font-bold px-4 py-2.5 rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" class="btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white text-xs font-extrabold px-5 py-2.5 rounded-xl shadow-md transition flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk text-xs"></i>
                    <span>Save Changes & Route</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     MODAL 3: Deactivate & Reassign Clients
     ============================================================ -->
<div id="deactivate_modal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm hidden" role="dialog" aria-modal="true" aria-labelledby="deact_modal_title">
    <div class="bg-white rounded-2xl border border-silver-600 shadow-2xl max-w-lg w-full overflow-hidden transform transition-all scale-95 duration-200 my-auto" id="deact_modal_box">
        <!-- Header -->
        <div class="p-4 sm:p-5 bg-gradient-to-r from-red-600 to-rose-700 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-user-slash text-base"></i>
                </div>
                <div>
                    <h3 id="deact_modal_title" class="font-extrabold text-sm sm:text-base leading-tight">Deactivate Collector & Reassign Route</h3>
                    <p class="text-xs text-red-100 mt-0.5">Safely preserve logs and transfer client routes.</p>
                </div>
            </div>
            <button type="button" onclick="closeDeactivateModal()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer" title="Close" aria-label="Close modal">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="collectors.php" class="p-5 sm:p-6 space-y-4">
            <input type="hidden" name="action" value="deactivate_and_reassign">
            <input type="hidden" id="deact_collector_id" name="collector_id" value="">

            <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 leading-relaxed flex items-start gap-2.5">
                <i class="fa-solid fa-triangle-exclamation text-amber-600 mt-0.5 text-xs flex-shrink-0"></i>
                <div>
                    You are deactivating <strong id="deact_name" class="font-black text-slate-800">Collector</strong>. All past deposits, receipts, and handovers will be securely preserved.
                </div>
            </div>

            <div>
                <label for="new_collector_id" class="block text-xs font-bold text-slate-700 mb-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-users text-steel_azure text-xs"></i>
                    <span>Transfer <span id="deact_clients_count" class="font-extrabold text-steel_azure">0</span> Clients To:</span>
                </label>
                <select id="new_collector_id" name="new_collector_id"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm font-semibold transition bg-white">
                    <option value="">-- Leave Unassigned (Admin can assign later) --</option>
                    <?php foreach ($activeCollectors as $ac): ?>
                        <option value="<?= $ac['id'] ?>" class="collector-option-item" data-id="<?= $ac['id'] ?>">
                            <?= htmlspecialchars($ac['full_name']) ?> (<?= $ac['assigned_clients'] ?> clients currently)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Select an active collector to inherit this agent's client portfolio immediately.</p>
            </div>

            <div class="pt-4 border-t border-silver-600 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeDeactivateModal()" class="btn-touch bg-white text-slate-600 border border-silver-600 hover:bg-slate-50 text-xs font-bold px-4 py-2.5 rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" class="btn-touch bg-red-600 hover:bg-red-700 text-white text-xs font-extrabold px-5 py-2.5 rounded-xl shadow-md transition flex items-center gap-1.5">
                    <i class="fa-solid fa-user-slash text-xs"></i>
                    <span>Confirm Deactivation</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     MODAL 4: Reactivate Collector & Assign Route Modal
     ============================================================ -->
<div id="reactivate_modal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm hidden" role="dialog" aria-modal="true" aria-labelledby="reactivate_modal_title">
    <div class="bg-white rounded-2xl border border-silver-600 shadow-2xl max-w-lg w-full overflow-hidden transform transition-all scale-95 duration-200 my-auto max-h-[92vh] flex flex-col" id="reactivate_modal_box">
        <!-- Header -->
        <div class="p-4 sm:p-5 bg-gradient-to-r from-emerald-600 to-teal-700 text-white flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-user-check text-base"></i>
                </div>
                <div>
                    <h3 id="reactivate_modal_title" class="font-extrabold text-sm sm:text-base leading-tight">Reactivate Collector & Assign Route</h3>
                    <p class="text-xs text-emerald-100 mt-0.5">Restore field agent access and assign client portfolio.</p>
                </div>
            </div>
            <button type="button" onclick="closeReactivateModal()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer" title="Close" aria-label="Close modal">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="collectors.php" class="p-5 sm:p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="action" value="reactivate_collector">
            <input type="hidden" id="reactivate_collector_id" name="collector_id" value="">

            <!-- Collector Identity Review Card -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center text-sm flex-shrink-0 shadow-xs" id="reactivate_avatar">
                    --
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-extrabold text-slate-800 truncate" id="reactivate_name">-</div>
                    <div class="text-[11px] text-slate-500 font-medium truncate" id="reactivate_meta">-</div>
                </div>
                <span class="text-[10px] font-black text-emerald-700 bg-emerald-100 border border-emerald-200 px-2.5 py-1 rounded-lg flex items-center gap-1">
                    <i class="fa-solid fa-rotate-left text-[9px]"></i>
                    <span>Reactivating</span>
                </span>
            </div>

            <!-- Route & Client Assignment Section -->
            <div class="space-y-3 pt-2">
                <div class="flex items-center gap-2 pb-1.5 border-b border-silver-600/70 text-emerald-800 font-extrabold text-xs uppercase tracking-wider">
                    <i class="fa-solid fa-route"></i>
                    <span>Customer Route Assignment</span>
                </div>

                <!-- Font Awesome Styled Radio Cards -->
                <div class="space-y-2" id="reactivate_route_options_group">
                    <!-- Option 1: Keep Current Route -->
                    <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-white hover:border-emerald-600 cursor-pointer transition has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/40 has-[:checked]:ring-1 has-[:checked]:ring-emerald-600">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="reactivate_route_action" value="none" checked onchange="toggleReactivateRouteContainers()" class="w-4 h-4 text-emerald-600">
                            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600 flex-shrink-0">
                                <i class="fa-solid fa-shield-halved text-xs"></i>
                            </div>
                            <div>
                                <div class="text-xs font-extrabold text-slate-800">Keep Current Route</div>
                                <div class="text-[11px] text-slate-400">No client reassignments will be made</div>
                            </div>
                        </div>
                    </label>

                    <?php if ($unassignedCount > 0): ?>
                    <!-- Option 2: Assign All Unassigned Clients -->
                    <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-white hover:border-emerald-600 cursor-pointer transition has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/40 has-[:checked]:ring-1 has-[:checked]:ring-emerald-600">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="reactivate_route_action" value="assign_unassigned" onchange="toggleReactivateRouteContainers()" class="w-4 h-4 text-emerald-600">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-700 flex-shrink-0">
                                <i class="fa-solid fa-user-plus text-xs"></i>
                            </div>
                            <div>
                                <div class="text-xs font-extrabold text-slate-800">Assign All Unassigned Clients</div>
                                <div class="text-[11px] text-slate-400">Hand all <?= $unassignedCount ?> unassigned clients to this collector</div>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1 flex-shrink-0">
                            <i class="fa-solid fa-circle-check text-[9px]"></i>
                            <span>+<?= $unassignedCount ?> Available</span>
                        </span>
                    </label>

                    <!-- Option 3: Pick Specific Unassigned Clients -->
                    <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-white hover:border-emerald-600 cursor-pointer transition has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/40 has-[:checked]:ring-1 has-[:checked]:ring-emerald-600">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="reactivate_route_action" value="specific_clients" onchange="toggleReactivateRouteContainers()" class="w-4 h-4 text-emerald-600">
                            <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-700 flex-shrink-0">
                                <i class="fa-solid fa-list-check text-xs"></i>
                            </div>
                            <div>
                                <div class="text-xs font-extrabold text-slate-800">Pick Specific Clients to Assign</div>
                                <div class="text-[11px] text-slate-400">Select individual clients from unassigned list</div>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 flex-shrink-0">
                            Custom Pick
                        </span>
                    </label>
                    <?php endif; ?>

                    <!-- Option 4: Transfer Route From Another Collector -->
                    <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-white hover:border-emerald-600 cursor-pointer transition has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/40 has-[:checked]:ring-1 has-[:checked]:ring-emerald-600">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="reactivate_route_action" value="transfer_from" onchange="toggleReactivateRouteContainers()" class="w-4 h-4 text-emerald-600">
                            <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center text-amber-700 flex-shrink-0">
                                <i class="fa-solid fa-arrow-right-arrow-left text-xs"></i>
                            </div>
                            <div>
                                <div class="text-xs font-extrabold text-slate-800">Transfer All Clients from Another Agent</div>
                                <div class="text-[11px] text-slate-400">Take over another collector's client portfolio</div>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200 flex items-center gap-1 flex-shrink-0">
                            <i class="fa-solid fa-users text-[9px]"></i>
                            <span>Transfer</span>
                        </span>
                    </label>
                </div>

                <!-- Transfer From Dropdown -->
                <div id="reactivate_transfer_container" class="hidden p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl space-y-2">
                    <label for="reactivate_transfer_from_id" class="block text-xs font-bold text-emerald-950 flex items-center gap-1.5">
                        <i class="fa-solid fa-user-tag text-emerald-700 text-xs"></i>
                        <span>Transfer Route From:</span>
                    </label>
                    <select id="reactivate_transfer_from_id" name="reactivate_transfer_from_id"
                            class="w-full px-3 py-2 rounded-xl border border-silver-600 focus:border-emerald-600 outline-none text-xs font-semibold bg-white">
                        <option value="">-- Select Source Collector --</option>
                        <?php foreach ($collectors as $c): ?>
                            <option value="<?= $c['id'] ?>" class="reactivate-transfer-col-opt" data-id="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['full_name']) ?> (<?= $c['assigned_clients'] ?> clients currently)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-emerald-800">All clients of the selected agent will be transferred to this collector upon reactivation.</p>
                </div>

                <!-- Specific Unassigned Clients Picker -->
                <?php if ($unassignedCount > 0): ?>
                <div id="reactivate_specific_container" class="hidden p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <label class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                            <i class="fa-solid fa-list-check text-emerald-700 text-xs"></i>
                            <span>Select Unassigned Clients:</span>
                        </label>
                        <div class="flex items-center gap-1.5 text-[11px]">
                            <button type="button" onclick="selectAllReactivateClients(true)" class="text-emerald-700 font-bold hover:underline">Select All</button>
                            <span class="text-slate-300">|</span>
                            <button type="button" onclick="selectAllReactivateClients(false)" class="text-slate-500 font-medium hover:underline">Deselect</button>
                        </div>
                    </div>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        <input type="text" id="reactivate_client_filter" oninput="filterReactivateClients()" placeholder="Filter by client name or account #..."
                               class="w-full pl-8 pr-3 py-1.5 rounded-lg border border-silver-600 text-xs outline-none bg-white">
                    </div>
                    <div class="max-h-44 overflow-y-auto divide-y divide-slate-200 border border-slate-200 rounded-lg bg-white p-1" id="reactivate_clients_list">
                        <?php foreach ($unassignedCustomers as $uc): ?>
                            <label class="reactivate-client-item flex items-center justify-between p-2 hover:bg-slate-50 rounded cursor-pointer text-xs"
                                   data-search="<?= strtolower($uc['full_name'] . ' ' . $uc['account_number'] . ' ' . ($uc['location'] ?? '')) ?>">
                                <div class="flex items-center gap-2 min-w-0">
                                    <input type="checkbox" name="reactivate_selected_customer_ids[]" value="<?= $uc['id'] ?>" class="reactivate-client-checkbox w-4 h-4 rounded text-emerald-600">
                                    <span class="font-bold text-slate-800 truncate"><?= htmlspecialchars($uc['full_name']) ?></span>
                                    <span class="text-[11px] font-mono text-slate-400">#<?= htmlspecialchars($uc['account_number']) ?></span>
                                </div>
                                <?php if (!empty($uc['location'])): ?>
                                    <span class="text-[10px] text-slate-400 ml-2 truncate max-w-[120px]"><?= htmlspecialchars($uc['location']) ?></span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-900 leading-relaxed flex items-start gap-2.5">
                <i class="fa-solid fa-circle-info text-emerald-600 mt-0.5 text-xs flex-shrink-0"></i>
                <span>Reactivating will restore mobile app sign-in capability and apply any route assignments configured above.</span>
            </div>

            <!-- Form Actions -->
            <div class="pt-3 border-t border-silver-600 flex items-center gap-3 flex-shrink-0">
                <button type="button" onclick="closeReactivateModal()"
                        class="flex-1 btn-touch px-4 py-2.5 bg-white text-slate-700 border border-silver-600 hover:bg-slate-50 text-xs font-bold rounded-xl transition flex items-center justify-center">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 btn-touch px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold rounded-xl shadow-xs transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-rotate-left text-xs"></i>
                    <span>Yes, Reactivate Collector</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Helper to open/close modal with scale animation
function showModal(modalId, boxId) {
    const modal = document.getElementById(modalId);
    const box   = document.getElementById(boxId);
    if (!modal) return;
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        if (box) {
            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }
    });
}

function hideModal(modalId, boxId) {
    const modal = document.getElementById(modalId);
    const box   = document.getElementById(boxId);
    if (!modal) return;
    if (box) {
        box.classList.remove('scale-100');
        box.classList.add('scale-95');
    }
    setTimeout(() => modal.classList.add('hidden'), 150);
}

function openAddModal() {
    showModal('add_modal', 'add_modal_box');
}
function closeAddModal() {
    hideModal('add_modal', 'add_modal_box');
}

function openEditModal(collector) {
    document.getElementById('edit_collector_id').value = collector.id;
    document.getElementById('edit_full_name').value = collector.full_name;
    document.getElementById('edit_phone').value = collector.phone || '';
    document.getElementById('edit_username').value = collector.username;
    document.getElementById('edit_new_password').value = '';
    document.getElementById('edit_is_active').checked = parseInt(collector.is_active) === 1;
    document.getElementById('edit_assigned_count').textContent = collector.assigned_clients || '0';

    // Reset radio selection to 'none'
    const defaultRadio = document.querySelector('input[name="route_action"][value="none"]');
    if (defaultRadio) {
        defaultRadio.checked = true;
        toggleEditRouteContainers();
    }

    // Hide self from transfer options
    document.querySelectorAll('.edit-transfer-col-opt').forEach(opt => {
        opt.style.display = (opt.getAttribute('data-id') == collector.id) ? 'none' : '';
    });

    showModal('edit_modal', 'edit_modal_box');
}
function closeEditModal() {
    hideModal('edit_modal', 'edit_modal_box');
}

function toggleEditRouteContainers() {
    const action = document.querySelector('input[name="route_action"]:checked')?.value || 'none';
    const transContainer = document.getElementById('edit_transfer_container');
    const specContainer = document.getElementById('edit_specific_container');

    if (transContainer) transContainer.classList.toggle('hidden', action !== 'transfer_from');
    if (specContainer) specContainer.classList.toggle('hidden', action !== 'specific_clients');
}

function filterEditClients() {
    const q = document.getElementById('edit_client_filter')?.value.toLowerCase().trim() || '';
    document.querySelectorAll('.edit-client-item').forEach(item => {
        const text = item.getAttribute('data-search') || '';
        item.style.display = text.includes(q) ? '' : 'none';
    });
}

function selectAllEditClients(checked) {
    document.querySelectorAll('.edit-client-checkbox').forEach(cb => {
        if (cb.closest('.edit-client-item').style.display !== 'none') {
            cb.checked = checked;
        }
    });
}

function openDeactivateModal(collector) {
    document.getElementById('deact_collector_id').value = collector.id;
    document.getElementById('deact_name').textContent = collector.full_name;
    document.getElementById('deact_clients_count').textContent = collector.assigned_clients;

    // Hide self from reassignment options
    document.querySelectorAll('.collector-option-item').forEach(opt => {
        opt.style.display = (opt.getAttribute('data-id') == collector.id) ? 'none' : '';
    });

    showModal('deactivate_modal', 'deact_modal_box');
}
function closeDeactivateModal() {
    hideModal('deactivate_modal', 'deact_modal_box');
}

function openReactivateModal(collector) {
    document.getElementById('reactivate_collector_id').value = collector.id;
    document.getElementById('reactivate_name').textContent = collector.full_name;
    document.getElementById('reactivate_meta').textContent = '@' + collector.username + (collector.phone ? ' • ' + collector.phone : '');
    
    const initials = collector.full_name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
    document.getElementById('reactivate_avatar').textContent = initials || 'CO';

    // Reset radio selection to 'none'
    const defaultRadio = document.querySelector('input[name="reactivate_route_action"][value="none"]');
    if (defaultRadio) {
        defaultRadio.checked = true;
        toggleReactivateRouteContainers();
    }

    // Hide self from transfer options
    document.querySelectorAll('.reactivate-transfer-col-opt').forEach(opt => {
        opt.style.display = (opt.getAttribute('data-id') == collector.id) ? 'none' : '';
    });

    showModal('reactivate_modal', 'reactivate_modal_box');
}
function closeReactivateModal() {
    hideModal('reactivate_modal', 'reactivate_modal_box');
}

function toggleReactivateRouteContainers() {
    const action = document.querySelector('input[name="reactivate_route_action"]:checked')?.value || 'none';
    const transContainer = document.getElementById('reactivate_transfer_container');
    const specContainer = document.getElementById('reactivate_specific_container');

    if (transContainer) transContainer.classList.toggle('hidden', action !== 'transfer_from');
    if (specContainer) specContainer.classList.toggle('hidden', action !== 'specific_clients');
}

function filterReactivateClients() {
    const q = document.getElementById('reactivate_client_filter')?.value.toLowerCase().trim() || '';
    document.querySelectorAll('.reactivate-client-item').forEach(item => {
        const text = item.getAttribute('data-search') || '';
        item.style.display = text.includes(q) ? '' : 'none';
    });
}

function selectAllReactivateClients(checked) {
    document.querySelectorAll('.reactivate-client-checkbox').forEach(cb => {
        if (cb.closest('.reactivate-client-item').style.display !== 'none') {
            cb.checked = checked;
        }
    });
}

// Global modal esc and backdrop click listeners
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddModal();
        closeEditModal();
        closeDeactivateModal();
        closeReactivateModal();
    }
});

['add_modal', 'edit_modal', 'deactivate_modal', 'reactivate_modal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('click', function(e) {
            if (e.target === this) {
                if (id === 'add_modal') closeAddModal();
                else if (id === 'edit_modal') closeEditModal();
                else if (id === 'deactivate_modal') closeDeactivateModal();
                else if (id === 'reactivate_modal') closeReactivateModal();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
