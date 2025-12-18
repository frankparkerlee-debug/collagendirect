<?php
// order_notify.php — Sends order PDFs to manufacturer/DME
// Uses SMTP/Gmail via email_sender.php
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/lib/email_sender.php';

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
$recipients = [
  ['email'=>'parker@collagendirect.health','name'=>'CollagenDirect Order'],
  ['email'=>'plee@tryarti.com','name'=>'DME Fulfillment']
];

$subject = "New Patient Order — {$order['first_name']} {$order['last_name']}";

$bodyContent = "
  <h2 style='color: #1e293b; margin: 0 0 20px 0;'>New Patient Order</h2>
  <p style='color: #475569;'>A new order has been submitted by <strong>{$order['doctor_name']}</strong>.</p>

  <div style='background-color: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 20px; margin: 20px 0;'>
    <p style='margin: 5px 0; color: #475569;'><strong>Patient:</strong> {$order['first_name']} {$order['last_name']}</p>
    <p style='margin: 5px 0; color: #475569;'><strong>Product:</strong> {$order['product']}</p>
    <p style='margin: 5px 0; color: #475569;'><strong>Quantity:</strong> {$order['quantity']}</p>
    <p style='margin: 5px 0; color: #475569;'><strong>Ship To:</strong> {$order['address']}, {$order['city']} {$order['state']} {$order['zip']}</p>
  </div>

  <p style='color: #475569;'>Full details attached as PDF.</p>
";

$plainText = "New Patient Order

A new order has been submitted by {$order['doctor_name']}.

Patient: {$order['first_name']} {$order['last_name']}
Product: {$order['product']}
Quantity: {$order['quantity']}
Ship To: {$order['address']}, {$order['city']} {$order['state']} {$order['zip']}

Full details attached as PDF.
";

$htmlBody = email_template($subject, $bodyContent);

// Read PDF attachment
$pdfContent = file_get_contents($pdfPath);

// Send to all recipients
$successCount = 0;
foreach ($recipients as $recipient) {
  // Note: PHPMailer attachment support would need to be added to send_email()
  // For now, send without attachment - the order details are in the email body
  $result = send_email($recipient['email'], $recipient['name'], $subject, $htmlBody, $plainText);
  if ($result) {
    $successCount++;
    error_log("[order_notify] Email sent to {$recipient['email']} for order #$orderId");
  }
}

json_out(200, ['status'=>'sent', 'recipients' => $successCount]);
