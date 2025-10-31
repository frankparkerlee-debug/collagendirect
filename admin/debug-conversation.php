<?php
// Debug script to check conversation threading
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';
$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
$auth = __DIR__ . '/auth.php'; if (is_file($auth)) require_once $auth;
if (function_exists('require_admin')) require_admin();

$patientId = $_GET['patient_id'] ?? '';

if (!$patientId) {
  header('Content-Type: text/html; charset=utf-8');
  echo '<h2>Conversation Debug Tool</h2>';
  echo '<p>Select a patient to debug their conversation thread:</p>';

  try {
    $stmt = $pdo->query("
      SELECT id, first_name, last_name, status_comment, provider_response, created_at
      FROM patients
      WHERE status_comment IS NOT NULL OR provider_response IS NOT NULL
      ORDER BY created_at DESC
      LIMIT 20
    ");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($patients)) {
      echo '<p>No patients with conversation messages found.</p>';

      // Show all patients instead
      $allStmt = $pdo->query("SELECT id, first_name, last_name, created_at FROM patients ORDER BY created_at DESC LIMIT 10");
      $allPatients = $allStmt->fetchAll(PDO::FETCH_ASSOC);
      echo '<h3>Recent patients (showing first 10):</h3><ul>';
      foreach ($allPatients as $p) {
        $url = '?patient_id=' . urlencode($p['id']);
        echo '<li><a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) . '</a> (ID: ' . htmlspecialchars($p['id']) . ')</li>';
      }
      echo '</ul>';
    } else {
      echo '<h3>Patients with conversation messages:</h3><ul>';
      foreach ($patients as $p) {
        $url = '?patient_id=' . urlencode($p['id']);
        $hasComment = !empty($p['status_comment']) ? '✓ Comments' : '';
        $hasResponse = !empty($p['provider_response']) ? '✓ Response' : '';
        echo '<li><a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) . '</a> - ' . $hasComment . ' ' . $hasResponse . '</li>';
      }
      echo '</ul>';
    }
  } catch (Exception $e) {
    echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
  }
  exit;
}

// First, list all patients to help debug
echo "Checking patient: " . htmlspecialchars($patientId) . "\n\n";

try {
  $countStmt = $pdo->query("SELECT COUNT(*) as total FROM patients");
  $total = $countStmt->fetch(PDO::FETCH_ASSOC);
  echo "Total patients in database: " . $total['total'] . "\n\n";

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
    // List first 5 patients to help find the right ID
    echo "Patient not found. Here are the first 5 patients:\n\n";
    $sampleStmt = $pdo->query("SELECT id, first_name, last_name FROM patients ORDER BY created_at DESC LIMIT 5");
    $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($samples as $s) {
      echo "  - ID: {$s['id']}, Name: {$s['first_name']} {$s['last_name']}\n";
    }
    die();
  }
} catch (Exception $e) {
  die("Database error: " . $e->getMessage() . "\n");
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
