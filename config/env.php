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
 * Dynamically determines the project root URL path.
 * In dev: /ProjectName/
 * In production: /
 */
/**
 * Dynamically determines the project root URL path.
 * Automatically handles subdirectory development and production 'public-as-root' setups.
 */
function getProjectRoot() {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
    $scriptFileName = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    
    // Case 1: Subdirectory development setup (e.g., /Cosmo_Smiles_Dental_Clinic/public/...)
    $publicPos = strpos($scriptName, '/public/');
    if ($publicPos !== false) {
        return rtrim(substr($scriptName, 0, $publicPos + 1), '/') . '/';
    }
    
    // Case 2: Production setup where 'public/' is the document root or mapped to a subdirectory
    $publicPosPhysical = strpos($scriptFileName, '/public/');
    if ($publicPosPhysical !== false) {
        $pathAfterPublic = substr($scriptFileName, $publicPosPhysical + 8); // e.g. client/profile.php
        $relativeScriptName = ltrim($scriptName, '/'); 
        
        if (!empty($pathAfterPublic) && strpos($relativeScriptName, $pathAfterPublic) !== false) {
             $urlRoot = substr($relativeScriptName, 0, strpos($relativeScriptName, $pathAfterPublic));
             return '/' . rtrim($urlRoot, '/') . (empty($urlRoot) ? '' : '/');
        }
    }
    
    return '/';
}

/**
 * Helper function to clean asset URLs for hosting.
 * Automatically strips the 'public/' prefix in production environments.
 */
if (!function_exists('clean_url')) {
    function clean_url($path) {
        if (empty($path)) return '';
        $path = ltrim($path, '/');
        
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $publicInUrl = (strpos($scriptName, '/public/') !== false);
        
        // Strip 'public/' prefix if it's NOT in the current browser URL (Production mode)
        if (!$publicInUrl && substr($path, 0, 7) === 'public/') {
            $path = substr($path, 7);
        }
        
        $root = defined('URL_ROOT') ? URL_ROOT : '/';
        return rtrim($root, '/') . '/' . ltrim($path, '/');
    }
}

define('URL_ROOT', getProjectRoot());

// Auto-load .env from project root
$envPath = __DIR__ . '/../.env';
loadEnv($envPath);
