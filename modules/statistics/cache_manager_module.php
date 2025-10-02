<?php
/**
 * Cache Manager Module
 * Manage caching for heavy statistics reports
 */

namespace Modules\Statistics\Cache;

class CacheManager {
    private $cacheDir = null;
    private $defaultTtl = 3600; // 1 hour
    private $enabled = true;
    private $db = null;
    private $useDatabase = false;
    
    public function __construct($db = null, $cacheDir = null) {
        $this->db = $db;
        $this->cacheDir = $cacheDir ?: sys_get_temp_dir() . '/statistics_cache/';
        
        // Create cache directory if not exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
        
        // Check if database caching table exists
        if ($this->db) {
            $this->checkDatabaseTable();
        }
    }
    
    /**
     * Check if cache table exists
     */
    private function checkDatabaseTable() {
        try {
            $this->db->query("SELECT 1 FROM statistics_cache LIMIT 1");
            $this->useDatabase = true;
        } catch (\Exception $e) {
            // Table doesn't exist, create it
            $this->createCacheTable();
        }
    }
    
    /**
     * Create cache table
     */
    private function createCacheTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS statistics_cache (
                cache_key VARCHAR(255) PRIMARY KEY,
                cache_value LONGTEXT,
                expires_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                tags VARCHAR(500),
                INDEX idx_expires (expires_at),
                INDEX idx_tags (tags)
            )";
            
            $this->db->exec($sql);
            $this->useDatabase = true;
        } catch (\Exception $e) {
            // Can't create table, use file cache only
            $this->useDatabase = false;
        }
    }
    
    /**
     * Enable/disable caching
     */
    public function setEnabled($enabled) {
        $this->enabled = $enabled;
        return $this;
    }
    
    /**
     * Set default TTL
     */
    public function setDefaultTtl($seconds) {
        $this->defaultTtl = $seconds;
        return $this;
    }
    
    /**
     * Generate cache key
     */
    public function generateKey($identifier, $params = []) {
        $keyParts = [$identifier];
        
        // Add user context
        if (isset($_SESSION['user'])) {
            $keyParts[] = $_SESSION['user']['id'];
            $keyParts[] = $_SESSION['user']['role'];
        }
        
        // Add parameters
        if (!empty($params)) {
            ksort($params);
            $keyParts[] = md5(json_encode($params));
        }
        
        return implode('_', $keyParts);
    }
    
    /**
     * Get from cache
     */
    public function get($key) {
        if (!$this->enabled) {
            return null;
        }
        
        // Try database cache first
        if ($this->useDatabase) {
            $data = $this->getFromDatabase($key);
            if ($data !== null) {
                return $data;
            }
        }
        
        // Try file cache
        return $this->getFromFile($key);
    }
    
    /**
     * Set cache value
     */
    public function set($key, $value, $ttl = null, $tags = []) {
        if (!$this->enabled) {
            return false;
        }
        
        $ttl = $ttl ?: $this->defaultTtl;
        $expiresAt = time() + $ttl;
        
        // Store in database if available
        if ($this->useDatabase) {
            $this->setInDatabase($key, $value, $expiresAt, $tags);
        }
        
        // Also store in file cache
        return $this->setInFile($key, $value, $expiresAt);
    }
    
    /**
     * Delete from cache
     */
    public function delete($key) {
        if ($this->useDatabase) {
            $this->deleteFromDatabase($key);
        }
        
        $this->deleteFromFile($key);
        
        return true;
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        // Clear database cache
        if ($this->useDatabase) {
            $this->db->exec("TRUNCATE TABLE statistics_cache");
        }
        
        // Clear file cache
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    /**
     * Clear expired cache
     */
    public function clearExpired() {
        // Clear expired from database
        if ($this->useDatabase) {
            $this->db->exec("DELETE FROM statistics_cache WHERE expires_at < NOW()");
        }
        
        // Clear expired files
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            $data = $this->readFile($file);
            if ($data && $data['expires_at'] < time()) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    /**
     * Clear cache by tags
     */
    public function clearByTags($tags) {
        if (!is_array($tags)) {
            $tags = [$tags];
        }
        
        if ($this->useDatabase) {
            $placeholders = array_fill(0, count($tags), '?');
            $sql = "DELETE FROM statistics_cache WHERE ";
            $conditions = [];
            
            foreach ($tags as $tag) {
                $conditions[] = "tags LIKE ?";
            }
            
            $sql .= implode(' OR ', $conditions);
            
            $params = array_map(function($tag) {
                return '%' . $tag . '%';
            }, $tags);
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
        
        // File cache doesn't support tags efficiently
        // Would need to read all files to check tags
        
        return true;
    }
    
    /**
     * Get from database
     */
    private function getFromDatabase($key) {
        try {
            $stmt = $this->db->prepare(
                "SELECT cache_value FROM statistics_cache 
                 WHERE cache_key = ? AND expires_at > NOW()"
            );
            $stmt->execute([$key]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result) {
                return json_decode($result['cache_value'], true);
            }
        } catch (\Exception $e) {
            // Log error
        }
        
        return null;
    }
    
    /**
     * Set in database
     */
    private function setInDatabase($key, $value, $expiresAt, $tags) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO statistics_cache (cache_key, cache_value, expires_at, tags) 
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE 
                 cache_value = VALUES(cache_value),
                 expires_at = VALUES(expires_at),
                 tags = VALUES(tags)"
            );
            
            $stmt->execute([
                $key,
                json_encode($value),
                date('Y-m-d H:i:s', $expiresAt),
                implode(',', $tags)
            ]);
            
            return true;
        } catch (\Exception $e) {
            // Log error
        }
        
        return false;
    }
    
    /**
     * Delete from database
     */
    private function deleteFromDatabase($key) {
        try {
            $stmt = $this->db->prepare("DELETE FROM statistics_cache WHERE cache_key = ?");
            $stmt->execute([$key]);
            return true;
        } catch (\Exception $e) {
            // Log error
        }
        
        return false;
    }
    
    /**
     * Get from file cache
     */
    private function getFromFile($key) {
        $filename = $this->getCacheFilename($key);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = $this->readFile($filename);
        
        if (!$data || $data['expires_at'] < time()) {
            unlink($filename);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Set in file cache
     */
    private function setInFile($key, $value, $expiresAt) {
        $filename = $this->getCacheFilename($key);
        
        $data = [
            'key' => $key,
            'value' => $value,
            'expires_at' => $expiresAt,
            'created_at' => time()
        ];
        
        return $this->writeFile($filename, $data);
    }
    
    /**
     * Delete from file cache
     */
    private function deleteFromFile($key) {
        $filename = $this->getCacheFilename($key);
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return true;
    }
    
    /**
     * Get cache filename
     */
    private function getCacheFilename($key) {
        return $this->cacheDir . md5($key) . '.cache';
    }
    
    /**
     * Read file
     */
    private function readFile($filename) {
        $content = @file_get_contents($filename);
        
        if ($content === false) {
            return null;
        }
        
        return @unserialize($content);
    }
    
    /**
     * Write file
     */
    private function writeFile($filename, $data) {
        return @file_put_contents($filename, serialize($data), LOCK_EX) !== false;
    }
    
    /**
     * Get cache statistics
     */
    public function getStatistics() {
        $stats = [
            'total_entries' => 0,
            'total_size' => 0,
            'expired_entries' => 0,
            'database_entries' => 0,
            'file_entries' => 0
        ];
        
        // Database stats
        if ($this->useDatabase) {
            $stats['database_entries'] = $this->db->query(
                "SELECT COUNT(*) FROM statistics_cache"
            )->fetchColumn();
            
            $stats['expired_entries'] = $this->db->query(
                "SELECT COUNT(*) FROM statistics_cache WHERE expires_at < NOW()"
            )->fetchColumn();
        }
        
        // File stats
        $files = glob($this->cacheDir . '*.cache');
        $stats['file_entries'] = count($files);
        
        foreach ($files as $file) {
            $stats['total_size'] += filesize($file);
        }
        
        $stats['total_entries'] = $stats['database_entries'] + $stats['file_entries'];
        $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);
        
        return $stats;
    }
    
    /**
     * Format bytes
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Remember query result
     */
    public function remember($key, $callback, $ttl = null, $tags = []) {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = call_user_func($callback);
            $this->set($key, $value, $ttl, $tags);
        }
        
        return $value;
    }
}