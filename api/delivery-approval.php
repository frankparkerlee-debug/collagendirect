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
               o.qty_per_change, o.actual_pieces, o.total_pieces, o.product_id, o.product_price,
               dc.pod_signed_at, dc.pod_signature_image, dc.pod_signature_typed, dc.pod_signed_by,
               dc.pod_designee_name, dc.pod_designee_relationship, dc.pod_quantity_confirmed,
               dc.pod_date_received, dc.pod_document_path,
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

    // Render the immutable signed Proof-of-Delivery document on demand (?doc=1)
    if (($_GET['doc'] ?? '') === '1') {
        if (!empty($data['pod_signed_at'])) { renderPodDocument($data); }
        else { showErrorPage('Not Signed', 'This proof of delivery has not been signed yet.'); }
        exit;
    }

    // Handle form submission (approval + POD signature)
    if ($action === 'approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $ok = handleApproval($pdo, $data, $token);
        // Reload data (now includes signature/POD fields)
        $stmt->execute([$token]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ok) {
            showApprovalPage($data, $token, true, format_datetime_central($data['pod_signed_at'] ?? 'now'));
        } else {
            showApprovalPage($data, $token, false, null, 'Please add your signature, type your full name, confirm the quantity received, and check the certification box.');
        }
        exit;
    }

    // Already signed → show confirmed page
    if ($data['pod_signed_at'] || $data['aob_signed_at']) {
        $signedDate = format_datetime_central($data['pod_signed_at'] ?? $data['aob_signed_at']);
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
function handleApproval(PDO $pdo, array $data, string $token): bool {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // POD inputs
    $sigImage   = trim((string)($_POST['pod_signature'] ?? ''));
    $sigTyped   = trim((string)($_POST['pod_signature_typed'] ?? ''));
    $signedBy   = (($_POST['pod_signed_by'] ?? 'beneficiary') === 'designee') ? 'designee' : 'beneficiary';
    $designeeName = trim((string)($_POST['pod_designee_name'] ?? ''));
    $designeeRel  = trim((string)($_POST['pod_designee_relationship'] ?? ''));
    $qtyConfirmed = (int)($_POST['pod_quantity_confirmed'] ?? 0);
    $qtyCorrect   = !empty($_POST['pod_quantity_correct']);
    $dateReceived = trim((string)($_POST['pod_date_received'] ?? ''));
    $podAck       = !empty($_POST['pod_ack']);

    // Server-side compliance validation (client also enforces)
    if (strpos($sigImage, 'data:image/') !== 0 || strlen($sigImage) < 200) return false; // real drawn signature
    if ($sigTyped === '') return false;
    if (!$podAck) return false;
    if ($qtyConfirmed <= 0) return false;
    if ($signedBy === 'designee' && ($designeeName === '' || $designeeRel === '')) return false;

    $receivedDate = $dateReceived !== '' ? date('Y-m-d', strtotime($dateReceived)) : date('Y-m-d');

    // Immutable snapshots for the POD record
    $patientName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
    $patientAddress = trim(implode(', ', array_filter([$data['address']??'', $data['city']??'', $data['state']??'', $data['zip']??''])));
    $physicianName = trim(($data['phys_first'] ?? '') . ' ' . ($data['phys_last'] ?? ''));
    $itemLine = trim(($data['product_name'] ?? $data['product'] ?? '') . ' ' . ($data['product_size'] ?? ''));
    if (!empty($data['cpt_code'])) $itemLine .= ' (' . $data['cpt_code'] . ')';

    $pdo->prepare("
        UPDATE delivery_confirmations SET
            confirmed_at = COALESCE(confirmed_at, NOW()),
            confirmation_method = 'web_pod_signed',
            confirmed_ip = ?, confirmed_user_agent = ?,
            aob_signed_at = NOW(), aob_signature_ip = ?, aob_signature_user_agent = ?,
            pod_signed_at = NOW(),
            pod_signature_image = ?, pod_signature_typed = ?,
            pod_signed_by = ?, pod_designee_name = ?, pod_designee_relationship = ?,
            pod_quantity_confirmed = ?, pod_quantity_correct = ?, pod_date_received = ?,
            pod_attestation_version = ?,
            patient_name_snapshot = ?, patient_dob_snapshot = ?, patient_address_snapshot = ?,
            order_product_snapshot = ?, order_physician_snapshot = ?, order_physician_npi_snapshot = ?,
            order_date_snapshot = ?, updated_at = NOW()
        WHERE confirmation_token = ?
    ")->execute([
        $ip, $userAgent, $ip, $userAgent,
        $sigImage, $sigTyped,
        $signedBy, ($signedBy === 'designee' ? $designeeName : null), ($signedBy === 'designee' ? $designeeRel : null),
        $qtyConfirmed, ($qtyCorrect ? 't' : 'f'), $receivedDate,
        'POD-1.0',
        $patientName, $data['dob'] ?? null, $patientAddress,
        $itemLine, $physicianName, $data['phys_npi'] ?? null,
        $data['order_date'] ?? null,
        $token,
    ]);

    // The stored document is rendered on demand from the immutable snapshot above.
    $docUrl = '/api/delivery-approval.php?token=' . urlencode($token) . '&doc=1';
    $pdo->prepare("UPDATE delivery_confirmations SET pod_document_path = ? WHERE confirmation_token = ?")->execute([$docUrl, $token]);

    error_log("[delivery-approval] POD signed for order {$data['order_id']} by {$patientName} ({$signedBy}) qty {$qtyConfirmed} from IP {$ip}");
    return true;
}

/**
 * Render the immutable, print-ready Proof-of-Delivery document from the frozen
 * snapshot data captured at signing time (Medicare 42 CFR §414.514 elements).
 */
function renderPodDocument(array $d): void {
    $e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES);
    $sigDate = $d['pod_signed_at'] ? date('F j, Y g:i A', strtotime($d['pod_signed_at'])) : '';
    $received = $d['pod_date_received'] ? date('F j, Y', strtotime($d['pod_date_received'])) : $sigDate;
    $dob = $d['patient_dob_snapshot'] ? date('m/d/Y', strtotime($d['patient_dob_snapshot'])) : '';
    $signedBy = $d['pod_signed_by'] ?: 'beneficiary';
    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html><html><head><meta charset="utf-8"><title>Proof of Delivery — <?=$e($d['patient_name_snapshot'])?></title>
    <style>
      @page{size:Letter;margin:0.75in}
      body{font-family:Georgia,'Times New Roman',serif;color:#111;font-size:12pt;line-height:1.5;max-width:720px;margin:0 auto;padding:24px}
      h1{font-size:18pt;margin:0 0 2px;color:#20419b} .sub{color:#555;font-size:10pt;margin-bottom:18px}
      .box{border:1px solid #bbb;border-radius:6px;padding:12px 16px;margin:12px 0}
      .row{display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px dotted #ddd}
      .row:last-child{border-bottom:0} .lbl{color:#555} .val{font-weight:bold;text-align:right}
      .cert{background:#f6f8fc;border:1px solid #ccd;border-radius:6px;padding:14px 16px;margin:16px 0;font-size:11pt}
      .sig{margin-top:8px} .sig img{max-height:90px;border-bottom:2px solid #111;display:block}
      .foot{margin-top:24px;font-size:9pt;color:#666;border-top:1px solid #ccc;padding-top:8px}
      @media print{button{display:none}}
    </style></head><body>
      <button onclick="window.print()" style="float:right;padding:6px 12px">Print / Save PDF</button>
      <h1>Proof of Delivery</h1>
      <div class="sub">MD DME, LLC &middot; Medicare Proof of Delivery (42 CFR §414.514)</div>

      <div class="box">
        <div class="row"><span class="lbl">Beneficiary</span><span class="val"><?=$e($d['patient_name_snapshot'])?></span></div>
        <div class="row"><span class="lbl">Date of Birth</span><span class="val"><?=$e($dob)?></span></div>
        <div class="row"><span class="lbl">Delivery Address</span><span class="val"><?=$e($d['patient_address_snapshot'])?></span></div>
      </div>

      <div class="box">
        <div class="row"><span class="lbl">Item delivered</span><span class="val"><?=$e($d['order_product_snapshot'])?></span></div>
        <div class="row"><span class="lbl">Quantity received (confirmed)</span><span class="val"><?=$e($d['pod_quantity_confirmed'])?></span></div>
        <div class="row"><span class="lbl">Prescribing physician</span><span class="val"><?=$e($d['order_physician_snapshot'])?> (NPI <?=$e($d['order_physician_npi_snapshot'])?>)</span></div>
        <div class="row"><span class="lbl">Date received</span><span class="val"><?=$e($received)?></span></div>
      </div>

      <div class="cert">
        I certify that I received the item(s) and quantity listed above on the date indicated. This constitutes my
        electronic proof of delivery.
      </div>

      <div class="box">
        <div class="row"><span class="lbl">Signed by</span><span class="val"><?= $signedBy === 'designee' ? 'Designee: '.$e($d['pod_designee_name']).' ('.$e($d['pod_designee_relationship']).')' : 'Beneficiary' ?></span></div>
        <div class="sig"><?php if (!empty($d['pod_signature_image'])): ?><img src="<?=$e($d['pod_signature_image'])?>" alt="signature"><?php endif; ?></div>
        <div class="row" style="margin-top:6px"><span class="lbl">Signature (typed)</span><span class="val"><?=$e($d['pod_signature_typed'])?></span></div>
        <div class="row"><span class="lbl">Signed (date/time)</span><span class="val"><?=$e($sigDate)?></span></div>
      </div>

      <div class="foot">
        Electronic signature captured under ESIGN/UETA. Recorded: IP <?=$e($d['confirmed_ip'])?>, attestation <?=$e($d['pod_attestation_version'])?>.
        This is an immutable record generated from data captured at signing.
      </div>
    </body></html><?php
}

/**
 * Show the main approval page with order details and AOB
 * After confirmation, page remains accessible with grayed button showing confirmed status
 */
function showApprovalPage(array $data, string $token, bool $isConfirmed = false, ?string $confirmedDate = null, ?string $error = null): void {
    $patientName = htmlspecialchars(trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')));
    $patientFirstName = htmlspecialchars($data['first_name'] ?? 'Patient');
    $product = htmlspecialchars(trim(($data['product_name'] ?? $data['product'] ?? 'Wound Care Supplies') . ' ' . ($data['product_size'] ?? '')));
    $orderId = htmlspecialchars(substr($data['order_id'], 0, 8));
    $physicianName = htmlspecialchars(trim(($data['phys_first'] ?? '') . ' ' . ($data['phys_last'] ?? '')));
    $practiceName = htmlspecialchars($data['practice_name'] ?? '');
    $deliveredDate = $data['delivered_at'] ? date('F j, Y', strtotime($data['delivered_at'])) : 'Recently';
    $dob = $data['dob'] ? date('m/d/Y', strtotime($data['dob'])) : 'N/A';
    $hcpcsCode = htmlspecialchars($data['cpt_code'] ?? '');
    $quantity = (int)($data['actual_pieces'] ?? $data['total_pieces'] ?? $data['qty_per_change'] ?? 1);
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
            .pod-field { margin: 14px 0; }
            .pod-lbl { display:block; font-weight:600; font-size:0.85rem; color:#334155; margin-bottom:6px; }
            .pod-input { width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:1rem; box-sizing:border-box; }
            .pod-check { display:flex; gap:8px; align-items:flex-start; font-size:0.9rem; color:#334155; line-height:1.4; cursor:pointer; }
            .pod-check input { margin-top:3px; width:18px; height:18px; flex:0 0 auto; }
            .pod-cert { background:#f6f8fc; border:1px solid #c7d2fe; border-radius:8px; padding:12px 14px; margin:14px 0; }
            .pod-sign { border:2px solid #20419b; }
            .sig-wrap { position:relative; }
            .sig-pad { width:100%; height:180px; border:1px dashed #94a3b8; border-radius:8px; background:#fff; touch-action:none; display:block; }
            .sig-clear { position:absolute; top:8px; right:8px; padding:4px 10px; font-size:0.75rem; background:#e2e8f0; border:none; border-radius:6px; cursor:pointer; }
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
                    <h3>Proof of Delivery Signed</h3>
                    <p>Signed on <?php echo htmlspecialchars($confirmedDate); ?></p>
                </div>

                <p class="greeting">
                    Thank you, <?php echo $patientFirstName; ?>! Your proof of delivery is complete and on file. Your record is below.
                </p>

                <?php if (!empty($data['pod_signed_at'])): ?>
                <div class="section" style="text-align:center;">
                    <?php if (!empty($data['pod_signature_image'])): ?>
                    <img src="<?php echo htmlspecialchars($data['pod_signature_image']); ?>" alt="Your signature" style="max-height:70px;border-bottom:2px solid #111;margin-bottom:8px;">
                    <?php endif; ?>
                    <div style="font-size:0.8rem;color:#475569;">
                        Signed by <?php echo $data['pod_signed_by']==='designee' ? htmlspecialchars($data['pod_designee_name'].' ('.$data['pod_designee_relationship'].')') : 'you'; ?>
                        &middot; Qty received: <strong><?php echo (int)($data['pod_quantity_confirmed'] ?? 0); ?></strong>
                    </div>
                    <a href="?token=<?php echo htmlspecialchars($token); ?>&doc=1" target="_blank"
                       style="display:inline-block;margin-top:10px;padding:8px 16px;background:#20419b;color:#fff;text-decoration:none;border-radius:8px;font-size:0.85rem;font-weight:600;">
                        View / Print Proof of Delivery
                    </a>
                </div>
                <?php endif; ?>

                <button type="button" class="confirm-btn" disabled>
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Delivery Confirmed
                </button>

                <?php else: ?>
                <!-- Pending Confirmation State -->
                <?php if ($error): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:12px 14px;border-radius:8px;margin-bottom:14px;font-size:0.9rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                <p class="greeting">
                    Hi <?php echo $patientFirstName; ?>, please review the items below, confirm the quantity you received, and sign to complete your proof of delivery.
                </p>
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

                <?php if (!$isConfirmed): ?>
                <!-- Proof of Delivery: quantity confirm + receipt date + designee + signature -->
                <div class="section pod-sign">
                    <div class="section-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Complete Your Proof of Delivery
                    </div>
                    <form method="POST" action="?token=<?php echo htmlspecialchars($token); ?>" id="approval-form">
                        <input type="hidden" name="action" value="approve">

                        <div class="pod-field">
                            <label class="pod-check"><input type="checkbox" id="qty-ok" checked>
                                I received <strong><?php echo $quantity; ?></strong> unit(s) of the item shown above.</label>
                        </div>
                        <input type="hidden" name="pod_quantity_confirmed" id="pod_qty" value="<?php echo $quantity; ?>">
                        <input type="hidden" name="pod_quantity_correct" id="pod_qty_correct" value="1">

                        <div class="pod-field">
                            <label class="pod-lbl">Date you received these supplies</label>
                            <input type="date" name="pod_date_received" id="pod_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" class="pod-input" required>
                        </div>

                        <div class="pod-field">
                            <label class="pod-check"><input type="checkbox" id="is-designee" onchange="toggleDesignee()">
                                I am signing on behalf of the patient (I am not the patient).</label>
                            <div id="designee-fields" style="display:none;margin-top:8px;">
                                <input type="text" name="pod_designee_name" id="designee_name" placeholder="Your full name" class="pod-input" style="margin-bottom:8px;">
                                <input type="text" name="pod_designee_relationship" id="designee_rel" placeholder="Relationship to patient (e.g. spouse, caregiver)" class="pod-input">
                            </div>
                        </div>
                        <input type="hidden" name="pod_signed_by" id="pod_signed_by" value="beneficiary">

                        <div class="pod-cert">
                            <label class="pod-check"><input type="checkbox" id="pod-ack" name="pod_ack" value="1">
                                <span>I certify that I have <strong>received the item(s) and quantity shown above</strong> on the date indicated, and I authorize the Assignment of Benefits above.</span></label>
                        </div>

                        <div class="pod-field">
                            <label class="pod-lbl">Signature — sign with your finger or mouse</label>
                            <div class="sig-wrap">
                                <canvas id="sig-pad" class="sig-pad"></canvas>
                                <button type="button" class="sig-clear" onclick="clearSig()">Clear</button>
                            </div>
                            <input type="hidden" name="pod_signature" id="pod_signature">
                        </div>

                        <div class="pod-field">
                            <label class="pod-lbl">Type your full name</label>
                            <input type="text" name="pod_signature_typed" id="pod_typed" placeholder="Full legal name" class="pod-input" autocomplete="name" required>
                        </div>

                        <button type="submit" class="confirm-btn" id="confirm-btn">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Sign &amp; Confirm Delivery
                        </button>
                        <p style="font-size:0.72rem;color:#64748b;margin-top:10px;text-align:center;">
                            By signing, you agree this electronic signature is legally binding (ESIGN/UETA). Your IP address, device, and timestamp are recorded.
                        </p>
                    </form>
                </div>
                <?php endif; ?>
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
            function toggleDesignee() {
                const on = document.getElementById('is-designee').checked;
                document.getElementById('designee-fields').style.display = on ? 'block' : 'none';
                document.getElementById('pod_signed_by').value = on ? 'designee' : 'beneficiary';
                document.getElementById('designee_name').required = on;
                document.getElementById('designee_rel').required = on;
            }
            document.getElementById('qty-ok').addEventListener('change', function(){
                document.getElementById('pod_qty_correct').value = this.checked ? '1' : '0';
            });

            // Signature pad (finger/mouse)
            const canvas = document.getElementById('sig-pad');
            const ctx = canvas.getContext('2d');
            let drawing = false, hasSig = false;
            function sizeCanvas(){
                const ratio = window.devicePixelRatio || 1;
                const rect = canvas.getBoundingClientRect();
                canvas.width = Math.max(1, rect.width * ratio);
                canvas.height = Math.max(1, rect.height * ratio);
                ctx.scale(ratio, ratio); ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.strokeStyle = '#111';
            }
            function pos(e){ const r = canvas.getBoundingClientRect(); const t = e.touches ? e.touches[0] : e; return { x: t.clientX - r.left, y: t.clientY - r.top }; }
            function startDraw(e){ drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
            function moveDraw(e){ if(!drawing) return; const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); hasSig = true; e.preventDefault(); }
            function endDraw(){ drawing = false; }
            canvas.addEventListener('mousedown', startDraw); canvas.addEventListener('mousemove', moveDraw); window.addEventListener('mouseup', endDraw);
            canvas.addEventListener('touchstart', startDraw, {passive:false}); canvas.addEventListener('touchmove', moveDraw, {passive:false}); canvas.addEventListener('touchend', endDraw);
            function clearSig(){ ctx.clearRect(0,0,canvas.width,canvas.height); hasSig = false; document.getElementById('pod_signature').value=''; }
            setTimeout(sizeCanvas, 60);

            const form = document.getElementById('approval-form');
            const confirmBtn = document.getElementById('confirm-btn');
            form.addEventListener('submit', function(e) {
                if (confirmBtn.dataset.submitting === 'true') { e.preventDefault(); return; }
                if (!hasSig) { e.preventDefault(); alert('Please sign in the signature box.'); return; }
                if (!document.getElementById('pod_typed').value.trim()) { e.preventDefault(); alert('Please type your full name.'); return; }
                if (!document.getElementById('pod-ack').checked) { e.preventDefault(); alert('Please check the certification box to confirm you received the items.'); return; }
                if (document.getElementById('is-designee').checked &&
                    (!document.getElementById('designee_name').value.trim() || !document.getElementById('designee_rel').value.trim())) {
                    e.preventDefault(); alert('Please enter the designee name and relationship.'); return;
                }
                document.getElementById('pod_signature').value = canvas.toDataURL('image/png');
                confirmBtn.dataset.submitting = 'true';
                confirmBtn.innerHTML = '<svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation: spin 1s linear infinite;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Processing...';
                confirmBtn.style.background = '#94a3b8'; confirmBtn.style.cursor = 'not-allowed';
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
