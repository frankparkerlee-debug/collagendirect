<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Flexible SendGrid sender via cURL (no SDK).
 *
 * Usage:
 * 1) Dynamic Template:
 *    sg_send(
 *      ['email'=>'to@domain.com','name'=>'Name'],
 *      null, null,
 *      [
 *        'template_id' => env('SG_TMPL_PASSWORD_RESET'),
 *        'dynamic_data'=> ['first_name'=>'Dr. Lee','reset_url'=>'...'],
 *        'categories'  => ['auth','password'],
 *        // optional:
 *        // 'bcc' => [ ['email'=>'ops@domain.com','name'=>'Ops'] ],
 *        // 'reply_to' => ['email'=>'support@domain.com','name'=>'Support'],
 *        // 'headers'  => ['X-Portal'=>'CD'],
 *      ]
 *    );
 *
 * 2) Simple HTML (no template):
 *    sg_send('to@domain.com', 'Subject Here', '<strong>Hello</strong>', ['text'=>'Hello']);
 *
 * @param string|array $to        string email OR ['email'=>..,'name'=>..] OR array of those
 * @param ?string      $subject   subject (required for non-template sends)
 * @param ?string      $html      html (required for non-template sends)
 * @param array        $opts      options: template_id, dynamic_data, text, categories, headers, bcc, reply_to
 * @return bool
 */
function sg_send($to, ?string $subject, ?string $html, array $opts = []): bool {
  $apiKey = env('SENDGRID_API_KEY');
  $from   = env('SMTP_FROM', 'no-reply@collagendirect.health');
  $fromNm = env('SMTP_FROM_NAME', 'CollagenDirect');
  if (!$apiKey) { error_log('sg_send: missing SENDGRID_API_KEY'); return false; }

  // --- Normalize recipients
  $norm = static function($x) {
    if (is_string($x)) return [['email'=>$x]];
    if (isset($x['email'])) return [[ 'email'=>$x['email'], 'name'=>$x['name'] ?? $x['email'] ]];
    // assume array of recipient items
    $out = [];
    foreach ($x as $r) {
      if (is_string($r)) { $out[] = ['email'=>$r]; continue; }
      if (isset($r['email'])) { $out[] = ['email'=>$r['email'], 'name'=>$r['name'] ?? $r['email']]; }
    }
    return $out;
  };
  $toList = $norm($to);
  if (empty($toList)) { error_log('sg_send: no recipients'); return false; }

  $personalization = ['to'=>$toList];

  // Optional BCC
  if (!empty($opts['bcc'])) {
    $personalization['bcc'] = $norm($opts['bcc']);
  }
  // Optional per-message headers (SG allows limited custom headers)
  if (!empty($opts['pers_headers']) && is_array($opts['pers_headers'])) {
    $personalization['headers'] = $opts['pers_headers'];
  }
  // Optional override subject (useful even with templates if you want)
  if (!empty($opts['subject'])) {
    $personalization['subject'] = (string)$opts['subject'];
  } elseif ($subject && empty($opts['template_id'])) {
    $personalization['subject'] = $subject; // non-template path needs subject
  }

  $payload = [
    'personalizations' => [ $personalization ],
    'from' => ['email' => $from, 'name' => $fromNm],
  ];

  // Optional reply-to
  if (!empty($opts['reply_to']['email'])) {
    $payload['reply_to'] = [
      'email' => $opts['reply_to']['email'],
      'name'  => $opts['reply_to']['name'] ?? $opts['reply_to']['email'],
    ];
  }

  // Categories
  if (!empty($opts['categories']) && is_array($opts['categories'])) {
    $payload['categories'] = array_values($opts['categories']);
  }

  // Global headers
  if (!empty($opts['headers']) && is_array($opts['headers'])) {
    $payload['headers'] = $opts['headers'];
  }

  // --- Choose mode: template or simple HTML
  if (!empty($opts['template_id'])) {
    $payload['template_id'] = (string)$opts['template_id'];
    if (!empty($opts['dynamic_data']) && is_array($opts['dynamic_data'])) {
      // SendGrid expects this key name exactly:
      $payload['personalizations'][0]['dynamic_template_data'] = $opts['dynamic_data'];
    }
    // In template mode, 'content' is not required (and often ignored).
  } else {
    // Simple HTML/text email
    $text = $opts['text'] ?? strip_tags((string)$html);
    if (!$subject || !$html) {
      error_log('sg_send: subject/html required for non-template send');
      return false;
    }
    $payload['content'] = [
      ['type' => 'text/plain', 'value' => $text],
      ['type' => 'text/html',  'value' => $html],
    ];
  }

  // --- Send
  $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
  curl_setopt_array($ch, [
    CURLOPT_POST            => true,
    CURLOPT_HTTPHEADER      => [
      'Authorization: Bearer ' . $apiKey,
      'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS      => json_encode($payload),
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_TIMEOUT         => 20,
  ]);

  $resBody = curl_exec($ch);
  $status  = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $err     = curl_error($ch);
  curl_close($ch);

  if ($err) {
    error_log('SendGrid cURL error: ' . $err);
    return false;
  }

  // SendGrid returns 202 on success
  if ($status === 202) return true;

  error_log("SendGrid API non-202 ($status): " . $resBody);
  return false;
}
