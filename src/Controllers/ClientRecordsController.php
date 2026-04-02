<?php
class ClientRecordsController {
    private $pdo;
    private $clientId;
    private $clientName;
    
    public function __construct() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set client info from CORRECT session variables
        // Use client_ prefixed variables, not the generic ones
        $this->clientId = $_SESSION['client_id'] ?? null;
        
        // Get client name from client-specific session variables
        $firstName = $_SESSION['client_first_name'] ?? '';
        $lastName = $_SESSION['client_last_name'] ?? '';
        
        if (!empty($firstName) && !empty($lastName)) {
            $this->clientName = trim($firstName . ' ' . $lastName);
        } else {
            // Fallback: try to get from database
            $this->clientName = 'My Account';
        }
        
        // Initialize database connection
        $this->initDatabase();
        
        // If name wasn't set from session, get it from database
        if ($this->clientName === 'My Account' && $this->clientId && $this->pdo) {
            $this->clientName = $this->getClientNameFromDatabase();
        }
    }
    
    /**
     * Initialize database connection
     */
    private function initDatabase() {
        try {
            // Include the Database class
            require_once __DIR__ . '/../../config/database.php';
            
            // Create database instance and get connection
            $database = new Database();
            $this->pdo = $database->getConnection();
            
            if ($this->pdo === null) {
                throw new PDOException("Failed to establish database connection");
            }
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get client name from database for accuracy
     */
    private function getClientNameFromDatabase() {
        if (!$this->clientId || !$this->pdo) {
            return 'My Account';
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT first_name, last_name FROM clients 
                WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$this->clientId]);
            $clientData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($clientData) {
                $fullName = trim($clientData['first_name'] . ' ' . $clientData['last_name']);
                
                // Update session with correct client data
                $_SESSION['client_first_name'] = $clientData['first_name'];
                $_SESSION['client_last_name'] = $clientData['last_name'];
                
                return $fullName;
            }
            
            return 'My Account';
            
        } catch (PDOException $e) {
            error_log("Error fetching client name: " . $e->getMessage());
            return 'My Account';
        }
    }
    
    /**
     * Get all patient records for the logged-in client
     */
    public function getPatientRecords() {
        $data = [
            'records' => [],
            'record_count' => 0,
            'user_name' => $this->clientName,
            'client_id' => $this->clientId,
            'success' => true
        ];
        
        if (!$this->clientId) {
            $data['success'] = false;
            $data['error'] = 'Not logged in';
            return $data;
        }
        
        if (!$this->pdo) {
            $data['success'] = false;
            $data['error'] = 'Database connection failed';
            return $data;
        }
        
        try {
            // Get client status
            $stmtStatus = $this->pdo->prepare("SELECT medical_history_status, client_id, medical_history_edit_allowed FROM clients WHERE id = ?");
            $stmtStatus->execute([$this->clientId]);
            $client = $stmtStatus->fetch(PDO::FETCH_ASSOC);
            $status = $client['medical_history_status'] ?? 'pending';
            $varcharClientId = $client['client_id'] ?? '';
            $editAllowed = $client['medical_history_edit_allowed'] ?? 0;

            $stmt = $this->pdo->prepare("
                SELECT * FROM patient_records 
                WHERE client_id = ? AND is_archived = 0 
                ORDER BY record_date DESC, record_time DESC
            ");
            $stmt->execute([$varcharClientId]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $data['records'] = $records;
            $data['record_count'] = count($records);
            $data['medical_history_status'] = $status;
            $data['medical_history_edit_allowed'] = (int)$editAllowed;

            // Fetch saved medical history data
            if ($status === 'completed' && !empty($varcharClientId)) {
                $histStmt = $this->pdo->prepare("SELECT * FROM patient_medical_history WHERE client_id = ? LIMIT 1");
                $histStmt->execute([$varcharClientId]);
                $data['medical_history'] = $histStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                // Check for pending edit request
                $reqStmt = $this->pdo->prepare("SELECT id, status, requested_at FROM medical_edit_requests WHERE client_id = ? AND status = 'pending' ORDER BY requested_at DESC LIMIT 1");
                $reqStmt->execute([$varcharClientId]);
                $data['pending_edit_request'] = $reqStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } else {
                $data['medical_history'] = null;
                $data['pending_edit_request'] = null;
            }

            $data['varchar_client_id'] = $varcharClientId;
            $data['success'] = true;
            
        } catch (PDOException $e) {
            error_log("Database error in ClientRecordsController: " . $e->getMessage());
            $data['success'] = false;
            $data['error'] = 'Database error: ' . $e->getMessage();
        }
        
        return $data;
    }
    
    /**
     * Format date for display
     */
    public function formatDate($dateString) {
        return date('F j, Y', strtotime($dateString));
    }
    
    /**
     * Format time for display
     */
    public function formatTime($timeString) {
        return date('g:i A', strtotime($timeString));
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return !empty($this->clientId);
    }
    
    /**
     * Get client ID
     */
    public function getClientId() {
        return $this->clientId;
    }
    
    /**
     * Get client name
     */
    public function getClientName() {
        return $this->clientName;
    }
}