<?php
require_once '../../system/config.php';
require_once '../../automations/RuleEngine.php';

header('Content-Type: application/json');

$manager = new RuleManager($db);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                $rules = $manager->listRules($_GET);
                echo json_encode(['success' => true, 'rules' => $rules]);
            } elseif (isset($_GET['id'])) {
                $rule = $manager->getRule($_GET['id']);
                echo json_encode(['success' => true, 'rule' => $rule]);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $ruleId = $manager->createRule($data);
            echo json_encode(['success' => true, 'rule_id' => $ruleId]);
            break;
            
        case 'PUT':
            if (!isset($_GET['id'])) {
                throw new Exception('Rule ID required');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $manager->updateRule($_GET['id'], $data);
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            if (!isset($_GET['id'])) {
                throw new Exception('Rule ID required');
            }
            $manager->deleteRule($_GET['id']);
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}