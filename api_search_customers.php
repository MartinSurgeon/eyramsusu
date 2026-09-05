<?php
// api_search_customers.php - Robust Asynchronous Full-Database Customer Search API
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Require authenticated session
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = get_logged_in_user();
$query = trim($_GET['q'] ?? $_GET['search'] ?? '');

if ($query === '') {
    echo json_encode(['success' => true, 'total' => 0, 'customers' => []]);
    exit;
}

$pdo = get_db_connection();
$searchWildcard = '%' . $query . '%';

try {
    $selectFields = "
        c.id, c.account_number, c.full_name, c.gender, c.phone, c.location, c.change_balance,
        c.assigned_collector_id, u.full_name AS collector_name,
        act_sc.id AS active_card_id, act_sc.card_number AS active_card_number, act_sc.daily_amount AS active_daily_amount,
        act_sc.spaces_filled AS active_spaces_filled, act_sc.total_spaces AS active_total_spaces, act_sc.total_saved AS active_total_saved,
        comp_sc.id AS completed_card_id, comp_sc.card_number AS completed_card_number, comp_sc.daily_amount AS completed_daily_amount,
        comp_sc.spaces_filled AS completed_spaces_filled, comp_sc.total_spaces AS completed_total_spaces, comp_sc.total_saved AS completed_total_saved,
        comp_p.id AS completed_payout_id, comp_p.status AS completed_payout_status,
        latest_sc.id AS latest_card_id,
        (SELECT COUNT(*) FROM susu_cards WHERE customer_id = c.id) as total_cards_count
    ";

    $joins = "
        LEFT JOIN users u ON c.assigned_collector_id = u.id
        LEFT JOIN susu_cards act_sc ON act_sc.id = (
            SELECT id FROM susu_cards 
            WHERE customer_id = c.id AND status = 'active' 
            ORDER BY id DESC LIMIT 1
        )
        LEFT JOIN susu_cards comp_sc ON comp_sc.id = (
            SELECT sc2.id FROM susu_cards sc2
            WHERE sc2.customer_id = c.id 
              AND (sc2.status = 'completed' OR sc2.spaces_filled >= sc2.total_spaces)
              AND NOT EXISTS (
                  SELECT 1 FROM payouts p_paid WHERE p_paid.card_id = sc2.id AND p_paid.status = 'paid'
              )
            ORDER BY sc2.id DESC LIMIT 1
        )
        LEFT JOIN payouts comp_p ON comp_p.card_id = comp_sc.id AND comp_p.status = 'pending'
        LEFT JOIN susu_cards latest_sc ON latest_sc.id = (
            SELECT id FROM susu_cards WHERE customer_id = c.id ORDER BY id DESC LIMIT 1
        )
    ";

    if ($user['role'] === 'collector') {
        // Collector only searches their assigned active clients
        $sql = "
            SELECT {$selectFields}
            FROM customers c
            {$joins}
            WHERE c.is_active = 1 
              AND c.assigned_collector_id = ?
              AND (
                  c.full_name LIKE ? OR 
                  c.account_number LIKE ? OR 
                  c.phone LIKE ? OR 
                  c.location LIKE ?
              )
            ORDER BY (comp_sc.id IS NOT NULL) DESC, c.full_name ASC
            LIMIT 30
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user['id'],
            $searchWildcard,
            $searchWildcard,
            $searchWildcard,
            $searchWildcard
        ]);
    } else {
        // Admin searches all active clients across the entire database
        $sql = "
            SELECT {$selectFields}
            FROM customers c
            {$joins}
            WHERE c.is_active = 1 
              AND (
                  c.full_name LIKE ? OR 
                  c.account_number LIKE ? OR 
                  c.phone LIKE ? OR 
                  c.location LIKE ? OR
                  u.full_name LIKE ?
              )
            ORDER BY (comp_sc.id IS NOT NULL) DESC, c.full_name ASC
            LIMIT 30
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $searchWildcard,
            $searchWildcard,
            $searchWildcard,
            $searchWildcard,
            $searchWildcard
        ]);
    }

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format fields for frontend consumption
    $formatted = array_map(function ($c) {
        $hasCompleted = !empty($c['completed_card_id']);
        $hasActive = !empty($c['active_card_id']);

        if ($hasCompleted) {
            $cardId = (int)$c['completed_card_id'];
            $cardNumber = (int)$c['completed_card_number'];
            $dailyAmount = (float)$c['completed_daily_amount'];
            $spacesFilled = (int)$c['completed_spaces_filled'];
            $totalSpaces = (int)$c['completed_total_spaces'];
            $totalSaved = (float)$c['completed_total_saved'];
            $cardStatus = 'completed';
        } elseif ($hasActive) {
            $cardId = (int)$c['active_card_id'];
            $cardNumber = (int)$c['active_card_number'];
            $dailyAmount = (float)$c['active_daily_amount'];
            $spacesFilled = (int)$c['active_spaces_filled'];
            $totalSpaces = (int)$c['active_total_spaces'];
            $totalSaved = (float)$c['active_total_saved'];
            $cardStatus = 'active';
        } else {
            $cardId = null;
            $cardNumber = null;
            $dailyAmount = 20.00;
            $spacesFilled = 0;
            $totalSpaces = 31;
            $totalSaved = 0.00;
            $cardStatus = 'none';
        }

        return [
            'id' => (int)$c['id'],
            'account_number' => $c['account_number'],
            'full_name' => $c['full_name'],
            'gender' => $c['gender'] ?? null,
            'phone' => $c['phone'],
            'location' => $c['location'] ?: 'Not specified',
            'change_balance' => (float)$c['change_balance'],
            'change_balance_formatted' => format_money($c['change_balance']),
            'assigned_collector_id' => $c['assigned_collector_id'] ? (int)$c['assigned_collector_id'] : null,
            'collector_name' => $c['collector_name'] ?: 'Unassigned',
            'card_id' => $cardId,
            'card_number' => $cardNumber,
            'daily_amount' => $dailyAmount,
            'daily_amount_formatted' => format_money($dailyAmount),
            'spaces_filled' => $spacesFilled,
            'total_spaces' => $totalSpaces,
            'total_saved' => $totalSaved,
            'total_saved_formatted' => format_money($totalSaved),
            'card_status' => $cardStatus,
            'is_completed' => $hasCompleted,
            'is_pending_payout' => !empty($c['completed_payout_id']),
            'latest_card_id' => !empty($c['latest_card_id']) ? (int)$c['latest_card_id'] : null,
            'total_cards_count' => (int)($c['total_cards_count'] ?? 0)
        ];
    }, $results);

    echo json_encode([
        'success' => true,
        'query' => $query,
        'total' => count($formatted),
        'customers' => $formatted
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database search failed']);
}
