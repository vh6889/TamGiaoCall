<?php
/**
 * Admin Rules Management Page
 * File: admin-rules.php
 */

define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/RuleEngine.php';

require_admin();

$page_title = 'Quản Lý Quy Tắc Động';

use TSM\RuleEngine\RuleEngine;

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_rule':
            $ruleData = [
                'rule_key' => sanitize($_POST['rule_key']),
                'name' => sanitize($_POST['name']),
                'description' => sanitize($_POST['description']),
                'entity_type' => sanitize($_POST['entity_type']),
                'rule_type' => sanitize($_POST['rule_type']),
                'priority' => (int)$_POST['priority'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'trigger_conditions' => $_POST['trigger_conditions'], // JSON
                'actions' => $_POST['actions'], // JSON
                'metadata' => $_POST['metadata'] ?? '{}',
                'created_by' => get_logged_user()['id']
            ];
            
            try {
                db_insert('rules', $ruleData);
                set_flash('success', 'Quy tắc đã được tạo thành công!');
            } catch (Exception $e) {
                set_flash('error', 'Lỗi: ' . $e->getMessage());
            }
            redirect('admin-rules.php');
            break;
            
        case 'update_rule':
            $ruleId = (int)$_POST['rule_id'];
            $ruleData = [
                'name' => sanitize($_POST['name']),
                'description' => sanitize($_POST['description']),
                'entity_type' => sanitize($_POST['entity_type']),
                'rule_type' => sanitize($_POST['rule_type']),
                'priority' => (int)$_POST['priority'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'trigger_conditions' => $_POST['trigger_conditions'],
                'actions' => $_POST['actions'],
                'metadata' => $_POST['metadata'] ?? '{}',
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            try {
                db_update('rules', $ruleData, 'id = ?', [$ruleId]);
                set_flash('success', 'Quy tắc đã được cập nhật!');
            } catch (Exception $e) {
                set_flash('error', 'Lỗi: ' . $e->getMessage());
            }
            redirect('admin-rules.php');
            break;
            
        case 'delete_rule':
            $ruleId = (int)$_POST['rule_id'];
            try {
                db_delete('rules', 'id = ?', [$ruleId]);
                set_flash('success', 'Quy tắc đã được xóa!');
            } catch (Exception $e) {
                set_flash('error', 'Lỗi: ' . $e->getMessage());
            }
            redirect('admin-rules.php');
            break;
    }
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['ajax']) {
        case 'get_rule':
            $ruleId = (int)$_GET['id'];
            $rule = db_get_row("SELECT * FROM rules WHERE id = ?", [$ruleId]);
            if ($rule) {
                $rule['trigger_conditions'] = json_decode($rule['trigger_conditions'], true);
                $rule['actions'] = json_decode($rule['actions'], true);
                $rule['metadata'] = json_decode($rule['metadata'], true);
            }
            echo json_encode($rule);
            exit;
            
        case 'test_rule':
            $ruleId = (int)$_POST['rule_id'];
            $testData = json_decode($_POST['test_data'], true);
            
            // Test rule with sample data
            $engine = new RuleEngine($db);
            $rule = db_get_row("SELECT * FROM rules WHERE id = ?", [$ruleId]);
            
            if ($rule) {
                $result = $engine->testRule($rule, $testData);
                echo json_encode(['success' => true, 'result' => $result]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Rule not found']);
            }
            exit;
            
        case 'get_templates':
            $templates = db_get_results("SELECT * FROM rule_templates WHERE is_active = 1 ORDER BY name");
            echo json_encode($templates);
            exit;
    }
}

// Load data
$rules = db_get_results("
    SELECT r.*, u.full_name as created_by_name,
           (SELECT COUNT(*) FROM rule_executions WHERE rule_id = r.id) as execution_count,
           (SELECT COUNT(*) FROM rule_executions WHERE rule_id = r.id AND execution_status = 'success') as success_count
    FROM rules r
    LEFT JOIN users u ON r.created_by = u.id
    ORDER BY r.priority DESC, r.name ASC
");

$statuses = db_get_results("SELECT * FROM status_definitions ORDER BY entity_type, sort_order");
$customerLabels = db_get_results("SELECT * FROM customer_labels ORDER BY label_name");
$userLabels = db_get_results("SELECT * FROM user_labels ORDER BY label_name");

include 'includes/header.php';
?>

<style>
.rule-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    transition: all 0.3s;
}
.rule-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.rule-active {
    border-left: 4px solid #28a745;
}
.rule-inactive {
    border-left: 4px solid #6c757d;
    opacity: 0.7;
}
.condition-group {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 10px;
    margin: 5px 0;
}
.action-item {
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: 4px;
    padding: 8px;
    margin: 5px 0;
}
.priority-badge {
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 12px;
}
.priority-high { background: #ff6b6b; color: white; }
.priority-medium { background: #ffd93d; color: #333; }
.priority-low { background: #6bcf7f; color: white; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-magic me-2"></i>Quản Lý Quy Tắc Động</h4>
        <div>
            <button class="btn btn-outline-primary me-2" onclick="loadTemplates()">
                <i class="fas fa-file-import me-1"></i> Mẫu có sẵn
            </button>
            <button class="btn btn-primary" onclick="showCreateRuleModal()">
                <i class="fas fa-plus me-1"></i> Tạo quy tắc mới
            </button>
        </div>
    </div>

    <?php display_flash(); ?>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3><?php echo count($rules); ?></h3>
                    <small class="text-muted">Tổng số quy tắc</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3><?php echo count(array_filter($rules, fn($r) => $r['is_active'])); ?></h3>
                    <small class="text-muted">Đang hoạt động</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3><?php echo array_sum(array_column($rules, 'execution_count')); ?></h3>
                    <small class="text-muted">Lần thực thi</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <?php 
                    $totalExec = array_sum(array_column($rules, 'execution_count'));
                    $totalSuccess = array_sum(array_column($rules, 'success_count'));
                    $successRate = $totalExec > 0 ? round(($totalSuccess / $totalExec) * 100) : 0;
                    ?>
                    <h3><?php echo $successRate; ?>%</h3>
                    <small class="text-muted">Tỷ lệ thành công</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <select class="form-select" id="filterEntity" onchange="filterRules()">
                        <option value="">Tất cả loại</option>
                        <option value="order">Đơn hàng</option>
                        <option value="customer">Khách hàng</option>
                        <option value="employee">Nhân viên</option>
                        <option value="task">Nhiệm vụ</option>
                        <option value="system">Hệ thống</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterStatus" onchange="filterRules()">
                        <option value="">Tất cả trạng thái</option>
                        <option value="active">Đang hoạt động</option>
                        <option value="inactive">Không hoạt động</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" id="filterSearch" placeholder="Tìm kiếm theo tên..." onkeyup="filterRules()">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Đặt lại
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Rules List -->
    <div id="rulesList">
        <?php foreach ($rules as $rule): ?>
            <?php 
            $conditions = json_decode($rule['trigger_conditions'], true);
            $actions = json_decode($rule['actions'], true);
            ?>
            <div class="rule-card <?php echo $rule['is_active'] ? 'rule-active' : 'rule-inactive'; ?>" 
                 data-entity="<?php echo $rule['entity_type']; ?>"
                 data-status="<?php echo $rule['is_active'] ? 'active' : 'inactive'; ?>"
                 data-name="<?php echo strtolower($rule['name']); ?>">
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center mb-2">
                            <h5 class="mb-0 me-3"><?php echo htmlspecialchars($rule['name']); ?></h5>
                            <?php if ($rule['priority'] >= 80): ?>
                                <span class="priority-badge priority-high">Ưu tiên cao</span>
                            <?php elseif ($rule['priority'] >= 50): ?>
                                <span class="priority-badge priority-medium">Ưu tiên TB</span>
                            <?php else: ?>
                                <span class="priority-badge priority-low">Ưu tiên thấp</span>
                            <?php endif; ?>
                            <span class="badge bg-<?php echo $rule['is_active'] ? 'success' : 'secondary'; ?> ms-2">
                                <?php echo $rule['is_active'] ? 'Hoạt động' : 'Không hoạt động'; ?>
                            </span>
                        </div>
                        
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($rule['description']); ?></p>
                        
                        <div class="row mb-2">
                            <div class="col-md-4">
                                <small class="text-muted">Loại:</small>
                                <span class="badge bg-info"><?php echo ucfirst($rule['entity_type']); ?></span>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Kiểu:</small>
                                <span><?php echo str_replace('_', ' ', $rule['rule_type']); ?></span>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Thực thi:</small>
                                <span><?php echo $rule['execution_count']; ?> lần</span>
                                <?php if ($rule['execution_count'] > 0): ?>
                                    <span class="text-success">(<?php echo $rule['success_count']; ?> thành công)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Quick View Conditions -->
                        <div class="mb-2">
                            <strong>Điều kiện:</strong>
                            <code><?php echo formatConditionsSummary($conditions); ?></code>
                        </div>
                        
                        <!-- Quick View Actions -->
                        <div>
                            <strong>Hành động:</strong>
                            <?php foreach ($actions as $action): ?>
                                <span class="badge bg-primary me-1"><?php echo $action['type']; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-4 text-end">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewRule(<?php echo $rule['id']; ?>)" title="Xem chi tiết">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="editRule(<?php echo $rule['id']; ?>)" title="Sửa">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="testRule(<?php echo $rule['id']; ?>)" title="Test">
                                <i class="fas fa-flask"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="duplicateRule(<?php echo $rule['id']; ?>)" title="Nhân bản">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteRule(<?php echo $rule['id']; ?>)" title="Xóa">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted d-block">
                                Tạo bởi: <?php echo htmlspecialchars($rule['created_by_name'] ?? 'System'); ?>
                            </small>
                            <small class="text-muted">
                                <?php echo date('d/m/Y H:i', strtotime($rule['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($rules)): ?>
            <div class="text-center py-5">
                <i class="fas fa-magic fa-3x text-muted mb-3"></i>
                <p class="text-muted">Chưa có quy tắc nào được tạo</p>
                <button class="btn btn-primary" onclick="showCreateRuleModal()">
                    <i class="fas fa-plus me-1"></i> Tạo quy tắc đầu tiên
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Rule Builder Modal -->
<div class="modal fade" id="ruleModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ruleModalTitle">Tạo Quy Tắc Mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="ruleForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create_rule">
                    <input type="hidden" name="rule_id" id="ruleId">
                    <input type="hidden" name="trigger_conditions" id="triggerConditions">
                    <input type="hidden" name="actions" id="actionsJson">
                    <input type="hidden" name="metadata" id="metadataJson" value="{}">
                    
                    <!-- Basic Info -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Mã quy tắc <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="rule_key" id="ruleKey" required 
                                   pattern="[a-z0-9_]+" title="Chỉ chữ thường, số và dấu gạch dưới">
                            <small class="text-muted">Ví dụ: auto_vip_customer</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tên quy tắc <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="ruleName" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="description" id="ruleDescription" rows="2"></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Áp dụng cho <span class="text-danger">*</span></label>
                            <select class="form-select" name="entity_type" id="entityType" required onchange="updateAvailableFields()">
                                <option value="">Chọn...</option>
                                <option value="order">Đơn hàng</option>
                                <option value="customer">Khách hàng</option>
                                <option value="employee">Nhân viên</option>
                                <option value="task">Nhiệm vụ</option>
                                <option value="system">Hệ thống</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Loại quy tắc <span class="text-danger">*</span></label>
                            <select class="form-select" name="rule_type" id="ruleType" required>
                                <option value="">Chọn...</option>
                                <option value="status_transition">Chuyển trạng thái</option>
                                <option value="time_based">Theo thời gian</option>
                                <option value="event_based">Theo sự kiện</option>
                                <option value="condition_based">Theo điều kiện</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Độ ưu tiên <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="priority" id="priority" 
                                   min="1" max="100" value="50" required>
                            <small class="text-muted">1-100 (cao hơn = ưu tiên)</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Trạng thái</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
                                <label class="form-check-label" for="isActive">Kích hoạt</label>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Conditions Builder -->
                    <div class="mb-4">
                        <h6><i class="fas fa-filter me-2"></i>Điều Kiện Kích Hoạt</h6>
                        <div id="conditionsBuilder" class="border rounded p-3">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Actions Builder -->
                    <div class="mb-4">
                        <h6><i class="fas fa-bolt me-2"></i>Hành Động Thực Hiện</h6>
                        <div id="actionsBuilder" class="border rounded p-3">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-info" onclick="validateRule()">
                        <i class="fas fa-check me-1"></i> Kiểm tra
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Lưu quy tắc
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
function formatConditionsSummary($conditions) {
    if (!$conditions) return 'N/A';
    
    $summary = [];
    if (isset($conditions['type'])) {
        $summary[] = $conditions['type'];
    }
    if (isset($conditions['conditions']) && is_array($conditions['conditions'])) {
        foreach ($conditions['conditions'] as $cond) {
            if (isset($cond['field']) && isset($cond['operator'])) {
                $summary[] = $cond['field'] . ' ' . $cond['operator'] . ' ' . ($cond['value'] ?? '');
            }
        }
    }
    
    return implode(' | ', array_slice($summary, 0, 3)) . (count($summary) > 3 ? '...' : '');
}
?>

<script src="assets/js/rule-builder.js"></script>

<?php include 'includes/footer.php'; ?>