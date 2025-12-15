<?php
/**
 * Rep Invite Completion Page
 *
 * Allows invited reps to set their password and sign required documents
 */
declare(strict_types=1);
session_start();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Get and validate token
$token = $_GET['token'] ?? '';
if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
  $invalidToken = true;
} else {
  // Verify token against database
  require_once __DIR__ . '/../api/db.php';

  $stmt = $pdo->prepare("
    SELECT sr.id as rep_id, sr.user_id, sr.invite_token_expires_at, sr.company_name,
           u.email, u.first_name, u.last_name, u.phone
    FROM sales_reps sr
    JOIN users u ON u.id = sr.user_id
    WHERE sr.invite_token = ? AND sr.status = 'invited'
  ");
  $stmt->execute([$token]);
  $inviteData = $stmt->fetch();

  if (!$inviteData) {
    $invalidToken = true;
  } else if ($inviteData['invite_token_expires_at'] && strtotime($inviteData['invite_token_expires_at']) < time()) {
    $expiredToken = true;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Complete Your Registration — CollagenDirect</title>
  <meta name="description" content="Complete your CollagenDirect sales representative registration.">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              teal: '#47c6be',
              blue: '#2a78ff',
              navy: '#0a2540',
              slate: '#64748b'
            }
          },
          fontFamily: {
            sans: ['Inter', 'system-ui', 'sans-serif']
          }
        }
      }
    }
  </script>
  <style>
    body { font-feature-settings: 'cv11', 'ss01'; -webkit-font-smoothing: antialiased; }
    .step-indicator.active { background: linear-gradient(135deg, #14b8a6 0%, #10b981 100%); color: white; }
    .step-indicator.completed { background: #10b981; color: white; }
    .step-indicator.inactive { background: #e5e7eb; color: #6b7280; }
    .step-line.active { background: #10b981; }
    .step-line.inactive { background: #e5e7eb; }
    .agreement-container { max-height: 400px; overflow-y: auto; }
    .agreement-container::-webkit-scrollbar { width: 8px; }
    .agreement-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
    .agreement-container::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 4px; }
    .agreement-container::-webkit-scrollbar-thumb:hover { background: #64748b; }
  </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-white min-h-screen">

  <!-- Header -->
  <header class="bg-white border-b border-gray-100 sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-6 py-4">
      <div class="flex items-center justify-between">
        <a href="/" class="flex items-center gap-3">
          <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-8 w-auto">
        </a>
      </div>
    </div>
  </header>

  <main class="max-w-4xl mx-auto px-6 py-12">
    <?php if (isset($invalidToken) && $invalidToken): ?>
      <!-- Invalid Token -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 md:p-10 text-center">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
          <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Invalid Invite Link</h1>
        <p class="text-gray-600 mb-6">This invite link is invalid or has already been used. Please contact your administrator for a new invite.</p>
        <a href="/login" class="inline-flex items-center gap-2 text-teal-600 font-semibold hover:underline">
          Go to Login
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </a>
      </div>
    <?php elseif (isset($expiredToken) && $expiredToken): ?>
      <!-- Expired Token -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 md:p-10 text-center">
        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-6">
          <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Invite Expired</h1>
        <p class="text-gray-600 mb-6">This invite has expired. Please contact your administrator to send a new invite.</p>
        <p class="text-sm text-gray-500">
          Contact: <a href="mailto:partners@collagendirect.health" class="text-teal-600 hover:underline">partners@collagendirect.health</a>
        </p>
      </div>
    <?php else: ?>
      <!-- Progress Indicator -->
      <div class="mb-12">
        <div class="flex items-center justify-center">
          <div class="flex items-center">
            <!-- Step 1 -->
            <div class="flex flex-col items-center">
              <div id="step1-indicator" class="step-indicator active w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all">1</div>
              <span class="text-xs mt-2 font-medium text-gray-600">Password</span>
            </div>
            <div id="line1" class="step-line inactive w-16 md:w-24 h-1 mx-2 transition-all"></div>

            <!-- Step 2 -->
            <div class="flex flex-col items-center">
              <div id="step2-indicator" class="step-indicator inactive w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all">2</div>
              <span class="text-xs mt-2 font-medium text-gray-600">Agreement</span>
            </div>
            <div id="line2" class="step-line inactive w-16 md:w-24 h-1 mx-2 transition-all"></div>

            <!-- Step 3 -->
            <div class="flex flex-col items-center">
              <div id="step3-indicator" class="step-indicator inactive w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all">3</div>
              <span class="text-xs mt-2 font-medium text-gray-600">BAA</span>
            </div>
            <div id="line3" class="step-line inactive w-16 md:w-24 h-1 mx-2 transition-all"></div>

            <!-- Step 4 -->
            <div class="flex flex-col items-center">
              <div id="step4-indicator" class="step-indicator inactive w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all">4</div>
              <span class="text-xs mt-2 font-medium text-gray-600">Complete</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Step 1: Set Password -->
      <section id="step1" class="step-section">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 md:p-10">
          <div class="text-center mb-8">
            <h1 class="text-3xl font-black text-gray-900 mb-2">Welcome, <?= htmlspecialchars($inviteData['first_name']) ?>!</h1>
            <p class="text-gray-600">Create your password to complete your registration</p>
          </div>

          <!-- Account Info (Read-only) -->
          <div class="bg-slate-50 rounded-xl p-6 mb-8">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Your Account Info</h3>
            <div class="grid md:grid-cols-2 gap-4">
              <div>
                <p class="text-xs text-gray-500">Name</p>
                <p class="font-medium text-gray-900"><?= htmlspecialchars($inviteData['first_name'] . ' ' . $inviteData['last_name']) ?></p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Email</p>
                <p class="font-medium text-gray-900"><?= htmlspecialchars($inviteData['email']) ?></p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Phone</p>
                <p class="font-medium text-gray-900"><?= htmlspecialchars($inviteData['phone'] ?? '-') ?></p>
              </div>
              <?php if ($inviteData['company_name']): ?>
              <div>
                <p class="text-xs text-gray-500">Company</p>
                <p class="font-medium text-gray-900"><?= htmlspecialchars($inviteData['company_name']) ?></p>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <form id="passwordForm" class="space-y-6">
            <div class="grid md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Create Password <span class="text-red-500">*</span></label>
                <div class="relative">
                  <input type="password" id="password" name="password" required minlength="8"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition pr-12"
                    placeholder="Min 8 characters">
                  <button type="button" onclick="togglePassword('password')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    <svg id="password-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                  </button>
                </div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password <span class="text-red-500">*</span></label>
                <input type="password" id="confirmPassword" name="confirm_password" required
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
                  placeholder="Re-enter password">
                <p id="passwordError" class="text-red-600 text-sm mt-1 hidden"></p>
              </div>
            </div>

            <div id="formError" class="hidden bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm"></div>

            <button type="submit" id="step1Submit"
              class="w-full py-4 bg-gradient-to-r from-teal-600 to-emerald-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:from-teal-700 hover:to-emerald-700 transition-all duration-200 flex items-center justify-center gap-2">
              Continue to Agreement
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
              </svg>
            </button>
          </form>
        </div>
      </section>

      <!-- Step 2: Sales Rep Agreement -->
      <section id="step2" class="step-section hidden">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 md:p-10">
          <div class="text-center mb-8">
            <h1 class="text-3xl font-black text-gray-900 mb-2">Sales Representative Agreement</h1>
            <p class="text-gray-600">Please review and sign the agreement below</p>
          </div>

          <div id="agreementContainer" class="agreement-container border border-gray-200 rounded-xl p-6 bg-slate-50 mb-6">
            <div class="prose prose-sm max-w-none text-gray-700">
              <h2 class="text-lg font-bold text-gray-900 mb-4">SALES REPRESENTATIVE AGREEMENT</h2>
              <p class="text-sm text-gray-500 mb-4">Version 1.0 - Effective Date: January 1, 2025</p>

              <p class="mb-4">This Sales Representative Agreement ("Agreement") is entered into between CollagenDirect ("Company") and the undersigned sales representative ("Representative").</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">1. APPOINTMENT</h3>
              <p class="mb-4">Company hereby appoints Representative as an independent sales representative to promote and market Company's wound care products to healthcare providers and medical practices within the assigned territory.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">2. INDEPENDENT CONTRACTOR STATUS</h3>
              <p class="mb-4">Representative is an independent contractor and not an employee, partner, or joint venturer of Company. Representative is solely responsible for all taxes, insurance, and business expenses related to their activities under this Agreement.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">3. COMMISSION STRUCTURE</h3>
              <p class="mb-4">Representative shall receive a commission of twenty-five percent (25%) of collected revenue from orders placed by healthcare providers that Representative has successfully onboarded to the Company's platform. Commission is payable monthly, on the 15th of each month for the previous month's collections.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">4. REPRESENTATIVE RESPONSIBILITIES</h3>
              <ul class="list-disc pl-6 mb-4 space-y-2">
                <li>Promote Company's products professionally and ethically</li>
                <li>Comply with all applicable laws, regulations, and industry standards</li>
                <li>Not make false or misleading claims about products</li>
                <li>Maintain confidentiality of Company information</li>
                <li>Complete required product training</li>
                <li>Report activities through Company's sales portal</li>
              </ul>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">5. COMPANY RESPONSIBILITIES</h3>
              <ul class="list-disc pl-6 mb-4 space-y-2">
                <li>Provide product training and marketing materials</li>
                <li>Process and fulfill orders promptly</li>
                <li>Calculate and pay commissions accurately</li>
                <li>Provide access to sales tracking dashboard</li>
                <li>Handle all customer support and order issues</li>
              </ul>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">6. CONFIDENTIALITY</h3>
              <p class="mb-4">Representative agrees to maintain strict confidentiality regarding Company's business practices, customer information, pricing structures, and any other proprietary information. This obligation survives termination of this Agreement.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">7. HIPAA COMPLIANCE</h3>
              <p class="mb-4">Representative acknowledges that Company handles Protected Health Information (PHI) and agrees to comply with HIPAA regulations. Representative will sign a separate Business Associate Agreement (BAA) as required by law.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">8. NON-COMPETE</h3>
              <p class="mb-4">During the term of this Agreement and for twelve (12) months thereafter, Representative agrees not to directly sell or promote competing wound care products to physicians onboarded through this program.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">9. TERM AND TERMINATION</h3>
              <p class="mb-4">This Agreement is effective upon acceptance and continues until terminated. Either party may terminate with 30 days written notice. Commission on orders placed before termination will be paid according to the normal schedule.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">10. GOVERNING LAW</h3>
              <p class="mb-4">This Agreement is governed by the laws of the State of Texas. Any disputes shall be resolved through binding arbitration in Texas.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">11. ELECTRONIC SIGNATURE</h3>
              <p class="mb-4">The parties agree that this Agreement may be executed electronically, and that electronic signatures shall have the same legal effect as original signatures.</p>

              <div class="mt-8 pt-6 border-t border-gray-300">
                <p class="font-semibold text-gray-900">BY SIGNING BELOW, YOU ACKNOWLEDGE THAT YOU HAVE READ, UNDERSTAND, AND AGREE TO ALL TERMS AND CONDITIONS OF THIS AGREEMENT.</p>
              </div>
            </div>
          </div>

          <div id="scrollNotice" class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-amber-800 text-sm flex items-center gap-3">
            <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            <span>Please scroll to the bottom of the agreement to continue</span>
          </div>

          <div id="signatureSection" class="hidden space-y-6">
            <div class="flex items-start gap-3">
              <input type="checkbox" id="agreeTerms" class="mt-1 w-5 h-5 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
              <label for="agreeTerms" class="text-sm text-gray-700">
                I have read and agree to the terms and conditions of this Sales Representative Agreement
              </label>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Full Legal Name (E-Signature) <span class="text-red-500">*</span></label>
              <input type="text" id="signatureName"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition font-serif text-lg"
                placeholder="Type your full legal name">
              <p class="text-xs text-gray-500 mt-2">By typing your name above, you are electronically signing this agreement</p>
            </div>

            <div id="agreement2Error" class="hidden bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm"></div>

            <div class="flex gap-4">
              <button type="button" onclick="goToStep(1)" class="flex-1 py-4 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200 transition">
                Back
              </button>
              <button type="button" id="step2Submit" onclick="submitStep2()"
                class="flex-1 py-4 bg-gradient-to-r from-teal-600 to-emerald-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:from-teal-700 hover:to-emerald-700 transition-all duration-200 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                Sign & Continue
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Step 3: BAA -->
      <section id="step3" class="step-section hidden">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 md:p-10">
          <div class="text-center mb-8">
            <h1 class="text-3xl font-black text-gray-900 mb-2">Business Associate Agreement</h1>
            <p class="text-gray-600">HIPAA-required agreement for handling protected health information</p>
          </div>

          <div id="baaContainer" class="agreement-container border border-gray-200 rounded-xl p-6 bg-slate-50 mb-6">
            <div class="prose prose-sm max-w-none text-gray-700">
              <h2 class="text-lg font-bold text-gray-900 mb-4">BUSINESS ASSOCIATE AGREEMENT</h2>
              <p class="text-sm text-gray-500 mb-4">HIPAA Compliance Document - Version 1.0</p>

              <p class="mb-4">This Business Associate Agreement ("BAA") is entered into between CollagenDirect ("Covered Entity") and the undersigned ("Business Associate") as required by the Health Insurance Portability and Accountability Act of 1996 ("HIPAA") and its implementing regulations.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">1. DEFINITIONS</h3>
              <p class="mb-4">"Protected Health Information" or "PHI" means any information, whether oral or recorded in any form or medium, that: (i) relates to the past, present, or future physical or mental condition of an individual; the provision of health care to an individual; or the past, present, or future payment for the provision of health care to an individual; and (ii) identifies the individual or with respect to which there is a reasonable basis to believe the information can be used to identify the individual.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">2. OBLIGATIONS OF BUSINESS ASSOCIATE</h3>
              <p class="mb-2">Business Associate agrees to:</p>
              <ul class="list-disc pl-6 mb-4 space-y-2">
                <li>Not use or disclose PHI other than as permitted by this BAA or as required by law</li>
                <li>Use appropriate safeguards to prevent unauthorized use or disclosure of PHI</li>
                <li>Report any unauthorized use or disclosure of PHI to Covered Entity within 24 hours</li>
                <li>Ensure that any subcontractors agree to the same restrictions</li>
                <li>Make PHI available for individual access requests as required by HIPAA</li>
                <li>Make PHI available for amendment as required by HIPAA</li>
                <li>Maintain and make available information required for accounting of disclosures</li>
                <li>Comply with applicable requirements of the HIPAA Security Rule</li>
              </ul>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">3. PERMITTED USES AND DISCLOSURES</h3>
              <p class="mb-4">Business Associate may use or disclose PHI only as necessary to perform services under the Sales Representative Agreement, as required by law, or as otherwise permitted by HIPAA.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">4. SECURITY REQUIREMENTS</h3>
              <p class="mb-4">Business Associate shall implement administrative, physical, and technical safeguards that reasonably and appropriately protect the confidentiality, integrity, and availability of electronic PHI.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">5. BREACH NOTIFICATION</h3>
              <p class="mb-4">Business Associate shall notify Covered Entity of any breach of unsecured PHI within 24 hours of discovery. Notification shall include identification of each individual affected and the circumstances of the breach.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">6. TERM AND TERMINATION</h3>
              <p class="mb-4">This BAA is effective upon execution and continues until all PHI is destroyed or returned. Upon termination, Business Associate shall return or destroy all PHI in its possession.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">7. PENALTIES</h3>
              <p class="mb-4">Business Associate acknowledges that violations of HIPAA may result in civil and criminal penalties including fines up to $1.5 million per violation category per year.</p>

              <h3 class="font-bold text-gray-900 mt-6 mb-2">8. GOVERNING LAW</h3>
              <p class="mb-4">This BAA shall be governed by federal HIPAA regulations and the laws of the State of Texas.</p>

              <div class="mt-8 pt-6 border-t border-gray-300">
                <p class="font-semibold text-gray-900">BY SIGNING BELOW, YOU ACKNOWLEDGE THAT YOU HAVE READ, UNDERSTAND, AND AGREE TO COMPLY WITH ALL TERMS OF THIS BUSINESS ASSOCIATE AGREEMENT.</p>
              </div>
            </div>
          </div>

          <div id="baaScrollNotice" class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-amber-800 text-sm flex items-center gap-3">
            <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            <span>Please scroll to the bottom of the agreement to continue</span>
          </div>

          <div id="baaSignatureSection" class="hidden space-y-6">
            <div class="flex items-start gap-3">
              <input type="checkbox" id="agreeBaa" class="mt-1 w-5 h-5 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
              <label for="agreeBaa" class="text-sm text-gray-700">
                I have read and agree to the terms of this Business Associate Agreement and understand my HIPAA compliance obligations
              </label>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Full Legal Name (E-Signature) <span class="text-red-500">*</span></label>
              <input type="text" id="baaSignatureName"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition font-serif text-lg"
                placeholder="Type your full legal name">
            </div>

            <div id="baaError" class="hidden bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm"></div>

            <div class="flex gap-4">
              <button type="button" onclick="goToStep(2)" class="flex-1 py-4 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200 transition">
                Back
              </button>
              <button type="button" id="step3Submit" onclick="submitFinal()"
                class="flex-1 py-4 bg-gradient-to-r from-teal-600 to-emerald-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:from-teal-700 hover:to-emerald-700 transition-all duration-200 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                <span id="submitText">Complete Registration</span>
                <svg id="submitArrow" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Step 4: Success -->
      <section id="step4" class="step-section hidden">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 md:p-10 text-center">
          <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
          </div>
          <h1 class="text-3xl font-black text-gray-900 mb-4">Registration Complete!</h1>
          <p class="text-gray-600 text-lg mb-8">
            Thank you for completing your registration. Your application is now pending review.
          </p>

          <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-8 text-left">
            <h3 class="font-bold text-amber-800 mb-2">What's Next?</h3>
            <ul class="text-amber-700 text-sm space-y-2">
              <li class="flex items-start gap-2">
                <svg class="w-5 h-5 flex-shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span>Our team will review your application within 1-2 business days</span>
              </li>
              <li class="flex items-start gap-2">
                <svg class="w-5 h-5 flex-shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span>You'll receive an email notification once your account is approved</span>
              </li>
              <li class="flex items-start gap-2">
                <svg class="w-5 h-5 flex-shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span>Once approved, you can log in and start using your dashboard</span>
              </li>
            </ul>
          </div>

          <p class="text-sm text-gray-500 mb-6">
            Questions? Contact us at <a href="mailto:partners@collagendirect.health" class="text-teal-600 hover:underline">partners@collagendirect.health</a>
          </p>

          <a href="/login" class="inline-flex items-center gap-2 py-4 px-8 bg-gradient-to-r from-teal-600 to-emerald-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:from-teal-700 hover:to-emerald-700 transition-all duration-200">
            Go to Login
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
            </svg>
          </a>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <script>
    const token = '<?= htmlspecialchars($token) ?>';
    const csrfToken = '<?= htmlspecialchars($_SESSION['csrf']) ?>';
    let currentStep = 1;
    let formData = {
      password: '',
      rep_agreement_signature: '',
      rep_agreement_signed_at: null,
      baa_signature: '',
      baa_signed_at: null
    };

    // Toggle password visibility
    function togglePassword(inputId) {
      const input = document.getElementById(inputId);
      input.type = input.type === 'password' ? 'text' : 'password';
    }

    // Step navigation
    function goToStep(step) {
      document.querySelectorAll('.step-section').forEach(s => s.classList.add('hidden'));
      document.getElementById('step' + step).classList.remove('hidden');

      // Update indicators
      for (let i = 1; i <= 4; i++) {
        const indicator = document.getElementById('step' + i + '-indicator');
        const line = document.getElementById('line' + (i - 1));
        indicator.classList.remove('active', 'completed', 'inactive');
        if (i < step) {
          indicator.classList.add('completed');
          indicator.innerHTML = '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>';
          if (line) {
            line.classList.remove('inactive');
            line.classList.add('active');
          }
        } else if (i === step) {
          indicator.classList.add('active');
          indicator.textContent = i;
        } else {
          indicator.classList.add('inactive');
          indicator.textContent = i;
          if (line) {
            line.classList.remove('active');
            line.classList.add('inactive');
          }
        }
      }
      currentStep = step;
    }

    // Step 1: Password form
    document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
      e.preventDefault();
      const password = document.getElementById('password').value;
      const confirm = document.getElementById('confirmPassword').value;
      const errorDiv = document.getElementById('passwordError');
      const formErrorDiv = document.getElementById('formError');

      formErrorDiv.classList.add('hidden');
      errorDiv.classList.add('hidden');

      if (password.length < 8) {
        formErrorDiv.textContent = 'Password must be at least 8 characters';
        formErrorDiv.classList.remove('hidden');
        return;
      }

      if (password !== confirm) {
        errorDiv.textContent = 'Passwords do not match';
        errorDiv.classList.remove('hidden');
        return;
      }

      formData.password = password;
      goToStep(2);
    });

    // Agreement scroll detection
    const agreementContainer = document.getElementById('agreementContainer');
    const scrollNotice = document.getElementById('scrollNotice');
    const signatureSection = document.getElementById('signatureSection');

    agreementContainer?.addEventListener('scroll', function() {
      const scrolledToBottom = this.scrollHeight - this.scrollTop <= this.clientHeight + 50;
      if (scrolledToBottom) {
        scrollNotice.classList.add('hidden');
        signatureSection.classList.remove('hidden');
      }
    });

    // BAA scroll detection
    const baaContainer = document.getElementById('baaContainer');
    const baaScrollNotice = document.getElementById('baaScrollNotice');
    const baaSignatureSection = document.getElementById('baaSignatureSection');

    baaContainer?.addEventListener('scroll', function() {
      const scrolledToBottom = this.scrollHeight - this.scrollTop <= this.clientHeight + 50;
      if (scrolledToBottom) {
        baaScrollNotice.classList.add('hidden');
        baaSignatureSection.classList.remove('hidden');
      }
    });

    // Enable/disable submit buttons based on checkbox and signature
    const agreeTerms = document.getElementById('agreeTerms');
    const signatureName = document.getElementById('signatureName');
    const step2Submit = document.getElementById('step2Submit');

    function updateStep2Button() {
      step2Submit.disabled = !(agreeTerms?.checked && signatureName?.value.trim());
    }
    agreeTerms?.addEventListener('change', updateStep2Button);
    signatureName?.addEventListener('input', updateStep2Button);

    const agreeBaa = document.getElementById('agreeBaa');
    const baaSignatureName = document.getElementById('baaSignatureName');
    const step3Submit = document.getElementById('step3Submit');

    function updateStep3Button() {
      step3Submit.disabled = !(agreeBaa?.checked && baaSignatureName?.value.trim());
    }
    agreeBaa?.addEventListener('change', updateStep3Button);
    baaSignatureName?.addEventListener('input', updateStep3Button);

    // Step 2 submit
    function submitStep2() {
      formData.rep_agreement_signature = signatureName.value.trim();
      formData.rep_agreement_signed_at = new Date().toISOString();
      goToStep(3);
    }

    // Final submit
    async function submitFinal() {
      const errorDiv = document.getElementById('baaError');
      errorDiv.classList.add('hidden');

      formData.baa_signature = baaSignatureName.value.trim();
      formData.baa_signed_at = new Date().toISOString();

      const submitBtn = document.getElementById('step3Submit');
      const submitText = document.getElementById('submitText');
      const submitArrow = document.getElementById('submitArrow');

      submitBtn.disabled = true;
      submitText.textContent = 'Processing...';
      submitArrow.classList.add('animate-spin');

      try {
        const response = await fetch('/api/rep-invite-complete.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({
            token: token,
            password: formData.password,
            rep_agreement_signature: formData.rep_agreement_signature,
            rep_agreement_signed_at: formData.rep_agreement_signed_at,
            baa_signature: formData.baa_signature,
            baa_signed_at: formData.baa_signed_at
          })
        });

        const result = await response.json();

        if (result.success) {
          goToStep(4);
        } else {
          errorDiv.textContent = result.error || 'An error occurred. Please try again.';
          errorDiv.classList.remove('hidden');
          submitBtn.disabled = false;
          submitText.textContent = 'Complete Registration';
          submitArrow.classList.remove('animate-spin');
        }
      } catch (err) {
        errorDiv.textContent = 'Network error. Please check your connection and try again.';
        errorDiv.classList.remove('hidden');
        submitBtn.disabled = false;
        submitText.textContent = 'Complete Registration';
        submitArrow.classList.remove('animate-spin');
      }
    }
  </script>
</body>
</html>
