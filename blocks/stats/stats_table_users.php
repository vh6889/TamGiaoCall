<div class="row mb-4">
    <div class="col-12">
        <div class="table-container">
            <div class="chart-header">
                <h5 class="chart-title">
                    <i class="fas fa-users text-info me-2"></i>
                    Báo cáo hiệu suất nhân viên
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nhân viên</th>
                            <th>Vai trò</th>
                            <th>Tổng đơn</th>
                            <th>Thành công</th>
                            <th>Thất bại</th>
                            <th>Doanh thu</th>
                            <th>Tỷ lệ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $users = $reportData['users'] ?? [];
                        foreach ($users as $user): 
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($user['full_name'] ?? '') ?></strong></td>
                            <td><span class="badge bg-secondary"><?= $user['role'] ?? '' ?></span></td>
                            <td><?= number_format($user['total_orders'] ?? 0) ?></td>
                            <td class="text-success"><?= number_format($user['success_orders'] ?? 0) ?></td>
                            <td class="text-danger"><?= number_format($user['failed_orders'] ?? 0) ?></td>
                            <td><strong><?= number_format($user['success_revenue'] ?? 0, 0, ',', '.') ?>đ</strong></td>
                            <td>
                                <?php 
                                $rate = $user['success_rate'] ?? 0;
                                $color = $rate >= 70 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
                                ?>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-<?= $color ?>" style="width: <?= $rate ?>%">
                                        <?= number_format($rate, 1) ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>