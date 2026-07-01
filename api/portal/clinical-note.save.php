<?php
/**
 * Save a guided clinical note (create or update). Standard form POST → redirects back
 * to the portal page. Stores the captured field values (structured JSONB) plus the
 * rendered note body, scoped to the logged-in account's patient.
 */
declare(strict_types=1);
try {
  require_once __DIR__ . '/../db.php';                 // session + $pdo
} catch (Throwable $e) {
  http_response_code(500); echo 'db_error'; exit;
}
require_once __DIR__ . '/../lib/clinical_note.php';

if (empty($_SESSION['user_id'])) { http_response_code(401); echo 'not_authenticated'; exit; }
$userId = $_SESSION['user_id'];

$patientId   = trim((string)($_POST['patient_id'] ?? ''));
$noteId      = trim((string)($_POST['note_id'] ?? ''));
$templateKey = trim((string)($_POST['template_key'] ?? 'wound_care_dictation'));
$status      = ($_POST['status'] ?? 'draft') === 'final' ? 'final' : 'draft';
$physician   = trim((string)($_POST['physician_name'] ?? ''));

$template = clinical_note_template($templateKey);
if (!$template) { http_response_code(400); echo 'bad_template'; exit; }
if ($patientId === '') { http_response_code(400); echo 'missing_patient'; exit; }

// Patient must belong to this account.
$pchk = $pdo->prepare("SELECT * FROM patients WHERE id = ? AND user_id = ?");
$pchk->execute([$patientId, $userId]);
$patient = $pchk->fetch(PDO::FETCH_ASSOC);
if (!$patient) { http_response_code(403); echo 'patient_not_found'; exit; }

// Assemble captured data.
$f = is_array($_POST['f'] ?? null) ? $_POST['f'] : [];
$w = is_array($_POST['w'] ?? null) ? array_values($_POST['w']) : [];
$data = ['f' => $f, 'w' => $w];
$woundCount = cn_wound_count($data);
$body = cn_render_note($template, $data, $patient, $woundCount);
$structuredJson = json_encode($data);

if ($noteId !== '') {
  // Update (only the owner's own note)
  $upd = $pdo->prepare("UPDATE clinical_notes
      SET structured = ?::jsonb, body = ?, status = ?, physician_name = ?, template_key = ?,
          signed_by = CASE WHEN ? = 'final' THEN ? ELSE signed_by END,
          signed_at = CASE WHEN ? = 'final' THEN NOW() ELSE signed_at END,
          updated_at = NOW()
      WHERE id = ? AND user_id = ?");
  $upd->execute([$structuredJson, $body, $status, ($physician ?: null), $templateKey,
                 $status, ($physician ?: null), $status, $noteId, $userId]);
  $savedId = $noteId;
} else {
  $savedId = bin2hex(random_bytes(16));
  $ins = $pdo->prepare("INSERT INTO clinical_notes
      (id, patient_id, user_id, physician_name, note_type, template_key, structured, body, status,
       signed_by, signed_at, created_by, created_at, updated_at)
      VALUES (?,?,?,?,?,?,?::jsonb,?,?,?, CASE WHEN ?='final' THEN NOW() ELSE NULL END, ?, NOW(), NOW())");
  $ins->execute([$savedId, $patientId, $userId, ($physician ?: null), 'wound_care', $templateKey,
                 $structuredJson, $body, $status, ($status === 'final' ? ($physician ?: null) : null),
                 $status, $userId]);
}

header('Location: /portal/?page=clinical-notes&note=' . rawurlencode($savedId) . '&saved=1');
exit;
