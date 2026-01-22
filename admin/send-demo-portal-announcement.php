<?php
/**
 * Send Demo Portal Announcement to Sales Reps
 * One-time announcement email about the new demo portal
 */
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../api/lib/email_sender.php';

// Only superadmin can send bulk emails
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: /admin/');
    exit;
}

$message = '';
$messageType = '';
$sentCount = 0;
$failedCount = 0;
$recipients = [];

// Get all active sales reps (users with role 'distributor' or 'rep')
try {
    $stmt = $pdo->query("
        SELECT id, email, name
        FROM users
        WHERE role IN ('distributor', 'rep', 'sales_rep')
        AND email IS NOT NULL
        AND email != ''
        ORDER BY name
    ");
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $message = 'Error fetching recipients: ' . $e->getMessage();
    $messageType = 'error';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
    $selectedIds = $_POST['recipient_ids'] ?? [];

    if (empty($selectedIds)) {
        $message = 'Please select at least one recipient.';
        $messageType = 'error';
    } else {
        // Get selected recipients
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE id IN ($placeholders)");
        $stmt->execute($selectedIds);
        $selectedRecipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($selectedRecipients as $rep) {
            $result = send_demo_portal_announcement($rep['email'], $rep['name']);
            if ($result) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        if ($sentCount > 0 && $failedCount === 0) {
            $message = "Successfully sent announcement to {$sentCount} recipient(s).";
            $messageType = 'success';
        } elseif ($sentCount > 0 && $failedCount > 0) {
            $message = "Sent to {$sentCount} recipient(s), but {$failedCount} failed.";
            $messageType = 'warning';
        } else {
            $message = "Failed to send to all {$failedCount} recipient(s).";
            $messageType = 'error';
        }
    }
}

/**
 * Send the demo portal announcement email
 */
function send_demo_portal_announcement(string $email, string $name): bool {
    $baseUrl = env('APP_URL', 'https://collagendirect.health');
    $demoUrl = $baseUrl . '/demo-portal/login.html';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">New Demo Portal Now Available</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Hi {$name},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    We're excited to announce the launch of the <strong>CollagenDirect Demo Portal</strong> — a new tool to help you showcase our physician portal to prospective practices.
</p>

<div style="background-color: #f8fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <p style="color: #1e293b; font-size: 15px; font-weight: 600; margin: 0 0 12px 0; color: #f59e0b;">
        What's Included
    </p>
    <ul style="color: #475569; font-size: 14px; line-height: 1.8; margin: 0; padding-left: 20px;">
        <li><strong>Guided Tour</strong> — Walk prospects through key features step-by-step</li>
        <li><strong>Interactive Sandbox</strong> — Create test patients and place sample orders</li>
        <li><strong>Referral Order Demo</strong> — Show ICD-10 lookup, wound sizing, document uploads</li>
        <li><strong>Wholesale/DME Demo</strong> — Demonstrate inventory ordering for DME-licensed practices</li>
    </ul>
</div>

<div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px 20px; margin: 20px 0;">
    <p style="color: #92400e; font-size: 14px; margin: 0;">
        <strong>HIPAA Note:</strong> The demo portal uses only synthetic data. All demo data is automatically deleted within 24 hours.
    </p>
</div>

<p style="color: #1e293b; font-size: 15px; font-weight: 600; margin: 20px 0 10px 0;">
    How to Access
</p>

<ol style="color: #475569; font-size: 14px; line-height: 1.8; margin: 0 0 20px 0; padding-left: 20px;">
    <li>Go to the demo portal login page</li>
    <li>Log in with your CollagenDirect credentials</li>
    <li>The guided tour will start automatically — or skip to explore freely</li>
</ol>

<div style="text-align: center; margin: 30px 0;">
    <a href="{$demoUrl}" style="display: inline-block; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
        Access Demo Portal
    </a>
</div>

<p style="color: #1e293b; font-size: 15px; font-weight: 600; margin: 20px 0 10px 0;">
    Tips for Demos
</p>

<ul style="color: #475569; font-size: 14px; line-height: 1.8; margin: 0; padding-left: 20px;">
    <li>Use the "Start Tour" button to walk through features with the prospect</li>
    <li>Click "Reset Demo" at any time to start fresh with new sample data</li>
    <li>Let prospects interact — they can create test patients and place sample orders</li>
    <li>Highlight the two ordering models: Referral (we bill) vs Wholesale (they bill as DME)</li>
</ul>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 25px 0 0 0;">
    Questions or feedback? Reply to this email or reach out to the product team.
</p>
HTML;

    $html = email_template('New Demo Portal Available', $body);
    return send_email($email, $name, 'New: Demo Portal for Prospect Presentations', $html);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Send Demo Portal Announcement | Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --muted: #64748b;
            --brand: #0d9488;
            --demo-accent: #f59e0b;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            padding: 2rem;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: var(--muted);
            margin-bottom: 2rem;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        .message.success { background: #d1fae5; color: #065f46; }
        .message.error { background: #fee2e2; color: #991b1b; }
        .message.warning { background: #fef3c7; color: #92400e; }

        .recipient-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        .recipient-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
        }
        .recipient-item:last-child { border-bottom: none; }
        .recipient-item:hover { background: #f8fafc; }
        .recipient-item input[type="checkbox"] {
            width: 1.125rem;
            height: 1.125rem;
            accent-color: var(--brand);
        }
        .recipient-name { font-weight: 500; }
        .recipient-email { color: var(--muted); font-size: 0.875rem; }

        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }
        .select-all {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--muted);
            font-size: 0.875rem;
            cursor: pointer;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9375rem;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--demo-accent), #d97706);
            color: white;
        }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary {
            background: var(--bg);
            color: var(--text);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover { background: #f1f5f9; }

        .preview-section {
            margin-top: 2rem;
        }
        .preview-section h3 {
            font-size: 1rem;
            margin-bottom: 1rem;
            color: var(--muted);
        }
        .email-preview {
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }
        .email-preview iframe {
            width: 100%;
            height: 500px;
            border: none;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--muted);
            text-decoration: none;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }
        .back-link:hover { color: var(--text); }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/admin/" class="back-link">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Admin
        </a>

        <h1>Send Demo Portal Announcement</h1>
        <p class="subtitle">Notify sales reps about the new demo portal for prospect presentations.</p>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <h2 style="font-size: 1rem; margin-bottom: 1rem;">Select Recipients</h2>

                <?php if (empty($recipients)): ?>
                    <div class="empty-state">
                        <p>No sales reps found in the system.</p>
                    </div>
                <?php else: ?>
                    <div class="recipient-list">
                        <?php foreach ($recipients as $r): ?>
                            <label class="recipient-item">
                                <input type="checkbox" name="recipient_ids[]" value="<?= htmlspecialchars($r['id']) ?>" checked>
                                <div>
                                    <div class="recipient-name"><?= htmlspecialchars($r['name'] ?: 'Unknown') ?></div>
                                    <div class="recipient-email"><?= htmlspecialchars($r['email']) ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="actions">
                        <label class="select-all">
                            <input type="checkbox" id="selectAll" checked onchange="toggleAll(this)">
                            Select All (<?= count($recipients) ?>)
                        </label>
                        <button type="submit" name="send" class="btn btn-primary">
                            Send Announcement
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="preview-section">
            <h3>Email Preview</h3>
            <div class="email-preview">
                <iframe src="/demo-portal/assets/rep-notification-email.html"></iframe>
            </div>
        </div>
    </div>

    <script>
        function toggleAll(checkbox) {
            document.querySelectorAll('input[name="recipient_ids[]"]').forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }
    </script>
</body>
</html>
