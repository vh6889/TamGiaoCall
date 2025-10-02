<?php
/**
 * Test Statistics Module
 * File để test và debug module statistics
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

echo "<h2>Testing Statistics Module</h2>";

// Test 1: Check file exists
$files_to_check = [
    'modules/statistics/statistics_autoload.php',
    'modules/statistics/Core/statistics_base_module.php',
    'modules/statistics/Reports/overview_report_module.php',
    'modules/statistics/Widgets/metric_card_widget.php'
];

echo "<h3>1. Checking files existence:</h3>";
foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ $file EXISTS<br>";
    } else {
        echo "❌ $file NOT FOUND<br>";
    }
}

// Test 2: Try manual loading
echo "<h3>2. Try manual loading:</h3>";

// Manually include the base class first
if (file_exists('modules/statistics/Core/statistics_base_module.php')) {
    require_once 'modules/statistics/Core/statistics_base_module.php';
    echo "✅ Loaded statistics_base_module.php<br>";
}

// Then include overview report
if (file_exists('modules/statistics/Reports/overview_report_module.php')) {
    require_once 'modules/statistics/Reports/overview_report_module.php';
    echo "✅ Loaded overview_report_module.php<br>";
}

// Test 3: Check if classes exist
echo "<h3>3. Checking classes:</h3>";

if (class_exists('Modules\Statistics\Core\StatisticsBase')) {
    echo "✅ Class Modules\Statistics\Core\StatisticsBase EXISTS<br>";
} else {
    echo "❌ Class Modules\Statistics\Core\StatisticsBase NOT FOUND<br>";
}

if (class_exists('Modules\Statistics\Reports\OverviewReport')) {
    echo "✅ Class Modules\Statistics\Reports\OverviewReport EXISTS<br>";
} else {
    echo "❌ Class Modules\Statistics\Reports\OverviewReport NOT FOUND<br>";
}

// Test 4: Try to instantiate
echo "<h3>4. Try to instantiate:</h3>";

try {
    $db = get_db_connection();
    $report = new Modules\Statistics\Reports\OverviewReport($db);
    echo "✅ Successfully created OverviewReport instance<br>";
    
    // Test a method
    $report->setDateRange(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
    echo "✅ Successfully called setDateRange method<br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace:<pre>" . $e->getTraceAsString() . "</pre>";
}

// Test 5: Test autoloader
echo "<h3>5. Testing autoloader:</h3>";

// First unload classes if loaded
if (function_exists('opcache_reset')) {
    opcache_reset();
}

// Include autoloader
require_once 'modules/statistics/statistics_autoload.php';
echo "✅ Loaded autoloader<br>";

// Try to use class with autoloader
try {
    $db = get_db_connection();
    $report2 = new Modules\Statistics\Reports\OverviewReport($db);
    echo "✅ Autoloader works! Created OverviewReport via autoloader<br>";
} catch (Exception $e) {
    echo "❌ Autoloader failed: " . $e->getMessage() . "<br>";
}

// Test 6: List all declared classes with Statistics namespace
echo "<h3>6. All loaded Statistics classes:</h3>";
$allClasses = get_declared_classes();
foreach ($allClasses as $class) {
    if (strpos($class, 'Modules\\Statistics') === 0) {
        echo "- $class<br>";
    }
}

echo "<hr>";
echo "<p>Test completed. Check the results above to identify the issue.</p>";