<?php
/**
 * POST /public/api/auth/request_reset.php
 * Body: { "email": "name@practice.com" }
 *
 * Behavior:
 * - Always responds with {"ok":true} (prevents account enumeration).
 * - Validates email format, looks up user (by email, case-insensitive).
 * - Creates selector + verifier; stores hashed verifier (sha256).
 * - Expires in 15 minutes; single-use (invalidates older outstanding tokens).
 * - Sends SendGrid Dynamic Template (SG_TMPL_PASSWORD_RESET) with reset_url.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);

require __DIR__ . '/../db.php';          // provides $pdo
require __DIR__ . '/../lib/env.php';     // env('KEY')
require __DIR__ . '/../lib/sg_curl.php'; // sg_send(...)

if (!function_exists('json_out')) {
  function json_out(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
  }
}

// ---------- Config ----------
$ttlMinutes = 15;  // keep this in sync with email/UI copy

// ---------- Helpers ----------
function b64url(string $bin): string {
  return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}
function client_ip(): string {
  foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) return explode(',', $_SERVER[$k])[0];
  }
  return '0.0.0.0';
}

// ---------- Read input ----------
$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true) ?: [];
$email = isset($in['email']) ? trim(strtolower((string)$in['email'])) : '';

// Always generic response to caller
$GENERIC = static function (): void { json_out(200, ['ok' => true]); };

// Basic validation (don’t reveal anything)
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  error_log('[reset] invalid-email format'); // minimal breadcrumb
  $GENERIC();
}

// ---------- Ensure table exists (safe to run repeatedly) ----------
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS password_resets (
      id            INT AUTO_INCREMENT PRIMARY KEY,
      user_id       VARCHAR(64) NULL,              -- matches users.id (varchar)
      email         VARCHAR(255) NOT NULL,
      selector      VARCHAR(32)  NOT NULL,
      token_hash    VARBINARY(64) NOT NULL,        -- sha256 binary
      requested_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      expires_at    DATETIME NOT NULL,
      consumed_at   DATETIME NULL,
      ip            VARCHAR(64)  NULL,
      ua            VARCHAR(255) NULL,
      INDEX (email),
      INDEX (selector),
      INDEX (user_id),
      INDEX (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} catch (Throwable $e) {
  error_log('password_resets create failed: '.$e->getMessage());
  $GENERIC();
}

// ---------- Find user by email (case-insensitive) ----------
$user = null;
try {
  $st = $pdo->prepare("SELECT id, first_name, email FROM users WHERE LOWER(email)=?");
  $st->execute([$email]);
  $user = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  error_log('users lookup failed: '.$e->getMessage());
  // continue; we will still send to the provided email without name
}

// ---------- Simple rate limit: 5/hour per email ----------
try {
  $st = $pdo->prepare("
    SELECT COUNT(*) AS c
      FROM password_resets
     WHERE email = ?
       AND requested_at >= (NOW() - INTERVAL 1 HOUR)
  ");
  $st->execute([$email]);
  $count = (int)($st->fetch()['c'] ?? 0);
  if ($count >= 5) {
    error_log('[reset] rate-limit-skip email='.$email.' lastHourCount='.$count);
    $GENERIC();
  }
} catch (Throwable $e) {
  error_log('rate-limit check failed: '.$e->getMessage());
  // continue
}

// ---------- Create selector + verifier ----------
$selector  = bin2hex(random_bytes(8));       // 16 hex chars
$verifier  = random_bytes(32);               // 32 bytes
$tokenHash = hash('sha256', $verifier, true);
$tokenB64  = b64url($verifier);

$ip = client_ip();
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);

// ---------- Store token (and invalidate older outstanding tokens) ----------
try {
  $pdo->beginTransaction();

  $pdo->prepare("
    UPDATE password_resets
       SET consumed_at = NOW()
     WHERE email = ?
       AND consumed_at IS NULL
       AND expires_at > NOW()
  ")->execute([$email]);

  $pdo->prepare("
    INSERT INTO password_resets (user_id, email, selector, token_hash, expires_at, ip, ua)
    VALUES (?, ?, ?, ?, (NOW() + INTERVAL {$ttlMinutes} MINUTE), ?, ?)
  ")->execute([
    $user['id'] ?? null,
    $email,
    $selector,
    $tokenHash,
    $ip,
    $ua
  ]);

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('insert password_resets failed: '.$e->getMessage());
  $GENERIC();
}

// ---------- Build reset URL (points to your real page) ----------
$resetUrl = 'https://collagendirect.health/portal/reset/?selector='
          . urlencode($selector) . '&token=' . urlencode($tokenB64);

// ---------- Send via SendGrid template ----------
$templateId = env('SG_TMPL_PASSWORD_RESET','');
$sentOk = false;

try {
  if ($templateId) {
    $sentOk = sg_send(
      ['email' => $email, 'name' => $user['first_name'] ?? $email],
      null, null,
      [
        'template_id' => $templateId,
        'dynamic_data'=> [
          'first_name'    => $user['first_name'] ?? 'there',
          'reset_url'     => $resetUrl, // IMPORTANT: used in template href="{{{reset_url}}}"
          'support_email' => 'support@collagendirect.health',
          'year'          => date('Y'),
        ],
        'categories' => ['auth','password']
      ]
    );
  } else {
    // Fallback inline (only if template not configured)
    $subject = 'Reset your CollagenDirect password';
    $html    = '<p>Hi '.htmlspecialchars($user['first_name'] ?? 'there').',</p>'
             . '<p>Reset your password here: <a href="'.htmlspecialchars($resetUrl).'">Reset Password</a></p>'
             . '<p>This link expires in '.$ttlMinutes.' minutes.</p>';
    $sentOk = sg_send($email, $subject, $html, ['categories'=>['auth','password']]);
  }
} catch (Throwable $e) {
  error_log('SendGrid send failed: '.$e->getMessage());
  // still return generic
}

// ---------- Minimal breadcrumb for troubleshooting ----------
error_log('[reset] email='.$email.' sent='.( $sentOk ? 'yes' : 'no' ).' template='.($templateId ?: '(inline)').' url='.$resetUrl);

// ---------- Debug view (optional): call with ?debug=1 while testing ----------
if (!empty($_GET['debug'])) {
  json_out(200, [
    'ok'        => true,
    'debug'     => [
      'email'    => $email,
      'template' => $templateId ?: '(inline)',
      'reset_url'=> $resetUrl,
      'sent'     => $sentOk ? 'yes' : 'no',
    ]
  ]);
}

// ---------- Always respond generic success ----------
$GENERIC();
