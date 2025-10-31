<?php
/**
 * Lead Finder UI
 * User interface to run lead generation across target states
 */

session_start();
require_once(__DIR__ . '/config.php');

// Check authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit;
}

$target_states = ['TX', 'OK', 'AZ', 'LA', 'AL', 'FL', 'TN', 'GA'];
$target_specialties = [
    '207RI0011X' => 'Wound Care',
    '213E00000X' => 'Podiatry',
    '207N00000X' => 'Dermatology',
    '208600000X' => 'Surgery - Vascular',
    '208G00000X' => 'Surgery - General',
    '207R00000X' => 'Internal Medicine'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Finder - Sales Agent</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f7fa;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #7f8c8d;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .checkbox-item input[type="checkbox"] {
            margin-right: 8px;
            width: 18px;
            height: 18px;
        }

        .info-box {
            background: #e8f8f7;
            border-left: 4px solid #47c6be;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .info-box strong {
            color: #47c6be;
        }

        button {
            background: #47c6be;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
        }

        button:hover {
            background: #34a89e;
        }

        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        #output {
            margin-top: 30px;
            padding: 20px;
            background: #1e1e1e;
            color: #00ff00;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            max-height: 400px;
            overflow-y: auto;
            display: none;
        }

        #output.active {
            display: block;
        }

        .progress {
            width: 100%;
            height: 30px;
            background: #ecf0f1;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
            display: none;
        }

        .progress.active {
            display: block;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #47c6be 0%, #34a89e 100%);
            width: 0%;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #47c6be;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

        <h1>🔍 Lead Finder</h1>
        <p class="subtitle">Search NPI Registry for wound care physicians across target states</p>

        <div class="info-box">
            <strong>How it works:</strong><br>
            This tool searches the public NPI (National Provider Identifier) Registry to find physicians in your selected states and specialties.
            It automatically enriches leads with contact information and estimates monthly volume.
        </div>

        <form id="leadFinderForm">
            <div class="form-group">
                <label>Select States</label>
                <div class="checkbox-grid">
                    <?php foreach ($target_states as $state): ?>
                    <div class="checkbox-item">
                        <input type="checkbox" name="states[]" value="<?= $state ?>" id="state_<?= $state ?>" checked>
                        <label for="state_<?= $state ?>"><?= $state ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Select Specialties</label>
                <div class="checkbox-grid">
                    <?php foreach ($target_specialties as $code => $name): ?>
                    <div class="checkbox-item">
                        <input type="checkbox" name="specialties[]" value="<?= $name ?>" id="spec_<?= $code ?>" checked>
                        <label for="spec_<?= $code ?>"><?= $name ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" id="startButton">Start Lead Generation</button>
        </form>

        <div class="progress" id="progress">
            <div class="progress-bar" id="progressBar">0%</div>
        </div>

        <div id="output"></div>
    </div>

    <script>
        const form = document.getElementById('leadFinderForm');
        const output = document.getElementById('output');
        const progress = document.getElementById('progress');
        const progressBar = document.getElementById('progressBar');
        const startButton = document.getElementById('startButton');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(form);
            const states = formData.getAll('states[]');
            const specialties = formData.getAll('specialties[]');

            if (states.length === 0 || specialties.length === 0) {
                alert('Please select at least one state and one specialty');
                return;
            }

            startButton.disabled = true;
            startButton.textContent = 'Running...';
            output.classList.add('active');
            progress.classList.add('active');
            output.innerHTML = 'Starting lead generation...\n\n';

            const total = states.length * specialties.length;
            let current = 0;

            for (const state of states) {
                for (const specialty of specialties) {
                    output.innerHTML += `\n=== Searching: ${state} - ${specialty} ===\n`;

                    try {
                        const response = await fetch('lead-finder-api.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                state: state,
                                specialty: specialty
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            output.innerHTML += `✓ Found ${result.found} providers\n`;
                            output.innerHTML += `✓ Saved ${result.saved} new leads\n`;
                            output.innerHTML += `⊘ Skipped ${result.found - result.saved} duplicates\n`;
                        } else {
                            output.innerHTML += `✗ Error: ${result.error}\n`;
                        }
                    } catch (error) {
                        output.innerHTML += `✗ Exception: ${error.message}\n`;
                    }

                    current++;
                    const percentage = Math.round((current / total) * 100);
                    progressBar.style.width = percentage + '%';
                    progressBar.textContent = percentage + '%';

                    output.scrollTop = output.scrollHeight;

                    // Rate limiting: 1 second between requests
                    await new Promise(resolve => setTimeout(resolve, 1000));
                }
            }

            output.innerHTML += `\n========================================\n`;
            output.innerHTML += `Lead generation complete!\n`;
            output.innerHTML += `========================================\n`;

            startButton.disabled = false;
            startButton.textContent = 'Start Lead Generation';
        });
    </script>
</body>
</html>
