<?php
// Quick fix: Add TWILIO_FROM_PHONE to .env
$env = __DIR__ . '/../api/.env';
$content = file_get_contents($env);
if (strpos($content, 'TWILIO_FROM_PHONE') === false) {
    file_put_contents($env . '.bak', $content);
    file_put_contents($env, $content . "\nTWILIO_FROM_PHONE=+18884156880\n");
    echo "✓ Added TWILIO_FROM_PHONE=+18884156880\n";
} else {
    echo "✓ Already exists\n";
}
