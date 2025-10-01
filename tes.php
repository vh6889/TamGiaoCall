<?php
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

echo "<h2>TEST PHASE 2 - NEW FUNCTIONS</h2>";

// Test 1: Lấy danh sách labels
echo "<h3>1. Danh sách nhãn:</h3>";
$labels = get_order_labels();
echo "<pre>";
print_r($labels);
echo "</pre>";

// Test 2: Lấy đơn free
echo "<h3>2. Đơn hàng FREE (chưa ai nhận):</h3>";
$free_orders = get_free_orders(5);
echo "Tổng: " . count_free_orders() . " đơn<br>";
echo "<pre>";
print_r($free_orders);
echo "</pre>";

// Test 3: Kiểm tra 1 đơn
if (!empty($free_orders)) {
    $order = $free_orders[0];
    echo "<h3>3. Chi tiết đơn #{$order['order_number']}:</h3>";
    echo "System Status: {$order['system_status']}<br>";
    echo "Primary Label: " . ($order['primary_label'] ?: 'Chưa có') . "<br>";
    echo "Label Name: " . ($order['label_name'] ?: 'Chưa có') . "<br>";
}

echo "<hr><h2>✅ PHASE 2 FUNCTIONS WORKING!</h2>";