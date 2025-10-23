<?php
// /admin/config.php

// OPTIONAL: Free USPS Web Tools (register and paste your UserID here to enable live USPS status lookups)
// https://www.usps.com/business/web-tools-apis/
define('USPS_USERID', getenv('USPS_USERID') ?: '');  // e.g., 'ABCD1234ABCD'

// OPTIONAL: If you later add the official carrier APIs, put creds here.
// UPS OAuth Client (Tracking API)
// define('UPS_CLIENT_ID', getenv('UPS_CLIENT_ID') ?: '');
// define('UPS_CLIENT_SECRET', getenv('UPS_CLIENT_SECRET') ?: '');

// FedEx Tracking API
// define('FEDEX_KEY', getenv('FEDEX_KEY') ?: '');
// define('FEDEX_SECRET', getenv('FEDEX_SECRET') ?: '');
