<?php
require __DIR__ . '/../api/db.php';

// Remove superadmin role from parker@senecawest.com
$email = 'parker@senecawest.com';

$stmt = $pdo->prepare("UPDATE users SET role = 'practice_admin', admin_type = NULL WHERE email = ?");
$stmt->execute([$email]);

echo "Updated $email:\n";
echo "- Set role to 'practice_admin'\n";
echo "- Removed admin_type (set to NULL)\n";
echo "\nThis user should now only have access to /portal, not /admin\n";
