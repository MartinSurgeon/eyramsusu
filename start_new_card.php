<?php
// start_new_card.php - Start a new Susu Card cycle for an existing customer
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $dailyAmount = (float)($_POST['daily_amount'] ?? 0);

    if ($customerId > 0 && $dailyAmount > 0) {
        $pdo = get_db_connection();

        // Get max card number for this customer
        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(card_number), 0) FROM susu_cards WHERE customer_id = ?");
        $stmtMax->execute([$customerId]);
        $nextCardNum = (int)$stmtMax->fetchColumn() + 1;

        // Insert new card
        $stmtInsert = $pdo->prepare("
            INSERT INTO susu_cards (customer_id, card_number, daily_amount, total_spaces, spaces_filled, total_saved, status) 
            VALUES (?, ?, ?, 31, 0, 0.00, 'active')
        ");
        $stmtInsert->execute([$customerId, $nextCardNum, $dailyAmount]);
        $newCardId = $pdo->lastInsertId();

        set_flash_message('success', "New Susu Card #{$nextCardNum} opened successfully!");
        header("Location: view_card.php?id={$newCardId}");
        exit;
    }
}

header('Location: customers.php');
exit;
