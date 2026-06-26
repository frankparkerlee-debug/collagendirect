<?php
/**
 * Send New Hire Welcome Email
 *
 * Sends a comprehensive welcome email to new sales team members
 * with links to onboarding resources and training materials.
 * Uses SMTP/Gmail via email_sender.php
 */

require_once __DIR__ . '/../api/lib/env.php';
require_once __DIR__ . '/../api/lib/email_sender.php';

// New hire information
$newHireName = 'Alina Herrera';
$newHireEmail = 'alinaherrera29@gmail.com';
$newHireFirstName = 'Alina';

$subject = "Welcome to CollagenDirect, {$newHireFirstName}! Your Onboarding Resources";

// Email HTML content
$htmlContent = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to CollagenDirect</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Arial', 'Helvetica', sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">

                    <!-- Header with gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0075bc 0%, #34d399 100%); padding: 50px 40px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0 0 10px 0; font-size: 32px; font-weight: 900;">Welcome to CollagenDirect!</h1>
                            <p style="color: #e0f7f5; margin: 0; font-size: 16px;">Hi {$newHireFirstName}, we're thrilled to have you on our team!</p>
                        </td>
                    </tr>

                    <!-- Main content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="color: #1f2937; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Congratulations on joining CollagenDirect as a <strong>Sales Representative</strong>! You're about to embark on an exciting journey helping physicians transform their wound care practices.
                            </p>

                            <p style="color: #1f2937; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                                We've prepared a comprehensive onboarding playbook to help you hit the ground running. Below you'll find all the resources you need to become a successful member of our sales team.
                            </p>

                            <!-- Key Resources Section -->
                            <div style="background-color: #f0fdfc; border-left: 4px solid #0075bc; padding: 20px; margin-bottom: 30px; border-radius: 8px;">
                                <h2 style="color: #0a2540; margin: 0 0 15px 0; font-size: 20px; font-weight: 700;">Your Training Hub</h2>
                                <p style="color: #374151; font-size: 14px; margin: 0 0 15px 0;">
                                    Access your personalized onboarding portal with interactive checklists and progress tracking:
                                </p>
                                <a href="https://collagendirect.health/sales-training/new-hire-welcome.php?email={$newHireEmail}"
                                   style="display: inline-block; background-color: #0075bc; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 700; font-size: 16px; margin-top: 10px;">
                                    Start Your Onboarding
                                </a>
                            </div>

                            <!-- What to Expect -->
                            <h2 style="color: #0a2540; margin: 0 0 20px 0; font-size: 20px; font-weight: 700;">Your First Week Journey</h2>

                            <!-- Phase 1 -->
                            <div style="margin-bottom: 20px;">
                                <div style="background-color: #fef2f2; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                                    <h3 style="color: #dc2626; margin: 0 0 8px 0; font-size: 16px; font-weight: 700;">
                                        Day 1: HR & Compliance (Critical)
                                    </h3>
                                    <ul style="color: #374151; font-size: 14px; margin: 0; padding-left: 20px; line-height: 1.6;">
                                        <li>Complete HIPAA training & sign confidentiality agreement</li>
                                        <li>Submit I-9, W-4, and direct deposit forms</li>
                                        <li>Review employee handbook & Business Associate Agreement</li>
                                        <li>Set up your @collagendirect.health email</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Phase 2 -->
                            <div style="margin-bottom: 20px;">
                                <div style="background-color: #eff6ff; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                                    <h3 style="color: #2563eb; margin: 0 0 8px 0; font-size: 16px; font-weight: 700;">
                                        Days 2-3: Product & Industry Knowledge
                                    </h3>
                                    <ul style="color: #374151; font-size: 14px; margin: 0; padding-left: 20px; line-height: 1.6;">
                                        <li>Learn about collagen wound therapy and clinical evidence</li>
                                        <li>Memorize our 4 core products and HCPCS billing codes</li>
                                        <li>Understand insurance coverage (Medicare, Medicaid, commercial)</li>
                                        <li>Study common physician questions and objections</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Phase 3 -->
                            <div style="margin-bottom: 20px;">
                                <div style="background-color: #ecfdf5; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                                    <h3 style="color: #059669; margin: 0 0 8px 0; font-size: 16px; font-weight: 700;">
                                        Days 3-5: Sales Methodology Training
                                    </h3>
                                    <ul style="color: #374151; font-size: 14px; margin: 0; padding-left: 20px; line-height: 1.6;">
                                        <li>Master our 4-Step Sales Process (Get Meeting → Conversation → Register → Nurture)</li>
                                        <li>Practice discovery questions and role-play scenarios</li>
                                        <li>Review cold call scripts and objection handling techniques</li>
                                        <li>Study competitive battle cards for positioning vs competitors</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Phase 4 -->
                            <div style="margin-bottom: 30px;">
                                <div style="background-color: #f5f3ff; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                                    <h3 style="color: #7c3aed; margin: 0 0 8px 0; font-size: 16px; font-weight: 700;">
                                        End of Week 1: Systems & Tools
                                    </h3>
                                    <ul style="color: #374151; font-size: 14px; margin: 0; padding-left: 20px; line-height: 1.6;">
                                        <li>Complete physician portal walkthrough</li>
                                        <li>Get CRM system access and training</li>
                                        <li>Receive your product sample kit</li>
                                        <li>Schedule shadow day with a senior sales rep</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Quick Reference Resources -->
                            <div style="background-color: #fef9c3; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                                <h2 style="color: #854d0e; margin: 0 0 15px 0; font-size: 18px; font-weight: 700;">Quick Access Resources</h2>
                                <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <a href="https://collagendirect.health/sales-training/" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">Training Hub Home</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <a href="https://collagendirect.health/sales-training/sales-process.php" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">4-Step Sales Process</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <a href="https://collagendirect.health/sales-training/product-training.php" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">Product Training</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <a href="https://collagendirect.health/sales-training/objections.php" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">Objection Handling</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <a href="https://collagendirect.health/sales-training/hipaa-training.php" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">HIPAA Training</a>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Support -->
                            <div style="background-color: #f9fafb; border-radius: 8px; padding: 20px; text-align: center;">
                                <p style="color: #6b7280; font-size: 14px; margin: 0 0 15px 0;">
                                    <strong>Questions or need help?</strong>
                                </p>
                                <p style="color: #2563eb; font-size: 16px; margin: 0; font-weight: 600;">
                                    Contact: <a href="mailto:parker@collagendirect.health" style="color: #2563eb; text-decoration: none;">parker@collagendirect.health</a>
                                </p>
                            </div>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #0a2540; padding: 30px 40px; text-align: center;">
                            <p style="color: #9ca3af; font-size: 14px; margin: 0 0 10px 0;">
                                <strong style="color: #ffffff;">Welcome to the team!</strong><br>
                                We're excited to have you at CollagenDirect.
                            </p>
                            <p style="color: #6b7280; font-size: 12px; margin: 0;">
                                CollagenDirect | Streamlining Wound Care, One Patient at a Time<br>
                                <a href="https://collagendirect.health" style="color: #0075bc; text-decoration: none;">collagendirect.health</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

// Plain text version for email clients that don't support HTML
$textContent = <<<TEXT
Welcome to CollagenDirect, {$newHireFirstName}!

Hi {$newHireFirstName},

Congratulations on joining CollagenDirect as a Sales Representative! We're thrilled to have you on our team.

YOUR TRAINING HUB
Access your personalized onboarding portal here:
https://collagendirect.health/sales-training/new-hire-welcome.php?email={$newHireEmail}

YOUR FIRST WEEK JOURNEY

Day 1: HR & Compliance (Critical)
- Complete HIPAA training & sign confidentiality agreement
- Submit I-9, W-4, and direct deposit forms
- Review employee handbook & Business Associate Agreement
- Set up your @collagendirect.health email

Days 2-3: Product & Industry Knowledge
- Learn about collagen wound therapy and clinical evidence
- Memorize our 4 core products and HCPCS billing codes
- Understand insurance coverage
- Study common physician questions

Days 3-5: Sales Methodology Training
- Master our 4-Step Sales Process
- Practice discovery questions and role-play scenarios
- Review cold call scripts and objection handling
- Study competitive battle cards

End of Week 1: Systems & Tools
- Complete physician portal walkthrough
- Get CRM system access
- Receive your product sample kit
- Schedule shadow day with senior rep

QUICK ACCESS RESOURCES
- Training Hub: https://collagendirect.health/sales-training/
- 4-Step Sales Process: https://collagendirect.health/sales-training/sales-process.php
- Product Training: https://collagendirect.health/sales-training/product-training.php
- Objection Handling: https://collagendirect.health/sales-training/objections.php
- HIPAA Training: https://collagendirect.health/sales-training/hipaa-training.php

NEED HELP?
Contact Parker: parker@collagendirect.health

Welcome to the team!

CollagenDirect
Streamlining Wound Care, One Patient at a Time
https://collagendirect.health
TEXT;

// Send the email
echo "Sending welcome email to {$newHireName} ({$newHireEmail})...\n";
echo "-----------------------------------------------------------\n";

$success = send_email($newHireEmail, $newHireName, $subject, $htmlContent, $textContent);

if ($success) {
    echo "SUCCESS! Email sent successfully.\n";
    echo "\nEmail Details:\n";
    echo "  To: {$newHireName} <{$newHireEmail}>\n";
    echo "  Subject: {$subject}\n";
    echo "\nThe email includes:\n";
    echo "  - Direct link to personalized onboarding portal\n";
    echo "  - Complete first-week journey breakdown\n";
    echo "  - Quick access to all training resources\n";
    echo "  - Contact information for support\n";
} else {
    echo "ERROR: Failed to send email.\n";
    echo "Check the error logs for details.\n";
    exit(1);
}

echo "\n-----------------------------------------------------------\n";
echo "Done!\n";
?>
