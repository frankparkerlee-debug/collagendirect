<?php
// Platform Admin - Subscriptions Management
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
  <h1 class="text-2xl font-bold mb-2">Subscription Management</h1>
  <p class="text-slate-600">Manage billing and subscriptions for practices</p>
</div>

<div class="bg-white border rounded-2xl p-8 shadow-soft text-center">
  <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="mx-auto mb-4 text-slate-300">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
  </svg>
  <h3 class="text-lg font-semibold mb-2">Subscription Billing Coming Soon</h3>
  <p class="text-slate-600 max-w-md mx-auto mb-4">
    Subscription management and billing features will be integrated here. This will include plan selection, payment processing, and usage tracking.
  </p>
  <div class="text-sm text-slate-500">
    <strong>Planned Features:</strong>
    <ul class="mt-2 text-left max-w-sm mx-auto space-y-1">
      <li>• Tiered subscription plans</li>
      <li>• Automated billing via Stripe</li>
      <li>• Usage analytics and limits</li>
      <li>• Invoice generation</li>
    </ul>
  </div>
</div>

<?php include __DIR__ . '/../_footer.php'; ?>
