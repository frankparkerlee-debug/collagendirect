<?php
declare(strict_types=1);
require __DIR__ . '/../db.php';
if (empty($_SESSION['user_id'])) json_out(401, ['error'=>'Unauthorized']);
json_out(200, ['ok'=>true, 'items'=>[]]);
