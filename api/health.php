<?php
// public/api/health.php
declare(strict_types=1);
require __DIR__ . '/db.php';
json_out(200, ['ok' => true, 'db' => 'connected']);
