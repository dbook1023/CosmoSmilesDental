<?php
/**
 * Migration Script: Featured Testimonials
 * Adds 'is_featured' column to appointment_feedbacks table if it doesn't exist.
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        // Check if the column already exists
        $checkQuery = "SHOW COLUMNS FROM appointment_feedbacks LIKE 'is_featured'";
        $stmt = $conn->prepare($checkQuery);
        $stmt->execute();
        $columnExists = $stmt->fetch();

        if (!$columnExists) {
            echo "[*] Adding 'is_featured' column to appointment_feedbacks table...\n";
            $alterQuery = "ALTER TABLE appointment_feedbacks ADD COLUMN is_featured TINYINT(1) DEFAULT 0";
            if ($conn->exec($alterQuery) !== false) {
                echo "[+] Successfully added 'is_featured' column.\n";
            } else {
                echo "[ERROR] Failed to add 'is_featured' column.\n";
            }
        } else {
            echo "[+] 'is_featured' column already exists in appointment_feedbacks.\n";
        }
    }
} catch (PDOException $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
}
