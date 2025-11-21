<?php
/**
 * Practice Locations Management
 * Allows practice admins to manage multiple facility addresses
 */

// This file is included by portal/index.php
global $pdo, $user;

if (!isset($user) || !is_array($user)) {
  echo '<div style="padding: 2rem;">Unauthorized access</div>';
  return;
}

$userId = $user['id'];
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
      header('Location: ?page=practice-locations');
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
      header('Location: ?page=practice-locations');
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
      header('Location: ?page=practice-locations');
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
      header('Location: ?page=practice-locations');
      exit;
    }
  }
}

// Fetch locations
$locations = $pdo->prepare("
  SELECT * FROM practice_locations
  WHERE user_id = ? AND is_active = TRUE
  ORDER BY is_primary DESC, location_name ASC
");
$locations->execute([$userId]);
$locations = $locations->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.locations-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

.location-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 1.5rem;
  margin-bottom: 1rem;
  position: relative;
}

.location-card.primary {
  border-color: #10b981;
  border-width: 2px;
}

.primary-badge {
  position: absolute;
  top: 1rem;
  right: 1rem;
  background: #10b981;
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}

.location-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
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
}

.btn-primary {
  background: #10b981;
  color: white;
  border-color: #10b981;
}

.btn-primary:hover {
  background: #059669;
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

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  font-weight: 500;
  margin-bottom: 0.5rem;
  color: #1e293b;
}

.form-control {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 0.875rem;
}

.form-control:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  align-items: center;
  justify-content: center;
}

.modal.active {
  display: flex;
}

.modal-content {
  background: white;
  border-radius: 12px;
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  padding: 1.5rem;
  border-top: 1px solid #e2e8f0;
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
}
</style>

<div class="locations-container">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
    <div>
      <h1 style="font-size: 1.875rem; font-weight: 700; margin-bottom: 0.5rem;">Practice Locations</h1>
      <p style="color: #64748b; font-size: 0.875rem;">Manage delivery addresses for wholesale orders</p>
    </div>
    <?php if ($isPracticeAdmin): ?>
    <button onclick="openAddModal()" class="btn btn-primary">
      + Add Location
    </button>
    <?php endif; ?>
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

  <?php if (empty($locations)): ?>
    <div style="text-align: center; padding: 3rem; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
      <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 1rem; opacity: 0.3;">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
      </svg>
      <h3 style="font-size: 1.125rem; margin-bottom: 0.5rem;">No locations yet</h3>
      <p style="color: #64748b; margin-bottom: 1.5rem;">Add your first practice location to start ordering</p>
      <?php if ($isPracticeAdmin): ?>
      <button onclick="openAddModal()" class="btn btn-primary">Add Location</button>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php foreach ($locations as $location): ?>
      <div class="location-card <?= $location['is_primary'] ? 'primary' : '' ?>">
        <?php if ($location['is_primary']): ?>
          <span class="primary-badge">PRIMARY</span>
        <?php endif; ?>

        <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.75rem;">
          <?= htmlspecialchars($location['location_name']) ?>
        </h3>

        <div style="color: #64748b; line-height: 1.6;">
          <div><?= htmlspecialchars($location['address']) ?></div>
          <div><?= htmlspecialchars($location['city']) ?>, <?= htmlspecialchars($location['state']) ?> <?= htmlspecialchars($location['zip']) ?></div>
          <?php if ($location['phone']): ?>
            <div>Phone: <?= htmlspecialchars($location['phone']) ?></div>
          <?php endif; ?>
        </div>

        <?php if ($isPracticeAdmin): ?>
          <div class="location-actions">
            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($location)) ?>)" class="btn btn-secondary">
              Edit
            </button>
            <?php if (!$location['is_primary']): ?>
              <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="set_primary">
                <input type="hidden" name="location_id" value="<?= $location['id'] ?>">
                <button type="submit" class="btn btn-secondary">Set as Primary</button>
              </form>
              <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this location?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="location_id" value="<?= $location['id'] ?>">
                <button type="submit" class="btn btn-danger">Delete</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Add/Edit Location Modal -->
<div id="location-modal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="modal-title" style="font-size: 1.25rem; font-weight: 600;">Add Location</h2>
    </div>
    <form method="POST" id="location-form">
      <div class="modal-body">
        <input type="hidden" name="action" id="form-action" value="add">
        <input type="hidden" name="location_id" id="location-id" value="">

        <div class="form-group">
          <label for="location_name">Location Name *</label>
          <input type="text" name="location_name" id="location_name" class="form-control" placeholder="e.g., Main Office, Satellite Clinic" required>
        </div>

        <div class="form-group">
          <label for="address">Street Address *</label>
          <input type="text" name="address" id="address" class="form-control" required>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label for="city">City *</label>
            <input type="text" name="city" id="city" class="form-control" required>
          </div>

          <div class="form-group">
            <label for="state">State *</label>
            <input type="text" name="state" id="state" class="form-control" maxlength="2" pattern="[A-Z]{2}" placeholder="TX" required>
          </div>

          <div class="form-group">
            <label for="zip">ZIP *</label>
            <input type="text" name="zip" id="zip" class="form-control" pattern="\d{5}" maxlength="5" required>
          </div>
        </div>

        <div class="form-group">
          <label for="phone">Phone</label>
          <input type="tel" name="phone" id="phone" class="form-control" placeholder="(123) 456-7890">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Location</button>
      </div>
    </form>
  </div>
</div>

<!-- Google Maps Places API for Address Autocomplete -->
<?php if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&libraries=places"></script>
<script>
let autocomplete;

function initAutocomplete() {
  const addressInput = document.getElementById('address');

  autocomplete = new google.maps.places.Autocomplete(addressInput, {
    types: ['address'],
    componentRestrictions: { country: 'us' }
  });

  autocomplete.addListener('place_changed', function() {
    const place = autocomplete.getPlace();

    if (!place.address_components) {
      return;
    }

    // Parse address components
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

    // Auto-fill the form fields
    document.getElementById('address').value = street;
    document.getElementById('city').value = city;
    document.getElementById('state').value = state;
    document.getElementById('zip').value = zip;
  });
}
</script>
<?php endif; ?>

<script>
function openAddModal() {
  document.getElementById('modal-title').textContent = 'Add Location';
  document.getElementById('form-action').value = 'add';
  document.getElementById('location-form').reset();
  document.getElementById('location-id').value = '';
  document.getElementById('location-modal').classList.add('active');

  // Initialize Google Maps autocomplete when modal opens
  <?php if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY): ?>
  setTimeout(initAutocomplete, 100);
  <?php endif; ?>
}

function openEditModal(location) {
  document.getElementById('modal-title').textContent = 'Edit Location';
  document.getElementById('form-action').value = 'edit';
  document.getElementById('location-id').value = location.id;
  document.getElementById('location_name').value = location.location_name;
  document.getElementById('address').value = location.address;
  document.getElementById('city').value = location.city;
  document.getElementById('state').value = location.state;
  document.getElementById('zip').value = location.zip;
  document.getElementById('phone').value = location.phone || '';
  document.getElementById('location-modal').classList.add('active');

  // Initialize Google Maps autocomplete when modal opens
  <?php if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY): ?>
  setTimeout(initAutocomplete, 100);
  <?php endif; ?>
}

function closeModal() {
  document.getElementById('location-modal').classList.remove('active');
}

// Close modal when clicking outside
document.getElementById('location-modal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeModal();
  }
});
</script>
