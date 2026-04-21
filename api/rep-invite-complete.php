<?php
/**
 * Complete Sales Rep Invite API
 *
 * POST /api/rep-invite-complete.php
 * Completes an invited rep's registration by setting password and recording signatures
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/email_sender.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(405, ['error' => 'Method not allowed']);
}

require_csrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Extract fields
$token = trim($input['token'] ?? '');
$password = $input['password'] ?? '';
$repAgreementSignature = trim($input['rep_agreement_signature'] ?? '');
$repAgreementSignedAt = $input['rep_agreement_signed_at'] ?? null;
$baaSignature = trim($input['baa_signature'] ?? '');
$baaSignedAt = $input['baa_signed_at'] ?? null;

// Validation
$errors = [];
if (!$token) $errors[] = 'Invalid invite token';
if (!$password || strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
if (!$repAgreementSignature) $errors[] = 'Rep agreement signature is required';
if (!$baaSignature) $errors[] = 'BAA signature is required';

if (!empty($errors)) {
  json_out(400, ['error' => implode(', ', $errors)]);
}

// Verify token and get rep/user info
$stmt = $pdo->prepare("
  SELECT sr.id as rep_id, sr.user_id, sr.invite_token_expires_at, u.email, u.first_name, u.last_name
  FROM sales_reps sr
  JOIN users u ON u.id = sr.user_id
  WHERE sr.invite_token = ? AND sr.status = 'invited'
");
$stmt->execute([$token]);
$inviteData = $stmt->fetch();

if (!$inviteData) {
  json_out(400, ['error' => 'Invalid or expired invite link. Please contact your administrator.']);
}

// Check if token is expired
if ($inviteData['invite_token_expires_at'] && strtotime($inviteData['invite_token_expires_at']) < time()) {
  json_out(400, ['error' => 'This invite has expired. Please contact your administrator to send a new invite.']);
}

// Capture metadata
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (strpos($clientIp, ',') !== false) {
  $clientIp = trim(explode(',', $clientIp)[0]);
}
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

try {
  $pdo->beginTransaction();

  // 1. Update user with password
  $passwordHash = password_hash($password, PASSWORD_DEFAULT);
  $pdo->prepare("
    UPDATE users
    SET password_hash = ?, updated_at = NOW()
    WHERE id = ?
  ")->execute([$passwordHash, $inviteData['user_id']]);

  // 2. Update sales_rep status to pending (awaiting admin approval)
  $pdo->prepare("
    UPDATE sales_reps
    SET status = 'pending',
        invite_token = NULL,
        invite_token_expires_at = NULL,
        application_date = NOW(),
        updated_at = NOW()
    WHERE id = ?
  ")->execute([$inviteData['rep_id']]);

  // 3. Record signed rep agreement
  // Use ON CONFLICT to handle re-signing (e.g. if invite was re-sent)
  $pdo->prepare("
    INSERT INTO rep_signed_documents (
      rep_id, document_type, document_version,
      signature_text, signed_at, ip_address, user_agent,
      created_at
    ) VALUES (
      ?, 'rep_agreement', '1.0',
      ?, ?, ?, ?,
      NOW()
    )
    ON CONFLICT (rep_id, document_type, document_version)
    DO UPDATE SET signature_text = EXCLUDED.signature_text,
                  signed_at = EXCLUDED.signed_at,
                  ip_address = EXCLUDED.ip_address,
                  user_agent = EXCLUDED.user_agent
  ")->execute([
    $inviteData['rep_id'],
    $repAgreementSignature,
    $repAgreementSignedAt ? date('Y-m-d H:i:s', strtotime($repAgreementSignedAt)) : date('Y-m-d H:i:s'),
    $clientIp,
    $userAgent
  ]);

  // 4. Record signed BAA
  $pdo->prepare("
    INSERT INTO rep_signed_documents (
      rep_id, document_type, document_version,
      signature_text, signed_at, ip_address, user_agent,
      created_at
    ) VALUES (
      ?, 'baa', '1.0',
      ?, ?, ?, ?,
      NOW()
    )
    ON CONFLICT (rep_id, document_type, document_version)
    DO UPDATE SET signature_text = EXCLUDED.signature_text,
                  signed_at = EXCLUDED.signed_at,
                  ip_address = EXCLUDED.ip_address,
                  user_agent = EXCLUDED.user_agent
  ")->execute([
    $inviteData['rep_id'],
    $baaSignature,
    $baaSignedAt ? date('Y-m-d H:i:s', strtotime($baaSignedAt)) : date('Y-m-d H:i:s'),
    $clientIp,
    $userAgent
  ]);

  $pdo->commit();

  // 5. Send notification email to admins
  sendAdminInviteCompletedNotification($pdo, $inviteData);

  // 6. Send confirmation email to rep
  sendRepConfirmationEmail($inviteData);

  json_out(200, [
    'success' => true,
    'message' => 'Registration completed! Your application is now pending review.'
  ]);

} catch (PDOException $e) {
  $pdo->rollBack();
  error_log("[rep-invite-complete] Database error: " . $e->getMessage());
  json_out(500, ['error' => 'An error occurred. Please try again.']);
} catch (Throwable $e) {
  $pdo->rollBack();
  error_log("[rep-invite-complete] Error: " . $e->getMessage());
  json_out(500, ['error' => 'An unexpected error occurred.']);
}

/**
 * Send notification to admins that an invited rep completed registration
 */
function sendAdminInviteCompletedNotification(PDO $pdo, array $inviteData): void {
  try {
    $stmt = $pdo->prepare("
      SELECT email, name FROM admin_users
      WHERE role IN ('superadmin', 'manufacturer')
      LIMIT 10
    ");
    $stmt->execute();
    $admins = $stmt->fetchAll();

    if (empty($admins)) return;

    $fullName = trim($inviteData['first_name'] . ' ' . $inviteData['last_name']);

    $bodyContent = <<<HTML
<h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 0 0 20px 0;">
  Invited Rep Completed Registration
</h2>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  <strong>{$fullName}</strong> has completed their registration and signed the required documents.
</p>

<div style="background: #f8fafc; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
  <table style="width: 100%; border-collapse: collapse;">
    <tr>
      <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Name</td>
      <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: 600;">{$fullName}</td>
    </tr>
    <tr>
      <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Email</td>
      <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">{$inviteData['email']}</td>
    </tr>
    <tr>
      <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Status</td>
      <td style="padding: 8px 0; color: #f59e0b; font-size: 14px; font-weight: 600;">Pending Review</td>
    </tr>
  </table>
</div>

<div style="text-align: center; margin: 30px 0;">
  <a href="https://collagendirect.health/admin/platform/distributors.php?tab=pending"
     style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #0d9488 0%, #10b981 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
    Review Application
  </a>
</div>
HTML;

    $subject = "Invited Rep Ready for Review: $fullName";
    $html = email_template($subject, $bodyContent);

    foreach ($admins as $admin) {
      send_email($admin['email'], $admin['name'] ?? 'Admin', $subject, $html);
    }

  } catch (Throwable $e) {
    error_log("[rep-invite-complete] Admin notification failed: " . $e->getMessage());
  }
}

/**
 * Send confirmation email to rep
 */
function sendRepConfirmationEmail(array $inviteData): void {
  try {
    $fullName = trim($inviteData['first_name'] . ' ' . $inviteData['last_name']);

    $bodyContent = <<<HTML
<h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 0 0 20px 0;">
  Registration Complete!
</h2>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  Hi {$inviteData['first_name']},
</p>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  Thank you for completing your CollagenDirect sales partner registration. Your application is now being reviewed by our team.
</p>

<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px 20px; margin: 20px 0; border-radius: 0 8px 8px 0;">
  <p style="color: #92400e; font-size: 14px; margin: 0;">
    <strong>What's Next?</strong><br>
    We'll review your application and notify you by email once it's approved. This typically takes 1-2 business days.
  </p>
</div>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  Once approved, you'll be able to log in and start using your sales rep dashboard.
</p>

<p style="color: #6b7280; font-size: 14px; margin-top: 20px;">
  Questions? Contact us at <a href="mailto:partners@collagendirect.health" style="color: #0d9488;">partners@collagendirect.health</a>
</p>
HTML;

    $subject = "CollagenDirect - Registration Complete";
    $html = email_template($subject, $bodyContent);

    send_email($inviteData['email'], $fullName, $subject, $html);

  } catch (Throwable $e) {
    error_log("[rep-invite-complete] Rep confirmation email failed: " . $e->getMessage());
  }
}
