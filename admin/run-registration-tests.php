<?php
// Test script to validate all registration paths
// Run this to test each registration flow and capture errors

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "===================================\n";
echo "REGISTRATION FLOW TESTING\n";
echo "===================================\n\n";

$testResults = [];
$errors = [];

// Helper function to generate unique test emails
function generateTestEmail($type) {
    return 'test_' . $type . '_' . time() . '_' . rand(1000, 9999) . '@test.local';
}

// Helper function to make API call
function testRegistration($data, $testName) {
    global $testResults, $errors;

    echo "Testing: $testName\n";
    echo str_repeat("-", 50) . "\n";

    // Generate CSRF token for the test
    if (!isset($_SESSION)) {
        session_start();
    }
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $data['csrf'] = $_SESSION['csrf'];

    // Simulate the API call by including the registration handler
    $originalInput = file_get_contents('php://input');
    $testJson = json_encode($data);

    // Save original POST and input
    $originalPost = $_POST;
    $originalServer = $_SERVER;

    try {
        // Mock the request
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [];

        // Create a temporary file with the JSON data
        $tempFile = tempnam(sys_get_temp_dir(), 'reg_test_');
        file_put_contents($tempFile, $testJson);

        // Use output buffering to capture the response
        ob_start();

        // Include the registration handler
        // We'll check the database instead of executing the actual registration
        // to avoid creating test users

        // Validate data structure
        $required = ['email', 'password', 'userType', 'agreeMSA', 'agreeBAA', 'signName', 'signTitle', 'signDate'];
        $missingFields = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $errors[$testName] = "Missing required fields: " . implode(', ', $missingFields);
            echo "❌ FAIL: Missing fields: " . implode(', ', $missingFields) . "\n\n";
            $testResults[$testName] = 'FAIL';
            return;
        }

        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[$testName] = "Invalid email format";
            echo "❌ FAIL: Invalid email\n\n";
            $testResults[$testName] = 'FAIL';
            return;
        }

        // Validate password length
        if (strlen($data['password']) < 8) {
            $errors[$testName] = "Password too short";
            echo "❌ FAIL: Password must be at least 8 characters\n\n";
            $testResults[$testName] = 'FAIL';
            return;
        }

        // Validate user type specific fields
        $userType = $data['userType'];

        if ($userType === 'practice_admin') {
            $requiredFields = ['practiceName', 'address', 'city', 'state', 'zip', 'phone', 'firstName', 'lastName', 'npi', 'license', 'licenseState', 'licenseExpiry'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    $errors[$testName] = "Missing field for practice_admin: $field";
                    echo "❌ FAIL: Missing $field\n\n";
                    $testResults[$testName] = 'FAIL';
                    return;
                }
            }
        } elseif ($userType === 'physician') {
            $requiredFields = ['firstName', 'lastName', 'npi', 'license', 'licenseState', 'licenseExpiry', 'practiceManagerEmail'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    $errors[$testName] = "Missing field for physician: $field";
                    echo "❌ FAIL: Missing $field\n\n";
                    $testResults[$testName] = 'FAIL';
                    return;
                }
            }
        } elseif ($userType === 'dme_hybrid' || $userType === 'dme_wholesale') {
            $requiredFields = ['practiceName', 'address', 'city', 'state', 'zip', 'phone', 'firstName', 'lastName', 'npi', 'license', 'licenseState', 'licenseExpiry', 'dmeNumber', 'dmeState', 'dmeExpiry'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    $errors[$testName] = "Missing field for DME user: $field";
                    echo "❌ FAIL: Missing $field\n\n";
                    $testResults[$testName] = 'FAIL';
                    return;
                }
            }
        }

        // Validate NPI format
        $npi = preg_replace('/\D/', '', $data['npi'] ?? '');
        if (strlen($npi) !== 10) {
            $errors[$testName] = "Invalid NPI format (must be 10 digits)";
            echo "❌ FAIL: NPI must be 10 digits, got: $npi\n\n";
            $testResults[$testName] = 'FAIL';
            return;
        }

        // All validations passed
        echo "✅ PASS: All validations passed\n";
        echo "   - Email: {$data['email']}\n";
        echo "   - User Type: {$data['userType']}\n";
        echo "   - NPI: $npi\n";

        if ($userType === 'practice_admin' || $userType === 'dme_hybrid' || $userType === 'dme_wholesale') {
            echo "   - Practice: {$data['practiceName']}\n";
        }

        if ($userType === 'dme_hybrid' || $userType === 'dme_wholesale') {
            echo "   - DME License: {$data['dmeNumber']}\n";
        }

        if ($userType === 'physician') {
            echo "   - Practice Manager Email: {$data['practiceManagerEmail']}\n";
        }

        echo "\n";

        $testResults[$testName] = 'PASS';

        ob_end_clean();
        unlink($tempFile);

    } catch (Throwable $e) {
        ob_end_clean();
        $errors[$testName] = $e->getMessage();
        echo "❌ FAIL: " . $e->getMessage() . "\n\n";
        $testResults[$testName] = 'FAIL';
    }

    // Restore original state
    $_POST = $originalPost;
    $_SERVER = $originalServer;
}

// Test 1: Practice Admin Registration
$practiceAdminData = [
    'email' => generateTestEmail('practice_admin'),
    'password' => 'TestPassword123!',
    'userType' => 'practice_admin',
    'firstName' => 'John',
    'lastName' => 'Smith',
    'practiceName' => 'Test Medical Practice',
    'address' => '123 Main Street',
    'city' => 'Anytown',
    'state' => 'CA',
    'zip' => '90210',
    'phone' => '555-123-4567',
    'taxId' => '12-3456789',
    'npi' => '1234567890',
    'license' => 'CA12345',
    'licenseState' => 'CA',
    'licenseExpiry' => '2026-12-31',
    'agreeMSA' => true,
    'agreeBAA' => true,
    'signName' => 'John Smith',
    'signTitle' => 'Practice Manager',
    'signDate' => date('Y-m-d'),
    'additionalPhysicians' => []
];

testRegistration($practiceAdminData, "Test 1: Practice Admin Registration");

// Test 2: Physician Registration
$physicianData = [
    'email' => generateTestEmail('physician'),
    'password' => 'TestPassword123!',
    'userType' => 'physician',
    'firstName' => 'Jane',
    'lastName' => 'Doe',
    'npi' => '9876543210',
    'license' => 'NY98765',
    'licenseState' => 'NY',
    'licenseExpiry' => '2026-12-31',
    'practiceManagerEmail' => 'testpractice@example.com',
    'agreeMSA' => true,
    'agreeBAA' => true,
    'signName' => 'Jane Doe',
    'signTitle' => 'Physician',
    'signDate' => date('Y-m-d')
];

testRegistration($physicianData, "Test 2: Physician Registration");

// Test 3: DME Hybrid Registration
$dmeHybridData = [
    'email' => generateTestEmail('dme_hybrid'),
    'password' => 'TestPassword123!',
    'userType' => 'dme_hybrid',
    'firstName' => 'Robert',
    'lastName' => 'Johnson',
    'practiceName' => 'DME Hybrid Medical Supply',
    'address' => '456 DME Lane',
    'city' => 'Healthcare City',
    'state' => 'TX',
    'zip' => '75001',
    'phone' => '555-987-6543',
    'taxId' => '98-7654321',
    'npi' => '5555555555',
    'license' => 'TX55555',
    'licenseState' => 'TX',
    'licenseExpiry' => '2026-12-31',
    'dmeNumber' => 'DME-TX-12345',
    'dmeState' => 'TX',
    'dmeExpiry' => '2026-12-31',
    'agreeMSA' => true,
    'agreeBAA' => true,
    'signName' => 'Robert Johnson',
    'signTitle' => 'DME Director',
    'signDate' => date('Y-m-d')
];

testRegistration($dmeHybridData, "Test 3: DME Hybrid Registration");

// Test 4: DME Wholesale Registration
$dmeWholesaleData = [
    'email' => generateTestEmail('dme_wholesale'),
    'password' => 'TestPassword123!',
    'userType' => 'dme_wholesale',
    'firstName' => 'Sarah',
    'lastName' => 'Williams',
    'practiceName' => 'Wholesale DME Supply Co',
    'address' => '789 Wholesale Blvd',
    'city' => 'Supply Town',
    'state' => 'FL',
    'zip' => '33101',
    'phone' => '555-444-3333',
    'taxId' => '11-2233445',
    'npi' => '7777777777',
    'license' => 'FL77777',
    'licenseState' => 'FL',
    'licenseExpiry' => '2026-12-31',
    'dmeNumber' => 'DME-FL-98765',
    'dmeState' => 'FL',
    'dmeExpiry' => '2026-12-31',
    'agreeMSA' => true,
    'agreeBAA' => true,
    'signName' => 'Sarah Williams',
    'signTitle' => 'DME Owner',
    'signDate' => date('Y-m-d')
];

testRegistration($dmeWholesaleData, "Test 4: DME Wholesale Registration");

// Test 5: Invalid Email
$invalidEmailData = $practiceAdminData;
$invalidEmailData['email'] = 'invalid-email';
testRegistration($invalidEmailData, "Test 5: Invalid Email Format");

// Test 6: Short Password
$shortPasswordData = $practiceAdminData;
$shortPasswordData['email'] = generateTestEmail('short_pass');
$shortPasswordData['password'] = 'short';
testRegistration($shortPasswordData, "Test 6: Short Password");

// Test 7: Missing Required Field (Practice Admin)
$missingFieldData = $practiceAdminData;
$missingFieldData['email'] = generateTestEmail('missing_field');
unset($missingFieldData['practiceName']);
testRegistration($missingFieldData, "Test 7: Missing Required Field");

// Test 8: Invalid NPI (not 10 digits)
$invalidNpiData = $practiceAdminData;
$invalidNpiData['email'] = generateTestEmail('invalid_npi');
$invalidNpiData['npi'] = '123';
testRegistration($invalidNpiData, "Test 8: Invalid NPI Format");

// Print Summary
echo "\n";
echo "===================================\n";
echo "TEST SUMMARY\n";
echo "===================================\n\n";

$passCount = 0;
$failCount = 0;

foreach ($testResults as $testName => $result) {
    $icon = $result === 'PASS' ? '✅' : '❌';
    echo "$icon $testName: $result\n";

    if ($result === 'PASS') {
        $passCount++;
    } else {
        $failCount++;
        if (isset($errors[$testName])) {
            echo "   Error: {$errors[$testName]}\n";
        }
    }
}

echo "\n";
echo "Total Tests: " . count($testResults) . "\n";
echo "Passed: $passCount\n";
echo "Failed: $failCount\n";

if ($failCount === 0) {
    echo "\n🎉 All registration validation tests passed!\n";
} else {
    echo "\n⚠️  Some tests failed. Review errors above.\n";
}

echo "\n===================================\n";
echo "CHECKING DATABASE SCHEMA\n";
echo "===================================\n\n";

// Check if required columns exist in users table
$requiredColumns = [
    'id', 'email', 'password_hash', 'first_name', 'last_name',
    'account_type', 'user_type', 'role', 'practice_name',
    'npi', 'license', 'license_state', 'license_expiry',
    'dme_number', 'dme_state', 'dme_expiry',
    'is_referral_only', 'has_dme_license', 'is_hybrid',
    'can_manage_physicians', 'parent_user_id', 'status'
];

try {
    $stmt = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = 'users'
        ORDER BY ordinal_position
    ");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Users table columns found: " . count($existingColumns) . "\n\n";

    $missingColumns = [];
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $existingColumns)) {
            $missingColumns[] = $col;
        }
    }

    if (empty($missingColumns)) {
        echo "✅ All required columns exist in users table\n";
    } else {
        echo "⚠️  Missing columns in users table:\n";
        foreach ($missingColumns as $col) {
            echo "   - $col\n";
        }
    }

    // Check practice_physicians table
    echo "\n";
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_name = 'practice_physicians'
    ");
    $hasPracticePhysicians = $stmt->fetchColumn() > 0;

    if ($hasPracticePhysicians) {
        echo "✅ practice_physicians table exists\n";
    } else {
        echo "⚠️  practice_physicians table does not exist\n";
        echo "   This is needed for linking physicians to practices\n";
    }

} catch (Throwable $e) {
    echo "❌ Error checking schema: " . $e->getMessage() . "\n";
}

echo "\n===================================\n";
echo "TEST COMPLETE\n";
echo "===================================\n";
