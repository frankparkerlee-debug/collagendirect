<?php
/**
 * Order Display Helpers
 *
 * Centralized functions for displaying order information consistently
 * across all pages, emails, PDFs, and exports.
 */

declare(strict_types=1);

/**
 * Get a consistent display order number for any order
 *
 * Priority:
 * 1. order_number field (if set and not empty)
 * 2. First 8 characters of UUID with # prefix
 *
 * @param array|string $order Order array with 'id' and optional 'order_number', or just the ID string
 * @return string Formatted order number (e.g., "#RF-20241215-001" or "#abc12345")
 */
function format_order_number(array|string $order): string {
  if (is_string($order)) {
    // Just an ID was passed
    return '#' . substr($order, 0, 8);
  }

  // Check for order_number field first
  $orderNumber = $order['order_number'] ?? null;
  if ($orderNumber && trim($orderNumber) !== '') {
    // Order has a specific order number assigned
    return '#' . $orderNumber;
  }

  // Fallback to truncated UUID
  $id = $order['id'] ?? $order['order_id'] ?? '';
  if ($id) {
    return '#' . substr($id, 0, 8);
  }

  return '#unknown';
}

/**
 * Get order number without the # prefix (for use in links, searches, etc.)
 *
 * @param array|string $order Order array or ID string
 * @return string Order identifier without prefix
 */
function get_order_identifier(array|string $order): string {
  if (is_string($order)) {
    return substr($order, 0, 8);
  }

  $orderNumber = $order['order_number'] ?? null;
  if ($orderNumber && trim($orderNumber) !== '') {
    return $orderNumber;
  }

  $id = $order['id'] ?? $order['order_id'] ?? '';
  return $id ? substr($id, 0, 8) : 'unknown';
}

/**
 * Format order number for display in HTML with proper escaping
 *
 * @param array|string $order Order array or ID string
 * @return string HTML-escaped order number
 */
function format_order_number_html(array|string $order): string {
  return htmlspecialchars(format_order_number($order), ENT_QUOTES, 'UTF-8');
}
