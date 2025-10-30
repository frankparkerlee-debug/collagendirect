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
  <title>Objection Mastery | CollagenDirect Sales Training</title>
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
          <div class="text-sm font-bold text-gray-900">Objection Mastery</div>
          <div class="text-xs text-gray-500">Turn "no" into opportunity</div>
        </div>
      </div>
      <div class="flex items-center gap-4">
        <a href="index.php" class="text-sm text-gray-600 hover:text-brand-teal transition">Training Hub</a>
      </div>
    </div>
  </header>

  <div class="max-w-6xl mx-auto px-6 py-12">

    <!-- Hero -->
    <div class="bg-gradient-to-r from-brand-teal via-emerald-500 to-green-500 rounded-3xl p-12 text-white mb-12 text-center">
      <div class="text-6xl mb-4">💪</div>
      <h1 class="text-5xl font-black mb-4">Objection Mastery</h1>
      <p class="text-xl text-teal-50 max-w-3xl mx-auto mb-6">
        Objections aren't rejections—they're OPPORTUNITIES to deliver more value. Every "no" is really "I don't see the value YET." Your job? Show them what they're missing.
      </p>
      <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur px-6 py-3 rounded-xl">
        <span class="font-semibold">Remember: Great reps WELCOME objections because they reveal pain points</span>
      </div>
    </div>

    <!-- Mindset Shift -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10 border-4 border-yellow-500">
      <div class="text-center mb-8">
        <div class="text-5xl mb-4">🧠</div>
        <h2 class="text-3xl font-black text-gray-900 mb-4">Mindset Shift: Objections = Buying Signals</h2>
        <p class="text-lg text-gray-600 max-w-3xl mx-auto">
          When a physician says "we're all set," they're not slamming the door. They're saying "I need more information before I make a change." That's your cue to LISTEN and deliver value.
        </p>
      </div>

      <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-red-50 border-2 border-red-300 p-6 rounded-xl">
          <h3 class="font-bold text-lg text-red-900 mb-4">❌ Amateur Mindset</h3>
          <ul class="space-y-2 text-sm text-red-800">
            <li>• "They said no, I'll move on"</li>
            <li>• "I don't want to be pushy"</li>
            <li>• "They're happy with their supplier"</li>
            <li>• Takes objections personally</li>
            <li>• Gives up after first "no"</li>
          </ul>
        </div>

        <div class="bg-green-50 border-2 border-green-500 p-6 rounded-xl">
          <h3 class="font-bold text-lg text-green-900 mb-4">✅ Pro Mindset</h3>
          <ul class="space-y-2 text-sm text-green-800">
            <li>• "They need more info, let me deliver value"</li>
            <li>• "I'm helping, not selling"</li>
            <li>• "What pain are they not telling me?"</li>
            <li>• Sees objections as discovery opportunities</li>
            <li>• Knows "no" today can be "yes" tomorrow</li>
          </ul>
        </div>
      </div>

      <div class="mt-8 bg-yellow-50 border-l-4 border-yellow-600 p-6 rounded-r-xl">
        <p class="font-bold text-yellow-900 mb-2">💡 The Golden Rule of Objection Handling:</p>
        <p class="text-sm text-yellow-800">
          <strong>Acknowledge → Empathize → Reframe with Value → Ask a Question</strong>
        </p>
        <p class="text-xs text-yellow-700 mt-2">Never argue. Never dismiss. Never pitch harder. Instead, show them the value they don't see yet.</p>
      </div>
    </div>

    <!-- Top 10 Objections -->
    <div class="space-y-6">

      <!-- Objection 1 -->
      <div class="bg-white rounded-2xl shadow-lg p-8 border-l-4 border-brand-teal">
        <div class="flex items-start gap-4 mb-6">
          <div class="w-12 h-12 bg-brand-teal rounded-xl flex items-center justify-center flex-shrink-0 text-2xl text-white font-black">
            1
          </div>
          <div class="flex-1">
            <h3 class="text-2xl font-black text-gray-900 mb-2">"We're all set with our current supplier"</h3>
            <p class="text-sm text-gray-600">The most common objection—and the easiest to overcome with value selling</p>
          </div>
        </div>

        <div class="space-y-4">
          <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded-r-lg">
            <p class="text-xs font-bold text-blue-900 mb-2">💭 What They're REALLY Saying:</p>
            <p class="text-sm text-blue-800">"I don't see a reason to change. Show me what I'm missing."</p>
          </div>

          <div class="bg-gray-50 p-6 rounded-xl">
            <p class="text-sm font-bold text-gray-900 mb-4">🗣️ Value-Based Response Framework:</p>

            <div class="space-y-3">
              <div class="bg-white p-4 rounded-lg border-2 border-green-500">
                <p class="text-xs font-bold text-green-700 mb-2">ACKNOWLEDGE:</p>
                <p class="text-sm italic text-gray-700">"That's great to hear! I'm glad you have a supplier you trust."</p>
              </div>

              <div class="bg-white p-4 rounded-lg border-2 border-green-500">
                <p class="text-xs font-bold text-green-700 mb-2">EMPATHIZE:</p>
                <p class="text-sm italic text-gray-700">"Most of our best clients felt the same way before they tried us."</p>
              </div>

              <div class="bg-white p-4 rounded-lg border-2 border-green-500">
                <p class="text-xs font-bold text-green-700 mb-2">REFRAME WITH VALUE:</p>
                <p class="text-sm italic text-gray-700">"But here's what I'm hearing from clinics every day: they're frustrated with 5-7 day delivery times and surprise insurance denials 30 days later. We ship in 24-48 hours and verify coverage upfront—so you never get hit with a denied claim."</p>
              </div>

              <div class="bg-white p-4 rounded-lg border-2 border-green-500">
                <p class="text-xs font-bold text-green-700 mb-2">ASK A QUESTION:</p>
                <p class="text-sm italic text-gray-700">"Have you ever had a patient coming in Friday and realized you're out of collagen—and your supplier can't get it to you until next week?"</p>
              </div>
            </div>
          </div>

          <div class="bg-yellow-50 p-4 rounded-lg border-2 border-yellow-400">
            <p class="text-xs font-bold text-yellow-900 mb-2">💪 Why This Works:</p>
            <ul class="text-xs text-yellow-800 space-y-1">
              <li>• You didn't argue or push back</li>
              <li>• You validated their current situation</li>
              <li>• You introduced TWO pain points they might have (speed + insurance)</li>
              <li>• You asked a question that makes them think about their own pain</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Objection 2 -->
      <div class="bg-white rounded-2xl shadow-lg p-8 border-l-4 border-purple-600">
        <div class="flex items-start gap-4 mb-6">
          <div class="w-12 h-12 bg-purple-600 rounded-xl flex items-center justify-center flex-shrink-0 text-2xl text-white font-black">
            2
          </div>
          <div class="flex-1">
            <h3 class="text-2xl font-black text-gray-900 mb-2">"How much does it cost?"</h3>
            <p class="text-sm text-gray-600">Price objection = they're interested! Don't lead with price—lead with VALUE.</p>
          </div>
        </div>

        <div class="space-y-4">
          <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded-r-lg">
            <p class="text-xs font-bold text-blue-900 mb-2">💭 What They're REALLY Saying:</p>
            <p class="text-sm text-blue-800">"I'm interested, but I need to justify the investment. Show me the ROI."</p>
          </div>

          <div class="bg-gray-50 p-6 rounded-xl">
            <p class="text-sm font-bold text-gray-900 mb-4">🗣️ Value-Based Response:</p>

            <div class="bg-white p-4 rounded-lg border-2 border-green-500 mb-3">
              <p class="text-sm italic text-gray-700 mb-3">"Great question! The actual product cost is the same as any collagen supplier—we bill insurance directly using standard HCPCS codes. So from a pure product standpoint, it's apples to apples."</p>
              <p class="text-sm italic text-gray-700 mb-3">"Where you SAVE money is in three areas:"</p>
              <ul class="text-sm text-gray-700 space-y-2 ml-6">
                <li><strong>1. Staff time:</strong> Our 2-minute portal vs 20-minute phone calls = 10-15 hours saved per week. That's real money.</li>
                <li><strong>2. Cash flow:</strong> No denied claims means you get paid faster. Most clinics have 2-5% denial rates—we eliminate that.</li>
                <li><strong>3. Patient throughput:</strong> Faster healing (40% reduction) means fewer follow-up visits, so you can see more new patients.</li>
              </ul>
              <p class="text-sm italic text-gray-700 mt-3">"So yes, the product cost is standard, but your total cost of ownership actually goes DOWN because you're saving staff time and getting paid faster."</p>
            </div>
          </div>

          <div class="bg-yellow-50 p-4 rounded-lg border-2 border-yellow-400">
            <p class="text-xs font-bold text-yellow-900 mb-2">💪 Why This Works:</p>
            <ul class="text-xs text-yellow-800 space-y-1">
              <li>• You reframed "cost" as "total value delivered"</li>
              <li>• You quantified savings in three specific areas</li>
              <li>• You shifted focus from price to ROI</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Objection 3 -->
      <div class="bg-white rounded-2xl shadow-lg p-8 border-l-4 border-orange-600">
        <div class="flex items-start gap-4 mb-6">
          <div class="w-12 h-12 bg-orange-600 rounded-xl flex items-center justify-center flex-shrink-0 text-2xl text-white font-black">
            3
          </div>
          <div class="flex-1">
            <h3 class="text-2xl font-black text-gray-900 mb-2">"We don't have time to switch suppliers"</h3>
            <p class="text-sm text-gray-600">They're concerned about friction. Show them how EASY you make it.</p>
          </div>
        </div>

        <div class="space-y-4">
          <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded-r-lg">
            <p class="text-xs font-bold text-blue-900 mb-2">💭 What They're REALLY Saying:</p>
            <p class="text-sm text-blue-800">"Change sounds like work. Convince me it's worth the effort."</p>
          </div>

          <div class="bg-gray-50 p-6 rounded-xl">
            <p class="text-sm font-bold text-gray-900 mb-4">🗣️ Value-Based Response:</p>

            <div class="bg-white p-4 rounded-lg border-2 border-green-500">
              <p class="text-sm italic text-gray-700 mb-3">"I totally get that—you're busy treating patients, not dealing with admin work. That's exactly why we made registration super simple."</p>
              <p class="text-sm italic text-gray-700 mb-3">"Here's how it works: I'll help you register right now—takes 5 minutes. Then you can order your first patient's supplies today. You don't have to switch entirely—just try us for ONE patient and see how it compares."</p>
              <p class="text-sm italic text-gray-700">"If our 24-48 hour shipping and zero-hassle insurance process doesn't blow you away, stick with your current supplier. But I'm confident you'll see the difference."</p>
            </div>
          </div>

          <div class="bg-yellow-50 p-4 rounded-lg border-2 border-yellow-400">
            <p class="text-xs font-bold text-yellow-900 mb-2">💪 Why This Works:</p>
            <ul class="text-xs text-yellow-800 space-y-1">
              <li>• You acknowledged their concern (validation)</li>
              <li>• You offered to do the work FOR them (remove friction)</li>
              <li>• Low-risk trial close ("just one patient")</li>
              <li>• Confidence in your value proposition</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Objection 4 -->
      <div class="bg-white rounded-2xl shadow-lg p-8 border-l-4 border-red-600">
        <div class="flex items-start gap-4 mb-6">
          <div class="w-12 h-12 bg-red-600 rounded-xl flex items-center justify-center flex-shrink-0 text-2xl text-white font-black">
            4
          </div>
          <div class="flex-1">
            <h3 class="text-2xl font-black text-gray-900 mb-2">"Send me some information"</h3>
            <p class="text-sm text-gray-600">The polite brush-off. Don't just email and hope—create a next step.</p>
          </div>
        </div>

        <div class="space-y-4">
          <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded-r-lg">
            <p class="text-xs font-bold text-blue-900 mb-2">💭 What They're REALLY Saying:</p>
            <p class="text-sm text-blue-800">"I'm mildly interested but busy. Make it easy for me to evaluate you."</p>
          </div>

          <div class="bg-gray-50 p-6 rounded-xl">
            <p class="text-sm font-bold text-gray-900 mb-4">🗣️ Value-Based Response:</p>

            <div class="bg-white p-4 rounded-lg border-2 border-green-500">
              <p class="text-sm italic text-gray-700 mb-3">"Absolutely! I'll email you our product catalog and some case studies right after this call."</p>
              <p class="text-sm italic text-gray-700 mb-3">"But honestly, the best way to understand the difference is to see the portal in action—it takes 2 minutes and I can show you exactly how orders work, how we handle insurance, and how fast we ship."</p>
              <p class="text-sm italic text-gray-700">"Do you have 5 minutes right now, or should I call you back tomorrow morning at 9am? Either way, I'll send the materials today."</p>
            </div>
          </div>

          <div class="bg-yellow-50 p-4 rounded-lg border-2 border-yellow-400">
            <p class="text-xs font-bold text-yellow-900 mb-2">💪 Why This Works:</p>
            <ul class="text-xs text-yellow-800 space-y-1">
              <li>• You agreed to send info (compliance)</li>
              <li>• You reframed to a demo (more valuable than brochure)</li>
              <li>• You offered two specific times (assumptive close)</li>
              <li>• You maintained control of next steps</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Objection 5 -->
      <div class="bg-white rounded-2xl shadow-lg p-8 border-l-4 border-green-600">
        <div class="flex items-start gap-4 mb-6">
          <div class="w-12 h-12 bg-green-600 rounded-xl flex items-center justify-center flex-shrink-0 text-2xl text-white font-black">
            5
          </div>
          <div class="flex-1">
            <h3 class="text-2xl font-black text-gray-900 mb-2">"Does insurance cover this?"</h3>
            <p class="text-sm text-gray-600">Great buying signal! They're already thinking about using it.</p>
          </div>
        </div>

        <div class="space-y-4">
          <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded-r-lg">
            <p class="text-xs font-bold text-blue-900 mb-2">💭 What They're REALLY Saying:</p>
            <p class="text-sm text-blue-800">"I'm interested, but I need financial certainty before I commit."</p>
          </div>

          <div class="bg-gray-50 p-6 rounded-xl">
            <p class="text-sm font-bold text-gray-900 mb-4">🗣️ Value-Based Response:</p>

            <div class="bg-white p-4 rounded-lg border-2 border-green-500">
              <p class="text-sm italic text-gray-700 mb-3">"Yes—Medicare, Medicaid, and most commercial plans cover collagen for chronic wounds. It's billed under standard HCPCS codes like A6010 and A6021."</p>
              <p class="text-sm italic text-gray-700 mb-3">"But here's what makes us different: most suppliers make you order first, THEN find out 30 days later if insurance denied it. We verify eligibility BEFORE you even place the order."</p>
              <p class="text-sm italic text-gray-700">"So when you're looking at a patient in the portal, you'll see right away: 'Covered' or 'Needs prior auth.' No surprise denials, no chasing down paperwork after the fact. You know upfront what's covered."</p>
            </div>
          </div>

          <div class="bg-yellow-50 p-4 rounded-lg border-2 border-yellow-400">
            <p class="text-xs font-bold text-yellow-900 mb-2">💪 Why This Works:</p>
            <ul class="text-xs text-yellow-800 space-y-1">
              <li>• You answered their question (yes, it's covered)</li>
              <li>• You differentiated with value (pre-verification)</li>
              <li>• You removed their biggest fear (denied claims)</li>
            </ul>
          </div>
        </div>
      </div>

    </div>

    <!-- Advanced Techniques -->
    <div class="bg-gradient-to-r from-brand-navy to-slate-800 text-white rounded-3xl p-10 mt-12 mb-10">
      <h2 class="text-3xl font-black mb-6">🎓 Advanced Objection Techniques</h2>

      <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-white/10 backdrop-blur rounded-xl p-6">
          <h3 class="font-bold text-lg mb-3">1. The Boomerang Technique</h3>
          <p class="text-sm text-slate-200 mb-3">Turn their objection into a REASON to buy:</p>
          <div class="bg-white/20 p-3 rounded-lg text-sm">
            <p class="italic mb-2">"We're too busy to switch suppliers."</p>
            <p class="font-bold">→ "That's exactly WHY you should try us. Our 2-minute portal saves you 15 hours a week—so you'll have MORE time for patients, not less."</p>
          </div>
        </div>

        <div class="bg-white/10 backdrop-blur rounded-xl p-6">
          <h3 class="font-bold text-lg mb-3">2. The Feel-Felt-Found Method</h3>
          <p class="text-sm text-slate-200 mb-3">Show empathy with social proof:</p>
          <div class="bg-white/20 p-3 rounded-lg text-sm">
            <p class="italic mb-2">"We're happy with our current supplier."</p>
            <p class="font-bold">→ "I totally understand how you FEEL. Most of our clients FELT the same way. But what they FOUND was that 24-48 hour delivery vs 7 days made a huge difference in patient care."</p>
          </div>
        </div>

        <div class="bg-white/10 backdrop-blur rounded-xl p-6">
          <h3 class="font-bold text-lg mb-3">3. The Trial Close</h3>
          <p class="text-sm text-slate-200 mb-3">Make it low-risk to try:</p>
          <div class="bg-white/20 p-3 rounded-lg text-sm">
            <p class="font-bold">"You don't have to commit to anything. Just order for ONE patient this week and compare. If it's not faster, easier, and better covered than your current supplier—stick with them. Fair?"</p>
          </div>
        </div>

        <div class="bg-white/10 backdrop-blur rounded-xl p-6">
          <h3 class="font-bold text-lg mb-3">4. The Question Reversal</h3>
          <p class="text-sm text-slate-200 mb-3">Answer with a value question:</p>
          <div class="bg-white/20 p-3 rounded-lg text-sm">
            <p class="italic mb-2">"How much does it cost?"</p>
            <p class="font-bold">→ "Fair question. Let me ask you this: if I could show you a way to get products twice as fast, eliminate denied claims, and save your staff 15 hours a week—would the product cost even matter?"</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Closing Motivation -->
    <div class="bg-gradient-to-r from-purple-600 via-pink-500 to-red-500 rounded-3xl p-12 text-white text-center">
      <div class="text-6xl mb-4">🔥</div>
      <h2 class="text-4xl font-black mb-4">Remember: Objections Mean Interest!</h2>
      <p class="text-xl max-w-3xl mx-auto mb-8">
        If a physician says "we're all set," they didn't hang up on you. They're TALKING to you. That means they're evaluating you. Your job is to deliver so much value that switching becomes a no-brainer.
      </p>
      <div class="grid md:grid-cols-3 gap-6 max-w-4xl mx-auto">
        <div class="bg-white/10 backdrop-blur rounded-xl p-6">
          <div class="text-3xl mb-2">👂</div>
          <p class="font-bold mb-1">Listen More Than You Talk</p>
          <p class="text-sm text-pink-100">Objections reveal pain points</p>
        </div>
        <div class="bg-white/10 backdrop-blur rounded-xl p-6">
          <div class="text-3xl mb-2">💰</div>
          <p class="font-bold mb-1">Lead with Value, Not Price</p>
          <p class="text-sm text-pink-100">ROI beats cost every time</p>
        </div>
        <div class="bg-white/10 backdrop-blur rounded-xl p-6">
          <div class="text-3xl mb-2">🤝</div>
          <p class="font-bold mb-1">Never Argue, Always Help</p>
          <p class="text-sm text-pink-100">You're solving their problems</p>
        </div>
      </div>
      <p class="text-lg mt-8 text-orange-100">
        Next: See how real reps crushed these objections in <a href="success-stories.php" class="underline font-bold">Success Stories</a>
      </p>
    </div>

  </div>

</body>
</html>
