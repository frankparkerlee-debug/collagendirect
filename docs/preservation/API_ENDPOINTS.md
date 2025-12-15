# CollagenDirect API Endpoints Documentation

**Generated:** 2025-12-15
**Purpose:** Complete documentation of all API endpoints for preservation before system changes.

---

## Table of Contents

1. [Authentication Endpoints](#authentication-endpoints)
2. [Portal API (Physician-facing)](#portal-api-physician-facing)
3. [Admin API (Backend)](#admin-api-backend)
4. [Patient API](#patient-api)
5. [Webhook Endpoints](#webhook-endpoints)
6. [Cron Jobs](#cron-jobs)
7. [Utility/Internal Endpoints](#utilityinternal-endpoints)

---

## Authentication Endpoints

### `POST /api/auth.php`
User authentication (login).

**Request:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "user": {
    "id": "uuid-string",
    "email": "user@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "role": "physician",
    "account_type": "referral"
  },
  "token": "session_token"
}
```

**Authentication:** None (public endpoint)

---

### `POST /api/register.php`
New user registration.

**Request:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "first_name": "John",
  "last_name": "Doe",
  "practice_name": "Acme Medical",
  "npi": "1234567890",
  "account_type": "referral"
}
```

**Response:**
```json
{
  "success": true,
  "user_id": "uuid-string",
  "message": "Registration successful"
}
```

**Authentication:** None (public endpoint)

---

### `POST /api/auth/request_reset.php`
Request password reset email.

**Request:**
```json
{
  "email": "user@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Reset email sent if account exists"
}
```

---

### `POST /api/auth/reset_password.php`
Reset password with token.

**Request:**
```json
{
  "token": "reset_token_here",
  "password": "new_password123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Password reset successful"
}
```

---

### `GET /api/logout.php`
End user session.

**Response:** Redirect to login page.

---

### `GET /api/me.php`
Get current authenticated user info.

**Response:**
```json
{
  "id": "uuid-string",
  "email": "user@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "practice_name": "Acme Medical",
  "role": "physician",
  "account_type": "referral",
  "status": "active"
}
```

**Authentication:** Required (session)

---

## Portal API (Physician-facing)

### Patients

#### `GET /api/portal/patients.php`
List patients for current user.

**Query Parameters:**
- `search` - Search by name
- `page` - Page number
- `limit` - Results per page

**Response:**
```json
{
  "patients": [...],
  "total": 100,
  "page": 1,
  "limit": 20
}
```

**Authentication:** Required (physician/practice)

---

#### `POST /api/portal/patients.php`
Create new patient.

**Request:**
```json
{
  "first_name": "Jane",
  "last_name": "Smith",
  "dob": "1970-01-15",
  "sex": "F",
  "phone": "555-123-4567",
  "insurance_provider": "Blue Cross",
  "insurance_member_id": "ABC123"
}
```

---

### Orders

#### `GET /api/portal/order.get.php`
Get single order details.

**Query Parameters:**
- `id` - Order ID (required)

**Response:**
```json
{
  "order": {
    "id": "uuid",
    "patient_id": "uuid",
    "product": "AlgiHeal 4x4",
    "status": "submitted",
    "icd10_primary": "L97.421",
    ...
  },
  "patient": {...}
}
```

---

#### `POST /api/portal/order.update.php`
Update order details.

**Request:**
```json
{
  "id": "order-uuid",
  "status": "approved",
  "wound_notes": "Updated wound assessment..."
}
```

---

#### `POST /api/portal/orders.create.php`
Create new referral order.

**Request:**
```json
{
  "patient_id": "uuid",
  "product_id": 1,
  "icd10_primary": "L97.421",
  "wound_location": "left foot",
  "wound_type": "diabetic_ulcer",
  "frequency_per_week": 3,
  "duration_days": 30
}
```

---

#### `POST /api/portal/order.submit-draft.php`
Submit a draft order for processing.

**Request:**
```json
{
  "order_id": "uuid"
}
```

---

#### `POST /api/portal/wholesale-order.create.php`
Create wholesale (practice-billed) order.

**Request:**
```json
{
  "patient_id": "uuid",
  "products": [
    {"product_id": 1, "quantity": 10},
    {"product_id": 2, "quantity": 5}
  ],
  "shipping_address": {...}
}
```

---

### AI/Scoring

#### `GET /api/portal/get_approval_score.php`
Get AI approval score for patient.

**Query Parameters:**
- `patient_id` - Patient UUID

**Response:**
```json
{
  "score": "GREEN",
  "score_numeric": 85,
  "summary": "High likelihood of approval",
  "missing_items": [],
  "complete_items": ["ID card", "Insurance card", "Clinical notes"],
  "suggestions": [...]
}
```

---

#### `POST /api/portal/generate_approval_score.php`
Generate new AI approval score.

**Request:**
```json
{
  "patient_id": "uuid",
  "include_order": true
}
```

---

#### `POST /api/portal/background_score.php`
Queue background score generation.

**Request:**
```json
{
  "patient_id": "uuid"
}
```

---

#### `POST /api/portal/generate_order_suggestions.php`
Get AI suggestions for order improvement.

**Request:**
```json
{
  "order_id": "uuid"
}
```

---

### Address/NPI Lookup

#### `GET /api/portal/address-search.php`
Search addresses (Google Places).

**Query Parameters:**
- `q` - Search query

**Response:**
```json
{
  "predictions": [
    {"description": "123 Main St, City, ST 12345", "place_id": "..."}
  ]
}
```

---

#### `GET /api/portal/address-details.php`
Get address details from place ID.

**Query Parameters:**
- `place_id` - Google place ID

---

#### `GET /api/portal/npi-search.php`
Search NPI registry.

**Query Parameters:**
- `npi` - NPI number
- `name` - Provider name

---

#### `GET /api/portal/get-physicians.php`
Get physicians list for practice admin.

**Authentication:** Required (practice_admin role)

---

### Metrics

#### `GET /api/portal/metrics.php`
Get dashboard metrics for current user.

**Response:**
```json
{
  "total_patients": 150,
  "total_orders": 45,
  "pending_orders": 12,
  "completed_orders": 33
}
```

---

## Admin API (Backend)

### Orders

#### `GET /api/admin/order.get.php`
Get order details (admin view).

**Query Parameters:**
- `id` - Order ID

---

#### `POST /api/admin/order.review.php`
Update order status/review.

**Request:**
```json
{
  "order_id": "uuid",
  "status": "approved",
  "notes": "Review notes..."
}
```

---

#### `GET /api/admin/orders/pending-review.php`
Get orders pending review.

**Response:**
```json
{
  "orders": [...],
  "count": 15
}
```

---

#### `POST /api/admin/orders/update-status.php`
Batch update order status.

**Request:**
```json
{
  "order_ids": ["uuid1", "uuid2"],
  "status": "shipped",
  "tracking_number": "1Z999AA10123456784"
}
```

---

#### `POST /api/admin/create-wholesale-order.php`
Admin-created wholesale order.

**Request:**
```json
{
  "user_id": "clinic-uuid",
  "products": [...],
  "shipping": {...}
}
```

---

### Patients

#### `GET /api/admin/patients.php`
Search all patients (admin).

**Query Parameters:**
- `search` - Search term
- `user_id` - Filter by physician

---

### AI Assistant

#### `POST /api/admin/ai_assistant.php`
AI assistant for order analysis.

**Request:**
```json
{
  "action": "analyze_order",
  "order_id": "uuid"
}
```

**Actions:**
- `analyze_order` - Get order completeness analysis
- `generate_response` - Generate message to physician
- `generate_letter` - Generate medical necessity letter
- `generate_visit_note` - Generate clinical visit note

---

#### `GET /api/admin/test_ai_key.php`
Test AI API key configuration.

---

## Patient API

### `GET /api/patient/confirm-delivery.php`
Patient delivery confirmation page.

**Query Parameters:**
- `token` - Confirmation token

---

### `POST /api/delivery-approval.php`
Submit delivery confirmation with AOB signature.

**Request:**
```json
{
  "token": "confirmation_token",
  "signature_name": "Jane Smith",
  "signature_date": "2025-12-15"
}
```

---

### `POST /api/confirm-delivery.php`
Simple delivery confirmation.

**Request:**
```json
{
  "token": "confirmation_token",
  "confirmed": true
}
```

---

## Webhook Endpoints

### Twilio SMS

#### `POST /api/twilio/delivery-confirmation-reply.php`
Handle SMS replies for delivery confirmations.

**Twilio Webhook Payload:**
```
From=+15551234567&To=+15559876543&Body=YES&MessageSid=...
```

---

#### `POST /api/twilio/receive-mms.php`
Handle MMS (photo) messages from patients.

**Twilio Webhook Payload:** Includes media URLs for wound photos.

---

## Cron Jobs

### `GET /api/cron/send-delivery-confirmations.php`
Send delivery confirmation SMS to patients.

**Schedule:** Runs daily
**Action:** Sends SMS for orders marked as delivered but not confirmed.

---

### `GET /api/cron/send-physician-status-updates.php`
Send order status emails to physicians.

**Schedule:** Runs daily
**Action:** Notifies physicians of order status changes.

---

### `GET /api/cron/send-wound-photo-prompts.php`
Send photo prompts to patients for wound monitoring.

**Schedule:** Runs weekly
**Action:** Prompts patients to submit wound photos.

---

## Utility/Internal Endpoints

### `GET /api/health.php`
Health check endpoint.

**Response:**
```json
{
  "status": "ok",
  "database": "connected",
  "timestamp": "2025-12-15T10:30:00Z"
}
```

---

### `GET /api/icd10_search.php`
Search ICD-10 codes.

**Query Parameters:**
- `q` - Search query

**Response:**
```json
{
  "results": [
    {"code": "L97.421", "description": "Non-pressure chronic ulcer of right heel and midfoot with fat layer exposed"}
  ]
}
```

---

### `GET /api/validate-npi.php`
Validate NPI number.

**Query Parameters:**
- `npi` - NPI number

**Response:**
```json
{
  "valid": true,
  "provider": {
    "name": "John Smith MD",
    "specialty": "Podiatry"
  }
}
```

---

### `POST /api/insurance-ocr.php`
Extract insurance info from card image.

**Request:** Form data with image file

**Response:**
```json
{
  "success": true,
  "extracted": {
    "member_id": "ABC123",
    "group_id": "GRP456",
    "payer_name": "Blue Cross"
  }
}
```

---

### `GET /api/query-products.php`
Query product catalog.

**Query Parameters:**
- `category` - Filter by category
- `active` - Filter active only

---

### `GET /api/preauth.php`
Preauthorization status and management.

**Query Parameters:**
- `order_id` - Get preauth for order

---

### `POST /api/billing-routes.php`
Billing operations router.

**Actions:**
- `record_payment` - Record wholesale payment
- `send_invoice` - Send invoice email
- `void_order` - Void an order

---

## Library Files (Not Endpoints)

These are internal PHP libraries, not HTTP endpoints:

| File | Purpose |
|------|---------|
| `/api/lib/ai_service.php` | Claude AI integration |
| `/api/lib/commission.php` | Commission calculation |
| `/api/lib/email_sender.php` | SMTP/SendGrid email |
| `/api/lib/twilio_sms.php` | Twilio SMS integration |
| `/api/lib/env.php` | Environment variables |
| `/api/lib/json.php` | JSON response helpers |
| `/api/lib/timezone.php` | Timezone utilities |
| `/api/lib/file_utils.php` | File upload handling |
| `/api/lib/auto_score.php` | Auto-scoring logic |

---

## Authentication Methods

### Session-based (Portal/Admin)
- PHP sessions with CSRF protection
- Session cookie: `PHPSESSID`
- CSRF token in forms and AJAX headers

### Token-based (Patient)
- Unique tokens generated for delivery confirmations
- Tokens stored in orders table
- Single-use with expiration

---

## Common Response Formats

### Success Response
```json
{
  "success": true,
  "data": {...},
  "message": "Operation completed"
}
```

### Error Response
```json
{
  "success": false,
  "error": "Error message",
  "code": "ERROR_CODE"
}
```

### Paginated Response
```json
{
  "data": [...],
  "total": 100,
  "page": 1,
  "limit": 20,
  "pages": 5
}
```

---

## Rate Limiting

Currently no explicit rate limiting implemented. Consider adding for:
- Authentication endpoints
- AI endpoints (expensive operations)
- Public-facing endpoints

---

## CORS Configuration

CORS headers set in individual endpoints as needed. No global CORS middleware.

---

*This document should be updated whenever API endpoints are added or modified.*
