<?php
// daily_handover.php - End-of-Day Collector Cash Settlement
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = get_logged_in_user();
$pdo = get_db_connection();
$error = '';

// Handle Admin Approval of a Handover
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_handover') {
    require_admin();

    $handoverId = (int)($_POST['handover_id'] ?? 0);
    $adminNote = trim($_POST['admin_note'] ?? 'Cash verified and accepted.');

    if ($handoverId > 0) {
        $stmtH = $pdo->prepare("SELECT * FROM daily_handovers WHERE id = ?");
        $stmtH->execute([$handoverId]);
        $handover = $stmtH->fetch();

        if ($handover && $handover['status'] === 'submitted') {
            try {
                $pdo->beginTransaction();

                // 1. Mark handover as approved
                $stmtApprove = $pdo->prepare("
                    UPDATE daily_handovers 
                    SET status = 'approved', approved_by = ?, admin_note = ?, approved_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmtApprove->execute([$user['id'], $adminNote, $handoverId]);

                // 2. Link all unhanded deposits of this collector to this handover ID
                $stmtLink = $pdo->prepare("
                    UPDATE deposits 
                    SET handover_id = ? 
                    WHERE collector_id = ? AND handover_id IS NULL
                ");
                $stmtLink->execute([$handoverId, $handover['collector_id']]);

                // 3. Notify Collector
                create_notification(
                    $handover['collector_id'],
                    'handover_approved',
                    "Cash Handover Approved",
                    "Your daily cash handover #{$handoverId} of " . format_money($handover['cash_received']) . " was approved. Liability cleared.",
                    "daily_handover.php"
                );

                $pdo->commit();
                set_flash_message('success', "Daily cash handover #{$handoverId} approved! Collector's cash in hand liability cleared.");
                header('Location: daily_handover.php');
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error approving handover: ' . $e->getMessage();
            }
        }
    }
}

// Handle Admin Rejection of a Handover
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_handover') {
    require_admin();

    $handoverId = (int)($_POST['handover_id'] ?? 0);
    $rejectionReason = trim($_POST['rejection_reason'] ?? '');

    if ($handoverId <= 0) {
        $error = 'Invalid handover ID.';
    } elseif (empty($rejectionReason)) {
        $error = 'A rejection reason is required to reject a handover.';
    } else {
        $stmtH = $pdo->prepare("SELECT * FROM daily_handovers WHERE id = ?");
        $stmtH->execute([$handoverId]);
        $handover = $stmtH->fetch();

        if ($handover && $handover['status'] === 'submitted') {
            try {
                $pdo->beginTransaction();

                // 1. Mark handover as rejected
                $stmtReject = $pdo->prepare("
                    UPDATE daily_handovers 
                    SET status = 'rejected', approved_by = ?, admin_note = ?, approved_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmtReject->execute([$user['id'], $rejectionReason, $handoverId]);

                // 2. Notify Collector
                create_notification(
                    $handover['collector_id'],
                    'handover_rejected',
                    "Cash Handover #{$handoverId} Rejected",
                    "Your cash handover of " . format_money($handover['cash_received']) . " was rejected by Admin ({$user['full_name']}). Reason: {$rejectionReason}. Cash deposits have been released back to your bag for re-submission.",
                    "daily_handover.php"
                );

                // 3. Log Audit Event
                log_audit_event($user['id'], 'handover_rejected', "Handover #{$handoverId} rejected for user ID {$handover['collector_id']}. Reason: {$rejectionReason}");

                $pdo->commit();
                set_flash_message('success', "Daily cash handover #{$handoverId} rejected. Deposits have been released back for re-submission.");
                header('Location: daily_handover.php');
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error rejecting handover: ' . $e->getMessage();
            }
        } else {
            $error = 'Handover not found or not awaiting verification.';
        }
    }
}

// Handle Collector (or Admin) Submitting a Handover
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_handover') {
    $collectorId = $user['role'] === 'collector' ? $user['id'] : (int)($_POST['collector_id'] ?? 0);
    $physicalCash = (float)($_POST['physical_cash'] ?? 0);
    $collectorNote = trim($_POST['collector_note'] ?? '');
    $handoverDate = !empty($_POST['handover_date']) ? $_POST['handover_date'] : date('Y-m-d');

    // Duplicate Check: ensure no pending submission exists for this user
    $stmtCheckPending = $pdo->prepare("SELECT id FROM daily_handovers WHERE collector_id = ? AND status = 'submitted' LIMIT 1");
    $stmtCheckPending->execute([$collectorId]);
    $existingPendingRow = $stmtCheckPending->fetch();

    if ($existingPendingRow) {
        $error = "A cash handover (#{$existingPendingRow['id']}) is already pending verification for this person. Please verify or reject the pending handover before submitting a new one.";
    } else {
        // Calculate current expected cash in hand
        $expectedCash = get_collector_cash_in_hand($collectorId);

        if ($expectedCash <= 0 && $physicalCash <= 0) {
            $error = 'No unsettled cash in hand to handover.';
        } else {
        $variance = round($physicalCash - $expectedCash, 2);
        $status = 'submitted';

        try {
            $pdo->beginTransaction();

            $stmtInsert = $pdo->prepare("
                INSERT INTO daily_handovers (collector_id, handover_date, expected_cash, cash_received, difference, status, collector_note) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([$collectorId, $handoverDate, $expectedCash, $physicalCash, $variance, $status, $collectorNote]);
            $handoverId = $pdo->lastInsertId();

            // If an Admin submitted and approved directly on the spot
            if ($user['role'] === 'admin' && isset($_POST['auto_approve']) && $_POST['auto_approve'] == '1') {
                $stmtApprove = $pdo->prepare("
                    UPDATE daily_handovers 
                    SET status = 'approved', approved_by = ?, admin_note = 'Immediate Admin handover', approved_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmtApprove->execute([$user['id'], $handoverId]);

                $stmtLink = $pdo->prepare("UPDATE deposits SET handover_id = ? WHERE collector_id = ? AND handover_id IS NULL");
                $stmtLink->execute([$handoverId, $collectorId]);
            }

            // Notify Admins
            $collectorName = $user['role'] === 'collector' ? $user['full_name'] : "Collector";
            $notifTitle = "Daily Cash Handover Submitted";
            $notifMsg = "{$collectorName} submitted cash handover of " . format_money($physicalCash) . " (Expected: " . format_money($expectedCash) . ").";
            if ($variance != 0) {
                $notifMsg .= ($variance < 0 ? " ⚠️ Shortage: " . format_money(abs($variance)) : " ℹ️ Overage: +" . format_money($variance));
            }
            create_notification(null, 'handover_submitted', $notifTitle, $notifMsg, "daily_handover.php");

            $pdo->commit();

            set_flash_message('success', 'Daily cash handover submitted successfully! Expected: ' . format_money($expectedCash) . ', Tendered: ' . format_money($physicalCash));
            header('Location: daily_handover.php');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Error submitting handover: ' . $e->getMessage();
        }
    }
}
}

// Fetch Pending Handovers
$stmtPending = $pdo->query("
    SELECT h.*, u.full_name as collector_name, u.phone as collector_phone
    FROM daily_handovers h
    JOIN users u ON h.collector_id = u.id
    WHERE h.status = 'submitted'
    ORDER BY h.id DESC
");
$pendingHandovers = $stmtPending->fetchAll();

// Handle Sorting for Settlement History
$sort = $_GET['sort'] ?? 'date';
$dir = strtolower($_GET['dir'] ?? 'desc');
$dir = in_array($dir, ['asc', 'desc']) ? $dir : 'desc';

$sortColumns = [
    'date'       => 'h.handover_date',
    'collector'  => 'u.full_name',
    'expected'   => 'h.expected_cash',
    'received'   => 'h.cash_received',
    'difference' => 'h.difference',
    'verified'   => 'admin.full_name'
];

$orderByColumn = $sortColumns[$sort] ?? 'h.handover_date';
$orderBySQL = "{$orderByColumn} {$dir}, h.id DESC";

// Helper functions for column sorting
if (!function_exists('getHandoverSortUrl')) {
    function getHandoverSortUrl($column, $currentSort, $currentDir) {
        $params = $_GET;
        $params['sort'] = $column;
        $params['dir'] = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
        $params['page'] = 1;
        return 'daily_handover.php?' . http_build_query($params);
    }
}

if (!function_exists('renderHandoverSortIcon')) {
    function renderHandoverSortIcon($column, $currentSort, $currentDir) {
        if ($currentSort !== $column) {
            return '<i class="fa-solid fa-sort text-slate-300 ml-1 text-[10px] group-hover:text-slate-500"></i>';
        }
        return $currentDir === 'asc' 
            ? '<i class="fa-solid fa-arrow-up-a-z text-steel_azure ml-1 text-xs"></i>' 
            : '<i class="fa-solid fa-arrow-down-z-a text-steel_azure ml-1 text-xs"></i>';
    }
}

// Fetch Handover History with pagination and sorting
$stmtHistory = $pdo->query("
    SELECT h.*, u.full_name as collector_name, admin.full_name as approved_by_name
    FROM daily_handovers h
    JOIN users u ON h.collector_id = u.id
    LEFT JOIN users admin ON h.approved_by = admin.id
    WHERE h.status IN ('approved', 'has_difference', 'rejected')
    ORDER BY {$orderBySQL}
");
$allHistoryHandovers = $stmtHistory->fetchAll();
$historyPage = max(1, (int)($_GET['page'] ?? 1));
$pagedHistory = paginate_array($allHistoryHandovers, 10, $historyPage);
$historyHandovers = $pagedHistory['items'];

// Calculate current user's or selected collector's Cash in Hand
$myCashInHand = get_collector_cash_in_hand($user['id']);

// Fetch all staff (collectors and admins) who have active collections or unhanded cash
$stmtStaffList = $pdo->query("
    SELECT u.id, u.full_name, u.role, u.phone,
           COALESCE((SELECT SUM(amount) FROM deposits WHERE collector_id = u.id AND handover_id IS NULL), 0.00) as cash_in_hand,
           COALESCE((SELECT SUM(amount) FROM deposits WHERE collector_id = u.id AND deposit_date = CURRENT_DATE), 0.00) as today_collected,
           (SELECT COUNT(*) FROM deposits WHERE collector_id = u.id AND handover_id IS NULL) as unsettled_count
    FROM users u
    WHERE u.is_active = 1
      AND (u.role = 'collector' OR (SELECT COUNT(*) FROM deposits WHERE collector_id = u.id AND handover_id IS NULL) > 0 OR (SELECT COUNT(*) FROM deposits WHERE collector_id = u.id AND deposit_date = CURRENT_DATE) > 0)
    ORDER BY (cash_in_hand > 0) DESC, u.role ASC, u.full_name ASC
");
$staffList = $stmtStaffList->fetchAll(PDO::FETCH_ASSOC);

// Target collector selection
$targetCollectorId = null;
if ($user['role'] === 'collector') {
    $targetCollectorId = $user['id'];
} elseif ($user['role'] === 'admin') {
    if (isset($_GET['collector_id'])) {
        $targetCollectorId = (int)$_GET['collector_id'];
    } elseif ($myCashInHand > 0) {
        $targetCollectorId = $user['id']; // Auto-select admin if holding cash!
    } elseif (!empty($staffList) && $staffList[0]['cash_in_hand'] > 0) {
        $targetCollectorId = (int)$staffList[0]['id'];
    } elseif (!empty($staffList)) {
        $targetCollectorId = (int)$staffList[0]['id'];
    }
}

$targetUser = null;
$userActivePending = null;
if ($targetCollectorId) {
    $stmtT = $pdo->prepare("SELECT id, full_name, role, phone FROM users WHERE id = ?");
    $stmtT->execute([$targetCollectorId]);
    $targetUser = $stmtT->fetch(PDO::FETCH_ASSOC);

    // Check if target user has an active pending handover in queue
    $stmtPendingCheck = $pdo->prepare("
        SELECT id, expected_cash, cash_received, difference, submitted_at, collector_note 
        FROM daily_handovers 
        WHERE collector_id = ? AND status = 'submitted' 
        LIMIT 1
    ");
    $stmtPendingCheck->execute([$targetCollectorId]);
    $userActivePending = $stmtPendingCheck->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = "Daily Cash Handover";
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 section-card">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-steel_azure">Daily Cash Handover</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Check physical cash brought by collectors against the system record.</p>
        </div>
        <div>
            <a href="<?= $user['role'] === 'admin' ? 'admin_dashboard.php' : 'collector_dashboard.php' ?>" class="text-xs font-bold text-cornflower_ocean hover:text-steel_azure inline-flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left text-xs"></i>
                <span>Back to Home</span>
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3.5 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-red-500 text-sm"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Admin Staff & Office Cash Bags Selector -->
    <?php if ($user['role'] === 'admin' && !empty($staffList)): ?>
        <div class="bg-white rounded-2xl border-2 border-silver-600 shadow-sm p-4 sm:p-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3.5">
                <div>
                    <h2 class="text-sm sm:text-base font-black text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-wallet text-steel_azure"></i>
                        <span>Staff & Office Cash Bags</span>
                    </h2>
                    <p class="text-xs text-slate-500">Click any staff member or admin account to receive their cash or check their bag.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php foreach ($staffList as $s): ?>
                    <?php 
                        $isSelected = ($targetCollectorId === (int)$s['id']); 
                        $hasCash = ($s['cash_in_hand'] > 0);
                    ?>
                    <a href="daily_handover.php?collector_id=<?= $s['id'] ?>" 
                       class="p-3.5 rounded-xl border-2 transition flex items-center justify-between gap-3 <?= $isSelected ? 'border-steel_azure bg-blue-50/60 ring-2 ring-steel_azure/20' : ($hasCash ? 'border-amber-300 bg-amber-50/40 hover:border-amber-400' : 'border-silver-600 hover:border-slate-400 bg-white') ?>">
                        <div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="font-bold text-xs sm:text-sm text-slate-800"><?= htmlspecialchars($s['full_name']) ?></span>
                                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded <?= $s['role'] === 'admin' ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-blue-100 text-steel_azure border border-blue-200' ?>">
                                    <?= $s['role'] === 'admin' ? 'Office / Admin' : 'Collector' ?>
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500 mt-1">
                                <?= $s['unsettled_count'] ?> unhanded deposit<?= $s['unsettled_count'] == 1 ? '' : 's' ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs sm:text-sm font-black <?= $hasCash ? 'text-pumpkin_spice' : 'text-slate-400' ?>">
                                <?= format_money($s['cash_in_hand']) ?>
                            </div>
                            <span class="text-[10px] font-bold <?= $hasCash ? 'text-pumpkin_spice' : 'text-emerald-600' ?>">
                                <?= $hasCash ? '● Needs Handover' : '✓ All Settled' ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Collector / Admin Handover Submission Box -->
    <?php if ($targetCollectorId && ($user['role'] === 'collector' || $user['role'] === 'admin')): ?>
        <?php 
            $targetExpectedCash = get_collector_cash_in_hand($targetCollectorId);
        ?>

        <?php if ($userActivePending): ?>
            <!-- Safeguard: Active Pending Handover Alert (Blocks Duplicate Submissions) -->
            <div class="bg-amber-50 border-2 border-amber-300 rounded-2xl p-6 max-w-2xl mx-auto shadow-sm">
                <div class="flex items-start gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-amber-200 text-amber-900 flex items-center justify-center shrink-0 text-xl font-bold shadow-2xs">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <h3 class="text-base font-extrabold text-amber-950">
                                Handover #<?= $userActivePending['id'] ?> Pending Verification
                            </h3>
                            <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-amber-200 text-amber-900 border border-amber-300">
                                In Queue
                            </span>
                        </div>
                        <p class="text-xs text-amber-900/80 mt-1">
                            <strong><?= htmlspecialchars($targetUser['full_name'] ?? 'This user') ?></strong> already has an unverified cash handover submitted on <strong><?= date('d M Y, h:i A', strtotime($userActivePending['submitted_at'])) ?></strong>.
                        </p>
                        <p class="text-[11px] text-amber-800 mt-1 font-medium">
                            To maintain financial integrity and avoid duplicate counting, a new handover cannot be created until this pending submission is either verified or rejected.
                        </p>

                        <!-- Breakdown Box -->
                        <div class="mt-3.5 grid grid-cols-1 sm:grid-cols-2 gap-2.5 bg-white/90 p-3.5 rounded-xl border border-amber-200 text-xs">
                            <div>
                                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">Expected System Cash</span>
                                <div class="text-sm font-black text-slate-800 mt-0.5"><?= format_money($userActivePending['expected_cash']) ?></div>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">Physical Tendered Cash</span>
                                <div class="text-sm font-black text-steel_azure mt-0.5"><?= format_money($userActivePending['cash_received']) ?></div>
                            </div>
                        </div>

                        <?php if ($user['role'] === 'admin'): ?>
                            <div class="mt-4 flex items-center gap-2 flex-wrap">
                                <a href="#pending-handover-<?= $userActivePending['id'] ?>" class="btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white font-bold text-xs px-3.5 py-2 rounded-xl transition inline-flex items-center gap-1.5 shadow-2xs">
                                    <i class="fa-solid fa-arrow-down text-[10px]"></i>
                                    <span>Review Handover #<?= $userActivePending['id'] ?> Below</span>
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-[11px] text-amber-900 font-semibold mt-3 flex items-center gap-1">
                                <i class="fa-solid fa-circle-info text-xs"></i>
                                <span>Please bring your physical cash bag to the office for verification.</span>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl border-2 border-silver-600 shadow-md p-6 max-w-2xl mx-auto">
                <div class="flex items-center justify-between gap-2 mb-1 flex-wrap">
                    <h2 class="text-base font-bold text-slate-800">
                        <?= ($targetUser && $targetUser['role'] === 'admin') ? 'Settle Direct Office Collections (Admin)' : 'Submit End-of-Day Cash Handover' ?>
                    </h2>
                    <?php if ($targetUser): ?>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $targetUser['role'] === 'admin' ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-blue-100 text-steel_azure border border-blue-200' ?>">
                            <?= htmlspecialchars($targetUser['full_name']) ?> (<?= ucfirst($targetUser['role']) ?>)
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-slate-500 mb-4">
                    <?= ($targetUser && $targetUser['role'] === 'admin') ? 'Verify and clear physical cash collected directly in the office into the vault.' : 'Enter the physical cash counted in the field bag to hand over to the office.' ?>
                </p>

                <!-- Expected Cash Badge -->
                <div class="bg-platinum-800 p-4 rounded-xl border border-silver-600 mb-5 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">System Expected Cash in Hand</span>
                        <div class="text-2xl font-black text-steel_azure mt-0.5"><?= format_money($targetExpectedCash) ?></div>
                        <span class="text-[11px] text-slate-400">Total unhanded deposits under this user</span>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-cornflower_ocean-900 text-steel_azure flex items-center justify-center text-xl font-black">
                        ₵
                    </div>
                </div>

                <form method="POST" action="daily_handover.php" class="space-y-4">
                    <input type="hidden" name="action" value="submit_handover">
                    <input type="hidden" name="collector_id" value="<?= $targetCollectorId ?>">

                    <div>
                        <label for="physical_cash" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Physical Cash Handed Over *
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 font-extrabold text-sm">GH₵</span>
                            <input type="number" step="0.50" min="0" id="physical_cash" name="physical_cash" required
                                   value="<?= number_format($targetExpectedCash, 2, '.', '') ?>"
                                   class="w-full pl-14 pr-4 py-3 rounded-xl border-2 border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none text-base font-black text-slate-800 transition">
                        </div>
                        <p class="helper-text">Count the physical cash in the bag/register and enter the exact amount.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="handover_date" class="block text-xs font-bold text-slate-700 mb-1">Handover Date</label>
                            <input type="date" id="handover_date" name="handover_date" value="<?= date('Y-m-d') ?>" required
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm text-slate-700 transition">
                        </div>

                        <div>
                            <label for="collector_note" class="block text-xs font-bold text-slate-700 mb-1">Notes / Discrepancy Note</label>
                            <input type="text" id="collector_note" name="collector_note" 
                                   placeholder="<?= ($targetUser && $targetUser['role'] === 'admin') ? 'e.g. Office collections settlement' : 'e.g. End of day bag handover' ?>"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm text-slate-700 transition">
                        </div>
                    </div>

                    <?php if ($user['role'] === 'admin'): ?>
                        <div class="flex items-center gap-2 p-2.5 bg-platinum rounded-lg text-xs font-semibold text-slate-700">
                            <input type="checkbox" id="auto_approve" name="auto_approve" value="1" checked class="w-4 h-4 rounded text-steel_azure">
                            <label for="auto_approve">Automatically verify and approve settlement immediately</label>
                        </div>
                    <?php endif; ?>

                    <!-- Submit Action Button -->
                    <div class="pt-4 border-t border-silver-600/60">
                        <button type="submit" class="w-full btn-action-primary bg-steel_azure hover:bg-steel_azure-400 text-white font-extrabold text-base tracking-wide shadow-md transition">
                            <?= ($targetUser && $targetUser['role'] === 'admin') ? '✓ Clear & Settle Office Cash Immediately' : '✓ Submit Cash Handover for Verification' ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Admin Review of Pending Handovers -->
    <?php if ($user['role'] === 'admin'): ?>
        <div class="bg-white rounded-2xl border-2 border-silver-600 shadow-sm overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-silver-600 flex items-center justify-between bg-platinum-800">
                <div>
                    <h2 class="text-base font-black text-slate-800 flex items-center gap-2">
                        <span>Handovers Awaiting Admin Count & Verification</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= count($pendingHandovers) > 0 ? 'bg-pumpkin_spice text-white' : 'bg-platinum text-slate-500' ?>">
                            <?= count($pendingHandovers) ?>
                        </span>
                    </h2>
                    <p class="text-xs text-slate-500">Count physical cash tendered and approve to clear collector liability.</p>
                </div>
            </div>

            <div class="divide-y divide-silver-600/60">
                <?php if (empty($pendingHandovers)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon bg-emerald-50 text-emerald-600">
                            <i class="fa-solid fa-circle-check text-3xl"></i>
                        </div>
                        <div class="empty-state-title">All Clear</div>
                        <div class="empty-state-text">No handovers currently waiting for verification.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($pendingHandovers as $h): ?>
                        <div id="pending-handover-<?= $h['id'] ?>" class="p-4 sm:p-5 hover:bg-platinum-900 transition flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-black text-sm sm:text-base text-slate-800"><?= htmlspecialchars($h['collector_name']) ?></span>
                                    <span class="text-xs text-slate-500 flex items-center gap-1">
                                        <i class="fa-solid fa-phone text-[10px] text-slate-400"></i>
                                        <?= htmlspecialchars($h['collector_phone']) ?>
                                    </span>
                                    <span class="text-xs text-slate-400">&bull; <?= date('d M Y, h:i A', strtotime($h['submitted_at'])) ?></span>
                                    <span class="text-[10px] font-extrabold px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 border border-slate-200">#<?= $h['id'] ?></span>
                                </div>

                                <!-- Variance Status Indicator (Color-coded) -->
                                <div class="mt-2.5 flex flex-wrap items-center gap-2 text-xs">
                                    <span class="bg-platinum px-3 py-1 rounded-md text-slate-700">
                                        System Expected: <strong><?= format_money($h['expected_cash']) ?></strong>
                                    </span>
                                    <span class="bg-blue-50 text-steel_azure border border-blue-200 px-3 py-1 rounded-md font-bold">
                                        Physical Tendered: <strong><?= format_money($h['cash_received']) ?></strong>
                                    </span>
                                    
                                    <?php if ($h['difference'] == 0): ?>
                                        <span class="bg-emerald-50 text-emerald-800 border border-emerald-300 px-2.5 py-1 rounded-md font-bold inline-flex items-center gap-1">
                                            <i class="fa-solid fa-check text-xs"></i>
                                            <span>Exact Match (GH₵ 0.00)</span>
                                        </span>
                                    <?php elseif ($h['difference'] < 0): ?>
                                        <span class="bg-red-50 text-red-700 border border-red-300 px-2.5 py-1 rounded-md font-bold inline-flex items-center gap-1">
                                            <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                                            <span>Shortage: <?= format_money(abs($h['difference'])) ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-amber-50 text-pumpkin_spice border border-amber-300 px-2.5 py-1 rounded-md font-bold inline-flex items-center gap-1">
                                            <i class="fa-solid fa-circle-info text-xs"></i>
                                            <span>Overage: +<?= format_money($h['difference']) ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($h['collector_note'])): ?>
                                    <div class="text-xs text-slate-500 mt-2">
                                        Note: <em>"<?= htmlspecialchars($h['collector_note']) ?>"</em>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Verification & Rejection Actions -->
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                <?php if ((float)$h['difference'] == 0): ?>
                                    <!-- Exact Match: Fast 1-Click Approval Form -->
                                    <form method="POST" action="daily_handover.php" class="m-0 flex items-center">
                                        <input type="hidden" name="action" value="approve_handover">
                                        <input type="hidden" name="handover_id" value="<?= $h['id'] ?>">
                                        <input type="hidden" name="admin_note" value="Cash counted and verified (Exact Match).">
                                        <button type="submit" 
                                                class="btn-touch bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black px-4 py-2 shadow-2xs rounded-xl transition whitespace-nowrap inline-flex items-center gap-1.5">
                                            <i class="fa-solid fa-circle-check text-xs"></i>
                                            <span>Confirm & Clear Liability</span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Discrepancy: Triggers High-Visibility Variance Confirmation Modal -->
                                    <button type="button" 
                                            onclick="openVarianceModal(<?= htmlspecialchars(json_encode([
                                                'id' => $h['id'],
                                                'collector_name' => $h['collector_name'],
                                                'expected_cash' => (float)$h['expected_cash'],
                                                'cash_received' => (float)$h['cash_received'],
                                                'difference' => (float)$h['difference'],
                                                'collector_note' => $h['collector_note'] ?? '',
                                                'submitted_at' => date('d M Y, h:i A', strtotime($h['submitted_at']))
                                            ]), ENT_QUOTES, 'UTF-8') ?>)"
                                            class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-black px-4 py-2 shadow-2xs rounded-xl transition whitespace-nowrap inline-flex items-center gap-1.5">
                                        <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                                        <span>Review & Confirm Variance</span>
                                    </button>
                                <?php endif; ?>

                                <!-- Secondary Action (Hick's Law): Reject Handover -->
                                <button type="button" 
                                        onclick="openRejectModal(<?= $h['id'] ?>, '<?= htmlspecialchars($h['collector_name'], ENT_QUOTES) ?>', <?= (float)$h['cash_received'] ?>)"
                                        class="btn-touch bg-white border border-rose-300 text-rose-600 hover:bg-rose-50 hover:border-rose-400 text-xs font-bold px-3 py-2 rounded-xl transition whitespace-nowrap inline-flex items-center gap-1">
                                    <i class="fa-solid fa-xmark text-xs"></i>
                                    <span>Reject</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Historical Settled Handovers Table -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-silver-600/70 flex items-center gap-3">
            <div class="section-heading-icon bg-emerald-50 text-emerald-600">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <div>
                <h2 class="text-base font-bold text-slate-800">Past Cash Handovers</h2>
                <p class="text-xs text-slate-500">List of all cash handovers checked, approved, or rejected by the admin.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-platinum text-slate-600 font-semibold border-b border-silver-600/70">
                    <tr>
                        <th class="py-3 px-4">
                            <a href="<?= getHandoverSortUrl('date', $sort, $dir) ?>" class="inline-flex items-center gap-1 hover:text-steel_azure transition group" title="Sort by Date">
                                <span>Date</span>
                                <?= renderHandoverSortIcon('date', $sort, $dir) ?>
                            </a>
                        </th>
                        <th class="py-3 px-4">
                            <a href="<?= getHandoverSortUrl('collector', $sort, $dir) ?>" class="inline-flex items-center gap-1 hover:text-steel_azure transition group" title="Sort by Collector">
                                <span>Collector</span>
                                <?= renderHandoverSortIcon('collector', $sort, $dir) ?>
                            </a>
                        </th>
                        <th class="py-3 px-4">
                            <a href="<?= getHandoverSortUrl('expected', $sort, $dir) ?>" class="inline-flex items-center gap-1 hover:text-steel_azure transition group" title="Sort by Expected Cash">
                                <span>Expected Cash</span>
                                <?= renderHandoverSortIcon('expected', $sort, $dir) ?>
                            </a>
                        </th>
                        <th class="py-3 px-4">
                            <a href="<?= getHandoverSortUrl('received', $sort, $dir) ?>" class="inline-flex items-center gap-1 hover:text-steel_azure transition group" title="Sort by Cash Received">
                                <span>Cash Received</span>
                                <?= renderHandoverSortIcon('received', $sort, $dir) ?>
                            </a>
                        </th>
                        <th class="py-3 px-4">
                            <a href="<?= getHandoverSortUrl('difference', $sort, $dir) ?>" class="inline-flex items-center gap-1 hover:text-steel_azure transition group" title="Sort by Difference">
                                <span>Difference</span>
                                <?= renderHandoverSortIcon('difference', $sort, $dir) ?>
                            </a>
                        </th>
                        <th class="py-3 px-4">
                            <a href="<?= getHandoverSortUrl('verified', $sort, $dir) ?>" class="inline-flex items-center gap-1 hover:text-steel_azure transition group" title="Sort by Verified By">
                                <span>Verified / Handled By</span>
                                <?= renderHandoverSortIcon('verified', $sort, $dir) ?>
                            </a>
                        </th>
                        <th class="py-3 px-4 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-silver-600/50">
                    <?php if (empty($historyHandovers)): ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="empty-state">
                                    <div class="empty-state-icon bg-slate-100 text-slate-400">
                                        <i class="fa-solid fa-box-archive text-3xl"></i>
                                    </div>
                                    <div class="empty-state-title">No Settled Handovers</div>
                                    <div class="empty-state-text">No settled handovers yet.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historyHandovers as $hh): ?>
                            <?php $isRejected = ($hh['status'] === 'rejected'); ?>
                            <tr class="hover:bg-platinum-800 transition <?= $isRejected ? 'bg-rose-50/25' : '' ?>">
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-800">
                                        <?= date('d M Y', strtotime($hh['handover_date'])) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-medium flex items-center gap-1 mt-0.5">
                                        <i class="fa-regular fa-clock text-[10px]"></i>
                                        <span><?= date('h:i A', strtotime($hh['approved_at'] ?: $hh['submitted_at'])) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 font-bold text-slate-800">
                                    <?= htmlspecialchars($hh['collector_name']) ?>
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-700 whitespace-nowrap">
                                    <?= format_money($hh['expected_cash']) ?>
                                </td>
                                <td class="py-3 px-4 font-black whitespace-nowrap <?= $isRejected ? 'text-slate-400 line-through' : 'text-emerald-600' ?>">
                                    <?= format_money($hh['cash_received']) ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <?php if ($isRejected): ?>
                                        <span class="text-xs font-bold text-rose-600">Unsettled</span>
                                    <?php elseif ($hh['difference'] == 0): ?>
                                        <span class="text-xs font-bold text-emerald-700">0.00 (Exact)</span>
                                    <?php elseif ($hh['difference'] < 0): ?>
                                        <span class="text-xs font-bold text-red-600">Short <?= format_money(abs($hh['difference'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-xs font-bold text-pumpkin_spice">+<?= format_money($hh['difference']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                    <div><?= htmlspecialchars($hh['approved_by_name'] ?: 'Admin') ?></div>
                                    <?php if (!empty($hh['admin_note'])): ?>
                                        <div class="text-[10px] text-slate-400 italic max-w-xs truncate" title="<?= htmlspecialchars($hh['admin_note']) ?>">
                                            <?= htmlspecialchars($hh['admin_note']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <?php if ($isRejected): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-[11px] font-extrabold bg-rose-100 text-rose-800 border border-rose-200">
                                            REJECTED
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                            SETTLED
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?= render_pagination($pagedHistory['total'], $pagedHistory['per_page'], $pagedHistory['current']) ?>
    </div>

</div>

<!-- Modal 1: Variance Confirmation Modal -->
<div id="varianceModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 transition-opacity duration-200">
    <div class="bg-white rounded-2xl border-2 border-amber-300 shadow-xl max-w-lg w-full overflow-hidden">
        <!-- Modal Header -->
        <div class="p-5 bg-amber-50 border-b border-amber-200 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-200 text-amber-900 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-amber-950">Acknowledge Handover Variance</h3>
                    <p class="text-xs text-amber-800">Physical cash counted does not match system calculation.</p>
                </div>
            </div>
            <button type="button" onclick="closeVarianceModal()" class="text-slate-400 hover:text-slate-700 text-base p-1">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <form method="POST" action="daily_handover.php" class="p-5 space-y-4">
            <input type="hidden" name="action" value="approve_handover">
            <input type="hidden" name="handover_id" id="varianceHandoverId">

            <div class="text-xs text-slate-600">
                You are about to verify and clear the cash handover for <strong id="varianceCollectorName" class="text-slate-900"></strong>.
            </div>

            <!-- Discrepancy Card -->
            <div class="p-4 rounded-xl border bg-platinum-800 border-silver-600 space-y-2 text-xs">
                <div class="flex justify-between items-center">
                    <span class="text-slate-500 font-medium">System Expected:</span>
                    <span id="varianceExpected" class="font-bold text-slate-800"></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500 font-medium">Physical Tendered:</span>
                    <span id="varianceTendered" class="font-bold text-steel_azure"></span>
                </div>
                <div class="pt-2 border-t border-silver-600 flex justify-between items-center text-sm font-extrabold">
                    <span>Difference / Discrepancy:</span>
                    <span id="varianceDiffBadge"></span>
                </div>
            </div>

            <!-- Verification Note -->
            <div>
                <label for="varianceAdminNote" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Admin Verification Note *
                </label>
                <input type="text" id="varianceAdminNote" name="admin_note" required
                       placeholder="e.g. Shortage counted and agreed; collector recovery plan noted"
                       class="w-full px-3.5 py-2.5 rounded-xl border-2 border-silver-600 focus:border-amber-500 outline-none text-xs sm:text-sm text-slate-800 transition">
                <p class="helper-text">Explain how this variance is being handled or acknowledged.</p>
            </div>

            <!-- Actions -->
            <div class="pt-2 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeVarianceModal()" class="btn-touch px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition">
                    Cancel
                </button>
                <button type="submit" class="btn-touch px-4 py-2.5 rounded-xl text-xs font-extrabold bg-amber-600 hover:bg-amber-700 text-white transition inline-flex items-center gap-1.5 shadow-md">
                    <i class="fa-solid fa-circle-check text-xs"></i>
                    <span>Acknowledge Variance & Clear Liability</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Reject Handover Modal -->
<div id="rejectModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 transition-opacity duration-200">
    <div class="bg-white rounded-2xl border-2 border-rose-300 shadow-xl max-w-lg w-full overflow-hidden">
        <!-- Modal Header -->
        <div class="p-5 bg-rose-50 border-b border-rose-200 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-200 text-rose-900 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-ban"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-rose-950">Reject Cash Handover</h3>
                    <p class="text-xs text-rose-800">Releases deposits back to the collector's bag for recount.</p>
                </div>
            </div>
            <button type="button" onclick="closeRejectModal()" class="text-slate-400 hover:text-slate-700 text-base p-1">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <form method="POST" action="daily_handover.php" class="p-5 space-y-4">
            <input type="hidden" name="action" value="reject_handover">
            <input type="hidden" name="handover_id" id="rejectHandoverId">

            <div class="text-xs text-slate-600">
                You are rejecting Handover #<span id="rejectHandoverNum" class="font-bold"></span> for <strong id="rejectCollectorName" class="text-slate-900"></strong> (<span id="rejectTenderedAmount" class="font-bold text-steel_azure"></span>).
            </div>

            <!-- Mandatory Rejection Reason -->
            <div>
                <label for="rejection_reason" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Reason for Rejection *
                </label>
                <textarea id="rejection_reason" name="rejection_reason" rows="3" required
                          placeholder="e.g. Typo in amount tendered (entered GH₵ 1,000 instead of GH₵ 11,295); recount required"
                          class="w-full px-3.5 py-2.5 rounded-xl border-2 border-silver-600 focus:border-rose-500 outline-none text-xs sm:text-sm text-slate-800 transition"></textarea>
                <p class="helper-text">This reason will be recorded in the audit log and sent to the collector.</p>
            </div>

            <!-- Actions -->
            <div class="pt-2 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeRejectModal()" class="btn-touch px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition">
                    Cancel
                </button>
                <button type="submit" class="btn-touch px-4 py-2.5 rounded-xl text-xs font-extrabold bg-rose-600 hover:bg-rose-700 text-white transition inline-flex items-center gap-1.5 shadow-md">
                    <i class="fa-solid fa-xmark text-xs"></i>
                    <span>Confirm Rejection & Release Deposits</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openVarianceModal(data) {
    document.getElementById('varianceHandoverId').value = data.id;
    document.getElementById('varianceCollectorName').textContent = data.collector_name;
    document.getElementById('varianceExpected').textContent = 'GH₵ ' + parseFloat(data.expected_cash).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('varianceTendered').textContent = 'GH₵ ' + parseFloat(data.cash_received).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    var diff = parseFloat(data.difference);
    var diffBadge = document.getElementById('varianceDiffBadge');
    if (diff < 0) {
        diffBadge.className = 'text-red-600 font-extrabold';
        diffBadge.textContent = 'Shortage: GH₵ ' + Math.abs(diff).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('varianceAdminNote').value = 'Shortage of GH₵ ' + Math.abs(diff).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' counted and acknowledged.';
    } else {
        diffBadge.className = 'text-pumpkin_spice font-extrabold';
        diffBadge.textContent = 'Overage: +GH₵ ' + diff.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('varianceAdminNote').value = 'Overage of GH₵ ' + diff.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' counted and acknowledged.';
    }
    
    document.getElementById('varianceModal').classList.remove('hidden');
}

function closeVarianceModal() {
    document.getElementById('varianceModal').classList.add('hidden');
}

function openRejectModal(id, collectorName, tenderedAmount) {
    document.getElementById('rejectHandoverId').value = id;
    document.getElementById('rejectHandoverNum').textContent = id;
    document.getElementById('rejectCollectorName').textContent = collectorName;
    document.getElementById('rejectTenderedAmount').textContent = 'GH₵ ' + parseFloat(tenderedAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('rejection_reason').value = '';
    
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeVarianceModal();
        closeRejectModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
