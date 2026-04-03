<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
require_once __DIR__ . '/../../src/Controllers/OTPController.php';
