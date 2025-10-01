<?php
/**
 * Status Helper - CHỈ khai báo functions CHƯA có trong functions.php
 */

// get_order_status_configs() - ĐÃ CÓ trong functions.php line 532
// KHÔNG khai báo lại

// Function này orders.php cần ở dòng 58
if (!function_exists('get_all_statuses')) {
    function get_all_statuses() {
        $configs = get_order_status_configs(); // Dùng function có sẵn
        $statuses = [];
        foreach ($configs as $key => $config) {
            $statuses[$key] = $config['label'];
        }
        return $statuses;
    }
}

// Các functions khác CHỈ khai báo nếu CHƯA có
if (!function_exists('render_status_badge')) {
    function render_status_badge($status_key) {
        $configs = get_order_status_configs();
        if (isset($configs[$status_key])) {
            $config = $configs[$status_key];
            return sprintf(
                '<span class="badge" style="background-color: %s">%s</span>',
                $config['color'],
                htmlspecialchars($config['label'])
            );
        }
        return '<span class="badge bg-secondary">' . htmlspecialchars($status_key) . '</span>';
    }
}

if (!function_exists('render_status_options')) {
    function render_status_options($current_status = '') {
        $configs = get_order_status_configs();
        $html = '';
        foreach ($configs as $key => $config) {
            $selected = ($key == $current_status) ? 'selected' : '';
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                htmlspecialchars($key),
                $selected,
                htmlspecialchars($config['label'])
            );
        }
        return $html;
    }
}


// Get pending status keys (đang xử lý)
function get_pending_statuses() {
    return db_get_col(
        "SELECT status_key FROM order_status_configs 
         WHERE label NOT LIKE '%mới%' 
           AND label NOT LIKE '%hoàn%' 
           AND label NOT LIKE '%hủy%'"
    ) ?: [];
}


// Get confirmed status key
function get_confirmed_status() {
    $status = db_get_var("SELECT status_key FROM order_status_configs WHERE label LIKE '%xác nhận%' OR label LIKE '%hoàn%' LIMIT 1");
    return $status ?: db_get_var("SELECT status_key FROM order_status_configs ORDER BY sort_order DESC LIMIT 1");
}

// Get new status key  
function get_new_status_key() {
    $status = db_get_var("SELECT status_key FROM order_status_configs WHERE label LIKE '%mới%' LIMIT 1");
    return $status ?: db_get_var("SELECT status_key FROM order_status_configs ORDER BY sort_order ASC LIMIT 1");
}

// Get calling status key
function get_calling_status_key() {
    $status = db_get_var("SELECT status_key FROM order_status_configs WHERE label LIKE '%gọi%' LIMIT 1");
    return $status ?: get_new_status_key();
}

?>