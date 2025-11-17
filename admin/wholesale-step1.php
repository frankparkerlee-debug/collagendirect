<?php
/**
 * Step 1: Add Patients and Select Shipping
 */
?>

<form method="POST" id="step1-form">
  <input type="hidden" name="action" value="save_patients">

  <!-- Order Type Selection -->
  <div style="background: var(--brand-light); border: 2px solid var(--brand); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 2rem;">
    <label style="display: block; font-weight: 600; margin-bottom: 1rem; color: var(--ink);">
      Order Type *
    </label>
    <select name="order_type" id="order-type" onchange="toggleOrderType()"
            style="width: 100%; max-width: 400px; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);"
            required>
      <option value="">Select order type...</option>
      <option value="patient_orders" <?= ($_SESSION['admin_order_type'] ?? '') === 'patient_orders' ? 'selected' : '' ?>>Patient Orders</option>
      <option value="office_stock" <?= ($_SESSION['admin_order_type'] ?? '') === 'office_stock' ? 'selected' : '' ?>>Office Stock (No Patients)</option>
    </select>
  </div>

  <!-- Patient Orders Section -->
  <div id="patient-orders-section" style="display: <?= ($_SESSION['admin_order_type'] ?? '') === 'office_stock' ? 'none' : 'block' ?>;">

  <!-- Shipping Selection -->
  <?php if (!empty($practiceLocations)): ?>
    <div style="background: var(--brand-light); border: 2px solid var(--brand); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 2rem;">
      <label style="display: block; font-weight: 600; margin-bottom: 1rem; color: var(--ink);">
        Shipping Destination *
      </label>
      <select name="shipping[type]" id="shipping-type" onchange="toggleShippingOptions()"
              style="width: 100%; max-width: 400px; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 1rem;"
              required>
        <option value="">Select shipping destination...</option>
        <option value="practice" <?= ($shipping['type'] ?? '') === 'practice' ? 'selected' : '' ?>>Ship to Practice/Office</option>
        <option value="patients" <?= ($shipping['type'] ?? '') === 'patients' ? 'selected' : '' ?>>Ship to Individual Patients</option>
      </select>

      <!-- Practice Location Selection -->
      <div id="practice-location-select" style="display: <?= ($shipping['type'] ?? '') === 'practice' ? 'block' : 'none' ?>;">
        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: var(--ink); font-size: 0.875rem;">
          Practice Location
        </label>
        <select name="shipping[location_id]"
                style="width: 100%; max-width: 600px; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
          <option value="">Select location...</option>
          <?php foreach ($practiceLocations as $loc): ?>
            <option value="<?= htmlspecialchars($loc['id']) ?>" <?= ($shipping['location_id'] ?? '') == $loc['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['location_name']) ?> -
              <?= htmlspecialchars($loc['address']) ?>,
              <?= htmlspecialchars($loc['city']) ?>,
              <?= htmlspecialchars($loc['state']) ?>
              <?= htmlspecialchars($loc['zip']) ?>
              <?= $loc['is_primary'] ? '(Primary)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="patient-shipping-note" style="display: <?= ($shipping['type'] ?? '') === 'patients' ? 'block' : 'none' ?>; padding: 0.75rem; background: white; border-radius: var(--radius); font-size: 0.875rem; color: var(--muted);">
        Products will be shipped directly to each patient's address
      </div>
    </div>
  <?php endif; ?>

  <!-- Patients List -->
  <div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h4 style="font-size: 1rem; font-weight: 600; color: var(--ink); margin: 0;">
        Patients
      </h4>
      <button type="button" onclick="addPatient()"
              style="padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; border: 1px solid var(--brand); border-radius: var(--radius); background: var(--brand-light); color: var(--brand); cursor: pointer;">
        + Add Patient
      </button>
    </div>

    <div id="patients-container">
      <?php if (!empty($patients)): ?>
        <?php foreach ($patients as $index => $patient): ?>
          <div class="patient-card" id="patient-<?= $index ?>" data-index="<?= $index ?>"
               style="border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 1rem; background: white;">

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <h5 style="font-size: 0.9375rem; font-weight: 600; color: var(--ink); margin: 0;">
                Patient <?= $index + 1 ?>
              </h5>
              <button type="button" onclick="removePatient(<?= $index ?>)"
                      style="padding: 0.25rem 0.75rem; font-size: 0.75rem; border: 1px solid var(--error); border-radius: var(--radius); background: white; color: var(--error); cursor: pointer;">
                Remove
              </button>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
              <div>
                <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">First Name *</label>
                <input type="text" name="patients[<?= $index ?>][first_name]" value="<?= htmlspecialchars($patient['first_name'] ?? '') ?>" required
                       style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
              <div>
                <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Last Name *</label>
                <input type="text" name="patients[<?= $index ?>][last_name]" value="<?= htmlspecialchars($patient['last_name'] ?? '') ?>" required
                       style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
              <div>
                <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Date of Birth *</label>
                <input type="date" name="patients[<?= $index ?>][dob]" value="<?= htmlspecialchars($patient['dob'] ?? '') ?>" required
                       style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
            </div>

            <!-- Patient Address (always shown for record keeping) -->
            <div class="patient-address-fields">
              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Street Address</label>
                <input type="text" name="patients[<?= $index ?>][address]" value="<?= htmlspecialchars($patient['address'] ?? '') ?>"
                       style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
              <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem;">
                <div>
                  <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">City</label>
                  <input type="text" name="patients[<?= $index ?>][city]" value="<?= htmlspecialchars($patient['city'] ?? '') ?>"
                         style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
                </div>
                <div>
                  <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">State</label>
                  <input type="text" name="patients[<?= $index ?>][state]" value="<?= htmlspecialchars($patient['state'] ?? '') ?>" maxlength="2"
                         style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
                </div>
                <div>
                  <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Zip</label>
                  <input type="text" name="patients[<?= $index ?>][zip]" value="<?= htmlspecialchars($patient['zip'] ?? '') ?>" maxlength="10"
                         style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div id="no-patients-message" style="display: <?= empty($patients) ? 'block' : 'none' ?>; text-align: center; padding: 3rem; color: var(--muted); border: 2px dashed var(--border); border-radius: var(--radius);">
      <p style="font-size: 0.875rem;">No patients added yet. Click "+ Add Patient" to begin.</p>
    </div>
  </div>

  </div>
  <!-- End Patient Orders Section -->

  <!-- Office Stock Section -->
  <div id="office-stock-section" style="display: <?= ($_SESSION['admin_order_type'] ?? '') === 'office_stock' ? 'block' : 'none' ?>;">
    <!-- Shipping Selection for Office Stock -->
    <?php if (!empty($practiceLocations)): ?>
      <div style="background: var(--brand-light); border: 2px solid var(--brand); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 2rem;">
        <label style="display: block; font-weight: 600; margin-bottom: 1rem; color: var(--ink);">
          Shipping Destination *
        </label>
        <select name="shipping[type]" id="office-shipping-type"
                style="width: 100%; max-width: 400px; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 1rem;"
                required>
          <option value="practice" selected>Ship to Practice/Office</option>
        </select>

        <div>
          <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: var(--ink); font-size: 0.875rem;">
            Practice Location
          </label>
          <select name="shipping[location_id]"
                  style="width: 100%; max-width: 600px; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
            <option value="">Select location...</option>
            <?php foreach ($practiceLocations as $loc): ?>
              <option value="<?= htmlspecialchars($loc['id']) ?>" <?= ($shipping['location_id'] ?? '') == $loc['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($loc['location_name']) ?> -
                <?= htmlspecialchars($loc['address']) ?>,
                <?= htmlspecialchars($loc['city']) ?>,
                <?= htmlspecialchars($loc['state']) ?>
                <?= htmlspecialchars($loc['zip']) ?>
                <?= $loc['is_primary'] ? '(Primary)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    <?php endif; ?>

    <div style="text-align: center; padding: 3rem; background: var(--bg-gray); border-radius: var(--radius);">
      <svg style="width: 64px; height: 64px; margin: 0 auto 1rem; opacity: 0.4; color: var(--brand);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
      </svg>
      <p style="font-size: 1rem; font-weight: 600; color: var(--ink); margin-bottom: 0.5rem;">
        Office Stock Order
      </p>
      <p style="font-size: 0.875rem; color: var(--muted);">
        Products will be assigned in the next step.<br>
        No patient information needed for office stock orders.
      </p>
    </div>
  </div>
  <!-- End Office Stock Section -->

  <!-- Actions -->
  <div style="display: flex; justify-content: space-between; padding-top: 1.5rem; border-top: 1px solid var(--border);">
    <a href="?practice_id=<?= urlencode($selectedPracticeId) ?>"
       style="padding: 0.75rem 1.5rem; font-size: 0.875rem; font-weight: 500; border: 1px solid var(--border); border-radius: var(--radius); background: white; color: var(--ink); text-decoration: none; cursor: pointer;">
      Cancel
    </a>
    <button type="submit"
            style="padding: 0.75rem 1.5rem; font-size: 0.875rem; font-weight: 500; border: none; border-radius: var(--radius); background: var(--brand); color: white; cursor: pointer;">
      Next: Assign Products →
    </button>
  </div>
</form>

<script>
let patientCounter = <?= count($patients) ?>;

function toggleOrderType() {
  const orderType = document.getElementById('order-type').value;
  const patientSection = document.getElementById('patient-orders-section');
  const officeSection = document.getElementById('office-stock-section');

  if (orderType === 'patient_orders') {
    patientSection.style.display = 'block';
    officeSection.style.display = 'none';
  } else if (orderType === 'office_stock') {
    patientSection.style.display = 'none';
    officeSection.style.display = 'block';
  } else {
    patientSection.style.display = 'none';
    officeSection.style.display = 'none';
  }
}

function toggleShippingOptions() {
  const shippingType = document.getElementById('shipping-type').value;
  const practiceLocationSelect = document.getElementById('practice-location-select');
  const patientShippingNote = document.getElementById('patient-shipping-note');

  if (shippingType === 'practice') {
    practiceLocationSelect.style.display = 'block';
    patientShippingNote.style.display = 'none';
  } else if (shippingType === 'patients') {
    practiceLocationSelect.style.display = 'none';
    patientShippingNote.style.display = 'block';
  } else {
    practiceLocationSelect.style.display = 'none';
    patientShippingNote.style.display = 'none';
  }
}

function addPatient() {
  const container = document.getElementById('patients-container');
  const index = patientCounter++;

  const patientCard = document.createElement('div');
  patientCard.className = 'patient-card';
  patientCard.id = `patient-${index}`;
  patientCard.dataset.index = index;
  patientCard.style.cssText = 'border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 1rem; background: white;';

  patientCard.innerHTML = `
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <h5 style="font-size: 0.9375rem; font-weight: 600; color: var(--ink); margin: 0;">Patient ${index + 1}</h5>
      <button type="button" onclick="removePatient(${index})"
              style="padding: 0.25rem 0.75rem; font-size: 0.75rem; border: 1px solid var(--error); border-radius: var(--radius); background: white; color: var(--error); cursor: pointer;">
        Remove
      </button>
    </div>

    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
      <div>
        <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">First Name *</label>
        <input type="text" name="patients[${index}][first_name]" required
               style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
      </div>
      <div>
        <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Last Name *</label>
        <input type="text" name="patients[${index}][last_name]" required
               style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
      </div>
      <div>
        <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Date of Birth *</label>
        <input type="date" name="patients[${index}][dob]" required
               style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
      </div>
    </div>

    <div class="patient-address-fields">
      <div style="margin-bottom: 1rem;">
        <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Street Address</label>
        <input type="text" name="patients[${index}][address]"
               style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
      </div>
      <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem;">
        <div>
          <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">City</label>
          <input type="text" name="patients[${index}][city]"
                 style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
        </div>
        <div>
          <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">State</label>
          <input type="text" name="patients[${index}][state]" maxlength="2"
                 style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
        </div>
        <div>
          <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Zip</label>
          <input type="text" name="patients[${index}][zip]" maxlength="10"
                 style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
        </div>
      </div>
    </div>
  `;

  container.appendChild(patientCard);
  document.getElementById('no-patients-message').style.display = 'none';
}

function removePatient(index) {
  const card = document.getElementById(`patient-${index}`);
  if (card) {
    card.remove();
  }

  // Check if any patients remain
  const remainingPatients = document.querySelectorAll('.patient-card');
  if (remainingPatients.length === 0) {
    document.getElementById('no-patients-message').style.display = 'block';
  }
}

// Initialize if no patients yet
if (patientCounter === 0) {
  addPatient();
}

// Google Places Autocomplete for address fields
function initAutocompleteForField(input) {
  if (!input || typeof google === 'undefined') return;

  const autocomplete = new google.maps.places.Autocomplete(input, {
    types: ['address'],
    componentRestrictions: { country: 'us' }
  });

  autocomplete.addListener('place_changed', function() {
    const place = autocomplete.getPlace();
    if (!place.address_components) return;

    // Find the parent patient card
    let patientCard = input.closest('.patient-card');
    if (!patientCard) return;

    // Extract address components
    let streetNumber = '';
    let route = '';
    let city = '';
    let state = '';
    let zip = '';

    for (const component of place.address_components) {
      const types = component.types;
      if (types.includes('street_number')) {
        streetNumber = component.long_name;
      } else if (types.includes('route')) {
        route = component.long_name;
      } else if (types.includes('locality')) {
        city = component.long_name;
      } else if (types.includes('administrative_area_level_1')) {
        state = component.short_name;
      } else if (types.includes('postal_code')) {
        zip = component.long_name;
      }
    }

    // Populate the fields
    input.value = streetNumber + (streetNumber ? ' ' : '') + route;

    const cityInput = patientCard.querySelector('input[name*="[city]"]');
    const stateInput = patientCard.querySelector('input[name*="[state]"]');
    const zipInput = patientCard.querySelector('input[name*="[zip]"]');

    if (cityInput) cityInput.value = city;
    if (stateInput) stateInput.value = state;
    if (zipInput) zipInput.value = zip;
  });
}

// Initialize autocomplete for existing address fields
document.addEventListener('DOMContentLoaded', function() {
  const addressInputs = document.querySelectorAll('input[name*="[address]"]');
  addressInputs.forEach(input => {
    initAutocompleteForField(input);
  });
});

// Modified addPatient to initialize autocomplete for new fields
const originalAddPatient = addPatient;
addPatient = function() {
  originalAddPatient();
  // Initialize autocomplete for the newly added patient's address field
  setTimeout(() => {
    const lastPatientCard = document.querySelector('.patient-card:last-of-type');
    if (lastPatientCard) {
      const addressInput = lastPatientCard.querySelector('input[name*="[address]"]');
      if (addressInput && typeof google !== 'undefined') {
        initAutocompleteForField(addressInput);
      }
    }
  }, 100);
};
</script>

<?php if (defined('GOOGLE_PLACES_API_KEY') && GOOGLE_PLACES_API_KEY): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_PLACES_API_KEY ?>&libraries=places&callback=initAutocompleteForExistingFields" async defer></script>
<script>
function initAutocompleteForExistingFields() {
  const addressInputs = document.querySelectorAll('input[name*="[address]"]');
  addressInputs.forEach(input => {
    initAutocompleteForField(input);
  });
}
</script>
<?php endif; ?>
