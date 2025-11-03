<?php
/**
 * Set user credentials (MD, DO, NP, PA, RN)
 *
 * Usage: https://collagendirect.health/admin/set-user-credentials.php?email=user@example.com&credential=MD&npi=1234567890
 */

require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

$email = $_GET['email'] ?? '';
$credential = strtoupper($_GET['credential'] ?? 'MD');
$npi = $_GET['npi'] ?? '';

if (empty($email)) {
    echo "Error: email parameter required\n";
    echo "Usage: ?email=user@example.com&credential=MD&npi=1234567890\n";
    exit;
}

// Validate credential type
$validCredentials = ['MD', 'DO', 'NP', 'PA', 'RN', 'OTHER'];
if (!in_array($credential, $validCredentials)) {
    echo "Error: Invalid credential type\n";
    echo "Valid types: " . implode(', ', $validCredentials) . "\n";
    exit;
}

echo "=== Set User Credentials ===\n\n";
echo "Email: $email\n";
echo "Credential: $credential\n";
echo "NPI: " . ($npi ?: '(not set)') . "\n\n";

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, credential_type FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "✗ User not found: $email\n";
        exit(1);
    }

    echo "Found user: {$user['first_name']} {$user['last_name']}\n";
    echo "Current credential: " . ($user['credential_type'] ?? 'MD (default)') . "\n\n";

    // Update credential type
    $updateSql = "UPDATE users SET credential_type = ?";
    $params = [$credential];

    if ($npi) {
        $updateSql .= ", npi = ?";
        $params[] = $npi;
    }

    $updateSql .= " WHERE id = ?";
    $params[] = $user['id'];

    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute($params);

    echo "✓ Updated successfully!\n\n";
    echo "New settings:\n";
    echo "  Credential Type: $credential\n";
    if ($npi) {
        echo "  NPI: $npi\n";
    }

    // Show reimbursement rate
    echo "\nReimbursement Rates:\n";
    switch ($credential) {
        case 'MD':
        case 'DO':
            echo "  E/M Codes: 100% ($92, $130, $180)\n";
            break;
        case 'NP':
        case 'PA':
            echo "  E/M Codes: 85% ($78.20, $110.50, $153.00)\n";
            if ($credential === 'PA') {
                echo "  Note: PA requires supervising_physician_id to be set\n";
            }
            break;
        case 'RN':
            echo "  E/M Codes: NOT ALLOWED (system will block)\n";
            echo "  Can bill: CPT 99211 (~$25-40, not telehealth)\n";
            break;
        default:
            echo "  Unknown credential type\n";
    }

} catch (PDOException $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
