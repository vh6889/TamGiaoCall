<?php
/**
 * Settings Page (Admin only)
 */
define('TSM_ACCESS', true);
require_once '../system/config.php';
require_once '../system/functions.php';



require_admin();

$page_title = 'Cài đặt hệ thống';

// Lấy các cài đặt hiện tại từ database để hiển thị
$settings = [
    'site_name' => get_setting('site_name', SITE_NAME),
    'woo_api_url' => get_setting('woo_api_url', ''),
    'woo_consumer_key' => get_setting('woo_consumer_key', ''),
    'woo_consumer_secret' => get_setting('woo_consumer_secret', '')
];


include '../includes/header.php';
?>

<div class="table-card">
    <h5 class="mb-3"><i class="fas fa-cogs me-2"></i>Cài đặt hệ thống</h5>
    <p>Cấu hình các thông số quan trọng cho hệ thống, bao gồm cả việc tích hợp với WooCommerce.</p>
    <hr>
    
    <form id="settingsForm">
        <h6 class="mt-4">Cài đặt chung</h6>
        <div class="mb-3">
            <label for="site_name" class="form-label">Tên hệ thống</label>
            <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
            <small class="form-text text-muted">Tên sẽ hiển thị trên trang đăng nhập và tiêu đề trang.</small>
        </div>

        <h6 class="mt-4">Tích hợp WooCommerce</h6>
        <div class="mb-3">
            <label for="woo_api_url" class="form-label">WooCommerce Site URL</label>
            <input type="url" class="form-control" id="woo_api_url" name="woo_api_url" placeholder="https://your-domain.com" value="<?php echo htmlspecialchars($settings['woo_api_url']); ?>">
            <small class="form-text text-muted">URL của website WordPress cài đặt WooCommerce.</small>
        </div>
        <div class="mb-3">
            <label for="woo_consumer_key" class="form-label">Consumer Key</label>
            <input type="text" class="form-control" id="woo_consumer_key" name="woo_consumer_key" value="<?php echo htmlspecialchars($settings['woo_consumer_key']); ?>">
        </div>
        <div class="mb-3">
            <label for="woo_consumer_secret" class="form-label">Consumer Secret</label>
            <input type="password" class="form-control" id="woo_consumer_secret" name="woo_consumer_secret" value="<?php echo htmlspecialchars($settings['woo_consumer_secret']); ?>">
        </div>

        <hr>
        <button type="submit" class="btn btn-primary" id="btnSaveSettings">
            <i class="fas fa-save me-2"></i>Lưu thay đổi
        </button>
    </form>
</div>

<script>
$(document).ready(function() {
    $('#settingsForm').submit(function(e) {
        e.preventDefault();
        
        const btn = $('#btnSaveSettings');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Đang lưu...');

        const settingsData = {
            site_name: $('#site_name').val(),
            woo_api_url: $('#woo_api_url').val(),
            woo_consumer_key: $('#woo_consumer_key').val(),
            woo_consumer_secret: $('#woo_consumer_secret').val()
        };

        $.ajax({
            url: 'api/save-settings.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(settingsData),
            success: function(response) {
                if (response.success) {
                    showToast('Đã lưu cài đặt thành công!', 'success');
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                }
            },
            error: function() {
                showToast('Không thể kết nối đến máy chủ.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Lưu thay đổi');
            }
        });
    });
});
</script>

<?php
include '../includes/footer.php';
?>