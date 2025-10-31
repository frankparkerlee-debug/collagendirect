<?php
/**
 * SendGrid Email Integration for Sales Outreach Agent
 *
 * This class handles all email sending via SendGrid API
 */

class SendGridMailer {
    private $api_key;
    private $from_email;
    private $from_name;

    public function __construct() {
        $this->api_key = SENDGRID_API_KEY;
        $this->from_email = SENDGRID_FROM_EMAIL;
        $this->from_name = SENDGRID_FROM_NAME;
    }

    /**
     * Send a single email
     *
     * @param array $params Array with keys: to_email, to_name, subject, html_content, text_content, template_id (optional)
     * @return array Response with success status and message_id
     */
    public function sendEmail($params) {
        $to_email = $params['to_email'];
        $to_name = $params['to_name'] ?? '';
        $subject = $params['subject'];
        $html_content = $params['html_content'];
        $text_content = $params['text_content'] ?? strip_tags($html_content);

        // Build email payload
        $email_data = [
            'personalizations' => [
                [
                    'to' => [
                        [
                            'email' => $to_email,
                            'name' => $to_name
                        ]
                    ],
                    'subject' => $subject
                ]
            ],
            'from' => [
                'email' => $this->from_email,
                'name' => $this->from_name
            ],
            'content' => [
                [
                    'type' => 'text/plain',
                    'value' => $text_content
                ],
                [
                    'type' => 'text/html',
                    'value' => $html_content
                ]
            ],
            'tracking_settings' => [
                'click_tracking' => [
                    'enable' => SENDGRID_ENABLE_CLICK_TRACKING,
                    'enable_text' => false
                ],
                'open_tracking' => [
                    'enable' => SENDGRID_ENABLE_TRACKING
                ]
            ],
            'categories' => ['sales_outreach']
        ];

        // Add custom args for tracking
        if (isset($params['lead_id'])) {
            $email_data['custom_args'] = [
                'lead_id' => (string)$params['lead_id'],
                'campaign_id' => (string)($params['campaign_id'] ?? 0)
            ];
        }

        // Send via SendGrid API
        $response = $this->sendRequest($email_data);

        return $response;
    }

    /**
     * Send bulk emails (batch)
     *
     * @param array $emails Array of email parameter arrays
     * @return array Results for each email
     */
    public function sendBulkEmails($emails) {
        $results = [];
        $batch_size = 1000; // SendGrid max batch size

        foreach (array_chunk($emails, $batch_size) as $batch) {
            foreach ($batch as $email_params) {
                $results[] = $this->sendEmail($email_params);

                // Rate limiting: sleep 10ms between emails to stay under 100/hour
                usleep(36000); // 36ms = max 100 emails/hour
            }
        }

        return $results;
    }

    /**
     * Send email from template
     *
     * @param int $template_id Database template ID
     * @param array $lead Lead data for personalization
     * @param array $custom_vars Additional variables
     * @return array Response
     */
    public function sendFromTemplate($template_id, $lead, $custom_vars = []) {
        // Fetch template from database
        $template = $this->getEmailTemplate($template_id);

        if (!$template) {
            return ['success' => false, 'error' => 'Template not found'];
        }

        // Merge personalization variables
        $variables = array_merge([
            'physician_name' => $lead['physician_name'] ?? 'Doctor',
            'practice_name' => $lead['practice_name'],
            'city' => $lead['city'] ?? '',
            'specialty' => $lead['specialty'] ?? '',
            'rep_name' => $_SESSION['admin_user'] ?? 'CollagenDirect Team',
            'demo_link' => DEMO_URL
        ], $custom_vars);

        // Replace template variables
        $subject = $this->replaceVariables($template['subject_line'], $variables);
        $html_content = $this->replaceVariables($template['body_html'], $variables);
        $text_content = $this->replaceVariables($template['body_text'], $variables);

        // Send email
        $response = $this->sendEmail([
            'to_email' => $lead['email'],
            'to_name' => $lead['physician_name'] ?? $lead['practice_name'],
            'subject' => $subject,
            'html_content' => $html_content,
            'text_content' => $text_content,
            'lead_id' => $lead['id'],
            'campaign_id' => $custom_vars['campaign_id'] ?? null
        ]);

        // Log outreach if successful
        if ($response['success']) {
            $this->logOutreach([
                'lead_id' => $lead['id'],
                'campaign_id' => $custom_vars['campaign_id'] ?? null,
                'outreach_type' => 'email',
                'subject' => $subject,
                'message' => $text_content,
                'sent_by' => $_SESSION['admin_user'] ?? 'system'
            ]);

            // Update template metrics
            $this->updateTemplateMetrics($template_id);
        }

        return $response;
    }

    /**
     * Replace template variables with actual values
     */
    private function replaceVariables($content, $variables) {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        return $content;
    }

    /**
     * Send request to SendGrid API
     */
    private function sendRequest($data) {
        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headers = curl_getinfo($ch);

        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            // Get X-Message-Id from response headers if available
            preg_match('/X-Message-Id: (.+)/', $response, $matches);
            $message_id = $matches[1] ?? null;

            return [
                'success' => true,
                'message_id' => $message_id,
                'http_code' => $http_code
            ];
        } else {
            $error = json_decode($response, true);
            return [
                'success' => false,
                'error' => $error['errors'][0]['message'] ?? 'Unknown error',
                'http_code' => $http_code
            ];
        }
    }

    /**
     * Get email template from database
     */
    private function getEmailTemplate($template_id) {
        global $pdo; // Assumes PDO connection available

        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ? AND is_active = 1");
        $stmt->execute([$template_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Log outreach to database
     */
    private function logOutreach($data) {
        global $pdo;

        $stmt = $pdo->prepare("
            INSERT INTO outreach_log
            (lead_id, campaign_id, outreach_type, subject, message, sent_by, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $data['lead_id'],
            $data['campaign_id'],
            $data['outreach_type'],
            $data['subject'],
            $data['message'],
            $data['sent_by']
        ]);

        // Update lead's last_contacted_at
        $stmt = $pdo->prepare("UPDATE leads SET last_contacted_at = NOW(), status = 'contacted' WHERE id = ?");
        $stmt->execute([$data['lead_id']]);
    }

    /**
     * Update template metrics
     */
    private function updateTemplateMetrics($template_id) {
        global $pdo;

        $stmt = $pdo->prepare("UPDATE email_templates SET times_sent = times_sent + 1 WHERE id = ?");
        $stmt->execute([$template_id]);
    }

    /**
     * Process SendGrid webhook events (opens, clicks, bounces)
     */
    public function processWebhook($event_data) {
        global $pdo;

        foreach ($event_data as $event) {
            $event_type = $event['event'];
            $lead_id = $event['lead_id'] ?? null;
            $timestamp = $event['timestamp'];

            if (!$lead_id) continue;

            // Update outreach_log based on event type
            switch ($event_type) {
                case 'open':
                    $stmt = $pdo->prepare("
                        UPDATE outreach_log
                        SET opened_at = FROM_UNIXTIME(?)
                        WHERE lead_id = ? AND outreach_type = 'email'
                        ORDER BY sent_at DESC LIMIT 1
                    ");
                    $stmt->execute([$timestamp, $lead_id]);
                    break;

                case 'click':
                    $stmt = $pdo->prepare("
                        UPDATE outreach_log
                        SET clicked_at = FROM_UNIXTIME(?)
                        WHERE lead_id = ? AND outreach_type = 'email'
                        ORDER BY sent_at DESC LIMIT 1
                    ");
                    $stmt->execute([$timestamp, $lead_id]);
                    break;

                case 'bounce':
                case 'dropped':
                    // Mark lead email as invalid
                    $stmt = $pdo->prepare("UPDATE leads SET notes = CONCAT(notes, '\n[BOUNCED EMAIL] ') WHERE id = ?");
                    $stmt->execute([$lead_id]);
                    break;

                case 'unsubscribe':
                    // Move to do not contact
                    $stmt = $pdo->prepare("UPDATE leads SET status = 'do_not_contact' WHERE id = ?");
                    $stmt->execute([$lead_id]);
                    break;
            }
        }

        return ['success' => true, 'processed' => count($event_data)];
    }
}

/**
 * Example Usage:
 *
 * // Initialize mailer
 * $mailer = new SendGridMailer();
 *
 * // Send single email
 * $result = $mailer->sendEmail([
 *     'to_email' => 'doctor@practice.com',
 *     'to_name' => 'Dr. Smith',
 *     'subject' => 'Save Time on Wound Care Orders',
 *     'html_content' => '<h1>Hello Dr. Smith</h1>...',
 *     'text_content' => 'Hello Dr. Smith...',
 *     'lead_id' => 123
 * ]);
 *
 * // Send from template
 * $result = $mailer->sendFromTemplate(
 *     1, // template_id
 *     $lead_data, // lead array
 *     ['campaign_id' => 5] // custom vars
 * );
 */
?>
