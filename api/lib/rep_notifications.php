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
    $baseUrl = env('APP_URL', 'https://collagendirect.health');
    $portalUrl = $baseUrl . '/sales-training/login.php';
    $quickStartUrl = $baseUrl . '/sales-training/quick-start-guide.php';
    $trainingUrl = $baseUrl . '/sales-training/';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">Congratulations! You're Approved!</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Dear {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    We're excited to welcome you to the CollagenDirect Sales Team! Your application has been reviewed and approved.
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    Your account is now active. <strong>Use the same email and password you created during signup to log in.</strong>
</p>

<!-- Primary CTA: Quick Start Guide -->
<div style="background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%); border-radius: 12px; padding: 24px; margin: 25px 0; text-align: center;">
    <p style="color: #ffffff; font-size: 13px; font-weight: 600; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.5px;">
        ⚡ START HERE - 30 MINUTES
    </p>
    <p style="color: #ffffff; font-size: 18px; font-weight: 700; margin: 0 0 15px 0;">
        Quick Start Guide: Start Selling TODAY
    </p>
    <a href="{$quickStartUrl}" style="display: inline-block; background: #ffffff; color: #ea580c; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 15px;">
        Open Quick Start Guide
    </a>
</div>

<div style="background-color: #f8fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <p style="color: #1e293b; font-size: 15px; font-weight: 600; margin: 0 0 12px 0;">
        Your Two Portals:
    </p>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
                <strong style="color: #0d9488;">Sales Training Portal</strong><br>
                <span style="color: #64748b; font-size: 13px;">Scripts, product training, objection handling</span>
            </td>
            <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                <a href="{$trainingUrl}" style="color: #0d9488; font-size: 13px; text-decoration: none;">training →</a>
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 0;">
                <strong style="color: #0d9488;">Rep Dashboard</strong><br>
                <span style="color: #64748b; font-size: 13px;">Onboard clinics, track commissions, manage docs</span>
            </td>
            <td style="padding: 10px 0; text-align: right;">
                <a href="{$portalUrl}" style="color: #0d9488; font-size: 13px; text-decoration: none;">dashboard →</a>
            </td>
        </tr>
    </table>
</div>

<div style="background-color: #f0fdfa; border-left: 4px solid #14b8a6; padding: 15px 20px; margin: 20px 0;">
    <p style="color: #0d9488; font-size: 14px; margin: 0; font-weight: 500;">
        <strong>Your First Week Checklist:</strong><br>
        1. Complete the Quick Start Guide (30 min)<br>
        2. Review the product training materials<br>
        3. Make your first outreach calls<br>
        4. Onboard your first clinic from the Dashboard
    </p>
</div>

<p style="color: #64748b; font-size: 13px; line-height: 1.6; margin: 20px 0 0 0;">
    We'll send you follow-up emails over the next week with more training tips. Welcome to the team!
</p>
HTML;

    $html = email_template('Welcome to CollagenDirect!', $body);

    // Schedule follow-up onboarding emails
    schedule_rep_onboarding_emails($pdo, $repEmail, $repName);

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
 * Send invitation email to rep with onboarding link
 *
 * The invite URL routes to /rep-invite/?token=xxx where the rep will:
 * 1. Set their password
 * 2. Sign the Sales Rep Agreement
 * 3. Sign the Business Associate Agreement (BAA)
 */
function send_rep_invite(PDO $pdo, string $repEmail, string $repName, string $inviteToken, ?string $personalNote = null): bool {
    // Important: Use the onboarding URL with the invite token, NOT the login page
    $onboardingUrl = env('APP_URL', 'https://collagendirect.health') . '/rep-invite/?token=' . urlencode($inviteToken);

    $personalNoteHtml = $personalNote
        ? "<div style=\"background-color: #f0fdfa; border-left: 4px solid #14b8a6; padding: 15px 20px; margin: 20px 0;\">
            <p style=\"color: #0d9488; font-size: 14px; margin: 0; font-weight: 500;\">
                <strong>Personal Note:</strong><br>" . htmlspecialchars($personalNote) . "
            </p>
           </div>"
        : '';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">You're Invited to Join CollagenDirect!</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Dear {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    You've been invited to become a CollagenDirect Sales Representative. To get started, you'll need to complete a quick onboarding process.
</p>

{$personalNoteHtml}

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    Click the button below to complete your setup:
</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="{$onboardingUrl}" style="display: inline-block; background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
        Complete Your Onboarding
    </a>
</div>

<div style="background-color: #f0fdfa; border-left: 4px solid #14b8a6; padding: 15px 20px; margin: 20px 0;">
    <p style="color: #0d9488; font-size: 14px; margin: 0; font-weight: 500;">
        <strong>What to Expect:</strong><br>
        • Set your secure password<br>
        • Review and sign the Sales Rep Agreement<br>
        • Review and sign the Business Associate Agreement (BAA)<br>
        • Get access to your distributor portal
    </p>
</div>

<p style="color: #94a3b8; font-size: 13px; line-height: 1.6; margin: 20px 0 0 0;">
    <strong>Note:</strong> This invitation link expires in 7 days. If you need a new link, please contact your administrator.
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 20px 0 0 0;">
    We look forward to a successful partnership!
</p>
HTML;

    $html = email_template('Welcome to CollagenDirect!', $body);
    return send_email($repEmail, $repName, 'You\'re Invited to Join CollagenDirect!', $html);
}

/**
 * Send notification to rep: Application Approved (alias for backwards compatibility)
 */
function send_rep_approved(PDO $pdo, string $repEmail, string $repName): bool {
    return send_rep_application_approved($pdo, $repEmail, $repName);
}

/**
 * Send notification to rep: Application Rejected (alias for backwards compatibility)
 */
function send_rep_rejected(PDO $pdo, string $repEmail, string $repName, ?string $reason = null): bool {
    return send_rep_application_rejected($pdo, $repEmail, $repName, $reason);
}

/**
 * Send W9 request email to rep
 */
function send_rep_w9_request(PDO $pdo, string $repEmail, string $repName, int $taxYear, ?string $message = null): bool {
    $portalUrl = env('APP_URL', 'https://collagendirect.health') . '/admin/rep/';

    $messageHtml = $message
        ? "<div style=\"background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px 20px; margin: 20px 0;\">
            <p style=\"color: #92400e; font-size: 14px; margin: 0;\">
                <strong>Note from Admin:</strong><br>{$message}
            </p>
           </div>"
        : '';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">W-9 Form Required</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Dear {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    We need you to submit a W-9 form for tax year <strong>{$taxYear}</strong>. This is required for commission payouts and tax reporting purposes.
</p>

{$messageHtml}

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    Please log in to your portal and upload your completed W-9 form at your earliest convenience.
</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="{$portalUrl}" style="display: inline-block; background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
        Submit W-9 Form
    </a>
</div>

<div style="background-color: #f8fafc; border-left: 4px solid #64748b; padding: 15px 20px; margin: 20px 0;">
    <p style="color: #475569; font-size: 14px; margin: 0;">
        <strong>Why is this needed?</strong><br>
        The W-9 form provides your tax identification information which is required by the IRS before we can process commission payments to you.
    </p>
</div>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 20px 0 0 0;">
    Thank you for your prompt attention to this matter.
</p>
HTML;

    $html = email_template('W-9 Form Required - CollagenDirect', $body);
    return send_email($repEmail, $repName, "W-9 Form Required for Tax Year {$taxYear}", $html);
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

// ============================================================================
// ONBOARDING EMAIL SEQUENCE
// ============================================================================

/**
 * Schedule the onboarding email sequence for a newly approved rep
 */
function schedule_rep_onboarding_emails(PDO $pdo, string $repEmail, string $repName): bool {
    try {
        // Check if table exists, create if not
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS scheduled_emails (
                id SERIAL PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255),
                email_type VARCHAR(50) NOT NULL,
                scheduled_for TIMESTAMP NOT NULL,
                sent_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create index if not exists
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_scheduled_emails_pending ON scheduled_emails(scheduled_for) WHERE sent_at IS NULL");

        $now = new DateTime('now', new DateTimeZone('America/New_York'));

        // Day 1: Product Training focus (24 hours later)
        $day1 = clone $now;
        $day1->modify('+1 day');

        // Day 3: Battle Cards focus
        $day3 = clone $now;
        $day3->modify('+3 days');

        // Day 7: HIPAA + Success Stories
        $day7 = clone $now;
        $day7->modify('+7 days');

        $stmt = $pdo->prepare("
            INSERT INTO scheduled_emails (email, name, email_type, scheduled_for)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([$repEmail, $repName, 'onboarding_day1', $day1->format('Y-m-d H:i:s')]);
        $stmt->execute([$repEmail, $repName, 'onboarding_day3', $day3->format('Y-m-d H:i:s')]);
        $stmt->execute([$repEmail, $repName, 'onboarding_day7', $day7->format('Y-m-d H:i:s')]);

        return true;
    } catch (Exception $e) {
        error_log("Failed to schedule onboarding emails: " . $e->getMessage());
        return false;
    }
}

/**
 * Day 1 Email: Product Training Focus
 * "How's it going? Here's what you need to know about our products"
 */
function send_rep_onboarding_day1(PDO $pdo, string $repEmail, string $repName): bool {
    $baseUrl = env('APP_URL', 'https://collagendirect.health');
    $productTrainingUrl = $baseUrl . '/sales-training/product-training.php';
    $objectionsUrl = $baseUrl . '/sales-training/objections.php';
    $quickStartUrl = $baseUrl . '/sales-training/quick-start-guide.php';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">Day 1: Know What You're Selling</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Hey {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    How's your first day going? If you haven't already, make sure you've completed the <a href="{$quickStartUrl}" style="color: #0d9488;">Quick Start Guide</a> – it's the fastest way to get up to speed.
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    Today's focus: <strong>Product Knowledge</strong>. The more confidently you can speak about our products, the more deals you'll close.
</p>

<!-- Primary CTA -->
<div style="background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%); border-radius: 12px; padding: 24px; margin: 25px 0; text-align: center;">
    <p style="color: #ffffff; font-size: 13px; font-weight: 600; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.5px;">
        📚 TODAY'S TRAINING
    </p>
    <p style="color: #ffffff; font-size: 18px; font-weight: 700; margin: 0 0 15px 0;">
        Product Training: Master the Portfolio
    </p>
    <a href="{$productTrainingUrl}" style="display: inline-block; background: #ffffff; color: #059669; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 15px;">
        Start Product Training
    </a>
</div>

<div style="background-color: #f8fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <p style="color: #1e293b; font-size: 15px; font-weight: 600; margin: 0 0 12px 0;">
        What You'll Learn:
    </p>
    <ul style="color: #475569; font-size: 14px; line-height: 1.8; margin: 0; padding-left: 20px;">
        <li>All 4 core products and their applications</li>
        <li>HCPCS codes and reimbursement info</li>
        <li>Clinical evidence and differentiators</li>
        <li>Which product for which wound type</li>
    </ul>
</div>

<div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px 20px; margin: 20px 0;">
    <p style="color: #92400e; font-size: 14px; margin: 0;">
        <strong>Pro Tip:</strong> After product training, check out the <a href="{$objectionsUrl}" style="color: #92400e;">Objection Handling</a> guide. Knowing how to handle "we already have a supplier" will make your first calls much smoother.
    </p>
</div>

<p style="color: #64748b; font-size: 13px; line-height: 1.6; margin: 20px 0 0 0;">
    Tomorrow we'll send you competitive intel. Keep up the momentum!
</p>
HTML;

    $html = email_template('Day 1: Product Training', $body);
    return send_email($repEmail, $repName, 'Day 1: Know What You\'re Selling - Product Training', $html);
}

/**
 * Day 3 Email: Battle Cards Focus
 * "Crush the competition with these battle cards"
 */
function send_rep_onboarding_day3(PDO $pdo, string $repEmail, string $repName): bool {
    $baseUrl = env('APP_URL', 'https://collagendirect.health');
    $battleCardsUrl = $baseUrl . '/sales-training/battle-cards.php';
    $scriptsUrl = $baseUrl . '/sales-training/scripts.php';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">Day 3: Crush the Competition</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Hey {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    By now you should have a solid foundation on our products. Today's focus: <strong>competitive positioning</strong>.
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    Most practices already have a wound care supplier. Here's how to position CollagenDirect as the better choice.
</p>

<!-- Primary CTA -->
<div style="background: linear-gradient(135deg, #ef4444 0%, #f97316 100%); border-radius: 12px; padding: 24px; margin: 25px 0; text-align: center;">
    <p style="color: #ffffff; font-size: 13px; font-weight: 600; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.5px;">
        🎯 TODAY'S TRAINING
    </p>
    <p style="color: #ffffff; font-size: 18px; font-weight: 700; margin: 0 0 15px 0;">
        Battle Cards: Win Against Any Competitor
    </p>
    <a href="{$battleCardsUrl}" style="display: inline-block; background: #ffffff; color: #dc2626; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 15px;">
        View Battle Cards
    </a>
</div>

<div style="background-color: #f8fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <p style="color: #1e293b; font-size: 15px; font-weight: 600; margin: 0 0 12px 0;">
        Competitors You'll Face:
    </p>
    <ul style="color: #475569; font-size: 14px; line-height: 1.8; margin: 0; padding-left: 20px;">
        <li><strong>Smith & Nephew</strong> – How we compare on price & service</li>
        <li><strong>3M</strong> – Our clinical advantages</li>
        <li><strong>Integra</strong> – Why practices switch to us</li>
        <li><strong>Generic suppliers</strong> – Our quality difference</li>
    </ul>
</div>

<div style="background-color: #f0fdfa; border-left: 4px solid #14b8a6; padding: 15px 20px; margin: 20px 0;">
    <p style="color: #0d9488; font-size: 14px; margin: 0;">
        <strong>Also check out:</strong> Our <a href="{$scriptsUrl}" style="color: #0d9488;">Sales Scripts</a> have proven talk tracks for handling "we're happy with our current supplier" objections.
    </p>
</div>

<p style="color: #64748b; font-size: 13px; line-height: 1.6; margin: 20px 0 0 0;">
    You're building real sales skills. Keep it up!
</p>
HTML;

    $html = email_template('Day 3: Competitive Intel', $body);
    return send_email($repEmail, $repName, 'Day 3: Battle Cards - Beat Any Competitor', $html);
}

/**
 * Day 7 Email: HIPAA + Success Stories
 * "You're ready to close deals"
 */
function send_rep_onboarding_day7(PDO $pdo, string $repEmail, string $repName): bool {
    $baseUrl = env('APP_URL', 'https://collagendirect.health');
    $hipaaUrl = $baseUrl . '/sales-training/hipaa-training.php';
    $trainingUrl = $baseUrl . '/sales-training/';
    $repPortalUrl = $baseUrl . '/admin/rep/';

    $body = <<<HTML
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 22px;">Day 7: You're Ready to Close Deals</h2>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Hey {$repName},
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">
    Congratulations on completing your first week! You now have the knowledge to confidently sell CollagenDirect products.
</p>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
    One last thing: <strong>HIPAA compliance</strong>. Since you'll be working with healthcare providers, this training is required.
</p>

<!-- Primary CTA -->
<div style="background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%); border-radius: 12px; padding: 24px; margin: 25px 0; text-align: center;">
    <p style="color: #ffffff; font-size: 13px; font-weight: 600; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.5px;">
        ✅ REQUIRED TRAINING
    </p>
    <p style="color: #ffffff; font-size: 18px; font-weight: 700; margin: 0 0 15px 0;">
        HIPAA Compliance Training
    </p>
    <a href="{$hipaaUrl}" style="display: inline-block; background: #ffffff; color: #7c3aed; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 15px;">
        Complete HIPAA Training
    </a>
</div>

<div style="background-color: #f0fdfa; border-left: 4px solid #14b8a6; padding: 15px 20px; margin: 20px 0;">
    <p style="color: #0d9488; font-size: 14px; margin: 0; font-weight: 500;">
        <strong>Your Week 1 Recap:</strong><br>
        ✓ Quick Start Guide – selling fundamentals<br>
        ✓ Product Training – know the portfolio<br>
        ✓ Battle Cards – competitive positioning<br>
        → HIPAA Training – compliance (do this today)
    </p>
</div>

<div style="background-color: #f8fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <p style="color: #1e293b; font-size: 15px; font-weight: 600; margin: 0 0 12px 0;">
        What's Next?
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
        You have everything you need to start closing deals:
    </p>
    <ul style="color: #475569; font-size: 14px; line-height: 1.8; margin: 0; padding-left: 20px;">
        <li>Use your <a href="{$repPortalUrl}" style="color: #0d9488;">Rep Dashboard</a> to onboard clinics</li>
        <li>Reference the <a href="{$trainingUrl}" style="color: #0d9488;">Training Portal</a> anytime you need scripts or product info</li>
        <li>Track your commissions in real-time as orders come in</li>
    </ul>
</div>

<p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 20px 0 0 0;">
    Questions? Reply to this email or reach out to your account manager. We're here to help you succeed.
</p>

<p style="color: #64748b; font-size: 13px; line-height: 1.6; margin: 15px 0 0 0;">
    Go close some deals! 🎯
</p>
HTML;

    $html = email_template('Week 1 Complete!', $body);
    return send_email($repEmail, $repName, 'Week 1 Complete - HIPAA Training & Next Steps', $html);
}

/**
 * Process scheduled emails (call this from a cron job)
 * Returns the number of emails sent
 */
function process_scheduled_emails(PDO $pdo): int {
    $sent = 0;

    try {
        // Get emails that are due and haven't been sent
        $stmt = $pdo->prepare("
            SELECT id, email, name, email_type
            FROM scheduled_emails
            WHERE scheduled_for <= NOW()
              AND sent_at IS NULL
            ORDER BY scheduled_for ASC
            LIMIT 50
        ");
        $stmt->execute();
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($emails as $email) {
            $success = false;

            switch ($email['email_type']) {
                case 'onboarding_day1':
                    $success = send_rep_onboarding_day1($pdo, $email['email'], $email['name']);
                    break;
                case 'onboarding_day3':
                    $success = send_rep_onboarding_day3($pdo, $email['email'], $email['name']);
                    break;
                case 'onboarding_day7':
                    $success = send_rep_onboarding_day7($pdo, $email['email'], $email['name']);
                    break;
            }

            if ($success) {
                // Mark as sent
                $updateStmt = $pdo->prepare("UPDATE scheduled_emails SET sent_at = NOW() WHERE id = ?");
                $updateStmt->execute([$email['id']]);
                $sent++;
            }
        }
    } catch (Exception $e) {
        error_log("Error processing scheduled emails: " . $e->getMessage());
    }

    return $sent;
}
