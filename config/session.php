<?php
class SessionManager {
    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public function get($key) {
        return $_SESSION[$key] ?? null;
    }

    public function destroy() {
        session_destroy();
    }

    public function isLoggedIn() {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /public/assets/admin-login.php');
            exit;
        }
    }
}
?>