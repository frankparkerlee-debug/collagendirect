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
          colors: { brand: { DEFAULT: '#2a78ff', 600:'#2565d8', 700:'#1e54b6' } },
          boxShadow: { soft:'0 12px 30px rgba(10,37,64,.07)' },
          borderRadius: { xl:'14px', '2xl':'18px' }
        }
      }
    }
  </script>
  <style>
    .navlink{ display:flex; align-items:center; gap:.5rem; padding:.5rem .75rem; border-radius:.5rem; }
    .navlink:hover{ background:#f1f5f9; transition:.15s; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900">
  <div class="min-h-screen flex">
    <aside class="w-72 bg-white border-r">
      <div class="px-5 py-5 border-b">
        <div class="flex items-center gap-3">
          <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-8 w-auto" onerror="this.remove()">
          <div>
            <div class="text-lg font-extrabold tracking-tight">CollagenDirect</div>
            <div class="text-[11px] text-slate-500">Admin Console</div>
          </div>
        </div>
      </div>
      <nav class="p-3 space-y-1 text-sm">
        <a href="/admin/index.php"     class="navlink <?=cd_active($path,'/admin/index.php')?>">Dashboard</a>
        <a href="/admin/orders.php"    class="navlink <?=cd_active($path,'/admin/orders.php')?>">Manage Orders</a>
        <a href="/admin/shipments.php" class="navlink <?=cd_active($path,'/admin/shipments.php')?>">Shipments</a>
        <a href="/admin/billing.php"  class="navlink <?=cd_active($path,'/admin/billing.php')?>">Billing</a>
        <a href="/admin/users.php"     class="navlink <?=cd_active($path,'/admin/users.php')?>">Users</a>
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
