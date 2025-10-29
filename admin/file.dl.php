<?php
// /public/admin/file.dl.php — secure file proxy (robust path normalization + inline view)
declare(strict_types=1);

require_once __DIR__ . '/db.php';
$auth = __DIR__ . '/auth.php';
if (is_file($auth) && function_exists('require_admin')) require_admin();

/* ---- CSRF ---- */
if (empty($_GET['csrf']) || $_GET['csrf'] !== ($_SESSION['csrf'] ?? '')) {
  http_response_code(403); echo "forbidden"; exit;
}

/* ---- Normalize incoming path ---- */
$raw = (string)($_GET['p'] ?? '');
$rel = parse_url($raw, PHP_URL_PATH);                     // strip any query
if (!$rel) { http_response_code(400); echo "bad_path"; exit; }

/* Accept both /public/uploads/... and /uploads/... */
if (strpos($rel, '/public/uploads/') === 0) {
  $relLocal = substr($rel, strlen('/public'));            // -> /uploads/...
} elseif (strpos($rel, '/uploads/') === 0) {
  $relLocal = $rel;                                       // already /uploads/...
} else {
  http_response_code(400); echo "bad_path"; exit;
}

/* ---- Resolve safely under uploads directory ---- */
// Check persistent disk first, then fall back to local uploads
if (is_dir('/var/data/uploads')) {
  // Persistent disk on Render
  $abs = '/var/data' . $relLocal;  // /var/data/uploads/ids/file.jpg
  $uploadsRoot = '/var/data/uploads';
  error_log("[file.dl] Using persistent disk: abs={$abs}");
} else {
  // Local development
  $docRoot = realpath(__DIR__ . '/..');
  $abs = realpath($docRoot . $relLocal);
  $uploadsRoot = realpath($docRoot . '/uploads');
  error_log("[file.dl] Using local uploads: docRoot={$docRoot}, abs={$abs}, relLocal={$relLocal}");
}

if (!$abs || !is_file($abs)) {
  error_log("[file.dl] file not found: abs={$abs}, is_file=" . (is_file($abs) ? 'true' : 'false'));
  http_response_code(404); echo "not_found"; exit;
}

/* Must stay inside /uploads */
if (!$uploadsRoot || strncmp($abs, $uploadsRoot, strlen($uploadsRoot)) !== 0) {
  error_log("[file.dl] outside uploads: $abs, uploadsRoot={$uploadsRoot}");
  http_response_code(404); echo "not_found"; exit;
}

/* ---- Serve file (inline by default when mode=view) ---- */
$filename   = basename($abs);
$size       = @filesize($abs) ?: 0;
$finfo      = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
$mime       = $finfo ? (finfo_file($finfo, $abs) ?: 'application/octet-stream') : 'application/octet-stream';
if ($finfo) finfo_close($finfo);

$disposition = (($_GET['mode'] ?? '') === 'view') ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($filename) . '"');
// Allow caching for 1 hour to prevent re-downloading and improve performance
header('Cache-Control: private, max-age=3600');

$fp = @fopen($abs, 'rb');
if ($fp) { fpassthru($fp); fclose($fp); exit; }
error_log("[file.dl] fopen failed: $abs");
http_response_code(500); echo "read_error";
