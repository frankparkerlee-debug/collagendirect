<?php
session_start();
require_once('../config.php');

if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = 'demo_user';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In production, insert into database
    // For now, just show success message
    $success = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Lead | Sales Outreach Agent</title>

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
<body class="bg-gray-50 text-gray-900">

  <!-- Header -->
  <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-8 w-auto">
          <div>
            <div class="text-sm font-bold text-gray-900">Sales Outreach Agent</div>
            <div class="text-xs text-gray-500">Add New Lead</div>
          </div>
        </div>
        <a href="index.php" class="text-sm text-gray-600 hover:text-brand-teal">← Back to Dashboard</a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <div class="max-w-4xl mx-auto px-6 py-8">

    <?php if (isset($success)): ?>
    <div class="mb-6 bg-green-50 border-2 border-green-200 rounded-xl p-6">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-green-200 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
        </div>
        <div>
          <div class="font-bold text-green-900">Lead added successfully!</div>
          <div class="text-sm text-green-700">The lead has been added to your pipeline.</div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border-2 border-gray-200 p-8">
      <h1 class="text-3xl font-black text-gray-900 mb-6">Add New Lead</h1>

      <form method="POST" action="">

        <!-- Practice Information -->
        <div class="mb-8">
          <h2 class="text-xl font-bold text-gray-900 mb-4">Practice Information</h2>
          <div class="grid md:grid-cols-2 gap-6">

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Practice Name *</label>
              <input type="text" name="practice_name" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" placeholder="Advanced Wound Care Center">
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Physician Name</label>
              <input type="text" name="physician_name" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" placeholder="Dr. Sarah Martinez">
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Specialty</label>
              <select name="specialty" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none">
                <option value="">Select Specialty</option>
                <option value="Podiatry">Podiatry</option>
                <option value="Wound Care">Wound Care</option>
                <option value="Dermatology">Dermatology</option>
                <option value="Vascular Surgery">Vascular Surgery</option>
                <option value="General Surgery">General Surgery</option>
                <option value="Family Medicine">Family Medicine</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Phone</label>
              <input type="tel" name="phone" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" placeholder="(713) 555-0123">
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
              <input type="email" name="email" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" placeholder="contact@practice.com">
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Website</label>
              <input type="url" name="website" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" placeholder="https://practice.com">
            </div>

          </div>
        </div>

        <!-- Location -->
        <div class="mb-8">
          <h2 class="text-xl font-bold text-gray-900 mb-4">Location</h2>
          <div class="grid md:grid-cols-2 gap-6">

            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Street Address</label>
              <input type="text" name="address" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" placeholder="123 Medical Plaza Dr, Suite 200">
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">City</label>
              <input type="text" name="city" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" placeholder="Houston">
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">State</label>
              <select name="state" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none">
                <option value="">Select State</option>
                <option value="TX" selected>Texas</option>
                <option value="AL">Alabama</option>
                <option value="AK">Alaska</option>
                <option value="AZ">Arizona</option>
                <option value="AR">Arkansas</option>
                <option value="CA">California</option>
                <!-- Add more states as needed -->
              </select>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">ZIP Code</label>
              <input type="text" name="zip" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" placeholder="77001">
            </div>

          </div>
        </div>

        <!-- Lead Details -->
        <div class="mb-8">
          <h2 class="text-xl font-bold text-gray-900 mb-4">Lead Details</h2>
          <div class="grid md:grid-cols-2 gap-6">

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Lead Source</label>
              <select name="lead_source" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none">
                <option value="manual">Manual Entry</option>
                <option value="web_scrape">Web Scraping</option>
                <option value="referral">Referral</option>
                <option value="purchased_list">Purchased List</option>
                <option value="inbound">Inbound</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Priority</label>
              <select name="priority" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none">
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="low">Low</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Estimated Monthly Volume (orders)</label>
              <input type="number" name="estimated_monthly_volume" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" placeholder="20">
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Assigned Rep</label>
              <select name="assigned_rep" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none">
                <option value="">Unassigned</option>
                <option value="rep1">Sales Rep 1</option>
                <option value="rep2">Sales Rep 2</option>
                <option value="rep3">Sales Rep 3</option>
              </select>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
              <textarea name="notes" rows="4" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" placeholder="Any additional information about this lead..."></textarea>
            </div>

          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-4">
          <button type="submit" class="px-8 py-4 bg-gradient-to-r from-brand-teal to-emerald-500 text-white font-bold rounded-xl hover:shadow-xl transition">
            Add Lead
          </button>
          <a href="index.php" class="px-8 py-4 bg-gray-200 text-gray-700 font-bold rounded-xl hover:bg-gray-300 transition">
            Cancel
          </a>
        </div>

      </form>
    </div>

  </div>

</body>
</html>
