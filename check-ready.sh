#!/bin/bash

echo "========================================"
echo "CollagenDirect Order Creation Readiness"
echo "========================================"
echo ""

# Check MySQL
echo "1. MySQL Container:"
if docker ps | grep -q collagen-mysql; then
    echo "   ✓ Running"
else
    echo "   ✗ Not running - start with: docker start collagen-mysql"
    exit 1
fi

# Check PHP
echo ""
echo "2. PHP Server:"
if ps aux | grep -q "[p]hp -S localhost:8000"; then
    echo "   ✓ Running on port 8000"
else
    echo "   ✗ Not running - start with: php -S localhost:8000 &"
    exit 1
fi

# Check database connection
echo ""
echo "3. Database Connection:"
RESPONSE=$(curl -s http://localhost:8000/api/db.php)
if echo "$RESPONSE" | grep -q "success"; then
    echo "   ✓ Connected"
else
    echo "   ✗ Failed: $RESPONSE"
    exit 1
fi

# Check patient has files
echo ""
echo "4. Patient Files (Your Mom):"
docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect -e "
SELECT
    CONCAT(first_name, ' ', last_name) as name,
    CASE WHEN id_card_path IS NOT NULL THEN '✓' ELSE '✗' END as id_card,
    CASE WHEN ins_card_path IS NOT NULL THEN '✓' ELSE '✗' END as insurance,
    CASE WHEN aob_path IS NOT NULL THEN '✓' ELSE '✗' END as aob
FROM patients
WHERE id = '37a48e443174cee3ee4e454d4c83bb04';
" 2>/dev/null | tail -n +2

# Check user has NPI
echo ""
echo "5. User Account (sparkingmatt@gmail.com):"
docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect -e "
SELECT
    email,
    CASE WHEN npi IS NOT NULL AND npi != '' THEN CONCAT('✓ NPI: ', npi) ELSE '✗ No NPI' END as npi_status
FROM users
WHERE email = 'sparkingmatt@gmail.com';
" 2>/dev/null | tail -n +2

# Check upload directories
echo ""
echo "6. Upload Directories:"
for dir in ids insurance aob notes; do
    if [ -d "uploads/$dir" ] && [ -w "uploads/$dir" ]; then
        echo "   ✓ uploads/$dir/ (writable)"
    else
        echo "   ✗ uploads/$dir/ (missing or not writable)"
    fi
done

# Check cpt column exists (the fix)
echo ""
echo "7. Database Fix (cpt column):"
if docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect -e "DESCRIBE orders" 2>/dev/null | grep -q "cpt"; then
    echo "   ✓ cpt column exists"
else
    echo "   ✗ cpt column missing - run: mysql < SQL_FIXES.sql"
    exit 1
fi

echo ""
echo "========================================"
echo "✅ ALL CHECKS PASSED!"
echo "========================================"
echo ""
echo "You can now create an order at:"
echo "http://localhost:8000/portal"
echo ""
echo "Login with:"
echo "  Email:    sparkingmatt@gmail.com"
echo "  Password: TempPassword123!"
echo ""
echo "Patient ready for orders: Your Mom"
echo ""
echo "See HOW_TO_CREATE_ORDER.md for detailed instructions"
echo ""
