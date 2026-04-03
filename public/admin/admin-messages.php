<?php 
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Services/TextBeeSMSService.php';


// Ensure user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin-login.php");
    exit();
}


// Initialize Database
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
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
                    ':staff_id' => 'ADMIN_' . ($_SESSION['admin_id'] ?? 'UNKNOWN'),
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

$currentPage = 'messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Send Reminders - Cosmo Smiles Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php  include 'includes/admin-sidebar-css.php'; ?>
    <style>

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
            animation: fadeIn 0.6s ease;
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
            color: var(--text);
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
            color: var(--text);
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

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive adaptation */
        @media (max-width: 992px) {
            .admin-sidebar { transform: translateX(-100%); z-index: 1001; }
            .admin-sidebar.active { transform: translateX(0); }
            .admin-main { margin-left: 0; }
            .hamburger { display: block; }
        }
    </style>
</head>
<body>
    <!-- Overlay for mobile sidebar -->
    <div class="overlay"></div>

    <?php  include 'includes/admin-header.php'; ?>

    <div class="admin-container">
        <?php  include 'includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="dashboard-header">
                <div class="header-content">
                    <h1>Messaging & Reminders</h1>
                    <p>Send automated SMS reminders and follow-ups to patients</p>
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

            <div id="success-message" class="success-message">
                <i class="fas fa-check-circle"></i>
                <span id="success-text">Message sent successfully!</span>
            </div>

            <div class="reminders-container">
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
                        <div id="lookup-results" class="lookup-results">
                            <!-- Results will be injected here -->
                        </div>
                    </div>

                    <div id="appointment-selection" class="appointment-selection">
                        <div class="lookup-header">
                            <h4>Select Appointment</h4>
                        </div>
                        <div id="appointment-list" class="appointment-list">
                            <!-- Appointments will be injected here -->
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
                                Character count: <span id="char-count">0</span>/1600 &bull; 
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

                        <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 20px;" id="send-btn">
                            <i class="fas fa-paper-plane"></i> Send SMS Reminder
                        </button>
                    </form>
                </div>
            </div>
        </main>
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

        const patients = <?php  echo $patientsJson; ?>;
        let selectedPatient = null;
        let selectedAppointment = null;

        // Set date
        const currentDate = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };

        // Sidebar and Header Interactions
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
        const smsContent = document.getElementById('sms-content');
        const createReminderForm = document.getElementById('create-reminder-form');
        const sendBtn = document.getElementById('send-btn');

        // Set default send time to current time + 1 hour
        const now = new Date();
        const nextHour = new Date(now.getTime() + 60 * 60 * 1000);
        sendTime.value = nextHour.toISOString().slice(0, 16);

        // Search patient by Client ID
        function searchPatient() {
            const searchTerm = patientSearch.value.trim();
            
            if (!searchTerm) {
                showNotification('Please enter a Patient ID (Client ID)', 'error');
                return;
            }
            
            lookupResults.innerHTML = '';
            lookupResults.classList.remove('active');
            appointmentSelection.classList.remove('active');
            
            const foundPatient = patients.find(patient => 
                patient.id.toLowerCase() === searchTerm.toLowerCase() || 
                patient.name.toLowerCase().includes(searchTerm.toLowerCase())
            );
            
            if (!foundPatient) {
                lookupResults.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: var(--error);">
                        <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>Patient not found. Please check the Client ID and try again.</p>
                    </div>
                `;
                lookupResults.classList.add('active');
                return;
            }
            
            displayPatient(foundPatient);
        }
        
        function displayPatient(patient) {
            lookupResults.innerHTML = `
                <div class="patient-result" onclick="selectPatientItem('${patient.id}')">
                    <div class="patient-info">
                        <div>
                            <div class="patient-name">${escapeHtml(patient.name)}</div>
                            <div class="patient-details">
                                Client ID: ${escapeHtml(patient.id)} \u2022 Phone: ${escapeHtml(patient.phone)}
                            </div>
                        </div>
                        <div>
                            <span class="badge" style="background: var(--success); color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                                ${patient.appointments.length} appointment(s)
                            </span>
                        </div>
                    </div>
                </div>
            `;
            lookupResults.classList.add('active');
        }

        window.selectPatientItem = (id) => {
            const patient = patients.find(p => p.id === id);
            document.querySelectorAll('.patient-result').forEach(item => {
                item.classList.remove('selected');
                if (item.getAttribute('onclick').includes(id)) item.classList.add('selected');
            });
            
            selectedPatient = patient;
            showAppointments(patient);
        };

        function showAppointments(patient) {
            appointmentList.innerHTML = '';
            appointmentSelection.classList.add('active');
            selectedAppointment = null;
            
            if (!patient.appointments || patient.appointments.length === 0) {
                appointmentList.innerHTML = `<p style="padding: 20px; text-align: center;">No appointments found.</p>`;
                return;
            }
            
            appointmentList.innerHTML = patient.appointments.map(a => `
                <div class="appointment-item" onclick="selectAppointmentItem('${a.id}')">
                    <div class="appointment-details">
                        <div>
                            <div class="appointment-id">${escapeHtml(a.id)}</div>
                            <div class="appointment-info">
                                ${formatDate(a.date)} at ${formatTime(a.time)}<br>
                                <small>${escapeHtml(a.type)} \u2022 ${escapeHtml(a.status)}</small>
                            </div>
                        </div>
                        <div><i class="fas fa-chevron-right"></i></div>
                    </div>
                </div>
            `).join('');
            
            // Auto-select first appointment
            if (patient.appointments.length > 0) {
                const firstId = patient.appointments[0].id;
                selectAppointmentItem(firstId);
            }
        }

        window.selectAppointmentItem = (id) => {
            selectedAppointment = selectedPatient.appointments.find(a => a.id === id);
            document.querySelectorAll('.appointment-item').forEach(item => {
                item.classList.remove('selected');
                if (item.querySelector('.appointment-id').textContent === id) item.classList.add('selected');
            });
            updateSMSPreview();
        };

        // Helper functions
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateString) {
            if (!dateString) return 'Date not set';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        }

        function formatTime(timeString) {
            if (!timeString) return 'Time not set';
            const [hours, minutes] = timeString.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            return `${hour % 12 || 12}:${minutes} ${ampm}`;
        }

        reminderMessage.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;
            charCount.style.color = count > 1600 ? 'var(--error)' : (count > 1500 ? 'var(--warning)' : 'var(--dark)');
            updateSMSPreview();
        });

        function updateSMSPreview() {
            if (!selectedPatient || !selectedAppointment) {
                smsContent.textContent = 'Please select a patient and appointment first.';
                return;
            }
            
            let preview = reminderMessage.value;
            preview = preview.replace(/\[Patient Name\]/g, selectedPatient.name)
                            .replace(/\[Appointment Date\]/g, formatDate(selectedAppointment.date))
                            .replace(/\[Appointment Time\]/g, formatTime(selectedAppointment.time))
                            .replace(/\[Appointment ID\]/g, selectedAppointment.id);
            
            smsContent.textContent = preview || 'Your SMS preview will appear here...';
        }

        reminderType.addEventListener('change', function() {
            if (this.value && selectedPatient && selectedAppointment) {
                let autoMessage = '';
                switch(this.value) {
                    case 'appointment':
                        autoMessage = `Hi [Patient Name], this is a reminder for your dental appointment on [Appointment Date] at [Appointment Time]. ID: [Appointment ID]. Please arrive 15 minutes early.`;
                        break;
                    case 'followup':
                        autoMessage = `Hi [Patient Name], this is a follow-up from Cosmo Smiles Dental. How are you feeling after your visit on [Appointment Date]?`;
                        break;
                    case 'payment':
                        autoMessage = `Hi [Patient Name], this is a reminder regarding an outstanding balance for your visit on [Appointment Date]. Please contact us.`;
                        break;
                    case 'medication':
                        autoMessage = `Hi [Patient Name], please remember to take your medications as directed after your procedure on [Appointment Date].`;
                        break;
                    case 'custom':
                        autoMessage = `Hi [Patient Name], `;
                        break;
                }
                reminderMessage.value = autoMessage;
                reminderMessage.dispatchEvent(new Event('input'));
            }
        });

        createReminderForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!selectedPatient || !selectedAppointment) {
                showNotification('Please select a patient and appointment first', 'error');
                return;
            }
            
            const message = reminderMessage.value.trim();
            if (!message || message.length > 1600) {
                showNotification('Please enter a valid message (max 1600 characters)', 'error');
                return;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            submitBtn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'send_sms');
            formData.append('phone', selectedPatient.phone);
            formData.append('message', smsContent.textContent); // Send the processed message
            formData.append('client_id', selectedPatient.id);
            formData.append('appointment_id', selectedAppointment.id);
            formData.append('send_time', sendTime.value);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    const successMsg = document.getElementById('success-message');
                    successMsg.querySelector('span').textContent = `SMS reminder sent to ${selectedPatient.name}`;
                    successMsg.classList.add('active');
                    setTimeout(() => successMsg.classList.remove('active'), 5000);
                    
                    reminderType.value = '';
                    reminderMessage.value = '';
                    charCount.textContent = '0';
                    updateSMSPreview();
                } else {
                    showNotification('Error: ' + result.message, 'error');
                }
            } catch (error) {
                showNotification('An error occurred. Please check logs.', 'error');
            } finally {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            }
        });

        searchPatientBtn.addEventListener('click', searchPatient);
        patientSearch.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); searchPatient(); } });

        // Standardized Admin Clock
        function updateAdminClock() {
            const now = new Date();
            const dateOptions = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
            const timeOptions = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
            
            const dateEl = document.getElementById('admin-date');
            const timeEl = document.getElementById('admin-time');
            
            if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', dateOptions);
            if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', timeOptions);
        }
        setInterval(updateAdminClock, 1000);
        updateAdminClock();
    </script>
</body>
</html>
