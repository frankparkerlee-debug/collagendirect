<?php
// admin/download-all.php - Download all order documents as ZIP (manufacturer only)
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

// Get current admin user
$admin = current_admin();
$adminRole = $admin['role'] ?? '';

// Only manufacturer can download all documents
if ($adminRole !== 'manufacturer') {
  http_response_code(403);
  die('Access denied. This feature is only available to manufacturer users.');
}

// Verify CSRF token
if (empty($_GET['csrf']) || $_GET['csrf'] !== ($_SESSION['csrf'] ?? '')) {
  http_response_code(403);
  die('Invalid CSRF token');
}

// Get order ID
$orderId = trim($_GET['id'] ?? '');
if (empty($orderId)) {
  http_response_code(400);
  die('Order ID required');
}

/* ================= Helpers ================= */
function uploads_root_abs() {
  $cands = [
    realpath(__DIR__ . '/../uploads'),
    isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT'] . '/public/uploads') : false,
    isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT'] . '/uploads') : false,
  ];
  foreach ($cands as $p) { if ($p && is_dir($p)) return rtrim($p, '/'); }
  return rtrim(__DIR__ . '/../uploads', '/');
}

function list_bucket_files_abs($bucket) {
  $dir = uploads_root_abs() . '/' . trim($bucket, '/');
  if (!is_dir($dir)) return [];
  $files = glob($dir.'/*'); if (!$files) $files = [];
  $files = array_values(array_filter($files, 'is_file'));
  return $files;
}

function find_bucket_files_full($bucket, $tokens) {
  $absFiles = list_bucket_files_abs($bucket);
  if (!$absFiles) return [];
  $tokens = array_values(array_filter(array_map('strval', $tokens)));
  foreach ($tokens as &$t) $t = strtolower($t);
  $hits = [];
  foreach ($absFiles as $abs) {
    $name = strtolower(basename($abs));
    $ok = false;
    foreach ($tokens as $t) {
      if ($t !== '' && strpos($name, $t) !== false) {
        $ok = true;
        break;
      }
    }
    if ($ok) $hits[] = $abs;
  }
  return $hits;
}

/* ================= Get Order Data ================= */
try {
  $stmt = $pdo->prepare("
    SELECT o.id, o.patient_id,
           p.first_name, p.last_name
    FROM orders o
    LEFT JOIN patients p ON p.id = o.patient_id
    WHERE o.id = ?
  ");
  $stmt->execute([$orderId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    http_response_code(404);
    die('Order not found');
  }

  // Build patient name and search tokens
  $patientName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
  $patientId = (string)($order['patient_id'] ?? '');
  $slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($patientName));
  $tokens = array_filter([$patientId, $orderId, $slug]);

  // Find all documents
  $noteFiles = find_bucket_files_full('notes', $tokens);
  $idFiles = find_bucket_files_full('ids', $tokens);
  $insFiles = find_bucket_files_full('insurance', $tokens);

  $allFiles = array_merge($noteFiles, $idFiles, $insFiles);

  if (empty($allFiles)) {
    http_response_code(404);
    die('No documents found for this order');
  }

  // Create ZIP file
  $zipName = "order_{$orderId}_documents.zip";
  $zipPath = sys_get_temp_dir() . '/' . $zipName;

  $zip = new ZipArchive();
  if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('Failed to create ZIP file');
  }

  // Add files to ZIP with organized folders
  foreach ($noteFiles as $file) {
    $zip->addFile($file, 'notes/' . basename($file));
  }
  foreach ($idFiles as $file) {
    $zip->addFile($file, 'patient_id/' . basename($file));
  }
  foreach ($insFiles as $file) {
    $zip->addFile($file, 'insurance_card/' . basename($file));
  }

  // Add order PDF
  $orderPdfPath = __DIR__ . '/order.pdf.php?id=' . urlencode($orderId);
  // Note: We can't directly add the generated PDF to the ZIP without executing it
  // Instead, we'll add a README with a link to the PDF
  $readme = "Order #$orderId Documents\n\n";
  $readme .= "Patient: $patientName\n";
  $readme .= "Patient ID: $patientId\n\n";
  $readme .= "Documents included:\n";
  $readme .= "- Notes: " . count($noteFiles) . " file(s)\n";
  $readme .= "- Patient ID: " . count($idFiles) . " file(s)\n";
  $readme .= "- Insurance Card: " . count($insFiles) . " file(s)\n\n";
  $readme .= "To view the complete order PDF, please visit:\n";
  $readme .= "https://collagendirect.onrender.com/admin/order.pdf.php?id=$orderId\n";
  $zip->addFromString('README.txt', $readme);

  $zip->close();

  // Send ZIP file to browser
  if (!file_exists($zipPath)) {
    http_response_code(500);
    die('ZIP file was not created');
  }

  header('Content-Type: application/zip');
  header('Content-Disposition: attachment; filename="' . $zipName . '"');
  header('Content-Length: ' . filesize($zipPath));
  header('Pragma: no-cache');
  header('Expires: 0');

  readfile($zipPath);
  unlink($zipPath); // Clean up temp file
  exit;

} catch (Throwable $e) {
  error_log('[download-all] ' . $e->getMessage());
  http_response_code(500);
  die('An error occurred while creating the download');
}
