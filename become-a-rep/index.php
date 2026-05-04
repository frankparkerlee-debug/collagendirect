<?php
declare(strict_types=1);

// Match session config used by api/db.php so the CSRF token survives the POST
if (session_status() === PHP_SESSION_NONE) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  ini_set('session.gc_maxlifetime', (string)(60*60*24*30));
  ini_set('session.cookie_lifetime', (string)(60*60*24*30));
  session_set_cookie_params([
    'lifetime' => 60*60*24*30,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Become a Sales Rep — CollagenDirect Partner Program</title>
  <meta name="description" content="Apply to become a CollagenDirect sales representative. Join our partner program and earn 15% commission on wound care products.">

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

    /* Agreement scroll detection */
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
        <a href="/partners" class="text-sm text-gray-600 hover:text-gray-900 transition">
          <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
          </svg>
          Back to Partner Info
        </a>
      </div>
    </div>
  </header>

  <main class="max-w-4xl mx-auto px-6 py-12">
    <!-- Progress Indicator -->
    <div class="mb-12">
      <div class="flex items-center justify-center">
        <div class="flex items-center">
          <!-- Step 1 -->
          <div class="flex flex-col items-center">
            <div id="step1-indicator" class="step-indicator active w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all">1</div>
            <span class="text-xs mt-2 font-medium text-gray-600">Account</span>
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

    <!-- Step 1: Account Information -->
    <section id="step1" class="step-section">
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 md:p-10">
        <div class="text-center mb-8">
          <h1 class="text-3xl font-black text-gray-900 mb-2">Create Your Account</h1>
          <p class="text-gray-600">Start your journey as a CollagenDirect sales partner</p>
        </div>

        <form id="accountForm" class="space-y-6">
          <div class="grid md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
              <input type="text" id="firstName" name="first_name" required
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
                placeholder="John">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Last Name <span class="text-red-500">*</span></label>
              <input type="text" id="lastName" name="last_name" required
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
                placeholder="Smith">
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address <span class="text-red-500">*</span></label>
            <input type="email" id="email" name="email" required
              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
              placeholder="john@example.com">
            <p id="emailError" class="text-red-600 text-sm mt-1 hidden"></p>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number <span class="text-red-500">*</span></label>
            <input type="tel" id="phone" name="phone" required
              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
              placeholder="(555) 123-4567">
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Company Name <span class="text-gray-400 font-normal">(optional)</span></label>
            <input type="text" id="companyName" name="company_name"
              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
              placeholder="Your Company LLC">
          </div>

          <div class="grid md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Password <span class="text-red-500">*</span></label>
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

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">How did you hear about us? <span class="text-gray-400 font-normal">(optional)</span></label>
            <select id="howHeard" name="how_heard"
              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition bg-white">
              <option value="">Select an option...</option>
              <option value="linkedin">LinkedIn</option>
              <option value="google">Google Search</option>
              <option value="referral">Referral from colleague</option>
              <option value="conference">Trade show / Conference</option>
              <option value="industry_contact">Industry contact</option>
              <option value="other">Other</option>
            </select>
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

        <p class="text-center text-sm text-gray-500 mt-6">
          Already have an account? <a href="/login" class="text-teal-600 font-semibold hover:underline">Sign in</a>
        </p>
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
            <p class="mb-4">Representative shall receive a commission of <strong>fifteen percent (15%)</strong> of collected revenue from orders placed by healthcare providers that Representative has successfully onboarded to the Company's platform. Commission is payable monthly, on the 15th of each month for the previous month's collections.</p>

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

            <h3 class="font-bold text-gray-900 mt-6 mb-2">8. TERM AND TERMINATION</h3>
            <p class="mb-4">This Agreement is effective upon acceptance and continues until terminated. Either party may terminate with 30 days written notice. Commission on orders placed before termination will be paid according to the normal schedule.</p>

            <h3 class="font-bold text-gray-900 mt-6 mb-2">9. GOVERNING LAW</h3>
            <p class="mb-4">This Agreement is governed by the laws of the State of Texas. Any disputes shall be resolved through binding arbitration in Texas.</p>

            <h3 class="font-bold text-gray-900 mt-6 mb-2">10. ELECTRONIC SIGNATURE</h3>
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
            <button type="button" id="step3Submit" onclick="submitStep3()"
              class="flex-1 py-4 bg-gradient-to-r from-teal-600 to-emerald-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:from-teal-700 hover:to-emerald-700 transition-all duration-200 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
              Sign & Submit Application
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Step 4: Confirmation -->
    <section id="step4" class="step-section hidden">
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 md:p-10 text-center">
        <div class="w-20 h-20 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-full flex items-center justify-center mx-auto mb-6">
          <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
        </div>

        <h1 class="text-3xl font-black text-gray-900 mb-4">Application Submitted!</h1>
        <p class="text-lg text-gray-600 mb-8 max-w-lg mx-auto">
          Thank you for applying to become a CollagenDirect sales representative. Your application has been submitted and is under review.
        </p>

        <div class="bg-slate-50 rounded-xl p-6 mb-8 max-w-md mx-auto">
          <h3 class="font-bold text-gray-900 mb-4">What happens next?</h3>
          <div class="space-y-4 text-left">
            <div class="flex items-start gap-3">
              <div class="w-6 h-6 bg-teal-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                <span class="text-teal-600 font-bold text-xs">1</span>
              </div>
              <p class="text-sm text-gray-600">Our team will review your application within 1-2 business days</p>
            </div>
            <div class="flex items-start gap-3">
              <div class="w-6 h-6 bg-teal-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                <span class="text-teal-600 font-bold text-xs">2</span>
              </div>
              <p class="text-sm text-gray-600">You'll receive an email notification once your account is approved</p>
            </div>
            <div class="flex items-start gap-3">
              <div class="w-6 h-6 bg-teal-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                <span class="text-teal-600 font-bold text-xs">3</span>
              </div>
              <p class="text-sm text-gray-600">Once approved, you can access your sales rep dashboard and start onboarding physicians</p>
            </div>
          </div>
        </div>

        <p class="text-sm text-gray-500 mb-6">
          Questions? Contact us at <a href="mailto:partners@collagendirect.health" class="text-teal-600 font-semibold hover:underline">partners@collagendirect.health</a>
        </p>

        <a href="/" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-gradient-to-r from-teal-600 to-emerald-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200">
          Return to Homepage
        </a>
      </div>
    </section>
  </main>

  <script>
    // State management
    const state = {
      step: 1,
      accountData: {},
      agreementSigned: false,
      baaSigned: false,
      userId: null
    };

    // CSRF token
    const csrfToken = '<?= htmlspecialchars($_SESSION['csrf']) ?>';

    // Step navigation
    function goToStep(step) {
      // Hide all sections
      document.querySelectorAll('.step-section').forEach(s => s.classList.add('hidden'));

      // Show target section
      document.getElementById(`step${step}`).classList.remove('hidden');

      // Update indicators
      for (let i = 1; i <= 4; i++) {
        const indicator = document.getElementById(`step${i}-indicator`);
        const line = document.getElementById(`line${i}`);

        if (i < step) {
          indicator.classList.remove('active', 'inactive');
          indicator.classList.add('completed');
          indicator.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>';
          if (line) {
            line.classList.remove('inactive');
            line.classList.add('active');
          }
        } else if (i === step) {
          indicator.classList.remove('completed', 'inactive');
          indicator.classList.add('active');
          indicator.textContent = i;
          if (line) {
            line.classList.remove('active');
            line.classList.add('inactive');
          }
        } else {
          indicator.classList.remove('active', 'completed');
          indicator.classList.add('inactive');
          indicator.textContent = i;
          if (line) {
            line.classList.remove('active');
            line.classList.add('inactive');
          }
        }
      }

      state.step = step;
      window.scrollTo(0, 0);
    }

    // Toggle password visibility
    function togglePassword(fieldId) {
      const field = document.getElementById(fieldId);
      field.type = field.type === 'password' ? 'text' : 'password';
    }

    // Validate email format
    function validateEmail(email) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Check if email exists
    async function checkEmailExists(email) {
      try {
        const res = await fetch('/api/check-email.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ email })
        });
        const data = await res.json();
        return data.exists;
      } catch (e) {
        return false;
      }
    }

    // Step 1: Account form submission
    document.getElementById('accountForm').addEventListener('submit', async function(e) {
      e.preventDefault();

      const firstName = document.getElementById('firstName').value.trim();
      const lastName = document.getElementById('lastName').value.trim();
      const email = document.getElementById('email').value.trim();
      const phone = document.getElementById('phone').value.trim();
      const companyName = document.getElementById('companyName').value.trim();
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirmPassword').value;
      const howHeard = document.getElementById('howHeard').value;

      const formError = document.getElementById('formError');
      const emailError = document.getElementById('emailError');
      const passwordError = document.getElementById('passwordError');

      // Reset errors
      formError.classList.add('hidden');
      emailError.classList.add('hidden');
      passwordError.classList.add('hidden');

      // Validation
      if (!firstName || !lastName || !email || !phone || !password || !confirmPassword) {
        formError.textContent = 'Please fill in all required fields.';
        formError.classList.remove('hidden');
        return;
      }

      if (!validateEmail(email)) {
        emailError.textContent = 'Please enter a valid email address.';
        emailError.classList.remove('hidden');
        return;
      }

      if (password.length < 8) {
        passwordError.textContent = 'Password must be at least 8 characters.';
        passwordError.classList.remove('hidden');
        return;
      }

      if (password !== confirmPassword) {
        passwordError.textContent = 'Passwords do not match.';
        passwordError.classList.remove('hidden');
        return;
      }

      // Check if email exists
      const submitBtn = document.getElementById('step1Submit');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Checking...';

      const exists = await checkEmailExists(email);

      if (exists) {
        emailError.textContent = 'This email is already registered. Please sign in or use a different email.';
        emailError.classList.remove('hidden');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Continue to Agreement <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>';
        return;
      }

      // Save data and proceed
      state.accountData = { firstName, lastName, email, phone, companyName, password, howHeard };

      // Pre-fill signature fields
      document.getElementById('signatureName').value = `${firstName} ${lastName}`;
      document.getElementById('baaSignatureName').value = `${firstName} ${lastName}`;

      submitBtn.disabled = false;
      submitBtn.innerHTML = 'Continue to Agreement <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>';

      goToStep(2);
    });

    // Agreement scroll detection
    const agreementContainer = document.getElementById('agreementContainer');
    const scrollNotice = document.getElementById('scrollNotice');
    const signatureSection = document.getElementById('signatureSection');

    agreementContainer.addEventListener('scroll', function() {
      const scrolledToBottom = this.scrollHeight - this.scrollTop <= this.clientHeight + 50;
      if (scrolledToBottom) {
        scrollNotice.classList.add('hidden');
        signatureSection.classList.remove('hidden');
      }
    });

    // Enable/disable step 2 submit based on checkbox and signature
    document.getElementById('agreeTerms').addEventListener('change', updateStep2Button);
    document.getElementById('signatureName').addEventListener('input', updateStep2Button);

    function updateStep2Button() {
      const agreed = document.getElementById('agreeTerms').checked;
      const signed = document.getElementById('signatureName').value.trim().length > 0;
      document.getElementById('step2Submit').disabled = !(agreed && signed);
    }

    // Step 2 submission
    function submitStep2() {
      const agreed = document.getElementById('agreeTerms').checked;
      const signature = document.getElementById('signatureName').value.trim();
      const errorEl = document.getElementById('agreement2Error');

      if (!agreed || !signature) {
        errorEl.textContent = 'Please agree to the terms and provide your signature.';
        errorEl.classList.remove('hidden');
        return;
      }

      state.agreementSigned = true;
      state.agreementSignature = signature;
      state.agreementSignedAt = new Date().toISOString();

      goToStep(3);
    }

    // BAA scroll detection
    const baaContainer = document.getElementById('baaContainer');
    const baaScrollNotice = document.getElementById('baaScrollNotice');
    const baaSignatureSection = document.getElementById('baaSignatureSection');

    baaContainer.addEventListener('scroll', function() {
      const scrolledToBottom = this.scrollHeight - this.scrollTop <= this.clientHeight + 50;
      if (scrolledToBottom) {
        baaScrollNotice.classList.add('hidden');
        baaSignatureSection.classList.remove('hidden');
      }
    });

    // Enable/disable step 3 submit
    document.getElementById('agreeBaa').addEventListener('change', updateStep3Button);
    document.getElementById('baaSignatureName').addEventListener('input', updateStep3Button);

    function updateStep3Button() {
      const agreed = document.getElementById('agreeBaa').checked;
      const signed = document.getElementById('baaSignatureName').value.trim().length > 0;
      document.getElementById('step3Submit').disabled = !(agreed && signed);
    }

    // Step 3 submission - final submit
    async function submitStep3() {
      const agreed = document.getElementById('agreeBaa').checked;
      const signature = document.getElementById('baaSignatureName').value.trim();
      const errorEl = document.getElementById('baaError');
      const submitBtn = document.getElementById('step3Submit');

      if (!agreed || !signature) {
        errorEl.textContent = 'Please agree to the BAA and provide your signature.';
        errorEl.classList.remove('hidden');
        return;
      }

      state.baaSigned = true;
      state.baaSignature = signature;
      state.baaSignedAt = new Date().toISOString();

      // Show loading
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Submitting...';

      try {
        // Refresh CSRF token right before submission to handle long form fills
        let activeCsrfToken = csrfToken;
        try {
          const csrfRes = await fetch('/api/csrf.php', { credentials: 'same-origin' });
          if (csrfRes.ok) {
            const csrfData = await csrfRes.json();
            if (csrfData.csrfToken) activeCsrfToken = csrfData.csrfToken;
          }
        } catch (e) { /* fall back to page token */ }

        const response = await fetch('/api/rep-signup.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': activeCsrfToken
          },
          body: JSON.stringify({
            // Account data
            first_name: state.accountData.firstName,
            last_name: state.accountData.lastName,
            email: state.accountData.email,
            phone: state.accountData.phone,
            company_name: state.accountData.companyName,
            password: state.accountData.password,
            how_heard: state.accountData.howHeard,
            // Agreement signatures
            rep_agreement_signature: state.agreementSignature,
            rep_agreement_signed_at: state.agreementSignedAt,
            baa_signature: state.baaSignature,
            baa_signed_at: state.baaSignedAt
          })
        });

        const data = await response.json();

        if (response.ok && data.success) {
          goToStep(4);
        } else {
          errorEl.textContent = data.error || 'An error occurred. Please try again.';
          errorEl.classList.remove('hidden');
          submitBtn.disabled = false;
          submitBtn.innerHTML = 'Sign & Submit Application <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>';
        }
      } catch (err) {
        errorEl.textContent = 'Network error. Please check your connection and try again.';
        errorEl.classList.remove('hidden');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Sign & Submit Application <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>';
      }
    }
  </script>

</body>
</html>
