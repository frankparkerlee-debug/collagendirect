<?php
// /public/api/portal/health.php
declare(strict_types=1);
require __DIR__ . '/../db.php';
json_out(200, ['ok' => true, 'message' => 'Portal API up']);
