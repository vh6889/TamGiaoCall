<?php
/**
 * Common Functions
 * Telesale Manager System
 * Version: 2.0 - FULLY DYNAMIC
 */

if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

// =============================================
// DATABASE FUNCTIONS
// =============================================

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

function db_query($sql, $params = []) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_get_row($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function db_get_results($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function db_get_var($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchColumn();
}

// NEW: Get column values as array
function db_get_col($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Alias for compatibility
function db_get_value($sql, $params = []) {
    return db_get_var($sql, $params);
}

function db_insert($table, $data) {
    $fields = array_keys($data);
    $values = array_values($data);
    $placeholders = array_fill(0, count($fields), '?');
    
    $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") 
            VALUES (" . implode(', ', $placeholders) . ")";
    
    db_query($sql, $values);
    return get_db_connection()->lastInsertId();
}

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
}

function db_delete($table, $where, $where_params = []) {
    $sql = "DELETE FROM {$table} WHERE {$where}";
    db_query($sql, $where_params);
}

// =============================================
// AUTHENTICATION FUNCTIONS
// =============================================

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user - CRITICAL FUNCTION
 * Returns full user array from database
 */
function get_logged_user() {
    if (!is_logged_in()) {
        return null;
    }

    static $cached_user = null;
    
    if ($cached_user === null) {
        $cached_user = db_get_row(
            "SELECT * FROM users WHERE id = ? AND status = 'active'",
            [$_SESSION['user_id']]
        );
        
        if (!is_array($cached_user) || empty($cached_user)) {
            return null;
        }
    }
    
    return $cached_user;
}

function is_admin() {
    $user = get_logged_user();
    return $user && is_array($user) && isset($user['role']) && $user['role'] === 'admin';
}

function is_manager() {
    $user = get_logged_user();
    return $user && is_array($user) && isset($user['role']) && $user['role'] === 'manager';
}

function is_telesale() {
    $user = get_logged_user();
    return $user && is_array($user) && isset($user['role']) && $user['role'] === 'telesale';
}

function require_login() {
    if (!is_logged_in()) {
        redirect('index.php?error=login_required');
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        redirect('dashboard.php?error=access_denied');
        exit;
    }
}

function login_user($username, $password) {
    $user = db_get_row(
        "SELECT * FROM users WHERE username = ? AND status = 'active'",
        [$username]
    );
    
    if (!$user || !is_array($user) || !password_verify($password, $user['password'])) {
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

function logout_user() {
    log_activity('logout', 'User logged out');
    session_destroy();
    redirect('index.php');
}

// =============================================
// SECURITY FUNCTIONS
// =============================================

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

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

function redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo '<script>window.location.href="' . $url . '";</script>';
    }
    exit;
}

function format_date($date, $format = DATETIME_FORMAT) {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

function format_money($amount, $decimals = 0) {
    $formatted = number_format($amount, $decimals, ',', '.');
    
    if (CURRENCY_POSITION === 'left') {
        return CURRENCY_SYMBOL . $formatted;
    } else {
        return $formatted . CURRENCY_SYMBOL;
    }
}

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

function truncate($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

function generate_random_string($length = 10) {
    return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / 62))), 1, $length);
}

// =============================================
// ACTIVITY LOG FUNCTIONS
// =============================================

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
/**
 * Lấy thông tin đơn hàng theo ID
 * JOIN với order_labels để lấy thông tin nhãn
 * 
 * @param int $order_id ID đơn hàng
 * @return array|null Thông tin đơn hàng hoặc null nếu không tìm thấy
 */
function get_order($order_id) {
    return db_get_row("
        SELECT 
            o.*,
            ol.label_name,
            ol.color AS label_color,
            ol.icon AS label_icon,
            ol.is_final AS label_is_final,
            u.full_name AS assigned_to_name,
            u.username AS assigned_to_username
        FROM orders o
        LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
        LEFT JOIN users u ON o.assigned_to = u.id
        WHERE o.id = ?
    ", [$order_id]);
}

function get_orders($filters = []) {
    $where = ['1=1'];
    $params = [];
    
    if (!empty($filters['status'])) {
        $where[] = "o.primary_label = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['assigned_to'])) {
        $where[] = "o.assigned_to = ?";
        $params[] = $filters['assigned_to'];
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = "DATE(o.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "DATE(o.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(o.customer_phone LIKE ? OR o.customer_name LIKE ? OR o.order_number LIKE ?)";
        $search_term = '%' . $filters['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($filters['available'])) {
        $where[] = "o.system_status = 'free'";
        $where[] = "o.is_locked = 0";
    }
    
    $page = $filters['page'] ?? 1;
    $per_page = $filters['per_page'] ?? ITEMS_PER_PAGE;
    $offset = ($page - 1) * $per_page;
    
    $order_by = $filters['order_by'] ?? 'created_at';
    $order_dir = $filters['order_dir'] ?? 'DESC';
    
    $sql = "SELECT o.*, 
               o.primary_label as status,       
               o.system_status,                  
               ol.label_name, 
               ol.color AS label_color, 
               ol.icon AS label_icon
        FROM orders o
            LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
            WHERE " . implode(' AND ', $where) . 
           " ORDER BY o.{$order_by} {$order_dir} LIMIT {$per_page} OFFSET {$offset}";
    
    return db_get_results($sql, $params);
}

function count_orders($filters = []) {
    $where = ['1=1'];
    $params = [];
    
    if (!empty($filters['status'])) {
        $where[] = "primary_label = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['assigned_to'])) {
        $where[] = "assigned_to = ?";
        $params[] = $filters['assigned_to'];
    }
    
    if (!empty($filters['available'])) {
        $where[] = "system_status = 'free'";
        $where[] = "is_locked = 0";
    }
    
    $sql = "SELECT COUNT(*) FROM orders WHERE " . implode(' AND ', $where);
    return (int) db_get_var($sql, $params);
}
/**
 * Count orders by primary_label (for status tabs)
 * 
 * @param string $label_key Label key to count
 * @param int|null $user_id Filter by user (null = all users)
 * @return int Count of orders
 */
function count_orders_by_status($label_key, $user_id = null) {
    $where = ['primary_label = ?'];
    $params = [$label_key];
    
    if ($user_id !== null) {
        $where[] = 'assigned_to = ?';
        $params[] = $user_id;
    }
    
    $sql = "SELECT COUNT(*) FROM orders WHERE " . implode(' AND ', $where);
    return (int)db_get_var($sql, $params);
}

// =============================================
// USER FUNCTIONS
// =============================================

function get_telesales($status = 'active') {
    return db_get_results(
        "SELECT * FROM users WHERE role = 'telesale' AND status = ? ORDER BY full_name",
        [$status]
    );
}

function get_managers($status = 'active') {
    return db_get_results(
        "SELECT * FROM users WHERE role = 'manager' AND status = ? ORDER BY full_name",
        [$status]
    );
}

function get_user($user_id) {
    return db_get_row("SELECT * FROM users WHERE id = ?", [$user_id]);
}

/**
 * Get user statistics - FULLY DYNAMIC
 */
function get_user_statistics($user_id = null, $date_from = null, $date_to = null) {
    $where = ['1=1'];
    $params = [];
    
    if ($user_id) {
        $where[] = "o.assigned_to = ?";
        $params[] = $user_id;
    }
    
    if ($date_from) {
        $where[] = "DATE(o.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where[] = "DATE(o.created_at) <= ?";
        $params[] = $date_to;
    }
    
    // Build dynamic query using LABEL configs (updated)
    $sql = "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN ol.label_name LIKE '%xác nhận%' OR ol.label_name LIKE '%hoàn%' 
                         OR ol.label_name LIKE '%thành công%' THEN 1 ELSE 0 END) as confirmed_orders,
                SUM(CASE WHEN ol.label_name LIKE '%từ chối%' OR ol.label_name LIKE '%rejected%' 
                         THEN 1 ELSE 0 END) as rejected_orders,
                SUM(CASE WHEN ol.label_name LIKE '%không nghe%' OR ol.label_name LIKE '%no answer%' 
                         THEN 1 ELSE 0 END) as no_answer_orders,
                SUM(o.call_count) as total_calls,
                ROUND(
                    SUM(CASE WHEN ol.label_name LIKE '%xác nhận%' OR ol.label_name LIKE '%hoàn%' 
                             OR ol.label_name LIKE '%thành công%' THEN 1 ELSE 0 END) * 100.0 / 
                    NULLIF(COUNT(*), 0), 2
                ) as success_rate
            FROM orders o
            LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
            WHERE " . implode(' AND ', $where);
    
    return db_get_row($sql, $params);
}

// =============================================
// PERMISSION FUNCTIONS
// =============================================

function has_permission($permission) {
    $user = get_logged_user();
    if (!$user) return false;
    
    // Admin has all permissions
    if ($user['role'] === 'admin') return true;
    
    // Check permission from database
    $has_perm = db_get_var(
        "SELECT COUNT(*) FROM role_permissions WHERE role = ? AND permission = ?",
        [$user['role'], $permission]
    );
    
    return $has_perm > 0;
}

function require_permission($permission) {
    if (!has_permission($permission)) {
        redirect('dashboard.php?error=permission_denied');
        exit;
    }
}

function can_manage_user($target_user_id) {
    $current_user = get_logged_user();
    if (!$current_user) return false;
    
    // Admin can manage all
    if ($current_user['role'] === 'admin') return true;
    
    // Manager can manage assigned telesales
    if ($current_user['role'] === 'manager') {
        $is_assigned = db_get_var(
            "SELECT COUNT(*) FROM manager_assignments 
             WHERE manager_id = ? AND telesale_id = ?",
            [$current_user['id'], $target_user_id]
        );
        return $is_assigned > 0;
    }
    
    return false;
}

function get_manager_telesales($manager_id) {
    return db_get_results(
        "SELECT u.* FROM users u
         INNER JOIN manager_assignments ma ON u.id = ma.telesale_id
         WHERE ma.manager_id = ? AND u.status = 'active'
         ORDER BY u.full_name",
        [$manager_id]
    );
}

// =============================================
// REMINDER FUNCTIONS
// =============================================

function insert_reminder($order_id, $user_id, $type, $due_time, $remind_time = null) {
    db_insert('reminders', [
        'order_id' => $order_id,
        'user_id' => $user_id,
        'type' => $type,
        'due_time' => $due_time,
        'remind_time' => $remind_time,
        'status' => 'pending'
    ]);
    log_activity('insert_reminder', "Inserted reminder for order #$order_id (type: $type)", 'order', $order_id);
}

function cancel_pending_reminders($order_id) {
    db_update('reminders', 
        ['status' => 'cancelled'], 
        'order_id = ? AND status = ?', 
        [$order_id, 'pending']
    );
    log_activity('cancel_reminder', "Cancelled pending reminders for order #$order_id", 'order', $order_id);
}

function get_pending_reminders() {
    return db_get_results(
        "SELECT * FROM reminders WHERE status = 'pending' ORDER BY remind_time ASC"
    );
}

function get_overdue_reminders($grace_minutes = 5) {
    $grace_time = date('Y-m-d H:i:s', strtotime("-$grace_minutes minutes"));
    return db_get_results(
        "SELECT * FROM reminders WHERE status IN ('pending', 'sent') AND due_time < ?", 
        [$grace_time]
    );
}

// =============================================
// SETTINGS FUNCTIONS
// =============================================

function get_setting($key, $default = null) {
    $value = db_get_var("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $value !== false && $value !== null ? $value : $default;
}

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

function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

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

function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function json_success($message = 'Success', $data = []) {
    json_response([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

function json_error($message = 'Error', $code = 400) {
    json_response([
        'success' => false,
        'message' => $message
    ], $code);
}
/**
 * Render status options HTML for select element
 * 
 * @param string $current_status Current order status (optional)
 * @param bool $include_all Include "All" option
 * @return string HTML options
 */
function render_status_options($current_status = null, $include_all = false) {
    $html = '';
    
    if ($include_all) {
        $html .= '<option value="">Tất cả trạng thái</option>';
    }
    
    $statuses = db_get_results(
        "SELECT label_key AS status_key, label_name AS label, color, icon 
         FROM order_labels 
         WHERE is_system = 0
         ORDER BY sort_order ASC"
    );
    
    foreach ($statuses as $status) {
        $selected = ($current_status && $current_status === $status['status_key']) ? 'selected' : '';
        
        $html .= sprintf(
            '<option value="%s" %s>%s</option>',
            htmlspecialchars($status['status_key']),
            $selected,
            htmlspecialchars($status['label'])
        );
    }
    
    return $html;
}


/**
 * Check if user can receive handover
 * Used by managers
 */
function can_receive_handover($order_id) {
    $current_user = get_logged_user();
    
    if (!is_manager()) {
        return false;
    }
    
    $order = get_order($order_id);
    if (!$order) {
        return false;
    }
    
    // Manager can receive if order is assigned to their telesales
    if ($order['assigned_to']) {
        $telesales = get_manager_telesales($current_user['id']);
        $telesale_ids = array_column($telesales, 'id');
        
        return in_array($order['assigned_to'], $telesale_ids);
    }
    
    return false;
}

/**
 * Format time duration
 * 
 * @param int $seconds Duration in seconds
 * @return string Formatted duration (HH:MM:SS)
 */
function format_duration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

/**
 * Get order statistics for user
 * 
 * @param int $user_id
 * @param string $date_from
 * @param string $date_to
 * @return array
 */
function get_user_order_stats($user_id, $date_from = null, $date_to = null) {
    $where = ['assigned_to = ?'];
    $params = [$user_id];
    
    if ($date_from) {
        $where[] = 'DATE(created_at) >= ?';
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where[] = 'DATE(created_at) <= ?';
        $params[] = $date_to;
    }
    
    $stats = db_get_row(
        "SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status IN ('" . implode("','", get_confirmed_statuses()) . "') THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status IN ('" . implode("','", get_cancelled_statuses()) . "') THEN 1 ELSE 0 END) as cancelled,
            SUM(call_count) as total_calls,
            SUM(total_amount) as total_revenue
         FROM orders 
         WHERE " . implode(' AND ', $where),
        $params
    );
    
    // Calculate success rate
    if ($stats && $stats['total_orders'] > 0) {
        $stats['success_rate'] = round(($stats['confirmed'] / $stats['total_orders']) * 100, 2);
    } else {
        $stats['success_rate'] = 0;
    }
    
    return $stats;
}

/**
 * Check if order can be edited
 * 
 * @param array $order Order data
 * @param array $user Current user data
 * @return bool
 */
function can_edit_order($order, $user) {
    // Admin can always edit (unless locked)
    if ($user['role'] === 'admin' && !$order['is_locked']) {
        return true;
    }
    
    // Order is locked
    if ($order['is_locked']) {
        return false;
    }
    
    // Order not assigned to current user
    if ($order['assigned_to'] != $user['id']) {
        return false;
    }
    
    // Check if there's an active call
    $active_call = db_get_row(
        "SELECT * FROM call_logs 
         WHERE order_id = ? AND user_id = ? AND end_time IS NULL",
        [$order['id'], $user['id']]
    );
    
    return (bool)$active_call;
}

/**
 * Get order workflow state
 * Returns current state and available actions
 * 
 * @param array $order
 * @param array $user
 * @return array
 */
function get_order_workflow_state($order, $user) {
    $state = [
        'is_locked' => (bool)$order['is_locked'],
        'can_claim' => false,
        'can_start_call' => false,
        'can_edit' => false,
        'can_end_call' => false,
        'can_update_status' => false,
        'can_transfer' => false,
        'can_reclaim' => false
    ];
    
    // Locked - no actions allowed
    if ($order['is_locked']) {
        return $state;
    }
    
    // Not assigned - can claim
    if (!$order['assigned_to']) {
        $state['can_claim'] = true;
        return $state;
    }
    
    // Check active call
    $active_call = db_get_row(
        "SELECT * FROM call_logs 
         WHERE order_id = ? AND user_id = ? AND end_time IS NULL",
        [$order['id'], $user['id']]
    );
    
    // Assigned to current user
    if ($order['assigned_to'] == $user['id']) {
        if ($active_call) {
            $state['can_edit'] = true;
            $state['can_end_call'] = true;
        } else {
            $state['can_start_call'] = true;
        }
    }
    
    // Admin/Manager actions
    if ($user['role'] === 'admin' || $user['role'] === 'manager') {
        $state['can_transfer'] = true;
        $state['can_reclaim'] = true;
    }
    
    return $state;
}
// =============================================
// ORDER LABEL FUNCTIONS (NEW LOGIC)
// =============================================

/**
 * Lấy tất cả nhãn đơn hàng
 * @return array Danh sách nhãn
 */
function get_order_labels($include_system = false) {
    $where = $include_system ? '' : 'WHERE is_system = 0';
    return db_get_results("
        SELECT * FROM order_labels 
        {$where}
        ORDER BY sort_order ASC, label_name ASC
    ");
}

/**
 * Lấy thông tin 1 nhãn
 * @param string $label_key Mã nhãn
 * @return array|null Thông tin nhãn
 */
function get_order_label($label_key) {
    return db_get_row("
        SELECT * FROM order_labels WHERE label_key = ?
    ", [$label_key]);
}

/**
 * Kiểm tra nhãn có tồn tại không
 * @param string $label_key Mã nhãn
 * @return bool
 */
function label_exists($label_key) {
    return (bool)db_get_var("
        SELECT COUNT(*) FROM order_labels WHERE label_key = ?
    ", [$label_key]);
}

/**
 * Kiểm tra nhãn có phải là nhãn kết thúc không
 * @param string $label_key Mã nhãn
 * @return bool
 */
function is_final_label($label_key) {
    return (bool)db_get_var("
        SELECT is_final FROM order_labels WHERE label_key = ?
    ", [$label_key]);
}

/**
 * Gán nhãn cho đơn hàng
 * @param int $order_id ID đơn hàng
 * @param string $label_key Mã nhãn
 * @param int|null $user_id ID người gán (null = system)
 * @return bool
 */
function assign_order_label($order_id, $label_key, $user_id = null) {
    try {
        begin_transaction();
        
        // Validate
        if (!label_exists($label_key)) {
            throw new Exception("Nhãn không tồn tại: {$label_key}");
        }
        
        $order = get_order($order_id);
        if (!$order) {
            throw new Exception("Đơn hàng không tồn tại");
        }
        
        if ($order['is_locked']) {
            throw new Exception("Đơn hàng đã bị khóa");
        }
        
        // Update primary_label
        db_update('orders', [
            'primary_label' => $label_key,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$order_id]);
        
        // Trigger sẽ tự động ghi vào order_label_history và order_notes
        
        commit_transaction();
        return true;
        
    } catch (Exception $e) {
        rollback_transaction();
        error_log("Error assigning label: " . $e->getMessage());
        return false;
    }
}

/**
 * Xóa nhãn khỏi đơn hàng
 * @param int $order_id ID đơn hàng
 * @param int|null $user_id ID người xóa
 * @return bool
 */
function remove_order_label($order_id, $user_id = null) {
    try {
        begin_transaction();
        
        $order = get_order($order_id);
        if (!$order || !$order['primary_label']) {
            throw new Exception("Đơn hàng không có nhãn");
        }
        
        if ($order['is_locked']) {
            throw new Exception("Đơn hàng đã bị khóa");
        }
        
        // Remove label
        db_update('orders', [
            'primary_label' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$order_id]);
        
        // Log history
        db_insert('order_label_history', [
            'order_id' => $order_id,
            'label_key' => $order['primary_label'],
            'action' => 'removed',
            'assigned_by' => $user_id
        ]);
        
        commit_transaction();
        return true;
        
    } catch (Exception $e) {
        rollback_transaction();
        error_log("Error removing label: " . $e->getMessage());
        return false;
    }
}

/**
 * Lấy lịch sử nhãn của đơn hàng
 * @param int $order_id ID đơn hàng
 * @return array
 */
function get_order_label_history($order_id) {
    return db_get_results("
        SELECT 
            olh.*,
            ol.label_name,
            ol.color,
            ol.icon,
            u.full_name as assigned_by_name
        FROM order_label_history olh
        LEFT JOIN order_labels ol ON olh.label_key = ol.label_key
        LEFT JOIN users u ON olh.assigned_by = u.id
        WHERE olh.order_id = ?
        ORDER BY olh.created_at DESC
    ", [$order_id]);
}

/**
 * Claim đơn hàng (nhận đơn)
 * Chỉ đổi system_status từ free → assigned
 * KHÔNG gán nhãn tự động
 */
function claim_order($order_id, $user_id) {
    try {
        begin_transaction();
        
        // Lock row
        $order = db_get_row("SELECT * FROM orders WHERE id = ? FOR UPDATE", [$order_id]);
        
        if (!$order) {
            throw new Exception('Đơn hàng không tồn tại');
        }
        
        if ($order['system_status'] !== 'free') {
            throw new Exception('Đơn hàng đã được nhận bởi người khác');
        }
        
        if ($order['is_locked']) {
            throw new Exception('Đơn hàng đã bị khóa');
        }
        
        // Update: free → assigned
        db_update('orders', [
            'system_status' => 'assigned',
            'assigned_to' => $user_id,
            'assigned_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$order_id]);
        
        // Log
        db_insert('order_notes', [
            'order_id' => $order_id,
            'user_id' => $user_id,
            'note_type' => 'system',
            'content' => 'Nhận đơn hàng'
        ]);
        
        commit_transaction();
        return true;
        
    } catch (Exception $e) {
        rollback_transaction();
        error_log("Error claiming order: " . $e->getMessage());
        return false;
    }
}

/**
 * Release đơn hàng (trả đơn về pool)
 * Chỉ đổi system_status từ assigned → free
 * GIỮ NGUYÊN primary_label
 */
function release_order($order_id, $user_id) {
    try {
        begin_transaction();
        
        $order = get_order($order_id);
        
        if (!$order) {
            throw new Exception('Đơn hàng không tồn tại');
        }
        
        if ($order['system_status'] !== 'assigned') {
            throw new Exception('Đơn hàng chưa được nhận');
        }
        
        if ($order['is_locked']) {
            throw new Exception('Đơn hàng đã bị khóa, không thể trả lại');
        }
        
        // Update: assigned → free
        db_update('orders', [
            'system_status' => 'free',
            'assigned_to' => null,
            'assigned_at' => null
        ], 'id = ?', [$order_id]);
        
        // Log
        db_insert('order_notes', [
            'order_id' => $order_id,
            'user_id' => $user_id,
            'note_type' => 'system',
            'content' => 'Trả đơn hàng về pool'
        ]);
        
        commit_transaction();
        return true;
        
    } catch (Exception $e) {
        rollback_transaction();
        error_log("Error releasing order: " . $e->getMessage());
        return false;
    }
}

/**
 * Lấy danh sách đơn FREE (chưa ai nhận)
 */
function get_free_orders($limit = 50, $offset = 0) {
    return db_get_results("
        SELECT o.*, 
               ol.label_name, ol.color AS label_color, ol.icon AS label_icon
        FROM orders o
        LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
        WHERE o.system_status = 'free' 
          AND o.is_locked = 0
          AND o.approval_status != 'pending'
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ", [$limit, $offset]);
}

/**
 * Đếm số đơn FREE
 */
function count_free_orders() {
    return (int)db_get_var("
        SELECT COUNT(*) FROM orders 
        WHERE system_status = 'free' 
          AND is_locked = 0
          AND approval_status != 'pending'
    ");
}

/**
 * Lấy đơn của user (đã nhận - assigned)
 */
function get_user_assigned_orders($user_id, $limit = 50, $offset = 0) {
    return db_get_results("
        SELECT o.*, 
               ol.label_name, ol.color AS label_color, ol.icon AS label_icon
        FROM orders o
        LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
        WHERE o.system_status = 'assigned' 
          AND o.assigned_to = ?
          AND o.is_locked = 0
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ", [$user_id, $limit, $offset]);
}

/**
 * Đếm đơn của user
 */
function count_user_assigned_orders($user_id) {
    return (int)db_get_var("
        SELECT COUNT(*) FROM orders 
        WHERE system_status = 'assigned' 
          AND assigned_to = ?
          AND is_locked = 0
    ", [$user_id]);
}

// =============================================
// BACKWARD COMPATIBILITY (Giữ tên hàm cũ)
// =============================================

/**
 * @deprecated Dùng get_order_labels() thay thế
 */
function get_order_status_configs($include_system = false) {
    return get_order_labels($include_system);
}

/**
 * @deprecated Dùng get_order_label() thay thế
 */
function get_status_config($status_key) {
    return get_order_label($status_key);
}