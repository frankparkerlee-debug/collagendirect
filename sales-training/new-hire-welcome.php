<?php
session_start();
$authorized = false;
$user_email = '';

if (isset($_SESSION['user_email'])) {
    $user_email = $_SESSION['user_email'];
    if (preg_match('/@collagendirect\.health$/i', $user_email)) {
        $authorized = true;
    }
}

if (isset($_GET['email']) && preg_match('/@collagendirect\.health$/i', $_GET['email'])) {
    $_SESSION['user_email'] = $_GET['email'];
    $user_email = $_GET['email'];
    $authorized = true;
}

if (!$authorized) {
    header('Location: login.php');
    exit;
}

$user_name = explode('@', $user_email)[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Welcome to CollagenDirect! | New Hire Onboarding</title>
  <meta name="robots" content="noindex, nofollow">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: { teal: '#47c6be', blue: '#2a78ff', navy: '#0a2540' }
          }
        }
      }
    }
  </script>
</head>
<body class="bg-gray-50">

  <!-- Header -->
  <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-8 w-auto">
        <div>
          <div class="text-sm font-bold text-gray-900">New Hire Onboarding</div>
          <div class="text-xs text-gray-500">Welcome Aboard!</div>
        </div>
      </div>
      <a href="login.php?logout=1" class="text-sm text-gray-500 hover:text-brand-teal transition">Logout</a>
    </div>
  </header>

  <div class="max-w-6xl mx-auto px-6 py-12">

    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-brand-teal to-emerald-500 rounded-3xl p-12 text-white mb-12 text-center">
      <div class="text-6xl mb-4">👋</div>
      <h1 class="text-5xl font-black mb-4">Welcome to CollagenDirect, <?php echo htmlspecialchars(ucfirst($user_name)); ?>!</h1>
      <p class="text-xl text-teal-50 max-w-3xl mx-auto mb-6">
        We're thrilled to have you on the team. This portal will guide you through your first week, covering everything from compliance training to sales methodology.
      </p>
      <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur px-6 py-3 rounded-xl">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span class="font-semibold">Estimated time to complete: 4-6 hours spread over your first week</span>
      </div>
    </div>

    <!-- Progress Tracker -->
    <div class="bg-white rounded-3xl shadow-xl p-8 mb-12">
      <h2 class="text-2xl font-black text-gray-900 mb-6">Your Onboarding Progress</h2>
      <div class="space-y-3">
        <div class="flex items-center gap-4">
          <div class="w-full bg-gray-200 rounded-full h-3">
            <div class="bg-gradient-to-r from-brand-teal to-emerald-500 h-3 rounded-full" style="width: 0%" id="progress-bar"></div>
          </div>
          <div class="text-sm font-bold text-gray-600 whitespace-nowrap"><span id="progress-percent">0</span>% Complete</div>
        </div>
        <p class="text-sm text-gray-600">
          <span id="completed-count">0</span> of <span id="total-count">19</span> tasks completed
        </p>
      </div>
    </div>

    <!-- Phase 1: HR & Compliance (Critical First) -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <div class="flex items-center gap-4 mb-8">
        <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-orange-500 rounded-2xl flex items-center justify-center flex-shrink-0">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
          </svg>
        </div>
        <div>
          <div class="text-sm font-bold text-orange-600 mb-1">PHASE 1 - COMPLETE FIRST</div>
          <h2 class="text-3xl font-black text-gray-900">HR & Compliance Paperwork</h2>
          <p class="text-gray-600">Required for all healthcare employees - complete by end of Day 1</p>
        </div>
      </div>

      <div class="space-y-4">
        <!-- Task Checklist -->
        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="compliance">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Complete Form I-9 (Employment Eligibility)</div>
            <p class="text-sm text-gray-600 mt-1">Verify identity and employment authorization (bring two forms of ID)</p>
            <a href="forms/i-9.html" target="_blank" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ Open & print I-9 form</a>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="compliance">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Complete Form W-4 (Tax Withholding)</div>
            <p class="text-sm text-gray-600 mt-1">Federal tax withholding information</p>
            <a href="forms/w-4.html" target="_blank" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ Open & print W-4 form</a>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="compliance">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Complete Direct Deposit Form</div>
            <p class="text-sm text-gray-600 mt-1">Bank account info for payroll (bring voided check or bank letter)</p>
            <a href="forms/direct-deposit.html" target="_blank" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ Open & print form</a>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-red-50 rounded-xl border-2 border-red-300 hover:border-red-500 cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-red-600 mt-1 onboarding-checkbox" data-category="compliance">
          <div class="flex-1">
            <div class="font-bold text-red-900 group-hover:text-red-700 transition flex items-center gap-2">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
              </svg>
              Complete HIPAA Training & Sign Confidentiality Agreement
            </div>
            <p class="text-sm text-red-800 mt-1">REQUIRED for healthcare organizations - protects patient privacy</p>
            <div class="mt-2 space-y-1">
              <a href="hipaa-training.php" target="_blank" class="text-xs text-red-700 hover:underline inline-block font-semibold">→ Start HIPAA training (30 minutes)</a>
              <br>
              <a href="forms/hipaa-confidentiality-agreement.html" target="_blank" class="text-xs text-red-700 hover:underline inline-block font-semibold">→ Print & sign confidentiality agreement</a>
            </div>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-red-50 rounded-xl border-2 border-red-300 hover:border-red-500 cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-red-600 mt-1 onboarding-checkbox" data-category="compliance">
          <div class="flex-1">
            <div class="font-bold text-red-900 group-hover:text-red-700 transition flex items-center gap-2">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
              </svg>
              Review & Sign Business Associate Agreement (BAA)
            </div>
            <p class="text-sm text-red-800 mt-1">Required for handling Protected Health Information (PHI)</p>
            <div class="mt-2 space-y-1">
              <a href="/legal/baa.html" target="_blank" class="text-xs text-red-700 hover:underline inline-block font-semibold">→ Read full BAA agreement</a>
              <br>
              <a href="forms/baa-employee-acknowledgment.html" target="_blank" class="text-xs text-red-700 hover:underline inline-block font-semibold">→ Print & sign BAA acknowledgment</a>
            </div>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="compliance">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Review & Acknowledge Employee Handbook</div>
            <p class="text-sm text-gray-600 mt-1">Company policies, code of conduct, benefits overview</p>
            <div class="mt-2 space-y-1">
              <a href="forms/employee-handbook.html" target="_blank" class="text-xs text-brand-teal hover:underline inline-block">→ Read full employee handbook</a>
              <br>
              <a href="forms/employee-handbook-acknowledgment.html" target="_blank" class="text-xs text-brand-teal hover:underline inline-block">→ Print acknowledgment form</a>
            </div>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="compliance">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Complete Emergency Contact Information</div>
            <p class="text-sm text-gray-600 mt-1">Provide emergency contacts and optional medical information</p>
            <a href="forms/emergency-contact.html" target="_blank" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ Open & print form</a>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="compliance">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Set Up Email & Portal Access</div>
            <p class="text-sm text-gray-600 mt-1">Your @collagendirect.health email and physician portal credentials</p>
            <a href="mailto:it@collagendirect.health?subject=New%20Hire%20IT%20Setup" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ Contact IT for setup</a>
          </div>
        </label>
      </div>
    </div>

    <!-- Phase 2: Product & Industry Knowledge -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <div class="flex items-center gap-4 mb-8">
        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center flex-shrink-0">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
          </svg>
        </div>
        <div>
          <div class="text-sm font-bold text-blue-600 mb-1">PHASE 2 - DAY 2-3</div>
          <h2 class="text-3xl font-black text-gray-900">Product & Industry Knowledge</h2>
          <p class="text-gray-600">Learn about collagen therapy and wound care basics</p>
        </div>
      </div>

      <div class="space-y-4">
        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="product">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Read: Complete Guide to Collagen Wound Therapy</div>
            <p class="text-sm text-gray-600 mt-1">Understand what collagen does and why physicians use it (8 min read)</p>
            <a href="/resources/collagen-therapy-guide/" target="_blank" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ Read guide</a>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="product">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Review Product Catalog & HCPCS Codes</div>
            <p class="text-sm text-gray-600 mt-1">Memorize our 4 main products and their billing codes</p>
            <a href="/sales-training/quick-reference.php" target="_blank" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ View Quick Reference</a>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="product">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Study Insurance Coverage & Reimbursement</div>
            <p class="text-sm text-gray-600 mt-1">Understand Medicare, Medicaid, and commercial insurance coverage</p>
            <a href="/insurance-coverage.html" target="_blank" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ Read Insurance Hub</a>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="product">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Review Physician FAQ</div>
            <p class="text-sm text-gray-600 mt-1">Learn common physician questions and our answers</p>
            <a href="/faq-physicians.html" target="_blank" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ Read FAQ</a>
          </div>
        </label>
      </div>
    </div>

    <!-- Phase 3: Sales Training -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <div class="flex items-center gap-4 mb-8">
        <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-2xl flex items-center justify-center flex-shrink-0">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
          </svg>
        </div>
        <div>
          <div class="text-sm font-bold text-emerald-600 mb-1">PHASE 3 - DAY 3-5</div>
          <h2 class="text-3xl font-black text-gray-900">Sales Methodology Training</h2>
          <p class="text-gray-600">Master our 4-step sales process</p>
        </div>
      </div>

      <div class="space-y-4">
        <label class="flex items-start gap-4 p-5 bg-emerald-50 rounded-xl border-2 border-emerald-300 hover:border-emerald-500 cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-emerald-600 mt-1 onboarding-checkbox" data-category="sales">
          <div class="flex-1">
            <div class="font-bold text-emerald-900 group-hover:text-emerald-700 transition flex items-center gap-2">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
              </svg>
              Read: The 4-Step Sales Process (PRIORITY)
            </div>
            <p class="text-sm text-emerald-800 mt-1">Our proven methodology: Get Meeting → Conversation → Register → Nurture</p>
            <a href="/sales-training/sales-process.php" target="_blank" class="text-xs text-emerald-700 hover:underline mt-2 inline-block font-semibold">→ Read full guide</a>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="sales">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Memorize Discovery Questions</div>
            <p class="text-sm text-gray-600 mt-1">Learn the 4 key questions to find physician pain points</p>
            <a href="/sales-training/sales-process.php#step2" target="_blank" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ Jump to Step 2 (Discovery)</a>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="sales">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Practice Cold Call Scripts</div>
            <p class="text-sm text-gray-600 mt-1">Role-play gatekeeper scenarios with your manager</p>
            <a href="/sales-training/scripts.php" target="_blank" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ View scripts</a>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="sales">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Study Competitive Battle Cards</div>
            <p class="text-sm text-gray-600 mt-1">Learn how to position vs Smith & Nephew, 3M, Integra, etc.</p>
            <a href="/sales-training/battle-cards.php" target="_blank" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ View battle cards</a>
          </div>
        </label>
      </div>
    </div>

    <!-- Phase 4: Systems & Tools -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <div class="flex items-center gap-4 mb-8">
        <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center flex-shrink-0">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
          </svg>
        </div>
        <div>
          <div class="text-sm font-bold text-purple-600 mb-1">PHASE 4 - END OF WEEK 1</div>
          <h2 class="text-3xl font-black text-gray-900">Systems & Tools Training</h2>
          <p class="text-gray-600">Learn the portal, CRM, and other tools</p>
        </div>
      </div>

      <div class="space-y-4">
        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="systems">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Complete Portal Walkthrough</div>
            <p class="text-sm text-gray-600 mt-1">Learn how physicians use the portal (you'll demo this to prospects)</p>
            <a href="/portal-guide.php" target="_blank" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ View portal guide</a>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="systems">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Set Up CRM Access</div>
            <p class="text-sm text-gray-600 mt-1">Get access to customer relationship management system</p>
            <a href="mailto:sales-support@collagendirect.health?subject=CRM%20Access%20Request" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ Request CRM access</a>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="systems">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Get Product Samples Kit</div>
            <p class="text-sm text-gray-600 mt-1">Physical samples to show physicians (collagen sheets, gels, powder)</p>
            <a href="mailto:sales-support@collagendirect.health?subject=Sample%20Kit%20Request" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ Request sample kit</a>
          </div>
        </label>

        <label class="flex items-start gap-4 p-5 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-brand-teal cursor-pointer transition group">
          <input type="checkbox" class="w-5 h-5 text-brand-teal mt-1 onboarding-checkbox" data-category="systems">
          <div class="flex-1">
            <div class="font-bold text-gray-900 group-hover:text-brand-teal transition">Schedule Shadow Day with Senior Rep</div>
            <p class="text-sm text-gray-600 mt-1">Observe a seasoned rep on actual sales calls</p>
            <a href="mailto:sales-support@collagendirect.health?subject=Shadow%20Day%20Request" class="text-xs text-brand-teal hover:underline mt-2 inline-block">→ Schedule with manager</a>
          </div>
        </label>
      </div>
    </div>

    <!-- Completion -->
    <div class="bg-gradient-to-r from-brand-navy to-slate-800 text-white rounded-3xl p-10 text-center" id="completion-banner" style="display: none;">
      <div class="text-6xl mb-4">🎉</div>
      <h2 class="text-4xl font-black mb-4">Congratulations, You're Ready!</h2>
      <p class="text-xl text-slate-200 mb-6 max-w-2xl mx-auto">
        You've completed your onboarding. You're now a fully compliant, trained member of the CollagenDirect sales team.
      </p>
      <div class="inline-flex items-center gap-3 bg-white/20 backdrop-blur px-6 py-3 rounded-xl">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <span class="font-bold">Next Step: Schedule your first week of sales calls with your manager</span>
      </div>
    </div>

  </div>

  <script>
    // Progress tracking
    const checkboxes = document.querySelectorAll('.onboarding-checkbox');
    const progressBar = document.getElementById('progress-bar');
    const progressPercent = document.getElementById('progress-percent');
    const completedCount = document.getElementById('completed-count');
    const totalCount = document.getElementById('total-count');
    const completionBanner = document.getElementById('completion-banner');

    totalCount.textContent = checkboxes.length;

    // Load saved progress from localStorage
    const savedProgress = JSON.parse(localStorage.getItem('onboarding_progress') || '{}');
    checkboxes.forEach((checkbox, index) => {
      if (savedProgress[index]) {
        checkbox.checked = true;
      }
    });

    function updateProgress() {
      const completed = Array.from(checkboxes).filter(cb => cb.checked).length;
      const total = checkboxes.length;
      const percent = Math.round((completed / total) * 100);

      progressBar.style.width = percent + '%';
      progressPercent.textContent = percent;
      completedCount.textContent = completed;

      // Save progress
      const progress = {};
      checkboxes.forEach((cb, index) => {
        progress[index] = cb.checked;
      });
      localStorage.setItem('onboarding_progress', JSON.stringify(progress));

      // Show completion banner
      if (percent === 100) {
        completionBanner.style.display = 'block';
        completionBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }

    checkboxes.forEach(checkbox => {
      checkbox.addEventListener('change', updateProgress);
    });

    // Initial update
    updateProgress();
  </script>

</body>
</html>
