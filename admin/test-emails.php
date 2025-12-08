<?php
/**
 * Email Preview & Test Tool
 * View all email templates and send test emails
 */
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_admin();
require_once __DIR__ . '/../api/lib/email_notifications.php';

$admin = current_admin();
$msg = '';
$msgType = 'success';

// Handle test email sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    verify_csrf();
    $testEmail = trim($_POST['test_email'] ?? '');
    $emailType = $_POST['email_type'] ?? '';

    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Invalid email address';
        $msgType = 'error';
    } else {
        $result = false;
        switch ($emailType) {
            case 'password_reset':
                $result = send_password_reset_email($testEmail, 'Test User', 'https://collagendirect.health/portal/reset?token=test123');
                break;
            case 'account_created':
                $result = send_physician_account_created_email($testEmail, 'Dr. Test User', 'TempPass123!');
                break;
            case 'account_confirm':
                $result = send_account_confirmation_email($testEmail, 'Dr. Test User', 'Test Practice');
                break;
            case 'order_received':
                $result = send_order_received_email([
                    'patient_email' => $testEmail,
                    'patient_name' => 'John Patient',
                    'order_id' => 'ORD-TEST-001',
                    'order_date' => date('m/d/Y'),
                    'physician_name' => 'Dr. Smith',
                    'practice_name' => 'Test Practice',
                    'product_name' => 'CollagenDirect Wound Care',
                    'quantity' => '2'
                ]);
                break;
            case 'order_approved':
                $result = send_order_approved_email([
                    'physician_email' => $testEmail,
                    'physician_name' => 'Dr. Test',
                    'patient_name' => 'John Patient',
                    'order_id' => 'ORD-TEST-001',
                    'product_name' => 'CollagenDirect Wound Care',
                    'quantity' => '2'
                ]);
                break;
            case 'order_shipped':
                $result = send_order_shipped_email([
                    'patient_email' => $testEmail,
                    'patient_name' => 'John Patient',
                    'order_id' => 'ORD-TEST-001',
                    'carrier' => 'UPS',
                    'tracking_number' => '1Z999AA10123456784',
                    'product_name' => 'CollagenDirect Wound Care',
                    'quantity' => '2'
                ]);
                break;
        }

        if ($result) {
            $msg = "Test email sent successfully to $testEmail";
            $msgType = 'success';
        } else {
            $msg = "Failed to send test email. Check server logs for details.";
            $msgType = 'error';
        }
    }
}

// Email preview data
$previews = [
    'password_reset' => [
        'name' => 'Password Reset',
        'description' => 'Sent when user clicks "Forgot Password"',
        'audience' => 'All users'
    ],
    'account_created' => [
        'name' => 'Account Created (Admin)',
        'description' => 'Sent when admin creates a new provider account',
        'audience' => 'Physicians & Practice Managers'
    ],
    'account_confirm' => [
        'name' => 'Account Confirmation (Self-Register)',
        'description' => 'Sent when user registers their own account',
        'audience' => 'Physicians & Practice Managers'
    ],
    'order_received' => [
        'name' => 'Order Received',
        'description' => 'Confirmation when patient submits an order',
        'audience' => 'Patients'
    ],
    'order_approved' => [
        'name' => 'Order Approved',
        'description' => 'Notification when admin approves an order',
        'audience' => 'Physicians'
    ],
    'order_shipped' => [
        'name' => 'Order Shipped',
        'description' => 'Notification with tracking info when order ships',
        'audience' => 'Patients'
    ]
];

// Generate preview HTML for each email type
function get_preview_html(string $type): string {
    switch ($type) {
        case 'password_reset':
            $bodyContent = <<<HTML
      <h2 style="color: #1e293b; margin: 0 0 20px 0;">Password Reset Request</h2>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        Hello <strong>Test User</strong>,
      </p>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        We received a request to reset your password for your CollagenDirect account.
      </p>

      <div style="text-align: center; margin: 30px 0;">
        <a href="#" style="display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;">
          Reset Password
        </a>
      </div>

      <p style="color: #64748b; font-size: 14px; margin: 20px 0;">
        This link will expire in <strong>15 minutes</strong> for security reasons.
      </p>

      <div style="background-color: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 15px; margin: 20px 0;">
        <p style="margin: 0; color: #92400e; font-size: 14px;">
          <strong>Didn't request this?</strong><br>
          If you didn't request a password reset, you can safely ignore this email.
        </p>
      </div>
HTML;
            break;

        case 'account_created':
            $bodyContent = <<<HTML
      <h2 style="color: #1e293b; margin: 0 0 20px 0;">Welcome to CollagenDirect!</h2>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        Hello <strong>Dr. Test User</strong>,
      </p>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        Your CollagenDirect account has been created. You can now access the portal to manage orders and patients.
      </p>

      <div style="background-color: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 20px; margin: 25px 0;">
        <p style="margin: 0 0 10px 0; font-weight: 600; color: #0f766e;">Your Login Credentials:</p>
        <p style="margin: 0 0 5px 0; color: #475569;"><strong>Email:</strong> doctor@example.com</p>
        <p style="margin: 0; color: #475569;"><strong>Temporary Password:</strong> TempPass123!</p>
      </div>

      <p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
        For security, please change your password after your first login.
      </p>

      <div style="text-align: center; margin: 30px 0;">
        <a href="#" style="display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;">
          Login to Portal
        </a>
      </div>
HTML;
            break;

        case 'account_confirm':
            $bodyContent = <<<HTML
      <h2 style="color: #1e293b; margin: 0 0 20px 0;">Welcome to CollagenDirect!</h2>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        Hello <strong>Dr. Test User</strong>,
      </p>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        Thank you for registering with CollagenDirect. Your account for <strong>Test Practice</strong> has been created successfully.
      </p>

      <div style="text-align: center; margin: 30px 0;">
        <a href="#" style="display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;">
          Access Your Portal
        </a>
      </div>

      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        From your portal you can:
      </p>
      <ul style="color: #475569; line-height: 1.8;">
        <li>Submit patient orders</li>
        <li>Track order status</li>
        <li>Manage your practice information</li>
      </ul>
HTML;
            break;

        case 'order_received':
            $bodyContent = <<<HTML
      <h2 style="color: #1e293b; margin: 0 0 20px 0;">Order Received</h2>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        Hello <strong>John Patient</strong>,
      </p>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        We've received your order and it's being processed.
      </p>

      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 25px 0;">
        <p style="margin: 0 0 15px 0; font-weight: 600; color: #1e293b;">Order Details:</p>
        <table style="width: 100%; font-size: 14px; color: #475569;">
          <tr><td style="padding: 5px 0;"><strong>Order ID:</strong></td><td>ORD-TEST-001</td></tr>
          <tr><td style="padding: 5px 0;"><strong>Date:</strong></td><td>12/07/2024</td></tr>
          <tr><td style="padding: 5px 0;"><strong>Physician:</strong></td><td>Dr. Smith</td></tr>
          <tr><td style="padding: 5px 0;"><strong>Practice:</strong></td><td>Test Practice</td></tr>
        </table>
        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 15px 0;">
        <p style="margin: 0; color: #475569;"><strong>Product:</strong> CollagenDirect Wound Care (Qty: 2)</p>
      </div>

      <p style="color: #475569; line-height: 1.6;">
        You'll receive another email with tracking information once your order ships.
      </p>
HTML;
            break;

        case 'order_approved':
            $bodyContent = <<<HTML
      <h2 style="color: #1e293b; margin: 0 0 20px 0;">Order Approved</h2>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        Hello <strong>Dr. Test</strong>,
      </p>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        Good news! An order for your patient has been approved and is being processed.
      </p>

      <div style="background-color: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 20px; margin: 25px 0;">
        <p style="margin: 0 0 10px 0; font-weight: 600; color: #166534;">
          <span style="font-size: 18px;">&#10003;</span> Order Approved
        </p>
        <table style="width: 100%; font-size: 14px; color: #475569;">
          <tr><td style="padding: 5px 0;"><strong>Order ID:</strong></td><td>ORD-TEST-001</td></tr>
          <tr><td style="padding: 5px 0;"><strong>Patient:</strong></td><td>John Patient</td></tr>
          <tr><td style="padding: 5px 0;"><strong>Product:</strong></td><td>CollagenDirect Wound Care (Qty: 2)</td></tr>
        </table>
      </div>

      <div style="text-align: center; margin: 30px 0;">
        <a href="#" style="display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;">
          View in Portal
        </a>
      </div>
HTML;
            break;

        case 'order_shipped':
            $bodyContent = <<<HTML
      <h2 style="color: #1e293b; margin: 0 0 20px 0;">Your Order Has Shipped!</h2>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        Hello <strong>John Patient</strong>,
      </p>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        Great news! Your order is on its way.
      </p>

      <div style="background-color: #eff6ff; border: 1px solid #93c5fd; border-radius: 8px; padding: 20px; margin: 25px 0;">
        <p style="margin: 0 0 15px 0; font-weight: 600; color: #1e40af;">Shipping Details:</p>
        <table style="width: 100%; font-size: 14px; color: #475569;">
          <tr><td style="padding: 5px 0;"><strong>Carrier:</strong></td><td>UPS</td></tr>
          <tr><td style="padding: 5px 0;"><strong>Tracking #:</strong></td><td>1Z999AA10123456784</td></tr>
        </table>
      </div>

      <div style="text-align: center; margin: 30px 0;">
        <a href="https://wwwapps.ups.com/WebTracking/track?tracknum=1Z999AA10123456784" style="display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;">
          Track Your Package
        </a>
      </div>

      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 25px 0;">
        <p style="margin: 0 0 10px 0; font-weight: 600; color: #1e293b;">Order Summary:</p>
        <p style="margin: 0; color: #475569;"><strong>Order ID:</strong> ORD-TEST-001</p>
        <p style="margin: 5px 0 0 0; color: #475569;"><strong>Product:</strong> CollagenDirect Wound Care (Qty: 2)</p>
      </div>
HTML;
            break;

        default:
            $bodyContent = '<p>No preview available</p>';
    }

    return email_template('Email Preview', $bodyContent);
}
?>
<?php include __DIR__ . '/_header.php'; ?>

<div class="mb-4">
    <a href="/admin/" class="text-brand">&larr; Back to Dashboard</a>
</div>

<h1 class="text-2xl font-semibold mb-6">Email Templates & Testing</h1>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded <?= $msgType === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-teal-50 border border-teal-200 text-teal-700' ?>">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Test Email Form -->
<div class="bg-white border rounded-lg p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">Send Test Email</h2>
    <form method="POST" class="flex flex-wrap gap-4 items-end">
        <?= csrf_field() ?>
        <div>
            <label class="block text-sm text-slate-600 mb-1">Email Address</label>
            <input type="email" name="test_email" value="<?= htmlspecialchars($admin['email'] ?? '') ?>"
                   class="border rounded px-3 py-2 w-64" required>
        </div>
        <div>
            <label class="block text-sm text-slate-600 mb-1">Email Type</label>
            <select name="email_type" class="border rounded px-3 py-2" required>
                <?php foreach ($previews as $key => $preview): ?>
                <option value="<?= $key ?>"><?= htmlspecialchars($preview['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" name="send_test" value="1" class="bg-brand text-white rounded px-4 py-2">
            Send Test Email
        </button>
    </form>
</div>

<!-- Email Previews -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <?php foreach ($previews as $key => $preview): ?>
    <div class="bg-white border rounded-lg overflow-hidden">
        <div class="p-4 bg-slate-50 border-b">
            <h3 class="font-semibold"><?= htmlspecialchars($preview['name']) ?></h3>
            <p class="text-sm text-slate-600 mt-1"><?= htmlspecialchars($preview['description']) ?></p>
            <p class="text-xs text-slate-500 mt-1">Audience: <?= htmlspecialchars($preview['audience']) ?></p>
        </div>
        <div class="p-4">
            <div class="border rounded overflow-hidden" style="max-height: 400px; overflow-y: auto;">
                <iframe srcdoc="<?= htmlspecialchars(get_preview_html($key)) ?>"
                        style="width: 100%; height: 400px; border: none;"></iframe>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Configuration Status -->
<div class="bg-white border rounded-lg p-6 mt-6">
    <h2 class="text-lg font-semibold mb-4">Email Configuration Status</h2>
    <table class="w-full text-sm">
        <tr class="border-b">
            <td class="py-2 font-medium">SMTP Host</td>
            <td class="py-2"><?= env('SMTP_HOST') ? '<span class="text-green-600">&#10003; Configured</span>' : '<span class="text-slate-400">Not set</span>' ?></td>
        </tr>
        <tr class="border-b">
            <td class="py-2 font-medium">SMTP Port</td>
            <td class="py-2"><?= env('SMTP_PORT') ?: '<span class="text-slate-400">Not set</span>' ?></td>
        </tr>
        <tr class="border-b">
            <td class="py-2 font-medium">SMTP User</td>
            <td class="py-2"><?= env('SMTP_USER') ? '<span class="text-green-600">&#10003; Configured</span>' : '<span class="text-slate-400">Not set</span>' ?></td>
        </tr>
        <tr class="border-b">
            <td class="py-2 font-medium">SendGrid API Key (Fallback)</td>
            <td class="py-2"><?= env('SENDGRID_API_KEY') ? '<span class="text-green-600">&#10003; Configured</span>' : '<span class="text-slate-400">Not set</span>' ?></td>
        </tr>
        <tr>
            <td class="py-2 font-medium">From Address</td>
            <td class="py-2"><?= env('SMTP_FROM') ?: 'no-reply@collagendirect.health' ?></td>
        </tr>
    </table>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
