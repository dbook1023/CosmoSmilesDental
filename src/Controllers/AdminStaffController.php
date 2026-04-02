<?php
// src/Controllers/AdminStaffController.php

require_once __DIR__ . '/../../config/database.php';

class AdminStaffController {
    private $db;
    private $conn;

    public function __construct($pdo = null) {
        if ($pdo) {
            $this->conn = $pdo;
        } else {
            $this->db = new Database();
            $this->conn = $this->db->getConnection();
        }
    }

    /**
     * Get all staff from both admin_users and staff_users tables with pagination and search
     */
    public function getAllStaff($filters = []) {
        try {
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : 5;
            $page = isset($filters['page']) ? (int)$filters['page'] : 1;
            $offset = ($page - 1) * $limit;

            // Base queries
            $queryAdmin = "SELECT id, dentist_id as staff_id, first_name, last_name, email, role, status, created_at, 'admin_type' as user_type 
                          FROM admin_users WHERE 1=1";
            $queryStaff = "SELECT id, staff_id, first_name, last_name, email, role, status, created_at, 'staff_type' as user_type 
                          FROM staff_users WHERE 1=1";

            $paramsAdmin = [];
            $paramsStaff = [];

            // Search Filter
            if (!empty($filters['search'])) {
                $search = "%" . $filters['search'] . "%";
                $searchPart = " AND (first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR dentist_id LIKE :search)";
                $queryAdmin .= $searchPart;
                $paramsAdmin[':search'] = $search;

                $searchPartStaff = " AND (first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR staff_id LIKE :search)";
                $queryStaff .= $searchPartStaff;
                $paramsStaff[':search'] = $search;
            }

            // Status Filter
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $queryAdmin .= " AND status = :status";
                $queryStaff .= " AND status = :status";
                $paramsAdmin[':status'] = $filters['status'];
                $paramsStaff[':status'] = $filters['status'];
            }

            // Role Filter
            if (!empty($filters['role']) && $filters['role'] !== 'all') {
                $role = $filters['role'];
                if ($role === 'dentist') {
                    $queryStaff .= " AND 1=0"; // Dentist only in admin_users
                } else if ($role === 'receptionist') {
                    $queryAdmin .= " AND 1=0"; // Receptionist only in staff_users
                } else {
                    // Other roles not supported anymore, but keep compatible
                    $queryAdmin .= " AND 1=0";
                    $queryStaff .= " AND role = :role";
                    $paramsStaff[':role'] = $role;
                }
            }

            // Get total counts for combined tables after filtering
            $stmtCountAdmin = $this->conn->prepare("SELECT COUNT(*) FROM (" . $queryAdmin . ") as t");
            $stmtCountAdmin->execute($paramsAdmin);
            $countAdmin = $stmtCountAdmin->fetchColumn();

            $stmtCountStaff = $this->conn->prepare("SELECT COUNT(*) FROM (" . $queryStaff . ") as t");
            $stmtCountStaff->execute($paramsStaff);
            $countStaff = $stmtCountStaff->fetchColumn();

            $totalRecords = $countAdmin + $countStaff;
            $totalPages = ceil($totalRecords / $limit);

            // Fetch data
            $stmtAdmin = $this->conn->prepare($queryAdmin);
            $stmtAdmin->execute($paramsAdmin);
            $admins = $stmtAdmin->fetchAll(PDO::FETCH_ASSOC);

            $stmtStaff = $this->conn->prepare($queryStaff);
            $stmtStaff->execute($paramsStaff);
            $staff = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);

            $combined = array_merge($admins, $staff);

            // Sort by created_at desc
            usort($combined, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            // Slice for pagination
            $paginated = array_slice($combined, $offset, $limit);

            return [
                'staff' => $paginated,
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'limit' => $limit
            ];
        } catch (Exception $e) {
            error_log("Error in getAllStaff: " . $e->getMessage());
            return ['staff' => [], 'total_records' => 0, 'total_pages' => 0];
        }
    }

    /**
     * Get single staff details for view modal
     */
    public function getStaffDetails($id, $type) {
        try {
            if ($type === 'admin_type') {
                $query = "SELECT id, dentist_id as staff_id, first_name, last_name, email, phone, role, status, specialization, created_at, 'Admin' as department 
                          FROM admin_users WHERE id = :id";
            } else {
                $query = "SELECT id, staff_id, first_name, last_name, email, phone, role, status, specialization, created_at, department 
                          FROM staff_users WHERE id = :id";
            }
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getStaffDetails: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Export all staff to CSV
     */
    public function exportStaff() {
        try {
            $data = $this->getAllStaff(['limit' => 1000, 'page' => 1]);
            $staff = $data['staff'];

            $output = fopen('php://temp', 'r+');
            fputcsv($output, ['Staff ID', 'First Name', 'Last Name', 'Email', 'Role', 'Status', 'User Type', 'Created At']);
            
            foreach ($staff as $row) {
                fputcsv($output, [
                    $row['staff_id'],
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $row['role'],
                    $row['status'],
                    $row['user_type'],
                    $row['created_at']
                ]);
            }
            
            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);
            return $csv;
        } catch (Exception $e) {
            error_log("Error in exportStaff: " . $e->getMessage());
            return "";
        }
    }

    /**
     * Delete a staff member with restrictions
     */
    public function deleteStaff($id, $type) {
        try {
            if ($type === 'admin_type') {
                return ['success' => false, 'message' => "Administrator accounts cannot be deleted for security reasons."];
            }

            // Check if status is inactive
            $stmt = $this->conn->prepare("SELECT status FROM staff_users WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || $user['status'] !== 'inactive') {
                return ['success' => false, 'message' => "Only inactive staff members can be deleted. Please deactivate the account first."];
            }

            $query = "DELETE FROM staff_users WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':id' => $id]);

            return ['success' => true, 'message' => "Staff member deleted successfully."];
        } catch (Exception $e) {
            error_log("Error in deleteStaff: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get staff statistics
     */
    public function getStaffStats() {
        try {
            $stats = [
                'total' => 0,
                'active' => 0,
                'pending' => 0,
                'medical' => 0
            ];

            // Count admin_users (All are treated as Dentists)
            $stmtAdmin = $this->conn->query("SELECT status FROM admin_users");
            while ($row = $stmtAdmin->fetch(PDO::FETCH_ASSOC)) {
                $stats['total']++;
                if ($row['status'] === 'active') $stats['active']++;
                if ($row['status'] === 'pending') $stats['pending']++;
                $stats['medical']++; 
            }

            // Count staff_users
            $stmtStaff = $this->conn->query("SELECT status, role FROM staff_users");
            while ($row = $stmtStaff->fetch(PDO::FETCH_ASSOC)) {
                $stats['total']++;
                if ($row['status'] === 'active') $stats['active']++;
                if ($row['status'] === 'pending') $stats['pending']++;
                if ($row['role'] === 'assistant_dentist') $stats['medical']++;
            }

            return $stats;
        } catch (Exception $e) {
            error_log("Error in getStaffStats: " . $e->getMessage());
            return ['total' => 0, 'active' => 0, 'pending' => 0, 'medical' => 0];
        }
    }

    /**
     * Add a new staff member
     */
    public function addStaff($data) {
        try {
            $role = $data['role'];
            $table = '';
            
            if ($role === 'dentist') {
                $table = 'admin_users';
                $idField = 'dentist_id';
                $idPrefix = 'DENT';
                $data['role'] = 'admin';
            } else {
                $table = 'staff_users';
                $idField = 'staff_id';
                $idPrefix = 'REC';
                $data['role'] = 'receptionist';
            }

            $newId = $this->generateUniqueId($table, $idField, $idPrefix);
            $day = date('d');
            $lastNameClean = str_replace(' ', '', $data['last_name']);
            $capitalizedLastName = ucfirst(strtolower($lastNameClean));
            $defaultPassword = '#' . $capitalizedLastName . $day;
            
            $hashedPassword = hash('sha512', hash('sha256', $defaultPassword) . 'cosmo_admin_salt_2024');

            if ($table === 'admin_users') {
                $query = "INSERT INTO $table ($idField, username, first_name, last_name, email, password, role, status, created_at, updated_at) 
                          VALUES (:id, :username, :first_name, :last_name, :email, :password, :role, :status, NOW(), NOW())";
                $params = [
                    ':id' => $newId,
                    ':username' => $newId,
                    ':first_name' => $data['first_name'],
                    ':last_name' => $data['last_name'],
                    ':email' => $data['email'],
                    ':password' => $hashedPassword,
                    ':role' => $data['role'],
                    ':status' => $data['status']
                ];
            } else {
                $query = "INSERT INTO $table ($idField, first_name, last_name, email, password, role, status, created_at, updated_at) 
                          VALUES (:id, :first_name, :last_name, :email, :password, :role, :status, NOW(), NOW())";
                $params = [
                    ':id' => $newId,
                    ':first_name' => $data['first_name'],
                    ':last_name' => $data['last_name'],
                    ':email' => $data['email'],
                    ':password' => $hashedPassword,
                    ':role' => $data['role'],
                    ':status' => $data['status']
                ];
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);

            return ['success' => true, 'id' => $newId, 'message' => "Staff member added successfully. Default password is: $defaultPassword"];
        } catch (Exception $e) {
            error_log("Error in addStaff: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function generateUniqueId($table, $field, $prefix) {
        $stmt = $this->conn->query("SELECT $field FROM $table ORDER BY id DESC LIMIT 1");
        $last = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($last) {
            $num = (int)preg_replace('/[^0-9]/', '', $last[$field]);
            $num++;
        } else {
            $num = 1;
        }
        return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Update an existing staff member
     */
    public function updateStaff($id, $type, $data) {
        try {
            $table = ($type === 'admin_type') ? 'admin_users' : 'staff_users';
            
            // Get current data to check for sensitive changes
            $stmt = $this->conn->prepare("SELECT email, phone FROM $table WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($current) {
                $emailChanged = ($data['email'] !== $current['email']);
                $phoneChanged = ($data['phone'] !== $current['phone']);

                if ($emailChanged || $phoneChanged) {
                    if (empty($data['otp'])) {
                        return ['success' => false, 'needs_otp' => true, 'message' => 'Verification code required for email/phone changes'];
                    }
                    
                    require_once __DIR__ . '/OTPController.php';
                    $otpCtrl = new OTPController();
                    
                    if ($emailChanged) {
                        $verify = $otpCtrl->verifyEmailOTP($data['email'], $data['otp']);
                        if (!$verify['success']) return ['success' => false, 'message' => 'Invalid email verification code'];
                    } else if ($phoneChanged) {
                        $verify = $otpCtrl->verifyPhoneOTP($data['phone'], $data['otp']);
                        if (!$verify['success']) return ['success' => false, 'message' => 'Invalid phone verification code'];
                    }
                }
            }

            $query = "UPDATE $table SET 
                        first_name = :first_name, 
                        last_name = :last_name, 
                        email = :email, 
                        phone = :phone,
                        role = :role, 
                        status = :status, 
                        specialization = :specialization,
                        updated_at = NOW() 
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':id' => $id,
                ':first_name' => $data['first_name'],
                ':last_name' => $data['last_name'],
                ':email' => $data['email'],
                ':phone' => $data['phone'] ?? '',
                ':role' => $data['role'],
                ':status' => $data['status'],
                ':specialization' => $data['specialization'] ?? ''
            ]);

            return ['success' => true, 'message' => "Staff member updated successfully."];
        } catch (Exception $e) {
            error_log("Error in updateStaff: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'get_all':
                return array_merge(['success' => true], $this->getAllStaff($data), ['stats' => $this->getStaffStats()]);
            case 'add':
                return $this->addStaff($data);
            case 'update':
                return $this->updateStaff($data['id'], $data['type'], $data);
            case 'delete':
                return $this->deleteStaff($data['id'], $data['type']);
            case 'get_details':
                $details = $this->getStaffDetails($data['id'], $data['type']);
                return $details ? ['success' => true, 'staff' => $details] : ['success' => false, 'message' => 'Staff not found'];
            case 'export':
                return ['success' => true, 'csv' => $this->exportStaff()];
            default:
                return ['success' => false, 'message' => 'Invalid action'];
        }
    }
}
?>
