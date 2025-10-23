<?php
// /admin/_bootstrap.php — shared error handling for admin
require_once __DIR__ . '/_safeguards.php';

@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__.'/admin_error.log');
$DEBUG = getenv('DEBUG') === '1';

set_error_handler(function($sev,$msg,$file,$line) use ($DEBUG) {
  error_log("[PHP-$sev] $msg in $file:$line");
  if ($DEBUG) {
    echo "<pre>[PHP-$sev] $msg in $file:$line</pre>";
  }
  return true; // don’t escalate notices/warnings to fatals
});

set_exception_handler(function($e) use ($DEBUG) {
  error_log("Uncaught: ".$e->getMessage()." in ".$e->getFile().":".$e->getLine());
  http_response_code(500);
  if ($DEBUG) {
    echo "<pre>Uncaught: ".$e->getMessage()."\n".$e->getFile().":".$e->getLine()."</pre>";
  } else {
    echo "Internal error. See /admin/view-log.php";
  }
});
