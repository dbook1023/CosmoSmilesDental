<?php
// config/config.php

// Google reCAPTCHA v3 Keys
// Get these from: https://www.google.com/recaptcha/admin
require_once __DIR__ . '/env.php';
define('RECAPTCHA_SITE_KEY', env('RECAPTCHA_SITE_KEY', 'YOUR_SITE_KEY_HERE'));
define('RECAPTCHA_SECRET_KEY', env('RECAPTCHA_SECRET_KEY', 'YOUR_SECRET_KEY_HERE'));

// DDoS / Global Rate Limit Settings
define('GLOBAL_LIMIT_PER_MINUTE', (int) env('GLOBAL_LIMIT_PER_MINUTE', 60)); // Max 60 requests per minute per IP
