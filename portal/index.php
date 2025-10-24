<?php
// /public/portal/index.php — CollagenDirect Physician Portal
declare(strict_types=1);

/* ------------ DB + session/bootstrap ------------ */
require __DIR__ . '/../api/db.php'; // defines $pdo + session helpers

if (empty($_SESSION['user_id'])) {
  header('Location: /login?next=/portal/index.php?page=dashboard');
  exit;
}
$userId = (string)$_SESSION['user_id'];

// Load user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
  // User not found, destroy session and redirect to login
  session_destroy();
  header('Location: /login');
  exit;
}

// Check if this is a referral-only practice (no billing features)
$isReferralOnly = (bool)($user['is_referral_only'] ?? false);

// Check if user is a practice admin or superadmin (has access to /admin)
$userRole = $user['role'] ?? 'physician';
$isPracticeAdmin = in_array($userRole, ['practice_admin', 'superadmin']);

/* ------------ Upload roots (keep structure) ------------ */
$UPLOAD_ROOT = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
$DIRS = [
  'ids'       => $UPLOAD_ROOT . '/ids',
  'insurance' => $UPLOAD_ROOT . '/insurance',
  'notes'     => $UPLOAD_ROOT . '/notes',
  'aob'       => $UPLOAD_ROOT . '/aob', // NEW for AOB files
];
foreach ($DIRS as $p) { if (!is_dir($p)) @mkdir($p, 0755, true); }

/* ------------ Helpers ------------ */
function jerr(string $m, int $c=400){ http_response_code($c); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>$m]); exit; }
function jok($d=[]){ header('Content-Type: application/json'); echo json_encode(['ok'=>true]+$d); exit; }
function slug(string $n){ $s=preg_replace('~[^\pL\d]+~u','-',$n); $s=trim($s,'-'); $s=@iconv('UTF-8','ASCII//TRANSLIT',$s)?:$s; $s=strtolower($s); $s=preg_replace('~[^-\w]+~','',$s); return $s?:'file'; }
function validPhone(?string $p){ return $p===null||$p===''||preg_match('/^\d{10}$/',$p); }
function validEmail(?string $e){ return $e===null||$e===''||filter_var($e,FILTER_VALIDATE_EMAIL); }
function usStates(): array {
  return ['AL','AK','AZ','AR','CA','CO','CT','DE','DC','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'];
}

/* ============================================================
   API
   ============================================================ */
$action = $_GET['action'] ?? null;
if ($action) {

  if ($action==='metrics'){
    $q=$pdo->prepare("SELECT COUNT(*) FROM patients WHERE user_id=?"); $q->execute([$userId]); $patients=(int)$q->fetchColumn();
    $q=$pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id=? AND status IN ('submitted','pending')"); $q->execute([$userId]); $pending=(int)$q->fetchColumn();
    $q=$pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id=? AND status IN ('approved','active','shipped')"); $q->execute([$userId]); $active=(int)$q->fetchColumn();
    jok(['metrics'=>['patients'=>$patients,'pending'=>$pending,'active_orders'=>$active]]);
  }

  if ($action==='patients'){
    $q=trim((string)($_GET['q']??'')); $limit=max(1,min(300,(int)($_GET['limit']??100))); $offset=max(0,(int)($_GET['offset']??0));
    $args=[$userId,$userId,$userId];
    $join="LEFT JOIN (
        SELECT o1.patient_id,o1.status,o1.shipments_remaining,o1.created_at
        FROM orders o1
        JOIN (SELECT patient_id,MAX(created_at) m FROM orders WHERE user_id=? GROUP BY patient_id) last
          ON last.patient_id=o1.patient_id AND last.m=o1.created_at
        WHERE o1.user_id=?
      ) lo ON lo.patient_id=p.id";
    $sql="SELECT p.id,p.first_name,p.last_name,p.dob,p.phone,p.email,p.address,p.city,p.state,p.zip,p.mrn,
                 lo.status last_status,lo.shipments_remaining last_remaining
          FROM patients p $join
          WHERE p.user_id=?";
    if ($q!==''){
      $like="%$q%";
      $sql.=" AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ? OR p.email LIKE ? OR p.mrn LIKE ?)";
      array_push($args,$like,$like,$like,$like,$like);
    }
    $sql.=" ORDER BY p.updated_at DESC,p.created_at DESC LIMIT $limit OFFSET $offset";
    $st=$pdo->prepare($sql); $st->execute($args);
    jok(['rows'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
  }

  if ($action==='patient.get'){
    $pid=(string)($_GET['id']??''); if($pid==='') jerr('Missing patient id');
    $s=$pdo->prepare("SELECT id,user_id,first_name,last_name,dob,mrn,phone,email,address,city,state,zip,sex,
                             insurance_provider,insurance_member_id,insurance_group_id,insurance_payer_phone,
                             id_card_path,id_card_mime,ins_card_path,ins_card_mime,
                             aob_path,aob_signed_at,
                             created_at,updated_at
                      FROM patients WHERE id=? AND user_id=?");
    $s->execute([$pid,$userId]); $p=$s->fetch(PDO::FETCH_ASSOC);
    if(!$p) jerr('Patient not found',404);

    $o=$pdo->prepare("SELECT id,status,product,product_id,product_price,shipments_remaining,delivery_mode,payment_type,
                             shipping_name,shipping_phone,shipping_address,shipping_city,shipping_state,shipping_zip,
                             wound_location,wound_laterality,wound_notes,
                             created_at,updated_at,expires_at,
                             sign_name,sign_title,signed_at,
                             rx_note_name,rx_note_mime,rx_note_path
                      FROM orders
                      WHERE patient_id=? AND user_id=?
                      ORDER BY created_at DESC");
    $o->execute([$pid,$userId]); $orders=$o->fetchAll(PDO::FETCH_ASSOC);

    jok(['patient'=>$p,'orders'=>$orders]);
  }

  if ($action==='patient.save'){
    $pid=(string)($_POST['id']??'');
    $first=trim((string)($_POST['first_name']??''));
    $last =trim((string)($_POST['last_name']??''));
    $dob  =$_POST['dob']??null;
    $mrn  =trim((string)($_POST['mrn']??''));
    $phone=$_POST['phone']??null; $cell_phone=$_POST['cell_phone']??null; $email=$_POST['email']??null;
    $address=$_POST['address']??null; $city=$_POST['city']??null; $state=$_POST['state']??null; $zip=$_POST['zip']??null;
    $ins_provider=$_POST['insurance_provider']??null; $ins_member_id=$_POST['insurance_member_id']??null;
    $ins_group_id=$_POST['insurance_group_id']??null; $ins_payer_phone=$_POST['insurance_payer_phone']??null;

    if($first===''||$last==='') jerr('First and last name are required');
    if(!validPhone($phone)) jerr('Phone must be 10 digits');
    if(!validEmail($email)) jerr('Invalid email');

    if ($pid===''){
      if($mrn===''){ $mrn = 'CD-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(2)),0,4)); }
      $pid=bin2hex(random_bytes(16));
      $st=$pdo->prepare("INSERT INTO patients
        (id,user_id,first_name,last_name,dob,mrn,city,state,phone,cell_phone,email,address,zip,
         insurance_provider,insurance_member_id,insurance_group_id,insurance_payer_phone,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
      $st->execute([$pid,$userId,$first,$last,$dob,$mrn,$city,$state,$phone,$cell_phone,$email,$address,$zip,
                    $ins_provider,$ins_member_id,$ins_group_id,$ins_payer_phone]);
    } else {
      $st=$pdo->prepare("UPDATE patients SET first_name=?,last_name=?,dob=?,mrn=?,city=?,state=?,phone=?,cell_phone=?,email=?,address=?,zip=?,
                         insurance_provider=?,insurance_member_id=?,insurance_group_id=?,insurance_payer_phone=?,updated_at=NOW()
                         WHERE id=? AND user_id=?");
      $st->execute([$first,$last,$dob,$mrn,$city,$state,$phone,$cell_phone,$email,$address,$zip,
                    $ins_provider,$ins_member_id,$ins_group_id,$ins_payer_phone,$pid,$userId]);
    }
    jok(['id'=>$pid,'mrn'=>$mrn]);
  }

  /* PATIENT uploads — patient-level ID/INS, plus generated AOB; order-level RX only */
  if ($action==='patient.upload'){
    $pid=(string)($_POST['patient_id']??''); $type=(string)($_POST['type']??''); // id|ins|rx|aob
    if($pid==='' || !in_array($type,['id','ins','rx','aob'],true)) jerr('Invalid upload');
    $chk=$pdo->prepare("SELECT id,first_name,last_name FROM patients WHERE id=? AND user_id=?"); $chk->execute([$pid,$userId]);
    $prow=$chk->fetch(PDO::FETCH_ASSOC); if(!$prow) jerr('Patient not found',404);

    $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/heic'=>'heic','text/plain'=>'txt'];
    $fi=new finfo(FILEINFO_MIME_TYPE);

    if ($type==='aob') {
      $now = date('Y-m-d H:i:s'); $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
      $content = "Assignment of Benefits (AOB)\n\n"
        ."Patient: ".($prow['first_name'].' '.$prow['last_name'])."\n"
        ."Signed by: Provider User ID ".$userId."\n"
        ."Date/Time: $now\n"
        ."Request IP: $ip\n\n"
        ."I authorize the Durable Medical Equipment supplier to bill my insurance on my behalf and receive payment directly.\n";
      $final = 'aob-'.date('Ymd-His').'-'.substr($pid,0,6).'.txt';
      $abs = $DIRS['aob'].'/'.$final;
      file_put_contents($abs,$content);
      $rel='/uploads/aob/'.$final;

      $pdo->prepare("UPDATE patients SET aob_path=?, aob_signed_at=?, aob_ip=?, updated_at=NOW() WHERE id=? AND user_id=?")
          ->execute([$rel,$now,$ip,$pid,$userId]);

      jok(['path'=>$rel,'name'=>'AOB.txt','mime'=>'text/plain','stamped'=>true]);
    }

    if($type!=='aob' && (empty($_FILES['file']) || $_FILES['file']['error']!==UPLOAD_ERR_OK)) jerr('No file uploaded');

    if ($type!=='aob'){
      $f=$_FILES['file']; if($f['size']>25*1024*1024) jerr('File too large (max 25MB)');
      $mime=$fi->file($f['tmp_name']) ?: 'application/octet-stream';
      if(!isset($allowed[$mime])) jerr('Unsupported file type'); $ext=$allowed[$mime];

      $final=slug(pathinfo($f['name'],PATHINFO_FILENAME)).'-'.date('Ymd-His').'-'.substr($pid,0,6).'.'.$ext;
      $folder = ($type==='id'?'ids':($type==='ins'?'insurance':'notes'));
      $abs=$DIRS[$folder].'/'.$final; if(!move_uploaded_file($f['tmp_name'],$abs)) jerr('Failed to save',500);
      $rel='/uploads/'.$folder.'/'.$final;

      if ($type==='id'){
        $pdo->prepare("UPDATE patients SET id_card_path=?, id_card_mime=?, updated_at=NOW() WHERE id=? AND user_id=?")
            ->execute([$rel,$mime,$pid,$userId]);
      } elseif ($type==='ins'){
        $pdo->prepare("UPDATE patients SET ins_card_path=?, ins_card_mime=?, updated_at=NOW() WHERE id=? AND user_id=?")
            ->execute([$rel,$mime,$pid,$userId]);
      }
      jok(['path'=>$rel,'name'=>$f['name'],'mime'=>$mime]);
    }
  }

  /* ---- delete patient + their orders + unlink order files ---- */
  if ($action==='patient.delete'){
    $pid=(string)($_POST['id']??''); 
    if($pid==='') jerr('Missing patient id');

    try{
      $pdo->beginTransaction();
      $s=$pdo->prepare("SELECT id FROM patients WHERE id=? AND user_id=? FOR UPDATE");
      $s->execute([$pid,$userId]);
      if(!$s->fetchColumn()){ 
        $pdo->rollBack(); 
        jerr('Patient not found',404); 
      }

      $files=[];
      $os=$pdo->prepare("SELECT rx_note_path FROM orders WHERE patient_id=? AND user_id=?");
      $os->execute([$pid,$userId]);
      while($o=$os->fetch(PDO::FETCH_ASSOC)){
        $p=$o['rx_note_path']??null;
        if ($p && is_string($p) && strpos($p,'/uploads/')===0) $files[]=$p;
      }

      $pdo->prepare("DELETE FROM orders   WHERE patient_id=? AND user_id=?")->execute([$pid,$userId]);
      $pdo->prepare("DELETE FROM patients WHERE id=? AND user_id=?")->execute([$pid,$userId]);

      $pdo->commit();

      foreach ($files as $rel){
        $full = realpath(__DIR__ . '/..' . $rel);
        if ($full && is_file($full) && strpos($full, $UPLOAD_ROOT)===0){
          @unlink($full);
        }
      }

      jok();
    } catch (Throwable $e){
      if ($pdo->inTransaction()) $pdo->rollBack();
      jerr('Failed to delete patient',500);
    }
  }

  if ($action==='products'){
    $rows=$pdo->query("SELECT id,name,size,size AS uom,price_admin AS price,cpt_code AS hcpcs FROM products WHERE active=TRUE ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    jok(['rows'=>$rows]);
  }

  /* ORDER.CREATE — enforce NPI, patient cards + AOB, clinical completeness; only visit note uploads per order */
  if ($action==='order.create'){
    try {
      $pdo->beginTransaction();

      // Must have provider NPI
      $u=$pdo->prepare("SELECT npi,sign_name,sign_title FROM users WHERE id=? FOR UPDATE");
      $u->execute([$userId]); $ud=$u->fetch(PDO::FETCH_ASSOC);
      if(!$ud || empty($ud['npi'])){ $pdo->rollBack(); jerr('Provider NPI is required. Please add your NPI in your profile.'); }

      $pid=(string)($_POST['patient_id']??''); if($pid==='') jerr('patient_id is required');
      $own=$pdo->prepare("SELECT id,first_name,last_name,address,city,state,zip,phone,email,
                                 id_card_path,ins_card_path,aob_path
                          FROM patients WHERE id=? AND user_id=? FOR UPDATE");
      $own->execute([$pid,$userId]); $p=$own->fetch(PDO::FETCH_ASSOC);
      if(!$p){ $pdo->rollBack(); jerr('Patient not found',404); }

      $payment_type=$_POST['payment_type'] ?? 'insurance';

      $product_id=(int)($_POST['product_id']??0);
      $pr=$pdo->prepare("SELECT id,name,price_admin,cpt_code FROM products WHERE id=? AND active=1");
      $pr->execute([$product_id]); $prod=$pr->fetch(PDO::FETCH_ASSOC);
      if(!$prod){ $pdo->rollBack(); jerr('Product not found',404); }

      // REQUIRED: Patient ID and Insurance Card must always be on file
      if(empty($p['id_card_path']) || empty($p['ins_card_path'])){
        $pdo->rollBack();
        jerr('Patient ID and Insurance Card must be on file before creating an order. Please upload these documents first.');
      }

      // Insurance orders also require AOB
      if($payment_type==='insurance'){
        if(empty($p['aob_path'])){ $pdo->rollBack(); jerr('Assignment of Benefits (AOB) must be signed for insurance orders.'); }
      }

      $delivery_to=$_POST['delivery_to'] ?? 'patient';
      $delivery_mode=($delivery_to==='office')?'office':'patient';

      $sign_name=trim((string)($_POST['sign_name']??'')); if($sign_name===''){ $pdo->rollBack(); jerr('E-signature name is required'); }
      $sign_title=trim((string)($_POST['sign_title']??'')); 
      $ack=(int)($_POST['ack_sig']??0); if(!$ack){ $pdo->rollBack(); jerr('Please acknowledge the e-signature statement.'); }

      // Ensure non-null shipping fields
      $ship_name  = $_POST['shipping_name']    ?? '';
      $ship_phone = $_POST['shipping_phone']   ?? '';
      $ship_addr  = $_POST['shipping_address'] ?? '';
      $ship_city  = $_POST['shipping_city']    ?? '';
      $ship_state = $_POST['shipping_state']   ?? '';
      $ship_zip   = $_POST['shipping_zip']     ?? '';

      if ($delivery_mode==='patient') {
        $ship_name  = $ship_name  ?: ($p['first_name'].' '.$p['last_name']);
        $ship_phone = $ship_phone ?: ($p['phone']   ?? '');
        $ship_addr  = $ship_addr  ?: ($p['address'] ?? '');
        $ship_city  = $ship_city  ?: ($p['city']    ?? '');
        $ship_state = $ship_state ?: ($p['state']   ?? '');
        $ship_zip   = $ship_zip   ?: ($p['zip']     ?? '');
      }

      // ---------- Clinical completeness ----------
      // Wounds data (multiple wounds support)
      $wounds_json = trim((string)($_POST['wounds_data'] ?? ''));
      if ($wounds_json === '') { $pdo->rollBack(); jerr('Wounds data is required.'); }

      $wounds_data = json_decode($wounds_json, true);
      if (!is_array($wounds_data) || count($wounds_data) === 0) {
        $pdo->rollBack(); jerr('At least one wound is required.');
      }

      // Validate wounds data
      foreach ($wounds_data as $idx => $wound) {
        if (empty($wound['location'])) {
          $pdo->rollBack(); jerr("Wound #" . ($idx + 1) . ": Location is required.");
        }
        if (empty($wound['length_cm']) || empty($wound['width_cm'])) {
          $pdo->rollBack(); jerr("Wound #" . ($idx + 1) . ": Length and width are required.");
        }
        if (empty($wound['icd10_primary'])) {
          $pdo->rollBack(); jerr("Wound #" . ($idx + 1) . ": Primary ICD-10 is required.");
        }
      }

      // For backward compatibility, extract first wound data for legacy columns
      $first_wound = $wounds_data[0];
      $icd10_primary = $first_wound['icd10_primary'] ?? '';
      $icd10_secondary = $first_wound['icd10_secondary'] ?? '';
      $wlen = $first_wound['length_cm'] ?? null;
      $wwid = $first_wound['width_cm'] ?? null;
      $wdep = $first_wound['depth_cm'] ?? null;
      $wtype = $first_wound['type'] ?? '';
      $wstage = $first_wound['stage'] ?? '';
      $wound_location = $first_wound['location'] ?? '';
      $wound_laterality = $first_wound['laterality'] ?? '';
      $wound_notes = $first_wound['notes'] ?? '';

      $last_eval = $_POST['last_eval_date'] ?? null; if(!$last_eval){ $pdo->rollBack(); jerr('Date of last evaluation is required.'); }

      $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');

      // Validate: start_date must be within 30 days of last_eval_date
      $last_eval_ts = strtotime($last_eval);
      $start_date_ts = strtotime($start_date);
      $days_diff = ($start_date_ts - $last_eval_ts) / 86400; // Convert seconds to days

      if ($days_diff > 30) {
        $pdo->rollBack();
        jerr('Order start date must be within 30 days of last evaluation. Last eval: ' . date('m/d/Y', $last_eval_ts) . ', Start date: ' . date('m/d/Y', $start_date_ts) . ' (' . round($days_diff) . ' days apart).');
      }

      if ($days_diff < 0) {
        $pdo->rollBack();
        jerr('Order start date cannot be before the last evaluation date.');
      }
      $freq_per_week = max(0,(int)($_POST['frequency_per_week']??0));
      $qty_per_change = max(1,(int)($_POST['qty_per_change']??1));
      $duration_days = max(1,(int)($_POST['duration_days']??30));
      $refills_allowed = max(0,(int)($_POST['refills_allowed']??0));
      $additional_instructions = trim((string)($_POST['additional_instructions']??''));
      $secondary_dressing = trim((string)($_POST['secondary_dressing']??''));
      if($freq_per_week<=0){ $pdo->rollBack(); jerr('Frequency per week is required.'); }

      $oid=bin2hex(random_bytes(16));
      $ins=$pdo->prepare("INSERT INTO orders
        (id,patient_id,user_id,product,product_id,product_price,status,shipments_remaining,delivery_mode,payment_type,
         wound_location,wound_laterality,wound_notes,
         shipping_name,shipping_phone,shipping_address,shipping_city,shipping_state,shipping_zip,
         sign_name,sign_title,signed_at,created_at,updated_at,
         icd10_primary,icd10_secondary,wound_length_cm,wound_width_cm,wound_depth_cm,
         wound_type,wound_stage,last_eval_date,start_date,frequency_per_week,qty_per_change,duration_days,refills_allowed,additional_instructions,secondary_dressing,
         wounds_data,
         cpt)
        VALUES (?,?,?,?,?,?,?,?,?,?,
                ?,?,?,
                ?,?,?,?,?,?,
                ?,?,NOW(),NOW(),NOW(),
                ?,?,?,?,?,
                ?,?,?,?,?,?,?,?,?,?,
                ?::jsonb,
                ?)");
      $ins->execute([
        $oid,$pid,$userId,$prod['name'],$prod['id'],$prod['price_admin'],'submitted',0,$delivery_mode,$payment_type, // shipments_remaining=0
        $wound_location,$wound_laterality,$wound_notes,
        (string)$ship_name,(string)$ship_phone,(string)$ship_addr,(string)$ship_city,(string)$ship_state,(string)$ship_zip,
        $sign_name,$sign_title,
        $icd10_primary,$icd10_secondary,$wlen,$wwid,$wdep,
        $wtype,$wstage,$last_eval,$start_date,$freq_per_week,$qty_per_change,$duration_days,$refills_allowed,$additional_instructions,$secondary_dressing,
        $wounds_json,
        $prod['cpt_code'] ?? null
      ]);

      // Visit note (optional)
      $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/heic'=>'heic','text/plain'=>'txt'];
      $fi=new finfo(FILEINFO_MIME_TYPE);
      if(!empty($_FILES['file_rx_note']) && $_FILES['file_rx_note']['error']===UPLOAD_ERR_OK){
        $f=$_FILES['file_rx_note']; if($f['size']>25*1024*1024) jerr('File too large (max 25MB)');
        $mime=$fi->file($f['tmp_name']) ?: 'application/octet-stream';
        if(!isset($allowed[$mime])) jerr('Unsupported file type'); $ext=$allowed[$mime];
        $final=slug(pathinfo($f['name'],PATHINFO_FILENAME)).'-'.date('Ymd-His').'-'.substr($oid,0,6).'.'.$ext;
        $abs=$DIRS['notes'].'/'.$final; if(!@move_uploaded_file($f['tmp_name'],$abs)) jerr('Failed to save file',500);
        $rel='/uploads/notes/'.$final;
        $pdo->prepare("UPDATE orders SET rx_note_name=?, rx_note_mime=?, rx_note_path=?, updated_at=NOW() WHERE id=? AND user_id=?")
            ->execute([$f['name'],$mime,$rel,$oid,$userId]);
      } else {
        $notes_text=trim((string)($_POST['notes_text']??'')); 
        if($notes_text!==''){
          $safe='clinical-note-'.date('Ymd-His').'-'.substr($oid,0,6).'.txt';
          file_put_contents($DIRS['notes'].'/'.$safe, $notes_text);
          $pdo->prepare("UPDATE orders SET rx_note_name=?,rx_note_mime=?,rx_note_path=?,updated_at=NOW() WHERE id=? AND user_id=?")
              ->execute(['clinical-note.txt','text/plain','/uploads/notes/'.$safe,$oid,$userId]);
        }
      }

      // snapshot provider signature
      $pdo->prepare("UPDATE users SET sign_name=?,sign_title=?,sign_date=CURDATE(),updated_at=NOW() WHERE id=?")
          ->execute([$sign_name,$sign_title,$userId]);

      $pdo->commit();
      jok(['order_id'=>$oid,'status'=>'submitted']);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      jerr('Order create failed: '.$e->getMessage(), 500);
    }
  }

  // Require reason
  if ($action==='order.stop'){
    $oid=(string)($_POST['order_id']??''); $reason=trim((string)($_POST['reason']??'')); if($oid==='') jerr('order_id required');
    if($reason==='') jerr('Stop reason is required');
    $note="\n[Order stopped ".date('Y-m-d H:i')." — Reason: ".$reason."]";
    $pdo->prepare("UPDATE orders SET status='stopped',expires_at=NOW(),wound_notes=CONCAT(COALESCE(wound_notes,''),?),updated_at=NOW() WHERE id=? AND user_id=?")
        ->execute([$note,$oid,$userId]);
    jok();
  }

  if ($action==='order.reorder'){
    $oid=(string)($_POST['order_id']??''); $notes_text=trim((string)($_POST['notes_text']??'')); if($oid==='') jerr('order_id required');
    if($notes_text==='') jerr('A new clinical note is required to restart');
    $s=$pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=?"); $s->execute([$oid,$userId]); $o=$s->fetch(PDO::FETCH_ASSOC);
    if(!$o) jerr('Order not found',404);
    $new=bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO orders
      (id,patient_id,user_id,product,product_id,product_price,status,shipments_remaining,delivery_mode,payment_type,
       wound_location,wound_laterality,wound_notes,shipping_name,shipping_phone,shipping_address,shipping_city,shipping_state,shipping_zip,
       sign_name,sign_title,signed_at,created_at,updated_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
      ->execute([$new,$o['patient_id'],$userId,$o['product'],$o['product_id'],$o['product_price'],'submitted',$o['shipments_remaining'],$o['delivery_mode'],$o['payment_type'],
        $o['wound_location'],$o['wound_laterality'],$o['wound_notes'],$o['shipping_name'],$o['shipping_phone'],$o['shipping_address'],$o['shipping_city'],$o['shipping_state'],$o['shipping_zip'],
        $o['sign_name'],$o['sign_title'],date('Y-m-d H:i:s')]);

    $safe='clinical-note-'.date('Ymd-His').'-'.substr($new,0,6).'.txt';
    file_put_contents($DIRS['notes'].'/'.$safe, $notes_text);
    $pdo->prepare("UPDATE orders SET rx_note_name=?,rx_note_mime=?,rx_note_path=?,updated_at=NOW() WHERE id=? AND user_id=?")
        ->execute(['clinical-note.txt','text/plain','/uploads/notes/'.$safe,$new,$userId]);

    jok(['order_id'=>$new,'status'=>'submitted']);
  }

  // Orders listing aligned to schema
  if ($action==='orders'){
    $q=trim((string)($_GET['q']??'')); 
    $status=trim((string)($_GET['status']??'')); 
    $where="user_id=?"; $args=[ $userId ];
    if($q!==''){ $where.=" AND (product LIKE ? OR shipping_name LIKE ?)"; $like="%$q%"; array_push($args,$like,$like); }
    if($status!==''){ $where.=" AND status=?"; $args[]=$status; }
    $st=$pdo->prepare("SELECT id,patient_id,product,shipments_remaining,status,delivery_mode,created_at,expires_at
                       FROM orders WHERE $where ORDER BY created_at DESC LIMIT 300");
    $st->execute($args); jok(['rows'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
  }

  if ($action==='file.dl'){
    $oid=(string)($_GET['order_id']??''); if($oid==='') { http_response_code(404); exit; }
    $s=$pdo->prepare("SELECT user_id,rx_note_name,rx_note_mime,rx_note_path FROM orders WHERE id=?"); $s->execute([$oid]); $o=$s->fetch(PDO::FETCH_ASSOC);
    if(!$o||$o['user_id']!==$userId){ http_response_code(403); exit; }
    if(!$o['rx_note_path']){ http_response_code(404); exit; }
    $full=realpath(__DIR__.'/..'.$o['rx_note_path']); if(!$full||!is_readable($full)||strpos($full,$UPLOAD_ROOT)!==0){ http_response_code(404); exit; }
    header('Content-Type: '.($o['rx_note_mime']?:'application/octet-stream')); header('Content-Disposition: inline; filename="'.($o['rx_note_name']?:basename($full)).'"'); header('Content-Length: '.filesize($full)); readfile($full); exit;
  }

  if ($action==='user.change_password'){
    $cur=(string)($_POST['current']??''); $new=(string)($_POST['new']??''); $confirm=(string)($_POST['confirm']??'');
    if($new===''||$confirm==='') jerr('New password is required');
    if($new!==$confirm) jerr('Passwords do not match');
    $s=$pdo->prepare("SELECT password_hash FROM users WHERE id=?"); $s->execute([$userId]); $row=$s->fetch(PDO::FETCH_ASSOC);
    if(!$row||empty($row['password_hash'])) jerr('Account not configured for password change',400);
    if(!password_verify($cur,$row['password_hash'])) jerr('Current password is incorrect',403);
    $hash=password_hash($new,PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?")->execute([$hash,$userId]);
    jok();
  }

  jerr('Unknown action',404);
}

/* ============================================================
   Router
   ============================================================ */
$page = $_GET['page'] ?? 'dashboard';
if ($page==='logout'){
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
  }
  session_destroy();
  header('Location: /login');
  exit;
}
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>CollagenDirect — <?php echo ucfirst($page); ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  /* Design Tokens - Healthcare UI */
  :root {
    --brand: #4DB8A8;
    --brand-dark: #3A9688;
    --brand-light: #E0F5F2;
    --ink: #1F2937;
    --ink-light: #6B7280;
    --muted: #9CA3AF;
    --bg-gray: #F9FAFB;
    --bg-sidebar: #F6F6F6;
    --border: #E5E7EB;
    --border-sidebar: #E8E8E9;
    --ring: rgba(77, 184, 168, 0.2);
    --radius: 0.5rem;
    --radius-lg: 0.75rem;
    --success: #10B981;
    --success-light: #D1FAE5;
    --warning: #F59E0B;
    --warning-light: #FEF3C7;
    --error: #EF4444;
    --error-light: #FEE2E2;
    --info: #3B82F6;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  }

  html, body {
    font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    color: var(--ink);
    -webkit-font-smoothing: antialiased;
    background: #ffffff;
  }

  /* Card Component */
  .card {
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.15s ease;
  }
  .card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  }

  /* Button Component - shadcn style */
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: var(--radius);
    padding: 0.4375rem 0.875rem;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.15s ease;
    border: 1px solid var(--border);
    background: #ffffff;
    color: var(--ink);
    cursor: pointer;
  }
  .btn:hover {
    background: #f9fafb;
    border-color: var(--muted);
  }
  .btn:focus {
    outline: none;
    box-shadow: 0 0 0 3px var(--ring);
  }
  .btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  /* Button Variants */
  .btn-primary {
    background: var(--brand);
    color: #ffffff;
    border-color: var(--brand);
  }
  .btn-primary:hover {
    background: var(--brand-dark);
    border-color: var(--brand-dark);
  }

  .btn-success {
    background: var(--success);
    color: #ffffff;
    border-color: var(--success);
  }

  .btn-outline {
    background: transparent;
    border-color: var(--border);
  }

  .btn-ghost {
    border-color: transparent;
    background: transparent;
  }
  .btn-ghost:hover {
    background: #f3f4f6;
  }

  /* Badge/Pill Component - Healthcare Design */
  .badge {
    display: inline-flex;
    align-items: center;
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
    border-radius: 0.375rem;
    border: none;
  }

  /* Status badges */
  .badge-approved,
  .badge-paid {
    background: var(--success-light);
    color: var(--success);
  }

  .badge-pending {
    background: var(--warning-light);
    color: #B45309;
  }

  .badge-reject,
  .badge-unpaid,
  .badge-rejected {
    background: var(--error-light);
    color: var(--error);
  }

  .badge-success {
    background: var(--success-light);
    color: var(--success);
  }

  .badge-warning {
    background: var(--warning-light);
    color: #B45309;
  }

  .badge-error {
    background: var(--error-light);
    color: var(--error);
  }

  .badge-info {
    background: #DBEAFE;
    color: #1E40AF;
  }

  /* Legacy pill support */
  .pill { @extend .badge; @extend .badge-info; }
  .pill--active { @extend .badge-success; }
  .pill--pending { @extend .badge-warning; }
  .pill--stopped { @extend .badge-error; }

  /* Input Component */
  input, select, textarea {
    border: 1px solid var(--border) !important;
    border-radius: var(--radius) !important;
    padding: 0.625rem 0.875rem !important;
    font-size: 0.875rem !important;
    transition: all 0.15s ease;
    line-height: 1.5;
    min-height: 2.5rem;
  }

  /* Ensure date inputs have proper padding */
  input[type="date"],
  input[type="datetime-local"],
  input[type="time"] {
    padding: 0.5rem 0.75rem !important;
  }

  /* Select specific styling */
  select {
    padding-right: 2.5rem !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%231B1B1B' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    appearance: none;
  }

  /* Textarea specific styling */
  textarea {
    resize: vertical;
    min-height: 5rem;
  }

  input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--brand) !important;
    box-shadow: 0 0 0 3px var(--ring) !important;
  }
  input:disabled, select:disabled, textarea:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f9fafb !important;
  }

  /* Label Component */
  label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--ink);
    margin-bottom: 0.5rem;
    display: block;
  }

  /* Form field wrapper */
  .form-field {
    margin-bottom: 1.5rem;
  }
  .form-field > label {
    margin-bottom: 0.5rem;
  }
  .form-field input,
  .form-field select,
  .form-field textarea {
    width: 100%;
  }

  /* Inline inputs (search bars, etc) - don't expand to 100% */
  .flex input,
  .flex select {
    width: auto;
    flex-shrink: 0;
  }

  /* Grid form layouts */
  .form-grid {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: 1fr;
  }
  @media (min-width: 640px) {
    .form-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }
  .form-grid-full {
    grid-column: 1 / -1;
  }

  /* Form field spacing for grid items */
  .grid > div {
    display: flex;
    flex-direction: column;
  }
  .grid > div > label {
    margin-bottom: 0.5rem;
  }
  .grid > div > input,
  .grid > div > select,
  .grid > div > textarea {
    margin-top: auto;
  }

  /* Dialog/Modal */
  dialog {
    border: none;
    border-radius: calc(var(--radius) * 1.5);
    padding: 0;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  }
  dialog::backdrop {
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(4px);
  }

  /* Navigation */
  .sidenav a {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    color: var(--muted);
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.15s ease;
    border: 1px solid transparent;
  }
  .sidenav a:hover {
    color: var(--ink);
    background: #f9fafb;
  }
  .sidenav a.active {
    background: var(--brand-light);
    color: var(--brand-dark);
    border-color: #a7f3d0;
  }

  /* Table improvements */
  table {
    border-collapse: separate;
    border-spacing: 0;
  }
  thead th {
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    padding: 0.75rem 0.5rem;
  }
  tbody td {
    padding: 1rem 0.5rem;
  }
  tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background 0.15s ease;
  }
  tbody tr:hover {
    background: #f9fafb;
  }
  /* Sidebar Layout */
  .app-container {
    display: flex;
    height: 100vh;
    overflow: hidden;
  }

  .sidebar {
    width: 240px;
    background: var(--bg-sidebar);
    border-right: 1px solid var(--border-sidebar);
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    overflow-y: auto;
    transition: width 0.3s ease, transform 0.3s ease;
    z-index: 100;
  }

  .sidebar.collapsed {
    width: 72px;
  }

  .sidebar.collapsed .sidebar-user > div:last-child,
  .sidebar.collapsed .sidebar-nav a span,
  .sidebar.collapsed .sidebar a[href="/admin/"] span {
    display: none;
  }

  .sidebar.collapsed .sidebar-user {
    justify-content: center;
    padding: 0.5rem;
  }

  .sidebar.collapsed .sidebar-nav a {
    justify-content: center;
    padding: 0.75rem;
  }

  .sidebar.collapsed .sidebar a[href="/admin/"] {
    justify-content: center;
    padding: 0.75rem !important;
  }

  .sidebar-header {
    max-height: 60px;
    height: 60px;
    padding: 0.625rem 1rem;
    border-bottom: 1px solid var(--border-sidebar);
    display: flex;
    align-items: center;
  }

  .sidebar-user {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.5rem 0.75rem;
    border-radius: var(--radius);
    transition: background 0.2s;
  }

  .sidebar-user:hover {
    background: rgba(0,0,0,0.04);
  }

  .sidebar-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--brand);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.8125rem;
  }

  .sidebar-nav {
    padding: 1rem;
    flex: 1;
  }

  .sidebar-nav a {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    color: #5A5B60;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.2s;
    margin-bottom: 0.25rem;
    border: 1px solid transparent;
  }

  .sidebar-nav a:hover {
    background: var(--brand-light);
    color: var(--brand-dark);
    border-color: var(--border-sidebar);
  }

  .sidebar-nav a.active {
    background: #ffffff;
    color: #1B1B1B;
    font-weight: 600;
    border: 1px solid var(--border-sidebar);
  }

  .sidebar-nav-icon {
    width: 20px;
    height: 20px;
  }

  /* Admin link at bottom of sidebar */
  .sidebar a[href="/admin/"]:hover {
    background: var(--brand-light) !important;
    color: var(--brand-dark) !important;
    border-color: var(--border-sidebar) !important;
  }

  .main-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    margin-left: 240px;
    height: 100vh;
    transition: margin-left 0.3s ease, width 0.3s ease;
    width: calc(100% - 240px);
    max-width: calc(100% - 240px);
  }

  .main-content.sidebar-collapsed {
    margin-left: 72px;
    width: calc(100% - 72px);
    max-width: calc(100% - 72px);
  }

  .top-bar {
    height: 60px;
    max-height: 60px;
    background: white;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 2rem;
    position: sticky;
    top: 0;
    z-index: 10;
    flex-shrink: 0;
  }

  .top-bar-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--ink);
  }

  .top-bar-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .icon-btn {
    width: 36px;
    height: 36px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
    cursor: pointer;
  }

  .icon-btn:hover {
    background: var(--bg-gray);
  }

  .icon-btn.active {
    background: var(--brand-light);
    color: var(--brand-dark);
  }

  /* Dropdown Menu Styles */
  .dropdown {
    position: relative;
  }

  .dropdown-menu {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    min-width: 320px;
    max-height: 480px;
    overflow-y: auto;
    z-index: 50;
    display: none;
  }

  .dropdown-menu.show {
    display: block;
  }

  .dropdown-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .dropdown-header h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--ink);
  }

  .dropdown-body {
    padding: 0.5rem 0;
  }

  .dropdown-item {
    padding: 0.75rem 1.25rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
    transition: background 0.15s;
    border-bottom: 1px solid var(--bg-gray);
  }

  .dropdown-item:hover {
    background: var(--bg-gray);
  }

  .dropdown-item:last-child {
    border-bottom: none;
  }

  .dropdown-footer {
    padding: 0.75rem 1.25rem;
    border-top: 1px solid var(--border);
    text-align: center;
  }

  .dropdown-footer button {
    color: var(--brand);
    font-weight: 500;
    font-size: 0.875rem;
  }

  /* Notification Badge */
  .notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: var(--error);
    color: white;
    border-radius: 9999px;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.625rem;
    font-weight: 600;
  }

  .content-area {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    overflow-x: hidden;
  }

  /* ========== MOBILE RESPONSIVE & TOUCH OPTIMIZATIONS ========== */

  /* Mobile hamburger menu */
  .mobile-menu-btn {
    display: none;
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 1000;
    width: 44px;
    height: 44px;
    background: var(--brand);
    color: white;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  }

  .mobile-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 998;
  }

  /* Tablet and below (< 768px) */
  @media (max-width: 767px) {
    .mobile-menu-btn {
      display: flex;
    }

    .mobile-overlay.mobile-open {
      display: block;
    }

    /* Sidebar becomes slide-out menu */
    .sidebar {
      position: fixed;
      left: -240px;
      transition: left 0.3s ease;
      z-index: 999;
      box-shadow: 2px 0 8px rgba(0,0,0,0.15);
    }

    .sidebar.mobile-open {
      left: 0;
    }

    /* Main content takes full width */
    .main-content {
      margin-left: 0;
      width: 100%;
    }

    /* Top bar adjustments */
    .top-bar {
      padding: 0 1rem 0 4rem; /* Space for hamburger button */
      height: 56px;
    }

    .top-bar-title {
      font-size: 1rem;
    }

    /* Hide "New Order" text on mobile, show only icon */
    .new-order-text {
      display: none;
    }

    #global-new-order-btn {
      padding: 0.75rem;
      min-width: 44px;
      justify-content: center;
    }

    /* Content area padding */
    .content-area {
      padding: 1rem;
    }

    /* Tables become horizontally scrollable */
    table {
      display: block;
      overflow-x: auto;
      white-space: nowrap;
      -webkit-overflow-scrolling: touch;
    }

    /* Make cards stack vertically */
    .grid {
      grid-template-columns: 1fr !important;
    }

    /* Touch-friendly buttons - minimum 44x44px */
    .btn, button {
      min-height: 44px;
      padding: 0.75rem 1rem;
      font-size: 0.875rem;
    }

    /* Form fields stack vertically */
    .form-grid {
      grid-template-columns: 1fr !important;
    }

    /* Patient detail page mobile adjustments */
    #patient-detail-container {
      grid-template-columns: 1fr !important;
    }

    #patient-detail-container .lg\\:col-span-2 {
      grid-column: span 1;
    }

    /* Breadcrumb and actions stack on mobile */
    .flex.items-center.justify-between.mb-4 {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.75rem;
    }

    .flex.items-center.justify-between.mb-4 > div:last-child {
      width: 100%;
    }

    .flex.items-center.justify-between.mb-4 .btn {
      width: 100%;
    }

    /* Dashboard metrics stack */
    .metrics-grid {
      grid-template-columns: 1fr !important;
    }

    /* Hide less important columns in tables */
    .table-hide-mobile {
      display: none;
    }

    /* Accordion/expandable rows work better on mobile */
    .acc-row {
      overflow-x: auto;
    }

    /* Dropdown menus */
    .dropdown {
      position: fixed !important;
      left: 1rem !important;
      right: 1rem !important;
      width: auto !important;
    }

    /* Cards have less padding on mobile */
    .card {
      padding: 1rem !important;
    }

    /* Sidebar nav items more touch-friendly */
    .sidebar-nav a {
      padding: 1rem;
      font-size: 1rem;
    }

    /* Profile dropdown adjustments */
    .sidebar-user {
      padding: 1rem;
    }

    /* Action buttons in cards */
    .card .flex.gap-2 {
      flex-direction: column;
    }

    .card .flex.gap-2 .btn {
      width: 100%;
    }

    /* Patient cards in Orders/History sections */
    .space-y-2 > div {
      flex-direction: column !important;
      gap: 0.5rem;
    }

    .space-y-2 > div .btn-sm {
      width: 100%;
    }

    /* Top-level CRUD actions on mobile */
    .flex.gap-2:has(#patient-detail-new-order-btn) {
      width: 100%;
      flex-direction: row;
    }

    #patient-detail-new-order-btn {
      flex: 1;
    }
  }

  /* Small mobile (< 480px) */
  @media (max-width: 479px) {
    .top-bar {
      padding: 0 0.5rem 0 3.5rem;
    }

    .content-area {
      padding: 0.75rem;
    }

    .btn, button {
      font-size: 0.8125rem;
      padding: 0.625rem 0.875rem;
    }

    /* Even more compact on very small screens */
    .card {
      padding: 0.75rem !important;
      border-radius: 8px;
    }

    h1, .top-bar-title {
      font-size: 0.875rem;
    }

    h2 {
      font-size: 1rem;
    }

    /* Hide avatar on very small screens */
    .sidebar-avatar {
      width: 32px;
      height: 32px;
      font-size: 0.75rem;
    }

    /* Stack breadcrumb elements */
    .text-sm.text-slate-600 {
      font-size: 0.75rem;
    }
  }

  /* Touch enhancements for all mobile devices */
  @media (hover: none) and (pointer: coarse) {
    /* Larger tap targets */
    a, button, .btn, input[type="checkbox"], input[type="radio"] {
      min-height: 44px;
      min-width: 44px;
    }

    /* Remove hover states, use active states instead */
    .btn:hover, button:hover, a:hover {
      background: inherit;
    }

    .btn:active, button:active {
      transform: scale(0.98);
      opacity: 0.8;
    }

    /* Better focus indicators for touch */
    input:focus, textarea:focus, select:focus {
      outline: 2px solid var(--brand);
      outline-offset: 2px;
    }

    /* Smooth scrolling on touch devices */
    * {
      -webkit-overflow-scrolling: touch;
    }

    /* Prevent text selection on buttons */
    .btn, button {
      -webkit-user-select: none;
      user-select: none;
      -webkit-tap-highlight-color: transparent;
    }
  }
</style>
</head>
<body>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" id="mobile-menu-btn" aria-label="Toggle menu">
  <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
  </svg>
</button>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobile-overlay"></div>

<!-- Profile Dropdown Menu (triggered from sidebar) -->
<div class="dropdown-menu" id="profile-menu" style="min-width: auto; width: max-content; position: fixed; top: auto; left: auto; z-index: 1001;">
  <div class="dropdown-header">
    <div>
      <div style="font-weight: 600; color: var(--ink);"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
      <div style="font-size: 0.75rem; color: var(--muted);"><?php echo htmlspecialchars($user['email']); ?></div>
    </div>
  </div>
  <div class="dropdown-body">
    <a href="?page=profile" class="dropdown-item">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
      <div>
        <div style="font-weight: 500; color: var(--ink);">Profile & Settings</div>
        <div style="font-size: 0.75rem; color: var(--muted);">Manage your account</div>
      </div>
    </a>
    <a href="#" class="dropdown-item">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      <div>
        <div style="font-weight: 500; color: var(--ink);">Information</div>
        <div style="font-size: 0.75rem; color: var(--muted);">About this app</div>
      </div>
    </a>
    <a href="#" class="dropdown-item">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
      <div>
        <div style="font-weight: 500; color: var(--ink);">Notification</div>
        <div style="font-size: 0.75rem; color: var(--muted);">Notification settings</div>
      </div>
    </a>
    <a href="?page=logout" class="dropdown-item" style="border-top: 1px solid var(--border); margin-top: 0.5rem; padding-top: 0.75rem; color: var(--error);">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
      <div style="font-weight: 500;">Log out</div>
    </a>
  </div>
</div>

<div class="app-container">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-user" id="sidebar-profile-trigger" style="cursor: pointer; transition: background 0.2s;">
        <div class="sidebar-avatar">
          <?php echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1)); ?>
        </div>
        <div style="flex:1; min-width:0; display:flex; align-items:center; gap:0.5rem;">
          <div style="font-weight:600; font-size:0.875rem; color:var(--ink); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1;">
            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
          </div>
          <svg style="width:16px; height:16px; color:#5A5B60; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
          </svg>
        </div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a class="<?php echo $page==='dashboard'?'active':''; ?>" href="?page=dashboard">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
        <span>Dashboard</span>
      </a>
      <a class="<?php echo $page==='patients'?'active':''; ?>" href="?page=patients">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
        <span>Patient</span>
      </a>
      <a class="<?php echo $page==='orders'?'active':''; ?>" href="?page=orders">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
        <span>Orders</span>
      </a>
      <a class="<?php echo $page==='messages'?'active':''; ?>" href="?page=messages">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
        <span>Messages</span>
      </a>
      <?php if (!$isReferralOnly): ?>
      <a class="<?php echo $page==='billing'?'active':''; ?>" href="?page=billing">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
        <span>Billing</span>
      </a>
      <a class="<?php echo $page==='transactions'?'active':''; ?>" href="?page=transactions">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
        <span>Transactions</span>
      </a>
      <?php endif; ?>
      <a class="<?php echo $page==='profile'?'active':''; ?>" href="?page=profile">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
        <span>Profile</span>
      </a>
    </nav>

    <!-- Admin Functions Section -->
    <?php if ($isPracticeAdmin): ?>
    <div style="margin-top: auto; padding: 1rem; border-top: 1px solid var(--border-sidebar);">
      <a href="/admin/" style="display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; border-radius:8px; color:#5A5B60; font-weight:500; font-size:0.875rem; transition:all 0.2s; border:1px solid transparent;">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        <span>Practice Admin</span>
      </a>
    </div>
    <?php endif; ?>
  </aside>

  <!-- Main Content -->
  <div class="main-content">
    <div class="top-bar">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <button class="icon-btn" id="sidebar-toggle-btn" title="Toggle sidebar">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
        </button>
        <h1 class="top-bar-title"><?php echo ucfirst($page); ?></h1>
      </div>
      <div class="top-bar-actions">
        <!-- Search Button -->
        <button class="icon-btn" id="search-btn">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </button>

        <!-- Notifications Dropdown -->
        <div class="dropdown">
          <button class="icon-btn" id="notifications-btn" style="position: relative;">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            <span class="notification-badge" id="notification-count">4</span>
          </button>
          <div class="dropdown-menu" id="notifications-menu">
            <div class="dropdown-header">
              <h3>Notifications <span style="color: var(--muted); font-weight: 400;">4</span></h3>
              <button class="icon-btn" style="width: 32px; height: 32px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
              </button>
            </div>
            <div style="padding: 0.5rem 1.25rem; border-bottom: 1px solid var(--border);">
              <div style="display: flex; gap: 1rem; font-size: 0.875rem;">
                <button style="padding: 0.5rem 0; color: var(--brand); border-bottom: 2px solid var(--brand); font-weight: 500;">All</button>
                <button style="padding: 0.5rem 0; color: var(--muted);">Doctor</button>
                <button style="padding: 0.5rem 0; color: var(--muted);">Patient</button>
              </div>
            </div>
            <div class="dropdown-body" id="notifications-list">
              <!-- Notifications will be populated by JS -->
            </div>
            <div class="dropdown-footer">
              <button class="btn btn-ghost">Mark all as read</button>
              <button class="btn btn-primary" style="margin-left: 0.5rem;">View All</button>
            </div>
          </div>
        </div>

        <!-- New Order Button -->
        <button class="btn btn-primary" id="global-new-order-btn" style="display: flex; align-items: center; gap: 0.5rem;">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
          </svg>
          <span class="new-order-text">New Order</span>
        </button>
      </div>
    </div>

    <main class="content-area">
<?php if ($page==='dashboard'): ?>
  <!-- Stat Cards -->
  <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
      <div class="text-sm text-muted mb-2" style="color: var(--ink-light); font-weight: 500;">Total patient</div>
      <div id="m-patients" class="text-3xl font-bold mb-2" style="color: var(--ink);">-</div>
      <div class="flex items-center gap-2 text-sm">
        <span class="flex items-center gap-1" style="color: var(--success); font-weight: 500;">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
          65.92%
        </span>
        <span style="color: var(--muted);">Since last week</span>
      </div>
    </div>

    <div class="card p-5">
      <div class="text-sm text-muted mb-2" style="color: var(--ink-light); font-weight: 500;">Total appointment</div>
      <div id="m-active" class="text-3xl font-bold mb-2" style="color: var(--ink);">-</div>
      <div class="flex items-center gap-2 text-sm">
        <span class="flex items-center gap-1" style="color: var(--error); font-weight: 500;">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
          23.89%
        </span>
        <span style="color: var(--muted);">Since last week</span>
      </div>
    </div>

    <div class="card p-5">
      <div class="text-sm text-muted mb-2" style="color: var(--ink-light); font-weight: 500;">Total income</div>
      <div class="text-3xl font-bold mb-2" style="color: var(--ink);">$12,923.45</div>
      <div class="flex items-center gap-2 text-sm">
        <span class="flex items-center gap-1" style="color: var(--error); font-weight: 500;">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
          12.23%
        </span>
        <span style="color: var(--muted);">Since last week</span>
      </div>
    </div>

    <div class="card p-5">
      <div class="text-sm text-muted mb-2" style="color: var(--ink-light); font-weight: 500;">Total treatment</div>
      <div id="m-pending" class="text-3xl font-bold mb-2" style="color: var(--ink);">-</div>
      <div class="flex items-center gap-2 text-sm">
        <span class="flex items-center gap-1" style="color: var(--success); font-weight: 500;">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
          65.92%
        </span>
        <span style="color: var(--muted);">Since last week</span>
      </div>
    </div>
  </section>

  <!-- Overview Chart Section -->
  <section class="card p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-lg font-semibold" style="color: var(--ink);">Overview</h3>
        <p class="text-sm" style="color: var(--ink-light);">Patients in a month
          <span style="color: var(--success); font-weight: 500;"> ↗ 53.82%</span>
        </p>
      </div>
      <select class="btn btn-outline">
        <option>Yearly</option>
        <option>Monthly</option>
        <option>Weekly</option>
      </select>
    </div>
    <div style="height: 300px; padding: 1rem;">
      <canvas id="patientChart"></canvas>
    </div>
  </section>

  <!-- Patients List -->
  <section class="card p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold" style="color: var(--ink);">Patients list</h3>
      <div class="flex gap-2">
        <button class="btn btn-outline">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
          Filter
        </button>
        <button class="btn btn-outline">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
          Export
        </button>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead style="border-bottom: 1px solid var(--border);">
          <tr class="text-left">
            <th class="py-3 px-2" style="width: 40px;">
              <input type="checkbox" style="width: 16px; height: 16px; cursor: pointer;">
            </th>
            <th class="py-3 px-2" style="color: var(--ink-light); font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Patient name</th>
            <th class="py-3 px-2" style="color: var(--ink-light); font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Patient ID</th>
            <th class="py-3 px-2" style="color: var(--ink-light); font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Age</th>
            <th class="py-3 px-2" style="color: var(--ink-light); font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Date of birth</th>
            <th class="py-3 px-2" style="color: var(--ink-light); font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Gender</th>
            <th class="py-3 px-2" style="color: var(--ink-light); font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Email</th>
            <th class="py-3 px-2" style="color: var(--ink-light); font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Status</th>
            <th class="py-3 px-2" style="width: 40px;"></th>
          </tr>
        </thead>
        <tbody id="patients-tbody"></tbody>
      </table>
    </div>
  </section>

<?php elseif ($page==='patients'): ?>
  <!-- Top-level actions -->
  <div class="flex items-center gap-3 mb-4">
    <h2 class="text-lg font-semibold">Manage Patients</h2>
    <input id="q" class="ml-auto w-full sm:w-96" placeholder="Search name, phone, email, MRN…">
    <button class="btn btn-outline" id="btn-add-patient" type="button">
      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
      Add Patient
    </button>
  </div>

  <section class="card p-5">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Name</th><th class="py-2">DOB</th><th class="py-2">Phone</th><th class="py-2">Email</th>
            <th class="py-2">City/State</th><th class="py-2">Status</th><th class="py-2">Bandage Count</th><th class="py-2">Action</th>
          </tr>
        </thead>
        <tbody id="tb"></tbody>
      </table>
    </div>
  </section>

<?php elseif ($page==='orders'): ?>
  <!-- Top-level actions -->
  <div class="flex items-center gap-3 mb-4">
    <h2 class="text-lg font-semibold">Manage Orders</h2>
    <input id="oq" class="ml-auto w-full sm:w-80" placeholder="Search product or recipient…">
    <select id="of">
        <option value="">All Status</option>
        <option value="submitted">Submitted</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="active">Active</option>
        <option value="shipped">Shipped</option>
        <option value="stopped">Stopped</option>
      </select>
  </div>

  <section class="card p-5">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Created</th><th class="py-2">Product</th><th class="py-2">Status</th><th class="py-2">Bandage Count</th><th class="py-2">Deliver To</th><th class="py-2">Expires</th><th class="py-2">Action</th>
          </tr>
        </thead>
        <tbody id="orders-tb"></tbody>
      </table>
    </div>
  </section>

<?php elseif ($page==='messages'): ?>
  <!-- Top-level actions -->
  <div class="flex items-center gap-3 mb-4">
    <h2 class="text-lg font-semibold">Messages</h2>
    <div class="ml-auto flex gap-2">
      <input id="msg-search" class="w-full sm:w-80" placeholder="Search messages…">
      <button class="btn btn-primary" id="btn-compose" type="button">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        Compose
      </button>
    </div>
  </div>

  <section class="card p-5">
    <div style="display: grid; grid-template-columns: 320px 1fr; gap: 1rem; min-height: 600px;">
      <!-- Message List -->
      <div style="border-right: 1px solid var(--border); overflow-y: auto;">
        <div style="padding: 0.5rem 0;">
          <button style="width: 100%; text-align: left; padding: 0.75rem; border-bottom: 1px solid var(--border); background: #f0fdfa; border-left: 3px solid var(--brand);">
            <div style="display: flex; align-items: start; gap: 0.75rem;">
              <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; flex-shrink: 0;">JS</div>
              <div style="flex: 1; min-width: 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                  <div style="font-weight: 600; font-size: 0.875rem; color: var(--ink);">John Smith</div>
                  <div style="font-size: 0.75rem; color: var(--muted);">2h</div>
                </div>
                <div style="font-size: 0.875rem; color: var(--ink); font-weight: 500; margin-bottom: 0.25rem;">Patient Records Update</div>
                <div style="font-size: 0.75rem; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">I've uploaded the latest lab results...</div>
              </div>
            </div>
          </button>
          <button style="width: 100%; text-align: left; padding: 0.75rem; border-bottom: 1px solid var(--border);">
            <div style="display: flex; align-items: start; gap: 0.75rem;">
              <div style="width: 40px; height: 40px; border-radius: 50%; background: #94a3b8; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; flex-shrink: 0;">MW</div>
              <div style="flex: 1; min-width: 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                  <div style="font-weight: 500; font-size: 0.875rem; color: var(--ink);">Mary Wilson</div>
                  <div style="font-size: 0.75rem; color: var(--muted);">1d</div>
                </div>
                <div style="font-size: 0.875rem; color: var(--muted); margin-bottom: 0.25rem;">Order Confirmation</div>
                <div style="font-size: 0.75rem; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Thank you for your order #12345...</div>
              </div>
            </div>
          </button>
          <button style="width: 100%; text-align: left; padding: 0.75rem; border-bottom: 1px solid var(--border);">
            <div style="display: flex; align-items: start; gap: 0.75rem;">
              <div style="width: 40px; height: 40px; border-radius: 50%; background: #94a3b8; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; flex-shrink: 0;">RJ</div>
              <div style="flex: 1; min-width: 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                  <div style="font-weight: 500; font-size: 0.875rem; color: var(--ink);">Robert Johnson</div>
                  <div style="font-size: 0.75rem; color: var(--muted);">3d</div>
                </div>
                <div style="font-size: 0.875rem; color: var(--muted); margin-bottom: 0.25rem;">Insurance Documents</div>
                <div style="font-size: 0.75rem; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Documents have been uploaded successfully...</div>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- Message Thread -->
      <div style="display: flex; flex-direction: column;">
        <div style="padding: 1rem; border-bottom: 1px solid var(--border);">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">JS</div>
            <div>
              <div style="font-weight: 600; color: var(--ink);">John Smith</div>
              <div style="font-size: 0.75rem; color: var(--muted);">Patient ID: 12345</div>
            </div>
          </div>
        </div>

        <div style="flex: 1; overflow-y: auto; padding: 1.5rem; background: #f8fafc;">
          <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; gap: 0.75rem;">
              <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.75rem; flex-shrink: 0;">JS</div>
              <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                  <span style="font-weight: 600; font-size: 0.875rem;">John Smith</span>
                  <span style="font-size: 0.75rem; color: var(--muted);">2 hours ago</span>
                </div>
                <div style="background: white; padding: 1rem; border-radius: var(--radius-lg); border: 1px solid var(--border); font-size: 0.875rem; line-height: 1.5;">
                  Hi Dr. <?php echo htmlspecialchars($user['last_name']); ?>,<br><br>
                  I've uploaded the latest lab results for my recent checkup. Could you please review them when you have a chance?<br><br>
                  Thank you!
                </div>
              </div>
            </div>
          </div>

          <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; gap: 0.75rem; flex-direction: row-reverse;">
              <div style="width: 32px; height: 32px; border-radius: 50%; background: #94a3b8; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.75rem; flex-shrink: 0;">
                <?php echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1)); ?>
              </div>
              <div style="flex: 1; text-align: right;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; justify-content: flex-end;">
                  <span style="font-size: 0.75rem; color: var(--muted);">1 hour ago</span>
                  <span style="font-weight: 600; font-size: 0.875rem;">You</span>
                </div>
                <div style="background: var(--brand); color: white; padding: 1rem; border-radius: var(--radius-lg); font-size: 0.875rem; line-height: 1.5; display: inline-block; text-align: left;">
                  Hi John,<br><br>
                  I've reviewed your lab results. Everything looks good! Let's schedule a follow-up appointment next month.
                </div>
              </div>
            </div>
          </div>
        </div>

        <div style="padding: 1rem; border-top: 1px solid var(--border); background: white;">
          <div style="display: flex; gap: 0.75rem; align-items: end;">
            <textarea id="msg-reply" placeholder="Type your message..." style="flex: 1; min-height: 80px; resize: vertical; padding: 0.75rem; border: 1px solid var(--border); border-radius: var(--radius); font-size: 0.875rem;"></textarea>
            <button class="btn btn-primary" style="height: fit-content;">
              <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>

<?php elseif ($page==='billing'): ?>
  <?php if ($isReferralOnly): header('Location: ?page=dashboard'); exit; endif; ?>
  <!-- Top-level actions -->
  <div class="flex items-center gap-3 mb-4">
    <h2 class="text-lg font-semibold">Billing & Invoices</h2>
    <div class="ml-auto flex gap-2">
      <input id="bill-search" class="w-full sm:w-80" placeholder="Search invoices…">
      <select id="bill-filter">
        <option value="">All Status</option>
        <option value="paid">Paid</option>
        <option value="pending">Pending</option>
        <option value="overdue">Overdue</option>
      </select>
    </div>
  </div>

  <section class="card p-5">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Invoice #</th>
            <th class="py-2">Date</th>
            <th class="py-2">Patient</th>
            <th class="py-2">Description</th>
            <th class="py-2">Amount</th>
            <th class="py-2">Status</th>
            <th class="py-2">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr class="border-b hover:bg-slate-50">
            <td class="py-3"><span class="font-mono text-brand">#INV-2024-001</span></td>
            <td class="py-3">Jan 15, 2024</td>
            <td class="py-3">
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.75rem;">JS</div>
                John Smith
              </div>
            </td>
            <td class="py-3">Wound Care - CollagenBand Classic</td>
            <td class="py-3 font-semibold">$450.00</td>
            <td class="py-3"><span class="pill pill--pending">Paid</span></td>
            <td class="py-3">
              <button class="btn btn-ghost" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 0.25rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Download
              </button>
            </td>
          </tr>
          <tr class="border-b hover:bg-slate-50">
            <td class="py-3"><span class="font-mono text-brand">#INV-2024-002</span></td>
            <td class="py-3">Jan 18, 2024</td>
            <td class="py-3">
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: #94a3b8; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.75rem;">MW</div>
                Mary Wilson
              </div>
            </td>
            <td class="py-3">Wound Care - CollagenBand Plus</td>
            <td class="py-3 font-semibold">$650.00</td>
            <td class="py-3"><span class="pill" style="background: #fef3c7; color: #92400e;">Pending</span></td>
            <td class="py-3">
              <button class="btn btn-ghost" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 0.25rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                View
              </button>
            </td>
          </tr>
          <tr class="border-b hover:bg-slate-50">
            <td class="py-3"><span class="font-mono text-brand">#INV-2024-003</span></td>
            <td class="py-3">Jan 20, 2024</td>
            <td class="py-3">
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: #94a3b8; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.75rem;">RJ</div>
                Robert Johnson
              </div>
            </td>
            <td class="py-3">Wound Care - CollagenBand Premium</td>
            <td class="py-3 font-semibold">$850.00</td>
            <td class="py-3"><span class="pill pill--stopped">Overdue</span></td>
            <td class="py-3">
              <button class="btn btn-primary" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">Send Reminder</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <div style="padding: 1rem; background: #f0fdfa; border-radius: var(--radius-lg); border: 1px solid #99f6e4;">
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Total Billed</div>
          <div style="font-size: 1.5rem; font-weight: 600; color: var(--ink);">$12,450.00</div>
        </div>
        <div style="padding: 1rem; background: #f0fdf4; border-radius: var(--radius-lg); border: 1px solid #bbf7d0;">
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Paid</div>
          <div style="font-size: 1.5rem; font-weight: 600; color: #16a34a;">$8,900.00</div>
        </div>
        <div style="padding: 1rem; background: #fef3c7; border-radius: var(--radius-lg); border: 1px solid #fde68a;">
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Pending</div>
          <div style="font-size: 1.5rem; font-weight: 600; color: #92400e;">$2,700.00</div>
        </div>
        <div style="padding: 1rem; background: #fee2e2; border-radius: var(--radius-lg); border: 1px solid #fecaca;">
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Overdue</div>
          <div style="font-size: 1.5rem; font-weight: 600; color: #dc2626;">$850.00</div>
        </div>
      </div>
    </div>
  </section>

<?php elseif ($page==='transactions'): ?>
  <?php if ($isReferralOnly): header('Location: ?page=dashboard'); exit; endif; ?>
  <!-- Top-level actions -->
  <div class="flex items-center gap-3 mb-4">
    <h2 class="text-lg font-semibold">Transaction History</h2>
    <div class="ml-auto flex gap-2">
      <input id="txn-search" class="w-full sm:w-80" placeholder="Search transactions…">
      <select id="txn-filter">
        <option value="">All Types</option>
        <option value="payment">Payment</option>
        <option value="refund">Refund</option>
        <option value="invoice">Invoice</option>
      </select>
      <button class="btn btn-ghost">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
        Export CSV
      </button>
    </div>
  </div>

  <section class="card p-5">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Transaction ID</th>
            <th class="py-2">Date & Time</th>
            <th class="py-2">Type</th>
            <th class="py-2">Patient</th>
            <th class="py-2">Invoice #</th>
            <th class="py-2">Payment Method</th>
            <th class="py-2">Amount</th>
            <th class="py-2">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr class="border-b hover:bg-slate-50">
            <td class="py-3"><span class="font-mono text-xs">txn_9k3j2h1g4f</span></td>
            <td class="py-3">Jan 15, 2024<br><span class="text-xs text-slate-500">10:34 AM</span></td>
            <td class="py-3"><span class="pill" style="background: #dcfce7; color: #166534;">Payment</span></td>
            <td class="py-3">
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.75rem;">JS</div>
                John Smith
              </div>
            </td>
            <td class="py-3"><span class="font-mono text-brand">#INV-2024-001</span></td>
            <td class="py-3">
              <div style="display: flex; align-items: center; gap: 0.375rem;">
                <svg width="24" height="16" viewBox="0 0 24 16" fill="none"><rect width="24" height="16" rx="2" fill="#1434CB"/><rect x="12" y="4" width="8" height="8" rx="4" fill="#EB001B"/><rect x="4" y="4" width="8" height="8" rx="4" fill="#FF5F00"/></svg>
                Mastercard •••• 4242
              </div>
            </td>
            <td class="py-3 font-semibold text-green-600">+$450.00</td>
            <td class="py-3"><span class="pill pill--pending">Success</span></td>
          </tr>
          <tr class="border-b hover:bg-slate-50">
            <td class="py-3"><span class="font-mono text-xs">txn_8h2g1f9k3j</span></td>
            <td class="py-3">Jan 14, 2024<br><span class="text-xs text-slate-500">3:22 PM</span></td>
            <td class="py-3"><span class="pill" style="background: #e0e7ff; color: #3730a3;">Invoice</span></td>
            <td class="py-3">
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: #94a3b8; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.75rem;">MW</div>
                Mary Wilson
              </div>
            </td>
            <td class="py-3"><span class="font-mono text-brand">#INV-2024-002</span></td>
            <td class="py-3">—</td>
            <td class="py-3 font-semibold">$650.00</td>
            <td class="py-3"><span class="pill" style="background: #fef3c7; color: #92400e;">Pending</span></td>
          </tr>
          <tr class="border-b hover:bg-slate-50">
            <td class="py-3"><span class="font-mono text-xs">txn_7g1f8k2h9j</span></td>
            <td class="py-3">Jan 12, 2024<br><span class="text-xs text-slate-500">11:15 AM</span></td>
            <td class="py-3"><span class="pill" style="background: #ffe4e6; color: #9f1239;">Refund</span></td>
            <td class="py-3">
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: #94a3b8; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.75rem;">SB</div>
                Sarah Brown
              </div>
            </td>
            <td class="py-3"><span class="font-mono text-brand">#INV-2023-987</span></td>
            <td class="py-3">
              <div style="display: flex; align-items: center; gap: 0.375rem;">
                <svg width="24" height="16" viewBox="0 0 24 16" fill="none"><rect width="24" height="16" rx="2" fill="#0066CC"/></svg>
                Visa •••• 1234
              </div>
            </td>
            <td class="py-3 font-semibold text-red-600">-$125.00</td>
            <td class="py-3"><span class="pill pill--pending">Success</span></td>
          </tr>
          <tr class="border-b hover:bg-slate-50">
            <td class="py-3"><span class="font-mono text-xs">txn_6f9h1k7g2j</span></td>
            <td class="py-3">Jan 10, 2024<br><span class="text-xs text-slate-500">9:45 AM</span></td>
            <td class="py-3"><span class="pill" style="background: #dcfce7; color: #166534;">Payment</span></td>
            <td class="py-3">
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: #94a3b8; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.75rem;">RJ</div>
                Robert Johnson
              </div>
            </td>
            <td class="py-3"><span class="font-mono text-brand">#INV-2023-985</span></td>
            <td class="py-3">
              <div style="display: flex; align-items: center; gap: 0.375rem;">
                <svg width="24" height="16" viewBox="0 0 24 16" fill="none"><rect width="24" height="16" rx="2" fill="#002D72"/></svg>
                Amex •••• 9876
              </div>
            </td>
            <td class="py-3 font-semibold text-green-600">+$750.00</td>
            <td class="py-3"><span class="pill pill--pending">Success</span></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
      <div style="font-size: 0.875rem; color: var(--muted);">Showing 4 of 247 transactions</div>
      <div style="display: flex; gap: 0.5rem;">
        <button class="btn btn-ghost" disabled style="opacity: 0.5;">Previous</button>
        <button class="btn btn-ghost">Next</button>
      </div>
    </div>
  </section>

<?php elseif ($page==='profile'): ?>
  <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="card p-5 lg:col-span-2">
      <h2 class="text-lg font-semibold mb-3">Business Agreements</h2>
      <ul class="list-disc pl-6 text-sm">
        <li><a class="underline" href="/assets/baa.pdf" target="_blank">Business Associate Agreement (BAA)</a></li>
        <li><a class="underline" href="/assets/terms.pdf" target="_blank">Terms &amp; Conditions</a></li>
      </ul>
    </div>
    <div class="card p-5">
      <h2 class="text-lg font-semibold mb-3">Change Password</h2>
      <div class="grid gap-3">
        <input id="pw-cur" type="password" placeholder="Current password">
        <input id="pw-new" type="password" placeholder="New password">
        <input id="pw-con" type="password" placeholder="Confirm new password">
        <button id="btn-pw" class="btn btn-primary" type="button">Update Password</button>
        <div id="pw-hint" class="text-xs text-slate-500"></div>
      </div>
    </div>
  </section>

<?php elseif ($page==='patient-detail' || $page==='patient-edit'): ?>
  <?php
    $patientId = $_GET['id'] ?? '';
    if (!$patientId) {
      echo '<div class="card p-6"><p class="text-red-600">Patient ID is required</p><a href="?page=patients" class="btn mt-3">Back to Patients</a></div>';
    } else {
      // Patient data will be loaded via JavaScript
      $isEditing = ($page === 'patient-edit');
  ?>
  <!-- Breadcrumb with CRUD Actions -->
  <div class="flex items-center justify-between mb-4">
    <div class="text-sm text-slate-600">
      <a href="?page=patients" class="hover:underline">Patient</a>
      <span class="mx-2">/</span>
      <span><?php echo $isEditing ? 'Edit' : 'Detail'; ?> patient</span>
    </div>
    <?php if (!$isEditing): ?>
    <div class="flex gap-2">
      <button id="patient-detail-new-order-btn" class="btn btn-primary" type="button" style="display:none;">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline; margin-right: 4px; vertical-align: middle;">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        New Order
      </button>
    </div>
    <?php endif; ?>
  </div>

  <div id="patient-detail-container" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Loading state -->
    <div class="lg:col-span-3 text-center py-12">
      <div class="text-slate-500">Loading patient information...</div>
    </div>
  </div>

  <script>
  // Store patient data for loading after main script is ready
  window._patientDetailData = {
    patientId: <?php echo json_encode($patientId); ?>,
    isEditing: <?php echo json_encode($isEditing); ?>
  };
  </script>
  <?php } ?>

<?php elseif ($page==='patient-add'): ?>
  <!-- Breadcrumb -->
  <div class="mb-4 text-sm text-slate-600">
    <a href="?page=patients" class="hover:underline">Patient</a>
    <span class="mx-2">/</span>
    <span>Add new patient</span>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column - Patient Form -->
    <div class="card p-6 lg:col-span-1">
      <h2 class="text-2xl font-bold mb-6">Add New Patient</h2>

      <form id="add-patient-form" class="space-y-4">
        <!-- Basic Information -->
        <div>
          <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-2">First Name *</label>
          <input type="text" id="new-first-name" class="w-full" required>
        </div>

        <div>
          <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-2">Last Name *</label>
          <input type="text" id="new-last-name" class="w-full" required>
        </div>

        <div>
          <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-2">Date of Birth *</label>
          <input type="date" id="new-dob" class="w-full" required>
        </div>

        <div>
          <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-2">Sex</label>
          <select id="new-sex" class="w-full">
            <option value="">Select...</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <div>
          <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-2">Phone</label>
          <input type="tel" id="new-phone" class="w-full" maxlength="10" placeholder="10 digits">
        </div>

        <div>
          <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-2">Email</label>
          <input type="email" id="new-email" class="w-full">
        </div>

        <div class="pt-4 border-t">
          <h3 class="font-semibold mb-3">Address</h3>
          <div class="space-y-3">
            <input type="text" id="new-address" class="w-full" placeholder="Street address">
            <div class="grid grid-cols-2 gap-2">
              <input type="text" id="new-city" class="w-full" placeholder="City">
              <select id="new-state" class="w-full">
                <option value="">State</option>
                <?php foreach(usStates() as $s): ?>
                  <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <input type="text" id="new-zip" class="w-full" placeholder="ZIP code">
          </div>
        </div>

        <div class="pt-4 border-t">
          <h3 class="font-semibold mb-3">Insurance Information (Optional)</h3>
          <div class="space-y-3">
            <input type="text" id="new-insurance-provider" class="w-full" placeholder="Insurance provider">
            <input type="text" id="new-insurance-member-id" class="w-full" placeholder="Member ID">
            <input type="text" id="new-insurance-group-id" class="w-full" placeholder="Group ID">
            <input type="tel" id="new-insurance-phone" class="w-full" placeholder="Insurance phone">
          </div>
        </div>

        <div id="add-patient-error" class="text-sm text-red-600 hidden"></div>

        <div class="flex gap-2 pt-4">
          <button type="submit" class="btn flex-1 text-white" style="background: var(--brand);">Create Patient</button>
          <a href="?page=patients" class="btn">Cancel</a>
        </div>
      </form>
    </div>

    <!-- Right Column - Information -->
    <div class="lg:col-span-2 space-y-6">
      <div class="card p-6">
        <h3 class="font-semibold text-lg mb-3">Required Information</h3>
        <p class="text-sm text-slate-600 mb-4">
          Please fill out at least the required fields marked with an asterisk (*).
          You can add additional information like insurance details and documents after creating the patient.
        </p>
        <div class="space-y-2 text-sm">
          <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>Basic demographic information required</span>
          </div>
          <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>Documents can be uploaded after patient creation</span>
          </div>
          <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>MRN will be auto-generated if not provided</span>
          </div>
        </div>
      </div>

      <div class="card p-6">
        <h3 class="font-semibold text-lg mb-3">Next Steps</h3>
        <p class="text-sm text-slate-600 mb-3">After creating the patient, you'll be able to:</p>
        <ol class="list-decimal list-inside space-y-2 text-sm text-slate-600">
          <li>Upload required documents (ID card, insurance card)</li>
          <li>Generate and sign Assignment of Benefits (AOB)</li>
          <li>Create wound care orders</li>
          <li>Schedule appointments</li>
        </ol>
      </div>
    </div>
  </div>

<?php endif; ?>
</main>

<!-- ORDER dialog -->
<dialog id="dlg-order" class="rounded-2xl w-full max-w-4xl">
  <form method="dialog" class="p-0">
    <div class="p-5 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold">Create New Order</h3>
      <button class="btn" type="button" onclick="document.getElementById('dlg-order').close()">Close</button>
    </div>

    <div class="p-5 grid gap-5">
      <!-- Patient chooser -->
      <div>
        <label class="text-sm font-medium">Patient</label>
        <div class="relative">
          <input id="chooser-input" class="w-full" placeholder="Type name, phone, or email" autocomplete="off">
          <input type="hidden" id="chooser-id">
          <div id="chooser-list" class="absolute z-20 mt-1 w-full max-h-64 overflow-auto bg-white border rounded-xl shadow hidden"></div>
        </div>
        <div id="chooser-hint" class="text-xs text-slate-500 mt-1">Search or choose “Create new patient”.</div>

        <!-- Create new patient -->
        <div id="create-section" class="mt-3 hidden">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input id="np-first" placeholder="First name">
            <input id="np-last"  placeholder="Last name">
            <input id="np-dob"   type="date" placeholder="DOB">
            <input id="np-phone" placeholder="Phone (10 digits)">
            <input id="np-cell-phone" placeholder="Cell Phone (10 digits)">
            <input id="np-email" class="md:col-span-2" placeholder="Email">
            <input id="np-address" class="md:col-span-2" placeholder="Street address">
            <input id="np-city"  placeholder="City">
            <select id="np-state">
              <option value="">State</option>
              <?php foreach(usStates() as $s) echo "<option>$s</option>"; ?>
            </select>
            <input id="np-zip" placeholder="ZIP">

            <!-- Insurance Information -->
            <div class="md:col-span-2 text-sm font-medium" style="margin-top:0.5rem">Insurance Information</div>
            <input id="np-ins-provider" placeholder="Insurance Carrier (e.g., Blue Cross)">
            <input id="np-ins-member-id" placeholder="Member ID">
            <input id="np-ins-group-id" placeholder="Group Number">
            <input id="np-ins-payer-phone" placeholder="Payer Phone">

            <!-- Required Document Uploads -->
            <div class="md:col-span-2 mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
              <div class="text-sm font-medium text-blue-900 mb-2">Required Documents</div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="text-xs text-blue-800 block mb-1">Photo ID <span class="text-red-600">*</span></label>
                  <input type="file" id="np-id-card" accept="image/*,application/pdf" class="text-sm">
                  <div class="text-xs text-blue-600 mt-1">Required before order submission</div>
                </div>
                <div>
                  <label class="text-xs text-blue-800 block mb-1">Insurance Card <span class="text-red-600">*</span></label>
                  <input type="file" id="np-ins-card" accept="image/*,application/pdf" class="text-sm">
                  <div class="text-xs text-blue-600 mt-1">Required before order submission</div>
                </div>
              </div>
            </div>
          </div>
          <button id="btn-create-patient" type="button" class="btn mt-2">Save Patient &amp; Use</button>
          <div id="np-hint" class="text-xs text-slate-500 mt-1"></div>
        </div>

        <!-- Selected Patient Document Status -->
        <div id="patient-doc-status" class="mt-3 p-3 border rounded-lg hidden">
          <div class="text-sm font-medium mb-2">Patient Documents</div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
            <div id="doc-status-id" class="flex items-center gap-2">
              <span class="doc-icon">⚠️</span>
              <span>Photo ID: <span class="font-medium" id="doc-status-id-text">Not uploaded</span></span>
            </div>
            <div id="doc-status-ins" class="flex items-center gap-2">
              <span class="doc-icon">⚠️</span>
              <span>Insurance Card: <span class="font-medium" id="doc-status-ins-text">Not uploaded</span></span>
            </div>
          </div>
          <div id="doc-upload-section" class="mt-3 pt-3 border-t hidden">
            <div class="text-sm font-medium text-amber-900 mb-2">Upload Missing Documents</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div id="upload-id-container" class="hidden">
                <label class="text-xs block mb-1">Photo ID <span class="text-red-600">*</span></label>
                <input type="file" id="existing-patient-id-card" accept="image/*,application/pdf" class="text-sm w-full">
              </div>
              <div id="upload-ins-container" class="hidden">
                <label class="text-xs block mb-1">Insurance Card <span class="text-red-600">*</span></label>
                <input type="file" id="existing-patient-ins-card" accept="image/*,application/pdf" class="text-sm w-full">
              </div>
            </div>
            <button id="btn-upload-docs" type="button" class="btn btn-sm mt-2 hidden">Upload Documents</button>
          </div>
        </div>
      </div>

      <!-- Product + Clinical completeness -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="text-sm">Product</label>
          <select id="ord-product" class="w-full"></select>
        </div>

        <div>
          <label class="text-sm block mb-1">Payment</label>
          <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="paytype" value="insurance" checked> Insurance</label>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="paytype" value="self_pay"> Cash</label>
          </div>
        </div>

        <!-- Wounds Section (Multiple wounds supported) -->
        <div class="md:col-span-2">
          <div class="flex items-center justify-between mb-3">
            <label class="text-sm font-medium">Wounds <span class="text-red-600">*</span></label>
            <button type="button" id="btn-add-wound" class="text-sm px-3 py-1 rounded" style="background:var(--primary);color:white">+ Add Wound</button>
          </div>
          <div id="wounds-container" class="space-y-4">
            <!-- Wounds will be added here dynamically -->
          </div>
        </div>

        <div>
          <label class="text-sm">Date of Last Evaluation <span class="text-red-600">*</span></label>
          <input id="last-eval" type="date" class="w-full">
        </div>

        <div>
          <label class="text-sm">Start Date</label>
          <input id="start-date" type="date" class="w-full">
          <div id="date-validation-hint" class="text-xs mt-1" style="display:none;"></div>
        </div>
        <div>
          <label class="text-sm">Frequency (changes per week) <span class="text-red-600">*</span></label>
          <input id="freq-week" type="number" min="1" max="21" value="3" class="w-full">
        </div>
        <div>
          <label class="text-sm">Quantity per Change <span class="text-red-600">*</span></label>
          <input id="qty-change" type="number" min="1" value="1" class="w-full">
        </div>
        <div>
          <label class="text-sm">Duration (days) <span class="text-red-600">*</span></label>
          <input id="duration-days" type="number" min="1" value="30" class="w-full">
        </div>
        <div>
          <label class="text-sm">Refills Authorized</label>
          <input id="refills" type="number" min="0" value="0" class="w-full">
        </div>
        <div class="md:col-span-2">
          <label class="text-sm">Additional Instructions</label>
          <input id="addl-instr" class="w-full" placeholder="e.g., Saline cleanse, apply before dressing">
        </div>

        <div class="md:col-span-2">
          <label class="text-sm">Secondary Dressing</label>
          <select id="secondary-dressing" class="w-full">
            <option value="">None</option>
            <option value="Gauze - 2x2">Gauze - 2x2</option>
            <option value="Gauze - 4x4">Gauze - 4x4</option>
            <option value="Gauze - 6x6">Gauze - 6x6</option>
            <option value="Non-adherent pad">Non-adherent pad</option>
            <option value="Foam dressing">Foam dressing</option>
            <option value="Transparent film">Transparent film</option>
            <option value="Compression wrap">Compression wrap</option>
            <option value="Tubular bandage">Tubular bandage</option>
            <option value="Other">Other (specify in instructions)</option>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="text-sm">Clinical Notes (paste)</label>
          <textarea id="ord-notes" class="w-full" rows="3" placeholder="Paste chart note"></textarea>
        </div>
      </div>

      <!-- Delivery & e-sign -->
      <div class="grid grid-cols-1 gap-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm block mb-1">Deliver To</label>
            <div class="flex items-center gap-4 mb-2">
              <label class="flex items-center gap-2 text-sm"><input type="radio" name="deliver" value="patient" checked> Patient</label>
              <label class="flex items-center gap-2 text-sm"><input type="radio" name="deliver" value="office"> Office</label>
            </div>
            <div id="office-addr" class="grid grid-cols-1 md:grid-cols-2 gap-3 hidden">
              <div class="md:col-span-2"><input id="ship-name" class="w-full" placeholder="Recipient / Attn"></div>
              <div><input id="ship-phone" class="w-full" placeholder="Phone (10 digits)"></div>
              <div class="md:col-span-2"><input id="ship-addr" class="w-full" placeholder="Street address"></div>
              <div><input id="ship-city" class="w-full" placeholder="City"></div>
              <div>
                <select id="ship-state" class="w-full">
                  <option value="">State</option>
                  <?php foreach(usStates() as $s) echo "<option>$s</option>"; ?>
                </select>
              </div>
              <div><input id="ship-zip" class="w-full" placeholder="ZIP"></div>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-3 bg-slate-50 rounded-xl border">
            <div><label class="text-sm font-medium">E-Signature Name <span class="text-red-600">*</span></label><input id="sign-name" class="w-full" placeholder="Dr. Jane Doe"></div>
            <div><label class="text-sm">Title</label><input id="sign-title" class="w-full" placeholder="MD / PA-C / NP"></div>
            <label class="flex items-start gap-2 text-sm md:col-span-2"><input id="ack-sig" type="checkbox"> <span>I certify medical necessity and authorize this order (e-signature).</span></label>
          </div>
        </div>
      </div>

      <!-- Uploads tied to ORDER -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-t pt-4">
        <div class="md:col-span-1">
          <label class="text-sm">Visit Notes (PDF/txt)</label>
          <input type="file" id="file-rx" accept=".pdf,.txt,image/*" class="w-full">
        </div>
        <div class="md:col-span-2">
          <label class="text-sm block mb-2">Insurance Requirements</label>
          <div class="text-xs text-slate-600">
            Patient ID & Insurance Card must be on file with the patient. An AOB is also required.
          </div>
          <div class="mt-2">
            <button type="button" id="btn-aob" class="btn">Generate & Sign AOB</button>
            <span id="aob-hint" class="text-xs text-slate-500 ml-2"></span>
          </div>
        </div>
      </div>
    </div>

    <div class="p-5 border-t flex items-center justify-end">
      <button type="button" id="btn-order-create" class="btn btn-primary">Submit Order</button>
    </div>
  </form>
</dialog>

<!-- ADD PATIENT dialog -->
<dialog id="dlg-patient" class="rounded-2xl w-full max-w-3xl">
  <form method="dialog" class="p-0">
    <div class="p-5 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold">Add New Patient</h3>
      <button class="btn" type="button" onclick="document.getElementById('dlg-patient').close()">Close</button>
    </div>

    <div class="p-5 overflow-y-auto" style="max-height: 70vh;">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Basic Info -->
        <div>
          <label class="text-sm font-medium">First Name <span class="text-red-600">*</span></label>
          <input id="patient-first" class="w-full" placeholder="First name" required>
        </div>
        <div>
          <label class="text-sm font-medium">Last Name <span class="text-red-600">*</span></label>
          <input id="patient-last" class="w-full" placeholder="Last name" required>
        </div>
        <div>
          <label class="text-sm font-medium">Date of Birth <span class="text-red-600">*</span></label>
          <input id="patient-dob" type="date" class="w-full" required>
        </div>
        <div>
          <label class="text-sm font-medium">MRN</label>
          <input id="patient-mrn" class="w-full" placeholder="Auto-generated if blank">
        </div>

        <!-- Contact Info -->
        <div class="md:col-span-2 text-sm font-medium" style="margin-top:0.5rem">Contact Information</div>
        <div>
          <label class="text-sm font-medium">Phone <span class="text-red-600">*</span></label>
          <input id="patient-phone" class="w-full" placeholder="(555) 123-4567" required>
        </div>
        <div>
          <label class="text-sm font-medium">Cell Phone</label>
          <input id="patient-cell-phone" class="w-full" placeholder="Mobile number">
        </div>
        <div class="md:col-span-2">
          <label class="text-sm font-medium">Email <span class="text-red-600">*</span></label>
          <input id="patient-email" type="email" class="w-full" placeholder="patient@example.com" required>
        </div>

        <!-- Address -->
        <div class="md:col-span-2 text-sm font-medium" style="margin-top:0.5rem">Address</div>
        <div class="md:col-span-2">
          <label class="text-sm font-medium">Street Address</label>
          <input id="patient-address" class="w-full" placeholder="123 Main St">
        </div>
        <div>
          <label class="text-sm font-medium">City</label>
          <input id="patient-city" class="w-full" placeholder="City">
        </div>
        <div>
          <label class="text-sm font-medium">State</label>
          <select id="patient-state" class="w-full">
            <option value="">Select State</option>
            <?php foreach(usStates() as $s) echo "<option>$s</option>"; ?>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium">ZIP</label>
          <input id="patient-zip" class="w-full" placeholder="12345">
        </div>

        <!-- Insurance Information -->
        <div class="md:col-span-2 text-sm font-medium" style="margin-top:0.5rem">Insurance Information</div>
        <div>
          <label class="text-sm">Insurance Carrier</label>
          <input id="patient-ins-provider" class="w-full" placeholder="e.g., Blue Cross">
        </div>
        <div>
          <label class="text-sm">Member ID</label>
          <input id="patient-ins-member-id" class="w-full" placeholder="Member ID">
        </div>
        <div>
          <label class="text-sm">Group Number</label>
          <input id="patient-ins-group-id" class="w-full" placeholder="Group Number">
        </div>
        <div>
          <label class="text-sm">Payer Phone</label>
          <input id="patient-ins-payer-phone" class="w-full" placeholder="Payer Phone">
        </div>

        <!-- Required Documents -->
        <div class="md:col-span-2 text-sm font-medium" style="margin-top:0.5rem">Required Documents</div>
        <div>
          <label class="text-sm">Photo ID <span class="text-red-600">*</span></label>
          <input id="patient-id-file" type="file" class="w-full" accept="image/*,.pdf">
          <div class="text-xs text-slate-500 mt-1">License, passport, or government ID</div>
        </div>
        <div>
          <label class="text-sm">Insurance Card <span class="text-red-600">*</span></label>
          <input id="patient-ins-file" type="file" class="w-full" accept="image/*,.pdf">
          <div class="text-xs text-slate-500 mt-1">Front and back if possible</div>
        </div>
      </div>
      <div id="patient-hint" class="text-xs text-slate-500 mt-3"></div>
    </div>

    <div class="p-5 border-t flex items-center justify-end gap-2">
      <button type="button" class="btn" onclick="document.getElementById('dlg-patient').close()">Cancel</button>
      <button type="button" id="btn-save-patient" class="btn btn-primary">Save Patient</button>
    </div>
  </form>
</dialog>

<!-- Stop / Restart prompts -->
<dialog id="dlg-stop" class="rounded-2xl w-full max-w-md">
  <form method="dialog" class="p-0">
    <div class="p-5 border-b"><h3 class="text-lg font-semibold">Stop Order</h3></div>
    <div class="p-5">
      <label class="text-sm">Reason</label>
      <select id="stop-reason" class="w-full">
        <option value="">Select a reason…</option>
        <option>Wound healed</option>
        <option>Worsening wound</option>
        <option>Patient deceased</option>
        <option>Therapy changed</option>
        <option>Other</option>
      </select>
    </div>
    <div class="p-5 border-t flex items-center justify-end gap-2">
      <button type="button" class="btn" onclick="document.getElementById('dlg-stop').close()">Cancel</button>
      <button type="button" id="btn-stop-go" class="btn btn-primary">Stop</button>
    </div>
  </form>
</dialog>

<dialog id="dlg-restart" class="rounded-2xl w-full max-w-xl">
  <form method="dialog" class="p-0">
    <div class="p-5 border-b"><h3 class="text-lg font-semibold">Restart Order</h3></div>
    <div class="p-5">
      <p class="text-sm text-slate-600 mb-2">Paste a fresh clinical note (required).</p>
      <textarea id="restart-notes" rows="6" class="w-full" placeholder="New chart note"></textarea>
    </div>
    <div class="p-5 border-t flex items-center justify-end gap-2">
      <button type="button" class="btn" onclick="document.getElementById('dlg-restart').close()">Cancel</button>
      <button type="button" id="btn-restart-go" class="btn btn-primary">Restart</button>
    </div>
  </form>
</dialog>

<!-- AOB dialog -->
<dialog id="dlg-aob" class="rounded-2xl w-full max-w-lg">
  <form method="dialog" class="p-0">
    <div class="p-5 border-b"><h3 class="text-lg font-semibold">Assignment of Benefits</h3></div>
    <div class="p-5">
      <p class="text-sm text-slate-700">Click “Sign AOB” to generate and stamp an AOB for this patient. The document will include patient name, date/time, and your request IP.</p>
      <div id="aob-msg" class="text-xs text-slate-500 mt-2"></div>
    </div>
    <div class="p-5 border-t flex items-center justify-end gap-2">
      <button type="button" class="btn" onclick="document.getElementById('dlg-aob').close()">Close</button>
      <button type="button" id="btn-aob-sign" class="btn btn-primary">Sign AOB</button>
    </div>
  </form>
</dialog>

<script>
const $=s=>document.querySelector(s);
const fd=o=>{const f=new FormData(); for(const [k,v] of Object.entries(o)) f.append(k,v??''); return f;};
async function api(q,opts={}){const r=await fetch(`?${q}`,{method:opts.method||'GET',headers:{'Accept':'application/json','X-Requested-With':'fetch'},body:opts.body||null});const t=await r.text();try{return JSON.parse(t);}catch(e){alert('Server error:\n'+t);console.error('Server said:',t);throw e;}}

/* Helper functions - defined early for use throughout */
function pill(s){ if(!s) return '<span class="pill">—</span>'; const c={active:'pill pill--active',approved:'pill pill--pending',submitted:'pill pill--pending',pending:'pill pill--pending',shipped:'pill',stopped:'pill pill--stopped'}[(s||'').toLowerCase()]||'pill'; return `<span class="${c}" style="text-transform:capitalize">${s}</span>`; }
function fmt(d){ if(!d) return '—'; return (''+d).slice(0,10); }
function esc(s){ return (s??'').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
function getInitials(first, last){
  const f = (first||'').trim()[0]||'';
  const l = (last||'').trim()[0]||'';
  return (f+l).toUpperCase() || '?';
}

/* ========== Mobile Menu Functionality ========== */
(function initMobileMenu() {
  const menuBtn = document.getElementById('mobile-menu-btn');
  const overlay = document.getElementById('mobile-overlay');
  const sidebar = document.querySelector('.sidebar');

  if (!menuBtn || !overlay || !sidebar) return;

  // Toggle menu function
  function toggleMenu(forceClose = false) {
    if (forceClose) {
      sidebar.classList.remove('mobile-open');
      overlay.classList.remove('mobile-open');
      document.body.style.overflow = '';
    } else {
      const isOpen = sidebar.classList.toggle('mobile-open');
      overlay.classList.toggle('mobile-open');
      // Prevent body scroll when menu is open
      document.body.style.overflow = isOpen ? 'hidden' : '';
    }
  }

  // Open/close menu on button click
  menuBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleMenu();
  });

  // Close menu when clicking overlay
  overlay.addEventListener('click', () => toggleMenu(true));

  // Close menu when clicking sidebar links
  sidebar.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      // Small delay to allow navigation
      setTimeout(() => toggleMenu(true), 100);
    });
  });

  // Close menu on window resize to desktop
  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
      if (window.innerWidth > 767) {
        toggleMenu(true);
      }
    }, 250);
  });

  // Close menu on ESC key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
      toggleMenu(true);
    }
  });
})();

/* Metrics (dashboard) */
if (<?php echo json_encode($page==='dashboard'); ?>) {
  (async()=>{ try{const m=await api('action=metrics'); $('#m-patients').textContent=m.metrics.patients; $('#m-pending').textContent=m.metrics.pending; $('#m-active').textContent=m.metrics.active_orders;}catch(e){} })();

  // Initialize Chart.js for patient growth visualization
  const ctx = document.getElementById('patientChart');
  if (ctx) {
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
          label: 'New Patients',
          data: [12, 19, 15, 25, 22, 30, 35, 42, 38, 45, 50, 58],
          borderColor: '#4DB8A8',
          backgroundColor: 'rgba(77, 184, 168, 0.1)',
          tension: 0.4,
          fill: true,
          pointRadius: 4,
          pointHoverRadius: 6,
          pointBackgroundColor: '#4DB8A8',
          pointBorderColor: '#fff',
          pointBorderWidth: 2
        }, {
          label: 'Active Orders',
          data: [8, 12, 10, 18, 15, 22, 25, 30, 28, 35, 38, 42],
          borderColor: '#10B981',
          backgroundColor: 'rgba(16, 185, 129, 0.1)',
          tension: 0.4,
          fill: true,
          pointRadius: 4,
          pointHoverRadius: 6,
          pointBackgroundColor: '#10B981',
          pointBorderColor: '#fff',
          pointBorderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'top',
            align: 'end',
            labels: {
              usePointStyle: true,
              padding: 15,
              font: {
                family: 'Inter',
                size: 12
              }
            }
          },
          tooltip: {
            backgroundColor: '#1F2937',
            titleFont: {
              family: 'Inter',
              size: 13
            },
            bodyFont: {
              family: 'Inter',
              size: 12
            },
            padding: 12,
            cornerRadius: 8,
            displayColors: true,
            callbacks: {
              label: function(context) {
                return context.dataset.label + ': ' + context.parsed.y + ' patients';
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              font: {
                family: 'Inter',
                size: 11
              },
              color: '#9CA3AF'
            },
            grid: {
              color: '#E5E7EB',
              drawBorder: false
            }
          },
          x: {
            ticks: {
              font: {
                family: 'Inter',
                size: 11
              },
              color: '#9CA3AF'
            },
            grid: {
              display: false,
              drawBorder: false
            }
          }
        },
        interaction: {
          intersect: false,
          mode: 'index'
        }
      }
    });
  }
}

/* Helper functions are now defined at the top of the script block */

/* Helper: Calculate age from DOB */
function calculateAge(dob){
  if(!dob) return '-';
  const birthDate = new Date(dob);
  const today = new Date();
  let age = today.getFullYear() - birthDate.getFullYear();
  const m = today.getMonth() - birthDate.getMonth();
  if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
  return age + ' years';
}

/* Helper: Format date */
function formatDate(date){
  if(!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

/* Dropdown functionality */
document.addEventListener('DOMContentLoaded', () => {
  const notificationsBtn = document.getElementById('notifications-btn');
  const notificationsMenu = document.getElementById('notifications-menu');
  const profileBtn = document.getElementById('profile-btn');
  const profileMenu = document.getElementById('profile-menu');
  const globalNewOrderBtn = document.getElementById('global-new-order-btn');
  const sidebarProfileTrigger = document.getElementById('sidebar-profile-trigger');
  const sidebarToggleBtn = document.getElementById('sidebar-toggle-btn');
  const sidebar = document.querySelector('.sidebar');
  const mainContent = document.querySelector('.main-content');

  // Sidebar collapse toggle
  if (sidebarToggleBtn && sidebar && mainContent) {
    // Load saved state from localStorage
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (sidebarCollapsed) {
      sidebar.classList.add('collapsed');
      mainContent.classList.add('sidebar-collapsed');
    }

    sidebarToggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      mainContent.classList.toggle('sidebar-collapsed');

      // Save state to localStorage
      const isCollapsed = sidebar.classList.contains('collapsed');
      localStorage.setItem('sidebarCollapsed', isCollapsed);
    });
  }

  // Global New Order button - opens patient selector
  if (globalNewOrderBtn) {
    globalNewOrderBtn.addEventListener('click', () => {
      openOrderDialog();
    });
  }

  // Toggle notifications dropdown
  if (notificationsBtn) {
    notificationsBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      notificationsMenu.classList.toggle('show');
      if (profileMenu) profileMenu.classList.remove('show');
    });
  }

  // Function to toggle profile menu
  const toggleProfileMenu = (e) => {
    e.stopPropagation();

    // Position dropdown near the sidebar profile trigger
    if (sidebarProfileTrigger && profileMenu) {
      const rect = sidebarProfileTrigger.getBoundingClientRect();
      profileMenu.style.top = (rect.bottom + 8) + 'px';
      profileMenu.style.left = rect.left + 'px';
    }

    profileMenu.classList.toggle('show');
    if (notificationsMenu) notificationsMenu.classList.remove('show');
  };

  // Toggle profile dropdown from sidebar user profile
  if (sidebarProfileTrigger) {
    sidebarProfileTrigger.addEventListener('click', toggleProfileMenu);
  }

  // Close dropdowns when clicking outside
  document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
      menu.classList.remove('show');
    });
  });

  // Prevent dropdown menus from closing when clicking inside them
  document.querySelectorAll('.dropdown-menu').forEach(menu => {
    menu.addEventListener('click', (e) => {
      e.stopPropagation();
    });
  });

  // Populate notifications
  populateNotifications();
});

/* Populate notification dropdown */
function populateNotifications() {
  const notificationsList = document.getElementById('notifications-list');
  if (!notificationsList) return;

  const notifications = [
    {
      type: 'patient',
      avatar: 'JS',
      name: 'John Smith',
      action: 'submitted new patient records',
      time: '2 hours ago',
      unread: true,
      hasActions: true
    },
    {
      type: 'order',
      avatar: 'MW',
      name: 'Mary Wilson',
      action: 'order #12345 has been shipped',
      time: '5 hours ago',
      unread: true,
      hasActions: false
    },
    {
      type: 'patient',
      avatar: 'RJ',
      name: 'Robert Johnson',
      action: 'uploaded new insurance documents',
      time: '1 day ago',
      unread: false,
      hasActions: true
    },
    {
      type: 'system',
      avatar: 'SY',
      name: 'System',
      action: 'Monthly report is ready for review',
      time: '2 days ago',
      unread: false,
      hasActions: false
    }
  ];

  notificationsList.innerHTML = notifications.map(notif => `
    <div class="dropdown-item" style="display: block; padding: 1rem 1.25rem; ${notif.unread ? 'background: #f0fdfa;' : ''}">
      <div style="display: flex; gap: 0.75rem;">
        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; flex-shrink: 0;">
          ${notif.avatar}
        </div>
        <div style="flex: 1; min-width: 0;">
          <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.25rem;">
            <div style="font-weight: 500; color: var(--ink);">${notif.name}</div>
            ${notif.unread ? '<div style="width: 8px; height: 8px; border-radius: 50%; background: var(--brand); flex-shrink: 0; margin-top: 0.375rem;"></div>' : ''}
          </div>
          <div style="font-size: 0.875rem; color: var(--muted); margin-bottom: 0.25rem;">${notif.action}</div>
          <div style="font-size: 0.75rem; color: var(--muted);">${notif.time}</div>
          ${notif.hasActions ? `
            <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
              <button class="btn btn-primary" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">Approve</button>
              <button class="btn btn-ghost" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">Reject</button>
            </div>
          ` : ''}
        </div>
      </div>
    </div>
  `).join('');
}

/* DASHBOARD table (view-only) */
if (<?php echo json_encode($page==='dashboard'); ?>){
  let rows=[];
  async function loadPatients(q=''){ const res=await api('action=patients&limit=50&q='+encodeURIComponent(q)); rows=res.rows||[]; render(); }
  function render(){
    const tb=$('#patients-tbody'); tb.innerHTML='';
    if(!rows.length){
      tb.innerHTML=`<tr><td colspan="9" class="py-12 text-center" style="color: var(--muted);">
        <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
          <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
          <div><div style="font-weight: 500; font-size: 1rem; color: var(--ink); margin-bottom: 0.25rem;">No patients found</div><div style="font-size: 0.875rem;">Add your first patient to get started</div></div>
        </div>
      </td></tr>`;
      return;
    }
    for(const p of rows){
      const initials = getInitials(p.first_name, p.last_name);
      const age = calculateAge(p.dob);
      const dob = formatDate(p.dob);
      const statusBadge = p.last_status ?
        `<span class="badge badge-${p.last_status.toLowerCase()}">${esc(p.last_status)}</span>` :
        `<span class="badge">Unknown</span>`;

      tb.insertAdjacentHTML('beforeend',`
        <tr style="border-bottom: 1px solid var(--border); transition: background 0.15s;">
          <td class="py-3 px-2">
            <input type="checkbox" style="width: 16px; height: 16px; cursor: pointer;">
          </td>
          <td class="py-3 px-2">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.75rem; flex-shrink: 0;">
                ${initials}
              </div>
              <span style="font-weight: 500; color: var(--ink);">${esc(p.first_name||'')} ${esc(p.last_name||'')}</span>
            </div>
          </td>
          <td class="py-3 px-2" style="color: var(--ink-light);">${esc(p.mrn || '-')}</td>
          <td class="py-3 px-2" style="color: var(--ink-light);">${age}</td>
          <td class="py-3 px-2" style="color: var(--ink-light);">${dob}</td>
          <td class="py-3 px-2" style="color: var(--ink-light);">${esc(p.city && p.state ? p.city+', '+p.state : '-')}</td>
          <td class="py-3 px-2" style="color: var(--ink-light);">${esc(p.email||'-')}</td>
          <td class="py-3 px-2">${statusBadge}</td>
          <td class="py-3 px-2 text-right">
            <button class="icon-btn" type="button" data-acc="${p.id}" style="width: 32px; height: 32px;">
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
            </button>
          </td>
        </tr>`);
    }
  }
  loadPatients('');
}

/* PATIENTS page */
if (<?php echo json_encode($page==='patients'); ?>){
  let rows=[];
  async function load(q=''){ const res=await api('action=patients&limit=200&q='+encodeURIComponent(q)); rows=res.rows||[]; draw(); }
  function draw(){
    const tb=$('#tb'); tb.innerHTML='';
    if(!rows.length){ tb.innerHTML=`<tr><td colspan="8" class="py-6 text-center text-slate-500">No patients</td></tr>`; return; }
    for(const p of rows){
      tb.insertAdjacentHTML('beforeend',`
        <tr class="border-b hover:bg-slate-50">
          <td class="py-2">${esc(p.first_name||'')} ${esc(p.last_name||'')}</td>
          <td class="py-2">${esc(p.dob||'')}</td>
          <td class="py-2">${esc(p.phone||'')}</td>
          <td class="py-2">${esc(p.email||'')}</td>
          <td class="py-2">${esc(p.city||'')}${p.state?', '+esc(p.state):''}</td>
          <td class="py-2">${pill(p.last_status)}</td>
          <td class="py-2">${p.last_remaining ?? '—'}</td>
          <td class="py-2"><button class="btn" type="button" data-acc="${p.id}">View / Edit</button></td>
        </tr>`);
    }
  }
  $('#q').addEventListener('input',e=>load(e.target.value.trim()));
  // Removed btn-new-order - using global-new-order-btn in header instead

  // Add Patient button
  document.getElementById('btn-add-patient').addEventListener('click',()=>{
    // Clear form
    $('#patient-first').value='';
    $('#patient-last').value='';
    $('#patient-dob').value='';
    $('#patient-mrn').value='';
    $('#patient-phone').value='';
    $('#patient-cell-phone').value='';
    $('#patient-email').value='';
    $('#patient-address').value='';
    $('#patient-city').value='';
    $('#patient-state').value='';
    $('#patient-zip').value='';
    $('#patient-ins-provider').value='';
    $('#patient-ins-member-id').value='';
    $('#patient-ins-group-id').value='';
    $('#patient-ins-payer-phone').value='';
    $('#patient-id-file').value='';
    $('#patient-ins-file').value='';
    $('#patient-hint').textContent='';
    $('#patient-hint').style.color='';
    // Open dialog
    document.getElementById('dlg-patient').showModal();
  });

  // Save Patient button
  document.getElementById('btn-save-patient').addEventListener('click',async()=>{
    const btn = $('#btn-save-patient');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    try {
      const first=$('#patient-first').value.trim();
      const last=$('#patient-last').value.trim();
      const dob=$('#patient-dob').value;
      const mrn=$('#patient-mrn').value.trim();
      const phone=$('#patient-phone').value.trim();
      const cellPhone=$('#patient-cell-phone').value.trim();
      const email=$('#patient-email').value.trim();
      const address=$('#patient-address').value.trim();
      const city=$('#patient-city').value.trim();
      const state=$('#patient-state').value;
      const zip=$('#patient-zip').value.trim();
      const insProvider=$('#patient-ins-provider').value.trim();
      const insMemberId=$('#patient-ins-member-id').value.trim();
      const insGroupId=$('#patient-ins-group-id').value.trim();
      const insPayerPhone=$('#patient-ins-payer-phone').value.trim();
      const idFile = $('#patient-id-file').files[0];
      const insFile = $('#patient-ins-file').files[0];

      // Validate required fields
      if(!first||!last||!dob){
        $('#patient-hint').textContent='Please fill in required fields (First Name, Last Name, DOB)';
        $('#patient-hint').style.color='var(--error)';
        btn.disabled = false;
        btn.textContent = 'Save Patient';
        return;
      }

      if(!phone){
        $('#patient-hint').textContent='Phone is required';
        $('#patient-hint').style.color='var(--error)';
        btn.disabled = false;
        btn.textContent = 'Save Patient';
        return;
      }

      if(!email){
        $('#patient-hint').textContent='Email is required';
        $('#patient-hint').style.color='var(--error)';
        btn.disabled = false;
        btn.textContent = 'Save Patient';
        return;
      }

      if(!idFile){
        $('#patient-hint').textContent='Photo ID is required';
        $('#patient-hint').style.color='var(--error)';
        btn.disabled = false;
        btn.textContent = 'Save Patient';
        return;
      }

      if(!insFile){
        $('#patient-hint').textContent='Insurance Card is required';
        $('#patient-hint').style.color='var(--error)';
        btn.disabled = false;
        btn.textContent = 'Save Patient';
        return;
      }

      // Step 1: Create patient
      const patientBody = fd({
        first_name:first, last_name:last, dob, mrn, phone, cell_phone:cellPhone,
        email, address, city, state, zip,
        insurance_provider:insProvider, insurance_member_id:insMemberId,
        insurance_group_id:insGroupId, insurance_payer_phone:insPayerPhone
      });
      const patientRes = await api('action=patient.save',{method:'POST',body:patientBody});

      if(!patientRes.ok){
        $('#patient-hint').textContent=patientRes.error||'Failed to save patient';
        $('#patient-hint').style.color='var(--error)';
        btn.disabled = false;
        btn.textContent = 'Save Patient';
        return;
      }

      const patientId = patientRes.id;

      // Step 2: Upload ID
      const idForm = new FormData();
      idForm.append('patient_id', patientId);
      idForm.append('type', 'id');
      idForm.append('file', idFile);
      const idResp = await fetch('?action=patient.upload', {method:'POST', body:idForm});
      const idResult = await idResp.json();
      if(!idResult.ok){
        $('#patient-hint').textContent='Failed to upload ID: ' + (idResult.error||'Unknown error');
        $('#patient-hint').style.color='var(--error)';
        btn.disabled = false;
        btn.textContent = 'Save Patient';
        return;
      }

      // Step 3: Upload Insurance Card
      const insForm = new FormData();
      insForm.append('patient_id', patientId);
      insForm.append('type', 'ins');
      insForm.append('file', insFile);
      const insResp = await fetch('?action=patient.upload', {method:'POST', body:insForm});
      const insResult = await insResp.json();
      if(!insResult.ok){
        $('#patient-hint').textContent='Failed to upload insurance card: ' + (insResult.error||'Unknown error');
        $('#patient-hint').style.color='var(--error)';
        btn.disabled = false;
        btn.textContent = 'Save Patient';
        return;
      }

      // Success!
      $('#patient-hint').textContent='Patient created successfully!';
      $('#patient-hint').style.color='var(--success)';

      setTimeout(() => {
        document.getElementById('dlg-patient').close();
        load(''); // Reload patients list
      }, 500);

    } catch(e) {
      $('#patient-hint').textContent='Network error: ' + e.message;
      $('#patient-hint').style.color='var(--error)';
      btn.disabled = false;
      btn.textContent = 'Save Patient';
    }
  });

  load('');
}

/* ORDERS page */
if (<?php echo json_encode($page==='orders'); ?>){
  const tb=$('#orders-tb');
  async function loadOrders(){
    const q=$('#oq').value.trim(); const s=$('#of').value;
    const res=await api('action=orders&q='+encodeURIComponent(q)+'&status='+encodeURIComponent(s)); const rows=res.rows||[];
    tb.innerHTML = rows.map(o=>`
      <tr class="border-b hover:bg-slate-50">
        <td class="py-2">${fmt(o.created_at)}</td>
        <td class="py-2">${esc(o.product||'')}</td>
        <td class="py-2">${pill(o.status)}</td>
        <td class="py-2">${o.shipments_remaining??0}</td>
        <td class="py-2">${o.delivery_mode==='office'?'Office':'Patient'}</td>
        <td class="py-2">${fmt(o.expires_at)}</td>
        <td class="py-2 flex gap-2">
          ${o.status==='stopped'
            ? `<button class="btn" data-restart="${esc(o.id)}">Restart</button>`
            : `<button class="btn" data-stop="${esc(o.id)}">Stop</button>`}
        </td>
      </tr>
    `).join('') || `<tr><td colspan="7" class="py-6 text-center text-slate-500">No orders</td></tr>`;
  }
  $('#oq').addEventListener('input',loadOrders);
  $('#of').addEventListener('change',loadOrders);
  // Removed btn-new-order2 - using global-new-order-btn in header instead
  tb.addEventListener('click',async(e)=>{
    const b=e.target.closest('button'); if(!b) return;
    if(b.dataset.stop){ openStopDialog(b.dataset.stop, loadOrders); }
    if(b.dataset.restart){ openRestartDialog(b.dataset.restart, loadOrders); }
  });
  loadOrders();
}

/* ADMIN: change password */
if (<?php echo json_encode($page==='profile'); ?>){
  $('#btn-pw').onclick=async()=>{
    const r=await fetch('?action=user.change_password',{method:'POST',body:fd({current:$('#pw-cur').value,new:$('#pw-new').value,confirm:$('#pw-con').value})});
    const j=await r.json(); $('#pw-hint').textContent = j.ok ? 'Password updated.' : (j.error||'Failed to update');
    if(j.ok){ $('#pw-cur').value=''; $('#pw-new').value=''; $('#pw-con').value=''; }
  };
}

/* Patient detail navigation is handled by page-specific handler below (line ~3564) */

/* Accordion details */
async function toggleAccordion(rowEl, patientId, page){
  const next = rowEl.nextElementSibling;
  if (next && next.classList.contains('acc-row')) { next.remove(); return; }
  rowEl.parentElement.querySelectorAll('.acc-row').forEach(el=>el.remove());

  const data = await api('action=patient.get&id='+encodeURIComponent(patientId));
  const p = data.patient; const orders = data.orders||[];
  const editable = (page==='patients');

  // Calculate age from DOB
  const calcAge = (dob) => {
    if (!dob) return 'N/A';
    const years = Math.floor((new Date() - new Date(dob)) / 31557600000);
    return years + ' years ' + Math.floor(((new Date() - new Date(dob)) % 31557600000) / 2629800000) + ' month';
  };

  const detailsBlock = editable
  ? `
    <!-- Patient Profile Header with Avatar -->
    <div class="text-center mb-6">
      <div class="w-24 h-24 mx-auto mb-4 rounded-full bg-gradient-to-br from-teal-400 to-teal-600 flex items-center justify-center text-white text-3xl font-bold shadow-lg">
        ${(p.first_name||'').charAt(0).toUpperCase()}${(p.last_name||'').charAt(0).toUpperCase()}
      </div>
      <h2 class="text-2xl font-bold mb-2">${esc(p.first_name||'')} ${esc(p.last_name||'')}</h2>
      <div class="text-slate-500 text-sm flex items-center justify-center gap-3">
        ${p.mrn ? `<span>MRN: ${esc(p.mrn)}</span><span>•</span>` : ''}
        ${p.phone ? `<span>${esc(p.phone)}</span>` : '<span class="text-slate-400">No phone</span>'}
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex gap-2 mb-6">
      <button class="btn flex-1 text-white" type="button" style="background: var(--brand);">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline; margin-right: 4px; vertical-align: middle;">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
        Make an Appointment
      </button>
      <button class="btn" type="button">Message</button>
      <button class="btn" type="button">•••</button>
    </div>

    <!-- Demographics Section -->
    <div class="space-y-4 mb-6">
      <div>
        <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-2">Date of birth</label>
        <input type="date" class="w-full mb-1" id="acc-dob-${esc(p.id)}" value="${esc(p.dob||'')}">
        <div class="text-xs text-slate-600">Age: ${calcAge(p.dob)}</div>
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-2">Address</label>
        <input class="w-full mb-2" id="acc-address-${esc(p.id)}" value="${esc(p.address||'')}" placeholder="Street address">
        <div class="text-sm">${esc(p.address||'')}, ${esc(p.city||'')}, ${esc(p.state||'')} ${esc(p.zip||'')}, United States</div>
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-2">Email</label>
        <input class="w-full" id="acc-email-${esc(p.id)}" value="${esc(p.email||'')}">
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-2">Blood group</label>
        <div class="text-sm font-medium">A+</div>
      </div>
    </div>

    <!-- Hidden form fields -->
    <input type="hidden" id="acc-first-${esc(p.id)}" value="${esc(p.first_name||'')}">
    <input type="hidden" id="acc-last-${esc(p.id)}" value="${esc(p.last_name||'')}">
    <input type="hidden" id="acc-mrn-${esc(p.id)}" value="${esc(p.mrn||'')}">
    <input type="hidden" id="acc-phone-${esc(p.id)}" value="${esc(p.phone||'')}">
    <input type="hidden" id="acc-city-${esc(p.id)}" value="${esc(p.city||'')}">
    <input type="hidden" id="acc-state-${esc(p.id)}" value="${esc(p.state||'')}">
    <input type="hidden" id="acc-zip-${esc(p.id)}" value="${esc(p.zip||'')}">

    <!-- Medical History - Future Feature -->
    <!-- Medical history tracking not yet implemented -->

    <!-- Insurance Information -->
    <div class="mb-6 pb-6 border-t pt-6">
      <h4 class="font-semibold text-lg mb-4">Insurance information</h4>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <div class="text-slate-500 text-xs mb-1">Insurance Carrier</div>
            <div class="font-medium text-sm">${esc(p.insurance_provider) || '<span class="text-slate-400">Not provided</span>'}</div>
          </div>
          <div>
            <div class="text-slate-500 text-xs mb-1">Member ID</div>
            <div class="font-medium text-sm">${esc(p.insurance_member_id) || '<span class="text-slate-400">Not provided</span>'}</div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <div class="text-slate-500 text-xs mb-1">Group Number</div>
            <div class="font-medium text-sm">${esc(p.insurance_group_id) || '<span class="text-slate-400">Not provided</span>'}</div>
          </div>
          <div>
            <div class="text-slate-500 text-xs mb-1">Payer Phone</div>
            <div class="font-medium text-sm">${esc(p.insurance_payer_phone) || '<span class="text-slate-400">Not provided</span>'}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Required Documents Section -->
    <div class="mb-6 pb-6 border-t pt-6">
      <h5 class="font-semibold text-sm mb-3">Required Documents for Insurance Orders</h5>
      <div class="space-y-3">
        <div>
          <label class="text-xs">ID Card / Driver's License ${p.id_card_path ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">*</span>'}</label>
          ${p.id_card_path ? `<div class="text-xs text-slate-600 mb-1"><a href="${esc(p.id_card_path)}" target="_blank" class="underline">View current file</a></div>` : ''}
          <input type="file" class="w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic" onchange="uploadPatientFile('${esc(p.id)}', 'id', this.files[0])">
        </div>
        <div>
          <label class="text-xs">Insurance Card ${p.ins_card_path ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">*</span>'}</label>
          ${p.ins_card_path ? `<div class="text-xs text-slate-600 mb-1"><a href="${esc(p.ins_card_path)}" target="_blank" class="underline">View current file</a></div>` : ''}
          <input type="file" class="w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic" onchange="uploadPatientFile('${esc(p.id)}', 'ins', this.files[0])">
        </div>
        <div>
          <label class="text-xs">Assignment of Benefits (AOB) ${p.aob_path ? '<span class="text-green-600">✓ Signed</span>' : '<span class="text-red-600">* Required</span>'}</label>
          ${p.aob_path ? `<div class="text-xs text-slate-600 mb-1">Signed: ${fmt(p.aob_signed_at)}</div>` : ''}
          <button type="button" class="btn text-sm mt-1" onclick="generateAOB('${esc(p.id)}')">${p.aob_path ? 'Re-generate' : 'Generate & Sign'} AOB</button>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex gap-2">
      <button class="btn flex-1 text-white" type="button" data-p-save="${esc(p.id)}" style="background: var(--brand);">Save Changes</button>
      <button class="btn" type="button" data-p-del="${esc(p.id)}">Delete Patient</button>
    </div>
  `
  : `
    <h4 class="font-semibold mb-3">Patient Details</h4>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
      <div><div class="text-slate-500">Name</div><div class="font-semibold">${esc(p.first_name||'')} ${esc(p.last_name||'')}</div></div>
      <div><div class="text-slate-500">DOB</div><div>${fmt(p.dob)}</div></div>
      <div><div class="text-slate-500">MRN</div><div>${esc(p.mrn||'')}</div></div>
      <div><div class="text-slate-500">Phone</div><div>${esc(p.phone||'')}</div></div>
      <div class="sm:col-span-2"><div class="text-slate-500">Address</div><div>${esc(p.address||'')} ${esc(p.city||'')} ${esc(p.state||'')} ${esc(p.zip||'')}</div></div>
    </div>
  `;

  const acc = document.createElement('tr');
  acc.className = 'acc-row';
  acc.innerHTML = `
    <td class="py-4 px-3 bg-slate-50" colspan="8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="card p-6 lg:col-span-1">${detailsBlock}</div>
        <div class="lg:col-span-2 space-y-6">
          <!-- Appointments Section -->
          <div class="card p-6">
            <h4 class="font-semibold text-lg mb-4">Appointments</h4>
            <div class="mb-4">
              <div class="text-sm text-slate-500 mb-3">Upcoming appointment</div>
              <div class="space-y-2">
                ${orders.filter(o=>o.status==='active').slice(0,2).map((o,i)=>{
                  const colors = ['bg-blue-50 border-l-4 border-l-blue-500', 'bg-green-50 border-l-4 border-l-green-500'];
                  return `
                    <div class="${colors[i%2]} p-3 rounded">
                      <div class="flex items-center justify-between">
                        <div>
                          <div class="font-medium text-sm">Dr. ${esc($user['last_name']||'')}</div>
                          <div class="text-xs text-slate-600">${fmt(o.created_at)} • 10:00 AM</div>
                        </div>
                        <button class="btn text-xs" style="background: var(--brand); color: white;">Join Meeting</button>
                      </div>
                    </div>
                  `;
                }).join('') || '<div class="text-sm text-slate-500">No upcoming appointments</div>'}
              </div>
            </div>
            <div>
              <div class="text-sm text-slate-500 mb-3">Previous appointment</div>
              <div class="space-y-2">
                ${orders.filter(o=>o.status!=='active').slice(0,3).map((o,i)=>{
                  const colors = ['bg-pink-50 border-l-4 border-l-pink-400', 'bg-amber-50 border-l-4 border-l-amber-400', 'bg-purple-50 border-l-4 border-l-purple-400'];
                  return `
                    <div class="${colors[i%3]} p-3 rounded">
                      <div class="flex items-center justify-between">
                        <div>
                          <div class="font-medium text-sm">Dr. ${esc($user['last_name']||'')}</div>
                          <div class="text-xs text-slate-600">${fmt(o.created_at)} • 10:00 AM</div>
                        </div>
                        <button class="btn text-xs">Join Meeting</button>
                      </div>
                    </div>
                  `;
                }).join('') || '<div class="text-sm text-slate-500">No previous appointments</div>'}
              </div>
              <button class="text-sm text-slate-600 mt-3 hover:underline">See more ↓</button>
            </div>
          </div>

          <!-- History/Orders Section -->
          <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
              <h4 class="font-semibold text-lg">History</h4>
              <button class="btn btn-primary" type="button" data-new-order="${esc(p.id)}">Create Order</button>
            </div>
            <div class="text-sm text-slate-500 mb-3">Upcoming appointment</div>
            <div class="space-y-2 mb-6">
              ${orders.slice(0,2).map((o,i)=>{
                const colors = ['bg-blue-50 border-l-4 border-l-blue-500', 'bg-green-50 border-l-4 border-l-green-500'];
                return `
                  <div class="${colors[i%2]} p-3 rounded">
                    <div class="flex items-center justify-between">
                      <div>
                        <div class="font-medium text-sm">${esc(o.product||'Wound Care Order')}</div>
                        <div class="text-xs text-slate-600">${fmt(o.created_at)} • ${pill(o.status||'')}</div>
                      </div>
                      <button class="btn text-xs" style="background: var(--brand); color: white;">View Details</button>
                    </div>
                  </div>
                `;
              }).join('') || '<div class="text-sm text-slate-500">No order history</div>'}
            </div>
            <button class="text-sm text-slate-600 hover:underline">See more ↓</button>

            <!-- Full orders table (collapsed by default) -->
            <details class="mt-4">
              <summary class="cursor-pointer text-sm font-medium mb-3">View all orders (detailed)</summary>
              <div class="overflow-x-auto mt-3">
                <table class="w-full text-sm">
                  <thead class="border-b"><tr class="text-left">
                    <th class="py-2">Created</th><th class="py-2">Product</th><th class="py-2">Status</th>
                    <th class="py-2">Bandage Cnt</th><th class="py-2">Deliver To</th><th class="py-2">Expires</th><th class="py-2">Notes</th><th class="py-2">Actions</th>
                  </tr></thead>
                  <tbody>
                    ${orders.map(o=>{
                      const notesBtn = o.rx_note_path ? `<a class="underline" href="?action=file.dl&order_id=${esc(o.id)}" target="_blank">${esc(o.rx_note_name||'Open')}</a>` : '—';
                      const actions = (o.status==='stopped')
                        ? `<button class="btn" data-restart="${esc(o.id)}">Restart</button>`
                        : `<button class="btn" data-stop="${esc(o.id)}">Stop</button>`;
                      return `<tr class="border-b">
                        <td class="py-2">${fmt(o.created_at)}</td>
                        <td class="py-2">${esc(o.product||'')}</td>
                        <td class="py-2">${pill(o.status||'')}</td>
                        <td class="py-2">${o.shipments_remaining ?? 0}</td>
                        <td class="py-2">${o.delivery_mode==='office'?'Office':'Patient'}</td>
                        <td class="py-2">${fmt(o.expires_at)}</td>
                        <td class="py-2">${notesBtn}</td>
                        <td class="py-2 flex gap-2">${actions}</td>
                      </tr>`;
                    }).join('') || `<tr><td colspan="8" class="py-6 text-center text-slate-500">No orders</td></tr>`}
                  </tbody>
                </table>
              </div>
            </details>
          </div>
        </div>
      </div>
    </td>`;
  rowEl.after(acc);

  if (editable){
    const saveBtn = acc.querySelector(`[data-p-save="${p.id}"]`);
    if (saveBtn) saveBtn.onclick = async ()=>{
      const body = fd({
        id: p.id,
        first_name: acc.querySelector(`#acc-first-${p.id}`).value.trim(),
        last_name:  acc.querySelector(`#acc-last-${p.id}`).value.trim(),
        dob:        acc.querySelector(`#acc-dob-${p.id}`).value,
        mrn:        acc.querySelector(`#acc-mrn-${p.id}`).value,
        phone:      acc.querySelector(`#acc-phone-${p.id}`).value,
        email:      acc.querySelector(`#acc-email-${p.id}`).value,
        address:    acc.querySelector(`#acc-address-${p.id}`).value,
        city:       acc.querySelector(`#acc-city-${p.id}`).value,
        state:      acc.querySelector(`#acc-state-${p.id}`).value,
        zip:        acc.querySelector(`#acc-zip-${p.id}`).value,
      });
      const r = await fetch('?action=patient.save',{method:'POST',body});
      const j = await r.json();
      if(!j.ok){ alert(j.error||'Save failed'); return; }
      toggleAccordion(rowEl, p.id, page);
    };

    const delBtn = acc.querySelector(`[data-p-del="${p.id}"]`);
    if (delBtn) delBtn.onclick = async ()=>{
      if(!confirm('Delete patient and all orders?')) return;
      const r=await fetch('?action=patient.delete',{method:'POST',body:fd({id:p.id})});
      const j=await r.json(); if(!j.ok){ alert(j.error||'Delete failed'); return; }
      acc.remove(); rowEl.remove();
    };
  }

  const newOrderBtn = acc.querySelector(`[data-new-order="${p.id}"]`);
  if (newOrderBtn) newOrderBtn.onclick = ()=> openOrderDialog(p.id);

  acc.querySelectorAll('[data-stop]').forEach(b=>b.onclick=()=> openStopDialog(b.dataset.stop, ()=>toggleAccordion(rowEl,p.id,page)));
  acc.querySelectorAll('[data-restart]').forEach(b=>b.onclick=()=> openRestartDialog(b.dataset.restart, ()=>toggleAccordion(rowEl,p.id,page)));
}

/* STOP / RESTART */
let _pendingOrderId=null;
function openStopDialog(orderId, onDone){
  _pendingOrderId=orderId; $('#stop-reason').value=''; $('#dlg-stop').showModal();
  $('#btn-stop-go').onclick=async()=>{
    const reason=$('#stop-reason').value.trim(); if(!reason) { alert('Select a reason'); return; }
    const r=await fetch('?action=order.stop',{method:'POST',body:fd({order_id:_pendingOrderId,reason})}); const j=await r.json();
    if(!j.ok){ alert(j.error||'Error'); return; }
    $('#dlg-stop').close(); onDone&&onDone();
  };
}
function openRestartDialog(orderId, onDone){
  _pendingOrderId=orderId; $('#restart-notes').value=''; $('#dlg-restart').showModal();
  $('#btn-restart-go').onclick=async()=>{
    const notes=$('#restart-notes').value.trim(); if(!notes){ alert('Please paste a clinical note'); return; }
    const r=await fetch('?action=order.reorder',{method:'POST',body:fd({order_id:_pendingOrderId,notes_text:notes})}); const j=await r.json();
    if(!j.ok){ alert(j.error||'Error'); return; }
    $('#dlg-restart').close(); onDone&&onDone();
  };
}

/* Wound location -> "Other" box & Deliver toggle */
document.addEventListener('change', (e)=>{
  if(e.target && e.target.id==='ord-wloc'){
    const other = document.getElementById('ord-wloc-other');
    other.classList.toggle('hidden', e.target.value!=='Other');
  }
  if(e.target && e.target.name==='deliver'){
    document.getElementById('office-addr').classList.toggle('hidden', e.target.value!=='office');
  }
});

/* Patient file upload functions */
async function uploadPatientFile(patientId, type, file) {
  if (!file) return;
  if (file.size > 25 * 1024 * 1024) {
    alert('File too large. Maximum size is 25MB.');
    return;
  }

  const form = new FormData();
  form.append('patient_id', patientId);
  form.append('type', type);
  form.append('file', file);

  try {
    const response = await fetch('?action=patient.upload', { method: 'POST', body: form });
    const text = await response.text();

    // Try to parse as JSON
    let result;
    try {
      result = JSON.parse(text);
    } catch (e) {
      console.error('Server response was not JSON:', text);
      alert('Upload failed: Server error. Check console for details.');
      return;
    }

    if (result.ok) {
      alert(`${type === 'id' ? 'ID Card' : 'Insurance Card'} uploaded successfully!`);
      // Check if we're on a full-page detail view or accordion
      if (window.location.search.includes('page=patient-detail') || window.location.search.includes('page=patient-edit')) {
        // Reload the current page
        window.location.reload();
      } else {
        // Refresh just the accordion view to show the new file
        const rowEl = document.querySelector(`tr[data-patient-id="${patientId}"]`);
        if (rowEl) {
          const nextRow = rowEl.nextElementSibling;
          if (nextRow && nextRow.classList.contains('acc-row')) {
            // Re-trigger the accordion to refresh data
            toggleAccordion(rowEl, patientId, '<?php echo $page; ?>');
            setTimeout(() => toggleAccordion(rowEl, patientId, '<?php echo $page; ?>'), 100);
          }
        }
      }
    } else {
      alert('Upload failed: ' + (result.error || 'Unknown error'));
    }
  } catch (error) {
    alert('Upload error: ' + error.message);
    console.error('Full error:', error);
  }
}

// Update patient document status display
async function updatePatientDocStatus(patientId) {
  try {
    const data = await api('action=patient.get&id=' + encodeURIComponent(patientId));
    const p = data.patient;

    const statusDiv = $('#patient-doc-status');
    const hasId = p.id_card_path && p.id_card_path.trim() !== '';
    const hasIns = p.ins_card_path && p.ins_card_path.trim() !== '';

    // Update status text
    $('#doc-status-id-text').textContent = hasId ? 'Uploaded ✓' : 'Not uploaded';
    $('#doc-status-id-text').style.color = hasId ? 'green' : 'red';
    $('#doc-status-ins-text').textContent = hasIns ? 'Uploaded ✓' : 'Not uploaded';
    $('#doc-status-ins-text').style.color = hasIns ? 'green' : 'red';

    // Show upload section if missing documents
    if (!hasId || !hasIns) {
      $('#doc-upload-section').classList.remove('hidden');
      if (!hasId) $('#upload-id-container').classList.remove('hidden');
      if (!hasIns) $('#upload-ins-container').classList.remove('hidden');
      $('#btn-upload-docs').classList.remove('hidden');

      // Handle document upload for existing patient
      $('#btn-upload-docs').onclick = async () => {
        const btn = $('#btn-upload-docs');
        btn.disabled = true;
        btn.textContent = 'Uploading...';

        try {
          if (!hasId) {
            const idFile = $('#existing-patient-id-card').files[0];
            if (!idFile) { alert('Please select a Photo ID file'); btn.disabled = false; btn.textContent = 'Upload Documents'; return; }

            const idForm = new FormData();
            idForm.append('patient_id', patientId);
            idForm.append('type', 'id');
            idForm.append('file', idFile);
            const idResp = await fetch('?action=patient.upload', {method:'POST', body:idForm});
            const idResult = await idResp.json();
            if(!idResult.ok) throw new Error('ID upload failed: ' + (idResult.error||'Unknown error'));
          }

          if (!hasIns) {
            const insFile = $('#existing-patient-ins-card').files[0];
            if (!insFile) { alert('Please select an Insurance Card file'); btn.disabled = false; btn.textContent = 'Upload Documents'; return; }

            const insForm = new FormData();
            insForm.append('patient_id', patientId);
            insForm.append('type', 'ins');
            insForm.append('file', insFile);
            const insResp = await fetch('?action=patient.upload', {method:'POST', body:insForm});
            const insResult = await insResp.json();
            if(!insResult.ok) throw new Error('Insurance upload failed: ' + (insResult.error||'Unknown error'));
          }

          alert('Documents uploaded successfully!');
          await updatePatientDocStatus(patientId);
        } catch (e) {
          alert('Error: ' + e.message);
        } finally {
          btn.disabled = false;
          btn.textContent = 'Upload Documents';
        }
      };
    } else {
      $('#doc-upload-section').classList.add('hidden');
    }

    statusDiv.classList.remove('hidden');
  } catch (e) {
    console.error('Error fetching patient document status:', e);
  }
}

async function generateAOB(patientId) {
  if (!confirm('Generate and sign Assignment of Benefits (AOB) for this patient?')) {
    return;
  }

  const form = new FormData();
  form.append('patient_id', patientId);
  form.append('type', 'aob');

  try {
    const response = await fetch('?action=patient.upload', { method: 'POST', body: form });
    const result = await response.json();

    if (result.ok) {
      alert('AOB generated and signed successfully!');
      // Check if we're on a full-page detail view or accordion
      if (window.location.search.includes('page=patient-detail') || window.location.search.includes('page=patient-edit')) {
        // Reload the current page
        window.location.reload();
      } else {
        // Refresh just the accordion view to show the new AOB status
        const rowEl = document.querySelector(`tr[data-patient-id="${patientId}"]`);
        if (rowEl) {
          const nextRow = rowEl.nextElementSibling;
          if (nextRow && nextRow.classList.contains('acc-row')) {
            // Re-trigger the accordion to refresh data
            toggleAccordion(rowEl, patientId, '<?php echo $page; ?>');
            setTimeout(() => toggleAccordion(rowEl, patientId, '<?php echo $page; ?>'), 100);
          }
        }
      }
    } else {
      alert('AOB generation failed: ' + (result.error || 'Unknown error'));
    }
  } catch (error) {
    alert('Error: ' + error.message);
  }
}

/* WOUNDS MANAGER - Multiple wounds per order */
let woundCounter = 0;

function initWoundsManager() {
  const container = $('#wounds-container');
  const addBtn = $('#btn-add-wound');

  // Clear existing wounds
  container.innerHTML = '';
  woundCounter = 0;

  // Add first wound by default
  addWound();

  // Add wound button handler
  addBtn.onclick = (e) => {
    e.preventDefault();
    addWound();
  };
}

function addWound() {
  const container = $('#wounds-container');
  const idx = woundCounter++;

  const woundEl = document.createElement('div');
  woundEl.className = 'border rounded p-4 relative';
  woundEl.style.borderColor = 'var(--line)';
  woundEl.dataset.woundIndex = idx;

  woundEl.innerHTML = `
    <div class="flex items-center justify-between mb-3">
      <strong class="text-sm">Wound #${idx + 1}</strong>
      ${idx > 0 ? '<button type="button" class="wound-remove text-sm text-red-600 hover:underline">Remove</button>' : ''}
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div class="md:col-span-2">
        <label class="text-sm">Wound Location <span class="text-red-600">*</span></label>
        <select class="wound-location w-full">
          <option value="">Select…</option>
          <option>Foot — Plantar</option><option>Foot — Dorsal</option><option>Heel</option><option>Ankle</option>
          <option>Lower Leg — Medial</option><option>Lower Leg — Lateral</option><option>Knee</option>
          <option>Thigh</option><option>Hip</option><option>Buttock</option><option>Sacrum/Coccyx</option>
          <option>Abdomen</option><option>Groin</option>
          <option>Upper Arm</option><option>Forearm</option><option>Hand — Dorsal</option><option>Hand — Palmar</option>
          <option>Elbow</option><option>Shoulder</option><option>Back — Upper</option><option>Back — Lower</option>
          <option>Neck</option><option>Face/Scalp</option><option>Other</option>
        </select>
      </div>
      <div>
        <label class="text-sm">Laterality</label>
        <input class="wound-laterality w-full" placeholder="e.g., Left, Right">
      </div>
      <div>
        <label class="text-sm">Length (cm) <span class="text-red-600">*</span></label>
        <input class="wound-length w-full" type="number" step="0.01" min="0">
      </div>
      <div>
        <label class="text-sm">Width (cm) <span class="text-red-600">*</span></label>
        <input class="wound-width w-full" type="number" step="0.01" min="0">
      </div>
      <div>
        <label class="text-sm">Depth (cm)</label>
        <input class="wound-depth w-full" type="number" step="0.01" min="0">
      </div>
      <div>
        <label class="text-sm">Wound Type</label>
        <select class="wound-type w-full">
          <option value="">Select…</option>
          <option>Diabetic ulcer</option><option>Venous stasis ulcer</option>
          <option>Arterial ulcer</option><option>Pressure ulcer</option>
          <option>Traumatic</option><option>Other</option>
        </select>
      </div>
      <div>
        <label class="text-sm">Wound Stage</label>
        <select class="wound-stage w-full">
          <option value="">N/A</option><option>I</option><option>II</option><option>III</option><option>IV</option>
        </select>
      </div>
      <div>
        <label class="text-sm">Primary ICD-10 <span class="text-red-600">*</span></label>
        <input class="wound-icd10-primary w-full" placeholder="e.g., L97.412">
      </div>
      <div>
        <label class="text-sm">Secondary ICD-10</label>
        <input class="wound-icd10-secondary w-full">
      </div>
      <div class="md:col-span-2">
        <label class="text-sm">Wound Notes</label>
        <textarea class="wound-notes w-full" rows="2" placeholder="Additional notes for this wound"></textarea>
      </div>
    </div>
  `;

  container.appendChild(woundEl);

  // Remove handler
  const removeBtn = woundEl.querySelector('.wound-remove');
  if (removeBtn) {
    removeBtn.onclick = (e) => {
      e.preventDefault();
      woundEl.remove();
      renumberWounds();
    };
  }
}

function renumberWounds() {
  const wounds = document.querySelectorAll('[data-wound-index]');
  wounds.forEach((w, i) => {
    const header = w.querySelector('strong');
    if (header) header.textContent = `Wound #${i + 1}`;
  });
}

function collectWoundsData() {
  const wounds = [];
  const woundEls = document.querySelectorAll('[data-wound-index]');

  woundEls.forEach((el) => {
    const wound = {
      location: el.querySelector('.wound-location').value,
      laterality: el.querySelector('.wound-laterality').value,
      length_cm: parseFloat(el.querySelector('.wound-length').value) || null,
      width_cm: parseFloat(el.querySelector('.wound-width').value) || null,
      depth_cm: parseFloat(el.querySelector('.wound-depth').value) || null,
      type: el.querySelector('.wound-type').value,
      stage: el.querySelector('.wound-stage').value,
      icd10_primary: el.querySelector('.wound-icd10-primary').value,
      icd10_secondary: el.querySelector('.wound-icd10-secondary').value,
      notes: el.querySelector('.wound-notes').value
    };
    wounds.push(wound);
  });

  return wounds;
}

/* ORDER dialog (chooser + uploads + AOB) */
let _currentPatientId = null;

async function openOrderDialog(preselectId=null){
  const prods=(await api('action=products')).rows||[];
  $('#ord-product').innerHTML=prods.map(p=>{
    let label = esc(p.name);
    if (p.size) label += ` (${esc(p.size)})`;
    if (p.hcpcs) label += ` — ${esc(p.hcpcs)}`;
    return `<option value="${p.id}">${label}</option>`;
  }).join('');

  // chooser
  const box=$('#chooser-input'), list=$('#chooser-list'), hidden=$('#chooser-id'), hint=$('#chooser-hint'), create=$('#create-section');
  box.value=''; hidden.value=''; create.classList.add('hidden'); list.classList.add('hidden'); $('#np-hint').textContent='';
  _currentPatientId = preselectId || null;

  // hide office address by default
  document.getElementById('office-addr').classList.add('hidden');

  // clear office shipping
  ['ship-name','ship-phone','ship-addr','ship-city','ship-state','ship-zip'].forEach(id=>$('#'+id).value='');

  if(preselectId){
    try{
      const d=await api('action=patient.get&id='+encodeURIComponent(preselectId));
      const p=d.patient;
      hidden.value=preselectId;
      box.value=`${p.first_name||''} ${p.last_name||''} (MRN ${p.mrn||''})`;
    }catch(e){}
  } else { hint.textContent='Start typing to search'; }

  box.oninput=async()=>{
    const q=box.value.trim(); hidden.value=''; _currentPatientId=null;
    $('#patient-doc-status').classList.add('hidden');
    if(q.length<2){ list.classList.add('hidden'); return; }
    const r=await api('action=patients&limit=8&q='+encodeURIComponent(q)); const rows=r.rows||[];
    list.innerHTML = (rows.map(p=>`<button type="button" class="w-full text-left px-3 py-2 hover:bg-slate-50" data-p="${p.id}">${esc(p.first_name)} ${esc(p.last_name)} — ${fmt(p.dob)} • ${esc(p.phone||'')}</button>`).join(''))
      + `<div class="border-t my-1"></div><button type="button" id="opt-create" class="w-full text-left px-3 py-2 hover:bg-slate-50">➕ Create new patient "${esc(q)}"</button>`;
    list.classList.remove('hidden');
    list.querySelectorAll('[data-p]').forEach(b=>b.onclick=async()=>{
      hidden.value=b.dataset.p;
      _currentPatientId=b.dataset.p;
      box.value=b.textContent;
      list.classList.add('hidden');
      create.classList.add('hidden');
      // Fetch patient details to check document status
      await updatePatientDocStatus(b.dataset.p);
    });
    const add=list.querySelector('#opt-create'); if(add) add.onclick=()=>{ list.classList.add('hidden'); create.classList.remove('hidden'); $('#np-first').focus(); };
  };

  $('#btn-create-patient').onclick=async()=>{
    if(hidden.value){ $('#np-hint').textContent='A patient is already selected.'; return; }
    const first=$('#np-first').value.trim(), last=$('#np-last').value.trim();
    if(!first||!last){ $('#np-hint').textContent='First and last name required.'; return; }

    const idFile = $('#np-id-card').files[0];
    const insFile = $('#np-ins-card').files[0];

    if(!idFile){ $('#np-hint').textContent='Photo ID is required.'; $('#np-hint').style.color='red'; return; }
    if(!insFile){ $('#np-hint').textContent='Insurance card is required.'; $('#np-hint').style.color='red'; return; }

    $('#np-hint').textContent='Creating patient...'; $('#np-hint').style.color='';

    // Create patient first
    const r=await fetch('?action=patient.save',{method:'POST',body:fd({
      first_name:first,last_name:last,dob:$('#np-dob').value,
      phone:$('#np-phone').value,cell_phone:$('#np-cell-phone').value,email:$('#np-email').value,
      address:$('#np-address').value,city:$('#np-city').value,state:$('#np-state').value,zip:$('#np-zip').value,
      insurance_provider:$('#np-ins-provider').value,insurance_member_id:$('#np-ins-member-id').value,
      insurance_group_id:$('#np-ins-group-id').value,insurance_payer_phone:$('#np-ins-payer-phone').value
    })});
    const j=await r.json();
    if(!j.ok){ $('#np-hint').textContent=j.error||'Failed to create'; $('#np-hint').style.color='red'; return; }

    const patientId = j.id;
    $('#np-hint').textContent='Uploading documents...';

    // Upload ID card
    try {
      const idForm = new FormData();
      idForm.append('patient_id', patientId);
      idForm.append('type', 'id');
      idForm.append('file', idFile);
      const idResp = await fetch('?action=patient.upload', {method:'POST', body:idForm});
      const idResult = await idResp.json();
      if(!idResult.ok){ throw new Error('ID upload failed: ' + (idResult.error||'Unknown error')); }

      // Upload insurance card
      const insForm = new FormData();
      insForm.append('patient_id', patientId);
      insForm.append('type', 'ins');
      insForm.append('file', insFile);
      const insResp = await fetch('?action=patient.upload', {method:'POST', body:insForm});
      const insResult = await insResp.json();
      if(!insResult.ok){ throw new Error('Insurance upload failed: ' + (insResult.error||'Unknown error')); }

      hidden.value=patientId; _currentPatientId=patientId;
      box.value=`${first} ${last} (MRN ${j.mrn})`;
      $('#np-hint').textContent='Patient created with documents ✓'; $('#np-hint').style.color='green';
      create.classList.add('hidden');
      await updatePatientDocStatus(patientId);
    } catch(e) {
      $('#np-hint').textContent='Error: ' + e.message; $('#np-hint').style.color='red';
    }
  };

  // reset e-sign & clinical fields
  document.querySelector('input[name="deliver"][value="patient"]').checked=true;
  document.getElementById('office-addr').classList.add('hidden');
  $('#ack-sig').checked=false; $('#sign-name').value=''; $('#sign-title').value='';
  $('#last-eval').value=''; $('#start-date').value='';
  $('#freq-week').value='3'; $('#qty-change').value='1'; $('#duration-days').value='30'; $('#refills').value='0';
  $('#addl-instr').value=''; $('#secondary-dressing').value='';
  $('#ord-notes').value='';
  $('#file-rx').value=''; $('#aob-hint').textContent='';

  // Submit
  $('#btn-order-create').onclick=async()=>{
    const btn=$('#btn-order-create'); if(btn.disabled) return; btn.disabled=true; btn.textContent='Submitting…';
    try{
      const pid=$('#chooser-id').value || _currentPatientId; if(!pid){ alert('Select or create a patient'); btn.disabled=false; btn.textContent='Submit Order'; return; }

      // Verify patient has required documents
      const patientData = await api('action=patient.get&id=' + encodeURIComponent(pid));
      const patient = patientData.patient;
      const hasId = patient.id_card_path && patient.id_card_path.trim() !== '';
      const hasIns = patient.ins_card_path && patient.ins_card_path.trim() !== '';

      if (!hasId || !hasIns) {
        const missing = [];
        if (!hasId) missing.push('Photo ID');
        if (!hasIns) missing.push('Insurance Card');
        alert('Cannot submit order: Patient is missing required documents: ' + missing.join(', ') + '. Please upload these documents before submitting the order.');
        btn.disabled=false; btn.textContent='Submit Order';
        return;
      }

      if(!$('#ack-sig').checked){ alert('Please acknowledge the e-signature statement.'); btn.disabled=false; btn.textContent='Submit Order'; return; }

      // Collect wounds data
      const woundsData = collectWoundsData();
      if (woundsData.length === 0) {
        alert('Please add at least one wound');
        btn.disabled=false; btn.textContent='Submit Order';
        return;
      }

      // Validate wounds
      for (let i = 0; i < woundsData.length; i++) {
        const w = woundsData[i];
        if (!w.location || !w.length_cm || !w.width_cm || !w.icd10_primary) {
          alert(`Wound #${i + 1}: Please fill in required fields (Location, Length, Width, Primary ICD-10)`);
          btn.disabled=false; btn.textContent='Submit Order';
          return;
        }
      }

      const body=new FormData();
      body.append('patient_id', pid);
      body.append('product_id', $('#ord-product').value);
      body.append('payment_type', document.querySelector('input[name="paytype"]:checked').value);

      // Send wounds as JSON
      body.append('wounds_data', JSON.stringify(woundsData));

      body.append('last_eval_date', $('#last-eval').value);
      body.append('start_date', $('#start-date').value);
      body.append('frequency_per_week', $('#freq-week').value);
      body.append('qty_per_change', $('#qty-change').value);
      body.append('duration_days', $('#duration-days').value);
      body.append('refills_allowed', $('#refills').value);
      body.append('additional_instructions', $('#addl-instr').value);
      body.append('secondary_dressing', $('#secondary-dressing').value);

      body.append('notes_text', $('#ord-notes').value);

      body.append('delivery_to', document.querySelector('input[name="deliver"]:checked').value);
      body.append('shipping_name', $('#ship-name').value);
      body.append('shipping_phone', $('#ship-phone').value);
      body.append('shipping_address', $('#ship-addr').value);
      body.append('shipping_city', $('#ship-city').value);
      body.append('shipping_state', $('#ship-state').value);
      body.append('shipping_zip', $('#ship-zip').value);

      body.append('sign_name', $('#sign-name').value);
      body.append('sign_title', $('#sign-title').value);
      body.append('ack_sig', '1');

      if($('#file-rx').files[0])  body.append('file_rx_note', $('#file-rx').files[0]);

      const r=await fetch('?action=order.create',{method:'POST',body});
      const t=await r.text(); let j;
      try{ j=JSON.parse(t); }catch{ alert('Server returned non-JSON:\n'+t); return; }
      if(!j.ok){ alert(j.error||'Order creation failed'); return; }

      document.getElementById('dlg-order').close();
      const accBtn=document.querySelector(`[data-acc="${pid}"]`);
      if(accBtn){ const row=accBtn.closest('tr'); toggleAccordion(row,pid,<?php echo json_encode($page); ?>); }
      if(<?php echo json_encode($page==='orders'); ?>){ const evt=new Event('input'); (document.getElementById('oq')||{dispatchEvent:()=>{}}).dispatchEvent(evt); }
    }catch(e){
      alert('Network or server error: '+e);
    }finally{
      btn.disabled=false; btn.textContent='Submit Order';
    }
  };

  // AOB events
  $('#btn-aob').onclick = ()=>{
    if(!_currentPatientId && !$('#chooser-id').value){ alert('Select a patient first'); return; }
    $('#aob-msg').textContent = '';
    document.getElementById('dlg-aob').showModal();
  };
  $('#btn-aob-sign').onclick = async ()=>{
    const pid = _currentPatientId || $('#chooser-id').value;
    if(!pid){ return; }
    const r = await fetch('?action=patient.upload',{method:'POST', body: fd({ patient_id: pid, type:'aob' })});
    const j = await r.json();
    if(!j.ok){ alert(j.error||'Failed to sign AOB'); return; }
    $('#aob-msg').textContent = 'AOB signed and saved.';
    $('#aob-hint').textContent = 'AOB on file ✓';
  };

  // Initialize wounds manager
  initWoundsManager();

  // Add date validation listeners
  const lastEvalInput = $('#last-eval');
  const startDateInput = $('#start-date');
  const dateHint = $('#date-validation-hint');

  function validateDates() {
    const lastEval = lastEvalInput.value;
    const startDate = startDateInput.value || new Date().toISOString().split('T')[0];

    if (!lastEval) {
      dateHint.style.display = 'none';
      return;
    }

    const lastEvalDate = new Date(lastEval);
    const startDateObj = new Date(startDate);
    const daysDiff = Math.floor((startDateObj - lastEvalDate) / (1000 * 60 * 60 * 24));

    if (daysDiff < 0) {
      dateHint.textContent = '⚠ Start date cannot be before last evaluation date';
      dateHint.style.color = 'var(--error)';
      dateHint.style.display = 'block';
    } else if (daysDiff > 30) {
      dateHint.textContent = `⚠ Start date is ${daysDiff} days after last evaluation (max 30 days allowed)`;
      dateHint.style.color = 'var(--error)';
      dateHint.style.display = 'block';
    } else {
      dateHint.textContent = `✓ ${daysDiff} days between evaluation and start date`;
      dateHint.style.color = 'var(--success)';
      dateHint.style.display = 'block';
    }
  }

  lastEvalInput.addEventListener('change', validateDates);
  startDateInput.addEventListener('change', validateDates);

  document.getElementById('dlg-order').showModal();
}

/* Stop/Restart fallback */
document.addEventListener('click', (e)=>{
  const stopBtn=e.target.closest('[data-stop]'); if(stopBtn) openStopDialog(stopBtn.dataset.stop, ()=>location.reload());
  const reBtn=e.target.closest('[data-restart]'); if(reBtn) openRestartDialog(reBtn.dataset.restart, ()=>location.reload());
});

/* ========== FULL-PAGE PATIENT DETAIL/EDIT RENDERING ========== */

function renderPatientDetailPage(p, orders, isEditing) {
  const container = document.getElementById('patient-detail-container');
  if (!container) return;

  // Calculate age
  const calcAge = (dob) => {
    if (!dob) return 'N/A';
    const years = Math.floor((new Date() - new Date(dob)) / 31557600000);
    const months = Math.floor(((new Date() - new Date(dob)) % 31557600000) / 2629800000);
    return `${years} years ${months} month`;
  };

  // Left column - Patient profile with insurance PRIORITIZED
  const leftColumn = `
    <div class="card p-6">
      <!-- Patient Profile Header with Avatar -->
      <div class="text-center mb-6">
        <div class="w-20 h-20 mx-auto mb-3 rounded-full bg-gradient-to-br from-teal-400 to-teal-600 flex items-center justify-center text-white text-2xl font-bold shadow-lg">
          ${(p.first_name||'').charAt(0).toUpperCase()}${(p.last_name||'').charAt(0).toUpperCase()}
        </div>
        <h2 class="text-xl font-bold mb-1">${esc(p.first_name||'')} ${esc(p.last_name||'')}</h2>
        <div class="text-slate-500 text-xs flex items-center justify-center gap-2">
          <span>${esc(p.sex||'Female')}</span>
          <span>•</span>
          <span>#${esc(p.mrn||'N/A')}</span>
          <span>•</span>
          <span>+1 ${esc(p.phone||'')}</span>
        </div>
      </div>

      ${!isEditing ? `
        <!-- View Mode - Action Buttons -->
        <div class="flex gap-2 mb-4">
          <button class="btn flex-1 btn-primary" type="button" onclick="openOrderDialog('${esc(p.id)}')">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline; margin-right: 4px; vertical-align: middle;">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            New Order
          </button>
          <button class="btn" type="button" onclick="alert('Message feature coming soon')">Message</button>
          <button class="btn" type="button" onclick="document.getElementById('action-menu-${esc(p.id)}').classList.toggle('hidden')">•••</button>
        </div>
        <!-- Action Menu -->
        <div id="action-menu-${esc(p.id)}" class="hidden mb-4 p-2 bg-slate-50 rounded border">
          <a href="?page=patient-edit&id=${esc(p.id)}" class="block px-3 py-2 text-sm hover:bg-white rounded">Edit profile patient</a>
          <button onclick="if(confirm('Delete this patient?')) deletePatient('${esc(p.id)}')" class="block w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-white rounded">Delete patient</button>
        </div>
      ` : ''}

      <!-- Key Demographics - Compact -->
      <div class="space-y-3 mb-4 text-sm">
        <div>
          <div class="text-slate-500 text-xs mb-1">Date of birth</div>
          ${isEditing
            ? `<input type="date" class="w-full text-sm" id="edit-dob" value="${esc(p.dob||'')}">`
            : `<div class="font-medium">${fmt(p.dob)} <span class="text-slate-500">(${calcAge(p.dob)})</span></div>`
          }
        </div>

        <div>
          <div class="text-slate-500 text-xs mb-1">Address</div>
          ${isEditing
            ? `<input class="w-full text-sm mb-1" id="edit-address" value="${esc(p.address||'')}" placeholder="Street address">`
            : `<div class="font-medium">${esc(p.address||'')}<br>${esc(p.city||'')}, ${esc(p.state||'')} ${esc(p.zip||'')}, United States</div>`
          }
        </div>

        <div>
          <div class="text-slate-500 text-xs mb-1">Email</div>
          ${isEditing
            ? `<input class="w-full text-sm" id="edit-email" value="${esc(p.email||'')}">`
            : `<div class="font-medium">${esc(p.email||'')}</div>`
          }
        </div>

        ${isEditing ? `
          <input type="hidden" id="edit-city" value="${esc(p.city||'')}">
          <input type="hidden" id="edit-state" value="${esc(p.state||'')}">
          <input type="hidden" id="edit-zip" value="${esc(p.zip||'')}">
          <input type="hidden" id="edit-phone" value="${esc(p.phone||'')}">
        ` : ''}

        <div>
          <div class="text-slate-500 text-xs mb-1">Blood group</div>
          <div class="font-medium">A+</div>
        </div>
      </div>

      <!-- Medical History - Compact -->
      <div class="mb-4 pb-4 border-t pt-4">
        <div class="text-slate-500 text-xs mb-2">Medical history</div>
        <div class="flex flex-wrap gap-1">
          <span class="px-2 py-1 bg-slate-100 text-slate-700 rounded text-xs">Hypertension</span>
          <span class="px-2 py-1 bg-slate-100 text-slate-700 rounded text-xs">Asthma</span>
          <span class="px-2 py-1 bg-slate-100 text-slate-700 rounded text-xs">Diabetes</span>
        </div>
      </div>

      <!-- Insurance Information - PRIORITIZED SECTION -->
      <div class="mb-4 pb-4 border-t pt-4">
        <h4 class="font-semibold text-sm mb-3">Insurance information</h4>
        <div class="space-y-3 text-sm">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <div class="text-slate-500 text-xs mb-1">Type of insurance</div>
              <div class="font-medium">${esc(p.insurance_provider||'Not provided')}</div>
            </div>
            <div>
              <div class="text-slate-500 text-xs mb-1">Participation number</div>
              <div class="font-medium">${esc(p.insurance_member_id||'N/A')}</div>
            </div>
          </div>
          <!-- Validity period and membership status not yet tracked in database -->
        </div>
      </div>

      ${isEditing ? `
        <!-- Required Documents Section -->
        <div class="mb-6 pb-6 border-t pt-6">
          <h5 class="font-semibold text-sm mb-3">Required Documents</h5>
          <div class="space-y-3">
            <div>
              <label class="text-xs">ID Card ${p.id_card_path ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">*</span>'}</label>
              ${p.id_card_path ? `<div class="text-xs text-slate-600 mb-1"><a href="${esc(p.id_card_path)}" target="_blank" class="underline">View current file</a></div>` : ''}
              <input type="file" class="w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic" onchange="uploadPatientFile('${esc(p.id)}', 'id', this.files[0])">
            </div>
            <div>
              <label class="text-xs">Insurance Card ${p.ins_card_path ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">*</span>'}</label>
              ${p.ins_card_path ? `<div class="text-xs text-slate-600 mb-1"><a href="${esc(p.ins_card_path)}" target="_blank" class="underline">View current file</a></div>` : ''}
              <input type="file" class="w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic" onchange="uploadPatientFile('${esc(p.id)}', 'ins', this.files[0])">
            </div>
            <div>
              <label class="text-xs">AOB ${p.aob_path ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">*</span>'}</label>
              ${p.aob_path ? `<div class="text-xs text-slate-600 mb-1">Signed: ${fmt(p.aob_signed_at)}</div>` : ''}
              <button type="button" class="btn text-sm mt-1" onclick="generateAOB('${esc(p.id)}')">${p.aob_path ? 'Re-generate' : 'Generate & Sign'} AOB</button>
            </div>
          </div>
        </div>

        <!-- Save/Cancel Actions -->
        <div class="flex gap-2">
          <button class="btn flex-1 text-white" type="button" onclick="savePatientFromDetail('${esc(p.id)}')" style="background: var(--brand);">Save Changes</button>
          <a href="?page=patient-detail&id=${esc(p.id)}" class="btn">Cancel</a>
        </div>
      ` : `
        <div class="flex gap-2">
          <a href="?page=patient-edit&id=${esc(p.id)}" class="btn flex-1 text-white" style="background: var(--brand);">Edit Patient</a>
          <button class="btn" type="button" onclick="if(confirm('Delete this patient?')) deletePatient('${esc(p.id)}')">Delete</button>
        </div>
      `}
    </div>
  `;

  // Wire up the top-level New Order button
  const topBtn = document.getElementById('patient-detail-new-order-btn');
  if (topBtn) {
    topBtn.style.display = 'inline-flex';
    topBtn.onclick = () => openOrderDialog(p.id);
  }

  // Right column - Orders and History (matching screenshot layout)
  const rightColumn = `
    <div class="lg:col-span-2 space-y-6">
      <!-- Orders Section -->
      <div class="card p-6">
        <h4 class="font-semibold text-base mb-4">Orders</h4>

        ${orders.length > 0 ? `
          <!-- Upcoming/Active Orders -->
          <div class="mb-4">
            <div class="text-xs text-slate-500 mb-3">Upcoming orders</div>
            <div class="space-y-2">
              ${orders.filter(o=>o.status==='active' || o.status==='submitted' || o.status==='approved').slice(0, 3).map((o,i)=>{
                const colors = [
                  {bg: 'bg-blue-50', border: 'border-l-blue-500'},
                  {bg: 'bg-green-50', border: 'border-l-green-500'},
                  {bg: 'bg-purple-50', border: 'border-l-purple-500'}
                ];
                const color = colors[i % 3];
                return `
                  <div class="${color.bg} border-l-4 ${color.border} p-3 rounded flex items-center justify-between">
                    <div class="flex-1">
                      <div class="font-medium text-sm mb-1">${esc(o.product||'Wound Care Product')}</div>
                      <div class="text-xs text-slate-600">${fmt(o.created_at)} • ${esc(o.frequency||'Weekly')}</div>
                    </div>
                    <button class="btn btn-sm" onclick="alert('View order details')">View Details</button>
                  </div>
                `;
              }).join('')}
            </div>
            ${orders.filter(o=>o.status==='active' || o.status==='submitted' || o.status==='approved').length > 3 ? `
              <button class="text-xs text-slate-600 hover:text-slate-900 mt-3 flex items-center gap-1">
                See more
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
              </button>
            ` : ''}
          </div>

        ` : `
          <div class="text-center py-8">
            <p class="text-slate-500 text-sm mb-3">No wound care orders yet</p>
            <button class="btn btn-primary btn-sm" type="button" onclick="openOrderDialog('${esc(p.id)}')">Create First Order</button>
          </div>
        `}
      </div>

      <!-- History Section -->
      <div class="card p-6">
        <h4 class="font-semibold text-base mb-4">History</h4>
        ${orders.length > 0 ? `
          <div class="space-y-2">
            ${orders.slice(0, 5).map((o,i)=>{
              const colors = [
                {bg: 'bg-blue-50', border: 'border-l-blue-500'},
                {bg: 'bg-green-50', border: 'border-l-green-500'},
                {bg: 'bg-purple-50', border: 'border-l-purple-500'},
                {bg: 'bg-amber-50', border: 'border-l-amber-500'},
                {bg: 'bg-pink-50', border: 'border-l-pink-500'}
              ];
              const color = colors[i % 5];
              return `
                <div class="${color.bg} border-l-4 ${color.border} p-3 rounded flex items-center justify-between">
                  <div class="flex-1">
                    <div class="font-medium text-sm mb-1">${esc(o.product||'Wound Care Product')}</div>
                    <div class="text-xs text-slate-600">${fmt(o.created_at)} • ${esc(o.frequency||'Weekly')}</div>
                  </div>
                  <button class="btn btn-sm" onclick="alert('View order details')">View Details</button>
                </div>
              `;
            }).join('')}
          </div>
          ${orders.length > 5 ? `
            <button class="text-xs text-slate-600 hover:text-slate-900 mt-3 flex items-center gap-1">
              See more
              <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
              </svg>
            </button>
          ` : ''}
        ` : `
          <div class="text-center py-8">
            <p class="text-slate-500 text-sm">No order history yet</p>
          </div>
        `}
      </div>
    </div>
  `;

  container.innerHTML = leftColumn + rightColumn;
}

async function savePatientFromDetail(patientId) {
  const body = fd({
    id: patientId,
    dob: $('#edit-dob')?.value || '',
    address: $('#edit-address')?.value || '',
    email: $('#edit-email')?.value || '',
    city: $('#edit-city')?.value || '',
    state: $('#edit-state')?.value || '',
    zip: $('#edit-zip')?.value || '',
    phone: $('#edit-phone')?.value || ''
  });

  try {
    const r = await fetch('?action=patient.save', {method: 'POST', body});
    const j = await r.json();
    if (j.ok) {
      window.location.href = '?page=patient-detail&id=' + encodeURIComponent(patientId);
    } else {
      alert('Save failed: ' + (j.error || 'Unknown error'));
    }
  } catch (e) {
    alert('Error saving patient: ' + e.message);
  }
}

async function deletePatient(patientId) {
  try {
    const r = await fetch('?action=patient.delete', {method: 'POST', body: fd({id: patientId})});
    const j = await r.json();
    if (j.ok) {
      window.location.href = '?page=patients';
    } else {
      alert('Delete failed: ' + (j.error || 'Unknown error'));
    }
  } catch (e) {
    alert('Error deleting patient: ' + e.message);
  }
}

/* ========== ADD PATIENT FORM HANDLER ========== */

if (document.getElementById('add-patient-form')) {
  document.getElementById('add-patient-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const errorDiv = $('#add-patient-error');
    errorDiv.classList.add('hidden');

    const body = fd({
      first_name: $('#new-first-name').value.trim(),
      last_name: $('#new-last-name').value.trim(),
      dob: $('#new-dob').value,
      sex: $('#new-sex').value,
      phone: $('#new-phone').value,
      email: $('#new-email').value,
      address: $('#new-address').value,
      city: $('#new-city').value,
      state: $('#new-state').value,
      zip: $('#new-zip').value,
      insurance_provider: $('#new-insurance-provider').value,
      insurance_member_id: $('#new-insurance-member-id').value,
      insurance_group_id: $('#new-insurance-group-id').value,
      insurance_payer_phone: $('#new-insurance-phone').value
    });

    try {
      const r = await fetch('?action=patient.save', {method: 'POST', body});
      const j = await r.json();
      if (j.ok) {
        // Redirect to the new patient's detail page
        window.location.href = '?page=patient-detail&id=' + encodeURIComponent(j.id);
      } else {
        errorDiv.textContent = j.error || 'Failed to create patient';
        errorDiv.classList.remove('hidden');
      }
    } catch (e) {
      errorDiv.textContent = 'Error: ' + e.message;
      errorDiv.classList.remove('hidden');
    }
  });
}

/* ========== UPDATE PATIENT TABLE TO USE NEW PAGES ========== */

// Update the "Add Patient" button to go to the new page instead of modal
const addPatientBtn = document.getElementById('btn-add-patient');
if (addPatientBtn) {
  addPatientBtn.onclick = () => {
    window.location.href = '?page=patient-add';
  };
}

// Update patient row clicks to go to detail page instead of accordion
document.addEventListener('click', (e) => {
  const accBtn = e.target.closest('[data-acc]');
  if (accBtn && <?php echo json_encode($page === 'patients'); ?>) {
    e.preventDefault();
    const patientId = accBtn.dataset.acc;
    window.location.href = '?page=patient-detail&id=' + encodeURIComponent(patientId);
  }
});

// Load patient detail if on patient-detail or patient-edit page
if (window._patientDetailData) {
  (async () => {
    const { patientId, isEditing } = window._patientDetailData;
    try {
      const data = await api('action=patient.get&id=' + encodeURIComponent(patientId));
      const p = data.patient;
      const orders = data.orders || [];
      renderPatientDetailPage(p, orders, isEditing);
    } catch (e) {
      document.getElementById('patient-detail-container').innerHTML = `
        <div class="lg:col-span-3 card p-6">
          <p class="text-red-600 mb-3">Failed to load patient information</p>
          <a href="?page=patients" class="btn">Back to Patients</a>
        </div>
      `;
      console.error('Patient load error:', e);
    }
  })();
}

</script>

    </main>
  </div>
</div>
</body></html>
