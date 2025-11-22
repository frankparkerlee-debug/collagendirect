<?php
/**
 * Practice Locations Management - Inline Editing
 * Allows practice admins to manage multiple facility addresses
 */

require_once __DIR__ . '/../admin/db.php';
require_once __DIR__ . '/../admin/auth.php';
require_once __DIR__ . '/../admin/config.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
  header('Location: /login');
  exit;
}

// Load user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  header('Location: /login');
  exit;
}

$isPracticeAdmin = in_array($user['role'] ?? '', ['practice_admin', 'superadmin']);

// Handle POST actions (add, edit, delete, set primary)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'add' && $isPracticeAdmin) {
    $locationName = trim($_POST['location_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($locationName && $address && $city && $state && $zip) {
      try {
        $pdo->prepare("
          INSERT INTO practice_locations (user_id, location_name, address, city, state, zip, phone, is_active)
          VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
        ")->execute([$userId, $locationName, $address, $city, $state, $zip, $phone]);

        $_SESSION['success_msg'] = 'Location added successfully';
      } catch (PDOException $e) {
        $_SESSION['error_msg'] = 'Error adding location: ' . $e->getMessage();
      }
      header('Location: /portal/?page=profile#locations');
      exit;
    }
  } elseif ($action === 'edit' && $isPracticeAdmin) {
    $locationId = (int)($_POST['location_id'] ?? 0);
    $locationName = trim($_POST['location_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($locationId && $locationName && $address && $city && $state && $zip) {
      try {
        $pdo->prepare("
          UPDATE practice_locations
          SET location_name = ?, address = ?, city = ?, state = ?, zip = ?, phone = ?, updated_at = NOW()
          WHERE id = ? AND user_id = ?
        ")->execute([$locationName, $address, $city, $state, $zip, $phone, $locationId, $userId]);

        $_SESSION['success_msg'] = 'Location updated successfully';
      } catch (PDOException $e) {
        $_SESSION['error_msg'] = 'Error updating location: ' . $e->getMessage();
      }
      header('Location: /portal/?page=profile#locations');
      exit;
    }
  } elseif ($action === 'delete' && $isPracticeAdmin) {
    $locationId = (int)($_POST['location_id'] ?? 0);

    if ($locationId) {
      // Check if it's the primary location
      $isPrimary = $pdo->prepare("SELECT is_primary FROM practice_locations WHERE id = ? AND user_id = ?");
      $isPrimary->execute([$locationId, $userId]);
      $loc = $isPrimary->fetch(PDO::FETCH_ASSOC);

      if ($loc && $loc['is_primary']) {
        $_SESSION['error_msg'] = 'Cannot delete primary location. Set another location as primary first.';
      } else {
        $pdo->prepare("DELETE FROM practice_locations WHERE id = ? AND user_id = ?")->execute([$locationId, $userId]);
        $_SESSION['success_msg'] = 'Location deleted successfully';
      }
      header('Location: /portal/?page=profile#locations');
      exit;
    }
  } elseif ($action === 'set_primary' && $isPracticeAdmin) {
    $locationId = (int)($_POST['location_id'] ?? 0);

    if ($locationId) {
      $pdo->beginTransaction();
      try {
        // Remove primary from all locations
        $pdo->prepare("UPDATE practice_locations SET is_primary = FALSE WHERE user_id = ?")->execute([$userId]);
        // Set new primary
        $pdo->prepare("UPDATE practice_locations SET is_primary = TRUE WHERE id = ? AND user_id = ?")->execute([$locationId, $userId]);
        $pdo->commit();
        $_SESSION['success_msg'] = 'Primary location updated successfully';
      } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = 'Error setting primary location';
      }
      header('Location: /portal/?page=profile#locations');
      exit;
    }
  }
}

// Check if table exists first
$tableExists = false;
try {
  $tableCheck = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'practice_locations'
    )
  ");
  $tableExists = $tableCheck->fetchColumn();
} catch (Exception $e) {
  $tableExists = false;
}

// Fetch locations if table exists
$locations = [];
if ($tableExists) {
  try {
    $locationsStmt = $pdo->prepare("
      SELECT * FROM practice_locations
      WHERE user_id = ? AND is_active = TRUE
      ORDER BY is_primary DESC, location_name ASC
    ");
    $locationsStmt->execute([$userId]);
    $locations = $locationsStmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    $_SESSION['error_msg'] = 'Error loading locations: ' . $e->getMessage();
  }
}
?>

<!-- Google Maps Places API -->
<?php if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&libraries=places"></script>
<?php endif; ?>

<style>
.locations-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
}

.locations-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.locations-table thead {
  background: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
}

.locations-table th {
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
  color: #64748b;
}

.locations-table td {
  padding: 1rem;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: middle;
}

.locations-table tbody tr:hover {
  background: #f8fafc;
}

.location-input {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 0.875rem;
}

.location-input:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.primary-badge {
  background: #10b981;
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  display: inline-block;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
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

.add-row {
  background: #f0fdf4 !important;
}

.add-row td {
  border-bottom: 2px solid #10b981;
}

.empty-state {
  text-align: center;
  padding: 3rem;
  background: white;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}
</style>

<div class="locations-container">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
    <div>
      <h1 style="font-size: 1.875rem; font-weight: 700; margin-bottom: 0.5rem;">Practice Locations</h1>
      <p style="color: #64748b; font-size: 0.875rem;">Manage delivery addresses for wholesale orders. Click "+ Add Location" to add a new row.</p>
    </div>
  </div>

  <?php if (isset($_SESSION['success_msg'])): ?>
    <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
      <?= htmlspecialchars($_SESSION['success_msg']) ?>
    </div>
    <?php unset($_SESSION['success_msg']); endif; ?>

  <?php if (isset($_SESSION['error_msg'])): ?>
    <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
      <?= htmlspecialchars($_SESSION['error_msg']) ?>
    </div>
    <?php unset($_SESSION['error_msg']); endif; ?>

  <?php if (!$tableExists): ?>
    <div style="background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
      <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">⚠️ Database Setup Required</h3>
      <p style="margin-bottom: 1rem;">The practice_locations table needs to be created before you can manage locations.</p>
      <a href="/admin/fix-schema-direct.php" style="background: #f59e0b; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block; font-weight: 600;">
        Run Database Setup
      </a>
    </div>
  <?php elseif (empty($locations) && !$isPracticeAdmin): ?>
    <div class="empty-state">
      <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 1rem; opacity: 0.3;">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
      </svg>
      <h3 style="font-size: 1.125rem; margin-bottom: 0.5rem;">No locations yet</h3>
      <p style="color: #64748b;">Contact your practice admin to add locations</p>
    </div>
  <?php else: ?>
    <table class="locations-table">
      <thead>
        <tr>
          <th style="width: 20%;">Location Name</th>
          <th style="width: 25%;">Street Address</th>
          <th style="width: 15%;">City</th>
          <th style="width: 8%;">State</th>
          <th style="width: 10%;">ZIP</th>
          <th style="width: 12%;">Phone</th>
          <th style="width: 10%; text-align: center;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($locations as $location): ?>
          <tr data-location-id="<?= $location['id'] ?>">
            <td>
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <?= htmlspecialchars($location['location_name']) ?>
                <?php if ($location['is_primary']): ?>
                  <span class="primary-badge">PRIMARY</span>
                <?php endif; ?>
              </div>
            </td>
            <td><?= htmlspecialchars($location['address']) ?></td>
            <td><?= htmlspecialchars($location['city']) ?></td>
            <td><?= htmlspecialchars($location['state']) ?></td>
            <td><?= htmlspecialchars($location['zip']) ?></td>
            <td><?= htmlspecialchars($location['phone'] ?: '-') ?></td>
            <td style="text-align: center;">
              <?php if ($isPracticeAdmin): ?>
                <button onclick="editLocation(<?= $location['id'] ?>)" class="btn btn-secondary btn-small">Edit</button>
                <?php if (!$location['is_primary']): ?>
                  <form method="POST" style="display: inline;" onsubmit="return confirm('Set as primary location?');">
                    <input type="hidden" name="action" value="set_primary">
                    <input type="hidden" name="location_id" value="<?= $location['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-small">Set Primary</button>
                  </form>
                  <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this location?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="location_id" value="<?= $location['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                  </form>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>

        <!-- Add New Row (always visible when admin) -->
        <?php if ($isPracticeAdmin): ?>
          <tr class="add-row" id="add-row" style="display: none;">
            <td colspan="7">
              <form method="POST" id="add-form" style="display: grid; grid-template-columns: 1fr 1.5fr 1fr 0.5fr 0.75fr 1fr auto; gap: 0.75rem; align-items: end;">
                <input type="hidden" name="action" value="add">

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Location Name *</label>
                  <input type="text" name="location_name" class="location-input" placeholder="Main Office" required>
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Street Address *</label>
                  <input type="text" name="address" id="new-address" class="location-input address-autocomplete" placeholder="123 Main St" required>
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">City *</label>
                  <input type="text" name="city" id="new-city" class="location-input" required>
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">State *</label>
                  <input type="text" name="state" id="new-state" class="location-input" maxlength="2" pattern="[A-Z]{2}" placeholder="TX" required style="text-transform: uppercase;">
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">ZIP *</label>
                  <input type="text" name="zip" id="new-zip" class="location-input" pattern="\d{5}" maxlength="5" required>
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Phone</label>
                  <input type="tel" name="phone" class="location-input" placeholder="(123) 456-7890">
                </div>

                <div style="display: flex; gap: 0.5rem;">
                  <button type="submit" class="btn btn-primary">Save</button>
                  <button type="button" onclick="cancelAdd()" class="btn btn-secondary">Cancel</button>
                </div>
              </form>
            </td>
          </tr>

          <!-- Edit Row (hidden by default) -->
          <tr class="add-row" id="edit-row" style="display: none;">
            <td colspan="7">
              <form method="POST" id="edit-form" style="display: grid; grid-template-columns: 1fr 1.5fr 1fr 0.5fr 0.75fr 1fr auto; gap: 0.75rem; align-items: end;">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="location_id" id="edit-location-id">

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Location Name *</label>
                  <input type="text" name="location_name" id="edit-name" class="location-input" required>
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Street Address *</label>
                  <input type="text" name="address" id="edit-address" class="location-input address-autocomplete" required>
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">City *</label>
                  <input type="text" name="city" id="edit-city" class="location-input" required>
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">State *</label>
                  <input type="text" name="state" id="edit-state" class="location-input" maxlength="2" pattern="[A-Z]{2}" required style="text-transform: uppercase;">
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">ZIP *</label>
                  <input type="text" name="zip" id="edit-zip" class="location-input" pattern="\d{5}" maxlength="5" required>
                </div>

                <div>
                  <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Phone</label>
                  <input type="tel" name="phone" id="edit-phone" class="location-input">
                </div>

                <div style="display: flex; gap: 0.5rem;">
                  <button type="submit" class="btn btn-primary">Update</button>
                  <button type="button" onclick="cancelEdit()" class="btn btn-secondary">Cancel</button>
                </div>
              </form>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if ($isPracticeAdmin): ?>
      <div style="margin-top: 1rem;">
        <button onclick="showAddRow()" class="btn btn-primary" id="add-btn">+ Add Location</button>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script>
let currentEditRow = null;
let autocompleteInstances = {};

function showAddRow() {
  document.getElementById('add-row').style.display = 'table-row';
  document.getElementById('add-btn').style.display = 'none';
  document.getElementById('edit-row').style.display = 'none';

  // Initialize autocomplete for new address
  <?php if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY): ?>
  setTimeout(() => initAutocomplete('new-address', 'new-city', 'new-state', 'new-zip'), 100);
  <?php endif; ?>
}

function cancelAdd() {
  document.getElementById('add-row').style.display = 'none';
  document.getElementById('add-btn').style.display = 'inline-block';
  document.getElementById('add-form').reset();
}

function editLocation(locationId) {
  // Hide add row if visible
  document.getElementById('add-row').style.display = 'none';
  document.getElementById('add-btn').style.display = 'none';

  // Get location data from the row
  const row = document.querySelector(`tr[data-location-id="${locationId}"]`);
  const cells = row.querySelectorAll('td');

  // Extract text content (strip HTML like badges)
  const locationName = cells[0].textContent.trim().replace('PRIMARY', '').trim();
  const address = cells[1].textContent.trim();
  const city = cells[2].textContent.trim();
  const state = cells[3].textContent.trim();
  const zip = cells[4].textContent.trim();
  const phone = cells[5].textContent.trim() === '-' ? '' : cells[5].textContent.trim();

  // Populate edit form
  document.getElementById('edit-location-id').value = locationId;
  document.getElementById('edit-name').value = locationName;
  document.getElementById('edit-address').value = address;
  document.getElementById('edit-city').value = city;
  document.getElementById('edit-state').value = state;
  document.getElementById('edit-zip').value = zip;
  document.getElementById('edit-phone').value = phone;

  // Show edit row
  document.getElementById('edit-row').style.display = 'table-row';
  currentEditRow = locationId;

  // Initialize autocomplete for edit address
  <?php if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY): ?>
  setTimeout(() => initAutocomplete('edit-address', 'edit-city', 'edit-state', 'edit-zip'), 100);
  <?php endif; ?>
}

function cancelEdit() {
  document.getElementById('edit-row').style.display = 'none';
  document.getElementById('add-btn').style.display = 'inline-block';
  document.getElementById('edit-form').reset();
  currentEditRow = null;
}

<?php if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY): ?>
function initAutocomplete(addressId, cityId, stateId, zipId) {
  const addressInput = document.getElementById(addressId);

  if (!addressInput || autocompleteInstances[addressId]) {
    return;
  }

  const autocomplete = new google.maps.places.Autocomplete(addressInput, {
    types: ['address'],
    componentRestrictions: { country: 'us' }
  });

  autocompleteInstances[addressId] = autocomplete;

  autocomplete.addListener('place_changed', function() {
    const place = autocomplete.getPlace();

    if (!place.address_components) {
      return;
    }

    let street = '';
    let city = '';
    let state = '';
    let zip = '';

    place.address_components.forEach(component => {
      const types = component.types;

      if (types.includes('street_number')) {
        street = component.long_name;
      } else if (types.includes('route')) {
        street += (street ? ' ' : '') + component.long_name;
      } else if (types.includes('locality')) {
        city = component.long_name;
      } else if (types.includes('administrative_area_level_1')) {
        state = component.short_name;
      } else if (types.includes('postal_code')) {
        zip = component.long_name;
      }
    });

    document.getElementById(addressId).value = street;
    document.getElementById(cityId).value = city;
    document.getElementById(stateId).value = state;
    document.getElementById(zipId).value = zip;
  });
}
<?php endif; ?>
</script>
