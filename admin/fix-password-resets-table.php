<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/db.php';

echo "=== Checking password_resets Table ===\n\n";

try {
  // Check if table exists and get its structure
  $stmt = $pdo->query("
    SELECT column_name, data_type
    FROM information_schema.columns
    WHERE table_name = 'password_resets'
    ORDER BY ordinal_position
  ");
  $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($columns)) {
    echo "✗ Table 'password_resets' does not exist\n\n";
    echo "Creating table...\n";

    // Create the table with correct structure
    $pdo->exec("
      CREATE TABLE password_resets (
        id            SERIAL PRIMARY KEY,
        user_id       VARCHAR(64) NULL,
        email         VARCHAR(255) NOT NULL,
        selector      VARCHAR(32) NOT NULL,
        token_hash    BYTEA NOT NULL,
        requested_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at    TIMESTAMP NOT NULL,
        consumed_at   TIMESTAMP NULL,
        ip            VARCHAR(64) NULL,
        ua            VARCHAR(255) NULL
      );

      CREATE INDEX idx_pr_email ON password_resets(email);
      CREATE INDEX idx_pr_selector ON password_resets(selector);
      CREATE INDEX idx_pr_user_id ON password_resets(user_id);
      CREATE INDEX idx_pr_expires ON password_resets(expires_at);
    ");

    echo "✓ Table created successfully!\n";
  } else {
    echo "✓ Table exists with columns:\n";
    foreach ($columns as $col) {
      echo "  - {$col['column_name']} ({$col['data_type']})\n";
    }

    // Check for missing columns
    $existingCols = array_column($columns, 'column_name');
    $requiredCols = ['id', 'user_id', 'email', 'selector', 'token_hash',
                     'requested_at', 'expires_at', 'consumed_at', 'ip', 'ua'];

    $missingCols = array_diff($requiredCols, $existingCols);

    if (!empty($missingCols)) {
      echo "\n✗ Missing columns: " . implode(', ', $missingCols) . "\n";
      echo "Adding missing columns...\n";

      foreach ($missingCols as $col) {
        switch ($col) {
          case 'consumed_at':
            $pdo->exec("ALTER TABLE password_resets ADD COLUMN consumed_at TIMESTAMP NULL");
            echo "  ✓ Added consumed_at\n";
            break;
          case 'ip':
            $pdo->exec("ALTER TABLE password_resets ADD COLUMN ip VARCHAR(64) NULL");
            echo "  ✓ Added ip\n";
            break;
          case 'ua':
            $pdo->exec("ALTER TABLE password_resets ADD COLUMN ua VARCHAR(255) NULL");
            echo "  ✓ Added ua\n";
            break;
        }
      }
    } else {
      echo "\n✓ All required columns present\n";
    }
  }

  echo "\n=== Table Structure Verification Complete ===\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  exit(1);
}
