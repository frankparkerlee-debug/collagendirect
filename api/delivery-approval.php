<?php
declare(strict_types=1);

/**
 * Patient Delivery Approval Page
 *
 * New workflow:
 * 1. Product marked as delivered
 * 2. SMS sent to patient with link to THIS page
 * 3. Patient reviews order details and AOB
 * 4. Patient clicks "Approve & Sign" to confirm
 *
 * Stores compliance data: IP, patient info, order info, physician info, signature time
 *
 * URL: https://collagendirect.health/api/delivery-approval.php?token={token}
 */

require_once __DIR__ . '/../admin/db.php';
require_once __DIR__ . '/lib/timezone.php';

// Get confirmation token from URL
$token = $_GET['token'] ?? '';
$action = $_POST['action'] ?? '';

if (empty($token)) {
    showErrorPage('Invalid Link', 'The confirmation link is missing required information. Please use the link from your text message.');
    exit;
}

try {
    // Look up confirmation by token with full order/patient/physician details
    $stmt = $pdo->prepare("
        SELECT dc.id, dc.order_id, dc.confirmed_at, dc.sms_sent_at, dc.created_at,
               dc.aob_viewed_at, dc.aob_signed_at,
               o.product, o.delivered_at, o.frequency, o.duration_days, o.created_at as order_date,
               o.qty_per_change, o.product_id, o.product_price,
               p.id as patient_id, p.first_name, p.last_name, p.dob, p.address, p.city, p.state, p.zip,
               p.phone as patient_phone, p.email as patient_email,
               p.insurance_provider, p.insurance_member_id,
               u.first_name as phys_first, u.last_name as phys_last, u.npi as phys_npi,
               u.practice_name, u.phone as phys_phone,
               pr.name as product_name, pr.size as product_size, pr.cpt_code
        FROM delivery_confirmations dc
        JOIN orders o ON o.id = dc.order_id
        JOIN patients p ON p.id = o.patient_id
        LEFT JOIN users u ON u.id = o.user_id
        LEFT JOIN products pr ON pr.id = o.product_id
        WHERE dc.confirmation_token = ?
    ");
    $stmt->execute([$token]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        showErrorPage('Invalid Link', 'This confirmation link is not valid. It may have expired or been used already.');
        exit;
    }

    // Check if token is expired (7 days from SMS sent)
    if ($data['sms_sent_at']) {
        $sentTime = strtotime($data['sms_sent_at']);
        $expiryTime = $sentTime + (7 * 24 * 60 * 60); // 7 days

        if (time() > $expiryTime) {
            showErrorPage(
                'Link Expired',
                'This confirmation link has expired (valid for 7 days). Please contact your physician\'s office if you need assistance.'
            );
            exit;
        }
    }

    // Handle form submission (approval)
    if ($action === 'approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        handleApproval($pdo, $data, $token);
        // After approval, reload data and show confirmed page
        $stmt->execute([$token]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $confirmedDate = format_datetime_central($data['aob_signed_at'] ?? 'now');
        showApprovalPage($data, $token, true, $confirmedDate);
        exit;
    }

    // Check if already confirmed/signed - show same page with grayed button
    if ($data['aob_signed_at']) {
        $signedDate = format_datetime_central($data['aob_signed_at']);
        showApprovalPage($data, $token, true, $signedDate);
        exit;
    }

    // Record that patient viewed the AOB (if not already recorded)
    if (!$data['aob_viewed_at']) {
        $pdo->prepare("UPDATE delivery_confirmations SET aob_viewed_at = NOW(), updated_at = NOW() WHERE id = ?")
            ->execute([$data['id']]);
    }

    // Show the approval page (not yet confirmed)
    showApprovalPage($data, $token, false);

} catch (Throwable $e) {
    error_log("[delivery-approval] Error: " . $e->getMessage());
    showErrorPage('System Error', 'We encountered an error processing your request. Please try again later.');
}

/**
 * Handle the approval/signing action
 */
function handleApproval(PDO $pdo, array $data, string $token): void {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Build snapshots for compliance record
    $patientName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
    $patientAddress = trim(implode(', ', array_filter([
        $data['address'] ?? '',
        $data['city'] ?? '',
        $data['state'] ?? '',
        $data['zip'] ?? ''
    ])));
    $physicianName = trim(($data['phys_first'] ?? '') . ' ' . ($data['phys_last'] ?? ''));
    $productDisplay = trim(($data['product_name'] ?? $data['product'] ?? '') . ' ' . ($data['product_size'] ?? ''));

    // Update confirmation record with all compliance data
    $updateStmt = $pdo->prepare("
        UPDATE delivery_confirmations
        SET confirmed_at = NOW(),
            confirmation_method = 'web_approval',
            confirmed_ip = ?,
            confirmed_user_agent = ?,
            aob_signed_at = NOW(),
            aob_signature_ip = ?,
            aob_signature_user_agent = ?,
            patient_name_snapshot = ?,
            patient_dob_snapshot = ?,
            patient_address_snapshot = ?,
            order_product_snapshot = ?,
            order_physician_snapshot = ?,
            order_physician_npi_snapshot = ?,
            order_date_snapshot = ?,
            updated_at = NOW()
        WHERE confirmation_token = ?
    ");

    $updateStmt->execute([
        $ip,
        $userAgent,
        $ip,
        $userAgent,
        $patientName,
        $data['dob'] ?? null,
        $patientAddress,
        $productDisplay,
        $physicianName,
        $data['phys_npi'] ?? null,
        $data['order_date'] ?? null,
        $token
    ]);

    error_log("[delivery-approval] Order {$data['order_id']} approved and AOB signed by {$patientName} from IP {$ip}");
}

/**
 * Show the main approval page with order details and AOB
 * After confirmation, page remains accessible with grayed button showing confirmed status
 */
function showApprovalPage(array $data, string $token, bool $isConfirmed = false, ?string $confirmedDate = null): void {
    $patientName = htmlspecialchars(trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')));
    $patientFirstName = htmlspecialchars($data['first_name'] ?? 'Patient');
    $product = htmlspecialchars(trim(($data['product_name'] ?? $data['product'] ?? 'Wound Care Supplies') . ' ' . ($data['product_size'] ?? '')));
    $orderId = htmlspecialchars(substr($data['order_id'], 0, 8));
    $physicianName = htmlspecialchars(trim(($data['phys_first'] ?? '') . ' ' . ($data['phys_last'] ?? '')));
    $practiceName = htmlspecialchars($data['practice_name'] ?? '');
    $deliveredDate = $data['delivered_at'] ? date('F j, Y', strtotime($data['delivered_at'])) : 'Recently';
    $dob = $data['dob'] ? date('m/d/Y', strtotime($data['dob'])) : 'N/A';
    $hcpcsCode = htmlspecialchars($data['cpt_code'] ?? '');
    $quantity = (int)($data['qty_per_change'] ?? 1);
    if ($quantity < 1) $quantity = 1;
    $patientAddress = htmlspecialchars(trim(implode(', ', array_filter([
        $data['address'] ?? '',
        $data['city'] ?? '',
        $data['state'] ?? '',
        $data['zip'] ?? ''
    ]))));

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $isConfirmed ? 'Delivery Confirmed' : 'Confirm Your Delivery'; ?> - MD DME</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background: linear-gradient(135deg, <?php echo $isConfirmed ? '#10b981 0%, #059669' : '#0d9488 0%, #14b8a6'; ?> 100%);
                min-height: 100vh;
                padding: 1rem;
            }
            .container {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.2);
                max-width: 600px;
                margin: 0 auto;
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, <?php echo $isConfirmed ? '#10b981, #059669' : '#0d9488, #14b8a6'; ?>);
                color: white;
                padding: 1.5rem;
                text-align: center;
            }
            .header h1 {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
            .header p {
                opacity: 0.9;
                font-size: 0.9rem;
            }
            .content {
                padding: 1.5rem;
            }
            .greeting {
                font-size: 1.1rem;
                color: #1e293b;
                margin-bottom: 1rem;
                line-height: 1.5;
            }
            .confirm-btn {
                background: <?php echo $isConfirmed ? '#94a3b8' : 'linear-gradient(135deg, #10b981, #059669)'; ?>;
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 12px;
                text-align: center;
                margin-bottom: 1.5rem;
                cursor: <?php echo $isConfirmed ? 'default' : 'pointer'; ?>;
                border: none;
                width: 100%;
                font-size: 1.1rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            <?php if (!$isConfirmed): ?>
            .confirm-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
            }
            .confirm-btn:active {
                transform: translateY(0);
            }
            <?php endif; ?>
            .confirmed-badge {
                background: #dcfce7;
                border: 2px solid #86efac;
                border-radius: 12px;
                padding: 1rem;
                margin-bottom: 1.5rem;
                text-align: center;
            }
            .confirmed-badge-icon {
                width: 48px;
                height: 48px;
                background: #10b981;
                border-radius: 50%;
                margin: 0 auto 0.75rem;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .confirmed-badge-icon svg {
                width: 28px;
                height: 28px;
                color: white;
            }
            .confirmed-badge h3 {
                color: #166534;
                font-size: 1.1rem;
                margin-bottom: 0.25rem;
            }
            .confirmed-badge p {
                color: #15803d;
                font-size: 0.875rem;
            }
            .section {
                background: #f8fafc;
                border-radius: 12px;
                padding: 1.25rem;
                margin-bottom: 1rem;
            }
            .section-title {
                font-size: 0.8rem;
                font-weight: 600;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 0.75rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .section-title svg {
                width: 16px;
                height: 16px;
            }
            .detail-row {
                display: flex;
                justify-content: space-between;
                padding: 0.5rem 0;
                border-bottom: 1px solid #e2e8f0;
            }
            .detail-row:last-child {
                border-bottom: none;
            }
            .detail-label {
                color: #64748b;
                font-size: 0.875rem;
            }
            .detail-value {
                color: #1e293b;
                font-weight: 500;
                font-size: 0.875rem;
                text-align: right;
            }
            .aob-section {
                background: <?php echo $isConfirmed ? '#f0fdf4' : '#fef3c7'; ?>;
                border: 2px solid <?php echo $isConfirmed ? '#86efac' : '#fcd34d'; ?>;
                border-radius: 12px;
                padding: 1.25rem;
                margin-bottom: 1rem;
            }
            .aob-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                cursor: pointer;
            }
            .aob-title {
                font-weight: 600;
                color: <?php echo $isConfirmed ? '#166534' : '#92400e'; ?>;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .aob-toggle {
                color: <?php echo $isConfirmed ? '#166534' : '#92400e'; ?>;
                font-size: 0.8rem;
                text-decoration: underline;
            }
            .aob-content {
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid <?php echo $isConfirmed ? '#86efac' : '#fcd34d'; ?>;
                font-size: 0.85rem;
                color: <?php echo $isConfirmed ? '#15803d' : '#78350f'; ?>;
                line-height: 1.6;
                display: none;
            }
            .aob-content.expanded {
                display: block;
            }
            .aob-preview {
                margin-top: 0.75rem;
                font-size: 0.85rem;
                color: <?php echo $isConfirmed ? '#15803d' : '#78350f'; ?>;
                line-height: 1.5;
            }
            .aob-signed-info {
                margin-top: 0.75rem;
                padding-top: 0.75rem;
                border-top: 1px solid <?php echo $isConfirmed ? '#86efac' : '#fcd34d'; ?>;
                font-size: 0.8rem;
                color: #166534;
            }
            .footer {
                text-align: center;
                padding: 1rem;
                font-size: 0.8rem;
                color: #64748b;
                border-top: 1px solid #e2e8f0;
            }
            .footer a {
                color: #0d9488;
            }
            @media (max-width: 480px) {
                body { padding: 0.5rem; }
                .content { padding: 1rem; }
                .detail-row { flex-direction: column; gap: 0.25rem; }
                .detail-value { text-align: left; }
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>MD DME</h1>
                <p><?php echo $isConfirmed ? 'Delivery Confirmed' : 'Delivery Confirmation'; ?></p>
            </div>

            <div class="content">
                <?php if ($isConfirmed): ?>
                <!-- Confirmed State -->
                <div class="confirmed-badge">
                    <div class="confirmed-badge-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h3>Delivery Confirmed & AOB Signed</h3>
                    <p>Confirmed on <?php echo htmlspecialchars($confirmedDate); ?></p>
                </div>

                <p class="greeting">
                    Thank you, <?php echo $patientFirstName; ?>! Your delivery has been confirmed and the Assignment of Benefits has been signed. Your order details are below for your records.
                </p>

                <!-- Grayed out button -->
                <button type="button" class="confirm-btn" disabled>
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Already Confirmed
                </button>

                <?php else: ?>
                <!-- Pending Confirmation State -->
                <p class="greeting">
                    Hi <?php echo $patientFirstName; ?>, please confirm receipt of your wound care supplies by clicking the button below.
                </p>

                <form method="POST" action="?token=<?php echo htmlspecialchars($token); ?>" id="approval-form">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="confirm-btn" id="confirm-btn">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Confirm & Sign
                    </button>
                </form>
                <?php endif; ?>

                <!-- Order Details -->
                <div class="section">
                    <div class="section-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Order Details
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Order ID</span>
                        <span class="detail-value">#<?php echo $orderId; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Product</span>
                        <span class="detail-value"><?php echo $product; ?></span>
                    </div>
                    <?php if ($hcpcsCode): ?>
                    <div class="detail-row">
                        <span class="detail-label">HCPCS</span>
                        <span class="detail-value"><?php echo $hcpcsCode; ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="detail-label">Qty Delivered</span>
                        <span class="detail-value"><?php echo $quantity; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Delivered</span>
                        <span class="detail-value"><?php echo $deliveredDate; ?></span>
                    </div>
                </div>

                <!-- Patient Info -->
                <div class="section">
                    <div class="section-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Your Information
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Name</span>
                        <span class="detail-value"><?php echo $patientName; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date of Birth</span>
                        <span class="detail-value"><?php echo $dob; ?></span>
                    </div>
                    <?php if ($patientAddress): ?>
                    <div class="detail-row">
                        <span class="detail-label">Address</span>
                        <span class="detail-value"><?php echo $patientAddress; ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Physician Info -->
                <div class="section">
                    <div class="section-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Prescribing Physician
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Physician</span>
                        <span class="detail-value">Dr. <?php echo $physicianName; ?></span>
                    </div>
                    <?php if ($practiceName): ?>
                    <div class="detail-row">
                        <span class="detail-label">Practice</span>
                        <span class="detail-value"><?php echo $practiceName; ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- AOB Section -->
                <div class="aob-section">
                    <div class="aob-header" onclick="toggleAOB()">
                        <div class="aob-title">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Assignment of Benefits (AOB)
                            <?php if ($isConfirmed): ?><span style="font-size: 0.75rem; margin-left: 0.5rem;">- Signed</span><?php endif; ?>
                        </div>
                        <span class="aob-toggle" id="aob-toggle-text">View Full Document</span>
                    </div>
                    <p class="aob-preview">
                        <?php if ($isConfirmed): ?>
                        You have authorized your insurance to pay MD DME directly for wound care supplies provided to you.
                        <?php else: ?>
                        By clicking "Confirm & Sign", you authorize your insurance to pay MD DME directly for wound care supplies provided to you.
                        <?php endif; ?>
                    </p>
                    <?php if ($isConfirmed && $confirmedDate): ?>
                    <div class="aob-signed-info">
                        <strong>Signed:</strong> <?php echo htmlspecialchars($confirmedDate); ?>
                    </div>
                    <?php endif; ?>
                    <div class="aob-content" id="aob-content">
                        <p><strong>ASSIGNMENT OF BENEFITS AGREEMENT</strong></p>
                        <br>
                        <p><strong>Patient Name:</strong> <?php echo $patientName; ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo $dob; ?></p>
                        <p><strong>Product:</strong> <?php echo $product; ?></p>
                        <p><strong>Prescribing Physician:</strong> Dr. <?php echo $physicianName; ?></p>
                        <br>
                        <p>I, <?php echo $patientName; ?>, hereby authorize and assign all medical and surgical benefits, including Medicare, Medicaid, private insurance, and any other health plan benefits payable to me, to be paid directly to MD DME, LLC or its designated billing entity for wound care supplies and related services provided.</p>
                        <br>
                        <p>I understand that I am financially responsible for any charges not covered by my insurance. I authorize the release of any medical information necessary to process insurance claims.</p>
                        <br>
                        <p>I have received the wound care supplies described above and authorize MD DME to bill my insurance on my behalf.</p>
                        <br>
                        <p><strong>This authorization will remain in effect until revoked by me in writing.</strong></p>
                    </div>
                </div>
            </div>

            <div class="footer">
                <p>Questions? Contact your physician's office or email <a href="mailto:support@collagendirect.health">support@collagendirect.health</a></p>
                <p style="margin-top: 0.5rem;">&copy; <?php echo date('Y'); ?> MD DME, LLC</p>
            </div>
        </div>

        <script>
            // Toggle AOB full content
            function toggleAOB() {
                const content = document.getElementById('aob-content');
                const toggleText = document.getElementById('aob-toggle-text');
                content.classList.toggle('expanded');
                toggleText.textContent = content.classList.contains('expanded') ? 'Hide Full Document' : 'View Full Document';
            }

            <?php if (!$isConfirmed): ?>
            // Prevent double submission
            const form = document.getElementById('approval-form');
            const confirmBtn = document.getElementById('confirm-btn');

            form.addEventListener('submit', function(e) {
                if (confirmBtn.dataset.submitting === 'true') {
                    e.preventDefault();
                    return;
                }
                confirmBtn.dataset.submitting = 'true';
                confirmBtn.innerHTML = '<svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation: spin 1s linear infinite;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Processing...';
                confirmBtn.style.background = '#94a3b8';
                confirmBtn.style.cursor = 'not-allowed';
            });
            <?php endif; ?>
        </script>
    </body>
    </html>
    <?php
}

/**
 * Show error page
 */
function showErrorPage(string $title, string $message): void {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - MD DME</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
            }
            .container {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 500px;
                width: 100%;
                padding: 2.5rem;
                text-align: center;
            }
            .icon {
                width: 80px;
                height: 80px;
                margin: 0 auto 1.5rem;
                background: #ef4444;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .icon svg {
                width: 48px;
                height: 48px;
                color: white;
            }
            h1 {
                font-size: 1.75rem;
                color: #1e293b;
                margin-bottom: 1rem;
            }
            .message {
                font-size: 1.125rem;
                color: #475569;
                line-height: 1.6;
                margin-bottom: 2rem;
            }
            .footer {
                font-size: 0.875rem;
                color: #64748b;
                line-height: 1.5;
            }
            .footer a {
                color: #ef4444;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>

            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>

            <div class="footer">
                <p>Need help? Contact your physician's office.</p>
                <p style="margin-top: 1rem;">
                    Visit <a href="https://collagendirect.health">collagendirect.health</a> for more information.
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
}
