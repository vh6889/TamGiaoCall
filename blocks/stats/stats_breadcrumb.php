<div class="breadcrumb-drill mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="?<?= http_build_query(array_diff_key($_GET, array_flip(['drill', 'drill_id']))) ?>">
                    <i class="fas fa-home"></i> Tổng quan
                </a>
            </li>
            <?php foreach ($drilldownData['breadcrumbs'] as $crumb): ?>
            <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['label']) ?></li>
            <?php endforeach; ?>
        </ol>
    </nav>
</div>