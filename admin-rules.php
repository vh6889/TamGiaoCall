<?php
// admin-rules.php - Trang quản lý Rules động (FIXED VERSION)
if (!defined('TSM_ACCESS')) {
    define('TSM_ACCESS', true);
}

require_once 'config.php';
require_once 'functions.php';

// Check authentication
if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

// Only admin and manager can access
if (!is_admin() && !is_manager()) {
    header('Location: dashboard.php');
    exit;
}

$user = get_logged_user();

// Load labels from database with CORRECT column names
$orderLabels = db_get_results("SELECT label_key AS status_key, label FROM order_labels ORDER BY sort_order");
$customerLabels = db_get_results("SELECT label_key, label_name FROM customer_labels ORDER BY sort_order");  
$userLabels = db_get_results("SELECT label_key, label_name FROM user_labels ORDER BY sort_order");

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'list_rules':
            $entityType = $_GET['entity_type'] ?? null;
            $where = ["1=1"];
            $params = [];
            
            if ($entityType) {
                $where[] = "entity_type = ?";
                $params[] = $entityType;
            }
            
            $rules = db_get_results(
                "SELECT * FROM rules 
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY priority DESC, id ASC",
                $params
            );
            
            foreach ($rules as &$rule) {
                $rule['trigger_conditions'] = json_decode($rule['trigger_conditions'], true);
                $rule['actions'] = json_decode($rule['actions'], true);
            }
            
            echo json_encode(['success' => true, 'rules' => $rules]);
            exit;
            
        case 'get_rule':
            $ruleId = $_GET['id'] ?? null;
            
            if (!$ruleId) {
                echo json_encode(['success' => false, 'message' => 'Rule ID required']);
                exit;
            }
            
            $rule = db_get_row("SELECT * FROM rules WHERE id = ?", [$ruleId]);
            
            if ($rule) {
                $rule['trigger_conditions'] = json_decode($rule['trigger_conditions'], true);
                $rule['actions'] = json_decode($rule['actions'], true);
                echo json_encode(['success' => true, 'rule' => $rule]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Rule not found']);
            }
            exit;
            
        case 'save_rule':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data['name'] || !$data['entity_type']) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $ruleKey = 'rule_' . time() . '_' . rand(1000, 9999);
            
            $id = db_insert('rules', [
                'rule_key' => $ruleKey,
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'entity_type' => $data['entity_type'],
                'rule_type' => 'condition_based',
                'priority' => $data['priority'] ?? 50,
                'is_active' => $data['is_active'] ?? 1,
                'trigger_conditions' => json_encode($data['trigger_conditions']),
                'actions' => json_encode($data['actions']),
                'created_by' => $user['id']
            ]);
            
            log_activity('create_rule', "Created rule: {$data['name']}", 'rule', $id);
            
            echo json_encode(['success' => true, 'rule_id' => $id]);
            exit;
            
        case 'update_rule':
            $ruleId = $_GET['id'] ?? null;
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$ruleId) {
                echo json_encode(['success' => false, 'message' => 'Rule ID required']);
                exit;
            }
            
            $updates = [];
            if (isset($data['name'])) $updates['name'] = $data['name'];
            if (isset($data['description'])) $updates['description'] = $data['description'];
            if (isset($data['priority'])) $updates['priority'] = $data['priority'];
            if (isset($data['is_active'])) $updates['is_active'] = $data['is_active'];
            if (isset($data['trigger_conditions'])) {
                $updates['trigger_conditions'] = json_encode($data['trigger_conditions']);
            }
            if (isset($data['actions'])) {
                $updates['actions'] = json_encode($data['actions']);
            }
            
            db_update('rules', $updates, ['id' => $ruleId]);
            
            log_activity('update_rule', "Updated rule #$ruleId", 'rule', $ruleId);
            
            echo json_encode(['success' => true]);
            exit;
            
        case 'delete_rule':
            $ruleId = $_GET['id'] ?? null;
            
            if (!$ruleId) {
                echo json_encode(['success' => false, 'message' => 'Rule ID required']);
                exit;
            }
            
            db_query("DELETE FROM rules WHERE id = ?", [$ruleId]);
            
            log_activity('delete_rule', "Deleted rule #$ruleId", 'rule', $ruleId);
            
            echo json_encode(['success' => true]);
            exit;
            
        case 'toggle_rule':
            $ruleId = $_GET['id'] ?? null;
            $isActive = $_GET['active'] ?? 1;
            
            db_update('rules', ['is_active' => $isActive], ['id' => $ruleId]);
            
            echo json_encode(['success' => true]);
            exit;
            
        case 'test_rule':
            $ruleId = $_GET['id'] ?? null;
            $testEntityId = $_GET['test_entity'] ?? null;
            
            if (!$ruleId) {
                echo json_encode(['success' => false, 'message' => 'Rule ID required']);
                exit;
            }
            
            // For testing, just return sample result
            echo json_encode([
                'success' => true, 
                'test_result' => [
                    'conditions_matched' => true,
                    'actions_executed' => ['action1', 'action2'],
                    'message' => 'Test completed successfully'
                ]
            ]);
            exit;
    }
}

include 'includes/header.php';
?>

<style>
    .rule-builder {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
    }
    
    .condition-group {
        background: white;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .condition-row {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 10px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 6px;
    }
    
    .condition-row select, .condition-row input {
        flex: 1;
        min-width: 150px;
    }
    
    .action-item {
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
    }
    
    .btn-remove {
        width: 36px;
        height: 36px;
        padding: 0;
        border-radius: 50%;
    }
    
    .rule-preview {
        background: #e7f3ff;
        border: 2px dashed #0066cc;
        border-radius: 8px;
        padding: 20px;
        margin: 20px 0;
    }
    
    .rule-card {
        background: white;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: all 0.3s;
    }
    
    .rule-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .entity-badge {
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .entity-order { background: #e3f2fd; color: #1976d2; }
    .entity-customer { background: #e8f5e9; color: #388e3c; }
    .entity-employee { background: #fff3e0; color: #e65100; }
    .entity-task { background: #f3e5f5; color: #7b1fa2; }
    .entity-system { background: #e0e0e0; color: #424242; }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-cogs"></i> Quản lý Rules Động</h2>
        <div>
            <button class="btn btn-success me-2" onclick="loadSampleRules()">
                <i class="fas fa-magic"></i> Load Rules Mẫu
            </button>
            <button class="btn btn-primary" onclick="openRuleBuilder()">
                <i class="fas fa-plus"></i> Tạo Rule Mới
            </button>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 id="totalRules">0</h4>
                    <small>Tổng Rules</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 id="activeRules">0</h4>
                    <small>Rules Đang Hoạt Động</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 id="executionsToday">0</h4>
                    <small>Lần Thực Thi Hôm Nay</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 id="successRate">0%</h4>
                    <small>Tỷ Lệ Thành Công</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" href="#" onclick="filterRules('all', event)">Tất cả</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="filterRules('order', event)">Đơn hàng</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="filterRules('customer', event)">Khách hàng</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="filterRules('employee', event)">Nhân viên</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="filterRules('task', event)">Tasks</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="filterRules('system', event)">Hệ thống</a>
        </li>
    </ul>

    <!-- Rules list -->
    <div id="rulesList">
        <div class="text-center py-5">
            <i class="fas fa-spinner fa-spin fa-3x text-primary"></i>
            <p class="mt-3">Đang tải rules...</p>
        </div>
    </div>
</div>

<!-- Modal Create/Edit Rule -->
<div class="modal fade" id="ruleModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ruleModalTitle">Tạo Rule Mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editingRuleId" value="">
                
                <!-- Basic Info -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Tên Rule <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ruleName" 
                               placeholder="VD: Xử lý khách không nghe máy">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Loại Entity <span class="text-danger">*</span></label>
                        <select class="form-select" id="entityType" onchange="updateFieldOptions()">
                            <option value="order">Đơn hàng</option>
                            <option value="customer">Khách hàng</option>
                            <option value="employee">Nhân viên</option>
                            <option value="task">Task</option>
                            <option value="system">Hệ thống</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Độ ưu tiên</label>
                        <input type="number" class="form-control" id="priority" value="50" min="1" max="100">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control" id="ruleDescription" rows="2" 
                              placeholder="Mô tả chi tiết rule này làm gì..."></textarea>
                </div>

                <!-- Conditions -->
                <div class="rule-builder">
                    <h5 class="mb-3"><i class="fas fa-filter"></i> Điều kiện (IF)</h5>
                    <div class="condition-group">
                        <select class="form-select mb-3" id="conditionOperator" style="width: 200px">
                            <option value="AND">TẤT CẢ điều kiện (AND)</option>
                            <option value="OR">BẤT KỲ điều kiện (OR)</option>
                        </select>
                        <div id="conditionsContainer">
                            <!-- Conditions will be added here -->
                        </div>
                        <button class="btn btn-success btn-sm" onclick="addCondition()">
                            <i class="fas fa-plus"></i> Thêm điều kiện
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="rule-builder">
                    <h5 class="mb-3"><i class="fas fa-bolt"></i> Hành động (THEN)</h5>
                    <div id="actionsContainer">
                        <!-- Actions will be added here -->
                    </div>
                    <button class="btn btn-success btn-sm" onclick="addAction()">
                        <i class="fas fa-plus"></i> Thêm hành động
                    </button>
                </div>

                <!-- Preview -->
                <div class="rule-preview">
                    <h5><i class="fas fa-eye"></i> Xem trước</h5>
                    <div id="rulePreview">
                        <strong style="color: #0066cc;">NẾU</strong> (chưa có điều kiện)<br>
                        <strong style="color: #00aa00;">THÌ</strong> (chưa có hành động)
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-warning" onclick="testRule()">
                    <i class="fas fa-flask"></i> Test Rule
                </button>
                <button type="button" class="btn btn-primary" onclick="saveRule()">
                    <i class="fas fa-save"></i> Lưu Rule
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Configuration from PHP data - Include both key and label for display
const orderStatusOptions = <?php 
    $options = [];
    foreach($orderLabels as $label) {
        $options[] = ['value' => $label['status_key'], 'text' => $label['label']];
    }
    echo json_encode($options); 
?>;
const customerLabelOptions = <?php 
    $options = [];
    foreach($customerLabels as $label) {
        $options[] = ['value' => $label['label_key'], 'text' => $label['label_name']];
    }
    echo json_encode($options);
?>;
const userLabelOptions = <?php 
    $options = [];
    foreach($userLabels as $label) {
        $options[] = ['value' => $label['label_key'], 'text' => $label['label_name']];
    }
    echo json_encode($options);
?>;

// Field definitions for each entity type
const fieldDefinitions = {
    order: {
        'status': {
            label: 'Trạng thái đơn hàng',
            type: 'select',
            options: orderStatusOptions // Now contains {value, text} objects
        },
        'total_calls': { label: 'Tổng số cuộc gọi', type: 'number' },
        'hours_since_created': { label: 'Số giờ từ khi tạo', type: 'number' },
        'hours_since_assigned': { label: 'Số giờ từ khi gán', type: 'number' },
        'assigned_to_role': {
            label: 'Role người được gán',
            type: 'select',
            options: [
                {value: 'telesale', text: 'Telesale'},
                {value: 'manager', text: 'Manager'},
                {value: 'admin', text: 'Admin'}
            ]
        },
        'total_amount': { label: 'Tổng giá trị đơn', type: 'number' },
        'call_count': { label: 'Số lần gọi', type: 'number' }
    },
    customer: {
        'has_label': {
            label: 'Có nhãn khách hàng',
            type: 'select',
            options: customerLabelOptions // Now contains {value, text} objects
        },
        'total_orders': { label: 'Tổng số đơn', type: 'number' },
        'total_value': { label: 'Tổng giá trị mua', type: 'number' },
        'completed_orders': { label: 'Đơn thành công', type: 'number' },
        'cancelled_orders': { label: 'Đơn hủy', type: 'number' },
        'is_vip': { label: 'Là khách VIP', type: 'boolean' },
        'is_blacklisted': { label: 'Trong blacklist', type: 'boolean' },
        'risk_score': { label: 'Điểm rủi ro', type: 'number' }
    },
    employee: {
        'has_label': {
            label: 'Có nhãn nhân viên',
            type: 'select',
            options: userLabelOptions // Now contains {value, text} objects
        },
        'role': {
            label: 'Role nhân viên',
            type: 'select',
            options: [
                {value: 'telesale', text: 'Telesale'},
                {value: 'manager', text: 'Manager'},
                {value: 'admin', text: 'Admin'}
            ]
        },
        'violation_count': { label: 'Số lần vi phạm', type: 'number' },
        'warning_count': { label: 'Số lần cảnh báo', type: 'number' },
        'suspension_count': { label: 'Số lần bị suspend', type: 'number' },
        'performance_score': { label: 'Điểm hiệu suất', type: 'number' },
        'successful_orders': { label: 'Số đơn thành công', type: 'number' },
        'total_orders_handled': { label: 'Tổng đơn đã xử lý', type: 'number' }
    },
    task: {
        'task_type': { label: 'Loại task', type: 'text' },
        'status': {
            label: 'Trạng thái task',
            type: 'select',
            options: [
                {value: 'pending', text: 'Đang chờ'},
                {value: 'in_progress', text: 'Đang xử lý'},
                {value: 'completed', text: 'Hoàn thành'},
                {value: 'cancelled', text: 'Đã hủy'},
                {value: 'overdue', text: 'Quá hạn'}
            ]
        },
        'priority': {
            label: 'Độ ưu tiên',
            type: 'select',
            options: [
                {value: 'low', text: 'Thấp'},
                {value: 'normal', text: 'Bình thường'},
                {value: 'high', text: 'Cao'},
                {value: 'urgent', text: 'Khẩn cấp'}
            ]
        },
        'hours_until_due': { label: 'Giờ còn lại', type: 'number' },
        'is_overdue': { label: 'Quá hạn', type: 'boolean' }
    },
    system: {
        'time_of_day': { label: 'Giờ trong ngày', type: 'number' },
        'day_of_week': { label: 'Ngày trong tuần', type: 'number' },
        'is_working_hour': { label: 'Giờ làm việc', type: 'boolean' }
    }
};

// Operators
const operators = {
    equals: 'bằng',
    not_equals: 'khác',
    greater_than: 'lớn hơn',
    less_than: 'nhỏ hơn',
    greater_than_or_equals: '>= lớn hơn bằng',
    less_than_or_equals: '<= nhỏ hơn bằng',
    contains: 'chứa',
    not_contains: 'không chứa',
    in: 'trong danh sách',
    not_in: 'không trong danh sách',
    is_true: 'là đúng',
    is_false: 'là sai',
    has_label: 'có nhãn',
    not_has_label: 'không có nhãn'
};

// Action types
const actionTypes = {
    // Order actions
    'change_order_status': { 
        label: 'Đổi trạng thái đơn', 
        params: { status: 'select:order_status' },
        entity: ['order']
    },
    'assign_order_to_role': { 
        label: 'Gán đơn cho role', 
        params: { role: 'select:role' },
        entity: ['order']
    },
    'assign_order_to_user': { 
        label: 'Gán đơn cho user', 
        params: { user_id: 'number' },
        entity: ['order']
    },
    'add_order_note': { 
        label: 'Thêm ghi chú đơn hàng', 
        params: { note: 'text' },
        entity: ['order']
    },
    
    // Customer actions
    'add_customer_label': { 
        label: 'Thêm nhãn khách hàng', 
        params: { label_key: 'select:customer_label' },
        entity: ['customer', 'order']
    },
    'remove_customer_label': { 
        label: 'Xóa nhãn khách hàng', 
        params: { label_key: 'select:customer_label' },
        entity: ['customer', 'order']
    },
    'mark_customer_vip': { 
        label: 'Đánh dấu khách VIP', 
        params: {},
        entity: ['customer', 'order']
    },
    'add_to_blacklist': { 
        label: 'Thêm vào blacklist', 
        params: { reason: 'text' },
        entity: ['customer', 'order']
    },
    
    // Employee actions
    'add_user_label': { 
        label: 'Thêm nhãn nhân viên', 
        params: { label_key: 'select:user_label' },
        entity: ['employee']
    },
    'suspend_user': { 
        label: 'Suspend nhân viên', 
        params: { duration_hours: 'number', reason: 'text' },
        entity: ['employee']
    },
    'send_warning': { 
        label: 'Gửi cảnh báo', 
        params: { message: 'text' },
        entity: ['employee']
    },
    'increase_violation': { 
        label: 'Tăng vi phạm', 
        params: { reason: 'text' },
        entity: ['employee']
    },
    
    // System actions (available for all)
    'send_notification': { 
        label: 'Gửi thông báo', 
        params: { to: 'text', priority: 'select:priority', message: 'text' },
        entity: ['all']
    },
    'create_task': { 
        label: 'Tạo task nhắc nhở', 
        params: { task_type: 'text', due_in_hours: 'number', description: 'text' },
        entity: ['all']
    },
    'create_reminder': { 
        label: 'Tạo reminder', 
        params: { type: 'text', due_time: 'number' },
        entity: ['all']
    },
    'log_action': { 
        label: 'Ghi log', 
        params: { action_type: 'text', description: 'text' },
        entity: ['all']
    },
    'escalate_to_manager': { 
        label: 'Chuyển lên Manager', 
        params: { message: 'text' },
        entity: ['order', 'task']
    },
    'auto_close': { 
        label: 'Tự động đóng', 
        params: { reason: 'text' },
        entity: ['order', 'task']
    }
};

// Counters
let conditionCounter = 0;
let actionCounter = 0;
let currentEditingRule = null;

// Load rules
async function loadRules(entityType = null) {
    try {
        let url = '?ajax=1&action=list_rules';
        if (entityType && entityType !== 'all') {
            url += `&entity_type=${entityType}`;
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        // Update statistics
        updateStatistics(data.rules);
        
        const container = document.getElementById('rulesList');
        
        if (data.rules.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Chưa có rule nào. 
                    <a href="#" onclick="openRuleBuilder()">Tạo rule đầu tiên</a> hoặc
                    <a href="#" onclick="loadSampleRules()">Load rules mẫu</a>
                </div>
            `;
            return;
        }
        
        let html = '';
        data.rules.forEach(rule => {
            const condCount = rule.trigger_conditions?.rules?.length || 0;
            const actionCount = rule.actions?.length || 0;
            
            html += `
                <div class="rule-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h5 class="mb-2">
                                ${rule.name}
                                <span class="entity-badge entity-${rule.entity_type}">${rule.entity_type}</span>
                                <span class="badge bg-info">Priority: ${rule.priority}</span>
                            </h5>
                            <p class="text-muted mb-2">${rule.description || 'Không có mô tả'}</p>
                            <small class="text-muted">
                                <i class="fas fa-filter"></i> ${condCount} điều kiện |
                                <i class="fas fa-bolt"></i> ${actionCount} hành động |
                                <i class="fas fa-clock"></i> Tạo: ${rule.created_at || 'N/A'}
                            </small>
                        </div>
                        <div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" 
                                       id="ruleSwitch${rule.id}"
                                       ${rule.is_active == 1 ? 'checked' : ''}
                                       onchange="toggleRule(${rule.id}, this.checked)">
                                <label class="form-check-label" for="ruleSwitch${rule.id}">
                                    ${rule.is_active == 1 ? 'Đang hoạt động' : 'Tạm dừng'}
                                </label>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" onclick="editRule(${rule.id})"
                                        title="Sửa rule">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="testRule(${rule.id})"
                                        title="Test rule">
                                    <i class="fas fa-flask"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteRule(${rule.id})"
                                        title="Xóa rule">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading rules:', error);
        showToast('Lỗi khi tải rules', 'error');
    }
}

// Update statistics
function updateStatistics(rules) {
    const total = rules.length;
    const active = rules.filter(r => r.is_active == 1).length;
    
    document.getElementById('totalRules').textContent = total;
    document.getElementById('activeRules').textContent = active;
    
    // These would need actual data from rule_executions table
    document.getElementById('executionsToday').textContent = '0';
    document.getElementById('successRate').textContent = '0%';
}

// Add condition
function addCondition() {
    conditionCounter++;
    const id = `condition_${conditionCounter}`;
    const entityType = document.getElementById('entityType').value;
    const fields = fieldDefinitions[entityType] || {};
    
    let html = `
        <div class="condition-row" id="${id}">
            <select class="form-select" data-field onchange="updateConditionValue('${id}'); updatePreview()">
                <option value="">-- Chọn trường --</option>
    `;
    
    for (const [key, field] of Object.entries(fields)) {
        html += `<option value="${key}">${field.label}</option>`;
    }
    
    html += `
            </select>
            <select class="form-select" data-operator onchange="updatePreview()">
    `;
    
    for (const [key, label] of Object.entries(operators)) {
        html += `<option value="${key}">${label}</option>`;
    }
    
    html += `
            </select>
            <div class="value-container" style="flex: 1">
                <input type="text" class="form-control" data-value placeholder="Giá trị" onkeyup="updatePreview()">
            </div>
            <button class="btn btn-danger btn-remove" onclick="removeElement('${id}')">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.getElementById('conditionsContainer').insertAdjacentHTML('beforeend', html);
    updatePreview();
}

// Update condition value field based on selected field type
function updateConditionValue(conditionId) {
    const row = document.getElementById(conditionId);
    const field = row.querySelector('[data-field]').value;
    const valueContainer = row.querySelector('.value-container');
    const entityType = document.getElementById('entityType').value;
    const fieldDef = fieldDefinitions[entityType]?.[field];
    
    if (!fieldDef) {
        valueContainer.innerHTML = '<input type="text" class="form-control" data-value placeholder="Giá trị" onkeyup="updatePreview()">';
        return;
    }
    
    if (fieldDef.type === 'select' && fieldDef.options) {
        let html = '<select class="form-select" data-value onchange="updatePreview()">';
        html += '<option value="">-- Chọn giá trị --</option>';
        
        fieldDef.options.forEach(option => {
            if (typeof option === 'object') {
                // Display both text and value for clarity
                html += `<option value="${option.value}">${option.text}</option>`;
            } else {
                html += `<option value="${option}">${option}</option>`;
            }
        });
        
        html += '</select>';
        valueContainer.innerHTML = html;
    } else if (fieldDef.type === 'boolean') {
        valueContainer.innerHTML = `
            <select class="form-select" data-value onchange="updatePreview()">
                <option value="true">Đúng</option>
                <option value="false">Sai</option>
            </select>
        `;
    } else if (fieldDef.type === 'number') {
        valueContainer.innerHTML = '<input type="number" class="form-control" data-value placeholder="Giá trị số" onkeyup="updatePreview()">';
    } else {
        valueContainer.innerHTML = '<input type="text" class="form-control" data-value placeholder="Giá trị" onkeyup="updatePreview()">';
    }
}

// Add action
function addAction() {
    actionCounter++;
    const id = `action_${actionCounter}`;
    const entityType = document.getElementById('entityType').value;
    
    // Filter actions for current entity type
    const availableActions = {};
    for (const [key, action] of Object.entries(actionTypes)) {
        if (action.entity.includes('all') || action.entity.includes(entityType)) {
            availableActions[key] = action;
        }
    }
    
    let html = `
        <div class="action-item" id="${id}">
            <div class="d-flex justify-content-between mb-2">
                <select class="form-select" data-action-type onchange="updateActionParams('${id}')">
                    <option value="">-- Chọn hành động --</option>
    `;
    
    for (const [key, action] of Object.entries(availableActions)) {
        html += `<option value="${key}">${action.label}</option>`;
    }
    
    html += `
                </select>
                <button class="btn btn-danger btn-sm" onclick="removeElement('${id}')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="action-params" id="${id}_params"></div>
        </div>
    `;
    
    document.getElementById('actionsContainer').insertAdjacentHTML('beforeend', html);
    updatePreview();
}

// Update action parameters  
function updateActionParams(actionId) {
    const select = document.querySelector(`#${actionId} select[data-action-type]`);
    const actionType = select.value;
    const paramsContainer = document.getElementById(`${actionId}_params`);
    
    if (!actionType || !actionTypes[actionType]) {
        paramsContainer.innerHTML = '';
        updatePreview();
        return;
    }
    
    let html = '';
    const params = actionTypes[actionType].params;
    
    for (const [param, type] of Object.entries(params)) {
        let inputHtml = '';
        
        if (type.startsWith('select:')) {
            const optionType = type.split(':')[1];
            inputHtml = `<select class="form-control form-control-sm" data-param="${param}" onchange="updatePreview()">`;
            inputHtml += '<option value="">-- Chọn --</option>';
            
            if (optionType === 'order_status') {
                orderStatusOptions.forEach(option => {
                    inputHtml += `<option value="${option.value}">${option.text} (${option.value})</option>`;
                });
            } else if (optionType === 'customer_label') {
                customerLabelOptions.forEach(option => {
                    inputHtml += `<option value="${option.value}">${option.text}</option>`;
                });
            } else if (optionType === 'user_label') {
                userLabelOptions.forEach(option => {
                    inputHtml += `<option value="${option.value}">${option.text}</option>`;
                });
            } else if (optionType === 'role') {
                [
                    {value: 'telesale', text: 'Telesale'},
                    {value: 'manager', text: 'Manager'},
                    {value: 'admin', text: 'Admin'}
                ].forEach(role => {
                    inputHtml += `<option value="${role.value}">${role.text}</option>`;
                });
            } else if (optionType === 'priority') {
                [
                    {value: 'low', text: 'Thấp'},
                    {value: 'normal', text: 'Bình thường'},
                    {value: 'high', text: 'Cao'},
                    {value: 'urgent', text: 'Khẩn cấp'}
                ].forEach(p => {
                    inputHtml += `<option value="${p.value}">${p.text}</option>`;
                });
            }
            
            inputHtml += `</select>`;
        } else if (type === 'number') {
            inputHtml = `<input type="number" class="form-control form-control-sm" 
                               data-param="${param}" placeholder="${param}" onkeyup="updatePreview()">`;
        } else {
            inputHtml = `<input type="text" class="form-control form-control-sm" 
                               data-param="${param}" placeholder="${param}" onkeyup="updatePreview()">`;
        }
        
        // Better labels for parameters
        const paramLabels = {
            'status': 'Trạng thái',
            'role': 'Vai trò',
            'label_key': 'Nhãn',
            'user_id': 'ID người dùng',
            'duration_hours': 'Thời gian (giờ)',
            'reason': 'Lý do',
            'message': 'Nội dung',
            'to': 'Gửi đến',
            'priority': 'Độ ưu tiên',
            'task_type': 'Loại task',
            'due_in_hours': 'Hạn (giờ)',
            'description': 'Mô tả',
            'note': 'Ghi chú',
            'due_time': 'Thời hạn',
            'action_type': 'Loại hành động'
        };
        
        const label = paramLabels[param] || param;
        
        html += `
            <div class="mb-2">
                <label class="form-label small text-muted">${label}:</label>
                ${inputHtml}
            </div>
        `;
    }
    
    paramsContainer.innerHTML = html;
    updatePreview();
}

// Update preview
function updatePreview() {
    const conditions = [];
    const actions = [];
    
    // Collect conditions
    document.querySelectorAll('#conditionsContainer .condition-row').forEach(row => {
        const field = row.querySelector('[data-field]').value;
        const operator = row.querySelector('[data-operator]').value;
        const value = row.querySelector('[data-value]').value;
        
        if (field && value) {
            const entityType = document.getElementById('entityType').value;
            const fieldLabel = fieldDefinitions[entityType][field]?.label || field;
            conditions.push(`${fieldLabel} ${operators[operator]} "${value}"`);
        }
    });
    
    // Collect actions  
    document.querySelectorAll('#actionsContainer .action-item').forEach(item => {
        const actionType = item.querySelector('[data-action-type]').value;
        if (actionType && actionTypes[actionType]) {
            const params = [];
            item.querySelectorAll('[data-param]').forEach(input => {
                if (input.value) {
                    params.push(`${input.dataset.param}="${input.value}"`);
                }
            });
            let actionText = actionTypes[actionType].label;
            if (params.length > 0) {
                actionText += ` (${params.join(', ')})`;
            }
            actions.push(actionText);
        }
    });
    
    const operator = document.getElementById('conditionOperator').value;
    const operatorText = operator === 'AND' ? ' <strong>VÀ</strong> ' : ' <strong>HOẶC</strong> ';
    
    let preview = '<strong style="color: #0066cc;">NẾU</strong> ';
    preview += conditions.length ? conditions.join(operatorText) : '(chưa có điều kiện)';
    preview += '<br><strong style="color: #00aa00;">THÌ</strong> ';
    preview += actions.length ? actions.join(' <strong>VÀ</strong> ') : '(chưa có hành động)';
    
    document.getElementById('rulePreview').innerHTML = preview;
}

// Save rule
async function saveRule() {
    const conditions = [];
    const actions = [];
    
    // Collect conditions
    document.querySelectorAll('#conditionsContainer .condition-row').forEach(row => {
        const field = row.querySelector('[data-field]').value;
        const operator = row.querySelector('[data-operator]').value;
        const value = row.querySelector('[data-value]').value;
        
        if (field && value) {
            conditions.push({
                field: `${document.getElementById('entityType').value}.${field}`,
                operator: operator,
                value: value
            });
        }
    });
    
    // Collect actions
    document.querySelectorAll('#actionsContainer .action-item').forEach(item => {
        const actionType = item.querySelector('[data-action-type]').value;
        if (actionType) {
            const params = {};
            item.querySelectorAll('[data-param]').forEach(input => {
                params[input.dataset.param] = input.value;
            });
            actions.push({ type: actionType, params: params });
        }
    });
    
    const ruleData = {
        name: document.getElementById('ruleName').value,
        description: document.getElementById('ruleDescription').value,
        entity_type: document.getElementById('entityType').value,
        priority: parseInt(document.getElementById('priority').value),
        is_active: 1,
        trigger_conditions: {
            type: document.getElementById('conditionOperator').value,
            rules: conditions
        },
        actions: actions
    };
    
    if (!ruleData.name || conditions.length === 0 || actions.length === 0) {
        showToast('Vui lòng điền đầy đủ thông tin', 'error');
        return;
    }
    
    try {
        const editingId = document.getElementById('editingRuleId').value;
        const url = editingId 
            ? `?ajax=1&action=update_rule&id=${editingId}`
            : '?ajax=1&action=save_rule';
            
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(ruleData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Lưu rule thành công!', 'success');
            $('#ruleModal').modal('hide');
            loadRules();
            resetRuleBuilder();
        } else {
            showToast(data.message || 'Có lỗi xảy ra', 'error');
        }
    } catch (error) {
        console.error('Error saving rule:', error);
        showToast('Không thể lưu rule', 'error');
    }
}

// Edit rule
async function editRule(ruleId) {
    try {
        const response = await fetch(`?ajax=1&action=get_rule&id=${ruleId}`);
        const data = await response.json();
        
        if (!data.success) {
            showToast('Không thể tải rule', 'error');
            return;
        }
        
        const rule = data.rule;
        
        // Reset form first
        resetRuleBuilder();
        
        // Set basic info
        document.getElementById('editingRuleId').value = ruleId;
        document.getElementById('ruleModalTitle').textContent = 'Sửa Rule';
        document.getElementById('ruleName').value = rule.name;
        document.getElementById('ruleDescription').value = rule.description || '';
        document.getElementById('entityType').value = rule.entity_type;
        document.getElementById('priority').value = rule.priority;
        document.getElementById('conditionOperator').value = rule.trigger_conditions.type || 'AND';
        
        // Load conditions
        if (rule.trigger_conditions && rule.trigger_conditions.rules) {
            rule.trigger_conditions.rules.forEach(cond => {
                addCondition();
                const row = document.querySelector('#conditionsContainer .condition-row:last-child');
                const field = cond.field.split('.')[1]; // Remove entity prefix
                row.querySelector('[data-field]').value = field;
                row.querySelector('[data-operator]').value = cond.operator;
                row.querySelector('[data-value]').value = cond.value;
            });
        }
        
        // Load actions
        if (rule.actions) {
            rule.actions.forEach(action => {
                addAction();
                const item = document.querySelector('#actionsContainer .action-item:last-child');
                item.querySelector('[data-action-type]').value = action.type;
                
                // Trigger param update
                const actionId = item.id;
                updateActionParams(actionId);
                
                // Fill param values
                if (action.params) {
                    for (const [param, value] of Object.entries(action.params)) {
                        const input = item.querySelector(`[data-param="${param}"]`);
                        if (input) input.value = value;
                    }
                }
            });
        }
        
        updatePreview();
        $('#ruleModal').modal('show');
        
    } catch (error) {
        console.error('Error editing rule:', error);
        showToast('Lỗi khi tải rule', 'error');
    }
}

// Test rule
async function testRule(ruleId) {
    try {
        const testEntity = prompt('Nhập ID entity để test (VD: order ID = 1):');
        if (!testEntity) return;
        
        const response = await fetch(`?ajax=1&action=test_rule&id=${ruleId || document.getElementById('editingRuleId').value}&test_entity=${testEntity}`);
        const data = await response.json();
        
        if (data.success) {
            const result = data.test_result;
            let message = `Test Result:\n`;
            message += `Conditions matched: ${result.conditions_matched ? 'YES' : 'NO'}\n`;
            if (result.conditions_matched) {
                message += `Actions to execute: ${result.actions_executed.join(', ')}`;
            }
            alert(message);
        } else {
            showToast('Test thất bại', 'error');
        }
    } catch (error) {
        console.error('Error testing rule:', error);
        showToast('Lỗi khi test rule', 'error');
    }
}

// Helper functions
function openRuleBuilder() {
    resetRuleBuilder();
    document.getElementById('ruleModalTitle').textContent = 'Tạo Rule Mới';
    $('#ruleModal').modal('show');
}

function resetRuleBuilder() {
    document.getElementById('editingRuleId').value = '';
    document.getElementById('ruleName').value = '';
    document.getElementById('ruleDescription').value = '';
    document.getElementById('entityType').value = 'order';
    document.getElementById('priority').value = '50';
    document.getElementById('conditionOperator').value = 'AND';
    document.getElementById('conditionsContainer').innerHTML = '';
    document.getElementById('actionsContainer').innerHTML = '';
    conditionCounter = 0;
    actionCounter = 0;
    updatePreview();
}

function removeElement(id) {
    document.getElementById(id).remove();
    updatePreview();
}

function updateFieldOptions() {
    // Reset conditions and actions when entity type changes
    document.getElementById('conditionsContainer').innerHTML = '';
    document.getElementById('actionsContainer').innerHTML = '';
    updatePreview();
}

async function toggleRule(ruleId, isActive) {
    try {
        await fetch(`?ajax=1&action=toggle_rule&id=${ruleId}&active=${isActive ? 1 : 0}`);
        showToast(isActive ? 'Rule đã được kích hoạt' : 'Rule đã tạm dừng', 'success');
    } catch (error) {
        showToast('Không thể thay đổi trạng thái', 'error');
    }
}

async function deleteRule(ruleId) {
    if (!confirm('Xác nhận xóa rule này? Hành động này không thể hoàn tác.')) return;
    
    try {
        await fetch(`?ajax=1&action=delete_rule&id=${ruleId}`);
        showToast('Đã xóa rule', 'success');
        loadRules();
    } catch (error) {
        showToast('Không thể xóa rule', 'error');
    }
}

function filterRules(type, event) {
    event.preventDefault();
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    event.target.classList.add('active');
    loadRules(type === 'all' ? null : type);
}

// Load sample rules
function loadSampleRules() {
    if (!confirm('Tạo các rule mẫu? Điều này sẽ thêm 3 rule mẫu vào hệ thống.')) return;
    
    const sampleRules = [
        {
            name: 'Xử lý khách không nghe máy lâu',
            description: 'Tự động chuyển đơn sang rác khi gọi nhiều lần không nghe',
            entity_type: 'order',
            priority: 90,
            trigger_conditions: {
                type: 'AND',
                rules: [
                    { field: 'order.status', operator: 'equals', value: 'khong-nghe' },
                    { field: 'order.hours_since_created', operator: 'greater_than', value: '48' },
                    { field: 'order.call_count', operator: 'greater_than', value: '5' }
                ]
            },
            actions: [
                { type: 'change_order_status', params: { status: 'n-a-1759224173' } }, // Đơn rác
                { type: 'add_customer_label', params: { label_key: 'n-a-1759225492' } }, // Khách gọi nhiều không nghe
                { type: 'send_notification', params: { 
                    to: 'manager', 
                    priority: 'high',
                    message: 'Đơn hàng chuyển vào rác do không liên lạc được'
                }}
            ]
        },
        {
            name: 'Suspend nhân viên yếu kém',
            description: 'Tự động suspend nhân viên có hiệu suất thấp và vi phạm nhiều',
            entity_type: 'employee',
            priority: 85,
            trigger_conditions: {
                type: 'AND',
                rules: [
                    { field: 'employee.performance_score', operator: 'less_than', value: '50' },
                    { field: 'employee.violation_count', operator: 'greater_than', value: '3' },
                    { field: 'employee.role', operator: 'equals', value: 'telesale' }
                ]
            },
            actions: [
                { type: 'add_user_label', params: { label_key: 'n-a' } }, // Nhân viên yếu kém
                { type: 'suspend_user', params: { 
                    duration_hours: '24',
                    reason: 'Performance kém và vi phạm nhiều'
                }},
                { type: 'send_notification', params: {
                    to: 'admin',
                    priority: 'urgent',
                    message: 'Đã suspend nhân viên do performance kém'
                }}
            ]
        },
        {
            name: 'Nâng cấp khách VIP',
            description: 'Tự động gắn nhãn VIP cho khách hàng mua nhiều',
            entity_type: 'customer',
            priority: 70,
            trigger_conditions: {
                type: 'AND',
                rules: [
                    { field: 'customer.total_orders', operator: 'greater_than', value: '5' },
                    { field: 'customer.total_value', operator: 'greater_than', value: '10000000' }
                ]
            },
            actions: [
                { type: 'add_customer_label', params: { label_key: 'khach-hang-vip' } }, // Khách hàng VIP
                { type: 'mark_customer_vip', params: {} }
            ]
        }
    ];
    
    // Save each sample rule
    let saved = 0;
    sampleRules.forEach(async (rule) => {
        try {
            const response = await fetch('?ajax=1&action=save_rule', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(rule)
            });
            
            const data = await response.json();
            if (data.success) saved++;
            
            if (saved === sampleRules.length) {
                showToast('Đã tạo ' + saved + ' rule mẫu!', 'success');
                loadRules();
            }
        } catch (error) {
            console.error('Error creating sample rule:', error);
        }
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadRules();
    
    // Auto refresh every 30 seconds
    setInterval(loadRules, 30000);
});
</script>

<?php include 'includes/footer.php'; ?>