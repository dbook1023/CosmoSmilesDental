<?php
// src/Services/DdosProtection.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/SecurityService.php';

$database = new Database();
$db = $database->getConnection();
$security = new SecurityService($db);

if (!$security->checkGlobalRateLimit()) {
    http_response_code(429);
    die("<h1>429 Too Many Requests</h1><p>Our systems have detected an unusual amount of traffic from your IP. Please try again later.</p>");
}
