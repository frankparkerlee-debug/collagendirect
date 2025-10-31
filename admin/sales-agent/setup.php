<?php
/**
 * Sales Agent Setup Page
 * One-time setup to create database tables
 */
session_start();

// Basic authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    die('Unauthorized. Please log in to the admin panel first.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Agent Setup</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .status-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 14px;
            max-height: 400px;
            overflow-y: auto;
        }
        button {
            background: #47c6be;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            font-weight: 600;
        }
        button:hover {
            background: #34a89e;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .info {
            color: #17a2b8;
            margin-bottom: 20px;
            padding: 15px;
            background: #d1ecf1;
            border-radius: 4px;
            border-left: 4px solid #17a2b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sales Outreach Agent Setup</h1>

        <div class="info">
            <strong>ℹ️ First Time Setup</strong><br>
            This will create all necessary database tables for the Sales Outreach Agent system.
            Only run this once. If tables already exist, they will be skipped.
        </div>

        <button id="runMigration" onclick="runMigration()">Run Database Migration</button>

        <div id="output" class="status-box" style="display:none;">
            <div id="outputContent"></div>
        </div>

        <div style="margin-top: 30px;">
            <a href="index.php" style="color: #47c6be;">← Back to Sales Agent Dashboard</a>
        </div>
    </div>

    <script>
        async function runMigration() {
            const button = document.getElementById('runMigration');
            const output = document.getElementById('output');
            const outputContent = document.getElementById('outputContent');

            button.disabled = true;
            button.textContent = 'Running migration...';
            output.style.display = 'block';
            outputContent.innerHTML = 'Starting migration...<br><br>';

            try {
                const response = await fetch('run-migration.php');
                const text = await response.text();

                outputContent.innerHTML = text
                    .replace(/\n/g, '<br>')
                    .replace(/✓/g, '<span class="success">✓</span>')
                    .replace(/✗/g, '<span class="error">✗</span>')
                    .replace(/⚠/g, '<span style="color: #ffc107;">⚠</span>');

                button.textContent = 'Migration Complete';
                button.style.background = '#28a745';
            } catch (error) {
                outputContent.innerHTML += `<span class="error">Error: ${error.message}</span>`;
                button.textContent = 'Migration Failed';
                button.style.background = '#dc3545';
            }
        }
    </script>
</body>
</html>
