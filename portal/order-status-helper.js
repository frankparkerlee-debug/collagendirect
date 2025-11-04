/**
 * Order Status Helper
 * Centralized logic for determining order status and editability
 */

/**
 * Get unified display status for an order
 * @param {Object} order - Order object with status, review_status, created_at, locked_at
 * @returns {Object} { status: string, editable: boolean, color: string, description: string }
 */
function getOrderDisplayStatus(order) {
  // Check if expired (30 days old and not approved/accepted)
  const createdDate = new Date(order.created_at);
  const daysSinceCreated = (Date.now() - createdDate) / (1000 * 60 * 60 * 24);

  if (daysSinceCreated > 30 && order.review_status !== 'approved' && order.status !== 'approved' && order.status !== 'active') {
    return {
      status: 'Expired',
      editable: false,
      color: 'slate',
      bgColor: 'bg-slate-50',
      textColor: 'text-slate-700',
      borderColor: 'border-l-slate-400',
      badgeBg: 'bg-slate-100',
      badgeText: 'text-slate-700',
      description: 'Order has expired and cannot be modified'
    };
  }

  // Check review_status (new workflow)
  if (order.review_status) {
    switch(order.review_status) {
      case 'draft':
        return {
          status: 'Draft',
          editable: true,
          color: 'slate',
          bgColor: 'bg-slate-50',
          textColor: 'text-slate-700',
          borderColor: 'border-l-slate-500',
          badgeBg: 'bg-slate-100',
          badgeText: 'text-slate-700',
          description: 'Order is being prepared'
        };

      case 'pending_admin_review':
        return {
          status: 'Pending',
          editable: !order.locked_at,
          color: 'blue',
          bgColor: 'bg-blue-50',
          textColor: 'text-blue-700',
          borderColor: 'border-l-blue-500',
          badgeBg: 'bg-blue-100',
          badgeText: 'text-blue-800',
          description: 'Awaiting manufacturer review'
        };

      case 'needs_revision':
        return {
          status: 'Needs Revision',
          editable: true,
          color: 'orange',
          bgColor: 'bg-orange-50',
          textColor: 'text-orange-700',
          borderColor: 'border-l-orange-500',
          badgeBg: 'bg-orange-100',
          badgeText: 'text-orange-800',
          description: 'Manufacturer requested changes'
        };

      case 'approved':
        return {
          status: 'Accepted',
          editable: false,
          color: 'green',
          bgColor: 'bg-green-50',
          textColor: 'text-green-700',
          borderColor: 'border-l-green-500',
          badgeBg: 'bg-green-100',
          badgeText: 'text-green-800',
          description: 'Order approved and will be billed'
        };

      case 'rejected':
        return {
          status: 'Rejected',
          editable: false,
          color: 'red',
          bgColor: 'bg-red-50',
          textColor: 'text-red-700',
          borderColor: 'border-l-red-500',
          badgeBg: 'bg-red-100',
          badgeText: 'text-red-800',
          description: 'Order was rejected'
        };
    }
  }

  // Fallback to old status field for backward compatibility
  if (order.status) {
    if (order.status === 'approved' || order.status === 'active') {
      return {
        status: 'Accepted',
        editable: false,
        color: 'green',
        bgColor: 'bg-green-50',
        textColor: 'text-green-700',
        borderColor: 'border-l-green-500',
        badgeBg: 'bg-green-100',
        badgeText: 'text-green-800',
        description: 'Order approved and active'
      };
    }

    if (order.status === 'stopped' || order.status === 'completed') {
      return {
        status: 'Completed',
        editable: false,
        color: 'slate',
        bgColor: 'bg-slate-50',
        textColor: 'text-slate-700',
        borderColor: 'border-l-slate-400',
        badgeBg: 'bg-slate-100',
        badgeText: 'text-slate-700',
        description: 'Order is completed'
      };
    }
  }

  // Default: Pending
  return {
    status: 'Pending',
    editable: true,
    color: 'blue',
    bgColor: 'bg-blue-50',
    textColor: 'text-blue-700',
    borderColor: 'border-l-blue-500',
    badgeBg: 'bg-blue-100',
    badgeText: 'text-blue-800',
    description: 'Order pending review'
  };
}

/**
 * Check if an order can be edited
 * @param {Object} order - Order object
 * @returns {boolean} True if editable
 */
function canEditOrder(order) {
  const displayStatus = getOrderDisplayStatus(order);
  return displayStatus.editable;
}

/**
 * Get status badge HTML
 * @param {Object} order - Order object
 * @returns {string} HTML for status badge
 */
function getStatusBadgeHTML(order) {
  const s = getOrderDisplayStatus(order);
  return `<span class="px-2 py-0.5 ${s.badgeBg} ${s.badgeText} rounded-full text-xs font-medium" title="${esc(s.description)}">${esc(s.status)}</span>`;
}

/**
 * Render order card with proper status
 * @param {Object} order - Order object
 * @param {number} index - Card index for coloring
 * @returns {string} HTML for order card
 */
function renderOrderCard(order, index) {
  const s = getOrderDisplayStatus(order);

  return `
    <div class="${s.bgColor} border-l-4 ${s.borderColor} p-3 rounded">
      <div class="flex items-center justify-between">
        <div class="flex-1">
          <div class="flex items-center gap-2 mb-1">
            <span class="font-medium text-sm ${s.textColor}">${esc(order.product || 'Wound Care Product')}</span>
            ${getStatusBadgeHTML(order)}
          </div>
          <div class="text-xs text-slate-600">
            ${fmt(order.created_at)} • ${esc(order.frequency || 'Weekly')}
          </div>
          ${order.review_notes ? `
            <div class="text-xs ${s.textColor} mt-1">
              <span class="font-medium">Admin note:</span> ${esc(order.review_notes)}
            </div>
          ` : ''}
        </div>
        <div class="flex gap-2">
          <button class="btn btn-sm" onclick='viewOrderDetailsEnhanced(${JSON.stringify(order)})'>
            View
          </button>
          ${s.editable ? `
            <button class="btn btn-sm btn-primary" onclick='openOrderEditDialog("${esc(order.id)}")'>
              Edit
            </button>
          ` : ''}
        </div>
      </div>
    </div>
  `;
}

// Export functions
if (typeof window !== 'undefined') {
  window.getOrderDisplayStatus = getOrderDisplayStatus;
  window.canEditOrder = canEditOrder;
  window.getStatusBadgeHTML = getStatusBadgeHTML;
  window.renderOrderCard = renderOrderCard;
}
