<?php
require_once 'config/database.php';
require_once 'src/Controllers/PatientRecordsController.php';

$database = new Database();
$db = $database->getConnection();

echo "--- PATIENT RECORDS DETAIL for PAT0001 ---\n";

$stmt = $db->prepare("SELECT id, client_id, is_archived, record_date FROM patient_records WHERE client_id = ?");
$stmt->execute(['PAT0001']);
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | ClientID: '{$row['client_id']}' | Archived: {$row['is_archived']} | Date: {$row['record_date']}\n";
}

$controller = new \Controllers\PatientRecordsController($db);
$result = $controller->searchPatient('PAT0001');

echo "\n--- Controller Search Result ---\n";
echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
echo "Total Records (from result): " . $result['patient']['total_records'] . "\n";
echo "Medical History: " . ($result['patient']['medical_history'] ? 'YES' : 'NO') . "\n";
