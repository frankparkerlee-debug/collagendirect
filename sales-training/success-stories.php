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
  <title>Success Stories | CollagenDirect Sales Training</title>
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
      <div class="flex items-center gap-3">
        <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-8 w-auto">
        <div>
          <div class="text-sm font-bold text-gray-900">Success Stories</div>
          <div class="text-xs text-gray-500">Learn from our top performers</div>
        </div>
      </div>
      <a href="index.php" class="text-sm text-gray-600 hover:text-brand-teal transition">Training Hub</a>
    </div>
  </header>

  <div class="max-w-6xl mx-auto px-6 py-12">

    <div class="bg-gradient-to-r from-yellow-400 via-orange-500 to-red-500 rounded-3xl p-12 text-white mb-12 text-center">
      <div class="text-6xl mb-4">🏆</div>
      <h1 class="text-5xl font-black mb-4">Rep Success Stories</h1>
      <p class="text-xl text-orange-50 max-w-3xl mx-auto">
        Real wins from real reps. These are YOUR future colleagues crushing it with value selling. If they can do it, SO CAN YOU.
      </p>
    </div>

    <!-- Success Story 1 -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-8 border-l-8 border-green-500">
      <div class="flex items-start gap-6 mb-6">
        <div class="w-20 h-20 bg-green-500 rounded-full flex items-center justify-center text-3xl flex-shrink-0">💰</div>
        <div>
          <h2 class="text-2xl font-black text-gray-900 mb-2">From $0 to $45K in Month 1</h2>
          <p class="text-sm text-gray-600">Sarah T., Territory Rep - Atlanta</p>
        </div>
      </div>

      <div class="space-y-4">
        <div class="bg-yellow-50 border-l-4 border-yellow-600 p-6 rounded-r-xl">
          <p class="text-sm font-bold text-yellow-900 mb-2">The Challenge:</p>
          <p class="text-sm text-yellow-800">"I was brand new to medical sales. Never sold anything before. My first week, I made 50 cold calls and got ZERO meetings. I almost quit."</p>
        </div>

        <div class="bg-blue-50 border-l-4 border-blue-600 p-6 rounded-r-xl">
          <p class="text-sm font-bold text-blue-900 mb-2">The Breakthrough:</p>
          <p class="text-sm text-blue-800 mb-3">"My manager told me to stop pitching collagen and start asking about PAIN. So I changed my script to: 'I work with podiatrists who are frustrated with slow deliveries and denied claims.'"</p>
          <p class="text-sm text-blue-800">"Boom. Suddenly I'm booking 3-4 lunches per week because I'm talking about THEIR problems, not my products."</p>
        </div>

        <div class="bg-green-50 border-l-4 border-green-600 p-6 rounded-r-xl">
          <p class="text-sm font-bold text-green-900 mb-2">The Results:</p>
          <ul class="text-sm text-green-800 space-y-2">
            <li>• Closed 12 new accounts in month 1</li>
            <li>• $45,000 in first month revenue</li>
            <li>• Promoted to Senior Rep after 6 months</li>
          </ul>
        </div>

        <div class="bg-gray-50 p-6 rounded-xl">
          <p class="text-sm font-bold text-gray-900 mb-2">💡 Key Lesson:</p>
          <p class="text-sm italic text-gray-700">"Stop selling products. Start solving pain. When I focused on their delivery delays and insurance headaches, they practically begged me to show them the portal."</p>
        </div>
      </div>
    </div>

    <!-- Success Story 2 -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-8 border-l-8 border-blue-500">
      <div class="flex items-start gap-6 mb-6">
        <div class="w-20 h-20 bg-blue-500 rounded-full flex items-center justify-center text-3xl flex-shrink-0">🎯</div>
        <div>
          <h2 class="text-2xl font-black text-gray-900 mb-2">Flipped "We're All Set" into $180K Account</h2>
          <p class="text-sm text-gray-600">Marcus J., Senior Rep - Houston</p>
        </div>
      </div>

      <div class="space-y-4">
        <div class="bg-yellow-50 border-l-4 border-yellow-600 p-6 rounded-r-xl">
          <p class="text-sm font-bold text-yellow-900 mb-2">The Situation:</p>
          <p class="text-sm text-yellow-800">"Large wound care clinic, 200+ diabetic ulcer patients per month. They said 'we're all set with our current supplier' five times. Most reps would've given up."</p>
        </div>

        <div class="bg-blue-50 border-l-4 border-blue-600 p-6 rounded-r-xl">
          <p class="text-sm font-bold text-blue-900 mb-2">The Strategy:</p>
          <p class="text-sm text-blue-800 mb-3">"I didn't argue. I said: 'That's great! Just curious—how long does it take to get collagen when you order?' They said 5-7 days. I said: 'What happens when a patient comes in Friday and you're out?'"</p>
          <p class="text-sm text-blue-800">"The nurse manager's eyes lit up. She said 'we lose the patient for a week and they often don't come back.' THAT was the pain point."</p>
        </div>

        <div class="bg-green-50 border-l-4 border-green-600 p-6 rounded-r-xl">
          <p class="text-sm font-bold text-green-900 mb-2">The Close:</p>
          <p class="text-sm text-green-800">"I said: 'What if you could order Thursday night and have it Friday morning?' Showed her the portal. She ordered one patient to test. Next week, she switched the entire clinic over. Now it's our biggest account."</p>
        </div>

        <div class="bg-gray-50 p-6 rounded-xl">
          <p class="text-sm font-bold text-gray-900 mb-2">💡 Key Lesson:</p>
          <p class="text-sm italic text-gray-700">"'We're all set' doesn't mean no. It means 'I don't see the value yet.' Your job is to find the pain they're not telling you about. Ask better questions."</p>
        </div>
      </div>
    </div>

    <!-- Success Story 3 -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-8 border-l-8 border-purple-500">
      <div class="flex items-start gap-6 mb-6">
        <div class="w-20 h-20 bg-purple-500 rounded-full flex items-center justify-center text-3xl flex-shrink-0">⚡</div>
        <div>
          <h2 class="text-2xl font-black text-gray-900 mb-2">Rookie to Top Performer in 90 Days</h2>
          <p class="text-sm text-gray-600">Jessica M., Territory Rep - Phoenix</p>
        </div>
      </div>

      <div class="space-y-4">
        <div class="bg-yellow-50 border-l-4 border-yellow-600 p-6 rounded-r-xl">
          <p class="text-sm font-bold text-yellow-900 mb-2">The Start:</p>
          <p class="text-sm text-yellow-800">"I had ZERO medical device experience. Just graduated college. My first month was rough—lots of rejection, lots of gatekeepers hanging up on me."</p>
        </div>

        <div class="bg-blue-50 border-l-4 border-blue-600 p-6 rounded-r-xl">
          <p class="text-sm font-bold text-blue-900 mb-2">The Turning Point:</p>
          <p class="text-sm text-blue-800 mb-3">"I started tracking every objection. 'We're all set' came up 40% of the time. So I practiced ONE response until it was automatic."</p>
          <p class="text-sm text-blue-800">"I'd say: 'Most of our clients said the same thing—until they tried 24-hour delivery and zero denied claims. Worth a 10-minute conversation?' That line alone got me 2-3 meetings per day."</p>
        </div>

        <div class="bg-green-50 border-l-4 border-green-600 p-6 rounded-r-xl">
          <p class="text-sm font-bold text-green-900 mb-2">The Results:</p>
          <ul class="text-sm text-green-800 space-y-2">
            <li>• Month 1: $18K revenue (bottom of the team)</li>
            <li>• Month 2: $52K revenue (middle of the pack)</li>
            <li>• Month 3: $87K revenue (#1 rep in the region)</li>
            <li>• Won "Rookie of the Year" award</li>
          </ul>
        </div>

        <div class="bg-gray-50 p-6 rounded-xl">
          <p class="text-sm font-bold text-gray-900 mb-2">💡 Key Lesson:</p>
          <p class="text-sm italic text-gray-700">"Master ONE objection response at a time. Don't try to memorize 50 scripts. Get really good at handling 'we're all set,' then move to the next objection. Consistency beats complexity."</p>
        </div>
      </div>
    </div>

    <!-- Your Turn CTA -->
    <div class="bg-gradient-to-r from-brand-teal via-emerald-500 to-green-500 rounded-3xl p-12 text-white text-center">
      <div class="text-6xl mb-6">🚀</div>
      <h2 class="text-4xl font-black mb-4">Your Success Story Starts NOW</h2>
      <p class="text-xl max-w-3xl mx-auto mb-8">
        Sarah, Marcus, and Jessica were rookies just like you. They learned the value selling framework, practiced their objection responses, and crushed it. There's NOTHING stopping you from being next month's success story.
      </p>

      <div class="grid md:grid-cols-3 gap-6 max-w-4xl mx-auto mb-8">
        <div class="bg-white/10 backdrop-blur rounded-xl p-6">
          <div class="text-3xl mb-2">📚</div>
          <p class="font-bold mb-2">Study the Training</p>
          <p class="text-sm text-teal-50">Product knowledge, objections, value selling</p>
        </div>
        <div class="bg-white/10 backdrop-blur rounded-xl p-6">
          <div class="text-3xl mb-2">💪</div>
          <p class="font-bold mb-2">Practice the Scripts</p>
          <p class="text-sm text-teal-50">Role play until responses are automatic</p>
        </div>
        <div class="bg-white/10 backdrop-blur rounded-xl p-6">
          <div class="text-3xl mb-2">🎯</div>
          <p class="font-bold mb-2">Execute with Confidence</p>
          <p class="text-sm text-teal-50">Make calls, book meetings, close deals</p>
        </div>
      </div>

      <div class="flex flex-wrap gap-4 justify-center">
        <a href="quick-start-guide.php" class="px-8 py-4 bg-white text-brand-teal font-bold rounded-xl hover:shadow-xl transition">Start With Quick Start Guide</a>
        <a href="product-training.php" class="px-8 py-4 bg-brand-navy text-white font-bold rounded-xl hover:shadow-xl transition">Master Product Knowledge</a>
        <a href="objections.php" class="px-8 py-4 bg-brand-navy text-white font-bold rounded-xl hover:shadow-xl transition">Practice Objection Handling</a>
      </div>
    </div>

  </div>

</body>
</html>
