<?php
// order_notify.php — Sends order PDFs to manufacturer/DME
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/sg_curl.php'; // your SendGrid wrapper

function fetchOrder(PDO $pdo, int $id): ?array {
  $st = $pdo->prepare("
    SELECT o.*, p.first_name, p.last_name, p.address, p.city, p.state, p.zip, 
           p.phone, p.email, d.name AS doctor_name, d.npi
      FROM orders o
      JOIN patients p ON o.patient_id = p.id
      JOIN doctors d ON o.doctor_id = d.id
     WHERE o.id = ?");
  $st->execute([$id]);
  return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$orderId = (int)($_POST['order_id'] ?? 0);
if (!$orderId) json_out(400, ['error'=>'Missing order_id']);

$order = fetchOrder($pdo, $orderId);
if (!$order) json_out(404, ['error'=>'Order not found']);

// === Build PDF summary ===
$pdfName = sprintf('%sOrder.pdf', strtoupper(substr($order['first_name'],0,1).$order['last_name']));
$pdfPath = __DIR__ . "/../../uploads/orders/$pdfName";

require_once __DIR__ . '/fpdf/fpdf.php';
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,'Patient Order Summary',0,1);
$pdf->SetFont('Arial','',12);
foreach ($order as $k=>$v) if(!is_numeric($k)) $pdf->Cell(0,8,ucfirst(str_replace('_',' ',$k)).": $v",0,1);
$pdf->Output('F',$pdfPath);

// === Email setup ===
$to = [
  ['email'=>'parker@collagendirect.health','name'=>'CollagenDirect ORder'],
  ['email'=>'plee@tryarti.com','name'=>'DME Fulfillment']
];
$subject = "New Patient Order — {$order['first_name']} {$order['last_name']}";
$html = "
  <p>A new order has been submitted by <strong>{$order['doctor_name']}</strong>.</p>
  <ul>
    <li><b>Patient:</b> {$order['first_name']} {$order['last_name']}</li>
    <li><b>Product:</b> {$order['product']}</li>
    <li><b>Quantity:</b> {$order['quantity']}</li>
    <li><b>Ship To:</b> {$order['address']}, {$order['city']} {$order['state']} {$order['zip']}</li>
  </ul>
  <p>Full details attached as PDF.</p>
";
$attachments = [
  [
    'content' => base64_encode(file_get_contents($pdfPath)),
    'filename'=> basename($pdfPath),
    'type'    => 'application/pdf',
    'disposition'=>'attachment'
  ]
];

sg_send($to, $subject, $html, $attachments);
json_out(200, ['status'=>'sent']);
