<?php
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

echo "<h2>Debug Session và Get Current User</h2>";

echo "<h3>1. Check Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>2. Check is_logged_in():</h3>";
echo is_logged_in() ? "✓ TRUE" : "✗ FALSE";
echo "<br><br>";

if (isset($_SESSION['user_id'])) {
    echo "<h3>3. Direct Database Query:</h3>";
    $user_id = $_SESSION['user_id'];
    echo "Querying user_id: $user_id<br>";
    
    $user = db_get_row(
        "SELECT * FROM users WHERE id = ? AND status = 'active'",
        [$user_id]
    );
    
    echo "Result:<br><pre>";
    var_dump($user);
    echo "</pre>";
    echo "Type: " . gettype($user) . "<br>";
    echo "Is array: " . (is_array($user) ? 'YES' : 'NO') . "<br>";
    
    if ($user && is_array($user)) {
        echo "<br>User data found:<br>";
        echo "ID: " . $user['id'] . "<br>";
        echo "Username: " . $user['username'] . "<br>";
        echo "Full name: " . $user['full_name'] . "<br>";
        echo "Role: " . $user['role'] . "<br>";
    }
}

echo "<h3>4. Test get_current_user() function:</h3>";
$current_user = get_current_user();
echo "<pre>";
var_dump($current_user);
echo "</pre>";
echo "Type: " . gettype($current_user) . "<br>";
echo "Is array: " . (is_array($current_user) ? 'YES' : 'NO') . "<br>";

echo "<h3>5. Test PDO Fetch Mode:</h3>";
$pdo = get_db_connection();
echo "PDO Fetch Mode: ";
var_dump($pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
?>