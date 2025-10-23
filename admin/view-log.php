<?php
// /admin/view-log.php — TEMP log viewer (delete when stable)
$log = __DIR__ . '/admin_error.log';
header('Content-Type: text/plain; charset=utf-8');
if (!file_exists($log)) { echo "No log yet: $log\n"; exit; }
readfile($log);
