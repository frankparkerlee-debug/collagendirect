<?php
/**
 * ONE-TIME SCRIPT: Fix email click tracking directly on server
 * Bypasses Git blob corruption issue
 */

header('Content-Type: application/json');

$files = [
    __DIR__ . '/lib/email_notifications.php',
    __DIR__ . '/lib/registration_welcome.php',
    __DIR__ . '/lib/provider_welcome.php'
];

$trackingSettings = "    'tracking_settings' => [
      'click_tracking' => ['enable' => false, 'enable_text' => false],
      'open_tracking' => ['enable' => false]
    ]";

$results = [];

foreach ($files as $file) {
    $filename = basename($file);

    if (!file_exists($file)) {
        $results[$filename] = ['ok' => false, 'error' => 'File not found'];
        continue;
    }

    $content = file_get_contents($file);

    // Check if already fixed
    if (strpos($content, 'tracking_settings') !== false) {
        $results[$filename] = ['ok' => true, 'message' => 'Already fixed', 'already_present' => true];
        continue;
    }

    // Find the data array closing for each file
    if ($filename === 'email_notifications.php') {
        // Add after categories line
        $pattern = "'categories' => ['auth', 'password-reset']\n  ];";
        $replacement = "'categories' => ['auth', 'password-reset'],\n" . $trackingSettings . "\n  ];";
    } elseif ($filename === 'registration_welcome.php') {
        // Add after reply_to line
        $pattern = "'reply_to' => ['email' => 'support@collagendirect.health', 'name' => 'CollagenDirect Support']\n  ];";
        $replacement = "'reply_to' => ['email' => 'support@collagendirect.health', 'name' => 'CollagenDirect Support'],\n" . $trackingSettings . "\n  ];";
    } elseif ($filename === 'provider_welcome.php') {
        // Add after content array
        $pattern = "['type' => 'text/plain', 'value' => \$emailBody]\n    ]\n  ];";
        $replacement = "['type' => 'text/plain', 'value' => \$emailBody]\n    ],\n" . $trackingSettings . "\n  ];";
    }

    if (strpos($content, $pattern) === false) {
        $results[$filename] = ['ok' => false, 'error' => 'Pattern not found'];
        continue;
    }

    $newContent = str_replace($pattern, $replacement, $content);

    if (file_put_contents($file, $newContent) === false) {
        $results[$filename] = ['ok' => false, 'error' => 'Failed to write file'];
        continue;
    }

    $results[$filename] = ['ok' => true, 'message' => 'Fixed successfully'];
}

echo json_encode([
    'ok' => true,
    'message' => 'Email tracking fix applied',
    'results' => $results
], JSON_PRETTY_PRINT);
