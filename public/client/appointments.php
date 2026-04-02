<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Controllers/AppointmentController.php';
require_once __DIR__ . '/../../src/Controllers/MedicalHistoryController.php';

// Start session
session_start();

// Enhanced session handling with security
$isLoggedIn = isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true;

// Set user name if logged in, otherwise empty
$userName = '';
$clientDbId = null; // auto_increment id
$clientId = null;   // client_id (varchar) for API calls

if ($isLoggedIn) {
    // client_id is now strictly numeric (primary key id)
    $clientDbId = $_SESSION['client_id'] ?? null;
    // client_client_id is strictly varchar (PAT...)
    $clientId = $_SESSION['client_client_id'] ?? null;
    
    $firstName = $_SESSION['client_first_name'] ?? '';
    $lastName = $_SESSION['client_last_name'] ?? '';
    $userName = trim($firstName . ' ' . $lastName);
    
    // Debug logging
    error_log("=== APPOINTMENTS.PHP DEBUG ===");
    error_log("Is logged in: YES");
    error_log("Numeric client_id: " . ($clientDbId ?: 'NOT SET'));
    error_log("Varchar client_client_id: " . ($clientId ?: 'NOT SET'));
    
    // If we have numeric ID but no varchar ID, fetch it (rare but possible if session was created before migration)
    if ($clientDbId && !$clientId) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            if ($db) {
                $stmt = $db->prepare("SELECT client_id FROM clients WHERE id = :id");
                $stmt->execute([':id' => $clientDbId]);
                $clientData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($clientData && !empty($clientData['client_id'])) {
                    $clientId = $clientData['client_id'];
                    $_SESSION['client_client_id'] = $clientId;
                    error_log("Fetched and restored client_client_id: " . $clientId);
                }
            }
        } catch (PDOException $e) {
            error_log("Error restoring client_client_id in appointments.php: " . $e->getMessage());
        }
    }
    
    // If both names are empty, show generic name
    
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
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userData && !empty($userData['profile_image'])) {
                $profileImage = $userData['profile_image'];
            }
        } catch (Exception $e) {
            error_log("Error fetching profile image: " . $e->getMessage());
        }
    }
} else {
    $userName = 'My Account';
    error_log("User is NOT logged in");
}

error_log("Final client_id for API calls (varchar): " . ($clientId ?: 'NULL'));

error_log("Final client_id for API calls: " . ($clientId ?: 'NULL'));

$appointmentController = new AppointmentController();

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'get_appointments') {
        $month = $_GET['month'] ?? date('n');
        $year = $_GET['year'] ?? date('Y');
        
        error_log("=== AJAX get_appointments called ===");
        error_log("Month: $month, Year: $year");
        error_log("Client ID: " . ($clientId ?: 'NULL'));
        
        if ($clientId) {
            $appointments = $appointmentController->getClientAppointments($clientId, $month, $year);
            error_log("Appointments returned: " . count($appointments));
            echo json_encode($appointments);
        } else {
            error_log("ERROR: No client ID available for appointments query");
            echo json_encode([]);
        }
        exit;
    }
    
    if ($_GET['action'] === 'get_appointment_history') {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 5; // Show 5 appointments per page
        
        error_log("=== AJAX get_appointment_history called ===");
        error_log("Page: $page, Per Page: $perPage");
        error_log("Client ID: " . ($clientId ?: 'NULL'));
        
        if ($clientId) {
            $result = $appointmentController->getAppointmentHistory($clientId, $page, $perPage);
            error_log("History records returned: " . count($result['history']));
            echo json_encode($result);
        } else {
            error_log("ERROR: No client ID available for history query");
            echo json_encode([
                'history' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total_records' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false
                ]
            ]);
        }
        exit;
    }
    
    if ($_GET['action'] === 'cancel_appointment') {
        $appointmentId = $_GET['id'] ?? null;
        $reason = $_GET['reason'] ?? null;
        if ($appointmentId && $clientId) {
            $result = $appointmentController->cancelAppointment($appointmentId, $clientId, $reason);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid appointment ID or client not logged in']);
        }
        exit;
    }
    
    if ($_GET['action'] === 'check_reschedule') {
        $appointmentId = $_GET['id'] ?? null;
        error_log("=== check_reschedule AJAX called ===");
        error_log("Appointment ID: " . $appointmentId);
        error_log("Client ID: " . $clientId);
        if ($appointmentId && $clientId) {
            $result = $appointmentController->canRescheduleAppointment($appointmentId, $clientId);
            error_log("Reschedule check result: " . print_r($result, true));
            echo json_encode($result);
        } else {
            error_log("ERROR: Missing appointment ID or client ID");
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid appointment ID or client not logged in',
                'can_reschedule' => false
            ]);
        }
        exit;
    }
    
    // NEW AJAX HANDLER: Get appointment details for rescheduling
    if ($_GET['action'] === 'get_reschedule_details') {
        $appointmentId = $_GET['id'] ?? null;
        error_log("=== get_reschedule_details AJAX called ===");
        error_log("Appointment ID: " . $appointmentId);
        error_log("Client ID: " . $clientId);
        if ($appointmentId && $clientId) {
            $result = $appointmentController->getRescheduleAppointmentDetails($appointmentId, $clientId);
            error_log("Reschedule details result: " . print_r($result, true));
            echo json_encode($result);
        } else {
            error_log("ERROR: Missing appointment ID or client ID");
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid appointment ID or client not logged in'
            ]);
        }
        exit;
    }

    // AJAX handler to check if medical history is completed
    if ($_GET['action'] === 'check_medical_history') {
        if ($clientId) {
            $medicalHistoryController = new \Controllers\MedicalHistoryController();
            $isCompleted = $medicalHistoryController->isHistoryCompleted($clientId);
            echo json_encode(['success' => true, 'is_completed' => $isCompleted]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Client not logged in']);
        }
        exit;
    }

    // AJAX handler for feedback submission
    if ($_GET['action'] === 'submit_feedback') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $appointmentId = $input['appointment_id'] ?? null;
            $rating = $input['rating'] ?? null;
            $feedback = $input['feedback'] ?? '';

            if ($appointmentId && $clientId && $rating) {
                $result = $appointmentController->submitFeedback($appointmentId, $clientId, $rating, $feedback);
                echo json_encode($result);
            } else {
                echo json_encode(['success' => false, 'message' => 'Missing required feedback information']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/x-icon" href="../images/logo1-white.png">
  <title>Appointments - Cosmo Smiles Dental</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/appointments.css">
  <?php include 'includes/client-header-css.php'; ?>
</head>

<<body>
    <?php 
    $baseDir = '../'; 
    include 'includes/client-header.php'; 
    ?>

    <!-- Appointments Section -->
    <section class="appointments-section">
        <div class="container">
            <div class="appointments-header">
                <div class="header-content">
                    <h1 class="appointments-title">Appointments</h1>
                    <p class="appointments-subtitle">Schedule and manage your dental appointments</p>
                </div>
                <a href="new-appointments.php" class="btn btn-primary new-appointment-btn" id="new-appointment-btn">
                    <i class="fas fa-plus"></i> New Appointment
                </a>
            </div>

            <?php if(!$isLoggedIn): ?>
                <div class="login-required-message">
                    <div class="message-content">
                        <i class="fas fa-exclamation-circle"></i>
                        <h3>Login Required</h3>
                        <p>Please log in to view and manage your appointments.</p>
                        <a href="login.php" class="btn btn-primary">Login Now</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="appointments-content">
                    <!-- Calendar Dashboard -->
                    <div class="calendar-section">
                        <div class="calendar-header">
                            <h2>Appointment Calendar</h2>
                            <div class="calendar-controls">
                                <button class="calendar-nav" id="prev-month">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <h3 id="current-month"><?php echo date('F Y'); ?></h3>
                                <button class="calendar-nav" id="next-month">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>

                        <div class="calendar">
                            <div class="calendar-weekdays">
                                <div class="weekday">Sun</div>
                                <div class="weekday">Mon</div>
                                <div class="weekday">Tue</div>
                                <div class="weekday">Wed</div>
                                <div class="weekday">Thu</div>
                                <div class="weekday">Fri</div>
                                <div class="weekday">Sat</div>
                            </div>
                            <div class="calendar-days" id="calendar-days">
                                <!-- Calendar days will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- Appointments Sidebar -->
                    <div class="appointments-sidebar">
                        <!-- Appointment Details -->
                        <div class="appointment-details-section">
                            <h3>Appointment Details</h3>
                            <div class="selected-date-info" id="selected-date-info">
                                <h4>No Date Selected</h4>
                                <p>Click on a date in the calendar to view appointments</p>
                            </div>
                            <div class="appointment-details-list" id="appointment-details-list">
                                <!-- Appointment details will be populated by JavaScript -->
                            </div>
                        </div>

                        <!-- Appointment History -->
                        <div class="sidebar-section">
                            <h3>Appointment History</h3>
                            <div class="appointments-list" id="appointment-history">
                                <!-- Appointment history will be populated by JavaScript -->
                                <div class="no-appointments">
                                    <div class="no-appointments-content">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        <h4>Loading History...</h4>
                                        <p>Your appointment history is being loaded</p>
                                    </div>
                                </div>
                            </div>
                            <!-- Pagination will be added here by JavaScript -->
                            <div id="pagination-container"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Modals -->
    <div class="modal" id="cancelModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cancel Appointment</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="confirmation-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h4>Are you sure you want to cancel this appointment?</h4>
                    <p>This action cannot be undone.</p>
                </div>
                <div class="reason-section">
                    <label for="cancel-reason">Reason for cancellation:</label>
                    <select id="cancel-reason" class="form-control">
                        <option value="">Select a reason</option>
                        <option value="schedule_conflict">Schedule Conflict</option>
                        <option value="financial_reasons">Financial Reasons</option>
                        <option value="found_another_dentist">Found Another Dentist</option>
                        <option value="health_issues">Health Issues</option>
                        <option value="personal_reasons">Personal Reasons</option>
                        <option value="other">Other</option>
                    </select>
                    <div class="other-reason" id="other-reason-container" style="display: none;">
                        <textarea id="other-reason" class="form-control" placeholder="Please specify your reason..." rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="form-btn form-btn-outline" id="cancel-no">No, Keep Appointment</button>
                <button class="form-btn" id="cancel-yes">Yes, Cancel Appointment</button>
            </div>
        </div>
    </div>

    <div class="modal" id="rescheduleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reschedule Appointment</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="confirmation-message">
                    <i class="fas fa-calendar-alt"></i>
                    <h4>Are you sure you want to reschedule this appointment?</h4>
                    <p>You'll be redirected to the booking page to select a new date and time. Your existing appointment details will be pre-filled.</p>
                </div>
                <div class="current-appointment-details" id="current-appointment-details">
                    <!-- Current appointment details will be populated by JavaScript -->
                </div>
                <div class="reason-section">
                    <label for="reschedule-reason">Reason for rescheduling:</label>
                    <select id="reschedule-reason" class="form-control">
                        <option value="">Select a reason</option>
                        <option value="schedule_conflict">Schedule Conflict</option>
                        <option value="preferred_different_time">Preferred Different Time</option>
                        <option value="unavailable_on_date">Unavailable on Selected Date</option>
                        <option value="personal_reasons">Personal Reasons</option>
                        <option value="other">Other</option>
                    </select>
                    <div class="other-reason" id="reschedule-other-container" style="display: none;">
                        <textarea id="reschedule-other-reason" class="form-control" placeholder="Please specify your reason..." rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="form-btn form-btn-outline" id="reschedule-no">No, Keep Current Time</button>
                <button class="form-btn" id="reschedule-yes">Yes, Reschedule</button>
            </div>
        </div>
    </div>

    <div class="modal" id="confirmedAppointmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Appointment Verification Required</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="confirmation-message">
                    <i class="fas fa-info-circle"></i>
                    <h4>Verification Required</h4>
                    <p>This appointment has been confirmed. Changes to confirmed appointments require verification from our receptionist.</p>
                    <p>Please contact our clinic directly to reschedule or cancel this appointment.</p>
                </div>
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span>Phone: 09266492903</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>Email: reception@cosmosmilesdental.com</span>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="form-btn" id="close-verification">Close</button>
            </div>
        </div>
    </div>

    <div class="modal" id="feedbackModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Appointment Feedback</h3>
                <button class="close-modal" id="close-feedback-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="feedback-intro">
                    <i class="fas fa-star" style="color: #f1c40f; font-size: 2rem; margin-bottom: 1rem;"></i>
                    <h4>How was your experience?</h4>
                    <p>Your feedback helps us improve our services.</p>
                </div>
                <form id="feedbackForm">
                    <input type="hidden" id="feedback-appointment-id">
                    <div class="rating-section">
                        <label>Your Rating:</label>
                        <div class="star-rating">
                            <input type="radio" id="star5" name="rating" value="5" /><label for="star5" title="5 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star4" name="rating" value="4" /><label for="star4" title="4 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star3" name="rating" value="3" /><label for="star3" title="3 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star2" name="rating" value="2" /><label for="star2" title="2 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star1" name="rating" value="1" /><label for="star1" title="1 star"><i class="fas fa-star"></i></label>
                        </div>
                    </div>
                    <div class="comment-section">
                        <label for="feedback-comment">Your Comments (Optional):</label>
                        <textarea id="feedback-comment" class="form-control" placeholder="Tell us more about your visit..." rows="4"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button class="form-btn form-btn-outline" id="feedback-cancel">Cancel</button>
                <button class="form-btn" id="feedback-submit">Submit Feedback</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/appointments.js"></script>
</body>
</html>