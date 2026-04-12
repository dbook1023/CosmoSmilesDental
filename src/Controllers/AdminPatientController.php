<?php
// src/Controllers/AdminPatientController.php

require_once __DIR__ . '/../../config/database.php';

class AdminPatientController {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        date_default_timezone_set('Asia/Manila');
    }

    // Get admin user by ID
    public function getAdminUserById($admin_id) {
        try {
            $query = "SELECT id, dentist_id, username, email, first_name, last_name, role, status, last_login 
                     FROM admin_users 
                     WHERE id = ? AND status = 'active'";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $admin_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting admin user: " . $e->getMessage());
            return null;
        }
    }

    // Generate unique patient ID (PAT0001, PAT0002, etc.)
    private function generatePatientId() {
        try {
            // Get the last patient ID
            $query = "SELECT client_id FROM clients WHERE client_id LIKE 'PAT%' ORDER BY LENGTH(client_id) DESC, client_id DESC LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $lastPatient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lastPatient && preg_match('/PAT(\d+)/', $lastPatient['client_id'], $matches)) {
                // Extract the number part and increment
                $lastNumber = intval($matches[1]);
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }
            
            return 'PAT' . $newNumber;
        } catch (Exception $e) {
            error_log("Error generating patient ID: " . $e->getMessage());
            // Fallback - use timestamp
            return 'PAT' . date('YmdHis');
        }
    }

    public function getPatientStatistics() {
        try {
            $stats = [];
            
            // Log connection status
            if (!$this->conn) {
                 error_log("CRITICAL: Database connection is NULL in getPatientStatistics");
                 return $this->getFallbackStats();
            }

            // 1. Total Patients
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM clients");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_patients'] = (int)($result['total'] ?? 0);
            
            // 2. Active Patients: Patients with ANY appointment in the last 90 days
            $activeQuery = "SELECT COUNT(DISTINCT client_id) as total 
                           FROM appointments 
                           WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
            $stmt = $this->conn->prepare($activeQuery);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['active_patients'] = (int)($result['total'] ?? 0);
            
            // 3. Inactive Patients: Simple calculation for robustness
            $stats['inactive_patients'] = max(0, $stats['total_patients'] - $stats['active_patients']);
            
            // 4. New This Month
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM clients 
                        WHERE MONTH(created_at) = MONTH(CURDATE()) 
                        AND YEAR(created_at) = YEAR(CURDATE())");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['new_this_month'] = (int)($result['total'] ?? 0);
            
            // 5. Appointments Today & Trends
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM appointments 
                                 WHERE DATE(appointment_date) = CURDATE() 
                                 AND status IN ('pending', 'confirmed')");
            $stmt->execute();
            $stats['appointments_today'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM appointments 
                              WHERE DATE(appointment_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) 
                              AND status IN ('pending', 'confirmed', 'completed')");
            $stmt->execute();
            $yesterdayTotal = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            $stats['appointments_change'] = $stats['appointments_today'] - $yesterdayTotal;
            
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM clients 
                              WHERE MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                              AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
            $stmt->execute();
            $lastMonthTotal = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            $stats['new_change'] = $stats['new_this_month'] - $lastMonthTotal;
            
            return $stats;
            
        } catch (Throwable $e) {
            error_log("CRITICAL ERROR in getPatientStatistics: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return $this->getFallbackStats();
        }
    }

    private function getFallbackStats() {
        return [
            'total_patients' => 0,
            'active_patients' => 0,
            'new_this_month' => 0,
            'inactive_patients' => 0,
            'appointments_today' => 0,
            'appointments_change' => 0,
            'new_change' => 0
        ];
    }

    // Check if patient is active based on appointments in the last 90 days
    public function isPatientActive($patientId) {
        try {
            // First get the client_id from the patient ID
            $clientQuery = "SELECT client_id FROM clients WHERE id = ?";
            $clientStmt = $this->conn->prepare($clientQuery);
            $clientStmt->bindValue(1, $patientId, PDO::PARAM_INT);
            $clientStmt->execute();
            $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$client) {
                return false;
            }
            
            // Check if patient has appointments in last 90 days
            // Include pending, confirmed, and completed appointments
            $query = "SELECT COUNT(*) as count 
                     FROM appointments 
                     WHERE client_id = ? 
                     AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $client['client_id'], PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return ($row['count'] > 0);
            
        } catch (Exception $e) {
            error_log("Error checking patient activity: " . $e->getMessage());
            return false;
        }
    }

    // Generate default password: # + LastName (capitalized) + day (e.g., #Smith24)
    private function generateDefaultPassword($lastName) {
        $day = date('d'); // Day of month (01-31)
        // Capitalize each word and remove spaces (e.g., "Dela Cruz" -> "DelaCruz")
        $formattedLastName = str_replace(' ', '', ucwords(strtolower($lastName)));
        return '#' . $formattedLastName . $day;
    }

    // Validate Philippine phone number (11 digits starting with 09)
    public function validatePhilippinePhone($phone) {
        // Remove all non-digit characters
        $cleaned = preg_replace('/\D/', '', $phone);
        
        // Check if it's exactly 11 digits and starts with 09
        return (strlen($cleaned) === 11 && preg_match('/^09/', $cleaned));
    }

    // Get ALL patients from database with search functionality
    public function getAllPatients($filters = []) {
        try {
            $query = "SELECT 
                c.id,
                c.client_id,
                c.first_name,
                c.last_name,
                c.birthdate,
                c.gender,
                c.phone,
                c.email,
                c.is_minor,
                c.parental_consent,
                c.profile_image,
                c.address_line1,
                c.address_line2,
                c.city,
                c.state,
                c.postal_code,
                c.country,
                c.created_at,
                c.updated_at,
                (
                    SELECT a.appointment_date 
                    FROM appointments a 
                    WHERE a.client_id = c.client_id 
                    ORDER BY a.appointment_date DESC 
                    LIMIT 1
                ) as last_visit
            FROM clients c 
            WHERE 1=1";

            $params = [];

            // Apply search filter
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $query .= " AND (
                    c.client_id LIKE ? OR 
                    c.first_name LIKE ? OR 
                    c.last_name LIKE ? OR
                    CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR
                    c.phone LIKE ? OR
                    c.email LIKE ?
                )";
                $params = array_merge($params, 
                    [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]
                );
            }

            // Apply status filter - Based on appointments in last 90 days
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                if ($filters['status'] === 'active') {
                    $query .= " AND EXISTS (
                        SELECT 1 FROM appointments a 
                        WHERE a.client_id = c.client_id 
                        AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    )";
                } elseif ($filters['status'] === 'inactive') {
                    $query .= " AND NOT EXISTS (
                        SELECT 1 FROM appointments a 
                        WHERE a.client_id = c.client_id 
                        AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    )";
                }
            }

            // Apply gender filter
            if (!empty($filters['gender']) && $filters['gender'] !== 'all') {
                $query .= " AND c.gender = ?";
                $params[] = $filters['gender'];
            }

            // Apply minor filter
            if (!empty($filters['is_minor']) && $filters['is_minor'] !== 'all') {
                $query .= " AND c.is_minor = ?";
                $params[] = ($filters['is_minor'] === '1') ? 1 : 0;
            }

            // Apply sorting
            $validSortFields = ['c.created_at', 'c.last_name', 'c.first_name', 'last_visit'];
            $sortField = in_array($filters['sort_by'], $validSortFields) ? $filters['sort_by'] : 'c.created_at';
            $sortOrder = strtoupper($filters['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
            $query .= " ORDER BY $sortField $sortOrder";

            // Add pagination
            $limit = 10;
            $page = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
            $offset = ($page - 1) * $limit;
            $query .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->conn->prepare($query);
            
            // Bind parameters for PDO
            if (!empty($params)) {
                foreach ($params as $key => $value) {
                    $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                    $stmt->bindValue($key + 1, $value, $paramType);
                }
            }

            $stmt->execute();
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count for pagination
            $total = $this->getPatientsCount($filters);

            return [
                'patients' => $patients,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ];

        } catch (Exception $e) {
            error_log("Error getting patients: " . $e->getMessage());
            return ['patients' => [], 'total' => 0, 'page' => 1, 'limit' => 10];
        }
    }

    // Get total count of patients with filters
    private function getPatientsCount($filters) {
        try {
            $query = "SELECT COUNT(*) as total FROM clients c WHERE 1=1";
            $params = [];

            // Apply search filter
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $query .= " AND (
                    c.client_id LIKE ? OR 
                    c.first_name LIKE ? OR 
                    c.last_name LIKE ? OR
                    CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR
                    c.phone LIKE ? OR
                    c.email LIKE ?
                )";
                $params = array_merge($params, 
                    [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]
                );
            }

            // Apply status filter
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                if ($filters['status'] === 'active') {
                    $query .= " AND EXISTS (
                        SELECT 1 FROM appointments a 
                        WHERE a.client_id = c.client_id 
                        AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    )";
                } elseif ($filters['status'] === 'inactive') {
                    $query .= " AND NOT EXISTS (
                        SELECT 1 FROM appointments a 
                        WHERE a.client_id = c.client_id 
                        AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    )";
                }
            }

            // Apply gender filter
            if (!empty($filters['gender']) && $filters['gender'] !== 'all') {
                $query .= " AND c.gender = ?";
                $params[] = $filters['gender'];
            }

            // Apply minor filter
            if (!empty($filters['is_minor']) && $filters['is_minor'] !== 'all') {
                $query .= " AND c.is_minor = ?";
                $params[] = ($filters['is_minor'] === '1') ? 1 : 0;
            }

            $stmt = $this->conn->prepare($query);
            
            if (!empty($params)) {
                foreach ($params as $key => $value) {
                    $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                    $stmt->bindValue($key + 1, $value, $paramType);
                }
            }

            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $row['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error getting patients count: " . $e->getMessage());
            return 0;
        }
    }

    // Get patient by ID with full details including address and profile image
    public function getPatientDetails($id) {
        try {
            $query = "SELECT 
                c.id,
                c.client_id,
                c.first_name,
                c.last_name,
                c.birthdate,
                c.gender,
                c.address_line1,
                c.address_line2,
                c.city,
                c.state,
                c.postal_code,
                c.country,
                c.phone,
                c.email,
                c.is_minor,
                c.parental_consent,
                c.profile_image,
                c.created_at,
                c.updated_at,
                (
                    SELECT a.appointment_date 
                    FROM appointments a 
                    WHERE a.client_id = c.client_id 
                    ORDER BY a.appointment_date DESC 
                    LIMIT 1
                ) as last_visit
            FROM clients c 
            WHERE c.id = ?";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$patient) {
                error_log("Patient not found with ID: " . $id);
                return null;
            }
            
            // Format full address
            $patient['full_address'] = $this->formatFullAddress($patient);
            
            // Check if patient is active (appointments in last 90 days)
            $patient['is_active'] = $this->isPatientActive($id);

            // NEW: Fetch medical history
            $medicalQuery = "SELECT * FROM patient_medical_history WHERE client_id = ?";
            $medStmt = $this->conn->prepare($medicalQuery);
            $medStmt->bindValue(1, $patient['client_id'], PDO::PARAM_STR);
            $medStmt->execute();
            $patient['medical_history'] = $medStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            
            return $patient;

        } catch (Exception $e) {
            error_log("Error getting patient details for ID {$id}: " . $e->getMessage());
            return null;
        }
    }

    // Format full address from address components
    private function formatFullAddress($patient) {
        $addressParts = [];
        
        if (!empty($patient['address_line1'])) {
            $addressParts[] = $patient['address_line1'];
        }
        if (!empty($patient['address_line2'])) {
            $addressParts[] = $patient['address_line2'];
        }
        
        $cityState = [];
        if (!empty($patient['city'])) {
            $cityState[] = $patient['city'];
        }
        if (!empty($patient['state'])) {
            $cityState[] = $patient['state'];
        }
        if (!empty($patient['postal_code'])) {
            $cityState[] = $patient['postal_code'];
        }
        
        if (!empty($cityState)) {
            $addressParts[] = implode(', ', $cityState);
        }
        
        if (!empty($patient['country'])) {
            $addressParts[] = $patient['country'];
        }
        
        $fullAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'No address provided';
        
        return $fullAddress;
    }

    // Create new patient
    public function createPatient($data) {
        try {
            // Sanitize inputs
            $data = $this->sanitizeInputArray($data);
            
            // Validate required fields
            $requiredFields = ['first_name', 'last_name', 'birthdate', 'phone', 'email'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    error_log("Missing required field for create: " . $field);
                    return false;
                }
            }

            // Generate unique patient ID
            $clientId = $this->generatePatientId();
            
            // Check if email already exists
            if ($this->emailExists($data['email'])) {
                error_log("Email already exists: " . $data['email']);
                return false;
            }

            // Validate phone number
            if (!$this->validatePhilippinePhone($data['phone'])) {
                error_log("Invalid Philippine phone number: " . $data['phone']);
                return false;
            }

            // Format phone number (keep only digits)
            $formattedPhone = preg_replace('/\D/', '', $data['phone']);
            
            // Calculate age and determine if minor
            $birthdate = new DateTime($data['birthdate']);
            $today = new DateTime();
            $age = $today->diff($birthdate)->y;
            $is_minor = ($age < 18) ? 1 : 0;

            // Generate default password: # + LastName (capitalized) + day
            $defaultPassword = $this->generateDefaultPassword($data['last_name']);
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

            $query = "INSERT INTO clients SET 
                client_id = ?,
                first_name = ?,
                last_name = ?,
                birthdate = ?,
                gender = ?,
                phone = ?,
                email = ?,
                password = ?,
                is_minor = ?,
                parental_consent = ?,
                created_at = NOW(),
                updated_at = NOW()";

            $stmt = $this->conn->prepare($query);
            
            $stmt->bindValue(1, $clientId, PDO::PARAM_STR);
            $stmt->bindValue(2, $data['first_name'], PDO::PARAM_STR);
            $stmt->bindValue(3, $data['last_name'], PDO::PARAM_STR);
            $stmt->bindValue(4, $data['birthdate'], PDO::PARAM_STR);
            $stmt->bindValue(5, $data['gender'], PDO::PARAM_STR);
            $stmt->bindValue(6, $formattedPhone, PDO::PARAM_STR);
            $stmt->bindValue(7, $data['email'], PDO::PARAM_STR);
            $stmt->bindValue(8, $hashedPassword, PDO::PARAM_STR);
            $stmt->bindValue(9, $is_minor, PDO::PARAM_INT);
            $stmt->bindValue(10, $data['parental_consent'] ?? 0, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            
            if ($result) {
                $dbId = $this->conn->lastInsertId();
                error_log("Patient created successfully: " . $clientId . " (DB ID: " . $dbId . ")");
                
                // Store the default password in session to show in success message
                $_SESSION['patient_created'] = [
                    'client_id' => $clientId,
                    'default_password' => $defaultPassword,
                    'patient_name' => $data['first_name'] . ' ' . $data['last_name']
                ];
                
                return true;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error creating patient: " . print_r($errorInfo, true));
                return false;
            }

        } catch (Exception $e) {
            error_log("Error creating patient: " . $e->getMessage());
            return false;
        }
    }

    // Update patient
    public function updatePatient($id, $data) {
        try {
            // Sanitize inputs
            $data = $this->sanitizeInputArray($data);
            
            // Validate required fields
            $requiredFields = ['first_name', 'last_name', 'birthdate', 'phone', 'email'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    error_log("Missing required field for update: " . $field);
                    return false;
                }
            }

            // Check if email already exists (excluding current patient)
            if ($this->emailExists($data['email'], $id)) {
                error_log("Email already exists: " . $data['email']);
                return false;
            }

            // Validate phone number
            if (!$this->validatePhilippinePhone($data['phone'])) {
                error_log("Invalid Philippine phone number: " . $data['phone']);
                return false;
            }

            // Format phone number (keep only digits)
            $formattedPhone = preg_replace('/\D/', '', $data['phone']);
            
            // Calculate age and determine if minor
            $birthdate = new DateTime($data['birthdate']);
            $today = new DateTime();
            $age = $today->diff($birthdate)->y;
            $is_minor = ($age < 18) ? 1 : 0;

            // Include address fields in update if provided
            $query = "UPDATE clients SET 
                first_name = ?,
                last_name = ?,
                birthdate = ?,
                gender = ?,
                phone = ?,
                email = ?,
                is_minor = ?,
                parental_consent = ?,
                address_line1 = ?,
                address_line2 = ?,
                city = ?,
                state = ?,
                postal_code = ?,
                country = ?,
                updated_at = NOW()
                WHERE id = ?";

            $stmt = $this->conn->prepare($query);
            
            $stmt->bindValue(1, $data['first_name'], PDO::PARAM_STR);
            $stmt->bindValue(2, $data['last_name'], PDO::PARAM_STR);
            $stmt->bindValue(3, $data['birthdate'], PDO::PARAM_STR);
            $stmt->bindValue(4, $data['gender'], PDO::PARAM_STR);
            $stmt->bindValue(5, $formattedPhone, PDO::PARAM_STR);
            $stmt->bindValue(6, $data['email'], PDO::PARAM_STR);
            $stmt->bindValue(7, $is_minor, PDO::PARAM_INT);
            $stmt->bindValue(8, $data['parental_consent'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(9, $data['address_line1'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(10, $data['address_line2'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(11, $data['city'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(12, $data['state'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(13, $data['postal_code'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(14, $data['country'] ?? 'Philippines', PDO::PARAM_STR);
            $stmt->bindValue(15, $id, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            
            if ($result) {
                error_log("Patient updated successfully: ID " . $id);
                return true;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error updating patient: " . print_r($errorInfo, true));
                return false;
            }

        } catch (Exception $e) {
            error_log("Error updating patient: " . $e->getMessage());
            return false;
        }
    }

    // Delete patient
    public function deletePatient($id) {
        try {
            // First, check if patient exists
            $patient = $this->getPatientDetails($id);
            if (!$patient) {
                error_log("Patient not found for deletion: " . $id);
                return false;
            }

            // Check if patient has appointments
            $appointmentsQuery = "SELECT COUNT(*) as count FROM appointments WHERE client_id = ?";
            $appointmentsStmt = $this->conn->prepare($appointmentsQuery);
            $appointmentsStmt->bindValue(1, $patient['client_id'], PDO::PARAM_STR);
            $appointmentsStmt->execute();
            $appointmentsRow = $appointmentsStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($appointmentsRow['count'] > 0) {
                error_log("Cannot delete patient with appointments: " . $patient['client_id']);
                return false; // Or implement soft delete
            }

            $query = "DELETE FROM clients WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $id, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            
            if ($result) {
                error_log("Patient deleted successfully: " . $patient['client_id']);
                return true;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error deleting patient: " . print_r($errorInfo, true));
                return false;
            }

        } catch (Exception $e) {
            error_log("Error deleting patient: " . $e->getMessage());
            return false;
        }
    }

    // Check if email already exists
    private function emailExists($email, $excludeId = null) {
        try {
            $query = "SELECT COUNT(*) as count FROM clients WHERE email = ?";
            $params = [$email];
            
            if ($excludeId) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($row['count'] > 0);
        } catch (Exception $e) {
            error_log("Error checking if email exists: " . $e->getMessage());
            return false;
        }
    }

    // Sanitize input array
    private function sanitizeInputArray($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(strip_tags(trim($value)));
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    // Handle API request for patient details
    public function handlePatientDetailsRequest() {
        // Set header for JSON response
        header('Content-Type: application/json');
        
        // Check if admin is logged in
        if (!isset($_SESSION['admin_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid patient ID required']);
            exit;
        }

        $patientId = intval($_GET['id']);
        $patient = $this->getPatientDetails($patientId);

        if ($patient) {
            echo json_encode([
                'success' => true, 
                'patient' => $patient
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false, 
                'message' => 'Patient not found'
            ]);
        }
        exit;
    }

    // Get dentist check-in status mapped from admin user
    public function getDentistCheckInStatus($adminId) {
        try {
            $query = "SELECT d.is_checked_in, d.checked_in_at 
                      FROM admin_users a
                      JOIN dentists d ON a.dentist_id = d.license_number
                      WHERE a.id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $adminId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return ['status' => false, 'time' => null];
            }
            return [
                'status' => (bool)$result['is_checked_in'],
                'time' => $result['checked_in_at']
            ];
        } catch (Exception $e) {
            error_log("Error getting dentist check-in status: " . $e->getMessage());
            return ['status' => false, 'time' => null];
        }
    }

    // Toggle dentist check-in status mapped from admin user
    public function toggleDentistCheckInStatus($adminId, $status) {
        try {
            // First get the license number from the admin user
            $query = "SELECT dentist_id FROM admin_users WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $adminId, PDO::PARAM_INT);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin || empty($admin['dentist_id'])) {
                return false;
            }

            $licenseNumber = $admin['dentist_id'];
            $is_checked_in = $status ? 1 : 0;
            $timeVal = $status ? date('Y-m-d H:i:s') : null;

            $updateQuery = "UPDATE dentists SET is_checked_in = ?, checked_in_at = ? WHERE license_number = ?";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindValue(1, $is_checked_in, PDO::PARAM_INT);
            $updateStmt->bindValue(2, $timeVal, $timeVal === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $updateStmt->bindValue(3, $licenseNumber, PDO::PARAM_STR);
            
            $success = $updateStmt->execute();
            if ($success) {
                return ['success' => true, 'time' => $timeVal];
            }
            return false;
        } catch (Exception $e) {
            error_log("Error toggling dentist check-in status: " . $e->getMessage());
            return false;
        }
    }

    // Export patients to CSV
    public function exportPatients($filters = []) {
        try {
            // Get patients data
            $patientsData = $this->getAllPatients($filters);
            $patients = $patientsData['patients'];
            
            // Create CSV output
            $output = fopen('php://output', 'w');
            
            // Add headers
            fputcsv($output, [
                'Patient ID', 
                'First Name', 
                'Last Name', 
                'Birthdate', 
                'Age', 
                'Gender', 
                'Phone', 
                'Email', 
                'Address Line 1',
                'Address Line 2',
                'City',
                'State',
                'Postal Code',
                'Country',
                'Full Address',
                'Status', 
                'Last Visit', 
                'Date Created'
            ]);
            
            // Add data rows
            foreach ($patients as $patient) {
                // Calculate age
                $birthdate = new DateTime($patient['birthdate']);
                $today = new DateTime();
                $age = $today->diff($birthdate)->y;
                
                // Determine status using the same logic as isPatientActive
                $isActive = $this->isPatientActive($patient['id']);
                $status = $isActive ? 'Active' : 'Inactive';
                
                // Format full address
                $fullAddress = $this->formatFullAddress($patient);
                
                fputcsv($output, [
                    $patient['client_id'],
                    $patient['first_name'],
                    $patient['last_name'],
                    $patient['birthdate'],
                    $age,
                    ucfirst($patient['gender']),
                    $patient['phone'],
                    $patient['email'],
                    $patient['address_line1'] ?? '',
                    $patient['address_line2'] ?? '',
                    $patient['city'] ?? '',
                    $patient['state'] ?? '',
                    $patient['postal_code'] ?? '',
                    $patient['country'] ?? 'Philippines',
                    $fullAddress,
                    $status,
                    $patient['last_visit'] ? date('Y-m-d', strtotime($patient['last_visit'])) : 'Never',
                    $patient['created_at']
                ]);
            }
            
            fclose($output);
            return true;
            
        } catch (Exception $e) {
            error_log("Error exporting patients: " . $e->getMessage());
            return false;
        }
    }
}
