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
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quick Reference Guide | Sales Training</title>
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
    body { font-feature-settings: 'cv11', 'ss01'; -webkit-font-smoothing: antialiased; }
    @media print {
      .no-print { display: none; }
      body { background: white; }
    }
  </style>
</head>
<body class="bg-gray-50">

  <!-- Header -->
  <header class="bg-white border-b border-gray-200 sticky top-0 z-50 no-print">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
      <a href="index.php" class="text-brand-teal hover:text-brand-navy transition flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Back to Training Hub
      </a>
      <button onclick="window.print()" class="px-4 py-2 bg-brand-teal text-white rounded-lg hover:bg-brand-navy transition">
        Print / Save PDF
      </button>
    </div>
  </header>

  <!-- Content -->
  <div class="max-w-5xl mx-auto px-6 py-12">

    <div class="bg-white rounded-3xl shadow-xl p-12 mb-8">
      <div class="text-center mb-8">
        <h1 class="text-4xl font-black text-gray-900 mb-2">Sales Quick Reference Guide</h1>
        <p class="text-gray-600">Your one-page cheat sheet for selling CollagenDirect products</p>
      </div>

      <!-- Product Matrix -->
      <section class="mb-10">
        <h2 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-brand-teal">Product Matrix</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-100">
              <tr>
                <th class="p-3 text-left font-bold">Product</th>
                <th class="p-3 text-left font-bold">HCPCS Code</th>
                <th class="p-3 text-left font-bold">Best For</th>
                <th class="p-3 text-left font-bold">Key Feature</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr>
                <td class="p-3 font-semibold">Collagen Powder</td>
                <td class="p-3"><span class="px-2 py-1 bg-purple-100 text-purple-800 rounded font-mono text-xs">A6010</span></td>
                <td class="p-3">Deep wounds, tunneling, undermining</td>
                <td class="p-3">Fills irregular wound beds</td>
              </tr>
              <tr>
                <td class="p-3 font-semibold">Collagen Sheet (2x2, 3x3)</td>
                <td class="p-3"><span class="px-2 py-1 bg-blue-100 text-blue-800 rounded font-mono text-xs">A6021</span></td>
                <td class="p-3">Small-medium diabetic ulcers, pressure injuries</td>
                <td class="p-3">Easy application, conforms to wound</td>
              </tr>
              <tr>
                <td class="p-3 font-semibold">Collagen Sheet (4x4, 4x5)</td>
                <td class="p-3"><span class="px-2 py-1 bg-emerald-100 text-emerald-800 rounded font-mono text-xs">A6210</span></td>
                <td class="p-3">Large chronic wounds, surgical sites</td>
                <td class="p-3">Coverage for extensive wounds</td>
              </tr>
              <tr>
                <td class="p-3 font-semibold">Antimicrobial Gel</td>
                <td class="p-3"><span class="px-2 py-1 bg-orange-100 text-orange-800 rounded font-mono text-xs">A6248</span></td>
                <td class="p-3">Infected wounds, high bioburden</td>
                <td class="p-3">Silver ions for infection control</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Key Selling Points -->
      <section class="mb-10">
        <h2 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-brand-teal">Key Selling Points</h2>
        <div class="grid md:grid-cols-2 gap-4">
          <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
            <h3 class="font-bold text-emerald-900 mb-2">98% Reimbursement Success</h3>
            <p class="text-sm text-emerald-800">Our pre-verification process reduces denials by 90%. We handle insurance headaches.</p>
          </div>
          <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg">
            <h3 class="font-bold text-blue-900 mb-2">24-48 Hour Shipping</h3>
            <p class="text-sm text-blue-800">Competitors take 5-7 days. We ship same/next day. Your patients get products faster.</p>
          </div>
          <div class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded-r-lg">
            <h3 class="font-bold text-purple-900 mb-2">Digital-First Portal</h3>
            <p class="text-sm text-purple-800">No faxing. Order in 3 clicks. Automatic patient tracking. HIPAA-compliant cloud system.</p>
          </div>
          <div class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded-r-lg">
            <h3 class="font-bold text-orange-900 mb-2">No Prior Auth (Most Cases)</h3>
            <p class="text-sm text-orange-800">HCPCS codes A6010/A6021/A6210 are typically covered without PA. Faster patient access.</p>
          </div>
        </div>
      </section>

      <!-- Pricing Guide -->
      <section class="mb-10">
        <h2 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-brand-teal">Typical Patient Cost</h2>
        <div class="bg-gray-50 rounded-xl p-6">
          <div class="grid md:grid-cols-3 gap-6">
            <div>
              <div class="text-2xl font-black text-brand-navy mb-1">$5-15</div>
              <div class="text-sm font-semibold text-gray-700 mb-2">Medicare Part B</div>
              <div class="text-xs text-gray-600">20% coinsurance after deductible</div>
            </div>
            <div>
              <div class="text-2xl font-black text-brand-navy mb-1">$0-30</div>
              <div class="text-sm font-semibold text-gray-700 mb-2">Medicare Advantage</div>
              <div class="text-xs text-gray-600">Varies by plan, often copay</div>
            </div>
            <div>
              <div class="text-2xl font-black text-brand-navy mb-1">$0-50</div>
              <div class="text-sm font-semibold text-gray-700 mb-2">Commercial Insurance</div>
              <div class="text-xs text-gray-600">80-100% covered after deductible</div>
            </div>
          </div>
        </div>
      </section>

      <!-- Common Objections Quick Responses -->
      <section class="mb-10">
        <h2 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-brand-teal">Quick Objection Responses</h2>
        <div class="space-y-3">
          <div class="border-l-4 border-red-400 pl-4">
            <div class="font-bold text-gray-900">"We already have a supplier."</div>
            <div class="text-sm text-gray-700 mt-1">
              "I understand. Most of our physicians switched from [Competitor] because of our 24-48hr shipping vs their 5-7 days. Can I show you a side-by-side comparison?"
            </div>
          </div>
          <div class="border-l-4 border-red-400 pl-4">
            <div class="font-bold text-gray-900">"I'm worried about insurance coverage."</div>
            <div class="text-sm text-gray-700 mt-1">
              "That's our specialty. We pre-verify every patient and have a 98% approval rate. We handle all documentation and denials. You focus on patients, we handle billing."
            </div>
          </div>
          <div class="border-l-4 border-red-400 pl-4">
            <div class="font-bold text-gray-900">"Your pricing seems high."</div>
            <div class="text-sm text-gray-700 mt-1">
              "Let me clarify - that's the retail price insurance sees. Patient out-of-pocket is typically $5-15 for Medicare. Plus, faster healing means fewer dressing changes, so total cost of care is lower."
            </div>
          </div>
          <div class="border-l-4 border-red-400 pl-4">
            <div class="font-bold text-gray-900">"I don't have time to learn a new system."</div>
            <div class="text-sm text-gray-700 mt-1">
              "It's actually simpler. Three clicks to order vs filling out 10-page fax forms. Most physicians complete their first order in under 2 minutes. I can show you right now."
            </div>
          </div>
        </div>
      </section>

      <!-- Target Physician Types -->
      <section class="mb-10">
        <h2 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-brand-teal">Best Target Physicians</h2>
        <div class="grid md:grid-cols-3 gap-4 text-sm">
          <div class="bg-teal-50 rounded-lg p-4">
            <div class="font-bold text-teal-900 mb-2">Wound Care Specialists</div>
            <ul class="text-teal-800 space-y-1 text-xs">
              <li>• Highest volume potential</li>
              <li>• Understand collagen benefits</li>
              <li>• Often frustrated with slow suppliers</li>
            </ul>
          </div>
          <div class="bg-blue-50 rounded-lg p-4">
            <div class="font-bold text-blue-900 mb-2">Podiatrists (DPM)</div>
            <ul class="text-blue-800 space-y-1 text-xs">
              <li>• See many diabetic foot ulcers</li>
              <li>• Value quick healing products</li>
              <li>• Often bill Medicare Part B</li>
            </ul>
          </div>
          <div class="bg-purple-50 rounded-lg p-4">
            <div class="font-bold text-purple-900 mb-2">Vascular Surgeons</div>
            <ul class="text-purple-800 space-y-1 text-xs">
              <li>• Post-surgical wound management</li>
              <li>• Arterial/venous ulcers</li>
              <li>• High patient volume</li>
            </ul>
          </div>
        </div>
      </section>

      <!-- ROI Calculator -->
      <section class="mb-10">
        <h2 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-brand-teal">ROI Talking Points</h2>
        <div class="bg-gradient-to-br from-brand-navy to-slate-800 text-white rounded-2xl p-6">
          <div class="grid md:grid-cols-2 gap-6">
            <div>
              <div class="text-3xl font-black mb-2">30% Faster Healing</div>
              <p class="text-sm text-slate-300">Average vs standard gauze dressings. Fewer follow-up visits = more appointment slots = more revenue.</p>
            </div>
            <div>
              <div class="text-3xl font-black mb-2">4 Hours/Week Saved</div>
              <p class="text-sm text-slate-300">Digital ordering vs fax/phone. Front office staff can focus on patient care instead of supply ordering.</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Next Steps -->
      <section>
        <h2 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-brand-teal">Closing & Next Steps</h2>
        <div class="space-y-3 text-sm">
          <div class="flex items-start gap-3">
            <div class="w-8 h-8 bg-brand-teal text-white rounded-full flex items-center justify-center flex-shrink-0 font-bold">1</div>
            <div>
              <div class="font-bold text-gray-900">Schedule 15-Minute Portal Demo</div>
              <div class="text-gray-600">"Can I show you how the ordering system works? I can walk you through a sample order in 15 minutes."</div>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <div class="w-8 h-8 bg-brand-teal text-white rounded-full flex items-center justify-center flex-shrink-0 font-bold">2</div>
            <div>
              <div class="font-bold text-gray-900">Send Product Samples</div>
              <div class="text-gray-600">"I'd like to send you samples of our collagen sheets so you can see the quality. What's the best address?"</div>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <div class="w-8 h-8 bg-brand-teal text-white rounded-full flex items-center justify-center flex-shrink-0 font-bold">3</div>
            <div>
              <div class="font-bold text-gray-900">Set Up Account</div>
              <div class="text-gray-600">"Let's get you registered. It takes 5 minutes and you'll be able to order immediately. No credit card needed."</div>
            </div>
          </div>
        </div>
      </section>

    </div>

    <!-- Footer -->
    <div class="text-center text-sm text-gray-500 no-print">
      <a href="index.php" class="text-brand-teal hover:underline">← Back to Training Hub</a>
    </div>

  </div>

</body>
</html>
