<?php
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

echo "<h2>Testing Login Function</h2>";

$username = 'admin';
$password = 'admin123';

echo "Attempting login with:<br>";
echo "Username: $username<br>";
echo "Password: $password<br><br>";

// Get user from database
$user = db_get_row(
    "SELECT * FROM users WHERE username = ? AND status = 'active'",
    [$username]
);

if (!$user) {
    echo "✗ User not found or not active!<br>";
} else {
    echo "✓ User found:<br>";
    echo "<pre>";
    print_r([
        'id' => $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
        'status' => $user['status']
    ]);
    echo "</pre>";
    
    echo "Password hash in DB: " . substr($user['password'], 0, 30) . "...<br><br>";
    
    // Test password verify
    if (password_verify($password, $user['password'])) {
        echo "✓ Password verified successfully!<br><br>";
        
        // Test login function
        echo "Testing login_user() function:<br>";
        if (login_user($username, $password)) {
            echo "✓ login_user() returned TRUE<br>";
            echo "Session data:<br>";
            echo "<pre>";
            print_r([
                'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
                'username' => $_SESSION['username'] ?? 'NOT SET',
                'role' => $_SESSION['role'] ?? 'NOT SET'
            ]);
            echo "</pre>";
        } else {
            echo "✗ login_user() returned FALSE<br>";
        }
    } else {
        echo "✗ Password verification FAILED!<br>";
        echo "Expected hash to match password: $password<br>";
    }
}
?>