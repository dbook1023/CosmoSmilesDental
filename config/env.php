<?php
/**
 * Environment Configuration Loader
 * 
 * Loads variables from .env file into $_ENV and getenv().
 * Usage: require_once __DIR__ . '/../config/env.php';
 *        Then use: env('DB_HOST', 'default_value')
 */

function loadEnv($path) {
    if (!file_exists($path)) {
        error_log("WARNING: .env file not found at: $path");
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') === false) {
            continue;
        }
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Remove surrounding quotes if present
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        
        // Only set if not already defined (real env vars take precedence)
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * Get an environment variable with an optional default.
 */
function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    
    if ($value === false || $value === null) {
        return $default;
    }
    
    // Convert string booleans
    $lower = strtolower($value);
    if ($lower === 'true') return true;
    if ($lower === 'false') return false;
    if ($lower === 'null') return null;
    
    return $value;
}


/**
 * Dynamically determines the public assets root URL path.
 * 
 * Logic:
 * 1. Look for '/public/' in the current script name.
 * 2. If found, return everything up to and including '/public/'.
 * 3. If not found, assume the script is already running from the public root and return the directory path.
 * 
 * @return string The public base URL path with a trailing slash.
 */
function getProjectRoot() {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Find the position of '/public/' in the path
    $publicPos = strpos($scriptName, '/public/');
    
    if ($publicPos !== false) {
        // Return everything including 'public/'
        $root = substr($scriptName, 0, $publicPos + 8);
        return rtrim($root, '/') . '/';
    }
    
    // Fallback: If no '/public/' is found, we assume we are already in the public root.
    // Use the directory of the current script name.
    $dir = dirname($scriptName);
    return rtrim(str_replace('\\', '/', $dir), '/') . '/';
}

define('URL_ROOT', getProjectRoot());

// Auto-load .env from project root
$envPath = __DIR__ . '/../.env';
loadEnv($envPath);
