<?php
/**
 * Admin: Create Wholesale Order on Behalf of Practice
 * Mirrors portal wholesale ordering but allows admin to select practice/physician
 */

require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/db.php';

// Get list of all practices/physicians for selection
$practicesStmt = $pdo->query("
  SELECT id, practice_name, first_name, last_name, user_type, email
  FROM users
  WHERE user_type IN ('practice_admin', 'physician', 'dme_wholesale')
    AND (deleted_at IS NULL OR deleted_at > NOW())
  ORDER BY practice_name ASC, last_name ASC
");
$practices = $practicesStmt->fetchAll(PDO::FETCH_ASSOC);

// Selected practice for order creation
$selectedPracticeId = $_GET['practice_id'] ?? '';
$selectedPractice = null;

if ($selectedPracticeId) {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$selectedPracticeId]);
  $selectedPractice = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="main-content">
  <div style="max-width: 1600px; margin: 0 auto; padding: 2rem;">

    <!-- Page Header -->
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
        Create Wholesale Order
      </h1>
      <p style="color: var(--ink-light); font-size: 0.875rem;">
        Create a wholesale order on behalf of a practice or physician
      </p>
    </div>

    <!-- Practice Selection -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
      <h3 style="font-size: 1.125rem; font-weight: 600; color: var(--ink); margin-bottom: 1.5rem;">
        Select Practice / Physician
      </h3>

      <form method="GET" id="practice-select-form">
        <div style="display: flex; gap: 1rem; align-items: flex-end;">
          <div style="flex: 1; max-width: 600px;">
            <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
              Practice / Provider
            </label>
            <select name="practice_id" onchange="this.form.submit()"
                    style="width: 100%; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);"
                    required>
              <option value="">-- Select Practice/Physician --</option>
              <?php foreach ($practices as $practice): ?>
                <option value="<?= htmlspecialchars($practice['id']) ?>"
                        <?= $selectedPracticeId === $practice['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($practice['practice_name'] ?? ($practice['first_name'] . ' ' . $practice['last_name'])) ?>
                  (<?= htmlspecialchars($practice['user_type']) ?>) - <?= htmlspecialchars($practice['email']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </form>
    </div>

    <?php if ($selectedPractice): ?>
      <!-- Redirect to portal with impersonation -->
      <script>
        window.location.href = '/portal/index.php?page=wholesale&admin_as_user=<?= urlencode($selectedPracticeId) ?>';
      </script>

      <!-- Fallback message while redirecting -->
      <div class="card" style="padding: 3rem; text-align: center;">
        <div style="margin-bottom: 1rem;">
          <svg style="width: 48px; height: 48px; margin: 0 auto; color: var(--brand); animation: spin 1s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
          </svg>
        </div>
        <p style="font-size: 1rem; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem;">
          Loading Wholesale Order Form...
        </p>
        <p style="font-size: 0.875rem; color: var(--muted);">
          Creating order for: <?= htmlspecialchars($selectedPractice['practice_name'] ?? ($selectedPractice['first_name'] . ' ' . $selectedPractice['last_name'])) ?>
        </p>
      </div>

      <style>
        @keyframes spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }
      </style>
    <?php else: ?>
      <!-- Empty State -->
      <div class="card" style="padding: 3rem; text-align: center;">
        <svg style="width: 64px; height: 64px; margin: 0 auto 1rem; opacity: 0.3; color: var(--muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
        </svg>
        <p style="font-size: 1rem; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem;">
          No Practice Selected
        </p>
        <p style="font-size: 0.875rem; color: var(--muted);">
          Please select a practice or physician above to create a wholesale order
        </p>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
