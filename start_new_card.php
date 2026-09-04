<?php
// start_new_card.php - Start a new Susu Card cycle for an existing customer
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $dailyAmount = (float)($_POST['daily_amount'] ?? 0);
    $redirectTo = trim($_POST['redirect_to'] ?? '');

    if ($customerId > 0 && $dailyAmount > 0) {
        $pdo = get_db_connection();

        // Safety Guard: Check if customer already has an active card
        $stmtCheck = $pdo->prepare("SELECT id FROM susu_cards WHERE customer_id = ? AND status = 'active' LIMIT 1");
        $stmtCheck->execute([$customerId]);
        if ($existingCardId = $stmtCheck->fetchColumn()) {
            set_flash_message('error', 'This customer already has an active Susu Card. Please complete or close it before opening a new one.');
            header("Location: view_card.php?id={$existingCardId}");
            exit;
        }

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

        set_flash_message('success', "New 31-Space Susu Card #{$nextCardNum} opened successfully!");
        if (!empty($redirectTo)) {
            header("Location: " . $redirectTo);
        } else {
            header("Location: view_card.php?id={$newCardId}");
        }
        exit;
    }
}

header('Location: customers.php');
exit;
