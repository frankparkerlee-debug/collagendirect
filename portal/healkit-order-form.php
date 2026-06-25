<?php
/**
 * HealKit Order Creation Form
 * Simplified version of the referral order - no delivery choice, no required docs
 * Always ships to patient address. Patient ID, Insurance Card, and notes are optional.
 */

// This file is included by portal/index.php
global $pdo, $user, $isPracticeAdmin;

if (!isset($user) || !is_array($user)) {
  echo '<div style="padding: 2rem;">Unauthorized access</div>';
  return;
}

$userId = $user['id'];

// Fetch products
$productsStmt = $pdo->prepare("SELECT id, name, size, cpt_code, price_admin, pieces_per_box FROM products WHERE active = TRUE ORDER BY name, size");
$productsStmt->execute();
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch practice physicians if practice admin (with dynamic column detection)
$physicians = [];
if ($isPracticeAdmin) {
  try {
    $ppCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'practice_physicians'")->fetchAll(PDO::FETCH_COLUMN);
    $adminCol = in_array('practice_admin_id', $ppCols) ? 'practice_admin_id' :
                (in_array('practice_manager_id', $ppCols) ? 'practice_manager_id' : 'practice_user_id');
    $hasPhysicianName = in_array('physician_name', $ppCols);
    $npiCol = in_array('npi', $ppCols) ? 'npi' : (in_array('physician_npi', $ppCols) ? 'physician_npi' : null);
    $licenseCol = in_array('license_number', $ppCols) ? 'license_number' : (in_array('license', $ppCols) ? 'license' : null);
    $signatureCol = in_array('signature_text', $ppCols) ? 'signature_text' : null;
    $hasIsActive = in_array('is_active', $ppCols);

    $where = "$adminCol = ?" . ($hasIsActive ? " AND is_active = TRUE" : "");

    if ($hasPhysicianName) {
      $select = "id, physician_name";
      if ($npiCol) $select .= ", $npiCol as npi";
      if ($licenseCol) $select .= ", $licenseCol as license_number";
      if ($signatureCol) $select .= ", $signatureCol as signature_text";
      $order = "physician_name ASC";
    } else {
      $fn = in_array('first_name', $ppCols) ? 'first_name' : 'physician_first_name';
      $ln = in_array('last_name', $ppCols) ? 'last_name' : 'physician_last_name';
      $select = "id, CONCAT($fn, ' ', $ln) as physician_name";
      if ($npiCol) $select .= ", $npiCol as npi";
      if ($licenseCol) $select .= ", $licenseCol as license_number";
      if ($signatureCol) $select .= ", $signatureCol as signature_text";
      $order = "$fn ASC, $ln ASC";
    }

    $physStmt = $pdo->prepare("SELECT $select FROM practice_physicians WHERE $where ORDER BY $order");
    $physStmt->execute([$userId]);
    $physicians = $physStmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    // Table might not exist - continue without physicians
    $physicians = [];
  }
}
?>

<!-- Load search-helpers for address autocomplete -->
<script src="/portal/search-helpers.js"></script>

<style>
.hk-form-container { max-width: 900px; margin: 0 auto; padding: 1.5rem; }
.hk-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
.hk-section-title { font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
.hk-section-num { width: 28px; height: 28px; border-radius: 50%; background: #6366f1; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 700; }
.hk-label { font-size: 0.875rem; font-weight: 500; color: #374151; display: block; margin-bottom: 0.375rem; }
.hk-input { width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.875rem; font-family: inherit; }
.hk-input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
.hk-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.hk-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
.hk-full { grid-column: 1 / -1; }
.hk-hint { font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; }
.hk-search-results { position: absolute; z-index: 20; margin-top: 4px; width: 100%; max-height: 250px; overflow-y: auto; background: white; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
.hk-search-results button { display: block; width: 100%; text-align: left; padding: 0.625rem 1rem; border: none; background: none; cursor: pointer; font-size: 0.875rem; }
.hk-search-results button:hover { background: #f8fafc; }
.hk-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: 8px; font-size: 0.875rem; font-weight: 500; cursor: pointer; border: 1px solid #e2e8f0; background: white; color: #374151; transition: all 0.15s; }
.hk-btn:hover { background: #f8fafc; }
.hk-btn-primary { background: #6366f1; color: white; border-color: #6366f1; }
.hk-btn-primary:hover { background: #4f46e5; border-color: #4f46e5; }
.hk-btn-ghost { background: transparent; border-color: transparent; }
.hk-btn-ghost:hover { background: #f1f5f9; }
.hk-info-box { background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; }
.hk-info-box p { font-size: 0.875rem; color: #4338ca; margin: 0; }
.hk-wound-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem; position: relative; }
.hk-remove-wound { position: absolute; top: 0.5rem; right: 0.5rem; background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.25rem; line-height: 1; padding: 0.25rem; }
@media (max-width: 768px) {
  .hk-grid, .hk-grid-3 { grid-template-columns: 1fr; }
}
</style>

<div class="hk-form-container">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
    <div>
      <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem;">
        <svg style="width: 24px; height: 24px; display: inline-block; margin-right: 0.5rem; vertical-align: middle; color: #6366f1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
        </svg>
        New HealKit Order
      </h1>
      <p style="color: #64748b; font-size: 0.875rem;">Simplified ordering - no insurance docs required</p>
    </div>
    <a href="?page=healkit" class="hk-btn">
      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
      </svg>
      Back to Orders
    </a>
  </div>

  <div class="hk-info-box">
    <p><strong>HealKit Orders:</strong> Patient ID, Insurance Card, and clinical notes are optional. Orders always ship to the patient address. Upload them only if you'd like us to check notes and verify benefits.</p>
  </div>

  <form id="healkit-order-form">
    <!-- 1. Patient Selection -->
    <div class="hk-card">
      <div class="hk-section-title"><span class="hk-section-num">1</span> Patient</div>
      <div style="position: relative;">
        <input id="hk-patient-search" class="hk-input" placeholder="Type patient name to search..." autocomplete="off">
        <input type="hidden" id="hk-patient-id">
        <div id="hk-search-results" class="hk-search-results" style="display: none;"></div>
      </div>
      <div id="hk-patient-hint" class="hk-hint">Search for existing patient or create a new one</div>

      <!-- Create New Patient (hidden by default) -->
      <div id="hk-create-patient" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
        <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.75rem; color: #6366f1;">New Patient</div>
        <div class="hk-grid">
          <div><label class="hk-label">First Name *</label><input id="hk-np-first" class="hk-input" placeholder="First name"></div>
          <div><label class="hk-label">Last Name *</label><input id="hk-np-last" class="hk-input" placeholder="Last name"></div>
          <div><label class="hk-label">Date of Birth</label><input id="hk-np-dob" type="date" class="hk-input"></div>
          <div><label class="hk-label">Phone</label><input id="hk-np-phone" class="hk-input" placeholder="(555) 123-4567"></div>
          <div><label class="hk-label">Email</label><input id="hk-np-email" class="hk-input" type="email" placeholder="patient@example.com"></div>
          <div><label class="hk-label">Address</label><input id="hk-np-address" class="hk-input" placeholder="Street address"></div>
          <div><label class="hk-label">City</label><input id="hk-np-city" class="hk-input" placeholder="City"></div>
          <div>
            <label class="hk-label">State</label>
            <input id="hk-np-state" class="hk-input" placeholder="State" maxlength="2">
          </div>
          <div><label class="hk-label">ZIP</label><input id="hk-np-zip" class="hk-input" placeholder="ZIP code"></div>
        </div>
        <div style="margin-top: 0.75rem;">
          <div style="font-weight: 500; font-size: 0.8125rem; color: #64748b; margin-bottom: 0.5rem;">Insurance (Optional)</div>
          <div class="hk-grid">
            <div><input id="hk-np-ins-provider" class="hk-input" placeholder="Insurance Carrier"></div>
            <div><input id="hk-np-ins-member-id" class="hk-input" placeholder="Member ID"></div>
            <div><input id="hk-np-ins-group-id" class="hk-input" placeholder="Group Number"></div>
            <div><input id="hk-np-ins-payer-phone" class="hk-input" placeholder="Payer Phone"></div>
          </div>
        </div>
        <button type="button" id="hk-btn-create-patient" class="hk-btn hk-btn-primary" style="width: 100%; margin-top: 0.75rem;">Save Patient & Use for Order</button>
        <div id="hk-np-hint" class="hk-hint" style="margin-top: 0.5rem;"></div>
      </div>
    </div>

    <!-- 2. Wounds & Products -->
    <div class="hk-card">
      <div class="hk-section-title">
        <span class="hk-section-num">2</span> Wounds & Products
        <button type="button" id="hk-add-wound" class="hk-btn hk-btn-primary" style="margin-left: auto; padding: 0.375rem 0.75rem; font-size: 0.8125rem;">+ Add Wound</button>
      </div>
      <div id="hk-wounds-container">
        <!-- Wounds added dynamically -->
      </div>
    </div>

    <!-- 3. Optional Documents -->
    <div class="hk-card">
      <div class="hk-section-title"><span class="hk-section-num">3</span> Documents (Optional)</div>
      <div class="hk-grid-3">
        <div>
          <label class="hk-label">Visit Notes</label>
          <input type="file" id="hk-file-rx" accept=".pdf,.txt,image/*" class="hk-input" style="padding: 0.375rem;">
          <div class="hk-hint">Clinical notes if available</div>
        </div>
        <div>
          <label class="hk-label">Baseline Wound Photo</label>
          <input type="file" id="hk-file-wound-photo" accept="image/*" class="hk-input" style="padding: 0.375rem;">
          <div class="hk-hint">Optional baseline documentation</div>
        </div>
        <div>
          <label class="hk-label">IVR Document</label>
          <input type="file" id="hk-file-ivr" accept=".pdf,.txt,image/*" class="hk-input" style="padding: 0.375rem;">
          <div class="hk-hint">Insurance Verification Record</div>
        </div>
      </div>
      <div class="hk-grid" style="margin-top: 1rem;">
        <div>
          <label class="hk-label">Patient ID</label>
          <input type="file" id="hk-file-id-card" accept="image/*,.pdf" class="hk-input" style="padding: 0.375rem;">
          <div class="hk-hint">Optional - Driver's License or State ID</div>
        </div>
        <div>
          <label class="hk-label">Insurance Card</label>
          <input type="file" id="hk-file-ins-card" accept="image/*,.pdf" class="hk-input" style="padding: 0.375rem;">
          <div class="hk-hint">Optional - front and back</div>
        </div>
      </div>
      <div style="margin-top: 1rem;">
        <label class="hk-label">Clinical Notes (text)</label>
        <textarea id="hk-notes" class="hk-input" rows="3" placeholder="Paste notes here (optional)"></textarea>
      </div>
    </div>

    <!-- 4. E-Signature -->
    <div class="hk-card">
      <div class="hk-section-title"><span class="hk-section-num">4</span> Physician Signature</div>
      <div class="hk-grid">
        <?php if ($isPracticeAdmin && !empty($physicians)): ?>
        <div>
          <label class="hk-label">Ordering Physician *</label>
          <select id="hk-physician-select" class="hk-input">
            <option value="">Select Physician...</option>
            <?php foreach ($physicians as $phys): ?>
            <option value="<?= htmlspecialchars($phys['id']) ?>"
              data-name="<?= htmlspecialchars($phys['physician_name']) ?>"
              data-npi="<?= htmlspecialchars($phys['npi'] ?? '') ?>"
              data-license="<?= htmlspecialchars($phys['license_number'] ?? '') ?>"
              data-signature="<?= htmlspecialchars($phys['signature_text'] ?? $phys['physician_name']) ?>">
              <?= htmlspecialchars($phys['physician_name']) ?>
            </option>
            <?php endforeach; ?>
            <option value="manual">Enter Manually</option>
          </select>
        </div>
        <?php endif; ?>
        <div>
          <label class="hk-label">E-Signature Name *</label>
          <input id="hk-sign-name" class="hk-input" placeholder="Dr. Jane Doe" value="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>">
        </div>
        <div>
          <label class="hk-label">Title</label>
          <input id="hk-sign-title" class="hk-input" placeholder="MD / PA-C / NP" value="<?= htmlspecialchars($user['credentials'] ?? '') ?>">
        </div>
        <input type="hidden" id="hk-physician-id" value="">
        <input type="hidden" id="hk-physician-npi" value="">
        <input type="hidden" id="hk-physician-license" value="">
      </div>
      <label style="display: flex; align-items: flex-start; gap: 0.5rem; margin-top: 1rem; font-size: 0.875rem;">
        <input type="checkbox" id="hk-ack-sig" style="margin-top: 0.25rem;">
        <span>I certify medical necessity and authorize this order (e-signature).</span>
      </label>
    </div>

    <!-- Submit -->
    <div style="display: flex; justify-content: space-between; gap: 1rem;">
      <button type="button" id="hk-btn-draft" class="hk-btn">Save as Draft</button>
      <button type="button" id="hk-btn-submit" class="hk-btn hk-btn-primary">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Submit HealKit Order
      </button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const $ = s => document.querySelector(s);
  const productsData = <?= json_encode($products) ?>;
  let woundCount = 0;
  let _patientId = null;

  // ---- Patient Search ----
  const searchInput = $('#hk-patient-search');
  const searchResults = $('#hk-search-results');
  const patientIdInput = $('#hk-patient-id');
  const createSection = $('#hk-create-patient');

  searchInput.addEventListener('input', async () => {
    const q = searchInput.value.trim();
    _patientId = null;
    patientIdInput.value = '';
    if (q.length < 2) { searchResults.style.display = 'none'; return; }

    try {
      const r = await fetch(`/portal/index.php?action=patients&limit=8&q=${encodeURIComponent(q)}`);
      const data = await r.json();
      const rows = data.rows || [];

      let html = rows.map(p =>
        `<button type="button" data-id="${p.id}" data-name="${p.first_name} ${p.last_name}">${p.first_name} ${p.last_name} — ${p.dob || 'N/A'} • ${p.phone || ''}</button>`
      ).join('');
      html += `<div style="border-top: 1px solid #e2e8f0; margin: 0.25rem 0;"></div>`;
      html += `<button type="button" id="hk-opt-create" style="color: #6366f1; font-weight: 500;">+ Create new patient "${q}"</button>`;

      searchResults.innerHTML = html;
      searchResults.style.display = 'block';

      searchResults.querySelectorAll('[data-id]').forEach(btn => {
        btn.addEventListener('click', () => {
          _patientId = btn.dataset.id;
          patientIdInput.value = btn.dataset.id;
          searchInput.value = btn.dataset.name;
          searchResults.style.display = 'none';
          createSection.style.display = 'none';
          $('#hk-patient-hint').textContent = 'Patient selected: ' + btn.dataset.name;
        });
      });

      const createBtn = searchResults.querySelector('#hk-opt-create');
      if (createBtn) {
        createBtn.addEventListener('click', () => {
          searchResults.style.display = 'none';
          createSection.style.display = 'block';
          const parts = q.split(' ');
          if (parts.length >= 2) {
            $('#hk-np-first').value = parts[0];
            $('#hk-np-last').value = parts.slice(1).join(' ');
          } else {
            $('#hk-np-first').value = q;
          }
          $('#hk-np-first').focus();
        });
      }
    } catch (e) {
      console.error('Patient search error:', e);
    }
  });

  // Close search results when clicking outside
  document.addEventListener('click', (e) => {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
      searchResults.style.display = 'none';
    }
  });

  // ---- Create Patient ----
  $('#hk-btn-create-patient').addEventListener('click', async () => {
    const hint = $('#hk-np-hint');
    const first = $('#hk-np-first').value.trim();
    const last = $('#hk-np-last').value.trim();
    if (!first || !last) { hint.textContent = 'First and last name required'; hint.style.color = '#ef4444'; return; }

    const body = new FormData();
    body.append('first_name', first);
    body.append('last_name', last);
    body.append('dob', $('#hk-np-dob').value);
    body.append('phone', $('#hk-np-phone').value);
    body.append('email', $('#hk-np-email').value);
    body.append('address', $('#hk-np-address').value);
    body.append('city', $('#hk-np-city').value);
    body.append('state', $('#hk-np-state').value);
    body.append('zip', $('#hk-np-zip').value);
    body.append('insurance_provider', $('#hk-np-ins-provider').value);
    body.append('insurance_member_id', $('#hk-np-ins-member-id').value);
    body.append('insurance_group_id', $('#hk-np-ins-group-id').value);
    body.append('insurance_payer_phone', $('#hk-np-ins-payer-phone').value);

    try {
      const r = await fetch('/portal/index.php?action=patient.save', { method: 'POST', body });
      const data = await r.json();
      if (data.ok) {
        _patientId = data.id;
        patientIdInput.value = data.id;
        searchInput.value = `${first} ${last}`;
        createSection.style.display = 'none';
        hint.textContent = 'Patient created!';
        hint.style.color = '#10b981';
        $('#hk-patient-hint').textContent = 'Patient selected: ' + first + ' ' + last;
      } else {
        hint.textContent = data.error || 'Failed to create patient';
        hint.style.color = '#ef4444';
      }
    } catch (e) {
      hint.textContent = 'Network error: ' + e.message;
      hint.style.color = '#ef4444';
    }
  });

  // ---- Wounds ----
  function addWound() {
    woundCount++;
    const idx = woundCount;
    const productOptions = productsData.map(p =>
      `<option value="${p.id}" data-name="${p.name} ${p.size}" data-cpt="${p.cpt_code || ''}" data-price="${p.price_admin || 0}">${p.name} ${p.size}</option>`
    ).join('');

    const html = `
      <div class="hk-wound-card" data-wound-index="${idx}">
        <button type="button" class="hk-remove-wound" onclick="this.closest('.hk-wound-card').remove()" title="Remove wound">&times;</button>
        <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.75rem; color: #6366f1;">Wound ${idx}</div>
        <div class="hk-grid">
          <div>
            <label class="hk-label">Location *</label>
            <select class="hk-input wound-location">
              <option value="">Select...</option>
              <option value="Head/Face">Head/Face</option>
              <option value="Neck">Neck</option>
              <option value="Chest">Chest</option>
              <option value="Abdomen">Abdomen</option>
              <option value="Back">Back</option>
              <option value="Upper Arm">Upper Arm</option>
              <option value="Lower Arm/Wrist">Lower Arm/Wrist</option>
              <option value="Hand/Finger">Hand/Finger</option>
              <option value="Hip/Buttock">Hip/Buttock</option>
              <option value="Upper Leg/Thigh">Upper Leg/Thigh</option>
              <option value="Knee">Knee</option>
              <option value="Lower Leg/Calf">Lower Leg/Calf</option>
              <option value="Ankle">Ankle</option>
              <option value="Foot/Toe">Foot/Toe</option>
              <option value="Sacrum/Coccyx">Sacrum/Coccyx</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div>
            <label class="hk-label">Laterality</label>
            <select class="hk-input wound-laterality">
              <option value="">N/A</option>
              <option value="left">Left</option>
              <option value="right">Right</option>
              <option value="bilateral">Bilateral</option>
            </select>
          </div>
          <div>
            <label class="hk-label">Primary Dressing *</label>
            <select class="hk-input wound-product">
              <option value="">Select product...</option>
              ${productOptions}
            </select>
          </div>
          <div>
            <label class="hk-label">Secondary Dressing</label>
            <select class="hk-input wound-secondary-product">
              <option value="">None</option>
              ${productOptions}
            </select>
          </div>
          <div>
            <label class="hk-label">Additional Supply</label>
            <select class="hk-input wound-additional-product">
              <option value="">None</option>
              ${productOptions}
            </select>
          </div>
          <div>
            <label class="hk-label">Qty per Change</label>
            <input type="number" class="hk-input wound-qty" value="1" min="1" max="10">
          </div>
          <div>
            <label class="hk-label">Frequency/Week</label>
            <input type="number" class="hk-input wound-freq" value="7" min="1" max="21">
          </div>
          <div>
            <label class="hk-label">Duration (Days)</label>
            <input type="number" class="hk-input wound-duration" value="30" min="1" max="90">
          </div>
        </div>
      </div>
    `;
    $('#hk-wounds-container').insertAdjacentHTML('beforeend', html);
  }

  $('#hk-add-wound').addEventListener('click', addWound);
  // Start with one wound
  addWound();

  // ---- Physician Selection ----
  const physSelect = $('#hk-physician-select');
  if (physSelect) {
    physSelect.addEventListener('change', function() {
      const opt = this.options[this.selectedIndex];
      const signName = $('#hk-sign-name');
      if (this.value === 'manual') {
        signName.value = '';
        signName.removeAttribute('readonly');
        $('#hk-physician-id').value = '';
      } else if (this.value) {
        signName.value = opt.dataset.signature || opt.dataset.name;
        signName.setAttribute('readonly', 'readonly');
        $('#hk-physician-id').value = this.value;
        $('#hk-physician-npi').value = opt.dataset.npi || '';
        $('#hk-physician-license').value = opt.dataset.license || '';
      }
    });
  }

  // ---- Collect Wounds Data ----
  function collectWounds() {
    const wounds = [];
    document.querySelectorAll('.hk-wound-card').forEach(el => {
      const productSelect = el.querySelector('.wound-product');
      const selectedOpt = productSelect?.options[productSelect.selectedIndex];
      const secondarySelect = el.querySelector('.wound-secondary-product');
      const secondaryOpt = secondarySelect?.options[secondarySelect.selectedIndex];
      const additionalSelect = el.querySelector('.wound-additional-product');
      const additionalOpt = additionalSelect?.options[additionalSelect.selectedIndex];

      const wound = {
        location: el.querySelector('.wound-location')?.value || '',
        laterality: el.querySelector('.wound-laterality')?.value || '',
        product_id: productSelect?.value || '',
        product_name: selectedOpt?.dataset.name || '',
        product_cpt: selectedOpt?.dataset.cpt || '',
        product_price: parseFloat(selectedOpt?.dataset.price || 0),
        qty_per_change: parseInt(el.querySelector('.wound-qty')?.value || 1),
        frequency_per_week: parseInt(el.querySelector('.wound-freq')?.value || 7),
        duration_days: parseInt(el.querySelector('.wound-duration')?.value || 30)
      };

      // Add secondary dressing if selected
      if (secondarySelect?.value) {
        wound.secondary_product_id = secondarySelect.value;
        wound.secondary_product_name = secondaryOpt?.dataset.name || '';
        wound.secondary_product_cpt = secondaryOpt?.dataset.cpt || '';
        wound.secondary_product_price = parseFloat(secondaryOpt?.dataset.price || 0);
      }

      // Add additional supply if selected
      if (additionalSelect?.value) {
        wound.additional_product_id = additionalSelect.value;
        wound.additional_product_name = additionalOpt?.dataset.name || '';
        wound.additional_product_cpt = additionalOpt?.dataset.cpt || '';
        wound.additional_product_price = parseFloat(additionalOpt?.dataset.price || 0);
      }

      wounds.push(wound);
    });
    return wounds;
  }

  // ---- Submit Order ----
  async function submitOrder(isDraft = false) {
    const pid = _patientId || patientIdInput.value;
    if (!pid) { alert('Please select or create a patient first.'); return; }

    const wounds = collectWounds();
    if (wounds.length === 0) { alert('Please add at least one wound.'); return; }

    // Validate wounds
    for (let i = 0; i < wounds.length; i++) {
      if (!isDraft && (!wounds[i].location || !wounds[i].product_id)) {
        alert(`Wound ${i + 1}: Location and product are required.`);
        return;
      }
    }

    if (!isDraft) {
      const signName = $('#hk-sign-name')?.value?.trim();
      if (!signName) { alert('E-Signature name is required.'); return; }
      if (!$('#hk-ack-sig').checked) { alert('Please check the e-signature certification.'); return; }
    }

    const btn = isDraft ? $('#hk-btn-draft') : $('#hk-btn-submit');
    const origText = btn.textContent;
    btn.disabled = true;
    btn.textContent = isDraft ? 'Saving...' : 'Submitting...';

    try {
      const body = new FormData();
      body.append('patient_id', pid);
      body.append('payment_type', 'insurance');
      body.append('wounds_data', JSON.stringify(wounds));
      body.append('delivery_to', 'patient'); // Always patient for HealKit
      body.append('notes_text', $('#hk-notes')?.value || '');
      body.append('sign_name', $('#hk-sign-name')?.value || '');
      body.append('sign_title', $('#hk-sign-title')?.value || '');
      body.append('esign_confirm', isDraft ? '0' : '1');
      body.append('order_type', 'healkit'); // Flag for API
      body.append('physician_id', $('#hk-physician-id')?.value || '');
      body.append('physician_npi', $('#hk-physician-npi')?.value || '');
      body.append('physician_license', $('#hk-physician-license')?.value || '');

      if (isDraft) body.append('save_as_draft', '1');

      // File uploads
      const fileRx = $('#hk-file-rx');
      if (fileRx?.files[0]) body.append('file_rx_note', fileRx.files[0]);
      const filePhoto = $('#hk-file-wound-photo');
      if (filePhoto?.files[0]) body.append('baseline_wound_photo', filePhoto.files[0]);
      const fileIvr = $('#hk-file-ivr');
      if (fileIvr?.files[0]) body.append('file_ivr', fileIvr.files[0]);
      const fileId = $('#hk-file-id-card');
      if (fileId?.files[0]) body.append('id_card', fileId.files[0]);
      const fileIns = $('#hk-file-ins-card');
      if (fileIns?.files[0]) body.append('ins_card', fileIns.files[0]);

      const r = await fetch('/api/portal/orders.create.php', { method: 'POST', body });
      const text = await r.text();
      let data;
      try { data = JSON.parse(text); } catch { alert('Server error:\n' + text); return; }

      if (data.ok) {
        alert(isDraft ? 'Draft saved!' : 'HealKit order submitted successfully!');
        window.location.href = '?page=healkit';
      } else {
        // Surface the real error so we can diagnose. The server returns
        // 'error' (machine code) and 'debug_message' (the actual PDO/PHP error).
        let msg = data.error || 'Order creation failed';
        if (data.debug_message) {
          msg += '\n\nDetails: ' + data.debug_message;
        }
        if (data.debug_file && data.debug_line) {
          msg += '\n(' + data.debug_file + ':' + data.debug_line + ')';
        }
        alert('Error: ' + msg);
        console.error('HealKit submit error:', data);
      }
    } catch (e) {
      alert('Network error: ' + e.message);
    } finally {
      btn.disabled = false;
      btn.textContent = origText;
    }
  }

  $('#hk-btn-submit').addEventListener('click', () => submitOrder(false));
  $('#hk-btn-draft').addEventListener('click', () => submitOrder(true));
});
</script>
