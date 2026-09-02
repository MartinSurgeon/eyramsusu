<?php
// api_notifications.php - Asynchronous Notification Handling
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = get_logged_in_user();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'mark_read') {
    $notificationId = (int)($_POST['id'] ?? 0);
    if ($notificationId > 0) {
        mark_notification_read($notificationId);
        $unreadCount = get_unread_notification_count($user['id'], $user['role']);
        echo json_encode(['success' => true, 'unread_count' => $unreadCount]);
        exit;
    }
} elseif ($action === 'mark_all_read') {
    mark_all_notifications_read($user['id'], $user['role']);
    echo json_encode(['success' => true, 'unread_count' => 0]);
    exit;
} elseif ($action === 'get_unread_count') {
    $unreadCount = get_unread_notification_count($user['id'], $user['role']);
    echo json_encode(['success' => true, 'unread_count' => $unreadCount]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;
