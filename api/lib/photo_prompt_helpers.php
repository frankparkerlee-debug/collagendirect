<?php
declare(strict_types=1);

/**
 * Photo Prompt Helper Functions
 *
 * Handles scheduling logic for automated wound photo prompts
 */

/**
 * Parse frequency string to number of days between prompts
 *
 * Examples:
 *   "Daily" or "1x per week" → 1 day
 *   "3x per week" → 2 days (every ~2 days for 3 times/week)
 *   "2x per week" → 3 days
 *   "Weekly" or "1x per week" → 7 days
 *
 * @param string|null $frequency The frequency string from order
 * @return int Number of days between prompts (default: 7)
 */
function parse_frequency_to_days(?string $frequency): int {
    if (empty($frequency)) {
        return 7; // Default: weekly
    }

    $freq = strtolower(trim($frequency));

    // Daily patterns
    if (preg_match('/daily|every\s*day|1\s*per\s*day/i', $freq)) {
        return 1;
    }

    // Times per week patterns
    if (preg_match('/(\d+)\s*x?\s*(per|\/)\s*week/i', $freq, $matches)) {
        $timesPerWeek = intval($matches[1]);
        if ($timesPerWeek >= 7) return 1; // 7+ times/week = daily
        if ($timesPerWeek >= 3) return 2; // 3-6 times/week = every 2 days
        if ($timesPerWeek >= 2) return 3; // 2 times/week = every 3 days
        return 7; // 1 time/week
    }

    // Weekly patterns
    if (preg_match('/weekly|once\s*a\s*week|1\s*per\s*week/i', $freq)) {
        return 7;
    }

    // Bi-weekly
    if (preg_match('/bi-?weekly|every\s*2\s*weeks/i', $freq)) {
        return 14;
    }

    // Monthly
    if (preg_match('/monthly|once\s*a\s*month/i', $freq)) {
        return 30;
    }

    // Default to weekly if can't parse
    return 7;
}

/**
 * Calculate end date for photo prompts based on product name
 *
 * Looks for patterns like "15-Day Kit", "30 Day Supply" in product name
 * Falls back to 30 days if no duration found
 *
 * @param string $productName The product name
 * @param string $startDate The start date (YYYY-MM-DD)
 * @return string End date (YYYY-MM-DD)
 */
function calculate_prompt_end_date(string $productName, string $startDate): string {
    // Look for patterns like "15-Day", "30 Day", "15 days"
    if (preg_match('/(\d+)[-\s]?day/i', $productName, $matches)) {
        $days = intval($matches[1]);
    } else {
        // Default to 30 days
        $days = 30;
    }

    // Add buffer of 3 days for continued monitoring
    $days += 3;

    $endDate = date('Y-m-d', strtotime($startDate . " +{$days} days"));
    return $endDate;
}

/**
 * Generate SMS message for photo prompt
 *
 * @param string $patientFirstName Patient's first name
 * @param string $productName Product name (e.g., "15-Day Alginate Kit")
 * @param string $physicianName Physician's name (optional)
 * @return string SMS message text
 */
function generate_photo_prompt_message(
    string $patientFirstName,
    string $productName,
    string $physicianName = ''
): string {
    $greeting = "Hi {$patientFirstName}!";

    if (!empty($physicianName)) {
        $doctorPart = " Dr. {$physicianName} would like";
    } else {
        $doctorPart = " Your doctor would like";
    }

    $message = "{$greeting}{$doctorPart} an update on your wound healing progress.\n\n"
        . "Please reply to this message with a photo of your wound.\n\n"
        . "📸 Tap the camera icon to attach a photo. Thank you!";

    return $message;
}

/**
 * Create photo prompt schedule for an order
 *
 * Called when order is marked as delivered
 *
 * @param PDO $pdo Database connection
 * @param string $orderId Order ID
 * @param string $patientId Patient ID
 * @param string|null $frequency Frequency string (e.g., "3x per week")
 * @param string $productName Product name
 * @param string $deliveryDate Delivery date (YYYY-MM-DD)
 * @return bool Success
 */
function create_photo_prompt_schedule(
    PDO $pdo,
    string $orderId,
    string $patientId,
    ?string $frequency,
    string $productName,
    string $deliveryDate
): bool {
    try {
        // Check if schedule already exists
        $existing = $pdo->prepare("SELECT id FROM photo_prompt_schedule WHERE order_id = ?");
        $existing->execute([$orderId]);

        if ($existing->fetch()) {
            error_log("[photo_prompt] Schedule already exists for order {$orderId}");
            return true;
        }

        $frequencyDays = parse_frequency_to_days($frequency);
        $startDate = $deliveryDate;
        $endDate = calculate_prompt_end_date($productName, $startDate);

        // First prompt: 2 days after delivery (give patient time to receive)
        $nextPromptDate = date('Y-m-d', strtotime($startDate . ' +2 days'));

        $stmt = $pdo->prepare("
            INSERT INTO photo_prompt_schedule (
                order_id, patient_id,
                frequency_days, next_prompt_date,
                start_date, end_date,
                active,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, true, NOW(), NOW())
        ");

        $stmt->execute([
            $orderId,
            $patientId,
            $frequencyDays,
            $nextPromptDate,
            $startDate,
            $endDate
        ]);

        error_log("[photo_prompt] Created schedule for order {$orderId}: every {$frequencyDays} days until {$endDate}");
        return true;

    } catch (Throwable $e) {
        error_log("[photo_prompt] Error creating schedule for order {$orderId}: " . $e->getMessage());
        return false;
    }
}
