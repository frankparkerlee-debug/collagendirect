<?php
/**
 * Shared ship-quantity helper.
 *
 * Business rule: Wholesale orders ship whole BOXES; patient-referral and
 * HealKit orders are tracked in PIECES. Use this everywhere an order's
 * ship quantity is displayed so the unit is consistent.
 *
 * @param array $row An order/dressing row. Recognized keys:
 *   billed_by, qty_per_change, boxes_to_ship, total_pieces,
 *   frequency|frequency_per_week, duration_days, refills_allowed
 * @return array{count:int, unit:string, label:string, is_wholesale:bool}
 */
if (!function_exists('order_ship_quantity')) {
  function order_ship_quantity(array $row): array {
    $isWholesale = ($row['billed_by'] ?? '') === 'practice_dme';
    $qty    = max(1, (int)($row['qty_per_change'] ?? 1));
    $boxes  = (int)($row['boxes_to_ship'] ?? 0);
    $pieces = (int)($row['total_pieces'] ?? 0);

    if ($isWholesale) {
      $count = $boxes > 0 ? $boxes : $qty;
      $unit  = $count === 1 ? 'box' : 'boxes';
    } else {
      if ($pieces > 0) {
        $count = $pieces;
      } else {
        // Fallback for legacy orders without a stored piece count
        $fpw  = (int)($row['frequency'] ?? $row['frequency_per_week'] ?? 1); if ($fpw === 0) $fpw = 1;
        $days = (int)($row['duration_days'] ?? 30); if ($days === 0) $days = 30;
        $refills = max(0, (int)($row['refills_allowed'] ?? 0));
        $count = (int)ceil(($days / 7.0) * $fpw * $qty * (1 + $refills));
      }
      $unit = $count === 1 ? 'piece' : 'pieces';
    }

    return ['count' => $count, 'unit' => $unit, 'label' => $count . ' ' . $unit, 'is_wholesale' => $isWholesale];
  }
}
