<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

try {
    // 1. Create medical_edit_requests table
    $sql = "CREATE TABLE IF NOT EXISTS medical_edit_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id VARCHAR(20) NOT NULL,
        status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_at TIMESTAMP NULL,
        reviewed_by INT NULL,
        admin_notes TEXT,
        INDEX (client_id),
        INDEX (status)
    )";
    $pdo->exec($sql);
    echo "Table 'medical_edit_requests' created or already exists.\n";

    // 2. Add medical_history_edit_allowed to clients table if it doesn't exist
    $result = $pdo->query("SHOW COLUMNS FROM clients LIKE 'medical_history_edit_allowed'");
    if ($result->rowCount() === 0) {
        $pdo->exec("ALTER TABLE clients ADD COLUMN medical_history_edit_allowed TINYINT(1) DEFAULT 0");
        echo "Column 'medical_history_edit_allowed' added to 'clients' table.\n";
    } else {
        echo "Column 'medical_history_edit_allowed' already exists in 'clients' table.\n";
    }

    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
