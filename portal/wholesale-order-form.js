/**
 * Wholesale Order Form - Patient-First Batch Ordering
 * JavaScript functionality for cart-based ordering system
 */

// Global state
const wholesaleState = {
  cart: [],
  currentPatient: null,
  currentDeliveryType: 'patient',
  currentLocationId: null,
  selectedProducts: [],
  products: [],
  patients: [],
  practiceLocations: [],
  physicians: [],
  isPracticeAdmin: false
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
  initializeWholesaleForm();
});

function initializeWholesaleForm() {
  // Load products, patients, practice locations, and physicians
  loadProducts();
  loadPatients();
  loadPracticeLocations();
  loadPhysicians();

  // Initialize Google Places Autocomplete
  initializeAddressAutocomplete();

  // Event listeners
  document.getElementById('toggle-new-patient').addEventListener('click', toggleNewPatientForm);
  document.getElementById('existing-patient-select').addEventListener('change', selectExistingPatient);
  document.getElementById('btn-continue-to-products').addEventListener('click', continueToProducts);
  document.getElementById('btn-back-to-patient').addEventListener('click', backToPatient);
  document.getElementById('btn-add-to-cart').addEventListener('click', addToCart);
  document.getElementById('btn-submit-all-orders').addEventListener('click', submitAllOrders);
  document.getElementById('new-phone').addEventListener('input', formatPhoneNumber);

  // Initial delivery selection
  selectDelivery('patient');
}

// Load products from API
async function loadProducts() {
  try {
    const response = await fetch('/portal/index.php?action=products');
    const data = await response.json();
    if (data.ok && data.rows) {
      wholesaleState.products = data.rows;
      renderProductsGrid();
    }
  } catch (error) {
    console.error('Failed to load products:', error);
    alert('Error loading products. Please refresh the page.');
  }
}

// Load patients from API
async function loadPatients() {
  try {
    const response = await fetch('/portal/index.php?action=patients');
    const data = await response.json();
    if (data.ok && data.rows) {
      wholesaleState.patients = data.rows;
      const select = document.getElementById('existing-patient-select');
      select.innerHTML = '<option value="">-- Choose patient --</option>';
      data.rows.forEach(p => {
        const option = document.createElement('option');
        option.value = p.id;
        option.textContent = `${p.first_name} ${p.last_name} (DOB: ${p.dob || 'N/A'})`;
        option.dataset.patient = JSON.stringify(p);
        select.appendChild(option);
      });
    }
  } catch (error) {
    console.error('Failed to load patients:', error);
  }
}

// Load practice locations from API
async function loadPracticeLocations() {
  try {
    const response = await fetch('/portal/index.php?action=practice.locations');
    const data = await response.json();
    if (data.ok && data.locations) {
      wholesaleState.practiceLocations = data.locations;
      const select = document.getElementById('practice-location-select');
      select.innerHTML = '<option value="">-- Choose location --</option>';
      data.locations.forEach(loc => {
        const option = document.createElement('option');
        option.value = loc.id;
        option.textContent = `${loc.location_name} - ${loc.address}, ${loc.city}, ${loc.state}`;
        option.dataset.location = JSON.stringify(loc);
        if (loc.is_primary) {
          option.selected = true;
          wholesaleState.currentLocationId = loc.id;
        }
        select.appendChild(option);
      });
    }
  } catch (error) {
    console.error('Failed to load practice locations:', error);
  }
}

// Load physicians for practice admins
async function loadPhysicians() {
  try {
    const response = await fetch('/api/portal/get-physicians.php');
    const data = await response.json();
    if (data.ok && data.physicians && data.physicians.length > 0) {
      wholesaleState.physicians = data.physicians;
      wholesaleState.isPracticeAdmin = true;

      const select = document.getElementById('wholesale-physician-select');
      const signNameInput = document.getElementById('wholesale-sign-name');

      // Populate dropdown
      data.physicians.forEach(phys => {
        const option = document.createElement('option');
        option.value = phys.id;
        option.textContent = phys.physician_name;
        option.dataset.name = phys.physician_name;
        option.dataset.npi = phys.npi || '';
        option.dataset.license = phys.license_number || '';
        option.dataset.signature = phys.signature_text || phys.physician_name;
        select.insertBefore(option, select.options[1]); // Insert before "Enter Manually"
      });

      // Handle physician selection
      select.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];

        if (this.value === 'manual') {
          signNameInput.style.display = 'block';
          signNameInput.value = '';
          signNameInput.removeAttribute('readonly');
          document.getElementById('wholesale-physician-id').value = '';
          document.getElementById('wholesale-physician-npi').value = '';
          document.getElementById('wholesale-physician-license').value = '';
        } else if (this.value) {
          signNameInput.style.display = 'block';
          signNameInput.value = selectedOption.dataset.signature || selectedOption.dataset.name;
          signNameInput.setAttribute('readonly', 'readonly');
          document.getElementById('wholesale-physician-id').value = this.value;
          document.getElementById('wholesale-physician-npi').value = selectedOption.dataset.npi;
          document.getElementById('wholesale-physician-license').value = selectedOption.dataset.license;
        } else {
          signNameInput.style.display = 'none';
          signNameInput.value = '';
          document.getElementById('wholesale-physician-id').value = '';
          document.getElementById('wholesale-physician-npi').value = '';
          document.getElementById('wholesale-physician-license').value = '';
        }
      });
    }
  } catch (error) {
    console.error('Failed to load physicians:', error);
  }
}

// Toggle new patient form
function toggleNewPatientForm(e) {
  e.preventDefault();
  const form = document.getElementById('new-patient-form');
  const select = document.getElementById('existing-patient-select');

  if (form.style.display === 'none') {
    form.style.display = 'block';
    select.value = '';
    select.disabled = true;
    e.target.textContent = 'select existing patient';
  } else {
    form.style.display = 'none';
    select.disabled = false;
    e.target.textContent = 'create a new patient';
    clearNewPatientForm();
  }
}

// Select existing patient
function selectExistingPatient(e) {
  const select = e.target;
  if (select.value) {
    const option = select.options[select.selectedIndex];
    wholesaleState.currentPatient = JSON.parse(option.dataset.patient);
  } else {
    wholesaleState.currentPatient = null;
  }
}

// Delivery type selection
function selectDelivery(type) {
  wholesaleState.currentDeliveryType = type;
  document.getElementById('delivery-type').value = type;

  // Update UI
  document.querySelectorAll('#delivery-patient, #delivery-office').forEach(el => {
    el.classList.remove('selected');
  });
  document.getElementById(`delivery-${type}`).classList.add('selected');

  // Show/hide practice location selector
  const locationSelector = document.getElementById('practice-location-selector');
  if (type === 'office') {
    locationSelector.style.display = 'block';
    // Set to primary location if not already set
    if (!wholesaleState.currentLocationId && wholesaleState.practiceLocations.length > 0) {
      const primaryLoc = wholesaleState.practiceLocations.find(loc => loc.is_primary);
      if (primaryLoc) {
        wholesaleState.currentLocationId = primaryLoc.id;
        document.getElementById('practice-location-select').value = primaryLoc.id;
      }
    }
  } else {
    locationSelector.style.display = 'none';
    wholesaleState.currentLocationId = null;
  }
}

// Handle location selection
document.addEventListener('DOMContentLoaded', function() {
  const locationSelect = document.getElementById('practice-location-select');
  if (locationSelect) {
    locationSelect.addEventListener('change', function() {
      wholesaleState.currentLocationId = this.value || null;
    });
  }
});

// Continue to products step
function continueToProducts() {
  // Validate patient selection
  const existingSelect = document.getElementById('existing-patient-select');
  const newForm = document.getElementById('new-patient-form');

  if (newForm.style.display !== 'none') {
    // Validate new patient form
    const firstName = document.getElementById('new-first-name').value.trim();
    const lastName = document.getElementById('new-last-name').value.trim();
    const dob = document.getElementById('new-dob').value;
    const phone = document.getElementById('new-phone').value.trim();
    const address = document.getElementById('new-address').value.trim();
    const city = document.getElementById('new-city').value.trim();
    const state = document.getElementById('new-state').value.trim();
    const zip = document.getElementById('new-zip').value.trim();

    if (!firstName || !lastName || !dob || !phone || !address || !city || !state || !zip) {
      alert('Please fill out all required patient fields');
      return;
    }

    // Create temporary patient object
    wholesaleState.currentPatient = {
      isNew: true,
      first_name: firstName,
      last_name: lastName,
      dob: dob,
      phone: standardizePhoneNumber(phone),
      address: address,
      city: city,
      state: state,
      zip: zip,
      accepts_sms: document.getElementById('new-accepts-sms').checked
    };
  } else if (!existingSelect.value) {
    alert('Please select a patient or create a new one');
    return;
  }

  // Validate location if shipping to office
  if (wholesaleState.currentDeliveryType === 'office' && !wholesaleState.currentLocationId) {
    alert('Please select a practice location for office delivery');
    return;
  }

  // Show products step
  document.getElementById('step-patient').style.display = 'none';
  document.getElementById('step-products').style.display = 'block';
}

// Back to patient step
function backToPatient() {
  document.getElementById('step-products').style.display = 'none';
  document.getElementById('step-patient').style.display = 'block';

  // Clear selected products
  wholesaleState.selectedProducts = [];
  document.querySelectorAll('.product-item').forEach(el => el.classList.remove('selected'));
  document.getElementById('btn-add-to-cart').disabled = true;
}

// Render products grid
function renderProductsGrid() {
  const grid = document.getElementById('products-grid');
  grid.innerHTML = '';

  wholesaleState.products.forEach(product => {
    const div = document.createElement('div');
    div.className = 'product-item';
    div.dataset.productId = product.id;
    div.onclick = () => toggleProductSelection(product.id);

    // price_wholesale is already per BOX, not per piece
    const pricePerBox = parseFloat(product.price_wholesale || 0);
    const piecesPerBox = parseInt(product.pieces_per_box || 10);

    div.innerHTML = `
      <div style="font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">${product.name}</div>
      <div style="font-size: 0.875rem; color: #64748b; margin-bottom: 0.75rem;">${product.size || ''}</div>
      <div style="font-size: 0.875rem; color: #10b981; font-weight: 600; margin-bottom: 0.25rem;">$${pricePerBox.toFixed(2)} per box</div>
      <div style="font-size: 0.75rem; color: #64748b;">${piecesPerBox} pieces per box</div>
      <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #e2e8f0;">
        <input type="number"
               class="form-control form-control-sm product-quantity"
               data-product-id="${product.id}"
               placeholder="Boxes"
               min="1"
               value="1"
               onclick="event.stopPropagation();"
               style="text-align: center; border-radius: 8px;">
        <small style="font-size: 0.7rem; color: #94a3b8; display: block; margin-top: 0.25rem;">Number of boxes</small>
      </div>
    `;

    grid.appendChild(div);
  });
}

// Toggle product selection
function toggleProductSelection(productId) {
  const element = document.querySelector(`[data-product-id="${productId}"]`);
  const index = wholesaleState.selectedProducts.findIndex(p => p.id === productId);

  if (index > -1) {
    // Deselect
    wholesaleState.selectedProducts.splice(index, 1);
    element.classList.remove('selected');
  } else {
    // Select
    const product = wholesaleState.products.find(p => p.id === productId);
    const quantityInput = element.querySelector('.product-quantity');
    const boxes = parseInt(quantityInput.value) || 1;

    wholesaleState.selectedProducts.push({
      ...product,
      boxes: boxes
    });
    element.classList.add('selected');
  }

  // Enable/disable add to cart button
  document.getElementById('btn-add-to-cart').disabled = wholesaleState.selectedProducts.length === 0;
}

// Add current patient and products to cart
function addToCart() {
  if (!wholesaleState.currentPatient || wholesaleState.selectedProducts.length === 0) {
    alert('Please select patient and at least one product');
    return;
  }

  // Update quantities from inputs
  wholesaleState.selectedProducts.forEach(product => {
    const input = document.querySelector(`.product-quantity[data-product-id="${product.id}"]`);
    if (input) {
      product.boxes = parseInt(input.value) || 1;
    }
  });

  // Add to cart
  wholesaleState.cart.push({
    patient: { ...wholesaleState.currentPatient },
    products: [...wholesaleState.selectedProducts],
    deliveryType: wholesaleState.currentDeliveryType,
    locationId: wholesaleState.currentLocationId
  });

  // Reset for next patient
  wholesaleState.currentPatient = null;
  wholesaleState.selectedProducts = [];
  wholesaleState.currentDeliveryType = 'patient';

  // Clear UI
  document.getElementById('existing-patient-select').value = '';
  clearNewPatientForm();
  document.getElementById('new-patient-form').style.display = 'none';
  document.getElementById('existing-patient-select').disabled = false;
  document.getElementById('toggle-new-patient').textContent = 'create a new patient';
  selectDelivery('patient');

  // Back to patient step
  backToPatient();

  // Render cart
  renderCart();

  alert('Patient and products added to cart! Add another patient or submit your orders.');
}

// Render cart
function renderCart() {
  const count = wholesaleState.cart.length;
  document.getElementById('cart-count').textContent = count;

  if (count === 0) {
    document.getElementById('cart-empty').style.display = 'block';
    document.getElementById('cart-items-list').style.display = 'none';
    document.getElementById('cart-total').style.display = 'none';
    document.getElementById('wholesale-physician-selector').style.display = 'none';
    document.getElementById('btn-submit-all-orders').style.display = 'none';
    return;
  }

  document.getElementById('cart-empty').style.display = 'none';
  document.getElementById('cart-items-list').style.display = 'block';
  document.getElementById('cart-total').style.display = 'block';

  // Show physician selector for practice admins
  if (wholesaleState.isPracticeAdmin) {
    document.getElementById('wholesale-physician-selector').style.display = 'block';
  }

  document.getElementById('btn-submit-all-orders').style.display = 'block';

  // Render cart items
  const list = document.getElementById('cart-items-list');
  list.innerHTML = '';

  let totalProducts = 0;
  let totalCost = 0;

  wholesaleState.cart.forEach((item, index) => {
    const cartItem = document.createElement('div');
    cartItem.className = 'cart-item';

    let productsHtml = '';
    let itemCost = 0;

    item.products.forEach(product => {
      // price_wholesale is already per BOX, not per piece
      const pricePerBox = parseFloat(product.price_wholesale || 0);
      const productCost = pricePerBox * parseInt(product.boxes);
      itemCost += productCost;
      totalProducts++;

      productsHtml += `
        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
          <span>• ${product.name} (${product.boxes} boxes × $${pricePerBox.toFixed(2)})</span>
          <span style="font-weight: 600;">$${productCost.toFixed(2)}</span>
        </div>
      `;
    });

    totalCost += itemCost;

    cartItem.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
        <div>
          <div style="font-weight: 600; color: #1e293b;">${item.patient.first_name} ${item.patient.last_name}</div>
          <div style="font-size: 0.75rem; color: #64748b;">${item.deliveryType === 'patient' ? 'Patient Address' : 'Office Stock'}</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 6px;">
          <svg style="width: 12px; height: 12px; display: inline-block;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
          </svg>
        </button>
      </div>
      ${productsHtml}
      <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between;">
        <span style="font-size: 0.875rem; font-weight: 600; color: #1e293b;">Subtotal:</span>
        <span style="font-size: 0.875rem; font-weight: 700; color: #10b981;">$${itemCost.toFixed(2)}</span>
      </div>
    `;

    list.appendChild(cartItem);
  });

  // Update totals
  document.getElementById('total-patients').textContent = count;
  document.getElementById('total-products').textContent = totalProducts;
  document.getElementById('total-cost').textContent = '$' + totalCost.toFixed(2);
}

// Remove item from cart
function removeFromCart(index) {
  if (confirm('Remove this patient and their products from the cart?')) {
    wholesaleState.cart.splice(index, 1);
    renderCart();
  }
}

// Submit all orders
async function submitAllOrders() {
  if (wholesaleState.cart.length === 0) {
    alert('Cart is empty');
    return;
  }

  // Validate physician selection for practice admins
  if (wholesaleState.isPracticeAdmin) {
    const physicianSelect = document.getElementById('wholesale-physician-select');
    const signTitle = document.getElementById('wholesale-sign-title').value.trim();

    if (!physicianSelect.value || !signTitle) {
      alert('Please select a physician and provide their title before submitting');
      return;
    }
  }

  const totalPatients = wholesaleState.cart.length;
  const totalProducts = wholesaleState.cart.reduce((sum, item) => sum + item.products.length, 0);

  if (!confirm(`Submit ${totalPatients} patient(s) with ${totalProducts} product(s)?`)) {
    return;
  }

  const button = document.getElementById('btn-submit-all-orders');
  const originalText = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<svg style="width: 18px; height: 18px; display: inline-block; margin-right: 0.5rem; vertical-align: middle; animation: spin 1s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" fill="none" stroke-linecap="round"></path></svg> Submitting...';

  try {
    // Collect physician data
    const physicianData = wholesaleState.isPracticeAdmin ? {
      physician_id: document.getElementById('wholesale-physician-id').value,
      physician_npi: document.getElementById('wholesale-physician-npi').value,
      physician_license: document.getElementById('wholesale-physician-license').value,
      sign_name: document.getElementById('wholesale-sign-name').value,
      sign_title: document.getElementById('wholesale-sign-title').value
    } : null;

    // Prepare cart data for batch submission
    const cartData = wholesaleState.cart.map(item => ({
      patient: item.patient,
      products: item.products.map(p => ({
        productId: p.id,
        boxes: p.boxes
      })),
      deliveryType: item.deliveryType,
      locationId: item.locationId
    }));

    // Submit all orders in a single batch request
    const response = await fetch('/portal/index.php?action=order.create.wholesale.batch', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        cart: cartData,
        physician: physicianData
      })
    });

    const data = await response.json();

    if (!data.ok) {
      throw new Error(data.error || 'Failed to submit orders');
    }

    const created = data.created || 0;
    const failed = data.failed || 0;

    if (failed === 0) {
      alert(`✓ Success! ${created} order(s) submitted.\n\nAll orders are now pending admin review.`);
      wholesaleState.cart = [];
      renderCart();
      window.location.href = '/portal/index.php?page=dashboard';
    } else {
      let message = `Submitted ${created} order(s) successfully.\n`;
      message += `${failed} order(s) failed.\n\n`;

      if (data.failures && data.failures.length > 0) {
        message += 'Failed orders:\n';
        data.failures.forEach(f => {
          message += `- ${f.patient || 'Unknown'}: ${f.error}\n`;
        });
      }

      alert(message);

      // Keep failed items in cart for retry
      // Note: This is a simplified approach - in production you'd want to match failed items precisely
      renderCart();
    }

  } catch (error) {
    console.error('Error submitting orders:', error);
    alert('Error submitting orders:\n' + error.message);
  } finally {
    button.disabled = false;
    button.innerHTML = originalText;
  }
}

// Phone number formatting
function formatPhoneNumber(e) {
  let value = e.target.value.replace(/\D/g, '');
  if (value.length >= 10) {
    value = value.substring(0, 10);
    e.target.value = `(${value.substring(0, 3)}) ${value.substring(3, 6)}-${value.substring(6, 10)}`;
  }
}

// Standardize phone number for SMS
function standardizePhoneNumber(phone) {
  const digits = phone.replace(/\D/g, '');
  if (digits.length === 10) {
    return `+1${digits}`;
  } else if (digits.length === 11 && digits[0] === '1') {
    return `+${digits}`;
  }
  return phone;
}

// Initialize Google Places Autocomplete
function initializeAddressAutocomplete() {
  if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
    console.warn('Google Places API not loaded');
    return;
  }

  const input = document.getElementById('new-address');
  const autocomplete = new google.maps.places.Autocomplete(input, {
    types: ['address'],
    componentRestrictions: { country: 'us' }
  });

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
        street = component.long_name + ' ';
      } else if (types.includes('route')) {
        street += component.long_name;
      } else if (types.includes('locality')) {
        city = component.long_name;
      } else if (types.includes('administrative_area_level_1')) {
        state = component.short_name;
      } else if (types.includes('postal_code')) {
        zip = component.short_name;
      }
    });

    document.getElementById('new-address').value = street;
    document.getElementById('new-city').value = city;
    document.getElementById('new-state').value = state;
    document.getElementById('new-zip').value = zip;
  });
}

// Clear new patient form
function clearNewPatientForm() {
  document.getElementById('new-first-name').value = '';
  document.getElementById('new-last-name').value = '';
  document.getElementById('new-dob').value = '';
  document.getElementById('new-phone').value = '';
  document.getElementById('new-address').value = '';
  document.getElementById('new-city').value = '';
  document.getElementById('new-state').value = '';
  document.getElementById('new-zip').value = '';
  document.getElementById('new-accepts-sms').checked = true;
}

// Make removeFromCart global
window.removeFromCart = removeFromCart;
window.selectDelivery = selectDelivery;
