<?php
declare(strict_types=1);

/**
 * Timezone Helper Functions
 * Centralizes timezone handling for the application
 * Default timezone: America/Chicago (Central Time - Dallas/Chicago)
 */

// Set default timezone for the entire application
date_default_timezone_set('America/Chicago');

/**
 * Get current datetime in Central Time
 *
 * @param string $format Date format (default: 'Y-m-d H:i:s')
 * @return string Formatted datetime in Central Time
 */
function now_central(string $format = 'Y-m-d H:i:s'): string {
    return date($format);
}

/**
 * Format a datetime string to Central Time
 *
 * @param string|null $datetime Datetime string (assumes UTC if no timezone specified)
 * @param string $format Output format (default: 'Y-m-d H:i:s')
 * @return string|null Formatted datetime in Central Time, or null if input is null
 */
function to_central(?string $datetime, string $format = 'Y-m-d H:i:s'): ?string {
    if (empty($datetime)) {
        return null;
    }

    try {
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Chicago'));
        return $dt->format($format);
    } catch (Exception $e) {
        error_log("timezone.php: Error converting datetime '{$datetime}': " . $e->getMessage());
        return $datetime; // Return original on error
    }
}

/**
 * Get user-friendly time format for SMS/display
 * Examples: "2:30 PM", "11:45 AM"
 *
 * @param string|null $datetime Datetime string (optional, defaults to now)
 * @return string Formatted time in Central Time
 */
function format_time_central(?string $datetime = null): string {
    if (empty($datetime)) {
        return date('g:i A');
    }

    try {
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Chicago'));
        return $dt->format('g:i A');
    } catch (Exception $e) {
        return date('g:i A');
    }
}

/**
 * Get user-friendly date format for display
 * Examples: "Nov 6, 2025", "Jan 15, 2025"
 *
 * @param string|null $datetime Datetime string (optional, defaults to now)
 * @return string Formatted date in Central Time
 */
function format_date_central(?string $datetime = null): string {
    if (empty($datetime)) {
        return date('M j, Y');
    }

    try {
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Chicago'));
        return $dt->format('M j, Y');
    } catch (Exception $e) {
        return date('M j, Y');
    }
}

/**
 * Get user-friendly datetime format for display
 * Examples: "Nov 6, 2025 at 2:30 PM"
 *
 * @param string|null $datetime Datetime string (optional, defaults to now)
 * @return string Formatted datetime in Central Time
 */
function format_datetime_central(?string $datetime = null): string {
    if (empty($datetime)) {
        return date('M j, Y \a\t g:i A');
    }

    try {
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Chicago'));
        return $dt->format('M j, Y \a\t g:i A');
    } catch (Exception $e) {
        return date('M j, Y \a\t g:i A');
    }
}

/**
 * Get timezone abbreviation (CST or CDT depending on DST)
 *
 * @return string Timezone abbreviation
 */
function get_timezone_abbr(): string {
    return date('T'); // Returns "CST" or "CDT"
}

/**
 * Check if currently observing Daylight Saving Time
 *
 * @return bool True if DST is active, false otherwise
 */
function is_dst(): bool {
    return (bool) date('I');
}
