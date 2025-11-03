<?php
/**
 * PreAuthEligibilityChecker - Insurance Eligibility Verification Service
 *
 * Checks patient insurance eligibility before submitting preauthorization requests.
 * This ensures the patient has active coverage before the manufacturer invests time
 * in the preauth process.
 *
 * INTEGRATION OPTIONS:
 * 1. Real-time: Availity API, Change Healthcare, etc. (requires credentials)
 * 2. Manual: Store eligibility results from phone verification
 * 3. Cached: Use recent eligibility checks within validity period
 *
 * @package CollagenDirect
 * @version 1.0.0
 */

class PreAuthEligibilityChecker {

    private $db;
    private $availityEnabled = false; // Set to true when Availity credentials configured
    private $eligibilityCacheDays = 30; // Cache eligibility for 30 days

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Check insurance eligibility for a patient
     *
     * @param array $params [carrier_name, member_id, patient_dob, service_date]
     * @return array ['eligible' => bool, 'reason' => string, 'details' => array]
     */
    public function checkEligibility($params) {
        $carrierName = $params['carrier_name'] ?? null;
        $memberId = $params['member_id'] ?? null;
        $patientDob = $params['patient_dob'] ?? null;
        $serviceDate = $params['service_date'] ?? date('Y-m-d');

        // Validate required params
        if (!$carrierName || !$memberId || !$patientDob) {
            return [
                'eligible' => false,
                'reason' => 'Missing required information for eligibility check',
                'details' => []
            ];
        }

        // 1. Check cache first - has eligibility been verified recently?
        $cachedEligibility = $this->getCachedEligibility($memberId, $carrierName);
        if ($cachedEligibility) {
            return [
                'eligible' => true,
                'reason' => 'Recent eligibility verification found (cached)',
                'details' => $cachedEligibility,
                'source' => 'cache'
            ];
        }

        // 2. If Availity is enabled, perform real-time check
        if ($this->availityEnabled) {
            $realTimeCheck = $this->checkAvailityEligibility($params);
            if ($realTimeCheck['success']) {
                // Cache the result
                $this->cacheEligibility($memberId, $carrierName, $realTimeCheck['data']);

                return [
                    'eligible' => $realTimeCheck['eligible'],
                    'reason' => $realTimeCheck['reason'],
                    'details' => $realTimeCheck['data'],
                    'source' => 'availity'
                ];
            }
        }

        // 3. Fall back to optimistic eligibility
        // Since we have basic insurance info, assume eligible unless proven otherwise
        // The manufacturer can manually verify if needed
        return [
            'eligible' => true,
            'reason' => 'Insurance information provided - manual verification recommended',
            'details' => [
                'carrier_name' => $carrierName,
                'member_id' => $memberId,
                'verification_method' => 'assumed',
                'requires_manual_verification' => true
            ],
            'source' => 'assumed'
        ];
    }

    /**
     * Check cached eligibility records
     */
    private function getCachedEligibility($memberId, $carrierName) {
        $stmt = $this->db->prepare("
            SELECT
                eligibility_data,
                verified_at
            FROM eligibility_cache
            WHERE member_id = :member_id
            AND carrier_name = :carrier_name
            AND verified_at > NOW() - INTERVAL '{$this->eligibilityCacheDays} days'
            ORDER BY verified_at DESC
            LIMIT 1
        ");

        try {
            $stmt->execute([
                ':member_id' => $memberId,
                ':carrier_name' => $carrierName
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return json_decode($result['eligibility_data'], true);
            }
        } catch (PDOException $e) {
            // Table might not exist yet - that's okay
            error_log("Eligibility cache check failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Cache eligibility verification results
     */
    private function cacheEligibility($memberId, $carrierName, $eligibilityData) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO eligibility_cache (
                    member_id,
                    carrier_name,
                    eligibility_data,
                    verified_at
                ) VALUES (
                    :member_id,
                    :carrier_name,
                    :eligibility_data,
                    NOW()
                )
                ON CONFLICT (member_id, carrier_name)
                DO UPDATE SET
                    eligibility_data = EXCLUDED.eligibility_data,
                    verified_at = EXCLUDED.verified_at
            ");

            $stmt->execute([
                ':member_id' => $memberId,
                ':carrier_name' => $carrierName,
                ':eligibility_data' => json_encode($eligibilityData)
            ]);
        } catch (PDOException $e) {
            // Table might not exist yet - log and continue
            error_log("Failed to cache eligibility: " . $e->getMessage());
        }
    }

    /**
     * Check eligibility via Availity API (real-time)
     *
     * NOTE: This requires Availity credentials and configuration
     * See: https://www.availity.com/essentials/eligibility-and-benefits
     */
    private function checkAvailityEligibility($params) {
        // Placeholder for Availity integration
        // When implemented, this will make a real-time API call to Availity

        $availityClientId = getenv('AVAILITY_CLIENT_ID');
        $availityClientSecret = getenv('AVAILITY_CLIENT_SECRET');

        if (!$availityClientId || !$availityClientSecret) {
            return [
                'success' => false,
                'error' => 'Availity credentials not configured'
            ];
        }

        // TODO: Implement Availity API integration
        // 1. Authenticate with Availity OAuth
        // 2. Submit 270 eligibility inquiry
        // 3. Parse 271 eligibility response
        // 4. Return structured data

        return [
            'success' => false,
            'error' => 'Availity integration not yet implemented'
        ];
    }

    /**
     * Manually record eligibility verification
     *
     * This allows staff to record eligibility checks done via phone
     * or carrier portal, so the system knows the patient is eligible.
     *
     * @param array $params
     * @return array
     */
    public function recordManualVerification($params) {
        $memberId = $params['member_id'] ?? null;
        $carrierName = $params['carrier_name'] ?? null;
        $eligible = $params['eligible'] ?? true;
        $notes = $params['notes'] ?? '';
        $verifiedBy = $params['verified_by'] ?? null; // User ID

        if (!$memberId || !$carrierName) {
            return [
                'ok' => false,
                'error' => 'Missing required information'
            ];
        }

        $eligibilityData = [
            'eligible' => $eligible,
            'verification_method' => 'manual',
            'verified_by' => $verifiedBy,
            'notes' => $notes,
            'verified_at' => date('c')
        ];

        $this->cacheEligibility($memberId, $carrierName, $eligibilityData);

        return [
            'ok' => true,
            'message' => 'Eligibility verification recorded'
        ];
    }

    /**
     * Check if patient has DME benefits for wound care
     *
     * Some carriers have specific benefit limits for DME or wound care products.
     * This method can be extended to check benefit details.
     */
    public function checkDMEBenefits($memberId, $carrierName, $hcpcsCode) {
        // Placeholder for detailed benefit checking
        // This would integrate with carrier benefit APIs to check:
        // - Coverage for specific HCPCS code
        // - Quantity limits
        // - Frequency limits
        // - Copay/coinsurance amounts
        // - Prior authorization requirements

        return [
            'covered' => true,
            'requires_preauth' => true,
            'quantity_limit' => null,
            'frequency_limit' => null,
            'notes' => 'Detailed benefit check not implemented - assuming standard DME coverage'
        ];
    }
}
