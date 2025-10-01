<?php
/**
 * Simple Rule Handler for Order Detail Page
 * Xử lý rules và reminders đơn giản mà không cần RuleEngine phức tạp
 */

if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Get applicable reminders for order
 */
function get_order_reminders($order_id) {
    return db_get_results(
        "SELECT * FROM reminders 
         WHERE order_id = ? AND status = 'pending' 
         ORDER BY due_time ASC", 
        [$order_id]
    );
}

/**
 * Get business rules suggestions for order
 * Trả về các gợi ý dựa trên business logic
 */
function get_order_suggestions($order) {
    $suggestions = [];
    
    // Rule 1: Đơn hàng chưa gán quá lâu
    if (!$order['assigned_to'] && $order['created_at']) {
        $hours_since_created = (time() - strtotime($order['created_at'])) / 3600;
        if ($hours_since_created > 24) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => 'Đơn hàng đã tồn tại hơn 24 giờ chưa được xử lý'
            ];
        }
    }
    
    // Rule 2: Đơn hàng đã gán nhưng chưa gọi
    if ($order['assigned_to'] && $order['call_count'] == 0) {
        $hours_since_assigned = (time() - strtotime($order['assigned_at'])) / 3600;
        if ($hours_since_assigned > 4) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => 'Đã phân công hơn 4 giờ nhưng chưa có cuộc gọi nào'
            ];
        }
    }
    
    // Rule 3: Khách hàng VIP (đơn hàng cao giá trị)
    if ($order['total_amount'] > 5000000) { // > 5 triệu
        $suggestions[] = [
            'type' => 'info',
            'message' => 'Khách hàng VIP - Đơn hàng giá trị cao, cần ưu tiên xử lý'
        ];
    }
    
    // Rule 4: Nhiều cuộc gọi không thành công
    if ($order['call_count'] >= 3) {
        $suggestions[] = [
            'type' => 'warning',
            'message' => 'Đã gọi ' . $order['call_count'] . ' lần, cân nhắc thay đổi cách tiếp cận'
        ];
    }
    
    // Rule 5: Đơn hàng callback đã quá hạn
    if ($order['callback_time'] && $order['callback_time'] != '0000-00-00 00:00:00' && strtotime($order['callback_time']) < time()) {
        $suggestions[] = [
            'type' => 'danger',
            'message' => 'Đã quá thời gian hẹn gọi lại!'
        ];
    }
    
    // Rule 6: Check customer history
    $customer_orders = db_get_var(
        "SELECT COUNT(*) FROM orders WHERE customer_phone = ? AND id != ?",
        [$order['customer_phone'], $order['id']]
    );
    
    if ($customer_orders > 0) {
        $suggestions[] = [
            'type' => 'info',
            'message' => "Khách hàng cũ - Đã có $customer_orders đơn hàng trước đây"
        ];
        
        // Check if customer has cancelled orders before
        $cancelled_orders = db_get_var(
            "SELECT COUNT(*) FROM orders 
             WHERE customer_phone = ? 
             AND status IN (SELECT status_key FROM order_status_configs WHERE label LIKE '%hủy%' OR label LIKE '%rejected%' OR label LIKE '%bom%') 
             AND id != ?",
            [$order['customer_phone'], $order['id']]
        );
        
        if ($cancelled_orders > 0) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => "Lưu ý: Khách đã từ chối/hủy $cancelled_orders đơn trước đây"
            ];
        }
    }
    
    // Rule 7: Check product-specific rules (if needed)
    $products = json_decode($order['products'], true) ?? [];
    foreach ($products as $product) {
        // Example: High-value products need special handling
        if (isset($product['price']) && $product['price'] > 10000000) {
            $suggestions[] = [
                'type' => 'info',
                'message' => 'Sản phẩm giá trị cao - Cần xác nhận kỹ thông tin giao hàng'
            ];
            break;
        }
    }
    
    return $suggestions;
}

/**
 * Check and create automatic reminders based on order status
 */
function create_auto_reminders($order_id, $order_status, $user_id) {
    // Auto create reminder for callback status
    if (in_array($order_status, ['callback', 'goi-lai', 'hen-goi-lai'])) {
        $existing = db_get_var(
            "SELECT COUNT(*) FROM reminders 
             WHERE order_id = ? AND type = 'callback' AND status = 'pending'",
            [$order_id]
        );
        
        if (!$existing) {
            db_insert('reminders', [
                'order_id' => $order_id,
                'user_id' => $user_id,
                'type' => 'callback',
                'message' => 'Nhớ gọi lại cho khách hàng',
                'due_time' => date('Y-m-d H:i:s', strtotime('+2 hours')),
                'status' => 'pending'
            ]);
        }
    }
    
    // Auto reminder for no_answer after multiple attempts
    $no_answer_statuses = db_get_col("SELECT status_key FROM order_status_configs WHERE label LIKE '%không nghe%' OR label LIKE '%no_answer%'");
    if (!empty($no_answer_statuses) && in_array($order_status, $no_answer_statuses)) {
        $call_count = db_get_var(
            "SELECT call_count FROM orders WHERE id = ?",
            [$order_id]
        );
        
        if ($call_count >= 3) {
            db_insert('reminders', [
                'order_id' => $order_id,
                'user_id' => $user_id,
                'type' => 'follow_up',
                'message' => 'Đã gọi nhiều lần không được, cần thay đổi thời gian gọi',
                'due_time' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'status' => 'pending'
            ]);
        }
    }
}

/**
 * Mark reminders as completed when order status changes
 */
function complete_order_reminders($order_id, $new_status) {
    // Get confirmed and cancelled statuses
    $confirmed_statuses = db_get_col("SELECT status_key FROM order_status_configs WHERE label LIKE '%xác nhận%' OR label LIKE '%hoàn%' OR label LIKE '%thành công%'");
    $cancelled_statuses = db_get_col("SELECT status_key FROM order_status_configs WHERE label LIKE '%hủy%' OR label LIKE '%rejected%' OR label LIKE '%bom%'");
    
    $final_statuses = array_merge($confirmed_statuses ?: [], $cancelled_statuses ?: []);
    
    // Complete reminders when order is finalized
    if (in_array($new_status, $final_statuses)) {
        db_update('reminders', 
            ['status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')],
            'order_id = ? AND status = ?',
            [$order_id, 'pending']
        );
    }
}

/**
 * Get warnings for employee based on performance
 */
function get_employee_warnings($user_id) {
    $warnings = [];
    
    // Check if employee_performance table exists
    $table_exists = db_get_var("SHOW TABLES LIKE 'employee_performance'");
    
    if ($table_exists) {
        // Check violation count
        $violations = db_get_var(
            "SELECT violation_count FROM employee_performance WHERE user_id = ?",
            [$user_id]
        );
        
        if ($violations && $violations > 0) {
            $warnings[] = [
                'type' => 'warning',
                'message' => "Bạn có $violations vi phạm, hãy cẩn thận trong xử lý đơn hàng"
            ];
        }
    }
    
    // Check today's performance
    $today_orders = db_get_row(
        "SELECT COUNT(*) as total,
                SUM(CASE WHEN osc.label LIKE '%xác nhận%' OR osc.label LIKE '%hoàn%' OR osc.label LIKE '%thành công%' THEN 1 ELSE 0 END) as confirmed
         FROM orders o
         LEFT JOIN order_status_configs osc ON o.status = osc.status_key
         WHERE o.assigned_to = ? 
         AND DATE(o.updated_at) = CURDATE()",
        [$user_id]
    );
    
    if ($today_orders && $today_orders['total'] > 10) {
        $success_rate = ($today_orders['confirmed'] / $today_orders['total']) * 100;
        if ($success_rate < 30) {
            $warnings[] = [
                'type' => 'warning', 
                'message' => 'Tỷ lệ chốt đơn hôm nay thấp (' . round($success_rate) . '%), cần cải thiện'
            ];
        }
    }
    
    return $warnings;
}
