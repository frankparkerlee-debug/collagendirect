<?php
// Debug script to check conversation threading
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

$patientId = $_GET['patient_id'] ?? '';

if (!$patientId) {
  die('Usage: ?patient_id=xxx');
}

$stmt = $pdo->prepare("
  SELECT
    id,
    first_name,
    last_name,
    status_comment,
    provider_response,
    provider_response_at
  FROM patients
  WHERE id = ?
");
$stmt->execute([$patientId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
  die('Patient not found');
}

header('Content-Type: text/plain; charset=utf-8');

echo "Patient: {$patient['first_name']} {$patient['last_name']}\n";
echo "ID: {$patient['id']}\n\n";

echo "==================== STATUS_COMMENT FIELD ====================\n";
echo $patient['status_comment'] ?: '(empty)';
echo "\n\n";

echo "==================== PROVIDER_RESPONSE FIELD ====================\n";
echo $patient['provider_response'] ?: '(empty)';
echo "\n";
echo "Response At: " . ($patient['provider_response_at'] ?: '(null)');
echo "\n\n";

echo "==================== PARSED MESSAGES ====================\n";

$messages = [];

// Parse all messages from status_comment
if (!empty($patient['status_comment'])) {
  $parts = preg_split('/\n\n---\n\n/', $patient['status_comment']);
  echo "Found " . count($parts) . " parts in status_comment\n\n";

  foreach ($parts as $i => $part) {
    echo "Part $i:\n";
    echo substr($part, 0, 100) . "...\n\n";

    // Match manufacturer messages
    if (preg_match('/^\[([^\]]+)\]\s+Manufacturer:\n(.+)/s', $part, $match)) {
      $messages[] = [
        'type' => 'manufacturer',
        'timestamp' => $match[1],
        'message' => trim($match[2])
      ];
      echo "  -> Matched as MANUFACTURER message\n";
      continue;
    }

    // Match physician messages
    if (preg_match('/^\[([^\]]+)\]\s+Physician:\n(.+)/s', $part, $match)) {
      $messages[] = [
        'type' => 'provider',
        'timestamp' => $match[1],
        'message' => trim($match[2])
      ];
      echo "  -> Matched as PHYSICIAN message\n";
      continue;
    }

    echo "  -> NO MATCH\n";
  }
}

echo "\n==================== CHRONOLOGICAL MESSAGE LIST ====================\n";
usort($messages, function($a, $b) {
  return strtotime($a['timestamp']) - strtotime($b['timestamp']);
});

foreach ($messages as $i => $msg) {
  echo ($i + 1) . ". [{$msg['timestamp']}] " . strtoupper($msg['type']) . ":\n";
  echo "   " . substr($msg['message'], 0, 80) . "...\n\n";
}

echo "Total messages parsed: " . count($messages) . "\n";
