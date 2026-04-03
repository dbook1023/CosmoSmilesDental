<?php
// scripts/migrate_login_attempts.php
// Safely creates the login_attempts table if it doesn't exist.

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "CREATE TABLE IF NOT EXISTS `login_attempts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `identifier` varchar(255) NOT NULL,
        `ip_address` varchar(45) NOT NULL,
        `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
        `is_successful` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `identifier` (`identifier`),
        KEY `ip_address` (`ip_address`),
        KEY `attempt_time` (`attempt_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    $db->exec($query);
    echo "Success: login_attempts table verified/created.\n";
} catch (PDOException $e) {
    echo "Error creating login_attempts table: " . $e->getMessage() . "\n";
}
