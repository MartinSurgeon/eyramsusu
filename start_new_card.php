<?php
// start_new_card.php - Start a new Susu Card cycle for an existing customer
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$currentUser = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $dailyAmount = (float)($_POST['daily_amount'] ?? 0);
    $redirectTo = trim($_POST['redirect_to'] ?? '');

    if ($customerId > 0 && $dailyAmount > 0) {
        $pdo = get_db_connection();

        // Check customer exists and verify route assignment if collector
        $stmtCust = $pdo->prepare("SELECT id, full_name, assigned_collector_id, is_active FROM customers WHERE id = ?");
        $stmtCust->execute([$customerId]);
        $customer = $stmtCust->fetch();

        if (!$customer || !(int)$customer['is_active']) {
            set_flash_message('error', 'Invalid or inactive customer.');
            header('Location: customers.php');
            exit;
        }

        if ($currentUser['role'] === 'collector' && !empty($customer['assigned_collector_id']) && (int)$customer['assigned_collector_id'] !== (int)$currentUser['id']) {
            set_flash_message('error', 'You can only open subsequent cards for customers assigned to your route.');
            header('Location: customers.php');
            exit;
        }

        // Safety Guard: Check if customer already has an active card
        $stmtCheck = $pdo->prepare("SELECT id FROM susu_cards WHERE customer_id = ? AND status = 'active' LIMIT 1");
        $stmtCheck->execute([$customerId]);
        if ($existingCardId = $stmtCheck->fetchColumn()) {
            set_flash_message('error', 'This customer already has an active Susu Card. Please complete or close it before opening a new one.');
            header("Location: view_card.php?id={$existingCardId}");
            exit;
        }

        // Get max card number and completed history for this customer
        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(card_number), 0) FROM susu_cards WHERE customer_id = ?");
        $stmtMax->execute([$customerId]);
        $maxCardNum = (int)$stmtMax->fetchColumn();
        $nextCardNum = $maxCardNum + 1;

        // Business Rule: Collectors can only open Card #2 onwards for clients who finished their previous card
        if ($currentUser['role'] === 'collector') {
            if ($maxCardNum < 1) {
                set_flash_message('error', 'Only Administrators are authorized to enroll and open the initial Card #1 for a new customer.');
                header('Location: customers.php');
                exit;
            }

            // Verify previous card is completed or closed
            $stmtFinished = $pdo->prepare("SELECT COUNT(*) FROM susu_cards WHERE customer_id = ? AND status IN ('completed', 'closed_early')");
            $stmtFinished->execute([$customerId]);
            if ((int)$stmtFinished->fetchColumn() === 0) {
                set_flash_message('error', 'Customer must complete their previous Susu Card before opening a new card.');
                header('Location: customers.php');
                exit;
            }
        }

        // Insert new card
        $stmtInsert = $pdo->prepare("
            INSERT INTO susu_cards (customer_id, card_number, daily_amount, total_spaces, spaces_filled, total_saved, status) 
            VALUES (?, ?, ?, 31, 0, 0.00, 'active')
        ");
        $stmtInsert->execute([$customerId, $nextCardNum, $dailyAmount]);
        $newCardId = $pdo->lastInsertId();

        // Audit Trail
        log_audit_event(
            $currentUser['id'],
            'open_card',
            "Opened 31-Space Susu Card #{$nextCardNum} (GH₵ " . number_format($dailyAmount, 2) . "/day) for {$customer['full_name']} (#{$customerId})"
        );

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
