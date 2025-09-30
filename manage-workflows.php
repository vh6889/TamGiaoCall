<?php
/**
 * Trang Quản lý Quy tắc Tự động hóa (Workflows) - PHIÊN BẢN HOÀN CHỈNH
 * Cho phép Admin Thêm, Sửa, Xóa và Cấu hình chi tiết các quy tắc.
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

require_admin();

$page_title = 'Quản Lý Quy Tắc Tự Động Hóa';

// ==================================================================
// === ĐỊNH NGHĨA CÁC "VIÊN GẠCH LEGO" CHO HỆ THỐNG QUY TẮC ===
// ==================================================================

// --- 1. Danh sách các SỰ KIỆN KÍCH HOẠT (Triggers) ---
$trigger_events = [
    'order_created' => 'Khi đơn hàng mới được tạo',
    'order_status_changed' => 'Khi trạng thái đơn hàng thay đổi',
    'customer_purchase_completed' => 'Khi khách hàng hoàn thành một đơn hàng'
];

// --- 2. Danh sách các ĐIỀU KIỆN (Condition Facts) ---
$conditions_facts = [
    'order_status' => ['label' => 'Trạng thái đơn hàng', 'type' => 'select_status'],
    'order_total' => ['label' => 'Tổng giá trị đơn', 'type' => 'number'],
    'customer_label' => ['label' => 'Nhãn khách hàng', 'type' => 'select_customer_label'],
    'user_label' => ['label' => 'Nhãn nhân viên', 'type' => 'select_user_label']
];

// --- 3. Danh sách các HÀNH ĐỘNG (Actions) ---
$actions_list = [
    'change_order_status' => ['label' => 'Đổi trạng thái đơn hàng', 'params' => ['status_key' => 'select_status']],
    'add_customer_label' => ['label' => 'Gán nhãn cho Khách hàng', 'params' => ['label_key' => 'select_customer_label']],
    'add_user_label' => ['label' => 'Gán nhãn cho Nhân viên', 'params' => ['label_key' => 'select_user_label']],
    'suspend_user' => ['label' => 'Treo tài khoản Nhân viên', 'params' => []],
    'create_reminder' => ['label' => 'Tạo nhắc nhở', 'params' => ['note' => 'text', 'due_hours' => 'number']]
];

// --- 4. Lấy dữ liệu cho các dropdown động ---
$all_order_statuses = db_get_results("SELECT status_key, label FROM order_status_configs ORDER BY sort_order ASC");
$all_customer_labels = db_get_results("SELECT label_key, label_name FROM customer_labels ORDER BY label_name ASC");
$all_user_labels = db_get_results("SELECT label_key, label_name FROM user_labels ORDER BY label_name ASC");


// ==================================================================
// === XỬ LÝ CÁC YÊU CẦU TỪ CLIENT (FORM, AJAX, DELETE) ===
// ==================================================================

// --- Xử lý form THÊM / SỬA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $trigger_event = sanitize($_POST['trigger_event'] ?? '');
    $status = sanitize($_POST['status'] ?? 'inactive');
    
    // Xử lý conditions và actions từ form
    $conditions = [];
    if (isset($_POST['conditions']) && is_array($_POST['conditions'])) {
        foreach ($_POST['conditions'] as $c) {
            if (!empty($c['fact']) && !empty($c['operator']) && isset($c['value'])) {
                $conditions[] = [
                    'fact' => sanitize($c['fact']),
                    'operator' => sanitize($c['operator']),
                    'value' => sanitize($c['value'])
                ];
            }
        }
    }

    $actions = [];
    if (isset($_POST['actions']) && is_array($_POST['actions'])) {
        foreach ($_POST['actions'] as $a) {
            if (!empty($a['action'])) {
                $actions[] = [
                    'action' => sanitize($a['action']),
                    'params' => sanitize($a['params'] ?? [])
                ];
            }
        }
    }

    $workflow_data = [
        'name' => $name,
        'description' => $description,
        'trigger_event' => $trigger_event,
        'status' => $status,
        'conditions_json' => json_encode($conditions),
        'actions_json' => json_encode($actions)
    ];

    if ($_POST['form_action'] === 'add') {
        db_insert('workflows', $workflow_data);
        set_flash('success', 'Đã tạo quy tắc mới thành công!');
    } elseif ($_POST['form_action'] === 'edit') {
        $workflow_id = (int)$_POST['workflow_id'];
        db_update('workflows', $workflow_data, 'id = ?', [$workflow_id]);
        set_flash('success', 'Đã cập nhật quy tắc thành công!');
    }
    redirect('manage-workflows.php');
}

// --- Xử lý yêu cầu XÓA ---
if (isset($_GET['delete'])) {
    $workflow_id = (int)$_GET['delete'];
    db_delete('workflows', 'id = ?', [$workflow_id]);
    set_flash('success', 'Đã xóa quy tắc!');
    redirect('manage-workflows.php');
}

// --- Xử lý yêu cầu AJAX để lấy thông tin chi tiết của workflow ---
if (isset($_GET['get_workflow'])) {
    $workflow_id = (int)$_GET['get_workflow'];
    $workflow = db_get_row("SELECT * FROM workflows WHERE id = ?", [$workflow_id]);
    if ($workflow) {
        // Decode JSON để gửi về cho client
        $workflow['conditions_json'] = json_decode($workflow['conditions_json'], true);
        $workflow['actions_json'] = json_decode($workflow['actions_json'], true);
        json_response($workflow);
    }
    json_error('Không tìm thấy quy tắc.', 404);
}


// Lấy tất cả các quy tắc đã tạo từ CSDL để hiển thị
$workflows = db_get_results("SELECT * FROM workflows ORDER BY sort_order ASC, name ASC");

include 'includes/header.php';
?>

<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><i class="fas fa-cogs me-2"></i>Quản lý Quy tắc Tự động hóa</h5>
        <button class="btn btn-primary" onclick="prepareAddModal()">
            <i class="fas fa-plus me-2"></i> Thêm Quy tắc mới
        </button>
    </div>

    <?php display_flash(); ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Tên Quy tắc</th>
                    <th>Kích hoạt khi</th>
                    <th class="text-center">Trạng thái</th>
                    <th class="text-end" style="width: 150px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($workflows)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Chưa có quy tắc nào được tạo.</td></tr>
                <?php else: ?>
                    <?php foreach ($workflows as $workflow): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($workflow['name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($workflow['description']); ?></small>
                        </td>
                        <td><code><?php echo htmlspecialchars($trigger_events[$workflow['trigger_event']] ?? $workflow['trigger_event']); ?></code></td>
                        <td class="text-center">
                            <?php if ($workflow['status'] === 'active'): ?>
                                <span class="badge bg-success">Hoạt động</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Không hoạt động</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-primary" title="Sửa" onclick="prepareEditModal(<?php echo $workflow['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete=<?php echo $workflow['id']; ?>" class="btn btn-sm btn-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa quy tắc này?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="workflowModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="workflowForm" method="POST" action="manage-workflows.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="form_action" id="form_action">
                    <input type="hidden" name="workflow_id" id="workflow_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Tên Quy tắc</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                             <label for="trigger_event" class="form-label">Kích hoạt quy tắc KHI</label>
                            <select class="form-select" id="trigger_event" name="trigger_event" required>
                                <option value="">-- Chọn một sự kiện --</option>
                                <?php foreach ($trigger_events as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="status" name="status" value="active" checked>
                            <label class="form-check-label" for="status">Hoạt động</label>
                        </div>
                    </div>
                    <hr>

                    <div class="p-3 border rounded mb-3">
                         <h6 class="mb-3"><i class="fas fa-question-circle me-2"></i>ĐIỀU KIỆN (NẾU TẤT CẢ ĐỀU ĐÚNG)</h6>
                         <div id="conditions-container"></div>
                         <button type="button" class="btn btn-sm btn-outline-secondary" id="add-condition-btn">
                             <i class="fas fa-plus me-1"></i> Thêm điều kiện
                         </button>
                    </div>

                     <div class="p-3 border rounded">
                         <h6 class="mb-3"><i class="fas fa-play-circle me-2"></i>HÀNH ĐỘNG (THÌ LÀM NHỮNG VIỆC SAU)</h6>
                         <div id="actions-container"></div>
                         <button type="button" class="btn btn-sm btn-outline-secondary" id="add-action-btn">
                             <i class="fas fa-plus me-1"></i> Thêm hành động
                         </button>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu Quy tắc</button>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="condition-row-template">
    <div class="row align-items-center mb-2 condition-row">
        <div class="col-md-4">
            <select class="form-select form-select-sm fact-select" name="conditions[][fact]"></select>
        </div>
        <div class="col-md-3">
            <select class="form-select form-select-sm operator-select" name="conditions[][operator]"></select>
        </div>
        <div class="col-md-4">
            <div class="value-input-group"></div>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-danger remove-row-btn"><i class="fas fa-times"></i></button>
        </div>
    </div>
</template>

<template id="action-row-template">
     <div class="row align-items-center mb-2 action-row">
        <div class="col-md-4">
            <select class="form-select form-select-sm action-select" name="actions[][action]"></select>
        </div>
        <div class="col-md-7">
            <div class="params-container"></div>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-danger remove-row-btn"><i class="fas fa-times"></i></button>
        </div>
    </div>
</template>


<script>
// ==================================================================
// === CHUYỂN DỮ LIỆU TỪ PHP SANG JAVASCRIPT ===
// ==================================================================
const conditionsFacts = <?php echo json_encode($conditions_facts); ?>;
const actionsList = <?php echo json_encode($actions_list); ?>;
const allData = {
    order_statuses: <?php echo json_encode($all_order_statuses); ?>,
    customer_labels: <?php echo json_encode($all_customer_labels); ?>,
    user_labels: <?php echo json_encode($all_user_labels); ?>
};


// ==================================================================
// === LOGIC JAVASCRIPT CHO GIAO DIỆN ĐỘNG ===
// ==================================================================
document.addEventListener('DOMContentLoaded', function() {
    const workflowModal = new bootstrap.Modal(document.getElementById('workflowModal'));
    const modalTitle = document.getElementById('modalTitle');
    const workflowForm = document.getElementById('workflowForm');
    const conditionsContainer = document.getElementById('conditions-container');
    const actionsContainer = document.getElementById('actions-container');

    // --- Các hàm tạo giao diện động ---

    // Tạo input cho phần "Giá trị" của Điều kiện
    function createValueInput(factKey, value = '') {
        const fact = conditionsFacts[factKey];
        if (!fact) return '';
        
        let inputHtml = '';
        const inputName = 'conditions[][value]';

        switch (fact.type) {
            case 'select_status':
                inputHtml = `<select class="form-select form-select-sm" name="${inputName}"><option value="">-- Chọn trạng thái --</option>`;
                allData.order_statuses.forEach(s => inputHtml += `<option value="${s.status_key}" ${value == s.status_key ? 'selected' : ''}>${s.label}</option>`);
                inputHtml += `</select>`;
                break;
            case 'select_customer_label':
                 inputHtml = `<select class="form-select form-select-sm" name="${inputName}"><option value="">-- Chọn nhãn --</option>`;
                allData.customer_labels.forEach(l => inputHtml += `<option value="${l.label_key}" ${value == l.label_key ? 'selected' : ''}>${l.label_name}</option>`);
                inputHtml += `</select>`;
                break;
            case 'select_user_label':
                 inputHtml = `<select class="form-select form-select-sm" name="${inputName}"><option value="">-- Chọn nhãn --</option>`;
                allData.user_labels.forEach(l => inputHtml += `<option value="${l.label_key}" ${value == l.label_key ? 'selected' : ''}>${l.label_name}</option>`);
                inputHtml += `</select>`;
                break;
            case 'number':
                inputHtml = `<input type="number" class="form-control form-control-sm" name="${inputName}" value="${value}">`;
                break;
            default: // text
                inputHtml = `<input type="text" class="form-control form-control-sm" name="${inputName}" value="${value}">`;
        }
        return inputHtml;
    }

    // Tạo input cho các "Tham số" của Hành động
    function createParamsInputs(actionKey, params = {}) {
        const action = actionsList[actionKey];
        if (!action || Object.keys(action.params).length === 0) return '';
        
        let html = '<div class="row gx-2">';
        for (const [paramKey, paramType] of Object.entries(action.params)) {
             html += '<div class="col">';
            const inputName = `actions[][params][${paramKey}]`;
            const value = params[paramKey] || '';

            switch (paramType) {
                 case 'select_status':
                    html += `<select class="form-select form-select-sm" name="${inputName}"><option value="">-- Chọn trạng thái --</option>`;
                    allData.order_statuses.forEach(s => html += `<option value="${s.status_key}" ${value == s.status_key ? 'selected' : ''}>${s.label}</option>`);
                    html += `</select>`;
                    break;
                case 'select_customer_label':
                    html += `<select class="form-select form-select-sm" name="${inputName}"><option value="">-- Chọn nhãn --</option>`;
                    allData.customer_labels.forEach(l => html += `<option value="${l.label_key}" ${value == l.label_key ? 'selected' : ''}>${l.label_name}</option>`);
                    html += `</select>`;
                    break;
                case 'select_user_label':
                    html += `<select class="form-select form-select-sm" name="${inputName}"><option value="">-- Chọn nhãn --</option>`;
                    allData.user_labels.forEach(l => html += `<option value="${l.label_key}" ${value == l.label_key ? 'selected' : ''}>${l.label_name}</option>`);
                    html += `</select>`;
                    break;
                case 'number':
                    html += `<input type="number" class="form-control form-control-sm" name="${inputName}" value="${value}" placeholder="${paramKey}">`;
                    break;
                default:
                    html += `<input type="text" class="form-control form-control-sm" name="${inputName}" value="${value}" placeholder="${paramKey}">`;
            }
             html += '</div>';
        }
        html += '</div>';
        return html;
    }


    // Thêm một dòng điều kiện mới
    function addConditionRow(data = {}) {
        const template = document.getElementById('condition-row-template').content.cloneNode(true);
        const row = template.querySelector('.condition-row');
        
        const factSelect = row.querySelector('.fact-select');
        factSelect.innerHTML = '<option value="">-- Chọn thuộc tính --</option>';
        for (const key in conditionsFacts) {
            factSelect.innerHTML += `<option value="${key}" ${data.fact == key ? 'selected' : ''}>${conditionsFacts[key].label}</option>`;
        }

        const operatorSelect = row.querySelector('.operator-select');
        // Tạm thời dùng toán tử chung, có thể mở rộng sau
        const operators = {'equals': 'Bằng', 'not_equals': 'Không bằng', 'greater_than': 'Lớn hơn', 'less_than': 'Nhỏ hơn'};
        operatorSelect.innerHTML = '';
        for(const opKey in operators) {
            operatorSelect.innerHTML += `<option value="${opKey}" ${data.operator == opKey ? 'selected' : ''}>${operators[opKey]}</option>`;
        }
        
        if(data.fact) {
            row.querySelector('.value-input-group').innerHTML = createValueInput(data.fact, data.value);
        }

        factSelect.addEventListener('change', e => {
            row.querySelector('.value-input-group').innerHTML = createValueInput(e.target.value);
        });

        conditionsContainer.appendChild(row);
    }
    
    // Thêm một dòng hành động mới
    function addActionRow(data = {}) {
        const template = document.getElementById('action-row-template').content.cloneNode(true);
        const row = template.querySelector('.action-row');
        
        const actionSelect = row.querySelector('.action-select');
        actionSelect.innerHTML = '<option value="">-- Chọn hành động --</option>';
        for (const key in actionsList) {
            actionSelect.innerHTML += `<option value="${key}" ${data.action == key ? 'selected' : ''}>${actionsList[key].label}</option>`;
        }

        if(data.action) {
            row.querySelector('.params-container').innerHTML = createParamsInputs(data.action, data.params);
        }

        actionSelect.addEventListener('change', e => {
            row.querySelector('.params-container').innerHTML = createParamsInputs(e.target.value);
        });

        actionsContainer.appendChild(row);
    }

    // --- Xử lý sự kiện click ---
    document.getElementById('add-condition-btn').addEventListener('click', () => addConditionRow());
    document.getElementById('add-action-btn').addEventListener('click', () => addActionRow());

    // Xóa một dòng (điều kiện hoặc hành động)
    workflowForm.addEventListener('click', function(e) {
        if (e.target.closest('.remove-row-btn')) {
            e.target.closest('.row').remove();
        }
    });

    // --- Các hàm quản lý Modal ---
    window.prepareAddModal = function() {
        workflowForm.reset();
        modalTitle.innerText = 'Tạo Quy tắc mới';
        workflowForm.querySelector('#form_action').value = 'add';
        workflowForm.querySelector('#workflow_id').value = '';
        workflowForm.querySelector('#status').checked = true;
        conditionsContainer.innerHTML = '';
        actionsContainer.innerHTML = '';
        workflowModal.show();
    }

    window.prepareEditModal = async function(workflowId) {
        workflowForm.reset();
        modalTitle.innerText = 'Đang tải...';
        conditionsContainer.innerHTML = '';
        actionsContainer.innerHTML = '';
        workflowModal.show();

        try {
            const response = await fetch(`?get_workflow=${workflowId}`);
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();

            modalTitle.innerText = 'Sửa Quy tắc: ' + data.name;
            workflowForm.querySelector('#form_action').value = 'edit';
            workflowForm.querySelector('#workflow_id').value = data.id;
            workflowForm.querySelector('#name').value = data.name;
            workflowForm.querySelector('#description').value = data.description;
            workflowForm.querySelector('#trigger_event').value = data.trigger_event;
            workflowForm.querySelector('#status').checked = (data.status === 'active');

            data.conditions_json.forEach(c => addConditionRow(c));
            data.actions_json.forEach(a => addActionRow(a));

        } catch (error) {
            console.error('Fetch error:', error);
            showToast('Không thể tải dữ liệu quy tắc.', 'error');
            workflowModal.hide();
        }
    }
});
</script>


<?php include 'includes/footer.php'; ?>