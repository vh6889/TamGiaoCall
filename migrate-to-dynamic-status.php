<?php
/**
 * SCRIPT MIGRATE TOÀN BỘ HỆ THỐNG SANG STATUS ĐỘNG
 * Chạy 1 lần để cập nhật tất cả files
 */

define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

// Kiểm tra quyền admin
require_admin();

echo '<!DOCTYPE html>
<html>
<head>
    <title>Migrate to Dynamic Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>🔄 Migrate System to Dynamic Status</h2>
    <hr>';

// 1. KIỂM TRA STATUS_HELPER.PHP
echo '<div class="alert alert-info">
    <h5>Bước 1: Kiểm tra file status_helper.php</h5>';

if (!file_exists('includes/status_helper.php')) {
    echo '<span class="text-danger">❌ Chưa có file includes/status_helper.php</span><br>';
    echo 'Đang tạo file...<br>';
    
    $helper_content = '<?php
/**
 * Helper Functions cho Dynamic Status System
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
    
    if (!$status) {
        return \'<span class="badge bg-secondary">
                    <i class="fas fa-tag"></i> \' . htmlspecialchars($status_key) . \'
                </span>\';
    }
    
    return \'<span class="badge" style="background-color: \' . htmlspecialchars($status[\'color\']) . \'">
                <i class="fas \' . htmlspecialchars($status[\'icon\']) . \'"></i> 
                \' . htmlspecialchars($status[\'label\']) . \'
            </span>\';
}

// Tạo dropdown options cho select status
function render_status_options($selected = null) {
    $statuses = get_all_statuses();
    $html = \'\';
    
    foreach($statuses as $status) {
        $selected_attr = ($selected == $status[\'value\']) ? \'selected\' : \'\';
        $html .= \'<option value="\' . htmlspecialchars($status[\'value\']) . \'" 
                         data-color="\' . htmlspecialchars($status[\'color\']) . \'"
                         data-icon="\' . htmlspecialchars($status[\'icon\']) . \'" 
                         \' . $selected_attr . \'>\' 
                . htmlspecialchars($status[\'text\']) 
                . \'</option>\';
    }
    
    return $html;
}

// Lấy default status (status đầu tiên)
function get_default_status() {
    return db_get_var(
        "SELECT status_key FROM order_status_configs ORDER BY sort_order ASC LIMIT 1"
    );
}
?>';
    
    file_put_contents('includes/status_helper.php', $helper_content);
    echo '<span class="text-success">✅ Đã tạo file includes/status_helper.php</span><br>';
} else {
    echo '<span class="text-success">✅ File includes/status_helper.php đã tồn tại</span><br>';
}
echo '</div>';

// 2. CẬP NHẬT CÁC FILE API
echo '<div class="alert alert-warning">
    <h5>Bước 2: Cập nhật các file API</h5>';

$api_files = [
    'api/claim-order.php' => [
        ['find' => "'status' => 'assigned'", 'replace' => "'status' => get_default_status()"]
    ],
    'api/approve-order.php' => [
        ['find' => "'status' => 'new'", 'replace' => "'status' => get_default_status()"],
        ['find' => "'status' => 'cancelled'", 'replace' => "'status' => db_get_var(\"SELECT status_key FROM order_status_configs WHERE label LIKE '%hủy%' LIMIT 1\") ?: 'cancelled'"]
    ],
    'api/submit-manual-order.php' => [
        ['find' => "'status' => 'pending_approval'", 'replace' => "'status' => db_get_var(\"SELECT status_key FROM order_status_configs WHERE label LIKE '%duyệt%' LIMIT 1\") ?: 'pending_approval'"]
    ]
];

foreach ($api_files as $file => $replacements) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $original = $content;
        
        // Thêm require status_helper nếu chưa có
        if (strpos($content, 'status_helper.php') === false) {
            $content = str_replace(
                "require_once '../functions.php';",
                "require_once '../functions.php';\nrequire_once '../includes/status_helper.php';",
                $content
            );
        }
        
        // Thực hiện replacements
        foreach ($replacements as $rep) {
            $content = str_replace($rep['find'], $rep['replace'], $content);
        }
        
        if ($content !== $original) {
            file_put_contents($file, $content);
            echo "✅ Đã cập nhật: $file<br>";
        } else {
            echo "⚠️ Không thay đổi: $file<br>";
        }
    } else {
        echo "❌ Không tìm thấy: $file<br>";
    }
}
echo '</div>';

// 3. CẬP NHẬT DASHBOARD.PHP
echo '<div class="alert alert-success">
    <h5>Bước 3: Cập nhật dashboard.php</h5>';

if (file_exists('dashboard.php')) {
    $content = file_get_contents('dashboard.php');
    $original = $content;
    
    // Thêm require status_helper
    if (strpos($content, 'status_helper.php') === false) {
        $content = str_replace(
            "require_once 'functions.php';",
            "require_once 'functions.php';\nrequire_once 'includes/status_helper.php';",
            $content
        );
    }
    
    // Thay get_status_badge thành render_status_badge
    $content = str_replace('get_status_badge(', 'render_status_badge(', $content);
    
    if ($content !== $original) {
        file_put_contents('dashboard.php', $content);
        echo '✅ Đã cập nhật dashboard.php<br>';
    } else {
        echo '⚠️ Dashboard.php không cần thay đổi<br>';
    }
} else {
    echo '❌ Không tìm thấy dashboard.php<br>';
}
echo '</div>';

// 4. CẬP NHẬT ORDER-DETAIL.PHP
echo '<div class="alert alert-primary">
    <h5>Bước 4: Cập nhật order-detail.php</h5>';

if (file_exists('order-detail.php')) {
    $content = file_get_contents('order-detail.php');
    $original = $content;
    
    // Thêm require status_helper
    if (strpos($content, 'status_helper.php') === false) {
        $content = str_replace(
            "require_once 'functions.php';",
            "require_once 'functions.php';\nrequire_once 'includes/status_helper.php';",
            $content
        );
    }
    
    // Thay get_status_badge thành render_status_badge
    $content = str_replace('get_status_badge(', 'render_status_badge(', $content);
    
    // Tìm và thay dropdown status
    if (strpos($content, 'render_status_options') === false) {
        // Tìm select status cũ và thay bằng dynamic
        $old_select = '/<select[^>]*id=["\']orderStatus["\'][^>]*>.*?<\/select>/si';
        $new_select = '<select name="status" id="orderStatus" class="form-select">
                        <?php echo render_status_options($order[\'status\']); ?>
                       </select>';
        
        if (preg_match($old_select, $content)) {
            $content = preg_replace($old_select, $new_select, $content);
        }
    }
    
    if ($content !== $original) {
        file_put_contents('order-detail.php', $content);
        echo '✅ Đã cập nhật order-detail.php<br>';
    } else {
        echo '⚠️ Order-detail.php không cần thay đổi<br>';
    }
} else {
    echo '❌ Không tìm thấy order-detail.php<br>';
}
echo '</div>';

// 5. TẠO DATA TEST
echo '<div class="alert alert-dark">
    <h5>Bước 5: Tạo data test với status động</h5>';

// Xóa data test cũ
db_query("DELETE FROM orders WHERE order_number LIKE 'DYN%' OR order_number LIKE 'TEST%'");
echo 'Đã xóa data test cũ<br>';

// Tạo data test mới
$statuses = db_get_results("SELECT status_key, label FROM order_status_configs LIMIT 10");
$created_count = 0;

foreach ($statuses as $i => $status) {
    $order_data = [
        'order_number' => 'DYN' . str_pad($i+1, 3, '0', STR_PAD_LEFT),
        'customer_name' => 'Test ' . $status['label'],
        'customer_phone' => '090' . str_pad($i, 7, '0', STR_PAD_LEFT),
        'customer_address' => '123 Test Street, District ' . ($i+1),
        'total_amount' => rand(100000, 2000000),
        'status' => $status['status_key'],
        'created_at' => date('Y-m-d H:i:s'),
        'products' => json_encode([
            ['name' => 'Product Test', 'qty' => 1, 'price' => 100000]
        ])
    ];
    
    db_insert('orders', $order_data);
    $created_count++;
}

echo "✅ Đã tạo $created_count đơn hàng test với status động<br>";
echo '</div>';

// 6. KIỂM TRA KẾT QUẢ
echo '<div class="alert alert-success">
    <h5>✅ Hoàn tất Migration!</h5>
    <p>Hệ thống đã được cập nhật để sử dụng status động từ database.</p>
    <div class="mt-3">
        <a href="orders.php" class="btn btn-primary">Xem trang Orders</a>
        <a href="dashboard.php" class="btn btn-info">Xem Dashboard</a>
        <a href="order-status-config.php" class="btn btn-warning">Quản lý Status</a>
    </div>
</div>';

// Log activity
log_activity('migrate_dynamic_status', 'Migrated system to use dynamic status');

echo '</div></body></html>';
?>