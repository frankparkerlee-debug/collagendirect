/**
 * Enhanced Admin Order Review
 * Adds AI approval score display and new workflow review actions
 */

// Review status badge configurations
const ADMIN_REVIEW_STATUS_BADGES = {
  'draft': '<span class="px-2 py-1 bg-slate-100 text-slate-700 rounded text-xs font-medium">Draft</span>',
  'pending_admin_review': '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-medium">Pending Review</span>',
  'needs_revision': '<span class="px-2 py-1 bg-orange-100 text-orange-800 rounded text-xs font-medium">Needs Revision</span>',
  'approved': '<span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-medium">Approved</span>',
  'rejected': '<span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-medium">Rejected</span>'
};

/**
 * Enhanced view order with AI assessment
 */
async function viewOrderEnhanced(orderId) {
  try {
    // Fetch order details
    const response = await fetch(`/api/admin/order.get.php?order_id=${encodeURIComponent(orderId)}`);
    const data = await response.json();

    if (!data.ok || !data.order) {
      alert('Failed to load order');
      return;
    }

    const order = data.order;

    // Fetch AI assessment if available
    let aiAssessment = null;
    if (order.patient_id) {
      try {
        const aiResponse = await fetch(`/api/portal/get_approval_score.php?patient_id=${encodeURIComponent(order.patient_id)}`);
        const aiData = await aiResponse.json();
        if (aiData.ok && aiData.has_score) {
          aiAssessment = aiData;
        }
      } catch (e) {
        console.warn('Failed to load AI assessment:', e);
      }
    }

    // Display modal
    displayEnhancedOrderModal(order, aiAssessment);

  } catch (error) {
    alert('Error loading order: ' + error.message);
  }
}

/**
 * Display enhanced order modal with AI assessment
 */
function displayEnhancedOrderModal(order, aiAssessment) {
  const modal = document.getElementById('orderModal');
  const modalTitle = document.getElementById('modalTitle');
  const modalContent = document.getElementById('modalContent');

  modalTitle.textContent = `Order Review - ${order.id.substring(0, 8)}`;

  // Build AI Assessment section
  let aiAssessmentHtml = '';
  if (aiAssessment) {
    const scoreColor = {
      'GREEN': 'bg-green-50 border-green-300',
      'YELLOW': 'bg-yellow-50 border-yellow-300',
      'RED': 'bg-red-50 border-red-300'
    }[aiAssessment.score] || 'bg-gray-50 border-gray-300';

    const scoreTextColor = {
      'GREEN': 'text-green-900',
      'YELLOW': 'text-yellow-900',
      'RED': 'text-red-900'
    }[aiAssessment.score] || 'text-gray-900';

    aiAssessmentHtml = `
      <div class="border-l-4 ${scoreColor} p-4 rounded">
        <h3 class="font-semibold ${scoreTextColor} mb-2">AI Approval Assessment</h3>
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm ${scoreTextColor}">Approval Score:</span>
          <span class="font-bold text-lg ${scoreTextColor}">${aiAssessment.score} (${aiAssessment.score_numeric}/100)</span>
        </div>
        ${aiAssessment.summary ? `
          <p class="text-sm ${scoreTextColor} mb-3">${esc(aiAssessment.summary)}</p>
        ` : ''}

        ${aiAssessment.missing_items && aiAssessment.missing_items.length > 0 ? `
          <div class="mb-3">
            <p class="text-sm font-semibold ${scoreTextColor}">Missing Items:</p>
            <ul class="list-disc list-inside text-sm ${scoreTextColor} ml-2">
              ${aiAssessment.missing_items.map(item => `<li>${esc(item)}</li>`).join('')}
            </ul>
          </div>
        ` : ''}

        ${aiAssessment.concerns && aiAssessment.concerns.length > 0 ? `
          <div class="mb-3">
            <p class="text-sm font-semibold ${scoreTextColor}">Concerns:</p>
            <ul class="list-disc list-inside text-sm ${scoreTextColor} ml-2">
              ${aiAssessment.concerns.map(concern => `<li>${esc(concern)}</li>`).join('')}
            </ul>
          </div>
        ` : ''}

        ${aiAssessment.recommendations && aiAssessment.recommendations.length > 0 ? `
          <div>
            <p class="text-sm font-semibold ${scoreTextColor}">Recommendations:</p>
            <ul class="list-disc list-inside text-sm ${scoreTextColor} ml-2">
              ${aiAssessment.recommendations.map(rec => `<li>${esc(rec)}</li>`).join('')}
            </ul>
          </div>
        ` : ''}
      </div>
    `;
  }

  // Build review status section
  let reviewStatusHtml = '';
  if (order.review_status) {
    reviewStatusHtml = `
      <div class="bg-blue-50 border border-blue-200 rounded p-4">
        <div class="flex items-center justify-between mb-2">
          <h3 class="font-semibold text-gray-900">Current Review Status</h3>
          ${ADMIN_REVIEW_STATUS_BADGES[order.review_status]}
        </div>
        ${order.review_notes ? `
          <div class="mt-2 text-sm text-gray-700">
            <strong>Previous Notes:</strong> ${esc(order.review_notes)}
          </div>
        ` : ''}
        ${order.reviewed_at ? `
          <div class="text-xs text-gray-600 mt-2">
            Last reviewed: ${new Date(order.reviewed_at).toLocaleString()}
          </div>
        ` : ''}
      </div>
    `;
  }

  // Build revision history section
  let revisionHistoryHtml = '';
  // TODO: Fetch and display revision history from order_revisions table

  modalContent.innerHTML = `
    <div class="space-y-6">
      <!-- AI Assessment -->
      ${aiAssessmentHtml}

      <!-- Review Status -->
      ${reviewStatusHtml}

      <!-- Patient Info -->
      <div>
        <h3 class="font-semibold text-gray-900 mb-3">Patient Information</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div><span class="text-gray-600">Order ID:</span> <code class="bg-gray-100 px-2 py-0.5 rounded">${esc(order.id.substring(0, 16))}</code></div>
          <div><span class="text-gray-600">Patient ID:</span> <code class="bg-gray-100 px-2 py-0.5 rounded">${esc(order.patient_id ? order.patient_id.substring(0, 16) : 'N/A')}</code></div>
        </div>
      </div>

      <!-- Order Details -->
      <div>
        <h3 class="font-semibold text-gray-900 mb-3">Order Details</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div><span class="text-gray-600">Product:</span> ${esc(order.product || 'N/A')}</div>
          <div><span class="text-gray-600">Payment:</span> ${esc(order.payment_type || 'Insurance')}</div>
          <div><span class="text-gray-600">Frequency:</span> ${esc(order.frequency || 'N/A')}</div>
          <div><span class="text-gray-600">Delivery:</span> ${esc(order.delivery_mode || 'Standard')}</div>
        </div>
      </div>

      <!-- Wound Details -->
      ${order.wound_location || order.wound_notes ? `
        <div>
          <h3 class="font-semibold text-gray-900 mb-3">Wound Information</h3>
          <div class="space-y-2 text-sm">
            ${order.wound_location ? `<div><span class="text-gray-600">Location:</span> ${esc(order.wound_location)}</div>` : ''}
            ${order.wound_laterality ? `<div><span class="text-gray-600">Laterality:</span> ${esc(order.wound_laterality)}</div>` : ''}
            ${order.wound_notes ? `<div><span class="text-gray-600">Notes:</span> ${esc(order.wound_notes)}</div>` : ''}
          </div>
        </div>
      ` : ''}

      <!-- Admin Feedback Form -->
      <div>
        <h3 class="font-semibold text-gray-900 mb-3">Admin Feedback (Optional)</h3>
        <textarea
          id="admin-review-notes"
          class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
          rows="3"
          placeholder="Enter feedback for the physician (e.g., specific items that need clarification)..."
        ></textarea>
      </div>
    </div>
  `;

  // Update modal actions
  const modalActions = modal.querySelector('.sticky.bottom-0');
  modalActions.innerHTML = `
    <button onclick="closeModal()" class="px-4 py-2 border rounded hover:bg-gray-50">Cancel</button>
    <button onclick="reviewOrderAction('reject')" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Reject</button>
    <button onclick="reviewOrderAction('request_changes')" class="px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700">Request Changes</button>
    <button onclick="reviewOrderAction('approve')" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Approve</button>
  `;

  // Store current order ID for review actions
  window.currentReviewOrderId = order.id;

  modal.style.display = 'flex';
}

/**
 * Perform review action (approve, request_changes, reject)
 */
async function reviewOrderAction(action) {
  const orderId = window.currentReviewOrderId;
  if (!orderId) {
    alert('No order selected');
    return;
  }

  const notes = document.getElementById('admin-review-notes')?.value || null;

  // Confirmation
  const actionLabels = {
    'approve': 'approve',
    'request_changes': 'request changes for',
    'reject': 'reject'
  };

  if (!confirm(`Are you sure you want to ${actionLabels[action]} this order?`)) {
    return;
  }

  try {
    const response = await fetch('/api/admin/order.review.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_id: orderId,
        action: action,
        notes: notes
      })
    });

    const result = await response.json();

    if (result.ok) {
      alert(`Order ${actionLabels[action]}d successfully!`);
      closeModal();
      // Reload orders list
      if (typeof loadOrders === 'function') {
        loadOrders();
      }
    } else {
      alert('Failed to review order: ' + (result.error || 'Unknown error'));
    }
  } catch (error) {
    alert('Error reviewing order: ' + error.message);
    console.error('Full error:', error);
  }
}

/**
 * Helper function to escape HTML
 */
function esc(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// Export functions
if (typeof window !== 'undefined') {
  window.viewOrderEnhanced = viewOrderEnhanced;
  window.reviewOrderAction = reviewOrderAction;
  window.ADMIN_REVIEW_STATUS_BADGES = ADMIN_REVIEW_STATUS_BADGES;
}
