<?php
session_start();
$authorized = false;
$user_email = '';

if (isset($_SESSION['user_email'])) {
    $user_email = $_SESSION['user_email'];
    if (preg_match('/@collagendirect\.health$/i', $user_email)) {
        $authorized = true;
    }
}

if (isset($_GET['email']) && preg_match('/@collagendirect\.health$/i', $_GET['email'])) {
    $_SESSION['user_email'] = $_GET['email'];
    $user_email = $_GET['email'];
    $authorized = true;
}

if (!$authorized) {
    header('Location: login.php');
    exit;
}

$user_name = explode('@', $user_email)[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Product Mastery Quest | CollagenDirect Sales Training</title>
  <meta name="robots" content="noindex, nofollow">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: { teal: '#47c6be', blue: '#2a78ff', navy: '#0a2540' }
          }
        }
      }
    }
  </script>
</head>
<body class="bg-gray-50">

  <!-- Header -->
  <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-8 w-auto">
        <div>
          <div class="text-sm font-bold text-gray-900">Product Mastery Quest</div>
          <div class="text-xs text-gray-500">Level up your product knowledge</div>
        </div>
      </div>
      <div class="flex items-center gap-4">
        <a href="index.php" class="text-sm text-gray-600 hover:text-brand-teal transition">Training Hub</a>
      </div>
    </div>
  </header>

  <div class="max-w-6xl mx-auto px-6 py-12">

    <!-- Hero -->
    <div class="bg-gradient-to-r from-purple-600 via-pink-500 to-red-500 rounded-3xl p-12 text-white mb-12 text-center relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-purple-900/20 to-transparent"></div>
      <div class="relative z-10">
        <div class="text-6xl mb-4">🎯</div>
        <h1 class="text-5xl font-black mb-4">Product Mastery Quest</h1>
        <p class="text-xl text-pink-50 max-w-3xl mx-auto mb-6">
          Master our products and unlock your earning potential! Each level builds your confidence and helps you deliver MORE VALUE to physicians.
        </p>
        <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur px-6 py-3 rounded-xl">
          <span class="font-semibold">Your Progress: <span id="total-progress">0</span>% Complete</span>
        </div>
      </div>
    </div>

    <!-- Progress Tracker -->
    <div class="bg-white rounded-3xl shadow-xl p-8 mb-10">
      <h2 class="text-2xl font-black text-gray-900 mb-6">Your Learning Path</h2>
      <div class="grid md:grid-cols-4 gap-4">
        <div class="bg-gray-50 rounded-xl p-4 text-center">
          <div class="text-3xl mb-2">📚</div>
          <div class="text-sm text-gray-600 mb-1">Modules Completed</div>
          <div class="text-2xl font-black text-brand-teal"><span id="modules-complete">0</span>/4</div>
        </div>
        <div class="bg-gray-50 rounded-xl p-4 text-center">
          <div class="text-3xl mb-2">⭐</div>
          <div class="text-sm text-gray-600 mb-1">Knowledge Points</div>
          <div class="text-2xl font-black text-yellow-500"><span id="knowledge-points">0</span></div>
        </div>
        <div class="bg-gray-50 rounded-xl p-4 text-center">
          <div class="text-3xl mb-2">🏆</div>
          <div class="text-sm text-gray-600 mb-1">Achievements</div>
          <div class="text-2xl font-black text-purple-600"><span id="achievements">0</span>/12</div>
        </div>
        <div class="bg-gray-50 rounded-xl p-4 text-center">
          <div class="text-3xl mb-2">🎓</div>
          <div class="text-sm text-gray-600 mb-1">Current Level</div>
          <div class="text-2xl font-black text-blue-600"><span id="current-level">Beginner</span></div>
        </div>
      </div>
    </div>

    <!-- Level 1: Foundation -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10 border-4 border-green-500">
      <div class="flex items-start gap-6 mb-8">
        <div class="w-20 h-20 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center flex-shrink-0 text-4xl">
          1️⃣
        </div>
        <div class="flex-1">
          <div class="inline-flex items-center gap-2 bg-green-100 px-4 py-1 rounded-full text-sm font-bold text-green-700 mb-2">
            LEVEL 1: FOUNDATION
          </div>
          <h2 class="text-3xl font-black text-gray-900 mb-2">The Value We Deliver</h2>
          <p class="text-gray-600">Understand the core value proposition that makes physicians choose CollagenDirect</p>
        </div>
        <label class="flex items-center cursor-pointer">
          <input type="checkbox" class="w-6 h-6 text-green-600 rounded module-checkbox" data-module="1" data-points="25">
          <span class="ml-2 text-sm text-gray-600">Mark Complete</span>
        </label>
      </div>

      <div class="space-y-6">
        <div class="bg-gradient-to-r from-brand-teal to-emerald-500 text-white p-8 rounded-2xl">
          <h3 class="text-2xl font-black mb-4">🎯 The VALUE Framework</h3>
          <p class="text-lg mb-6">Our value isn't about collagen—it's about solving physician PAIN. Remember this framework:</p>

          <div class="grid md:grid-cols-3 gap-6">
            <div class="bg-white/10 backdrop-blur rounded-xl p-6">
              <div class="text-4xl mb-3">⚡</div>
              <h4 class="font-bold text-lg mb-2">Speed = Patient Care</h4>
              <p class="text-sm text-teal-50">24-48 hour delivery means they can treat patients THIS WEEK, not next week. That's faster healing, happier patients, better outcomes.</p>
            </div>

            <div class="bg-white/10 backdrop-blur rounded-xl p-6">
              <div class="text-4xl mb-3">💰</div>
              <h4 class="font-bold text-lg mb-2">Zero Denials = Cash Flow</h4>
              <p class="text-sm text-teal-50">We handle all insurance verification BEFORE they order. No surprise denials = predictable revenue = peace of mind.</p>
            </div>

            <div class="bg-white/10 backdrop-blur rounded-xl p-6">
              <div class="text-4xl mb-3">⏰</div>
              <h4 class="font-bold text-lg mb-2">Time = Money</h4>
              <p class="text-sm text-teal-50">Their staff spends HOURS on phone orders and paperwork. Our 2-minute portal = more time for patient care, less administrative cost.</p>
            </div>
          </div>
        </div>

        <div class="bg-yellow-50 border-2 border-yellow-500 p-6 rounded-xl">
          <h4 class="font-bold text-yellow-900 mb-3">💡 Mindset Shift: You're Not Selling Collagen</h4>
          <p class="text-sm text-yellow-800 mb-4">
            When you walk into a clinic, you're not there to sell them wound dressing. You're there to offer them:
          </p>
          <ul class="space-y-2 text-sm text-yellow-800">
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span><strong>More revenue:</strong> Faster billing, fewer denials, better reimbursement rates</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span><strong>Better patient outcomes:</strong> Faster healing = happier patients = more referrals</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span><strong>Staff efficiency:</strong> 2-minute orders vs 20-minute phone calls</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span><strong>Peace of mind:</strong> Products arrive when needed, insurance covered, no hassle</span>
            </li>
          </ul>
          <p class="text-sm text-yellow-900 font-bold mt-4">👉 THIS is what physicians buy. The collagen is just HOW we deliver it.</p>
        </div>

        <div class="bg-blue-50 border-l-4 border-blue-600 p-6 rounded-r-xl">
          <h4 class="font-bold text-blue-900 mb-3">🎓 Knowledge Check</h4>
          <p class="text-sm text-blue-800 mb-4">What are the 3 core values we deliver?</p>
          <div class="space-y-2 text-sm">
            <div class="bg-white p-3 rounded border-2 border-blue-200">
              <strong>1. Speed:</strong> 24-48 hour delivery (vs 5-7 days)
            </div>
            <div class="bg-white p-3 rounded border-2 border-blue-200">
              <strong>2. Financial Certainty:</strong> Pre-verified insurance, zero surprise denials
            </div>
            <div class="bg-white p-3 rounded border-2 border-blue-200">
              <strong>3. Simplicity:</strong> 2-minute portal vs hours of phone calls and paperwork
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Level 2: Product Knowledge -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10 border-4 border-blue-500">
      <div class="flex items-start gap-6 mb-8">
        <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center flex-shrink-0 text-4xl">
          2️⃣
        </div>
        <div class="flex-1">
          <div class="inline-flex items-center gap-2 bg-blue-100 px-4 py-1 rounded-full text-sm font-bold text-blue-700 mb-2">
            LEVEL 2: PRODUCT MASTERY
          </div>
          <h2 class="text-3xl font-black text-gray-900 mb-2">The 4 Solutions (Not Products!)</h2>
          <p class="text-gray-600">Learn how each product solves specific physician challenges</p>
        </div>
        <label class="flex items-center cursor-pointer">
          <input type="checkbox" class="w-6 h-6 text-blue-600 rounded module-checkbox" data-module="2" data-points="25">
          <span class="ml-2 text-sm text-gray-600">Mark Complete</span>
        </label>
      </div>

      <div class="grid md:grid-cols-2 gap-6">
        <!-- Product 1: Collagen Sheets -->
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 border-2 border-blue-300">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-16 h-16 bg-blue-500 rounded-xl flex items-center justify-center text-3xl">📄</div>
            <div>
              <h3 class="text-xl font-black text-blue-900">Collagen Sheets</h3>
              <p class="text-xs text-blue-700">HCPCS: A6010, A6021</p>
            </div>
          </div>

          <div class="space-y-4">
            <div class="bg-white p-4 rounded-xl">
              <p class="text-sm font-bold text-blue-900 mb-2">💪 The VALUE They Get:</p>
              <ul class="text-xs text-gray-700 space-y-1">
                <li>• <strong>Faster healing:</strong> Cuts healing time 30-50% (peer-reviewed studies)</li>
                <li>• <strong>Predictable outcomes:</strong> Reliable granulation tissue formation</li>
                <li>• <strong>Easy billing:</strong> Covered by Medicare/Medicaid for chronic wounds</li>
              </ul>
            </div>

            <div class="bg-white p-4 rounded-xl">
              <p class="text-sm font-bold text-blue-900 mb-2">🎯 When Physicians Choose This:</p>
              <ul class="text-xs text-gray-700 space-y-1">
                <li>• Patient has diabetic foot ulcer (most common)</li>
                <li>• Pressure ulcers (Stage 3-4)</li>
                <li>• Venous leg ulcers not healing with compression alone</li>
                <li>• Surgical wounds with delayed healing</li>
              </ul>
            </div>

            <div class="bg-yellow-50 p-4 rounded-xl border-2 border-yellow-400">
              <p class="text-xs font-bold text-yellow-900 mb-2">🗣️ VALUE TALK TRACK:</p>
              <p class="text-xs italic text-yellow-800">"Most of our clinics see diabetic ulcers heal 40% faster with collagen sheets compared to standard dressings. That means fewer visits, happier patients, and better reimbursement for you. Plus, it's a straightforward Medicare approval—no prior auth headaches."</p>
            </div>
          </div>
        </div>

        <!-- Product 2: Collagen Particles -->
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl p-6 border-2 border-green-300">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-16 h-16 bg-green-500 rounded-xl flex items-center justify-center text-3xl">🧪</div>
            <div>
              <h3 class="text-xl font-black text-green-900">Collagen Particles</h3>
              <p class="text-xs text-green-700">HCPCS: A6010</p>
            </div>
          </div>

          <div class="space-y-4">
            <div class="bg-white p-4 rounded-xl">
              <p class="text-sm font-bold text-green-900 mb-2">💪 The VALUE They Get:</p>
              <ul class="text-xs text-gray-700 space-y-1">
                <li>• <strong>Fills dead space:</strong> Prevents abscesses in deep wounds</li>
                <li>• <strong>Heals from inside out:</strong> Granulation starts at wound base</li>
                <li>• <strong>Reduces infection risk:</strong> No hollow spaces for bacteria</li>
              </ul>
            </div>

            <div class="bg-white p-4 rounded-xl">
              <p class="text-sm font-bold text-green-900 mb-2">🎯 When Physicians Choose This:</p>
              <ul class="text-xs text-gray-700 space-y-1">
                <li>• Deep tunneling or undermining wounds</li>
                <li>• Sacral pressure ulcers with depth</li>
                <li>• Post-surgical cavities</li>
                <li>• Any wound with >2cm depth</li>
              </ul>
            </div>

            <div class="bg-yellow-50 p-4 rounded-xl border-2 border-yellow-400">
              <p class="text-xs font-bold text-yellow-900 mb-2">🗣️ VALUE TALK TRACK:</p>
              <p class="text-xs italic text-yellow-800">"When you have a deep pressure ulcer, sheets won't reach the base. Particles fill that dead space and promote healing from the bottom up—which is exactly what you need to prevent infection and close the wound properly."</p>
            </div>
          </div>
        </div>

        <!-- Product 3: Antimicrobial Gel -->
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-2xl p-6 border-2 border-purple-300">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-16 h-16 bg-purple-500 rounded-xl flex items-center justify-center text-3xl">💧</div>
            <div>
              <h3 class="text-xl font-black text-purple-900">Antimicrobial Gel</h3>
              <p class="text-xs text-purple-700">HCPCS: A6248, A6249</p>
            </div>
          </div>

          <div class="space-y-4">
            <div class="bg-white p-4 rounded-xl">
              <p class="text-sm font-bold text-purple-900 mb-2">💪 The VALUE They Get:</p>
              <ul class="text-xs text-gray-700 space-y-1">
                <li>• <strong>Two products in one:</strong> Collagen healing + antimicrobial protection</li>
                <li>• <strong>Saves money:</strong> No need to buy separate silver dressing</li>
                <li>• <strong>Reduces biofilm:</strong> Silver ions disrupt bacterial colonies</li>
              </ul>
            </div>

            <div class="bg-white p-4 rounded-xl">
              <p class="text-sm font-bold text-purple-900 mb-2">🎯 When Physicians Choose This:</p>
              <ul class="text-xs text-gray-700 space-y-1">
                <li>• Wounds with signs of infection (odor, drainage, redness)</li>
                <li>• High biofilm risk (diabetic foot ulcers)</li>
                <li>• Immunocompromised patients</li>
                <li>• Wounds stalled despite standard treatment</li>
              </ul>
            </div>

            <div class="bg-yellow-50 p-4 rounded-xl border-2 border-yellow-400">
              <p class="text-xs font-bold text-yellow-900 mb-2">🗣️ VALUE TALK TRACK:</p>
              <p class="text-xs italic text-yellow-800">"If you're seeing biofilm or early infection, this gives you both antimicrobial protection AND collagen healing in one product. Your patients get better outcomes, you save on ordering two separate products, and it's all covered under the same HCPCS code."</p>
            </div>
          </div>
        </div>

        <!-- Product 4: Collagen Powder -->
        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-2xl p-6 border-2 border-orange-300">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-16 h-16 bg-orange-500 rounded-xl flex items-center justify-center text-3xl">🧴</div>
            <div>
              <h3 class="text-xl font-black text-orange-900">Collagen Powder</h3>
              <p class="text-xs text-orange-700">HCPCS: A6010</p>
            </div>
          </div>

          <div class="space-y-4">
            <div class="bg-white p-4 rounded-xl">
              <p class="text-sm font-bold text-orange-900 mb-2">💪 The VALUE They Get:</p>
              <ul class="text-xs text-gray-700 space-y-1">
                <li>• <strong>Maximum conformability:</strong> Reaches irregular wound beds</li>
                <li>• <strong>Precision application:</strong> Use exactly what's needed</li>
                <li>• <strong>Cost-effective:</strong> No waste from cutting sheets to size</li>
              </ul>
            </div>

            <div class="bg-white p-4 rounded-xl">
              <p class="text-sm font-bold text-orange-900 mb-2">🎯 When Physicians Choose This:</p>
              <ul class="text-xs text-gray-700 space-y-1">
                <li>• Irregular wound shapes</li>
                <li>• Sinus tracts or narrow tunnels</li>
                <li>• Wounds around toes or fingers</li>
                <li>• When precise dosing matters</li>
              </ul>
            </div>

            <div class="bg-yellow-50 p-4 rounded-xl border-2 border-yellow-400">
              <p class="text-xs font-bold text-yellow-900 mb-2">🗣️ VALUE TALK TRACK:</p>
              <p class="text-xs italic text-yellow-800">"For those oddly-shaped wounds where sheets don't conform well, powder gives you precision. You can dust it exactly where you need it, get into narrow spaces, and you're not wasting product by cutting sheets down to size."</p>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-8 bg-gradient-to-r from-brand-teal to-emerald-500 text-white p-6 rounded-2xl">
        <h4 class="font-bold text-lg mb-3">🎯 Quick Decision Tree for Physicians</h4>
        <div class="grid md:grid-cols-2 gap-4 text-sm">
          <div class="bg-white/10 backdrop-blur rounded-lg p-4">
            <p class="font-bold mb-2">Shallow, flat wound? → <span class="text-yellow-300">Collagen Sheets</span></p>
            <p class="text-xs text-teal-50">Diabetic ulcers, pressure ulcers, venous ulcers</p>
          </div>
          <div class="bg-white/10 backdrop-blur rounded-lg p-4">
            <p class="font-bold mb-2">Deep wound with tunneling? → <span class="text-yellow-300">Collagen Particles</span></p>
            <p class="text-xs text-teal-50">Sacral ulcers, post-surgical cavities</p>
          </div>
          <div class="bg-white/10 backdrop-blur rounded-lg p-4">
            <p class="font-bold mb-2">Signs of infection? → <span class="text-yellow-300">Antimicrobial Gel</span></p>
            <p class="text-xs text-teal-50">Biofilm, odor, drainage, high infection risk</p>
          </div>
          <div class="bg-white/10 backdrop-blur rounded-lg p-4">
            <p class="font-bold mb-2">Irregular shape or sinus tract? → <span class="text-yellow-300">Collagen Powder</span></p>
            <p class="text-xs text-teal-50">Toe wounds, tunnels, precision needed</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Level 3: Clinical Evidence -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10 border-4 border-purple-500">
      <div class="flex items-start gap-6 mb-8">
        <div class="w-20 h-20 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center flex-shrink-0 text-4xl">
          3️⃣
        </div>
        <div class="flex-1">
          <div class="inline-flex items-center gap-2 bg-purple-100 px-4 py-1 rounded-full text-sm font-bold text-purple-700 mb-2">
            LEVEL 3: CLINICAL CONFIDENCE
          </div>
          <h2 class="text-3xl font-black text-gray-900 mb-2">Backing Your Claims with Science</h2>
          <p class="text-gray-600">Build trust with evidence-based selling</p>
        </div>
        <label class="flex items-center cursor-pointer">
          <input type="checkbox" class="w-6 h-6 text-purple-600 rounded module-checkbox" data-module="3" data-points="25">
          <span class="ml-2 text-sm text-gray-600">Mark Complete</span>
        </label>
      </div>

      <div class="space-y-6">
        <div class="bg-purple-50 border-2 border-purple-300 p-6 rounded-xl">
          <h3 class="font-bold text-lg text-purple-900 mb-4">📊 The Data Physicians Trust</h3>
          <div class="grid md:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded-lg">
              <div class="text-3xl mb-2">40%</div>
              <p class="text-sm font-bold text-gray-900 mb-1">Faster Healing Time</p>
              <p class="text-xs text-gray-600">Diabetic foot ulcers with collagen vs standard care (Veves et al., Diabetes Care 2002)</p>
            </div>
            <div class="bg-white p-4 rounded-lg">
              <div class="text-3xl mb-2">FDA</div>
              <p class="text-sm font-bold text-gray-900 mb-1">Cleared Devices</p>
              <p class="text-xs text-gray-600">All products are FDA-cleared Class II medical devices for wound management</p>
            </div>
            <div class="bg-white p-4 rounded-lg">
              <div class="text-3xl mb-2">99%</div>
              <p class="text-sm font-bold text-gray-900 mb-1">Bioavailability</p>
              <p class="text-xs text-gray-600">Type I collagen is highly bioavailable and integrates with native tissue</p>
            </div>
          </div>
        </div>

        <div class="bg-blue-50 border-l-4 border-blue-600 p-6 rounded-r-xl">
          <h4 class="font-bold text-blue-900 mb-3">💡 How to Use Clinical Evidence (Value Selling Style)</h4>
          <p class="text-sm text-blue-800 mb-4">Don't overwhelm physicians with data. Instead, connect evidence to their OUTCOMES:</p>
          <div class="space-y-3">
            <div class="bg-white p-4 rounded-lg">
              <p class="text-sm font-bold text-gray-900 mb-2">❌ Product-Focused (Don't Do This):</p>
              <p class="text-xs italic text-gray-600">"Our collagen has 99% bioavailability and is a type I bovine dermal matrix..."</p>
            </div>
            <div class="bg-white p-4 rounded-lg border-2 border-green-500">
              <p class="text-sm font-bold text-green-900 mb-2">✅ Value-Focused (Do This):</p>
              <p class="text-xs italic text-gray-700">"In peer-reviewed studies, clinics using collagen saw diabetic ulcers heal 40% faster than standard dressings. That means your patients get back to normal life sooner, you have fewer follow-up visits, and you can see more patients per week."</p>
            </div>
          </div>
        </div>

        <div class="bg-yellow-50 border-2 border-yellow-500 p-6 rounded-xl">
          <h4 class="font-bold text-yellow-900 mb-3">🎯 Clinical Evidence Cheat Sheet</h4>
          <div class="space-y-2 text-sm">
            <div class="flex items-start gap-3 bg-white p-3 rounded">
              <span class="text-xl">📄</span>
              <div>
                <p class="font-bold text-gray-900">When they ask about diabetic foot ulcers:</p>
                <p class="text-xs text-gray-700">"Veves study in Diabetes Care showed 40% faster healing vs standard care. That's published, peer-reviewed data."</p>
              </div>
            </div>
            <div class="flex items-start gap-3 bg-white p-3 rounded">
              <span class="text-xl">🧪</span>
              <div>
                <p class="font-bold text-gray-900">When they ask about safety:</p>
                <p class="text-xs text-gray-700">"All FDA-cleared Class II devices. Used in over 10,000 wound care clinics nationwide with excellent safety profile."</p>
              </div>
            </div>
            <div class="flex items-start gap-3 bg-white p-3 rounded">
              <span class="text-xl">💧</span>
              <div>
                <p class="font-bold text-gray-900">When they ask about infection risk with antimicrobial gel:</p>
                <p class="text-xs text-gray-700">"Silver ions have broad-spectrum antimicrobial activity against biofilm. Reduces bacterial load while collagen promotes healing—dual benefit in one product."</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Level 4: Real-World Application -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10 border-4 border-red-500">
      <div class="flex items-start gap-6 mb-8">
        <div class="w-20 h-20 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center flex-shrink-0 text-4xl">
          4️⃣
        </div>
        <div class="flex-1">
          <div class="inline-flex items-center gap-2 bg-red-100 px-4 py-1 rounded-full text-sm font-bold text-red-700 mb-2">
            LEVEL 4: MASTER PRACTITIONER
          </div>
          <h2 class="text-3xl font-black text-gray-900 mb-2">Real Conversations, Real Results</h2>
          <p class="text-gray-600">See how top reps use value selling in the field</p>
        </div>
        <label class="flex items-center cursor-pointer">
          <input type="checkbox" class="w-6 h-6 text-red-600 rounded module-checkbox" data-module="4" data-points="25">
          <span class="ml-2 text-sm text-gray-600">Mark Complete</span>
        </label>
      </div>

      <div class="space-y-6">
        <div class="bg-gradient-to-r from-red-50 to-orange-50 border-2 border-red-300 p-6 rounded-xl">
          <h3 class="font-bold text-lg text-red-900 mb-4">🎬 Role Play Scenario #1: Podiatrist with Diabetic Foot Ulcer Patients</h3>

          <div class="space-y-4">
            <div class="bg-white p-4 rounded-lg">
              <p class="text-xs font-bold text-gray-500 mb-2">YOU:</p>
              <p class="text-sm text-gray-800">"Dr. Johnson, I noticed you see a lot of diabetic foot ulcer patients. How long do they typically take to heal with your current treatment protocol?"</p>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg">
              <p class="text-xs font-bold text-gray-500 mb-2">DOCTOR:</p>
              <p class="text-sm text-gray-700">"Honestly, too long. We're looking at 8-12 weeks on average, sometimes longer if they're non-compliant."</p>
            </div>

            <div class="bg-white p-4 rounded-lg border-2 border-green-500">
              <p class="text-xs font-bold text-green-700 mb-2">YOU (VALUE RESPONSE):</p>
              <p class="text-sm text-gray-800 mb-3">"That matches what we hear. Here's what's interesting—clinics using collagen sheets are seeing those same ulcers heal in 5-7 weeks. That's a 40% reduction in healing time."</p>
              <p class="text-sm text-gray-800 mb-3">"For you, that means: fewer follow-up visits per patient, better outcomes, and you can take on more new patients because you're not seeing the same folks for 3 months."</p>
              <p class="text-sm text-gray-800">"Plus, it's a straightforward Medicare approval—A6010 code, covered for chronic wounds, and we handle all the paperwork. Want me to show you how easy the ordering process is?"</p>
            </div>
          </div>

          <div class="mt-4 bg-yellow-100 border-l-4 border-yellow-600 p-4 rounded-r-lg">
            <p class="text-xs font-bold text-yellow-900 mb-2">💡 Why This Works:</p>
            <ul class="text-xs text-yellow-800 space-y-1">
              <li>• Started with discovery (find the pain)</li>
              <li>• Connected product to THEIR outcome (fewer visits, more patients)</li>
              <li>• Removed friction (insurance handled)</li>
              <li>• Clear call-to-action (show the portal)</li>
            </ul>
          </div>
        </div>

        <div class="bg-gradient-to-r from-blue-50 to-purple-50 border-2 border-blue-300 p-6 rounded-xl">
          <h3 class="font-bold text-lg text-blue-900 mb-4">🎬 Role Play Scenario #2: Wound Care Clinic with Ordering Frustration</h3>

          <div class="space-y-4">
            <div class="bg-white p-4 rounded-lg">
              <p class="text-xs font-bold text-gray-500 mb-2">YOU:</p>
              <p class="text-sm text-gray-800">"How's your current wound care supply ordering process working for you?"</p>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg">
              <p class="text-xs font-bold text-gray-500 mb-2">NURSE MANAGER:</p>
              <p class="text-sm text-gray-700">"Ugh, it's a nightmare. We spend half the day on the phone, then we wait a week for products to arrive. Sometimes they deny the claim and we don't find out until 30 days later."</p>
            </div>

            <div class="bg-white p-4 rounded-lg border-2 border-green-500">
              <p class="text-xs font-bold text-green-700 mb-2">YOU (VALUE RESPONSE):</p>
              <p class="text-sm text-gray-800 mb-3">"That's exactly the problem we solve. Let me show you something..."</p>
              <p class="text-sm text-gray-800 mb-3">"Our portal takes 2 minutes to order—click the product, enter patient info, done. No phone calls. We verify insurance eligibility BEFORE you submit, so you know upfront if it's covered. No surprise denials 30 days later."</p>
              <p class="text-sm text-gray-800 mb-3">"And we ship in 24-48 hours, not 7 days. So if you have a patient coming in Friday and you need products, you can order Thursday night and have it Friday morning."</p>
              <p class="text-sm text-gray-800">"How much time would that save your staff per week?"</p>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg">
              <p class="text-xs font-bold text-gray-500 mb-2">NURSE MANAGER:</p>
              <p class="text-sm text-gray-700">"Probably 10-15 hours. That's half a person's job just dealing with supply orders."</p>
            </div>

            <div class="bg-white p-4 rounded-lg border-2 border-green-500">
              <p class="text-xs font-bold text-green-700 mb-2">YOU (CLOSE):</p>
              <p class="text-sm text-gray-800">"Exactly. That's 10-15 hours your staff can spend on patient care instead of phone calls. Want to try it with one patient this week and see how it compares?"</p>
            </div>
          </div>

          <div class="mt-4 bg-yellow-100 border-l-4 border-yellow-600 p-4 rounded-r-lg">
            <p class="text-xs font-bold text-yellow-900 mb-2">💡 Why This Works:</p>
            <ul class="text-xs text-yellow-800 space-y-1">
              <li>• Let them articulate their pain (validation)</li>
              <li>• Directly addressed each pain point (speed, insurance, simplicity)</li>
              <li>• Quantified the value (10-15 hours saved per week)</li>
              <li>• Low-risk trial close (just one patient)</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Completion Badge -->
    <div class="bg-gradient-to-r from-yellow-400 via-orange-500 to-red-500 rounded-3xl p-12 text-white text-center" id="completion-badge" style="display: none;">
      <div class="text-8xl mb-6">🏆</div>
      <h2 class="text-4xl font-black mb-4">Product Master Unlocked!</h2>
      <p class="text-xl mb-6">You've completed all 4 levels! You're ready to deliver MASSIVE value to physicians.</p>
      <div class="inline-flex items-center gap-4 bg-white/20 backdrop-blur px-8 py-4 rounded-2xl">
        <div class="text-center">
          <div class="text-3xl font-black">100</div>
          <div class="text-sm">Knowledge Points</div>
        </div>
        <div class="text-center">
          <div class="text-3xl font-black">🎓</div>
          <div class="text-sm">Product Master</div>
        </div>
      </div>
      <p class="text-sm mt-8 text-orange-100">Next: Check out <a href="objections.php" class="underline font-bold">Objection Mastery</a> and <a href="success-stories.php" class="underline font-bold">Success Stories</a></p>
    </div>

  </div>

  <script>
    // Gamification Logic
    const checkboxes = document.querySelectorAll('.module-checkbox');
    const progressEl = document.getElementById('total-progress');
    const modulesEl = document.getElementById('modules-complete');
    const pointsEl = document.getElementById('knowledge-points');
    const achievementsEl = document.getElementById('achievements');
    const levelEl = document.getElementById('current-level');
    const completionBadge = document.getElementById('completion-badge');

    // Load saved progress
    const savedProgress = JSON.parse(localStorage.getItem('product_training_progress') || '{}');
    checkboxes.forEach((checkbox, index) => {
      if (savedProgress[index]) {
        checkbox.checked = true;
      }
    });

    function updateProgress() {
      const completed = Array.from(checkboxes).filter(cb => cb.checked).length;
      const total = checkboxes.length;
      const percent = Math.round((completed / total) * 100);
      const points = completed * 25;

      progressEl.textContent = percent;
      modulesEl.textContent = completed;
      pointsEl.textContent = points;
      achievementsEl.textContent = completed * 3; // 3 achievements per module

      // Level progression
      if (completed === 0) levelEl.textContent = 'Beginner';
      else if (completed === 1) levelEl.textContent = 'Learner';
      else if (completed === 2) levelEl.textContent = 'Practitioner';
      else if (completed === 3) levelEl.textContent = 'Expert';
      else if (completed === 4) {
        levelEl.textContent = 'Product Master';
        completionBadge.style.display = 'block';
        completionBadge.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }

      // Save progress
      const progress = {};
      checkboxes.forEach((cb, index) => {
        progress[index] = cb.checked;
      });
      localStorage.setItem('product_training_progress', JSON.stringify(progress));
    }

    checkboxes.forEach(checkbox => {
      checkbox.addEventListener('change', updateProgress);
    });

    // Initial update
    updateProgress();
  </script>

</body>
</html>
