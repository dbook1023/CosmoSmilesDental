<?php 
// admin-patients.php

// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in FIRST
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit;
}

// Fix the require path - adjust based on your actual file structure
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/env.php';

// Handle API requests
if (isset($_GET['action'])) {
    require_once __DIR__ . '/../../src/Controllers/AdminPatientController.php';
    $patientController = new AdminPatientController();
    
    switch ($_GET['action']) {
        case 'get_patient_details':
            if (isset($_GET['id'])) {
                $patientController->handlePatientDetailsRequest();
            }
            exit;
            
        case 'export_patients':
            // Export patients to CSV
            $filters = [
                'status' => $_GET['status'] ?? 'all',
                'search' => $_GET['search'] ?? '',
                'gender' => $_GET['gender'] ?? 'all',
                'is_minor' => $_GET['is_minor'] ?? 'all'
            ];
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="patients_export_' . date('Y-m-d') . '.csv"');
            
            $patientController->exportPatients($filters);
            exit;
    }
}

require_once __DIR__ . '/../../src/Controllers/AdminPatientController.php';
$patientController = new AdminPatientController();

// Get admin user details from database
$adminUser = $patientController->getAdminUserById($_SESSION['admin_id']);

// Get patient statistics from database
$patientStats = $patientController->getPatientStatistics();

// Handle filters
$filters = [
    'status' => $_GET['status'] ?? 'all',
    'gender' => $_GET['gender'] ?? 'all',
    'is_minor' => $_GET['is_minor'] ?? 'all',
    'search' => $_GET['search'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? 'created_at',
    'sort_order' => $_GET['sort_order'] ?? 'desc',
    'page' => $_GET['page'] ?? 1
];

// Handle POST requests (create, update, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token for all POST requests
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = 'Security token mismatch. Please try again.';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($filters));
        exit;
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_patient':
                // Validate required fields
                $requiredFields = ['first_name', 'last_name', 'birthdate', 'phone', 'email'];
                $missingFields = [];
                foreach ($requiredFields as $field) {
                    if (empty($_POST[$field])) {
                        $missingFields[] = $field;
                    }
                }
                
                if (!empty($missingFields)) {
                    $_SESSION['error_message'] = 'Missing required fields: ' . implode(', ', $missingFields);
                } else {
                    // Validate phone number
                    if (!$patientController->validatePhilippinePhone($_POST['phone'])) {
                        $_SESSION['error_message'] = 'Invalid phone number format. Must be 11 digits starting with 09.';
                    } else {
                        // Calculate age
                        $birthdate = new DateTime($_POST['birthdate']);
                        $today = new DateTime();
                        $age = $today->diff($birthdate)->y;
                        $is_minor = ($age < 18) ? 1 : 0;
                        
                        $patientData = [
                            'first_name' => $_POST['first_name'],
                            'last_name' => $_POST['last_name'],
                            'birthdate' => $_POST['birthdate'],
                            'gender' => $_POST['gender'] ?? 'other',
                            'phone' => $_POST['phone'],
                            'email' => $_POST['email'],
                            'is_minor' => $is_minor,
                            'parental_consent' => ($is_minor && isset($_POST['parental_consent'])) ? 1 : 0
                        ];
                        
                        $success = $patientController->createPatient($patientData);
                        if ($success) {
                            if (isset($_SESSION['patient_created'])) {
                                $patientInfo = $_SESSION['patient_created'];
                                $_SESSION['success_message'] = "Patient {$patientInfo['patient_name']} (ID: {$patientInfo['client_id']}) created successfully! Default password: {$patientInfo['default_password']}";
                                unset($_SESSION['patient_created']);
                            } else {
                                $_SESSION['success_message'] = 'Patient created successfully!';
                            }
                        } else {
                            $_SESSION['error_message'] = 'Unable to create patient. Please check if email already exists.';
                        }
                    }
                }
                break;
                
            case 'delete_patient':
                if (!isset($_POST['patient_id']) || !is_numeric($_POST['patient_id'])) {
                    $_SESSION['error_message'] = 'Invalid patient ID.';
                    break;
                }
                
                $patientId = intval($_POST['patient_id']);
                $success = $patientController->deletePatient($patientId);
                
                if ($success) {
                    $_SESSION['success_message'] = 'Patient deleted successfully!';
                } else {
                    $_SESSION['error_message'] = 'Unable to delete patient. Patient may have existing appointments.';
                }
                break;
        }
        
        // Redirect to avoid form resubmission
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($filters));
        exit;
    }
}

// Get patients data
$patientsData = $patientController->getAllPatients($filters);
$patients = $patientsData['patients'];
$totalPatients = $patientsData['total'];
$currentPageNum = $patientsData['page'];
$limit = $patientsData['limit'];

// Calculate pagination
$totalPages = ceil($totalPatients / $limit);

// Get messages from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';

// Clear messages after retrieving
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Sidebar variables
$currentPage = 'patients';
$sidebarAdminName = $adminUser ? 'Dr. ' . $adminUser['first_name'] . ' ' . $adminUser['last_name'] : 'Administrator';
$sidebarAdminRole = ($adminUser && strtolower($adminUser['role'] ?? '') === 'admin') ? 'Administrator' : ($adminUser ? ucfirst($adminUser['role'] ?? '') : 'Administrator');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Patients Management - Cosmo Smiles Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-patient.css">
    <?php  include 'includes/admin-sidebar-css.php'; ?>
    <style>
        /* Modal Design Enhancements */
        .modal-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: none;
            box-shadow: 0 20px 40px rgba(3, 7, 79, 0.15), 0 10px 20px rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            overflow: hidden;
            animation: modalSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            transform-origin: center;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 25px 30px;
            border-bottom: 3px solid var(--accent);
        }

        .modal-header h3 {
            color: white;
            font-family: "Inter", sans-serif;
            font-size: 1.5rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-header h3::before {
            content: "";
            display: inline-block;
            width: 6px;
            height: 24px;
            background: var(--accent);
            border-radius: 3px;
        }

        .modal-header .close-modal {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 1.2rem;
        }

        .modal-header .close-modal:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
            background: white;
        }

        .modal-footer {
            background: var(--sidebar-bg);
            padding: 20px 30px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        /* Form Group Enhancements */
        .form-group {
            position: relative;
            margin-bottom: 25px;
        }

        .form-group label {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }

        .form-group label::before {
            content: "â€¢";
            color: var(--accent);
            font-size: 1.2rem;
        }

        .form-control {
            border: 2px solid #e1e5e9;
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            color: var(--dark);
        }

        .form-control:focus {
            border-color: var(--accent);
            background: white;
            box-shadow: 0 0 0 3px rgba(108, 168, 240, 0.15);
            transform: translateY(-1px);
        }

        .form-control:disabled {
            background: #f1f3f5;
            border-color: #dee2e6;
            color: #868e96;
        }

        /* Form Row Spacing */
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 10px;
        }

        .form-row .form-group {
            flex: 1;
        }

        /* Checkbox Styling */
        .form-check {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #e1e5e9;
            transition: all 0.3s ease;
        }

        .form-check:hover {
            border-color: var(--accent);
            background: #e6f0ff;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            cursor: pointer;
            accent-color: var(--secondary);
        }

        .form-check-label {
            font-weight: 500;
            color: var(--dark);
            cursor: pointer;
            margin-bottom: 0;
        }

        /* Form Text Styling */
        .form-text {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 6px;
            padding-left: 22px;
        }

        .form-text::before {
            content: "â„¹";
            font-size: 0.8rem;
            color: var(--secondary);
        }

        /* Patient Details View Styling */
        .patient-details-view {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border-radius: 12px;
            padding: 25px;
            border: 2px solid #e6f0ff;
        }

        .patient-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e6f0ff;
        }

        .patient-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            box-shadow: 0 8px 20px rgba(3, 7, 79, 0.2);
        }

        .patient-title h4 {
            color: var(--primary);
            font-family: "Inter", sans-serif;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .patient-title p {
            color: var(--dark);
            opacity: 0.8;
            font-size: 0.95rem;
            margin: 0;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .detail-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e6f0ff;
            transition: all 0.3s ease;
        }

        .detail-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-color: var(--accent);
        }

        .detail-item label {
            display: block;
            font-size: 0.85rem;
            color: var(--dark);
            opacity: 0.7;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .detail-item span {
            display: block;
            font-size: 1rem;
            color: var(--primary);
            font-weight: 600;
        }

        /* Delete Modal Special Styling */
        #delete-patient-modal .modal-content {
            border: 3px solid #f8d7da;
        }

        #delete-patient-modal .modal-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        #delete-patient-modal .modal-body {
            background: #fff5f5;
        }

        .text-warning {
            color: #e53e3e;
            background: #fed7d7;
            padding: 10px 15px;
            border-radius: 8px;
            border-left: 4px solid #e53e3e;
            margin: 15px 0;
            font-size: 0.9rem;
        }

        /* Button Enhancements */
        .btn {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            font-weight: 600;
            letter-spacing: 0.5px;
            border: none;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            color: white;
        }

        .btn-error {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Responsive Modal Design */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10px;
            }

            .modal-header {
                padding: 20px;
            }

            .modal-body {
                padding: 20px;
            }

            .modal-footer {
                padding: 15px 20px;
                flex-direction: column;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .patient-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Animation for Modal Content */
        .modal-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            gap: 15px;
        }

        .modal-loading i {
            font-size: 2.5rem;
            color: var(--accent);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Success/Error States for Inputs */
        .form-control.is-valid {
            border-color: var(--success);
            background-color: rgba(40, 167, 69, 0.05);
        }

        .form-control.is-invalid {
            border-color: var(--error);
            background-color: rgba(220, 53, 69, 0.05);
        }

        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 6px;
            font-size: 0.85rem;
            color: var(--error);
            padding-left: 22px;
        }

        .valid-feedback {
            display: block;
            width: 100%;
            margin-top: 6px;
            font-size: 0.85rem;
            color: var(--success);
            padding-left: 22px;
        }

        /* Availability Toggle */
        .availability-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 8px 16px;
            border-radius: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            margin-right: 15px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
            margin: 0;
        }

        .switch input { 
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            -webkit-transition: .4s;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            -webkit-transition: .4s;
            transition: .4s;
        }

        input:checked + .slider {
            background-color: var(--success, #28a745);
        }

        input:focus + .slider {
            box-shadow: 0 0 1px var(--success, #28a745);
        }

        input:checked + .slider:before {
            -webkit-transform: translateX(26px);
            -ms-transform: translateX(26px);
            transform: translateX(26px);
        }

        /* Rounded sliders */
        .slider.round {
            border-radius: 34px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        #checkin-status-text {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }

        /* Age Indicator in Forms */
        .age-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            padding: 4px 10px;
            border-radius: 12px;
            margin-left: 10px;
            font-weight: 600;
        }

        .age-minor {
            background: #cce5ff;
            color: #004085;
        }

        .age-adult {
            background: #d4edda;
            color: #155724;
        }
        
        /* Enhanced Modal Scrolling for View Modal */
        #view-patient-modal .modal-content {
            max-height: 85vh !important;
            overflow-y: auto !important;
            width: 90% !important;
            max-width: 800px !important;
        }

        #view-patient-modal .modal-body {
            max-height: 60vh;
            overflow-y: auto;
            padding: 30px;
        }

        #view-patient-modal .patient-details-view {
            max-height: none;
            overflow-y: visible;
        }

        /* Ensure modal stays centered */
        .modal.active {
            display: flex !important;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Improved scrollbar styling */
        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover {
            background: var(--secondary);
        }

        /* Add scrollbar to modal body */
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f8fafc;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        /* Responsive adjustments for view modal */
        @media (max-width: 768px) {
            #view-patient-modal .modal-content {
                width: 95% !important;
                margin: 10px;
                max-height: 90vh !important;
            }
            
            #view-patient-modal .modal-body {
                max-height: 70vh;
                padding: 20px;
            }
            
            .details-grid {
                grid-template-columns: 1fr !important;
            }
            
            .patient-header {
                flex-direction: column !important;
                text-align: center !important;
                gap: 15px !important;
            }
        }

        /* Additional styling for better content visibility */
        .patient-details-view {
            min-height: 300px;
        }

        .detail-item {
            min-height: 70px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Profile image in patient table */
        .patient-avatar img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary);
            overflow: hidden;
        }
    </style>
</head>
<body data-success-message="<?php  echo htmlspecialchars($success_message, ENT_QUOTES); ?>" 
      data-error-message="<?php  echo htmlspecialchars($error_message, ENT_QUOTES); ?>">
    
    <?php  include 'includes/admin-header.php'; ?>

    <!-- Overlay for mobile sidebar -->
    <div class="overlay"></div>

    <!-- System Messages Container -->
    <div id="systemMessages" class="system-message"></div>

     <!-- Admin Dashboard Layout -->
    <div class="admin-container">
        <?php  include 'includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="header-content">
                    <h1>Patients Management</h1>
                    <p>Manage patient records, appointments, and medical history</p>
                </div>
                <div class="header-actions">
                    <div class="date-display">
                        <i class="fas fa-calendar-alt" style="font-size: 1.2rem; color: var(--secondary);"></i>
                        <div class="clock-content">
                            <span id="admin-date">Loading...</span>
                            <span id="admin-time">00:00:00 AM</span>
                        </div>
                    </div>
                    <button class="btn btn-primary" id="add-patient-btn">
                        <i class="fas fa-plus"></i> Add Patient
                    </button>
                </div>
            </div>

                        <!-- Patient Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Patients</h3>
                        <div class="stat-number"><?php  echo $patientStats['total_patients']; ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-database"></i> All registered patients
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Active Patients</h3>
                        <div class="stat-number"><?php  echo $patientStats['active_patients']; ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-calendar-check"></i> With appointments in last 90 days
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-content">
                        <h3>New This Month</h3>
                        <div class="stat-number"><?php  echo $patientStats['new_this_month']; ?></div>
                        <div class="stat-change <?php  echo $patientStats['new_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php  if ($patientStats['new_change'] >= 0): ?>
                                <i class="fas fa-arrow-up"></i> <?php  echo $patientStats['new_change']; ?> from last month
                            <?php  else: ?>
                                <i class="fas fa-arrow-down"></i> <?php  echo abs($patientStats['new_change']); ?> from last month
                            <?php  endif; ?>
                        </div>
                        <div class="stat-subtitle">
                            Based on account creation date
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Inactive Patients</h3>
                        <div class="stat-number"><?php  echo $patientStats['inactive_patients']; ?></div>
                        <div class="stat-change">
                            <i class="fas fa-clock"></i> No appointments in 90+ days
                        </div>
                        <div class="stat-subtitle">
                            <?php  
                            $inactive_percentage = $patientStats['total_patients'] > 0 
                                ? round(($patientStats['inactive_patients'] / $patientStats['total_patients']) * 100, 1) 
                                : 0;
                            echo $inactive_percentage . '% of total patients';
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Patient Filter -->
            <div class="patient-filter">
                <form method="GET" id="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="status-filter">Status</label>
                            <select id="status-filter" name="status" class="filter-control">
                                <option value="all" <?php  echo ($filters['status'] === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="active" <?php  echo ($filters['status'] === 'active') ? 'selected' : ''; ?>>Active (Has pending, confirmed, or completed appointments in last 90 days)</option>
                                <option value="inactive" <?php  echo ($filters['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive (No appointments in last 90 days)</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="gender-filter">Gender</label>
                            <select id="gender-filter" name="gender" class="filter-control">
                                <option value="all" <?php  echo ($filters['gender'] === 'all') ? 'selected' : ''; ?>>All Genders</option>
                                <option value="male" <?php  echo ($filters['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php  echo ($filters['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php  echo ($filters['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="minor-filter">Age Group</label>
                            <select id="minor-filter" name="is_minor" class="filter-control">
                                <option value="all" <?php  echo ($filters['is_minor'] === 'all') ? 'selected' : ''; ?>>All Ages</option>
                                <option value="1" <?php  echo ($filters['is_minor'] === '1') ? 'selected' : ''; ?>>Minors (Under 18)</option>
                                <option value="0" <?php  echo ($filters['is_minor'] === '0') ? 'selected' : ''; ?>>Adults (18+)</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="search-filter">Search</label>
                            <input type="text" id="search-filter" name="search" class="filter-control" 
                                   placeholder="Search by name, ID, email or phone..."
                                   value="<?php  echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="admin-patients.php" class="btn">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Patient Table -->
            <div class="patient-table-container">
                <div class="table-header">
                    <h3>All Patients (<?php  echo $totalPatients; ?> total)</h3>
                    <div class="table-actions">
                        <button class="btn btn-success" onclick="exportPatients()">
                            <i class="fas fa-file-export"></i> Export
                        </button>
                    </div>
                </div>
                
                <div class="table-content">
                    <table class="patient-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Last Visit</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php  if (empty($patients)): ?>
                                <tr>
                                    <td colspan="7" class="no-patients">
                                        <i class="fas fa-user-times"></i>
                                        <p><?php  echo isset($filters['search']) ? 'No patients found matching your search' : 'No patients found'; ?></p>
                                        <button class="btn btn-primary" id="add-patient-btn-table">
                                            <i class="fas fa-plus"></i> Add First Patient
                                        </button>
                                    </td>
                                </tr>
                            <?php  else: ?>
                                <?php  foreach ($patients as $patient): ?>
                                    <?php 
                                    // Calculate age
                                    $birthdate = new DateTime($patient['birthdate']);
                                    $today = new DateTime();
                                    $age = $today->diff($birthdate)->y;
                                    
                                    // Determine age group
                                    $ageClass = '';
                                    if ($age < 12) $ageClass = 'age-child';
                                    elseif ($age < 18) $ageClass = 'age-child';
                                    elseif ($age < 65) $ageClass = 'age-adult';
                                    else $ageClass = 'age-senior';
                                    
                                    // Format last visit
                                    $lastVisit = 'Never';
                                    if ($patient['last_visit']) {
                                        $visitDate = new DateTime($patient['last_visit']);
                                        $lastVisit = $visitDate->format('M j, Y');
                                    }
                                    
                                    // Determine activity status using 90-day logic
                                    $isActive = $patientController->isPatientActive($patient['id']);
                                    $status = $isActive ? 'Active' : 'Inactive';
                                    $statusClass = $isActive ? 'status-active' : 'status-inactive';
                                    ?>
                                    
                                    <tr>
                                        <td>
                                            <div class="patient-info">
                                                <div class="patient-avatar">
                                                    <?php  
                                                    $displayImage = $patient['profile_image'];
                                                    if (!empty($displayImage) && strpos($displayImage, 'uploads/') === false) {
                                                        $displayImage = 'uploads/avatar/' . $displayImage;
                                                    }
                                                    ?>
                                                    <?php  if (!empty($displayImage)): ?>
                                                        <img src="<?php  echo URL_ROOT . htmlspecialchars($displayImage); ?>" 
                                                             alt="<?php  echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>"
                                                             onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\'fas fa-user\'></i>';">
                                                    <?php  else: ?>
                                                        <i class="fas fa-user"></i>
                                                    <?php  endif; ?>
                                                </div>
                                                <div class="patient-details">
                                                    <h4><?php  echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                                        <?php  if ($patient['parental_consent'] && $patient['is_minor']): ?>
                                                            <span class="consent-badge" title="Parental Consent Given"><i class="fas fa-check-circle"></i></span>
                                                        <?php  endif; ?>
                                                    </h4>
                                                    <p>ID: <?php  echo htmlspecialchars($patient['client_id']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?php  echo htmlspecialchars($patient['email']); ?></div>
                                            <div><?php  echo htmlspecialchars($patient['phone']); ?></div>
                                        </td>
                                        <td>
                                            <span class="age-badge <?php  echo $ageClass; ?>">
                                                <?php  echo $age; ?> years
                                            </span>
                                        </td>
                                        <td><?php  echo ucfirst($patient['gender']); ?></td>
                                        <td><?php  echo $lastVisit; ?></td>
                                        <td>
                                            <span class="patient-status <?php  echo $statusClass; ?>">
                                                <?php  echo $status; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="patient-actions">
                                                <!-- VIEW - View Details -->
                                                <button class="action-btn view" onclick="viewPatient(<?php  echo $patient['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php  endforeach; ?>
                            <?php  endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php  if ($totalPages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?php  echo (($currentPageNum - 1) * $limit) + 1; ?> to <?php  echo min($currentPageNum * $limit, $totalPatients); ?> of <?php  echo $totalPatients; ?> patients
                    </div>
                    <div class="pagination-controls">
                        <?php  if ($currentPageNum > 1): ?>
                            <a href="?<?php  echo http_build_query(array_merge($filters, ['page' => $currentPageNum - 1])); ?>" class="pagination-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php  endif; ?>
                        
                        <?php  for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php  if ($i == $currentPageNum): ?>
                                <span class="pagination-btn active"><?php  echo $i; ?></span>
                            <?php  else: ?>
                                <a href="?<?php  echo http_build_query(array_merge($filters, ['page' => $i])); ?>" class="pagination-btn"><?php  echo $i; ?></a>
                            <?php  endif; ?>
                        <?php  endfor; ?>
                        
                        <?php  if ($currentPageNum < $totalPages): ?>
                            <a href="?<?php  echo http_build_query(array_merge($filters, ['page' => $currentPageNum + 1])); ?>" class="pagination-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php  endif; ?>
                    </div>
                </div>
                <?php  endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Patient Modal -->
    <div class="modal" id="add-patient-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Patient</h3>
                <button class="close-modal">&times;</button>
            </div>
            <form id="add-patient-form" method="POST">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first-name">First Name *</label>
                            <input type="text" id="first-name" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="last-name">Last Name *</label>
                            <input type="text" id="last-name" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="birthdate">Date of Birth *</label>
                            <input type="date" id="birthdate" name="birthdate" class="form-control" required 
                                   max="<?php  echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender *</label>
                            <select id="gender" name="gender" class="form-control" required>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   pattern="09[0-9]{9}" title="Must be 11 digits starting with 09" required
                                   placeholder="09123456789">
                            <small class="form-text">11 digits starting with 09</small>
                        </div>
                    </div>
                    
                    <div class="form-group" id="parentalConsentContainer" style="display: none;">
                        <div class="form-check">
                            <input type="checkbox" id="parental_consent" name="parental_consent" class="form-check-input">
                            <label for="parental_consent" class="form-check-label">
                                Parental Consent Given (Required for minors under 18)
                            </label>
                        </div>
                    </div>
                    
                    <input type="hidden" name="action" value="create_patient">
                    <input type="hidden" name="csrf_token" value="<?php  echo $_SESSION['csrf_token']; ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" id="cancel-add-patient">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Patient
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Patient Details Modal -->
    <div class="modal" id="view-patient-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> Patient Details</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body" id="view-patient-body">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="close-view-patient">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- External JavaScript -->
    <script>
        // Inline JavaScript for critical functions
        document.addEventListener('DOMContentLoaded', function() {
            // Birthdate change handler for parental consent
            const birthdateInput = document.getElementById('birthdate');
            const parentalConsentContainer = document.getElementById('parentalConsentContainer');
            
            if (birthdateInput && parentalConsentContainer) {
                birthdateInput.addEventListener('change', function() {
                    updateParentalConsentVisibility(this.value, parentalConsentContainer, 'parental_consent');
                });
            }
        });

        function updateParentalConsentVisibility(birthdateString, containerElement, checkboxId) {
            if (!birthdateString) {
                containerElement.style.display = 'none';
                return;
            }
            
            const birthdate = new Date(birthdateString);
            const today = new Date();
            let age = today.getFullYear() - birthdate.getFullYear();
            const monthDiff = today.getMonth() - birthdate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
                age--;
            }
            
            if (age < 18) {
                containerElement.style.display = 'block';
            } else {
                containerElement.style.display = 'none';
                document.getElementById(checkboxId).checked = false;
            }
        }

        function exportPatients() {
            const filters = {
                status: document.getElementById('status-filter')?.value || 'all',
                gender: document.getElementById('gender-filter')?.value || 'all',
                is_minor: document.getElementById('minor-filter')?.value || 'all',
                search: document.getElementById('search-filter')?.value || ''
            };
            
            const queryString = new URLSearchParams(filters).toString();
            window.location.href = `?action=export_patients&${queryString}`;
        }
    </script>
    <script>window.URL_ROOT = "<?php echo URL_ROOT; ?>";</script>
    <script src="../assets/js/admin-patient.js"></script>
</body>
</html>