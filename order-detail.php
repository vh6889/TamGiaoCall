<?php
/**
 * Order Detail Page - Version 4.0 Fixed
 * Sửa lỗi quản lý sản phẩm và hiển thị trạng thái
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/status_helper.php';

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
if (!is_admin() && !is_manager() && $order['assigned_to'] != $current_user['id']) {
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

// Parse products
$products = json_decode($order['products'], true) ?? [];

// Đảm bảo structure đúng cho mỗi product
foreach ($products as $key => &$product) {
    $product['id'] = $product['id'] ?? ($key + 1);
    $product['sku'] = $product['sku'] ?? 'N/A';
    $product['name'] = $product['name'] ?? 'Unknown';
    $product['regular_price'] = floatval($product['regular_price'] ?? $product['price'] ?? 0);
    $product['sale_price'] = floatval($product['sale_price'] ?? $product['price'] ?? 0);
    $product['qty'] = intval($product['qty'] ?? 1);
    $product['line_total'] = $product['sale_price'] * $product['qty'];
}

// Calculate totals
$subtotal = 0;
foreach ($products as $product) {
    $subtotal += $product['line_total'];
}
$discount = max(0, $subtotal - floatval($order['total_amount']));

// Get reminders
$reminders = db_get_results(
    "SELECT * FROM reminders 
     WHERE order_id = ? AND status = 'pending' 
     ORDER BY due_time ASC", 
    [$order_id]
);

// Check order lock status
$is_locked = $order['is_locked'] ?? false;

// Check active call
$active_call = db_get_row(
    "SELECT * FROM call_logs 
     WHERE order_id = ? AND user_id = ? AND end_time IS NULL",
    [$order_id, $current_user['id']]
);

// Get telesales list
$telesales_list = (is_admin() || is_manager()) ? get_telesales('active') : [];

// Mock related products (will be from WooCommerce)
$related_products = [
    ['id' => 201, 'name' => 'Sản phẩm bổ sung A', 'price' => 500000, 'sku' => 'ADD-A'],
    ['id' => 202, 'name' => 'Sản phẩm bổ sung B', 'price' => 300000, 'sku' => 'ADD-B'],
];

// Get status label for display
$current_status_label = db_get_var(
    "SELECT label FROM order_status_configs WHERE status_key = ?",
    [$order['status']]
) ?: $order['status'];

$page_title = 'Chi tiết đơn hàng #' . $order['order_number'];

include 'includes/header.php';
?>

<!-- Reminders Alert -->
<?php if (!empty($reminders)): ?>
<div class="alert alert-warning alert-dismissible mb-3">
    <h6><i class="fas fa-bell"></i> Nhắc nhở</h6>
    <ul class="mb-0">
    <?php foreach ($reminders as $reminder): ?>
        <li><?php echo htmlspecialchars($reminder['message']); ?> 
            - Hạn: <?php echo format_date($reminder['due_time']); ?>
        </li>
    <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Main Content -->
    <div class="col-lg-8">
        <!-- Order Header -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Đơn hàng #<?php echo htmlspecialchars($order['order_number']); ?>
                    </h5>
                    <div>
                        <span class="badge bg-<?php echo get_status_color($order['status']); ?>">
                            <?php echo $current_status_label; ?>
                        </span>
                        <?php if ($is_locked): ?>
                        <span class="badge bg-secondary ms-2">
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
                <h6 class="border-bottom pb-2">
                    <i class="fas fa-user me-2"></i>Thông tin khách hàng
                    <?php if ($active_call && !$is_locked): ?>
                    <button class="btn btn-sm btn-outline-primary float-end" onclick="editCustomer()">
                        <i class="fas fa-edit"></i> Sửa
                    </button>
                    <?php endif; ?>
                </h6>
                
                <div id="customerInfo">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Họ tên:</strong> <span id="displayName"><?php echo htmlspecialchars($order['customer_name']); ?></span></p>
                            <p><strong>Điện thoại:</strong> 
                                <a href="tel:<?php echo $order['customer_phone']; ?>">
                                    <span id="displayPhone"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                                </a>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Email:</strong> <span id="displayEmail"><?php echo htmlspecialchars($order['customer_email'] ?? 'N/A'); ?></span></p>
                            <p><strong>Địa chỉ:</strong> <span id="displayAddress"><?php echo htmlspecialchars($order['customer_address'] ?? 'N/A'); ?></span></p>
                        </div>
                    </div>
                </div>
                
                <div id="customerEdit" style="display:none;">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <input type="text" class="form-control" id="editName" placeholder="Họ tên">
                        </div>
                        <div class="col-md-6 mb-2">
                            <input type="text" class="form-control" id="editPhone" placeholder="Số điện thoại">
                        </div>
                        <div class="col-md-6 mb-2">
                            <input type="email" class="form-control" id="editEmail" placeholder="Email">
                        </div>
                        <div class="col-md-6 mb-2">
                            <input type="text" class="form-control" id="editAddress" placeholder="Địa chỉ">
                        </div>
                    </div>
                    <button class="btn btn-success btn-sm" onclick="saveCustomer()">Lưu</button>
                    <button class="btn btn-secondary btn-sm" onclick="cancelEditCustomer()">Hủy</button>
                </div>
            </div>
        </div>
        
        <!-- Products -->
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="border-bottom pb-2">
                    <i class="fas fa-box me-2"></i>Sản phẩm
                    <?php if ($active_call && !$is_locked): ?>
                    <button class="btn btn-sm btn-success float-end" onclick="showAddProduct()">
                        <i class="fas fa-plus"></i> Thêm
                    </button>
                    <?php endif; ?>
                </h6>
                
                <div class="table-responsive">
                    <table class="table" id="productsTable">
                        <thead>
                            <tr>
                                <th width="50">Hình</th>
                                <th>Sản phẩm</th>
                                <th width="100">Mã SKU</th>
                                <th width="110">Giá gốc</th>
                                <th width="110">Giá sale</th>
                                <th width="80">SL</th>
                                <th width="120">Thành tiền</th>
                                <?php if ($active_call && !$is_locked): ?>
                                <th width="50"></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="productsList">
                            <!-- Products will be rendered by JS -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-end"><strong>Tạm tính:</strong></td>
                                <td colspan="2"><strong id="subtotal">0đ</strong></td>
                            </tr>
                            <?php if ($discount > 0): ?>
                            <tr class="text-success">
                                <td colspan="6" class="text-end"><strong>Giảm giá:</strong></td>
                                <td colspan="2"><strong id="discount">-0đ</strong></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="table-active">
                                <td colspan="6" class="text-end"><h5>Tổng cộng:</h5></td>
                                <td colspan="2"><h5 class="text-primary" id="total">0đ</h5></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Notes -->
        <div class="card">
            <div class="card-body">
                <h6 class="border-bottom pb-2">
                    <i class="fas fa-comments me-2"></i>Lịch sử ghi chú
                </h6>
                <?php if (!empty($notes)): ?>
                <?php foreach ($notes as $note): ?>
                <div class="border-start border-3 ps-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <strong><?php echo htmlspecialchars($note['full_name'] ?? 'Hệ thống'); ?></strong>
                        <small class="text-muted"><?php echo time_ago($note['created_at']); ?></small>
                    </div>
                    <p class="mb-0">
                        <?php 
                        // Hiển thị content với tên trạng thái đúng
                        if ($note['note_type'] === 'status' && strpos($note['content'], 'Cập nhật trạng thái:') !== false) {
                            $status_key = trim(str_replace('Cập nhật trạng thái:', '', $note['content']));
                            $status_label = db_get_var(
                                "SELECT label FROM order_status_configs WHERE status_key = ?",
                                [$status_key]
                            ) ?: $status_key;
                            echo "Cập nhật trạng thái: " . htmlspecialchars($status_label);
                        } else {
                            echo nl2br(htmlspecialchars($note['content']));
                        }
                        ?>
                    </p>
                    <?php if ($note['note_type']): ?>
                    <span class="badge bg-secondary"><?php echo $note['note_type']; ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p class="text-muted">Chưa có ghi chú</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Call Control -->
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="border-bottom pb-2">
                    <i class="fas fa-phone me-2"></i>Xử lý cuộc gọi
                </h6>
                
                <?php if ($is_locked): ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-lock"></i> Đơn hàng đã xử lý xong
                    </div>
                <?php elseif (!$order['assigned_to']): ?>
                    <button class="btn btn-primary w-100" onclick="claimOrder()">
                        <i class="fas fa-hand-paper"></i> Nhận đơn
                    </button>
                <?php elseif ($order['assigned_to'] == $current_user['id'] || is_admin()): ?>
                    
                    <?php if (!$active_call): ?>
                    <div id="startCallPanel">
                        <button class="btn btn-success w-100" onclick="startCall()">
                            <i class="fas fa-phone"></i> Bắt đầu gọi
                        </button>
                    </div>
                    <?php else: ?>
                    <div id="activeCallPanel">
                        <div class="alert alert-success">
                            <i class="fas fa-phone-volume"></i> Đang gọi
                            <div><strong id="callTimer">00:00:00</strong></div>
                        </div>
                        
                        <textarea class="form-control mb-2" id="callNotes" rows="3" 
                                  placeholder="Ghi chú cuộc gọi..."></textarea>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="needCallback">
                            <label class="form-check-label">Cần gọi lại</label>
                        </div>
                        
                        <div id="callbackDiv" style="display:none;" class="mb-2">
                            <input type="datetime-local" class="form-control" id="callbackTime">
                        </div>
                        
                        <button class="btn btn-danger w-100" onclick="endCall()">
                            <i class="fas fa-phone-slash"></i> Kết thúc
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <div id="updateStatusPanel" style="display:none;" class="mt-3">
                        <label>Cập nhật trạng thái:</label>
                        <select class="form-select mb-2" id="orderStatus">
                            <?php echo render_status_options($order['status']); ?>
                        </select>
                        <button class="btn btn-primary w-100" onclick="updateStatus()">
                            <i class="fas fa-save"></i> Lưu trạng thái
                        </button>
                    </div>
                    
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Order Info -->
        <div class="card">
            <div class="card-body">
                <h6 class="border-bottom pb-2">Thông tin</h6>
                <small>Người xử lý:</small><br>
                <?php 
                if ($order['assigned_to']) {
                    $user = get_user($order['assigned_to']);
                    echo '<strong>' . htmlspecialchars($user['full_name'] ?? 'N/A') . '</strong>';
                } else {
                    echo '<span class="text-muted">Chưa gán</span>';
                }
                ?><br>
                <small>Số cuộc gọi:</small> <strong><?php echo $order['call_count']; ?></strong><br>
                <small>Ngày tạo:</small> <strong><?php echo format_date($order['created_at']); ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm sản phẩm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <?php foreach ($related_products as $rp): ?>
                    <a href="#" class="list-group-item list-group-item-action" 
                       onclick="addProduct(<?php echo htmlspecialchars(json_encode($rp)); ?>); return false;">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong><?php echo htmlspecialchars($rp['name']); ?></strong><br>
                                <small>SKU: <?php echo $rp['sku']; ?></small>
                            </div>
                            <div class="text-end">
                                <strong class="text-primary"><?php echo format_money($rp['price']); ?></strong>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Global state
const orderId = <?php echo $order_id; ?>;
let products = <?php echo json_encode($products); ?>;
let hasUnsavedChanges = false;
let callTimer = null;
let callStartTime = null;
const canEdit = <?php echo ($active_call && !$is_locked) ? 'true' : 'false'; ?>;

// Initialize
$(document).ready(function() {
    renderProducts();
    
    // Track unsaved changes
    $('input, textarea, select').on('change input', function() {
        if (!$(this).hasClass('no-track')) {
            hasUnsavedChanges = true;
        }
    });
    
    // Warn before leaving with unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = 'Bạn có thay đổi chưa lưu. Bạn có chắc muốn rời khỏi trang?';
        }
    });
    
    // Callback toggle
    $('#needCallback').change(function() {
        $('#callbackDiv').toggle($(this).is(':checked'));
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

// Product Management
function renderProducts() {
    const tbody = $('#productsList');
    tbody.empty();
    
    let subtotal = 0;
    
    products.forEach((product, index) => {
        const lineTotal = product.sale_price * product.qty;
        subtotal += lineTotal;
        
        const row = `
            <tr>
                <td><img src="${product.image || 'assets/img/no-image.png'}" width="40" class="img-thumbnail"></td>
                <td>${product.name}</td>
                <td>${product.sku}</td>
                <td>${formatMoney(product.regular_price)}</td>
                <td class="text-danger"><strong>${formatMoney(product.sale_price)}</strong></td>
                <td>
                    ${canEdit ? 
                        `<input type="number" class="form-control form-control-sm" 
                                value="${product.qty}" min="1" style="width:60px"
                                onchange="updateQty(${index}, this.value)">` 
                        : product.qty}
                </td>
                <td><strong>${formatMoney(lineTotal)}</strong></td>
                ${canEdit ? `<td><button class="btn btn-sm btn-danger" onclick="removeProduct(${index})"><i class="fas fa-trash"></i></button></td>` : ''}
            </tr>
        `;
        tbody.append(row);
    });
    
    // Update totals
    $('#subtotal').text(formatMoney(subtotal));
    const total = <?php echo $order['total_amount']; ?>;
    const discount = Math.max(0, subtotal - total);
    if (discount > 0) {
        $('#discount').text('-' + formatMoney(discount)).parent().parent().show();
    } else {
        $('#discount').parent().parent().hide();
    }
    $('#total').text(formatMoney(total));
}

function updateQty(index, qty) {
    qty = parseInt(qty) || 1;
    if (qty < 1) qty = 1;
    
    products[index].qty = qty;
    products[index].line_total = products[index].sale_price * qty;
    
    renderProducts();
    saveProducts();
}

function removeProduct(index) {
    if (!confirm('Xóa sản phẩm này?')) return;
    
    products.splice(index, 1);
    renderProducts();
    saveProducts();
}

function showAddProduct() {
    $('#addProductModal').modal('show');
}

function addProduct(product) {
    // Check if product already exists
    const existing = products.find(p => p.id === product.id);
    if (existing) {
        existing.qty += 1;
    } else {
        products.push({
            ...product,
            regular_price: product.price,
            sale_price: product.price,
            qty: 1,
            line_total: product.price
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
        data: {
            order_id: orderId,
            products: JSON.stringify(products)
        },
        success: function(response) {
            if (!response.success) {
                showToast('Lỗi lưu sản phẩm', 'error');
            }
        }
    });
}

// Customer Management
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
    hasUnsavedChanges = false;
    
    $.ajax({
        url: 'api/update-customer.php',
        method: 'POST',
        data: {
            order_id: orderId,
            customer_name: $('#editName').val(),
            customer_phone: $('#editPhone').val(),
            customer_email: $('#editEmail').val(),
            customer_address: $('#editAddress').val()
        },
        success: function(response) {
            if (response.success) {
                $('#displayName').text($('#editName').val());
                $('#displayPhone').text($('#editPhone').val());
                $('#displayEmail').text($('#editEmail').val() || 'N/A');
                $('#displayAddress').text($('#editAddress').val() || 'N/A');
                cancelEditCustomer();
                showToast('Đã lưu thông tin', 'success');
            }
        }
    });
}

// Call Management
function claimOrder() {
    if (!confirm('Nhận đơn hàng này?')) return;
    
    $.post('api/claim-order.php', { order_id: orderId }, function(response) {
        if (response.success) {
            location.reload();
        }
    });
}

function startCall() {
    $.post('api/start-call.php', { order_id: orderId }, function(response) {
        if (response.success) {
            location.reload();
        }
    });
}

function endCall() {
    const notes = $('#callNotes').val().trim();
    if (!notes) {
        showToast('Vui lòng nhập ghi chú', 'error');
        return;
    }
    
    hasUnsavedChanges = false;
    
    $.post('api/end-call.php', {
        order_id: orderId,
        notes: notes,
        callback_time: $('#needCallback').is(':checked') ? $('#callbackTime').val() : null
    }, function(response) {
        if (response.success) {
            $('#activeCallPanel').hide();
            $('#updateStatusPanel').show();
            if (callTimer) clearInterval(callTimer);
        }
    });
}

function updateStatus() {
    hasUnsavedChanges = false;
    
    $.post('api/update-status.php', {
        order_id: orderId,
        status: $('#orderStatus').val()
    }, function(response) {
        if (response.success) {
            showToast('Đã cập nhật trạng thái', 'success');
            setTimeout(() => location.reload(), 1000);
        }
    });
}

// Utils
function formatMoney(amount) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(amount);
}

function showToast(message, type = 'info') {
    // Create toast container if not exists
    if (!$('#toastContainer').length) {
        $('body').append('<div id="toastContainer" style="position:fixed;top:20px;right:20px;z-index:9999"></div>');
    }
    
    const alertClass = type === 'error' ? 'danger' : type;
    const toast = $(`
        <div class="alert alert-${alertClass} alert-dismissible fade show">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('#toastContainer').append(toast);
    setTimeout(() => toast.alert('close'), 3000);
}
</script>

<?php include 'includes/footer.php'; ?>