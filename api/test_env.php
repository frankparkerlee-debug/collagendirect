<?php
declare(strict_types=1);
require __DIR__ . '/lib/env.php';
header('Content-Type: text/plain');

// Helper to safely show values
function snippet(string $v, int $left = 8, int $right = 6): string {
  $len = strlen($v);
  if ($len === 0) return '(empty)';
  if ($len <= $left + $right) return $v;
  return substr($v, 0, $left) . '...' . substr($v, -$right);
}

// Show where we’re reading from
echo "Resolved .env path: " . realpath(__DIR__ . '/.env') . PHP_EOL;

// Core creds
echo "SENDGRID_API_KEY: " . snippet(env('SENDGRID_API_KEY','')) . PHP_EOL;
echo "SMTP_FROM: " . env('SMTP_FROM','(not set)') . PHP_EOL;
echo "SMTP_FROM_NAME: " . env('SMTP_FROM_NAME','(not set)') . PHP_EOL;

// Templates
echo "SG_TMPL_PASSWORD_RESET: " . snippet(env('SG_TMPL_PASSWORD_RESET','')) . PHP_EOL;
echo "SG_TMPL_ACCOUNT_CONFIRM: " . snippet(env('SG_TMPL_ACCOUNT_CONFIRM','')) . PHP_EOL;
echo "SG_TMPL_ORDER_CONFIRM: " . snippet(env('SG_TMPL_ORDER_CONFIRM','')) . PHP_EOL;
