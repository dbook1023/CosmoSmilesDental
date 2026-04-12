<?php
require_once __DIR__ . '/../../config/database.php';

class AppointmentController {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        
        if (!$this->conn) {
            error_log("Database connection failed in AppointmentController");
        }
        
        date_default_timezone_set('Asia/Manila');
    }

    public function bookAppointment($appointmentData) {
        try {
            error_log("=== APPOINTMENT BOOKING STARTED ===");
            error_log("POST Data received: " . print_r($appointmentData, true));
            
            // Check if this is a reschedule request
            $isReschedule = isset($appointmentData['reschedule_id']) && !empty($appointmentData['reschedule_id']);
            $originalAppointmentId = $isReschedule ? trim($appointmentData['reschedule_id']) : null;
            
            error_log("Is reschedule: " . ($isReschedule ? 'YES' : 'NO'));
            error_log("Original appointment ID: " . ($originalAppointmentId ?: 'NONE'));
            
            // Validate required fields
            $required = [
                'patient_first_name', 'patient_last_name', 'patient_phone', 
                'patient_email', 'service_id', 'appointment_date', 
                'appointment_time', 'payment_type'
            ];
            
            foreach ($required as $field) {
                if (empty($appointmentData[$field])) {
                    error_log("Missing required field: $field");
                    return ['success' => false, 'message' => "Please fill in all required fields."];
                }
            }
            
            // Validate appointment date (cannot be in the past)
            $appointmentDate = new DateTime($appointmentData['appointment_date']);
            $today = new DateTime('today');
            
            if ($appointmentDate < $today) {
                error_log("Appointment date is in the past: " . $appointmentData['appointment_date']);
                return ['success' => false, 'message' => "Appointment date cannot be in the past."];
            }
            
            // Check availability before booking
            $dentistId = $appointmentData['dentist_id'] ?? null;
            $availability = $this->checkAvailability($dentistId, $appointmentData['appointment_date']);
            
            // Convert selected time to database format for comparison
            $selectedTimeDb = date('H:i:s', strtotime($appointmentData['appointment_time']));
            $selectedTimeDisplay = date('g:i A', strtotime($appointmentData['appointment_time']));
            
            // Check if the time slot is available
            $isSlotAvailable = false;
            foreach ($availability['available_slots'] as $availableSlot) {
                $availableSlotDb = date('H:i:s', strtotime($availableSlot));
                if ($availableSlotDb === $selectedTimeDb) {
                    $isSlotAvailable = true;
                    break;
                }
            }
            
            if (!$isSlotAvailable) {
                error_log("Time slot not available: " . $selectedTimeDb);
                return ['success' => false, 'message' => "The selected time slot is no longer available. Please choose another time."];
            }
            
            // Get service-related information (Handle multiple services)
            if (is_array($appointmentData['service_id'])) {
                $serviceIds = $appointmentData['service_id'];
            } else {
                // Handle both single ID and CSV string
                $serviceIds = explode(',', (string)$appointmentData['service_id']);
            }
            
            $serviceIds = array_filter(array_map('trim', $serviceIds)); // Remove whitespace and empty values
            $serviceIds = array_unique($serviceIds); // Remove duplicates
            
            if (empty($serviceIds)) {
                return ['success' => false, 'message' => "Please select at least one service."];
            }
            
            $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
            $serviceQuery = "SELECT id, price, name, duration_minutes FROM services WHERE id IN ($placeholders) AND is_active = 1";
            $serviceStmt = $this->conn->prepare($serviceQuery);
            $serviceStmt->execute(array_values($serviceIds));
            $foundServices = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($foundServices) === 0) {
                error_log("No valid services found for IDs: " . implode(', ', $serviceIds));
                return ['success' => false, 'message' => "Invalid services selected."];
            }
            
            $servicePrice = 0;
            $serviceNames = [];
            $durationMinutes = 0;
            $finalServiceIds = [];
            
            foreach ($foundServices as $s) {
                $servicePrice += $s['price'];
                $serviceNames[] = $s['name'];
                $durationMinutes += ($s['duration_minutes'] ?? 30);
                $finalServiceIds[] = $s['id'];
            }
            
            $serviceName = implode(', ', $serviceNames);
            $combinedServiceIds = implode(',', $finalServiceIds);
            
            // Store the combined service string in the appointmentData for the rest of the method
            $appointmentData['service_id'] = $combinedServiceIds;
            
            error_log("Multiple services processed: IDs=$combinedServiceIds, Total Price=$servicePrice, Duration=$durationMinutes");
            
            // Handle dentist selection
            $dentistId = null;
            if (!empty($appointmentData['dentist_id']) && $appointmentData['dentist_id'] !== 'dr-any') {
                $dentistId = $appointmentData['dentist_id'];
            } else if (isset($appointmentData['dentist_id']) && $appointmentData['dentist_id'] === 'dr-any') {
                // Automatically assign to a random checked-in dentist if "Any" is selected
                $availableDentists = $this->getAllDentists();
                if (!empty($availableDentists)) {
                    $randomDentist = $availableDentists[array_rand($availableDentists)];
                    $dentistId = $randomDentist['id'];
                    error_log("Automatically assigned to Dentist ID: " . $dentistId);
                }
            }
            
            // Get client information - updated to use client_id (varchar)
            $clientId = null;
            if (isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] && isset($_SESSION['client_client_id'])) {
                $clientId = $_SESSION['client_client_id'];
            }
            
            error_log("Client ID from session: " . ($clientId ?: 'GUEST'));
            
            // Handle optional notes
            $notes = isset($appointmentData['appointment_notes']) ? $appointmentData['appointment_notes'] : '';
            
            // If rescheduling, UPDATE the existing appointment
            if ($isReschedule && $originalAppointmentId) {
                error_log("=== PROCESSING RESCHEDULE ===");
                error_log("Rescheduling appointment ID: " . $originalAppointmentId);
                error_log("Client ID: " . ($clientId ?: 'GUEST'));
                
                // Verify the appointment exists and belongs to the client
                $verifyQuery = "SELECT id, appointment_id, status, appointment_date, appointment_time, client_id, 
                               service_id, dentist_id, patient_first_name, patient_last_name, patient_phone, 
                               patient_email, payment_type, notes, service_price
                               FROM appointments 
                               WHERE appointment_id = :appointment_id";
                
                // Only add client_id check if user is logged in
                if ($clientId) {
                    $verifyQuery .= " AND client_id = :client_id";
                }
                
                error_log("Verification query: " . $verifyQuery);
                
                $verifyStmt = $this->conn->prepare($verifyQuery);
                $verifyStmt->bindParam(':appointment_id', $originalAppointmentId);
                if ($clientId) {
                    $verifyStmt->bindParam(':client_id', $clientId);
                }
                $verifyStmt->execute();
                $existingAppointment = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                
                error_log("Existing appointment found: " . ($existingAppointment ? 'YES' : 'NO'));
                if ($existingAppointment) {
                    error_log("Existing appointment details: " . print_r($existingAppointment, true));
                }
                
                if (!$existingAppointment) {
                    error_log("ERROR: Appointment not found or doesn't belong to client");
                    return ['success' => false, 'message' => "Appointment not found or access denied."];
                }
                
                // Check 48-hour rule for rescheduling
                $modificationCheck = $this->canModifyAppointment(
                    $existingAppointment['appointment_date'], 
                    $existingAppointment['appointment_time']
                );
                
                if (!$modificationCheck['can_modify']) {
                    error_log("Cannot modify appointment: " . $modificationCheck['message']);
                    return ['success' => false, 'message' => $modificationCheck['message']];
                }
                
                // UPDATE the existing appointment with new date/time
                $updateQuery = "UPDATE appointments 
                                SET dentist_id = :dentist_id,
                                   service_id = :service_id,
                                   service_price = :service_price,
                                   appointment_date = :appointment_date,
                                   appointment_time = :appointment_time,
                                   client_notes = :notes,
                                   duration_minutes = :duration_minutes,
                                   status = 'pending',
                                   updated_at = NOW(),
                                   patient_first_name = :patient_first_name,
                                   patient_last_name = :patient_last_name,
                                   patient_phone = :patient_phone,
                                   patient_email = :patient_email,
                                   payment_type = :payment_type
                                WHERE id = :id";
                
                error_log("Update query: " . $updateQuery);
                error_log("Update parameters:");
                error_log("  id: " . $existingAppointment['id']);
                error_log("  dentist_id: " . ($dentistId ?: 'NULL'));
                error_log("  service_id: " . $appointmentData['service_id']);
                error_log("  service_price: " . $servicePrice);
                error_log("  appointment_date: " . $appointmentData['appointment_date']);
                error_log("  appointment_time: " . $selectedTimeDb);
                
                $stmt = $this->conn->prepare($updateQuery);
                
                // Bind parameters
                $stmt->bindParam(':id', $existingAppointment['id']);
                $stmt->bindParam(':dentist_id', $dentistId);
                $stmt->bindParam(':service_id', $appointmentData['service_id']);
                $stmt->bindParam(':service_price', $servicePrice);
                $stmt->bindParam(':appointment_date', $appointmentData['appointment_date']);
                $stmt->bindParam(':appointment_time', $selectedTimeDb);
                $stmt->bindParam(':notes', $notes);
                $stmt->bindParam(':duration_minutes', $durationMinutes);
                $stmt->bindParam(':patient_first_name', $appointmentData['patient_first_name']);
                $stmt->bindParam(':patient_last_name', $appointmentData['patient_last_name']);
                $stmt->bindParam(':patient_phone', $appointmentData['patient_phone']);
                $stmt->bindParam(':patient_email', $appointmentData['patient_email']);
                $stmt->bindParam(':payment_type', $appointmentData['payment_type']);
                
                if ($stmt->execute()) {
                    $rowCount = $stmt->rowCount();
                    error_log("Update successful! Rows affected: " . $rowCount);
                    
                    // Log the reschedule activity
                    $oldDate = $existingAppointment['appointment_date'];
                    $oldTime = date('g:i A', strtotime($existingAppointment['appointment_time']));
                    $newDate = $appointmentData['appointment_date'];
                    $newTime = $selectedTimeDisplay;
                    
                    error_log("Appointment {$originalAppointmentId} rescheduled from {$oldDate} {$oldTime} to {$newDate} {$newTime}");
                    
                    $message = "Appointment rescheduled successfully! Your appointment ID: {$originalAppointmentId}.";
                    
                    return [
                        'success' => true, 
                        'message' => $message,
                        'appointment_id' => $originalAppointmentId,
                        'appointment_db_id' => $existingAppointment['id'],
                        'service_price' => $servicePrice,
                        'service_name' => $serviceName,
                        'client_id' => $clientId,
                        'is_reschedule' => true,
                        'original_appointment_id' => $originalAppointmentId
                    ];
                } else {
                    $errorInfo = $stmt->errorInfo();
                    error_log("Appointment update FAILED: " . print_r($errorInfo, true));
                    return ['success' => false, 'message' => "Failed to reschedule appointment. Please try again."];
                }
            } 
            // If NOT rescheduling, INSERT new appointment
            else {
                error_log("=== PROCESSING NEW APPOINTMENT ===");
                
                // Generate unique appointment ID using the appointment date
                $appointmentCode = $this->generateAppointmentId($appointmentData['appointment_date']);
                error_log("New appointment ID generated: " . $appointmentCode);
                
                // Insert new appointment
                $query = "INSERT INTO appointments 
                          (appointment_id, client_id, dentist_id, service_id, service_price,
                           patient_first_name, patient_last_name, patient_phone, 
                           patient_email, appointment_date, appointment_time, 
                           client_notes, payment_type, status, duration_minutes, created_at) 
                         VALUES 
                         (:appointment_id, :client_id, :dentist_id, :service_id, :service_price,
                          :patient_first_name, :patient_last_name, :patient_phone, 
                          :patient_email, :appointment_date, :appointment_time, 
                          :notes, :payment_type, 'pending', :duration_minutes, NOW())";
                
                error_log("Insert query for new appointment");
                
                $stmt = $this->conn->prepare($query);
                
                // Bind parameters
                $stmt->bindParam(':appointment_id', $appointmentCode);
                $stmt->bindParam(':client_id', $clientId);
                $stmt->bindParam(':dentist_id', $dentistId);
                $stmt->bindParam(':service_id', $appointmentData['service_id']);
                $stmt->bindParam(':service_price', $servicePrice);
                $stmt->bindParam(':patient_first_name', $appointmentData['patient_first_name']);
                $stmt->bindParam(':patient_last_name', $appointmentData['patient_last_name']);
                $stmt->bindParam(':patient_phone', $appointmentData['patient_phone']);
                $stmt->bindParam(':patient_email', $appointmentData['patient_email']);
                $stmt->bindParam(':appointment_date', $appointmentData['appointment_date']);
                $stmt->bindParam(':appointment_time', $selectedTimeDb);
                $stmt->bindParam(':notes', $notes);
                $stmt->bindParam(':duration_minutes', $durationMinutes);
                $stmt->bindParam(':payment_type', $appointmentData['payment_type']);
                
                if ($stmt->execute()) {
                    $appointmentDbId = $this->conn->lastInsertId();
                    error_log("New appointment inserted successfully! ID: " . $appointmentDbId);
                    
                    $message = "Appointment booked successfully! Your appointment ID is {$appointmentCode}.";
                    
                    return [
                        'success' => true, 
                        'message' => $message,
                        'appointment_id' => $appointmentCode,
                        'appointment_db_id' => $appointmentDbId,
                        'service_price' => $servicePrice,
                        'service_name' => $serviceName,
                        'client_id' => $clientId,
                        'is_reschedule' => false,
                        'original_appointment_id' => null
                    ];
                } else {
                    $errorInfo = $stmt->errorInfo();
                    error_log("Appointment insertion failed: " . print_r($errorInfo, true));
                    return ['success' => false, 'message' => "Failed to book appointment. Please try again."];
                }
            }
            
        } catch (PDOException $e) {
            error_log("Appointment booking PDO error: " . $e->getMessage());
            error_log("Appointment data: " . print_r($appointmentData, true));
            return ['success' => false, 'message' => "Database error occurred. Please try again."];
        } catch (Exception $e) {
            error_log("Appointment booking general error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function generateAppointmentId($appointmentDate) {
        try {
            $prefix = "CSD";
            $dateCode = date('Ymd', strtotime($appointmentDate));
            
            $query = "SELECT MAX(appointment_id) as last_id FROM appointments 
                     WHERE appointment_id LIKE :pattern";
            
            $pattern = $prefix . $dateCode . '%';
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':pattern', $pattern);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $sequence = 1;
            if ($result && !empty($result['last_id'])) {
                $lastId = $result['last_id'];
                $datePart = substr($lastId, 3, 8);
                if ($datePart === $dateCode) {
                    $lastSequence = intval(substr($lastId, -3));
                    $sequence = $lastSequence + 1;
                }
            }
            
            $appointmentId = $prefix . $dateCode . str_pad($sequence, 3, '0', STR_PAD_LEFT);
            
            return $appointmentId;
            
        } catch (PDOException $e) {
            error_log("Error generating appointment ID: " . $e->getMessage());
            return $prefix . $dateCode . date('His');
        }
    }

    public function getAllDentists() {
        try {
            $query = "SELECT id, CONCAT('Dr. ', first_name, ' ', last_name) as name, specialization 
                      FROM dentists 
                      WHERE is_active = 1 AND is_checked_in = 1
                      ORDER BY first_name, last_name";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching dentists: " . $e->getMessage());
            return [];
        }
    }

    public function getAllServices() {
        try {
            $query = "SELECT id, name, description, duration_minutes, price 
                      FROM services 
                      WHERE is_active = 1 
                      ORDER BY name";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching services: " . $e->getMessage());
            return [];
        }
    }

    public function checkAvailability($dentistId, $date) {
        try {
            $timestamp = strtotime($date);
            $dayOfWeek = date('w', $timestamp); // 0 (Sunday) to 6 (Saturday)
            
            if ($dayOfWeek == 0) {
                // Sunday closed
                $allTimeSlots = [];
            } else if ($dayOfWeek == 6) {
                // Saturday 9 AM to 3 PM
                $allTimeSlots = [
                    '09:00:00', '10:00:00', '11:00:00', '12:00:00', 
                    '13:00:00', '14:00:00', '15:00:00'
                ];
            } else {
                // Mon-Fri 8 AM to 6 PM
                $allTimeSlots = [
                    '08:00:00', '09:00:00', '10:00:00', '11:00:00',
                    '12:00:00', '13:00:00', '14:00:00', '15:00:00', 
                    '16:00:00', '17:00:00', '18:00:00'
                ];
            }
            
            $currentDateTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
            $currentDate = $currentDateTime->format('Y-m-d');
            
            // Query to get booked appointments
            $query = "SELECT appointment_time FROM appointments 
                      WHERE appointment_date = :appointment_date 
                      AND status IN ('pending', 'confirmed')";
            
            $params = [':appointment_date' => $date];
            
            if ($dentistId && $dentistId !== 'dr-any') {
                $query .= " AND dentist_id = :dentist_id";
                $params[':dentist_id'] = $dentistId;
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $bookedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Convert booked slots to time strings
            $bookedTimes = [];
            foreach ($bookedSlots as $slot) {
                $bookedTimes[] = date('H:i:s', strtotime($slot));
            }
            
            $availableSlots = [];
            $isToday = ($date === $currentDate);
            
            foreach ($allTimeSlots as $slot) {
                $slotTime = strtotime($slot);
                $slotDateTime = date('H:i:s', $slotTime);
                
                $isBooked = in_array($slotDateTime, $bookedTimes);
                
                $isPast = false;
                if ($isToday) {
                    $slotDateTimeObj = DateTime::createFromFormat('H:i:s', $slotDateTime, new DateTimeZone('Asia/Manila'));
                    if ($slotDateTimeObj < $currentDateTime) {
                        $isPast = true;
                    }
                }
                
                if (!$isBooked && !$isPast) {
                    $availableSlots[] = date('g:i A', $slotTime);
                }
            }
            
            $isPastDate = $date < $currentDate;
            $isFullyBooked = !$isPastDate && count($availableSlots) === 0;
            
            return [
                'available_slots' => $availableSlots,
                'is_fully_booked' => $isFullyBooked,
                'total_slots' => count($allTimeSlots),
                'booked_slots' => count($bookedSlots),
                'is_past_date' => $isPastDate,
                'is_today' => $isToday,
                'current_time' => $currentDateTime->format('H:i:s')
            ];
        } catch (PDOException $e) {
            error_log("Error checking availability: " . $e->getMessage());
            return [
                'available_slots' => [],
                'is_fully_booked' => true,
                'total_slots' => 11,
                'booked_slots' => 11,
                'is_past_date' => false,
                'is_today' => false,
                'current_time' => '00:00:00'
            ];
        }
    }

    public function getMonthlyAvailability($year, $month, $dentistId = null) {
        try {
            $startDate = date('Y-m-01', strtotime("$year-$month-01"));
            $endDate = date('Y-m-t', strtotime("$year-$month-01"));
            $currentDateTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
            $currentDate = $currentDateTime->format('Y-m-d');
            
            $availability = [];
            
            $currentDateObj = new DateTime($startDate);
            $endDateTime = new DateTime($endDate);
            
            while ($currentDateObj <= $endDateTime) {
                $dateString = $currentDateObj->format('Y-m-d');
                $dateAvailability = $this->checkAvailability($dentistId, $dateString);
                
                $availability[$dateString] = [
                    'is_fully_booked' => $dateAvailability['is_fully_booked'],
                    'available_slots' => $dateAvailability['available_slots'],
                    'booked_slots' => $dateAvailability['booked_slots'],
                    'total_slots' => $dateAvailability['total_slots'],
                    'is_past_date' => $dateAvailability['is_past_date'],
                    'is_today' => $dateAvailability['is_today']
                ];
                
                $currentDateObj->modify('+1 day');
            }
            
            return ['availability' => $availability];
            
        } catch (PDOException $e) {
            error_log("Error getting monthly availability: " . $e->getMessage());
            return ['availability' => []];
        }
    }

    public function getServicePrice($serviceId) {
        try {
            $query = "SELECT price, name FROM services WHERE id = :service_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':service_id', $serviceId);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting service price: " . $e->getMessage());
            return ['price' => 0, 'name' => ''];
        }
    }

    public function resolveServiceDetails($serviceIdsCsv) {
        if (empty($serviceIdsCsv)) return ['name' => 'Dental Service', 'duration' => 30, 'price' => 0];
        
        $ids = is_array($serviceIdsCsv) ? $serviceIdsCsv : array_filter(array_map('trim', explode(',', $serviceIdsCsv)));
        if (empty($ids)) return ['name' => 'Dental Service', 'duration' => 30, 'price' => 0];
        
        try {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $query = "SELECT name, duration_minutes, price FROM services WHERE id IN ($placeholders)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(array_values($ids));
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($services)) return ['name' => 'Dental Service', 'duration' => 30, 'price' => 0];
            
            $names = array_column($services, 'name');
            $totalDuration = array_sum(array_column($services, 'duration_minutes'));
            $totalPrice = array_sum(array_column($services, 'price'));
            
            return [
                'name' => implode(', ', $names),
                'duration' => $totalDuration ?: 30,
                'price' => $totalPrice
            ];
        } catch (PDOException $e) {
            error_log("Error resolving service details: " . $e->getMessage());
            return ['name' => 'Dental Service', 'duration' => 30, 'price' => 0];
        }
    }

    public function resolveServiceNames($serviceIdsCsv) {
        $details = $this->resolveServiceDetails($serviceIdsCsv);
        return $details['name'];
    }

    public function getClientAppointments($clientId, $month = null, $year = null) {
        try {
            error_log("=== getClientAppointments() called ===");
            error_log("Client ID parameter: " . ($clientId ?: 'NULL'));
            error_log("Month: " . ($month ?: 'current month'));
            error_log("Year: " . ($year ?: 'current year'));
            
            $query = "SELECT a.*, 
                             CONCAT('Dr. ', d.first_name, ' ', d.last_name) as dentist_name,
                             d.specialization as dentist_specialization,
                             f.id as feedback_id
                      FROM appointments a
                      LEFT JOIN dentists d ON a.dentist_id = d.id
                      LEFT JOIN appointment_feedbacks f ON a.appointment_id = f.appointment_id
                      WHERE (a.client_id = :client_id OR (a.client_id REGEXP '^[0-9]+$' AND a.client_id = :numeric_id))";
            
            // Extract numeric ID if possible
            $numericId = 0;
            if (preg_match('/PAT0*(\d+)/', $clientId, $matches)) {
                $numericId = $matches[1];
            } else if (is_numeric($clientId)) {
                $numericId = $clientId;
            }

            $params = [
                ':client_id' => $clientId,
                ':numeric_id' => $numericId
            ];
            
            if ($month && $year) {
                $query .= " AND MONTH(a.appointment_date) = :month AND YEAR(a.appointment_date) = :year";
                $params[':month'] = $month;
                $params[':year'] = $year;
            }
            
            $query .= " ORDER BY a.created_at DESC, a.id DESC";
            
            error_log("SQL Query: " . $query);
            error_log("SQL Parameters: " . print_r($params, true));
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Raw appointments fetched: " . count($appointments));
            if (count($appointments) > 0) {
                error_log("First appointment: " . print_r($appointments[0], true));
            }
            
            $formattedAppointments = [];
            foreach ($appointments as $appointment) {
                $date = $appointment['appointment_date'];
                if (!isset($formattedAppointments[$date])) {
                    $formattedAppointments[$date] = [];
                }
                
                $details = $this->resolveServiceDetails($appointment['service_id']);
                $serviceName = $details['name'];
                
                $formattedAppointments[$date][] = [
                    'id' => $appointment['id'],
                    'appointment_id' => $appointment['appointment_id'],
                    'appointment_date' => $appointment['appointment_date'],
                    'appointment_time' => $appointment['appointment_time'],
                    'time' => date('g:i A', strtotime($appointment['appointment_time'])), // Added for original JS compatibility with AM/PM
                    'status' => $appointment['status'],
                    'service' => $serviceName,
                    'service_name' => $serviceName,
                    'service_id' => $appointment['service_id'],
                    'dentist' => $appointment['dentist_name'],
                    'dentist_name' => $appointment['dentist_name'],
                    'dentist_id' => $appointment['dentist_id'],
                    'notes' => $appointment['client_notes'] ?: 'No additional notes',
                    'payment_type' => $appointment['payment_type'],
                    'service_price' => $appointment['service_price'], // Keep original for reference, or use $details['price']
                    'duration' => $details['duration'] . ' mins', // Original JS expects localized string
                    'duration_minutes' => $details['duration'],
                    'has_feedback' => !empty($appointment['feedback_id']),
                    'patient_first_name' => $appointment['patient_first_name'] ?? 'Guest',
                    'patient_last_name' => $appointment['patient_last_name'] ?? 'Patient',
                    'patient_phone' => $appointment['patient_phone'],
                    'patient_email' => $appointment['patient_email']
                ];
            }
            
            error_log("Formatted appointments: " . count($formattedAppointments) . " dates");
            return $formattedAppointments;
            
        } catch (PDOException $e) {
            error_log("Error fetching client appointments: " . $e->getMessage());
            error_log("Error details: " . print_r($this->conn->errorInfo(), true));
            return [];
        }
    }

    public function getAppointmentHistory($clientId, $page = 1, $perPage = 5) {
        try {
            error_log("=== getAppointmentHistory() called ===");
            error_log("Client ID: " . ($clientId ?: 'NULL'));
            error_log("Page: $page, Per Page: $perPage");
            
            $offset = ($page - 1) * $perPage;
            
            $countQuery = "SELECT COUNT(*) as total 
                          FROM appointments a
                          WHERE (a.client_id = :client_id OR (a.client_id REGEXP '^[0-9]+$' AND a.client_id = :numeric_id))";
            
            // Extract numeric ID if possible
            $numericId = 0;
            if (preg_match('/PAT0*(\d+)/', $clientId, $matches)) {
                $numericId = $matches[1];
            } else if (is_numeric($clientId)) {
                $numericId = $clientId;
            }

            $countStmt = $this->conn->prepare($countQuery);
            $countStmt->bindParam(':client_id', $clientId);
            $countStmt->bindParam(':numeric_id', $numericId, PDO::PARAM_INT);
            $countStmt->execute();
            $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $totalRecords = $totalResult['total'] ?? 0;
            $totalPages = ceil($totalRecords / $perPage);
            
            error_log("Total records found: " . $totalRecords);
            
            $query = "SELECT a.*, 
                             CONCAT('Dr. ', d.first_name, ' ', d.last_name) as dentist_name,
                             d.specialization as dentist_specialization,
                             f.id as feedback_id
                      FROM appointments a
                      LEFT JOIN dentists d ON a.dentist_id = d.id
                      LEFT JOIN appointment_feedbacks f ON a.appointment_id = f.appointment_id
                      WHERE (a.client_id = :client_id OR (a.client_id REGEXP '^[0-9]+$' AND a.client_id = :numeric_id))
                      ORDER BY a.created_at DESC, a.id DESC
                      LIMIT :limit OFFSET :offset";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':client_id', $clientId);
            $stmt->bindParam(':numeric_id', $numericId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("History appointments fetched: " . count($appointments));
            
            $history = [];
            foreach ($appointments as $appointment) {
                $details = $this->resolveServiceDetails($appointment['service_id']);
                $serviceName = $details['name'];
                $history[] = [
                    'id' => $appointment['id'],
                    'appointment_id' => $appointment['appointment_id'],
                    'date' => $appointment['appointment_date'],
                    'time' => date('g:i A', strtotime($appointment['appointment_time'])),
                    'notes' => $appointment['client_notes'] ?: 'No additional notes',
                    'service' => $serviceName,
                    'service_name' => $serviceName,
                    'service_price' => $appointment['service_price'],
                    'payment_type' => $appointment['payment_type'],
                    'status' => $appointment['status'],
                    'has_feedback' => !empty($appointment['feedback_id']),
                    'dentist' => $appointment['dentist_name'],
                    'dentist_name' => $appointment['dentist_name'],
                    'specialization' => $appointment['dentist_specialization'],
                    'patient_first_name' => $appointment['patient_first_name'],
                    'patient_last_name' => $appointment['patient_last_name'],
                    'patient_phone' => $appointment['patient_phone'],
                    'patient_email' => $appointment['patient_email'],
                    'duration_minutes' => $details['duration'],
                    'created_at' => $appointment['created_at'],
                    'updated_at' => $appointment['updated_at']
                ];
            }
            
            return [
                'history' => $history,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_records' => $totalRecords,
                    'total_pages' => $totalPages,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $totalPages
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Error fetching appointment history: " . $e->getMessage());
            return [
                'history' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total_records' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false
                ]
            ];
        }
    }

    public function canModifyAppointment($appointmentDate, $appointmentTime) {
        try {
            $appointmentDateTime = new DateTime($appointmentDate . ' ' . $appointmentTime);
            $now = new DateTime();
            
            $diff = $now->diff($appointmentDateTime);
            $hoursDifference = $diff->h + ($diff->days * 24);
            
            if ($hoursDifference < 48) {
                return [
                    'can_modify' => false,
                    'message' => 'Appointments cannot be cancelled or rescheduled within 48 hours of the appointment time. Please contact the clinic directly.'
                ];
            }
            
            return [
                'can_modify' => true,
                'message' => 'Appointment can be modified.'
            ];
            
        } catch (Exception $e) {
            error_log("Error checking appointment modification: " . $e->getMessage());
            return [
                'can_modify' => false,
                'message' => 'Error checking appointment modification eligibility'
            ];
        }
    }

    public function cancelAppointment($appointmentId, $clientId, $reason = null) {
        try {
            $verifyQuery = "SELECT id, status, appointment_date, appointment_time 
                           FROM appointments 
                           WHERE appointment_id = :appointment_id 
                           AND client_id = :client_id";
            
            $verifyStmt = $this->conn->prepare($verifyQuery);
            $verifyStmt->bindParam(':appointment_id', $appointmentId);
            $verifyStmt->bindParam(':client_id', $clientId);
            $verifyStmt->execute();
            
            $appointment = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointment) {
                return ['success' => false, 'message' => 'Appointment not found or access denied.'];
            }
            
            $appointmentDateTime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
            $now = new DateTime();
            
            if ($appointmentDateTime < $now) {
                return ['success' => false, 'message' => 'Cannot cancel past appointments.'];
            }
            
            $modificationCheck = $this->canModifyAppointment($appointment['appointment_date'], $appointment['appointment_time']);
            if (!$modificationCheck['can_modify']) {
                return ['success' => false, 'message' => $modificationCheck['message']];
            }
            
            $updateQuery = "UPDATE appointments 
                           SET status = 'cancelled', 
                               updated_at = NOW() 
                           WHERE appointment_id = :appointment_id";
            
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':appointment_id', $appointmentId);
            
            if ($updateStmt->execute()) {
                error_log("Appointment {$appointmentId} cancelled. Reason: " . ($reason ?: 'Not specified'));
                return ['success' => true, 'message' => 'Appointment cancelled successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to cancel appointment.'];
            }
            
        } catch (PDOException $e) {
            error_log("Error cancelling appointment: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred.'];
        }
    }

    public function getAppointmentById($appointmentId, $clientDbId) {
        try {
            // First get the client_id (varchar) from the database using the auto_increment id
            $clientQuery = "SELECT client_id FROM clients WHERE id = :id";
            $clientStmt = $this->conn->prepare($clientQuery);
            $clientStmt->bindParam(':id', $clientDbId);
            $clientStmt->execute();
            $clientData = $clientStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$clientData) {
                return ['success' => false, 'message' => 'Client not found.'];
            }
            
            $clientId = $clientData['client_id'];
            
            $query = "SELECT a.* 
                      FROM appointments a
                      WHERE a.appointment_id = :appointment_id 
                      AND (a.client_id = :client_id OR (a.client_id REGEXP '^[0-9]+$' AND a.client_id = :numeric_id))";
            
            // Extract numeric ID if possible
            $numericId = $clientDbId;

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':appointment_id', $appointmentId);
            $stmt->bindParam(':client_id', $clientId);
            $stmt->bindParam(':numeric_id', $numericId, PDO::PARAM_INT);
            $stmt->execute();
            
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($appointment) {
                return [
                    'success' => true,
                    'appointment' => [
                        'id' => $appointment['id'],
                        'appointment_id' => $appointment['appointment_id'],
                        'client_id' => $appointment['client_id'],
                        'patient_first_name' => $appointment['patient_first_name'],
                        'patient_last_name' => $appointment['patient_last_name'],
                        'patient_phone' => $appointment['patient_phone'],
                        'patient_email' => $appointment['patient_email'],
                        'service_id' => $appointment['service_id'],
                        'service_name' => $this->resolveServiceNames($appointment['service_id']),
                        'dentist_id' => $appointment['dentist_id'],
                        'dentist_name' => 'N/A',
                        'appointment_date' => $appointment['appointment_date'],
                        'appointment_time' => $appointment['appointment_time'],
                        'notes' => $appointment['client_notes'],
                        'payment_type' => $appointment['payment_type'],
                        'status' => $appointment['status'],
                        'service_price' => $appointment['service_price'],
                        'duration_minutes' => $appointment['duration_minutes']
                    ]
                ];
            } else {
                return ['success' => false, 'message' => 'Appointment not found.'];
            }
            
        } catch (PDOException $e) {
            error_log("Error fetching appointment: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred.'];
        }
    }

    public function canRescheduleAppointment($appointmentId, $clientId) {
        try {
            error_log("=== canRescheduleAppointment called ===");
            error_log("Appointment ID: " . $appointmentId);
            error_log("Client ID: " . ($clientId ?: 'NULL'));
            
            $query = "SELECT appointment_date, appointment_time, status 
                      FROM appointments 
                      WHERE appointment_id = :appointment_id 
                      AND client_id = :client_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':appointment_id', $appointmentId);
            $stmt->bindParam(':client_id', $clientId);
            $stmt->execute();
            
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointment) {
                error_log("ERROR: Appointment not found or doesn't belong to client");
                return [
                    'success' => false,
                    'message' => 'Appointment not found or access denied.'
                ];
            }
            
            error_log("Found appointment: " . print_r($appointment, true));
            
            $modificationCheck = $this->canModifyAppointment(
                $appointment['appointment_date'], 
                $appointment['appointment_time']
            );
            
            error_log("Modification check result: " . ($modificationCheck['can_modify'] ? 'YES' : 'NO'));
            
            return [
                'success' => true,
                'can_reschedule' => $modificationCheck['can_modify'],
                'message' => $modificationCheck['message'],
                'appointment_date' => $appointment['appointment_date'],
                'appointment_time' => $appointment['appointment_time'],
                'status' => $appointment['status']
            ];
            
        } catch (PDOException $e) {
            error_log("Error checking reschedule eligibility: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error checking reschedule eligibility.'
            ];
        }
    }

    // NEW METHOD: Get appointment details for rescheduling
    public function getRescheduleAppointmentDetails($appointmentId, $clientId) {
        try {
            error_log("=== getRescheduleAppointmentDetails called ===");
            error_log("Appointment ID: " . $appointmentId);
            error_log("Client ID: " . ($clientId ?: 'NULL'));
            
            $query = "SELECT a.* 
                      FROM appointments a
                      WHERE a.appointment_id = :appointment_id 
                      AND (a.client_id = :client_id OR (a.client_id REGEXP '^[0-9]+$' AND a.client_id = :numeric_id))";
            
            // Extract numeric ID if possible
            $numericId = 0;
            if (preg_match('/PAT0*(\d+)/', $clientId, $matches)) {
                $numericId = $matches[1];
            } else if (is_numeric($clientId)) {
                $numericId = $clientId;
            }

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':appointment_id', $appointmentId);
            $stmt->bindParam(':client_id', $clientId);
            $stmt->bindParam(':numeric_id', $numericId, PDO::PARAM_INT);
            $stmt->execute();
            
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointment) {
                error_log("ERROR: Appointment not found or doesn't belong to client");
                return [
                    'success' => false,
                    'message' => 'Appointment not found or access denied.'
                ];
            }
            
            error_log("Found appointment details: " . print_r($appointment, true));
            
            if ($appointment) {
                $appointment['service_name'] = $this->resolveServiceNames($appointment['service_id']);
                $appointment['time_display'] = date('g:i A', strtotime($appointment['appointment_time']));
            }
            
            return [
                'success' => true,
                'appointment' => [
                    'id' => $appointment['id'],
                    'appointment_id' => $appointment['appointment_id'],
                    'original_appointment_date' => $appointment['appointment_date'],
                    'original_appointment_time' => $appointment['appointment_time'],
                    'original_appointment_time_display' => $appointment['time_display'],
                    'patient_first_name' => $appointment['patient_first_name'],
                    'patient_last_name' => $appointment['patient_last_name'],
                    'service_id' => $appointment['service_id'],
                    'service_name' => $appointment['service_name'],
                    'dentist_id' => $appointment['dentist_id'],
                    'dentist_name' => 'N/A',
                    'notes' => $appointment['client_notes'],
                    'payment_type' => $appointment['payment_type'],
                    'status' => $appointment['status'],
                    'service_price' => $appointment['service_price'],
                    'duration_minutes' => $appointment['duration_minutes'] ?? 30
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Error fetching reschedule appointment details: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error fetching appointment details.'
            ];
        }
    }

    /**
     * Submit feedback for a completed appointment
     */
    public function submitFeedback($appointmentId, $clientId, $rating, $feedback) {
        try {
            // Verify appointment belongs to client and is completed
            $verifyQuery = "SELECT id, status FROM appointments 
                           WHERE appointment_id = :appointment_id AND client_id = :client_id";
            $verifyStmt = $this->conn->prepare($verifyQuery);
            $verifyStmt->bindParam(':appointment_id', $appointmentId);
            $verifyStmt->bindParam(':client_id', $clientId);
            $verifyStmt->execute();
            $appointment = $verifyStmt->fetch(PDO::FETCH_ASSOC);

            if (!$appointment) {
                return ['success' => false, 'message' => 'Appointment not found or access denied.'];
            }

            if ($appointment['status'] !== 'completed') {
                return ['success' => false, 'message' => 'Feedback can only be submitted for completed appointments.'];
            }

            // Check if feedback already exists
            $checkQuery = "SELECT id FROM appointment_feedbacks WHERE appointment_id = :appointment_id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':appointment_id', $appointmentId);
            $checkStmt->execute();
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Feedback has already been submitted for this appointment.'];
            }

            // Insert feedback
            $insertQuery = "INSERT INTO appointment_feedbacks (appointment_id, rating, feedback) 
                            VALUES (:appointment_id, :rating, :feedback)";
            $insertStmt = $this->conn->prepare($insertQuery);
            $insertStmt->bindParam(':appointment_id', $appointmentId);
            $insertStmt->bindParam(':rating', $rating, PDO::PARAM_INT);
            $insertStmt->bindParam(':feedback', $feedback);

            if ($insertStmt->execute()) {
                return ['success' => true, 'message' => 'Thank you for your feedback!'];
            } else {
                return ['success' => false, 'message' => 'Failed to submit feedback. Please try again.'];
            }

        } catch (PDOException $e) {
            error_log("Error submitting feedback: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred.'];
        }
    }

    /**
     * Get feedback for a specific appointment
     */
    public function getAppointmentFeedback($appointmentId) {
        try {
            $query = "SELECT rating, feedback, created_at FROM appointment_feedbacks WHERE appointment_id = :appointment_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':appointment_id', $appointmentId);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching feedback: " . $e->getMessage());
            return null;
        }
    }
}
