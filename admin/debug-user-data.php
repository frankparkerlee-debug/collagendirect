<?php
/**
 * Debug script to investigate data crossover issues
 * Shows which users have which patients and billable encounters
 */

require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== USER DATA INVESTIGATION ===\n\n";

try {
  // Get the three users in question
  $users = $pdo->query("
    SELECT id, email, role, created_at
    FROM users
    WHERE email IN ('frank.parker.lee@gmail.com', 'parker@senecawest.com', 'parker@collagendirect.health')
    ORDER BY email
  ")->fetchAll(PDO::FETCH_ASSOC);

  echo "USERS:\n";
  echo str_repeat("-", 80) . "\n";
  foreach ($users as $user) {
    echo "Email: {$user['email']}\n";
    echo "  ID: {$user['id']}\n";
    echo "  Role: {$user['role']}\n";
    echo "  Created: {$user['created_at']}\n";
    echo "\n";
  }

  echo "\nPATIENTS BY USER:\n";
  echo str_repeat("=", 80) . "\n\n";

  foreach ($users as $user) {
    echo "User: {$user['email']} (ID: {$user['id']})\n";
    echo str_repeat("-", 80) . "\n";

    // Get patients
    $patients = $pdo->prepare("
      SELECT id, first_name, last_name, dob, mrn, created_at
      FROM patients
      WHERE user_id = ?
      ORDER BY created_at DESC
    ");
    $patients->execute([$user['id']]);
    $patientList = $patients->fetchAll(PDO::FETCH_ASSOC);

    echo "Total patients: " . count($patientList) . "\n";

    if (count($patientList) > 0) {
      echo "\nPatient List:\n";
      foreach ($patientList as $p) {
        echo "  - {$p['first_name']} {$p['last_name']} (ID: {$p['id']}, MRN: {$p['mrn']}, DOB: {$p['dob']}, Created: {$p['created_at']})\n";
      }
    }

    echo "\n";
  }

  echo "\nBILLABLE ENCOUNTERS BY PHYSICIAN:\n";
  echo str_repeat("=", 80) . "\n\n";

  foreach ($users as $user) {
    echo "Physician: {$user['email']} (ID: {$user['id']})\n";
    echo str_repeat("-", 80) . "\n";

    // Get billable encounters
    $encounters = $pdo->prepare("
      SELECT
        e.id,
        e.encounter_date,
        e.cpt_code,
        e.charge_amount,
        e.assessment,
        p.first_name,
        p.last_name,
        p.user_id as patient_owner_id
      FROM billable_encounters e
      JOIN patients p ON p.id = e.patient_id
      WHERE e.physician_id = ?
      ORDER BY e.encounter_date DESC
      LIMIT 20
    ");
    $encounters->execute([$user['id']]);
    $encounterList = $encounters->fetchAll(PDO::FETCH_ASSOC);

    echo "Total encounters: " . count($encounterList) . "\n";

    if (count($encounterList) > 0) {
      echo "\nRecent Encounters:\n";
      foreach ($encounterList as $enc) {
        echo "  - {$enc['encounter_date']}: {$enc['first_name']} {$enc['last_name']} (Patient Owner ID: {$enc['patient_owner_id']}) - {$enc['cpt_code']} \${$enc['charge_amount']}\n";
      }
    }

    // Get total revenue
    $revenue = $pdo->prepare("
      SELECT COUNT(*) as count, COALESCE(SUM(charge_amount), 0) as total
      FROM billable_encounters
      WHERE physician_id = ?
    ");
    $revenue->execute([$user['id']]);
    $revData = $revenue->fetch(PDO::FETCH_ASSOC);

    echo "\nTotal Revenue: \$" . number_format($revData['total'], 2) . " ({$revData['count']} encounters)\n";
    echo "\n";
  }

  echo "\nWOUND PHOTOS BY PATIENT OWNER:\n";
  echo str_repeat("=", 80) . "\n\n";

  foreach ($users as $user) {
    echo "User: {$user['email']} (ID: {$user['id']})\n";
    echo str_repeat("-", 80) . "\n";

    // Get wound photos from their patients
    $photos = $pdo->prepare("
      SELECT
        wp.id,
        wp.uploaded_at,
        wp.reviewed,
        p.first_name,
        p.last_name,
        p.user_id as patient_owner_id
      FROM wound_photos wp
      JOIN patients p ON p.id = wp.patient_id
      WHERE p.user_id = ?
      ORDER BY wp.uploaded_at DESC
      LIMIT 20
    ");
    $photos->execute([$user['id']]);
    $photoList = $photos->fetchAll(PDO::FETCH_ASSOC);

    echo "Total wound photos from their patients: " . count($photoList) . "\n";

    if (count($photoList) > 0) {
      echo "\nRecent Photos:\n";
      foreach ($photoList as $photo) {
        $status = $photo['reviewed'] ? 'Reviewed' : 'Pending';
        echo "  - {$photo['uploaded_at']}: {$photo['first_name']} {$photo['last_name']} (Patient Owner: {$photo['patient_owner_id']}) - {$status}\n";
      }
    }

    echo "\n";
  }

  echo "\nCROSS-CHECK: Are there any billable_encounters where physician_id != patient.user_id?\n";
  echo str_repeat("=", 80) . "\n\n";

  $crossCheck = $pdo->query("
    SELECT
      e.id as encounter_id,
      e.physician_id,
      e.patient_id,
      p.user_id as patient_owner_id,
      p.first_name,
      p.last_name,
      u1.email as physician_email,
      u2.email as patient_owner_email
    FROM billable_encounters e
    JOIN patients p ON p.id = e.patient_id
    LEFT JOIN users u1 ON u1.id = e.physician_id
    LEFT JOIN users u2 ON u2.id = p.user_id
    WHERE e.physician_id != p.user_id
      AND e.physician_id IN (
        SELECT id FROM users WHERE email IN ('frank.parker.lee@gmail.com', 'parker@senecawest.com', 'parker@collagendirect.health')
      )
    ORDER BY e.encounter_date DESC
    LIMIT 50
  ")->fetchAll(PDO::FETCH_ASSOC);

  if (count($crossCheck) > 0) {
    echo "⚠️  FOUND MISMATCHES: " . count($crossCheck) . " encounters where physician != patient owner\n\n";
    foreach ($crossCheck as $mismatch) {
      echo "  Encounter ID: {$mismatch['encounter_id']}\n";
      echo "    Patient: {$mismatch['first_name']} {$mismatch['last_name']} (ID: {$mismatch['patient_id']})\n";
      echo "    Patient Owner: {$mismatch['patient_owner_email']} (ID: {$mismatch['patient_owner_id']})\n";
      echo "    Billing Physician: {$mismatch['physician_email']} (ID: {$mismatch['physician_id']})\n";
      echo "    ❌ MISMATCH: Billing physician is not the patient owner!\n\n";
    }
  } else {
    echo "✓ No mismatches found - all billable encounters match patient owners\n";
  }

  echo "\n=== INVESTIGATION COMPLETE ===\n";

} catch (Exception $e) {
  echo "\n✗ Error: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
