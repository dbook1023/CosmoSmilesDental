<?php
/**
 * Cosmo Smiles Dental Clinic - Setup Script
 * 
 * This script automates the full configuration of the project.
 * It will:
 * 1. Check for .env file and create it from .env.example if missing.
 * 2. Verify basic directory structure and permissions.
 * 3. Check for composer dependencies.
 * 4. Initialize the database schema and default data.
 * 5. Run all pending migration scripts.
 */

// Helper function to output formatted messages
function logMsg($type, $msg) {
    $prefix = [
        'info'  => "[*] ",
        'success' => "[+] ",
        'warn'  => "[!] ",
        'error' => "[ERROR] "
    ];
    echo ($prefix[$type] ?? "") . $msg . "\n";
}

echo "================================================\n";
echo "Cosmo Smiles Dental Clinic - Auto Configuration\n";
echo "================================================\n\n";

// 1. Environment File Setup
$envFile = __DIR__ . '/.env';
$exampleFile = __DIR__ . '/.env.example';

if (!file_exists($envFile)) {
    logMsg('info', ".env file not found. Creating from .env.example...");
    if (file_exists($exampleFile)) {
        if (copy($exampleFile, $envFile)) {
            logMsg('success', "Successfully created .env file.");
            logMsg('warn', "IMPORTANT: Open .env and update your database and SMTP credentials.");
        } else {
            logMsg('error', "Failed to copy .env.example to .env. Check file permissions.");
        }
    } else {
        logMsg('error', ".env.example not found. Please ensure it exists in the root directory.");
    }
} else {
    logMsg('success', ".env file already exists.");
}

// 2. Directory Checks
$dirs = [
    'uploads', 
    'tmp', 
    'logs', 
    'uploads/avatar', 
    'uploads/signatures', 
    'uploads/patient_records',
    'public/assets/images/dynamic'
];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!file_exists($path)) {
        logMsg('info', "Creating missing directory: $dir...");
        if (mkdir($path, 0777, true)) {
            logMsg('success', "Created $dir directory.");
        } else {
            logMsg('error', "Failed to create $dir directory.");
        }
    } else {
        logMsg('success', "$dir directory exists.");
    }
}

// 3. Composer Check
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "\n";
    logMsg('warn', "vendor/autoload.php not found.");
    logMsg('warn', "Please run 'composer install' to install project dependencies.");
} else {
    logMsg('success', "Composer dependencies found.");
}

// 4. Database Setup
echo "\n--- Database Configuration ---\n";
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        logMsg('success', "Database connection successful.");

        // Check if tables exist
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tables)) {
            logMsg('info', "Database is empty. Initializing schema...");
            $sqlFile = file_exists(__DIR__ . '/database/production_setup.sql') ? 'database/production_setup.sql' : 
                      (file_exists(__DIR__ . '/deploy_setup.sql') ? 'deploy_setup.sql' : 
                      (file_exists(__DIR__ . '/database_schema.sql') ? 'database_schema.sql' : null));
            
            if ($sqlFile) {
                logMsg('info', "Importing $sqlFile...");
                $sql = file_get_contents(__DIR__ . '/' . $sqlFile);
                if ($conn->exec($sql) !== false) {
                    logMsg('success', "Database schema and default data imported successfully.");
                } else {
                    logMsg('error', "Failed to import database schema.");
                }
            } else {
                logMsg('warn', "No SQL schema file (deploy_setup.sql or database_schema.sql) found.");
            }
        } else {
            logMsg('success', "Database already contains " . count($tables) . " tables.");
        }

        // 5. Run Migrations
        echo "\n--- Running Migrations ---\n";
        $migrationFiles = [
            __DIR__ . '/scripts/migrate_login_attempts.php',
            __DIR__ . '/scripts/migrate_request_logs.php',
            __DIR__ . '/scripts/migrate_notes.php',
            __DIR__ . '/scripts/migrate_medical_history.php',
            __DIR__ . '/scripts/migrate_records.php',
            __DIR__ . '/scripts/migrate_medical_edit.php',
            __DIR__ . '/scripts/migrate_medical_edit_requests.php'
        ];

        foreach ($migrationFiles as $file) {
            if (file_exists($file)) {
                $basename = basename($file);
                logMsg('info', "Executing $basename...");
                // We capture output to show it here
                ob_start();
                include $file;
                $output = ob_get_clean();
                echo trim($output) . "\n";
            }
        }
        logMsg('success', "All pending migrations processed.");

    } else {
        logMsg('error', "Could not connect to the database. Please check .env settings.");
    }
} catch (Exception $e) {
    logMsg('error', "Database error: " . $e->getMessage());
}

echo "\n================================================\n";
echo "Setup process completed.\n";
echo "Next steps:\n";
echo "1. Verify .env credentials if you haven't already.\n";
echo "2. Run 'composer install' if dependencies are missing.\n";
echo "3. Access the application via your local server.\n";
echo "================================================\n";
