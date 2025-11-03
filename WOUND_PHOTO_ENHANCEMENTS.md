# Wound Photo System - Enhancements Summary

## Completed Features

### ✅ 1. Request Photo Button
**Location**: Patient detail page (portal/index.php line 7327)

**How it works:**
- Button appears next to "New Order" button in patient profile
- Clicking prompts physician to optionally enter an Order ID
- If no order ID provided, creates general photo request
- Sends SMS to patient via Twilio asking for wound photo
- Validates patient has phone number before sending

**Code:**
```javascript
async function requestWoundPhoto(patientId, patientName, phone) {
  const orderId = prompt(`Request wound photo from ${patientName}?\n\nOptional: Enter Order ID...`);
  const response = await api('action=request_wound_photo', {
    patient_id: patientId,
    order_id: orderId || null
  });
}
```

### ✅ 2. Link Photos to Orders
**Database changes:**
- New migration: `admin/add-order-id-to-wound-photos.php`
- Added `order_id` column to `wound_photos` table
- Added `order_id` column to `photo_requests` table
- Photos automatically linked when patient replies to SMS

**API changes** (portal/index.php line 708):
- `request_wound_photo` endpoint now accepts `order_id` parameter
- Validates order belongs to patient
- Stores order_id in photo_request record

**Webhook changes** (api/twilio/receive-mms.php line 162):
- When photo received, looks up pending photo_request
- Retrieves order_id from request
- Links photo to that order automatically

## Remaining Features to Implement

### 🔲 2. Notification System (Red Dot Indicator)

**Requirements:**
- Red dot appears next to patient's "View/Edit" button when new photo arrives
- Clicking patient clears the notification
- Track which photos physician has seen

**Implementation Plan:**

#### A. Database Changes
Create new table to track photo notifications:

```sql
CREATE TABLE photo_notifications (
  id VARCHAR(64) PRIMARY KEY,
  wound_photo_id VARCHAR(64) REFERENCES wound_photos(id) ON DELETE CASCADE,
  physician_id VARCHAR(64) REFERENCES users(id) ON DELETE CASCADE,
  patient_id VARCHAR(64) REFERENCES patients(id) ON DELETE CASCADE,
  seen BOOLEAN DEFAULT FALSE,
  seen_at TIMESTAMP,
  created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_photo_notifications_physician ON photo_notifications(physician_id, seen);
CREATE INDEX idx_photo_notifications_patient ON photo_notifications(patient_id);
```

#### B. Trigger Notification When Photo Received
In `api/twilio/receive-mms.php`, after saving photo:

```php
// Create notification for physician
$notificationId = bin2hex(random_bytes(16));
$stmt = $pdo->prepare("
  INSERT INTO photo_notifications (id, wound_photo_id, physician_id, patient_id)
  VALUES (?, ?, ?, ?)
");
$stmt->execute([$notificationId, $photoId, $patient['user_id'], $patient['id']]);
```

#### C. Add API Endpoint to Get Notification Counts
In `portal/index.php`:

```php
if ($action==='get_photo_notification_counts'){
  // Get count of unseen photos per patient for this physician
  $stmt = $pdo->prepare("
    SELECT patient_id, COUNT(*) as count
    FROM photo_notifications
    WHERE physician_id = ? AND seen = FALSE
    GROUP BY patient_id
  ");
  $stmt->execute([$userId]);
  $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

  jok(['counts' => $counts]);
}
```

#### D. Mark Notifications as Seen
When physician clicks on patient:

```php
if ($action==='mark_photo_notifications_seen'){
  $patientId = $_POST['patient_id'] ?? '';
  $pdo->prepare("
    UPDATE photo_notifications
    SET seen = TRUE, seen_at = NOW()
    WHERE physician_id = ? AND patient_id = ? AND seen = FALSE
  ")->execute([$userId, $patientId]);

  jok(['message' => 'Notifications marked as seen']);
}
```

#### E. Update Patient List UI
In the patients table/list, add red dot badge:

```html
<td>
  <a href="?page=patient-detail&id=${patient.id}" onclick="markPhotosSeen('${patient.id}')">
    View/Edit
    ${photoNotificationCounts[patient.id] > 0 ? `
      <span class="notification-badge">${photoNotificationCounts[patient.id]}</span>
    ` : ''}
  </a>
</td>
```

CSS:
```css
.notification-badge {
  display: inline-block;
  background: #ef4444;
  color: white;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  font-size: 11px;
  line-height: 20px;
  text-align: center;
  margin-left: 6px;
}
```

### 🔲 3. Display Photos in Patient Profile

**Requirements:**
- Show wound photos within patient detail page
- Group photos by order (treatment plan)
- Show unlinked photos separately
- Display upload date, review status, billing status

**Implementation Plan:**

#### A. Add Photos Section to Patient Detail
In `portal/index.php`, function `renderPatientDetailPage()`, add new section in the right column:

```javascript
const photosSection = `
  <div class="card p-6 mb-6">
    <h3 class="text-lg font-bold mb-4">Wound Photos</h3>
    ${renderWoundPhotos(p.id, orders)}
  </div>
`;
```

#### B. Create Photo Rendering Function

```javascript
function renderWoundPhotos(patientId, orders) {
  // Fetch photos grouped by order
  const photos = await api(`action=get_patient_photos&patient_id=${patientId}`);

  let html = '';

  // Group photos by order
  const photosByOrder = {};
  const unlinkedPhotos = [];

  photos.forEach(photo => {
    if (photo.order_id) {
      if (!photosByOrder[photo.order_id]) {
        photosByOrder[photo.order_id] = [];
      }
      photosByOrder[photo.order_id].push(photo);
    } else {
      unlinkedPhotos.push(photo);
    }
  });

  // Render photos grouped by order
  orders.forEach(order => {
    const orderPhotos = photosByOrder[order.id] || [];
    if (orderPhotos.length > 0) {
      html += `
        <div class="mb-6 pb-4 border-b">
          <h4 class="font-semibold text-sm mb-2">
            Order #${order.id.substring(0, 8)} - ${order.product_name}
          </h4>
          <div class="grid grid-cols-3 gap-2">
            ${orderPhotos.map(photo => `
              <div class="relative">
                <img src="${photo.photo_path}"
                     class="w-full h-24 object-cover rounded cursor-pointer"
                     onclick="viewPhotoDetail('${photo.id}')">
                <div class="text-xs mt-1">
                  ${formatDate(photo.uploaded_at)}
                  ${photo.reviewed ? '✓ Reviewed' : '⏳ Pending'}
                </div>
              </div>
            `).join('')}
          </div>
        </div>
      `;
    }
  });

  // Render unlinked photos
  if (unlinkedPhotos.length > 0) {
    html += `
      <div class="mb-4">
        <h4 class="font-semibold text-sm mb-2">General Photos</h4>
        <div class="grid grid-cols-3 gap-2">
          ${unlinkedPhotos.map(photo => `
            <div class="relative">
              <img src="${photo.photo_path}"
                   class="w-full h-24 object-cover rounded cursor-pointer"
                   onclick="viewPhotoDetail('${photo.id}')">
              <div class="text-xs mt-1">
                ${formatDate(photo.uploaded_at)}
              </div>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  return html || '<p class="text-sm text-slate-500">No photos uploaded yet</p>';
}
```

#### C. Add API Endpoint to Get Patient Photos

```php
if ($action==='get_patient_photos'){
  $patientId = $_GET['patient_id'] ?? '';

  // Verify access
  $stmt = $pdo->prepare("
    SELECT wp.*, o.product_name
    FROM wound_photos wp
    LEFT JOIN orders o ON o.id = wp.order_id
    WHERE wp.patient_id = ?
    ORDER BY wp.uploaded_at DESC
  ");
  $stmt->execute([$patientId]);
  $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

  jok(['photos' => $photos]);
}
```

### 🔲 4. Automated Photo Request Scheduler

**Requirements:**
- Automatically send photo requests based on treatment frequency
- Example: If treatment is 4x/week for 45 days, send photo request 4x/week
- Track which requests have been sent
- Don't duplicate requests if one is already pending

**Implementation Plan:**

#### A. Extract Treatment Schedule from Orders
When order is created, parse the frequency:

```php
// In orders table, add columns:
ALTER TABLE orders ADD COLUMN photo_frequency VARCHAR(50); -- '4x_weekly', 'daily', '3x_weekly'
ALTER TABLE orders ADD COLUMN photo_duration_days INT; -- 45, 60, 90
ALTER TABLE orders ADD COLUMN photo_schedule_start DATE;
ALTER TABLE orders ADD COLUMN photo_schedule_end DATE;
ALTER TABLE orders ADD COLUMN last_photo_request_sent DATE;
```

#### B. Create Cron Job / Scheduler
Create `admin/send-scheduled-photo-requests.php`:

```php
<?php
/**
 * Automated Photo Request Scheduler
 * Run via cron: 0 9 * * * php /var/www/html/admin/send-scheduled-photo-requests.php
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/lib/twilio_helper.php';

$today = date('Y-m-d');

// Find orders that need photo requests sent today
$stmt = $pdo->query("
  SELECT o.*, p.id as patient_id, p.first_name, p.phone, p.user_id as physician_id
  FROM orders o
  JOIN patients p ON p.id = o.patient_id
  WHERE o.photo_frequency IS NOT NULL
    AND o.photo_schedule_start <= '$today'
    AND o.photo_schedule_end >= '$today'
    AND (
      o.last_photo_request_sent IS NULL
      OR o.last_photo_request_sent < '$today'
    )
");

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($orders as $order) {
  // Check if should send today based on frequency
  if (!shouldSendPhotoRequestToday($order)) {
    continue;
  }

  // Check if patient already has pending photo request
  $pending = $pdo->prepare("
    SELECT id FROM photo_requests
    WHERE patient_id = ? AND order_id = ? AND completed = FALSE
  ");
  $pending->execute([$order['patient_id'], $order['id']]);

  if ($pending->fetch()) {
    echo "Skipping {$order['patient_id']} - pending request exists\n";
    continue;
  }

  // Create photo request
  $requestId = bin2hex(random_bytes(16));
  $uploadToken = bin2hex(random_bytes(32));

  $pdo->prepare("
    INSERT INTO photo_requests
    (id, patient_id, physician_id, order_id, wound_location, upload_token, token_expires_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ")->execute([
    $requestId,
    $order['patient_id'],
    $order['physician_id'],
    $order['id'],
    'wound',
    $uploadToken,
    date('Y-m-d H:i:s', strtotime('+7 days'))
  ]);

  // Send SMS
  $twilioHelper = new TwilioHelper();
  $result = $twilioHelper->sendPhotoRequest(
    $order['phone'],
    $order['first_name'],
    $uploadToken
  );

  if ($result['success']) {
    // Update order
    $pdo->prepare("
      UPDATE orders
      SET last_photo_request_sent = ?
      WHERE id = ?
    ")->execute([$today, $order['id']]);

    echo "✓ Sent photo request to {$order['first_name']} for order {$order['id']}\n";
  } else {
    echo "✗ Failed to send to {$order['first_name']}: {$result['error']}\n";
  }
}

function shouldSendPhotoRequestToday($order) {
  $frequency = $order['photo_frequency'];
  $lastSent = $order['last_photo_request_sent'];

  if (!$lastSent) {
    return true; // Never sent before
  }

  $daysSinceLastSent = (strtotime('today') - strtotime($lastSent)) / 86400;

  switch ($frequency) {
    case 'daily':
      return $daysSinceLastSent >= 1;
    case '4x_weekly':
      // Send Mon, Tue, Thu, Fri (skip Wed, Sat, Sun)
      $dow = date('N'); // 1=Mon, 7=Sun
      return in_array($dow, [1, 2, 4, 5]) && $daysSinceLastSent >= 1;
    case '3x_weekly':
      // Send Mon, Wed, Fri
      $dow = date('N');
      return in_array($dow, [1, 3, 5]) && $daysSinceLastSent >= 1;
    case '2x_weekly':
      // Send Tue, Fri
      $dow = date('N');
      return in_array($dow, [2, 5]) && $daysSinceLastSent >= 1;
    default:
      return false;
  }
}
```

#### C. Set Up Cron Job
On the server, add to crontab:

```bash
# Send automated wound photo requests every day at 9 AM
0 9 * * * php /var/www/html/admin/send-scheduled-photo-requests.php >> /var/log/photo-requests.log 2>&1
```

#### D. Add UI to Configure Photo Schedule
When creating/editing an order, add fields:

```html
<div>
  <label>Automated Photo Requests</label>
  <select name="photo_frequency">
    <option value="">None</option>
    <option value="daily">Daily</option>
    <option value="4x_weekly">4x per week (Mon/Tue/Thu/Fri)</option>
    <option value="3x_weekly">3x per week (Mon/Wed/Fri)</option>
    <option value="2x_weekly">2x per week (Tue/Fri)</option>
  </select>
</div>

<div>
  <label>Duration (days)</label>
  <input type="number" name="photo_duration_days" value="45">
</div>
```

## Implementation Priority

1. **Highest Priority**: Display photos in patient profile (#3)
   - Most immediate value for physicians
   - Essential for tracking treatment progress
   - Completes the photo workflow loop

2. **High Priority**: Notification system (#2)
   - Improves physician awareness of new photos
   - Reduces time to review
   - Better patient experience

3. **Medium Priority**: Automated scheduler (#4)
   - Reduces manual work
   - Ensures consistent photo collection
   - Requires more setup (cron job)

## Testing Checklist

### Request Photo Button
- [ ] Button appears on patient detail page
- [ ] Button hidden when no phone number
- [ ] Prompt asks for order ID
- [ ] SMS sent successfully with order ID
- [ ] SMS sent successfully without order ID
- [ ] Photo request saved to database

### Order Linking
- [ ] Photo linked to order when order ID provided
- [ ] Photo saved without order_id when not provided
- [ ] Patient can reply to SMS and photo is linked correctly
- [ ] Multiple photos can be linked to same order
- [ ] Photos for different orders kept separate

### Notification System (when implemented)
- [ ] Red dot appears when new photo arrives
- [ ] Clicking patient clears notification
- [ ] Multiple notifications shown correctly
- [ ] Notifications only visible to patient's physician

### Photo Display (when implemented)
- [ ] Photos grouped by order correctly
- [ ] Unlinked photos shown separately
- [ ] Clicking photo opens detail view
- [ ] Review status shown correctly
- [ ] Billing status shown correctly

### Automated Scheduler (when implemented)
- [ ] Cron job runs successfully
- [ ] Photos requested on correct days
- [ ] Duplicate requests prevented
- [ ] Schedule respects treatment end date
- [ ] Notifications sent successfully
