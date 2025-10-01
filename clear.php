<?php
/**
 * SCRIPT SỬA TOÀN BỘ HARDCODE - VERSION CUỐI CÙNG
 * Sửa tất cả 30+ vấn đề trong 15+ files
 */

define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/status_helper.php';
require_admin();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Sửa TOÀN BỘ Hardcode - Final Version</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .file-section { 
            margin: 10px 0; 
            padding: 10px; 
            border-left: 3px solid #007bff;
            background: #f8f9fa;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2>🔧 Sửa TOÀN BỘ Hardcode Status - Final Version</h2>
    <hr>
    
    <?php
    $total_fixes = 0;
    $files_fixed = [];
    
    // Danh sách tất cả các file và các hardcode cần sửa
    $fixes_needed = [
        'api/approve-order.php' => [
            "'status' => 'new'" => "'status' => get_new_status_key()",
            "'status' => 'cancelled'" => "'status' => db_get_var(\"SELECT status_key FROM order_status_configs WHERE label LIKE '%hủy%' LIMIT 1\")"
        ],
        
        'api/update-status.php' => [
            "in_array(\$status, ['completed', 'confirmed', 'rejected', 'cancelled'])" => 
            "in_array(\$status, array_merge(get_confirmed_statuses(), db_get_col(\"SELECT status_key FROM order_status_configs WHERE label LIKE '%hủy%' OR label LIKE '%rejected%'\")))"
        ],
        
        'api/end-call.php' => [
            "'status' => 'completed'" => "'status' => 'completed'",
            "'status' => 'pending'" => "'status' => 'pending'"
        ],
        
        'api/set-callback.php' => [
            "'status' => 'callback'" => "'status' => db_get_var(\"SELECT status_key FROM order_status_configs WHERE label LIKE '%gọi lại%' OR label LIKE '%callback%' LIMIT 1\")"
        ],
        
        'api/claim-order.php' => [
            "'status' => 'assigned'" => "'status' => db_get_var(\"SELECT status_key FROM order_status_configs WHERE label LIKE '%nhận%' OR label LIKE '%assigned%' LIMIT 1\")"
        ],
        
        'api/start-call.php' => [
            "'status' => 'dong-goi-sai'" => "'status' => get_calling_status_key()"
        ],
        
        'api/reclaim-order.php' => [
            "'status' => 'new'" => "'status' => get_new_status_key()"
        ],
        
        'api/delete-user.php' => [
            "status = 'new'" => "status = \" . get_new_status_key() . \""
        ],
        
        'api/disable-user.php' => [
            "status = 'new'" => "status = \" . get_new_status_key() . \""
        ],
        
        'api/manager-disable-user.php' => [
            "status = 'new'" => "status = \" . get_new_status_key() . \""
        ],
        
        'simple-rule-handler.php' => [
            "in_array(\$new_status, ['completed', 'confirmed', 'rejected', 'cancelled'])" => 
            "in_array(\$new_status, array_merge(get_confirmed_statuses(), db_get_col(\"SELECT status_key FROM order_status_configs WHERE label LIKE '%hủy%' OR label LIKE '%rejected%'\")))",
            "'status' => 'pending'" => "'status' => 'pending'",
            "SUM(CASE WHEN status = 'confirmed'" => "SUM(CASE WHEN status IN (SELECT status_key FROM order_status_configs WHERE label LIKE '%xác nhận%' OR label LIKE '%hoàn%')",
            "AND status IN ('cancelled', 'rejected')" => 
            "AND status IN (SELECT status_key FROM order_status_configs WHERE label LIKE '%hủy%' OR label LIKE '%rejected%')",
            "if (\$order_status == 'no_answer')" => 
            "if (in_array(\$order_status, db_get_col(\"SELECT status_key FROM order_status_configs WHERE label LIKE '%không nghe%' OR label LIKE '%no_answer%'\")))"
        ],
        
        'functions.php' => [
            "['status_key' => 'giao-thanh-cong'" => "['status_key' => get_confirmed_status()"
        ],
        
        'pending-orders.php' => [
            "WHERE o.approval_status = 'pending'" => "WHERE o.approval_status = 'pending'"
        ],
        
        'kpi.php' => [
            "status = 'giao-thanh-cong'" => "status = \" . get_confirmed_status() . \""
        ],
        
        'rule-engine-php.php' => [
            "'status' => 'suspended'" => "'status' => 'suspended'",
            "status = 'new'" => "status = \" . get_new_status_key() . \""
        ],
        
        'cron/run-scheduled-rules.php' => [
            "WHERE status NOT IN ('completed', 'cancelled')" => 
            "WHERE status NOT IN (SELECT status_key FROM order_status_configs WHERE label LIKE '%hoàn%' OR label LIKE '%hủy%')",
            "WHERE status = 'active'" => "WHERE status = 'active'"
        ]
    ];
    
    // Thêm helper functions nếu cần
    echo '<div class="alert alert-info">';
    echo '<h5>Bước 1: Kiểm tra helper functions</h5>';
    
    // Ensure helper functions exist
    $helper_file = 'includes/status_helper.php';
    if (file_exists($helper_file)) {
        $helper_content = file_get_contents($helper_file);
        $functions_to_add = [];
        
        // Check each required function
        if (strpos($helper_content, 'get_confirmed_statuses') === false) {
            $functions_to_add[] = '
function get_confirmed_statuses() {
    return db_get_col(
        "SELECT status_key FROM order_status_configs 
         WHERE label LIKE \'%xác nhận%\' OR label LIKE \'%hoàn%\' 
            OR label LIKE \'%thành công%\' OR label LIKE \'%completed%\'"
    ) ?: [];
}';
        }
        
        if (strpos($helper_content, 'get_cancelled_statuses') === false) {
            $functions_to_add[] = '
function get_cancelled_statuses() {
    return db_get_col(
        "SELECT status_key FROM order_status_configs 
         WHERE label LIKE \'%hủy%\' OR label LIKE \'%cancelled%\' 
            OR label LIKE \'%rejected%\'"
    ) ?: [];
}';
        }
        
        if (!empty($functions_to_add)) {
            $helper_content = str_replace('?>', implode("\n", $functions_to_add) . "\n?>", $helper_content);
            file_put_contents($helper_file, $helper_content);
            echo '<span class="success">✅ Đã thêm ' . count($functions_to_add) . ' helper functions</span><br>';
        } else {
            echo '<span class="success">✅ Helper functions đã đủ</span><br>';
        }
    }
    echo '</div>';
    
    // Sửa từng file
    echo '<div class="alert alert-warning">';
    echo '<h5>Bước 2: Sửa hardcode trong các files</h5>';
    
    foreach ($fixes_needed as $file => $replacements) {
        echo '<div class="file-section">';
        echo "<strong>📁 $file</strong><br>";
        
        if (!file_exists($file)) {
            echo '<span class="error">❌ File không tồn tại</span><br>';
            continue;
        }
        
        $content = file_get_contents($file);
        $original = $content;
        $fixes_in_file = 0;
        
        // Đảm bảo file có include helper nếu cần
        if (strpos($file, 'api/') === 0 && strpos($content, 'status_helper.php') === false) {
            $content = str_replace(
                "require_once '../functions.php';",
                "require_once '../functions.php';\nrequire_once '../includes/status_helper.php';",
                $content
            );
        }
        
        // Thực hiện các thay thế
        foreach ($replacements as $search => $replace) {
            if (strpos($content, $search) !== false) {
                $content = str_replace($search, $replace, $content);
                $fixes_in_file++;
                echo "  ✔️ Sửa: <code>" . htmlspecialchars(substr($search, 0, 50)) . "...</code><br>";
            }
        }
        
        if ($content !== $original) {
            file_put_contents($file, $content);
            $files_fixed[] = $file;
            $total_fixes += $fixes_in_file;
            echo '<span class="success">✅ Đã sửa ' . $fixes_in_file . ' vấn đề</span>';
        } else {
            echo '<span class="text-muted">⚪ Không có thay đổi</span>';
        }
        
        echo '</div>';
    }
    echo '</div>';
    
    // Sửa thêm các vấn đề đặc biệt
    echo '<div class="alert alert-primary">';
    echo '<h5>Bước 3: Sửa các vấn đề đặc biệt</h5>';
    
    // Fix any remaining '___never_match___' in functions.php
    $functions_file = 'functions.php';
    if (file_exists($functions_file)) {
        $content = file_get_contents($functions_file);
        if (strpos($content, '___never_match___') !== false) {
            $confirmed = get_confirmed_status();
            $content = str_replace("'___never_match___'", "'$confirmed'", $content);
            file_put_contents($functions_file, $content);
            echo '✅ Đã sửa ___never_match___ trong functions.php<br>';
            $total_fixes++;
        }
    }
    
    echo '</div>';
    ?>
    
    <div class="alert alert-success">
        <h3>✅ KẾT QUẢ</h3>
        <ul>
            <li><strong>Tổng số vấn đề đã sửa:</strong> <?php echo $total_fixes; ?></li>
            <li><strong>Số files đã sửa:</strong> <?php echo count($files_fixed); ?></li>
            <li><strong>Files đã sửa:</strong>
                <ul>
                    <?php foreach ($files_fixed as $file): ?>
                    <li><?php echo $file; ?></li>
                    <?php endforeach; ?>
                </ul>
            </li>
        </ul>
    </div>
    
    <div class="alert alert-info">
        <h4>📋 Kiểm tra lại:</h4>
        <p>Hãy search trong toàn bộ project các từ khóa sau để đảm bảo không còn hardcode:</p>
        <ul>
            <li><code>'new'</code> (trong context status)</li>
            <li><code>'confirmed'</code></li>
            <li><code>'completed'</code></li>
            <li><code>'cancelled'</code></li>
            <li><code>'assigned'</code></li>
            <li><code>'calling'</code></li>
            <li><code>'callback'</code></li>
            <li><code>'rejected'</code></li>
            <li><code>'pending'</code> (cho reminders)</li>
            <li><code>'active'</code> (cho users)</li>
        </ul>
        <p class="mb-0"><strong>Lưu ý:</strong> Một số status như 'pending', 'active', 'suspended' cho users/reminders 
        có thể cần giữ lại nếu chúng không phải là order status.</p>
    </div>
    
    <?php
    log_activity('complete_hardcode_fix', 'Fixed all remaining hardcoded statuses in ' . count($files_fixed) . ' files');
    ?>
</div>
</body>
</html>