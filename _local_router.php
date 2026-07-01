<?php
/**
 * LOCAL-ONLY router for PHP's built-in server (php -S).
 * Emulates the .htaccess pretty-URL rewrites that Apache normally handles.
 * Usage: php -S localhost:8000 _local_router.php   (gitignored)
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// Serve real existing files (css, js, images, *.php, etc.) as-is.
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
  return false;
}

// Pretty routes from .htaccess
if (preg_match('#^/login/?$#', $uri))  { require __DIR__ . '/login/index.php';  return true; }
if (preg_match('#^/logout/?$#', $uri)) { require __DIR__ . '/logout/index.php'; return true; }
if (preg_match('#^/portal/?$#', $uri)) { require __DIR__ . '/portal/index.php'; return true; }

// Directory index (DirectoryIndex index.php index.html)
if (is_dir($file)) {
  foreach (['index.php', 'index.html'] as $idx) {
    $cand = rtrim($file, '/') . '/' . $idx;
    if (file_exists($cand)) {
      if (substr($idx, -4) === '.php') { require $cand; return true; }
      return false; // let the server serve the static index.html
    }
  }
}

// Root
if ($uri === '/') {
  if (file_exists(__DIR__ . '/index.php'))  { require __DIR__ . '/index.php';  return true; }
  if (file_exists(__DIR__ . '/index.html')) { return false; }
}

return false;
