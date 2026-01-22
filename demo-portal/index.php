<?php
/**
 * Demo Portal Main Page
 * Provides a sandboxed demo experience matching the physician portal design
 */
declare(strict_types=1);

require_once __DIR__ . '/../admin/db.php';

// Check for valid demo session
if (empty($_SESSION['demo_mode']) || empty($_SESSION['demo_session_id'])) {
    header('Location: /demo-portal/login.html');
    exit;
}

$sessionId = $_SESSION['demo_session_id'];
$userName = $_SESSION['demo_user_name'] ?? 'Demo User';

// Verify session is still valid (not expired)
$sessionCheck = $pdo->prepare("SELECT id FROM demo_sessions WHERE id = ? AND expires_at > NOW()");
$sessionCheck->execute([$sessionId]);
if (!$sessionCheck->fetch()) {
    session_destroy();
    header('Location: /demo-portal/login.html?expired=1');
    exit;
}

// Get current page
$page = $_GET['page'] ?? 'dashboard';
$validPages = ['dashboard', 'patients', 'orders', 'wholesale', 'referral-order'];
if (!in_array($page, $validPages)) {
    $page = 'dashboard';
}

// Get patient ID for referral order page
$selectedPatientId = $_GET['patient_id'] ?? null;

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

// Status colors
$statusColors = [
    'submitted' => 'bg-yellow-100 text-yellow-700',
    'approved' => 'bg-green-100 text-green-700',
    'in_transit' => 'bg-blue-100 text-blue-700',
    'delivered' => 'bg-emerald-100 text-emerald-700',
    'pending' => 'bg-gray-100 text-gray-700'
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CollagenDirect — <?=ucfirst($page)?> (Demo)</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/shepherd.js@11.0.0/dist/css/shepherd.css">
  <style>
    /* Design Tokens - Healthcare UI (matching physician portal) */
    :root {
      --brand: #4DB8A8;
      --brand-dark: #3A9688;
      --brand-light: #E0F5F2;
      --ink: #1F2937;
      --ink-light: #6B7280;
      --muted: #9CA3AF;
      --bg-gray: #F9FAFB;
      --bg-sidebar: #F6F6F6;
      --border: #E5E7EB;
      --border-sidebar: #E8E8E9;
      --ring: rgba(77, 184, 168, 0.2);
      --radius: 0.5rem;
      --radius-lg: 0.75rem;
      --success: #10B981;
      --warning: #F59E0B;
      --error: #EF4444;
      --demo-accent: #f59e0b;
    }

    html, body {
      font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
      color: var(--ink);
      -webkit-font-smoothing: antialiased;
      background: #ffffff;
    }

    /* App Layout */
    .app-container {
      display: flex;
      height: 100vh;
      overflow: hidden;
    }

    /* Sidebar - matching physician portal */
    .sidebar {
      width: 240px;
      background: var(--bg-sidebar);
      border-right: 1px solid var(--border-sidebar);
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      position: fixed;
      left: 0;
      top: 44px; /* Account for demo banner */
      bottom: 0;
      overflow-y: auto;
      z-index: 100;
    }

    .sidebar-header {
      height: 60px;
      padding: 0.625rem 1rem;
      border-bottom: 1px solid var(--border-sidebar);
      display: flex;
      align-items: center;
    }

    .sidebar-user {
      display: flex;
      align-items: center;
      gap: 0.625rem;
      padding: 0.5rem 0.75rem;
      border-radius: var(--radius);
      transition: background 0.2s;
      cursor: pointer;
    }

    .sidebar-user:hover {
      background: rgba(0,0,0,0.04);
    }

    .sidebar-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: var(--brand);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 0.8125rem;
    }

    .sidebar-nav {
      padding: 1rem;
      flex: 1;
    }

    .sidebar-nav a {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 1rem;
      border-radius: 8px;
      color: #5A5B60;
      font-weight: 500;
      font-size: 0.875rem;
      transition: all 0.2s;
      margin-bottom: 0.25rem;
      border: 1px solid transparent;
      text-decoration: none;
    }

    .sidebar-nav a:hover {
      background: var(--brand-light);
      color: var(--brand-dark);
      border-color: var(--border-sidebar);
    }

    .sidebar-nav a.active {
      background: #ffffff;
      color: #1B1B1B;
      font-weight: 600;
      border: 1px solid var(--border-sidebar);
    }

    .sidebar-nav-icon {
      width: 20px;
      height: 20px;
    }

    /* Main Content */
    .main-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      margin-left: 240px;
      margin-top: 44px; /* Account for demo banner */
      height: calc(100vh - 44px);
      width: calc(100% - 240px);
    }

    .top-bar {
      height: 60px;
      background: white;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 2rem;
      position: sticky;
      top: 0;
      z-index: 10;
      flex-shrink: 0;
    }

    .top-bar-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--ink);
    }

    .content-area {
      flex: 1;
      overflow-y: auto;
      padding: 1.5rem 2rem;
      background: var(--bg-gray);
    }

    /* Demo Banner */
    .demo-banner {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 44px;
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: white;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 1.5rem;
      font-size: 0.875rem;
      z-index: 1000;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .demo-banner .demo-badge {
      background: rgba(255,255,255,0.2);
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-weight: 600;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-right: 1rem;
    }

    .demo-banner .demo-actions {
      display: flex;
      gap: 0.5rem;
    }

    .demo-banner .demo-actions button {
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.3);
      color: white;
      padding: 0.375rem 0.875rem;
      border-radius: 6px;
      font-size: 0.8125rem;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.2s;
    }

    .demo-banner .demo-actions button:hover {
      background: rgba(255,255,255,0.25);
    }

    /* Card Component */
    .card {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }

    /* Button Component */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      border-radius: var(--radius);
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.15s ease;
      border: 1px solid var(--border);
      background: #ffffff;
      color: var(--ink);
      cursor: pointer;
    }

    .btn:hover {
      background: #f9fafb;
      border-color: var(--muted);
    }

    .btn-primary {
      background: var(--brand);
      color: #ffffff;
      border-color: var(--brand);
    }

    .btn-primary:hover {
      background: var(--brand-dark);
      border-color: var(--brand-dark);
    }

    /* Table Styles */
    .data-table {
      width: 100%;
      font-size: 0.875rem;
    }

    .data-table thead {
      background: var(--bg-gray);
    }

    .data-table th {
      text-align: left;
      padding: 0.75rem 1rem;
      font-weight: 600;
      color: var(--ink-light);
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
      border-bottom: 1px solid var(--border);
    }

    .data-table td {
      padding: 1rem;
      border-bottom: 1px solid var(--border);
    }

    .data-table tbody tr:hover {
      background: #f9fafb;
    }

    /* Metric Cards */
    .metric-card {
      background: white;
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 1.25rem;
    }

    .metric-label {
      font-size: 0.875rem;
      color: var(--ink-light);
      margin-bottom: 0.25rem;
    }

    .metric-value {
      font-size: 2rem;
      font-weight: 700;
      color: var(--ink);
    }

    /* Status Badge */
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.625rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }

    /* Demo Notice Box */
    .demo-notice {
      background: #fef3c7;
      border: 1px solid #fcd34d;
      border-radius: var(--radius);
      padding: 0.75rem 1rem;
      font-size: 0.8125rem;
      color: #92400e;
    }

    /* Modal */
    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.5);
      z-index: 1001;
      display: none;
      align-items: center;
      justify-content: center;
    }

    .modal-backdrop.active {
      display: flex;
    }

    .modal-content {
      background: white;
      border-radius: var(--radius-lg);
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
      max-width: 32rem;
      width: 100%;
      margin: 1rem;
      max-height: 90vh;
      overflow-y: auto;
    }

    .modal-header {
      padding: 1.5rem;
      border-bottom: 1px solid var(--border);
    }

    .modal-header h2 {
      font-size: 1.25rem;
      font-weight: 600;
    }

    .modal-body {
      padding: 1.5rem;
    }

    /* Form Styles */
    .form-group {
      margin-bottom: 1rem;
    }

    .form-label {
      display: block;
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--ink);
      margin-bottom: 0.375rem;
    }

    .form-input {
      width: 100%;
      padding: 0.5rem 0.75rem;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      font-size: 0.875rem;
      transition: border-color 0.15s, box-shadow 0.15s;
    }

    .form-input:focus {
      outline: none;
      border-color: var(--brand);
      box-shadow: 0 0 0 3px var(--ring);
    }

    /* Upload Box Styles */
    .upload-box {
      border: 2px dashed var(--border);
      border-radius: var(--radius-lg);
      padding: 1.25rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s;
      background: white;
    }

    .upload-box:hover {
      border-color: var(--brand);
      background: var(--brand-light);
    }

    .upload-box.uploaded {
      border-color: var(--success);
      border-style: solid;
      background: #f0fdf4;
    }

    .upload-box .upload-content {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.25rem;
    }

    .upload-box .upload-success {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    /* ICD-10 Autocomplete Styles */
    .icd10-results {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      max-height: 200px;
      overflow-y: auto;
      z-index: 100;
    }

    .icd10-results .icd10-item {
      padding: 0.5rem 0.75rem;
      cursor: pointer;
      border-bottom: 1px solid var(--border);
      font-size: 0.8125rem;
    }

    .icd10-results .icd10-item:last-child {
      border-bottom: none;
    }

    .icd10-results .icd10-item:hover {
      background: var(--brand-light);
    }

    .icd10-results .icd10-code {
      font-weight: 600;
      color: var(--brand-dark);
    }

    .icd10-results .icd10-desc {
      color: var(--ink-light);
      margin-left: 0.5rem;
    }

    .form-group {
      position: relative;
    }

    /* Shepherd.js custom styles - ensure proper z-index layering */
    .shepherd-modal-overlay-container {
      z-index: 9998 !important;
    }

    .shepherd-element {
      z-index: 9999 !important;
    }

    /* Fix: Ensure demo banner is always clickable above any overlay */
    .demo-banner {
      z-index: 10000 !important;
    }
  </style>

  <!-- Immediately remove any stuck Shepherd overlay before page renders -->
  <script>
    (function() {
      // Remove any stuck overlay elements immediately
      document.querySelectorAll('.shepherd-modal-overlay-container, .shepherd-element').forEach(function(el) {
        el.remove();
      });
    })();
  </script>
</head>
<body data-current-page="<?=e($page)?>">

  <!-- Demo Banner -->
  <div class="demo-banner">
    <div style="display: flex; align-items: center;">
      <span class="demo-badge">Demo Mode</span>
      <span>Welcome, <?=e($userName)?></span>
    </div>
    <div class="demo-actions">
      <button onclick="DemoTour.checkAndStart()">Restart Tour</button>
      <button onclick="DemoTour.reset()">Reset Demo</button>
      <button onclick="location.href='/demo-portal/logout.php'">Exit Demo</button>
    </div>
  </div>

  <div class="app-container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-user">
          <div class="sidebar-avatar">
            <?=strtoupper(substr($userName, 0, 1) . (strpos($userName, ' ') ? substr($userName, strpos($userName, ' ') + 1, 1) : ''))?>
          </div>
          <div style="flex:1; min-width:0;">
            <div style="font-weight:600; font-size:0.875rem; color:var(--ink); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
              <?=e($userName)?>
            </div>
          </div>
        </div>
      </div>

      <nav class="sidebar-nav">
        <a class="<?=$page === 'dashboard' ? 'active' : ''?>" href="?page=dashboard">
          <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
          <span>Dashboard</span>
        </a>
        <a class="<?=$page === 'patients' ? 'active' : ''?>" href="?page=patients" data-nav="patients">
          <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
          <span>Patients</span>
        </a>
        <a class="<?=$page === 'orders' ? 'active' : ''?>" href="?page=orders" data-nav="orders">
          <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
          <span>Orders</span>
        </a>
        <a class="<?=$page === 'wholesale' ? 'active' : ''?>" href="?page=wholesale" data-nav="wholesale">
          <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
          <span>Wholesale Orders</span>
        </a>
      </nav>

      <div style="padding: 1rem; border-top: 1px solid var(--border-sidebar);">
        <div class="demo-notice">
          <strong>Demo Data Notice:</strong> All data is synthetic and will be deleted when you exit.
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
      <div class="top-bar">
        <h1 class="top-bar-title"><?=ucfirst($page)?></h1>
        <?php if ($page === 'patients'): ?>
        <button id="addPatientBtn" class="btn btn-primary" onclick="showAddPatientModal()">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          Add Patient
        </button>
        <?php endif; ?>
      </div>

      <div class="content-area">
        <?php if ($page === 'dashboard'): ?>
        <!-- Dashboard Page -->
        <div id="dashboardMetrics" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
          <div class="metric-card">
            <div class="metric-label">Total Patients</div>
            <div class="metric-value"><?=$metrics['total_patients']?></div>
          </div>
          <div class="metric-card">
            <div class="metric-label">Total Orders</div>
            <div class="metric-value"><?=$metrics['total_orders']?></div>
          </div>
          <div class="metric-card">
            <div class="metric-label">Pending Review</div>
            <div class="metric-value" style="color: var(--warning);"><?=$metrics['pending_orders']?></div>
          </div>
          <div class="metric-card">
            <div class="metric-label">Approved</div>
            <div class="metric-value" style="color: var(--success);"><?=$metrics['approved_orders']?></div>
          </div>
        </div>

        <div class="card">
          <div style="padding: 1.25rem; border-bottom: 1px solid var(--border);">
            <h2 style="font-size: 1rem; font-weight: 600;">Recent Orders</h2>
          </div>
          <?php if (empty($orders)): ?>
            <div style="padding: 2rem; text-align: center; color: var(--ink-light);">
              No orders yet. Create a patient and place an order to see activity here.
            </div>
          <?php else: ?>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Patient</th>
                  <th>Product</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                <tr>
                  <td style="font-weight: 500; color: var(--brand);"><?=e($order['order_number'])?></td>
                  <td><?=e($order['patient_first'] . ' ' . $order['patient_last'])?></td>
                  <td><?=e($order['product'])?> <?=e($order['product_size'])?></td>
                  <td>
                    <?php $color = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-700'; ?>
                    <span class="status-badge <?=$color?>"><?=ucfirst($order['status'])?></span>
                  </td>
                  <td style="color: var(--ink-light);"><?=date('M j, Y', strtotime($order['created_at']))?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <?php elseif ($page === 'patients'): ?>
        <!-- Patients Page -->
        <div id="patientsList" class="card">
          <?php if (empty($patients)): ?>
            <div style="padding: 3rem; text-align: center;">
              <p style="color: var(--ink-light); margin-bottom: 1rem;">No patients yet.</p>
              <button onclick="showAddPatientModal()" class="btn btn-primary">Add your first patient</button>
            </div>
          <?php else: ?>
            <table class="data-table">
              <thead>
                <tr>
                  <th>MRN</th>
                  <th>Name</th>
                  <th>DOB</th>
                  <th>Insurance</th>
                  <th>Wound Location</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($patients as $patient): ?>
                <tr>
                  <td style="font-family: monospace; font-size: 0.8125rem;"><?=e($patient['mrn'])?></td>
                  <td style="font-weight: 500;"><?=e($patient['first_name'] . ' ' . $patient['last_name'])?></td>
                  <td><?=$patient['dob'] ? date('m/d/Y', strtotime($patient['dob'])) : '—'?></td>
                  <td><?=e($patient['insurance_provider'] ?: '—')?></td>
                  <td><?=e($patient['wound_location'] ?: '—')?></td>
                  <td style="white-space: nowrap;">
                    <a href="?page=referral-order&patient_id=<?=e($patient['id'])?>" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8125rem; margin-right: 0.25rem;">Referral Order</a>
                    <a href="?page=wholesale&patient=<?=e($patient['id'])?>" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.8125rem;">Wholesale</a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <?php elseif ($page === 'orders'): ?>
        <!-- Orders Page -->
        <div id="ordersList" class="card">
          <?php if (empty($orders)): ?>
            <div style="padding: 3rem; text-align: center;">
              <p style="color: var(--ink-light); margin-bottom: 1rem;">No orders yet.</p>
              <a href="?page=patients" class="btn btn-primary">Go to Patients to create an order</a>
            </div>
          <?php else: ?>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Patient</th>
                  <th>Product</th>
                  <th>Status</th>
                  <th>Tracking</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                  <td style="font-weight: 500; color: var(--brand);"><?=e($order['order_number'])?></td>
                  <td><?=e($order['patient_first'] . ' ' . $order['patient_last'])?></td>
                  <td><?=e($order['product'])?> <?=e($order['product_size'])?></td>
                  <td>
                    <?php $color = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-700'; ?>
                    <span class="status-badge <?=$color?>"><?=ucfirst($order['status'])?></span>
                  </td>
                  <td style="font-family: monospace; font-size: 0.75rem;"><?=e($order['tracking_number'] ?: '—')?></td>
                  <td style="color: var(--ink-light);"><?=date('M j, Y', strtotime($order['created_at']))?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <?php elseif ($page === 'wholesale'): ?>
        <!-- Wholesale Orders Page -->
        <div id="wholesaleForm" class="card" style="padding: 1.5rem;">
          <h2 style="font-size: 1rem; font-weight: 600; margin-bottom: 1.5rem;">Create Wholesale Order</h2>

          <form id="wholesaleOrderForm">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Patient</label>
                <select name="patient_id" required class="form-input">
                  <option value="">Select patient...</option>
                  <?php foreach ($patients as $p): ?>
                    <option value="<?=e($p['id'])?>"><?=e($p['first_name'] . ' ' . $p['last_name'])?> (<?=e($p['mrn'])?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Product</label>
                <select name="product_id" required class="form-input">
                  <option value="">Select product...</option>
                  <?php foreach ($products as $p): ?>
                    <option value="<?=e($p['id'])?>" data-name="<?=e($p['name'])?>" data-size="<?=e($p['size'])?>"><?=e($p['name'])?> - <?=e($p['size'])?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Quantity (boxes)</label>
                <input type="number" name="quantity" value="1" min="1" max="100" class="form-input">
              </div>

              <div class="form-group">
                <label class="form-label">Delivery</label>
                <select name="delivery_mode" class="form-input">
                  <option value="patient">Ship to Patient</option>
                  <option value="office">Ship to Office</option>
                </select>
              </div>
            </div>

            <div style="margin-top: 1.5rem;">
              <button type="submit" class="btn btn-primary">Place Wholesale Order</button>
            </div>
          </form>
        </div>

        <?php elseif ($page === 'referral-order'): ?>
        <!-- Referral Order Page -->
        <?php
          $selectedPatient = null;
          if ($selectedPatientId) {
            foreach ($patients as $p) {
              if ($p['id'] === $selectedPatientId) {
                $selectedPatient = $p;
                break;
              }
            }
          }
        ?>
        <div id="referralOrderForm" class="card" style="padding: 1.5rem;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.125rem; font-weight: 600;">Create Referral Order</h2>
            <span style="background: var(--brand-light); color: var(--brand-dark); padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">We Bill Insurance</span>
          </div>

          <form id="referralOrderFormEl">
            <!-- Patient Selection -->
            <div class="card" style="padding: 1rem; margin-bottom: 1.5rem; background: var(--bg-gray);">
              <h3 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.75rem; color: var(--ink-light);">Patient Information</h3>
              <div class="form-group" style="margin-bottom: 0;">
                <select name="patient_id" id="referralPatientSelect" required class="form-input" style="font-size: 1rem;">
                  <option value="">Select patient...</option>
                  <?php foreach ($patients as $p): ?>
                    <option value="<?=e($p['id'])?>" <?=$selectedPatientId === $p['id'] ? 'selected' : ''?>><?=e($p['first_name'] . ' ' . $p['last_name'])?> (<?=e($p['mrn'])?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div id="patientInsuranceInfo" style="margin-top: 0.75rem; display: <?=$selectedPatient ? 'block' : 'none'?>;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8125rem; color: var(--ink-light);">
                  <span>Insurance: <strong id="patientInsurer"><?=e($selectedPatient['insurance_provider'] ?? '—')?></strong></span>
                  <span>Member ID: <strong id="patientMemberId"><?=e($selectedPatient['insurance_member_id'] ?? '—')?></strong></span>
                </div>
              </div>
            </div>

            <!-- Wound Information -->
            <div class="card" style="padding: 1rem; margin-bottom: 1.5rem; background: var(--bg-gray);">
              <h3 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.75rem; color: var(--ink-light);">Wound Information</h3>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                  <label class="form-label">Wound Location *</label>
                  <select name="wound_location" required class="form-input">
                    <option value="">Select location...</option>
                    <option value="Left Lower Leg">Left Lower Leg</option>
                    <option value="Right Lower Leg">Right Lower Leg</option>
                    <option value="Left Foot">Left Foot</option>
                    <option value="Right Foot">Right Foot</option>
                    <option value="Left Ankle">Left Ankle</option>
                    <option value="Right Ankle">Right Ankle</option>
                    <option value="Sacrum">Sacrum</option>
                    <option value="Left Heel">Left Heel</option>
                    <option value="Right Heel">Right Heel</option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Wound Type *</label>
                  <select name="wound_type" required class="form-input">
                    <option value="">Select type...</option>
                    <option value="Venous Ulcer">Venous Ulcer</option>
                    <option value="Diabetic Ulcer">Diabetic Ulcer</option>
                    <option value="Pressure Ulcer">Pressure Ulcer</option>
                    <option value="Surgical Wound">Surgical Wound</option>
                    <option value="Arterial Ulcer">Arterial Ulcer</option>
                  </select>
                </div>
              </div>

              <!-- Wound Dimensions -->
              <div style="margin-top: 1rem;">
                <label class="form-label">Wound Dimensions (cm) *</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                  <div>
                    <input type="number" name="wound_length" step="0.1" min="0.1" placeholder="Length" required class="form-input" style="text-align: center;">
                    <span style="font-size: 0.75rem; color: var(--ink-light); display: block; text-align: center; margin-top: 0.25rem;">Length</span>
                  </div>
                  <div>
                    <input type="number" name="wound_width" step="0.1" min="0.1" placeholder="Width" required class="form-input" style="text-align: center;">
                    <span style="font-size: 0.75rem; color: var(--ink-light); display: block; text-align: center; margin-top: 0.25rem;">Width</span>
                  </div>
                  <div>
                    <input type="number" name="wound_depth" step="0.1" min="0" placeholder="Depth" class="form-input" style="text-align: center;">
                    <span style="font-size: 0.75rem; color: var(--ink-light); display: block; text-align: center; margin-top: 0.25rem;">Depth</span>
                  </div>
                </div>
              </div>

              <!-- ICD-10 Codes -->
              <div style="margin-top: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                  <label class="form-label">Primary ICD-10 Code *</label>
                  <input type="text" name="icd10_primary" id="icd10Primary" required class="form-input" placeholder="Type to search..." autocomplete="off">
                  <div id="icd10PrimaryResults" class="icd10-results" style="display: none;"></div>
                </div>
                <div class="form-group">
                  <label class="form-label">Secondary ICD-10 Code</label>
                  <input type="text" name="icd10_secondary" id="icd10Secondary" class="form-input" placeholder="Optional..." autocomplete="off">
                  <div id="icd10SecondaryResults" class="icd10-results" style="display: none;"></div>
                </div>
              </div>
            </div>

            <!-- Product Selection -->
            <div class="card" style="padding: 1rem; margin-bottom: 1.5rem; background: var(--bg-gray);">
              <h3 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.75rem; color: var(--ink-light);">Product & Treatment</h3>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                  <label class="form-label">Product *</label>
                  <select name="product_id" required class="form-input">
                    <option value="">Select product...</option>
                    <?php foreach ($products as $p): ?>
                      <option value="<?=e($p['id'])?>" data-name="<?=e($p['name'])?>" data-size="<?=e($p['size'])?>"><?=e($p['name'])?> - <?=e($p['size'])?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Application Frequency *</label>
                  <select name="frequency" required class="form-input">
                    <option value="">Select frequency...</option>
                    <option value="Weekly">Weekly</option>
                    <option value="Every 2 Weeks">Every 2 Weeks</option>
                    <option value="Monthly">Monthly</option>
                  </select>
                </div>
              </div>

              <div class="form-group" style="margin-top: 1rem;">
                <label class="form-label">Treatment Duration *</label>
                <select name="duration" required class="form-input">
                  <option value="">Select duration...</option>
                  <option value="4 weeks">4 Weeks</option>
                  <option value="8 weeks">8 Weeks</option>
                  <option value="12 weeks">12 Weeks</option>
                </select>
              </div>
            </div>

            <!-- Document Uploads (Simulated) -->
            <div class="card" style="padding: 1rem; margin-bottom: 1.5rem; background: var(--bg-gray);">
              <h3 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.75rem; color: var(--ink-light);">Required Documents</h3>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <!-- Photo ID Upload -->
                <div class="upload-box" id="photoIdUpload">
                  <input type="file" id="photoIdFile" accept="image/*,.pdf" style="display: none;">
                  <div class="upload-content" onclick="document.getElementById('photoIdFile').click()">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 0.5rem;">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span style="font-weight: 500;">Photo ID</span>
                    <span style="font-size: 0.75rem; color: var(--ink-light);">Click to upload</span>
                  </div>
                  <div class="upload-success" style="display: none;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--success);">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span style="font-weight: 500; color: var(--success);">Uploaded</span>
                  </div>
                </div>

                <!-- Insurance Card Upload -->
                <div class="upload-box" id="insuranceUpload">
                  <input type="file" id="insuranceFile" accept="image/*,.pdf" style="display: none;">
                  <div class="upload-content" onclick="document.getElementById('insuranceFile').click()">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 0.5rem;">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    <span style="font-weight: 500;">Insurance Card</span>
                    <span style="font-size: 0.75rem; color: var(--ink-light);">Click to upload</span>
                  </div>
                  <div class="upload-success" style="display: none;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--success);">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span style="font-weight: 500; color: var(--success);">Uploaded</span>
                  </div>
                </div>
              </div>

              <!-- Wound Photo Upload -->
              <div class="upload-box" id="woundPhotoUpload" style="margin-top: 1rem;">
                <input type="file" id="woundPhotoFile" accept="image/*" style="display: none;">
                <div class="upload-content" onclick="document.getElementById('woundPhotoFile').click()">
                  <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 0.5rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                  </svg>
                  <span style="font-weight: 500;">Baseline Wound Photo</span>
                  <span style="font-size: 0.75rem; color: var(--ink-light);">Click to upload wound photo</span>
                </div>
                <div class="upload-success" style="display: none;">
                  <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--success);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                  </svg>
                  <span style="font-weight: 500; color: var(--success);">Photo Uploaded</span>
                </div>
              </div>
            </div>

            <!-- Submit -->
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <a href="?page=patients" class="btn">Cancel</a>
              <button type="submit" class="btn btn-primary" style="min-width: 200px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Submit Referral Order
              </button>
            </div>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Add Patient Modal -->
  <div id="addPatientModal" class="modal-backdrop">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Add New Patient</h2>
      </div>
      <form id="addPatientForm" class="modal-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">First Name *</label>
            <input type="text" name="first_name" required class="form-input">
          </div>
          <div class="form-group">
            <label class="form-label">Last Name *</label>
            <input type="text" name="last_name" required class="form-input">
          </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="dob" class="form-input">
          </div>
          <div class="form-group">
            <label class="form-label">Sex</label>
            <select name="sex" class="form-input">
              <option value="">Select...</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="tel" name="phone" class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">Insurance Provider</label>
          <input type="text" name="insurance_provider" class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">Wound Location</label>
          <input type="text" name="wound_location" placeholder="e.g., Left Lower Leg" class="form-input">
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
          <button type="button" onclick="hideAddPatientModal()" class="btn">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Patient</button>
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
      document.getElementById('addPatientModal').classList.add('active');
    }

    function hideAddPatientModal() {
      document.getElementById('addPatientModal').classList.remove('active');
      document.getElementById('addPatientForm').reset();
    }

    // Close modal on backdrop click
    document.getElementById('addPatientModal').addEventListener('click', function(e) {
      if (e.target === this) hideAddPatientModal();
    });

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

    // Common ICD-10 codes for wound care (simulated autocomplete data)
    const icd10Codes = [
      { code: 'L97.529', desc: 'Non-pressure chronic ulcer of other part of unspecified foot with unspecified severity' },
      { code: 'L97.519', desc: 'Non-pressure chronic ulcer of other part of right foot with unspecified severity' },
      { code: 'L97.419', desc: 'Non-pressure chronic ulcer of heel and midfoot of unspecified foot with unspecified severity' },
      { code: 'L97.319', desc: 'Non-pressure chronic ulcer of ankle with unspecified severity' },
      { code: 'L97.219', desc: 'Non-pressure chronic ulcer of calf with unspecified severity' },
      { code: 'L89.159', desc: 'Pressure ulcer of sacral region, unspecified stage' },
      { code: 'L89.150', desc: 'Pressure ulcer of sacral region, unstageable' },
      { code: 'E11.621', desc: 'Type 2 diabetes mellitus with foot ulcer' },
      { code: 'E11.622', desc: 'Type 2 diabetes mellitus with other skin ulcer' },
      { code: 'I83.019', desc: 'Varicose veins of unspecified lower extremity with ulcer of unspecified site' },
      { code: 'I83.029', desc: 'Varicose veins of unspecified lower extremity with ulcer and inflammation' },
      { code: 'I87.2', desc: 'Venous insufficiency (chronic) (peripheral)' },
      { code: 'T81.31XA', desc: 'Disruption of external operation wound, NEC, initial encounter' },
      { code: 'L98.499', desc: 'Non-pressure chronic ulcer of skin of other sites with unspecified severity' },
    ];

    // ICD-10 Autocomplete functionality
    function initICD10Autocomplete(inputId, resultsId) {
      const input = document.getElementById(inputId);
      const results = document.getElementById(resultsId);
      if (!input || !results) return;

      input.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        if (query.length < 2) {
          results.style.display = 'none';
          return;
        }

        const matches = icd10Codes.filter(item =>
          item.code.toLowerCase().includes(query) ||
          item.desc.toLowerCase().includes(query)
        ).slice(0, 6);

        if (matches.length === 0) {
          results.style.display = 'none';
          return;
        }

        results.innerHTML = matches.map(item => `
          <div class="icd10-item" data-code="${item.code}" data-desc="${item.desc}">
            <span class="icd10-code">${item.code}</span>
            <span class="icd10-desc">${item.desc}</span>
          </div>
        `).join('');
        results.style.display = 'block';
      });

      results.addEventListener('click', function(e) {
        const item = e.target.closest('.icd10-item');
        if (item) {
          input.value = item.dataset.code + ' - ' + item.dataset.desc;
          results.style.display = 'none';
        }
      });

      // Close on click outside
      document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !results.contains(e.target)) {
          results.style.display = 'none';
        }
      });
    }

    // Initialize ICD-10 autocomplete for referral order page
    initICD10Autocomplete('icd10Primary', 'icd10PrimaryResults');
    initICD10Autocomplete('icd10Secondary', 'icd10SecondaryResults');

    // File upload handlers (simulated - just shows success state)
    ['photoIdFile', 'insuranceFile', 'woundPhotoFile'].forEach(fileId => {
      const fileInput = document.getElementById(fileId);
      if (fileInput) {
        fileInput.addEventListener('change', function() {
          if (this.files.length > 0) {
            const uploadBox = this.closest('.upload-box');
            uploadBox.classList.add('uploaded');
            uploadBox.querySelector('.upload-content').style.display = 'none';
            uploadBox.querySelector('.upload-success').style.display = 'flex';
          }
        });
      }
    });

    // Patient selection updates insurance info display
    const referralPatientSelect = document.getElementById('referralPatientSelect');
    if (referralPatientSelect) {
      const patientData = <?php echo json_encode(array_map(function($p) {
        return [
          'id' => $p['id'],
          'insurance_provider' => $p['insurance_provider'] ?? '',
          'insurance_member_id' => $p['insurance_member_id'] ?? ''
        ];
      }, $patients)); ?>;

      referralPatientSelect.addEventListener('change', function() {
        const patient = patientData.find(p => p.id === this.value);
        const infoDiv = document.getElementById('patientInsuranceInfo');
        if (patient && infoDiv) {
          document.getElementById('patientInsurer').textContent = patient.insurance_provider || '—';
          document.getElementById('patientMemberId').textContent = patient.insurance_member_id || '—';
          infoDiv.style.display = 'block';
        } else if (infoDiv) {
          infoDiv.style.display = 'none';
        }
      });
    }

    // Referral Order Form submission
    document.getElementById('referralOrderFormEl')?.addEventListener('submit', async (e) => {
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
        quantity: 1,
        payment_type: 'referral',
        billed_by: 'collagen_direct',
        delivery_mode: 'patient',
        frequency: formData.get('frequency'),
        wound_location: formData.get('wound_location'),
        wound_type: formData.get('wound_type'),
        wound_length: formData.get('wound_length'),
        wound_width: formData.get('wound_width'),
        wound_depth: formData.get('wound_depth'),
        icd10_primary: formData.get('icd10_primary'),
        icd10_secondary: formData.get('icd10_secondary')
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
          alert('Referral Order ' + result.order_number + ' submitted successfully!\n\nCollagenDirect will handle insurance billing for this patient.');
          location.href = '?page=orders';
        } else {
          alert(result.error || 'Failed to create order');
        }
      } catch (err) {
        console.error(err);
        alert('Network error');
      }
    });

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

    // Cleanup any stuck tour overlays on page load
    document.addEventListener('DOMContentLoaded', () => {
      // First cleanup any stuck overlays from previous page loads
      DemoTour.cleanup();
      // Tour auto-start is disabled until API issues are resolved
      // Users can click "Restart Tour" button to manually start the tour
      // DemoTour.checkAndStart();
    });
  </script>
</body>
</html>
