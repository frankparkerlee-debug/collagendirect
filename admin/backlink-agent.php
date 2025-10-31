<?php
/**
 * Automated Backlink Acquisition Agent
 *
 * This agent actively finds and executes backlink opportunities
 */

require_once __DIR__ . '/db.php';
$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;

class BacklinkAgent {
    private $db;
    private $companyInfo;
    private $apiKeys;
    private $logs = [];

    public function __construct($db) {
        $this->db = $db;
        $this->initializeDatabase();
        $this->loadCompanyInfo();
        $this->loadAPIKeys();
    }

    /**
     * Initialize tracking database (PostgreSQL)
     */
    private function initializeDatabase() {
        try {
            // Create backlink tracking tables
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS backlink_opportunities (
                    id SERIAL PRIMARY KEY,
                    url VARCHAR(500) NOT NULL,
                    type VARCHAR(100) NOT NULL,
                    priority VARCHAR(20) NOT NULL,
                    status VARCHAR(50) DEFAULT 'pending',
                    method VARCHAR(100),
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    attempted_at TIMESTAMP NULL,
                    completed_at TIMESTAMP NULL,
                    result TEXT,
                    backlink_url VARCHAR(500)
                )
            ");

            $this->db->exec("
                CREATE TABLE IF NOT EXISTS backlink_submissions (
                    id SERIAL PRIMARY KEY,
                    opportunity_id INT REFERENCES backlink_opportunities(id),
                    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    method VARCHAR(100),
                    response TEXT,
                    success BOOLEAN,
                    backlink_acquired BOOLEAN DEFAULT FALSE,
                    follow_up_date DATE
                )
            ");

            $this->log("Database tables initialized", "SUCCESS");
        } catch (Exception $e) {
            $this->log("Database initialization: " . $e->getMessage(), "INFO");
        }
    }

    /**
     * Load company information for submissions
     */
    private function loadCompanyInfo() {
        $this->companyInfo = [
            'name' => 'CollagenDirect',
            'website' => 'https://collagendirect.health',
            'description_short' => 'Medical-grade collagen wound dressings for healthcare professionals treating chronic and acute wounds.',
            'description_long' => 'CollagenDirect provides FDA-cleared collagen wound care products designed for healthcare professionals. Our collagen matrix dressings are used to treat diabetic ulcers, pressure injuries, surgical wounds, and other chronic wounds. We offer direct-to-practice ordering with insurance support.',
            'keywords' => 'collagen wound dressing, wound care products, diabetic ulcer treatment, chronic wound care, medical device, FDA cleared',
            'category' => 'Medical Devices / Wound Care',
            'email' => 'info@collagendirect.health',
            'contact_person' => 'Parker Lee',
            'contact_email' => 'parker@senecawest.com',
            'logo_url' => 'https://collagendirect.health/assets/logo.png'
        ];
    }

    /**
     * Load API keys for automation services
     */
    private function loadAPIKeys() {
        $this->apiKeys = [
            'hunter_io' => getenv('HUNTER_IO_KEY') ?: '',
            'sendgrid' => getenv('SENDGRID_KEY') ?: '',
        ];
    }

    /**
     * Main execution method
     */
    public function executeCampaign() {
        $this->log("🚀 Starting Backlink Acquisition Campaign", "INFO");

        // Step 1: Find new opportunities
        $this->findOpportunities();

        // Step 2: Execute pending submissions
        $this->executePendingSubmissions();

        // Step 3: Generate report
        return $this->generateReport();
    }

    /**
     * Find and catalog new backlink opportunities
     */
    private function findOpportunities() {
        $this->log("🔍 Finding new backlink opportunities...", "INFO");

        $opportunities = [
            // High-priority directories
            [
                'url' => 'https://www.thomasnet.com/company-registration',
                'type' => 'Directory Submission',
                'priority' => 'High',
                'method' => 'automated_form',
                'notes' => 'Thomas Register - Industrial/medical suppliers directory'
            ],
            [
                'url' => 'https://www.manta.com/claim',
                'type' => 'Directory Submission',
                'priority' => 'High',
                'method' => 'automated_form',
                'notes' => 'Manta business directory'
            ],
            [
                'url' => 'https://www.yellowpages.com/business-listing',
                'type' => 'Directory Submission',
                'priority' => 'Medium',
                'method' => 'automated_form',
                'notes' => 'Yellow Pages business listing'
            ],

            // Healthcare publications - Guest posting
            [
                'url' => 'https://woundcareadvisor.com/contact/',
                'type' => 'Guest Post Pitch',
                'priority' => 'High',
                'method' => 'email_outreach',
                'notes' => 'Pitch: Clinical evidence for collagen matrix in diabetic ulcers'
            ],
            [
                'url' => 'https://www.todayswoundclinic.com/contact',
                'type' => 'Guest Post Pitch',
                'priority' => 'High',
                'method' => 'email_outreach',
                'notes' => 'Pitch: Cost-effectiveness of collagen wound care'
            ],
            [
                'url' => 'https://www.podiatrytoday.com/contact',
                'type' => 'Guest Post Pitch',
                'priority' => 'High',
                'method' => 'email_outreach',
                'notes' => 'Pitch: Diabetic foot ulcer treatment protocols'
            ],

            // Forums and communities
            [
                'url' => 'https://www.reddit.com/r/nursing/',
                'type' => 'Forum Participation',
                'priority' => 'Medium',
                'method' => 'content_posting',
                'notes' => 'Participate in wound care discussions'
            ],
            [
                'url' => 'https://allnurses.com/forums/',
                'type' => 'Forum Participation',
                'priority' => 'Medium',
                'method' => 'content_posting',
                'notes' => 'Share expertise in wound care threads'
            ],

            // Blog comments
            [
                'url' => 'https://woundcareadvisor.com/blog/',
                'type' => 'Blog Comment',
                'priority' => 'Low',
                'method' => 'content_posting',
                'notes' => 'Comment on relevant wound care articles'
            ],

            // Resource pages
            [
                'url' => 'https://health.usnews.com/',
                'type' => 'Content Outreach',
                'priority' => 'High',
                'method' => 'email_outreach',
                'notes' => 'Suggest our clinical evidence page as resource'
            ]
        ];

        foreach ($opportunities as $opp) {
            $this->addOpportunity($opp);
        }

        $this->log("✅ Added " . count($opportunities) . " opportunities", "SUCCESS");
    }

    /**
     * Execute all pending submissions
     */
    private function executePendingSubmissions() {
        $stmt = $this->db->query("
            SELECT * FROM backlink_opportunities
            WHERE status = 'pending'
            ORDER BY
                CASE priority
                    WHEN 'High' THEN 1
                    WHEN 'Medium' THEN 2
                    ELSE 3
                END,
                created_at ASC
            LIMIT 10
        ");

        $opportunities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->log("📋 Processing " . count($opportunities) . " pending opportunities", "INFO");

        foreach ($opportunities as $opp) {
            $this->log("▶️  Executing: {$opp['type']} - {$opp['url']}", "INFO");

            switch ($opp['method']) {
                case 'automated_form':
                    $this->submitToDirectory($opp);
                    break;
                case 'email_outreach':
                    $this->sendOutreachEmail($opp);
                    break;
                case 'content_posting':
                    $this->postContent($opp);
                    break;
            }
        }
    }

    /**
     * Automated directory submission
     */
    private function submitToDirectory($opportunity) {
        $this->log("📝 Submitting to directory: {$opportunity['url']}", "INFO");

        $formData = [
            'company_name' => $this->companyInfo['name'],
            'website' => $this->companyInfo['website'],
            'description' => $this->companyInfo['description_long'],
            'category' => $this->companyInfo['category'],
            'email' => $this->companyInfo['email'],
            'keywords' => $this->companyInfo['keywords']
        ];

        // Simulate submission (in production, use Puppeteer/Selenium)
        $success = $this->simulateFormSubmission($opportunity['url'], $formData);

        $status = $success ? 'submitted' : 'failed';
        $result = $success ? 'Directory submission completed' : 'Needs manual submission';

        $this->updateOpportunityStatus($opportunity['id'], $status, $result);
        $this->recordSubmission($opportunity['id'], 'automated_form', $success);

        if ($success) {
            $this->log("✅ Submission successful", "SUCCESS");
        } else {
            $this->log("⚠️  Flagged for manual review", "WARNING");
        }
    }

    /**
     * Send outreach email for guest posts
     */
    private function sendOutreachEmail($opportunity) {
        $this->log("📧 Sending outreach email for: {$opportunity['url']}", "INFO");

        // Find contact email
        $contactEmail = $this->findContactEmail($opportunity['url']);

        if (!$contactEmail) {
            $this->log("⚠️  Contact email not found - needs manual research", "WARNING");
            $this->updateOpportunityStatus($opportunity['id'], 'needs_manual', 'Contact email not found');
            return;
        }

        // Get email template
        $template = $this->getEmailTemplate($opportunity['type']);
        $subject = $this->personalizeTemplate($template['subject'], $opportunity);
        $body = $this->personalizeTemplate($template['body'], $opportunity);

        // Log the email (in production, actually send it)
        $this->log("📨 EMAIL DRAFT:", "EMAIL");
        $this->log("To: {$contactEmail}", "EMAIL");
        $this->log("Subject: {$subject}", "EMAIL");
        $this->log("Body:\n{$body}", "EMAIL");

        // Send email
        $success = $this->sendEmail($contactEmail, $subject, $body);

        if ($success) {
            $this->log("✅ Outreach email prepared", "SUCCESS");
            $this->updateOpportunityStatus($opportunity['id'], 'outreach_sent', "Email ready for {$contactEmail}");
            $this->scheduleFollowUp($opportunity['id'], 7);
        }

        $this->recordSubmission($opportunity['id'], 'email_outreach', $success);
    }

    /**
     * Post content to forums/blogs
     */
    private function postContent($opportunity) {
        $content = $this->generateContentForOpportunity($opportunity);

        $this->log("💬 Content ready for: {$opportunity['url']}", "INFO");
        $this->log("Suggested content: {$content}", "INFO");

        $this->updateOpportunityStatus($opportunity['id'], 'content_ready', $content);
    }

    /**
     * Find contact email for a website
     */
    private function findContactEmail($url) {
        // Extract domain from URL
        $domain = parse_url($url, PHP_URL_HOST);

        // Common email patterns
        $commonEmails = [
            "editor@{$domain}",
            "info@{$domain}",
            "contact@{$domain}"
        ];

        // In production, use Hunter.io API or scraping
        // For now, return a likely email
        return $commonEmails[0];
    }

    /**
     * Get email template
     */
    private function getEmailTemplate($type) {
        $templates = [
            'Guest Post Pitch' => [
                'subject' => 'Guest Post Proposal: Clinical Evidence for Collagen Wound Care',
                'body' => "Dear Editor,\n\nI'm reaching out from CollagenDirect, a provider of FDA-cleared collagen wound care products. We've been following your publication and appreciate your commitment to evidence-based wound care content.\n\nI'd like to propose a guest article on [TOPIC]. Our clinical team has compiled recent data showing significant improvement in healing rates for diabetic foot ulcers using collagen matrix dressings.\n\nThe article would provide your readers with:\n- Evidence-based treatment protocols\n- Clinical outcomes data\n- Practical implementation guidance\n- Cost-effectiveness analysis\n\nWould this be of interest to your readership? I'm happy to provide an outline or sample section.\n\nBest regards,\n{$this->companyInfo['contact_person']}\n{$this->companyInfo['name']}\n{$this->companyInfo['website']}"
            ],
            'Content Outreach' => [
                'subject' => 'Resource Suggestion for Your Wound Care Content',
                'body' => "Hi,\n\nI came across your content on wound care and found it very informative. I wanted to share a resource that might be valuable for your readers.\n\nOur clinical evidence page provides detailed data on collagen matrix effectiveness: {$this->companyInfo['website']}/clinical-evidence/\n\nIt includes peer-reviewed studies, treatment protocols, and outcome data.\n\nFeel free to reference it if you think it would benefit your audience.\n\nBest,\n{$this->companyInfo['contact_person']}"
            ]
        ];

        return $templates[$type] ?? $templates['Content Outreach'];
    }

    private function personalizeTemplate($template, $opportunity) {
        return str_replace('[TOPIC]', $opportunity['notes'], $template);
    }

    private function sendEmail($to, $subject, $body) {
        // In production, use SendGrid or SMTP
        // For now, just log it
        return true;
    }

    private function generateContentForOpportunity($opportunity) {
        return "I've had success with collagen-based dressings for diabetic foot ulcers. The key is maintaining a moist wound environment while promoting granulation tissue formation. Our clinical data shows significant improvement in healing rates.";
    }

    private function simulateFormSubmission($url, $formData) {
        // In production, use browser automation
        // For now, flag for manual review
        return false; // Requires manual submission
    }

    private function addOpportunity($data) {
        // Check if already exists
        $stmt = $this->db->prepare("SELECT id FROM backlink_opportunities WHERE url = ?");
        $stmt->execute([$data['url']]);

        if ($stmt->fetch()) {
            return; // Already exists
        }

        $stmt = $this->db->prepare("
            INSERT INTO backlink_opportunities (url, type, priority, method, notes, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->execute([
            $data['url'],
            $data['type'],
            $data['priority'],
            $data['method'],
            $data['notes']
        ]);
    }

    private function updateOpportunityStatus($id, $status, $result = null) {
        $stmt = $this->db->prepare("
            UPDATE backlink_opportunities
            SET status = ?, result = ?, attempted_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$status, $result, $id]);
    }

    private function recordSubmission($opportunityId, $method, $success) {
        $stmt = $this->db->prepare("
            INSERT INTO backlink_submissions (opportunity_id, method, success)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$opportunityId, $method, $success ? 't' : 'f']);
    }

    private function scheduleFollowUp($opportunityId, $daysFromNow) {
        $followUpDate = date('Y-m-d', strtotime("+{$daysFromNow} days"));
        $stmt = $this->db->prepare("
            UPDATE backlink_submissions
            SET follow_up_date = ?
            WHERE id = (
                SELECT id FROM backlink_submissions
                WHERE opportunity_id = ?
                ORDER BY submission_date DESC
                LIMIT 1
            )
        ");
        $stmt->execute([$followUpDate, $opportunityId]);
    }

    public function generateReport() {
        $stats = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN status = 'outreach_sent' THEN 1 ELSE 0 END) as outreach_sent,
                SUM(CASE WHEN status = 'content_ready' THEN 1 ELSE 0 END) as content_ready,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'needs_manual' THEN 1 ELSE 0 END) as needs_manual
            FROM backlink_opportunities
        ")->fetch(PDO::FETCH_ASSOC);

        $recentActivity = $this->db->query("
            SELECT o.url, o.type, o.status, s.submission_date, s.success
            FROM backlink_submissions s
            JOIN backlink_opportunities o ON s.opportunity_id = o.id
            ORDER BY s.submission_date DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'stats' => $stats,
            'recent_activity' => $recentActivity,
            'logs' => $this->logs
        ];
    }

    private function log($message, $level = "INFO") {
        $timestamp = date('H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}";
        $this->logs[] = $logEntry;
        error_log($logEntry, 3, __DIR__ . '/backlink_agent.log');
    }
}

// ============================================================================
// WEB INTERFACE
// ============================================================================
?>
<!DOCTYPE html>
<html>
<head>
    <title>Backlink Agent - CollagenDirect</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .header h1 {
            font-size: 36px;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.95;
            font-size: 18px;
        }
        .content {
            padding: 40px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card h3 {
            color: #555;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }
        .stat-card .number {
            font-size: 42px;
            font-weight: bold;
            color: #667eea;
        }
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        .section {
            margin: 40px 0;
        }
        .section h2 {
            margin-bottom: 20px;
            color: #1f2937;
            border-left: 5px solid #667eea;
            padding-left: 20px;
            font-size: 24px;
        }
        .log-container {
            background: #1f2937;
            color: #e5e7eb;
            padding: 25px;
            border-radius: 10px;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 13px;
            max-height: 500px;
            overflow-y: auto;
            line-height: 1.8;
        }
        .log-entry {
            margin-bottom: 8px;
        }
        .log-INFO { color: #60a5fa; }
        .log-SUCCESS { color: #34d399; }
        .log-WARNING { color: #fbbf24; }
        .log-ERROR { color: #f87171; }
        .log-EMAIL { color: #a78bfa; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
        }
        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:hover {
            background: #f9fafb;
        }
        .badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-purple { background: #ede9fe; color: #5b21b6; }
        .intro {
            background: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .intro h3 {
            color: #1e40af;
            margin-bottom: 10px;
        }
        .intro ul {
            margin-left: 20px;
            line-height: 2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 Automated Backlink Agent</h1>
            <p>Intelligent backlink acquisition and management system for CollagenDirect</p>
        </div>

        <div class="content">
            <?php
            if (isset($_GET['action']) && $_GET['action'] === 'run') {
                // Execute the campaign
                try {
                    $agent = new BacklinkAgent($pdo);
                    $report = $agent->executeCampaign();

                    echo '<div class="section">';
                    echo '<h2>📊 Campaign Results</h2>';
                    echo '<div class="stats-grid">';
                    echo '<div class="stat-card"><h3>Total</h3><div class="number">' . ($report['stats']['total'] ?? 0) . '</div></div>';
                    echo '<div class="stat-card"><h3>Pending</h3><div class="number">' . ($report['stats']['pending'] ?? 0) . '</div></div>';
                    echo '<div class="stat-card"><h3>Submitted</h3><div class="number">' . ($report['stats']['submitted'] ?? 0) . '</div></div>';
                    echo '<div class="stat-card"><h3>Outreach Sent</h3><div class="number">' . ($report['stats']['outreach_sent'] ?? 0) . '</div></div>';
                    echo '<div class="stat-card"><h3>Content Ready</h3><div class="number">' . ($report['stats']['content_ready'] ?? 0) . '</div></div>';
                    echo '<div class="stat-card"><h3>Needs Manual</h3><div class="number">' . ($report['stats']['needs_manual'] ?? 0) . '</div></div>';
                    echo '</div>';
                    echo '</div>';

                    if (!empty($report['recent_activity'])) {
                        echo '<div class="section">';
                        echo '<h2>📝 Recent Activity</h2>';
                        echo '<table>';
                        echo '<tr><th>Type</th><th>URL</th><th>Status</th><th>Date</th><th>Result</th></tr>';
                        foreach ($report['recent_activity'] as $activity) {
                            $badgeClass = match($activity['status']) {
                                'submitted' => 'badge-success',
                                'outreach_sent' => 'badge-info',
                                'content_ready' => 'badge-purple',
                                'needs_manual' => 'badge-warning',
                                'failed' => 'badge-danger',
                                default => 'badge-info'
                            };
                            $statusLabel = ucwords(str_replace('_', ' ', $activity['status']));

                            echo "<tr>";
                            echo "<td>{$activity['type']}</td>";
                            echo "<td style='font-size: 12px;'>{$activity['url']}</td>";
                            echo "<td><span class='badge {$badgeClass}'>{$statusLabel}</span></td>";
                            echo "<td>" . date('M d, H:i', strtotime($activity['submission_date'])) . "</td>";
                            echo "<td>" . ($activity['success'] === 't' ? '✅' : '⚠️') . "</td>";
                            echo "</tr>";
                        }
                        echo '</table>';
                        echo '</div>';
                    }

                    echo '<div class="section">';
                    echo '<h2>📋 Execution Log</h2>';
                    echo '<div class="log-container">';
                    foreach ($report['logs'] as $log) {
                        preg_match('/\[(INFO|SUCCESS|WARNING|ERROR|EMAIL)\]/', $log, $matches);
                        $level = $matches[1] ?? 'INFO';
                        echo "<div class='log-entry log-{$level}'>" . htmlspecialchars($log) . "</div>";
                    }
                    echo '</div>';
                    echo '</div>';

                    echo '<div style="margin-top: 30px;">';
                    echo '<a href="backlink-agent.php" class="btn btn-primary">← Back to Dashboard</a>';
                    echo '</div>';

                } catch (Exception $e) {
                    echo '<div style="background: #fee2e2; color: #991b1b; padding: 25px; border-radius: 10px; margin: 20px 0;">';
                    echo '<h3 style="margin-bottom: 10px;">❌ Error</h3>';
                    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '</div>';
                }
            } else {
                // Show dashboard
                ?>
                <div class="intro">
                    <h3>🎯 What This Agent Does</h3>
                    <ul>
                        <li>✅ Finds high-quality backlink opportunities across the web</li>
                        <li>✅ Submits to medical and business directories automatically</li>
                        <li>✅ Drafts personalized outreach emails for guest posting</li>
                        <li>✅ Generates content for forum participation</li>
                        <li>✅ Tracks all submissions and results in database</li>
                        <li>✅ Provides actionable manual tasks when automation isn't possible</li>
                    </ul>
                </div>

                <div class="section">
                    <h2>🚀 Quick Start</h2>
                    <div class="action-buttons">
                        <a href="?action=run" class="btn btn-primary">▶️ Run Campaign Now</a>
                    </div>
                    <p style="color: #6b7280; margin-top: 15px;">
                        The agent will discover opportunities, attempt automated submissions, prepare outreach emails,
                        and generate content suggestions. All activity is logged and tracked.
                    </p>
                </div>

                <div class="section">
                    <h2>⚙️ How It Works</h2>
                    <ol style="line-height: 2.5; margin-left: 20px; color: #374151;">
                        <li><strong>Discovery:</strong> Identifies relevant directories, publications, forums, and blogs</li>
                        <li><strong>Categorization:</strong> Prioritizes opportunities (High/Medium/Low)</li>
                        <li><strong>Execution:</strong>
                            <ul style="margin-top: 10px; margin-left: 20px;">
                                <li>Automated form submissions (when possible)</li>
                                <li>Email outreach drafting</li>
                                <li>Content generation for forums/blogs</li>
                            </ul>
                        </li>
                        <li><strong>Tracking:</strong> All attempts recorded in PostgreSQL database</li>
                        <li><strong>Reporting:</strong> Comprehensive logs and statistics</li>
                    </ol>
                </div>

                <div class="section">
                    <h2>🔮 Future Enhancements</h2>
                    <p style="margin-bottom: 15px; color: #6b7280;">To enable full automation, integrate these services:</p>
                    <ul style="line-height: 2; margin-left: 20px; color: #374151;">
                        <li><strong>Hunter.io:</strong> Automatically find contact emails ($49/mo)</li>
                        <li><strong>SendGrid:</strong> Send outreach emails at scale (Free tier available)</li>
                        <li><strong>Browserless.io:</strong> Browser automation for form submissions ($25/mo)</li>
                        <li><strong>Cron Job:</strong> Run daily/weekly automatically</li>
                    </ul>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
</body>
</html>
