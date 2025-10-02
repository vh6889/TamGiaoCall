<?php
/**
 * Simple Rule Handler for Order Detail Page
 * Xu ly rules va reminders don gian ma khong can RuleEngine phuc tap
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
 * Tra ve cac goi y dua tren business logic
 */
function get_order_suggestions($order) {
    $suggestions = [];
    
    // Rule 1: Don hang chua gan qua lau
    if (!$order['assigned_to'] && $order['created_at']) {
        $hours_since_created = (time() - strtotime($order['created_at'])) / 3600;
        if ($hours_since_created > 24) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => 'Don hang da ton tai hon 24 gio chua duoc xu ly'
            ];
        }
    }
    
    // Rule 2: Don hang da gan nhung chua goi
    if ($order['assigned_to'] && $order['call_count'] == 0) {
        $hours_since_assigned = (time() - strtotime($order['assigned_at'])) / 3600;
        if ($hours_since_assigned > 4) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => 'Da phan cong hon 4 gio nhung chua co cuoc goi nao'
            ];
        }
    }
    
    // Rule 3: Khach hang VIP (don hang cao gia tri)
    if ($order['total_amount'] > 5000000) { // > 5 trieu
        $suggestions[] = [
            'type' => 'info',
            'message' => 'Khach hang VIP - Don hang gia tri cao, can uu tien xu ly'
        ];
    }
    
    // Rule 4: Nhieu cuoc goi khong thanh cong
    if ($order['call_count'] >= 3) {
        $suggestions[] = [
            'type' => 'warning',
            'message' => 'Da goi ' . $order['call_count'] . ' lan, can nhac thay doi cach tiep can'
        ];
    }
    
    // Rule 5: Don hang callback da qua han
    if ($order['callback_time'] && $order['callback_time'] != '0000-00-00 00:00:00' && strtotime($order['callback_time']) < time()) {
        $suggestions[] = [
            'type' => 'danger',
            'message' => 'Da qua thoi gian hen goi lai!'
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
            'message' => "Khach hang cu - Da co $customer_orders don hang truoc day"
        ];
        
        // Check if customer has cancelled orders before
        $cancelled_orders = db_get_var(
			"SELECT COUNT(*) FROM orders 
			 WHERE customer_phone = ? 
			 AND primary_label IN (SELECT label_key FROM order_labels WHERE ...)  // ✅ Đổi thành 'primary_label'
			 AND id != ?",
			[$order['customer_phone'], $order['id']]
		);
        
        if ($cancelled_orders > 0) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => "Luu y: Khach da tu choi/huy $cancelled_orders don truoc day"
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
                'message' => 'San pham gia tri cao - Can xac nhan ky thong tin giao hang'
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
                'message' => 'Nho goi lai cho khach hang',
                'due_time' => date('Y-m-d H:i:s', strtotime('+2 hours')),
                'primary_label' => 'pending'
            ]);
        }
    }
    
    // Auto reminder for no_answer after multiple attempts
    $no_answer_statuses = db_get_col("SELECT label_key FROM order_labels WHERE label_name LIKE '%khong nghe%' OR label LIKE '%no_answer%'");
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
                'message' => 'Da goi nhieu lan khong duoc, can thay doi thoi gian goi',
                'due_time' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'primary_label' => 'pending'
            ]);
        }
    }
}

/**
 * Mark reminders as completed when order status changes
 */
function complete_order_reminders($order_id, $new_status) {
    // Get confirmed and cancelled statuses
    $confirmed_statuses = db_get_col("SELECT label_key FROM order_labels WHERE label_name LIKE '%xac nhan%' OR label LIKE '%hoan%' OR label LIKE '%thanh cong%'");
    $cancelled_statuses = db_get_col("SELECT label_key FROM order_labels WHERE label_name LIKE '%huy%' OR label LIKE '%rejected%' OR label LIKE '%bom%'");
    
    $final_statuses = array_merge($confirmed_statuses ?: [], $cancelled_statuses ?: []);
    
    // Complete reminders when order is finalized
    if (in_array($new_status, $final_statuses)) {
        db_update('reminders', 
            ['primary_label' => 'completed', 'completed_at' => date('Y-m-d H:i:s')],
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
                'message' => "Ban co $violations vi pham, hay can than trong xu ly don hang"
            ];
        }
    }
    
    // Check today's performance
    $today_orders = db_get_row(
    "SELECT COUNT(*) as total,
            SUM(CASE WHEN ol.label_name LIKE '%xac nhan%' OR ol.label_name LIKE '%hoan%' OR ol.label_name LIKE '%thanh cong%' THEN 1 ELSE 0 END) as confirmed
     FROM orders o
     LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
     WHERE o.assigned_to = ? 
     AND DATE(o.updated_at) = CURDATE()",
    [$user_id]
	);
    
    if ($today_orders && $today_orders['total'] > 10) {
        $success_rate = ($today_orders['confirmed'] / $today_orders['total']) * 100;
        if ($success_rate < 30) {
            $warnings[] = [
                'type' => 'warning', 
                'message' => 'Ty le chot don hom nay thap (' . round($success_rate) . '%), can cai thien'
            ];
        }
    }
    
    return $warnings;
}
