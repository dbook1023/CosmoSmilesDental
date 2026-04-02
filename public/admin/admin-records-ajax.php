<?php
namespace Controllers;

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
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->upload_dir = __DIR__ . '/../../../uploads/patient_records/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
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
            // Use transaction to prevent race conditions
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM patient_records 
                WHERE record_id LIKE :pattern
                AND DATE(created_at) = CURDATE()
                FOR UPDATE
            ");
            $stmt->execute([':pattern' => 'REC-' . $date . '-%']);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $next_number = $result['count'] + 1;
            
            $this->pdo->commit();
            
            return 'REC-' . $date . '-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error generating record ID: " . $e->getMessage());
            // Fallback with microtime for uniqueness
            return 'REC-' . $date . '-' . substr(str_replace('.', '', microtime(true)), -6);
        }
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
                       c.gender, c.phone, c.email, c.profile_image,
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
            $record_data['notes'] = isset($record_data['notes']) ? trim($record_data['notes']) : '';
            $record_data['followup'] = isset($record_data['followup']) ? trim($record_data['followup']) : '';
            
            // Handle file uploads
            $saved_files = [];
            if (!empty($uploaded_files)) {
                $saved_files = $this->handleFileUploads($uploaded_files);
            }
            
            $record_id = $this->generateRecordId();
            
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
                (record_id, client_id, record_type, record_title, record_date, record_time, 
                 dentist, duration, `procedure`, description, notes, followup_instructions, 
                 files, tooth_numbers, surfaces, created_by)
                VALUES 
                (:record_id, :client_id, :record_type, :record_title, :record_date, :record_time,
                 :dentist, :duration, :procedure, :description, :notes, :followup_instructions,
                 :files, :tooth_numbers, :surfaces, :created_by)
            ");
            
            $stmt->execute([
                ':record_id' => $record_id,
                ':client_id' => $record_data['client_id'],
                ':record_type' => $record_data['record_type'],
                ':record_title' => $record_data['record_title'],
                ':record_date' => $record_data['record_date'],
                ':record_time' => $record_data['record_time'],
                ':dentist' => $admin_name,
                ':duration' => $duration,
                ':procedure' => $record_data['procedure'],
                ':description' => $record_data['description'],
                ':notes' => $record_data['notes'] ?? '',
                ':followup_instructions' => $record_data['followup'] ?? '',
                ':files' => $files_json,
                ':tooth_numbers' => $tooth_numbers_json,
                ':surfaces' => $surfaces_json,
                ':created_by' => $admin_name
            ]);
            
            return [
                'success' => true,
                'message' => 'Record created successfully',
                'record_id' => $record_id,
                'duration' => $duration
            ];
        } catch (\Exception $e) {
            error_log("Error creating record: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create record: ' . $e->getMessage()
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
            
            $stmt = $this->pdo->prepare("
                UPDATE patient_records 
                SET is_archived = 1,
                    archived_by = :archived_by,
                    archive_reason = :archive_reason,
                    archived_at = NOW()
                WHERE record_id = :record_id
            ");
            
            $archive_reason = $reason . ($notes ? " - " . trim($notes) : "");
            
            $stmt->execute([
                ':record_id' => $record_id,
                ':archived_by' => $admin_name,
                ':archive_reason' => $archive_reason
            ]);
            
            return [
                'success' => true,
                'message' => 'Record archived successfully'
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
            
            $stmt = $this->pdo->prepare("
                UPDATE patient_records 
                SET is_archived = 0,
                    archived_by = NULL,
                    archive_reason = NULL,
                    archived_at = NULL
                WHERE record_id = :record_id
            ");
            
            $stmt->execute([':record_id' => $record_id]);
            
            return [
                'success' => true,
                'message' => 'Record restored successfully'
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
    
    public function handleAjaxRequest($action, $data, $admin_id) {
        // Validate CSRF token
        if (!$this->validateCsrfToken()) {
            return [
                'success' => false,
                'message' => 'Invalid security token'
            ];
        }
        
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
}
?>