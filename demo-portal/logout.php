<?php
/**
 * Demo Portal Logout
 * Clears demo session and redirects to login
 */
declare(strict_types=1);

require_once __DIR__ . '/../admin/db.php';

// Clear demo session variables
unset($_SESSION['demo_mode']);
unset($_SESSION['demo_user_id']);
unset($_SESSION['demo_session_id']);
unset($_SESSION['demo_user_name']);
unset($_SESSION['demo_company']);

// Redirect to login
header('Location: /demo-portal/login.php');
exit;
