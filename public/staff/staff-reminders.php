<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../src/Controllers/StaffAppointmentController.php';


// Ensure user is logged in
if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: ../../staff-login.php");
    exit();
}

// Fetch logged-in staff user information
$staffUser = null;
$staffName = "Staff User";
$staffRole = "Staff";
$staffId = null;

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Try to fetch by staff_id from session
    $staffIdFromSession = $_SESSION['staff_id'] ?? null;

    if ($staffIdFromSession) {
        // Check if staff_id is numeric or string
        if (is_numeric($staffIdFromSession)) {
            $staffQuery = "SELECT first_name, last_name, role, staff_id, email, phone, department 
                           FROM staff_users 
                           WHERE id = :id AND status = 'active'";
            $staffStmt = $conn->prepare($staffQuery);
            $staffStmt->execute([':id' => $staffIdFromSession]);
        }
        else {
            $staffQuery = "SELECT first_name, last_name, role, staff_id, email, phone, department 
                           FROM staff_users 
                           WHERE staff_id = :staff_id AND status = 'active'";
            $staffStmt = $conn->prepare($staffQuery);
            $staffStmt->execute([':staff_id' => $staffIdFromSession]);
        }

        $staffUser = $staffStmt->fetch(PDO::FETCH_ASSOC);
    }

    // If not found by staff_id, try by email
    if (!$staffUser && isset($_SESSION['staff_email'])) {
        $staffQuery = "SELECT first_name, last_name, role, staff_id, email, phone, department 
                       FROM staff_users 
                       WHERE email = :email AND status = 'active'";
        $staffStmt = $conn->prepare($staffQuery);
        $staffStmt->execute([':email' => $_SESSION['staff_email']]);
        $staffUser = $staffStmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($staffUser) {
        $staffName = $staffUser['first_name'] . ' ' . $staffUser['last_name'];
        $staffRole = ucfirst(str_replace('_', ' ', $staffUser['role']));
        $staffId = $staffUser['staff_id'];

        // Update session with correct values (DO NOT overwrite staff_id with the string staff_id)
        $_SESSION['staff_email'] = $staffUser['email'];
        $_SESSION['staff_name'] = $staffName;
    }
    else {
        error_log("Staff user not found. Session staff_id: " . ($staffIdFromSession ?? 'not set'));
    }
}
catch (Exception $e) {
    error_log("Error fetching staff user: " . $e->getMessage());
}

// Handle AJAX POST request for sending SMS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_sms') {
    header('Content-Type: application/json');
    $phone = $_POST['phone'] ?? '';
    $message = $_POST['message'] ?? '';

    if (empty($phone) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Phone number and message are required.']);
        exit;
    }

    try {
        $smsService = new TextBeeSMSService();
        $success = $smsService->sendSMS($phone, $message);

        if ($success) {
            // Log the reminder in database
            try {
                // Create reminder_logs table if it doesn't exist
                $createTableSQL = "CREATE TABLE IF NOT EXISTS reminder_logs (
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    staff_id VARCHAR(20),
                    appointment_id VARCHAR(20),
                    client_id VARCHAR(100),
                    message TEXT,
                    sent_at DATETIME,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                $conn->exec($createTableSQL);

                $logQuery = "INSERT INTO reminder_logs (staff_id, appointment_id, client_id, message, sent_at) 
                             VALUES (:staff_id, :appointment_id, :client_id, :message, NOW())";
                $logStmt = $conn->prepare($logQuery);
                $logStmt->execute([
                    ':staff_id' => $staffId,
                    ':appointment_id' => $_POST['appointment_id'] ?? null,
                    ':client_id' => $_POST['client_id'] ?? null,
                    ':message' => $message
                ]);
            }
            catch (Exception $e) {
                error_log("Error logging reminder: " . $e->getMessage());
            }

            echo json_encode(['success' => true, 'message' => 'SMS reminder sent successfully.']);
        }
        else {
            echo json_encode(['success' => false, 'message' => 'Failed to send SMS through gateway.']);
        }
    }
    catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch database appointments with client information
$patientsJson = '[]';
try {
    if (!isset($conn)) {
        $db = new Database();
        $conn = $db->getConnection();
    }

    // Fetch appointments with client information
    $query = "SELECT 
                a.appointment_id as id,
                a.client_id,
                CONCAT(a.patient_first_name, ' ', a.patient_last_name) as name,
                COALESCE(c.phone, a.patient_phone) as phone,
                a.appointment_id as appointment_id,
                a.appointment_date as date,
                a.appointment_time as time,
                a.service_id,
                a.status,
                a.notes
              FROM appointments a
              LEFT JOIN clients c ON a.client_id = c.client_id
              WHERE a.status != 'cancelled'
              ORDER BY a.client_id, a.appointment_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all services to map service IDs to names
    $servicesStmt = $conn->prepare("SELECT id, name FROM services");
    $servicesStmt->execute();
    $allServices = [];
    foreach ($servicesStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $allServices[$s['id']] = $s['name'];
    }

    // Group appointments by client_id
    $patientsMap = [];
    foreach ($results as $row) {
        $clientId = $row['client_id'];

        if (!isset($patientsMap[$clientId])) {
            $patientsMap[$clientId] = [
                'id' => $clientId,
                'name' => $row['name'],
                'phone' => $row['phone'],
                'appointments' => []
            ];
        }

        if ($row['appointment_id']) {
            // Reconstruct service_type dynamically
            $serviceType = 'Dental Service';
            if (!empty($row['service_id'])) {
                $ids = array_filter(array_map('trim', explode(',', $row['service_id'])));
                $names = [];
                foreach ($ids as $s_id) {
                    if (isset($allServices[$s_id])) {
                        $names[] = $allServices[$s_id];
                    }
                }
                if (!empty($names)) {
                    $serviceType = implode(', ', $names);
                }
            }

            $patientsMap[$clientId]['appointments'][] = [
                'id' => $row['appointment_id'],
                'date' => $row['date'],
                'time' => $row['time'],
                'type' => $serviceType,
                'status' => $row['status'],
                'notes' => $row['notes']
            ];
        }
    }

    $patientsArray = array_values($patientsMap);
    $patientsJson = json_encode($patientsArray);


}
catch (Exception $e) {
    error_log("Error fetching reminder patients: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Reminders - Cosmo Smiles Dental</title>
    <link rel="icon" type="image/png" href="<?php echo clean_url('public/assets/images/logo1-white.png'); ?>">
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
            z-index: 1001;
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

        .btn-warning {
            background: var(--warning);
            color: var(--dark);
        }

        /* Reminders Container */
        .reminders-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
        }

        /* Create Reminder Card */
        .create-reminder-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-accent);
        }

        .card-header h3 {
            color: var(--primary);
            font-size: 1.2rem;
            margin: 0;
        }

        .card-header i {
            color: var(--secondary);
            font-size: 1.2rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
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

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232c3e50' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 15px;
            padding-right: 40px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
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

        /* Patient Lookup Section */
        .patient-lookup {
            background: var(--light-accent);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .lookup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .lookup-header h4 {
            margin: 0;
            color: var(--primary);
        }

        .lookup-form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
        }

        .lookup-results {
            margin-top: 20px;
            display: none;
        }

        .lookup-results.active {
            display: block;
        }

        .patient-result {
            background: white;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .patient-result:hover {
            background: #f0f7ff;
            border-color: var(--accent);
        }

        .patient-result.selected {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .patient-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .patient-name {
            font-weight: 600;
            font-size: 1rem;
        }

        .patient-details {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-top: 5px;
        }

        /* Appointment Selection */
        .appointment-selection {
            background: var(--light-accent);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            display: none;
        }

        .appointment-selection.active {
            display: block;
        }

        .appointment-list {
            max-height: 200px;
            overflow-y: auto;
            margin-top: 15px;
        }

        .appointment-item {
            background: white;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .appointment-item:hover {
            background: #f0f7ff;
            border-color: var(--accent);
        }

        .appointment-item.selected {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .appointment-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .appointment-id {
            font-weight: 600;
            color: var(--secondary);
            background: var(--light-accent);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .appointment-item.selected .appointment-id {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .appointment-info {
            margin-top: 8px;
            font-size: 0.9rem;
        }

        /* SMS Preview */
        .sms-preview {
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-family: monospace;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .sms-header {
            color: var(--dark);
            opacity: 0.7;
            font-size: 0.8rem;
            margin-bottom: 10px;
            border-bottom: 1px dashed var(--border);
            padding-bottom: 5px;
        }

        .sms-content {
            white-space: pre-wrap;
        }

        /* Success Message */
        .success-message {
            display: none;
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            align-items: center;
            gap: 10px;
        }

        .success-message.active {
            display: flex;
        }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

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
                z-index: 999;
            }
            
            .admin-sidebar.active {
                transform: translateX(0);
            }
            
            .admin-main {
                margin-left: 0;
                width: 100%;
            }
            
            .reminders-container {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
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
            
            .lookup-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .admin-main {
                padding: 15px;
            }
            
            .create-reminder-card {
                padding: 20px;
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
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reminders-container {
            animation: fadeIn 0.6s ease;
        }
    </style>
</head>
<body>
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">
                    <a href="../index.php"><img src="<?php echo clean_url('public/assets/images/logo-main-white-1.png'); ?>" alt="Cosmo Smiles Dental"></a>
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
                </div>
                
                <!-- Additional Links -->
                <div class="nav-section">
                    <a href="staff-messages.php" class="sidebar-item">
                        <i class="fas fa-comments"></i>
                        <span>Messages</span>
                    </a>
                    
                    <a href="staff-reminders.php" class="sidebar-item active">
                        <i class="fas fa-bell"></i>
                        <span>Send Reminders</span>
                    </a>
                    
                    <a href="staff-settings.php" class="sidebar-item">
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
                        <span class="profile-name"><?php echo htmlspecialchars($staffName); ?></span>
                        <span class="profile-role"><?php echo htmlspecialchars($staffRole); ?></span>
                    </div>
                </div>
                <a href="../../staff-logout.php" class="sidebar-item logout-btn">
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
                    <h1>Send SMS Reminders</h1>
                    <p>Send appointment reminders to patients via SMS</p>
                </div>
                <div class="header-actions">
                    <div class="date-display">
                        <i class="fas fa-calendar"></i>
                        <span id="current-date">Loading...</span>
                    </div>
                </div>
            </div>

            <!-- Success Message -->
            <div class="success-message" id="success-message">
                <i class="fas fa-check-circle"></i>
                <span>SMS reminder sent successfully!</span>
            </div>

            <!-- Reminders Container -->
            <div class="reminders-container">
                <!-- Create Reminder Card -->
                <div class="create-reminder-card">
                    <div class="card-header">
                        <i class="fas fa-bell"></i>
                        <h3>Send SMS Reminder</h3>
                    </div>
                    
                    <!-- Patient Lookup Section -->
                    <div class="patient-lookup">
                        <div class="lookup-header">
                            <h4>Find Patient</h4>
                            <span style="font-size: 0.9rem; color: var(--dark); opacity: 0.7;">Delivery Method: SMS Only</span>
                        </div>
                        <div class="lookup-form">
                            <input type="text" id="patient-search" class="form-control" placeholder="Enter Patient ID (Client ID)">
                            <button type="button" class="btn" id="search-patient-btn">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                        
                        <div class="lookup-results" id="lookup-results">
                            <!-- Patient results will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Appointment Selection -->
                    <div class="appointment-selection" id="appointment-selection">
                        <div class="lookup-header">
                            <h4>Select Appointment</h4>
                        </div>
                        <div class="appointment-list" id="appointment-list">
                            <!-- Appointment items will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <form id="create-reminder-form">
                        <div class="form-group">
                            <label for="reminder-type" class="required">Reminder Type</label>
                            <select id="reminder-type" class="form-control" required>
                                <option value="">Select reminder type</option>
                                <option value="appointment">Appointment Reminder</option>
                                <option value="followup">Follow-up Reminder</option>
                                <option value="payment">Payment Reminder</option>
                                <option value="medication">Medication Reminder</option>
                                <option value="custom">Custom Message</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="reminder-message" class="required">SMS Message</label>
                            <textarea id="reminder-message" class="form-control" rows="4" required 
                                      placeholder="Enter your SMS message..."></textarea>
                            <div class="form-help">
                                Character count: <span id="char-count">0</span>/1600 • 
                                Use [Patient Name], [Appointment Date], [Appointment Time], [Appointment ID] as placeholders
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="send-time">Send Time</label>
                            <input type="datetime-local" id="send-time" class="form-control">
                            <div class="form-help">Leave empty to send immediately</div>
                        </div>
                        
                        <div class="sms-preview" id="sms-preview">
                            <div class="sms-header">SMS Preview:</div>
                            <div class="sms-content" id="sms-content">
                                Your SMS preview will appear here...
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 20px;">
                            <i class="fas fa-paper-plane"></i> Send SMS Reminder
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Set current date
        const currentDate = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('current-date').textContent = currentDate.toLocaleDateString('en-PH', options);

        // Mobile sidebar toggle
        const hamburger = document.querySelector('.hamburger');
        const sidebar = document.querySelector('.admin-sidebar');
        const overlay = document.querySelector('.overlay');

        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });

        // Close sidebar when clicking on a link (for mobile)
        const sidebarLinks = document.querySelectorAll('.sidebar-item');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                }
            });
        });

        // Patients data from database
        const patients = <?php echo $patientsJson; ?>;
        
        // Debug: Log patients data to console
        console.log('Patients loaded:', patients);
        console.log('Total patients:', patients.length);
        if (patients.length > 0) {
            console.log('First patient Client ID:', patients[0].id);
            console.log('First patient name:', patients[0].name);
            console.log('First patient appointments:', patients[0].appointments);
        }

        // State
        let selectedPatient = null;
        let selectedAppointment = null;

        // DOM Elements
        const patientSearch = document.getElementById('patient-search');
        const searchPatientBtn = document.getElementById('search-patient-btn');
        const lookupResults = document.getElementById('lookup-results');
        const appointmentSelection = document.getElementById('appointment-selection');
        const appointmentList = document.getElementById('appointment-list');
        const reminderType = document.getElementById('reminder-type');
        const reminderMessage = document.getElementById('reminder-message');
        const charCount = document.getElementById('char-count');
        const sendTime = document.getElementById('send-time');
        const smsPreview = document.getElementById('sms-preview');
        const smsContent = document.getElementById('sms-content');
        const createReminderForm = document.getElementById('create-reminder-form');
        const successMessage = document.getElementById('success-message');

        // Set default send time to current time + 1 hour
        const now = new Date();
        const nextHour = new Date(now.getTime() + 60 * 60 * 1000);
        sendTime.value = nextHour.toISOString().slice(0, 16);

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
                    <span class="notification-message">${message}</span>
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

        // Search patient by Client ID
        function searchPatient() {
            const searchTerm = patientSearch.value.trim();
            
            if (!searchTerm) {
                showNotification('Please enter a Patient ID (Client ID)', 'error');
                return;
            }
            
            // Clear previous results
            lookupResults.innerHTML = '';
            lookupResults.classList.remove('active');
            appointmentSelection.classList.remove('active');
            
            // Find patient - exact match
            const foundPatient = patients.find(patient => patient.id === searchTerm);
            
            console.log('Searching for Client ID:', searchTerm);
            console.log('Found patient:', foundPatient);
            
            if (!foundPatient) {
                // Try case-insensitive search
                const foundPatientCI = patients.find(patient => 
                    patient.id.toLowerCase() === searchTerm.toLowerCase()
                );
                
                if (foundPatientCI) {
                    displayPatient(foundPatientCI);
                } else {
                    lookupResults.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: var(--error);">
                            <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Patient not found. Please check the Client ID and try again.</p>
                            <p style="font-size: 0.8rem; margin-top: 10px; color: var(--dark);">
                                Example IDs: ${patients.slice(0, 5).map(p => p.id).join(', ')}${patients.length > 5 ? '...' : ''}
                            </p>
                        </div>
                    `;
                    lookupResults.classList.add('active');
                }
                return;
            }
            
            displayPatient(foundPatient);
        }
        
        function displayPatient(patient) {
            // Display patient info
            const patientElement = document.createElement('div');
            patientElement.className = 'patient-result';
            patientElement.innerHTML = `
                <div class="patient-info">
                    <div>
                        <div class="patient-name">${escapeHtml(patient.name)}</div>
                        <div class="patient-details">
                            Client ID: ${escapeHtml(patient.id)} &bull; Phone: ${escapeHtml(patient.phone)}
                        </div>
                    </div>
                    <div>
                        <span class="badge" style="background: var(--success); color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                            ${patient.appointments.length} appointment(s)
                        </span>
                    </div>
                </div>
            `;
            
            patientElement.addEventListener('click', () => {
                // Remove selected class from all results
                document.querySelectorAll('.patient-result').forEach(item => {
                    item.classList.remove('selected');
                });
                
                // Add selected class
                patientElement.classList.add('selected');
                
                // Set selected patient
                selectedPatient = patient;
                
                // Show appointment selection
                showAppointments(patient);
            });
            
            lookupResults.appendChild(patientElement);
            lookupResults.classList.add('active');
        }

        // Show appointments for selected patient
        function showAppointments(patient) {
            appointmentList.innerHTML = '';
            appointmentSelection.classList.add('active');
            selectedAppointment = null;
            
            if (!patient.appointments || patient.appointments.length === 0) {
                appointmentList.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: var(--dark); opacity: 0.7;">
                        <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>No appointments found for this patient.</p>
                    </div>
                `;
                return;
            }
            
            patient.appointments.forEach(appointment => {
                const appointmentElement = document.createElement('div');
                appointmentElement.className = 'appointment-item';
                appointmentElement.innerHTML = `
                    <div class="appointment-details">
                        <div>
                            <div class="appointment-id">${escapeHtml(appointment.id)}</div>
                            <div class="appointment-info">
                                ${formatDate(appointment.date)} at ${formatTime(appointment.time)}<br>
                                <small>${escapeHtml(appointment.type)} &bull; ${escapeHtml(appointment.status)}</small>
                            </div>
                        </div>
                        <div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                `;
                
                appointmentElement.addEventListener('click', () => {
                    // Remove selected class from all appointments
                    document.querySelectorAll('.appointment-item').forEach(item => {
                        item.classList.remove('selected');
                    });
                    
                    // Add selected class
                    appointmentElement.classList.add('selected');
                    
                    // Set selected appointment
                    selectedAppointment = appointment;
                    
                    // Update SMS preview
                    updateSMSPreview();
                });
                
                appointmentList.appendChild(appointmentElement);
            });
            
            // Auto-select first appointment
            if (patient.appointments.length > 0) {
                appointmentList.firstChild.click();
            }
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Format date
        function formatDate(dateString) {
            if (!dateString) return 'Date not set';
            const date = new Date(dateString);
            const options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }

        // Format time
        function formatTime(timeString) {
            if (!timeString) return 'Time not set';
            const [hours, minutes] = timeString.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:${minutes} ${ampm}`;
        }

        // Update character count
        reminderMessage.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;
            charCount.style.color = count > 1600 ? 'var(--error)' : (count > 1400 ? 'var(--warning)' : 'var(--dark)');
            
            updateSMSPreview();
        });

        // Update SMS preview
        function updateSMSPreview() {
            if (!selectedPatient || !selectedAppointment) {
                smsContent.textContent = 'Please select a patient and appointment first.';
                return;
            }
            
            const message = reminderMessage.value;
            let preview = message;
            
            // Replace placeholders
            if (selectedPatient) {
                preview = preview.replace(/\[Patient Name\]/g, selectedPatient.name);
            }
            
            if (selectedAppointment) {
                preview = preview.replace(/\[Appointment Date\]/g, formatDate(selectedAppointment.date));
                preview = preview.replace(/\[Appointment Time\]/g, formatTime(selectedAppointment.time));
                preview = preview.replace(/\[Appointment ID\]/g, selectedAppointment.id);
            }
            
            smsContent.textContent = preview || 'Your SMS preview will appear here...';
        }

        // Update preview when type changes
        reminderType.addEventListener('change', function() {
            if (this.value && selectedPatient && selectedAppointment) {
                // Auto-generate message based on type
                let autoMessage = '';
                
                switch(this.value) {
                    case 'appointment':
                        autoMessage = `Hi ${selectedPatient ? selectedPatient.name : '[Patient Name]'}, this is a reminder for your dental appointment on ${selectedAppointment ? formatDate(selectedAppointment.date) : '[Appointment Date]'} at ${selectedAppointment ? formatTime(selectedAppointment.time) : '[Appointment Time]'}. Appointment ID: ${selectedAppointment ? selectedAppointment.id : '[Appointment ID]'}. Please arrive 15 minutes early.`;
                        break;
                    case 'followup':
                        autoMessage = `Hi ${selectedPatient ? selectedPatient.name : '[Patient Name]'}, this is a follow-up reminder from Cosmo Smiles Dental. Please schedule your next appointment.`;
                        break;
                    case 'payment':
                        autoMessage = `Hi ${selectedPatient ? selectedPatient.name : '[Patient Name]'}, this is a payment reminder from Cosmo Smiles Dental. Please settle your outstanding balance.`;
                        break;
                    case 'medication':
                        autoMessage = `Hi ${selectedPatient ? selectedPatient.name : '[Patient Name]'}, this is a medication reminder from Cosmo Smiles Dental. Please take your prescribed medication as directed.`;
                        break;
                    case 'custom':
                        autoMessage = `Hi ${selectedPatient ? selectedPatient.name : '[Patient Name]'}, this is a message from Cosmo Smiles Dental. `;
                        break;
                }
                
                reminderMessage.value = autoMessage;
                charCount.textContent = autoMessage.length;
                
                if (autoMessage.length > 1600) {
                    charCount.style.color = 'var(--error)';
                } else if (autoMessage.length > 1400) {
                    charCount.style.color = 'var(--warning)';
                } else {
                    charCount.style.color = 'var(--dark)';
                }
                
                updateSMSPreview();
            }
        });

        // Form submission
        createReminderForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Validate patient selection
            if (!selectedPatient) {
                showNotification('Please select a patient first', 'error');
                return;
            }
            
            // Validate appointment selection
            if (!selectedAppointment) {
                showNotification('Please select an appointment', 'error');
                return;
            }
            
            // Validate message
            const message = reminderMessage.value.trim();
            if (!message) {
                showNotification('Please enter a message', 'error');
                return;
            }
            
            if (message.length > 1600) {
                showNotification('SMS message cannot exceed 1600 characters', 'error');
                return;
            }
            
            // Get form values
            const typeValue = reminderType.value;
            const sendTimeValue = sendTime.value;
            
            // Create reminder object
            const reminder = {
                patientId: selectedPatient.id,
                patientName: selectedPatient.name,
                patientPhone: selectedPatient.phone,
                appointmentId: selectedAppointment.id,
                appointmentDate: selectedAppointment.date,
                appointmentTime: selectedAppointment.time,
                type: typeValue.replace(/-/g, ' '),
                message: smsContent.textContent, // Use the processed preview message
                sendTime: sendTimeValue || 'immediately',
                timestamp: new Date().toISOString()
            };
            
            try {
                // Show loading state on button
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                submitBtn.disabled = true;
                
                const formData = new FormData();
                formData.append('action', 'send_sms');
                formData.append('phone', reminder.patientPhone);
                formData.append('message', reminder.message);
                formData.append('client_id', reminder.patientId);
                formData.append('appointment_id', reminder.appointmentId);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    showSuccessMessage(`SMS reminder sent to ${selectedPatient.name} (${selectedPatient.phone})`);
                    
                    // Reset form (but keep patient selected)
                    reminderType.value = '';
                    reminderMessage.value = '';
                    charCount.textContent = '0';
                    charCount.style.color = 'var(--dark)';
                    
                    // Keep patient and appointment selected
                    updateSMSPreview();
                } else {
                    showNotification('Failed to send SMS: ' + result.message, 'error');
                }
                
                // Restore button
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                
            } catch (error) {
                showNotification('An error occurred while sending the SMS.', 'error');
                console.error(error);
                
                // Restore button
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send SMS Reminder';
                submitBtn.disabled = false;
            }
        });

        function showSuccessMessage(message) {
            successMessage.querySelector('span').textContent = message;
            successMessage.classList.add('active');
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                successMessage.classList.remove('active');
            }, 5000);
        }

        // Event listeners
        searchPatientBtn.addEventListener('click', searchPatient);
        
        patientSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchPatient();
            }
        });

        // Initialize character count
        charCount.textContent = '0';
        
        // Log staff info
        console.log('Staff Name:', <?php echo json_encode($staffName); ?>);
        console.log('Staff Role:', <?php echo json_encode($staffRole); ?>);
    </script>
</body>
</html>