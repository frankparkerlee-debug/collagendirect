<?php
// Script to assign all physicians to the current admin user
// Run this to allow an employee admin to see all patients
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$adminId = $admin['id'] ?? '';

header('Content-Type: text/plain');

echo "=== ASSIGN ALL PHYSICIANS TO ADMIN USER ===\n\n";

if ($adminRole === 'superadmin' || $adminRole === 'manufacturer') {
    echo "Your role is '$adminRole' - you already see all patients!\n";
    echo "No need to run this script.\n";
    exit;
}

echo "Admin ID: $adminId\n";
echo "Admin Role: $adminRole\n";
echo "Admin Name: " . ($admin['name'] ?? 'Unknown') . "\n\n";

// Get all physicians
$physicians = $pdo->query("
    SELECT id, first_name, last_name, email, role
    FROM users
    WHERE role IN ('physician', 'practice_admin')
    ORDER BY first_name, last_name
")->fetchAll();

echo "Found " . count($physicians) . " physicians to assign\n\n";

if (empty($physicians)) {
    echo "No physicians found in the database.\n";
    exit;
}

// Clear existing assignments for this admin
$pdo->prepare("DELETE FROM admin_physicians WHERE admin_id = ?")->execute([$adminId]);
echo "Cleared existing physician assignments\n\n";

// Assign all physicians
$inserted = 0;
foreach ($physicians as $phys) {
    try {
        $pdo->prepare("
            INSERT INTO admin_physicians (admin_id, physician_user_id, created_at)
            VALUES (?, ?, NOW())
            ON CONFLICT (admin_id, physician_user_id) DO NOTHING
        ")->execute([$adminId, $phys['id']]);

        echo "✓ Assigned: " . $phys['first_name'] . " " . $phys['last_name'] . " (" . $phys['email'] . ")\n";
        $inserted++;
    } catch (Exception $e) {
        echo "✗ Failed to assign " . $phys['email'] . ": " . $e->getMessage() . "\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Successfully assigned $inserted physicians to your admin account\n";
echo "You should now be able to see all patients in admin/patients.php and admin/billing.php\n";
