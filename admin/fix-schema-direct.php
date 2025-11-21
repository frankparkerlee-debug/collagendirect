<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

header('Content-Type: text/plain');

echo "=== DIRECT SCHEMA FIXES ===\n\n";

try {
  // 1. Add price_referral to products if missing
  echo "1. Checking products.price_referral...\n";
  $hasCol = $pdo->query("
    SELECT column_name FROM information_schema.columns
    WHERE table_name = 'products' AND column_name = 'price_referral'
  ")->fetchColumn();
  
  if (!$hasCol) {
    $pdo->exec("ALTER TABLE products ADD COLUMN price_referral DECIMAL(10,2) DEFAULT 0.00");
    echo "   ✓ Added price_referral column\n";
  } else {
    echo "   ✓ price_referral already exists\n";
  }
  
  // 2. Ensure practice_locations table exists
  echo "\n2. Checking practice_locations table...\n";
  $tableExists = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'practice_locations'
    )
  ")->fetchColumn();
  
  if (!$tableExists) {
    $pdo->exec("
      CREATE TABLE practice_locations (
        id SERIAL PRIMARY KEY,
        user_id VARCHAR(32) NOT NULL,
        location_name VARCHAR(255) NOT NULL,
        address TEXT NOT NULL,
        city VARCHAR(100) NOT NULL,
        state VARCHAR(50) NOT NULL,
        zip VARCHAR(20) NOT NULL,
        phone VARCHAR(50),
        is_primary BOOLEAN DEFAULT FALSE,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
      )
    ");
    $pdo->exec("CREATE INDEX idx_practice_locations_user ON practice_locations(user_id)");
    echo "   ✓ Created practice_locations table\n";
  } else {
    echo "   ✓ practice_locations already exists\n";
  }
  
  // 3. Ensure admin_permissions table exists
  echo "\n3. Checking admin_permissions table...\n";
  $tableExists = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'admin_permissions'
    )
  ")->fetchColumn();
  
  if (!$tableExists) {
    $pdo->exec("
      CREATE TABLE admin_permissions (
        id SERIAL PRIMARY KEY,
        admin_user_id INTEGER NOT NULL,
        permission_key VARCHAR(100) NOT NULL,
        granted BOOLEAN DEFAULT TRUE,
        granted_by INTEGER,
        granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
        UNIQUE(admin_user_id, permission_key)
      )
    ");
    echo "   ✓ Created admin_permissions table\n";
  } else {
    echo "   ✓ admin_permissions already exists\n";
  }
  
  // 4. Add use_custom_permissions to admin_users if missing
  echo "\n4. Checking admin_users.use_custom_permissions...\n";
  $hasCol = $pdo->query("
    SELECT column_name FROM information_schema.columns
    WHERE table_name = 'admin_users' AND column_name = 'use_custom_permissions'
  ")->fetchColumn();
  
  if (!$hasCol) {
    $pdo->exec("ALTER TABLE admin_users ADD COLUMN use_custom_permissions BOOLEAN DEFAULT FALSE");
    echo "   ✓ Added use_custom_permissions column\n";
  } else {
    echo "   ✓ use_custom_permissions already exists\n";
  }
  
  echo "\n✓ ALL SCHEMA FIXES COMPLETE\n";
  echo "\nYou can now:\n";
  echo "- Use /admin/products.php (with price_referral)\n";
  echo "- Use /portal/?page=practice-locations (inline UI)\n";
  echo "- Manage admin permissions via /admin/users.php\n";
  
} catch (Exception $e) {
  echo "\n✗ ERROR: " . $e->getMessage() . "\n";
}
