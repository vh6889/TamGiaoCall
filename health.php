<?php
/**
 * Health Check Endpoint
 * For monitoring systems to check if the application is running
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

try {
    // Database check
    $pdo = get_db_connection();
    $result = $pdo->query("SELECT 1")->fetchColumn();
    $health['checks']['database'] = $result === 1 ? 'ok' : 'error';
    
    // Table existence check
    $tables = ['users', 'orders', 'order_notes'];
    foreach ($tables as $table) {
        $exists = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetchColumn();
        $health['checks']['table_' . $table] = $exists ? 'ok' : 'missing';
    }
    
    // Overall status
    $allOk = true;
    foreach ($health['checks'] as $check) {
        if ($check !== 'ok') {
            $allOk = false;
        }
    }
    
    $health['status'] = $allOk ? 'healthy' : 'degraded';
    http_response_code($allOk ? 200 : 503);
    
} catch (Exception $e) {
    $health['status'] = 'unhealthy';
    $health['error'] = 'System error occurred';
    http_response_code(503);
}

echo json_encode($health, JSON_PRETTY_PRINT);