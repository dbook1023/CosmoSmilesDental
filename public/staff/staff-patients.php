<?php 
// public/assets/staff/staff-patients.php

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

// Handle API requests
if (isset($_GET['action'])) {
    require_once __DIR__ . '/../../src/Controllers/StaffPatientController.php';
    $patientController = new StaffPatientController();

    switch ($_GET['action']) {
        case 'get_patient_details':
            if (isset($_GET['id'])) {
                $patientController->handlePatientDetailsRequest();
            }
            exit;

        case 'download_template':
            // Download CSV template with address fields
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="patients_template.csv"');
            echo "first_name,last_name,birthdate,gender,address_line1,address_line2,city,state,postal_code,country,phone,email\n";
            echo "Juan,Dela Cruz,1990-05-15,male,123 Rizal St,,Manila,Metro Manila,1000,Philippines,09123456789,juan.delacruz@email.com\n";
            echo "Maria,Santos,1985-08-22,female,456 Mabini St,,Quezon City,Metro Manila,1100,Philippines,09123456788,maria.santos@email.com\n";
            exit;

        case 'export_patients':
            // Export patients to CSV with address fields
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="patients_export_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Patient ID', 'First Name', 'Last Name', 'Birthdate', 'Gender',
                'Address Line 1', 'Address Line 2', 'City', 'State', 'Postal Code', 'Country',
                'Phone', 'Email', 'Status', 'Date Created'
            ]);

            // Get all patients for export
            $filters = [
                'search' => $_GET['search'] ?? '',
                'gender' => $_GET['gender'] ?? 'all',
                'is_minor' => $_GET['is_minor'] ?? 'all'
            ];

            $patientsData = $patientController->getAllPatients($filters);
            $patients = $patientsData['patients'];

            foreach ($patients as $patient) {
                $isActive = $patientController->isPatientActive($patient['id']);
                $status = $isActive ? 'Active' : 'Inactive';

                fputcsv($output, [
                    $patient['client_id'],
                    $patient['first_name'],
                    $patient['last_name'],
                    $patient['birthdate'],
                    ucfirst($patient['gender']),
                    $patient['address_line1'] ?? '',
                    $patient['address_line2'] ?? '',
                    $patient['city'] ?? '',
                    $patient['state'] ?? '',
                    $patient['postal_code'] ?? '',
                    $patient['country'] ?? 'Philippines',
                    $patient['phone'],
                    $patient['email'],
                    $status,
                    $patient['created_at']
                ]);
            }

            fclose($output);
            exit;
    }
}

// Handle POST requests (import)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_patients') {
    require_once __DIR__ . '/../../src/Controllers/StaffPatientController.php';
    $patientController = new StaffPatientController();
    $patientController->handleImportPatients();
    exit;
}

require_once __DIR__ . '/../../src/Controllers/StaffPatientController.php';
$patientController = new StaffPatientController();

// Get staff user details from database
$staffUser = $patientController->getStaffUserById($_SESSION['staff_id']);

// Get patient statistics from database
$patientStats = $patientController->getPatientStatistics();

// Handle filters (removed city filter)
$filters = [
    'gender' => $_GET['gender'] ?? 'all',
    'is_minor' => $_GET['is_minor'] ?? 'all',
    'search' => $_GET['search'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? 'created_at',
    'sort_order' => $_GET['sort_order'] ?? 'desc',
    'page' => $_GET['page'] ?? 1
];

// Handle actions
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
                }
                else {
                    // Validate phone number
                    if (!$patientController->validatePhilippinePhone($_POST['phone'])) {
                        $_SESSION['error_message'] = 'Invalid phone number format. Must be 11 digits starting with 09.';
                    }
                    else {
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
                            'address_line1' => $_POST['address_line1'] ?? '',
                            'address_line2' => $_POST['address_line2'] ?? '',
                            'city' => $_POST['city'] ?? '',
                            'state' => $_POST['state'] ?? '',
                            'postal_code' => $_POST['postal_code'] ?? '',
                            'country' => $_POST['country'] ?? 'Philippines',
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
                            }
                            else {
                                $_SESSION['success_message'] = 'Patient created successfully!';
                            }
                        }
                        else {
                            $_SESSION['error_message'] = 'Unable to create patient. Please check if email already exists.';
                        }
                    }
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
$currentPage = $patientsData['page'];
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients Management - Cosmo Smiles Dental Staff</title>
    <link rel="icon" type="image/png" href="<?php echo clean_url('public/assets/images/logo1-white.png'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo clean_url('public/assets/css/staff-patients.css'); ?>">
</head>
<body>
    <!-- Staff Header -->
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

    <!-- Staff Dashboard Layout -->
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
                    
                    <a href="staff-patients.php" class="sidebar-item active">
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
                    <h1>Patients Management</h1>
                    <p>Manage patient records, appointments, and medical history</p>
                </div>
                <div class="header-actions">
                    <div class="date-display">
                        <i class="fas fa-calendar"></i>
                        <span id="current-date"><?php  echo date('F j, Y'); ?></span>
                    </div>
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
                            <i class="fas fa-database"></i> From database
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
                            <i class="fas fa-calendar-check"></i> Appointment in last 90 days
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
                                <i class="fas fa-arrow-up"></i> +<?php  echo $patientStats['new_change']; ?> from last month
                            <?php 
else: ?>
                                <i class="fas fa-arrow-down"></i> <?php  echo $patientStats['new_change']; ?> from last month
                            <?php 
endif; ?>
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
                            <i class="fas fa-clock"></i> No appointment in 90+ days
                        </div>
                    </div>
                </div>
            </div>

            <!-- Patient Filter -->
            <div class="patient-filter">
                <form method="GET" id="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="gender-filter">Gender</label>
                            <select id="gender-filter" name="gender" class="filter-control">
                                <option value="all" <?php  echo($filters['gender'] === 'all') ? 'selected' : ''; ?>>All Genders</option>
                                <option value="male" <?php  echo($filters['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php  echo($filters['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php  echo($filters['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="minor-filter">Age Group</label>
                            <select id="minor-filter" name="is_minor" class="filter-control">
                                <option value="all" <?php  echo($filters['is_minor'] === 'all') ? 'selected' : ''; ?>>All Ages</option>
                                <option value="1" <?php  echo($filters['is_minor'] === '1') ? 'selected' : ''; ?>>Minors (Under 18)</option>
                                <option value="0" <?php  echo($filters['is_minor'] === '0') ? 'selected' : ''; ?>>Adults (18+)</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="search-filter">Search</label>
                            <input type="text" id="search-filter" name="search" class="filter-control" 
                                   placeholder="Search by name, ID, email, phone..."
                                   value="<?php  echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="staff-patients.php" class="btn">
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
                        <!-- Table actions if needed -->
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
                                <th>Last Appointment</th>
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
                            <?php 
else: ?>
                                <?php  foreach ($patients as $patient): ?>
                                    <?php 
        // Calculate age
        $birthdate = new DateTime($patient['birthdate']);
        $today = new DateTime();
        $age = $today->diff($birthdate)->y;

        // Determine age group
        $ageClass = '';
        if ($age < 12)
            $ageClass = 'age-child';
        elseif ($age < 18)
            $ageClass = 'age-child';
        elseif ($age < 65)
            $ageClass = 'age-adult';
        else
            $ageClass = 'age-senior';

        // Determine activity status
        $isActive = $patientController->isPatientActive($patient['id']);
        $status = $isActive ? 'Active' : 'Inactive';
        $statusClass = $isActive ? 'status-active' : 'status-inactive';

        // Standardized image path using clean_url
        $displayImage = $patient['profile_image'];
        $fullImagePath = !empty($displayImage) ? clean_url('public/uploads/avatar/' . basename($displayImage)) : '';

        // Get last appointment date
        $lastAppointment = $patientController->getLastAppointmentDate($patient['id']);

        // Escape data for HTML
        $patientName = htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']);
        $patientId = htmlspecialchars($patient['client_id']);
        $patientEmail = htmlspecialchars($patient['email']);
        $patientPhone = htmlspecialchars($patient['phone']);
        $patientGender = ucfirst(htmlspecialchars($patient['gender']));
?>
                                    
                                    <tr>
                                        <td>
                                            <div class="patient-info">
                                                <div class="patient-avatar">
                                                    <?php  if (!empty($patient['profile_image'])): ?>
                                                        <img src="<?php  echo htmlspecialchars($fullImagePath); ?>" 
                                                             alt="<?php  echo $patientName; ?>"
                                                             onerror="this.onerror=null; this.parentNode.innerHTML='<i class=\'fas fa-user-circle\'></i>';">
                                                    <?php 
        else: ?>
                                                        <i class="fas fa-user-circle"></i>
                                                    <?php 
        endif; ?>
                                                </div>
                                                <div class="patient-details">
                                                    <h4><?php  echo $patientName; ?>
                                                        <?php  if ($patient['parental_consent'] && $patient['is_minor']): ?>
                                                            <span class="consent-badge" title="Parental Consent Given"><i class="fas fa-check-circle"></i></span>
                                                        <?php 
        endif; ?>
                                                    </h4>
                                                    <p>ID: <?php  echo $patientId; ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div><i class="fas fa-envelope"></i> <?php  echo $patientEmail; ?></div>
                                            <div><i class="fas fa-phone"></i> <?php  echo $patientPhone; ?></div>
                                        </td>
                                        <td>
                                            <span class="age-badge <?php  echo $ageClass; ?>">
                                                <?php  echo $age; ?> years
                                            </span>
                                        </td>
                                        <td><?php  echo $patientGender; ?></td>
                                        <td>
                                            <span class="last-appointment"><?php  echo htmlspecialchars($lastAppointment); ?></span>
                                        </td>
                                        <td>
                                            <span class="patient-status <?php  echo $statusClass; ?>">
                                                <?php  echo $status; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="patient-actions">
                                                <button class="action-btn view" onclick="viewPatient(<?php  echo $patient['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
    endforeach; ?>
                            <?php 
endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php  if ($totalPages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?php  echo(($currentPage - 1) * $limit) + 1; ?> to <?php  echo min($currentPage * $limit, $totalPatients); ?> of <?php  echo $totalPatients; ?> patients
                    </div>
                    <div class="pagination-controls">
                        <?php  if ($currentPage > 1): ?>
                            <a href="?<?php  echo http_build_query(array_merge($filters, ['page' => $currentPage - 1])); ?>" class="pagination-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php 
    endif; ?>
                        
                        <?php  for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php  if ($i == $currentPage): ?>
                                <span class="pagination-btn active"><?php  echo $i; ?></span>
                            <?php 
        else: ?>
                                <a href="?<?php  echo http_build_query(array_merge($filters, ['page' => $i])); ?>" class="pagination-btn"><?php  echo $i; ?></a>
                            <?php 
        endif; ?>
                        <?php 
    endfor; ?>
                        
                        <?php  if ($currentPage < $totalPages): ?>
                            <a href="?<?php  echo http_build_query(array_merge($filters, ['page' => $currentPage + 1])); ?>" class="pagination-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php 
    endif; ?>
                    </div>
                </div>
                <?php 
endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Patient Modal -->
    <div class="modal" id="add-patient-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Patient</h3>
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
                    
                    <!-- Address Fields -->
                    <div class="form-group">
                        <label for="address-line1">Address Line 1</label>
                        <input type="text" id="address-line1" name="address_line1" class="form-control" 
                               placeholder="Street address, P.O. box">
                    </div>
                    
                    <div class="form-group">
                        <label for="address-line2">Address Line 2</label>
                        <input type="text" id="address-line2" name="address_line2" class="form-control" 
                               placeholder="Apartment, suite, unit, building, floor">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" class="form-control" placeholder="City">
                        </div>
                        <div class="form-group">
                            <label for="state">State/Province</label>
                            <input type="text" id="state" name="state" class="form-control" placeholder="State/Province">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="postal-code">Postal Code</label>
                            <input type="text" id="postal-code" name="postal_code" class="form-control" placeholder="Postal code">
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" class="form-control" value="Philippines">
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
                    <button type="button" class="btn" id="cancel-add-patient">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Patient</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Patient Details Modal -->
    <div id="viewPatientModal" class="modal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3>Patient Details</h3>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- External JavaScript -->
    <script src="<?php echo clean_url('public/assets/js/staff-patients.js'); ?>"></script>
    <script>
        window.URL_ROOT = "<?php echo URL_ROOT; ?>";
        // Pass PHP data to JavaScript
        const phpData = {
            successMessage: <?php  echo json_encode($success_message); ?>,
            errorMessage: <?php  echo json_encode($error_message); ?>,
            csrfToken: <?php  echo json_encode($_SESSION['csrf_token'] ?? ''); ?>,
            currentDate: <?php  echo json_encode(date('F j, Y')); ?>,
            totalPatients: <?php  echo $patientStats['total_patients']; ?>,
            activePatients: <?php  echo $patientStats['active_patients']; ?>,
            newThisMonth: <?php  echo $patientStats['new_this_month']; ?>,
            inactivePatients: <?php  echo $patientStats['inactive_patients']; ?>
        };
    </script>
</body>
</html>