<?php
/**
 * Migration: Add Wound Photo Upload Tables
 *
 * Creates tables for:
 * - Photo requests (physician requests patient to send photo)
 * - Wound photos (actual photos uploaded by patients)
 * - Billable encounters (E/M codes for photo reviews)
 */

require_once __DIR__ . '/../api/db.php';

echo "<pre>\n";
echo "=== Adding Wound Photo Upload Tables ===\n\n";

try {
  // 1. Photo Requests Table
  echo "Creating photo_requests table...\n";
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS photo_requests (
      id VARCHAR(64) PRIMARY KEY,
      patient_id VARCHAR(64) NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
      physician_id VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,

      -- Request details
      requested_at TIMESTAMP DEFAULT NOW(),
      requested_by VARCHAR(64) REFERENCES users(id),
      wound_location VARCHAR(100),
      request_notes TEXT,

      -- Secure token for link-based upload (email fallback)
      upload_token VARCHAR(64) UNIQUE,
      token_expires_at TIMESTAMP,

      -- Upload status
      completed BOOLEAN DEFAULT FALSE,
      photo_id VARCHAR(64) REFERENCES wound_photos(id),
      uploaded_at TIMESTAMP,

      -- Notification tracking
      sms_sent BOOLEAN DEFAULT FALSE,
      sms_sent_at TIMESTAMP,
      email_sent BOOLEAN DEFAULT FALSE,
      email_sent_at TIMESTAMP,

      created_at TIMESTAMP DEFAULT NOW(),
      updated_at TIMESTAMP DEFAULT NOW()
    )
  ");
  echo "✓ photo_requests table created\n\n";

  // 2. Wound Photos Table
  echo "Creating wound_photos table...\n";
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS wound_photos (
      id VARCHAR(64) PRIMARY KEY,
      patient_id VARCHAR(64) NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
      photo_request_id VARCHAR(64) REFERENCES photo_requests(id),

      uploaded_at TIMESTAMP DEFAULT NOW(),
      uploaded_via VARCHAR(20), -- 'sms', 'email_link', 'portal', 'manual'
      photo_path VARCHAR(500) NOT NULL,
      photo_mime VARCHAR(100),
      photo_size_bytes INT,
      patient_notes TEXT,

      -- Metadata
      wound_location VARCHAR(100),
      from_phone VARCHAR(20), -- if uploaded via SMS

      -- Review tracking
      reviewed BOOLEAN DEFAULT FALSE,
      reviewed_at TIMESTAMP,
      reviewed_by VARCHAR(64) REFERENCES users(id),

      -- Billing tracking
      billed BOOLEAN DEFAULT FALSE,
      billable_encounter_id VARCHAR(64) REFERENCES billable_encounters(id),

      created_at TIMESTAMP DEFAULT NOW()
    )
  ");
  echo "✓ wound_photos table created\n\n";

  // 3. Billable Encounters Table
  echo "Creating billable_encounters table...\n";
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS billable_encounters (
      id VARCHAR(64) PRIMARY KEY,
      patient_id VARCHAR(64) NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
      physician_id VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,

      encounter_date TIMESTAMP DEFAULT NOW(),
      encounter_type VARCHAR(50) DEFAULT 'telehealth_photo_review',

      -- Related data
      wound_photo_id VARCHAR(64) REFERENCES wound_photos(id),

      -- Physician assessment
      assessment VARCHAR(100), -- 'improving', 'stable', 'concern', 'urgent'
      physician_notes TEXT,
      review_duration_seconds INT, -- auto-tracked

      -- Auto-generated billing data
      cpt_code VARCHAR(10) DEFAULT '99213',
      modifier VARCHAR(10) DEFAULT '95',
      icd10_codes TEXT[], -- array of diagnosis codes
      charge_amount DECIMAL(10,2),

      -- Auto-generated documentation
      clinical_note TEXT,

      -- Status tracking
      status VARCHAR(20) DEFAULT 'pending', -- pending, approved, exported, billed
      approved_at TIMESTAMP,
      approved_by VARCHAR(64) REFERENCES users(id),
      exported BOOLEAN DEFAULT FALSE,
      exported_at TIMESTAMP,
      billed_at TIMESTAMP,

      created_at TIMESTAMP DEFAULT NOW(),
      updated_at TIMESTAMP DEFAULT NOW()
    )
  ");
  echo "✓ billable_encounters table created\n\n";

  // 4. Add indexes for performance
  echo "Adding indexes...\n";

  $indexes = [
    "CREATE INDEX IF NOT EXISTS idx_photo_requests_patient ON photo_requests(patient_id)",
    "CREATE INDEX IF NOT EXISTS idx_photo_requests_physician ON photo_requests(physician_id)",
    "CREATE INDEX IF NOT EXISTS idx_photo_requests_token ON photo_requests(upload_token)",
    "CREATE INDEX IF NOT EXISTS idx_photo_requests_completed ON photo_requests(completed)",

    "CREATE INDEX IF NOT EXISTS idx_wound_photos_patient ON wound_photos(patient_id)",
    "CREATE INDEX IF NOT EXISTS idx_wound_photos_reviewed ON wound_photos(reviewed)",
    "CREATE INDEX IF NOT EXISTS idx_wound_photos_uploaded_at ON wound_photos(uploaded_at DESC)",

    "CREATE INDEX IF NOT EXISTS idx_billable_encounters_patient ON billable_encounters(patient_id)",
    "CREATE INDEX IF NOT EXISTS idx_billable_encounters_physician ON billable_encounters(physician_id)",
    "CREATE INDEX IF NOT EXISTS idx_billable_encounters_date ON billable_encounters(encounter_date)",
    "CREATE INDEX IF NOT EXISTS idx_billable_encounters_status ON billable_encounters(status)",
    "CREATE INDEX IF NOT EXISTS idx_billable_encounters_exported ON billable_encounters(exported)"
  ];

  foreach ($indexes as $index) {
    $pdo->exec($index);
  }
  echo "✓ Indexes created\n\n";

  // 5. Create uploads directory for wound photos
  echo "Creating uploads directory...\n";
  $uploadsDir = __DIR__ . '/../uploads/wound_photos';
  if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
    echo "✓ Created: $uploadsDir\n";
  } else {
    echo "✓ Directory already exists: $uploadsDir\n";
  }
  echo "\n";

  echo "=== Migration Complete! ===\n\n";

  echo "Next steps:\n";
  echo "1. Set up Twilio account (see TWILIO_SETUP.md)\n";
  echo "2. Add Twilio credentials to environment variables\n";
  echo "3. Install Twilio SDK: composer require twilio/sdk\n";
  echo "4. Configure Twilio webhook to point to /api/twilio/receive-mms.php\n";

} catch (Exception $e) {
  echo "✗ ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}

echo "</pre>";
