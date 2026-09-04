<?php
// logout.php - Safely logs out the user with audit trail
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Capture user details BEFORE session is destroyed
$sessionUser = get_logged_in_user();
if ($sessionUser) {
    log_audit_event((int)$sessionUser['id'], 'logout', "User signed out: {$sessionUser['full_name']} ({$sessionUser['role']})");
}

logout_user();
session_start();
set_flash_message('success', 'You have successfully signed out. See you next time!');
header('Location: login.php');
exit;
