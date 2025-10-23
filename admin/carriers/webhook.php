<?php
declare(strict_types=1);
require __DIR__ . '/../auth.php'; // gives $pdo and session, but webhooks won’t have session
/* If you need a shared secret, validate here: */
$secret = $_GET['secret'] ?? '';
if ($secret !== 'replace-with-strong-shared-secret') { http_response_code(401); exit('unauthorized'); }

/* Expect JSON like:
{
  "carrier":"ups","tracking":"1Z...","status":"delivered",
  "eta":"2025-10-18T18:30:00Z","delivered_at":"2025-10-18T17:12:00Z"
}
*/
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$carrier  = strtolower($data['carrier'] ?? '');
$tracking = trim((string)($data['tracking'] ?? ''));
$status   = strtolower($data['status'] ?? '');
$eta      = $data['eta'] ?? null;
$deliv    = $data['delivered_at'] ?? null;

if (!$carrier || !$tracking) { http_response_code(400); exit('bad payload'); }

$map = ['label_created'=>'pending','in_transit'=>'in_transit','out_for_delivery'=>'in_transit','delivered'=>'delivered','exception'=>'pending'];
$portalStatus = $map[$status] ?? 'in_transit';

$sql = "UPDATE orders SET rx_note_mime=?, carrier_status=?, carrier_eta=?, status=?, delivered_at=IF(? IS NOT NULL, ?, delivered_at), updated_at=NOW()
        WHERE rx_note_name=? LIMIT 1";
$pdo->prepare($sql)->execute([$carrier, $status, $eta, $portalStatus, $deliv, $deliv, $tracking]);

echo "ok";
