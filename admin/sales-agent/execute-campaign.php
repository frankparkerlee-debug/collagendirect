<?php
/**
 * Campaign Execution Script
 *
 * This script actually sends emails to targeted leads using SendGrid
 * Called when a campaign is launched from create-campaign.php
 */

session_start();
require_once('config.php');
require_once('sendgrid-integration.php');

// Check authentication
if (!isset($_SESSION['admin_logged_in'])) {
    die('Unauthorized');
}

// Get campaign parameters from POST
$campaign_name = $_POST['campaign_name'] ?? '';
$template_id = (int)($_POST['template_id'] ?? 0);
$target_status = $_POST['target_status'] ?? '';
$target_specialty = $_POST['target_specialty'] ?? '';
$target_state = $_POST['target_state'] ?? '';
$min_volume = (int)($_POST['min_volume'] ?? 0);

if (!$campaign_name || !$template_id) {
    $_SESSION['error'] = 'Campaign name and template are required';
    header('Location: create-campaign.php');
    exit;
}

// Connect to database
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database connection failed';
    header('Location: create-campaign.php');
    exit;
}

// Create campaign record
$stmt = $pdo->prepare("
    INSERT INTO outreach_campaigns
    (campaign_name, campaign_type, subject_line, message_template,
     target_specialty, target_state, min_volume, status, created_by, start_date)
    VALUES (?, 'email', '', '', ?, ?, ?, 'active', ?, NOW())
");

$stmt->execute([
    $campaign_name,
    $target_specialty,
    $target_state,
    $min_volume,
    $_SESSION['admin_user']
]);

$campaign_id = $pdo->lastInsertId();

// Build query to fetch targeted leads
$sql = "SELECT * FROM leads WHERE 1=1 AND email IS NOT NULL AND email != '' AND status != 'do_not_contact'";
$params = [];

if ($target_status) {
    $sql .= " AND status = ?";
    $params[] = $target_status;
}

if ($target_specialty) {
    $sql .= " AND specialty = ?";
    $params[] = $target_specialty;
}

if ($target_state) {
    $sql .= " AND state = ?";
    $params[] = $target_state;
}

if ($min_volume > 0) {
    $sql .= " AND estimated_monthly_volume >= ?";
    $params[] = $min_volume;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize SendGrid mailer
$mailer = new SendGridMailer();

// Track results
$results = [
    'total' => count($leads),
    'sent' => 0,
    'failed' => 0,
    'errors' => []
];

// Send emails to each lead
foreach ($leads as $lead) {
    try {
        // Send from template with personalization
        $response = $mailer->sendFromTemplate(
            $template_id,
            $lead,
            ['campaign_id' => $campaign_id]
        );

        if ($response['success']) {
            $results['sent']++;

            // Update campaign metrics
            $pdo->prepare("UPDATE outreach_campaigns SET total_sent = total_sent + 1 WHERE id = ?")
                ->execute([$campaign_id]);
        } else {
            $results['failed']++;
            $results['errors'][] = "Lead {$lead['id']}: {$response['error']}";
        }

        // Rate limiting: 36ms between emails = 100 emails/hour (SendGrid free tier)
        usleep(36000);

    } catch (Exception $e) {
        $results['failed']++;
        $results['errors'][] = "Lead {$lead['id']}: " . $e->getMessage();
    }
}

// Update campaign with final stats
$pdo->prepare("
    UPDATE outreach_campaigns
    SET total_sent = ?, status = 'completed', end_date = NOW()
    WHERE id = ?
")->execute([$results['sent'], $campaign_id]);

// Store results in session to display on next page
$_SESSION['campaign_results'] = $results;
$_SESSION['campaign_name'] = $campaign_name;

// Redirect back to campaigns page
header('Location: campaigns.php?success=1');
exit;
?>
