<?php
// /admin/messages.php - Admin messaging with role-based access
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_admin();

$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$adminId = $admin['id'] ?? '';
$adminName = $admin['name'] ?? 'Admin';

// Determine message access based on role
$messages = [];
$canCompose = true;

// Get list of providers for compose dropdown
$providers = [];
if ($adminRole === 'superadmin' || $adminRole === 'manufacturer') {
  // Can message any provider
  $stmt = $pdo->query("SELECT id, first_name, last_name, email, practice_name FROM users WHERE role IN ('physician', 'practice_admin') ORDER BY first_name, last_name");
  $providers = $stmt->fetchAll();
} else {
  // Employees can only message their assigned physicians
  $stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email, u.practice_name
    FROM users u
    INNER JOIN admin_physicians ap ON ap.physician_user_id = u.id
    WHERE ap.admin_id = ?
    ORDER BY u.first_name, u.last_name
  ");
  $stmt->execute([$adminId]);
  $providers = $stmt->fetchAll();
}

if ($adminRole === 'superadmin') {
  // Super admin sees ALL messages
  $stmt = $pdo->query("
    SELECT m.*,
           COALESCE(p.first_name || ' ' || p.last_name, 'Unknown') as patient_name
    FROM messages m
    LEFT JOIN patients p ON p.id = m.patient_id
    ORDER BY m.created_at DESC
    LIMIT 200
  ");
  $messages = $stmt->fetchAll();
} elseif ($adminRole === 'manufacturer') {
  // Manufacturer sees all messages from physicians/practices
  $stmt = $pdo->query("
    SELECT m.*,
           COALESCE(p.first_name || ' ' || p.last_name, 'Unknown') as patient_name
    FROM messages m
    LEFT JOIN patients p ON p.id = m.patient_id
    WHERE m.sender_type = 'provider'
    ORDER BY m.created_at DESC
    LIMIT 200
  ");
  $messages = $stmt->fetchAll();
} else {
  // Employees see messages from their assigned physicians only
  $stmt = $pdo->prepare("
    SELECT m.*,
           COALESCE(p.first_name || ' ' || p.last_name, 'Unknown') as patient_name
    FROM messages m
    LEFT JOIN patients p ON p.id = m.patient_id
    INNER JOIN admin_physicians ap ON ap.physician_user_id = m.sender_id
    WHERE ap.admin_id = ? AND m.sender_type = 'provider'
    ORDER BY m.created_at DESC
    LIMIT 200
  ");
  $stmt->execute([$adminId]);
  $messages = $stmt->fetchAll();
}

// Handle message actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $action = $_POST['action'] ?? '';

  if ($action === 'mark_read') {
    $msgId = (int)($_POST['message_id'] ?? 0);
    $pdo->prepare("UPDATE messages SET is_read = TRUE, read_at = NOW() WHERE id = ?")->execute([$msgId]);
    header('Location: /admin/messages.php');
    exit;
  }

  if ($action === 'compose' || $action === 'reply') {
    $recipientId = trim($_POST['recipient_id'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $patientId = trim($_POST['patient_id'] ?? '') ?: null;

    if (!$subject || !$body) {
      $error = 'Subject and message body are required';
    } else {
      // Insert message
      $stmt = $pdo->prepare("
        INSERT INTO messages (
          sender_type, sender_id, sender_name,
          recipient_type, recipient_id,
          subject, body, patient_id,
          created_at
        ) VALUES (
          'admin', ?, ?,
          'provider', ?,
          ?, ?, ?,
          NOW()
        )
      ");
      $stmt->execute([
        $adminId,
        $adminName,
        $recipientId,
        $subject,
        $body,
        $patientId
      ]);

      header('Location: /admin/messages.php?success=1');
      exit;
    }
  }
}

$success = isset($_GET['success']);

include __DIR__ . '/_header.php';
?>

<div>
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">Messages</h1>
    <?php if ($canCompose): ?>
    <button class="btn btn-primary" onclick="showComposeDialog()">
      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
      </svg>
      Compose Message
    </button>
    <?php endif; ?>
  </div>

  <?php if ($success): ?>
  <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded">
    Message sent successfully!
  </div>
  <?php endif; ?>

  <?php if (isset($error)): ?>
  <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded">
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <div class="bg-white border rounded-lg overflow-hidden">
    <div class="grid grid-cols-12">
      <!-- Message List -->
      <div class="col-span-4 border-r max-h-screen overflow-y-auto">
        <div class="p-4 border-b bg-slate-50">
          <input type="text" placeholder="Search messages..." class="w-full border rounded px-3 py-2 text-sm" id="search-messages">
        </div>

        <div id="message-list">
          <?php if (empty($messages)): ?>
            <div class="p-8 text-center text-slate-500">
              <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="mx-auto mb-3 opacity-50">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
              </svg>
              <p class="font-medium">No messages</p>
              <p class="text-sm mt-1">Messages from providers will appear here</p>
            </div>
          <?php else: ?>
            <?php foreach ($messages as $msg): ?>
            <div class="border-b p-4 hover:bg-slate-50 cursor-pointer message-item"
                 data-message-id="<?= $msg['id'] ?>"
                 onclick="showMessage(<?= $msg['id'] ?>)">
              <div class="flex justify-between items-start mb-2">
                <div class="font-medium text-sm"><?= htmlspecialchars($msg['sender_name'] ?? 'Unknown') ?></div>
                <div class="text-xs text-slate-500"><?= date('M j', strtotime($msg['created_at'])) ?></div>
              </div>
              <div class="text-sm font-medium mb-1"><?= htmlspecialchars($msg['subject']) ?></div>
              <div class="text-xs text-slate-600 truncate"><?= htmlspecialchars(substr($msg['body'], 0, 80)) ?>...</div>
              <?php if ($msg['patient_name'] !== 'Unknown'): ?>
                <div class="text-xs text-slate-500 mt-1">Patient: <?= htmlspecialchars($msg['patient_name']) ?></div>
              <?php endif; ?>
              <?php if (!$msg['is_read']): ?>
                <span class="inline-block w-2 h-2 bg-blue-500 rounded-full mt-2"></span>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Message Detail -->
      <div class="col-span-8 p-6" id="message-detail">
        <div class="text-center text-slate-500 py-16">
          <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="mx-auto mb-4 opacity-30">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
          </svg>
          <p class="text-lg font-medium">Select a message</p>
          <p class="text-sm mt-2">Choose a message from the list to view its contents</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Compose Message Dialog -->
<dialog id="compose-dialog" class="rounded-lg shadow-lg p-0" style="max-width: 600px; width: 90%;">
  <form method="post" class="p-6">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="compose" id="compose-action">
    <input type="hidden" name="original_message_id" id="original-message-id">

    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold" id="dialog-title">Compose Message</h2>
      <button type="button" onclick="document.getElementById('compose-dialog').close()" class="text-slate-400 hover:text-slate-600">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>

    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium mb-1">To</label>
        <select name="recipient_id" id="compose-recipient" class="w-full border rounded px-3 py-2" required>
          <option value="">Select recipient</option>
          <?php foreach ($providers as $provider): ?>
            <option value="<?= $provider['id'] ?>">
              <?= htmlspecialchars($provider['first_name'] . ' ' . $provider['last_name']) ?>
              <?php if ($provider['practice_name']): ?>
                (<?= htmlspecialchars($provider['practice_name']) ?>)
              <?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Subject</label>
        <input type="text" name="subject" id="compose-subject" class="w-full border rounded px-3 py-2" required>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Message</label>
        <textarea name="body" id="compose-body" rows="8" class="w-full border rounded px-3 py-2" required></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Related Patient (Optional)</label>
        <input type="text" name="patient_id" id="compose-patient" class="w-full border rounded px-3 py-2" placeholder="Patient ID">
      </div>
    </div>

    <div class="flex justify-end gap-3 mt-6">
      <button type="button" onclick="document.getElementById('compose-dialog').close()" class="btn">Cancel</button>
      <button type="submit" class="btn btn-primary">Send Message</button>
    </div>
  </form>
</dialog>

<script>
// Store messages data for client-side display
const messagesData = <?= json_encode($messages) ?>;

function showMessage(messageId) {
  const message = messagesData.find(m => m.id == messageId);
  if (!message) return;

  const detailDiv = document.getElementById('message-detail');
  const createdDate = new Date(message.created_at).toLocaleString();

  detailDiv.innerHTML = `
    <div class="border-b pb-4 mb-4">
      <div class="flex justify-between items-start mb-3">
        <div>
          <h2 class="text-xl font-semibold">${escapeHtml(message.subject)}</h2>
          <div class="text-sm text-slate-600 mt-1">
            From: ${escapeHtml(message.sender_name || 'Unknown')} (${escapeHtml(message.sender_type)})
          </div>
          ${message.patient_name !== 'Unknown' ? `
            <div class="text-sm text-slate-600">
              Patient: ${escapeHtml(message.patient_name)}
            </div>
          ` : ''}
        </div>
        <div class="text-xs text-slate-500">${createdDate}</div>
      </div>

      <div class="flex gap-2">
        ${!message.is_read ? `
          <form method="post" class="inline">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?? '' ?>">
            <input type="hidden" name="action" value="mark_read">
            <input type="hidden" name="message_id" value="${message.id}">
            <button type="submit" class="text-xs text-blue-600 hover:underline">Mark as read</button>
          </form>
        ` : ''}
        <button onclick="showReplyDialog(${message.id})" class="btn btn-primary text-sm">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
          </svg>
          Reply
        </button>
      </div>
    </div>

    <div class="prose max-w-none">
      <div class="whitespace-pre-wrap">${escapeHtml(message.body)}</div>
    </div>
  `;
}

function showComposeDialog() {
  document.getElementById('dialog-title').textContent = 'Compose Message';
  document.getElementById('compose-action').value = 'compose';
  document.getElementById('compose-recipient').value = '';
  document.getElementById('compose-subject').value = '';
  document.getElementById('compose-body').value = '';
  document.getElementById('compose-patient').value = '';
  document.getElementById('original-message-id').value = '';
  document.getElementById('compose-dialog').showModal();
}

function showReplyDialog(messageId) {
  const message = messagesData.find(m => m.id == messageId);
  if (!message) return;

  document.getElementById('dialog-title').textContent = 'Reply to Message';
  document.getElementById('compose-action').value = 'reply';
  document.getElementById('compose-recipient').value = message.sender_id;
  document.getElementById('compose-subject').value = 'Re: ' + message.subject;
  document.getElementById('compose-body').value = '';
  document.getElementById('compose-patient').value = message.patient_id || '';
  document.getElementById('original-message-id').value = message.id;
  document.getElementById('compose-dialog').showModal();
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Search functionality
document.getElementById('search-messages')?.addEventListener('input', (e) => {
  const query = e.target.value.toLowerCase();
  document.querySelectorAll('.message-item').forEach(item => {
    const text = item.textContent.toLowerCase();
    item.style.display = text.includes(query) ? 'block' : 'none';
  });
});
</script>

<?php include __DIR__ . '/_footer.php'; ?>
