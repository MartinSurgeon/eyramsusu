<?php
// index.php - Front entry router
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $user = get_logged_in_user();
    if ($user['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: collector_dashboard.php');
    }
    exit;
}

header('Location: login.php');
exit;
