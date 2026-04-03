<?php
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    $conn->exec("ALTER TABLE patient_records ADD COLUMN findings TEXT AFTER description");
    echo "Table patient_records updated successfully with findings column.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column findings already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
