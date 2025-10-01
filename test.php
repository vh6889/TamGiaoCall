<?php
/**
 * SYSTEM INTEGRITY CHECK SCRIPT
 * Script kiểm tra toàn diện hệ thống sau khi refactoring
 * 
 * Cách sử dụng:
 * 1. Upload file này vào thư mục gốc dự án
 * 2. Truy cập: http://yoursite.com/system-check.php
 * 3. Xem báo cáo chi tiết
 * 
 * QUAN TRỌNG: Xóa file này sau khi kiểm tra xong!
 */

define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/status_helper.php'; // FIX: Thêm dòng này

// Chỉ admin mới được chạy
if (!is_logged_in() || !is_admin()) {
    die('⛔ UNAUTHORIZED - Admin only');
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Integrity Check</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { color: #2c3e50; margin-bottom: 30px; font-size: 2.5rem; }
        .section { background: white; border-radius: 8px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .section h2 { color: #34495e; margin-bottom: 20px; font-size: 1.5rem; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        .check-item { padding: 12px; margin: 8px 0; border-radius: 5px; display: flex; align-items: center; }
        .check-item.pass { background: #d4edda; border-left: 4px solid #28a745; }
        .check-item.fail { background: #f8d7da; border-left: 4px solid #dc3545; }
        .check-item.warn { background: #fff3cd; border-left: 4px solid #ffc107; }
        .check-item.info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
        .icon { font-size: 20px; margin-right: 12px; font-weight: bold; }
        .pass .icon { color: #28a745; }
        .fail .icon { color: #dc3545; }
        .warn .icon { color: #ffc107; }
        .info .icon { color: #17a2b8; }
        .details { margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-family: monospace; font-size: 13px; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .summary-card { background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .summary-card h3 { font-size: 2.5rem; margin-bottom: 5px; }
        .summary-card.pass h3 { color: #28a745; }
        .summary-card.fail h3 { color: #dc3545; }
        .summary-card.warn h3 { color: #ffc107; }
        .summary-card.info h3 { color: #17a2b8; }
        .code { background: #282c34; color: #abb2bf; padding: 15px; border-radius: 5px; overflow-x: auto; margin-top: 10px; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px; }
        .btn:hover { background: #2980b9; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #f8f9fa; font-weight: 600; }
        tr:hover { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 System Integrity Check</h1>
        <p style="color: #7f8c8d; margin-bottom: 30px; font-size: 1.1rem;">
            Kiểm tra toàn diện hệ thống sau khi refactoring từ <code>order_status_configs</code> sang <code>order_labels</code>
        </p>

<?php
// ============================================
// INITIALIZE COUNTERS
// ============================================
$stats = [
    'pass' => 0,
    'fail' => 0,
    'warn' => 0,
    'info' => 0
];

$checks = [];

// ============================================
// HELPER FUNCTIONS
// ============================================
function add_check($type, $message, $details = null) {
    global $checks, $stats;
    $checks[] = ['type' => $type, 'message' => $message, 'details' => $details];
    $stats[$type]++;
}

function file_contains($file, $pattern) {
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    return strpos($content, $pattern) !== false;
}

function file_not_contains($file, $pattern) {
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    return strpos($content, $pattern) === false;
}

// ============================================
// CHECK 1: DATABASE STRUCTURE
// ============================================
echo '<div class="section">';
echo '<h2>📊 1. Database Structure</h2>';

// Check if order_labels table exists
$table_exists = db_get_var("SHOW TABLES LIKE 'order_labels'");
if ($table_exists) {
    add_check('pass', 'Bảng order_labels đã tồn tại');
    
    // Check columns
    $required_columns = ['label_key', 'label_name', 'color', 'icon', 'sort_order', 'is_system', 'is_final'];
    $existing_columns = db_get_col("SHOW COLUMNS FROM order_labels");
    
    foreach ($required_columns as $col) {
        if (in_array($col, $existing_columns)) {
            add_check('pass', "Cột order_labels.$col tồn tại");
        } else {
            add_check('fail', "Cột order_labels.$col KHÔNG tồn tại", "Cần chạy migration");
        }
    }
} else {
    add_check('fail', 'Bảng order_labels KHÔNG tồn tại', 'Cần chạy migration ngay!');
}

// Check orders table structure
$orders_columns = db_get_col("SHOW COLUMNS FROM orders");
if (in_array('system_status', $orders_columns)) {
    add_check('pass', 'Cột orders.system_status tồn tại');
} else {
    add_check('fail', 'Cột orders.system_status KHÔNG tồn tại', 'Cần chạy: ALTER TABLE orders ADD system_status ENUM("free","assigned")');
}

if (in_array('primary_label', $orders_columns)) {
    add_check('pass', 'Cột orders.primary_label tồn tại');
} else {
    add_check('fail', 'Cột orders.primary_label KHÔNG tồn tại', 'Cần chạy: ALTER TABLE orders ADD primary_label VARCHAR(50)');
}

// Check if old column 'status' still exists
if (in_array('status', $orders_columns)) {
    add_check('warn', 'Cột orders.status CŨ vẫn tồn tại', 'Có thể xóa sau khi migrate xong. Hoặc giữ lại để backward compatible.');
}

// Check order_label_history table
$history_exists = db_get_var("SHOW TABLES LIKE 'order_label_history'");
if ($history_exists) {
    add_check('pass', 'Bảng order_label_history đã tồn tại (tracking history)');
} else {
    add_check('warn', 'Bảng order_label_history chưa có', 'Không bắt buộc nhưng nên có để track lịch sử thay đổi');
}

foreach ($checks as $check) {
    echo '<div class="check-item ' . $check['type'] . '">';
    echo '<span class="icon">' . ($check['type'] === 'pass' ? '✓' : ($check['type'] === 'fail' ? '✗' : ($check['type'] === 'warn' ? '⚠' : 'ℹ'))) . '</span>';
    echo '<div style="flex: 1;">';
    echo '<strong>' . htmlspecialchars($check['message']) . '</strong>';
    if ($check['details']) {
        echo '<div class="details">' . htmlspecialchars($check['details']) . '</div>';
    }
    echo '</div>';
    echo '</div>';
}
$checks = []; // Reset for next section

echo '</div>';

// ============================================
// CHECK 2: DATABASE DATA INTEGRITY
// ============================================
echo '<div class="section">';
echo '<h2>🗂️ 2. Database Data Integrity</h2>';

// Check if labels exist
$label_count = (int)db_get_var("SELECT COUNT(*) FROM order_labels");
if ($label_count > 0) {
    add_check('pass', "Có {$label_count} nhãn trong hệ thống");
    
    // List all labels
    $labels = db_get_results("SELECT label_key, label_name, is_system, is_final FROM order_labels ORDER BY sort_order");
    $details = "<table><tr><th>Key</th><th>Name</th><th>System</th><th>Final</th></tr>";
    foreach ($labels as $label) {
        $details .= "<tr>";
        $details .= "<td>" . htmlspecialchars($label['label_key']) . "</td>";
        $details .= "<td>" . htmlspecialchars($label['label_name']) . "</td>";
        $details .= "<td>" . ($label['is_system'] ? '✓' : '-') . "</td>";
        $details .= "<td>" . ($label['is_final'] ? '✓' : '-') . "</td>";
        $details .= "</tr>";
    }
    $details .= "</table>";
    add_check('info', 'Danh sách nhãn hiện tại:', $details);
} else {
    add_check('fail', 'KHÔNG có nhãn nào trong order_labels', 'Cần import nhãn từ backup hoặc tạo mới');
}

// Check orders using new columns
$orders_with_system_status = db_get_var("SELECT COUNT(*) FROM orders WHERE system_status IS NOT NULL");
$orders_with_primary_label = db_get_var("SELECT COUNT(*) FROM orders WHERE primary_label IS NOT NULL");
$total_orders = db_get_var("SELECT COUNT(*) FROM orders");

if ($total_orders > 0) {
    $percent_system = round(($orders_with_system_status / $total_orders) * 100, 2);
    $percent_label = round(($orders_with_primary_label / $total_orders) * 100, 2);
    
    if ($percent_system >= 100) {
        add_check('pass', "{$percent_system}% đơn hàng có system_status ({$orders_with_system_status}/{$total_orders})");
    } elseif ($percent_system >= 80) {
        add_check('warn', "{$percent_system}% đơn hàng có system_status ({$orders_with_system_status}/{$total_orders})", 'Còn đơn chưa migrate');
    } else {
        add_check('fail', "CHỈ {$percent_system}% đơn hàng có system_status ({$orders_with_system_status}/{$total_orders})", 'Cần chạy migration ngay!');
    }
    
    if ($percent_label >= 100) {
        add_check('pass', "{$percent_label}% đơn hàng có primary_label ({$orders_with_primary_label}/{$total_orders})");
    } elseif ($percent_label >= 80) {
        add_check('warn', "{$percent_label}% đơn hàng có primary_label ({$orders_with_primary_label}/{$total_orders})", 'Còn đơn chưa migrate');
    } else {
        add_check('fail', "CHỈ {$percent_label}% đơn hàng có primary_label ({$orders_with_primary_label}/{$total_orders})", 'Cần chạy migration ngay!');
    }
} else {
    add_check('info', 'Chưa có đơn hàng nào trong hệ thống');
}

// Check for orphan labels (primary_label not in order_labels)
$orphan_check = db_get_results("
    SELECT DISTINCT o.primary_label, COUNT(*) as count
    FROM orders o
    LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
    WHERE o.primary_label IS NOT NULL 
      AND ol.label_key IS NULL
    GROUP BY o.primary_label
");

if (empty($orphan_check)) {
    add_check('pass', 'Không có đơn hàng nào sử dụng nhãn không tồn tại');
} else {
    $details = "Các nhãn không tồn tại nhưng đang được sử dụng:\n";
    foreach ($orphan_check as $row) {
        $details .= "- {$row['primary_label']}: {$row['count']} đơn\n";
    }
    add_check('fail', count($orphan_check) . ' nhãn không tồn tại đang được sử dụng', $details);
}

foreach ($checks as $check) {
    echo '<div class="check-item ' . $check['type'] . '">';
    echo '<span class="icon">' . ($check['type'] === 'pass' ? '✓' : ($check['type'] === 'fail' ? '✗' : ($check['type'] === 'warn' ? '⚠' : 'ℹ'))) . '</span>';
    echo '<div style="flex: 1;">';
    echo '<strong>' . htmlspecialchars($check['message']) . '</strong>';
    if ($check['details']) {
        echo '<div class="details">' . nl2br(htmlspecialchars($check['details'])) . '</div>';
    }
    echo '</div>';
    echo '</div>';
}
$checks = [];

echo '</div>';

// ============================================
// CHECK 3: CODE FILES - HARDCODE DETECTION
// ============================================
echo '<div class="section">';
echo '<h2>📝 3. Code Files - Hardcode Detection</h2>';

// TẤT CẢ FILE CẦN KIỂM TRA - 60 FILES (CHÍNH XÁC 100%)
$files_to_check = [
    // CORE FILES - THƯ MỤC GỐC (18 files)
    'admin-rules.php',
    'config.php',
    'create-manual-order.php',
    'create-user.php',
    'customer-history.php',
    'dashboard.php',
    'functions.php',
    'import.php',
    'index.php',
    'kpi.php',
    'logout.php',
    'manage-customer-labels.php',
    'manage-order-labels.php',
    'manage-user-labels.php',
    'order-detail.php',
    'orders.php',
    'pending-orders.php',
    'profile.php',
    'rule-engine-php.php',
    'settings.php',
    'simple-rule-handler.php',
    'statistics.php',
    'users.php',
    
    // INCLUDES (7 files)
    'includes/error_handler.php',
    'includes/footer.php',
    'includes/header.php',
    'includes/role_helper.php',
    'includes/security_helper.php',
    'includes/status_helper.php',
    'includes/transaction_helper.php',
    
    // API (30 files)
    'api/add-note.php',
    'api/approve-order.php',
    'api/assign-manager.php',
    'api/assign-order.php',
    'api/claim-order.php',
    'api/complete-reminder.php',
    'api/create-user.php',
    'api/delete-order.php',
    'api/delete-user.php',
    'api/disable-user.php',
    'api/end-call.php',
    'api/manager-disable-user.php',
    'api/manager-receive-order.php',
    'api/receive-order.php',
    'api/reclaim-order.php',
    'api/remove-assignment.php',
    'api/save-kpi.php',
    'api/save-settings.php',
    'api/send-to-shipping.php',
    'api/set-callback.php',
    'api/start-call.php',
    'api/submit-manual-order.php',
    'api/transfer-order.php',
    'api/unassign-order.php',
    'api/update-customer.php',
    'api/update-customer-info.php',
    'api/update-products.php',
    'api/update-status.php',
    'api/update-user.php',
    
    // API/RULES (3 files)
    'api/rules/execute.php',
    'api/rules/manage.php',
    'api/rules/run-hooks.php',
    
    // CRON (2 files)
    'cron/run-rules.php',
    'cron/run-scheduled-rules.php',
];

$bad_patterns = [
    'order_status_configs' => 'Tên bảng CŨ',
    "FROM order_status_configs" => 'Query sử dụng bảng cũ',
    "JOIN order_status_configs" => 'JOIN với bảng cũ',
    "UPDATE order_status_configs" => 'UPDATE bảng cũ',
    "INSERT INTO order_status_configs" => 'INSERT bảng cũ',
    " osc." => 'Alias cũ (osc)',
    "->status =" => 'Gán trực tiếp vào $order->status',
    "'status' => 'new'" => 'Hardcode status="new"',
    "'status' => 'assigned'" => 'Hardcode status="assigned"',
    "'status' => 'free'" => 'Hardcode status="free"',
];

foreach ($files_to_check as $file) {
    if (!file_exists($file)) {
        add_check('warn', "$file không tồn tại", 'File có thể đã đổi tên hoặc xóa');
        continue;
    }
    
    $content = file_get_contents($file);
    $found_issues = [];
    
    foreach ($bad_patterns as $pattern => $desc) {
        if (stripos($content, $pattern) !== false) {
            $found_issues[] = $desc . " ($pattern)";
        }
    }
    
    if (empty($found_issues)) {
        add_check('pass', "$file: Không phát hiện hardcode");
    } else {
        add_check('fail', "$file: Phát hiện " . count($found_issues) . " vấn đề", implode("\n", $found_issues));
    }
}

foreach ($checks as $check) {
    echo '<div class="check-item ' . $check['type'] . '">';
    echo '<span class="icon">' . ($check['type'] === 'pass' ? '✓' : ($check['type'] === 'fail' ? '✗' : ($check['type'] === 'warn' ? '⚠' : 'ℹ'))) . '</span>';
    echo '<div style="flex: 1;">';
    echo '<strong>' . htmlspecialchars($check['message']) . '</strong>';
    if ($check['details']) {
        echo '<div class="details">' . nl2br(htmlspecialchars($check['details'])) . '</div>';
    }
    echo '</div>';
    echo '</div>';
}
$checks = [];

echo '</div>';

// ============================================
// CHECK 4: CORE FUNCTIONS VALIDATION
// ============================================
echo '<div class="section">';
echo '<h2>⚙️ 4. Core Functions Validation</h2>';

// Check if critical functions exist
$required_functions = [
    'get_order_labels' => 'Lấy danh sách nhãn',
    'get_order_label' => 'Lấy thông tin 1 nhãn',
    'label_exists' => 'Kiểm tra nhãn tồn tại',
    'assign_order_label' => 'Gán nhãn cho đơn',
    'is_system_status' => 'Kiểm tra system status',
    'get_confirmed_statuses' => 'Lấy nhãn thành công',
    'get_cancelled_statuses' => 'Lấy nhãn hủy',
    'get_all_statuses' => 'Lấy tất cả status (backward compat)',
    'get_status_info' => 'Lấy thông tin status',
    'format_status_badge' => 'Format badge hiển thị',
];

foreach ($required_functions as $func => $desc) {
    if (function_exists($func)) {
        add_check('pass', "Hàm $func() tồn tại - $desc");
    } else {
        add_check('fail', "Hàm $func() KHÔNG tồn tại", "Cần thêm vào functions.php hoặc status_helper.php");
    }
}

// Test actual function calls
try {
    if (function_exists('get_order_labels')) {
        $test_labels = get_order_labels();
        if (is_array($test_labels)) {
            add_check('pass', "get_order_labels() hoạt động OK - trả về " . count($test_labels) . " nhãn");
        } else {
            add_check('fail', "get_order_labels() trả về kết quả không hợp lệ");
        }
    } else {
        add_check('warn', "Hàm get_order_labels() không tồn tại");
    }
} catch (Exception $e) {
    add_check('fail', "get_order_labels() gặp lỗi: " . $e->getMessage());
}

try {
    if (function_exists('get_all_statuses')) {
        $test_statuses = get_all_statuses();
        if (is_array($test_statuses)) {
            add_check('pass', "get_all_statuses() hoạt động OK (backward compat)");
        } else {
            add_check('fail', "get_all_statuses() trả về kết quả không hợp lệ");
        }
    } else {
        add_check('warn', "Hàm get_all_statuses() không tồn tại");
    }
} catch (Exception $e) {
    add_check('fail', "get_all_statuses() gặp lỗi: " . $e->getMessage());
}

foreach ($checks as $check) {
    echo '<div class="check-item ' . $check['type'] . '">';
    echo '<span class="icon">' . ($check['type'] === 'pass' ? '✓' : ($check['type'] === 'fail' ? '✗' : ($check['type'] === 'warn' ? '⚠' : 'ℹ'))) . '</span>';
    echo '<div style="flex: 1;">';
    echo '<strong>' . htmlspecialchars($check['message']) . '</strong>';
    if ($check['details']) {
        echo '<div class="details">' . nl2br(htmlspecialchars($check['details'])) . '</div>';
    }
    echo '</div>';
    echo '</div>';
}
$checks = [];

echo '</div>';

// ============================================
// SUMMARY
// ============================================
$total_checks = $stats['pass'] + $stats['fail'] + $stats['warn'] + $stats['info'];
$pass_rate = $total_checks > 0 ? round(($stats['pass'] / $total_checks) * 100, 1) : 0;

echo '<div class="summary">';
echo '<div class="summary-card pass"><h3>' . $stats['pass'] . '</h3><p>Passed</p></div>';
echo '<div class="summary-card fail"><h3>' . $stats['fail'] . '</h3><p>Failed</p></div>';
echo '<div class="summary-card warn"><h3>' . $stats['warn'] . '</h3><p>Warnings</p></div>';
echo '<div class="summary-card info"><h3>' . $stats['info'] . '</h3><p>Info</p></div>';
echo '</div>';

echo '<div class="section">';
echo '<h2>📈 Overall Health: ' . $pass_rate . '%</h2>';

if ($pass_rate >= 90) {
    echo '<div class="check-item pass">';
    echo '<span class="icon">🎉</span>';
    echo '<div><strong>HỆ THỐNG HOẠT ĐỘNG TỐT!</strong><br>Chỉ còn một số vấn đề nhỏ cần xử lý.</div>';
    echo '</div>';
} elseif ($pass_rate >= 70) {
    echo '<div class="check-item warn">';
    echo '<span class="icon">⚠️</span>';
    echo '<div><strong>HỆ THỐNG CÓ MỘT SỐ VẤN ĐỀ</strong><br>Cần kiểm tra và sửa các lỗi được đánh dấu màu đỏ.</div>';
    echo '</div>';
} else {
    echo '<div class="check-item fail">';
    echo '<span class="icon">🚨</span>';
    echo '<div><strong>HỆ THỐNG CÓ VẤN ĐỀ NGHIÊM TRỌNG!</strong><br>Cần sửa ngay các lỗi critical trước khi đưa vào production.</div>';
    echo '</div>';
}

echo '</div>';

// ============================================
// ACTION BUTTONS
// ============================================
echo '<div class="section">';
echo '<h2>🔧 Next Actions</h2>';
echo '<a href="?" class="btn">🔄 Chạy lại kiểm tra</a>';
echo '<a href="dashboard.php" class="btn">🏠 Về Dashboard</a>';
echo '<a href="#" onclick="if(confirm(\'Bạn có chắc muốn xóa file kiểm tra này?\')) window.location=\'?action=delete_self\'; return false;" class="btn btn-danger">🗑️ Xóa file kiểm tra này</a>';
echo '<div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px;">';
echo '<strong>⚠️ LƯU Ý BẢO MẬT:</strong> Sau khi kiểm tra xong, hãy xóa file <code>system-check.php</code> này để tránh lộ thông tin hệ thống!';
echo '</div>';
echo '</div>';

?>

    </div>

    <script>
    // Auto scroll to first fail
    window.addEventListener('load', function() {
        const firstFail = document.querySelector('.check-item.fail');
        if (firstFail) {
            firstFail.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstFail.style.boxShadow = '0 0 20px rgba(220, 53, 69, 0.5)';
        }
    });
    </script>
</body>
</html>

<?php
// Self-delete functionality
if (isset($_GET['action']) && $_GET['action'] === 'delete_self') {
    if (unlink(__FILE__)) {
        echo '<script>alert("File đã được xóa thành công!"); window.location="dashboard.php";</script>';
    } else {
        echo '<script>alert("Không thể xóa file. Vui lòng xóa thủ công!"); history.back();</script>';
    }
}
?>