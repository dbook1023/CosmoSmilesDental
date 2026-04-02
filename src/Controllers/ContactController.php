<?php
// src/Controllers/ContactController.php
require_once __DIR__ . '/../../config/database.php';

class ContactController {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function processContactForm($data) {
        // Validate form data
        $errors = $this->validateContactData($data);
        
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => implode(". ", $errors),
                'type' => 'error'
            ];
        }
        
        try {
            // Verify client_id exists if not null
            $client_id = $data['client_id'];
            if ($client_id) {
                $checkStmt = $this->db->prepare("SELECT client_id FROM clients WHERE client_id = :client_id");
                $checkStmt->bindParam(':client_id', $client_id);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() === 0) {
                    // Client not found, set to null to avoid foreign key error
                    $client_id = null;
                }
            }
            
            // Prepare SQL query
            $sql = "INSERT INTO messages (client_id, name, email, phone, message, status, submitted_at) 
                    VALUES (:client_id, :name, :email, :phone, :message, 'unread', NOW())";
            
            $stmt = $this->db->prepare($sql);
            
            // Bind parameters
            $stmt->bindParam(':client_id', $client_id);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':phone', $data['phone']);
            $stmt->bindParam(':message', $data['message']);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => "Your message has been sent successfully! We'll get back to you within 24 hours.",
                    'type' => 'success'
                ];
            } else {
                $errorInfo = $stmt->errorInfo();
                return [
                    'success' => false,
                    'message' => "Failed to send message. Please try again.",
                    'type' => 'error'
                ];
            }
            
        } catch (PDOException $e) {
            // Handle foreign key constraint error gracefully
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                // Try insert without client_id
                try {
                    $sql = "INSERT INTO messages (client_id, name, email, phone, message, status, submitted_at) 
                            VALUES (NULL, :name, :email, :phone, :message, 'unread', NOW())";
                    
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindParam(':name', $data['name']);
                    $stmt->bindParam(':email', $data['email']);
                    $stmt->bindParam(':phone', $data['phone']);
                    $stmt->bindParam(':message', $data['message']);
                    
                    if ($stmt->execute()) {
                        return [
                            'success' => true,
                            'message' => "Your message has been sent successfully! We'll get back to you within 24 hours.",
                            'type' => 'success'
                        ];
                    }
                } catch (Exception $e2) {
                    // Fall through to general error
                }
            }
            
            return [
                'success' => false,
                'message' => "Unable to send message at this time. Please try again later.",
                'type' => 'error'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "System error. Please try again.",
                'type' => 'error'
            ];
        }
    }
    
    private function validateContactData($data) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = "Full Name is required";
        }
        
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid Email Address is required";
        }
        
        if (empty($data['message'])) {
            $errors[] = "Message cannot be empty";
        }
        
        if (strlen($data['message']) < 10) {
            $errors[] = "Message should be at least 10 characters long";
        }
        
        return $errors;
    }
    
    public function getClientData($identifier) {
        if (!$identifier) {
            return null;
        }
        
        try {
            // First try exact match on client_id
            $sql = "SELECT client_id, CONCAT(first_name, ' ', last_name) as full_name, email, phone 
                    FROM clients 
                    WHERE client_id = :identifier 
                    LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // If not found by client_id, try numeric id
            if (is_numeric($identifier)) {
                $sql = "SELECT client_id, CONCAT(first_name, ' ', last_name) as full_name, email, phone 
                        FROM clients 
                        WHERE id = :identifier 
                        LIMIT 1";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':identifier', $identifier, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    return $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
            
            return null;
        } catch (Exception $e) {
            // Silently fail
            error_log("ContactController: Error fetching client data - " . $e->getMessage());
            return null;
        }
    }
}
?>