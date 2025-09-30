<?php
/**
 * Common Functions
 * Telesale Manager System
 */

if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

// =============================================
// DATABASE FUNCTIONS
// =============================================

/**
 * Get Database Connection
 */
function get_db_connection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Execute Query
 */
function db_query($sql, $params = []) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Get Single Row
 */
function db_get_row($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetch();
}

/**
 * Get All Rows
 */
function db_get_results($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Get Single Value
 */
function db_get_var($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchColumn();
}

/**
 * Insert Data
 */
function db_insert($table, $data) {
    $fields = array_keys($data);
    $values = array_values($data);
    $placeholders = array_fill(0, count($fields), '?');
    
    $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") 
            VALUES (" . implode(', ', $placeholders) . ")";
    
    db_query($sql, $values);
    return get_db_connection()->lastInsertId();
}

/**
 * Update Data
 */
function db_update($table, $data, $where, $where_params = []) {
    $set_parts = [];
    $values = [];
    
    foreach ($data as $field => $value) {
        $set_parts[] = "{$field} = ?";
        $values[] = $value;
    }
    
    $sql = "UPDATE {$table} SET " . implode(', ', $set_parts) . " WHERE {$where}";
    $values = array_merge($values, $where_params);
    
    db_query($sql, $values);
    return get_db_connection()->lastInsertId();
}

/**
 * Delete Data
 */
function db_delete($table, $where, $where_params = []) {
    $sql = "DELETE FROM {$table} WHERE {$where}";
    db_query($sql, $where_params);
}

// =============================================
// AUTHENTICATION FUNCTIONS
// =============================================

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user
 */
if (!function_exists('get_current_user')) {
    /**
     * Get current user
     */
    function get_current_user() {
        if (!is_logged_in()) {
            return null;
        }

        return db_get_row(
            "SELECT * FROM users WHERE id = ? AND status = 'active'",
            [$_SESSION['user_id']]
        );
    }
}

/**
 * Check if user is admin
 */
function is_admin() {
    $user = get_current_user();
    return $user && $user['role'] === 'admin';
}

/**
 * Check if user is telesale
 */
function is_telesale() {
    $user = get_current_user();
    return $user && $user['role'] === 'telesale';
}

/**
 * Require login
 */
function require_login() {
    if (!is_logged_in()) {
        redirect('index.php?error=login_required');
        exit;
    }
}

/**
 * Require admin
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        redirect('dashboard.php?error=access_denied');
        exit;
    }
}

/**
 * Login user
 */
function login_user($username, $password) {
    $user = db_get_row(
        "SELECT * FROM users WHERE username = ? AND status = 'active'",
        [$username]
    );
    
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    
    // Update last login
    db_update('users', [
        'last_login_at' => date('Y-m-d H:i:s'),
        'last_login_ip' => get_client_ip()
    ], 'id = ?', [$user['id']]);
    
    // Log activity
    log_activity('login', 'User logged in');
    
    return true;
}

/**
 * Logout user
 */
function logout_user() {
    log_activity('logout', 'User logged out');
    session_destroy();
    redirect('index.php');
}

// =============================================
// SECURITY FUNCTIONS
// =============================================

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Hash password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get client IP
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// =============================================
// UTILITY FUNCTIONS
// =============================================

/**
 * Redirect
 */
function redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo '<script>window.location.href="' . $url . '";</script>';
    }
    exit;
}

/**
 * Format date
 */
function format_date($date, $format = DATETIME_FORMAT) {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Format money
 */
function format_money($amount, $decimals = 0) {
    $formatted = number_format($amount, $decimals, ',', '.');
    
    if (CURRENCY_POSITION === 'left') {
        return CURRENCY_SYMBOL . $formatted;
    } else {
        return $formatted . CURRENCY_SYMBOL;
    }
}

/**
 * Get order status badge
 */
function get_status_badge($status) {
    $statuses = ORDER_STATUS;
    
    if (!isset($statuses[$status])) {
        return '<span class="badge bg-secondary">Unknown</span>';
    }
    
    $info = $statuses[$status];
    return sprintf(
        '<span class="badge bg-%s"><i class="fas %s me-1"></i>%s</span>',
        $info['color'],
        $info['icon'],
        $info['label']
    );
}

/**
 * Get time ago
 */
function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return $diff . ' giây trước';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' phút trước';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' giờ trước';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' ngày trước';
    } else {
        return format_date($datetime);
    }
}

/**
 * Truncate text
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Generate random string
 */
function generate_random_string($length = 10) {
    return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / 62))), 1, $length);
}

// =============================================
// ACTIVITY LOG FUNCTIONS
// =============================================

/**
 * Log activity
 */
function log_activity($action, $description = '', $related_type = null, $related_id = null) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    return db_insert('activity_logs', [
        'user_id' => $user_id,
        'action' => $action,
        'description' => $description,
        'related_type' => $related_type,
        'related_id' => $related_id,
        'ip_address' => get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

// =============================================
// ORDER FUNCTIONS
// =============================================

/**
 * Get orders with filters
 */
function get_orders($filters = []) {
    $where = ['1=1'];
    $params = [];
    
    // Filter by status
    if (!empty($filters['status'])) {
        $where[] = "status = ?";
        $params[] = $filters['status'];
    }
    
    // Filter by assigned user
    if (!empty($filters['assigned_to'])) {
        $where[] = "assigned_to = ?";
        $params[] = $filters['assigned_to'];
    }
    
    // Filter by date range
    if (!empty($filters['date_from'])) {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    // Search by phone or name
    if (!empty($filters['search'])) {
        $where[] = "(customer_phone LIKE ? OR customer_name LIKE ? OR order_number LIKE ?)";
        $search_term = '%' . $filters['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // Available orders (not assigned)
    if (!empty($filters['available'])) {
        $where[] = "assigned_to IS NULL";
        $where[] = "status = 'new'";
    }
    
    // Pagination
    $page = $filters['page'] ?? 1;
    $per_page = $filters['per_page'] ?? ITEMS_PER_PAGE;
    $offset = ($page - 1) * $per_page;
    
    // Order by
    $order_by = $filters['order_by'] ?? 'created_at';
    $order_dir = $filters['order_dir'] ?? 'DESC';
    
    $sql = "SELECT * FROM orders WHERE " . implode(' AND ', $where) . 
           " ORDER BY {$order_by} {$order_dir} LIMIT {$per_page} OFFSET {$offset}";
    
    return db_get_results($sql, $params);
}

/**
 * Get order by ID
 */
function get_order($order_id) {
    return db_get_row("SELECT * FROM orders WHERE id = ?", [$order_id]);
}

/**
 * Get order notes
 */
function get_order_notes($order_id) {
    return db_get_results(
        "SELECT n.*, u.full_name, u.username 
         FROM order_notes n 
         LEFT JOIN users u ON n.user_id = u.id 
         WHERE n.order_id = ? 
         ORDER BY n.created_at DESC",
        [$order_id]
    );
}

/**
 * Count orders
 */
function count_orders($filters = []) {
    $where = ['1=1'];
    $params = [];
    
    if (!empty($filters['status'])) {
        $where[] = "status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['assigned_to'])) {
        $where[] = "assigned_to = ?";
        $params[] = $filters['assigned_to'];
    }
    
    if (!empty($filters['available'])) {
        $where[] = "assigned_to IS NULL";
        $where[] = "status = 'new'";
    }
    
    $sql = "SELECT COUNT(*) FROM orders WHERE " . implode(' AND ', $where);
    return db_get_var($sql, $params);
}

// =============================================
// USER FUNCTIONS
// =============================================

/**
 * Get all telesales
 */
function get_telesales($status = 'active') {
    return db_get_results(
        "SELECT * FROM users WHERE role = 'telesale' AND status = ? ORDER BY full_name",
        [$status]
    );
}

/**
 * Get user by ID
 */
function get_user($user_id) {
    return db_get_row("SELECT * FROM users WHERE id = ?", [$user_id]);
}

/**
 * Get user statistics
 */
function get_user_statistics($user_id = null, $date_from = null, $date_to = null) {
    $where = ['1=1'];
    $params = [];
    
    if ($user_id) {
        $where[] = "assigned_to = ?";
        $params[] = $user_id;
    }
    
    if ($date_from) {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $date_to;
    }
    
    $sql = "SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_orders,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_orders,
                COUNT(CASE WHEN status = 'no_answer' THEN 1 END) as no_answer_orders,
                SUM(call_count) as total_calls,
                ROUND(COUNT(CASE WHEN status = 'confirmed' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as success_rate
            FROM orders 
            WHERE " . implode(' AND ', $where);
    
    return db_get_row($sql, $params);
}

// =============================================
// SETTINGS FUNCTIONS
// =============================================

/**
 * Get setting value
 */
function get_setting($key, $default = null) {
    $value = db_get_var("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $value !== false ? $value : $default;
}

/**
 * Update setting
 */
function update_setting($key, $value) {
    $exists = db_get_var("SELECT COUNT(*) FROM settings WHERE setting_key = ?", [$key]);
    
    if ($exists) {
        db_update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
    } else {
        db_insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
    }
}

// =============================================
// NOTIFICATION FUNCTIONS
// =============================================

/**
 * Set flash message
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Display flash message
 */
function display_flash() {
    $flash = get_flash();
    if ($flash) {
        $alert_class = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ];
        
        $class = $alert_class[$flash['type']] ?? 'alert-info';
        
        echo '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
        echo sanitize($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

// =============================================
// JSON RESPONSE (for AJAX)
// =============================================

/**
 * Send JSON response
 */
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * JSON success response
 */
function json_success($message = 'Success', $data = []) {
    json_response([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * JSON error response
 */
function json_error($message = 'Error', $code = 400) {
    json_response([
        'success' => false,
        'message' => $message
    ], $code);
}