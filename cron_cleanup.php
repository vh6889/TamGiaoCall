<?php
/**
 * Cron Job: Cleanup old data
 * Run: 0 2 * * * php /path/to/cron_cleanup.php
 */
define('TSM_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting cleanup...\n";

try {
    $pdo = get_db_connection();
    
    // Clean old rate limits (older than 1 day)
    $deleted = $pdo->exec("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    echo "✓ Deleted {$deleted} old rate limit records\n";
    
    // Clean old failed login attempts (older than 7 days)
    $deleted = $pdo->exec("DELETE FROM failed_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    echo "✓ Deleted {$deleted} old failed login records\n";
    
    // Clean old activity logs (older than 90 days)
    $deleted = $pdo->exec("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    echo "✓ Deleted {$deleted} old activity logs\n";
    
    echo "[" . date('Y-m-d H:i:s') . "] Cleanup completed successfully\n";
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log('[CRON_CLEANUP] ' . $e->getMessage());
}