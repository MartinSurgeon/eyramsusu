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

// Fetch assigned customers with their active or completed Susu Card
$stmtCust = $pdo->prepare("
    SELECT c.*, 
           act_sc.id as active_card_id,
           act_sc.card_number as active_card_number,
           act_sc.daily_amount as active_daily_amount,
           act_sc.spaces_filled as active_spaces_filled,
           act_sc.total_spaces as active_total_spaces,
           act_sc.total_saved as active_total_saved,
           comp_sc.id as completed_card_id,
           comp_sc.card_number as completed_card_number,
           comp_sc.daily_amount as completed_daily_amount,
           comp_sc.spaces_filled as completed_spaces_filled,
           comp_sc.total_spaces as completed_total_spaces,
           comp_sc.total_saved as completed_total_saved,
           comp_p.id as completed_payout_id,
           latest_sc.id as latest_card_id
    FROM customers c
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
    WHERE c.assigned_collector_id = ? AND c.is_active = 1
    ORDER BY (comp_sc.id IS NOT NULL) DESC, c.full_name ASC
");
$stmtCust->execute([$collectorId]);
$myCustomers = $stmtCust->fetchAll(PDO::FETCH_ASSOC);

foreach ($myCustomers as &$c) {
    if (!empty($c['completed_card_id'])) {
        $c['card_id'] = $c['completed_card_id'];
        $c['card_number'] = $c['completed_card_number'];
        $c['daily_amount'] = (float)$c['completed_daily_amount'];
        $c['spaces_filled'] = (int)$c['completed_spaces_filled'];
        $c['total_spaces'] = (int)$c['completed_total_spaces'];
        $c['total_saved'] = (float)$c['completed_total_saved'];
        $c['card_status'] = 'completed';
    } elseif (!empty($c['active_card_id'])) {
        $c['card_id'] = $c['active_card_id'];
        $c['card_number'] = $c['active_card_number'];
        $c['daily_amount'] = (float)$c['active_daily_amount'];
        $c['spaces_filled'] = (int)$c['active_spaces_filled'];
        $c['total_spaces'] = (int)$c['active_total_spaces'];
        $c['total_saved'] = (float)$c['active_total_saved'];
        $c['card_status'] = 'active';
    } else {
        $c['card_id'] = null;
        $c['card_number'] = null;
        $c['daily_amount'] = 20.00;
        $c['spaces_filled'] = 0;
        $c['total_spaces'] = 31;
        $c['total_saved'] = 0.00;
        $c['card_status'] = null;
    }
}
unset($c);

$totalAssignedCount = count($myCustomers);
$activeCardsCount = count(array_filter($myCustomers, fn($c) => !empty($c['card_id'])));

// Fetch active admin details for direct messaging
$stmtAdmin = $pdo->query("SELECT phone, full_name FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
$adminUser = $stmtAdmin ? $stmtAdmin->fetch() : null;
$adminPhone = $adminUser ? $adminUser['phone'] : '0553224837';
$adminName = $adminUser ? $adminUser['full_name'] : 'Admin';
$cleanAdminPhone = preg_replace('/^0/', '233', preg_replace('/\D/', '', $adminPhone));

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
                <a href="record_deposit.php" class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-sm font-extrabold shadow-lg transition flex items-center">
                    <i class="fa-solid fa-circle-plus mr-1.5"></i>
                    <span>Record Deposit</span>
                </a>

                <!-- Secondary CTA (Hick's Law: Visually lighter) -->
                <a href="daily_handover.php" class="btn-touch bg-white/10 hover:bg-white/20 text-white border border-white/25 text-xs font-bold transition flex items-center">
                    <i class="fa-solid fa-handshake mr-1.5"></i>
                    <span>Handover Cash</span>
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
                <div class="font-extrabold text-sm text-white"><?= $totalAssignedCount ?> clients <span class="text-xs font-normal text-cornflower_ocean-900">(<?= $activeCardsCount ?> active cards)</span></div>
            </div>
        </div>
    </div>

    <!-- Assigned Customers Section -->
    <div class="section-card">
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-slate-800">My Assigned Customers</h2>
                <p class="text-xs text-slate-500">Tap "Deposit" on any customer or alert admin if a new card is required.</p>
            </div>
            
            <!-- Quick Search Filter -->
            <div class="w-full sm:w-64">
                <input type="text" id="customer_search" placeholder="Search name or account..."
                       class="w-full px-3 py-2 text-xs rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none transition">
            </div>
        </div>

        <div id="search_empty_notice" class="hidden">
            <div class="empty-state">
                <div class="empty-state-icon bg-slate-100 text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-xl"></i>
                </div>
                <div class="empty-state-title">No Matches Found</div>
                <div class="empty-state-text">Try a different search term.</div>
            </div>
        </div>

        <!-- Customer Cards (Gestalt Similarity: Consistent card style) -->
        <div class="space-y-3">
            <?php if (empty($myCustomers)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon bg-blue-50 text-steel_azure">
                        <i class="fa-solid fa-users text-2xl"></i>
                    </div>
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
                                <span><i class="fa-solid fa-location-dot text-slate-400 mr-1"></i><?= htmlspecialchars($cust['location'] ?: 'No location') ?></span>
                                <span><i class="fa-solid fa-phone text-slate-400 mr-1"></i><?= htmlspecialchars($cust['phone']) ?></span>
                            </div>

                            <!-- Card Progress -->
                            <?php if ($cust['card_id'] && $cust['card_status'] === 'completed'): ?>
                                <div class="mt-2 text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2 flex items-center justify-between gap-2">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-award text-emerald-600 text-sm"></i>
                                        <span>31/31 Completed &bull; Saved: <strong class="text-emerald-950 font-black"><?= format_money($cust['total_saved']) ?></strong></span>
                                    </span>
                                    <span class="text-[10px] uppercase tracking-wider font-extrabold px-2 py-0.5 rounded-md bg-white border border-emerald-300">
                                        <?= !empty($cust['completed_payout_id']) ? '⏳ Pending' : '🎯 Ready' ?>
                                    </span>
                                </div>
                            <?php elseif ($cust['card_id']): ?>
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
                                <div class="mt-2 text-xs font-semibold text-amber-700 bg-amber-50/80 border border-amber-200/80 rounded-lg px-2.5 py-1.5 inline-flex items-center gap-1.5">
                                    <i class="fa-solid fa-triangle-exclamation text-amber-600 text-xs"></i>
                                    <span>No active card</span>
                                    <span class="text-slate-400">&bull;</span>
                                    <span class="text-slate-500 font-normal">Admin can start a new card</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons (Hick's Law: Primary + Secondary) -->
                        <div class="flex items-center gap-2 pt-2 sm:pt-0 border-t sm:border-t-0 border-silver-600/40">
                            <?php if ($cust['card_id'] && $cust['card_status'] === 'completed'): ?>
                                <a href="request_payout.php?card_id=<?= $cust['card_id'] ?>" 
                                   class="btn-touch flex-1 sm:flex-none bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-black px-3.5 py-2 rounded-xl shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-paper-plane text-xs"></i>
                                    <span>Request Payout</span>
                                </a>
                                <a href="view_card.php?id=<?= $cust['card_id'] ?>" 
                                   class="btn-touch flex-1 sm:flex-none bg-white hover:bg-platinum text-steel_azure border border-steel_azure text-xs font-bold px-3 py-2 rounded-xl transition flex items-center justify-center gap-1">
                                    <i class="fa-solid fa-id-card text-xs"></i>
                                    <span>Card</span>
                                </a>
                            <?php elseif ($cust['card_id']): ?>
                                <a href="record_deposit.php?customer_id=<?= $cust['id'] ?>" 
                                   class="btn-touch flex-1 sm:flex-none bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs font-extrabold px-3 py-2 shadow-sm transition flex items-center justify-center gap-1">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                    <span>Deposit</span>
                                </a>
                                <a href="view_card.php?id=<?= $cust['card_id'] ?>" 
                                   class="btn-touch flex-1 sm:flex-none bg-white hover:bg-platinum text-steel_azure border border-steel_azure text-xs font-bold px-3 py-2 transition flex items-center justify-center gap-1">
                                    <i class="fa-solid fa-id-card text-xs"></i>
                                    <span>Card</span>
                                </a>
                            <?php else: ?>
                                <button type="button" 
                                        onclick='openCardAlertModal(<?= htmlspecialchars(json_encode([
                                            "id" => $cust["id"],
                                            "full_name" => $cust["full_name"],
                                            "account_number" => $cust["account_number"],
                                            "phone" => $cust["phone"],
                                            "location" => $cust["location"] ?: "No location specified"
                                        ]), ENT_QUOTES) ?>)'
                                        class="btn-touch flex-1 sm:flex-none bg-steel_azure hover:bg-steel_azure-400 text-white text-xs font-extrabold px-3.5 py-2 rounded-xl shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-bell text-xs"></i>
                                    <span>Alert Admin</span>
                                </button>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- ============================================================
     ALERT ADMIN CONFIRMATION MODAL (HCI & Jakob's Law)
     ============================================================ -->
<div id="alert_admin_modal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm hidden" role="dialog" aria-modal="true" aria-labelledby="alert_modal_title">
    <div class="bg-white rounded-2xl border border-silver-600 shadow-2xl max-w-md w-full overflow-hidden transform transition-all scale-95 duration-200 my-auto max-h-[90vh] flex flex-col" id="alert_admin_modal_box">
        <!-- Header -->
        <div class="p-4 sm:p-5 bg-gradient-to-r from-steel_azure to-steel_azure-400 text-white flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-bell text-base"></i>
                </div>
                <div>
                    <h3 id="alert_modal_title" class="font-extrabold text-sm sm:text-base leading-tight">Alert Admin to Open Card</h3>
                    <p class="text-xs text-white/75 mt-0.5">Request a new 31-space Susu card for client.</p>
                </div>
            </div>
            <button type="button" onclick="closeCardAlertModal()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer" title="Close" aria-label="Close modal">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <div class="p-5 sm:p-6 space-y-4 overflow-y-auto flex-1">
            <!-- Customer Identity Summary Card -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-steel_azure text-white font-black flex items-center justify-center text-sm flex-shrink-0 shadow-xs" id="modal_cust_avatar">
                    --
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <div class="text-sm font-extrabold text-slate-800 truncate" id="modal_cust_name">-</div>
                        <span class="text-[10px] font-mono text-slate-500 bg-white border border-slate-200 px-1.5 py-0.2 rounded font-bold" id="modal_cust_acc">-</span>
                    </div>
                    <div class="text-[11px] text-slate-500 font-medium truncate mt-0.5" id="modal_cust_loc">-</div>
                </div>
            </div>

            <!-- Context Info -->
            <p class="text-xs text-slate-600 leading-relaxed">
                As a field collector, you cannot issue cards directly. Choose how you want to notify the office administrator:
            </p>

            <!-- Action Buttons Stack (Fitts's Law: Large Thumb Targets) -->
            <div class="space-y-2.5 pt-1">
                <!-- 1. Send In-App Notification (Primary) -->
                <button type="button" id="modal_send_inapp_btn" onclick="sendModalAdminAlert()"
                        class="w-full btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white font-extrabold text-xs sm:text-sm py-3 px-4 rounded-xl shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-bell text-xs"></i>
                    <span id="modal_send_inapp_text">Send In-App Alert to Admin</span>
                </button>

                <!-- 2. Direct WhatsApp Link -->
                <a id="modal_wa_link" href="#" target="_blank" rel="noopener noreferrer"
                   class="w-full btn-touch bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs sm:text-sm py-3 px-4 rounded-xl shadow-xs transition flex items-center justify-center gap-2 cursor-pointer">
                    <i class="fa-brands fa-whatsapp text-sm"></i>
                    <span>WhatsApp Admin (<?= htmlspecialchars($adminPhone) ?>)</span>
                </a>
            </div>

            <!-- In-Modal Success Message -->
            <div id="modal_alert_feedback" class="hidden text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 p-3 rounded-xl flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base flex-shrink-0"></i>
                <span>Notification sent! The office administrator has been alerted in real time.</span>
            </div>
        </div>

        <div class="p-4 border-t border-silver-600 flex items-center justify-end bg-slate-50/70 flex-shrink-0">
            <button type="button" onclick="closeCardAlertModal()" class="btn-touch bg-white text-slate-600 border border-silver-600 hover:bg-slate-100 text-xs font-bold px-4 py-2 rounded-xl transition">
                Dismiss
            </button>
        </div>
    </div>
</div>

<script>
let currentModalCustomerId = null;
const adminPhone = <?= json_encode($cleanAdminPhone) ?>;
const adminName = <?= json_encode($adminName) ?>;

function openCardAlertModal(cust) {
    currentModalCustomerId = cust.id;
    
    // Set customer details
    document.getElementById('modal_cust_name').textContent = cust.full_name;
    document.getElementById('modal_cust_acc').textContent = '#' + cust.account_number;
    document.getElementById('modal_cust_loc').textContent = cust.location || cust.phone || '';
    
    // Set avatar initials
    const initials = (cust.full_name || '').split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
    document.getElementById('modal_cust_avatar').textContent = initials || 'CU';

    // Prepare WhatsApp URL
    const waText = encodeURIComponent(`Hello ${adminName}, customer ${cust.full_name} (#${cust.account_number}) needs a new Susu Card opened so I can record their deposit.`);
    document.getElementById('modal_wa_link').href = `https://wa.me/${adminPhone}?text=${waText}`;

    // Reset button & feedback
    const btn = document.getElementById('modal_send_inapp_btn');
    const btnText = document.getElementById('modal_send_inapp_text');
    const feedback = document.getElementById('modal_alert_feedback');
    btn.disabled = false;
    btn.className = 'w-full btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white font-extrabold text-xs sm:text-sm py-3 px-4 rounded-xl shadow-md transition flex items-center justify-center gap-2 cursor-pointer';
    btnText.textContent = 'Send In-App Alert to Admin';
    feedback.classList.add('hidden');

    // Ensure modal is attached directly to document.body to prevent any container/scroll traps
    const modal = document.getElementById('alert_admin_modal');
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    // Lock body scroll
    document.body.classList.add('overflow-hidden');

    // Show modal with smooth scale animation
    const box = document.getElementById('alert_admin_modal_box');
    modal.classList.remove('hidden');
    setTimeout(() => {
        box.classList.remove('scale-95');
        box.classList.add('scale-100');
    }, 10);
}

function closeCardAlertModal() {
    const modal = document.getElementById('alert_admin_modal');
    const box = document.getElementById('alert_admin_modal_box');
    if (!modal) return;
    
    document.body.classList.remove('overflow-hidden');
    box.classList.remove('scale-100');
    box.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 150);
}

function sendModalAdminAlert() {
    if (!currentModalCustomerId) return;
    const btn = document.getElementById('modal_send_inapp_btn');
    const btnText = document.getElementById('modal_send_inapp_text');
    const feedback = document.getElementById('modal_alert_feedback');

    btn.disabled = true;
    btnText.textContent = 'Sending alert...';

    const formData = new FormData();
    formData.append('action', 'alert_admin_card');
    formData.append('customer_id', currentModalCustomerId);

    fetch('api_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            btn.className = 'w-full btn-touch bg-emerald-600 text-white font-extrabold text-xs sm:text-sm py-3 px-4 rounded-xl shadow-xs transition flex items-center justify-center gap-2 cursor-default';
            btnText.innerHTML = '<i class="fa-solid fa-check"></i> Alert Sent to Admin';
            feedback.classList.remove('hidden');
        } else {
            btn.disabled = false;
            btnText.textContent = 'Send In-App Alert to Admin';
            alert(data.error || 'Could not send alert. Please use WhatsApp.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btnText.textContent = 'Send In-App Alert to Admin';
        alert('Network error. Please use the WhatsApp button.');
    });
}

// Close on backdrop click & ESC
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('alert_admin_modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeCardAlertModal();
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeCardAlertModal();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
