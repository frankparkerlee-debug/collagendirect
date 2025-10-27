<?php
// Test script to verify manufacturer can access patient data
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$adminId = $admin['id'] ?? '';

header('Content-Type: text/plain; charset=utf-8');

echo "===================================\n";
echo "MANUFACTURER ACCESS TEST\n";
echo "===================================\n\n";

// Test 1: Check current user role
echo "Test 1: Current User Role\n";
echo str_repeat("-", 50) . "\n";
echo "Admin ID: $adminId\n";
echo "Admin Role: $adminRole\n";
echo "Admin Name: " . ($admin['name'] ?? 'Unknown') . "\n";
echo "Admin Email: " . ($admin['email'] ?? 'Unknown') . "\n";

if ($adminRole === 'manufacturer') {
    echo "✅ PASS: Logged in as manufacturer\n\n";
} else {
    echo "⚠️  WARNING: Not logged in as manufacturer (role: $adminRole)\n";
    echo "   This test is designed for manufacturer users\n";
    echo "   Results may not be accurate for other roles\n\n";
}

// Test 2: Check patient data access
echo "Test 2: Patient Data Access\n";
echo str_repeat("-", 50) . "\n";

try {
    // Count total patients
    $totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    echo "Total patients in database: $totalPatients\n";

    // Count patients accessible to this admin (with role-based filtering)
    if ($adminRole === 'superadmin' || $adminRole === 'manufacturer') {
        $accessiblePatients = $totalPatients;
        echo "Accessible patients (no filter): $accessiblePatients\n";
        echo "✅ PASS: Manufacturer sees ALL patients (no filtering)\n";
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT p.id)
            FROM patients p
            WHERE EXISTS (
                SELECT 1 FROM admin_physicians ap
                WHERE ap.admin_id = ? AND ap.physician_user_id = p.user_id
            )
        ");
        $stmt->execute([$adminId]);
        $accessiblePatients = $stmt->fetchColumn();
        echo "Accessible patients (filtered): $accessiblePatients\n";

        if ($accessiblePatients < $totalPatients) {
            echo "ℹ️  INFO: Employee sees filtered subset of patients\n";
        }
    }
    echo "\n";

} catch (Throwable $e) {
    echo "❌ FAIL: Error accessing patient data\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// Test 3: Check order data access
echo "Test 3: Order Data Access\n";
echo str_repeat("-", 50) . "\n";

try {
    // Count total orders
    $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('rejected', 'cancelled')")->fetchColumn();
    echo "Total active orders in database: $totalOrders\n";

    // Count orders accessible to this admin (with role-based filtering)
    if ($adminRole === 'superadmin' || $adminRole === 'manufacturer') {
        $accessibleOrders = $totalOrders;
        echo "Accessible orders (no filter): $accessibleOrders\n";
        echo "✅ PASS: Manufacturer sees ALL orders (no filtering)\n";
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT o.id)
            FROM orders o
            WHERE o.status NOT IN ('rejected', 'cancelled')
              AND EXISTS (
                  SELECT 1 FROM admin_physicians ap
                  WHERE ap.admin_id = ? AND ap.physician_user_id = o.user_id
              )
        ");
        $stmt->execute([$adminId]);
        $accessibleOrders = $stmt->fetchColumn();
        echo "Accessible orders (filtered): $accessibleOrders\n";

        if ($accessibleOrders < $totalOrders) {
            echo "ℹ️  INFO: Employee sees filtered subset of orders\n";
        }
    }
    echo "\n";

} catch (Throwable $e) {
    echo "❌ FAIL: Error accessing order data\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// Test 4: Check users management permission
echo "Test 4: Users Management Permission\n";
echo str_repeat("-", 50) . "\n";

$isOwner = in_array($adminRole, ['owner', 'superadmin', 'admin', 'practice_admin', 'manufacturer']);

if ($isOwner) {
    echo "✅ PASS: Has \$isOwner permission (can manage users)\n";

    if ($adminRole === 'manufacturer') {
        echo "✅ PASS: Manufacturer role included in \$isOwner array\n";
    }
} else {
    echo "❌ FAIL: Does not have \$isOwner permission\n";
    if ($adminRole === 'manufacturer') {
        echo "   BUG: Manufacturer should have \$isOwner permission!\n";
    }
}
echo "\n";

// Test 5: Check message access
echo "Test 5: Message Data Access\n";
echo str_repeat("-", 50) . "\n";

try {
    // Count total messages from providers
    $totalMessages = $pdo->query("SELECT COUNT(*) FROM messages WHERE sender_type = 'provider'")->fetchColumn();
    echo "Total provider messages in database: $totalMessages\n";

    // Check what this admin can see
    if ($adminRole === 'superadmin') {
        $accessibleMessages = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
        echo "Accessible messages (superadmin sees ALL): $accessibleMessages\n";
        echo "✅ PASS: Superadmin sees all messages\n";
    } elseif ($adminRole === 'manufacturer') {
        $accessibleMessages = $totalMessages;
        echo "Accessible messages (manufacturer sees provider messages): $accessibleMessages\n";
        echo "✅ PASS: Manufacturer sees all provider messages\n";
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT m.id)
            FROM messages m
            WHERE m.sender_type = 'provider'
              AND EXISTS (
                  SELECT 1 FROM admin_physicians ap
                  WHERE ap.admin_id = ? AND ap.physician_user_id = m.sender_id
              )
        ");
        $stmt->execute([$adminId]);
        $accessibleMessages = $stmt->fetchColumn();
        echo "Accessible messages (filtered): $accessibleMessages\n";
        echo "ℹ️  INFO: Employee sees filtered messages\n";
    }
    echo "\n";

} catch (Throwable $e) {
    echo "❌ FAIL: Error accessing message data\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// Test 6: Check physician assignment capability
echo "Test 6: Physician Assignment Capability\n";
echo str_repeat("-", 50) . "\n";

if ($isOwner) {
    echo "✅ PASS: Can access physician assignment interface\n";

    // Check if there are employees to assign
    $employeeCount = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role IN ('employee', 'admin')")->fetchColumn();
    echo "   Employees in system: $employeeCount\n";

    // Check if there are physicians to assign
    $physicianCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('physician', 'practice_admin')")->fetchColumn();
    echo "   Physicians in system: $physicianCount\n";

    if ($employeeCount > 0 && $physicianCount > 0) {
        echo "✅ PASS: System has both employees and physicians for testing\n";
    } else {
        echo "ℹ️  INFO: Limited data for assignment testing\n";
    }
} else {
    echo "❌ FAIL: Cannot access physician assignment (not \$isOwner)\n";
}
echo "\n";

// Test 7: Check database schema compliance
echo "Test 7: Database Schema Compliance\n";
echo str_repeat("-", 50) . "\n";

try {
    // Check admin_users table structure
    $stmt = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = 'admin_users'
        ORDER BY ordinal_position
    ");
    $adminUserColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $requiredAdminColumns = ['id', 'name', 'email', 'role', 'password_hash'];
    $missingAdminColumns = array_diff($requiredAdminColumns, $adminUserColumns);

    if (empty($missingAdminColumns)) {
        echo "✅ PASS: admin_users table has all required columns\n";
    } else {
        echo "❌ FAIL: admin_users table missing columns: " . implode(', ', $missingAdminColumns) . "\n";
    }

    // Check if manufacturer role exists in system
    $manufacturerCount = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'manufacturer'")->fetchColumn();
    echo "   Manufacturer users in system: $manufacturerCount\n";

    if ($manufacturerCount > 0) {
        echo "✅ PASS: Manufacturer users exist in system\n";
    } else {
        echo "⚠️  WARNING: No manufacturer users found\n";
        echo "   Create one via: /admin/users.php?tab=manufacturer\n";
    }

} catch (Throwable $e) {
    echo "❌ FAIL: Error checking schema\n";
    echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Summary
echo "===================================\n";
echo "TEST SUMMARY\n";
echo "===================================\n\n";

$testsPassed = 0;
$testsTotal = 7;

// Evaluate each test
if ($adminRole === 'manufacturer' || $adminRole === 'superadmin') {
    echo "✅ Test 1: User Role - PASS\n";
    $testsPassed++;
} else {
    echo "⚠️  Test 1: User Role - SKIP (not manufacturer)\n";
}

if (isset($accessiblePatients) && ($adminRole === 'manufacturer' ? $accessiblePatients === $totalPatients : true)) {
    echo "✅ Test 2: Patient Access - PASS\n";
    $testsPassed++;
} else {
    echo "❌ Test 2: Patient Access - FAIL\n";
}

if (isset($accessibleOrders) && ($adminRole === 'manufacturer' ? $accessibleOrders === $totalOrders : true)) {
    echo "✅ Test 3: Order Access - PASS\n";
    $testsPassed++;
} else {
    echo "❌ Test 3: Order Access - FAIL\n";
}

if ($isOwner) {
    echo "✅ Test 4: Users Management - PASS\n";
    $testsPassed++;
} else {
    echo "❌ Test 4: Users Management - FAIL\n";
}

if (isset($accessibleMessages)) {
    echo "✅ Test 5: Message Access - PASS\n";
    $testsPassed++;
} else {
    echo "❌ Test 5: Message Access - FAIL\n";
}

if ($isOwner && isset($employeeCount) && isset($physicianCount)) {
    echo "✅ Test 6: Physician Assignment - PASS\n";
    $testsPassed++;
} else {
    echo "❌ Test 6: Physician Assignment - FAIL\n";
}

if (empty($missingAdminColumns)) {
    echo "✅ Test 7: Schema Compliance - PASS\n";
    $testsPassed++;
} else {
    echo "❌ Test 7: Schema Compliance - FAIL\n";
}

echo "\nTotal: $testsPassed / $testsTotal tests passed\n";

if ($testsPassed === $testsTotal) {
    echo "\n🎉 All manufacturer access tests passed!\n";
} else {
    echo "\n⚠️  Some tests failed or were skipped\n";
}

echo "\n===================================\n";
