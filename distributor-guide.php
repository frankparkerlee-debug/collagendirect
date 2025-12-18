<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Distributor Product Guide - CollagenDirect</title>
  <meta name="robots" content="noindex, nofollow">
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
      background: linear-gradient(135deg, #0a2540 0%, #1e3a5f 100%);
    }
    .section {
      scroll-margin-top: 100px;
    }
    @media print {
      .no-print { display: none !important; }
      body { background: white; }
      .print-break { page-break-before: always; }
    }
  </style>
</head>
<body class="bg-gray-50">

  <!-- Header -->
  <header class="gradient-bg text-white sticky top-0 z-50 shadow-lg no-print">
    <div class="container mx-auto px-6 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-8 w-auto brightness-0 invert">
          <span class="text-teal-300 text-sm font-semibold">Distributor Product Guide</span>
        </div>
        <div class="flex items-center gap-4">
          <button onclick="window.print()" class="px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 text-sm font-semibold">
            Print Guide
          </button>
          <a href="/admin/" class="bg-brand-teal text-brand-navy px-6 py-2 rounded-xl font-bold hover:shadow-lg transition-all">
            Admin Portal
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <section class="gradient-bg text-white py-20">
    <div class="container mx-auto px-6 text-center">
      <div class="inline-block px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full text-sm font-semibold mb-6 border border-white/20">
        For Authorized Distributors
      </div>
      <h1 class="text-5xl lg:text-6xl font-black mb-6">Distributor Product Guide</h1>
      <p class="text-xl text-gray-300 mb-8 max-w-2xl mx-auto leading-relaxed">
        Complete product information, wholesale pricing, and clinical positioning for distribution partners
      </p>
      <div class="flex gap-4 justify-center flex-wrap">
        <a href="#products" class="group bg-brand-teal text-brand-navy px-8 py-4 rounded-2xl font-bold shadow-lg hover:shadow-xl transition-all">
          View Products
          <span class="inline-block group-hover:translate-x-1 transition-transform ml-2">→</span>
        </a>
        <a href="#wholesale" class="border-2 border-white text-white px-8 py-4 rounded-2xl font-bold hover:bg-white hover:text-brand-navy transition-all">
          Wholesale Pricing
        </a>
      </div>
    </div>
  </section>

  <!-- Quick Navigation -->
  <nav class="bg-white border-b sticky top-16 z-40 shadow-sm no-print">
    <div class="container mx-auto px-6">
      <div class="flex gap-8 overflow-x-auto py-4 text-sm font-medium">
        <a href="#overview" class="text-gray-700 hover:text-brand-teal whitespace-nowrap transition-colors">Overview</a>
        <a href="#products" class="text-gray-700 hover:text-brand-teal whitespace-nowrap transition-colors">Product Line</a>
        <a href="#clinical" class="text-gray-700 hover:text-brand-teal whitespace-nowrap transition-colors">Clinical Positioning</a>
        <a href="#wholesale" class="text-gray-700 hover:text-brand-teal whitespace-nowrap transition-colors">Wholesale Pricing</a>
        <a href="#ordering" class="text-gray-700 hover:text-brand-teal whitespace-nowrap transition-colors">How to Order</a>
        <a href="#support" class="text-gray-700 hover:text-brand-teal whitespace-nowrap transition-colors">Support</a>
      </div>
    </div>
  </nav>

  <div class="container mx-auto px-6 py-12">

    <!-- Company Overview -->
    <section id="overview" class="section mb-16">
      <h2 class="text-4xl font-bold mb-8">Company Overview</h2>

      <div class="grid md:grid-cols-2 gap-8 mb-12">
        <div class="bg-white rounded-xl shadow-lg p-8">
          <h3 class="text-xl font-bold mb-4 text-brand-navy">About CollagenDirect</h3>
          <p class="text-gray-600 mb-4">
            CollagenDirect provides FDA-cleared collagen wound care products directly to physicians with streamlined ordering,
            fast fulfillment, and comprehensive insurance billing support.
          </p>
          <ul class="space-y-2 text-gray-600">
            <li class="flex items-start gap-2">
              <svg class="w-5 h-5 text-brand-teal flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span>FDA-cleared Class II medical devices</span>
            </li>
            <li class="flex items-start gap-2">
              <svg class="w-5 h-5 text-brand-teal flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span>98% insurance reimbursement rate</span>
            </li>
            <li class="flex items-start gap-2">
              <svg class="w-5 h-5 text-brand-teal flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span>24-48 hour order fulfillment</span>
            </li>
            <li class="flex items-start gap-2">
              <svg class="w-5 h-5 text-brand-teal flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span>Comprehensive clinical support</span>
            </li>
          </ul>
        </div>

        <div class="bg-gradient-to-br from-brand-teal to-emerald-500 rounded-xl shadow-lg p-8 text-white">
          <h3 class="text-xl font-bold mb-4">Why Physicians Choose CollagenDirect</h3>
          <div class="space-y-4">
            <div class="bg-white/10 backdrop-blur rounded-lg p-4">
              <div class="font-bold mb-1">Faster Deliveries</div>
              <p class="text-sm text-teal-50">24-48 hour fulfillment vs. 7+ days from traditional suppliers</p>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-lg p-4">
              <div class="font-bold mb-1">Higher Reimbursement</div>
              <p class="text-sm text-teal-50">98% approval rate with our billing support team</p>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-lg p-4">
              <div class="font-bold mb-1">Less Paperwork</div>
              <p class="text-sm text-teal-50">Digital ordering system eliminates fax machines and phone trees</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Target Market -->
      <div class="bg-white rounded-xl shadow-lg p-8">
        <h3 class="text-xl font-bold mb-6 text-brand-navy">Target Market</h3>
        <div class="grid md:grid-cols-4 gap-6">
          <div class="text-center p-4 rounded-lg bg-gray-50">
            <div class="text-3xl mb-2">🩺</div>
            <div class="font-bold text-gray-900">Wound Care Clinics</div>
            <p class="text-sm text-gray-600 mt-1">Outpatient wound centers</p>
          </div>
          <div class="text-center p-4 rounded-lg bg-gray-50">
            <div class="text-3xl mb-2">🦶</div>
            <div class="font-bold text-gray-900">Podiatrists</div>
            <p class="text-sm text-gray-600 mt-1">DPM practices</p>
          </div>
          <div class="text-center p-4 rounded-lg bg-gray-50">
            <div class="text-3xl mb-2">💉</div>
            <div class="font-bold text-gray-900">Vascular Surgeons</div>
            <p class="text-sm text-gray-600 mt-1">Vascular specialists</p>
          </div>
          <div class="text-center p-4 rounded-lg bg-gray-50">
            <div class="text-3xl mb-2">🏥</div>
            <div class="font-bold text-gray-900">Primary Care</div>
            <p class="text-sm text-gray-600 mt-1">Family medicine with wound patients</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Product Line -->
    <section id="products" class="section mb-16 print-break">
      <h2 class="text-4xl font-bold mb-8">Product Line</h2>
      <p class="text-gray-600 mb-8 max-w-3xl">
        Our collagen wound care products are designed to accelerate healing for chronic and acute wounds.
        Each product addresses specific clinical needs and wound characteristics.
      </p>

      <div class="grid md:grid-cols-2 gap-8">

        <!-- Product 1: Collagen Sheets -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border-2 border-blue-200">
          <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6">
            <div class="flex items-center gap-4">
              <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center text-3xl">📄</div>
              <div>
                <h3 class="text-2xl font-black">Collagen Sheets</h3>
                <p class="text-blue-100">HCPCS: A6010, A6021</p>
              </div>
            </div>
          </div>
          <div class="p-6">
            <div class="mb-4">
              <h4 class="font-bold text-gray-900 mb-2">Product Description</h4>
              <p class="text-gray-600 text-sm">
                Sterile, porous collagen dressings that promote granulation tissue formation and accelerate wound healing.
                Available in multiple sizes for various wound dimensions.
              </p>
            </div>
            <div class="mb-4">
              <h4 class="font-bold text-gray-900 mb-2">Clinical Indications</h4>
              <ul class="text-sm text-gray-600 space-y-1">
                <li>• Diabetic foot ulcers (most common use case)</li>
                <li>• Pressure ulcers (Stage 3-4)</li>
                <li>• Venous leg ulcers</li>
                <li>• Surgical wounds with delayed healing</li>
                <li>• Shallow to moderate depth wounds</li>
              </ul>
            </div>
            <div class="bg-blue-50 rounded-lg p-4">
              <h4 class="font-bold text-blue-900 mb-2">Key Selling Points</h4>
              <ul class="text-sm text-blue-800 space-y-1">
                <li>• Cuts healing time 30-50% (peer-reviewed studies)</li>
                <li>• Predictable granulation tissue formation</li>
                <li>• Medicare/Medicaid covered for chronic wounds</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Product 2: Collagen Particles -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border-2 border-green-200">
          <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6">
            <div class="flex items-center gap-4">
              <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center text-3xl">🧪</div>
              <div>
                <h3 class="text-2xl font-black">Collagen Particles</h3>
                <p class="text-green-100">HCPCS: A6010</p>
              </div>
            </div>
          </div>
          <div class="p-6">
            <div class="mb-4">
              <h4 class="font-bold text-gray-900 mb-2">Product Description</h4>
              <p class="text-gray-600 text-sm">
                Sterile collagen granules designed to fill wound cavities and tunneling.
                Promotes healing from the wound base upward by eliminating dead space.
              </p>
            </div>
            <div class="mb-4">
              <h4 class="font-bold text-gray-900 mb-2">Clinical Indications</h4>
              <ul class="text-sm text-gray-600 space-y-1">
                <li>• Deep tunneling or undermining wounds</li>
                <li>• Sacral pressure ulcers with depth</li>
                <li>• Post-surgical cavities</li>
                <li>• Any wound with >2cm depth</li>
                <li>• Wounds requiring base-up healing</li>
              </ul>
            </div>
            <div class="bg-green-50 rounded-lg p-4">
              <h4 class="font-bold text-green-900 mb-2">Key Selling Points</h4>
              <ul class="text-sm text-green-800 space-y-1">
                <li>• Fills dead space to prevent abscesses</li>
                <li>• Promotes granulation from wound base</li>
                <li>• Reduces infection risk in deep wounds</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Product 3: Antimicrobial Gel -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border-2 border-purple-200">
          <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-6">
            <div class="flex items-center gap-4">
              <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center text-3xl">💧</div>
              <div>
                <h3 class="text-2xl font-black">Antimicrobial Gel</h3>
                <p class="text-purple-100">HCPCS: A6248, A6249</p>
              </div>
            </div>
          </div>
          <div class="p-6">
            <div class="mb-4">
              <h4 class="font-bold text-gray-900 mb-2">Product Description</h4>
              <p class="text-gray-600 text-sm">
                Collagen-based gel with silver ions for combined wound healing and antimicrobial protection.
                Disrupts bacterial biofilms while promoting tissue regeneration.
              </p>
            </div>
            <div class="mb-4">
              <h4 class="font-bold text-gray-900 mb-2">Clinical Indications</h4>
              <ul class="text-sm text-gray-600 space-y-1">
                <li>• Wounds with signs of infection (odor, drainage)</li>
                <li>• High biofilm risk (diabetic foot ulcers)</li>
                <li>• Immunocompromised patients</li>
                <li>• Stalled wounds despite standard treatment</li>
                <li>• Wounds requiring antimicrobial protection</li>
              </ul>
            </div>
            <div class="bg-purple-50 rounded-lg p-4">
              <h4 class="font-bold text-purple-900 mb-2">Key Selling Points</h4>
              <ul class="text-sm text-purple-800 space-y-1">
                <li>• Two products in one: healing + antimicrobial</li>
                <li>• Cost-effective vs. separate silver dressing</li>
                <li>• Silver ions disrupt bacterial colonies</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Product 4: Collagen Powder -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border-2 border-orange-200">
          <div class="bg-gradient-to-r from-orange-500 to-orange-600 text-white p-6">
            <div class="flex items-center gap-4">
              <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center text-3xl">🧴</div>
              <div>
                <h3 class="text-2xl font-black">Collagen Powder</h3>
                <p class="text-orange-100">HCPCS: A6010</p>
              </div>
            </div>
          </div>
          <div class="p-6">
            <div class="mb-4">
              <h4 class="font-bold text-gray-900 mb-2">Product Description</h4>
              <p class="text-gray-600 text-sm">
                Fine collagen powder for precise application to irregular wound beds.
                Maximum conformability for wounds where sheets cannot reach.
              </p>
            </div>
            <div class="mb-4">
              <h4 class="font-bold text-gray-900 mb-2">Clinical Indications</h4>
              <ul class="text-sm text-gray-600 space-y-1">
                <li>• Irregular wound shapes</li>
                <li>• Sinus tracts or narrow tunnels</li>
                <li>• Wounds around toes or fingers</li>
                <li>• Areas requiring precise dosing</li>
                <li>• Hard-to-reach wound surfaces</li>
              </ul>
            </div>
            <div class="bg-orange-50 rounded-lg p-4">
              <h4 class="font-bold text-orange-900 mb-2">Key Selling Points</h4>
              <ul class="text-sm text-orange-800 space-y-1">
                <li>• Maximum conformability to wound bed</li>
                <li>• No waste from cutting sheets to size</li>
                <li>• Precision application for narrow spaces</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Product Selection Guide -->
      <div class="mt-12 bg-gradient-to-r from-brand-teal to-emerald-500 text-white rounded-xl p-8">
        <h3 class="text-2xl font-bold mb-6">Quick Product Selection Guide</h3>
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-white/10 backdrop-blur rounded-lg p-4">
            <p class="font-bold mb-2">Shallow, flat wound?</p>
            <p class="text-yellow-300 font-bold">→ Collagen Sheets</p>
            <p class="text-xs text-teal-50 mt-1">Diabetic ulcers, pressure ulcers</p>
          </div>
          <div class="bg-white/10 backdrop-blur rounded-lg p-4">
            <p class="font-bold mb-2">Deep with tunneling?</p>
            <p class="text-yellow-300 font-bold">→ Collagen Particles</p>
            <p class="text-xs text-teal-50 mt-1">Sacral ulcers, cavities</p>
          </div>
          <div class="bg-white/10 backdrop-blur rounded-lg p-4">
            <p class="font-bold mb-2">Signs of infection?</p>
            <p class="text-yellow-300 font-bold">→ Antimicrobial Gel</p>
            <p class="text-xs text-teal-50 mt-1">Biofilm, odor, high risk</p>
          </div>
          <div class="bg-white/10 backdrop-blur rounded-lg p-4">
            <p class="font-bold mb-2">Irregular shape?</p>
            <p class="text-yellow-300 font-bold">→ Collagen Powder</p>
            <p class="text-xs text-teal-50 mt-1">Toe wounds, sinus tracts</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Clinical Positioning -->
    <section id="clinical" class="section mb-16 print-break">
      <h2 class="text-4xl font-bold mb-8">Clinical Positioning</h2>

      <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
        <h3 class="text-xl font-bold mb-6 text-brand-navy">The 3 Pain Points We Solve</h3>
        <p class="text-gray-600 mb-6">
          When speaking with physicians, focus on these three problems that CollagenDirect uniquely solves:
        </p>

        <div class="grid md:grid-cols-3 gap-6">
          <div class="border-2 border-red-200 rounded-xl p-6 bg-red-50">
            <div class="text-3xl mb-3">⏰</div>
            <h4 class="font-bold text-red-900 mb-2">Slow Deliveries</h4>
            <p class="text-sm text-red-800 mb-3">
              <strong>Their problem:</strong> Traditional suppliers take 7+ days. Patient comes back, wound has deteriorated.
            </p>
            <p class="text-sm text-red-900 font-semibold">
              <strong>Our solution:</strong> 24-48 hour fulfillment. Order today, treat tomorrow.
            </p>
          </div>

          <div class="border-2 border-amber-200 rounded-xl p-6 bg-amber-50">
            <div class="text-3xl mb-3">💸</div>
            <h4 class="font-bold text-amber-900 mb-2">Insurance Denials</h4>
            <p class="text-sm text-amber-800 mb-3">
              <strong>Their problem:</strong> Claims denied, revenue lost, staff time wasted on appeals.
            </p>
            <p class="text-sm text-amber-900 font-semibold">
              <strong>Our solution:</strong> 98% reimbursement rate with dedicated billing support team.
            </p>
          </div>

          <div class="border-2 border-blue-200 rounded-xl p-6 bg-blue-50">
            <div class="text-3xl mb-3">📋</div>
            <h4 class="font-bold text-blue-900 mb-2">Paperwork Headaches</h4>
            <p class="text-sm text-blue-800 mb-3">
              <strong>Their problem:</strong> Faxing, phone trees, tracking down orders, manual documentation.
            </p>
            <p class="text-sm text-blue-900 font-semibold">
              <strong>Our solution:</strong> Digital portal with automatic documentation and order tracking.
            </p>
          </div>
        </div>
      </div>

      <!-- Talk Tracks -->
      <div class="bg-white rounded-xl shadow-lg p-8">
        <h3 class="text-xl font-bold mb-6 text-brand-navy">Sample Talk Tracks</h3>

        <div class="space-y-6">
          <div class="border-l-4 border-brand-teal pl-6">
            <h4 class="font-bold text-gray-900 mb-2">Opening Statement</h4>
            <p class="text-gray-600 italic">
              "I work with wound care practices to help them get products faster, get paid reliably, and eliminate paperwork headaches.
              Most of the clinics I work with were spending half their day on the phone with suppliers and insurance companies.
              We've helped them cut that down to about 10 minutes."
            </p>
          </div>

          <div class="border-l-4 border-emerald-500 pl-6">
            <h4 class="font-bold text-gray-900 mb-2">Value Proposition</h4>
            <p class="text-gray-600 italic">
              "We ship in 24-48 hours, not 7 days. So if you have a patient coming in Friday and you need products,
              you can order Thursday night and have it Friday morning. Plus, our billing team has a 98% reimbursement rate
              - that means fewer denials and more revenue for your practice."
            </p>
          </div>

          <div class="border-l-4 border-blue-500 pl-6">
            <h4 class="font-bold text-gray-900 mb-2">Handling "We already have a supplier"</h4>
            <p class="text-gray-600 italic">
              "That's great - most practices do. I'm not asking you to switch everything over.
              What I'd like to do is run a pilot: try us for your next 5 patients and see the difference.
              If we're not faster and easier, you've lost nothing. If we are, you've found a better solution."
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Wholesale Pricing -->
    <section id="wholesale" class="section mb-16 print-break">
      <h2 class="text-4xl font-bold mb-8">Wholesale Pricing</h2>

      <div class="bg-gradient-to-r from-amber-50 to-yellow-50 border-l-4 border-amber-500 p-6 rounded-xl mb-8">
        <div class="flex items-start gap-3">
          <svg class="w-6 h-6 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
          <div>
            <h4 class="font-bold text-amber-900 mb-1">Confidential Pricing Information</h4>
            <p class="text-amber-800 text-sm">
              Wholesale pricing is confidential and for authorized distributors only.
              Do not share this information with end customers or competitors.
            </p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="bg-brand-navy text-white p-6">
          <h3 class="text-xl font-bold">Wholesale Price List</h3>
          <p class="text-gray-300 text-sm">Current as of <?= date('F Y') ?></p>
        </div>

        <div class="p-6">
          <p class="text-gray-600 mb-6">
            Contact your account manager for current wholesale pricing and volume discounts.
            Pricing varies by product size, order volume, and distribution agreement terms.
          </p>

          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-gray-100">
                  <th class="text-left p-4 font-bold text-gray-900">Product</th>
                  <th class="text-left p-4 font-bold text-gray-900">Size Options</th>
                  <th class="text-left p-4 font-bold text-gray-900">HCPCS Code</th>
                  <th class="text-left p-4 font-bold text-gray-900">Units/Box</th>
                  <th class="text-center p-4 font-bold text-gray-900">Pricing</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr>
                  <td class="p-4 font-medium">Collagen Sheets</td>
                  <td class="p-4 text-gray-600">1x1, 2x2, 4x4, 4x8 inches</td>
                  <td class="p-4"><code class="bg-gray-100 px-2 py-1 rounded">A6010, A6021</code></td>
                  <td class="p-4 text-gray-600">10-20 per box</td>
                  <td class="p-4 text-center">
                    <span class="bg-brand-teal/10 text-brand-teal px-3 py-1 rounded-full text-xs font-semibold">Contact Sales</span>
                  </td>
                </tr>
                <tr>
                  <td class="p-4 font-medium">Collagen Particles</td>
                  <td class="p-4 text-gray-600">1g, 3g vials</td>
                  <td class="p-4"><code class="bg-gray-100 px-2 py-1 rounded">A6010</code></td>
                  <td class="p-4 text-gray-600">10 per box</td>
                  <td class="p-4 text-center">
                    <span class="bg-brand-teal/10 text-brand-teal px-3 py-1 rounded-full text-xs font-semibold">Contact Sales</span>
                  </td>
                </tr>
                <tr>
                  <td class="p-4 font-medium">Antimicrobial Gel</td>
                  <td class="p-4 text-gray-600">15g, 30g tubes</td>
                  <td class="p-4"><code class="bg-gray-100 px-2 py-1 rounded">A6248, A6249</code></td>
                  <td class="p-4 text-gray-600">5-10 per box</td>
                  <td class="p-4 text-center">
                    <span class="bg-brand-teal/10 text-brand-teal px-3 py-1 rounded-full text-xs font-semibold">Contact Sales</span>
                  </td>
                </tr>
                <tr>
                  <td class="p-4 font-medium">Collagen Powder</td>
                  <td class="p-4 text-gray-600">1g, 5g containers</td>
                  <td class="p-4"><code class="bg-gray-100 px-2 py-1 rounded">A6010</code></td>
                  <td class="p-4 text-gray-600">10 per box</td>
                  <td class="p-4 text-center">
                    <span class="bg-brand-teal/10 text-brand-teal px-3 py-1 rounded-full text-xs font-semibold">Contact Sales</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-6 p-4 bg-gray-50 rounded-lg">
            <h4 class="font-bold text-gray-900 mb-2">Volume Discounts Available</h4>
            <ul class="text-sm text-gray-600 space-y-1">
              <li>• <strong>Tier 1:</strong> Standard wholesale pricing</li>
              <li>• <strong>Tier 2:</strong> 5%+ discount for $5,000+ monthly orders</li>
              <li>• <strong>Tier 3:</strong> 10%+ discount for $15,000+ monthly orders</li>
              <li>• <strong>Custom:</strong> Contact us for high-volume distribution agreements</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Payment Terms -->
      <div class="grid md:grid-cols-2 gap-8 mt-8">
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-bold mb-4 text-brand-navy">Payment Terms</h3>
          <ul class="space-y-3 text-gray-600">
            <li class="flex items-start gap-3">
              <svg class="w-5 h-5 text-brand-teal flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span><strong>Net 30:</strong> Standard payment terms for approved accounts</span>
            </li>
            <li class="flex items-start gap-3">
              <svg class="w-5 h-5 text-brand-teal flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span><strong>Credit Card:</strong> Accepted for all orders</span>
            </li>
            <li class="flex items-start gap-3">
              <svg class="w-5 h-5 text-brand-teal flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span><strong>ACH/Wire:</strong> Available for bulk orders</span>
            </li>
            <li class="flex items-start gap-3">
              <svg class="w-5 h-5 text-brand-teal flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span><strong>2% Discount:</strong> For payment within 10 days</span>
            </li>
          </ul>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-bold mb-4 text-brand-navy">Shipping</h3>
          <ul class="space-y-3 text-gray-600">
            <li class="flex items-start gap-3">
              <svg class="w-5 h-5 text-brand-teal flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span><strong>Free Shipping:</strong> On orders over $500</span>
            </li>
            <li class="flex items-start gap-3">
              <svg class="w-5 h-5 text-brand-teal flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span><strong>Standard:</strong> 2-3 business days (ground)</span>
            </li>
            <li class="flex items-start gap-3">
              <svg class="w-5 h-5 text-brand-teal flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span><strong>Expedited:</strong> Next-day available (extra charge)</span>
            </li>
            <li class="flex items-start gap-3">
              <svg class="w-5 h-5 text-brand-teal flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span><strong>Drop Ship:</strong> Direct to customer available</span>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <!-- How to Order -->
    <section id="ordering" class="section mb-16 print-break">
      <h2 class="text-4xl font-bold mb-8">How to Order</h2>

      <div class="grid md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6 text-center">
          <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-brand-teal to-emerald-500 rounded-xl flex items-center justify-center text-white text-2xl font-black">1</div>
          <h3 class="text-lg font-bold mb-2">Login to Portal</h3>
          <p class="text-gray-600 text-sm">Access your distributor account at <code class="bg-gray-100 px-2 py-1 rounded text-xs">collagendirect.health/admin</code></p>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 text-center">
          <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-brand-teal to-emerald-500 rounded-xl flex items-center justify-center text-white text-2xl font-black">2</div>
          <h3 class="text-lg font-bold mb-2">Create Wholesale Order</h3>
          <p class="text-gray-600 text-sm">Select products, quantities, and shipping destination for your clinic</p>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 text-center">
          <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-brand-teal to-emerald-500 rounded-xl flex items-center justify-center text-white text-2xl font-black">3</div>
          <h3 class="text-lg font-bold mb-2">Track & Receive</h3>
          <p class="text-gray-600 text-sm">Get real-time tracking and manage inventory through your dashboard</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-8">
        <h3 class="text-xl font-bold mb-6 text-brand-navy">Order Methods</h3>

        <div class="grid md:grid-cols-2 gap-6">
          <div class="border rounded-lg p-6">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
              </div>
              <div>
                <h4 class="font-bold text-gray-900">Online Portal</h4>
                <p class="text-sm text-gray-500">Recommended</p>
              </div>
            </div>
            <p class="text-gray-600 text-sm mb-4">
              Use the distributor portal for fastest processing. Orders placed before 2pm ET ship same day.
            </p>
            <a href="/admin/" class="inline-flex items-center text-brand-teal font-semibold text-sm hover:underline">
              Access Portal →
            </a>
          </div>

          <div class="border rounded-lg p-6">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
              </div>
              <div>
                <h4 class="font-bold text-gray-900">Email Order</h4>
                <p class="text-sm text-gray-500">Alternative</p>
              </div>
            </div>
            <p class="text-gray-600 text-sm mb-4">
              Email orders to <a href="mailto:orders@collagendirect.health" class="text-brand-teal hover:underline">orders@collagendirect.health</a> with your account number and PO.
            </p>
            <span class="text-gray-400 text-sm">Response within 2 hours</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Support -->
    <section id="support" class="section mb-16">
      <h2 class="text-4xl font-bold mb-8">Distributor Support</h2>

      <div class="grid md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="w-12 h-12 bg-brand-teal/10 rounded-lg flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-brand-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
            </svg>
          </div>
          <h3 class="font-bold text-gray-900 mb-2">Sales Support</h3>
          <p class="text-gray-600 text-sm mb-4">Questions about pricing, products, or distribution agreements</p>
          <a href="mailto:parker@collagendirect.health" class="text-brand-teal font-semibold text-sm hover:underline">parker@collagendirect.health</a>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="w-12 h-12 bg-brand-teal/10 rounded-lg flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-brand-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
          </div>
          <h3 class="font-bold text-gray-900 mb-2">Order Support</h3>
          <p class="text-gray-600 text-sm mb-4">Order status, shipping, returns, and inventory questions</p>
          <a href="mailto:orders@collagendirect.health" class="text-brand-teal font-semibold text-sm hover:underline">orders@collagendirect.health</a>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="w-12 h-12 bg-brand-teal/10 rounded-lg flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-brand-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
            </svg>
          </div>
          <h3 class="font-bold text-gray-900 mb-2">Clinical Support</h3>
          <p class="text-gray-600 text-sm mb-4">Product applications, clinical questions, and training materials</p>
          <a href="mailto:clinical@collagendirect.health" class="text-brand-teal font-semibold text-sm hover:underline">clinical@collagendirect.health</a>
        </div>
      </div>
    </section>

  </div>

  <!-- Footer -->
  <footer class="bg-brand-navy text-white py-12 no-print">
    <div class="container mx-auto px-6 text-center">
      <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-8 mx-auto mb-4 brightness-0 invert">
      <p class="text-gray-400 text-sm mb-4">Streamlining Wound Care, One Patient at a Time</p>
      <p class="text-gray-500 text-xs">
        &copy; <?= date('Y') ?> CollagenDirect. All rights reserved.<br>
        This guide contains confidential information for authorized distributors only.
      </p>
    </div>
  </footer>

</body>
</html>
