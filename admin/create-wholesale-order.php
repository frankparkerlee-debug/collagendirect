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
      <!-- Embed Wholesale Ordering Interface -->
      <div class="card" style="padding: 1.5rem;">
        <div style="background: var(--brand-light); border: 1px solid var(--brand); border-radius: var(--radius); padding: 1rem; margin-bottom: 1.5rem;">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <svg style="width: 20px; height: 20px; color: var(--brand);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
              <strong>Creating order for:</strong> <?= htmlspecialchars($selectedPractice['practice_name'] ?? ($selectedPractice['first_name'] . ' ' . $selectedPractice['last_name'])) ?>
              <br>
              <span style="font-size: 0.875rem; color: var(--muted);">
                Orders will be attributed to this practice's account
              </span>
            </div>
          </div>
        </div>

        <!-- Iframe to load wholesale ordering page -->
        <iframe id="wholesale-order-iframe"
                src="/portal/index.php?page=wholesale&admin_as_user=<?= urlencode($selectedPracticeId) ?>"
                style="width: 100%; min-height: 1200px; border: none; border-radius: var(--radius);"
                onload="adjustIframeHeight(this)">
        </iframe>
      </div>

      <script>
        function adjustIframeHeight(iframe) {
          try {
            // Attempt to adjust iframe height based on content
            const iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
            iframe.style.height = iframeDocument.body.scrollHeight + 'px';
          } catch (e) {
            // If cross-origin, use default height
            iframe.style.height = '1200px';
          }
        }

        // Adjust height on window resize
        window.addEventListener('resize', function() {
          const iframe = document.getElementById('wholesale-order-iframe');
          if (iframe) {
            adjustIframeHeight(iframe);
          }
        });

        // Periodically adjust height for dynamic content
        setInterval(function() {
          const iframe = document.getElementById('wholesale-order-iframe');
          if (iframe) {
            adjustIframeHeight(iframe);
          }
        }, 1000);
      </script>
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
