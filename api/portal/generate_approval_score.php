<?php
// API endpoint to generate AI approval score for patient profile
// Called automatically when physician completes patient documentation

// Prevent any HTML/PHP errors from breaking JSON response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();

try {
  require_once __DIR__ . '/../db.php';
  require_once __DIR__ . '/../lib/ai_service.php';
} catch (Exception $e) {
  echo json_encode(['ok' => false, 'error' => 'Failed to load required files: ' . $e->getMessage()]);
  exit;
}

// Check authentication (portal uses 'user_id' not 'portal_user_id')
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$userId = $_SESSION['user_id'];
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'physician';

// Get patient ID
$patientId = '';
if (isset($_POST['patient_id'])) {
  $patientId = trim($_POST['patient_id']);
} elseif (isset($_GET['patient_id'])) {
  $patientId = trim($_GET['patient_id']);
}

if (empty($patientId)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Patient ID required']);
  exit;
}

try {
  // Fetch complete patient data (without trying to read file content via SQL)
  if ($userRole === 'superadmin') {
    $stmt = $pdo->prepare("SELECT p.* FROM patients p WHERE p.id = ?");
    $stmt->execute([$patientId]);
  } else {
    $stmt = $pdo->prepare("SELECT p.* FROM patients p WHERE p.id = ? AND p.user_id = ?");
    $stmt->execute([$patientId, $userId]);
  }

  $patient = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$patient) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Patient not found or access denied']);
    exit;
  }

  // Helper function to convert relative path to absolute filesystem path
  $getAbsolutePath = function($relativePath) {
    if (empty($relativePath)) return null;

    // If already absolute, return it
    if ($relativePath[0] === '/') {
      // Check if it's a relative path starting with /uploads
      if (strpos($relativePath, '/uploads/') === 0) {
        // Try persistent disk first (Render production)
        if (is_dir('/var/data/uploads')) {
          $absPath = '/var/data' . $relativePath;
          if (file_exists($absPath)) return $absPath;
        }
        // Try document root (local development)
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($docRoot) {
          $absPath = $docRoot . $relativePath;
          if (file_exists($absPath)) return $absPath;
        }
        // Try relative to current directory
        $absPath = __DIR__ . '/../..' . $relativePath;
        if (file_exists($absPath)) return realpath($absPath);
      }
      return file_exists($relativePath) ? $relativePath : null;
    }
    return file_exists($relativePath) ? realpath($relativePath) : null;
  };

  // Helper function to extract text from PDF
  $extractPdfText = function($pdfPath) {
    if (!file_exists($pdfPath)) return '';

    // Try using pdftotext if available
    $output = '';
    $textFile = tempnam(sys_get_temp_dir(), 'pdf_');
    exec("pdftotext " . escapeshellarg($pdfPath) . " " . escapeshellarg($textFile) . " 2>/dev/null", $execOutput, $returnCode);

    if ($returnCode === 0 && file_exists($textFile)) {
      $output = file_get_contents($textFile);
      unlink($textFile);
      return $output;
    }

    // Fallback: read raw PDF and extract basic text (very limited)
    $content = file_get_contents($pdfPath);
    // This is a very basic extraction - it may not work well for all PDFs
    if (preg_match_all('/\((.*?)\)/s', $content, $matches)) {
      return implode(' ', $matches[1]);
    }

    return '[PDF file present but text extraction not available - file exists and is readable]';
  };

  // Read notes from file if path provided
  $patient['notes_text'] = '';
  if (!empty($patient['notes_path'])) {
    $notesAbsPath = $getAbsolutePath($patient['notes_path']);
    if ($notesAbsPath && file_exists($notesAbsPath)) {
      $mime = $patient['notes_mime'] ?? '';
      if (strpos($mime, 'pdf') !== false || pathinfo($notesAbsPath, PATHINFO_EXTENSION) === 'pdf') {
        $patient['notes_text'] = $extractPdfText($notesAbsPath);
      } else {
        $patient['notes_text'] = @file_get_contents($notesAbsPath) ?: '';
      }
    }
  }

  // Prepare documents array for AI analysis with actual file content
  $documents = [];

  // Add ID card document
  if (!empty($patient['id_card_path'])) {
    $idAbsPath = $getAbsolutePath($patient['id_card_path']);
    $extracted = '';
    if ($idAbsPath && file_exists($idAbsPath)) {
      $mime = $patient['id_card_mime'] ?? '';
      if (strpos($mime, 'pdf') !== false || pathinfo($idAbsPath, PATHINFO_EXTENSION) === 'pdf') {
        $extracted = $extractPdfText($idAbsPath);
      } else {
        $extracted = '[Image file present - ID card uploaded and available]';
      }
    }

    $documents[] = [
      'type' => 'Photo ID',
      'filename' => basename($patient['id_card_path']),
      'path' => $patient['id_card_path'],
      'mime' => isset($patient['id_card_mime']) ? $patient['id_card_mime'] : 'unknown',
      'extracted_text' => $extracted,
      'exists' => $idAbsPath && file_exists($idAbsPath)
    ];
  }

  // Add insurance card document
  if (!empty($patient['ins_card_path'])) {
    $insAbsPath = $getAbsolutePath($patient['ins_card_path']);
    $extracted = '';
    if ($insAbsPath && file_exists($insAbsPath)) {
      $mime = $patient['ins_card_mime'] ?? '';
      if (strpos($mime, 'pdf') !== false || pathinfo($insAbsPath, PATHINFO_EXTENSION) === 'pdf') {
        $extracted = $extractPdfText($insAbsPath);
      } else {
        $extracted = '[Image file present - Insurance card uploaded and available]';
      }
    }

    $documents[] = [
      'type' => 'Insurance Card',
      'filename' => basename($patient['ins_card_path']),
      'path' => $patient['ins_card_path'],
      'mime' => isset($patient['ins_card_mime']) ? $patient['ins_card_mime'] : 'unknown',
      'extracted_text' => $extracted,
      'exists' => $insAbsPath && file_exists($insAbsPath)
    ];
  }

  // Add clinical notes document
  if (!empty($patient['notes_path'])) {
    $notesAbsPath = $getAbsolutePath($patient['notes_path']);

    $documents[] = [
      'type' => 'Clinical Notes',
      'filename' => basename($patient['notes_path']),
      'path' => $patient['notes_path'],
      'mime' => isset($patient['notes_mime']) ? $patient['notes_mime'] : 'unknown',
      'extracted_text' => isset($patient['notes_text']) ? $patient['notes_text'] : '',
      'exists' => $notesAbsPath && file_exists($notesAbsPath)
    ];
  }

  // Initialize AI service
  $aiService = new AIService();

  // Generate approval score
  $result = $aiService->generateApprovalScore($patient, $documents);

  if (isset($result['error'])) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
  }

  // Save the score color to the database for display in patient lists
  try {
    $updateStmt = $pdo->prepare("
      UPDATE patients
      SET approval_score_color = ?,
          approval_score_at = NOW()
      WHERE id = ?
    ");
    $updateStmt->execute([$result['score'], $patientId]);
  } catch (Exception $e) {
    error_log("Failed to save approval score color: " . $e->getMessage());
    // Don't fail the request, just log the error
  }

  // Save complete feedback to patient_approval_scores table (for persistence)
  try {
    // Check if table exists first
    $tableCheck = $pdo->query("
      SELECT COUNT(*) as cnt
      FROM information_schema.tables
      WHERE table_name = 'patient_approval_scores'
    ");
    $tableExists = (int)$tableCheck->fetchColumn() > 0;

    if ($tableExists) {
      $insertStmt = $pdo->prepare("
        INSERT INTO patient_approval_scores
        (patient_id, score, score_numeric, summary, missing_items, complete_items,
         recommendations, concerns, document_analysis, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $insertStmt->execute([
        $patientId,
        $result['score'],
        $result['score_numeric'],
        $result['summary'],
        json_encode($result['missing_items']),
        json_encode($result['complete_items']),
        json_encode($result['recommendations']),
        json_encode($result['concerns']),
        json_encode($result['document_analysis']),
        $userId
      ]);
    }
  } catch (Exception $e) {
    error_log("Failed to save complete approval score feedback: " . $e->getMessage());
    // Don't fail the request, just log the error
  }

  // Return the score
  echo json_encode([
    'ok' => true,
    'score' => $result['score'],
    'score_numeric' => $result['score_numeric'],
    'summary' => $result['summary'],
    'missing_items' => $result['missing_items'],
    'complete_items' => $result['complete_items'],
    'recommendations' => $result['recommendations'],
    'concerns' => $result['concerns'],
    'document_analysis' => $result['document_analysis']
  ]);

} catch (Exception $e) {
  error_log("Approval score generation error: " . $e->getMessage());
  error_log("Stack trace: " . $e->getTraceAsString());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Failed to generate approval score', 'details' => $e->getMessage()]);
} catch (Throwable $e) {
  error_log("Fatal error in approval score: " . $e->getMessage());
  error_log("Stack trace: " . $e->getTraceAsString());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
}
