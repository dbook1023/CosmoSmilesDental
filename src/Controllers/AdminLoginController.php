<?php
session_start();
header('Content-Type: application/json');

// Include database configuration and security service
require_once '../../config/database.php';
require_once __DIR__ . '/../Services/SecurityService.php';

class AdminLoginController {
    private $db;
    private $security;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->security = new SecurityService($this->db);
    }

    public function handleLogin() {
        // Only accept POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode([
                'success' => false,
                'errors' => ['general' => 'Invalid request method']
            ]);
            exit();
        }
        
        // Get form data
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $remember = isset($_POST['remember']);
        $ip = SecurityService::getIpAddress();

        $recaptchaToken = $_POST['recaptcha_token'] ?? '';
        
        // Verify reCAPTCHA
        if (!$this->security->verifyReCaptcha($recaptchaToken)) {
            echo json_encode([
                'success' => false,
                'errors' => ['general' => "Bot detection failed. Please try again or contact support."]
            ]);
            exit();
        }
        
        // 1. Check Rate Limit
        $rateLimit = $this->security->checkRateLimit($username, $ip);
        if ($rateLimit['is_blocked']) {
            echo json_encode([
                'success' => false,
                'errors' => ['general' => "Too many failed attempts. Please try again in {$rateLimit['wait_message']}."]
            ]);
            exit();
        }
        
        // Input validation
        $errors = [];
        
        if (empty($username)) {
            $errors['username'] = "Username is required";
        }
        
        if (empty($password)) {
            $errors['password'] = "Password is required";
        }
        
        // If validation errors, return them
        if (!empty($errors)) {
            echo json_encode([
                'success' => false,
                'errors' => $errors
            ]);
            exit();
        }
        
        // Check database
        try {
            // Check if user exists (can login with username, email, or dentist_id)
            $query = "SELECT * FROM admin_users WHERE (username = :username OR email = :username OR dentist_id = :username) AND status = 'active' LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            
            if (!$stmt->execute()) {
                throw new Exception('Database query failed');
            }
            
            if ($stmt->rowCount() == 0) {
                // Check if account exists but is inactive
                $queryInactive = "SELECT * FROM admin_users WHERE (username = :username OR email = :username OR dentist_id = :username) AND status = 'inactive' LIMIT 1";
                $stmtInactive = $this->db->prepare($queryInactive);
                $stmtInactive->bindParam(':username', $username);
                $stmtInactive->execute();
                
                if ($stmtInactive->rowCount() > 0) {
                    $errors['username'] = "Your account is inactive. Please contact administrator.";
                } else {
                    $errors['username'] = "No account found with this username/email/dentist ID.";
                }
                
                echo json_encode([
                    'success' => false,
                    'errors' => $errors
                ]);
                exit();
            }
            
            // User found, fetch data
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // **FIXED: Calculate the double hash exactly like your SQL did**
            // You ran: SHA2(CONCAT(SHA2(password, 256), 'cosmo_admin_salt_2024'), 512)
            // This means: SHA512( SHA256(password) + 'cosmo_admin_salt_2024' )
            $hashedInput = hash('sha512', hash('sha256', $password) . 'cosmo_admin_salt_2024');
            
            // Compare the hashed input with stored hash
            if ($hashedInput === $admin['password']) {
                // Start session
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['dentist_id'] = $admin['dentist_id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_first_name'] = $admin['first_name'];
                $_SESSION['admin_last_name'] = $admin['last_name'];
                $_SESSION['admin_full_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['admin_status'] = $admin['status'];
                
                // Update last login
                $this->updateLastLogin($admin['id']);
                
                // Record success
                $this->security->recordAttempt($username, $ip, true);

                // Return success response
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful! Redirecting...',
                    'redirect' => 'admin/admin-dashboard.php'
                ]);
                exit();
            } else {
                // For debugging - see what's happening
                error_log("Login attempt failed for: $username");
                error_log("Input hash: $hashedInput");
                error_log("Stored hash: " . $admin['password']);
                
                // Invalid password
                $this->security->recordAttempt($username, $ip, false);
                $errors['password'] = "Invalid password. Please try again.";
                echo json_encode([
                    'success' => false,
                    'errors' => $errors
                ]);
                exit();
            }
            
        } catch (PDOException $e) {
            error_log("Database error in login: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'errors' => ['general' => 'Database error. Please try again.']
            ]);
            exit();
        } catch (Exception $e) {
            error_log("General error in login: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'errors' => ['general' => 'System error. Please try again.']
            ]);
            exit();
        }
    }

    private function updateLastLogin($adminId) {
        try {
            $query = "UPDATE admin_users SET last_login = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $adminId);
            $stmt->execute();
        } catch (PDOException $e) {
            // Log error but don't fail login because of this
            error_log("Failed to update last login: " . $e->getMessage());
        }
    }
}

// Handle the login
try {
    $adminLoginController = new AdminLoginController();
    $adminLoginController->handleLogin();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'errors' => ['general' => 'Failed to initialize login system.']
    ]);
}
?>