<?php
define('TSM_ACCESS', true);
require_once '../system/config.php';
require_once '../automations/RuleEngine.php';

$engine = new RuleEngine($pdo);

// Run rules cho orders không hoàn thành
$orders = db_get_results("
    SELECT id FROM orders 
    WHERE status NOT IN ('completed', 'cancelled')
");

foreach ($orders as $order) {
    $engine->evaluate('order', $order['id'], 'scheduled');
}

echo "Rules executed: " . date('Y-m-d H:i:s');