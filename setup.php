<?php
/**
 * Cosmo Smiles Dental Clinic - Setup Script
 * 
 * This script helps automate the initial configuration of the project.
 * It will:
 * 1. Check for .env file and create it from .env.example if missing.
 * 2. Verify basic directory structure and permissions.
 * 3. Check for composer dependencies.
 */

echo "================================================\n";
echo "Cosmo Smiles Dental Clinic - Auto Configuration\n";
echo "================================================\n\n";

// 1. Environment File Setup
$envFile = __DIR__ . '/.env';
$exampleFile = __DIR__ . '/.env.example';

if (!file_exists($envFile)) {
    echo "[*] .env file not found. Creating from .env.example...\n";
    if (file_exists($exampleFile)) {
        if (copy($exampleFile, $envFile)) {
            echo "[+] Successfully created .env file.\n";
            echo "[!] IMPORTANT: Open .env and update your database and SMTP credentials.\n";
        } else {
            echo "[!] ERROR: Failed to copy .env.example to .env. Check file permissions.\n";
        }
    } else {
        echo "[!] ERROR: .env.example not found. Please ensure it exists in the root directory.\n";
    }
} else {
    echo "[+] .env file already exists.\n";
}

// 2. Directory Checks
$dirs = ['uploads', 'tmp', 'logs'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!file_exists($path)) {
        echo "[*] Creating missing directory: $dir...\n";
        if (mkdir($path, 0777, true)) {
            echo "[+] Created $dir directory.\n";
        } else {
            echo "[!] ERROR: Failed to create $dir directory.\n";
        }
    } else {
        echo "[+] $dir directory exists.\n";
    }
}

// 3. Composer Check
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "\n[!] WARNING: vendor/autoload.php not found.\n";
    echo "[!] Please run 'composer install' to install project dependencies (PHPMailer, etc.).\n";
} else {
    echo "[+] Composer dependencies found.\n";
}

// 4. Database Check (Optional)
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        echo "[+] Database connection successful.\n";
    } else {
        echo "[!] WARNING: Could not connect to the database. Please check .env settings.\n";
    }
} catch (Exception $e) {
    echo "[!] WARNING: Database connection error: " . $e->getMessage() . "\n";
}

echo "\n================================================\n";
echo "Setup process completed.\n";
echo "Next steps:\n";
echo "1. Configure .env with your production credentials.\n";
echo "2. Import your database schema (if applicable).\n";
echo "3. Run 'composer install' on your hosting server.\n";
echo "================================================\n";
