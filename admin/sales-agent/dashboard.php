<?php
/**
 * Sales Agent Dashboard
 * Overview of lead generation, outreach campaigns, and performance metrics
 */

session_start();
require_once(__DIR__ . '/config.php');

// Check authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit;
}

// Get statistics
$stats = [];

// Total leads by status
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM leads
    GROUP BY status
    ORDER BY count DESC
");
$stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Total leads by state
$stmt = $pdo->query("
    SELECT state, COUNT(*) as count
    FROM leads
    WHERE state IN ('TX', 'OK', 'AZ', 'LA', 'AL', 'FL', 'TN', 'GA')
    GROUP BY state
    ORDER BY count DESC
");
$stats['by_state'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Email engagement metrics
$stmt = $pdo->query("
    SELECT
        COUNT(*) as total_sent,
        COUNT(CASE WHEN opened_at IS NOT NULL THEN 1 END) as opened,
        COUNT(CASE WHEN clicked_at IS NOT NULL THEN 1 END) as clicked,
        COUNT(CASE WHEN replied_at IS NOT NULL THEN 1 END) as replied
    FROM outreach_log
    WHERE outreach_type = 'email'
");
$stats['email_metrics'] = $stmt->fetch();

// Calculate rates
if ($stats['email_metrics']['total_sent'] > 0) {
    $stats['open_rate'] = round(($stats['email_metrics']['opened'] / $stats['email_metrics']['total_sent']) * 100, 1);
    $stats['click_rate'] = round(($stats['email_metrics']['clicked'] / $stats['email_metrics']['total_sent']) * 100, 1);
    $stats['reply_rate'] = round(($stats['email_metrics']['replied'] / $stats['email_metrics']['total_sent']) * 100, 1);
} else {
    $stats['open_rate'] = $stats['click_rate'] = $stats['reply_rate'] = 0;
}

// Top qualified leads
$stmt = $pdo->query("
    SELECT * FROM leads
    WHERE status IN ('qualified', 'demo_scheduled')
    ORDER BY lead_score DESC, created_at DESC
    LIMIT 10
");
$top_leads = $stmt->fetchAll();

// Recent activity (last 24 hours)
$stmt = $pdo->query("
    SELECT l.physician_name, l.practice_name, l.state,
           o.outreach_type, o.subject, o.sent_at, o.opened_at, o.clicked_at
    FROM outreach_log o
    JOIN leads l ON o.lead_id = l.id
    WHERE o.sent_at >= CURRENT_TIMESTAMP - INTERVAL '24 hours'
    ORDER BY o.sent_at DESC
    LIMIT 20
");
$recent_activity = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Agent Dashboard - CollagenDirect</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            color: #2c3e50;
        }

        .header {
            background: linear-gradient(135deg, #47c6be 0%, #34a89e 100%);
            color: white;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 16px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-card .change {
            font-size: 14px;
            color: #27ae60;
        }

        .section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .section h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .state-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }

        .state-card {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
            border: 2px solid #e0e0e0;
        }

        .state-card .state-name {
            font-weight: bold;
            font-size: 18px;
            color: #47c6be;
            margin-bottom: 5px;
        }

        .state-card .count {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge.new { background: #3498db; color: white; }
        .badge.contacted { background: #f39c12; color: white; }
        .badge.qualified { background: #27ae60; color: white; }
        .badge.nurture { background: #95a5a6; color: white; }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #47c6be 0%, #34a89e 100%);
            transition: width 0.3s;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #47c6be;
            color: white;
        }

        .btn-primary:hover {
            background: #34a89e;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #ecf0f1;
            color: #2c3e50;
        }

        .btn-secondary:hover {
            background: #d5dbdb;
        }

        .metric-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .metric-label {
            font-weight: 600;
            color: #7f8c8d;
        }

        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎯 Sales Agent Dashboard</h1>
        <p>Automated physician outreach across 8 states</p>
    </div>

    <div class="container">
        <div class="action-buttons">
            <a href="lead-finder-ui.php" class="btn btn-primary">🔍 Find New Leads</a>
            <a href="create-campaign.php" class="btn btn-secondary">📧 Create Campaign</a>
            <a href="index.php" class="btn btn-secondary">📊 View All Leads</a>
        </div>

        <!-- Key Metrics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Leads</h3>
                <div class="value"><?= array_sum($stats['by_status']) ?></div>
                <div class="change">Across 8 states</div>
            </div>

            <div class="stat-card">
                <h3>Qualified</h3>
                <div class="value"><?= $stats['by_status']['qualified'] ?? 0 ?></div>
                <div class="change">Ready for handoff</div>
            </div>

            <div class="stat-card">
                <h3>Open Rate</h3>
                <div class="value"><?= $stats['open_rate'] ?>%</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $stats['open_rate'] ?>%"></div>
                </div>
            </div>

            <div class="stat-card">
                <h3>Click Rate</h3>
                <div class="value"><?= $stats['click_rate'] ?>%</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $stats['click_rate'] ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Email Metrics -->
        <div class="section">
            <h2>📧 Email Performance</h2>
            <div class="metric-row">
                <span class="metric-label">Total Sent</span>
                <span class="metric-value"><?= number_format($stats['email_metrics']['total_sent']) ?></span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Opened</span>
                <span class="metric-value"><?= number_format($stats['email_metrics']['opened']) ?> <small>(<?= $stats['open_rate'] ?>%)</small></span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Clicked</span>
                <span class="metric-value"><?= number_format($stats['email_metrics']['clicked']) ?> <small>(<?= $stats['click_rate'] ?>%)</small></span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Replied</span>
                <span class="metric-value"><?= number_format($stats['email_metrics']['replied']) ?> <small>(<?= $stats['reply_rate'] ?>%)</small></span>
            </div>
        </div>

        <!-- Leads by State -->
        <div class="section">
            <h2>🗺️ Leads by State</h2>
            <div class="state-grid">
                <?php foreach ($stats['by_state'] as $state => $count): ?>
                <div class="state-card">
                    <div class="state-name"><?= htmlspecialchars($state) ?></div>
                    <div class="count"><?= number_format($count) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Qualified Leads -->
        <div class="section">
            <h2>⭐ Top Qualified Leads</h2>
            <table>
                <thead>
                    <tr>
                        <th>Physician</th>
                        <th>Practice</th>
                        <th>Location</th>
                        <th>Specialty</th>
                        <th>Score</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_leads as $lead): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($lead['physician_name']) ?></strong></td>
                        <td><?= htmlspecialchars($lead['practice_name']) ?></td>
                        <td><?= htmlspecialchars($lead['city']) ?>, <?= htmlspecialchars($lead['state']) ?></td>
                        <td><?= htmlspecialchars($lead['specialty']) ?></td>
                        <td><strong><?= $lead['lead_score'] ?></strong></td>
                        <td><span class="badge <?= $lead['status'] ?>"><?= $lead['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Activity -->
        <div class="section">
            <h2>🕒 Recent Activity (Last 24 Hours)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Physician</th>
                        <th>Location</th>
                        <th>Action</th>
                        <th>Engagement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_activity as $activity): ?>
                    <tr>
                        <td><?= date('M j, g:i A', strtotime($activity['sent_at'])) ?></td>
                        <td><?= htmlspecialchars($activity['physician_name']) ?></td>
                        <td><?= htmlspecialchars($activity['state']) ?></td>
                        <td><?= htmlspecialchars($activity['subject']) ?></td>
                        <td>
                            <?php if ($activity['opened_at']): ?>
                                ✅ Opened
                            <?php endif; ?>
                            <?php if ($activity['clicked_at']): ?>
                                🖱️ Clicked
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
