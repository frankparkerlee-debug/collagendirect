<?php
/**
 * Complete Sales Agent Automation with HubSpot Integration
 *
 * 4-Stage Lifecycle:
 * 1. Prospect → Find physicians via NPI Registry
 * 2. Contact → Outreach to drive registration
 * 3. Register → Physician signs up for portal
 * 4. Nurture → Ensure referrals keep happening
 */

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/hubspot-integration.php');
require_once(__DIR__ . '/sendgrid-integration.php');

class CompleteAutomation {
    private $pdo;
    private $hubspot;
    private $mailer;
    private $dry_run = false;

    // SendGrid template IDs
    private $templates = [
        'cold_outreach' => 'd-ffb45888b631435c9261460d993c6a37',
        'followup_soft' => 'd-ffb45888b631435c9261460d993c6a37',
        'followup_roi' => 'd-6e834e33b85d477e88afb5c38e0e550e',
        'breakup' => 'd-53cc9016294241d1864885852e3e0f12',

        // Post-registration nurture templates
        'welcome_registered' => 'd-ffb45888b631435c9261460d993c6a37', // TODO: Create specific template
        'first_order_reminder' => 'd-ffb45888b631435c9261460d993c6a37',
        'no_orders_30_days' => 'd-ffb45888b631435c9261460d993c6a37',
        'reengagement' => 'd-ffb45888b631435c9261460d993c6a37'
    ];

    public function __construct($pdo, $dry_run = false) {
        $this->pdo = $pdo;
        $this->hubspot = new HubSpotIntegration();
        $this->mailer = new SendGridMailer();
        $this->dry_run = $dry_run;
    }

    /**
     * Main automation runner - call daily via cron
     */
    public function run() {
        echo "=== Complete Sales Automation with HubSpot ===\n";
        echo "Time: " . date('Y-m-d H:i:s') . "\n";
        echo "Dry run: " . ($this->dry_run ? 'YES' : 'NO') . "\n\n";

        $stats = [
            // Pre-registration outreach
            'cold_sent' => 0,
            'followup1_sent' => 0,
            'followup2_sent' => 0,
            'breakup_sent' => 0,

            // Post-registration nurture
            'welcome_sent' => 0,
            'first_order_reminders' => 0,
            'at_risk_contacted' => 0,
            'reengagement_sent' => 0,

            'errors' => 0
        ];

        // ========================================
        // STAGE 2: PRE-REGISTRATION OUTREACH
        // ========================================

        echo "\n=== STAGE 2: PRE-REGISTRATION OUTREACH ===\n";

        // Day 0: Cold outreach to new leads
        $stats['cold_sent'] = $this->sendColdOutreach();

        // Day 3: Soft follow-up
        $stats['followup1_sent'] = $this->sendFollowup1();

        // Day 7: ROI-focused follow-up
        $stats['followup2_sent'] = $this->sendFollowup2();

        // Day 14: Breakup email
        $stats['breakup_sent'] = $this->sendBreakupEmail();

        // ========================================
        // STAGE 4: POST-REGISTRATION NURTURE
        // ========================================

        echo "\n=== STAGE 4: POST-REGISTRATION NURTURE ===\n";

        // Welcome newly registered physicians
        $stats['welcome_sent'] = $this->welcomeNewRegistrations();

        // Remind physicians who registered but haven't ordered
        $stats['first_order_reminders'] = $this->remindFirstOrder();

        // Re-engage physicians who haven't ordered in 30 days
        $stats['at_risk_contacted'] = $this->contactAtRiskPhysicians();

        // Reactivate churned physicians (no orders in 90 days)
        $stats['reengagement_sent'] = $this->reengageChurned();

        // ========================================
        // SUMMARY
        // ========================================

        echo "\n=== Automation Complete ===\n";
        echo "Pre-Registration Outreach:\n";
        echo "  - Cold outreach sent: {$stats['cold_sent']}\n";
        echo "  - Follow-up #1 sent: {$stats['followup1_sent']}\n";
        echo "  - Follow-up #2 sent: {$stats['followup2_sent']}\n";
        echo "  - Breakup emails sent: {$stats['breakup_sent']}\n";
        echo "\nPost-Registration Nurture:\n";
        echo "  - Welcome emails sent: {$stats['welcome_sent']}\n";
        echo "  - First order reminders: {$stats['first_order_reminders']}\n";
        echo "  - At-risk contacted: {$stats['at_risk_contacted']}\n";
        echo "  - Reengagement sent: {$stats['reengagement_sent']}\n";
        echo "=============================\n";

        return $stats;
    }

    // ========================================
    // PRE-REGISTRATION OUTREACH METHODS
    // ========================================

    /**
     * Send cold outreach to new leads (Stage 2: Contact)
     */
    private function sendColdOutreach() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM leads
            WHERE status = 'new'
            AND email IS NOT NULL
            AND last_contacted_at IS NULL
            AND hubspot_contact_id IS NOT NULL
            ORDER BY lead_score DESC, created_at ASC
            LIMIT 50
        ");
        $stmt->execute();
        $leads = $stmt->fetchAll();

        echo "\n--- Cold Outreach (Stage 2: Contact) ---\n";
        echo "Found " . count($leads) . " new leads to contact\n";

        $sent = 0;
        foreach ($leads as $lead) {
            if ($this->sendEmailWithHubSpotSync($lead, 'cold_outreach', 'Cold Outreach')) {
                $sent++;

                if (!$this->dry_run) {
                    // Update lead status
                    $this->pdo->prepare("
                        UPDATE leads
                        SET status = 'contacted',
                            last_contacted_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ")->execute([$lead['id']]);

                    // Update HubSpot deal stage
                    if ($lead['hubspot_deal_id']) {
                        $this->hubspot->updateDealStage($lead['hubspot_deal_id'], 'contacted');
                    }
                }
            }

            usleep(36000); // Rate limiting
        }

        return $sent;
    }

    /**
     * Send follow-up #1 (Day 3) - Soft touch
     */
    private function sendFollowup1() {
        $stmt = $this->pdo->prepare("
            SELECT l.* FROM leads l
            LEFT JOIN outreach_log o ON l.id = o.lead_id
            WHERE l.status = 'contacted'
            AND l.last_contacted_at <= CURRENT_TIMESTAMP - INTERVAL '3 days'
            AND l.last_contacted_at >= CURRENT_TIMESTAMP - INTERVAL '4 days'
            AND o.replied_at IS NULL
            GROUP BY l.id
            HAVING COUNT(o.id) = 1
            LIMIT 50
        ");
        $stmt->execute();
        $leads = $stmt->fetchAll();

        echo "\n--- Follow-up #1 (Day 3 - Soft Touch) ---\n";
        echo "Found " . count($leads) . " leads for follow-up\n";

        $sent = 0;
        foreach ($leads as $lead) {
            if ($this->sendEmailWithHubSpotSync($lead, 'followup_soft', 'Follow-up #1 (Soft)')) {
                $sent++;
            }
            usleep(36000);
        }

        return $sent;
    }

    /**
     * Send follow-up #2 (Day 7) - ROI focus
     */
    private function sendFollowup2() {
        $stmt = $this->pdo->prepare("
            SELECT l.* FROM leads l
            LEFT JOIN outreach_log o ON l.id = o.lead_id
            WHERE l.status = 'contacted'
            AND l.last_contacted_at <= CURRENT_TIMESTAMP - INTERVAL '4 days'
            AND l.last_contacted_at >= CURRENT_TIMESTAMP - INTERVAL '5 days'
            AND o.replied_at IS NULL
            GROUP BY l.id
            HAVING COUNT(o.id) = 2
            LIMIT 50
        ");
        $stmt->execute();
        $leads = $stmt->fetchAll();

        echo "\n--- Follow-up #2 (Day 7 - ROI Focus) ---\n";
        echo "Found " . count($leads) . " leads for ROI follow-up\n";

        $sent = 0;
        foreach ($leads as $lead) {
            if ($this->sendEmailWithHubSpotSync($lead, 'followup_roi', 'Follow-up #2 (ROI)')) {
                $sent++;
            }
            usleep(36000);
        }

        return $sent;
    }

    /**
     * Send breakup email (Day 14)
     */
    private function sendBreakupEmail() {
        $stmt = $this->pdo->prepare("
            SELECT l.* FROM leads l
            LEFT JOIN outreach_log o ON l.id = o.lead_id
            WHERE l.status = 'contacted'
            AND l.last_contacted_at <= CURRENT_TIMESTAMP - INTERVAL '7 days'
            AND l.last_contacted_at >= CURRENT_TIMESTAMP - INTERVAL '8 days'
            AND o.replied_at IS NULL
            GROUP BY l.id
            HAVING COUNT(o.id) = 3
            LIMIT 50
        ");
        $stmt->execute();
        $leads = $stmt->fetchAll();

        echo "\n--- Breakup Email (Day 14) ---\n";
        echo "Found " . count($leads) . " leads for breakup email\n";

        $sent = 0;
        foreach ($leads as $lead) {
            if ($this->sendEmailWithHubSpotSync($lead, 'breakup', 'Breakup Email')) {
                $sent++;

                if (!$this->dry_run) {
                    // Move to nurture status (6-month follow-up)
                    $this->pdo->prepare("
                        UPDATE leads
                        SET status = 'nurture',
                            next_followup_date = CURRENT_DATE + INTERVAL '6 months'
                        WHERE id = ?
                    ")->execute([$lead['id']]);
                }
            }
            usleep(36000);
        }

        return $sent;
    }

    // ========================================
    // POST-REGISTRATION NURTURE METHODS
    // ========================================

    /**
     * Welcome newly registered physicians (within 24 hours)
     */
    private function welcomeNewRegistrations() {
        // Query portal database for new registrations
        $stmt = $this->pdo->prepare("
            SELECT u.*, l.hubspot_contact_id
            FROM portal_users u
            LEFT JOIN leads l ON u.email = l.email
            WHERE u.created_at >= CURRENT_TIMESTAMP - INTERVAL '24 hours'
            AND u.user_type = 'physician'
            AND l.welcome_sent = FALSE
        ");
        $stmt->execute();
        $newPhysicians = $stmt->fetchAll();

        echo "\n--- Welcome New Registrations (Stage 3 → 4) ---\n";
        echo "Found " . count($newPhysicians) . " new registrations\n";

        $sent = 0;
        foreach ($newPhysicians as $physician) {
            // Send welcome email
            // TODO: Create specific welcome template

            // Sync to HubSpot
            if ($physician['hubspot_contact_id'] && !$this->dry_run) {
                $this->hubspot->trackRegistration($physician['hubspot_contact_id'], [
                    'user_id' => $physician['id'],
                    'practice_name' => $physician['practice_name']
                ]);

                // Mark as welcomed
                $this->pdo->prepare("
                    UPDATE leads SET welcome_sent = TRUE WHERE email = ?
                ")->execute([$physician['email']]);
            }

            $sent++;
        }

        return $sent;
    }

    /**
     * Remind physicians who registered 7+ days ago but haven't ordered
     */
    private function remindFirstOrder() {
        $stmt = $this->pdo->prepare("
            SELECT u.*, l.hubspot_contact_id
            FROM portal_users u
            LEFT JOIN leads l ON u.email = l.email
            LEFT JOIN orders o ON u.id = o.user_id
            WHERE u.created_at <= CURRENT_TIMESTAMP - INTERVAL '7 days'
            AND u.created_at >= CURRENT_TIMESTAMP - INTERVAL '14 days'
            AND o.id IS NULL
            AND u.user_type = 'physician'
        ");
        $stmt->execute();
        $physicians = $stmt->fetchAll();

        echo "\n--- First Order Reminders (No orders after 7 days) ---\n";
        echo "Found " . count($physicians) . " physicians to remind\n";

        $sent = 0;
        foreach ($physicians as $physician) {
            // Send reminder email
            // Log to HubSpot
            if ($physician['hubspot_contact_id'] && !$this->dry_run) {
                $note = "📧 Sent first order reminder\n\n";
                $note .= "Physician registered " . $physician['created_at'] . " but hasn't placed first order yet.\n";
                $note .= "Reminder sent to check in and offer assistance.";

                $this->hubspot->logNote($physician['hubspot_contact_id'], $note);
            }

            $sent++;
        }

        return $sent;
    }

    /**
     * Contact at-risk physicians (no orders in 30 days)
     */
    private function contactAtRiskPhysicians() {
        $stmt = $this->pdo->prepare("
            SELECT u.*, l.hubspot_contact_id, MAX(o.created_at) as last_order_date
            FROM portal_users u
            LEFT JOIN leads l ON u.email = l.email
            LEFT JOIN orders o ON u.id = o.user_id
            WHERE u.user_type = 'physician'
            GROUP BY u.id, l.hubspot_contact_id
            HAVING MAX(o.created_at) <= CURRENT_TIMESTAMP - INTERVAL '30 days'
            AND MAX(o.created_at) >= CURRENT_TIMESTAMP - INTERVAL '60 days'
        ");
        $stmt->execute();
        $atRisk = $stmt->fetchAll();

        echo "\n--- At-Risk Physicians (No orders 30+ days) ---\n";
        echo "Found " . count($atRisk) . " at-risk physicians\n";

        $contacted = 0;
        foreach ($atRisk as $physician) {
            // Send re-engagement email
            // Log to HubSpot
            if ($physician['hubspot_contact_id'] && !$this->dry_run) {
                $note = "⚠️ Physician marked as AT-RISK\n\n";
                $note .= "Last order: {$physician['last_order_date']}\n";
                $note .= "Re-engagement email sent to check if they need assistance.";

                $this->hubspot->logNote($physician['hubspot_contact_id'], $note);

                // Create task for account manager to call
                $this->hubspot->createTask($physician['hubspot_contact_id'], [
                    'subject' => 'Call at-risk physician - ' . $physician['practice_name'],
                    'description' => "This physician hasn't ordered in 30+ days. Call to check in and see if they need help with anything.",
                    'priority' => 'HIGH',
                    'due_date' => '+2 days'
                ]);
            }

            $contacted++;
        }

        return $contacted;
    }

    /**
     * Reactivate churned physicians (no orders in 90+ days)
     */
    private function reengageChurned() {
        $stmt = $this->pdo->prepare("
            SELECT u.*, l.hubspot_contact_id, MAX(o.created_at) as last_order_date
            FROM portal_users u
            LEFT JOIN leads l ON u.email = l.email
            LEFT JOIN orders o ON u.id = o.user_id
            WHERE u.user_type = 'physician'
            GROUP BY u.id, l.hubspot_contact_id
            HAVING MAX(o.created_at) <= CURRENT_TIMESTAMP - INTERVAL '90 days'
            OR MAX(o.created_at) IS NULL
        ");
        $stmt->execute();
        $churned = $stmt->fetchAll();

        echo "\n--- Churned Physicians (No orders 90+ days) ---\n";
        echo "Found " . count($churned) . " churned physicians\n";

        $sent = 0;
        foreach ($churned as $physician) {
            // Send reactivation offer
            // Log to HubSpot
            if ($physician['hubspot_contact_id'] && !$this->dry_run) {
                $note = "❌ Physician marked as CHURNED\n\n";
                $note .= "Last order: " . ($physician['last_order_date'] ?: 'Never') . "\n";
                $note .= "Reactivation campaign sent with special offer.";

                $this->hubspot->logNote($physician['hubspot_contact_id'], $note);

                // Update deal stage to churned
                if ($physician['hubspot_deal_id']) {
                    $this->hubspot->updateDealStage($physician['hubspot_deal_id'], 'churned');
                }
            }

            $sent++;
        }

        return $sent;
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Send email and sync to HubSpot
     */
    private function sendEmailWithHubSpotSync($lead, $template_key, $label) {
        $template_id = $this->templates[$template_key] ?? null;

        if (!$template_id) {
            echo "✗ Template not found: $template_key\n";
            return false;
        }

        if ($this->dry_run) {
            echo "○ [DRY RUN] Would send $label to: {$lead['email']} ({$lead['physician_name']})\n";
            return true;
        }

        echo "→ Sending $label to: {$lead['email']} ({$lead['physician_name']})... ";

        $variables = [
            'physician_name' => $lead['physician_name'],
            'practice_name' => $lead['practice_name'],
            'city' => $lead['city'],
            'state' => $lead['state'],
            'specialty' => $lead['specialty'],
            'rep_name' => 'Parker Lee',
            'demo_link' => DEMO_URL
        ];

        // Send via SendGrid
        $result = $this->mailer->sendDynamicTemplate(
            $template_id,
            $lead['email'],
            $lead['physician_name'],
            $variables,
            ['lead_id' => $lead['id'], 'sequence' => $template_key]
        );

        if ($result['success']) {
            echo "✓\n";

            // Log to HubSpot
            if ($lead['hubspot_contact_id']) {
                $this->hubspot->logEmail($lead['hubspot_contact_id'], [
                    'sent_at' => date('Y-m-d H:i:s'),
                    'subject' => $label,
                    'body' => "Automated email sent via sales agent: $label",
                    'from_email' => 'sales@collagendirect.health',
                    'to_email' => $lead['email']
                ]);
            }

            return true;
        } else {
            echo "✗ Error: {$result['error']}\n";
            return false;
        }
    }
}

// Run if executed directly (for cron job)
if (php_sapi_name() === 'cli') {
    $dry_run = in_array('--dry-run', $argv ?? []);
    $automation = new CompleteAutomation($pdo, $dry_run);
    $automation->run();
}
?>
