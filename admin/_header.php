<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$admin = current_admin();
$path  = isset($_SERVER['REQUEST_URI']) ? (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
function cd_active($path, $match){
  return (strpos($path, $match) !== false) ? 'bg-brand/10 text-brand font-semibold' : 'text-slate-700';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CollagenDirect — Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Design Tokens - Healthcare UI */
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

    html, body {
      font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
      color: var(--ink);
      -webkit-font-smoothing: antialiased;
      background: #ffffff;
      margin: 0;
      padding: 0;
    }

    /* Card Component */
    .card {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
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

    /* Navigation Links */
    .navlink {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.625rem 1rem;
      border-radius: var(--radius);
      color: var(--muted);
      font-weight: 500;
      font-size: 0.875rem;
      transition: all 0.15s ease;
      border: 1px solid transparent;
      text-decoration: none;
    }
    .navlink:hover { color: var(--ink); background: #f9fafb; }
    .navlink.active, .navlink.bg-brand\/10 {
      background: var(--brand-light);
      color: var(--brand-dark);
      border-color: #a7f3d0;
    }
    .navlink svg { width: 18px; height: 18px; flex-shrink: 0; }

    /* Context Switcher */
    .context-switcher { position: relative; cursor: pointer; }
    .context-menu {
      display: none;
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      margin-top: 0.5rem;
      background: white;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      z-index: 50;
    }
    .context-menu.active { display: block; }
    .context-menu-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 1rem;
      transition: background 0.15s;
      text-decoration: none;
      color: var(--ink);
      border-bottom: 1px solid var(--border);
    }
    .context-menu-item:last-child { border-bottom: none; }
    .context-menu-item:hover { background: #f9fafb; }
    .context-menu-item.active { background: var(--brand-light); color: var(--brand-dark); font-weight: 600; }

    /* Input Components */
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

    /* Sidebar Navigation Styles */
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
    }
    .sidebar-header {
      max-height: 60px;
      height: 60px;
      padding: 0.625rem 1rem;
      border-bottom: 1px solid var(--border-sidebar);
      display: flex;
      align-items: center;
    }
    .sidebar-nav a {
      border: 1px solid transparent;
      border-radius: 8px;
    }
    .sidebar-nav a:hover {
      background: var(--brand-light) !important;
      color: var(--brand-dark) !important;
      border-color: var(--border-sidebar) !important;
    }
    .sidebar-nav a.active {
      background: #ffffff !important;
      color: var(--ink) !important;
      border: 1px solid var(--border-sidebar) !important;
    }
    .sidebar-nav-icon {
      width: 20px;
      height: 20px;
    }
    button[type="submit"]:hover {
      background: rgba(239, 68, 68, 0.1) !important;
    }
  </style>
  <script>
    function toggleContextMenu() {
      document.getElementById('context-menu').classList.toggle('active');
    }
    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
      const switcher = document.getElementById('context-switcher');
      const menu = document.getElementById('context-menu');
      if (switcher && menu && !switcher.contains(e.target)) {
        menu.classList.remove('active');
      }
    });
  </script>
</head>
<body style="margin: 0; padding: 0;">
  <div style="display: flex; height: 100vh; overflow: hidden;">
    <aside class="sidebar">
      <div class="sidebar-header">
        <?php
        $isSuperAdmin = in_array(($admin['role'] ?? ''), ['owner', 'superadmin']);
        $currentContext = $_SESSION['admin_context'] ?? 'practice';
        ?>
        <?php if ($isSuperAdmin): ?>
        <!-- Super Admin Context Switcher -->
        <div class="context-switcher" id="context-switcher" onclick="toggleContextMenu(); event.stopPropagation();">
          <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem; border-radius: var(--radius); transition: background 0.2s; cursor: pointer;">
            <img src="/assets/collagendirect.png" alt="CollagenDirect" style="height: 32px; width: auto;" onerror="this.remove()">
            <div style="flex: 1; min-width: 0;">
              <div style="font-size: 1.125rem; font-weight: 800; letter-spacing: -0.025em;">CollagenDirect</div>
              <div style="font-size: 0.6875rem; color: var(--muted); display: flex; align-items: center; gap: 0.25rem;">
                <?php echo $currentContext === 'platform' ? 'Platform Admin' : 'Practice Admin'; ?>
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
              </div>
            </div>
          </div>
          <div class="context-menu" id="context-menu">
            <a href="?context=practice" class="context-menu-item <?php echo $currentContext === 'practice' ? 'active' : ''; ?>">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
              </svg>
              <div>
                <div class="text-sm font-medium">Practice Admin</div>
                <div class="text-xs text-slate-500">Manage orders & physicians</div>
              </div>
            </a>
            <a href="?context=platform" class="context-menu-item <?php echo $currentContext === 'platform' ? 'active' : ''; ?>">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
              </svg>
              <div>
                <div class="text-sm font-medium">Platform Admin</div>
                <div class="text-xs text-slate-500">Manage practices & system</div>
              </div>
            </a>
          </div>
        </div>
        <?php else: ?>
        <!-- Regular Admin - No Switcher -->
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <img src="/assets/collagendirect.png" alt="CollagenDirect" style="height: 32px; width: auto;" onerror="this.remove()">
          <div>
            <div style="font-size: 1.125rem; font-weight: 800; letter-spacing: -0.025em;">CollagenDirect</div>
            <div style="font-size: 0.6875rem; color: var(--muted);">Admin Console</div>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <nav class="sidebar-nav" style="padding: 1rem; flex: 1;">
        <?php if ($currentContext === 'platform' && $isSuperAdmin): ?>
          <!-- Platform Admin Navigation -->
          <a href="/admin/platform/dashboard.php" class="<?=cd_active($path,'/admin/platform/dashboard')?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--ink-light); font-weight: 500; font-size: 0.875rem; transition: all 0.2s; margin-bottom: 0.25rem; <?=cd_active($path,'/admin/platform/dashboard') ? 'background: #ffffff; color: var(--ink); font-weight: 600; border: 1px solid var(--border-sidebar);' : ''?>">
            <svg class="sidebar-nav-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            Overview
          </a>
          <a href="/admin/platform/practices.php" class="<?=cd_active($path,'/admin/platform/practices')?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--ink-light); font-weight: 500; font-size: 0.875rem; transition: all 0.2s; margin-bottom: 0.25rem; <?=cd_active($path,'/admin/platform/practices') ? 'background: #ffffff; color: var(--ink); font-weight: 600; border: 1px solid var(--border-sidebar);' : ''?>">
            <svg class="sidebar-nav-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            Practices
          </a>
          <a href="/admin/platform/subscriptions.php" class="<?=cd_active($path,'/admin/platform/subscriptions')?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--ink-light); font-weight: 500; font-size: 0.875rem; transition: all 0.2s; margin-bottom: 0.25rem; <?=cd_active($path,'/admin/platform/subscriptions') ? 'background: #ffffff; color: var(--ink); font-weight: 600; border: 1px solid var(--border-sidebar);' : ''?>">
            <svg class="sidebar-nav-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
            </svg>
            Subscriptions
          </a>
          <a href="/admin/platform/system.php" class="<?=cd_active($path,'/admin/platform/system')?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--ink-light); font-weight: 500; font-size: 0.875rem; transition: all 0.2s; margin-bottom: 0.25rem; <?=cd_active($path,'/admin/platform/system') ? 'background: #ffffff; color: var(--ink); font-weight: 600; border: 1px solid var(--border-sidebar);' : ''?>">
            <svg class="sidebar-nav-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            System Settings
          </a>
        <?php else: ?>
          <!-- Practice Admin Navigation -->
          <a href="/admin/index.php" class="<?=cd_active($path,'/admin/index.php')?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--ink-light); font-weight: 500; font-size: 0.875rem; transition: all 0.2s; margin-bottom: 0.25rem; <?=cd_active($path,'/admin/index.php') ? 'background: #ffffff; color: var(--ink); font-weight: 600; border: 1px solid var(--border-sidebar);' : ''?>">Dashboard</a>
          <a href="/admin/orders.php" class="<?=cd_active($path,'/admin/orders.php')?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--ink-light); font-weight: 500; font-size: 0.875rem; transition: all 0.2s; margin-bottom: 0.25rem; <?=cd_active($path,'/admin/orders.php') ? 'background: #ffffff; color: var(--ink); font-weight: 600; border: 1px solid var(--border-sidebar);' : ''?>">Manage Orders</a>
          <a href="/admin/shipments.php" class="<?=cd_active($path,'/admin/shipments.php')?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--ink-light); font-weight: 500; font-size: 0.875rem; transition: all 0.2s; margin-bottom: 0.25rem; <?=cd_active($path,'/admin/shipments.php') ? 'background: #ffffff; color: var(--ink); font-weight: 600; border: 1px solid var(--border-sidebar);' : ''?>">Shipments</a>
          <a href="/admin/billing.php" class="<?=cd_active($path,'/admin/billing.php')?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--ink-light); font-weight: 500; font-size: 0.875rem; transition: all 0.2s; margin-bottom: 0.25rem; <?=cd_active($path,'/admin/billing.php') ? 'background: #ffffff; color: var(--ink); font-weight: 600; border: 1px solid var(--border-sidebar);' : ''?>">Billing</a>
          <a href="/admin/users.php" class="<?=cd_active($path,'/admin/users.php')?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--ink-light); font-weight: 500; font-size: 0.875rem; transition: all 0.2s; margin-bottom: 0.25rem; <?=cd_active($path,'/admin/users.php') ? 'background: #ffffff; color: var(--ink); font-weight: 600; border: 1px solid var(--border-sidebar);' : ''?>">Users</a>
        <?php endif; ?>
      </nav>
      <div style="padding: 1rem; border-top: 1px solid var(--border);">
        <div style="margin-bottom: 0.5rem; font-size: 0.75rem; color: var(--muted);">Signed in as <b style="color: var(--ink);"><?=e($admin['name'] ?? 'Admin')?></b></div>
        <form method="post" action="/admin/logout.php" style="margin: 0;">
          <?=csrf_field()?>
          <button type="submit" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: var(--radius); color: var(--error); font-weight: 500; font-size: 0.875rem; transition: background 0.2s; background: none; border: none; cursor: pointer; width: 100%;">
            <svg class="sidebar-nav-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
            Log out
          </button>
        </form>
      </div>
    </aside>
    <main style="flex: 1; display: flex; flex-direction: column; margin-left: 260px; height: 100vh; overflow: hidden;">
      <header style="height: 60px; max-height: 60px; background: white; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; position: sticky; top: 0; z-index: 10; flex-shrink: 0;">
        <div style="font-size: 1.25rem; font-weight: 600; color: var(--ink);">CollagenDirect Admin</div>
        <div style="font-size: 0.75rem; color: var(--muted);">collagendirect.health</div>
      </header>
      <div style="padding: 2rem; max-width: 1400px; margin: 0 auto; width: 100%; overflow-y: auto; flex: 1;">
