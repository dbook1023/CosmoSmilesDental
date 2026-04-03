<?php
/**
 * Migration Script: Create request_logs table
 * This ensures the DDoS protection system has the necessary table.
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception("Could not connect to the database.");
    }

    echo "[*] Checking for 'request_logs' table...\n";
    
    // Check if table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'request_logs'");
    $exists = $stmt->rowCount() > 0;

    if (!$exists) {
        echo "[+] Creating 'request_logs' table...\n";
        $sql = "CREATE TABLE `request_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ip_address` varchar(45) NOT NULL,
            `request_time` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `ip_address` (`ip_address`),
            KEY `request_time` (`request_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        
        $conn->exec($sql);
        echo "[SUCCESS] 'request_logs' table created successfully.\n";
    } else {
        echo "[INFO] 'request_logs' table already exists.\n";
    }

} catch (Exception $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
