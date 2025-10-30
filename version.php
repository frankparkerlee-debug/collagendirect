<?php
header('Content-Type: text/plain');
echo "Version: " . date('Y-m-d H:i:s') . "\n";
echo "Git commit: ";
system('git rev-parse --short HEAD 2>&1');
echo "\n";
echo "Last commit message: ";
system('git log -1 --pretty=%B 2>&1');
