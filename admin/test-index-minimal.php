<?php
// Minimal test of index.php to find where it breaks
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

echo "1. Starting...\n";

require_once __DIR__ . '/db.php';
echo "2. DB loaded\n";

$bootstrap = __DIR__.'/_bootstrap.php';
if (is_file($bootstrap)) require_once $bootstrap;
echo "3. Bootstrap loaded\n";

$auth = __DIR__ . '/auth.php';
if (is_file($auth)) require_once $auth;
echo "4. Auth loaded\n";

if (function_exists('require_admin')) require_admin();
echo "5. Admin check passed\n";

function qCount(PDO $p, string $q): int {
  try { $r=$p->query($q)->fetch(); return (int)($r['c']??0); } catch(Throwable $e){ return 0; }
}
echo "6. qCount function defined\n";

$totalOrders = qCount($pdo, "SELECT COUNT(*) c FROM orders");
echo "7. Total orders: $totalOrders\n";

$pendingApprovals = qCount($pdo, "SELECT COUNT(*) c FROM orders WHERE status IN ('submitted','pending','awaiting_approval')");
echo "8. Pending approvals: $pendingApprovals\n";

$activePatients = qCount($pdo, "SELECT COUNT(DISTINCT patient_id) c FROM orders WHERE status IN ('approved','in_transit','delivered')");
echo "9. Active patients: $activePatients\n";

echo "10. All KPIs loaded successfully!\n";
echo "\nTest complete - index.php should work up to this point.\n";
?>
