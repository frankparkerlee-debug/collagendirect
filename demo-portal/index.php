<?php
/**
 * Demo Portal Main Page
 * Provides a sandboxed demo experience with guided tour
 */
declare(strict_types=1);

require_once __DIR__ . '/../admin/db.php';

// Check for valid demo session
if (empty($_SESSION['demo_mode']) || empty($_SESSION['demo_session_id'])) {
    header('Location: /demo-portal/login.php');
    exit;
}

$sessionId = $_SESSION['demo_session_id'];
$userName = $_SESSION['demo_user_name'] ?? 'Demo User';
$companyName = $_SESSION['demo_company'] ?? '';

// Verify session is still valid (not expired)
$sessionCheck = $pdo->prepare("SELECT id FROM demo_sessions WHERE id = ? AND expires_at > NOW()");
$sessionCheck->execute([$sessionId]);
if (!$sessionCheck->fetch()) {
    // Session expired, clear and redirect
    session_destroy();
    header('Location: /demo-portal/login.php?expired=1');
    exit;
}

// Get current page
$page = $_GET['page'] ?? 'dashboard';
$validPages = ['dashboard', 'patients', 'orders', 'wholesale'];
if (!in_array($page, $validPages)) {
    $page = 'dashboard';
}

// Fetch demo data for the current session
$patients = [];
$orders = [];

try {
    $patientStmt = $pdo->prepare("SELECT * FROM demo_patients WHERE demo_session_id = ? ORDER BY created_at DESC");
    $patientStmt->execute([$sessionId]);
    $patients = $patientStmt->fetchAll(PDO::FETCH_ASSOC);

    $orderStmt = $pdo->prepare("
        SELECT o.*, p.first_name AS patient_first, p.last_name AS patient_last, p.mrn
        FROM demo_orders o
        LEFT JOIN demo_patients p ON p.id = o.demo_patient_id
        WHERE o.demo_session_id = ?
        ORDER BY o.created_at DESC
    ");
    $orderStmt->execute([$sessionId]);
    $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[demo-portal] Data fetch error: ' . $e->getMessage());
}

// Get products for order forms
$products = [];
try {
    $productStmt = $pdo->query("SELECT id, name, size, price_referral, price_wholesale FROM products WHERE is_active = TRUE ORDER BY name, size");
    $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Fallback products
    $products = [
        ['id' => 1, 'name' => 'CollagenMatrix', 'size' => '2x2 cm', 'price_referral' => 150, 'price_wholesale' => 120],
        ['id' => 2, 'name' => 'CollagenMatrix', 'size' => '4x4 cm', 'price_referral' => 250, 'price_wholesale' => 200],
        ['id' => 3, 'name' => 'CollagenMatrix', 'size' => '5x5 cm', 'price_referral' => 350, 'price_wholesale' => 280],
    ];
}

// Calculate metrics
$metrics = [
    'total_patients' => count($patients),
    'total_orders' => count($orders),
    'pending_orders' => count(array_filter($orders, fn($o) => $o['status'] === 'submitted')),
    'approved_orders' => count(array_filter($orders, fn($o) => $o['status'] === 'approved')),
];

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Demo Portal | CollagenDirect</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/shepherd.js@11.0.0/dist/css/shepherd.css">
  <link rel="stylesheet" href="/demo-portal/assets/demo-styles.css">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: '#5FA8A1',
            demo: '#f59e0b'
          }
        }
      }
    }
  </script>
</head>
<body class="bg-gray-50 font-[Inter]" data-current-page="<?=e($page)?>">

  <!-- Demo Banner -->
  <div class="demo-banner">
    <span class="demo-badge">Demo Mode</span>
    <span>Welcome, <?=e($userName)?><?=$companyName ? ' (' . e($companyName) . ')' : ''?></span>
    <div class="demo-actions">
      <button onclick="DemoTour.checkAndStart()">Restart Tour</button>
      <button onclick="DemoTour.reset()">Reset Demo</button>
      <button onclick="location.href='/demo-portal/logout.php'">Exit Demo</button>
    </div>
  </div>

  <!-- Main Layout -->
  <div class="flex min-h-screen">
    <!-- Sidebar Navigation -->
    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col">
      <div class="p-4 border-b border-gray-200">
        <a href="/demo-portal/" class="flex items-center gap-3">
          <img src="/assets/collagendirect.png" alt="Logo" class="h-8">
          <span class="font-bold text-gray-800">COLLAGEN <span class="text-brand">DIRECT</span></span>
        </a>
      </div>

      <nav class="flex-1 p-4">
        <ul class="space-y-1">
          <li>
            <a href="?page=dashboard" data-nav="dashboard"
               class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition
                      <?=$page === 'dashboard' ? 'bg-brand/10 text-brand' : 'text-gray-600 hover:bg-gray-100'?>">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
              </svg>
              Dashboard
            </a>
          </li>
          <li>
            <a href="?page=patients" data-nav="patients"
               class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition
                      <?=$page === 'patients' ? 'bg-brand/10 text-brand' : 'text-gray-600 hover:bg-gray-100'?>">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
              Patients
            </a>
          </li>
          <li>
            <a href="?page=orders" data-nav="orders"
               class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition
                      <?=$page === 'orders' ? 'bg-brand/10 text-brand' : 'text-gray-600 hover:bg-gray-100'?>">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
              </svg>
              Orders
              <?php if ($metrics['pending_orders'] > 0): ?>
                <span class="ml-auto bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5 rounded-full"><?=$metrics['pending_orders']?></span>
              <?php endif; ?>
            </a>
          </li>
          <li>
            <a href="?page=wholesale" data-nav="wholesale"
               class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition
                      <?=$page === 'wholesale' ? 'bg-brand/10 text-brand' : 'text-gray-600 hover:bg-gray-100'?>">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
              </svg>
              Wholesale Orders
            </a>
          </li>
        </ul>
      </nav>

      <div class="p-4 border-t border-gray-200">
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
          <p class="text-xs text-amber-700">
            <strong>Demo Data Notice:</strong> All data is synthetic and will be deleted within 24 hours.
          </p>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-auto">
      <div class="p-6">

        <?php if ($page === 'dashboard'): ?>
        <!-- Dashboard Page -->
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Dashboard</h1>

        <div id="dashboardMetrics" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
          <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-sm text-gray-500 mb-1">Total Patients</div>
            <div class="text-3xl font-bold text-gray-800"><?=$metrics['total_patients']?></div>
          </div>
          <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-sm text-gray-500 mb-1">Total Orders</div>
            <div class="text-3xl font-bold text-gray-800"><?=$metrics['total_orders']?></div>
          </div>
          <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-sm text-gray-500 mb-1">Pending Review</div>
            <div class="text-3xl font-bold text-yellow-600"><?=$metrics['pending_orders']?></div>
          </div>
          <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-sm text-gray-500 mb-1">Approved</div>
            <div class="text-3xl font-bold text-green-600"><?=$metrics['approved_orders']?></div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Recent Orders</h2>
          <?php if (empty($orders)): ?>
            <p class="text-gray-500 text-sm">No orders yet. Create a patient and place an order to see activity here.</p>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Order #</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Patient</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Product</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Date</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                  <tr>
                    <td class="px-4 py-3 font-medium text-brand"><?=e($order['order_number'])?></td>
                    <td class="px-4 py-3"><?=e($order['patient_first'] . ' ' . $order['patient_last'])?></td>
                    <td class="px-4 py-3"><?=e($order['product'])?> <?=e($order['product_size'])?></td>
                    <td class="px-4 py-3">
                      <?php
                      $statusColors = [
                        'submitted' => 'bg-yellow-100 text-yellow-700',
                        'approved' => 'bg-green-100 text-green-700',
                        'in_transit' => 'bg-blue-100 text-blue-700',
                        'delivered' => 'bg-emerald-100 text-emerald-700',
                        'pending' => 'bg-gray-100 text-gray-700'
                      ];
                      $color = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-700';
                      ?>
                      <span class="px-2 py-1 rounded-full text-xs font-medium <?=$color?>"><?=ucfirst($order['status'])?></span>
                    </td>
                    <td class="px-4 py-3 text-gray-500"><?=date('M j, Y', strtotime($order['created_at']))?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <?php elseif ($page === 'patients'): ?>
        <!-- Patients Page -->
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-2xl font-bold text-gray-800">Patients</h1>
          <button id="addPatientBtn" onclick="showAddPatientModal()" class="bg-brand hover:bg-brand/90 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            + Add Patient
          </button>
        </div>

        <div id="patientsList" class="bg-white rounded-xl border border-gray-200">
          <?php if (empty($patients)): ?>
            <div class="p-8 text-center">
              <p class="text-gray-500 mb-4">No patients yet.</p>
              <button onclick="showAddPatientModal()" class="text-brand font-medium hover:underline">Add your first patient</button>
            </div>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">MRN</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Name</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">DOB</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Insurance</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Wound Location</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <?php foreach ($patients as $patient): ?>
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-sm"><?=e($patient['mrn'])?></td>
                    <td class="px-4 py-3 font-medium"><?=e($patient['first_name'] . ' ' . $patient['last_name'])?></td>
                    <td class="px-4 py-3"><?=$patient['dob'] ? date('m/d/Y', strtotime($patient['dob'])) : '—'?></td>
                    <td class="px-4 py-3"><?=e($patient['insurance_provider'] ?: '—')?></td>
                    <td class="px-4 py-3"><?=e($patient['wound_location'] ?: '—')?></td>
                    <td class="px-4 py-3">
                      <button onclick="createOrderForPatient('<?=e($patient['id'])?>')" class="text-brand hover:underline text-sm">Create Order</button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <?php elseif ($page === 'orders'): ?>
        <!-- Orders Page -->
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Orders</h1>

        <div id="ordersList" class="bg-white rounded-xl border border-gray-200">
          <?php if (empty($orders)): ?>
            <div class="p-8 text-center">
              <p class="text-gray-500 mb-4">No orders yet.</p>
              <a href="?page=patients" class="text-brand font-medium hover:underline">Go to Patients to create an order</a>
            </div>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Order #</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Patient</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Product</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Tracking</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Date</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <?php foreach ($orders as $order): ?>
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-brand"><?=e($order['order_number'])?></td>
                    <td class="px-4 py-3"><?=e($order['patient_first'] . ' ' . $order['patient_last'])?></td>
                    <td class="px-4 py-3"><?=e($order['product'])?> <?=e($order['product_size'])?></td>
                    <td class="px-4 py-3">
                      <?php
                      $color = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-700';
                      ?>
                      <span class="px-2 py-1 rounded-full text-xs font-medium <?=$color?>"><?=ucfirst($order['status'])?></span>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs"><?=e($order['tracking_number'] ?: '—')?></td>
                    <td class="px-4 py-3 text-gray-500"><?=date('M j, Y', strtotime($order['created_at']))?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <?php elseif ($page === 'wholesale'): ?>
        <!-- Wholesale Orders Page -->
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Wholesale / DME Orders</h1>

        <div id="wholesaleForm" class="bg-white rounded-xl border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Create Wholesale Order</h2>

          <form id="wholesaleOrderForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Patient</label>
                <select name="patient_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:border-brand">
                  <option value="">Select patient...</option>
                  <?php foreach ($patients as $p): ?>
                    <option value="<?=e($p['id'])?>"><?=e($p['first_name'] . ' ' . $p['last_name'])?> (<?=e($p['mrn'])?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                <select name="product_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:border-brand">
                  <option value="">Select product...</option>
                  <?php foreach ($products as $p): ?>
                    <option value="<?=e($p['id'])?>" data-name="<?=e($p['name'])?>" data-size="<?=e($p['size'])?>"><?=e($p['name'])?> - <?=e($p['size'])?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity (boxes)</label>
                <input type="number" name="quantity" value="1" min="1" max="100" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:border-brand">
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Delivery</label>
                <select name="delivery_mode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:border-brand">
                  <option value="patient">Ship to Patient</option>
                  <option value="office">Ship to Office</option>
                </select>
              </div>
            </div>

            <div class="pt-4">
              <button type="submit" class="bg-brand hover:bg-brand/90 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                Place Wholesale Order
              </button>
            </div>
          </form>
        </div>
        <?php endif; ?>

      </div>
    </main>
  </div>

  <!-- Add Patient Modal -->
  <div id="addPatientModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-800">Add New Patient</h2>
      </div>
      <form id="addPatientForm" class="p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
            <input type="text" name="first_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
            <input type="text" name="last_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
            <input type="date" name="dob" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sex</label>
            <select name="sex" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
              <option value="">Select...</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
          <input type="tel" name="phone" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Insurance Provider</label>
          <input type="text" name="insurance_provider" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Wound Location</label>
          <input type="text" name="wound_location" placeholder="e.g., Left Lower Leg" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <button type="button" onclick="hideAddPatientModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
          <button type="submit" class="bg-brand hover:bg-brand/90 text-white px-6 py-2 rounded-lg text-sm font-medium">Add Patient</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/shepherd.js@11.0.0/dist/js/shepherd.min.js"></script>
  <script src="/demo-portal/assets/tour-config.js"></script>
  <script>
    // Add Patient Modal
    function showAddPatientModal() {
      document.getElementById('addPatientModal').classList.remove('hidden');
      document.getElementById('addPatientModal').classList.add('flex');
    }

    function hideAddPatientModal() {
      document.getElementById('addPatientModal').classList.add('hidden');
      document.getElementById('addPatientModal').classList.remove('flex');
      document.getElementById('addPatientForm').reset();
    }

    // Add Patient Form
    document.getElementById('addPatientForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const data = Object.fromEntries(new FormData(form));

      try {
        const res = await fetch('/api/demo/patients.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify(data)
        });

        const result = await res.json();
        if (result.ok) {
          hideAddPatientModal();
          location.reload();
        } else {
          alert(result.error || 'Failed to add patient');
        }
      } catch (err) {
        console.error(err);
        alert('Network error');
      }
    });

    // Create Order for Patient
    function createOrderForPatient(patientId) {
      location.href = '?page=wholesale&patient=' + patientId;
    }

    // Wholesale Order Form
    document.getElementById('wholesaleOrderForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const formData = new FormData(form);

      const productSelect = form.querySelector('[name="product_id"]');
      const selectedOption = productSelect.options[productSelect.selectedIndex];

      const data = {
        patient_id: formData.get('patient_id'),
        product_id: parseInt(formData.get('product_id')),
        product: selectedOption.dataset.name,
        product_size: selectedOption.dataset.size,
        quantity: parseInt(formData.get('quantity')),
        delivery_mode: formData.get('delivery_mode'),
        payment_type: 'wholesale',
        billed_by: 'practice_dme'
      };

      try {
        const res = await fetch('/api/demo/orders.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify(data)
        });

        const result = await res.json();
        if (result.ok) {
          alert('Order ' + result.order_number + ' created successfully!');
          location.href = '?page=orders';
        } else {
          alert(result.error || 'Failed to create order');
        }
      } catch (err) {
        console.error(err);
        alert('Network error');
      }
    });

    // Pre-select patient if provided in URL
    const urlParams = new URLSearchParams(window.location.search);
    const preselectedPatient = urlParams.get('patient');
    if (preselectedPatient) {
      const patientSelect = document.querySelector('[name="patient_id"]');
      if (patientSelect) {
        patientSelect.value = preselectedPatient;
      }
    }

    // Start tour on first visit
    document.addEventListener('DOMContentLoaded', () => {
      DemoTour.checkAndStart();
    });
  </script>
</body>
</html>
