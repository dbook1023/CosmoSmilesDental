<?php
// src/Controllers/StaffAppointmentController.php

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';

// Add SMS Service class
class TextBeeSMSService {
    private $baseUrl;
    private $apiKey;
    private $deviceId;
    
    public function __construct() {
        $this->baseUrl  = env('SMS_BASE_URL', 'https://api.textbee.dev/api/v1');
        $this->apiKey   = env('SMS_API_KEY', '');
        $this->deviceId = env('SMS_DEVICE_ID', '');
    }
    
    /**
     * Send SMS via TextBee API
     */
    public function sendSMS($recipient, $message) {
        try {
            $url = $this->baseUrl . '/gateway/devices/' . $this->deviceId . '/send-sms';
            
            // Format phone number properly
            $formattedPhone = $this->formatPhone($recipient);
            
            if (!$formattedPhone) {
                error_log("Invalid phone number format: " . $recipient);
                return false;
            }
            
            $data = [
                'recipients' => [$formattedPhone],
                'message' => $message
            ];
            
            $headers = [
                'x-api-key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                error_log("CURL Error sending SMS: " . curl_error($ch));
                curl_close($ch);
                return false;
            }
            
            curl_close($ch);
            
            // Check for success (201 Created is success for TextBee)
            if ($httpCode == 200 || $httpCode == 201) {
                error_log("SMS sent successfully to: " . $formattedPhone);
                return true;
            } else {
                error_log("Failed to send SMS. HTTP Code: " . $httpCode . ", Response: " . $response);
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Exception sending SMS: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Format Philippine phone number for API
     */
    private function formatPhone($phone) {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if it's a valid Philippine mobile number
        if (strlen($cleaned) === 11 && substr($cleaned, 0, 2) === '09') {
            return '+63' . substr($cleaned, 1);
        }
        
        if (strlen($cleaned) === 12 && substr($cleaned, 0, 2) === '63') {
            return '+' . $cleaned;
        }
        
        if (strlen($phone) === 13 && substr($phone, 0, 3) === '+63') {
            return $phone;
        }
        
        return false; // Invalid format
    }
    
    /**
     * Send appointment confirmation SMS
     */
    public function sendAppointmentConfirmation($appointmentData) {
        $patientName = $appointmentData['patient_full_name'];
        $appointmentDate = $appointmentData['appointment_date'];
        $appointmentTime = $appointmentData['appointment_time'];
        $patientPhone = $appointmentData['patient_phone'];
        $appointmentId = $appointmentData['appointment_id'];
        $dentistName = $appointmentData['dentist_name'] ?? 'our dentist';
        
        $dateFormatted = date('F j, Y', strtotime($appointmentDate));
        $timeFormatted = date('g:i A', strtotime($appointmentTime));
        
        $message = "Hi $patientName! Your dental appointment (ID: $appointmentId) at Cosmo Smiles Dental with $dentistName has been confirmed for $dateFormatted at $timeFormatted. Please arrive 30 to 60 minutes early. Thank You!";
        
        return $this->sendSMS($patientPhone, $message);
    }
    
    /**
     * Send appointment cancellation SMS
     */
    public function sendAppointmentCancellation($appointmentData, $reason = '') {
        $patientName = $appointmentData['patient_full_name'];
        $appointmentDate = $appointmentData['appointment_date'];
        $appointmentTime = $appointmentData['appointment_time'];
        $patientPhone = $appointmentData['patient_phone'];
        $appointmentId = $appointmentData['appointment_id'];
        
        $dateFormatted = date('F j, Y', strtotime($appointmentDate));
        $timeFormatted = date('g:i A', strtotime($appointmentTime));
        
        $message = "Hi $patientName! Your dental appointment (ID: $appointmentId) at Cosmo Smiles Dental on $dateFormatted at $timeFormatted has been cancelled.";
        
        if (!empty($reason)) {
            $message .= " Reason: $reason";
        }
        
        $message .= " Please contact us at (02) 123-4567 to reschedule. Reply STOP to unsubscribe.";
        
        return $this->sendSMS($patientPhone, $message);
    }
}

class StaffAppointmentController {
    private $db;
    private $conn;
    private $smsService;
    private $staffName = 'Staff';
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        $this->smsService = new TextBeeSMSService();
        
        // Get staff name from session if available
        if (isset($_SESSION['staff_name'])) {
            $this->staffName = $_SESSION['staff_name'];
        } elseif (isset($_SESSION['staff_id'])) {
            // Try to get name from DB if not in session
            $stmt = $this->conn->prepare("SELECT first_name, last_name FROM staff_users WHERE id = ?");
            $stmt->execute([$_SESSION['staff_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $this->staffName = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['staff_name'] = $this->staffName;
            }
        }
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
    
    public function resolveServiceNames($serviceIdsCsv) {
        if (empty($serviceIdsCsv)) return 'Dental Service';
        
        // If it's already a single ID (numeric), handle it
        if (is_numeric($serviceIdsCsv)) {
            $ids = [$serviceIdsCsv];
        } else {
            $ids = explode(',', $serviceIdsCsv);
            $ids = array_filter(array_map('trim', $ids));
        }
        
        if (empty($ids)) return 'Dental Service';
        
        try {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $query = "SELECT name, price, duration_minutes FROM services WHERE id IN ($placeholders)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(array_values($ids));
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $names = [];
            $totalPrice = 0;
            $totalDuration = 0;
            
            foreach ($services as $s) {
                $names[] = $s['name'];
                $totalPrice += $s['price'];
                $totalDuration += $s['duration_minutes'];
            }
            
            // For backward compatibility return string if only name is needed, 
            // but we'll use the array version in our own code
            return !empty($names) ? implode(', ', $names) : 'Dental Service';
        } catch (PDOException $e) {
            error_log("Error resolving service names: " . $e->getMessage());
            return 'Dental Service';
        }
    }
    
    // Helper to get full service details
    public function resolveServiceDetails($serviceIdsCsv) {
        if (empty($serviceIdsCsv)) return ['name' => 'Dental Service', 'price' => 0, 'duration' => 30];
        
        if (is_numeric($serviceIdsCsv)) {
            $ids = [$serviceIdsCsv];
        } else {
            $ids = explode(',', $serviceIdsCsv);
            $ids = array_filter(array_map('trim', $ids));
        }
        
        if (empty($ids)) return ['name' => 'Dental Service', 'price' => 0, 'duration' => 30];
        
        try {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $query = "SELECT name, price, duration_minutes FROM services WHERE id IN ($placeholders)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(array_values($ids));
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $names = [];
            $totalPrice = 0;
            $totalDuration = 0;
            
            foreach ($services as $s) {
                $names[] = $s['name'];
                $totalPrice += $s['price'];
                $totalDuration += ($s['duration_minutes'] ?? 30);
            }
            
            return [
                'name' => !empty($names) ? implode(', ', $names) : 'Dental Service',
                'price' => $totalPrice,
                'duration' => $totalDuration
            ];
        } catch (PDOException $e) {
            return ['name' => 'Dental Service', 'price' => 0, 'duration' => 30];
        }
    }

    // Get ALL appointments from database with search functionality
    public function getAllAppointments($filters = []) {
        try {
            $query = "SELECT 
                a.appointment_id,
                a.id as db_id,
                a.client_id as patient_client_id,
                a.*,
                CONCAT('Dr. ', d.first_name, ' ', d.last_name) as dentist_name,
                CONCAT(a.patient_first_name, ' ', a.patient_last_name) as patient_full_name,
                a.patient_phone,
                a.patient_email,
                a.payment_type,
                c.profile_image as patient_image
            FROM appointments a
            LEFT JOIN dentists d ON a.dentist_id = d.id
            LEFT JOIN clients c ON (a.client_id = c.client_id OR (a.client_id REGEXP '^[0-9]+$' AND a.client_id = c.id))
            WHERE 1=1";

            $params = [];

            // Apply search filter
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $query .= " AND (
                    a.appointment_id LIKE ? OR 
                    a.client_id LIKE ? OR
                    CONCAT(a.patient_first_name, ' ', a.patient_last_name) LIKE ? OR
                    a.patient_phone LIKE ? OR
                    a.patient_email LIKE ? OR
                    CONCAT(d.first_name, ' ', d.last_name) LIKE ?
                )";
                $params = array_merge($params, 
                    [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]
                );
            }

            // Apply filters - ONLY if not 'all'
            if (!empty($filters['date_range']) && $filters['date_range'] !== 'all') {
                switch($filters['date_range']) {
                    case 'today':
                        $query .= " AND a.appointment_date = CURDATE()";
                        break;
                    case 'tomorrow':
                        $query .= " AND a.appointment_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
                        break;
                    case 'this-week':
                        $query .= " AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                        break;
                    case 'next-week':
                        $query .= " AND a.appointment_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)";
                        break;
                    case 'this-month':
                        $query .= " AND MONTH(a.appointment_date) = MONTH(CURDATE()) AND YEAR(a.appointment_date) = YEAR(CURDATE())";
                        break;
                }
            }

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $query .= " AND a.status = ?";
                $params[] = $filters['status'];
            } else if (empty($filters['status']) || $filters['status'] === 'all') {
                $hideNoShow = $filters['hide_no_show'] ?? 'false';
                if ($hideNoShow === 'true') {
                    $query .= " AND a.status != 'no_show'";
                }
            }

            if (!empty($filters['dentist_id']) && $filters['dentist_id'] !== 'all') {
                $query .= " AND a.dentist_id = ?";
                $params[] = $filters['dentist_id'];
            }

            // Order by date and time (newest first)
            $query .= " ORDER BY a.created_at DESC, a.id DESC";

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
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Resolve multiple service names
            foreach ($appointments as &$appointment) {
                $serviceDetails = $this->resolveServiceDetails($appointment['service_id']);
                $appointment['service_name'] = $serviceDetails['name'];
                $appointment['service'] = $serviceDetails['name'];
                $appointment['service_price'] = $serviceDetails['price'];
                $appointment['duration'] = $appointment['duration_minutes'] ?? $serviceDetails['duration'];
            }
            unset($appointment);

            // Get total count for pagination
            $total = $this->getAppointmentsCount($filters);

            return [
                'appointments' => $appointments,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ];

        } catch (Exception $e) {
            error_log("Error getting appointments: " . $e->getMessage());
            return ['appointments' => [], 'total' => 0, 'page' => 1, 'limit' => 10];
        }
    }

    // Get total count of appointments with filters
    private function getAppointmentsCount($filters) {
        try {
            $query = "SELECT COUNT(*) as total FROM appointments a 
                     LEFT JOIN dentists d ON a.dentist_id = d.id
                     WHERE 1=1";
            $params = [];

            // Apply search filter
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $query .= " AND (
                    a.appointment_id LIKE ? OR 
                    a.client_id LIKE ? OR
                    CONCAT(a.patient_first_name, ' ', a.patient_last_name) LIKE ? OR
                    a.patient_phone LIKE ? OR
                    a.patient_email LIKE ? OR
                    CONCAT(d.first_name, ' ', d.last_name) LIKE ?
                )";
                $params = array_merge($params, 
                    [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]
                );
            }

            // Apply same filters as above
            if (!empty($filters['date_range']) && $filters['date_range'] !== 'all') {
                switch($filters['date_range']) {
                    case 'today':
                        $query .= " AND a.appointment_date = CURDATE()";
                        break;
                    case 'tomorrow':
                        $query .= " AND a.appointment_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
                        break;
                    case 'this-week':
                        $query .= " AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                        break;
                    case 'next-week':
                        $query .= " AND a.appointment_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)";
                        break;
                    case 'this-month':
                        $query .= " AND MONTH(a.appointment_date) = MONTH(CURDATE()) AND YEAR(a.appointment_date) = YEAR(CURDATE())";
                        break;
                }
            }

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $query .= " AND a.status = ?";
                $params[] = $filters['status'];
            } else if (empty($filters['status']) || $filters['status'] === 'all') {
                $hideNoShow = $filters['hide_no_show'] ?? 'false';
                if ($hideNoShow === 'true') {
                    $query .= " AND a.status != 'no_show'";
                }
            }

            if (!empty($filters['dentist_id']) && $filters['dentist_id'] !== 'all') {
                $query .= " AND a.dentist_id = ?";
                $params[] = $filters['dentist_id'];
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
            error_log("Error getting appointments count: " . $e->getMessage());
            return 0;
        }
    }

    // Get appointment by ID with full details
    public function getAppointmentDetails($id) {
        try {
            $query = "SELECT 
                a.appointment_id,
                a.id as db_id,
                a.client_id as patient_client_id,
                a.*,
                CONCAT('Dr. ', d.first_name, ' ', d.last_name) as dentist_name,
                d.specialization as dentist_specialization,
                
                CONCAT(a.patient_first_name, ' ', a.patient_last_name) as patient_full_name,
                a.patient_phone,
                a.patient_email,
                f.rating as feedback_rating,
                f.feedback as feedback_text,
                f.created_at as feedback_date
            FROM appointments a
            LEFT JOIN dentists d ON a.dentist_id = d.id
            
            LEFT JOIN appointment_feedbacks f ON a.appointment_id = f.appointment_id
            WHERE a.id = ?";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointment) {
                error_log("Appointment not found with ID: " . $id);
                return null;
            }

            // Resolve multiple service details
            $serviceDetails = $this->resolveServiceDetails($appointment['service_id']);
            $appointment['service_name'] = $serviceDetails['name'];
            $appointment['service'] = $serviceDetails['name'];
            $appointment['service_price'] = $serviceDetails['price'];
            $appointment['duration'] = $appointment['duration_minutes'] ?? $serviceDetails['duration'];
            
            return $appointment;

        } catch (Exception $e) {
            error_log("Error getting appointment details for ID {$id}: " . $e->getMessage());
            return null;
        }
    }

    // Handle API request for appointment details
    public function handleAppointmentDetailsRequest() {
        // Check if staff is logged in
        if (!isset($_SESSION['staff_id']) || $_SESSION['staff_role'] !== 'receptionist') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid appointment ID required']);
            exit;
        }

        $appointmentId = intval($_GET['id']);
        $appointment = $this->getAppointmentDetails($appointmentId);

        if ($appointment) {
            echo json_encode([
                'success' => true, 
                'appointment' => $appointment
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false, 
                'message' => 'Appointment not found'
            ]);
        }
        exit;
    }

    // Handle booked slots API request for time slot management
    public function handleBookedSlotsRequest() {
        // Check if staff is logged in
        if (!isset($_SESSION['staff_id']) || $_SESSION['staff_role'] !== 'receptionist') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        if (ob_get_length()) ob_clean(); header('Content-Type: application/json');
        
        try {
            $date = $_GET['date'] ?? '';
            $dentistId = $_GET['dentist_id'] ?? '';
            $excludeId = $_GET['exclude_id'] ?? null;
            
            if (empty($date) || empty($dentistId)) {
                echo json_encode(['success' => false, 'message' => 'Date and dentist ID are required']);
                exit;
            }
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                echo json_encode(['success' => false, 'message' => 'Invalid date format']);
                exit;
            }
            
            // Get ALL appointments (including pending) for the selected date and dentist
            $sql = "SELECT appointment_time FROM appointments 
                    WHERE appointment_date = ? 
                    AND dentist_id = ? 
                    AND status NOT IN ('cancelled', 'no_show')";
            
            $params = [$date, $dentistId];
            
            // Exclude current appointment when editing
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $bookedSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'bookedSlots' => $bookedSlots
            ]);
            
        } catch (PDOException $e) {
            error_log("Error fetching booked slots: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }

    /**
     * Handle AJAX request to fetch all appointments with filters
     */
    public function handleFetchAllRequest() {
        if (!isset($_SESSION['staff_id']) || $_SESSION['staff_role'] !== 'receptionist') {
            if (ob_get_length()) ob_clean(); header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        if (ob_get_length()) ob_clean(); header('Content-Type: application/json');
        
        try {
            $filters = [
                'date_range' => $_GET['date_range'] ?? 'all',
                'status' => $_GET['status'] ?? 'all',
                'dentist_id' => $_GET['dentist_id'] ?? 'all',
                'search' => $_GET['search'] ?? '',
                'page' => $_GET['page'] ?? 1,
                'hide_no_show' => $_GET['hide_no_show'] ?? 'false'
            ];

            $result = $this->getAllAppointments($filters);
            
            echo json_encode([
                'success' => true,
                'appointments' => $result['appointments'],
                'total' => $result['total'],
                'page' => $result['page'],
                'limit' => $result['limit']
            ]);
            exit;
        } catch (Exception $e) {
            error_log("Error in handleFetchAllRequest: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error fetching appointments']);
            exit;
        }
    }

    // Generate appointment ID USING THE APPOINTMENT DATE (not current date)
    private function generateAppointmentId($appointmentDate) {
        $prefix = 'CSD';
        
        // Parse the appointment date to get year, month, day
        $dateObj = new DateTime($appointmentDate);
        $year = $dateObj->format('Y');
        $month = $dateObj->format('m');
        $day = $dateObj->format('d');
        
        $datePrefix = $prefix . $year . $month . $day;
        
        // Get last appointment number for the appointment date
        $query = "SELECT appointment_id FROM appointments 
                 WHERE appointment_id LIKE ? 
                 ORDER BY appointment_id DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $datePrefix . '%', PDO::PARAM_STR);
        $stmt->execute();
        $lastAppointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastAppointment) {
            // Extract the last 3 digits from the ID (CSD20251217001 -> 001)
            $lastNumber = intval(substr($lastAppointment['appointment_id'], -3));
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }
        
        return $datePrefix . $newNumber;
    }

    // CREATE - Create new appointment (UPDATED for VARCHAR client_id)
    public function createAppointment($data) {
        try {
            // Sanitize inputs
            $data = $this->sanitizeInputArray($data);
            
            // Validate required fields
            $requiredFields = ['client_id', 'dentist_id', 'service_id', 'appointment_date', 'appointment_time', 'patient_first_name', 'patient_last_name', 'patient_phone', 'patient_email'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    error_log("Missing required field for create: " . $field);
                    return false;
                }
            }

            // Validate date and time with hour restrictions
            $dateValidation = $this->validateAppointmentDateTime($data['appointment_date'], $data['appointment_time']);
            if (!$dateValidation['valid']) {
                error_log("Invalid date/time: " . $dateValidation['message']);
                return false;
            }

            // Check for existing appointment at same time
            if ($this->isTimeSlotBooked($data['appointment_date'], $data['appointment_time'], $data['dentist_id'])) {
                error_log("Time slot already booked: " . $data['appointment_date'] . " " . $data['appointment_time'] . " for dentist " . $data['dentist_id']);
                return false;
            }

            // Check if client exists by client_id (VARCHAR)
            if (!$this->clientExists($data['client_id'])) {
                error_log("Patient not found with client_id: " . $data['client_id']);
                return false;
            }

            // Generate appointment ID USING THE APPOINTMENT DATE
            $appointmentId = $this->generateAppointmentId($data['appointment_date']);

            $query = "INSERT INTO appointments SET 
                appointment_id = ?,
                client_id = ?,
                dentist_id = ?, 
                service_id = ?, 
                appointment_date = ?, 
                appointment_time = ?, 
                status = ?, 
                notes = ?, 
                duration_minutes = ?,
                patient_first_name = ?,
                patient_last_name = ?,
                patient_phone = ?,
                patient_email = ?,
                payment_type = ?,
                service_price = ?,
                created_at = NOW(),
                updated_at = NOW()";

            $stmt = $this->conn->prepare($query);
            
            // Handle 'any' dentist selection
            $dentistId = $data['dentist_id'];
            if ($dentistId === 'any') {
                $dentistsList = $this->getDentists();
                $availableDentists = array_filter($dentistsList, function($d) {
                    return isset($d['is_checked_in']) && $d['is_checked_in'] == 1;
                });
                
                if (!empty($availableDentists)) {
                    $randomDentist = $availableDentists[array_rand($availableDentists)];
                    $dentistId = $randomDentist['id'];
                    error_log("Automatically assigned 'any' dentist to: " . $dentistId);
                } else {
                    error_log("No available dentists for 'any' assignment");
                    return false;
                }
            }

            // Handle multiple services
            if (is_array($data['service_id'])) {
                $serviceIds = $data['service_id'];
            } else {
                $serviceIds = array_filter(array_map('trim', explode(',', (string)$data['service_id'])));
            }
            $serviceIds = array_unique($serviceIds);

            $serviceNames = [];
            $totalPrice = 0;
            $totalDuration = 0;

            foreach ($serviceIds as $sId) {
                $serviceQuery = "SELECT name, price, duration_minutes FROM services WHERE id = ?";
                $sStmt = $this->conn->prepare($serviceQuery);
                $sStmt->execute([$sId]);
                $service = $sStmt->fetch(PDO::FETCH_ASSOC);
                if ($service) {
                    $serviceNames[] = $service['name'];
                    $totalPrice += $service['price'];
                    $totalDuration += $service['duration_minutes'];
                }
            }

            $serviceIdString = implode(',', $serviceIds);
            $serviceNameString = implode(', ', $serviceNames);

            $stmt->bindValue(1, $appointmentId, PDO::PARAM_STR);
            $stmt->bindValue(2, $data['client_id'], PDO::PARAM_STR);
            $stmt->bindValue(3, intval($dentistId), PDO::PARAM_INT);
            $stmt->bindValue(4, $serviceIdString, PDO::PARAM_STR);
            $stmt->bindValue(5, $data['appointment_date'], PDO::PARAM_STR);
            $stmt->bindValue(6, $data['appointment_time'], PDO::PARAM_STR);
            $stmt->bindValue(7, $data['status'] ?? 'pending', PDO::PARAM_STR);
            $stmt->bindValue(8, $data['notes'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(9, intval($totalDuration), PDO::PARAM_INT);
            $stmt->bindValue(10, $data['patient_first_name'], PDO::PARAM_STR);
            $stmt->bindValue(11, $data['patient_last_name'], PDO::PARAM_STR);
            $stmt->bindValue(12, $data['patient_phone'], PDO::PARAM_STR);
            $stmt->bindValue(13, $data['patient_email'], PDO::PARAM_STR);
            $stmt->bindValue(14, $data['payment_type'] ?? 'cash', PDO::PARAM_STR);
            $stmt->bindValue(15, floatval($totalPrice));
            
            $result = $stmt->execute();
            
            if ($result) {
                $dbId = $this->conn->lastInsertId();
                error_log("Appointment created successfully: " . $appointmentId . " (DB ID: " . $dbId . ")");
                
                // Send SMS if status is confirmed
                if (($data['status'] ?? 'pending') === 'confirmed') {
                    $appointmentData = $this->getAppointmentDetails($dbId);
                    if ($appointmentData) {
                        $smsSent = $this->smsService->sendAppointmentConfirmation($appointmentData);
                        error_log("SMS notification for new appointment: " . ($smsSent ? "Sent" : "Failed"));
                    }
                }
                
                return true;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error creating appointment: " . print_r($errorInfo, true));
                return false;
            }

        } catch (Exception $e) {
            error_log("Error creating appointment: " . $e->getMessage());
            return false;
        }
    }

    // Check if time slot is already booked
    private function isTimeSlotBooked($date, $time, $dentistId, $excludeId = null) {
        try {
            $query = "SELECT COUNT(*) as count FROM appointments 
                     WHERE appointment_date = ? 
                     AND appointment_time = ? 
                     AND dentist_id = ?
                     AND status NOT IN ('cancelled', 'no_show')";
            
            $params = [$date, $time, $dentistId];
            
            if ($excludeId) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return ($row['count'] > 0);
        } catch (Exception $e) {
            error_log("Error checking time slot: " . $e->getMessage());
            return false;
        }
    }

    // Check if client exists by client_id (VARCHAR)
    private function clientExists($clientId) {
        try {
            $query = "SELECT COUNT(*) as count FROM clients WHERE client_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $clientId, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($row['count'] > 0);
        } catch (Exception $e) {
            error_log("Error checking if client exists: " . $e->getMessage());
            return false;
        }
    }

    // Get client by client_id for form
    public function getClientDetails($clientId) {
        try {
            $query = "SELECT id, client_id, first_name, last_name, phone, email FROM clients WHERE client_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $clientId, PDO::PARAM_STR);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting client details: " . $e->getMessage());
            return null;
        }
    }

    // UPDATE - Edit appointment details
    public function updateAppointment($id, $data) {
        try {
            error_log("Attempting to update appointment ID: {$id}");
            
            // Get current appointment details BEFORE update for SMS comparison
            $oldAppointmentDetails = $this->getAppointmentDetails($id);
            $oldStatus = $oldAppointmentDetails['status'] ?? 'pending';
            
            // Sanitize inputs
            $data = $this->sanitizeInputArray($data);
            
            // Validate required fields
            $requiredFields = ['client_id', 'dentist_id', 'service_id', 'appointment_date', 'appointment_time', 'status'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    error_log("Missing required field for update: " . $field);
                    return false;
                }
            }

            // Validate date and time with hour restrictions
            $dateValidation = $this->validateAppointmentDateTime($data['appointment_date'], $data['appointment_time']);
            if (!$dateValidation['valid']) {
                error_log("Invalid date/time: " . $dateValidation['message']);
                return false;
            }

            // Check for existing appointment at same time (exclude current appointment)
            if ($this->isTimeSlotBooked($data['appointment_date'], $data['appointment_time'], $data['dentist_id'], $id)) {
                error_log("Time slot already booked: " . $data['appointment_date'] . " " . $data['appointment_time'] . " for dentist " . $data['dentist_id']);
                return false;
            }

            // Check if client exists by client_id (VARCHAR)
            if (!$this->clientExists($data['client_id'])) {
                error_log("Client not found with client_id: " . $data['client_id']);
                return false;
            }

            $query = "UPDATE appointments SET 
                client_id = ?,
                dentist_id = ?, 
                service_id = ?, 
                appointment_date = ?, 
                appointment_time = ?, 
                status = ?, 
                notes = ?, 
                duration_minutes = ?,
                patient_first_name = ?,
                patient_last_name = ?,
                patient_phone = ?,
                patient_email = ?,
                payment_type = ?,
                service_price = ?,
                updated_at = NOW()
                WHERE id = ?";

            $stmt = $this->conn->prepare($query);
            
            // Handle multiple services
            if (is_array($data['service_id'])) {
                $serviceIds = $data['service_id'];
            } else {
                $serviceIds = array_filter(array_map('trim', explode(',', (string)$data['service_id'])));
            }
            $serviceIds = array_unique($serviceIds);

            $serviceNames = [];
            $totalPrice = 0;
            $totalDuration = 0;

            foreach ($serviceIds as $sId) {
                $serviceQuery = "SELECT name, price, duration_minutes FROM services WHERE id = ?";
                $sStmt = $this->conn->prepare($serviceQuery);
                $sStmt->execute([$sId]);
                $service = $sStmt->fetch(PDO::FETCH_ASSOC);
                if ($service) {
                    $serviceNames[] = $service['name'];
                    $totalPrice += $service['price'];
                    $totalDuration += $service['duration_minutes'];
                }
            }

            $serviceIdString = implode(',', $serviceIds);
            $serviceNameString = implode(', ', $serviceNames);

            // Bind parameters
            $stmt->bindValue(1, $data['client_id'], PDO::PARAM_STR);
            $stmt->bindValue(2, intval($data['dentist_id']), PDO::PARAM_INT);
            $stmt->bindValue(3, $serviceIdString, PDO::PARAM_STR);
            $stmt->bindValue(4, $data['appointment_date'], PDO::PARAM_STR);
            $stmt->bindValue(5, $data['appointment_time'], PDO::PARAM_STR);
            $stmt->bindValue(6, $data['status'], PDO::PARAM_STR);
            $stmt->bindValue(7, $data['notes'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(8, intval($totalDuration), PDO::PARAM_INT);
            $stmt->bindValue(9, $data['patient_first_name'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(10, $data['patient_last_name'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(11, $data['patient_phone'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(12, $data['patient_email'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(13, $data['payment_type'] ?? 'cash', PDO::PARAM_STR);
            $stmt->bindValue(14, floatval($totalPrice), PDO::PARAM_STR);
            $stmt->bindValue(15, intval($id), PDO::PARAM_INT);
            
            $result = $stmt->execute();
            
            if ($result) {
                $rowCount = $stmt->rowCount();
                error_log("Appointment updated successfully: " . $id . " (Rows affected: " . $rowCount . ")");
                
                // Audit Logging - Tracker specific changes
                $changes = [];
                $fieldsToTrack = [
                    'dentist_id' => 'Dentist',
                    'service_id' => 'Service',
                    'appointment_date' => 'Date',
                    'appointment_time' => 'Time',
                    'status' => 'Status',
                    'notes' => 'Notes',
                    'patient_phone' => 'Phone',
                    'service_price' => 'Price'
                ];

                foreach ($fieldsToTrack as $field => $label) {
                    $oldVal = $oldAppointmentDetails[$field] ?? '';
                    $newVal = $data[$field] ?? '';
                    if ($oldVal != $newVal) {
                        $changes[] = "{$label}: '{$oldVal}' -> '{$newVal}'";
                    }
                }

                if (!empty($changes)) {
                    $auditLog = "\n--- APPOINTMENT MODIFIED ---\n";
                    $auditLog .= "Modified by: " . $this->staffName . " (Staff)\n";
                    $auditLog .= "Date: " . date('F j, Y h:i A') . "\n";
                    $auditLog .= "Changes:\n  - " . implode("\n  - ", $changes) . "\n";
                    $auditLog .= "----------------------------\n";
                    
                    $updateAuditQuery = "UPDATE appointments SET admin_notes = CONCAT(IFNULL(admin_notes, ''), ?) WHERE id = ?";
                    $auditStmt = $this->conn->prepare($updateAuditQuery);
                    $auditStmt->execute([$auditLog, intval($id)]);
                }

                // Send SMS if status changed to confirmed, cancelled, or no_show
                $newStatus = $data['status'];
                if ($oldStatus !== $newStatus && in_array($newStatus, ['confirmed', 'cancelled', 'no_show'])) {
                    $updatedAppointmentDetails = $this->getAppointmentDetails($id);
                    if ($updatedAppointmentDetails) {
                        $this->sendAppointmentStatusSMS($updatedAppointmentDetails, $newStatus, $oldStatus);
                    }
                }
                
                return true;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error updating appointment: " . print_r($errorInfo, true));
                return false;
            }

        } catch (Exception $e) {
            error_log("Error updating appointment ID {$id}: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    // UPDATE - Just update status with SMS notification
    public function updateAppointmentStatus($id, $status) {
        try {
            error_log("Attempting to update appointment ID: {$id} to status: {$status}");
            
            // Validate status
            $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
            if (!in_array($status, $validStatuses)) {
                error_log("Invalid status: " . $status);
                return false;
            }

            // First get current appointment details BEFORE update
            $appointmentDetails = $this->getAppointmentDetails($id);
            
            if (!$appointmentDetails) {
                error_log("Appointment not found with ID: " . $id);
                return false;
            }
            
            $oldStatus = $appointmentDetails['status'];
            
            // If status is the same, no need to update or log
            if ($oldStatus === $status) {
                return true;
            }
            
            $query = "UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $status, PDO::PARAM_STR);
            $stmt->bindValue(2, intval($id), PDO::PARAM_INT);
            
            $result = $stmt->execute();
            
            if ($result) {
                // Add status update note to admin_notes ONLY (never client notes/general notes)
                $statusNote = "\n--- STATUS UPDATE ---\n";
                $statusNote .= "Status changed to: " . strtoupper($status) . "\n";
                $statusNote .= "Changed by: " . $this->staffName . " (Staff)\n";
                $statusNote .= "Changed on: " . date('F j, Y h:i A') . "\n";
                $statusNote .= "---------------------\n";
                
                $updateNotesQuery = "UPDATE appointments SET admin_notes = CONCAT(IFNULL(admin_notes, ''), ?) WHERE id = ?";
                $notesStmt = $this->conn->prepare($updateNotesQuery);
                $notesStmt->execute([$statusNote, intval($id)]);

                // Send SMS notification if status changed to confirmed/cancelled/no_show
                if (in_array($status, ['confirmed', 'cancelled', 'no_show'])) {
                    $this->sendAppointmentStatusSMS($appointmentDetails, $status, $oldStatus);
                }
                
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Exception updating appointment status for ID {$id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send SMS notification for appointment status changes
     */
    private function sendAppointmentStatusSMS($appointmentData, $newStatus, $oldStatus) {
        try {
            // Check if patient has a valid phone number
            $patientPhone = $appointmentData['patient_phone'] ?? '';
            
            if (empty($patientPhone)) {
                error_log("No phone number found for patient: " . $appointmentData['patient_full_name']);
                return false;
            }
            
            // Send appropriate SMS based on status
            switch ($newStatus) {
                case 'confirmed':
                    error_log("Sending confirmation SMS for appointment: " . $appointmentData['appointment_id']);
                    return $this->smsService->sendAppointmentConfirmation($appointmentData);
                    
                case 'cancelled':
                    error_log("Sending cancellation SMS for appointment: " . $appointmentData['appointment_id']);
                    return $this->smsService->sendAppointmentCancellation($appointmentData, 'By clinic request');
                    
                case 'no_show':
                    error_log("Sending no-show SMS for appointment: " . $appointmentData['appointment_id']);
                    return $this->smsService->sendAppointmentCancellation($appointmentData, 'Marked as no-show');
                    
                default:
                    error_log("No SMS notification needed for status change to: {$newStatus}");
                    return true;
            }
            
        } catch (Exception $e) {
            error_log("Error sending appointment status SMS: " . $e->getMessage());
            return false;
        }
    }

    // Get available dentists for dropdown
    public function getDentists() {
        try {
            $query = "SELECT id, CONCAT(first_name, ' ', last_name) as name, specialization, is_checked_in 
                     FROM dentists 
                     WHERE is_active = 1 
                     ORDER BY first_name, last_name";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting dentists: " . $e->getMessage());
            return [];
        }
    }

    // Get available services for dropdown
    public function getServices() {
        try {
            $query = "SELECT id, name, price, duration_minutes 
                     FROM services 
                     WHERE is_active = 1 
                     ORDER BY name";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting services: " . $e->getMessage());
            return [];
        }
    }

    // Get all clients for dropdown
    public function getAllClients() {
        try {
            $query = "SELECT client_id, CONCAT(first_name, ' ', last_name) as full_name, phone, email 
                     FROM clients 
                     ORDER BY client_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting clients: " . $e->getMessage());
            return [];
        }
    }

    // Check if appointment exists
    public function appointmentExists($id) {
        try {
            $query = "SELECT COUNT(*) as count FROM appointments WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, intval($id), PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($row['count'] > 0);
        } catch (Exception $e) {
            error_log("Error checking if appointment exists: " . $e->getMessage());
            return false;
        }
    }

    // Get appointment statistics for dashboard
    public function getAppointmentStats() {
        try {
            $query = "SELECT 
                status,
                COUNT(*) as count
            FROM appointments 
            WHERE appointment_date >= CURDATE()
            GROUP BY status";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $formattedStats = [];
            foreach ($stats as $stat) {
                $formattedStats[$stat['status']] = $stat['count'];
            }
            
            return $formattedStats;
        } catch (Exception $e) {
            error_log("Error getting appointment stats: " . $e->getMessage());
            return [];
        }
    }

    // Get today's appointments
    public function getTodaysAppointments() {
        try {
            $query = "SELECT 
                a.*,
                CONCAT('Dr. ', d.first_name, ' ', d.last_name) as dentist_name,
                a.patient_first_name,
                a.patient_last_name,
                a.patient_phone,
                a.patient_email
            FROM appointments a 
            LEFT JOIN dentists d ON a.dentist_id = d.id
            WHERE a.appointment_date = CURDATE()
            ORDER BY a.appointment_time ASC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting today's appointments: " . $e->getMessage());
            return [];
        }
    }

    // Check date validation - no Sundays, no past dates, Saturday hours 9am-3pm INCLUDING 3PM, only hourly slots
    // Weekdays: 8am-6pm INCLUDING 6PM
    public function validateAppointmentDateTime($date, $time) {
        try {
            // Get current date and time in server timezone
            $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
            $selectedDateTime = new DateTime($date . ' ' . $time, new DateTimeZone('Asia/Manila'));
            
            // Check if selected date/time is in the past
            if ($selectedDateTime < $now) {
                return ['valid' => false, 'message' => 'Cannot book appointments in the past. Selected time has already passed.'];
            }
            
            // Check if it's Sunday
            $dayOfWeek = $selectedDateTime->format('w');
            if ($dayOfWeek == 0) { // 0 = Sunday
                return ['valid' => false, 'message' => 'Sundays are unavailable'];
            }
            
            // Check if time is on the hour (not half hours)
            $minute = $selectedDateTime->format('i');
            if ($minute != '00') {
                return ['valid' => false, 'message' => 'Appointments are only available on the hour (e.g., 8:00, 9:00, 10:00)'];
            }
            
            // Get hour
            $hour = $selectedDateTime->format('H');
            
            // Check if hour is within valid range (8am-6pm) INCLUDING 6PM
            if ($hour < 8 || $hour > 18) {
                return ['valid' => false, 'message' => 'Appointments are only available from 8:00 AM to 6:00 PM'];
            }
            
            // Check Saturday hours (9am-3pm) INCLUDING 3PM
            if ($dayOfWeek == 6) { // 6 = Saturday
                if ($hour < 9 || $hour > 15) {
                    return ['valid' => false, 'message' => 'Saturday appointments are only available from 9:00 AM to 3:00 PM'];
                }
            }
            
            return ['valid' => true, 'message' => 'Date and time are valid'];
            
        } catch (Exception $e) {
            error_log("Error validating appointment date/time: " . $e->getMessage());
            return ['valid' => false, 'message' => 'Error validating date and time'];
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

    // Handle get dentists API request
    public function handleGetDentistsRequest() {
        if (ob_get_length()) ob_clean(); header('Content-Type: application/json');
        try {
            $dentists = $this->getDentists();
            echo json_encode(['success' => true, 'dentists' => $dentists]);
        } catch (Exception $e) {
            error_log("Error getting dentists for API: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error loading dentists']);
        }
        exit;
    }

    // Handle get services API request
    public function handleGetServicesRequest() {
        if (ob_get_length()) ob_clean(); header('Content-Type: application/json');
        try {
            $services = $this->getServices();
            echo json_encode(['success' => true, 'services' => $services]);
        } catch (Exception $e) {
            error_log("Error getting services for API: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error loading services']);
        }
        exit;
    }
}
