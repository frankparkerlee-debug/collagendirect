<?php
/**
 * LOCAL-ONLY seed script. Creates one test physician and a few fake patients
 * with deliberately mixed-case names, so the portal patient search can be tested.
 * Safe: all data is invented. Not for production. (gitignored)
 */
declare(strict_types=1);
require __DIR__ . '/api/db.php'; // also provides uid()

$email = 'test@local.dev';
$password = 'test1234';
$hash = password_hash($password, PASSWORD_DEFAULT);

// --- Test physician ---
$existing = $pdo->prepare("SELECT id FROM users WHERE email=?");
$existing->execute([$email]);
$userId = $existing->fetchColumn();

if ($userId) {
  $pdo->prepare("UPDATE users SET password_hash=?, role='physician', first_name='Test', last_name='Doctor' WHERE id=?")
      ->execute([$hash, $userId]);
  echo "Updated existing test user: $email\n";
} else {
  $userId = uid();
  $pdo->prepare("INSERT INTO users (id,email,password_hash,first_name,last_name,role,status,account_type,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())")
      ->execute([$userId, $email, $hash, 'Test', 'Doctor', 'physician', 'active', 'referral']);
  echo "Created test user: $email\n";
}

// --- Fake patients (mixed case on purpose) ---
$patients = [
  ['John',   'SMITH',     'john.smith@example.com',  '5551110001', 'MRN1001'],
  ['jane',   'doe',       'jane.doe@example.com',    '5551110002', 'MRN1002'],
  ['Robert', 'McKinley',  'rob.mck@example.com',     '5551110003', 'MRN1003'],
  ['MARIA',  'Garcia',    'maria.g@example.com',     '5551110004', 'MRN1004'],
  ['ahmed',  'KHAN',      'ahmed.khan@example.com',  '5551110005', 'MRN1005'],
];

// Clear previous seed patients for this user so re-runs stay clean
$pdo->prepare("DELETE FROM patients WHERE user_id=?")->execute([$userId]);

$ins = $pdo->prepare("INSERT INTO patients
  (id,user_id,first_name,last_name,email,phone,mrn,dob,sex,address,city,address_state,zip,state,created_at,updated_at)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
foreach ($patients as $p) {
  $ins->execute([uid(), $userId, $p[0], $p[1], $p[2], $p[3], $p[4],
                 '1970-01-01', 'M', '123 Test St', 'Testville', 'CA', '90001', 'active']);
}

echo "Seeded " . count($patients) . " patients for user_id=$userId\n";
echo "\n=== Local login ===\n";
echo "  URL:      http://localhost:8000/login\n";
echo "  Email:    $email\n";
echo "  Password: $password\n";
