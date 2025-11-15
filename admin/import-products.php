<?php
/**
 * Product Import Script
 * Imports products from CSV data into the database
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== Product Import Script ===\n\n";

// CSV data as array (from your spreadsheet)
// NOTE: Medicare Allowable = referral revenue per piece, Price per Box = wholesale pricing
$csvData = [
    ['Brand' => 'AlgiHeal', 'Primary' => 'YES', 'Secondary' => 'NO', 'Product' => 'Calcium Alginate 2x2', 'HCPCS' => 'A6196', 'Minimal Exudate' => 'X', 'REF' => 'MD02022CA', 'Medicare Allowable' => '$102.80', 'Pieces per Box' => '10', 'Price per Box' => '$25.00'],
    ['Brand' => 'AlgiHeal', 'Primary' => 'YES', 'Secondary' => 'NO', 'Product' => 'Calcium Alginate 4.33x4.33', 'HCPCS' => 'A6197', 'Minimal Exudate' => 'X', 'REF' => 'MD04044CA', 'Medicare Allowable' => '$229.80', 'Pieces per Box' => '10', 'Price per Box' => '$40.00'],
    ['Brand' => 'AlgiHeal', 'Primary' => 'YES', 'Secondary' => 'NO', 'Product' => 'Calcium Alginate 6x6', 'HCPCS' => 'A6197', 'Minimal Exudate' => 'X', 'REF' => 'MD06066CA', 'Medicare Allowable' => '$229.80', 'Pieces per Box' => '10', 'Price per Box' => '$57.50'],
    ['Brand' => 'AlgiHeal', 'Primary' => 'YES', 'Secondary' => 'NO', 'Product' => 'Calcium Alginate Rope', 'HCPCS' => 'A6199', 'Minimal Exudate' => 'X', 'REF' => 'MDCAR', 'Medicare Allowable' => '$73.70', 'Pieces per Box' => '10', 'Price per Box' => '$50.00'],
    ['Brand' => 'AlgiHeal AG', 'Primary' => 'YES', 'Secondary' => 'NO', 'Product' => 'Silver Alginate 2x2', 'HCPCS' => 'A6196', 'Minimal Exudate' => 'X', 'REF' => 'MD02022SA', 'Medicare Allowable' => '$102.80', 'Pieces per Box' => '10', 'Price per Box' => '$27.50'],
    ['Brand' => 'AlgiHeal AG', 'Primary' => 'YES', 'Secondary' => 'NO', 'Product' => 'Silver Alginate 4.33x4.33', 'HCPCS' => 'A6197', 'Minimal Exudate' => 'X', 'REF' => 'MD04044SA', 'Medicare Allowable' => '$229.80', 'Pieces per Box' => '10', 'Price per Box' => '$47.50'],
    ['Brand' => 'AlgiHeal AG', 'Primary' => 'YES', 'Secondary' => 'NO', 'Product' => 'Silver Alginate 6x6', 'HCPCS' => 'A6197', 'Minimal Exudate' => 'X', 'REF' => 'MD06066SA', 'Medicare Allowable' => '$229.80', 'Pieces per Box' => '10', 'Price per Box' => '$62.50'],
    ['Brand' => 'CuraFoam', 'Primary' => 'YES', 'Secondary' => 'YES', 'Product' => 'Silicone Foam 2x2', 'HCPCS' => 'A6212', 'Minimal Exudate' => 'X', 'REF' => 'MD02022SFB', 'Medicare Allowable' => '$135.70', 'Pieces per Box' => '10', 'Price per Box' => '$30.00'],
    ['Brand' => 'CuraFoam', 'Primary' => 'YES', 'Secondary' => 'YES', 'Product' => 'Silicone Foam 4.13x4.13', 'HCPCS' => 'A6213', 'Minimal Exudate' => 'X', 'REF' => 'MD04045FB', 'Medicare Allowable' => '$147.00', 'Pieces per Box' => '10', 'Price per Box' => '$20.00'],
    ['Brand' => 'CuraFoam', 'Primary' => 'YES', 'Secondary' => 'YES', 'Product' => 'Silicone Foam 6x6', 'HCPCS' => 'A6213', 'Minimal Exudate' => 'X', 'REF' => 'MD06066SFB', 'Medicare Allowable' => '$147.00', 'Pieces per Box' => '10', 'Price per Box' => '$45.00'],
    ['Brand' => 'CuraFoam', 'Primary' => 'YES', 'Secondary' => 'YES', 'Product' => 'Sacral Silicone 9"x9"', 'HCPCS' => 'A6214', 'Minimal Exudate' => 'X', 'REF' => 'GEN-14700', 'Medicare Allowable' => '$143.90', 'Pieces per Box' => '10', 'Price per Box' => '$65.00'],
    ['Brand' => 'HydraPad', 'Primary' => 'YES', 'Secondary' => 'YES', 'Product' => 'Super Absorb 2x2', 'HCPCS' => 'A6251', 'Minimal Exudate' => 'X', 'REF' => 'MD02022SAN', 'Medicare Allowable' => '$27.80', 'Pieces per Box' => '10', 'Price per Box' => '$15.00'],
    ['Brand' => 'HydraPad', 'Primary' => 'YES', 'Secondary' => 'YES', 'Product' => 'Super Absorb 4.13x4.13', 'HCPCS' => 'A6252', 'Minimal Exudate' => 'X', 'REF' => 'MD04045AN', 'Medicare Allowable' => '$45.50', 'Pieces per Box' => '10', 'Price per Box' => '$25.00'],
    ['Brand' => 'HydraPad', 'Primary' => 'YES', 'Secondary' => 'YES', 'Product' => 'Super Absorb 8x8', 'HCPCS' => 'A6253', 'Minimal Exudate' => 'X', 'REF' => 'MD08088SAN', 'Medicare Allowable' => '$88.50', 'Pieces per Box' => '10', 'Price per Box' => '$90.00'],
    ['Brand' => 'HydraPad', 'Primary' => 'YES', 'Secondary' => 'YES', 'Product' => 'Super Absorb 2x2', 'HCPCS' => 'A6251', 'Minimal Exudate' => 'X', 'REF' => 'MD02022SAA', 'Medicare Allowable' => '$27.80', 'Pieces per Box' => '10', 'Price per Box' => '$17.50'],
    ['Brand' => 'HydraPad', 'Primary' => 'YES', 'Secondary' => 'YES', 'Product' => 'Super Absorb 4.13x4.13', 'HCPCS' => 'A6252', 'Minimal Exudate' => 'X', 'REF' => 'MD04045AA', 'Medicare Allowable' => '$45.50', 'Pieces per Box' => '10', 'Price per Box' => '$37.50'],
    ['Brand' => 'HydraPad', 'Primary' => 'YES', 'Secondary' => 'YES', 'Product' => 'Super Absorb 8x8', 'HCPCS' => 'A6253', 'Minimal Exudate' => 'X', 'REF' => 'MD08088SAA', 'Medicare Allowable' => '$88.50', 'Pieces per Box' => '10', 'Price per Box' => '$50.00'],
    ['Brand' => 'CollaHeal', 'Primary' => 'YES', 'Secondary' => 'NO', 'Product' => 'Collagen Drx 2x2', 'HCPCS' => 'A6021', 'Minimal Exudate' => 'Full Thickness', 'REF' => 'MD02022CS', 'Medicare Allowable' => '$3,286.00', 'Pieces per Box' => '10', 'Price per Box' => '$120.00'],
    ['Brand' => 'CollaHeal', 'Primary' => 'YES', 'Secondary' => 'NO', 'Product' => 'Collagen Drx 7x7', 'HCPCS' => 'A6023', 'Minimal Exudate' => 'Full Thickness', 'REF' => 'MD07070CS', 'Medicare Allowable' => '$1,034.60', 'Pieces per Box' => '10', 'Price per Box' => '$900.00'],
    ['Brand' => 'CollaHeal', 'Primary' => 'YES', 'Secondary' => 'NO', 'Product' => 'Collagen 1"x6"', 'HCPCS' => 'A6010', 'Minimal Exudate' => 'Full Thickness', 'REF' => 'MD001000', 'Medicare Allowable' => '$348.60', 'Pieces per Box' => '10', 'Price per Box' => '$165.00'],
    ['Brand' => 'Gauze', 'Primary' => 'NO', 'Secondary' => 'YES', 'Product' => 'Bordered Gauze 4"x4"(2"x2")', 'HCPCS' => 'A6219', 'Minimal Exudate' => '', 'REF' => 'GEN-15410', 'Medicare Allowable' => '$33.25', 'Pieces per Box' => '25', 'Price per Box' => '$15.00'],
    ['Brand' => 'Gauze', 'Primary' => 'NO', 'Secondary' => 'YES', 'Product' => 'Bordered Gauze 6"x6"(4.5"x4.5")', 'HCPCS' => 'A6220', 'Minimal Exudate' => '', 'REF' => 'GEN-15610', 'Medicare Allowable' => '$90.50', 'Pieces per Box' => '25', 'Price per Box' => '$30.80'],
    ['Brand' => 'Gauze', 'Primary' => 'NO', 'Secondary' => 'YES', 'Product' => 'Bordered Gauze 8"x8"(6.5"x6.5")', 'HCPCS' => 'A6221', 'Minimal Exudate' => '', 'REF' => 'GEN-15810', 'Medicare Allowable' => '$139.25', 'Pieces per Box' => '25', 'Price per Box' => '$65.00'],
    ['Brand' => 'Dermat', 'Primary' => 'NO', 'Secondary' => 'NO', 'Product' => 'Dermal Wound 8oz Bottle', 'HCPCS' => 'A6260', 'Minimal Exudate' => '', 'REF' => 'MD08WCS', 'Medicare Allowable' => '$28.38', 'Pieces per Box' => '6', 'Price per Box' => '$3.96'],
    ['Brand' => 'Sacral', 'Primary' => 'YES', 'Secondary' => 'YES', 'Product' => 'Silicone Foam 9"X9"', 'HCPCS' => 'A6213', 'Minimal Exudate' => '', 'REF' => 'GEN-14700', 'Medicare Allowable' => '$147.00', 'Pieces per Box' => '10', 'Price per Box' => '$65.00'],
    ['Brand' => 'Arobella', 'Primary' => 'NO', 'Secondary' => 'NO', 'Product' => 'Disposable Tubing Set', 'HCPCS' => 'N/A', 'Minimal Exudate' => '', 'REF' => 'AR-0001', 'Medicare Allowable' => '', 'Pieces per Box' => '1', 'Price per Box' => '$35.00'],
    ['Brand' => 'NPWT', 'Primary' => 'NO', 'Secondary' => 'NO', 'Product' => 'Medium Drsg-Medium', 'HCPCS' => 'A6550', 'Minimal Exudate' => '', 'REF' => 'NP-MDK', 'Medicare Allowable' => '$30.52', 'Pieces per Box' => '1', 'Price per Box' => '$27.50'],
    ['Brand' => 'NPWT', 'Primary' => 'NO', 'Secondary' => 'NO', 'Product' => 'Versa Canister 100ML', 'HCPCS' => 'A7000', 'Minimal Exudate' => '', 'REF' => 'NP-100V', 'Medicare Allowable' => '$10.22', 'Pieces per Box' => '1', 'Price per Box' => '$11.50'],
    ['Brand' => 'NPWT', 'Primary' => 'NO', 'Secondary' => 'NO', 'Product' => 'Nisus Canister 250ML', 'HCPCS' => 'A7000', 'Minimal Exudate' => '', 'REF' => 'NP-250N', 'Medicare Allowable' => '$102.20', 'Pieces per Box' => '10', 'Price per Box' => '$13.50'],
];

function cleanPrice(string $price): float {
    return (float) str_replace(['$', ','], '', $price);
}

function cleanInt(string $value): int {
    return (int) str_replace([','], '', $value);
}

try {
    $pdo->beginTransaction();

    // First, clear existing products
    echo "Clearing existing products...\n";
    $pdo->exec("DELETE FROM products");
    echo "✓ Cleared existing products\n\n";

    $imported = 0;
    $errors = [];

    echo "Importing products...\n\n";

    foreach ($csvData as $index => $row) {
        $lineNum = $index + 2; // +2 because of header row and 0-index

        try {
            // Extract and clean data
            $sku = trim($row['REF']);
            $name = trim($row['Product']);
            $brand = trim($row['Brand']);
            $hcpcs = trim($row['HCPCS']);
            $medicareAllowable = !empty($row['Medicare Allowable']) ? cleanPrice($row['Medicare Allowable']) : 0;
            $piecesPerBox = cleanInt($row['Pieces per Box']);
            $pricePerBox = cleanPrice($row['Price per Box']);

            // Validate required fields
            if (empty($sku)) {
                $errors[] = "Line $lineNum: Missing SKU/REF";
                continue;
            }
            if (empty($name)) {
                $errors[] = "Line $lineNum: Missing Product name";
                continue;
            }

            // Build description from wound type indicators
            $description = $brand . ' - ';
            $woundTypes = [];

            if (!empty($row['Minimal Exudate']) && $row['Minimal Exudate'] === 'X') {
                $woundTypes[] = 'Minimal Exudate';
            }
            if (!empty($row['Moderate Exudate'])) {
                $woundTypes[] = str_replace('Full Thickness', 'Moderate Exudate/Full Thickness', $row['Moderate Exudate']);
            }
            if (!empty($row['Heavy Exudate'])) {
                $woundTypes[] = str_replace('Full Thickness X', 'Heavy Exudate/Full Thickness', $row['Heavy Exudate']);
            }

            $description .= !empty($woundTypes) ? implode(', ', $woundTypes) : 'Medical dressing';

            // Determine if product is primary or secondary dressing
            $isPrimary = ($row['Primary'] === 'YES');
            $isSecondary = ($row['Secondary'] === 'YES');

            // Build category
            $category = $brand;
            if ($isPrimary && $isSecondary) {
                $category .= ' (Primary/Secondary)';
            } elseif ($isPrimary) {
                $category .= ' (Primary)';
            } elseif ($isSecondary) {
                $category .= ' (Secondary)';
            }

            // Insert product
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    sku, name, description, category, size,
                    hcpcs_code, price_admin, price_wholesale,
                    pieces_per_box, active, created_at, updated_at
                ) VALUES (
                    :sku, :name, :description, :category, :size,
                    :hcpcs_code, :price_admin, :price_wholesale,
                    :pieces_per_box, :active, NOW(), NOW()
                )
                ON CONFLICT (sku) DO UPDATE SET
                    name = EXCLUDED.name,
                    description = EXCLUDED.description,
                    category = EXCLUDED.category,
                    hcpcs_code = EXCLUDED.hcpcs_code,
                    price_admin = EXCLUDED.price_admin,
                    price_wholesale = EXCLUDED.price_wholesale,
                    pieces_per_box = EXCLUDED.pieces_per_box,
                    updated_at = NOW()
            ");

            $stmt->execute([
                ':sku' => $sku,
                ':name' => $name,
                ':description' => $description,
                ':category' => $category,
                ':size' => '',
                ':hcpcs_code' => ($hcpcs !== 'N/A') ? $hcpcs : null,
                ':price_admin' => $medicareAllowable, // Medicare Allowable = referral revenue per piece
                ':price_wholesale' => $pricePerBox,   // Price per Box = wholesale pricing
                ':pieces_per_box' => $piecesPerBox,
                ':active' => true
            ]);

            $imported++;
            echo "  ✓ Imported: $sku - $name (Medicare: $" . number_format($medicareAllowable, 2) . "/pc, Wholesale: $" . number_format($pricePerBox, 2) . "/box)\n";

        } catch (PDOException $e) {
            $errors[] = "Line $lineNum ($sku): " . $e->getMessage();
        }
    }

    $pdo->commit();

    echo "\n=== Import Summary ===\n";
    echo "Products imported: $imported\n";
    echo "Errors: " . count($errors) . "\n";

    if (!empty($errors)) {
        echo "\nErrors encountered:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }

    // Show product count by category
    echo "\nProducts by category:\n";
    $categories = $pdo->query("
        SELECT category, COUNT(*) as count
        FROM products
        WHERE active = true
        GROUP BY category
        ORDER BY category
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($categories as $cat) {
        echo "  - {$cat['category']}: {$cat['count']} products\n";
    }

    echo "\n✓ Product import completed successfully!\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ Import failed: " . $e->getMessage() . "\n";
    http_response_code(500);
    exit(1);
}
