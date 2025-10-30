<?php
session_start();

// Simple email-based authentication
// In production, this should integrate with your actual authentication system
$authorized = false;
$user_email = '';

if (isset($_SESSION['user_email'])) {
    $user_email = $_SESSION['user_email'];
    // Check if email ends with @collagendirect.health
    if (preg_match('/@collagendirect\.health$/i', $user_email)) {
        $authorized = true;
    }
}

// For development/demo: allow ?email= parameter (REMOVE IN PRODUCTION)
if (isset($_GET['email']) && preg_match('/@collagendirect\.health$/i', $_GET['email'])) {
    $_SESSION['user_email'] = $_GET['email'];
    $user_email = $_GET['email'];
    $authorized = true;
}

if (!$authorized) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales Training Portal | CollagenDirect</title>
  <meta name="robots" content="noindex, nofollow">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
          },
          fontFamily: {
            sans: ['Inter', 'system-ui', 'sans-serif']
          }
        }
      }
    }
  </script>
  <style>
    body { font-feature-settings: 'cv11', 'ss01'; -webkit-font-smoothing: antialiased; }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-slate-100 text-gray-900">

  <!-- Header -->
  <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-8 w-auto">
          <div>
            <div class="text-sm font-bold text-gray-900">Sales Training Portal</div>
            <div class="text-xs text-gray-500">Internal Use Only</div>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-600">Welcome, <strong><?php echo htmlspecialchars(explode('@', $user_email)[0]); ?></strong></span>
          <a href="login.php?logout=1" class="text-sm text-gray-500 hover:text-brand-teal transition">Logout</a>
        </div>
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <section class="py-16 bg-gradient-to-r from-brand-navy via-slate-900 to-brand-navy text-white">
    <div class="max-w-7xl mx-auto px-6">
      <h1 class="text-5xl font-black mb-4">Sales Enablement Hub</h1>
      <p class="text-xl text-slate-300 max-w-3xl">
        Everything you need to confidently sell CollagenDirect products, handle objections, and close more deals.
      </p>
    </div>
  </section>

  <!-- Quick Links Grid -->
  <section class="py-12 max-w-7xl mx-auto px-6">
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">

      <!-- Quick Reference Guide -->
      <a href="quick-reference.php" class="group bg-white rounded-2xl p-8 border-2 border-gray-200 hover:border-brand-teal hover:shadow-xl transition-all">
        <div class="w-14 h-14 bg-gradient-to-br from-brand-teal to-emerald-500 rounded-xl flex items-center justify-center mb-4">
          <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-brand-teal transition">Quick Reference Guide</h3>
        <p class="text-gray-600 text-sm mb-4">
          One-page cheat sheet: products, HCPCS codes, pricing, key selling points.
        </p>
        <div class="flex items-center gap-2 text-brand-teal font-semibold text-sm">
          View Guide
          <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
          </svg>
        </div>
      </a>

      <!-- Competitive Battle Cards -->
      <a href="battle-cards.php" class="group bg-white rounded-2xl p-8 border-2 border-gray-200 hover:border-brand-teal hover:shadow-xl transition-all">
        <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-orange-500 rounded-xl flex items-center justify-center mb-4">
          <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
          </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-brand-teal transition">Competitive Battle Cards</h3>
        <p class="text-gray-600 text-sm mb-4">
          How to position against Smith & Nephew, 3M, Integra, and other competitors.
        </p>
        <div class="flex items-center gap-2 text-brand-teal font-semibold text-sm">
          View Battle Cards
          <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
          </svg>
        </div>
      </a>

      <!-- Sales Scripts -->
      <a href="scripts.php" class="group bg-white rounded-2xl p-8 border-2 border-gray-200 hover:border-brand-teal hover:shadow-xl transition-all">
        <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-purple-500 rounded-xl flex items-center justify-center mb-4">
          <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
          </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-brand-teal transition">Sales Scripts & Talk Tracks</h3>
        <p class="text-gray-600 text-sm mb-4">
          Proven conversation starters, objection handlers, and closing techniques.
        </p>
        <div class="flex items-center gap-2 text-brand-teal font-semibold text-sm">
          View Scripts
          <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
          </svg>
        </div>
      </a>

      <!-- Product Training -->
      <a href="product-training.php" class="group bg-white rounded-2xl p-8 border-2 border-gray-200 hover:border-brand-teal hover:shadow-xl transition-all">
        <div class="w-14 h-14 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center mb-4">
          <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
          </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-brand-teal transition">Product Training</h3>
        <p class="text-gray-600 text-sm mb-4">
          Deep dive into each product: specs, applications, clinical evidence, use cases.
        </p>
        <div class="flex items-center gap-2 text-brand-teal font-semibold text-sm">
          Start Training
          <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
          </svg>
        </div>
      </a>

      <!-- Objection Handling -->
      <a href="objections.php" class="group bg-white rounded-2xl p-8 border-2 border-gray-200 hover:border-brand-teal hover:shadow-xl transition-all">
        <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center mb-4">
          <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-brand-teal transition">Objection Handling</h3>
        <p class="text-gray-600 text-sm mb-4">
          30+ common objections with proven responses and supporting evidence.
        </p>
        <div class="flex items-center gap-2 text-brand-teal font-semibold text-sm">
          View Objections
          <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
          </svg>
        </div>
      </a>

      <!-- Customer Success Stories -->
      <a href="success-stories.php" class="group bg-white rounded-2xl p-8 border-2 border-gray-200 hover:border-brand-teal hover:shadow-xl transition-all">
        <div class="w-14 h-14 bg-gradient-to-br from-pink-500 to-rose-500 rounded-xl flex items-center justify-center mb-4">
          <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
          </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-brand-teal transition">Success Stories</h3>
        <p class="text-gray-600 text-sm mb-4">
          Real physician testimonials, case studies, and quantified outcomes.
        </p>
        <div class="flex items-center gap-2 text-brand-teal font-semibold text-sm">
          View Stories
          <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
          </svg>
        </div>
      </a>

    </div>
  </section>

  <!-- Resources Section -->
  <section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-6">
      <h2 class="text-3xl font-black text-gray-900 mb-8">Additional Resources</h2>
      <div class="grid md:grid-cols-3 gap-6">

        <div class="bg-gradient-to-br from-slate-50 to-gray-100 rounded-2xl p-6 border border-gray-200">
          <h3 class="font-bold text-gray-900 mb-3">Public-Facing Content</h3>
          <ul class="space-y-2 text-sm text-gray-700">
            <li><a href="/faq-physicians.html" class="hover:text-brand-teal transition flex items-center gap-2">
              <span>→</span> Physician FAQ
            </a></li>
            <li><a href="/insurance-coverage.html" class="hover:text-brand-teal transition flex items-center gap-2">
              <span>→</span> Insurance Coverage Hub
            </a></li>
            <li><a href="/portal-guide.php" class="hover:text-brand-teal transition flex items-center gap-2">
              <span>→</span> Portal Usage Guide
            </a></li>
            <li><a href="/resources/" class="hover:text-brand-teal transition flex items-center gap-2">
              <span>→</span> Educational Content
            </a></li>
          </ul>
        </div>

        <div class="bg-gradient-to-br from-slate-50 to-gray-100 rounded-2xl p-6 border border-gray-200">
          <h3 class="font-bold text-gray-900 mb-3">Quick Stats</h3>
          <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex justify-between">
              <span>Reimbursement Success:</span>
              <strong>98%</strong>
            </li>
            <li class="flex justify-between">
              <span>Ship Time:</span>
              <strong>24-48hrs</strong>
            </li>
            <li class="flex justify-between">
              <span>Active Physicians:</span>
              <strong>2,500+</strong>
            </li>
            <li class="flex justify-between">
              <span>Products Available:</span>
              <strong>12+</strong>
            </li>
          </ul>
        </div>

        <div class="bg-gradient-to-br from-slate-50 to-gray-100 rounded-2xl p-6 border border-gray-200">
          <h3 class="font-bold text-gray-900 mb-3">Need Help?</h3>
          <p class="text-sm text-gray-700 mb-4">
            Questions about products, pricing, or sales strategy?
          </p>
          <a href="mailto:sales-support@collagendirect.health" class="inline-flex items-center gap-2 text-brand-teal font-semibold text-sm hover:gap-3 transition-all">
            Contact Sales Leadership
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
            </svg>
          </a>
        </div>

      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-gray-900 text-gray-400 py-8">
    <div class="max-w-7xl mx-auto px-6 text-center text-sm">
      <p>&copy; 2025 CollagenDirect. Internal Use Only - Do Not Distribute.</p>
    </div>
  </footer>

</body>
</html>
