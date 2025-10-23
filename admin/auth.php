<?php
// admin/auth.php
declare(strict_types=1);
require __DIR__ . '/db.php';

function current_admin() {
  return $_SESSION['admin'] ?? null;
}
function require_admin(): void {
  if (!current_admin()) {
    header('Location: /admin/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin/'));
    exit;
  }
}
