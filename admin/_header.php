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
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { brand: { DEFAULT: '#4DB8A8', 600:'#3A9688', 700:'#2D7A6E' } },
          boxShadow: { soft:'0 12px 30px rgba(10,37,64,.07)' },
          borderRadius: { xl:'14px', '2xl':'18px' }
        }
      }
    }
  </script>
  <style>
    .navlink{ display:flex; align-items:center; gap:.5rem; padding:.5rem .75rem; border-radius:.5rem; }
    .navlink:hover{ background:#f1f5f9; transition:.15s; }
    .context-switcher{ position:relative; cursor:pointer; }
    .context-menu{ display:none; position:absolute; top:100%; left:0; right:0; margin-top:0.5rem; background:white; border:1px solid #e2e8f0; border-radius:0.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:50; }
    .context-menu.active{ display:block; }
    .context-menu-item{ display:flex; align-items:center; gap:0.5rem; padding:0.75rem 1rem; transition:background 0.15s; }
    .context-menu-item:hover{ background:#f1f5f9; }
    .context-menu-item.active{ background:#E0F5F2; color:#4DB8A8; font-weight:600; }
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
<body class="bg-slate-50 text-slate-900">
  <div class="min-h-screen flex">
    <aside class="w-72 bg-white border-r">
      <div class="px-5 py-5 border-b">
        <?php
        $isSuperAdmin = in_array(($admin['role'] ?? ''), ['owner', 'superadmin']);
        $currentContext = $_SESSION['admin_context'] ?? 'practice';
        ?>
        <?php if ($isSuperAdmin): ?>
        <!-- Super Admin Context Switcher -->
        <div class="context-switcher" id="context-switcher" onclick="toggleContextMenu(); event.stopPropagation();">
          <div class="flex items-center gap-3 p-2 rounded hover:bg-slate-50 transition">
            <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-8 w-auto" onerror="this.remove()">
            <div class="flex-1">
              <div class="text-lg font-extrabold tracking-tight">CollagenDirect</div>
              <div class="text-[11px] text-slate-500 flex items-center gap-1">
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
        <div class="flex items-center gap-3">
          <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-8 w-auto" onerror="this.remove()">
          <div>
            <div class="text-lg font-extrabold tracking-tight">CollagenDirect</div>
            <div class="text-[11px] text-slate-500">Admin Console</div>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <nav class="p-3 space-y-1 text-sm">
        <?php if ($currentContext === 'platform' && $isSuperAdmin): ?>
          <!-- Platform Admin Navigation -->
          <a href="/admin/platform/dashboard.php" class="navlink <?=cd_active($path,'/admin/platform/dashboard')?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            Overview
          </a>
          <a href="/admin/platform/practices.php" class="navlink <?=cd_active($path,'/admin/platform/practices')?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            Practices
          </a>
          <a href="/admin/platform/subscriptions.php" class="navlink <?=cd_active($path,'/admin/platform/subscriptions')?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
            </svg>
            Subscriptions
          </a>
          <a href="/admin/platform/system.php" class="navlink <?=cd_active($path,'/admin/platform/system')?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            System Settings
          </a>
        <?php else: ?>
          <!-- Practice Admin Navigation -->
          <a href="/admin/index.php"     class="navlink <?=cd_active($path,'/admin/index.php')?>">Dashboard</a>
          <a href="/admin/orders.php"    class="navlink <?=cd_active($path,'/admin/orders.php')?>">Manage Orders</a>
          <a href="/admin/shipments.php" class="navlink <?=cd_active($path,'/admin/shipments.php')?>">Shipments</a>
          <a href="/admin/billing.php"  class="navlink <?=cd_active($path,'/admin/billing.php')?>">Billing</a>
          <a href="/admin/users.php"     class="navlink <?=cd_active($path,'/admin/users.php')?>">Users</a>
        <?php endif; ?>
      </nav>
      <div class="mt-auto p-4 border-t text-xs">
        <div class="mb-2 text-slate-600">Signed in as <b><?=e($admin['name'] ?? 'Admin')?></b></div>
        <form method="post" action="/admin/logout.php" class="inline">
          <?=csrf_field()?><button class="text-slate-600 hover:text-brand">Log out</button>
        </form>
      </div>
    </aside>
    <main class="flex-1">
      <header class="bg-white border-b">
        <div class="px-6 py-4 flex items-center justify-between">
          <div class="font-semibold">CollagenDirect • Company Admin</div>
          <div class="text-xs text-slate-500">collagendirect.health</div>
        </div>
      </header>
      <div class="p-6 max-w-[1400px] mx-auto">
