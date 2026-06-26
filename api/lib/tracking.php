<?php
/**
 * Shipping tracking helpers — shared by HealKit, referral, and wholesale views.
 * Storage convention: orders.tracking_number (+ orders.carrier).
 */
declare(strict_types=1);

if (!function_exists('order_tracking_url')) {
  /**
   * Build a clickable carrier tracking URL. Defaults to UPS when the carrier
   * is unknown (HealKit ships UPS).
   */
  function order_tracking_url(string $tracking, ?string $carrier = null): string {
    $tracking = trim($tracking);
    if ($tracking === '') return '';
    $c = strtolower(trim((string)($carrier ?? '')));
    return match ($c) {
      'fedex' => 'https://www.fedex.com/fedextrack/?trknbr=' . urlencode($tracking),
      'usps'  => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=' . urlencode($tracking),
      default => 'https://www.ups.com/track?loc=en_US&tracknum=' . urlencode($tracking), // ups / unknown
    };
  }
}

if (!function_exists('order_tracking_label')) {
  /** Carrier label for display, defaulting to UPS. */
  function order_tracking_label(?string $carrier = null): string {
    $c = strtoupper(trim((string)($carrier ?? '')));
    return $c !== '' ? $c : 'UPS';
  }
}
