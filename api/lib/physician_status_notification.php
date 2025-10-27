<?php
// api/lib/physician_status_notification.php - Physician order status update emails (batched)
declare(strict_types=1);

if (!function_exists('send_physician_status_batch')) {
  /**
   * Send batched status update email to physician for multiple orders
   * Groups all status changes and expiring orders for a single physician
   *
   * @param PDO $pdo Database connection
   * @param string $userId Physician user ID
   * @param array $statusChanges Array of status change records
   * @param array $expiringOrders Array of expiring order records
   * @return bool Success status
   */
  function send_physician_status_batch(PDO $pdo, string $userId, array $statusChanges, array $expiringOrders): bool {
    try {
      // Get physician details
      $userStmt = $pdo->prepare("
        SELECT first_name, last_name, email, practice_name
        FROM users
        WHERE id = ?
      ");
      $userStmt->execute([$userId]);
      $user = $userStmt->fetch(PDO::FETCH_ASSOC);

      if (!$user || empty($user['email'])) {
        error_log("[physician-status] No email for user: $userId");
        return false;
      }

      $physicianName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
      $physicianEmail = $user['email'];

      // Count totals
      $totalChanges = count($statusChanges);
      $totalExpiring = count($expiringOrders);
      $totalUpdates = $totalChanges + $totalExpiring;

      if ($totalUpdates === 0) {
        return false;
      }

      // Build subject
      $subject = "CollagenDirect: $totalUpdates Patient Order Update";
      if ($totalUpdates > 1) {
        $subject .= "s";
      }

      // Build HTML email
      $html = "
      <div style='font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto; padding: 20px;'>
        <div style='background-color: #2563eb; color: white; padding: 20px; border-radius: 5px 5px 0 0;'>
          <h2 style='margin: 0;'>Order Status Updates</h2>
        </div>

        <div style='background-color: #f9fafb; padding: 20px; border: 1px solid #e5e7eb;'>
          <p>Dear Dr. $physicianName,</p>

          <p>You have <strong>$totalUpdates order update" . ($totalUpdates > 1 ? "s" : "") . "</strong> from CollagenDirect:</p>
          <ul style='margin: 10px 0;'>
            " . ($totalChanges > 0 ? "<li><strong>$totalChanges</strong> status change" . ($totalChanges > 1 ? "s" : "") . "</li>" : "") . "
            " . ($totalExpiring > 0 ? "<li><strong>$totalExpiring</strong> order" . ($totalExpiring > 1 ? "s" : "") . " expiring soon</li>" : "") . "
          </ul>
        </div>
      ";

      // Status Changes Section
      if ($totalChanges > 0) {
        $html .= "
        <div style='margin-top: 20px;'>
          <h3 style='color: #1f2937; border-bottom: 2px solid #2563eb; padding-bottom: 10px;'>Recent Status Changes</h3>
          <table style='width: 100%; border-collapse: collapse;'>
            <thead>
              <tr style='background-color: #f3f4f6;'>
                <th style='padding: 10px; text-align: left; border: 1px solid #e5e7eb;'>Order #</th>
                <th style='padding: 10px; text-align: left; border: 1px solid #e5e7eb;'>Patient</th>
                <th style='padding: 10px; text-align: left; border: 1px solid #e5e7eb;'>Product</th>
                <th style='padding: 10px; text-align: left; border: 1px solid #e5e7eb;'>Status</th>
                <th style='padding: 10px; text-align: left; border: 1px solid #e5e7eb;'>Tracking</th>
              </tr>
            </thead>
            <tbody>
        ";

        foreach ($statusChanges as $change) {
          $orderId = htmlspecialchars((string)$change['order_id']);
          $patientName = htmlspecialchars($change['patient_name'] ?? 'Unknown');
          $product = htmlspecialchars($change['product'] ?? 'N/A');
          $newStatus = htmlspecialchars($change['new_status'] ?? '');
          $statusLabel = format_status_label($newStatus);
          $statusColor = get_status_color($newStatus);

          $trackingInfo = '';
          if (!empty($change['tracking_code'])) {
            $carrier = htmlspecialchars($change['carrier'] ?? 'Carrier');
            $trackingCode = htmlspecialchars($change['tracking_code']);
            $trackingInfo = "$carrier: $trackingCode";
          } else {
            $trackingInfo = '—';
          }

          $html .= "
            <tr>
              <td style='padding: 10px; border: 1px solid #e5e7eb;'>#$orderId</td>
              <td style='padding: 10px; border: 1px solid #e5e7eb;'>$patientName</td>
              <td style='padding: 10px; border: 1px solid #e5e7eb; font-size: 13px;'>$product</td>
              <td style='padding: 10px; border: 1px solid #e5e7eb;'>
                <span style='background-color: $statusColor; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;'>
                  $statusLabel
                </span>
              </td>
              <td style='padding: 10px; border: 1px solid #e5e7eb; font-size: 12px;'>$trackingInfo</td>
            </tr>
          ";
        }

        $html .= "
            </tbody>
          </table>
        </div>
        ";
      }

      // Expiring Orders Section
      if ($totalExpiring > 0) {
        $html .= "
        <div style='margin-top: 30px;'>
          <h3 style='color: #dc2626; border-bottom: 2px solid #dc2626; padding-bottom: 10px;'>⚠ Orders Expiring Soon</h3>
          <p style='color: #dc2626;'><strong>Action Required:</strong> The following orders will expire within 7 days. Please contact patients to schedule refills if needed.</p>

          <table style='width: 100%; border-collapse: collapse;'>
            <thead>
              <tr style='background-color: #fef2f2;'>
                <th style='padding: 10px; text-align: left; border: 1px solid #fecaca;'>Order #</th>
                <th style='padding: 10px; text-align: left; border: 1px solid #fecaca;'>Patient</th>
                <th style='padding: 10px; text-align: left; border: 1px solid #fecaca;'>Product</th>
                <th style='padding: 10px; text-align: left; border: 1px solid #fecaca;'>Expires In</th>
              </tr>
            </thead>
            <tbody>
        ";

        foreach ($expiringOrders as $order) {
          $orderId = htmlspecialchars((string)$order['order_id']);
          $patientName = htmlspecialchars($order['patient_name'] ?? 'Unknown');
          $product = htmlspecialchars($order['product'] ?? 'N/A');
          $daysRemaining = (int)$order['days_remaining'];

          $urgencyColor = $daysRemaining <= 3 ? '#dc2626' : '#f59e0b';

          $html .= "
            <tr style='background-color: #fffbeb;'>
              <td style='padding: 10px; border: 1px solid #fecaca;'>#$orderId</td>
              <td style='padding: 10px; border: 1px solid #fecaca;'>$patientName</td>
              <td style='padding: 10px; border: 1px solid #fecaca; font-size: 13px;'>$product</td>
              <td style='padding: 10px; border: 1px solid #fecaca;'>
                <strong style='color: $urgencyColor;'>$daysRemaining days</strong>
              </td>
            </tr>
          ";
        }

        $html .= "
            </tbody>
          </table>
        </div>
        ";
      }

      // Footer
      $html .= "
        <div style='margin-top: 30px; padding: 20px; background-color: #f9fafb; border-radius: 5px;'>
          <p style='margin: 0; font-size: 14px;'>
            <strong>Need to take action?</strong><br>
            Log in to the <a href='https://collagendirect.health/portal/' style='color: #2563eb;'>CollagenDirect Physician Portal</a> to view detailed order information and manage your patients.
          </p>
        </div>

        <div style='margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280;'>
          <p>
            You received this email because you are a registered physician with CollagenDirect.
            These updates are sent daily to keep you informed of order status changes and expiring prescriptions.
          </p>
          <p>
            Questions? Contact us at <a href='mailto:support@collagendirect.health'>support@collagendirect.health</a>
          </p>
          <p style='margin-top: 20px;'>
            &copy; " . date('Y') . " CollagenDirect. All rights reserved.
          </p>
        </div>
      </div>
      ";

      // Send email
      require_once __DIR__ . '/sg_curl.php';

      $result = sg_send(
        ['email' => $physicianEmail, 'name' => $physicianName],
        $subject,
        $html,
        ['categories' => ['physician', 'status', 'batch']]
      );

      if ($result) {
        error_log("[physician-status] Batch email sent to $physicianEmail ($totalUpdates updates)");
      } else {
        error_log("[physician-status] Failed to send batch email to $physicianEmail");
      }

      return $result;

    } catch (Throwable $e) {
      error_log("[physician-status] Error: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Format status label for display
   */
  function format_status_label(string $status): string {
    $labels = [
      'shipped' => 'Shipped',
      'delivered' => 'Delivered',
      'approved' => 'Approved',
      'in_production' => 'In Production',
      'submitted' => 'Submitted',
      'under_review' => 'Under Review',
      'cancelled' => 'Cancelled',
      'terminated' => 'Terminated'
    ];

    return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
  }

  /**
   * Get color for status badge
   */
  function get_status_color(string $status): string {
    $colors = [
      'shipped' => '#2563eb',
      'delivered' => '#059669',
      'approved' => '#0891b2',
      'in_production' => '#7c3aed',
      'submitted' => '#6b7280',
      'under_review' => '#f59e0b',
      'cancelled' => '#dc2626',
      'terminated' => '#991b1b'
    ];

    return $colors[$status] ?? '#6b7280';
  }
}
