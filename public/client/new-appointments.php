<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../src/Controllers/AppointmentController.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    // Redirect to login page
    header("Location: login.php");
    exit();
}

// Mandatory Medical History Check
$database = new Database();
$db = $database->getConnection();
$clientDbId = $_SESSION['client_id'] ?? null;

if ($clientDbId) {
    try {
        $stmt = $db->prepare("SELECT medical_history_status FROM clients WHERE id = :id");
        $stmt->execute([':id' => $clientDbId]);
        $clientStatus = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($clientStatus && $clientStatus['medical_history_status'] === 'pending') {
            // Redirect to patient records to fill out medical exam
            header("Location: patient-records.php?exam=pending");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error checking medical history status: " . $e->getMessage());
    }
}

// Enhanced session handling with security
$isLoggedIn = isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true;

// Set user name if logged in, otherwise empty
$userName = '';
$clientDbId = null; // auto_increment id
$clientId = null;   // client_id (varchar) for display

// Initialize userData array with empty values
$userData = [
    'first_name' => '',
    'last_name' => '',
    'phone' => '',
    'email' => ''
];

// Debug logging
error_log("=== NEW APPOINTMENTS PAGE LOADED ===");
error_log("Is logged in: " . ($isLoggedIn ? 'YES' : 'NO'));
error_log("Session data: " . print_r($_SESSION, true));

// Since we've already checked login above, we can be confident user is logged in
if ($isLoggedIn) {
    $clientDbId = $_SESSION['client_id'] ?? null;
    error_log("Client DB ID from session: " . ($clientDbId ?: 'NULL'));
    
    // Try to get user data from session first
    $userData = [
        'first_name' => $_SESSION['client_first_name'] ?? '',
        'last_name' => $_SESSION['client_last_name'] ?? '',
        'phone' => $_SESSION['client_phone'] ?? '',
        'email' => $_SESSION['client_email'] ?? ''
    ];
    
    // If we have client ID, fetch data from database
    if ($clientDbId) {
        try {
            // Create Database instance and get connection
            $database = new Database();
            $db = $database->getConnection();
            
            if ($db) {
                // Get client data including the client_id (varchar)
                $stmt = $db->prepare("SELECT client_id, phone, first_name, last_name, email FROM clients WHERE id = :id");
                $stmt->execute([':id' => $clientDbId]);
                $clientData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($clientData) {
                    error_log("Client data fetched from DB: " . print_r($clientData, true));
                    
                    // Get the display client_id (varchar) from the database
                    $clientId = $clientData['client_id'] ?? null;
                    error_log("Client ID from DB: " . ($clientId ?: 'NULL/EMPTY'));
                    
                    // If client_id is empty, generate one
                    if (empty($clientId)) {
                        error_log("Client ID is empty, generating new one...");
                        $clientId = "PAT" . str_pad($clientDbId, 6, '0', STR_PAD_LEFT);
                        error_log("Generated Client ID: " . $clientId);
                        
                        // Update the database with generated client_id
                        $updateStmt = $db->prepare("UPDATE clients SET client_id = :client_id WHERE id = :id");
                        $updateStmt->execute([
                            ':client_id' => $clientId,
                            ':id' => $clientDbId
                        ]);
                        error_log("Database updated with new client_id");
                    }
                    
                    // Store in session for later use
                    $_SESSION['client_client_id'] = $clientId;
                    error_log("Client ID stored in session: " . $clientId);
                    
                    // Update any missing fields
                    if (empty($userData['first_name']) && !empty($clientData['first_name'])) {
                        $userData['first_name'] = $clientData['first_name'];
                        $_SESSION['client_first_name'] = $clientData['first_name'];
                    }
                    if (empty($userData['last_name']) && !empty($clientData['last_name'])) {
                        $userData['last_name'] = $clientData['last_name'];
                        $_SESSION['client_last_name'] = $clientData['last_name'];
                    }
                    if (empty($userData['phone']) && !empty($clientData['phone'])) {
                        $userData['phone'] = $clientData['phone'];
                        $_SESSION['client_phone'] = $clientData['phone'];
                    }
                    if (empty($userData['email']) && !empty($clientData['email'])) {
                        $userData['email'] = $clientData['email'];
                        $_SESSION['client_email'] = $clientData['email'];
                    }
                } else {
                    // Client not found in database
                    error_log("ERROR: Client not found in database with id: " . $clientDbId);
                    $clientId = null;
                }
            } else {
                error_log("ERROR: Database connection failed");
            }
        } catch (PDOException $e) {
            // Log error but continue with session data
            error_log("ERROR fetching client data from database: " . $e->getMessage());
        }
    } else {
        error_log("ERROR: clientDbId is null, cannot fetch client data");
    }
    
    $userName = trim($userData['first_name'] . ' ' . $userData['last_name']);
    
    // If both names are empty, show generic name
    if (empty($userName)) {
        $userName = 'My Account';
    }

    // Get user profile image if logged in
    $profileImage = null;
    if ($isLoggedIn && $clientDbId) {
        $sql = "SELECT profile_image FROM clients WHERE id = :id LIMIT 1";
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $clientDbId);
            $stmt->execute();
            $userDataFromDb = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userDataFromDb && !empty($userDataFromDb['profile_image'])) {
                $profileImage = $userDataFromDb['profile_image'];
            }
        } catch (Exception $e) {
            error_log("Error fetching profile image: " . $e->getMessage());
        }
    }
} else {
    $userName = 'My Account';
}

error_log("Final Client ID for display: " . ($clientId ?: 'NULL'));
error_log("Is logged in: " . ($isLoggedIn ? 'YES' : 'NO'));

$appointmentController = new AppointmentController();

// Handle reschedule data
$rescheduleData = [];
$isReschedule = false;
$rescheduleAppointmentId = null;

// DEBUG: Check if reschedule parameter exists
error_log("Checking for reschedule parameter: " . (isset($_GET['reschedule']) ? 'YES' : 'NO'));
error_log("Reschedule value: " . ($_GET['reschedule'] ?? 'NOT SET'));

if (isset($_GET['reschedule']) && $isLoggedIn && $clientDbId) {
    $rescheduleAppointmentId = $_GET['reschedule'];
    $isReschedule = true;
    
    error_log("=== RESCHEDULE MODE ACTIVATED ===");
    error_log("Reschedule Appointment ID: " . $rescheduleAppointmentId);
    error_log("Client DB ID: " . $clientDbId);
    error_log("Client ID (varchar): " . ($clientId ?: 'NULL'));
    
    // IMPORTANT: We need to use the NEW method getRescheduleAppointmentDetails
    // which accepts the varchar client_id directly
    if ($clientId) {
        $result = $appointmentController->getRescheduleAppointmentDetails($rescheduleAppointmentId, $clientId);
        
        error_log("Result from getRescheduleAppointmentDetails: " . print_r($result, true));
        
        if ($result['success']) {
            $rescheduleData = $result['appointment'];
            
            error_log("Reschedule data fetched: " . print_r($rescheduleData, true));
            
            // Pre-fill user data from appointment (these fields should be readonly)
            $userData['first_name'] = $rescheduleData['patient_first_name'] ?? $userData['first_name'];
            $userData['last_name'] = $rescheduleData['patient_last_name'] ?? $userData['last_name'];
            $userData['phone'] = $rescheduleData['patient_phone'] ?? $userData['phone'];
            $userData['email'] = $rescheduleData['patient_email'] ?? $userData['email'];
            
            $userName = trim($userData['first_name'] . ' ' . $userData['last_name']);
            
            // Debug log what we found
            error_log("Pre-filled data:");
            error_log("  Service ID: " . ($rescheduleData['service_id'] ?? 'NOT FOUND'));
            error_log("  Dentist ID: " . ($rescheduleData['dentist_id'] ?? 'NOT FOUND'));
            error_log("  Payment Type: " . ($rescheduleData['payment_type'] ?? 'NOT FOUND'));
            error_log("  Appointment Date: " . ($rescheduleData['original_appointment_date'] ?? 'NOT FOUND'));
            error_log("  Appointment Time: " . ($rescheduleData['original_appointment_time'] ?? 'NOT FOUND'));
            error_log("  Appointment Time Display: " . ($rescheduleData['original_appointment_time_display'] ?? 'NOT FOUND'));
        } else {
            error_log("ERROR: Failed to fetch appointment details: " . ($result['message'] ?? 'Unknown error'));
            $isReschedule = false;
            $rescheduleAppointmentId = null;
        }
    } else {
        error_log("ERROR: No client_id (varchar) available for reschedule");
        $isReschedule = false;
        $rescheduleAppointmentId = null;
    }
}

$dentists = $appointmentController->getAllDentists();
$services = $appointmentController->getAllServices();

// Handle AJAX requests for calendar/availability only
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'check_availability') {
        $dentistId = $_GET['dentist'] ?? null;
        $date = $_GET['date'] ?? '';
        echo json_encode($appointmentController->checkAvailability($dentistId, $date));
        exit;
    }
    
    if ($_GET['action'] === 'get_service_price') {
        $serviceId = $_GET['service_id'] ?? null;
        if ($serviceId) {
            $service = $appointmentController->getServicePrice($serviceId);
            echo json_encode($service);
        } else {
            echo json_encode(['price' => 0, 'name' => '']);
        }
        exit;
    }
    
    if ($_GET['action'] === 'get_monthly_availability') {
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('n');
        $dentistId = $_GET['dentist'] ?? null;
        echo json_encode($appointmentController->getMonthlyAvailability($year, $month, $dentistId));
        exit;
    }

    if ($_GET['action'] === 'get_dentists') {
        echo json_encode(['success' => true, 'dentists' => $appointmentController->getAllDentists()]);
        exit;
    }
}

// Handle form submission - TRADITIONAL FORM SUBMISSION
$errorMessage = '';
$successMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a reschedule
    $isReschedulePost = !empty($_POST['reschedule_id']);
    $rescheduleAppointmentIdPost = $isReschedulePost ? trim($_POST['reschedule_id']) : null;
    
    // Debug log
    error_log("=== FORM SUBMISSION START ===");
    error_log("Is reschedule: " . ($isReschedulePost ? 'YES' : 'NO'));
    error_log("POST data: " . print_r($_POST, true));
    
    if ($isReschedulePost && $rescheduleAppointmentIdPost) {
        // For reschedule, update the existing appointment
        $result = $appointmentController->bookAppointment($_POST);
        
        if (!$result['success']) {
            $errorMessage = $result['message'];
        } else {
            $successMessage = $result['message'];
            // Keep the form in reschedule mode to show the same appointment ID
            $isReschedule = true;
            $rescheduleAppointmentId = $rescheduleAppointmentIdPost;
            
            // Re-fetch appointment data to show updated info
            if ($clientId) {
                $result = $appointmentController->getRescheduleAppointmentDetails($rescheduleAppointmentId, $clientId);
                if ($result['success']) {
                    $rescheduleData = $result['appointment'];
                }
            }
        }
    } else {
        // For new appointment, create new
        $result = $appointmentController->bookAppointment($_POST);
        
        if (!$result['success']) {
            $errorMessage = $result['message'];
        } else {
            $successMessage = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isReschedule ? 'Reschedule Appointment' : 'New Appointment'; ?> - Cosmo Smiles Dental</title>
    <link rel="icon" type="image/png" href="<?php echo clean_url('public/assets/images/logo1-white.png'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo clean_url('public/assets/css/new-appointment.css'); ?>">
    <?php include 'includes/client-header-css.php'; ?>
</head>
<body>
    <?php 
    $baseDir = '../'; 
    include 'includes/client-header.php'; 
    ?>

    <!-- New Appointment Section -->
    <section class="new-appointment-section">
        <div class="container">
            <div class="appointment-header">
                <div class="header-content">
                    <h1 class="appointments-title"><?php echo $isReschedule ? 'Reschedule Appointment' : 'Make Appointment'; ?></h1>
                    <p class="appointments-subtitle">Fill out the form below to <?php echo $isReschedule ? 'reschedule' : 'schedule'; ?> your dental appointment</p>
                    <?php if($isReschedule): ?>
                        <div class="reschedule-notice">
                            <i class="fas fa-info-circle"></i>
                            <span>You are rescheduling appointment ID: <?php echo htmlspecialchars($rescheduleAppointmentId); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <a href="appointments.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Appointments
                </a>
            </div>

            <div class="appointment-form-container">
                <!-- Display success message if any -->
                <?php if (!empty($successMessage)): ?>
                    <div class="response-alert success">
                        <div class="alert-header">
                            <i class="alert-icon fas fa-check-circle"></i>
                            <h4 class="alert-title">Appointment <?php echo $isReschedule ? 'Rescheduled' : 'Booked'; ?> Successfully!</h4>
                        </div>
                        <p class="alert-message"><?php echo htmlspecialchars($successMessage); ?></p>
                        <div class="alert-actions">
                            <a href="appointments.php" class="btn btn-primary">View Appointments</a>
                            <a href="new-appointments.php" class="btn btn-outline">Book Another Appointment</a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Display error message if any -->
                <?php if (!empty($errorMessage)): ?>
                    <div class="response-alert error">
                        <div class="alert-header">
                            <i class="alert-icon fas fa-exclamation-triangle"></i>
                            <h4 class="alert-title">Please Check Your Input</h4>
                        </div>
                        <p class="alert-message"><?php echo htmlspecialchars($errorMessage); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Form Steps -->
                <div class="form-steps">
                    <div class="step active" id="step-1">
                        <div class="step-number">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="step" id="step-2">
                        <div class="step-number">2</div>
                        <div class="step-label">Appointment Details</div>
                    </div>
                    <div class="step" id="step-3">
                        <div class="step-number">3</div>
                        <div class="step-label">Review & Confirm</div>
                    </div>
                </div>

                <!-- START OF FORM - Traditional form submission -->
                <form method="POST" action="" id="appointment-form">
                    <!-- Hidden field for reschedule ID -->
                    <?php if($isReschedule && $rescheduleAppointmentId): ?>
                        <input type="hidden" name="reschedule_id" value="<?php echo htmlspecialchars($rescheduleAppointmentId); ?>">
                    <?php endif; ?>
                    
                    <!-- Step 1: Personal Information -->
                    <div class="form-step active" id="form-step-1">
                        <div class="form-section">
                            <h3 class="section-title">Personal Information</h3>
                            
                            <?php if($isLoggedIn): ?>
                            <div class="user-info-notice">
                                <i class="fas fa-info-circle"></i>
                                <span>Your information is pre-filled from your <?php echo $isReschedule ? 'appointment' : 'account'; ?>. You can update if needed.</span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" id="patient-first-name" name="patient_first_name" class="form-control" 
                                        placeholder=" " 
                                        value="<?php echo htmlspecialchars($userData['first_name']); ?>"
                                        required <?php echo $isReschedule ? 'readonly onfocus="this.blur()"' : ''; ?>>
                                    <label for="patient-first-name">First Name</label>
                                    <span class="validation-message"></span>
                                </div>
                                
                                <div class="form-group">
                                    <input type="text" id="patient-last-name" name="patient_last_name" class="form-control" 
                                        placeholder=" " 
                                        value="<?php echo htmlspecialchars($userData['last_name']); ?>"
                                        required <?php echo $isReschedule ? 'readonly onfocus="this.blur()"' : ''; ?>>
                                    <label for="patient-last-name">Last Name</label>
                                    <span class="validation-message"></span>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <input type="tel" id="patient-phone" name="patient_phone" class="form-control" 
                                        placeholder=" " 
                                        value="<?php echo htmlspecialchars($userData['phone']); ?>"
                                        required <?php echo $isReschedule ? 'readonly onfocus="this.blur()"' : ''; ?>>
                                    <label for="patient-phone">Phone Number</label>
                                    <span class="validation-message"></span>
                                </div>
                                
                                <div class="form-group">
                                    <input type="email" id="patient-email" name="patient_email" class="form-control" 
                                        placeholder=" " 
                                        value="<?php echo htmlspecialchars($userData['email']); ?>"
                                        required <?php echo $isReschedule ? 'readonly onfocus="this.blur()"' : ''; ?>>
                                    <label for="patient-email">Email Address</label>
                                    <span class="validation-message"></span>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <div></div>
                            <button type="button" class="form-btn next-step" data-next="2">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Appointment Details -->
                    <div class="form-step" id="form-step-2">
                        <div class="form-section">
                            <h3 class="section-title">Appointment Details</h3>
                            
                            <!-- Availability Notice -->
                            <div id="service-availability-notice" class="response-alert error" style="display:none; margin-bottom: 25px;">
                                <div class="alert-header">
                                    <i class="alert-icon fas fa-calendar-times"></i>
                                    <h4 class="alert-title">No Available Dentist Today</h4>
                                </div>
                                <p class="alert-message">We're sorry, but there are no dentists currently available for bookings. Please check back later or contact the clinic directly.</p>
                            </div>
                            
                            <div id="services-container">
                                <?php 
                                // Parse service IDs for rescheduling
                                $rescheduleServiceIds = [];
                                if ($isReschedule && isset($rescheduleData['service_id'])) {
                                    $rescheduleServiceIds = array_filter(array_map('trim', explode(',', $rescheduleData['service_id'])));
                                }
                                
                                // If not rescheduling or if no services found (fallback), show at least one row
                                if (empty($rescheduleServiceIds)) {
                                    $rescheduleServiceIds = [null];
                                }
                                
                                foreach ($rescheduleServiceIds as $index => $currentServiceId):
                                ?>
                                <div class="service-row">
                                    <div class="form-group service-select-group">
                                        <select <?php echo $index === 0 ? 'id="appointment-service"' : ''; ?> name="service_id[]" class="form-control service-selection" required <?php echo $isReschedule ? 'readonly onfocus="this.blur()" style="pointer-events: none; background-color: #f5f5f5;"' : ''; ?>>
                                            <option value="" disabled <?php echo !$currentServiceId ? 'selected' : ''; ?>>Select service type</option>
                                            <?php foreach ($services as $service): 
                                                $isSelected = ($currentServiceId && $service['id'] == $currentServiceId);
                                                $price = $service['price'];
                                            ?>
                                                <option value="<?php echo $service['id']; ?>" 
                                                    data-price="<?php echo $price; ?>"
                                                    <?php echo $isSelected ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($service['name']); ?> - ₱<?php echo number_format($price, 2); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label>Service Type</label>
                                        <span class="validation-message"></span>
                                    </div>
                                    <?php if($isReschedule && count($rescheduleServiceIds) > 1): ?>
                                    <!-- No remove button for rescheduling multiple services to prevent accidental changes -->
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if(!$isReschedule): ?>
                            <div class="add-service-wrapper">
                                <button type="button" id="add-service-btn" class="btn btn-outline btn-sm">
                                    <i class="fas fa-plus"></i> Add Another Service
                                </button>
                            </div>
                            <?php endif; ?>

                            <div class="service-price-display" id="service-price-display">
                                <?php if($isReschedule && isset($rescheduleData['service_price'])): ?>
                                    Total Price: ₱<?php echo number_format($rescheduleData['service_price'], 2); ?>
                                <?php else: ?>
                                    Total Price: ₱0.00
                                <?php endif; ?>
                            </div>

                            <!-- Calendar Dashboard -->
                            <div class="calendar-dashboard">
                                <div class="calendar-header">
                                    <h4>Select Appointment Date</h4>
                                    <div class="calendar-nav">
                                        <button type="button" id="prev-month"><i class="fas fa-chevron-left"></i></button>
                                        <h5 id="current-month-year"><?php echo date('F Y'); ?></h5>
                                        <button type="button" id="next-month"><i class="fas fa-chevron-right"></i></button>
                                    </div>
                                </div>
                                <div id="calendar-container"></div>
                                <div id="calendar-message" class="calendar-message"></div>
                                
                                <div class="time-slots-container">
                                    <h4 class="time-slots-title">Available Time Slots</h4>
                                    <div class="time-slots" id="time-slots">
                                        <div class="no-date-selected">Please select a date first</div>
                                    </div>
                                </div>
                                
                                <!-- Hidden fields for selected date and time -->
                                <input type="hidden" id="selected-date" name="appointment_date" value="<?php echo $isReschedule && isset($rescheduleData['original_appointment_date']) ? htmlspecialchars($rescheduleData['original_appointment_date']) : ''; ?>">
                                <input type="hidden" id="selected-time" name="appointment_time" value="<?php echo $isReschedule && isset($rescheduleData['original_appointment_time_display']) ? htmlspecialchars($rescheduleData['original_appointment_time_display']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <select id="appointment-dentist" name="dentist_id" class="form-control" required <?php echo $isReschedule ? 'readonly onfocus="this.blur()" style="pointer-events: none; background-color: #f5f5f5;"' : ''; ?>>
                                    <option value="" disabled selected>Select dentist</option>
                                    <?php foreach ($dentists as $dentist): ?>
                                        <option value="<?php echo $dentist['id']; ?>"
                                            <?php echo ($isReschedule && isset($rescheduleData['dentist_id']) && $dentist['id'] == $rescheduleData['dentist_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dentist['name']); ?> - <?php echo htmlspecialchars($dentist['specialization']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (count($dentists) === 1): ?>
                                    <option value="dr-any" <?php echo ($isReschedule && (!isset($rescheduleData['dentist_id']) || empty($rescheduleData['dentist_id']))) ? 'selected' : ''; ?>>Any Available Dentist</option>
                                    <?php endif; ?>
                                </select>
                                <label for="appointment-dentist">Preferred Dentist</label>
                                <span class="validation-message"></span>
                            </div>

                            <div class="form-group">
                                <select id="payment-type" name="payment_type" class="form-control" required <?php echo $isReschedule ? 'readonly onfocus="this.blur()" style="pointer-events: none; background-color: #f5f5f5;"' : ''; ?>>
                                    <option value="" disabled selected>Select payment type</option>
                                    <option value="cash" <?php echo ($isReschedule && isset($rescheduleData['payment_type']) && $rescheduleData['payment_type'] == 'cash') ? 'selected' : ''; ?>>Cash (Pay on Site)</option>
                                    <!-- GCash option removed as requested -->
                                </select>
                                <label for="payment-type">Payment Type</label>
                                <span class="validation-message"></span>
                            </div>

                            <!-- Add hidden fields for reschedule to ensure values are submitted -->
                            <?php if($isReschedule): ?>
                                <input type="hidden" name="service_id" value="<?php echo isset($rescheduleData['service_id']) ? htmlspecialchars($rescheduleData['service_id']) : ''; ?>">
                                <input type="hidden" name="dentist_id" value="<?php echo isset($rescheduleData['dentist_id']) ? htmlspecialchars($rescheduleData['dentist_id']) : ''; ?>">
                                <input type="hidden" name="payment_type" value="<?php echo isset($rescheduleData['payment_type']) ? htmlspecialchars($rescheduleData['payment_type']) : ''; ?>">
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <textarea id="appointment-notes" name="appointment_notes" class="form-control" rows="3" placeholder=" " <?php echo $isReschedule ? 'readonly' : ''; ?>><?php echo $isReschedule && isset($rescheduleData['notes']) ? htmlspecialchars($rescheduleData['notes']) : ''; ?></textarea>
                                <label for="appointment-notes">Additional Notes (Optional)</label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="form-btn form-btn-outline prev-step" data-prev="1">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="button" class="form-btn next-step" data-next="3">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Review & Confirm -->
                    <div class="form-step" id="form-step-3">
                        <div class="form-section">
                            <h3 class="section-title">Appointment Summary</h3>
                            
                            <div class="appointment-summary">
                                <div class="summary-section">
                                    <h4>Personal Information</h4>
                                    <div class="summary-details">
                                        <div class="summary-item">
                                            <span class="summary-label">First Name</span>
                                            <span class="summary-value" id="summary-first-name">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Last Name</span>
                                            <span class="summary-value" id="summary-last-name">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Phone Number</span>
                                            <span class="summary-value" id="summary-phone">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Email Address</span>
                                            <span class="summary-value" id="summary-email">-</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="summary-section">
                                    <h4>Appointment Details</h4>
                                    <div class="summary-details">
                                        <div class="summary-item">
                                            <span class="summary-label">Appointment ID</span>
                                            <span class="summary-value" id="summary-appointment-id">
                                                <?php echo $isReschedule ? htmlspecialchars($rescheduleAppointmentId) : 'Will be auto-generated'; ?>
                                            </span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Patient ID</span>
                                            <span class="summary-value" id="summary-patient-id">
                                                <?php 
                                                if($isLoggedIn) {
                                                    // First try to get from session
                                                    if (isset($_SESSION['client_client_id']) && !empty($_SESSION['client_client_id'])) {
                                                        echo htmlspecialchars($_SESSION['client_client_id']);
                                                    } 
                                                    // Then try the variable
                                                    else if ($clientId) {
                                                        echo htmlspecialchars($clientId);
                                                    }
                                                    // If still empty, show a fallback
                                                    else {
                                                        echo 'Guest Patient';
                                                    }
                                                } else {
                                                    echo 'Guest Patient';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Service Type</span>
                                            <span class="summary-value" id="summary-service">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Service Price</span>
                                            <span class="summary-value" id="summary-service-price">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Date & Time</span>
                                            <span class="summary-value" id="summary-datetime">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Dentist</span>
                                            <span class="summary-value" id="summary-dentist">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Payment Type</span>
                                            <span class="summary-value" id="summary-payment-type">-</span>
                                        </div>
                                        <div class="summary-item full-width">
                                            <span class="summary-label">Additional Notes</span>
                                            <span class="summary-value" id="summary-notes">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cancellation Policy Notice -->
                            <div class="cancellation-policy-notice">
                                <div class="policy-content">
                                    <i class="fas fa-info-circle"></i>
                                    <div class="policy-text">
                                        <h4>Cancellation Policy</h4>
                                        <p>Appointments cannot be cancelled or rescheduled within 48 hours of the appointment time.</p>
                                        <p>If you need to cancel or reschedule, please contact our receptionist at least 48 hours before your scheduled appointment.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="confirmation-section">
                                <div class="confirmation-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <h3 class="confirmation-message">Ready to confirm your <?php echo $isReschedule ? 'rescheduled' : 'new'; ?> appointment?</h3>
                                <p>Please review your appointment details above before confirming.</p>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="form-btn form-btn-outline prev-step" data-prev="2">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="submit" class="form-btn" id="confirm-appointment">
                                <i class="fas fa-check"></i> Confirm <?php echo $isReschedule ? 'Reschedule' : 'Appointment'; ?>
                            </button>
                        </div>
                    </div>
                </form>
                <!-- END OF FORM -->
            </div>
        </div>
    </section>

    <script src="<?php echo clean_url('public/assets/js/new-appointments.js'); ?>"></script>
    <script>
        // Pass PHP variables to JavaScript
        window.isReschedule = <?php echo $isReschedule ? 'true' : 'false'; ?>;
        window.rescheduleAppointmentId = '<?php echo $rescheduleAppointmentId ?: ''; ?>';
        
        // If reschedule and we have appointment data, pre-fill the date/time
        <?php if($isReschedule && isset($rescheduleData['original_appointment_date'])): ?>
            window.rescheduleDate = '<?php echo $rescheduleData['original_appointment_date']; ?>';
            window.rescheduleTime = '<?php echo $rescheduleData['original_appointment_time_display'] ?? date('g:i A', strtotime($rescheduleData['original_appointment_time'] ?? '')); ?>';
        <?php else: ?>
            window.rescheduleDate = '';
            window.rescheduleTime = '';
        <?php endif; ?>
        
        console.log('Reschedule JS Variables:', {
            isReschedule: isReschedule,
            rescheduleAppointmentId: rescheduleAppointmentId,
            rescheduleDate: rescheduleDate,
            rescheduleTime: rescheduleTime
        });

        // Live Availability Updates
        function refreshDentistList() {
            if (isReschedule) return; // Don't refresh during reschedule

            fetch('new-appointments.php?action=get_dentists')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.dentists) {
                        const hasAvailable = data.dentists.length > 0;
                        const notice = document.getElementById('service-availability-notice');
                        const nextBtn2 = document.querySelector('#form-step-2 .next-step');
                        const dentistSelect = document.getElementById('appointment-dentist');
                        
                        // Toggle notice and block progression
                        if (notice) notice.style.display = hasAvailable ? 'none' : 'block';
                        if (nextBtn2) {
                            if (!hasAvailable) {
                                nextBtn2.disabled = true;
                                nextBtn2.style.opacity = '0.5';
                                nextBtn2.style.cursor = 'not-allowed';
                            } else {
                                nextBtn2.disabled = false;
                                nextBtn2.style.opacity = '1';
                                nextBtn2.style.cursor = 'pointer';
                            }
                        }
                        
                        if (dentistSelect) {
                            const currentValue = dentistSelect.value;
                            dentistSelect.innerHTML = '';
                            
                            if (hasAvailable) {
                                // Add static options
                                dentistSelect.add(new Option('Select dentist', '', true, true));
                                dentistSelect.options[0].disabled = true;
                                if (data.dentists.length === 1) {
                                    dentistSelect.add(new Option('Any Available Dentist', 'dr-any'));
                                }
                                
                                // Add available dentists
                                data.dentists.forEach(dentist => {
                                    dentistSelect.add(new Option(`${dentist.name} - ${dentist.specialization}`, dentist.id));
                                });
                                
                                // Restore value if still available
                                if (Array.from(dentistSelect.options).some(opt => opt.value === currentValue)) {
                                    dentistSelect.value = currentValue;
                                }
                            } else {
                                // No dentists available
                                dentistSelect.add(new Option('No Available Dentist Today', '', true, true));
                                dentistSelect.value = '';
                                
                                // Clear calendar message if needed
                                const calMsg = document.getElementById('calendar-message');
                                if (calMsg) calMsg.innerHTML = '<span class="text-danger">Temporarily unavailable</span>';
                            }
                        }
                    }
                })
                .catch(error => console.error('Error refreshing dentists:', error));
        }

        // Poll every 1 second
        setInterval(refreshDentistList, 1000);
    </script>
</body>
</html>