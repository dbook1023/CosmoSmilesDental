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
 * Detect project root URL prefix (e.g., /Cosmo_Smiles_Dental_Clinic/)
 */
function getProjectRoot() {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    // If we're at /Cosmo_Smiles_Dental_Clinic/public/index.php,
    // we want /Cosmo_Smiles_Dental_Clinic/
    $publicPos = strpos($scriptName, '/public/');
    if ($publicPos !== false) {
        return substr($scriptName, 0, $publicPos + 1);
    }
    
    // Fallback for root files (like setup.php or others)
    $setupPos = strpos($scriptName, '/setup.php');
    if ($setupPos !== false) {
        return substr($scriptName, 0, $setupPos + 1);
    }

    return '/'; // Final fallback
}

define('URL_ROOT', getProjectRoot());

// Auto-load .env from project root
$envPath = __DIR__ . '/../.env';
loadEnv($envPath);
