<?php
/**
 * Direct OCR test with full error reporting
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/insurance-ocr.php';

echo "<h1>Direct OCR Test</h1>\n";

$imagePath = '/opt/render/project/src/uploads/insurance/sample-medicare-card-20251119-060620-426afc.jpg';

echo "<h2>Configuration</h2>\n";
echo "<ul>\n";
echo "<li>INSURANCE_OCR_ENABLED: " . (getenv('INSURANCE_OCR_ENABLED') ?: 'NOT SET') . "</li>\n";
echo "<li>INSURANCE_OCR_PROVIDER: " . (getenv('INSURANCE_OCR_PROVIDER') ?: 'NOT SET') . "</li>\n";
echo "<li>ANTHROPIC_API_KEY: " . (getenv('ANTHROPIC_API_KEY') ? 'SET (' . strlen(getenv('ANTHROPIC_API_KEY')) . ' chars)' : 'NOT SET') . "</li>\n";
echo "</ul>\n";

echo "<h2>File Check</h2>\n";
echo "<ul>\n";
echo "<li>Path: " . htmlspecialchars($imagePath) . "</li>\n";
echo "<li>Exists: " . (file_exists($imagePath) ? 'YES' : 'NO') . "</li>\n";

if (file_exists($imagePath)) {
    echo "<li>Readable: " . (is_readable($imagePath) ? 'YES' : 'NO') . "</li>\n";
    echo "<li>Size: " . number_format(filesize($imagePath)) . " bytes</li>\n";

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $imagePath);
    finfo_close($finfo);
    echo "<li>MIME Type: " . htmlspecialchars($mimeType) . "</li>\n";
}
echo "</ul>\n";

if (!file_exists($imagePath)) {
    echo "<p style='color: red;'>File does not exist!</p>\n";
    exit;
}

echo "<h2>Creating InsuranceOCR Instance</h2>\n";
try {
    $insuranceOCR = new InsuranceOCR();
    echo "<p>✓ Instance created</p>\n";
    echo "<p>OCR Enabled: " . ($insuranceOCR->isEnabled() ? 'YES' : 'NO') . "</p>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>Failed to create instance: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    exit;
}

if (!$insuranceOCR->isEnabled()) {
    echo "<p style='color: red;'>OCR is not enabled. Please set environment variables.</p>\n";
    exit;
}

echo "<h2>Processing Insurance Card</h2>\n";
echo "<p>Calling processInsuranceCard()...</p>\n";
flush();

try {
    $startTime = microtime(true);
    $result = $insuranceOCR->processInsuranceCard($imagePath);
    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000, 2);

    echo "<p>Processing completed in {$duration}ms</p>\n";

    if ($result === null) {
        echo "<p style='color: red;'><strong>✗ Result is NULL</strong></p>\n";
        echo "<p>This means the OCR processing failed. Check the error_log for details.</p>\n";

        // Try to get more info
        echo "<h3>Attempting Direct API Call</h3>\n";
        $apiKey = getenv('ANTHROPIC_API_KEY');

        if (!$apiKey) {
            echo "<p style='color: red;'>ANTHROPIC_API_KEY not available</p>\n";
        } else {
            echo "<p>Reading image file...</p>\n";
            $imageData = file_get_contents($imagePath);

            if (!$imageData) {
                echo "<p style='color: red;'>Failed to read image file</p>\n";
            } else {
                echo "<p>Image read successfully: " . number_format(strlen($imageData)) . " bytes</p>\n";
                echo "<p>Base64 encoding...</p>\n";
                $base64Image = base64_encode($imageData);
                echo "<p>Encoded to " . number_format(strlen($base64Image)) . " characters</p>\n";

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $imagePath);
                finfo_close($finfo);

                $payload = [
                    'model' => 'claude-3-5-sonnet-latest',
                    'max_tokens' => 1024,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'image',
                                    'source' => [
                                        'type' => 'base64',
                                        'media_type' => $mimeType,
                                        'data' => $base64Image
                                    ]
                                ],
                                [
                                    'type' => 'text',
                                    'text' => 'Extract insurance info from this card as JSON.'
                                ]
                            ]
                        ]
                    ]
                ];

                echo "<p>Making API request to Anthropic...</p>\n";
                flush();

                $ch = curl_init('https://api.anthropic.com/v1/messages');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01'
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                echo "<p>HTTP Status Code: <strong>{$httpCode}</strong></p>\n";

                if ($curlError) {
                    echo "<p style='color: red;'>cURL Error: " . htmlspecialchars($curlError) . "</p>\n";
                }

                echo "<h3>API Response:</h3>\n";
                echo "<pre style='background: #f5f5f5; padding: 10px; overflow-x: auto;'>";
                echo htmlspecialchars($response);
                echo "</pre>\n";

                if ($httpCode === 200) {
                    $decoded = json_decode($response, true);
                    if ($decoded) {
                        echo "<h3>Decoded Response:</h3>\n";
                        echo "<pre style='background: #e8f5e9; padding: 10px; overflow-x: auto;'>";
                        echo htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT));
                        echo "</pre>\n";
                    }
                }
            }
        }

    } else {
        echo "<p style='color: green;'><strong>✓ SUCCESS!</strong></p>\n";

        echo "<h3>Extracted Data:</h3>\n";
        echo "<pre style='background: #e8f5e9; padding: 10px; overflow-x: auto;'>";
        echo htmlspecialchars(print_r($result, true));
        echo "</pre>\n";

        echo "<h3>Confidence Score:</h3>\n";
        echo "<p><strong>" . round($result['confidence'] * 100) . "%</strong></p>\n";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Exception:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}
