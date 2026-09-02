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
    <footer class="hidden md:block mt-auto py-6 border-t border-slate-200 bg-white text-center">
        <div class="max-w-7xl mx-auto px-4">
            <p class="text-xs text-slate-400 font-medium">
                Eyram Susu &copy; <?= date('Y') ?> &bull; Digital 31-Space Susu Passbook System
            </p>
            <p class="text-xs text-slate-400 mt-1">
                Need help? Contact your office administrator &bull; Your savings data is stored securely
            </p>
            <p class="text-[11px] text-slate-500 font-medium mt-2 flex items-center justify-center gap-1.5">
                <span>Developed by <strong class="text-slate-700">Mart IT Services</strong></span>
                <span class="text-slate-300">&bull;</span>
                <a href="https://wa.me/233557869989" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-emerald-600 hover:text-emerald-700 font-bold hover:underline">
                    <i class="fa-brands fa-whatsapp text-emerald-600 text-sm"></i>
                    <span>WhatsApp 0557869989</span>
                </a>
            </p>
        </div>
    </footer>

    <!-- App JavaScript (Auto-resolves assets/js/ or js/) -->
    <script src="<?= get_asset_url('js') ?>"></script>
</body>
</html>
