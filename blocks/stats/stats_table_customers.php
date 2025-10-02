<div class="row mb-4">
    <div class="col-12">
        <div class="table-container">
            <div class="chart-header">
                <h5 class="chart-title">
                    <i class="fas fa-user-friends text-success me-2"></i>
                    Báo cáo khách hàng
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Khách hàng</th>
                            <th>Số điện thoại</th>
                            <th>Email</th>
                            <th>Tổng đơn</th>
                            <th>Tổng giá trị</th>
                            <th>Lần cuối mua</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $customers = $reportData['customers'] ?? [];
                        foreach ($customers as $customer): 
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($customer['customer_name'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($customer['customer_phone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($customer['customer_email'] ?? '-') ?></td>
                            <td><?= number_format($customer['total_orders'] ?? 0) ?></td>
                            <td><strong><?= number_format($customer['total_value'] ?? 0, 0, ',', '.') ?>đ</strong></td>
                            <td>
                                <?php 
                                if (!empty($customer['last_order_date'])) {
                                    echo date('d/m/Y H:i', strtotime($customer['last_order_date']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>