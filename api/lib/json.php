<?php
declare(strict_types=1);
function json_out(array $payload, int $status=200): never {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload);
  exit;
}
