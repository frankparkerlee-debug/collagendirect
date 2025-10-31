<?php
/**
 * Sales Outreach Agent Configuration
 *
 * Copy this file to config.php and fill in your credentials
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'collagendirect');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

// SendGrid Configuration
define('SENDGRID_API_KEY', 'your_sendgrid_api_key_here');
define('SENDGRID_FROM_EMAIL', 'sales@collagendirect.health');
define('SENDGRID_FROM_NAME', 'CollagenDirect Sales Team');

// Tracking Configuration
define('SENDGRID_ENABLE_TRACKING', true); // Enable open and click tracking
define('SENDGRID_ENABLE_CLICK_TRACKING', true);

// SMS Configuration (Twilio - Optional)
define('TWILIO_ENABLED', false); // Set to true when ready to use SMS
define('TWILIO_SID', 'your_twilio_sid');
define('TWILIO_TOKEN', 'your_twilio_token');
define('TWILIO_PHONE', '+1234567890'); // Your Twilio phone number

// Application Settings
define('BASE_URL', 'https://collagendirect.health');
define('DEMO_URL', BASE_URL . '/demo/');

// Outreach Limits (to prevent spam)
define('MAX_EMAILS_PER_HOUR', 100); // SendGrid free tier: 100/day
define('MAX_SMS_PER_DAY', 50);

// Admin Email (for notifications)
define('ADMIN_EMAIL', 'admin@collagendirect.health');

// Timezone
date_default_timezone_set('America/Chicago'); // Central Time (Texas)
?>
