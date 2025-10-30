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
  <title>Sales Scripts & Talk Tracks | Sales Training</title>
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

  <div class="max-w-5xl mx-auto px-6 py-12">

    <div class="mb-12 text-center">
      <h1 class="text-4xl font-black text-gray-900 mb-4">Sales Scripts & Talk Tracks</h1>
      <p class="text-xl text-gray-600">
        Proven conversation frameworks for every stage of the sales cycle
      </p>
    </div>

    <!-- Cold Call Script -->
    <div class="bg-white rounded-3xl shadow-lg p-8 mb-8">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
          </svg>
        </div>
        <div>
          <h2 class="text-2xl font-black text-gray-900">Cold Call Script</h2>
          <p class="text-sm text-gray-600">First outreach to wound care physicians</p>
        </div>
      </div>

      <div class="space-y-4 text-sm">
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg">
          <div class="font-bold text-blue-900 mb-2">Opening (15 seconds)</div>
          <p class="text-blue-800 leading-relaxed">
            "Good morning Dr. [Name], this is [Your Name] from CollagenDirect. We help wound care specialists like you streamline collagen ordering while maintaining 98% reimbursement rates. Do you have 2 minutes for me to explain how we're different from traditional DME suppliers?"
          </p>
        </div>

        <div class="bg-gray-50 p-4 rounded-lg">
          <div class="font-bold text-gray-900 mb-2">If YES → Value Proposition (30 seconds)</div>
          <p class="text-gray-700 leading-relaxed">
            "Great. The three things our physicians love most: First, we ship in 24-48 hours instead of the typical 5-7 days, so your patients start healing faster. Second, our digital portal lets you order in 3 clicks - no faxing 10-page forms. Third, we pre-verify every patient's insurance and handle all denials, which saves your front office about 4 hours per week. Most practices see their first patient order within a week of signing up."
          </p>
        </div>

        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
          <div class="font-bold text-emerald-900 mb-2">Next Step / Close</div>
          <p class="text-emerald-800 leading-relaxed">
            "I'd love to show you the portal in a quick 15-minute demo. Are you available Thursday at 2pm or would Friday morning work better?"
          </p>
        </div>

        <div class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded-r-lg">
          <div class="font-bold text-orange-900 mb-2">If GATEKEEPER (Receptionist/MA)</div>
          <p class="text-orange-800 leading-relaxed">
            "Hi, I'm [Name] from CollagenDirect. I work with wound care physicians to simplify their collagen supply ordering. Who typically handles wound care product ordering for Dr. [Name]'s practice?"
          </p>
        </div>
      </div>
    </div>

    <!-- Demo Script -->
    <div class="bg-white rounded-3xl shadow-lg p-8 mb-8">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
          </svg>
        </div>
        <div>
          <h2 class="text-2xl font-black text-gray-900">Portal Demo Script</h2>
          <p class="text-sm text-gray-600">15-minute portal walkthrough</p>
        </div>
      </div>

      <div class="space-y-4 text-sm">
        <div class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded-r-lg">
          <div class="font-bold text-purple-900 mb-2">Introduction (2 min)</div>
          <p class="text-purple-800 leading-relaxed">
            "Thanks for joining! I'm going to show you exactly what your front office staff would do to order collagen products for a patient. The whole process takes about 90 seconds once you're set up. Let me share my screen..."
          </p>
        </div>

        <div class="space-y-2">
          <div class="flex items-start gap-3">
            <div class="w-6 h-6 bg-brand-teal text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold">1</div>
            <div>
              <div class="font-bold text-gray-900">Dashboard Overview (2 min)</div>
              <p class="text-gray-700">
                "When you log in, you see all your active patients. Here's a patient with a diabetic foot ulcer we're tracking. Click 'Create Order' and watch how simple this is..."
              </p>
            </div>
          </div>

          <div class="flex items-start gap-3">
            <div class="w-6 h-6 bg-brand-teal text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold">2</div>
            <div>
              <div class="font-bold text-gray-900">Product Selection (3 min)</div>
              <p class="text-gray-700">
                "The system asks simple questions: Wound type? Size? Infection present? Based on your answers, it recommends the right product. For this 3x3cm diabetic ulcer with light drainage, it suggests our 3x3 collagen sheet - HCPCS A6021."
              </p>
            </div>
          </div>

          <div class="flex items-start gap-3">
            <div class="w-6 h-6 bg-brand-teal text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold">3</div>
            <div>
              <div class="font-bold text-gray-900">Insurance Verification (3 min)</div>
              <p class="text-gray-700">
                "We automatically pull the patient's insurance from your EHR or you can enter it manually. See this green checkmark? That means we've verified Medicare Part B coverage. Patient's estimated cost: $12."
              </p>
            </div>
          </div>

          <div class="flex items-start gap-3">
            <div class="w-6 h-6 bg-brand-teal text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold">4</div>
            <div>
              <div class="font-bold text-gray-900">Order Placement (2 min)</div>
              <p class="text-gray-700">
                "Click 'Submit Order' and you're done. The patient receives a text notification that products are shipping. Delivery in 24-48 hours. That's it - total time: 90 seconds."
              </p>
            </div>
          </div>

          <div class="flex items-start gap-3">
            <div class="w-6 h-6 bg-brand-teal text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold">5</div>
            <div>
              <div class="font-bold text-gray-900">Reporting & Tracking (3 min)</div>
              <p class="text-gray-700">
                "Go back to the dashboard. You can see all active orders, patient progress photos, healing timelines. This wound is showing 40% closure after 2 weeks - that's the data you need for documentation."
              </p>
            </div>
          </div>
        </div>

        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg mt-4">
          <div class="font-bold text-emerald-900 mb-2">Closing Question</div>
          <p class="text-emerald-800 leading-relaxed">
            "Compared to faxing forms or calling your current supplier, this is pretty straightforward, right? Would you like to get your account set up today or do you have questions first?"
          </p>
        </div>
      </div>
    </div>

    <!-- Follow-Up Email Templates -->
    <div class="bg-white rounded-3xl shadow-lg p-8 mb-8">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
          </svg>
        </div>
        <div>
          <h2 class="text-2xl font-black text-gray-900">Follow-Up Email Templates</h2>
          <p class="text-sm text-gray-600">Copy-paste templates for common scenarios</p>
        </div>
      </div>

      <div class="space-y-6">
        <!-- Template 1 -->
        <div class="bg-gray-50 rounded-xl p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-900">After Initial Call (No Demo Scheduled)</h3>
            <button onclick="copyToClipboard('email1')" class="text-xs px-3 py-1 bg-brand-teal text-white rounded-lg hover:bg-brand-navy transition">Copy</button>
          </div>
          <div id="email1" class="bg-white p-4 rounded-lg border border-gray-200 font-mono text-xs text-gray-700 leading-relaxed">
Subject: Quick follow-up - CollagenDirect for [Practice Name]<br><br>

Hi Dr. [Name],<br><br>

Thanks for speaking with me earlier about streamlining your collagen product ordering. I wanted to share a few quick resources:<br><br>

• <strong>Product Comparison Chart</strong>: See our full catalog with HCPCS codes<br>
• <strong>Insurance Coverage Guide</strong>: Medicare, Medicaid, and major commercial payers<br>
• <strong>Portal Demo Video</strong> (2 min): See the ordering process in action<br><br>

Most physicians are surprised by how much time they save - about 4 hours per week for a typical wound care practice.<br><br>

Would you be open to a quick 15-minute demo this week? I have openings Thursday 2pm or Friday 10am.<br><br>

Best,<br>
[Your Name]<br>
[Your Phone] | [Your Email]
          </div>
        </div>

        <!-- Template 2 -->
        <div class="bg-gray-50 rounded-xl p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-900">After Demo (Interested But Not Ready)</h3>
            <button onclick="copyToClipboard('email2')" class="text-xs px-3 py-1 bg-brand-teal text-white rounded-lg hover:bg-brand-navy transition">Copy</button>
          </div>
          <div id="email2" class="bg-white p-4 rounded-lg border border-gray-200 font-mono text-xs text-gray-700 leading-relaxed">
Subject: Sample products for [Practice Name]<br><br>

Dr. [Name],<br><br>

Great speaking with you! Per our demo, I'm sending you sample collagen sheets (2x2, 3x3, 4x4) so you can see the quality firsthand.<br><br>

In the meantime, here's what I'll do on my end:<br>
• Set up your practice account (you can activate anytime - no obligation)<br>
• Add your team members so they can explore the portal<br>
• Pre-load your top 10 wound care patients for easy ordering<br><br>

When the samples arrive, try them on your next diabetic foot ulcer or pressure injury. If you're satisfied with the results, just click "Activate Account" and you'll be live.<br><br>

Questions? Call/text me anytime at [Your Phone].<br><br>

[Your Name]
          </div>
        </div>

        <!-- Template 3 -->
        <div class="bg-gray-50 rounded-xl p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-900">After Demo (Ready to Sign Up)</h3>
            <button onclick="copyToClipboard('email3')" class="text-xs px-3 py-1 bg-brand-teal text-white rounded-lg hover:bg-brand-navy transition">Copy</button>
          </div>
          <div id="email3" class="bg-white p-4 rounded-lg border border-gray-200 font-mono text-xs text-gray-700 leading-relaxed">
Subject: Welcome to CollagenDirect! Next steps<br><br>

Dr. [Name],<br><br>

Exciting! Welcome aboard. Your portal account is live at: https://collagendirect.health/login<br><br>

Login credentials:<br>
• Email: [their email]<br>
• Temporary password: [password] (please change after first login)<br><br>

<strong>Next Steps:</strong><br>
1. Log in and update your password<br>
2. Add your front office staff as users (Settings → Team Members)<br>
3. Create your first patient order (should take ~2 minutes)<br><br>

I'll check in next week to see how your first orders went. In the meantime, our support team is available 8am-6pm EST at support@collagendirect.health or (800) XXX-XXXX.<br><br>

Looking forward to partnering with you!<br><br>

[Your Name]
          </div>
        </div>

      </div>
    </div>

    <!-- Voicemail Script -->
    <div class="bg-white rounded-3xl shadow-lg p-8 mb-8">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-500 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
          </svg>
        </div>
        <div>
          <h2 class="text-2xl font-black text-gray-900">Voicemail Script</h2>
          <p class="text-sm text-gray-600">Keep it under 20 seconds</p>
        </div>
      </div>

      <div class="bg-orange-50 border-l-4 border-orange-500 p-6 rounded-r-lg">
        <p class="text-sm text-orange-900 leading-relaxed">
          "Hi Dr. [Name], this is [Your Name] from CollagenDirect. We help wound care physicians ship collagen products in 24 hours instead of waiting a week. I'll send you a quick email with details. My number is [Your Phone]. Thanks!"
        </p>
      </div>

      <div class="mt-4 text-sm text-gray-600">
        <strong>Pro Tip:</strong> Always follow a voicemail with an email within 5 minutes. They're more likely to respond to email.
      </div>
    </div>

  </div>

  <script>
    function copyToClipboard(elementId) {
      const element = document.getElementById(elementId);
      const text = element.textContent;
      navigator.clipboard.writeText(text).then(() => {
        alert('Email template copied to clipboard!');
      });
    }
  </script>

</body>
</html>
