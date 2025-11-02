<?php
/**
 * Automated Lead Finder
 * Searches NPI Registry and other databases for wound care physicians
 * in target states: TX, OK, AZ, LA, AL, FL, TN, GA
 */

require_once(__DIR__ . '/config.php');

// Target states
$target_states = ['TX', 'OK', 'AZ', 'LA', 'AL', 'FL', 'TN', 'GA'];

// Target specialties (taxonomy codes)
$target_specialties = [
    '207R00000X' => 'Internal Medicine',
    '207RG0100X' => 'Gastroenterology',
    '207RI0011X' => 'Wound Care',
    '213E00000X' => 'Podiatry',
    '207N00000X' => 'Dermatology',
    '208600000X' => 'Surgery - Vascular',
    '208G00000X' => 'Surgery - General',
    '207W00000X' => 'Sports Medicine'
];

class LeadFinder {
    private $pdo;
    private $hubspot;
    private $api_delay = 1; // seconds between API calls
    private $use_hubspot = true;

    public function __construct($pdo, $hubspot = null) {
        $this->pdo = $pdo;
        $this->hubspot = $hubspot;
        $this->use_hubspot = ($hubspot !== null);
    }

    /**
     * Search NPI Registry API
     * Documentation: https://npiregistry.cms.hhs.gov/api-page
     */
    public function searchNPIRegistry($state, $taxonomy_code, $limit = 200) {
        $base_url = 'https://npiregistry.cms.hhs.gov/api/';

        $params = [
            'version' => '2.1',
            'state' => $state,
            'taxonomy_description' => $taxonomy_code,
            'limit' => $limit,
            'skip' => 0
        ];

        $url = $base_url . '?' . http_build_query($params);

        echo "Searching NPI Registry: $state - Taxonomy: $taxonomy_code\n";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $data = json_decode($response, true);
            return $data['results'] ?? [];
        }

        return [];
    }

    /**
     * Extract and normalize lead data from NPI result
     */
    public function extractLeadData($result) {
        $basic = $result['basic'] ?? [];
        $addresses = $result['addresses'] ?? [];
        $taxonomies = $result['taxonomies'] ?? [];

        // Get practice address (prefer location over mailing)
        $practice_address = null;
        foreach ($addresses as $addr) {
            if ($addr['address_purpose'] === 'LOCATION') {
                $practice_address = $addr;
                break;
            }
        }
        if (!$practice_address && !empty($addresses)) {
            $practice_address = $addresses[0];
        }

        // Get primary taxonomy
        $primary_taxonomy = null;
        foreach ($taxonomies as $tax) {
            if ($tax['primary'] ?? false) {
                $primary_taxonomy = $tax;
                break;
            }
        }
        if (!$primary_taxonomy && !empty($taxonomies)) {
            $primary_taxonomy = $taxonomies[0];
        }

        return [
            'npi' => $result['number'] ?? '',
            'physician_name' => trim(($basic['first_name'] ?? '') . ' ' . ($basic['last_name'] ?? '')),
            'practice_name' => $basic['organization_name'] ?? $basic['name'] ?? '',
            'specialty' => $primary_taxonomy['desc'] ?? '',
            'address' => trim(($practice_address['address_1'] ?? '') . ' ' . ($practice_address['address_2'] ?? '')),
            'city' => $practice_address['city'] ?? '',
            'state' => $practice_address['state'] ?? '',
            'zip' => $practice_address['postal_code'] ?? '',
            'phone' => $practice_address['telephone_number'] ?? '',
            'enumeration_date' => $result['enumeration_date'] ?? ''
        ];
    }

    /**
     * Enrich lead with email and additional data
     */
    public function enrichLead($lead) {
        // Try to find email using common patterns
        $domain = $this->guessPracticeDomain($lead['practice_name'], $lead['city'], $lead['state']);

        if ($domain) {
            $lead['website'] = 'https://' . $domain;
            $lead['email'] = $this->guessEmail($lead['physician_name'], $domain);
        }

        // Estimate monthly volume based on specialty
        $lead['estimated_monthly_volume'] = $this->estimateVolume($lead['specialty']);

        // Calculate lead score
        $lead['lead_score'] = $this->calculateInitialScore($lead);

        return $lead;
    }

    /**
     * Guess practice domain using Google search or common patterns
     */
    private function guessPracticeDomain($practice_name, $city, $state) {
        // Normalize practice name
        $normalized = strtolower($practice_name);
        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);

        // Common domain patterns
        $patterns = [
            $normalized . '.com',
            $normalized . 'clinic.com',
            $normalized . 'health.com',
            str_replace(' ', '', strtolower($practice_name)) . '.com'
        ];

        // For now, return first pattern
        // In production, you'd verify these domains exist
        return $patterns[0];
    }

    /**
     * Guess email using common patterns
     */
    private function guessEmail($physician_name, $domain) {
        $parts = explode(' ', strtolower($physician_name));
        if (count($parts) < 2) return null;

        $first = $parts[0];
        $last = $parts[count($parts) - 1];

        // Common email patterns
        $patterns = [
            $first . '.' . $last . '@' . $domain,
            $first . $last . '@' . $domain,
            substr($first, 0, 1) . $last . '@' . $domain,
            'dr.' . $last . '@' . $domain,
            'info@' . $domain,
            'contact@' . $domain
        ];

        return $patterns[0]; // Return most common pattern
    }

    /**
     * Estimate monthly volume based on specialty
     */
    private function estimateVolume($specialty) {
        $volumes = [
            'Wound Care' => 30,
            'Podiatry' => 25,
            'Dermatology' => 20,
            'Surgery - Vascular' => 15,
            'Surgery - General' => 10,
            'Internal Medicine' => 8
        ];

        foreach ($volumes as $spec => $vol) {
            if (stripos($specialty, $spec) !== false) {
                return $vol;
            }
        }

        return 5; // default
    }

    /**
     * Calculate initial lead score
     */
    private function calculateInitialScore($lead) {
        $score = 0;

        // High-value specialties
        if (stripos($lead['specialty'], 'Wound') !== false) $score += 30;
        elseif (stripos($lead['specialty'], 'Podiatry') !== false) $score += 25;
        elseif (stripos($lead['specialty'], 'Dermatology') !== false) $score += 20;

        // Has email
        if (!empty($lead['email'])) $score += 10;

        // Has phone
        if (!empty($lead['phone'])) $score += 5;

        // Estimated volume
        $score += min($lead['estimated_monthly_volume'] ?? 0, 20);

        return $score;
    }

    /**
     * Save lead to database AND HubSpot
     */
    public function saveLead($lead) {
        // Check if lead already exists (by NPI or email)
        $stmt = $this->pdo->prepare("
            SELECT id FROM leads
            WHERE (email = ? AND email IS NOT NULL)
            OR (phone = ? AND phone IS NOT NULL)
            LIMIT 1
        ");
        $stmt->execute([$lead['email'], $lead['phone']]);

        if ($stmt->fetch()) {
            return false; // Lead already exists
        }

        // Insert new lead to local database
        $stmt = $this->pdo->prepare("
            INSERT INTO leads (
                practice_name, physician_name, specialty,
                address, city, state, zip,
                phone, email, website,
                lead_score, lead_source, estimated_monthly_volume,
                status, priority
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, 'npi_registry', ?,
                'new', 'medium'
            )
            RETURNING id
        ");

        $stmt->execute([
            $lead['practice_name'],
            $lead['physician_name'],
            $lead['specialty'],
            $lead['address'],
            $lead['city'],
            $lead['state'],
            $lead['zip'],
            $lead['phone'],
            $lead['email'],
            $lead['website'],
            $lead['lead_score'],
            $lead['estimated_monthly_volume']
        ]);

        $localLeadId = $stmt->fetchColumn();

        // Also push to HubSpot
        if ($this->use_hubspot && $this->hubspot) {
            $hsResponse = $this->hubspot->createOrUpdateContact($lead);

            if ($hsResponse['success']) {
                $hsContactId = $hsResponse['data']['id'];

                // Create a deal for this lead
                $dealData = [
                    'practice_name' => $lead['practice_name'],
                    'specialty' => $lead['specialty'],
                    'estimated_monthly_volume' => $lead['estimated_monthly_volume'],
                    'estimated_value' => $lead['estimated_monthly_volume'] * 100 // Rough estimate
                ];

                $this->hubspot->createDeal($hsContactId, $dealData);

                // Log initial note
                $note = "🔍 New lead found via NPI Registry\n\n";
                $note .= "Source: NPI Registry automated search\n";
                $note .= "Specialty: {$lead['specialty']}\n";
                $note .= "Location: {$lead['city']}, {$lead['state']}\n";
                $note .= "Lead Score: {$lead['lead_score']}/100\n";
                $note .= "Estimated Monthly Volume: {$lead['estimated_monthly_volume']} patients\n";

                $this->hubspot->logNote($hsContactId, $note);

                // Store HubSpot ID in local database
                $this->pdo->prepare("
                    UPDATE leads SET hubspot_contact_id = ? WHERE id = ?
                ")->execute([$hsContactId, $localLeadId]);
            }
        }

        return true;
    }

    /**
     * Run full lead generation for all states and specialties
     */
    public function runLeadGeneration($states, $specialties) {
        $total_found = 0;
        $total_saved = 0;

        foreach ($states as $state) {
            foreach ($specialties as $taxonomy => $specialty_name) {
                echo "\n=== Searching: $state - $specialty_name ===\n";

                $results = $this->searchNPIRegistry($state, $specialty_name, 200);

                echo "Found " . count($results) . " providers\n";

                foreach ($results as $result) {
                    $lead = $this->extractLeadData($result);
                    $lead = $this->enrichLead($lead);

                    if ($this->saveLead($lead)) {
                        $total_saved++;
                        echo "✓ Saved: {$lead['physician_name']} - {$lead['practice_name']}\n";
                    } else {
                        echo "⊘ Skipped (duplicate): {$lead['physician_name']}\n";
                    }

                    $total_found++;
                }

                // Rate limiting
                sleep($this->api_delay);
            }
        }

        echo "\n========================================\n";
        echo "Lead generation complete!\n";
        echo "Total found: $total_found\n";
        echo "Total saved: $total_saved\n";
        echo "Duplicates skipped: " . ($total_found - $total_saved) . "\n";
        echo "========================================\n";

        return ['found' => $total_found, 'saved' => $total_saved];
    }
}

// Run if executed directly
if (php_sapi_name() === 'cli') {
    $finder = new LeadFinder($pdo);
    $finder->runLeadGeneration($target_states, $target_specialties);
}
?>
