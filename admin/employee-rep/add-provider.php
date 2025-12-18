<?php
/**
 * Employee Sales Rep: Add Provider
 *
 * Create a new practice/clinic or physician that is automatically assigned to this employee rep.
 * Combines the previous onboard-clinic.php and add-physician.php into one page.
 *
 * Supports:
 * - New Practice (practice_admin) - standalone practice with optional physicians
 * - New Physician - standalone physician OR linked to existing practice
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../../api/lib/provider_welcome.php';

// Ensure adminId is an integer (admin_users.id is INTEGER, not VARCHAR)
$adminId = (int)$admin['id'];

$message = '';
$error = '';
$createdUserId = null;

// Get assigned practices for adding physicians to existing practices
$practicesStmt = $pdo->prepare("
  SELECT id, practice_name, first_name, last_name, email
  FROM users
  WHERE employee_rep_id = ?
  AND role = 'practice_admin'
  ORDER BY practice_name, last_name
");
$practicesStmt->execute([$adminId]);
$practices = $practicesStmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();

  $providerType = $_POST['provider_type'] ?? 'practice';
  $email = trim($_POST['email'] ?? '');
  $firstName = trim($_POST['first_name'] ?? '');
  $lastName = trim($_POST['last_name'] ?? '');
  $accountType = $_POST['account_type'] ?? 'referral';

  // Generate a secure temporary password (12 chars, mixed case + numbers)
  $tempPassword = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 12);

  // Validate required fields
  if (!$email || !$firstName || !$lastName) {
    $error = 'Please fill in all required fields.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } else {
    // Check if email already exists
    $checkStmt = $pdo->prepare("SELECT id, employee_rep_id, practice_name, first_name, last_name FROM users WHERE email = ?");
    $checkStmt->execute([strtolower($email)]);
    $existingUser = $checkStmt->fetch();
    if ($existingUser) {
      if ($existingUser['employee_rep_id'] == $adminId) {
        $error = 'This provider is already assigned to you.';
      } else {
        $existingClinicName = $existingUser['practice_name'] ?: $existingUser['first_name'] . ' ' . $existingUser['last_name'];
        $existingClinicId = $existingUser['id'];
        $error = 'duplicate_email';
      }
    }

    // Check for duplicate NPI if provided
    if (!$error) {
      $npi = preg_replace('/\D/', '', $_POST['npi'] ?? '');
      if ($npi && strlen($npi) === 10) {
        $npiStmt = $pdo->prepare("SELECT id, employee_rep_id, practice_name, first_name, last_name FROM users WHERE npi = ? AND deleted_at IS NULL");
        $npiStmt->execute([$npi]);
        $existingNpi = $npiStmt->fetch();
        if ($existingNpi) {
          if ($existingNpi['employee_rep_id'] == $adminId) {
            $error = 'A provider with this NPI is already assigned to you.';
          } else {
            $existingClinicName = $existingNpi['practice_name'] ?: $existingNpi['first_name'] . ' ' . $existingNpi['last_name'];
            $existingClinicId = $existingNpi['id'];
            $error = 'duplicate_npi';
          }
        }
      }
    }
  }

  if (!$error) {
    try {
      $pdo->beginTransaction();

      $userId = bin2hex(random_bytes(16));
      $createdUserId = $userId;

      // Map account type to database fields
      $dbAccountType = 'referral';
      $isReferralOnly = 0;
      $hasDmeLicense = 0;
      $isHybrid = 0;

      if ($accountType === 'referral') {
        $dbAccountType = 'referral';
        $isReferralOnly = 1;
      } elseif ($accountType === 'wholesale') {
        $dbAccountType = 'wholesale';
        $hasDmeLicense = 1;
      } elseif ($accountType === 'both') {
        $dbAccountType = 'referral';
        $isHybrid = 1;
        $hasDmeLicense = 1;
      }

      // Common fields
      $npi = preg_replace('/\D/', '', $_POST['npi'] ?? '');
      $ptan = trim($_POST['ptan'] ?? '');
      $license = trim($_POST['license'] ?? '');
      $licenseState = $_POST['license_state'] ?? null;
      $licenseExpiry = !empty($_POST['license_expiry']) ? $_POST['license_expiry'] : null;
      $taxId = trim($_POST['tax_id'] ?? '');
      $phone = trim($_POST['phone'] ?? '');

      // DME fields
      $dmeNumber = trim($_POST['dme_number'] ?? '');
      $dmeState = $_POST['dme_state'] ?? null;
      $dmeExpiry = !empty($_POST['dme_expiry']) ? $_POST['dme_expiry'] : null;

      // Address fields
      $practiceName = trim($_POST['practice_name'] ?? '');
      $address = trim($_POST['address'] ?? '');
      $city = trim($_POST['city'] ?? '');
      $state = $_POST['state'] ?? '';
      $zip = trim($_POST['zip'] ?? '');

      if ($providerType === 'practice') {
        // Creating a practice owner (practice_admin)
        $pdo->prepare("
          INSERT INTO users(
            id, email, password_hash, first_name, last_name, practice_name,
            address, city, state, zip, phone, tax_id,
            npi, ptan, license, license_state, license_expiry,
            dme_number, dme_state, dme_expiry,
            role, user_type, account_type, status, can_manage_physicians,
            is_referral_only, has_dme_license, is_hybrid,
            employee_rep_id, rep_assignment_date, rep_assigned_by, rep_assigned_by_user_id,
            created_at, updated_at
          ) VALUES (?,LOWER(?),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'practice_admin','practice_admin',?,'active',TRUE,?,?,?,?,NOW(),'employee_onboard',?,NOW(),NOW())
        ")->execute([
          $userId, $email, password_hash($tempPassword, PASSWORD_DEFAULT), $firstName, $lastName, $practiceName,
          $address, $city, $state, $zip, $phone, $taxId ?: null,
          $npi ?: null, $ptan ?: null, $license ?: null, $licenseState, $licenseExpiry,
          $dmeNumber ?: null, $dmeState, $dmeExpiry,
          $dbAccountType,
          $isReferralOnly, $hasDmeLicense, $isHybrid,
          $adminId, $adminId
        ]);

        // Create default practice location if address provided
        if ($address && $city && $state && $zip) {
          $locationId = bin2hex(random_bytes(16));
          $locName = $practiceName ?: ($firstName . ' ' . $lastName . ' Office');
          $pdo->prepare("
            INSERT INTO practice_locations (id, user_id, location_name, address, city, state, zip, phone, is_primary, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, TRUE, NOW(), NOW())
          ")->execute([
            $locationId, $userId, $locName, $address, $city, $state, $zip, $phone ?: null
          ]);
        }

        $pdo->commit();

        // Send welcome email with temp password
        $fullName = $firstName . ' ' . $lastName;
        $emailSent = send_provider_welcome_email($email, $fullName, 'practice_admin', $tempPassword);

        $displayName = $practiceName ?: $fullName;
        if ($emailSent) {
          $message = 'Practice "' . htmlspecialchars($displayName) . '" created successfully. Welcome email with login credentials sent to provider.';
        } else {
          $message = 'Practice "' . htmlspecialchars($displayName) . '" created successfully. Warning: Welcome email could not be sent - contact support.';
        }

      } elseif ($providerType === 'physician_to_practice') {
        // Adding a physician to an existing practice
        $practiceId = $_POST['practice_id'] ?? '';

        // Validate practice belongs to this rep
        $validPractice = false;
        foreach ($practices as $p) {
          if ($p['id'] === $practiceId) {
            $validPractice = true;
            break;
          }
        }

        if (!$validPractice) {
          throw new Exception('Please select one of your assigned practices.');
        }

        // Create physician user
        $pdo->prepare("
          INSERT INTO users(
            id, email, password_hash, first_name, last_name,
            npi, ptan, license, license_state, license_expiry,
            role, user_type, account_type, status,
            is_referral_only, has_dme_license, is_hybrid,
            employee_rep_id, rep_assignment_date, rep_assigned_by, rep_assigned_by_user_id,
            created_at, updated_at
          ) VALUES (?,LOWER(?),?,?,?,?,?,?,?,?,'physician','physician',?,'active',?,?,?,?,NOW(),'employee_onboard',?,NOW(),NOW())
        ")->execute([
          $userId, $email, password_hash($tempPassword, PASSWORD_DEFAULT), $firstName, $lastName,
          $npi ?: null, $ptan ?: null, $license ?: null, $licenseState, $licenseExpiry,
          $dbAccountType,
          $isReferralOnly, $hasDmeLicense, $isHybrid,
          $adminId, $adminId
        ]);

        // Link to practice via practice_physicians table
        $pdo->prepare("
          INSERT INTO practice_physicians (practice_admin_id, physician_id, first_name, last_name, physician_email, physician_npi, physician_ptan, physician_license, physician_license_state, physician_license_expiry, created_at, updated_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ")->execute([
          $practiceId, $userId, $firstName, $lastName, strtolower($email), $npi ?: null, $ptan ?: null, $license ?: null, $licenseState, $licenseExpiry
        ]);

        $pdo->commit();

        // Send welcome email with temp password
        $fullName = $firstName . ' ' . $lastName;
        $emailSent = send_provider_welcome_email($email, $fullName, 'physician', $tempPassword);

        if ($emailSent) {
          $message = 'Physician "' . htmlspecialchars($fullName) . '" added to practice. Welcome email with login credentials sent to provider.';
        } else {
          $message = 'Physician "' . htmlspecialchars($fullName) . '" added to practice. Warning: Welcome email could not be sent - contact support.';
        }

      } else {
        // Creating a standalone physician
        $pdo->prepare("
          INSERT INTO users(
            id, email, password_hash, first_name, last_name,
            address, city, state, zip, phone, tax_id,
            npi, ptan, license, license_state, license_expiry,
            role, user_type, account_type, status,
            is_referral_only, has_dme_license, is_hybrid,
            employee_rep_id, rep_assignment_date, rep_assigned_by, rep_assigned_by_user_id,
            created_at, updated_at
          ) VALUES (?,LOWER(?),?,?,?,?,?,?,?,?,?,?,?,?,?,?,'physician','physician',?,'active',?,?,?,?,NOW(),'employee_onboard',?,NOW(),NOW())
        ")->execute([
          $userId, $email, password_hash($tempPassword, PASSWORD_DEFAULT), $firstName, $lastName,
          $address, $city, $state, $zip, $phone, $taxId ?: null,
          $npi ?: null, $ptan ?: null, $license ?: null, $licenseState, $licenseExpiry,
          $dbAccountType,
          $isReferralOnly, $hasDmeLicense, $isHybrid,
          $adminId, $adminId
        ]);

        $pdo->commit();

        // Send welcome email with temp password
        $fullName = $firstName . ' ' . $lastName;
        $emailSent = send_provider_welcome_email($email, $fullName, 'physician', $tempPassword);

        if ($emailSent) {
          $message = 'Physician "' . htmlspecialchars($fullName) . '" created successfully. Welcome email with login credentials sent to provider.';
        } else {
          $message = 'Physician "' . htmlspecialchars($fullName) . '" created successfully. Warning: Welcome email could not be sent - contact support.';
        }
      }

    } catch (Exception $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      error_log("Employee rep add provider error: " . $e->getMessage());
      $error = $e->getMessage() ?: 'An error occurred while creating the provider. Please try again.';
    }
  }
}

$states = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY','DC'];
?>

<!-- Page Header -->
<div class="mb-6">
  <a href="/admin/employee-rep/clinics.php" class="text-brand hover:underline text-sm">&larr; Back to My Clinics</a>
  <h2 class="text-2xl font-bold text-gray-900 mt-2">Add Provider</h2>
  <p class="text-gray-600 mt-1">Create a new practice or physician account that will be assigned to you.</p>
</div>

<?php if ($message): ?>
  <div class="card p-4 mb-6 bg-green-50 border-green-200">
    <div class="flex items-start text-green-800">
      <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
      <div>
        <?= $message ?>
      </div>
    </div>
    <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
      <div class="flex items-start text-amber-800">
        <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <div class="text-sm">
          <p class="font-medium">Next Step Required</p>
          <p class="mt-1">The provider must log in and complete BAA & Terms agreement before they can use the platform. They will be prompted to sign these documents on their first login.</p>
        </div>
      </div>
    </div>
    <div class="mt-3">
      <a href="/admin/employee-rep/clinics.php" class="btn btn-primary">View My Clinics</a>
      <a href="/admin/employee-rep/add-provider.php" class="btn ml-2">Add Another</a>
    </div>
  </div>
<?php endif; ?>

<?php if ($error === 'duplicate_email' || $error === 'duplicate_npi'): ?>
  <div class="card p-4 mb-6 bg-amber-50 border-amber-200">
    <div class="flex items-start text-amber-800">
      <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
      <div class="flex-1">
        <strong>Provider Already Exists</strong>
        <p class="text-sm mt-1">
          A provider with this <?= $error === 'duplicate_email' ? 'email' : 'NPI' ?> already exists: <strong><?= htmlspecialchars($existingClinicName ?? 'Unknown') ?></strong>
        </p>
        <p class="text-sm mt-2">
          Please contact an administrator if you need this provider assigned to you.
        </p>
        <div class="mt-3">
          <a href="/admin/employee-rep/add-provider.php" class="btn text-sm">Start Over</a>
        </div>
      </div>
    </div>
  </div>
<?php elseif ($error && $error !== 'duplicate_email' && $error !== 'duplicate_npi'): ?>
  <div class="card p-4 mb-6 bg-red-50 border-red-200">
    <div class="flex items-center text-red-800">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      <?= htmlspecialchars($error) ?>
    </div>
  </div>
<?php endif; ?>

<?php if (!$message && $error !== 'duplicate_email' && $error !== 'duplicate_npi'): ?>
<form method="POST" class="card p-6" id="provider-form">
  <?= csrf_field() ?>

  <!-- Provider Type Selection -->
  <div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-3">What type of provider are you adding?</label>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <label class="cursor-pointer">
        <input type="radio" name="provider_type" value="practice" checked class="sr-only peer" onchange="toggleProviderType()">
        <div class="p-4 border-2 rounded-lg text-center peer-checked:border-brand peer-checked:bg-brand-light transition h-full">
          <svg class="w-8 h-8 mx-auto mb-2 text-gray-400 peer-checked:text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
          </svg>
          <span class="font-medium block">New Practice</span>
          <p class="text-xs text-gray-500 mt-1">Create a practice/clinic that can manage multiple physicians</p>
        </div>
      </label>
      <label class="cursor-pointer">
        <input type="radio" name="provider_type" value="physician" class="sr-only peer" onchange="toggleProviderType()">
        <div class="p-4 border-2 rounded-lg text-center peer-checked:border-brand peer-checked:bg-brand-light transition h-full">
          <svg class="w-8 h-8 mx-auto mb-2 text-gray-400 peer-checked:text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
          </svg>
          <span class="font-medium block">Standalone Physician</span>
          <p class="text-xs text-gray-500 mt-1">Independent practitioner not under a practice</p>
        </div>
      </label>
      <label class="cursor-pointer <?= empty($practices) ? 'opacity-50' : '' ?>">
        <input type="radio" name="provider_type" value="physician_to_practice" class="sr-only peer" onchange="toggleProviderType()" <?= empty($practices) ? 'disabled' : '' ?>>
        <div class="p-4 border-2 rounded-lg text-center peer-checked:border-brand peer-checked:bg-brand-light transition h-full <?= empty($practices) ? 'cursor-not-allowed' : '' ?>">
          <svg class="w-8 h-8 mx-auto mb-2 text-gray-400 peer-checked:text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
          </svg>
          <span class="font-medium block">Add to Practice</span>
          <p class="text-xs text-gray-500 mt-1"><?= empty($practices) ? 'No practices available' : 'Add physician to existing practice' ?></p>
        </div>
      </label>
    </div>
  </div>

  <!-- Practice Selection (for adding to existing practice) -->
  <div id="practice-selection" class="hidden mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">Select Practice <span class="text-red-500">*</span></label>
    <select name="practice_id" class="w-full" id="practice-select">
      <option value="">Choose a practice...</option>
      <?php foreach ($practices as $practice): ?>
        <option value="<?= htmlspecialchars($practice['id']) ?>">
          <?= htmlspecialchars($practice['practice_name'] ?: $practice['first_name'] . ' ' . $practice['last_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Account Type -->
  <div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">Account Type <span class="text-red-500">*</span></label>
    <select name="account_type" required class="w-full" id="account-type-select" onchange="toggleDMEFields()">
      <option value="referral">Referral Only (Insurance billing)</option>
      <option value="wholesale">Wholesale Only (Direct purchase - requires DME)</option>
      <option value="both">Both Referral & Wholesale</option>
    </select>
    <p class="text-xs text-gray-500 mt-1">Referral = Patient orders billed to insurance. Wholesale = Practice purchases directly.</p>
  </div>

  <!-- Contact Information -->
  <div class="border-t pt-6 mb-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Contact Information</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
        <input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
        <input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <p class="text-xs text-gray-500 mt-1">Temporary password will be emailed to provider</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
        <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
      </div>
    </div>
  </div>

  <!-- Practice-specific fields -->
  <div id="practice-fields" class="border-t pt-6 mb-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Practice Information</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Practice Name <span class="text-red-500">*</span></label>
        <input type="text" name="practice_name" id="practice-name-input" value="<?= htmlspecialchars($_POST['practice_name'] ?? '') ?>">
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Address <span class="text-red-500">*</span></label>
        <input type="text" name="address" id="address-input" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">City <span class="text-red-500">*</span></label>
        <input type="text" name="city" id="city-input" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">State <span class="text-red-500">*</span></label>
          <select name="state" id="state-input">
            <option value="">Select...</option>
            <?php foreach ($states as $st): ?>
              <option value="<?= $st ?>" <?= ($_POST['state'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code <span class="text-red-500">*</span></label>
          <input type="text" name="zip" id="zip-input" value="<?= htmlspecialchars($_POST['zip'] ?? '') ?>">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Tax ID (EIN)</label>
        <input type="text" name="tax_id" placeholder="XX-XXXXXXX" value="<?= htmlspecialchars($_POST['tax_id'] ?? '') ?>">
      </div>
    </div>
  </div>

  <!-- Physician Credentials -->
  <div id="credentials-section" class="border-t pt-6 mb-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Physician Credentials</h3>
    <p class="text-sm text-gray-500 mb-4">Required for referral orders. These credentials will be used for insurance verification.</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">NPI Number <span class="text-red-500" id="npi-required">*</span></label>
        <input type="text" name="npi" maxlength="10" pattern="[0-9]{10}" placeholder="10 digits" value="<?= htmlspecialchars($_POST['npi'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">PTAN</label>
        <input type="text" name="ptan" value="<?= htmlspecialchars($_POST['ptan'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Medical License # <span class="text-red-500" id="license-required">*</span></label>
        <input type="text" name="license" value="<?= htmlspecialchars($_POST['license'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">License State <span class="text-red-500" id="license-state-required">*</span></label>
        <select name="license_state">
          <option value="">Select...</option>
          <?php foreach ($states as $st): ?>
            <option value="<?= $st ?>" <?= ($_POST['license_state'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">License Expiry <span class="text-red-500" id="license-expiry-required">*</span></label>
        <input type="date" name="license_expiry" value="<?= htmlspecialchars($_POST['license_expiry'] ?? '') ?>">
      </div>
    </div>
  </div>

  <!-- DME Fields (for wholesale) -->
  <div id="dme-fields" class="border-t pt-6 mb-6 hidden">
    <h3 class="text-lg font-medium text-gray-900 mb-4">DME License Information</h3>
    <p class="text-sm text-gray-500 mb-4">Required for wholesale accounts to purchase products directly.</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">DME Number <span class="text-red-500">*</span></label>
        <input type="text" name="dme_number" id="dme-number-input" value="<?= htmlspecialchars($_POST['dme_number'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">DME State <span class="text-red-500">*</span></label>
        <select name="dme_state" id="dme-state-input">
          <option value="">Select...</option>
          <?php foreach ($states as $st): ?>
            <option value="<?= $st ?>" <?= ($_POST['dme_state'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">DME Expiry <span class="text-red-500">*</span></label>
        <input type="date" name="dme_expiry" id="dme-expiry-input" value="<?= htmlspecialchars($_POST['dme_expiry'] ?? '') ?>">
      </div>
    </div>
  </div>

  <!-- Agreement Notice -->
  <div class="border-t pt-6 mb-6">
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
      <div class="flex items-start">
        <svg class="w-5 h-5 text-blue-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div class="text-sm text-blue-800">
          <p class="font-medium mb-1">BAA & Terms Agreement Required</p>
          <p>The provider will be required to sign the Business Associate Agreement (BAA) and Terms of Service upon their first login before they can access the platform.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Submit -->
  <div class="border-t pt-6">
    <button type="submit" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
      Create Provider
    </button>
    <a href="/admin/employee-rep/clinics.php" class="btn ml-2">Cancel</a>
  </div>
</form>

<script>
function toggleProviderType() {
  const providerType = document.querySelector('input[name="provider_type"]:checked').value;

  const practiceFields = document.getElementById('practice-fields');
  const practiceSelection = document.getElementById('practice-selection');
  const practiceNameInput = document.getElementById('practice-name-input');
  const addressInput = document.getElementById('address-input');
  const cityInput = document.getElementById('city-input');
  const stateInput = document.getElementById('state-input');
  const zipInput = document.getElementById('zip-input');
  const practiceSelect = document.getElementById('practice-select');

  if (providerType === 'practice') {
    // New practice
    practiceFields.classList.remove('hidden');
    practiceSelection.classList.add('hidden');
    practiceNameInput.required = true;
    addressInput.required = true;
    cityInput.required = true;
    stateInput.required = true;
    zipInput.required = true;
    practiceSelect.required = false;
  } else if (providerType === 'physician_to_practice') {
    // Add to existing practice
    practiceFields.classList.add('hidden');
    practiceSelection.classList.remove('hidden');
    practiceNameInput.required = false;
    addressInput.required = false;
    cityInput.required = false;
    stateInput.required = false;
    zipInput.required = false;
    practiceSelect.required = true;
  } else {
    // Standalone physician
    practiceFields.classList.add('hidden');
    practiceSelection.classList.add('hidden');
    practiceNameInput.required = false;
    addressInput.required = false;
    cityInput.required = false;
    stateInput.required = false;
    zipInput.required = false;
    practiceSelect.required = false;
  }
}

function toggleDMEFields() {
  const accountType = document.getElementById('account-type-select').value;
  const dmeFields = document.getElementById('dme-fields');
  const credentialsSection = document.getElementById('credentials-section');

  const dmeNumber = document.getElementById('dme-number-input');
  const dmeState = document.getElementById('dme-state-input');
  const dmeExpiry = document.getElementById('dme-expiry-input');

  const npiRequired = document.getElementById('npi-required');
  const licenseRequired = document.getElementById('license-required');
  const licenseStateRequired = document.getElementById('license-state-required');
  const licenseExpiryRequired = document.getElementById('license-expiry-required');

  if (accountType === 'wholesale') {
    // Wholesale only - DME required, credentials optional
    dmeFields.classList.remove('hidden');
    dmeNumber.required = true;
    dmeState.required = true;
    dmeExpiry.required = true;

    // Hide required indicators for credentials
    npiRequired.classList.add('hidden');
    licenseRequired.classList.add('hidden');
    licenseStateRequired.classList.add('hidden');
    licenseExpiryRequired.classList.add('hidden');
  } else if (accountType === 'both') {
    // Both - DME and credentials required
    dmeFields.classList.remove('hidden');
    dmeNumber.required = true;
    dmeState.required = true;
    dmeExpiry.required = true;

    npiRequired.classList.remove('hidden');
    licenseRequired.classList.remove('hidden');
    licenseStateRequired.classList.remove('hidden');
    licenseExpiryRequired.classList.remove('hidden');
  } else {
    // Referral only - credentials required, no DME
    dmeFields.classList.add('hidden');
    dmeNumber.required = false;
    dmeState.required = false;
    dmeExpiry.required = false;

    npiRequired.classList.remove('hidden');
    licenseRequired.classList.remove('hidden');
    licenseStateRequired.classList.remove('hidden');
    licenseExpiryRequired.classList.remove('hidden');
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  toggleProviderType();
  toggleDMEFields();
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
