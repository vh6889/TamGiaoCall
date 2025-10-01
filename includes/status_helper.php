<?php
/**
 * Status Helper - SIMPLIFIED & NO DUPLICATE
 * ✅ FIXED: Xóa hàm trùng với functions.php
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
// LABEL FUNCTIONS
// ==================================================

/**
 * Get all user-created labels (is_system = 0)
 */
function get_all_statuses() {
    return db_get_results("
        SELECT label_key AS status_key, 
               label_key AS value,
               label_name AS label, 
               label_name AS text,
               color, icon, sort_order, is_system, label_value
        FROM order_labels 
        WHERE is_system = 0
        ORDER BY sort_order ASC
    ");
}

/**
 * Alias for get_all_statuses()
 */
function get_user_statuses() {
    return get_all_statuses();
}

/**
 * Get status info by key
 */
function get_status_info($status_key) {
    $label = db_get_row("
        SELECT label_key AS status_key, 
               label_name AS label, 
               color, icon, label_value
        FROM order_labels 
        WHERE label_key = ?
    ", [$status_key]);
    
    if ($label) return $label;
    
    // Fallback for system statuses
    if ($status_key === 'free') {
        return [
            'status_key' => 'free',
            'label' => '[HỆ THỐNG] Chưa gán',
            'color' => '#6c757d',
            'icon' => 'fa-inbox',
            'label_value' => 0
        ];
    }
    
    if ($status_key === 'assigned') {
        return [
            'status_key' => 'assigned',
            'label' => '[HỆ THỐNG] Đã gán',
            'color' => '#17a2b8',
            'icon' => 'fa-user-check',
            'label_value' => 0
        ];
    }
    
    // Unknown status
    return [
        'status_key' => $status_key,
        'label' => $status_key,
        'color' => '#6c757d',
        'icon' => 'fa-tag',
        'label_value' => 0
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

function validate_status_transition($current_status, $new_status) {
    if (is_system_status($new_status)) return false;
    return validate_status_change($new_status);
}

// ==================================================
// DEFAULT LABEL FUNCTIONS
// ==================================================

/**
 * ✅ PATCHED: Hardcode return 'lbl_new_order'
 */
function get_new_status_key() {
    return 'lbl_new_order';
}

/**
 * ✅ NEW: Get completed status key
 */
function get_completed_status_key() {
    return 'lbl_completed';
}

// ==================================================
// LABEL MANAGEMENT (KHÔNG TRÙNG VỚI functions.php)
// ==================================================

/**
 * ✅ NEW: Get user labels for dropdown (exclude lbl_new_order, include lbl_completed)
 * Hàm này KHÔNG trùng với get_order_labels() trong functions.php
 */
function get_user_labels_for_dropdown() {
    return db_get_results("
        SELECT label_key, label_name, label_value, color, icon
        FROM order_labels 
        WHERE is_system = 0 OR label_key = 'lbl_completed'
        ORDER BY 
            CASE WHEN label_key = 'lbl_completed' THEN 999 ELSE sort_order END ASC
    ");
}

/**
 * Generate unique label key for admin-created labels
 */
function generate_label_key() {
    return 'lbl_' . time() . '_' . bin2hex(random_bytes(4));
}

// ==================================================
// ORDER COMPLETION CHECKS
// ==================================================

/**
 * ✅ NEW: Check if order is completed (based on label_value)
 */
function is_order_completed($order_id) {
    $label_value = db_get_var("
        SELECT ol.label_value
        FROM orders o
        JOIN order_labels ol ON o.primary_label = ol.label_key
        WHERE o.id = ?
    ", [$order_id]);
    
    return ($label_value == 1);
}

/**
 * ✅ NEW: Count orders by completion status
 */
function count_orders_by_completion($user_id = null) {
    $where = ['1=1'];
    $params = [];
    
    if ($user_id) {
        $where[] = 'o.assigned_to = ?';
        $params[] = $user_id;
    }
    
    $sql = "
        SELECT 
            SUM(CASE WHEN ol.label_value = 0 THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN ol.label_value = 1 THEN 1 ELSE 0 END) as completed_orders
        FROM orders o
        JOIN order_labels ol ON o.primary_label = ol.label_key
        WHERE " . implode(' AND ', $where);
    
    return db_get_row($sql, $params);
}
function format_status_badge($status_key) {
    if (empty($status_key)) {
        return '<span class="badge bg-secondary">N/A</span>';
    }
    
    // Get status info from order_labels table
    $status_info = get_status_info($status_key);
    
    if (!$status_info) {
        return '<span class="badge bg-secondary">' . htmlspecialchars($status_key) . '</span>';
    }
    
    $color = htmlspecialchars($status_info['color']);
    $icon = htmlspecialchars($status_info['icon']);
    $label = htmlspecialchars($status_info['label']);
    
    return sprintf(
        '<span class="badge" style="background-color: %s; color: #fff; text-shadow: 1px 1px 1px rgba(0,0,0,0.3);">
            <i class="fas %s"></i> %s
        </span>',
        $color,
        $icon,
        $label
    );
}
function get_status_options_with_labels() {
    return db_get_results("
        SELECT label_key, label_name, label_value, color, icon
        FROM order_labels 
        WHERE is_system = 0 OR label_key = 'lbl_completed'
        ORDER BY 
            CASE WHEN label_value = 1 THEN 999 ELSE sort_order END ASC
    ");
}