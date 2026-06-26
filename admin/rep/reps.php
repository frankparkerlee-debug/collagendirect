<?php
/**
 * Sales Rep Portal: My Reps  (distributor-only)
 *
 * A distributor manages the reps beneath them: invite, resend invite, activate.
 * Reps are sales_reps rows with parent_rep_id = this distributor's sales_reps.id.
 * Every action is scoped to parent_rep_id = the logged-in distributor's rep id.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../../api/lib/rep_notifications.php';

// Only distributors (top-level reps) may manage sub-reps.
if (!$isRegularSalesRep || !$isDistributor) {
  echo '<div class="card" style="padding:1.5rem;margin:1.5rem;"><p style="color:#dc2626;font-weight:600;">This page is only available to distributor accounts.</p></div>';
  require __DIR__ . '/_footer.php';
  exit;
}

$myRepId  = $admin['rep_id'];
$myUserId = $admin['id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'invite_rep') {
      $firstName   = trim($_POST['first_name'] ?? '');
      $lastName    = trim($_POST['last_name'] ?? '');
      $email       = strtolower(trim($_POST['email'] ?? ''));
      $phone       = trim($_POST['phone'] ?? '');
      $companyName = trim($_POST['company_name'] ?? '');
      $note        = trim($_POST['note'] ?? '');

      if (!$firstName || !$lastName || !$email || !$phone) {
        $error = 'Please fill in first name, last name, email, and phone.';
      } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
      } else {
        $chk = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
          $error = 'A user with this email already exists.';
        } else {
          $pdo->beginTransaction();
          try {
            $inviteToken   = bin2hex(random_bytes(32));
            $inviteExpires = date('Y-m-d H:i:s', strtotime('+7 days'));
            $userId = bin2hex(random_bytes(16));
            $pdo->prepare("INSERT INTO users (id, email, first_name, last_name, phone, role, password_hash, status, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, 'physician', '', 'pending', NOW(), NOW())")
                ->execute([$userId, $email, $firstName, $lastName, $phone]);
            $repId = bin2hex(random_bytes(16));
            $pdo->prepare("INSERT INTO sales_reps (id, user_id, status, company_name, invite_token, invite_token_expires_at, invited_by, parent_rep_id, application_date, created_at, updated_at)
                           VALUES (?, ?, 'invited', ?, ?, ?, ?, ?, NOW(), NOW(), NOW())")
                ->execute([$repId, $userId, $companyName ?: null, $inviteToken, $inviteExpires, $myUserId, $myRepId]);
            $pdo->commit();
            try { send_rep_invite($pdo, $email, "$firstName $lastName", $inviteToken, $note ?: null); }
            catch (Throwable $e) { error_log('[rep invite email] ' . $e->getMessage()); }
            $message = "Invite sent to $firstName $lastName ($email). The invite expires in 7 days.";
          } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
        }
      }
    } elseif ($action === 'resend_invite') {
      $repId = $_POST['rep_id'] ?? '';
      $chk = $pdo->prepare("SELECT sr.status, u.email, u.first_name, u.last_name
                            FROM sales_reps sr JOIN users u ON u.id = sr.user_id
                            WHERE sr.id = ? AND sr.parent_rep_id = ?");
      $chk->execute([$repId, $myRepId]);
      $r = $chk->fetch(PDO::FETCH_ASSOC);
      if (!$r) {
        $error = 'Rep not found.';
      } elseif (in_array($r['status'], ['active', 'suspended', 'terminated'], true)) {
        $error = 'This rep has already completed registration.';
      } else {
        $inviteToken   = bin2hex(random_bytes(32));
        $inviteExpires = date('Y-m-d H:i:s', strtotime('+7 days'));
        $pdo->prepare("UPDATE sales_reps SET status='invited', invite_token=?, invite_token_expires_at=?, updated_at=NOW() WHERE id=? AND parent_rep_id=?")
            ->execute([$inviteToken, $inviteExpires, $repId, $myRepId]);
        try { send_rep_invite($pdo, $r['email'], trim($r['first_name'] . ' ' . $r['last_name']), $inviteToken, null); }
        catch (Throwable $e) { error_log('[rep resend email] ' . $e->getMessage()); }
        $message = 'A fresh invite link (valid 7 days) was sent to ' . $r['email'] . '.';
      }
    } elseif ($action === 'activate_rep') {
      $repId = $_POST['rep_id'] ?? '';
      $upd = $pdo->prepare("UPDATE sales_reps SET status='active', approved_date=NOW(), updated_at=NOW()
                            WHERE id=? AND parent_rep_id=? AND status IN ('pending','suspended')");
      $upd->execute([$repId, $myRepId]);
      $pdo->prepare("UPDATE users SET status='active', updated_at=NOW()
                     WHERE id=(SELECT user_id FROM sales_reps WHERE id=? AND parent_rep_id=?)")->execute([$repId, $myRepId]);
      $message = $upd->rowCount() ? 'Rep activated — they can now log in.' : 'No change.';
    } elseif ($action === 'suspend_rep') {
      $repId = $_POST['rep_id'] ?? '';
      $pdo->prepare("UPDATE sales_reps SET status='suspended', updated_at=NOW() WHERE id=? AND parent_rep_id=? AND status='active'")
          ->execute([$repId, $myRepId]);
      $message = 'Rep suspended.';
    }
  } catch (Throwable $e) {
    $error = 'Error: ' . $e->getMessage();
  }
}

// Fetch this distributor's reps
$stmt = $pdo->prepare("
  SELECT sr.id, sr.status, sr.company_name, sr.invite_token_expires_at, sr.application_date,
         u.first_name, u.last_name, u.email, u.phone,
         (SELECT COUNT(*) FROM users c WHERE c.assigned_rep_id = sr.id) AS clinic_count,
         (SELECT COALESCE(SUM(commission_amount), 0) FROM rep_commission_ledger WHERE rep_id = sr.id) AS commission_total
  FROM sales_reps sr JOIN users u ON u.id = sr.user_id
  WHERE sr.parent_rep_id = ?
  ORDER BY sr.created_at DESC
");
$stmt->execute([$myRepId]);
$myReps = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusStyles = [
  'active'     => 'background:#d1fae5;color:#065f46;',
  'pending'    => 'background:#fef3c7;color:#92400e;',
  'invited'    => 'background:#e6f2fb;color:#20419b;',
  'suspended'  => 'background:#fee2e2;color:#991b1b;',
  'expired'    => 'background:#f3f4f6;color:#6b7280;',
  'rejected'   => 'background:#fee2e2;color:#991b1b;',
  'terminated' => 'background:#f3f4f6;color:#6b7280;',
];
?>

<div style="max-width:1100px;margin:0 auto;padding:1.5rem;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.5rem;font-weight:700;color:#1a1a1a;">My Reps</h1>
      <p style="color:#64748b;font-size:0.875rem;">Invite and manage the reps on your team. Each rep sees only the clients assigned to them.</p>
    </div>
    <button onclick="document.getElementById('invite-rep').style.display='block'" class="btn btn-primary" style="background:#0075bc;border-color:#0075bc;">+ Invite Rep</button>
  </div>

  <?php if ($message): ?><div style="margin-bottom:1rem;padding:0.75rem 1rem;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:8px;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div style="margin-bottom:1rem;padding:0.75rem 1rem;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:8px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Invite form -->
  <div id="invite-rep" style="display:none;margin-bottom:1.5rem;" class="card">
    <form method="post" style="padding:1.25rem;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="invite_rep">
      <h3 style="font-weight:700;margin-bottom:1rem;color:#20419b;">Invite a new rep</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
        <div><label style="font-size:0.75rem;color:#64748b;">First Name *</label><input name="first_name" required style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:6px;"></div>
        <div><label style="font-size:0.75rem;color:#64748b;">Last Name *</label><input name="last_name" required style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:6px;"></div>
        <div><label style="font-size:0.75rem;color:#64748b;">Email *</label><input name="email" type="email" required style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:6px;"></div>
        <div><label style="font-size:0.75rem;color:#64748b;">Phone *</label><input name="phone" required style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:6px;"></div>
        <div><label style="font-size:0.75rem;color:#64748b;">Company (optional)</label><input name="company_name" style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:6px;"></div>
        <div><label style="font-size:0.75rem;color:#64748b;">Personal note (optional)</label><input name="note" style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:6px;"></div>
      </div>
      <div style="margin-top:1rem;display:flex;gap:0.5rem;">
        <button type="submit" class="btn btn-primary" style="background:#0075bc;border-color:#0075bc;">Send Invite</button>
        <button type="button" onclick="document.getElementById('invite-rep').style.display='none'" class="btn">Cancel</button>
      </div>
    </form>
  </div>

  <!-- Reps table -->
  <div class="card" style="padding:0;overflow:hidden;">
    <table style="width:100%;font-size:0.875rem;border-collapse:collapse;">
      <thead>
        <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;text-align:left;color:#64748b;">
          <th style="padding:0.75rem 1rem;">Rep</th>
          <th style="padding:0.75rem 1rem;">Contact</th>
          <th style="padding:0.75rem 1rem;text-align:center;">Clients</th>
          <th style="padding:0.75rem 1rem;text-align:right;">Earned</th>
          <th style="padding:0.75rem 1rem;text-align:center;">Status</th>
          <th style="padding:0.75rem 1rem;text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($myReps)): ?>
        <tr><td colspan="6" style="padding:2.5rem;text-align:center;color:#64748b;">No reps yet. Click <strong>Invite Rep</strong> to add your first one.</td></tr>
        <?php endif; ?>
        <?php foreach ($myReps as $r):
          $st = $r['status'];
          $isExpiredInvite = ($st === 'invited' && !empty($r['invite_token_expires_at']) && strtotime($r['invite_token_expires_at']) < time());
        ?>
        <tr style="border-bottom:1px solid #f1f5f9;">
          <td style="padding:0.75rem 1rem;">
            <div style="font-weight:600;"><?= htmlspecialchars(trim($r['first_name'] . ' ' . $r['last_name'])) ?></div>
            <?php if ($r['company_name']): ?><div style="font-size:0.75rem;color:#64748b;"><?= htmlspecialchars($r['company_name']) ?></div><?php endif; ?>
          </td>
          <td style="padding:0.75rem 1rem;">
            <div><?= htmlspecialchars($r['email']) ?></div>
            <div style="font-size:0.75rem;color:#64748b;"><?= htmlspecialchars($r['phone'] ?? '') ?></div>
          </td>
          <td style="padding:0.75rem 1rem;text-align:center;font-weight:600;"><?= (int)$r['clinic_count'] ?></td>
          <td style="padding:0.75rem 1rem;text-align:right;">$<?= number_format((float)($r['commission_total'] ?? 0), 2) ?></td>
          <td style="padding:0.75rem 1rem;text-align:center;">
            <span style="padding:0.2rem 0.6rem;border-radius:999px;font-size:0.7rem;font-weight:600;<?= $statusStyles[$st] ?? 'background:#f3f4f6;color:#6b7280;' ?>"><?= ucfirst($st) ?><?= $isExpiredInvite ? ' (expired)' : '' ?></span>
          </td>
          <td style="padding:0.75rem 1rem;text-align:right;white-space:nowrap;">
            <?php if ($st === 'active'): ?>
              <a href="/admin/rep/rep-commissions.php?rep_id=<?= htmlspecialchars($r['id']) ?>" style="color:#0075bc;font-weight:600;font-size:0.8rem;text-decoration:none;margin-right:0.5rem;">Commissions</a>
            <?php endif; ?>
            <?php if (in_array($st, ['invited','expired','rejected'], true)): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Send a fresh invite link to this rep?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="resend_invite"><input type="hidden" name="rep_id" value="<?= htmlspecialchars($r['id']) ?>">
                <button class="text-link" style="background:none;border:none;color:#0075bc;font-weight:600;font-size:0.8rem;cursor:pointer;">Resend Invite</button>
              </form>
            <?php endif; ?>
            <?php if (in_array($st, ['pending','suspended'], true)): ?>
              <form method="post" style="display:inline;margin-left:0.5rem;" onsubmit="return confirm('Activate this rep so they can log in?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="activate_rep"><input type="hidden" name="rep_id" value="<?= htmlspecialchars($r['id']) ?>">
                <button style="background:none;border:none;color:#059669;font-weight:600;font-size:0.8rem;cursor:pointer;">Activate</button>
              </form>
            <?php endif; ?>
            <?php if ($st === 'active'): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Suspend this rep?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="suspend_rep"><input type="hidden" name="rep_id" value="<?= htmlspecialchars($r['id']) ?>">
                <button style="background:none;border:none;color:#b45309;font-weight:600;font-size:0.8rem;cursor:pointer;">Suspend</button>
              </form>
            <?php endif; ?>
            <?php if (!in_array($st, ['invited','expired','rejected','pending','suspended','active'], true)): ?>
              <span style="color:#64748b;font-size:0.8rem;">&mdash;</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
