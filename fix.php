<?php
/**
 * SCRIPT TỰ ĐỘNG SỬA TẤT CẢ CÁC FILE
 * Chuyển đổi từ logic cũ sang logic mới
 */

define('TSM_ACCESS', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes

// Các pattern cần tìm và thay thế
$replacements = [
    // Table names
    'order_status_configs' => 'order_labels',
    
    // Column names
    'status_key' => 'label_key',
    "'label'" => "'label_name'",
    '"label"' => '"label_name"',
    '`label`' => '`label_name`',
    
    // Trong SELECT queries
    'SELECT label ' => 'SELECT label_name AS label ',
    'SELECT label_key AS status_key, ' => 'SELECT label_key AS status_key',
    'SELECT label_key AS status_key, ' => 'SELECT label_key AS status_key,',
    
    // Function calls (chỉ replace trong một số trường hợp cụ thể)
];

// Các file KHÔNG được sửa (quan trọng!)
$exclude_files = [
    'fix_all_files.php',
    'test_phase2.php',
    'migration_phase1.sql',
    'config.php',
    '.git',
    'vendor',
    'node_modules',
    'backup'
];

// Các thư mục cần quét
$scan_dirs = [
    __DIR__,
    __DIR__ . '/includes',
    __DIR__ . '/api',
];

$files_fixed = [];
$errors = [];

/**
 * Kiểm tra file có nên bỏ qua không
 */
function should_skip_file($filepath) {
    global $exclude_files;
    
    $filename = basename($filepath);
    
    // Skip exclude files
    foreach ($exclude_files as $exclude) {
        if (strpos($filepath, $exclude) !== false) {
            return true;
        }
    }
    
    // Chỉ xử lý file PHP
    if (pathinfo($filepath, PATHINFO_EXTENSION) !== 'php') {
        return true;
    }
    
    return false;
}

/**
 * Sửa nội dung file
 */
function fix_file_content($content) {
    $original = $content;
    
    // 1. Replace table name trong queries
    $content = preg_replace(
        '/FROM\s+`?order_status_configs`?/i',
        'FROM order_labels',
        $content
    );
    
    $content = preg_replace(
        '/JOIN\s+`?order_status_configs`?\s+/i',
        'JOIN order_labels ',
        $content
    );
    
    // 2. Replace column names trong SELECT
    $content = preg_replace(
        '/SELECT\s+status_key\s+as\s+value/i',
        'SELECT label_key AS status_key, label_key AS value',
        $content
    );
    
    $content = preg_replace(
        '/SELECT\s+status_key,?\s*/i',
        'SELECT label_key AS status_key, ',
        $content
    );
    
    // 3. Replace label column
    $content = preg_replace(
        '/,\s*label\s+as\s+text/i',
        ', label_name AS label, label_name AS text',
        $content
    );
    
    $content = preg_replace(
        '/SELECT\s+label\s+FROM/i',
        'SELECT label_name AS label FROM',
        $content
    );
    
    // 4. Replace WHERE conditions
    $content = preg_replace(
        '/WHERE\s+status_key\s*=/i',
        'WHERE label_key =',
        $content
    );
    
    // 5. Fix specific function calls
    $content = str_replace(
        'get_order_labels(true)',
        'get_order_labels(true)',
        $content
    );
    
    return $content !== $original ? $content : false;
}

/**
 * Scan và fix files
 */
function scan_and_fix($dir) {
    global $files_fixed, $errors;
    
    if (!is_dir($dir)) {
        return;
    }
    
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $filepath = $dir . '/' . $file;
        
        // Đệ quy với subdirectories
        if (is_dir($filepath)) {
            scan_and_fix($filepath);
            continue;
        }
        
        // Skip files
        if (should_skip_file($filepath)) {
            continue;
        }
        
        try {
            // Đọc file
            $content = file_get_contents($filepath);
            
            // Kiểm tra có cần sửa không
            if (strpos($content, 'order_status_configs') === false) {
                continue; // File không có gì cần sửa
            }
            
            // Backup
            $backup_path = $filepath . '.backup_' . date('Ymd_His');
            file_put_contents($backup_path, $content);
            
            // Fix content
            $new_content = fix_file_content($content);
            
            if ($new_content !== false) {
                // Ghi lại file
                file_put_contents($filepath, $new_content);
                
                $files_fixed[] = [
                    'file' => str_replace(__DIR__ . '/', '', $filepath),
                    'backup' => str_replace(__DIR__ . '/', '', $backup_path)
                ];
            } else {
                // Không có gì thay đổi, xóa backup
                unlink($backup_path);
            }
            
        } catch (Exception $e) {
            $errors[] = "Error in {$filepath}: " . $e->getMessage();
        }
    }
}

// ==========================================
// EXECUTE
// ==========================================

echo "<!DOCTYPE html>
<html>
<head>
    <title>Auto Fix All Files</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #569cd6; }
        .file-item { padding: 5px; margin: 5px 0; background: #252526; border-left: 3px solid #007acc; }
        pre { background: #1e1e1e; padding: 10px; border: 1px solid #3e3e3e; }
        .btn { 
            display: inline-block;
            padding: 10px 20px; 
            margin: 10px 5px;
            background: #0e639c; 
            color: white; 
            text-decoration: none; 
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover { background: #1177bb; }
        .btn-danger { background: #a1260d; }
        .btn-danger:hover { background: #c72e0d; }
    </style>
</head>
<body>";

echo "<h1>🔧 AUTO FIX ALL FILES</h1>";
echo "<p class='info'>Đang quét và sửa tất cả các file...</p>";

// Scan directories
foreach ($scan_dirs as $dir) {
    scan_and_fix($dir);
}

// Display results
echo "<h2 class='success'>✅ Kết quả:</h2>";
echo "<p><strong>Đã sửa: " . count($files_fixed) . " files</strong></p>";

if (!empty($files_fixed)) {
    echo "<h3>Các file đã được sửa:</h3>";
    foreach ($files_fixed as $item) {
        echo "<div class='file-item'>";
        echo "📄 <strong>{$item['file']}</strong><br>";
        echo "💾 Backup: {$item['backup']}";
        echo "</div>";
    }
}

if (!empty($errors)) {
    echo "<h3 class='error'>❌ Lỗi:</h3>";
    foreach ($errors as $error) {
        echo "<p class='error'>$error</p>";
    }
}

echo "<hr>";
echo "<h2>📝 Bước tiếp theo:</h2>";
echo "<ol>";
echo "<li>Kiểm tra danh sách file đã sửa ở trên</li>";
echo "<li>Test hệ thống: <a href='dashboard.php' class='btn'>Vào Dashboard</a></li>";
echo "<li>Nếu có lỗi, khôi phục từ backup</li>";
echo "</ol>";

echo "<hr>";
echo "<a href='dashboard.php' class='btn'>🚀 Test Dashboard</a>";
echo "<a href='manage-order-labels.php' class='btn'>📋 Quản lý Nhãn</a>";
echo "<button class='btn btn-danger' onclick='if(confirm(\"Xóa tất cả file backup?\")) window.location.href=\"?cleanup=1\"'>🗑️ Xóa Backup</button>";

// Cleanup backups
if (isset($_GET['cleanup'])) {
    $deleted = 0;
    foreach ($files_fixed as $item) {
        $backup_file = __DIR__ . '/' . $item['backup'];
        if (file_exists($backup_file)) {
            unlink($backup_file);
            $deleted++;
        }
    }
    echo "<script>alert('Đã xóa {$deleted} file backup!'); window.location.href='fix_all_files.php';</script>";
}

echo "</body></html>";