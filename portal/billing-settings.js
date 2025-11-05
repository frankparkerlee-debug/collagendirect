/**
 * Billing Settings - Insurance Routing Configuration
 * Allows hybrid DME practices to configure which insurers route to direct billing vs CollagenDirect
 */

// Top 15 Southern US Insurance Companies + Other
const SOUTHERN_INSURERS = [
  'UnitedHealthcare (UHC)',
  'BlueCross BlueShield',
  'Aetna',
  'Humana',
  'Cigna',
  'Medicare',
  'Anthem',
  'Centene / Ambetter',
  'Medicaid',
  'Florida Blue',
  'Molina Healthcare',
  'WellCare',
  'Oscar Health',
  'TriCare',
  'Bright Health',
  'Other / Unlisted'
];

let currentRoutes = [];
let defaultRoute = 'collagen_direct';

/**
 * Load billing routes from API
 */
async function loadBillingRoutes() {
  try {
    const [routesResp, defaultResp] = await Promise.all([
      fetch('/api/billing-routes.php?action=routes.get'),
      fetch('/api/billing-routes.php?action=default_route.get')
    ]);

    const routesData = await routesResp.json();
    const defaultData = await defaultResp.json();

    if (routesData.success) {
      currentRoutes = routesData.routes || [];
    }

    if (defaultData.success) {
      defaultRoute = defaultData.default_route || 'collagen_direct';
    }

    renderBillingSettings();
  } catch (error) {
    console.error('Error loading billing routes:', error);
    showToast('Failed to load billing settings', 'error');
  }
}

/**
 * Render billing settings UI
 */
function renderBillingSettings() {
  const container = document.getElementById('billing-settings-content');

  if (!container) return;

  // Build route map for quick lookup
  const routeMap = {};
  currentRoutes.forEach(r => {
    routeMap[r.insurer_name] = r.billing_route;
  });

  const html = `
    <div class="max-w-4xl mx-auto">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
        <h2 class="text-2xl font-bold text-slate-900 mb-2">Billing Route Configuration</h2>
        <p class="text-slate-600">
          Configure which insurance companies route to direct billing (your DME license)
          vs CollagenDirect billing (MD-DME model).
        </p>
      </div>

      <!-- Default Route -->
      <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
          <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
          </svg>
          Default Billing Route
        </h3>
        <p class="text-sm text-slate-600 mb-4">
          Used for insurance companies not configured below.
        </p>

        <div class="flex gap-4">
          <label class="flex items-center gap-2 cursor-pointer">
            <input
              type="radio"
              name="default_route"
              value="collagen_direct"
              ${defaultRoute === 'collagen_direct' ? 'checked' : ''}
              onchange="updateDefaultRoute('collagen_direct')"
              class="w-4 h-4 text-blue-600"
            >
            <span class="text-sm font-medium text-slate-700">CollagenDirect (MD-DME)</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer">
            <input
              type="radio"
              name="default_route"
              value="practice_dme"
              ${defaultRoute === 'practice_dme' ? 'checked' : ''}
              onchange="updateDefaultRoute('practice_dme')"
              class="w-4 h-4 text-blue-600"
            >
            <span class="text-sm font-medium text-slate-700">My Practice (Direct Bill)</span>
          </label>
        </div>
      </div>

      <!-- Insurance Company Routes -->
      <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
          <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          Insurance Company Routing
        </h3>
        <p class="text-sm text-slate-600 mb-6">
          Select billing route for each insurance company. Leave as "Use Default" to use the default route above.
        </p>

        <div class="space-y-3">
          ${SOUTHERN_INSURERS.map(insurer => {
            const currentSetting = routeMap[insurer] || 'default';
            return `
              <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg border border-slate-200 hover:border-blue-300 transition-colors">
                <div class="flex-1">
                  <span class="font-medium text-slate-900">${escapeHtml(insurer)}</span>
                </div>

                <div class="flex gap-2">
                  <select
                    class="px-3 py-1.5 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    onchange="updateInsurerRoute('${escapeHtml(insurer)}', this.value)"
                    data-insurer="${escapeHtml(insurer)}"
                  >
                    <option value="default" ${currentSetting === 'default' ? 'selected' : ''}>
                      Use Default (${defaultRoute === 'collagen_direct' ? 'CollagenDirect' : 'Practice DME'})
                    </option>
                    <option value="collagen_direct" ${currentSetting === 'collagen_direct' ? 'selected' : ''}>
                      CollagenDirect (MD-DME)
                    </option>
                    <option value="practice_dme" ${currentSetting === 'practice_dme' ? 'selected' : ''}>
                      My Practice (Direct Bill)
                    </option>
                  </select>

                  ${currentSetting !== 'default' ? `
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium ${
                      currentSetting === 'collagen_direct'
                        ? 'bg-blue-100 text-blue-800'
                        : 'bg-green-100 text-green-800'
                    }">
                      ${currentSetting === 'collagen_direct' ? 'CD' : 'DME'}
                    </span>
                  ` : ''}
                </div>
              </div>
            `;
          }).join('')}
        </div>

        <!-- Quick Actions -->
        <div class="mt-6 pt-6 border-t border-slate-200">
          <h4 class="text-sm font-semibold text-slate-900 mb-3">Quick Actions</h4>
          <div class="flex gap-3">
            <button
              onclick="setAllRoutes('collagen_direct')"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm font-medium"
            >
              Set All to CollagenDirect
            </button>
            <button
              onclick="setAllRoutes('practice_dme')"
              class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm font-medium"
            >
              Set All to Practice DME
            </button>
            <button
              onclick="resetAllRoutes()"
              class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md hover:bg-slate-300 transition-colors text-sm font-medium"
            >
              Reset All to Default
            </button>
          </div>
        </div>
      </div>

      <!-- Help Section -->
      <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h4 class="text-sm font-semibold text-blue-900 mb-2 flex items-center gap-2">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
          </svg>
          How Billing Routes Work
        </h4>
        <ul class="text-sm text-blue-800 space-y-1 ml-6 list-disc">
          <li><strong>CollagenDirect (MD-DME):</strong> Order requires admin review, uses MD-DME pricing, ships from CollagenDirect</li>
          <li><strong>My Practice (Direct Bill):</strong> Order auto-approved, uses wholesale pricing, you handle billing and insurance</li>
          <li><strong>Use Default:</strong> Applies your default setting (configured above) for this insurance company</li>
          <li>When creating an order, the system will automatically determine the billing route based on the patient's insurance</li>
        </ul>
      </div>
    </div>
  `;

  container.innerHTML = html;
}

/**
 * Update default billing route
 */
async function updateDefaultRoute(route) {
  try {
    const formData = new FormData();
    formData.append('action', 'default_route.set');
    formData.append('default_route', route);

    const response = await fetch('/api/billing-routes.php', {
      method: 'POST',
      body: formData
    });

    const data = await response.json();

    if (data.success) {
      defaultRoute = route;
      showToast('Default billing route updated', 'success');
      renderBillingSettings(); // Re-render to update "Use Default" labels
    } else {
      throw new Error(data.error || 'Failed to update default route');
    }
  } catch (error) {
    console.error('Error updating default route:', error);
    showToast('Failed to update default route', 'error');
  }
}

/**
 * Update individual insurer route
 */
async function updateInsurerRoute(insurerName, route) {
  try {
    if (route === 'default') {
      // Delete the specific route (fall back to default)
      const formData = new FormData();
      formData.append('action', 'routes.delete');
      formData.append('insurer_name', insurerName);

      const response = await fetch('/api/billing-routes.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        // Remove from currentRoutes
        currentRoutes = currentRoutes.filter(r => r.insurer_name !== insurerName);
        showToast(`${insurerName} reset to default`, 'success');
      } else {
        throw new Error(data.error || 'Failed to delete route');
      }
    } else {
      // Set specific route
      const formData = new FormData();
      formData.append('action', 'routes.set');
      formData.append('insurer_name', insurerName);
      formData.append('billing_route', route);

      const response = await fetch('/api/billing-routes.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        // Update currentRoutes
        const existing = currentRoutes.find(r => r.insurer_name === insurerName);
        if (existing) {
          existing.billing_route = route;
        } else {
          currentRoutes.push(data.route);
        }

        const routeLabel = route === 'collagen_direct' ? 'CollagenDirect' : 'Practice DME';
        showToast(`${insurerName} → ${routeLabel}`, 'success');
      } else {
        throw new Error(data.error || 'Failed to set route');
      }
    }

    renderBillingSettings();
  } catch (error) {
    console.error('Error updating insurer route:', error);
    showToast('Failed to update route', 'error');
    loadBillingRoutes(); // Reload to reset UI
  }
}

/**
 * Set all insurers to a specific route
 */
async function setAllRoutes(route) {
  if (!confirm(`Set all insurance companies to ${route === 'collagen_direct' ? 'CollagenDirect' : 'Practice DME'}?`)) {
    return;
  }

  try {
    const routes = SOUTHERN_INSURERS.map(insurer => ({
      insurer_name: insurer,
      billing_route: route
    }));

    const formData = new FormData();
    formData.append('action', 'routes.bulk_set');
    formData.append('routes', JSON.stringify(routes));

    const response = await fetch('/api/billing-routes.php', {
      method: 'POST',
      body: formData
    });

    const data = await response.json();

    if (data.success) {
      showToast('All routes updated', 'success');
      await loadBillingRoutes();
    } else {
      throw new Error(data.error || 'Failed to update routes');
    }
  } catch (error) {
    console.error('Error setting all routes:', error);
    showToast('Failed to update routes', 'error');
  }
}

/**
 * Reset all routes to default
 */
async function resetAllRoutes() {
  if (!confirm('Reset all insurance companies to use the default route?')) {
    return;
  }

  try {
    const formData = new FormData();
    formData.append('action', 'routes.bulk_set');
    formData.append('routes', JSON.stringify([]));

    const response = await fetch('/api/billing-routes.php', {
      method: 'POST',
      body: formData
    });

    const data = await response.json();

    if (data.success) {
      showToast('All routes reset to default', 'success');
      await loadBillingRoutes();
    } else {
      throw new Error(data.error || 'Failed to reset routes');
    }
  } catch (error) {
    console.error('Error resetting routes:', error);
    showToast('Failed to reset routes', 'error');
  }
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
  // Use existing toast system if available
  if (window.showToast) {
    window.showToast(message, type);
    return;
  }

  // Fallback simple toast
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
  document.addEventListener('DOMContentLoaded', loadBillingRoutes);
} else {
  loadBillingRoutes();
}
