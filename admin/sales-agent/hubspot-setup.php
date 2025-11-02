<?php
/**
 * HubSpot Setup Script
 *
 * This script will:
 * 1. Test HubSpot API connection
 * 2. Create custom properties for contacts
 * 3. Set up deal pipeline with stages
 * 4. Verify everything is working
 *
 * Run this ONCE after adding HUBSPOT_API_KEY to Render environment
 */

require_once('../config.php');
require_once('hubspot-integration.php');

// Initialize HubSpot
$hubspot = new HubSpotIntegration();

echo "<h1>HubSpot Setup Script</h1>\n";
echo "<pre>\n";

// Step 1: Test API Connection
echo "=== STEP 1: Testing HubSpot API Connection ===\n";
$testContact = $hubspot->testConnection();
if ($testContact['success']) {
    echo "✅ API Connection successful!\n";
    echo "   Response: Connected to HubSpot API\n\n";
} else {
    echo "❌ API Connection failed!\n";
    echo "   Error: " . ($testContact['error'] ?? 'Unknown error') . "\n";
    echo "   HTTP Code: " . ($testContact['http_code'] ?? 'N/A') . "\n\n";
    echo "Please check:\n";
    echo "1. HUBSPOT_API_KEY is set in Render environment variables\n";
    echo "2. API key is valid and not expired\n";
    echo "3. API key has proper scopes (contacts.read, contacts.write, deals, etc.)\n\n";
    exit;
}

// Step 2: Create Custom Contact Properties
echo "=== STEP 2: Creating Custom Contact Properties ===\n";

$properties = [
    [
        'name' => 'specialty',
        'label' => 'Medical Specialty',
        'type' => 'string',
        'fieldType' => 'text',
        'groupName' => 'contactinformation',
        'description' => 'Physician medical specialty (e.g., Wound Care, Podiatry, Vascular Surgery)'
    ],
    [
        'name' => 'lead_score',
        'label' => 'Lead Score',
        'type' => 'number',
        'fieldType' => 'number',
        'groupName' => 'contactinformation',
        'description' => 'Automated lead scoring based on engagement (emails opened, links clicked, etc.)'
    ],
    [
        'name' => 'npi_number',
        'label' => 'NPI Number',
        'type' => 'string',
        'fieldType' => 'text',
        'groupName' => 'contactinformation',
        'description' => 'National Provider Identifier (NPI) number from NPI Registry'
    ],
    [
        'name' => 'estimated_monthly_volume',
        'label' => 'Estimated Monthly Volume',
        'type' => 'number',
        'fieldType' => 'number',
        'groupName' => 'contactinformation',
        'description' => 'Estimated monthly patient volume for wound care products'
    ],
    [
        'name' => 'portal_user_id',
        'label' => 'Portal User ID',
        'type' => 'string',
        'fieldType' => 'text',
        'groupName' => 'contactinformation',
        'description' => 'User ID in CollagenDirect portal after registration'
    ],
    [
        'name' => 'registration_date',
        'label' => 'Registration Date',
        'type' => 'date',
        'fieldType' => 'date',
        'groupName' => 'contactinformation',
        'description' => 'Date physician registered on CollagenDirect portal'
    ],
    [
        'name' => 'last_order_date',
        'label' => 'Last Order Date',
        'type' => 'date',
        'fieldType' => 'date',
        'groupName' => 'contactinformation',
        'description' => 'Date of most recent product order'
    ],
    [
        'name' => 'total_orders',
        'label' => 'Total Orders',
        'type' => 'number',
        'fieldType' => 'number',
        'groupName' => 'contactinformation',
        'description' => 'Total number of orders placed'
    ],
    [
        'name' => 'lifetime_value',
        'label' => 'Lifetime Value',
        'type' => 'number',
        'fieldType' => 'number',
        'groupName' => 'contactinformation',
        'description' => 'Total revenue from this physician'
    ]
];

foreach ($properties as $property) {
    echo "Creating property: {$property['label']}... ";

    $result = $hubspot->createProperty($property);

    if ($result['success']) {
        echo "✅ Created\n";
    } else {
        // Check if property already exists
        if (strpos($result['error'], 'already exists') !== false || strpos($result['error'], 'Property already exists') !== false) {
            echo "⚠️  Already exists (skipping)\n";
        } else {
            echo "❌ Failed: " . $result['error'] . "\n";
        }
    }
}

echo "\n";

// Step 3: Create Deal Pipeline
echo "=== STEP 3: Creating Deal Pipeline ===\n";

// First, check if pipeline already exists
echo "Checking for existing pipelines... ";
$pipelinesResponse = $hubspot->getPipelines();

$pipelineExists = false;
$pipelineId = null;

if ($pipelinesResponse['success'] && isset($pipelinesResponse['data']['results'])) {
    foreach ($pipelinesResponse['data']['results'] as $pipeline) {
        if ($pipeline['label'] === 'Physician Acquisition') {
            $pipelineExists = true;
            $pipelineId = $pipeline['id'];
            echo "✅ Found existing pipeline (ID: {$pipelineId})\n\n";
            break;
        }
    }
}

if (!$pipelineExists) {
    echo "Not found, creating new pipeline...\n";

    $pipelineData = [
        'label' => 'Physician Acquisition',
        'displayOrder' => 1,
        'stages' => [
            [
                'label' => 'New Lead',
                'displayOrder' => 0,
                'metadata' => [
                    'probability' => '0.05'
                ]
            ],
            [
                'label' => 'Contacted',
                'displayOrder' => 1,
                'metadata' => [
                    'probability' => '0.10'
                ]
            ],
            [
                'label' => 'Engaged (Opened Email)',
                'displayOrder' => 2,
                'metadata' => [
                    'probability' => '0.20'
                ]
            ],
            [
                'label' => 'Highly Engaged (Clicked Link)',
                'displayOrder' => 3,
                'metadata' => [
                    'probability' => '0.40'
                ]
            ],
            [
                'label' => 'Qualified (Score ≥50)',
                'displayOrder' => 4,
                'metadata' => [
                    'probability' => '0.60'
                ]
            ],
            [
                'label' => 'Registered',
                'displayOrder' => 5,
                'metadata' => [
                    'probability' => '0.80',
                    'isClosed' => 'false'
                ]
            ],
            [
                'label' => 'Active Customer',
                'displayOrder' => 6,
                'metadata' => [
                    'probability' => '1.0',
                    'isClosed' => 'true'
                ]
            ],
            [
                'label' => 'At-Risk',
                'displayOrder' => 7,
                'metadata' => [
                    'probability' => '0.50'
                ]
            ],
            [
                'label' => 'Churned',
                'displayOrder' => 8,
                'metadata' => [
                    'probability' => '0.0',
                    'isClosed' => 'true'
                ]
            ]
        ]
    ];

    $result = $hubspot->createPipeline($pipelineData);

    if ($result['success']) {
        $pipelineId = $result['data']['id'];
        echo "✅ Pipeline created successfully (ID: {$pipelineId})\n\n";
    } else {
        echo "❌ Failed to create pipeline: " . $result['error'] . "\n\n";
    }
}

// Step 4: Create a Test Contact
echo "=== STEP 4: Creating Test Contact ===\n";

$testLead = [
    'email' => 'test.physician@example.com',
    'physician_name' => 'Dr. John Test Smith',
    'practice_name' => 'Test Wound Care Clinic',
    'specialty' => 'Wound Care Specialist',
    'phone' => '555-123-4567',
    'state' => 'TX',
    'city' => 'Houston',
    'lead_score' => 25,
    'estimated_monthly_volume' => 15,
    'npi' => '1234567890'
];

echo "Creating test contact: {$testLead['physician_name']}...\n";
$contactResult = $hubspot->createOrUpdateContact($testLead);

if ($contactResult['success']) {
    $contactId = $contactResult['data']['id'];
    echo "✅ Test contact created (ID: {$contactId})\n";

    // Create a test deal for this contact
    echo "Creating test deal...\n";
    $dealResult = $hubspot->createDeal($contactId, [
        'practice_name' => $testLead['practice_name'],
        'specialty' => $testLead['specialty'],
        'estimated_monthly_volume' => $testLead['estimated_monthly_volume']
    ]);

    if ($dealResult['success']) {
        echo "✅ Test deal created (ID: {$dealResult['data']['id']})\n";
    } else {
        echo "⚠️  Deal creation failed: " . $dealResult['error'] . "\n";
    }

    // Log a test note
    echo "Logging test note to timeline...\n";
    $noteResult = $hubspot->logNote($contactId,
        "🧪 Test Note\n\nThis is a test note from the CollagenDirect Sales Agent setup script.\n\nIf you can see this, the integration is working perfectly!"
    );

    if ($noteResult['success']) {
        echo "✅ Test note logged successfully\n";
    } else {
        echo "⚠️  Note logging failed: " . $noteResult['error'] . "\n";
    }

} else {
    echo "❌ Failed to create test contact: " . $contactResult['error'] . "\n";
}

echo "\n";

// Step 5: Summary
echo "=== SETUP COMPLETE ===\n\n";
echo "✅ HubSpot integration is ready!\n\n";
echo "Next steps:\n";
echo "1. Check your HubSpot account to see the test contact: Dr. John Test Smith\n";
echo "2. Run the lead finder to import real leads: /admin/sales-agent/lead-finder-ui.php\n";
echo "3. Set up daily automation cron job:\n";
echo "   0 14 * * * cd /var/www/html/admin/sales-agent && php complete-automation.php\n";
echo "4. Configure SendGrid webhook:\n";
echo "   URL: https://collagendirect.health/admin/sales-agent/sendgrid-webhook.php\n";
echo "   Events: opened, clicked, bounced, dropped, unsubscribe, spamreport\n\n";
echo "View your HubSpot contacts: https://app.hubspot.com/contacts\n";
echo "View your deals pipeline: https://app.hubspot.com/contacts/deals\n";

echo "</pre>\n";
?>
