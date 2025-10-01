<?php
/**
 * Status Helper - NO DUPLICATE FUNCTIONS
 * Chỉ chứa các functions KHÔNG có trong functions.php
 */
if (!defined('TSM_ACCESS')) die('Direct access not allowed');

// ==================================================
// SYSTEM STATUS FUNCTIONS
// ==================================================
function get_free_status_key() {
    return 'free';
}

function get_assigned_status_key() {
    return 'assigned';
}

function is_system_status($status_key) {
    return in_array($status_key, ['free', 'assigned']);
}

// ==================================================
// STATUS INFO FUNCTIONS  
// ==================================================

/**
 * Get status info by key
 * Bao gồm cả fallback cho system statuses
 */
function get_status_info($status_key) {
    $label = db_get_row("
        SELECT label_key AS status_key, 
               label_name AS label, 
               color, icon, core_status
        FROM order_labels 
        WHERE label_key = ?
    ", [$status_key]);
    
    if ($label) return $label;
    
    // Fallback for system statuses
    if ($status_key === 'free') {
        return [
            'status_key' => 'free',
            'label' => 'Chưa gán',
            'color' => '#6c757d',
            'icon' => 'fa-inbox',
            'core_status' => 'new'
        ];
    }
    
    if ($status_key === 'assigned') {
        return [
            'status_key' => 'assigned',
            'label' => 'Đã gán',
            'color' => '#17a2b8',
            'icon' => 'fa-user-check',
            'core_status' => 'processing'
        ];
    }
    
    // Unknown status
    return [
        'status_key' => $status_key,
        'label' => $status_key,
        'color' => '#6c757d',
        'icon' => 'fa-tag',
        'core_status' => 'processing'
    ];
}

// ==================================================
// VALIDATION FUNCTIONS
// ==================================================

function is_valid_status($status_key) {
    return (bool)db_get_var("SELECT COUNT(*) FROM order_labels WHERE label_key = ?", [$status_key]);
}

function validate_status_change($new_status) {
    if (is_system_status($new_status)) return false;
    return is_valid_status($new_status);
}

// ==================================================
// DISPLAY FUNCTIONS
// ==================================================

/**
 * Format status badge HTML
 */
function format_status_badge($status_key) {
    if (empty($status_key)) {
        return '<span class="badge bg-secondary">N/A</span>';
    }
    
    $status_info = get_status_info($status_key);
    
    if (!$status_info) {
        return '<span class="badge bg-secondary">' . htmlspecialchars($status_key) . '</span>';
    }
    
    $color = htmlspecialchars($status_info['color']);
    $icon = htmlspecialchars($status_info['icon']);
    $label = htmlspecialchars($status_info['label']);
    
    return sprintf(
        '<span class="badge" style="background-color: %s; color: #fff;">
            <i class="fas %s"></i> %s
        </span>',
        $color,
        $icon,
        $label
    );
}

/**
 * Get status options for dropdown
 * Chỉ lấy user-created labels
 */
function get_status_options_for_dropdown() {
    return db_get_results("
        SELECT label_key, label_name, color, icon, core_status
        FROM order_labels 
        WHERE is_system = 0
        ORDER BY sort_order ASC
    ");
}

/**
 * Get all user-created statuses
 */
function get_all_statuses() {
    return db_get_results("
        SELECT 
            label_key AS status_key,
            label_key AS value,
            label_name AS label,
            label_name AS text,
            color, 
            icon, 
            sort_order, 
            is_system,
            core_status
        FROM order_labels 
        WHERE is_system = 0
        ORDER BY sort_order ASC
    ");
}

/**
 * Generate unique label key
 */
function generate_label_key() {
    return 'lbl_' . time() . '_' . bin2hex(random_bytes(4));
}

// ==================================================
// CORE STATUS CHECK FUNCTIONS
// ==================================================

/**
 * Check if order is completed
 * Based on core_status = 'success'
 */
function is_order_completed($order_id) {
    $core_status = db_get_var("
        SELECT ol.core_status
        FROM orders o
        JOIN order_labels ol ON o.primary_label = ol.label_key
        WHERE o.id = ?
    ", [$order_id]);
    
    return ($core_status === 'success');
}

/**
 * Count orders by core status
 */
function count_orders_by_core_status_for_user($user_id = null) {
    $where = ['1=1'];
    $params = [];
    
    if ($user_id) {
        $where[] = 'o.assigned_to = ?';
        $params[] = $user_id;
    }
    
    $sql = "
        SELECT 
            SUM(CASE WHEN ol.core_status = 'new' THEN 1 ELSE 0 END) as new_orders,
            SUM(CASE WHEN ol.core_status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
            SUM(CASE WHEN ol.core_status = 'success' THEN 1 ELSE 0 END) as success_orders,
            SUM(CASE WHEN ol.core_status = 'failed' THEN 1 ELSE 0 END) as failed_orders
        FROM orders o
        LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
        WHERE " . implode(' AND ', $where);
    
    return db_get_row($sql, $params);
}

/**
 * Get label by core status
 * Ví dụ: lấy tất cả labels có core_status = 'success'
 */
function get_labels_by_core_status($core_status) {
    return db_get_results("
        SELECT label_key, label_name, color, icon
        FROM order_labels
        WHERE core_status = ?
        ORDER BY sort_order ASC
    ", [$core_status]);
}

/**
 * Check if label is final (success or failed)
 */
function is_final_status($label_key) {
    $core_status = db_get_var("
        SELECT core_status FROM order_labels WHERE label_key = ?
    ", [$label_key]);
    
    return in_array($core_status, ['success', 'failed']);
}

/**
 * Get default label for core status
 */
function get_default_label_for_core($core_status) {
    return db_get_var("
        SELECT label_key 
        FROM order_labels 
        WHERE core_status = ? AND is_default = 1
        LIMIT 1
    ", [$core_status]);
}

// Alias functions cho tương thích
function get_user_statuses() {
    return get_all_statuses();
}

function get_user_labels_for_dropdown() {
    return get_status_options_for_dropdown();
}
?>