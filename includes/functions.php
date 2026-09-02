<?php
// includes/functions.php - Core Business Logic & Calculations

require_once __DIR__ . '/../config/db.php';

/**
 * Format money with Ghanaian Cedi symbol
 */
function format_money($amount) {
    return 'GH₵ ' . number_format((float)$amount, 2);
}

/**
 * Clean and sanitize user input
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Calculate deposit breakdown (Spaces filled + Customer Change)
 * Implements business rule 6: Multi-contributions & float balance
 */
function calculate_deposit_breakdown($daily_amount, $cash_paid, $current_change = 0.00, $current_spaces_filled = 0, $total_spaces = 31) {
    $daily_amount = (float)$daily_amount;
    $cash_paid = (float)$cash_paid;
    $current_change = (float)$current_change;
    $current_spaces_filled = (int)$current_spaces_filled;
    $total_spaces = (int)$total_spaces;

    if ($daily_amount <= 0 || $cash_paid <= 0) {
        return [
            'valid' => false,
            'message' => 'Invalid daily amount or cash paid.'
        ];
    }

    $total_pool = $cash_paid + $current_change;
    $spaces_can_fill = (int)floor($total_pool / $daily_amount);
    $spaces_remaining = max(0, $total_spaces - $current_spaces_filled);

    $spaces_to_fill = min($spaces_can_fill, $spaces_remaining);
    $money_applied = $spaces_to_fill * $daily_amount;
    $new_change = round($total_pool - $money_applied, 2);

    return [
        'valid' => true,
        'cash_paid' => $cash_paid,
        'current_change' => $current_change,
        'total_pool' => $total_pool,
        'spaces_to_fill' => $spaces_to_fill,
        'start_space' => $spaces_to_fill > 0 ? ($current_spaces_filled + 1) : 0,
        'end_space' => $spaces_to_fill > 0 ? ($current_spaces_filled + $spaces_to_fill) : 0,
        'money_applied' => $money_applied,
        'new_change' => $new_change,
        'is_completed' => ($current_spaces_filled + $spaces_to_fill >= $total_spaces)
    ];
}

/**
 * Calculate Payout Breakdown
 * Implements business rules 8, 9, 10:
 * Gross Total Saved - 1 Contribution Fee + Any Customer Change = Net Customer Payout
 */
function calculate_payout_breakdown($card_id) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT c.*, cust.full_name, cust.account_number, cust.change_balance, cust.phone 
        FROM susu_cards c 
        JOIN customers cust ON c.customer_id = cust.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$card_id]);
    $card = $stmt->fetch();

    if (!$card) {
        return null;
    }

    $total_saved = (float)$card['total_saved'];
    $daily_amount = (float)$card['daily_amount'];
    $change_balance = (float)$card['change_balance'];

    // Rule: Business takes exactly ONE contribution amount as its fee
    $business_fee = min($daily_amount, $total_saved);
    $customer_payout = max(0, $total_saved - $business_fee) + $change_balance;

    return [
        'card' => $card,
        'total_saved' => $total_saved,
        'spaces_filled' => (int)$card['spaces_filled'],
        'daily_amount' => $daily_amount,
        'business_fee' => $business_fee,
        'change_refunded' => $change_balance,
        'customer_payout' => $customer_payout,
        'is_full_cycle' => ((int)$card['spaces_filled'] >= (int)$card['total_spaces'])
    ];
}

/**
 * Calculate a Collector's current Cash in Hand (unsettled collections)
 */
function get_collector_cash_in_hand($collector_id) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0.00) as total_cash 
        FROM deposits 
        WHERE collector_id = ? AND handover_id IS NULL
    ");
    $stmt->execute([$collector_id]);
    return (float)$stmt->fetchColumn();
}

/**
 * Get active Susu Card for a customer
 */
function get_active_card_for_customer($customer_id) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT * FROM susu_cards 
        WHERE customer_id = ? AND status = 'active' 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$customer_id]);
    return $stmt->fetch();
}

/**
 * Fetch all completed deposit entries for a Susu Card
 */
function get_card_deposits($card_id) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT d.*, u.full_name as collector_name 
        FROM deposits d 
        LEFT JOIN users u ON d.collector_id = u.id 
        WHERE d.card_id = ? 
        ORDER BY d.space_number ASC
    ");
    $stmt->execute([$card_id]);
    return $stmt->fetchAll();
}

/**
 * Get overall summary stats for Admin dashboard
 */
function get_admin_dashboard_stats() {
    $pdo = get_db_connection();

    // Today's total collections
    $stmt1 = $pdo->query("SELECT COALESCE(SUM(amount), 0.00) FROM deposits WHERE deposit_date = CURRENT_DATE");
    $today_collections = (float)$stmt1->fetchColumn();

    // Total active cards
    $stmt2 = $pdo->query("SELECT COUNT(*) FROM susu_cards WHERE status = 'active'");
    $active_cards = (int)$stmt2->fetchColumn();

    // Pending payout requests
    $stmt3 = $pdo->query("SELECT COUNT(*) FROM payouts WHERE status = 'pending'");
    $pending_payouts = (int)$stmt3->fetchColumn();

    // Pending daily cash handovers
    $stmt4 = $pdo->query("SELECT COUNT(*) FROM daily_handovers WHERE status = 'submitted'");
    $pending_handovers = (int)$stmt4->fetchColumn();

    // Total lifetime business fees earned
    $stmt5 = $pdo->query("SELECT COALESCE(SUM(business_fee), 0.00) FROM payouts WHERE status IN ('approved', 'paid')");
    $total_business_fees = (float)$stmt5->fetchColumn();

    // Total active customers
    $stmt6 = $pdo->query("SELECT COUNT(*) FROM customers WHERE is_active = 1");
    $total_customers = (int)$stmt6->fetchColumn();

    return [
        'today_collections' => $today_collections,
        'active_cards' => $active_cards,
        'pending_payouts' => $pending_payouts,
        'pending_handovers' => $pending_handovers,
        'total_business_fees' => $total_business_fees,
        'total_customers' => $total_customers
    ];
}

/**
 * Create an In-App Notification
 * If $userId is NULL, notification is visible to all admins.
 */
function create_notification($userId, $type, $title, $message, $link = null) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) 
            VALUES (?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$userId, $type, $title, $message, $link]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch Recent Notifications for a User
 */
function get_user_notifications($userId, $userRole = 'collector', $limit = 15) {
    try {
        $pdo = get_db_connection();
        if ($userRole === 'admin') {
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? OR user_id IS NULL 
                ORDER BY id DESC LIMIT " . (int)$limit
            );
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY id DESC LIMIT " . (int)$limit
            );
            $stmt->execute([$userId]);
        }
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Count unread notifications
 */
function get_unread_notification_count($userId, $userRole = 'collector') {
    try {
        $pdo = get_db_connection();
        if ($userRole === 'admin') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0
            ");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$userId]);
        }
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Mark a single notification as read
 */
function mark_notification_read($notificationId) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        return $stmt->execute([$notificationId]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 */
function mark_all_notifications_read($userId, $userRole = 'collector') {
    try {
        $pdo = get_db_connection();
        if ($userRole === 'admin') {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL");
            return $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            return $stmt->execute([$userId]);
        }
    } catch (Exception $e) {
        return false;
    }
}

