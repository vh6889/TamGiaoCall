<?php
// File này sẽ tạo hash mới cho password
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

echo "<h2>Password Hash Generator</h2>";
echo "Password: <strong>$password</strong><br><br>";
echo "Generated Hash:<br>";
echo "<textarea style='width:100%; height:60px;'>$hash</textarea><br><br>";

echo "SQL để update:<br>";
echo "<textarea style='width:100%; height:100px;'>";
echo "UPDATE users SET `password` = '$hash' WHERE username = 'admin';";
echo "</textarea><br><br>";

// Test ngay
if (password_verify($password, $hash)) {
    echo "<span style='color:green;'>✓ Verification test PASSED!</span>";
} else {
    echo "<span style='color:red;'>✗ Verification test FAILED!</span>";
}

// Generate for telesale too
echo "<hr><h3>For Telesale</h3>";
$telesale_pass = 'telesale123';
$telesale_hash = password_hash($telesale_pass, PASSWORD_BCRYPT, ['cost' => 10]);
echo "Password: <strong>$telesale_pass</strong><br><br>";
echo "SQL để update:<br>";
echo "<textarea style='width:100%; height:100px;'>";
echo "UPDATE users SET `password` = '$telesale_hash' WHERE username IN ('telesale1', 'telesale2');";
echo "</textarea>";
?>