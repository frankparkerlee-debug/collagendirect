<?php
/**
 * Distributor Management - Phase 10e
 *
 * Relocated from /admin/sales-reps.php to Admin Settings > Distributors.
 * This is a wrapper that includes the existing sales-reps.php functionality
 * with updated terminology.
 *
 * Sub-tabs:
 * - Active Distributors (default)
 * - Pending Applications
 * - Assignment Requests
 * - Commission Payouts
 *
 * Accessible to: superadmin, manufacturer, admin, sales
 */
declare(strict_types=1);
require __DIR__ . '/../auth.php';
require_admin();

// Sales reps cannot access admin settings
if (function_exists('deny_sales_rep')) deny_sales_rep();

// Load permission helper
require_once __DIR__ . '/../lib/permissions.php';

// Check permission
if (!has_permission('admin_settings.distributors.view')) {
    header('Location: /admin/index.php');
    exit;
}

$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$canManage = has_permission('admin_settings.distributors.manage', 'full');

// Handle tab routing
$activeTab = $_GET['tab'] ?? 'active';

// Redirect old URL patterns to new structure
if (isset($_GET['redirect_from'])) {
    // Already at new location, just continue
}

// Include the main sales-reps logic
// We define a flag to indicate we're in the new UI
define('DISTRIBUTOR_UI_MODE', true);

// Process form submissions - same logic as sales-reps.php
require_once __DIR__ . '/../../api/lib/email_notifications.php';

$message = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'approve_rep':
                $repId = $_POST['rep_id'] ?? '';
                // Form passes percentage (e.g., 15 for 15%), convert to decimal (0.15)
                $commissionRate = floatval($_POST['commission_rate'] ?? 15) / 100;

                if ($repId) {
                    $pdo->prepare("UPDATE sales_reps SET status = 'active', approved_date = NOW(), approved_by = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$admin['id'], $repId]);

                    $pdo->prepare("INSERT INTO rep_commission_rates (rep_id, rate, effective_date, set_by, notes, created_at) VALUES (?, ?, CURRENT_DATE, ?, 'Initial rate on approval', NOW())")
                        ->execute([$repId, $commissionRate, $admin['id']]);

                    // Send approval email
                    sendDistributorApprovalEmail($pdo, $repId);

                    $message = 'Distributor approved successfully.';
                }
                break;

            case 'reject_rep':
                $repId = $_POST['rep_id'] ?? '';
                $reason = $_POST['rejection_reason'] ?? '';

                if ($repId) {
                    $pdo->prepare("UPDATE sales_reps SET status = 'rejected', notes = CONCAT(COALESCE(notes, ''), '\nRejected: ', ?), updated_at = NOW() WHERE id = ?")
                        ->execute([$reason, $repId]);

                    sendDistributorRejectionEmail($pdo, $repId, $reason);
                    $message = 'Application rejected.';
                }
                break;

            case 'suspend_rep':
                $repId = $_POST['rep_id'] ?? '';
                if ($repId) {
                    $pdo->prepare("UPDATE sales_reps SET status = 'suspended', updated_at = NOW() WHERE id = ?")->execute([$repId]);
                    $message = 'Distributor suspended.';
                }
                break;

            case 'reactivate_rep':
                $repId = $_POST['rep_id'] ?? '';
                if ($repId) {
                    $pdo->prepare("UPDATE sales_reps SET status = 'active', updated_at = NOW() WHERE id = ?")->execute([$repId]);
                    $message = 'Distributor reactivated.';
                }
                break;

            case 'terminate_rep':
                $repId = $_POST['rep_id'] ?? '';
                if ($repId) {
                    $pdo->prepare("UPDATE sales_reps SET status = 'terminated', updated_at = NOW() WHERE id = ?")->execute([$repId]);
                    $message = 'Distributor terminated.';
                }
                break;

            case 'approve_assignment':
                $requestId = $_POST['request_id'] ?? '';
                if ($requestId) {
                    $reqStmt = $pdo->prepare("SELECT * FROM rep_assignment_requests WHERE id = ?");
                    $reqStmt->execute([$requestId]);
                    $request = $reqStmt->fetch();

                    if ($request) {
                        $pdo->prepare("UPDATE users SET assigned_rep_id = ?, rep_assignment_date = NOW(), rep_assigned_by = 'admin', rep_assigned_by_user_id = ? WHERE id = ?")
                            ->execute([$request['rep_id'], $admin['id'], $request['clinic_id']]);

                        $pdo->prepare("UPDATE rep_assignment_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                            ->execute([$admin['id'], $requestId]);

                        $message = 'Assignment request approved.';
                    }
                }
                break;

            case 'deny_assignment':
                $requestId = $_POST['request_id'] ?? '';
                $reason = $_POST['denial_reason'] ?? '';
                if ($requestId) {
                    $pdo->prepare("UPDATE rep_assignment_requests SET status = 'denied', denial_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                        ->execute([$reason, $admin['id'], $requestId]);
                    $message = 'Assignment request denied.';
                }
                break;

            case 'record_payout':
                $repId = $_POST['rep_id'] ?? '';
                $amount = floatval($_POST['amount'] ?? 0);
                $paymentMethod = $_POST['payment_method'] ?? 'check';
                $referenceNumber = $_POST['reference_number'] ?? '';
                $periodStart = $_POST['period_start'] ?? null;
                $periodEnd = $_POST['period_end'] ?? null;
                $notes = $_POST['notes'] ?? '';

                if ($repId && $amount > 0) {
                    require_once __DIR__ . '/../../api/lib/commission.php';
                    $balanceInfo = get_commission_balance($pdo, $repId);
                    $currentBalance = $balanceInfo['balance'];

                    if ($amount > $currentBalance + 0.01) {
                        $error = 'Payout amount ($' . number_format($amount, 2) . ') exceeds available balance ($' . number_format($currentBalance, 2) . ')';
                    } else {
                        $pdo->prepare("INSERT INTO rep_commission_payouts (rep_id, amount, payment_method, reference_number, period_start, period_end, payout_date, processed_by, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE, ?, ?, NOW())")
                            ->execute([$repId, $amount, $paymentMethod, $referenceNumber ?: null, $periodStart ?: null, $periodEnd ?: null, $admin['id'], $notes ?: null]);

                        $message = 'Payout of $' . number_format($amount, 2) . ' recorded successfully.';
                    }
                }
                break;

            case 'approve_w9':
                $w9Id = (int)($_POST['w9_id'] ?? 0);
                if ($w9Id) {
                    // Get W9 submission details
                    $w9Stmt = $pdo->prepare("SELECT rep_id FROM rep_w9_submissions WHERE id = ?");
                    $w9Stmt->execute([$w9Id]);
                    $w9 = $w9Stmt->fetch();

                    if ($w9) {
                        // Update W9 submission status
                        $pdo->prepare("UPDATE rep_w9_submissions SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                            ->execute([$admin['id'], $w9Id]);

                        // Update sales_reps W9 status
                        $pdo->prepare("UPDATE sales_reps SET w9_status = 'approved', w9_approved_at = NOW(), updated_at = NOW() WHERE id = ?")
                            ->execute([$w9['rep_id']]);

                        // Send approval email
                        if (function_exists('send_generic_email')) {
                            $repStmt = $pdo->prepare("SELECT u.email, u.first_name FROM sales_reps sr JOIN users u ON u.id = sr.user_id WHERE sr.id = ?");
                            $repStmt->execute([$w9['rep_id']]);
                            $rep = $repStmt->fetch();
                            if ($rep) {
                                send_generic_email(
                                    $rep['email'],
                                    "W9 Approved - CollagenDirect",
                                    "Hi {$rep['first_name']},\n\nYour W9 form has been reviewed and approved. You are now eligible to receive commission payouts.\n\nThank you,\nCollagenDirect Team"
                                );
                            }
                        }

                        $message = 'W9 approved successfully.';
                    }
                }
                break;

            case 'reject_w9':
                $w9Id = (int)($_POST['w9_id'] ?? 0);
                $reason = trim($_POST['rejection_reason'] ?? '');
                if ($w9Id) {
                    $w9Stmt = $pdo->prepare("SELECT rep_id FROM rep_w9_submissions WHERE id = ?");
                    $w9Stmt->execute([$w9Id]);
                    $w9 = $w9Stmt->fetch();

                    if ($w9) {
                        // Update W9 submission status
                        $pdo->prepare("UPDATE rep_w9_submissions SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ? WHERE id = ?")
                            ->execute([$admin['id'], $reason ?: null, $w9Id]);

                        // Update sales_reps W9 status
                        $pdo->prepare("UPDATE sales_reps SET w9_status = 'rejected', updated_at = NOW() WHERE id = ?")
                            ->execute([$w9['rep_id']]);

                        // Send rejection email
                        if (function_exists('send_generic_email')) {
                            $repStmt = $pdo->prepare("SELECT u.email, u.first_name FROM sales_reps sr JOIN users u ON u.id = sr.user_id WHERE sr.id = ?");
                            $repStmt->execute([$w9['rep_id']]);
                            $rep = $repStmt->fetch();
                            if ($rep) {
                                $reasonText = $reason ? "\n\nReason: {$reason}" : '';
                                send_generic_email(
                                    $rep['email'],
                                    "W9 Review - Action Required",
                                    "Hi {$rep['first_name']},\n\nYour W9 form submission has been reviewed and requires corrections.{$reasonText}\n\nPlease log in to your distributor portal and submit a new W9 form.\n\nThank you,\nCollagenDirect Team"
                                );
                            }
                        }

                        $message = 'W9 rejected. Distributor has been notified.';
                    }
                }
                break;

            case 'request_w9':
                $repId = $_POST['rep_id'] ?? '';
                if ($repId) {
                    // Send W9 request email using the dedicated function
                    require_once __DIR__ . '/../../api/lib/rep_notifications.php';
                    $repStmt = $pdo->prepare("SELECT u.email, u.first_name, u.last_name FROM sales_reps sr JOIN users u ON u.id = sr.user_id WHERE sr.id = ?");
                    $repStmt->execute([$repId]);
                    $rep = $repStmt->fetch();
                    if ($rep) {
                        $taxYear = (int)date('Y');
                        if (send_rep_w9_request($pdo, $rep['email'], $rep['first_name'] . ' ' . $rep['last_name'], $taxYear)) {
                            $message = 'W9 request email sent to ' . htmlspecialchars($rep['first_name']) . '.';
                        } else {
                            $error = 'Unable to send W9 request email. Please check email configuration.';
                        }
                    } else {
                        $error = 'Distributor not found.';
                    }
                }
                break;

            case 'invite_rep':
                $firstName = trim($_POST['invite_first_name'] ?? '');
                $lastName = trim($_POST['invite_last_name'] ?? '');
                $email = strtolower(trim($_POST['invite_email'] ?? ''));
                $phone = trim($_POST['invite_phone'] ?? '');
                $companyName = trim($_POST['invite_company_name'] ?? '');
                $commissionRate = floatval($_POST['invite_commission_rate'] ?? 25) / 100;
                $personalNote = trim($_POST['invite_note'] ?? '');

                if (!$firstName || !$lastName || !$email || !$phone) {
                    $error = 'Please fill in all required fields.';
                    break;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Invalid email address.';
                    break;
                }

                $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $checkEmail->execute([$email]);
                if ($checkEmail->fetch()) {
                    $error = 'A user with this email already exists.';
                    break;
                }

                $inviteToken = bin2hex(random_bytes(32));
                $inviteExpires = date('Y-m-d H:i:s', strtotime('+7 days'));

                $userId = bin2hex(random_bytes(16));
                $pdo->prepare("INSERT INTO users (id, email, first_name, last_name, phone, role, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'physician', '', NOW(), NOW())")
                    ->execute([$userId, $email, $firstName, $lastName, $phone]);

                $repId = bin2hex(random_bytes(16));
                // invited_by references users.id - only set if admin is from users table (superadmin or sales_rep)
                // admin_users (employees) don't have a users.id, so set to null
                $invitedBy = null;
                if ($admin['role'] === 'superadmin' || $admin['role'] === 'sales_rep') {
                    // These roles come from users table, so $admin['id'] is a valid users.id
                    $invitedBy = $admin['id'];
                }
                $pdo->prepare("INSERT INTO sales_reps (id, user_id, status, company_name, invite_token, invite_token_expires_at, invited_by, application_date, created_at, updated_at) VALUES (?, ?, 'invited', ?, ?, ?, ?, NOW(), NOW(), NOW())")
                    ->execute([$repId, $userId, $companyName ?: null, $inviteToken, $inviteExpires, $invitedBy]);

                $pdo->prepare("INSERT INTO rep_commission_rates (rep_id, rate, effective_date, set_by, notes, created_at) VALUES (?, ?, CURRENT_DATE, ?, 'Set on invite', NOW())")
                    ->execute([$repId, $commissionRate, $admin['id']]);

                sendDistributorInviteEmail($pdo, $repId, $inviteToken, $personalNote);

                $message = "Invite sent to {$firstName} {$lastName} at {$email}. The invite expires in 7 days.";
                break;
        }

        if ($message && !$error) {
            header('Location: /admin/platform/distributors.php?tab=' . $activeTab . '&msg=' . urlencode($message));
            exit;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Fetch data based on active tab
$activeReps = [];
$pendingReps = [];
$invitedReps = [];
$assignmentRequests = [];
$payoutData = [];

// Active distributors
// Commission rate query matches sales-rep-detail.php: effective_date <= today, order by effective_date DESC then created_at DESC
$activeReps = $pdo->query("
    SELECT sr.*, u.first_name, u.last_name, u.email, u.phone,
           (SELECT COUNT(*) FROM users WHERE assigned_rep_id = sr.id) as clinic_count,
           (SELECT rate FROM rep_commission_rates WHERE rep_id = sr.id AND (effective_date IS NULL OR effective_date <= CURRENT_DATE) ORDER BY effective_date DESC NULLS LAST, created_at DESC LIMIT 1) as commission_rate
    FROM sales_reps sr
    JOIN users u ON u.id = sr.user_id
    WHERE sr.status = 'active'
    ORDER BY u.first_name, u.last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Pending applications
$pendingReps = $pdo->query("
    SELECT sr.*, u.first_name, u.last_name, u.email, u.phone
    FROM sales_reps sr
    JOIN users u ON u.id = sr.user_id
    WHERE sr.status = 'pending'
    ORDER BY sr.application_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Invited (pending completion)
$invitedReps = $pdo->query("
    SELECT sr.*, u.first_name, u.last_name, u.email, u.phone,
           inviter.first_name as inviter_first, inviter.last_name as inviter_last
    FROM sales_reps sr
    JOIN users u ON u.id = sr.user_id
    LEFT JOIN users inviter ON inviter.id = sr.invited_by
    WHERE sr.status = 'invited'
    ORDER BY sr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Assignment requests
try {
    $assignmentRequests = $pdo->query("
        SELECT rar.*,
               u.first_name as rep_first, u.last_name as rep_last,
               clinic.practice_name, clinic.first_name as clinic_first, clinic.last_name as clinic_last
        FROM rep_assignment_requests rar
        JOIN sales_reps sr ON sr.id = rar.rep_id
        JOIN users u ON u.id = sr.user_id
        JOIN users clinic ON clinic.id = rar.clinic_id
        WHERE rar.status = 'pending'
        ORDER BY rar.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table may not exist
    $assignmentRequests = [];
}

// Payout data
require_once __DIR__ . '/../../api/lib/commission.php';
$payoutData = [];
foreach ($activeReps as $rep) {
    $balance = get_commission_balance($pdo, $rep['id']);
    if ($balance['balance'] > 0) {
        $payoutData[] = array_merge($rep, ['pending_balance' => $balance['balance'], 'pending_entries' => $balance['pending_entries']]);
    }
}

// W9 Review data
$pendingW9s = [];
try {
    $pendingW9s = $pdo->query("
        SELECT w9.*, sr.company_name, u.first_name, u.last_name, u.email
        FROM rep_w9_submissions w9
        JOIN sales_reps sr ON sr.id = w9.rep_id
        JOIN users u ON u.id = sr.user_id
        WHERE w9.status = 'pending'
        ORDER BY w9.submitted_at ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table may not exist yet
    $pendingW9s = [];
}

// Get distributors missing W9 (active reps without approved W9)
$missingW9s = [];
try {
    $missingW9s = $pdo->query("
        SELECT sr.id, sr.company_name, sr.w9_status, u.first_name, u.last_name, u.email
        FROM sales_reps sr
        JOIN users u ON u.id = sr.user_id
        WHERE sr.status = 'active'
        AND (sr.w9_status IS NULL OR sr.w9_status IN ('none', 'rejected', 'expired'))
        ORDER BY u.last_name, u.first_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $missingW9s = [];
}

// Count badges
$pendingCount = count($pendingReps) + count($invitedReps);
$requestCount = count($assignmentRequests);
$payoutCount = count($payoutData);
$w9Count = count($pendingW9s);

// Email helper functions (simplified versions)
function sendDistributorApprovalEmail($pdo, $repId) {
    // Use existing email system
    require_once __DIR__ . '/../../api/lib/rep_notifications.php';
    $stmt = $pdo->prepare("SELECT u.email, u.first_name, u.last_name FROM sales_reps sr JOIN users u ON u.id = sr.user_id WHERE sr.id = ?");
    $stmt->execute([$repId]);
    $rep = $stmt->fetch();
    if ($rep) {
        send_rep_approved($pdo, $rep['email'], $rep['first_name'] . ' ' . $rep['last_name']);
    }
}

function sendDistributorRejectionEmail($pdo, $repId, $reason) {
    require_once __DIR__ . '/../../api/lib/rep_notifications.php';
    $stmt = $pdo->prepare("SELECT u.email, u.first_name, u.last_name FROM sales_reps sr JOIN users u ON u.id = sr.user_id WHERE sr.id = ?");
    $stmt->execute([$repId]);
    $rep = $stmt->fetch();
    if ($rep) {
        send_rep_rejected($pdo, $rep['email'], $rep['first_name'] . ' ' . $rep['last_name'], $reason);
    }
}

function sendDistributorInviteEmail($pdo, $repId, $inviteToken, $personalNote) {
    require_once __DIR__ . '/../../api/lib/rep_notifications.php';
    $stmt = $pdo->prepare("SELECT u.email, u.first_name, u.last_name FROM sales_reps sr JOIN users u ON u.id = sr.user_id WHERE sr.id = ?");
    $stmt->execute([$repId]);
    $rep = $stmt->fetch();
    if ($rep) {
        send_rep_invite($pdo, $rep['email'], $rep['first_name'] . ' ' . $rep['last_name'], $inviteToken, $personalNote);
    }
}
?>
<?php include __DIR__ . '/../_header.php'; ?>

<div class="max-w-7xl mx-auto">
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Distributors</h1>
            <p class="text-sm text-gray-500 mt-1">Manage distributor accounts, applications, and commissions</p>
        </div>
        <?php if ($canManage): ?>
        <div class="flex gap-2">
            <button onclick="document.getElementById('invite-modal').showModal()"
                    class="btn btn-primary flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Invite Distributor
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Sub-tabs -->
    <div class="mb-4 border-b">
        <nav class="flex gap-4">
            <a href="?tab=active" class="px-4 py-2 border-b-2 <?= $activeTab === 'active' ? 'border-brand text-brand font-medium' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                Active Distributors
                <span class="ml-1 text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full"><?= count($activeReps) ?></span>
            </a>
            <a href="?tab=pending" class="px-4 py-2 border-b-2 <?= $activeTab === 'pending' ? 'border-brand text-brand font-medium' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                Pending Applications
                <?php if ($pendingCount > 0): ?>
                <span class="ml-1 text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=w9review" class="px-4 py-2 border-b-2 <?= $activeTab === 'w9review' ? 'border-brand text-brand font-medium' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                W9 Review
                <?php if ($w9Count > 0): ?>
                <span class="ml-1 text-xs bg-orange-100 text-orange-800 px-2 py-0.5 rounded-full"><?= $w9Count ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=requests" class="px-4 py-2 border-b-2 <?= $activeTab === 'requests' ? 'border-brand text-brand font-medium' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                Assignment Requests
                <?php if ($requestCount > 0): ?>
                <span class="ml-1 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full"><?= $requestCount ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=payouts" class="px-4 py-2 border-b-2 <?= $activeTab === 'payouts' ? 'border-brand text-brand font-medium' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                Commission Payouts
                <?php if ($payoutCount > 0): ?>
                <span class="ml-1 text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded-full"><?= $payoutCount ?></span>
                <?php endif; ?>
            </a>
        </nav>
    </div>

    <!-- Tab Content -->
    <div class="bg-white border rounded-lg">
        <?php if ($activeTab === 'active'): ?>
        <!-- Active Distributors -->
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Distributor</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Contact</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Company</th>
                    <th class="text-center py-3 px-4 font-semibold text-gray-600">Clinics</th>
                    <th class="text-center py-3 px-4 font-semibold text-gray-600">Commission</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activeReps)): ?>
                <tr><td colspan="6" class="py-8 text-center text-gray-500">No active distributors.</td></tr>
                <?php else: ?>
                <?php foreach ($activeReps as $rep): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4">
                        <div class="font-medium"><?= htmlspecialchars($rep['first_name'] . ' ' . $rep['last_name']) ?></div>
                        <div class="text-xs text-gray-500">Since <?= date('M j, Y', strtotime($rep['approved_date'] ?? $rep['created_at'])) ?></div>
                    </td>
                    <td class="py-3 px-4">
                        <div><?= htmlspecialchars($rep['email']) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($rep['phone'] ?? '-') ?></div>
                    </td>
                    <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($rep['company_name'] ?? '-') ?></td>
                    <td class="py-3 px-4 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                            <?= $rep['clinic_count'] ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <?= $rep['commission_rate'] ? number_format($rep['commission_rate'] * 100, 0) . '%' : '-' ?>
                    </td>
                    <td class="py-3 px-4">
                        <a href="/admin/sales-rep-detail.php?id=<?= urlencode($rep['id']) ?>" class="text-blue-600 hover:underline text-xs">View</a>
                        <?php if ($canManage): ?>
                        <form method="post" class="inline ml-2" onsubmit="return confirm('Suspend this distributor?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="suspend_rep">
                            <input type="hidden" name="rep_id" value="<?= htmlspecialchars($rep['id']) ?>">
                            <button class="text-yellow-600 hover:underline text-xs">Suspend</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php elseif ($activeTab === 'pending'): ?>
        <!-- Pending Applications -->
        <?php if (!empty($pendingReps)): ?>
        <div class="p-4 border-b">
            <h3 class="font-semibold text-gray-700">Pending Review</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Applicant</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Contact</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Company</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Applied</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingReps as $rep): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4 font-medium"><?= htmlspecialchars($rep['first_name'] . ' ' . $rep['last_name']) ?></td>
                    <td class="py-3 px-4">
                        <div><?= htmlspecialchars($rep['email']) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($rep['phone'] ?? '-') ?></div>
                    </td>
                    <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($rep['company_name'] ?? '-') ?></td>
                    <td class="py-3 px-4 text-gray-500 text-xs"><?= date('M j, Y', strtotime($rep['application_date'])) ?></td>
                    <td class="py-3 px-4">
                        <?php if ($canManage): ?>
                        <button onclick="showApproveModal('<?= htmlspecialchars($rep['id']) ?>', '<?= htmlspecialchars(addslashes($rep['first_name'] . ' ' . $rep['last_name'])) ?>')"
                                class="text-green-600 hover:underline text-xs">Approve</button>
                        <button onclick="showRejectModal('<?= htmlspecialchars($rep['id']) ?>', '<?= htmlspecialchars(addslashes($rep['first_name'] . ' ' . $rep['last_name'])) ?>')"
                                class="text-red-600 hover:underline text-xs ml-2">Reject</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if (!empty($invitedReps)): ?>
        <div class="p-4 border-b <?= !empty($pendingReps) ? 'border-t mt-4' : '' ?>">
            <h3 class="font-semibold text-gray-700">Invited (Awaiting Completion)</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Invitee</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Email</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Invited By</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Expires</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invitedReps as $rep): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4 font-medium"><?= htmlspecialchars($rep['first_name'] . ' ' . $rep['last_name']) ?></td>
                    <td class="py-3 px-4"><?= htmlspecialchars($rep['email']) ?></td>
                    <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars(($rep['inviter_first'] ?? '') . ' ' . ($rep['inviter_last'] ?? '')) ?></td>
                    <td class="py-3 px-4 text-gray-500 text-xs">
                        <?php
                        $expires = strtotime($rep['invite_token_expires_at']);
                        $isExpired = $expires < time();
                        ?>
                        <span class="<?= $isExpired ? 'text-red-600' : '' ?>">
                            <?= date('M j, Y', $expires) ?>
                            <?= $isExpired ? '(Expired)' : '' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if (empty($pendingReps) && empty($invitedReps)): ?>
        <div class="py-8 text-center text-gray-500">No pending applications.</div>
        <?php endif; ?>

        <?php elseif ($activeTab === 'w9review'): ?>
        <!-- W9 Review -->
        <?php if (!empty($pendingW9s)): ?>
        <div class="p-4 border-b">
            <h3 class="font-semibold text-gray-700">Pending W9 Submissions</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Distributor</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Company</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Tax Year</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Submitted</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Document</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingW9s as $w9): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4">
                        <div class="font-medium"><?= htmlspecialchars($w9['first_name'] . ' ' . $w9['last_name']) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($w9['email']) ?></div>
                    </td>
                    <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($w9['company_name'] ?? '-') ?></td>
                    <td class="py-3 px-4"><?= htmlspecialchars((string)$w9['tax_year']) ?></td>
                    <td class="py-3 px-4 text-gray-500 text-xs"><?= date('M j, Y g:i A', strtotime($w9['submitted_at'])) ?></td>
                    <td class="py-3 px-4">
                        <a href="/<?= htmlspecialchars($w9['file_path']) ?>" target="_blank" class="text-blue-600 hover:underline text-xs flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            View
                        </a>
                        <div class="text-xs text-gray-400"><?= htmlspecialchars($w9['file_name']) ?></div>
                    </td>
                    <td class="py-3 px-4">
                        <?php if ($canManage): ?>
                        <form method="post" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve_w9">
                            <input type="hidden" name="w9_id" value="<?= (int)$w9['id'] ?>">
                            <button class="text-green-600 hover:underline text-xs">Approve</button>
                        </form>
                        <button onclick="showRejectW9Modal(<?= (int)$w9['id'] ?>, '<?= htmlspecialchars(addslashes($w9['first_name'] . ' ' . $w9['last_name'])) ?>')"
                                class="text-red-600 hover:underline text-xs ml-2">Reject</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if (!empty($missingW9s)): ?>
        <div class="p-4 border-b <?= !empty($pendingW9s) ? 'border-t mt-4' : '' ?>">
            <h3 class="font-semibold text-gray-700">Missing W9 Forms</h3>
            <p class="text-xs text-gray-500 mt-1">Active distributors who haven't submitted an approved W9</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Distributor</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Company</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Status</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($missingW9s as $rep): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4">
                        <div class="font-medium"><?= htmlspecialchars($rep['first_name'] . ' ' . $rep['last_name']) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($rep['email']) ?></div>
                    </td>
                    <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($rep['company_name'] ?? '-') ?></td>
                    <td class="py-3 px-4">
                        <?php
                        $statusLabels = [
                            'none' => ['bg-gray-100 text-gray-800', 'Not Submitted'],
                            'rejected' => ['bg-red-100 text-red-800', 'Rejected'],
                            'expired' => ['bg-orange-100 text-orange-800', 'Expired'],
                        ];
                        $status = $rep['w9_status'] ?? 'none';
                        $statusInfo = $statusLabels[$status] ?? $statusLabels['none'];
                        ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $statusInfo[0] ?>">
                            <?= $statusInfo[1] ?>
                        </span>
                    </td>
                    <td class="py-3 px-4">
                        <?php if ($canManage): ?>
                        <form method="post" class="inline" onsubmit="return confirm('Send W9 request email to this distributor?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="request_w9">
                            <input type="hidden" name="rep_id" value="<?= htmlspecialchars($rep['id']) ?>">
                            <button class="text-brand hover:underline text-xs">Send Request</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if (empty($pendingW9s) && empty($missingW9s)): ?>
        <div class="py-8 text-center text-gray-500">All distributors have valid W9 forms on file.</div>
        <?php endif; ?>

        <?php elseif ($activeTab === 'requests'): ?>
        <!-- Assignment Requests -->
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Distributor</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Requesting Clinic</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Requested</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assignmentRequests)): ?>
                <tr><td colspan="4" class="py-8 text-center text-gray-500">No pending assignment requests.</td></tr>
                <?php else: ?>
                <?php foreach ($assignmentRequests as $req): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4 font-medium"><?= htmlspecialchars($req['rep_first'] . ' ' . $req['rep_last']) ?></td>
                    <td class="py-3 px-4">
                        <div><?= htmlspecialchars($req['practice_name'] ?: $req['clinic_first'] . ' ' . $req['clinic_last']) ?></div>
                    </td>
                    <td class="py-3 px-4 text-gray-500 text-xs"><?= date('M j, Y', strtotime($req['created_at'])) ?></td>
                    <td class="py-3 px-4">
                        <?php if ($canManage): ?>
                        <form method="post" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve_assignment">
                            <input type="hidden" name="request_id" value="<?= htmlspecialchars($req['id']) ?>">
                            <button class="text-green-600 hover:underline text-xs">Approve</button>
                        </form>
                        <button onclick="showDenyAssignmentModal('<?= htmlspecialchars($req['id']) ?>')"
                                class="text-red-600 hover:underline text-xs ml-2">Deny</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php elseif ($activeTab === 'payouts'): ?>
        <!-- Commission Payouts -->
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Distributor</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Company</th>
                    <th class="text-right py-3 px-4 font-semibold text-gray-600">Pending Balance</th>
                    <th class="text-center py-3 px-4 font-semibold text-gray-600">Entries</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payoutData)): ?>
                <tr><td colspan="5" class="py-8 text-center text-gray-500">No pending commission payouts.</td></tr>
                <?php else: ?>
                <?php foreach ($payoutData as $rep): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4 font-medium"><?= htmlspecialchars($rep['first_name'] . ' ' . $rep['last_name']) ?></td>
                    <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($rep['company_name'] ?? '-') ?></td>
                    <td class="py-3 px-4 text-right font-mono text-green-600 font-medium">
                        $<?= number_format($rep['pending_balance'], 2) ?>
                    </td>
                    <td class="py-3 px-4 text-center text-gray-500"><?= $rep['pending_entries'] ?></td>
                    <td class="py-3 px-4">
                        <?php if ($canManage): ?>
                        <button onclick="showPayoutModal('<?= htmlspecialchars($rep['id']) ?>', '<?= htmlspecialchars(addslashes($rep['first_name'] . ' ' . $rep['last_name'])) ?>', <?= $rep['pending_balance'] ?>)"
                                class="text-brand hover:underline text-xs">Record Payout</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modals -->
<?php if ($canManage): ?>

<!-- Invite Modal -->
<dialog id="invite-modal" class="rounded-xl shadow-xl w-full max-w-lg p-0 backdrop:bg-black/50">
    <form method="post" class="p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="invite_rep">

        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Invite Distributor</h2>
            <button type="button" onclick="document.getElementById('invite-modal').close()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                    <input type="text" name="invite_first_name" required class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                    <input type="text" name="invite_last_name" required class="w-full border rounded-lg px-3 py-2">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                <input type="email" name="invite_email" required class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                <input type="tel" name="invite_phone" required class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                <input type="text" name="invite_company_name" class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Commission Rate (%)</label>
                <input type="number" name="invite_commission_rate" value="15" min="0" max="100" step="0.1" class="w-24 border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Personal Note (optional)</label>
                <textarea name="invite_note" rows="2" class="w-full border rounded-lg px-3 py-2" placeholder="Add a personal note to the invite email..."></textarea>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
            <button type="button" onclick="document.getElementById('invite-modal').close()" class="btn">Cancel</button>
            <button type="submit" class="btn btn-primary">Send Invite</button>
        </div>
    </form>
</dialog>

<!-- Approve Modal -->
<dialog id="approve-modal" class="rounded-xl shadow-xl w-full max-w-sm p-0 backdrop:bg-black/50">
    <form method="post" class="p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="approve_rep">
        <input type="hidden" name="rep_id" id="approve-rep-id">

        <h2 class="text-xl font-semibold mb-4">Approve Distributor</h2>
        <p class="text-sm text-gray-600 mb-4">Approve <strong id="approve-rep-name"></strong> as a distributor?</p>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Commission Rate (%)</label>
            <input type="number" name="commission_rate" value="15" min="0" max="100" step="0.1" class="w-24 border rounded-lg px-3 py-2">
        </div>

        <div class="flex justify-end gap-3">
            <button type="button" onclick="document.getElementById('approve-modal').close()" class="btn">Cancel</button>
            <button type="submit" class="btn btn-primary">Approve</button>
        </div>
    </form>
</dialog>

<!-- Reject Modal -->
<dialog id="reject-modal" class="rounded-xl shadow-xl w-full max-w-sm p-0 backdrop:bg-black/50">
    <form method="post" class="p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reject_rep">
        <input type="hidden" name="rep_id" id="reject-rep-id">

        <h2 class="text-xl font-semibold mb-4">Reject Application</h2>
        <p class="text-sm text-gray-600 mb-4">Reject <strong id="reject-rep-name"></strong>'s application?</p>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
            <textarea name="rejection_reason" rows="3" class="w-full border rounded-lg px-3 py-2"></textarea>
        </div>

        <div class="flex justify-end gap-3">
            <button type="button" onclick="document.getElementById('reject-modal').close()" class="btn">Cancel</button>
            <button type="submit" class="btn" style="background: var(--error); color: white;">Reject</button>
        </div>
    </form>
</dialog>

<!-- Deny Assignment Modal -->
<dialog id="deny-assignment-modal" class="rounded-xl shadow-xl w-full max-w-sm p-0 backdrop:bg-black/50">
    <form method="post" class="p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="deny_assignment">
        <input type="hidden" name="request_id" id="deny-request-id">

        <h2 class="text-xl font-semibold mb-4">Deny Assignment Request</h2>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
            <textarea name="denial_reason" rows="3" class="w-full border rounded-lg px-3 py-2"></textarea>
        </div>

        <div class="flex justify-end gap-3">
            <button type="button" onclick="document.getElementById('deny-assignment-modal').close()" class="btn">Cancel</button>
            <button type="submit" class="btn" style="background: var(--error); color: white;">Deny</button>
        </div>
    </form>
</dialog>

<!-- Payout Modal -->
<dialog id="payout-modal" class="rounded-xl shadow-xl w-full max-w-md p-0 backdrop:bg-black/50">
    <form method="post" class="p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="record_payout">
        <input type="hidden" name="rep_id" id="payout-rep-id">

        <h2 class="text-xl font-semibold mb-4">Record Payout</h2>
        <p class="text-sm text-gray-600 mb-4">Recording payout for <strong id="payout-rep-name"></strong></p>
        <p class="text-sm text-gray-600 mb-4">Available balance: <strong id="payout-balance" class="text-green-600"></strong></p>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Amount ($) *</label>
                <input type="number" name="amount" id="payout-amount" step="0.01" min="0.01" required class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
                <select name="payment_method" required class="w-full border rounded-lg px-3 py-2">
                    <option value="check">Check</option>
                    <option value="ach">ACH Transfer</option>
                    <option value="wire">Wire Transfer</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                <input type="text" name="reference_number" class="w-full border rounded-lg px-3 py-2" placeholder="Check #, ACH ID, etc.">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Period Start</label>
                    <input type="date" name="period_start" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Period End</label>
                    <input type="date" name="period_end" class="w-full border rounded-lg px-3 py-2">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full border rounded-lg px-3 py-2"></textarea>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
            <button type="button" onclick="document.getElementById('payout-modal').close()" class="btn">Cancel</button>
            <button type="submit" class="btn btn-primary">Record Payout</button>
        </div>
    </form>
</dialog>

<!-- Reject W9 Modal -->
<dialog id="reject-w9-modal" class="rounded-xl shadow-xl w-full max-w-sm p-0 backdrop:bg-black/50">
    <form method="post" class="p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reject_w9">
        <input type="hidden" name="w9_id" id="reject-w9-id">

        <h2 class="text-xl font-semibold mb-4">Reject W9</h2>
        <p class="text-sm text-gray-600 mb-4">Reject W9 submission from <strong id="reject-w9-name"></strong>?</p>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Reason (will be sent to distributor)</label>
            <textarea name="rejection_reason" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="e.g., Document is illegible, missing signature, incorrect tax year..."></textarea>
        </div>

        <div class="flex justify-end gap-3">
            <button type="button" onclick="document.getElementById('reject-w9-modal').close()" class="btn">Cancel</button>
            <button type="submit" class="btn" style="background: var(--error); color: white;">Reject W9</button>
        </div>
    </form>
</dialog>

<script>
function showApproveModal(repId, repName) {
    document.getElementById('approve-rep-id').value = repId;
    document.getElementById('approve-rep-name').textContent = repName;
    document.getElementById('approve-modal').showModal();
}

function showRejectModal(repId, repName) {
    document.getElementById('reject-rep-id').value = repId;
    document.getElementById('reject-rep-name').textContent = repName;
    document.getElementById('reject-modal').showModal();
}

function showDenyAssignmentModal(requestId) {
    document.getElementById('deny-request-id').value = requestId;
    document.getElementById('deny-assignment-modal').showModal();
}

function showPayoutModal(repId, repName, balance) {
    document.getElementById('payout-rep-id').value = repId;
    document.getElementById('payout-rep-name').textContent = repName;
    document.getElementById('payout-balance').textContent = '$' + balance.toFixed(2);
    document.getElementById('payout-amount').value = balance.toFixed(2);
    document.getElementById('payout-amount').max = balance.toFixed(2);
    document.getElementById('payout-modal').showModal();
}

function showRejectW9Modal(w9Id, repName) {
    document.getElementById('reject-w9-id').value = w9Id;
    document.getElementById('reject-w9-name').textContent = repName;
    document.getElementById('reject-w9-modal').showModal();
}
</script>

<?php endif; ?>

<?php include __DIR__ . '/../_footer.php'; ?>
