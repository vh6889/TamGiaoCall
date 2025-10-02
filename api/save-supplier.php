<?php
// ============================================
// api/save-supplier.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'save-supplier.php') {
    define('TSM_ACCESS', true);
    require_once '../system/config.php';
    require_once '../includes/transaction_helper.php';
    require_once '../includes/error_handler.php';
    require_once '../system/functions.php';
    require_once '../includes/security_helper.php';
	require_once '../includes/product_helper.php';
    
    header('Content-Type: application/json');
    
    require_csrf();
    require_admin();
    
    check_rate_limit('save-supplier', get_logged_user()['id'], 10, 60);
    
    try {
        $supplier_code = validate_required_string($_POST['supplier_code'] ?? '', 'Supplier code', 1, 50);
        $name = validate_required_string($_POST['name'] ?? '', 'Supplier name', 1, 255);
        
        // Check unique supplier code
        $exists = db_get_var("SELECT id FROM suppliers WHERE supplier_code = ?", [$supplier_code]);
        if ($exists) {
            throw new Exception('Mã nhà cung cấp đã tồn tại');
        }
        
        $supplier_data = [
            'supplier_code' => $supplier_code,
            'name' => $name,
            'contact_name' => sanitize($_POST['contact_name'] ?? ''),
            'phone' => validate_phone($_POST['phone'] ?? ''),
            'email' => validate_email_input($_POST['email'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'notes' => sanitize($_POST['notes'] ?? ''),
            'is_active' => 1
        ];
        
        // Handle API integration
        if (isset($_POST['sync_enabled']) && $_POST['sync_enabled']) {
            $supplier_data['sync_enabled'] = 1;
            $supplier_data['api_endpoint'] = filter_var($_POST['api_endpoint'] ?? '', FILTER_VALIDATE_URL) ?: null;
            
            // Encrypt API credentials before saving
            if (!empty($_POST['api_key'])) {
                $supplier_data['api_key'] = base64_encode($_POST['api_key']);
            }
            if (!empty($_POST['api_secret'])) {
                $supplier_data['api_secret'] = base64_encode($_POST['api_secret']);
            }
        }
        
        $supplier_id = db_insert('suppliers', $supplier_data);
        
        log_activity('create_supplier', "Created supplier: $name", 'supplier', $supplier_id);
        
        json_success('Đã tạo nhà cung cấp', ['supplier_id' => $supplier_id]);
        
    } catch (Exception $e) {
        handle_api_error($e, 'Không thể tạo nhà cung cấp');
    }
}