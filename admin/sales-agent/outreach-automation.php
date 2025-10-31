<?php
/**
 * Automated Outreach Sequence
 * Runs daily to send scheduled emails based on lead status and timing
 *
 * Sequence:
 * - Day 0: Cold outreach email
 * - Day 3: Follow-up #1 (ROI focus)
 * - Day 7: Follow-up #2 (social proof)
 * - Day 14: Breakup email
 * - Qualified leads: Handoff to account manager
 */

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/sendgrid-integration.php');

class OutreachAutomation {
    private $pdo;
    private $mailer;
    private $dry_run = false;

    // SendGrid template IDs
    private $templates = [
        'cold_outreach' => 'd-ffb45888b631435c9261460d993c6a37',
        'followup_1' => 'd-ffb45888b631435c9261460d993c6a37', // Same as cold but different angle
        'followup_roi' => 'd-6e834e33b85d477e88afb5c38e0e550e', // Save ROI for later
        'breakup' => 'd-53cc9016294241d1864885852e3e0f12'
    ];

    public function __construct($pdo, $dry_run = false) {
        $this->pdo = $pdo;
        $this->mailer = new SendGridMailer();
        $this->dry_run = $dry_run;
    }

    /**
     * Main automation runner - call this daily via cron
     */
    public function run() {
        echo "=== Starting Outreach Automation ===\n";
        echo "Time: " . date('Y-m-d H:i:s') . "\n";
        echo "Dry run: " . ($this->dry_run ? 'YES' : 'NO') . "\n\n";

        $stats = [
            'cold_sent' => 0,
            'followup1_sent' => 0,
            'followup2_sent' => 0,
            'breakup_sent' => 0,
            'qualified' => 0,
            'errors' => 0
        ];

        // Step 1: Send cold outreach to new leads
        $stats['cold_sent'] = $this->sendColdOutreach();

        // Step 2: Send follow-up #1 (Day 3)
        $stats['followup1_sent'] = $this->sendFollowup1();

        // Step 3: Send follow-up #2 (Day 7)
        $stats['followup2_sent'] = $this->sendFollowup2();

        // Step 4: Send breakup email (Day 14)
        $stats['breakup_sent'] = $this->sendBreakupEmail();

        // Step 5: Check for qualified leads and notify account manager
        $stats['qualified'] = $this->checkQualifiedLeads();

        // Step 6: Update lead scores based on engagement
        $this->updateLeadScores();

        echo "\n=== Automation Complete ===\n";
        echo "Cold outreach sent: {$stats['cold_sent']}\n";
        echo "Follow-up #1 sent: {$stats['followup1_sent']}\n";
        echo "Follow-up #2 sent: {$stats['followup2_sent']}\n";
        echo "Breakup emails sent: {$stats['breakup_sent']}\n";
        echo "Qualified leads: {$stats['qualified']}\n";
        echo "=============================\n";

        return $stats;
    }

    /**
     * Send cold outreach to new leads
     */
    private function sendColdOutreach() {
        // Get leads that:
        // - Status = 'new'
        // - Have email
        // - Haven't been contacted yet
        // - Limit to 50 per day (SendGrid free tier)

        $stmt = $this->pdo->prepare("
            SELECT * FROM leads
            WHERE status = 'new'
            AND email IS NOT NULL
            AND last_contacted_at IS NULL
            ORDER BY lead_score DESC, created_at ASC
            LIMIT 50
        ");
        $stmt->execute();
        $leads = $stmt->fetchAll();

        echo "\n--- Cold Outreach ---\n";
        echo "Found " . count($leads) . " new leads to contact\n";

        $sent = 0;
        foreach ($leads as $lead) {
            if ($this->sendEmail($lead, 'cold_outreach', 'Cold Outreach')) {
                $sent++;

                if (!$this->dry_run) {
                    // Update lead status
                    $this->pdo->prepare("
                        UPDATE leads
                        SET status = 'contacted',
                            last_contacted_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ")->execute([$lead['id']]);
                }
            }

            // Rate limiting: 36ms between emails = ~100/hour
            usleep(36000);
        }

        return $sent;
    }

    /**
     * Send follow-up #1 (Day 3) - Soft touch, no hard ROI numbers yet
     */
    private function sendFollowup1() {
        // Get leads contacted 3 days ago, no response
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

        echo "\n--- Follow-up #1 (Day 3) ---\n";
        echo "Found " . count($leads) . " leads for follow-up\n";

        $sent = 0;
        foreach ($leads as $lead) {
            // Use same cold template but follow-up context
            if ($this->sendEmail($lead, 'followup_1', 'Follow-up #1 (Soft touch)')) {
                $sent++;

                if (!$this->dry_run) {
                    $this->pdo->prepare("
                        UPDATE leads
                        SET last_contacted_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ")->execute([$lead['id']]);
                }
            }

            usleep(36000);
        }

        return $sent;
    }

    /**
     * Send follow-up #2 (Day 7) - NOW show ROI numbers (trust is building)
     */
    private function sendFollowup2() {
        // Get leads contacted 7 days ago, no response, received 2 emails
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
        echo "Found " . count($leads) . " leads for follow-up\n";

        $sent = 0;
        foreach ($leads as $lead) {
            // NOW we show them the money - they've seen us twice, trust is building
            if ($this->sendEmail($lead, 'followup_roi', 'Follow-up #2 (ROI Numbers)')) {
                $sent++;

                if (!$this->dry_run) {
                    $this->pdo->prepare("
                        UPDATE leads
                        SET last_contacted_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ")->execute([$lead['id']]);
                }
            }

            usleep(36000);
        }

        return $sent;
    }

    /**
     * Send breakup email (Day 14)
     */
    private function sendBreakupEmail() {
        // Get leads contacted 14 days ago, no response
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
            if ($this->sendEmail($lead, 'breakup', 'Breakup Email')) {
                $sent++;

                if (!$this->dry_run) {
                    // Move to nurture status
                    $this->pdo->prepare("
                        UPDATE leads
                        SET status = 'nurture',
                            last_contacted_at = CURRENT_TIMESTAMP,
                            next_followup_date = CURRENT_DATE + INTERVAL '6 months'
                        WHERE id = ?
                    ")->execute([$lead['id']]);
                }
            }

            usleep(36000);
        }

        return $sent;
    }

    /**
     * Send email using SendGrid template
     */
    private function sendEmail($lead, $template_key, $label) {
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
            'rep_name' => 'Parker Lee', // TODO: Get from session or config
            'demo_link' => DEMO_URL
        ];

        try {
            $result = $this->mailer->sendDynamicTemplate(
                $template_id,
                $lead['email'],
                $lead['physician_name'],
                $variables,
                ['lead_id' => $lead['id'], 'sequence' => $template_key]
            );

            if ($result['success']) {
                echo "✓\n";

                // Log outreach
                $this->logOutreach($lead['id'], null, 'email', $label, $template_id);

                return true;
            } else {
                echo "✗ Error: {$result['error']}\n";
                return false;
            }
        } catch (Exception $e) {
            echo "✗ Exception: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Log outreach activity
     */
    private function logOutreach($lead_id, $campaign_id, $type, $subject, $message) {
        $stmt = $this->pdo->prepare("
            INSERT INTO outreach_log (
                lead_id, campaign_id, outreach_type,
                subject, message, sent_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $lead_id,
            $campaign_id,
            $type,
            $subject,
            $message,
            'automation'
        ]);
    }

    /**
     * Check for qualified leads and notify account manager
     */
    private function checkQualifiedLeads() {
        // Leads with score >= 50 or clicked demo link
        $stmt = $this->pdo->prepare("
            SELECT l.*, COUNT(o.id) as email_count,
                   SUM(CASE WHEN o.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens,
                   SUM(CASE WHEN o.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicks
            FROM leads l
            LEFT JOIN outreach_log o ON l.id = o.lead_id
            WHERE l.status IN ('contacted', 'new')
            AND (l.lead_score >= 50 OR o.clicked_at IS NOT NULL)
            GROUP BY l.id
        ");
        $stmt->execute();
        $qualified = $stmt->fetchAll();

        echo "\n--- Qualified Leads Check ---\n";
        echo "Found " . count($qualified) . " qualified leads\n";

        foreach ($qualified as $lead) {
            if (!$this->dry_run) {
                // Update status to qualified
                $this->pdo->prepare("
                    UPDATE leads
                    SET status = 'qualified',
                        priority = 'high'
                    WHERE id = ?
                ")->execute([$lead['id']]);

                // Notify account manager
                $this->notifyAccountManager($lead);
            }

            echo "✓ Qualified: {$lead['physician_name']} (Score: {$lead['lead_score']}, Opens: {$lead['opens']}, Clicks: {$lead['clicks']})\n";
        }

        return count($qualified);
    }

    /**
     * Notify account manager of qualified lead
     */
    private function notifyAccountManager($lead) {
        $to_email = ADMIN_EMAIL;
        $subject = "🎯 Qualified Lead: {$lead['physician_name']} ({$lead['state']})";

        $body = "
            <h2>New Qualified Lead Ready for Handoff</h2>

            <h3>Lead Information</h3>
            <ul>
                <li><strong>Physician:</strong> Dr. {$lead['physician_name']}</li>
                <li><strong>Practice:</strong> {$lead['practice_name']}</li>
                <li><strong>Specialty:</strong> {$lead['specialty']}</li>
                <li><strong>Location:</strong> {$lead['city']}, {$lead['state']}</li>
                <li><strong>Phone:</strong> {$lead['phone']}</li>
                <li><strong>Email:</strong> {$lead['email']}</li>
            </ul>

            <h3>Engagement Summary</h3>
            <ul>
                <li><strong>Lead Score:</strong> {$lead['lead_score']}</li>
                <li><strong>Status:</strong> Qualified</li>
                <li><strong>Estimated Monthly Volume:</strong> {$lead['estimated_monthly_volume']} patients</li>
            </ul>

            <p><a href=\"" . BASE_URL . "/admin/sales-agent/index.php?lead_id={$lead['id']}\">View Full Lead Details →</a></p>

            <hr>
            <p><em>This lead has been automatically qualified by the outreach automation system.</em></p>
        ";

        $this->mailer->sendEmail([
            'to_email' => $to_email,
            'to_name' => 'Account Manager',
            'subject' => $subject,
            'html_content' => $body,
            'text_content' => strip_tags($body)
        ]);
    }

    /**
     * Update lead scores based on engagement
     */
    private function updateLeadScores() {
        echo "\n--- Updating Lead Scores ---\n";

        // Update scores based on email engagement
        $this->pdo->exec("
            UPDATE leads l
            SET lead_score = lead_score +
                (SELECT COALESCE(SUM(
                    CASE WHEN o.opened_at IS NOT NULL THEN 5 ELSE 0 END +
                    CASE WHEN o.clicked_at IS NOT NULL THEN 10 ELSE 0 END +
                    CASE WHEN o.replied_at IS NOT NULL THEN 30 ELSE 0 END
                ), 0)
                FROM outreach_log o
                WHERE o.lead_id = l.id
                AND o.sent_at >= CURRENT_TIMESTAMP - INTERVAL '1 day')
            WHERE EXISTS (
                SELECT 1 FROM outreach_log
                WHERE lead_id = l.id
                AND sent_at >= CURRENT_TIMESTAMP - INTERVAL '1 day'
            )
        ");

        $updated = $this->pdo->query("SELECT ROW_COUNT()")->fetchColumn();
        echo "Updated scores for $updated leads\n";
    }
}

// Run if executed directly (for cron job)
if (php_sapi_name() === 'cli') {
    $dry_run = in_array('--dry-run', $argv ?? []);
    $automation = new OutreachAutomation($pdo, $dry_run);
    $automation->run();
}
?>
