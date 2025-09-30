<?php
/**
 * KPI Management Page (Admin only)
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

require_admin();

$page_title = 'Quản lý KPIs & Mục tiêu';

// Lấy tháng/năm để xem, mặc định là tháng hiện tại
$target_month_input = $_GET['month'] ?? date('Y-m');
$target_month_date = new DateTime($target_month_input . '-01');
$target_month_sql = $target_month_date->format('Y-m-01');
$month_start = $target_month_date->format('Y-m-01');
$month_end = $target_month_date->format('Y-m-t');

// 1. Lấy danh sách tất cả telesale đang hoạt động
$telesales = get_telesales('active');

// 2. Lấy dữ liệu KPI đã thiết lập và đã đạt được của tháng đang xem
$kpi_data = [];
if (!empty($telesales)) {
    foreach ($telesales as $ts) {
        $user_id = $ts['id'];
        
        // Lấy mục tiêu đã set từ bảng `kpis`
        $targets = db_get_results("SELECT target_type, target_value FROM kpis WHERE user_id = ? AND target_month = ?", [$user_id, $target_month_sql]);
        $kpi_data[$user_id]['targets'] = [
            'confirmed_orders' => 0,
            'total_revenue' => 0
        ];
        foreach($targets as $target) {
            $kpi_data[$user_id]['targets'][$target['target_type']] = $target['target_value'];
        }

        // Lấy kết quả đã đạt được từ bảng `orders`
        $achieved = db_get_row(
            "SELECT COUNT(id) as confirmed_orders, SUM(CASE WHEN status = 'confirmed' THEN total_amount ELSE 0 END) as total_revenue
             FROM orders
             WHERE assigned_to = ? AND status = 'confirmed' AND DATE(completed_at) BETWEEN ? AND ?",
            [$user_id, $month_start, $month_end]
        );
        $kpi_data[$user_id]['achieved'] = [
            'confirmed_orders' => $achieved['confirmed_orders'] ?? 0,
            'total_revenue' => $achieved['total_revenue'] ?? 0
        ];
    }
}

include 'includes/header.php';
?>

<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-bullseye me-2"></i>Quản lý KPIs & Mục tiêu</h5>
        <form method="GET" class="d-flex align-items-center">
            <label for="month" class="form-label me-2 mb-0">Chọn tháng:</label>
            <input type="month" class="form-control form-control-sm" id="month" name="month" value="<?php echo $target_month_input; ?>" onchange="this.form.submit()">
        </form>
    </div>
    
    <form id="kpiForm">
        <input type="hidden" name="target_month" value="<?php echo $target_month_sql; ?>">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nhân viên</th>
                        <th>Mục tiêu Đơn hàng</th>
                        <th>Đạt được</th>
                        <th>Tiến độ</th>
                        <th>Mục tiêu Doanh thu</th>
                        <th>Đạt được</th>
                        <th>Tiến độ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($telesales)): ?>
                    <tr><td colspan="7" class="text-center text-muted">Chưa có nhân viên telesale nào.</td></tr>
                    <?php else: ?>
                    <?php foreach ($telesales as $ts): ?>
                    <?php $user_id = $ts['id']; $data = $kpi_data[$user_id]; ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($ts['full_name']); ?></strong></td>
                        <td>
                            <input type="number" class="form-control form-control-sm" 
                                   name="kpis[<?php echo $user_id; ?>][confirmed_orders]"
                                   value="<?php echo $data['targets']['confirmed_orders']; ?>" min="0">
                        </td>
                        <td><?php echo $data['achieved']['confirmed_orders']; ?></td>
                        <td>
                            <?php 
                            $orders_progress = $data['targets']['confirmed_orders'] > 0 
                                ? round(($data['achieved']['confirmed_orders'] / $data['targets']['confirmed_orders']) * 100, 1) 
                                : 0; 
                            ?>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar <?php echo $orders_progress >= 100 ? 'bg-success' : ''; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo min(100, $orders_progress); ?>%;" 
                                     aria-valuenow="<?php echo $orders_progress; ?>" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    <?php echo $orders_progress; ?>%
                                </div>
                            </div>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm" 
                                   name="kpis[<?php echo $user_id; ?>][total_revenue]"
                                   value="<?php echo $data['targets']['total_revenue']; ?>" min="0" step="1000">
                        </td>
                        <td><?php echo format_money($data['achieved']['total_revenue']); ?></td>
                        <td>
                            <?php 
                            $revenue_progress = $data['targets']['total_revenue'] > 0 
                                ? round(($data['achieved']['total_revenue'] / $data['targets']['total_revenue']) * 100, 1) 
                                : 0; 
                            ?>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar <?php echo $revenue_progress >= 100 ? 'bg-success' : ''; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo min(100, $revenue_progress); ?>%;" 
                                     aria-valuenow="<?php echo $revenue_progress; ?>" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    <?php echo $revenue_progress; ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <hr>
        <div class="text-end">
            <button type="submit" class="btn btn-primary" id="btnSaveKpis">
                <i class="fas fa-save me-2"></i>Lưu lại mục tiêu
            </button>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    $('#kpiForm').submit(function(e) {
        e.preventDefault();
        const btn = $('#btnSaveKpis');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Đang lưu...');

        const formData = $(this).serializeArray();
        let postData = {
            target_month: '',
            kpis: {}
        };

        formData.forEach(function(item) {
            if (item.name === 'target_month') {
                postData.target_month = item.value;
            } else {
                // Parse names like kpis[1][confirmed_orders]
                const match = item.name.match(/kpis\[(\d+)\]\[(\w+)\]/);
                if (match) {
                    const userId = match[1];
                    const kpiType = match[2];
                    if (!postData.kpis[userId]) {
                        postData.kpis[userId] = {};
                    }
                    postData.kpis[userId][kpiType] = item.value;
                }
            }
        });

        $.ajax({
            url: 'api/save-kpi.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(postData),
            success: function(response) {
                if (response.success) {
                    showToast('Đã lưu mục tiêu thành công!', 'success');
                    // Optional: reload to show updated progress, though not necessary
                    // setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                }
            },
            error: function() {
                showToast('Không thể kết nối đến máy chủ.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Lưu lại mục tiêu');
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>