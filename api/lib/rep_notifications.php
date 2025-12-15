<?php
/**
 * Sales Rep Email Notifications
 *
 * Email templates and sending functions for all sales rep-related notifications.
 */
declare(strict_types=1);

require_once __DIR__ . '/email_sender.php';
require_once __DIR__ . '/env.php';

/**
 * Get admin notification recipients (superadmin and manufacturer roles)
 */
function get_admin_notification_emails(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT email, name FROM admin_users
        WHERE role IN ('superadmin', 'manufacturer')
        AND email IS NOT NULL
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Send notification to rep: Application Submitted
 */
function send_rep_application_submitted(PDO $pdo, string $repEmail, string $repName): bool {
    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">Application Received</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Dear {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Thank you for applying to become a CollagenDirect Sales Representative. We have received your application and all accompanying documents.
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    Our team will review your application within 2-3 business days. You will receive an email notification once a decision has been made.
</p>

<div style="background-color: #f0fdfa; border-left: 4px solid #14b8a6; padding: 15px 20px; margin: 20px 0;">
    <p style="color: #0d9488; font-size: 14px; margin: 0; font-weight: 500;">
        <strong>What's Next?</strong><br>
        We'll notify you by email once your application has been reviewed. If you have any questions in the meantime, please don't hesitate to reach out.
    </p>
</div>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 20px 0 0 0;">
    Thank you for your interest in joining CollagenDirect!
</p>
HTML;

    $html = email_template('Application Received - CollagenDirect', $body);
    return send_email($repEmail, $repName, 'Your CollagenDirect Sales Rep Application', $html);
}

/**
 * Send notification to rep: Application Approved
 */
function send_rep_application_approved(PDO $pdo, string $repEmail, string $repName): bool {
    $loginUrl = env('APP_URL', 'https://collagendirect.health') . '/admin/login.php';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">Congratulations! You're Approved!</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Dear {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    We're excited to welcome you to the CollagenDirect Sales Team! Your application has been reviewed and approved.
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    Your account is now active and you can log in to access your Sales Rep Portal.
</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="{$loginUrl}" style="display: inline-block; background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
        Log In to Your Portal
    </a>
</div>

<div style="background-color: #f0fdfa; border-left: 4px solid #14b8a6; padding: 15px 20px; margin: 20px 0;">
    <p style="color: #0d9488; font-size: 14px; margin: 0; font-weight: 500;">
        <strong>Getting Started:</strong><br>
        • Onboard your first clinic from the Dashboard<br>
        • Add physicians to your assigned clinics<br>
        • Track your commissions in real-time<br>
        • Access your signed documents anytime
    </p>
</div>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 20px 0 0 0;">
    We look forward to a successful partnership!
</p>
HTML;

    $html = email_template('Welcome to CollagenDirect!', $body);
    return send_email($repEmail, $repName, 'Welcome to CollagenDirect - Your Account is Active!', $html);
}

/**
 * Send notification to rep: Application Rejected
 */
function send_rep_application_rejected(PDO $pdo, string $repEmail, string $repName, ?string $reason = null): bool {
    $reasonText = $reason
        ? "<p style=\"color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;\"><strong>Reason:</strong> {$reason}</p>"
        : '';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">Application Update</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Dear {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Thank you for your interest in becoming a CollagenDirect Sales Representative. After careful review, we're unable to approve your application at this time.
</p>

{$reasonText}

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    If you believe this decision was made in error or if your circumstances have changed, please feel free to reach out to us.
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 20px 0 0 0;">
    We appreciate your understanding and wish you the best in your future endeavors.
</p>
HTML;

    $html = email_template('Application Update - CollagenDirect', $body);
    return send_email($repEmail, $repName, 'Your CollagenDirect Sales Rep Application', $html);
}

/**
 * Send notification to rep: Assignment Request Approved
 */
function send_rep_assignment_approved(PDO $pdo, string $repEmail, string $repName, string $clinicName): bool {
    $portalUrl = env('APP_URL', 'https://collagendirect.health') . '/admin/rep/clinics.php';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">Assignment Request Approved</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Dear {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Great news! Your request to be assigned to <strong>{$clinicName}</strong> has been approved.
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    This clinic is now part of your assigned roster. Any orders and payments from this clinic will contribute to your commission.
</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="{$portalUrl}" style="display: inline-block; background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
        View My Clinics
    </a>
</div>
HTML;

    $html = email_template('Assignment Approved', $body);
    return send_email($repEmail, $repName, "{$clinicName} has been assigned to you", $html);
}

/**
 * Send notification to rep: Assignment Request Denied
 */
function send_rep_assignment_denied(PDO $pdo, string $repEmail, string $repName, string $clinicName, ?string $reason = null): bool {
    $reasonText = $reason
        ? "<p style=\"color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;\"><strong>Reason:</strong> {$reason}</p>"
        : '';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">Assignment Request Update</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Dear {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Your request to be assigned to <strong>{$clinicName}</strong> was not approved.
</p>

{$reasonText}

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    If you have any questions about this decision, please feel free to reach out.
</p>
HTML;

    $html = email_template('Assignment Request Update', $body);
    return send_email($repEmail, $repName, "Assignment Request Update for {$clinicName}", $html);
}

/**
 * Send notification to rep: Clinic Assigned by Admin
 */
function send_rep_clinic_assigned(PDO $pdo, string $repEmail, string $repName, string $clinicName, string $adminName): bool {
    $portalUrl = env('APP_URL', 'https://collagendirect.health') . '/admin/rep/clinics.php';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">New Clinic Assigned</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Dear {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    <strong>{$clinicName}</strong> has been assigned to your account by {$adminName}.
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    This clinic is now part of your assigned roster. Any orders and payments from this clinic will contribute to your commission.
</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="{$portalUrl}" style="display: inline-block; background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
        View My Clinics
    </a>
</div>
HTML;

    $html = email_template('New Clinic Assigned', $body);
    return send_email($repEmail, $repName, "{$clinicName} has been assigned to you", $html);
}

/**
 * Send notification to rep: Clinic Removed/Reassigned
 */
function send_rep_clinic_removed(PDO $pdo, string $repEmail, string $repName, string $clinicName): bool {
    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">Clinic Reassignment Notice</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Dear {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    <strong>{$clinicName}</strong> has been reassigned and is no longer in your assigned roster.
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    Any existing commission entries for this clinic will remain on your ledger, but future orders will not be attributed to you.
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 0 0;">
    If you have questions about this change, please contact your administrator.
</p>
HTML;

    $html = email_template('Clinic Reassignment', $body);
    return send_email($repEmail, $repName, "{$clinicName} has been reassigned", $html);
}

/**
 * Send notification to rep: Commission Payout Processed
 */
function send_rep_payout_processed(PDO $pdo, string $repEmail, string $repName, float $amount, string $method, ?string $reference = null): bool {
    $referenceText = $reference
        ? "<tr><td style=\"padding: 10px; border-bottom: 1px solid #e2e8f0; color: #64748b;\">Reference #:</td><td style=\"padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: 600;\">{$reference}</td></tr>"
        : '';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">Commission Payout Processed</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Dear {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    A commission payout has been processed for your account.
</p>

<table style="width: 100%; border-collapse: collapse; margin: 20px 0; background-color: #f8fafc; border-radius: 8px; overflow: hidden;">
    <tr>
        <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; color: #64748b;">Amount:</td>
        <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #059669; font-size: 18px;">$HTML . number_format($amount, 2) . <<<HTML</td>
    </tr>
    <tr>
        <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; color: #64748b;">Payment Method:</td>
        <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: 600;">{$method}</td>
    </tr>
    {$referenceText}
    <tr>
        <td style="padding: 10px; color: #64748b;">Date:</td>
        <td style="padding: 10px; font-weight: 600;">HTML . date('F j, Y') . <<<HTML</td>
    </tr>
</table>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 20px 0 0 0;">
    You can view your complete payout history in your portal.
</p>
HTML;

    $html = email_template('Payout Processed', $body);
    return send_email($repEmail, $repName, 'Commission Payout of $' . number_format($amount, 2) . ' Processed', $html);
}

/**
 * Send notification to admin: New Rep Application
 */
function send_admin_new_rep_application(PDO $pdo, string $repName, string $repEmail, ?string $company = null): bool {
    $reviewUrl = env('APP_URL', 'https://collagendirect.health') . '/admin/platform/distributors.php?tab=pending';
    $companyText = $company ? "<br>Company: {$company}" : '';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">New Sales Rep Application</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    A new sales rep application has been submitted and requires your review.
</p>

<div style="background-color: #f8fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <p style="color: #1e293b; font-size: 16px; margin: 0; font-weight: 600;">
        {$repName}
    </p>
    <p style="color: #64748b; font-size: 14px; margin: 5px 0 0 0;">
        {$repEmail}{$companyText}
    </p>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="{$reviewUrl}" style="display: inline-block; background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
        Review Application
    </a>
</div>
HTML;

    $html = email_template('New Sales Rep Application', $body);

    // Send to all admins
    $admins = get_admin_notification_emails($pdo);
    $success = true;
    foreach ($admins as $admin) {
        if (!send_email($admin['email'], $admin['name'], "New Sales Rep Application: {$repName}", $html)) {
            $success = false;
        }
    }
    return $success;
}

/**
 * Send notification to admin: New Assignment Request
 */
function send_admin_new_assignment_request(PDO $pdo, string $repName, string $clinicName): bool {
    $reviewUrl = env('APP_URL', 'https://collagendirect.health') . '/admin/platform/distributors.php?tab=requests';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">New Assignment Request</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    A sales rep has requested to be assigned to a clinic.
</p>

<div style="background-color: #f8fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <p style="color: #64748b; font-size: 14px; margin: 0;">Rep:</p>
    <p style="color: #1e293b; font-size: 16px; margin: 5px 0 15px 0; font-weight: 600;">
        {$repName}
    </p>
    <p style="color: #64748b; font-size: 14px; margin: 0;">Requesting Assignment to:</p>
    <p style="color: #1e293b; font-size: 16px; margin: 5px 0 0 0; font-weight: 600;">
        {$clinicName}
    </p>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="{$reviewUrl}" style="display: inline-block; background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
        Review Request
    </a>
</div>
HTML;

    $html = email_template('New Assignment Request', $body);

    // Send to all admins
    $admins = get_admin_notification_emails($pdo);
    $success = true;
    foreach ($admins as $admin) {
        if (!send_email($admin['email'], $admin['name'], "Assignment Request: {$repName} → {$clinicName}", $html)) {
            $success = false;
        }
    }
    return $success;
}
