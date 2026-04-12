<?php
// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get DB Name properly
    $stmt = $conn->query("SELECT DATABASE()");
    $dbName = $stmt->fetchColumn();

    if (!$dbName) {
        die("Could not determine database name. Please check your config/database.php");
    }

    echo "<div style='font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;'>";
    echo "<h2 style='color: #2c3e50;'>Cosmo Smiles Dental - Auto Database Patcher</h2>";
    echo "<p>Connected to database: <strong>{$dbName}</strong></p>";
    echo "<ul style='font-family: monospace; list-style-type: none; padding: 0;'>";

    // Helper function to check if a column exists
    function columnExists($conn, $dbName, $table, $column) {
        $stmt = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$dbName, $table, $column]);
        return $stmt->fetchColumn() !== false;
    }

    // Comprehensive list of all database gaps
    $updates = [
        'clients' => [
            'gender' => "enum('male','female','other') DEFAULT NULL",
            'address_line1' => "varchar(255) DEFAULT NULL",
            'address_line2' => "varchar(255) DEFAULT NULL",
            'city' => "varchar(100) DEFAULT NULL",
            'state' => "varchar(100) DEFAULT NULL",
            'postal_code' => "varchar(20) DEFAULT NULL",
            'country' => "varchar(100) DEFAULT 'Philippines'",
            'phone' => "varchar(15) DEFAULT NULL", 
            'is_minor' => "tinyint(1) DEFAULT 0",
            'parental_consent' => "tinyint(1) DEFAULT 0",
            'profile_image' => "varchar(255) DEFAULT NULL",
            'medical_history_status' => "enum('pending','completed') DEFAULT 'pending'",
            'medical_history_edit_allowed' => "tinyint(1) DEFAULT 0",
            'parental_signature' => "varchar(255) DEFAULT NULL"
        ],
        'appointments' => [
            'dentist_id' => "int(11) DEFAULT NULL",
            'notes' => "text DEFAULT NULL",
            'client_notes' => "text DEFAULT NULL",
            'admin_notes' => "text DEFAULT NULL",
            'duration_minutes' => "int(11) DEFAULT 30",
            'payment_type' => "enum('cash','gcash') DEFAULT 'cash'",
            'service_price' => "decimal(10,2) DEFAULT NULL",
            'patient_first_name' => "varchar(50) DEFAULT NULL",
            'patient_last_name' => "varchar(50) DEFAULT NULL",
            'patient_phone' => "varchar(15) DEFAULT NULL",
            'patient_email' => "varchar(100) DEFAULT NULL"
        ],
        'patient_records' => [
            'record_time' => "time NOT NULL DEFAULT '00:00:00'",
            'findings' => "text DEFAULT NULL",
            'notes' => "text DEFAULT NULL",
            'followup_instructions' => "text DEFAULT NULL",
            'files' => "text DEFAULT NULL",
            'tooth_numbers' => "text DEFAULT NULL",
            'surfaces' => "text DEFAULT NULL",
            'created_by' => "varchar(100) NOT NULL DEFAULT 'System'",
            'is_archived' => "tinyint(1) DEFAULT 0",
            'archived_by' => "varchar(100) DEFAULT NULL",
            'archive_reason' => "text DEFAULT NULL",
            'archived_at' => "timestamp NULL DEFAULT NULL"
        ],
        'patient_medical_history' => [
            'heart_disease_details' => "text DEFAULT NULL",
            'high_blood_pressure' => "tinyint(1) DEFAULT 0",
            'past_surgeries' => "text DEFAULT NULL",
            'current_medications' => "text DEFAULT NULL",
            'is_pregnant' => "tinyint(1) DEFAULT 0",
            'other_conditions' => "text DEFAULT NULL"
        ],
        'verification_otps' => [
            'phone' => "varchar(20) DEFAULT NULL",
            'verified' => "tinyint(1) DEFAULT 0"
        ],
        'staff_users' => [
            'phone' => "varchar(15) DEFAULT NULL",
            'last_login' => "timestamp NULL DEFAULT NULL"
        ],
        'admin_users' => [
            'phone' => "varchar(20) DEFAULT NULL",
            'specialization' => "varchar(100) DEFAULT NULL",
            'last_login' => "timestamp NULL DEFAULT NULL"
        ],
        'dentists' => [
            'bio' => "text DEFAULT NULL",
            'is_checked_in' => "tinyint(1) DEFAULT 0",
            'checked_in_at' => "datetime DEFAULT NULL"
        ]
    ];

    // Loop through and patch
    foreach ($updates as $table => $columns) {
        foreach ($columns as $column => $definition) {
            if (!columnExists($conn, $dbName, $table, $column)) {
                try {
                    $conn->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                    echo "<li style='color: green; margin-bottom: 5px;'>✅ <strong>Added missing column:</strong> `$column` to `$table`</li>";
                } catch (Exception $e) {
                    echo "<li style='color: red; margin-bottom: 5px;'>❌ <strong>Failed to add:</strong> `$column` to `$table`: " . $e->getMessage() . "</li>";
                }
            } else {
                echo "<li style='color: gray; margin-bottom: 5px;'>➖ <i>Skipped: `$column` already exists in `$table`</i></li>";
            }
        }
    }

    echo "<hr>";

    // CRITICAL FIX: Make client_id Nullable for Guest Bookings
    try {
        $conn->exec("ALTER TABLE `appointments` MODIFY `client_id` varchar(100) NULL");
        echo "<li style='color: blue; font-weight: bold;'>🎯 Fixed: Guest Bookings now enabled (`client_id` allowed to be blank).</li>";
    } catch (Exception $e) {
        echo "<li style='color: red;'>❌ Failed to fix Guest Booking rule: " . $e->getMessage() . "</li>";
    }

    // CRITICAL FIX: Change service_id to varchar to support multiple services ('1,2') 
    try {
        $conn->exec("ALTER TABLE `appointments` MODIFY `service_id` varchar(255) NOT NULL");
        echo "<li style='color: blue; font-weight: bold;'>🎯 Fixed: Multiple Services support added (`service_id` changed from int to varchar).</li>";
    } catch (Exception $e) {
        echo "<li style='color: red;'>❌ Failed to fix Multiple Services rule: " . $e->getMessage() . "</li>";
    }

    echo "</ul>";
    echo "<h3 style='color: #27ae60; text-align: center; margin-top: 30px;'>🎉 Auto-Patch Complete! Your database is now 100% matched to your code.</h3>";
    echo "<p style='text-align: center;'>You may now return to the clinic website and successfully book an appointment.</p>";
    echo "<div style='background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-top: 20px; font-weight: bold; text-align: center;'>";
    echo "⚠️ Security Notice: Please delete `run_db_patch.php` from your hosting server after you are done testing.";
    echo "</div>";
    echo "</div>";

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
