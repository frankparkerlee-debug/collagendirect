<?php
/**
 * Sales Rep Management - REDIRECT
 *
 * This page has been relocated to /admin/platform/distributors.php
 * This redirect preserves existing bookmarks and external links.
 */
declare(strict_types=1);

// Redirect to new location, preserving query parameters
$newUrl = '/admin/platform/distributors.php';
if (!empty($_SERVER['QUERY_STRING'])) {
  $newUrl .= '?' . $_SERVER['QUERY_STRING'];
}
header('Location: ' . $newUrl, true, 301);
exit;

// Original code below kept for reference (never executed)
require __DIR__ . '/_header.php';

// Check permissions
$allowedRoles = ['superadmin', 'manufacturer'];
if (!in_array($admin['role'] ?? '', $allowedRoles)) {
  header('Location: /admin/');
  exit;
}

// Handle actions
$message = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'active';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $action = $_POST['action'] ?? '';

  try {
    switch ($action) {
      case 'approve_rep':
        $repId = trim((string)($_POST['rep_id'] ?? ''));
        $commissionRate = floatval($_POST['commission_rate'] ?? 0.25);

        if (!$repId) {
          $error = 'Cannot approve: missing rep_id in form submission.';
          error_log("[sales-reps.approve_rep] FAILED — empty rep_id. POST: " . json_encode($_POST));
          break;
        }

        // Verify the rep exists and capture starting state for diagnostics
        $verifyStmt = $pdo->prepare("SELECT id, status FROM sales_reps WHERE id = ?");
        $verifyStmt->execute([$repId]);
        $beforeRow = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        if (!$beforeRow) {
          $error = "Cannot approve: no sales_reps row with id=$repId.";
          error_log("[sales-reps.approve_rep] FAILED — rep_id not found: $repId");
          break;
        }

        $pdo->beginTransaction();
        try {
          // Update status to active (approved_by is NULL because admin_users.id is not a valid users.id FK)
          $updStmt = $pdo->prepare("UPDATE sales_reps SET status = 'active', approved_date = NOW(), approved_by = NULL, updated_at = NOW() WHERE id = ?");
          $updStmt->execute([$repId]);
          if ($updStmt->rowCount() !== 1) {
            throw new RuntimeException("UPDATE affected " . $updStmt->rowCount() . " rows (expected 1) for rep_id=$repId");
          }

          // Also flip the linked users.status to 'active' — invite-created users default to 'pending'
          // and nothing else updates it, so admin user lists keep showing "pending" otherwise.
          $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = (SELECT user_id FROM sales_reps WHERE id = ?) AND status <> 'active'")
              ->execute([$repId]);

          // Detect which column name exists (set_by or created_by) — schema varies by deploy
          $colCheck = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'rep_commission_rates' AND column_name IN ('set_by', 'created_by')")->fetchAll(PDO::FETCH_COLUMN);
          if (in_array('set_by', $colCheck)) {
            $pdo->prepare("INSERT INTO rep_commission_rates (rep_id, rate, effective_date, set_by, notes, created_at) VALUES (?, ?, CURRENT_DATE, NULL, 'Initial rate on approval', NOW())")
                ->execute([$repId, $commissionRate]);
          } elseif (in_array('created_by', $colCheck)) {
            $pdo->prepare("INSERT INTO rep_commission_rates (rep_id, rate, effective_date, created_by, notes, created_at) VALUES (?, ?, CURRENT_DATE, NULL, 'Initial rate on approval', NOW())")
                ->execute([$repId, $commissionRate]);
          } else {
            $pdo->prepare("INSERT INTO rep_commission_rates (rep_id, rate, effective_date, notes, created_at) VALUES (?, ?, CURRENT_DATE, 'Initial rate on approval', NOW())")
                ->execute([$repId, $commissionRate]);
          }

          $pdo->commit();
          error_log("[sales-reps.approve_rep] OK — rep_id=$repId was status='" . ($beforeRow['status'] ?? '?') . "', now 'active'");
        } catch (Throwable $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          error_log("[sales-reps.approve_rep] FAILED — rep_id=$repId: " . $e->getMessage());
          $error = 'Approval failed: ' . $e->getMessage();
          break;
        }

        // Send approval email (after commit, so failure doesn't block approval)
        try {
          sendApprovalEmail($pdo, $repId);
        } catch (Throwable $emailErr) {
          error_log("[sales-reps.approve_rep] Email failed but approval succeeded: " . $emailErr->getMessage());
        }

        $message = 'Sales rep approved successfully (was: ' . ($beforeRow['status'] ?? '?') . ', now: active).';
        break;

      case 'reject_rep':
        $repId = $_POST['rep_id'] ?? '';
        $reason = $_POST['rejection_reason'] ?? '';

        if ($repId) {
          $pdo->prepare("UPDATE sales_reps SET status = 'rejected', notes = CONCAT(COALESCE(notes, ''), '\nRejected: ', ?), updated_at = NOW() WHERE id = ?")
              ->execute([$reason, $repId]);

          // Send rejection email
          sendRejectionEmail($pdo, $repId, $reason);

          $message = 'Application rejected.';
        }
        break;

      case 'suspend_rep':
        $repId = $_POST['rep_id'] ?? '';
        if ($repId) {
          $pdo->prepare("UPDATE sales_reps SET status = 'suspended', updated_at = NOW() WHERE id = ?")->execute([$repId]);
          $message = 'Sales rep suspended.';
        }
        break;

      case 'reactivate_rep':
        $repId = $_POST['rep_id'] ?? '';
        if ($repId) {
          $pdo->prepare("UPDATE sales_reps SET status = 'active', updated_at = NOW() WHERE id = ?")->execute([$repId]);
          $message = 'Sales rep reactivated.';
        }
        break;

      case 'terminate_rep':
        $repId = $_POST['rep_id'] ?? '';
        if ($repId) {
          $pdo->prepare("UPDATE sales_reps SET status = 'terminated', updated_at = NOW() WHERE id = ?")->execute([$repId]);
          $message = 'Sales rep terminated.';
        }
        break;

      case 'approve_assignment':
        $requestId = $_POST['request_id'] ?? '';
        if ($requestId) {
          // Get request details
          $reqStmt = $pdo->prepare("SELECT * FROM rep_assignment_requests WHERE id = ?");
          $reqStmt->execute([$requestId]);
          $request = $reqStmt->fetch();

          if ($request) {
            // Update user's assigned rep
            $pdo->prepare("UPDATE users SET assigned_rep_id = ?, rep_assignment_date = NOW(), rep_assigned_by = 'admin', rep_assigned_by_user_id = ? WHERE id = ?")
                ->execute([$request['rep_id'], $admin['id'], $request['clinic_id']]);

            // Update request status
            $pdo->prepare("UPDATE rep_assignment_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                ->execute([$admin['id'], $requestId]);

            // Send notification to rep
            require_once __DIR__ . '/../api/lib/rep_notifications.php';
            $repStmt = $pdo->prepare("SELECT u.email, u.first_name, u.last_name FROM sales_reps sr JOIN users u ON u.id = sr.user_id WHERE sr.id = ?");
            $repStmt->execute([$request['rep_id']]);
            $repInfo = $repStmt->fetch();
            $clinicStmt = $pdo->prepare("SELECT practice_name, first_name, last_name FROM users WHERE id = ?");
            $clinicStmt->execute([$request['clinic_id']]);
            $clinicInfo = $clinicStmt->fetch();
            if ($repInfo && $clinicInfo) {
              $clinicName = $clinicInfo['practice_name'] ?: $clinicInfo['first_name'] . ' ' . $clinicInfo['last_name'];
              send_rep_assignment_approved($pdo, $repInfo['email'], $repInfo['first_name'] . ' ' . $repInfo['last_name'], $clinicName);
            }

            $message = 'Assignment request approved.';
          }
        }
        break;

      case 'deny_assignment':
        $requestId = $_POST['request_id'] ?? '';
        $reason = $_POST['denial_reason'] ?? '';
        if ($requestId) {
          // Get request details for notification
          $reqStmt = $pdo->prepare("SELECT * FROM rep_assignment_requests WHERE id = ?");
          $reqStmt->execute([$requestId]);
          $request = $reqStmt->fetch();

          $pdo->prepare("UPDATE rep_assignment_requests SET status = 'denied', denial_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
              ->execute([$reason, $admin['id'], $requestId]);

          // Send notification to rep
          if ($request) {
            require_once __DIR__ . '/../api/lib/rep_notifications.php';
            $repStmt = $pdo->prepare("SELECT u.email, u.first_name, u.last_name FROM sales_reps sr JOIN users u ON u.id = sr.user_id WHERE sr.id = ?");
            $repStmt->execute([$request['rep_id']]);
            $repInfo = $repStmt->fetch();
            $clinicStmt = $pdo->prepare("SELECT practice_name, first_name, last_name FROM users WHERE id = ?");
            $clinicStmt->execute([$request['clinic_id']]);
            $clinicInfo = $clinicStmt->fetch();
            if ($repInfo && $clinicInfo) {
              $clinicName = $clinicInfo['practice_name'] ?: $clinicInfo['first_name'] . ' ' . $clinicInfo['last_name'];
              send_rep_assignment_denied($pdo, $repInfo['email'], $repInfo['first_name'] . ' ' . $repInfo['last_name'], $clinicName, $reason);
            }
          }

          $message = 'Assignment request denied.';
        }
        break;

      case 'record_payout':
        $repId = $_POST['rep_id'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? 'check';
        $referenceNumber = $_POST['reference_number'] ?? '';
        $periodStart = $_POST['period_start'] ?? null;
        $periodEnd = $_POST['period_end'] ?? null;
        $notes = $_POST['notes'] ?? '';

        if ($repId && $amount > 0) {
          // Validate payout amount doesn't exceed current balance
          require_once __DIR__ . '/../api/lib/commission.php';
          $balanceInfo = get_commission_balance($pdo, $repId);
          $currentBalance = $balanceInfo['balance'];

          if ($amount > $currentBalance + 0.01) { // Allow small rounding tolerance
            $error = 'Payout amount ($' . number_format($amount, 2) . ') exceeds available balance ($' . number_format($currentBalance, 2) . ')';
          } else {
            $pdo->prepare("INSERT INTO rep_commission_payouts (rep_id, amount, payment_method, reference_number, period_start, period_end, payout_date, processed_by, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE, ?, ?, NOW())")
                ->execute([$repId, $amount, $paymentMethod, $referenceNumber ?: null, $periodStart ?: null, $periodEnd ?: null, $admin['id'], $notes ?: null]);

            // Send notification to rep
            require_once __DIR__ . '/../api/lib/rep_notifications.php';
            $repStmt = $pdo->prepare("SELECT u.email, u.first_name, u.last_name FROM sales_reps sr JOIN users u ON u.id = sr.user_id WHERE sr.id = ?");
            $repStmt->execute([$repId]);
            $repInfo = $repStmt->fetch();
            if ($repInfo) {
              send_rep_payout_processed($pdo, $repInfo['email'], $repInfo['first_name'] . ' ' . $repInfo['last_name'], $amount, ucfirst($paymentMethod), $referenceNumber ?: null);
            }

            $message = 'Payout of $' . number_format($amount, 2) . ' recorded successfully.';
          }
        }
        break;

      case 'invite_rep':
        $firstName = trim($_POST['invite_first_name'] ?? '');
        $lastName = trim($_POST['invite_last_name'] ?? '');
        $email = strtolower(trim($_POST['invite_email'] ?? ''));
        $phone = trim($_POST['invite_phone'] ?? '');
        $companyName = trim($_POST['invite_company_name'] ?? '');
        $commissionRate = floatval($_POST['invite_commission_rate'] ?? 15) / 100;
        $personalNote = trim($_POST['invite_note'] ?? '');

        if (!$firstName || !$lastName || !$email || !$phone) {
          $error = 'Please fill in all required fields.';
          break;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $error = 'Invalid email address.';
          break;
        }

        // Check if email already exists
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->fetch()) {
          $error = 'A user with this email already exists.';
          break;
        }

        // Use transaction to ensure all records are created or none
        $pdo->beginTransaction();
        try {
            // Generate secure invite token
            $inviteToken = bin2hex(random_bytes(32));
            $inviteExpires = date('Y-m-d H:i:s', strtotime('+7 days'));

            // Create user record
            $userId = bin2hex(random_bytes(16));
            $pdo->prepare("
              INSERT INTO users (id, email, first_name, last_name, phone, role, password_hash, created_at, updated_at)
              VALUES (?, ?, ?, ?, ?, 'physician', '', NOW(), NOW())
            ")->execute([$userId, $email, $firstName, $lastName, $phone]);

            // Create sales_reps record
            $repId = bin2hex(random_bytes(16));
            // invited_by references users.id - only set if admin is from users table (superadmin or sales_rep)
            // admin_users (employees) don't have a users.id, so set to null
            $invitedBy = null;
            if ($admin['role'] === 'superadmin' || $admin['role'] === 'sales_rep') {
                // These roles come from users table, so $admin['id'] is a valid users.id
                $invitedBy = $admin['id'];
            }
            $pdo->prepare("
              INSERT INTO sales_reps (id, user_id, status, company_name, invite_token, invite_token_expires_at, invited_by, application_date, created_at, updated_at)
              VALUES (?, ?, 'invited', ?, ?, ?, ?, NOW(), NOW(), NOW())
            ")->execute([$repId, $userId, $companyName ?: null, $inviteToken, $inviteExpires, $invitedBy]);

            // Set commission rate (effective when invite is accepted)
            // set_by is NULL because admin_users.id is not a valid users.id FK
            $pdo->prepare("
              INSERT INTO rep_commission_rates (rep_id, rate, effective_date, set_by, notes, created_at)
              VALUES (?, ?, CURRENT_DATE, NULL, 'Set on invite', NOW())
            ")->execute([$repId, $commissionRate]);

            $pdo->commit();

            // Send invite email after successful commit
            sendInviteEmail($pdo, $repId, $inviteToken, $personalNote);

            $message = "Invite sent to {$firstName} {$lastName} at {$email}. The invite expires in 7 days.";
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        break;

      case 'add_rep_directly':
        $firstName = trim($_POST['direct_first_name'] ?? '');
        $lastName = trim($_POST['direct_last_name'] ?? '');
        $email = strtolower(trim($_POST['direct_email'] ?? ''));
        $phone = trim($_POST['direct_phone'] ?? '');
        $companyName = trim($_POST['direct_company_name'] ?? '');
        $commissionRate = floatval($_POST['direct_commission_rate'] ?? 25) / 100;
        $tempPassword = trim($_POST['direct_temp_password'] ?? '');
        $documentsConfirmed = !empty($_POST['documents_confirmed']);

        if (!$firstName || !$lastName || !$email || !$phone || !$tempPassword) {
          $error = 'Please fill in all required fields including temporary password.';
          break;
        }

        if (strlen($tempPassword) < 8) {
          $error = 'Temporary password must be at least 8 characters.';
          break;
        }

        if (!$documentsConfirmed) {
          $error = 'Please confirm that all required documents have been signed offline.';
          break;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $error = 'Invalid email address.';
          break;
        }

        // Check if email already exists
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->fetch()) {
          $error = 'A user with this email already exists.';
          break;
        }

        // Create user record with password
        $userId = bin2hex(random_bytes(16));
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $pdo->prepare("
          INSERT INTO users (id, email, first_name, last_name, phone, role, password_hash, created_at, updated_at)
          VALUES (?, ?, ?, ?, ?, 'physician', ?, NOW(), NOW())
        ")->execute([$userId, $email, $firstName, $lastName, $phone, $passwordHash]);

        // Create sales_reps record as active (approved_by is NULL because admin_users.id is not a valid users.id FK)
        $repId = bin2hex(random_bytes(16));
        $pdo->prepare("
          INSERT INTO sales_reps (id, user_id, status, company_name, approved_by, approved_date, application_date, created_at, updated_at)
          VALUES (?, ?, 'active', ?, NULL, NOW(), NOW(), NOW(), NOW())
        ")->execute([$repId, $userId, $companyName ?: null]);

        // Set commission rate (set_by is NULL because admin_users.id is not a valid users.id FK)
        $pdo->prepare("
          INSERT INTO rep_commission_rates (rep_id, rate, effective_date, set_by, notes, created_at)
          VALUES (?, ?, CURRENT_DATE, NULL, 'Set on direct add', NOW())
        ")->execute([$repId, $commissionRate]);

        // Record offline attestation for documents
        $docTypes = ['rep_agreement', 'baa'];
        foreach ($docTypes as $docType) {
          $docId = bin2hex(random_bytes(16));
          $pdo->prepare("
            INSERT INTO rep_signed_documents (id, rep_id, document_type, document_version, signature_name, signed_at, ip_address, source, uploaded_by, created_at)
            VALUES (?, ?, ?, '1.0', ?, NOW(), ?, 'offline_attestation', ?, NOW())
          ")->execute([$docId, $repId, $docType, $firstName . ' ' . $lastName, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $admin['id']]);
        }

        // Send welcome email with credentials
        sendDirectAddWelcomeEmail($pdo, $repId, $tempPassword);

        $message = "Sales rep {$firstName} {$lastName} has been created and is now active. Login credentials have been emailed.";
        break;

      case 'resend_invite':
        $repId = $_POST['rep_id'] ?? '';
        if ($repId) {
          // Generate new token and extend expiry
          $newToken = bin2hex(random_bytes(32));
          $newExpires = date('Y-m-d H:i:s', strtotime('+7 days'));

          $pdo->prepare("
            UPDATE sales_reps
            SET invite_token = ?, invite_token_expires_at = ?, updated_at = NOW()
            WHERE id = ? AND status = 'invited'
          ")->execute([$newToken, $newExpires, $repId]);

          sendInviteEmail($pdo, $repId, $newToken, '');

          $message = 'Invite has been resent with a new 7-day expiration.';
        }
        break;

      case 'cancel_invite':
        $repId = $_POST['rep_id'] ?? '';
        if ($repId) {
          // Get user_id first
          $userStmt = $pdo->prepare("SELECT user_id FROM sales_reps WHERE id = ? AND status = 'invited'");
          $userStmt->execute([$repId]);
          $userId = $userStmt->fetchColumn();

          if ($userId) {
            // Delete commission rates
            $pdo->prepare("DELETE FROM rep_commission_rates WHERE rep_id = ?")->execute([$repId]);

            // Delete sales_rep record
            $pdo->prepare("DELETE FROM sales_reps WHERE id = ?")->execute([$repId]);

            // Delete user record (they never activated)
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

            $message = 'Invite cancelled and records removed.';
          }
        }
        break;
    }
  } catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
  }
}

// Fetch data based on active tab
$activeReps = [];
$pendingApplications = [];
$invitedReps = [];
$assignmentRequests = [];
$payoutQueue = [];

// Active Reps Query
$activeRepsQuery = "
  SELECT sr.*,
    u.email, u.first_name, u.last_name, u.phone,
    (SELECT rate FROM rep_commission_rates WHERE rep_id = sr.id AND (effective_date IS NULL OR effective_date <= CURRENT_DATE) ORDER BY effective_date DESC NULLS LAST, created_at DESC LIMIT 1) as current_rate,
    (SELECT COUNT(*) FROM users WHERE assigned_rep_id = sr.id AND role IN ('physician', 'practice_admin') AND id NOT IN (SELECT user_id FROM sales_reps WHERE user_id IS NOT NULL)) as clinic_count,
    COALESCE((SELECT SUM(commission_amount) FROM rep_commission_ledger WHERE rep_id = sr.id), 0) as total_commission,
    COALESCE((SELECT SUM(amount) FROM rep_commission_payouts rcp WHERE rcp.rep_id = sr.id), 0) as total_paid
  FROM sales_reps sr
  JOIN users u ON u.id = sr.user_id
  WHERE sr.status IN ('active', 'suspended', 'terminated')
  ORDER BY sr.created_at DESC
";
$activeReps = $pdo->query($activeRepsQuery)->fetchAll();

// Pending Applications Query
$pendingQuery = "
  SELECT sr.*,
    u.email, u.first_name, u.last_name, u.phone,
    (SELECT COUNT(*) FROM rep_signed_documents WHERE rep_id = sr.id AND document_type = 'rep_agreement') as has_agreement,
    (SELECT COUNT(*) FROM rep_signed_documents WHERE rep_id = sr.id AND document_type = 'baa') as has_baa
  FROM sales_reps sr
  JOIN users u ON u.id = sr.user_id
  WHERE sr.status = 'pending'
  ORDER BY sr.application_date DESC
";
$pendingApplications = $pdo->query($pendingQuery)->fetchAll();

// Invited Reps Query
$invitedQuery = "
  SELECT sr.*,
    u.email, u.first_name, u.last_name, u.phone,
    (SELECT rate FROM rep_commission_rates WHERE rep_id = sr.id LIMIT 1) as commission_rate,
    iu.email as invited_by_email, iu.first_name as invited_by_first_name, iu.last_name as invited_by_last_name
  FROM sales_reps sr
  JOIN users u ON u.id = sr.user_id
  LEFT JOIN users iu ON iu.id = sr.invited_by
  WHERE sr.status = 'invited'
  ORDER BY sr.created_at DESC
";
$invitedReps = $pdo->query($invitedQuery)->fetchAll();

// Assignment Requests Query
$assignmentQuery = "
  SELECT ar.*,
    sr_requester.id as requester_rep_id,
    u_requester.first_name as requester_first_name, u_requester.last_name as requester_last_name,
    u_clinic.id as clinic_user_id, u_clinic.practice_name as clinic_name, u_clinic.npi as clinic_npi,
    u_clinic.first_name as clinic_first_name, u_clinic.last_name as clinic_last_name,
    u_current_rep.first_name as current_rep_first_name, u_current_rep.last_name as current_rep_last_name
  FROM rep_assignment_requests ar
  JOIN sales_reps sr_requester ON sr_requester.id = ar.rep_id
  JOIN users u_requester ON u_requester.id = sr_requester.user_id
  JOIN users u_clinic ON u_clinic.id = ar.clinic_id
  LEFT JOIN sales_reps sr_current ON sr_current.id = u_clinic.assigned_rep_id
  LEFT JOIN users u_current_rep ON u_current_rep.id = sr_current.user_id
  WHERE ar.status = 'pending'
  ORDER BY ar.created_at DESC
";
$assignmentRequests = $pdo->query($assignmentQuery)->fetchAll();

// Payout Queue Query
$payoutQuery = "
  SELECT sr.*,
    u.email, u.first_name, u.last_name,
    COALESCE((SELECT SUM(commission_amount) FROM rep_commission_ledger WHERE rep_id = sr.id), 0) as total_commission,
    COALESCE((SELECT SUM(amount) FROM rep_commission_payouts rcp WHERE rcp.rep_id = sr.id), 0) as total_paid,
    (SELECT MAX(payout_date) FROM rep_commission_payouts WHERE rep_id = sr.id) as last_payout_date,
    (SELECT amount FROM rep_commission_payouts WHERE rep_id = sr.id ORDER BY payout_date DESC LIMIT 1) as last_payout_amount
  FROM sales_reps sr
  JOIN users u ON u.id = sr.user_id
  WHERE sr.status = 'active'
  ORDER BY (COALESCE((SELECT SUM(commission_amount) FROM rep_commission_ledger WHERE rep_id = sr.id), 0) - COALESCE((SELECT SUM(amount) FROM rep_commission_payouts WHERE rep_id = sr.id), 0)) DESC
";
$payoutQueue = $pdo->query($payoutQuery)->fetchAll();

// Calculate summary stats for payouts
$totalUnpaid = 0;
$repsWithBalance = 0;
foreach ($payoutQueue as $rep) {
  $balance = $rep['total_commission'] - $rep['total_paid'];
  if ($balance > 0) {
    $totalUnpaid += $balance;
    $repsWithBalance++;
  }
}

$lastPayoutDate = $pdo->query("SELECT MAX(payout_date) as last_date FROM rep_commission_payouts")->fetch()['last_date'];

/**
 * Send approval email to rep
 */
function sendApprovalEmail(PDO $pdo, string $repId): void {
  require_once __DIR__ . '/../api/lib/email_sender.php';

  $stmt = $pdo->prepare("
    SELECT u.email, u.first_name, u.last_name
    FROM sales_reps sr
    JOIN users u ON u.id = sr.user_id
    WHERE sr.id = ?
  ");
  $stmt->execute([$repId]);
  $rep = $stmt->fetch();

  if (!$rep) return;

  $fullName = trim($rep['first_name'] . ' ' . $rep['last_name']);

  $bodyContent = <<<HTML
<h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 0 0 20px 0;">
  Welcome to the CollagenDirect Partner Program!
</h2>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  Hi {$fullName},
</p>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  Great news! Your application to become a CollagenDirect sales representative has been approved. You can now access your sales rep dashboard and start onboarding physicians.
</p>

<div style="text-align: center; margin: 30px 0;">
  <a href="https://collagendirect.health/login"
     style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #0d9488 0%, #10b981 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
    Access Your Dashboard
  </a>
</div>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  <strong>What's next?</strong>
</p>

<ul style="color: #374151; font-size: 14px; line-height: 1.8; margin: 0 0 20px 20px;">
  <li>Log in using your email and password</li>
  <li>Explore your dashboard and commission tracking</li>
  <li>Start reaching out to healthcare providers</li>
  <li>Add clinics to your portfolio</li>
</ul>

<p style="color: #6b7280; font-size: 14px; margin-top: 20px;">
  If you have any questions, contact us at <a href="mailto:partners@collagendirect.health" style="color: #0d9488;">partners@collagendirect.health</a>
</p>
HTML;

  $subject = "Your CollagenDirect Sales Rep Application is Approved!";
  $html = email_template($subject, $bodyContent);

  send_email($rep['email'], $fullName, $subject, $html);
}

/**
 * Send invite email to new rep
 */
function sendInviteEmail(PDO $pdo, string $repId, string $inviteToken, string $personalNote): void {
  require_once __DIR__ . '/../api/lib/email_sender.php';

  $stmt = $pdo->prepare("
    SELECT u.email, u.first_name, u.last_name
    FROM sales_reps sr
    JOIN users u ON u.id = sr.user_id
    WHERE sr.id = ?
  ");
  $stmt->execute([$repId]);
  $rep = $stmt->fetch();

  if (!$rep) return;

  $fullName = trim($rep['first_name'] . ' ' . $rep['last_name']);
  $inviteUrl = "https://collagendirect.health/rep-invite/{$inviteToken}";

  $noteHtml = $personalNote ? <<<HTML
<div style="background: #f0fdfa; border-left: 4px solid #0d9488; padding: 15px 20px; margin: 20px 0; border-radius: 0 8px 8px 0;">
  <p style="color: #115e59; font-size: 14px; margin: 0; font-style: italic;">"{$personalNote}"</p>
</div>
HTML : '';

  $bodyContent = <<<HTML
<h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 0 0 20px 0;">
  You're Invited to Join CollagenDirect
</h2>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  Hi {$fullName},
</p>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  You've been invited to join the CollagenDirect sales partner program. Complete your registration to start earning commissions on wound care products.
</p>

{$noteHtml}

<div style="text-align: center; margin: 30px 0;">
  <a href="{$inviteUrl}"
     style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #0d9488 0%, #10b981 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
    Complete Your Registration
  </a>
</div>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  <strong>What you'll need to do:</strong>
</p>

<ul style="color: #374151; font-size: 14px; line-height: 1.8; margin: 0 0 20px 20px;">
  <li>Create your password</li>
  <li>Sign the Sales Rep Agreement</li>
  <li>Sign the Business Associate Agreement (BAA)</li>
</ul>

<p style="color: #6b7280; font-size: 14px; margin-top: 20px;">
  This invite expires in 7 days. If you have any questions, contact us at <a href="mailto:partners@collagendirect.health" style="color: #0d9488;">partners@collagendirect.health</a>
</p>

<p style="color: #9ca3af; font-size: 12px; margin-top: 30px;">
  If the button doesn't work, copy and paste this link into your browser:<br>
  <a href="{$inviteUrl}" style="color: #0d9488; word-break: break-all;">{$inviteUrl}</a>
</p>
HTML;

  $subject = "You're Invited to Join CollagenDirect";
  $html = email_template($subject, $bodyContent);

  send_email($rep['email'], $fullName, $subject, $html);
}

/**
 * Send welcome email to directly-added rep with credentials
 */
function sendDirectAddWelcomeEmail(PDO $pdo, string $repId, string $tempPassword): void {
  require_once __DIR__ . '/../api/lib/email_sender.php';

  $stmt = $pdo->prepare("
    SELECT u.email, u.first_name, u.last_name
    FROM sales_reps sr
    JOIN users u ON u.id = sr.user_id
    WHERE sr.id = ?
  ");
  $stmt->execute([$repId]);
  $rep = $stmt->fetch();

  if (!$rep) return;

  $fullName = trim($rep['first_name'] . ' ' . $rep['last_name']);
  $loginUrl = "https://collagendirect.health/login";

  $bodyContent = <<<HTML
<h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 0 0 20px 0;">
  Welcome to CollagenDirect!
</h2>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  Hi {$fullName},
</p>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  Your CollagenDirect sales representative account has been created and is now active. You can start using your dashboard immediately to manage clinics and track your commissions.
</p>

<div style="background: #f8fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
  <p style="color: #6b7280; font-size: 14px; margin: 0 0 8px 0;"><strong>Your Login Credentials:</strong></p>
  <p style="color: #374151; font-size: 14px; margin: 0 0 4px 0;">Email: <strong>{$rep['email']}</strong></p>
  <p style="color: #374151; font-size: 14px; margin: 0;">Temporary Password: <strong style="font-family: monospace; background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">{$tempPassword}</strong></p>
</div>

<p style="color: #dc2626; font-size: 14px; margin: 0 0 20px 0;">
  <strong>Important:</strong> Please change your password after your first login for security.
</p>

<div style="text-align: center; margin: 30px 0;">
  <a href="{$loginUrl}"
     style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #0d9488 0%, #10b981 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
    Log In Now
  </a>
</div>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  <strong>What you can do:</strong>
</p>

<ul style="color: #374151; font-size: 14px; line-height: 1.8; margin: 0 0 20px 20px;">
  <li>View your dashboard and commission balance</li>
  <li>Request clinic assignments</li>
  <li>Track your earnings and payouts</li>
  <li>Access your signed documents</li>
</ul>

<p style="color: #6b7280; font-size: 14px; margin-top: 20px;">
  If you have any questions, contact us at <a href="mailto:partners@collagendirect.health" style="color: #0d9488;">partners@collagendirect.health</a>
</p>
HTML;

  $subject = "Welcome to CollagenDirect - Your Account is Ready";
  $html = email_template($subject, $bodyContent);

  send_email($rep['email'], $fullName, $subject, $html);
}

/**
 * Send rejection email to applicant
 */
function sendRejectionEmail(PDO $pdo, string $repId, string $reason): void {
  require_once __DIR__ . '/../api/lib/email_sender.php';

  $stmt = $pdo->prepare("
    SELECT u.email, u.first_name, u.last_name
    FROM sales_reps sr
    JOIN users u ON u.id = sr.user_id
    WHERE sr.id = ?
  ");
  $stmt->execute([$repId]);
  $rep = $stmt->fetch();

  if (!$rep) return;

  $fullName = trim($rep['first_name'] . ' ' . $rep['last_name']);

  $bodyContent = <<<HTML
<h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 0 0 20px 0;">
  Application Update
</h2>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  Hi {$fullName},
</p>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  Thank you for your interest in becoming a CollagenDirect sales representative. After reviewing your application, we are unable to approve it at this time.
</p>

<div style="background: #f8fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
  <p style="color: #6b7280; font-size: 14px; margin: 0 0 8px 0;"><strong>Reason:</strong></p>
  <p style="color: #374151; font-size: 14px; margin: 0;">{$reason}</p>
</div>

<p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
  If you believe this decision was made in error or would like to discuss further, please contact us.
</p>

<p style="color: #6b7280; font-size: 14px; margin-top: 20px;">
  Contact: <a href="mailto:partners@collagendirect.health" style="color: #0d9488;">partners@collagendirect.health</a>
</p>
HTML;

  $subject = "CollagenDirect Sales Rep Application Update";
  $html = email_template($subject, $bodyContent);

  send_email($rep['email'], $fullName, $subject, $html);
}
?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Rep Management</h2>
    <p class="text-gray-600 mt-1">Manage sales representatives, applications, and commissions</p>
  </div>
  <div class="relative">
    <button onclick="toggleAddRepDropdown()" class="btn btn-primary flex items-center gap-2">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
      </svg>
      Add Rep
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
    </button>
    <div id="addRepDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 z-20">
      <div class="py-1">
        <button onclick="openInviteModal()" class="w-full text-left px-4 py-3 hover:bg-gray-50 border-b border-gray-100">
          <div class="font-medium text-gray-900">Invite Rep</div>
          <div class="text-xs text-gray-500">Send invite email, rep signs docs online</div>
        </button>
        <button onclick="openDirectAddModal()" class="w-full text-left px-4 py-3 hover:bg-gray-50">
          <div class="font-medium text-gray-900">Add Rep Directly</div>
          <div class="text-xs text-gray-500">Docs signed offline, activate immediately</div>
        </button>
      </div>
    </div>
  </div>
</div>

<?php if ($message): ?>
  <div class="card p-4 mb-6 bg-green-50 border-green-200">
    <div class="flex items-center text-green-800">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
      <?= htmlspecialchars($message) ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="card p-4 mb-6 bg-red-50 border-red-200">
    <div class="flex items-center text-red-800">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      <?= htmlspecialchars($error) ?>
    </div>
  </div>
<?php endif; ?>

<!-- Tab Navigation -->
<div class="card mb-6">
  <div class="flex border-b border-gray-200 overflow-x-auto">
    <a href="?tab=active" class="px-6 py-4 text-sm font-medium whitespace-nowrap <?= $activeTab === 'active' ? 'text-teal-600 border-b-2 border-teal-600' : 'text-gray-500 hover:text-gray-700' ?>">
      Active Reps
      <span class="ml-2 px-2 py-0.5 text-xs rounded-full <?= $activeTab === 'active' ? 'bg-teal-100 text-teal-700' : 'bg-gray-100 text-gray-600' ?>">
        <?= count(array_filter($activeReps, fn($r) => $r['status'] === 'active')) ?>
      </span>
    </a>
    <a href="?tab=invited" class="px-6 py-4 text-sm font-medium whitespace-nowrap <?= $activeTab === 'invited' ? 'text-teal-600 border-b-2 border-teal-600' : 'text-gray-500 hover:text-gray-700' ?>">
      Invited
      <?php if (count($invitedReps) > 0): ?>
        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700"><?= count($invitedReps) ?></span>
      <?php endif; ?>
    </a>
    <a href="?tab=pending" class="px-6 py-4 text-sm font-medium whitespace-nowrap <?= $activeTab === 'pending' ? 'text-teal-600 border-b-2 border-teal-600' : 'text-gray-500 hover:text-gray-700' ?>">
      Pending Applications
      <?php if (count($pendingApplications) > 0): ?>
        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-700"><?= count($pendingApplications) ?></span>
      <?php endif; ?>
    </a>
    <a href="?tab=assignments" class="px-6 py-4 text-sm font-medium whitespace-nowrap <?= $activeTab === 'assignments' ? 'text-teal-600 border-b-2 border-teal-600' : 'text-gray-500 hover:text-gray-700' ?>">
      Assignment Requests
      <?php if (count($assignmentRequests) > 0): ?>
        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-700"><?= count($assignmentRequests) ?></span>
      <?php endif; ?>
    </a>
    <a href="?tab=payouts" class="px-6 py-4 text-sm font-medium whitespace-nowrap <?= $activeTab === 'payouts' ? 'text-teal-600 border-b-2 border-teal-600' : 'text-gray-500 hover:text-gray-700' ?>">
      Commission Payouts
    </a>
  </div>
</div>

<!-- Tab 1: Active Reps -->
<?php if ($activeTab === 'active'): ?>
<div class="card overflow-visible">
  <?php if (empty($activeReps)): ?>
    <div class="p-8 text-center">
      <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
      </svg>
      <h3 class="text-lg font-medium text-gray-900 mb-2">No Active Sales Reps</h3>
      <p class="text-gray-500">Once reps are approved, they'll appear here.</p>
    </div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr>
          <th>Rep Name</th>
          <th>Contact</th>
          <th>Status</th>
          <th>Commission Rate</th>
          <th>Clinics</th>
          <th>Total Commission</th>
          <th>Balance</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($activeReps as $rep): ?>
          <?php
            $balance = ($rep['total_commission'] ?? 0) - ($rep['total_paid'] ?? 0);
            $statusColors = [
              'active' => 'bg-green-100 text-green-800',
              'suspended' => 'bg-red-100 text-red-800',
              'terminated' => 'bg-gray-100 text-gray-800',
            ];
          ?>
          <tr>
            <td>
              <a href="/admin/sales-rep-detail.php?id=<?= $rep['id'] ?>" class="font-medium text-teal-600 hover:underline">
                <?= htmlspecialchars($rep['first_name'] . ' ' . $rep['last_name']) ?>
              </a>
              <?php if ($rep['company_name']): ?>
                <div class="text-xs text-gray-500"><?= htmlspecialchars($rep['company_name']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="text-sm"><?= htmlspecialchars($rep['email']) ?></div>
              <div class="text-xs text-gray-500"><?= htmlspecialchars($rep['phone'] ?? '') ?></div>
            </td>
            <td>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $statusColors[$rep['status']] ?? 'bg-gray-100' ?>">
                <?= ucfirst($rep['status']) ?>
              </span>
            </td>
            <td><?= $rep['current_rate'] ? number_format((float)$rep['current_rate'] * 100, 1) . '%' : '-' ?></td>
            <td><?= $rep['clinic_count'] ?></td>
            <td class="font-medium">$<?= number_format((float)($rep['total_commission'] ?? 0), 2) ?></td>
            <td class="<?= $balance > 0 ? 'text-amber-600 font-medium' : '' ?>">
              $<?= number_format($balance, 2) ?>
            </td>
            <td class="text-sm text-gray-500">
              <?= $rep['created_at'] ? date('M j, Y', strtotime($rep['created_at'])) : '-' ?>
            </td>
            <td>
              <div class="relative inline-block">
                <button onclick="toggleDropdown('dropdown-<?= $rep['id'] ?>')" class="btn text-xs">
                  Actions
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div id="dropdown-<?= $rep['id'] ?>" class="hidden absolute right-0 mt-1 w-40 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                  <a href="/admin/sales-rep-detail.php?id=<?= $rep['id'] ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">View Details</a>
                  <?php if ($rep['status'] === 'active'): ?>
                    <form method="POST" class="border-t border-gray-100">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="suspend_rep">
                      <input type="hidden" name="rep_id" value="<?= $rep['id'] ?>">
                      <button type="submit" class="w-full text-left px-4 py-2 text-sm text-amber-600 hover:bg-gray-50" onclick="return confirm('Suspend this rep?')">Suspend</button>
                    </form>
                  <?php elseif ($rep['status'] === 'suspended'): ?>
                    <form method="POST" class="border-t border-gray-100">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="reactivate_rep">
                      <input type="hidden" name="rep_id" value="<?= $rep['id'] ?>">
                      <button type="submit" class="w-full text-left px-4 py-2 text-sm text-green-600 hover:bg-gray-50">Reactivate</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($rep['status'] !== 'terminated'): ?>
                    <form method="POST" class="border-t border-gray-100">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="terminate_rep">
                      <input type="hidden" name="rep_id" value="<?= $rep['id'] ?>">
                      <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-50" onclick="return confirm('Terminate this rep? This cannot be undone.')">Terminate</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Tab: Invited Reps -->
<?php if ($activeTab === 'invited'): ?>
<div class="card overflow-visible">
  <?php if (empty($invitedReps)): ?>
    <div class="p-8 text-center">
      <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
      </svg>
      <h3 class="text-lg font-medium text-gray-900 mb-2">No Pending Invites</h3>
      <p class="text-gray-500">Invited reps will appear here until they complete registration.</p>
      <button onclick="openInviteModal()" class="btn btn-primary mt-4">
        <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        Invite a Rep
      </button>
    </div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Contact</th>
          <th>Commission Rate</th>
          <th>Invited By</th>
          <th>Sent</th>
          <th>Expires</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invitedReps as $rep):
          $isExpired = $rep['invite_token_expires_at'] && strtotime($rep['invite_token_expires_at']) < time();
        ?>
          <tr>
            <td>
              <div class="font-medium"><?= htmlspecialchars($rep['first_name'] . ' ' . $rep['last_name']) ?></div>
              <?php if ($rep['company_name']): ?>
                <div class="text-xs text-gray-500"><?= htmlspecialchars($rep['company_name']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="text-sm"><?= htmlspecialchars($rep['email']) ?></div>
              <div class="text-xs text-gray-500"><?= htmlspecialchars($rep['phone'] ?? '') ?></div>
            </td>
            <td><?= $rep['commission_rate'] ? number_format((float)$rep['commission_rate'] * 100, 1) . '%' : '25%' ?></td>
            <td class="text-sm text-gray-600">
              <?= $rep['invited_by_first_name'] ? htmlspecialchars($rep['invited_by_first_name'] . ' ' . $rep['invited_by_last_name']) : '-' ?>
            </td>
            <td class="text-sm text-gray-500">
              <?= date('M j, Y', strtotime($rep['created_at'])) ?>
            </td>
            <td>
              <?php if ($isExpired): ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Expired</span>
              <?php else: ?>
                <span class="text-sm text-gray-500"><?= date('M j, Y', strtotime($rep['invite_token_expires_at'])) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <div class="flex gap-2">
                <form method="POST" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="resend_invite">
                  <input type="hidden" name="rep_id" value="<?= $rep['id'] ?>">
                  <button type="submit" class="btn text-xs" title="<?= $isExpired ? 'Send new invite' : 'Resend invite' ?>">
                    <?= $isExpired ? 'Resend' : 'Resend' ?>
                  </button>
                </form>
                <form method="POST" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="cancel_invite">
                  <input type="hidden" name="rep_id" value="<?= $rep['id'] ?>">
                  <button type="submit" class="btn text-xs text-red-600" onclick="return confirm('Cancel this invite? This will remove the rep entirely.')">Cancel</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Tab 2: Pending Applications -->
<?php if ($activeTab === 'pending'): ?>
<div class="card overflow-visible">
  <?php if (empty($pendingApplications)): ?>
    <div class="p-8 text-center">
      <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
      </svg>
      <h3 class="text-lg font-medium text-gray-900 mb-2">No Pending Applications</h3>
      <p class="text-gray-500">New applications will appear here for review.</p>
    </div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr>
          <th>Applicant</th>
          <th>Contact</th>
          <th>Company</th>
          <th>Applied</th>
          <th>Agreement</th>
          <th>BAA</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pendingApplications as $app): ?>
          <tr>
            <td>
              <div class="font-medium"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></div>
            </td>
            <td>
              <div class="text-sm"><?= htmlspecialchars($app['email']) ?></div>
              <div class="text-xs text-gray-500"><?= htmlspecialchars($app['phone'] ?? '') ?></div>
            </td>
            <td><?= htmlspecialchars($app['company_name'] ?: '-') ?></td>
            <td class="text-sm text-gray-500">
              <?= $app['application_date'] ? date('M j, Y', strtotime($app['application_date'])) : '-' ?>
            </td>
            <td>
              <?php if ($app['has_agreement']): ?>
                <span class="text-green-600"><svg class="w-5 h-5 inline" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg></span>
              <?php else: ?>
                <span class="text-gray-300"><svg class="w-5 h-5 inline" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($app['has_baa']): ?>
                <span class="text-green-600"><svg class="w-5 h-5 inline" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg></span>
              <?php else: ?>
                <span class="text-gray-300"><svg class="w-5 h-5 inline" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg></span>
              <?php endif; ?>
            </td>
            <td>
              <button onclick="openReviewModal('<?= $app['id'] ?>', '<?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($app['email'], ENT_QUOTES) ?>')" class="btn btn-primary text-xs">
                Review
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Tab 3: Assignment Requests -->
<?php if ($activeTab === 'assignments'): ?>
<div class="card overflow-visible">
  <?php if (empty($assignmentRequests)): ?>
    <div class="p-8 text-center">
      <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
      </svg>
      <h3 class="text-lg font-medium text-gray-900 mb-2">No Pending Assignment Requests</h3>
      <p class="text-gray-500">When reps request clinic assignments, they'll appear here.</p>
    </div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr>
          <th>Requesting Rep</th>
          <th>Clinic</th>
          <th>Current Assignment</th>
          <th>Request Date</th>
          <th>Note</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($assignmentRequests as $req): ?>
          <tr>
            <td>
              <div class="font-medium"><?= htmlspecialchars($req['requester_first_name'] . ' ' . $req['requester_last_name']) ?></div>
            </td>
            <td>
              <div class="font-medium"><?= htmlspecialchars($req['clinic_name'] ?: ($req['clinic_first_name'] . ' ' . $req['clinic_last_name'])) ?></div>
              <div class="text-xs text-gray-500">NPI: <?= htmlspecialchars($req['clinic_npi'] ?? '-') ?></div>
            </td>
            <td>
              <?php if ($req['current_rep_first_name']): ?>
                <?= htmlspecialchars($req['current_rep_first_name'] . ' ' . $req['current_rep_last_name']) ?>
              <?php else: ?>
                <span class="text-gray-400">Unassigned</span>
              <?php endif; ?>
            </td>
            <td class="text-sm text-gray-500">
              <?= date('M j, Y', strtotime($req['created_at'])) ?>
            </td>
            <td class="text-sm text-gray-600 max-w-xs truncate">
              <?= htmlspecialchars($req['notes'] ?? '-') ?>
            </td>
            <td>
              <div class="flex gap-2">
                <form method="POST" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="approve_assignment">
                  <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                  <button type="submit" class="btn btn-primary text-xs" onclick="return confirm('Approve this assignment?')">Approve</button>
                </form>
                <button onclick="openDenyModal('<?= $req['id'] ?>')" class="btn text-xs text-red-600">Deny</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Tab 4: Commission Payouts -->
<?php if ($activeTab === 'payouts'): ?>
<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Total Unpaid Commission</p>
        <p class="text-2xl font-bold text-amber-600 mt-1">$<?= number_format($totalUnpaid, 2) ?></p>
      </div>
      <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
      </div>
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Reps with Balance</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $repsWithBalance ?></p>
      </div>
      <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
        </svg>
      </div>
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Last Payout Run</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">
          <?= $lastPayoutDate ? date('M j, Y', strtotime($lastPayoutDate)) : 'Never' ?>
        </p>
      </div>
      <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
      </div>
    </div>
  </div>
</div>

<div class="card overflow-visible">
  <?php if (empty($payoutQueue)): ?>
    <div class="p-8 text-center">
      <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
      </svg>
      <h3 class="text-lg font-medium text-gray-900 mb-2">No Active Reps</h3>
      <p class="text-gray-500">Commission payouts will appear here when reps earn commission.</p>
    </div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr>
          <th>Rep Name</th>
          <th>Total Commission</th>
          <th>Total Paid</th>
          <th>Balance</th>
          <th>Last Payout</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payoutQueue as $rep):
          $balance = ($rep['total_commission'] ?? 0) - ($rep['total_paid'] ?? 0);
        ?>
          <tr>
            <td>
              <a href="/admin/sales-rep-detail.php?id=<?= $rep['id'] ?>" class="font-medium text-teal-600 hover:underline">
                <?= htmlspecialchars($rep['first_name'] . ' ' . $rep['last_name']) ?>
              </a>
            </td>
            <td>$<?= number_format((float)($rep['total_commission'] ?? 0), 2) ?></td>
            <td>$<?= number_format((float)($rep['total_paid'] ?? 0), 2) ?></td>
            <td class="<?= $balance > 0 ? 'text-amber-600 font-bold' : '' ?>">
              $<?= number_format($balance, 2) ?>
            </td>
            <td class="text-sm text-gray-500">
              <?php if ($rep['last_payout_date']): ?>
                <?= date('M j, Y', strtotime($rep['last_payout_date'])) ?>
                <span class="text-gray-400">($<?= number_format((float)$rep['last_payout_amount'], 2) ?>)</span>
              <?php else: ?>
                <span class="text-gray-400">Never</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="flex gap-2">
                <a href="/admin/sales-rep-detail.php?id=<?= $rep['id'] ?>#ledger" class="btn text-xs">View Ledger</a>
                <?php if ($balance > 0): ?>
                  <button onclick="openPayoutModal('<?= $rep['id'] ?>', '<?= htmlspecialchars($rep['first_name'] . ' ' . $rep['last_name'], ENT_QUOTES) ?>', <?= $balance ?>)" class="btn btn-primary text-xs">Record Payout</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Review Application Modal -->
<dialog id="reviewModal" class="rounded-2xl w-full max-w-lg p-0 backdrop:bg-black/50">
  <form method="POST" class="p-0">
    <?= csrf_field() ?>
    <input type="hidden" name="rep_id" id="reviewRepId">

    <div class="p-6 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold text-gray-900">Review Application</h3>
        <button type="button" onclick="document.getElementById('reviewModal').close()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>
    </div>

    <div class="p-6 space-y-4">
      <div>
        <p class="text-sm text-gray-500">Applicant</p>
        <p class="font-medium" id="reviewName"></p>
        <p class="text-sm text-gray-600" id="reviewEmail"></p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Commission Rate</label>
        <div class="flex items-center gap-2">
          <input type="number" name="commission_rate" id="commissionRate" value="0.25" step="0.01" min="0" max="1" class="w-24">
          <span class="text-gray-600">= <span id="ratePercent">25</span>%</span>
        </div>
        <p class="text-xs text-gray-500 mt-1">Default: 25% (0.25)</p>
      </div>

      <div id="rejectionSection" class="hidden">
        <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason</label>
        <textarea name="rejection_reason" rows="3" class="w-full" placeholder="Provide a reason for rejection..."></textarea>
      </div>
    </div>

    <div class="p-6 border-t border-gray-200 flex gap-3">
      <button type="submit" name="action" value="approve_rep" class="flex-1 btn btn-primary">
        Approve & Activate
      </button>
      <button type="button" onclick="toggleRejection()" id="rejectToggle" class="flex-1 btn text-red-600">
        Reject
      </button>
      <button type="submit" name="action" value="reject_rep" id="confirmReject" class="hidden flex-1 btn bg-red-600 text-white border-red-600 hover:bg-red-700">
        Confirm Rejection
      </button>
    </div>
  </form>
</dialog>

<!-- Deny Assignment Modal -->
<dialog id="denyModal" class="rounded-2xl w-full max-w-md p-0 backdrop:bg-black/50">
  <form method="POST" class="p-0">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="deny_assignment">
    <input type="hidden" name="request_id" id="denyRequestId">

    <div class="p-6 border-b border-gray-200">
      <h3 class="text-lg font-bold text-gray-900">Deny Assignment Request</h3>
    </div>

    <div class="p-6">
      <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Denial</label>
      <textarea name="denial_reason" rows="3" required class="w-full" placeholder="Provide a reason..."></textarea>
    </div>

    <div class="p-6 border-t border-gray-200 flex gap-3">
      <button type="button" onclick="document.getElementById('denyModal').close()" class="flex-1 btn">Cancel</button>
      <button type="submit" class="flex-1 btn bg-red-600 text-white border-red-600 hover:bg-red-700">Deny Request</button>
    </div>
  </form>
</dialog>

<!-- Record Payout Modal -->
<dialog id="payoutModal" class="rounded-2xl w-full max-w-lg p-0 backdrop:bg-black/50">
  <form method="POST" class="p-0">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="record_payout">
    <input type="hidden" name="rep_id" id="payoutRepId">

    <div class="p-6 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold text-gray-900">Record Payout</h3>
        <button type="button" onclick="document.getElementById('payoutModal').close()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>
    </div>

    <div class="p-6 space-y-4">
      <div>
        <p class="text-sm text-gray-500">Rep</p>
        <p class="font-medium" id="payoutRepName"></p>
      </div>

      <div>
        <p class="text-sm text-gray-500">Current Balance</p>
        <p class="text-xl font-bold text-amber-600" id="payoutBalance"></p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Payout Amount</label>
          <input type="number" name="amount" id="payoutAmount" step="0.01" min="0" required class="w-full">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
          <select name="payment_method" class="w-full">
            <option value="check">Check</option>
            <option value="ach">ACH</option>
            <option value="wire">Wire</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Period Start</label>
          <input type="date" name="period_start" class="w-full">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Period End</label>
          <input type="date" name="period_end" class="w-full">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
        <input type="text" name="reference_number" class="w-full" placeholder="Check # or Transaction ID">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
        <textarea name="notes" rows="2" class="w-full" placeholder="Optional notes..."></textarea>
      </div>
    </div>

    <div class="p-6 border-t border-gray-200 flex gap-3">
      <button type="button" onclick="document.getElementById('payoutModal').close()" class="flex-1 btn">Cancel</button>
      <button type="submit" class="flex-1 btn btn-primary">Record Payout</button>
    </div>
  </form>
</dialog>

<!-- Invite Rep Modal -->
<dialog id="inviteModal" class="rounded-2xl w-full max-w-lg p-0 backdrop:bg-black/50">
  <form method="POST" class="p-0">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="invite_rep">

    <div class="p-6 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold text-gray-900">Invite Sales Rep</h3>
        <button type="button" onclick="document.getElementById('inviteModal').close()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>
      <p class="text-sm text-gray-500 mt-1">Send an invite email. They'll sign documents online.</p>
    </div>

    <div class="p-6 space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
          <input type="text" name="invite_first_name" required class="w-full">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
          <input type="text" name="invite_last_name" required class="w-full">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
        <input type="email" name="invite_email" required class="w-full">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
        <input type="tel" name="invite_phone" required class="w-full" placeholder="(555) 555-5555">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
        <input type="text" name="invite_company_name" class="w-full" placeholder="Optional">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Commission Rate</label>
        <div class="flex items-center gap-2">
          <input type="number" name="invite_commission_rate" value="25" step="1" min="0" max="100" class="w-24">
          <span class="text-gray-600">%</span>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Personal Note</label>
        <textarea name="invite_note" rows="2" class="w-full" placeholder="Optional message to include in the invite email..."></textarea>
      </div>
    </div>

    <div class="p-6 border-t border-gray-200 bg-gray-50">
      <div class="flex items-start gap-3 text-sm text-gray-600 mb-4">
        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span>An email will be sent with a link to complete registration and sign required documents. The invite expires in 7 days.</span>
      </div>
      <div class="flex gap-3">
        <button type="button" onclick="document.getElementById('inviteModal').close()" class="flex-1 btn">Cancel</button>
        <button type="submit" class="flex-1 btn btn-primary">Send Invite</button>
      </div>
    </div>
  </form>
</dialog>

<!-- Direct Add Rep Modal -->
<dialog id="directAddModal" class="rounded-2xl w-full max-w-lg p-0 backdrop:bg-black/50">
  <form method="POST" class="p-0">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_rep_directly">

    <div class="p-6 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold text-gray-900">Add Rep Directly</h3>
        <button type="button" onclick="document.getElementById('directAddModal').close()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>
      <p class="text-sm text-gray-500 mt-1">For reps who signed documents offline/in-person.</p>
    </div>

    <div class="p-6 space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
          <input type="text" name="direct_first_name" required class="w-full">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
          <input type="text" name="direct_last_name" required class="w-full">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
        <input type="email" name="direct_email" required class="w-full">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
        <input type="tel" name="direct_phone" required class="w-full" placeholder="(555) 555-5555">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
        <input type="text" name="direct_company_name" class="w-full" placeholder="Optional">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Temporary Password <span class="text-red-500">*</span></label>
        <div class="relative">
          <input type="text" name="direct_temp_password" id="tempPassword" required minlength="8" class="w-full pr-24">
          <button type="button" onclick="generatePassword()" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-teal-600 hover:text-teal-700 font-medium">Generate</button>
        </div>
        <p class="text-xs text-gray-500 mt-1">Minimum 8 characters. Rep should change on first login.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Commission Rate</label>
        <div class="flex items-center gap-2">
          <input type="number" name="direct_commission_rate" value="25" step="1" min="0" max="100" class="w-24">
          <span class="text-gray-600">%</span>
        </div>
      </div>

      <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
        <label class="flex items-start gap-3 cursor-pointer">
          <input type="checkbox" name="documents_confirmed" required class="mt-1">
          <span class="text-sm text-amber-800">
            <strong>I confirm</strong> that the Sales Rep Agreement and BAA have been signed offline and the original documents are on file.
          </span>
        </label>
      </div>
    </div>

    <div class="p-6 border-t border-gray-200 bg-gray-50">
      <div class="flex items-start gap-3 text-sm text-gray-600 mb-4">
        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span>The rep will be activated immediately and receive login credentials via email.</span>
      </div>
      <div class="flex gap-3">
        <button type="button" onclick="document.getElementById('directAddModal').close()" class="flex-1 btn">Cancel</button>
        <button type="submit" class="flex-1 btn btn-primary">Create & Activate Rep</button>
      </div>
    </div>
  </form>
</dialog>

<script>
function toggleDropdown(id) {
  const dropdown = document.getElementById(id);
  document.querySelectorAll('[id^="dropdown-"]').forEach(d => {
    if (d.id !== id) d.classList.add('hidden');
  });
  dropdown.classList.toggle('hidden');
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('[onclick*="toggleDropdown"]')) {
    document.querySelectorAll('[id^="dropdown-"]').forEach(d => d.classList.add('hidden'));
  }
});

function openReviewModal(repId, name, email) {
  document.getElementById('reviewRepId').value = repId;
  document.getElementById('reviewName').textContent = name;
  document.getElementById('reviewEmail').textContent = email;
  document.getElementById('rejectionSection').classList.add('hidden');
  document.getElementById('rejectToggle').classList.remove('hidden');
  document.getElementById('confirmReject').classList.add('hidden');
  document.getElementById('reviewModal').showModal();
}

function toggleRejection() {
  document.getElementById('rejectionSection').classList.remove('hidden');
  document.getElementById('rejectToggle').classList.add('hidden');
  document.getElementById('confirmReject').classList.remove('hidden');
}

document.getElementById('commissionRate').addEventListener('input', function() {
  document.getElementById('ratePercent').textContent = Math.round(this.value * 100);
});

function openDenyModal(requestId) {
  document.getElementById('denyRequestId').value = requestId;
  document.getElementById('denyModal').showModal();
}

function openPayoutModal(repId, name, balance) {
  document.getElementById('payoutRepId').value = repId;
  document.getElementById('payoutRepName').textContent = name;
  document.getElementById('payoutBalance').textContent = '$' + balance.toFixed(2);
  document.getElementById('payoutAmount').value = balance.toFixed(2);
  document.getElementById('payoutModal').showModal();
}

// Add Rep dropdown functions
function toggleAddRepDropdown() {
  const dropdown = document.getElementById('addRepDropdown');
  dropdown.classList.toggle('hidden');
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('[onclick*="toggleAddRepDropdown"]') && !e.target.closest('#addRepDropdown')) {
    document.getElementById('addRepDropdown')?.classList.add('hidden');
  }
});

function openInviteModal() {
  document.getElementById('addRepDropdown').classList.add('hidden');
  document.getElementById('inviteModal').showModal();
}

function openDirectAddModal() {
  document.getElementById('addRepDropdown').classList.add('hidden');
  document.getElementById('directAddModal').showModal();
}

function generatePassword() {
  const chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
  let password = '';
  for (let i = 0; i < 12; i++) {
    password += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  document.getElementById('tempPassword').value = password;
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>
