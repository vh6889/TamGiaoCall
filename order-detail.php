<?php
/**
 * Order Detail Page - Professional Version 5.0
 * Complete workflow: Claim → Start Call → Edit → End Call → Update Status → Lock
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/status_helper.php';
require_once 'simple-rule-handler.php';

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
$can_view = is_admin() || is_manager() || $order['assigned_to'] == $current_user['id'];

if (!$can_view) {
    set_flash('error', 'Bạn không có quyền xem đơn hàng này');
    redirect('orders.php');
}

// Get order data
$notes = db_get_results(
    "SELECT n.*, u.full_name, u.username
     FROM order_notes n 
     LEFT JOIN users u ON n.user_id = u.id 
     WHERE n.order_id = ? 
     ORDER BY n.created_at DESC", 
    [$order_id]
);

// Get call logs
$call_logs = db_get_results(
    "SELECT * FROM call_logs 
     WHERE order_id = ? 
     ORDER BY start_time DESC",
    [$order_id]
);

// Parse products
$products = json_decode($order['products'], true) ?? [];
foreach ($products as $key => &$product) {
    $product['id'] = $product['id'] ?? ($key + 1);
    $product['sku'] = $product['sku'] ?? 'N/A';
    $product['name'] = $product['name'] ?? 'Unknown';
    $product['regular_price'] = floatval($product['regular_price'] ?? $product['price'] ?? 0);
    $product['sale_price'] = floatval($product['sale_price'] ?? $product['price'] ?? 0);
    $product['qty'] = intval($product['qty'] ?? 1);
    $product['line_total'] = $product['sale_price'] * $product['qty'];
    $product['image'] = $product['image'] ?? '';
    $product['attributes'] = $product['attributes'] ?? [];
}

// Calculate totals
$subtotal = 0;
foreach ($products as $product) {
    $subtotal += $product['line_total'];
}
$discount = max(0, $subtotal - floatval($order['total_amount']));

// Get reminders & suggestions
$reminders = get_order_reminders($order_id);
$suggestions = get_order_suggestions($order);

// Check states
$is_locked = (bool)($order['is_locked'] ?? false);
$active_call = db_get_row(
    "SELECT * FROM call_logs 
     WHERE order_id = ? AND user_id = ? AND end_time IS NULL",
    [$order_id, $current_user['id']]
);

// Can edit only during active call
$can_edit = !$is_locked && $active_call && $order['assigned_to'] == $current_user['id'];

// Get telesales list for transfer
$telesales_list = (is_admin() || is_manager()) ? get_telesales('active') : [];

// Mock related products (will be from WooCommerce API)
$related_products = [
    ['id' => 201, 'name' => 'Sản phẩm bổ sung A', 'price' => 500000, 'sku' => 'ADD-A', 'image' => ''],
    ['id' => 202, 'name' => 'Sản phẩm bổ sung B', 'price' => 300000, 'sku' => 'ADD-B', 'image' => ''],
    ['id' => 203, 'name' => 'Sản phẩm bổ sung C', 'price' => 750000, 'sku' => 'ADD-C', 'image' => ''],
];

// Get status info
$current_status_label = db_get_var(
    "SELECT label FROM order_status_configs WHERE status_key = ?",
    [$order['status']]
) ?: $order['status'];

$status_options = get_status_options_with_labels();

$page_title = 'Chi tiết đơn hàng #' . $order['order_number'];

include 'includes/header.php';
?>

<style>
.product-attribute {
    font-size: 0.85rem;
    color: #666;
    padding: 2px 6px;
    background: #f8f9fa;
    border-radius: 3px;
    margin-right: 5px;
    display: inline-block;
}
.call-log-item {
    border-left: 3px solid #0d6efd;
    padding-left: 15px;
    margin-bottom: 15px;
}
.suggestion-card {
    border-left: 4px solid;
    padding: 10px 15px;
    margin-bottom: 10px;
    border-radius: 4px;
}
.suggestion-info { border-color: #0dcaf0; background: #cff4fc; }
.suggestion-warning { border-color: #ffc107; background: #fff3cd; }
.suggestion-danger { border-color: #dc3545; background: #f8d7da; }
</style>

<!-- Reminders & Suggestions -->
<?php if (!empty($reminders) || !empty($suggestions)): ?>
<div class="row g-3 mb-3">
    <?php if (!empty($reminders)): ?>
    <div class="col-md-6">
        <div class="alert alert-warning">
            <h6><i class="fas fa-bell"></i> Nhắc nhở</h6>
            <?php foreach ($reminders as $reminder): 
                $is_overdue = strtotime($reminder['due_time']) < time();
            ?>
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <strong class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                            <?php echo htmlspecialchars($reminder['message']); ?>
                        </strong><br>
                        <small>
                            <?php echo $is_overdue ? '⚠️ Quá hạn: ' : 'Hạn: '; ?>
                            <?php echo format_date($reminder['due_time']); ?>
                        </small>
                    </div>
                    <button class="btn btn-sm btn-success" onclick="completeReminder(<?php echo $reminder['id']; ?>)">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($suggestions)): ?>
    <div class="col-md-6">
        <h6><i class="fas fa-lightbulb"></i> Gợi ý & Cảnh báo</h6>
        <?php foreach ($suggestions as $suggestion): ?>
            <div class="suggestion-card suggestion-<?php echo $suggestion['type']; ?>">
                <?php echo htmlspecialchars($suggestion['message']); ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Main Content -->
    <div class="col-lg-8">
        <!-- Order Header -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">
                            <i class="fas fa-shopping-cart me-2"></i>
                            Đơn hàng #<?php echo htmlspecialchars($order['order_number']); ?>
                        </h5>
                        <small class="text-muted">
                            Nguồn: <span class="badge bg-secondary"><?php echo strtoupper($order['source'] ?? 'woocommerce'); ?></span>
                            | Tạo: <?php echo format_date($order['created_at']); ?>
                        </small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-<?php echo get_status_color($order['status']); ?> fs-6">
                            <?php echo $current_status_label; ?>
                        </span>
                        <?php if ($is_locked): ?>
                        <br><span class="badge bg-secondary mt-2">
                            <i class="fas fa-lock"></i> Đã khóa
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Info -->
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="border-bottom pb-2 mb-3">
                    <i class="fas fa-user me-2"></i>Thông tin khách hàng
                    <?php if ($can_edit): ?>
                    <button class="btn btn-sm btn-outline-primary float-end" onclick="editCustomer()">
                        <i class="fas fa-edit"></i> Sửa
                    </button>
                    <?php endif; ?>
                </h6>
                
                <div id="customerInfo">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>Họ tên:</strong><br>
                                <span id="displayName" class="fs-5"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                            </p>
                            <p class="mb-2">
                                <strong>Điện thoại:</strong><br>
                                <a href="tel:<?php echo $order['customer_phone']; ?>" class="fs-5 text-decoration-none">
                                    <i class="fas fa-phone text-success"></i>
                                    <span id="displayPhone"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                                </a>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>Email:</strong><br>
                                <span id="displayEmail"><?php echo htmlspecialchars($order['customer_email'] ?? 'Chưa có'); ?></span>
                            </p>
                            <p class="mb-2">
                                <strong>Địa chỉ:</strong><br>
                                <span id="displayAddress"><?php echo htmlspecialchars($order['customer_address'] ?? 'Chưa có'); ?></span>
                            </p>
                        </div>
                    </div>
                    <?php if ($order['customer_notes']): ?>
                    <div class="alert alert-info mt-2 mb-0">
                        <strong>Ghi chú của khách:</strong><br>
                        <?php echo nl2br(htmlspecialchars($order['customer_notes'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Edit Form (Hidden) -->
                <div id="customerEdit" style="display:none;">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Họ tên *</label>
                            <input type="text" class="form-control" id="editName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Điện thoại *</label>
                            <input type="text" class="form-control" id="editPhone" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="editEmail">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Địa chỉ</label>
                            <input type="text" class="form-control" id="editAddress">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-success" onclick="saveCustomer()">
                            <i class="fas fa-save"></i> Lưu
                        </button>
                        <button class="btn btn-secondary" onclick="cancelEditCustomer()">Hủy</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Products -->
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="border-bottom pb-2 mb-3">
                    <i class="fas fa-box me-2"></i>Sản phẩm (<?php echo count($products); ?>)
                    <?php if ($can_edit): ?>
                    <button class="btn btn-sm btn-success float-end" onclick="showAddProduct()">
                        <i class="fas fa-plus"></i> Thêm
                    </button>
                    <?php endif; ?>
                </h6>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="productsTable">
                        <thead class="table-light">
                            <tr>
                                <th width="60">Hình</th>
                                <th>Sản phẩm</th>
                                <th width="100">SKU</th>
                                <th width="110" class="text-end">Giá gốc</th>
                                <th width="110" class="text-end">Giá bán</th>
                                <th width="80" class="text-center">SL</th>
                                <th width="120" class="text-end">Thành tiền</th>
                                <?php if ($can_edit): ?>
                                <th width="60"></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="productsList">
                            <!-- Rendered by JS -->
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="<?php echo $can_edit ? 6 : 5; ?>" class="text-end">
                                    <strong>Tạm tính:</strong>
                                </td>
                                <td class="text-end" colspan="<?php echo $can_edit ? 2 : 2; ?>">
                                    <strong id="subtotal">0₫</strong>
                                </td>
                            </tr>
                            <?php if ($discount > 0): ?>
                            <tr class="text-success">
                                <td colspan="<?php echo $can_edit ? 6 : 5; ?>" class="text-end">
                                    <strong>Giảm giá:</strong>
                                </td>
                                <td class="text-end" colspan="<?php echo $can_edit ? 2 : 2; ?>">
                                    <strong id="discount">-0₫</strong>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr class="table-primary">
                                <td colspan="<?php echo $can_edit ? 6 : 5; ?>" class="text-end">
                                    <h5 class="mb-0">Tổng cộng:</h5>
                                </td>
                                <td class="text-end" colspan="<?php echo $can_edit ? 2 : 2; ?>">
                                    <h5 class="mb-0 text-primary" id="total">0₫</h5>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Call Logs & Notes -->
        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tabNotes">
                            <i class="fas fa-comments"></i> Ghi chú (<?php echo count($notes); ?>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tabCalls">
                            <i class="fas fa-phone"></i> Lịch sử gọi (<?php echo count($call_logs); ?>)
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- Notes Tab -->
                    <div class="tab-pane fade show active" id="tabNotes">
                        <?php if (!empty($notes)): ?>
                        <div style="max-height: 500px; overflow-y: auto;">
                            <?php foreach ($notes as $note): ?>
                            <div class="border-start border-3 border-<?php echo $note['note_type'] === 'system' ? 'secondary' : 'primary'; ?> ps-3 mb-3">
                                <div class="d-flex justify-content-between">
                                    <strong><?php echo htmlspecialchars($note['full_name'] ?? 'Hệ thống'); ?></strong>
                                    <small class="text-muted"><?php echo time_ago($note['created_at']); ?></small>
                                </div>
                                <p class="mb-1">
                                    <?php 
                                    if ($note['note_type'] === 'status' && strpos($note['content'], 'Cập nhật trạng thái:') !== false) {
                                        $status_key = trim(str_replace('Cập nhật trạng thái:', '', $note['content']));
                                        $status_label = db_get_var(
                                            "SELECT label FROM order_status_configs WHERE status_key = ?",
                                            [$status_key]
                                        ) ?: $status_key;
                                        echo "Cập nhật trạng thái: <strong>" . htmlspecialchars($status_label) . "</strong>";
                                    } else {
                                        echo nl2br(htmlspecialchars($note['content']));
                                    }
                                    ?>
                                </p>
                                <span class="badge bg-secondary"><?php echo $note['note_type']; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center py-3">Chưa có ghi chú</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Call Logs Tab -->
                    <div class="tab-pane fade" id="tabCalls">
                        <?php if (!empty($call_logs)): ?>
                        <div style="max-height: 500px; overflow-y: auto;">
                            <?php foreach ($call_logs as $log): 
                                $duration = $log['end_time'] ? (strtotime($log['end_time']) - strtotime($log['start_time'])) : 0;
                                $duration_str = gmdate('H:i:s', $duration);
                            ?>
                            <div class="call-log-item">
                                <div class="d-flex justify-content-between">
                                    <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                                    <span class="badge bg-<?php echo $log['status'] === 'completed' ? 'success' : 'primary'; ?>">
                                        <?php echo $log['status']; ?>
                                    </span>
                                </div>
                                <div class="text-muted small">
                                    <i class="fas fa-clock"></i> <?php echo format_date($log['start_time']); ?>
                                    <?php if ($log['end_time']): ?>
                                    - <?php echo format_date($log['end_time']); ?>
                                    <span class="ms-2"><i class="fas fa-hourglass"></i> <?php echo $duration_str; ?></span>
                                    <?php else: ?>
                                    <span class="text-success"> - Đang gọi...</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($log['note']): ?>
                                <div class="mt-1 p-2 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($log['note'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center py-3">Chưa có cuộc gọi nào</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Call Control Panel -->
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="border-bottom pb-2 mb-3">
                    <i class="fas fa-phone-volume me-2"></i>Xử lý đơn hàng
                </h6>
                
                <?php if ($is_locked): ?>
                    <!-- Locked State -->
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle"></i> Đơn hàng đã xử lý xong
                        <hr>
                        <small>
                            Khóa bởi: <?php echo htmlspecialchars(get_user($order['locked_by'])['full_name'] ?? 'N/A'); ?><br>
                            Thời gian: <?php echo format_date($order['locked_at']); ?>
                        </small>
                    </div>
                    
                <?php elseif (!$order['assigned_to']): ?>
                    <!-- Not Assigned - Can Claim -->
                    <button class="btn btn-primary w-100 mb-2" onclick="claimOrder()">
                        <i class="fas fa-hand-paper"></i> Nhận đơn hàng
                    </button>
                    <small class="text-muted">Nhấn để nhận đơn và bắt đầu xử lý</small>
                    
                <?php elseif ($order['assigned_to'] == $current_user['id']): ?>
                    <!-- Assigned to current user -->
                    
                    <?php if (!$active_call): ?>
                        <!-- Not in call - Show Start Call -->
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Bạn đã nhận đơn này
                        </div>
                        <button class="btn btn-success w-100 btn-lg" onclick="startCall()">
                            <i class="fas fa-phone"></i> Bắt đầu cuộc gọi
                        </button>
                        <small class="text-muted d-block mt-2">
                            Bấm để bắt đầu - Sau đó bạn có thể sửa thông tin và thêm ghi chú
                        </small>
                        
                    <?php else: ?>
                        <!-- In active call - Show controls -->
                        <div class="alert alert-success mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-phone-volume"></i> Đang gọi</span>
                                <span class="badge bg-success" style="font-size: 1.1rem;">
                                    <span id="callTimer">00:00:00</span>
                                </span>
                            </div>
                        </div>
                        
                        <label class="form-label">Ghi chú cuộc gọi *</label>
                        <textarea class="form-control mb-3" id="callNotes" rows="4" 
                                  placeholder="Nhập nội dung trao đổi với khách hàng..."></textarea>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="needCallback">
                            <label class="form-check-label">
                                Cần gọi lại
                            </label>
                        </div>
                        
                        <div id="callbackDiv" style="display:none;" class="mb-3">
                            <label class="form-label">Thời gian gọi lại</label>
                            <input type="datetime-local" class="form-control" id="callbackTime">
                        </div>
                        
                        <button class="btn btn-danger w-100 btn-lg" onclick="endCall()">
                            <i class="fas fa-phone-slash"></i> Kết thúc cuộc gọi
                        </button>
                    <?php endif; ?>
                    
                    <!-- Update Status Panel (shown after end call) -->
                    <div id="updateStatusPanel" style="display:none;" class="mt-3">
                        <hr>
                        <h6 class="mb-3"><i class="fas fa-clipboard-check"></i> Cập nhật trạng thái</h6>
                        <label class="form-label">Chọn trạng thái đơn hàng *</label>
                        <select class="form-select mb-3" id="orderStatus">
                            <option value="">-- Chọn trạng thái --</option>
                            <?php foreach ($status_options as $status): ?>
                            <option value="<?php echo $status['status_key']; ?>">
                                <?php echo htmlspecialchars($status['label']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary w-100 btn-lg" onclick="updateStatus()">
                            <i class="fas fa-save"></i> Lưu & Hoàn tất
                        </button>
                        <small class="text-muted d-block mt-2">
                            Sau khi lưu, đơn hàng sẽ bị khóa và không thể chỉnh sửa
                        </small>
                    </div>
                    
                <?php elseif (is_admin() || is_manager()): ?>
                    <!-- Assigned to someone else, but admin/manager can view -->
                    <div class="alert alert-warning">
                        <strong>Đang xử lý bởi:</strong><br>
                        <?php 
                        $assigned_user = get_user($order['assigned_to']);
                        echo htmlspecialchars($assigned_user['full_name'] ?? 'N/A');
                        ?>
                    </div>
                    
                <?php else: ?>
                    <div class="alert alert-secondary">
                        Đơn hàng đã được gán cho người khác
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Admin/Manager Actions -->
        <?php if ((is_admin() || is_manager()) && !$is_locked): ?>
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="border-bottom pb-2 mb-3">
                    <i class="fas fa-tools me-2"></i>Quản lý
                </h6>
                
                <?php if (is_admin()): ?>
                <button class="btn btn-outline-primary w-100 mb-2" onclick="showTransferModal()">
                    <i class="fas fa-exchange-alt"></i> Chuyển giao
                </button>
                
                <button class="btn btn-outline-secondary w-100 mb-2" onclick="reclaimOrder()">
                    <i class="fas fa-undo"></i> Thu hồi về kho
                </button>
                <?php endif; ?>
                
                <?php if ($order['assigned_to'] && !$active_call): ?>
                <button class="btn btn-outline-warning w-100" onclick="forceUnassign()">
                    <i class="fas fa-user-times"></i> Hủy phân công
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Order Info -->
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="border-bottom pb-2 mb-3">Thông tin</h6>
                
                <div class="mb-2">
                    <small class="text-muted">Người xử lý:</small><br>
                    <?php 
                    if ($order['assigned_to']) {
                        $user = get_user($order['assigned_to']);
                        echo '<strong>' . htmlspecialchars($user['full_name'] ?? 'N/A') . '</strong>';
                        echo '<br><small class="text-muted">Từ: ' . format_date($order['assigned_at']) . '</small>';
                    } else {
                        echo '<span class="text-muted">Chưa gán</span>';
                    }
                    ?>
                </div>
                
                <hr>
                
                <div class="mb-2">
                    <small class="text-muted">Số cuộc gọi:</small>
                    <strong class="float-end"><?php echo $order['call_count'] ?? 0; ?> lần</strong>
                </div>
                
                <?php if ($order['last_call_at']): ?>
                <div class="mb-2">
                    <small class="text-muted">Gọi lần cuối:</small><br>
                    <small><?php echo format_date($order['last_call_at']); ?></small>
                </div>
                <?php endif; ?>
                
                <hr>
                
                <div class="mb-2">
                    <small class="text-muted">Ngày tạo:</small><br>
                    <small><?php echo format_date($order['created_at']); ?></small>
                </div>
                
                <div class="mb-2">
                    <small class="text-muted">Cập nhật:</small><br>
                    <small><?php echo format_date($order['updated_at']); ?></small>
                </div>
            </div>
        </div>
        
        <!-- Future Actions -->
        <div class="card border-warning">
            <div class="card-body">
                <h6 class="border-bottom pb-2 mb-3">
                    <i class="fas fa-truck me-2"></i>Hành động khác
                </h6>
                
                <button class="btn btn-outline-warning w-100 mb-2" disabled>
                    <i class="fas fa-shipping-fast"></i> Gửi sang giao hàng
                </button>
                <small class="text-muted">Tính năng đang phát triển</small>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Product -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle"></i> Thêm sản phẩm
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control mb-3" placeholder="Tìm kiếm sản phẩm..." id="searchProduct">
                <div class="list-group" id="productSearchResults">
                    <?php foreach ($related_products as $rp): ?>
                    <a href="#" class="list-group-item list-group-item-action" 
                       onclick="addProduct(<?php echo htmlspecialchars(json_encode($rp)); ?>); return false;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($rp['name']); ?></strong><br>
                                <small class="text-muted">SKU: <?php echo $rp['sku']; ?></small>
                            </div>
                            <div class="text-end">
                                <strong class="text-primary fs-5"><?php echo format_money($rp['price']); ?></strong><br>
                                <button class="btn btn-sm btn-success mt-1">
                                    <i class="fas fa-plus"></i> Thêm
                                </button>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Transfer Order -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chuyển giao đơn hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Chọn nhân viên nhận chuyển giao</label>
                <select class="form-select" id="transferUserId">
                    <option value="">-- Chọn nhân viên --</option>
                    <?php foreach ($telesales_list as $ts): ?>
                    <option value="<?php echo $ts['id']; ?>">
                        <?php echo htmlspecialchars($ts['full_name']); ?> (<?php echo $ts['username']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="confirmTransfer()">
                    <i class="fas fa-check"></i> Xác nhận
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Global state
const orderId = <?php echo $order_id; ?>;
const canEdit = <?php echo $can_edit ? 'true' : 'false'; ?>;
const isLocked = <?php echo $is_locked ? 'true' : 'false'; ?>;
const csrfToken = '<?php echo generate_csrf_token(); ?>';

let products = <?php echo json_encode($products); ?>;
let hasUnsavedChanges = false;
let callTimer = null;
let callStartTime = null;

// Initialize
$(document).ready(function() {
    renderProducts();
    
    // Track unsaved changes
    if (canEdit) {
        $('input, textarea, select').on('change input', function() {
            if (!$(this).hasClass('no-track')) {
                hasUnsavedChanges = true;
            }
        });
        
        // Warn before leaving
        window.addEventListener('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'Bạn có thay đổi chưa lưu. Bạn có chắc muốn rời khỏi trang?';
            }
        });
    }
    
    // Callback toggle
    $('#needCallback').change(function() {
        $('#callbackDiv').toggle($(this).is(':checked'));
        if ($(this).is(':checked')) {
            // Set default callback time to 2 hours later
            const now = new Date();
            now.setHours(now.getHours() + 2);
            $('#callbackTime').val(now.toISOString().slice(0, 16));
        }
    });
});

// Start timer if active call
<?php if ($active_call): ?>
callStartTime = new Date('<?php echo $active_call['start_time']; ?>');
startTimer();
<?php endif; ?>

function startTimer() {
    callTimer = setInterval(() => {
        if (!callStartTime) return;
        const now = new Date();
        const diff = Math.floor((now - callStartTime) / 1000);
        const h = String(Math.floor(diff / 3600)).padStart(2, '0');
        const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
        const s = String(diff % 60).padStart(2, '0');
        $('#callTimer').text(`${h}:${m}:${s}`);
    }, 1000);
}

// ========================================
// PRODUCT MANAGEMENT
// ========================================
function renderProducts() {
    const tbody = $('#productsList');
    tbody.empty();
    
    let subtotal = 0;
    
    products.forEach((product, index) => {
        const lineTotal = product.sale_price * product.qty;
        subtotal += lineTotal;
        
        // Render attributes
        let attributesHtml = '';
        if (product.attributes && product.attributes.length > 0) {
            product.attributes.forEach(attr => {
                attributesHtml += `<span class="product-attribute">${attr.name}: ${attr.value}</span>`;
            });
        }
        
        const row = `
            <tr>
                <td>
                    <img src="${product.image || 'assets/img/no-image.png'}" 
                         class="img-thumbnail" 
                         style="width:50px;height:50px;object-fit:cover;">
                </td>
                <td>
                    <strong>${escapeHtml(product.name)}</strong>
                    ${attributesHtml ? '<br>' + attributesHtml : ''}
                </td>
                <td><small class="text-muted">${escapeHtml(product.sku)}</small></td>
                <td class="text-end">
                    ${product.regular_price > product.sale_price ? 
                        '<small class="text-decoration-line-through text-muted">' + formatMoney(product.regular_price) + '</small>' 
                        : formatMoney(product.regular_price)}
                </td>
                <td class="text-end">
                    <strong class="text-danger">${formatMoney(product.sale_price)}</strong>
                </td>
                <td class="text-center">
                    ${canEdit ? 
                        `<input type="number" class="form-control form-control-sm text-center" 
                                value="${product.qty}" min="1" max="999" style="width:70px"
                                onchange="updateQty(${index}, this.value)">` 
                        : `<span class="badge bg-secondary">${product.qty}</span>`}
                </td>
                <td class="text-end">
                    <strong>${formatMoney(lineTotal)}</strong>
                </td>
                ${canEdit ? `
                    <td class="text-center">
                        <button class="btn btn-sm btn-danger" onclick="removeProduct(${index})" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                ` : ''}
            </tr>
        `;
        tbody.append(row);
    });
    
    // Update totals
    $('#subtotal').text(formatMoney(subtotal));
    const total = <?php echo $order['total_amount']; ?>;
    const discount = Math.max(0, subtotal - total);
    if (discount > 0) {
        $('#discount').text('-' + formatMoney(discount));
    }
    $('#total').text(formatMoney(total));
}

function updateQty(index, qty) {
    qty = parseInt(qty) || 1;
    if (qty < 1) qty = 1;
    if (qty > 999) qty = 999;
    
    products[index].qty = qty;
    products[index].line_total = products[index].sale_price * qty;
    
    renderProducts();
    saveProducts();
}

function removeProduct(index) {
    if (!confirm('Xóa sản phẩm này khỏi đơn hàng?')) return;
    
    products.splice(index, 1);
    renderProducts();
    saveProducts();
    showToast('Đã xóa sản phẩm', 'success');
}

function showAddProduct() {
    $('#addProductModal').modal('show');
}

function addProduct(product) {
    // Check if product already exists
    const existing = products.find(p => p.id === product.id);
    if (existing) {
        existing.qty += 1;
        existing.line_total = existing.sale_price * existing.qty;
    } else {
        products.push({
            id: product.id,
            sku: product.sku,
            name: product.name,
            image: product.image || '',
            regular_price: product.price,
            sale_price: product.price,
            qty: 1,
            line_total: product.price,
            attributes: product.attributes || []
        });
    }
    
    $('#addProductModal').modal('hide');
    renderProducts();
    saveProducts();
    showToast('Đã thêm sản phẩm', 'success');
}

function saveProducts() {
    hasUnsavedChanges = false;
    
    $.ajax({
        url: 'api/update-products.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            csrf_token: csrfToken,
            order_id: orderId,
            products: JSON.stringify(products)
        }),
        success: function(response) {
            if (!response.success) {
                showToast('Lỗi lưu sản phẩm: ' + response.message, 'error');
            }
        },
        error: function() {
            showToast('Không thể lưu sản phẩm', 'error');
        }
    });
}

// ========================================
// CUSTOMER MANAGEMENT
// ========================================
function editCustomer() {
    $('#editName').val($('#displayName').text());
    $('#editPhone').val($('#displayPhone').text());
    $('#editEmail').val($('#displayEmail').text());
    $('#editAddress').val($('#displayAddress').text());
    
    $('#customerInfo').hide();
    $('#customerEdit').show();
}

function cancelEditCustomer() {
    $('#customerEdit').hide();
    $('#customerInfo').show();
}

function saveCustomer() {
    const name = $('#editName').val().trim();
    const phone = $('#editPhone').val().trim();
    
    if (!name || !phone) {
        showToast('Vui lòng nhập đầy đủ họ tên và số điện thoại', 'error');
        return;
    }
    
    hasUnsavedChanges = false;
    
    $.ajax({
        url: 'api/update-customer-info.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            csrf_token: csrfToken,
            order_id: orderId,
            customer_name: name,
            customer_phone: phone,
            customer_email: $('#editEmail').val().trim(),
            customer_address: $('#editAddress').val().trim()
        }),
        success: function(response) {
            if (response.success) {
                $('#displayName').text(name);
                $('#displayPhone').text(phone);
                $('#displayEmail').text($('#editEmail').val().trim() || 'Chưa có');
                $('#displayAddress').text($('#editAddress').val().trim() || 'Chưa có');
                cancelEditCustomer();
                showToast('Đã lưu thông tin khách hàng', 'success');
            } else {
                showToast('Lỗi: ' + response.message, 'error');
            }
        },
        error: function() {
            showToast('Không thể lưu thông tin', 'error');
        }
    });
}

// ========================================
// CALL MANAGEMENT
// ========================================
function claimOrder() {
    if (!confirm('Nhận đơn hàng này để xử lý?')) return;
    
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
                showToast('Đã nhận đơn hàng', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Lỗi: ' + response.message, 'error');
            }
        }
    });
}

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
                showToast('Lỗi: ' + response.message, 'error');
            }
        }
    });
}

function endCall() {
    const notes = $('#callNotes').val().trim();
    if (!notes) {
        showToast('Vui lòng nhập ghi chú cuộc gọi', 'error');
        $('#callNotes').focus();
        return;
    }
    
    if (!confirm('Kết thúc cuộc gọi? Bạn sẽ cần cập nhật trạng thái đơn hàng sau đó.')) return;
    
    hasUnsavedChanges = false;
    
    const callbackTime = $('#needCallback').is(':checked') ? $('#callbackTime').val() : null;
    
    $.ajax({
        url: 'api/end-call.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            csrf_token: csrfToken,
            order_id: orderId,
            notes: notes,
            callback_time: callbackTime
        }),
        success: function(response) {
            if (response.success) {
                showToast('Đã kết thúc cuộc gọi', 'success');
                if (callTimer) clearInterval(callTimer);
                
                // Hide call panel, show status update
                $('.alert-success').fadeOut();
                $('#callNotes').parent().fadeOut();
                $('.form-check').fadeOut();
                $('#callbackDiv').fadeOut();
                $('.btn-danger').fadeOut();
                
                setTimeout(() => {
                    $('#updateStatusPanel').slideDown();
                }, 500);
            } else {
                showToast('Lỗi: ' + response.message, 'error');
            }
        }
    });
}

function updateStatus() {
    const status = $('#orderStatus').val();
    
    if (!status) {
        showToast('Vui lòng chọn trạng thái đơn hàng', 'error');
        return;
    }
    
    if (!confirm('Xác nhận cập nhật trạng thái? Sau khi lưu, đơn hàng sẽ bị khóa và không thể chỉnh sửa.')) return;
    
    hasUnsavedChanges = false;
    
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
                showToast('Đã cập nhật trạng thái thành công!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Lỗi: ' + response.message, 'error');
            }
        }
    });
}

// ========================================
// ADMIN ACTIONS
// ========================================
function showTransferModal() {
    $('#transferModal').modal('show');
}

function confirmTransfer() {
    const targetUserId = $('#transferUserId').val();
    
    if (!targetUserId) {
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
            target_user_id: targetUserId
        }),
        success: function(response) {
            if (response.success) {
                $('#transferModal').modal('hide');
                showToast('Đã chuyển giao đơn hàng', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Lỗi: ' + response.message, 'error');
            }
        }
    });
}

function reclaimOrder() {
    if (!confirm('Thu hồi đơn về kho chung?')) return;
    
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
                showToast('Đã thu hồi đơn hàng', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Lỗi: ' + response.message, 'error');
            }
        }
    });
}

function forceUnassign() {
    if (!confirm('Hủy phân công đơn hàng này? Đơn sẽ trở về trạng thái mới.')) return;
    
    $.ajax({
        url: 'api/unassign-order.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            csrf_token: csrfToken,
            order_id: orderId
        }),
        success: function(response) {
            if (response.success) {
                showToast('Đã hủy phân công', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Lỗi: ' + response.message, 'error');
            }
        }
    });
}

// ========================================
// REMINDERS
// ========================================
function completeReminder(reminderId) {
    $.ajax({
        url: 'api/complete-reminder.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            csrf_token: csrfToken,
            reminder_id: reminderId
        }),
        success: function(response) {
            if (response.success) {
                showToast('Đã đánh dấu hoàn thành', 'success');
                setTimeout(() => location.reload(), 1000);
            }
        }
    });
}

// ========================================
// UTILITY FUNCTIONS
// ========================================
function formatMoney(amount) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(amount);
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

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