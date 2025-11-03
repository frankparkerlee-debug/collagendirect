# CollagenDirect PreAuthorization Agent

## Overview

The PreAuthorization Agent automates insurance preauthorization for CollagenDirect's DME wound care products. This agent is triggered when the **manufacturer (CollagenDirect) receives an order** from a physician, and handles the entire preauth process from eligibility verification through carrier submission and status monitoring.

## Workflow

```
1. Doctor sees patient → Documents visit note
2. Doctor issues order → For wound care treatment
3. Order arrives at CollagenDirect (manufacturer) ← PREAUTH STARTS HERE
4. Manufacturer acquires preauthorization ← THIS AGENT
5. Manufacturer distributes product to patient
```

## Features

- **Automated Eligibility Checking**: Verifies patient insurance coverage before preauth submission
- **AI-Powered Documentation**: Uses Claude AI to generate medical necessity letters from physician notes
- **Multi-Carrier Support**: Configurable rules for different insurance carriers (Medicare, Medicaid, BCBS, UHC, Aetna, Cigna, Humana, etc.)
- **Multiple Submission Methods**: API, EDI, portal, fax, or manual submission
- **Status Monitoring**: Automated tracking and notifications for preauth status changes
- **HIPAA-Compliant Audit Trail**: Complete logging of all actions for compliance
- **Retry Logic**: Automatic retry for failed submissions
- **Expiration Tracking**: Monitors preauth approval expiration dates

## Architecture

### Core Components

1. **PreAuthAgent** (`api/services/PreAuthAgent.php`)
   - Main orchestration class
   - Processes orders, creates preauth requests
   - Generates medical necessity letters with AI
   - Handles submission and status updates

2. **PreAuthEligibilityChecker** (`api/services/PreAuthEligibilityChecker.php`)
   - Verifies insurance eligibility
   - Caches eligibility results
   - Supports Availity integration (optional)

3. **PreAuthCarrierIntegration** (`api/services/PreAuthCarrierIntegration.php`)
   - Handles carrier-specific submission methods
   - Supports API, EDI, portal, fax, and manual submission
   - Checks preauth status with carriers

4. **API Endpoint** (`api/preauth.php`)
   - RESTful endpoints for preauth operations
   - CSRF-protected and role-based access control

5. **Admin Dashboard** (`admin/preauth-dashboard.php`)
   - Web interface for managing preauth requests
   - Filtering, status updates, and statistics

6. **Cron Job** (`admin/cron/preauth-agent.php`)
   - Automated retry processing
   - Status checking for pending requests
   - Expiration monitoring

### Database Schema

1. **preauth_requests** - Tracks all preauth requests
2. **preauth_rules** - Carrier-specific preauth requirements
3. **preauth_audit_log** - HIPAA-compliant audit trail
4. **eligibility_cache** - Cached eligibility verification results (optional)

## Installation

### Step 1: Run Database Migrations

```bash
cd /path/to/collagendirect
psql -U your_db_user -d your_db_name -f admin/migrations/001_create_preauth_requests_table.sql
psql -U your_db_user -d your_db_name -f admin/migrations/002_create_preauth_rules_table.sql
psql -U your_db_user -d your_db_name -f admin/migrations/003_create_preauth_audit_log_table.sql
```

Or use your existing migration system.

### Step 2: Create Eligibility Cache Table (Optional)

```sql
CREATE TABLE IF NOT EXISTS eligibility_cache (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_id VARCHAR(100) NOT NULL,
    carrier_name VARCHAR(255) NOT NULL,
    eligibility_data JSONB NOT NULL,
    verified_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(member_id, carrier_name)
);

CREATE INDEX idx_eligibility_member ON eligibility_cache(member_id);
CREATE INDEX idx_eligibility_carrier ON eligibility_cache(carrier_name);
CREATE INDEX idx_eligibility_verified ON eligibility_cache(verified_at);
```

### Step 3: Configure Cron Jobs

Add to your crontab:

```bash
# PreAuth Agent - Retry failed submissions every 4 hours
0 */4 * * * php /path/to/collagendirect/admin/cron/preauth-agent.php --task=retry >> /var/log/preauth-cron.log 2>&1

# PreAuth Agent - Check pending status every 2 hours
0 */2 * * * php /path/to/collagendirect/admin/cron/preauth-agent.php --task=status >> /var/log/preauth-cron.log 2>&1

# PreAuth Agent - Check expirations daily at 3am
0 3 * * * php /path/to/collagendirect/admin/cron/preauth-agent.php --task=expiration >> /var/log/preauth-cron.log 2>&1
```

### Step 4: Set Environment Variables (Optional)

For carrier API integrations:

```bash
# Availity (EDI/Eligibility)
export AVAILITY_CLIENT_ID="your_client_id"
export AVAILITY_CLIENT_SECRET="your_client_secret"

# eFax (for fax submissions)
export EFAX_API_KEY="your_efax_api_key"

# Carrier-specific API credentials
export UHC_API_KEY="your_uhc_key"
export AETNA_API_KEY="your_aetna_key"
```

### Step 5: Make Cron Script Executable

```bash
chmod +x /path/to/collagendirect/admin/cron/preauth-agent.php
```

## Usage

### Triggering Preauth on Order Receipt

When CollagenDirect receives a new order from a physician, trigger the preauth agent:

```php
// In your order processing code
require_once __DIR__ . '/api/services/PreAuthAgent.php';

$agent = new PreAuthAgent();
$result = $agent->processOrder($orderId);

if ($result['ok']) {
    if ($result['preauth_required'] === false) {
        // Preauth not required, proceed with order
        echo "Preauth not required: " . $result['reason'];
    } else {
        // Preauth created and submitted (if auto-submit enabled)
        echo "Preauth created: " . $result['preauth_request_id'];
        echo "Status: " . $result['status'];

        if (isset($result['preauth_number'])) {
            echo "Preauth Number: " . $result['preauth_number'];
        }
    }
} else {
    // Error occurred
    error_log("Preauth failed: " . $result['error']);
}
```

### API Endpoints

#### Process Order for Preauth
```bash
POST /api/preauth.php?action=preauth.processOrder
Body: {
    "order_id": "uuid-of-order",
    "csrf_token": "token"
}
```

#### Check Preauth Status
```bash
POST /api/preauth.php?action=preauth.checkStatus
Body: {
    "preauth_request_id": "uuid",
    "csrf_token": "token"
}
```

#### Update Preauth Status Manually
```bash
POST /api/preauth.php?action=preauth.updateStatus
Body: {
    "preauth_request_id": "uuid",
    "status": "approved",
    "preauth_number": "PA123456",
    "notes": "Approved via phone call",
    "csrf_token": "token"
}
```

#### Get Preauth by Order
```bash
GET /api/preauth.php?action=preauth.getByOrder&order_id=uuid
```

#### Get Statistics
```bash
GET /api/preauth.php?action=preauth.getStats
```

### Admin Dashboard

Access the preauth dashboard at:
```
https://your-domain.com/admin/preauth-dashboard.php
```

Features:
- View all preauth requests with filtering
- Real-time statistics
- Manual status updates
- View audit logs
- Filter by status, carrier, date range

## Configuration

### Carrier Rules

The `preauth_rules` table contains configuration for each carrier. Default rules are pre-populated for major carriers, but you can customize:

```sql
-- Example: Update UnitedHealthcare rule
UPDATE preauth_rules
SET
    requires_preauth = TRUE,
    submission_method = 'api',
    api_endpoint = 'https://api.uhc.com/preauth',
    typical_turnaround_days = 2,
    auto_approval_eligible = TRUE
WHERE carrier_name = 'UnitedHealthcare'
  AND hcpcs_code = 'A6010';
```

### Auto-Submit Configuration

By default, auto-submit is enabled. To disable:

```php
// In PreAuthAgent.php constructor or via config
$this->autoSubmitEnabled = false;
```

When disabled, preauth requests are created but flagged for manual submission.

### Retry Configuration

Configure retry behavior:

```php
// In PreAuthAgent.php
private $retryMaxAttempts = 3;      // Max retry attempts
private $retryDelayHours = 24;      // Hours between retries
```

## Carrier Integration

### Supported Methods

1. **Manual** - Default, creates task for staff
2. **API** - Direct carrier API (requires credentials)
3. **EDI** - 278 transactions via clearinghouse (Availity, Change Healthcare)
4. **Portal** - Automated form submission (requires browser automation)
5. **Fax** - eFax API integration

### Adding Carrier API Integration

To add a new carrier API integration:

1. Add credentials to environment variables
2. Implement carrier-specific method in `PreAuthCarrierIntegration.php`:

```php
private function submitToNewCarrier($preauth, $rule) {
    $apiKey = getenv('NEW_CARRIER_API_KEY');

    // Build request payload
    $payload = [
        'member_id' => $preauth['member_id'],
        'hcpcs_code' => $preauth['hcpcs_code'],
        'diagnosis' => $preauth['icd10_primary'],
        // ... more fields
    ];

    // Make API call
    $ch = curl_init($rule['api_endpoint']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'preauth_number' => $data['preauth_number'],
            'response_data' => $data
        ];
    } else {
        return [
            'success' => false,
            'error_message' => 'API call failed with code ' . $httpCode
        ];
    }
}
```

3. Update the switch statement in `submitViaAPI()` method

### EDI Integration (Availity)

To enable EDI 278 transactions:

1. Sign up for Availity clearinghouse services
2. Configure credentials in environment variables
3. Enable EDI in `PreAuthCarrierIntegration.php`:

```php
private $availityEnabled = true;
```

4. Implement full EDI 278 transaction building in `buildEDI278Transaction()`

## AI Medical Necessity Letters

The agent uses Claude AI to generate professional medical necessity letters. The prompt includes:

- Patient demographics and diagnosis
- Wound measurements and clinical details
- Product information and HCPCS codes
- Physician notes

To customize the prompt, edit the `generateMedicalNecessityLetter()` method in `PreAuthAgent.php`.

## Monitoring and Alerts

### Cron Job Logs

Monitor cron job execution:

```bash
tail -f /var/log/preauth-cron.log
```

### Database Queries

Check pending preauth requests:

```sql
SELECT * FROM preauth_requests
WHERE status = 'pending'
ORDER BY created_at DESC;
```

Check failed submissions needing retry:

```sql
SELECT * FROM preauth_requests
WHERE status = 'pending'
  AND retry_count < 3
  AND next_retry_date <= NOW();
```

View audit trail for specific preauth:

```sql
SELECT * FROM preauth_audit_log
WHERE preauth_request_id = 'uuid'
ORDER BY created_at DESC;
```

### Statistics Dashboard

Access real-time statistics via API:

```bash
curl -X GET "https://your-domain.com/api/preauth.php?action=preauth.getStats" \
  -H "Cookie: PHPSESSID=your_session"
```

## Troubleshooting

### Preauth Not Auto-Submitting

Check:
1. Is `autoSubmitEnabled` set to `true`?
2. Does the carrier rule specify a valid submission method?
3. Check error logs for exceptions

### Eligibility Check Failing

- Verify patient has insurance information in database
- Check if Availity credentials are configured (if using API)
- Review eligibility cache for recent verifications

### Medical Necessity Letter Not Generating

- Verify Claude AI service is configured
- Check if order has required fields (diagnosis, wound measurements)
- Review fallback letter as backup

### Cron Jobs Not Running

- Verify cron jobs are added to crontab: `crontab -l`
- Check script is executable: `ls -l admin/cron/preauth-agent.php`
- Review cron logs: `tail -f /var/log/preauth-cron.log`
- Test manually: `php admin/cron/preauth-agent.php --task=all`

## Security

### CSRF Protection

All POST endpoints require CSRF token. Token is stored in session and validated on each request.

### Role-Based Access

Only users with roles `superadmin`, `manufacturer`, or `admin` can:
- Trigger preauth processing
- Update preauth status
- View preauth dashboard

### HIPAA Compliance

- All preauth actions are logged in audit trail
- PHI is stored securely in database with access controls
- Audit logs include actor, timestamp, and full change history

## Future Enhancements

### Phase 1 (Current)
- ✅ Core preauth agent
- ✅ AI medical necessity letters
- ✅ Manual submission workflow
- ✅ Status tracking and monitoring
- ✅ Admin dashboard

### Phase 2 (Planned)
- [ ] Availity EDI integration for eligibility and 278 transactions
- [ ] Direct carrier API integrations (UHC, Aetna, Cigna)
- [ ] Automated email notifications to patients and physicians
- [ ] PDF preauth form generation
- [ ] eFax integration for automated fax submissions

### Phase 3 (Future)
- [ ] Browser automation for carrier portals
- [ ] Machine learning for approval prediction
- [ ] Bulk preauth processing
- [ ] Real-time status webhooks from carriers
- [ ] Mobile app for status notifications

## Support

For issues or questions:
1. Check logs: `/var/log/preauth-cron.log` and PHP error logs
2. Review audit trail in database: `preauth_audit_log` table
3. Contact development team

## License

Proprietary - CollagenDirect Internal Use Only
