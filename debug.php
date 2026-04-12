<?php
/**
 * Cosmo Smiles Dental - 500 Error Debugger
 * Upload this to your root directory and access it via your browser.
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Cosmo Smiles Dental - Server Debugger</h1>";
echo "<hr>";

// 1. Check PHP Version
echo "<h3>1. Environment Check</h3>";
echo "PHP Version: " . PHP_VERSION . " (Required: 7.4+)<br>";
echo "Current Directory: " . __DIR__ . "<br>";

// 2. Check for .env file
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    echo "<span style='color: green;'>[+] .env file found.</span><br>";
} else {
    echo "<span style='color: red;'>[-] .env file NOT found!</span> (Run setup.php or upload your .env)<br>";
}

// 3. Check for Vendor folder
$vendorPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    echo "<span style='color: green;'>[+] Vendor/autoload.php found.</span><br>";
} else {
    echo "<span style='color: red;'>[-] Vendor folder NOT found!</span> (You must upload the 'vendor' folder or run 'composer install')<br>";
}

// 4. Test Database Connection
echo "<h3>2. Database Connectivity</h3>";
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        echo "<span style='color: green;'>[+] Database connection successful.</span><br>";
    } else {
        echo "<span style='color: red;'>[-] Database connection failed.</span> Check your .env credentials.<br>";
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>[-] Database Error: " . $e->getMessage() . "</span><br>";
}

// 5. Check Permissions
echo "<h3>3. Directory Permissions</h3>";
$dirs = ['uploads', 'tmp', 'logs'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_writable($path)) {
        echo "<span style='color: green;'>[+] $dir is writable.</span><br>";
    } else {
        echo "<span style='color: red;'>[-] $dir is NOT writable!</span> (Set permissions to 755 or 775)<br>";
    }
}

echo "<hr>";
echo "<strong>Next Step:</strong> If all items above are green but you still get a 500 error, check your server's <code>.htaccess</code> file or the main <code>public/index.php</code> for syntax errors.";
