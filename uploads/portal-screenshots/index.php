<?php
/**
 * Serve portal guide screenshots
 * Public access - no authentication required
 */

// Get requested filename
$filename = basename($_GET['file'] ?? '');

if (!$filename) {
    http_response_code(404);
    exit('File not specified');
}

// Sanitize filename - only allow alphanumeric, dash, underscore, and dot
if (!preg_match('/^[a-z0-9\-_\.]+$/i', $filename)) {
    http_response_code(400);
    exit('Invalid filename');
}

// Check if running on Render with persistent disk
$screenshotPath = is_dir('/var/data/uploads/portal-screenshots')
    ? '/var/data/uploads/portal-screenshots/' . $filename
    : __DIR__ . '/../../var/data/uploads/portal-screenshots/' . $filename;

// Check if file exists
if (!file_exists($screenshotPath)) {
    http_response_code(404);
    exit('File not found');
}

// Get file extension and set content type
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$contentTypes = [
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp'
];

$contentType = $contentTypes[$ext] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($screenshotPath));
header('Cache-Control: public, max-age=86400'); // Cache for 1 day
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

// Output file
readfile($screenshotPath);
