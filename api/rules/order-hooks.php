<?php
require_once '../../automations/RuleEngine.php';

class OrderHooks {
    private $engine;
    
    public function __construct($db) {
        $this->engine = new RuleEngine($db);
    }
    
    // Trigger khi tạo đơn mới
    public function onOrderCreated($orderId) {
        $this->engine->evaluate('order', $orderId, 'order_created');
    }
    
    // Trigger khi đổi status
    public function onStatusChanged($orderId, $oldStatus, $newStatus) {
        $this->engine->evaluate('order', $orderId, 'status_changed');
    }
    
    // Trigger khi gọi điện
    public function onCallLogged($orderId) {
        $this->engine->evaluate('order', $orderId, 'call_logged');
    }
    
    // Trigger khi assign
    public function onOrderAssigned($orderId, $userId) {
        $this->engine->evaluate('order', $orderId, 'order_assigned');
    }
}

// Sử dụng trong code hiện tại
// Ví dụ trong api/update-status.php
$hooks = new OrderHooks($db);
$hooks->onStatusChanged($orderId, $oldStatus, $newStatus);