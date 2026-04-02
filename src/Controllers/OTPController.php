<?php
// src/Controllers/OTPController.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/EmailService.php';
require_once __DIR__ . '/../Services/TextBeeSMSService.php';

class OTPController {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->ensureOTPTable();
    }
    
    /**
     * Create the verification_otps table if it doesn't exist
     */
    private function ensureOTPTable() {
        $sql = "CREATE TABLE IF NOT EXISTS verification_otps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            otp_code VARCHAR(6) NOT NULL,
            type ENUM('email', 'phone') NOT NULL,
            expires_at DATETIME NOT NULL,
            verified TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->exec($sql);
    }
    
    /**
     * Generate a 6-digit OTP code
     */
    private function generateOTP() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Send email OTP
     */
    public function sendEmailOTP($email, $firstName = 'User') {
        $sent = false;
        // Rate limiting: max 5 OTPs per email in 10 minutes
        $rateLimitQuery = "SELECT COUNT(*) as cnt FROM verification_otps 
                           WHERE email = :email AND type = 'email' 
                           AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)";
        $stmt = $this->db->prepare($rateLimitQuery);
        $stmt->execute([':email' => $email]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        
        if ($count >= 5) {
            return ['success' => false, 'message' => 'Too many OTP requests. Please wait a few minutes.'];
        }
        
        $otp = $this->generateOTP();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        // Invalidate previous OTPs for this email
        $invalidateQuery = "DELETE FROM verification_otps WHERE email = :email AND type = 'email'";
        $this->db->prepare($invalidateQuery)->execute([':email' => $email]);
        
        // Store new OTP
        $insertQuery = "INSERT INTO verification_otps (email, otp_code, type, expires_at) 
                         VALUES (:email, :otp, 'email', :expires)";
        $this->db->prepare($insertQuery)->execute([
            ':email' => $email,
            ':otp' => $otp,
            ':expires' => $expiresAt
        ]);
        
        // Send email via EmailService
        $emailService = new EmailService();
        $sent = $emailService->sendOTP($email, $otp, $firstName);
        
        if ($sent) {
            return ['success' => true, 'message' => 'Verification code sent to your email.'];
        } else {
            return ['success' => false, 'message' => 'Failed to send email. Please try again.'];
        }
    }
    
    /**
     * Verify email OTP
     */
    public function verifyEmailOTP($email, $code) {
        $now = date('Y-m-d H:i:s');
        $query = "SELECT * FROM verification_otps 
                  WHERE email = :email AND otp_code = :code AND type = 'email' 
                  AND verified = 0 AND expires_at > :now 
                  ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':email' => $email, ':code' => $code, ':now' => $now]);
        
        if ($stmt->rowCount() > 0) {
            // Mark as verified
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $updateQuery = "UPDATE verification_otps SET verified = 1 WHERE id = :id";
            $this->db->prepare($updateQuery)->execute([':id' => $row['id']]);
            
            // Store verified status in session
            $_SESSION['email_verified'] = $email;
            
            return ['success' => true, 'message' => 'Email verified successfully!'];
        }
        
        return ['success' => false, 'message' => 'Invalid or expired verification code.'];
    }
    
    /**
     * Send phone OTP via SMS
     */
    public function sendPhoneOTP($phone) {
        $sent = false;
        // Rate limiting
        $rateLimitQuery = "SELECT COUNT(*) as cnt FROM verification_otps 
                           WHERE phone = :phone AND type = 'phone' 
                           AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)";
        $stmt = $this->db->prepare($rateLimitQuery);
        $stmt->execute([':phone' => $phone]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        
        if ($count >= 5) {
            return ['success' => false, 'message' => 'Too many OTP requests. Please wait a few minutes.'];
        }
        
        $otp = $this->generateOTP();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        // Invalidate previous OTPs for this phone
        $invalidateQuery = "DELETE FROM verification_otps WHERE phone = :phone AND type = 'phone'";
        $this->db->prepare($invalidateQuery)->execute([':phone' => $phone]);
        
        // Store new OTP
        $insertQuery = "INSERT INTO verification_otps (phone, otp_code, type, expires_at) 
                         VALUES (:phone, :otp, 'phone', :expires)";
        $this->db->prepare($insertQuery)->execute([
            ':phone' => $phone,
            ':otp' => $otp,
            ':expires' => $expiresAt
        ]);
        
        // Send SMS via TextBee
        $smsService = new TextBeeSMSService();
        $message = "Your Cosmo Smiles Dental verification code is: $otp. Valid for 5 minutes. Do not share this code.";
        $sent = $smsService->sendSMS($phone, $message);
        
        if ($sent) {
            return ['success' => true, 'message' => 'Verification code sent to your phone.'];
        } else {
            return ['success' => false, 'message' => 'Failed to send SMS. Please try again.'];
        }
    }
    
    /**
     * Verify phone OTP
     */
    public function verifyPhoneOTP($phone, $code) {
        $now = date('Y-m-d H:i:s');
        $query = "SELECT * FROM verification_otps 
                  WHERE phone = :phone AND otp_code = :code AND type = 'phone' 
                  AND verified = 0 AND expires_at > :now 
                  ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':phone' => $phone, ':code' => $code, ':now' => $now]);
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $updateQuery = "UPDATE verification_otps SET verified = 1 WHERE id = :id";
            $this->db->prepare($updateQuery)->execute([':id' => $row['id']]);
            
            $_SESSION['phone_verified'] = $phone;
            
            return ['success' => true, 'message' => 'Phone number verified successfully!'];
        }
        
        return ['success' => false, 'message' => 'Invalid or expired verification code.'];
    }
}

// Handle AJAX requests (only if this file is the main entry point or included by the bridge)
$isDirectAjax = basename($_SERVER['SCRIPT_FILENAME']) === 'OTPController.php';
if ($isDirectAjax && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $controller = new OTPController();
    $action = $_POST['action'];
    
    switch ($action) {
        case 'send_otp':
        case 'send_email_otp':
            $email = trim($_POST['email'] ?? '');
            $firstName = trim($_POST['first_name'] ?? $_POST['firstName'] ?? 'User');
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode($controller->sendEmailOTP($email, $firstName));
            } else {
                echo json_encode(['success' => false, 'message' => 'Valid email required.']);
            }
            break;
            
        case 'send_phone_otp':
            $phone = trim($_POST['phone'] ?? '');
            if (!empty($phone) && preg_match('/^09[0-9]{9}$/', $phone)) {
                echo json_encode($controller->sendPhoneOTP($phone));
            } else {
                echo json_encode(['success' => false, 'message' => 'Valid phone number required.']);
            }
            break;
            
        case 'verify_email_otp':
            $email = trim($_POST['email'] ?? '');
            $code = trim($_POST['otp_code'] ?? $_POST['otp'] ?? '');
            if (!empty($email) && !empty($code)) {
                echo json_encode($controller->verifyEmailOTP($email, $code));
            } else {
                echo json_encode(['success' => false, 'message' => 'Email and verification code are required.']);
            }
            break;
            
        case 'verify_phone_otp':
            $phone = trim($_POST['phone'] ?? '');
            $code = trim($_POST['otp_code'] ?? $_POST['otp'] ?? '');
            if (!empty($phone) && !empty($code)) {
                echo json_encode($controller->verifyPhoneOTP($phone, $code));
            } else {
                echo json_encode(['success' => false, 'message' => 'Phone number and verification code are required.']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
    exit;
}
?>
