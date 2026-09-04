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

    <!-- App JavaScript (Auto-resolves assets/js/ or js/) -->
    <script src="<?= get_asset_url('js') ?>"></script>
</body>
</html>
