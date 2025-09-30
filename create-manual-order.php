<?php
/**
 * Create Manual Order Page
 */
define('TSM_ACCESS', true);


require_login();

$page_title = 'Tạo đơn hàng mới';

include 'includes/header.php';
?>

<div class="table-card">
    <h5 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Tạo đơn hàng thủ công</h5>
    <p>Điền thông tin khách hàng và sản phẩm để tạo một đơn hàng mới. Đơn hàng sẽ được gửi cho quản trị viên duyệt trước khi được xử lý.</p>
    <hr>
    
    <form id="manualOrderForm">
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-user me-2"></i>Thông tin khách hàng</h6>
                <div class="mb-3">
                    <label for="customer_name" class="form-label">Họ tên <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                </div>
                <div class="mb-3">
                    <label for="customer_phone" class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="customer_phone" name="customer_phone" required>
                </div>
                <div class="mb-3">
                    <label for="customer_address" class="form-label">Địa chỉ</label>
                    <input type="text" class="form-control" id="customer_address" name="customer_address">
                </div>
            </div>
            <div class="col-md-6">
                 <h6><i class="fas fa-edit me-2"></i>Ghi chú</h6>
                 <div class="mb-3">
                    <label for="customer_notes" class="form-label">Ghi chú của khách hàng</label>
                    <textarea class="form-control" id="customer_notes" name="customer_notes" rows="5"></textarea>
                </div>
            </div>
        </div>
        <hr>

        <h6><i class="fas fa-boxes me-2"></i>Chi tiết sản phẩm</h6>
        <div class="table-responsive">
            <table class="table" id="productTable">
                <thead>
                    <tr>
                        <th>Tên sản phẩm <span class="text-danger">*</span></th>
                        <th width="120">Số lượng <span class="text-danger">*</span></th>
                        <th width="200">Đơn giá <span class="text-danger">*</span></th>
                        <th width="200">Thành tiền</th>
                        <th width="50"></th>
                    </tr>
                </thead>
                <tbody id="productLines">
                    </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addProductBtn">
                                <i class="fas fa-plus"></i> Thêm sản phẩm
                            </button>
                        </td>
                    </tr>
                    <tr class="table-light">
                        <td colspan="3" class="text-end"><strong>Tổng cộng:</strong></td>
                        <td colspan="2"><strong id="totalAmount" class="fs-5 text-danger">0₫</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <hr>

        <div class="text-end">
            <button type="submit" class="btn btn-primary" id="btnSubmitOrder">
                <i class="fas fa-paper-plane me-2"></i>Gửi duyệt đơn hàng
            </button>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    // Function to add a new product row
    function addProductRow() {
        const rowId = Date.now();
        const newRow = `
            <tr id="row_${rowId}">
                <td><input type="text" class="form-control product-name" required></td>
                <td><input type="number" class="form-control product-qty" value="1" min="1" required></td>
                <td><input type="number" class="form-control product-price" value="0" min="0" step="1000" required></td>
                <td><strong class="line-total">0₫</strong></td>
                <td><button type="button" class="btn btn-sm btn-danger remove-product-btn" data-rowid="${rowId}"><i class="fas fa-trash"></i></button></td>
            </tr>`;
        $('#productLines').append(newRow);
    }

    // Add first row on page load
    addProductRow();

    $('#addProductBtn').click(addProductRow);

    // Remove product row
    $('#productTable').on('click', '.remove-product-btn', function() {
        const rowId = $(this).data('rowid');
        $('#row_' + rowId).remove();
        updateTotal();
    });

    // Update total when qty or price changes
    $('#productTable').on('input', '.product-qty, .product-price', function() {
        const row = $(this).closest('tr');
        const qty = parseFloat(row.find('.product-qty').val()) || 0;
        const price = parseFloat(row.find('.product-price').val()) || 0;
        const lineTotal = qty * price;
        row.find('.line-total').text(formatMoney(lineTotal));
        updateTotal();
    });

    // Function to update the grand total
    function updateTotal() {
        let grandTotal = 0;
        $('.line-total').each(function() {
            grandTotal += parseFloat($(this).text().replace(/[^\d]/g, '')) || 0;
        });
        $('#totalAmount').text(formatMoney(grandTotal));
    }
    
    // Helper to format money
    function formatMoney(amount) {
        return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
    }
    
    // Form submission
    $('#manualOrderForm').submit(function(e) {
        e.preventDefault();
        const btn = $('#btnSubmitOrder');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Đang gửi...');

        let products = [];
        let isValid = true;
        $('#productLines tr').each(function() {
            const name = $(this).find('.product-name').val().trim();
            const qty = parseInt($(this).find('.product-qty').val());
            const price = parseFloat($(this).find('.product-price').val());

            if (!name || isNaN(qty) || qty <= 0 || isNaN(price)) {
                isValid = false;
                return; // exit loop
            }
            products.push({ name: name, qty: qty, price: price * qty });
        });

        if (!isValid || products.length === 0) {
            showToast('Vui lòng điền đầy đủ thông tin sản phẩm.', 'error');
            btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Gửi duyệt đơn hàng');
            return;
        }

        const orderData = {
            customer_name: $('#customer_name').val(),
            customer_phone: $('#customer_phone').val(),
            customer_address: $('#customer_address').val(),
            customer_notes: $('#customer_notes').val(),
            total_amount: parseFloat($('#totalAmount').text().replace(/[^\d]/g, '')),
            products: products
        };

        $.ajax({
            url: 'api/submit-manual-order.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(orderData),
            success: function(response) {
                if (response.success) {
                    showToast('Đã gửi đơn hàng đi duyệt thành công!', 'success');
                    setTimeout(() => window.location.href = 'orders.php', 1500);
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                    btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Gửi duyệt đơn hàng');
                }
            },
            error: function() {
                showToast('Không thể kết nối đến máy chủ.', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Gửi duyệt đơn hàng');
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>