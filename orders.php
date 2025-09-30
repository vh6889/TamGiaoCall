<?php
/**
 * Orders Page
 */
define('TSM_ACCESS', true);


require_login();

$current_user = get_current_user();
$page_title = 'Quản lý đơn hàng';

// Get filters
$filter_status = $_GET['status'] ?? 'all';
$filter_search = $_GET['search'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Build filters
$filters = [
    'page' => $page,
    'per_page' => ITEMS_PER_PAGE
];

if ($filter_status !== 'all') {
    if ($filter_status === 'available') {
        $filters['available'] = true;
    } else {
        $filters['status'] = $filter_status;
    }
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

// If telesale, only show their orders or available orders
if (!is_admin() && $filter_status !== 'available') {
    $filters['assigned_to'] = $current_user['id'];
}

// Get orders
$orders = get_orders($filters);
$total_orders = count_orders($filters);
$total_pages = ceil($total_orders / ITEMS_PER_PAGE);

// Get status counts for tabs
$status_counts = [
    'all' => count_orders(is_admin() ? [] : ['assigned_to' => $current_user['id']]),
    'available' => count_orders(['available' => true]),
    'new' => count_orders(['status' => 'new']),
    'assigned' => count_orders(['status' => 'assigned']),
    'calling' => count_orders(['status' => 'calling']),
    'confirmed' => count_orders(['status' => 'confirmed']),
    'callback' => count_orders(['status' => 'callback'])
];

// === PHẦN BỔ SUNG BẮT ĐẦU (1): Lấy danh sách telesale cho modal ===
$telesales_list = is_admin() ? get_telesales('active') : [];
// === PHẦN BỔ SUNG KẾT THÚC (1) ===

include 'includes/header.php';
?>

<div class="table-card">
        <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'all' ? 'active' : ''; ?>" 
               href="?status=all">
                Tất cả 
                <span class="badge bg-secondary"><?php echo $status_counts['all']; ?></span>
            </a>
        </li>
        <?php if (!is_admin()): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'available' ? 'active' : ''; ?>" 
               href="?status=available">
                Có thể nhận 
                <span class="badge bg-primary"><?php echo $status_counts['available']; ?></span>
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'new' ? 'active' : ''; ?>" 
               href="?status=new">
                Đơn mới 
                <span class="badge bg-primary"><?php echo $status_counts['new']; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'assigned' ? 'active' : ''; ?>" 
               href="?status=assigned">
                Đã nhận 
                <span class="badge bg-info"><?php echo $status_counts['assigned']; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'calling' ? 'active' : ''; ?>" 
               href="?status=calling">
                Đang gọi 
                <span class="badge bg-warning"><?php echo $status_counts['calling']; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'confirmed' ? 'active' : ''; ?>" 
               href="?status=confirmed">
                Đã xác nhận 
                <span class="badge bg-success"><?php echo $status_counts['confirmed']; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'callback' ? 'active' : ''; ?>" 
               href="?status=callback">
                Gọi lại 
                <span class="badge bg-info"><?php echo $status_counts['callback']; ?></span>
            </a>
        </li>
    </ul>
    
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
        
        <div class="col-md-3">
            <input type="date" 
                   class="form-control" 
                   name="date_from" 
                   placeholder="Từ ngày"
                   value="<?php echo htmlspecialchars($filter_date_from); ?>">
        </div>
        
        <div class="col-md-3">
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
    </form>
    
        <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th width="100">Mã đơn</th>
                    <th>Khách hàng</th>
                    <th>SĐT</th>
                    <th>Địa chỉ</th>
                    <th width="120">Tổng tiền</th>
                    <th width="120">Trạng thái</th>
                    <?php if (is_admin()): ?>
                    <th>Người xử lý</th>
                    <?php endif; ?>
                    <th width="100">Ngày tạo</th>
                    <th width="150">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="<?php echo is_admin() ? 9 : 8; ?>" class="text-center text-muted py-5">
                        <i class="fas fa-inbox fa-4x mb-3 d-block opacity-25"></i>
                        <h5>Không có đơn hàng nào</h5>
                        <?php if ($filter_status === 'available'): ?>
                        <p>Hiện tại không có đơn hàng mới để nhận</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr id="order-row-<?php echo $order['id']; // ID cho JS cập nhật ?>">
                        <td>
                            <strong class="text-primary">#<?php echo htmlspecialchars($order['order_number']); ?></strong>
                            <?php if ($order['call_count'] > 0): ?>
                            <br><small class="text-muted">
                                <i class="fas fa-phone"></i> <?php echo $order['call_count']; ?> cuộc gọi
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                            <?php if ($order['customer_email']): ?>
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
                            <strong><?php echo format_money($order['total_amount']); ?></strong>
                        </td>
                        <td>
                            <?php echo get_status_badge($order['status']); ?>
                        </td>
                        <?php if (is_admin()): ?>
                        <td class="assigned-user-<?php echo $order['id']; // ID cho JS cập nhật ?>">
                            <?php if ($order['assigned_to']): ?>
                                <?php 
                                $assigned_user = get_user($order['assigned_to']);
                                echo $assigned_user ? htmlspecialchars($assigned_user['full_name']) : 'N/A';
                                ?>
                            <?php else: ?>
                                <span class="text-muted">Chưa gán</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <small><?php echo format_date($order['created_at'], 'd/m/Y'); ?></small>
                            <br>
                            <small class="text-muted"><?php echo format_date($order['created_at'], 'H:i'); ?></small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="order-detail.php?id=<?php echo $order['id']; ?>" 
                                   class="btn btn-info" 
                                   title="Xem chi tiết">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if ($order['status'] === 'new' && !$order['assigned_to'] && !is_admin()): // === DÒNG NÀY ĐÃ SỬA LẠI CHO ĐÚNG LOGIC CỦA BẠN === ?>
                                    <button class="btn btn-primary btn-claim-order" 
                                            data-order-id="<?php echo $order['id']; ?>"
                                            title="Nhận đơn">
                                        <i class="fas fa-hand-paper"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (is_admin()): ?>
                                    <button class="btn btn-warning btn-assign" data-bs-toggle="modal" data-bs-target="#assignOrderModal" data-order-id="<?php echo $order['id']; ?>" title="Phân công">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                                                        <button class="btn btn-danger btn-delete" 
                                            onclick="deleteOrder(<?php echo $order['id']; ?>)"
                                            title="Xóa">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
        <?php if ($total_pages > 1): ?>
    <nav>
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($filter_search); ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; // SỬA LẠI CHO ĐÚNG LOGIC GỐC ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page || $i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($filter_search); ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; // SỬA LẠI CHO ĐÚNG LOGIC GỐC ?>">
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
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($filter_search); ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; // SỬA LẠI CHO ĐÚNG LOGIC GỐC ?>">
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

<?php if (is_admin()): ?>
<div class="modal fade" id="assignOrderModal" tabindex="-1" aria-labelledby="assignOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignOrderModalLabel">Phân công đơn hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="assignOrderForm">
                    <input type="hidden" id="assignOrderId" name="order_id">
                    <div class="mb-3">
                        <label for="assignToUser" class="form-label">Chọn nhân viên để phân công:</label>
                        <select class="form-select" id="assignToUser" name="user_id" required>
                            <option value="">-- Chọn nhân viên --</option>
                            <?php foreach ($telesales_list as $ts): ?>
                            <option value="<?php echo $ts['id']; ?>"><?php echo htmlspecialchars($ts['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="btnSaveAssignment">Lưu phân công</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
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
            data: JSON.stringify({ order_id: orderId }),
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
            error: function() {
                showToast('Có lỗi xảy ra, vui lòng thử lại', 'error');
                button.prop('disabled', false).html('<i class="fas fa-hand-paper"></i>');
            }
        });
    });

    // === PHẦN BỔ SUNG BẮT ĐẦU (4): JS Phân công ===
    var assignModalEl = document.getElementById('assignOrderModal');
    if (assignModalEl) {
        assignModalEl.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var orderId = button.getAttribute('data-order-id');
            var modalOrderIdInput = assignModalEl.querySelector('#assignOrderId');
            modalOrderIdInput.value = orderId;
        });
    }

    $('#btnSaveAssignment').click(function() {
        const orderId = $('#assignOrderId').val();
        const userId = $('#assignToUser').val();
        
        if (!userId) {
            showToast('Vui lòng chọn một nhân viên.', 'error');
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: 'api/assign-order.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ order_id: orderId, user_id: userId }),
            success: function(response) {
                if (response.success) {
                    showToast('Đã phân công đơn hàng thành công!', 'success');
                    const selectedUserName = $('#assignToUser option:selected').text();
                    // Cập nhật giao diện không cần reload
                    $('#order-row-' + orderId + ' .assigned-user-' + orderId).html('<strong>' + selectedUserName + '</strong>');
                    var modal = bootstrap.Modal.getInstance(assignModalEl);
                    modal.hide();
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                }
            },
            error: function() { showToast('Không thể kết nối đến máy chủ.', 'error'); },
            complete: function() { btn.prop('disabled', false).text('Lưu phân công'); }
        });
    });
    // === PHẦN BỔ SUNG KẾT THÚC (4) ===
});

function deleteOrder(orderId) {
    if (!confirm('Bạn có chắc chắn muốn xóa đơn hàng này?')) {
        return;
    }
    
    $.ajax({
        url: 'api/delete-order.php',
        method: 'POST',
        data: JSON.stringify({ order_id: orderId }),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                showToast('Đã xóa đơn hàng', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showToast(response.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: function() {
            showToast('Có lỗi xảy ra, vui lòng thử lại', 'error');
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>