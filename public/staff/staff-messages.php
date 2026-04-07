<?php
// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if staff is logged in - based on your login controller
if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header('Location: ../staff-login.php');
    exit;
}

// Fix the require path
require_once __DIR__ . '/../../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get staff user details from database using the ID from session
$staff_name = '';
$staff_role = '';

try {
    $staffIdFromSession = $_SESSION['staff_id'];
    
    // Handle both numeric id and string staff_id (dashboard may overwrite session value)
    if (is_numeric($staffIdFromSession)) {
        $query = "SELECT id, staff_id, email, first_name, last_name, role, department, phone 
                  FROM staff_users 
                  WHERE id = :staff_id AND status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':staff_id', $staffIdFromSession, PDO::PARAM_INT);
    } else {
        $query = "SELECT id, staff_id, email, first_name, last_name, role, department, phone 
                  FROM staff_users 
                  WHERE staff_id = :staff_id AND status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':staff_id', $staffIdFromSession, PDO::PARAM_STR);
    }
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $staff_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $staff_first_name = $staff_data['first_name'];
        $staff_last_name = $staff_data['last_name'];
        $staff_role = $staff_data['role'];
        $staff_name = $staff_first_name . ' ' . $staff_last_name;
        
        // Store in session for future use
        $_SESSION['first_name'] = $staff_first_name;
        $_SESSION['last_name'] = $staff_last_name;
        $_SESSION['role'] = $staff_role;
    } else {
        // If staff not found, redirect to login
        header('Location: ../staff-login.php');
        exit;
    }
} catch(PDOException $e) {
    error_log("Error fetching staff details: " . $e->getMessage());
    header('Location: ../staff-login.php');
    exit;
}

// Fetch messages from database
$messages = [];
try {
    $query = "SELECT m.*, c.first_name, c.last_name 
              FROM messages m 
              LEFT JOIN clients c ON m.client_id = c.client_id 
              ORDER BY m.submitted_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert database data to match the expected format
    foreach ($messages as &$message) {
        $message['id'] = (int)$message['id'];
        $message['fullName'] = !empty($message['first_name']) ? 
            $message['first_name'] . ' ' . $message['last_name'] : 
            $message['name'];
        $message['email'] = $message['email'];
        $message['phone'] = $message['phone'] ?? 'N/A';
        $message['subject'] = 'Contact Form Inquiry';
        $message['message'] = $message['message'];
        
        // Format date and time
        $submitted_at = new DateTime($message['submitted_at']);
        $message['date'] = $submitted_at->format('Y-m-d');
        $message['time'] = $submitted_at->format('h:i A');
        $message['status'] = $message['status'];
    }
    
} catch(PDOException $e) {
    error_log("Error fetching messages: " . $e->getMessage());
    $messages = [];
}

// Handle mark as read action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_read'])) {
    $message_id = $_POST['message_id'] ?? 0;
    
    try {
        $query = "UPDATE messages SET status = 'read' WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $message_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Update the local messages array
        foreach ($messages as &$message) {
            if ($message['id'] == $message_id) {
                $message['status'] = 'read';
                break;
            }
        }
        
        echo json_encode(['success' => true]);
        exit();
        
    } catch(PDOException $e) {
        error_log("Error updating message status: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Cosmo Smiles Dental</title>
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

        /* Messages Container */
        .messages-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .messages-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .messages-header h3 {
            color: var(--primary);
            font-size: 1.1rem;
            margin: 0;
        }

        .messages-count {
            background: var(--light-accent);
            color: var(--secondary);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .messages-filter {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            padding: 8px 16px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            background: var(--light-accent);
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .messages-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .message-item {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .message-item:hover {
            background: var(--light-accent);
        }

        .message-item.unread {
            background: rgba(108, 168, 240, 0.1);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .message-sender {
            font-weight: 600;
            color: var(--primary);
            font-size: 1rem;
        }

        .message-time {
            font-size: 0.85rem;
            color: var(--dark);
            opacity: 0.7;
        }

        .message-subject {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .message-preview {
            color: var(--dark);
            opacity: 0.8;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .message-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85rem;
            color: var(--dark);
            opacity: 0.7;
        }

        .message-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Message Details Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--primary);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--dark);
        }

        .modal-body {
            padding: 25px;
        }

        .message-details {
            margin-bottom: 25px;
        }

        .detail-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .detail-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .detail-value {
            color: var(--text);
            font-size: 0.95rem;
        }

        .message-content {
            background: var(--light-accent);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            line-height: 1.6;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* No Messages */
        .no-messages {
            text-align: center;
            padding: 60px 20px;
            color: var(--dark);
            opacity: 0.7;
        }

        .no-messages i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--border);
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
            
            .messages-filter {
                flex-wrap: wrap;
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
            
            .message-header {
                flex-direction: column;
                gap: 5px;
            }
            
            .message-meta {
                flex-direction: column;
                gap: 5px;
            }
        }

        @media (max-width: 576px) {
            .admin-main {
                padding: 15px;
            }
            
            .modal-content {
                width: 95%;
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

        .messages-container {
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
                    <a href="staff-messages.php" class="sidebar-item active">
                        <i class="fas fa-comments"></i>
                        <span>Messages</span>
                    </a>
                    
                    <a href="staff-reminders.php" class="sidebar-item">
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
                        <span class="profile-name"><?php echo htmlspecialchars($staff_name); ?></span>
                        <span class="profile-role"><?php 
                            // Format role for better display
                            if ($staff_role === 'assistant_dentist') {
                                echo 'Assistant Dentist';
                            } else if ($staff_role === 'receptionist') {
                                echo 'Receptionist';
                            } else {
                                echo htmlspecialchars($staff_role);
                            }
                        ?></span>
                    </div>
                </div>
                <a href="staff-logout.php" class="sidebar-item logout-btn">
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
                    <h1>Patient Messages</h1>
                    <p>View patient inquiries from contact form</p>
                </div>
                <div class="header-actions">
                    <div class="date-display">
                        <i class="fas fa-calendar"></i>
                        <span id="current-date">Loading...</span>
                    </div>
                </div>
            </div>

            <!-- Messages Container -->
            <div class="messages-container">
                <div class="messages-header">
                    <h3>Contact Form Submissions</h3>
                    <span class="messages-count" id="messages-count">
                        <?php 
                            $unreadCount = array_reduce($messages, function($carry, $item) {
                                return $carry + ($item['status'] === 'unread' ? 1 : 0);
                            }, 0);
                            echo count($messages) . ' messages (' . $unreadCount . ' unread)';
                        ?>
                    </span>
                </div>
                
                <div class="messages-filter">
                    <button class="filter-btn active" data-filter="all">All Messages</button>
                    <button class="filter-btn" data-filter="unread">Unread</button>
                    <button class="filter-btn" data-filter="read">Read</button>
                    <button class="filter-btn" data-filter="today">Today</button>
                </div>
                
                <div class="messages-list" id="messages-list">
                    <!-- Messages will be populated by JavaScript -->
                </div>
            </div>
        </main>
    </div>

    <!-- Message Details Modal -->
    <div class="modal" id="message-details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Message Details</h3>
                <button class="close-modal" id="close-details-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="message-details" id="current-message-details">
                    <!-- Message details will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" id="mark-read-btn">
                    <i class="fas fa-check"></i> Mark as Read
                </button>
                <button class="btn" id="close-modal-btn">
                    Close
                </button>
            </div>
        </div>
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

        // Messages data from PHP
        const messages = <?php echo json_encode($messages); ?>;

        // State
        let allMessages = [...messages];
        let filteredMessages = [...messages];
        let currentMessage = null;

        // DOM Elements
        const messagesList = document.getElementById('messages-list');
        const messagesCount = document.getElementById('messages-count');
        const filterButtons = document.querySelectorAll('.filter-btn');
        const messageDetailsModal = document.getElementById('message-details-modal');
        const closeDetailsModal = document.getElementById('close-details-modal');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const currentMessageDetails = document.getElementById('current-message-details');
        const markReadBtn = document.getElementById('mark-read-btn');

        // Initialize messages list
        function renderMessages() {
            messagesList.innerHTML = '';
            
            if (filteredMessages.length === 0) {
                messagesList.innerHTML = `
                    <div class="no-messages">
                        <i class="fas fa-envelope-open"></i>
                        <h3>No messages found</h3>
                        <p>There are no messages matching your filter criteria.</p>
                    </div>
                `;
                return;
            }
            
            filteredMessages.forEach(message => {
                const messageItem = document.createElement('div');
                messageItem.className = `message-item ${message.status === 'unread' ? 'unread' : ''}`;
                messageItem.setAttribute('data-message-id', message.id);
                
                // Get preview text
                const previewText = message.message.length > 150 ? 
                    message.message.substring(0, 150) + '...' : message.message;
                
                messageItem.innerHTML = `
                    <div class="message-header">
                        <div class="message-sender">${message.fullName}</div>
                        <div class="message-time">${message.date} ${message.time}</div>
                    </div>
                    <div class="message-subject">${message.subject}</div>
                    <div class="message-preview">${previewText}</div>
                    <div class="message-meta">
                        <div class="message-meta-item">
                            <i class="fas fa-envelope"></i>
                            <span>${message.email}</span>
                        </div>
                        <div class="message-meta-item">
                            <i class="fas fa-phone"></i>
                            <span>${message.phone}</span>
                        </div>
                        <div class="message-meta-item">
                            <i class="fas fa-circle" style="font-size: 0.5rem; color: ${message.status === 'unread' ? 'var(--secondary)' : 'var(--dark)'}"></i>
                            <span>${message.status === 'unread' ? 'Unread' : 'Read'}</span>
                        </div>
                    </div>
                `;
                
                messageItem.addEventListener('click', () => {
                    // Load message details
                    loadMessageDetails(message);
                    
                    // Mark as read if unread
                    if (message.status === 'unread') {
                        markMessageAsRead(message.id, () => {
                            message.status = 'read';
                            renderMessages();
                            updateMessagesCount();
                            loadMessageDetails(message);
                        });
                    }
                });
                
                messagesList.appendChild(messageItem);
            });
            
            updateMessagesCount();
        }

        // Update messages count
        function updateMessagesCount() {
            const unreadCount = allMessages.filter(m => m.status === 'unread').length;
            const totalCount = allMessages.length;
            messagesCount.textContent = `${totalCount} messages (${unreadCount} unread)`;
        }

        // Load message details
        function loadMessageDetails(message) {
            currentMessage = message;
            
            currentMessageDetails.innerHTML = `
                <div class="detail-item">
                    <div class="detail-label">Full Name</div>
                    <div class="detail-value">${message.fullName}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Email Address</div>
                    <div class="detail-value">${message.email}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Phone Number</div>
                    <div class="detail-value">${message.phone}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Subject</div>
                    <div class="detail-value">${message.subject}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Date & Time</div>
                    <div class="detail-value">${message.date} ${message.time}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        ${message.status === 'unread' ? 
                            '<span style="color: var(--secondary); font-weight: 600;">Unread</span>' : 
                            '<span style="color: var(--success); font-weight: 600;">Read</span>'}
                    </div>
                </div>
                <div class="message-content">
                    ${message.message.replace(/\n/g, '<br>')}
                </div>
            `;
            
            messageDetailsModal.classList.add('active');
            
            // Update mark as read button text
            markReadBtn.innerHTML = message.status === 'unread' ? 
                '<i class="fas fa-check"></i> Mark as Read' :
                '<i class="fas fa-check-double"></i> Already Read';
            markReadBtn.disabled = message.status === 'read';
        }

        // Filter messages
        function filterMessages(filter) {
            const today = new Date().toISOString().split('T')[0];
            
            switch(filter) {
                case 'all':
                    filteredMessages = [...allMessages];
                    break;
                case 'unread':
                    filteredMessages = allMessages.filter(m => m.status === 'unread');
                    break;
                case 'read':
                    filteredMessages = allMessages.filter(m => m.status === 'read');
                    break;
                case 'today':
                    filteredMessages = allMessages.filter(m => m.date === today);
                    break;
                default:
                    filteredMessages = [...allMessages];
            }
            
            renderMessages();
        }

        // Mark message as read via AJAX
        function markMessageAsRead(messageId, callback) {
            const formData = new FormData();
            formData.append('mark_as_read', true);
            formData.append('message_id', messageId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the allMessages array
                    const messageIndex = allMessages.findIndex(m => m.id == messageId);
                    if (messageIndex !== -1) {
                        allMessages[messageIndex].status = 'read';
                    }
                    
                    if (callback) callback();
                } else {
                    console.error('Error marking message as read:', data.error);
                    showNotification('Failed to mark message as read. Please try again.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        }

        // Event listeners for filter buttons
        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                button.classList.add('active');
                
                // Filter messages
                const filter = button.getAttribute('data-filter');
                filterMessages(filter);
            });
        });

        // Close details modal
        closeDetailsModal.addEventListener('click', () => {
            messageDetailsModal.classList.remove('active');
        });

        closeModalBtn.addEventListener('click', () => {
            messageDetailsModal.classList.remove('active');
        });

        // Mark as read button
        markReadBtn.addEventListener('click', () => {
            if (currentMessage && currentMessage.status === 'unread') {
                markMessageAsRead(currentMessage.id, () => {
                    currentMessage.status = 'read';
                    
                    // Update UI
                    renderMessages();
                    updateMessagesCount();
                    loadMessageDetails(currentMessage);
                });
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && messageDetailsModal.classList.contains('active')) {
                messageDetailsModal.classList.remove('active');
            }
        });

        // Initialize
        renderMessages();
    </script>
</body>
</html>