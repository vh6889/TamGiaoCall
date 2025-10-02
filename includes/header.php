<?php
// Bảo vệ, không cho truy cập trực tiếp
if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

// Lấy thông tin người dùng đang đăng nhập
$current_user = get_logged_user();
// Lấy trang hiện tại để active menu
$current_page = basename($_SERVER['PHP_SELF']);

// === CẬP NHẬT LOGIC ACTIVE SUBMENU ===
// Nhóm các trang con của Admin/Manager để xác định active state cho submenu
$system_pages = [
    '../admin_panel/users.php',
    '../admin_panel/manager-assignments.php',
    '../admin_panel/manage-order-statuses.php',
    '../admin_panel/manage-customer-labels.php',
    '../admin_panel/manage-user-labels.php',
    '../admin_panel/admin-rules.php',
    '../admin_panel/settings.php',
	'../admin_panel/kpi.php',
];
$is_system_page_active = in_array($current_page, $system_pages);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
	<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        body { background-color: #f4f7f6; }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: #fff;
            position: fixed;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            flex-shrink: 0;
        }
        .sidebar-header h3 { font-size: 1.5rem; font-weight: 700; }
        .sidebar-nav {
            list-style: none;
            padding: 20px 0;
            flex-grow: 1;
            overflow-y: auto;
        }
        .sidebar-nav::-webkit-scrollbar { width: 8px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.3); border-radius: 4px; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #eee;
            text-decoration: none;
            transition: all 0.2s;
        }
        .sidebar-nav a:hover, .sidebar-nav li.active > a {
            background-color: rgba(255,255,255,0.15);
            color: #fff;
        }
        .sidebar-nav li.active > a { border-left: 4px solid #fff; }
        .sidebar-nav a i.nav-icon { width: 30px; text-align: center; margin-right: 10px; }
        .sidebar-nav .submenu {
            list-style: none;
            padding-left: 0;
            background-color: rgba(0,0,0,0.2);
        }
        .sidebar-nav .submenu a { padding-left: 40px; }
        .sidebar-nav .submenu a:hover, .sidebar-nav .submenu li.active > a { background-color: rgba(0,0,0,0.3); }
        .sidebar-nav a .arrow { margin-left: auto; transition: transform 0.3s; }
        .sidebar-nav a[aria-expanded="true"] .arrow { transform: rotate(90deg); }
        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); }
        .top-navbar { background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 1rem 2rem; }
        .table-card, .stat-card { background-color: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    </style>

<meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
</head>
<body>
    <div class="wrapper">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-headset"></i> <?php echo get_setting('site_name', SITE_NAME); ?></h3>
            </div>
            
            <ul class="sidebar-nav">
                <li class="<?php echo ($current_page == '../admin_panel/dashboard.php') ? 'active' : ''; ?>">
                    <a href="../admin_panel/dashboard.php"><i class="fas fa-tachometer-alt nav-icon"></i> Trang chủ</a>
                </li>
				<?php if (is_admin()): ?>
				<li class="<?php echo ($current_page == '../product_panel/products.php') ? 'active' : ''; ?>">
					<a href="../product_panel/products.php">
						<i class="fas fa-boxes nav-icon"></i> Quản lý hàng hoá
					</a>
				</li>
				<?php endif; ?>
                <li class="<?php echo ($current_page == '../order_panel/orders.php' || $current_page == 'order-detail.php') ? 'active' : ''; ?>">
                    <a href="../order_panel/orders.php"><i class="fas fa-shopping-cart nav-icon"></i> Quản lý đơn hàng</a>
                </li>
                
                <?php if (is_admin() || is_manager()): ?>
                <li class="<?php echo ($current_page == '../order_panel/customer-history.php') ? 'active' : ''; ?>">
                    <a href="../order_panel/customer-history.php"><i class="fas fa-address-book nav-icon"></i> Lịch sử Khách hàng</a>
                </li>
                <li class="<?php echo ($current_page == '../order_panel/pending-orders.php') ? 'active' : ''; ?>">
                    <a href="../order_panel/pending-orders.php">
                        <i class="fas fa-user-check nav-icon"></i> Duyệt đơn
                        <?php
                        // Đếm số đơn chờ duyệt và hiển thị badge nếu có
                        $pending_count = count_orders(['primary_label' => 'pending_approval']);
                        if ($pending_count > 0) echo "<span class='badge bg-danger ms-auto'>{$pending_count}</span>";
                        ?>
                    </a>
                </li>
                <li class="<?php echo ($current_page == '../admin_panel/stats.php') ? 'active' : ''; ?>">
                    <a href="../admin_panel/stats.php"><i class="fas fa-chart-bar nav-icon"></i> Thống kê</a>
                </li>
                <?php endif; ?>
                
                <?php if (is_admin() || is_manager()): ?>
                <li class="<?php echo $is_system_page_active ? 'active' : ''; ?>">
                    <a href="#systemSettings" data-bs-toggle="collapse" aria-expanded="<?php echo $is_system_page_active ? 'true' : 'false'; ?>">
                        <i class="fas fa-cogs nav-icon"></i>
                        Quản trị Hệ thống
                        <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <ul class="collapse submenu <?php echo $is_system_page_active ? 'show' : ''; ?>" id="systemSettings">
						<li class="<?php echo ($current_page == '../admin_panel/kpi.php') ? 'active' : ''; ?>">
                        <a href="kpi.php">
                            <i class="fas fa-bullseye nav-icon"></i> 
                            <?php echo is_admin() ? 'Quản lý KPI' : 'Xem KPI'; ?>
                        </a>
                    </li>
                        <li class="<?php echo ($current_page == '../admin_panel/users.php') ? 'active' : ''; ?>">
                            <a href="../admin_panel/users.php">
                                <i class="fas fa-users-cog nav-icon"></i> 
                                <?php echo is_admin() ? 'Quản lý nhân viên' : 'Xem nhân viên'; ?>
                            </a>
                        </li>
                        
                        <?php if (is_admin()): // Chỉ Admin mới thấy các mục cấu hình sâu ?>
                        <li class="<?php echo ($current_page == '../admin_panel/manager-assignments.php') ? 'active' : ''; ?>">
                            <a href="../admin_panel/manager-assignments.php"><i class="fas fa-sitemap nav-icon"></i> Phân công Manager</a>
                        </li>
                        <li class="<?php echo ($current_page == '../admin_panel/manage-order-labels.php') ? 'active' : ''; ?>">
							<a href="../admin_panel/manage-order-labels.php"><i class="fas fa-tags nav-icon"></i> Nhãn Đơn Hàng</a>
						</li>
						<li class="<?php echo ($current_page == '../admin_panel/manage-customer-labels.php') ? 'active' : ''; ?>">
							<a href="../admin_panel/manage-customer-labels.php"><i class="fas fa-user-tag nav-icon"></i> Nhãn Khách Hàng</a>
						</li>
						<li class="<?php echo ($current_page == '../admin_panel/manage-user-labels.php') ? 'active' : ''; ?>">
							<a href="../admin_panel/manage-user-labels.php"><i class="fas fa-user-shield nav-icon"></i> Nhãn Nhân Viên</a>
						</li>
						<li class="<?php echo ($current_page == '../admin_panel/admin-rules.php' || $current_page == '../admin_panel/admin-rules.php') ? 'active' : ''; ?>">
							<a href="../admin_panel/admin-rules.php"><i class="fas fa-magic nav-icon"></i> Xây dựng Quy tắc</a>
						</li>
                         <li class="<?php echo ($current_page == '../admin_panel/settings.php') ? 'active' : ''; ?>">
                            <a href="settings.php"><i class="fas fa-tools nav-icon"></i> Cài đặt chung</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="main-content">
            <header class="top-navbar d-flex justify-content-end align-items-center">
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle fa-2x me-2"></i>
                        <span><?php echo htmlspecialchars($current_user['full_name']); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit me-2"></i>Hồ sơ cá nhân</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
            </header>
            <main class="p-4">