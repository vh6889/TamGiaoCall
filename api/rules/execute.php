
<?php
require_once '../../system/config.php';
require_once '../../automations/RuleEngine.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$entityType = $data['entity_type'] ?? null;
$entityId = $data['entity_id'] ?? null;
$triggerEvent = $data['trigger_event'] ?? null;

if (!$entityType || !$entityId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

try {
    $engine = new RuleEngine($db);
    $results = $engine->evaluate($entityType, $entityId, $triggerEvent);
    
    echo json_encode([
        'success' => true,
        'executed_rules' => $results
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}