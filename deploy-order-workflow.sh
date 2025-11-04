#!/bin/bash
#
# Deploy Order Workflow Implementation
#

echo "=== Deploying Order Workflow Implementation ==="
echo ""

# Change to the collagendirect directory
cd "$(dirname "$0")"

echo "Step 1: Checking git status..."
git status --short

echo ""
echo "Step 2: Adding new files..."
git add admin/add-order-lifecycle-fields.php
git add admin/add-order-revisions-table.php
git add api/portal/generate_order_suggestions.php
git add api/portal/order.update.php
git add api/portal/order.get.php
git add api/admin/order.review.php
git add api/admin/order.get.php
git add portal/order-workflow.js
git add portal/order-edit-dialog.html
git add admin/order-review-enhanced.js

echo ""
echo "Step 3: Adding modified files..."
git add api/portal/orders.create.php
git add admin/run-all-migrations.php

echo ""
echo "Step 4: Adding documentation..."
git add AI_ORDER_WORKFLOW_PLAN.md
git add ORDER_WORKFLOW_IMPLEMENTATION_SUMMARY.md
git add DEPLOYMENT_GUIDE.md
git add run-order-workflow-migrations.sh
git add deploy-order-workflow.sh

echo ""
echo "Step 5: Committing changes..."
git commit -m "$(cat <<'EOF'
Implement AI-assisted order editing workflow

Backend Infrastructure:
- Add order lifecycle fields (review_status, ai_suggestions, locking)
- Create order_revisions table for complete audit trail
- Implement order editing API with permission checks
- Add admin review API (approve/reject/request changes)
- Build AI order suggestions generator

Doctor Portal UI:
- Enhanced order details viewer with AI suggestions
- Complete order edit dialog with all fields
- Accept AI suggestions button
- Review status indicators and feedback display

Admin Panel UI:
- Enhanced review interface with AI assessment
- Three-action workflow: approve/request changes/reject
- Admin feedback form
- Patient approval score integration

Features:
- Universal: Works for all doctors and all patients
- Complete audit trail: All changes tracked in order_revisions
- Email notifications: Doctors notified of review decisions
- Permission system: Role-based access with edit locking
- AI-powered: Suggestions for order improvements

Database changes:
- orders table: +9 columns for lifecycle management
- order_revisions table: Full revision history

Files created: 13 new files
Files modified: 2 files

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"

if [ $? -eq 0 ]; then
  echo "✓ Commit successful"

  echo ""
  echo "Step 6: Pushing to origin..."
  git push origin main

  if [ $? -eq 0 ]; then
    echo "✓ Push successful"
    echo ""
    echo "=== Deployment Initiated ==="
    echo ""
    echo "Next steps:"
    echo "1. Wait for Render to complete deployment (check dashboard)"
    echo "2. Run migrations: https://collagendirect.health/admin/run-all-migrations.php"
    echo "3. Follow integration steps in DEPLOYMENT_GUIDE.md"
    echo "4. Test with Randy Dittmar patient"
  else
    echo "✗ Push failed"
    exit 1
  fi
else
  echo "✗ Commit failed"
  exit 1
fi
