<div class="dashboard-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="mb-2">
                <i class="fas fa-chart-line me-2"></i>
                <?= $page_title ?>
            </h1>
            <p class="mb-0 opacity-75">
                Dữ liệu từ <?= date('d/m/Y', strtotime($date_from)) ?> 
                đến <?= date('d/m/Y', strtotime($date_to)) ?>
            </p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div class="export-buttons justify-content-md-end">
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" 
                   class="btn btn-light btn-export">
                    <i class="fas fa-file-excel text-success me-2"></i>Excel
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" 
                   class="btn btn-light btn-export">
                    <i class="fas fa-file-csv text-info me-2"></i>CSV
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'pdf'])) ?>" 
                   class="btn btn-light btn-export">
                    <i class="fas fa-file-pdf text-danger me-2"></i>PDF
                </a>
            </div>
        </div>
    </div>
</div>