<?php
// includes/auth.php - Session Management and Access Control

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Log in a user and set session variables
 */
function login_user($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['phone'] = $user['phone'] ?? '';
}

/**
 * Log out user and destroy session
 */
function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Get current logged in user details
 */
function get_logged_in_user() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'phone' => $_SESSION['phone'] ?? ''
    ];
}

/**
 * Check if a user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if current user is admin
 */
function is_admin() {
    return is_logged_in() && $_SESSION['role'] === 'admin';
}

/**
 * Check if current user is collector
 */
function is_collector() {
    return is_logged_in() && $_SESSION['role'] === 'collector';
}

/**
 * Restrict page to logged in users
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Restrict page to administrators
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        header('Location: collector_dashboard.php');
        exit;
    }
}

/**
 * Flash message helpers
 */
function set_flash_message($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

function get_flash_messages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}
