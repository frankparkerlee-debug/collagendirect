<?php
declare(strict_types=1);
require __DIR__ . '/auth.php'; require_admin();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/shipping.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $id = $_POST['id'] ?? '';
  if ($id === '') { header('Location: /admin/shipments.php'); exit; }

  if (($_POST['action'] ?? '') === 'save_tracking') {
    $tracking = trim((string)($_POST['tracking'] ?? ''));
    $carrier  = $_POST['carrier'] ?: detect_carrier($tracking);

    // Save tracking + inferred status
    $pdo->prepare(
      "UPDATE orders SET rx_note_mime=?, rx_note_name=?, status='in_transit',
         shipped_at = COALESCE(shipped_at, NOW()),
         updated_at = NOW()
       WHERE id=?"
    )->execute([$carrier ?: null, $tracking ?: null, $id]);

    // Optional: attempt status pull (stub now; ready for future API)
    if ($tracking) {
      $trk = fetch_tracking_status($tracking, $carrier);
      if (!empty($trk['status'])) {
        $pdo->prepare("UPDATE orders SET carrier_status=?, carrier_eta=?, status=?,
                         delivered_at = COALESCE(?, delivered_at),
                         updated_at=NOW()
                       WHERE id=?")
            ->execute([
              $trk['status'], $trk['eta'], $trk['status'],
              $trk['delivered_at'], $id
            ]);
      }
    }
  } elseif (($_POST['action'] ?? '') === 'set_status') {
    $pdo->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE id=?")
        ->execute([$_POST['status'] ?? 'pending', $id]);
  }
  header('Location: /admin/shipments.php'); exit;
}

$rows = $pdo->query("
  SELECT o.id, o.product, o.shipping_name, o.shipping_city, o.shipping_state,
         o.rx_note_name, o.rx_note_mime, o.status, o.created_at
  FROM orders o
  ORDER BY o.created_at DESC
  LIMIT 500
")->fetchAll();

include __DIR__ . '/_header.php';
?>
<div class="flex items-center justify-between mb-4">
  <div class="text-xl font-semibold">Manage Shipments</div>
</div>

<div class="bg-white border rounded-2xl overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="border-b">
      <tr class="text-left">
        <th class="py-2">ID</th>
        <th class="py-2">Item</th>
        <th class="py-2">Deliver To</th>
        <th class="py-2">Carrier</th>
        <th class="py-2">Tracking</th>
        <th class="py-2">Quick Track</th>
        <th class="py-2">Status</th>
        <th class="py-2">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $rawTrack   = (string)($r['rx_note_name'] ?? '');
        $displayVal = looks_like_filename($rawTrack) ? '' : $rawTrack; // avoid showing filenames in field
        $carrier    = $r['rx_note_mime'] ?: detect_carrier($displayVal);
        $trackHref  = $displayVal ? tracking_url($displayVal, $carrier ?: null) : '';
      ?>
      <tr class="border-b hover:bg-slate-50">
        <td class="py-3">#<?=e($r['id'])?></td>
        <td class="py-3"><?=e($r['product'] ?? '')?></td>
        <td class="py-3"><?=e(($r['shipping_name'] ?? '').' — '.($r['shipping_city'] ?? '').', '.($r['shipping_state'] ?? ''))?></td>
        <td class="py-3">
          <form method="post" class="inline"><?=csrf_field()?>
            <input type="hidden" name="action" value="save_tracking">
            <input type="hidden" name="id" value="<?=e($r['id'])?>">
            <select name="carrier" class="border rounded px-2 py-1 text-xs">
              <option value="">Auto-detect</option>
              <option value="ups"   <?=($carrier==='ups'?'selected':'')?>>UPS</option>
              <option value="fedex" <?=($carrier==='fedex'?'selected':'')?>>FedEx</option>
              <option value="usps"  <?=($carrier==='usps'?'selected':'')?>>USPS</option>
            </select>
        </td>
        <td class="py-3">
            <input name="tracking" class="border rounded px-2 py-1 text-xs w-44" placeholder="Tracking number" value="<?=e($displayVal)?>">
            <button class="ml-2 text-brand text-xs">Save</button>
          </form>
        </td>
        <td class="py-3">
          <?php if ($trackHref): ?>
            <a class="text-brand underline text-xs" href="<?=e($trackHref)?>" target="_blank">Track</a>
          <?php else: ?>
            <span class="text-slate-400 text-xs">—</span>
          <?php endif; ?>
        </td>
        <td class="py-3">
          <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold <?=($r['status']==='delivered'?'bg-teal-100 text-teal-700':($r['status']==='in_transit'?'bg-amber-100 text-amber-800':'bg-gray-100 text-gray-700'))?>">
            <?=e(ucwords(str_replace('_',' ',$r['status'] ?? 'pending')))?>
          </span>
        </td>
        <td class="py-3">
          <form method="post" class="inline"><?=csrf_field()?>
            <input type="hidden" name="action" value="set_status">
            <input type="hidden" name="id" value="<?=e($r['id'])?>">
            <select name="status" class="border rounded px-2 py-1 text-xs">
              <option value="pending"     <?=($r['status']==='pending'?'selected':'')?>>Pending</option>
              <option value="in_transit"  <?=($r['status']==='in_transit'?'selected':'')?>>In Transit</option>
              <option value="delivered"   <?=($r['status']==='delivered'?'selected':'')?>>Delivered</option>
            </select>
            <button class="ml-2 text-slate-700 text-xs">Update</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
