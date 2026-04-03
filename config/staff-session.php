<?php
// config/staff_session.php

class StaffSessionManager {
    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function set($key, $value) {
        $_SESSION['staff_' . $key] = $value;
    }

    public function get($key) {
        return $_SESSION['staff_' . $key] ?? null;
    }

    public function destroy() {
        // Only destroy staff-related session data
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'staff_') === 0) {
                unset($_SESSION[$key]);
            }
        }
    }

    public function isStaffLoggedIn() {
        return isset($_SESSION['staff_logged_in']) && $_SESSION['staff_logged_in'] === true;
    }

    public function requireStaffLogin() {
        if (!$this->isStaffLoggedIn()) {
            header('Location: /public/assets/staff-login.php');
            exit;
        }
    }

    public function login($staffData) {
        $_SESSION['staff_logged_in'] = true;
        $_SESSION['staff_id'] = $staffData['id'];
        $_SESSION['staff_staff_id'] = $staffData['staff_id'];
        $_SESSION['staff_email'] = $staffData['email'];
        $_SESSION['staff_first_name'] = $staffData['first_name'];
        $_SESSION['staff_last_name'] = $staffData['last_name'];
        $_SESSION['staff_role'] = $staffData['role'];
        $_SESSION['staff_department'] = $staffData['department'];
    }
}
