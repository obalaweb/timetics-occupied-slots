<?php
/**
 * Debug Plugin Initialization
 * 
 * This script helps debug plugin initialization issues
 */

// Only run if accessed directly and in debug mode
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    die('Debug mode required');
}

echo "<h2>Timetics Occupied Slots Plugin Debug</h2>";

// Check if classes exist
$classes_to_check = [
    'OccupiedSlotsCache',
    'OccupiedSlotsLogger', 
    'OccupiedSlotsCoreOptimized',
    'OccupiedSlotsAdmin',
    'OccupiedSlotsSettingsModifier',
    'OccupiedSlotsGoogleCalendarFixed',
    'OccupiedSlotsSimpleBlocker',
    'OccupiedSlotsFrontendReact',
    'OccupiedSlotsCacheOptimized',
    'OccupiedSlotsAssetOptimizer',
    'OccupiedSlotsPerformanceMonitor'
];

echo "<h3>Class Availability Check</h3>";
foreach ($classes_to_check as $class) {
    $exists = class_exists($class);
    echo "<p><strong>$class:</strong> " . ($exists ? "✅ Available" : "❌ Missing") . "</p>";
}

// Check if methods exist
echo "<h3>Method Availability Check</h3>";

if (class_exists('OccupiedSlotsCache')) {
    $methods = ['is_plugin_enabled', 'get_google_calendar_overlap', 'clear_all', 'get_cache_stats'];
    foreach ($methods as $method) {
        $exists = method_exists('OccupiedSlotsCache', $method);
        echo "<p><strong>OccupiedSlotsCache::$method:</strong> " . ($exists ? "✅ Available" : "❌ Missing") . "</p>";
    }
}

if (class_exists('OccupiedSlotsLogger')) {
    $methods = ['start_timer', 'end_timer', 'debug', 'log_slot_processing', 'get_performance_metrics'];
    foreach ($methods as $method) {
        $exists = method_exists('OccupiedSlotsLogger', $method);
        echo "<p><strong>OccupiedSlotsLogger::$method:</strong> " . ($exists ? "✅ Available" : "❌ Missing") . "</p>";
    }
}

// Test plugin initialization
echo "<h3>Plugin Initialization Test</h3>";
try {
    $plugin = Timetics_Occupied_Slots_Addon::get_instance();
    echo "<p>✅ Plugin initialized successfully</p>";
    
    // Test core functionality
    $core = $plugin->get_core();
    echo "<p>✅ Core functionality available</p>";
    
    // Test frontend
    $frontend = $plugin->get_frontend();
    echo "<p>✅ Frontend available</p>";
    
    // Test Google Calendar
    $google_calendar = $plugin->get_google_calendar();
    echo "<p>✅ Google Calendar integration available</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Plugin initialization failed: " . $e->getMessage() . "</p>";
}

// Check WordPress hooks
echo "<h3>WordPress Hooks Check</h3>";
$hooks_to_check = [
    'timetics_booking_slot',
    'timetics_schedule_data_for_selected_date', 
    'timetics/admin/booking/get_entries',
    'wp_enqueue_scripts',
    'admin_enqueue_scripts'
];

foreach ($hooks_to_check as $hook) {
    $has_filters = has_filter($hook);
    echo "<p><strong>$hook:</strong> " . ($has_filters ? "✅ Has filters" : "❌ No filters") . "</p>";
}

echo "<h3>Debug Complete</h3>";
?>
