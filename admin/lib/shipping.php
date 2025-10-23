<?php
// /public/admin/lib/shipping.php
declare(strict_types=1);

/** Try to detect the carrier from the tracking number. Returns 'ups' | 'fedex' | 'usps' | null */
function detect_carrier(string $t): ?string {
  $t = strtoupper(preg_replace('/\s+/', '', $t));

  // UPS: typically starts with 1Z + 16 chars (alnum)
  if (preg_match('/^1Z[0-9A-Z]{16}$/', $t)) return 'ups';

  // FedEx: many formats. Common 12, 15, 20, 22 digits (all numeric).
  if (preg_match('/^\d{12}$|^\d{15}$|^\d{20}$|^\d{22}$/', $t)) return 'fedex';

  // USPS: 20–22 digits; also “92...”, “94...”, “95...” often USPS
  if (preg_match('/^\d{20,22}$/', $t) || preg_match('/^(92|93|94|95)\d{18}$/', $t)) return 'usps';

  return null;
}

/** Build a tracking URL. If carrier unknown, return Google search as a safe fallback. */
function tracking_url(string $tracking, ?string $carrier = null): string {
  $tracking = trim($tracking);
  $c = $carrier ?: detect_carrier($tracking);

  switch ($c) {
    case 'ups':
      return 'https://www.ups.com/track?loc=en_US&tracknum=' . rawurlencode($tracking);
    case 'fedex':
      return 'https://www.fedex.com/fedextrack/?trknbr=' . rawurlencode($tracking);
    case 'usps':
      return 'https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=' . rawurlencode($tracking);
    default:
      return 'https://www.google.com/search?q=' . rawurlencode($tracking . ' tracking');
  }
}

/**
 * Placeholder for live status pulls.
 * If you later add a carrier API, implement here and return:
 * ['status'=>'in_transit'|'delivered'|..., 'eta'=>'YYYY-mm-dd'|null, 'delivered_at'=>'YYYY-mm-dd HH:ii:ss'|null]
 */
function fetch_tracking_status(string $tracking, ?string $carrier = null): array {
  // No live API at the moment—return empty so UI falls back to manual/links.
  return ['status' => null, 'eta' => null, 'delivered_at' => null];
}

/** True if the string looks like an uploaded filename (to avoid showing it in tracking field). */
function looks_like_filename(string $s): bool {
  return (bool)preg_match('/\.(pdf|png|jpe?g|gif|tiff?|heic|webp|txt|docx?)$/i', trim($s));
}
