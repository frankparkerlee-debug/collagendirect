<?php
// Platform Admin - Practices Management
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
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold mb-2">Practices Management</h1>
      <p class="text-slate-600">Manage organizations using the CollagenDirect platform</p>
    </div>
    <button class="btn bg-brand text-white px-4 py-2 rounded-lg hover:bg-brand-600 transition">
      + Create Practice
    </button>
  </div>
</div>

<div class="bg-white border rounded-2xl p-8 shadow-soft text-center">
  <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="mx-auto mb-4 text-slate-300">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
  </svg>
  <h3 class="text-lg font-semibold mb-2">Practice Management Coming Soon</h3>
  <p class="text-slate-600 max-w-md mx-auto mb-4">
    Multi-tenant practice management features are under development. This will allow you to create and manage multiple practices, assign physicians, and control access.
  </p>
  <div class="text-sm text-slate-500">
    <strong>Current Setup:</strong> All physicians are currently in a single shared practice.
  </div>
</div>

<?php include __DIR__ . '/../_footer.php'; ?>
