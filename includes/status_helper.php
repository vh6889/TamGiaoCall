<?php
/**
 * Status Helper - UPDATED FOR NEW LABEL LOGIC
 * Hệ thống MỚI: system_status (free/assigned) + primary_label (dynamic)
 */

if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

// =============================================
// SYSTEM STATUS FUNCTIONS (HARDCODED)
// =============================================

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

// =============================================
// LABEL FUNCTIONS (DYNAMIC FROM DATABASE)
// =============================================

/**
 * Lấy tất cả nhãn (backward compatible với tên cũ)
 */
function get_all_statuses() {
    return db_get_results("
        SELECT label_key AS status_key, 
               label_key AS value,
               label_name AS label, 
               label_name AS text,
               color, icon, sort_order, is_system, is_final
        FROM order_labels 
        WHERE is_system = 0
        ORDER BY sort_order ASC
    ");
}

/**
 * Get user-defined labels only (excluding system)
 */
function get_user_statuses() {
    return get_all_statuses();
}

/**
 * Get status options for select dropdown
 */
function get_status_options_with_labels() {
    return db_get_results("
        SELECT label_key AS status_key, 
               label_key AS value,
               label_name AS label,
               label_name AS text,
               color, icon
        FROM order_labels 
        WHERE is_system = 0 
        ORDER BY sort_order ASC
    ");
}

/**
 * Lấy thông tin 1 nhãn (backward compatible)
 */
function get_status_info($status_key) {
    $label = db_get_row("
        SELECT label_key AS status_key, 
               label_name AS label, 
               color, icon, is_final
        FROM order_labels 
        WHERE label_key = ?
    ", [$status_key]);
    
    if ($label) {
        return $label;
    }
    
    // Fallback for system statuses
    if ($status_key === 'free') {
        return [
            'status_key' => 'free',
            'label' => '[HỆ THỐNG] Chưa gán',
            'color' => '#6c757d',
            'icon' => 'fa-inbox',
            'is_final' => 0
        ];
    }
    
    if ($status_key === 'assigned') {
        return [
            'status_key' => 'assigned',
            'label' => '[HỆ THỐNG] Đã gán',
            'color' => '#17a2b8',
            'icon' => 'fa-user-check',
            'is_final' => 0
        ];
    }
    
    // Default fallback
    return [
        'status_key' => $status_key,
        'label' => $status_key,
        'color' => '#6c757d',
        'icon' => 'fa-tag',
        'is_final' => 0
    ];
}

// =============================================
// VALIDATION FUNCTIONS
// =============================================

/**
 * Check if label exists in database
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
    // Không cho phép user chọn status hệ thống
    if (is_system_status($new_status)) {
        return false;
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
    
    // Kiểm tra đơn có bị lock không (check ở ngoài hàm này)
    // Ở đây chỉ validate logic transition
    
    return validate_status_change($new_status);
}

// =============================================
// STATISTICS FUNCTIONS
// =============================================

/**
 * Get confirmed statuses (for statistics)
 */
function get_confirmed_statuses() {
    $statuses = db_get_col("
        SELECT label_key 
        FROM order_labels 
        WHERE is_system = 0 
          AND (label_name LIKE '%thành công%' 
               OR label_name LIKE '%hoàn thành%' 
               OR label_name LIKE '%completed%'
               OR label_name LIKE '%giao thành công%')
    ");
    
    return $statuses ?: [];
}

/**
 * Get cancelled statuses (for statistics)
 */
function get_cancelled_statuses() {
    $statuses = db_get_col("
        SELECT label_key 
        FROM order_labels 
        WHERE is_system = 0 
          AND (label_name LIKE '%hủy%' 
               OR label_name LIKE '%cancelled%' 
               OR label_name LIKE '%bom%'
               OR label_name LIKE '%từ chối%')
    ");
    
    return $statuses ?: [];
}

/**
 * Get "new" status key for reclaimed orders
 */
function get_new_status_key() {
    $status = db_get_var("
        SELECT label_key 
        FROM order_labels 
        WHERE label_name LIKE '%mới%' 
          OR label_name LIKE '%new%'
        ORDER BY sort_order 
        LIMIT 1
    ");
    
    return $status ?: 'n-a'; // Fallback
}

// =============================================
// DISPLAY FUNCTIONS
// =============================================

/**
 * Get status badge HTML (backward compatible)
 */
function get_status_badge($status_key) {
    $info = get_status_info($status_key);
    
    return format_status_badge(
        $status_key, 
        $info['label'], 
        $info['color'], 
        $info['icon']
    );
}

/**
 * Format status badge with custom parameters
 */
function format_status_badge($status_key, $label = null, $color = null, $icon = null) {
    if (!$label) {
        $info = get_status_info($status_key);
        $label = $info['label'];
        $color = $info['color'];
        $icon = $info['icon'];
    }
    
    return sprintf(
        '<span class="badge" style="background-color: %s; color: #fff; text-shadow: 1px 1px 1px rgba(0,0,0,0.3);">
            <i class="fas %s me-1"></i> %s
        </span>',
        htmlspecialchars($color),
        htmlspecialchars($icon),
        htmlspecialchars($label)
    );
}

/**
 * Get status color
 */
function get_status_color($status_key) {
    $info = get_status_info($status_key);
    return $info['color'];
}

/**
 * Get status icon
 */
function get_status_icon($status_key) {
    $info = get_status_info($status_key);
    return $info['icon'];
}

/**
 * Get status label text
 */
function get_status_label($status_key) {
    $info = get_status_info($status_key);
    return $info['label'];
}
