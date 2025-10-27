<?php
// Debug script to check why patients aren't showing
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$adminId = $admin['id'] ?? '';

header('Content-Type: text/plain');

echo "=== ADMIN INFO ===\n";
echo "Admin ID: " . ($adminId ?: 'NONE') . "\n";
echo "Admin Role: " . ($adminRole ?: 'NONE') . "\n";
echo "Admin Name: " . ($admin['name'] ?? 'NONE') . "\n";
echo "Admin Email: " . ($admin['email'] ?? 'NONE') . "\n";
echo "\n";

echo "=== TOTAL PATIENTS ===\n";
$totalPatients = $pdo->query("SELECT COUNT(*) as cnt FROM patients")->fetch();
echo "Total patients in database: " . ($totalPatients['cnt'] ?? 0) . "\n\n";

echo "=== ADMIN PHYSICIANS RELATIONSHIPS ===\n";
if ($adminRole === 'superadmin' || $adminRole === 'manufacturer') {
    echo "Role is superadmin or manufacturer - sees ALL patients (no filter applied)\n\n";
} else {
    echo "Role is employee - filtered by admin_physicians table\n";
    $relationships = $pdo->prepare("SELECT * FROM admin_physicians WHERE admin_id = ?");
    $relationships->execute([$adminId]);
    $rels = $relationships->fetchAll();

    if (empty($rels)) {
        echo "WARNING: No physician assignments found for this admin user!\n";
        echo "This is why patients are not showing.\n\n";
        echo "To fix: INSERT rows into admin_physicians table linking admin_id to physician_user_id\n\n";
    } else {
        echo "Assigned physicians (" . count($rels) . "):\n";
        foreach ($rels as $rel) {
            $phys = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
            $phys->execute([$rel['physician_user_id']]);
            $physData = $phys->fetch();
            if ($physData) {
                echo "  - " . $physData['first_name'] . " " . $physData['last_name'] . " (ID: " . $physData['id'] . ")\n";
            } else {
                echo "  - Physician user_id " . $rel['physician_user_id'] . " (NOT FOUND IN users TABLE)\n";
            }
        }
        echo "\n";

        // Count patients from assigned physicians
        $patientCount = $pdo->prepare("
            SELECT COUNT(DISTINCT p.id) as cnt
            FROM patients p
            WHERE EXISTS (
                SELECT 1 FROM admin_physicians ap
                WHERE ap.admin_id = ? AND ap.physician_user_id = p.user_id
            )
        ");
        $patientCount->execute([$adminId]);
        $count = $patientCount->fetch();
        echo "Patients from assigned physicians: " . ($count['cnt'] ?? 0) . "\n\n";
    }
}

echo "=== PATIENTS BY PHYSICIAN ===\n";
$patientsByPhys = $pdo->query("
    SELECT
        p.user_id,
        u.first_name,
        u.last_name,
        u.email,
        COUNT(p.id) as patient_count
    FROM patients p
    LEFT JOIN users u ON u.id = p.user_id
    GROUP BY p.user_id, u.first_name, u.last_name, u.email
    ORDER BY patient_count DESC
")->fetchAll();

foreach ($patientsByPhys as $row) {
    echo "Physician: " . ($row['first_name'] ?? '') . " " . ($row['last_name'] ?? '') . " (ID: " . ($row['user_id'] ?? 'NULL') . ") - " . $row['patient_count'] . " patients\n";
}
