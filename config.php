<?php
/**
 * Configuration File
 * Telesale Manager System
 * Version: 1.0.0
 */

// Không cho truy cập trực tiếp
if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

// =============================================
// DATABASE CONFIGURATION
// =============================================
define('DB_HOST', 'localhost');           // Database host
define('DB_NAME', 'telesale_manager');    // Database name
define('DB_USER', 'root');                // Database username
define('DB_PASS', '');                    // Database password
define('DB_CHARSET', 'utf8mb4');

// =============================================
// SITE CONFIGURATION
// =============================================
define('SITE_NAME', 'Telesale Manager');
define('SITE_URL', 'http://localhost:8080/tamgiaocall/');
define('TIMEZONE', 'Asia/Ho_Chi_Minh');

// =============================================
// API CONFIGURATION
// =============================================
define('API_SECRET_KEY', 'YOUR-SECRET-KEY-12345'); // Thay bằng key bảo mật của bạn
define('WOO_API_URL', '');                         // URL WordPress/WooCommerce
define('WOO_CONSUMER_KEY', '');                    // WooCommerce Consumer Key
define('WOO_CONSUMER_SECRET', '');                 // WooCommerce Consumer Secret

// =============================================
// SESSION CONFIGURATION
// =============================================
define('SESSION_NAME', 'tsm_session');
define('SESSION_LIFETIME', 86400); // 24 hours

// =============================================
// SECURITY
// =============================================
define('HASH_COST', 10); // bcrypt cost
define('PASSWORD_MIN_LENGTH', 6);

// =============================================
// PAGINATION
// =============================================
define('ITEMS_PER_PAGE', 20);

// =============================================
// FILE UPLOAD (cho tương lai)
// =============================================
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_SIZE', 5242880); // 5MB

// =============================================
// DATE & TIME FORMAT
// =============================================
define('DATE_FORMAT', 'd/m/Y');
define('DATETIME_FORMAT', 'd/m/Y H:i');
define('TIME_FORMAT', 'H:i');

// =============================================
// CURRENCY
// =============================================
define('CURRENCY_SYMBOL', '₫');
define('CURRENCY_POSITION', 'right'); // left or right

// =============================================
// ORDER STATUS
// =============================================
// REMOVED - Using dynamic order_status_configs table instead
// define('ORDER_STATUS', [...]);

// =============================================
// USER ROLES
// =============================================
// REMOVED - Using dynamic role_permissions table instead
// define('USER_ROLES', [...]);

// =============================================
// ERROR REPORTING (Development/Production)
// =============================================
define('ENVIRONMENT', 'development'); // development or production

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// =============================================
// TIMEZONE SETUP
// =============================================
date_default_timezone_set(TIMEZONE);

// =============================================
// START SESSION
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.name', SESSION_NAME);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// =============================================
// AUTOLOAD CLASSES (Simple)
// =============================================
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});