<div class="row mb-4">
    <div class="col-12">
        <div class="table-container">
            <div class="chart-header">
                <h5 class="chart-title">
                    <i class="fas fa-box text-warning me-2"></i>
                    Báo cáo sản phẩm bán chạy
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>SKU</th>
                            <th>Tên sản phẩm</th>
                            <th>Số lượng bán</th>
                            <th>Doanh thu</th>
                            <th>Số đơn hàng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $products = $reportData['products'] ?? [];
                        $rank = 1;
                        foreach ($products as $product): 
                        ?>
                        <tr>
                            <td><?= $rank++ ?></td>
                            <td><code><?= htmlspecialchars($product['sku'] ?? '') ?></code></td>
                            <td><strong><?= htmlspecialchars($product['name'] ?? '') ?></strong></td>
                            <td><?= number_format($product['total_quantity'] ?? 0) ?></td>
                            <td><strong class="text-success"><?= number_format($product['total_revenue'] ?? 0, 0, ',', '.') ?>đ</strong></td>
                            <td><?= number_format($product['order_count'] ?? 0) ?></td>
                        </tr>
                        <?php 
                        if ($rank > 50) break;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>