<?php
// public/assets/staff/staff-logout.php

// Start session
session_start();

// Log the logout activity (optional)
if (isset($_SESSION['staff_id'])) {
    $staffName = $_SESSION['staff_first_name'] ?? 'Unknown Staff';
    $staffId = $_SESSION['staff_id'];
    error_log("Staff logout: ID $staffId - $staffName");
}

// Destroy all session data
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Redirect to staff login page
header('Location: ../staff-login.php');
exit;
?>