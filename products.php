<?php
/**
 * Products Management System
 * Professional inventory management with categories, attributes, pricing, and suppliers
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

// Only admin can access this module
require_admin();

$page_title = 'Quản lý Sản phẩm';

// Get active tab
$tab = $_GET['tab'] ?? 'products';

// Get filters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

// Build query for products
if ($tab == 'products') {
    $where = "1=1";
    $params = [];
    
    if ($search) {
        $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if ($category_filter) {
        $where .= " AND p.category_id = ?";
        $params[] = $category_filter;
    }
    
    if ($status_filter) {
        $where .= " AND p.status = ?";
        $params[] = $status_filter;
    }
    
    if ($stock_filter == 'low') {
        $where .= " AND p.stock_quantity <= p.low_stock_threshold AND p.manage_stock = 1";
    } elseif ($stock_filter == 'out') {
        $where .= " AND p.stock_quantity = 0 AND p.manage_stock = 1";
    }
    
    // Get total count
    $total = db_get_var("SELECT COUNT(*) FROM products p WHERE $where", $params);
    $total_pages = ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;
    
    // Get products with category
    $products = db_get_results("
        SELECT p.*, 
               c.name as category_name,
               (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
               (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) as variant_count
        FROM products p
        LEFT JOIN product_categories c ON p.category_id = c.id
        WHERE $where
        ORDER BY p.updated_at DESC
        LIMIT $offset, $per_page
    ", $params);
}

// Get categories for dropdown
$categories = db_get_results("
    SELECT * FROM product_categories 
    WHERE is_active = 1 
    ORDER BY parent_id, sort_order, name
");

// Get suppliers
$suppliers = db_get_results("
    SELECT * FROM suppliers 
    WHERE is_active = 1 
    ORDER BY name
");

// Get attributes
$attributes = db_get_results("
    SELECT * FROM product_attributes 
    ORDER BY sort_order, attribute_name
");

include 'includes/header.php';
?>

<style>
.nav-tabs .nav-link {
    color: #495057;
    border-radius: 10px 10px 0 0;
}
.nav-tabs .nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: transparent;
}
.product-card {
    transition: transform 0.2s;
    border-radius: 10px;
    overflow: hidden;
}
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}
.product-image {
    height: 200px;
    object-fit: cover;
    width: 100%;
}
.stock-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1;
}
.price-tag {
    font-size: 1.2rem;
    font-weight: bold;
    color: #28a745;
}
.old-price {
    text-decoration: line-through;
    color: #6c757d;
    font-size: 0.9rem;
}
.category-tree {
    list-style: none;
    padding-left: 20px;
}
.category-tree li {
    position: relative;
    padding: 5px 0;
}
.category-tree li::before {
    content: '├─';
    position: absolute;
    left: -15px;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-boxes"></i> Quản lý Kho hàng</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
        <i class="fas fa-plus"></i> Thêm sản phẩm mới
    </button>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?= $tab == 'products' ? 'active' : '' ?>" href="?tab=products">
            <i class="fas fa-box"></i> Sản phẩm
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab == 'categories' ? 'active' : '' ?>" href="?tab=categories">
            <i class="fas fa-folder-tree"></i> Danh mục
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab == 'attributes' ? 'active' : '' ?>" href="?tab=attributes">
            <i class="fas fa-tags"></i> Thuộc tính
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab == 'suppliers' ? 'active' : '' ?>" href="?tab=suppliers">
            <i class="fas fa-truck"></i> Nhà cung cấp
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab == 'coupons' ? 'active' : '' ?>" href="?tab=coupons">
            <i class="fas fa-percentage"></i> Mã giảm giá
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab == 'stock' ? 'active' : '' ?>" href="?tab=stock">
            <i class="fas fa-warehouse"></i> Tồn kho
        </a>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content">
    <?php if ($tab == 'products'): ?>
    <!-- Products Tab -->
    <div class="tab-pane active">
        <!-- Filters -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <input type="hidden" name="tab" value="products">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Tìm tên, SKU, barcode..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="category">
                            <option value="">-- Danh mục --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                    <?= str_repeat('— ', $cat['parent_id'] ? 1 : 0) . htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">-- Trạng thái --</option>
                            <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Đang bán</option>
                            <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Ngừng bán</option>
                            <option value="draft" <?= $status_filter == 'draft' ? 'selected' : '' ?>>Nháp</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="stock">
                            <option value="">-- Tồn kho --</option>
                            <option value="low" <?= $stock_filter == 'low' ? 'selected' : '' ?>>Sắp hết</option>
                            <option value="out" <?= $stock_filter == 'out' ? 'selected' : '' ?>>Hết hàng</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Tìm kiếm
                        </button>
                        <a href="?tab=products" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Products Grid -->
        <div class="row">
            <?php foreach ($products as $product): ?>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card product-card h-100">
                    <?php if ($product['stock_quantity'] <= 0 && $product['manage_stock']): ?>
                        <span class="badge bg-danger stock-badge">Hết hàng</span>
                    <?php elseif ($product['stock_quantity'] <= $product['low_stock_threshold'] && $product['manage_stock']): ?>
                        <span class="badge bg-warning stock-badge">Sắp hết</span>
                    <?php endif; ?>
                    
                    <img src="<?= $product['primary_image'] ?? 'assets/img/no-image.png' ?>" class="product-image" alt="<?= htmlspecialchars($product['name']) ?>">
                    
                    <div class="card-body">
                        <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
                        <p class="text-muted small mb-1">SKU: <?= htmlspecialchars($product['sku']) ?></p>
                        <?php if ($product['category_name']): ?>
                            <span class="badge bg-info mb-2"><?= htmlspecialchars($product['category_name']) ?></span>
                        <?php endif; ?>
                        
                        <div class="mb-2">
                            <?php if ($product['sale_price']): ?>
                                <span class="price-tag"><?= format_money($product['sale_price']) ?></span>
                                <span class="old-price ms-2"><?= format_money($product['regular_price']) ?></span>
                            <?php else: ?>
                                <span class="price-tag"><?= format_money($product['regular_price']) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($product['manage_stock']): ?>
                            <p class="mb-2">
                                <i class="fas fa-warehouse"></i> Tồn: <strong><?= $product['stock_quantity'] ?></strong>
                                <?php if ($product['variant_count'] > 0): ?>
                                    <span class="badge bg-secondary ms-1"><?= $product['variant_count'] ?> biến thể</span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="btn-group w-100">
                            <button class="btn btn-sm btn-outline-primary" onclick="editProduct(<?= $product['id'] ?>)">
                                <i class="fas fa-edit"></i> Sửa
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="viewProduct(<?= $product['id'] ?>)">
                                <i class="fas fa-eye"></i> Xem
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?= $product['id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?tab=products&page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>&status=<?= $status_filter ?>&stock=<?= $stock_filter ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    
    <?php elseif ($tab == 'categories'): ?>
    <!-- Categories Tab -->
    <div class="tab-pane active">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6>Thêm danh mục mới</h6>
                    </div>
                    <div class="card-body">
                        <form id="categoryForm">
                            <div class="mb-3">
                                <label>Tên danh mục</label>
                                <input type="text" class="form-control" id="catName" required>
                            </div>
                            <div class="mb-3">
                                <label>Danh mục cha</label>
                                <select class="form-select" id="catParent">
                                    <option value="">-- Không có --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <?php if (!$cat['parent_id']): ?>
                                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Mô tả</label>
                                <textarea class="form-control" id="catDescription" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Lưu danh mục
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h6>Danh sách danh mục</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tên danh mục</th>
                                        <th>Slug</th>
                                        <th>Danh mục cha</th>
                                        <th>Trạng thái</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $all_categories = db_get_results("
                                        SELECT c1.*, c2.name as parent_name 
                                        FROM product_categories c1
                                        LEFT JOIN product_categories c2 ON c1.parent_id = c2.id
                                        ORDER BY c1.parent_id, c1.sort_order, c1.name
                                    ");
                                    foreach ($all_categories as $cat):
                                    ?>
                                    <tr>
                                        <td><?= $cat['id'] ?></td>
                                        <td>
                                            <?= $cat['parent_id'] ? '— ' : '' ?>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($cat['slug']) ?></td>
                                        <td><?= htmlspecialchars($cat['parent_name'] ?? '-') ?></td>
                                        <td>
                                            <?php if ($cat['is_active']): ?>
                                                <span class="badge bg-success">Hoạt động</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Ẩn</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editCategory(<?= $cat['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory(<?= $cat['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($tab == 'suppliers'): ?>
    <!-- Suppliers Tab -->
    <div class="tab-pane active">
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
            <i class="fas fa-plus"></i> Thêm nhà cung cấp
        </button>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Mã NCC</th>
                                <th>Tên nhà cung cấp</th>
                                <th>Liên hệ</th>
                                <th>Điện thoại</th>
                                <th>API Sync</th>
                                <th>Lần sync cuối</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $sup): ?>
                            <tr>
                                <td><?= htmlspecialchars($sup['supplier_code']) ?></td>
                                <td><strong><?= htmlspecialchars($sup['name']) ?></strong></td>
                                <td><?= htmlspecialchars($sup['contact_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($sup['phone'] ?? '-') ?></td>
                                <td>
                                    <?php if ($sup['sync_enabled']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> Đã kết nối
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Thủ công</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $sup['last_sync_at'] ? date('d/m/Y H:i', strtotime($sup['last_sync_at'])) : '-' ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editSupplier(<?= $sup['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($sup['sync_enabled']): ?>
                                            <button class="btn btn-sm btn-outline-info" onclick="syncSupplier(<?= $sup['id'] ?>)">
                                                <i class="fas fa-sync"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteSupplier(<?= $sup['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
    
    <?php elseif ($tab == 'stock'): ?>
    <!-- Stock Management Tab -->
    <div class="tab-pane active">
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary">
                            <?= db_get_var("SELECT COUNT(*) FROM products WHERE manage_stock = 1") ?>
                        </h3>
                        <p class="mb-0">Tổng sản phẩm</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success">
                            <?= db_get_var("SELECT SUM(stock_quantity * cost_price) FROM products WHERE manage_stock = 1") ?? 0 ?>
                        </h3>
                        <p class="mb-0">Giá trị tồn kho</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning">
                            <?= db_get_var("SELECT COUNT(*) FROM products WHERE stock_quantity <= low_stock_threshold AND manage_stock = 1") ?>
                        </h3>
                        <p class="mb-0">Sắp hết hàng</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-danger">
                            <?= db_get_var("SELECT COUNT(*) FROM products WHERE stock_quantity = 0 AND manage_stock = 1") ?>
                        </h3>
                        <p class="mb-0">Hết hàng</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stock movements history -->
        <div class="card">
            <div class="card-header">
                <h6>Lịch sử xuất nhập kho</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Thời gian</th>
                                <th>Sản phẩm</th>
                                <th>Loại</th>
                                <th>Số lượng</th>
                                <th>Tồn trước</th>
                                <th>Tồn sau</th>
                                <th>Ghi chú</th>
                                <th>Người thực hiện</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $movements = db_get_results("
                                SELECT sm.*, p.name as product_name, p.sku, u.full_name
                                FROM stock_movements sm
                                JOIN products p ON sm.product_id = p.id
                                LEFT JOIN users u ON sm.created_by = u.id
                                ORDER BY sm.created_at DESC
                                LIMIT 50
                            ");
                            foreach ($movements as $move):
                            ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($move['created_at'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($move['product_name']) ?></strong><br>
                                    <small class="text-muted">SKU: <?= htmlspecialchars($move['sku']) ?></small>
                                </td>
                                <td>
                                    <?php
                                    $type_badges = [
                                        'in' => 'success',
                                        'out' => 'danger',
                                        'adjustment' => 'warning',
                                        'return' => 'info'
                                    ];
                                    $type_labels = [
                                        'in' => 'Nhập',
                                        'out' => 'Xuất',
                                        'adjustment' => 'Điều chỉnh',
                                        'return' => 'Hoàn trả'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $type_badges[$move['type']] ?>">
                                        <?= $type_labels[$move['type']] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($move['quantity'] > 0): ?>
                                        <span class="text-success">+<?= $move['quantity'] ?></span>
                                    <?php else: ?>
                                        <span class="text-danger"><?= $move['quantity'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $move['stock_before'] ?? '-' ?></td>
                                <td><strong><?= $move['stock_after'] ?? '-' ?></strong></td>
                                <td><?= htmlspecialchars($move['notes'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($move['full_name'] ?? 'Hệ thống') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm sản phẩm mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="productForm">
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Basic Info -->
                            <h6 class="mb-3">Thông tin cơ bản</h6>
                            <div class="mb-3">
                                <label>Tên sản phẩm <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Mã SKU <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="sku" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Mã vạch (Barcode)</label>
                                    <input type="text" class="form-control" name="barcode">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Danh mục</label>
                                <select class="form-select" name="category_id">
                                    <option value="">-- Chọn danh mục --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>">
                                            <?= str_repeat('— ', $cat['parent_id'] ? 1 : 0) . htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Mô tả ngắn</label>
                                <textarea class="form-control" name="short_description" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label>Mô tả chi tiết</label>
                                <textarea class="form-control" name="description" rows="4"></textarea>
                            </div>
                            
                            <!-- Attributes -->
                            <h6 class="mb-3">Thuộc tính sản phẩm</h6>
                            <div class="row">
                                <?php foreach ($attributes as $attr): ?>
                                <div class="col-md-6 mb-3">
                                    <label><?= htmlspecialchars($attr['attribute_name']) ?></label>
                                    <input type="text" class="form-control" name="attributes[<?= $attr['id'] ?>]">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Pricing -->
                            <h6 class="mb-3">Giá bán</h6>
                            <div class="mb-3">
                                <label>Giá gốc <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="regular_price" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label>Giá khuyến mại</label>
                                <input type="number" class="form-control" name="sale_price" min="0">
                            </div>
                            <div class="mb-3">
                                <label>Giá nhập/vốn</label>
                                <input type="number" class="form-control" name="cost_price" min="0">
                            </div>
                            
                            <!-- Stock -->
                            <h6 class="mb-3">Kho hàng</h6>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="manage_stock" id="manageStock" checked>
                                <label class="form-check-label" for="manageStock">
                                    Quản lý tồn kho
                                </label>
                            </div>
                            <div class="mb-3" id="stockFields">
                                <label>Số lượng trong kho</label>
                                <input type="number" class="form-control" name="stock_quantity" value="0" min="0">
                                <label class="mt-2">Ngưỡng cảnh báo</label>
                                <input type="number" class="form-control" name="low_stock_threshold" value="10" min="0">
                            </div>
                            
                            <!-- Images -->
                            <h6 class="mb-3">Hình ảnh</h6>
                            <div class="mb-3">
                                <input type="file" class="form-control" name="images[]" multiple accept="image/*">
                                <small class="text-muted">Chọn nhiều ảnh, ảnh đầu tiên là ảnh chính</small>
                            </div>
                            
                            <!-- Supplier -->
                            <h6 class="mb-3">Nhà cung cấp</h6>
                            <select class="form-select mb-3" name="supplier_id">
                                <option value="">-- Chọn nhà cung cấp --</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <!-- Status -->
                            <h6 class="mb-3">Trạng thái</h6>
                            <select class="form-select" name="status">
                                <option value="active">Đang bán</option>
                                <option value="inactive">Ngừng bán</option>
                                <option value="draft">Nháp</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveProduct()">
                    <i class="fas fa-save"></i> Lưu sản phẩm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm nhà cung cấp</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="supplierForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Mã nhà cung cấp <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="supplier_code" required>
                            </div>
                            <div class="mb-3">
                                <label>Tên nhà cung cấp <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label>Người liên hệ</label>
                                <input type="text" class="form-control" name="contact_name">
                            </div>
                            <div class="mb-3">
                                <label>Số điện thoại</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Địa chỉ</label>
                                <textarea class="form-control" name="address" rows="3"></textarea>
                            </div>
                            <h6>Tích hợp API (Tùy chọn)</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="sync_enabled" id="syncEnabled">
                                <label class="form-check-label" for="syncEnabled">
                                    Kết nối API tự động đồng bộ
                                </label>
                            </div>
                            <div id="apiFields" style="display:none;">
                                <div class="mb-3">
                                    <label>API Endpoint</label>
                                    <input type="url" class="form-control" name="api_endpoint">
                                </div>
                                <div class="mb-3">
                                    <label>API Key</label>
                                    <input type="text" class="form-control" name="api_key">
                                </div>
                                <div class="mb-3">
                                    <label>API Secret</label>
                                    <input type="password" class="form-control" name="api_secret">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveSupplier()">
                    <i class="fas fa-save"></i> Lưu nhà cung cấp
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= generate_csrf_token() ?>';

// Toggle stock management fields
$('#manageStock').change(function() {
    if (this.checked) {
        $('#stockFields').show();
    } else {
        $('#stockFields').hide();
    }
});

// Toggle API fields
$('#syncEnabled').change(function() {
    if (this.checked) {
        $('#apiFields').show();
    } else {
        $('#apiFields').hide();
    }
});

// Save product
function saveProduct() {
    const formData = new FormData($('#productForm')[0]);
    formData.append('csrf_token', csrfToken);
    
    $.ajax({
        url: 'api/save-product.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                showToast('Đã lưu sản phẩm thành công', 'success');
                $('#addProductModal').modal('hide');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(response.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: function() {
            showToast('Lỗi kết nối', 'error');
        }
    });
}

// Save category
$('#categoryForm').submit(function(e) {
    e.preventDefault();
    
    $.post('api/save-category.php', {
        csrf_token: csrfToken,
        name: $('#catName').val(),
        parent_id: $('#catParent').val(),
        description: $('#catDescription').val()
    }, function(response) {
        if (response.success) {
            showToast('Đã thêm danh mục', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response.message || 'Có lỗi xảy ra', 'error');
        }
    });
});

// Save supplier
function saveSupplier() {
    const formData = $('#supplierForm').serialize();
    
    $.post('api/save-supplier.php', formData + '&csrf_token=' + csrfToken, function(response) {
        if (response.success) {
            showToast('Đã lưu nhà cung cấp', 'success');
            $('#addSupplierModal').modal('hide');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response.message || 'Có lỗi xảy ra', 'error');
        }
    });
}

// Sync supplier products
function syncSupplier(supplierId) {
    if (!confirm('Đồng bộ sản phẩm từ nhà cung cấp này?')) return;
    
    showToast('Đang đồng bộ...', 'info');
    
    $.post('api/sync-supplier.php', {
        csrf_token: csrfToken,
        supplier_id: supplierId
    }, function(response) {
        if (response.success) {
            showToast(`Đã đồng bộ ${response.count} sản phẩm`, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response.message || 'Lỗi đồng bộ', 'error');
        }
    });
}

// Delete functions
function deleteProduct(id) {
    if (!confirm('Xóa sản phẩm này?')) return;
    
    $.post('api/delete-product.php', {
        csrf_token: csrfToken,
        product_id: id
    }, function(response) {
        if (response.success) {
            showToast('Đã xóa sản phẩm', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response.message || 'Có lỗi xảy ra', 'error');
        }
    });
}

function deleteCategory(id) {
    if (!confirm('Xóa danh mục này?')) return;
    
    $.post('api/delete-category.php', {
        csrf_token: csrfToken,
        category_id: id
    }, function(response) {
        if (response.success) {
            showToast('Đã xóa danh mục', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response.message || 'Có lỗi xảy ra', 'error');
        }
    });
}

// View product details
function viewProduct(id) {
    window.location.href = 'product-detail.php?id=' + id;
}

// Edit product
function editProduct(id) {
    window.location.href = 'product-edit.php?id=' + id;
}

// Toast notification
function showToast(message, type = 'info') {
    // Create toast container if not exists
    if (!$('#toastContainer').length) {
        $('body').append('<div id="toastContainer" style="position:fixed;top:20px;right:20px;z-index:9999;"></div>');
    }
    
    const bgClass = {
        'success': 'bg-success',
        'error': 'bg-danger',
        'warning': 'bg-warning',
        'info': 'bg-info'
    }[type] || 'bg-info';
    
    const toast = $(`
        <div class="toast align-items-center text-white ${bgClass} border-0 mb-2" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    
    $('#toastContainer').append(toast);
    const bsToast = new bootstrap.Toast(toast[0], { delay: 3000 });
    bsToast.show();
    
    toast.on('hidden.bs.toast', function() {
        $(this).remove();
    });
}
</script>

<?php include 'includes/footer.php'; ?>