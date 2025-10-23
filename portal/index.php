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
    $phone=$_POST['phone']??null; $email=$_POST['email']??null;
    $address=$_POST['address']??null; $city=$_POST['city']??null; $state=$_POST['state']??null; $zip=$_POST['zip']??null;

    if($first===''||$last==='') jerr('First and last name are required');
    if(!validPhone($phone)) jerr('Phone must be 10 digits');
    if(!validEmail($email)) jerr('Invalid email');

    if ($pid===''){
      if($mrn===''){ $mrn = 'CD-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(2)),0,4)); }
      $pid=bin2hex(random_bytes(16));
      $st=$pdo->prepare("INSERT INTO patients
        (id,user_id,first_name,last_name,dob,mrn,city,state,phone,email,address,zip,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
      $st->execute([$pid,$userId,$first,$last,$dob,$mrn,$city,$state,$phone,$email,$address,$zip]);
    } else {
      $st=$pdo->prepare("UPDATE patients SET first_name=?,last_name=?,dob=?,mrn=?,city=?,state=?,phone=?,email=?,address=?,zip=?,updated_at=NOW()
                         WHERE id=? AND user_id=?");
      $st->execute([$first,$last,$dob,$mrn,$city,$state,$phone,$email,$address,$zip,$pid,$userId]);
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
    $rows=$pdo->query("SELECT id,name,size,uom,price_admin AS price FROM products WHERE active=1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
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

      // Insurance gate: patient-level ID + INS + AOB required
      if($payment_type==='insurance'){
        if(empty($p['id_card_path']) || empty($p['ins_card_path'])){ $pdo->rollBack(); jerr('Patient ID and Insurance Card must be on file at the patient level.'); }
        if(empty($p['aob_path'])){ $pdo->rollBack(); jerr('Assignment of Benefits (AOB) must be signed.'); }
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
      $icd10_primary   = trim((string)($_POST['icd10_primary']??''));
      $icd10_secondary = trim((string)($_POST['icd10_secondary']??''));
      if($icd10_primary===''){ $pdo->rollBack(); jerr('Primary diagnosis (ICD-10) is required.'); }

      $wlen = $_POST['wound_length_cm']!=='' ? (float)$_POST['wound_length_cm'] : null;
      $wwid = $_POST['wound_width_cm']!==''  ? (float)$_POST['wound_width_cm']  : null;
      $wdep = $_POST['wound_depth_cm']!==''  ? (float)$_POST['wound_depth_cm']  : null;
      if($wlen===null || $wwid===null){ $pdo->rollBack(); jerr('Wound length and width are required.'); }

      $wtype=(string)($_POST['wound_type']??'');
      $wstage=(string)($_POST['wound_stage']??'');
      $last_eval = $_POST['last_eval_date'] ?? null; if(!$last_eval){ $pdo->rollBack(); jerr('Date of last evaluation is required.'); }

      $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
      $freq_per_week = max(0,(int)($_POST['frequency_per_week']??0));
      $qty_per_change = max(1,(int)($_POST['qty_per_change']??1));
      $duration_days = max(1,(int)($_POST['duration_days']??30));
      $refills_allowed = max(0,(int)($_POST['refills_allowed']??0));
      $additional_instructions = trim((string)($_POST['additional_instructions']??''));
      if($freq_per_week<=0){ $pdo->rollBack(); jerr('Frequency per week is required.'); }

      $oid=bin2hex(random_bytes(16));
      $ins=$pdo->prepare("INSERT INTO orders
        (id,patient_id,user_id,product,product_id,product_price,status,shipments_remaining,delivery_mode,payment_type,
         wound_location,wound_laterality,wound_notes,
         shipping_name,shipping_phone,shipping_address,shipping_city,shipping_state,shipping_zip,
         sign_name,sign_title,signed_at,created_at,updated_at,
         icd10_primary,icd10_secondary,wound_length_cm,wound_width_cm,wound_depth_cm,
         wound_type,wound_stage,last_eval_date,start_date,frequency_per_week,qty_per_change,duration_days,refills_allowed,additional_instructions,
         cpt)
        VALUES (?,?,?,?,?,?,?,?,?,?,
                ?,?,?,
                ?,?,?,?,?,?,
                ?,?,NOW(),NOW(),NOW(),
                ?,?,?,?,?,
                ?,?,?,?,?,?,?,?,?,
                ?)");
      $ins->execute([
        $oid,$pid,$userId,$prod['name'],$prod['id'],$prod['price_admin'],'submitted',0,$delivery_mode,$payment_type, // shipments_remaining=0
        ($_POST['wound_location']??null),($_POST['wound_laterality']??null),($_POST['wound_notes']??null),
        (string)$ship_name,(string)$ship_phone,(string)$ship_addr,(string)$ship_city,(string)$ship_state,(string)$ship_zip,
        $sign_name,$sign_title,
        $icd10_primary,$icd10_secondary,$wlen,$wwid,$wdep,
        $wtype,$wstage,$last_eval,$start_date,$freq_per_week,$qty_per_change,$duration_days,$refills_allowed,$additional_instructions,
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  /* Design Tokens - shadcn inspired */
  :root {
    --brand: #10b981;
    --brand-dark: #059669;
    --brand-light: #d1fae5;
    --ink: #0f172a;
    --muted: #64748b;
    --border: #e2e8f0;
    --ring: rgba(16, 185, 129, 0.2);
    --radius: 0.5rem;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    --info: #3b82f6;
  }

  html, body {
    font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    color: var(--ink);
    -webkit-font-smoothing: antialiased;
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
    padding: 0.5rem 1rem;
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

  /* Badge/Pill Component */
  .badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 500;
    border-radius: 9999px;
    border: 1px solid transparent;
  }

  .badge-success {
    background: #d1fae5;
    color: #065f46;
    border-color: #a7f3d0;
  }

  .badge-warning {
    background: #fef3c7;
    color: #92400e;
    border-color: #fde68a;
  }

  .badge-error {
    background: #fee2e2;
    color: #991b1b;
    border-color: #fecaca;
  }

  .badge-info {
    background: #dbeafe;
    color: #1e40af;
    border-color: #bfdbfe;
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
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
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
</style>
</head>
<body class="min-h-screen bg-white">
<header class="sticky top-0 z-10 bg-white/90 backdrop-blur border-b border-slate-200">
  <div class="px-4 lg:px-6 xl:px-8 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <img src="/assets/collagendirect.png" class="h-8 w-auto" alt="CollagenDirect">
      <span class="hidden sm:inline text-sm text-slate-500">Physician Portal</span>
    </div>
    <nav class="sidenav flex gap-1">
      <a class="<?php echo $page==='dashboard'?'active':''; ?>" href="?page=dashboard">Dashboard</a>
      <a class="<?php echo $page==='patients'?'active':''; ?>" href="?page=patients">Patients</a>
      <a class="<?php echo $page==='orders'?'active':''; ?>" href="?page=orders">Orders</a>
      <a class="<?php echo $page==='admin'?'active':''; ?>" href="?page=admin">Admin</a>
      <a href="?page=logout">Logout</a>
    </nav>
  </div>
</header>

<main class="px-4 lg:px-6 xl:px-8 py-6 space-y-8">
<?php if ($page==='dashboard'): ?>
  <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="card p-5"><div class="text-sm text-slate-500">Active Orders</div><div id="m-active" class="text-3xl font-semibold mt-1">-</div></div>
    <div class="card p-5"><div class="text-sm text-slate-500">Pending Approvals</div><div id="m-pending" class="text-3xl font-semibold mt-1">-</div></div>
    <div class="card p-5"><div class="text-sm text-slate-500">Total Patients</div><div id="m-patients" class="text-3xl font-semibold mt-1">-</div></div>
  </section>

  <section class="card p-5">
    <div class="flex items-center gap-3 mb-4">
      <h2 class="text-lg font-semibold">Patients</h2>
      <input id="patient-search" class="ml-auto w-full sm:w-96" placeholder="Search by name, phone, email, MRN…">
      <button class="btn btn-primary" id="btn-new-order" type="button">New Order</button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Name</th><th class="py-2">DOB</th><th class="py-2">Phone</th><th class="py-2">Email</th>
            <th class="py-2">City/State</th><th class="py-2">Status</th><th class="py-2">Bandage Count</th><th class="py-2">Action</th>
          </tr>
        </thead>
        <tbody id="patients-tbody"></tbody>
      </table>
    </div>
  </section>

<?php elseif ($page==='patients'): ?>
  <section class="card p-5">
    <div class="flex items-center gap-3 mb-4">
      <h2 class="text-lg font-semibold">Manage Patients</h2>
      <input id="q" class="ml-auto w-full sm:w-96" placeholder="Search name, phone, email, MRN…">
      <button class="btn btn-primary" id="btn-new-order" type="button">New Order</button>
    </div>
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
  <section class="card p-5">
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
      <button class="btn btn-primary" id="btn-new-order2" type="button">New Order</button>
    </div>
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

<?php else: ?>
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
            <input id="np-email" class="md:col-span-2" placeholder="Email">
            <input id="np-address" class="md:col-span-2" placeholder="Street address">
            <input id="np-city"  placeholder="City">
            <select id="np-state">
              <option value="">State</option>
              <?php foreach(usStates() as $s) echo "<option>$s</option>"; ?>
            </select>
            <input id="np-zip" placeholder="ZIP">
          </div>
          <button id="btn-create-patient" type="button" class="btn mt-2">Save Patient &amp; Use</button>
          <div id="np-hint" class="text-xs text-slate-500 mt-1"></div>
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

        <div>
          <label class="text-sm">Primary ICD-10 <span class="text-red-600">*</span></label>
          <input id="icd10-primary" class="w-full" placeholder="e.g., L97.412">
        </div>
        <div>
          <label class="text-sm">Secondary ICD-10 (optional)</label>
          <input id="icd10-secondary" class="w-full" placeholder="">
        </div>

        <div>
          <label class="text-sm">Wound Length (cm) <span class="text-red-600">*</span></label>
          <input id="wlen" type="number" step="0.01" min="0" class="w-full">
        </div>
        <div>
          <label class="text-sm">Wound Width (cm) <span class="text-red-600">*</span></label>
          <input id="wwid" type="number" step="0.01" min="0" class="w-full">
        </div>
        <div>
          <label class="text-sm">Wound Depth (cm)</label>
          <input id="wdep" type="number" step="0.01" min="0" class="w-full">
        </div>
        <div>
          <label class="text-sm">Wound Type</label>
          <select id="wtype" class="w-full">
            <option value="">Select…</option>
            <option>Diabetic ulcer</option><option>Venous stasis ulcer</option>
            <option>Arterial ulcer</option><option>Pressure ulcer</option>
            <option>Traumatic</option><option>Other</option>
          </select>
        </div>
        <div>
          <label class="text-sm">Wound Stage</label>
          <select id="wstage" class="w-full">
            <option value="">N/A</option><option>I</option><option>II</option><option>III</option><option>IV</option>
          </select>
        </div>
        <div>
          <label class="text-sm">Date of Last Evaluation <span class="text-red-600">*</span></label>
          <input id="last-eval" type="date" class="w-full">
        </div>

        <div>
          <label class="text-sm">Start Date</label>
          <input id="start-date" type="date" class="w-full">
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
          <input id="addl-instr" class="w-full" placeholder="e.g., Saline cleanse, secondary dressing">
        </div>

        <div class="md:col-span-2">
          <label class="text-sm">Wound Location</label>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <select id="ord-wloc" class="w-full">
              <option value="">Select…</option>
              <option>Foot — Plantar</option><option>Foot — Dorsal</option><option>Heel</option><option>Ankle</option>
              <option>Lower Leg — Medial</option><option>Lower Leg — Lateral</option><option>Knee</option>
              <option>Thigh</option><option>Hip</option><option>Buttock</option><option>Sacrum/Coccyx</option>
              <option>Abdomen</option><option>Groin</option>
              <option>Upper Arm</option><option>Forearm</option><option>Hand — Dorsal</option><option>Hand — Palmar</option>
              <option>Elbow</option><option>Shoulder</option><option>Back — Upper</option><option>Back — Lower</option>
              <option>Neck</option><option>Face/Scalp</option><option>Other</option>
            </select>
            <input id="ord-wloc-other" class="w-full hidden" placeholder="If Other, describe">
          </div>
        </div>

        <div>
          <label class="text-sm">Laterality</label>
          <input id="ord-wlat" class="w-full">
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

/* Metrics (dashboard) */
if (<?php echo json_encode($page==='dashboard'); ?>) {
  (async()=>{ try{const m=await api('action=metrics'); $('#m-patients').textContent=m.metrics.patients; $('#m-pending').textContent=m.metrics.pending; $('#m-active').textContent=m.metrics.active_orders;}catch(e){} })();
}

/* Helpers */
function pill(s){ if(!s) return '<span class="pill">—</span>'; const c={active:'pill pill--active',approved:'pill pill--pending',submitted:'pill pill--pending',pending:'pill pill--pending',shipped:'pill',stopped:'pill pill--stopped'}[(s||'').toLowerCase()]||'pill'; return `<span class="${c}" style="text-transform:capitalize">${s}</span>`; }
function fmt(d){ if(!d) return '—'; return (''+d).slice(0,10); }
function esc(s){ return (s??'').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

/* DASHBOARD table (view-only) */
if (<?php echo json_encode($page==='dashboard'); ?>){
  let rows=[];
  async function loadPatients(q=''){ const res=await api('action=patients&limit=50&q='+encodeURIComponent(q)); rows=res.rows||[]; render(); }
  function render(){
    const tb=$('#patients-tbody'); tb.innerHTML='';
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
          <td class="py-2"><button class="btn" type="button" data-acc="${p.id}">View</button></td>
        </tr>`);
    }
  }
  $('#patient-search').addEventListener('input',e=>loadPatients(e.target.value.trim()));
  $('#btn-new-order').addEventListener('click',()=>openOrderDialog());
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
  document.getElementById('btn-new-order').addEventListener('click',()=>openOrderDialog());
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
  $('#btn-new-order2').addEventListener('click',()=>openOrderDialog());
  tb.addEventListener('click',async(e)=>{
    const b=e.target.closest('button'); if(!b) return;
    if(b.dataset.stop){ openStopDialog(b.dataset.stop, loadOrders); }
    if(b.dataset.restart){ openRestartDialog(b.dataset.restart, loadOrders); }
  });
  loadOrders();
}

/* ADMIN: change password */
if (<?php echo json_encode($page==='admin'); ?>){
  $('#btn-pw').onclick=async()=>{
    const r=await fetch('?action=user.change_password',{method:'POST',body:fd({current:$('#pw-cur').value,new:$('#pw-new').value,confirm:$('#pw-con').value})});
    const j=await r.json(); $('#pw-hint').textContent = j.ok ? 'Password updated.' : (j.error||'Failed to update');
    if(j.ok){ $('#pw-cur').value=''; $('#pw-new').value=''; $('#pw-con').value=''; }
  };
}

/* Global click -> accordion */
document.addEventListener('click', async (e)=>{
  const btn = e.target.closest('[data-acc]');
  if (!btn) return;
  e.preventDefault();
  const pid = btn.getAttribute('data-acc');
  const row = btn.closest('tr');
  try { await toggleAccordion(row, pid, <?php echo json_encode($page); ?>); }
  catch(err){ alert('Server error'); }
});

/* Accordion details */
async function toggleAccordion(rowEl, patientId, page){
  const next = rowEl.nextElementSibling;
  if (next && next.classList.contains('acc-row')) { next.remove(); return; }
  rowEl.parentElement.querySelectorAll('.acc-row').forEach(el=>el.remove());

  const data = await api('action=patient.get&id='+encodeURIComponent(patientId));
  const p = data.patient; const orders = data.orders||[];
  const editable = (page==='patients');

  const detailsBlock = editable
  ? `
    <div class="flex items-center justify-between mb-3">
      <h4 class="font-semibold">Patient Details</h4>
      <div class="flex gap-2">
        <button class="btn" type="button" data-p-save="${esc(p.id)}">Save</button>
        <button class="btn" type="button" data-p-del="${esc(p.id)}">Delete</button>
      </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div><label class="text-xs">First</label><input class="w-full" id="acc-first-${esc(p.id)}" value="${esc(p.first_name||'')}"></div>
      <div><label class="text-xs">Last</label><input class="w-full" id="acc-last-${esc(p.id)}" value="${esc(p.last_name||'')}"></div>
      <div><label class="text-xs">DOB</label><input type="date" class="w-full" id="acc-dob-${esc(p.id)}" value="${esc(p.dob||'')}"></div>
      <div><label class="text-xs">MRN</label><input class="w-full" id="acc-mrn-${esc(p.id)}" value="${esc(p.mrn||'')}"></div>
      <div><label class="text-xs">Phone</label><input maxlength="10" class="w-full" id="acc-phone-${esc(p.id)}" value="${esc(p.phone||'')}"></div>
      <div><label class="text-xs">Email</label><input class="w-full" id="acc-email-${esc(p.id)}" value="${esc(p.email||'')}"></div>
      <div class="sm:col-span-2"><label class="text-xs">Address</label><input class="w-full" id="acc-address-${esc(p.id)}" value="${esc(p.address||'')}"></div>
      <div><label class="text-xs">City</label><input class="w-full" id="acc-city-${esc(p.id)}" value="${esc(p.city||'')}"></div>
      <div><label class="text-xs">State</label>
        <select class="w-full" id="acc-state-${esc(p.id)}">
          <option value="">State</option>
          ${<?php echo json_encode(usStates()); ?>.map(s=>`<option ${p.state===s?'selected':''}>${s}</option>`).join('')}
        </select>
      </div>
      <div><label class="text-xs">ZIP</label><input class="w-full" id="acc-zip-${esc(p.id)}" value="${esc(p.zip||'')}"></div>
    </div>
    <div class="mt-4 pt-4 border-t">
      <h5 class="font-semibold text-sm mb-3">Required Documents for Insurance Orders</h5>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="text-xs">ID Card / Driver's License ${p.id_card_path ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">*</span>'}</label>
          ${p.id_card_path ? `<div class="text-xs text-slate-600 mb-1">Current: <a href="${esc(p.id_card_path)}" target="_blank" class="underline">${esc(p.id_card_name || 'View')}</a></div>` : ''}
          <input type="file" class="w-full text-sm" data-upload-id="${esc(p.id)}" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic" onchange="uploadPatientFile('${esc(p.id)}', 'id', this.files[0])">
        </div>
        <div>
          <label class="text-xs">Insurance Card ${p.ins_card_path ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">*</span>'}</label>
          ${p.ins_card_path ? `<div class="text-xs text-slate-600 mb-1">Current: <a href="${esc(p.ins_card_path)}" target="_blank" class="underline">${esc(p.ins_card_name || 'View')}</a></div>` : ''}
          <input type="file" class="w-full text-sm" data-upload-ins="${esc(p.id)}" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic" onchange="uploadPatientFile('${esc(p.id)}', 'ins', this.files[0])">
        </div>
      </div>
      <div class="mt-3">
        <label class="text-xs">Assignment of Benefits (AOB) ${p.aob_path ? '<span class="text-green-600">✓ Signed</span>' : '<span class="text-red-600">* Required</span>'}</label>
        ${p.aob_path ? `<div class="text-xs text-slate-600 mb-1">Signed: ${fmt(p.aob_signed_at)}</div>` : ''}
        <button type="button" class="btn text-sm" onclick="generateAOB('${esc(p.id)}')">${p.aob_path ? 'Re-generate' : 'Generate & Sign'} AOB</button>
      </div>
    </div>`
  : `
    <h4 class="font-semibold mb-3">Patient Details</h4>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
      <div><div class="text-slate-500">Name</div><div class="font-semibold">${esc(p.first_name||'')} ${esc(p.last_name||'')}</div></div>
      <div><div class="text-slate-500">DOB</div><div>${fmt(p.dob)}</div></div>
      <div><div class="text-slate-500">MRN</div><div>${esc(p.mrn||'')}</div></div>
      <div><div class="text-slate-500">Phone</div><div>${esc(p.phone||'')}</div></div>
      <div class="sm:col-span-2"><div class="text-slate-500">Address</div><div>${esc(p.address||'')} ${esc(p.city||'')} ${esc(p.state||'')} ${esc(p.zip||'')}</div></div>
    </div>`;

  const acc = document.createElement('tr');
  acc.className = 'acc-row';
  acc.innerHTML = `
    <td class="py-4 px-3 bg-slate-50" colspan="8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="card p-4 lg:col-span-1">${detailsBlock}</div>
        <div class="card p-4 lg:col-span-2">
          <div class="flex items-center justify-between mb-3">
            <h4 class="font-semibold">Orders</h4>
            <button class="btn btn-primary" type="button" data-new-order="${esc(p.id)}">Create Order</button>
          </div>
          <div class="overflow-x-auto">
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
      // Reload the patient detail view to show the new file
      location.reload();
    } else {
      alert('Upload failed: ' + (result.error || 'Unknown error'));
    }
  } catch (error) {
    alert('Upload error: ' + error.message);
    console.error('Full error:', error);
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
      location.reload();
    } else {
      alert('AOB generation failed: ' + (result.error || 'Unknown error'));
    }
  } catch (error) {
    alert('Error: ' + error.message);
  }
}

/* ORDER dialog (chooser + uploads + AOB) */
let _currentPatientId = null;

async function openOrderDialog(preselectId=null){
  const prods=(await api('action=products')).rows||[]; 
  $('#ord-product').innerHTML=prods.map(p=>`<option value="${p.id}">${esc(p.name)}${p.size? ' — '+esc(p.size):''} ${p.uom? '('+esc(p.uom)+')':''}</option>`).join('');

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
    const q=box.value.trim(); hidden.value=''; _currentPatientId=null; if(q.length<2){ list.classList.add('hidden'); return; }
    const r=await api('action=patients&limit=8&q='+encodeURIComponent(q)); const rows=r.rows||[];
    list.innerHTML = (rows.map(p=>`<button type="button" class="w-full text-left px-3 py-2 hover:bg-slate-50" data-p="${p.id}">${esc(p.first_name)} ${esc(p.last_name)} — ${fmt(p.dob)} • ${esc(p.phone||'')}</button>`).join(''))
      + `<div class="border-t my-1"></div><button type="button" id="opt-create" class="w-full text-left px-3 py-2 hover:bg-slate-50">➕ Create new patient “${esc(q)}”</button>`;
    list.classList.remove('hidden');
    list.querySelectorAll('[data-p]').forEach(b=>b.onclick=()=>{ hidden.value=b.dataset.p; _currentPatientId=b.dataset.p; box.value=b.textContent; list.classList.add('hidden'); create.classList.add('hidden'); });
    const add=list.querySelector('#opt-create'); if(add) add.onclick=()=>{ list.classList.add('hidden'); create.classList.remove('hidden'); $('#np-first').focus(); };
  };

  $('#btn-create-patient').onclick=async()=>{
    if(hidden.value){ $('#np-hint').textContent='A patient is already selected.'; return; }
    const first=$('#np-first').value.trim(), last=$('#np-last').value.trim();
    if(!first||!last){ $('#np-hint').textContent='First and last name required.'; return; }
    const r=await fetch('?action=patient.save',{method:'POST',body:fd({
      first_name:first,last_name:last,dob:$('#np-dob').value,
      phone:$('#np-phone').value,email:$('#np-email').value,
      address:$('#np-address').value,city:$('#np-city').value,state:$('#np-state').value,zip:$('#np-zip').value
    })});
    const j=await r.json(); if(!j.ok){ $('#np-hint').textContent=j.error||'Failed to create'; return; }
    hidden.value=j.id; _currentPatientId=j.id; box.value=`${first} ${last} (MRN ${j.mrn})`; $('#np-hint').textContent='Patient created and selected.'; create.classList.add('hidden');
  };

  // reset e-sign & clinical fields
  document.querySelector('input[name="deliver"][value="patient"]').checked=true;
  document.getElementById('office-addr').classList.add('hidden');
  $('#ack-sig').checked=false; $('#sign-name').value=''; $('#sign-title').value='';
  $('#icd10-primary').value=''; $('#icd10-secondary').value='';
  $('#wlen').value=''; $('#wwid').value=''; $('#wdep').value='';
  $('#wtype').value=''; $('#wstage').value='';
  $('#last-eval').value=''; $('#start-date').value='';
  $('#freq-week').value='3'; $('#qty-change').value='1'; $('#duration-days').value='30'; $('#refills').value='0';
  $('#addl-instr').value='';
  $('#ord-wlat').value=''; $('#ord-wloc').value=''; $('#ord-wloc-other').value=''; $('#ord-wloc-other').classList.add('hidden'); $('#ord-notes').value='';
  $('#file-rx').value=''; $('#aob-hint').textContent='';

  // Submit
  $('#btn-order-create').onclick=async()=>{
    const btn=$('#btn-order-create'); if(btn.disabled) return; btn.disabled=true; btn.textContent='Submitting…';
    try{
      const pid=$('#chooser-id').value || _currentPatientId; if(!pid){ alert('Select or create a patient'); return; }
      if(!$('#ack-sig').checked){ alert('Please acknowledge the e-signature statement.'); return; }
      const body=new FormData();
      body.append('patient_id', pid);
      body.append('product_id', $('#ord-product').value);
      body.append('payment_type', document.querySelector('input[name="paytype"]:checked').value);

      body.append('icd10_primary', $('#icd10-primary').value);
      body.append('icd10_secondary', $('#icd10-secondary').value);
      body.append('wound_length_cm', $('#wlen').value);
      body.append('wound_width_cm',  $('#wwid').value);
      body.append('wound_depth_cm',  $('#wdep').value);
      body.append('wound_type', $('#wtype').value);
      body.append('wound_stage', $('#wstage').value);
      body.append('last_eval_date', $('#last-eval').value);
      body.append('start_date', $('#start-date').value);
      body.append('frequency_per_week', $('#freq-week').value);
      body.append('qty_per_change', $('#qty-change').value);
      body.append('duration_days', $('#duration-days').value);
      body.append('refills_allowed', $('#refills').value);
      body.append('additional_instructions', $('#addl-instr').value);

      const wl = $('#ord-wloc').value==='Other' ? $('#ord-wloc-other').value : $('#ord-wloc').value;
      body.append('wound_location', wl);
      body.append('wound_laterality', $('#ord-wlat').value);
      body.append('wound_notes', $('#ord-notes').value);
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

  document.getElementById('dlg-order').showModal();
}

/* Stop/Restart fallback */
document.addEventListener('click', (e)=>{
  const stopBtn=e.target.closest('[data-stop]'); if(stopBtn) openStopDialog(stopBtn.dataset.stop, ()=>location.reload());
  const reBtn=e.target.closest('[data-restart]'); if(reBtn) openRestartDialog(reBtn.dataset.restart, ()=>location.reload());
});
</script>

</body></html>
