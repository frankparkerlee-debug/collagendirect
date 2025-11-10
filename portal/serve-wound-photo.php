<?php
/**
 * Serve wound photo with portal authentication
 * Allows physicians to view photos for their own patients
 */
declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';

// Must be logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$photoId = $_GET['id'] ?? '';
if (!$photoId) {
    http_response_code(400);
    die('Photo ID required');
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'physician';

// Get photo from database
$stmt = $pdo->prepare("
    SELECT wp.photo_path, wp.patient_id, p.user_id
    FROM wound_photos wp
    JOIN patients p ON p.id = wp.patient_id
    WHERE wp.id = ?
");
$stmt->execute([$photoId]);
$photo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$photo) {
    http_response_code(404);
    die('Photo not found');
}

// Check access - user must be superadmin or own the patient
if ($userRole !== 'superadmin' && $photo['user_id'] !== $userId) {
    http_response_code(403);
    die('Access denied');
}

// Find the actual file
$photoPath = $photo['photo_path'];
$possiblePaths = [
    __DIR__ . '/../' . ltrim($photoPath, '/'),
    __DIR__ . '/../../' . ltrim($photoPath, '/'),
    '/opt/render/project/src/' . ltrim($photoPath, '/'),
];

$foundPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $foundPath = $path;
        break;
    }
}

if (!$foundPath) {
    http_response_code(404);
    error_log('[portal/serve-wound-photo] File not found. Tried: ' . implode(', ', $possiblePaths));
    error_log('[portal/serve-wound-photo] Photo path from DB: ' . $photoPath);
    die('Photo file not found on server');
}

// Determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $foundPath);
finfo_close($finfo);

// Serve the file
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($foundPath));
header('Cache-Control: private, max-age=3600');
readfile($foundPath);
