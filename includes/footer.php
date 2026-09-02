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
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span class="text-[11px]">Home</span>
            </a>

            <a href="customers.php" class="flex flex-col items-center justify-center p-2 text-xs font-bold text-slate-500 hover:text-steel_azure transition <?= isMobileNavActive(['customers.php', 'add_customer.php'], $mobilePage) ?>">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span class="text-[11px]">Customers</span>
            </a>

            <!-- Prominent Central Action Button (Hick's Law & Fitts's Law): Daily Records for Admin, Collect for Collector -->
            <?php if ($currentUser['role'] === 'admin'): ?>
                <a href="reports.php" class="flex flex-col items-center justify-center -mt-6 group" title="Daily Records">
                    <div class="w-14 h-14 bg-gradient-to-tr from-steel_azure to-steel_azure-400 text-white rounded-2xl shadow-xl flex items-center justify-center text-2xl font-black group-hover:scale-105 group-active:scale-95 transition-transform border-4 border-white">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-black text-steel_azure mt-1 tracking-tight uppercase">Records</span>
                </a>
            <?php else: ?>
                <a href="record_deposit.php" class="flex flex-col items-center justify-center -mt-6 group" title="Collect Money">
                    <div class="w-14 h-14 bg-gradient-to-tr from-pumpkin_spice to-pumpkin_spice-600 text-white rounded-2xl shadow-xl flex items-center justify-center text-2xl font-black group-hover:scale-105 group-active:scale-95 transition-transform border-4 border-white">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-black text-pumpkin_spice mt-1 tracking-tight uppercase">Collect</span>
                </a>
            <?php endif; ?>

            <?php if ($currentUser['role'] === 'admin'): ?>
                <a href="payouts.php" class="flex flex-col items-center justify-center p-2 text-xs font-bold text-slate-500 hover:text-steel_azure transition <?= isMobileNavActive(['payouts.php', 'request_payout.php'], $mobilePage) ?>">
                    <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-[11px]">Cashout</span>
                </a>
            <?php else: ?>
                <a href="request_payout.php" class="flex flex-col items-center justify-center p-2 text-xs font-bold text-slate-500 hover:text-steel_azure transition <?= isMobileNavActive('request_payout.php', $mobilePage) ?>">
                    <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span class="text-[11px]">Cashout</span>
                </a>
            <?php endif; ?>

            <a href="daily_handover.php" class="flex flex-col items-center justify-center p-2 text-xs font-bold text-slate-500 hover:text-steel_azure transition <?= isMobileNavActive('daily_handover.php', $mobilePage) ?>">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                <span class="text-[11px]">Handover</span>
            </a>

        </nav>
    <?php endif; ?>

    <!-- Desktop Footer (Peak-End Rule: Confidence-Building Close) -->
    <footer class="hidden md:block mt-auto py-6 border-t border-slate-200 bg-white text-center">
        <div class="max-w-7xl mx-auto px-4">
            <p class="text-xs text-slate-500 font-medium">
                <strong class="text-steel_azure font-heading">Eyram Susu</strong> &copy; <?= date('Y') ?> &bull; Traditional 31-Space Daily Savings Book
            </p>
            <p class="text-[11px] text-slate-400 mt-1">
                Need help? Contact your office administrator &bull; Your savings data is stored securely
            </p>
            <p class="text-[11px] text-slate-500 font-medium mt-2 flex items-center justify-center gap-1.5">
                <span>Developed by <strong class="text-slate-700">Mart IT Services</strong></span>
                <span class="text-slate-300">&bull;</span>
                <a href="https://wa.me/233557869989" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-emerald-600 hover:text-emerald-700 font-bold hover:underline">
                    <span>WhatsApp 0557869989</span>
                </a>
            </p>
        </div>
    </footer>

    <!-- App JavaScript (Auto-resolves assets/js/ or js/) -->
    <script src="<?= get_asset_url('js') ?>"></script>
</body>
</html>
