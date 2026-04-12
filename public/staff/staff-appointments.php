<?php 
// public/assets/staff/staff-appointments.php

// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if staff is logged in FIRST
if (!isset($_SESSION['staff_id']) || $_SESSION['staff_role'] !== 'receptionist') {
    header('Location: ../staff-login.php');
    exit;
}

// Fix the require path - adjust based on your actual file structure
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../src/Services/SessionService.php';

// Check for inactivity
SessionService::checkInactivity('staff');

// Handle API requests
if (isset($_GET['action'])) {
    ini_set('display_errors', 0);
    if (ob_get_length()) @ob_clean();
    require_once __DIR__ . '/../../src/Controllers/StaffAppointmentController.php';
    $appointmentController = new StaffAppointmentController();

    switch ($_GET['action']) {
        case 'get_appointment_details':
            if (isset($_GET['id'])) {
                $appointmentController->handleAppointmentDetailsRequest();
            }
            exit;

        case 'get_booked_slots':
            if (isset($_GET['date']) && isset($_GET['dentist_id'])) {
                $appointmentController->handleBookedSlotsRequest();
            }
            exit;

        case 'get_client_details':
            if (isset($_GET['client_id'])) {
                $client = $appointmentController->getClientDetails($_GET['client_id']);
                echo json_encode(['success' => !!$client, 'client' => $client]);
            }
            exit;

        case 'get_dentists':
            $dentists = $appointmentController->getDentists();
            echo json_encode(['success' => true, 'dentists' => $dentists]);
            exit;

        case 'get_services':
            $services = $appointmentController->getServices();
            echo json_encode(['success' => true, 'services' => $services]);
            exit;

        case 'fetch_appointments':
            $appointmentController->handleFetchAllRequest();
            exit;
    }
}

require_once __DIR__ . '/../../src/Controllers/StaffAppointmentController.php';
$appointmentController = new StaffAppointmentController();

// Get staff user details from database
$staffUser = $appointmentController->getStaffUserById($_SESSION['staff_id']);

// Handle filters
$filters = [
    'date_range' => $_GET['date_range'] ?? 'all',
    'status' => $_GET['status'] ?? 'all',
    'dentist_id' => $_GET['dentist_id'] ?? 'all',
    'search' => $_GET['search'] ?? '',
    'page' => $_GET['page'] ?? 1,
    'hide_no_show' => $_GET['hide_no_show'] ?? (!isset($_GET['status']) ? 'true' : 'false') // default to true on initial load
];

// Handle actions with SMS notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                if (isset($_POST['appointment_id']) && isset($_POST['status'])) {
                    $success = $appointmentController->updateAppointmentStatus(
                        $_POST['appointment_id'],
                        $_POST['status']
                    );
                    if ($success) {
                        $_SESSION['success_message'] = 'Appointment status updated successfully. SMS notification sent to patient.';
                    }
                    else {
                        $_SESSION['error_message'] = 'Unable to update appointment status';
                    }
                }
                break;

            case 'cancel_appointment':
                if (isset($_POST['appointment_id'])) {
                    $success = $appointmentController->updateAppointmentStatus(
                        $_POST['appointment_id'],
                        'cancelled'
                    );
                    if ($success) {
                        $_SESSION['success_message'] = 'Appointment cancelled successfully. SMS notification sent to patient.';
                    }
                    else {
                        $_SESSION['error_message'] = 'Unable to cancel appointment';
                    }
                }
                break;

            case 'confirm_appointment':
                if (isset($_POST['appointment_id'])) {
                    $success = $appointmentController->updateAppointmentStatus(
                        $_POST['appointment_id'],
                        'confirmed'
                    );
                    if ($success) {
                        $_SESSION['success_message'] = 'Appointment confirmed successfully. SMS notification sent to patient.';
                    }
                    else {
                        $_SESSION['error_message'] = 'Unable to confirm appointment';
                    }
                }
                break;

            case 'edit_appointment':
                if (isset($_POST['appointment_id'])) {
                    // Validate date with time restrictions
                    $dateValidation = $appointmentController->validateAppointmentDateTime(
                        $_POST['appointment_date'],
                        $_POST['appointment_time']
                    );

                    if (!$dateValidation['valid']) {
                        $_SESSION['error_message'] = $dateValidation['message'];
                    }
                    else {
                        // Get service price from service selection
                        $service_price = 0;
                        if (isset($_POST['service_id']) && is_numeric($_POST['service_id'])) {
                            $services = $appointmentController->getServices();
                            foreach ($services as $service) {
                                if ($service['id'] == $_POST['service_id']) {
                                    $service_price = $service['price'];
                                    break;
                                }
                            }
                        }

                        $appointmentData = [
                            'client_id' => $_POST['client_id'],
                            'dentist_id' => $_POST['dentist_id'],
                            'service_id' => $_POST['service_id'],
                            'appointment_date' => $_POST['appointment_date'],
                            'appointment_time' => $_POST['appointment_time'],
                            'status' => $_POST['status'],
                            'notes' => $_POST['notes'] ?? '',
                            'duration_minutes' => $_POST['duration_minutes'] ?? 60,
                            'payment_type' => $_POST['payment_type'] ?? 'cash',
                            'service_price' => $service_price,
                            'patient_first_name' => $_POST['patient_first_name'] ?? '',
                            'patient_last_name' => $_POST['patient_last_name'] ?? '',
                            'patient_phone' => $_POST['patient_phone'] ?? '',
                            'patient_email' => $_POST['patient_email'] ?? ''
                        ];

                        $success = $appointmentController->updateAppointment($_POST['appointment_id'], $appointmentData);
                        if ($success) {
                            $statusChanged = false;
                            // Check if status changed to confirmed/cancelled
                            if (in_array($_POST['status'], ['confirmed', 'cancelled'])) {
                                $statusChanged = true;
                            }

                            $_SESSION['success_message'] = 'Appointment updated successfully' .
                                ($statusChanged ? '. SMS notification sent to patient.' : '');
                        }
                        else {
                            $_SESSION['error_message'] = 'Unable to update appointment';
                        }
                    }
                }
                break;

            case 'create_appointment':
                // Validate date with time restrictions
                $dateValidation = $appointmentController->validateAppointmentDateTime(
                    $_POST['appointment_date'],
                    $_POST['appointment_time']
                );

                if (!$dateValidation['valid']) {
                    $_SESSION['error_message'] = $dateValidation['message'];
                }
                else {
                    // Get service price from service selection
                    $service_price = 0;
                    if (isset($_POST['service_id']) && is_numeric($_POST['service_id'])) {
                        $services = $appointmentController->getServices();
                        foreach ($services as $service) {
                            if ($service['id'] == $_POST['service_id']) {
                                $service_price = $service['price'];
                                break;
                            }
                        }
                    }

                    $appointmentData = [
                        'client_id' => $_POST['client_id'],
                        'dentist_id' => $_POST['dentist_id'],
                        'service_id' => $_POST['service_id'],
                        'appointment_date' => $_POST['appointment_date'],
                        'appointment_time' => $_POST['appointment_time'],
                        'status' => 'pending', // Default status
                        'notes' => $_POST['notes'] ?? '',
                        'duration_minutes' => $_POST['duration_minutes'] ?? 60,
                        'payment_type' => $_POST['payment_type'] ?? 'cash',
                        'service_price' => $service_price,
                        'patient_first_name' => $_POST['patient_first_name'] ?? '',
                        'patient_last_name' => $_POST['patient_last_name'] ?? '',
                        'patient_phone' => $_POST['patient_phone'] ?? '',
                        'patient_email' => $_POST['patient_email'] ?? ''
                    ];

                    $success = $appointmentController->createAppointment($appointmentData);
                    if ($success) {
                        $_SESSION['success_message'] = 'Appointment created successfully';
                    }
                    else {
                        $_SESSION['error_message'] = 'Unable to create appointment. Please check if patient exists.';
                    }
                }
                break;
        }

        // Redirect to avoid form resubmission
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($filters));
        exit;
    }
}

// Get appointments data
$appointmentsData = $appointmentController->getAllAppointments($filters);
$appointments = $appointmentsData['appointments'];
$totalAppointments = $appointmentsData['total'];
$currentPage = $appointmentsData['page'];
$limit = $appointmentsData['limit'];

// Get dentists for filter
$dentists = $appointmentController->getDentists();

// Get services for edit form
$services = $appointmentController->getServices();

// Get all clients for dropdown
$clients = $appointmentController->getAllClients();

// Calculate pagination
$totalPages = ceil($totalAppointments / $limit);

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff - Appointments Management - Cosmo Smiles Dental</title>
    <link rel="icon" type="image/png" href="<?php echo clean_url('public/assets/images/logo1-white.png'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo clean_url('public/assets/css/staff-appointments.css'); ?>">
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

    <!-- System Messages Container -->
    <div id="systemMessages" class="system-message"></div>

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
                    
                    <a href="staff-appointments.php" class="sidebar-item active">
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
                        <span class="profile-name">
                            <?php
if ($staffUser) {
    echo htmlspecialchars($staffUser['first_name'] . ' ' . $staffUser['last_name']);
}
else {
    echo 'Receptionist';
}
?>
                        </span>
                        <span class="profile-role">
                            <?php
if ($staffUser) {
    echo htmlspecialchars(ucfirst(str_replace('_', ' ', $staffUser['role'])));
}
else {
    echo 'Receptionist';
}
?>
                        </span>
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
                    <h1>Appointments Management</h1>
                    <p>Manage and track all patient appointments</p>
                </div>
                <div class="header-actions">
                    <div class="date-display">
                        <i class="fas fa-calendar"></i>
                        <span id="current-date"><?php echo date('F j, Y'); ?></span>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="openCreateAppointmentModal()">
                        <i class="fas fa-plus"></i> Create Appointment
                    </button>
                </div>
            </div>

            <!-- Appointments Filter -->
            <form method="GET" class="appointments-filter" id="appointments-filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="date-filter">Date Range</label>
                        <select id="date-filter" name="date_range" class="filter-control">
                            <option value="all" <?php echo($filters['date_range'] === 'all') ? 'selected' : ''; ?>>All Dates</option>
                            <option value="today" <?php echo($filters['date_range'] === 'today') ? 'selected' : ''; ?>>Today</option>
                            <option value="tomorrow" <?php echo($filters['date_range'] === 'tomorrow') ? 'selected' : ''; ?>>Tomorrow</option>
                            <option value="this-week" <?php echo($filters['date_range'] === 'this-week') ? 'selected' : ''; ?>>This Week</option>
                            <option value="next-week" <?php echo($filters['date_range'] === 'next-week') ? 'selected' : ''; ?>>Next Week</option>
                            <option value="this-month" <?php echo($filters['date_range'] === 'this-month') ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status-filter">Status</label>
                        <select id="status-filter" name="status" class="filter-control">
                            <option value="all" <?php echo($filters['status'] === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo($filters['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo($filters['status'] === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo($filters['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo($filters['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="no_show" <?php echo($filters['status'] === 'no_show') ? 'selected' : ''; ?>>No Show</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="dentist-filter">Dentist</label>
                        <select id="dentist-filter" name="dentist_id" class="filter-control">
                            <option value="all" <?php echo($filters['dentist_id'] === 'all') ? 'selected' : ''; ?>>All Dentists</option>
                            <?php foreach ($dentists as $dentist): ?>
                                <option value="<?php echo $dentist['id']; ?>" <?php echo($filters['dentist_id'] == $dentist['id']) ? 'selected' : ''; ?>>
                                    Dr. <?php echo htmlspecialchars($dentist['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group hide-noshow-group">
                        <label style="opacity: 0; display: block; margin-bottom: 5px;">Archive</label>
                        <style>
                            .toggle-switch-container { display: flex; align-items: center; gap: 8px; }
                            .theme-switch { position: relative; display: inline-block; width: 36px; height: 20px; margin: 0; }
                            .theme-switch input { opacity: 0; width: 0; height: 0; }
                            .switch-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 20px; }
                            .switch-slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
                            input:checked + .switch-slider { background-color: var(--primary, #3b82f6); }
                            input:checked + .switch-slider:before { transform: translateX(16px); }
                            .toggle-label { font-size: 0.85rem; color: var(--text-color); cursor: pointer; font-weight: 500; margin: 0; user-select: none; }
                        </style>
                        <div class="toggle-switch-container" style="margin-top: -3px;">
                            <label class="theme-switch" title="Toggle visibility of No-Show appointments">
                                <input type="checkbox" name="hide_no_show" value="true" <?php echo ($filters['hide_no_show'] === 'true') ? 'checked' : ''; ?> id="staff-hide-noshow">
                                <span class="switch-slider"></span>
                            </label>
                            <label class="toggle-label" for="staff-hide-noshow">Hide No-Show Archive</label>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="staff-appointments.php" class="btn">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Search Bar -->
            <div class="search-container">
                <div class="search-box">
                    <input type="text" id="searchInput" class="search-input" 
                           placeholder="Search appointments by ID, patient ID, name, phone, email, dentist, or service..."
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                    <button type="button" id="searchBtn" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>

            <!-- Appointments Table -->
            <div class="appointments-table-container">
                <div class="table-header">
                    <h3>All Appointments (<?php echo $totalAppointments; ?> total)</h3>
                    <div class="table-actions">
                        <!-- Optional: Add export or other actions here -->
                    </div>
                </div>
                
                <div class="table-content">
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th>Appointment ID</th>
                                <th>Patient ID</th>
                                <th>Patient</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Dentist</th>
                                <th>Service</th>
                                <th>Payment Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="appointments-table-body">
                            <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="10" class="no-appointments">
                                        <i class="fas fa-calendar-times"></i>
                                        <p><?php echo isset($filters['search']) ? 'No appointments found matching your search' : 'No appointments found'; ?></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $appointment): ?>
                                    <?php
        // Check if appointment is in the past
        $appointmentDateTime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
        $now = new DateTime();
        $isPast = $appointmentDateTime < $now;
?>
                                    
                                    <tr class="<?php echo $isPast ? 'past-appointment-indicator' : ''; ?>">
                                        <td><?php echo $appointment['appointment_id'] ?? 'N/A'; ?></td>
                                        <td><?php echo htmlspecialchars($appointment['patient_client_id'] ?? 'N/A'); ?></td>
                                        <td>
                                            <div class="patient-info">
                                                <div class="patient-avatar">
                                                    <?php 
                                                    $displayImage = $appointment['patient_image'];
                                                    if (!empty($displayImage) && strpos($displayImage, 'uploads/') === false) {
                                                        $displayImage = 'uploads/avatar/' . $displayImage;
                                                    }
                                                    ?>
                                                    <?php if(!empty($displayImage)): ?>
                                                        <img src="<?php echo URL_ROOT . htmlspecialchars($displayImage); ?>" 
                                                             alt="Avatar" 
                                                             style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"
                                                             onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\'fas fa-user\'></i>';">
                                                    <?php else: ?>
                                                        <i class="fas fa-user"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="patient-details">
                                                    <h4><?php echo htmlspecialchars($appointment['patient_full_name'] ?? 'Unknown Patient'); ?></h4>
                                                    <p><?php echo htmlspecialchars($appointment['patient_phone'] ?? 'No Phone'); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></td>
                                        <td><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['dentist_name'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['service_name'] ?? 'Dental Service'); ?></td>
                                        <td>
                                            <span class="payment-type payment-<?php echo $appointment['payment_type'] ?? 'cash'; ?>">
                                                <?php echo ucfirst($appointment['payment_type'] ?? 'cash'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="appointment-status status-<?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="appointment-actions">
                                                <!-- READ - View Details -->
                                                <button class="action-btn view" onclick="viewAppointment(<?php echo $appointment['db_id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                
                                                <!-- UPDATE - Edit Appointment -->
                                                <button class="action-btn edit" 
                                                    onclick="<?php echo $isPast ? 'showPastAppointmentError()' : 'editAppointment(' . $appointment['db_id'] . ')'; ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                
                                                <!-- Quick Actions -->
                                                <?php if ($isPast): ?>
                                                    <!-- For past appointments, show actions with error messages -->
                                                    <?php if ($appointment['status'] === 'pending'): ?>
                                                        <button class="action-btn confirm" onclick="showPastAppointmentError('confirm')">
                                                            <i class="fas fa-check"></i> Confirm
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($appointment['status'] !== 'cancelled' && $appointment['status'] !== 'completed'): ?>
                                                        <button class="action-btn cancel" onclick="showPastAppointmentError('cancel')">
                                                            <i class="fas fa-times"></i> Cancel
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <!-- For future appointments, keep the normal behavior -->
                                                    <?php if ($appointment['status'] === 'pending'): ?>
                                                        <button class="action-btn confirm" onclick="showConfirmation('confirm', <?php echo $appointment['db_id'] ?? 0; ?>, '<?php echo htmlspecialchars(addslashes($appointment['patient_full_name'] ?? 'Unknown Patient')); ?>')">
                                                            <i class="fas fa-check"></i> Confirm
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($appointment['status'] !== 'cancelled' && $appointment['status'] !== 'completed'): ?>
                                                        <button class="action-btn cancel" onclick="showConfirmation('cancel', <?php echo $appointment['db_id'] ?? 0; ?>, '<?php echo htmlspecialchars(addslashes($appointment['patient_full_name'] ?? 'Unknown Patient')); ?>')">
                                                            <i class="fas fa-times"></i> Cancel
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div id="pagination-container">
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing <?php echo(($currentPage - 1) * $limit) + 1; ?> to <?php echo min($currentPage * $limit, $totalAppointments); ?> of <?php echo $totalAppointments; ?> appointments
                        </div>
                        <div class="pagination-controls">
                            <?php if ($currentPage > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $currentPage - 1])); ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == $currentPage): ?>
                                    <span class="pagination-btn active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $i])); ?>" class="pagination-btn"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $currentPage + 1])); ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- View Appointment Details Modal -->
    <div id="viewAppointmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Appointment Details</h3>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-actions">
                <button type="button" class="btn" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Appointment Modal -->
    <div id="editAppointmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Appointment</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editAppointmentForm" method="POST">
                <div class="modal-body" id="editModalBody">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-actions">
                    <input type="hidden" name="appointment_id" id="editAppointmentId" value="">
                    <input type="hidden" name="action" value="edit_appointment">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Appointment Modal -->
    <div id="createAppointmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Appointment</h3>
                <button class="close-modal" onclick="closeCreateAppointmentModal()">&times;</button>
            </div>
            <form id="createAppointmentForm" method="POST">
                <div class="modal-body">
                    <!-- Availability Warning -->
                    <div id="no_dentist_warning" class="message-error" style="display:none; margin-bottom:15px; padding: 10px; border-radius: 4px; border: 1px solid #e74c3c;">
                        <i class="fas fa-exclamation-triangle"></i> <strong>No dentists are currently checked in.</strong> 
                        Appointments can only be booked once the dentistâ€™s availability is enabled, please come back again when the dentist is available.
                    </div>
                    
                    <div class="form-group">
                        <label for="create_client_id">Patient ID *</label>
                        <div class="patient-id-input-container">
                            <input type="text" id="create_client_id" name="client_id" class="form-control" 
                                   placeholder="Enter Patient ID (e.g., PAT0001)" required>
                            <button type="button" id="checkPatientBtn" class="btn">
                                <i class="fas fa-search"></i> Check
                            </button>
                        </div>
                        <div id="patient_details" class="patient-details-box">
                            <div class="detail-row">
                                <div class="detail-label">Name:</div>
                                <div class="detail-value" id="patient_name"></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Phone:</div>
                                <div class="detail-value" id="patient_phone"></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Email:</div>
                                <div class="detail-value" id="patient_email"></div>
                            </div>
                        </div>
                        <div id="patient_error" class="message-error"></div>
                    </div>
                    
                    <!-- Hidden fields for patient details -->
                    <input type="hidden" id="create_patient_first_name" name="patient_first_name">
                    <input type="hidden" id="create_patient_last_name" name="patient_last_name">
                    <input type="hidden" id="create_patient_phone" name="patient_phone">
                    <input type="hidden" id="create_patient_email" name="patient_email">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_dentist_id">Dentist *</label>
                            <select id="create_dentist_id" name="dentist_id" class="form-control" required>
                                <option value="">Select Dentist</option>
                                <?php 
                                $checkedInCount = 0;
                                foreach ($dentists as $d) {
                                    if (isset($d['is_checked_in']) && $d['is_checked_in'] == 1) {
                                        $checkedInCount++;
                                    }
                                }
                                if ($checkedInCount === 1): 
                                ?>
                                <option value="any">Any Available Dentist</option>
                                <?php endif; ?>
                                 <?php foreach ($dentists as $dentist): ?>
                                    <?php if (isset($dentist['is_checked_in']) && $dentist['is_checked_in'] == 1): ?>
                                        <option value="<?php echo $dentist['id']; ?>">
                                            Dr. <?php echo htmlspecialchars($dentist['name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="create_service_id">Service *</label>
                            <select id="create_service_id" name="service_id" class="form-control" required>
                                <option value="">Select Service</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>" data-duration="<?php echo $service['duration_minutes']; ?>">
                                        <?php echo htmlspecialchars($service['name']); ?> (â‚±<?php echo $service['price']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_appointment_date">Date *</label>
                            <input type="date" id="create_appointment_date" name="appointment_date" class="form-control" required 
                                   min="<?php echo date('Y-m-d'); ?>">
                            <div id="date_validation" class="message-info"></div>
                        </div>
                        <div class="form-group">
                            <label for="create_appointment_time">Time *</label>
                            <select id="create_appointment_time" name="appointment_time" class="form-control" required>
                                <option value="">Select Date and Dentist First</option>
                            </select>
                            <div id="create_time_slots_loading" class="loading-message">
                                <i class="fas fa-spinner fa-spin"></i> Loading available time slots...
                            </div>
                            <div id="create_time_slots_message" class="message-info"></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_payment_type">Payment Type</label>
                            <select id="create_payment_type" name="payment_type" class="form-control">
                                <option value="cash">Cash</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="create_duration_minutes">Duration (minutes)</label>
                            <input type="number" id="create_duration_minutes" name="duration_minutes" class="form-control" value="60" min="60" step="60" readonly>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_notes">Notes</label>
                        <textarea id="create_notes" name="notes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
                    </div>
                    
                    <input type="hidden" name="action" value="create_appointment">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" onclick="closeCreateAppointmentModal()">Cancel</button>
                    <button type="submit" id="createSubmitBtn" class="btn btn-primary" disabled>Create Appointment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal for Status Changes -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="confirmationTitle">Confirm Action</h3>
                <button class="close-modal" onclick="closeConfirmationModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="confirmationMessage">Are you sure you want to perform this action?</p>
                <p class="sms-notice"><i class="fas fa-sms"></i> An SMS notification will be sent to the patient.</p>
            </div>
            <div class="modal-actions">
                <form id="confirmationForm" method="POST">
                    <input type="hidden" name="appointment_id" id="confirmAppointmentId" value="">
                    <input type="hidden" name="action" id="confirmAction" value="">
                    <input type="hidden" name="status" id="confirmStatus" value="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="button" class="btn" onclick="closeConfirmationModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="confirmButton">Confirm</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Pass PHP data to JavaScript
        window.phpData = {
            successMessage: <?php echo json_encode($success_message); ?>,
            errorMessage: <?php echo json_encode($error_message); ?>,
            dentists: <?php echo json_encode($dentists ?: []); ?>,
            services: <?php echo json_encode($services ?: []); ?>,
            clients: <?php echo json_encode($clients ?: []); ?>,
            currentDate: <?php echo json_encode(date('F j, Y')); ?>,
            csrfToken: <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>
        };
    </script>
    <script src="<?php echo clean_url('public/assets/js/staff-appointments.js'); ?>"></script>
    <script>
        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            // Set current date
            const dateEl = document.getElementById('current-date');
            if (dateEl) dateEl.textContent = window.phpData.currentDate;

            // Show PHP messages
            if (window.phpData.successMessage) {
                setTimeout(() => { if (typeof showSystemMessage === 'function') showSystemMessage(window.phpData.successMessage, 'success'); }, 500);
            }
            if (window.phpData.errorMessage) {
                setTimeout(() => { if (typeof showSystemMessage === 'function') showSystemMessage(window.phpData.errorMessage, 'error'); }, 500);
            }

            // Initialize patient check functionality
            const checkPatientBtn = document.getElementById('checkPatientBtn');
            const createClientId = document.getElementById('create_client_id');
            const patientDetails = document.getElementById('patient_details');
            const patientError = document.getElementById('patient_error');
            const createSubmitBtn = document.getElementById('createSubmitBtn');

            if (checkPatientBtn && createClientId) {
                checkPatientBtn.addEventListener('click', function() {
                    const clientId = createClientId.value.trim();
                    if (!clientId) {
                        if (patientError) {
                            patientError.textContent = 'Please enter a Patient ID first';
                            patientError.style.display = 'block';
                        }
                        if (patientDetails) patientDetails.style.display = 'none';
                        if (createSubmitBtn) createSubmitBtn.disabled = true;
                        return;
                    }

                    // Show loading
                    if (patientError) patientError.style.display = 'none';
                    if (patientDetails) patientDetails.style.display = 'none';
                    checkPatientBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
                    checkPatientBtn.disabled = true;

                    // Fetch patient details
                    fetch(`staff-appointments.php?action=get_client_details&client_id=${clientId}`)
                        .then(response => response.json())
                        .then(data => {
                            checkPatientBtn.innerHTML = '<i class="fas fa-search"></i> Check';
                            checkPatientBtn.disabled = false;

                            if (data.success && data.client) {
                                const client = data.client;
                                if (document.getElementById('patient_name')) document.getElementById('patient_name').textContent = client.first_name + ' ' + client.last_name;
                                if (document.getElementById('patient_phone')) document.getElementById('patient_phone').textContent = client.phone;
                                if (document.getElementById('patient_email')) document.getElementById('patient_email').textContent = client.email;
                                if (patientDetails) patientDetails.style.display = 'block';
                                if (patientError) patientError.style.display = 'none';
                                if (document.getElementById('create_patient_first_name')) document.getElementById('create_patient_first_name').value = client.first_name;
                                if (document.getElementById('create_patient_last_name')) document.getElementById('create_patient_last_name').value = client.last_name;
                                if (document.getElementById('create_patient_phone')) document.getElementById('create_patient_phone').value = client.phone;
                                if (document.getElementById('create_patient_email')) document.getElementById('create_patient_email').value = client.email;
                                if (createSubmitBtn) createSubmitBtn.disabled = false;
                            } else {
                                if (patientError) {
                                    patientError.textContent = 'Patient not found. Please check the Patient ID.';
                                    patientError.style.display = 'block';
                                }
                                if (patientDetails) patientDetails.style.display = 'none';
                                if (createSubmitBtn) createSubmitBtn.disabled = true;
                            }
                        })
                        .catch(error => {
                            checkPatientBtn.innerHTML = '<i class="fas fa-search"></i> Check';
                            checkPatientBtn.disabled = false;
                            if (patientError) {
                                patientError.textContent = 'Error checking patient. Please try again.';
                                patientError.style.display = 'block';
                            }
                            if (createSubmitBtn) createSubmitBtn.disabled = true;
                        });
                });

                createClientId.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        checkPatientBtn.click();
                    }
                });
            }
        });

        // Modal Control Functions (Not in external JS)
        function closeEditModal() {
            const modal = document.getElementById('editAppointmentModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        function closeViewModal() {
            const modal = document.getElementById('viewAppointmentModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        function closeConfirmationModal() {
            const modal = document.getElementById('confirmationModal');
            if (modal) modal.style.display = 'none';
        }

        // Additional polling for dentist availability
        function refreshDentistList() {
            fetch('staff-appointments.php?action=get_dentists')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.dentists) {
                        window.phpData.dentists = data.dentists;
                        document.dispatchEvent(new CustomEvent('dentistsRefreshed', { detail: data.dentists }));
                    }
                })
                .catch(error => console.error('Error refreshing dentists:', error));
        }
        setInterval(refreshDentistList, 10000); // Poll every 10 seconds

        // Auto-submit filter form when no-show toggle is changed
        const noShowToggle = document.getElementById('staff-hide-noshow');
        if (noShowToggle) {
            noShowToggle.addEventListener('change', function() {
                document.getElementById('appointments-filter-form').submit();
            });
        }
    </script>
</body>
</html>