<?php
/**
 * Diagnostic: Check insurance data for a specific order and patient
 */

require_once __DIR__ . '/../api/db.php';

$order_id = $_GET['order_id'] ?? '13879e95a3e298205436c72306931ba0';

echo "<h1>Insurance Data Diagnostic</h1>\n";
echo "<p>Order ID: <strong>" . htmlspecialchars($order_id) . "</strong></p>\n";

try {
    // Fetch order with patient join (same as PDF query)
    $sql = "SELECT
              o.id as order_id,
              o.insurer_name as o_insurer_name,
              o.member_id as o_member_id,
              o.group_id as o_group_id,
              o.payer_phone as o_payer_phone,
              p.id as patient_id,
              p.first_name,
              p.last_name,
              p.insurance_provider as p_insurance_provider,
              p.insurance_member_id as p_insurance_member_id,
              p.insurance_group_id as p_insurance_group_id,
              p.insurance_payer_phone as p_insurance_payer_phone,
              p.insurance_ocr_processed,
              p.insurance_ocr_date,
              p.insurance_ocr_confidence
            FROM orders o
            LEFT JOIN patients p ON p.id = o.patient_id
            WHERE o.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "<p style='color: red;'>Order not found!</p>\n";
        exit;
    }

    echo "<h2>Patient Information</h2>\n";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Field</th><th>Value</th></tr>\n";
    echo "<tr><td>Patient ID</td><td>" . htmlspecialchars($row['patient_id']) . "</td></tr>\n";
    echo "<tr><td>Name</td><td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td></tr>\n";
    echo "</table>\n";

    echo "<h2>Insurance Data in ORDERS Table</h2>\n";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>\n";

    $orders_has_data = false;

    echo "<tr><td>insurer_name</td><td>" . htmlspecialchars($row['o_insurer_name'] ?? '—') . "</td>";
    echo "<td style='color: " . (!empty($row['o_insurer_name']) ? 'green' : 'red') . ";'>";
    echo (!empty($row['o_insurer_name']) ? '✓ Has data' : '✗ Empty');
    echo "</td></tr>\n";
    if (!empty($row['o_insurer_name'])) $orders_has_data = true;

    echo "<tr><td>member_id</td><td>" . htmlspecialchars($row['o_member_id'] ?? '—') . "</td>";
    echo "<td style='color: " . (!empty($row['o_member_id']) ? 'green' : 'red') . ";'>";
    echo (!empty($row['o_member_id']) ? '✓ Has data' : '✗ Empty');
    echo "</td></tr>\n";
    if (!empty($row['o_member_id'])) $orders_has_data = true;

    echo "<tr><td>group_id</td><td>" . htmlspecialchars($row['o_group_id'] ?? '—') . "</td>";
    echo "<td style='color: " . (!empty($row['o_group_id']) ? 'green' : 'red') . ";'>";
    echo (!empty($row['o_group_id']) ? '✓ Has data' : '✗ Empty');
    echo "</td></tr>\n";
    if (!empty($row['o_group_id'])) $orders_has_data = true;

    echo "<tr><td>payer_phone</td><td>" . htmlspecialchars($row['o_payer_phone'] ?? '—') . "</td>";
    echo "<td style='color: " . (!empty($row['o_payer_phone']) ? 'green' : 'red') . ";'>";
    echo (!empty($row['o_payer_phone']) ? '✓ Has data' : '✗ Empty');
    echo "</td></tr>\n";
    if (!empty($row['o_payer_phone'])) $orders_has_data = true;

    echo "</table>\n";

    echo "<h2>Insurance Data in PATIENTS Table</h2>\n";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>\n";

    $patients_has_data = false;

    echo "<tr><td>insurance_provider</td><td>" . htmlspecialchars($row['p_insurance_provider'] ?? '—') . "</td>";
    echo "<td style='color: " . (!empty($row['p_insurance_provider']) ? 'green' : 'red') . ";'>";
    echo (!empty($row['p_insurance_provider']) ? '✓ Has data' : '✗ Empty');
    echo "</td></tr>\n";
    if (!empty($row['p_insurance_provider'])) $patients_has_data = true;

    echo "<tr><td>insurance_member_id</td><td>" . htmlspecialchars($row['p_insurance_member_id'] ?? '—') . "</td>";
    echo "<td style='color: " . (!empty($row['p_insurance_member_id']) ? 'green' : 'red') . ";'>";
    echo (!empty($row['p_insurance_member_id']) ? '✓ Has data' : '✗ Empty');
    echo "</td></tr>\n";
    if (!empty($row['p_insurance_member_id'])) $patients_has_data = true;

    echo "<tr><td>insurance_group_id</td><td>" . htmlspecialchars($row['p_insurance_group_id'] ?? '—') . "</td>";
    echo "<td style='color: " . (!empty($row['p_insurance_group_id']) ? 'green' : 'red') . ";'>";
    echo (!empty($row['p_insurance_group_id']) ? '✓ Has data' : '✗ Empty');
    echo "</td></tr>\n";
    if (!empty($row['p_insurance_group_id'])) $patients_has_data = true;

    echo "<tr><td>insurance_payer_phone</td><td>" . htmlspecialchars($row['p_insurance_payer_phone'] ?? '—') . "</td>";
    echo "<td style='color: " . (!empty($row['p_insurance_payer_phone']) ? 'green' : 'red') . ";'>";
    echo (!empty($row['p_insurance_payer_phone']) ? '✓ Has data' : '✗ Empty');
    echo "</td></tr>\n";
    if (!empty($row['p_insurance_payer_phone'])) $patients_has_data = true;

    echo "</table>\n";

    echo "<h2>OCR Status</h2>\n";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Field</th><th>Value</th></tr>\n";
    echo "<tr><td>OCR Processed</td><td>" . ($row['insurance_ocr_processed'] ? 'Yes' : 'No') . "</td></tr>\n";
    echo "<tr><td>OCR Date</td><td>" . htmlspecialchars($row['insurance_ocr_date'] ?? '—') . "</td></tr>\n";
    echo "<tr><td>OCR Confidence</td><td>" . ($row['insurance_ocr_confidence'] ? (round($row['insurance_ocr_confidence'] * 100) . '%') : '—') . "</td></tr>\n";
    echo "</table>\n";

    echo "<h2>Summary & Recommendations</h2>\n";
    echo "<ul>\n";

    if ($orders_has_data && $patients_has_data) {
        echo "<li style='color: green;'><strong>✓ GOOD:</strong> Insurance data exists in BOTH orders and patients tables</li>\n";
        echo "<li>PDF should display insurance information correctly</li>\n";
    } elseif ($orders_has_data && !$patients_has_data) {
        echo "<li style='color: orange;'><strong>⚠ NEEDS BACKFILL:</strong> Insurance data only in orders table</li>\n";
        echo "<li>PDF fallback should work, but run backfill script to copy to patients table</li>\n";
        echo "<li>Action: <a href='backfill-patient-insurance-from-orders.php'>Run backfill script</a></li>\n";
    } elseif (!$orders_has_data && $patients_has_data) {
        echo "<li style='color: green;'><strong>✓ OK:</strong> Insurance data only in patients table (new system)</li>\n";
        echo "<li>PDF should display insurance information correctly</li>\n";
    } else {
        echo "<li style='color: red;'><strong>✗ PROBLEM:</strong> No insurance data in either table</li>\n";
        echo "<li>This order was created without insurance information</li>\n";
        echo "<li>Action: Edit the patient record to add insurance data manually</li>\n";
    }

    echo "</ul>\n";

} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
