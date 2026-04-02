<?php
// public/assets/admin/admin-logout.php

// Start the session
session_start();

// Log admin logout activity (optional but useful)
if (!empty($_SESSION['admin_id'])) {
    $adminId   = $_SESSION['admin_id'];
    $adminName = $_SESSION['admin_first_name'] ?? 'Unknown Admin';

    error_log(
        "Admin logout: ID {$adminId} - {$adminName} at " . date('Y-m-d H:i:s')
    );
}

// Unset all session variables
$_SESSION = [];

// Delete the session cookie if cookies are used
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Regenerate session ID for extra security
session_regenerate_id(true);

// Destroy the session
session_destroy();

// Delete admin "remember me" cookie if it exists
if (isset($_COOKIE['admin_remember'])) {
    setcookie(
        'admin_remember',
        '',
        time() - 3600,
        '/',
        '',
        true,   // secure (set to false if not using HTTPS)
        true    // httponly
    );
}

// Redirect to admin login page
header('Location: ../admin-login.php');
exit;
