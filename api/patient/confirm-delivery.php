<?php
/**
 * Patient Delivery Confirmation Handler
 * URL: /api/patient/confirm-delivery.php?token=xxx
 *
 * Handles patient clicking "Confirm Delivery" link in email
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

// Get token from query string
$token = $_GET['token'] ?? '';

if (empty($token)) {
  http_response_code(400);
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Link - CollagenDirect</title>
    <style>
      body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; text-align: center; }
      .error { color: #dc2626; }
      .button { background-color: #2563eb; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
    </style>
  </head>
  <body>
    <h1 class="error">Invalid Confirmation Link</h1>
    <p>The confirmation link you clicked is invalid or incomplete.</p>
    <p>Please check your email for the correct link, or contact support if you continue to have issues.</p>
    <a href="mailto:support@collagendirect.health" class="button">Contact Support</a>
  </body>
  </html>
  <?php
  exit;
}

try {
  // Look up confirmation record
  $stmt = $pdo->prepare("
    SELECT odc.*, o.id as order_id, o.status,
           p.first_name, p.last_name, p.email,
           u.first_name AS phys_first, u.last_name AS phys_last
    FROM order_delivery_confirmations odc
    INNER JOIN orders o ON o.id = odc.order_id
    INNER JOIN patients p ON p.id = o.patient_id
    LEFT JOIN users u ON u.id = o.user_id
    WHERE odc.confirmation_token = ?
  ");
  $stmt->execute([$token]);
  $confirmation = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$confirmation) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Link Not Found - CollagenDirect</title>
      <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; text-align: center; }
        .error { color: #dc2626; }
        .button { background-color: #2563eb; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
      </style>
    </head>
    <body>
      <h1 class="error">Confirmation Link Not Found</h1>
      <p>This confirmation link is invalid or has expired.</p>
      <p>If you need assistance, please contact our support team.</p>
      <a href="mailto:support@collagendirect.health" class="button">Contact Support</a>
    </body>
    </html>
    <?php
    exit;
  }

  // Check if already confirmed
  if (!empty($confirmation['confirmed_at'])) {
    $confirmedDate = date('F j, Y', strtotime($confirmation['confirmed_at']));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Already Confirmed - CollagenDirect</title>
      <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; text-align: center; }
        .success { color: #059669; }
        .info-box { background-color: #f3f4f6; padding: 20px; border-radius: 5px; margin: 20px 0; }
      </style>
    </head>
    <body>
      <h1 class="success">✓ Already Confirmed</h1>
      <p>This delivery was already confirmed on <strong><?= htmlspecialchars($confirmedDate) ?></strong>.</p>
      <div class="info-box">
        <strong>Order #<?= htmlspecialchars($confirmation['order_id']) ?></strong><br>
        Patient: <?= htmlspecialchars(trim($confirmation['first_name'] . ' ' . $confirmation['last_name'])) ?>
      </div>
      <p>Thank you for using CollagenDirect!</p>
    </body>
    </html>
    <?php
    exit;
  }

  // Record confirmation
  $clientIp = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';

  $updateStmt = $pdo->prepare("
    UPDATE order_delivery_confirmations
    SET confirmed_at = NOW(),
        confirmed_ip = ?
    WHERE id = ?
  ");
  $updateStmt->execute([$clientIp, $confirmation['id']]);

  // Success page
  $orderId = $confirmation['order_id'];
  $patientName = trim($confirmation['first_name'] . ' ' . $confirmation['last_name']);
  $physicianName = 'Dr. ' . trim($confirmation['phys_first'] . ' ' . $confirmation['phys_last']);

  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Confirmed - CollagenDirect</title>
    <style>
      body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; text-align: center; }
      .success { color: #059669; }
      .checkmark { font-size: 72px; color: #059669; }
      .info-box { background-color: #f3f4f6; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: left; }
      .button { background-color: #2563eb; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
    </style>
  </head>
  <body>
    <div class="checkmark">✓</div>
    <h1 class="success">Delivery Confirmed!</h1>

    <p>Thank you for confirming receipt of your CollagenDirect order.</p>

    <div class="info-box">
      <strong>Confirmation Details:</strong><br>
      Order #: <?= htmlspecialchars($orderId) ?><br>
      Patient: <?= htmlspecialchars($patientName) ?><br>
      Prescribing Physician: <?= htmlspecialchars($physicianName) ?><br>
      Confirmed: <?= date('F j, Y \a\t g:i A') ?>
    </div>

    <p>Your confirmation has been recorded for insurance compliance purposes.</p>

    <p style="font-size: 14px; color: #6b7280;">
      If you have any questions or concerns about your order, please contact your physician's office or our support team.
    </p>

    <a href="mailto:support@collagendirect.health" class="button">Contact Support</a>

    <p style="font-size: 12px; color: #9ca3af; margin-top: 40px;">
      CollagenDirect<br>
      Medical Wound Care Products<br>
      &copy; <?= date('Y') ?> All rights reserved
    </p>
  </body>
  </html>
  <?php

  // Log confirmation in error log for tracking
  error_log("[delivery-confirmation] Order #$orderId confirmed by patient $patientName from IP $clientIp");

} catch (Throwable $e) {
  error_log("[delivery-confirmation] Error: " . $e->getMessage());
  http_response_code(500);
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - CollagenDirect</title>
    <style>
      body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; text-align: center; }
      .error { color: #dc2626; }
      .button { background-color: #2563eb; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
    </style>
  </head>
  <body>
    <h1 class="error">System Error</h1>
    <p>We encountered an error processing your confirmation.</p>
    <p>Please try again later or contact our support team for assistance.</p>
    <a href="mailto:support@collagendirect.health" class="button">Contact Support</a>
  </body>
  </html>
  <?php
}
