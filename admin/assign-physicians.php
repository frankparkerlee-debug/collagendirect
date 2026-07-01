<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_admin();
if (function_exists('deny_sales_rep')) deny_sales_rep();
require_once __DIR__ . '/../api/lib/features.php';

$admin = current_admin();
$msg = '';

/* Save: tie each submitted physician to the chosen practice (practice_id = owner id). */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $map = $_POST['practice_for'] ?? [];   // physician_id => practice_admin_id ('' = leave unassigned)
  $assigned = 0;
  $setPid  = $pdo->prepare("UPDATE users SET practice_id = ?, updated_at = NOW() WHERE id = ? AND role = 'physician'");
  $ownFeat = $pdo->prepare("SELECT features FROM users WHERE id = ?");
  $setFeat = $pdo->prepare("UPDATE users SET features = ?::jsonb WHERE id = ?");
  foreach ($map as $physId => $ownerId) {
    $physId  = (string)$physId;
    $ownerId = trim((string)$ownerId);
    if ($ownerId === '') continue;               // skip rows left blank
    $setPid->execute([$ownerId, $physId]);
    // Inherit the practice's features so the physician's nav matches immediately.
    $ownFeat->execute([$ownerId]);
    $of = $ownFeat->fetchColumn();
    if ($of !== false && $of !== null && $of !== '') $setFeat->execute([$of, $physId]);
    $assigned++;
  }
  $msg = $assigned > 0 ? "Assigned {$assigned} physician(s) to a practice." : 'No changes — nothing selected.';
}

/* Unlinked physicians (no practice_id yet). */
$unlinked = $pdo->query("
  SELECT id, first_name, last_name, email, created_at
  FROM users
  WHERE role = 'physician' AND (practice_id IS NULL OR TRIM(practice_id) = '')
  ORDER BY last_name, first_name
")->fetchAll();

/* Practice owners for the dropdowns. */
$practiceOwners = $pdo->query("
  SELECT id, practice_name, CONCAT(first_name, ' ', last_name) AS owner_name
  FROM users
  WHERE role = 'practice_admin' AND practice_name IS NOT NULL AND TRIM(practice_name) <> ''
  ORDER BY practice_name
")->fetchAll();
?>
<?php include __DIR__ . '/_header.php'; ?>

<div class="flex items-center justify-between mb-4">
  <div class="text-xl font-semibold">Assign Physicians to Practices</div>
  <a class="text-sm text-blue-600" href="/admin/users.php?tab=physicians">← Back to Providers</a>
</div>

<?php if ($msg): ?><div class="mb-3 text-sm bg-teal-50 border border-teal-200 text-teal-700 p-2 rounded"><?=htmlspecialchars($msg)?></div><?php endif; ?>

<div class="bg-white border rounded p-4">
  <p class="text-sm text-slate-600 mb-3">
    These physician accounts aren't tied to a practice. Assign each to its practice owner so it inherits the
    practice's portal features and grouping. Leave a row blank to keep it standalone. You can also set this on an
    individual physician from <a class="text-blue-600" href="/admin/users.php?tab=physicians">Providers</a>.
  </p>

  <?php if (!$unlinked): ?>
    <div class="text-sm text-slate-500">All physician accounts are already tied to a practice. 🎉</div>
  <?php else: ?>
    <form method="post">
      <?=csrf_field()?>
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-slate-500 border-b">
            <th class="py-2">Physician</th>
            <th class="py-2">Email</th>
            <th class="py-2 w-1/3">Practice</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($unlinked as $p): ?>
            <tr class="border-b">
              <td class="py-2"><?=htmlspecialchars(trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: '(no name)')?></td>
              <td class="py-2 text-slate-600"><?=htmlspecialchars($p['email'] ?? '')?></td>
              <td class="py-2">
                <select class="border rounded px-2 py-1 w-full" name="practice_for[<?=htmlspecialchars($p['id'])?>]">
                  <option value="">— Leave standalone —</option>
                  <?php foreach ($practiceOwners as $po): ?>
                    <option value="<?=htmlspecialchars($po['id'])?>"><?=htmlspecialchars($po['practice_name'])?> (<?=htmlspecialchars($po['owner_name'])?>)</option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="mt-4">
        <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm" type="submit">Save assignments</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
