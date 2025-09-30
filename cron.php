<?php
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

// Get pending reminds
$pending = get_pending_reminders();
foreach ($pending as $rem) {
    if ($rem['remind_time'] <= date('Y-m-d H:i:s')) {
        // Gửi remind (placeholder: log hoặc email)
        log_activity('send_remind', "Sent remind for order #{$rem['order_id']} (type: {$rem['type']})", 'order', $rem['order_id']);
        // Nếu muốn email: mail(get_user($rem['user_id'])['email'], 'Remind', 'Action order #'.$rem['order_id']);
        db_update('reminders', ['status' => 'sent'], 'id = ?', [$rem['id']]);
    }
}

// Get overdue
$overdue = get_overdue_reminders(5);  // Grace 5 min
foreach ($overdue as $rem) {
    $configs = get_order_status_configs();
    $status_config = $configs[$current_status] ?? [];  // Lấy từ order status
    if (!empty($status_config['logic']['auto_lock_on_overdue'])) {
        db_update('users', ['status' => 'suspended'], 'id = ?', [$rem['user_id']]);
        log_activity('lock_user', "Locked user #{$rem['user_id']} due to overdue remind on order #{$rem['order_id']}", 'user', $rem['user_id']);
    }
    db_update('reminders', ['status' => 'overdue'], 'id = ?', [$rem['id']]);
}
echo "Cron completed.";