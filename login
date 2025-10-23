<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Physician Portal — Login | CollagenDirect</title>
  <meta name="description" content="Secure login for CollagenDirect physician portal." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root{
      --bg:#ffffff; --panel:#f6f8fb; --text:#1e2a38; --muted:#5c6b80; --line:rgba(0,0,0,.08);
      --brand-teal:#47c6be; --teal-50:#e9fbf8; --teal-100:#d2f6f1; --teal-700:#0b5f56;
      --radius:16px; --shadow:0 14px 40px rgba(7,16,40,.10); --max:1180px;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:var(--bg);color:var(--text);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif;line-height:1.5;-webkit-font-smoothing:antialiased}
    img{max-width:100%;display:block}
    a{text-decoration:none;color:inherit}
    .container{max-width:var(--max);margin:0 auto;padding:0 20px}

    /* Header (matches Contact page) */
    header{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.92);backdrop-filter:saturate(160%) blur(8px);border-bottom:1px solid var(--line)}
    .nav{display:flex;align-items:center;justify-content:space-between;height:74px}
    .brand{display:flex;align-items:center;gap:14px}
    .brand img{height:34px;width:auto}
    .brand .title{font-weight:800;font-size:1.05rem;letter-spacing:.4px}
    .brand .subtitle{font-size:.78rem;color:var(--muted)}
    .nav ul{display:flex;gap:18px;list-style:none}
    .link{padding:10px 12px;border-radius:10px;font-weight:600;color:#3c4657}
    .link:hover{background:var(--teal-50)}
    .cta{display:flex;gap:10px;align-items:center}
    .btn{padding:12px 16px;border-radius:12px;font-weight:700;cursor:pointer;border:1px solid transparent;display:inline-flex;align-items:center;justify-content:center;transition:filter .2s ease, background-color .2s ease, border-color .2s ease}
    .btn.primary{background:var(--brand-teal);color:#0a1b1a}
    .btn.primary:hover{filter:brightness(.96)}
    .btn.ghost{background:#fff;border:1px solid var(--brand-teal);color:#1e2a38}
    .btn.ghost:hover{background:var(--teal-50)}

    /* Hero strip */
    .hero{background:linear-gradient(180deg,#fdfefe,#f4f7fb);border-bottom:1px solid var(--line)}
    .hero-wrap{display:flex;align-items:center;min-height:200px;padding:24px 0}
    .hero h1{font-size:2.1rem;font-weight:900;color:#1a2430}
    .lead{color:var(--muted);font-size:1.02rem;margin-top:6px;max-width:62ch}

    /* Auth layout */
    .auth{display:grid;grid-template-columns:1.05fr .95fr;gap:24px;align-items:stretch;padding:48px 0}
    .auth-card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:24px;box-shadow:var(--shadow)}
    .auth-card h2{font-size:1.5rem;margin-bottom:8px}
    .muted{color:var(--muted)}
    .form-grid{display:grid;grid-template-columns:1fr;gap:14px;margin-top:12px}
    /* Style existing inputs without touching names/ids */
    .auth-card input,.auth-card select,.auth-card textarea{
      width:100%;padding:12px 14px;border-radius:12px;border:1px solid #dde2ec;background:#fbfcfe;color:#111;outline:none;font-size:1rem
    }
    .auth-card input:focus,.auth-card select:focus,.auth-card textarea:focus{
      border-color:var(--brand-teal);box-shadow:0 0 0 3px rgba(71,198,190,.18)
    }
    .row{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    .row a{color:var(--teal-700);text-decoration:underline}

    /* Messages (compatible with your existing classes) */
    .error{display:none;margin:0 0 4px;padding:.7rem .8rem;border-radius:12px;border:1px solid #ffc9c9;background:#fff5f5;color:#8a1f1f}
    .success{background:#e9fbf8;border:1px solid #b8efe7;color:#0b5f56;padding:10px 12px;border-radius:12px;font-size:.95rem}

    /* Password toggle button from your form */
    .toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:.9rem;color:#5c6b80;cursor:pointer;background:none;border:none}

    /* Right-hand reassurance panel */
    .panel{background:linear-gradient(135deg,#e9fbf8,#f6f8fb);border:1px solid var(--line);border-radius:20px;box-shadow:var(--shadow);padding:22px}
    .panel h3{margin:0 0 8px;color:#0b5f56}
    .stat{display:flex;gap:10px;align-items:center;margin-top:10px}
    .dot{width:9px;height:9px;border-radius:999px;background:var(--brand-teal)}

    /* Footer */
    footer{margin-top:0;background:linear-gradient(180deg,#f7fbfb,#f6f8fb);border-top:1px solid var(--line)}
    .footer-grid{display:grid;grid-template-columns:1.2fr .6fr .6fr .6fr;gap:24px;padding:36px 0}
    .foot-brand{display:flex;gap:14px}
    .foot-brand img{height:42px}
    .foot-small{color:#6e7a93;font-size:.92rem}

    /* Responsive */
    @media (max-width:980px){.auth{grid-template-columns:1fr}.panel{order:-1;margin-bottom:16px}}
    @media (max-width:860px){.nav ul{display:none}}
    @media (max-width:600px){.hero h1{font-size:1.8rem}}
  </style>
</head>
<body>
  <!-- Header (identical to Contact page) -->
  <header>
    <div class="container nav">
      <a href="/index.html" class="brand" aria-label="Go to homepage">
        <img src="/assets/collagendirect.png" alt="CollagenDirect logo">
        <div>
          <div class="title">COLLAGEN <span style="color:var(--brand-teal)">DIRECT</span></div>
          <div class="subtitle">Evidence-Backed Wound Therapies</div>
        </div>
      </a>
      <ul>
        <li><a class="link" href="/products">Products</a></li>
        <li><a class="link" href="/index.html#value">About</a></li>
        <li><a class="link" href="/index.html#resources">Resources</a></li>
        <li><a class="link" href="/contact">Contact</a></li>
      </ul>
      <div class="cta">
        <a class="btn ghost" href="/register">Register</a>
        <a class="btn primary" href="/portal">Provider Login</a>
      </div>
    </div>
  </header>

  <!-- Hero -->
  <section class="hero">
    <div class="container hero-wrap">
      <div>
        <h1>Physician Portal Login</h1>
        <p class="lead">HIPAA-secure access to submit, start, or stop patient orders and track shipments in real time.</p>
      </div>
    </div>
  </section>

  <!-- Auth -->
  <main class="container auth">
    <!-- Login Card with YOUR exact form -->
    <section class="auth-card">
      <h2>Welcome back</h2>
      <p class="muted">New here? <a href="/register">Create an account</a></p>
      <p id="err" class="error" role="alert">Invalid email or password. Please try again.</p>

      <!-- === YOUR ORIGINAL FORM & IDs (unchanged) === -->
      <form id="loginForm" novalidate action="javascript:void(0)">
        <div class="form-grid">
          <div class="field" style="position:relative">
            <label for="email">Work Email</label>
            <input id="email" type="email" required placeholder="name@practice.com" autocomplete="username" />
          </div>
          <div class="field" style="position:relative">
            <label for="password">Password</label>
            <input id="password" type="password" required minlength="8" placeholder="••••••••" autocomplete="current-password" />
            <button type="button" class="toggle" aria-label="Show password" id="togglePw">Show</button>
          </div>
          <div class="row">
            <label class="muted" style="display:flex;align-items:center;gap:8px">
              <input id="remember" type="checkbox" style="width:16px;height:16px" />
              <span>Remember me on this device</span>
            </label>
            <a class="muted" href="/portal/forgot">Forgot password?</a>
          </div>
          <button class="btn primary" type="submit" style="width:100%">Sign in</button>
        </div>
      </form>
      <p class="muted" style="margin-top:12px">For assistance, contact <a href="mailto:clinical@collagendirect.com">clinical@collagendirect.com</a>.</p>
      <!-- === /YOUR ORIGINAL FORM === -->
    </section>

    <!-- Right panel (marketing / reassurance) -->
    <aside class="panel" aria-label="Why CollagenDirect">
      <h3>Clinical-grade access</h3>
      <p class="muted">Secure tools for physician teams to manage orders with speed and accuracy.</p>
      <div class="stat"><span class="dot"></span><span>Role-based access & audit trail</span></div>
      <div class="stat"><span class="dot"></span><span>COAs, IFUs, coding docs in one place</span></div>
      <div class="stat"><span class="dot"></span><span>Direct-to-manufacturer supply</span></div>
      <div class="stat"><span class="dot"></span><span>U.S. support • 1-business-day response</span></div>
      <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn ghost" href="/register">Register a Physician Account</a>
        <a class="btn" style="border:1px solid var(--line)" href="/contact">Contact Support</a>
      </div>
    </aside>
  </main>

  <!-- Footer (matches Contact page) -->
  <footer>
    <div class="container footer-grid">
      <div class="foot-brand">
        <img src="/assets/collagendirect.png" alt="CollagenDirect">
        <div>
          <div style="font-weight:900;letter-spacing:.4px">COLLAGEN <span style="color:var(--brand-teal)">DIRECT</span></div>
          <p class="foot-small">Direct-to-manufacturer collagen therapies for modern wound care.</p>
        </div>
      </div>
      <div>
        <h4>Company</h4>
        <a href="/index.html#">About</a><br>
        <a href="/contact">Contact</a>
      </div>
      <div>
        <h4>Products</h4>
        <a href="/products">Catalog</a><br>
        <a href="/index.html#resources">Resources</a>
      </div>
      <div>
        <h4>Legal</h4>
        <a href="/terms">Terms</a><br>
        <a href="/privacy">Privacy</a>
      </div>
    </div>
    <div style="border-top:1px solid var(--line);padding:18px 0;text-align:center;color:#6e7a93;font-size:.9rem">
      © <span id="year"></span> CollagenDirect. All rights reserved.
    </div>
  </footer>

  <!-- === YOUR ORIGINAL JS (unchanged) === -->
  <script>
  (function () {
    // Show/Hide password
    const toggle = document.getElementById('togglePw');
    const pw = document.getElementById('password');
    if (toggle && pw) {
      toggle.addEventListener('click', (e) => {
        const showing = pw.type === 'text';
        pw.type = showing ? 'password' : 'text';
        toggle.textContent = showing ? 'Show' : 'Hide';
        toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      });
    }

    // CSRF helper
    async function csrf() {
      const r = await fetch('/api/csrf.php', { credentials: 'include' });
      const j = await r.json();
      return j.csrfToken;
    }

    // Form submit -> POST /api/login.php
    const form = document.getElementById('loginForm');
    const err = document.getElementById('err');

    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      err.style.display = 'none';

      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;
      const remember = document.getElementById('remember')?.checked || false;

      if (!email || !password) {
        err.textContent = 'Please complete all required fields.';
        err.style.display = 'block';
        return;
      }

      try {
        const token = await csrf();
        const res = await fetch('/api/login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'x-csrf-token': token },
          credentials: 'include',
          body: JSON.stringify({ email, password, remember })
        });

        let data = {};
        try { data = await res.json(); } catch {}

        if (res.ok && (data.ok || data.success)) {
          const params = new URLSearchParams(location.search);
          location.href = params.get('next') || '/portal/';
        } else {
          err.textContent = data.error || `Login failed (status ${res.status}).`;
          err.style.display = 'block';
        }
      } catch (ex) {
        console.error(ex);
        err.textContent = 'Network error. Please try again.';
        err.style.display = 'block';
      }
    });
  })();
  </script>
  <!-- === /YOUR ORIGINAL JS === -->

  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
</body>
</html>