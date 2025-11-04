<?php
/**
 * Add table to store complete AI approval score feedback
 * This allows feedback to persist when users return to patient profile
 *
 * Run via: https://collagendirect.health/admin/add-approval-feedback-table.php
 */

require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Adding AI Approval Feedback Table ===\n\n";

try {
    // Check if table already exists
    echo "Step 1: Checking if table exists...\n";
    $checkTable = $pdo->query("
        SELECT COUNT(*) as cnt
        FROM information_schema.tables
        WHERE table_name = 'patient_approval_scores'
    ");
    $tableExists = (int)$checkTable->fetchColumn() > 0;

    if ($tableExists) {
        echo "  ✓ Table 'patient_approval_scores' already exists\n\n";
    } else {
        echo "  Creating table 'patient_approval_scores'...\n";

        $pdo->exec("
            CREATE TABLE patient_approval_scores (
                id SERIAL PRIMARY KEY,
                patient_id VARCHAR(32) NOT NULL,
                score VARCHAR(10) NOT NULL,
                score_numeric INTEGER NOT NULL,
                summary TEXT,
                missing_items JSONB,
                complete_items JSONB,
                recommendations JSONB,
                concerns JSONB,
                document_analysis JSONB,
                created_at TIMESTAMP DEFAULT NOW(),
                created_by VARCHAR(32),
                FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
            )
        ");

        echo "  ✓ Table created\n";

        // Add index for faster lookups
        echo "  Adding indexes...\n";
        $pdo->exec("CREATE INDEX idx_approval_scores_patient ON patient_approval_scores(patient_id)");
        $pdo->exec("CREATE INDEX idx_approval_scores_created ON patient_approval_scores(created_at DESC)");
        echo "  ✓ Indexes created\n";
    }

    // Add column comments for documentation
    echo "\nStep 2: Adding column comments...\n";
    $pdo->exec("COMMENT ON TABLE patient_approval_scores IS 'Stores complete AI approval score feedback for patient profiles'");
    $pdo->exec("COMMENT ON COLUMN patient_approval_scores.score IS 'Color-coded score: RED, YELLOW, or GREEN'");
    $pdo->exec("COMMENT ON COLUMN patient_approval_scores.score_numeric IS 'Numeric score from 0-100'");
    $pdo->exec("COMMENT ON COLUMN patient_approval_scores.summary IS '2-3 sentence overall assessment from AI'");
    $pdo->exec("COMMENT ON COLUMN patient_approval_scores.missing_items IS 'JSON array of missing/incomplete items'");
    $pdo->exec("COMMENT ON COLUMN patient_approval_scores.complete_items IS 'JSON array of complete items'");
    $pdo->exec("COMMENT ON COLUMN patient_approval_scores.recommendations IS 'JSON array of specific actions to improve score'");
    $pdo->exec("COMMENT ON COLUMN patient_approval_scores.concerns IS 'JSON array of specific concerns'");
    $pdo->exec("COMMENT ON COLUMN patient_approval_scores.document_analysis IS 'JSON object with detailed document analysis'");
    echo "  ✓ Comments added\n";

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✓ SUCCESS! AI Approval Feedback table ready\n\n";

    echo "This table will store:\n";
    echo "  - Complete AI approval score and color (RED/YELLOW/GREEN)\n";
    echo "  - Summary assessment from AI\n";
    echo "  - Missing items that need attention\n";
    echo "  - Complete items (positive feedback)\n";
    echo "  - Specific recommendations for improving score\n";
    echo "  - Concerns identified by AI\n";
    echo "  - Detailed document analysis\n\n";

    echo "Feedback will persist when physicians return to patient profile.\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
