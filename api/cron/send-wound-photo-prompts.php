<?php
declare(strict_types=1);

/**
 * Automated Wound Photo Prompt Cron Job
 *
 * Runs daily at 10 AM Central Time
 * Sends SMS prompts to patients for wound photo updates based on order frequency
 *
 * Setup:
 * Add to crontab: 0 10 * * * cd /path/to/project && php api/cron/send-wound-photo-prompts.php
 * Or use system scheduler (e.g., cPanel Cron Jobs, AWS EventBridge)
 */

require_once __DIR__ . '/../../admin/db.php';
require_once __DIR__ . '/../lib/timezone.php';
require_once __DIR__ . '/../lib/twilio_sms.php';
require_once __DIR__ . '/../lib/photo_prompt_helpers.php';

// Run as CLI only (security)
if (php_sapi_name() !== 'cli' && !isset($_GET['test'])) {
    http_response_code(403);
    die('This script can only be run from command line');
}

$isTest = isset($_GET['test']);
if ($isTest) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "=== Wound Photo Prompt Cron Job ===\n";
echo "Started: " . format_datetime_central() . " " . get_timezone_abbr() . "\n\n";

try {
    $today = date('Y-m-d');
    $sendCount = 0;
    $skipCount = 0;
    $errorCount = 0;

    // Find all schedules that need a prompt today
    $stmt = $pdo->prepare("
        SELECT
            s.id as schedule_id,
            s.order_id,
            s.patient_id,
            s.frequency_days,
            s.next_prompt_date,
            s.last_prompt_sent_at,
            s.total_prompts_sent,
            o.product,
            o.frequency as frequency_text,
            p.first_name,
            p.last_name,
            p.phone,
            u.first_name as phys_first,
            u.last_name as phys_last,
            u.practice_name
        FROM photo_prompt_schedule s
        JOIN orders o ON o.id = s.order_id
        JOIN patients p ON p.id = s.patient_id
        LEFT JOIN users u ON u.id = o.user_id
        WHERE s.active = TRUE
          AND s.next_prompt_date <= ?
          AND (s.end_date IS NULL OR s.end_date >= ?)
          AND o.status = 'delivered'
        ORDER BY s.next_prompt_date ASC
    ");

    $stmt->execute([$today, $today]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($schedules) . " order(s) needing photo prompts\n\n";

    foreach ($schedules as $schedule) {
        $orderId = substr($schedule['order_id'], 0, 8);
        $patientName = $schedule['first_name'] . ' ' . $schedule['last_name'];

        echo "Order {$orderId} - {$patientName}:\n";

        // Check if patient has valid phone number
        if (empty($schedule['phone'])) {
            echo "  ⚠️  Skipped: No phone number\n\n";
            $skipCount++;
            continue;
        }

        // Generate physician name
        $physicianName = '';
        if (!empty($schedule['phys_last'])) {
            $physicianName = $schedule['phys_last'];
            if (!empty($schedule['phys_first'])) {
                $physicianName .= ', ' . $schedule['phys_first'];
            }
        } elseif (!empty($schedule['practice_name'])) {
            $physicianName = $schedule['practice_name'];
        }

        // Generate message
        $message = generate_photo_prompt_message(
            $schedule['first_name'],
            $schedule['product'],
            $physicianName
        );

        // Send SMS (skip in test mode unless explicitly requested)
        if ($isTest && !isset($_GET['send'])) {
            echo "  📱 TEST MODE: Would send SMS to " . $schedule['phone'] . "\n";
            echo "     Message: " . str_replace("\n", "\n     ", $message) . "\n";
            $sendCount++;
        } else {
            echo "  📱 Sending SMS to " . $schedule['phone'] . "...\n";

            $result = twilio_send_sms($schedule['phone'], $message);

            if ($result['success']) {
                echo "  ✓ Sent! SID: " . $result['sid'] . "\n";

                // Create photo_request record
                $requestId = bin2hex(random_bytes(16));
                $insertRequest = $pdo->prepare("
                    INSERT INTO photo_requests (
                        id, patient_id, order_id,
                        requested_at, requested_via,
                        sms_sid, status,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, NOW(), 'automated_sms', ?, 'pending', NOW(), NOW())
                ");

                $insertRequest->execute([
                    $requestId,
                    $schedule['patient_id'],
                    $schedule['order_id'],
                    $result['sid']
                ]);

                // Update schedule
                $nextPrompt = date('Y-m-d', strtotime($today . ' +' . $schedule['frequency_days'] . ' days'));
                $updateStmt = $pdo->prepare("
                    UPDATE photo_prompt_schedule
                    SET last_prompt_sent_at = NOW(),
                        next_prompt_date = ?,
                        total_prompts_sent = total_prompts_sent + 1,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $updateStmt->execute([$nextPrompt, $schedule['schedule_id']]);

                echo "  ✓ Next prompt scheduled for: {$nextPrompt}\n";
                $sendCount++;
            } else {
                echo "  ✗ Failed: " . ($result['error'] ?? 'Unknown error') . "\n";
                $errorCount++;
            }
        }

        echo "\n";
    }

    // Summary
    echo "=== Summary ===\n";
    echo "Messages sent: {$sendCount}\n";
    echo "Skipped (no phone): {$skipCount}\n";
    echo "Errors: {$errorCount}\n";
    echo "\nCompleted: " . format_datetime_central() . "\n";

    if ($isTest) {
        echo "\n💡 Test mode: No SMS actually sent. Add &send=1 to URL to send for real.\n";
    }

} catch (Throwable $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Cron Job Complete ===\n";
