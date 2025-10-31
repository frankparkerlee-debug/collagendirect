<?php
/**
 * Sales Outreach Agent Configuration
 */

// Database Configuration (PostgreSQL)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'collagen_db');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '5432');

// SendGrid Configuration
define('SENDGRID_API_KEY', getenv('SENDGRID_API_KEY') ?: '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');
define('SENDGRID_FROM_EMAIL', 'sales@collagendirect.health');
define('SENDGRID_FROM_NAME', 'CollagenDirect Sales Team');

// Tracking Configuration
define('SENDGRID_ENABLE_TRACKING', true); // Enable open and click tracking
define('SENDGRID_ENABLE_CLICK_TRACKING', true);

// SMS Configuration (Twilio - Optional)
define('TWILIO_ENABLED', false); // Set to true when ready to use SMS
define('TWILIO_SID', '');
define('TWILIO_TOKEN', '');
define('TWILIO_PHONE', ''); // Your Twilio phone number

// Application Settings
define('BASE_URL', 'https://collagendirect.health');
define('DEMO_URL', BASE_URL . '/demo/');

// Outreach Limits (to prevent spam)
define('MAX_EMAILS_PER_HOUR', 100); // SendGrid free tier: 100/day
define('MAX_SMS_PER_DAY', 50);

// Admin Email (for notifications)
define('ADMIN_EMAIL', 'parker@collagendirect.health');

// Timezone
date_default_timezone_set('America/Chicago'); // Central Time (Texas)

// Create PDO connection
try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";options='--client_encoding=UTF8'";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>
