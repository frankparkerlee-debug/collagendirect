<?php
/**
 * Sales Rep: My Account
 *
 * View and update account information, business profile, and W9 documents.
 *
 * Phase 11 Update: Added Business Information tab and W9 submission workflow.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../../api/lib/commission.php';

$repId = $admin['rep_id'] ?? null;
if (!$repId) {
  echo '<div class="card p-6"><p class="text-red-600">Sales rep profile not found.</p></div>';
  require __DIR__ . '/_footer.php';
  exit;
}

// CSV Export for Payout History
if (isset($_GET['export']) && $_GET['export'] === 'payouts-csv') {
  $filename = 'payout_history_' . date('Y-m-d') . '.csv';

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=' . $filename);

  $output = fopen('php://output', 'w');

  // CSV Headers
  fputcsv($output, [
    'Date',
    'Amount',
    'Payment Method',
    'Reference #',
    'Period',
    'Status',
    'Notes'
  ]);

  // Fetch all payouts for this rep
  $exportQuery = "
    SELECT *
    FROM rep_commission_payouts
    WHERE rep_id = ?
    ORDER BY payout_date DESC
  ";
  $exportStmt = $pdo->prepare($exportQuery);
  $exportStmt->execute([$repId]);

  while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
    $period = '';
    if ($row['period_start'] && $row['period_end']) {
      $period = date('M j, Y', strtotime($row['period_start'])) . ' - ' . date('M j, Y', strtotime($row['period_end']));
    }
    fputcsv($output, [
      date('Y-m-d', strtotime($row['payout_date'])),
      number_format((float)$row['amount'], 2),
      ucfirst($row['payment_method'] ?? 'check'),
      $row['reference_number'] ?? '',
      $period,
      'Completed',
      $row['notes'] ?? ''
    ]);
  }

  fclose($output);
  exit;
}

$message = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'profile';

// Get full rep profile
$repStmt = $pdo->prepare("
  SELECT sr.*, u.email, u.first_name, u.last_name, u.phone
  FROM sales_reps sr
  JOIN users u ON u.id = sr.user_id
  WHERE sr.id = ?
");
$repStmt->execute([$repId]);
$repProfile = $repStmt->fetch();

// Get current commission rate and effective date
$rateStmt = $pdo->prepare("
  SELECT rate, effective_date, created_at
  FROM rep_commission_rates
  WHERE rep_id = ?
  AND (effective_date IS NULL OR effective_date <= CURRENT_DATE)
  ORDER BY effective_date DESC NULLS LAST
  LIMIT 1
");
$rateStmt->execute([$repId]);
$currentRate = $rateStmt->fetch();

// Get payout history
$payoutsStmt = $pdo->prepare("
  SELECT *
  FROM rep_commission_payouts
  WHERE rep_id = ?
  ORDER BY payout_date DESC
  LIMIT 20
");
$payoutsStmt->execute([$repId]);
$payouts = $payoutsStmt->fetchAll();

// Get current W9 submission (most recent)
$w9Stmt = $pdo->prepare("
  SELECT *
  FROM rep_w9_submissions
  WHERE rep_id = ?
  ORDER BY submitted_at DESC
  LIMIT 1
");
$w9Stmt->execute([$repId]);
$currentW9 = $w9Stmt->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $action = $_POST['action'] ?? '';

  // Handle password change
  if ($action === 'change_password') {
    $currentPw = $_POST['current_password'] ?? '';
    $newPw = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if (!$currentPw || !$newPw || !$confirmPw) {
      $error = 'Please fill in all password fields.';
    } elseif ($newPw !== $confirmPw) {
      $error = 'New passwords do not match.';
    } elseif (strlen($newPw) < 8) {
      $error = 'New password must be at least 8 characters.';
    } else {
      // Verify current password
      $userStmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
      $userStmt->execute([$admin['id']]);
      $user = $userStmt->fetch();

      if (!password_verify($currentPw, $user['password_hash'])) {
        $error = 'Current password is incorrect.';
      } else {
        $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")
            ->execute([password_hash($newPw, PASSWORD_DEFAULT), $admin['id']]);
        $message = 'Password updated successfully.';
      }
    }
  }

  // Handle business info update
  if ($action === 'update_business_info') {
    $activeTab = 'business';
    try {
      $updateFields = [
        'dba' => trim($_POST['dba'] ?? ''),
        'business_address_line1' => trim($_POST['business_address_line1'] ?? ''),
        'business_address_line2' => trim($_POST['business_address_line2'] ?? ''),
        'business_city' => trim($_POST['business_city'] ?? ''),
        'business_state' => trim($_POST['business_state'] ?? ''),
        'business_zip' => trim($_POST['business_zip'] ?? ''),
        'business_phone' => trim($_POST['business_phone'] ?? ''),
        'business_email' => trim($_POST['business_email'] ?? ''),
        'website' => trim($_POST['website'] ?? ''),
        'tax_classification' => trim($_POST['tax_classification'] ?? ''),
      ];

      // Validate business email if provided
      if ($updateFields['business_email'] && !filter_var($updateFields['business_email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid business email address.');
      }

      // Validate website URL if provided
      if ($updateFields['website'] && !filter_var($updateFields['website'], FILTER_VALIDATE_URL)) {
        // Try adding https:// if missing
        if (!str_starts_with($updateFields['website'], 'http')) {
          $updateFields['website'] = 'https://' . $updateFields['website'];
        }
        if (!filter_var($updateFields['website'], FILTER_VALIDATE_URL)) {
          throw new Exception('Invalid website URL.');
        }
      }

      $sql = "UPDATE sales_reps SET
        dba = ?,
        business_address_line1 = ?,
        business_address_line2 = ?,
        business_city = ?,
        business_state = ?,
        business_zip = ?,
        business_phone = ?,
        business_email = ?,
        website = ?,
        tax_classification = ?,
        updated_at = NOW()
        WHERE id = ?";

      $pdo->prepare($sql)->execute([
        $updateFields['dba'] ?: null,
        $updateFields['business_address_line1'] ?: null,
        $updateFields['business_address_line2'] ?: null,
        $updateFields['business_city'] ?: null,
        $updateFields['business_state'] ?: null,
        $updateFields['business_zip'] ?: null,
        $updateFields['business_phone'] ?: null,
        $updateFields['business_email'] ?: null,
        $updateFields['website'] ?: null,
        $updateFields['tax_classification'] ?: null,
        $repId
      ]);

      // Refresh profile
      $repStmt->execute([$repId]);
      $repProfile = $repStmt->fetch();

      $message = 'Business information updated successfully.';
    } catch (Exception $e) {
      $error = $e->getMessage();
    }
  }

  // Handle W9 upload
  if ($action === 'upload_w9') {
    $activeTab = 'documents';
    try {
      if (!isset($_FILES['w9_file']) || $_FILES['w9_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Please select a file to upload.');
      }

      $file = $_FILES['w9_file'];
      $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
      $maxSize = 10 * 1024 * 1024; // 10MB

      if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Please upload a PDF, JPG, or PNG file.');
      }

      if ($file['size'] > $maxSize) {
        throw new Exception('File too large. Maximum size is 10MB.');
      }

      // Create upload directory if it doesn't exist
      $uploadDir = __DIR__ . '/../../uploads/w9/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }

      // Generate unique filename
      $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
      $filename = $repId . '_' . date('Ymd_His') . '.' . $ext;
      $filepath = $uploadDir . $filename;

      if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save file. Please try again.');
      }

      // Get tax year from form or default to current year
      $taxYear = (int)($_POST['tax_year'] ?? date('Y'));

      // Insert W9 submission record
      $insertSql = "INSERT INTO rep_w9_submissions
        (rep_id, status, file_path, file_name, file_mime, tax_year, source, submitted_at)
        VALUES (?, 'pending', ?, ?, ?, ?, 'self_service', NOW())";

      $pdo->prepare($insertSql)->execute([
        $repId,
        'uploads/w9/' . $filename,
        $file['name'],
        $file['type'],
        $taxYear
      ]);

      // Update rep's W9 status
      $pdo->prepare("UPDATE sales_reps SET w9_status = 'pending', updated_at = NOW() WHERE id = ?")
          ->execute([$repId]);

      // Refresh data
      $w9Stmt->execute([$repId]);
      $currentW9 = $w9Stmt->fetch();
      $repStmt->execute([$repId]);
      $repProfile = $repStmt->fetch();

      // Send notification email to admin (using generic email function if available)
      if (function_exists('send_generic_email')) {
        // Get admin emails
        $adminEmails = $pdo->query("
          SELECT u.email FROM admin_users au
          JOIN users u ON au.user_id = u.id
          WHERE au.role IN ('superadmin', 'admin')
          AND u.status = 'active'
          LIMIT 5
        ")->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($adminEmails)) {
          $repName = $repProfile['first_name'] . ' ' . $repProfile['last_name'];
          $companyName = $repProfile['company_name'] ?: 'N/A';
          foreach ($adminEmails as $adminEmail) {
            send_generic_email(
              $adminEmail,
              "New W9 Submission - {$repName}",
              "A new W9 form has been submitted and requires review.\n\n" .
              "Distributor: {$repName}\n" .
              "Company: {$companyName}\n" .
              "Tax Year: {$taxYear}\n\n" .
              "Please log in to review and approve the submission."
            );
          }
        }
      }

      $message = 'W9 form uploaded successfully. It will be reviewed by our team.';
    } catch (Exception $e) {
      $error = $e->getMessage();
    }
  }
}

// Tax classification options
$taxClassifications = [
  '' => 'Select classification...',
  'sole_proprietor' => 'Individual/Sole Proprietor',
  'llc_single' => 'Single-member LLC',
  'llc_c' => 'LLC (C-Corp election)',
  'llc_s' => 'LLC (S-Corp election)',
  'llc_p' => 'LLC (Partnership)',
  'c_corp' => 'C Corporation',
  's_corp' => 'S Corporation',
  'partnership' => 'Partnership',
  'trust' => 'Trust/Estate',
  'other' => 'Other'
];

// US States
$usStates = [
  '' => 'Select state...',
  'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
  'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
  'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
  'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
  'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
  'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
  'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
  'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
  'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
  'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
  'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
  'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
  'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia'
];

// W9 status info
$w9Status = $repProfile['w9_status'] ?? 'none';
$w9StatusLabels = [
  'none' => ['label' => 'Not Submitted', 'class' => 'bg-gray-100 text-gray-800', 'icon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
  'pending' => ['label' => 'Pending Review', 'class' => 'bg-yellow-100 text-yellow-800', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
  'approved' => ['label' => 'Approved', 'class' => 'bg-green-100 text-green-800', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
  'rejected' => ['label' => 'Rejected', 'class' => 'bg-red-100 text-red-800', 'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
  'expired' => ['label' => 'Expired', 'class' => 'bg-orange-100 text-orange-800', 'icon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
];
$w9StatusInfo = $w9StatusLabels[$w9Status] ?? $w9StatusLabels['none'];
?>

<!-- Page Header -->
<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">My Account</h2>
  <p class="text-gray-600 mt-1">Manage your profile, business information, and documents</p>
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

<?php if ($w9Status === 'none' || $w9Status === 'rejected' || $w9Status === 'expired'): ?>
  <div class="card p-4 mb-6 <?= $w9Status === 'none' ? 'bg-yellow-50 border-yellow-200' : 'bg-red-50 border-red-200' ?>">
    <div class="flex items-start">
      <svg class="w-5 h-5 mr-3 mt-0.5 <?= $w9Status === 'none' ? 'text-yellow-600' : 'text-red-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
      </svg>
      <div>
        <p class="font-medium <?= $w9Status === 'none' ? 'text-yellow-800' : 'text-red-800' ?>">
          <?php if ($w9Status === 'none'): ?>
            W9 Required
          <?php elseif ($w9Status === 'rejected'): ?>
            W9 Rejected
          <?php else: ?>
            W9 Expired
          <?php endif; ?>
        </p>
        <p class="text-sm mt-1 <?= $w9Status === 'none' ? 'text-yellow-700' : 'text-red-700' ?>">
          <?php if ($w9Status === 'none'): ?>
            Please submit your W9 form to receive commission payouts. Go to the <a href="?tab=documents" class="underline font-medium">Documents tab</a> to upload.
          <?php elseif ($w9Status === 'rejected'): ?>
            Your W9 submission was rejected<?php if ($currentW9 && $currentW9['rejection_reason']): ?>: <?= htmlspecialchars($currentW9['rejection_reason']) ?><?php endif; ?>. Please submit a new W9 form.
          <?php else: ?>
            Your W9 has expired. Please submit a new W9 form to continue receiving commission payouts.
          <?php endif; ?>
        </p>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Tab Navigation -->
<div class="border-b border-gray-200 mb-6">
  <nav class="-mb-px flex space-x-8">
    <a href="?tab=profile"
       class="<?= $activeTab === 'profile' ? 'border-teal-500 text-teal-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
      Profile
    </a>
    <a href="?tab=business"
       class="<?= $activeTab === 'business' ? 'border-teal-500 text-teal-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
      Business Info
    </a>
    <a href="?tab=documents"
       class="<?= $activeTab === 'documents' ? 'border-teal-500 text-teal-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
      Documents
      <?php if ($w9Status === 'none' || $w9Status === 'rejected' || $w9Status === 'expired'): ?>
        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">!</span>
      <?php endif; ?>
    </a>
    <a href="?tab=payouts"
       class="<?= $activeTab === 'payouts' ? 'border-teal-500 text-teal-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
      Payouts
    </a>
  </nav>
</div>

<?php if ($activeTab === 'profile'): ?>
<!-- Profile Tab -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <!-- Profile Information -->
  <div class="card p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Profile Information</h3>

    <div class="space-y-4">
      <div class="flex justify-between py-3 border-b border-gray-100">
        <span class="text-gray-500">Name</span>
        <span class="font-medium"><?= htmlspecialchars($repProfile['first_name'] . ' ' . $repProfile['last_name']) ?></span>
      </div>
      <div class="flex justify-between py-3 border-b border-gray-100">
        <span class="text-gray-500">Email</span>
        <span class="font-medium"><?= htmlspecialchars($repProfile['email']) ?></span>
      </div>
      <div class="flex justify-between py-3 border-b border-gray-100">
        <span class="text-gray-500">Phone</span>
        <span class="font-medium"><?= htmlspecialchars($repProfile['phone'] ?: '-') ?></span>
      </div>
      <div class="flex justify-between py-3 border-b border-gray-100">
        <span class="text-gray-500">Company</span>
        <span class="font-medium"><?= htmlspecialchars($repProfile['company_name'] ?: '-') ?></span>
      </div>
      <div class="flex justify-between py-3 border-b border-gray-100">
        <span class="text-gray-500">Status</span>
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
          <?php
          switch ($repProfile['status']) {
            case 'active': echo 'bg-green-100 text-green-800'; break;
            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
            case 'suspended': echo 'bg-red-100 text-red-800'; break;
            default: echo 'bg-gray-100 text-gray-800';
          }
          ?>
        ">
          <?= ucfirst($repProfile['status']) ?>
        </span>
      </div>
      <div class="flex justify-between py-3 border-b border-gray-100">
        <span class="text-gray-500">Member Since</span>
        <span class="font-medium"><?= date('F j, Y', strtotime($repProfile['created_at'])) ?></span>
      </div>
      <?php if ($currentRate): ?>
        <div class="flex justify-between py-3">
          <span class="text-gray-500">Commission Rate</span>
          <span class="font-medium text-green-600"><?= number_format((float)$currentRate['rate'] * 100, 1) ?>%</span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Change Password</h3>

    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
          <input type="password" name="current_password" required class="w-full">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
          <input type="password" name="new_password" required minlength="8" class="w-full">
          <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
          <input type="password" name="confirm_password" required class="w-full">
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
      </div>
    </form>
  </div>
</div>

<!-- Commission Terms -->
<div class="card p-6 mt-6">
  <h3 class="text-lg font-medium text-gray-900 mb-4">Commission Terms</h3>

  <div class="bg-teal-50 rounded-lg p-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div>
        <p class="text-sm text-gray-500 mb-1">Your Commission Rate</p>
        <p class="text-2xl font-bold text-teal-600"><?= $currentRate ? number_format((float)$currentRate['rate'] * 100, 0) : '25' ?>%</p>
      </div>
      <div>
        <p class="text-sm text-gray-500 mb-1">Effective Since</p>
        <p class="text-lg font-medium text-gray-900">
          <?php
          if ($currentRate) {
            echo $currentRate['effective_date']
              ? date('F j, Y', strtotime($currentRate['effective_date']))
              : date('F j, Y', strtotime($currentRate['created_at']));
          } else {
            echo 'N/A';
          }
          ?>
        </p>
      </div>
      <div>
        <p class="text-sm text-gray-500 mb-1">Calculation Basis</p>
        <p class="text-sm text-gray-700">Commission is calculated on collected payments, not order placement.</p>
      </div>
    </div>
  </div>

  <p class="text-sm text-gray-500 mt-4">
    Your commission is calculated when payment is collected from your assigned clinics.
    Commission entries appear in your ledger after payment is recorded.
    <a href="/admin/rep/commissions.php" class="text-teal-600 hover:underline">View Commission Ledger &rarr;</a>
  </p>
</div>

<?php elseif ($activeTab === 'business'): ?>
<!-- Business Info Tab -->
<div class="card p-6">
  <h3 class="text-lg font-medium text-gray-900 mb-4">Business Information</h3>
  <p class="text-sm text-gray-500 mb-6">This information is used for tax reporting and commission payouts.</p>

  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_business_info">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Company Details -->
      <div class="space-y-4">
        <h4 class="font-medium text-gray-900 pb-2 border-b">Company Details</h4>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Legal Business Name</label>
          <input type="text" value="<?= htmlspecialchars($repProfile['company_name'] ?? '') ?>" disabled
                 class="w-full bg-gray-50 text-gray-500">
          <p class="text-xs text-gray-500 mt-1">Contact support to change your legal business name</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">DBA (Doing Business As)</label>
          <input type="text" name="dba" value="<?= htmlspecialchars($repProfile['dba'] ?? '') ?>"
                 class="w-full" placeholder="Optional trade name">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
          <input type="url" name="website" value="<?= htmlspecialchars($repProfile['website'] ?? '') ?>"
                 class="w-full" placeholder="https://example.com">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tax Classification</label>
          <select name="tax_classification" class="w-full">
            <?php foreach ($taxClassifications as $value => $label): ?>
              <option value="<?= $value ?>" <?= ($repProfile['tax_classification'] ?? '') === $value ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="text-xs text-gray-500 mt-1">As shown on your W9 form</p>
        </div>
      </div>

      <!-- Business Address -->
      <div class="space-y-4">
        <h4 class="font-medium text-gray-900 pb-2 border-b">Business Address</h4>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 1</label>
          <input type="text" name="business_address_line1" value="<?= htmlspecialchars($repProfile['business_address_line1'] ?? '') ?>"
                 class="w-full" placeholder="Street address">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 2</label>
          <input type="text" name="business_address_line2" value="<?= htmlspecialchars($repProfile['business_address_line2'] ?? '') ?>"
                 class="w-full" placeholder="Suite, unit, building (optional)">
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
            <input type="text" name="business_city" value="<?= htmlspecialchars($repProfile['business_city'] ?? '') ?>"
                   class="w-full">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
            <select name="business_state" class="w-full">
              <?php foreach ($usStates as $abbr => $name): ?>
                <option value="<?= $abbr ?>" <?= ($repProfile['business_state'] ?? '') === $abbr ? 'selected' : '' ?>>
                  <?= htmlspecialchars($name) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
          <input type="text" name="business_zip" value="<?= htmlspecialchars($repProfile['business_zip'] ?? '') ?>"
                 class="w-full" placeholder="12345" maxlength="10">
        </div>
      </div>

      <!-- Business Contact -->
      <div class="space-y-4 md:col-span-2">
        <h4 class="font-medium text-gray-900 pb-2 border-b">Business Contact</h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Business Phone</label>
            <input type="tel" name="business_phone" value="<?= htmlspecialchars($repProfile['business_phone'] ?? '') ?>"
                   class="w-full" placeholder="(555) 123-4567">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Business Email</label>
            <input type="email" name="business_email" value="<?= htmlspecialchars($repProfile['business_email'] ?? '') ?>"
                   class="w-full" placeholder="accounting@company.com">
            <p class="text-xs text-gray-500 mt-1">For invoices and tax documents (different from login email)</p>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-6 pt-4 border-t">
      <button type="submit" class="btn btn-primary">Save Business Information</button>
    </div>
  </form>
</div>

<?php elseif ($activeTab === 'documents'): ?>
<!-- Documents Tab -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <!-- W9 Status -->
  <div class="card p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">W9 Form Status</h3>

    <div class="flex items-start mb-6">
      <div class="flex-shrink-0">
        <span class="inline-flex items-center justify-center w-12 h-12 rounded-full <?= str_replace(['text-', '800'], ['bg-', '100'], $w9StatusInfo['class']) ?>">
          <svg class="w-6 h-6 <?= str_replace('bg-', 'text-', explode(' ', $w9StatusInfo['class'])[0]) ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $w9StatusInfo['icon'] ?>"></path>
          </svg>
        </span>
      </div>
      <div class="ml-4">
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium <?= $w9StatusInfo['class'] ?>">
          <?= $w9StatusInfo['label'] ?>
        </span>
        <?php if ($currentW9): ?>
          <p class="text-sm text-gray-500 mt-2">
            Submitted: <?= date('F j, Y', strtotime($currentW9['submitted_at'])) ?>
            <?php if ($currentW9['tax_year']): ?>
              (Tax Year <?= $currentW9['tax_year'] ?>)
            <?php endif; ?>
          </p>
          <?php if ($w9Status === 'approved' && $repProfile['w9_approved_at']): ?>
            <p class="text-sm text-green-600 mt-1">
              Approved: <?= date('F j, Y', strtotime($repProfile['w9_approved_at'])) ?>
            </p>
          <?php endif; ?>
          <?php if ($w9Status === 'rejected' && $currentW9['rejection_reason']): ?>
            <p class="text-sm text-red-600 mt-1">
              Reason: <?= htmlspecialchars($currentW9['rejection_reason']) ?>
            </p>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-sm text-gray-500 mt-2">No W9 on file. Please upload your W9 form.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($w9Status !== 'approved' && $w9Status !== 'pending'): ?>
      <div class="bg-yellow-50 rounded-lg p-4 mb-4">
        <p class="text-sm text-yellow-800">
          <strong>Important:</strong> A valid W9 form is required before commission payouts can be processed.
        </p>
      </div>
    <?php endif; ?>

    <?php if ($w9Status === 'approved'): ?>
      <div class="bg-green-50 rounded-lg p-4">
        <p class="text-sm text-green-800">
          Your W9 is on file and approved. You're all set to receive commission payouts.
        </p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Upload W9 -->
  <div class="card p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">
      <?= ($w9Status === 'approved') ? 'Update W9 Form' : 'Upload W9 Form' ?>
    </h3>

    <?php if ($w9Status === 'pending'): ?>
      <div class="bg-yellow-50 rounded-lg p-4 mb-4">
        <p class="text-sm text-yellow-800">
          Your W9 submission is currently under review. You'll be notified once it's approved.
        </p>
      </div>
    <?php else: ?>
      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_w9">

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Year</label>
            <select name="tax_year" class="w-full">
              <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">W9 Document</label>
            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-teal-400 transition-colors">
              <div class="space-y-1 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                  <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <div class="flex text-sm text-gray-600">
                  <label class="relative cursor-pointer rounded-md font-medium text-teal-600 hover:text-teal-500">
                    <span>Upload a file</span>
                    <input type="file" name="w9_file" accept=".pdf,.jpg,.jpeg,.png" required class="sr-only">
                  </label>
                  <p class="pl-1">or drag and drop</p>
                </div>
                <p class="text-xs text-gray-500">PDF, JPG, or PNG up to 10MB</p>
              </div>
            </div>
          </div>

          <div class="text-sm text-gray-500">
            <p class="mb-2"><strong>Need a blank W9 form?</strong></p>
            <a href="https://www.irs.gov/pub/irs-pdf/fw9.pdf" target="_blank"
               class="text-teal-600 hover:underline flex items-center">
              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
              </svg>
              Download IRS Form W-9
            </a>
          </div>

          <button type="submit" class="btn btn-primary w-full">Upload W9</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- Signed Documents -->
<div class="card p-6 mt-6">
  <h3 class="text-lg font-medium text-gray-900 mb-4">Signed Documents</h3>

  <?php
  $docsStmt = $pdo->prepare("
    SELECT document_type, document_version, signed_at, signature_text
    FROM rep_signed_documents
    WHERE rep_id = ?
    ORDER BY signed_at DESC
  ");
  $docsStmt->execute([$repId]);
  $docs = $docsStmt->fetchAll();
  ?>

  <?php if (empty($docs)): ?>
    <p class="text-gray-500">No signed documents on file.</p>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr>
            <th class="text-left py-2">Document</th>
            <th class="text-left py-2">Version</th>
            <th class="text-left py-2">Signed By</th>
            <th class="text-left py-2">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($docs as $doc): ?>
            <tr class="border-t border-gray-100">
              <td class="py-2">
                <?php
                $docNames = [
                  'rep_agreement' => 'Sales Rep Agreement',
                  'baa' => 'Business Associate Agreement',
                  'nda' => 'Non-Disclosure Agreement',
                  'w9' => 'W-9 Form',
                ];
                echo htmlspecialchars($docNames[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type'])));
                ?>
              </td>
              <td class="py-2"><?= htmlspecialchars($doc['document_version'] ?: '-') ?></td>
              <td class="py-2"><?= htmlspecialchars($doc['signature_text']) ?></td>
              <td class="py-2"><?= date('M j, Y', strtotime($doc['signed_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php elseif ($activeTab === 'payouts'): ?>
<!-- Payouts Tab -->
<div class="card p-6">
  <div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-medium text-gray-900">Payout History</h3>
    <?php if (!empty($payouts)): ?>
      <a href="?export=payouts-csv" class="btn text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
        Export CSV
      </a>
    <?php endif; ?>
  </div>

  <?php if ($w9Status !== 'approved'): ?>
    <div class="bg-yellow-50 rounded-lg p-4 mb-4">
      <p class="text-sm text-yellow-800">
        <strong>Note:</strong> Commission payouts require an approved W9 on file.
        <?php if ($w9Status !== 'pending'): ?>
          <a href="?tab=documents" class="underline">Upload your W9 &rarr;</a>
        <?php endif; ?>
      </p>
    </div>
  <?php endif; ?>

  <?php if (empty($payouts)): ?>
    <p class="text-gray-500">No payouts yet.</p>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr>
            <th class="text-left py-2">Date</th>
            <th class="text-right py-2">Amount</th>
            <th class="text-left py-2">Method</th>
            <th class="text-left py-2">Reference #</th>
            <th class="text-left py-2">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payouts as $payout): ?>
            <tr class="border-t border-gray-100">
              <td class="py-2"><?= date('M j, Y', strtotime($payout['payout_date'])) ?></td>
              <td class="py-2 text-right font-medium text-green-600">$<?= number_format((float)$payout['amount'], 2) ?></td>
              <td class="py-2"><?= ucfirst($payout['payment_method'] ?? 'check') ?></td>
              <td class="py-2"><?= htmlspecialchars($payout['reference_number'] ?? '-') ?></td>
              <td class="py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                  Completed
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="text-sm text-gray-500 mt-4">
      Showing last 20 payouts.
      <a href="/admin/rep/payouts.php" class="text-teal-600 hover:underline">View all payouts &rarr;</a>
    </p>
  <?php endif; ?>
</div>

<!-- Commission Summary -->
<div class="card p-6 mt-6">
  <h3 class="text-lg font-medium text-gray-900 mb-4">Commission Terms</h3>

  <div class="bg-teal-50 rounded-lg p-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div>
        <p class="text-sm text-gray-500 mb-1">Your Commission Rate</p>
        <p class="text-2xl font-bold text-teal-600"><?= $currentRate ? number_format((float)$currentRate['rate'] * 100, 0) : '25' ?>%</p>
      </div>
      <div>
        <p class="text-sm text-gray-500 mb-1">Effective Since</p>
        <p class="text-lg font-medium text-gray-900">
          <?php
          if ($currentRate) {
            echo $currentRate['effective_date']
              ? date('F j, Y', strtotime($currentRate['effective_date']))
              : date('F j, Y', strtotime($currentRate['created_at']));
          } else {
            echo 'N/A';
          }
          ?>
        </p>
      </div>
      <div>
        <p class="text-sm text-gray-500 mb-1">Calculation Basis</p>
        <p class="text-sm text-gray-700">Commission is calculated on collected payments, not order placement.</p>
      </div>
    </div>
  </div>

  <p class="text-sm text-gray-500 mt-4">
    <a href="/admin/rep/commissions.php" class="text-teal-600 hover:underline">View Commission Ledger &rarr;</a>
  </p>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
