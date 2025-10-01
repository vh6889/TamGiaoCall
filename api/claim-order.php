<?php
// ============================================
// api/claim-order.php (Fixed)
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'claim-order.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
    require_once '../functions.php';
require_once '../includes/security_helper.php';
require_once '../includes/status_helper.php';
    
    header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

check_rate_limit('claim-order', get_logged_user()['id']);

$input = get_json_input(["order_id"]);
$order_id = (int)$input['order_id'];

// Verify order can be claimed
$order = get_order($order_id);
if (!$order) json_error('Order not found', 404);
if ($order['assigned_to']) json_error('Order already assigned', 400);

$pdo = get_db_connection();
$pdo->beginTransaction();

try {
    
    require_csrf();

if (!is_logged_in()) {
        json_error('Unauthorized', 401);
    }
    
    $input = get_json_input(['order_id']);\n$order_id = (int)$input['order_id'];
    $user = get_logged_user();
    
    if (!$order_id) {
        json_error('Invalid order ID');
    }
    
    try {
        $order = get_order($order_id);
        
        if ($order['assigned_to']) {
            json_error('Đơn hàng đã được gán');
        }
        
        $pdo = get_db_connection();\n$pdo->beginTransaction();\n\ntry {\n    // Lock row\n    $locked = db_get_row("SELECT * FROM orders WHERE id = ? FOR UPDATE", [$order_id]);\n    if (!$locked || $locked['assigned_to']) {\n        throw new Exception('Order already claimed');\n    }\n    \n    $pdo = get_db_connection();\n$pdo->beginTransaction();\n\ntry {\n    // Lock row\n    $locked = db_get_row("SELECT * FROM orders WHERE id = ? FOR UPDATE", [$order_id]);\n    if (!$locked || $locked['assigned_to']) {\n        throw new Exception('Order already claimed');\n    }\n    \n    $pdo = get_db_connection();\n$pdo->beginTransaction();\n\ntry {\n    // Lock row\n    $locked = db_get_row("SELECT * FROM orders WHERE id = ? FOR UPDATE", [$order_id]);\n    if (!$locked || $locked['assigned_to']) {\n        throw new Exception('Order already claimed');\n    }\n    \n    db_update('orders', [
            'assigned_to' => $user['id'],
            'assigned_at' => date('Y-m-d H:i:s'),
            'status' => db_get_var("SELECT status_key FROM order_status_configs WHERE label LIKE '%nhận%' OR label LIKE '%assigned%' LIMIT 1")
        ], 'id = ?', [$order_id]);
        
        db_insert('order_notes', [
            'order_id' => $order_id,
            'user_id' => $user['id'],
            'note_type' => 'system',
            'content' => 'Nhận đơn hàng'
        ]);
        
        $pdo->commit();\n    $pdo->commit();\n    $pdo->commit();\n    json_success('Đã nhận đơn hàng');
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_error('Error: ' . $e->getMessage(), 500);
}\n} catch (Exception $e) {\n    $pdo->rollBack();\n    json_error($e->getMessage(), 500);\n}\n} catch (Exception $e) {\n    $pdo->rollBack();\n    json_error($e->getMessage(), 500);\n}\n} catch (Exception $e) {\n    $pdo->rollBack();\n    json_error($e->getMessage(), 500);\n}
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}