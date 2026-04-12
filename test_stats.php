<?php
require_once __DIR__ . '/src/Controllers/AdminPatientController.php';

// Mock session if needed, though getPatientStatistics doesn't use it
$controller = new AdminPatientController();
$stats = $controller->getPatientStatistics();

echo "Stats Result:\n";
print_r($stats);

// Manually test queries to find which one fails
$db = new Database();
$conn = $db->getConnection();

echo "\nManual Query Tests:\n";

try {
    $q1 = "SELECT COUNT(*) as total FROM clients";
    $s1 = $conn->prepare($q1);
    $s1->execute();
    echo "Total Query: OK (" . $s1->fetch(PDO::FETCH_ASSOC)['total'] . ")\n";
} catch (Exception $e) { echo "Total Query: FAILED - " . $e->getMessage() . "\n"; }

try {
    $q2 = "SELECT COUNT(DISTINCT c.id) as total 
           FROM clients c 
           INNER JOIN appointments a ON c.client_id = a.client_id 
           WHERE a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
    $s2 = $conn->prepare($q2);
    $s2->execute();
    echo "Active Query: OK (" . $s2->fetch(PDO::FETCH_ASSOC)['total'] . ")\n";
} catch (Exception $e) { echo "Active Query: FAILED - " . $e->getMessage() . "\n"; }

try {
    $q3 = "SELECT COUNT(DISTINCT c.id) as total 
           FROM clients c 
           LEFT JOIN appointments a ON c.client_id = a.client_id 
               AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
           WHERE a.id IS NULL";
    $s3 = $conn->prepare($q3);
    $s3->execute();
    echo "Inactive Query: OK (" . $s3->fetch(PDO::FETCH_ASSOC)['total'] . ")\n";
} catch (Exception $e) { echo "Inactive Query: FAILED - " . $e->getMessage() . "\n"; }
?>
