<?php
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

// Test hàm db_get_results
$test = db_get_results("SELECT 1 as test");
echo "Test db_get_results:<br><pre>";
var_dump($test);
echo "</pre>";

// Test query KPI thực tế
$user_id = 2; // telesale1
$current_month_sql = date('Y-m-01');
$kpi_test = db_get_results("SELECT target_type, target_value FROM kpis WHERE user_id = ? AND target_month = ?", [$user_id, $current_month_sql]);

echo "<br>Test KPI query:<br><pre>";
var_dump($kpi_test);
echo "</pre>";

echo "<br>Type: " . gettype($kpi_test);
echo "<br>Is array: " . (is_array($kpi_test) ? 'YES' : 'NO');
?>