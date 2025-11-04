#!/bin/bash
#
# Run order workflow migrations on production via SSH
#

echo "=== Running Order Workflow Migrations ==="
echo ""
echo "This will:"
echo "  1. Add order lifecycle fields (review_status, ai_suggestions, etc.)"
echo "  2. Create order_revisions table"
echo ""

# Run migrations via SSH
ssh collagendirect.health << 'ENDSSH'
cd /var/www/html

echo "Step 1: Adding order lifecycle fields..."
php admin/add-order-lifecycle-fields.php
echo ""

echo "Step 2: Creating order revisions table..."
php admin/add-order-revisions-table.php
echo ""

echo "=== Migrations Complete ==="
ENDSSH

echo ""
echo "Done! The order workflow features are now available."
echo ""
echo "Next steps:"
echo "  - Update doctor portal UI to show AI suggestions"
echo "  - Update admin panel to show order review interface"
echo "  - Test with Randy Dittmar test patient"
