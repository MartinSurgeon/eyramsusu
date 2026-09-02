<?php
// install.php - Initializes database, tables, and seed users
require_once __DIR__ . '/config/db.php';

try {
    // 1. Connect to MySQL server without database first to ensure DB exists
    $pdoServer = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

    // 2. Connect to the eyramsusu database
    $pdo = get_db_connection();

    // Check for --fresh flag
    $isFresh = in_array('--fresh', $argv ?? []);
    if ($isFresh) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tables = ['daily_handovers', 'payouts', 'deposits', 'susu_cards', 'customers', 'users'];
        foreach ($tables as $tbl) {
            $pdo->exec("DROP TABLE IF EXISTS `{$tbl}`;");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        echo "CLEARED: Existing tables dropped for fresh install.\n";
    }

    // 3. Read and execute schema
    $schemaSql = file_get_contents(__DIR__ . '/database/schema.sql');
    $pdo->exec($schemaSql);

    // 4. Check if users already seeded
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        // Seed Admin & Collector
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $collectorPass = password_hash('collector123', PASSWORD_DEFAULT);

        $stmtUser = $pdo->prepare("INSERT INTO users (full_name, username, password_hash, role, phone) VALUES (?, ?, ?, ?, ?)");
        $stmtUser->execute(['Business Admin', 'admin', $adminPass, 'admin', '0244000111']);
        $adminId = $pdo->lastInsertId();

        $stmtUser->execute(['Kofi Mensah', 'kofi', $collectorPass, 'collector', '0244000222']);
        $collectorId = $pdo->lastInsertId();

        // Seed Sample Customers (Esi & Sena from business rules)
        $stmtCust = $pdo->prepare("INSERT INTO customers (account_number, full_name, phone, location, assigned_collector_id, change_balance) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtCust->execute(['ACC-1001', 'Esi Mensah', '0201112233', 'Makola Market, Shed 4', $collectorId, 0.00]);
        $esiId = $pdo->lastInsertId();

        $stmtCust->execute(['ACC-1002', 'Sena Agbeti', '0204445566', 'Kaneshie Station', $collectorId, 0.00]);
        $senaId = $pdo->lastInsertId();

        // Create initial 31-space Susu Cards
        // Esi chooses GH₵20
        $stmtCard = $pdo->prepare("INSERT INTO susu_cards (customer_id, card_number, daily_amount, total_spaces, spaces_filled, total_saved, status) VALUES (?, 1, ?, 31, 0, 0.00, 'active')");
        $stmtCard->execute([$esiId, 20.00]);

        // Sena chooses GH₵50
        $stmtCard->execute([$senaId, 50.00]);

        echo "SUCCESS: Database created, schema imported, and starter accounts seeded successfully!\n";
        echo "Admin Login: admin / admin123\n";
        echo "Collector Login: kofi / collector123\n";
    } else {
        echo "Database already initialized and users exist.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
