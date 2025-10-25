<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$admin = current_admin();

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
  <title>CollagenDirect — Admin</title>
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
    /* === PORTAL DESIGN SYSTEM - EXACT COPY === */
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

    /* Utility classes to match admin pages */
    .shadow-soft {
      box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }

    .text-brand {
      color: var(--brand);
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
          $name = $admin['name'] ?? 'Admin';
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
            <?php echo htmlspecialchars($admin['name'] ?? 'Admin'); ?>
          </div>
          <div style="font-size:0.75rem; color:var(--muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
            <?php echo htmlspecialchars($admin['email'] ?? ''); ?>
          </div>
        </div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a class="<?=isActive('index')?>" href="/admin/index.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
        <span>Dashboard</span>
      </a>
      <a class="<?=isActive('orders')?>" href="/admin/orders.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
        <span>Manage Orders</span>
      </a>
      <a class="<?=isActive('shipments')?>" href="/admin/shipments.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
        <span>Shipments</span>
      </a>
      <a class="<?=isActive('billing')?>" href="/admin/billing.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
        <span>Billing</span>
      </a>
      <a class="<?=isActive('patients')?>" href="/admin/patients.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
        <span>Patients</span>
      </a>
      <a class="<?=isActive('users')?>" href="/admin/users.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
        <span>Users</span>
      </a>
      <a class="<?=isActive('messages')?>" href="/admin/messages.php">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
        <span>Messages</span>
      </a>
    </nav>

    <div style="padding: 1rem; border-top: 1px solid var(--border);">
      <a href="/portal/" style="display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; border-radius:8px; color:#5A5B60; font-weight:500; font-size:0.875rem; transition:all 0.2s; border:1px solid transparent; text-decoration:none;">
        <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
        <span>Physician Portal</span>
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
      <h1 class="top-bar-title">CollagenDirect Admin</h1>
      <div style="font-size: 0.75rem; color: var(--muted);">collagendirect.health</div>
    </div>
    <div class="content-area">
