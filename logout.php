<?php
// logout.php - Safely logs out the user
require_once __DIR__ . '/includes/auth.php';

logout_user();
session_start();
set_flash_message('success', 'You have successfully logged out.');
header('Location: login.php');
exit;
