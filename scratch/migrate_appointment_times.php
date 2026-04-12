<?php
try {
    $host = 'localhost';
    $dbname = 'cosmo_smiles_dental';
    $user = 'root';
    $pass = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Adding columns actual_start_time and actual_end_time to appointments table...\n";
    $pdo->exec("ALTER TABLE appointments 
               ADD COLUMN actual_start_time TIME NULL AFTER duration_minutes, 
               ADD COLUMN actual_end_time TIME NULL AFTER actual_start_time");
    
    echo "Migration successful!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist. Skipping.\n";
    } else {
        echo "Migration failed: " . $e->getMessage() . "\n";
    }
}
?>
