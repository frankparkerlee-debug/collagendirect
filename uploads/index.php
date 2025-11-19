<?php
/**
 * File proxy for persistent disk uploads
 * Serves files from /opt/render/project/src/uploads on Render
 */

// Get the requested file path (everything after /uploads/)
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove /uploads/ prefix
$file_path = preg_replace('#^/uploads/#', '', $path);

// Security: prevent directory traversal
if (strpos($file_path, '..') !== false || strpos($file_path, './') !== false) {
    http_response_code(403);
    die('Forbidden');
}

// Try persistent disk first (Render production)
$persistent_disk_path = '/opt/render/project/src/uploads/' . $file_path;

// Fallback to document root (local development)
$local_path = ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..') . '/uploads/' . $file_path;

// Determine which path to use
$full_path = null;
if (file_exists($persistent_disk_path)) {
    $full_path = $persistent_disk_path;
} elseif (file_exists($local_path)) {
    $full_path = $local_path;
}

// File not found
if (!$full_path || !is_file($full_path)) {
    http_response_code(404);
    die('File not found');
}

// Get MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($full_path);

// Security: only allow specific file types
$allowed_mimes = [
    'application/pdf',
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/webp',
    'image/heic',
    'text/plain'
];

if (!in_array($mime, $allowed_mimes)) {
    http_response_code(403);
    die('File type not allowed');
}

// Set appropriate headers
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($full_path));
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
header('Cache-Control: private, max-age=3600');

// Output file
readfile($full_path);
exit;
