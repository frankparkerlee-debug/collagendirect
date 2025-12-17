<?php
/**
 * Employee Sales Rep Portal Header
 *
 * Provides the navigation sidebar and layout for employee sales reps
 * who have the rep view enabled. Similar to the distributor portal
 * but for admin_users with role='sales' and has_rep_view=true.
 */
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';

$admin = current_admin();

// Ensure only employee sales reps with rep view can access this portal
// IMPORTANT: Only admin_users (with INTEGER ids) can access this portal, not users table users
// The has_rep_view flag is only set for admin_users from the login flow
if (!$admin || !isset($_SESSION['admin']) || empty($_SESSION['admin']['has_rep_view'])) {
  header('Location: /admin/');
  exit;
}

// Use the session data directly to ensure we have the INTEGER id from admin_users
// This prevents using a VARCHAR users.id when a superadmin or sales_rep somehow accesses this page
$admin = $_SESSION['admin'];

// Get managed distributors count
$managedDistributorsStmt = $pdo->prepare("
  SELECT COUNT(*) as count
  FROM sales_reps
  WHERE managed_by_admin_id = ?
  AND status = 'active'
");
$managedDistributorsStmt->execute([$admin['id']]);
$managedDistributorsCount = (int)$managedDistributorsStmt->fetch()['count'];

// Get direct accounts count
$directAccountsStmt = $pdo->prepare("
  SELECT COUNT(*) as count
  FROM users
  WHERE employee_rep_id = ?
  AND role IN ('physician', 'practice_admin')
");
$directAccountsStmt->execute([$admin['id']]);
$directAccountsCount = (int)$directAccountsStmt->fetch()['count'];

// Determine current page for navigation highlighting
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
function isActive($pageName) {
  global $currentPage;
  return $currentPage === $pageName ? 'active' : '';
}

// CSRF field helper if not already defined
if (!function_exists('csrf_field')) {
  function csrf_field() {
    if (!isset($_SESSION['csrf'])) {
      $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf']) . '">';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CollagenDirect — Employee Sales Portal</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: '#4DB8A8',
            'brand-dark': '#3A9688',
            'brand-light': '#E0F5F2',
            ink: '#1F2937',
            'ink-light': '#6B7280',
            muted: '#9CA3AF',
          },
        },
      },
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* === PORTAL DESIGN SYSTEM === */
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
      --success-light: #D1FAE5;
      --warning: #F59E0B;
      --warning-light: #FEF3C7;
      --error: #EF4444;
      --error-light: #FEE2E2;
      --info: #3B82F6;
      --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    html, body {
      font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
      color: var(--ink);
      -webkit-font-smoothing: antialiased;
      background: #ffffff;
    }

    /* Card Component */
    .card {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow-sm);
      transition: box-shadow 0.15s ease;
    }

    /* Button Component */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      border-radius: var(--radius);
      padding: 0.4375rem 0.875rem;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.15s ease;
      border: 1px solid var(--border);
      background: #ffffff;
      color: var(--ink);
      cursor: pointer;
    }
    .btn:hover { background: #f9fafb; border-color: var(--muted); }
    .btn-primary { background: var(--brand); color: #ffffff; border-color: var(--brand); }
    .btn-primary:hover { background: var(--brand-dark); border-color: var(--brand-dark); }

    /* Input Component */
    input, select, textarea {
      border: 1px solid var(--border) !important;
      border-radius: var(--radius) !important;
      padding: 0.625rem 0.875rem !important;
      font-size: 0.875rem !important;
      transition: all 0.15s ease;
      font-family: inherit;
    }
    input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: var(--brand) !important;
      box-shadow: 0 0 0 3px var(--ring) !important;
    }

    /* Table Styles */
    table {
      border-collapse: separate;
      border-spacing: 0;
      width: 100%;
    }
    thead th {
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
      padding: 0.75rem 0.5rem;
      text-align: left;
    }
    tbody td {
      padding: 1rem 0.5rem;
      color: var(--ink);
      font-size: 0.875rem;
    }
    tbody tr {
      border-bottom: 1px solid var(--border);
      transition: background 0.15s ease;
    }
    tbody tr:hover { background: #f9fafb; }

    /* === LAYOUT === */
    .app-container {
      display: flex;
      height: 100vh;
      overflow: hidden;
    }

    .sidebar {
      width: 260px;
      background: var(--bg-sidebar);
      border-right: 1px solid var(--border-sidebar);
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      position: fixed;
      left: 0;
      top: 0;
      bottom: 0;
      overflow-y: auto;
      transition: width 0.3s ease, transform 0.3s ease;
      z-index: 100;
    }

    .sidebar-header {
      max-height: 60px;
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
      width: 100%;
    }
    .sidebar-user:hover { background: rgba(0,0,0,0.04); }

    .sidebar-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 0.8125rem;
      flex-shrink: 0;
    }

    .sidebar-nav {
      padding: 1rem;
      flex: 1;
    }

    .sidebar-section-label {
      font-size: 0.625rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--muted);
      padding: 1rem 1rem 0.5rem;
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
      flex-shrink: 0;
    }

    .main-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      margin-left: 260px;
      height: 100vh;
      transition: margin-left 0.3s ease, width 0.3s ease;
      width: calc(100% - 260px);
      max-width: calc(100% - 260px);
      background: var(--bg-gray);
    }

    .top-bar {
      height: 60px;
      max-height: 60px;
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
      padding: 2rem;
      overflow-y: auto;
      overflow-x: hidden;
      flex: 1;
    }

    /* Role badge */
    .role-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.625rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .role-badge-employee {
      background: linear-gradient(135deg, #ede9fe 0%, #e0e7ff 100%);
      color: #6366f1;
    }

    .text-brand {
      color: var(--brand);
    }

    .badge-count {
      background: var(--brand);
      color: white;
      font-size: 0.625rem;
      font-weight: 700;
      padding: 0.125rem 0.375rem;
      border-radius: 9999px;
      margin-left: auto;
    }
  </style>
</head>
<body>

<div class="app-container">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-user">
        <div class="sidebar-avatar">
          <?php
          $name = $admin['name'] ?? 'Rep';
          $initials = '';
          $parts = explode(' ', $name);
          if (count($parts) >= 2) {
            $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
          } else {
            $initials = strtoupper(substr($name, 0, 2));
          }
          echo $initials;
          ?>
        </div>
        <div style="flex:1; min-width:0;">
          <div style="font-weight:600; font-size:0.875rem; color:var(--ink); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
            <?php echo htmlspecialchars($admin['name'] ?? 'Employee Rep'); ?>
          </div>
          <div style="font-size:0.75rem; color:var(--muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
            <span class="role-badge role-badge-employee">Employee Sales</span>
          </div>
        </div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <!-- Dashboard -->
      <a class="<?=isActive('index')?>" href="/admin/employee-rep/">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
        <span>Dashboard</span>
      </a>

      <div class="sidebar-section-label">Direct Accounts</div>

      <!-- My Direct Clinics -->
      <a class="<?=isActive('clinics')?>" href="/admin/employee-rep/clinics.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
        <span>My Clinics</span>
        <?php if ($directAccountsCount > 0): ?>
          <span class="badge-count"><?= $directAccountsCount ?></span>
        <?php endif; ?>
      </a>

      <!-- Add Provider -->
      <a class="<?=isActive('add-provider')?>" href="/admin/employee-rep/add-provider.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
        <span>Add Provider</span>
      </a>

      <!-- Direct Orders -->
      <a class="<?=isActive('orders')?>" href="/admin/employee-rep/orders.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        <span>Orders</span>
      </a>

      <!-- Create Wholesale Order -->
      <a class="<?=isActive('create-wholesale-order')?>" href="/admin/employee-rep/create-wholesale-order.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
        <span>Wholesale Order</span>
      </a>

      <div class="sidebar-section-label">Distributor Management</div>

      <!-- My Distributors -->
      <a class="<?=isActive('distributors')?>" href="/admin/employee-rep/distributors.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
        <span>My Distributors</span>
        <?php if ($managedDistributorsCount > 0): ?>
          <span class="badge-count"><?= $managedDistributorsCount ?></span>
        <?php endif; ?>
      </a>

      <!-- Distributor Activity -->
      <a class="<?=isActive('distributor-activity')?>" href="/admin/employee-rep/distributor-activity.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
        <span>Activity</span>
      </a>

      <div class="sidebar-section-label">Earnings</div>

      <!-- Commissions -->
      <a class="<?=isActive('commissions')?>" href="/admin/employee-rep/commissions.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span>Commissions</span>
      </a>
    </nav>

    <div style="padding: 1rem; border-top: 1px solid var(--border);">
      <a href="/admin/" style="display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; border-radius:8px; color:#5A5B60; font-weight:500; font-size:0.875rem; transition:all 0.2s; border:1px solid transparent; text-decoration:none; margin-bottom:0.25rem;">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path></svg>
        <span>Back to Admin</span>
      </a>
      <form method="post" action="/admin/logout.php" style="margin:0;">
        <?=csrf_field()?>
        <button type="submit" style="display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; border-radius:8px; color:var(--error); font-weight:500; font-size:0.875rem; transition:background 0.2s; background:none; border:none; cursor:pointer; width:100%; text-align:left;">
          <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
          <span>Log out</span>
        </button>
      </form>
    </div>
  </aside>

  <!-- Main Content -->
  <div class="main-content">
    <div class="top-bar">
      <h1 class="top-bar-title">Employee Sales Portal</h1>
      <div style="font-size: 0.75rem; color: var(--muted);">collagendirect.health</div>
    </div>
    <div class="content-area">
