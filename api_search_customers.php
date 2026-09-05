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
    if ($user['role'] === 'collector') {
        // Collector only searches their assigned active clients
        $sql = "
            SELECT c.id, c.account_number, c.full_name, c.gender, c.phone, c.location, c.change_balance,
                   c.assigned_collector_id, u.full_name AS collector_name,
                   sc.id AS card_id, sc.card_number, sc.daily_amount, sc.spaces_filled, 
                   sc.total_spaces, sc.total_saved, sc.status AS card_status
            FROM customers c
            LEFT JOIN users u ON c.assigned_collector_id = u.id
            LEFT JOIN susu_cards sc ON c.id = sc.customer_id AND sc.status = 'active'
            WHERE c.is_active = 1 
              AND c.assigned_collector_id = ?
              AND (
                  c.full_name LIKE ? OR 
                  c.account_number LIKE ? OR 
                  c.phone LIKE ? OR 
                  c.location LIKE ?
              )
            ORDER BY c.full_name ASC
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
            SELECT c.id, c.account_number, c.full_name, c.gender, c.phone, c.location, c.change_balance,
                   c.assigned_collector_id, u.full_name AS collector_name,
                   sc.id AS card_id, sc.card_number, sc.daily_amount, sc.spaces_filled, 
                   sc.total_spaces, sc.total_saved, sc.status AS card_status
            FROM customers c
            LEFT JOIN users u ON c.assigned_collector_id = u.id
            LEFT JOIN susu_cards sc ON c.id = sc.customer_id AND sc.status = 'active'
            WHERE c.is_active = 1 
              AND (
                  c.full_name LIKE ? OR 
                  c.account_number LIKE ? OR 
                  c.phone LIKE ? OR 
                  c.location LIKE ? OR
                  u.full_name LIKE ?
              )
            ORDER BY c.full_name ASC
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
            'card_id' => $c['card_id'] ? (int)$c['card_id'] : null,
            'card_number' => $c['card_number'] ? (int)$c['card_number'] : null,
            'daily_amount' => $c['daily_amount'] !== null ? (float)$c['daily_amount'] : null,
            'daily_amount_formatted' => $c['daily_amount'] !== null ? format_money($c['daily_amount']) : null,
            'spaces_filled' => $c['spaces_filled'] !== null ? (int)$c['spaces_filled'] : 0,
            'total_spaces' => $c['total_spaces'] !== null ? (int)$c['total_spaces'] : 31,
            'total_saved' => $c['total_saved'] !== null ? (float)$c['total_saved'] : 0.00,
            'total_saved_formatted' => $c['total_saved'] !== null ? format_money($c['total_saved']) : 'GH₵ 0.00',
            'card_status' => $c['card_status'] ?: 'none'
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
