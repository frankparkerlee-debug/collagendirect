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
      --bg:#fafafa; --text:#1e2a38; --muted:#6b7280; --line:#e5e7eb;
      --brand:#5FA8A1; --brand-hover:#4d8d87;
      --radius:12px; --shadow:0 1px 3px rgba(0,0,0,.05);
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{
      background: linear-gradient(to bottom right, #f8fafc 0%, #ffffff 50%, rgba(71, 198, 190, 0.03) 100%);
      color:var(--text);
      font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif;
      line-height:1.6;
      -webkit-font-smoothing:antialiased;
      min-height:100vh;
      display:flex;
      flex-direction:column;
    }
    img{max-width:100%;display:block}
    a{text-decoration:none;color:inherit}

    /* Simple header */
    header{
      background:#fff;
      border-bottom:1px solid var(--line);
      padding:1rem 0;
    }
    .nav{
      max-width:1200px;
      margin:0 auto;
      padding:0 1.5rem;
      display:flex;
      align-items:center;
      justify-content:space-between;
    }
    .brand{display:flex;align-items:center;gap:12px}
    .brand img{height:32px;width:auto}
    .brand .title{font-weight:700;font-size:1rem;letter-spacing:.3px}

    /* Centered auth layout matching screenshot */
    main{
      flex:1;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:2rem 1rem;
    }
    .auth-card{
      background:#fff;
      border:1px solid var(--line);
      border-radius:16px;
      padding:3rem 2.5rem;
      box-shadow:0 4px 6px -1px rgba(0,0,0,.05),0 2px 4px -1px rgba(0,0,0,.03);
      width:100%;
      max-width:440px;
    }
    .auth-card h1{
      font-size:1.75rem;
      font-weight:700;
      color:#111;
      text-align:center;
      margin-bottom:0.5rem;
      line-height:1.3;
    }
    .auth-card .subtitle{
      text-align:center;
      color:var(--muted);
      font-size:0.95rem;
      margin-bottom:2rem;
    }
    .muted{color:var(--muted)}

    /* Form styling */
    .form-grid{display:flex;flex-direction:column;gap:1.25rem}
    .field{display:flex;flex-direction:column;gap:0.5rem}
    .field label{
      font-size:0.875rem;
      font-weight:500;
      color:#374151;
    }
    .auth-card input,.auth-card select{
      width:100%;
      padding:0.75rem 1rem;
      border-radius:var(--radius);
      border:1px solid var(--line);
      background:#fff;
      color:#111;
      outline:none;
      font-size:0.95rem;
      transition:border-color 0.2s,box-shadow 0.2s;
    }
    .auth-card input::placeholder{color:#9ca3af}
    .auth-card input:focus,.auth-card select:focus{
      border-color:var(--brand);
      box-shadow:0 0 0 3px rgba(95,168,161,.1);
    }
    .row{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
      font-size:0.875rem;
    }
    .row label{
      display:flex;
      align-items:center;
      gap:0.5rem;
      color:var(--muted);
      cursor:pointer;
    }
    .row a{
      color:var(--brand);
      text-decoration:none;
      font-weight:500;
    }
    .row a:hover{text-decoration:underline}

    /* Buttons matching screenshot style */
    .btn{
      width:100%;
      padding:0.875rem 1.5rem;
      border-radius:var(--radius);
      font-weight:600;
      font-size:1rem;
      cursor:pointer;
      border:none;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      transition:all 0.2s;
    }
    .btn.primary{
      background:var(--brand);
      color:#fff;
    }
    .btn.primary:hover{
      background:var(--brand-hover);
    }
    .btn.secondary{
      background:#fff;
      color:#374151;
      border:1px solid var(--line);
    }
    .btn.secondary:hover{
      background:#f9fafb;
    }

    /* Messages */
    .error{
      display:none;
      margin:0 0 1rem;
      padding:0.75rem 1rem;
      border-radius:var(--radius);
      border:1px solid #fecaca;
      background:#fef2f2;
      color:#991b1b;
      font-size:0.875rem;
    }

    /* Password toggle */
    .toggle{
      position:absolute;
      right:12px;
      top:50%;
      transform:translateY(-50%);
      font-size:0.875rem;
      color:var(--muted);
      cursor:pointer;
      background:none;
      border:none;
      padding:0.25rem 0.5rem;
    }
    .toggle:hover{color:var(--text)}

    /* Divider */
    .divider{
      display:flex;
      align-items:center;
      text-align:center;
      margin:1.5rem 0;
      color:var(--muted);
      font-size:0.875rem;
    }
    .divider::before,.divider::after{
      content:'';
      flex:1;
      border-bottom:1px solid var(--line);
    }
    .divider span{padding:0 1rem}

    /* Footer */
    footer{
      background:#fff;
      border-top:1px solid var(--line);
      padding:1rem;
      text-align:center;
      color:var(--muted);
      font-size:0.875rem;
    }

    /* Responsive */
    @media (max-width:640px){
      .auth-card{padding:2rem 1.5rem}
      .auth-card h1{font-size:1.5rem}
    }
  </style>
</head>
<body>
  <!-- Simple header -->
  <header>
    <div class="nav">
      <a href="/index.html" class="brand" aria-label="Go to homepage">
        <img src="/assets/collagendirect.png" alt="CollagenDirect logo">
        <span class="title">COLLAGEN <span style="color:#5FA8A1">DIRECT</span></span>
      </a>
    </div>
  </header>

  <!-- Centered auth card -->
  <main>
    <section class="auth-card">
      <div id="portalBadge" style="display:none;background:#5FA8A1;color:#fff;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;padding:0.5rem 1rem;border-radius:8px;text-align:center;margin-bottom:1rem">Admin Portal</div>
      <h1 id="pageTitle">Welcome back</h1>
      <p class="subtitle" id="pageSubtitle">New here? <a href="/register" style="color:var(--brand);font-weight:500">Create an account</a></p>

      <p id="err" class="error" role="alert">Invalid email or password. Please try again.</p>

      <form id="loginForm" novalidate action="javascript:void(0)">
        <div class="form-grid">
          <div class="field">
            <label for="email">Work Email</label>
            <input id="email" type="email" required placeholder="name@practice.com" autocomplete="username" />
          </div>
          <div class="field" style="position:relative">
            <label for="password">Password</label>
            <input id="password" type="password" required minlength="8" placeholder="••••••••" autocomplete="current-password" />
            <button type="button" class="toggle" aria-label="Show password" id="togglePw">Show</button>
          </div>
          <div class="row">
            <label>
              <input id="remember" type="checkbox" style="width:16px;height:16px" />
              <span>Remember me on this device</span>
            </label>
            <a href="/portal/forgot">Forgot password?</a>
          </div>
          <button class="btn primary" type="submit">Sign in</button>
        </div>
      </form>

      <!-- HIPAA Security & Trust Messaging -->
      <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--line)">
        <div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;margin-bottom:1rem">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#10b981">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
          </svg>
          <span style="font-weight:600;font-size:0.875rem;color:#10b981">HIPAA Compliant & Secure</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;font-size:0.75rem;color:var(--muted);text-align:center">
          <div style="display:flex;flex-direction:column;align-items:center;gap:0.25rem">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="color:#6b7280">
              <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"></path>
            </svg>
            <span>256-bit SSL Encryption</span>
          </div>
          <div style="display:flex;flex-direction:column;align-items:center;gap:0.25rem">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="color:#6b7280">
              <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM9 8V6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9z"></path>
            </svg>
            <span>Secure PHI Storage</span>
          </div>
          <div style="display:flex;flex-direction:column;align-items:center;gap:0.25rem">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="color:#6b7280">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path>
            </svg>
            <span>Audit Logging</span>
          </div>
          <div style="display:flex;flex-direction:column;align-items:center;gap:0.25rem">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="color:#6b7280">
              <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"></path>
            </svg>
            <span>BAA Compliant</span>
          </div>
        </div>
        <p style="margin-top:1rem;text-align:center;font-size:0.75rem;color:var(--muted);line-height:1.4">
          Your patient data is protected with enterprise-grade security and full HIPAA compliance. All access is logged and monitored.
        </p>
      </div>

      <p class="muted" style="margin-top:1.5rem;text-align:center;font-size:0.875rem">
        For assistance, contact <a href="mailto:support@collagendirect.health" style="color:var(--brand);font-weight:500">support@collagendirect.health</a>
      </p>
    </section>
  </main>

  <!-- Simple footer -->
  <footer>
    © <span id="year"></span> CollagenDirect. All rights reserved.
  </footer>

  <!-- === YOUR ORIGINAL JS (unchanged) === -->
  <script>
  (function () {
    // Check if accessing admin portal
    const params = new URLSearchParams(location.search);
    const nextUrl = params.get('next') || '';
    const isAdminPortal = nextUrl.includes('/admin') || document.referrer.includes('/admin');

    if (isAdminPortal) {
      // Show admin badge
      const badge = document.getElementById('portalBadge');
      if (badge) badge.style.display = 'block';

      // Update title and subtitle for admin
      const title = document.getElementById('pageTitle');
      const subtitle = document.getElementById('pageSubtitle');
      if (title) title.textContent = 'Admin Portal Login';
      if (subtitle) subtitle.innerHTML = 'Sign in with your admin credentials';
    }

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