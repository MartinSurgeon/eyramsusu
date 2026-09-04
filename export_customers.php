<?php
// export_customers.php - Export Customer List to Excel-compatible CSV for Account Number Corrections
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Strict Admin-only access
require_admin();

$pdo = get_db_connection();

// Fetch all active customers with collector details, ordered alphabetically by name
$stmt = $pdo->query("
    SELECT c.id, c.account_number, c.full_name, c.gender, c.phone, c.location,
           u.full_name as collector_name
    FROM customers c
    LEFT JOIN users u ON c.assigned_collector_id = u.id
    WHERE c.is_active = 1
    ORDER BY c.full_name ASC
");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define CSV filename with today's date
$filename = 'eyram_customers_' . date('Y-m-d') . '.csv';

// Send HTTP headers to force browser download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open PHP output stream
$output = fopen('php://output', 'w');

// Output UTF-8 Byte Order Mark (BOM) so Microsoft Excel displays accents correctly
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Header Row
fputcsv($output, [
    'Customer ID (Internal)',
    'Current Account Number',
    'New Account Number',
    'Full Name',
    'Gender',
    'Phone Number',
    'Location / Stall',
    'Assigned Collector'
]);

// Write each customer row
foreach ($customers as $c) {
    fputcsv($output, [
        $c['id'],
        $c['account_number'],
        '', // Blank column for admin to enter corrections
        $c['full_name'],
        $c['gender'] ?? '',
        $c['phone'],
        $c['location'] ?? '',
        $c['collector_name'] ?? 'Unassigned'
    ]);
}

fclose($output);
exit;
