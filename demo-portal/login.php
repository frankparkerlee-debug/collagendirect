<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Demo Portal — Login | CollagenDirect</title>
  <meta name="description" content="Distributor demo portal login for CollagenDirect." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root{
      --bg:#fafafa; --text:#1e2a38; --muted:#6b7280; --line:#e5e7eb;
      --brand:#5FA8A1; --brand-hover:#4d8d87;
      --demo-accent:#f59e0b; --demo-accent-hover:#d97706;
      --radius:12px; --shadow:0 1px 3px rgba(0,0,0,.05);
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{
      background: linear-gradient(to bottom right, #fffbeb 0%, #ffffff 50%, rgba(245, 158, 11, 0.05) 100%);
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
    .demo-badge{
      background:linear-gradient(135deg, #f59e0b, #d97706);
      color:#fff;
      font-size:0.75rem;
      font-weight:600;
      text-transform:uppercase;
      letter-spacing:0.5px;
      padding:0.5rem 1rem;
      border-radius:8px;
      text-align:center;
      margin-bottom:1rem;
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

    .form-grid{display:flex;flex-direction:column;gap:1.25rem}
    .field{display:flex;flex-direction:column;gap:0.5rem}
    .field label{
      font-size:0.875rem;
      font-weight:500;
      color:#374151;
    }
    .auth-card input{
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
    .auth-card input:focus{
      border-color:var(--demo-accent);
      box-shadow:0 0 0 3px rgba(245,158,11,.15);
    }

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
      background:linear-gradient(135deg, #f59e0b, #d97706);
      color:#fff;
    }
    .btn.primary:hover{
      background:linear-gradient(135deg, #d97706, #b45309);
    }
    .btn.primary:disabled{
      opacity:0.6;
      cursor:not-allowed;
    }

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

    .info-box{
      background:#fef3c7;
      border:1px solid #fcd34d;
      border-radius:var(--radius);
      padding:1rem;
      margin-top:1.5rem;
    }
    .info-box h3{
      font-size:0.875rem;
      font-weight:600;
      color:#92400e;
      margin-bottom:0.5rem;
      display:flex;
      align-items:center;
      gap:0.5rem;
    }
    .info-box p{
      font-size:0.8rem;
      color:#78350f;
      line-height:1.5;
    }

    footer{
      background:#fff;
      border-top:1px solid var(--line);
      padding:1rem;
      text-align:center;
      color:var(--muted);
      font-size:0.875rem;
    }

    @media (max-width:640px){
      .auth-card{padding:2rem 1.5rem}
      .auth-card h1{font-size:1.5rem}
    }
  </style>
</head>
<body>
  <header>
    <div class="nav">
      <a href="/index.html" class="brand" aria-label="Go to homepage">
        <img src="/assets/collagendirect.png" alt="CollagenDirect logo">
        <span class="title">COLLAGEN <span style="color:#5FA8A1">DIRECT</span></span>
      </a>
    </div>
  </header>

  <main>
    <section class="auth-card">
      <div class="demo-badge">Demo Portal</div>
      <h1>Distributor Demo Access</h1>
      <p class="subtitle">Experience the CollagenDirect physician portal</p>

      <p id="err" class="error" role="alert">Invalid credentials. Please try again.</p>

      <form id="loginForm" novalidate action="javascript:void(0)">
        <div class="form-grid">
          <div class="field">
            <label for="email">Email Address</label>
            <input id="email" type="email" required placeholder="your@email.com" autocomplete="username" />
          </div>
          <div class="field" style="position:relative">
            <label for="password">Password</label>
            <input id="password" type="password" required placeholder="••••••••" autocomplete="current-password" />
            <button type="button" class="toggle" aria-label="Show password" id="togglePw">Show</button>
          </div>
          <button class="btn primary" type="submit" id="submitBtn">
            <span id="btnText">Start Demo</span>
            <span id="btnLoading" style="display:none">
              <svg style="animation:spin 1s linear infinite;margin-right:8px" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
              </svg>
              Loading...
            </span>
          </button>
        </div>
      </form>

      <div class="info-box">
        <h3>
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Demo Environment
        </h3>
        <p>
          This is a sandboxed demo using synthetic data. All information entered is temporary and automatically deleted within 24 hours for HIPAA compliance. No real patient data is used.
        </p>
      </div>

      <p class="muted" style="margin-top:1.5rem;text-align:center;font-size:0.875rem">
        Need demo access? Contact <a href="mailto:sales@collagendirect.health" style="color:var(--demo-accent);font-weight:500">sales@collagendirect.health</a>
      </p>
    </section>
  </main>

  <footer>
    &copy; <span id="year"></span> CollagenDirect. Demo Portal.
  </footer>

  <style>
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
  </style>

  <script>
  (function () {
    // Show/Hide password
    const toggle = document.getElementById('togglePw');
    const pw = document.getElementById('password');
    if (toggle && pw) {
      toggle.addEventListener('click', () => {
        const showing = pw.type === 'text';
        pw.type = showing ? 'password' : 'text';
        toggle.textContent = showing ? 'Show' : 'Hide';
      });
    }

    // Form submit
    const form = document.getElementById('loginForm');
    const err = document.getElementById('err');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnLoading = document.getElementById('btnLoading');

    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      err.style.display = 'none';

      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;

      if (!email || !password) {
        err.textContent = 'Please enter both email and password.';
        err.style.display = 'block';
        return;
      }

      // Show loading state
      submitBtn.disabled = true;
      btnText.style.display = 'none';
      btnLoading.style.display = 'inline-flex';

      try {
        const res = await fetch('/api/demo/login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ email, password })
        });

        let data = {};
        try { data = await res.json(); } catch {}

        if (res.ok && data.ok) {
          // Redirect to demo portal
          location.href = data.redirect || '/demo-portal/';
        } else {
          err.textContent = data.error || data.message || 'Login failed. Please try again.';
          err.style.display = 'block';
        }
      } catch (ex) {
        console.error(ex);
        err.textContent = 'Network error. Please try again.';
        err.style.display = 'block';
      } finally {
        // Reset button state
        submitBtn.disabled = false;
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
      }
    });

    // Year
    document.getElementById('year').textContent = new Date().getFullYear();
  })();
  </script>
</body>
</html>
