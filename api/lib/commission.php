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
 * Supports two commission modes:
 * 1. Per-product dollar amount: Fixed $ per item (from rep_product_commissions table)
 * 2. Percentage of payment: Traditional % of collected amount (from rep_commission_rates)
 *
 * Per-product dollar amounts take priority. Different reps can have different amounts
 * for the same product (e.g., rep manager sees full commission, sub-rep gets different amount).
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
  // First check assigned_rep_id (external distributors)
  $repStmt = $pdo->prepare("SELECT assigned_rep_id FROM users WHERE id = ?");
  $repStmt->execute([$clinicId]);
  $repId = $repStmt->fetchColumn();

  // If no external rep assigned, check admin_physicians for internal admins
  // Internal admins have their ID stored in admin_physicians.admin_id (integer from admin_users)
  // Check if this admin has a linked sales_reps profile via their email
  if (!$repId) {
    $adminStmt = $pdo->prepare("
      SELECT ap.admin_id, sr.id as sales_rep_id
      FROM admin_physicians ap
      JOIN admin_users au ON au.id = ap.admin_id
      LEFT JOIN users u ON u.email = au.email
      LEFT JOIN sales_reps sr ON sr.user_id = u.id
      WHERE ap.physician_user_id = ?
      LIMIT 1
    ");
    $adminStmt->execute([$clinicId]);
    $adminRow = $adminStmt->fetch();

    if ($adminRow && $adminRow['sales_rep_id']) {
      // Use sales_rep_id if admin has a linked sales_reps profile (via email match)
      $repId = $adminRow['sales_rep_id'];
    }
  }

  if (!$repId) {
    // No rep assigned, no commission
    return null;
  }

  // 2. Check for per-product dollar commission first
  // Look up the order's product_id and quantity (boxes_to_ship)
  $commissionType = 'percentage';
  $commissionAmount = 0.0;
  $rate = 0.0;
  $perProductUsed = false;

  try {
    $orderStmt = $pdo->prepare("SELECT product_id, boxes_to_ship, total_pieces FROM orders WHERE id = ?");
    $orderStmt->execute([$orderId]);
    $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if ($orderData && !empty($orderData['product_id'])) {
      // Check rep_product_commissions table for a per-product dollar amount
      $productCommStmt = $pdo->prepare("
        SELECT commission_amount
        FROM rep_product_commissions
        WHERE rep_id = ? AND product_id = ?
        AND (effective_date IS NULL OR effective_date <= ?)
        AND (end_date IS NULL OR end_date >= ?)
        ORDER BY effective_date DESC NULLS LAST
        LIMIT 1
      ");
      $productCommStmt->execute([$repId, $orderData['product_id'], $paymentDate, $paymentDate]);
      $perProductAmount = $productCommStmt->fetchColumn();

      if ($perProductAmount !== false && $perProductAmount !== null) {
        // Per-product dollar commission found
        $perProductAmount = (float)$perProductAmount;
        $quantity = max(1, (int)($orderData['boxes_to_ship'] ?? 1));
        $commissionAmount = $perProductAmount * $quantity;
        $rate = $perProductAmount; // Store per-unit amount as the "rate" for display
        $commissionType = 'per_product';
        $perProductUsed = true;
      }
    }
  } catch (Throwable $e) {
    // If per-product lookup fails (table might not exist yet), fall through to percentage
    error_log('[commission] Per-product lookup failed: ' . $e->getMessage());
  }

  // 3. Fall back to percentage if no per-product rate found
  if (!$perProductUsed) {
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

    $commissionAmount = $paymentAmount * $rate;
    $commissionType = 'percentage';
  }

  // 4. Create or update ledger entry
  // Use UPSERT to handle incremental payments on the same order
  // Include commission_type if column exists
  try {
    $insertStmt = $pdo->prepare("
      INSERT INTO rep_commission_ledger (
        rep_id, order_id, order_type, payment_id, clinic_id,
        payment_date, collected_amount, commission_rate, commission_amount,
        commission_type, status, created_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
      ON CONFLICT (rep_id, order_id) DO UPDATE SET
        collected_amount = rep_commission_ledger.collected_amount + EXCLUDED.collected_amount,
        commission_amount = rep_commission_ledger.commission_amount + EXCLUDED.commission_amount,
        payment_date = EXCLUDED.payment_date,
        commission_type = EXCLUDED.commission_type,
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
      $commissionAmount,
      $commissionType
    ]);
  } catch (PDOException $e) {
    // Fallback: commission_type column might not exist yet
    if (strpos($e->getMessage(), 'commission_type') !== false) {
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
    } else {
      throw $e;
    }
  }
  $entryId = $insertStmt->fetchColumn();

  return [
    'entry_id' => $entryId,
    'rep_id' => $repId,
    'rate' => $rate,
    'commission_amount' => $commissionAmount,
    'commission_type' => $commissionType,
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
  // First check assigned_rep_id (external distributors)
  $repStmt = $pdo->prepare("SELECT assigned_rep_id FROM users WHERE id = ?");
  $repStmt->execute([$clinicId]);
  $repId = $repStmt->fetchColumn();

  // If no external rep, check admin_physicians for internal admins
  if (!$repId) {
    $adminStmt = $pdo->prepare("
      SELECT ap.admin_id, sr.id as sales_rep_id
      FROM admin_physicians ap
      JOIN admin_users au ON au.id = ap.admin_id
      LEFT JOIN users u ON u.email = au.email
      LEFT JOIN sales_reps sr ON sr.user_id = u.id
      WHERE ap.physician_user_id = ?
      LIMIT 1
    ");
    $adminStmt->execute([$clinicId]);
    $adminRow = $adminStmt->fetch();

    if ($adminRow && $adminRow['sales_rep_id']) {
      $repId = $adminRow['sales_rep_id'];
    }
  }

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
 * Get per-product commission amounts for a rep
 *
 * @param PDO $pdo Database connection
 * @param string $repId Sales rep ID
 * @param string|null $asOfDate Date to check (defaults to today)
 * @return array Array of [product_id => commission_amount]
 */
function get_product_commissions(PDO $pdo, string $repId, ?string $asOfDate = null): array {
  $date = $asOfDate ?? date('Y-m-d');
  $result = [];

  try {
    $stmt = $pdo->prepare("
      SELECT DISTINCT ON (product_id) product_id, commission_amount
      FROM rep_product_commissions
      WHERE rep_id = ?
      AND (effective_date IS NULL OR effective_date <= ?)
      AND (end_date IS NULL OR end_date >= ?)
      ORDER BY product_id, effective_date DESC NULLS LAST
    ");
    $stmt->execute([$repId, $date, $date]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $result[(int)$row['product_id']] = (float)$row['commission_amount'];
    }
  } catch (Throwable $e) {
    // Table might not exist yet
    error_log('[commission] get_product_commissions error: ' . $e->getMessage());
  }

  return $result;
}

/**
 * Set per-product commission amount for a rep
 *
 * @param PDO $pdo Database connection
 * @param string $repId Sales rep ID
 * @param int $productId Product ID
 * @param float $amount Dollar amount per item
 * @param string|null $effectiveDate When this rate takes effect
 * @param string|null $setBy Admin user ID who set it
 * @param string|null $notes Optional notes
 * @return bool Success
 */
function set_product_commission(
  PDO $pdo,
  string $repId,
  int $productId,
  float $amount,
  ?string $effectiveDate = null,
  ?string $setBy = null,
  ?string $notes = null
): bool {
  try {
    $effectiveDate = $effectiveDate ?? date('Y-m-d');

    // End any current rate for this rep+product
    $pdo->prepare("
      UPDATE rep_product_commissions
      SET end_date = ?
      WHERE rep_id = ? AND product_id = ? AND end_date IS NULL
    ")->execute([$effectiveDate, $repId, $productId]);

    // Insert new rate
    $pdo->prepare("
      INSERT INTO rep_product_commissions (rep_id, product_id, commission_amount, effective_date, set_by, notes, created_at)
      VALUES (?, ?, ?, ?, ?, ?, NOW())
    ")->execute([$repId, $productId, $amount, $effectiveDate, $setBy, $notes]);

    return true;
  } catch (PDOException $e) {
    error_log("Error setting product commission: " . $e->getMessage());
    return false;
  }
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
    // Detect which column name exists (set_by or created_by) — schema varies by deploy.
    $colCheck = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'rep_commission_rates' AND column_name IN ('set_by', 'created_by')")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('set_by', $colCheck)) {
      $stmt = $pdo->prepare("INSERT INTO rep_commission_rates (rep_id, rate, effective_date, set_by, notes, created_at) VALUES (?, ?, NULL, ?, 'Initial rate on approval', NOW())");
      $stmt->execute([$repId, $rate, $setBy]);
    } elseif (in_array('created_by', $colCheck)) {
      $stmt = $pdo->prepare("INSERT INTO rep_commission_rates (rep_id, rate, effective_date, created_by, notes, created_at) VALUES (?, ?, NULL, ?, 'Initial rate on approval', NOW())");
      $stmt->execute([$repId, $rate, $setBy]);
    } else {
      $stmt = $pdo->prepare("INSERT INTO rep_commission_rates (rep_id, rate, effective_date, notes, created_at) VALUES (?, ?, NULL, 'Initial rate on approval', NOW())");
      $stmt->execute([$repId, $rate]);
    }
    return true;
  } catch (PDOException $e) {
    error_log("Error setting initial commission rate: " . $e->getMessage());
    return false;
  }
}
