<?php
declare(strict_types=1);
require __DIR__ . '/lib/sg_curl.php';

$id = env('SG_TMPL_PASSWORD_RESET', '(missing)');
error_log('[test_template_reset] Using template_id='.$id);

$ok = sg_send(
  ['email'=>'parker@collagendirect.health','name'=>'You'],
  null, null, [
    'template_id' => $id,
    'dynamic_data'=> [
      'first_name'   => 'Parker',
      'reset_url'    => 'https://collagendirect.health/reset?token=test123',
      'support_email'=> 'support@collagendirect.health',
      'year'         => date('Y'),
      // Optional visual hint during testing:
      'template_hint'=> 'PASSWORD_RESET'
    ],
    'categories'  => ['auth','password']
  ]
);

header('Content-Type:text/plain');
echo $ok ? "OK (reset template accepted)" : "FAILED (see error_log)";
