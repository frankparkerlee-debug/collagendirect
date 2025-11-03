<?php
/**
 * PreAuthAgent - Automated Insurance Preauthorization Agent
 *
 * WORKFLOW:
 * 1. Doctor sees patient and documents visit note
 * 2. Doctor issues order for wound care treatment
 * 3. Order arrives at CollagenDirect (manufacturer) ← PREAUTH STARTS HERE
 * 4. Manufacturer (CollagenDirect) acquires preauthorization ← THIS AGENT
 * 5. Manufacturer distributes product to patient
 *
 * This agent automates the manufacturer's preauthorization process:
 * 1. Triggered when a new order is received by CollagenDirect
 * 2. Checks if preauth is required based on carrier rules
 * 3. Verifies patient's insurance eligibility
 * 4. Generates medical necessity documentation using AI (from physician notes)
 * 5. Submits preauth requests to insurance carriers on behalf of manufacturer
 * 6. Monitors status and sends notifications to all parties
 * 7. Updates order status once preauth is approved
 *
 * @package CollagenDirect
 * @version 1.0.0
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/ClaudeService.php';
require_once __DIR__ . '/PreAuthEligibilityChecker.php';
require_once __DIR__ . '/PreAuthCarrierIntegration.php';

class PreAuthAgent {

    private $db;
    private $claudeService;
    private $eligibilityChecker;
    private $carrierIntegration;

    // Configuration
    private $autoSubmitEnabled = true;
    private $retryMaxAttempts = 3;
    private $retryDelayHours = 24;

    public function __construct() {
        $this->db = getDbConnection();
        $this->claudeService = new ClaudeService();
        $this->eligibilityChecker = new PreAuthEligibilityChecker($this->db);
        $this->carrierIntegration = new PreAuthCarrierIntegration($this->db);
    }

    /**
     * Main entry point: Process a new order for preauthorization
     *
     * TRIGGER POINT: This method should be called when CollagenDirect (manufacturer)
     * receives a new order from a physician. This is the point where the manufacturer
     * takes responsibility for acquiring insurance preauthorization before distributing
     * the product to the patient.
     *
     * @param string $orderId UUID of the order received from physician
     * @return array Result with preauth_request_id or error
     */
    public function processOrder($orderId) {
        try {
            // 1. Get order and patient details
            $order = $this->getOrderDetails($orderId);
            if (!$order) {
                return ['ok' => false, 'error' => 'Order not found'];
            }

            $patient = $this->getPatientDetails($order['patient_id']);
            if (!$patient) {
                return ['ok' => false, 'error' => 'Patient not found'];
            }

            // 2. Check if preauth is required
            $preauthRequired = $this->checkPreauthRequired(
                $patient['insurance_provider'],
                $order['hcpcs_code'],
                $order['quantity']
            );

            if (!$preauthRequired['required']) {
                $this->logAction($orderId, 'preauth_not_required', 'agent', [
                    'reason' => $preauthRequired['reason']
                ]);

                return [
                    'ok' => true,
                    'preauth_required' => false,
                    'reason' => $preauthRequired['reason']
                ];
            }

            // 3. Check insurance eligibility
            $eligibility = $this->eligibilityChecker->checkEligibility([
                'carrier_name' => $patient['insurance_provider'],
                'member_id' => $patient['insurance_member_id'],
                'patient_dob' => $patient['date_of_birth'],
                'service_date' => date('Y-m-d')
            ]);

            if (!$eligibility['eligible']) {
                $this->logAction($orderId, 'eligibility_check_failed', 'agent', [
                    'reason' => $eligibility['reason']
                ]);

                return [
                    'ok' => false,
                    'error' => 'Insurance eligibility check failed',
                    'reason' => $eligibility['reason']
                ];
            }

            // 4. Create preauth request record
            $preauthRequestId = $this->createPreauthRequest($order, $patient);

            // 5. Generate medical necessity letter using AI
            $medicalNecessityLetter = $this->generateMedicalNecessityLetter($order, $patient);
            $this->updatePreauthRequest($preauthRequestId, [
                'medical_necessity_letter' => $medicalNecessityLetter
            ]);

            // 6. Submit preauth request if auto-submit is enabled
            if ($this->autoSubmitEnabled) {
                $submissionResult = $this->submitPreauthRequest($preauthRequestId);

                return [
                    'ok' => true,
                    'preauth_request_id' => $preauthRequestId,
                    'status' => $submissionResult['status'],
                    'message' => $submissionResult['message'],
                    'preauth_number' => $submissionResult['preauth_number'] ?? null
                ];
            } else {
                return [
                    'ok' => true,
                    'preauth_request_id' => $preauthRequestId,
                    'status' => 'pending',
                    'message' => 'Preauth request created, awaiting manual submission'
                ];
            }

        } catch (Exception $e) {
            error_log("PreAuthAgent::processOrder error: " . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if preauthorization is required based on carrier rules
     */
    private function checkPreauthRequired($carrierName, $hcpcsCode, $quantity) {
        $stmt = $this->db->prepare("
            SELECT * FROM preauth_rules
            WHERE carrier_name = :carrier_name
            AND hcpcs_code = :hcpcs_code
            AND is_active = TRUE
            ORDER BY priority DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':carrier_name' => $carrierName,
            ':hcpcs_code' => $hcpcsCode
        ]);

        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rule) {
            // No specific rule found, default to requiring preauth for safety
            return [
                'required' => true,
                'reason' => 'No specific rule found, defaulting to require preauth',
                'rule' => null
            ];
        }

        // Check quantity threshold
        if ($rule['quantity_threshold'] && $quantity > $rule['quantity_threshold']) {
            return [
                'required' => true,
                'reason' => "Quantity {$quantity} exceeds threshold {$rule['quantity_threshold']}",
                'rule' => $rule
            ];
        }

        return [
            'required' => $rule['requires_preauth'],
            'reason' => $rule['requires_preauth'] ? 'Carrier requires preauth for this product' : 'Preauth not required',
            'rule' => $rule
        ];
    }

    /**
     * Create a new preauth request record
     */
    private function createPreauthRequest($order, $patient) {
        $stmt = $this->db->prepare("
            INSERT INTO preauth_requests (
                order_id,
                patient_id,
                carrier_name,
                member_id,
                group_id,
                hcpcs_code,
                product_name,
                quantity_requested,
                icd10_primary,
                icd10_secondary,
                status,
                auto_submitted,
                carrier_phone,
                physician_notes
            ) VALUES (
                :order_id,
                :patient_id,
                :carrier_name,
                :member_id,
                :group_id,
                :hcpcs_code,
                :product_name,
                :quantity_requested,
                :icd10_primary,
                :icd10_secondary,
                'pending',
                TRUE,
                :carrier_phone,
                :physician_notes
            )
            RETURNING id
        ");

        $stmt->execute([
            ':order_id' => $order['id'],
            ':patient_id' => $patient['id'],
            ':carrier_name' => $patient['insurance_provider'],
            ':member_id' => $patient['insurance_member_id'],
            ':group_id' => $patient['insurance_group_id'],
            ':hcpcs_code' => $order['hcpcs_code'],
            ':product_name' => $order['product_name'],
            ':quantity_requested' => $order['quantity'],
            ':icd10_primary' => $order['icd10_primary'],
            ':icd10_secondary' => $order['icd10_secondary'],
            ':carrier_phone' => $patient['insurance_payer_phone'],
            ':physician_notes' => $order['physician_notes']
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $preauthRequestId = $result['id'];

        // Log creation
        $this->logPreauthAction($preauthRequestId, 'created', 'agent', null, 'PreAuth Agent', true);

        return $preauthRequestId;
    }

    /**
     * Generate medical necessity letter using Claude AI
     */
    private function generateMedicalNecessityLetter($order, $patient) {
        $prompt = "Generate a professional medical necessity letter for insurance preauthorization.

Patient Information:
- Age: " . $this->calculateAge($patient['date_of_birth']) . " years old
- Primary Diagnosis (ICD-10): {$order['icd10_primary']}
" . ($order['icd10_secondary'] ? "- Secondary Diagnosis: {$order['icd10_secondary']}" : "") . "

Product Information:
- Product: {$order['product_name']}
- HCPCS Code: {$order['hcpcs_code']}
- Quantity: {$order['quantity']}

Clinical Information:
- Wound Type: {$order['wound_type']}
- Wound Size: {$order['wound_length']}cm x {$order['wound_width']}cm x {$order['wound_depth']}cm
" . ($order['physician_notes'] ? "- Physician Notes: {$order['physician_notes']}" : "") . "

Please write a compelling medical necessity letter that:
1. Explains the patient's condition and diagnosis
2. Justifies why this specific collagen wound dressing is medically necessary
3. Cites relevant clinical evidence and FDA clearance
4. Explains why alternative treatments are insufficient
5. Includes specific wound measurements and clinical details
6. Uses professional medical terminology appropriate for insurance review

The letter should be persuasive, evidence-based, and formatted professionally.";

        try {
            $response = $this->claudeService->sendPrompt($prompt);
            return $response['response'] ?? 'Medical necessity letter generation failed';
        } catch (Exception $e) {
            error_log("Failed to generate medical necessity letter: " . $e->getMessage());
            return $this->generateFallbackLetter($order, $patient);
        }
    }

    /**
     * Generate a basic fallback letter if AI fails
     */
    private function generateFallbackLetter($order, $patient) {
        $age = $this->calculateAge($patient['date_of_birth']);
        $date = date('F j, Y');

        return "To Whom It May Concern:

Date: {$date}

RE: Medical Necessity for Advanced Wound Care Product
Patient: {$patient['first_name']} {$patient['last_name']}
Member ID: {$patient['insurance_member_id']}
ICD-10 Diagnosis: {$order['icd10_primary']}
HCPCS Code: {$order['hcpcs_code']}

I am writing to request preauthorization for {$order['product_name']} for the above-referenced patient who is under my care for {$order['wound_type']}.

This {$age}-year-old patient presents with a wound measuring {$order['wound_length']}cm x {$order['wound_width']}cm x {$order['wound_depth']}cm that requires advanced wound care management. Based on clinical assessment and evidence-based wound care protocols, the requested collagen matrix dressing is medically necessary for optimal wound healing outcomes.

The FDA-cleared collagen wound dressing is indicated for this patient's condition and is expected to improve healing outcomes compared to standard dressings. The requested quantity of {$order['quantity']} units is appropriate for the wound size and expected treatment duration.

Please approve this preauthorization request to ensure the patient receives appropriate and timely wound care treatment.

Sincerely,
[Prescribing Physician]

This documentation was prepared by CollagenDirect DME Services in support of the preauthorization request.";
    }

    /**
     * Submit preauth request to carrier
     */
    private function submitPreauthRequest($preauthRequestId) {
        // Get preauth request details
        $preauth = $this->getPreauthRequestDetails($preauthRequestId);

        // Get submission method from rules
        $rule = $this->getPreauthRule($preauth['carrier_name'], $preauth['hcpcs_code']);

        $startTime = microtime(true);

        try {
            // Attempt to submit via carrier integration
            $result = $this->carrierIntegration->submitPreauth($preauth, $rule);

            $duration = round((microtime(true) - $startTime) * 1000); // milliseconds

            if ($result['success']) {
                // Update preauth status to submitted
                $this->updatePreauthRequest($preauthRequestId, [
                    'status' => 'submitted',
                    'submission_date' => date('Y-m-d H:i:s'),
                    'preauth_number' => $result['preauth_number'] ?? null,
                    'carrier_response_data' => json_encode($result['response_data'] ?? [])
                ]);

                // Log successful submission
                $this->logPreauthAction(
                    $preauthRequestId,
                    'submitted',
                    'agent',
                    null,
                    'PreAuth Agent',
                    true,
                    null,
                    [
                        'submission_method' => $result['submission_method'],
                        'external_system' => $result['external_system'],
                        'duration_ms' => $duration
                    ]
                );

                // Send notification to patient and physician
                $this->sendPreauthStatusNotification($preauthRequestId, 'submitted');

                return [
                    'ok' => true,
                    'status' => 'submitted',
                    'message' => 'Preauth request submitted successfully',
                    'preauth_number' => $result['preauth_number'] ?? null
                ];
            } else {
                // Submission failed, schedule retry
                $nextRetryDate = date('Y-m-d H:i:s', strtotime("+{$this->retryDelayHours} hours"));

                $this->updatePreauthRequest($preauthRequestId, [
                    'retry_count' => $preauth['retry_count'] + 1,
                    'last_retry_date' => date('Y-m-d H:i:s'),
                    'next_retry_date' => $nextRetryDate
                ]);

                // Log failed submission
                $this->logPreauthAction(
                    $preauthRequestId,
                    'submission_failed',
                    'agent',
                    null,
                    'PreAuth Agent',
                    false,
                    $result['error_message'],
                    ['duration_ms' => $duration]
                );

                return [
                    'ok' => false,
                    'status' => 'pending',
                    'message' => 'Submission failed, scheduled for retry',
                    'error' => $result['error_message']
                ];
            }
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);

            error_log("PreAuthAgent::submitPreauthRequest error: " . $e->getMessage());

            $this->logPreauthAction(
                $preauthRequestId,
                'submission_error',
                'agent',
                null,
                'PreAuth Agent',
                false,
                $e->getMessage(),
                ['duration_ms' => $duration]
            );

            return [
                'ok' => false,
                'status' => 'pending',
                'message' => 'Submission error occurred',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process retry queue - called by cron job
     */
    public function processRetryQueue() {
        $stmt = $this->db->prepare("
            SELECT * FROM preauth_requests
            WHERE status = 'pending'
            AND next_retry_date <= NOW()
            AND retry_count < :max_retries
            ORDER BY next_retry_date ASC
            LIMIT 10
        ");

        $stmt->execute([':max_retries' => $this->retryMaxAttempts]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($requests as $request) {
            $result = $this->submitPreauthRequest($request['id']);
            $results[] = [
                'preauth_request_id' => $request['id'],
                'result' => $result
            ];
        }

        return [
            'ok' => true,
            'processed' => count($results),
            'results' => $results
        ];
    }

    /**
     * Check status of pending preauth requests - called by cron job
     */
    public function checkPendingRequests() {
        $stmt = $this->db->query("
            SELECT * FROM preauth_requests
            WHERE status IN ('submitted', 'need_info')
            AND submission_date IS NOT NULL
            AND submission_date > NOW() - INTERVAL '30 days'
        ");

        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($requests as $request) {
            $statusCheck = $this->carrierIntegration->checkPreauthStatus(
                $request['preauth_number'],
                $request['carrier_name']
            );

            if ($statusCheck['status_changed']) {
                $this->updatePreauthRequest($request['id'], [
                    'status' => $statusCheck['new_status'],
                    'carrier_response_data' => json_encode($statusCheck['response_data'])
                ]);

                // Send notification if approved or denied
                if (in_array($statusCheck['new_status'], ['approved', 'denied'])) {
                    $this->sendPreauthStatusNotification($request['id'], $statusCheck['new_status']);
                }
            }

            $results[] = [
                'preauth_request_id' => $request['id'],
                'status' => $statusCheck['new_status'],
                'changed' => $statusCheck['status_changed']
            ];
        }

        return [
            'ok' => true,
            'checked' => count($results),
            'results' => $results
        ];
    }

    /**
     * Send preauth status notification
     */
    private function sendPreauthStatusNotification($preauthRequestId, $status) {
        // TODO: Integrate with existing SendGrid email system
        // This should send emails to patient, physician, and manufacturer
        // based on the status (submitted, approved, denied, need_info)

        $preauth = $this->getPreauthRequestDetails($preauthRequestId);
        $patient = $this->getPatientDetails($preauth['patient_id']);
        $order = $this->getOrderDetails($preauth['order_id']);

        // Log notification sent
        $this->logPreauthAction(
            $preauthRequestId,
            'email_sent',
            'system',
            null,
            'Email System',
            true,
            null,
            ['status' => $status, 'recipient' => $patient['email']]
        );
    }

    // Helper methods

    private function getOrderDetails($orderId) {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = :id");
        $stmt->execute([':id' => $orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getPatientDetails($patientId) {
        $stmt = $this->db->prepare("SELECT * FROM patients WHERE id = :id");
        $stmt->execute([':id' => $patientId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getPreauthRequestDetails($preauthRequestId) {
        $stmt = $this->db->prepare("SELECT * FROM preauth_requests WHERE id = :id");
        $stmt->execute([':id' => $preauthRequestId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getPreauthRule($carrierName, $hcpcsCode) {
        $stmt = $this->db->prepare("
            SELECT * FROM preauth_rules
            WHERE carrier_name = :carrier_name
            AND hcpcs_code = :hcpcs_code
            AND is_active = TRUE
            ORDER BY priority DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':carrier_name' => $carrierName,
            ':hcpcs_code' => $hcpcsCode
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function updatePreauthRequest($preauthRequestId, $data) {
        $fields = [];
        $params = [':id' => $preauthRequestId];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }

        $sql = "UPDATE preauth_requests SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    private function logPreauthAction($preauthRequestId, $action, $actorType, $actorId = null, $actorName = null, $success = true, $errorMessage = null, $metadata = null) {
        $stmt = $this->db->prepare("
            SELECT log_preauth_action(:preauth_request_id, :action, :actor_type, :actor_id, :actor_name, :success, :error_message, :metadata)
        ");

        $stmt->execute([
            ':preauth_request_id' => $preauthRequestId,
            ':action' => $action,
            ':actor_type' => $actorType,
            ':actor_id' => $actorId,
            ':actor_name' => $actorName,
            ':success' => $success,
            ':error_message' => $errorMessage,
            ':metadata' => $metadata ? json_encode($metadata) : null
        ]);
    }

    private function logAction($orderId, $action, $actor, $metadata = []) {
        error_log("PreAuthAgent [{$orderId}] {$action}: " . json_encode($metadata));
    }

    private function calculateAge($dateOfBirth) {
        $dob = new DateTime($dateOfBirth);
        $now = new DateTime();
        return $now->diff($dob)->y;
    }
}
