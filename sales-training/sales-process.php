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
  <title>4-Step Sales Process | Sales Training</title>
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

  <div class="max-w-6xl mx-auto px-6 py-12">

    <div class="mb-12 text-center">
      <h1 class="text-5xl font-black text-gray-900 mb-4">The CollagenDirect Sales Process</h1>
      <p class="text-xl text-gray-600 max-w-3xl mx-auto">
        A relationship-driven, 4-step approach to winning and retaining wound care physicians
      </p>
    </div>

    <!-- Process Overview -->
    <div class="bg-gradient-to-r from-brand-navy to-slate-800 text-white rounded-3xl p-8 mb-12">
      <h2 class="text-2xl font-bold mb-6">Our Sales Philosophy</h2>
      <p class="text-lg text-slate-200 mb-6 leading-relaxed">
        We don't sell products. We solve physician pain points. Our job is to make wound care easier, faster, and more profitable for busy doctors who are frustrated with slow suppliers, insurance headaches, and complex ordering systems.
      </p>
      <div class="grid md:grid-cols-4 gap-4 text-sm">
        <div class="bg-white/10 backdrop-blur rounded-xl p-4">
          <div class="text-3xl font-black mb-2">1</div>
          <div class="font-bold mb-1">Get the Meeting</div>
          <div class="text-slate-300 text-xs">Lunch or call with doctor</div>
        </div>
        <div class="bg-white/10 backdrop-blur rounded-xl p-4">
          <div class="text-3xl font-black mb-2">2</div>
          <div class="font-bold mb-1">Doctor Conversation</div>
          <div class="text-slate-300 text-xs">Focus on their pain points</div>
        </div>
        <div class="bg-white/10 backdrop-blur rounded-xl p-4">
          <div class="text-3xl font-black mb-2">3</div>
          <div class="font-bold mb-1">Help Register</div>
          <div class="text-slate-300 text-xs">Solve patient/doc issues</div>
        </div>
        <div class="bg-white/10 backdrop-blur rounded-xl p-4">
          <div class="text-3xl font-black mb-2">4</div>
          <div class="font-bold mb-1">Nurture</div>
          <div class="text-slate-300 text-xs">Build long-term relationship</div>
        </div>
      </div>
    </div>

    <!-- STEP 1: GET THE MEETING -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <div class="flex items-center gap-4 mb-8">
        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center flex-shrink-0">
          <span class="text-3xl font-black text-white">1</span>
        </div>
        <div>
          <h2 class="text-3xl font-black text-gray-900">Get the Meeting</h2>
          <p class="text-gray-600">Goal: Secure lunch with doctor present OR phone call with doctor</p>
        </div>
      </div>

      <!-- Gatekeeper Strategy -->
      <div class="mb-8">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Getting Past the Front Desk</h3>
        <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded-r-lg mb-4">
          <div class="font-bold text-blue-900 mb-3">Cold Call to Office (Receptionist)</div>
          <p class="text-sm text-blue-800 mb-4 leading-relaxed">
            <strong>You:</strong> "Good morning! This is [Name] from CollagenDirect. I work with wound care physicians to help them get faster product delivery and better reimbursement. Who typically handles wound care supply ordering for Dr. [Name]?"
          </p>
          <div class="bg-white rounded-lg p-4 border border-blue-200">
            <div class="text-xs font-bold text-blue-900 mb-2">KEY INSIGHT:</div>
            <p class="text-xs text-blue-800">You're not selling yet. You're finding the decision-maker. Often it's an office manager or MA, not the doctor directly.</p>
          </div>
        </div>

        <div class="space-y-3">
          <div class="border-l-4 border-emerald-400 pl-4">
            <div class="font-bold text-gray-900 text-sm">If they connect you to Office Manager/MA:</div>
            <p class="text-sm text-gray-700 mt-1">
              "Hi [Name], I help wound care practices save time on ordering and reduce insurance denials. I'd love to buy lunch for Dr. [Name] and your team to show how we're different from traditional suppliers. Does Thursday or Friday work better?"
            </p>
          </div>

          <div class="border-l-4 border-emerald-400 pl-4">
            <div class="font-bold text-gray-900 text-sm">If they say "Send information":</div>
            <p class="text-sm text-gray-700 mt-1">
              "Absolutely, I'll email you details. But most physicians tell me a 10-minute conversation saves them hours of reading. Can we schedule a quick call this week? I can work around Dr. [Name]'s schedule."
            </p>
          </div>

          <div class="border-l-4 border-emerald-400 pl-4">
            <div class="font-bold text-gray-900 text-sm">If they say "We're happy with our current supplier":</div>
            <p class="text-sm text-gray-700 mt-1">
              "I hear that a lot. Most of our physicians were happy too until they realized they were waiting 5-7 days for products we deliver in 24 hours. Can I buy lunch and show you the difference? If I can't save you time, the lunch is on me anyway."
            </p>
          </div>
        </div>
      </div>

      <!-- Lunch Strategy -->
      <div class="mb-8">
        <h3 class="text-xl font-bold text-gray-900 mb-4">The Lunch Meeting (In-Person)</h3>
        <div class="grid md:grid-cols-2 gap-6">
          <div class="bg-gray-50 rounded-xl p-6">
            <h4 class="font-bold text-gray-900 mb-3">✓ DO</h4>
            <ul class="space-y-2 text-sm text-gray-700">
              <li>• Bring food for entire office (3-5 people typical)</li>
              <li>• Ask about dietary restrictions beforehand</li>
              <li>• Arrive 5 minutes early to set up</li>
              <li>• Bring product samples they can touch/feel</li>
              <li>• Keep presentation to 10-15 minutes max</li>
              <li>• Ask questions about their pain points first</li>
              <li>• Leave printed Quick Reference sheet</li>
            </ul>
          </div>
          <div class="bg-gray-50 rounded-xl p-6">
            <h4 class="font-bold text-gray-900 mb-3">✗ DON'T</h4>
            <ul class="space-y-2 text-sm text-gray-700">
              <li>• Don't pitch products immediately</li>
              <li>• Don't bring a laptop (use iPad if anything)</li>
              <li>• Don't go over 20 minutes total</li>
              <li>• Don't badmouth competitors by name</li>
              <li>• Don't leave without a next step</li>
              <li>• Don't forget to thank the front desk staff</li>
              <li>• Don't oversell - let them ask questions</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Phone Call Alternative -->
      <div>
        <h3 class="text-xl font-bold text-gray-900 mb-4">The Phone Call Alternative</h3>
        <div class="bg-purple-50 border-l-4 border-purple-500 p-6 rounded-r-lg">
          <p class="text-sm text-purple-900 mb-4 leading-relaxed">
            If you can't get in-person, a phone call with the doctor (or decision-maker) present works. Keep it to 15 minutes.
          </p>
          <div class="bg-white rounded-lg p-4 border border-purple-200">
            <div class="font-bold text-purple-900 mb-2 text-sm">Opening:</div>
            <p class="text-sm text-purple-800">
              "Thanks for making time, Dr. [Name]. I know you're busy, so I'll keep this brief. I work with wound care specialists who are frustrated with slow product delivery and insurance denials. Before I tell you about us, what's your biggest headache with your current wound care supplier?"
            </p>
          </div>
        </div>
      </div>

      <div class="mt-8 bg-emerald-50 rounded-xl p-6">
        <div class="flex items-start gap-3">
          <div class="text-2xl">🎯</div>
          <div>
            <div class="font-bold text-emerald-900 mb-2">Step 1 Success Metric:</div>
            <p class="text-sm text-emerald-800">You've succeeded when the doctor says "Tell me more" or "How does it work?" That's your transition to Step 2.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- STEP 2: DOCTOR CONVERSATION -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <div class="flex items-center gap-4 mb-8">
        <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-2xl flex items-center justify-center flex-shrink-0">
          <span class="text-3xl font-black text-white">2</span>
        </div>
        <div>
          <h2 class="text-3xl font-black text-gray-900">Doctor Conversation</h2>
          <p class="text-gray-600">Focus on THEIR pain points, not our features</p>
        </div>
      </div>

      <!-- Discovery Questions -->
      <div class="mb-8">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Discovery: Find Their Pain</h3>
        <p class="text-gray-700 mb-6">Ask these questions to understand what frustrates them most:</p>

        <div class="space-y-4">
          <div class="bg-teal-50 rounded-xl p-5 border-l-4 border-teal-500">
            <div class="font-bold text-teal-900 mb-2">Question 1: Delivery Speed</div>
            <p class="text-sm text-teal-800 mb-3">"How long does it typically take to get collagen products from your current supplier?"</p>
            <div class="bg-white rounded-lg p-3 border border-teal-200">
              <div class="text-xs font-bold text-teal-900 mb-1">LISTEN FOR:</div>
              <p class="text-xs text-teal-800">"5-7 days," "A week," "Forever," "Too long" → PAIN POINT = Speed</p>
              <p class="text-xs text-teal-700 mt-2"><strong>Your response:</strong> "That's what we hear from everyone. We ship in 24-48 hours. Your patients get products 3-5 days faster, which means faster healing."</p>
            </div>
          </div>

          <div class="bg-teal-50 rounded-xl p-5 border-l-4 border-teal-500">
            <div class="font-bold text-teal-900 mb-2">Question 2: Insurance Headaches</div>
            <p class="text-sm text-teal-800 mb-3">"How often do you deal with insurance denials or pre-authorization issues for wound care products?"</p>
            <div class="bg-white rounded-lg p-3 border border-teal-200">
              <div class="text-xs font-bold text-teal-900 mb-1">LISTEN FOR:</div>
              <p class="text-xs text-teal-800">"All the time," "My staff spends hours on this," "It's a nightmare" → PAIN POINT = Reimbursement</p>
              <p class="text-xs text-teal-700 mt-2"><strong>Your response:</strong> "That's exactly why we exist. We pre-verify every patient and handle all denials. Our approval rate is 98%. Your staff gets those 4 hours back every week."</p>
            </div>
          </div>

          <div class="bg-teal-50 rounded-xl p-5 border-l-4 border-teal-500">
            <div class="font-bold text-teal-900 mb-2">Question 3: Ordering Complexity</div>
            <p class="text-sm text-teal-800 mb-3">"How do you currently order wound care products - phone, fax, online?"</p>
            <div class="bg-white rounded-lg p-3 border border-teal-200">
              <div class="text-xs font-bold text-teal-900 mb-1">LISTEN FOR:</div>
              <p class="text-xs text-teal-800">"We fax a form," "Call the rep," "Fill out paperwork" → PAIN POINT = Complexity</p>
              <p class="text-xs text-teal-700 mt-2"><strong>Your response:</strong> "Our portal takes 90 seconds. Three clicks. No faxing 10-page forms. I can show you right now if you want."</p>
            </div>
          </div>

          <div class="bg-teal-50 rounded-xl p-5 border-l-4 border-teal-500">
            <div class="font-bold text-teal-900 mb-2">Question 4: Patient Outcomes</div>
            <p class="text-sm text-teal-800 mb-3">"What percentage of your chronic wounds are still open after 4 weeks?"</p>
            <div class="bg-white rounded-lg p-3 border border-teal-200">
              <div class="text-xs font-bold text-teal-900 mb-1">LISTEN FOR:</div>
              <p class="text-xs text-teal-800">High percentage, frustration with slow healing → PAIN POINT = Clinical outcomes</p>
              <p class="text-xs text-teal-700 mt-2"><strong>Your response:</strong> "Collagen provides the scaffold for faster granulation tissue. Our physicians see 30% faster healing vs standard gauze. Faster healing = fewer visits = better outcomes."</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Demo (Portal) - Only if Needed -->
      <div class="mb-8">
        <h3 class="text-xl font-bold text-gray-900 mb-4">The Portal Demo (ONLY if They Ask or Need Proof)</h3>
        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-6 rounded-r-lg mb-4">
          <div class="flex items-start gap-3">
            <div class="text-2xl">⚠️</div>
            <div>
              <p class="text-sm text-yellow-900 font-bold mb-2">Don't lead with the demo!</p>
              <p class="text-sm text-yellow-800">Only show the portal if they say "This sounds great, but how does it actually work?" or you've identified ordering complexity as their main pain.</p>
            </div>
          </div>
        </div>

        <div class="bg-gray-50 rounded-xl p-6">
          <h4 class="font-bold text-gray-900 mb-3">2-Minute Portal Demo Script</h4>
          <ol class="space-y-3 text-sm text-gray-700">
            <li class="flex gap-3">
              <span class="font-bold text-brand-teal">1.</span>
              <span><strong>"This is your dashboard."</strong> Point to active patients list. "See Mrs. Johnson's diabetic foot ulcer? Click Create Order."</span>
            </li>
            <li class="flex gap-3">
              <span class="font-bold text-brand-teal">2.</span>
              <span><strong>"System asks basic questions."</strong> Wound type, size, drainage. Takes 30 seconds.</span>
            </li>
            <li class="flex gap-3">
              <span class="font-bold text-brand-teal">3.</span>
              <span><strong>"We verify insurance automatically."</strong> Green checkmark = approved. Patient cost: $12.</span>
            </li>
            <li class="flex gap-3">
              <span class="font-bold text-brand-teal">4.</span>
              <span><strong>"Click Submit."</strong> Done. Products ship today, arrive tomorrow or day after. Patient gets text notification.</span>
            </li>
            <li class="flex gap-3">
              <span class="font-bold text-brand-teal">5.</span>
              <span><strong>"That's it."</strong> 90 seconds vs 15 minutes on the phone or filling out fax forms."</span>
            </li>
          </ol>
        </div>
      </div>

      <!-- Closing Step 2 -->
      <div class="bg-emerald-50 rounded-xl p-6">
        <div class="flex items-start gap-3">
          <div class="text-2xl">🎯</div>
          <div>
            <div class="font-bold text-emerald-900 mb-2">Step 2 Success Metric:</div>
            <p class="text-sm text-emerald-800 mb-3">Doctor says "I'm interested" or "How do we get started?" That's your cue for Step 3.</p>
            <div class="bg-white rounded-lg p-4 border border-emerald-200 mt-3">
              <p class="text-sm text-emerald-900 font-bold mb-1">Transition to Step 3:</p>
              <p class="text-sm text-emerald-800">"Great! Let's get you set up. It takes about 5 minutes. Do you want to do it right now or should I come back tomorrow to help your team?"</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- STEP 3: HELP REGISTER -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <div class="flex items-center gap-4 mb-8">
        <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center flex-shrink-0">
          <span class="text-3xl font-black text-white">3</span>
        </div>
        <div>
          <h2 class="text-3xl font-black text-gray-900">Help Register & Solve Problems</h2>
          <p class="text-gray-600">Be their problem-solver, not just a salesperson</p>
        </div>
      </div>

      <!-- Registration Assistance -->
      <div class="mb-8">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Registration Walkthrough (Do This WITH Them)</h3>
        <div class="bg-purple-50 border-l-4 border-purple-500 p-6 rounded-r-lg mb-6">
          <p class="text-sm text-purple-900 mb-3">
            <strong>Critical:</strong> Don't just send them a link. Sit with them (in person or on Zoom) and complete registration together. This builds trust and prevents drop-off.
          </p>
        </div>

        <div class="space-y-4">
          <div class="bg-gray-50 rounded-lg p-5">
            <div class="font-bold text-gray-900 mb-2">Step 1: Basic Practice Info (2 min)</div>
            <p class="text-sm text-gray-700">Help them enter practice name, address, NPI, tax ID. Have your laptop ready to help if they get stuck.</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-5">
            <div class="font-bold text-gray-900 mb-2">Step 2: Add Team Members (1 min)</div>
            <p class="text-sm text-gray-700">Add office manager and MAs who will actually place orders. Give them admin access.</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-5">
            <div class="font-bold text-gray-900 mb-2">Step 3: First Test Patient (2 min)</div>
            <p class="text-sm text-gray-700">Walk them through adding one real patient with a current wound. Don't place the order yet - just show them how easy it is.</p>
          </div>
        </div>
      </div>

      <!-- Common Problems & Solutions -->
      <div class="mb-8">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Common Problems You'll Solve</h3>

        <div class="space-y-4">
          <div class="border-2 border-gray-200 rounded-xl p-5">
            <div class="flex items-start gap-3">
              <div class="text-2xl">🚨</div>
              <div class="flex-1">
                <div class="font-bold text-gray-900 mb-2">Problem: "Patient's insurance isn't in the system"</div>
                <div class="text-sm text-gray-700 mb-3">Common with smaller Medicare Advantage plans or out-of-state Medicaid.</div>
                <div class="bg-emerald-50 rounded-lg p-3">
                  <div class="text-xs font-bold text-emerald-900 mb-1">YOUR SOLUTION:</div>
                  <p class="text-xs text-emerald-800">"No problem. I'll add that plan to our system today. In the meantime, let me manually verify this patient's coverage for you. Give me the member ID and I'll call their insurance right now."</p>
                </div>
              </div>
            </div>
          </div>

          <div class="border-2 border-gray-200 rounded-xl p-5">
            <div class="flex items-start gap-3">
              <div class="text-2xl">🚨</div>
              <div class="flex-1">
                <div class="font-bold text-gray-900 mb-2">Problem: "We need products TODAY, can't wait 24-48 hours"</div>
                <div class="text-sm text-gray-700 mb-3">Urgent wound, patient leaving town, etc.</div>
                <div class="bg-emerald-50 rounded-lg p-3">
                  <div class="text-xs font-bold text-emerald-900 mb-1">YOUR SOLUTION:</div>
                  <p class="text-xs text-emerald-800">"Let me see what I can do. What's the wound type and size?" [Call warehouse, arrange same-day courier if possible, or bring samples from your car if you have them]. "I have a 4x4 collagen sheet in my car. Let me give it to you now, and I'll rush-ship more for tomorrow."</p>
                </div>
              </div>
            </div>
          </div>

          <div class="border-2 border-gray-200 rounded-xl p-5">
            <div class="flex items-start gap-3">
              <div class="text-2xl">🚨</div>
              <div class="flex-1">
                <div class="font-bold text-gray-900 mb-2">Problem: "Our EHR doesn't integrate"</div>
                <div class="text-sm text-gray-700 mb-3">They want automatic patient data import.</div>
                <div class="bg-emerald-50 rounded-lg p-3">
                  <div class="text-xs font-bold text-emerald-900 mb-1">YOUR SOLUTION:</div>
                  <p class="text-xs text-emerald-800">"We're working on EHR integrations. For now, adding a patient takes 30 seconds - just name, DOB, and insurance. Your MA can do it while the patient is checking in. Most practices find that faster than waiting for IT to set up an integration."</p>
                </div>
              </div>
            </div>
          </div>

          <div class="border-2 border-gray-200 rounded-xl p-5">
            <div class="flex items-start gap-3">
              <div class="text-2xl">🚨</div>
              <div class="flex-1">
                <div class="font-bold text-gray-900 mb-2">Problem: "Documentation for insurance is confusing"</div>
                <div class="text-sm text-gray-700 mb-3">They're worried about audits or denials.</div>
                <div class="bg-emerald-50 rounded-lg p-3">
                  <div class="text-xs font-bold text-emerald-900 mb-1">YOUR SOLUTION:</div>
                  <p class="text-xs text-emerald-800">"We provide you with a documentation template for every order. It auto-fills the wound measurements, product used, and HCPCS codes. Just copy-paste into your note. We also handle all the insurance paperwork - you never talk to the payer."</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-emerald-50 rounded-xl p-6">
        <div class="flex items-start gap-3">
          <div class="text-2xl">🎯</div>
          <div>
            <div class="font-bold text-emerald-900 mb-2">Step 3 Success Metric:</div>
            <p class="text-sm text-emerald-800">They've placed their first order (even if it's a test order) and they say "This was easier than I expected." Now you move to Step 4.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- STEP 4: NURTURE -->
    <div class="bg-white rounded-3xl shadow-xl p-10 mb-10">
      <div class="flex items-center gap-4 mb-8">
        <div class="w-16 h-16 bg-gradient-to-br from-orange-500 to-red-500 rounded-2xl flex items-center justify-center flex-shrink-0">
          <span class="text-3xl font-black text-white">4</span>
        </div>
        <div>
          <h2 class="text-3xl font-black text-gray-900">Nurture & Retain</h2>
          <p class="text-gray-600">Turn first-time users into loyal, high-volume customers</p>
        </div>
      </div>

      <div class="mb-8">
        <h3 class="text-xl font-bold text-gray-900 mb-4">The First 30 Days (Critical Period)</h3>
        <p class="text-gray-700 mb-6">Most physicians who place 1-2 orders in the first month become long-term customers. Your job is to ensure their first experience is flawless.</p>

        <div class="space-y-4">
          <div class="bg-orange-50 rounded-xl p-5 border-l-4 border-orange-500">
            <div class="font-bold text-orange-900 mb-2">Day 1-2: First Order Follow-Up</div>
            <p class="text-sm text-orange-800 mb-3">After their first order ships, call or text to confirm:</p>
            <ul class="text-sm text-orange-700 space-y-1 ml-4">
              <li>• "Did the products arrive on time?"</li>
              <li>• "Was the quality what you expected?"</li>
              <li>• "Any questions about application or documentation?"</li>
            </ul>
          </div>

          <div class="bg-orange-50 rounded-xl p-5 border-l-4 border-orange-500">
            <div class="font-bold text-orange-900 mb-2">Week 1: Check In on Usage</div>
            <p class="text-sm text-orange-800 mb-3">Quick call or email:</p>
            <p class="text-sm text-orange-700">"Hi Dr. [Name], just checking in - have you used the collagen sheets on any patients yet? How's the healing progress? Any issues I can help with?"</p>
          </div>

          <div class="bg-orange-50 rounded-xl p-5 border-l-4 border-orange-500">
            <div class="font-bold text-orange-900 mb-2">Week 2-3: Encourage Second Order</div>
            <p class="text-sm text-orange-800 mb-3">If they haven't ordered again:</p>
            <p class="text-sm text-orange-700">"I noticed you haven't placed a second order yet. Do you need more products? Are there any issues with the portal or the products themselves? I'm here to help."</p>
          </div>

          <div class="bg-orange-50 rounded-xl p-5 border-l-4 border-orange-500">
            <div class="font-bold text-orange-900 mb-2">Week 4: Solidify the Relationship</div>
            <p class="text-sm text-orange-800 mb-3">At the one-month mark:</p>
            <p class="text-sm text-orange-700">"It's been a month since we started working together. How many patients have you used our products on? Are you seeing the faster healing we talked about? What can I do to make this even better for you?"</p>
          </div>
        </div>
      </div>

      <div class="mb-8">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Long-Term Nurture Strategies</h3>

        <div class="grid md:grid-cols-2 gap-6">
          <div class="bg-gray-50 rounded-xl p-6">
            <h4 class="font-bold text-gray-900 mb-3">Monthly Check-Ins</h4>
            <ul class="text-sm text-gray-700 space-y-2">
              <li>• Review order volume (increasing or decreasing?)</li>
              <li>• Ask about new wound care patients</li>
              <li>• Offer new product samples (antimicrobial gel, etc.)</li>
              <li>• Share success stories from other physicians</li>
              <li>• Ask for referrals to other wound care docs</li>
            </ul>
          </div>

          <div class="bg-gray-50 rounded-xl p-6">
            <h4 class="font-bold text-gray-900 mb-3">Quarterly Business Reviews</h4>
            <ul class="text-sm text-gray-700 space-y-2">
              <li>• Show them their usage stats (patients served, healing times)</li>
              <li>• Calculate time saved vs old supplier</li>
              <li>• Discuss any new products or services</li>
              <li>• Get feedback on how we can improve</li>
              <li>• Ask if they'd be willing to be a reference</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="mb-8">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Handling Churn Risk</h3>
        <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded-r-lg">
          <div class="font-bold text-red-900 mb-3">Warning Signs They're About to Leave:</div>
          <ul class="text-sm text-red-800 space-y-2">
            <li>• Order volume drops 50%+ month-over-month</li>
            <li>• They stop responding to your calls/emails</li>
            <li>• Office staff mentions "trying a new supplier"</li>
            <li>• Complaints about product quality or delivery</li>
          </ul>
          <div class="mt-4 bg-white rounded-lg p-4 border border-red-200">
            <div class="font-bold text-red-900 mb-2">Immediate Action:</div>
            <p class="text-sm text-red-800">Call within 24 hours. Don't email - CALL. "Dr. [Name], I noticed your orders dropped off. Is everything okay? Did we do something wrong? How can I fix this?"</p>
          </div>
        </div>
      </div>

      <div class="bg-emerald-50 rounded-xl p-6">
        <div class="flex items-start gap-3">
          <div class="text-2xl">🎯</div>
          <div>
            <div class="font-bold text-emerald-900 mb-2">Step 4 Success Metric:</div>
            <p class="text-sm text-emerald-800">Doctor is ordering 4+ times per month, responds to your check-ins positively, and refers you to other physicians. They're now a loyal customer.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary Card -->
    <div class="bg-gradient-to-br from-brand-navy to-slate-800 text-white rounded-3xl p-10">
      <h2 class="text-3xl font-black mb-6">Remember: We Solve Problems, Not Sell Products</h2>
      <div class="grid md:grid-cols-2 gap-8">
        <div>
          <h3 class="font-bold text-xl mb-4">Physician Pain Points We Solve:</h3>
          <ul class="space-y-2 text-slate-200 text-sm">
            <li>✓ Slow delivery (5-7 days → 24-48 hours)</li>
            <li>✓ Insurance headaches (we handle 100% of it)</li>
            <li>✓ Complex ordering (fax forms → 3 clicks)</li>
            <li>✓ Poor healing outcomes (collagen = 30% faster)</li>
            <li>✓ Staff time waste (save 4 hrs/week)</li>
            <li>✓ Denial management (98% approval rate)</li>
          </ul>
        </div>
        <div>
          <h3 class="font-bold text-xl mb-4">Your Value Proposition:</h3>
          <p class="text-slate-200 text-sm leading-relaxed">
            "I help wound care physicians get products faster, reduce insurance denials to almost zero, and save their staff hours every week on ordering. Most docs see 30% faster healing and tell me this is the easiest supplier they've ever worked with."
          </p>
        </div>
      </div>
    </div>

  </div>

</body>
</html>
