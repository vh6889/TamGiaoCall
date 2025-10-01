<?php
/**
 * Status Helper - System Status Management
 * Chỉ có 2 status hệ thống cố định: free và assigned
 */

if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Get system status: FREE (chưa gán)
 */
function get_free_status_key() {
    return 'free';
}

/**
 * Get system status: ASSIGNED (đã gán)
 */
function get_assigned_status_key() {
    return 'assigned';
}

/**
 * Check if status is system status
 */
function is_system_status($status_key) {
    return in_array($status_key, ['free', 'assigned']);
}

/**
 * Get all user-defined statuses (excluding system statuses)
 */
function get_user_statuses() {
    return db_get_results(
        "SELECT label_key AS status_key, label_key AS value, label_name AS label, label_name AS text, color, icon 
         FROM order_labels 
         WHERE is_system = 0 
         ORDER BY sort_order ASC"
    );
}

/**
 * Get all statuses including system
 */
function get_all_statuses() {
    return db_get_results("SELECT label_key AS status_key, label_name AS label, color, icon FROM order_labels ORDER BY sort_order");
}

/**
 * Get status options for select dropdown (excluding system statuses)
 */
function get_status_options_with_labels() {
    return db_get_results(
        "SELECT label_key AS status_key, label FROM order_labels 
         WHERE is_system = 0 
         ORDER BY sort_order ASC"
    );
}

/**
 * Get confirmed statuses (for statistics)
 */
function get_confirmed_statuses() {
    $statuses = db_get_col(
        "SELECT label_key AS status_key, FROM order_labels 
         WHERE is_system = 0 
         AND (label LIKE '%thành công%' OR label LIKE '%hoàn thành%' OR label LIKE '%completed%')"
    );
    return $statuses ?: [];
}

/**
 * Get cancelled statuses (for statistics)
 */
function get_cancelled_statuses() {
    $statuses = db_get_col(
        "SELECT label_key AS status_key, FROM order_labels 
         WHERE is_system = 0 
         AND (label LIKE '%hủy%' OR label LIKE '%cancelled%' OR label LIKE '%bom%')"
    );
    return $statuses ?: [];
}

/**
 * Check if status exists in database
 */
function is_valid_status($status_key) {
    return (bool)db_get_var(
        "SELECT COUNT(*) FROM order_labels WHERE label_key = ?",
        [$status_key]
    );
}

/**
 * Validate status change
 * System statuses cannot be manually set by user
 */
function validate_status_change($new_status) {
    if (is_system_status($new_status)) {
        return false; // Không cho phép user chọn status hệ thống
    }
    
    return is_valid_status($new_status);
}

/**
 * Validate status transition
 * Kiểm tra xem có thể chuyển từ status hiện tại sang status mới không
 */
function validate_status_transition($current_status, $new_status) {
    // System statuses cannot be set by user
    if (is_system_status($new_status)) {
        return false;
    }
    
    // Cannot change from locked/final statuses
    $final_statuses = array_merge(
        get_confirmed_statuses(),
        get_cancelled_statuses()
    );
    
    if (in_array($current_status, $final_statuses)) {
        return false;
    }
    
    return true;
}

/**
 * Get status badge HTML
 */
function get_status_badge($status_key) {
    $status = db_get_row(
        "SELECT label, color, icon FROM order_labels WHERE label_key = ?",
        [$status_key]
    );
    
    if (!$status) {
        return '<span class="badge bg-secondary">N/A</span>';
    }
    
    return sprintf(
        '<span class="badge" style="background-color: %s"><i class="fas %s"></i> %s</span>',
        htmlspecialchars($status['color']),
        htmlspecialchars($status['icon']),
        htmlspecialchars($status['label'])
    );
}

/**
 * Get status color for badge/display
 */
function get_status_color($status_key) {
    $color = db_get_var(
        "SELECT color FROM order_labels WHERE label_key = ?",
        [$status_key]
    );
    
    return $color ?: '#6c757d'; // Default gray
}
