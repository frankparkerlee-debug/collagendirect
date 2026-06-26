<?php
/**
 * Create additional orders for secondary and additional products
 * Called after primary order is created
 *
 * @param PDO $pdo Database connection
 * @param string $primary_order_id The ID of the primary order (used as order_group_id)
 * @param array $wounds_data Array of wound data with product information
 * @param array $order_params Common order parameters (patient_id, user_id, shipping, etc.)
 * @param string $wounds_json JSON-encoded wounds data
 * @param string $hcpcsCol Column name for HCPCS code ('hcpcs_code' or 'cpt_code')
 * @param bool $isWholesale Whether this is a wholesale order
 * @return int Number of additional orders created
 */
function create_multi_product_orders(
  PDO $pdo,
  string $primary_order_id,
  array $wounds_data,
  array $order_params,
  string $wounds_json,
  string $hcpcsCol,
  bool $isWholesale
): int {
  $orders_created = 0;

  // Extract common parameters
  $pid = $order_params['patient_id'];
  $patientOwnerId = $order_params['user_id'];
  $orderStatus = $order_params['status'];
  $reviewStatus = $order_params['review_status'];
  $delivery_mode = $order_params['delivery_mode'];
  $payment_type = $order_params['payment_type'];
  $ship_name = $order_params['shipping_name'];
  $ship_phone = $order_params['shipping_phone'];
  $ship_addr = $order_params['shipping_address'];
  $ship_city = $order_params['shipping_city'];
  $ship_state = $order_params['shipping_state'];
  $ship_zip = $order_params['shipping_zip'];
  $sign_name = $order_params['sign_name'];
  $sign_title = $order_params['sign_title'];
  $expires_at = $order_params['expires_at'];
  $last_eval = $order_params['last_eval_date'];
  $start_date = $order_params['start_date'];
  $refills_allowed = $order_params['refills_allowed'];
  $billed_by = $order_params['billed_by'];

  // Set order_group_id and product_type for the primary order
  $pdo->prepare("UPDATE orders SET order_group_id = ?, product_type = 'primary', wound_index = 0 WHERE id = ?")
      ->execute([$primary_order_id, $primary_order_id]);

  // Loop through all wounds to create orders for each product type
  foreach ($wounds_data as $wound_idx => $wound) {
    // Skip primary product for first wound (already created)
    $skip_primary = ($wound_idx === 0);

    // Create order for PRIMARY product (if not first wound)
    if (!$skip_primary && !empty($wound['product_id'])) {
      $pr_primary = $pdo->prepare("SELECT id, name, price_admin, price_wholesale, pieces_per_box, cost_per_box, medicare_allowable, {$hcpcsCol} as hcpcs_code FROM products WHERE id=? AND active=TRUE");
      $pr_primary->execute([$wound['product_id']]);
      $prod_primary = $pr_primary->fetch(PDO::FETCH_ASSOC);

      if ($prod_primary) {
        $orders_created += create_single_product_order(
          $pdo, $primary_order_id, $wound, $wound_idx, $prod_primary, 'primary',
          $order_params, $wounds_json, $isWholesale
        );
      }
    }

    // Create order for SECONDARY product (if specified)
    if (!empty($wound['secondary_product_id'])) {
      $pr_sec = $pdo->prepare("SELECT id, name, price_admin, price_wholesale, pieces_per_box, cost_per_box, medicare_allowable, {$hcpcsCol} as hcpcs_code FROM products WHERE id=? AND active=TRUE");
      $pr_sec->execute([$wound['secondary_product_id']]);
      $prod_sec = $pr_sec->fetch(PDO::FETCH_ASSOC);

      if ($prod_sec) {
        $orders_created += create_single_product_order(
          $pdo, $primary_order_id, $wound, $wound_idx, $prod_sec, 'secondary',
          $order_params, $wounds_json, $isWholesale
        );
      }
    }

    // Create order for ADDITIONAL product (if specified)
    if (!empty($wound['additional_product_id'])) {
      $pr_add = $pdo->prepare("SELECT id, name, price_admin, price_wholesale, pieces_per_box, cost_per_box, medicare_allowable, {$hcpcsCol} as hcpcs_code FROM products WHERE id=? AND active=TRUE");
      $pr_add->execute([$wound['additional_product_id']]);
      $prod_add = $pr_add->fetch(PDO::FETCH_ASSOC);

      if ($prod_add) {
        $orders_created += create_single_product_order(
          $pdo, $primary_order_id, $wound, $wound_idx, $prod_add, 'additional',
          $order_params, $wounds_json, $isWholesale
        );
      }
    }
  }

  return $orders_created;
}

/**
 * Create a single product order
 */
function create_single_product_order(
  PDO $pdo,
  string $order_group_id,
  array $wound,
  int $wound_idx,
  array $product,
  string $product_type,
  array $order_params,
  string $wounds_json,
  bool $isWholesale
): int {
  $new_oid = bin2hex(random_bytes(16));

  // Calculate boxes needed for this product
  $freq = max(0, (int)($wound['frequency_per_week'] ?? 0));
  $qty = max(1, (int)($wound['qty_per_change'] ?? 1));
  $days = max(1, (int)($wound['duration_days'] ?? 30));
  $refills = $order_params['refills_allowed'];

  $pieces_per_box = max(1, (int)($product['pieces_per_box'] ?? 10));

  // Apply defaults if frequency is 0
  if ($freq === 0) $freq = 1;

  // Calculate pieces and boxes (matches revenue_calculator.php formula)
  $weeks = $days / 7.0;
  $total_pieces = (int)ceil($weeks * $freq * $qty * (1 + $refills));
  $boxes_needed = (int)ceil($total_pieces / $pieces_per_box);
  $billable_pieces = $total_pieces;

  // Calculate price per box (wholesale or CPT/Medicare rate)
  $price_per_box = $isWholesale
    ? (float)($product['price_wholesale'] ?? 0)
    : (float)($product['price_admin'] ?? 0);

  // Get cost per box for profit calculation
  $cost_per_box = (float)($product['cost_per_box'] ?? 0);

  // Calculate expected revenue and cost
  if ($isWholesale) {
    // Wholesale: revenue = boxes * price_per_box
    $cpt_rate_used = $price_per_box / max(1, $pieces_per_box);
    $expected_revenue = $boxes_needed * $price_per_box;
  } else {
    // Referral: revenue = billable_pieces * per-piece rate.
    // medicare_allowable is a PER-BOX rate, so divide by pieces_per_box to get the per-piece rate.
    $medicare_rate = (float)($product['medicare_allowable'] ?? 0);
    $cpt_rate_used = $medicare_rate > 0 ? $medicare_rate / max(1, $pieces_per_box) : $price_per_box / max(1, $pieces_per_box);
    $expected_revenue = $billable_pieces * $cpt_rate_used;
  }
  $expected_cost = $boxes_needed * $cost_per_box;

  // Insert the order
  $ins = $pdo->prepare("
    INSERT INTO orders (
      id, patient_id, user_id, product, product_id, product_price, status, review_status,
      shipments_remaining, delivery_mode, payment_type,
      wound_location, wound_laterality, wound_notes,
      shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_zip,
      sign_name, sign_title, signed_at, created_at, updated_at, expires_at,
      icd10_primary, icd10_secondary, wound_length_cm, wound_width_cm, wound_depth_cm,
      wound_type, wound_stage, last_eval_date, start_date,
      frequency_per_week, qty_per_change, duration_days, refills_allowed,
      additional_instructions, wounds_data, cpt, billed_by,
      order_group_id, product_type, wound_index,
      total_pieces, boxes_to_ship, billable_pieces, expected_revenue, expected_cost, cpt_rate_used
    ) VALUES (
      ?,?,?,?,?,?,?,?,?,?,?,
      ?,?,?,
      ?,?,?,?,?,?,
      ?,?,NOW(),NOW(),NOW(),?,
      ?,?,?,?,?,
      ?,?,?,?,
      ?,?,?,?,
      ?,?::jsonb,?,?,
      ?,?,?,
      ?,?,?,?,?,?
    )
  ");

  $ins->execute([
    $new_oid,
    $order_params['patient_id'],
    $order_params['user_id'],
    $product['name'],
    $product['id'],
    $price_per_box,
    $order_params['status'],
    $order_params['review_status'],
    $boxes_needed,
    $order_params['delivery_mode'],
    $order_params['payment_type'],
    $wound['location'] ?? '',
    $wound['laterality'] ?? '',
    $wound['notes'] ?? '',
    $order_params['shipping_name'],
    $order_params['shipping_phone'],
    $order_params['shipping_address'],
    $order_params['shipping_city'],
    $order_params['shipping_state'],
    $order_params['shipping_zip'],
    $order_params['sign_name'],
    $order_params['sign_title'],
    $order_params['expires_at'],
    $wound['icd10_primary'] ?? '',
    $wound['icd10_secondary'] ?? '',
    $wound['length_cm'] ?? null,
    $wound['width_cm'] ?? null,
    $wound['depth_cm'] ?? null,
    $wound['type'] ?? '',
    $wound['stage'] ?? '',
    $order_params['last_eval_date'],
    $order_params['start_date'],
    $freq,
    $qty,
    $days,
    $refills,
    $wound['notes'] ?? '',
    $wounds_json,
    $product['hcpcs_code'] ?? null,
    $order_params['billed_by'],
    $order_group_id,
    $product_type,
    $wound_idx,
    // Calculated values stored at creation time
    $total_pieces,
    $boxes_needed,
    $billable_pieces,
    $expected_revenue,
    $expected_cost,
    $cpt_rate_used
  ]);

  return 1;
}
