<?php 
session_start();

// Set security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Correct path based on your folder structure
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/env.php';

// Controller is at: src/Controllers/PatientRecordsController.php
require_once __DIR__ . '/../../src/Controllers/PatientRecordsController.php';

use Controllers\PatientRecordsController;

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Create database connection
$database = new Database();
$pdo = $database->getConnection();

// Initialize controller with database connection
$controller = new PatientRecordsController($pdo);

// Generate CSRF token (simple version)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode([
            'success' => false,
            'message' => 'Security token invalid. Please refresh the page.'
        ]);
        exit();
    }
    
    // Handle medical edit request actions
    $medicalActions = ['approve_edit_request', 'deny_edit_request', 'get_pending_edit_requests'];
    if (in_array($_POST['action'], $medicalActions)) {
        require_once __DIR__ . '/../../src/Controllers/MedicalHistoryController.php';
        $medController = new \Controllers\MedicalHistoryController($pdo);

        switch ($_POST['action']) {
            case 'approve_edit_request':
                $requestId = intval($_POST['request_id'] ?? 0);
                $result = $medController->approveEdit($requestId, $_SESSION['admin_id']);
                echo json_encode($result);
                exit();

            case 'deny_edit_request':
                $requestId = intval($_POST['request_id'] ?? 0);
                $notes = $_POST['notes'] ?? '';
                $result = $medController->denyEdit($requestId, $_SESSION['admin_id'], $notes);
                echo json_encode($result);
                exit();

            case 'get_pending_edit_requests':
                $requests = $medController->getAllPendingRequests();
                echo json_encode(['success' => true, 'requests' => $requests]);
                exit();
        }
    }
    
    $data = [];
    foreach ($_POST as $key => $value) {
        if ($key === 'record_data' && is_string($value)) {
            $decoded = json_decode($value, true);
            $data[$key] = $decoded ?: [];
        } elseif ($key !== 'csrf_token') {
            $data[$key] = $value;
        }
    }
    
    // Handle file uploads
    $uploaded_files = [];
    if (isset($_FILES['files'])) {
        $uploaded_files = $_FILES['files'];
    }
    
    $result = $controller->handleAjaxRequest($_POST['action'], $data, $_SESSION['admin_id'], $uploaded_files);

    // For search_patient responses, enrich the patient object with medical history data
    // so the JS "View Medical History" button works correctly
    if ($_POST['action'] === 'search_patient' && !empty($result['success']) && !empty($result['patient'])) {
        $varcharClientId = $result['patient']['client_id'] ?? '';
        
        if (!empty($varcharClientId)) {
            try {
                // Get medical_history_status and edit_allowed flag
                $stmtClient = $pdo->prepare(
                    "SELECT medical_history_status, medical_history_edit_allowed FROM clients WHERE client_id = ? LIMIT 1"
                );
                $stmtClient->execute([$varcharClientId]);
                $clientMeta = $stmtClient->fetch(PDO::FETCH_ASSOC);
                $medStatus = $clientMeta['medical_history_status'] ?? 'pending';
                $editAllowed = (int)($clientMeta['medical_history_edit_allowed'] ?? 0);

                $result['patient']['medical_history_edit_allowed'] = $editAllowed;

                if ($medStatus === 'completed') {
                    // Fetch actual medical history
                    $histStmt = $pdo->prepare("SELECT * FROM patient_medical_history WHERE client_id = ? LIMIT 1");
                    $histStmt->execute([$varcharClientId]);
                    $medHistory = $histStmt->fetch(PDO::FETCH_ASSOC);
                    $result['patient']['medical_history'] = $medHistory ?: null;

                    // Fetch pending edit request
                    $reqStmt = $pdo->prepare(
                        "SELECT id, status, requested_at FROM medical_edit_requests 
                         WHERE client_id = ? AND status = 'pending' 
                         ORDER BY requested_at DESC LIMIT 1"
                    );
                    $reqStmt->execute([$varcharClientId]);
                    $result['patient']['pending_edit_request'] = $reqStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                } else {
                    $result['patient']['medical_history'] = null;
                    $result['patient']['pending_edit_request'] = null;
                }
            } catch (PDOException $e) {
                error_log("Error enriching search_patient with medical history: " . $e->getMessage());
                $result['patient']['medical_history'] = null;
                $result['patient']['pending_edit_request'] = null;
                $result['patient']['medical_history_edit_allowed'] = 0;
            }
        }
    }

    echo json_encode($result);
    exit();
}

// Get admin information for display
$admin_id = $_SESSION['admin_id'];
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, role, dentist_id FROM admin_users WHERE id = :id");
    $stmt->execute([':id' => $admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        $admin_name = 'Dr. ' . htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name'], ENT_QUOTES, 'UTF-8');
        $admin_role = ($admin['role'] === 'admin') ? 'Administrator' : ucfirst(htmlspecialchars($admin['role'], ENT_QUOTES, 'UTF-8'));
        $admin_dentist_id = htmlspecialchars($admin['dentist_id'], ENT_QUOTES, 'UTF-8');
        $admin_full_name = 'Dr. ' . htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name'], ENT_QUOTES, 'UTF-8');
    } else {
        $admin_name = "Dr. Administrator";
        $admin_role = "Administrator";
        $admin_dentist_id = '';
        $admin_full_name = 'Dr. Administrator';
    }
} catch (PDOException $e) {
    error_log("Error getting admin info: " . $e->getMessage());
    $admin_name = "Dr. Administrator";
    $admin_role = "Administrator";
    $admin_dentist_id = '';
    $admin_full_name = 'Dr. Administrator';
}

// Get services for dropdown
$services = $controller->getServices();

// Sidebar variables
$currentPage = 'records';
$sidebarAdminName = $admin_name;
$sidebarAdminRole = $admin_role;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Patient Records - Cosmo Smiles Dental</title>
    <link rel="icon" type="image/png" href="<?php echo clean_url('public/assets/images/logo1-white.png'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo clean_url('public/assets/css/admin-records.css'); ?>">
    <link rel="stylesheet" href="<?php echo clean_url('public/assets/css/odontogram.css'); ?>">
    <?php  include 'includes/admin-sidebar-css.php'; ?>
</head>
<body>
    <?php  include 'includes/admin-header.php'; ?>

    <!-- Admin Dashboard Layout -->
    <div class="admin-container">
        <?php  include 'includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="header-content">
                    <h1>Patient Records</h1>
                    <p>Access and manage patient medical records and documents</p>
                </div>
                <div class="header-actions">
                    <div class="date-display">
                        <i class="fas fa-calendar-alt" style="font-size: 1.2rem; color: var(--secondary);"></i>
                        <div class="clock-content">
                            <span id="admin-date">Loading...</span>
                            <span id="admin-time">00:00:00 AM</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Patient ID Search Section -->
            <div class="patient-search-section" id="search-section">
                <h3>Enter Patient ID to Access Records</h3>
                <div class="search-container">
                    <div class="form-group">
                        <input type="text" id="patient-id-input" class="search-input" 
                               placeholder="Enter Patient ID (e.g., PT0000)" maxlength="20"
                               pattern="[A-Z0-9]+" title="Only uppercase letters and numbers">
                    </div>
                    <button class="btn btn-primary search-btn" id="search-patient-btn">
                        <i class="fas fa-search"></i> Access Records
                    </button>
                </div>
                <div class="search-example">
                     Example: PAT0000
                </div>
            </div>

            <!-- Records Container (Initially Hidden) -->
            <div class="records-container" id="records-container">
                <!-- Patient Info Bar -->
                <div class="patient-info-bar">
                    <div class="patient-details">
                        <div class="patient-avatar-large" id="patient-avatar-container">
                            <img id="patient-profile-img" src="" alt="" style="display:none; width:100%; height:100%; border-radius:50%; object-fit:cover;">
                            <span id="patient-initials">JD</span>
                        </div>
                    <div class="patient-info-text">
                        <h4 id="patient-name">Juan Dela Cruz</h4>
                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <p id="patient-id-display" style="margin: 0;">Patient ID: PT-2101</p>
                            <button class="btn btn-primary btn-sm" id="view-medical-history-btn" style="padding: 4px 12px; font-size: 0.85rem; display: none; background-color: #007bff; border-color: #007bff; border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <i class="fas fa-file-medical"></i> View Medical Result
                            </button>
                            <div id="medical-edit-request-container" style="display: none; align-items: center; gap: 10px;"></div>
                        </div>
                    </div>
                </div>
                <div class="patient-stats">
                        <div class="stat-item">
                            <span class="stat-value" id="total-records">0</span>
                            <span class="stat-label">Total Records</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value" id="last-updated">-</span>
                            <span class="stat-label">Last Updated</span>
                        </div>
                    </div>
                </div>

                <!-- Records Header -->
                <div class="records-header">
                    <h3>Patient Records</h3>
                    <button class="btn btn-success" id="create-record-btn">
                        <i class="fas fa-file-medical-alt"></i> Create New Record
                    </button>
                </div>

                <!-- Records Content -->
                <div class="records-content">
                    <!-- Records Actions Bar -->
                    <div class="records-actions-bar" style="flex-wrap: wrap;">
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button class="btn btn-primary" id="filter-all-btn" data-filter="all">
                                All Records
                            </button>
                            <button class="btn" id="filter-treatment-btn" data-filter="treatment">
                                Treatments
                            </button>
                            <button class="btn" id="filter-consultation-btn" data-filter="consultation">
                                Consultations
                            </button>
                            <button class="btn" id="filter-xray-btn" data-filter="xray">
                                X-Rays
                            </button>
                            <button class="btn" id="filter-prescription-btn" data-filter="prescription">
                                Prescriptions
                            </button>
                        </div>
                        <div style="margin-left: auto;">
                            <button class="btn" id="show-archived-btn" title="Show Archived Records">
                                <i class="fas fa-archive"></i> Archived
                            </button>
                            <button class="btn" id="refresh-btn">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Success Message -->
                    <div class="success-message" id="success-message">
                        <i class="fas fa-check-circle"></i>
                        <span id="success-message-text"></span>
                    </div>

                    <!-- Error Message -->
                    <div class="error-message" id="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span id="error-message-text"></span>
                    </div>

                    <!-- Records List -->
                    <div class="records-list" id="records-list">
                        <!-- Record items will be populated by JavaScript -->
                    </div>

                    <!-- Empty State (Initially Hidden) -->
                    <div class="empty-state" id="empty-state" style="display: none;">
                        <i class="fas fa-folder-open"></i>
                        <h3>No records found</h3>
                        <p>Create new records or upload files to get started</p>
                        <button class="btn btn-primary" style="margin-top: 20px;" id="empty-create-btn">
                            <i class="fas fa-file-medical-alt"></i> Create First Record
                        </button>
                    </div>

                    <!-- Back to Search Button -->
                    <div class="back-to-search">
                        <button class="btn" id="back-to-search-btn">
                            <i class="fas fa-search"></i> Search Another Patient
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Record Modal -->
    <div class="modal" id="create-record-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Patient Record</h3>
                <button class="close-modal" id="close-create-modal">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Medical Alert Section -->
                <div id="create-record-medical-alerts" class="medical-alerts-container"></div>
                
                <form id="create-record-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php  echo $csrf_token; ?>">
                    <input type="hidden" id="record-appointment-id" name="appointment_id" value="">
                    
                    <div id="appointment-selection-container" style="display: none; background: #f0f7ff; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff; margin-bottom: 20px;">
                        <label for="completed-appointment-select" style="color: #004085; font-weight: 600; display: block; margin-bottom: 8px;">
                            <i class="fas fa-calendar-check"></i> Create from Completed Appointment
                        </label>
                        <select id="completed-appointment-select" class="form-control" style="border-color: #007bff; max-width: 100%;">
                            <option value="">Create a New Record</option>
                        </select>
                        <div class="form-help" style="margin-top: 5px; color: #0056b3;">Selecting an appointment will auto-fill the record details to save you time.</div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="record-patient-id" class="required">Patient ID</label>
                            <input type="text" id="record-patient-id" class="form-control" required 
                                   placeholder="Enter Patient ID" maxlength="20"
                                   pattern="[A-Z0-9]+" title="Only uppercase letters and numbers">
                            <div class="form-help">Enter existing Patient ID (e.g., PT0000)</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="record-type" class="required">Record Type</label>
                            <select id="record-type" class="form-control" required>
                                <option value="">Select Record Type</option>
                                <option value="treatment">Treatment</option>
                                <option value="consultation">Consultation</option>
                                <option value="xray">X-Ray</option>
                                <option value="prescription">Prescription</option>
                                <option value="followup">Follow-up</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="record-title" class="required">Record Title</label>
                            <input type="text" id="record-title" class="form-control" required 
                                   placeholder="e.g., Tooth Filling, Root Canal, Dental Check-up" maxlength="200">
                        </div>
                        
                        <div class="form-group">
                            <label for="record-date" class="required">Date</label>
                            <input type="date" id="record-date" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="record-time" class="required">Time</label>
                            <input type="time" id="record-time" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="record-dentist" class="required">Dentist</label>
                            <input type="text" id="record-dentist" class="form-control" readonly value="<?php  echo $admin_full_name; ?>">
                            <div class="form-help">Automatically detected from logged-in user</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="record-duration">Duration</label>
                            <input type="text" id="record-duration" class="form-control" readonly placeholder="Will be auto-filled based on procedure">
                            <div class="form-help">Automatically calculated from service duration</div>
                        </div>
                        
                        <div class="form-group service-dropdown-container">
                            <label for="record-procedure" class="required">Procedure</label>
                            <input type="text" id="record-procedure" class="form-control" required 
                                   placeholder="Select or type procedure..." 
                                   list="procedure-options" maxlength="100">
                            <datalist id="procedure-options">
                                <?php  foreach ($services as $service): ?>
                                    <option value="<?php  echo $service['name']; ?>">
                                        <?php  echo $service['name']; ?>
                                    </option>
                                <?php  endforeach; ?>
                            </datalist>
                            <div class="form-help">Select from available services or type custom procedure</div>
                        </div>
                        
                        <!-- Tooth Numbers Section -->
                        <div class="form-group full-width">
                            <label>Tooth Numbers</label>
                            <div class="tooth-selection-container">
                                <div class="tooth-grid" id="tooth-grid">
                                    <!-- Tooth buttons will be generated by JavaScript -->
                                </div>
                                <div class="selected-teeth-display" id="selected-teeth-display">
                                    <div class="selected-teeth-label">Selected Teeth:</div>
                                    <div class="selected-teeth-list" id="selected-teeth-list">None</div>
                                    <input type="hidden" id="record-tooth-numbers" name="tooth_numbers">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Surfaces Section -->
                        <div class="form-group full-width">
                            <label>Tooth Surfaces</label>
                            <div class="surfaces-selection">
                                <div class="surface-checkboxes">
                                    <label class="surface-checkbox">
                                        <input type="checkbox" name="surfaces" value="mesial">
                                        <span>Mesial (M)</span>
                                    </label>
                                    <label class="surface-checkbox">
                                        <input type="checkbox" name="surfaces" value="distal">
                                        <span>Distal (D)</span>
                                    </label>
                                    <label class="surface-checkbox">
                                        <input type="checkbox" name="surfaces" value="occlusal">
                                        <span>Occlusal (O)</span>
                                    </label>
                                    <label class="surface-checkbox">
                                        <input type="checkbox" name="surfaces" value="buccal">
                                        <span>Buccal (B)</span>
                                    </label>
                                    <label class="surface-checkbox">
                                        <input type="checkbox" name="surfaces" value="lingual">
                                        <span>Lingual (L)</span>
                                    </label>
                                    <label class="surface-checkbox">
                                        <input type="checkbox" name="surfaces" value="palatal">
                                        <span>Palatal (P)</span>
                                    </label>
                                </div>
                                <input type="hidden" id="record-surfaces" name="surfaces">
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="record-description" class="required">Description</label>
                            <textarea id="record-description" class="form-control" rows="4" required 
                                      placeholder="Detailed description of the procedure..." maxlength="2000"></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="record-findings">Clinical Findings</label>
                            <textarea id="record-findings" class="form-control" rows="4" 
                                      placeholder="Results, observations, and findings..." maxlength="2000"></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="record-notes">Additional Notes</label>
                            <textarea id="record-notes" class="form-control" rows="3" 
                                      placeholder="Any additional observations or notes..." maxlength="1000"></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="record-followup">Follow-up Instructions</label>
                            <textarea id="record-followup" class="form-control" rows="3" 
                                      placeholder="Instructions for patient follow-up..." maxlength="1000"></textarea>
                        </div>
                    </div>
                    
                    <!-- File Upload Section in Modal -->
                    <div class="form-group full-width">
                        <label>Attach Files (Optional)</label>
                        <div class="modal-upload-area" id="modal-upload-area">
                            <div class="modal-upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                <h4>Drag & Drop files here</h4>
                                <p>or click to browse files</p>
                                <p style="font-size: 0.8rem;">Supported: PDF, JPG, PNG, DOC, DOCX (Max: 10MB)</p>
                            </div>
                            <input type="file" class="file-input" id="modal-file-input" multiple 
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" name="files[]">
                        </div>
                        
                        <!-- File Preview in Modal -->
                        <div class="modal-file-preview" id="modal-file-preview">
                            <div style="margin-top: 20px; margin-bottom: 10px; font-weight: 600; color: var(--dark);">
                                Selected Files
                            </div>
                            <div class="modal-preview-grid" id="modal-preview-grid">
                                <!-- File previews will be added here -->
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" id="cancel-create-record">Cancel</button>
                <button class="btn btn-success" id="save-record-btn">
                    <i class="fas fa-save"></i> Save Patient Record
                </button>
            </div>
        </div>
    </div>

    <!-- View Record Details Modal -->
    <div class="modal" id="view-record-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Record Details</h3>
                <button class="close-modal" id="close-view-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="record-details-container" id="record-details-content">
                    <!-- Record details will be populated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" id="close-view-record">Close</button>
                <button class="btn btn-primary" id="edit-record-btn">
                    <i class="fas fa-edit"></i> Edit Record
                </button>
                <button class="btn btn-warning" id="archive-record-btn">
                    <i class="fas fa-archive"></i> Archive Record
                </button>
                <button class="btn btn-success" id="download-record-btn">
                    <i class="fas fa-download"></i> Download as PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Archive Record Modal -->
    <div class="modal" id="archive-record-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Archive Record</h3>
                <button class="close-modal" id="close-archive-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="archive-record-form">
                    <input type="hidden" name="csrf_token" value="<?php  echo $csrf_token; ?>">
                    <div class="form-group">
                        <label for="archive-reason" class="required">Archive Reason</label>
                        <select id="archive-reason" class="form-control" required>
                            <option value="">Select reason for archiving</option>
                            <option value="duplicate">Duplicate Record</option>
                            <option value="error">Data Entry Error</option>
                            <option value="merged">Merged with Another Record</option>
                            <option value="obsolete">Obsolete Information</option>
                            <option value="patient_request">Patient Request</option>
                            <option value="other">Other Reason</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="archive-notes">Additional Notes</label>
                        <textarea id="archive-notes" class="form-control" rows="3" 
                                  placeholder="Provide additional details..." maxlength="500"></textarea>
                    </div>
                    <div class="warning-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> Archived records will be hidden from the main view but can be restored if needed.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" id="cancel-archive">Cancel</button>
                <button class="btn btn-warning" id="confirm-archive">
                    <i class="fas fa-archive"></i> Archive Record
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Record Modal -->
    <div class="modal" id="edit-record-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Patient Record</h3>
                <button class="close-modal" id="close-edit-modal">&times;</button>
            </div>
        <form id="edit-record-form">
            <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php  echo $csrf_token; ?>">
                    <input type="hidden" id="edit-record-id" name="record_id" value="">
                    <input type="hidden" id="edit-record-patient-id" name="client_id" value="">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit-record-type" class="required">Record Type</label>
                            <select id="edit-record-type" class="form-control" name="record_type" required>
                                <option value="">Select Record Type</option>
                                <option value="treatment">Treatment</option>
                                <option value="consultation">Consultation</option>
                                <option value="xray">X-Ray</option>
                                <option value="prescription">Prescription</option>
                                <option value="followup">Follow-up</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="edit-record-title" class="required">Record Title</label>
                            <input type="text" id="edit-record-title" class="form-control" name="record_title" required 
                                   placeholder="e.g., Tooth Filling, Root Canal" maxlength="200">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit-record-date" class="required">Date</label>
                            <input type="date" id="edit-record-date" class="form-control" name="record_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit-record-time" class="required">Time</label>
                            <input type="time" id="edit-record-time" class="form-control" name="record_time" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit-record-duration">Duration</label>
                            <input type="text" id="edit-record-duration" class="form-control" name="duration" readonly>
                        </div>
                        
                        <div class="form-group service-dropdown-container">
                            <label for="edit-record-procedure" class="required">Procedure</label>
                            <input type="text" id="edit-record-procedure" class="form-control" name="procedure" required 
                                   list="edit-procedure-options" maxlength="100">
                            <datalist id="edit-procedure-options">
                                <?php  foreach ($services as $service): ?>
                                    <option value="<?php  echo htmlspecialchars($service['name']); ?>">
                                        <?php  echo htmlspecialchars($service['name']); ?>
                                    </option>
                                <?php  endforeach; ?>
                            </datalist>
                        </div>
                        
                        <!-- Tooth Numbers Section -->
                        <div class="form-group full-width">
                            <label>Tooth Numbers</label>
                            <div class="tooth-selection-container">
                                <div class="tooth-grid" id="edit-tooth-grid">
                                    <!-- Tooth buttons generated by JS -->
                                </div>
                                <div class="selected-teeth-display">
                                    <div class="selected-teeth-label">Selected Teeth:</div>
                                    <div class="selected-teeth-list" id="edit-selected-teeth-list">None</div>
                                    <input type="hidden" id="edit-record-tooth-numbers" name="tooth_numbers">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Surfaces Section -->
                        <div class="form-group full-width">
                            <label>Tooth Surfaces</label>
                            <div class="surfaces-selection">
                                <div class="surface-checkboxes" id="edit-surface-checkboxes">
                                    <label class="surface-checkbox">
                                        <input type="checkbox" name="edit_surfaces[]" value="mesial">
                                        <span>Mesial (M)</span>
                                    </label>
                                    <label class="surface-checkbox">
                                        <input type="checkbox" name="edit_surfaces[]" value="distal">
                                        <span>Distal (D)</span>
                                    </label>
                                    <label class="surface-checkbox">
                                        <input type="checkbox" name="edit_surfaces[]" value="occlusal">
                                        <span>Occlusal (O)</span>
                                    </label>
                                    <label class="surface-checkbox">
                                        <input type="checkbox" name="edit_surfaces[]" value="buccal">
                                        <span>Buccal (B)</span>
                                    </label>
                                    <label class="surface-checkbox">
                                        <input type="checkbox" name="edit_surfaces[]" value="lingual">
                                        <span>Lingual (L)</span>
                                    </label>
                                    <label class="surface-checkbox">
                                        <input type="checkbox" name="edit_surfaces[]" value="palatal">
                                        <span>Palatal (P)</span>
                                    </label>
                                </div>
                                <input type="hidden" id="edit-record-surfaces" name="surfaces">
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="edit-record-description" class="required">Description</label>
                            <textarea id="edit-record-description" class="form-control" name="description" rows="4" required></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="edit-record-findings">Clinical Findings</label>
                            <textarea id="edit-record-findings" class="form-control" name="findings" rows="4"></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="edit-record-notes">Additional Notes</label>
                            <textarea id="edit-record-notes" class="form-control" name="notes" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="edit-record-followup">Follow-up Instructions</label>
                            <textarea id="edit-record-followup" class="form-control" name="followup_instructions" rows="3"></textarea>
                        </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" id="cancel-edit-record">Cancel</button>
                <button type="submit" class="btn btn-success" id="update-record-btn">
                    <i class="fas fa-save"></i> Update Record
                </button>
            </div>
        </form>
        </div>
    </div>

    <!-- Medical History Modal -->
    <div class="modal" id="medical-history-modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-notes-medical"></i> Patient Medical History</h3>
                <button class="close-modal" id="close-medical-history-modal">&times;</button>
            </div>
            <div class="modal-body" id="medical-history-content">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="close-medical-history-btn">Close</button>
            </div>
        </div>
    </div>

    <script src="<?php echo clean_url('public/assets/js/admin-records.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo clean_url('public/assets/js/odontogram.js'); ?>?v=<?php echo time(); ?>"></script>
</body>
</html>