<?php
// /public/admin/orders.php — full functionality, defensive
declare(strict_types=1);
$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
require_once __DIR__.'/db.php';
$auth = __DIR__.'/auth.php'; if (is_file($auth)) { require_once $auth; if (function_exists('require_admin')) require_admin(); }

/* Optional shipping helpers */
$shipLib = __DIR__.'/lib/shipping.php';
if (is_file($shipLib)) require_once $shipLib;
if (!function_exists('detect_carrier')) { // tiny fallback
  function detect_carrier(string $t): ?string {
    $t = strtoupper(trim($t));
    if (preg_match('/^1Z[0-9A-Z]{16}$/',$t)) return 'ups';
    if (preg_match('/^\d{12}$|^\d{15}$|^\d{20}$|^\d{22}$/',$t)) return 'fedex';
    if (preg_match('/^\d{20,22}$|^\d{26,34}$|^[A-Z]{2}\d{9}US$/',$t)) return 'usps';
    return null;
  }
}
if (!function_exists('fetch_tracking_status')) { function fetch_tracking_status(string $t, ?string $c=null){ return ['carrier'=>$c?:detect_carrier($t),'status'=>null,'eta'=>null,'delivered_at'=>null,'raw'=>null]; } }

/* Catalog (optional) + frequency */
try { $products = $pdo->query("SELECT id,name,size,sku,price_admin FROM products WHERE active=1 ORDER BY name,size")->fetchAll(); }
catch(Throwable $e){ $products = []; }
$freqOptions = ['Daily','Every other Day','Weekly'];

/* Actions */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf();
  $action = $_POST['action'] ?? ''; $id = $_POST['id'] ?? '';
  if ($id && $action==='approve') {
    $pdo->prepare("UPDATE orders SET status='approved', updated_at=NOW() WHERE id=?")->execute([$id]);
  } elseif ($id && $action==='reject') {
    $pdo->prepare("UPDATE orders SET status='rejected', updated_at=NOW() WHERE id=?")->execute([$id]);
  } elseif ($id && $action==='ship') {
    $tracking = trim((string)($_POST['tracking']??'')); $carrier = $_POST['carrier'] ?: detect_carrier($tracking);
    $pdo->prepare("UPDATE orders SET
      shipping_name=:n, shipping_phone=:ph, shipping_address=:a, shipping_city=:c, shipping_state=:s, shipping_zip=:z,
      rx_note_mime=:carrier, rx_note_name=:tracking, status='in_transit',
      shipped_at=IF(shipped_at IS NULL, NOW(), shipped_at), updated_at=NOW()
    WHERE id=:id")->execute([
      'n'=>$_POST['shipping_name']??null,'ph'=>$_POST['shipping_phone']??null,'a'=>$_POST['shipping_address']??null,
      'c'=>$_POST['shipping_city']??null,'s'=>$_POST['shipping_state']??null,'z'=>$_POST['shipping_zip']??null,
      'carrier'=>$carrier,'tracking'=>$tracking,'id'=>$id
    ]);
    if ($tracking) {
      $trk = fetch_tracking_status($tracking,$carrier);
      if (!empty($trk['status'])) {
        $pdo->prepare("UPDATE orders SET carrier_status=?, carrier_eta=?, status=?, delivered_at=IF(? IS NOT NULL, ?, delivered_at), updated_at=NOW() WHERE id=?")
            ->execute([$trk['status'],$trk['eta'],$trk['status'],$trk['delivered_at'],$trk['delivered_at'],$id]);
      }
    }
  } elseif ($id && $action==='edit_order') {
    $qtySql = ""; $params = [
      'pid'=>($_POST['product_id'] ?: null),
      'pname'=>($_POST['product_label'] ?: ($_POST['product_text'] ?? null)),
      'freq'=>($_POST['frequency'] ?? null),
      'price'=>(float)($_POST['product_price'] ?? 0),
      'dmode'=>($_POST['delivery_mode'] ?? null),
      'id'=>$id
    ];
    // only update quantity if column exists
    try {
      $hasQty = (int)$pdo->query("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='quantity'")->fetch()['c'] > 0;
      if ($hasQty && $_POST['quantity'] !== '') { $qtySql = ", quantity=:qty"; $params['qty'] = (int)$_POST['quantity']; }
    } catch(Throwable $e){}
    $pdo->prepare("UPDATE orders SET product_id=:pid, product=:pname, frequency=:freq, product_price=:price, delivery_mode=:dmode $qtySql, updated_at=NOW() WHERE id=:id")->execute($params);

    // shipping edits
    $pdo->prepare("UPDATE orders SET shipping_name=:n, shipping_phone=:ph, shipping_address=:a, shipping_city=:c, shipping_state=:s, shipping_zip=:z WHERE id=:id")->execute([
      'n'=>$_POST['shipping_name']??null,'ph'=>$_POST['shipping_phone']??null,'a'=>$_POST['shipping_address']??null,
      'c'=>$_POST['shipping_city']??null,'s'=>$_POST['shipping_state']??null,'z'=>$_POST['shipping_zip']??null,'id'=>$id
    ]);

    // patient demo
    if (!empty($_POST['patient_id'])) {
      $pdo->prepare("UPDATE patients SET dob=:dob, insurance_provider=:prov, insurance_member_id=:mid, insurance_group_id=:gid, insurance_payer_phone=:pp WHERE id=:pid")
          ->execute([
            'dob'=>($_POST['pat_dob'] ?: null),'prov'=>($_POST['ins_provider'] ?: null),'mid'=>($_POST['ins_member'] ?: null),
            'gid'=>($_POST['ins_group'] ?: null),'pp'=>($_POST['ins_payer_phone'] ?: null),'pid'=>$_POST['patient_id']
          ]);
    }
  }
  header('Location: /admin/orders.php'); exit;
}

/* List all orders */
$rows = $pdo->query("
  SELECT o.*, p.first_name, p.last_name, p.id AS pid, p.dob,
         p.insurance_provider, p.insurance_member_id, p.insurance_group_id, p.insurance_payer_phone
  FROM orders o LEFT JOIN patients p ON p.id=o.patient_id
  ORDER BY o.created_at DESC LIMIT 1000
")->fetchAll();

$header = __DIR__.'/_header.php'; $footer = __DIR__.'/_footer.php'; $hasLayout=is_file($header)&&is_file($footer);
if ($hasLayout) include $header; else echo '<!doctype html><meta charset="utf-8"><script src="https://cdn.tailwindcss.com"></script><div class="p-6">';
?>
<div class="flex items-center justify-between mb-4"><div class="text-xl font-semibold">Manage Orders</div></div>
<div class="bg-white border rounded-2xl overflow-hidden shadow-soft">
  <table class="w-full text-sm">
    <thead class="border-b">
      <tr class="text-left">
        <th class="py-2">Patient</th>
        <th class="py-2">Order</th>
        <th class="py-2">Product / Frequency</th>
        <th class="py-2">Qty</th>
        <th class="py-2">Status</th>
        <th class="py-2">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr class="border-b hover:bg-slate-50">
        <td class="py-3"><?=e(trim(($r['first_name']??'').' '.($r['last_name']??'')) ?: '—')?></td>
        <td class="py-3">#<?=e($r['id'])?></td>
        <td class="py-3">
          <?php
            $label = $r['product'] ?? '';
            if (!empty($r['product_id'])) {
              foreach ($products as $pr) { if ($pr['id']==$r['product_id']) { $label = $pr['name'].($pr['size']?(' '.$pr['size']):''); break; } }
            }
            echo e(($label ?: '—').' • '.($r['frequency'] ?? ''));
          ?>
        </td>
        <td class="py-3"><?=e(array_key_exists('quantity',$r)?($r['quantity'] ?? 1):1)?></td>
        <td class="py-3">
          <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold
            <?php $s=$r['status']??''; echo $s==='approved'?'bg-green-100 text-green-700':($s==='submitted'||$s==='pending'?'bg-yellow-100 text-yellow-800':($s==='rejected'?'bg-rose-100 text-rose-700':($s==='in_transit'?'bg-amber-100 text-amber-800':($s==='delivered'?'bg-teal-100 text-teal-700':'bg-gray-100 text-gray-700')))); ?>">
            <?=e(ucwords(str_replace('_',' ',$s ?: 'unknown')))?>
          </span>
        </td>
        <td class="py-3">
          <!-- Approve / Reject -->
          <form method="post" class="inline"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($r['id'])?>"><input type="hidden" name="action" value="approve"><button class="text-brand hover:underline">Approve</button></form>
          <form method="post" class="inline ml-2"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($r['id'])?>"><input type="hidden" name="action" value="reject"><button class="text-rose-600 hover:underline">Reject</button></form>

          <!-- Ship -->
          <details class="inline-block ml-3">
            <summary class="cursor-pointer text-slate-700 hover:underline inline">Ship</summary>
            <form method="post" class="mt-2 p-3 bg-slate-50 border rounded">
              <?=csrf_field()?><input type="hidden" name="id" value="<?=e($r['id'])?>"/><input type="hidden" name="action" value="ship"/>
              <div class="grid grid-cols-2 gap-2">
                <input class="border rounded px-2 py-1" name="shipping_name"   placeholder="Recipient Name"  value="<?=e($r['shipping_name'] ?? '')?>"/>
                <input class="border rounded px-2 py-1" name="shipping_phone"  placeholder="Recipient Phone" value="<?=e($r['shipping_phone'] ?? '')?>"/>
                <input class="border rounded px-2 py-1 col-span-2" name="shipping_address" placeholder="Address" value="<?=e($r['shipping_address'] ?? '')?>"/>
                <input class="border rounded px-2 py-1" name="shipping_city"   placeholder="City"   value="<?=e($r['shipping_city'] ?? '')?>"/>
                <input class="border rounded px-2 py-1" name="shipping_state"  placeholder="State"  value="<?=e($r['shipping_state'] ?? '')?>"/>
                <input class="border rounded px-2 py-1" name="shipping_zip"    placeholder="Zip"    value="<?=e($r['shipping_zip'] ?? '')?>"/>
                <input class="border rounded px-2 py-1 col-span-2" name="tracking" placeholder="Tracking Number" value="<?=e($r['rx_note_name'] ?? '')?>"/>
                <select class="border rounded px-2 py-1" name="carrier">
                  <option value="">Auto-detect</option>
                  <option value="ups"   <?=($r['rx_note_mime']==='ups'?'selected':'')?>>UPS</option>
                  <option value="fedex" <?=($r['rx_note_mime']==='fedex'?'selected':'')?>>FedEx</option>
                  <option value="usps"  <?=($r['rx_note_mime']==='usps'?'selected':'')?>>USPS</option>
                </select>
              </div>
              <div class="text-[11px] text-slate-500 mt-1">Carrier auto-detects; USPS will fetch live status if USPS_USERID is set.</div>
              <button class="mt-2 bg-brand text-white rounded px-3 py-1">Save & Update</button>
            </form>
          </details>

          <!-- Edit dialog -->
          <button onclick="document.getElementById('dlg-<?=e($r['id'])?>').showModal()" class="ml-3 text-slate-700 hover:underline">Edit</button>
          <dialog id="dlg-<?=e($r['id'])?>" class="rounded-2xl p-0 w-[860px]">
            <form method="dialog"><button class="absolute right-3 top-3 text-slate-500">✕</button></form>
            <div class="p-5 border-b font-semibold">Edit Order #<?=e($r['id'])?></div>
            <div class="p-5">
              <form method="post" class="grid grid-cols-2 gap-3">
                <?=csrf_field()?><input type="hidden" name="action" value="edit_order">
                <input type="hidden" name="id" value="<?=e($r['id'])?>"><input type="hidden" name="patient_id" value="<?=e($r['pid'])?>">
                <input type="hidden" name="product_text" value="<?=e($r['product'] ?? '')?>">
                <div class="col-span-2 text-sm font-medium text-slate-600">Order</div>

                <label class="text-xs text-slate-500">Product</label><span></span>
                <select name="product_id" class="border rounded px-2 py-1 col-span-2" onchange="this.dataset.lbl=this.options[this.selectedIndex].text; this.form.product_label.value=this.dataset.lbl; this.form.product_price.value=this.options[this.selectedIndex].dataset.price||'';">
                  <option value="">— choose —</option>
                  <?php foreach ($products as $p): $txt=$p['name'].($p['size']?(" ".$p['size']):'')." (".$p['sku'].")"; ?>
                    <option value="<?=$p['id']?>" data-price="<?=$p['price_admin']?>" <?=(!empty($r['product_id']) && $r['product_id']==$p['id']?'selected':'')?>><?=e($txt)?></option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="product_label" value="<?=e($r['product'] ?? '')?>">

                <label class="text-xs text-slate-500">Frequency</label>
                <label class="text-xs text-slate-500">Quantity</label>
                <select name="frequency" class="border rounded px-2 py-1">
                  <?php foreach($freqOptions as $f): ?><option <?=$r['frequency']===$f?'selected':''?>><?=$f?></option><?php endforeach; ?>
                </select>
                <input class="border rounded px-2 py-1" name="quantity" type="number" min="0" value="<?=e(array_key_exists('quantity',$r)?($r['quantity'] ?? 1):1)?>">

                <label class="text-xs text-slate-500">Unit Price (admin)</label>
                <label class="text-xs text-slate-500">Delivery Mode</label>
                <input class="border rounded px-2 py-1" name="product_price" type="number" step="0.01" value="<?=e($r['product_price'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="delivery_mode" value="<?=e($r['delivery_mode'] ?? '')?>" placeholder="e.g., Ship to patient">

                <div class="col-span-2 text-sm font-medium text-slate-600 mt-2">Shipping</div>
                <input class="border rounded px-2 py-1" name="shipping_name"  placeholder="Recipient Name"  value="<?=e($r['shipping_name'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="shipping_phone" placeholder="Recipient Phone" value="<?=e($r['shipping_phone'] ?? '')?>">
                <input class="border rounded px-2 py-1 col-span-2" name="shipping_address" placeholder="Address" value="<?=e($r['shipping_address'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="shipping_city"  placeholder="City"  value="<?=e($r['shipping_city'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="shipping_state" placeholder="State" value="<?=e($r['shipping_state'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="shipping_zip"   placeholder="Zip"   value="<?=e($r['shipping_zip'] ?? '')?>">

                <div class="col-span-2 text-sm font-medium text-slate-600 mt-2">Patient Demographics</div>
                <label class="text-xs text-slate-500">DOB</label><label class="text-xs text-slate-500">Insurance Provider</label>
                <input class="border rounded px-2 py-1" type="date" name="pat_dob" value="<?=e($r['dob'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="ins_provider" value="<?=e($r['insurance_provider'] ?? '')?>">
                <label class="text-xs text-slate-500">Member ID</label><label class="text-xs text-slate-500">Group ID</label>
                <input class="border rounded px-2 py-1" name="ins_member" value="<?=e($r['insurance_member_id'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="ins_group"  value="<?=e($r['insurance_group_id'] ?? '')?>">
                <label class="text-xs text-slate-500">Payer Phone</label><span></span>
                <input class="border rounded px-2 py-1 col-span-2" name="ins_payer_phone" value="<?=e($r['insurance_payer_phone'] ?? '')?>">

                <div class="col-span-2"><button class="mt-2 bg-brand text-white rounded px-3 py-2">Save Changes</button></div>
              </form>
            </div>
          </dialog>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($hasLayout) include $footer; else echo '</div>'; ?>
