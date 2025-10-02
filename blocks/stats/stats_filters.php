<div class="filter-section">
    <form method="GET" class="row g-3" id="filterForm">
        <div class="col-md-2">
            <label class="form-label">Từ ngày</label>
            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Giờ</label>
            <input type="time" name="time_from" class="form-control" value="<?= htmlspecialchars($time_from) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Đến ngày</label>
            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Giờ</label>
            <input type="time" name="time_to" class="form-control" value="<?= htmlspecialchars($time_to) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Loại báo cáo</label>
            <select name="report_type" class="form-select" id="reportTypeSelect">
                <option value="overview" <?= $report_type == 'overview' ? 'selected' : '' ?>>Tổng quan</option>
                <option value="users" <?= $report_type == 'users' ? 'selected' : '' ?>>Nhân viên</option>
                <option value="products" <?= $report_type == 'products' ? 'selected' : '' ?>>Sản phẩm</option>
                <option value="customers" <?= $report_type == 'customers' ? 'selected' : '' ?>>Khách hàng</option>
                <option value="orders" <?= $report_type == 'orders' ? 'selected' : '' ?>>Đơn hàng</option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-filter me-2"></i>Lọc dữ liệu
            </button>
        </div>
    </form>
</div>