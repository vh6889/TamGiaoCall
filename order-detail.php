<?php
/**
 * Order Detail Page - FIXED VERSION
 * Sửa lỗi phân công và hiển thị nút
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/status_helper.php';
// Removed security_helper to avoid duplicate function declaration

require_login();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    redirect('orders.php');
}

$order = get_order($order_id);

if (!$order) {
    set_flash('error', 'Không tìm thấy đơn hàng');
    redirect('orders.php');
}

// Check permission
$current_user = get_logged_user();
$can_view = is_admin() || is_manager() || 
            ($order['assigned_to'] == $current_user['id']);

if (!$can_view) {
    set_flash('error', 'Bạn không có quyền xem đơn hàng này');
    redirect('orders.php');
}

// Get order notes
$notes = db_get_results(
    "SELECT n.*, u.full_name, u.username 
     FROM order_notes n 
     LEFT JOIN users u ON n.user_id = u.id 
     WHERE n.order_id = ? 
     ORDER BY n.created_at DESC", 
    [$order_id]
);

// Get reminders
$reminders = db_get_results(
    "SELECT * FROM reminders 
     WHERE order_id = ? AND status = 'pending' 
     ORDER BY due_time ASC", 
    [$order_id]
);

// Parse products
$products = json_decode($order['products'], true) ?? [];

// Enhance product data
foreach ($products as &$product) {
    $product['sku'] = $product['sku'] ?? 'N/A';
    $product['regular_price'] = floatval($product['regular_price'] ?? $product['price'] ?? 0);
    $product['sale_price'] = floatval($product['sale_price'] ?? $product['price'] ?? 0);
    $product['attributes'] = $product['attributes'] ?? [];
    $product['qty'] = intval($product['qty'] ?? 1);
    $product['line_total'] = $product['sale_price'] * $product['qty'];
}

// Calculate totals
$subtotal = array_sum(array_column($products, 'line_total'));
$discount = max(0, $subtotal - floatval($order['total_amount']));
$shipping = floatval($order['shipping_cost'] ?? 0);

// Check states
$is_locked = (bool)($order['is_locked'] ?? false);
$is_free = !$order['assigned_to'] || $order['system_status'] === 'free';
$is_my_order = $order['assigned_to'] == $current_user['id'];

// Check active call
$active_call = null;
if ($is_my_order) {
    $active_call = db_get_row(
        "SELECT * FROM call_logs 
         WHERE order_id = ? AND user_id = ? AND end_time IS NULL",
        [$order_id, $current_user['id']]
    );
}

// Can edit during active call
$can_edit = !$is_locked && $active_call && $is_my_order;

// Get telesales list for admin/manager
$telesales_list = [];
if (is_admin() || is_manager()) {
    $telesales_list = get_telesales('active');
}

// Get status options
$status_options = db_get_results(
    "SELECT label_key, label_name, color, icon, label_value 
     FROM order_labels 
     WHERE label_key NOT IN ('pending_approval', 'free')
     ORDER BY sort_order, label_name"
);

// Get current label name
$current_status_label = $order['label_name'] ?? 'Đơn mới';

$page_title = 'Chi tiết đơn hàng #' . $order['order_number'];
include 'includes/header.php';
?>

<style>
.timeline { max-height: 400px; overflow-y: auto; }
.qty-input { width: 60px; }
.table td { vertical-align: middle; }
#callTimer { font-family: monospace; font-size: 1.2em; }
</style>

<!-- Alerts Section -->
<?php if (!empty($reminders)): ?>
<div class="alert alert-warning alert-dismissible fade show mb-3">
    <h6 class="alert-heading"><i class="fas fa-bell"></i> Nhắc nhở quan trọng</h6>
    <ul class="mb-0">
    <?php foreach ($reminders as $reminder): ?>
        <li><?php echo htmlspecialchars($reminder['message'] ?? ''); ?></li>
    <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column -->
    <div class="col-lg-8">
        <!-- Header -->
        <div class="table-card mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">
                        Đơn hàng #<?php echo htmlspecialchars($order['order_number']); ?>
                    </h5>
                    <span class="badge mt-2" style="background-color: <?php echo $order['label_color'] ?? '#6c757d'; ?>">
                        <?php echo htmlspecialchars($current_status_label); ?>
                    </span>
                    <?php if ($is_locked): ?>
                        <span class="badge bg-secondary ms-2">
                            <i class="fas fa-lock"></i> Đã khóa
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Action Buttons -->
                <div>
                    <?php if (!$is_locked): ?>
                        <?php if ($is_free && !is_admin()): ?>
                            <!-- Nút Nhận đơn cho Telesale/Manager -->
                            <button class="btn btn-primary" onclick="claimOrder()">
                                <i class="fas fa-hand-paper"></i> Nhận đơn này
                            </button>
                        <?php elseif ($is_my_order): ?>
                            <?php if (!$active_call): ?>
                                <button class="btn btn-success" onclick="startCall()">
                                    <i class="fas fa-phone"></i> Bắt đầu gọi
                                </button>
                            <?php else: ?>
                                <button class="btn btn-danger" onclick="endCall()">
                                    <i class="fas fa-phone-slash"></i> Kết thúc gọi
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (is_admin()): ?>
                            <button class="btn btn-outline-secondary ms-2" 
                                    data-bs-toggle="dropdown">
                                <i class="fas fa-cog"></i> Quản lý
                            </button>
                            <ul class="dropdown-menu">
                                <?php if ($is_free): ?>
                                    <li><a class="dropdown-item" href="#" 
                                           data-bs-toggle="modal" data-bs-target="#assignModal">
                                        <i class="fas fa-user-plus"></i> Phân công
                                    </a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="#" 
                                           data-bs-toggle="modal" data-bs-target="#transferModal">
                                        <i class="fas fa-exchange-alt"></i> Chuyển giao
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="reclaimOrder()">
                                        <i class="fas fa-undo"></i> Thu hồi
                                    </a></li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Customer Info -->
        <div class="table-card mb-3">
            <h6 class="mb-3"><i class="fas fa-user me-2"></i>Thông tin khách hàng</h6>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Họ tên:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p><strong>Điện thoại:</strong> 
                        <a href="tel:<?php echo $order['customer_phone']; ?>">
                            <?php echo htmlspecialchars($order['customer_phone']); ?>
                        </a>
                        <button class="btn btn-sm btn-link" onclick="copyToClipboard('<?php echo $order['customer_phone']; ?>')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </p>
                </div>
                <div class="col-md-6">
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email'] ?: 'N/A'); ?></p>
                    <p><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($order['customer_address'] ?: 'N/A'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Products -->
        <div class="table-card mb-3">
            <h6 class="mb-3"><i class="fas fa-box me-2"></i>Sản phẩm</h6>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Tên sản phẩm</th>
                            <th>SL</th>
                            <th>Đơn giá</th>
                            <th>Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['sku']); ?></td>
                            <td><?php echo htmlspecialchars($product['name'] ?? 'Unknown Product'); ?></td>
                            <td><?php echo $product['qty']; ?></td>
                            <td><?php echo format_money($product['sale_price']); ?></td>
                            <td><?php echo format_money($product['line_total']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end"><strong>Tạm tính:</strong></td>
                            <td><strong><?php echo format_money($subtotal); ?></strong></td>
                        </tr>
                        <tr class="table-active">
                            <td colspan="4" class="text-end"><h5>Tổng cộng:</h5></td>
                            <td><h5 class="text-danger"><?php echo format_money($order['total_amount']); ?></h5></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <!-- Notes -->
        <div class="table-card">
            <h6 class="mb-3"><i class="fas fa-comments me-2"></i>Lịch sử ghi chú</h6>
            <?php if (!empty($notes)): ?>
                <?php foreach ($notes as $note): ?>
                <div class="mb-3 border-start border-3 ps-3">
                    <div class="d-flex justify-content-between">
                        <strong><?php echo htmlspecialchars($note['full_name'] ?? 'Hệ thống'); ?></strong>
                        <small class="text-muted"><?php echo time_ago($note['created_at']); ?></small>
                    </div>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($note['content'])); ?></p>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">Chưa có ghi chú</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Right Column -->
    <div class="col-lg-4">
        <!-- Order Info -->
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title"><i class="fas fa-info-circle"></i> Thông tin đơn</h6>
                <table class="table table-sm">
                    <tr>
                        <td>Nguồn:</td>
                        <td class="text-end">
                            <span class="badge bg-<?php echo $order['source'] == 'manual' ? 'warning' : 'info'; ?>">
                                <?php echo $order['source'] == 'manual' ? 'Thủ công' : 'WooCommerce'; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>Ngày tạo:</td>
                        <td class="text-end"><?php echo format_date($order['created_at']); ?></td>
                    </tr>
                    <tr>
                        <td>Người xử lý:</td>
                        <td class="text-end">
                            <?php echo htmlspecialchars($order['assigned_to_name'] ?? 'Chưa phân công'); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Số cuộc gọi:</td>
                        <td class="text-end"><?php echo $order['call_count'] ?? 0; ?> lần</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Status Update -->
        <?php 
        $can_update_status = (is_admin() && !$is_locked) || ($can_edit);
        if ($can_update_status): 
        ?>
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title"><i class="fas fa-sync"></i> Cập nhật trạng thái</h6>
                <select class="form-select" id="statusSelect">
                    <option value="">-- Chọn trạng thái --</option>
                    <?php foreach ($status_options as $opt): ?>
                    <option value="<?php echo $opt['label_key']; ?>"
                            <?php echo $opt['label_key'] == $order['primary_label'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($opt['label_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary w-100 mt-2" onclick="updateStatus()">
                    <i class="fas fa-save"></i> Lưu trạng thái
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Add Note -->
        <?php if (is_admin() || $is_my_order): ?>
        <div class="card">
            <div class="card-body">
                <h6 class="card-title"><i class="fas fa-sticky-note"></i> Ghi chú nội bộ</h6>
                <textarea class="form-control mb-2" id="newNote" rows="3" 
                          placeholder="Thêm ghi chú mới..."></textarea>
                <button class="btn btn-sm btn-primary" onclick="addNote()">
                    <i class="fas fa-plus"></i> Thêm ghi chú
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Assign Modal -->
<?php if (is_admin() && $is_free): ?>
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Phân công đơn hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>Chọn nhân viên để phân công:</label>
                    <select class="form-select" id="assignUserId">
                        <option value="">-- Chọn nhân viên --</option>
                        <?php foreach ($telesales_list as $ts): ?>
                        <option value="<?php echo $ts['id']; ?>">
                            <?php echo htmlspecialchars($ts['full_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Ghi chú (tùy chọn):</label>
                    <textarea class="form-control" id="assignNote" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" onclick="confirmAssign()">
                    <i class="fas fa-check"></i> Lưu phân công
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Transfer Modal -->
<?php if (is_admin() && !$is_free): ?>
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chuyển giao đơn hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <select class="form-select" id="transferUserId">
                    <option value="">-- Chọn nhân viên --</option>
                    <?php foreach ($telesales_list as $ts): ?>
                    <?php if ($ts['id'] != $order['assigned_to']): ?>
                    <option value="<?php echo $ts['id']; ?>">
                        <?php echo htmlspecialchars($ts['full_name']); ?>
                    </option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="confirmTransfer()">
                    Chuyển giao
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Constants
const orderId = <?php echo $order_id; ?>;
const csrfToken = '<?php echo generate_csrf_token(); ?>';

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Đã copy!', 'success');
    });
}

// Claim order (for telesale)
function claimOrder() {
    if (!confirm('Nhận đơn hàng này?')) return;
    
    $.ajax({
        url: 'api/claim-order.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            csrf_token: csrfToken,
            order_id: orderId
        }),
        success: function(response) {
            if (response.success) {
                showToast('Đã nhận đơn hàng!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(response.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr);
            showToast('Lỗi kết nối server', 'error');
        }
    });
}

// Start call
function startCall() {
    $.ajax({
        url: 'api/start-call.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            csrf_token: csrfToken,
            order_id: orderId
        }),
        success: function(response) {
            if (response.success) {
                showToast('Đã bắt đầu cuộc gọi', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(response.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr);
            showToast('Lỗi kết nối server', 'error');
        }
    });
}

// End call
function endCall() {
    const note = prompt('Ghi chú cuộc gọi:');
    if (!note) {
        showToast('Vui lòng nhập ghi chú', 'warning');
        return;
    }
    
    $.ajax({
        url: 'api/end-call.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            csrf_token: csrfToken,
            order_id: orderId,
            note: note
        }),
        success: function(response) {
            if (response.success) {
                showToast('Đã kết thúc cuộc gọi', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(response.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr);
            showToast('Lỗi kết nối server', 'error');
        }
    });
}

// Add note
function addNote() {
    const content = $('#newNote').val().trim();
    if (!content) {
        showToast('Vui lòng nhập nội dung', 'warning');
        return;
    }
    
    $.ajax({
        url: 'api/add-note.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            csrf_token: csrfToken,
            order_id: orderId,
            content: content
        }),
        success: function(response) {
            if (response.success) {
                showToast('Đã thêm ghi chú', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(response.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr);
            showToast('Lỗi kết nối server', 'error');
        }
    });
}

// Update status
function updateStatus() {
    const status = $('#statusSelect').val();
    if (!status) {
        showToast('Vui lòng chọn trạng thái', 'warning');
        return;
    }
    
    if (!confirm('Cập nhật trạng thái?\nĐơn hàng sẽ bị khóa sau khi lưu.')) return;
    
    $.ajax({
        url: 'api/update-status.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            csrf_token: csrfToken,
            order_id: orderId,
            status: status
        }),
        success: function(response) {
            if (response.success) {
                showToast('Đã cập nhật trạng thái', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(response.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr);
            showToast('Lỗi kết nối server', 'error');
        }
    });
}

// Assign order (admin)
function confirmAssign() {
    const userId = $('#assignUserId').val();
    const note = $('#assignNote').val();
    
    if (!userId) {
        showToast('Vui lòng chọn nhân viên', 'error');
        return;
    }
    
    $.ajax({
        url: 'api/assign-order.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            csrf_token: csrfToken,
            order_id: orderId,
            user_id: userId,
            note: note
        }),
        success: function(response) {
            if (response.success) {
                $('#assignModal').modal('hide');
                showToast('Đã phân công thành công!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(response.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr);
            showToast('Lỗi kết nối server', 'error');
        }
    });
}

// Transfer order (admin)
function confirmTransfer() {
    const targetId = $('#transferUserId').val();
    if (!targetId) {
        showToast('Vui lòng chọn nhân viên', 'error');
        return;
    }
    
    $.ajax({
        url: 'api/transfer-order.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            csrf_token: csrfToken,
            order_id: orderId,
            target_user_id: targetId
        }),
        success: function(response) {
            if (response.success) {
                $('#transferModal').modal('hide');
                showToast('Đã chuyển giao', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(response.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr);
            showToast('Lỗi kết nối server', 'error');
        }
    });
}

// Reclaim order (admin)
function reclaimOrder() {
    if (!confirm('Thu hồi đơn hàng về kho chung?')) return;
    
    $.ajax({
        url: 'api/reclaim-order.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            csrf_token: csrfToken,
            order_id: orderId
        }),
        success: function(response) {
            if (response.success) {
                showToast('Đã thu hồi đơn', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(response.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr);
            showToast('Lỗi kết nối server', 'error');
        }
    });
}

// Toast function
function showToast(message, type = 'info') {
    // Simple alert for now
    if (type === 'error') {
        alert('❌ ' + message);
    } else if (type === 'success') {
        alert('✅ ' + message);
    } else {
        alert('ℹ️ ' + message);
    }
}
</script>

<?php include 'includes/footer.php'; ?>