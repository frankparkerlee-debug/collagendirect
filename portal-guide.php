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
        <a href="#patients" class="text-gray-700 hover:text-brand-teal font-medium whitespace-nowrap transition-colors">Patient Management</a>
        <a href="#orders" class="text-gray-700 hover:text-brand-teal font-medium whitespace-nowrap transition-colors">Creating Orders</a>
        <a href="#icd10" class="text-gray-700 hover:text-brand-teal font-medium whitespace-nowrap transition-colors">ICD-10 Search</a>
        <a href="#documents" class="text-gray-700 hover:text-brand-teal font-medium whitespace-nowrap transition-colors">Documents</a>
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

    <!-- Dashboard Overview -->
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
            <h3 class="text-xl font-bold mb-4">Quick Actions</h3>
            <div class="space-y-3">
              <a href="/portal/?page=order-add" class="block w-full bg-brand-teal text-white py-3 px-4 rounded-lg font-semibold hover:bg-teal-600 transition text-left flex items-center justify-between">
                <span>New Order</span>
                <span>→</span>
              </a>
              <a href="/portal/?page=patient-add" class="block w-full bg-white border-2 border-gray-300 py-3 px-4 rounded-lg font-semibold hover:border-brand-teal hover:text-brand-teal transition text-left flex items-center justify-between">
                <span>Add Patient</span>
                <span>→</span>
              </a>
              <a href="/portal/?page=patients" class="block w-full bg-white border-2 border-gray-300 py-3 px-4 rounded-lg font-semibold hover:border-brand-teal hover:text-brand-teal transition text-left flex items-center justify-between">
                <span>View All Patients</span>
                <span>→</span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Patient Management -->
    <section id="patients" class="section mb-20">
      <h2 class="text-4xl font-bold mb-8">Patient Management</h2>

      <!-- Patient List Screenshot -->
      <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
        <h3 class="text-2xl font-bold mb-4">Patient List Overview</h3>
        <div class="mb-6">
          <img src="/uploads/portal-screenshots/?file=patients-list.png"
               alt="Patient list showing all patients with search and filter options"
               class="w-full rounded-lg shadow-md border">
        </div>
        <p class="text-gray-600">View all your patients, search by name, and quickly access patient details and order history.</p>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
        <h3 class="text-2xl font-bold mb-6">Adding a New Patient</h3>

        <!-- Patient Add Form Screenshot -->
        <div class="mb-6">
          <img src="/uploads/portal-screenshots/?file=patient-add.png"
               alt="Add new patient form with demographics, insurance, and document upload fields"
               class="w-full rounded-lg shadow-md border">
        </div>

        <div class="space-y-6">
          <div class="flex gap-6">
            <div class="flex-shrink-0">
              <div class="w-12 h-12 rounded-full gradient-bg text-white flex items-center justify-center text-xl font-bold">1</div>
            </div>
            <div class="flex-1">
              <h4 class="text-lg font-bold mb-2">Click "New Patient" Button</h4>
              <p class="text-gray-600">Located in the top navigation or dashboard quick actions</p>
            </div>
          </div>

          <div class="flex gap-6">
            <div class="flex-shrink-0">
              <div class="w-12 h-12 rounded-full gradient-bg text-white flex items-center justify-center text-xl font-bold">2</div>
            </div>
            <div class="flex-1">
              <h4 class="text-lg font-bold mb-2">Enter Patient Demographics</h4>
              <div class="bg-gray-50 p-4 rounded-lg mt-2">
                <ul class="space-y-2 text-sm">
                  <li><strong>Required:</strong> First Name, Last Name, Date of Birth, Phone, Email</li>
                  <li><strong>Optional:</strong> Address, City, State, ZIP Code</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="flex gap-6">
            <div class="flex-shrink-0">
              <div class="w-12 h-12 rounded-full gradient-bg text-white flex items-center justify-center text-xl font-bold">3</div>
            </div>
            <div class="flex-1">
              <h4 class="text-lg font-bold mb-2">Add Insurance Information</h4>
              <div class="bg-gray-50 p-4 rounded-lg mt-2">
                <ul class="space-y-2 text-sm">
                  <li>Insurance Provider (e.g., Medicare, Blue Cross)</li>
                  <li>Member ID / Subscriber Number</li>
                  <li>Group ID (if applicable)</li>
                  <li>Insurance Company Phone Number</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="flex gap-6">
            <div class="flex-shrink-0">
              <div class="w-12 h-12 rounded-full gradient-bg text-white flex items-center justify-center text-xl font-bold">4</div>
            </div>
            <div class="flex-1">
              <h4 class="text-lg font-bold mb-2">Upload Required Documents</h4>
              <div class="grid md:grid-cols-3 gap-4 mt-2">
                <div class="bg-blue-50 border border-blue-200 p-3 rounded-lg text-sm">
                  <strong class="text-blue-900">Photo ID</strong>
                  <p class="text-blue-700 text-xs mt-1">Driver's license or government ID</p>
                </div>
                <div class="bg-green-50 border border-green-200 p-3 rounded-lg text-sm">
                  <strong class="text-green-900">Insurance Card</strong>
                  <p class="text-green-700 text-xs mt-1">Front and back photos</p>
                </div>
                <div class="bg-teal-50 border border-teal-200 p-3 rounded-lg text-sm">
                  <strong class="text-teal-900">Clinical Notes</strong>
                  <p class="text-teal-600 text-xs mt-1">Visit notes or assessments</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-8">
        <h3 class="text-2xl font-bold mb-6">Viewing & Editing Patient Information</h3>

        <!-- Patient Detail Screenshot -->
        <div class="mb-6">
          <img src="/uploads/portal-screenshots/?file=patient-detail.png"
               alt="Patient detail page showing demographics, order history, and action buttons"
               class="w-full rounded-lg shadow-md border">
        </div>

        <div class="grid md:grid-cols-2 gap-6">
          <div>
            <p class="text-gray-600 mb-4">Navigate to the patient detail page and click "Edit Patient" to update:</p>
            <ul class="space-y-2 text-sm">
              <li class="flex items-center gap-2">
                <span class="text-brand-teal">✓</span> Date of Birth
              </li>
              <li class="flex items-center gap-2">
                <span class="text-brand-teal">✓</span> Contact Information (Phone, Email)
              </li>
              <li class="flex items-center gap-2">
                <span class="text-brand-teal">✓</span> Address
              </li>
              <li class="flex items-center gap-2">
                <span class="text-brand-teal">✓</span> Insurance Details
              </li>
              <li class="flex items-center gap-2">
                <span class="text-brand-teal">✓</span> Upload New Documents
              </li>
            </ul>
          </div>
          <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
            <h4 class="font-bold text-yellow-900 mb-2">💡 Pro Tip</h4>
            <p class="text-yellow-800 text-sm">Keep patient phone numbers up to date! They're used for SMS delivery confirmations.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Creating Orders -->
    <section id="orders" class="section mb-20">
      <h2 class="text-4xl font-bold mb-8">Creating Orders</h2>

      <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
        <div class="mb-6">
          <img src="/uploads/portal-screenshots/?file=order-create.png"
               alt="Order creation form showing product selection, wound documentation, and ICD-10 fields"
               class="w-full rounded-lg shadow-md border">
        </div>

        <h3 class="text-2xl font-bold mb-6">Order Workflow</h3>

        <div class="space-y-6">
          <div class="border-l-4 border-brand-teal pl-6">
            <h4 class="text-lg font-bold mb-2"><span class="step-number text-2xl">1.</span> Select Patient</h4>
            <p class="text-gray-600">Choose an existing patient or create a new one</p>
          </div>

          <div class="border-l-4 border-brand-teal pl-6">
            <h4 class="text-lg font-bold mb-2"><span class="step-number text-2xl">2.</span> Choose Product</h4>
            <p class="text-gray-600 mb-2">Select from available wound care products:</p>
            <ul class="text-sm space-y-1 text-gray-600 ml-4">
              <li>• Collagen Matrix products in various sizes</li>
              <li>• Frequency options: Daily, Every other day, Weekly</li>
              <li>• Quantity and duration settings</li>
            </ul>
          </div>

          <div class="border-l-4 border-brand-teal pl-6">
            <h4 class="text-lg font-bold mb-2"><span class="step-number text-2xl">3.</span> Document Wound Details</h4>
            <div class="bg-gray-50 p-4 rounded-lg mt-2">
              <p class="font-semibold mb-2">For each wound, provide:</p>
              <div class="grid md:grid-cols-2 gap-3 text-sm">
                <div>
                  <strong>Location:</strong> Anatomical site
                  <p class="text-gray-600 text-xs">e.g., Right heel, Left ankle</p>
                </div>
                <div>
                  <strong>Laterality:</strong> Left/Right/Bilateral
                </div>
                <div>
                  <strong>Measurements:</strong> Length, Width, Depth (cm)
                </div>
                <div>
                  <strong>Stage:</strong> I, II, III, IV, or N/A
                </div>
                <div>
                  <strong>ICD-10 Codes:</strong> Primary (required), Secondary (optional)
                  <p class="text-brand-teal text-xs">✨ NEW: Autocomplete search!</p>
                </div>
                <div>
                  <strong>Notes:</strong> Additional wound details
                </div>
              </div>
            </div>
          </div>

          <div class="border-l-4 border-brand-teal pl-6">
            <h4 class="text-lg font-bold mb-2"><span class="step-number text-2xl">4.</span> Enter Shipping Information</h4>
            <p class="text-gray-600">Delivery address, recipient name, and contact phone</p>
          </div>

          <div class="border-l-4 border-brand-teal pl-6">
            <h4 class="text-lg font-bold mb-2"><span class="step-number text-2xl">5.</span> Review & Submit</h4>
            <p class="text-gray-600">Double-check all information before submitting to CollagenDirect</p>
          </div>
        </div>
      </div>

      <div class="bg-green-50 border-l-4 border-green-500 p-6 rounded-lg">
        <h4 class="font-bold text-green-900 mb-2">✅ After Submission</h4>
        <p class="text-green-800 mb-4">Your order is sent to CollagenDirect for review. You'll receive notifications at each stage:</p>

        <!-- Orders List Screenshot -->
        <div class="mb-4 bg-white rounded-lg p-3">
          <img src="/uploads/portal-screenshots/?file=orders-list.png"
               alt="Orders list showing different order statuses and tracking information"
               class="w-full rounded-lg shadow-md border">
        </div>
        <div class="space-y-2 text-sm">
          <div class="flex items-center gap-3">
            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-semibold">Submitted</span>
            <span class="text-green-900">Order received by CollagenDirect</span>
          </div>
          <div class="flex items-center gap-3">
            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-semibold">Approved</span>
            <span class="text-green-900">Order approved and being prepared</span>
          </div>
          <div class="flex items-center gap-3">
            <span class="bg-amber-100 text-amber-800 px-2 py-1 rounded text-xs font-semibold">In Transit</span>
            <span class="text-green-900">Shipped to patient with tracking number</span>
          </div>
          <div class="flex items-center gap-3">
            <span class="bg-teal-100 text-teal-800 px-2 py-1 rounded text-xs font-semibold">Delivered</span>
            <span class="text-green-900">Patient receives delivery confirmation SMS</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ICD-10 Search Feature -->
    <section id="icd10" class="section mb-20">
      <div class="flex items-center gap-3 mb-8">
        <h2 class="text-4xl font-bold">ICD-10 Code Search</h2>
        <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-brand-teal to-emerald-500 text-white text-sm font-bold rounded-full shadow-lg glow-teal">
          ✨ NEW FEATURE
        </span>
      </div>

      <div class="relative bg-gradient-to-br from-teal-50 via-cyan-50 to-emerald-50 border-2 border-brand-teal/30 rounded-2xl p-8 mb-8 overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-brand-teal/10 to-emerald-500/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-gradient-to-tr from-cyan-500/10 to-brand-teal/10 rounded-full blur-2xl"></div>

        <div class="relative z-10">
          <div class="flex items-start gap-4 mb-6">
            <div class="text-5xl">🎯</div>
            <div>
              <h3 class="text-2xl font-bold mb-2 text-brand-navy">Real-Time ICD-10 Code Lookup</h3>
              <p class="text-brand-slate font-medium">Powered by the NIH National Library of Medicine database - always up to date!</p>
            </div>
          </div>

          <!-- Actual ICD-10 Autocomplete Screenshot -->
          <div class="mt-6 bg-white rounded-xl p-5 shadow-xl border-2 border-brand-teal/20">
            <div class="flex items-center gap-2 mb-3">
              <span class="text-2xl">⭐</span>
              <p class="text-sm font-bold text-brand-teal">See it in action:</p>
            </div>
            <img src="/uploads/portal-screenshots/?file=icd10-autocomplete.png"
                 alt="ICD-10 autocomplete feature showing search results appearing as you type"
                 class="w-full rounded-lg shadow-lg border-2 border-brand-teal/30">
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
        <h3 class="text-2xl font-bold mb-6">How to Use ICD-10 Autocomplete</h3>

        <div class="space-y-6">
          <div class="flex gap-6 items-start">
            <div class="flex-shrink-0 text-4xl">1️⃣</div>
            <div class="flex-1">
              <h4 class="text-lg font-bold mb-2">Start Typing</h4>
              <p class="text-gray-600 mb-3">In the Primary or Secondary ICD-10 field, type at least 2 characters</p>
              <div class="bg-gray-50 border-2 border-dashed border-gray-300 p-3 rounded-lg">
                <div class="text-sm text-gray-500 mb-1">Primary ICD-10 *</div>
                <div class="bg-white border border-gray-300 px-3 py-2 rounded">diab</div>
              </div>
            </div>
          </div>

          <div class="flex gap-6 items-start">
            <div class="flex-shrink-0 text-4xl">2️⃣</div>
            <div class="flex-1">
              <h4 class="text-lg font-bold mb-2">Select from Dropdown</h4>
              <p class="text-gray-600 mb-3">Results appear instantly from the NIH database</p>
              <div class="bg-white border border-gray-300 rounded-lg shadow-lg p-2 max-w-md">
                <div class="p-2 hover:bg-green-50 rounded cursor-pointer border-l-4 border-green-500">
                  <div class="flex items-center gap-2">
                    <strong class="text-green-700 text-sm">E11.9</strong>
                    <span class="text-gray-700 text-sm">Type 2 diabetes mellitus without complications</span>
                  </div>
                </div>
                <div class="p-2 hover:bg-green-50 rounded cursor-pointer">
                  <div class="flex items-center gap-2">
                    <strong class="text-green-700 text-sm">E10.9</strong>
                    <span class="text-gray-700 text-sm">Type 1 diabetes mellitus without complications</span>
                  </div>
                </div>
                <div class="p-2 hover:bg-green-50 rounded cursor-pointer">
                  <div class="flex items-center gap-2">
                    <strong class="text-green-700 text-sm">E11.65</strong>
                    <span class="text-gray-700 text-sm">Type 2 diabetes mellitus with hyperglycemia</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex gap-6 items-start">
            <div class="flex-shrink-0 text-4xl">3️⃣</div>
            <div class="flex-1">
              <h4 class="text-lg font-bold mb-2">Code Auto-Fills</h4>
              <p class="text-gray-600 mb-3">Use keyboard arrows (<kbd>↑</kbd> <kbd>↓</kbd>) to navigate, <kbd>Enter</kbd> to select, or click with mouse</p>
              <div class="bg-green-50 border border-green-200 p-3 rounded-lg">
                <div class="flex items-center gap-2">
                  <span class="text-green-600">✓</span>
                  <span class="text-green-900 font-semibold">Code selected: E11.9 - Type 2 diabetes mellitus without complications</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-8 bg-blue-50 border-l-4 border-blue-500 p-6 rounded-lg">
          <h4 class="font-bold text-blue-900 mb-3">💡 Search Tips</h4>
          <ul class="space-y-2 text-blue-800 text-sm">
            <li><strong>By diagnosis:</strong> Type "pressure ulcer" to find all pressure ulcer codes</li>
            <li><strong>By code:</strong> Type "L97" to find all codes starting with L97</li>
            <li><strong>By body part:</strong> Type "heel" or "ankle" to find location-specific codes</li>
            <li><strong>Multiple searches:</strong> Can search differently for primary vs secondary codes</li>
          </ul>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-8">
        <h3 class="text-2xl font-bold mb-4">Common Wound Care ICD-10 Codes</h3>
        <div class="grid md:grid-cols-2 gap-4">
          <div class="border border-gray-200 rounded-lg p-4">
            <h4 class="font-bold mb-2 text-teal-600">Pressure Ulcers</h4>
            <ul class="space-y-1 text-sm text-gray-700">
              <li><strong>L89.XXX</strong> - Pressure ulcer by site and stage</li>
              <li><strong>L97.XXX</strong> - Non-pressure chronic ulcer of lower limb</li>
            </ul>
          </div>
          <div class="border border-gray-200 rounded-lg p-4">
            <h4 class="font-bold mb-2 text-teal-600">Diabetic Ulcers</h4>
            <ul class="space-y-1 text-sm text-gray-700">
              <li><strong>E11.621</strong> - Type 2 diabetes with foot ulcer</li>
              <li><strong>E11.622</strong> - Type 2 diabetes with other skin ulcer</li>
            </ul>
          </div>
          <div class="border border-gray-200 rounded-lg p-4">
            <h4 class="font-bold mb-2 text-teal-600">Venous Ulcers</h4>
            <ul class="space-y-1 text-sm text-gray-700">
              <li><strong>I83.0</strong> - Varicose veins with ulcer</li>
              <li><strong>I87.2</strong> - Venous insufficiency (chronic)</li>
            </ul>
          </div>
          <div class="border border-gray-200 rounded-lg p-4">
            <h4 class="font-bold mb-2 text-teal-600">Surgical Wounds</h4>
            <ul class="space-y-1 text-sm text-gray-700">
              <li><strong>T81.3</strong> - Disruption of wound, not elsewhere classified</li>
              <li><strong>T81.4</strong> - Infection following a procedure</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- Document Management -->
    <section id="documents" class="section mb-20">
      <h2 class="text-4xl font-bold mb-8">Document Management</h2>

      <div class="grid md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="text-4xl mb-4">🪪</div>
          <h3 class="text-xl font-bold mb-2">Patient ID</h3>
          <p class="text-gray-600 mb-4">Driver's license, passport, or government-issued ID</p>
          <div class="text-sm text-gray-500">
            <strong>Accepted:</strong> PDF, JPG, PNG, WEBP, HEIC
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="text-4xl mb-4">💳</div>
          <h3 class="text-xl font-bold mb-2">Insurance Card</h3>
          <p class="text-gray-600 mb-4">Front and back photos of insurance card</p>
          <div class="text-sm text-gray-500">
            <strong>Accepted:</strong> PDF, JPG, PNG, WEBP, HEIC
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="text-4xl mb-4">📄</div>
          <h3 class="text-xl font-bold mb-2">Clinical Notes</h3>
          <p class="text-gray-600 mb-4">Visit notes, wound assessments, medical records</p>
          <div class="text-sm text-gray-500">
            <strong>Accepted:</strong> PDF, JPG, PNG, TXT
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-8">
        <h3 class="text-2xl font-bold mb-6">Uploading Documents</h3>
        <div class="space-y-4">
          <div class="flex items-start gap-4">
            <span class="text-brand-teal text-2xl">1.</span>
            <div>
              <p class="font-semibold">Navigate to patient detail page</p>
              <p class="text-gray-600 text-sm">Click on any patient to view their full profile</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <span class="text-brand-teal text-2xl">2.</span>
            <div>
              <p class="font-semibold">Click "Edit Patient" if in view mode</p>
              <p class="text-gray-600 text-sm">File upload buttons appear in edit mode</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <span class="text-brand-teal text-2xl">3.</span>
            <div>
              <p class="font-semibold">Select file to upload</p>
              <p class="text-gray-600 text-sm">Click "Choose File" button under the appropriate document type</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <span class="text-brand-teal text-2xl">4.</span>
            <div>
              <p class="font-semibold">File uploads automatically</p>
              <p class="text-gray-600 text-sm">See confirmation message when upload completes</p>
            </div>
          </div>
        </div>

        <div class="mt-6 bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
          <h4 class="font-bold text-yellow-900 mb-2">📱 Mobile Uploads</h4>
          <p class="text-yellow-800 text-sm">The portal works great on mobile! Take photos with your phone camera and upload directly from the patient detail page.</p>
        </div>
      </div>
    </section>

    <!-- Tips & Tricks -->
    <section id="tips" class="section mb-20">
      <h2 class="text-4xl font-bold mb-8">Tips & Tricks</h2>

      <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="text-3xl">⚡</div>
            <h3 class="text-xl font-bold">Keyboard Shortcuts</h3>
          </div>
          <ul class="space-y-2 text-sm">
            <li class="flex items-center gap-2">
              <kbd>↑</kbd> <kbd>↓</kbd> <span class="text-gray-600">Navigate ICD-10 search results</span>
            </li>
            <li class="flex items-center gap-2">
              <kbd>Enter</kbd> <span class="text-gray-600">Select highlighted ICD-10 code</span>
            </li>
            <li class="flex items-center gap-2">
              <kbd>Esc</kbd> <span class="text-gray-600">Close ICD-10 dropdown</span>
            </li>
          </ul>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="text-3xl">📱</div>
            <h3 class="text-xl font-bold">Mobile Friendly</h3>
          </div>
          <p class="text-gray-600 text-sm mb-3">The portal is fully responsive and works on:</p>
          <ul class="space-y-1 text-sm text-gray-700">
            <li>✓ Smartphones (iOS and Android)</li>
            <li>✓ Tablets (iPad, Surface, etc.)</li>
            <li>✓ Desktop computers</li>
            <li>✓ All modern browsers</li>
          </ul>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="text-3xl">🔍</div>
            <h3 class="text-xl font-bold">Quick Search</h3>
          </div>
          <p class="text-gray-600 text-sm">Use the search bar on the patients page to quickly find:</p>
          <ul class="space-y-1 text-sm text-gray-700 mt-2">
            <li>• Patient names</li>
            <li>• Phone numbers</li>
            <li>• Email addresses</li>
            <li>• Medical record numbers (MRN)</li>
          </ul>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="text-3xl">💾</div>
            <h3 class="text-xl font-bold">Auto-Save</h3>
          </div>
          <p class="text-gray-600 text-sm">The portal automatically saves your progress as you work. No need to worry about losing data!</p>
          <div class="mt-3 text-xs text-gray-500 bg-gray-50 p-2 rounded">
            Note: Always review before final submission
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="text-3xl">📊</div>
            <h3 class="text-xl font-bold">Track Orders</h3>
          </div>
          <p class="text-gray-600 text-sm">View real-time order status from the dashboard:</p>
          <ul class="space-y-1 text-sm text-gray-700 mt-2">
            <li>• Submitted orders pending approval</li>
            <li>• Approved orders being prepared</li>
            <li>• In-transit shipments with tracking</li>
            <li>• Delivered orders with confirmation</li>
          </ul>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="text-3xl">🔔</div>
            <h3 class="text-xl font-bold">Notifications</h3>
          </div>
          <p class="text-gray-600 text-sm">You'll receive email notifications for:</p>
          <ul class="space-y-1 text-sm text-gray-700 mt-2">
            <li>✓ Order approvals</li>
            <li>✓ Shipment updates</li>
            <li>✓ Authorization status changes</li>
            <li>✓ Important updates from CollagenDirect</li>
          </ul>
        </div>
      </div>

      <div class="mt-8 bg-gradient-to-r from-teal-50 to-blue-50 border-2 border-teal-200 rounded-xl p-8">
        <h3 class="text-2xl font-bold mb-4 text-teal-900">Best Practices</h3>
        <div class="grid md:grid-cols-2 gap-6">
          <div>
            <h4 class="font-bold text-purple-800 mb-2">✅ Do:</h4>
            <ul class="space-y-1 text-sm text-teal-600">
              <li>• Keep patient contact info up to date</li>
              <li>• Upload clear, legible document photos</li>
              <li>• Use ICD-10 autocomplete for accurate codes</li>
              <li>• Document all wound measurements precisely</li>
              <li>• Review orders before submitting</li>
            </ul>
          </div>
          <div>
            <h4 class="font-bold text-purple-800 mb-2">❌ Don't:</h4>
            <ul class="space-y-1 text-sm text-teal-600">
              <li>• Use placeholder phone numbers (e.g., 1234567890)</li>
              <li>• Submit orders with incomplete information</li>
              <li>• Upload blurry or unreadable documents</li>
              <li>• Skip required fields</li>
              <li>• Forget to verify insurance information</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- Need Help -->
    <section class="mb-20">
      <div class="bg-gradient-to-r from-brand-teal to-blue-600 text-white rounded-2xl shadow-xl p-12 text-center">
        <h2 class="text-4xl font-bold mb-4">Need Help?</h2>
        <p class="text-xl text-purple-100 mb-8 max-w-2xl mx-auto">
          Our support team is here to assist you with any questions about the physician portal
        </p>
        <div class="flex flex-wrap gap-4 justify-center">
          <a href="mailto:support@collagendirect.health" class="bg-white text-teal-600 px-8 py-4 rounded-lg font-semibold hover:bg-teal-50 transition flex items-center gap-2">
            <span>📧</span>
            Email Support
          </a>
          <a href="tel:+18884156880" class="bg-teal-600 border-2 border-white text-white px-8 py-4 rounded-lg font-semibold hover:bg-purple-800 transition flex items-center gap-2">
            <span>📞</span>
            Call (888) 415-6880
          </a>
          <a href="/portal/index.php" class="bg-teal-600 border-2 border-white text-white px-8 py-4 rounded-lg font-semibold hover:bg-purple-800 transition flex items-center gap-2">
            <span>🚀</span>
            Launch Portal
          </a>
        </div>
      </div>
    </section>

  </div>

  <!-- Footer -->
  <footer class="bg-gray-900 text-gray-400 py-12">
    <div class="container mx-auto px-6 text-center">
      <div class="text-2xl font-bold text-white mb-4">CollagenDirect</div>
      <p class="mb-4">Simplifying wound care ordering for healthcare providers</p>
      <div class="text-sm">
        © <?php echo date('Y'); ?> CollagenDirect. All rights reserved.
      </div>
    </div>
  </footer>

  <script>
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });

    // Highlight active section in navigation
    const sections = document.querySelectorAll('.section');
    const navLinks = document.querySelectorAll('nav a[href^="#"]');

    window.addEventListener('scroll', () => {
      let current = '';
      sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        if (scrollY >= (sectionTop - 200)) {
          current = section.getAttribute('id');
        }
      });

      navLinks.forEach(link => {
        link.classList.remove('text-brand-teal', 'font-bold');
        if (link.getAttribute('href') === `#${current}`) {
          link.classList.add('text-brand-teal', 'font-bold');
        }
      });
    });
  </script>

</body>
</html>
