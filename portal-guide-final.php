<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Physician Portal Guide - CollagenDirect</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              teal: '#47c6be',
              blue: '#2a78ff',
              navy: '#0a2540',
              slate: '#64748b'
            }
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      font-feature-settings: 'cv11', 'ss01';
      -webkit-font-smoothing: antialiased;
    }
    .gradient-bg {
      background: linear-gradient(135deg, #47c6be 0%, #10b981 100%);
    }
    .section {
      scroll-margin-top: 100px;
    }
    .feature-card {
      transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
      border: 1px solid rgba(71,198,190,0.1);
    }
    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 30px rgba(71,198,190,0.15);
      border-color: rgba(71,198,190,0.3);
    }
    .step-number {
      background: linear-gradient(135deg, #47c6be 0%, #10b981 100%);
      background-clip: text;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .screenshot-placeholder {
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      border: 2px dashed #cbd5e0;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #718096;
      font-weight: 500;
      min-height: 300px;
    }
    kbd {
      background: #edf2f7;
      border: 1px solid #cbd5e0;
      border-radius: 4px;
      padding: 2px 6px;
      font-family: monospace;
      font-size: 0.875em;
    }
    .glow-teal {
      box-shadow: 0 0 30px rgba(71,198,190,0.3);
    }
    .accent-gradient {
      background: linear-gradient(135deg, rgba(71,198,190,0.1) 0%, rgba(16,185,129,0.1) 100%);
    }
    nav a:hover {
      color: #47c6be;
    }
  </style>
</head>
<body class="bg-gray-50">

  <!-- Header -->
  <header class="gradient-bg text-white sticky top-0 z-50 shadow-lg shadow-brand-teal/20">
    <div class="container mx-auto px-6 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="text-2xl font-bold">CollagenDirect</div>
          <span class="text-teal-100 text-sm">Physician Portal Guide</span>
        </div>
        <a href="/portal/index.php" class="bg-white text-brand-teal px-6 py-2 rounded-xl font-bold hover:shadow-lg hover:shadow-brand-teal/30 transition-all">
          Launch Portal →
        </a>
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <section class="relative gradient-bg text-white py-24 overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.1),transparent_50%)]"></div>
    <div class="container mx-auto px-6 text-center relative z-10">
      <div class="inline-block px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full text-sm font-semibold mb-6 border border-white/20">
        📚 Complete Guide
      </div>
      <h1 class="text-6xl font-bold mb-6">Physician Portal Guide</h1>
      <p class="text-xl text-teal-50 mb-8 max-w-2xl mx-auto leading-relaxed">
        Everything you need to know about ordering wound care supplies for your patients
      </p>
      <div class="flex gap-4 justify-center flex-wrap">
        <a href="#getting-started" class="group bg-white text-brand-teal px-8 py-4 rounded-2xl font-bold shadow-lg hover:shadow-xl hover:shadow-brand-teal/30 transition-all">
          Get Started
          <span class="inline-block group-hover:translate-x-1 transition-transform ml-2">→</span>
        </a>
        <a href="/portal/" class="border-2 border-white text-white px-8 py-4 rounded-2xl font-bold hover:bg-white hover:text-brand-teal transition-all">
          Open Portal
        </a>
      </div>
    </div>
  </section>

  <!-- Quick Navigation -->
  <nav class="bg-white border-b sticky top-16 z-40 shadow-sm">
    <div class="container mx-auto px-6">
      <div class="flex gap-8 overflow-x-auto py-4 text-sm font-medium">
        <a href="#getting-started" class="text-gray-700 hover:text-brand-teal font-medium whitespace-nowrap transition-colors">Getting Started</a>
        <a href="#dashboard" class="text-gray-700 hover:text-brand-teal font-medium whitespace-nowrap transition-colors">Dashboard</a>
        <a href="#add-patient" class="text-gray-700 hover:text-brand-teal font-medium whitespace-nowrap transition-colors">Add Patient</a>
        <a href="#documents" class="text-gray-700 hover:text-brand-teal font-medium whitespace-nowrap transition-colors">Documents</a>
        <a href="#icd10" class="text-gray-700 hover:text-brand-teal font-medium whitespace-nowrap transition-colors">ICD-10 Search</a>
        <a href="#orders" class="text-gray-700 hover:text-brand-teal font-medium whitespace-nowrap transition-colors">Create Order</a>
        <a href="#view-patients" class="text-gray-700 hover:text-brand-teal font-medium whitespace-nowrap transition-colors">View Patients</a>
        <a href="#tips" class="text-gray-700 hover:text-brand-teal font-medium whitespace-nowrap transition-colors">Tips & Tricks</a>
      </div>
    </div>
  </nav>

  <div class="container mx-auto px-6 py-12">

    <!-- Getting Started -->
    <section id="getting-started" class="section mb-20">
      <h2 class="text-4xl font-bold mb-8">Getting Started</h2>

      <div class="grid md:grid-cols-3 gap-6 mb-12">
        <div class="feature-card relative bg-white p-6 rounded-xl shadow-md overflow-hidden group">
          <div class="absolute inset-0 bg-gradient-to-br from-brand-teal/5 to-emerald-500/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
          <div class="relative z-10">
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-brand-teal to-emerald-500 flex items-center justify-center text-2xl mb-4 shadow-lg">🔐</div>
            <h3 class="text-xl font-bold mb-2">Step 1: Login</h3>
            <p class="text-gray-600">Access the portal at <a href="https://collagendirect.health/portal" class="text-brand-teal hover:underline font-semibold">collagendirect.health/portal</a> with your credentials</p>
          </div>
        </div>

        <div class="feature-card relative bg-white p-6 rounded-xl shadow-md overflow-hidden group">
          <div class="absolute inset-0 bg-gradient-to-br from-brand-teal/5 to-emerald-500/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
          <div class="relative z-10">
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-brand-teal to-emerald-500 flex items-center justify-center text-2xl mb-4 shadow-lg">👥</div>
            <h3 class="text-xl font-bold mb-2">Step 2: Add Patients</h3>
            <p class="text-gray-600">Create patient profiles with demographics and insurance information</p>
          </div>
        </div>

        <div class="feature-card relative bg-white p-6 rounded-xl shadow-md overflow-hidden group">
          <div class="absolute inset-0 bg-gradient-to-br from-brand-teal/5 to-emerald-500/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
          <div class="relative z-10">
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-brand-teal to-emerald-500 flex items-center justify-center text-2xl mb-4 shadow-lg">📋</div>
            <h3 class="text-xl font-bold mb-2">Step 3: Submit Orders</h3>
            <p class="text-gray-600">Select wound care products and complete the order workflow</p>
          </div>
        </div>
      </div>

      <div class="bg-gradient-to-r from-cyan-50 to-teal-50 border-l-4 border-brand-teal p-6 rounded-xl shadow-md">
        <h4 class="font-bold text-brand-navy mb-3 flex items-center gap-2">
          <span>💡</span>
          First Time Login?
        </h4>
        <p class="text-brand-slate mb-4">If you don't have login credentials yet, register your practice to get started. You'll receive a welcome email with your login credentials.</p>
        <a href="/register" class="inline-flex items-center gap-2 bg-gradient-to-r from-brand-teal to-emerald-500 text-white px-6 py-3 rounded-xl font-bold shadow-lg hover:shadow-xl hover:shadow-brand-teal/30 transition-all">
          <span>Register Your Practice</span>
          <span>→</span>
        </a>
      </div>
    </section>
    <section id="dashboard" class="section mb-20">
      <h2 class="text-4xl font-bold mb-8">Dashboard Overview</h2>

      <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
        <div class="mb-6">
          <img src="/uploads/portal-screenshots/?file=dashboard.png"
               alt="Portal Dashboard showing revenue analytics, patient count, and recent activity"
               class="w-full rounded-lg shadow-md border">
        </div>

        <div class="grid md:grid-cols-2 gap-8">
          <div>
            <h3 class="text-xl font-bold mb-4">Key Metrics</h3>
            <ul class="space-y-3">
              <li class="flex items-start gap-3">
                <span class="text-green-500 text-xl">✓</span>
                <div>
                  <strong>Revenue Analytics</strong>
                  <p class="text-gray-600 text-sm">Track commissions and total revenue from orders</p>
                </div>
              </li>
              <li class="flex items-start gap-3">
                <span class="text-green-500 text-xl">✓</span>
                <div>
                  <strong>Recent Patients</strong>
                  <p class="text-gray-600 text-sm">Quick access to recently added patients</p>
                </div>
              </li>
              <li class="flex items-start gap-3">
                <span class="text-green-500 text-xl">✓</span>
                <div>
                  <strong>Order Statistics</strong>
                  <p class="text-gray-600 text-sm">Active orders, pending approvals, and shipment status</p>
                </div>
              </li>
            </ul>
          </div>

          <div>
            <h3 class="text-xl font-bold mb-4">Quick Actions Guide</h3>
            <p class="text-sm text-gray-600 mb-4">Learn how to perform common tasks in the portal</p>
            <div class="space-y-3">
              <a href="#orders" class="block w-full bg-brand-teal text-white py-3 px-4 rounded-lg font-semibold hover:bg-teal-600 transition text-left flex items-center justify-between">
                <span>📋 How to Create a New Order</span>
                <span>→</span>
              </a>
              <a href="#patients" class="block w-full bg-white border-2 border-gray-300 py-3 px-4 rounded-lg font-semibold hover:border-brand-teal hover:text-brand-teal transition text-left flex items-center justify-between">
                <span>👤 How to Add a Patient</span>
                <span>→</span>
              </a>
              <a href="#patients" class="block w-full bg-white border-2 border-gray-300 py-3 px-4 rounded-lg font-semibold hover:border-brand-teal hover:text-brand-teal transition text-left flex items-center justify-between">
                <span>📁 How to View All Patients</span>
                <span>→</span>
              </a>
            </div>
          </div>
        </div>

    <!-- Add a New Patient -->
    <section id="add-patient" class="section mb-20">
      <h2 class="text-4xl font-bold mb-8">How to Add a New Patient</h2>
