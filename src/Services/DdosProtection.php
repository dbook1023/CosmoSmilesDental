<?php
// src/Services/DdosProtection.php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(__DIR__)));
}

try {
    require_once BASE_PATH . '/config/database.php';
    require_once BASE_PATH . '/config/config.php';
    require_once BASE_PATH . '/src/Services/SecurityService.php';

    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        $security = new SecurityService($db);
        if (!$security->checkGlobalRateLimit()) {
            http_response_code(429);
            die("<h1>429 Too Many Requests</h1>");
        }
    }
} catch (Exception $e) {
    error_log("DDoS Protection Error: " . $e->getMessage());
}
