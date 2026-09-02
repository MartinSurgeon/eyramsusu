<?php
// includes/header.php - Top Navigation & Global Layout
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$currentUser = get_logged_in_user();
$pageTitle = isset($pageTitle) ? $pageTitle . ' - Eyram Susu' : 'Eyram Susu';

// Calculate Money with Collector today
$collectorMoneyWithMe = 0.00;
if ($currentUser && $currentUser['role'] === 'collector') {
    $collectorMoneyWithMe = get_collector_cash_in_hand($currentUser['id']);
}

// Fetch user notifications
$userNotifications = [];
$unreadNotificationCount = 0;
if ($currentUser) {
    $unreadNotificationCount = get_unread_notification_count($currentUser['id'], $currentUser['role']);
    $userNotifications = get_user_notifications($currentUser['id'], $currentUser['role'], 10);
}

// Detect current page for active nav state (Jakob's Law)
$currentPage = basename($_SERVER['SCRIPT_NAME']);
function isNavActive($page, $currentPage) {
    if (is_array($page)) {
        return in_array($currentPage, $page) ? 'nav-link-active' : '';
    }
    return $currentPage === $page ? 'nav-link-active' : '';
}
function ariaCurrent($page, $currentPage) {
    if (is_array($page)) {
        return in_array($currentPage, $page) ? 'aria-current="page"' : '';
    }
    return $currentPage === $page ? 'aria-current="page"' : '';
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        pumpkin_spice: {
                            DEFAULT: '#ff6700',
                            100: '#331400',
                            200: '#662900',
                            300: '#993d00',
                            400: '#cc5200',
                            500: '#ff6700',
                            600: '#ff8533',
                            700: '#ffa366',
                            800: '#ffc299',
                            900: '#ffe0cc'
                        },
                        platinum: {
                            DEFAULT: '#ebebeb',
                            100: '#2f2f2f',
                            200: '#5e5e5e',
                            300: '#8d8d8d',
                            400: '#bcbcbc',
                            500: '#ebebeb',
                            600: '#efefef',
                            700: '#f3f3f3',
                            800: '#f7f7f7',
                            900: '#fbfbfb'
                        },
                        silver: {
                            DEFAULT: '#c0c0c0',
                            100: '#262626',
                            200: '#4d4d4d',
                            300: '#737373',
                            400: '#999999',
                            500: '#c0c0c0',
                            600: '#cccccc',
                            700: '#d9d9d9',
                            800: '#e6e6e6',
                            900: '#f2f2f2'
                        },
                        cornflower_ocean: {
                            DEFAULT: '#3a6ea5',
                            100: '#0c1621',
                            200: '#172c42',
                            300: '#234264',
                            400: '#2f5885',
                            500: '#3a6ea5',
                            600: '#568bc4',
                            700: '#80a8d2',
                            800: '#aac5e1',
                            900: '#d5e2f0'
                        },
                        steel_azure: {
                            DEFAULT: '#004e98',
                            100: '#00101f',
                            200: '#00203d',
                            300: '#002f5c',
                            400: '#003f7a',
                            500: '#004e98',
                            600: '#0074e0',
                            700: '#2997ff',
                            800: '#70baff',
                            900: '#b8dcff'
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Custom Eyram Susu Passbook Theme -->
    <link rel="stylesheet" href="assets/css/custom.css?v=<?= filemtime(__DIR__ . '/../assets/css/custom.css') ?>">
    <script>
    (function() {
        try {
            if (localStorage.getItem('eyram_sidebar_collapsed') === 'true') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch(e) {}
    })();
    </script>
</head>
<body class="min-h-full flex flex-col antialiased pb-24 md:pb-10">

<?php if ($currentUser): ?>
<div class="min-h-screen flex flex-row w-full bg-slate-50">

    <!-- Desktop Collapsible Sidebar (HCI: Predictable & Expandable) -->
    <aside id="app_sidebar" class="hidden md:flex flex-col min-h-screen z-40 border-r border-slate-800/80 sticky top-0 h-screen select-none overflow-y-auto overflow-x-hidden flex-shrink-0" aria-label="Sidebar navigation">
        
        <!-- Sidebar Brand & Toggle Bar -->
        <div class="p-4 border-b border-slate-800/80 flex items-center justify-between h-17 flex-shrink-0">
            <a href="index.php" class="flex items-center gap-3 group overflow-hidden">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-pumpkin_spice to-pumpkin_spice-600 flex items-center justify-center text-white font-black text-xl shadow-md flex-shrink-0">
                    ₵
                </div>
                <div class="flex flex-col sidebar-brand-name">
                    <span class="font-extrabold text-base tracking-tight text-white font-heading leading-tight">Eyram Susu</span>
                    <span class="text-[10px] text-slate-400 font-medium tracking-wide">Daily Passbook</span>
                </div>
            </a>
            <button type="button" onclick="toggleSidebar()" class="sidebar-collapse-btn p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition" title="Toggle Sidebar (Collapse / Expand)">
                <svg class="w-5 h-5 sidebar-collapse-icon transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
            </button>
        </div>

        <!-- User Profile Badge in Sidebar -->
        <div class="p-3.5 border-b border-slate-800/80 bg-slate-900/50 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-steel_azure text-white font-black flex items-center justify-center text-xs flex-shrink-0 shadow-xs">
                <?= strtoupper(substr($currentUser['full_name'], 0, 2)) ?>
            </div>
            <div class="sidebar-user-details flex-1 min-w-0">
                <div class="text-xs font-bold text-white truncate"><?= htmlspecialchars($currentUser['full_name']) ?></div>
                <div class="text-[10px] text-slate-400 capitalize truncate"><?= $currentUser['role'] === 'admin' ? 'Office Manager' : 'Susu Collector' ?></div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 p-3 space-y-4 overflow-y-auto" role="navigation" aria-label="Main sidebar">
            <div>
                <div class="sidebar-section-title px-3 mb-1.5 text-[10px] font-black uppercase tracking-wider text-slate-500">
                    Main Menu
                </div>
                <div class="space-y-1">
                    <a href="<?= $currentUser['role'] === 'admin' ? 'admin_dashboard.php' : 'collector_dashboard.php' ?>" 
                       class="sidebar-link <?= isNavActive(['admin_dashboard.php', 'collector_dashboard.php'], $currentPage) ?>" 
                       title="Dashboard">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        <span class="sidebar-text">Dashboard</span>
                    </a>

                    <a href="customers.php" 
                       class="sidebar-link <?= isNavActive(['customers.php', 'add_customer.php'], $currentPage) ?>" 
                       title="Customers Directory">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="sidebar-text"><?= $currentUser['role'] === 'admin' ? 'Customers' : 'My Customers' ?></span>
                    </a>

                    <?php if ($currentUser['role'] === 'admin'): ?>
                        <a href="collectors.php" 
                           class="sidebar-link <?= isNavActive('collectors.php', $currentPage) ?>" 
                           title="Manage Collectors">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <span class="sidebar-text">Collectors Hub</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="sidebar-section-title px-3 mb-1.5 text-[10px] font-black uppercase tracking-wider text-slate-500">
                    Operations
                </div>
                <div class="space-y-1">
                    <a href="record_deposit.php" 
                       class="sidebar-link sidebar-link-accent <?= isNavActive('record_deposit.php', $currentPage) ?>" 
                       title="Collect Money (Record Deposit)">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        <span class="sidebar-text font-black">Collect Money</span>
                    </a>

                    <a href="<?= $currentUser['role'] === 'admin' ? 'payouts.php' : 'request_payout.php' ?>" 
                       class="sidebar-link <?= isNavActive(['payouts.php', 'request_payout.php'], $currentPage) ?>" 
                       title="Customer Cashout">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="sidebar-text"><?= $currentUser['role'] === 'admin' ? 'Cashouts' : 'Give Customer Money' ?></span>
                    </a>

                    <a href="daily_handover.php" 
                       class="sidebar-link <?= isNavActive('daily_handover.php', $currentPage) ?>" 
                       title="Daily Handover">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        <span class="sidebar-text"><?= $currentUser['role'] === 'admin' ? 'Cash Handovers' : 'Give Money to Office' ?></span>
                    </a>
                </div>
            </div>

            <?php if ($currentUser['role'] === 'admin'): ?>
                <div>
                    <div class="sidebar-section-title px-3 mb-1.5 text-[10px] font-black uppercase tracking-wider text-slate-500">
                        Audit & Reports
                    </div>
                    <div class="space-y-1">
                        <a href="reports.php" 
                           class="sidebar-link <?= isNavActive('reports.php', $currentPage) ?>" 
                           title="Daily Records & Analytics">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span class="sidebar-text">Daily Records</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </nav>

        <!-- Sidebar Footer -->
        <div class="p-3 border-t border-slate-800/80 space-y-2 flex-shrink-0">
            <?php if ($currentUser['role'] === 'collector'): ?>
                <div class="p-2.5 rounded-xl bg-slate-800/90 border border-slate-700/60 flex items-center justify-between">
                    <span class="text-[10px] font-bold text-pumpkin_spice-600 uppercase tracking-wider sidebar-text">With Me:</span>
                    <span class="text-white text-xs font-black font-heading"><?= format_money($collectorMoneyWithMe) ?></span>
                </div>
            <?php endif; ?>

            <a href="logout.php" class="sidebar-link text-slate-400 hover:text-red-400 hover:bg-red-500/10" title="Sign Out">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <span class="sidebar-text">Sign Out</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Column -->
    <div class="flex-1 flex flex-col min-w-0">
<?php endif; ?>

    <!-- Top Navigation Bar -->
    <header class="bg-gradient-to-r from-steel_azure to-steel_azure-400 text-white shadow-lg sticky top-0 z-40 border-b border-steel_azure-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-17">
                
                <!-- Left: Sidebar Toggle Button (Desktop) & Brand Mark (Mobile) -->
                <div class="flex items-center space-x-3">
                    <?php if ($currentUser): ?>
                        <!-- Desktop Sidebar Toggle Button -->
                        <button type="button" onclick="toggleSidebar()" class="hidden md:flex items-center justify-center w-10 h-10 rounded-xl bg-white/10 hover:bg-white/20 text-white transition" title="Toggle Sidebar">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                    <?php endif; ?>

                    <!-- Mobile Brand Logo -->
                    <a href="index.php" class="flex items-center space-x-2 sm:space-x-3 group <?= $currentUser ? 'md:hidden' : '' ?>">
                        <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-gradient-to-br from-pumpkin_spice to-pumpkin_spice-400 flex items-center justify-center text-white font-black text-xl sm:text-2xl shadow-md group-hover:scale-105 transition-transform flex-shrink-0">
                            ₵
                        </div>
                        <div class="flex flex-col">
                            <span class="font-extrabold text-lg sm:text-xl tracking-tight leading-none text-white font-heading">Eyram Susu</span>
                            <span class="text-[10px] sm:text-[11px] text-cornflower_ocean-900 font-medium tracking-wide mt-1 hidden sm:block">Daily Savings Passbook</span>
                        </div>
                    </a>

                    <?php if ($currentUser): ?>
                        <!-- Current Context Indicator on Desktop -->
                        <div class="hidden md:flex items-center gap-2">
                            <span class="text-xs font-bold text-cornflower_ocean-900 uppercase tracking-wider">Eyram Susu</span>
                            <span class="text-white/40">&bull;</span>
                            <span class="text-xs font-extrabold text-white font-heading"><?= htmlspecialchars($pageTitle ?? 'Passbook') ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($currentUser): ?>
                    <!-- Right User Pill & Live Money Bag & Notification Center -->
                    <div class="flex items-center space-x-2.5 sm:space-x-3">
                        
                        <?php if ($currentUser['role'] === 'collector'): ?>
                            <!-- Live Money with Me Pill (Mobile Compact) -->
                            <div class="bg-steel_azure-200/90 border border-steel_azure-300 px-2.5 sm:px-3.5 py-1.5 rounded-xl flex items-center space-x-1.5 sm:space-x-2 text-xs font-bold shadow-sm flex-shrink-0">
                                <span class="text-pumpkin_spice-600 font-extrabold text-[10px] sm:text-[11px] uppercase tracking-wider hidden sm:inline">With Me:</span>
                                <span class="text-white text-xs sm:text-sm font-black font-heading"><?= format_money($collectorMoneyWithMe) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="hidden sm:flex flex-col text-right">
                            <span class="text-xs font-black text-white"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                            <span class="text-[11px] text-cornflower_ocean-900 font-semibold capitalize"><?= $currentUser['role'] === 'admin' ? 'Office Manager' : 'Susu Collector' ?></span>
                        </div>

                        <!-- Notification Bell & Interactive Drawer -->
                        <div class="relative" id="notification_dropdown_wrapper">
                            <button type="button" id="notification_bell_btn" onclick="toggleNotificationDropdown(event)" class="notification-bell-btn" aria-label="Notifications" title="Notifications">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                <span id="notification_unread_badge" class="notification-badge <?= $unreadNotificationCount > 0 ? '' : 'hidden' ?>">
                                    <?= $unreadNotificationCount ?>
                                </span>
                            </button>

                            <!-- Mobile Backdrop Overlay -->
                            <div id="notification_backdrop" class="notification-backdrop" onclick="toggleNotificationDropdown(event)"></div>

                            <!-- Notification Dropdown Drawer -->
                            <div id="notification_dropdown" class="notification-dropdown">
                                <div class="p-3.5 border-b border-silver-600/80 flex items-center justify-between bg-platinum-800">
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-sm text-slate-800">Notifications</span>
                                        <span id="drawer_unread_count" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-steel_azure text-white">
                                            <?= $unreadNotificationCount ?> unread
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <?php if ($unreadNotificationCount > 0): ?>
                                            <button type="button" id="mark_all_read_btn" class="text-[11px] font-bold text-cornflower_ocean hover:text-steel_azure transition">
                                                Mark all read
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" onclick="toggleNotificationDropdown(event)" class="text-slate-400 hover:text-slate-700 font-black text-xl px-1.5 py-0.5 rounded-lg hover:bg-slate-200/60 leading-none transition" title="Close notifications">
                                            &times;
                                        </button>
                                    </div>
                                </div>

                                <div class="max-h-80 overflow-y-auto divide-y divide-silver-600/50" id="notification_list">
                                    <?php if (empty($userNotifications)): ?>
                                        <div class="p-6 text-center text-xs text-slate-400">
                                            <div class="text-2xl mb-1">🔔</div>
                                            No notifications yet.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($userNotifications as $n): ?>
                                            <a href="<?= htmlspecialchars($n['link'] ?: '#') ?>" 
                                               data-id="<?= $n['id'] ?>"
                                               class="notification-item <?= !$n['is_read'] ? 'unread' : '' ?>">
                                                <div class="notification-icon-box <?= !$n['is_read'] ? 'bg-blue-100 text-steel_azure' : 'bg-slate-100 text-slate-500' ?>">
                                                    <?php
                                                    $icon = '🔔';
                                                    if (strpos($n['type'], 'handover') !== false) $icon = '📦';
                                                    elseif (strpos($n['type'], 'payout') !== false) $icon = '💰';
                                                    elseif (strpos($n['type'], 'customer') !== false) $icon = '👤';
                                                    elseif (strpos($n['type'], 'shortage') !== false) $icon = '⚠️';
                                                    echo $icon;
                                                    ?>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs font-bold text-slate-800 truncate"><?= htmlspecialchars($n['title']) ?></div>
                                                    <div class="text-[11px] text-slate-500 line-clamp-2 mt-0.5 leading-snug"><?= htmlspecialchars($n['message']) ?></div>
                                                    <div class="text-[10px] text-slate-400 mt-1"><?= date('d M, h:i A', strtotime($n['created_at'])) ?></div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <a href="logout.php" title="Sign Out" class="px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white rounded-xl transition text-xs font-bold">
                            Sign Out
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </header>

    <!-- Flash Notifications Container -->
    <div id="toast-container"></div>
    <?php
    $flashMessages = get_flash_messages();
    foreach ($flashMessages as $msg): ?>
        <div class="flash-toast-data hidden" data-type="<?= htmlspecialchars($msg['type']) ?>" data-message="<?= htmlspecialchars($msg['message']) ?>"></div>
    <?php endforeach; ?>

    <script>
    function toggleNotificationDropdown(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const dropdown = document.getElementById('notification_dropdown');
        const backdrop = document.getElementById('notification_backdrop');
        if (!dropdown) return;
        const isOpen = dropdown.classList.contains('open');
        if (isOpen) {
            dropdown.classList.remove('open');
            if (backdrop) backdrop.classList.remove('open');
        } else {
            dropdown.classList.add('open');
            if (backdrop) backdrop.classList.add('open');
        }
    }

    function toggleSidebar() {
        document.documentElement.classList.toggle('sidebar-collapsed');
        document.body.classList.toggle('sidebar-collapsed');
        const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
        try {
            localStorage.setItem('eyram_sidebar_collapsed', isCollapsed ? 'true' : 'false');
        } catch(e) {}
    }
    </script>

    <!-- Main Content Area (Page Entrance Animation) -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 page-enter">
