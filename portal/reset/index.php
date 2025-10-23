<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Create New Password — CollagenDirect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    :root { --bg:#f6f8fb; --text:#1e2a38; --muted:#5c6b80; --card:#fff; --card-border:#e7ecf5;
            --input-bg:#fbfcfe; --input-bd:#dde2ec; --accent:#47c6be; --accent-text:#0a1b1a;
            --ok-bg:#e9fbf8; --ok-bd:#b8efe7; --ok-tx:#0b5f56; --err-bg:#fff5f5; --err-bd:#ffc9c9; --err-tx:#8a1f1f; }
    * { box-sizing: border-box; } /* prevents inputs from spilling outside the card */
    body{font-family:Inter,system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--text);margin:0}
    .wrap{max-width:520px;margin:60px auto;background:var(--card);border:1px solid var(--card-border);
          border-radius:16px;padding:22px;box-shadow:0 14px 40px rgba(7,16,40,.08)}
    h1{font-size:1.4rem;margin:0 0 8px 0}
    .muted{color:var(--muted); margin:0 0 10px 0}
    form{margin:0}
    label{display:block;font-weight:600;margin-top:6px}
    input{width:100%;padding:12px 14px;border:1px solid var(--input-bd);border-radius:12px;background:var(--input-bg);margin:12px 0}
    button{width:100%;padding:12px 16px;border-radius:12px;font-weight:700;border:0;cursor:pointer;background:var(--accent);color:var(--accent-text)}
    button[disabled]{opacity:.6;cursor:not-allowed}
    .msg{margin-top:10px;padding:10px 12px;border-radius:12px;display:none}
    .ok{background:var(--ok-bg);border:1px solid var(--ok-bd);color:var(--ok-tx)}
    .err{background:var(--err-bg);border:1px solid var(--err-bd);color:var(--err-tx)}
    a{color:var(--ok-tx);text-decoration:none}
    a:hover{text-decoration:underline}
    .help{font-size:.9rem;color:var(--muted);margin-top:-6px}
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Create a new password</h1>
    <p class="muted">Your reset link is single-use and expires in 15 minutes.</p>

    <form id="reset-form" novalidate>
      <label for="password">New password</label>
      <input id="password" type="password" placeholder="8+ chars, mix of upper/lower/number/symbol" autocomplete="new-password" required />
      <div class="help">Minimum 8 characters recommended.</div>

      <label for="confirm">Confirm new password</label>
      <input id="confirm" type="password" placeholder="Re-enter new password" autocomplete="new-password" required />

      <button id="setpw" type="submit">Update password</button>
    </form>

    <div id="ok" class="msg ok" role="alert">Password updated. <a href="/portal/login">Return to login</a>.</div>
    <div id="err" class="msg err" role="alert"></div>
  </div>

  <script>
    // Read selector & token from the URL – DO NOT lowercase (tokens are case-sensitive)
    const url = new URL(location.href);
    const selector = (url.searchParams.get('selector') || '').trim();
    const token    = (url.searchParams.get('token') || '').trim();

    const form = document.getElementById('reset-form');
    const pw   = document.getElementById('password');
    const cf   = document.getElementById('confirm');
    const btn  = document.getElementById('setpw');
    const ok   = document.getElementById('ok');
    const err  = document.getElementById('err');

    function show(el, txt){ if (txt) el.textContent = txt; el.style.display='block'; }
    function hide(...els){ els.forEach(e=>e.style.display='none'); }
    function strongEnough(p){
      if (p.length < 8) return false;
      return /[a-z]/.test(p) && /[A-Z]/.test(p) && /\d/.test(p) && /[^A-Za-z0-9]/.test(p);
    }

    // If selector/token missing, show a friendly error up-front
    if (!selector || !token) {
      show(err, 'This reset link is missing required parameters. Please request a new reset email.');
    }

    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      hide(ok, err);

      const p = pw.value, c = cf.value;
      if (!p || !c) { show(err, 'Please complete both fields.'); return; }
      if (p !== c)  { show(err, 'Passwords do not match.'); return; }
      if (!strongEnough(p)) { show(err, 'Please choose a stronger password 8+ chars incl. upper, lower, number, symbol).'); return; }

      btn.disabled = true;

      try {
        // If you use the secure API with CSRF:
        // const r = await fetch('/api/csrf.php',{credentials:'include'}); const { csrfToken } = await r.json();

        const res = await fetch('/api/auth/reset_password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' /*, 'x-csrf-token': csrfToken */ },
          credentials: 'include',
          body: JSON.stringify({ selector, token, password: p })
        });

        const j = await res.json().catch(()=>({}));
        if (res.ok && j.ok) {
          show(ok);
          pw.value = ''; cf.value = '';
        } else {
          show(err, j.error || 'Unable to reset password. Your link may be expired or already used.');
        }
      } catch {
        show(err, 'Network error. Try again.');
      } finally {
        btn.disabled = false;
      }
    });
  </script>
</body>
</html>
