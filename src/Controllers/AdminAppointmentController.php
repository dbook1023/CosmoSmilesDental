<?php
require_once '../../config/database.php';
class AdminAppointmentController {
    private $conn;
    private $dentistId;
    private $adminName;
    private $isSuperAdmin;
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            $this->sendErrorResponse('Unauthorized. Please login again.', 401);
        }
        try {
            $database = new Database();
            $this->conn = $database->getConnection();
        } catch (Exception $e) {
            $this->sendErrorResponse('Database connection failed: ' . $e->getMessage(), 500);
        }
        // Use admin_id (INT) which matches appointments.dentist_id, NOT dentist_id (VARCHAR like 'DENT0001')
        $this->dentistId = $_SESSION['admin_id'] ?? null;
        $this->adminName = trim(($_SESSION['admin_first_name'] ?? '') . ' ' . ($_SESSION['admin_last_name'] ?? ''));
        if (empty($this->adminName)) {
            $this->adminName = $_SESSION['admin_username'] ?? 'Administrator';
        }
        
        // All admin_users are dentists and super admins
        $this->isSuperAdmin = true;
    }
    public function handleRequest() {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $action = $_GET['action'] ?? 'index';
            switch ($action) {
                case 'fetchServices':
                    $this->fetchServices();
                    break;
                case 'fetchBookedSlots':
                    $this->fetchBookedTimeSlots();
                    break;
                case 'fetchConfirmed':
                    $this->fetchConfirmedAppointments();
                    break;
                case 'fetchCompleted':
                    $this->fetchCompletedAppointments();
                    break;
                case 'fetchAll':
                    $this->fetchAllAppointments();
                    break;
                case 'details':
                    $this->getAppointmentDetails();
                    break;
                case 'complete':
                    if ($method === 'POST') {
                        $this->completeAppointment();
                    }
                    break;
                case 'checkAvailability':
                    $this->checkTimeSlotAvailability();
                    break;
                case 'updateFollowup':
                    if ($method === 'POST') {
                        $this->scheduleFollowupAppointment();
                    }
                    break;
                case 'updateStatus':
                    if ($method === 'POST') {
                        $this->updateAppointmentStatus();
                    }
                    break;
                case 'updateAppointment':
                    if ($method === 'POST') {
                        $this->updateAppointmentDetails();
                    }
                    break;
                case 'updateNoShow':
                    $this->updateNoShowAppointments();
                    break;
                case 'fetchCalendar':
                    $this->fetchCalendarAppointments();
                    break;
                case 'fetchDentists':
                    $this->fetchDentists();
                    break;
                default:
                    $this->sendErrorResponse('Invalid action', 400);
            }
        } catch (Exception $e) {
            $this->sendErrorResponse('Server error: ' . $e->getMessage(), 500);
        }
    }
    private function fetchServices() {
        try {
            $query = "SELECT id, name, price, duration_minutes FROM services WHERE is_active = 1 ORDER BY name";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->sendSuccessResponse([
                'services' => $services,
                'count' => count($services)
            ]);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function fetchBookedTimeSlots() {
        $dentistId = $this->dentistId;
        try {
            $query = "
                SELECT 
                    DATE(appointment_date) as date,
                    TIME_FORMAT(appointment_time, '%H:%i') as time
                FROM appointments 
                WHERE dentist_id = ?
                AND status NOT IN ('cancelled', 'no_show')
                AND appointment_date >= CURDATE()
                ORDER BY appointment_date, appointment_time
            ";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$dentistId]);
            $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $bookedSlots = [];
            foreach ($slots as $slot) {
                $date = $slot['date'];
                if (!isset($bookedSlots[$date])) {
                    $bookedSlots[$date] = [];
                }
                $bookedSlots[$date][] = $slot['time'];
            }
            $this->sendSuccessResponse([
                'booked_slots' => $bookedSlots,
                'count' => count($slots)
            ]);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }

    private function fetchCalendarAppointments() {
        // Handle dentist filtering with super admin bypass
        $requestedDentistId = $_GET['dentist_id'] ?? 'all';
        $dentistId = $this->isSuperAdmin ? $requestedDentistId : $this->dentistId;

        $query = "
            SELECT 
                a.id, a.appointment_id, a.client_id, a.dentist_id, a.service_id,
                a.appointment_date as date, 
                a.appointment_time as time, 
                a.status,
                CONCAT(a.patient_first_name, ' ', a.patient_last_name) as patient,
                c.profile_image as patient_image
            FROM appointments a
            LEFT JOIN clients c ON (a.client_id = c.client_id OR (a.client_id REGEXP '^[0-9]+$' AND a.client_id = c.id))
            WHERE 1=1
        ";
        $params = [];
        
        if ($dentistId !== 'all') {
            $query .= " AND a.dentist_id = ?";
            $params[] = $dentistId;
        }

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($appointments as &$app) {
                $serviceDetails = $this->resolveServiceDetails($app['service_id']);
                $app['service_name'] = $serviceDetails['name'];
                $app['duration'] = $app['duration_minutes'] ?? $serviceDetails['duration'];
            }
            unset($app);

            $this->sendSuccessResponse([
                'calendar_appointments' => $appointments,
                'count' => count($appointments)
            ]);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function fetchAllAppointments() {
        $patient = $_GET['patient'] ?? null;
        $date = $_GET['date'] ?? null;
        $search = $_GET['search'] ?? null;
        $status = $_GET['status'] ?? 'all';
        
        // Handle dentist filtering with super admin bypass
        $requestedDentistId = $_GET['dentist_id'] ?? 'all';
        $dentistId = $this->isSuperAdmin ? $requestedDentistId : $this->dentistId;

        $query = "
            SELECT 
                a.*,
                u.first_name as dentist_fname,
                u.last_name as dentist_lname,
                c.profile_image as patient_image
            FROM appointments a
            LEFT JOIN admin_users u ON a.dentist_id = u.id
            LEFT JOIN clients c ON (a.client_id = c.client_id OR (a.client_id REGEXP '^[0-9]+$' AND a.client_id = c.id))
            WHERE 1=1
        ";
        $params = [];
        
        if ($dentistId !== 'all') {
            $query .= " AND a.dentist_id = ?";
            $params[] = $dentistId;
        }

        if ($search) {
            $query .= " AND (a.appointment_id LIKE ? OR a.client_id LIKE ? OR 
                      CONCAT(a.patient_first_name, ' ', a.patient_last_name) LIKE ? OR
                      a.patient_phone LIKE ? OR a.patient_email LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params = array_merge($params, 
                [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]
            );
        }
        if ($patient) {
            $query .= " AND (a.patient_first_name LIKE ? OR a.patient_last_name LIKE ?)";
            $searchTerm = "%" . $patient . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        if ($status !== 'all' && $status !== 'active') {
            $query .= " AND a.status = ?";
            $params[] = $status;
        } else if ($status === 'active') {
            $query .= " AND a.status IN ('pending', 'confirmed')";
        } else if ($status === 'all') {
            $hideNoShow = $_GET['hide_no_show'] ?? 'false';
            if ($hideNoShow === 'true') {
                $query .= " AND a.status != 'no_show'";
            }
        }
        if ($date) {
            $query .= " AND a.appointment_date >= ?";
            $params[] = $date;
        }
        $query .= " ORDER BY a.created_at DESC, a.id DESC LIMIT 100";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $formattedAppointments = [];
            foreach ($appointments as $appointment) {
                $clientNotes = $appointment['client_notes'] ?? '';
                $adminNotes = $appointment['admin_notes'] ?? '';
                $truncatedNotes = strlen($clientNotes) > 50 ? 
                    substr($clientNotes, 0, 50) . '...' : $clientNotes;
                $dentistName = trim(($appointment['dentist_fname'] ?? '') . ' ' . ($appointment['dentist_lname'] ?? ''));
                if (empty($dentistName)) {
                    $dentistName = "No Dentist Assigned";
                }
                
                // Resolve multiple services
                $serviceDetails = $this->resolveServiceDetails($appointment['service_id']);
                
                $formattedAppointments[] = [
                    'id' => $appointment['id'],
                    'appointment_id' => $appointment['appointment_id'],
                    'client_id' => $appointment['client_id'],
                    'dentist_id' => $appointment['dentist_id'],
                    'patient' => trim($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']),
                    'patient_image' => $appointment['patient_image'] ?? null,
                    'dentist_name' => 'Dr. ' . $dentistName,
                    'service' => $serviceDetails['name'],
                    'service_id' => $appointment['service_id'],
                    'date' => $appointment['appointment_date'],
                    'date_display' => date('M j, Y', strtotime($appointment['appointment_date'])),
                    'time' => date('h:i A', strtotime($appointment['appointment_time'])),
                    'duration' => $appointment['duration_minutes'] ?? $serviceDetails['duration'],
                    'payment_type' => $appointment['payment_type'] ?? 'cash',
                    'status' => $appointment['status'],
                    'client_notes' => $clientNotes,
                    'admin_notes' => $adminNotes,
                    'notes_truncated' => $truncatedNotes,
                    'created_at' => $appointment['created_at'],
                    'updated_at' => $appointment['updated_at']
                ];
            }
            $this->sendSuccessResponse([
                'all_appointments' => $formattedAppointments,
                'count' => count($formattedAppointments)
            ]);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function fetchConfirmedAppointments() {
        $patient = $_GET['patient'] ?? null;
        $date = $_GET['date'] ?? null;
        
        // Handle dentist filtering with super admin bypass
        $requestedDentistId = $_GET['dentist_id'] ?? 'all';
        $dentistId = $this->isSuperAdmin ? $requestedDentistId : $this->dentistId;

        $status = $_GET['status'] ?? 'confirmed'; 
        
        $params = [];
        // Per user request: ONLY confirmed appointments will appear in the Your Appointments table.
        // Ignore any global filter requests to display other statuses in this specific table.
        $statusFilter = "('confirmed')";

        $query = "
            SELECT 
                a.*,
                u.first_name as dentist_fname,
                u.last_name as dentist_lname,
                c.profile_image as patient_image
            FROM appointments a
            LEFT JOIN admin_users u ON a.dentist_id = u.id
            LEFT JOIN clients c ON (a.client_id = c.client_id OR (a.client_id REGEXP '^[0-9]+$' AND a.client_id = c.id))
            WHERE a.status IN $statusFilter
        ";

        if ($dentistId !== 'all') {
            $query .= " AND a.dentist_id = ?";
            $params[] = $dentistId;
        }

        if ($patient) {
            $query .= " AND (a.patient_first_name LIKE ? OR a.patient_last_name LIKE ? OR a.patient_email LIKE ?)";
            $searchTerm = "%" . $patient . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($date) {
            $query .= " AND a.appointment_date = ?";
            $params[] = $date;
        }

        $query .= " ORDER BY a.created_at DESC, a.id DESC LIMIT 100";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $formattedAppointments = [];
            foreach ($appointments as $appointment) {
                $dentistName = trim(($appointment['dentist_fname'] ?? '') . ' ' . ($appointment['dentist_lname'] ?? ''));
                if (empty($dentistName)) {
                    $dentistName = "No Dentist Assigned";
                }
                
                // Resolve multiple services
                $serviceDetails = $this->resolveServiceDetails($appointment['service_id']);
                
                $formattedAppointments[] = [
                    'id' => $appointment['id'],
                    'appointment_id' => $appointment['appointment_id'],
                    'client_id' => $appointment['client_id'],
                    'dentist_id' => $appointment['dentist_id'],
                    'patient' => trim($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']),
                    'patient_image' => $appointment['patient_image'] ?? null,
                    'dentist_name' => 'Dr. ' . $dentistName,
                    'service' => $serviceDetails['name'],
                    'service_id' => $appointment['service_id'],
                    'date' => $appointment['appointment_date'],
                    'date_display' => date('M j, Y', strtotime($appointment['appointment_date'])),
                    'time' => date('h:i A', strtotime($appointment['appointment_time'])),
                    'duration' => $appointment['duration_minutes'] ?? $serviceDetails['duration'],
                    'payment_type' => $appointment['payment_type'] ?? 'cash',
                    'status' => $appointment['status'],
                    'created_at' => $appointment['created_at']
                ];
            }
            $this->sendSuccessResponse([
                'confirmed_appointments' => $formattedAppointments,
                'count' => count($formattedAppointments)
            ]);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function fetchCompletedAppointments() {
        $patient = $_GET['patient'] ?? null;
        
        // Handle dentist filtering with super admin bypass
        $requestedDentistId = $_GET['dentist_id'] ?? 'all';
        $dentistId = $this->isSuperAdmin ? $requestedDentistId : $this->dentistId;

        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $query = "
            SELECT 
                a.*,
                c.profile_image as patient_image
            FROM appointments a
            LEFT JOIN clients c ON (a.client_id = c.client_id OR (a.client_id REGEXP '^[0-9]+$' AND a.client_id = c.id))
            WHERE a.status = 'completed'
        ";
        $params = [];
        if ($dentistId !== 'all') {
            $query .= " AND a.dentist_id = ?";
            $params[] = $dentistId;
        }
        if ($patient) {
            $query .= " AND (a.patient_first_name LIKE ? OR a.patient_last_name LIKE ?)";
            $searchTerm = "%" . $patient . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        $query .= " AND DATE(a.updated_at) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $query .= " ORDER BY a.created_at DESC, a.id DESC LIMIT 50";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $formattedAppointments = [];
            foreach ($appointments as $appointment) {
                $clientNotes = $appointment['client_notes'] ?? '';
                $adminNotes = $appointment['admin_notes'] ?? '';
                $truncatedAdminNotes = strlen($adminNotes) > 50 ? 
                    substr($adminNotes, 0, 50) . '...' : $adminNotes;
                
                // Resolve multiple services
                $serviceDetails = $this->resolveServiceDetails($appointment['service_id']);
                
                $formattedAppointments[] = [
                    'id' => $appointment['id'],
                    'appointment_id' => $appointment['appointment_id'],
                    'client_id' => $appointment['client_id'],
                    'patient' => trim($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']),
                    'patient_image' => $appointment['patient_image'] ?? null,
                    'service' => $serviceDetails['name'],
                    'service_id' => $appointment['service_id'],
                    'appointment_date' => $appointment['appointment_date'],
                    'appointment_time' => date('h:i A', strtotime($appointment['appointment_time'])),
                    'date_display' => date('M j, Y', strtotime($appointment['updated_at'])),
                    'time_display' => date('h:i A', strtotime($appointment['updated_at'])),
                    'duration' => $appointment['duration_minutes'] ?? $serviceDetails['duration'],
                    'payment_type' => $appointment['payment_type'] ?? 'cash',
                    'client_notes' => $clientNotes,
                    'admin_notes' => $adminNotes,
                    'admin_notes_truncated' => $truncatedAdminNotes,
                    'completed_date' => $appointment['updated_at']
                ];
            }
            $this->sendSuccessResponse([
                'completed_appointments' => $formattedAppointments,
                'count' => count($formattedAppointments)
            ]);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function getAppointmentDetails() {
        $appointmentId = $_GET['appointment_id'] ?? null;
        if (!$appointmentId) {
            $this->sendErrorResponse('Appointment ID is required', 400);
        }
        $query = "
            SELECT 
                a.*,
                CONCAT(a.patient_first_name, ' ', a.patient_last_name) AS patient_full_name,
                c.profile_image AS patient_image,
                CONCAT(u.first_name, ' ', u.last_name) as dentist_full_name,
                f.rating as feedback_rating,
                f.feedback as feedback_text,
                f.created_at as feedback_date
            FROM appointments a
            LEFT JOIN clients c ON (a.client_id = c.client_id OR (a.client_id REGEXP '^[0-9]+$' AND a.client_id = c.id))
            LEFT JOIN admin_users u ON a.dentist_id = u.id
            LEFT JOIN appointment_feedbacks f ON a.appointment_id = f.appointment_id
            WHERE a.appointment_id = ?
            LIMIT 1
        ";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$appointmentId]);
            if ($stmt->rowCount() === 0) {
                $this->sendErrorResponse('Appointment not found', 404);
            }
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            // Authorization Check
            if (!$this->isSuperAdmin && $appointment['dentist_id'] != $this->dentistId) {
                $this->sendErrorResponse('Unauthorized access to this appointment', 403);
            }

            $serviceDetails = $this->resolveServiceDetails($appointment['service_id']);
            $formatted = [
                'id' => $appointment['id'],
                'appointment_id' => $appointment['appointment_id'],
                'client_id' => $appointment['client_id'],
                'patient' => $appointment['patient_full_name'],
                'patient_image' => $appointment['patient_image'] ?? null,
                'dentist' => !empty($appointment['dentist_full_name']) ? "Dr. " . $appointment['dentist_full_name'] : "No Dentist Assigned",
                'service' => $serviceDetails['name'],
                'service_id' => $appointment['service_id'],
                'date' => $appointment['appointment_date'],
                'date_display' => date('F j, Y', strtotime($appointment['appointment_date'])),
                'time' => $appointment['appointment_time'],
                'time_display' => date('h:i A', strtotime($appointment['appointment_time'])),
                'status' => $appointment['status'],
                'client_notes' => $appointment['client_notes'] ?? '',
                'admin_notes' => $appointment['admin_notes'] ?? '',
                'duration' => $appointment['duration_minutes'] ?? $serviceDetails['duration'],
                'payment_type' => $appointment['payment_type'] ?? 'cash',
                'price' => $appointment['service_price'] ?? $serviceDetails['price'],
                'feedback' => $appointment['feedback_rating'] ? [
                    'rating' => $appointment['feedback_rating'],
                    'comment' => $appointment['feedback_text'],
                    'date' => date('F j, Y', strtotime($appointment['feedback_date']))
                ] : null,
                'created_at' => $appointment['created_at'],
                'updated_at' => $appointment['updated_at']
            ];
            $this->sendSuccessResponse(['appointment' => $formatted]);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function updateAppointmentStatus() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        $appointmentId = $input['appointment_id'] ?? null;
        $status = $input['status'] ?? null;
        if (!$appointmentId || !$status) {
            $this->sendErrorResponse('Appointment ID and status are required', 400);
        }
        $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
        if (!in_array($status, $validStatuses)) {
            $this->sendErrorResponse('Invalid status', 400);
        }
        try {
            $selectQuery = "SELECT * FROM appointments WHERE appointment_id = ? LIMIT 1";
            $stmt = $this->conn->prepare($selectQuery);
            $stmt->execute([$appointmentId]);
            if ($stmt->rowCount() === 0) {
                $this->sendErrorResponse('Appointment not found', 404);
            }
                        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            // Authorization Check
            if (!$this->isSuperAdmin && $appointment['dentist_id'] != $this->dentistId) {
                $this->sendErrorResponse('Unauthorized to update this appointment status', 403);
            }

            $updateQuery = "
                UPDATE appointments 
                SET status = ?, 
                    updated_at = NOW()
                WHERE appointment_id = ?
            ";
            $updateStmt = $this->conn->prepare($updateQuery);
            $success = $updateStmt->execute([
                $status,
                $appointmentId
            ]);
            if ($success) {
                $statusNote = "\n--- STATUS UPDATE ---\n";
                $statusNote .= "Status changed to: " . strtoupper($status) . "\n";
                $statusNote .= "Changed by: " . $this->adminName . "\n";
                $statusNote .= "Changed on: " . date('F j, Y h:i A') . "\n";
                
                $updateNotesQuery = "UPDATE appointments SET admin_notes = CONCAT(IFNULL(admin_notes, ''), ?) WHERE appointment_id = ?";
                $updateNotesStmt = $this->conn->prepare($updateNotesQuery);
                $updateNotesStmt->execute([$statusNote, $appointmentId]);
                
                $this->sendSuccessResponse([
                    'message' => 'Appointment status updated successfully',
                    'appointment_id' => $appointmentId,
                    'new_status' => $status
                ]);
            } else {
                $this->sendErrorResponse('Failed to update appointment status', 500);
            }
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function updateAppointmentDetails() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        $appointmentId = $input['appointment_id'] ?? null;
        $appointmentDate = $input['appointment_date'] ?? null;
        $appointmentTime = $input['appointment_time'] ?? null;
        $serviceId = $input['service_id'] ?? null;
        $paymentType = $input['payment_type'] ?? null;
        $status = $input['status'] ?? null;
        $clientNotes = $input['client_notes'] ?? null;
        $adminNotes = $input['admin_notes'] ?? null;
        if (!$appointmentId) {
            $this->sendErrorResponse('Appointment ID is required', 400);
        }
        if ($appointmentDate && strtotime($appointmentDate) < strtotime(date('Y-m-d'))) {
            $this->sendErrorResponse('Cannot edit past appointments', 400);
        }
        if ($appointmentDate) {
            $dayOfWeek = date('w', strtotime($appointmentDate));
            if ($dayOfWeek == 0) {
                $this->sendErrorResponse('Sundays are not available', 400);
            }
        }
        if ($appointmentTime) {
            $hour = (int) substr($appointmentTime, 0, 2);
            if ($appointmentDate) {
                $dayOfWeek = date('w', strtotime($appointmentDate));
                if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                    if ($hour < 8 || $hour > 18) {
                        $this->sendErrorResponse('Working hours on weekdays are 8:00 AM to 6:00 PM', 400);
                    }
                } elseif ($dayOfWeek == 6) {
                    if ($hour < 9 || $hour > 15) {
                        $this->sendErrorResponse('Working hours on Saturday are 9:00 AM to 3:00 PM', 400);
                    }
                }
            }
        }
        try {
            $selectQuery = "SELECT * FROM appointments WHERE appointment_id = ? LIMIT 1";
            $stmt = $this->conn->prepare($selectQuery);
            $stmt->execute([$appointmentId]);
            if ($stmt->rowCount() === 0) {
                $this->sendErrorResponse('Appointment not found', 404);
            }
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            // Authorization Check
            if (!$this->isSuperAdmin && $appointment['dentist_id'] != $this->dentistId) {
                $this->sendErrorResponse('Unauthorized to edit this appointment details', 403);
            }
            if (strtotime($appointment['appointment_date']) < strtotime(date('Y-m-d'))) {
                $this->sendErrorResponse('Cannot edit past appointments', 400);
            }
            if ($appointment['status'] === 'completed') {
                $this->sendErrorResponse('Cannot edit completed appointments', 400);
            }
            if ($appointmentDate && $appointmentTime) {
                $checkQuery = "
                    SELECT COUNT(*) as count 
                    FROM appointments 
                    WHERE appointment_date = ? 
                    AND appointment_time = ? 
                    AND dentist_id = ?
                    AND appointment_id != ?
                    AND status NOT IN ('cancelled', 'no_show')
                ";
                $checkStmt = $this->conn->prepare($checkQuery);
                $checkStmt->execute([
                    $appointmentDate,
                    $appointmentTime,
                    $this->dentistId,
                    $appointmentId
                ]);
                $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if ($checkResult['count'] > 0) {
                    $this->sendErrorResponse('This time slot is already booked', 400);
                }
            }
            $updateFields = [];
            $updateParams = [];
            if ($appointmentDate) {
                $updateFields[] = "appointment_date = ?";
                $updateParams[] = $appointmentDate;
            }
            if ($appointmentTime) {
                $updateFields[] = "appointment_time = ?";
                $updateParams[] = $appointmentTime;
            }
            if ($serviceId) {
                // Handle multiple services (can be array or CSV)
                if (is_array($serviceId)) {
                    $serviceIds = $serviceId;
                } else {
                    $serviceIds = array_filter(array_map('trim', explode(',', (string)$serviceId)));
                }
                $serviceIds = array_unique($serviceIds);
                $serviceId = implode(',', $serviceIds);

                // Validate and get details for all services
                $serviceDetails = $this->resolveServiceDetails($serviceId);
                
                // If the resolved name is the default 'Dental Service', it means validation failed for all IDs
                // However, resolveServiceDetails is a bit too lenient. Let's do a strict check.
                $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
                $stmt = $this->conn->prepare("SELECT COUNT(*) FROM services WHERE id IN ($placeholders) AND is_active = 1");
                $stmt->execute(array_values($serviceIds));
                $foundCount = $stmt->fetchColumn();
                
                if ($foundCount < count($serviceIds)) {
                    $this->sendErrorResponse('One or more selected services are currently unavailable.', 400);
                }

                $updateFields[] = "service_id = ?";
                $updateParams[] = $serviceId;
                
                $updateFields[] = "service_price = ?";
                $updateParams[] = $serviceDetails['price'];
                
                $updateFields[] = "duration_minutes = ?";
                $updateParams[] = $serviceDetails['duration'];
            }
            if ($paymentType) {
                $updateFields[] = "payment_type = ?";
                $updateParams[] = $paymentType;
            }
            if ($status) {
                $updateFields[] = "status = ?";
                $updateParams[] = $status;
            }
            if ($clientNotes !== null) {
                $updateFields[] = "client_notes = ?";
                $updateParams[] = $clientNotes;
            }
            if ($adminNotes !== null) {
                // Determine if we should append or overwrite. 
                // Since the UI prepopulates the textarea with existing notes, 
                // we should overwrite to avoid massive duplication.
                $updateFields[] = "admin_notes = ?";
                $updateParams[] = $adminNotes;
            }
            if (empty($updateFields)) {
                $this->sendErrorResponse('No fields to update', 400);
            }
            $updateFields[] = "updated_at = NOW()";
            $updateQuery = "UPDATE appointments SET " . implode(', ', $updateFields) . " WHERE appointment_id = ?";
            $updateParams[] = $appointmentId;
            $updateStmt = $this->conn->prepare($updateQuery);
            $success = $updateStmt->execute($updateParams);
            if ($success) {
                $this->sendSuccessResponse([
                    'message' => 'Appointment updated successfully',
                    'appointment_id' => $appointmentId
                ]);
            } else {
                $this->sendErrorResponse('Failed to update appointment', 500);
            }
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function completeAppointment() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        $appointmentId = $input['appointment_id'] ?? null;
        $adminNotes = $input['admin_notes'] ?? null;
        $duration = $input['duration'] ?? 30;
        $startTime = $input['start_time'] ?? null;
        $endTime = $input['end_time'] ?? null;
        if (!$appointmentId) {
            $this->sendErrorResponse('Appointment ID is required', 400);
        }
        try {
            $selectQuery = "SELECT * FROM appointments WHERE appointment_id = ? LIMIT 1";
            $stmt = $this->conn->prepare($selectQuery);
            $stmt->execute([$appointmentId]);
            if ($stmt->rowCount() === 0) {
                $this->sendErrorResponse('Appointment not found', 404);
            }
                        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            // Authorization Check
            if (!$this->isSuperAdmin && $appointment['dentist_id'] != $this->dentistId) {
                $this->sendErrorResponse('Unauthorized to complete this appointment', 403);
            }

            if ($appointment['status'] === 'completed') {
                $this->sendErrorResponse('Appointment is already completed', 400);
            }
            if ($appointment['status'] !== 'confirmed') {
                $this->sendErrorResponse('Only confirmed appointments can be marked as completed', 400);
            }
            $completionNotes = "\n--- COMPLETED NOTES ---\n";
            $completionNotes .= "Completed on: " . date('F j, Y h:i A') . "\n";
            $completionNotes .= "Completed by: " . $this->adminName . "\n";
            $completionNotes .= "Duration: " . $duration . " minutes\n";
            if ($startTime && $endTime) {
                $completionNotes .= "Start Time: " . $startTime . "\n";
                $completionNotes .= "End Time: " . $endTime . "\n";
            }
            if ($adminNotes) {
                $completionNotes .= "Admin Notes: " . $adminNotes . "\n";
            }
            $updateQuery = "
                UPDATE appointments 
                SET status = 'completed', 
                    duration_minutes = ?,
                    admin_notes = CONCAT(IFNULL(admin_notes, ''), ?),
                    updated_at = NOW()
                WHERE appointment_id = ?
            ";
            $updateStmt = $this->conn->prepare($updateQuery);
            $success = $updateStmt->execute([
                $duration,
                $completionNotes,
                $appointmentId
            ]);
            if ($success) {
                $this->getAppointmentDetailsById($appointmentId);
            } else {
                $this->sendErrorResponse('Failed to update appointment', 500);
            }
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function getAppointmentDetailsById($appointmentId) {
        $query = "
            SELECT 
                a.*,
                CONCAT(a.patient_first_name, ' ', a.patient_last_name) AS patient_full_name,
                
                
                CONCAT(u.first_name, ' ', u.last_name) as dentist_full_name,
                f.rating as feedback_rating,
                f.feedback as feedback_text,
                f.created_at as feedback_date
            FROM appointments a
            
            LEFT JOIN admin_users u ON a.dentist_id = u.id
            LEFT JOIN appointment_feedbacks f ON a.appointment_id = f.appointment_id
            WHERE a.appointment_id = ?
            LIMIT 1
        ";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$appointmentId]);
            if ($stmt->rowCount() === 0) {
                $this->sendErrorResponse('Appointment not found', 404);
            }
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            $formatted = [
                'id' => $appointment['id'],
                'appointment_id' => $appointment['appointment_id'],
                'client_id' => $appointment['client_id'],
                'patient' => $appointment['patient_full_name'],
                'dentist' => !empty($appointment['dentist_full_name']) ? "Dr. " . $appointment['dentist_full_name'] : "No Dentist Assigned",
                'service' => $this->resolveServiceNames($appointment['service_id']),
                'date' => $appointment['appointment_date'],
                'date_display' => date('F j, Y', strtotime($appointment['appointment_date'])),
                'time' => $appointment['appointment_time'],
                'time_display' => date('h:i A', strtotime($appointment['appointment_time'])),
                'status' => $appointment['status'],
                'client_notes' => $appointment['client_notes'] ?? '',
                'admin_notes' => $appointment['admin_notes'] ?? '',
                'duration' => $appointment['duration_minutes'] ?? 30,
                'payment_type' => $appointment['payment_type'] ?? 'cash',
                'price' => $appointment['service_price'] ?? 0.00,
                'feedback' => $appointment['feedback_rating'] ? [
                    'rating' => $appointment['feedback_rating'],
                    'comment' => $appointment['feedback_text'],
                    'date' => date('F j, Y', strtotime($appointment['feedback_date']))
                ] : null,
                'created_at' => $appointment['created_at'],
                'updated_at' => $appointment['updated_at']
            ];
            $this->sendSuccessResponse([
                'message' => 'Appointment marked as completed successfully',
                'appointment' => $formatted
            ]);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function checkTimeSlotAvailability() {
        $date = $_GET['date'] ?? null;
        $time = $_GET['time'] ?? null;
        if (!$date || !$time) {
            $this->sendErrorResponse('Date and time are required', 400);
        }
        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            $this->sendSuccessResponse([
                'available' => false,
                'message' => 'Cannot book appointments in the past'
            ]);
            return;
        }
        $dayOfWeek = date('w', strtotime($date));
        if ($dayOfWeek == 0) {
            $this->sendSuccessResponse([
                'available' => false,
                'message' => 'Sundays are not available'
            ]);
            return;
        }
        $hour = (int) substr($time, 0, 2);
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            if ($hour < 8 || $hour > 18) {
                $this->sendSuccessResponse([
                    'available' => false,
                    'message' => 'Working hours on weekdays are 8:00 AM to 6:00 PM'
                ]);
                return;
            }
        } elseif ($dayOfWeek == 6) {
            if ($hour < 9 || $hour > 15) {
                $this->sendSuccessResponse([
                    'available' => false,
                    'message' => 'Working hours on Saturday are 9:00 AM to 3:00 PM'
                ]);
                return;
            }
        }
        try {
            $query = "
                SELECT COUNT(*) as count 
                FROM appointments 
                WHERE appointment_date = ? 
                AND appointment_time = ? 
                AND dentist_id = ?
                AND status NOT IN ('cancelled', 'no_show')
            ";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$date, $time . ':00', $this->dentistId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row['count'] > 0) {
                $this->sendSuccessResponse([
                    'available' => false,
                    'message' => 'This time slot is already booked'
                ]);
            } else {
                $this->sendSuccessResponse([
                    'available' => true,
                    'message' => 'Time slot is available'
                ]);
            }
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function scheduleFollowupAppointment() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        $appointmentId = $input['appointment_id'] ?? null;
        $followupDate = $input['followup_date'] ?? null;
        $followupTime = $input['followup_time'] ?? '09:00:00';
        $serviceId = $input['service_id'] ?? null;
        if (!$appointmentId || !$followupDate) {
            $this->sendErrorResponse('Appointment ID and follow-up date are required', 400);
        }
        if (strtotime($followupDate) < strtotime(date('Y-m-d'))) {
            $this->sendErrorResponse('Cannot schedule appointments in the past', 400);
        }
        $dayOfWeek = date('w', strtotime($followupDate));
        if ($dayOfWeek == 0) {
            $this->sendErrorResponse('Sundays are not available', 400);
        }
        $hour = (int) substr($followupTime, 0, 2);
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            if ($hour < 8 || $hour > 18) {
                $this->sendErrorResponse('Working hours on weekdays are 8:00 AM to 6:00 PM', 400);
            }
        } elseif ($dayOfWeek == 6) {
            if ($hour < 9 || $hour > 15) {
                $this->sendErrorResponse('Working hours on Saturday are 9:00 AM to 3:00 PM', 400);
            }
        }
        try {
            $checkQuery = "
                SELECT COUNT(*) as count 
                FROM appointments 
                WHERE appointment_date = ? 
                AND appointment_time = ? 
                AND dentist_id = ?
                AND status NOT IN ('cancelled', 'no_show')
            ";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([$followupDate, $followupTime, $this->dentistId]);
            $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($checkResult['count'] > 0) {
                $this->sendErrorResponse('This time slot is already booked', 400);
            }
            $selectQuery = "SELECT * FROM appointments WHERE appointment_id = ? LIMIT 1";
            $stmt = $this->conn->prepare($selectQuery);
            $stmt->execute([$appointmentId]);
            if ($stmt->rowCount() === 0) {
                $this->sendErrorResponse('Original appointment not found', 404);
            }
            $originalAppointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Authorization Check
            if (!$this->isSuperAdmin && $originalAppointment['dentist_id'] != $this->dentistId) {
                $this->sendErrorResponse('Unauthorized to schedule follow-up for this patient', 403);
            }
            if ($originalAppointment['status'] !== 'completed') {
                $this->sendErrorResponse('Only completed appointments can schedule follow-ups', 400);
            }
            $newAppointmentId = $this->generateAppointmentId($followupDate);
            $insertQuery = "
                INSERT INTO appointments (
                    appointment_id,
                    client_id,
                    dentist_id,
                    service_id,
                    appointment_date,
                    appointment_time,
                    status,
                    notes,
                    duration_minutes,
                    patient_first_name,
                    patient_last_name,
                    patient_phone,
                    patient_email,
                    payment_type,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ";
            $followupNotes = "Follow-up appointment for: " . $originalAppointment['appointment_id'] . "\n" .
                           "Original appointment date: " . $originalAppointment['appointment_date'] . "\n" .
                           "Scheduled by: " . $this->adminName . "\n" .
                           "Reason: Follow-up for completed treatment\n";
            $insertStmt = $this->conn->prepare($insertQuery);
            $success = $insertStmt->execute([
                $newAppointmentId,
                $originalAppointment['client_id'],
                $this->dentistId,
                $serviceId ? $serviceId : $originalAppointment['service_id'],
                $followupDate,
                $followupTime,
                'pending',
                $followupNotes,
                $originalAppointment['duration_minutes'] ?? 30,
                $originalAppointment['patient_first_name'],
                $originalAppointment['patient_last_name'],
                $originalAppointment['patient_phone'],
                $originalAppointment['patient_email'],
                'cash'
            ]);
            if ($success) {
                $followupRecord = "\n\n--- FOLLOW-UP SCHEDULED ---\n" .
                                "Follow-up ID: " . $newAppointmentId . "\n" .
                                "Follow-up Date: " . $followupDate . "\n" .
                                "Follow-up Time: " . $followupTime . "\n" .
                                "Scheduled by: " . $this->adminName . "\n" .
                                "Scheduled on: " . date('Y-m-d H:i:s') . "\n";
                $updateNotesQuery = "UPDATE appointments SET notes = CONCAT(notes, ?) WHERE appointment_id = ?";
                $updateStmt = $this->conn->prepare($updateNotesQuery);
                $updateStmt->execute([$followupRecord, $appointmentId]);
                $this->sendSuccessResponse([
                    'message' => 'Follow-up appointment scheduled successfully',
                    'followup_appointment_id' => $newAppointmentId,
                    'followup_date' => $followupDate,
                    'followup_time' => substr($followupTime, 0, 5)
                ]);
            } else {
                $this->sendErrorResponse('Failed to create follow-up appointment', 500);
            }
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function updateNoShowAppointments() {
        try {
            $query = "
                UPDATE appointments 
                SET status = 'no_show', 
                    updated_at = NOW(),
                    notes = CONCAT(COALESCE(notes, ''), '\n\n--- AUTO NO-SHOW ---\nMarked as no-show on ', NOW(), '\n')
                WHERE status IN ('pending', 'confirmed') 
                AND appointment_date < CURDATE() 
                AND TIME(appointment_time) < '17:00:00'
            ";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $affectedRows = $stmt->rowCount();
            $this->sendSuccessResponse([
                'message' => 'No-show appointments updated successfully',
                'updated' => $affectedRows
            ]);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function generateAppointmentId($date) {
        $prefix = 'CSD';
        $dateObj = new DateTime($date);
        $datePart = $dateObj->format('Ymd');
        $query = "SELECT appointment_id FROM appointments WHERE appointment_id LIKE ? ORDER BY appointment_id DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$prefix . $datePart . '%']);
        $lastAppointment = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($lastAppointment) {
            $lastNumber = intval(substr($lastAppointment['appointment_id'], -3));
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }
        return $prefix . $datePart . $newNumber;
    }
    private function sendSuccessResponse($data = []) {
        ini_set('display_errors', 0);
        if (ob_get_length()) @ob_clean(); header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            ...$data
        ]);
        exit();
    }
    private function fetchDentists() {
        try {
            $query = "SELECT id, first_name, last_name, username FROM admin_users WHERE status = 'active' ORDER BY first_name ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->sendSuccessResponse([
                'dentists' => $dentists,
                'count' => count($dentists)
            ]);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    private function sendErrorResponse($message, $code = 400) {
        ini_set('display_errors', 0);
        if (ob_get_length()) @ob_clean(); header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
    private function resolveServiceNames($serviceIdsCsv) {
        if (empty($serviceIdsCsv)) return 'Dental Service';
        $ids = explode(',', $serviceIdsCsv);
        $ids = array_filter(array_map('trim', $ids));
        if (empty($ids)) return 'Dental Service';
        
        try {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $query = "SELECT name FROM services WHERE id IN ($placeholders)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(array_values($ids));
            $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return !empty($names) ? implode(', ', $names) : 'Dental Service';
        } catch (PDOException $e) {
            return 'Dental Service';
        }
    }

    private function resolveServiceDetails($serviceIdsCsv) {
        if (empty($serviceIdsCsv)) return ['name' => 'Dental Service', 'price' => 0, 'duration' => 30];
        $ids = explode(',', $serviceIdsCsv);
        $ids = array_filter(array_map('trim', $ids));
        if (empty($ids)) return ['name' => 'Dental Service', 'price' => 0, 'duration' => 30];
        
        try {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $query = "SELECT name, price, duration_minutes FROM services WHERE id IN ($placeholders)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(array_values($ids));
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $names = [];
            $totalPrice = 0;
            $totalDuration = 0;
            
            foreach ($services as $s) {
                $names[] = $s['name'];
                $totalPrice += $s['price'];
                $totalDuration += ($s['duration_minutes'] ?? 30);
            }
            
            return [
                'name' => !empty($names) ? implode(', ', $names) : 'Dental Service',
                'price' => $totalPrice,
                'duration' => $totalDuration
            ];
        } catch (PDOException $e) {
            return ['name' => 'Dental Service', 'price' => 0, 'duration' => 30];
        }
    }
}
$controller = new AdminAppointmentController();
$controller->handleRequest();
