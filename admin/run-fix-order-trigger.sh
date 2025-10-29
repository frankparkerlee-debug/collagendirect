#!/bin/bash
set -e

echo "=== Fix Order Status Trigger on Production ==="
echo ""
echo "This script will:"
echo "1. Convert order_status_changes.order_id from INTEGER to VARCHAR"
echo "2. Fix the trigger to use correct columns (tracking_number, carrier)"
echo "3. Verify the fix"
echo ""
read -p "Continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
  echo "Aborted."
  exit 1
fi

# Run the fix script via curl
curl -f "https://collagendirect.health/admin/fix-order-status-trigger.php" || {
  echo ""
  echo "✗ Fix failed!"
  exit 1
}

echo ""
echo "=== Fix Complete ==="
echo ""
echo "You can now:"
echo "- Test tracking number updates in admin/shipments.php"
echo "- Test order status changes in admin/orders.php"
