<?php
/**
 * Debug Check - Kiểm tra các functions và tables thiếu
 * Upload file này lên root, truy cập: http://yoursite.com/debug-check.php
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/status_helper.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Check</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .check { padding: 10px; margin: 5px 0; border-radius: 4px; }
        .ok { background: #d4edda; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        pre { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Debug Check - Telesale Manager System</h1>
    
    <h2>1. Kiểm tra Database Tables</h2>
    <?php
    $required_tables = ['users', 'orders', 'order_notes', 'order_status_configs', 
                        'call_logs', 'reminders', 'activity_logs'];
    
    $pdo = get_db_connection();
    foreach ($required_tables as $table) {
        $exists = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($exists) {
            echo "<div class='check ok'>✅ Bảng <strong>$table</strong> tồn tại</div>";
        } else {
            echo "<div class='check error'>❌ Bảng <strong>$table</strong> THIẾU - Cần tạo ngay!</div>";
        }
    }
    ?>
    
    <h2>2. Kiểm tra Functions PHP</h2>
    <?php
    $required_functions = [
        'get_order' => 'functions.php',
        'get_telesales' => 'functions.php',
        'get_order_reminders' => 'simple-rule-handler.php',
        'get_order_suggestions' => 'simple-rule-handler.php',
        'get_status_options_with_labels' => 'functions.php',
        'get_new_status_key' => 'includes/status_helper.php',
        'get_calling_status_key' => 'includes/status_helper.php',
        'get_confirmed_statuses' => 'includes/status_helper.php'
    ];
    
    foreach ($required_functions as $func => $file) {
        if (function_exists($func)) {
            echo "<div class='check ok'>✅ Function <strong>$func()</strong> tồn tại</div>";
        } else {
            echo "<div class='check error'>❌ Function <strong>$func()</strong> THIẾU trong $file</div>";
        }
    }
    ?>
    
    <h2>3. Test Functions</h2>
    
    <?php
    // Test get_telesales
    echo "<h3>Test get_telesales():</h3>";
    try {
        $telesales = get_telesales('active');
        if (is_array($telesales)) {
            echo "<div class='check ok'>✅ get_telesales() hoạt động - Trả về " . count($telesales) . " users</div>";
            if (!empty($telesales)) {
                echo "<pre>" . print_r($telesales[0], true) . "</pre>";
            }
        } else {
            echo "<div class='check error'>❌ get_telesales() trả về không phải array</div>";
        }
    } catch (Exception $e) {
        echo "<div class='check error'>❌ get_telesales() LỖI: " . $e->getMessage() . "</div>";
    }
    
    // Test get_order
    echo "<h3>Test get_order():</h3>";
    try {
        $orders = db_get_results("SELECT id FROM orders LIMIT 1");
        if (!empty($orders)) {
            $order_id = $orders[0]['id'];
            $order = get_order($order_id);
            if (is_array($order)) {
                echo "<div class='check ok'>✅ get_order($order_id) hoạt động</div>";
                echo "<pre>" . print_r($order, true) . "</pre>";
            } else {
                echo "<div class='check error'>❌ get_order() trả về không phải array</div>";
            }
        } else {
            echo "<div class='check warning'>⚠️ Không có đơn hàng nào để test</div>";
        }
    } catch (Exception $e) {
        echo "<div class='check error'>❌ get_order() LỖI: " . $e->getMessage() . "</div>";
    }
    
    // Test get_status_options_with_labels
    echo "<h3>Test get_status_options_with_labels():</h3>";
    try {
        $statuses = get_status_options_with_labels();
        if (is_array($statuses)) {
            echo "<div class='check ok'>✅ get_status_options_with_labels() hoạt động - Trả về " . count($statuses) . " statuses</div>";
            if (!empty($statuses)) {
                echo "<pre>" . print_r($statuses, true) . "</pre>";
            }
        } else {
            echo "<div class='check error'>❌ get_status_options_with_labels() trả về không phải array</div>";
        }
    } catch (Exception $e) {
        echo "<div class='check error'>❌ get_status_options_with_labels() LỖI: " . $e->getMessage() . "</div>";
    }
    ?>
    
    <h2>4. Kiểm tra File Tồn Tại</h2>
    <?php
    $required_files = [
        'config.php',
        'functions.php',
        'includes/status_helper.php',
        'simple-rule-handler.php',
        'api/claim-order.php',
        'api/start-call.php',
        'api/end-call.php',
        'api/unassign-order.php'
    ];
    
    foreach ($required_files as $file) {
        if (file_exists($file)) {
            echo "<div class='check ok'>✅ File <strong>$file</strong> tồn tại</div>";
        } else {
            echo "<div class='check error'>❌ File <strong>$file</strong> THIẾU</div>";
        }
    }
    ?>
    
    <h2>5. Kiểm tra Order Status Configs</h2>
    <?php
    try {
        $statuses = db_get_results("SELECT * FROM order_status_configs ORDER BY sort_order");
        if (empty($statuses)) {
            echo "<div class='check error'>❌ Bảng order_status_configs RỖNG - Cần import dữ liệu!</div>";
        } else {
            echo "<div class='check ok'>✅ Có " . count($statuses) . " trạng thái trong order_status_configs</div>";
            echo "<pre>" . print_r($statuses, true) . "</pre>";
        }
    } catch (Exception $e) {
        echo "<div class='check error'>❌ Lỗi query order_status_configs: " . $e->getMessage() . "</div>";
    }
    ?>
    
    <h2>6. PHP Error Log (10 dòng cuối)</h2>
    <?php
    $error_log = ini_get('error_log');
    if ($error_log && file_exists($error_log)) {
        $lines = file($error_log);
        $last_10 = array_slice($lines, -10);
        echo "<pre>" . htmlspecialchars(implode('', $last_10)) . "</pre>";
    } else {
        echo "<div class='check warning'>⚠️ Không tìm thấy error log</div>";
    }
    ?>
    
    <hr>
    <p><strong>Hướng dẫn:</strong></p>
    <ul>
        <li>Nếu có bảng THIẾU → Chạy file SQL migration tương ứng</li>
        <li>Nếu có function THIẾU → Kiểm tra file có được require đúng không</li>
        <li>Nếu order_status_configs RỖNG → Import dữ liệu từ file SQL gốc</li>
        <li>Xem PHP Error Log để biết lỗi chi tiết</li>
    </ul>
    
    <p><em>Sau khi fix xong, XÓA file debug-check.php này đi vì lý do bảo mật!</em></p>
</body>
</html>