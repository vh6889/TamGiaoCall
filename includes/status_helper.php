<?php
if (!defined('TSM_ACCESS')) die('Direct access not allowed');

// SYSTEM STATUS
function get_free_status_key() {
    return 'free';
}

function get_assigned_status_key() {
    return 'assigned';
}

function is_system_status($status_key) {
    return in_array($status_key, ['free', 'assigned']);
}

// LABEL FUNCTIONS
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

function get_user_statuses() {
    return get_all_statuses();
}

function get_status_info($status_key) {
    $label = db_get_row("
        SELECT label_key AS status_key, 
               label_name AS label, 
               color, icon, label_value
        FROM order_labels 
        WHERE label_key = ?
    ", [$status_key]);
    
    if ($label) return $label;
    
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
    
    return [
        'status_key' => $status_key,
        'label' => $status_key,
        'color' => '#6c757d',
        'icon' => 'fa-tag',
        'label_value' => 0
    ];
}

// VALIDATION
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

// GET DEFAULT LABEL
function get_new_status_key() {
    // Lấy label "Đơn mới" (label_value = 0, is_system = 1)
    $status = db_get_var("
        SELECT label_key 
        FROM order_labels 
        WHERE is_system = 1 AND label_value = 0
        LIMIT 1
    ");
    
    return $status ?: 'lbl_new_order';
}

// DISPLAY
function get_status_badge($status_key) {
    $info = get_status_info($status_key);
    return format_status_badge($status_key, $info['label'], $info['color'], $info['icon']);
}

function format_status_badge($status_key, $label = null, $color = null, $icon = null) {
    if (!$label) {
        $info = get_status_info($status_key);
        $label = $info['label'];
        $color = $info['color'];
        $icon = $info['icon'];
    }
    
    return sprintf(
        '<span class="badge" style="background-color: %s; color: #fff;">
            <i class="fas %s me-1"></i> %s
        </span>',
        htmlspecialchars($color),
        htmlspecialchars($icon),
        htmlspecialchars($label)
    );
}