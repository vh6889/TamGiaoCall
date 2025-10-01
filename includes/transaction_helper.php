<?php
/**
 * Transaction Helper - Prevents nested transaction bugs
 * Always use these functions instead of direct PDO calls
 */

if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

// Global transaction state
$GLOBALS['_transaction_active'] = false;
$GLOBALS['_transaction_pdo'] = null;

/**
 * Begin transaction safely - prevents nesting
 */
function begin_transaction() {
    if ($GLOBALS['_transaction_active']) {
        throw new Exception('Transaction already active. Nested transactions not allowed.');
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
    if (!$GLOBALS['_transaction_active']) {
        throw new Exception('No active transaction to commit');
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
 */
function execute_in_transaction($callback) {
    if (is_transaction_active()) {
        // If already in transaction, just execute
        return $callback();
    }
    
    begin_transaction();
    
    try {
        $result = $callback();
        commit_transaction();
        return $result;
    } catch (Exception $e) {
        rollback_transaction();
        throw $e;
    }
}