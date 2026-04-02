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

$recordId = $_GET['id'] ?? null;
if (!$recordId) {
    die("Record ID is required.");
}

    $clientId = $_SESSION['client_client_id'];
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        die("Database connection failed.");
    }
    
    try {
        // Get single record for the client
        $stmt = $pdo->prepare("
            SELECT record_id, record_date, record_time, record_type, record_title, 
                   dentist, duration, `procedure`, description, findings, notes, followup_instructions 
            FROM patient_records 
            WHERE id = ? AND client_id = ? AND is_archived = 0 
            LIMIT 1
        ");
        $stmt->execute([$recordId, $clientId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        die("Record not found or access denied.");
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=medical_report_' . $recordId . '.csv');
    
    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Output the column headings
    fputcsv($output, array('Record ID', 'Date', 'Time', 'Type', 'Title', 'Dentist', 'Duration', 'Procedure', 'Description', 'Findings', 'Notes', 'Follow-up Instructions'));
    
    // Output the record data
    fputcsv($output, $record);
    
    fclose($output);
    exit();
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("Database error: Unable to download report at this time.");
}
?>
