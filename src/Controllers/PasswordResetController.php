<?php
// src/Controllers/PasswordResetController.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/EmailService.php';
require_once __DIR__ . '/../Services/SecurityService.php';

class PasswordResetController {
    private $db;
    private $security;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->security = new SecurityService($this->db);
        $this->ensureResetTable();
    }
    
    /**
     * Create the password_resets table if it doesn't exist
     */
    private function ensureResetTable() {
        $sql = "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL,
            user_type ENUM('client', 'staff', 'admin') NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(token),
            INDEX(email)
        )";
        $this->db->exec($sql);
    }
    
    /**
     * Handle the forgot password request
     */
    public function requestReset($identifier) {
        $ip = SecurityService::getIpAddress();
        
        $recaptchaToken = $_POST['recaptcha_token'] ?? '';
        if (!$this->security->verifyReCaptcha($recaptchaToken)) {
            return [
                'success' => false, 
                'message' => "Bot detection failed. Please try again."
            ];
        }

        // 1. Check Rate Limit
        $rateLimit = $this->security->checkRateLimit($identifier, $ip);
        if ($rateLimit['is_blocked']) {
            return [
                'success' => false, 
                'message' => "Too many failed attempts. Please try again in {$rateLimit['wait_message']}."
            ];
        }

        // 2. Identify user by Registered ID (Client ID, Staff ID, or Admin ID)
        $user = $this->findUserByIdentifier($identifier);
        
        if (!$user) {
            $this->security->recordAttempt($identifier, $ip, false);
            // Secretly return success to prevent enumeration
            return ['success' => true, 'message' => 'If your account is identified, you will receive a reset link shortly.'];
        }

        // Record attempt activity (we track resets similarly to logins for security)
        $this->security->recordAttempt($identifier, $ip, true);
        
        $email = $user['email']; // ALWAYS use the email from the database
        $userType = $user['type'];
        $firstName = $user['first_name'];
        
        // 2. Generate secure token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // 3. Store token
        $this->db->prepare("DELETE FROM password_resets WHERE email = :email AND user_type = :type")
                 ->execute([':email' => $email, ':type' => $userType]);
                 
        $stmt = $this->db->prepare("INSERT INTO password_resets (email, token, user_type, expires_at) VALUES (:email, :token, :type, :expires)");
        $stmt->execute([
            ':email' => $email,
            ':token' => $token,
            ':type' => $userType,
            ':expires' => $expiresAt
        ]);
        
        // 4. Send email
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $requestUri = $_SERVER['REQUEST_URI'];
        // Detect public directory path dynamically based on script location
        $scriptName = $_SERVER['SCRIPT_NAME'];
        // scriptName is .../public/controllers/PasswordResetController.php
        // dirname once -> .../public/controllers
        // dirname twice -> .../public
        $publicPath = dirname(dirname($scriptName));
        
        // Ensure we don't end up with double slashes
        $publicPath = rtrim($publicPath, '/\\');
        
        $resetLink = "$protocol://$host$publicPath/reset-password.php?token=$token";
        
        $emailService = new EmailService();
        $sent = $emailService->sendPasswordResetEmail($email, $resetLink, $firstName);
        
        if ($sent) {
            return ['success' => true, 'message' => 'A password reset link has been sent to your email.'];
        } else {
            return ['success' => false, 'message' => 'Failed to send reset email. Please try again later.'];
        }
    }
    
    /**
     * Verify token and return user info if valid
     */
    public function verifyToken($token) {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("SELECT * FROM password_resets WHERE token = :token AND expires_at > :now LIMIT 1");
        $stmt->execute([':token' => $token, ':now' => $now]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reset) {
            return ['success' => false, 'message' => 'Invalid or expired reset token.'];
        }
        
        return ['success' => true, 'email' => $reset['email'], 'type' => $reset['user_type']];
    }
    
    /**
     * Reset the password
     */
    public function resetPassword($token, $newPassword) {
        if (strlen($newPassword) < 8 || 
            !preg_match('/[A-Z]/', $newPassword) || 
            !preg_match('/[a-z]/', $newPassword) || 
            !preg_match('/[0-9]/', $newPassword) || 
            !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            return ['success' => false, 'message' => 'Password does not meet minimum security requirements.'];
        }

        $verification = $this->verifyToken($token);
        if (!$verification['success']) {
            return $verification;
        }
        
        $email = $verification['email'];
        $type = $verification['type'];
        
        // Hash the password based on user type
        $hashedPassword = '';
        if ($type === 'client') {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        } else {
            // Staff and Admin use custom hash
            $hashedPassword = hash('sha512', hash('sha256', $newPassword) . 'cosmo_admin_salt_2024');
        }
        
        // Update the correct table
        $table = '';
        if ($type === 'client') {
            $table = 'clients';
        } elseif ($type === 'admin') {
            $table = 'admin_users';
        } elseif ($type === 'staff') {
            // Check which table the staff belongs to
            $stmt = $this->db->prepare("SELECT id FROM admin_users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            if ($stmt->rowCount() > 0) {
                $table = 'admin_users';
            } else {
                $table = 'staff_users';
            }
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE $table SET password = :pass WHERE email = :email");
            $stmt->execute([':pass' => $hashedPassword, ':email' => $email]);
            
            // Delete the token
            $this->db->prepare("DELETE FROM password_resets WHERE token = :token")->execute([':token' => $token]);
            
            return ['success' => true, 'message' => 'Password has been reset successfully! You can now login.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error. Please try again.'];
        }
    }
    
    private function findUserByIdentifier($identifier) {
        $identifier = trim($identifier);
        
        // 1. Search in clients (strictly by Client ID first, then Email as fallback)
        $stmt = $this->db->prepare("SELECT first_name, email, 'client' as type FROM clients WHERE client_id = :id OR email = :id LIMIT 1");
        $stmt->execute([':id' => $identifier]);
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) return $user;
        
        // 2. Search in staff_users (strictly by Staff ID first, then Email as fallback)
        $stmt = $this->db->prepare("SELECT first_name, email, 'staff' as type FROM staff_users WHERE (staff_id = :id OR email = :id) AND status = 'active' LIMIT 1");
        $stmt->execute([':id' => $identifier]);
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) return $user;

        // 3. Search in admin_users (strictly by Dentist ID first, then Username/Email)
        $stmt = $this->db->prepare("SELECT first_name, email, role as type FROM admin_users WHERE (dentist_id = :id OR username = :id OR email = :id) AND status = 'active' LIMIT 1");
        $stmt->execute([':id' => $identifier]);
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return [
                'first_name' => $user['first_name'],
                'email' => $user['email'],
                'type' => ($user['type'] === 'admin') ? 'admin' : 'staff'
            ];
        }
        
        return null;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $controller = new PasswordResetController();
    $action = $_POST['action'];
    
    if ($action === 'request_reset') {
        echo json_encode($controller->requestReset(trim($_POST['identifier'] ?? $_POST['email'] ?? '')));
    } elseif ($action === 'reset_password') {
        echo json_encode($controller->resetPassword($_POST['token'] ?? '', $_POST['password'] ?? ''));
    }
    exit;
}
