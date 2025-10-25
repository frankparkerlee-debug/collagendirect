<?php
declare(strict_types=1);
require __DIR__ . '/../auth.php';

$inTransit = $pdo->query("SELECT id, rx_note_name AS tracking, rx_note_mime AS carrier FROM orders WHERE status='in_transit' AND rx_note_name IS NOT NULL")->fetchAll();

foreach ($inTransit as $o) {
  $carrier = strtolower($o['carrier'] ?? '');
  $tracking= $o['tracking'] ?? '';
  if (!$carrier || !$tracking) continue;

  // TODO: replace with real API calls using your carrier creds
  // Example response stub:
  $status = 'in_transit'; $eta = null; $delivered_at = null;

  // Update
  $map = ['label_created'=>'pending','in_transit'=>'in_transit','out_for_delivery'=>'in_transit','delivered'=>'delivered','exception'=>'pending'];
  $portalStatus = $map[$status] ?? 'in_transit';
  $pdo->prepare("UPDATE orders SET carrier_status=?, carrier_eta=?, status=?, delivered_at=IF(? IS NOT NULL, ?, delivered_at), updated_at=NOW() WHERE id=?")
      ->execute([$status, $eta, $portalStatus, $delivered_at, $delivered_at, $o['id']]);
}
echo "ok";
