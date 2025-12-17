<?php
/**
 * Employee Sales Rep: Onboard New Clinic
 *
 * Create a new clinic/practice that is automatically assigned to this employee rep.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

// Ensure adminId is an integer (admin_users.id is INTEGER, not VARCHAR)
$adminId = (int)$admin['id'];

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();

  $providerType = $_POST['provider_type'] ?? 'practice';
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $firstName = trim($_POST['first_name'] ?? '');
  $lastName = trim($_POST['last_name'] ?? '');
  $accountType = $_POST['account_type'] ?? 'referral';

  // Validate required fields
  if (!$email || !$password || !$firstName || !$lastName) {
    $error = 'Please fill in all required fields.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } elseif (strlen($password) < 8) {
    $error = 'Password must be at least 8 characters.';
  } else {
    // Check if email already exists
    $checkStmt = $pdo->prepare("SELECT id, employee_rep_id, practice_name, first_name, last_name FROM users WHERE email = ?");
    $checkStmt->execute([strtolower($email)]);
    $existingUser = $checkStmt->fetch();
    if ($existingUser) {
      if ($existingUser['employee_rep_id'] == $adminId) {
        $error = 'This clinic is already assigned to you.';
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
            $error = 'A clinic with this NPI is already assigned to you.';
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
      $userId = bin2hex(random_bytes(16));

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

      if ($providerType === 'practice') {
        // Creating a practice owner
        $practiceName = trim($_POST['practice_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = $_POST['state'] ?? '';
        $zip = trim($_POST['zip'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        $pdo->prepare("
          INSERT INTO users(
            id, email, password_hash, first_name, last_name, practice_name,
            address, city, state, zip, phone,
            role, user_type, account_type, status, can_manage_physicians,
            is_referral_only, has_dme_license, is_hybrid,
            employee_rep_id, rep_assignment_date, rep_assigned_by, rep_assigned_by_user_id,
            created_at, updated_at
          ) VALUES (?,LOWER(?),?,?,?,?,?,?,?,?,?,'practice_admin','practice_admin',?,'active',TRUE,?,?,?,?,NOW(),'employee_onboard',?,NOW(),NOW())
        ")->execute([
          $userId, $email, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName, $practiceName,
          $address, $city, $state, $zip, $phone,
          $dbAccountType,
          $isReferralOnly, $hasDmeLicense, $isHybrid,
          $adminId, $adminId
        ]);

        $message = 'Practice "' . htmlspecialchars($practiceName ?: $firstName . ' ' . $lastName) . '" has been created and assigned to you.';
      } else {
        // Creating a physician
        $npi = preg_replace('/\D/', '', $_POST['npi'] ?? '');
        $license = trim($_POST['license'] ?? '');
        $licenseState = $_POST['license_state'] ?? null;

        $pdo->prepare("
          INSERT INTO users(
            id, email, password_hash, first_name, last_name,
            npi, license, license_state,
            role, user_type, account_type, status,
            is_referral_only, has_dme_license, is_hybrid,
            employee_rep_id, rep_assignment_date, rep_assigned_by, rep_assigned_by_user_id,
            created_at, updated_at
          ) VALUES (?,LOWER(?),?,?,?,?,?,?,'physician','physician',?,'active',?,?,?,?,NOW(),'employee_onboard',?,NOW(),NOW())
        ")->execute([
          $userId, $email, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName,
          $npi ?: null, $license ?: null, $licenseState,
          $dbAccountType,
          $isReferralOnly, $hasDmeLicense, $isHybrid,
          $adminId, $adminId
        ]);

        $message = 'Physician "' . htmlspecialchars($firstName . ' ' . $lastName) . '" has been created and assigned to you.';
      }
    } catch (PDOException $e) {
      error_log("Employee rep onboard clinic error: " . $e->getMessage());
      $error = 'An error occurred while creating the clinic. Please try again.';
    }
  }
}

$states = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY','DC'];
?>

<!-- Page Header -->
<div class="mb-6">
  <a href="/admin/employee-rep/clinics.php" class="text-brand hover:underline text-sm">&larr; Back to My Clinics</a>
  <h2 class="text-2xl font-bold text-gray-900 mt-2">Onboard New Clinic</h2>
  <p class="text-gray-600 mt-1">Create a new clinic or physician account that will be automatically assigned to you.</p>
</div>

<?php if ($message): ?>
  <div class="card p-4 mb-6 bg-green-50 border-green-200">
    <div class="flex items-center text-green-800">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
      <?= $message ?>
    </div>
    <div class="mt-3">
      <a href="/admin/employee-rep/clinics.php" class="btn btn-primary">View My Clinics</a>
      <a href="/admin/employee-rep/onboard-clinic.php" class="btn ml-2">Onboard Another</a>
    </div>
  </div>
<?php endif; ?>

<?php if ($error === 'duplicate_email' || $error === 'duplicate_npi'): ?>
  <div class="card p-4 mb-6 bg-amber-50 border-amber-200">
    <div class="flex items-start text-amber-800">
      <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
      <div class="flex-1">
        <strong>Clinic Already Exists</strong>
        <p class="text-sm mt-1">
          A clinic with this <?= $error === 'duplicate_email' ? 'email' : 'NPI' ?> already exists: <strong><?= htmlspecialchars($existingClinicName ?? 'Unknown') ?></strong>
        </p>
        <p class="text-sm mt-2">
          Please contact an administrator if you need this clinic assigned to you.
        </p>
        <div class="mt-3">
          <a href="/admin/employee-rep/onboard-clinic.php" class="btn text-sm">Start Over</a>
        </div>
      </div>
    </div>
  </div>
<?php elseif ($error): ?>
  <div class="card p-4 mb-6 bg-red-50 border-red-200">
    <div class="flex items-center text-red-800">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      <?= $error ?>
    </div>
  </div>
<?php endif; ?>

<?php if (!$message && $error !== 'duplicate_email' && $error !== 'duplicate_npi'): ?>
<form method="POST" class="card p-6">
  <?= csrf_field() ?>

  <!-- Provider Type Selection -->
  <div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-3">What are you onboarding?</label>
    <div class="flex gap-4">
      <label class="flex-1 cursor-pointer">
        <input type="radio" name="provider_type" value="practice" checked class="sr-only peer" onchange="toggleProviderType()">
        <div class="p-4 border-2 rounded-lg text-center peer-checked:border-brand peer-checked:bg-brand-light transition">
          <svg class="w-8 h-8 mx-auto mb-2 text-gray-400 peer-checked:text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
          </svg>
          <span class="font-medium">Practice / Clinic</span>
          <p class="text-xs text-gray-500 mt-1">Multi-provider organization</p>
        </div>
      </label>
      <label class="flex-1 cursor-pointer">
        <input type="radio" name="provider_type" value="physician" class="sr-only peer" onchange="toggleProviderType()">
        <div class="p-4 border-2 rounded-lg text-center peer-checked:border-brand peer-checked:bg-brand-light transition">
          <svg class="w-8 h-8 mx-auto mb-2 text-gray-400 peer-checked:text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
          </svg>
          <span class="font-medium">Individual Physician</span>
          <p class="text-xs text-gray-500 mt-1">Solo practitioner</p>
        </div>
      </label>
    </div>
  </div>

  <!-- Account Type -->
  <div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">Account Type <span class="text-red-500">*</span></label>
    <select name="account_type" required class="w-full">
      <option value="referral">Referral Only (Insurance billing)</option>
      <option value="wholesale">Wholesale Only (Direct purchase)</option>
      <option value="both">Both Referral & Wholesale</option>
    </select>
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
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
        <input type="password" name="password" required minlength="8" placeholder="Min 8 characters">
      </div>
    </div>
  </div>

  <!-- Practice-specific fields -->
  <div id="practice-fields" class="border-t pt-6 mb-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Practice Information</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Practice Name</label>
        <input type="text" name="practice_name" value="<?= htmlspecialchars($_POST['practice_name'] ?? '') ?>">
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
        <input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
        <input type="text" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
        <select name="state">
          <option value="">Select...</option>
          <?php foreach ($states as $st): ?>
            <option value="<?= $st ?>" <?= ($_POST['state'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
        <input type="text" name="zip" value="<?= htmlspecialchars($_POST['zip'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
        <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
      </div>
    </div>
  </div>

  <!-- Physician-specific fields (hidden by default) -->
  <div id="physician-fields" class="border-t pt-6 mb-6 hidden">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Physician Credentials</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">NPI Number</label>
        <input type="text" name="npi" maxlength="10" pattern="[0-9]{10}" value="<?= htmlspecialchars($_POST['npi'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Medical License #</label>
        <input type="text" name="license" value="<?= htmlspecialchars($_POST['license'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">License State</label>
        <select name="license_state">
          <option value="">Select...</option>
          <?php foreach ($states as $st): ?>
            <option value="<?= $st ?>" <?= ($_POST['license_state'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <!-- Submit -->
  <div class="border-t pt-6">
    <button type="submit" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
      Create & Assign to Me
    </button>
    <a href="/admin/employee-rep/clinics.php" class="btn ml-2">Cancel</a>
  </div>
</form>

<script>
function toggleProviderType() {
  const isPractice = document.querySelector('input[name="provider_type"]:checked').value === 'practice';
  document.getElementById('practice-fields').classList.toggle('hidden', !isPractice);
  document.getElementById('physician-fields').classList.toggle('hidden', isPractice);
}
</script>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
