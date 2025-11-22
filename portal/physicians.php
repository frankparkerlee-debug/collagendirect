<?php
/**
 * Physician Roster Management - Inline Editing
 * Allows practice admins to manage multiple physicians and their credentials
 */

require_once __DIR__ . '/../admin/db.php';
require_once __DIR__ . '/../admin/auth.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
  header('Location: /login');
  exit;
}

// Check if user is practice admin
$userStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userRole = $userStmt->fetchColumn();
$isPracticeAdmin = ($userRole === 'practice_admin');

if (!$isPracticeAdmin) {
  die('Only practice administrators can manage physician rosters.');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    try {
      $stmt = $pdo->prepare("
        INSERT INTO practice_physicians (
          practice_user_id, physician_name, npi, license_number,
          address, city, state, zip, phone, signature_text
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");

      $stmt->execute([
        $userId,
        $_POST['physician_name'],
        $_POST['npi'] ?? null,
        $_POST['license_number'] ?? null,
        $_POST['address'] ?? null,
        $_POST['city'] ?? null,
        $_POST['state'] ?? null,
        $_POST['zip'] ?? null,
        $_POST['phone'] ?? null,
        $_POST['signature_text'] ?? null
      ]);

      $_SESSION['success_msg'] = 'Physician added successfully';
      header('Location: /portal/?page=profile#physicians');
      exit;

    } catch (Exception $e) {
      $_SESSION['error_msg'] = 'Error adding physician: ' . $e->getMessage();
    }
  }

  elseif ($action === 'edit') {
    try {
      $stmt = $pdo->prepare("
        UPDATE practice_physicians SET
          physician_name = ?,
          npi = ?,
          license_number = ?,
          address = ?,
          city = ?,
          state = ?,
          zip = ?,
          phone = ?,
          signature_text = ?,
          updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND practice_user_id = ?
      ");

      $stmt->execute([
        $_POST['physician_name'],
        $_POST['npi'] ?? null,
        $_POST['license_number'] ?? null,
        $_POST['address'] ?? null,
        $_POST['city'] ?? null,
        $_POST['state'] ?? null,
        $_POST['zip'] ?? null,
        $_POST['phone'] ?? null,
        $_POST['signature_text'] ?? null,
        $_POST['physician_id'],
        $userId
      ]);

      $_SESSION['success_msg'] = 'Physician updated successfully';
      header('Location: /portal/?page=profile#physicians');
      exit;

    } catch (Exception $e) {
      $_SESSION['error_msg'] = 'Error updating physician: ' . $e->getMessage();
    }
  }

  elseif ($action === 'delete') {
    try {
      // Soft delete - set is_active to false
      $stmt = $pdo->prepare("
        UPDATE practice_physicians
        SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND practice_user_id = ?
      ");

      $stmt->execute([$_POST['physician_id'], $userId]);

      $_SESSION['success_msg'] = 'Physician deactivated';
      header('Location: /portal/?page=profile#physicians');
      exit;

    } catch (Exception $e) {
      $_SESSION['error_msg'] = 'Error deactivating physician: ' . $e->getMessage();
    }
  }

  elseif ($action === 'reactivate') {
    try {
      $stmt = $pdo->prepare("
        UPDATE practice_physicians
        SET is_active = TRUE, updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND practice_user_id = ?
      ");

      $stmt->execute([$_POST['physician_id'], $userId]);

      $_SESSION['success_msg'] = 'Physician reactivated';
      header('Location: /portal/?page=profile#physicians');
      exit;

    } catch (Exception $e) {
      $_SESSION['error_msg'] = 'Error reactivating physician: ' . $e->getMessage();
    }
  }
}

// Check if table exists first
$tableExists = false;
try {
  $tableCheck = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'practice_physicians'
    )
  ");
  $tableExists = $tableCheck->fetchColumn();
} catch (Exception $e) {
  $tableExists = false;
}

// Fetch physicians if table exists
$physicians = [];
if ($tableExists) {
  try {
    $physiciansStmt = $pdo->prepare("
      SELECT * FROM practice_physicians
      WHERE practice_user_id = ?
      ORDER BY is_active DESC, physician_name ASC
    ");
    $physiciansStmt->execute([$userId]);
    $physicians = $physiciansStmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    $_SESSION['error_msg'] = 'Error loading physicians: ' . $e->getMessage();
  }
}
?>

<!-- Google Maps Places API -->
<?php if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&libraries=places"></script>
<?php endif; ?>

<style>
.physicians-container {
  max-width: 1600px;
  margin: 0 auto;
  padding: 2rem;
}

.physicians-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.physicians-table thead {
  background: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
}

.physicians-table th {
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
  color: #64748b;
}

.physicians-table td {
  padding: 1rem;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: middle;
}

.physicians-table tbody tr:hover {
  background: #f8fafc;
}

.physicians-table tbody tr.inactive {
  opacity: 0.5;
  background: #f8fafc;
}

.physician-input {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 0.875rem;
}

.physician-input:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.inactive-badge {
  background: #fca5a5;
  color: #991b1b;
  padding: 0.25rem 0.75rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  display: inline-block;
}

.btn {
  cursor: pointer;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  background: white;
  transition: all 0.2s;
  text-decoration: none;
  display: inline-block;
}

.btn-primary {
  background: #10b981;
  color: white;
  border-color: #10b981;
}

.btn-primary:hover {
  background: #059669;
}

.btn-secondary {
  background: white;
  color: #64748b;
}

.btn-secondary:hover {
  background: #f1f5f9;
}

.btn-danger {
  background: #ef4444;
  color: white;
  border-color: #ef4444;
}

.btn-danger:hover {
  background: #dc2626;
}

.btn-small {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
}

.success-msg {
  padding: 1rem;
  background: #d1fae5;
  border: 1px solid #10b981;
  border-radius: 6px;
  color: #065f46;
  margin-bottom: 1.5rem;
}

.error-msg {
  padding: 1rem;
  background: #fee;
  border: 1px solid #dc3545;
  border-radius: 6px;
  color: #991b1b;
  margin-bottom: 1.5rem;
}

.setup-warning {
  padding: 2rem;
  background: white;
  border: 1px solid #fbbf24;
  border-radius: 8px;
  text-align: center;
}
</style>

<div class="physicians-container">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
      <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
        Physician Roster
      </h1>
      <p style="color: var(--muted); font-size: 0.875rem;">
        Manage physicians and rotate credentials for orders
      </p>
    </div>

    <?php if ($tableExists && count($physicians) > 0): ?>
      <button onclick="showAddRow()" class="btn btn-primary">
        + Add Physician
      </button>
    <?php endif; ?>
  </div>

  <?php if (isset($_SESSION['success_msg'])): ?>
    <div class="success-msg">
      <?= htmlspecialchars($_SESSION['success_msg']) ?>
    </div>
    <?php unset($_SESSION['success_msg']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error_msg'])): ?>
    <div class="error-msg">
      <?= htmlspecialchars($_SESSION['error_msg']) ?>
    </div>
    <?php unset($_SESSION['error_msg']); ?>
  <?php endif; ?>

  <?php if (!$tableExists): ?>
    <div class="setup-warning">
      <h2 style="font-size: 1.25rem; font-weight: 600; color: #f59e0b; margin-bottom: 1rem;">
        ⚠️ Setup Required
      </h2>
      <p style="color: var(--ink-light); margin-bottom: 1.5rem;">
        The physician roster feature requires database setup. Click the button below to initialize.
      </p>
      <a href="/admin/migrate-create-practice-physicians.php" class="btn btn-primary">
        Run Setup →
      </a>
    </div>

  <?php elseif (count($physicians) === 0): ?>
    <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 3rem; text-align: center;">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem; color: var(--ink);">
        No Physicians Yet
      </h2>
      <p style="color: var(--muted); margin-bottom: 2rem;">
        Add your first physician to start managing credentials for orders
      </p>
      <button onclick="showAddRow()" class="btn btn-primary">
        + Add First Physician
      </button>
    </div>

  <?php else: ?>
    <table class="physicians-table">
      <thead>
        <tr>
          <th>Physician Name</th>
          <th>NPI</th>
          <th>License #</th>
          <th>Address</th>
          <th>City, State ZIP</th>
          <th>Phone</th>
          <th>Signature Text</th>
          <th style="text-align: center;">Status</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($physicians as $physician): ?>
          <tr class="<?= $physician['is_active'] ? '' : 'inactive' ?>">
            <td style="font-weight: 600;"><?= htmlspecialchars($physician['physician_name']) ?></td>
            <td><?= htmlspecialchars($physician['npi'] ?? '-') ?></td>
            <td><?= htmlspecialchars($physician['license_number'] ?? '-') ?></td>
            <td><?= htmlspecialchars($physician['address'] ?? '-') ?></td>
            <td>
              <?php
              $location = [];
              if ($physician['city']) $location[] = $physician['city'];
              if ($physician['state']) $location[] = $physician['state'];
              if ($physician['zip']) $location[] = $physician['zip'];
              echo htmlspecialchars(implode(', ', $location) ?: '-');
              ?>
            </td>
            <td><?= htmlspecialchars($physician['phone'] ?? '-') ?></td>
            <td style="font-style: italic; color: #64748b;">
              <?= htmlspecialchars($physician['signature_text'] ?? '-') ?>
            </td>
            <td style="text-align: center;">
              <?php if (!$physician['is_active']): ?>
                <span class="inactive-badge">Inactive</span>
              <?php endif; ?>
            </td>
            <td style="text-align: right;">
              <?php if ($physician['is_active']): ?>
                <button onclick="showEditRow(<?= $physician['id'] ?>)" class="btn btn-secondary btn-small">
                  Edit
                </button>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Deactivate this physician?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="physician_id" value="<?= $physician['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-small">Deactivate</button>
                </form>
              <?php else: ?>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="action" value="reactivate">
                  <input type="hidden" name="physician_id" value="<?= $physician['id'] ?>">
                  <button type="submit" class="btn btn-primary btn-small">Reactivate</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>

          <!-- Inline Edit Row -->
          <tr class="edit-row" id="edit-row-<?= $physician['id'] ?>" style="display: none;">
            <td colspan="9">
              <form method="POST" style="display: grid; grid-template-columns: 1.5fr 1fr 1fr 1.5fr 1fr 1fr 1.5fr auto; gap: 0.75rem; padding: 1rem; background: #f8fafc;">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="physician_id" value="<?= $physician['id'] ?>">

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Physician Name *</label>
                  <input type="text" name="physician_name" class="physician-input" value="<?= htmlspecialchars($physician['physician_name']) ?>" required>
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">NPI</label>
                  <input type="text" name="npi" class="physician-input" value="<?= htmlspecialchars($physician['npi'] ?? '') ?>" maxlength="20">
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">License #</label>
                  <input type="text" name="license_number" class="physician-input" value="<?= htmlspecialchars($physician['license_number'] ?? '') ?>" maxlength="50">
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Address</label>
                  <input type="text" name="address" id="edit-address-<?= $physician['id'] ?>" class="physician-input address-autocomplete" value="<?= htmlspecialchars($physician['address'] ?? '') ?>">
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">City</label>
                  <input type="text" name="city" id="edit-city-<?= $physician['id'] ?>" class="physician-input" value="<?= htmlspecialchars($physician['city'] ?? '') ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                  <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">State</label>
                    <input type="text" name="state" id="edit-state-<?= $physician['id'] ?>" class="physician-input" value="<?= htmlspecialchars($physician['state'] ?? '') ?>" maxlength="2" pattern="[A-Z]{2}" style="text-transform: uppercase;">
                  </div>
                  <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">ZIP</label>
                    <input type="text" name="zip" id="edit-zip-<?= $physician['id'] ?>" class="physician-input" value="<?= htmlspecialchars($physician['zip'] ?? '') ?>" pattern="\d{5}" maxlength="5">
                  </div>
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Phone</label>
                  <input type="tel" name="phone" class="physician-input" value="<?= htmlspecialchars($physician['phone'] ?? '') ?>">
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Signature Text</label>
                  <input type="text" name="signature_text" class="physician-input" value="<?= htmlspecialchars($physician['signature_text'] ?? '') ?>" placeholder="Dr. John Smith, MD">
                </div>

                <div style="display: flex; gap: 0.5rem; align-items: end;">
                  <button type="submit" class="btn btn-primary">Save</button>
                  <button type="button" onclick="cancelEdit(<?= $physician['id'] ?>)" class="btn btn-secondary">Cancel</button>
                </div>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>

        <!-- Add New Row -->
        <tr class="add-row" id="add-row" style="display: none;">
          <td colspan="9">
            <form method="POST" id="add-form" style="display: grid; grid-template-columns: 1.5fr 1fr 1fr 1.5fr 1fr 1fr 1.5fr auto; gap: 0.75rem; padding: 1rem; background: #f0fdf4;">
              <input type="hidden" name="action" value="add">

              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Physician Name *</label>
                <input type="text" name="physician_name" class="physician-input" placeholder="Dr. Jane Smith" required>
              </div>

              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">NPI</label>
                <input type="text" name="npi" class="physician-input" placeholder="1234567890" maxlength="20">
              </div>

              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">License #</label>
                <input type="text" name="license_number" class="physician-input" placeholder="LIC123456" maxlength="50">
              </div>

              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Address</label>
                <input type="text" name="address" id="new-address" class="physician-input address-autocomplete" placeholder="123 Main St">
              </div>

              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">City</label>
                <input type="text" name="city" id="new-city" class="physician-input">
              </div>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">State</label>
                  <input type="text" name="state" id="new-state" class="physician-input" maxlength="2" pattern="[A-Z]{2}" placeholder="TX" style="text-transform: uppercase;">
                </div>
                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">ZIP</label>
                  <input type="text" name="zip" id="new-zip" class="physician-input" pattern="\d{5}" maxlength="5">
                </div>
              </div>

              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Phone</label>
                <input type="tel" name="phone" class="physician-input" placeholder="(123) 456-7890">
              </div>

              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Signature Text</label>
                <input type="text" name="signature_text" class="physician-input" placeholder="Dr. Jane Smith, MD">
              </div>

              <div style="display: flex; gap: 0.5rem; align-items: end;">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" onclick="cancelAdd()" class="btn btn-secondary">Cancel</button>
              </div>
            </form>
          </td>
        </tr>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script>
function showAddRow() {
  document.getElementById('add-row').style.display = 'table-row';
}

function cancelAdd() {
  document.getElementById('add-row').style.display = 'none';
  document.getElementById('add-form').reset();
}

function showEditRow(physicianId) {
  // Hide all edit rows first
  document.querySelectorAll('.edit-row').forEach(row => {
    row.style.display = 'none';
  });

  // Show the requested edit row
  document.getElementById('edit-row-' + physicianId).style.display = 'table-row';
}

function cancelEdit(physicianId) {
  document.getElementById('edit-row-' + physicianId).style.display = 'none';
}

// Google Places Autocomplete
<?php if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY): ?>
function initAutocomplete(addressId, cityId, stateId, zipId) {
  const addressInput = document.getElementById(addressId);
  if (!addressInput) return;

  const autocomplete = new google.maps.places.Autocomplete(addressInput, {
    types: ['address'],
    componentRestrictions: { country: 'us' }
  });

  autocomplete.addListener('place_changed', function() {
    const place = autocomplete.getPlace();
    if (!place.address_components) return;

    let street = '';
    let city = '';
    let state = '';
    let zip = '';

    for (const component of place.address_components) {
      const type = component.types[0];

      if (type === 'street_number') {
        street = component.long_name + ' ';
      } else if (type === 'route') {
        street += component.long_name;
      } else if (type === 'locality') {
        city = component.long_name;
      } else if (type === 'administrative_area_level_1') {
        state = component.short_name;
      } else if (type === 'postal_code') {
        zip = component.long_name;
      }
    }

    document.getElementById(addressId).value = street.trim();
    document.getElementById(cityId).value = city;
    document.getElementById(stateId).value = state;
    document.getElementById(zipId).value = zip;
  });
}

// Initialize autocomplete for new physician form
window.addEventListener('load', function() {
  initAutocomplete('new-address', 'new-city', 'new-state', 'new-zip');

  // Initialize for edit forms
  <?php foreach ($physicians as $physician): ?>
  initAutocomplete(
    'edit-address-<?= $physician['id'] ?>',
    'edit-city-<?= $physician['id'] ?>',
    'edit-state-<?= $physician['id'] ?>',
    'edit-zip-<?= $physician['id'] ?>'
  );
  <?php endforeach; ?>
});
<?php endif; ?>
</script>
