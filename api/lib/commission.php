<?php
/**
 * Commission Calculation Library
 *
 * Handles all commission-related operations for sales reps:
 * - Commission calculation on payment collection
 * - Rate lookup
 * - Ledger entries
 * - Balance calculations
 */

declare(strict_types=1);

/**
 * Calculate and record commission for a payment
 *
 * @param PDO $pdo Database connection
 * @param string $orderId Order ID
 * @param string $orderType 'wholesale' or 'referral'
 * @param string $clinicId The clinic/user ID
 * @param float $paymentAmount Amount collected
 * @param string $paymentDate Date of payment (Y-m-d)
 * @param string|null $paymentId Reference to the payment record
 * @return array|null Commission entry data or null if no commission applicable
 */
function calculate_commission(
  PDO $pdo,
  string $orderId,
  string $orderType,
  string $clinicId,
  float $paymentAmount,
  string $paymentDate,
  ?string $paymentId = null
): ?array {
  // 1. Get clinic's assigned rep
  $repStmt = $pdo->prepare("SELECT assigned_rep_id FROM users WHERE id = ?");
  $repStmt->execute([$clinicId]);
  $repId = $repStmt->fetchColumn();

  if (!$repId) {
    // No rep assigned, no commission
    return null;
  }

  // 2. Get rep's current commission rate (most recent effective_date <= today)
  $rateStmt = $pdo->prepare("
    SELECT rate
    FROM rep_commission_rates
    WHERE rep_id = ?
    AND (effective_date IS NULL OR effective_date <= ?)
    ORDER BY effective_date DESC NULLS LAST
    LIMIT 1
  ");
  $rateStmt->execute([$repId, $paymentDate]);
  $rate = $rateStmt->fetchColumn();

  if ($rate === false || $rate === null) {
    // No rate configured for this rep, use default 25%
    $rate = 0.25;
  } else {
    $rate = (float)$rate;
  }

  // 3. Calculate commission
  $commissionAmount = $paymentAmount * $rate;

  // 4. Create or update ledger entry
  // Use UPSERT to handle incremental payments on the same order
  $insertStmt = $pdo->prepare("
    INSERT INTO rep_commission_ledger (
      rep_id, order_id, order_type, payment_id, clinic_id,
      payment_date, collected_amount, commission_rate, commission_amount,
      status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ON CONFLICT (rep_id, order_id) DO UPDATE SET
      collected_amount = rep_commission_ledger.collected_amount + EXCLUDED.collected_amount,
      commission_amount = rep_commission_ledger.commission_amount + EXCLUDED.commission_amount,
      payment_date = EXCLUDED.payment_date,
      status = 'pending'
    RETURNING id
  ");
  $insertStmt->execute([
    $repId,
    $orderId,
    $orderType,
    $paymentId,
    $clinicId,
    $paymentDate,
    $paymentAmount,
    $rate,
    $commissionAmount
  ]);
  $entryId = $insertStmt->fetchColumn();

  return [
    'entry_id' => $entryId,
    'rep_id' => $repId,
    'rate' => $rate,
    'commission_amount' => $commissionAmount,
    'collected_amount' => $paymentAmount
  ];
}

/**
 * Record a commission reversal (for refunds/chargebacks)
 *
 * @param PDO $pdo Database connection
 * @param string $orderId Order ID
 * @param string $orderType 'wholesale' or 'referral'
 * @param string $clinicId The clinic/user ID
 * @param float $refundAmount Amount being refunded (positive number)
 * @param string $refundDate Date of refund (Y-m-d)
 * @param string|null $paymentId Reference to the original payment record
 * @param string|null $notes Reason for reversal
 * @return array|null Commission reversal entry data or null if no reversal needed
 */
function reverse_commission(
  PDO $pdo,
  string $orderId,
  string $orderType,
  string $clinicId,
  float $refundAmount,
  string $refundDate,
  ?string $paymentId = null,
  ?string $notes = null
): ?array {
  // 1. Get clinic's assigned rep (at time of reversal)
  $repStmt = $pdo->prepare("SELECT assigned_rep_id FROM users WHERE id = ?");
  $repStmt->execute([$clinicId]);
  $repId = $repStmt->fetchColumn();

  if (!$repId) {
    // No rep assigned, check if there's an original commission entry
    $origStmt = $pdo->prepare("
      SELECT rep_id, commission_rate
      FROM rep_commission_ledger
      WHERE order_id = ? AND clinic_id = ?
      ORDER BY created_at DESC
      LIMIT 1
    ");
    $origStmt->execute([$orderId, $clinicId]);
    $origEntry = $origStmt->fetch();

    if (!$origEntry) {
      return null;
    }
    $repId = $origEntry['rep_id'];
    $rate = (float)$origEntry['commission_rate'];
  } else {
    // Get the rate from the original commission entry for this order
    $rateStmt = $pdo->prepare("
      SELECT commission_rate
      FROM rep_commission_ledger
      WHERE order_id = ? AND rep_id = ?
      ORDER BY created_at DESC
      LIMIT 1
    ");
    $rateStmt->execute([$orderId, $repId]);
    $rate = $rateStmt->fetchColumn();

    if ($rate === false) {
      // No original entry, use current rate
      $rateStmt2 = $pdo->prepare("
        SELECT rate FROM rep_commission_rates
        WHERE rep_id = ? AND (effective_date IS NULL OR effective_date <= ?)
        ORDER BY effective_date DESC NULLS LAST LIMIT 1
      ");
      $rateStmt2->execute([$repId, $refundDate]);
      $rate = $rateStmt2->fetchColumn() ?: 0.25;
    }
    $rate = (float)$rate;
  }

  // 2. Calculate reversal amount (negative)
  $reversalAmount = -abs($refundAmount * $rate);

  // 3. Create reversal ledger entry (id is SERIAL, auto-generated)
  $insertStmt = $pdo->prepare("
    INSERT INTO rep_commission_ledger (
      rep_id, order_id, order_type, payment_id, clinic_id,
      payment_date, collected_amount, commission_rate, commission_amount,
      status, notes, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
    RETURNING id
  ");
  $insertStmt->execute([
    $repId,
    $orderId,
    $orderType,
    $paymentId,
    $clinicId,
    $refundDate,
    -abs($refundAmount), // Negative collected amount
    $rate,
    $reversalAmount,     // Negative commission
    $notes ?: 'Payment reversal/refund'
  ]);
  $entryId = $insertStmt->fetchColumn();

  return [
    'entry_id' => $entryId,
    'rep_id' => $repId,
    'rate' => $rate,
    'commission_amount' => $reversalAmount,
    'collected_amount' => -abs($refundAmount)
  ];
}

/**
 * Get current commission balance for a rep
 *
 * @param PDO $pdo Database connection
 * @param string $repId Sales rep ID
 * @return array Balance details
 */
function get_commission_balance(PDO $pdo, string $repId): array {
  // Total earned
  $earnedStmt = $pdo->prepare("
    SELECT COALESCE(SUM(commission_amount), 0) as total
    FROM rep_commission_ledger
    WHERE rep_id = ?
  ");
  $earnedStmt->execute([$repId]);
  $totalEarned = (float)$earnedStmt->fetchColumn();

  // Total paid out
  $paidStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM rep_commission_payouts
    WHERE rep_id = ?
  ");
  $paidStmt->execute([$repId]);
  $totalPaid = (float)$paidStmt->fetchColumn();

  $balance = $totalEarned - $totalPaid;

  return [
    'total_earned' => $totalEarned,
    'total_paid' => $totalPaid,
    'balance' => $balance
  ];
}

/**
 * Get current commission rate for a rep
 *
 * @param PDO $pdo Database connection
 * @param string $repId Sales rep ID
 * @param string|null $asOfDate Date to check rate for (defaults to today)
 * @return float Commission rate (e.g., 0.25 for 25%)
 */
function get_commission_rate(PDO $pdo, string $repId, ?string $asOfDate = null): float {
  $date = $asOfDate ?? date('Y-m-d');

  $stmt = $pdo->prepare("
    SELECT rate
    FROM rep_commission_rates
    WHERE rep_id = ?
    AND (effective_date IS NULL OR effective_date <= ?)
    ORDER BY effective_date DESC NULLS LAST
    LIMIT 1
  ");
  $stmt->execute([$repId, $date]);
  $rate = $stmt->fetchColumn();

  return $rate !== false ? (float)$rate : 0.25; // Default 25%
}

/**
 * Set default commission rate for a new rep
 *
 * @param PDO $pdo Database connection
 * @param string $repId Sales rep ID
 * @param float $rate Commission rate (default 0.25 = 25%)
 * @param string|null $setBy Admin user ID who set the rate
 * @return bool Success
 */
function set_initial_commission_rate(
  PDO $pdo,
  string $repId,
  float $rate = 0.25,
  ?string $setBy = null
): bool {
  try {
    $stmt = $pdo->prepare("
      INSERT INTO rep_commission_rates (rep_id, rate, effective_date, set_by, notes, created_at)
      VALUES (?, ?, NULL, ?, 'Initial rate on approval', NOW())
    ");
    $stmt->execute([$repId, $rate, $setBy]);
    return true;
  } catch (PDOException $e) {
    error_log("Error setting initial commission rate: " . $e->getMessage());
    return false;
  }
}
