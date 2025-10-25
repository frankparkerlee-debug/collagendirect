<?php
// /public/api/portal/patients.php
declare(strict_types=1);
require __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}
$uid = $_SESSION['user_id'];

function safe_str(?string $s): ?string {
  if ($s === null) return null;
  $s = trim($s);
  return $s === '' ? null : $s;
}

try {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $action = $_GET['action'] ?? $_POST['action'] ?? null;

  if ($method === 'GET' && isset($_GET['id'])) {
    // Single patient detail
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id=? AND user_id=?");
    $stmt->execute([$id, $uid]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

    // most recent order (if any)
    $o = $pdo->prepare("SELECT * FROM orders WHERE patient_id=? ORDER BY created_at DESC LIMIT 1");
    $o->execute([$id]);
    $lastOrder = $o->fetch(PDO::FETCH_ASSOC) ?: null;

    echo json_encode(['ok'=>true,'data'=>['patient'=>$p,'last_order'=>$lastOrder]]);
    exit;
  }

  if ($method === 'GET') {
    // List view
    $limit = (int)($_GET['limit'] ?? 50);
    $limit = max(1, min(500, $limit));
    $stmt = $pdo->prepare("
      SELECT id, first_name, last_name, dob, phone, email, address, city, state, zip,
             insurance_provider, insurance_member_id, insurance_group_id, insurance_payer_phone,
             note_path, ins_card_path, id_card_path, created_at, updated_at
      FROM patients
      WHERE user_id = ?
      ORDER BY updated_at DESC
      LIMIT {$limit}
    ");
    $stmt->execute([$uid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
  }

  if ($method === 'POST' && $action === 'update') {
    // Update demographics
    $id = $_POST['id'] ?? '';
    if (!$id) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_id']); exit; }

    // scope check
    $chk = $pdo->prepare("SELECT id FROM patients WHERE id=? AND user_id=?");
    $chk->execute([$id, $uid]);
    if (!$chk->fetch()) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

    $sql = "UPDATE patients SET
              first_name=?, last_name=?, dob=?,
              phone=?, email=?, address=?, city=?, state=?, zip=?,
              insurance_provider=?, insurance_member_id=?, insurance_group_id=?, insurance_payer_phone=?,
              updated_at = NOW()
            WHERE id=? AND user_id=?";
    $pdo->prepare($sql)->execute([
      safe_str($_POST['first_name'] ?? null),
      safe_str($_POST['last_name'] ?? null),
      safe_str($_POST['dob'] ?? null),
      safe_str($_POST['phone'] ?? null),
      safe_str($_POST['email'] ?? null),
      safe_str($_POST['address'] ?? null),
      safe_str($_POST['city'] ?? null),
      safe_str($_POST['state'] ?? null),
      safe_str($_POST['zip'] ?? null),
      safe_str($_POST['insurance_provider'] ?? null),
      safe_str($_POST['insurance_member_id'] ?? null),
      safe_str($_POST['insurance_group_id'] ?? null),
      safe_str($_POST['insurance_payer_phone'] ?? null),
      $id, $uid
    ]);

    echo json_encode(['ok'=>true]); exit;
  }

  if ($method === 'POST' && $action === 'upload') {
    // Upload/replace attachments at patient level
    $id = $_POST['id'] ?? '';
    if (!$id) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_id']); exit; }

    // scope check
    $chk = $pdo->prepare("SELECT id FROM patients WHERE id=? AND user_id=?");
    $chk->execute([$id, $uid]);
    if (!$chk->fetch()) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

    $updates = [];
    $params  = [];

    $uploads = [
      'note'     => ['/public/uploads/notes',     'note_path','note_mime'],
      'ins_card' => ['/public/uploads/insurance', 'ins_card_path','ins_card_mime'],
      'id_card'  => ['/public/uploads/ids',       'id_card_path','id_card_mime'],
    ];

    foreach ($uploads as $key => [$dir,$pathCol,$mimeCol]) {
      if (!empty($_FILES[$key]['tmp_name'])) {
        $tmp  = $_FILES[$key]['tmp_name'];
        $name = bin2hex(random_bytes(8)) . '-' . preg_replace('/[^A-Za-z0-9\.\-_]/','_', $_FILES[$key]['name']);
        $abs  = $_SERVER['DOCUMENT_ROOT'] . $dir;
        if (!is_dir($abs)) { mkdir($abs, 0775, true); }
        $dest = $abs . '/' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
          http_response_code(500);
          echo json_encode(['ok'=>false,'error'=>"failed_to_move_$key"]); exit;
        }
        $rel = $dir . '/' . $name;
        $updates[] = "$pathCol = ?";
        $params[]  = $rel;
        $updates[] = "$mimeCol = ?";
        $params[]  = ($_FILES[$key]['type'] ?? 'application/octet-stream');
      }
    }

    if ($updates) {
      $sql = "UPDATE patients SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id=? AND user_id=?";
      $params[] = $id; $params[] = $uid;
      $pdo->prepare($sql)->execute($params);
    }

    echo json_encode(['ok'=>true]); exit;
  }

  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}
