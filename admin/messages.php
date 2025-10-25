<?php
// /admin/messages.php - Admin messaging with role-based access
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_admin();

$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$adminId = $admin['id'] ?? '';

// Determine message access based on role
// Super admin: sees all messages
// Employees: sees messages from their assigned physicians only
// Manufacturer: sees all physician/practice messages

$messages = [];
$canCompose = true; // All admin users can compose messages

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

  if ($action === 'reply') {
    // TODO: Implement reply functionality
    // Would need to determine recipient based on original message sender
  }
}

include __DIR__ . '/_header.php';
?>

<div>
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">Messages</h1>
    <?php if ($canCompose): ?>
    <button class="btn btn-primary" onclick="alert('Compose functionality coming soon')">
      Compose Message
    </button>
    <?php endif; ?>
  </div>

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

      ${!message.is_read ? `
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?? '' ?>">
          <input type="hidden" name="action" value="mark_read">
          <input type="hidden" name="message_id" value="${message.id}">
          <button type="submit" class="text-xs text-blue-600 hover:underline">Mark as read</button>
        </form>
      ` : ''}
    </div>

    <div class="prose max-w-none">
      <div class="whitespace-pre-wrap">${escapeHtml(message.body)}</div>
    </div>

    <div class="mt-6 pt-6 border-t">
      <button class="btn btn-primary" onclick="alert('Reply functionality coming soon')">
        Reply
      </button>
    </div>
  `;
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
