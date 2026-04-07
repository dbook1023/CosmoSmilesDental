<?php 
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Services/SessionService.php';

// Check for inactivity
SessionService::checkInactivity('admin');

// Ensure user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../admin-login.php");
    exit();
}

require_once __DIR__ . '/../../src/Controllers/AdminPatientController.php';

// Fetch logged-in admin user information
$adminUser = null;
$adminName = "Admin User";
$adminRole = "Administrator";
$adminId = $_SESSION['admin_id'] ?? null;

$adminPatientController = new AdminPatientController();

// Handle AJAX toggle action
if (isset($_GET['action']) && $_GET['action'] === 'toggle_checkin' && isset($_GET['status'])) {
    header('Content-Type: application/json');
    $status = $_GET['status'] === 'true';
    $result = $adminPatientController->toggleDentistCheckInStatus($adminId, $status);
    echo json_encode(['success' => (bool)$result, 'time' => $result['time'] ?? null]);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($adminId) {
        $adminQuery = "SELECT first_name, last_name, role, email FROM admin_users WHERE id = :id";
        $adminStmt = $conn->prepare($adminQuery);
        $adminStmt->execute([':id' => $adminId]);
        $adminUser = $adminStmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($adminUser) {
        $adminName = "Dr. " . $adminUser['first_name'] . ' ' . $adminUser['last_name'];
        $adminRole = (strtolower($adminUser['role']) === 'admin') ? 'Administrator' : ucfirst($adminUser['role']);
    }

    // Get current check-in status
    $checkInInfo = $adminPatientController->getDentistCheckInStatus($adminId);
    $isCheckedIn = $checkInInfo['status'];
    $checkedInTime = $checkInInfo['time'] ? date('g:i A', strtotime($checkInInfo['time'])) : null;

} catch (Exception $e) {
    error_log("Error fetching admin user: " . $e->getMessage());
}

// Determine interval from GET parameter
$period = $_GET['period'] ?? 'week';
$intervalDays = 30;
$periodText = 'last month';
switch($period) {
    case 'today': 
        $intervalDays = 1; 
        $periodText = 'yesterday';
        break;
    case 'week': 
        $intervalDays = 7; 
        $periodText = 'last week';
        break;
    case 'month': 
        $intervalDays = 30; 
        $periodText = 'last month';
        break;
    case 'quarter': 
        $intervalDays = 90; 
        $periodText = 'last quarter';
        break;
    case 'year': 
        $intervalDays = 365; 
        $periodText = 'last year';
        break;
}
$intervalDays2x = $intervalDays * 2;
$retentionDays = max(90, $intervalDays * 3);

// Fetch dashboard statistics
// Fetch dashboard statistics defaults
$stats = [
    'total_appointments' => 0,
    'total_patients' => 0,
    'patient_retention' => 0,
    'monthly_revenue' => 0
];

$appointmentTrends = [];
$revenueTrends = [];
$patientTrends = [];
$chartLabels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']; // Default labels
$demographicsData = [];
$treatmentData = ['labels' => [], 'data' => []];
$operationsData = [];

// Geographic/Gender results defaults
$genderLabels = [];
$genderData = [];
$locationLabels = [];
$locationData = [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get total appointments (last X days)
    $appointmentQuery = "SELECT COUNT(*) as total FROM appointments 
                         WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL $intervalDays DAY) 
                         AND status != 'cancelled'";
    $appointmentStmt = $conn->prepare($appointmentQuery);
    $appointmentStmt->execute();
    $stats['total_appointments'] = $appointmentStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get new patients in interval
    $patientQuery = "SELECT COUNT(*) as total FROM clients WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL $intervalDays DAY)";
    $patientStmt = $conn->prepare($patientQuery);
    $patientStmt->execute();
    $stats['total_patients'] = $patientStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get patient retention (returning patients)
    $retentionQuery = "SELECT 
                        COUNT(DISTINCT CASE WHEN appointment_count > 1 THEN client_id END) * 100.0 / 
                        COUNT(DISTINCT client_id) as retention_rate
                        FROM (
                            SELECT client_id, COUNT(*) as appointment_count 
                            FROM appointments 
                            WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL $retentionDays DAY)
                            GROUP BY client_id
                        ) as patient_counts";
    $retentionStmt = $conn->prepare($retentionQuery);
    $retentionStmt->execute();
    $stats['patient_retention'] = round($retentionStmt->fetch(PDO::FETCH_ASSOC)['retention_rate'] ?? 0, 1);
    
    // Get monthly revenue (based on service prices)
    // We fetch all relevant appointments and sum their total service prices in PHP for accuracy with CSV IDs
    $revenueQuery = "SELECT service_id FROM appointments 
                     WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL $intervalDays DAY)
                     AND status = 'completed'";
    $revenueStmt = $conn->prepare($revenueQuery);
    $revenueStmt->execute();
    $apps = $revenueStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalRevenue = 0;
    // Cache service prices
    $servicePrices = [];
    $allServices = $conn->query("SELECT id, name, price FROM services")->fetchAll(PDO::FETCH_ASSOC);
    foreach($allServices as $s) $servicePrices[$s['id']] = $s['price'];

    foreach($apps as $app) {
        $ids = explode(',', $app['service_id']);
        foreach($ids as $id) {
            $totalRevenue += ($servicePrices[trim($id)] ?? 0);
        }
    }
    $stats['monthly_revenue'] = (float)$totalRevenue;
    
    // Get combined trends for the selected interval
    // Dynamic aggregation based on period
    $chartLabels = [];
    $groupBy = '';
    $groupByClient = ''; // For queries on the clients table (uses created_at instead of appointment_date)
    
    switch($period) {
        case 'today':
            $chartLabels = ['8am', '12pm', '4pm', '8pm'];
            $groupBy = "CASE 
                            WHEN HOUR(appointment_time) < 12 THEN '8am'
                            WHEN HOUR(appointment_time) < 16 THEN '12pm'
                            WHEN HOUR(appointment_time) < 20 THEN '4pm'
                            ELSE '8pm' 
                        END";
            $groupByClient = "CASE 
                            WHEN HOUR(created_at) < 12 THEN '8am'
                            WHEN HOUR(created_at) < 16 THEN '12pm'
                            WHEN HOUR(created_at) < 20 THEN '4pm'
                            ELSE '8pm' 
                        END";
            break;
        case 'week':
            $chartLabels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $groupBy = "DAYNAME(appointment_date)";
            $groupByClient = "DAYNAME(created_at)";
            break;
        case 'month':
            $chartLabels = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'];
            $groupBy = "CONCAT('Week ', FLOOR((DAYOFMONTH(appointment_date)-1)/7) + 1)";
            $groupByClient = "CONCAT('Week ', FLOOR((DAYOFMONTH(created_at)-1)/7) + 1)";
            break;
        case 'quarter':
        case 'year':
            $chartLabels = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            $groupBy = "MONTHNAME(appointment_date)";
            $groupByClient = "MONTHNAME(created_at)";
            break;
        default:
            $chartLabels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $groupBy = "DAYNAME(appointment_date)";
            $groupByClient = "DAYNAME(created_at)";
            break;
    }

    $trendQuery = "SELECT 
                    a.id,
                    a.service_id,
                    $groupBy as label
                   FROM appointments a
                   WHERE a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL $intervalDays DAY)
                   AND a.status = 'completed'";
    $trendStmt = $conn->prepare($trendQuery);
    $trendStmt->execute();
    $trendResults = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize stats containers
    $appointmentTrends = array_fill_keys($chartLabels, 0);
    $revenueTrends = array_fill_keys($chartLabels, 0);
    
    foreach ($trendResults as $row) {
        $label = $row['label'];
        if (isset($appointmentTrends[$label])) {
            $appointmentTrends[$label]++;
            
            $ids = explode(',', $row['service_id']);
            foreach($ids as $id) {
                $revenueTrends[$label] += ($servicePrices[trim($id)] ?? 0);
            }
        }
    }
    
    // Get patient acquisition trends
    $patientTrendQuery = "SELECT 
                    $groupByClient as label,
                    COUNT(id) as count
                   FROM clients
                   WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL $intervalDays DAY)
                   GROUP BY label";
    $patientTrendStmt = $conn->prepare($patientTrendQuery);
    $patientTrendStmt->execute();
    $patientTrendResults = $patientTrendStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $patientTrends = array_fill_keys($chartLabels, 0);
    foreach ($patientTrendResults as $row) {
        if (isset($patientTrends[$row['label']])) {
            $patientTrends[$row['label']] = $row['count'];
        }
    }
    
    // Fetch Recent Appointments
    $recentAppointments = [];
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Filter by logged-in admin (unless it's the master admin ID 1)
    if ($adminId && $adminId != 1) {
        $whereClause .= " AND a.dentist_id = :dentist_id";
        $params[':dentist_id'] = $adminId;
    }

    $recentQuery = "
        SELECT 
            a.*, 
            u.first_name as dentist_fname,
            u.last_name as dentist_lname
        FROM appointments a
        LEFT JOIN admin_users u ON a.dentist_id = u.id
        $whereClause
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5
    ";
    $recentStmt = $conn->prepare($recentQuery);
    $recentStmt->execute($params);
    $recentAppointmentsRaw = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Resolve service names for each recent appointment
    $allServiceNames = [];
    $servicesRaw = $conn->query("SELECT id, name FROM services")->fetchAll(PDO::FETCH_ASSOC);
    foreach($servicesRaw as $s) $allServiceNames[$s['id']] = $s['name'];

    foreach($recentAppointmentsRaw as $app) {
        $ids = array_filter(array_map('trim', explode(',', $app['service_id'] ?? '')));
        $names = [];
        foreach($ids as $id) if(isset($allServiceNames[$id])) $names[] = $allServiceNames[$id];
        $app['service_name'] = !empty($names) ? implode(', ', $names) : 'Dental Service';
        $recentAppointments[] = $app;
    }

    // Get demographics by age group
    $ageGroups = [
        '0-12 Years' => 0,
        '13-19 Years' => 0,
        '20-39 Years' => 0,
        '40-59 Years' => 0,
        '60+ Years' => 0
    ];
    
    $ageQuery = "SELECT 
                    TIMESTAMPDIFF(YEAR, c.birthdate, CURDATE()) as age
                 FROM clients c
                 WHERE c.birthdate IS NOT NULL";
    $ageStmt = $conn->prepare($ageQuery);
    $ageStmt->execute();
    $ages = $ageStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($ages as $ageRow) {
        $age = $ageRow['age'];
        if ($age <= 12) $ageGroups['0-12 Years']++;
        elseif ($age <= 19) $ageGroups['13-19 Years']++;
        elseif ($age <= 39) $ageGroups['20-39 Years']++;
        elseif ($age <= 59) $ageGroups['40-59 Years']++;
        else $ageGroups['60+ Years']++;
    }
    
    $demographicsData = array_values($ageGroups);
    
    // Get demographics by gender
    $genderQuery = "SELECT gender, COUNT(*) as count 
                    FROM clients 
                    WHERE gender IN ('male', 'female', 'other') 
                    GROUP BY gender";
    $genderStmt = $conn->prepare($genderQuery);
    $genderStmt->execute();
    $genders = $genderStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $genderGroups = ['Male' => 0, 'Female' => 0, 'Other' => 0];
    foreach ($genders as $gRow) {
        $g = ucfirst($gRow['gender']);
        if (isset($genderGroups[$g])) {
            $genderGroups[$g] = $gRow['count'];
        }
    }
    
    $genderLabels = array_keys($genderGroups);
    $genderData = array_values($genderGroups);

    // Get demographics by location (city)
    $locationQuery = "SELECT city, COUNT(*) as count 
                      FROM clients 
                      WHERE city IS NOT NULL AND city != '' 
                      GROUP BY city ORDER BY count DESC LIMIT 5";
    $locationStmt = $conn->prepare($locationQuery);
    $locationStmt->execute();
    $locations = $locationStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $locationLabels = [];
    $locationData = [];
    foreach ($locations as $lRow) {
        $locationLabels[] = $lRow['city'];
        $locationData[] = $lRow['count'];
    }
    
    // Get treatment distribution (CSV aware)
    $treatmentQuery = "SELECT service_id FROM appointments 
                       WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL $intervalDays DAY)";
    $tStmt = $conn->prepare($treatmentQuery);
    $tStmt->execute();
    $tApps = $tStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $counts = [];
    foreach($tApps as $app) {
        $ids = array_filter(array_map('trim', explode(',', $app['service_id'] ?? '')));
        foreach($ids as $id) {
            if(isset($allServiceNames[$id])) {
                $name = $allServiceNames[$id];
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }
    }
    arsort($counts);
    $treatmentResults = array_slice($counts, 0, 6, true);
    
    // Fallback labels if no treatments found in period
    if (empty($treatmentResults)) {
        $treatmentResults = ['No Procedures' => 0];
    }
    
    $treatmentLabels = array_keys($treatmentResults);
    $treatmentCounts = array_values($treatmentResults);
    $treatmentData = ['labels' => $treatmentLabels, 'data' => $treatmentCounts];
    
    // Get operations data (efficiency metrics)
    $operationsQuery = "SELECT 
                        AVG(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100 as completion_rate,
                        AVG(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) * 100 as no_show_rate
                       FROM appointments
                       WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL $intervalDays DAY)";
    $operationsStmt = $conn->prepare($operationsQuery);
    $operationsStmt->execute();
    $opsResults = $operationsStmt->fetch(PDO::FETCH_ASSOC);
    
    $operationsData = [
        'efficiency' => round($opsResults['completion_rate'] ?? 85, 1),
        'no_show_rate' => round($opsResults['no_show_rate'] ?? 5, 1),
        'utilization' => 78, // Default value, can be calculated based on available slots
        'retention' => $stats['patient_retention'],
        'revenue' => $stats['monthly_revenue']
    ];
    
    // Get top performing services for table (CSV aware)
    $serviceStats = [];
    
    // Initialize service stats from all active services
    foreach ($allServices as $s) {
        $serviceStats[$s['id']] = [
            'name' => $s['name'],
            'price' => (float)$s['price'],
            'appointment_count' => 0,
            'completed_count' => 0,
            'revenue' => 0
        ];
    }
    
    // Fetch all appointments for the interval
    $periodAptsQuery = "SELECT service_id, status FROM appointments 
                       WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL $intervalDays DAY)";
    $periodAptsStmt = $conn->prepare($periodAptsQuery);
    $periodAptsStmt->execute();
    $periodApts = $periodAptsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($periodApts as $apt) {
        $ids = array_filter(array_map('trim', explode(',', $apt['service_id'] ?? '')));
        foreach ($ids as $id) {
            if (isset($serviceStats[$id])) {
                $serviceStats[$id]['appointment_count']++;
                if ($apt['status'] === 'completed') {
                    $serviceStats[$id]['completed_count']++;
                    $serviceStats[$id]['revenue'] += $serviceStats[$id]['price'];
                }
            }
        }
    }
    
    // Calculate completion rates and format for output
    $topServicesRaw = [];
    foreach ($serviceStats as $s) {
        if ($s['appointment_count'] > 0) {
            $s['completion_rate'] = ($s['completed_count'] / $s['appointment_count']) * 100;
            $s['service_name'] = $s['name']; // Match table column key
            $topServicesRaw[] = $s;
        }
    }
    
    // Sort by appointment count descending
    usort($topServicesRaw, function($a, $b) {
        return $b['appointment_count'] <=> $a['appointment_count'];
    });
    
    $topServices = array_slice($topServicesRaw, 0, 5);
    
} catch (Exception $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
}

// Get previous period stats for comparison (last month)
try {
    $prevMonthQuery = "SELECT COUNT(*) as total FROM appointments 
                       WHERE appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL $intervalDays2x DAY) 
                       AND DATE_SUB(CURDATE(), INTERVAL $intervalDays DAY)
                       AND status != 'cancelled'";
    $prevStmt = $conn->prepare($prevMonthQuery);
    $prevStmt->execute();
    $prevAppointments = $prevStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    if ($prevAppointments == 0) {
        $appointmentChange = $stats['total_appointments'] > 0 ? 100 : 0;
    } else {
        $appointmentChange = round(($stats['total_appointments'] - $prevAppointments) / $prevAppointments * 100, 1);
    }
    
    $prevPatientsQuery = "SELECT COUNT(*) as total FROM clients 
                          WHERE created_at BETWEEN DATE_SUB(CURDATE(), INTERVAL $intervalDays2x DAY) 
                          AND DATE_SUB(CURDATE(), INTERVAL $intervalDays DAY)";
    $prevPatientStmt = $conn->prepare($prevPatientsQuery);
    $prevPatientStmt->execute();
    $prevPatients = $prevPatientStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    if ($prevPatients == 0) {
        $patientChange = $stats['total_patients'] > 0 ? 100 : 0;
    } else {
        $patientChange = round(($stats['total_patients'] - $prevPatients) / $prevPatients * 100, 1);
    }
        
} catch (Exception $e) {
    $appointmentChange = 0;
    $patientChange = 0;
}


// Sidebar variables
$currentPage = 'dashboard';
$sidebarAdminName = $adminName;
$sidebarAdminRole = $adminRole;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cosmo Smiles Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php  include 'includes/admin-sidebar-css.php'; ?>
    <style>
        :root {
            --chart-blue: #4a6bff;
            --chart-green: #36d399;
            --chart-orange: #ff9f40;
            --chart-purple: #9966ff;
        }

        .availability-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.95);
            padding: 8px 16px;
            border-radius: 50px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-right: 15px;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 24px;
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
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #2ecc71;
        }

        input:checked + .slider:before {
            transform: translateX(22px);
        }

        #checkin-status-text {
            font-weight: 600;
            font-size: 0.9rem;
        }

        #checkin-time-text {
            font-size: 0.8rem;
            color: #7f8c8d;
            font-style: italic;
        }

        .text-success { color: #27ae60; }
        .text-danger { color: #e74c3c; }

        /* Dashboard Styles */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header-content h1 {
            font-family: "Inter", sans-serif;
            color: var(--primary);
            font-size: 2.2rem;
            margin-bottom: 5px;
        }

        .header-content p {
            color: var(--dark);
            opacity: 0.8;
            margin: 0;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .date-display {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--dark);
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

        /* Filter Controls */
        .filter-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-label {
            font-size: 0.9rem;
            color: var(--dark);
            font-weight: 600;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: white;
            color: var(--dark);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-select:hover {
            border-color: var(--accent);
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(13, 91, 185, 0.1);
        }

        .btn {
            padding: 8px 16px;
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background: var(--primary);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: var(--light-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary);
            font-size: 1.5rem;
        }

        .stat-content h3 {
            font-size: 0.9rem;
            color: var(--dark);
            opacity: 0.8;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-change {
            font-size: 0.8rem;
            font-weight: 600;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
            color: var(--error);
        }

        /* Analytics Grid */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .analytics-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            height: 100%;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--primary);
        }

        .card-header .view-all {
            color: var(--secondary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .card-header .view-all:hover {
            text-decoration: underline;
        }

        .time-period {
            display: flex;
            gap: 5px;
            background: var(--light-accent);
            padding: 4px;
            border-radius: 6px;
        }

        .period-btn {
            padding: 4px 12px;
            border: none;
            background: transparent;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .period-btn:hover {
            background: rgba(255, 255, 255, 0.7);
        }

        .period-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .card-content {
            padding: 25px;
            height: calc(100% - 70px);
        }

        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }

        /* Data Tables - COMPACT SIZE */
        .data-table-container {
            width: 100%;
            overflow-x: auto;
            margin: 0;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .data-table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .data-table th {
            text-align: left;
            padding: 12px 15px;
            border-bottom: 2px solid var(--border);
            color: var(--primary);
            font-weight: 600;
            white-space: nowrap;
            background-color: var(--light-accent);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table tbody tr:hover {
            background: rgba(108, 168, 240, 0.08);
        }

        .trend-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .trend-up {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .trend-down {
            background: rgba(220, 53, 69, 0.1);
            color: var(--error);
        }

        .trend-neutral {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* System Messages */
        .system-message {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
        }

        .system-message .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .system-message .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .system-message .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .system-message .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .system-message .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-sidebar {
                width: 250px;
            }
            
            .admin-main {
                margin-left: 250px;
            }
        }

        @media (max-width: 992px) {
            .hamburger {
                display: block;
                z-index: 1100;
                position: absolute;
                right: 20px;
                top: 50%;
                transform: translateY(-50%);
            }
            
            .admin-sidebar {
                transform: translateX(-100%);
                z-index: 1080;
            }
            
            .admin-sidebar.active {
                transform: translateX(0);
            }
            
            .admin-main {
                margin-left: 0;
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            
            .filter-controls {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-group {
                width: 100%;
                justify-content: space-between;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-main {
                padding: 20px;
            }
            
            .analytics-card {
                margin-bottom: 20px;
            }
            
            .data-table {
                min-width: 500px;
            }
        }

        @media (max-width: 576px) {
            .admin-main {
                padding: 15px;
            }
            
            .card-header {
                padding: 15px 20px;
            }
            
            .card-content {
                padding: 20px;
            }
            
            .time-period {
                flex-wrap: wrap;
            }
            
            .period-btn {
                flex: 1;
                min-width: 60px;
            }
            
            .header-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .date-display {
                order: 1;
            }
            
            .data-table {
                font-size: 0.8rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 10px 12px;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card, .analytics-card {
            animation: fadeIn 0.6s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* Loading State */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        /* Responsive Layouts */
        @media (max-width: 1200px) {
            .charts-grid, .content-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .header-actions {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }
            .header-actions .date-display, .header-actions .availability-toggle {
                width: 100%;
                justify-content: center;
                margin-right: 0;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group {
                width: 100%;
                justify-content: space-between;
            }
            .filter-select {
                flex: 1;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            .data-table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Header -->
    <?php  include 'includes/admin-header.php'; ?>

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
                    <h1>Analytics Dashboard</h1>
                    <p>Welcome back, <?php  echo htmlspecialchars($adminName); ?>! View and analyze clinic performance data.</p>
                </div>
                <div class="header-actions">
                    <div class="availability-toggle">
                        <label class="switch">
                            <input type="checkbox" id="checkin-toggle" <?php  echo $isCheckedIn ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <span id="checkin-status-text" class="<?php  echo $isCheckedIn ? 'text-success' : 'text-danger'; ?>">
                            <?php  echo $isCheckedIn ? 'Checked In' : 'Checked Out'; ?>
                        </span>
                        <span id="checkin-time-text">
                            <?php  echo $isCheckedIn && $checkedInTime ? 'at ' . $checkedInTime : ''; ?>
                        </span>
                    </div>
                    <div class="date-display">
                        <i class="fas fa-calendar-alt" style="font-size: 1.2rem; color: var(--secondary);"></i>
                        <div class="clock-content">
                            <span id="admin-date">Loading...</span>
                            <span id="admin-time">00:00:00 AM</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="filter-controls">
                <div class="filter-group">
                    <span class="filter-label">Time Period:</span>
                    <select class="filter-select" id="time-period">
                        <option value="today" <?php  echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php  echo $period === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php  echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="quarter" <?php  echo $period === 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                        <option value="year" <?php  echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                    </select>
                </div>
                
                <button class="btn" id="apply-filters">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card" data-metric="appointments">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Appointments</h3>
                        <div class="stat-number"><?php  echo number_format($stats['total_appointments']); ?></div>
                        <div class="stat-change <?php  echo $appointmentChange >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php  echo $appointmentChange >= 0 ? 'up' : 'down'; ?>"></i> 
                            <?php  echo abs($appointmentChange); ?>% from <?php echo $periodText; ?>
                        </div>
                    </div>
                </div>

                <div class="stat-card" data-metric="patients">
                    <div class="stat-icon">
                        <i class="fas fa-user-injured"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Patients</h3>
                        <div class="stat-number"><?php  echo number_format($stats['total_patients']); ?></div>
                        <div class="stat-change <?php  echo $patientChange >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php  echo $patientChange >= 0 ? 'up' : 'down'; ?>"></i> 
                            <?php  echo abs($patientChange); ?>% from <?php echo $periodText; ?>
                        </div>
                    </div>
                </div>

                <div class="stat-card" data-metric="retention">
                    <div class="stat-icon">
                        <i class="fas fa-redo"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Patient Retention</h3>
                        <div class="stat-number"><?php  echo $stats['patient_retention']; ?>%</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> 8% from last quarter
                        </div>
                    </div>
                </div>

                <div class="stat-card" data-metric="revenue">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Monthly Revenue</h3>
                        <div class="stat-number">&#8369;<?php  echo number_format($stats['monthly_revenue'], 2); ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> 8.7% from <?php echo $periodText; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Grid -->
            <div class="analytics-grid">
                <!-- Appointments Chart -->
                <div class="analytics-card">
                    <div class="card-header">
                        <h3>Appointment Trends</h3>
                        <div class="time-period">
                            <button class="period-btn <?php echo $period === 'week' ? 'active' : ''; ?>" data-period="week">Week</button>
                            <button class="period-btn <?php echo $period === 'month' ? 'active' : ''; ?>" data-period="month">Month</button>
                            <button class="period-btn <?php echo $period === 'quarter' ? 'active' : ''; ?>" data-period="quarter">Quarter</button>
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="chart-container">
                            <canvas id="appointmentsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Revenue Growth Chart -->
                <div class="analytics-card">
                    <div class="card-header">
                        <h3>Revenue Growth</h3>
                        <div class="time-period">
                            <span style="font-size: 0.85rem; color: var(--dark); opacity: 0.7;">Daily Trend</span>
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Patient Acquisition Chart -->
                <div class="analytics-card">
                    <div class="card-header">
                        <h3>Patient Acquisition</h3>
                        <div class="time-period">
                            <span style="font-size: 0.85rem; color: var(--dark); opacity: 0.7;">Daily Trend</span>
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="chart-container">
                            <canvas id="patientChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Patient Demographics -->
                <div class="analytics-card">
                    <div class="card-header">
                        <h3>Patient Demographics</h3>
                        <div class="time-period">
                            <button class="period-btn active" data-period="age">Age</button>
                            <button class="period-btn" data-period="gender">Gender</button>
                            <button class="period-btn" data-period="location">Location</button>
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="chart-container">
                            <canvas id="demographicsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Treatment Types -->
                <div class="analytics-card">
                    <div class="card-header">
                        <h3>Treatment Distribution</h3>
                        <div class="time-period">
                            <button class="period-btn active" data-period="type">By Type</button>
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="chart-container">
                            <canvas id="treatmentChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="analytics-card">
                    <div class="card-header">
                        <h3>Performance Metrics</h3>
                        <div class="time-period">
                            <button class="period-btn active" data-period="efficiency">Efficiency</button>
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="chart-container">
                            <canvas id="operationsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Tables -->
            <div class="analytics-card" style="height: auto; margin-bottom: 30px;">
                <div class="card-header">
                    <h3>Recent Appointments</h3>
                    <a href="admin-appointments.php" class="view-all">View All Appointments</a>
                </div>
                <div class="card-content" style="height: auto; padding-bottom: 25px;">
                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Dentist</th>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php  if (empty($recentAppointments)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 20px;">No upcoming appointments found.</td>
                                    </tr>
                                <?php  else: ?>
                                    <?php  foreach ($recentAppointments as $appt): ?>
                                        <tr>
                                            <td><strong><?php  echo htmlspecialchars($appt['patient_first_name'] . ' ' . $appt['patient_last_name']); ?></strong></td>
                                            <td>Dr. <?php  echo htmlspecialchars(($appt['dentist_fname'] ?? 'Unknown') . ' ' . ($appt['dentist_lname'] ?? '')); ?></td>
                                            <td><?php  echo htmlspecialchars($appt['service_name'] ?? 'Dental Service'); ?></td>
                                            <td><?php  echo date('M j, Y', strtotime($appt['appointment_date'])); ?></td>
                                            <td><?php  echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                            <td>
                                                <span class="status-badge <?php  echo $appt['status']; ?>">
                                                    <?php  echo ucfirst($appt['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php  endforeach; ?>
                                <?php  endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="analytics-card" style="height: auto; margin-bottom: 30px;">
                <div class="card-header">
                    <h3>Top Performing Services</h3>
                    <a href="admin-services.php" class="view-all">View Full Report</a>
                </div>
                <div class="card-content" style="height: auto; padding-bottom: 25px;">
                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Appointments</th>
                                    <th>Revenue</th>
                                    <th>Completion Rate</th>
                                    <th>Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php  if (empty($topServices)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No data available</td>
                                </tr>
                                <?php  else: ?>
                                    <?php  foreach ($topServices as $service): ?>
                                    <tr>
                                        <td><?php  echo htmlspecialchars($service['service_name']); ?></td>
                                        <td><?php  echo $service['appointment_count']; ?></td>
                                        <td>&#8369;<?php  echo number_format($service['revenue'], 2); ?></td>
                                        <td><?php  echo round($service['completion_rate'], 1); ?>%</td>
                                        <td><span class="trend-indicator trend-up"><i class="fas fa-arrow-up"></i> 12%</span></td>
                                    </tr>
                                    <?php  endforeach; ?>
                                <?php  endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script>
        // PHP data for JavaScript
        const chartLabels = <?php echo json_encode($chartLabels); ?>;
        const appointmentTrends = <?php  echo json_encode(array_values($appointmentTrends)); ?>;
        const revenueTrends = <?php echo json_encode(array_values($revenueTrends)); ?>;
        const patientTrends = <?php echo json_encode(array_values($patientTrends)); ?>;
        const demographicsData = <?php  echo json_encode($demographicsData); ?>;
        const treatmentLabels = <?php  echo json_encode($treatmentData['labels'] ?? []); ?>;
        const treatmentCounts = <?php  echo json_encode($treatmentData['data'] ?? []); ?>;
        const operationsData = <?php  echo json_encode($operationsData); ?>;
        const demographicsAgeLabels = ['0-12 Years', '13-19 Years', '20-39 Years', '40-59 Years', '60+ Years'];
        const demographicsAgeData = <?php  echo json_encode($demographicsData); ?>;
        const demographicsGenderLabels = <?php echo json_encode($genderLabels ?? []); ?>;
        const demographicsGenderData = <?php echo json_encode($genderData ?? []); ?>;
        const demographicsLocationLabels = <?php echo json_encode($locationLabels ?? []); ?>;
        const demographicsLocationData = <?php echo json_encode($locationData ?? []); ?>;
        
        // Set current date
        const currentDate = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };

        // Mobile sidebar toggle
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

        // Close sidebar when clicking on a link (for mobile)
        const sidebarLinks = document.querySelectorAll('.sidebar-item');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                }
            });
        });

        // Responsive sidebar behavior
        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        });

        // Filter functionality
        const applyFiltersBtn = document.getElementById('apply-filters');
        const timePeriodSelect = document.getElementById('time-period');

        applyFiltersBtn.addEventListener('click', () => {
            const filters = {
                timePeriod: timePeriodSelect.value
            };
            
            // Show loading state
            applyFiltersBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Filtering...';
            applyFiltersBtn.disabled = true;
            
            // Simulate API call
            // Redirect to apply filters
            window.location.href = `?period=${filters.timePeriod}`;
        });

        // Initialize Charts
        let appointmentsChart, demographicsChart, treatmentChart, operationsChart, revenueChart, patientChart;

        function initializeCharts() {
            // Color palettes for categorized charts
        const paletteSolid = ['#4361ee', '#3a0ca3', '#7209b7', '#f72585', '#4cc9f0', '#2ec4b6'];
        const paletteAlpha = paletteSolid.map(color => color + 'B3'); // 70% opacity

        // Appointments Chart
        const appointmentsCtx = document.getElementById('appointmentsChart').getContext('2d');
            appointmentsChart = new Chart(appointmentsCtx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Appointments',
                        data: appointmentTrends,
                        borderColor: 'var(--chart-blue)',
                        backgroundColor: 'rgba(74, 107, 255, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Revenue Growth Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: revenueTrends,
                        borderColor: '#28a745', // Success Green
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // Patient Acquisition Chart
            const patientCtx = document.getElementById('patientChart').getContext('2d');
            patientChart = new Chart(patientCtx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'New Patients',
                        data: patientTrends,
                        backgroundColor: 'rgba(255, 159, 64, 0.8)', // Orange
                        borderColor: '#ff9f40',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { stepSize: 1 } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // Demographics Chart
            const demographicsCtx = document.getElementById('demographicsChart').getContext('2d');
            demographicsChart = new Chart(demographicsCtx, {
                type: 'doughnut',
                data: {
                    labels: demographicsAgeLabels,
                    datasets: [{
                        data: demographicsAgeData,
                        backgroundColor: paletteAlpha.slice(0, 5),
                        borderColor: paletteSolid.slice(0, 5),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });

            // Handle period/filter buttons
            document.querySelectorAll('.period-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    // Prevent multiple reloads
                    if (this.classList.contains('active') && !this.closest('.analytics-card').querySelector('h3').textContent.includes('Appointment')) return;

                    const group = this.closest('.time-period').querySelectorAll('.period-btn');
                    group.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const period = this.getAttribute('data-period');
                    const cardTitle = this.closest('.analytics-card').querySelector('h3').textContent;
                    
                    // Specific chart handling based on the period value given
                    if (period === 'age' || period === 'gender' || period === 'location') {
                        showSystemMessage(`Updating chart to view: ${period.charAt(0).toUpperCase() + period.slice(1)}...`, 'success');
                        if (period === 'age') {
                            demographicsChart.data.labels = demographicsAgeLabels;
                            demographicsChart.data.datasets[0].data = demographicsAgeData;
                        } else if (period === 'gender') {
                            demographicsChart.data.labels = demographicsGenderLabels;
                            demographicsChart.data.datasets[0].data = demographicsGenderData;
                        } else if (period === 'location') {
                            demographicsChart.data.labels = demographicsLocationLabels;
                            demographicsChart.data.datasets[0].data = demographicsLocationData;
                        }
                        
                        // Adjust palette size
                        const dataLength = demographicsChart.data.labels.length;
                        demographicsChart.data.datasets[0].backgroundColor = paletteAlpha.slice(0, dataLength);
                        demographicsChart.data.datasets[0].borderColor = paletteSolid.slice(0, dataLength);
                        demographicsChart.update();
                    } else if (period === 'week' || period === 'month' || period === 'quarter') {
                        // For generic appointment trends, force a global redirect
                        if (cardTitle.includes('Appointment')) {
                            showSystemMessage(`Redirecting to ${period} view...`, 'info');
                            setTimeout(() => {
                                window.location.href = `?period=${period}`;
                            }, 500); // Small delay for the toast to be seen
                        }
                    }
                });
            });

            // Treatment Chart
            const treatmentCtx = document.getElementById('treatmentChart').getContext('2d');
            treatmentChart = new Chart(treatmentCtx, {
                type: 'bar',
                data: {
                    labels: treatmentLabels,
                    datasets: [{
                        label: 'Number of Procedures',
                        data: treatmentCounts,
                        backgroundColor: paletteAlpha,
                        borderColor: paletteSolid,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Performance Metrics Chart (Horizontal Bar)
            const operationsCtx = document.getElementById('operationsChart').getContext('2d');
            operationsChart = new Chart(operationsCtx, {
                type: 'bar',
                data: {
                    labels: ['Efficiency', 'Wait Time', 'Utilization', 'Retention', 'Revenue', 'No-Show Rate'],
                    datasets: [{
                        label: 'Performance Score',
                        data: [
                            operationsData.efficiency, 
                            82, 
                            operationsData.utilization, 
                            operationsData.retention, 
                            operationsData.revenue, 
                            operationsData.no_show_rate
                        ],
                        backgroundColor: paletteAlpha,
                        borderColor: paletteSolid,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y', // Makes it horizontal
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }

        // System message function
        function showSystemMessage(message, type = 'info') {
            const systemMessages = document.getElementById('systemMessages');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <span>${message}</span>
                <button class="close-message" style="background:none; border:none; cursor:pointer; font-size:1.2rem; margin-left:10px;">&times;</button>
            `;
            
            systemMessages.appendChild(alert);
            
            // Add close functionality
            alert.querySelector('.close-message').addEventListener('click', () => {
                alert.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            });
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        }


        // Stat card click functionality
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('click', function() {
                const metric = this.getAttribute('data-metric');
                const value = this.querySelector('.stat-number').textContent;
                
                showSystemMessage(`Detailed view for ${metric}: ${value}`, 'info');
                
                // Highlight the clicked card
                statCards.forEach(c => c.style.boxShadow = '');
                this.style.boxShadow = '0 5px 15px rgba(13, 91, 185, 0.2)';
                
                setTimeout(() => {
                    this.style.boxShadow = '';
                }, 2000);
            });
        });

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            
            // Dentist Check-in Toggle
            const checkinToggle = document.getElementById('checkin-toggle');
            const statusText = document.getElementById('checkin-status-text');
            const timeText = document.getElementById('checkin-time-text');
            
            if (checkinToggle) {
                checkinToggle.addEventListener('change', function() {
                    const isChecked = this.checked;
                    const prevStatusText = statusText.textContent;
                    const prevStatusClass = statusText.className;
                    const prevTimeText = timeText.textContent;
                    
                    // Optimistic UI Update: update immediately
                    statusText.textContent = isChecked ? 'Checked In' : 'Checked Out';
                    statusText.className = isChecked ? 'text-success' : 'text-danger';
                    if (!isChecked) timeText.textContent = '';
                    
                    fetch(`admin-dashboard.php?action=toggle_checkin&status=${isChecked}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (isChecked && data.time) {
                                    // Parse naive time string from PHP (YYYY-MM-DD HH:MM:SS)
                                    // Split and use constructor to avoid timezone ambiguity
                                    const t = data.time.split(/[- :]/);
                                    const time = new Date(t[0], t[1]-1, t[2], t[3], t[4], t[5]);
                                    const formattedTime = time.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true });
                                    timeText.textContent = `at ${formattedTime}`;
                                }
                                showSystemMessage(isChecked ? 'Availability updated: You are now checked in.' : 'Availability updated: You are now checked out.', 'success');
                            } else {
                                // Revert on failure
                                this.checked = !isChecked;
                                statusText.textContent = prevStatusText;
                                statusText.className = prevStatusClass;
                                timeText.textContent = prevTimeText;
                                showSystemMessage('Failed to update status. Please sign in again or contact support.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            this.checked = !isChecked;
                            statusText.textContent = prevStatusText;
                            statusText.className = prevStatusClass;
                            timeText.textContent = prevTimeText;
                            showSystemMessage('An error occurred. Please check your connection.', 'error');
                        });
                });
            }
        });

        window.URL_ROOT = "<?php echo URL_ROOT; ?>";
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