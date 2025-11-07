<?php
/**
 * 837P Professional Claims Export
 * Generates HIPAA-compliant ASC X12 837P EDI format for billing
 */

declare(strict_types=1);
require_once __DIR__ . '/../api/db.php';
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'physician';

/**
 * Get appropriate diagnosis codes based on wound assessment and notes
 */
function getDiagnosisCodes(string $assessment, string $clinicalNote): array {
  $note = strtolower($clinicalNote);

  // Primary diagnosis - wound type based on clinical note keywords
  $primary = 'L97.929'; // Default: Non-pressure chronic ulcer

  // Diabetic wounds
  if (strpos($note, 'diabetic') !== false || strpos($note, 'diabetes') !== false) {
    if (strpos($note, 'foot') !== false || strpos($note, 'heel') !== false) {
      $primary = 'E11.621'; // Type 2 diabetes mellitus with foot ulcer
    } else if (strpos($note, 'leg') !== false) {
      $primary = 'E11.622'; // Type 2 diabetes mellitus with other skin ulcer
    } else {
      $primary = 'E11.622'; // Type 2 diabetes mellitus with other skin ulcer
    }
  }
  // Pressure ulcers
  else if (strpos($note, 'pressure') !== false || strpos($note, 'sacral') !== false || strpos($note, 'coccyx') !== false) {
    if (strpos($note, 'sacral') !== false) {
      $primary = 'L89.159'; // Pressure ulcer of sacral region
    } else if (strpos($note, 'heel') !== false) {
      $primary = 'L89.619'; // Pressure ulcer of right heel
    } else {
      $primary = 'L89.90'; // Pressure ulcer of unspecified site
    }
  }
  // Venous ulcers
  else if (strpos($note, 'venous') !== false) {
    $primary = 'I83.019'; // Varicose veins with ulcer
  }
  // Surgical/post-operative wounds
  else if (strpos($note, 'surgical') !== false || strpos($note, 'post-surgical') !== false || strpos($note, 'incision') !== false) {
    $primary = 'T81.31XA'; // Disruption of external operation wound
  }
  // Traumatic wounds
  else if (strpos($note, 'traumatic') !== false || strpos($note, 'trauma') !== false) {
    $primary = 'S91.009A'; // Unspecified open wound of foot
  }

  // Secondary diagnosis based on assessment/complications
  $secondary = null;

  if ($assessment === 'concern' || $assessment === 'urgent') {
    // Add infection code if concerning or urgent
    if (strpos($note, 'infection') !== false || strpos($note, 'infected') !== false ||
        strpos($note, 'purulent') !== false || strpos($note, 'pus') !== false) {
      $secondary = 'L03.90'; // Cellulitis, unspecified
    }
  }

  return [
    'primary' => $primary,
    'secondary' => $secondary
  ];
}

$month = $_GET['month'] ?? date('Y-m');
$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

// Get encounters with complete data for 837P export
if ($userRole === 'superadmin') {
    $sql = "
        SELECT
            e.encounter_date,
            e.id as encounter_id,
            p.id as patient_id,
            p.first_name,
            p.last_name,
            p.mrn,
            p.dob,
            p.sex,
            p.phone,
            p.email,
            p.address,
            p.city,
            p.state,
            p.zip,
            p.insurance_company,
            p.insurance_id,
            p.group_number,
            e.cpt_code,
            e.modifier,
            e.charge_amount,
            e.assessment,
            e.clinical_note,
            u.first_name as provider_first_name,
            u.last_name as provider_last_name,
            u.npi as provider_npi,
            u.credential_type as provider_credential,
            u.tax_id as provider_tax_id
        FROM billable_encounters e
        JOIN patients p ON p.id = e.patient_id
        LEFT JOIN users u ON u.id = e.physician_id
        WHERE e.encounter_date >= ? AND e.encounter_date <= ?
          AND e.exported = FALSE
        ORDER BY e.encounter_date, p.last_name, p.first_name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startDate, $endDate . ' 23:59:59']);
} else {
    $sql = "
        SELECT
            e.encounter_date,
            e.id as encounter_id,
            p.id as patient_id,
            p.first_name,
            p.last_name,
            p.mrn,
            p.dob,
            p.sex,
            p.phone,
            p.email,
            p.address,
            p.city,
            p.state,
            p.zip,
            p.insurance_company,
            p.insurance_id,
            p.group_number,
            e.cpt_code,
            e.modifier,
            e.charge_amount,
            e.assessment,
            e.clinical_note,
            u.first_name as provider_first_name,
            u.last_name as provider_last_name,
            u.npi as provider_npi,
            u.credential_type as provider_credential,
            u.tax_id as provider_tax_id
        FROM billable_encounters e
        JOIN patients p ON p.id = e.patient_id
        LEFT JOIN users u ON u.id = e.physician_id
        WHERE e.physician_id = ?
          AND e.encounter_date >= ? AND e.encounter_date <= ?
          AND e.exported = FALSE
        ORDER BY e.encounter_date, p.last_name, p.first_name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $startDate, $endDate . ' 23:59:59']);
}

$encounters = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($encounters)) {
    die('No encounters to export for the selected period.');
}

// Generate 837P EDI file
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="837P_' . $month . '_' . date('YmdHis') . '.txt"');
header('Pragma: no-cache');
header('Expires: 0');

// EDI Configuration
$interchangeControlNumber = rand(100000000, 999999999);
$groupControlNumber = rand(10000, 99999);
$transactionSetControlNumber = rand(1000, 9999);
$submitterName = 'COLLAGEDIRECT LLC';
$submitterEIN = '123456789'; // Replace with actual EIN
$receiverName = 'MEDICARE';
$receiverId = 'MEDICARE';

// Element separator: *
// Segment terminator: ~
// Sub-element separator: :

$output = '';

// ISA - Interchange Control Header
$output .= 'ISA*00*          *00*          *ZZ*' . str_pad($submitterEIN, 15) . '*ZZ*' . str_pad($receiverId, 15) . '*' . date('ymd') . '*' . date('Hi') . '*^*00501*' . str_pad((string)$interchangeControlNumber, 9, '0', STR_PAD_LEFT) . '*0*P*:~' . "\n";

// GS - Functional Group Header
$output .= 'GS*HC*' . $submitterEIN . '*' . $receiverId . '*' . date('Ymd') . '*' . date('Hi') . '*' . $groupControlNumber . '*X*005010X222A1~' . "\n";

// ST - Transaction Set Header
$output .= 'ST*837*' . str_pad((string)$transactionSetControlNumber, 4, '0', STR_PAD_LEFT) . '*005010X222A1~' . "\n";

// BHT - Beginning of Hierarchical Transaction
$output .= 'BHT*0019*00*' . $transactionSetControlNumber . '*' . date('Ymd') . '*' . date('Hi') . '*CH~' . "\n";

// Loop 1000A - Submitter Name
$output .= 'NM1*41*2*' . $submitterName . '*****46*' . $submitterEIN . '~' . "\n";
$output .= 'PER*IC*BILLING DEPT*TE*8884156880~' . "\n";

// Loop 1000B - Receiver Name
$output .= 'NM1*40*2*' . $receiverName . '*****46*' . $receiverId . '~' . "\n";

$hierarchicalIdCounter = 1;
$claimCounter = 0;

// Process each encounter
foreach ($encounters as $e) {
    $claimCounter++;

    // Get diagnosis codes
    $diagCodes = getDiagnosisCodes($e['assessment'] ?? 'stable', $e['clinical_note'] ?? '');

    // Loop 2000A - Billing Provider Hierarchical Level
    $billingHL = $hierarchicalIdCounter++;
    $output .= 'HL*' . $billingHL . '**20*1~' . "\n";

    // Loop 2010AA - Billing Provider Name
    $output .= 'NM1*85*2*' . strtoupper($submitterName) . '*****XX*' . ($e['provider_npi'] ?? '1234567890') . '~' . "\n";
    $output .= 'N3*123 MAIN STREET~' . "\n"; // Replace with actual address
    $output .= 'N4*MIAMI*FL*33101~' . "\n"; // Replace with actual city/state/zip
    $output .= 'REF*EI*' . ($e['provider_tax_id'] ?? $submitterEIN) . '~' . "\n";

    // Loop 2000B - Subscriber Hierarchical Level
    $subscriberHL = $hierarchicalIdCounter++;
    $output .= 'HL*' . $subscriberHL . '*' . $billingHL . '*22*0~' . "\n";
    $output .= 'SBR*P*18*******MC~' . "\n"; // P=Primary, MC=Medicare

    // Loop 2010BA - Subscriber Name
    $lastName = strtoupper($e['last_name'] ?? 'UNKNOWN');
    $firstName = strtoupper($e['first_name'] ?? 'UNKNOWN');
    $output .= 'NM1*IL*1*' . $lastName . '*' . $firstName . '****MI*' . ($e['insurance_id'] ?? $e['mrn']) . '~' . "\n";

    $address = strtoupper($e['address'] ?? '123 PATIENT ST');
    $city = strtoupper($e['city'] ?? 'MIAMI');
    $state = strtoupper($e['state'] ?? 'FL');
    $zip = $e['zip'] ?? '33101';

    $output .= 'N3*' . $address . '~' . "\n";
    $output .= 'N4*' . $city . '*' . $state . '*' . substr($zip, 0, 5) . '~' . "\n";
    $output .= 'DMG*D8*' . date('Ymd', strtotime($e['dob'])) . '*' . strtoupper($e['sex'] ?? 'U') . '~' . "\n";

    // Loop 2010BB - Payer Name
    $output .= 'NM1*PR*2*' . strtoupper($e['insurance_company'] ?? 'MEDICARE') . '*****PI*' . ($receiverId) . '~' . "\n";

    // Loop 2300 - Claim Information
    $claimId = 'CLM' . $claimCounter . date('ymd');
    $chargeAmount = number_format((float)($e['charge_amount'] ?? 0), 2, '.', '');

    $output .= 'CLM*' . $claimId . '*' . $chargeAmount . '***11:B:1*Y*A*Y*Y~' . "\n";
    $output .= 'DTP*472*D8*' . date('Ymd', strtotime($e['encounter_date'])) . '~' . "\n"; // Service date

    // PWK - Claim Supplemental Information (for telehealth documentation)
    $output .= 'PWK*OZ*EL~' . "\n"; // OZ=Other, EL=Electronic

    // Loop 2310B - Rendering Provider
    $providerLastName = strtoupper($e['provider_last_name'] ?? 'PROVIDER');
    $providerFirstName = strtoupper($e['provider_first_name'] ?? 'RENDERING');
    $output .= 'NM1*82*1*' . $providerLastName . '*' . $providerFirstName . '****XX*' . ($e['provider_npi'] ?? '1234567890') . '~' . "\n";

    // Loop 2400 - Service Line
    $output .= 'LX*1~' . "\n"; // Line counter

    $cptCode = $e['cpt_code'] ?? '99091';
    $modifier = $e['modifier'] ?? '';
    $serviceDate = date('Ymd', strtotime($e['encounter_date']));

    $output .= 'SV1*HC:' . $cptCode . ($modifier ? ':' . $modifier : '') . '*' . $chargeAmount . '*UN*1***1~' . "\n";
    $output .= 'DTP*472*D8*' . $serviceDate . '~' . "\n";

    // Loop 2430 - Line Adjudication Information
    // Loop 2440 - Service Line (for diagnosis pointers)

    // Diagnosis codes
    $primaryDx = $diagCodes['primary'];
    $secondaryDx = $diagCodes['secondary'] ?? null;

    // HI - Health Care Diagnosis Code
    $hiSegment = 'HI*ABK:' . $primaryDx;
    if ($secondaryDx) {
        $hiSegment .= '*ABF:' . $secondaryDx;
    }
    $output .= $hiSegment . '~' . "\n";
}

// SE - Transaction Set Trailer
$segmentCount = substr_count($output, '~');
$output .= 'SE*' . ($segmentCount + 1) . '*' . str_pad((string)$transactionSetControlNumber, 4, '0', STR_PAD_LEFT) . '~' . "\n";

// GE - Functional Group Trailer
$output .= 'GE*1*' . $groupControlNumber . '~' . "\n";

// IEA - Interchange Control Trailer
$output .= 'IEA*1*' . str_pad((string)$interchangeControlNumber, 9, '0', STR_PAD_LEFT) . '~' . "\n";

echo $output;

// Mark encounters as exported
$encounterIds = array_column($encounters, 'encounter_id');
if (!empty($encounterIds)) {
    $placeholders = implode(',', array_fill(0, count($encounterIds), '?'));
    $pdo->prepare("UPDATE billable_encounters SET exported = TRUE, exported_at = NOW() WHERE id IN ($placeholders)")
        ->execute($encounterIds);
}

exit;
