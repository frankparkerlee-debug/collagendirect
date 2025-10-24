<?php
// admin/order-review.php - Super Admin Order Review Dashboard
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_admin();

$admin = current_admin();
if ($admin['role'] !== 'superadmin') {
    header('Location: /admin/index.php');
    exit;
}

$pageTitle = 'Order Review Queue';
require __DIR__ . '/_header.php';
?>

<div>
  <div class="mb-6 flex justify-between items-center">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Order Review Queue</h1>
      <p class="text-sm text-gray-600 mt-1">Review and manage DME orders requiring approval</p>
    </div>
    <div class="flex gap-2">
      <select id="statusFilter" class="border rounded px-3 py-2 text-sm">
        <option value="all">All Statuses</option>
        <option value="submitted" selected>Submitted</option>
        <option value="under_review">Under Review</option>
        <option value="incomplete">Incomplete</option>
      </select>
    </div>
  </div>

  <!-- Orders Grid -->
  <div id="ordersGrid" class="grid gap-4">
    <div class="text-center py-12 text-gray-500">
      Loading orders...
    </div>
  </div>
</div>

<!-- Order Detail Modal -->
<div id="orderModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
  <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
    <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
      <h2 class="text-xl font-bold" id="modalTitle">Order Review</h2>
      <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>

    <div id="modalContent" class="p-6">
      <!-- Content loaded dynamically -->
    </div>

    <div class="sticky bottom-0 bg-gray-50 border-t px-6 py-4 flex justify-end gap-3">
      <button onclick="closeModal()" class="px-4 py-2 border rounded hover:bg-gray-50">Cancel</button>
      <button onclick="markIncomplete()" class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700">Mark Incomplete</button>
      <button onclick="approveOrder()" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Approve Order</button>
    </div>
  </div>
</div>

<script>
let currentOrders = [];
let selectedOrder = null;

// Load orders on page load
document.addEventListener('DOMContentLoaded', () => {
  loadOrders();
});

async function loadOrders() {
  try {
    const response = await fetch('/api/admin/orders/pending-review.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': '<?= $_SESSION['csrf'] ?? '' ?>'
      },
      body: JSON.stringify({})
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || 'Failed to load orders');
    }

    currentOrders = data.orders || [];
    renderOrders();

  } catch (error) {
    console.error('Error loading orders:', error);
    document.getElementById('ordersGrid').innerHTML = `
      <div class="text-center py-12 text-red-600">
        Error loading orders: ${error.message}
      </div>
    `;
  }
}

function renderOrders() {
  const filter = document.getElementById('statusFilter').value;
  const filtered = filter === 'all'
    ? currentOrders
    : currentOrders.filter(o => o.status === filter);

  const grid = document.getElementById('ordersGrid');

  if (filtered.length === 0) {
    grid.innerHTML = `
      <div class="text-center py-12 text-gray-500">
        No orders ${filter !== 'all' ? 'with status "' + filter + '"' : ''}
      </div>
    `;
    return;
  }

  grid.innerHTML = filtered.map(order => `
    <div class="bg-white border rounded-lg p-4 hover:shadow-md transition cursor-pointer"
         onclick="viewOrder('${order.id}')">
      <div class="flex justify-between items-start mb-3">
        <div>
          <h3 class="font-semibold text-gray-900">
            ${order.patient_first_name} ${order.patient_last_name}
          </h3>
          <p class="text-sm text-gray-600">
            Dr. ${order.physician_first_name} ${order.physician_last_name}
            ${order.practice_name ? ' • ' + order.practice_name : ''}
          </p>
        </div>
        <span class="px-2 py-1 text-xs rounded ${getStatusColor(order.status)}">
          ${order.status.replace('_', ' ').toUpperCase()}
        </span>
      </div>

      <div class="grid grid-cols-2 gap-2 text-sm">
        <div>
          <span class="text-gray-600">Product:</span>
          <span class="font-medium">${order.product || 'N/A'}</span>
        </div>
        <div>
          <span class="text-gray-600">Payment:</span>
          <span class="font-medium">${order.payment_method || 'insurance'}</span>
        </div>
        <div>
          <span class="text-gray-600">Delivery:</span>
          <span class="font-medium">${order.delivery_location || 'patient'}</span>
        </div>
        <div>
          <span class="text-gray-600">Completeness:</span>
          <span class="font-medium ${order.is_complete ? 'text-green-600' : 'text-red-600'}">
            ${order.is_complete ? '✓ Complete' : '✗ Incomplete'}
          </span>
        </div>
      </div>

      ${!order.is_complete && order.missing_fields && order.missing_fields.length > 0 ? `
        <div class="mt-3 pt-3 border-t">
          <span class="text-xs text-red-600 font-medium">
            Missing: ${order.missing_fields.join(', ')}
          </span>
        </div>
      ` : ''}

      <div class="mt-3 text-xs text-gray-500">
        Submitted ${new Date(order.created_at).toLocaleDateString()}
      </div>
    </div>
  `).join('');
}

function getStatusColor(status) {
  const colors = {
    'submitted': 'bg-blue-100 text-blue-800',
    'under_review': 'bg-yellow-100 text-yellow-800',
    'incomplete': 'bg-red-100 text-red-800',
    'verification_pending': 'bg-purple-100 text-purple-800',
    'approved': 'bg-green-100 text-green-800'
  };
  return colors[status] || 'bg-gray-100 text-gray-800';
}

function viewOrder(orderId) {
  selectedOrder = currentOrders.find(o => o.id === orderId);
  if (!selectedOrder) return;

  document.getElementById('modalTitle').textContent = `Order #${orderId.substring(0, 8)}`;
  document.getElementById('modalContent').innerHTML = `
    <div class="space-y-6">
      <!-- Patient Info -->
      <div>
        <h3 class="font-semibold text-gray-900 mb-3">Patient Information</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div><span class="text-gray-600">Name:</span> ${selectedOrder.patient_first_name} ${selectedOrder.patient_last_name}</div>
          <div><span class="text-gray-600">DOB:</span> ${selectedOrder.patient_dob || 'N/A'}</div>
        </div>
      </div>

      <!-- Physician Info -->
      <div>
        <h3 class="font-semibold text-gray-900 mb-3">Physician Information</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div><span class="text-gray-600">Name:</span> Dr. ${selectedOrder.physician_first_name} ${selectedOrder.physician_last_name}</div>
          <div><span class="text-gray-600">Practice:</span> ${selectedOrder.practice_name || 'N/A'}</div>
          <div>
            <span class="text-gray-600">DME License:</span>
            <span class="${selectedOrder.has_dme_license ? 'text-green-600' : 'text-gray-600'}">
              ${selectedOrder.has_dme_license ? 'Yes' : 'No'}
            </span>
          </div>
        </div>
      </div>

      <!-- Order Details -->
      <div>
        <h3 class="font-semibold text-gray-900 mb-3">Order Details</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div><span class="text-gray-600">Product:</span> ${selectedOrder.product || 'N/A'}</div>
          <div><span class="text-gray-600">Payment Method:</span> ${selectedOrder.payment_method}</div>
          <div><span class="text-gray-600">Delivery Location:</span> ${selectedOrder.delivery_location}</div>
          <div><span class="text-gray-600">Status:</span> ${selectedOrder.status}</div>
        </div>
      </div>

      <!-- Completeness Check -->
      <div>
        <h3 class="font-semibold text-gray-900 mb-3">Completeness Check</h3>
        <div class="p-4 rounded ${selectedOrder.is_complete ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}">
          <div class="font-medium ${selectedOrder.is_complete ? 'text-green-800' : 'text-red-800'}">
            ${selectedOrder.is_complete ? '✓ Order is complete and ready for processing' : '✗ Order is incomplete'}
          </div>
          ${!selectedOrder.is_complete && selectedOrder.missing_fields && selectedOrder.missing_fields.length > 0 ? `
            <div class="mt-2 text-sm text-red-700">
              <strong>Missing fields:</strong>
              <ul class="list-disc list-inside mt-1">
                ${selectedOrder.missing_fields.map(f => `<li>${f.replace(/_/g, ' ')}</li>`).join('')}
              </ul>
            </div>
          ` : ''}
        </div>
      </div>
    </div>
  `;

  document.getElementById('orderModal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('orderModal').style.display = 'none';
  selectedOrder = null;
}

async function markIncomplete() {
  if (!selectedOrder) return;

  if (!confirm('Mark this order as incomplete? The physician will be notified to provide missing information.')) {
    return;
  }

  try {
    const response = await fetch('/api/admin/orders/update-status.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': '<?= $_SESSION['csrf'] ?? '' ?>'
      },
      body: JSON.stringify({
        order_id: selectedOrder.id,
        status: 'incomplete',
        notes: 'Please provide missing information: ' + (selectedOrder.missing_fields || []).join(', ')
      })
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || 'Failed to update order');
    }

    alert('Order marked as incomplete');
    closeModal();
    loadOrders();

  } catch (error) {
    alert('Error: ' + error.message);
  }
}

async function approveOrder() {
  if (!selectedOrder) return;

  if (!selectedOrder.is_complete) {
    alert('Cannot approve incomplete order. Please mark as incomplete and request missing information.');
    return;
  }

  if (!confirm('Approve this order and send to manufacturer for insurance verification?')) {
    return;
  }

  try {
    const response = await fetch('/api/admin/orders/update-status.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': '<?= $_SESSION['csrf'] ?? '' ?>'
      },
      body: JSON.stringify({
        order_id: selectedOrder.id,
        status: 'verification_pending',
        notes: 'Order approved and sent to manufacturer for insurance verification'
      })
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || 'Failed to update order');
    }

    alert('Order approved and sent for verification');
    closeModal();
    loadOrders();

  } catch (error) {
    alert('Error: ' + error.message);
  }
}

// Filter change handler
document.getElementById('statusFilter')?.addEventListener('change', renderOrders);
</script>

<?php require __DIR__ . '/_footer.php'; ?>
