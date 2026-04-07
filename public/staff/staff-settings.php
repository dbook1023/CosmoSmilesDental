<?php
// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if staff is logged in
if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header('Location: ../staff-login.php');
    exit;
}

// Include database configuration
require_once __DIR__ . '/../../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get staff user details from database
$staff_data = null;
$staff_name = '';
$staff_role_display = '';

try {
    $staffIdFromSession = $_SESSION['staff_id'];
    
    // Handle both numeric id and string staff_id (dashboard may overwrite session value)
    if (is_numeric($staffIdFromSession)) {
        $query = "SELECT id, staff_id, email, first_name, last_name, role, department, phone, status, last_login, created_at
                  FROM staff_users 
                  WHERE id = :staff_id AND status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':staff_id', $staffIdFromSession, PDO::PARAM_INT);
    } else {
        $query = "SELECT id, staff_id, email, first_name, last_name, role, department, phone, status, last_login, created_at
                  FROM staff_users 
                  WHERE staff_id = :staff_id AND status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':staff_id', $staffIdFromSession, PDO::PARAM_STR);
    }
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $staff_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $staff_name = $staff_data['first_name'] . ' ' . $staff_data['last_name'];
        $staff_role_display = ucfirst(str_replace('_', ' ', $staff_data['role']));
    } else {
        header('Location: ../staff-login.php');
        exit;
    }
} catch(PDOException $e) {
    error_log("Error fetching staff details: " . $e->getMessage());
    header('Location: ../staff-login.php');
    exit;
}

// Handle password change POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    header('Content-Type: application/json');
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
        exit;
    }

    // Strict Backend Validation
    $hasUppercase = preg_match('/[A-Z]/', $newPassword);
    $hasLowercase = preg_match('/[a-z]/', $newPassword);
    $hasNumber = preg_match('/[0-9]/', $newPassword);
    $hasSpecial = preg_match('/[^A-Za-z0-9]/', $newPassword);

    if (strlen($newPassword) < 8 || !$hasUppercase || !$hasLowercase || !$hasNumber || !$hasSpecial) {
        echo json_encode(['success' => false, 'message' => 'Password does not meet all security requirements.']);
        exit;
    }
    
    try {
        // Fetch stored password hash
        $pwQuery = "SELECT id, password FROM staff_users WHERE id = :id";
        $pwStmt = $db->prepare($pwQuery);
        $pwStmt->bindParam(':id', $staff_data['id'], PDO::PARAM_INT);
        $pwStmt->execute();
        $pwRow = $pwStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pwRow) {
            echo json_encode(['success' => false, 'message' => 'Staff account not found.']);
            exit;
        }
        
        // Hash current password using the same method as StaffLoginController
        // SHA512( SHA256(password) + 'cosmo_admin_salt_2024' )
        $hashedCurrentInput = hash('sha512', hash('sha256', $currentPassword) . 'cosmo_admin_salt_2024');
        
        if ($hashedCurrentInput !== $pwRow['password']) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }

        if ($hashedNewPassword === $pwRow['password']) {
            echo json_encode(['success' => false, 'message' => 'New password cannot be the same as current password.']);
            exit;
        }
        
        // Hash new password the same way
        $hashedNewPassword = hash('sha512', hash('sha256', $newPassword) . 'cosmo_admin_salt_2024');
        
        // Update password in database
        $updateQuery = "UPDATE staff_users SET password = :new_password WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':new_password', $hashedNewPassword, PDO::PARAM_STR);
        $updateStmt->bindParam(':id', $pwRow['id'], PDO::PARAM_INT);
        $updateStmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);
        exit;
        
    } catch(PDOException $e) {
        error_log("Error changing password: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A system error occurred. Please try again.']);
        exit;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    header('Content-Type: application/json');
    
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($firstName) || empty($lastName) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'First Name, Last Name and Email are required.']);
        exit;
    }

    // Check if sensitive info changed and verify OTP if so
    $isEmailChanged = ($email !== $staff_data['email']);
    $isPhoneChanged = ($phone !== ($staff_data['phone'] ?? ''));

    if ($isEmailChanged || $isPhoneChanged) {
        $otp = $_POST['otp'] ?? '';
        if (empty($otp)) {
            echo json_encode(['success' => false, 'needs_otp' => true, 'message' => 'Verification code required for email/phone changes.']);
            exit;
        }
        
        require_once __DIR__ . '/../../src/Controllers/OTPController.php';
        $otpCtrl = new OTPController();
        
        if ($isEmailChanged) {
            $verify = $otpCtrl->verifyEmailOTP($email, $otp);
            if (!$verify['success']) {
                echo json_encode(['success' => false, 'message' => 'Invalid email verification code.']);
                exit;
            }
        } else if ($isPhoneChanged) {
            $verify = $otpCtrl->verifyPhoneOTP($phone, $otp);
            if (!$verify['success']) {
                echo json_encode(['success' => false, 'message' => 'Invalid phone verification code.']);
                exit;
            }
        }
    }

    try {
        $updateQuery = "UPDATE staff_users SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, updated_at = NOW() WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':first_name', $firstName);
        $updateStmt->bindParam(':last_name', $lastName);
        $updateStmt->bindParam(':email', $email);
        $updateStmt->bindParam(':phone', $phone);
        $updateStmt->bindParam(':id', $staff_data['id'], PDO::PARAM_INT);
        
        if ($updateStmt->execute()) {
            // Update session data
            $_SESSION['staff_first_name'] = $firstName;
            $_SESSION['staff_last_name'] = $lastName;
            $_SESSION['staff_email'] = $email;
            
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile.']);
            exit;
        }
    } catch(PDOException $e) {
        error_log("Error updating staff profile: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A system error occurred.']);
        exit;
    }
}

// Format last login for display
$lastLoginDisplay = 'Never';
if (!empty($staff_data['last_login'])) {
    $lastLoginDate = new DateTime($staff_data['last_login']);
    $today = new DateTime('today');
    $yesterday = new DateTime('yesterday');
    
    if ($lastLoginDate->format('Y-m-d') === $today->format('Y-m-d')) {
        $lastLoginDisplay = 'Today, ' . $lastLoginDate->format('h:i A');
    } elseif ($lastLoginDate->format('Y-m-d') === $yesterday->format('Y-m-d')) {
        $lastLoginDisplay = 'Yesterday, ' . $lastLoginDate->format('h:i A');
    } else {
        $lastLoginDisplay = $lastLoginDate->format('M j, Y \a\t h:i A');
    }
}

// Format created_at for display
$dateJoinedDisplay = 'N/A';
if (!empty($staff_data['created_at'])) {
    $dateJoinedDisplay = date('F j, Y', strtotime($staff_data['created_at']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Cosmo Smiles Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Import Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap');
/* CSS Variables */
        :root {
            --primary: #03074f;
            --secondary: #0d5bb9;
            --accent: #6ca8f0;
            --light-accent: #e6f0ff;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --text: #333333;
            --white: #ffffff;
            --success: #28a745;
            --error: #dc3545;
            --warning: #ffc107;
            --border: #e1e5e9;
            --sidebar-bg: #f8fafc;
            --sidebar-width: 280px;
            --header-height: 70px;
        }

        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            color: var(--text);
            background-color: var(--white);
            line-height: 1.6;
            overflow-x: hidden;
            font-family: 'Open Sans', sans-serif;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Admin Header */
        .admin-header {
            background-color: var(--primary);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            height: var(--header-height);
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 5px;
            position: relative;
            height: 100%;
        }

        .logo {
            display: flex;
            align-items: center;
            z-index: 1001;
        }

        .logo img {
            height: 60px;
            width: auto;
            padding: 5px 0;
            filter: brightness(0) invert(1);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }


        .hamburger {
            display: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            z-index: 1100;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Admin Container Layout */
        .admin-container {
            display: flex;
            min-height: 100vh;
            padding-top: var(--header-height);
        }

        /* Sidebar Styles */
        .admin-sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: calc(100vh - var(--header-height));
            overflow-y: auto;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-header h3 {
            color: var(--primary);
            font-family: "Inter", sans-serif;
            font-size: 1.3rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 0;
        }

        .nav-section {
            margin-bottom: 20px;
        }

        .nav-section:last-child {
            margin-bottom: 0;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            border-left: 3px solid transparent;
        }

        .sidebar-item:hover {
            background: var(--light-accent);
            color: var(--secondary);
            border-left-color: var(--accent);
        }

        .sidebar-item.active {
            background: var(--primary);
            color: white;
            border-left-color: var(--secondary);
        }

        .sidebar-item i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .sidebar-item span {
            flex: 1;
            font-weight: 500;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--border);
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
        }

        .profile-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }

        .profile-role {
            font-size: 0.8rem;
            color: var(--dark);
            opacity: 0.7;
        }

        .logout-btn {
            margin-top: 15px;
            justify-content: center;
            background: var(--light-accent);
        }

        /* Main Content */
        .admin-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 30px;
            background: #f8fafc;
            min-height: calc(100vh - var(--header-height));
            transition: margin-left 0.3s ease;
        }

        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header-content h1 {
            font-family: "Inter", sans-serif;
            color: var(--primary);
            font-size: 2.2rem;
            margin-bottom: 5px;
        }

        .header-content p {
            color: var(--dark);
            opacity: 0.8;
            margin: 0;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .date-display {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--dark);
            font-weight: 500;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            font-family: "Open Sans", sans-serif;
            font-size: 0.9rem;
        }

        .btn:hover {
            background: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        /* Settings Container */
        .settings-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }

        /* Settings Sidebar */
        .settings-sidebar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .settings-nav {
            display: flex;
            flex-direction: column;
        }

        .settings-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 20px;
            color: var(--dark);
            text-decoration: none;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--border);
        }

        .settings-nav-item:last-child {
            border-bottom: none;
        }

        .settings-nav-item:hover {
            background: var(--light-accent);
            color: var(--secondary);
        }

        .settings-nav-item.active {
            background: var(--primary);
            color: white;
            border-left-color: var(--secondary);
        }

        .settings-nav-item i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Settings Content */
        .settings-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            min-height: 500px;
        }

        /* Hidden by default, shown with .active class */
        .settings-section {
            display: none;
        }

        .settings-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        /* Profile Section */
        .section-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--light-accent);
        }

        .section-header h2 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .section-header p {
            color: var(--dark);
            opacity: 0.8;
            margin: 0;
        }

        /* Profile Card */
        .profile-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .profile-card::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -30%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        .profile-card-header {
            display: flex;
            align-items: center;
            gap: 25px;
            position: relative;
            z-index: 1;
        }

        .profile-card-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .profile-card-info h3 {
            margin: 0 0 8px 0;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .profile-card-info p {
            margin: 0 0 5px 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .profile-card-info .staff-id {
            font-size: 0.9rem;
            opacity: 0.8;
            background: rgba(255, 255, 255, 0.15);
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 5px;
        }

        /* Profile Details */
        .profile-details {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .details-header {
            padding: 20px;
            background: var(--light-accent);
            border-bottom: 1px solid var(--border);
        }

        .details-header h4 {
            margin: 0;
            color: var(--primary);
            font-size: 1.2rem;
        }

        .details-body {
            padding: 0;
        }

        .detail-row {
            display: flex;
            border-bottom: 1px solid var(--border);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            width: 200px;
            padding: 20px;
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            border-right: 1px solid var(--border);
            display: flex;
            align-items: center;
        }

        .detail-value {
            flex: 1;
            padding: 20px;
            color: var(--text);
            display: flex;
            align-items: center;
        }

        /* Status Badge */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        /* Role Badge */
        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .role-receptionist {
            background: #cce5ff;
            color: #004085;
        }

        .role-assistant_dentist {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Info Box */
        .info-box {
            background: var(--light-accent);
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
            border-left: 4px solid var(--secondary);
        }

        .info-box h4 {
            margin: 0 0 10px 0;
            color: var(--primary);
            font-size: 1rem;
        }

        .info-box p {
            margin: 0;
            color: var(--dark);
            opacity: 0.8;
            font-size: 0.9rem;
        }

        /* Password Requirements */
        .password-requirements {
            background: var(--light-accent);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .password-requirements h4 {
            margin: 0 0 15px 0;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .requirement.met {
            color: var(--success);
        }

        .requirement.unmet {
            color: var(--dark);
            opacity: 0.5;
        }

        .requirement i {
            font-size: 0.9rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .required::after {
            content: " *";
            color: var(--error);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-family: 'Open Sans', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(108, 168, 240, 0.2);
        }

        .password-input-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--dark);
            cursor: pointer;
            font-size: 1.1rem;
        }

        .form-help {
            font-size: 0.85rem;
            color: var(--dark);
            opacity: 0.7;
            margin-top: 5px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: normal;
        }

        /* Success/Error Messages */
        .message {
            display: none;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            align-items: center;
            gap: 10px;
        }

        .message.active {
            display: flex;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
        }

        /* Custom Administrative Modals - UNIFIED STYLES */
        .overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(3, 7, 79, 0.6); backdrop-filter: blur(4px); 
            z-index: 1050; display: none; opacity: 0; transition: 0.3s; 
        }
        .overlay.active { display: block; opacity: 1; visibility: visible; }

        .admin-modal { 
            position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.9); 
            background: white; padding: 40px; border-radius: 16px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.2); z-index: 2000; 
            width: 90%; max-width: 500px; display: none; opacity: 0; 
            transition: 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); text-align: center;
        }
        .admin-modal.active { display: block; opacity: 1; transform: translate(-50%, -50%) scale(1); }
        .admin-modal-icon { 
            width: 80px; height: 80px; border-radius: 50%; 
            margin: 0 auto 25px; display: flex; align-items: center; justify-content: center; 
            font-size: 2.2rem; 
        }
        .admin-modal-success .admin-modal-icon { background: #e6ffed; color: var(--success); }
        .admin-modal-error .admin-modal-icon { background: #fff5f5; color: #e53e3e; }
        .admin-modal-confirm .admin-modal-icon { background: #fff8e6; color: #f59e0b; }
        
        .admin-modal h3 { font-size: 1.8rem; margin-bottom: 15px; font-family: "Inter", sans-serif; color: var(--primary); }
        .admin-modal p { color: #64748b; font-size: 1.1rem; line-height: 1.6; margin-bottom: 30px; }
        
        .admin-modal-actions { display: flex; gap: 15px; justify-content: center; }
        .admin-modal-btn { 
            padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; 
            border: none; font-size: 1rem; flex: 1; 
        }
        .btn-modal-confirm { background: var(--primary); color: white; }
        .btn-modal-confirm:hover { background: var(--secondary); transform: translateY(-2px); }
        .btn-modal-cancel { background: #f1f5f9; color: #64748b; }
        .btn-modal-cancel:hover { background: #e2e8f0; }

        .confirm-btns { display: flex; gap: 15px; margin-top: 20px; }
        .confirm-btns .btn { flex: 1; padding: 12px; font-weight: 700; border-radius: 8px; cursor: pointer; border: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: #edf2f7; color: #4a5568; }

        .modal-close { position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 1.2rem; color: #64748b; cursor: pointer; }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .admin-sidebar {
                width: 250px;
            }
            
            .admin-main {
                margin-left: 250px;
            }
        }

        @media (max-width: 992px) {
            .hamburger {
                display: block;
            }
            
            .admin-sidebar {
                transform: translateX(-100%);
                z-index: 1080;
                height: 100vh;
                top: 0;
            }
            
            .admin-sidebar.active {
                transform: translateX(0);
            }
            
            .admin-main {
                margin-left: 0;
                width: 100%;
            }
            
            .settings-container {
                grid-template-columns: 1fr;
            }
            
            .settings-sidebar {
                order: 2;
            }
            
            .settings-content {
                order: 1;
            }
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            
            .admin-main {
                padding: 20px;
            }
            
            .settings-content {
                padding: 20px;
            }
            
            .profile-card-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .profile-card {
                padding: 30px 20px;
            }
        }

        @media (max-width: 576px) {
            .admin-main {
                padding: 15px;
            }
            
            .settings-content {
                padding: 15px;
            }
            
            .header-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .settings-container {
            animation: fadeIn 0.6s ease;
        }
    </style>
</head>
<body>
    <!-- Admin Header (Standardized) -->
    <header class="admin-header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">
                    <a href="../index.php"><img src="../assets/images/logo-main-white-1.png" alt="Cosmo Smiles Dental"></a>
                </div>
                
                <div class="header-right">
                    <div class="hamburger">
                        <i class="fas fa-bars"></i>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Overlay for mobile sidebar -->
    <div class="overlay"></div>

    <!-- Admin Dashboard Layout -->
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-tooth"></i> Staff Dashboard</h3>
            </div>
            
            <nav class="sidebar-nav">
                <!-- Main Navigation Links -->
                <div class="nav-section">
                    <a href="staff-dashboard.php" class="sidebar-item">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    
                    <a href="staff-appointments.php" class="sidebar-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Appointments</span>
                    </a>
                    
                    <a href="staff-patients.php" class="sidebar-item">
                        <i class="fas fa-users"></i>
                        <span>Patients</span>
                    </a>
                    
                    <!--
                    <a href="staff-records.php" class="sidebar-item">
                        <i class="fas fa-file-medical"></i>
                        <span>Patient Records</span>
                    </a> -->
                    
                </div>
                
                <!-- Additional Links -->
                <div class="nav-section">
                    <a href="staff-messages.php" class="sidebar-item">
                        <i class="fas fa-comments"></i>
                        <span>Messages</span>
                    </a>
                    
                    <a href="staff-reminders.php" class="sidebar-item">
                        <i class="fas fa-bell"></i>
                        <span>Send Reminders</span>
                    </a>
                    
                    <a href="staff-settings.php" class="sidebar-item active">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="admin-profile">
                    <div class="profile-avatar">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="profile-info">
                        <span class="profile-name"><?= htmlspecialchars($staff_name) ?></span>
                        <span class="profile-role"><?= htmlspecialchars($staff_role_display) ?></span>
                    </div>
                </div>
                <a href="../staff-login.php" class="sidebar-item logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="header-content">
                    <h1>Account Settings</h1>
                    <p>View your profile information and change password</p>
                </div>
                <div class="header-actions">
                    <div class="date-display">
                        <i class="fas fa-calendar"></i>
                        <span id="current-date">Loading...</span>
                    </div>
                </div>
            </div>

            <!-- Settings Container -->
            <div class="settings-container">
                <!-- Settings Sidebar -->
                <div class="settings-sidebar">
                    <nav class="settings-nav">
                        <a href="#profile" class="settings-nav-item active" data-section="profile">
                            <i class="fas fa-user"></i>
                            <span>Profile Information</span>
                        </a>
                        <a href="#security" class="settings-nav-item" data-section="security">
                            <i class="fas fa-lock"></i>
                            <span>Change Password</span>
                        </a>
                    </nav>
                </div>

                <!-- Settings Content -->
                <div class="settings-content">
                    <!-- Profile Section - Only shows when Profile tab is active -->
                    <div class="settings-section active" id="profile-section">
                        <div class="section-header">
                            <h2>Profile Information</h2>
                            <p>Your personal and professional details</p>
                        </div>
                        
                        <!-- Profile Card -->
                        <div class="profile-card">
                            <div class="profile-card-header">
                                <div class="profile-card-avatar">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <div class="profile-card-info">
                                    <h3><?= htmlspecialchars($staff_name) ?></h3>
                                    <p><?= htmlspecialchars($staff_role_display) ?> • Department: <?= htmlspecialchars(ucfirst($staff_data['department'] ?? 'N/A')) ?></p>
                                    <div class="staff-id">Staff ID: <?= htmlspecialchars($staff_data['staff_id']) ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Profile Details -->
                        <div class="profile-details">
                            <div class="details-header">
                                <h4>Personal Information</h4>
                            </div>
                            <div class="details-body">
                                <form id="staff-profile-form">
                                    <input type="hidden" name="action" value="update_profile">
                                    <div class="detail-row">
                                        <div class="detail-label">
                                            <i class="fas fa-user"></i> First Name
                                        </div>
                                        <div class="detail-value">
                                            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($staff_data['first_name']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">
                                            <i class="fas fa-user"></i> Last Name
                                        </div>
                                        <div class="detail-value">
                                            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($staff_data['last_name']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">
                                            <i class="fas fa-envelope"></i> Email Address
                                        </div>
                                        <div class="detail-value" style="flex-direction: column; align-items: flex-start; gap: 5px;">
                                            <input type="email" name="email" id="profile-email" class="form-control" value="<?= htmlspecialchars($staff_data['email']) ?>" required style="width: 100%;">
                                            <div style="font-size: 0.8rem; color: #64748b; width: 100%;">
                                                <i class="fas fa-info-circle"></i> Changing email requires verification.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">
                                            <i class="fas fa-phone"></i> Phone Number
                                        </div>
                                        <div class="detail-value" style="flex-direction: column; align-items: flex-start; gap: 5px;">
                                            <input type="text" name="phone" id="profile-phone" class="form-control" value="<?= htmlspecialchars($staff_data['phone'] ?? '') ?>" placeholder="09xxxxxxxxx" style="width: 100%;">
                                            <div style="font-size: 0.8rem; color: #64748b; width: 100%;">
                                                <i class="fas fa-info-circle"></i> Changing phone requires verification.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="detail-row" style="border-bottom: none; padding-top: 20px;">
                                        <div class="detail-label" style="border-right: none; background: transparent;"></div>
                                        <div class="detail-value">
                                            <button type="submit" class="btn btn-primary" style="background: var(--primary); padding: 12px 25px;">
                                                <i class="fas fa-save"></i> Save Profile Changes
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Professional Details -->
                        <div class="profile-details" style="margin-top: 30px;">
                            <div class="details-header">
                                <h4>Professional Information</h4>
                            </div>
                            <div class="details-body">
                                <div class="detail-row">
                                    <div class="detail-label">
                                        <i class="fas fa-stethoscope" style="margin-right: 8px;"></i>
                                        Role
                                    </div>
                                    <div class="detail-value">
                                        <span class="role-badge role-<?= htmlspecialchars($staff_data['role']) ?>"><?= htmlspecialchars($staff_role_display) ?></span>
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">
                                        <i class="fas fa-building" style="margin-right: 8px;"></i>
                                        Department
                                    </div>
                                    <div class="detail-value"><?= htmlspecialchars(ucfirst($staff_data['department'] ?? 'N/A')) ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">
                                        <i class="fas fa-calendar-alt" style="margin-right: 8px;"></i>
                                        Date Joined
                                    </div>
                                    <div class="detail-value"><?= htmlspecialchars($dateJoinedDisplay) ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">
                                        <i class="fas fa-circle" style="margin-right: 8px;"></i>
                                        Status
                                    </div>
                                    <div class="detail-value">
                                        <span class="status-badge status-<?= htmlspecialchars($staff_data['status']) ?>"><?= htmlspecialchars(ucfirst($staff_data['status'])) ?></span>
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">
                                        <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i>
                                        Last Login
                                    </div>
                                    <div class="detail-value"><?= htmlspecialchars($lastLoginDisplay) ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Info Box -->
                        <div class="info-box">
                            <h4><i class="fas fa-info-circle" style="margin-right: 8px;"></i> Profile Update Information</h4>
                            <p>Your profile information is managed by the system administrator. Please contact the clinic administrator at admin@cosmosmilesdental.com or call (02) 123-4567 to request updates to your personal or professional information.</p>
                        </div>
                    </div>

                    <!-- Security Section - Only shows when Change Password tab is active -->
                    <div class="settings-section" id="security-section">
                        <div class="section-header">
                            <h2>Change Password</h2>
                            <p>Update your account password for security</p>
                        </div>
                        
                        <!-- Success/Error Messages -->
                        <div class="message success" id="success-message">
                            <i class="fas fa-check-circle"></i>
                            <span>Password changed successfully!</span>
                        </div>
                        
                        <div class="message error" id="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>There was an error changing your password.</span>
                        </div>
                        
                        <div class="password-requirements">
                            <h4>Password Requirements:</h4>
                            <div class="requirement unmet" id="req-length">
                                <i class="fas fa-circle"></i>
                                <span>At least 8 characters long</span>
                            </div>
                            <div class="requirement unmet" id="req-uppercase">
                                <i class="fas fa-circle"></i>
                                <span>Contains uppercase letter</span>
                            </div>
                            <div class="requirement unmet" id="req-lowercase">
                                <i class="fas fa-circle"></i>
                                <span>Contains lowercase letter</span>
                            </div>
                            <div class="requirement unmet" id="req-number">
                                <i class="fas fa-circle"></i>
                                <span>Contains number</span>
                            </div>
                            <div class="requirement unmet" id="req-special">
                                <i class="fas fa-circle"></i>
                                <span>Contains special character</span>
                            </div>
                        </div>
                        
                        <form id="password-form">
                            <div class="form-group">
                                <label for="current-password" class="required">Current Password</label>
                                <div class="password-input-container">
                                    <input type="password" id="current-password" class="form-control" required>
                                    <button type="button" class="toggle-password" data-target="current-password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="new-password" class="required">New Password</label>
                                <div class="password-input-container">
                                    <input type="password" id="new-password" class="form-control" required>
                                    <button type="button" class="toggle-password" data-target="new-password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-help">Create a strong password with at least 8 characters</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm-password" class="required">Confirm New Password</label>
                                <div class="password-input-container">
                                    <input type="password" id="confirm-password" class="form-control" required>
                                    <button type="button" class="toggle-password" data-target="confirm-password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-help">Re-enter your new password</div>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="logout-other-sessions">
                                <label for="logout-other-sessions">Log out of all other sessions after password change</label>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- UI Modals HTML -->
    <div class="admin-modal admin-modal-confirm" id="modal-confirm">
        <div class="admin-modal-icon"><i class="fas fa-question"></i></div>
        <h3 id="confirm-title">Confirmation</h3>
        <p id="confirm-message">Are you sure you want to proceed with this action?</p>
        <div class="admin-modal-actions">
            <button class="admin-modal-btn btn-modal-cancel" id="btn-confirm-no">Cancel</button>
            <button class="admin-modal-btn btn-modal-confirm" id="btn-confirm-yes">Yes, Proceed</button>
        </div>
    </div>

    <div class="admin-modal" id="modal-alert">
        <div class="admin-modal-icon"><i class="fas fa-info"></i></div>
        <h3 id="alert-title">Notification</h3>
        <p id="alert-message">Action completed.</p>
        <div class="admin-modal-actions">
            <button class="admin-modal-btn btn-modal-confirm" id="btn-alert-ok" style="width: 100%;">Understood</button>
        </div>
    </div>

    <!-- OTP Verification Modal -->
    <div class="admin-modal" id="modal-otp">
        <div class="admin-modal-icon" style="background: var(--secondary); color: white;"><i class="fas fa-shield-alt"></i></div>
        <h2 id="otp-title" style="font-family: 'Inter', sans-serif; color: var(--primary); margin-top: 15px;">Verify Your Identity</h2>
        <p id="otp-message">Please enter the 6-digit verification code sent to your email.</p>
        <div style="margin: 20px 0;">
            <input type="text" id="otp-input" class="form-control" placeholder="000000" maxlength="6" style="text-align: center; font-size: 1.5rem; letter-spacing: 5px; font-weight: 700;">
        </div>
        <div class="confirm-btns">
            <button class="btn btn-secondary" id="btn-otp-resend">Resend Code</button>
            <button class="btn btn-primary" id="btn-otp-verify">Verify & Save</button>
        </div>
        <button class="modal-close" onclick="closeModals()"><i class="fas fa-times"></i></button>
    </div>

    <script>
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.custom-notification');
            existingNotifications.forEach(notification => notification.remove());
            
            const notification = document.createElement('div');
            notification.className = `custom-notification ${type}`;
            
            // Inline styles for independence
            const bgColor = type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : type === 'warning' ? '#ff9800' : '#2196f3';
            notification.style.cssText = `position: fixed; top: 20px; right: 20px; background: ${bgColor}; color: white; padding: 15px 20px; border-radius: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 10px; z-index: 9999; font-family: 'Inter', sans-serif; transition: all 0.3s ease;`;
            
            let icon = 'fa-info-circle';
            if (type === 'success') icon = 'fa-check-circle';
            if (type === 'error') icon = 'fa-exclamation-circle';
            if (type === 'warning') icon = 'fa-exclamation-triangle';
            
            notification.innerHTML = `
                <div class="notification-content" style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas ${icon}"></i>
                    <span class="notification-message" style="white-space: pre-line;">${message}</span>
                </div>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; cursor: pointer; padding-left: 15px;">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }

        // Set current date helper (moved inside DOMContentLoaded)
        function updateDateDisplay() {
            const currentDate = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const dateEl = document.getElementById('current-date');
            if (dateEl) dateEl.textContent = currentDate.toLocaleDateString('en-PH', options);
        }
        
        // Global Helpers
        function closeModals() {
            const overlay = document.querySelector('.overlay');
            const sidebar = document.querySelector('.admin-sidebar');
            document.querySelectorAll('.admin-modal').forEach(m => m.classList.remove('active'));
            if (overlay) overlay.classList.remove('active');
            if (sidebar) sidebar.classList.remove('active');
        }

        function showConfirm(title, message, callback) {
            const confirmModal = document.getElementById('modal-confirm');
            const overlay = document.querySelector('.overlay');
            const t = document.getElementById('confirm-title');
            const m = document.getElementById('confirm-message');
            if (t) t.textContent = title;
            if (m) m.textContent = message;
            confirmActionCallback = callback;
            if (overlay) overlay.classList.add('active');
            if (confirmModal) confirmModal.classList.add('active');
        }

        function showAlert(type, title, message) {
            const alertModal = document.getElementById('modal-alert');
            const overlay = document.querySelector('.overlay');
            if (!alertModal) return;
            alertModal.className = `admin-modal admin-modal-${type} active`;
            const icon = alertModal.querySelector('.admin-modal-icon');
            if (icon) icon.innerHTML = type === 'success' ? '<i class="fas fa-check"></i>' : '<i class="fas fa-exclamation"></i>';
            const t = document.getElementById('alert-title');
            const m = document.getElementById('alert-message');
            if (t) t.textContent = title;
            if (m) m.textContent = message;
            if (overlay) overlay.classList.add('active');
        }

        // Initialization and Global Variables
        let confirmActionCallback = null;

        document.addEventListener('DOMContentLoaded', () => {
            // Assign dynamic elements
            const hamburger = document.querySelector('.hamburger');
            const sidebar = document.querySelector('.admin-sidebar');
            const overlayElement = document.querySelector('.overlay');
            
            const btnYes = document.getElementById('btn-confirm-yes');
            const btnNo = document.getElementById('btn-confirm-no');
            const btnOk = document.getElementById('btn-alert-ok');
            const settingsNavItems = document.querySelectorAll('.settings-nav-item');
            const settingsSections = document.querySelectorAll('.settings-section');
            const sidebarLinks = document.querySelectorAll('.sidebar-item');

            // Mobile sidebar toggle
            if (hamburger && sidebar && overlayElement) {
                hamburger.addEventListener('click', () => {
                    sidebar.classList.toggle('active');
                    overlayElement.classList.toggle('active');
                });
            }

            // Close modals when clicking overlay
            if (overlayElement) {
                overlayElement.addEventListener('click', closeModals);
            }

            // Modal Button Listeners
            if (btnYes) btnYes.addEventListener('click', () => { closeModals(); if (confirmActionCallback) confirmActionCallback(); });
            if (btnNo) btnNo.addEventListener('click', closeModals);
            if (btnOk) btnOk.addEventListener('click', closeModals);

            // Initialize Date Display
            updateDateDisplay();

            // Settings Navigation (Tabs)
            // Initialize: Show only profile section
            const profileSection = document.getElementById('profile-section');
            const securitySection = document.getElementById('security-section');
            
            if (profileSection) {
                profileSection.classList.add('active');
                profileSection.style.display = 'block';
            }
            if (securitySection) {
                securitySection.classList.remove('active');
                securitySection.style.display = 'none';
            }

            settingsNavItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    const targetSectionId = item.getAttribute('data-section');
                    
                    // Update active nav item
                    settingsNavItems.forEach(navItem => navItem.classList.remove('active'));
                    item.classList.add('active');
                    
                    // Hide all sections and show target
                    settingsSections.forEach(section => {
                        section.classList.remove('active');
                        section.style.display = 'none';
                    });
                    
                    const targetSection = document.getElementById(`${targetSectionId}-section`);
                    if (targetSection) {
                        targetSection.classList.add('active');
                        targetSection.style.display = 'block';
                    }
                });
            });

            // Close sidebar when clicking on a link (for mobile)
            sidebarLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 992) {
                        if (sidebar) sidebar.classList.remove('active');
                        if (overlayElement) overlayElement.classList.remove('active');
                    }
                });
            });
        });

        // Toggle password visibility
        const togglePasswordButtons = document.querySelectorAll('.toggle-password');
        
        togglePasswordButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = button.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Password requirement checking
        const newPasswordInput = document.getElementById('new-password');
        const requirements = {
            length: document.getElementById('req-length'),
            uppercase: document.getElementById('req-uppercase'),
            lowercase: document.getElementById('req-lowercase'),
            number: document.getElementById('req-number'),
            special: document.getElementById('req-special')
        };

        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                
                // Check length
                if (password.length >= 8) {
                    requirements.length.classList.remove('unmet');
                    requirements.length.classList.add('met');
                    requirements.length.querySelector('i').className = 'fas fa-check-circle';
                } else {
                    requirements.length.classList.remove('met');
                    requirements.length.classList.add('unmet');
                    requirements.length.querySelector('i').className = 'fas fa-circle';
                }
                
                // Check uppercase
                if (/[A-Z]/.test(password)) {
                    requirements.uppercase.classList.remove('unmet');
                    requirements.uppercase.classList.add('met');
                    requirements.uppercase.querySelector('i').className = 'fas fa-check-circle';
                } else {
                    requirements.uppercase.classList.remove('met');
                    requirements.uppercase.classList.add('unmet');
                    requirements.uppercase.querySelector('i').className = 'fas fa-circle';
                }
                
                // Check lowercase
                if (/[a-z]/.test(password)) {
                    requirements.lowercase.classList.remove('unmet');
                    requirements.lowercase.classList.add('met');
                    requirements.lowercase.querySelector('i').className = 'fas fa-check-circle';
                } else {
                    requirements.lowercase.classList.remove('met');
                    requirements.lowercase.classList.add('unmet');
                    requirements.lowercase.querySelector('i').className = 'fas fa-circle';
                }
                
                // Check number
                if (/[0-9]/.test(password)) {
                    requirements.number.classList.remove('unmet');
                    requirements.number.classList.add('met');
                    requirements.number.querySelector('i').className = 'fas fa-check-circle';
                } else {
                    requirements.number.classList.remove('met');
                    requirements.number.classList.add('unmet');
                    requirements.number.querySelector('i').className = 'fas fa-circle';
                }
                
                // Check special character
                if (/[^A-Za-z0-9]/.test(password)) {
                    requirements.special.classList.remove('unmet');
                    requirements.special.classList.add('met');
                    requirements.special.querySelector('i').className = 'fas fa-check-circle';
                } else {
                    requirements.special.classList.remove('met');
                    requirements.special.classList.add('unmet');
                    requirements.special.querySelector('i').className = 'fas fa-circle';
                }
            });
        }

        // Password form submission
        const passwordForm = document.getElementById('password-form');
        const successMessage = document.getElementById('success-message');
        const errorMessage = document.getElementById('error-message');

        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const currentPassword = document.getElementById('current-password').value;
                const newPassword = document.getElementById('new-password').value;
                const confirmPassword = document.getElementById('confirm-password').value;
                
                // Validation
                if (!currentPassword) {
                    showError('Please enter your current password');
                    return;
                }
                
                if (!newPassword) {
                    showError('Please enter a new password');
                    return;
                }
                
                if (newPassword.length < 8) {
                    showError('Password must be at least 8 characters long');
                    return;
                }
                
                if (newPassword !== confirmPassword) {
                    showError('New passwords do not match');
                    return;
                }
                
                // Check password requirements
                const hasUppercase = /[A-Z]/.test(newPassword);
                const hasLowercase = /[a-z]/.test(newPassword);
                const hasNumber = /[0-9]/.test(newPassword);
                const hasSpecial = /[^A-Za-z0-9]/.test(newPassword);
                
                if (!hasUppercase || !hasLowercase || !hasNumber || !hasSpecial) {
                    showError('Password does not meet all requirements');
                    return;
                }
                
                showConfirm('Change Password', 'Are you sure you want to update your password?', () => {
                    changePassword(currentPassword, newPassword);
                });
            });
        }

        function changePassword(currentPassword, newPassword) {
            // Show loading state
            const submitBtn = passwordForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing Password...';
            submitBtn.disabled = true;
            
            // Send POST request to server
            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_password', currentPassword);
            formData.append('new_password', newPassword);
            
            fetch('staff-settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    
                    // Reset form
                    passwordForm.reset();
                    
                    // Reset requirements display
                    Object.values(requirements).forEach(req => {
                        req.classList.remove('met');
                        req.classList.add('unmet');
                        req.querySelector('i').className = 'fas fa-circle';
                    });
                    
                    // If logout other sessions is checked, show message
                    const logoutOtherSessions = document.getElementById('logout-other-sessions').checked;
                    if (logoutOtherSessions) {
                        setTimeout(() => {
                            showNotification('You have been logged out of all other sessions. You will need to log in again on other devices.', 'info');
                        }, 500);
                    }
                } else {
                    showError(data.message);
                }
                
                // Reset button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                showError('A network error occurred. Please try again.');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        function showSuccess(message) {
            if (successMessage) {
                successMessage.querySelector('span').textContent = message;
                successMessage.classList.add('active');
                if (errorMessage) errorMessage.classList.remove('active');
                
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    successMessage.classList.remove('active');
                }, 5000);
            }
        }

        function showError(message) {
            if (errorMessage) {
                errorMessage.querySelector('span').textContent = message;
                errorMessage.classList.add('active');
                if (successMessage) successMessage.classList.remove('active');
                
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    errorMessage.classList.remove('active');
                }, 5000);
            }
        }
        // Profile form submission
        const staffProfileForm = document.getElementById('staff-profile-form');
        if (staffProfileForm) {
            staffProfileForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Check if any changes were made
                const firstName = staffProfileForm.querySelector('[name="first_name"]').value.trim();
                const lastName = staffProfileForm.querySelector('[name="last_name"]').value.trim();
                const email = staffProfileForm.querySelector('[name="email"]').value.trim();
                const phone = staffProfileForm.querySelector('[name="phone"]').value.trim();

                const currentFirstName = staffProfileForm.querySelector('[name="first_name"]').defaultValue.trim();
                const currentLastName = staffProfileForm.querySelector('[name="last_name"]').defaultValue.trim();
                const currentEmail = staffProfileForm.querySelector('[name="email"]').defaultValue.trim();
                const currentPhone = staffProfileForm.querySelector('[name="phone"]').defaultValue.trim();

                if (firstName === currentFirstName && lastName === currentLastName && 
                    email === currentEmail && phone === currentPhone) {
                    showAlert('error', 'No Changes Detected', 'You haven\'t made any changes to your profile information.');
                    return;
                }

                const processProfileUpdate = async (otpCode = '') => {
                    const submitBtn = staffProfileForm.querySelector('button[type="submit"]');
                    const originalHTML = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    submitBtn.disabled = true;
                    
                    try {
                        const formData = new FormData(staffProfileForm);
                        if (otpCode) formData.append('otp', otpCode);
                        
                        const response = await fetch('staff-settings.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.needs_otp) {
                            const email = staffProfileForm.querySelector('[name="email"]').value;
                            const phone = staffProfileForm.querySelector('[name="phone"]').value;
                            const currentEmail = "<?= $staff_data['email'] ?>";
                            const currentPhone = "<?= $staff_data['phone'] ?? '' ?>";
                            
                            let targetType = 'email';
                            let targetVal = email;

                            if (email !== currentEmail) {
                                targetType = 'email';
                                targetVal = email;
                            } else if (phone !== currentPhone) {
                                targetType = 'phone';
                                targetVal = phone;
                            }

                            showOTPModal(targetType, targetVal, async (code) => {
                                await processProfileUpdate(code);
                            });
                        } else if (data.success) {
                            showAlert('success', 'Update Successful', data.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showAlert('error', 'Update Failed', data.message);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showAlert('error', 'System Error', 'An unexpected error occurred.');
                    } finally {
                        submitBtn.innerHTML = originalHTML;
                        submitBtn.disabled = false;
                    }
                };

                showConfirm('Confirm Update', 'Are you sure you want to save these changes?', async () => {
                    await processProfileUpdate();
                });
            });
        }

        // OTP Modal Functionality
        function showOTPModal(type, value, callback) {
            const modal = document.getElementById('modal-otp');
            const message = document.getElementById('otp-message');
            const input = document.getElementById('otp-input');
            const verifyBtn = document.getElementById('btn-otp-verify');
            const resendBtn = document.getElementById('btn-otp-resend');

            message.textContent = `A verification code has been sent to ${value}`;
            input.value = '';
            
            if (modal && overlayElement) {
                modal.classList.add('active');
                overlayElement.classList.add('active');
            }

            sendOTP(type, value);

            verifyBtn.onclick = () => {
                const code = input.value.trim();
                if (code.length === 6) {
                    closeModals();
                    callback(code);
                } else {
                    showAlert('error', 'Invalid Code', 'Please enter the 6-digit code');
                }
            };

            resendBtn.onclick = () => sendOTP(type, value);
        }

        async function sendOTP(type, value) {
            const fd = new FormData();
            fd.append('action', type === 'email' ? 'send_email_otp' : 'send_phone_otp');
            fd.append(type, value);
            const firstNameInput = document.querySelector('[name="first_name"]');
            if (firstNameInput) fd.append('first_name', firstNameInput.value.trim());
            try {
                const res = await fetch('../controllers/OTPController.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    console.log('OTP Sent');
                } else {
                    showNotification(data.message, 'error');
                }
            } catch(e) {
                console.error(e);
            }
        }
    </script>

</body>
</html>
