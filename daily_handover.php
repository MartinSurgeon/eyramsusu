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

// Handle Collector (or Admin) Submitting a Handover
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_handover') {
    $collectorId = $user['role'] === 'collector' ? $user['id'] : (int)($_POST['collector_id'] ?? 0);
    $physicalCash = (float)($_POST['physical_cash'] ?? 0);
    $collectorNote = trim($_POST['collector_note'] ?? '');
    $handoverDate = !empty($_POST['handover_date']) ? $_POST['handover_date'] : date('Y-m-d');

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

// Fetch Pending Handovers
$stmtPending = $pdo->query("
    SELECT h.*, u.full_name as collector_name, u.phone as collector_phone
    FROM daily_handovers h
    JOIN users u ON h.collector_id = u.id
    WHERE h.status = 'submitted'
    ORDER BY h.id DESC
");
$pendingHandovers = $stmtPending->fetchAll();

// Fetch Handover History (Miller's law chunking)
$stmtHistory = $pdo->query("
    SELECT h.*, u.full_name as collector_name, admin.full_name as approved_by_name
    FROM daily_handovers h
    JOIN users u ON h.collector_id = u.id
    LEFT JOIN users admin ON h.approved_by = admin.id
    WHERE h.status = 'approved'
    ORDER BY h.id DESC LIMIT 15
");
$historyHandovers = $stmtHistory->fetchAll();

// Calculate current user's or selected collector's Cash in Hand
$myCashInHand = get_collector_cash_in_hand($user['id']);

$pageTitle = "Daily Cash Handover";
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 section-card">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-steel_azure">Daily Cash Handover</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Reconcile physical cash collections with system calculations.</p>
        </div>
        <div>
            <a href="<?= $user['role'] === 'admin' ? 'admin_dashboard.php' : 'collector_dashboard.php' ?>" class="text-xs font-bold text-cornflower_ocean hover:text-steel_azure">
                &larr; Back to Home
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3.5 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Collector Handover Submission Box -->
    <?php if ($user['role'] === 'collector' || ($user['role'] === 'admin' && isset($_GET['collector_id']))): ?>
        <?php 
            $targetCollectorId = $user['role'] === 'collector' ? $user['id'] : (int)$_GET['collector_id'];
            $targetExpectedCash = get_collector_cash_in_hand($targetCollectorId);
        ?>
        <div class="bg-white rounded-2xl border-2 border-silver-600 shadow-md p-6 max-w-2xl mx-auto">
            <h2 class="text-base font-bold text-slate-800 mb-1">Submit End-of-Day Cash Handover</h2>
            <p class="text-xs text-slate-500 mb-4">Enter the physical cash counted in your bag to hand over to the office.</p>

            <!-- Expected Cash Badge -->
            <div class="bg-platinum-800 p-4 rounded-xl border border-silver-600 mb-5 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">System Expected Cash in Hand</span>
                    <div class="text-2xl font-black text-steel_azure mt-0.5"><?= format_money($targetExpectedCash) ?></div>
                    <span class="text-[11px] text-slate-400">Total unhanded deposits collected in the field</span>
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
                    <p class="helper-text">Count the physical cash in your bag and enter the exact amount.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="handover_date" class="block text-xs font-bold text-slate-700 mb-1">Handover Date</label>
                        <input type="date" id="handover_date" name="handover_date" value="<?= date('Y-m-d') ?>" required
                               class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm text-slate-700 transition">
                    </div>

                    <div>
                        <label for="collector_note" class="block text-xs font-bold text-slate-700 mb-1">Notes / Discrepancy Note</label>
                        <input type="text" id="collector_note" name="collector_note" placeholder="e.g. End of day bag handover"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure outline-none text-xs sm:text-sm text-slate-700 transition">
                    </div>
                </div>

                <?php if ($user['role'] === 'admin'): ?>
                    <div class="flex items-center gap-2 p-2.5 bg-platinum rounded-lg text-xs font-semibold text-slate-700">
                        <input type="checkbox" id="auto_approve" name="auto_approve" value="1" checked class="w-4 h-4 rounded text-steel_azure">
                        <label for="auto_approve">Automatically verify and approve settlement immediately</label>
                    </div>
                <?php endif; ?>

                <!-- Submit Action Button (Clean In-Flow inside card per user decision) -->
                <div class="pt-4 border-t border-silver-600/60">
                    <button type="submit" class="w-full btn-action-primary bg-steel_azure hover:bg-steel_azure-400 text-white font-extrabold text-base tracking-wide shadow-md transition">
                        ✓ Submit Cash Handover for Verification
                    </button>
                </div>
            </form>
        </div>
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
                        <div class="empty-state-icon bg-emerald-50">✅</div>
                        <div class="empty-state-title">All Clear</div>
                        <div class="empty-state-text">No handovers currently waiting for verification.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($pendingHandovers as $h): ?>
                        <div class="p-4 sm:p-5 hover:bg-platinum-900 transition flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-black text-sm sm:text-base text-slate-800"><?= htmlspecialchars($h['collector_name']) ?></span>
                                    <span class="text-xs text-slate-500">📞 <?= htmlspecialchars($h['collector_phone']) ?></span>
                                    <span class="text-xs text-slate-400">&bull; <?= date('d M Y, h:i A', strtotime($h['submitted_at'])) ?></span>
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
                                        <span class="bg-emerald-50 text-emerald-800 border border-emerald-300 px-2.5 py-1 rounded-md font-bold">
                                            ✓ Exact Match (GH₵ 0.00)
                                        </span>
                                    <?php elseif ($h['difference'] < 0): ?>
                                        <span class="bg-red-50 text-red-700 border border-red-300 px-2.5 py-1 rounded-md font-bold">
                                            ⚠️ Shortage: <?= format_money(abs($h['difference'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-amber-50 text-pumpkin_spice border border-amber-300 px-2.5 py-1 rounded-md font-bold">
                                            ℹ️ Overage: +<?= format_money($h['difference']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($h['collector_note'])): ?>
                                    <div class="text-xs text-slate-500 mt-2">
                                        Note: <em>"<?= htmlspecialchars($h['collector_note']) ?>"</em>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Verification Form -->
                            <form method="POST" action="daily_handover.php" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                <input type="hidden" name="action" value="approve_handover">
                                <input type="hidden" name="handover_id" value="<?= $h['id'] ?>">
                                
                                <input type="text" name="admin_note" placeholder="Verification note..."
                                       value="Cash counted and verified."
                                       class="px-3 py-2 text-xs rounded-xl border border-silver-600 focus:border-steel_azure outline-none transition">

                                <button type="submit" 
                                        class="btn-touch bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black px-4 py-2 shadow-sm transition whitespace-nowrap">
                                    ✓ Confirm & Clear Liability
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Historical Settled Handovers Table -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-silver-600/70">
            <h2 class="text-base font-bold text-slate-800">Settlement History</h2>
            <p class="text-xs text-slate-500">Record of reconciled and approved daily cash handovers.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-platinum text-slate-600 font-semibold border-b border-silver-600/70">
                    <tr>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Collector</th>
                        <th class="py-3 px-4">Expected Cash</th>
                        <th class="py-3 px-4">Cash Received</th>
                        <th class="py-3 px-4">Difference</th>
                        <th class="py-3 px-4">Verified By</th>
                        <th class="py-3 px-4 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-silver-600/50">
                    <?php if (empty($historyHandovers)): ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="empty-state">
                                    <div class="empty-state-icon bg-slate-100">📦</div>
                                    <div class="empty-state-title">No Settled Handovers</div>
                                    <div class="empty-state-text">No settled handovers yet.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historyHandovers as $hh): ?>
                            <tr class="hover:bg-platinum-800 transition">
                                <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                    <?= date('d M Y', strtotime($hh['handover_date'])) ?>
                                </td>
                                <td class="py-3 px-4 font-bold text-slate-800">
                                    <?= htmlspecialchars($hh['collector_name']) ?>
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-700 whitespace-nowrap">
                                    <?= format_money($hh['expected_cash']) ?>
                                </td>
                                <td class="py-3 px-4 font-black text-emerald-600 whitespace-nowrap">
                                    <?= format_money($hh['cash_received']) ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <?php if ($hh['difference'] == 0): ?>
                                        <span class="text-xs font-bold text-emerald-700">0.00 (Exact)</span>
                                    <?php elseif ($hh['difference'] < 0): ?>
                                        <span class="text-xs font-bold text-red-600">Short <?= format_money(abs($hh['difference'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-xs font-bold text-pumpkin_spice">+<?= format_money($hh['difference']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                    <?= htmlspecialchars($hh['approved_by_name'] ?: 'Admin') ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                        SETTLED
                                    </span>
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
