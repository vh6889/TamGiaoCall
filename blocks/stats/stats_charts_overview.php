<div class="row mb-4">
    <div class="col-md-8">
        <div class="chart-container">
            <div class="chart-header">
                <h5 class="chart-title">
                    <i class="fas fa-chart-area text-primary me-2"></i>
                    Xu hướng theo ngày
                </h5>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary active" data-chart="revenue">
                        Doanh thu
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-chart="orders">
                        Đơn hàng
                    </button>
                </div>
            </div>
            <canvas id="trendChart"></canvas>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="chart-container">
            <div class="chart-header">
                <h5 class="chart-title">
                    <i class="fas fa-chart-pie text-warning me-2"></i>
                    Phân bố trạng thái
                </h5>
            </div>
            <canvas id="distributionChart"></canvas>
        </div>
    </div>
</div>