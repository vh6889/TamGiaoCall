<div class="row mb-4">
    <?php
    $metricConfigs = [
        [
            'title' => 'Tổng đơn hàng',
            'value' => $metrics['total_orders'] ?? 0,
            'format' => 'number',
            'icon' => 'fa-shopping-cart',
            'gradient' => 'var(--primary-gradient)',
            'compare' => $comparison['total_orders'] ?? null,
            'drill' => ['type' => 'metric', 'id' => 'total_orders']
        ],
        [
            'title' => 'Doanh thu',
            'value' => $metrics['total_revenue'] ?? 0,
            'format' => 'money',
            'icon' => 'fa-dollar-sign',
            'gradient' => 'var(--success-gradient)',
            'compare' => $comparison['total_revenue'] ?? null,
            'drill' => ['type' => 'metric', 'id' => 'total_revenue']
        ],
        [
            'title' => 'Tỷ lệ thành công',
            'value' => $metrics['success_rate'] ?? 0,
            'format' => 'percent',
            'icon' => 'fa-chart-line',
            'gradient' => 'var(--warning-gradient)',
            'compare' => $comparison['success_rate'] ?? null,
            'drill' => ['type' => 'metric', 'id' => 'success_rate']
        ],
        [
            'title' => 'Khách hàng',
            'value' => $metrics['unique_customers'] ?? 0,
            'format' => 'number',
            'icon' => 'fa-users',
            'gradient' => 'var(--info-gradient)',
            'compare' => $comparison['unique_customers'] ?? null,
            'drill' => ['type' => 'metric', 'id' => 'unique_customers']
        ]
    ];
    
    foreach ($metricConfigs as $config):
        $changePercent = isset($config['compare']['change_percent']) ? $config['compare']['change_percent'] : 0;
        $format = $config['format'] ?? 'number';
    ?>
    <div class="col-md-3 mb-3">
        <div class="metric-card clickable" 
             data-drill-type="<?= $config['drill']['type'] ?>"
             data-drill-id="<?= $config['drill']['id'] ?>">
            <div class="icon" style="background: <?= $config['gradient'] ?>; color: white;">
                <i class="fas <?= $config['icon'] ?>"></i>
            </div>
            <div class="value">
                <?php if ($format == 'money'): ?>
                    <?= number_format($config['value'], 0, ',', '.') ?>đ
                <?php elseif ($format == 'percent'): ?>
                    <?= number_format($config['value'], 1) ?>%
                <?php else: ?>
                    <?= number_format($config['value'], 0, ',', '.') ?>
                <?php endif; ?>
            </div>
            <div class="label"><?= $config['title'] ?></div>
            <?php if ($changePercent != 0): ?>
            <div class="change <?= $changePercent > 0 ? 'positive' : 'negative' ?>">
                <i class="fas fa-arrow-<?= $changePercent > 0 ? 'up' : 'down' ?>"></i>
                <?= abs($changePercent) ?>%
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>