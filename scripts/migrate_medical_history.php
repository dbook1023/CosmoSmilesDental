<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // 1. Create patient_medical_history table
    $sql1 = "CREATE TABLE IF NOT EXISTS patient_medical_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id VARCHAR(50) NOT NULL,
        heart_disease TINYINT(1) DEFAULT 0,
        heart_disease_details TEXT,
        high_blood_pressure TINYINT(1) DEFAULT 0,
        diabetes TINYINT(1) DEFAULT 0,
        allergies TEXT,
        past_surgeries TEXT,
        current_medications TEXT,
        is_pregnant TINYINT(1) DEFAULT 0,
        other_conditions TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
    )";
    $db->exec($sql1);
    echo "Table 'patient_medical_history' created or already exists.\n";

    // 2. Add medical_history_status to clients table
    // Check if column exists first
    $checkSql = "SHOW COLUMNS FROM clients LIKE 'medical_history_status'";
    $stmt = $db->query($checkSql);
    if ($stmt->rowCount() == 0) {
        $sql2 = "ALTER TABLE clients ADD COLUMN medical_history_status ENUM('pending', 'completed') DEFAULT 'pending' AFTER profile_image";
        $db->exec($sql2);
        echo "Column 'medical_history_status' added to 'clients' table.\n";
    } else {
        echo "Column 'medical_history_status' already exists.\n";
    }

    echo "Migration completed successfully!";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
?>
