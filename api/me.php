<?php
// public/api/me.php
declare(strict_types=1);
require __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) {
  json_out(401, ['error'=>'Unauthorized']);
}

$stmt = $pdo->prepare("SELECT id,email,first_name,last_name,account_type,practice_name,status FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) {
  // session points at a deleted user – clean up
  session_destroy();
  json_out(401, ['error'=>'Unauthorized']);
}

json_out(200, ['user'=>$user]);
