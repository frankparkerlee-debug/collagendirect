<?php
/**
 * Sample Package Request - Public Landing Page
 *
 * Simple form for physicians to request collagen patch samples.
 * No account required - just collect info for sales follow-up.
 */
declare(strict_types=1);
session_start();

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
  <title>Request Free Samples — CollagenDirect</title>
  <meta name="description" content="Request free collagen wound care samples for your practice. Try our premium collagen patches with no obligation.">

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
              teal: '#14b8a6',
              blue: '#2a78ff',
              navy: '#0a2540'
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
  </style>
</head>
<body class="bg-gradient-to-br from-teal-50 via-white to-emerald-50 min-h-screen">

  <!-- Header -->
  <header class="bg-white/80 backdrop-blur-sm border-b border-gray-100 sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-6 py-4">
      <div class="flex items-center justify-between">
        <a href="/" class="flex items-center gap-3">
          <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-8 w-auto">
        </a>
        <a href="/" class="text-sm text-gray-600 hover:text-gray-900 transition">
          Back to Home
        </a>
      </div>
    </div>
  </header>

  <main class="max-w-4xl mx-auto px-6 py-12">
    <!-- Hero Section -->
    <div class="text-center mb-12">
      <div class="inline-flex items-center gap-2 bg-teal-100 text-teal-800 px-4 py-2 rounded-full text-sm font-medium mb-6">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M5 2a2 2 0 00-2 2v14l3.5-2 3.5 2 3.5-2 3.5 2V4a2 2 0 00-2-2H5zm2.5 3a1.5 1.5 0 100 3 1.5 1.5 0 000-3zm6.207.293a1 1 0 00-1.414 0l-6 6a1 1 0 101.414 1.414l6-6a1 1 0 000-1.414zM12.5 10a1.5 1.5 0 100 3 1.5 1.5 0 000-3z" clip-rule="evenodd"></path>
        </svg>
        Free Sample Program
      </div>
      <h1 class="text-4xl md:text-5xl font-black text-gray-900 mb-4">
        Try Our Collagen Patches
      </h1>
      <p class="text-xl text-gray-600 max-w-2xl mx-auto">
        Experience the difference with our premium wound care products. Request a free sample kit for your practice today.
      </p>
    </div>

    <div class="grid md:grid-cols-5 gap-8">
      <!-- Benefits Sidebar -->
      <div class="md:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <h3 class="font-bold text-gray-900 mb-4">Sample Kit Includes:</h3>
          <ul class="space-y-3">
            <li class="flex items-start gap-3">
              <svg class="w-5 h-5 text-teal-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
              </svg>
              <span class="text-gray-700">Collagen wound patches in multiple sizes</span>
            </li>
            <li class="flex items-start gap-3">
              <svg class="w-5 h-5 text-teal-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
              </svg>
              <span class="text-gray-700">Product information and usage guides</span>
            </li>
            <li class="flex items-start gap-3">
              <svg class="w-5 h-5 text-teal-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
              </svg>
              <span class="text-gray-700">Reimbursement information</span>
            </li>
          </ul>
        </div>

        <div class="bg-gradient-to-br from-teal-500 to-emerald-600 rounded-2xl p-6 text-white">
          <h3 class="font-bold mb-2">Why CollagenDirect?</h3>
          <ul class="space-y-2 text-teal-50 text-sm">
            <li>Premium bovine collagen wound care</li>
            <li>Direct-to-practice ordering</li>
            <li>Simplified insurance billing</li>
            <li>Dedicated support team</li>
          </ul>
        </div>

        <div class="text-center text-sm text-gray-500">
          <p>Questions? Contact us at</p>
          <a href="mailto:samples@collagendirect.health" class="text-teal-600 font-medium hover:underline">samples@collagendirect.health</a>
        </div>
      </div>

      <!-- Request Form -->
      <div class="md:col-span-3">
        <div id="formSection" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
          <h2 class="text-2xl font-bold text-gray-900 mb-6">Request Your Free Samples</h2>

          <form id="sampleRequestForm" class="space-y-5">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                <input type="text" id="firstName" name="first_name" required
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
                  placeholder="John">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                <input type="text" id="lastName" name="last_name" required
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
                  placeholder="Smith">
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
              <input type="email" id="email" name="email" required
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
                placeholder="doctor@practice.com">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
              <input type="tel" id="phone" name="phone" required
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
                placeholder="(555) 123-4567">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Practice Name</label>
              <input type="text" id="practiceName" name="practice_name"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
                placeholder="Your Practice Name">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Specialty</label>
              <select id="specialty" name="specialty"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition bg-white">
                <option value="">Select your specialty...</option>
                <option value="wound_care">Wound Care</option>
                <option value="podiatry">Podiatry</option>
                <option value="dermatology">Dermatology</option>
                <option value="surgery">Surgery</option>
                <option value="primary_care">Primary Care</option>
                <option value="vascular">Vascular</option>
                <option value="plastic_surgery">Plastic Surgery</option>
                <option value="orthopedics">Orthopedics</option>
                <option value="other">Other</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">NPI Number</label>
              <input type="text" id="npi" name="npi" maxlength="10" pattern="[0-9]{10}"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
                placeholder="10-digit NPI (optional)">
            </div>

            <div class="border-t pt-5 mt-5">
              <h3 class="text-sm font-medium text-gray-700 mb-3">Shipping Address <span class="text-red-500">*</span></h3>
              <div class="space-y-4">
                <div>
                  <input type="text" id="shipAddress" name="ship_address" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
                    placeholder="Street Address">
                </div>
                <div class="grid grid-cols-6 gap-4">
                  <div class="col-span-3">
                    <input type="text" id="shipCity" name="ship_city" required
                      class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
                      placeholder="City">
                  </div>
                  <div class="col-span-1">
                    <select id="shipState" name="ship_state" required
                      class="w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition bg-white text-sm">
                      <option value="">State</option>
                      <option value="AL">AL</option><option value="AK">AK</option><option value="AZ">AZ</option>
                      <option value="AR">AR</option><option value="CA">CA</option><option value="CO">CO</option>
                      <option value="CT">CT</option><option value="DE">DE</option><option value="FL">FL</option>
                      <option value="GA">GA</option><option value="HI">HI</option><option value="ID">ID</option>
                      <option value="IL">IL</option><option value="IN">IN</option><option value="IA">IA</option>
                      <option value="KS">KS</option><option value="KY">KY</option><option value="LA">LA</option>
                      <option value="ME">ME</option><option value="MD">MD</option><option value="MA">MA</option>
                      <option value="MI">MI</option><option value="MN">MN</option><option value="MS">MS</option>
                      <option value="MO">MO</option><option value="MT">MT</option><option value="NE">NE</option>
                      <option value="NV">NV</option><option value="NH">NH</option><option value="NJ">NJ</option>
                      <option value="NM">NM</option><option value="NY">NY</option><option value="NC">NC</option>
                      <option value="ND">ND</option><option value="OH">OH</option><option value="OK">OK</option>
                      <option value="OR">OR</option><option value="PA">PA</option><option value="RI">RI</option>
                      <option value="SC">SC</option><option value="SD">SD</option><option value="TN">TN</option>
                      <option value="TX">TX</option><option value="UT">UT</option><option value="VT">VT</option>
                      <option value="VA">VA</option><option value="WA">WA</option><option value="WV">WV</option>
                      <option value="WI">WI</option><option value="WY">WY</option><option value="DC">DC</option>
                    </select>
                  </div>
                  <div class="col-span-2">
                    <input type="text" id="shipZip" name="ship_zip" required maxlength="10"
                      class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition"
                      placeholder="ZIP">
                  </div>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">How did you hear about us?</label>
              <select id="howHeard" name="how_heard"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition bg-white">
                <option value="">Select...</option>
                <option value="colleague">Colleague referral</option>
                <option value="conference">Conference/Trade show</option>
                <option value="google">Google search</option>
                <option value="sales_rep">Sales representative</option>
                <option value="other">Other</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
              <textarea id="notes" name="notes" rows="2"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition resize-none"
                placeholder="Any specific products or sizes you're interested in?"></textarea>
            </div>

            <div id="formError" class="hidden bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm"></div>

            <button type="submit" id="submitBtn"
              class="w-full py-4 bg-gradient-to-r from-teal-600 to-emerald-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:from-teal-700 hover:to-emerald-700 transition-all duration-200 flex items-center justify-center gap-2">
              Request Free Samples
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
              </svg>
            </button>

            <p class="text-xs text-gray-500 text-center">
              By submitting, you agree to be contacted about our products. We respect your privacy.
            </p>
          </form>
        </div>

        <!-- Success Message (hidden by default) -->
        <div id="successSection" class="hidden bg-white rounded-2xl shadow-sm border border-gray-200 p-8 text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-teal-500 to-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-gray-900 mb-3">Request Received!</h2>
          <p class="text-gray-600 mb-6">
            Thank you for your interest in CollagenDirect. Our team will review your request and ship your sample kit soon.
          </p>
          <div class="bg-teal-50 rounded-xl p-4 mb-6">
            <p class="text-sm text-teal-800">
              <strong>What's next?</strong> You'll receive a confirmation email shortly. A member of our team may reach out to answer any questions about our products.
            </p>
          </div>
          <a href="/" class="inline-flex items-center gap-2 text-teal-600 font-medium hover:underline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Return to Homepage
          </a>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="border-t border-gray-100 mt-16 py-8">
    <div class="max-w-4xl mx-auto px-6 text-center text-sm text-gray-500">
      <p>&copy; <?= date('Y') ?> CollagenDirect. All rights reserved.</p>
    </div>
  </footer>

  <script>
    const csrfToken = '<?= htmlspecialchars($_SESSION['csrf']) ?>';

    document.getElementById('sampleRequestForm').addEventListener('submit', async function(e) {
      e.preventDefault();

      const formError = document.getElementById('formError');
      const submitBtn = document.getElementById('submitBtn');

      // Reset error
      formError.classList.add('hidden');

      // Gather form data
      const data = {
        first_name: document.getElementById('firstName').value.trim(),
        last_name: document.getElementById('lastName').value.trim(),
        email: document.getElementById('email').value.trim(),
        phone: document.getElementById('phone').value.trim(),
        practice_name: document.getElementById('practiceName').value.trim(),
        specialty: document.getElementById('specialty').value,
        npi: document.getElementById('npi').value.trim(),
        ship_address: document.getElementById('shipAddress').value.trim(),
        ship_city: document.getElementById('shipCity').value.trim(),
        ship_state: document.getElementById('shipState').value,
        ship_zip: document.getElementById('shipZip').value.trim(),
        how_heard: document.getElementById('howHeard').value,
        notes: document.getElementById('notes').value.trim()
      };

      // Basic validation
      if (!data.first_name || !data.last_name || !data.email || !data.phone) {
        formError.textContent = 'Please fill in all required fields.';
        formError.classList.remove('hidden');
        return;
      }

      if (!data.ship_address || !data.ship_city || !data.ship_state || !data.ship_zip) {
        formError.textContent = 'Please provide a complete shipping address.';
        formError.classList.remove('hidden');
        return;
      }

      // Email validation
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
        formError.textContent = 'Please enter a valid email address.';
        formError.classList.remove('hidden');
        return;
      }

      // Show loading
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Submitting...';

      try {
        const response = await fetch('/api/sample-request.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify(data)
        });

        const result = await response.json();

        if (response.ok && result.success) {
          // Show success message
          document.getElementById('formSection').classList.add('hidden');
          document.getElementById('successSection').classList.remove('hidden');
          window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
          formError.textContent = result.error || 'An error occurred. Please try again.';
          formError.classList.remove('hidden');
          submitBtn.disabled = false;
          submitBtn.innerHTML = 'Request Free Samples <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>';
        }
      } catch (err) {
        formError.textContent = 'Network error. Please check your connection and try again.';
        formError.classList.remove('hidden');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Request Free Samples <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>';
      }
    });
  </script>

</body>
</html>
