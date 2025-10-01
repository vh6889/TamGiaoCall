<?php
/**
 * AUTHENTICATION & HELPER FUNCTIONS
 * Thêm vào functions.php
 */

// =============================================
// TRANSACTION FUNCTIONS
// =============================================

function begin_transaction() {
    get_db_connection()->beginTransaction();
}

function commit_transaction() {
    get_db_connection()->commit();
}

function rollback_transaction() {
    get_db_connection()->rollBack();
}

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

function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

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
?>