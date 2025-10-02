<?php
/**
 * API: Save Product Category
 * Fixed version with generate_slug function included
 */
define('TSM_ACCESS', true);
require_once '../system/config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../system/functions.php';
require_once '../includes/security_helper.php';

header('Content-Type: application/json');

// Check authentication and permissions
if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

if (!is_admin()) {
    json_error('Admin only', 403);
}

// Verify CSRF token
require_csrf();

// Rate limiting
check_rate_limit('save-category', get_logged_user()['id'], 20, 60);

// Helper function: Generate slug from text
function generate_slug($text) {
    // Remove Vietnamese accents
    $vietnamese = array(
        'à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ',
        'è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ',
        'ì','í','ị','ỉ','ĩ',
        'ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
        'ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ',
        'ỳ','ý','ỵ','ỷ','ỹ',
        'đ',
        'À','Á','Ạ','Ả','Ã','Â','Ầ','Ấ','Ậ','Ẩ','Ẫ','Ă','Ằ','Ắ','Ặ','Ẳ','Ẵ',
        'È','É','Ẹ','Ẻ','Ẽ','Ê','Ề','Ế','Ệ','Ể','Ễ',
        'Ì','Í','Ị','Ỉ','Ĩ',
        'Ò','Ó','Ọ','Ỏ','Õ','Ô','Ồ','Ố','Ộ','Ổ','Ỗ','Ơ','Ờ','Ớ','Ợ','Ở','Ỡ',
        'Ù','Ú','Ụ','Ủ','Ũ','Ư','Ừ','Ứ','Ự','Ử','Ữ',
        'Ỳ','Ý','Ỵ','Ỷ','Ỹ',
        'Đ'
    );
    
    $latin = array(
        'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
        'e','e','e','e','e','e','e','e','e','e','e',
        'i','i','i','i','i',
        'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
        'u','u','u','u','u','u','u','u','u','u','u',
        'y','y','y','y','y',
        'd',
        'A','A','A','A','A','A','A','A','A','A','A','A','A','A','A','A','A',
        'E','E','E','E','E','E','E','E','E','E','E',
        'I','I','I','I','I',
        'O','O','O','O','O','O','O','O','O','O','O','O','O','O','O','O','O',
        'U','U','U','U','U','U','U','U','U','U','U',
        'Y','Y','Y','Y','Y',
        'D'
    );
    
    $text = str_replace($vietnamese, $latin, $text);
    
    // Convert to lowercase
    $text = strtolower($text);
    
    // Replace non-alphanumeric characters with hyphens
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    
    // Remove leading/trailing hyphens
    $text = trim($text, '-');
    
    // Replace multiple hyphens with single hyphen
    $text = preg_replace('/-+/', '-', $text);
    
    // Return slug or generate one based on timestamp if empty
    return empty($text) ? 'category-' . time() : $text;
}

// Get JSON input
$input = get_json_input(['name']);

try {
    // Validate input
    $name = validate_required_string($input['name'], 'Category name', 1, 100);
    $parent_id = isset($input['parent_id']) && $input['parent_id'] 
                ? validate_id($input['parent_id'], 'Parent category', false) 
                : null;
    $description = sanitize($input['description'] ?? '');
    $sort_order = (int)($input['sort_order'] ?? 0);
    
    // Generate slug from name
    $slug = generate_slug($name);
    
    // Check if slug already exists
    $exists = db_get_var("SELECT id FROM product_categories WHERE slug = ?", [$slug]);
    if ($exists) {
        // Make slug unique by appending timestamp
        $slug .= '-' . time();
    }
    
    // If parent_id is provided, verify it exists
    if ($parent_id) {
        $parent = db_get_var("SELECT id FROM product_categories WHERE id = ?", [$parent_id]);
        if (!$parent) {
            json_error('Danh mục cha không tồn tại', 400);
        }
        
        // Prevent deep nesting (max 2 levels)
        $parent_has_parent = db_get_var(
            "SELECT parent_id FROM product_categories WHERE id = ?", 
            [$parent_id]
        );
        if ($parent_has_parent) {
            json_error('Chỉ hỗ trợ danh mục 2 cấp', 400);
        }
    }
    
    // Begin transaction
    begin_transaction();
    
    // Insert new category
    $category_data = [
        'parent_id' => $parent_id,
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'sort_order' => $sort_order,
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $category_id = db_insert('product_categories', $category_data);
    
    // Log activity
    log_activity(
        'create_category', 
        "Created product category: $name (#$category_id)", 
        'category', 
        $category_id
    );
    
    // Commit transaction
    commit_transaction();
    
    // Return success response
    json_success('Đã tạo danh mục thành công', [
        'category_id' => $category_id,
        'slug' => $slug
    ]);
    
} catch (Exception $e) {
    rollback_transaction();
    
    // Log error
    error_log('[SAVE_CATEGORY] Error: ' . $e->getMessage());
    
    // Return error response
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        json_error('Danh mục đã tồn tại', 400);
    } else {
        json_error('Không thể tạo danh mục: ' . $e->getMessage(), 500);
    }
}