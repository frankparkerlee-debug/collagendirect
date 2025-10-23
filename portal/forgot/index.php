<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Forgot Password — CollagenDirect</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    :root { --bg:#f6f8fb; --text:#1e2a38; --muted:#5c6b80; --card:#fff; --card-border:#e7ecf5;
            --input-bg:#fbfcfe; --input-border:#dde2ec; --accent:#47c6be; --accent-text:#0a1b1a;
            --ok-bg:#e9fbf8; --ok-bd:#b8efe7; --ok-tx:#0b5f56; --err-bg:#fff5f5; --err-bd:#ffc9c9; --err-tx:#8a1f1f; }
    * { box-sizing: border-box; }
    body{font-family:Inter,system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--text);margin:0}
    .wrap{max-width:520px;margin:60px auto;background:var(--card);border:1px solid var(--card-border);
          border-radius:16px;padding:22px;box-shadow:0 14px 40px rgba(7,16,40,.08)}
    h1{font-size:1.4rem;margin:0 0 8px 0}
    .muted{color:var(--muted); margin:0 0 10px 0}
    form{margin:0}
    label{display:block;font-weight:600;margin-top:6px}
    input[type="email"]{width:100%;padding:12px 14px;border:1px solid var(--input-border);border-radius:12px;background:var(--input-bg);margin:12px 0}
    button{width:100%;padding:12px 16px;border-radius:12px;font-weight:700;border:0;cursor:pointer;background:var(--accent);color:var(--accent-text)}
    button[disabled]{opacity:.6;cursor:not-allowed}
    .msg{margin-top:10px;padding:10px 12px;border-radius:12px;display:none}
    .ok{background:var(--ok-bg);border:1px solid var(--ok-bd);color:var(--ok-tx)}
    .err{background:var(--err-bg);border:1px solid var(--err-bd);color:var(--err-tx)}
    a{color:var(--ok-tx);text-decoration:none}
    a:hover{text-decoration:underline}
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Reset your password</h1>
    <p class="muted">Enter the work email you used to register. We’ll email a reset link.</p>

    <form id="forgot-form" novalidate>
      <label for="email">Work email</label>
      <input id="email" name="email" type="email" placeholder="name@practice.com" autocomplete="email" required />
      <button id="send" type="submit">Send reset link</button>
    </form>

    <div id="ok" class="msg ok" role="alert">If the email exists, we sent a reset link. Please check your inbox (and spam).</div>
    <div id="err" class="msg err" role="alert"></div>

    <p style="margin-top:10px"><a href="/portal/login">Back to login</a></p>
  </div>

  <script>
    const form = document.getElementById('forgot-form');
    const emailEl = document.getElementById('email');
    const sendBtn = document.getElementById('send');
    const ok = document.getElementById('ok');
    const err = document.getElementById('err');

    function show(el, txt){ if (txt) el.textContent = txt; el.style.display='block'; }
    function hide(...els){ els.forEach(e=>e.style.display='none'); }

    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      hide(ok, err);

      const email = emailEl.value.trim();
      if (!email) { show(err, 'Please enter your email.'); emailEl.focus(); return; }
      if (!emailEl.checkValidity()) { show(err, 'Please enter a valid email address.'); emailEl.focus(); return; }

      sendBtn.disabled = true;

      try {
        // SECURE sender (creates real selector/token)
        const res = await fetch('/api/auth/request_reset.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email })
        });
        await res.json(); // always generic
        show(ok);
        emailEl.value = '';
      } catch {
        show(err, 'Network error. Try again.');
      } finally {
        sendBtn.disabled = false;
      }
    });
  </script>
</body>
</html>
