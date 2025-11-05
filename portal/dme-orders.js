/**
 * DME Orders Management
 * Shows orders billed directly by practice (billed_by = 'practice_dme')
 * Independent from photo reviews - handles wholesale DME billing workflow
 */

let dmeOrders = [];
let filteredOrders = [];

/**
 * Load DME orders from API
 */
async function loadDMEOrders() {
  try {
    const response = await fetch('/api/index.php?action=orders.list');
    const data = await response.json();

    if (data.success && data.orders) {
      // Filter to only show practice_dme orders
      dmeOrders = data.orders.filter(order => order.billed_by === 'practice_dme');
      filteredOrders = [...dmeOrders];
      renderDMEOrders();
    } else {
      throw new Error(data.error || 'Failed to load orders');
    }
  } catch (error) {
    console.error('Error loading DME orders:', error);
    showToast('Failed to load DME orders', 'error');
  }
}

/**
 * Render DME orders UI
 */
function renderDMEOrders() {
  const container = document.getElementById('dme-orders-content');

  if (!container) return;

  const totalOrders = filteredOrders.length;
  const totalRevenue = filteredOrders.reduce((sum, o) => sum + parseFloat(o.product_price || 0), 0);

  const html = `
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-2xl font-bold text-slate-900 mb-2">DME Orders - Direct Billing</h2>
            <p class="text-slate-600">
              Orders billed directly by your practice using wholesale pricing. Independent from photo review billing.
            </p>
          </div>
          <button
            onclick="exportDirectBill()"
            class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition-colors text-sm font-medium flex items-center gap-2"
          >
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Export for Billing
          </button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
          <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4 border border-blue-200">
            <div class="text-sm font-semibold text-blue-900 mb-1">Total DME Orders</div>
            <div class="text-3xl font-bold text-blue-900">${totalOrders}</div>
          </div>
          <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-lg p-4 border border-emerald-200">
            <div class="text-sm font-semibold text-emerald-900 mb-1">Total Wholesale Value</div>
            <div class="text-3xl font-bold text-emerald-900">$${totalRevenue.toFixed(2)}</div>
          </div>
          <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-lg p-4 border border-amber-200">
            <div class="text-sm font-semibold text-amber-900 mb-1">Pending Submission</div>
            <div class="text-3xl font-bold text-amber-900">${filteredOrders.filter(o => o.status === 'approved' || o.status === 'shipped').length}</div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-4 mb-6">
        <div class="flex flex-wrap gap-4">
          <div class="flex-1 min-w-64">
            <input
              type="text"
              id="search-input"
              placeholder="Search patient name, order ID..."
              class="w-full px-4 py-2 border border-slate-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              oninput="filterOrders()"
            >
          </div>
          <select
            id="status-filter"
            class="px-4 py-2 border border-slate-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            onchange="filterOrders()"
          >
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="shipped">Shipped</option>
            <option value="delivered">Delivered</option>
          </select>
          <button
            onclick="resetFilters()"
            class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md hover:bg-slate-300 transition-colors text-sm font-medium"
          >
            Reset Filters
          </button>
        </div>
      </div>

      <!-- Orders Table -->
      <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
        ${filteredOrders.length === 0 ? `
          <div class="text-center py-12">
            <svg class="w-16 h-16 mx-auto mb-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-slate-600 text-lg font-medium mb-2">No DME Orders Found</p>
            <p class="text-slate-500 text-sm">Direct bill orders will appear here when configured in Billing Settings.</p>
          </div>
        ` : `
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Order ID</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Patient</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Product</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Status</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Date</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Price</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Documents</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200">
                ${filteredOrders.map(order => {
                  const statusColors = {
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'approved': 'bg-blue-100 text-blue-800',
                    'shipped': 'bg-purple-100 text-purple-800',
                    'delivered': 'bg-green-100 text-green-800',
                    'cancelled': 'bg-red-100 text-red-800'
                  };
                  const statusColor = statusColors[order.status] || 'bg-slate-100 text-slate-800';

                  const orderDate = order.created_at ? new Date(order.created_at).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                  }) : 'N/A';

                  return `
                    <tr class="hover:bg-slate-50 transition-colors">
                      <td class="px-4 py-3">
                        <span class="font-mono text-sm text-slate-900">${escapeHtml(order.id.substring(0, 8))}</span>
                      </td>
                      <td class="px-4 py-3">
                        <div class="font-medium text-slate-900">${escapeHtml(order.patient_name || 'Unknown')}</div>
                      </td>
                      <td class="px-4 py-3">
                        <div class="text-sm text-slate-900">${escapeHtml(order.product || 'N/A')}</div>
                      </td>
                      <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColor}">
                          ${escapeHtml(order.status)}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-sm text-slate-600">${orderDate}</td>
                      <td class="px-4 py-3 text-sm font-medium text-slate-900">$${parseFloat(order.product_price || 0).toFixed(2)}</td>
                      <td class="px-4 py-3">
                        <button
                          onclick="viewOrderDocuments('${escapeHtml(order.id)}')"
                          class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center gap-1"
                        >
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                          </svg>
                          View
                        </button>
                      </td>
                    </tr>
                  `;
                }).join('')}
              </tbody>
            </table>
          </div>
        `}
      </div>

      <!-- Help Section -->
      <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h4 class="text-sm font-semibold text-blue-900 mb-2 flex items-center gap-2">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
          </svg>
          About DME Direct Billing
        </h4>
        <ul class="text-sm text-blue-800 space-y-1 ml-6 list-disc">
          <li><strong>Direct Bill Orders:</strong> Orders where your practice bills the insurance company directly using your DME license</li>
          <li><strong>Wholesale Pricing:</strong> These orders use wholesale pricing instead of MD-DME retail pricing</li>
          <li><strong>Auto-Approval:</strong> Direct bill orders are auto-approved and skip CollagenDirect admin review</li>
          <li><strong>Export for Billing:</strong> Download comprehensive CSV with all fields needed for HCFA 1500 claim submission</li>
          <li><strong>Configure Routing:</strong> Visit <a href="?page=billing-settings" class="underline font-semibold">Billing Settings</a> to choose which insurers route to direct billing</li>
        </ul>
      </div>
    </div>
  `;

  container.innerHTML = html;
}

/**
 * Filter orders based on search and status
 */
function filterOrders() {
  const searchInput = document.getElementById('search-input')?.value.toLowerCase() || '';
  const statusFilter = document.getElementById('status-filter')?.value || '';

  filteredOrders = dmeOrders.filter(order => {
    const matchesSearch = !searchInput ||
      order.patient_name?.toLowerCase().includes(searchInput) ||
      order.id?.toLowerCase().includes(searchInput) ||
      order.product?.toLowerCase().includes(searchInput);

    const matchesStatus = !statusFilter || order.status === statusFilter;

    return matchesSearch && matchesStatus;
  });

  renderDMEOrders();
}

/**
 * Reset all filters
 */
function resetFilters() {
  const searchInput = document.getElementById('search-input');
  const statusFilter = document.getElementById('status-filter');

  if (searchInput) searchInput.value = '';
  if (statusFilter) statusFilter.value = '';

  filterOrders();
}

/**
 * Export direct bill orders
 */
function exportDirectBill() {
  // Get date range (default to current month)
  const now = new Date();
  const startDate = new Date(now.getFullYear(), now.getMonth(), 1);
  const endDate = now;

  const startStr = startDate.toISOString().substring(0, 10); // YYYY-MM-DD
  const endStr = endDate.toISOString().substring(0, 10); // YYYY-MM-DD

  // Create modal for date range selection
  const modal = document.createElement('div');
  modal.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
  `;

  modal.innerHTML = `
    <div style="background: white; padding: 2rem; border-radius: 8px; max-width: 500px; width: 90%;">
      <h3 style="margin-top: 0;">Export Direct Bill Orders</h3>
      <p style="color: #64748b; font-size: 0.875rem;">
        Select date range for direct bill orders export. CSV includes all fields for HCFA 1500 billing.
      </p>

      <div style="margin-bottom: 1rem; margin-top: 1.5rem;">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Start Date</label>
        <input type="date" id="export-start-date" value="${startStr}" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e0; border-radius: 4px;">
      </div>

      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">End Date</label>
        <input type="date" id="export-end-date" value="${endStr}" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e0; border-radius: 4px;">
      </div>

      <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
        <button onclick="this.closest('div').parentElement.remove()" style="padding: 0.5rem 1rem; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer;">
          Cancel
        </button>
        <button onclick="confirmDirectBillExport()" style="padding: 0.5rem 1rem; background: #059669; color: white; border: none; border-radius: 4px; cursor: pointer;">
          Export CSV
        </button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);
}

/**
 * Confirm and execute direct bill export
 */
function confirmDirectBillExport() {
  const startDate = document.getElementById('export-start-date').value;
  const endDate = document.getElementById('export-end-date').value;

  if (!startDate || !endDate) {
    alert('Please select both start and end dates.');
    return;
  }

  if (new Date(startDate) > new Date(endDate)) {
    alert('Start date must be before end date.');
    return;
  }

  // Close modal
  document.querySelector('div[style*="z-index: 10000"]').remove();

  // Open export URL
  window.location.href = `/api/export-direct-bill.php?start_date=${startDate}&end_date=${endDate}&billing_route=practice_dme`;
}

/**
 * View order documents
 */
async function viewOrderDocuments(orderId) {
  try {
    const response = await fetch(`/api/index.php?action=order.get&order_id=${orderId}`);
    const data = await response.json();

    if (!data.success || !data.order) {
      throw new Error('Failed to load order details');
    }

    const order = data.order;
    const documents = [];

    // Collect all document paths
    if (order.physician_order_path) {
      documents.push({
        name: 'Physician Order',
        path: order.physician_order_path,
        type: 'prescription'
      });
    }

    if (order.face_to_face_path) {
      documents.push({
        name: 'Face-to-Face Documentation',
        path: order.face_to_face_path,
        type: 'documentation'
      });
    }

    if (order.medical_records_path) {
      documents.push({
        name: 'Medical Records',
        path: order.medical_records_path,
        type: 'documentation'
      });
    }

    if (order.insurance_card_front_path) {
      documents.push({
        name: 'Insurance Card (Front)',
        path: order.insurance_card_front_path,
        type: 'insurance'
      });
    }

    if (order.insurance_card_back_path) {
      documents.push({
        name: 'Insurance Card (Back)',
        path: order.insurance_card_back_path,
        type: 'insurance'
      });
    }

    // Show documents modal
    showDocumentsModal(orderId, order.patient_name, documents);

  } catch (error) {
    console.error('Error loading order documents:', error);
    showToast('Failed to load order documents', 'error');
  }
}

/**
 * Show documents modal
 */
function showDocumentsModal(orderId, patientName, documents) {
  const modal = document.createElement('div');
  modal.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
  `;

  modal.innerHTML = `
    <div style="background: white; padding: 2rem; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
      <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1.5rem;">
        <div>
          <h3 style="margin: 0 0 0.25rem 0;">Order Documents</h3>
          <p style="color: #64748b; font-size: 0.875rem; margin: 0;">
            ${escapeHtml(patientName)} - Order ${escapeHtml(orderId.substring(0, 8))}
          </p>
        </div>
        <button onclick="this.closest('div').parentElement.remove()" style="padding: 0.5rem; background: none; border: none; cursor: pointer; color: #64748b;">
          <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      ${documents.length === 0 ? `
        <div class="text-center py-8">
          <svg class="w-12 h-12 mx-auto mb-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          <p style="color: #64748b;">No documents uploaded for this order</p>
        </div>
      ` : `
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
          ${documents.map(doc => `
            <a
              href="${escapeHtml(doc.path)}"
              target="_blank"
              style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; transition: all 0.2s;"
              onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1';"
              onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0';"
            >
              <div style="width: 40px; height: 40px; background: #3b82f6; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg style="width: 20px; height: 20px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
              </div>
              <div style="flex: 1; min-width: 0;">
                <div style="font-weight: 500; color: #1e293b; margin-bottom: 0.25rem;">${escapeHtml(doc.name)}</div>
                <div style="font-size: 0.75rem; color: #64748b; text-transform: capitalize;">${escapeHtml(doc.type)}</div>
              </div>
              <svg style="width: 20px; height: 20px; color: #64748b; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
              </svg>
            </a>
          `).join('')}
        </div>
      `}

      <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0;">
        <button
          onclick="this.closest('div').parentElement.remove()"
          style="width: 100%; padding: 0.75rem; background: #e2e8f0; color: #1e293b; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;"
        >
          Close
        </button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 ${
    type === 'success' ? 'bg-green-600' :
    type === 'error' ? 'bg-red-600' :
    'bg-blue-600'
  }`;
  toast.textContent = message;
  document.body.appendChild(toast);

  setTimeout(() => {
    toast.remove();
  }, 3000);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Auto-load on page load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', loadDMEOrders);
} else {
  loadDMEOrders();
}
