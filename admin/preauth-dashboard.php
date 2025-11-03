<?php
/**
 * Preauthorization Dashboard
 *
 * Admin interface for managing insurance preauthorization requests.
 * Shows all preauth requests with filtering and status management.
 *
 * @package CollagenDirect
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Check authentication using existing admin auth pattern
require_admin();
$admin = current_admin();

// Use global $pdo from db.php

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$carrierFilter = $_GET['carrier'] ?? 'all';
$dateRange = $_GET['date_range'] ?? '30'; // days

// Build query
$whereClauses = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereClauses[] = "pr.status = :status";
    $params[':status'] = $statusFilter;
}

if ($carrierFilter !== 'all') {
    $whereClauses[] = "pr.carrier_name = :carrier";
    $params[':carrier'] = $carrierFilter;
}

if ($dateRange !== 'all') {
    $whereClauses[] = "pr.created_at > NOW() - INTERVAL '{$dateRange} days'";
}

$whereSQL = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get preauth requests
$stmt = $pdo->prepare("
    SELECT
        pr.*,
        p.first_name,
        p.last_name,
        p.email as patient_email,
        o.wound_type,
        o.product,
        u.email as physician_email
    FROM preauth_requests pr
    JOIN patients p ON pr.patient_id = p.id
    JOIN orders o ON pr.order_id = o.id
    LEFT JOIN users u ON o.user_id = u.id
    {$whereSQL}
    ORDER BY pr.created_at DESC
    LIMIT 100
");

$stmt->execute($params);
$preauths = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsStmt = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'denied' THEN 1 ELSE 0 END) as denied,
        SUM(CASE WHEN status = 'need_info' THEN 1 ELSE 0 END) as need_info,
        SUM(CASE WHEN auto_submitted THEN 1 ELSE 0 END) as auto_submitted
    FROM preauth_requests
    WHERE created_at > NOW() - INTERVAL '30 days'
");

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get unique carriers for filter dropdown
$carriersStmt = $pdo->query("
    SELECT DISTINCT carrier_name
    FROM preauth_requests
    ORDER BY carrier_name
");
$carriers = $carriersStmt->fetchAll(PDO::FETCH_COLUMN);

// Generate CSRF token
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* ================= View ================= */
include __DIR__.'/_header.php';
?>
<style>
        .preauth-dashboard {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }

        .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #2c5282;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .filters {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filters select, .filters button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .preauth-table {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }

        .preauth-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .preauth-table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }

        .preauth-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .preauth-table tr:hover {
            background: #f7fafc;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-submitted { background: #dbeafe; color: #1e40af; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-denied { background: #fee2e2; color: #991b1b; }
        .status-need_info { background: #fce7f3; color: #831843; }
        .status-expired { background: #e5e7eb; color: #374151; }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary { background: #3b82f6; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-small { padding: 4px 8px; font-size: 12px; }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            max-width: 600px;
            border-radius: 8px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close {
            font-size: 28px;
            cursor: pointer;
            color: #666;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-group textarea {
            min-height: 100px;
        }
</style>

<div class="preauth-dashboard">
        <h1>Preauthorization Dashboard</h1>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Requests (30 days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['submitted'] ?></div>
                <div class="stat-label">Submitted</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['approved'] ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['denied'] ?></div>
                <div class="stat-label">Denied</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['auto_submitted'] ?></div>
                <div class="stat-label">Auto-Submitted</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <label>
                Status:
                <select id="statusFilter" onchange="applyFilters()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="submitted" <?= $statusFilter === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="denied" <?= $statusFilter === 'denied' ? 'selected' : '' ?>>Denied</option>
                    <option value="need_info" <?= $statusFilter === 'need_info' ? 'selected' : '' ?>>Need Info</option>
                </select>
            </label>

            <label>
                Carrier:
                <select id="carrierFilter" onchange="applyFilters()">
                    <option value="all">All Carriers</option>
                    <?php foreach ($carriers as $carrier): ?>
                        <option value="<?= htmlspecialchars($carrier) ?>" <?= $carrierFilter === $carrier ? 'selected' : '' ?>>
                            <?= htmlspecialchars($carrier) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Date Range:
                <select id="dateRangeFilter" onchange="applyFilters()">
                    <option value="7" <?= $dateRange === '7' ? 'selected' : '' ?>>Last 7 days</option>
                    <option value="30" <?= $dateRange === '30' ? 'selected' : '' ?>>Last 30 days</option>
                    <option value="90" <?= $dateRange === '90' ? 'selected' : '' ?>>Last 90 days</option>
                    <option value="all" <?= $dateRange === 'all' ? 'selected' : '' ?>>All time</option>
                </select>
            </label>

            <button class="btn btn-secondary" onclick="resetFilters()">Reset Filters</button>
        </div>

        <!-- Preauth Requests Table -->
        <div class="preauth-table">
            <table>
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Carrier</th>
                        <th>HCPCS</th>
                        <th>Product</th>
                        <th>Status</th>
                        <th>Preauth #</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($preauths) === 0): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                No preauthorization requests found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($preauths as $preauth): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($preauth['first_name'] . ' ' . $preauth['last_name']) ?></strong><br>
                                    <small><?= htmlspecialchars($preauth['patient_email']) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($preauth['carrier_name']) ?></strong><br>
                                    <small><?= htmlspecialchars($preauth['member_id']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($preauth['hcpcs_code']) ?></td>
                                <td>
                                    <?= htmlspecialchars($preauth['product_name']) ?><br>
                                    <small>Qty: <?= $preauth['quantity_requested'] ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $preauth['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $preauth['status'])) ?>
                                    </span>
                                    <?php if ($preauth['auto_submitted']): ?>
                                        <br><small style="color: #059669;">Auto</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($preauth['preauth_number']): ?>
                                        <strong><?= htmlspecialchars($preauth['preauth_number']) ?></strong>
                                    <?php else: ?>
                                        <span style="color: #999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= date('M j, Y', strtotime($preauth['created_at'])) ?><br>
                                    <small><?= date('g:i A', strtotime($preauth['created_at'])) ?></small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-primary btn-small" onclick="viewDetails('<?= $preauth['id'] ?>')">
                                            View
                                        </button>
                                        <button class="btn btn-secondary btn-small" onclick="updateStatus('<?= $preauth['id'] ?>')">
                                            Update
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Preauth Status</h2>
                <span class="close" onclick="closeModal('updateStatusModal')">&times;</span>
            </div>
            <form id="updateStatusForm">
                <input type="hidden" id="updatePreauthId" name="preauth_request_id">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf'] ?>">

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="pending">Pending</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                        <option value="denied">Denied</option>
                        <option value="need_info">Need Info</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="preauthNumber">Preauth Number (optional)</label>
                    <input type="text" id="preauthNumber" name="preauth_number" placeholder="Carrier-assigned preauth number">
                </div>

                <div class="form-group">
                    <label for="notes">Notes (optional)</label>
                    <textarea id="notes" name="notes" placeholder="Add any notes about this status update"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Update Status</button>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = '<?= $_SESSION['csrf'] ?>';

        function applyFilters() {
            const status = document.getElementById('statusFilter').value;
            const carrier = document.getElementById('carrierFilter').value;
            const dateRange = document.getElementById('dateRangeFilter').value;

            const params = new URLSearchParams();
            if (status !== 'all') params.append('status', status);
            if (carrier !== 'all') params.append('carrier', carrier);
            if (dateRange !== 'all') params.append('date_range', dateRange);

            window.location.href = '?' + params.toString();
        }

        function resetFilters() {
            window.location.href = window.location.pathname;
        }

        function viewDetails(preauthId) {
            window.location.href = '/admin/preauth-details.php?id=' + preauthId;
        }

        function updateStatus(preauthId) {
            document.getElementById('updatePreauthId').value = preauthId;
            document.getElementById('updateStatusModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Handle update status form submission
        document.getElementById('updateStatusForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch('/api/preauth.php?action=preauth.updateStatus', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.ok) {
                    alert('Status updated successfully');
                    window.location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Failed to update status: ' + error.message);
            }
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        };
    </script>
</div>
<?php include __DIR__.'/_footer.php'; ?>
