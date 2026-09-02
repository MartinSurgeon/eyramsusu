<?php
// collector_dashboard.php - Mobile Field Hub for Collectors
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = get_logged_in_user();
$pageTitle = "Collector Field Hub";
$pdo = get_db_connection();

$collectorId = $user['id'];
$cashInHand = get_collector_cash_in_hand($collectorId);

// Today's total collected by this collector
$stmtToday = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0.00) as today_total, COUNT(id) as deposit_count 
    FROM deposits 
    WHERE collector_id = ? AND deposit_date = CURRENT_DATE
");
$stmtToday->execute([$collectorId]);
$todayStats = $stmtToday->fetch();

// Fetch assigned customers with their active Susu Card
$stmtCust = $pdo->prepare("
    SELECT c.*, 
           sc.id as card_id, sc.card_number, sc.daily_amount, sc.spaces_filled, sc.total_spaces, sc.total_saved, sc.status as card_status
    FROM customers c
    LEFT JOIN susu_cards sc ON c.id = sc.customer_id AND sc.status = 'active'
    WHERE c.assigned_collector_id = ? AND c.is_active = 1
    ORDER BY c.full_name ASC
");
$stmtCust->execute([$collectorId]);
$myCustomers = $stmtCust->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-5 max-w-4xl mx-auto">

    <!-- Hero Cash in Hand Card (High Contrast & Clear Primary Action) -->
    <div class="bg-gradient-to-br from-steel_azure to-steel_azure-400 text-white rounded-3xl p-6 shadow-xl border border-steel_azure-300">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/10 rounded-full text-xs font-semibold text-cornflower_ocean-900 tracking-wide uppercase">
                    Field Cash Bag
                </span>
                <div class="text-3xl sm:text-4xl font-black mt-2 tracking-tight text-white">
                    <?= format_money($cashInHand) ?>
                </div>
                <p class="text-xs text-cornflower_ocean-800 mt-1">Total physical cash in hand awaiting office handover</p>
            </div>

            <div class="flex flex-col sm:flex-row gap-2.5 sm:items-center">
                <!-- Large Thumb Primary CTA (Fitts's Law) -->
                <a href="record_deposit.php" class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-sm font-extrabold shadow-lg transition">
                    <svg class="w-5 h-5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    Record Deposit
                </a>

                <!-- Secondary CTA (Hick's Law: Visually lighter) -->
                <a href="daily_handover.php" class="btn-touch bg-white/10 hover:bg-white/20 text-white border border-white/25 text-xs font-bold transition">
                    Handover Cash
                </a>
            </div>
        </div>

        <!-- Mini Stats Row -->
        <div class="grid grid-cols-2 gap-3 mt-6 pt-5 border-t border-white/15 text-xs">
            <div>
                <span class="text-cornflower_ocean-800 font-medium">Collected Today:</span>
                <div class="font-extrabold text-sm text-white"><?= format_money($todayStats['today_total']) ?> (<?= $todayStats['deposit_count'] ?> deposits)</div>
            </div>
            <div>
                <span class="text-cornflower_ocean-800 font-medium">My Assigned Customers:</span>
                <div class="font-extrabold text-sm text-white"><?= count($myCustomers) ?> clients</div>
            </div>
        </div>
    </div>

    <!-- Assigned Customers Section -->
    <div class="section-card">
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-slate-800">My Assigned Customers</h2>
                <p class="text-xs text-slate-500">Tap "Deposit" on any customer to quickly record their payment.</p>
            </div>
            
            <!-- Quick Search Filter -->
            <div class="w-full sm:w-64">
                <input type="text" id="customer_search" placeholder="Search name or account..."
                       class="w-full px-3 py-2 text-xs rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none transition">
            </div>
        </div>

        <div id="search_empty_notice" class="hidden">
            <div class="empty-state">
                <div class="empty-state-icon bg-slate-100">🔍</div>
                <div class="empty-state-title">No Matches Found</div>
                <div class="empty-state-text">Try a different search term.</div>
            </div>
        </div>

        <!-- Customer Cards (Gestalt Similarity: Consistent card style) -->
        <div class="space-y-3">
            <?php if (empty($myCustomers)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon bg-blue-50">👥</div>
                    <div class="empty-state-title">No Assigned Customers</div>
                    <div class="empty-state-text">You do not have any assigned customers yet. Please check with the Admin to get clients assigned to your route.</div>
                </div>
            <?php else: ?>
                <?php foreach ($myCustomers as $cust): ?>
                    <div class="customer-row card-elevated p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                         data-search="<?= htmlspecialchars($cust['full_name'] . ' ' . $cust['account_number'] . ' ' . $cust['location'] . ' ' . $cust['phone']) ?>">
                        
                        <!-- Customer Info -->
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-extrabold text-sm text-slate-800"><?= htmlspecialchars($cust['full_name']) ?></span>
                                <span class="text-[11px] font-semibold text-slate-500 bg-platinum px-2 py-0.5 rounded">
                                    <?= htmlspecialchars($cust['account_number']) ?>
                                </span>
                            </div>
                            
                            <div class="text-xs text-slate-500 mt-1 flex flex-wrap gap-x-3 gap-y-1">
                                <span>📍 <?= htmlspecialchars($cust['location'] ?: 'No location') ?></span>
                                <span>📞 <?= htmlspecialchars($cust['phone']) ?></span>
                            </div>

                            <!-- Card Progress -->
                            <?php if ($cust['card_id']): ?>
                                <div class="mt-2.5 flex items-center gap-2">
                                    <div class="flex-1 max-w-xs bg-silver-700 rounded-full h-2 overflow-hidden">
                                        <?php $pct = round(($cust['spaces_filled'] / $cust['total_spaces']) * 100); ?>
                                        <div class="bg-emerald-500 h-2 rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <span class="text-[11px] font-bold text-slate-700">
                                        <?= $cust['spaces_filled'] ?> / <?= $cust['total_spaces'] ?> spaces
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-600 font-medium mt-1">
                                    Agreed: <strong class="text-steel_azure"><?= format_money($cust['daily_amount']) ?></strong> &bull;
                                    Saved: <strong class="text-emerald-700"><?= format_money($cust['total_saved']) ?></strong>
                                    <?php if ($cust['change_balance'] > 0): ?>
                                        &bull; Change: <strong class="text-pumpkin_spice"><?= format_money($cust['change_balance']) ?></strong>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="mt-2 text-xs font-semibold text-amber-600 flex items-center gap-1.5">
                                    <span>⚠️</span> No active card. Admin can start a new card.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons (Hick's Law: Primary + Secondary) -->
                        <div class="flex items-center gap-2 pt-2 sm:pt-0 border-t sm:border-t-0 border-silver-600/40">
                            <?php if ($cust['card_id']): ?>
                                <a href="record_deposit.php?customer_id=<?= $cust['id'] ?>" 
                                   class="btn-touch flex-1 sm:flex-none bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-extrabold px-3 py-2 shadow-sm transition">
                                    Deposit
                                </a>
                                <a href="view_card.php?id=<?= $cust['card_id'] ?>" 
                                   class="btn-touch flex-1 sm:flex-none bg-white hover:bg-platinum text-steel_azure border border-steel_azure text-xs font-bold px-3 py-2 transition">
                                    Card
                                </a>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
