/**
 * Demo Portal Tour Configuration
 * Uses Shepherd.js for guided walkthrough
 *
 * Navigation Strategy:
 * - Store target step in sessionStorage before navigation
 * - After page load, check sessionStorage and resume at correct step
 * - Each step knows which page it needs to be on
 */

// Step definitions with their required pages
const TOUR_STEPS = [
  { id: 'welcome', page: 'dashboard' },
  { id: 'dashboard', page: 'dashboard' },
  { id: 'patients-list', page: 'patients' },
  { id: 'referral-intro', page: 'patients' },
  { id: 'referral-form', page: 'referral-order' },
  { id: 'orders-list', page: 'orders' },
  { id: 'wholesale-form', page: 'wholesale' },
  { id: 'complete', page: 'wholesale' }
];

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

  // Helper to navigate and resume at a specific step
  function goToStepWithNavigation(stepIndex) {
    const stepDef = TOUR_STEPS[stepIndex];
    const currentPage = document.body.dataset.currentPage;

    if (stepDef && stepDef.page !== currentPage) {
      // Need to navigate - save the target step and navigate
      sessionStorage.setItem('demoTourResumeStep', stepIndex.toString());
      window.location.href = '?page=' + stepDef.page;
      return false; // Signal that we're navigating away
    }
    return true; // Already on the right page
  }

  // Step 0: Welcome
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

  // Step 1: Dashboard Overview
  tour.addStep({
    id: 'dashboard',
    title: 'Dashboard Overview',
    text: `
      <p>Your dashboard shows practice activity at a glance — active patients, pending orders, and recent activity.</p>
    `,
    attachTo: { element: '#dashboardMetrics', on: 'bottom' },
    buttons: [
      { text: 'Back', action: tour.back, secondary: true },
      {
        text: 'Next',
        action: function() {
          // Going to step 2 (patients-list) which requires 'patients' page
          if (goToStepWithNavigation(2)) {
            tour.next();
          }
        }
      }
    ]
  });

  // Step 2: Patient Management
  tour.addStep({
    id: 'patients-list',
    title: 'Patient Management',
    text: `
      <p>The <strong>Patients</strong> section shows your full roster. Search, filter, or add new patients here.</p>
      <p class="text-sm text-gray-500 mt-2">The demo includes sample patients to explore.</p>
    `,
    attachTo: { element: '#patientsList', on: 'top' },
    buttons: [
      {
        text: 'Back',
        action: function() {
          // Going back to step 1 (dashboard) which requires 'dashboard' page
          if (goToStepWithNavigation(1)) {
            tour.back();
          }
        },
        secondary: true
      },
      { text: 'Next', action: tour.next }
    ]
  });

  // Step 3: Referral Orders Intro (still on patients page)
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
      {
        text: 'See the Form',
        action: function() {
          // Going to step 4 (referral-form) which requires 'referral-order' page
          if (goToStepWithNavigation(4)) {
            tour.next();
          }
        }
      }
    ]
  });

  // Step 4: Referral Order Form
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
      {
        text: 'Back',
        action: function() {
          // Going back to step 3 (referral-intro) which requires 'patients' page
          if (goToStepWithNavigation(3)) {
            tour.back();
          }
        },
        secondary: true
      },
      {
        text: 'Next',
        action: function() {
          // Going to step 5 (orders-list) which requires 'orders' page
          if (goToStepWithNavigation(5)) {
            tour.next();
          }
        }
      }
    ]
  });

  // Step 5: Order Tracking
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
      {
        text: 'Back',
        action: function() {
          // Going back to step 4 (referral-form) which requires 'referral-order' page
          if (goToStepWithNavigation(4)) {
            tour.back();
          }
        },
        secondary: true
      },
      {
        text: 'Next',
        action: function() {
          // Going to step 6 (wholesale-form) which requires 'wholesale' page
          if (goToStepWithNavigation(6)) {
            tour.next();
          }
        }
      }
    ]
  });

  // Step 6: Wholesale Orders
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
      {
        text: 'Back',
        action: function() {
          // Going back to step 5 (orders-list) which requires 'orders' page
          if (goToStepWithNavigation(5)) {
            tour.back();
          }
        },
        secondary: true
      },
      { text: 'Finish', action: tour.next }
    ]
  });

  // Step 7: Tour Complete
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
          saveTourProgress(7, true);
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

// Check tour progress and start/resume if needed
async function checkAndStartTour() {
  const currentPage = document.body.dataset.currentPage;

  // Check if we need to resume at a specific step (after navigation)
  const resumeStep = sessionStorage.getItem('demoTourResumeStep');
  sessionStorage.removeItem('demoTourResumeStep');

  if (resumeStep !== null) {
    const stepIndex = parseInt(resumeStep, 10);
    const tour = initDemoTour();
    tour.start();

    // Advance to the target step
    for (let i = 0; i < stepIndex; i++) {
      tour.next();
    }
    return;
  }

  // Otherwise check server for tour state
  try {
    const res = await fetch('/api/demo/tour.php', { credentials: 'include' });

    if (!res.ok) {
      console.error('Tour API returned status:', res.status);
      return;
    }

    const data = await res.json();

    // Only auto-start on dashboard for new users
    if (!data.tour_completed && data.tour_step_reached === 0 && currentPage === 'dashboard') {
      const tour = initDemoTour();
      tour.start();
    }
  } catch (e) {
    console.error('Failed to check tour progress:', e);
  }
}

// Start tour from beginning (used by Restart Tour button)
function startTourFromBeginning() {
  // First navigate to dashboard if not there
  const currentPage = document.body.dataset.currentPage;
  if (currentPage !== 'dashboard') {
    sessionStorage.setItem('demoTourResumeStep', '0');
    window.location.href = '?page=dashboard';
    return;
  }

  const tour = initDemoTour();
  tour.start();
}

// Cleanup any stuck Shepherd overlay
function cleanupStuckOverlay() {
  document.querySelectorAll('.shepherd-modal-overlay-container, .shepherd-element').forEach(el => {
    el.remove();
  });
}

// Export for use in main page
window.DemoTour = {
  init: initDemoTour,
  checkAndStart: checkAndStartTour,
  startFromBeginning: startTourFromBeginning,
  reset: resetDemo,
  cleanup: cleanupStuckOverlay
};
