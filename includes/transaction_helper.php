<?php
/**
 * Transaction Helper - FIXED VERSION
 * Xử lý transaction an toàn, tránh lỗi nested
 */

if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

// Global transaction state  
$GLOBALS['_transaction_active'] = false;
$GLOBALS['_transaction_pdo'] = null;

/**
 * Begin transaction safely - auto handle nested calls
 */
function begin_transaction() {
    // Nếu đã có transaction, return luôn (không throw exception)
    if ($GLOBALS['_transaction_active']) {
        return $GLOBALS['_transaction_pdo'];
    }
    
    $pdo = get_db_connection();
    $pdo->beginTransaction();
    
    $GLOBALS['_transaction_active'] = true;
    $GLOBALS['_transaction_pdo'] = $pdo;
    
    return $pdo;
}

/**
 * Commit transaction safely
 */
function commit_transaction() {
    // Không có transaction thì bỏ qua
    if (!$GLOBALS['_transaction_active']) {
        return;
    }
    
    $GLOBALS['_transaction_pdo']->commit();
    $GLOBALS['_transaction_active'] = false;
    $GLOBALS['_transaction_pdo'] = null;
}

/**
 * Rollback transaction safely
 */
function rollback_transaction() {
    if (!$GLOBALS['_transaction_active']) {
        return; // No transaction to rollback
    }
    
    try {
        $GLOBALS['_transaction_pdo']->rollBack();
    } catch (Exception $e) {
        error_log('[ROLLBACK_ERROR] ' . $e->getMessage());
    }
    
    $GLOBALS['_transaction_active'] = false;
    $GLOBALS['_transaction_pdo'] = null;
}

/**
 * Check if transaction is active
 */
function is_transaction_active() {
    return $GLOBALS['_transaction_active'];
}

/**
 * Execute code in transaction with automatic rollback on error
 * FIXED: Không conflict với security_helper.php
 */
function safe_execute_in_transaction($callback) {
    $was_active = is_transaction_active();
    
    if (!$was_active) {
        begin_transaction();
    }
    
    try {
        $result = $callback();
        
        if (!$was_active) {
            commit_transaction();
        }
        
        return $result;
    } catch (Exception $e) {
        if (!$was_active) {
            rollback_transaction();
        }
        throw $e;
    }
}

// Để tương thích ngược, giữ tên cũ nhưng gọi hàm mới
function execute_in_transaction($callback) {
    return safe_execute_in_transaction($callback);
}
?>