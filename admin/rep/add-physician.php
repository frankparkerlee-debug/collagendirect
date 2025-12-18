<?php
/**
 * Sales Rep: Add Physician to Clinic
 *
 * Add a physician to one of your assigned clinics.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../../api/lib/provider_welcome.php';

$repId = $admin['rep_id'] ?? null;
if (!$repId) {
  echo '<div class="card p-6"><p class="text-red-600">Sales rep profile not found.</p></div>';
  require __DIR__ . '/_footer.php';
  exit;
}

$message = '';
$error = '';

// Get assigned practices (only practice_admin accounts can have physicians added)
$practicesStmt = $pdo->prepare("
  SELECT id, practice_name, first_name, last_name, email
  FROM users
  WHERE assigned_rep_id = ?
  AND role = 'practice_admin'
  ORDER BY practice_name, last_name
");
$practicesStmt->execute([$repId]);
$practices = $practicesStmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();

  $practiceId = $_POST['practice_id'] ?? '';
  $email = trim($_POST['email'] ?? '');
  $firstName = trim($_POST['first_name'] ?? '');
  $lastName = trim($_POST['last_name'] ?? '');

  // Generate a secure temporary password (12 chars, mixed case + numbers)
  $tempPassword = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 12);
  $npi = preg_replace('/\D/', '', $_POST['npi'] ?? '');
  $license = trim($_POST['license'] ?? '');
  $licenseState = $_POST['license_state'] ?? null;

  // Address fields (optional - creates practice location if provided)
  $locationName = trim($_POST['location_name'] ?? '');
  $address = trim($_POST['address'] ?? '');
  $city = trim($_POST['city'] ?? '');
  $state = $_POST['state'] ?? '';
  $zip = trim($_POST['zip'] ?? '');
  $phone = trim($_POST['phone'] ?? '');

  // Validate practice belongs to this rep
  $validPractice = false;
  foreach ($practices as $p) {
    if ($p['id'] === $practiceId) {
      $validPractice = true;
      break;
    }
  }

  if (!$validPractice) {
    $error = 'Please select one of your assigned practices.';
  } elseif (!$email || !$firstName || !$lastName) {
    $error = 'Please fill in all required fields.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } else {
    // Check if email already exists
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->execute([strtolower($email)]);
    if ($checkStmt->fetch()) {
      $error = 'A user with this email already exists.';
    }
  }

  if (!$error) {
    try {
      $pdo->beginTransaction();

      $userId = bin2hex(random_bytes(16));

      // Create physician user
      $pdo->prepare("
        INSERT INTO users(
          id, email, password_hash, first_name, last_name,
          npi, license, license_state,
          role, user_type, account_type, status,
          assigned_rep_id, rep_assignment_date, rep_assigned_by, rep_assigned_by_user_id,
          created_at, updated_at
        ) VALUES (?,LOWER(?),?,?,?,?,?,?,'physician','physician','referral','active',?,NOW(),'self_onboard',?,NOW(),NOW())
      ")->execute([
        $userId, $email, password_hash($tempPassword, PASSWORD_DEFAULT), $firstName, $lastName,
        $npi ?: null, $license ?: null, $licenseState,
        $repId, $admin['id']
      ]);

      // Link to practice via practice_physicians table
      $pdo->prepare("
        INSERT INTO practice_physicians (practice_admin_id, physician_id, first_name, last_name, physician_email, physician_npi, physician_license, physician_license_state, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
      ")->execute([
        $practiceId, $userId, $firstName, $lastName, strtolower($email), $npi ?: null, $license ?: null, $licenseState
      ]);

      // Create practice location if address provided (linked to practice_admin)
      if ($address && $city && $state && $zip) {
        $locationId = bin2hex(random_bytes(16));
        $locName = $locationName ?: ($firstName . ' ' . $lastName . ' Office');
        $pdo->prepare("
          INSERT INTO practice_locations (id, user_id, location_name, address, city, state, zip, phone, is_primary, is_active, created_at, updated_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, FALSE, TRUE, NOW(), NOW())
        ")->execute([
          $locationId, $practiceId, $locName, $address, $city, $state, $zip, $phone ?: null
        ]);
      }

      $pdo->commit();

      // Send welcome email with temp password
      $fullName = $firstName . ' ' . $lastName;
      $emailSent = send_provider_welcome_email($email, $fullName, 'physician', $tempPassword);
      if ($emailSent) {
        error_log("[add-physician] Welcome email sent to $email");
        $message = 'Physician "' . htmlspecialchars($fullName) . '" has been added to the practice. A welcome email with login credentials has been sent.';
      } else {
        error_log("[add-physician] Failed to send welcome email to $email");
        $message = 'Physician "' . htmlspecialchars($fullName) . '" has been added to the practice. Warning: Welcome email could not be sent - please contact support.';
      }
    } catch (PDOException $e) {
      $pdo->rollBack();
      error_log("Add physician error: " . $e->getMessage());
      $error = 'An error occurred while adding the physician. Please try again.';
    }
  }
}

$states = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY','DC'];
?>

<!-- Page Header -->
<div class="mb-6">
  <a href="/admin/rep/clinics.php" class="text-brand hover:underline text-sm">&larr; Back to My Clinics</a>
  <h2 class="text-2xl font-bold text-gray-900 mt-2">Add Physician to Practice</h2>
  <p class="text-gray-600 mt-1">Add a new physician to one of your assigned practices.</p>
</div>

<?php if ($message): ?>
  <div class="card p-4 mb-6 bg-green-50 border-green-200">
    <div class="flex items-center text-green-800">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
      <?= $message ?>
    </div>
    <div class="mt-3">
      <a href="/admin/rep/clinics.php" class="btn btn-primary">View My Clinics</a>
      <a href="/admin/rep/add-physician.php" class="btn ml-2">Add Another</a>
    </div>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="card p-4 mb-6 bg-red-50 border-red-200">
    <div class="flex items-center text-red-800">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      <?= $error ?>
    </div>
  </div>
<?php endif; ?>

<?php if (empty($practices)): ?>
  <div class="card p-8 text-center">
    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
    </svg>
    <h3 class="text-lg font-medium text-gray-900 mb-2">No Practices Available</h3>
    <p class="text-gray-500 mb-4">You need to have at least one practice assigned to you before you can add physicians.</p>
    <a href="/admin/rep/onboard-clinic.php" class="btn btn-primary">Onboard a Practice First</a>
  </div>
<?php elseif (!$message): ?>
<form method="POST" class="card p-6">
  <?= csrf_field() ?>

  <!-- Practice Selection -->
  <div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">Select Practice <span class="text-red-500">*</span></label>
    <select name="practice_id" required class="w-full">
      <option value="">Choose a practice...</option>
      <?php foreach ($practices as $practice): ?>
        <option value="<?= htmlspecialchars($practice['id']) ?>" <?= ($_POST['practice_id'] ?? '') === $practice['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($practice['practice_name'] ?: $practice['first_name'] . ' ' . $practice['last_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Physician Information -->
  <div class="border-t pt-6 mb-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Physician Information</h3>
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
        <p class="text-xs text-gray-500 mt-1">A temporary password will be emailed to this address</p>
      </div>
    </div>
  </div>

  <!-- Credentials -->
  <div class="border-t pt-6 mb-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Credentials (Optional)</h3>
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

  <!-- Practice Location (Optional) -->
  <div class="border-t pt-6 mb-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Practice Location (Optional)</h3>
    <p class="text-sm text-gray-500 mb-4">If provided, this will create a new location for the practice that can be used for shipping orders.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Location Name</label>
        <input type="text" name="location_name" placeholder="e.g., Downtown Office" value="<?= htmlspecialchars($_POST['location_name'] ?? '') ?>">
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Street Address</label>
        <input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
        <input type="text" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
      </div>
      <div class="grid grid-cols-2 gap-4">
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
          <input type="text" name="zip" maxlength="10" value="<?= htmlspecialchars($_POST['zip'] ?? '') ?>">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
        <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
      </div>
    </div>
  </div>

  <!-- Submit -->
  <div class="border-t pt-6">
    <button type="submit" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
      Add Physician
    </button>
    <a href="/admin/rep/clinics.php" class="btn ml-2">Cancel</a>
  </div>
</form>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
