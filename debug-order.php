<?php
/**
 * System Check & Fix Script
 * Kiểm tra và sửa lỗi toàn bộ hệ thống
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

// Chỉ admin được chạy
require_admin();

$action = $_GET['action'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Check & Fix</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h1 class="text-danger">🚨 System Check & Fix</h1>
    
    <?php if ($action == 'fix_all'): ?>
        <div class="alert alert-warning">
            <h4>Đang sửa toàn bộ hệ thống...</h4>
        </div>
        
        <?php
        // 1. Drop old triggers
        echo "<p>1. Xóa triggers cũ...</p>";
        db_query("DROP TRIGGER IF EXISTS validate_system_status_insert");
        db_query("DROP TRIGGER IF EXISTS validate_system_status_update");
        echo "<p class='text-success'>✓ Đã xóa triggers cũ</p>";
        
        // 2. Fix all orders with wrong system_status
        echo "<p>2. Sửa system_status cho tất cả đơn hàng...</p>";
        
        // Orders with assigned_to but wrong status
        $fixed1 = db_query("
            UPDATE orders 
            SET system_status = 'assigned' 
            WHERE assigned_to IS NOT NULL 
              AND system_status != 'assigned'
        ");
        
        // Orders without assigned_to but wrong status  
        $fixed2 = db_query("
            UPDATE orders 
            SET system_status = 'free' 
            WHERE assigned_to IS NULL 
              AND system_status != 'free'
        ");
        
        echo "<p class='text-success'>✓ Đã sửa system_status</p>";
        
        // 3. Ensure all orders have primary_label
        echo "<p>3. Đảm bảo tất cả đơn có primary_label...</p>";
        db_query("
            UPDATE orders 
            SET primary_label = 'lbl_new_order' 
            WHERE primary_label IS NULL OR primary_label = ''
        ");
        echo "<p class='text-success'>✓ Đã set primary_label mặc định</p>";
        
        // 4. Create proper labels if not exist
        echo "<p>4. Tạo nhãn hệ thống nếu chưa có...</p>";
        db_query("
            INSERT IGNORE INTO order_labels 
            (label_key, label_name, label_value, color, icon, sort_order, is_system)
            VALUES 
            ('free', '[Kho chung] Chưa phân công', 0, '#6c757d', 'fa-inbox', -9999, 1),
            ('lbl_new_order', 'Đơn mới', 0, '#17a2b8', 'fa-plus-circle', 0, 0),
            ('lbl_processing', 'Đang xử lý', 0, '#ffc107', 'fa-spinner', 1, 0),
            ('lbl_confirmed', 'Đã xác nhận', 0, '#28a745', 'fa-check', 2, 0),
            ('lbl_completed', 'Hoàn thành', 1, '#28a745', 'fa-check-circle', 9999, 0),
            ('lbl_cancelled', 'Đã hủy', 0, '#dc3545', 'fa-times-circle', 9998, 0)
        ");
        echo "<p class='text-success'>✓ Đã tạo nhãn hệ thống</p>";
        
        // 5. Fix specific TEST001 order
        echo "<p>5. Sửa đơn TEST001...</p>";
        db_query("
            UPDATE orders 
            SET system_status = 'free',
                assigned_to = NULL,
                assigned_at = NULL,
                is_locked = 0,
                primary_label = 'lbl_new_order'
            WHERE order_number = 'TEST001'
        ");
        echo "<p class='text-success'>✓ Đã reset đơn TEST001</p>";
        
        echo "<div class='alert alert-success mt-3'>
            <h4>✅ ĐÃ SỬA XONG!</h4>
            <p>Hệ thống đã được sửa hoàn toàn. Vui lòng test lại các chức năng.</p>
            <a href='order-detail.php?id=1' class='btn btn-primary'>Test với đơn TEST001</a>
        </div>";
        ?>
        
    <?php else: ?>
        
        <h2>1. Kiểm tra Database</h2>
        <?php
        // Check system_status column exists
        $has_system_status = db_get_var("
            SELECT COUNT(*) 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
              AND TABLE_NAME = 'orders' 
              AND COLUMN_NAME = 'system_status'
        ", [DB_NAME]);
        ?>
        
        <table class="table table-bordered">
            <tr>
                <td>Cột system_status:</td>
                <td><?php echo $has_system_status ? 
                    '<span class="badge bg-success">OK</span>' : 
                    '<span class="badge bg-danger">MISSING</span>'; ?>
                </td>
            </tr>
            <?php if (!$has_system_status): ?>
            <tr>
                <td colspan="2" class="bg-danger text-white">
                    <strong>LỖI NGHIÊM TRỌNG: Thiếu cột system_status!</strong><br>
                    Chạy SQL: ALTER TABLE orders ADD system_status ENUM('free','assigned') DEFAULT 'free';
                </td>
            </tr>
            <?php endif; ?>
        </table>
        
        <h2>2. Kiểm tra Triggers</h2>
        <?php
        // Simplified query for older MySQL versions
        $triggers = db_get_results("SHOW TRIGGERS");
        $order_triggers = array_filter($triggers, function($t) {
            return isset($t['Table']) && $t['Table'] == 'orders';
        });
        ?>
        
        <?php if (!empty($order_triggers)): ?>
        <div class="alert alert-warning">
            <strong>Phát hiện Triggers trên bảng orders:</strong>
            <ul>
            <?php foreach ($order_triggers as $trigger): ?>
                <li><?php echo $trigger['Trigger'] ?? 'Unknown'; ?> 
                    (<?php echo $trigger['Event'] ?? ''; ?>)
                </li>
            <?php endforeach; ?>
            </ul>
            <p><strong>Triggers này có thể gây lỗi logic!</strong></p>
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            Không có triggers trên bảng orders.
        </div>
        <?php endif; ?>
        
        <h2>3. Kiểm tra Orders</h2>
        <?php
        $issues = db_get_results("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN assigned_to IS NULL AND system_status != 'free' THEN 1 ELSE 0 END) as wrong_free,
                SUM(CASE WHEN assigned_to IS NOT NULL AND system_status != 'assigned' THEN 1 ELSE 0 END) as wrong_assigned,
                SUM(CASE WHEN primary_label IS NULL OR primary_label = '' THEN 1 ELSE 0 END) as no_label
            FROM orders
        ");
        $issue = $issues[0];
        ?>
        
        <table class="table table-bordered">
            <tr>
                <td>Tổng đơn hàng:</td>
                <td><?php echo $issue['total']; ?></td>
            </tr>
            <tr class="<?php echo $issue['wrong_free'] > 0 ? 'table-danger' : ''; ?>">
                <td>Đơn không assigned nhưng status sai:</td>
                <td><?php echo $issue['wrong_free']; ?> đơn</td>
            </tr>
            <tr class="<?php echo $issue['wrong_assigned'] > 0 ? 'table-danger' : ''; ?>">
                <td>Đơn đã assigned nhưng status sai:</td>
                <td><?php echo $issue['wrong_assigned']; ?> đơn</td>
            </tr>
            <tr class="<?php echo $issue['no_label'] > 0 ? 'table-warning' : ''; ?>">
                <td>Đơn không có primary_label:</td>
                <td><?php echo $issue['no_label']; ?> đơn</td>
            </tr>
        </table>
        
        <h2>4. Chi tiết đơn TEST001</h2>
        <?php
        $test_order = db_get_row("
            SELECT * FROM orders WHERE order_number = 'TEST001'
        ");
        
        if ($test_order):
        ?>
        <table class="table table-bordered">
            <tr>
                <td>ID:</td>
                <td><?php echo $test_order['id']; ?></td>
            </tr>
            <tr class="<?php echo !$test_order['assigned_to'] && $test_order['system_status'] != 'free' ? 'table-danger' : ''; ?>">
                <td>system_status:</td>
                <td><strong><?php echo $test_order['system_status']; ?></strong></td>
            </tr>
            <tr>
                <td>assigned_to:</td>
                <td><?php echo $test_order['assigned_to'] ?: 'NULL'; ?></td>
            </tr>
            <tr>
                <td>primary_label:</td>
                <td><?php echo $test_order['primary_label'] ?: 'NULL'; ?></td>
            </tr>
            <tr>
                <td>is_locked:</td>
                <td><?php echo $test_order['is_locked'] ? 'YES' : 'NO'; ?></td>
            </tr>
        </table>
        <?php else: ?>
        <div class="alert alert-warning">Không tìm thấy đơn TEST001</div>
        <?php endif; ?>
        
        <hr>
        
        <?php
        $has_issues = $issue['wrong_free'] > 0 || 
                     $issue['wrong_assigned'] > 0 || 
                     $issue['no_label'] > 0 ||
                     !empty($order_triggers);
        ?>
        
        <?php if ($has_issues): ?>
        <div class="alert alert-danger">
            <h4>⚠️ PHÁT HIỆN LỖI HỆ THỐNG!</h4>
            <p>Click nút bên dưới để sửa tất cả lỗi:</p>
            <a href="?action=fix_all" class="btn btn-danger btn-lg" 
               onclick="return confirm('SỬA TOÀN BỘ HỆ THỐNG?\n\nQuá trình này sẽ:\n1. Xóa triggers\n2. Sửa system_status\n3. Reset đơn TEST001\n\nBạn chắc chắn?')">
                🔧 SỬA TẤT CẢ LỖI NGAY
            </a>
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            <h4>✅ Hệ thống OK!</h4>
            <p>Không phát hiện lỗi nào.</p>
        </div>
        <?php endif; ?>
        
    <?php endif; ?>
    
    <div class="mt-4">
        <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
        <a href="order-detail.php?id=<?php echo $test_order['id'] ?? 1; ?>" class="btn btn-primary">
            Xem đơn TEST001 →
        </a>
    </div>
</div>
</body>
</html>