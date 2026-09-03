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
            'message' => 'Please enter a valid amount.'
        ];
    }

    // Strict divisibility check (No remainder allowed)
    $remainder = round(fmod($cash_paid, $daily_amount), 2);
    if ($remainder > 0.005 && abs($remainder - $daily_amount) > 0.005) {
        $ex1 = format_money($daily_amount);
        $ex2 = format_money($daily_amount * 2);
        $ex3 = format_money($daily_amount * 3);
        return [
            'valid' => false,
            'message' => "Amount must be exact. Example: {$ex1}, {$ex2}, {$ex3}."
        ];
    }

    $spaces_to_fill = (int)round($cash_paid / $daily_amount);
    $spaces_remaining = max(0, $total_spaces - $current_spaces_filled);

    if ($spaces_to_fill > $spaces_remaining) {
        $maxAllowed = format_money($spaces_remaining * $daily_amount);
        return [
            'valid' => false,
            'message' => "Only {$spaces_remaining} space(s) left on this card. Maximum allowed is {$maxAllowed}."
        ];
    }

    $money_applied = $spaces_to_fill * $daily_amount;
    $new_change = $current_change; // Zero new remainder created

    return [
        'valid' => true,
        'cash_paid' => $cash_paid,
        'current_change' => $current_change,
        'total_pool' => $cash_paid + $current_change,
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
 * Reverse/Undo a deposit transaction made today before handover
 *
 * @param int $depositId ID of the deposit (or any space in the transaction batch)
 * @param string $reason Reason for cancellation
 * @param int $userId ID of user performing reversal
 * @param string $userRole 'admin' or 'collector'
 * @return array ['success' => bool, 'message' => string, 'amount' => float, 'spaces' => int, 'customer_id' => int]
 */
function reverse_deposit($depositId, $reason, $userId, $userRole = 'collector') {
    $pdo = get_db_connection();
    
    // 1. Fetch deposit details
    $stmt = $pdo->prepare("
        SELECT d.*, c.full_name as customer_name, c.account_number, u.full_name as collector_name,
               sc.spaces_filled, sc.total_saved, sc.status as card_status
        FROM deposits d
        JOIN customers c ON d.customer_id = c.id
        JOIN users u ON d.collector_id = u.id
        JOIN susu_cards sc ON d.card_id = sc.id
        WHERE d.id = ?
    ");
    $stmt->execute([(int)$depositId]);
    $deposit = $stmt->fetch();

    if (!$deposit) {
        return ['success' => false, 'message' => 'Deposit record not found.'];
    }

    // 2. Permission check: collectors can only cancel their own deposits
    if ($userRole !== 'admin' && (int)$deposit['collector_id'] !== (int)$userId) {
        return ['success' => false, 'message' => 'You can only cancel your own collections.'];
    }

    // 3. Handover check: cannot reverse deposits already handed over to admin
    if (!empty($deposit['handover_id'])) {
        return ['success' => false, 'message' => 'Cannot cancel: this deposit has already been handed over to the office.'];
    }

    // 4. Date check: can only cancel same-day deposits
    if ($deposit['deposit_date'] !== date('Y-m-d')) {
        return ['success' => false, 'message' => 'Cannot cancel: only collections from today can be undone.'];
    }

    try {
        $pdo->beginTransaction();

        // 5. Find all spaces recorded in this exact deposit batch (same card, collector, and created within 3 seconds)
        $batchStmt = $pdo->prepare("
            SELECT id, amount, space_number 
            FROM deposits 
            WHERE card_id = ? AND collector_id = ? 
              AND deposit_date = ? 
              AND handover_id IS NULL
              AND ABS(TIMESTAMPDIFF(SECOND, created_at, ?)) <= 3
            ORDER BY space_number DESC
        ");
        $batchStmt->execute([
            $deposit['card_id'],
            $deposit['collector_id'],
            $deposit['deposit_date'],
            $deposit['created_at']
        ]);
        $batchItems = $batchStmt->fetchAll();

        if (empty($batchItems)) {
            $batchItems = [['id' => $deposit['id'], 'amount' => $deposit['amount'], 'space_number' => $deposit['space_number']]];
        }

        $totalReversedMoney = 0.0;
        $totalSpacesCount = count($batchItems);
        $depositIdsToDelete = [];

        foreach ($batchItems as $item) {
            $totalReversedMoney += (float)$item['amount'];
            $depositIdsToDelete[] = (int)$item['id'];
        }

        // 6. Delete deposit entries
        $inPlaceholders = implode(',', array_fill(0, count($depositIdsToDelete), '?'));
        $delStmt = $pdo->prepare("DELETE FROM deposits WHERE id IN ($inPlaceholders)");
        $delStmt->execute($depositIdsToDelete);

        // 7. Update active card
        $newSpacesFilled = max(0, (int)$deposit['spaces_filled'] - $totalSpacesCount);
        $newTotalSaved = max(0.0, (float)$deposit['total_saved'] - $totalReversedMoney);
        
        $cardUpd = $pdo->prepare("
            UPDATE susu_cards 
            SET spaces_filled = ?, total_saved = ?, status = 'active'
            WHERE id = ?
        ");
        $cardUpd->execute([$newSpacesFilled, $newTotalSaved, $deposit['card_id']]);

        // 8. Dispatch In-App Notification to Admin
        $cleanReason = trim($reason) ?: 'Customer changed mind';
        $spacesText = $totalSpacesCount === 1 ? '1 space' : "{$totalSpacesCount} spaces";
        $adminMsg = "Collector {$deposit['collector_name']} cancelled deposit of " . format_money($totalReversedMoney) . " ({$spacesText}) for {$deposit['customer_name']} (#{$deposit['account_number']}). Reason: {$cleanReason}.";
        
        create_notification(
            null, // visible to all admins
            'warning',
            "Deposit Cancelled: {$deposit['customer_name']}",
            $adminMsg,
            "view_card.php?id={$deposit['card_id']}"
        );

        $pdo->commit();

        return [
            'success' => true,
            'message' => "Deposit of " . format_money($totalReversedMoney) . " cancelled successfully. You can now record the correct amount.",
            'amount' => $totalReversedMoney,
            'spaces' => $totalSpacesCount,
            'customer_id' => (int)$deposit['customer_id'],
            'card_id' => (int)$deposit['card_id']
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Deposit reversal error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Could not cancel deposit. Please try again.'];
    }
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

/**
 * Auto-detects and returns valid URL path for CSS or JS assets,
 * supporting both /assets/css/ and flattened /css/ structures with cache busting.
 */
function get_asset_url($assetType = 'css') {
    $rootDir = dirname(__DIR__);
    if ($assetType === 'css') {
        if (file_exists($rootDir . '/assets/css/custom.css')) {
            return 'assets/css/custom.css?v=' . filemtime($rootDir . '/assets/css/custom.css');
        } elseif (file_exists($rootDir . '/css/custom.css')) {
            return 'css/custom.css?v=' . filemtime($rootDir . '/css/custom.css');
        }
        return 'css/custom.css';
    } elseif ($assetType === 'js') {
        if (file_exists($rootDir . '/assets/js/app.js')) {
            return 'assets/js/app.js?v=' . filemtime($rootDir . '/assets/js/app.js');
        } elseif (file_exists($rootDir . '/js/app.js')) {
            return 'js/app.js?v=' . filemtime($rootDir . '/js/app.js');
        }
        return 'js/app.js';
    }
    return '';
}

/**
 * Paginates an array of items
 * Returns: ['items' => array, 'total' => int, 'pages' => int, 'current' => int, 'per_page' => int, 'start' => int, 'end' => int]
 */
function paginate_array(array $items, int $perPage = 10, int $currentPage = 1): array {
    $total = count($items);
    $pages = max(1, (int)ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $pages));
    $offset = ($currentPage - 1) * $perPage;
    $pagedItems = array_slice($items, $offset, $perPage);
    $start = $total > 0 ? $offset + 1 : 0;
    $end = min($offset + $perPage, $total);

    return [
        'items' => $pagedItems,
        'total' => $total,
        'pages' => $pages,
        'current' => $currentPage,
        'per_page' => $perPage,
        'start' => $start,
        'end' => $end
    ];
}

/**
 * Renders HTML pagination controls with Font Awesome icons, page numbers, and item counts.
 * Automatically preserves existing $_GET parameters.
 */
function render_pagination(int $totalItems, int $perPage, int $currentPage, string $paramName = 'page'): string {
    $totalPages = (int)ceil($totalItems / $perPage);
    if ($totalPages <= 1) {
        return '';
    }

    $currentPage = max(1, min($currentPage, $totalPages));
    $startItem = ($currentPage - 1) * $perPage + 1;
    $endItem = min($currentPage * $perPage, $totalItems);

    // Build URL query base preserving existing params
    $queryParams = $_GET;

    $buildUrl = function($pageNum) use ($queryParams, $paramName) {
        $queryParams[$paramName] = $pageNum;
        return '?' . http_build_query($queryParams);
    };

    $html = '<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3 bg-slate-50 border-t border-silver-600/70 text-xs text-slate-600 rounded-b-2xl">';
    
    // Left: Showing X to Y of Z
    $html .= '<div class="font-medium">';
    $html .= 'Showing <strong class="text-slate-800">' . $startItem . '</strong> to <strong class="text-slate-800">' . $endItem . '</strong> of <strong class="text-slate-800">' . $totalItems . '</strong> records';
    $html .= '</div>';

    // Right: Pagination Buttons
    $html .= '<div class="flex items-center gap-1.5 flex-wrap">';

    // Previous Button
    if ($currentPage > 1) {
        $html .= '<a href="' . htmlspecialchars($buildUrl($currentPage - 1)) . '" class="btn-touch px-2.5 py-1.5 rounded-lg bg-white hover:bg-slate-100 text-slate-700 border border-silver-600 font-bold transition flex items-center gap-1 shadow-2xs"><i class="fa-solid fa-chevron-left text-[10px]"></i><span>Prev</span></a>';
    } else {
        $html .= '<span class="px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed font-bold flex items-center gap-1"><i class="fa-solid fa-chevron-left text-[10px]"></i><span>Prev</span></span>';
    }

    // Page Numbers (Display up to 5 surrounding pages)
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $startPage + 4);
    if ($endPage - $startPage < 4) {
        $startPage = max(1, $endPage - 4);
    }

    if ($startPage > 1) {
        $html .= '<a href="' . htmlspecialchars($buildUrl(1)) . '" class="px-2.5 py-1.5 rounded-lg bg-white hover:bg-slate-100 text-slate-700 border border-silver-600 font-bold transition shadow-2xs">1</a>';
        if ($startPage > 2) {
            $html .= '<span class="px-1 text-slate-400">&hellip;</span>';
        }
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i === $currentPage) {
            $html .= '<span class="px-2.5 py-1.5 rounded-lg bg-steel_azure text-white font-extrabold shadow-xs">' . $i . '</span>';
        } else {
            $html .= '<a href="' . htmlspecialchars($buildUrl($i)) . '" class="px-2.5 py-1.5 rounded-lg bg-white hover:bg-slate-100 text-slate-700 border border-silver-600 font-bold transition shadow-2xs">' . $i . '</a>';
        }
    }

    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<span class="px-1 text-slate-400">&hellip;</span>';
        }
        $html .= '<a href="' . htmlspecialchars($buildUrl($totalPages)) . '" class="px-2.5 py-1.5 rounded-lg bg-white hover:bg-slate-100 text-slate-700 border border-silver-600 font-bold transition shadow-2xs">' . $totalPages . '</a>';
    }

    // Next Button
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . htmlspecialchars($buildUrl($currentPage + 1)) . '" class="btn-touch px-2.5 py-1.5 rounded-lg bg-white hover:bg-slate-100 text-slate-700 border border-silver-600 font-bold transition flex items-center gap-1 shadow-2xs"><span>Next</span><i class="fa-solid fa-chevron-right text-[10px]"></i></a>';
    } else {
        $html .= '<span class="px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed font-bold flex items-center gap-1"><span>Next</span><i class="fa-solid fa-chevron-right text-[10px]"></i></span>';
    }

    $html .= '</div></div>';

    return $html;
}

