<?php
// admin/logout.php
declare(strict_types=1);
require __DIR__ . '/db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $_SESSION['admin'] = null;
}
header('Location: /admin/login.php');
exit;
