<?php
namespace Controllers;

require_once __DIR__ . '/../../config/env.php';

class PatientRecordsController {
    private $pdo;
    private $upload_dir;
    private $allowed_file_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];
    private $max_file_size = 10 * 1024 * 1024; // 10MB
    
    // SMS Configuration - VERIFIED WORKING
    private $sms_enabled;
    private $sms_base_url;
    private $sms_api_key;
    private $sms_device_id;
    private $sms_test_mode;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->upload_dir = __DIR__ . '/../../public/uploads/patient_records/';
        
        $this->sms_enabled = env('SMS_ENABLED', true);
        $this->sms_base_url = env('SMS_BASE_URL', 'https://api.textbee.dev/api/v1');
        $this->sms_api_key = env('SMS_API_KEY', '');
        $this->sms_device_id = env('SMS_DEVICE_ID', '');
        $this->sms_test_mode = env('SMS_TEST_MODE', false);
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    /**
     * Send SMS notification to patient - VERIFIED WORKING
     */
    private function sendSMSNotification($patient_id, $message, $record_type = null, $record_title = null) {
        // Check if SMS is enabled
        if (!$this->sms_enabled) {
            error_log("SMS notifications are disabled");
            return false;
        }
        
        try {
            // Get patient phone number and name
            $stmt = $this->pdo->prepare("
                SELECT phone, first_name, last_name 
                FROM clients 
                WHERE client_id = :client_id
            ");
            $stmt->execute([':client_id' => $patient_id]);
            $patient = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$patient || empty($patient['phone'])) {
                error_log("SMS Error: Patient not found or no phone number for ID: $patient_id");
                return false;
            }
            
            $phone_number = $patient['phone'];
            $patient_name = trim($patient['first_name'] . ' ' . $patient['last_name']);
            
            // Format phone number (remove spaces, dashes, etc.)
            $phone_number = preg_replace('/[^0-9]/', '', $phone_number);
            
            // Convert to international format: 09385278503 -> +639385278503
            if (strlen($phone_number) === 10) {
                $phone_number = '+63' . $phone_number;
            } elseif (strlen($phone_number) === 11 && substr($phone_number, 0, 2) === '09') {
                $phone_number = '+63' . substr($phone_number, 1);
            } else {
                // If it doesn't match expected formats, try to add +63
                if (substr($phone_number, 0, 2) !== '63' && substr($phone_number, 0, 3) !== '+63') {
                    $phone_number = '+63' . $phone_number;
                } elseif (substr($phone_number, 0, 2) === '63') {
                    $phone_number = '+' . $phone_number;
                }
            }
            
            // Prepare SMS message
            $sms_message = $this->formatSMSMessage($message, $patient_name, $record_type, $record_title);
            
            // In test mode, just log the SMS
            if ($this->sms_test_mode) {
                error_log("=== SMS TEST MODE ===");
                error_log("Would send to: $phone_number");
                error_log("Patient: $patient_name");
                error_log("Message: $sms_message");
                error_log("=== END SMS TEST ===");
                return true;
            }
            
            // CORRECT API ENDPOINT (verified working)
            $url = $this->sms_base_url . '/gateway/devices/' . $this->sms_device_id . '/send-sms';
            
            // CORRECT DATA FORMAT (verified working)
            $sms_data = [
                'recipients' => [$phone_number], // Array of recipients
                'message' => $sms_message
            ];
            
            // Send request using cURL
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sms_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'x-api-key: ' . $this->sms_api_key  // CORRECT HEADER
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 || $http_code === 201) {
                error_log("SMS sent successfully to $phone_number for patient $patient_id");
                return true;
            } else {
                error_log("SMS API Error - HTTP Code: $http_code, Response: $response");
                return false;
            }
            
        } catch (\Exception $e) {
            error_log("SMS Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Format SMS message based on record type - IMPROVED VERSION
     */
    private function formatSMSMessage($message, $patient_name, $record_type = null, $record_title = null) {
        $clinic_name = "Cosmo Smiles Dental";
        
        // Get time-based greeting
        $hour = date('H');
        if ($hour < 12) {
            $greeting = "Good morning";
        } elseif ($hour < 18) {
            $greeting = "Good afternoon";
        } else {
            $greeting = "Good evening";
        }
        
        // Customize message based on record type
        switch ($record_type) {
            case 'treatment':
                $type_info = "treatment record";
                break;
            case 'consultation':
                $type_info = "consultation record";
                break;
            case 'xray':
                $type_info = "X-ray results";
                break;
            case 'prescription':
                $type_info = "prescription";
                break;
            case 'followup':
                $type_info = "follow-up record";
                break;
            case 'emergency':
                $type_info = "emergency record";
                break;
            default:
                $type_info = "medical record";
        }
        
        // Simple and clear message with greeting
        $sms_message = "$greeting $patient_name. Your $type_info";
        
        if ($record_title) {
            $sms_message .= " ($record_title)";
        }
        
        $sms_message .= " is now available in your patient portal. Login to view details.";
        
        // Add clinic name
        $full_message = "$sms_message - $clinic_name";
        
        // Increased limit for multi-part SMS support
        if (strlen($full_message) > 1600) {
            $full_message = substr($full_message, 0, 1597) . "...";
        }
        
        return $full_message;
    }
    
    /**
     * Send SMS for new record creation - IMPROVED VERSION
     */
    private function sendRecordCreatedSMS($patient_id, $record_data) {
        $record_type = $record_data['record_type'] ?? null;
        $record_title = $record_data['record_title'] ?? null;
        
        // Get record ID and upload date from database
        $stmt = $this->pdo->prepare("
            SELECT record_id, DATE_FORMAT(created_at, '%M %d, %Y') as upload_date 
            FROM patient_records 
            WHERE client_id = :client_id 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([':client_id' => $patient_id]);
        $latest_record = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $record_id = $latest_record['record_id'] ?? 'N/A';
        $upload_date = $latest_record['upload_date'] ?? date('M d, Y');
        
        // Get time-based greeting
        $hour = date('H');
        if ($hour < 12) {
            $greeting = "Good morning";
        } elseif ($hour < 18) {
            $greeting = "Good afternoon";
        } else {
            $greeting = "Good evening";
        }
        
        // Create clear message with all required info
        $message = "$greeting. Your medical record is available in patient portal. ";
        $message .= "Patient ID: $patient_id, ";
        $message .= "Record ID: $record_id, ";
        $message .= "Uploaded: $upload_date. ";
        $message .= "Login to view details.";
        
        return $this->sendSMSNotification($patient_id, $message, $record_type, $record_title);
    }
    
    /**
     * Send SMS for record archival
     */
    private function sendRecordArchivedSMS($patient_id, $record_data, $reason = '') {
        $record_type = $record_data['record_type'] ?? null;
        $record_title = $record_data['record_title'] ?? null;
        
        // Get time-based greeting
        $hour = date('H');
        if ($hour < 12) {
            $greeting = "Good morning";
        } elseif ($hour < 18) {
            $greeting = "Good afternoon";
        } else {
            $greeting = "Good evening";
        }
        
        $message = "$greeting. A medical record has been archived";
        if ($reason) {
            $message .= " (Reason: $reason)";
        }
        $message .= ". Archived records are kept for reference but hidden from active view.";
        
        return $this->sendSMSNotification($patient_id, $message, $record_type, $record_title);
    }
    
    /**
     * Send SMS for record restoration
     */
    private function sendRecordRestoredSMS($patient_id, $record_data) {
        $record_type = $record_data['record_type'] ?? null;
        $record_title = $record_data['record_title'] ?? null;
        
        // Get time-based greeting
        $hour = date('H');
        if ($hour < 12) {
            $greeting = "Good morning";
        } elseif ($hour < 18) {
            $greeting = "Good afternoon";
        } else {
            $greeting = "Good evening";
        }
        
        $message = "$greeting. A previously archived record has been restored and is now active in your medical history.";
        
        return $this->sendSMSNotification($patient_id, $message, $record_type, $record_title);
    }
    
    /**
     * Validate and sanitize uploaded files
     */
    private function handleFileUploads($uploaded_files) {
        $saved_files = [];
        
        foreach ($uploaded_files as $key => $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            // Validate file size
            if ($file['size'] > $this->max_file_size) {
                throw new \Exception("File {$file['name']} exceeds 10MB limit");
            }
            
            // Validate file type
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!array_key_exists($file_ext, $this->allowed_file_types)) {
                throw new \Exception("File type not allowed: {$file['name']}");
            }
            
            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if ($mime_type !== $this->allowed_file_types[$file_ext]) {
                throw new \Exception("Invalid file type: {$file['name']}");
            }
            
            // Generate secure filename
            $safe_filename = uniqid('rec_', true) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $safe_filename = substr($safe_filename, 0, 255); // Limit filename length
            $destination = $this->upload_dir . $safe_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $saved_files[] = $safe_filename;
            }
        }
        
        return $saved_files;
    }
    
    /**
     * Delete files when record is deleted
     */
    private function deleteRecordFiles($files_json) {
        try {
            $files = json_decode($files_json, true);
            if (is_array($files)) {
                foreach ($files as $file) {
                    $file_path = $this->upload_dir . $file;
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail the operation
            error_log("Failed to delete files: " . $e->getMessage());
        }
    }
    
    public function getAdminName($admin_id) {
        try {
            // Validate admin_id
            if (!is_numeric($admin_id) || $admin_id <= 0) {
                return 'Unknown';
            }
            
            $stmt = $this->pdo->prepare("SELECT first_name, last_name FROM admin_users WHERE id = :id");
            $stmt->execute([':id' => $admin_id]);
            $admin = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($admin) {
                // Sanitize names before concatenation
                $first_name = htmlspecialchars($admin['first_name'], ENT_QUOTES, 'UTF-8');
                $last_name = htmlspecialchars($admin['last_name'], ENT_QUOTES, 'UTF-8');
                return 'Dr. ' . $first_name . ' ' . $last_name;
            }
            return 'Unknown';
        } catch (\PDOException $e) {
            error_log("Error getting admin name: " . $e->getMessage());
            return 'Unknown';
        }
    }
    
    public function generateRecordId() {
        $date = date('Ymd');
        
        try {
            // Use transaction with higher isolation level to prevent race conditions
            $this->pdo->setAttribute(\PDO::ATTR_AUTOCOMMIT, false);
            $this->pdo->beginTransaction();
            
            // Get the last used number for today
            $stmt = $this->pdo->prepare("
                SELECT MAX(CAST(SUBSTRING_INDEX(record_id, '-', -1) AS UNSIGNED)) as max_number 
                FROM patient_records 
                WHERE record_id LIKE :pattern
                AND DATE(created_at) = CURDATE()
                FOR UPDATE
            ");
            $stmt->execute([':pattern' => 'REC-' . $date . '-%']);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $next_number = ($result['max_number'] ?: 0) + 1;
            
            $this->pdo->commit();
            $this->pdo->setAttribute(\PDO::ATTR_AUTOCOMMIT, true);
            
            return 'REC-' . $date . '-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
                $this->pdo->setAttribute(\PDO::ATTR_AUTOCOMMIT, true);
            }
            error_log("Error generating record ID: " . $e->getMessage());
            // Fallback with microtime and random string for uniqueness
            $random = bin2hex(random_bytes(3));
            return 'REC-' . $date . '-' . substr(str_replace('.', '', microtime(true)), -6) . '-' . $random;
        }
    }
    
    /**
     * Alternative ID generation method with retry logic
     */
    private function generateUniqueRecordId($max_attempts = 5) {
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            try {
                $record_id = $this->generateRecordId();
                
                // Check if this ID already exists
                $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM patient_records WHERE record_id = :record_id");
                $stmt->execute([':record_id' => $record_id]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($result['count'] == 0) {
                    return $record_id;
                }
                
                // Wait a bit before retry
                usleep(100000); // 0.1 second
            } catch (\Exception $e) {
                error_log("Attempt $attempt failed to generate unique ID: " . $e->getMessage());
            }
        }
        
        // If all attempts fail, use a UUID-based ID
        $date = date('Ymd');
        $uuid = bin2hex(random_bytes(8));
        return 'REC-' . $date . '-UUID-' . $uuid;
    }
    
    public function getServiceDuration($procedure_name) {
        try {
            // Validate input
            $procedure_name = trim($procedure_name);
            if (empty($procedure_name)) {
                return '';
            }
            
            $stmt = $this->pdo->prepare("
                SELECT duration_minutes 
                FROM services 
                WHERE name = :procedure_name 
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([':procedure_name' => $procedure_name]);
            $service = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($service && isset($service['duration_minutes']) && is_numeric($service['duration_minutes'])) {
                $minutes = (int)$service['duration_minutes'];
                
                // Convert minutes to readable format
                if ($minutes <= 30) {
                    return '30 minutes';
                } else if ($minutes <= 45) {
                    return '45 minutes';
                } else if ($minutes <= 60) {
                    return '1 hour';
                } else if ($minutes <= 75) {
                    return '1 hour 15 minutes';
                } else if ($minutes <= 90) {
                    return '1 hour 30 minutes';
                } else if ($minutes <= 120) {
                    return '2 hours';
                } else {
                    $hours = floor($minutes / 60);
                    $remainingMinutes = $minutes % 60;
                    if ($remainingMinutes > 0) {
                        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ' . $remainingMinutes . ' minutes';
                    }
                    return $hours . ' hour' . ($hours > 1 ? 's' : '');
                }
            }
            
            return '';
        } catch (\PDOException $e) {
            error_log("Error getting service duration: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Resolve multiple service details from CSV string
     */
    private function resolveServiceDetails($service_ids) {
        if (empty($service_ids)) return ['name' => 'Dental Service', 'duration' => 30];
        
        $ids = array_values(array_filter(array_map('trim', explode(',', $service_ids))));
        if (empty($ids)) return ['name' => 'Dental Service', 'duration' => 30];
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT name, duration_minutes FROM services WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $services = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($services)) return ['name' => 'Dental Service', 'duration' => 30];
        
        $names = array_column($services, 'name');
        $total_duration = array_sum(array_column($services, 'duration_minutes'));
        
        return [
            'name' => implode(', ', $names),
            'duration' => $total_duration ?: 30
        ];
    }
    
    public function searchPatient($patient_id) {
        try {
            // Validate patient_id
            $patient_id = trim($patient_id);
            if (empty($patient_id)) {
                return [
                    'success' => false,
                    'message' => 'Patient ID is required'
                ];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT c.client_id, c.first_name, c.last_name, 
                       CONCAT(c.first_name, ' ', c.last_name) as full_name,
                       DATE_FORMAT(c.birthdate, '%Y-%m-%d') as birthdate,
                       c.gender, c.phone, c.email, c.profile_image, c.medical_history_edit_allowed,
                       COUNT(CASE WHEN pr.is_archived = 0 THEN 1 END) as total_records,
                       MAX(pr.created_at) as last_updated
                FROM clients c
                LEFT JOIN patient_records pr ON c.client_id = pr.client_id
                WHERE c.client_id = :client_id
                GROUP BY c.client_id
            ");
            $stmt->execute([':client_id' => $patient_id]);
            $patient = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($patient) {
                // Sanitize patient data for output
                foreach ($patient as $key => $value) {
                    if (is_string($value)) {
                        $patient[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    }
                }

                // Fetch medical history
                $medicalStmt = $this->pdo->prepare("SELECT * FROM patient_medical_history WHERE client_id = :client_id");
                $medicalStmt->execute([':client_id' => $patient_id]);
                $patient['medical_history'] = $medicalStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

                // Fetch pending edit request
                $pendingRequestStmt = $this->pdo->prepare("SELECT * FROM medical_edit_requests WHERE client_id = :client_id AND status = 'pending' ORDER BY requested_at DESC LIMIT 1");
                $pendingRequestStmt->execute([':client_id' => $patient_id]);
                $patient['pending_edit_request'] = $pendingRequestStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

                // Fetch completed appointments for auto-fill
                $appointmentsStmt = $this->pdo->prepare("
                    SELECT a.id, a.appointment_id, a.appointment_date, a.appointment_time, 
                           a.duration_minutes, a.service_id
                    FROM appointments a
                    WHERE a.client_id = :client_id AND a.status = 'completed'
                    ORDER BY a.appointment_date DESC, a.appointment_time DESC
                ");
                $appointmentsStmt->execute([':client_id' => $patient_id]);
                $appointments = $appointmentsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                
                // Resolve multiple services for each appointment
                foreach ($appointments as &$apt) {
                    $details = $this->resolveServiceDetails($apt['service_id']);
                    $apt['service_name'] = $details['name'];
                    $apt['duration_minutes'] = $details['duration'];
                }
                $patient['completed_appointments'] = $appointments;
            }
            
            return [
                'success' => $patient !== false,
                'patient' => $patient ?: null,
                'message' => $patient ? 'Patient found' : 'Patient not found'
            ];
        } catch (\PDOException $e) {
            error_log("Error searching patient: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to search patient at this time'
            ];
        }
    }
    
    public function getRecords($client_id, $filter = 'all', $include_archived = false) {
        try {
            // Validate client_id
            if (empty($client_id)) {
                return [
                    'success' => false,
                    'message' => 'Client ID is required'
                ];
            }
            
            // Validate filter
            $valid_filters = ['all', 'treatment', 'consultation', 'xray', 'prescription', 'followup', 'emergency'];
            if (!in_array($filter, $valid_filters)) {
                $filter = 'all';
            }
            
            $sql = "
                SELECT pr.*, 
                       DATE_FORMAT(pr.record_date, '%Y-%m-%d') as formatted_date,
                       DATE_FORMAT(pr.created_at, '%Y-%m-%d %H:%i:%s') as created_at_formatted
                FROM patient_records pr
                WHERE pr.client_id = :client_id
            ";
            
            $params = [':client_id' => $client_id];
            
            if (!$include_archived) {
                $sql .= " AND pr.is_archived = 0";
            }
            
            if ($filter !== 'all') {
                $sql .= " AND pr.record_type = :record_type";
                $params[':record_type'] = $filter;
            }
            
            $sql .= " ORDER BY pr.record_date DESC, pr.record_time DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Sanitize record data
            foreach ($records as &$record) {
                foreach ($record as $key => $value) {
                    if (is_string($value) && !in_array($key, ['files', 'tooth_numbers', 'surfaces'])) {
                        $record[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    }
                }
            }
            
            return [
                'success' => true,
                'records' => $records
            ];
        } catch (\PDOException $e) {
            error_log("Error getting records: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to retrieve records at this time'
            ];
        }
    }
    
    public function createRecord($record_data, $admin_name, $uploaded_files = []) {
        try {
            // Validate required fields
            $required_fields = ['client_id', 'record_type', 'record_title', 'record_date', 'record_time', 'procedure', 'description'];
            foreach ($required_fields as $field) {
                if (empty($record_data[$field])) {
                    throw new \Exception("Missing required field: $field");
                }
            }
            
            // Validate record_type
            $valid_types = ['treatment', 'consultation', 'xray', 'prescription', 'followup', 'emergency'];
            if (!in_array($record_data['record_type'], $valid_types)) {
                throw new \Exception("Invalid record type");
            }
            
            // Sanitize inputs
            $record_data['record_title'] = substr(trim($record_data['record_title']), 0, 200);
            $record_data['procedure'] = substr(trim($record_data['procedure']), 0, 100);
            $record_data['description'] = trim($record_data['description']);
            $record_data['findings'] = isset($record_data['findings']) ? trim($record_data['findings']) : '';
            $record_data['notes'] = isset($record_data['notes']) ? trim($record_data['notes']) : '';
            $record_data['followup'] = isset($record_data['followup']) ? trim($record_data['followup']) : '';
            
            // Handle file uploads
            $saved_files = [];
            if (!empty($uploaded_files)) {
                $saved_files = $this->handleFileUploads($uploaded_files);
            }
            
            // Generate unique record ID with retry logic
            $record_id = $this->generateUniqueRecordId();
            
            // Get duration from services table
            $duration = '';
            if (isset($record_data['procedure']) && !empty($record_data['procedure'])) {
                $duration = $this->getServiceDuration($record_data['procedure']);
            }
            
            // Validate and sanitize JSON data
            $files_json = json_encode($saved_files);
            $tooth_numbers_json = json_encode($record_data['tooth_numbers'] ?? []);
            $surfaces_json = json_encode($record_data['surfaces'] ?? []);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid data format");
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO patient_records 
                (record_id, client_id, appointment_id, record_type, record_title, record_date, record_time, 
                 dentist, duration, `procedure`, description, findings, notes, followup_instructions, 
                 files, tooth_numbers, surfaces, created_by)
                VALUES 
                (:record_id, :client_id, :appointment_id, :record_type, :record_title, :record_date, :record_time,
                 :dentist, :duration, :procedure, :description, :findings, :notes, :followup_instructions,
                 :files, :tooth_numbers, :surfaces, :created_by)
            ");
            
            $stmt->execute([
                ':record_id' => $record_id,
                ':client_id' => $record_data['client_id'],
                ':appointment_id' => $record_data['appointment_id'] ?? null,
                ':record_type' => $record_data['record_type'],
                ':record_title' => $record_data['record_title'],
                ':record_date' => $record_data['record_date'],
                ':record_time' => $record_data['record_time'],
                ':dentist' => $admin_name,
                ':duration' => $duration,
                ':procedure' => $record_data['procedure'],
                ':description' => $record_data['description'],
                ':findings' => $record_data['findings'] ?? '',
                ':notes' => $record_data['notes'] ?? '',
                ':followup_instructions' => $record_data['followup'] ?? '',
                ':files' => $files_json,
                ':tooth_numbers' => $tooth_numbers_json,
                ':surfaces' => $surfaces_json,
                ':created_by' => $admin_name
            ]);
            
            // Send SMS notification after successful record creation - NOW WORKING!
            $sms_sent = $this->sendRecordCreatedSMS($record_data['client_id'], $record_data);
            
            return [
                'success' => true,
                'message' => 'Record created successfully' . ($sms_sent ? ' and SMS notification sent' : ''),
                'record_id' => $record_id,
                'duration' => $duration,
                'sms_sent' => $sms_sent
            ];
        } catch (\PDOException $e) {
            error_log("Database error creating record: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            
            // Check if it's a duplicate key error
            if ($e->getCode() == '23000') {
                return [
                    'success' => false,
                    'message' => "Failed to create record: A record with similar ID already exists. Please try again."
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to create record: Database error occurred. Please try again.'
            ];
        } catch (\Exception $e) {
            error_log("Error creating record: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create record: ' . $e->getMessage()
            ];
        }
    }
    
    public function updateRecord($record_id, $record_data, $admin_name) {
        try {
            if (empty($record_id)) {
                throw new \Exception("Record ID is required");
            }
            
            // Validate required fields
            $required_fields = ['record_type', 'record_title', 'record_date', 'record_time', 'procedure', 'description'];
            foreach ($required_fields as $field) {
                if (empty($record_data[$field])) {
                    throw new \Exception("Missing required field: $field");
                }
            }
            
            // Validate record_type
            $valid_types = ['treatment', 'consultation', 'xray', 'prescription', 'followup', 'emergency'];
            if (!in_array($record_data['record_type'], $valid_types)) {
                throw new \Exception("Invalid record type");
            }
            
            // Sanitize inputs
            $record_data['record_title'] = substr(trim($record_data['record_title']), 0, 200);
            $record_data['procedure'] = substr(trim($record_data['procedure']), 0, 100);
            $record_data['description'] = trim($record_data['description']);
            $record_data['findings'] = isset($record_data['findings']) ? trim($record_data['findings']) : '';
            $record_data['notes'] = isset($record_data['notes']) ? trim($record_data['notes']) : '';
            $record_data['followup'] = isset($record_data['followup']) ? trim($record_data['followup']) : '';
            
            // Get duration
            $duration = '';
            if (!empty($record_data['procedure'])) {
                $duration = $this->getServiceDuration($record_data['procedure']);
            }
            
            $tooth_numbers_json = json_encode($record_data['tooth_numbers'] ?? []);
            $surfaces_json = json_encode($record_data['surfaces'] ?? []);
            
            // Update the record
            $stmt = $this->pdo->prepare("
                UPDATE patient_records 
                SET record_type = :record_type, 
                    record_title = :record_title, 
                    record_date = :record_date, 
                    record_time = :record_time,
                    duration = :duration, 
                    `procedure` = :procedure, 
                    description = :description, 
                    findings = :findings, 
                    notes = :notes, 
                    followup_instructions = :followup_instructions,
                    tooth_numbers = :tooth_numbers, 
                    surfaces = :surfaces
                WHERE record_id = :record_id
            ");
            
            $stmt->execute([
                ':record_type' => $record_data['record_type'],
                ':record_title' => $record_data['record_title'],
                ':record_date' => $record_data['record_date'],
                ':record_time' => $record_data['record_time'],
                ':duration' => $duration,
                ':procedure' => $record_data['procedure'],
                ':description' => $record_data['description'],
                ':findings' => $record_data['findings'],
                ':notes' => $record_data['notes'],
                ':followup_instructions' => $record_data['followup'],
                ':tooth_numbers' => $tooth_numbers_json,
                ':surfaces' => $surfaces_json,
                ':record_id' => $record_id
            ]);
            
            return [
                'success' => true,
                'message' => 'Record updated successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error updating record: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update record: ' . $e->getMessage()
            ];
        }
    }
    
    public function archiveRecord($record_id, $admin_name, $reason = '', $notes = '') {
        try {
            // Validate inputs
            if (empty($record_id)) {
                throw new \Exception("Record ID is required");
            }
            
            if (empty($reason)) {
                throw new \Exception("Archive reason is required");
            }
            
            // Validate reason
            $valid_reasons = ['duplicate', 'error', 'merged', 'obsolete', 'patient_request', 'other'];
            if (!in_array($reason, $valid_reasons)) {
                throw new \Exception("Invalid archive reason");
            }
            
            // First get record details before archiving (for SMS)
            $stmt = $this->pdo->prepare("
                SELECT client_id, record_type, record_title 
                FROM patient_records 
                WHERE record_id = :record_id
            ");
            $stmt->execute([':record_id' => $record_id]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$record) {
                throw new \Exception("Record not found");
            }
            
            $archive_reason = $reason . ($notes ? " - " . trim($notes) : "");
            
            $stmt = $this->pdo->prepare("
                UPDATE patient_records 
                SET is_archived = 1,
                    archived_by = :archived_by,
                    archive_reason = :archive_reason,
                    archived_at = NOW()
                WHERE record_id = :record_id
            ");
            
            $stmt->execute([
                ':record_id' => $record_id,
                ':archived_by' => $admin_name,
                ':archive_reason' => $archive_reason
            ]);
            
            // Send SMS notification for archive
            $sms_sent = $this->sendRecordArchivedSMS($record['client_id'], $record, $reason);
            
            return [
                'success' => true,
                'message' => 'Record archived successfully' . ($sms_sent ? ' and SMS notification sent' : ''),
                'sms_sent' => $sms_sent
            ];
        } catch (\Exception $e) {
            error_log("Error archiving record: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to archive record: ' . $e->getMessage()
            ];
        }
    }
    
    public function restoreRecord($record_id) {
        try {
            if (empty($record_id)) {
                throw new \Exception("Record ID is required");
            }
            
            // First get record details before restoring (for SMS)
            $stmt = $this->pdo->prepare("
                SELECT client_id, record_type, record_title 
                FROM patient_records 
                WHERE record_id = :record_id
            ");
            $stmt->execute([':record_id' => $record_id]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$record) {
                throw new \Exception("Record not found");
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE patient_records 
                SET is_archived = 0,
                    archived_by = NULL,
                    archive_reason = NULL,
                    archived_at = NULL
                WHERE record_id = :record_id
            ");
            
            $stmt->execute([':record_id' => $record_id]);
            
            // Send SMS notification for restoration
            $sms_sent = $this->sendRecordRestoredSMS($record['client_id'], $record);
            
            return [
                'success' => true,
                'message' => 'Record restored successfully' . ($sms_sent ? ' and SMS notification sent' : ''),
                'sms_sent' => $sms_sent
            ];
        } catch (\Exception $e) {
            error_log("Error restoring record: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to restore record: ' . $e->getMessage()
            ];
        }
    }
    
    public function getDentists() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT CONCAT('Dr. ', first_name, ' ', last_name) as dentist_name 
                FROM admin_users 
                WHERE role IN ('admin', 'staff') 
                AND status = 'active'
                ORDER BY first_name, last_name
            ");
            $stmt->execute();
            $dentists = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Sanitize names
            foreach ($dentists as &$dentist) {
                $dentist['dentist_name'] = htmlspecialchars($dentist['dentist_name'], ENT_QUOTES, 'UTF-8');
            }
            
            return $dentists;
        } catch (\PDOException $e) {
            error_log("Error getting dentists: " . $e->getMessage());
            return [];
        }
    }
        
    public function getServices() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT name, description, duration_minutes, price 
                FROM services 
                WHERE is_active = 1
                ORDER BY name
            ");
            $stmt->execute();
            $services = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Sanitize service data
            foreach ($services as &$service) {
                foreach ($service as $key => $value) {
                    if (is_string($value)) {
                        $service[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    }
                }
            }
            
            return $services;
        } catch (\PDOException $e) {
            error_log("Error getting services: " . $e->getMessage());
            return [];
        }
    }
    
    public function handleAjaxRequest($action, $data, $admin_id, $uploaded_files = []) {
        // Redundant CSRF validation removed as it is handled in admin-records.php
        
        $admin_name = $this->getAdminName($admin_id);
        
        switch ($action) {
            case 'search_patient':
                return $this->searchPatient($data['patient_id'] ?? '');
                
            case 'get_records':
                return $this->getRecords(
                    $data['client_id'] ?? '',
                    $data['filter'] ?? 'all',
                    filter_var($data['include_archived'] ?? false, FILTER_VALIDATE_BOOLEAN)
                );
                
            case 'create_record':
                // Handle file uploads separately
                $uploaded_files = [];
                if (!empty($_FILES['files'])) {
                    $uploaded_files = $this->prepareUploadedFiles($_FILES['files']);
                }
                return $this->createRecord($data['record_data'] ?? [], $admin_name, $uploaded_files);
                
            case 'update_record':
                return $this->updateRecord($data['record_id'] ?? '', $data['record_data'] ?? [], $admin_name);
                
            case 'archive_record':
                return $this->archiveRecord(
                    $data['record_id'] ?? '',
                    $admin_name,
                    $data['reason'] ?? '',
                    $data['notes'] ?? ''
                );
                
            case 'restore_record':
                return $this->restoreRecord($data['record_id'] ?? '');
                
            case 'get_duration':
                $duration = $this->getServiceDuration($data['procedure'] ?? '');
                return [
                    'success' => !empty($duration),
                    'duration' => $duration
                ];
                
            case 'get_services':
                $services = $this->getServices();
                return [
                    'success' => true,
                    'services' => $services
                ];
                
            default:
                return [
                    'success' => false,
                    'message' => 'Invalid action'
                ];
        }
    }
    
    /**
     * Prepare uploaded files array for processing
     */
    private function prepareUploadedFiles($files) {
        $prepared_files = [];
        
        if (is_array($files['name'])) {
            // Multiple files
            foreach ($files['name'] as $key => $name) {
                if ($files['error'][$key] === UPLOAD_ERR_OK) {
                    $prepared_files[] = [
                        'name' => $files['name'][$key],
                        'type' => $files['type'][$key],
                        'tmp_name' => $files['tmp_name'][$key],
                        'error' => $files['error'][$key],
                        'size' => $files['size'][$key]
                    ];
                }
            }
        } elseif ($files['error'] === UPLOAD_ERR_OK) {
            // Single file
            $prepared_files[] = $files;
        }
        
        return $prepared_files;
    }
    
    /**
     * Validate CSRF token
     */
    private function validateCsrfToken() {
        if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token'])) {
            return false;
        }
        
        if ($_SESSION['csrf_token'] !== $_POST['csrf_token']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Direct SMS sending method for manual notifications
     */
    public function sendManualSMS($patient_id, $message, $record_type = null, $record_title = null) {
        return $this->sendSMSNotification($patient_id, $message, $record_type, $record_title);
    }
}
