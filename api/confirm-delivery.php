<?php
declare(strict_types=1);

/**
 * Delivery Confirmation Handler
 *
 * Web endpoint where patients confirm delivery by clicking link in SMS
 * Required for insurance compliance - tracks patient confirmation with timestamp
 *
 * URL: https://collagendirect.health/api/confirm-delivery.php?token={token}
 */

require_once __DIR__ . '/../admin/db.php';
require_once __DIR__ . '/lib/timezone.php';

// Get confirmation token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    showErrorPage('Invalid confirmation link', 'The confirmation link is missing required information. Please use the link from your text message.');
    exit;
}

try {
    // Look up confirmation by token
    $stmt = $pdo->prepare("
        SELECT dc.id, dc.order_id, dc.confirmed_at, dc.sms_sent_at, dc.created_at,
               o.product, o.delivered_at,
               p.first_name, p.last_name
        FROM delivery_confirmations dc
        JOIN orders o ON o.id = dc.order_id
        JOIN patients p ON p.id = o.patient_id
        WHERE dc.confirmation_token = ?
    ");
    $stmt->execute([$token]);
    $confirmation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$confirmation) {
        showErrorPage('Invalid Link', 'This confirmation link is not valid. It may have expired or been used already.');
        exit;
    }

    // Check if already confirmed
    if ($confirmation['confirmed_at']) {
        $confirmedDate = format_datetime_central($confirmation['confirmed_at']);
        showSuccessPage(
            'Already Confirmed',
            "Thank you! This delivery was already confirmed on {$confirmedDate}.",
            $confirmation,
            true // already confirmed
        );
        exit;
    }

    // Check if token is expired (7 days from SMS sent)
    if ($confirmation['sms_sent_at']) {
        $sentTime = strtotime($confirmation['sms_sent_at']);
        $expiryTime = $sentTime + (7 * 24 * 60 * 60); // 7 days

        if (time() > $expiryTime) {
            showErrorPage(
                'Link Expired',
                'This confirmation link has expired (valid for 7 days). Please contact your physician\'s office if you need assistance.'
            );
            exit;
        }
    }

    // Record confirmation
    $updateStmt = $pdo->prepare("
        UPDATE delivery_confirmations
        SET confirmed_at = NOW(),
            confirmation_method = 'web_link',
            confirmed_ip = ?,
            confirmed_user_agent = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $updateStmt->execute([$ip, $userAgent, $confirmation['id']]);

    error_log("[confirm-delivery] Order {$confirmation['order_id']} confirmed via web link by {$confirmation['first_name']} {$confirmation['last_name']} from IP {$ip}");

    // Show success page
    showSuccessPage(
        'Delivery Confirmed!',
        'Thank you for confirming receipt of your wound care supplies.',
        $confirmation,
        false
    );

} catch (Throwable $e) {
    error_log("[confirm-delivery] Error: " . $e->getMessage());
    showErrorPage('System Error', 'We encountered an error processing your confirmation. Please try again later.');
}

/**
 * Show success page
 */
function showSuccessPage(string $title, string $message, array $confirmation, bool $alreadyConfirmed = false): void {
    $patientName = htmlspecialchars($confirmation['first_name'] . ' ' . $confirmation['last_name']);
    $product = htmlspecialchars($confirmation['product'] ?? 'wound care supplies');
    $orderId = htmlspecialchars(substr($confirmation['order_id'], 0, 8));

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - CollagenDirect</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
                background: <?php echo $alreadyConfirmed ? '#3b82f6' : '#10b981'; ?>;
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
            .details {
                background: #f8fafc;
                border-radius: 8px;
                padding: 1.5rem;
                text-align: left;
                margin-bottom: 2rem;
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
                font-weight: 600;
                color: #64748b;
                font-size: 0.875rem;
            }
            .detail-value {
                color: #1e293b;
                font-size: 0.875rem;
                text-align: right;
            }
            .footer {
                font-size: 0.875rem;
                color: #64748b;
                line-height: 1.5;
            }
            .footer a {
                color: #667eea;
                text-decoration: none;
            }
            .footer a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>

            <div class="details">
                <div class="detail-row">
                    <span class="detail-label">Patient:</span>
                    <span class="detail-value"><?php echo $patientName; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Product:</span>
                    <span class="detail-value"><?php echo $product; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Order ID:</span>
                    <span class="detail-value"><?php echo $orderId; ?></span>
                </div>
                <?php if (!$alreadyConfirmed): ?>
                <div class="detail-row">
                    <span class="detail-label">Confirmed:</span>
                    <span class="detail-value"><?php echo format_datetime_central(); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="footer">
                <p>Your confirmation has been recorded for insurance compliance.</p>
                <p style="margin-top: 1rem;">
                    Questions? Contact your physician's office or visit
                    <a href="https://collagendirect.health">CollagenDirect.health</a>
                </p>
            </div>
        </div>
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
        <title><?php echo htmlspecialchars($title); ?> - CollagenDirect</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
                color: #667eea;
                text-decoration: none;
            }
            .footer a:hover {
                text-decoration: underline;
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
                <p>Need help? Please contact your physician's office.</p>
                <p style="margin-top: 1rem;">
                    Visit <a href="https://collagendirect.health">CollagenDirect.health</a> for more information.
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
}
