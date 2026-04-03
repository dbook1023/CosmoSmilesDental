<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // 1. Create medical_edit_requests table
    $sql1 = "CREATE TABLE IF NOT EXISTS medical_edit_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id VARCHAR(50) NOT NULL,
        status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        approved_by INT NULL,
        approved_at TIMESTAMP NULL,
        notes TEXT,
        FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
    )";
    $db->exec($sql1);
    echo "Table 'medical_edit_requests' created or already exists.\n";

    // 2. Add medical_history_edit_allowed column to clients table
    $checkSql = "SHOW COLUMNS FROM clients LIKE 'medical_history_edit_allowed'";
    $stmt = $db->query($checkSql);
    if ($stmt->rowCount() == 0) {
        $sql2 = "ALTER TABLE clients ADD COLUMN medical_history_edit_allowed TINYINT(1) DEFAULT 0 AFTER medical_history_status";
        $db->exec($sql2);
        echo "Column 'medical_history_edit_allowed' added to 'clients' table.\n";
    } else {
        echo "Column 'medical_history_edit_allowed' already exists.\n";
    }

    echo "Migration completed successfully!";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
?>
