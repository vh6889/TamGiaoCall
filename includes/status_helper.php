<?php
/**
 * Helper Functions cho Dynamic Status System
 * Lưu file này vào: includes/status_helper.php
 */

// Lấy tất cả status từ database
function get_all_statuses() {
    return db_get_results(
        "SELECT status_key as value, label as text, color, icon, sort_order 
         FROM order_status_configs 
         ORDER BY sort_order"
    );
}

// Lấy thông tin 1 status
function get_status_info($status_key) {
    if (empty($status_key)) {
        return null;
    }
    
    return db_get_row(
        "SELECT * FROM order_status_configs WHERE status_key = ?",
        [$status_key]
    );
}

// Tạo HTML cho status badge
function render_status_badge($status_key) {
    $status = get_status_info($status_key);
    
    // Nếu không tìm thấy status, dùng default
    if (!$status) {
        return '<span class="badge bg-secondary">
                    <i class="fas fa-tag"></i> ' . htmlspecialchars($status_key) . '
                </span>';
    }
    
    // Tạo badge với màu và icon từ database
    return '<span class="badge" style="background-color: ' . htmlspecialchars($status['color']) . '">
                <i class="fas ' . htmlspecialchars($status['icon']) . '"></i> 
                ' . htmlspecialchars($status['label']) . '
            </span>';
}

// Tạo dropdown options cho select status
function render_status_options($selected = null) {
    $statuses = get_all_statuses();
    $html = '';
    
    foreach($statuses as $status) {
        $selected_attr = ($selected == $status['value']) ? 'selected' : '';
        $html .= '<option value="' . htmlspecialchars($status['value']) . '" 
                         data-color="' . htmlspecialchars($status['color']) . '"
                         data-icon="' . htmlspecialchars($status['icon']) . '" 
                         ' . $selected_attr . '>' 
                . htmlspecialchars($status['text']) 
                . '</option>';
    }
    
    return $html;
}

// Lấy default status (status đầu tiên) - SỬA db_get_value thành db_get_var
function get_default_status() {
    return db_get_var(  // ĐÃ SỬA
        "SELECT status_key FROM order_status_configs ORDER BY sort_order ASC LIMIT 1"
    );
}
?>