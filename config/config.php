<?php

// Load database configuration FIRST
require_once __DIR__ . '/database.php';

// Then Buildings class (after database is available)
require_once __DIR__ . '/../includes/Buildings.php';

// Load environment variables
function loadEnv($file = '.env')
{
    if (!file_exists($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

// Load environment variables
loadEnv(__DIR__ . '/../.env');

// Application Configuration
define('APP_NAME', $_ENV['APP_NAME'] ?? 'PG Management System');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');

// Supabase Configuration
define('SUPABASE_URL', $_ENV['SUPABASE_URL']);
define('SUPABASE_ANON_KEY', $_ENV['SUPABASE_ANON_KEY']);
define('SUPABASE_SERVICE_KEY', $_ENV['SUPABASE_SERVICE_KEY']);

// Database Configuration
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_PORT', $_ENV['DB_PORT'] ?? 5432);
define('DB_NAME', $_ENV['DB_NAME'] ?? 'postgres');
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);

// ⭐ UPDATED Session Configuration
define('SESSION_LIFETIME', $_ENV['SESSION_LIFETIME'] ?? 28800);           // 8 hours in seconds
define('SESSION_REFRESH_TIME', $_ENV['SESSION_REFRESH_TIME'] ?? 1800);    // 30 minutes in seconds
define('COOKIE_SECURE', $_ENV['COOKIE_SECURE'] === 'true');
define('COOKIE_HTTPONLY', $_ENV['COOKIE_HTTPONLY'] === 'true');

// File Upload Configuration
define('MAX_FILE_SIZE', $_ENV['MAX_FILE_SIZE'] ?? 5242880); // 5MB
define('ALLOWED_EXTENSIONS', explode(',', $_ENV['ALLOWED_EXTENSIONS'] ?? 'jpg,jpeg,png,pdf'));
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Application Paths
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// ✅ REMOVED: Building Configuration (now dynamic via Buildings class)
// Hard-coded building constants have been removed
// Buildings are now managed via the Buildings class and database

// ✅ IMPROVED: Helper functions for backward compatibility
function getBuildingCodes()
{
    try {
        return Buildings::getCodes();
    } catch (Exception $e) {
        error_log('getBuildingCodes() error: ' . $e->getMessage());
        return [];
    }
}

function getBuildingNames()
{
    try {
        return Buildings::getNames();
    } catch (Exception $e) {
        error_log('getBuildingNames() error: ' . $e->getMessage());
        return [];
    }
}

// ✅ NEW: Additional building helper functions
function getBuildingName($code)
{
    try {
        $building = Buildings::getByCode($code);
        return $building ? $building['building_name'] : $code;
    } catch (Exception $e) {
        error_log('getBuildingName() error: ' . $e->getMessage());
        return $code;
    }
}

function buildingExists($code)
{
    try {
        return Buildings::exists($code);
    } catch (Exception $e) {
        error_log('buildingExists() error: ' . $e->getMessage());
        return false;
    }
}

// ⭐ ENHANCED Session Setup
if (session_status() == PHP_SESSION_NONE) {
    // Set PHP session configuration for enhanced session management
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => COOKIE_SECURE,
        'httponly' => COOKIE_HTTPONLY,
        'samesite' => 'Lax'
    ]);

    session_start();

    // ⭐ ADD SESSION ACTIVITY TRACKING
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    }

    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
}

// Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Helper Functions
function asset($path)
{
    return APP_URL . '/assets/' . $path;
}

function route($path)
{
    return APP_URL . '/' . $path;
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function flash($key, $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
    } else {
        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $message;
    }
}

function old($key, $default = '')
{
    return $_SESSION['old'][$key] ?? $default;
}

function csrf_token()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ⭐ NEW ENHANCED SESSION MANAGEMENT FUNCTIONS
function is_session_expired()
{
    if (!isset($_SESSION['last_activity'])) {
        return true;
    }

    return (time() - $_SESSION['last_activity']) > SESSION_LIFETIME;
}

function refresh_session()
{
    $_SESSION['last_activity'] = time();

    // Regenerate session ID periodically for security
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    }

    // Regenerate session ID every SESSION_REFRESH_TIME (30 minutes)
    if ((time() - $_SESSION['last_regeneration']) > SESSION_REFRESH_TIME) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

function extend_session()
{
    // ⭐ ADAPTED TO YOUR SESSION VARIABLES (admin_id instead of user_id)
    if (isset($_SESSION['admin_id'])) {
        refresh_session();

        // Update cookie expiration
        setcookie(
            session_name(),
            session_id(),
            time() + SESSION_LIFETIME,
            '/',
            '',
            COOKIE_SECURE,
            COOKIE_HTTPONLY
        );

        return true;
    }
    return false;
}

function get_session_remaining_time()
{
    if (!isset($_SESSION['last_activity'])) {
        return 0;
    }

    $remaining = SESSION_LIFETIME - (time() - $_SESSION['last_activity']);
    return max(0, $remaining);
}

function destroy_session()
{
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

// ⭐ SAFE HTML OUTPUT FUNCTION (used in your dashboard)
function safe_html($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
