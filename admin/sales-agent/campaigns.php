<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = 'demo_user';
}

// Check for campaign results
$campaign_results = $_SESSION['campaign_results'] ?? null;
$campaign_name = $_SESSION['campaign_name'] ?? '';

// Clear results from session after displaying
if ($campaign_results) {
    unset($_SESSION['campaign_results']);
    unset($_SESSION['campaign_name']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Campaigns | Sales Outreach Agent</title>

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
            <div class="text-xs text-gray-500">Campaigns</div>
          </div>
        </div>
        <a href="index.php" class="text-sm text-gray-600 hover:text-brand-teal">← Back to Dashboard</a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <div class="max-w-7xl mx-auto px-6 py-8">

    <?php if ($campaign_results): ?>
    <div class="mb-6 bg-green-50 border-2 border-green-200 rounded-xl p-6">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 bg-green-200 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-6 h-6 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
        </div>
        <div class="flex-1">
          <div class="font-bold text-green-900 mb-2">Campaign "<?php echo htmlspecialchars($campaign_name); ?>" sent successfully!</div>
          <div class="grid md:grid-cols-3 gap-4 mb-4">
            <div class="bg-white rounded-lg p-4 border border-green-200">
              <div class="text-2xl font-black text-green-700"><?php echo $campaign_results['sent']; ?></div>
              <div class="text-sm text-gray-600">Emails Sent</div>
            </div>
            <div class="bg-white rounded-lg p-4 border border-green-200">
              <div class="text-2xl font-black text-gray-700"><?php echo $campaign_results['total']; ?></div>
              <div class="text-sm text-gray-600">Total Targeted</div>
            </div>
            <div class="bg-white rounded-lg p-4 border border-green-200">
              <div class="text-2xl font-black <?php echo $campaign_results['failed'] > 0 ? 'text-red-600' : 'text-gray-400'; ?>">
                <?php echo $campaign_results['failed']; ?>
              </div>
              <div class="text-sm text-gray-600">Failed</div>
            </div>
          </div>

          <?php if (!empty($campaign_results['errors'])): ?>
          <details class="mt-4">
            <summary class="text-sm text-red-700 font-semibold cursor-pointer hover:underline">
              View <?php echo count($campaign_results['errors']); ?> error(s)
            </summary>
            <div class="mt-2 bg-red-50 rounded-lg p-4 text-sm text-red-800 max-h-40 overflow-y-auto">
              <?php foreach ($campaign_results['errors'] as $error): ?>
                <div class="mb-1">• <?php echo htmlspecialchars($error); ?></div>
              <?php endforeach; ?>
            </div>
          </details>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-black text-gray-900">Email Campaigns</h1>
      <a href="create-campaign.php" class="px-6 py-3 bg-gradient-to-r from-brand-teal to-emerald-500 text-white font-bold rounded-xl hover:shadow-xl transition">
        + New Campaign
      </a>
    </div>

    <!-- Campaign List -->
    <div class="bg-white rounded-xl border-2 border-gray-200 p-6">
      <p class="text-gray-600 mb-4">
        Campaign history will appear here once you've sent your first campaign.
      </p>

      <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-6">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 bg-blue-200 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <div>
            <div class="font-bold text-blue-900 mb-2">Before You Send Your First Campaign</div>
            <ol class="text-sm text-blue-800 space-y-2 list-decimal list-inside">
              <li>Add leads via "Add Lead" or "Import Leads" on dashboard</li>
              <li>Configure SendGrid in <code class="bg-blue-200 px-1 rounded">config.php</code> (see setup guide)</li>
              <li>Run database schema: <code class="bg-blue-200 px-1 rounded">mysql &lt; schema.sql</code></li>
              <li>Create a campaign and select your target audience</li>
              <li>Click "Launch Campaign" to send emails via SendGrid</li>
            </ol>

            <div class="mt-4 space-x-4">
              <a href="add-lead.php" class="text-sm font-semibold text-blue-700 hover:underline">→ Add Lead</a>
              <a href="create-campaign.php" class="text-sm font-semibold text-blue-700 hover:underline">→ Create Campaign</a>
              <a href="README.md" class="text-sm font-semibold text-blue-700 hover:underline">→ Setup Guide</a>
            </div>
          </div>
        </div>
      </div>

      <!-- Future: Campaign list table would go here once campaigns are in database -->

    </div>

  </div>

</body>
</html>
