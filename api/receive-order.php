<?php
/**
 * API: Receive Order from WooCommerce
 * This endpoint receives new orders from WooCommerce webhook
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../functions.php';

header('Content-Type: application/json');

// Verify API Key
$headers = getallheaders();
$api_key = $headers['X-API-Key'] ?? $headers['X-Api-Key'] ?? '';

if ($api_key !== API_SECRET_KEY) {
    json_error('Unauthorized', 401);
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    json_error('Invalid JSON data');
}

// Validate required fields
$required = ['woo_order_id', 'order_number', 'customer_name', 'customer_phone', 'total_amount'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        json_error("Missing required field: {$field}");
    }
}

// Check if order already exists
$existing = db_get_row(
    "SELECT id FROM orders WHERE woo_order_id = ?",
    [$data['woo_order_id']]
);

if ($existing) {
    json_error('Order already exists', 409);
}

try {
    // Prepare order data
    $order_data = [
        'woo_order_id' => $data['woo_order_id'],
        'order_number' => $data['order_number'],
        'customer_name' => $data['customer_name'],
        'customer_phone' => $data['customer_phone'],
        'customer_email' => $data['customer_email'] ?? null,
        'customer_address' => $data['customer_address'] ?? null,
        'customer_city' => $data['customer_city'] ?? null,
        'customer_notes' => $data['customer_notes'] ?? null,
        'total_amount' => $data['total_amount'],
        'currency' => $data['currency'] ?? 'VND',
        'payment_method' => $data['payment_method'] ?? null,
        'products' => json_encode($data['products'] ?? []),
        'status' => 'new',
        'woo_created_at' => $data['woo_created_at'] ?? date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Insert order
    $order_id = db_insert('orders', $order_data);
    
    // Log activity
    log_activity('receive_order', "Received order #{$data['order_number']} from WooCommerce", 'order', $order_id);
    
    json_success('Order received successfully', ['order_id' => $order_id]);
    
} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}