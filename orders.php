<?php
/**
 * Orders Page - Dynamic Status Version
 * Hiển thị danh sách đơn hàng với filter động theo status
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/status_helper.php';

require_login();

$current_user = get_logged_user();
$page_title = 'Quản lý đơn hàng';

// Get filters from URL
$filter_status = $_GET['status'] ?? 'all';
$filter_search = $_GET['search'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Build filters array
$filters = [
    'page' => $page,
    'per_page' => ITEMS_PER_PAGE
];

if ($filter_status !== 'all') {
    $filters['status'] = $filter_status;
}

if (!empty($filter_search)) {
    $filters['search'] = $filter_search;
}

if (!empty($filter_date_from)) {
    $filters['date_from'] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $filters['date_to'] = $filter_date_to;
}

// If telesale, only show their orders
if (!is_admin() && !is_manager()) {
    $filters['assigned_to'] = $current_user['id'];
}

// Get orders with dynamic status info
$orders = get_orders($filters);
$total_orders = count_orders($filters);
$total_pages = ceil($total_orders / ITEMS_PER_PAGE);

// Lấy danh sách telesale cho modal phân công
$telesales_list = is_admin() || is_manager() ? get_telesales('active') : [];

// Lấy tất cả USER-DEFINED statuses (không bao gồm free/assigned)
$user_statuses = get_all_statuses();
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="fas fa-shopping-cart"></i> Quản lý đơn hàng</h1>
        <?php if (is_admin() || is_manager()): ?>
        <div>
            <a href="create-order.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tạo đơn mới
            </a>
            <a href="import-orders.php" class="btn btn-success">
                <i class="fas fa-file-import"></i> Import Excel
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-body">
            <!-- Nav tabs với status động - CHỈ HIỂN THỊ USER-DEFINED STATUSES -->
            <ul class="nav nav-tabs mb-3" style="flex-wrap: wrap;">
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter_status === 'all' ? 'active' : ''; ?>" 
                       href="?status=all">
                        <i class="fas fa-list"></i> Tất cả 
                        <span class="badge bg-secondary">
                            <?php echo count_orders(is_admin() || is_manager() ? [] : ['assigned_to' => $current_user['id']]); ?>
                        </span>
                    </a>
                </li>
                
                <?php foreach($user_statuses as $status): ?>
                    <?php 
                    $count = count_orders_by_status($status['value'], (!is_admin() && !is_manager()) ? $current_user['id'] : null);
                    if ($count > 0 || $filter_status === $status['value']): // Chỉ hiện status có đơn
                    ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter_status === $status['value'] ? 'active' : ''; ?>" 
                           href="?status=<?php echo urlencode($status['value']); ?>"
                           style="<?php echo $filter_status === $status['value'] ? 'background-color: ' . $status['color'] . '20; border-color: ' . $status['color'] : ''; ?>">
                            <i class="fas <?php echo htmlspecialchars($status['icon']); ?>" 
                               style="color: <?php echo htmlspecialchars($status['color']); ?>"></i>
                            <?php echo htmlspecialchars($status['text']); ?>
                            <span class="badge" style="background-color: <?php echo htmlspecialchars($status['color']); ?>">
                                <?php echo $count; ?>
                            </span>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            
            <!-- Form filter -->
            <form method="GET" class="row g-3 mb-3">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               placeholder="Tìm theo SĐT, tên, mã đơn..."
                               value="<?php echo htmlspecialchars($filter_search); ?>">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <input type="date" 
                           class="form-control" 
                           name="date_from" 
                           placeholder="Từ ngày"
                           value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                
                <div class="col-md-2">
                    <input type="date" 
                           class="form-control" 
                           name="date_to" 
                           placeholder="Đến ngày"
                           value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i>Lọc
                    </button>
                </div>
                
                <div class="col-md-2">
                    <a href="orders.php" class="btn btn-secondary w-100">
                        <i class="fas fa-redo me-1"></i>Làm mới
                    </a>
                </div>
            </form>
            
            <!-- Bảng đơn hàng -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="100">Mã đơn</th>
                            <th>Khách hàng</th>
                            <th width="120">SĐT</th>
                            <th>Địa chỉ</th>
                            <th width="120">Tổng tiền</th>
                            <th width="150">Trạng thái</th>
                            <th width="120">Người xử lý</th>
                            <th width="100">Ngày tạo</th>
                            <th width="120">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-4x mb-3 d-block opacity-25"></i>
                                <h5>Không có đơn hàng nào</h5>
                                <p>Thử thay đổi bộ lọc hoặc tạo đơn mới</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                            <tr id="order-row-<?php echo $order['id']; ?>" class="order-row">
                                <td>
                                    <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="text-decoration-none">
                                        <strong class="text-primary">#<?php echo htmlspecialchars($order['order_number']); ?></strong>
                                    </a>
                                    <?php if ($order['call_count'] > 0): ?>
                                    <br><small class="text-muted">
                                        <i class="fas fa-phone"></i> <?php echo $order['call_count']; ?> cuộc gọi
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                                    <?php if (!empty($order['customer_email'])): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="tel:<?php echo $order['customer_phone']; ?>" class="text-decoration-none">
                                        <i class="fas fa-phone-alt text-success"></i>
                                        <?php echo htmlspecialchars($order['customer_phone']); ?>
                                    </a>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars(truncate($order['customer_address'] ?? 'N/A', 50)); ?></small>
                                </td>
                                <td>
                                    <strong class="text-success"><?php echo format_money($order['total_amount']); ?></strong>
                                </td>
                                <td>
                                    <?php echo format_status_badge($order['primary_label'] ?? $order['status']); ?>
                                    <?php if (!empty($order['callback_time']) && $order['callback_time'] != '0000-00-00 00:00:00'): ?>
                                    <br><small class="text-muted">
                                        <i class="fas fa-clock"></i> <?php echo format_date($order['callback_time'], 'd/m H:i'); ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td class="assigned-user-<?php echo $order['id']; ?>">
                                    <?php if (!empty($order['assigned_to'])): ?>
                                        <?php 
                                        $assigned_user = get_user($order['assigned_to']);
                                        if ($assigned_user && is_array($assigned_user)): ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($assigned_user['full_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Chưa gán</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo format_date($order['created_at'], 'd/m/Y'); ?></small>
                                    <br>
                                    <small class="text-muted"><?php echo format_date($order['created_at'], 'H:i'); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="order-detail.php?id=<?php echo $order['id']; ?>" 
                                           class="btn btn-info" 
                                           title="Xem chi tiết">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php 
                                        // Kiểm tra đơn có thể nhận không: status = free VÀ chưa gán
                                        if ($order['system_status'] === 'free' && empty($order['assigned_to']) && !is_admin() && !is_manager()):
                                        ?>
                                            <button class="btn btn-primary btn-claim-order" 
                                                    data-order-id="<?php echo $order['id']; ?>"
                                                    title="Nhận đơn">
                                                <i class="fas fa-hand-paper"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (is_admin() || is_manager()): ?>
                                            <button class="btn btn-warning btn-assign" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#assignOrderModal" 
                                                    data-order-id="<?php echo $order['id']; ?>" 
                                                    title="Phân công">
                                                <i class="fas fa-user-plus"></i>
                                            </button>
                                            
                                            <?php if (is_admin()): ?>
                                            <button class="btn btn-danger btn-delete" 
                                                    onclick="deleteOrder(<?php echo $order['id']; ?>)"
                                                    title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($filter_search); ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page || $i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($filter_search); ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($filter_search); ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="text-center text-muted">
                <small>Hiển thị <?php echo count($orders); ?> / <?php echo $total_orders; ?> đơn hàng</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal phân công đơn hàng -->
<?php if (is_admin() || is_manager()): ?>
<div class="modal fade" id="assignOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus"></i> Phân công đơn hàng
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assignOrderForm">
                    <input type="hidden" id="assignOrderId" name="order_id">
                    <div class="mb-3">
                        <label for="assignToUser" class="form-label">Chọn nhân viên để phân công:</label>
                        <select class="form-select" id="assignToUser" name="user_id" required>
                            <option value="">-- Chọn nhân viên --</option>
                            <?php foreach ($telesales_list as $ts): ?>
                            <option value="<?php echo $ts['id']; ?>">
                                <?php echo htmlspecialchars($ts['full_name']); ?>
                                <?php if ($ts['role'] === 'manager'): ?>
                                    <span class="badge bg-warning">Manager</span>
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="assignNote" class="form-label">Ghi chú (tùy chọn):</label>
                        <textarea class="form-control" id="assignNote" name="note" rows="3" 
                                  placeholder="Nhập ghi chú về việc phân công này..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="btnSaveAssignment">
                    <i class="fas fa-save"></i> Lưu phân công
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
// Universal AJAX error handler
function handleAjaxError(xhr, textStatus, errorThrown) {
    console.error('AJAX Error:', {xhr, textStatus, errorThrown});
    
    let message = 'Có lỗi xảy ra';
    
    try {
        const response = JSON.parse(xhr.responseText);
        if (response.message) {
            message = response.message;
        }
    } catch (e) {
        if (xhr.status === 0) {
            message = 'Không thể kết nối server';
        } else if (xhr.status === 404) {
            message = 'API không tồn tại (404)';
        } else if (xhr.status === 500) {
            message = 'Lỗi server (500)';
        } else if (xhr.status === 401) {
            message = 'Chưa đăng nhập';
        } else if (xhr.status === 403) {
            message = 'Không có quyền';
        } else if (xhr.responseText) {
            message = xhr.responseText.substring(0, 100);
        }
    }
    
    showToast(message, 'error');
}

$(document).ready(function() {
    // Claim order
    $('.btn-claim-order').click(function() {
        const orderId = $(this).data('order-id');
        const button = $(this);
        
        if (!confirm('Bạn có chắc muốn nhận đơn hàng này?')) {
            return;
        }
        
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: 'api/claim-order.php',
            method: 'POST',
            data: JSON.stringify({ 
                csrf_token: '<?php echo generate_csrf_token(); ?>',
                order_id: orderId 
            }),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    showToast('Đã nhận đơn hàng thành công!', 'success');
                    setTimeout(function() {
                        window.location.href = 'order-detail.php?id=' + orderId;
                    }, 1000);
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                    button.prop('disabled', false).html('<i class="fas fa-hand-paper"></i>');
                }
            },
            error: handleAjaxError
        });
    });

    // Assign order modal - populate order ID
    var assignModalEl = document.getElementById('assignOrderModal');
    if (assignModalEl) {
        assignModalEl.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var orderId = button.getAttribute('data-order-id');
            var modalOrderIdInput = assignModalEl.querySelector('#assignOrderId');
            modalOrderIdInput.value = orderId;
        });
    }

    // Save assignment
    $('#btnSaveAssignment').click(function() {
        const orderId = $('#assignOrderId').val();
        const userId = $('#assignToUser').val();
        const note = $('#assignNote').val();
        
        if (!userId) {
            showToast('Vui lòng chọn một nhân viên.', 'error');
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang xử lý...');

        $.ajax({
            url: 'api/assign-order.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ 
                csrf_token: '<?php echo generate_csrf_token(); ?>',
                order_id: orderId, 
                user_id: userId,
                note: note 
            }),
            success: function(response) {
                if (response.success) {
                    showToast('Đã phân công đơn hàng thành công!', 'success');
                    const selectedUserName = $('#assignToUser option:selected').text();
                    
                    // Cập nhật giao diện không cần reload
                    $('#order-row-' + orderId + ' .assigned-user-' + orderId).html(
                        '<span class="badge bg-info"><i class="fas fa-user"></i> ' + selectedUserName + '</span>'
                    );
                    
                    // Hide modal
                    var modal = bootstrap.Modal.getInstance(assignModalEl);
                    modal.hide();
                    
                    // Reset form
                    $('#assignOrderForm')[0].reset();
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                }
            },
            error: handleAjaxError,
            complete: function() { 
                btn.prop('disabled', false).html('<i class="fas fa-save"></i> Lưu phân công'); 
            }
        });
    });
});

function deleteOrder(orderId) {
    if (!confirm('Bạn có chắc chắn muốn xóa đơn hàng này?\n\nLưu ý: Tất cả dữ liệu liên quan sẽ bị xóa vĩnh viễn!')) {
        return;
    }
    
    $.ajax({
        url: 'api/delete-order.php',
        method: 'POST',
        data: JSON.stringify({ 
            csrf_token: '<?php echo generate_csrf_token(); ?>',
            order_id: orderId 
        }),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                showToast('Đã xóa đơn hàng', 'success');
                $('#order-row-' + orderId).fadeOut(500, function() {
                    $(this).remove();
                });
            } else {
                showToast(response.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: handleAjaxError
    });
}

// Hover effect cho rows
$('.order-row').hover(
    function() { $(this).addClass('table-active'); },
    function() { $(this).removeClass('table-active'); }
);

// Toast notification function
function showToast(message, type = 'info') {
    if (!$('#toastContainer').length) {
        $('body').append('<div id="toastContainer" style="position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;"></div>');
    }
    
    const alertClass = type === 'error' ? 'danger' : type;
    const icon = {
        'success': 'fa-check-circle',
        'error': 'fa-exclamation-circle',
        'warning': 'fa-exclamation-triangle',
        'info': 'fa-info-circle'
    }[type] || 'fa-info-circle';
    
    const toast = $(`
        <div class="alert alert-${alertClass} alert-dismissible fade show shadow-sm">
            <i class="fas ${icon} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('#toastContainer').append(toast);
    setTimeout(() => toast.alert('close'), 4000);
}
</script>

<?php include 'includes/footer.php'; ?>