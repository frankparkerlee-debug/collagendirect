<?php
/**
 * Sales Rep Portal: Set a rep's per-item commissions  (distributor-only)
 *
 * A distributor assigns per-product dollar commissions to one of their reps.
 * Each rep amount is capped at the distributor's own per-item rate for that product
 * (the company sets the distributor's rates; the distributor keeps the margin).
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../../api/lib/commission.php';

if (!$isRegularSalesRep || !$isDistributor) {
  echo '<div class="card" style="padding:1.5rem;margin:1.5rem;"><p style="color:#dc2626;font-weight:600;">This page is only available to distributor accounts.</p></div>';
  require __DIR__ . '/_footer.php';
  exit;
}

$myRepId  = $admin['rep_id'];
$myUserId = $admin['id'];
$repId = $_GET['rep_id'] ?? '';

// The rep must belong to this distributor.
$chk = $pdo->prepare("SELECT sr.id, u.first_name, u.last_name FROM sales_reps sr JOIN users u ON u.id = sr.user_id WHERE sr.id = ? AND sr.parent_rep_id = ?");
$chk->execute([$repId, $myRepId]);
$theRep = $chk->fetch(PDO::FETCH_ASSOC);
if (!$theRep) {
  echo '<div class="card" style="padding:1.5rem;margin:1.5rem;"><p style="color:#dc2626;">Rep not found.</p> <a href="/admin/rep/reps.php">Back to My Reps</a></div>';
  require __DIR__ . '/_footer.php';
  exit;
}
$repName = trim($theRep['first_name'] . ' ' . $theRep['last_name']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $distRates = get_product_commissions($pdo, $myRepId);  // [product_id => your per-item amount] = the caps
  $posted = $_POST['rate'] ?? [];
  $saved = 0; $capped = 0;
  foreach ($posted as $pid => $val) {
    $pid = (int)$pid;
    $val = trim((string)$val);
    if (!isset($distRates[$pid])) continue;     // can only set products you have a rate for
    if ($val === '') continue;                   // blank = leave unchanged
    $amt = (float)$val;
    if ($amt < 0) continue;
    if ($amt > (float)$distRates[$pid] + 1e-9) { $capped++; continue; }  // enforce cap
    set_product_commission($pdo, $repId, $pid, $amt, date('Y-m-d'), $myUserId, 'Set by distributor');
    $saved++;
  }
  $message = "$saved commission rate(s) saved." . ($capped ? " {$capped} skipped — above your own rate." : '');
}

// The distributor's per-item products (with names) — these are what can be assigned to reps.
$distProducts = $pdo->prepare("
  SELECT DISTINCT ON (rpc.product_id) rpc.product_id, rpc.commission_amount AS dist_amount, p.name, p.size
  FROM rep_product_commissions rpc
  JOIN products p ON p.id = rpc.product_id
  WHERE rpc.rep_id = ? AND (rpc.end_date IS NULL OR rpc.end_date >= CURRENT_DATE)
  ORDER BY rpc.product_id, rpc.effective_date DESC NULLS LAST
");
$distProducts->execute([$myRepId]);
$products = $distProducts->fetchAll(PDO::FETCH_ASSOC);
$repRates = get_product_commissions($pdo, $repId);  // rep's current [product_id => amount]
?>

<div style="max-width:900px;margin:0 auto;padding:1.5rem;">
  <div style="margin-bottom:1rem;">
    <a href="/admin/rep/reps.php" style="font-size:0.8rem;color:#0075bc;text-decoration:none;">&larr; Back to My Reps</a>
  </div>
  <h1 style="font-size:1.5rem;font-weight:700;color:#1a1a1a;">Commissions for <?= htmlspecialchars($repName) ?></h1>
  <p style="color:#64748b;font-size:0.875rem;margin-bottom:1.5rem;">Set what this rep earns per item. Each amount is capped at your own rate — you keep the difference as your margin.</p>

  <?php if ($message): ?><div style="margin-bottom:1rem;padding:0.75rem 1rem;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:8px;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div style="margin-bottom:1rem;padding:0.75rem 1rem;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:8px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php if (empty($products)): ?>
    <div class="card" style="padding:1.5rem;color:#64748b;">
      You don't have any per-item commission rates yet. Ask CollagenDirect to set your per-item rates, then you can pass a portion to your reps.
    </div>
  <?php else: ?>
  <form method="post" class="card" style="padding:0;overflow:hidden;">
    <?= csrf_field() ?>
    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
      <thead>
        <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;text-align:left;color:#64748b;">
          <th style="padding:0.75rem 1rem;">Product</th>
          <th style="padding:0.75rem 1rem;text-align:right;">Your rate / item</th>
          <th style="padding:0.75rem 1rem;text-align:right;">Rep earns / item</th>
          <th style="padding:0.75rem 1rem;text-align:right;">Your margin / item</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p):
          $pid = (int)$p['product_id'];
          $cap = (float)$p['dist_amount'];
          $cur = isset($repRates[$pid]) ? (float)$repRates[$pid] : null;
          $margin = $cur !== null ? max(0, $cap - $cur) : null;
          $label = trim(($p['name'] ?? 'Product') . ' ' . ($p['size'] ?? ''));
        ?>
        <tr style="border-bottom:1px solid #f1f5f9;">
          <td style="padding:0.75rem 1rem;font-weight:600;"><?= htmlspecialchars($label) ?></td>
          <td style="padding:0.75rem 1rem;text-align:right;color:#1a1a1a;">$<?= number_format($cap, 2) ?></td>
          <td style="padding:0.75rem 1rem;text-align:right;">
            <span style="color:#64748b;">$</span>
            <input type="number" step="0.01" min="0" max="<?= htmlspecialchars((string)$cap) ?>" name="rate[<?= $pid ?>]"
                   value="<?= $cur !== null ? htmlspecialchars(number_format($cur, 2, '.', '')) : '' ?>"
                   placeholder="0.00" style="width:90px;padding:0.35rem 0.5rem;border:1px solid #d1d5db;border-radius:6px;text-align:right;">
          </td>
          <td style="padding:0.75rem 1rem;text-align:right;color:#059669;font-weight:600;"><?= $margin !== null ? '$' . number_format($margin, 2) : '&mdash;' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div style="padding:1rem;display:flex;gap:0.5rem;border-top:1px solid #e5e7eb;">
      <button type="submit" class="btn btn-primary" style="background:#0075bc;border-color:#0075bc;">Save Commissions</button>
      <a href="/admin/rep/reps.php" class="btn">Cancel</a>
    </div>
    <p style="padding:0 1rem 1rem;font-size:0.75rem;color:#94a3b8;">Leave a field blank to keep the current value. Amounts above your own rate are rejected.</p>
  </form>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
