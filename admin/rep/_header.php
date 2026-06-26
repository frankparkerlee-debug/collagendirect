<?php
/**
 * Sales Rep Portal Header
 *
 * Provides the navigation sidebar and layout for sales rep users.
 * This is separate from the main admin _header.php to enforce
 * the limited navigation for sales reps.
 */
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';

$admin = current_admin();

// Ensure only sales reps OR employee sales reps can access this portal
// - Regular sales reps: role='sales_rep' with rep_id from sales_reps table (UUID in users table)
// - Employee sales reps: admin_users with has_rep_view=true (INTEGER in admin_users table)
//
// IMPORTANT: A regular sales rep has role='sales_rep' AND a rep_id from sales_reps table.
// An employee sales rep is from admin_users table and does NOT have role='sales_rep'.
$isRegularSalesRep = $admin && $admin['role'] === 'sales_rep' && !empty($admin['rep_id']);
$isEmployeeSalesRep = $admin && $admin['role'] !== 'sales_rep' && has_employee_rep_view();

if (!$isRegularSalesRep && !$isEmployeeSalesRep) {
  header('Location: /admin/login.php');
  exit;
}

// Get full rep profile (only for regular sales reps)
$rep = $isRegularSalesRep ? current_sales_rep() : null;

// For employee sales reps, we need to mark them appropriately
// They use employee_rep_id (INTEGER from admin_users.id), not assigned_rep_id
if ($isEmployeeSalesRep) {
  // Verify the admin_users.id is actually an integer (not a UUID from users table)
  // This prevents superadmins or regular sales reps from accidentally using this path
  if (!is_numeric($admin['id']) || strlen((string)$admin['id']) > 10) {
    // ID looks like a UUID, not an integer - this is not a valid employee rep
    header('Location: /admin/');
    exit;
  }
  $admin['is_employee_rep'] = true;
}

// Determine current page for navigation highlighting
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
function isActive($pageName) {
  global $currentPage;
  return $currentPage === $pageName ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CollagenDirect — Sales Rep Portal</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: '#0075bc',
            'brand-dark': '#20419b',
            'brand-light': '#e6f2fb',
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
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* === PORTAL DESIGN SYSTEM === */
    :root {
      --brand: #0075bc;
      --brand-dark: #20419b;
      --brand-light: #e6f2fb;
      --ink: #1F2937;
      --ink-light: #6B7280;
      --muted: #9CA3AF;
      --bg-gray: #F9FAFB;
      --bg-sidebar: #F6F6F6;
      --border: #E5E7EB;
      --border-sidebar: #E8E8E9;
      --ring: rgba(0, 117, 188, 0.2);
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
      font-family: Manrope, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
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
      width: 240px;
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
      background: var(--brand);
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

    /* Nested navigation styles */
    .nav-group {
      display: flex;
      flex-direction: column;
    }

    .nav-parent {
      display: flex;
      align-items: center;
      cursor: pointer;
    }

    .nav-parent .nav-chevron {
      margin-left: auto;
      transition: transform 0.2s ease;
    }

    .nav-parent.expanded .nav-chevron {
      transform: rotate(90deg);
    }

    .nav-submenu {
      display: none;
      flex-direction: column;
      padding-left: 1rem;
      margin-top: 0.25rem;
    }

    .nav-submenu a {
      padding: 0.5rem 1rem;
      font-size: 0.9rem;
      border-left: 2px solid var(--border-sidebar);
      margin-left: 0.5rem;
    }

    .nav-submenu a:hover {
      background: rgba(0, 0, 0, 0.05);
      border-left-color: var(--brand);
    }

    .nav-submenu a.active {
      background: rgba(18, 88, 214, 0.1);
      color: var(--brand);
      border-left-color: var(--brand);
    }

    .main-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      margin-left: 240px;
      height: 100vh;
      transition: margin-left 0.3s ease, width 0.3s ease;
      width: calc(100% - 240px);
      max-width: calc(100% - 240px);
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

    /* Icon buttons */
    .icon-btn {
      width: 36px;
      height: 36px;
      border-radius: var(--radius);
      border: 1px solid var(--border);
      background: #ffffff;
      color: var(--ink-light);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .icon-btn:hover {
      background: var(--bg-gray);
      color: var(--ink);
      border-color: var(--muted);
    }

    .shadow-soft {
      box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }

    .text-brand {
      color: var(--brand);
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
      background: var(--brand-light);
      color: var(--brand-dark);
    }
  </style>
  <script>
    function toggleSubmenu(event, submenuId) {
      event.preventDefault();
      const submenu = document.getElementById(submenuId);
      const parent = event.currentTarget;
      const chevron = parent.querySelector('.nav-chevron');

      if (submenu.style.display === 'none' || submenu.style.display === '') {
        submenu.style.display = 'flex';
        parent.classList.add('expanded');
      } else {
        submenu.style.display = 'none';
        parent.classList.remove('expanded');
      }
    }
  </script>
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
            <?php echo htmlspecialchars($admin['name'] ?? 'Sales Rep'); ?>
          </div>
          <div style="font-size:0.75rem; color:var(--muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
            <span class="role-badge">Sales Rep</span>
          </div>
        </div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <!-- Dashboard -->
      <a class="<?=isActive('index')?>" href="/admin/rep/">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
        <span>Dashboard</span>
      </a>

      <!-- My Clinics Section -->
      <div class="nav-group">
        <a class="nav-parent <?php echo (isActive('clinics') || isActive('onboard-clinic') || isActive('add-physician') || isActive('assignment-requests')) ? 'active expanded' : ''; ?>" href="#" onclick="toggleSubmenu(event, 'clinics-submenu')">
          <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
          <span>My Clinics</span>
          <svg class="nav-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-left: auto; transition: transform 0.2s;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </a>
        <div id="clinics-submenu" class="nav-submenu" style="display: <?php echo (isActive('clinics') || isActive('onboard-clinic') || isActive('add-physician') || isActive('assignment-requests')) ? 'flex' : 'none'; ?>;">
          <a class="<?=isActive('clinics')?>" href="/admin/rep/clinics.php">
            <span>Clinic Roster</span>
          </a>
          <a class="<?=isActive('onboard-clinic')?>" href="/admin/rep/onboard-clinic.php">
            <span>Onboard New Clinic</span>
          </a>
          <a class="<?=isActive('add-physician')?>" href="/admin/rep/add-physician.php">
            <span>Add Physician</span>
          </a>
          <a class="<?=isActive('assignment-requests')?>" href="/admin/rep/assignment-requests.php">
            <span>My Assignment Requests</span>
          </a>
        </div>
      </div>

      <!-- Orders (scoped view) -->
      <a class="<?=isActive('orders')?>" href="/admin/rep/orders.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        <span>Orders</span>
      </a>

      <!-- Wholesale Section -->
      <div class="nav-group">
        <a class="nav-parent <?php echo (isActive('wholesale-orders') || isActive('create-wholesale-order')) ? 'active expanded' : ''; ?>" href="#" onclick="toggleSubmenu(event, 'wholesale-submenu')">
          <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
          <span>Wholesale</span>
          <svg class="nav-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-left: auto; transition: transform 0.2s;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </a>
        <div id="wholesale-submenu" class="nav-submenu" style="display: <?php echo (isActive('wholesale-orders') || isActive('create-wholesale-order')) ? 'flex' : 'none'; ?>;">
          <a class="<?=isActive('create-wholesale-order')?>" href="/admin/rep/create-wholesale-order.php">
            <span>Create Order</span>
          </a>
          <a class="<?=isActive('wholesale-orders')?>" href="/admin/rep/wholesale-orders.php">
            <span>View Orders</span>
          </a>
        </div>
      </div>

      <!-- Commissions Section -->
      <div class="nav-group">
        <a class="nav-parent <?php echo (isActive('commissions') || isActive('payouts')) ? 'active expanded' : ''; ?>" href="#" onclick="toggleSubmenu(event, 'commissions-submenu')">
          <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <span>Commissions</span>
          <svg class="nav-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-left: auto; transition: transform 0.2s;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </a>
        <div id="commissions-submenu" class="nav-submenu" style="display: <?php echo (isActive('commissions') || isActive('payouts')) ? 'flex' : 'none'; ?>;">
          <a class="<?=isActive('commissions')?>" href="/admin/rep/commissions.php">
            <span>Commission Ledger</span>
          </a>
          <a class="<?=isActive('payouts')?>" href="/admin/rep/payouts.php">
            <span>Payout History</span>
          </a>
        </div>
      </div>

      <!-- Messages -->
      <a class="<?=isActive('messages')?>" href="/admin/rep/messages.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
        <span>Messages</span>
      </a>
    </nav>

    <div style="padding: 1rem; border-top: 1px solid var(--border);">
      <a href="/admin/rep/account.php" style="display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; border-radius:8px; color:#5A5B60; font-weight:500; font-size:0.875rem; transition:all 0.2s; border:1px solid transparent; text-decoration:none;">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
        <span>My Account</span>
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
      <h1 class="top-bar-title">Sales Rep Portal</h1>
      <div style="font-size: 0.75rem; color: var(--muted);">collagendirect.health</div>
    </div>
    <div class="content-area">
