<?php
/**
 * Step 1: Add Patients and Select Shipping
 */
?>

<form method="POST" id="step1-form">
  <input type="hidden" name="action" value="save_patients">

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

            <!-- Patient Address (only if shipping to patients) -->
            <div class="patient-address-fields" style="display: <?= ($shipping['type'] ?? '') === 'patients' ? 'block' : 'none' ?>;">
              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Street Address *</label>
                <input type="text" name="patients[<?= $index ?>][address]" value="<?= htmlspecialchars($patient['address'] ?? '') ?>"
                       style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
              <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem;">
                <div>
                  <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">City *</label>
                  <input type="text" name="patients[<?= $index ?>][city]" value="<?= htmlspecialchars($patient['city'] ?? '') ?>"
                         style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
                </div>
                <div>
                  <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">State *</label>
                  <input type="text" name="patients[<?= $index ?>][state]" value="<?= htmlspecialchars($patient['state'] ?? '') ?>" maxlength="2"
                         style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
                </div>
                <div>
                  <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Zip *</label>
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

function toggleShippingOptions() {
  const shippingType = document.getElementById('shipping-type').value;
  const practiceLocationSelect = document.getElementById('practice-location-select');
  const patientShippingNote = document.getElementById('patient-shipping-note');
  const addressFields = document.querySelectorAll('.patient-address-fields');

  if (shippingType === 'practice') {
    practiceLocationSelect.style.display = 'block';
    patientShippingNote.style.display = 'none';
    addressFields.forEach(field => field.style.display = 'none');

    // Make address fields not required
    addressFields.forEach(container => {
      container.querySelectorAll('input').forEach(input => input.removeAttribute('required'));
    });
  } else if (shippingType === 'patients') {
    practiceLocationSelect.style.display = 'none';
    patientShippingNote.style.display = 'block';
    addressFields.forEach(field => field.style.display = 'block');

    // Make address fields required
    addressFields.forEach(container => {
      container.querySelectorAll('input').forEach(input => input.setAttribute('required', ''));
    });
  } else {
    practiceLocationSelect.style.display = 'none';
    patientShippingNote.style.display = 'none';
    addressFields.forEach(field => field.style.display = 'none');
  }
}

function addPatient() {
  const container = document.getElementById('patients-container');
  const index = patientCounter++;
  const shippingTypeElement = document.getElementById('shipping-type');
  const shippingType = shippingTypeElement ? shippingTypeElement.value : 'patients';
  const showAddress = shippingType === 'patients';

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

    <div class="patient-address-fields" style="display: ${showAddress ? 'block' : 'none'};">
      <div style="margin-bottom: 1rem;">
        <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Street Address *</label>
        <input type="text" name="patients[${index}][address]" ${showAddress ? 'required' : ''}
               style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
      </div>
      <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem;">
        <div>
          <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">City *</label>
          <input type="text" name="patients[${index}][city]" ${showAddress ? 'required' : ''}
                 style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
        </div>
        <div>
          <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">State *</label>
          <input type="text" name="patients[${index}][state]" maxlength="2" ${showAddress ? 'required' : ''}
                 style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
        </div>
        <div>
          <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Zip *</label>
          <input type="text" name="patients[${index}][zip]" maxlength="10" ${showAddress ? 'required' : ''}
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
</script>
