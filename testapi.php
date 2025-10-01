<?php
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/security_helper.php';

header('Content-Type: application/json');

// Test claim order API
$_POST = json_decode('{"csrf_token":"test","order_id":22}', true);
$_SESSION['user_id'] = 1; // Fake login as admin

try {
    // Simulate claim-order.php logic
    $order_id = 22;
    
    $order = db_get_row("SELECT * FROM orders WHERE id = ?", [$order_id]);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    if ($order['assigned_to']) {
        throw new Exception('Order already assigned');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'API test works!',
        'order' => $order
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}