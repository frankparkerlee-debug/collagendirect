<?php
// /admin/_safeguards.php — keep this included very early on every admin page

// Minimal HTML escape used by templates
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

// CSRF helper (non-breaking if your real one isn’t loaded yet)
if (!function_exists('csrf_field')) {
  function csrf_field(){
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';
  }
}

// Polyfill for older PHP
if (!function_exists('str_contains')) {
  function str_contains($haystack, $needle){ return $needle === '' ? true : strpos((string)$haystack, (string)$needle) !== false; }
}
