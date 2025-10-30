<?php
session_start();

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check if email ends with @collagendirect.health
    if (preg_match('/@collagendirect\.health$/i', $email)) {

        // TEMPORARY: Simple password check for demo
        // In production, you should validate against your actual user database
        // For now, accept any password if email domain is correct

        // TODO: Replace this with actual authentication against your user database
        // Example: Check against portal users table, verify hashed password, etc.

        if (!empty($password)) {
            // Set session
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = explode('@', $email)[0]; // Extract name from email
            $_SESSION['login_time'] = time();

            // Redirect to training hub
            header('Location: index.php');
            exit;
        } else {
            $error = 'Please enter your password.';
        }
    } else {
        $error = 'Access denied. You must have a @collagendirect.health email address.';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    $success = 'You have been logged out successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Sales Training Portal</title>
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
  <style>
    body { font-feature-settings: 'cv11', 'ss01'; -webkit-font-smoothing: antialiased; }
  </style>
</head>
<body class="bg-gradient-to-br from-brand-navy via-slate-900 to-brand-navy min-h-screen flex items-center justify-center">

  <div class="w-full max-w-md px-6">

    <!-- Logo/Header -->
    <div class="text-center mb-8">
      <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-12 w-auto mx-auto mb-4">
      <h1 class="text-3xl font-black text-white mb-2">Sales Training Portal</h1>
      <p class="text-slate-400">Internal Use Only</p>
    </div>

    <!-- Login Card -->
    <div class="bg-white rounded-3xl shadow-2xl p-8">

      <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
          <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            <p class="text-sm text-red-800"><?php echo htmlspecialchars($error); ?></p>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
          <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <p class="text-sm text-emerald-800"><?php echo htmlspecialchars($success); ?></p>
          </div>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php">

        <!-- Email Field -->
        <div class="mb-4">
          <label for="email" class="block text-sm font-semibold text-gray-900 mb-2">
            Email Address
          </label>
          <input
            type="email"
            id="email"
            name="email"
            required
            placeholder="yourname@collagendirect.health"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-brand-teal focus:outline-none transition"
            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
          >
          <p class="text-xs text-gray-500 mt-1">Must be a @collagendirect.health email</p>
        </div>

        <!-- Password Field -->
        <div class="mb-6">
          <label for="password" class="block text-sm font-semibold text-gray-900 mb-2">
            Password
          </label>
          <input
            type="password"
            id="password"
            name="password"
            required
            placeholder="Enter your password"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-brand-teal focus:outline-none transition"
          >
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          class="w-full bg-gradient-to-r from-brand-teal to-emerald-500 text-white font-bold py-3 rounded-xl hover:shadow-lg hover:scale-105 transition-all"
        >
          Sign In
        </button>

      </form>

      <!-- Divider -->
      <div class="my-6 border-t border-gray-200"></div>

      <!-- Help Text -->
      <div class="text-center">
        <p class="text-sm text-gray-600 mb-2">Need access to the portal?</p>
        <a href="mailto:sales-support@collagendirect.health" class="text-sm text-brand-teal hover:text-brand-navy font-semibold transition">
          Contact Sales Support
        </a>
      </div>

    </div>

    <!-- Footer -->
    <div class="text-center mt-8 text-slate-400 text-sm">
      <p>&copy; 2025 CollagenDirect. Confidential & Proprietary.</p>
    </div>

  </div>

  <!-- Development Helper (REMOVE IN PRODUCTION) -->
  <?php if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false): ?>
    <div class="fixed bottom-4 right-4 bg-yellow-100 border-2 border-yellow-500 rounded-lg p-4 text-xs max-w-xs">
      <div class="font-bold text-yellow-900 mb-2">⚠️ Development Mode</div>
      <p class="text-yellow-800 mb-2">For testing, use any @collagendirect.health email with any password.</p>
      <p class="text-yellow-700 text-xs">Remove this in production!</p>
    </div>
  <?php endif; ?>

</body>
</html>
