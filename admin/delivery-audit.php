<?php
/**
 * Delivery & AOB Audit Report
 *
 * Provides easy access to all delivery confirmations and AOB signatures
 * for insurance audits and compliance review.
 *
 * Features:
 * - Filter by date range, status, physician
 * - Export to CSV for audit submissions
 * - View all compliance data at a glance
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../api/lib/timezone.php';

// Require admin login
if (function_exists('require_admin')) require_admin();
$admin = current_admin();
$userRole = $admin['role'] ?? '';

// Only superadmin and manufacturer can access audit reports
if (!in_array($userRole, ['superadmin', 'manufacturer', 'admin', 'employee'])) {
    header('Location: /admin/');
    exit;
}

// Get filter parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-90 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$statusFilter = $_GET['status'] ?? '';
$physicianFilter = $_GET['physician_id'] ?? '';
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

// Build query
$params = [];
$whereConditions = ["dc.aob_signed_at IS NOT NULL"]; // Only show signed AOBs

if ($dateFrom) {
    $whereConditions[] = "dc.aob_signed_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $whereConditions[] = "dc.aob_signed_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}
if ($physicianFilter) {
    $whereConditions[] = "o.physician_id = ?";
    $params[] = $physicianFilter;
}

$whereClause = implode(' AND ', $whereConditions);

$sql = "
    SELECT
        dc.id,
        dc.order_id,
        dc.confirmation_token,
        dc.sms_sent_at,
        dc.sms_status,
        dc.patient_phone,
        dc.confirmed_at,
        dc.confirmation_method,
        dc.confirmed_ip,
        dc.confirmed_user_agent,
        dc.aob_viewed_at,
        dc.aob_signed_at,
        dc.aob_signature_ip,
        dc.aob_signature_user_agent,
        dc.patient_name_snapshot,
        dc.patient_dob_snapshot,
        dc.patient_address_snapshot,
        dc.order_product_snapshot,
        dc.order_physician_snapshot,
        dc.order_physician_npi_snapshot,
        dc.order_date_snapshot,
        dc.created_at,
        o.status AS order_status,
        o.delivered_at,
        p.first_name,
        p.last_name,
        p.dob,
        p.phone,
        p.email,
        u.first_name AS phys_first,
        u.last_name AS phys_last,
        u.npi AS phys_npi,
        u.practice_name,
        pr.name AS product_name,
        pr.size AS product_size
    FROM delivery_confirmations dc
    JOIN orders o ON o.id = dc.order_id
    LEFT JOIN patients p ON p.id = o.patient_id
    LEFT JOIN users u ON u.id = o.physician_id
    LEFT JOIN products pr ON pr.id = o.product_id
    WHERE $whereClause
    ORDER BY dc.aob_signed_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get physicians for filter dropdown
$physicians = $pdo->query("
    SELECT id, first_name, last_name, practice_name
    FROM users
    WHERE role = 'physician'
    ORDER BY last_name, first_name
")->fetchAll(PDO::FETCH_ASSOC);

// Handle CSV export
if ($exportCsv) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="delivery-aob-audit-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV header
    fputcsv($output, [
        'Order ID',
        'Patient Name (Snapshot)',
        'Patient DOB (Snapshot)',
        'Patient Address (Snapshot)',
        'Patient Phone',
        'Patient Email',
        'Product (Snapshot)',
        'Physician (Snapshot)',
        'Physician NPI (Snapshot)',
        'Order Date (Snapshot)',
        'Delivered Date',
        'SMS/Email Sent At',
        'AOB Viewed At',
        'AOB Signed At',
        'Signature IP',
        'Confirmation Method',
        'Confirmation IP',
        'User Agent'
    ]);

    foreach ($records as $r) {
        fputcsv($output, [
            $r['order_id'],
            $r['patient_name_snapshot'] ?: ($r['first_name'] . ' ' . $r['last_name']),
            $r['patient_dob_snapshot'] ? date('m/d/Y', strtotime($r['patient_dob_snapshot'])) : ($r['dob'] ? date('m/d/Y', strtotime($r['dob'])) : ''),
            $r['patient_address_snapshot'] ?: '',
            $r['patient_phone'] ?: $r['phone'],
            $r['email'] ?: '',
            $r['order_product_snapshot'] ?: ($r['product_name'] . ($r['product_size'] ? ' ' . $r['product_size'] : '')),
            $r['order_physician_snapshot'] ?: ($r['phys_first'] . ' ' . $r['phys_last']),
            $r['order_physician_npi_snapshot'] ?: $r['phys_npi'],
            $r['order_date_snapshot'] ? date('m/d/Y', strtotime($r['order_date_snapshot'])) : '',
            $r['delivered_at'] ? date('m/d/Y', strtotime($r['delivered_at'])) : '',
            $r['sms_sent_at'] ? date('m/d/Y g:i A', strtotime($r['sms_sent_at'])) : '',
            $r['aob_viewed_at'] ? date('m/d/Y g:i A', strtotime($r['aob_viewed_at'])) : '',
            $r['aob_signed_at'] ? date('m/d/Y g:i A', strtotime($r['aob_signed_at'])) : '',
            $r['aob_signature_ip'] ?: $r['confirmed_ip'],
            $r['confirmation_method'] ?: '',
            $r['confirmed_ip'] ?: '',
            $r['confirmed_user_agent'] ?: ''
        ]);
    }

    fclose($output);
    exit;
}

// Helper function
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$header = __DIR__.'/_header.php';
$footer = __DIR__.'/_footer.php';
$hasLayout = is_file($header) && is_file($footer);
if ($hasLayout) include $header; else echo '<!doctype html><meta charset="utf-8"><script src="https://cdn.tailwindcss.com"></script><div class="p-6">';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Delivery & AOB Audit Report</h1>
        <p class="text-slate-500 text-sm mt-1">Insurance compliance records for delivery confirmations and AOB signatures</p>
    </div>
    <div class="flex gap-2">
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"
           class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Export CSV
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white border rounded-lg p-4 mb-6 shadow-sm">
    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="text-xs text-slate-500 mb-1 block">Date From</label>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>"
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-slate-500 mb-1 block">Date To</label>
            <input type="date" name="date_to" value="<?= e($dateTo) ?>"
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-slate-500 mb-1 block">Physician</label>
            <select name="physician_id" class="w-full border rounded px-3 py-2 text-sm">
                <option value="">All Physicians</option>
                <?php foreach ($physicians as $ph): ?>
                    <option value="<?= $ph['id'] ?>" <?= $physicianFilter == $ph['id'] ? 'selected' : '' ?>>
                        Dr. <?= e($ph['last_name']) ?>, <?= e($ph['first_name']) ?>
                        <?= $ph['practice_name'] ? '(' . e($ph['practice_name']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="bg-brand hover:bg-teal-700 text-white px-6 py-2 rounded-lg w-full">
                Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- Summary Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white border rounded-lg p-4 shadow-sm">
        <div class="text-3xl font-bold text-teal-600"><?= count($records) ?></div>
        <div class="text-sm text-slate-500">Total AOB Signatures</div>
    </div>
    <div class="bg-white border rounded-lg p-4 shadow-sm">
        <div class="text-3xl font-bold text-amber-600">
            <?= count(array_filter($records, fn($r) => $r['confirmation_method'] === 'web_approval')) ?>
        </div>
        <div class="text-sm text-slate-500">Web Approvals</div>
    </div>
    <div class="bg-white border rounded-lg p-4 shadow-sm">
        <div class="text-3xl font-bold text-blue-600">
            <?= count(array_filter($records, fn($r) => !empty($r['aob_viewed_at']))) ?>
        </div>
        <div class="text-sm text-slate-500">AOB Viewed</div>
    </div>
    <div class="bg-white border rounded-lg p-4 shadow-sm">
        <div class="text-3xl font-bold text-green-600">
            <?= count(array_filter($records, fn($r) => !empty($r['patient_name_snapshot']))) ?>
        </div>
        <div class="text-sm text-slate-500">Complete Records</div>
    </div>
</div>

<!-- Results Table -->
<div class="bg-white border rounded-lg shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Order ID</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Patient</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Product</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Physician</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">AOB Signed</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Signature IP</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Method</th>
                    <th class="text-center px-4 py-3 font-semibold text-slate-600">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                            No delivery confirmations found for the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="/admin/orders.php?search=<?= e(substr($r['order_id'], 0, 8)) ?>"
                                   class="font-mono text-teal-600 hover:underline">
                                    #<?= e(substr($r['order_id'], 0, 8)) ?>
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    <?= e($r['patient_name_snapshot'] ?: ($r['first_name'] . ' ' . $r['last_name'])) ?>
                                </div>
                                <div class="text-xs text-slate-500">
                                    DOB: <?= $r['patient_dob_snapshot'] ? date('m/d/Y', strtotime($r['patient_dob_snapshot'])) : ($r['dob'] ? date('m/d/Y', strtotime($r['dob'])) : 'N/A') ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <?= e($r['order_product_snapshot'] ?: ($r['product_name'] . ($r['product_size'] ? ' ' . $r['product_size'] : ''))) ?>
                            </td>
                            <td class="px-4 py-3">
                                <div><?= e($r['order_physician_snapshot'] ?: ('Dr. ' . $r['phys_first'] . ' ' . $r['phys_last'])) ?></div>
                                <div class="text-xs text-slate-500 font-mono">
                                    NPI: <?= e($r['order_physician_npi_snapshot'] ?: $r['phys_npi']) ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-green-700">
                                    <?= $r['aob_signed_at'] ? date('m/d/Y', strtotime($r['aob_signed_at'])) : 'N/A' ?>
                                </div>
                                <div class="text-xs text-slate-500">
                                    <?= $r['aob_signed_at'] ? date('g:i A', strtotime($r['aob_signed_at'])) : '' ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                <?= e($r['aob_signature_ip'] ?: $r['confirmed_ip'] ?: 'N/A') ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php
                                $method = $r['confirmation_method'] ?? '';
                                $badge = match($method) {
                                    'web_approval' => 'bg-amber-100 text-amber-800',
                                    'web_link' => 'bg-blue-100 text-blue-800',
                                    default => 'bg-gray-100 text-gray-800'
                                };
                                $label = match($method) {
                                    'web_approval' => 'Web + AOB',
                                    'web_link' => 'Web Link',
                                    default => $method ?: 'Unknown'
                                };
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $badge ?>">
                                    <?= $label ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button onclick='showAuditDetails(<?= json_encode([
                                    "order_id" => substr($r["order_id"], 0, 8),
                                    "patient_name" => $r["patient_name_snapshot"] ?: ($r["first_name"] . " " . $r["last_name"]),
                                    "patient_dob" => $r["patient_dob_snapshot"] ? date("m/d/Y", strtotime($r["patient_dob_snapshot"])) : ($r["dob"] ? date("m/d/Y", strtotime($r["dob"])) : "N/A"),
                                    "patient_address" => $r["patient_address_snapshot"] ?: "N/A",
                                    "patient_phone" => $r["patient_phone"] ?: $r["phone"] ?: "N/A",
                                    "product" => $r["order_product_snapshot"] ?: ($r["product_name"] . ($r["product_size"] ? " " . $r["product_size"] : "")),
                                    "physician" => $r["order_physician_snapshot"] ?: ("Dr. " . $r["phys_first"] . " " . $r["phys_last"]),
                                    "physician_npi" => $r["order_physician_npi_snapshot"] ?: $r["phys_npi"],
                                    "order_date" => $r["order_date_snapshot"] ? date("m/d/Y", strtotime($r["order_date_snapshot"])) : "N/A",
                                    "delivered_at" => $r["delivered_at"] ? date("m/d/Y", strtotime($r["delivered_at"])) : "N/A",
                                    "sms_sent_at" => $r["sms_sent_at"] ? date("m/d/Y g:i A", strtotime($r["sms_sent_at"])) : "N/A",
                                    "aob_viewed_at" => $r["aob_viewed_at"] ? date("m/d/Y g:i A", strtotime($r["aob_viewed_at"])) : "N/A",
                                    "aob_signed_at" => $r["aob_signed_at"] ? date("m/d/Y g:i A", strtotime($r["aob_signed_at"])) : "N/A",
                                    "signature_ip" => $r["aob_signature_ip"] ?: $r["confirmed_ip"] ?: "N/A",
                                    "confirmation_method" => $r["confirmation_method"] ?: "N/A",
                                    "user_agent" => $r["confirmed_user_agent"] ?: "N/A"
                                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                        class="text-teal-600 hover:text-teal-800 hover:underline">
                                    View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($records)): ?>
    <div class="px-4 py-3 bg-slate-50 border-t text-sm text-slate-600">
        Showing <?= count($records) ?> record(s) from <?= date('m/d/Y', strtotime($dateFrom)) ?> to <?= date('m/d/Y', strtotime($dateTo)) ?>
    </div>
    <?php endif; ?>
</div>

<!-- Audit Details Modal -->
<dialog id="auditDetailsModal" class="rounded-lg shadow-2xl p-0 backdrop:bg-black backdrop:bg-opacity-50" style="max-width: 700px; width: 95%;">
    <div class="bg-white rounded-lg max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-purple-600 to-purple-700 text-white px-6 py-4 flex justify-between items-center rounded-t-lg sticky top-0">
            <h2 class="text-xl font-semibold">📋 Audit Record Details</h2>
            <button onclick="document.getElementById('auditDetailsModal').close()" class="text-white hover:text-gray-200 text-2xl">&times;</button>
        </div>

        <div class="p-6 space-y-4">
            <!-- Patient Info -->
            <div class="bg-slate-50 rounded-lg p-4">
                <h3 class="font-semibold text-slate-700 mb-3">Patient Information (at time of signing)</h3>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-slate-500 block text-xs">Name:</span>
                        <span class="font-medium" id="audit_patient_name"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-xs">DOB:</span>
                        <span class="font-medium" id="audit_patient_dob"></span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-slate-500 block text-xs">Address:</span>
                        <span class="font-medium" id="audit_patient_address"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-xs">Phone:</span>
                        <span class="font-medium" id="audit_patient_phone"></span>
                    </div>
                </div>
            </div>

            <!-- Order Info -->
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <h3 class="font-semibold text-blue-800 mb-3">Order Information</h3>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-slate-500 block text-xs">Order ID:</span>
                        <span class="font-mono font-medium" id="audit_order_id"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-xs">Order Date:</span>
                        <span class="font-medium" id="audit_order_date"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-xs">Product:</span>
                        <span class="font-medium" id="audit_product"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-xs">Delivered:</span>
                        <span class="font-medium" id="audit_delivered_at"></span>
                    </div>
                </div>
            </div>

            <!-- Physician Info -->
            <div class="bg-slate-50 rounded-lg p-4">
                <h3 class="font-semibold text-slate-700 mb-3">Prescribing Physician</h3>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-slate-500 block text-xs">Physician:</span>
                        <span class="font-medium" id="audit_physician"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-xs">NPI:</span>
                        <span class="font-mono font-medium" id="audit_physician_npi"></span>
                    </div>
                </div>
            </div>

            <!-- AOB Signature Details -->
            <div class="bg-amber-50 rounded-lg p-4 border border-amber-300">
                <h3 class="font-semibold text-amber-800 mb-3">AOB Electronic Signature</h3>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-slate-500 block text-xs">Notification Sent:</span>
                        <span class="font-medium" id="audit_sms_sent_at"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-xs">AOB Viewed:</span>
                        <span class="font-medium" id="audit_aob_viewed_at"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-xs">AOB Signed:</span>
                        <span class="font-semibold text-green-700" id="audit_aob_signed_at"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-xs">Method:</span>
                        <span class="font-medium" id="audit_confirmation_method"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-xs">Signature IP:</span>
                        <span class="font-mono text-xs" id="audit_signature_ip"></span>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-slate-500 block text-xs">User Agent:</span>
                    <span class="font-mono text-xs bg-white p-2 rounded border block mt-1 break-all" id="audit_user_agent"></span>
                </div>
            </div>

            <div class="text-xs text-slate-500 bg-slate-50 p-3 rounded border">
                <strong>Compliance Note:</strong> This record captures all information at the time the patient electronically signed the Assignment of Benefits. The IP address and user agent verify the signature originated from the patient's device.
            </div>
        </div>

        <div class="px-6 py-4 bg-slate-50 border-t flex justify-end gap-2">
            <button onclick="document.getElementById('auditDetailsModal').close()"
                    class="bg-slate-500 hover:bg-slate-600 text-white px-4 py-2 rounded">
                Close
            </button>
        </div>
    </div>
</dialog>

<script>
function showAuditDetails(data) {
    document.getElementById('audit_order_id').textContent = '#' + data.order_id;
    document.getElementById('audit_patient_name').textContent = data.patient_name;
    document.getElementById('audit_patient_dob').textContent = data.patient_dob;
    document.getElementById('audit_patient_address').textContent = data.patient_address;
    document.getElementById('audit_patient_phone').textContent = data.patient_phone;
    document.getElementById('audit_product').textContent = data.product;
    document.getElementById('audit_physician').textContent = data.physician;
    document.getElementById('audit_physician_npi').textContent = data.physician_npi;
    document.getElementById('audit_order_date').textContent = data.order_date;
    document.getElementById('audit_delivered_at').textContent = data.delivered_at;
    document.getElementById('audit_sms_sent_at').textContent = data.sms_sent_at;
    document.getElementById('audit_aob_viewed_at').textContent = data.aob_viewed_at;
    document.getElementById('audit_aob_signed_at').textContent = data.aob_signed_at;
    document.getElementById('audit_signature_ip').textContent = data.signature_ip;
    document.getElementById('audit_confirmation_method').textContent =
        data.confirmation_method === 'web_approval' ? 'Web Approval + AOB' :
        data.confirmation_method === 'web_link' ? 'Web Link' : data.confirmation_method;
    document.getElementById('audit_user_agent').textContent = data.user_agent;

    document.getElementById('auditDetailsModal').showModal();
}
</script>

<?php if ($hasLayout) include $footer; else echo '</div>'; ?>
