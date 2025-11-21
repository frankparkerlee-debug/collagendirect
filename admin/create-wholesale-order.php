<?php
/**
 * Admin: Create Wholesale Order on Behalf of Practice
 * 3-step workflow: Patients → Products → Review
 */

// Initialize session FIRST, before any output
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Load database and config BEFORE any output
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Get selected practice ID early for POST handling
$selectedPracticeId = $_GET['practice_id'] ?? $_SESSION['admin_order_practice_id'] ?? '';
if ($selectedPracticeId) {
  $_SESSION['admin_order_practice_id'] = $selectedPracticeId;
}

// Handle form submissions BEFORE any output (headers)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['action'])) {
    switch ($_POST['action']) {
      case 'save_patients':
        $_SESSION['admin_order_type'] = $_POST['order_type'] ?? 'patient_orders';
        $_SESSION['admin_order_patients'] = $_POST['patients'] ?? [];
        $_SESSION['admin_order_shipping'] = $_POST['shipping'] ?? [];
        header('Location: ?practice_id=' . urlencode($selectedPracticeId) . '&step=2');
        exit;

      case 'save_products':
        $_SESSION['admin_order_products'] = $_POST['products'] ?? [];
        header('Location: ?practice_id=' . urlencode($selectedPracticeId) . '&step=3');
        exit;

      case 'back_to_patients':
        header('Location: ?practice_id=' . urlencode($selectedPracticeId) . '&step=1');
        exit;

      case 'back_to_products':
        header('Location: ?practice_id=' . urlencode($selectedPracticeId) . '&step=2');
        exit;
    }
  }
}

// NOW load header (which outputs HTML)
require_once __DIR__ . '/_header.php';

// Get list of all practices/physicians for selection
$practicesStmt = $pdo->query("
  SELECT id, practice_name, first_name, last_name, user_type, email
  FROM users
  WHERE user_type IN ('practice_admin', 'physician', 'dme_wholesale')
    AND (deleted_at IS NULL OR deleted_at > NOW())
  ORDER BY practice_name ASC, last_name ASC
");
$practices = $practicesStmt->fetchAll(PDO::FETCH_ASSOC);

// Clear session ONLY if changing practice AND not in the middle of the workflow
if (isset($_GET['practice_id']) && $_GET['practice_id'] !== ($_SESSION['admin_order_practice_id'] ?? '')) {
  // Only clear if we're on step 1 or no step specified (initial load)
  $currentStep = $_GET['step'] ?? '1';
  if ($currentStep === '1' || !isset($_GET['step'])) {
    unset($_SESSION['admin_order_patients'], $_SESSION['admin_order_products'], $_SESSION['admin_order_shipping'], $_SESSION['admin_order_type']);
  }
  $_SESSION['admin_order_practice_id'] = $_GET['practice_id'];
}

$selectedPractice = null;
$practiceLocations = [];

if ($selectedPracticeId) {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$selectedPracticeId]);
  $selectedPractice = $stmt->fetch(PDO::FETCH_ASSOC);

  // Get practice locations for shipping
  $locStmt = $pdo->prepare("
    SELECT * FROM practice_locations
    WHERE user_id = ? AND is_active = TRUE
    ORDER BY is_primary DESC, location_name ASC
  ");
  $locStmt->execute([$selectedPracticeId]);
  $practiceLocations = $locStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all active products (deduplicated, no deprecated products - same as portal)
$productsStmt = $pdo->query("
  SELECT DISTINCT ON (
    CASE
      WHEN hcpcs_code IS NOT NULL AND hcpcs_code != '' THEN hcpcs_code || '|' || LOWER(TRIM(COALESCE(size, '')))
      ELSE 'NO_HCPCS|' || LOWER(TRIM(name)) || '|' || LOWER(TRIM(COALESCE(size, '')))
    END
  )
    *
  FROM products
  WHERE active = TRUE
    AND (name NOT ILIKE '%deprecated%' OR name IS NULL)
    AND (category NOT ILIKE '%deprecated%' OR category IS NULL)
  ORDER BY
    CASE
      WHEN hcpcs_code IS NOT NULL AND hcpcs_code != '' THEN hcpcs_code || '|' || LOWER(TRIM(COALESCE(size, '')))
      ELSE 'NO_HCPCS|' || LOWER(TRIM(name)) || '|' || LOWER(TRIM(COALESCE(size, '')))
    END,
    CASE WHEN hcpcs_code IS NOT NULL AND hcpcs_code != '' THEN 0 ELSE 1 END,
    CASE WHEN price_wholesale > 0 THEN 0 ELSE 1 END,
    LENGTH(name) DESC,
    id ASC
");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get custom pricing for selected practice
$customPricing = [];
if ($selectedPracticeId) {
  $pricingStmt = $pdo->prepare("
    SELECT product_id, custom_price, discount_percentage
    FROM practice_pricing
    WHERE user_id = ?
  ");
  $pricingStmt->execute([$selectedPracticeId]);
  while ($row = $pricingStmt->fetch(PDO::FETCH_ASSOC)) {
    $customPricing[$row['product_id']] = $row;
  }
}

// Build product data for JavaScript with pricing (same logic as portal)
$productDataForJS = [];
foreach ($products as $product) {
  $piecesPerBox = max(1, (int)($product['pieces_per_box'] ?? 10));
  $defaultPricePerBox = (float)($product['price_wholesale'] ?? 0);
  $defaultPricePerPiece = $piecesPerBox > 0 ? $defaultPricePerBox / $piecesPerBox : 0;

  // Apply practice-specific pricing (same as portal/wholesale-new.php lines 48-53)
  $pricePerPiece = $defaultPricePerPiece;
  if (isset($customPricing[$product['id']])) {
    $customPrice = (float)($customPricing[$product['id']]['custom_price'] ?? 0);
    $discountPct = (float)($customPricing[$product['id']]['discount_percentage'] ?? 0);

    if ($customPrice > 0) {
      // Custom price per piece
      $pricePerPiece = $customPrice;
    } elseif ($discountPct != 0) {
      // Apply discount/upcharge percentage to default price
      $pricePerPiece = $defaultPricePerPiece * (1 - $discountPct / 100);
    }
  }
  $pricePerBox = $pricePerPiece * $piecesPerBox;

  $productDataForJS[$product['id']] = [
    'id' => $product['id'],
    'name' => $product['name'],
    'size' => $product['size'] ?? '',
    'pieces_per_box' => $piecesPerBox,
    'price_per_box' => $pricePerBox,
    'price_per_piece' => $pricePerPiece
  ];
}

// Determine current step
$step = $_GET['step'] ?? '1';

// Retrieve session data
$patients = $_SESSION['admin_order_patients'] ?? [];
$savedProducts = $_SESSION['admin_order_products'] ?? [];
$shipping = $_SESSION['admin_order_shipping'] ?? [];
?>

<div class="main-content">
  <div style="max-width: 1400px; margin: 0 auto; padding: 2rem;">

    <!-- Page Header -->
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
        Create Wholesale Order
      </h1>
      <p style="color: var(--ink-light); font-size: 0.875rem;">
        Create wholesale orders on behalf of a practice
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
      <!-- Step Indicator -->
      <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 3rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <div style="width: 2rem; height: 2rem; border-radius: 50%; <?= $step == '1' ? 'background: var(--brand); border: 2px solid var(--brand); color: white;' : ($step > '1' ? 'background: var(--brand); border: 2px solid var(--brand); color: white;' : 'background: var(--bg-gray); border: 2px solid var(--border); color: var(--muted);') ?>; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem;">
            1
          </div>
          <span style="font-size: 0.875rem; font-weight: 500;">Patients & Shipping</span>
        </div>
        <div style="color: var(--muted);">→</div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <div style="width: 2rem; height: 2rem; border-radius: 50%; <?= $step == '2' ? 'background: var(--brand); border: 2px solid var(--brand); color: white;' : ($step > '2' ? 'background: var(--brand); border: 2px solid var(--brand); color: white;' : 'background: var(--bg-gray); border: 2px solid var(--border); color: var(--muted);') ?>; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem;">
            2
          </div>
          <span style="font-size: 0.875rem; font-weight: 500;">Assign Products</span>
        </div>
        <div style="color: var(--muted);">→</div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <div style="width: 2rem; height: 2rem; border-radius: 50%; <?= $step == '3' ? 'background: var(--brand); border: 2px solid var(--brand); color: white;' : 'background: var(--bg-gray); border: 2px solid var(--border); color: var(--muted);' ?>; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem;">
            3
          </div>
          <span style="font-size: 0.875rem; font-weight: 500;">Review & Submit</span>
        </div>
      </div>

      <!-- Step Content -->
      <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
        <h3 style="font-size: 1.125rem; font-weight: 600; color: var(--ink); margin-bottom: 1.5rem;">
          Order for: <?= htmlspecialchars($selectedPractice['practice_name'] ?? ($selectedPractice['first_name'] . ' ' . $selectedPractice['last_name'])) ?>
        </h3>

        <?php
        // Include step-specific content
        $stepFile = __DIR__ . "/wholesale-step{$step}.php";
        if (file_exists($stepFile)) {
          include $stepFile;
        } else {
          echo '<p style="color: var(--error);">Invalid step</p>';
        }
        ?>
      </div>

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

<script>
const productData = <?= json_encode($productDataForJS) ?>;
const practiceId = '<?= htmlspecialchars($selectedPracticeId) ?>';
const adminId = '<?= htmlspecialchars($admin['id'] ?? '') ?>';
const practiceLocations = <?= json_encode($practiceLocations) ?>;
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
