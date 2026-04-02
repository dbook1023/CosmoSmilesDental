<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    echo "Connected to database successfully.\n";

    // 1. Add columns client_notes and admin_notes if they don't exist
    echo "Checking for new columns...\n";
    $stmt = $conn->query("SHOW COLUMNS FROM appointments");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('client_notes', $columns)) {
        echo "Adding client_notes column...\n";
        $conn->exec("ALTER TABLE appointments ADD COLUMN client_notes TEXT AFTER notes");
    }
    
    if (!in_array('admin_notes', $columns)) {
        echo "Adding admin_notes column...\n";
        $conn->exec("ALTER TABLE appointments ADD COLUMN admin_notes TEXT AFTER client_notes");
    }

    // 2. Migrate data
    echo "Migrating data from 'notes' to 'client_notes' and 'admin_notes'...\n";
    $stmt = $conn->query("SELECT id, notes FROM appointments");
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updateStmt = $conn->prepare("UPDATE appointments SET client_notes = ?, admin_notes = ? WHERE id = ?");

    foreach ($appointments as $appt) {
        $notes = $appt['notes'] ?? '';
        $clientNotes = $notes;
        $adminNotes = '';

        if (strpos($notes, '--- COMPLETED NOTES ---') !== false) {
            $parts = explode('--- COMPLETED NOTES ---', $notes);
            $clientNotes = trim($parts[0]);
            $adminNotes = trim($parts[1] ?? '');
        }

        // Also check for --- ADMIN NOTE --- split if it was used differently
        if (strpos($clientNotes, '--- ADMIN NOTE ---') !== false) {
            $parts = explode('--- ADMIN NOTE ---', $clientNotes);
            $clientNotes = trim($parts[0]);
            $adminNotes = trim($parts[1] ?? '') . ($adminNotes ? "\n\n" . $adminNotes : "");
        }

        $updateStmt->execute([$clientNotes, $adminNotes, $appt['id']]);
    }

    echo "Migration completed successfully. " . count($appointments) . " rows processed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
