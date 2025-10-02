<?php
/**
 * Status Helper Functions
 * Xử lý dynamic status/labels
 */

if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Get all user-defined statuses (không bao gồm system statuses)
 */
function get_all_statuses() {
    return db_get_results(
        "SELECT label_key as value, 
                label_name as text, 
                color, 
                icon, 
                core_status
         FROM order_labels 
         WHERE is_system = 0 OR is_default = 1
         ORDER BY sort_order ASC, label_name ASC"
    );
}

/**
 * Get system statuses only
 */
function get_system_statuses() {
    return db_get_results(
        "SELECT label_key as value, 
                label_name as text, 
                color, 
                icon, 
                core_status
         FROM order_labels 
         WHERE is_system = 1
         ORDER BY sort_order ASC"
    );
}

/**
 * Format status badge HTML
 */
function format_status_badge($status_key) {
    if (empty($status_key)) {
        return '<span class="badge bg-secondary">N/A</span>';
    }
    
    $status = db_get_row(
        "SELECT * FROM order_labels WHERE label_key = ?",
        [$status_key]
    );
    
    if (!$status) {
        return '<span class="badge bg-secondary">' . htmlspecialchars($status_key) . '</span>';
    }
    
    $color = $status['color'] ?? '#6c757d';
    $icon = $status['icon'] ?? 'fa-circle';
    $name = $status['label_name'] ?? $status_key;
    
    return sprintf(
        '<span class="badge" style="background-color: %s">
            <i class="fas %s"></i> %s
        </span>',
        htmlspecialchars($color),
        htmlspecialchars($icon),
        htmlspecialchars($name)
    );
}

/**
 * Get status info
 */
function get_status_info($status_key) {
    return db_get_row(
        "SELECT * FROM order_labels WHERE label_key = ?",
        [$status_key]
    );
}

/**
 * Check if status is final (success or failed)
 */
function is_final_status($status_key) {
    $status = get_status_info($status_key);
    if (!$status) return false;
    
    return in_array($status['core_status'], ['success', 'failed']);
}

/**
 * Check if status is system status
 */
function is_system_status($status_key) {
    $status = get_status_info($status_key);
    return $status && $status['is_system'] == 1;
}

/**
 * Get default new order status
 */
function get_default_new_status() {
    return 'lbl_new_order';
}

/**
 * Get default processing status
 */
function get_default_processing_status() {
    return 'lbl_processing';
}

/**
 * Create new custom status
 */
function create_custom_status($data) {
    // Generate unique key
    $label_key = 'lbl_' . uniqid();
    
    // Insert new status
    return db_insert('order_labels', [
        'label_key' => $label_key,
        'label_name' => $data['label_name'],
        'core_status' => $data['core_status'] ?? 'processing',
        'color' => $data['color'] ?? '#6c757d',
        'icon' => $data['icon'] ?? 'fa-circle',
        'sort_order' => $data['sort_order'] ?? 100,
        'is_system' => 0,
        'is_default' => 0,
        'description' => $data['description'] ?? null,
        'created_by' => get_logged_user()['id'] ?? null,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Update existing status
 */
function update_status($label_key, $data) {
    // Cannot update system status
    if (is_system_status($label_key)) {
        return false;
    }
    
    $update_data = [];
    
    if (isset($data['label_name'])) {
        $update_data['label_name'] = $data['label_name'];
    }
    
    if (isset($data['color'])) {
        $update_data['color'] = $data['color'];
    }
    
    if (isset($data['icon'])) {
        $update_data['icon'] = $data['icon'];
    }
    
    if (isset($data['sort_order'])) {
        $update_data['sort_order'] = $data['sort_order'];
    }
    
    if (isset($data['description'])) {
        $update_data['description'] = $data['description'];
    }
    
    $update_data['updated_at'] = date('Y-m-d H:i:s');
    
    return db_update('order_labels', $update_data, 'label_key = ?', [$label_key]);
}

/**
 * Delete custom status
 */
function delete_status($label_key) {
    // Cannot delete system status
    if (is_system_status($label_key)) {
        return false;
    }
    
    // Check if any orders are using this status
    $count = db_get_var(
        "SELECT COUNT(*) FROM orders WHERE primary_label = ?",
        [$label_key]
    );
    
    if ($count > 0) {
        return false; // Cannot delete - in use
    }
    
    return db_delete('order_labels', 'label_key = ?', [$label_key]);
}

/**
 * Get status statistics
 */
function get_status_stats($user_id = null) {
    $where = $user_id ? "WHERE o.assigned_to = ?" : "";
    $params = $user_id ? [$user_id] : [];
    
    $sql = "SELECT ol.label_key, ol.label_name, ol.color, ol.icon,
                   COUNT(o.id) as order_count
            FROM order_labels ol
            LEFT JOIN orders o ON ol.label_key = o.primary_label $where
            GROUP BY ol.label_key
            ORDER BY ol.sort_order";
    
    return db_get_results($sql, $params);
}

/**
 * Migrate old status to new label system (utility function)
 */
function migrate_old_status_to_label($old_status) {
    // Mapping cũ sang mới
    $status_map = [
        'new' => 'lbl_new_order',
        'processing' => 'lbl_processing',
        'confirmed' => 'lbl_confirmed',
        'callback' => 'lbl_callback',
        'completed' => 'lbl_completed',
        'cancelled' => 'lbl_cancelled'
    ];
    
    return $status_map[$old_status] ?? 'lbl_processing';
}