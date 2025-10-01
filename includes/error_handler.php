<?php
/**
 * Improved Error Handler - Never expose internal errors
 */

if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Log error with full context
 */
function log_error($message, $context = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'user_id' => $_SESSION['user_id'] ?? null,
        'ip' => get_client_ip(),
        'url' => $_SERVER['REQUEST_URI'] ?? 'CLI'
    ];
    
    error_log('[APP_ERROR] ' . json_encode($logEntry));
}

/**
 * Handle API errors safely
 */
function handle_api_error($e, $userMessage = 'Có lỗi xảy ra, vui lòng thử lại') {
    // Log full error details
    log_error($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    // Return generic message to user
    json_error($userMessage, 500);
}

/**
 * Validate and sanitize input with detailed error messages
 */
function validate_input($input, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $input[$field] ?? null;
        
        // Required check
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = ($rule['label'] ?? $field) . ' là bắt buộc';
            continue;
        }
        
        if (empty($value)) continue;
        
        // Type check
        if (isset($rule['type'])) {
            switch ($rule['type']) {
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field] = 'Email không hợp lệ';
                    }
                    break;
                case 'phone':
                    if (!preg_match('/^[0-9+]{10,15}$/', $value)) {
                        $errors[$field] = 'Số điện thoại không hợp lệ';
                    }
                    break;
                case 'int':
                    if (!is_numeric($value) || (int)$value != $value) {
                        $errors[$field] = 'Phải là số nguyên';
                    }
                    break;
            }
        }
        
        // Length check
        if (isset($rule['min_length']) && mb_strlen($value) < $rule['min_length']) {
            $errors[$field] = 'Tối thiểu ' . $rule['min_length'] . ' ký tự';
        }
        
        if (isset($rule['max_length']) && mb_strlen($value) > $rule['max_length']) {
            $errors[$field] = 'Tối đa ' . $rule['max_length'] . ' ký tự';
        }
    }
    
    return $errors;
}