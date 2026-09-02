<?php
// reset_seed_data.php - Drops all existing test data and seeds official user and customer data
require_once __DIR__ . '/config/db.php';

echo "========================================================\n";
echo "   EYRAM SUSU - DATABASE PURGE & OFFICIAL RE-SEED       \n";
echo "========================================================\n\n";

$pdo = get_db_connection();

try {
    // 1. Disable Foreign Key Checks to safely truncate tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $tables = [
        'notifications',
        'daily_handovers',
        'payouts',
        'deposits',
        'susu_cards',
        'customers',
        'users'
    ];

    foreach ($tables as $table) {
        // Check if table exists before truncating
        $check = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetch();
        if ($check) {
            $pdo->exec("TRUNCATE TABLE `{$table}`");
            echo "[PURGED] Table '{$table}' truncated cleanly.\n";
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "\n>>> ALL TEST DATA PURGED SUCCESSFULLY! <<<\n\n";

    // 2. Insert Official Users
    echo "--- Seeding Official Staff Accounts ---\n";
    $stmtUser = $pdo->prepare("
        INSERT INTO users (full_name, username, password_hash, role, phone, is_active) 
        VALUES (?, ?, ?, ?, ?, 1)
    ");

    // Admin: Agbenyenuse Stanley (Username: Eyram, Password: Seyram1)
    $adminPassHash = password_hash('Seyram1', PASSWORD_DEFAULT);
    $stmtUser->execute(['Agbenyenuse Stanley', 'Eyram', $adminPassHash, 'admin', '0553224837']);
    $adminId = $pdo->lastInsertId();
    echo "[INSERTED] Admin: Agbenyenuse Stanley (@Eyram, ID: {$adminId})\n";

    // Collector: Kuddy Peggy (Username: Peggy, Password: Peggy123)
    $collectorPassHash = password_hash('Peggy123', PASSWORD_DEFAULT);
    $stmtUser->execute(['Kuddy Peggy', 'Peggy', $collectorPassHash, 'collector', '0555495796']);
    $collectorId = $pdo->lastInsertId();
    echo "[INSERTED] Collector: Kuddy Peggy (@Peggy, ID: {$collectorId})\n\n";

    // 3. Insert Official 7 Customers & Initial 31-Space Active Cards
    echo "--- Seeding 7 Adaklu Waya Customer Passbooks ---\n";
    $customers = [
        [
            'reg_no' => '0035',
            'name'   => 'kottoh Patience',
            'phone'  => '0242057910',
            'loc'    => 'Adaklu Waya',
            'amount' => 50.00
        ],
        [
            'reg_no' => '0036',
            'name'   => 'Soglo Vivian',
            'phone'  => '0592663701',
            'loc'    => 'Adaklu Waya',
            'amount' => 50.00
        ],
        [
            'reg_no' => '0005',
            'name'   => 'Kudi Lucky',
            'phone'  => '0545482671',
            'loc'    => 'Adaklu Waya',
            'amount' => 100.00
        ],
        [
            'reg_no' => '0022',
            'name'   => 'Wase Yaovi',
            'phone'  => '0241164340',
            'loc'    => 'Adaklu Waya',
            'amount' => 20.00
        ],
        [
            'reg_no' => '0021',
            'name'   => 'Kpedo Bismarck',
            'phone'  => '0546249032',
            'loc'    => 'Adaklu Waya',
            'amount' => 10.00
        ],
        [
            'reg_no' => '0043',
            'name'   => 'Anyadi Emmanuel',
            'phone'  => '0597515726',
            'loc'    => 'Adaklu Waya',
            'amount' => 10.00
        ],
        [
            'reg_no' => '0004',
            'name'   => 'Deku Wonder',
            'phone'  => '0249771299',
            'loc'    => 'Adaklu Waya',
            'amount' => 20.00
        ]
    ];

    $stmtCust = $pdo->prepare("
        INSERT INTO customers (account_number, full_name, phone, location, assigned_collector_id, change_balance, is_active) 
        VALUES (?, ?, ?, ?, ?, 0.00, 1)
    ");

    $stmtCard = $pdo->prepare("
        INSERT INTO susu_cards (customer_id, card_number, daily_amount, total_spaces, spaces_filled, total_saved, status) 
        VALUES (?, 1, ?, 31, 0, 0.00, 'active')
    ");

    foreach ($customers as $c) {
        $stmtCust->execute([$c['reg_no'], $c['name'], $c['phone'], $c['loc'], $collectorId]);
        $customerId = $pdo->lastInsertId();

        $stmtCard->execute([$customerId, $c['amount']]);
        $cardId = $pdo->lastInsertId();

        echo "[INSERTED] #{$c['reg_no']} - {$c['name']} | GH₵ {$c['amount']}/space | Card ID: {$cardId} (Assigned to Peggy)\n";
    }

    echo "\n========================================================\n";
    echo ">>> RE-SEEDING COMPLETED SUCCESSFULLY! <<<\n";
    echo "Users: 2 (1 Admin, 1 Collector)\n";
    echo "Customers: 7 (All with Active Card #1 at Adaklu Waya)\n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
