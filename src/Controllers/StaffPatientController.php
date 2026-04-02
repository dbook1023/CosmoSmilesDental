<?php
// src/Controllers/StaffPatientController.php

require_once __DIR__ . '/../../config/database.php';

class StaffPatientController {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    // Get staff user by ID
    public function getStaffUserById($staff_id) {
        try {
            if (is_numeric($staff_id)) {
                $query = "SELECT id, staff_id, email, first_name, last_name, role, department, phone 
                         FROM staff_users 
                         WHERE id = ? AND status = 'active'";
                $stmt = $this->conn->prepare($query);
                $stmt->bindValue(1, $staff_id, PDO::PARAM_INT);
            } else {
                $query = "SELECT id, staff_id, email, first_name, last_name, role, department, phone 
                         FROM staff_users 
                         WHERE staff_id = ? AND status = 'active'";
                $stmt = $this->conn->prepare($query);
                $stmt->bindValue(1, $staff_id, PDO::PARAM_STR);
            }
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting staff user: " . $e->getMessage());
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

    // Get patient statistics - FIXED to match table data
    public function getPatientStatistics() {
        try {
            $stats = [];
            
            // Total Patients - count all clients
            $totalQuery = "SELECT COUNT(*) as total FROM clients";
            $totalStmt = $this->conn->prepare($totalQuery);
            $totalStmt->execute();
            $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_patients'] = $totalResult['total'] ?? 0;
            
            // ACTIVE PATIENTS: Patients with ANY appointment in the last 90 days
            // Using client_id to join with appointments table
            $activeQuery = "SELECT COUNT(DISTINCT c.id) as total 
                           FROM clients c 
                           INNER JOIN appointments a ON c.client_id = a.client_id 
                           WHERE a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                           AND a.status IN ('pending', 'confirmed', 'completed')";
            $activeStmt = $this->conn->prepare($activeQuery);
            $activeStmt->execute();
            $activeResult = $activeStmt->fetch(PDO::FETCH_ASSOC);
            $stats['active_patients'] = $activeResult['total'] ?? 0;
            
            // New Patients This Month - based on created_at date
            $newQuery = "SELECT COUNT(*) as total FROM clients 
                        WHERE MONTH(created_at) = MONTH(CURDATE()) 
                        AND YEAR(created_at) = YEAR(CURDATE())";
            $newStmt = $this->conn->prepare($newQuery);
            $newStmt->execute();
            $newResult = $newStmt->fetch(PDO::FETCH_ASSOC);
            $stats['new_this_month'] = $newResult['total'] ?? 0;
            
            // INACTIVE PATIENTS: Patients with NO appointments in the last 90 days
            // Using LEFT JOIN and checking for NULL appointments
            $inactiveQuery = "SELECT COUNT(DISTINCT c.id) as total 
                             FROM clients c 
                             LEFT JOIN appointments a ON c.client_id = a.client_id 
                                 AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                                 AND a.status IN ('pending', 'confirmed', 'completed')
                             WHERE a.id IS NULL";
            $inactiveStmt = $this->conn->prepare($inactiveQuery);
            $inactiveStmt->execute();
            $inactiveResult = $inactiveStmt->fetch(PDO::FETCH_ASSOC);
            $stats['inactive_patients'] = $inactiveResult['total'] ?? 0;
            
            // VERIFICATION: Log if there's a mismatch
            $calculatedTotal = $stats['active_patients'] + $stats['inactive_patients'];
            if ($stats['total_patients'] != $calculatedTotal) {
                error_log("PATIENT COUNT MISMATCH - Total: {$stats['total_patients']}, Active: {$stats['active_patients']}, Inactive: {$stats['inactive_patients']}");
                
                // FIX: Recalculate using a more reliable method
                $activeIdsQuery = "SELECT DISTINCT c.id 
                                  FROM clients c 
                                  INNER JOIN appointments a ON c.client_id = a.client_id 
                                  WHERE a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                                  AND a.status IN ('pending', 'confirmed', 'completed')";
                $activeIdsStmt = $this->conn->prepare($activeIdsQuery);
                $activeIdsStmt->execute();
                $activeIds = $activeIdsStmt->fetchAll(PDO::FETCH_COLUMN);
                
                $stats['active_patients'] = count($activeIds);
                $stats['inactive_patients'] = $stats['total_patients'] - $stats['active_patients'];
                
                error_log("RECALCULATED - Active: {$stats['active_patients']}, Inactive: {$stats['inactive_patients']}");
            }
            
            // Calculate new patients from last month for comparison
            $lastMonthQuery = "SELECT COUNT(*) as total FROM clients 
                              WHERE MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                              AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
            $lastMonthStmt = $this->conn->prepare($lastMonthQuery);
            $lastMonthStmt->execute();
            $lastMonthResult = $lastMonthStmt->fetch(PDO::FETCH_ASSOC);
            $lastMonthTotal = $lastMonthResult['total'] ?? 0;
            
            $stats['new_change'] = $stats['new_this_month'] - $lastMonthTotal;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting patient statistics: " . $e->getMessage());
            return [
                'total_patients' => 0,
                'active_patients' => 0,
                'new_this_month' => 0,
                'inactive_patients' => 0,
                'new_change' => 0
            ];
        }
    }

    // Check if patient is active based on appointments (ANY appointment in last 90 days)
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
            
            // Check if patient has ANY appointments in last 90 days (pending, confirmed, completed)
            $query = "SELECT COUNT(*) as count 
                     FROM appointments 
                     WHERE client_id = ? 
                     AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                     AND status IN ('pending', 'confirmed', 'completed')";
            
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

    // Get last appointment date for a patient
    public function getLastAppointmentDate($patientId) {
        try {
            // First get the client_id from the patient ID
            $clientQuery = "SELECT client_id FROM clients WHERE id = ?";
            $clientStmt = $this->conn->prepare($clientQuery);
            $clientStmt->bindValue(1, $patientId, PDO::PARAM_INT);
            $clientStmt->execute();
            $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$client) {
                return 'No appointments';
            }
            
            $query = "SELECT appointment_date 
                     FROM appointments 
                     WHERE client_id = ? 
                     ORDER BY appointment_date DESC 
                     LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $client['client_id'], PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['appointment_date'])) {
                return date('M j, Y', strtotime($result['appointment_date']));
            }
            
            return 'No appointments';
        } catch (Exception $e) {
            error_log("Error getting last appointment date: " . $e->getMessage());
            return 'No appointments';
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

    // Get ALL patients from database with search functionality (without address in main table)
    public function getAllPatients($filters = []) {
        try {
            $query = "SELECT 
                id,
                client_id,
                first_name,
                last_name,
                birthdate,
                gender,
                phone,
                email,
                is_minor,
                parental_consent,
                profile_image,
                created_at,
                updated_at
            FROM clients 
            WHERE 1=1";

            $params = [];

            // Apply search filter
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $query .= " AND (
                    client_id LIKE ? OR 
                    first_name LIKE ? OR 
                    last_name LIKE ? OR
                    CONCAT(first_name, ' ', last_name) LIKE ? OR
                    phone LIKE ? OR
                    email LIKE ?
                )";
                $params = array_merge($params, 
                    [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]
                );
            }

            // Apply gender filter
            if (!empty($filters['gender']) && $filters['gender'] !== 'all') {
                $query .= " AND gender = ?";
                $params[] = $filters['gender'];
            }

            // Apply minor filter
            if (!empty($filters['is_minor']) && $filters['is_minor'] !== 'all') {
                $query .= " AND is_minor = ?";
                $params[] = ($filters['is_minor'] === '1') ? 1 : 0;
            }

            // Apply sorting
            $validSortFields = ['created_at', 'last_name', 'first_name', 'birthdate'];
            $sortField = in_array($filters['sort_by'], $validSortFields) ? $filters['sort_by'] : 'created_at';
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
            $query = "SELECT COUNT(*) as total FROM clients WHERE 1=1";
            $params = [];

            // Apply search filter
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $query .= " AND (
                    client_id LIKE ? OR 
                    first_name LIKE ? OR 
                    last_name LIKE ? OR
                    CONCAT(first_name, ' ', last_name) LIKE ? OR
                    phone LIKE ? OR
                    email LIKE ?
                )";
                $params = array_merge($params, 
                    [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]
                );
            }

            if (!empty($filters['gender']) && $filters['gender'] !== 'all') {
                $query .= " AND gender = ?";
                $params[] = $filters['gender'];
            }

            if (!empty($filters['is_minor']) && $filters['is_minor'] !== 'all') {
                $query .= " AND is_minor = ?";
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

    // Get patient by ID with full details (including address for modal view)
    public function getPatientDetails($id) {
        try {
            $query = "SELECT 
                id,
                client_id,
                first_name,
                last_name,
                birthdate,
                gender,
                address_line1,
                address_line2,
                city,
                state,
                postal_code,
                country,
                phone,
                email,
                is_minor,
                parental_consent,
                profile_image,
                created_at,
                updated_at
            FROM clients 
            WHERE id = ?";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$patient) {
                error_log("Patient not found with ID: " . $id);
                return null;
            }
            
            return $patient;

        } catch (Exception $e) {
            error_log("Error getting patient details for ID {$id}: " . $e->getMessage());
            return null;
        }
    }

    // Handle API request for patient details
    public function handlePatientDetailsRequest() {
        // Set header for JSON response
        header('Content-Type: application/json');
        
        // Check if staff is logged in
        if (!isset($_SESSION['staff_id'])) {
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
            // Check if patient is active
            $isActive = $this->isPatientActive($patientId);
            $patient['is_active'] = $isActive;
            
            // Format full address
            $patient['full_address'] = $this->formatFullAddress($patient);
            
            // Get patient's recent appointments
            $patient['recent_appointments'] = $this->getPatientRecentAppointments($patient);
            
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

    // Get patient's recent appointments
    private function getPatientRecentAppointments($patient) {
        try {
            $query = "SELECT 
                a.appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.status,
                a.notes,
                a.payment_type,
                a.service_price,
                a.duration_minutes,
                s.service_name as service,
                s.id as service_id,
                d.first_name as dentist_first_name,
                d.last_name as dentist_last_name,
                d.staff_id as dentist_staff_id
            FROM appointments a
            LEFT JOIN services s ON a.service_id = s.id
            LEFT JOIN staff_users d ON a.dentist_id = d.id
            WHERE a.client_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 10";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $patient['client_id'], PDO::PARAM_STR);
            $stmt->execute();
            
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format the data for display
            foreach ($appointments as &$apt) {
                // Format service name
                if (empty($apt['service']) && !empty($apt['service_price'])) {
                    $apt['service'] = 'Dental Service (ID: ' . ($apt['service_id'] ?? 'N/A') . ')';
                } elseif (empty($apt['service'])) {
                    $apt['service'] = 'General Dental Service';
                }
                
                // Format dentist name
                if (!empty($apt['dentist_first_name']) || !empty($apt['dentist_last_name'])) {
                    $apt['dentist_name'] = trim('Dr. ' . $apt['dentist_first_name'] . ' ' . $apt['dentist_last_name']);
                } else {
                    $apt['dentist_name'] = 'Not Assigned';
                }
                
                // Format time
                if (!empty($apt['appointment_time'])) {
                    $apt['formatted_time'] = date('h:i A', strtotime($apt['appointment_time']));
                } else {
                    $apt['formatted_time'] = '—';
                }
                
                // Format date
                if (!empty($apt['appointment_date'])) {
                    $apt['formatted_date'] = date('M j, Y', strtotime($apt['appointment_date']));
                } else {
                    $apt['formatted_date'] = '—';
                }
                
                // Format price
                if (!empty($apt['service_price'])) {
                    $apt['formatted_price'] = '₱' . number_format($apt['service_price'], 2);
                } else {
                    $apt['formatted_price'] = '—';
                }
                
                // Format status class for styling
                $apt['status_class'] = strtolower(str_replace(' ', '-', $apt['status']));
                
                // Format payment type
                if (!empty($apt['payment_type'])) {
                    $apt['payment_type_upper'] = strtoupper($apt['payment_type']);
                } else {
                    $apt['payment_type_upper'] = '—';
                }
                
                // Ensure all fields exist
                $apt['appointment_id'] = $apt['appointment_id'] ?? 'N/A';
                $apt['notes'] = $apt['notes'] ?? '';
            }
            
            return $appointments;
            
        } catch (Exception $e) {
            error_log("Error getting recent appointments: " . $e->getMessage());
            return [];
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
        
        if (!empty($patient['city'])) {
            $addressParts[] = $patient['city'];
        }
        
        if (!empty($patient['state'])) {
            $addressParts[] = $patient['state'];
        }
        
        if (!empty($patient['postal_code'])) {
            $addressParts[] = $patient['postal_code'];
        }
        
        if (!empty($patient['country'])) {
            $addressParts[] = $patient['country'];
        }
        
        return !empty($addressParts) ? implode(', ', $addressParts) : 'No address provided';
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
                address_line1 = ?,
                address_line2 = ?,
                city = ?,
                state = ?,
                postal_code = ?,
                country = ?,
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
            $stmt->bindValue(5, $data['gender'] ?? 'other', PDO::PARAM_STR);
            $stmt->bindValue(6, $data['address_line1'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(7, $data['address_line2'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(8, $data['city'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(9, $data['state'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(10, $data['postal_code'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(11, $data['country'] ?? 'Philippines', PDO::PARAM_STR);
            $stmt->bindValue(12, $formattedPhone, PDO::PARAM_STR);
            $stmt->bindValue(13, $data['email'], PDO::PARAM_STR);
            $stmt->bindValue(14, $hashedPassword, PDO::PARAM_STR);
            $stmt->bindValue(15, $is_minor, PDO::PARAM_INT);
            $stmt->bindValue(16, $data['parental_consent'] ?? 0, PDO::PARAM_INT);
            
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

    // Handle import patients
    public function handleImportPatients() {
        // Check if staff is logged in
        if (!isset($_SESSION['staff_id'])) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        // Check if file was uploaded
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
            exit;
        }

        $file = $_FILES['import_file']['tmp_name'];
        $fileName = $_FILES['import_file']['name'];
        $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);

        // Check file extension
        if (strtolower($fileExt) !== 'csv') {
            echo json_encode(['success' => false, 'message' => 'Only CSV files are allowed']);
            exit;
        }

        try {
            // Read CSV file
            $handle = fopen($file, 'r');
            if ($handle === false) {
                echo json_encode(['success' => false, 'message' => 'Cannot open uploaded file']);
                exit;
            }

            // Get headers
            $headers = fgetcsv($handle);
            if ($headers === false) {
                echo json_encode(['success' => false, 'message' => 'Invalid CSV file format']);
                exit;
            }

            // Required headers
            $requiredHeaders = ['first_name', 'last_name', 'birthdate', 'phone', 'email'];
            
            // Validate headers
            foreach ($requiredHeaders as $required) {
                if (!in_array($required, $headers)) {
                    echo json_encode(['success' => false, 'message' => "Missing required column: $required"]);
                    exit;
                }
            }

            $importedCount = 0;
            $failedCount = 0;
            $errors = [];

            // Process each row
            $rowNum = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Map headers to values
                $rowData = array_combine($headers, $row);
                
                // Validate required fields
                $missingFields = [];
                foreach ($requiredHeaders as $field) {
                    if (empty(trim($rowData[$field]))) {
                        $missingFields[] = $field;
                    }
                }
                
                if (!empty($missingFields)) {
                    $errors[] = "Row $rowNum: Missing fields: " . implode(', ', $missingFields);
                    $failedCount++;
                    continue;
                }
                
                // Trim all values
                foreach ($rowData as $key => $value) {
                    $rowData[$key] = trim($value);
                }
                
                // Validate email
                if (!filter_var($rowData['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row $rowNum: Invalid email format: " . $rowData['email'];
                    $failedCount++;
                    continue;
                }
                
                // Check if email already exists
                if ($this->emailExists($rowData['email'])) {
                    $errors[] = "Row $rowNum: Email already exists: " . $rowData['email'];
                    $failedCount++;
                    continue;
                }
                
                // Validate phone
                if (!$this->validatePhilippinePhone($rowData['phone'])) {
                    $errors[] = "Row $rowNum: Invalid phone number. Must be 11 digits starting with 09: " . $rowData['phone'];
                    $failedCount++;
                    continue;
                }
                
                // Validate birthdate
                if (!strtotime($rowData['birthdate'])) {
                    $errors[] = "Row $rowNum: Invalid birthdate format: " . $rowData['birthdate'];
                    $failedCount++;
                    continue;
                }

                // Generate patient ID
                $clientId = $this->generatePatientId();
                
                // Generate default password
                $defaultPassword = $this->generateDefaultPassword($rowData['last_name']);
                $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
                
                // Calculate age
                $birthdate = new DateTime($rowData['birthdate']);
                $today = new DateTime();
                $age = $today->diff($birthdate)->y;
                $is_minor = ($age < 18) ? 1 : 0;
                
                // Format phone
                $formattedPhone = preg_replace('/\D/', '', $rowData['phone']);
                
                // Insert patient
                $query = "INSERT INTO clients SET 
                    client_id = ?,
                    first_name = ?,
                    last_name = ?,
                    birthdate = ?,
                    gender = ?,
                    address_line1 = ?,
                    address_line2 = ?,
                    city = ?,
                    state = ?,
                    postal_code = ?,
                    country = ?,
                    phone = ?,
                    email = ?,
                    password = ?,
                    is_minor = ?,
                    parental_consent = ?,
                    created_at = NOW(),
                    updated_at = NOW()";
                
                $stmt = $this->conn->prepare($query);
                
                $stmt->bindValue(1, $clientId, PDO::PARAM_STR);
                $stmt->bindValue(2, $rowData['first_name'], PDO::PARAM_STR);
                $stmt->bindValue(3, $rowData['last_name'], PDO::PARAM_STR);
                $stmt->bindValue(4, $rowData['birthdate'], PDO::PARAM_STR);
                $stmt->bindValue(5, strtolower($rowData['gender'] ?? 'other'), PDO::PARAM_STR);
                $stmt->bindValue(6, $rowData['address_line1'] ?? null, PDO::PARAM_STR);
                $stmt->bindValue(7, $rowData['address_line2'] ?? null, PDO::PARAM_STR);
                $stmt->bindValue(8, $rowData['city'] ?? null, PDO::PARAM_STR);
                $stmt->bindValue(9, $rowData['state'] ?? null, PDO::PARAM_STR);
                $stmt->bindValue(10, $rowData['postal_code'] ?? null, PDO::PARAM_STR);
                $stmt->bindValue(11, $rowData['country'] ?? 'Philippines', PDO::PARAM_STR);
                $stmt->bindValue(12, $formattedPhone, PDO::PARAM_STR);
                $stmt->bindValue(13, $rowData['email'], PDO::PARAM_STR);
                $stmt->bindValue(14, $hashedPassword, PDO::PARAM_STR);
                $stmt->bindValue(15, $is_minor, PDO::PARAM_INT);
                $stmt->bindValue(16, 0, PDO::PARAM_INT); // Default no parental consent
                
                if ($stmt->execute()) {
                    $importedCount++;
                } else {
                    $failedCount++;
                    $errorInfo = $stmt->errorInfo();
                    $errors[] = "Row $rowNum: Database error: " . $errorInfo[2];
                }
            }
            
            fclose($handle);
            
            echo json_encode([
                'success' => true,
                'message' => "Import completed successfully!",
                'imported' => $importedCount,
                'failed' => $failedCount,
                'errors' => $errors
            ]);
            
        } catch (Exception $e) {
            error_log("Error importing patients: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error importing patients: ' . $e->getMessage()]);
        }
        exit;
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

            $query = "UPDATE clients SET 
                first_name = ?,
                last_name = ?,
                birthdate = ?,
                gender = ?,
                address_line1 = ?,
                address_line2 = ?,
                city = ?,
                state = ?,
                postal_code = ?,
                country = ?,
                phone = ?,
                email = ?,
                is_minor = ?,
                parental_consent = ?,
                updated_at = NOW()
            WHERE id = ?";

            $stmt = $this->conn->prepare($query);
            
            $stmt->bindValue(1, $data['first_name'], PDO::PARAM_STR);
            $stmt->bindValue(2, $data['last_name'], PDO::PARAM_STR);
            $stmt->bindValue(3, $data['birthdate'], PDO::PARAM_STR);
            $stmt->bindValue(4, $data['gender'] ?? 'other', PDO::PARAM_STR);
            $stmt->bindValue(5, $data['address_line1'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(6, $data['address_line2'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(7, $data['city'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(8, $data['state'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(9, $data['postal_code'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(10, $data['country'] ?? 'Philippines', PDO::PARAM_STR);
            $stmt->bindValue(11, $formattedPhone, PDO::PARAM_STR);
            $stmt->bindValue(12, $data['email'], PDO::PARAM_STR);
            $stmt->bindValue(13, $is_minor, PDO::PARAM_INT);
            $stmt->bindValue(14, $data['parental_consent'] ?? 0, PDO::PARAM_INT);
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
    
    // Add this method to get the database connection
    public function getConnection() {
        return $this->conn;
    }
}
?>