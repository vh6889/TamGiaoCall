<?php
/**
 * CORE FUNCTIONS FILE
 * Bổ sung đầy đủ các hàm cần thiết
 */

// =============================================
// DATABASE CONNECTION FUNCTIONS
// =============================================

/**
 * Get database connection (singleton pattern)
 */
function get_db_connection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

// =============================================
// DATABASE QUERY FUNCTIONS
// =============================================

function db_query($sql, $params = []) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_get_row($sql, $params = []) {
    return db_query($sql, $params)->fetch();
}

function db_get_results($sql, $params = []) {
    return db_query($sql, $params)->fetchAll();
}

function db_get_var($sql, $params = []) {
    $result = db_query($sql, $params)->fetch(PDO::FETCH_NUM);
    return $result ? $result[0] : null;
}

function db_get_col($sql, $params = []) {
    return db_query($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
}

function db_insert($table, $data) {
    $pdo = get_db_connection();
    $fields = array_keys($data);
    $sql = "INSERT INTO $table (`" . implode('`, `', $fields) . "`) VALUES (:" . implode(', :', $fields) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    return $pdo->lastInsertId();
}

function db_update($table, $data, $where, $where_params = []) {
    $pdo = get_db_connection();
    $set_parts = [];
    $all_params = [];
    
    foreach ($data as $key => $value) {
        $set_parts[] = "`$key` = :set_$key";
        $all_params["set_$key"] = $value;
    }
    
    foreach ($where_params as $i => $param) {
        $all_params["where_$i"] = $param;
    }
    
    // Replace ? with named placeholders in where clause
    $where_processed = preg_replace_callback('/\?/', function($matches) {
        static $i = 0;
        return ':where_' . $i++;
    }, $where);
    
    $sql = "UPDATE $table SET " . implode(', ', $set_parts) . " WHERE $where_processed";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($all_params);
}

function db_delete($table, $where, $params = []) {
    $sql = "DELETE FROM $table WHERE $where";
    return db_query($sql, $params);
}

// =============================================
// AUTHENTICATION FUNCTIONS
// =============================================

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Require user to be logged in
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash('error', 'Vui lòng đăng nhập');
        redirect('index.php');
        exit;
    }
}

/**
 * Get logged in user data
 */
function get_logged_user() {
    if (!is_logged_in()) return null;
    
    static $user = null;
    if ($user === null) {
        $user = db_get_row(
            "SELECT u.*, ol.label_name as status_label 
             FROM users u 
             LEFT JOIN order_labels ol ON u.status = ol.label_key 
             WHERE u.id = ?",
            [$_SESSION['user_id']]
        );
    }
    return $user;
}

/**
 * Get user by ID
 */
function get_user($user_id) {
    return db_get_row("SELECT * FROM users WHERE id = ?", [$user_id]);
}

// =============================================
// PERMISSION FUNCTIONS
// =============================================

function is_admin() {
    $user = get_logged_user();
    return $user && $user['role'] === 'admin';
}

function is_manager() {
    $user = get_logged_user();
    return $user && $user['role'] === 'manager';
}

function is_telesale() {
    $user = get_logged_user();
    return $user && $user['role'] === 'telesale';
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        set_flash('error', 'Bạn không có quyền truy cập');
        redirect('dashboard.php');
        exit;
    }
}

function require_manager() {
    require_login();
    if (!is_admin() && !is_manager()) {
        set_flash('error', 'Bạn không có quyền truy cập');
        redirect('dashboard.php');
        exit;
    }
}

// =============================================
// ORDER FUNCTIONS
// =============================================

/**
 * Get order by ID with full details
 */
function get_order($order_id) {
    return db_get_row(
        "SELECT o.*, 
                ol.label_name, ol.color as label_color, ol.icon as label_icon,
                ol.core_status,
                u.full_name as assigned_to_name, u.username as assigned_to_username
         FROM orders o
         LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
         LEFT JOIN users u ON o.assigned_to = u.id
         WHERE o.id = ?",
        [$order_id]
    );
}

/**
 * Get orders with filters
 */
function get_orders($filters = []) {
    $where = ["1=1"];
    $params = [];
    
    // Filter by status/primary_label
    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        $where[] = "o.primary_label = ?";
        $params[] = $filters['status'];
    }
    
    // Filter by assigned user
    if (!empty($filters['assigned_to'])) {
        $where[] = "o.assigned_to = ?";
        $params[] = $filters['assigned_to'];
    }
    
    // Search filter (phone, name, order number)
    if (!empty($filters['search'])) {
        $search = '%' . $filters['search'] . '%';
        $where[] = "(o.customer_phone LIKE ? OR o.customer_name LIKE ? OR o.order_number LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    // Date filters
    if (!empty($filters['date_from'])) {
        $where[] = "DATE(o.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "DATE(o.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    // Build query
    $sql = "SELECT o.*, 
                   ol.label_name, ol.color as label_color, ol.icon as label_icon,
                   ol.core_status,
                   u.full_name as assigned_to_name
            FROM orders o
            LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
            LEFT JOIN users u ON o.assigned_to = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY o.created_at DESC";
    
    // Add pagination
    if (!empty($filters['per_page'])) {
        $per_page = (int)$filters['per_page'];
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $per_page;
        
        $sql .= " LIMIT $per_page OFFSET $offset";
    }
    
    return db_get_results($sql, $params);
}

/**
 * Get order label info
 */
function get_order_label($label_key) {
    return db_get_row(
        "SELECT * FROM order_labels WHERE label_key = ?",
        [$label_key]
    );
}

/**
 * Count orders by criteria
 */
function count_orders($criteria = []) {
    $where = ["1=1"];
    $params = [];
    
    if (isset($criteria['primary_label'])) {
        $where[] = "primary_label = ?";
        $params[] = $criteria['primary_label'];
    }
    
    if (isset($criteria['assigned_to'])) {
        $where[] = "assigned_to = ?";
        $params[] = $criteria['assigned_to'];
    }
    
    if (isset($criteria['system_status'])) {
        $where[] = "system_status = ?";
        $params[] = $criteria['system_status'];
    }
    
    // Add search filter
    if (!empty($criteria['search'])) {
        $search = '%' . $criteria['search'] . '%';
        $where[] = "(customer_phone LIKE ? OR customer_name LIKE ? OR order_number LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    // Add date filters  
    if (!empty($criteria['date_from'])) {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $criteria['date_from'];
    }
    
    if (!empty($criteria['date_to'])) {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $criteria['date_to'];
    }
    
    return db_get_var(
        "SELECT COUNT(*) FROM orders WHERE " . implode(' AND ', $where),
        $params
    );
}

/**
 * Count orders by status
 */
function count_orders_by_status($status_key, $user_id = null) {
    $where = ["primary_label = ?"];
    $params = [$status_key];
    
    if ($user_id) {
        $where[] = "assigned_to = ?";
        $params[] = $user_id;
    }
    
    return db_get_var(
        "SELECT COUNT(*) FROM orders WHERE " . implode(' AND ', $where),
        $params
    );
}

// =============================================
// USER FUNCTIONS
// =============================================

/**
 * Get active telesales
 */
function get_telesales($status = null) {
    $where = ["role = 'telesale'"];
    $params = [];
    
    if ($status === 'active') {
        $where[] = "status = 'active'";
    }
    
    return db_get_results(
        "SELECT * FROM users WHERE " . implode(' AND ', $where) . " ORDER BY full_name",
        $params
    );
}

// =============================================
// TRANSACTION FUNCTIONS
// =============================================
// Đã được định nghĩa trong includes/transaction_helper.php
// Không cần khai báo lại ở đây

// =============================================
// STATUS HELPER FUNCTIONS
// =============================================

/**
 * Get new order status key
 */
function get_new_status_key() {
    return 'lbl_new_order';
}

/**
 * Get processing status key
 */
function get_processing_status_key() {
    return 'lbl_processing';
}

/**
 * Get completed statuses
 * Lấy tất cả status có core_status = 'success'
 */
function get_completed_statuses() {
    return db_get_col(
        "SELECT label_key FROM order_labels WHERE core_status = 'success'"
    );
}

/**
 * Get cancelled statuses
 * Lấy tất cả status có core_status = 'failed'
 */
function get_cancelled_statuses() {
    return db_get_col(
        "SELECT label_key FROM order_labels WHERE core_status = 'failed'"
    );
}

/**
 * Get confirmed statuses (alias for completed)
 */
function get_confirmed_statuses() {
    return get_completed_statuses();
}

// =============================================
// UTILITY FUNCTIONS
// =============================================

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Set flash message
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get flash message
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
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
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

// require_csrf() đã được định nghĩa trong includes/security_helper.php với nhiều tính năng hơn
// Không khai báo lại ở đây

/**
 * Format money
 */
function format_money($amount) {
    return number_format($amount, 0, ',', '.') . ' đ';
}

/**
 * Time ago helper
 */
function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return $difference . ' giây trước';
    } elseif ($difference < 3600) {
        return round($difference/60) . ' phút trước';
    } elseif ($difference < 86400) {
        return round($difference/3600) . ' giờ trước';
    } elseif ($difference < 2592000) {
        return round($difference/86400) . ' ngày trước';
    } else {
        return date('d/m/Y H:i', $timestamp);
    }
}

/**
 * Format date
 */
function format_date($date, $format = 'd/m/Y H:i') {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return '';
    }
    return date($format, strtotime($date));
}

/**
 * Truncate text
 */
function truncate($text, $length = 100, $ending = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length - strlen($ending)) . $ending;
}

/**
 * JSON response helpers
 */
function json_success($message = 'Success', $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
    exit;
}

function json_error($message = 'Error', $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// =============================================
// ACTIVITY LOG FUNCTIONS
// =============================================

/**
 * Log user activity
 */
function log_activity($action, $description, $entity_type = null, $entity_id = null) {
    $user = get_logged_user();
    $user_id = $user ? $user['id'] : null;
    
    db_insert('activity_logs', [
        'user_id' => $user_id,
        'action' => $action,
        'description' => $description,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

// =============================================
// SETTINGS FUNCTIONS
// =============================================

/**
 * Get setting value
 */
function get_setting($key, $default = null) {
    $value = db_get_var("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $value !== null ? $value : $default;
}

/**
 * Update setting value
 */
function update_setting($key, $value) {
    db_query(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        [$key, $value]
    );
}

// =============================================
// ERROR HANDLER FUNCTIONS
// =============================================

function handle_error($message, $code = 500) {
    error_log("[ERROR $code] $message");
    
    if (is_ajax_request()) {
        json_error($message, $code);
    } else {
        set_flash('error', $message);
        redirect($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    }
}

// is_ajax_request() đã được định nghĩa trong includes/security_helper.php
// Không khai báo lại ở đây

// =============================================
// PERMISSION CHECK FUNCTIONS
// =============================================

/**
 * Check if user can access order
 */
function can_access_order($order_id, $user_id = null) {
    if (!$user_id) {
        $user = get_logged_user();
        if (!$user) return false;
        $user_id = $user['id'];
    }
    
    $user = get_user($user_id);
    if (!$user) return false;
    
    // Admin can access all
    if ($user['role'] === 'admin') return true;
    
    $order = get_order($order_id);
    if (!$order) return false;
    
    // User assigned to order
    if ($order['assigned_to'] == $user_id) return true;
    
    // Manager can access their team's orders
    if ($user['role'] === 'manager') {
        $team_ids = db_get_col(
            "SELECT telesale_id FROM manager_assignments WHERE manager_id = ?",
            [$user_id]
        );
        
        if (in_array($order['assigned_to'], $team_ids)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Require order access
 */
function require_order_access($order_id, $allow_free = false) {
    $order = get_order($order_id);
    if (!$order) {
        handle_error('Đơn hàng không tồn tại', 404);
    }
    
    $user = get_logged_user();
    if (!$user) {
        handle_error('Unauthorized', 401);
    }
    
    // Check if order is free (unassigned)
    if ($allow_free && !$order['assigned_to']) {
        return $order;
    }
    
    // Check access permission
    if (!can_access_order($order_id, $user['id'])) {
        handle_error('Bạn không có quyền truy cập đơn hàng này', 403);
    }
    
    return $order;
}

// =============================================
// LABEL & STATUS FUNCTIONS
// =============================================

/**
 * Get order labels for display
 */
function get_order_label_options($exclude_system = true) {
    $where = $exclude_system ? "WHERE is_system = 0" : "";
    
    return db_get_results(
        "SELECT label_key, label_name, color, icon, core_status 
         FROM order_labels 
         {$where}
         ORDER BY sort_order ASC, label_name ASC"
    );
}

/**
 * Format label for display
 */
function format_order_label($label_key) {
    $label = get_order_label($label_key);
    if (!$label) return '';
    
    return sprintf(
        '<span class="badge" style="background-color: %s">
            <i class="%s"></i> %s
        </span>',
        htmlspecialchars($label['color']),
        htmlspecialchars($label['icon']),
        htmlspecialchars($label['label_name'])
    );
}

// =============================================
// CALL LOG FUNCTIONS
// =============================================

/**
 * Get active call for order
 */
function get_active_call($order_id, $user_id = null) {
    $where = "order_id = ? AND end_time IS NULL";
    $params = [$order_id];
    
    if ($user_id) {
        $where .= " AND user_id = ?";
        $params[] = $user_id;
    }
    
    return db_get_row(
        "SELECT * FROM call_logs WHERE {$where}",
        $params
    );
}

/**
 * Check if user has active call
 */
function has_active_call($user_id) {
    return (bool)db_get_var(
        "SELECT COUNT(*) FROM call_logs 
         WHERE user_id = ? AND end_time IS NULL",
        [$user_id]
    );
}

/**
 * Get call statistics for order
 */
function get_call_stats($order_id) {
    return db_get_row(
        "SELECT 
            COUNT(*) as total_calls,
            SUM(duration) as total_duration,
            AVG(duration) as avg_duration,
            MAX(start_time) as last_call_time
         FROM call_logs 
         WHERE order_id = ? AND status = 'completed'",
        [$order_id]
    );
}

// =============================================
// AUTHENTICATION HELPER FUNCTIONS
// =============================================

/**
 * Hash password using bcrypt
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
}

/**
 * Login user - FIXED VERSION
 * Sửa lỗi cột last_login -> last_login_at
 */
function login_user($username, $password) {
    // Lấy thông tin user từ database
    $user = db_get_row(
        "SELECT * FROM users WHERE username = ? AND status = 'active'",
        [$username]
    );
    
    // Kiểm tra user tồn tại và password đúng
    if ($user && password_verify($password, $user['password'])) {
        // Lưu session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Log activity
        log_activity('login', 'User logged in', 'user', $user['id']);
        
        // ✅ SỬA: Cập nhật last_login_at (không phải last_login)
        db_update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),  // ✅ ĐÚNG: last_login_at
            'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null  // Cũng cập nhật IP nếu cần
        ], 'id = ?', [$user['id']]);
        
        return true;
    }
    
    return false;
}

// =============================================
// VALIDATION HELPER FUNCTIONS  
// =============================================

/**
 * Validate email format
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// =============================================
// DISPLAY HELPER FUNCTIONS
// =============================================

/**
 * Display flash message
 */
function display_flash() {
    $flash = get_flash();
    if ($flash) {
        $alert_class = $flash['type'] === 'success' ? 'alert-success' : 'alert-danger';
        echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
        echo '<i class="fas fa-' . ($flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle') . ' me-2"></i>';
        echo htmlspecialchars($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

/**
 * Get client IP address
 */
function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ips = explode(',', $_SERVER[$key]);
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}