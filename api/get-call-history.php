<?php
/**
 * API: Get Call History - CRM VERSION
 * Lấy lịch sử cuộc gọi theo kiểu CRM
 */
define('TSM_ACCESS', true);
require_once '../system/config.php';
require_once '../system/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

$order_id = (int)($_GET['order_id'] ?? 0);

if (!$order_id) {
    json_error('Invalid order ID', 400);
}

// Kiểm tra quyền xem
$order = get_order($order_id);
if (!$order) {
    json_error('Order not found', 404);
}

$user = get_logged_user();
if (!is_admin() && !is_manager() && $order['assigned_to'] != $user['id']) {
    json_error('Permission denied', 403);
}

try {
    // 1. Lấy tất cả cuộc gọi
    $calls = db_get_results(
        "SELECT c.*, u.full_name as caller_name
         FROM call_logs c
         LEFT JOIN users u ON c.user_id = u.id
         WHERE c.order_id = ?
         ORDER BY c.start_time DESC",
        [$order_id]
    );
    
    // 2. Lấy tất cả notes
    $notes = db_get_results(
        "SELECT n.*, u.full_name as user_name
         FROM order_notes n
         LEFT JOIN users u ON n.user_id = u.id
         WHERE n.order_id = ?
         ORDER BY n.created_at DESC",
        [$order_id]
    );
    
    // 3. Format timeline kiểu CRM
    $timeline = [];
    
    // Thêm cuộc gọi vào timeline
    foreach ($calls as $call) {
        $timeline[] = [
            'type' => 'call',
            'datetime' => $call['start_time'],
            'icon' => 'fa-phone',
            'color' => $call['status'] === 'active' ? 'success' : 'primary',
            'title' => "Cuộc gọi bởi " . $call['caller_name'],
            'content' => [
                'duration' => $call['duration'] ? gmdate("H:i:s", $call['duration']) : 'Đang gọi...',
                'note' => $call['note'],
                'status' => $call['status']
            ]
        ];
    }
    
    // Thêm notes vào timeline
    foreach ($notes as $note) {
        $icon = 'fa-sticky-note';
        $color = 'info';
        
        switch ($note['note_type']) {
            case 'system':
                $icon = 'fa-cog';
                $color = 'secondary';
                break;
            case 'assignment':
                $icon = 'fa-user-check';
                $color = 'warning';
                break;
            case 'status':
                $icon = 'fa-exchange-alt';
                $color = 'success';
                break;
        }
        
        $timeline[] = [
            'type' => 'note',
            'datetime' => $note['created_at'],
            'icon' => $icon,
            'color' => $color,
            'title' => $note['user_name'] ?: 'Hệ thống',
            'content' => $note['content']
        ];
    }
    
    // Sắp xếp timeline theo thời gian
    usort($timeline, function($a, $b) {
        return strtotime($b['datetime']) - strtotime($a['datetime']);
    });
    
    // 4. Thống kê tổng quan
    $stats = [
        'total_calls' => count($calls),
        'total_duration' => array_sum(array_column($calls, 'duration')),
        'active_call' => count(array_filter($calls, fn($c) => $c['status'] === 'active')) > 0,
        'last_call' => $calls[0]['start_time'] ?? null,
        'total_notes' => count($notes)
    ];
    
    json_success('Call history loaded', [
        'stats' => $stats,
        'timeline' => $timeline,
        'calls' => $calls,
        'notes' => $notes
    ]);
    
} catch (Exception $e) {
    json_error($e->getMessage());
}
?>