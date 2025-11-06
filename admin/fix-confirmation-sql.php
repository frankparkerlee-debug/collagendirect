<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "Fixing confirmation record for order bbf4f52b8af9f5d2cdd8f273e4ec0b6a\n\n";

try {
    // Update phone number and clear error notes
    $stmt = $pdo->prepare("
        UPDATE delivery_confirmations
        SET patient_phone = '+13057836633',
            notes = NULL,
            updated_at = NOW()
        WHERE order_id = 'bbf4f52b8af9f5d2cdd8f273e4ec0b6a'
    ");
    $stmt->execute();

    echo "✓ Updated patient phone to +13057836633\n";
    echo "✓ Cleared error notes\n\n";

    // Show the token
    $tokenStmt = $pdo->prepare("
        SELECT confirmation_token FROM delivery_confirmations
        WHERE order_id = 'bbf4f52b8af9f5d2cdd8f273e4ec0b6a'
    ");
    $tokenStmt->execute();
    $token = $tokenStmt->fetchColumn();

    if ($token) {
        $url = "https://collagendirect.health/api/confirm-delivery.php?token=" . $token;
        echo "Confirmation URL:\n{$url}\n\n";
        echo "This URL should now work when patient clicks the link.\n";
    }

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
