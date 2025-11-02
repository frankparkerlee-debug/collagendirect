<?php
/**
 * HubSpot CRM Integration
 * Syncs leads, contacts, deals, and activities to HubSpot
 */

class HubSpotIntegration {
    private $api_key;
    private $base_url = 'https://api.hubapi.com';

    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: getenv('HUBSPOT_API_KEY');
    }

    /**
     * Create or update a contact in HubSpot
     */
    public function createOrUpdateContact($lead) {
        $properties = [
            'email' => $lead['email'],
            'firstname' => $this->extractFirstName($lead['physician_name']),
            'lastname' => $this->extractLastName($lead['physician_name']),
            'phone' => $lead['phone'],
            'company' => $lead['practice_name'],
            'city' => $lead['city'],
            'state' => $lead['state'],
            'zip' => $lead['zip'],
            'address' => $lead['address'],
            'website' => $lead['website'],

            // Custom properties
            'specialty' => $lead['specialty'],
            'lead_source' => $lead['lead_source'] ?: 'NPI Registry',
            'lead_score' => $lead['lead_score'],
            'estimated_monthly_volume' => $lead['estimated_monthly_volume'],
            'npi_number' => $lead['npi'] ?? '',

            // Lifecycle stage
            'lifecyclestage' => 'lead'
        ];

        // Remove null values
        $properties = array_filter($properties, function($value) {
            return $value !== null && $value !== '';
        });

        $data = [
            'properties' => $properties
        ];

        // Try to find existing contact by email first
        $existingContact = $this->findContactByEmail($lead['email']);

        if ($existingContact) {
            // Update existing contact
            $contactId = $existingContact['id'];
            $response = $this->apiRequest("crm/v3/objects/contacts/{$contactId}", 'PATCH', $data);
        } else {
            // Create new contact
            $response = $this->apiRequest('crm/v3/objects/contacts', 'POST', $data);
        }

        return $response;
    }

    /**
     * Find contact by email
     */
    public function findContactByEmail($email) {
        if (empty($email)) return null;

        $response = $this->apiRequest("crm/v3/objects/contacts/{$email}?idProperty=email", 'GET');

        return $response['success'] ? $response['data'] : null;
    }

    /**
     * Create a deal in HubSpot
     */
    public function createDeal($contactId, $dealData) {
        $properties = [
            'dealname' => $dealData['practice_name'] . ' - Onboarding',
            'dealstage' => 'appointmentscheduled', // HubSpot default stage ID
            'pipeline' => 'default',
            'amount' => $dealData['estimated_value'] ?? 0,
            'closedate' => strtotime('+30 days') * 1000, // 30 days from now

            // Custom properties
            'deal_type' => 'New Practice',
            'specialty' => $dealData['specialty'],
            'estimated_monthly_volume' => $dealData['estimated_monthly_volume']
        ];

        $data = [
            'properties' => $properties,
            'associations' => [
                [
                    'to' => ['id' => $contactId],
                    'types' => [
                        [
                            'associationCategory' => 'HUBSPOT_DEFINED',
                            'associationTypeId' => 3 // Contact to Deal
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->apiRequest('crm/v3/objects/deals', 'POST', $data);
        return $response;
    }

    /**
     * Update deal stage
     */
    public function updateDealStage($dealId, $stage) {
        $stageMap = [
            'new' => 'appointmentscheduled',
            'contacted' => 'qualifiedtobuy',
            'engaged' => 'presentationscheduled',
            'registration_started' => 'decisionmakerboughtin',
            'registered' => 'contractsent',
            'first_referral' => 'closedwon',
            'active' => 'closedwon',
            'at_risk' => 'closedwon',
            'churned' => 'closedlost'
        ];

        $hubspotStage = $stageMap[$stage] ?? 'appointmentscheduled';

        $data = [
            'properties' => [
                'dealstage' => $hubspotStage
            ]
        ];

        return $this->apiRequest("crm/v3/objects/deals/{$dealId}", 'PATCH', $data);
    }

    /**
     * Log email activity
     */
    public function logEmail($contactId, $emailData) {
        $data = [
            'properties' => [
                'hs_timestamp' => strtotime($emailData['sent_at']) * 1000,
                'hubspot_owner_id' => $emailData['owner_id'] ?? null,
                'hs_email_subject' => $emailData['subject'],
                'hs_email_text' => strip_tags($emailData['body']),
                'hs_email_status' => 'SENT',
                'hs_email_direction' => 'EMAIL',
                'hs_email_from_email' => $emailData['from_email'] ?? 'sales@collagendirect.health',
                'hs_email_to_email' => $emailData['to_email']
            ],
            'associations' => [
                [
                    'to' => ['id' => $contactId],
                    'types' => [
                        [
                            'associationCategory' => 'HUBSPOT_DEFINED',
                            'associationTypeId' => 197 // Email to Contact
                        ]
                    ]
                ]
            ]
        ];

        return $this->apiRequest('crm/v3/objects/emails', 'POST', $data);
    }

    /**
     * Log a note/activity
     */
    public function logNote($contactId, $note, $timestamp = null) {
        $data = [
            'properties' => [
                'hs_timestamp' => ($timestamp ?: time()) * 1000,
                'hs_note_body' => $note
            ],
            'associations' => [
                [
                    'to' => ['id' => $contactId],
                    'types' => [
                        [
                            'associationCategory' => 'HUBSPOT_DEFINED',
                            'associationTypeId' => 202 // Note to Contact
                        ]
                    ]
                ]
            ]
        ];

        return $this->apiRequest('crm/v3/objects/notes', 'POST', $data);
    }

    /**
     * Log a call activity
     */
    public function logCall($contactId, $callData) {
        $data = [
            'properties' => [
                'hs_timestamp' => strtotime($callData['call_time']) * 1000,
                'hs_call_title' => $callData['title'] ?? 'Sales Call',
                'hs_call_body' => $callData['notes'],
                'hs_call_duration' => $callData['duration_seconds'] * 1000,
                'hs_call_direction' => $callData['direction'] ?? 'OUTBOUND',
                'hs_call_status' => $callData['status'] ?? 'COMPLETED',
                'hs_call_to_number' => $callData['to_number'],
                'hs_call_from_number' => $callData['from_number']
            ],
            'associations' => [
                [
                    'to' => ['id' => $contactId],
                    'types' => [
                        [
                            'associationCategory' => 'HUBSPOT_DEFINED',
                            'associationTypeId' => 194 // Call to Contact
                        ]
                    ]
                ]
            ]
        ];

        return $this->apiRequest('crm/v3/objects/calls', 'POST', $data);
    }

    /**
     * Create a task/reminder
     */
    public function createTask($contactId, $taskData) {
        $data = [
            'properties' => [
                'hs_task_subject' => $taskData['subject'],
                'hs_task_body' => $taskData['description'],
                'hs_task_status' => 'NOT_STARTED',
                'hs_task_priority' => $taskData['priority'] ?? 'MEDIUM',
                'hs_timestamp' => strtotime($taskData['due_date']) * 1000,
                'hubspot_owner_id' => $taskData['assigned_to'] ?? null
            ],
            'associations' => [
                [
                    'to' => ['id' => $contactId],
                    'types' => [
                        [
                            'associationCategory' => 'HUBSPOT_DEFINED',
                            'associationTypeId' => 204 // Task to Contact
                        ]
                    ]
                ]
            ]
        ];

        return $this->apiRequest('crm/v3/objects/tasks', 'POST', $data);
    }

    /**
     * Track registration event
     */
    public function trackRegistration($contactId, $portalData) {
        // Update contact lifecycle stage to customer
        $this->apiRequest("crm/v3/objects/contacts/{$contactId}", 'PATCH', [
            'properties' => [
                'lifecyclestage' => 'customer',
                'registration_date' => date('Y-m-d'),
                'portal_user_id' => $portalData['user_id'],
                'portal_status' => 'active'
            ]
        ]);

        // Log note about registration
        $note = "✅ Physician registered for CollagenDirect portal\n\n";
        $note .= "Portal User ID: {$portalData['user_id']}\n";
        $note .= "Registration Date: " . date('Y-m-d H:i:s') . "\n";
        $note .= "Practice: {$portalData['practice_name']}\n";

        $this->logNote($contactId, $note);

        // Create task to check for first referral
        $this->createTask($contactId, [
            'subject' => 'Follow up - Check for first referral',
            'description' => 'Physician registered 7 days ago. Check if they\'ve placed their first order. If not, reach out to assist.',
            'priority' => 'HIGH',
            'due_date' => '+7 days'
        ]);

        return true;
    }

    /**
     * Track referral/order
     */
    public function trackReferral($contactId, $orderData) {
        // Log note about the order
        $note = "🎉 New Order Placed!\n\n";
        $note .= "Order ID: {$orderData['order_id']}\n";
        $note .= "Products: {$orderData['products']}\n";
        $note .= "Total Value: \${$orderData['total_value']}\n";
        $note .= "Order Date: " . date('Y-m-d H:i:s') . "\n";

        $this->logNote($contactId, $note);

        // Update contact with last order date
        $this->apiRequest("crm/v3/objects/contacts/{$contactId}", 'PATCH', [
            'properties' => [
                'last_order_date' => date('Y-m-d'),
                'total_orders' => ($orderData['total_orders'] ?? 0),
                'lifetime_value' => ($orderData['lifetime_value'] ?? 0)
            ]
        ]);

        return true;
    }

    /**
     * Check for at-risk physicians (no orders in 30 days)
     */
    public function checkAtRiskPhysicians() {
        // Query for registered physicians with no orders in 30 days
        $filter = [
            'filterGroups' => [
                [
                    'filters' => [
                        [
                            'propertyName' => 'lifecyclestage',
                            'operator' => 'EQ',
                            'value' => 'customer'
                        ],
                        [
                            'propertyName' => 'last_order_date',
                            'operator' => 'LT',
                            'value' => strtotime('-30 days') * 1000
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->apiRequest('crm/v3/objects/contacts/search', 'POST', $filter);

        if ($response['success'] && !empty($response['data']['results'])) {
            return $response['data']['results'];
        }

        return [];
    }

    /**
     * Helper: Extract first name from full name
     */
    private function extractFirstName($fullName) {
        $parts = explode(' ', trim($fullName));
        return $parts[0] ?? '';
    }

    /**
     * Helper: Extract last name from full name
     */
    private function extractLastName($fullName) {
        $parts = explode(' ', trim($fullName));
        return count($parts) > 1 ? end($parts) : '';
    }

    /**
     * Make API request to HubSpot
     */
    private function apiRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->base_url . '/' . ltrim($endpoint, '/');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $result];
        } else {
            return [
                'success' => false,
                'error' => $result['message'] ?? 'Unknown error',
                'http_code' => $httpCode
            ];
        }
    }
}
?>
