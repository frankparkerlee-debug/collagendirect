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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HIPAA Training | CollagenDirect</title>
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

  <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
      <a href="new-hire-welcome.php" class="text-brand-teal hover:text-brand-navy transition flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Back to Onboarding
      </a>
    </div>
  </header>

  <div class="max-w-4xl mx-auto px-6 py-12">

    <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded-r-lg mb-8">
      <div class="flex items-start gap-3">
        <svg class="w-6 h-6 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
        </svg>
        <div>
          <h1 class="text-2xl font-black text-red-900 mb-2">HIPAA Training Module</h1>
          <p class="text-red-800">
            REQUIRED for all employees. This training must be completed before you can access patient information.
          </p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-3xl shadow-xl p-10">
      <h2 class="text-3xl font-black text-gray-900 mb-6">Placeholder: Integration Required</h2>

      <p class="text-gray-700 mb-6">
        This page is a placeholder for your HIPAA training module. You should integrate with a compliant HIPAA training platform such as:
      </p>

      <div class="space-y-4 mb-8">
        <div class="bg-blue-50 border-l-4 border-blue-500 p-5 rounded-r-lg">
          <h3 class="font-bold text-blue-900 mb-2">Option 1: Compliancy Group</h3>
          <p class="text-sm text-blue-800 mb-2">Comprehensive HIPAA training with certificates</p>
          <a href="https://compliancy-group.com" target="_blank" class="text-xs text-blue-700 hover:underline">→ Learn more</a>
        </div>

        <div class="bg-blue-50 border-l-4 border-blue-500 p-5 rounded-r-lg">
          <h3 class="font-bold text-blue-900 mb-2">Option 2: HIPAA Exams</h3>
          <p class="text-sm text-blue-800 mb-2">Online HIPAA training and certification</p>
          <a href="https://www.hipaaexams.com" target="_blank" class="text-xs text-blue-700 hover:underline">→ Learn more</a>
        </div>

        <div class="bg-blue-50 border-l-4 border-blue-500 p-5 rounded-r-lg">
          <h3 class="font-bold text-blue-900 mb-2">Option 3: Custom In-House Training</h3>
          <p class="text-sm text-blue-800 mb-2">Develop your own training content and quiz</p>
        </div>
      </div>

      <h3 class="text-xl font-bold text-gray-900 mb-4">What This Training Should Cover:</h3>
      <ul class="space-y-2 text-gray-700 mb-8">
        <li class="flex items-start gap-2">
          <span class="text-brand-teal">✓</span>
          <span>What is PHI (Protected Health Information)</span>
        </li>
        <li class="flex items-start gap-2">
          <span class="text-brand-teal">✓</span>
          <span>HIPAA Privacy Rule basics</span>
        </li>
        <li class="flex items-start gap-2">
          <span class="text-brand-teal">✓</span>
          <span>HIPAA Security Rule requirements</span>
        </li>
        <li class="flex items-start gap-2">
          <span class="text-brand-teal">✓</span>
          <span>Breach notification procedures</span>
        </li>
        <li class="flex items-start gap-2">
          <span class="text-brand-teal">✓</span>
          <span>Patient rights under HIPAA</span>
        </li>
        <li class="flex items-start gap-2">
          <span class="text-brand-teal">✓</span>
          <span>CollagenDirect-specific policies for handling PHI</span>
        </li>
        <li class="flex items-start gap-2">
          <span class="text-brand-teal">✓</span>
          <span>Consequences of HIPAA violations</span>
        </li>
      </ul>

      <div class="bg-yellow-50 border-l-4 border-yellow-500 p-6 rounded-r-lg">
        <h4 class="font-bold text-yellow-900 mb-2">For Now:</h4>
        <p class="text-sm text-yellow-800 mb-4">
          Until you integrate a formal HIPAA training platform, have new hires:
        </p>
        <ol class="list-decimal list-inside space-y-2 text-sm text-yellow-800">
          <li>Watch a HIPAA overview video (YouTube has many free resources)</li>
          <li>Read your internal HIPAA policies document</li>
          <li>Sign the HIPAA confidentiality agreement</li>
          <li>Schedule a meeting with the compliance officer for Q&A</li>
        </ol>
      </div>

      <div class="mt-8">
        <a href="new-hire-welcome.php" class="inline-flex items-center gap-2 px-6 py-3 bg-brand-teal text-white font-bold rounded-xl hover:bg-brand-navy transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
          </svg>
          Back to Onboarding Checklist
        </a>
      </div>
    </div>

  </div>

</body>
</html>
