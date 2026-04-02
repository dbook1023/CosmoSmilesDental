<?php
// src/Services/SecurityService.php

class SecurityService {
    private $db;
    private $maxAttempts = 5;
    private $lockoutTime = 900; // 15 minutes in seconds

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Check if the given identifier/IP is currently rate-limited.
     * @return array ['is_blocked' => bool, 'remaining' => int, 'wait_seconds' => int, 'wait_message' => string]
     */
    public function checkRateLimit($identifier, $ip) {
        $since = date('Y-m-d H:i:s', time() - 3600); // Look back 1 hour for progressive calculation
        
        $query = "SELECT COUNT(*) as count, MAX(attempt_time) as last_time, NOW() as current_db_time 
                  FROM login_attempts 
                  WHERE (identifier = :identifier OR ip_address = :ip) 
                  AND is_successful = 0 
                  AND attempt_time > :since";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':identifier' => $identifier,
            ':ip' => $ip,
            ':since' => $since
        ]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $attempts = (int)$row['count'];
        $lastTime = $row['last_time'] ? strtotime($row['last_time']) : 0;
        $currentDbTime = strtotime($row['current_db_time']);
        
        if ($attempts >= 5) {
            // Progressive delay: 30s for every 5 fails
            $delaySeconds = floor($attempts / 5) * 30;
            $unlockTime = $lastTime + $delaySeconds;
            $waitRemaining = $unlockTime - $currentDbTime;
            
            if ($waitRemaining > 0) {
                $waitMessage = ($waitRemaining < 60) 
                    ? "$waitRemaining seconds" 
                    : ceil($waitRemaining / 60) . " minutes";
                
                return [
                    'is_blocked' => true,
                    'remaining' => 0,
                    'wait_seconds' => $waitRemaining,
                    'wait_message' => $waitMessage
                ];
            }
        }
        
        return [
            'is_blocked' => false,
            'remaining' => 5 - ($attempts % 5),
            'wait_seconds' => 0,
            'wait_message' => ''
        ];
    }

    /**
     * Record a login attempt.
     */
    public function recordAttempt($identifier, $ip, $success) {
        $query = "INSERT INTO login_attempts (identifier, ip_address, is_successful) 
                  VALUES (:identifier, :ip, :success)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':identifier' => $identifier,
            ':ip' => $ip,
            ':success' => $success ? 1 : 0
        ]);
    }
    
    /**
     * Verify Google reCAPTCHA v3 token.
     */
    public function verifyReCaptcha($token) {
        $secret = env('RECAPTCHA_SECRET_KEY', defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '');
        
        if (empty($secret) || $secret === 'YOUR_SECRET_KEY_HERE' || $secret === 'your_secret_key_here') {
            return true; // Bypass if not configured yet (for user convenience)
        }

        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => self::getIpAddress()
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $result = json_decode($response);

        return $result && $result->success && $result->score >= 0.5;
    }

    /**
     * Check global IP rate limit (DDoS protection).
     * Returns true if allowed, false if ratelimited.
     */
    public function checkGlobalRateLimit() {
        $ip = self::getIpAddress();
        
        // 1. Log the request
        $this->db->prepare("INSERT INTO request_logs (ip_address) VALUES (:ip)")->execute([':ip' => $ip]);
        
        // 2. Count requests in last minute
        $limit = defined('GLOBAL_LIMIT_PER_MINUTE') ? GLOBAL_LIMIT_PER_MINUTE : 60;
        $query = "SELECT COUNT(*) FROM request_logs WHERE ip_address = :ip AND request_time > (NOW() - INTERVAL 1 MINUTE)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':ip' => $ip]);
        $count = $stmt->fetchColumn();
        
        return $count <= $limit;
    }

    /**
     * Helper to get user IP address.
     */
    public static function getIpAddress() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        } else {
            return '127.0.0.1'; // Fallback for CLI or cases where IP is missing
        }
    }
}
