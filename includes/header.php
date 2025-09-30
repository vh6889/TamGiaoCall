<?php
// Bảo vệ, không cho truy cập trực tiếp
if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

// CHỈ NẠP Ở ĐÂY
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Lấy thông tin người dùng đang đăng nhập
$current_user = get_current_user();
// Lấy trang hiện tại để active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?> - <?php echo SITE_NAME; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        :root {
            --sidebar-width: 250px;
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        body {
            background-color: #f4f7f6;
        }
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: #fff;
            position: fixed;
            height: 100%;
            transition: all 0.3s;
        }
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .sidebar-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .sidebar-nav {
            list-style: none;
            padding: 20px 0;
        }
        .sidebar-nav a {
            display: block;
            padding: 12px 20px;
            color: #eee;
            text-decoration: none;
            transition: all 0.2s;
        }
        .sidebar-nav a:hover, .sidebar-nav li.active > a {
            background-color: rgba(255,255,255,0.15);
            color: #fff;
            border-left: 4px solid #fff;
        }
        .sidebar-nav a i {
            width: 30px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            transition: all 0.3s;
        }
        .top-navbar {
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 1rem 2rem;
        }
        .table-card {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .stat-card {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .stat-card .icon {
            font-size: 24px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-headset"></i> <?php echo get_setting('site_name', SITE_NAME); ?></h3>
            </div>
            <ul class="sidebar-nav">
                <li class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Trang chủ</a>
                </li>
                <li class="<?php echo ($current_page == 'orders.php' || $current_page == 'order-detail.php') ? 'active' : ''; ?>">
                    <a href="orders.php"><i class="fas fa-shopping-cart"></i> Quản lý đơn hàng</a>
                </li>
                <?php if (is_admin()): ?>
                <li class="<?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
                    <a href="users.php"><i class="fas fa-users-cog"></i> Quản lý nhân viên</a>
                </li>
				<li class="<?php echo ($current_page == 'customer-history.php') ? 'active' : ''; ?>">
					<a href="customer-history.php"><i class="fas fa-address-book"></i> Lịch sử Khách hàng</a>
				</li>
				<li class="<?php echo ($current_page == 'pending-orders.php') ? 'active' : ''; ?>">
					<a href="pending-orders.php">
						<i class="fas fa-user-check"></i> Duyệt đơn
						<?php 
							$pending_count = count_orders(['status' => 'pending_approval']);
							if ($pending_count > 0) echo "<span class='badge bg-danger ms-2'>{$pending_count}</span>";
						?>
					</a>
				</li>
                <li class="<?php echo ($current_page == 'statistics.php') ? 'active' : ''; ?>">
                    <a href="statistics.php"><i class="fas fa-chart-bar"></i> Thống kê</a>
                </li>
                <li class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                    <a href="settings.php"><i class="fas fa-cogs"></i> Cài đặt</a>
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