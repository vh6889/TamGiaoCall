<?php
/**
 * Security Helper Functions
 * Version: 2.1 - More flexible with Content-Type
 */

if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Enhanced CSRF verification
 */
function require_csrf() {
    $token = null;
    $content_type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    
    // Check if JSON request
    if (strpos($content_type, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['csrf_token'] ?? null;
    } else {
        // Check POST first, then GET
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
        
        // If still no token and has raw input, try to parse as JSON
        if (!$token) {
            $raw_input = file_get_contents('php://input');
            if ($raw_input) {
                $input = json_decode($raw_input, true);
                if ($input && isset($input['csrf_token'])) {
                    $token = $input['csrf_token'];
                }
            }
        }
    }
    
    // Origin check (optional, more relaxed)
    $origin_valid = true;
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $allowed = parse_url(SITE_URL, PHP_URL_SCHEME) . '://' . parse_url(SITE_URL, PHP_URL_HOST);
        $origin_valid = (strpos($_SERVER['HTTP_ORIGIN'], $allowed) === 0);
    }
    
    if (!$token || !verify_csrf_token($token) || !$origin_valid) {
        http_response_code(403);
        if (is_ajax_request()) {
            json_error('Security validation failed', 403);
        } else {
            die('Security validation failed');
        }
    }
}

/**
 * Check if AJAX request
 */
function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Get JSON input - More flexible version
 * Accepts both application/json and regular POST with JSON body
 */
function get_json_input($required_fields = array()) {
    $content_type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    $input = null;
    
    // Try to get JSON from raw input
    $raw_input = file_get_contents('php://input');
    if ($raw_input) {
        $input = json_decode($raw_input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If not valid JSON, check if it's form-encoded
            if (strpos($content_type, 'application/x-www-form-urlencoded') !== false) {
                parse_str($raw_input, $input);
            } else {
                json_error('Invalid JSON: ' . json_last_error_msg(), 400);
            }
        }
    }
    
    // If still no input, check $_POST
    if (!$input && !empty($_POST)) {
        $input = $_POST;
    }
    
    if (!$input) {
        json_error('No input data received', 400);
    }
    
    // Validate required fields
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            json_error("Missing required field: $field", 400);
        }
    }
    
    return $input;
}

/**
 * Validate status transition
 */
function validate_status_transition($current_status, $new_status) {
    // Get all statuses
    $valid_statuses = db_get_col("SELECT label_key FROM order_labels");
    
    if (!in_array($new_status, $valid_statuses)) {
        return false;
    }
    
    // Get locked statuses (success or failed)
    $locked_statuses = db_get_col(
        "SELECT label_key FROM order_labels WHERE core_status IN ('success', 'failed')"
    );
    
    // Cannot change from locked status
    if (in_array($current_status, $locked_statuses)) {
        return false;
    }
    
    return true;
}

/**
 * Validate products JSON
 */
function validate_products_json($products) {
    if (!is_array($products)) {
        return array('valid' => false, 'error' => 'Products must be an array');
    }
    
    foreach ($products as $index => $product) {
        // Only name is truly required
        if (!isset($product['name']) || empty($product['name'])) {
            return array('valid' => false, 'error' => "Product at index $index missing name");
        }
        
        // Quantity defaults to 1 if not set
        if (isset($product['qty']) && $product['qty'] <= 0) {
            return array('valid' => false, 'error' => "Product at index $index has invalid quantity");
        }
        
        // Price can be 0 (free product)
        if (isset($product['price']) && $product['price'] < 0) {
            return array('valid' => false, 'error' => "Product at index $index has negative price");
        }
    }
    
    return array('valid' => true);
}

/**
 * Database-based rate limiting
 */
function check_rate_limit($action, $user_id, $limit = 60, $window = 60) {
    $key = "rate_limit_{$action}_{$user_id}";
    $cutoff = date('Y-m-d H:i:s', time() - $window);
    
    // Cleanup old entries (1% chance)
    if (rand(1, 100) === 1) {
        db_query("DELETE FROM rate_limits WHERE created_at < ?", array($cutoff));
    }
    
    $attempts = db_get_var(
        "SELECT COUNT(*) FROM rate_limits WHERE rate_key = ? AND created_at > ?",
        array($key, $cutoff)
    );
    
    if ($attempts >= $limit) {
        json_error('Rate limit exceeded. Please wait.', 429);
    }
    
    try {
        db_insert('rate_limits', array(
            'rate_key' => $key,
            'user_id' => $user_id,
            'action' => $action,
            'created_at' => date('Y-m-d H:i:s')
        ));
    } catch (Exception $e) {
        error_log('[RATE_LIMIT] ' . $e->getMessage());
    }
}

/**
 * Validate ID
 */
function validate_id($id, $field_name = 'ID') {
    $id = (int)$id;
    if ($id <= 0) {
        json_error("Invalid $field_name", 400);
    }
    return $id;
}

/**
 * Validate required string
 */
function validate_required_string($value, $field_name, $min = 1, $max = 255) {
    $value = trim(sanitize($value));
    $len = mb_strlen($value);
    
    if ($len < $min) {
        json_error("$field_name is required", 400);
    }
    if ($len > $max) {
        json_error("$field_name is too long (max $max)", 400);
    }
    
    return $value;
}

/**
 * Validate phone
 */
function validate_phone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (strlen($phone) < 10 || strlen($phone) > 15) {
        json_error('Invalid phone number', 400);
    }
    return $phone;
}

/**
 * Validate email
 */
function validate_email_input($email) {
    if (empty($email)) return null;
    
    $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
    if ($email === false) {
        json_error('Invalid email format', 400);
    }
    return $email;
}

/**
 * Execute with retry (for deadlock handling)
 */
function execute_with_retry($callback, $max_retries = 3) {
    $attempt = 0;
    
    while ($attempt < $max_retries) {
        try {
            // Call execute_in_transaction from transaction_helper.php
            return execute_in_transaction($callback);
        } catch (Exception $e) {
            $attempt++;
            
            if (stripos($e->getMessage(), 'deadlock') !== false && $attempt < $max_retries) {
                usleep(100000 * $attempt); // Wait longer each retry
                continue;
            }
            
            throw $e;
        }
    }
    
    throw new Exception("Failed after $max_retries retries");
}

/**
 * Sanitize array recursively
 */
function sanitize_array($array) {
    $result = array();
    foreach ($array as $key => $value) {
        $clean_key = sanitize($key);
        if (is_array($value)) {
            $result[$clean_key] = sanitize_array($value);
        } else {
            $result[$clean_key] = sanitize($value);
        }
    }
    return $result;
}

/**
 * Helper: Can receive handover
 * Check if manager can receive handover order
 */
function can_receive_handover($order_id) {
    $order = get_order($order_id);
    if (!$order) return false;
    
    $user = get_logged_user();
    if (!$user) return false;
    
    // Admin can always receive
    if ($user['role'] === 'admin') return true;
    
    // Manager can receive if order was from their team
    if ($user['role'] === 'manager' && $order['assigned_to']) {
        $team_ids = db_get_col(
            "SELECT telesale_id FROM manager_assignments WHERE manager_id = ?",
            [$user['id']]
        );
        
        return in_array($order['assigned_to'], $team_ids);
    }
    
    return false;
}