<?php
/**
 * Sales Rep: Messages
 *
 * Access to the messaging system (redirects to main messages page with rep context).
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

$repId = $admin['rep_id'] ?? null;
?>

<!-- Page Header -->
<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">Messages</h2>
  <p class="text-gray-600 mt-1">Communicate with CollagenDirect staff</p>
</div>

<div class="card p-8 text-center">
  <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
  </svg>
  <h3 class="text-lg font-medium text-gray-900 mb-2">Messages</h3>
  <p class="text-gray-500 mb-4">Use the messaging system to communicate with CollagenDirect operations and support.</p>
  <a href="/admin/messages.php" class="btn btn-primary">Open Messages</a>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
