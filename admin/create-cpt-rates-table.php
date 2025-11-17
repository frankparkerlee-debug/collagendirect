<?php
/**
 * Create CPT rates table and populate with Medicare Allowable rates
 * These rates are used for revenue calculation on referral orders
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

echo "=== Creating CPT Rates Table ===\n\n";

try {
  echo "Step 1: Creating cpt_rates table...\n";
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS cpt_rates (
      id SERIAL PRIMARY KEY,
      hcpcs_code VARCHAR(10) NOT NULL UNIQUE,
      rate DECIMAL(10,2) NOT NULL,
      description TEXT,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
  ");
  echo "  ✓ Table created\n\n";

  echo "Step 2: Populating CPT rates from Medicare Allowable data...\n";

  // Medicare Allowable rates from Dressing Rule Matrix
  $rates = [
    'A6196' => 102.80,  // Calcium Alginate 2x2, Silver Alginate 2x2
    'A6197' => 229.80,  // Calcium Alginate 4.33x4.33, 6x6, Silver Alginate 4.33x4.33, 6x6
    'A6199' => 73.70,   // Calcium Alginate Rope
    'A6212' => 135.70,  // Silicone Foam Dressing 2x2
    'A6213' => 147.00,  // Silicone Foam Dressing 4.13x4.13, 6x6, Sacral 9x9
    'A6214' => 143.90,  // Sacral Silicone Foam 9x9
    'A6251' => 27.80,   // Super Absorbent 2x2
    'A6252' => 45.50,   // Super Absorbent 4.13x4.13
    'A6253' => 88.50,   // Super Absorbent 8x8
    'A6021' => 236.60,  // Collagen 2x2
    'A6023' => 1034.80, // Collagen 7x7
    'A6010' => 348.60,  // Collagen Powder 1g
    'A6219' => 33.25,   // Bordered Gauze 4x4
    'A6220' => 90.50,   // Bordered Gauze 6x6
    'A6221' => 139.25,  // Bordered Gauze 8x8
    'A6260' => 28.38,   // Dermal Wound Cleanser
    'A6550' => 30.52,   // NPWT Medium Dressing Kit
    'A7000' => 102.20,  // NPWT Canister (using Nisus 250ML rate for base)
  ];

  $descriptions = [
    'A6196' => 'Alginate or other fiber gelling dressing, wound cover, sterile, pad size 16 sq. in. or less, each dressing',
    'A6197' => 'Alginate or other fiber gelling dressing, wound cover, sterile, pad size more than 16 sq. in. but less than or equal to 48 sq. in., each dressing',
    'A6199' => 'Alginate or other fiber gelling dressing, wound filler, sterile, per 6 inches',
    'A6212' => 'Foam dressing, wound cover, sterile, pad size 16 square inches or less, without adhesive border, each dressing',
    'A6213' => 'Foam dressing, wound cover, sterile, pad size more than 16 square inches but less than or equal to 48 square inches, without adhesive border, each dressing',
    'A6214' => 'Foam dressing, wound cover, sterile, pad size more than 48 square inches, without adhesive border, each dressing',
    'A6251' => 'Specialty absorptive dressing, wound cover, sterile, pad size 16 sq. in. or less, without adhesive border, each dressing',
    'A6252' => 'Specialty absorptive dressing, wound cover, sterile, pad size more than 16 sq. in. but less than or equal to 48 sq. in., without adhesive border, each dressing',
    'A6253' => 'Specialty absorptive dressing, wound cover, sterile, pad size more than 48 sq. in., without adhesive border, each dressing',
    'A6021' => 'Collagen dressing, sterile, size 16 sq. in. or less, each',
    'A6023' => 'Collagen dressing, sterile, size more than 16 sq. in. but less than or equal to 48 sq. in., each',
    'A6010' => 'Collagen based wound filler, dry form, sterile, per gram of collagen',
    'A6219' => 'Sterile gauze, impregnated, pad size 16 sq. in. or less, without adhesive border, each dressing',
    'A6220' => 'Sterile gauze, impregnated, pad size more than 16 sq. in., less than or equal to 48 sq. in., without adhesive border, each dressing',
    'A6221' => 'Sterile gauze, impregnated, pad size more than 48 sq. in., without adhesive border, each dressing',
    'A6260' => 'Wound cleansers, any type, any size',
    'A6550' => 'Wound care set, for negative pressure wound therapy electrical pump, includes all supplies and accessories',
    'A7000' => 'Canister, disposable, used with suction pump, each',
  ];

  $inserted = 0;
  foreach ($rates as $code => $rate) {
    $desc = $descriptions[$code] ?? '';
    $stmt = $pdo->prepare("
      INSERT INTO cpt_rates (hcpcs_code, rate, description)
      VALUES (?, ?, ?)
      ON CONFLICT (hcpcs_code) DO UPDATE SET
        rate = EXCLUDED.rate,
        description = EXCLUDED.description,
        updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$code, $rate, $desc]);
    $inserted++;
    echo "  ✓ {$code}: \${$rate} - {$desc}\n";
  }

  echo "\n=== Setup Complete ===\n";
  echo "CPT rates inserted/updated: {$inserted}\n";
  echo "✓ Medicare Allowable rates are now available for revenue calculations\n";
  echo "✓ Referral orders will use these rates instead of wholesale pricing\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
}
