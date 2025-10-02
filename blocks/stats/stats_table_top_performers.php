<div class="row mb-4">
    <div class="col-12">
        <div class="table-container">
            <div class="chart-header">
                <h5 class="chart-title">
                    <i class="fas fa-trophy text-warning me-2"></i>
                    Top nhân viên xuất sắc
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Xếp hạng</th>
                            <th>Nhân viên</th>
                            <th>Vai trò</th>
                            <th>Tổng đơn</th>
                            <th>Thành công</th>
                            <th>Doanh thu</th>
                            <th>Tỷ lệ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        $users = $topPerformers['users'] ?? [];
                        foreach ($users as $user): 
                            $successRate = ($user['total_orders'] ?? 0) > 0 
                                ? round(($user['success_orders'] ?? 0) * 100 / $user['total_orders'], 1) 
                                : 0;
                        ?>
                        <tr>
                            <td>
                                <?php if ($rank <= 3): ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-medal"></i> <?= $rank ?>
                                </span>
                                <?php else: ?>
                                <?= $rank ?>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($user['full_name'] ?? '') ?></strong></td>
                            <td><span class="badge bg-secondary"><?= $user['role'] ?? '' ?></span></td>
                            <td><?= number_format($user['total_orders'] ?? 0) ?></td>
                            <td><?= number_format($user['success_orders'] ?? 0) ?></td>
                            <td><?= number_format($user['success_revenue'] ?? 0, 0, ',', '.') ?>đ</td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-success" style="width: <?= $successRate ?>%">
                                        <?= $successRate ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php 
                        $rank++;
                        if ($rank > 10) break;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>