/**
 * Order Workflow Enhancement
 * Adds AI suggestions and order editing capabilities to the physician portal
 */

// Status badge configurations
const REVIEW_STATUS_BADGES = {
  'draft': '<span class="px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-medium">Draft</span>',
  'pending_admin_review': '<span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">Pending Review</span>',
  'needs_revision': '<span class="px-3 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-medium">⚠️ Revision Requested</span>',
  'approved': '<span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">✓ Approved</span>',
  'rejected': '<span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">✗ Rejected</span>'
};

/**
 * Enhanced order details viewer with AI suggestions and edit capabilities
 */
function viewOrderDetailsEnhanced(order) {
  const dlg = document.getElementById('dlg-order-details');
  const content = document.getElementById('order-details-content');

  // Check if order is editable
  const isEditable = order.review_status && ['draft', 'needs_revision'].includes(order.review_status);
  const isLocked = order.locked_at !== null;

  // Build AI suggestions section if available
  let aiSuggestionsHtml = '';
  if (order.ai_suggestions && !isLocked) {
    const suggestions = typeof order.ai_suggestions === 'string'
      ? JSON.parse(order.ai_suggestions)
      : order.ai_suggestions;

    if (suggestions && suggestions.suggestions && suggestions.suggestions.length > 0) {
      aiSuggestionsHtml = buildAISuggestionsSection(order.id, suggestions);
    }
  }

  // Build review status section
  let reviewStatusHtml = '';
  if (order.review_status) {
    reviewStatusHtml = `
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center justify-between mb-2">
          <h5 class="font-semibold text-sm">Review Status</h5>
          ${REVIEW_STATUS_BADGES[order.review_status] || REVIEW_STATUS_BADGES['pending_admin_review']}
        </div>
        ${order.review_status === 'needs_revision' && order.review_notes ? `
          <div class="mt-3 p-3 bg-orange-50 border border-orange-200 rounded">
            <p class="font-semibold text-sm text-orange-900 mb-1">Admin Feedback:</p>
            <p class="text-sm text-orange-800">${esc(order.review_notes)}</p>
          </div>
        ` : ''}
        ${order.reviewed_at ? `
          <div class="text-xs text-slate-600 mt-2">
            Reviewed on: ${fmt(order.reviewed_at)}
          </div>
        ` : ''}
      </div>
    `;
  }

  // Build edit button if order is editable
  let editButtonHtml = '';
  if (isEditable && !isLocked) {
    editButtonHtml = `
      <button
        type="button"
        class="btn btn-primary"
        onclick="openOrderEditDialog('${esc(order.id)}')"
      >
        Edit Order
      </button>
    `;
  }

  content.innerHTML = `
    <div class="grid gap-6">
      <!-- Review Status (if applicable) -->
      ${reviewStatusHtml}

      <!-- AI Suggestions (if applicable) -->
      ${aiSuggestionsHtml}

      <!-- Order Header -->
      <div class="pb-4 border-b">
        <div class="flex items-center justify-between mb-2">
          <h4 class="text-lg font-semibold">${esc(order.product || 'Wound Care Product')}</h4>
          <div class="flex gap-2">
            ${order.status ? `<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-medium">${esc(order.status)}</span>` : ''}
            ${order.review_status ? REVIEW_STATUS_BADGES[order.review_status] : ''}
          </div>
        </div>
        <div class="text-sm text-slate-600">
          Order ID: ${esc(order.id || 'N/A')}
        </div>
      </div>

      <!-- Product & Pricing -->
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <h5 class="font-semibold text-sm mb-2">Product Information</h5>
          <div class="space-y-1 text-sm">
            <div><span class="text-slate-600">Product:</span> ${esc(order.product || 'N/A')}</div>
            <div><span class="text-slate-600">Price:</span> $${order.product_price || '0.00'}</div>
            <div><span class="text-slate-600">Payment:</span> ${esc(order.payment_type || 'Insurance')}</div>
            <div><span class="text-slate-600">Delivery:</span> ${esc(order.delivery_mode || 'Ship to patient')}</div>
            ${order.frequency ? `<div><span class="text-slate-600">Frequency:</span> ${esc(order.frequency)}</div>` : ''}
          </div>
        </div>

        <div>
          <h5 class="font-semibold text-sm mb-2">Order Status</h5>
          <div class="space-y-1 text-sm">
            <div><span class="text-slate-600">Shipments Remaining:</span> ${order.shipments_remaining || 0}</div>
            <div><span class="text-slate-600">Created:</span> ${fmt(order.created_at)}</div>
            ${order.expires_at ? `<div><span class="text-slate-600">Expires:</span> ${fmt(order.expires_at)}</div>` : ''}
          </div>
        </div>
      </div>

      <!-- Shipping Information -->
      ${order.shipping_name ? `
        <div>
          <h5 class="font-semibold text-sm mb-2">Shipping Address</h5>
          <div class="text-sm text-slate-700">
            ${esc(order.shipping_name)}<br>
            ${esc(order.shipping_address || '')}<br>
            ${esc(order.shipping_city || '')}, ${esc(order.shipping_state || '')} ${esc(order.shipping_zip || '')}<br>
            ${order.shipping_phone ? `Phone: ${esc(order.shipping_phone)}` : ''}
          </div>
        </div>
      ` : ''}

      <!-- Wound Information -->
      ${order.wound_location || order.wound_laterality || order.wound_notes ? `
        <div>
          <h5 class="font-semibold text-sm mb-2">Wound Details</h5>
          <div class="space-y-1 text-sm">
            ${order.wound_location ? `<div><span class="text-slate-600">Location:</span> ${esc(order.wound_location)}</div>` : ''}
            ${order.wound_laterality ? `<div><span class="text-slate-600">Laterality:</span> ${esc(order.wound_laterality)}</div>` : ''}
            ${order.wound_notes ? `<div><span class="text-slate-600">Notes:</span> ${esc(order.wound_notes)}</div>` : ''}
          </div>
        </div>
      ` : ''}

      <!-- Provider Signature -->
      ${order.e_sign_name ? `
        <div>
          <h5 class="font-semibold text-sm mb-2">Provider Signature</h5>
          <div class="text-sm">
            <div>${esc(order.e_sign_name)} - ${esc(order.e_sign_title || '')}</div>
            ${order.e_sign_at ? `<div class="text-slate-600 text-xs mt-1">Signed: ${fmt(order.e_sign_at)}</div>` : ''}
          </div>
        </div>
      ` : ''}

      <!-- Clinical Notes -->
      ${order.rx_note_path ? `
        <div>
          <h5 class="font-semibold text-sm mb-2">Clinical Notes</h5>
          <div class="text-sm">
            <a href="?action=file.dl&order_id=${esc(order.id)}" target="_blank" class="text-blue-600 hover:underline">
              View Clinical Note
            </a>
          </div>
        </div>
      ` : ''}

      <!-- Edit Button -->
      ${editButtonHtml ? `
        <div class="pt-4 border-t flex justify-end gap-2">
          ${editButtonHtml}
        </div>
      ` : ''}
    </div>
  `;

  dlg.showModal();
}

/**
 * Build AI suggestions section HTML
 */
function buildAISuggestionsSection(orderId, suggestions) {
  const suggestionsList = suggestions.suggestions || [];
  const approvalScore = suggestions.approval_score || {};

  if (suggestionsList.length === 0) return '';

  const scoreColor = {
    'GREEN': 'bg-green-50 border-green-200 text-green-900',
    'YELLOW': 'bg-yellow-50 border-yellow-200 text-yellow-900',
    'RED': 'bg-red-50 border-red-200 text-red-900'
  }[approvalScore.score] || 'bg-blue-50 border-blue-200 text-blue-900';

  // Separate suggestions by category
  const demographicSuggestions = suggestionsList.filter(s => s.category === 'demographic');
  const orderSuggestions = suggestionsList.filter(s => s.category === 'order');

  // Build suggestion card HTML
  const buildSuggestionCard = (s) => {
    const priorityBadge = {
      'high': '<span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">High</span>',
      'medium': '<span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded text-xs">Medium</span>',
      'low': '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">Low</span>'
    }[s.priority] || '';

    const categoryBadge = s.category === 'demographic'
      ? '<span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-xs">Profile</span>'
      : '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">Order</span>';

    const locationHint = s.edit_location
      ? `<div class="text-xs text-slate-500 mt-1 italic">📍 ${esc(s.edit_location)}</div>`
      : '';

    return `
      <div class="p-3 bg-white border rounded">
        <div class="flex items-start justify-between mb-1">
          <span class="font-semibold text-sm">${esc(s.field_label || s.field)}</span>
          <div class="flex gap-1">
            ${categoryBadge}
            ${priorityBadge}
          </div>
        </div>
        <div class="text-sm text-slate-600 mb-1">
          Current: ${esc(s.current_value || 'Not provided')}
        </div>
        <div class="text-sm text-slate-900 mb-1">
          Suggested: <span class="font-medium">${esc(s.suggested_value)}</span>
        </div>
        <div class="text-xs text-slate-600">
          ${esc(s.reason)}
        </div>
        ${locationHint}
      </div>
    `;
  };

  // Build demographic section
  const demographicSection = demographicSuggestions.length > 0 ? `
    <div class="mb-3">
      <h6 class="font-semibold text-sm mb-2 flex items-center gap-2">
        <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-xs">Patient Profile Issues</span>
        ${approvalScore.demographic_issues_summary ? `<span class="text-xs font-normal text-slate-600">${esc(approvalScore.demographic_issues_summary)}</span>` : ''}
      </h6>
      <div class="grid gap-2">
        ${demographicSuggestions.map(buildSuggestionCard).join('')}
      </div>
    </div>
  ` : '';

  // Build order section
  const orderSection = orderSuggestions.length > 0 ? `
    <div class="mb-3">
      <h6 class="font-semibold text-sm mb-2 flex items-center gap-2">
        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">Order/Clinical Issues</span>
        ${approvalScore.order_issues_summary ? `<span class="text-xs font-normal text-slate-600">${esc(approvalScore.order_issues_summary)}</span>` : ''}
      </h6>
      <div class="grid gap-2">
        ${orderSuggestions.map(buildSuggestionCard).join('')}
      </div>
    </div>
  ` : '';

  return `
    <div class="${scoreColor} border rounded-lg p-4">
      <div class="flex items-center justify-between mb-3">
        <h5 class="font-semibold text-sm">AI Suggestions</h5>
        <span class="text-xs font-medium">Score: ${approvalScore.score_numeric || 0}/100</span>
      </div>
      ${approvalScore.summary ? `
        <p class="text-sm mb-3">${esc(approvalScore.summary)}</p>
      ` : ''}

      ${demographicSection}
      ${orderSection}

      <button
        type="button"
        class="btn btn-sm btn-primary"
        onclick="acceptAISuggestions('${esc(orderId)}')"
      >
        Accept All Suggestions
      </button>
    </div>
  `;
}

/**
 * Accept AI suggestions and update order
 */
async function acceptAISuggestions(orderId) {
  if (!confirm('Accept all AI suggestions? This will update the order with all recommended changes.')) {
    return;
  }

  try {
    const response = await fetch('/api/portal/order.update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_id: orderId,
        accept_ai_suggestions: true,
        reason: 'Accepted AI suggestions'
      })
    });

    const result = await response.json();

    if (result.ok) {
      alert('Order updated with AI suggestions successfully!');
      document.getElementById('dlg-order-details').close();
      // Refresh the current view
      if (typeof loadPage === 'function') {
        loadPage(currentPage);
      }
    } else {
      alert('Failed to update order: ' + (result.error || 'Unknown error'));
    }
  } catch (error) {
    alert('Error updating order: ' + error.message);
    console.error('Full error:', error);
  }
}

// Note: openOrderEditDialog is defined in order-edit-dialog.html
// It will be exported to window.openOrderEditDialog by that file

// Export functions for use in main portal
if (typeof window !== 'undefined') {
  window.viewOrderDetailsEnhanced = viewOrderDetailsEnhanced;
  window.acceptAISuggestions = acceptAISuggestions;
  window.REVIEW_STATUS_BADGES = REVIEW_STATUS_BADGES;
}
