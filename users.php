<?php
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

// Allow both admin and manager, but with different permissions
if (!is_logged_in() || (!is_admin() && !is_manager())) {
    redirect('dashboard.php?error=access_denied');
    exit;
}

$current_user = get_logged_user();
$page_title = is_admin() ? 'Quản lý nhân viên' : 'Xem nhân viên';

// Manager only sees their assigned telesales
if (is_manager()) {
    $users = db_get_results("
        SELECT u.*, COUNT(o.id) as pending_orders
        FROM users u
        INNER JOIN manager_assignments ma ON u.id = ma.telesale_id
        LEFT JOIN orders o ON u.id = o.assigned_to 
            AND o.primary_label NOT IN (
                SELECT label_key FROM order_labels 
                WHERE label_name LIKE '%mới%' 
                   OR label_name LIKE '%hoàn%' 
                   OR label_name LIKE '%hủy%'
            )
        WHERE ma.manager_id = ?
        GROUP BY u.id
        ORDER BY u.created_at DESC",
        [$current_user['id']]
    );
} else {
    // Admin sees all
    $users = db_get_results("
        SELECT u.*, COUNT(o.id) as pending_orders
        FROM users u
        LEFT JOIN orders o ON u.id = o.assigned_to 
            AND o.primary_label NOT IN (
                SELECT label_key FROM order_labels 
                WHERE label_name LIKE '%mới%' 
                   OR label_name LIKE '%hoàn%' 
                   OR label_name LIKE '%hủy%'
            )
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
}

// Lấy danh sách telesale đang hoạt động để bàn giao (giữ nguyên cho admin)
$active_telesales = get_telesales('active');

include 'includes/header.php';
?>

<div class="table-card">
    <?php if (is_admin()): ?>
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="fas fa-user-plus me-2"></i> Tạo nhân viên mới
        </button>
    </div>
    <?php elseif (is_manager()): ?>
    <div class="alert alert-info mb-3">
        <i class="fas fa-info-circle me-2"></i>
        Bạn đang xem danh sách nhân viên được phân công cho mình quản lý.
    </div>
    <?php endif; ?>