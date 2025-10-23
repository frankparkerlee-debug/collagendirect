<?php
// Platform Admin - System Settings
declare(strict_types=1);
require __DIR__ . '/../auth.php';
require_admin();

$admin = current_admin();
$isSuperAdmin = in_array(($admin['role'] ?? ''), ['owner', 'superadmin']);

if (!$isSuperAdmin) {
  header('Location: /admin/index.php');
  exit;
}

include __DIR__ . '/../_header.php';
?>

<div class="mb-6">
  <h1 class="text-2xl font-bold mb-2">System Settings</h1>
  <p class="text-slate-600">Platform configuration and advanced settings</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <div class="bg-white border rounded-2xl p-6 shadow-soft">
    <h3 class="font-semibold text-lg mb-4">Platform Information</h3>
    <div class="space-y-3 text-sm">
      <div class="flex justify-between">
        <span class="text-slate-600">Platform Version</span>
        <span class="font-medium">1.0.0</span>
      </div>
      <div class="flex justify-between">
        <span class="text-slate-600">Environment</span>
        <span class="font-medium">Production</span>
      </div>
      <div class="flex justify-between">
        <span class="text-slate-600">Database</span>
        <span class="font-medium">PostgreSQL</span>
      </div>
    </div>
  </div>

  <div class="bg-white border rounded-2xl p-6 shadow-soft">
    <h3 class="font-semibold text-lg mb-4">Configuration</h3>
    <p class="text-sm text-slate-600 mb-4">System-wide configuration settings will be available here.</p>
    <div class="space-y-2">
      <div class="p-3 bg-slate-50 rounded-lg text-sm text-slate-600">
        Email notifications - Coming soon
      </div>
      <div class="p-3 bg-slate-50 rounded-lg text-sm text-slate-600">
        API keys management - Coming soon
      </div>
      <div class="p-3 bg-slate-50 rounded-lg text-sm text-slate-600">
        Feature flags - Coming soon
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../_footer.php'; ?>
