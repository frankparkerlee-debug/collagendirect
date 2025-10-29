#!/bin/bash
set -e

echo "======================================"
echo "  Run All Pending Migrations"
echo "======================================"
echo ""
echo "This will run the following migrations on production:"
echo "  1. Add tracking_number and carrier columns"
echo "  2. Fix order status change trigger"
echo "  3. Add patient status columns"
echo ""
read -p "Continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
  echo "Aborted."
  exit 1
fi

echo ""
echo "=== Step 1: Add Tracking Columns ==="
echo ""
curl -f "https://collagendirect.health/admin/run-tracking-migration.php" || {
  echo ""
  echo "✗ Tracking migration failed!"
  exit 1
}

echo ""
echo ""
echo "=== Step 2: Fix Order Status Trigger ==="
echo ""
curl -f "https://collagendirect.health/admin/fix-order-status-trigger.php" || {
  echo ""
  echo "✗ Trigger fix failed!"
  exit 1
}

echo ""
echo ""
echo "=== Step 3: Add Patient Status Columns ==="
echo ""
curl -f "https://collagendirect.health/admin/run-patient-status-migration.php" || {
  echo ""
  echo "✗ Patient status migration failed!"
  exit 1
}

echo ""
echo ""
echo "======================================"
echo "  ✓ All Migrations Complete!"
echo "======================================"
echo ""
echo "You can now:"
echo "  - Update tracking numbers in admin/shipments.php"
echo "  - Update order status in admin/orders.php"
echo "  - Update patient status in admin/patients.php"
echo "  - View patient status in portal"
