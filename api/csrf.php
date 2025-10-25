<?php
// public/api/csrf.php
declare(strict_types=1);
require __DIR__ . '/db.php'; // starts session & defines json_out($code, $data)

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

header('Content-Type: application/json; charset=utf-8');
json_out(200, ['csrfToken' => $_SESSION['csrf']]);
