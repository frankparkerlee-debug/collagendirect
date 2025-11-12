<?php
header('Content-Type: application/json');

$googleKey = getenv('GOOGLE_PLACES_API_KEY');
$sendgridKey = getenv('SENDGRID_API_KEY');

echo json_encode([
    'google_places_key' => [
        'set' => !empty($googleKey),
        'length' => $googleKey ? strlen($googleKey) : 0
    ],
    'sendgrid_key' => [
        'set' => !empty($sendgridKey),
        'length' => $sendgridKey ? strlen($sendgridKey) : 0
    ]
], JSON_PRETTY_PRINT);
