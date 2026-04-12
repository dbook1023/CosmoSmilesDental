<?php
// 1. Diagnostic Shield
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define the Absolute Root once
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2)); 
}

try {
    ob_start();
    
    // 2. Load Core Controller using the solid BASE_PATH
    $corePath = BASE_PATH . "/src/Controllers/OTPController.php";
    if (!file_exists($corePath)) throw new Exception("Core OTP Controller missing.");
    
    require_once $corePath;

} catch (Throwable $e) {
    header('Content-Type: application/json');
    die(json_encode([
        'success' => false, 
        'message' => 'OTP Service Error: ' . $e->getMessage()
    ]));
}
