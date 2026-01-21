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
      <p>This guided tour will walk you through the key features of our physician portal.</p>
      <p class="text-sm text-gray-500 mt-2">You'll learn how to:</p>
      <ul class="text-sm text-gray-600 mt-1 ml-4 list-disc">
        <li>Manage patient records</li>
        <li>Place and track orders</li>
        <li>Create wholesale/DME orders</li>
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
      <p>Your dashboard provides a quick overview of your practice activity.</p>
      <p class="text-sm text-gray-500 mt-2">See active patients, pending orders, and recent activity at a glance.</p>
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

  // Step 3: Patient List
  tour.addStep({
    id: 'patients-nav',
    title: 'Patient Management',
    text: `
      <p>Click <strong>Patients</strong> to view and manage your patient roster.</p>
      <p class="text-sm text-gray-500 mt-2">You can search, filter, and add new patients from this section.</p>
    `,
    attachTo: { element: '[data-nav="patients"]', on: 'right' },
    buttons: [
      { text: 'Back', action: tour.back, secondary: true },
      { text: 'Next', action: tour.next }
    ]
  });

  // Step 4: Patient List View
  tour.addStep({
    id: 'patients-list',
    title: 'Your Patient Roster',
    text: `
      <p>Here you can see all your patients with their key information.</p>
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

  // Step 5: Add Patient Button
  tour.addStep({
    id: 'add-patient',
    title: 'Adding New Patients',
    text: `
      <p>Click <strong>Add Patient</strong> to create a new patient record.</p>
      <p class="text-sm text-gray-500 mt-2">You'll enter demographics, insurance info, and wound details.</p>
    `,
    attachTo: { element: '#addPatientBtn', on: 'bottom' },
    buttons: [
      { text: 'Back', action: tour.back, secondary: true },
      { text: 'Next', action: tour.next }
    ]
  });

  // Step 6: Orders Navigation
  tour.addStep({
    id: 'orders-nav',
    title: 'Order Management',
    text: `
      <p>The <strong>Orders</strong> section shows all patient orders.</p>
      <p class="text-sm text-gray-500 mt-2">Track order status from submission through delivery.</p>
    `,
    attachTo: { element: '[data-nav="orders"]', on: 'right' },
    buttons: [
      { text: 'Back', action: tour.back, secondary: true },
      { text: 'Next', action: tour.next }
    ]
  });

  // Step 7: Orders List
  tour.addStep({
    id: 'orders-list',
    title: 'Order Tracking',
    text: `
      <p>View all orders with their current status.</p>
      <ul class="text-sm text-gray-600 mt-2 ml-4 list-disc">
        <li><span class="text-yellow-600">Submitted</span> - Awaiting review</li>
        <li><span class="text-green-600">Approved</span> - Ready to ship</li>
        <li><span class="text-blue-600">In Transit</span> - On the way</li>
        <li><span class="text-emerald-600">Delivered</span> - Complete</li>
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

  // Step 8: Wholesale Orders
  tour.addStep({
    id: 'wholesale-nav',
    title: 'Wholesale / DME Orders',
    text: `
      <p><strong>Wholesale Orders</strong> allows practices with DME licenses to order in bulk.</p>
      <p class="text-sm text-gray-500 mt-2">Different pricing and workflow for direct practice billing.</p>
    `,
    attachTo: { element: '[data-nav="wholesale"]', on: 'right' },
    buttons: [
      { text: 'Back', action: tour.back, secondary: true },
      { text: 'Next', action: tour.next }
    ]
  });

  // Step 9: Wholesale Interface
  tour.addStep({
    id: 'wholesale-form',
    title: 'Bulk Ordering Made Easy',
    text: `
      <p>Create wholesale orders for multiple patients at once.</p>
      <ul class="text-sm text-gray-600 mt-2 ml-4 list-disc">
        <li>Select products and quantities</li>
        <li>Choose shipping destination</li>
        <li>Order ships directly to your practice</li>
      </ul>
    `,
    attachTo: { element: '#wholesaleForm', on: 'top' },
    buttons: [
      { text: 'Back', action: tour.back, secondary: true },
      { text: 'Next', action: tour.next }
    ],
    beforeShowPromise: function() {
      return navigateToPage('wholesale');
    }
  });

  // Step 10: Tour Complete
  tour.addStep({
    id: 'complete',
    title: 'Tour Complete!',
    text: `
      <p>You've completed the guided tour of CollagenDirect.</p>
      <p class="text-sm text-gray-500 mt-3">Feel free to explore the demo portal. You can:</p>
      <ul class="text-sm text-gray-600 mt-1 ml-4 list-disc">
        <li>Create test patients</li>
        <li>Place demo orders</li>
        <li>Try the wholesale ordering</li>
      </ul>
      <p class="text-sm text-amber-600 mt-3">
        <strong>Note:</strong> All demo data is automatically deleted within 24 hours.
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
          saveTourProgress(10, true);
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
      'dashboard': 1, // dashboard step
      'patients': 3,  // patients-list step
      'orders': 6,    // orders-list step
      'wholesale': 8  // wholesale-form step
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
      if (data.tour_step_reached > 0 && data.tour_step_reached < 10) {
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
