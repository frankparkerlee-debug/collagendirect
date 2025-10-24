<?php
// admin/login.php - Redirect to unified login
declare(strict_types=1);

// Redirect to main login page with admin redirect
$next = $_GET['next'] ?? '/admin/index.php';
header('Location: /login?next=' . urlencode($next));
exit;
