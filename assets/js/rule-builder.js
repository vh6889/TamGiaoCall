/**
 * Rule Builder JavaScript
 * File: assets/js/rule-builder.js
 */

// Global variables
let currentRule = null;
let availableFields = {};
let availableActions = {};

// Field definitions for different entity types
const entityFields = {
    order: {
        'order.status': { label: 'Trạng thái đơn hàng', type: 'select', options: 'statuses' },
        'order.total_amount': { label: 'Tổng giá trị', type: 'number' },
        'order.customer_phone': { label: 'SĐT khách hàng', type: 'text' },
        'order.assigned_to': { label: 'Nhân viên phụ trách', type: 'select', options: 'users' },
        'order.call_attempts': { label: 'Số lần gọi', type: 'number' },
        'order.created_at': { label: 'Ngày tạo', type: 'datetime' },
        'order.source': { label: 'Nguồn đơn', type: 'text' }
    },
    customer: {
        'customer_metrics.total_orders': { label: 'Tổng số đơn', type: 'number' },
        'customer_metrics.completed_orders': { label: 'Đơn thành công', type: 'number' },
        'customer_metrics.cancelled_orders': { label: 'Đơn hủy', type: 'number' },
        'customer_metrics.total_value': { label: 'Tổng giá trị mua', type: 'number' },
        'customer_metrics.is_vip': { label: 'Là VIP', type: 'boolean' },
        'customer_metrics.is_blacklisted': { label: 'Trong blacklist', type: 'boolean' },
        'customer_metrics.risk_score': { label: 'Điểm rủi ro', type: 'number' }
    },
    employee: {
        'employee_performance.total_orders_handled': { label: 'Tổng đơn xử lý', type: 'number' },
        'employee_performance.successful_orders': { label: 'Đơn thành công', type: 'number' },
        'employee_performance.avg_handling_time': { label: 'Thời gian xử lý TB (phút)', type: 'number' },
        'employee_performance.violation_count': { label: 'Số lần vi phạm', type: 'number' },
        'employee_performance.performance_score': { label: 'Điểm hiệu suất', type: 'number' }
    },
    task: {
        'task.status': { label: 'Trạng thái task', type: 'select', options: 'task_statuses' },
        'task.priority': { label: 'Độ ưu tiên', type: 'select', options: 'priorities' },
        'task.time_until_due': { label: 'Thời gian còn lại (phút)', type: 'number' },
        'task.is_overdue': { label: 'Đã quá hạn', type: 'boolean' }
    },
    system: {
        'system.current_time': { label: 'Thời gian hiện tại', type: 'datetime' },
        'system.day_of_week': { label: 'Ngày trong tuần', type: 'number' },
        'system.hour_of_day': { label: 'Giờ trong ngày', type: 'number' }
    }
};

// Action definitions
const actionTypes = {
    change_status: {
        label: 'Đổi trạng thái',
        params: {
            entity: { label: 'Đối tượng', type: 'select', options: ['order', 'task'] },
            new_status: { label: 'Trạng thái mới', type: 'select', options: 'statuses' },
            reason: { label: 'Lý do', type: 'text', required: false }
        }
    },
    create_task: {
        label: 'Tạo nhiệm vụ',
        params: {
            task_type: { label: 'Loại nhiệm vụ', type: 'select', options: ['callback', 'follow_up', 'review'] },
            title: { label: 'Tiêu đề', type: 'text' },
            description: { label: 'Mô tả', type: 'textarea', required: false },
            assign_to: { label: 'Giao cho', type: 'select', options: 'users', required: false },
            due_in_hours: { label: 'Thời hạn (giờ)', type: 'number', default: 24 },
            reminder_before_minutes: { label: 'Nhắc trước (phút)', type: 'number', default: 30 },
            priority: { label: 'Độ ưu tiên', type: 'select', options: 'priorities', default: 'normal' }
        }
    },
    send_notification: {
        label: 'Gửi thông báo',
        params: {
            to: { label: 'Gửi tới', type: 'select', options: 'users_or_role' },
            title: { label: 'Tiêu đề', type: 'text' },
            message: { label: 'Nội dung', type: 'textarea' },
            priority: { label: 'Độ ưu tiên', type: 'select', options: 'priorities', default: 'normal' },
            action_url: { label: 'Link hành động', type: 'text', required: false }
        }
    },
    add_label: {
        label: 'Thêm nhãn',
        params: {
            entity: { label: 'Đối tượng', type: 'select', options: ['customer', 'employee'] },
            label: { label: 'Nhãn', type: 'select', options: 'labels' }
        }
    },
    remove_label: {
        label: 'Xóa nhãn',
        params: {
            entity: { label: 'Đối tượng', type: 'select', options: ['customer', 'employee'] },
            label: { label: 'Nhãn', type: 'select', options: 'labels' }
        }
    },
    suspend_user: {
        label: 'Khóa tài khoản',
        params: {
            user_id: { label: 'Nhân viên', type: 'select', options: 'users', required: false },
            duration_hours: { label: 'Thời gian (giờ)', type: 'number', default: 24 },
            reason: { label: 'Lý do', type: 'text' }
        }
    },
    create_note: {
        label: 'Tạo ghi chú',
        params: {
            content: { label: 'Nội dung', type: 'textarea' },
            note_type: { label: 'Loại ghi chú', type: 'select', options: ['system', 'manual'], default: 'system' }
        }
    },
    schedule_job: {
        label: 'Lên lịch công việc',
        params: {
            job_type: { label: 'Loại công việc', type: 'text' },
            scheduled_after_hours: { label: 'Sau (giờ)', type: 'number', default: 1 }
        }
    },
    delay_action: {
        label: 'Trì hoãn hành động',
        params: {
            delay_minutes: { label: 'Trì hoãn (phút)', type: 'number', default: 30 }
        }
    }
};

// Operators for different field types
const operators = {
    text: ['equals', 'not_equals', 'contains', 'not_contains', 'is_empty', 'is_not_empty'],
    number: ['equals', 'not_equals', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'],
    boolean: ['equals', 'not_equals'],
    select: ['equals', 'not_equals', 'in', 'not_in'],
    datetime: ['equals', 'greater_than', 'less_than', 'between']
};

const operatorLabels = {
    equals: 'Bằng',
    not_equals: 'Không bằng',
    greater_than: 'Lớn hơn',
    less_than: 'Nhỏ hơn',
    greater_than_or_equal: 'Lớn hơn hoặc bằng',
    less_than_or_equal: 'Nhỏ hơn hoặc bằng',
    contains: 'Chứa',
    not_contains: 'Không chứa',
    in: 'Trong danh sách',
    not_in: 'Không trong danh sách',
    is_empty: 'Trống',
    is_not_empty: 'Không trống',
    between: 'Trong khoảng'
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeRuleBuilder();
});

function initializeRuleBuilder() {
    // Initialize Bootstrap modal
    window.ruleModal = new bootstrap.Modal(document.getElementById('ruleModal'));
    
    // Form submit handler
    document.getElementById('ruleForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveRule();
    });
}

function showCreateRuleModal() {
    currentRule = null;
    document.getElementById('formAction').value = 'create_rule';
    document.getElementById('ruleModalTitle').textContent = 'Tạo Quy Tắc Mới';
    document.getElementById('ruleForm').reset();
    document.getElementById('ruleKey').disabled = false;
    
    // Initialize empty builders
    initializeConditionsBuilder();
    initializeActionsBuilder();
    
    ruleModal.show();
}

function editRule(ruleId) {
    fetch(`admin-rules.php?ajax=get_rule&id=${ruleId}`)
        .then(response => response.json())
        .then(rule => {
            currentRule = rule;
            document.getElementById('formAction').value = 'update_rule';
            document.getElementById('ruleModalTitle').textContent = 'Sửa Quy Tắc';
            document.getElementById('ruleId').value = rule.id;
            document.getElementById('ruleKey').value = rule.rule_key;
            document.getElementById('ruleKey').disabled = true;
            document.getElementById('ruleName').value = rule.name;
            document.getElementById('ruleDescription').value = rule.description || '';
            document.getElementById('entityType').value = rule.entity_type;
            document.getElementById('ruleType').value = rule.rule_type;
            document.getElementById('priority').value = rule.priority;
            document.getElementById('isActive').checked = rule.is_active == 1;
            
            // Load conditions and actions
            updateAvailableFields();
            loadConditions(rule.trigger_conditions);
            loadActions(rule.actions);
            
            ruleModal.show();
        });
}

function updateAvailableFields() {
    const entityType = document.getElementById('entityType').value;
    availableFields = entityFields[entityType] || {};
    
    // Update conditions builder
    updateConditionFields();
}

function initializeConditionsBuilder() {
    const builder = document.getElementById('conditionsBuilder');
    builder.innerHTML = `
        <div class="condition-group-container" id="mainConditionGroup">
            <div class="d-flex justify-content-between mb-2">
                <select class="form-select form-select-sm w-auto" id="conditionLogic">
                    <option value="AND">TẤT CẢ điều kiện đúng (AND)</option>
                    <option value="OR">BẤT KỲ điều kiện đúng (OR)</option>
                </select>
                <button type="button" class="btn btn-sm btn-primary" onclick="addCondition()">
                    <i class="fas fa-plus"></i> Thêm điều kiện
                </button>
            </div>
            <div id="conditionsList"></div>
        </div>
    `;
}

function initializeActionsBuilder() {
    const builder = document.getElementById('actionsBuilder');
    builder.innerHTML = `
        <div class="actions-container">
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-sm btn-primary" onclick="addAction()">
                    <i class="fas fa-plus"></i> Thêm hành động
                </button>
            </div>
            <div id="actionsList"></div>
        </div>
    `;
}

function addCondition(condition = null) {
    const conditionsList = document.getElementById('conditionsList');
    const conditionId = 'condition_' + Date.now();
    const entityType = document.getElementById('entityType').value;
    
    if (!entityType) {
        alert('Vui lòng chọn loại đối tượng trước!');
        return;
    }
    
    const conditionHtml = `
        <div class="condition-item border rounded p-2 mb-2" id="${conditionId}">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <select class="form-select form-select-sm condition-field" onchange="updateOperators('${conditionId}')">
                        <option value="">-- Chọn trường --</option>
                        ${Object.entries(availableFields).map(([key, field]) => 
                            `<option value="${key}" ${condition && condition.field === key ? 'selected' : ''}>
                                ${field.label}
                            </option>`
                        ).join('')}
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm condition-operator">
                        <option value="">-- Chọn điều kiện --</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="condition-value-container"></div>
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeCondition('${conditionId}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    conditionsList.insertAdjacentHTML('beforeend', conditionHtml);
    
    if (condition) {
        const conditionEl = document.getElementById(conditionId);
        conditionEl.querySelector('.condition-field').value = condition.field;
        updateOperators(conditionId);
        conditionEl.querySelector('.condition-operator').value = condition.operator;
        updateValueInput(conditionId);
        
        // Set value
        const valueInput = conditionEl.querySelector('.condition-value-input');
        if (valueInput) {
            valueInput.value = condition.value;
        }
    }
}

function updateOperators(conditionId) {
    const condition = document.getElementById(conditionId);
    const field = condition.querySelector('.condition-field').value;
    const operatorSelect = condition.querySelector('.condition-operator');
    
    if (!field || !availableFields[field]) {
        operatorSelect.innerHTML = '<option value="">-- Chọn điều kiện --</option>';
        return;
    }
    
    const fieldType = availableFields[field].type;
    const availableOperators = operators[fieldType] || operators.text;
    
    operatorSelect.innerHTML = '<option value="">-- Chọn điều kiện --</option>' +
        availableOperators.map(op => 
            `<option value="${op}">${operatorLabels[op]}</option>`
        ).join('');
    
    operatorSelect.addEventListener('change', () => updateValueInput(conditionId));
}

function updateValueInput(conditionId) {
    const condition = document.getElementById(conditionId);
    const field = condition.querySelector('.condition-field').value;
    const operator = condition.querySelector('.condition-operator').value;
    const valueContainer = condition.querySelector('.condition-value-container');
    
    if (!field || !operator) {
        valueContainer.innerHTML = '';
        return;
    }
    
    const fieldConfig = availableFields[field];
    
    // Don't show value input for is_empty, is_not_empty
    if (operator === 'is_empty' || operator === 'is_not_empty') {
        valueContainer.innerHTML = '<span class="text-muted">Không cần giá trị</span>';
        return;
    }
    
    let inputHtml = '';
    
    switch (fieldConfig.type) {
        case 'number':
            inputHtml = `<input type="number" class="form-control form-control-sm condition-value-input" placeholder="Nhập giá trị">`;
            break;
            
        case 'boolean':
            inputHtml = `
                <select class="form-select form-select-sm condition-value-input">
                    <option value="1">Có</option>
                    <option value="0">Không</option>
                </select>`;
            break;
            
        case 'select':
            if (fieldConfig.options === 'statuses') {
                // Load from statuses
                inputHtml = `
                    <select class="form-select form-select-sm condition-value-input">
                        <option value="">-- Chọn --</option>
                        <!-- Will be loaded dynamically -->
                    </select>`;
            } else {
                inputHtml = `<input type="text" class="form-control form-control-sm condition-value-input" placeholder="Nhập giá trị">`;
            }
            break;
            
        case 'datetime':
            if (operator === 'between') {
                inputHtml = `
                    <div class="d-flex gap-1">
                        <input type="datetime-local" class="form-control form-control-sm condition-value-from">
                        <span class="px-1">-</span>
                        <input type="datetime-local" class="form-control form-control-sm condition-value-to">
                    </div>`;
            } else {
                inputHtml = `<input type="datetime-local" class="form-control form-control-sm condition-value-input">`;
            }
            break;
            
        default:
            inputHtml = `<input type="text" class="form-control form-control-sm condition-value-input" placeholder="Nhập giá trị">`;
    }
    
    valueContainer.innerHTML = inputHtml;
}

function removeCondition(conditionId) {
    document.getElementById(conditionId).remove();
}

function addAction(action = null) {
    const actionsList = document.getElementById('actionsList');
    const actionId = 'action_' + Date.now();
    
    const actionHtml = `
        <div class="action-item mb-2" id="${actionId}">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <select class="form-select form-select-sm action-type" onchange="updateActionParams('${actionId}')">
                    <option value="">-- Chọn hành động --</option>
                    ${Object.entries(actionTypes).map(([key, config]) => 
                        `<option value="${key}" ${action && action.type === key ? 'selected' : ''}>
                            ${config.label}
                        </option>`
                    ).join('')}
                </select>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeAction('${actionId}')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="action-params-container"></div>
        </div>
    `;
    
    actionsList.insertAdjacentHTML('beforeend', actionHtml);
    
    if (action) {
        updateActionParams(actionId);
        // Set param values
        setTimeout(() => {
            const actionEl = document.getElementById(actionId);
            if (action.params) {
                Object.entries(action.params).forEach(([key, value]) => {
                    const input = actionEl.querySelector(`[data-param="${key}"]`);
                    if (input) {
                        input.value = value;
                    }
                });
            }
            if (action.delay_minutes) {
                const delayInput = actionEl.querySelector('[data-param="delay_minutes"]');
                if (delayInput) {
                    delayInput.value = action.delay_minutes;
                }
            }
        }, 100);
    }
}

function updateActionParams(actionId) {
    const actionEl = document.getElementById(actionId);
    const actionType = actionEl.querySelector('.action-type').value;
    const paramsContainer = actionEl.querySelector('.action-params-container');
    
    if (!actionType) {
        paramsContainer.innerHTML = '';
        return;
    }
    
    const actionConfig = actionTypes[actionType];
    if (!actionConfig || !actionConfig.params) {
        paramsContainer.innerHTML = '';
        return;
    }
    
    let paramsHtml = '<div class="row">';
    
    Object.entries(actionConfig.params).forEach(([key, param]) => {
        const required = param.required !== false;
        let inputHtml = '';
        
        switch (param.type) {
            case 'select':
                inputHtml = `
                    <select class="form-control form-control-sm" data-param="${key}" ${required ? 'required' : ''}>
                        <option value="">-- Chọn --</option>
                        ${getSelectOptions(param.options)}
                    </select>`;
                break;
                
            case 'textarea':
                inputHtml = `
                    <textarea class="form-control form-control-sm" data-param="${key}" rows="2" 
                              placeholder="${param.label}" ${required ? 'required' : ''}></textarea>`;
                break;
                
            case 'number':
                inputHtml = `
                    <input type="number" class="form-control form-control-sm" data-param="${key}" 
                           placeholder="${param.label}" value="${param.default || ''}" ${required ? 'required' : ''}>`;
                break;
                
            default:
                inputHtml = `
                    <input type="text" class="form-control form-control-sm" data-param="${key}" 
                           placeholder="${param.label}" value="${param.default || ''}" ${required ? 'required' : ''}>`;
        }
        
        paramsHtml += `
            <div class="col-md-6 mb-2">
                <label class="form-label form-label-sm">
                    ${param.label} ${required ? '<span class="text-danger">*</span>' : ''}
                </label>
                ${inputHtml}
            </div>`;
    });
    
    // Add delay option
    paramsHtml += `
        <div class="col-md-6 mb-2">
            <label class="form-label form-label-sm">Trì hoãn (phút)</label>
            <input type="number" class="form-control form-control-sm" data-param="delay_minutes" 
                   min="0" placeholder="0 = Thực hiện ngay">
        </div>`;
    
    paramsHtml += '</div>';
    paramsContainer.innerHTML = paramsHtml;
}

function getSelectOptions(optionType) {
    // This would normally load from server
    // For now, return sample options
    switch (optionType) {
        case 'priorities':
            return `
                <option value="low">Thấp</option>
                <option value="normal">Bình thường</option>
                <option value="high">Cao</option>
                <option value="urgent">Khẩn cấp</option>`;
            
        case 'users':
            return `<option value="{{order.assigned_to}}">Nhân viên hiện tại</option>`;
            
        case 'users_or_role':
            return `
                <option value="{{order.assigned_to}}">Nhân viên hiện tại</option>
                <option value="admin">Tất cả Admin</option>
                <option value="manager">Tất cả Manager</option>`;
            
        default:
            if (Array.isArray(optionType)) {
                return optionType.map(opt => `<option value="${opt}">${opt}</option>`).join('');
            }
            return '';
    }
}

function removeAction(actionId) {
    document.getElementById(actionId).remove();
}

function loadConditions(conditions) {
    if (!conditions) return;
    
    const logic = conditions.type || 'AND';
    document.getElementById('conditionLogic').value = logic;
    
    if (conditions.conditions && Array.isArray(conditions.conditions)) {
        conditions.conditions.forEach(cond => {
            addCondition(cond);
        });
    }
}

function loadActions(actions) {
    if (!actions || !Array.isArray(actions)) return;
    
    actions.forEach(action => {
        addAction(action);
    });
}

function collectConditions() {
    const logic = document.getElementById('conditionLogic').value;
    const conditionItems = document.querySelectorAll('.condition-item');
    const conditions = [];
    
    conditionItems.forEach(item => {
        const field = item.querySelector('.condition-field').value;
        const operator = item.querySelector('.condition-operator').value;
        let value = null;
        
        if (operator !== 'is_empty' && operator !== 'is_not_empty') {
            const valueInput = item.querySelector('.condition-value-input');
            const valueFrom = item.querySelector('.condition-value-from');
            const valueTo = item.querySelector('.condition-value-to');
            
            if (valueFrom && valueTo) {
                value = { from: valueFrom.value, to: valueTo.value };
            } else if (valueInput) {
                value = valueInput.value;
            }
        }
        
        if (field && operator) {
            conditions.push({ field, operator, value });
        }
    });
    
    return {
        type: logic,
        conditions: conditions
    };
}

function collectActions() {
    const actionItems = document.querySelectorAll('.action-item');
    const actions = [];
    
    actionItems.forEach(item => {
        const type = item.querySelector('.action-type').value;
        if (!type) return;
        
        const params = {};
        const delay = item.querySelector('[data-param="delay_minutes"]');
        let delayMinutes = 0;
        
        item.querySelectorAll('[data-param]').forEach(input => {
            const paramName = input.dataset.param;
            if (paramName === 'delay_minutes') {
                delayMinutes = parseInt(input.value) || 0;
            } else {
                params[paramName] = input.value;
            }
        });
        
        const action = { type, params };
        if (delayMinutes > 0) {
            action.delay_minutes = delayMinutes;
        }
        
        actions.push(action);
    });
    
    return actions;
}

function validateRule() {
    const conditions = collectConditions();
    const actions = collectActions();
    
    let errors = [];
    
    // Validate basic info
    if (!document.getElementById('ruleKey').value) {
        errors.push('Mã quy tắc là bắt buộc');
    }
    if (!document.getElementById('ruleName').value) {
        errors.push('Tên quy tắc là bắt buộc');
    }
    if (!document.getElementById('entityType').value) {
        errors.push('Loại đối tượng là bắt buộc');
    }
    if (!document.getElementById('ruleType').value) {
        errors.push('Loại quy tắc là bắt buộc');
    }
    
    // Validate conditions
    if (conditions.conditions.length === 0) {
        errors.push('Phải có ít nhất một điều kiện');
    }
    
    // Validate actions
    if (actions.length === 0) {
        errors.push('Phải có ít nhất một hành động');
    }
    
    if (errors.length > 0) {
        alert('Lỗi validation:\n' + errors.join('\n'));
        return false;
    }
    
    alert('Quy tắc hợp lệ!');
    return true;
}

function saveRule() {
    const conditions = collectConditions();
    const actions = collectActions();
    
    // Set JSON values
    document.getElementById('triggerConditions').value = JSON.stringify(conditions);
    document.getElementById('actionsJson').value = JSON.stringify(actions);
    
    // Validate before submit
    if (!validateRule()) {
        return false;
    }
    
    // Submit form
    document.getElementById('ruleForm').submit();
}

function testRule(ruleId) {
    // Show test modal
    const testData = prompt('Nhập dữ liệu test (JSON format):\nVí dụ: {"entity_id": 123, "order": {"status": "new"}}');
    
    if (!testData) return;
    
    try {
        JSON.parse(testData);
    } catch (e) {
        alert('Dữ liệu JSON không hợp lệ!');
        return;
    }
    
    fetch('admin-rules.php?ajax=test_rule', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `rule_id=${ruleId}&test_data=${encodeURIComponent(testData)}`
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Kết quả test:\n' + JSON.stringify(result.result, null, 2));
        } else {
            alert('Lỗi: ' + result.error);
        }
    });
}

function duplicateRule(ruleId) {
    fetch(`admin-rules.php?ajax=get_rule&id=${ruleId}`)
        .then(response => response.json())
        .then(rule => {
            currentRule = null;
            document.getElementById('formAction').value = 'create_rule';
            document.getElementById('ruleModalTitle').textContent = 'Nhân bản Quy Tắc';
            document.getElementById('ruleId').value = '';
            document.getElementById('ruleKey').value = rule.rule_key + '_copy';
            document.getElementById('ruleKey').disabled = false;
            document.getElementById('ruleName').value = rule.name + ' (Copy)';
            document.getElementById('ruleDescription').value = rule.description || '';
            document.getElementById('entityType').value = rule.entity_type;
            document.getElementById('ruleType').value = rule.rule_type;
            document.getElementById('priority').value = rule.priority;
            document.getElementById('isActive').checked = false; // Set inactive by default
            
            updateAvailableFields();
            loadConditions(rule.trigger_conditions);
            loadActions(rule.actions);
            
            ruleModal.show();
        });
}

function deleteRule(ruleId) {
    if (!confirm('Bạn có chắc chắn muốn xóa quy tắc này? Hành động này không thể hoàn tác.')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete_rule">
        <input type="hidden" name="rule_id" value="${ruleId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function viewRule(ruleId) {
    fetch(`admin-rules.php?ajax=get_rule&id=${ruleId}`)
        .then(response => response.json())
        .then(rule => {
            let details = `
                <h5>${rule.name}</h5>
                <p>${rule.description || 'Không có mô tả'}</p>
                <hr>
                <h6>Điều kiện:</h6>
                <pre>${JSON.stringify(rule.trigger_conditions, null, 2)}</pre>
                <hr>
                <h6>Hành động:</h6>
                <pre>${JSON.stringify(rule.actions, null, 2)}</pre>
            `;
            
            // Create modal
            const modal = document.createElement('div');
            modal.innerHTML = `
                <div class="modal fade" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Chi tiết Quy tắc</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">${details}</div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal.querySelector('.modal'));
            bsModal.show();
            
            // Remove modal after hide
            modal.querySelector('.modal').addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
        });
}

function filterRules() {
    const entityFilter = document.getElementById('filterEntity').value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const searchFilter = document.getElementById('filterSearch').value.toLowerCase();
    
    document.querySelectorAll('.rule-card').forEach(card => {
        let show = true;
        
        if (entityFilter && card.dataset.entity !== entityFilter) {
            show = false;
        }
        
        if (statusFilter && card.dataset.status !== statusFilter) {
            show = false;
        }
        
        if (searchFilter && !card.dataset.name.includes(searchFilter)) {
            show = false;
        }
        
        card.style.display = show ? 'block' : 'none';
    });
}

function resetFilters() {
    document.getElementById('filterEntity').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterSearch').value = '';
    filterRules();
}

function loadTemplates() {
    fetch('admin-rules.php?ajax=get_templates')
        .then(response => response.json())
        .then(templates => {
            let html = '<div class="list-group">';
            templates.forEach(template => {
                html += `
                    <a href="#" class="list-group-item list-group-item-action" 
                       onclick="loadTemplate('${template.template_key}'); return false;">
                        <h6>${template.name}</h6>
                        <small>${template.description}</small>
                    </a>`;
            });
            html += '</div>';
            
            // Show in modal
            const modal = document.createElement('div');
            modal.innerHTML = `
                <div class="modal fade" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Chọn mẫu quy tắc</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">${html}</div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal.querySelector('.modal'));
            bsModal.show();
        });
}