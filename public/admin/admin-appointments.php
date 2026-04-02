<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../admin-login.php');
    exit();
}
$currentDate = date('F j, Y');
$adminFirstName = $_SESSION['admin_first_name'] ?? '';
$adminLastName = $_SESSION['admin_last_name'] ?? '';
$adminFullName = trim('Dr. '. $adminFirstName . ' ' . $adminLastName);
if (empty($adminFullName)) {
    $adminFullName = $_SESSION['admin_username'] ?? 'Administrator';
}
$dentistFirstName = $_SESSION['dentist_first_name'] ?? $adminFirstName;
$dentistLastName = $_SESSION['dentist_last_name'] ?? $adminLastName;
$dentistFullName = trim($dentistFirstName . ' ' . $dentistLastName);
if (empty($dentistFullName)) {
    $dentistFullName = $adminFullName;
}
$currentDentistId = $_SESSION['admin_id'] ?? ''; // Use admin_id (INT) to match appointments.dentist_id
$currentAdminId = $_SESSION['admin_id'] ?? '';
$isSuperAdmin = true; // All admin_users are dentists and super admins
date_default_timezone_set('Asia/Manila');
$currentDateTime = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - My Appointments - Cosmo Smiles Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-appointments.css">
    <?php include 'includes/admin-sidebar-css.php'; ?>
    <style>
        .appointments-table-container { margin-bottom: 20px !important; overflow-x: auto; }
        .appointments-table { table-layout: fixed !important; border-collapse: collapse !important; width: 100%; min-width: 1200px; }
        .appointments-table th, .appointments-table td { padding: 10px 6px !important; vertical-align: middle !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; line-height: 1.2 !important; font-size: 0.8rem !important; }
        .appointments-table th { font-size: 0.75rem !important; padding: 10px 6px !important; }
        .appointments-table td.patient-name { max-width: 120px !important; min-width: 100px !important; }
        .appointments-table td.appointment-date { width: 90px !important; min-width: 90px !important; }
        .appointments-table td.appointment-time { width: 70px !important; min-width: 70px !important; }
        .appointments-table td.service-name { max-width: 120px !important; min-width: 100px !important; }
        .status-badge { padding: 3px 6px !important; font-size: 0.7rem !important; min-width: 70px !important; display: inline-block !important; text-align: center !important; }
        .table-actions { display: flex !important; flex-wrap: wrap !important; gap: 3px !important; min-width: 180px; max-width: 200px; }
        .action-btn-small { padding: 4px 6px !important; font-size: 0.7rem !important; white-space: nowrap !important; min-width: auto !important; height: 24px; display: inline-flex; align-items: center; justify-content: center; }
        #allAppointmentsTable .table-actions { min-width: 150px; }
        .action-btn-small.icon-only { width: 24px; padding: 4px !important; }
        .action-btn-small.icon-only i { margin: 0 !important; }
        .appointment-time { font-weight: 500 !important; }
        .appointment-time:after { content: none !important; }
        .appointments-table-container { padding: 15px !important; }
        .appointments-table-header { margin-bottom: 12px !important; padding-bottom: 8px !important; }
        .modal-content { max-width: 600px !important; }
        .modal-body { padding: 15px !important; }
        .detail-row { margin-bottom: 6px !important; padding-bottom: 6px !important; }
        .calendar-filter { padding: 15px !important; margin-bottom: 15px !important; }
        .filter-row { margin-bottom: 12px !important; }
        .admin-main { padding: 15px !important; overflow-x: hidden !important; max-width: 100%; }
        .main-content-wrapper { gap: 15px !important; width: 100%; max-width: 100%; display: flex; flex-direction: column; overflow: hidden; }
        .search-box { display: flex !important; gap: 6px !important; align-items: center !important; }
        .search-box input { min-width: 150px !important; padding: 6px 10px !important; }
        .table-pagination { padding-top: 12px !important; margin-top: 8px !important; }
        .appointments-table tbody tr { height: 40px !important; }
        #confirmedAppointmentsTable th:nth-child(1) { width: 100px; }
        #confirmedAppointmentsTable th:nth-child(2) { width: 80px; }
        #confirmedAppointmentsTable th:nth-child(3) { width: 120px; }
        #confirmedAppointmentsTable th:nth-child(4) { width: 90px; }
        #confirmedAppointmentsTable th:nth-child(5) { width: 70px; }
        #confirmedAppointmentsTable th:nth-child(6) { width: 110px; }
        #confirmedAppointmentsTable th:nth-child(7) { width: 90px; }
        #confirmedAppointmentsTable th:nth-child(8) { width: 80px; }
        #confirmedAppointmentsTable th:nth-child(9) { width: 180px; }
        #completedAppointmentsTable th:nth-child(1) { width: 100px; }
        #completedAppointmentsTable th:nth-child(2) { width: 80px; }
        #completedAppointmentsTable th:nth-child(3) { width: 120px; }
        #completedAppointmentsTable th:nth-child(4) { width: 90px; }
        #completedAppointmentsTable th:nth-child(5) { width: 100px; }
        #completedAppointmentsTable th:nth-child(6) { width: 70px; }
        #completedAppointmentsTable th:nth-child(7) { width: 90px; }
        #completedAppointmentsTable th:nth-child(8) { width: 150px; }
        #allAppointmentsTable th:nth-child(1) { width: 100px; }
        #allAppointmentsTable th:nth-child(2) { width: 80px; }
        #allAppointmentsTable th:nth-child(3) { width: 120px; }
        #allAppointmentsTable th:nth-child(4) { width: 90px; }
        #allAppointmentsTable th:nth-child(5) { width: 70px; }
        #allAppointmentsTable th:nth-child(6) { width: 110px; }
        #allAppointmentsTable th:nth-child(7) { width: 90px; }
        #allAppointmentsTable th:nth-child(8) { width: 80px; }
        #allAppointmentsTable th:nth-child(9) { width: 90px; }
        #allAppointmentsTable th:nth-child(10) { width: 150px; }
        .message-container { position: fixed; top: 100px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px; animation: slideInRight 0.3s ease; }
        .message-content { display: flex; align-items: center; gap: 12px; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); font-weight: 500; }
        .message-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .message-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .message-info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        .message-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .message-close { margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; opacity: 0.7; padding: 5px; border-radius: 4px; transition: opacity 0.2s; }
        .message-close:hover { opacity: 1; background: rgba(0,0,0,0.1); }
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .confirmation-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center; }
        .confirmation-modal.active { display: flex; }
        .confirmation-content { background: white; border-radius: 8px; padding: 30px; max-width: 400px; width: 90%; box-shadow: 0 5px 20px rgba(0,0,0,0.2); text-align: center; }
        .confirmation-icon { font-size: 48px; margin-bottom: 20px; }
        .confirmation-icon.warning { color: #ffc107; }
        .confirmation-icon.success { color: #28a745; }
        .confirmation-icon.error { color: #dc3545; }
        .confirmation-text { margin-bottom: 25px; font-size: 16px; line-height: 1.5; }
        .confirmation-buttons { display: flex; gap: 10px; justify-content: center; }
        .confirmation-btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; transition: all 0.2s; min-width: 100px; }
        .confirmation-btn.confirm { background: #28a745; color: white; }
        .confirmation-btn.confirm:hover { background: #218838; }
        .confirmation-btn.cancel { background: #6c757d; color: white; }
        .confirmation-btn.cancel:hover { background: #5a6268; }
        .time-tracking-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 15px; }
        .time-tracker-item { display: flex; flex-direction: column; }
        .time-tracker-item label { font-weight: 600; color: var(--dark); margin-bottom: 5px; font-size: 0.85rem; }
        .time-tracker-item input, .time-tracker-item select { padding: 8px; border: 1px solid var(--border); border-radius: 6px; font-family: "Open Sans", sans-serif; font-size: 0.85rem; background: white; }
        .time-tracker-item input:read-only { background: #f8f9fa; cursor: not-allowed; }
        .action-btn-small.edit { background: var(--warning); color: var(--dark); }
        .action-btn-small.edit:hover { background: #e0a800; color: var(--dark); }
        .date-display {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .clock-content {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
            text-align: left;
        }

        #admin-date {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--dark);
            opacity: 0.7;
        }

        #admin-time {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            font-family: 'Monaco', 'Consolas', monospace;
            letter-spacing: 0.5px;
        }

        .current-time { margin-left: 10px; font-weight: 500; color: var(--secondary); background: var(--light-accent); padding: 3px 8px; border-radius: 4px; font-size: 0.85rem; }
        .appointment-patient { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 0.75rem; }
        .time-display-fix::after { content: none !important; }
        .edit-form-container { display: none; margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid var(--border); }
        .edit-form-container.active { display: block; }
        .edit-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .edit-form-group { display: flex; flex-direction: column; }
        .edit-form-group.full-width { grid-column: span 2; }
        .edit-form-group label { font-weight: 600; margin-bottom: 5px; color: var(--dark); font-size: 0.85rem; }
        .edit-form-control { padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-family: "Open Sans", sans-serif; font-size: 0.85rem; }
        .edit-form-control:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(108, 168, 240, 0.2); }
        .edit-form-actions { grid-column: span 2; display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px; }
        .action-btn-small.edit.disabled { opacity: 0.5; cursor: not-allowed; }
        .tooltip { position: relative; display: inline-block; }
        .tooltip .tooltip-text { visibility: hidden; width: 200px; background-color: var(--dark); color: white; text-align: center; border-radius: 6px; padding: 8px; position: absolute; z-index: 1; bottom: 125%; left: 50%; transform: translateX(-50%); opacity: 0; transition: opacity 0.3s; font-size: 0.75rem; font-weight: normal; }
        .tooltip:hover .tooltip-text { visibility: visible; opacity: 1; }
        .time-slot-unavailable { background-color: #f8d7da !important; color: #721c24 !important; cursor: not-allowed !important; }
        .time-slot-disabled { opacity: 0.5 !important; cursor: not-allowed !important; }
        input[type="date"]:disabled { opacity: 0.5; cursor: not-allowed; }
        @media (max-width: 1400px) { .appointments-table { min-width: 1100px; } .table-actions { min-width: 160px; } #allAppointmentsTable .table-actions { min-width: 130px; } }
        @media (max-width: 1200px) { .appointments-table { min-width: 1000px; } .table-actions { min-width: 140px; } }
        @media (max-width: 992px) { .appointments-table { min-width: 900px; } }
    </style>
    <script>
        const CURRENT_DENTIST_ID = '<?php echo $currentDentistId; ?>';
        const CURRENT_ADMIN_ID = '<?php echo $currentAdminId; ?>';
        const ADMIN_FULL_NAME = '<?php echo htmlspecialchars($adminFullName, ENT_QUOTES); ?>';
        const DENTIST_FULL_NAME = '<?php echo htmlspecialchars($dentistFullName, ENT_QUOTES); ?>';
        const IS_SUPER_ADMIN = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
        const SERVER_TIME = '<?php echo $currentDateTime; ?>';
        const PHILIPPINES_TIMEZONE = 'Asia/Manila';
        const API_BASE_URL = '../controllers/AdminAppointmentController.php';
        console.log('✓ API_BASE_URL:', API_BASE_URL);
        console.log('✓ Admin Name:', ADMIN_FULL_NAME);
        console.log('✓ Dentist Name:', DENTIST_FULL_NAME);
        console.log('✓ Server Time:', SERVER_TIME);
    </script>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">
                    <a href="../../../index.php"><img src="../assets/images/logo-main-white-1.png" alt="Cosmo Smiles Dental"></a>
                </div>
                <div class="header-right">
                    <div class="hamburger">
                        <i class="fas fa-bars"></i>
                    </div>
                </div>
            </nav>
        </div>
    </header>
    <div class="overlay"></div>
    <div class="confirmation-modal" id="confirmationModal">
        <div class="confirmation-content">
            <div class="confirmation-icon" id="confirmationIcon">
                <i class="fas fa-question-circle"></i>
            </div>
            <div class="confirmation-text" id="confirmationText">
                Are you sure you want to perform this action?
            </div>
            <div class="confirmation-buttons">
                <button class="confirmation-btn confirm" id="confirmAction">Confirm</button>
                <button class="confirmation-btn cancel" id="cancelAction">Cancel</button>
            </div>
        </div>
    </div>
    <div class="modal" id="appointmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-check"></i> Appointment Details</h3>
                <button class="modal-close" id="modalClose">&times;</button>
            </div>
            <div class="modal-body">
                <div class="appointment-detail">
                    <h4><i class="fas fa-info-circle"></i> Appointment Information</h4>
                    <div class="detail-row">
                        <span class="detail-label">Appointment ID:</span>
                        <span class="detail-value" id="modalAppointmentId">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Patient:</span>
                        <span class="detail-value" id="modalPatient">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Patient ID:</span>
                        <span class="detail-value" id="modalPatientId">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Service:</span>
                        <span class="detail-value" id="modalService">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date & Time:</span>
                        <span class="detail-value" id="modalDateTime">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value"><span id="modalStatus" class="status-badge">-</span></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Type:</span>
                        <span class="detail-value" id="modalPaymentType">-</span>
                    </div>
                    <div class="detail-row" id="durationRow">
                        <span class="detail-label">Duration:</span>
                        <span class="detail-value" id="modalDuration">-</span>
                    </div>
                    <!-- Client Feedback Section -->
                    <div id="modalFeedbackSection" style="display: none; margin-top: 15px; padding: 12px; background: #fff9f0; border: 1px solid #ffeeba; border-radius: 8px;">
                        <h5 style="margin-bottom: 8px; color: #856404;"><i class="fas fa-star" style="color: #ffc107;"></i> Client Feedback</h5>
                        <div class="detail-row" style="margin-bottom: 5px;">
                            <span class="detail-label">Rating:</span>
                            <span id="modalFeedbackRating" style="font-weight: 700; color: #ffc107;"></span>
                        </div>
                        <div style="font-style: italic; color: #666; font-size: 0.9rem;" id="modalFeedbackComment"></div>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Dentist:</span>
                        <span class="detail-value" id="modalDentist"><?php echo htmlspecialchars($dentistFullName); ?></span>
                    </div>
                    <div class="time-tracking-grid" id="timeTrackingSection" style="display: none; margin-top: 20px;">
                        <div class="time-tracker-item">
                            <label for="appointmentDuration">Duration (minutes)</label>
                            <select id="appointmentDuration">
                                <option value="30">30</option>
                                <option value="45">45</option>
                                <option value="60">60</option>
                                <option value="90">90</option>
                                <option value="120">120</option>
                            </select>
                        </div>
                        <div class="time-tracker-item">
                            <label for="startTime">Start Time</label>
                            <input type="time" id="startTime">
                        </div>
                        <div class="time-tracker-item">
                            <label for="endTime">End Time</label>
                            <input type="time" id="endTime" readonly>
                        </div>
                    </div>
                    <div class="edit-form-container" id="editFormContainer">
                        <h5><i class="fas fa-edit"></i> Edit Appointment Details</h5>
                        <div class="edit-form-grid" id="editFormGrid">
                        </div>
                        <div class="edit-form-actions">
                            <button class="btn btn-success" id="saveEditBtn">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button class="btn btn-warning" id="cancelEditBtn">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <h5><i class="fas fa-sticky-note"></i> Notes</h5>
                        <div style="display: grid; grid-template-columns: 1fr; gap: 15px; margin-top: 10px;">
                            <div>
                                <span class="label-heading" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Client Notes:</span>
                                <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; border-left: 3px solid var(--accent); min-height: 60px; max-height: 120px; overflow-y: auto;" id="modalClientNotes">
                                    No client notes
                                </div>
                            </div>
                            <div>
                                <label for="adminNotes">Admin Notes:</label>
                                <textarea id="adminNotes" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; font-family: 'Open Sans', sans-serif; font-size: 0.9rem; resize: vertical; min-height: 80px;" placeholder="Add admin notes here..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="appointment-detail" id="followupSection" style="display: none; margin-top: 20px;">
    <h4><i class="fas fa-calendar-plus"></i> Schedule Follow-up</h4>
    <div class="followup-section">
        <div class="detail-row">
            <span class="detail-label">Next Follow-up:</span>
            <span class="detail-value" id="modalFollowupInfo">Not scheduled</span>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label for="followupDate">Select Date:</label>
                <input type="date" id="followupDate" class="filter-control" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
            </div>
            <div>
                <label for="followupTime">Select Time:</label>
                <select id="followupTime" class="filter-control">
                    <option value="08:00">8:00 AM</option>
                    <option value="09:00">9:00 AM</option>
                    <option value="10:00">10:00 AM</option>
                    <option value="11:00">11:00 AM</option>
                    <option value="12:00">12:00 PM</option>
                    <option value="13:00">1:00 PM</option>
                    <option value="14:00">2:00 PM</option>
                    <option value="15:00">3:00 PM</option>
                    <option value="16:00">4:00 PM</option>
                    <option value="17:00">5:00 PM</option>
                    <option value="18:00">6:00 PM</option>
                </select>
            </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label for="followupService">Service:</label>
                <select id="followupService" class="filter-control">
                    <option value="">Select Service</option>
                </select>
            </div>
        </div>
        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
            <button class="btn btn-primary" id="checkAvailability">
                <i class="fas fa-search"></i> Check Availability
            </button>
            <button class="btn btn-success" id="scheduleFollowup" style="display: none;">
                <i class="fas fa-calendar-check"></i> Schedule Follow-up
            </button>
        </div>
        <div id="availabilityResult" class="availability-result" style="display: none;"></div>
    </div>
</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-warning" id="editAppointmentBtn">
                    <i class="fas fa-edit"></i> Edit Appointment
                </button>
                <button class="btn btn-success" id="completeAppointmentBtn">
                    <i class="fas fa-check-circle"></i> Mark as Completed
                </button>
                <button class="btn btn-primary" id="printReceipt">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <button class="btn" id="closeModal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
    <div class="admin-container">
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-tooth"></i> Dental Admin</h3>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <a href="admin-dashboard.php" class="sidebar-item">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="admin-appointments.php" class="sidebar-item active">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Appointments</span>
                    </a>
                    <a href="admin-patients.php" class="sidebar-item">
                        <i class="fas fa-users"></i>
                        <span>Patients</span>
                    </a>
                    <a href="admin-records.php" class="sidebar-item">
                        <i class="fas fa-file-medical"></i>
                        <span>Patient Records</span>
                    </a>
                    <a href="admin-staff.php" class="sidebar-item">
                        <i class="fas fa-user-md"></i>
                        <span>Staff Management</span>
                    </a>
                    <a href="admin-messages.php" class="sidebar-item">
                        <i class="fas fa-message"></i>
                        <span>Messages</span>    
                    </a>
                    <a href="admin-services.php" class="sidebar-item">
                        <i class="fas fa-teeth"></i>
                        <span>Services</span>
                    </a>
                    <a href="admin-settings.php" class="sidebar-item">
                        <i class="fas fa-cogs"></i>
                        <span>Admin Settings</span> 
                    </a>
                </div>
            </nav>
            <div class="sidebar-footer">
                <div class="admin-profile">
                    <div class="profile-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="profile-info">
                        <span class="profile-name"><?php echo htmlspecialchars($adminFullName); ?></span>
                        <span class="profile-role">Administrator</span>
                    </div>
                </div>
                <a href="admin-logout.php" class="sidebar-item logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>
        <main class="admin-main">
            <div class="main-content-wrapper">
                <div class="dashboard-header">
                    <div class="header-content">
                        <h1>My Appointments Calendar</h1>
                        <p>View and manage your appointments</p>
                    </div>
                    <div class="header-actions">
                        <div class="date-display">
                            <i class="fas fa-calendar-alt" style="font-size: 1.2rem; color: var(--secondary);"></i>
                            <div class="clock-content">
                                <span id="admin-date">Loading...</span>
                                <span id="admin-time">00:00:00 AM</span>
                            </div>
                        </div>
                        <button class="btn btn-primary" id="view-today">
                            <i class="fas fa-calendar-day"></i> Today
                        </button>
                    </div>
                </div>
                <div class="calendar-filter">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="status-filter">Filter by Status</label>
                            <select id="status-filter" class="filter-control">
                                <option value="all">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="no_show">No Show</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="date-filter">Filter by Date</label>
                            <input type="date" id="date-filter" class="filter-control" value="">
                        </div>
                        <div class="filter-group">
                            <label for="dentist-filter">Filter by Dentist</label>
                            <select id="dentist-filter" class="filter-control">
                                <option value="all">All Dentists</option>
                                <option value="<?php echo $currentAdminId; ?>">My Appointments</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="patient-filter">Filter by Patient Name</label>
                            <input type="text" id="patient-filter" class="filter-control" placeholder="Enter patient name...">
                        </div>
                    </div>
                    <div class="filter-row">
                        <div class="filter-group">
                            <span class="label-heading" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">View Type</span>
                            <div class="filter-tabs">
                                <button class="filter-tab" data-period="today">Today</button>
                                <button class="filter-tab" data-period="week">This Week</button>
                                <button class="filter-tab active" data-period="month">This Month</button>
                            </div>
                        </div>
                        <div class="filter-group">
                            <div class="filter-actions">
                                <button class="btn btn-primary" id="apply-filter">
                                    <i class="fas fa-filter"></i> Apply Filter
                                </button>
                                <button class="btn" id="clear-filter">
                                    <i class="fas fa-times"></i> Clear Filter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="calendar-navigation">
                    <button class="nav-btn" id="prev-period">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <div class="current-period" id="selected-period">This Month, <?php echo date('F Y'); ?></div>
                    <button class="nav-btn" id="next-period">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="calendar-dashboard">
                    <div class="calendar-header">
                        <div class="calendar-day-header">Sun</div>
                        <div class="calendar-day-header">Mon</div>
                        <div class="calendar-day-header">Tue</div>
                        <div class="calendar-day-header">Wed</div>
                        <div class="calendar-day-header">Thu</div>
                        <div class="calendar-day-header">Fri</div>
                        <div class="calendar-day-header">Sat</div>
                    </div>
                    <div class="calendar-grid" id="calendarGrid">
                    </div>
                </div>
                <div class="section-header">
                    <h2><i class="fas fa-list-alt"></i> Appointment Management</h2>
                </div>
                <div class="appointments-table-container">
                    <div class="appointments-table-header">
                        <h3><i class="fas fa-calendar-check"></i> Your Appointments</h3>
                        <div class="date-display">
                            <i class="fas fa-list"></i>
                            <span id="confirmed-table-period">This Month, <?php echo date('F Y'); ?></span>
                        </div>
                    </div>
                    <table class="appointments-table" id="confirmedAppointmentsTable">
                        <thead>
                            <tr>
                                <th>Appointment ID</th>
                                <th>Patient ID</th>
                                <th>Patient</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Service</th>
                                <th>Payment Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="confirmedAppointmentsTableBody">
                            <tr>
                                <td colspan="9" class="no-appointments">
                                    <i class="fas fa-calendar-check"></i>
                                    <p>No confirmed appointments found</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="table-pagination" id="confirmedTablePagination" style="display: none;">
                        <div class="table-info">
                            Showing <span id="confirmedTableStart">0</span> to <span id="confirmedTableEnd">0</span> of <span id="confirmedTableTotal">0</span> appointments
                        </div>
                        <div class="table-pagination-controls">
                            <button class="page-btn" id="prevConfirmedPage">
                                <i class="fas fa-chevron-left"></i> Previous
                            </button>
                            <div class="page-numbers" id="confirmedPageNumbers"></div>
                            <button class="page-btn" id="nextConfirmedPage">
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="appointments-table-container completed-appointments">
                    <div class="appointments-table-header">
                        <h3><i class="fas fa-check-circle"></i> Completed Appointments</h3>
                        <div class="header-filters" style="display: flex; gap: 15px; align-items: center;">
                            <div class="date-display" style="margin: 0;">
                                <i class="fas fa-history"></i>
                                <span id="completed-table-period">Last 30 Days</span>
                            </div>
                            <select id="completed-time-filter" class="form-control" style="width: auto; padding: 5px 10px; border-radius: 4px; border: 1px solid var(--border-color); color: var(--text-color);">
                                <option value="30days" selected>Last 30 Days</option>
                                <option value="6months">Last 6 Months</option>
                                <option value="all">All Time</option>
                            </select>
                        </div>
                    </div>
                    <table class="appointments-table" id="completedAppointmentsTable">
                        <thead>
                            <tr>
                                <th>Appointment ID</th>
                                <th>Patient ID</th>
                                <th>Patient</th>
                                <th>Date Completed</th>
                                <th>Service</th>
                                <th>Duration</th>
                                <th>Payment Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="completedAppointmentsTableBody">
                            <tr>
                                <td colspan="8" class="no-appointments">
                                    <i class="fas fa-check-circle"></i>
                                    <p>No completed appointments found</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="table-pagination" id="completedTablePagination" style="display: none;">
                        <div class="table-info">
                            Showing <span id="completedTableStart">0</span> to <span id="completedTableEnd">0</span> of <span id="completedTableTotal">0</span> appointments
                        </div>
                        <div class="table-pagination-controls">
                            <button class="page-btn" id="prevCompletedPage">
                                <i class="fas fa-chevron-left"></i> Previous
                            </button>
                            <div class="page-numbers" id="completedPageNumbers"></div>
                            <button class="page-btn" id="nextCompletedPage">
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="appointments-table-container all-appointments">
                    <div class="appointments-table-header">
                        <h3><i class="fas fa-calendar-alt"></i> All Appointments</h3>
                        <div class="header-actions" style="margin: 0; gap: 10px; align-items: center;">
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
                            <div class="filter-group hide-no-show-filter toggle-switch-container" style="margin: 0;">
                                <label class="theme-switch" title="Toggle visibility of No-Show appointments">
                                    <input type="checkbox" id="hide-no-show-checkbox" checked>
                                    <span class="switch-slider"></span>
                                </label>
                                <label class="toggle-label" for="hide-no-show-checkbox">Hide No-Show Archive</label>
                            </div>
                            <div class="filter-group" style="margin: 0;">
                                <select id="all-status-filter" class="filter-control" style="padding: 6px 10px; font-size: 0.8rem;">
                                    <option value="all">All Statuses</option>
                                    <option value="active">Active (Pending + Confirmed)</option>
                                    <option value="pending">Pending</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="no_show">No Show</option>
                                </select>
                            </div>
                            <div class="search-box">
                                <label for="search-all-appointments" class="sr-only" style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0;">Search all appointments</label>
                                <input type="text" id="search-all-appointments" placeholder="Search appointments..." class="filter-control">
                                <button class="btn btn-primary" id="search-all-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                                <button class="btn" id="clear-all-search">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </div>
                    </div>
                    <table class="appointments-table" id="allAppointmentsTable">
                        <thead>
                            <tr>
                                <th>Appointment ID</th>
                                <th>Patient ID</th>
                                <th>Patient</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Service</th>
                                <th>Payment Type</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="allAppointmentsTableBody">
                            <tr>
                                <td colspan="10" class="no-appointments">
                                    <i class="fas fa-calendar-alt"></i>
                                    <p>No appointments found</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="table-pagination" id="allTablePagination" style="display: none;">
                        <div class="table-info">
                            Showing <span id="allTableStart">0</span> to <span id="allTableEnd">0</span> of <span id="allTableTotal">0</span> appointments
                        </div>
                        <div class="table-pagination-controls">
                            <button class="page-btn" id="prevAllPage">
                                <i class="fas fa-chevron-left"></i> Previous
                            </button>
                            <div class="page-numbers" id="allPageNumbers"></div>
                            <button class="page-btn" id="nextAllPage">
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/admin-appointments.js"></script>
</body>
</html>