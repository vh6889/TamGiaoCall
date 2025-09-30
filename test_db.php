<?php
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

echo "Testing database connection...<br><br>";

try {
    $pdo = get_db_connection();
    echo "✓ Database connected successfully!<br><br>";
    
    // Test query users
    $users = db_get_results("SELECT id, username, full_name, role, status FROM users");
    
    echo "Users in database:<br>";
    echo "<pre>";
    print_r($users);
    echo "</pre>";
    
    // Test admin user specifically
    echo "<br>Testing admin user:<br>";
    $admin = db_get_row("SELECT * FROM users WHERE username = ?", ['admin']);
    if ($admin) {
        echo "✓ Admin user found!<br>";
        echo "Username: " . $admin['username'] . "<br>";
        echo "Status: " . $admin['status'] . "<br>";
        echo "Password hash: " . substr($admin['password'], 0, 20) . "...<br>";
    } else {
        echo "✗ Admin user NOT found!<br>";
    }
    
    // Test password verification
    echo "<br>Testing password verification:<br>";
    $test_password = 'admin123';
    $stored_hash = '$2y$10$IF4A7b64aL4JzG9Sj/qKLu5R6.d1W2A/WfGsr2j2aEUP87jBwR/S6';
    
    if (password_verify($test_password, $stored_hash)) {
        echo "✓ Password 'admin123' matches the hash!<br>";
    } else {
        echo "✗ Password 'admin123' does NOT match!<br>";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}
?>