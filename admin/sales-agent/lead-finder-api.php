<?php
/**
 * Lead Finder API Endpoint
 * Accepts AJAX requests to search NPI Registry
 */

session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/lead-finder.php');

// Check authentication
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['state']) || !isset($input['specialty'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing state or specialty']);
    exit;
}

$state = $input['state'];
$specialty = $input['specialty'];

try {
    $finder = new LeadFinder($pdo);

    // Search NPI Registry
    $results = $finder->searchNPIRegistry($state, $specialty, 200);

    $found = count($results);
    $saved = 0;

    foreach ($results as $result) {
        $lead = $finder->extractLeadData($result);
        $lead = $finder->enrichLead($lead);

        if ($finder->saveLead($lead)) {
            $saved++;
        }
    }

    echo json_encode([
        'success' => true,
        'state' => $state,
        'specialty' => $specialty,
        'found' => $found,
        'saved' => $saved,
        'duplicates' => $found - $saved
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
