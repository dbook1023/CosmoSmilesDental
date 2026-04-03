<?php
/**
 * Session Security Service
 * Handles session timeout and inactivity checks.
 */

class SessionService {
    // Session timeout in seconds (30 minutes)
    private static $timeout = 1800; 

    /**
     * Start/Initialize a secure session
     */
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Update last activity timestamp
        self::updateActivity();
    }

    /**
     * Update the last activity timestamp in the session
     */
    public static function updateActivity() {
        $_SESSION['last_activity'] = time();
    }

    /**
     * Check if the session has expired due to inactivity
     * Returns true if session is still valid, false if expired
     */
    public static function checkInactivity($role = 'client') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // If no activity timestamp, initialize it if logged in
        if (!isset($_SESSION['last_activity'])) {
            if (self::isUserLoggedIn($role)) {
                self::updateActivity();
                return true;
            }
            return true; // Not logged in yet
        }

        // Check for timeout
        if (time() - $_SESSION['last_activity'] > self::$timeout) {
            self::handleLogout($role, 'inactivity');
            return false;
        }

        self::updateActivity();
        return true;
    }

    /**
     * Check if a specific role is logged in
     */
    private static function isUserLoggedIn($role) {
        switch ($role) {
            case 'admin':
                return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
            case 'staff':
                return isset($_SESSION['staff_logged_in']) && $_SESSION['staff_logged_in'] === true;
            case 'client':
            default:
                return isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true;
        }
    }

    /**
     * Centralized logout handler
     */
    public static function handleLogout($role = 'client', $reason = '') {
        // Clear all session data
        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();

        // Redirect based on role using relative paths
        $redirectUrl = '../index.php'; // Default

        switch ($role) {
            case 'admin':
                $redirectUrl = '../admin-login.php';
                break;
            case 'staff':
                $redirectUrl = '../staff-login.php';
                break;
            case 'client':
                $redirectUrl = 'login.php';
                break;
        }

        if ($reason) {
            $redirectUrl .= "?reason=$reason";
        }

        header("Location: " . $redirectUrl);
        exit();
    }
}
