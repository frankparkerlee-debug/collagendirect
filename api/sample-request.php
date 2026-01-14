<?php
/**
 * API: Sample Package Request
 *
 * Handles sample package request submissions from the public form.
 * Stores request in database and sends email notifications.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/email_sender.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(405, ['error' => 'Method not allowed']);
}

require_csrf();

// Parse input
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Extract and sanitize fields
$firstName = trim($input['first_name'] ?? '');
$lastName = trim($input['last_name'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$phone = trim($input['phone'] ?? '');
$practiceName = trim($input['practice_name'] ?? '');
$specialty = trim($input['specialty'] ?? '');
$npi = preg_replace('/\D/', '', $input['npi'] ?? '');
$shipAddress = trim($input['ship_address'] ?? '');
$shipCity = trim($input['ship_city'] ?? '');
$shipState = strtoupper(trim($input['ship_state'] ?? ''));
$shipZip = trim($input['ship_zip'] ?? '');
$howHeard = trim($input['how_heard'] ?? '');
$notes = trim($input['notes'] ?? '');

// Validation
$errors = [];

if (!$firstName) $errors[] = 'First name is required';
if (!$lastName) $errors[] = 'Last name is required';
if (!$email) $errors[] = 'Email is required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
if (!$phone) $errors[] = 'Phone is required';
if (!$shipAddress) $errors[] = 'Shipping address is required';
if (!$shipCity) $errors[] = 'City is required';
if (!$shipState) $errors[] = 'State is required';
if (!$shipZip) $errors[] = 'ZIP code is required';

// Validate state is a valid 2-letter code
$validStates = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY','DC'];
if ($shipState && !in_array($shipState, $validStates)) {
  $errors[] = 'Invalid state';
}

// NPI validation (if provided)
if ($npi && strlen($npi) !== 10) {
  $errors[] = 'NPI must be 10 digits';
}

if (!empty($errors)) {
  json_out(400, ['error' => implode(', ', $errors)]);
}

// Check for duplicate pending request
$dupCheck = $pdo->prepare("
  SELECT id FROM sample_package_requests
  WHERE email = ? AND status IN ('pending', 'approved')
  LIMIT 1
");
$dupCheck->execute([$email]);
if ($dupCheck->fetch()) {
  json_out(400, ['error' => 'A sample request for this email is already pending. Please contact us if you have questions.']);
}

// Generate request ID
$requestId = uid();

// Get IP and user agent
$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

try {
  $pdo->beginTransaction();

  // Insert request
  $stmt = $pdo->prepare("
    INSERT INTO sample_package_requests (
      id, first_name, last_name, email, phone,
      practice_name, specialty, npi,
      ship_address, ship_city, ship_state, ship_zip,
      notes, how_heard,
      status, ip_address, user_agent,
      created_at, updated_at
    ) VALUES (
      ?, ?, ?, ?, ?,
      ?, ?, ?,
      ?, ?, ?, ?,
      ?, ?,
      'pending', ?, ?,
      NOW(), NOW()
    )
  ");

  $stmt->execute([
    $requestId, $firstName, $lastName, $email, $phone,
    $practiceName ?: null, $specialty ?: null, $npi ?: null,
    $shipAddress, $shipCity, $shipState, $shipZip,
    $notes ?: null, $howHeard ?: null,
    $ipAddress, $userAgent
  ]);

  $pdo->commit();

  // Send confirmation email to requester
  $fullName = $firstName . ' ' . $lastName;
  $subject = 'Sample Request Received - CollagenDirect';

  $bodyContent = <<<HTML
<h2 style="color: #14b8a6; margin-bottom: 16px;">Thank You for Your Request!</h2>
<p style="margin-bottom: 16px;">Dear Dr. {$firstName} {$lastName},</p>
<p style="margin-bottom: 16px;">We've received your request for a CollagenDirect sample kit. Our team will review your request and ship your samples soon.</p>
<div style="background: #f0fdfa; border-radius: 8px; padding: 16px; margin: 20px 0;">
  <p style="margin: 0; font-weight: bold; color: #134e4a;">Shipping To:</p>
  <p style="margin: 8px 0 0 0; color: #374151;">
    {$shipAddress}<br>
    {$shipCity}, {$shipState} {$shipZip}
  </p>
</div>
<p style="margin-bottom: 16px;">A member of our team may reach out to answer any questions about our products and how they can benefit your practice.</p>
<p style="margin-bottom: 16px;">If you have any questions in the meantime, please don't hesitate to contact us at <a href="mailto:samples@collagendirect.health" style="color: #14b8a6;">samples@collagendirect.health</a>.</p>
<p style="margin-bottom: 8px;">Best regards,</p>
<p style="margin: 0; font-weight: bold;">The CollagenDirect Team</p>
HTML;

  $html = email_template($subject, $bodyContent);
  $plainText = "Thank you for your sample request!\n\nWe've received your request and will ship your CollagenDirect sample kit soon.\n\nShipping to:\n{$shipAddress}\n{$shipCity}, {$shipState} {$shipZip}\n\nQuestions? Contact samples@collagendirect.health";

  try {
    send_email($email, $fullName, $subject, $html, $plainText);
  } catch (Exception $e) {
    error_log("Failed to send sample request confirmation email: " . $e->getMessage());
  }

  // Send notification to admin team
  try {
    // Get admin emails (platform admins)
    $adminEmails = $pdo->query("
      SELECT email, COALESCE(display_name, username) as name
      FROM admin_users
      WHERE role IN ('admin', 'sales')
      AND is_active = TRUE
      LIMIT 5
    ")->fetchAll();

    $adminSubject = 'New Sample Request - ' . $fullName;
    $adminBody = <<<HTML
<h2 style="color: #14b8a6; margin-bottom: 16px;">New Sample Package Request</h2>
<p style="margin-bottom: 16px;">A new sample request has been submitted:</p>
<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
  <tr>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: bold; width: 140px;">Name:</td>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{$firstName} {$lastName}</td>
  </tr>
  <tr>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: bold;">Email:</td>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><a href="mailto:{$email}">{$email}</a></td>
  </tr>
  <tr>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: bold;">Phone:</td>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{$phone}</td>
  </tr>
  <tr>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: bold;">Practice:</td>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{$practiceName}</td>
  </tr>
  <tr>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: bold;">Specialty:</td>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{$specialty}</td>
  </tr>
  <tr>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: bold;">NPI:</td>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{$npi}</td>
  </tr>
  <tr>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: bold;">Ship To:</td>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{$shipAddress}, {$shipCity}, {$shipState} {$shipZip}</td>
  </tr>
  <tr>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: bold;">How Heard:</td>
    <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{$howHeard}</td>
  </tr>
  <tr>
    <td style="padding: 8px; font-weight: bold; vertical-align: top;">Notes:</td>
    <td style="padding: 8px;">{$notes}</td>
  </tr>
</table>
<p style="margin-top: 20px;">
  <a href="https://collagendirect.health/admin/platform/sample-requests.php" style="display: inline-block; background: #14b8a6; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;">Review Request</a>
</p>
HTML;

    $adminHtml = email_template($adminSubject, $adminBody);

    foreach ($adminEmails as $admin) {
      send_email($admin['email'], $admin['name'], $adminSubject, $adminHtml, strip_tags($adminBody));
    }
  } catch (Exception $e) {
    error_log("Failed to send sample request admin notification: " . $e->getMessage());
  }

  json_out(200, ['success' => true, 'request_id' => $requestId]);

} catch (PDOException $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log("Sample request error: " . $e->getMessage());
  json_out(500, ['error' => 'Failed to submit request. Please try again.']);
}
