<?php
/**
 * Migration: Add insurance OCR tracking fields to patients table
 */

require_once __DIR__ . '/../api/db.php';

try {
  echo "<h1>Adding Insurance OCR Fields</h1>\n";

  // Add OCR processing flag
  $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS insurance_ocr_processed BOOLEAN DEFAULT FALSE");
  echo "<p>✓ Added insurance_ocr_processed column</p>\n";

  // Add OCR processing timestamp
  $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS insurance_ocr_date TIMESTAMP");
  echo "<p>✓ Added insurance_ocr_date column</p>\n";

  // Add raw OCR data storage (for debugging/improvements)
  $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS insurance_ocr_data JSONB");
  echo "<p>✓ Added insurance_ocr_data column</p>\n";

  // Add confidence score
  $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS insurance_ocr_confidence DECIMAL(3,2)");
  echo "<p>✓ Added insurance_ocr_confidence column</p>\n";

  echo "<h2>Migration completed successfully!</h2>\n";
  echo "<p>Insurance OCR tracking fields have been added to the patients table.</p>\n";
  echo "<p>Next steps:</p>\n";
  echo "<ul>\n";
  echo "<li>Get Anthropic API key from <a href='https://console.anthropic.com'>console.anthropic.com</a></li>\n";
  echo "<li>Set environment variables in Render:</li>\n";
  echo "<ul>\n";
  echo "<li><code>ANTHROPIC_API_KEY=your_api_key</code></li>\n";
  echo "<li><code>INSURANCE_OCR_PROVIDER=anthropic</code></li>\n";
  echo "<li><code>INSURANCE_OCR_ENABLED=true</code></li>\n";
  echo "</ul>\n";
  echo "<li>OCR will automatically process insurance cards during order creation</li>\n";
  echo "</ul>\n";

} catch (PDOException $e) {
  echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
  exit(1);
}
