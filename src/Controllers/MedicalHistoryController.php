<?php
namespace Controllers;

require_once __DIR__ . '/../../config/database.php';

class MedicalHistoryController {
    private $pdo;

    public function __construct($pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            $database = new \Database();
            $this->pdo = $database->getConnection();
        }
    }

    /**
     * Submit medical history for a client
     */
    public function submitHistory($clientId, $data) {
        try {
            $this->pdo->beginTransaction();

            // Prepare record data
            $stmt = $this->pdo->prepare("
                INSERT INTO patient_medical_history (
                    client_id, heart_disease, heart_disease_details, high_blood_pressure, 
                    diabetes, allergies, past_surgeries, current_medications, 
                    is_pregnant, other_conditions
                ) VALUES (
                    :client_id, :heart_disease, :heart_disease_details, :high_blood_pressure, 
                    :diabetes, :allergies, :past_surgeries, :current_medications, 
                    :is_pregnant, :other_conditions
                )
            ");

            $stmt->execute([
                ':client_id' => $clientId,
                ':heart_disease' => $data['heart_disease'] ?? 0,
                ':heart_disease_details' => $data['heart_disease_details'] ?? '',
                ':high_blood_pressure' => $data['high_blood_pressure'] ?? 0,
                ':diabetes' => $data['diabetes'] ?? 0,
                ':allergies' => $data['allergies'] ?? '',
                ':past_surgeries' => $data['past_surgeries'] ?? '',
                ':current_medications' => $data['current_medications'] ?? '',
                ':is_pregnant' => $data['is_pregnant'] ?? 0,
                ':other_conditions' => $data['other_conditions'] ?? ''
            ]);

            // Update client status
            $updateStmt = $this->pdo->prepare("
                UPDATE clients SET medical_history_status = 'completed' 
                WHERE client_id = :client_id
            ");
            $updateStmt->execute([':client_id' => $clientId]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Medical history submitted successfully'];

        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error submitting medical history: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Check if client has completed medical history
     */
    public function isHistoryCompleted($clientId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT medical_history_status FROM clients WHERE client_id = :client_id
            ");
            $stmt->execute([':client_id' => $clientId]);
            $client = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return ($client && $client['medical_history_status'] === 'completed');
        } catch (\PDOException $e) {
            error_log("Error checking medical history status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get medical history for a client
     */
    public function getHistory($clientId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM patient_medical_history WHERE client_id = :client_id LIMIT 1
            ");
            $stmt->execute([':client_id' => $clientId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error fetching medical history: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update existing medical history (only if edit is allowed)
     */
    public function updateHistory($clientId, $data) {
        try {
            // Check if edit is allowed
            if (!$this->isEditAllowed($clientId)) {
                return ['success' => false, 'message' => 'Edit not permitted. Please request permission from your dentist.'];
            }

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                UPDATE patient_medical_history SET
                    heart_disease = :heart_disease,
                    heart_disease_details = :heart_disease_details,
                    high_blood_pressure = :high_blood_pressure,
                    diabetes = :diabetes,
                    allergies = :allergies,
                    past_surgeries = :past_surgeries,
                    current_medications = :current_medications,
                    is_pregnant = :is_pregnant,
                    other_conditions = :other_conditions
                WHERE client_id = :client_id
            ");

            $stmt->execute([
                ':client_id' => $clientId,
                ':heart_disease' => $data['heart_disease'] ?? 0,
                ':heart_disease_details' => $data['heart_disease_details'] ?? '',
                ':high_blood_pressure' => $data['high_blood_pressure'] ?? 0,
                ':diabetes' => $data['diabetes'] ?? 0,
                ':allergies' => $data['allergies'] ?? '',
                ':past_surgeries' => $data['past_surgeries'] ?? '',
                ':current_medications' => $data['current_medications'] ?? '',
                ':is_pregnant' => $data['is_pregnant'] ?? 0,
                ':other_conditions' => $data['other_conditions'] ?? ''
            ]);

            // Reset edit permission after update
            $resetStmt = $this->pdo->prepare("
                UPDATE clients SET medical_history_edit_allowed = 0 WHERE client_id = :client_id
            ");
            $resetStmt->execute([':client_id' => $clientId]);

            // Mark any approved requests as used (deny further edits)
            $closeStmt = $this->pdo->prepare("
                UPDATE medical_edit_requests SET status = 'denied', notes = 'Auto-closed after update'
                WHERE client_id = :client_id AND status = 'approved'
            ");
            $closeStmt->execute([':client_id' => $clientId]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Medical history updated successfully'];

        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error updating medical history: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Request permission to edit medical history
     */
    public function requestEdit($clientId) {
        try {
            // Check if there's already a pending request
            $pending = $this->getPendingRequest($clientId);
            if ($pending) {
                return ['success' => false, 'message' => 'You already have a pending update request.'];
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO medical_edit_requests (client_id, status, requested_at)
                VALUES (:client_id, 'pending', NOW())
            ");
            $stmt->execute([':client_id' => $clientId]);

            return ['success' => true, 'message' => 'Update request submitted. Please wait for dentist approval.'];

        } catch (\PDOException $e) {
            error_log("Error requesting medical history edit: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Approve an edit request (admin/dentist action)
     */
    public function approveEdit($requestId, $adminId) {
        try {
            $this->pdo->beginTransaction();

            // Get the request details
            $stmt = $this->pdo->prepare("SELECT * FROM medical_edit_requests WHERE id = :id AND status = 'pending'");
            $stmt->execute([':id' => $requestId]);
            $request = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$request) {
                return ['success' => false, 'message' => 'Request not found or already processed.'];
            }

            // Update request status
            $updateStmt = $this->pdo->prepare("
                UPDATE medical_edit_requests 
                SET status = 'approved', approved_by = :admin_id, approved_at = NOW()
                WHERE id = :id
            ");
            $updateStmt->execute([':admin_id' => $adminId, ':id' => $requestId]);

            // Set edit permission on client
            $clientStmt = $this->pdo->prepare("
                UPDATE clients SET medical_history_edit_allowed = 1 WHERE client_id = :client_id
            ");
            $clientStmt->execute([':client_id' => $request['client_id']]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Edit request approved. Patient can now update their medical history.'];

        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error approving edit request: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Deny an edit request (admin/dentist action)
     */
    public function denyEdit($requestId, $adminId, $notes = '') {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE medical_edit_requests 
                SET status = 'denied', approved_by = :admin_id, approved_at = NOW(), notes = :notes
                WHERE id = :id AND status = 'pending'
            ");
            $stmt->execute([
                ':admin_id' => $adminId,
                ':id' => $requestId,
                ':notes' => $notes
            ]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Request not found or already processed.'];
            }

            return ['success' => true, 'message' => 'Edit request denied.'];

        } catch (\PDOException $e) {
            error_log("Error denying edit request: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Get pending edit request for a client
     */
    public function getPendingRequest($clientId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM medical_edit_requests 
                WHERE client_id = :client_id AND status = 'pending'
                ORDER BY requested_at DESC LIMIT 1
            ");
            $stmt->execute([':client_id' => $clientId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error checking pending request: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if edit is currently allowed for a client
     */
    public function isEditAllowed($clientId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT medical_history_edit_allowed FROM clients WHERE client_id = :client_id
            ");
            $stmt->execute([':client_id' => $clientId]);
            $client = $stmt->fetch(\PDO::FETCH_ASSOC);
            return ($client && $client['medical_history_edit_allowed'] == 1);
        } catch (\PDOException $e) {
            error_log("Error checking edit permission: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all pending edit requests (for admin dashboard)
     */
    public function getAllPendingRequests() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT mer.*, c.first_name, c.last_name, c.client_id as patient_code
                FROM medical_edit_requests mer
                JOIN clients c ON mer.client_id = c.client_id
                WHERE mer.status = 'pending'
                ORDER BY mer.requested_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error fetching pending requests: " . $e->getMessage());
            return [];
        }
    }
}
?>
