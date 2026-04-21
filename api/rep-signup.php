<?php
/**
 * Sales Rep Signup API
 *
 * POST /api/rep-signup.php
 * Creates user account, sales_rep profile, and signed documents
 * Sends notification email to admins
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

// Extract and validate required fields
$firstName = trim($input['first_name'] ?? '');
$lastName = trim($input['last_name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$companyName = trim($input['company_name'] ?? '');
$password = $input['password'] ?? '';
$howHeard = trim($input['how_heard'] ?? '');

// Agreement signatures
$repAgreementSignature = trim($input['rep_agreement_signature'] ?? '');
$repAgreementSignedAt = $input['rep_agreement_signed_at'] ?? null;
$baaSignature = trim($input['baa_signature'] ?? '');
$baaSignedAt = $input['baa_signed_at'] ?? null;

// Validation
$errors = [];

if (!$firstName) $errors[] = 'First name is required';
if (!$lastName) $errors[] = 'Last name is required';
if (!$email) $errors[] = 'Email is required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
if (!$phone) $errors[] = 'Phone is required';
if (!$password || strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
if (!$repAgreementSignature) $errors[] = 'Rep agreement signature is required';
if (!$baaSignature) $errors[] = 'BAA signature is required';

if (!empty($errors)) {
  json_out(400, ['error' => implode(', ', $errors)]);
}

// Check for existing email
$stmt = $pdo->prepare("
  SELECT u.id as user_id, sr.id as rep_id, sr.status as rep_status
  FROM users u
  LEFT JOIN sales_reps sr ON sr.user_id = u.id
  WHERE LOWER(u.email) = LOWER(?) AND u.deleted_at IS NULL
  LIMIT 1
");
$stmt->execute([$email]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
  // Allow re-registration if previous application expired or was rejected
  if ($existing['rep_status'] === 'expired' || $existing['rep_status'] === 'rejected') {
    // Reset existing records instead of deleting (preserves user ID references)
    if ($existing['rep_id']) {
      $pdo->prepare("DELETE FROM rep_signed_documents WHERE rep_id = ?")->execute([$existing['rep_id']]);
      $pdo->prepare("DELETE FROM sales_reps WHERE id = ?")->execute([$existing['rep_id']]);
    }
    // Update existing user instead of deleting and recreating
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("
      UPDATE users SET first_name = ?, last_name = ?, phone = ?, password_hash = ?,
        role = 'physician', status = 'active', updated_at = NOW()
      WHERE id = ?
    ")->execute([$firstName, $lastName, $phone, $passwordHash, $existing['user_id']]);

    // Create fresh sales_rep profile
    $repId = uid();
    $pdo->prepare("
      INSERT INTO sales_reps (id, user_id, company_name, status, application_date, how_heard_about_us, notes, created_at, updated_at)
      VALUES (?, ?, ?, 'pending', NOW(), ?, 'Re-applied via online form', NOW(), NOW())
    ")->execute([$repId, $existing['user_id'], $companyName ?: null, $howHeard ?: null]);

    // Record signed documents
    $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (strpos($clientIp, ',') !== false) $clientIp = trim(explode(',', $clientIp)[0]);
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $pdo->prepare("
      INSERT INTO rep_signed_documents (rep_id, document_type, document_version, signature_text, signed_at, ip_address, user_agent, created_at)
      VALUES (?, 'rep_agreement', '1.0', ?, ?, ?, ?, NOW())
    ")->execute([$repId, $repAgreementSignature, $repAgreementSignedAt ? date('Y-m-d H:i:s', strtotime($repAgreementSignedAt)) : date('Y-m-d H:i:s'), $clientIp, $userAgent]);

    $pdo->prepare("
      INSERT INTO rep_signed_documents (rep_id, document_type, document_version, signature_text, signed_at, ip_address, user_agent, created_at)
      VALUES (?, 'baa', '1.0', ?, ?, ?, ?, NOW())
    ")->execute([$repId, $baaSignature, $baaSignedAt ? date('Y-m-d H:i:s', strtotime($baaSignedAt)) : date('Y-m-d H:i:s'), $clientIp, $userAgent]);

    // Send admin notification
    sendAdminNotification($pdo, $firstName, $lastName, $email, $phone, $companyName);

    json_out(200, [
      'success' => true,
      'message' => 'Application re-submitted successfully',
      'user_id' => $existing['user_id'],
      'rep_id' => $repId
    ]);
  } else {
    json_out(400, ['error' => 'An account with this email already exists']);
  }
}

// Capture metadata
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (strpos($clientIp, ',') !== false) {
  $clientIp = trim(explode(',', $clientIp)[0]);
}
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

try {
  $pdo->beginTransaction();

  // 1. Create user account
  $userId = uid();
  $passwordHash = password_hash($password, PASSWORD_DEFAULT);

  $stmt = $pdo->prepare("
    INSERT INTO users (
      id, email, password_hash, first_name, last_name, phone,
      role, user_type, status, account_type,
      created_at, updated_at
    ) VALUES (
      ?, ?, ?, ?, ?, ?,
      'physician', 'physician', 'active', 'referral',
      NOW(), NOW()
    )
  ");
  $stmt->execute([
    $userId,
    strtolower($email),
    $passwordHash,
    $firstName,
    $lastName,
    $phone
  ]);

  // 2. Create sales_rep profile with pending status
  $repId = uid();

  $stmt = $pdo->prepare("
    INSERT INTO sales_reps (
      id, user_id, company_name, status,
      application_date, how_heard_about_us, notes,
      created_at, updated_at
    ) VALUES (
      ?, ?, ?, 'pending',
      NOW(), ?, 'Applied via online form',
      NOW(), NOW()
    )
  ");
  $stmt->execute([
    $repId,
    $userId,
    $companyName ?: null,
    $howHeard ?: null
  ]);

  // 3. Record signed rep agreement
  $stmt = $pdo->prepare("
    INSERT INTO rep_signed_documents (
      rep_id, document_type, document_version,
      signature_text, signed_at, ip_address, user_agent,
      created_at
    ) VALUES (
      ?, 'rep_agreement', '1.0',
      ?, ?, ?, ?,
      NOW()
    )
  ");
  $stmt->execute([
    $repId,
    $repAgreementSignature,
    $repAgreementSignedAt ? date('Y-m-d H:i:s', strtotime($repAgreementSignedAt)) : date('Y-m-d H:i:s'),
    $clientIp,
    $userAgent
  ]);

  // 4. Record signed BAA
  $stmt = $pdo->prepare("
    INSERT INTO rep_signed_documents (
      rep_id, document_type, document_version,
      signature_text, signed_at, ip_address, user_agent,
      created_at
    ) VALUES (
      ?, 'baa', '1.0',
      ?, ?, ?, ?,
      NOW()
    )
  ");
  $stmt->execute([
    $repId,
    $baaSignature,
    $baaSignedAt ? date('Y-m-d H:i:s', strtotime($baaSignedAt)) : date('Y-m-d H:i:s'),
    $clientIp,
    $userAgent
  ]);

  $pdo->commit();

  // 5. Send notification email to admins
  sendAdminNotification($pdo, $firstName, $lastName, $email, $phone, $companyName);

  json_out(200, [
    'success' => true,
    'message' => 'Application submitted successfully',
    'user_id' => $userId,
    'rep_id' => $repId
  ]);

} catch (PDOException $e) {
  $pdo->rollBack();
  error_log("[rep-signup] Database error: " . $e->getMessage());
  json_out(500, ['error' => 'An error occurred while processing your application. Please try again.']);
} catch (Throwable $e) {
  $pdo->rollBack();
  error_log("[rep-signup] Error: " . $e->getMessage());
  json_out(500, ['error' => 'An unexpected error occurred. Please try again.']);
}

/**
 * Send notification email to superadmins and manufacturers
 */
function sendAdminNotification(PDO $pdo, string $firstName, string $lastName, string $email, string $phone, string $companyName): void {
  try {
    // Get superadmin emails from admin_users
    $stmt = $pdo->prepare("
      SELECT email, name FROM admin_users
      WHERE role IN ('superadmin', 'manufacturer')
      LIMIT 10
    ");
    $stmt->execute();
    $admins = $stmt->fetchAll();

    if (empty($admins)) {
      error_log("[rep-signup] No admin recipients found for notification");
      return;
    }

    $fullName = trim("$firstName $lastName");

    $bodyContent = <<<HTML
<h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 0 0 20px 0;">
  New Sales Rep Application
</h2>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  A new sales representative application has been submitted and is awaiting review.
</p>

<div style="background: #f8fafc; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
  <table style="width: 100%; border-collapse: collapse;">
    <tr>
      <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Name</td>
      <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: 600;">{$fullName}</td>
    </tr>
    <tr>
      <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Email</td>
      <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">{$email}</td>
    </tr>
    <tr>
      <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Phone</td>
      <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">{$phone}</td>
    </tr>
    <tr>
      <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Company</td>
      <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">{$companyName}</td>
    </tr>
  </table>
</div>

<p style="color: #374151; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
  The applicant has signed the Sales Representative Agreement and Business Associate Agreement.
</p>

<div style="text-align: center; margin: 30px 0;">
  <a href="https://collagendirect.health/admin/platform/distributors.php?tab=pending"
     style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #0d9488 0%, #10b981 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
    Review Application
  </a>
</div>

<p style="color: #6b7280; font-size: 12px; margin-top: 20px;">
  This is an automated notification from the CollagenDirect sales partner portal.
</p>
HTML;

    $subject = "New Sales Rep Application: $fullName";
    $html = email_template($subject, $bodyContent);

    foreach ($admins as $admin) {
      send_email(
        $admin['email'],
        $admin['name'] ?? 'Admin',
        $subject,
        $html
      );
    }

    error_log("[rep-signup] Admin notification sent to " . count($admins) . " recipients");

  } catch (Throwable $e) {
    error_log("[rep-signup] Failed to send admin notification: " . $e->getMessage());
    // Don't throw - notification failure shouldn't fail the signup
  }
}
