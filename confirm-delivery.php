<?php
declare(strict_types=1);

/**
 * Delivery Confirmation Landing Page
 * Patients click SMS link to confirm order delivery
 */

require_once __DIR__ . '/api/lib/env.php';
require_once __DIR__ . '/admin/db.php';

// Get token from URL
$token = $_GET['token'] ?? '';

$success = false;
$error = null;
$orderInfo = null;

if (empty($token)) {
  $error = 'Invalid confirmation link. Please check your SMS and try again.';
} else {
  try {
    // Find confirmation record by token
    $stmt = $pdo->prepare("
      SELECT dc.id, dc.order_id, dc.patient_phone, dc.patient_email, dc.confirmed_at,
             o.product, o.quantity,
             p.first_name, p.last_name
      FROM delivery_confirmations dc
      LEFT JOIN orders o ON o.id = dc.order_id
      LEFT JOIN patients p ON p.id = o.patient_id
      WHERE dc.confirmation_token = ?
    ");
    $stmt->execute([$token]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
      $error = 'Invalid or expired confirmation link.';
    } elseif ($record['confirmed_at']) {
      // Already confirmed
      $success = true;
      $orderInfo = $record;
      $error = 'This order was already confirmed on ' . date('F j, Y g:i A', strtotime($record['confirmed_at']));
    } else {
      // Mark as confirmed
      $updateStmt = $pdo->prepare("
        UPDATE delivery_confirmations
        SET confirmed_at = NOW(),
            confirmed_ip = ?,
            confirmed_user_agent = ?,
            updated_at = NOW()
        WHERE id = ?
      ");

      $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
      $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

      $updateStmt->execute([$ip, $userAgent, $record['id']]);

      $success = true;
      $orderInfo = $record;

      error_log("[confirm-delivery] Order {$record['order_id']} confirmed by token {$token} from IP {$ip}");
    }
  } catch (Throwable $e) {
    error_log('[confirm-delivery] Error: ' . $e->getMessage());
    $error = 'An error occurred. Please try again later or contact support.';
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Delivery Confirmation - CollagenDirect</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .container {
      background: white;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      max-width: 500px;
      width: 100%;
      padding: 40px;
      text-align: center;
    }

    .logo {
      width: 60px;
      height: 60px;
      margin: 0 auto 20px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
    }

    .success-icon {
      width: 80px;
      height: 80px;
      margin: 0 auto 20px;
      background: #10b981;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 48px;
      animation: scaleIn 0.5s ease-out;
    }

    .error-icon {
      width: 80px;
      height: 80px;
      margin: 0 auto 20px;
      background: #ef4444;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 48px;
    }

    @keyframes scaleIn {
      0% {
        transform: scale(0);
        opacity: 0;
      }
      50% {
        transform: scale(1.1);
      }
      100% {
        transform: scale(1);
        opacity: 1;
      }
    }

    h1 {
      font-size: 28px;
      color: #1f2937;
      margin-bottom: 16px;
      font-weight: 700;
    }

    .message {
      font-size: 16px;
      color: #6b7280;
      line-height: 1.6;
      margin-bottom: 24px;
    }

    .order-details {
      background: #f9fafb;
      border-radius: 8px;
      padding: 20px;
      margin: 20px 0;
      text-align: left;
    }

    .order-details h3 {
      font-size: 14px;
      font-weight: 600;
      color: #374151;
      margin-bottom: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .detail-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid #e5e7eb;
    }

    .detail-row:last-child {
      border-bottom: none;
    }

    .detail-label {
      font-size: 14px;
      color: #6b7280;
      font-weight: 500;
    }

    .detail-value {
      font-size: 14px;
      color: #1f2937;
      font-weight: 600;
    }

    .button {
      display: inline-block;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 14px 32px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 16px;
      transition: transform 0.2s, box-shadow 0.2s;
      margin-top: 10px;
    }

    .button:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
    }

    .support-link {
      margin-top: 24px;
      font-size: 14px;
      color: #6b7280;
    }

    .support-link a {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
    }

    .support-link a:hover {
      text-decoration: underline;
    }

    @media (max-width: 600px) {
      .container {
        padding: 30px 20px;
      }

      h1 {
        font-size: 24px;
      }

      .message {
        font-size: 15px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <?php if ($success): ?>
      <div class="success-icon">✓</div>
      <h1>Delivery Confirmed!</h1>
      <p class="message">
        Thank you for confirming delivery of your order<?php if ($orderInfo): ?> with
        <?php echo htmlspecialchars($orderInfo['first_name'] . ' ' . $orderInfo['last_name']); ?><?php endif; ?>.
      </p>

      <?php if ($orderInfo): ?>
        <div class="order-details">
          <h3>Order Details</h3>
          <div class="detail-row">
            <span class="detail-label">Order ID</span>
            <span class="detail-value">#<?php echo htmlspecialchars($orderInfo['order_id']); ?></span>
          </div>
          <?php if ($orderInfo['product']): ?>
            <div class="detail-row">
              <span class="detail-label">Product</span>
              <span class="detail-value"><?php echo htmlspecialchars($orderInfo['product']); ?></span>
            </div>
          <?php endif; ?>
          <?php if ($orderInfo['quantity']): ?>
            <div class="detail-row">
              <span class="detail-label">Quantity</span>
              <span class="detail-value"><?php echo htmlspecialchars($orderInfo['quantity']); ?></span>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <p class="message" style="margin-top: 20px;">
        Your confirmation has been recorded for our records. If you have any questions about your order,
        please don't hesitate to contact your physician.
      </p>

    <?php else: ?>
      <div class="error-icon">✕</div>
      <h1>Confirmation Failed</h1>
      <p class="message">
        <?php echo htmlspecialchars($error); ?>
      </p>
    <?php endif; ?>

    <a href="https://collagendirect.health" class="button">Return to Home</a>

    <div class="support-link">
      Need help? <a href="mailto:support@collagendirect.health">Contact Support</a>
    </div>
  </div>
</body>
</html>
