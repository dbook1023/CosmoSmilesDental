<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit();
}

$clientId = $_SESSION['client_id'];
$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    die("Database connection failed.");
}

try {
    // Get all records for the client
    $stmt = $pdo->prepare("
        SELECT record_id, record_date, record_time, record_type, record_title, 
               dentist, duration, `procedure`, description, findings, notes, followup_instructions 
        FROM patient_records 
        WHERE client_id = ? AND is_archived = 0 
        ORDER BY record_date DESC, record_time DESC
    ");
    $stmt->execute([$clientId]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($records)) {
        die("No records found to export.");
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=medical_records_' . date('Y-m-d') . '.csv');
    
    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Output the column headings
    fputcsv($output, array('Record ID', 'Date', 'Time', 'Type', 'Title', 'Dentist', 'Duration', 'Procedure', 'Description', 'Findings', 'Notes', 'Follow-up Instructions'));
    
    // Loop over the rows, outputting them to the CSV
    foreach ($records as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("Database error: Unable to export records at this time.");
}
?>