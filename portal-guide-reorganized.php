<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Physician Portal Guide - CollagenDirect</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              teal: '#47c6be',
              blue: '#2a78ff',
              navy: '#0a2540',
              slate: '#64748b'
            }
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      font-feature-settings: 'cv11', 'ss01';
      -webkit-font-smoothing: antialiased;
    }
    .gradient-bg {
      background: linear-gradient(135deg, #47c6be 0%, #10b981 100%);
    }
    .section {
      scroll-margin-top: 100px;
    }
    .feature-card {
      transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
      border: 1px solid rgba(71,198,190,0.1);
    }
    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 30px rgba(71,198,190,0.15);
      border-color: rgba(71,198,190,0.3);
    }
    .step-number {
      background: linear-gradient(135deg, #47c6be 0%, #10b981 100%);
      background-clip: text;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    kbd {
      background: #edf2f7;
      border: 1px solid #cbd5e0;
      border-radius: 4px;
      padding: 2px 6px;
      font-family: monospace;
      font-size: 0.875em;
    }
    .glow-teal {
      box-shadow: 0 0 30px rgba(71,198,190,0.3);
    }
    nav a:hover {
      color: #47c6be;
    }
    #backToTop {
      position: fixed;
      bottom: 30px;
      right: 30px;
      z-index: 50;
      opacity: 0;
      transform: translateY(100px);
      transition: opacity 0.3s, transform 0.3s;
    }
    #backToTop.show {
      opacity: 1;
      transform: translateY(0);
    }
  </style>
</head>
<body class="bg-gray-50">
