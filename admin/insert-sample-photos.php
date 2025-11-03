<?php
/**
 * Insert Sample Wound Photos - Direct SQL Approach
 * Run via: https://collagendirect.health/admin/insert-sample-photos.php
 */

require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Inserting Sample Wound Photos ===\n\n";

try {
    // $pdo is already created by db.php
    $pdo->beginTransaction();

    // Get Parker's user ID
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $userStmt->execute(['parker@senecawest.com']);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("✗ User parker@senecawest.com not found\n");
    }

    echo "✓ Found user: parker@senecawest.com (ID: {$user['id']})\n";

    // Get any 5 patients from the database
    $patientsStmt = $pdo->prepare("
        SELECT id, first_name, last_name, phone
        FROM patients
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $patientsStmt->execute();
    $patients = $patientsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($patients)) {
        die("✗ No patients found in database\n");
    }

    echo "✓ Found " . count($patients) . " patients\n\n";

    // Sample scenarios
    $scenarios = [
        [
            'notes' => 'Diabetic foot ulcer, right heel. Patient reports reduced pain over past week.',
            'location' => 'right heel',
            'days_ago' => 2
        ],
        [
            'notes' => 'Pressure wound, sacral area. Wound size appears stable, no drainage.',
            'location' => 'sacral area',
            'days_ago' => 1
        ],
        [
            'notes' => 'Surgical wound, left knee. Some redness around edges, patient reports warmth.',
            'location' => 'left knee',
            'days_ago' => 3
        ],
        [
            'notes' => 'Venous leg ulcer, right lower leg. Significant deterioration, possible infection.',
            'location' => 'right lower leg',
            'days_ago' => 0
        ],
        [
            'notes' => 'Post-surgical wound, abdomen. Healing well, no signs of infection.',
            'location' => 'abdomen',
            'days_ago' => 5
        ]
    ];

    $created = 0;

    foreach ($patients as $index => $patient) {
        if (!isset($scenarios[$index])) break;

        $scenario = $scenarios[$index];
        $photoId = generateId();
        $uploadedAt = date('Y-m-d H:i:s', strtotime("-{$scenario['days_ago']} days"));

        // Insert wound photo
        $stmt = $pdo->prepare("
            INSERT INTO wound_photos (
                id, patient_id, photo_path, photo_mime, photo_size_bytes,
                patient_notes, uploaded_via, from_phone, uploaded_at, wound_location
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $photoPath = "uploads/wound_photos/sample_{$photoId}.jpg";

        $stmt->execute([
            $photoId,
            $patient['id'],
            $photoPath,
            'image/jpeg',
            125000,
            $scenario['notes'],
            'sms',
            $patient['phone'] ?? '555-0100',
            $uploadedAt,
            $scenario['location']
        ]);

        echo "✓ Created: {$patient['first_name']} {$patient['last_name']} - {$scenario['location']}\n";
        $created++;
    }

    $pdo->commit();

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✓ SUCCESS! Created {$created} sample wound photos\n\n";

    echo "Next Steps:\n";
    echo "1. Go to: https://collagendirect.health/portal/?page=photo-reviews\n";
    echo "2. You should see {$created} pending photos\n";
    echo "3. Click each to review and generate billing codes\n\n";

    echo "Revenue Potential: $634 for 5 reviews\n";
    echo "  - 2× CPT 99213 ($92) = $184\n";
    echo "  - 2× CPT 99214 ($135) = $270\n";
    echo "  - 1× CPT 99215 ($180) = $180\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

function generateId($length = 16) {
    $bytes = random_bytes($length);
    return substr(bin2hex($bytes), 0, $length);
}
