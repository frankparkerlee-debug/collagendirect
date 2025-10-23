<?php
// Platform Admin Dashboard
declare(strict_types=1);
require __DIR__ . '/../auth.php';
require_admin();

$admin = current_admin();
$isSuperAdmin = in_array(($admin['role'] ?? ''), ['owner', 'superadmin']);

if (!$isSuperAdmin) {
  header('Location: /admin/index.php');
  exit;
}

// Platform-wide metrics
try {
  $totalPractices = 0; // TODO: Count from practices table
  $totalPhysicians = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('physician', 'practice_admin')")->fetchColumn();
  $totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
  $totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
  $totalRevenue = (float)$pdo->query("SELECT SUM(product_price) FROM orders WHERE status NOT IN ('rejected', 'cancelled')")->fetchColumn();
} catch (Exception $e) {
  error_log($e->getMessage());
  $totalPractices = $totalPhysicians = $totalPatients = $totalOrders = 0;
  $totalRevenue = 0.0;
}

include __DIR__ . '/../_header.php';
?>

<div class="mb-6">
  <h1 class="text-2xl font-bold mb-2">Platform Overview</h1>
  <p class="text-slate-600">Manage CollagenDirect platform and practices</p>
</div>

<!-- Platform Metrics -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
  <div class="bg-white border rounded-2xl p-6 shadow-soft">
    <div class="flex items-center justify-between mb-2">
      <div class="text-sm text-slate-500">Total Practices</div>
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-brand">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
      </svg>
    </div>
    <div class="text-3xl font-bold"><?= number_format($totalPractices) ?></div>
    <div class="text-xs text-slate-500 mt-1">Active organizations</div>
  </div>

  <div class="bg-white border rounded-2xl p-6 shadow-soft">
    <div class="flex items-center justify-between mb-2">
      <div class="text-sm text-slate-500">Total Physicians</div>
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-brand">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
      </svg>
    </div>
    <div class="text-3xl font-bold"><?= number_format($totalPhysicians) ?></div>
    <div class="text-xs text-slate-500 mt-1">Registered users</div>
  </div>

  <div class="bg-white border rounded-2xl p-6 shadow-soft">
    <div class="flex items-center justify-between mb-2">
      <div class="text-sm text-slate-500">Total Patients</div>
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-brand">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
      </svg>
    </div>
    <div class="text-3xl font-bold"><?= number_format($totalPatients) ?></div>
    <div class="text-xs text-slate-500 mt-1">Across all practices</div>
  </div>

  <div class="bg-white border rounded-2xl p-6 shadow-soft">
    <div class="flex items-center justify-between mb-2">
      <div class="text-sm text-slate-500">Total Orders</div>
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-brand">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
      </svg>
    </div>
    <div class="text-3xl font-bold"><?= number_format($totalOrders) ?></div>
    <div class="text-xs text-slate-500 mt-1">Platform-wide</div>
  </div>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
  <div class="bg-white border rounded-2xl p-6 shadow-soft">
    <h3 class="font-semibold text-lg mb-4">Quick Actions</h3>
    <div class="space-y-2">
      <a href="/admin/platform/practices.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 transition">
        <div class="w-10 h-10 rounded-full bg-brand/10 flex items-center justify-center">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-brand">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
          </svg>
        </div>
        <div>
          <div class="font-medium">Create New Practice</div>
          <div class="text-xs text-slate-500">Add a new organization to the platform</div>
        </div>
      </a>
      <a href="/admin/platform/subscriptions.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 transition">
        <div class="w-10 h-10 rounded-full bg-brand/10 flex items-center justify-center">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-brand">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        </div>
        <div>
          <div class="font-medium">Manage Subscriptions</div>
          <div class="text-xs text-slate-500">View and update billing</div>
        </div>
      </a>
    </div>
  </div>

  <div class="bg-white border rounded-2xl p-6 shadow-soft">
    <h3 class="font-semibold text-lg mb-4">System Health</h3>
    <div class="space-y-3">
      <div class="flex items-center justify-between">
        <span class="text-sm text-slate-600">Database Status</span>
        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-700 rounded">Healthy</span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-sm text-slate-600">API Status</span>
        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-700 rounded">Operational</span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-sm text-slate-600">Storage Usage</span>
        <span class="text-sm text-slate-600">--</span>
      </div>
    </div>
  </div>
</div>

<!-- Coming Soon -->
<div class="bg-blue-50 border border-blue-200 rounded-2xl p-6">
  <div class="flex items-start gap-3">
    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-blue-600 flex-shrink-0">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>
    <div>
      <h4 class="font-semibold text-blue-900 mb-1">Platform Features In Development</h4>
      <p class="text-sm text-blue-700">Multi-tenant practice management, subscription billing, and analytics dashboards are currently being built.</p>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../_footer.php'; ?>
