<?php
session_start();
require_once('../config.php');
require_once('sendgrid-integration.php');

if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = 'demo_user';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_campaign') {
        // In production, insert into database and send emails
        $success = true;
        $campaign_preview = [
            'name' => $_POST['campaign_name'],
            'type' => $_POST['campaign_type'],
            'template_id' => $_POST['template_id'],
            'target_count' => rand(20, 100) // Mock count
        ];
    }
}

// Fetch email templates for dropdown
$email_templates = [
    ['id' => 1, 'name' => 'Cold Outreach - Time Savings', 'type' => 'cold_outreach'],
    ['id' => 2, 'name' => 'Followup - No Response', 'type' => 'followup'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Campaign | Sales Outreach Agent</title>

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
            <div class="text-xs text-gray-500">Create New Campaign</div>
          </div>
        </div>
        <a href="index.php" class="text-sm text-gray-600 hover:text-brand-teal">← Back to Dashboard</a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <div class="max-w-5xl mx-auto px-6 py-8">

    <?php if (isset($success)): ?>
    <div class="mb-6 bg-green-50 border-2 border-green-200 rounded-xl p-6">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-green-200 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
        </div>
        <div>
          <div class="font-bold text-green-900">Campaign created successfully!</div>
          <div class="text-sm text-green-700">
            "<?php echo htmlspecialchars($campaign_preview['name']); ?>" will be sent to <?php echo $campaign_preview['target_count']; ?> leads.
          </div>
          <a href="campaigns.php" class="text-sm text-green-700 font-semibold hover:underline mt-2 inline-block">View all campaigns →</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border-2 border-gray-200 p-8">
      <h1 class="text-3xl font-black text-gray-900 mb-6">Create Email Campaign</h1>

      <form method="POST" action="" id="campaign-form">
        <input type="hidden" name="action" value="create_campaign">

        <!-- Campaign Details -->
        <div class="mb-8">
          <h2 class="text-xl font-bold text-gray-900 mb-4">Campaign Details</h2>

          <div class="space-y-6">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Campaign Name *</label>
              <input type="text" name="campaign_name" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" placeholder="e.g. Texas Podiatrists - January 2025">
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Campaign Type</label>
              <select name="campaign_type" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none">
                <option value="email">Email</option>
                <option value="sms" disabled>SMS (Coming Soon)</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Email Template *</label>
              <select name="template_id" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" onchange="previewTemplate(this.value)">
                <option value="">Select Template</option>
                <?php foreach ($email_templates as $template): ?>
                <option value="<?php echo $template['id']; ?>">
                  <?php echo htmlspecialchars($template['name']); ?> (<?php echo ucfirst(str_replace('_', ' ', $template['type'])); ?>)
                </option>
                <?php endforeach; ?>
              </select>
              <a href="templates.php" class="text-sm text-brand-teal font-semibold hover:underline mt-2 inline-block">→ Manage templates</a>
            </div>
          </div>
        </div>

        <!-- Target Audience -->
        <div class="mb-8">
          <h2 class="text-xl font-bold text-gray-900 mb-4">Target Audience</h2>

          <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-6 mb-6">
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 bg-blue-200 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
              <div>
                <div class="font-bold text-blue-900 mb-1">Targeting Filters</div>
                <div class="text-sm text-blue-800">Select criteria to filter which leads receive this campaign. Leave blank to target all leads.</div>
              </div>
            </div>
          </div>

          <div class="grid md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Lead Status</label>
              <select name="target_status" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" onchange="updateTargetCount()">
                <option value="">All Statuses</option>
                <option value="new">New Leads Only</option>
                <option value="contacted">Contacted (No Response)</option>
                <option value="nurture">Nurture</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Specialty</label>
              <select name="target_specialty" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" onchange="updateTargetCount()">
                <option value="">All Specialties</option>
                <option value="Podiatry">Podiatry</option>
                <option value="Wound Care">Wound Care</option>
                <option value="Dermatology">Dermatology</option>
                <option value="Vascular Surgery">Vascular Surgery</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">State</label>
              <select name="target_state" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" onchange="updateTargetCount()">
                <option value="">All States</option>
                <option value="TX" selected>Texas Only</option>
                <option value="CA">California</option>
                <option value="FL">Florida</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Min Monthly Volume</label>
              <input type="number" name="min_volume" min="0" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none" placeholder="e.g. 10" onchange="updateTargetCount()">
            </div>
          </div>

          <!-- Target Count Preview -->
          <div class="mt-6 bg-gradient-to-r from-brand-teal to-emerald-500 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-sm font-semibold text-teal-100 mb-1">Estimated Recipients</div>
                <div class="text-4xl font-black" id="target-count">47</div>
              </div>
              <div class="text-right">
                <div class="text-sm font-semibold text-teal-100 mb-1">Cost Estimate</div>
                <div class="text-2xl font-bold">$0.00</div>
                <div class="text-xs text-teal-100">SendGrid Free Tier</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Schedule -->
        <div class="mb-8">
          <h2 class="text-xl font-bold text-gray-900 mb-4">Schedule</h2>

          <div class="space-y-4">
            <div>
              <label class="flex items-center gap-3 cursor-pointer">
                <input type="radio" name="schedule_type" value="now" checked class="w-5 h-5 text-brand-teal">
                <div>
                  <div class="font-semibold text-gray-900">Send Immediately</div>
                  <div class="text-sm text-gray-600">Campaign will be sent as soon as you click "Launch Campaign"</div>
                </div>
              </label>
            </div>

            <div>
              <label class="flex items-center gap-3 cursor-pointer">
                <input type="radio" name="schedule_type" value="scheduled" class="w-5 h-5 text-brand-teal" onchange="toggleSchedule()">
                <div>
                  <div class="font-semibold text-gray-900">Schedule for Later</div>
                  <div class="text-sm text-gray-600">Choose a specific date and time to send</div>
                </div>
              </label>
            </div>

            <div id="schedule-fields" class="ml-8 hidden">
              <div class="grid md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
                  <input type="date" name="schedule_date" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none">
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2">Time (Central)</label>
                  <input type="time" name="schedule_time" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-brand-teal focus:outline-none">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Template Preview -->
        <div class="mb-8" id="template-preview" style="display: none;">
          <h2 class="text-xl font-bold text-gray-900 mb-4">Email Preview</h2>
          <div class="border-2 border-gray-300 rounded-xl p-6 bg-gray-50">
            <div class="mb-4">
              <div class="text-sm font-semibold text-gray-600 mb-1">Subject:</div>
              <div class="font-bold text-gray-900" id="preview-subject">Dr. {{physician_name}} - Save 6 Hours Per Week on Supply Orders</div>
            </div>
            <div class="bg-white rounded-lg p-6 border-2 border-gray-200" id="preview-body">
              <p class="text-gray-700 mb-4">Select a template to see preview...</p>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-4">
          <button type="submit" class="px-8 py-4 bg-gradient-to-r from-brand-teal to-emerald-500 text-white font-bold rounded-xl hover:shadow-xl transition">
            🚀 Launch Campaign
          </button>
          <button type="button" onclick="saveDraft()" class="px-8 py-4 bg-gray-200 text-gray-700 font-bold rounded-xl hover:bg-gray-300 transition">
            Save as Draft
          </button>
          <a href="index.php" class="px-8 py-4 text-gray-600 font-semibold hover:text-gray-900">
            Cancel
          </a>
        </div>

      </form>
    </div>

    <!-- SendGrid Setup Notice -->
    <div class="mt-8 bg-yellow-50 border-2 border-yellow-200 rounded-xl p-6">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 bg-yellow-200 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-6 h-6 text-yellow-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
        </div>
        <div>
          <div class="font-bold text-yellow-900 mb-2">SendGrid Setup Required</div>
          <div class="text-sm text-yellow-800 mb-4">
            To send campaigns, you need to configure SendGrid in <code class="bg-yellow-200 px-2 py-1 rounded">config.php</code>
          </div>
          <ol class="text-sm text-yellow-800 space-y-2 list-decimal list-inside">
            <li>Create SendGrid account at <a href="https://signup.sendgrid.com/" target="_blank" class="font-semibold underline">sendgrid.com</a></li>
            <li>Generate API key with "Mail Send" permissions</li>
            <li>Copy <code class="bg-yellow-200 px-1 rounded">config.example.php</code> to <code class="bg-yellow-200 px-1 rounded">config.php</code></li>
            <li>Add your SendGrid API key to <code class="bg-yellow-200 px-1 rounded">SENDGRID_API_KEY</code></li>
            <li>Configure sender email (<code class="bg-yellow-200 px-1 rounded">sales@collagendirect.health</code>)</li>
            <li>Setup <a href="https://docs.sendgrid.com/for-developers/tracking-events/getting-started-event-webhook" target="_blank" class="font-semibold underline">Event Webhook</a> for tracking opens/clicks</li>
          </ol>
        </div>
      </div>
    </div>

  </div>

  <script>
    function toggleSchedule() {
      const scheduleFields = document.getElementById('schedule-fields');
      const radio = document.querySelector('input[name="schedule_type"][value="scheduled"]');
      scheduleFields.style.display = radio.checked ? 'block' : 'none';
    }

    function updateTargetCount() {
      // Mock function - in production, would do AJAX call to get real count
      const count = Math.floor(Math.random() * 50) + 20;
      document.getElementById('target-count').textContent = count;
    }

    function previewTemplate(template_id) {
      if (!template_id) {
        document.getElementById('template-preview').style.display = 'none';
        return;
      }

      // Mock preview - in production, would fetch from database
      const templates = {
        '1': {
          subject: 'Dr. {{physician_name}} - Save 6 Hours Per Week on Supply Orders',
          body: '<p>Hi Dr. {{physician_name}},</p><p>I work with wound care {{specialty}} practices in {{city}} to help them reduce the time spent ordering collagen products from 20 minutes per order to just 2 minutes.</p><p><strong>Quick question:</strong> How much time does your staff currently spend on the phone ordering wound care supplies each week?</p>'
        },
        '2': {
          subject: 'Following up - {{practice_name}}',
          body: '<p>Hi Dr. {{physician_name}},</p><p>I reached out last week about helping {{practice_name}} save time on wound care supply orders.</p><p><strong>Practices using CollagenDirect see:</strong></p><ul><li>$12,000+ extra revenue per month</li><li>$36,000+ annual savings</li><li>6 hours per week freed up</li></ul>'
        }
      };

      if (templates[template_id]) {
        document.getElementById('preview-subject').textContent = templates[template_id].subject;
        document.getElementById('preview-body').innerHTML = templates[template_id].body;
        document.getElementById('template-preview').style.display = 'block';
      }
    }

    function saveDraft() {
      alert('Campaign saved as draft. You can edit and launch it later from the Campaigns page.');
      window.location.href = 'campaigns.php';
    }
  </script>

</body>
</html>
