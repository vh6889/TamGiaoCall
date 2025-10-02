<?php
/**
 * Order Detail Page - CRM VERSION
 * Tích hợp đầy đủ tính năng CRM với timer cuộc gọi
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
$can_view = is_admin() || is_manager() || 
            ($order['assigned_to'] == $current_user['id']);

if (!$can_view) {
    set_flash('error', 'Bạn không có quyền xem đơn hàng này');
    redirect('orders.php');
}

// Get call history & timeline
$call_history = db_get_results(
    "SELECT c.*, u.full_name as caller_name
     FROM call_logs c
     LEFT JOIN users u ON c.user_id = u.id
     WHERE c.order_id = ?
     ORDER BY c.start_time DESC",
    [$order_id]
);

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

// Calculate totals
$subtotal = array_sum(array_map(function($p) {
    return ($p['price'] ?? 0) * ($p['qty'] ?? 1);
}, $products));

// Get status options
$status_options = db_get_results(
    "SELECT label_key, label_name, color, icon, core_status
     FROM order_labels 
     ORDER BY sort_order, label_name"
);

// Get telesales for admin
$telesales_list = [];
if (is_admin() || is_manager()) {
    $telesales_list = get_telesales('active');
}

$page_title = 'Chi tiết đơn hàng #' . $order['order_number'];
include 'includes/header.php';
?>

<style>
.timeline { 
    max-height: 500px; 
    overflow-y: auto;
    border-left: 3px solid #dee2e6;
    padding-left: 20px;
}
.timeline-item {
    position: relative;
    padding-bottom: 20px;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: -27px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #6c757d;
    border: 3px solid #fff;
}
.timeline-item.call::before { background: #28a745; }
.timeline-item.note::before { background: #17a2b8; }
.timeline-item.status::before { background: #ffc107; }

#callTimer {
    font-family: 'Courier New', monospace;
    font-size: 2rem;
    font-weight: bold;
    color: #dc3545;
    text-align: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
}

.edit-mode input, .edit-mode textarea {
    background-color: #fffbf0;
    border-color: #ffc107;
}

.product-row.editing {
    background-color: #fff3cd;
}
</style>

<div class="row g-4">
    <!-- Left Column -->
    <div class="col-lg-8">
        <!-- Header với Timer -->
        <div class="table-card mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">
                        Đơn hàng #<?= htmlspecialchars($order['order_number']) ?>
                    </h5>
                    <span class="badge mt-2" style="background-color: <?= $order['label_color'] ?? '#6c757d' ?>">
                        <?= htmlspecialchars($order['label_name'] ?? 'Đơn mới') ?>
                    </span>
                    <?php if ($is_locked): ?>
                        <span class="badge bg-secondary ms-2">
                            <i class="fas fa-lock"></i> Đã khóa
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Call Controls with Timer -->
                <div class="text-center">
                    <?php if ($active_call): ?>
                        <!-- Đang gọi - Hiện timer -->
                        <div id="callTimer" data-start="<?= $active_call['start_time'] ?>">00:00:00</div>
                        <button class="btn btn-danger mt-2" onclick="showEndCallModal()">
                            <i class="fas fa-phone-slash"></i> Kết thúc cuộc gọi
                        </button>
                    <?php elseif ($is_my_order && !$is_locked): ?>
                        <!-- Chưa gọi -->
                        <button class="btn btn-success btn-lg" onclick="startCall()">
                            <i class="fas fa-phone"></i> Bắt đầu gọi
                        </button>
                    <?php elseif ($is_free && !is_admin()): ?>
                        <!-- Đơn free -->
                        <button class="btn btn-primary" onclick="claimOrder()">
                            <i class="fas fa-hand-paper"></i> Nhận đơn này
                        </button>
                    <?php endif; ?>
                    
                    <!-- Admin Menu -->
                    <?php if (is_admin()): ?>
                        <button class="btn btn-outline-secondary ms-2" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> Quản lý
                        </button>
                        <ul class="dropdown-menu">
                            <?php if ($is_free): ?>
                                <li><a class="dropdown-item" href="#" onclick="showAssignModal()">
                                    <i class="fas fa-user-plus"></i> Phân công
                                </a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="#" onclick="showTransferModal()">
                                    <i class="fas fa-exchange-alt"></i> Chuyển giao
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="reclaimOrder()">
                                    <i class="fas fa-undo"></i> Thu hồi
                                </a></li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Customer Info (Editable during call) -->
        <div class="table-card mb-3 <?= $active_call ? 'edit-mode' : '' ?>">
            <h6 class="mb-3">
                <i class="fas fa-user me-2"></i>Thông tin khách hàng
                <?php if ($active_call): ?>
                    <span class="badge bg-warning ms-2">Có thể sửa</span>
                <?php endif; ?>
            </h6>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label>Họ tên:</label>
                        <input type="text" class="form-control" id="customer_name" 
                               value="<?= htmlspecialchars($order['customer_name']) ?>"
                               <?= !$active_call ? 'readonly' : '' ?>>
                    </div>
                    <div class="mb-3">
                        <label>Điện thoại:</label>
                        <input type="text" class="form-control" id="customer_phone"
                               value="<?= htmlspecialchars($order['customer_phone']) ?>"
                               <?= !$active_call ? 'readonly' : '' ?>>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label>Email:</label>
                        <input type="email" class="form-control" id="customer_email"
                               value="<?= htmlspecialchars($order['customer_email'] ?? '') ?>"
                               <?= !$active_call ? 'readonly' : '' ?>>
                    </div>
                    <div class="mb-3">
                        <label>Địa chỉ:</label>
                        <textarea class="form-control" id="customer_address" rows="2"
                                  <?= !$active_call ? 'readonly' : '' ?>><?= htmlspecialchars($order['customer_address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <?php if ($active_call): ?>
                <button class="btn btn-primary" onclick="updateCustomerInfo()">
                    <i class="fas fa-save"></i> Lưu thông tin khách hàng
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Products (Editable during call) -->
        <div class="table-card mb-3">
            <h6 class="mb-3">
                <i class="fas fa-box me-2"></i>Sản phẩm
                <?php if ($active_call): ?>
                    <button class="btn btn-sm btn-success float-end" onclick="addProductRow()">
                        <i class="fas fa-plus"></i> Thêm sản phẩm
                    </button>
                <?php endif; ?>
            </h6>
            <div class="table-responsive">
                <table class="table" id="productsTable">
                    <thead>
                        <tr>
                            <th>Tên sản phẩm</th>
                            <th width="100">SL</th>
                            <th width="150">Đơn giá</th>
                            <th width="150">Thành tiền</th>
                            <?php if ($active_call): ?>
                                <th width="50"></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $idx => $product): ?>
                        <tr class="product-row" data-index="<?= $idx ?>">
                            <td>
                                <input type="text" class="form-control product-name" 
                                       value="<?= htmlspecialchars($product['name'] ?? '') ?>"
                                       <?= !$active_call ? 'readonly' : '' ?>>
                            </td>
                            <td>
                                <input type="number" class="form-control product-qty" min="1"
                                       value="<?= $product['qty'] ?? 1 ?>"
                                       <?= !$active_call ? 'readonly' : '' ?>>
                            </td>
                            <td>
                                <input type="number" class="form-control product-price" 
                                       value="<?= $product['price'] ?? 0 ?>"
                                       <?= !$active_call ? 'readonly' : '' ?>>
                            </td>
                            <td class="product-total">
                                <?= format_money(($product['price'] ?? 0) * ($product['qty'] ?? 1)) ?>
                            </td>
                            <?php if ($active_call): ?>
                                <td>
                                    <button class="btn btn-sm btn-danger" onclick="removeProductRow(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-active">
                            <td colspan="3" class="text-end"><h5>Tổng cộng:</h5></td>
                            <td colspan="2">
                                <h5 class="text-danger" id="grandTotal">
                                    <?= format_money($order['total_amount']) ?>
                                </h5>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php if ($active_call): ?>
                <button class="btn btn-primary mt-2" onclick="updateProducts()">
                    <i class="fas fa-save"></i> Lưu danh sách sản phẩm
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Call Timeline -->
        <div class="table-card">
            <h6 class="mb-3">
                <i class="fas fa-history me-2"></i>Lịch sử hoạt động
                <span class="badge bg-secondary"><?= count($call_history) ?> cuộc gọi</span>
            </h6>
            <div class="timeline">
                <?php 
                // Merge calls and notes into timeline
                $timeline = [];
                
                foreach ($call_history as $call) {
                    $timeline[] = [
                        'type' => 'call',
                        'datetime' => $call['start_time'],
                        'user' => $call['caller_name'],
                        'content' => 'Cuộc gọi ' . ($call['duration'] ? gmdate("H:i:s", $call['duration']) : '(đang gọi)'),
                        'note' => $call['note']
                    ];
                }
                
                foreach ($notes as $note) {
                    $timeline[] = [
                        'type' => $note['note_type'],
                        'datetime' => $note['created_at'],
                        'user' => $note['full_name'] ?? 'Hệ thống',
                        'content' => $note['content']
                    ];
                }
                
                // Sort by datetime DESC
                usort($timeline, function($a, $b) {
                    return strtotime($b['datetime']) - strtotime($a['datetime']);
                });
                
                foreach ($timeline as $event): 
                ?>
                <div class="timeline-item <?= $event['type'] ?>">
                    <div class="d-flex justify-content-between">
                        <strong><?= htmlspecialchars($event['user']) ?></strong>
                        <small class="text-muted"><?= time_ago($event['datetime']) ?></small>
                    </div>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($event['content'])) ?></p>
                    <?php if (!empty($event['note'])): ?>
                        <div class="mt-2 p-2 bg-light rounded">
                            <small><?= nl2br(htmlspecialchars($event['note'])) ?></small>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Column -->
    <div class="col-lg-4">
        <!-- Order Stats -->
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title"><i class="fas fa-chart-bar"></i> Thống kê</h6>
                <table class="table table-sm">
                    <tr>
                        <td>Số cuộc gọi:</td>
                        <td class="text-end"><strong><?= $order['call_count'] ?? 0 ?></strong> lần</td>
                    </tr>
                    <tr>
                        <td>Tổng thời gian:</td>
                        <td class="text-end">
                            <strong>
                                <?php
                                $total_duration = array_sum(array_column($call_history, 'duration'));
                                echo gmdate("H:i:s", $total_duration);
                                ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Lần gọi cuối:</td>
                        <td class="text-end">
                            <?= $order['last_call_at'] ? time_ago($order['last_call_at']) : 'Chưa gọi' ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Người xử lý:</td>
                        <td class="text-end">
                            <?= htmlspecialchars($order['assigned_to_name'] ?? 'Chưa phân công') ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-body">
                <h6 class="card-title"><i class="fas fa-bolt"></i> Thao tác nhanh</h6>
                
                <?php if (!$is_locked && $is_my_order): ?>
                    <!-- Status Update -->
                    <div class="mb-3">
                        <label>Cập nhật trạng thái:</label>
                        <select class="form-select" id="quickStatus">
                            <option value="">-- Chọn trạng thái --</option>
                            <?php foreach ($status_options as $opt): ?>
                                <?php if ($opt['core_status'] !== 'new'): ?>
                                <option value="<?= $opt['label_key'] ?>"
                                        data-core="<?= $opt['core_status'] ?>"
                                        <?= $opt['label_key'] == $order['primary_label'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($opt['label_name']) ?>
                                    <?= $opt['core_status'] == 'success' ? '(Hoàn thành)' : '' ?>
                                    <?= $opt['core_status'] == 'failed' ? '(Thất bại)' : '' ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Callback Schedule -->
                    <div class="mb-3">
                        <label>Hẹn gọi lại:</label>
                        <input type="datetime-local" class="form-control" id="callbackTime">
                    </div>
                    
                    <!-- Quick Note -->
                    <div class="mb-3">
                        <label>Ghi chú nhanh:</label>
                        <textarea class="form-control" id="quickNote" rows="3"></textarea>
                    </div>
                    
                    <button class="btn btn-primary w-100" onclick="quickSave()">
                        <i class="fas fa-save"></i> Lưu nhanh
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- End Call Modal -->
<div class="modal fade" id="endCallModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kết thúc cuộc gọi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>Nội dung cuộc gọi: <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="callNote" rows="4" 
                              placeholder="Tóm tắt nội dung trao đổi với khách..." required></textarea>
                </div>
                <div class="mb-3">
                    <label>Cập nhật trạng thái:</label>
                    <select class="form-select" id="callStatus">
                        <option value="">-- Giữ nguyên --</option>
                        <?php foreach ($status_options as $opt): ?>
                            <?php if ($opt['core_status'] !== 'new'): ?>
                            <option value="<?= $opt['label_key'] ?>">
                                <?= htmlspecialchars($opt['label_name']) ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Hẹn gọi lại:</label>
                    <input type="datetime-local" class="form-control" id="callbackTimeModal">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger" onclick="endCall()">
                    <i class="fas fa-phone-slash"></i> Kết thúc cuộc gọi
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Modal Phân công -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Phân công đơn hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>Chọn nhân viên:</label>
                    <select class="form-select" id="assignToUser">
                        <option value="">-- Chọn nhân viên --</option>
                        <?php foreach ($telesales_list as $ts): ?>
                            <option value="<?= $ts['id'] ?>"><?= htmlspecialchars($ts['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="assignOrder()">
                    <i class="fas fa-check"></i> Phân công
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Chuyển giao -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chuyển giao đơn hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Đơn đang được xử lý bởi: <strong><?= htmlspecialchars($order['assigned_to_name'] ?? 'N/A') ?></strong></p>
                <div class="mb-3">
                    <label>Chuyển giao cho:</label>
                    <select class="form-select" id="transferToUser">
                        <option value="">-- Chọn nhân viên --</option>
                        <?php foreach ($telesales_list as $ts): ?>
                            <?php if ($ts['id'] != $order['assigned_to']): ?>
                                <option value="<?= $ts['id'] ?>"><?= htmlspecialchars($ts['full_name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Lý do chuyển giao:</label>
                    <textarea class="form-control" id="transferReason" rows="2" placeholder="Nhập lý do..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-warning" onclick="transferOrder()">
                    <i class="fas fa-exchange-alt"></i> Chuyển giao
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Thêm các functions còn thiếu vào phần JavaScript

// Claim Order (User nhận đơn)
function claimOrder() {
    if (!confirm('Bạn muốn nhận đơn hàng này?')) return;
    
    $.post('api/claim-order.php', {
        csrf_token: csrfToken,
        order_id: orderId
    }, function(response) {
        if (response.success) {
            showToast('Đã nhận đơn hàng thành công', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response.message || 'Có lỗi xảy ra', 'error');
        }
    }).fail(function(xhr) {
        showToast('Lỗi: ' + (xhr.responseJSON?.message || 'Không thể kết nối'), 'error');
    });
}

// Show Assign Modal
function showAssignModal() {
    $('#assignModal').modal('show');
}

// Assign Order
function assignOrder() {
    const userId = $('#assignToUser').val();
    
    if (!userId) {
        showToast('Vui lòng chọn nhân viên', 'warning');
        return;
    }
    
    $.post('api/assign-order.php', {
        csrf_token: csrfToken,
        order_id: orderId,
        user_id: userId
    }, function(response) {
        if (response.success) {
            $('#assignModal').modal('hide');
            showToast('Đã phân công thành công', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response.message || 'Có lỗi xảy ra', 'error');
        }
    }).fail(function(xhr) {
        showToast('Lỗi: ' + (xhr.responseJSON?.message || 'Không thể kết nối'), 'error');
    });
}

// Show Transfer Modal
function showTransferModal() {
    $('#transferModal').modal('show');
}

// Transfer Order
function transferOrder() {
    const userId = $('#transferToUser').val();
    const reason = $('#transferReason').val().trim();
    
    if (!userId) {
        showToast('Vui lòng chọn nhân viên', 'warning');
        return;
    }
    
    // Chuyển giao = thu hồi + phân công lại
    $.post('api/transfer-order.php', {
        csrf_token: csrfToken,
        order_id: orderId,
        new_user_id: userId,
        reason: reason
    }, function(response) {
        if (response.success) {
            $('#transferModal').modal('hide');
            showToast('Đã chuyển giao thành công', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response.message || 'Có lỗi xảy ra', 'error');
        }
    }).fail(function(xhr) {
        showToast('Lỗi: ' + (xhr.responseJSON?.message || 'Không thể kết nối'), 'error');
    });
}

// Reclaim Order (Thu hồi về kho chung)
function reclaimOrder() {
    if (!confirm('Bạn muốn thu hồi đơn hàng này về kho chung?')) return;
    
    $.post('api/reclaim-order.php', {
        csrf_token: csrfToken,
        order_id: orderId
    }, function(response) {
        if (response.success) {
            showToast('Đã thu hồi đơn hàng', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response.message || 'Có lỗi xảy ra', 'error');
        }
    }).fail(function(xhr) {
        showToast('Lỗi: ' + (xhr.responseJSON?.message || 'Không thể kết nối'), 'error');
    });
}

// Quick Save (Lưu nhanh status + note)
function quickSave() {
    const status = $('#quickStatus').val();
    const note = $('#quickNote').val().trim();
    const callback = $('#callbackTime').val();
    
    if (!status && !note && !callback) {
        showToast('Không có gì để lưu', 'warning');
        return;
    }
    
    const requests = [];
    
    // Update status if changed
    if (status && status !== '<?= $order['primary_label'] ?>') {
        requests.push(
            $.post('api/update-status.php', {
                csrf_token: csrfToken,
                order_id: orderId,
                status: status
            })
        );
    }
    
    // Add note if provided
    if (note) {
        requests.push(
            $.post('api/add-note.php', {
                csrf_token: csrfToken,
                order_id: orderId,
                content: note
            })
        );
    }
    
    // Create reminder if callback time set
    if (callback) {
        requests.push(
            $.post('api/create-reminder.php', {
                csrf_token: csrfToken,
                order_id: orderId,
                due_time: callback,
                type: 'callback'
            })
        );
    }
    
    // Execute all requests
    $.when.apply($, requests).done(function() {
        showToast('Đã lưu thành công', 'success');
        setTimeout(() => location.reload(), 1000);
    }).fail(function() {
        showToast('Có lỗi xảy ra khi lưu', 'error');
    });
}

// Improved Toast function
function showToast(message, type = 'info') {
    // Create toast container if not exists
    if (!$('#toastContainer').length) {
        $('body').append('<div id="toastContainer" style="position:fixed;top:20px;right:20px;z-index:9999;"></div>');
    }
    
    const bgClass = {
        'success': 'bg-success',
        'error': 'bg-danger',
        'warning': 'bg-warning',
        'info': 'bg-info'
    }[type] || 'bg-info';
    
    const toast = $(`
        <div class="toast align-items-center text-white ${bgClass} border-0 mb-2" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    
    $('#toastContainer').append(toast);
    const bsToast = new bootstrap.Toast(toast[0], { delay: 3000 });
    bsToast.show();
    
    // Remove after hidden
    toast.on('hidden.bs.toast', function() {
        $(this).remove();
    });
}

// Initialize tooltips
$(document).ready(function() {
    $('[data-bs-toggle="tooltip"]').tooltip();
});
</script>
<script>
const orderId = <?= $order_id ?>;
const csrfToken = '<?= generate_csrf_token() ?>';
let callTimer = null;

// Timer for active call
<?php if ($active_call): ?>
$(document).ready(function() {
    const startTime = new Date('<?= $active_call['start_time'] ?>').getTime();
    
    function updateTimer() {
        const now = new Date().getTime();
        const elapsed = Math.floor((now - startTime) / 1000);
        
        const hours = Math.floor(elapsed / 3600);
        const minutes = Math.floor((elapsed % 3600) / 60);
        const seconds = elapsed % 60;
        
        $('#callTimer').text(
            String(hours).padStart(2, '0') + ':' +
            String(minutes).padStart(2, '0') + ':' +
            String(seconds).padStart(2, '0')
        );
    }
    
    updateTimer();
    callTimer = setInterval(updateTimer, 1000);
});
<?php endif; ?>

// Start Call
function startCall() {
    $.post('api/start-call.php', {
        csrf_token: csrfToken,
        order_id: orderId
    }, function(response) {
        if (response.success) {
            showToast('Đã bắt đầu cuộc gọi', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response.message || 'Có lỗi xảy ra', 'error');
        }
    });
}

// Show End Call Modal
function showEndCallModal() {
    $('#endCallModal').modal('show');
}

// End Call
function endCall() {
    const note = $('#callNote').val().trim();
    const status = $('#callStatus').val();
    const callback = $('#callbackTimeModal').val();
    
    if (!note) {
        showToast('Vui lòng nhập nội dung cuộc gọi', 'warning');
        return;
    }
    
    $.post('api/end-call.php', {
        csrf_token: csrfToken,
        order_id: orderId,
        call_note: note,
        status: status,
        callback_time: callback
    }, function(response) {
        if (response.success) {
            $('#endCallModal').modal('hide');
            showToast('Đã kết thúc cuộc gọi', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response.message || 'Có lỗi xảy ra', 'error');
        }
    });
}

// Update Customer Info
function updateCustomerInfo() {
    const data = {
        csrf_token: csrfToken,
        order_id: orderId,
        update_type: 'customer',
        customer_name: $('#customer_name').val(),
        customer_phone: $('#customer_phone').val(),
        customer_email: $('#customer_email').val(),
        customer_address: $('#customer_address').val()
    };
    
    $.post('api/update-during-call.php', data, function(response) {
        if (response.success) {
            showToast('Đã cập nhật thông tin khách hàng', 'success');
        } else {
            showToast(response.message || 'Có lỗi xảy ra', 'error');
        }
    });
}

// Product Functions
function addProductRow() {
    const newRow = `
        <tr class="product-row">
            <td><input type="text" class="form-control product-name" placeholder="Tên sản phẩm"></td>
            <td><input type="number" class="form-control product-qty" min="1" value="1"></td>
            <td><input type="number" class="form-control product-price" value="0"></td>
            <td class="product-total">0 đ</td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="removeProductRow(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
    $('#productsTable tbody').append(newRow);
    updateProductTotals();
}

function removeProductRow(btn) {
    $(btn).closest('tr').remove();
    updateProductTotals();
}

function updateProductTotals() {
    let grandTotal = 0;
    
    $('.product-row').each(function() {
        const qty = parseInt($(this).find('.product-qty').val()) || 0;
        const price = parseInt($(this).find('.product-price').val()) || 0;
        const total = qty * price;
        
        $(this).find('.product-total').text(formatMoney(total));
        grandTotal += total;
    });
    
    $('#grandTotal').text(formatMoney(grandTotal));
}

function updateProducts() {
    const products = [];
    
    $('.product-row').each(function() {
        const name = $(this).find('.product-name').val();
        if (name) {
            products.push({
                name: name,
                qty: parseInt($(this).find('.product-qty').val()) || 1,
                price: parseInt($(this).find('.product-price').val()) || 0
            });
        }
    });
    
    $.post('api/update-during-call.php', {
        csrf_token: csrfToken,
        order_id: orderId,
        update_type: 'products',
        products: products
    }, function(response) {
        if (response.success) {
            showToast('Đã cập nhật sản phẩm', 'success');
        } else {
            showToast(response.message || 'Có lỗi xảy ra', 'error');
        }
    });
}

// Calculate on change
$(document).on('input', '.product-qty, .product-price', function() {
    updateProductTotals();
});

// Helper functions
function formatMoney(amount) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(amount);
}

function showToast(message, type = 'info') {
    // You can use a toast library here
    alert(message);
}
</script>

<?php include 'includes/footer.php'; ?>