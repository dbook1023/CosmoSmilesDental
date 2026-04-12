<?php
/**
 * Secure Image Proxy
 * Safely serves files from the private /uploads directory.
 * Prevents direct URL access to sensitive patient data.
 */
ob_start();
session_start();

$path = $_GET['path'] ?? '';
// Normalize the path
$path = ltrim($path, '/');

// 1. Security Check: Block directory traversal (no .. allowed)
if (strpos($path, '..') !== false) {
    header("HTTP/1.1 400 Bad Request");
    die('Invalid path requested.');
}

// 2. Identify Privacy Level
$isPublic = false;
// Avatars are generally public-facing
if (strpos($path, 'avatar/') === 0) {
    $isPublic = true;
}

// 3. Authorization Check
// If the file is NOT public (like signatures or records), only allow Admins or Staff
if (!$isPublic) {
    $isAuthorized = isset($_SESSION['admin_logged_in']) || isset($_SESSION['staff_logged_in']);
    
    if (!$isAuthorized) {
        header("HTTP/1.1 403 Forbidden");
        die('Unauthorized access attempt logged.');
    }
}

// 4. Resolve Physical Path (Root uploads folder)
$uploadsRoot = realpath(__DIR__ . '/../uploads/');
$fullPath = realpath($uploadsRoot . '/' . $path);

// 5. Verification: Ensure the path hasn't escaped the /uploads folder
if ($fullPath === false || strpos($fullPath, $uploadsRoot) !== 0 || !file_exists($fullPath)) {
    header("HTTP/1.1 404 Not Found");
    die('Asset not found in secure storage.');
}

// 6. Serve the File with Correct MIME Type
$mime = mime_content_type($fullPath);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
// Prevent browsers from caching sensitive records too long
if (!$isPublic) {
    header('Cache-Control: private, max-age=0');
}
readfile($fullPath);
ob_end_flush();
