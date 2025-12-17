<?php
/**
 * Enable Employee Rep View for Alina Herrera
 *
 * This migration enables the employee rep view for Alina Herrera
 * and sets up her commission rates.
 *
 * Run: php admin/migrations/enable_alina_rep_view.php
 * Or visit: /admin/migrations/enable_alina_rep_view.php
 */

declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

// Only allow superadmin to run migrations
$admin = current_admin();
if (!$admin || $admin['role'] !== 'superadmin') {
    die('Access denied. Only superadmin can run migrations.');
}

$results = [];
$errors = [];

try {
    // Find Alina in admin_users
    $stmt = $pdo->prepare("SELECT id, name, email, role, has_rep_view FROM admin_users WHERE email = ?");
    $stmt->execute(['alinaherrera29@gmail.com']);
    $alina = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alina) {
        $errors[] = "Alina Herrera not found in admin_users. Please check the email address.";
    } else {
        $results[] = "Found Alina: " . $alina['name'] . " (ID: " . $alina['id'] . ", Role: " . $alina['role'] . ")";

        // Enable rep view
        if (!$alina['has_rep_view']) {
            $pdo->prepare("UPDATE admin_users SET has_rep_view = TRUE WHERE id = ?")->execute([$alina['id']]);
            $results[] = "Enabled has_rep_view for Alina";
        } else {
            $results[] = "has_rep_view already enabled";
        }

        // Check if commission rates exist
        $rateCheck = $pdo->prepare("SELECT COUNT(*) FROM employee_rep_commission_rates WHERE admin_user_id = ?");
        $rateCheck->execute([$alina['id']]);
        $rateCount = (int)$rateCheck->fetchColumn();

        if ($rateCount === 0) {
            // Set default commission rates
            // Direct rate: 15%
            $pdo->prepare("
                INSERT INTO employee_rep_commission_rates (admin_user_id, rate_type, commission_rate, effective_date, notes)
                VALUES (?, 'direct', 0.15, CURRENT_DATE, 'Initial direct rate')
            ")->execute([$alina['id']]);
            $results[] = "Created direct commission rate: 15%";

            // Override rate: 5%
            $pdo->prepare("
                INSERT INTO employee_rep_commission_rates (admin_user_id, rate_type, commission_rate, effective_date, notes)
                VALUES (?, 'distributor_override', 0.05, CURRENT_DATE, 'Initial override rate')
            ")->execute([$alina['id']]);
            $results[] = "Created distributor override rate: 5%";
        } else {
            $results[] = "Commission rates already exist ($rateCount rate(s) found)";
        }

        // Verification
        $verifyStmt = $pdo->prepare("SELECT has_rep_view FROM admin_users WHERE id = ?");
        $verifyStmt->execute([$alina['id']]);
        $verify = $verifyStmt->fetch();

        if ($verify && $verify['has_rep_view']) {
            $results[] = "Verification: has_rep_view is TRUE";
        } else {
            $errors[] = "Verification failed: has_rep_view is not enabled";
        }
    }

} catch (Exception $e) {
    $errors[] = "Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enable Alina Rep View</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Enable Employee Rep View for Alina Herrera</h1>
        <p class="text-gray-600 mb-6">Sets up the employee rep portal access and commission rates</p>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <h2 class="text-lg font-semibold text-red-800 mb-2">Errors</h2>
                <ul class="list-disc list-inside text-red-700">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Results</h2>
            <ul class="space-y-2">
                <?php foreach ($results as $result): ?>
                    <li class="flex items-start gap-2">
                        <span class="text-green-600 mt-0.5">&#10003;</span>
                        <span class="text-gray-700"><?= htmlspecialchars($result) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if (empty($errors) && isset($alina)): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-green-800 mb-2">Next Steps</h3>
            <ol class="list-decimal list-inside text-green-700 space-y-1">
                <li>Alina should log out and log back in to see the new portal</li>
                <li>On login, she will be redirected to /admin/employee-rep/</li>
                <li>She can also access it via the "My Sales Portal" link in the sidebar</li>
                <li>Assign distributors to her from the Distributor detail pages</li>
            </ol>
        </div>
        <?php endif; ?>

        <div class="mt-6 flex gap-4">
            <a href="/admin/" class="btn bg-teal-500 text-white px-4 py-2 rounded hover:bg-teal-600">
                Return to Admin Dashboard
            </a>
            <a href="/admin/platform/internal-users.php" class="btn bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                View Internal Users
            </a>
        </div>
    </div>
</body>
</html>
