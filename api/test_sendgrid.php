require __DIR__ . '/lib/sg_curl.php';

$ok = sg_send(
  ['email'=>'parker@collagendirect.health','Parker'=>'You'],
  null, null, [
    'template_id' => env('SG_TMPL_PASSWORD_RESET'),
    'dynamic_data'=> [
      'first_name'   => 'Parker',
      'reset_url'    => 'https://collagendirect.health/reset?token=test123',
      'support_email'=> 'support@collagendirect.health',
      'year'         => date('Y')
    ],
    'categories'  => ['auth','password']
  ]
);

echo $ok ? "OK (template 202)" : "FAILED";
