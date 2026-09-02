<?php
// view_card.php - Visual 31-Space Susu Card Passbook
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = get_logged_in_user();
$pdo = get_db_connection();

$cardId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$cardId && isset($_GET['customer_id'])) {
    $activeCard = get_active_card_for_customer((int)$_GET['customer_id']);
    if ($activeCard) {
        $cardId = $activeCard['id'];
    }
}

if (!$cardId) {
    header('Location: customers.php');
    exit;
}

// Fetch Card and Customer
$stmt = $pdo->prepare("
    SELECT sc.*, c.full_name, c.account_number, c.phone, c.location, c.change_balance,
           u.full_name as collector_name
    FROM susu_cards sc
    JOIN customers c ON sc.customer_id = c.id
    LEFT JOIN users u ON c.assigned_collector_id = u.id
    WHERE sc.id = ?
");
$stmt->execute([$cardId]);
$card = $stmt->fetch();

if (!$card) {
    set_flash_message('error', 'Susu Card not found.');
    header('Location: customers.php');
    exit;
}

// Fetch all deposits recorded for this card, keyed by space_number
$depositsList = get_card_deposits($cardId);
$depositsBySpace = [];
foreach ($depositsList as $dep) {
    $depositsBySpace[$dep['space_number']] = $dep;
}

// Check for existing payout on this card
$stmtPayout = $pdo->prepare("SELECT * FROM payouts WHERE card_id = ? ORDER BY id DESC LIMIT 1");
$stmtPayout->execute([$cardId]);
$payout = $stmtPayout->fetch();

$pageTitle = "Susu Card #" . $card['card_number'] . " - " . $card['full_name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6 max-w-5xl mx-auto">

    <!-- Print-Only Passbook Header -->
    <div class="hidden print-passbook-header">
        <h1>Eyram Susu Savings</h1>
        <p>31-Space Passbook &bull; <?= htmlspecialchars($card['full_name']) ?> &bull; Card #<?= $card['card_number'] ?></p>
    </div>

    <!-- Top Navigation & Print Actions -->
    <div class="flex items-center justify-between no-print">
        <a href="customers.php" class="text-xs font-bold text-cornflower_ocean hover:text-steel_azure flex items-center gap-1">
            &larr; Customers Directory
        </a>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="btn-touch bg-white text-slate-700 hover:bg-platinum border border-silver-600 text-xs font-bold px-3 py-1.5 shadow-sm">
                🖨️ Print Passbook
            </button>
            <?php if ($card['status'] === 'active'): ?>
                <a href="record_deposit.php?customer_id=<?= $card['customer_id'] ?>" class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-extrabold px-3.5 py-1.5 shadow-sm">
                    + Record Deposit
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Celebratory Banner for Completed Cards (Peak-End Rule) -->
    <?php if ($card['status'] === 'completed'): ?>
        <div class="celebration-banner">
            <div class="text-lg font-black text-emerald-800">🎉 Card Completed!</div>
            <p class="text-xs font-semibold text-emerald-700 mt-0.5">All 31 spaces have been successfully filled. This Susu cycle is complete.</p>
        </div>
    <?php endif; ?>

    <!-- Passbook Header Card (Jakob's Law: Physical Book Appearance) -->
    <div class="bg-white rounded-2xl border-2 border-silver-600 shadow-md p-5 sm:p-6 overflow-hidden relative">
        <div class="absolute top-0 right-0 left-0 h-2 bg-gradient-to-r from-steel_azure via-cornflower_ocean to-pumpkin_spice"></div>

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mt-1">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-steel_azure text-white uppercase tracking-wider">
                        Susu Card #<?= $card['card_number'] ?>
                    </span>
                    
                    <?php if ($card['status'] === 'active'): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                            Active (<?= $card['spaces_filled'] ?>/<?= $card['total_spaces'] ?> Filled)
                        </span>
                    <?php elseif ($card['status'] === 'completed'): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-300">
                            ✓ Completed 31 Spaces
                        </span>
                    <?php else: ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
                            Closed Early (<?= $card['spaces_filled'] ?> Spaces)
                        </span>
                    <?php endif; ?>
                </div>

                <h1 class="text-xl sm:text-2xl font-black text-slate-800 mt-2">
                    <?= htmlspecialchars($card['full_name']) ?>
                </h1>
                
                <div class="text-xs text-slate-500 mt-1 flex flex-wrap gap-x-4 gap-y-1">
                    <span><strong>Account:</strong> <?= htmlspecialchars($card['account_number']) ?></span>
                    <span><strong>Phone:</strong> <?= htmlspecialchars($card['phone']) ?></span>
                    <span><strong>Location:</strong> <?= htmlspecialchars($card['location'] ?: 'N/A') ?></span>
                    <span><strong>Collector:</strong> <?= htmlspecialchars($card['collector_name'] ?: 'Unassigned') ?></span>
                </div>
            </div>

            <!-- Financial Metrics Highlights -->
            <div class="flex flex-wrap sm:flex-nowrap gap-3 bg-platinum-800 p-3.5 rounded-xl border border-silver-600/70">
                <div class="text-left sm:text-right pr-3 border-r border-silver-600">
                    <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Agreed Daily Rate</div>
                    <div class="text-base sm:text-lg font-black text-steel_azure"><?= format_money($card['daily_amount']) ?></div>
                    <div class="text-[10px] text-slate-400">per space</div>
                </div>

                <div class="text-left sm:text-right pr-3 border-r border-silver-600">
                    <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Saved</div>
                    <div class="text-base sm:text-lg font-black text-emerald-600"><?= format_money($card['total_saved']) ?></div>
                    <div class="text-[10px] text-slate-400"><?= $card['spaces_filled'] ?> spaces paid</div>
                </div>

                <div class="text-left sm:text-right">
                    <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Change Float</div>
                    <div class="text-base sm:text-lg font-black <?= $card['change_balance'] > 0 ? 'text-pumpkin_spice' : 'text-slate-400' ?>">
                        <?= format_money($card['change_balance']) ?>
                    </div>
                    <div class="text-[10px] text-slate-400">towards next space</div>
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="mt-5 pt-4 border-t border-silver-600/60">
            <div class="flex items-center justify-between text-xs font-bold text-slate-600 mb-1.5">
                <span>Card Progress</span>
                <span class="text-steel_azure"><?= $card['spaces_filled'] ?> of <?= $card['total_spaces'] ?> Spaces Complete (<?= round(($card['spaces_filled'] / $card['total_spaces']) * 100) ?>%)</span>
            </div>
            <div class="w-full bg-silver-700 rounded-full h-3 overflow-hidden p-0.5">
                <?php $progress = min(100, round(($card['spaces_filled'] / $card['total_spaces']) * 100)); ?>
                <div class="bg-gradient-to-r from-cornflower_ocean via-emerald-500 to-emerald-600 h-2 rounded-full transition-all duration-500" style="width: <?= $progress ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Visual 31-Space Susu Book Grid -->
    <div class="bg-white rounded-2xl border-2 border-silver-600 shadow-md p-5 sm:p-6">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-silver-600/70">
            <div>
                <h2 class="text-base sm:text-lg font-black text-slate-800">31-Space Savings Passbook</h2>
                <p class="text-xs text-slate-500">Each space represents one contribution of <?= format_money($card['daily_amount']) ?> (independent of calendar month).</p>
            </div>
            <div class="text-xs font-semibold text-slate-400 hidden sm:block">
                Spaces 1 – 31
            </div>
        </div>

        <!-- 31 Spaces Grid -->
        <div class="susu-book-grid">
            <?php for ($i = 1; $i <= (int)$card['total_spaces']; $i++): ?>
                <?php if (isset($depositsBySpace[$i])): 
                    $dep = $depositsBySpace[$i]; ?>
                    <!-- Paid Space -->
                    <div class="susu-space-box susu-space-paid">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-black text-emerald-800">#<?= $i ?></span>
                            <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] font-black shadow-xs">✓</span>
                        </div>
                        <div class="my-auto text-center">
                            <div class="text-[10px] text-slate-600 font-bold leading-tight">
                                <?= date('d M', strtotime($dep['deposit_date'])) ?>
                            </div>
                            <div class="text-[9px] text-slate-400 truncate">
                                <?= date('Y', strtotime($dep['deposit_date'])) ?>
                            </div>
                        </div>
                        <div class="text-center font-extrabold text-xs text-emerald-700 border-t border-emerald-200 pt-0.5">
                            <?= format_money($dep['amount']) ?>
                        </div>
                    </div>

                <?php elseif ($card['status'] === 'active' && $i === ($card['spaces_filled'] + 1)): ?>
                    <!-- Next Space to Fill -->
                    <a href="record_deposit.php?customer_id=<?= $card['customer_id'] ?>" 
                       class="susu-space-box susu-space-next hover:scale-105 cursor-pointer no-print"
                       title="Click to fill Space #<?= $i ?>">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-black text-pumpkin_spice">#<?= $i ?></span>
                            <span class="text-[10px] font-black text-pumpkin_spice">+</span>
                        </div>
                        <div class="my-auto text-center">
                            <span class="text-[10px] font-bold text-pumpkin_spice block leading-tight">Next Space</span>
                            <span class="text-[9px] text-slate-400">Click to pay</span>
                        </div>
                        <div class="text-center text-[10px] font-extrabold text-slate-500 border-t border-pumpkin_spice-800 pt-0.5">
                            <?= format_money($card['daily_amount']) ?>
                        </div>
                    </a>

                <?php else: ?>
                    <!-- Empty Future Space -->
                    <div class="susu-space-box susu-space-empty">
                        <span class="text-[11px] font-bold text-silver-400">#<?= $i ?></span>
                        <div class="my-auto text-center">
                            <span class="text-[10px] text-silver-400">&mdash;</span>
                        </div>
                        <div class="text-center text-[10px] font-medium text-silver-400 border-t border-silver-700 pt-0.5">
                            <?= format_money($card['daily_amount']) ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>

        <!-- Legend -->
        <div class="mt-6 pt-4 border-t border-silver-600/60 flex flex-wrap items-center justify-center gap-6 text-xs text-slate-500">
            <div class="flex items-center gap-1.5">
                <span class="w-3.5 h-3.5 rounded bg-emerald-50 border border-emerald-500 flex items-center justify-center text-[9px] font-bold text-emerald-700">✓</span>
                <span>Space Completed & Stamped</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3.5 h-3.5 rounded bg-pumpkin_spice-900 border border-pumpkin_spice"></span>
                <span>Next Space to Pay</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3.5 h-3.5 rounded bg-white border border-dashed border-silver"></span>
                <span>Empty Space Remaining</span>
            </div>
        </div>

    </div>

    <!-- Card Action Panel (Payout / Close Card or Start Next Card) -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm p-5 sm:p-6 no-print">
        <?php if ($card['status'] === 'active'): ?>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Customer Cashout & Cycle Closure</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Customer can cash out after 31 spaces or stop early. Business fee (1 contribution of <?= format_money($card['daily_amount']) ?>) will be deducted.
                    </p>
                </div>
                <div>
                    <?php if ($card['spaces_filled'] > 0): ?>
                        <a href="request_payout.php?card_id=<?= $card['id'] ?>" class="btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white text-xs sm:text-sm font-extrabold px-5 py-2.5 shadow-sm transition">
                            Request Customer Payout
                        </a>
                    <?php else: ?>
                        <button disabled class="btn-touch bg-platinum text-slate-400 cursor-not-allowed text-xs font-bold px-4 py-2">
                            No contributions yet to cash out
                        </button>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($payout): ?>
            <!-- Payout Summary Receipt -->
            <div class="p-4 bg-platinum-800 rounded-xl border border-silver-600">
                <div class="flex items-center justify-between pb-3 border-b border-silver-600/70">
                    <h3 class="text-sm font-bold text-slate-800">Final Payout Settlement Record</h3>
                    <span class="px-2.5 py-0.5 rounded text-xs font-bold <?= $payout['status'] === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                        <?= strtoupper($payout['status']) ?>
                    </span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3 text-xs">
                    <div>
                        <span class="text-slate-500">Gross Saved:</span>
                        <div class="font-extrabold text-slate-800"><?= format_money($payout['total_saved']) ?></div>
                    </div>
                    <div>
                        <span class="text-slate-500">Business Fee (1 space):</span>
                        <div class="font-extrabold text-pumpkin_spice">- <?= format_money($payout['business_fee']) ?></div>
                    </div>
                    <div>
                        <span class="text-slate-500">Change Refunded:</span>
                        <div class="font-extrabold text-slate-700">+ <?= format_money($payout['change_refunded']) ?></div>
                    </div>
                    <div>
                        <span class="text-slate-500">Net Customer Payout:</span>
                        <div class="font-black text-sm text-emerald-600"><?= format_money($payout['customer_payout']) ?></div>
                    </div>
                </div>

                <?php if ($user['role'] === 'admin'): ?>
                    <div class="mt-4 pt-3 border-t border-silver-600/60 flex justify-end">
                        <form method="POST" action="start_new_card.php">
                            <input type="hidden" name="customer_id" value="<?= $card['customer_id'] ?>">
                            <input type="hidden" name="daily_amount" value="<?= $card['daily_amount'] ?>">
                            <button type="submit" class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-bold px-4 py-2 shadow-sm">
                                + Open New Susu Card for <?= htmlspecialchars($card['full_name']) ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Complete History Log Table -->
    <div class="bg-white rounded-2xl border border-silver-600 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-silver-600/70">
            <h3 class="text-sm sm:text-base font-bold text-slate-800">Individual Deposit Logs</h3>
            <p class="text-xs text-slate-500">Chronological record of each space deposited and who collected it.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-platinum text-slate-600 font-semibold border-b border-silver-600/70">
                    <tr>
                        <th class="py-2.5 px-4">Space #</th>
                        <th class="py-2.5 px-4">Date Stamped</th>
                        <th class="py-2.5 px-4">Amount</th>
                        <th class="py-2.5 px-4">Collected By</th>
                        <th class="py-2.5 px-4">Handover Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-silver-600/50">
                    <?php if (empty($depositsList)): ?>
                        <tr>
                            <td colspan="5" class="text-center">
                                <div class="empty-state">
                                    <div class="empty-state-icon bg-slate-100">📋</div>
                                    <div class="empty-state-title">No Deposits Yet</div>
                                    <div class="empty-state-text">No deposits stamped on this card yet.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($depositsList as $dep): ?>
                            <tr class="hover:bg-platinum-800">
                                <td class="py-2.5 px-4 font-bold text-steel_azure">
                                    Space #<?= $dep['space_number'] ?>
                                </td>
                                <td class="py-2.5 px-4 text-slate-600">
                                    <?= date('d M Y, h:i A', strtotime($dep['created_at'])) ?>
                                </td>
                                <td class="py-2.5 px-4 font-bold text-emerald-600">
                                    <?= format_money($dep['amount']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-slate-700">
                                    <?= htmlspecialchars($dep['collector_name']) ?>
                                </td>
                                <td class="py-2.5 px-4">
                                    <?php if ($dep['handover_id']): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                            Handed Over
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800">
                                            In Cash Bag
                                        </span>
                                    <?php endif; ?>
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
