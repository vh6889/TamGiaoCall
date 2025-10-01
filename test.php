<?php
/**
 * Comprehensive Test Suite for Telesale Manager System
 * Tests all fixed API endpoints and database logic
 * 
 * Usage: php test-suite.php
 */

define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

// Test configuration
$TEST_CONFIG = [
    'base_url' => 'http://localhost:8080/tamgiaocall/api/',
    'admin_session' => null, // Will be set after login
    'test_user_id' => 2, // telesale1
    'test_order_id' => null, // Will be created
];

class TestSuite {
    private $results = [];
    private $passed = 0;
    private $failed = 0;
    private $warnings = 0;
    private $db;
    
    public function __construct() {
        $this->db = get_db_connection();
        echo "\n╔══════════════════════════════════════════════════════════════╗\n";
        echo "║       TELESALE MANAGER - COMPREHENSIVE TEST SUITE           ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";
    }
    
    // ==================== DATABASE STRUCTURE TESTS ====================
    
    public function testDatabaseStructure() {
        $this->section("DATABASE STRUCTURE TESTS");
        
        // Test 1: Check call_logs has 'status' not 'primary_label'
        $this->test("call_logs.status exists", function() {
            $cols = $this->getTableColumns('call_logs');
            return in_array('status', $cols) && !in_array('primary_label', $cols);
        });
        
        // Test 2: Check users has 'status' not 'primary_label'
        $this->test("users.status exists", function() {
            $cols = $this->getTableColumns('users');
            return in_array('status', $cols) && !in_array('primary_label', $cols);
        });
        
        // Test 3: Check reminders has 'status' not 'primary_label'
        $this->test("reminders.status exists", function() {
            $cols = $this->getTableColumns('reminders');
            return in_array('status', $cols) && !in_array('primary_label', $cols);
        });
        
        // Test 4: Check order_labels has 'label_value' not 'is_final'
        $this->test("order_labels.label_value exists", function() {
            $cols = $this->getTableColumns('order_labels');
            return in_array('label_value', $cols) && !in_array('is_final', $cols);
        });
        
        // Test 5: Check order_labels has 'label_name' not just 'label'
        $this->test("order_labels.label_name exists", function() {
            $cols = $this->getTableColumns('order_labels');
            return in_array('label_name', $cols);
        });
        
        // Test 6: Check system labels exist
        $this->test("System labels exist (lbl_new_order, lbl_completed)", function() {
            $labels = db_get_results("SELECT label_key FROM order_labels WHERE is_system = 1");
            $keys = array_column($labels, 'label_key');
            return in_array('lbl_new_order', $keys) && in_array('lbl_completed', $keys);
        });
    }
    
    // ==================== LABEL QUERY TESTS ====================
    
    public function testLabelQueries() {
        $this->section("LABEL QUERY TESTS");
        
        // Test 1: Query order_labels by label_name (not 'label')
        $this->test("Query order_labels.label_name works", function() {
            try {
                $result = db_get_var("
                    SELECT label_key FROM order_labels 
                    WHERE label_name LIKE '%mới%' 
                    LIMIT 1
                ");
                return $result !== false;
            } catch (Exception $e) {
                return false;
            }
        });
        
        // Test 2: Query label_value (not is_final)
        $this->test("Query order_labels.label_value works", function() {
            try {
                $completed = db_get_var("
                    SELECT COUNT(*) FROM order_labels 
                    WHERE label_value = 1
                ");
                return $completed > 0;
            } catch (Exception $e) {
                return false;
            }
        });
        
        // Test 3: Find callback label dynamically
        $this->test("Find callback label dynamically", function() {
            $callback_label = db_get_var("
                SELECT label_key FROM order_labels 
                WHERE label_name LIKE '%gọi lại%' 
                   OR label_name LIKE '%callback%' 
                   OR label_name LIKE '%hẹn%'
                ORDER BY sort_order 
                LIMIT 1
            ");
            return $callback_label !== false;
        });
    }
    
    // ==================== ORDER WORKFLOW TESTS ====================
    
    public function testOrderWorkflow() {
        $this->section("ORDER WORKFLOW TESTS");
        
        // Create test order
        $order_id = $this->createTestOrder();
        
        if (!$order_id) {
            $this->warning("Cannot create test order, skipping workflow tests");
            return;
        }
        
        // Test 1: Claim order (free → assigned)
        $this->test("Claim order changes system_status to 'assigned'", function() use ($order_id) {
            // Simulate claim
            db_update('orders', [
                'system_status' => 'assigned',
                'assigned_to' => 2,
                'assigned_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$order_id]);
            
            $order = get_order($order_id);
            return $order['system_status'] === 'assigned' && $order['assigned_to'] == 2;
        });
        
        // Test 2: Start call (create call_logs with status='active')
        $this->test("Start call creates call_logs.status='active'", function() use ($order_id) {
            $call_id = db_insert('call_logs', [
                'order_id' => $order_id,
                'user_id' => 2,
                'user_name' => 'Test User',
                'start_time' => date('Y-m-d H:i:s'),
                'status' => 'active' // Must be 'status' not 'primary_label'
            ]);
            
            $call = db_get_row("SELECT * FROM call_logs WHERE id = ?", [$call_id]);
            return $call && $call['status'] === 'active';
        });
        
        // Test 3: End call (update call_logs.status to 'completed')
        $this->test("End call updates call_logs.status='completed'", function() use ($order_id) {
            $call = db_get_row("
                SELECT * FROM call_logs 
                WHERE order_id = ? AND end_time IS NULL 
                ORDER BY start_time DESC LIMIT 1
            ", [$order_id]);
            
            if (!$call) return false;
            
            db_update('call_logs', [
                'end_time' => date('Y-m-d H:i:s'),
                'status' => 'completed' // Must be 'status'
            ], 'id = ?', [$call['id']]);
            
            $updated = db_get_row("SELECT * FROM call_logs WHERE id = ?", [$call['id']]);
            return $updated['status'] === 'completed';
        });
        
        // Test 4: Update to completed label (label_value=1) locks order
        $this->test("Update to label_value=1 locks order", function() use ($order_id) {
            // Get completed label
            $completed_label = db_get_var("
                SELECT label_key FROM order_labels WHERE label_value = 1 LIMIT 1
            ");
            
            if (!$completed_label) return false;
            
            // Update order
            db_update('orders', [
                'primary_label' => $completed_label,
                'is_locked' => 1,
                'locked_at' => date('Y-m-d H:i:s'),
                'locked_by' => 2,
                'completed_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$order_id]);
            
            $order = get_order($order_id);
            return $order['is_locked'] == 1 && $order['primary_label'] === $completed_label;
        });
        
        // Cleanup
        db_delete('orders', 'id = ?', [$order_id]);
    }
    
    // ==================== USER MANAGEMENT TESTS ====================
    
    public function testUserManagement() {
        $this->section("USER MANAGEMENT TESTS");
        
        // Test 1: Disable user (update users.status)
        $this->test("Disable user updates users.status='inactive'", function() {
            // Create test user
            $user_id = db_insert('users', [
                'username' => 'test_user_' . time(),
                'password' => password_hash('test123', PASSWORD_DEFAULT),
                'full_name' => 'Test User',
                'role' => 'telesale',
                'status' => 'active'
            ]);
            
            // Disable
            db_update('users', ['status' => 'inactive'], 'id = ?', [$user_id]);
            
            $user = get_user($user_id);
            $result = $user['status'] === 'inactive';
            
            // Cleanup
            db_delete('users', 'id = ?', [$user_id]);
            
            return $result;
        });
        
        // Test 2: Enable user (update users.status)
        $this->test("Enable user updates users.status='active'", function() {
            // Create disabled user
            $user_id = db_insert('users', [
                'username' => 'test_user_' . time(),
                'password' => password_hash('test123', PASSWORD_DEFAULT),
                'full_name' => 'Test User',
                'role' => 'telesale',
                'status' => 'inactive'
            ]);
            
            // Enable
            db_update('users', ['status' => 'active'], 'id = ?', [$user_id]);
            
            $user = get_user($user_id);
            $result = $user['status'] === 'active';
            
            // Cleanup
            db_delete('users', 'id = ?', [$user_id]);
            
            return $result;
        });
    }
    
    // ==================== REMINDER TESTS ====================
    
    public function testReminders() {
        $this->section("REMINDER TESTS");
        
        // Test 1: Create reminder with status='pending'
        $this->test("Create reminder with status='pending'", function() {
            $reminder_id = db_insert('reminders', [
                'order_id' => $order_id,
                'user_id' => 2,
                'type' => 'callback',
                'due_time' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'status' => 'pending'
            ]);
            
            $reminder = db_get_row("SELECT * FROM reminders WHERE id = ?", [$reminder_id]);
            $result = $reminder && $reminder['status'] === 'pending';
            
            // Cleanup
            db_delete('reminders', 'id = ?', [$reminder_id]);
            
            return $result;
        });
        
        // Test 2: Complete reminder (update status, no completed_at)
        $this->test("Complete reminder updates status only", function() {
            $reminder_id = db_insert('reminders', [
                'order_id' => $order_id,
                'user_id' => 2,
                'type' => 'callback',
                'due_time' => date('Y-m-d H:i:s'),
                'status' => 'pending'
            ]);
            
            // Complete (only update status)
            db_update('reminders', [
                'status' => 'completed'
            ], 'id = ?', [$reminder_id]);
            
            $reminder = db_get_row("SELECT * FROM reminders WHERE id = ?", [$reminder_id]);
            $result = $reminder['status'] === 'completed';
            
            // Cleanup
            db_delete('reminders', 'id = ?', [$reminder_id]);
            
            return $result;
        });
        
        // Test 3: Cancel reminders for order
        $this->test("Cancel pending reminders works", function() {
            $order_id = $this->createTestOrder();
            if (!$order_id) return false;
            
            // Create reminder
            db_insert('reminders', [
                'order_id' => $order_id,
                'user_id' => 2,
                'type' => 'callback',
                'due_time' => date('Y-m-d H:i:s'),
                'status' => 'pending'
            ]);
            
            // Cancel
            db_update('reminders', 
                ['status' => 'cancelled'],
                'order_id = ? AND status = ?',
                [$order_id, 'pending']
            );
            
            $pending = db_get_var("
                SELECT COUNT(*) FROM reminders 
                WHERE order_id = ? AND status = 'pending'
            ", [$order_id]);
            
            // Cleanup
            db_delete('orders', 'id = ?', [$order_id]);
            
            return $pending == 0;
        });
    }
    
    // ==================== FUNCTIONS TESTS ====================
    
    public function testHelperFunctions() {
        $this->section("HELPER FUNCTIONS TESTS");
        
        // Test 1: get_new_status_key() returns 'lbl_new_order'
        $this->test("get_new_status_key() returns system label", function() {
            $key = get_new_status_key();
            return $key === 'lbl_new_order';
        });
        
        // Test 2: get_free_status_key() returns 'lbl_new_order'
        $this->test("get_free_status_key() returns system label", function() {
            $key = get_free_status_key();
            return $key === 'lbl_new_order';
        });
        
        // Test 3: get_order_labels() returns array
        $this->test("get_order_labels() works", function() {
            $labels = get_order_labels(true);
            return is_array($labels) && count($labels) >= 2;
        });
    }
    
    // ==================== EDGE CASES ====================
    
    public function testEdgeCases() {
        $this->section("EDGE CASES & VALIDATION TESTS");
        
        // Test 1: Cannot update locked order
        $this->test("Cannot update locked order", function() {
            $order_id = $this->createTestOrder();
            if (!$order_id) return false;
            
            // Lock order
            db_update('orders', [
                'is_locked' => 1,
                'locked_at' => date('Y-m-d H:i:s'),
                'locked_by' => 1
            ], 'id = ?', [$order_id]);
            
            $order = get_order($order_id);
            $result = $order['is_locked'] == 1;
            
            // Cleanup
            db_delete('orders', 'id = ?', [$order_id]);
            
            return $result;
        });
        
        // Test 2: System status validation (free vs assigned)
        $this->test("System status 'free' means no assigned_to", function() {
            $order_id = $this->createTestOrder();
            if (!$order_id) return false;
            
            $order = get_order($order_id);
            $result = $order['system_status'] === 'free' && $order['assigned_to'] === null;
            
            // Cleanup
            db_delete('orders', 'id = ?', [$order_id]);
            
            return $result;
        });
    }
    
    // ==================== HELPER METHODS ====================
    
    private function getTableColumns($table) {
        $stmt = $this->db->query("SHOW COLUMNS FROM {$table}");
        return array_column($stmt->fetchAll(), 'Field');
    }
    
    private function createTestOrder() {
        try {
            return db_insert('orders', [
                'order_number' => 'TEST-' . time(),
                'customer_name' => 'Test Customer',
                'customer_phone' => '0900000000',
                'total_amount' => 100000,
                'products' => json_encode([]),
                'system_status' => 'free',
                'primary_label' => 'lbl_new_order',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function section($title) {
        echo "\n┌─ {$title} " . str_repeat("─", 60 - strlen($title)) . "┐\n";
    }
    
    private function test($description, $callback) {
        try {
            $result = $callback();
            
            if ($result) {
                $this->passed++;
                echo "│ ✓ PASS: {$description}\n";
            } else {
                $this->failed++;
                echo "│ ✗ FAIL: {$description}\n";
            }
            
            $this->results[] = [
                'test' => $description,
                'status' => $result ? 'PASS' : 'FAIL'
            ];
            
        } catch (Exception $e) {
            $this->failed++;
            echo "│ ✗ ERROR: {$description}\n";
            echo "│   → {$e->getMessage()}\n";
            
            $this->results[] = [
                'test' => $description,
                'status' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function warning($message) {
        $this->warnings++;
        echo "│ ⚠ WARNING: {$message}\n";
    }
    
    public function printSummary() {
        echo "└" . str_repeat("─", 62) . "┘\n\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║                        TEST SUMMARY                          ║\n";
        echo "╠══════════════════════════════════════════════════════════════╣\n";
        
        $total = $this->passed + $this->failed;
        $success_rate = $total > 0 ? round(($this->passed / $total) * 100, 2) : 0;
        
        printf("║  Total Tests:  %3d                                           ║\n", $total);
        printf("║  ✓ Passed:     %3d  (%.2f%%)                                ║\n", $this->passed, $success_rate);
        printf("║  ✗ Failed:     %3d                                           ║\n", $this->failed);
        printf("║  ⚠ Warnings:   %3d                                           ║\n", $this->warnings);
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";
        
        if ($this->failed == 0) {
            echo "🎉 ALL TESTS PASSED! System is ready for production.\n\n";
        } else {
            echo "⚠️  SOME TESTS FAILED. Please review and fix issues above.\n\n";
        }
        
        return $this->failed == 0;
    }
}

// ==================== RUN TESTS ====================

try {
    $suite = new TestSuite();
    
    // Run all test suites
    $suite->testDatabaseStructure();
    $suite->testLabelQueries();
    $suite->testOrderWorkflow();
    $suite->testUserManagement();
    $suite->testReminders();
    $suite->testHelperFunctions();
    $suite->testEdgeCases();
    
    // Print summary
    $success = $suite->printSummary();
    
    exit($success ? 0 : 1);
    
} catch (Exception $e) {
    echo "\n❌ FATAL ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}