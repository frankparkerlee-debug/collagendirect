/**
 * Demo Portal Tour Configuration
 * Uses Shepherd.js for guided walkthrough
 */

// Initialize the tour
function initDemoTour() {
  const tour = new Shepherd.Tour({
    useModalOverlay: true,
    defaultStepOptions: {
      classes: 'demo-tour-step',
      scrollTo: { behavior: 'smooth', block: 'center' },
      cancelIcon: { enabled: true },
      modalOverlayOpeningPadding: 8,
      modalOverlayOpeningRadius: 8
    }
  });

  // Step 1: Welcome
  tour.addStep({
    id: 'welcome',
    title: 'Welcome to CollagenDirect!',
    text: `
      <p>This quick tour will walk you through the key features of our physician portal.</p>
      <p class="text-sm text-gray-500 mt-2">You'll see how to:</p>
      <ul class="text-sm text-gray-600 mt-1 ml-4 list-disc">
        <li>Manage patient records</li>
        <li>Place <strong>Referral Orders</strong> (we bill insurance)</li>
        <li>Place <strong>Wholesale Orders</strong> (you bill as DME)</li>
      </ul>
    `,
    buttons: [
      {
        text: 'Skip Tour',
        action: () => {
          saveTourProgress(0, true);
          tour.complete();
        },
        secondary: true
      },
      {
        text: 'Start Tour',
        action: tour.next
      }
    ]
  });

  // Step 2: Dashboard Overview
  tour.addStep({
    id: 'dashboard',
    title: 'Dashboard Overview',
    text: `
      <p>Your dashboard shows practice activity at a glance — active patients, pending orders, and recent activity.</p>
    `,
    attachTo: { element: '#dashboardMetrics', on: 'bottom' },
    buttons: [
      { text: 'Back', action: tour.back, secondary: true },
      { text: 'Next', action: tour.next }
    ],
    beforeShowPromise: function() {
      return navigateToPage('dashboard');
    }
  });

  // Step 3: Patient Management
  tour.addStep({
    id: 'patients-list',
    title: 'Patient Management',
    text: `
      <p>The <strong>Patients</strong> section shows your full roster. Search, filter, or add new patients here.</p>
      <p class="text-sm text-gray-500 mt-2">The demo includes 5 sample patients to explore.</p>
    `,
    attachTo: { element: '#patientsList', on: 'top' },
    buttons: [
      { text: 'Back', action: tour.back, secondary: true },
      { text: 'Next', action: tour.next }
    ],
    beforeShowPromise: function() {
      return navigateToPage('patients');
    }
  });

  // Step 4: Referral Orders Intro
  tour.addStep({
    id: 'referral-intro',
    title: 'Referral Orders',
    text: `
      <p>Click <strong style="color: #4DB8A8;">Referral Order</strong> on any patient to start an insurance-billed order.</p>
      <p class="text-sm text-gray-500 mt-2">With Referral Orders:</p>
      <ul class="text-sm text-gray-600 mt-1 ml-4 list-disc">
        <li>CollagenDirect bills the patient's insurance</li>
        <li>No upfront cost to your practice</li>
        <li>We handle all billing and collections</li>
      </ul>
    `,
    attachTo: { element: '#patientsList', on: 'top' },
    buttons: [
      { text: 'Back', action: tour.back, secondary: true },
      { text: 'See the Form', action: tour.next }
    ]
  });

  // Step 5: Referral Order Form
  tour.addStep({
    id: 'referral-form',
    title: 'Referral Order Form',
    text: `
      <p>The form captures everything needed for insurance billing:</p>
      <ul class="text-sm text-gray-600 mt-2 ml-4 list-disc">
        <li><strong>Wound details</strong> — location, type, dimensions</li>
        <li><strong>ICD-10 lookup</strong> — searchable diagnosis codes</li>
        <li><strong>Product selection</strong> — size matched to wound</li>
        <li><strong>Documents</strong> — ID, insurance card, wound photo</li>
      </ul>
    `,
    attachTo: { element: '#referralOrderForm', on: 'top' },
    buttons: [
      { text: 'Back', action: tour.back, secondary: true },
      { text: 'Next', action: tour.next }
    ],
    beforeShowPromise: function() {
      return new Promise((resolve) => {
        const currentPage = document.body.dataset.currentPage;
        if (currentPage === 'referral-order') {
          resolve();
          return;
        }
        sessionStorage.setItem('demoTourNavigating', 'true');
        sessionStorage.setItem('demoTourTargetPage', 'referral-order');
        window.location.href = '?page=referral-order';
        resolve();
      });
    }
  });

  // Step 6: Order Tracking
  tour.addStep({
    id: 'orders-list',
    title: 'Order Tracking',
    text: `
      <p>The <strong>Orders</strong> section tracks all orders from submission to delivery:</p>
      <ul class="text-sm text-gray-600 mt-2 ml-4 list-disc">
        <li><span class="text-yellow-600">Submitted</span> — Awaiting review</li>
        <li><span class="text-green-600">Approved</span> — Ready to ship</li>
        <li><span class="text-blue-600">In Transit</span> — On the way</li>
        <li><span class="text-emerald-600">Delivered</span> — Complete</li>
      </ul>
    `,
    attachTo: { element: '#ordersList', on: 'top' },
    buttons: [
      { text: 'Back', action: tour.back, secondary: true },
      { text: 'Next', action: tour.next }
    ],
    beforeShowPromise: function() {
      return navigateToPage('orders');
    }
  });

  // Step 7: Wholesale Orders
  tour.addStep({
    id: 'wholesale-form',
    title: 'Wholesale / DME Orders',
    text: `
      <p><strong>Wholesale Orders</strong> are for practices with DME licenses:</p>
      <ul class="text-sm text-gray-600 mt-2 ml-4 list-disc">
        <li>Purchase inventory at wholesale pricing</li>
        <li>You bill insurance directly as DME supplier</li>
        <li>Ship to office or directly to patient</li>
        <li>Net-30 payment terms</li>
      </ul>
    `,
    attachTo: { element: '#wholesaleForm', on: 'top' },
    buttons: [
      { text: 'Back', action: tour.back, secondary: true },
      { text: 'Finish', action: tour.next }
    ],
    beforeShowPromise: function() {
      return navigateToPage('wholesale');
    }
  });

  // Step 8: Tour Complete
  tour.addStep({
    id: 'complete',
    title: 'Tour Complete!',
    text: `
      <p>You're all set to explore the CollagenDirect demo portal.</p>
      <p class="text-sm text-gray-500 mt-3">Try it out:</p>
      <ul class="text-sm text-gray-600 mt-1 ml-4 list-disc">
        <li>Create test patients</li>
        <li>Place Referral or Wholesale orders</li>
        <li>Track order status</li>
      </ul>
      <p class="text-sm text-amber-600 mt-3">
        <strong>Note:</strong> Demo data is automatically cleared within 24 hours.
      </p>
    `,
    buttons: [
      {
        text: 'Reset Demo',
        action: () => {
          resetDemo();
          tour.complete();
        },
        secondary: true
      },
      {
        text: 'Start Exploring',
        action: () => {
          saveTourProgress(8, true);
          tour.complete();
        }
      }
    ]
  });

  // Save progress on each step
  tour.on('show', (event) => {
    const stepIndex = tour.steps.indexOf(event.step);
    saveTourProgress(stepIndex, false);
  });

  return tour;
}

// Helper: Navigate to a page (full page navigation with tour state persistence)
function navigateToPage(page) {
  return new Promise((resolve) => {
    const currentPage = document.body.dataset.currentPage;
    if (currentPage === page) {
      resolve();
      return;
    }

    // Store that we're mid-tour navigation in sessionStorage
    sessionStorage.setItem('demoTourNavigating', 'true');
    sessionStorage.setItem('demoTourTargetPage', page);

    // Navigate to the new page
    window.location.href = '?page=' + page;

    // This resolve won't actually run since we're navigating away,
    // but keep it for completeness
    resolve();
  });
}

// Helper: Save tour progress to server
async function saveTourProgress(step, completed) {
  try {
    await fetch('/api/demo/tour.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ step, completed })
    });
  } catch (e) {
    console.error('Failed to save tour progress:', e);
  }
}

// Helper: Reset demo data
async function resetDemo() {
  try {
    const res = await fetch('/api/demo/reset.php', {
      method: 'POST',
      credentials: 'include'
    });
    if (res.ok) {
      window.location.reload();
    }
  } catch (e) {
    console.error('Failed to reset demo:', e);
  }
}

// Check tour progress and start if needed
async function checkAndStartTour() {
  // Check if we're in the middle of a tour navigation
  const isNavigating = sessionStorage.getItem('demoTourNavigating');
  const targetPage = sessionStorage.getItem('demoTourTargetPage');
  const currentPage = document.body.dataset.currentPage;

  // Clear the navigation flags
  sessionStorage.removeItem('demoTourNavigating');
  sessionStorage.removeItem('demoTourTargetPage');

  // If we just navigated for the tour, resume the tour at the appropriate step
  if (isNavigating && targetPage === currentPage) {
    const tour = initDemoTour();
    tour.start();
    // Skip to the step for this page
    const pageStepMap = {
      'dashboard': 1,       // dashboard step
      'patients': 2,        // patients-list step
      'referral-order': 4,  // referral-form step
      'orders': 5,          // orders-list step
      'wholesale': 6        // wholesale-form step
    };
    const targetStep = pageStepMap[currentPage] || 0;
    for (let i = 0; i < targetStep; i++) {
      tour.next();
    }
    return;
  }

  try {
    const res = await fetch('/api/demo/tour.php', { credentials: 'include' });

    // Handle non-ok response
    if (!res.ok) {
      console.error('Tour API returned status:', res.status);
      // Don't start tour automatically if API fails - prevent gray overlay
      return;
    }

    const data = await res.json();

    if (!data.tour_completed) {
      const tour = initDemoTour();

      // If user was partway through, offer to resume or restart
      if (data.tour_step_reached > 0 && data.tour_step_reached < 8) {
        const resume = confirm('Would you like to resume the tour where you left off?');
        if (resume) {
          tour.start();
          // Skip to the step they were on
          for (let i = 0; i < data.tour_step_reached; i++) {
            tour.next();
          }
        } else {
          tour.start();
        }
      } else {
        tour.start();
      }
    }
  } catch (e) {
    console.error('Failed to check tour progress:', e);
    // Don't start tour if there's an error - prevent gray overlay
  }
}

// Cleanup any stuck Shepherd overlay (e.g., from failed tour start)
function cleanupStuckOverlay() {
  const overlay = document.querySelector('.shepherd-modal-overlay-container');
  if (overlay) {
    overlay.remove();
  }
}

// Export for use in main page
window.DemoTour = {
  init: initDemoTour,
  checkAndStart: checkAndStartTour,
  reset: resetDemo,
  cleanup: cleanupStuckOverlay
};
