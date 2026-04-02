<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Controllers/MedicalHistoryController.php';

use Controllers\MedicalHistoryController;

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Standardized session handling
$clientId = $_SESSION['client_id'] ?? null;
$varcharClientId = $_SESSION['client_client_id'] ?? null;

// Debug logging for troubleshooting
error_log("=== SUBMIT MEDICAL HISTORY DEBUG ===");
error_log("Session client_id (numeric): " . ($clientId ?: 'NULL'));
error_log("Session client_client_id (varchar): " . ($varcharClientId ?: 'NULL'));

$database = new Database();
$db = $database->getConnection();

// Robust identification logic
if (!$varcharClientId) {
    if (!$clientId) {
        error_log("ERROR: No client identifier found in session");
        echo json_encode(['success' => false, 'message' => 'Not logged in or session expired.']);
        exit();
    }
    
    // If we only have numeric ID, fetch varchar ID
    try {
        $stmt = $db->prepare("SELECT client_id FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($client && !empty($client['client_id'])) {
            $varcharClientId = $client['client_id'];
            $_SESSION['client_client_id'] = $varcharClientId;
            error_log("Restored varcharClientId from DB: " . $varcharClientId);
        } else {
            // Check if clientId was actually the varchar PAT... ID by mistake
            if (is_string($clientId) && preg_match('/^PAT\d+$/i', $clientId)) {
                $varcharClientId = $clientId;
                error_log("Detected varchar ID in numeric field: " . $varcharClientId);
            } else {
                error_log("ERROR: Client record not found for ID: " . $clientId);
                echo json_encode(['success' => false, 'message' => 'Client record not found.']);
                exit();
            }
        }
    } catch (PDOException $e) {
        error_log("Database error in submit-medical-history: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new MedicalHistoryController($db);
    $action = $_POST['action'] ?? 'submit_medical_history';

    switch ($action) {
        case 'submit_medical_history':
            $result = $controller->submitHistory($varcharClientId, $_POST);
            break;

        case 'update_medical_history':
            $result = $controller->updateHistory($varcharClientId, $_POST);
            break;

        case 'request_edit':
            $result = $controller->requestEdit($varcharClientId);
            break;

        default:
            $result = ['success' => false, 'message' => 'Unknown action'];
            break;
    }

    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
