<?php
// login.php - System Authentication
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    $user = get_logged_in_user();
    header('Location: ' . ($user['role'] === 'admin' ? 'admin_dashboard.php' : 'collector_dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user);
            set_flash_message('success', 'Welcome back, ' . $user['full_name'] . '!');
            header('Location: ' . ($user['role'] === 'admin' ? 'admin_dashboard.php' : 'collector_dashboard.php'));
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}

$pageTitle = "Login";
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-platinum-800">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Eyram Susu</title>
    <meta name="description" content="Sign in to Eyram Susu Digital Savings Passbook - manage daily susu collections and customer accounts.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        pumpkin_spice: { DEFAULT: '#ff6700', 400: '#cc5200', 500: '#ff6700', 600: '#ff8533' },
                        platinum: { DEFAULT: '#ebebeb', 700: '#f3f3f3', 800: '#f7f7f7', 900: '#fbfbfb' },
                        silver: { DEFAULT: '#c0c0c0', 600: '#cccccc', 700: '#d9d9d9' },
                        cornflower_ocean: { DEFAULT: '#3a6ea5', 400: '#2f5885', 500: '#3a6ea5', 800: '#aac5e1' },
                        steel_azure: { DEFAULT: '#004e98', 400: '#003f7a', 500: '#004e98', 600: '#0074e0' }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= get_asset_url('css') ?>">
</head>
<body class="h-full flex items-center justify-center p-4">

    <div class="w-full max-w-md page-enter">
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-steel_azure text-white shadow-lg mb-3">
                <span class="text-3xl font-extrabold text-pumpkin_spice">₵</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-steel_azure tracking-tight">Eyram Susu</h1>
            <p class="text-sm text-slate-500 font-medium mt-1">Digital 31-Space Savings Passbook</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-silver-600 p-6 sm:p-8">
            
            <h2 class="text-lg font-bold text-slate-800 mb-2">Sign in to your account</h2>
            <p class="text-xs text-slate-500 mb-6">Enter your login details to access the system.</p>

            <?php if (!empty($error)): ?>
                <div class="mb-5 p-3.5 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label for="username" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Username</label>
                    <input type="text" id="username" name="username" required autocomplete="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           class="w-full px-3.5 py-3 rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none text-sm transition"
                           placeholder="e.g. admin or kofi">
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Password</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                               class="w-full px-3.5 py-3 pr-12 rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none text-sm transition"
                               placeholder="••••••••">
                        <!-- Password toggle injected by JS -->
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                            class="w-full btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white font-bold text-sm tracking-wide shadow-md hover:shadow-lg transition">
                        Sign In
                    </button>
                </div>
            </form>


        </div>

        <!-- Peak-End Trust Line -->
        <p class="text-center text-[11px] text-slate-400 mt-4 font-medium">
            Eyram Susu &copy; <?= date('Y') ?> &bull; Your savings, secured.
        </p>
        <p class="text-center text-[11px] text-slate-400 mt-1 font-medium">
            Developed by <span class="font-bold text-slate-600">Mart IT Services</span> &bull; 
            <a href="https://wa.me/233557869989" target="_blank" rel="noopener noreferrer" class="text-emerald-600 hover:text-emerald-700 font-bold hover:underline">WhatsApp 0557869989</a>
        </p>
    </div>

    <script src="<?= get_asset_url('js') ?>"></script>
</body>
</html>
