<?php
/**
 * User Profile Page
 */
define('TSM_ACCESS', true);


require_login();

$page_title = 'Hồ sơ cá nhân';
$current_user = get_current_user();
$user_id = $current_user['id'];

// Xử lý cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    
    if (empty($full_name) || empty($email)) {
        set_flash('error', 'Vui lòng không để trống Họ tên và Email.');
    } elseif (!is_valid_email($email)) {
        set_flash('error', 'Email không hợp lệ.');
    } else {
        db_update('users', [
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone
        ], 'id = ?', [$user_id]);
        
        set_flash('success', 'Đã cập nhật thông tin thành công!');
        redirect('profile.php');
    }
}

// Xử lý đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!password_verify($current_password, $current_user['password'])) {
        set_flash('error', 'Mật khẩu hiện tại không đúng.');
    } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
        set_flash('error', 'Mật khẩu mới phải có ít nhất ' . PASSWORD_MIN_LENGTH . ' ký tự.');
    } elseif ($new_password !== $confirm_password) {
        set_flash('error', 'Mật khẩu xác nhận không khớp.');
    } else {
        db_update('users', [
            'password' => hash_password($new_password)
        ], 'id = ?', [$user_id]);
        
        set_flash('success', 'Đã đổi mật khẩu thành công!');
        redirect('profile.php');
    }
}


include 'includes/header.php';
?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="table-card">
            <h5 class="mb-3">Thông tin cá nhân</h5>
            <?php display_flash(); ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['username']); ?>" disabled>
                </div>
                <div class="mb-3">
                    <label for="full_name" class="form-label">Họ và tên</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Số điện thoại</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($current_user['phone']); ?>">
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Lưu thay đổi
                </button>
            </form>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="table-card">
            <h5 class="mb-3">Đổi mật khẩu</h5>
            <form method="POST">
                <div class="mb-3">
                    <label for="current_password" class="form-label">Mật khẩu hiện tại</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <div class="mb-3">
                    <label for="new_password" class="form-label">Mật khẩu mới</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-success">
                    <i class="fas fa-key me-2"></i>Đổi mật khẩu
                </button>
            </form>
        </div>
    </div>
</div>


<?php
include 'includes/footer.php';
?>