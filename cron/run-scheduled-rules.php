<?php
require_once '../config.php';
require_once '../RuleEngine.php';

$engine = new RuleEngine($db);

// Run time-based rules cho orders
$orders = $db->query("
    SELECT id FROM orders 
    WHERE status NOT IN (SELECT label_key AS status_key, FROM order_labels WHERE label LIKE '%hoàn%' OR label LIKE '%hủy%')
")->fetchAll();

foreach ($orders as $order) {
    $engine->evaluate('order', $order['id'], 'scheduled_check');
}

// Run rules cho employees
$employees = $db->query("
    SELECT id FROM users WHERE status = 'active'
")->fetchAll();

foreach ($employees as $emp) {
    $engine->evaluate('employee', $emp['id'], 'scheduled_check');
}

echo "Scheduled rules executed at " . date('Y-m-d H:i:s');