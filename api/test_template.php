<?php
declare(strict_types=1);
require __DIR__ . '/lib/env.php';
require __DIR__ . '/lib/sg_curl.php';

$to = ['email'=>'parker@collagendirect.health','name'=>'You'];
$id = env('SG_TMPL_PASSWORD_RESET','');
$url = 'https://collagendirect.health/portal/reset/?selector=demo&token=demo';

$ok = sg_send($to, null, null, [
  'template_id' => $id,
  'dynamic_data'=> [
    'first_name'   => 'Tester',
    'reset_url'    => $url,     // make sure your template uses href="{{{reset_url}}}"
    'support_email'=> 'support@collagendirect.health',
    'year'         => date('Y'),
  ],
  'categories'=>['auth','password']
]);

header('Content-Type:text/plain');
echo $ok ? "OK (202)" : "FAILED (see error_log)";
