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
  <title>Competitive Battle Cards | Sales Training</title>
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
      <a href="index.php" class="text-brand-teal hover:text-brand-navy transition flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Back to Training Hub
      </a>
    </div>
  </header>

  <!-- Content -->
  <div class="max-w-6xl mx-auto px-6 py-12">

    <div class="mb-12 text-center">
      <h1 class="text-4xl font-black text-gray-900 mb-4">Competitive Battle Cards</h1>
      <p class="text-xl text-gray-600 max-w-3xl mx-auto">
        Know your competition. Use these battle cards to confidently position CollagenDirect against major wound care suppliers.
      </p>
    </div>

    <!-- Battle Card: Smith & Nephew -->
    <div class="bg-white rounded-3xl shadow-xl p-8 mb-8 border-l-8 border-red-500">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h2 class="text-3xl font-black text-gray-900">Smith & Nephew</h2>
          <p class="text-gray-600">Major competitor in advanced wound care (Collagen Matrix, Promogran products)</p>
        </div>
        <div class="px-4 py-2 bg-red-100 text-red-800 rounded-lg font-bold text-sm">THREAT LEVEL: HIGH</div>
      </div>

      <div class="grid md:grid-cols-2 gap-8">
        <!-- Their Strengths -->
        <div>
          <h3 class="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"></path>
            </svg>
            Their Strengths
          </h3>
          <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex items-start gap-2">
              <span class="text-orange-500">•</span>
              <span><strong>Brand Recognition:</strong> Large, established medical device company</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-orange-500">•</span>
              <span><strong>Clinical Data:</strong> Extensive published studies on Promogran</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-orange-500">•</span>
              <span><strong>Hospital Contracts:</strong> Strong presence in acute care settings</span>
            </li>
          </ul>
        </div>

        <!-- Our Advantages -->
        <div>
          <h3 class="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            Our Advantages
          </h3>
          <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Ship Time:</strong> 24-48hrs vs their 5-7 days</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Digital Portal:</strong> 3-click ordering vs complex rep-based system</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Pricing:</strong> 20-30% lower cost with same reimbursement codes</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Insurance Support:</strong> We pre-verify every patient, they don't</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Talking Points -->
      <div class="mt-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
        <h4 class="font-bold text-emerald-900 mb-2">If They Say: "We use Smith & Nephew's Promogran"</h4>
        <p class="text-sm text-emerald-800">
          <strong>You Say:</strong> "Promogran is a great product. We offer equivalent collagen/ORC matrix at 25% lower cost with the same HCPCS codes. The biggest difference our physicians mention is shipping time - we deliver in 24-48 hours vs their 5-7 days, so your patients start healing faster. Can I show you a side-by-side comparison?"
        </p>
      </div>
    </div>

    <!-- Battle Card: 3M (Tegaderm) -->
    <div class="bg-white rounded-3xl shadow-xl p-8 mb-8 border-l-8 border-blue-500">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h2 class="text-3xl font-black text-gray-900">3M Healthcare</h2>
          <p class="text-gray-600">Dominant in basic wound care (Tegaderm films, foam dressings)</p>
        </div>
        <div class="px-4 py-2 bg-blue-100 text-blue-800 rounded-lg font-bold text-sm">THREAT LEVEL: MEDIUM</div>
      </div>

      <div class="grid md:grid-cols-2 gap-8">
        <div>
          <h3 class="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"></path>
            </svg>
            Their Strengths
          </h3>
          <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex items-start gap-2">
              <span class="text-orange-500">•</span>
              <span><strong>Market Leader:</strong> Massive brand, trusted name</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-orange-500">•</span>
              <span><strong>Product Range:</strong> Complete wound care catalog</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-orange-500">•</span>
              <span><strong>Distribution:</strong> Available everywhere (retail, hospital, DME)</span>
            </li>
          </ul>
        </div>

        <div>
          <h3 class="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            Our Advantages
          </h3>
          <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Specialty Focus:</strong> We specialize in advanced collagen therapy vs their commodity foams</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Better for Chronic Wounds:</strong> Collagen stimulates healing, their foams just absorb</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Insurance Handling:</strong> We manage all reimbursement; 3M doesn't offer this service</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Direct Ordering:</strong> No middlemen, no distributors, no delays</span>
            </li>
          </ul>
        </div>
      </div>

      <div class="mt-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
        <h4 class="font-bold text-emerald-900 mb-2">If They Say: "We just use 3M foams for everything"</h4>
        <p class="text-sm text-emerald-800">
          <strong>You Say:</strong> "Foams work great for exudate management, but they don't actively promote healing. For stalled diabetic ulcers or pressure injuries, collagen provides the growth factors and scaffold that foam can't. We see physicians use 3M foams for simple wounds and switch to our collagen when wounds aren't progressing. Would you like to see our healing time data?"
        </p>
      </div>
    </div>

    <!-- Battle Card: Integra -->
    <div class="bg-white rounded-3xl shadow-xl p-8 mb-8 border-l-8 border-purple-500">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h2 class="text-3xl font-black text-gray-900">Integra LifeSciences</h2>
          <p class="text-gray-600">Advanced wound matrices and skin regeneration (DRT, Primatrix)</p>
        </div>
        <div class="px-4 py-2 bg-purple-100 text-purple-800 rounded-lg font-bold text-sm">THREAT LEVEL: MEDIUM</div>
      </div>

      <div class="grid md:grid-cols-2 gap-8">
        <div>
          <h3 class="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"></path>
            </svg>
            Their Strengths
          </h3>
          <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex items-start gap-2">
              <span class="text-orange-500">•</span>
              <span><strong>Premium Positioning:</strong> High-end regenerative products</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-orange-500">•</span>
              <span><strong>Clinical Evidence:</strong> Strong data for complex wounds</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-orange-500">•</span>
              <span><strong>Surgeon Relationships:</strong> Popular in surgical specialties</span>
            </li>
          </ul>
        </div>

        <div>
          <h3 class="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            Our Advantages
          </h3>
          <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Cost-Effectiveness:</strong> 40-50% lower cost for similar collagen products</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Ease of Use:</strong> Simpler ordering, no surgical rep required</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Outpatient Focus:</strong> Optimized for clinic/office use vs their OR focus</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Reimbursement:</strong> Standard HCPCS codes vs their complex product codes</span>
            </li>
          </ul>
        </div>
      </div>

      <div class="mt-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
        <h4 class="font-bold text-emerald-900 mb-2">If They Say: "We use Integra for our complex cases"</h4>
        <p class="text-sm text-emerald-800">
          <strong>You Say:</strong> "Integra makes excellent products for surgical reconstruction. For outpatient chronic wound management, our collagen sheets offer similar healing benefits at a fraction of the cost. Plus, our HCPCS codes (A6010/A6021/A6210) are straightforward vs Integra's complex billing. Most practices use us for day-to-day wound care and reserve Integra for OR cases. Would you like to compare outcomes?"
        </p>
      </div>
    </div>

    <!-- Battle Card: Generic/Local DME Suppliers -->
    <div class="bg-white rounded-3xl shadow-xl p-8 mb-8 border-l-8 border-gray-500">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h2 class="text-3xl font-black text-gray-900">Generic DME Suppliers</h2>
          <p class="text-gray-600">Local durable medical equipment companies, regional distributors</p>
        </div>
        <div class="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg font-bold text-sm">THREAT LEVEL: LOW</div>
      </div>

      <div class="grid md:grid-cols-2 gap-8">
        <div>
          <h3 class="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"></path>
            </svg>
            Their Strengths
          </h3>
          <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex items-start gap-2">
              <span class="text-orange-500">•</span>
              <span><strong>Established Relationships:</strong> Been serving practice for years</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-orange-500">•</span>
              <span><strong>One-Stop Shop:</strong> Provide everything (wheelchairs, oxygen, supplies)</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-orange-500">•</span>
              <span><strong>Local Presence:</strong> Can drop off products locally</span>
            </li>
          </ul>
        </div>

        <div>
          <h3 class="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            Our Advantages
          </h3>
          <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Collagen Expertise:</strong> We specialize in advanced wound care, they're generalists</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Technology:</strong> Digital portal vs their fax/phone system</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Faster Service:</strong> Direct manufacturer shipping vs their warehouse delays</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emerald-500">✓</span>
              <span><strong>Transparency:</strong> Real-time tracking vs "we'll call you when it's ready"</span>
            </li>
          </ul>
        </div>
      </div>

      <div class="mt-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
        <h4 class="font-bold text-emerald-900 mb-2">If They Say: "Our DME company handles everything for us"</h4>
        <p class="text-sm text-emerald-800">
          <strong>You Say:</strong> "I hear that a lot. The challenge with general DME suppliers is they're great for wheelchairs and CPAP machines, but collagen wound therapy requires specialized knowledge. Our physicians appreciate having a dedicated collagen specialist who understands reimbursement, application protocols, and product selection. You can keep your DME company for equipment and use us for advanced wound care. Can I show you how other practices manage this?"
        </p>
      </div>
    </div>

    <!-- Win/Loss Tracking -->
    <div class="bg-gradient-to-br from-brand-navy to-slate-800 text-white rounded-3xl p-8">
      <h2 class="text-2xl font-bold mb-4">Competitive Win/Loss Analysis</h2>
      <div class="grid md:grid-cols-3 gap-6 text-sm">
        <div>
          <div class="text-4xl font-black mb-2">68%</div>
          <div class="text-slate-300">Win rate vs Smith & Nephew</div>
          <div class="text-xs text-slate-400 mt-1">Primary win factor: Ship time + digital portal</div>
        </div>
        <div>
          <div class="text-4xl font-black mb-2">82%</div>
          <div class="text-slate-300">Win rate vs local DME</div>
          <div class="text-xs text-slate-400 mt-1">Primary win factor: Specialization + technology</div>
        </div>
        <div>
          <div class="text-4xl font-black mb-2">55%</div>
          <div class="text-slate-300">Win rate vs Integra</div>
          <div class="text-xs text-slate-400 mt-1">Primary win factor: Cost-effectiveness</div>
        </div>
      </div>
    </div>

  </div>

</body>
</html>
