<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$stmt = $db->query("SELECT COUNT(*) as count FROM patient_medical_history");
$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "Total physical records in patient_medical_history: $count\n";

$stmt = $db->query("SELECT client_id FROM patient_medical_history");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Client with medical history: " . $row['client_id'] . "\n";
}
