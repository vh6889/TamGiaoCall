<?php
// fix_pages.php - Chạy 1 lần rồi XÓA
$files = [
    'dashboard.php',
    'orders.php',
    'order-detail.php',
    'profile.php',
    'users.php',
    'kpi.php',
    'statistics.php',
    'settings.php',
    'create-manual-order.php',
    'pending-orders.php',
    'customer-history.php'
];

$add_lines = "
require_once 'config.php';
require_once 'functions.php';
";

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "✗ File not found: $file<br>";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Kiểm tra đã có chưa
    if (strpos($content, "require_once 'config.php';") !== false) {
        echo "- Already fixed: $file<br>";
        continue;
    }
    
    // Tìm dòng define('TSM_ACCESS', true);
    $pattern = "/(define\('TSM_ACCESS',\s*true\);)/";
    
    if (preg_match($pattern, $content)) {
        // Thêm require_once ngay sau define
        $content = preg_replace(
            $pattern,
            "$1" . $add_lines,
            $content,
            1
        );
        
        file_put_contents($file, $content);
        echo "✓ Fixed: $file<br>";
    } else {
        echo "✗ Cannot find define() in: $file<br>";
    }
}

echo "<br><strong style='color: red;'>DONE! Delete this file now: fix_pages.php</strong>";
?>