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
} elseif ($action === 'alert_admin_card') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    if ($customerId > 0) {
        $pdo = get_db_connection();
        $stmtCust = $pdo->prepare("SELECT full_name, account_number FROM customers WHERE id = ?");
        $stmtCust->execute([$customerId]);
        $cust = $stmtCust->fetch();
        if ($cust) {
            create_notification(
                null, // visible to all admins
                'warning',
                "New Card Needed: " . $cust['full_name'],
                "Collector " . $user['full_name'] . " is with " . $cust['full_name'] . " (#" . $cust['account_number'] . ") who needs a new Susu Card.",
                "record_deposit.php?customer_id=" . $customerId
            );
            echo json_encode(['success' => true, 'message' => 'Admin alerted successfully!']);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'Customer not found.']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;
