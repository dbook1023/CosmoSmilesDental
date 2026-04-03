<?php
session_start();
header('Content-Type: application/json');

// Include database configuration and security service
require_once '../../config/database.php';
require_once __DIR__ . '/../Services/SecurityService.php';

class StaffLoginController {
    private $db;
    private $security;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->security = new SecurityService($this->db);
    }

    public function handleLogin() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            // Get form data
            $staff_id = trim($_POST['staff_id']);
            $password = $_POST['password'];
            $remember = isset($_POST['remember']) ? true : false;
            $ip = SecurityService::getIpAddress();

            // Check Rate Limit
            $rateLimit = $this->security->checkRateLimit($staff_id, $ip);
            if ($rateLimit['is_blocked']) {
                echo json_encode([
                    'success' => false,
                    'errors' => ['general' => "Too many failed attempts. Please try again in {$rateLimit['wait_message']}."]
                ]);
                exit();
            }
            
            // Input validation
            $isValid = true;
            $errors = [];
            
            // Staff ID validation
            if (empty($staff_id)) {
                $errors['staff_id'] = "Staff ID is required";
                $isValid = false;
            }
            
            // Password validation
            if (empty($password)) {
                $errors['password'] = "Password is required";
                $isValid = false;
            }
            
            // If no validation errors, check database
            if ($isValid) {
                try {
                    // Check if staff exists
                    $query = "SELECT * FROM staff_users WHERE staff_id = :staff_id AND status = 'active' LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':staff_id', $staff_id);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() == 1) {
                        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // **FIXED: Calculate the double hash exactly like your SQL did**
                        // You ran: SHA2(CONCAT(SHA2(password, 256), 'cosmo_admin_salt_2024'), 512)
                        // This means: SHA512( SHA256(password) + 'cosmo_admin_salt_2024' )
                        $hashedInput = hash('sha512', hash('sha256', $password) . 'cosmo_admin_salt_2024');
                        
                        // Verify password - compare hashes
                        if ($hashedInput === $staff['password']) {
                            // Start session
                            $_SESSION['staff_logged_in'] = true;
                            $_SESSION['staff_id'] = $staff['id'];
                            $_SESSION['staff_staff_id'] = $staff['staff_id'];
                            $_SESSION['staff_first_name'] = $staff['first_name'];
                            $_SESSION['staff_last_name'] = $staff['last_name'];
                            $_SESSION['staff_full_name'] = $staff['first_name'] . ' ' . $staff['last_name'];
                            $_SESSION['staff_role'] = $staff['role'];
                            $_SESSION['staff_email'] = $staff['email'];
                            $_SESSION['staff_department'] = $staff['department'];
                            
                            // Update last login
                            $this->updateLastLogin($staff['id']);
                            
                            // Record success
                            $this->security->recordAttempt($staff_id, $ip, true);

                            // Return success response
                            echo json_encode([
                                'success' => true,
                                'message' => 'Login successful!',
                                'redirect' => 'staff/staff-dashboard.php'
                            ]);
                            exit();
                        } else {
                            // Debug: Log what's happening
                            error_log("Staff login failed for: $staff_id");
                            error_log("Input hash: $hashedInput");
                            error_log("Stored hash: " . $staff['password']);
                            
                            $this->security->recordAttempt($staff_id, $ip, false);
                            $errors['password'] = "Invalid password. Please try again.";
                        }
                    } else {
                        // Check if account is inactive
                        $queryInactive = "SELECT * FROM staff_users WHERE staff_id = :staff_id AND status = 'inactive' LIMIT 1";
                        $stmtInactive = $this->db->prepare($queryInactive);
                        $stmtInactive->bindParam(':staff_id', $staff_id);
                        $stmtInactive->execute();
                        
                        if ($stmtInactive->rowCount() > 0) {
                            $this->security->recordAttempt($staff_id, $ip, false);
                            $errors['staff_id'] = "Your account is inactive. Please contact administrator.";
                        } else {
                            $this->security->recordAttempt($staff_id, $ip, false);
                            $errors['staff_id'] = "No staff account found with this Staff ID.";
                        }
                    }
                    
                } catch (PDOException $e) {
                    error_log("Staff login error: " . $e->getMessage());
                    $errors['general'] = "System error. Please try again later.";
                }
            }
            
            // If there are errors, return JSON response
            if (!empty($errors)) {
                echo json_encode([
                    'success' => false,
                    'errors' => $errors
                ]);
                exit();
            }
        } else {
            echo json_encode([
                'success' => false,
                'errors' => ['general' => 'Invalid request method']
            ]);
            exit();
        }
    }

    private function updateLastLogin($staffId) {
        try {
            $query = "UPDATE staff_users SET last_login = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $staffId);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Failed to update staff last login: " . $e->getMessage());
        }
    }
}

// Instantiate and handle the login
$staffLoginController = new StaffLoginController();
$staffLoginController->handleLogin();
