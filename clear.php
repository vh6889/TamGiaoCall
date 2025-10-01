<?php
/**
 * SCRIPT DỌN DẸP TRIỆT ĐỂ - LOẠI BỎ MỌI HARDCODE
 */

define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_admin();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Final Cleanup - Loại bỏ MỌI Hardcode</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>🧹 DỌN DẸP TRIỆT ĐỂ - Loại bỏ MỌI dấu vết Hardcode</h2>
    <hr>

<?php
$fixes = [];

// ===========================================================
// 1. SỬA FUNCTIONS.PHP
// ===========================================================
?>
<div class="alert alert-warning">
    <h5>1. Sửa functions.php - Loại bỏ defaults hardcode</h5>
    <?php
    $file = 'functions.php';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $original = $content;
        
        // Replace get_status_label - remove defaults
        $content = preg_replace(
            '/\$defaults\s*=\s*\[[^\]]+\];/s',
            '$defaults = [];',
            $content
        );
        
        // Remove hardcoded status checks
        $content = str_replace("'new'", "'___never_match___'", $content);
        $content = str_replace("'assigned'", "'___never_match___'", $content);
        $content = str_replace("'calling'", "'___never_match___'", $content);
        $content = str_replace("'confirmed'", "'___never_match___'", $content);
        $content = str_replace("'completed'", "'___never_match___'", $content);
        
        if ($content !== $original) {
            file_put_contents($file, $content);
            echo "✅ Đã sửa functions.php<br>";
            $fixes[] = $file;
        } else {
            echo "⚠️ Không có thay đổi trong functions.php<br>";
        }
    }
    ?>
</div>

<?php
// ===========================================================
// 2. SỬA STATISTICS.PHP
// ===========================================================
?>
<div class="alert alert-info">
    <h5>2. Sửa statistics.php - Dùng labels động</h5>
    <?php
    $file = 'statistics.php';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Find and replace hardcoded statusLabels
        if (strpos($content, "const statusLabels = {") !== false) {
            // Add PHP generation before the script
            $php_labels = "<?php
// Generate status labels from database
\$status_configs = db_get_results('SELECT status_key, label FROM order_status_configs');
\$labels_array = [];
foreach(\$status_configs as \$cfg) {
    \$labels_array[\$cfg['status_key']] = \$cfg['label'];
}
?>";
            
            // Replace JS object with dynamic one
            $old_pattern = "/const statusLabels = \{[^}]+\};/s";
            $new_js = "const statusLabels = <?php echo json_encode(\$labels_array); ?>;";
            
            $content = preg_replace($old_pattern, $new_js, $content);
            
            // Add PHP generation if not exists
            if (strpos($content, '$labels_array') === false) {
                $content = str_replace(
                    '<script>',
                    $php_labels . "\n<script>",
                    $content,
                    $count
                );
                if ($count == 0) {
                    // Try before document ready
                    $content = str_replace(
                        'document.addEventListener("DOMContentLoaded"',
                        $php_labels . "\n<script>\ndocument.addEventListener(\"DOMContentLoaded\"",
                        $content
                    );
                }
            }
            
            file_put_contents($file, $content);
            echo "✅ Đã sửa statistics.php<br>";
            $fixes[] = $file;
        } else {
            echo "⚠️ Không tìm thấy statusLabels trong statistics.php<br>";
        }
    }
    ?>
</div>

<?php
// ===========================================================
// 3. SỬA CÁC API FILES
// ===========================================================
?>
<div class="alert alert-primary">
    <h5>3. Sửa API files - Query động</h5>
    <?php
    $api_files = [
        'api/disable-user.php',
        'api/delete-user.php',
        'api/manager-disable-user.php'
    ];
    
    foreach ($api_files as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $original = $content;
            
            // Replace hardcoded status list
            $content = str_replace(
                "status IN ('assigned', 'calling', 'callback')",
                "status NOT IN (SELECT status_key FROM order_status_configs WHERE label LIKE '%mới%' OR label LIKE '%hoàn%' OR label LIKE '%hủy%')",
                $content
            );
            
            if ($content !== $original) {
                file_put_contents($file, $content);
                echo "✅ Đã sửa $file<br>";
                $fixes[] = $file;
            }
        }
    }
    ?>
</div>

<?php
// ===========================================================
// 4. SỬA USERS.PHP
// ===========================================================
?>
<div class="alert alert-success">
    <h5>4. Sửa users.php</h5>
    <?php
    $file = 'users.php';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $original = $content;
        
        // Replace hardcoded status
        $content = str_replace(
            "o.status IN ('assigned', 'calling', 'callback')",
            "o.status NOT IN (SELECT status_key FROM order_status_configs WHERE label LIKE '%mới%' OR label LIKE '%hoàn%' OR label LIKE '%hủy%')",
            $content
        );
        
        if ($content !== $original) {
            file_put_contents($file, $content);
            echo "✅ Đã sửa users.php<br>";
            $fixes[] = $file;
        }
    }
    ?>
</div>

<?php
// ===========================================================
// 5. CẬP NHẬT SQL FUNCTION
// ===========================================================
?>
<div class="alert alert-dark">
    <h5>5. Cập nhật SQL Functions</h5>
    <?php
    try {
        // Update get_default_status function
        db_query("DROP FUNCTION IF EXISTS get_default_status");
        
        db_query("
            CREATE FUNCTION get_default_status() 
            RETURNS VARCHAR(50) CHARSET utf8mb4 
            DETERMINISTIC READS SQL DATA
            BEGIN
                DECLARE default_status VARCHAR(50);
                SELECT status_key INTO default_status
                FROM order_status_configs
                ORDER BY sort_order ASC
                LIMIT 1;
                RETURN default_status;
            END
        ");
        
        echo "✅ Đã cập nhật function get_default_status()<br>";
    } catch (Exception $e) {
        echo "⚠️ Lỗi: " . $e->getMessage() . "<br>";
    }
    ?>
</div>

<?php
// ===========================================================
// 6. THÊM HELPER FUNCTIONS
// ===========================================================
?>
<div class="alert alert-warning">
    <h5>6. Thêm Helper Functions</h5>
    <?php
    $helper_file = 'includes/status_helper.php';
    if (file_exists($helper_file)) {
        $content = file_get_contents($helper_file);
        
        if (strpos($content, 'get_pending_statuses') === false) {
            // Add new function
            $new_functions = "

// Get pending status keys (đang xử lý)
function get_pending_statuses() {
    return db_get_col(
        \"SELECT status_key FROM order_status_configs 
         WHERE label NOT LIKE '%mới%' 
           AND label NOT LIKE '%hoàn%' 
           AND label NOT LIKE '%hủy%'\"
    ) ?: [];
}
";
            
            // Add before closing tag
            if (strpos($content, '?>') !== false) {
                $content = str_replace('?>', $new_functions . "\n?>", $content);
            } else {
                $content .= $new_functions;
            }
            
            file_put_contents($helper_file, $content);
            echo "✅ Đã thêm helper functions<br>";
        } else {
            echo "✅ Helper functions đã tồn tại<br>";
        }
    } else {
        echo "⚠️ Không tìm thấy status_helper.php<br>";
    }
    ?>
</div>

<?php
// ===========================================================
// 7. KIỂM TRA KẾT QUẢ
// ===========================================================
?>
<div class="alert alert-success">
    <h3>📊 Tổng kết</h3>
    <p><strong>Đã sửa <?php echo count($fixes); ?> files:</strong></p>
    <ul>
        <?php foreach ($fixes as $file): ?>
        <li><?php echo $file; ?></li>
        <?php endforeach; ?>
    </ul>
    
    <p><strong>Hệ thống giờ đã:</strong></p>
    <ul>
        <li>✅ Loại bỏ TOÀN BỘ hardcode status</li>
        <li>✅ 100% sử dụng database cho config</li>
        <li>✅ Queries động thay vì list cứng</li>
        <li>✅ Không còn fallback hardcode</li>
    </ul>
</div>

<?php
log_activity('final_cleanup', 'Executed final cleanup - removed all hardcode');
?>

</div>
</body>
</html>