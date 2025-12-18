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
  <title>Quick Start Guide | CollagenDirect Sales Training</title>
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
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: white; }
    }
  </style>
</head>
<body class="bg-gray-50">

  <!-- Header -->
  <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-8 w-auto">
        <div>
          <div class="text-sm font-bold text-gray-900">Quick Start Guide</div>
          <div class="text-xs text-gray-500">Get selling in 30 minutes</div>
        </div>
      </div>
      <div class="flex items-center gap-4 no-print">
        <a href="index.php" class="text-sm text-gray-600 hover:text-brand-teal transition">Training Hub</a>
        <button onclick="window.print()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">Print Guide</button>
      </div>
    </div>
  </header>

  <div class="max-w-6xl mx-auto px-6 py-12">

    <!-- Hero -->
    <div class="bg-gradient-to-r from-orange-500 to-red-500 rounded-3xl p-12 text-white mb-12 text-center">
      <div class="text-6xl mb-4">🚀</div>
      <h1 class="text-5xl font-black mb-4">Quick Start Guide</h1>
      <p class="text-xl text-orange-50 max-w-3xl mx-auto mb-6">
        Everything you need to start making sales calls TODAY. Read this first, worry about the deep training later.
      </p>
      <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur px-6 py-3 rounded-xl">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span class="font-semibold">Read Time: 30 minutes</span>
      </div>
    </div>

    <!-- The One Thing -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10 border-4 border-brand-teal">
      <div class="text-center">
        <div class="text-4xl mb-4">💡</div>
        <h2 class="text-3xl font-black text-gray-900 mb-4">The ONE Thing You Need to Remember</h2>
        <div class="bg-brand-teal text-white p-8 rounded-2xl max-w-3xl mx-auto">
          <p class="text-2xl font-bold leading-relaxed">
            "We don't sell products.<br>We solve physician pain points."
          </p>
        </div>
        <p class="text-lg text-gray-600 mt-6 max-w-2xl mx-auto">
          Doctors don't care about collagen. They care about: <strong>slow deliveries</strong>, <strong>insurance denials</strong>, and <strong>paperwork headaches</strong>. That's what you solve.
        </p>
      </div>
    </div>

    <!-- Your First Week -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <h2 class="text-3xl font-black text-gray-900 mb-8">Your First Week Strategy</h2>

      <div class="space-y-6">
        <!-- Day 1 -->
        <div class="border-l-4 border-green-500 bg-green-50 p-6 rounded-r-lg">
          <h3 class="text-xl font-bold text-green-900 mb-3">Day 1: Learn the Basics (2 hours)</h3>
          <ul class="space-y-2 text-sm text-green-800">
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span>Memorize the <strong>4 products</strong> and their <strong>HCPCS codes</strong> (see Quick Reference below)</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span>Memorize the <strong>3 pain points</strong> (delivery speed, reimbursement, paperwork)</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span>Practice the <strong>cold call script</strong> 5 times out loud</span>
            </li>
          </ul>
        </div>

        <!-- Day 2 -->
        <div class="border-l-4 border-blue-500 bg-blue-50 p-6 rounded-r-lg">
          <h3 class="text-xl font-bold text-blue-900 mb-3">Day 2-3: Make Your First Calls (20 calls/day)</h3>
          <ul class="space-y-2 text-sm text-blue-800">
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span><strong>Goal:</strong> Book 2 lunch meetings or phone calls with doctors</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span>Target: Wound care clinics, podiatrists, vascular surgeons</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span>Use the gatekeeper script (see below)</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span>Don't pitch yet - just get the meeting</span>
            </li>
          </ul>
        </div>

        <!-- Day 4-5 -->
        <div class="border-l-4 border-purple-500 bg-purple-50 p-6 rounded-r-lg">
          <h3 class="text-xl font-bold text-purple-900 mb-3">Day 4-5: First Doctor Meetings (3-5 meetings)</h3>
          <ul class="space-y-2 text-sm text-purple-800">
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span>Ask the <strong>4 discovery questions</strong> (see below) - LISTEN more than you talk</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span>Respond to their pain points with how you solve them</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span>Help them register on the portal (hands-on assistance)</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="font-bold">✓</span>
              <span><strong>Goal:</strong> 1-2 new registrations by end of week</span>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- The 4 Products (Memorize This) -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <h2 class="text-3xl font-black text-gray-900 mb-8">The 4 Products (Memorize This)</h2>

      <div class="grid md:grid-cols-2 gap-6">
        <!-- Product 1 -->
        <div class="border-2 border-gray-300 rounded-2xl p-6 hover:border-brand-teal transition">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-2xl">📄</div>
            <div>
              <h3 class="font-black text-lg">Collagen Sheets</h3>
              <p class="text-xs text-gray-500">HCPCS: A6010, A6021</p>
            </div>
          </div>
          <p class="text-sm text-gray-700 mb-3"><strong>What it is:</strong> Flat collagen matrix for wound beds</p>
          <p class="text-sm text-gray-700 mb-3"><strong>When to use:</strong> Diabetic ulcers, pressure ulcers, surgical wounds</p>
          <p class="text-sm text-gray-700"><strong>Your pitch:</strong> "Accelerates granulation tissue formation, cuts healing time in half"</p>
        </div>

        <!-- Product 2 -->
        <div class="border-2 border-gray-300 rounded-2xl p-6 hover:border-brand-teal transition">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center text-2xl">🧪</div>
            <div>
              <h3 class="font-black text-lg">Collagen Particles</h3>
              <p class="text-xs text-gray-500">HCPCS: A6010</p>
            </div>
          </div>
          <p class="text-sm text-gray-700 mb-3"><strong>What it is:</strong> Granulated collagen for deep wounds</p>
          <p class="text-sm text-gray-700 mb-3"><strong>When to use:</strong> Tunneling wounds, deep cavities, undermining</p>
          <p class="text-sm text-gray-700"><strong>Your pitch:</strong> "Fills dead space, promotes healing from the inside out"</p>
        </div>

        <!-- Product 3 -->
        <div class="border-2 border-gray-300 rounded-2xl p-6 hover:border-brand-teal transition">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center text-2xl">💧</div>
            <div>
              <h3 class="font-black text-lg">Antimicrobial Gel</h3>
              <p class="text-xs text-gray-500">HCPCS: A6248, A6249</p>
            </div>
          </div>
          <p class="text-sm text-gray-700 mb-3"><strong>What it is:</strong> Collagen + silver for infected wounds</p>
          <p class="text-sm text-gray-700 mb-3"><strong>When to use:</strong> Wounds with infection or biofilm</p>
          <p class="text-sm text-gray-700"><strong>Your pitch:</strong> "Combines antimicrobial protection with collagen healing - one product, not two"</p>
        </div>

        <!-- Product 4 -->
        <div class="border-2 border-gray-300 rounded-2xl p-6 hover:border-brand-teal transition">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center text-2xl">🧴</div>
            <div>
              <h3 class="font-black text-lg">Collagen Powder</h3>
              <p class="text-xs text-gray-500">HCPCS: A6010</p>
            </div>
          </div>
          <p class="text-sm text-gray-700 mb-3"><strong>What it is:</strong> Ultra-fine collagen for irregular wounds</p>
          <p class="text-sm text-gray-700 mb-3"><strong>When to use:</strong> Tunneling, sinuses, hard-to-reach areas</p>
          <p class="text-sm text-gray-700"><strong>Your pitch:</strong> "Gets into places sheets and particles can't reach"</p>
        </div>
      </div>
    </div>

    <!-- The Cold Call Script -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <h2 class="text-3xl font-black text-gray-900 mb-8">The Cold Call Script (Memorize & Practice)</h2>

      <div class="space-y-6">
        <!-- Opening -->
        <div class="bg-gray-50 p-6 rounded-xl">
          <h3 class="font-bold text-lg text-gray-900 mb-3">Opening (Receptionist)</h3>
          <div class="bg-white p-4 rounded-lg border-2 border-gray-300">
            <p class="text-sm text-gray-700 mb-2"><strong>You:</strong></p>
            <p class="text-sm italic">"Good morning! This is [Your Name] from CollagenDirect. I work with wound care physicians to help them get faster product delivery and better reimbursement. Who typically handles wound care supply ordering for Dr. [Name]?"</p>
          </div>
          <p class="text-xs text-gray-600 mt-3"><strong>Why this works:</strong> You're not selling yet, just asking a question. Most receptionists will answer.</p>
        </div>

        <!-- Gatekeeper Objection -->
        <div class="bg-red-50 p-6 rounded-xl border-2 border-red-300">
          <h3 class="font-bold text-lg text-red-900 mb-3">If They Say: "We're all set with our current supplier"</h3>
          <div class="bg-white p-4 rounded-lg border-2 border-red-300">
            <p class="text-sm text-gray-700 mb-2"><strong>You:</strong></p>
            <p class="text-sm italic">"That's great! I'm not calling to replace them. But I get calls every day from clinics frustrated with 5-7 day delivery times and denied claims. If Dr. [Name] ever runs into those issues, we ship in 24-48 hours and handle all the insurance paperwork. Can I email Dr. [Name] some info so you have it on file?"</p>
          </div>
          <p class="text-xs text-gray-600 mt-3"><strong>Goal:</strong> Get the doctor's email so you can follow up directly.</p>
        </div>

        <!-- Getting the Meeting -->
        <div class="bg-green-50 p-6 rounded-xl border-2 border-green-300">
          <h3 class="font-bold text-lg text-green-900 mb-3">If They Transfer You or Ask More Questions</h3>
          <div class="bg-white p-4 rounded-lg border-2 border-green-300">
            <p class="text-sm text-gray-700 mb-2"><strong>You:</strong></p>
            <p class="text-sm italic">"I'd love to buy lunch for the team and show you how our portal works - it takes 2 minutes to order, and we handle all the insurance verification. Do you have lunch plans next Tuesday or Thursday?"</p>
          </div>
          <p class="text-xs text-gray-600 mt-3"><strong>Always offer two specific days</strong> - makes it easier for them to say yes.</p>
        </div>
      </div>
    </div>

    <!-- The 4 Discovery Questions -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <h2 class="text-3xl font-black text-gray-900 mb-8">The 4 Discovery Questions (Ask in Every Meeting)</h2>

      <div class="space-y-6">
        <div class="border-l-4 border-brand-teal bg-gray-50 p-6 rounded-r-lg">
          <h3 class="font-bold text-lg mb-2">1. "How long does it usually take to get collagen products from your current supplier?"</h3>
          <p class="text-sm text-gray-600 mb-3"><strong>What you're listening for:</strong> 5-7 days (or longer)</p>
          <p class="text-sm text-brand-teal font-semibold">→ Your response: "That's what we hear from everyone. We ship in 24-48 hours because we stock everything. If you have a patient coming in Friday, you can order Thursday night and have it Friday morning."</p>
        </div>

        <div class="border-l-4 border-brand-teal bg-gray-50 p-6 rounded-r-lg">
          <h3 class="font-bold text-lg mb-2">2. "Do you ever have issues with insurance denying claims or asking for more documentation?"</h3>
          <p class="text-sm text-gray-600 mb-3"><strong>What you're listening for:</strong> "All the time" or "Sometimes"</p>
          <p class="text-sm text-brand-teal font-semibold">→ Your response: "We handle all of that. Our portal auto-verifies eligibility before you order, and if insurance needs paperwork, we handle it - not your staff. You never get a denied claim surprise."</p>
        </div>

        <div class="border-l-4 border-brand-teal bg-gray-50 p-6 rounded-r-lg">
          <h3 class="font-bold text-lg mb-2">3. "How much time does your staff spend on the phone ordering supplies or dealing with paperwork?"</h3>
          <p class="text-sm text-gray-600 mb-3"><strong>What you're listening for:</strong> "Too much" or frustration</p>
          <p class="text-sm text-brand-teal font-semibold">→ Your response: "Our portal takes 2 minutes. Click the product, enter the patient info, done. No phone calls, no faxing, no waiting on hold. Your staff will love you for it."</p>
        </div>

        <div class="border-l-4 border-brand-teal bg-gray-50 p-6 rounded-r-lg">
          <h3 class="font-bold text-lg mb-2">4. "What types of wounds are you seeing most right now?"</h3>
          <p class="text-sm text-gray-600 mb-3"><strong>What you're listening for:</strong> Diabetic ulcers, pressure ulcers, surgical wounds, etc.</p>
          <p class="text-sm text-brand-teal font-semibold">→ Your response: Match their answer to the right product. "Our collagen sheets are perfect for diabetic ulcers - they cut healing time by 40% according to clinical studies."</p>
        </div>
      </div>

      <div class="bg-yellow-50 border-2 border-yellow-500 p-6 rounded-lg mt-8">
        <p class="text-sm font-bold text-yellow-900 mb-2">⚠️ CRITICAL RULE:</p>
        <p class="text-sm text-yellow-800">After you ask a question, <strong>STOP TALKING</strong>. Let them answer. The more they talk, the more you learn about their pain points - and the easier it is to close.</p>
      </div>
    </div>

    <!-- Common Objections -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <h2 class="text-3xl font-black text-gray-900 mb-8">Common Objections & How to Handle Them</h2>

      <div class="space-y-4">
        <div class="bg-gray-50 p-6 rounded-xl">
          <h3 class="font-bold text-gray-900 mb-2">"We're happy with our current supplier"</h3>
          <p class="text-sm text-gray-700 italic">"That's great! Most of our customers were happy too - until they tried us. What if I could show you a way to get products twice as fast with less paperwork? Worth a 15-minute conversation?"</p>
        </div>

        <div class="bg-gray-50 p-6 rounded-xl">
          <h3 class="font-bold text-gray-900 mb-2">"How much does it cost?"</h3>
          <p class="text-sm text-gray-700 italic">"Same as everyone else - we bill insurance directly using standard HCPCS codes. The difference is we ship faster and handle all the paperwork, so you actually save money on staff time. Plus, no denied claims means you get paid faster."</p>
        </div>

        <div class="bg-gray-50 p-6 rounded-xl">
          <h3 class="font-bold text-gray-900 mb-2">"We don't have time to switch suppliers"</h3>
          <p class="text-sm text-gray-700 italic">"I totally understand. That's exactly why we make it easy - registration takes 5 minutes, and I'll help you do it right now. You don't have to switch entirely - just try us for one patient and see how it goes."</p>
        </div>

        <div class="bg-gray-50 p-6 rounded-xl">
          <h3 class="font-bold text-gray-900 mb-2">"Send me some information"</h3>
          <p class="text-sm text-gray-700 italic">"Absolutely! I'll email you our product catalog. But honestly, it's much easier if I just show you the portal real quick - it's what makes us different. Do you have 5 minutes right now, or should I call you back tomorrow morning?"</p>
        </div>

        <div class="bg-gray-50 p-6 rounded-xl">
          <h3 class="font-bold text-gray-900 mb-2">"Does insurance cover this?"</h3>
          <p class="text-sm text-gray-700 italic">"Yes - Medicare, Medicaid, and most commercial plans cover collagen for chronic wounds. We verify eligibility automatically before you order, so you'll know upfront if a patient is covered. No surprises."</p>
        </div>
      </div>
    </div>

    <!-- Cheat Sheet -->
    <div class="bg-gradient-to-r from-brand-navy to-slate-800 text-white rounded-3xl p-10 mb-10">
      <h2 class="text-3xl font-black mb-8">Your Pocket Cheat Sheet (Print This)</h2>

      <div class="grid md:grid-cols-2 gap-8">
        <div>
          <h3 class="text-xl font-bold mb-4 text-brand-teal">HCPCS Codes (Memorize)</h3>
          <ul class="space-y-2 text-sm">
            <li><strong>A6010:</strong> Collagen sheets/particles/powder</li>
            <li><strong>A6021:</strong> Collagen dressings (pads)</li>
            <li><strong>A6248:</strong> Antimicrobial gel (≤16 sq in)</li>
            <li><strong>A6249:</strong> Antimicrobial gel (>16 sq in)</li>
          </ul>
        </div>

        <div>
          <h3 class="text-xl font-bold mb-4 text-brand-teal">The 3 Pain Points</h3>
          <ul class="space-y-2 text-sm">
            <li><strong>1. Slow Delivery:</strong> We ship 24-48hrs vs 5-7 days</li>
            <li><strong>2. Insurance Hassles:</strong> We handle all paperwork</li>
            <li><strong>3. Complicated Ordering:</strong> 2-minute portal vs phone calls</li>
          </ul>
        </div>

        <div>
          <h3 class="text-xl font-bold mb-4 text-brand-teal">Target Specialties</h3>
          <ul class="space-y-2 text-sm">
            <li>Wound care clinics</li>
            <li>Podiatrists</li>
            <li>Vascular surgeons</li>
            <li>Dermatologists</li>
            <li>Plastic surgeons</li>
            <li>Primary care (with diabetic patients)</li>
          </ul>
        </div>

        <div>
          <h3 class="text-xl font-bold mb-4 text-brand-teal">Your First Week Goals</h3>
          <ul class="space-y-2 text-sm">
            <li><strong>Day 1:</strong> Memorize products + script</li>
            <li><strong>Day 2-3:</strong> 40 calls → 2-4 meetings booked</li>
            <li><strong>Day 4-5:</strong> 3-5 meetings → 1-2 registrations</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Your Sales Tools -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <h2 class="text-3xl font-black text-gray-900 mb-8">Your Sales Tools & Dashboard</h2>

      <div class="grid md:grid-cols-2 gap-6 mb-8">
        <div class="border-2 border-brand-teal rounded-xl p-6 bg-teal-50">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-brand-teal rounded-xl flex items-center justify-center text-white text-xl">📊</div>
            <div>
              <h3 class="font-black text-lg">Sales Rep Portal</h3>
              <p class="text-xs text-gray-500">collagendirect.health/admin</p>
            </div>
          </div>
          <ul class="space-y-2 text-sm text-gray-700">
            <li>• View your assigned clinics and their order history</li>
            <li>• Create wholesale orders for your accounts</li>
            <li>• Track commissions and performance metrics</li>
            <li>• Onboard new physician accounts</li>
          </ul>
          <a href="/admin/" class="inline-flex items-center gap-2 mt-4 text-brand-teal font-semibold text-sm hover:underline">
            Open Sales Dashboard →
          </a>
        </div>

        <div class="border-2 border-gray-300 rounded-xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-2xl">🏥</div>
            <div>
              <h3 class="font-black text-lg">Physician Portal Demo</h3>
              <p class="text-xs text-gray-500">Show this to physicians</p>
            </div>
          </div>
          <ul class="space-y-2 text-sm text-gray-700">
            <li>• 2-minute ordering process</li>
            <li>• Automatic insurance verification</li>
            <li>• Real-time order tracking</li>
            <li>• Digital documentation storage</li>
          </ul>
          <a href="/portal-guide.php" class="inline-flex items-center gap-2 mt-4 text-brand-teal font-semibold text-sm hover:underline">
            View Physician Portal Guide →
          </a>
        </div>
      </div>

      <div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-4">
        <p class="text-sm font-bold text-yellow-900 mb-1">Pro Tip: Live Demo Sells</p>
        <p class="text-sm text-yellow-800">Instead of just talking about the portal, pull it up on your phone or laptop during meetings. A 60-second demo of the ordering process is worth 10 minutes of explanation.</p>
      </div>
    </div>

    <!-- Weekly Activity Tracker -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <h2 class="text-3xl font-black text-gray-900 mb-8">Weekly Activity Tracker</h2>
      <p class="text-gray-600 mb-6">Track these numbers each week to stay on target:</p>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-100">
              <th class="text-left p-3 font-bold">Activity</th>
              <th class="text-center p-3 font-bold">Target</th>
              <th class="text-center p-3 font-bold">Mon</th>
              <th class="text-center p-3 font-bold">Tue</th>
              <th class="text-center p-3 font-bold">Wed</th>
              <th class="text-center p-3 font-bold">Thu</th>
              <th class="text-center p-3 font-bold">Fri</th>
            </tr>
          </thead>
          <tbody>
            <tr class="border-b">
              <td class="p-3">Cold Calls Made</td>
              <td class="text-center p-3 font-bold text-brand-teal">20/day</td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
            </tr>
            <tr class="border-b">
              <td class="p-3">Meetings Booked</td>
              <td class="text-center p-3 font-bold text-brand-teal">3/week</td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
            </tr>
            <tr class="border-b">
              <td class="p-3">In-Person Meetings</td>
              <td class="text-center p-3 font-bold text-brand-teal">3/week</td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
            </tr>
            <tr class="border-b">
              <td class="p-3">New Registrations</td>
              <td class="text-center p-3 font-bold text-brand-teal">2/week</td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
            </tr>
            <tr>
              <td class="p-3">First Orders Placed</td>
              <td class="text-center p-3 font-bold text-brand-teal">1/week</td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
              <td class="text-center p-3"><input type="text" class="w-12 p-1 border rounded text-center" placeholder="0"></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="grid md:grid-cols-3 gap-4 mt-6">
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
          <p class="text-2xl font-black text-green-700">100</p>
          <p class="text-xs text-green-600">Calls/Week Target</p>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
          <p class="text-2xl font-black text-blue-700">3-5</p>
          <p class="text-xs text-blue-600">Meetings/Week Target</p>
        </div>
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">
          <p class="text-2xl font-black text-purple-700">2+</p>
          <p class="text-xs text-purple-600">Registrations/Week Target</p>
        </div>
      </div>
    </div>

    <!-- Next Steps -->
    <div class="bg-white rounded-3xl shadow-xl p-10 text-center">
      <h2 class="text-3xl font-black text-gray-900 mb-6">Ready to Start Selling?</h2>
      <p class="text-lg text-gray-600 mb-8 max-w-2xl mx-auto">
        This guide gets you started fast. For deeper training on objection handling, competitive positioning, and advanced techniques, check out the full training modules.
      </p>
      <div class="flex flex-wrap gap-4 justify-center">
        <a href="sales-process.php" class="px-8 py-4 bg-brand-teal text-white font-bold rounded-xl hover:bg-brand-navy transition shadow-lg">
          Full Sales Process Training
        </a>
        <a href="scripts.php" class="px-8 py-4 bg-white border-2 border-gray-300 text-gray-900 font-bold rounded-xl hover:border-brand-teal transition">
          More Scripts & Templates
        </a>
        <a href="battle-cards.php" class="px-8 py-4 bg-white border-2 border-gray-300 text-gray-900 font-bold rounded-xl hover:border-brand-teal transition">
          Competitive Battle Cards
        </a>
      </div>

      <div class="mt-8 pt-8 border-t border-gray-200">
        <p class="text-sm text-gray-500 mb-4">Additional Resources</p>
        <div class="flex flex-wrap gap-4 justify-center text-sm">
          <a href="product-training.php" class="text-brand-teal hover:underline">Product Deep Dive</a>
          <span class="text-gray-300">|</span>
          <a href="objections.php" class="text-brand-teal hover:underline">Objection Handling</a>
          <span class="text-gray-300">|</span>
          <a href="hipaa-training.php" class="text-brand-teal hover:underline">HIPAA Training</a>
          <span class="text-gray-300">|</span>
          <a href="/distributor-guide.php" class="text-brand-teal hover:underline">Distributor Guide</a>
        </div>
      </div>
    </div>

  </div>

</body>
</html>
