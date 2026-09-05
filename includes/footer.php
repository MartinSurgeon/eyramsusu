    </main>
    <?php if ($currentUser): ?>
        </div><!-- close main content column -->
    </div><!-- close layout wrapper -->
    <?php endif; ?>

    <?php if ($currentUser): ?>
        <!-- Mobile Bottom Action Bar (Thumb-Friendly for Walking in Markets - Fitts's Law) -->
        <?php
        // Mobile active nav detection
        function isMobileNavActive($pages, $currentPage) {
            if (is_array($pages)) {
                return in_array($currentPage, $pages) ? 'mobile-nav-active' : '';
            }
            return $currentPage === $pages ? 'mobile-nav-active' : '';
        }
        $mobilePage = basename($_SERVER['SCRIPT_NAME']);
        $homePages = $currentUser['role'] === 'admin' ? 'admin_dashboard.php' : 'collector_dashboard.php';
        ?>
            <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-md border-t border-slate-200 shadow-2xl z-50 flex items-center justify-around py-2 px-3" role="navigation" aria-label="Mobile navigation">
            
            <a href="<?= $currentUser['role'] === 'admin' ? 'admin_dashboard.php' : 'collector_dashboard.php' ?>" class="flex flex-col items-center justify-center p-2 text-xs font-bold text-slate-500 hover:text-steel_azure transition <?= isMobileNavActive($homePages, $mobilePage) ?>">
                <i class="fa-solid fa-house text-lg mb-1"></i>
                <span class="text-[11px]">Home</span>
            </a>

            <a href="customers.php" class="flex flex-col items-center justify-center p-2 text-xs font-bold text-slate-500 hover:text-steel_azure transition <?= isMobileNavActive(['customers.php', 'add_customer.php'], $mobilePage) ?>">
                <i class="fa-solid fa-users text-lg mb-1"></i>
                <span class="text-[11px]">Customers</span>
            </a>

            <!-- Prominent Central Action Button (Hick's Law & Fitts's Law): Daily Records for Admin, Collect for Collector -->
            <?php if ($currentUser['role'] === 'admin'): ?>
                <a href="reports.php" class="flex flex-col items-center justify-center -mt-6 group" title="Daily Records">
                    <div class="w-14 h-14 bg-gradient-to-tr from-steel_azure to-steel_azure-400 text-white rounded-2xl shadow-xl flex items-center justify-center text-2xl font-black group-hover:scale-105 group-active:scale-95 transition-transform border-4 border-white">
                        <i class="fa-solid fa-chart-simple text-2xl"></i>
                    </div>
                    <span class="text-[10px] font-black text-steel_azure mt-1 tracking-tight uppercase">Records</span>
                </a>
            <?php else: ?>
                <a href="record_deposit.php" class="flex flex-col items-center justify-center -mt-6 group" title="Collect Money">
                    <div class="w-14 h-14 bg-gradient-to-tr from-pumpkin_spice to-pumpkin_spice-600 text-white rounded-2xl shadow-xl flex items-center justify-center text-2xl font-black group-hover:scale-105 group-active:scale-95 transition-transform border-4 border-white">
                        <i class="fa-solid fa-plus text-2xl"></i>
                    </div>
                    <span class="text-[10px] font-black text-pumpkin_spice mt-1 tracking-tight uppercase">Collect</span>
                </a>
            <?php endif; ?>

            <?php if ($currentUser['role'] === 'admin'): ?>
                <a href="payouts.php" class="flex flex-col items-center justify-center p-2 text-xs font-bold text-slate-500 hover:text-steel_azure transition <?= isMobileNavActive(['payouts.php', 'request_payout.php'], $mobilePage) ?>">
                    <i class="fa-solid fa-wallet text-lg mb-1"></i>
                    <span class="text-[11px]">Cashout</span>
                </a>
            <?php else: ?>
                <a href="request_payout.php" class="flex flex-col items-center justify-center p-2 text-xs font-bold text-slate-500 hover:text-steel_azure transition <?= isMobileNavActive('request_payout.php', $mobilePage) ?>">
                    <i class="fa-solid fa-wallet text-lg mb-1"></i>
                    <span class="text-[11px]">Cashout</span>
                </a>
            <?php endif; ?>

            <a href="daily_handover.php" class="flex flex-col items-center justify-center p-2 text-xs font-bold text-slate-500 hover:text-steel_azure transition <?= isMobileNavActive('daily_handover.php', $mobilePage) ?>">
                <i class="fa-solid fa-handshake text-lg mb-1"></i>
                <span class="text-[11px]">Handover</span>
            </a>

        </nav>
    <?php endif; ?>

    <!-- Desktop Footer (Peak-End Rule: Confidence-Building Close) -->
    <footer class="hidden md:block mt-auto py-4 border-t border-slate-200 bg-white">
        <div class="max-w-7xl mx-auto px-6 flex items-center justify-between gap-4 flex-wrap">
            <!-- Left: Branding + Security Indicator -->
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-xs text-slate-500 font-medium">
                    <strong class="text-slate-700">Eyram Susu</strong> &copy; <?= date('Y') ?> &bull; Digital 31-Space Susu Passbook System
                </span>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-emerald-50 border border-emerald-200 rounded-full text-[10px] font-bold text-emerald-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
                    Bank-Grade Data Security
                </span>
            </div>

            <!-- Right: Developer Credit + WhatsApp -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="text-[11px] text-slate-400 font-medium">Developed by <strong class="text-slate-600">Mart IT Services</strong></span>
                <a href="https://wa.me/233557869989" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-500 hover:bg-emerald-600 text-white text-[11px] font-bold rounded-full transition shadow-sm hover:shadow-md">
                    <i class="fa-brands fa-whatsapp text-sm"></i>
                    <span>0557869989</span>
                </a>
            </div>
        </div>
    </footer>

    <!-- ═══════════════════════════════════════════════════════════════
         SIGN OUT CONFIRMATION MODAL
         Fitts's Law: Large "Stay Signed In" is default-focused (prevents accidents)
         Hick's Law: Only two choices — stay or leave.
         ═══════════════════════════════════════════════════════════════ -->
    <?php if ($currentUser): ?>
    <div id="signout_modal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden" role="dialog" aria-modal="true" aria-labelledby="signout_modal_title">
        <div class="bg-white rounded-2xl border border-silver-600 shadow-2xl max-w-sm w-full overflow-hidden transform transition-all scale-95 duration-200" id="signout_modal_box">

            <!-- Header -->
            <div class="p-5 bg-gradient-to-r from-slate-800 to-slate-700 text-white flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-500/20 border border-red-500/40 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-right-from-bracket text-red-400 text-sm"></i>
                </div>
                <div>
                    <h3 id="signout_modal_title" class="font-extrabold text-sm leading-tight">Sign Out?</h3>
                    <p class="text-xs text-slate-400 mt-0.5">You will be returned to the login screen.</p>
                </div>
            </div>

            <!-- User Identity Card (Who is signing out) -->
            <div class="px-5 pt-4 pb-2">
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-steel_azure text-white font-black flex items-center justify-center text-sm flex-shrink-0 shadow-xs">
                        <?= strtoupper(substr($currentUser['full_name'], 0, 2)) ?>
                    </div>
                    <div>
                        <div class="text-sm font-extrabold text-slate-800"><?= htmlspecialchars($currentUser['full_name']) ?></div>
                        <div class="text-[11px] text-slate-500 font-medium capitalize">
                            <?= $currentUser['role'] === 'admin' ? 'Office Manager' : 'Susu Collector' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="px-5 pb-5 pt-3 flex items-center gap-3">
                <!-- Cancel (default focused — Fitts's Law prevents accidental logout) -->
                <button type="button" id="signout_stay_btn" onclick="closeSignOutModal()"
                        class="flex-1 btn-touch px-4 py-2.5 bg-steel_azure hover:bg-steel_azure-400 text-white text-sm font-extrabold rounded-xl shadow-sm transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-shield text-xs"></i>
                    <span>Stay Signed In</span>
                </button>

                <!-- Confirm logout -->
                <a href="logout.php"
                   class="flex-1 btn-touch px-4 py-2.5 bg-white hover:bg-red-50 text-red-600 border border-red-200 hover:border-red-400 text-sm font-bold rounded-xl transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-right-from-bracket text-xs"></i>
                    <span>Yes, Sign Out</span>
                </a>
            </div>
        </div>
    </div>

    <script>
    function openSignOutModal() {
        const modal = document.getElementById('signout_modal');
        const box   = document.getElementById('signout_modal_box');
        if (!modal) return;
        modal.classList.remove('hidden');
        // Animate in
        requestAnimationFrame(() => {
            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        });
        // Focus the "Stay" button (Fitts's Law — safe default)
        setTimeout(() => {
            const stayBtn = document.getElementById('signout_stay_btn');
            if (stayBtn) stayBtn.focus();
        }, 50);
    }

    function closeSignOutModal() {
        const modal = document.getElementById('signout_modal');
        const box   = document.getElementById('signout_modal_box');
        if (!modal) return;
        box.classList.remove('scale-100');
        box.classList.add('scale-95');
        setTimeout(() => modal.classList.add('hidden'), 150);
    }

    // Keyboard: Esc closes, Enter on focused button confirms
    document.addEventListener('keydown', function(e) {
        const modal = document.getElementById('signout_modal');
        if (!modal || modal.classList.contains('hidden')) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            closeSignOutModal();
        }
    });

    // Click outside backdrop closes
    document.getElementById('signout_modal').addEventListener('click', function(e) {
        if (e.target === this) closeSignOutModal();
    });
    </script>
    <?php endif; ?>

    <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
    <?php
    $collectorDrawerData = get_collectors_distribution_summary();
    $drawerCollectors = $collectorDrawerData['collectors'];
    $drawerUnassigned = $collectorDrawerData['unassigned_count'];
    $drawerTotalCustomers = $collectorDrawerData['total_customers'];
    $drawerTotalAssigned = array_sum(array_column($drawerCollectors, 'customer_count'));
    ?>
    <!-- ═══════════════════════════════════════════════════════════════
         GLOBAL COLLECTORS & ROUTES DRAWER (ADMIN ONLY)
         HCI & Fitts's Law: Accessible from Mobile Header Hamburger & Customers Page
         Hick's Law: Clear primary CTA (+ Register New Collector), followed by scannable agent cards.
         ═══════════════════════════════════════════════════════════════ -->
    <div id="collectors_drawer_backdrop" class="fixed inset-0 z-[90] bg-slate-900/50 backdrop-blur-sm hidden transition-opacity duration-300 opacity-0 cursor-pointer" onclick="toggleCollectorsDrawer()"></div>
    <div id="collectors_drawer" class="fixed top-0 right-0 z-[95] h-full w-full max-w-sm bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-out flex flex-col">
        
        <!-- Drawer Header -->
        <div class="p-4 sm:p-5 bg-gradient-to-r from-steel_azure to-steel_azure-400 text-white flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-users-gear text-base"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-sm sm:text-base leading-tight">Collectors & Routes</h3>
                    <p class="text-xs text-white/75 mt-0.5"><?= count($drawerCollectors) ?> active field collectors</p>
                </div>
            </div>
            <button type="button" onclick="toggleCollectorsDrawer()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer" aria-label="Close drawer">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Quick Action: Register New Collector (Primary CTA - Hick's Law) -->
        <div class="p-3 bg-platinum/60 border-b border-silver-600/70 flex-shrink-0">
            <a href="collectors.php" class="w-full btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white font-extrabold text-xs sm:text-sm py-2.5 px-4 rounded-xl shadow-sm transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-user-plus text-xs"></i>
                <span>Register New Collector</span>
            </a>
        </div>

        <!-- Drawer Body (scrollable) -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            <?php if (empty($drawerCollectors)): ?>
                <div class="p-6 text-center text-slate-500">
                    <i class="fa-solid fa-users-slash text-2xl text-slate-300 mb-2"></i>
                    <p class="text-xs font-bold">No collectors registered yet</p>
                </div>
            <?php else: ?>
                <?php 
                foreach ($drawerCollectors as $idx => $collector): 
                    $custCount = (int)$collector['customer_count'];
                    $activeCount = (int)$collector['active_cards'];
                    $cashInHand = (float)$collector['cash_in_hand'];
                    $barPct = $drawerTotalAssigned > 0 ? round(($custCount / $drawerTotalAssigned) * 100) : 0;
                    
                    $colors = [
                        ['bg-blue-50', 'text-steel_azure', 'bg-steel_azure', 'border-blue-200'],
                        ['bg-emerald-50', 'text-emerald-600', 'bg-emerald-500', 'border-emerald-200'],
                        ['bg-purple-50', 'text-purple-600', 'bg-purple-500', 'border-purple-200'],
                        ['bg-pink-50', 'text-pink-600', 'bg-pink-500', 'border-pink-200'],
                        ['bg-amber-50', 'text-amber-600', 'bg-amber-500', 'border-amber-200'],
                    ];
                    $c = $colors[$idx % count($colors)];
                ?>
                    <div class="p-3.5 rounded-xl border <?= $c[3] ?> <?= $c[0] ?>/50 hover:shadow-sm transition-all duration-200">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg <?= $c[0] ?> <?= $c[1] ?> flex items-center justify-center text-xs font-black flex-shrink-0">
                                    <?= strtoupper(substr($collector['full_name'], 0, 2)) ?>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-slate-800"><?= htmlspecialchars($collector['full_name']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono flex items-center gap-1.5">
                                        <?php if (!empty($collector['phone'])): ?>
                                            <a href="tel:<?= htmlspecialchars($collector['phone']) ?>" class="hover:text-steel_azure transition">
                                                <i class="fa-solid fa-phone text-[9px] mr-0.5"></i><?= htmlspecialchars($collector['phone']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span>No phone</span>
                                        <?php endif; ?>
                                        <span>&bull;</span>
                                        <span>@<?= htmlspecialchars($collector['username']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-base font-black <?= $c[1] ?>"><?= $custCount ?></div>
                                <div class="text-[9px] text-slate-400 font-semibold uppercase">clients</div>
                            </div>
                        </div>
                        
                        <!-- Route distribution bar -->
                        <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden mb-1.5">
                            <div class="<?= $c[2] ?> h-1.5 rounded-full transition-all duration-500" style="width: <?= $barPct ?>%"></div>
                        </div>
                        
                        <div class="flex items-center justify-between text-[10px] text-slate-500 font-medium pt-1 border-t border-silver-600/30">
                            <span><strong class="text-slate-700"><?= $activeCount ?></strong> active cards</span>
                            
                            <?php if ($cashInHand > 0): ?>
                                <a href="daily_handover.php" class="inline-flex items-center gap-1 font-bold text-pumpkin_spice bg-orange-50 px-1.5 py-0.5 rounded border border-orange-200" title="Cash awaiting handover">
                                    <i class="fa-solid fa-sack-dollar text-[9px]"></i>
                                    <span><?= format_money($cashInHand) ?></span>
                                </a>
                            <?php else: ?>
                                <span class="text-slate-400 font-normal">GH₵ 0 cash</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($drawerUnassigned > 0): ?>
                <div class="p-3.5 rounded-xl border border-amber-200 bg-amber-50/50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center text-xs font-black flex-shrink-0">
                                <i class="fa-solid fa-user-slash text-xs"></i>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-amber-800">Unassigned Clients</div>
                                <div class="text-[10px] text-amber-600">Pending route allocation</div>
                            </div>
                        </div>
                        <a href="collectors.php" class="btn-touch px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white text-xs font-extrabold rounded-lg transition shadow-2xs">
                            Assign
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Drawer Footer -->
        <div class="p-4 border-t border-silver-600/70 bg-platinum/50 flex-shrink-0 flex items-center justify-between">
            <div class="text-xs">
                <span class="text-slate-500 font-medium">Assigned:</span>
                <span class="font-black text-steel_azure ml-1"><?= $drawerTotalAssigned ?> / <?= $drawerTotalCustomers ?></span>
            </div>
            <a href="collectors.php" class="text-xs font-bold text-steel_azure hover:text-cornflower_ocean transition flex items-center gap-1.5 group">
                <span>Manage Hub</span>
                <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-0.5 transition-transform"></i>
            </a>
        </div>
    </div>

    <script>
    function toggleCollectorsDrawer() {
        const drawer = document.getElementById('collectors_drawer');
        const backdrop = document.getElementById('collectors_drawer_backdrop');
        if (!drawer || !backdrop) return;

        const isOpen = !drawer.classList.contains('translate-x-full');
        
        if (isOpen) {
            // Close
            drawer.classList.add('translate-x-full');
            backdrop.classList.add('opacity-0');
            setTimeout(() => backdrop.classList.add('hidden'), 300);
            document.body.style.overflow = '';
        } else {
            // Open
            backdrop.classList.remove('hidden');
            requestAnimationFrame(() => {
                backdrop.classList.remove('opacity-0');
                drawer.classList.remove('translate-x-full');
            });
            document.body.style.overflow = 'hidden';
        }
    }

    // Backwards-compatibility alias for existing customers.php triggers
    window.toggleContributorsDrawer = toggleCollectorsDrawer;

    // Close drawer on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const drawer = document.getElementById('collectors_drawer');
            if (drawer && !drawer.classList.contains('translate-x-full')) {
                toggleCollectorsDrawer();
            }
        }
    });
    </script>
    <?php endif; ?>

    <!-- App JavaScript (Auto-resolves assets/js/ or js/) -->
    <script src="<?= get_asset_url('js') ?>"></script>
</body>
</html>
