<?php
declare(strict_types=1);

/**
 * Setup Photo Prompt Cron Job
 *
 * Configures automated wound photo prompts to run daily at 10 AM Central Time
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Photo Prompt Cron Job Setup ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Determine the full path to the cron script
$cronScript = __DIR__ . '/../api/cron/send-wound-photo-prompts.php';
$webUrl = 'https://collagendirect.health/api/cron/send-wound-photo-prompts.php';

echo "OPTION 1: Server Crontab (Recommended)\n";
echo "========================================\n";
echo "Add this line to your server's crontab:\n\n";

$projectPath = dirname(__DIR__);
echo "# Wound photo prompts - Daily at 10 AM Central (15:00 UTC during CDT, 16:00 UTC during CST)\n";
echo "0 15 * * * cd {$projectPath} && /usr/bin/php api/cron/send-wound-photo-prompts.php\n";
echo "\nTo edit crontab, run:\n";
echo "  crontab -e\n\n";

echo "OPTION 2: Web Cron (cPanel, Render, etc.)\n";
echo "==========================================\n";
echo "Configure your hosting control panel to call this URL daily at 10 AM Central:\n\n";
echo "URL: {$webUrl}\n";
echo "Schedule: 0 15 * * * (15:00 UTC)\n";
echo "Method: GET\n\n";

echo "OPTION 3: Test Manually\n";
echo "========================\n";
echo "Test the cron job now:\n\n";
echo "  # Dry run (shows what would be sent):\n";
echo "  curl '{$webUrl}?test=1'\n\n";
echo "  # Actually send SMS:\n";
echo "  curl '{$webUrl}?test=1&send=1'\n\n";

echo "CURRENT STATUS:\n";
echo "===============\n";

// Check if table exists
require_once __DIR__ . '/db.php';

try {
    $check = $pdo->query("SELECT COUNT(*) FROM photo_prompt_schedule")->fetchColumn();
    echo "✓ photo_prompt_schedule table exists\n";
    echo "  Current schedules: {$check}\n";

    // Check for active schedules
    $active = $pdo->query("SELECT COUNT(*) FROM photo_prompt_schedule WHERE active = TRUE")->fetchColumn();
    echo "  Active schedules: {$active}\n";

    if ($active > 0) {
        echo "\n📅 Next prompts scheduled:\n";
        $next = $pdo->query("
            SELECT s.next_prompt_date, COUNT(*) as count
            FROM photo_prompt_schedule s
            WHERE s.active = TRUE
            GROUP BY s.next_prompt_date
            ORDER BY s.next_prompt_date
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($next as $row) {
            echo "  {$row['next_prompt_date']}: {$row['count']} prompt(s)\n";
        }
    } else {
        echo "\n⚠️  No active schedules yet.\n";
        echo "   Schedules are created automatically when orders are marked 'delivered'.\n";
    }

    echo "\n✓ System ready!\n";

} catch (Throwable $e) {
    echo "✗ Error checking database: " . $e->getMessage() . "\n";
}

echo "\n=== Setup Complete ===\n";
