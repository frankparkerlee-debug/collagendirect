<?php
/**
 * Create Sample Wound Photos
 *
 * Creates 5 sample wound photos for testing the photo review system
 * Run via: https://collagendirect.health/admin/create-sample-wound-photos.php
 */

require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Creating Sample Wound Photos ===\n\n";

try {
    $pdo = getPDO();

    // Get Parker's user ID
    echo "Step 1: Finding user parker@senecawest.com...\n";
    $userStmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE email = ?");
    $userStmt->execute(['parker@senecawest.com']);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("✗ User parker@senecawest.com not found\n");
    }

    echo "✓ Found user: {$user['first_name']} {$user['last_name']} (ID: {$user['id']})\n\n";

    // Get some patients for this physician
    echo "Step 2: Finding patients for this physician...\n";
    $patientsStmt = $pdo->prepare("
        SELECT p.id, p.first_name, p.last_name, p.phone
        FROM patients p
        INNER JOIN admin_physicians ap ON ap.physician_user_id = p.user_id
        WHERE ap.admin_id = ?
        LIMIT 5
    ");
    $patientsStmt->execute([$user['id']]);
    $patients = $patientsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($patients) < 5) {
        echo "⚠ Warning: Only found " . count($patients) . " patients for this physician\n";
        if (count($patients) === 0) {
            die("✗ No patients found for this physician\n");
        }
    } else {
        echo "✓ Found " . count($patients) . " patients\n\n";
    }

    // Sample wound photo scenarios
    $scenarios = [
        [
            'notes' => 'Diabetic foot ulcer, right heel. Patient reports reduced pain over past week.',
            'location' => 'right heel',
            'assessment' => 'improving',
            'em_code' => '99214',
            'severity' => 'moderate',
            'days_ago' => 2
        ],
        [
            'notes' => 'Pressure wound, sacral area. Wound size appears stable, no drainage.',
            'location' => 'sacral area',
            'assessment' => 'stable',
            'em_code' => '99213',
            'severity' => 'low',
            'days_ago' => 1
        ],
        [
            'notes' => 'Surgical wound, left knee. Some redness around edges, patient reports warmth.',
            'location' => 'left knee',
            'assessment' => 'concern',
            'em_code' => '99214',
            'severity' => 'moderate',
            'days_ago' => 3
        ],
        [
            'notes' => 'Venous leg ulcer, right lower leg. Significant deterioration, possible infection.',
            'location' => 'right lower leg',
            'assessment' => 'urgent',
            'em_code' => '99215',
            'severity' => 'high',
            'days_ago' => 0
        ],
        [
            'notes' => 'Post-surgical wound, abdomen. Healing well, no signs of infection.',
            'location' => 'abdomen',
            'assessment' => 'improving',
            'em_code' => '99213',
            'severity' => 'low',
            'days_ago' => 5
        ]
    ];

    echo "Step 3: Creating sample wound photos...\n";
    echo str_repeat("-", 60) . "\n";

    $createdCount = 0;

    foreach ($patients as $index => $patient) {
        if (!isset($scenarios[$index])) break;

        $scenario = $scenarios[$index];
        $photoId = generateId();
        $uploadedAt = date('Y-m-d H:i:s', strtotime("-{$scenario['days_ago']} days"));

        echo "\nPatient: {$patient['first_name']} {$patient['last_name']}\n";
        echo "Location: {$scenario['location']}\n";
        echo "Assessment: {$scenario['assessment']}\n";
        echo "Notes: {$scenario['notes']}\n";

        // Create wound photo record
        $photoStmt = $pdo->prepare("
            INSERT INTO wound_photos (
                id, patient_id, photo_path, photo_mime, photo_size_bytes,
                patient_notes, uploaded_via, from_phone, uploaded_at,
                wound_location
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $photoPath = "uploads/wound_photos/sample_{$photoId}.jpg";

        $photoStmt->execute([
            $photoId,
            $patient['id'],
            $photoPath,
            'image/jpeg',
            125000, // 125KB sample size
            $scenario['notes'],
            'sms',
            $patient['phone'] ?? '555-0100',
            $uploadedAt,
            $scenario['location']
        ]);

        echo "✓ Created wound photo record (ID: {$photoId})\n";
        $createdCount++;
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✓ Successfully created {$createdCount} sample wound photos\n\n";

    echo "Next Steps:\n";
    echo "1. Go to: https://collagendirect.health/portal/?page=photo-reviews\n";
    echo "2. You should see {$createdCount} pending photo reviews\n";
    echo "3. Click on each photo to review and assign E/M billing codes\n\n";

    echo "Sample Scenarios Created:\n";
    foreach ($scenarios as $i => $s) {
        if ($i >= $createdCount) break;
        echo "  " . ($i + 1) . ". {$s['location']} - {$s['assessment']} ({$s['em_code']})\n";
    }

    echo "\n⚠ Note: Sample photos don't have actual image files.\n";
    echo "   The photo_path points to sample files that don't exist.\n";
    echo "   This is fine for testing the review workflow and billing.\n";
    echo "   Real photos will be uploaded via Twilio SMS.\n\n";

    echo "Revenue Potential:\n";
    echo "  - 99213 ($92) × 2 photos = $184\n";
    echo "  - 99214 ($135) × 2 photos = $270\n";
    echo "  - 99215 ($180) × 1 photo = $180\n";
    echo "  Total: $634 for these 5 reviews\n\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

function generateId($length = 16) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $id = '';
    for ($i = 0; $i < $length; $i++) {
        $id .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $id;
}
