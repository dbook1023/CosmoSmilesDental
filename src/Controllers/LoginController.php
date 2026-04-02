<?php
session_start();

// Enhanced session handling with security
$isLoggedIn = isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true;

// Set user name if logged in, otherwise empty
$userName = '';
if ($isLoggedIn) {
    $firstName = isset($_SESSION['client_first_name']) ? $_SESSION['client_first_name'] : '';
    $lastName = isset($_SESSION['client_last_name']) ? $_SESSION['client_last_name'] : '';
    $userName = trim($firstName . ' ' . $lastName);
    
    // If both names are empty, show generic name
    if (empty($userName)) {
        $userName = 'My Account';
    }
} else {
    $userName = 'My Account';
}
?>