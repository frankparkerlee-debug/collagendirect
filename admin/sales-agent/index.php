<?php
session_start();
require_once('../config.php');

// Check if user is logged in (you'll need to implement your auth system)
// For now, we'll use a simple check
if (!isset($_SESSION['admin_logged_in'])) {
    // Temporary bypass for development
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = 'demo_user';
}

// Get dashboard stats
$stats_query = "
    SELECT
        COUNT(*) as total_leads,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_leads,
        SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) as contacted_leads,
        SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified_leads,
        SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) as registered_leads,
        SUM(CASE WHEN next_followup_date = CURDATE() THEN 1 ELSE 0 END) as followups_today
    FROM leads
";

$campaigns_query = "
    SELECT
        COUNT(*) as total_campaigns,
        SUM(total_sent) as total_outreach,
        AVG(CASE WHEN total_sent > 0 THEN (total_opened / total_sent * 100) ELSE 0 END) as avg_open_rate,
        AVG(CASE WHEN total_sent > 0 THEN (total_replied / total_sent * 100) ELSE 0 END) as avg_reply_rate
    FROM outreach_campaigns
    WHERE status = 'active'
";

// For demo purposes, we'll use mock data if DB not set up
$stats = [
    'total_leads' => 247,
    'new_leads' => 52,
    'contacted_leads' => 98,
    'qualified_leads' => 45,
    'registered_leads' => 23,
    'followups_today' => 12
];

$campaign_stats = [
    'total_campaigns' => 5,
    'total_outreach' => 1243,
    'avg_open_rate' => 32.5,
    'avg_reply_rate' => 8.7
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales Outreach Agent | CollagenDirect</title>

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
            <div class="text-xs text-gray-500">Automated Lead Generation & Outreach</div>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <a href="../" class="text-sm text-gray-600 hover:text-brand-teal">← Back to Admin</a>
          <span class="text-sm text-gray-600">Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_user']); ?></strong></span>
        </div>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <div class="max-w-7xl mx-auto px-6 py-8">

    <!-- Stats Overview -->
    <div class="mb-8">
      <h1 class="text-3xl font-black text-gray-900 mb-6">Sales Dashboard</h1>

      <div class="grid md:grid-cols-2 lg:grid-cols-6 gap-6">
        <!-- Total Leads -->
        <div class="bg-white rounded-xl p-6 border-2 border-gray-200">
          <div class="text-sm font-semibold text-gray-600 mb-2">Total Leads</div>
          <div class="text-3xl font-black text-gray-900"><?php echo number_format($stats['total_leads']); ?></div>
        </div>

        <!-- New Leads -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white">
          <div class="text-sm font-semibold text-blue-100 mb-2">New Leads</div>
          <div class="text-3xl font-black"><?php echo $stats['new_leads']; ?></div>
        </div>

        <!-- Contacted -->
        <div class="bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl p-6 text-white">
          <div class="text-sm font-semibold text-yellow-100 mb-2">Contacted</div>
          <div class="text-3xl font-black"><?php echo $stats['contacted_leads']; ?></div>
        </div>

        <!-- Qualified -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white">
          <div class="text-sm font-semibold text-purple-100 mb-2">Qualified</div>
          <div class="text-3xl font-black"><?php echo $stats['qualified_leads']; ?></div>
        </div>

        <!-- Registered -->
        <div class="bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-6 text-white">
          <div class="text-sm font-semibold text-green-100 mb-2">Registered</div>
          <div class="text-3xl font-black"><?php echo $stats['registered_leads']; ?></div>
        </div>

        <!-- Follow-ups Today -->
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl p-6 text-white">
          <div class="text-sm font-semibold text-red-100 mb-2">Follow-ups Today</div>
          <div class="text-3xl font-black"><?php echo $stats['followups_today']; ?></div>
        </div>
      </div>
    </div>

    <!-- Campaign Performance -->
    <div class="mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">Campaign Performance</h2>
      <div class="bg-white rounded-xl p-6 border-2 border-gray-200">
        <div class="grid md:grid-cols-4 gap-6">
          <div>
            <div class="text-sm font-semibold text-gray-600 mb-1">Active Campaigns</div>
            <div class="text-2xl font-black text-gray-900"><?php echo $campaign_stats['total_campaigns']; ?></div>
          </div>
          <div>
            <div class="text-sm font-semibold text-gray-600 mb-1">Total Outreach</div>
            <div class="text-2xl font-black text-gray-900"><?php echo number_format($campaign_stats['total_outreach']); ?></div>
          </div>
          <div>
            <div class="text-sm font-semibold text-gray-600 mb-1">Avg Open Rate</div>
            <div class="text-2xl font-black text-brand-teal"><?php echo number_format($campaign_stats['avg_open_rate'], 1); ?>%</div>
          </div>
          <div>
            <div class="text-sm font-semibold text-gray-600 mb-1">Avg Reply Rate</div>
            <div class="text-2xl font-black text-brand-teal"><?php echo number_format($campaign_stats['avg_reply_rate'], 1); ?>%</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">Quick Actions</h2>
      <div class="grid md:grid-cols-4 gap-6">

        <a href="add-lead.php" class="group bg-gradient-to-br from-brand-teal to-emerald-500 rounded-xl p-6 text-white hover:shadow-xl transition">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
              </svg>
            </div>
            <div>
              <div class="font-bold">Add Lead</div>
              <div class="text-sm text-teal-100">Manual entry</div>
            </div>
          </div>
        </a>

        <a href="import-leads.php" class="group bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white hover:shadow-xl transition">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
              </svg>
            </div>
            <div>
              <div class="font-bold">Import Leads</div>
              <div class="text-sm text-blue-100">CSV upload</div>
            </div>
          </div>
        </a>

        <a href="create-campaign.php" class="group bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white hover:shadow-xl transition">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
              </svg>
            </div>
            <div>
              <div class="font-bold">New Campaign</div>
              <div class="text-sm text-purple-100">Email/SMS</div>
            </div>
          </div>
        </a>

        <a href="analytics.php" class="group bg-gradient-to-br from-orange-500 to-red-500 rounded-xl p-6 text-white hover:shadow-xl transition">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
              </svg>
            </div>
            <div>
              <div class="font-bold">Analytics</div>
              <div class="text-sm text-orange-100">Reports</div>
            </div>
          </div>
        </a>

      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="mb-6">
      <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-8">
          <a href="index.php" class="border-b-2 border-brand-teal py-4 px-1 text-sm font-semibold text-brand-teal">
            Dashboard
          </a>
          <a href="leads.php" class="border-b-2 border-transparent py-4 px-1 text-sm font-semibold text-gray-600 hover:text-gray-900 hover:border-gray-300">
            All Leads
          </a>
          <a href="campaigns.php" class="border-b-2 border-transparent py-4 px-1 text-sm font-semibold text-gray-600 hover:text-gray-900 hover:border-gray-300">
            Campaigns
          </a>
          <a href="templates.php" class="border-b-2 border-transparent py-4 px-1 text-sm font-semibold text-gray-600 hover:text-gray-900 hover:border-gray-300">
            Templates
          </a>
          <a href="settings.php" class="border-b-2 border-transparent py-4 px-1 text-sm font-semibold text-gray-600 hover:text-gray-900 hover:border-gray-300">
            Settings
          </a>
        </nav>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">Recent Activity</h2>
      <div class="bg-white rounded-xl border-2 border-gray-200 overflow-hidden">
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="text-left p-4 text-sm font-semibold text-gray-700">Lead</th>
              <th class="text-left p-4 text-sm font-semibold text-gray-700">Activity</th>
              <th class="text-left p-4 text-sm font-semibold text-gray-700">Status</th>
              <th class="text-left p-4 text-sm font-semibold text-gray-700">Date</th>
              <th class="text-left p-4 text-sm font-semibold text-gray-700">Action</th>
            </tr>
          </thead>
          <tbody>
            <!-- Demo data -->
            <tr class="border-b border-gray-100 hover:bg-gray-50">
              <td class="p-4">
                <div class="font-semibold text-gray-900">Dr. Sarah Martinez</div>
                <div class="text-sm text-gray-600">Podiatry Clinic - Houston</div>
              </td>
              <td class="p-4 text-sm text-gray-700">Email opened: "Save 6 Hours Per Week"</td>
              <td class="p-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                  Contacted
                </span>
              </td>
              <td class="p-4 text-sm text-gray-600">2 hours ago</td>
              <td class="p-4">
                <a href="lead-detail.php?id=1" class="text-brand-teal font-semibold text-sm hover:underline">View →</a>
              </td>
            </tr>

            <tr class="border-b border-gray-100 hover:bg-gray-50">
              <td class="p-4">
                <div class="font-semibold text-gray-900">Dr. James Chen</div>
                <div class="text-sm text-gray-600">Wound Care Center - Dallas</div>
              </td>
              <td class="p-4 text-sm text-gray-700">Replied to email: Interested in demo</td>
              <td class="p-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                  Qualified
                </span>
              </td>
              <td class="p-4 text-sm text-gray-600">5 hours ago</td>
              <td class="p-4">
                <a href="lead-detail.php?id=2" class="text-brand-teal font-semibold text-sm hover:underline">View →</a>
              </td>
            </tr>

            <tr class="border-b border-gray-100 hover:bg-gray-50">
              <td class="p-4">
                <div class="font-semibold text-gray-900">Advanced Dermatology</div>
                <div class="text-sm text-gray-600">Dr. Lisa Nguyen - Austin</div>
              </td>
              <td class="p-4 text-sm text-gray-700">Registered on platform!</td>
              <td class="p-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                  Registered
                </span>
              </td>
              <td class="p-4 text-sm text-gray-600">1 day ago</td>
              <td class="p-4">
                <a href="lead-detail.php?id=3" class="text-brand-teal font-semibold text-sm hover:underline">View →</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Today's Follow-ups -->
    <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-6">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 bg-yellow-200 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-6 h-6 text-yellow-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        </div>
        <div class="flex-1">
          <h3 class="text-xl font-bold text-gray-900 mb-2">Today's Follow-ups (<?php echo $stats['followups_today']; ?>)</h3>
          <p class="text-gray-700 mb-4">You have <?php echo $stats['followups_today']; ?> leads scheduled for follow-up today.</p>
          <a href="followups.php" class="inline-flex items-center gap-2 px-6 py-3 bg-yellow-500 text-white font-bold rounded-xl hover:bg-yellow-600 transition">
            View Follow-ups
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
            </svg>
          </a>
        </div>
      </div>
    </div>

  </div>

</body>
</html>
