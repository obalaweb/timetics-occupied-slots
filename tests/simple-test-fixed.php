<?php
/**
 * Simple Test Script - Fixed Path
 */

// Load WordPress
require_once('../../../../wp-load.php');

echo "Testing WordPress load...\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";

// Test if our class exists
if (class_exists('OccupiedSlotsSimpleBlocker')) {
    echo "✓ OccupiedSlotsSimpleBlocker class exists\n";
} else {
    echo "✗ OccupiedSlotsSimpleBlocker class not found\n";
}

// Test if Timetics is active
$active_plugins = get_option('active_plugins', []);
$timetics_active = false;
foreach ($active_plugins as $plugin) {
    if (strpos($plugin, 'timetics/timetics.php') !== false) {
        $timetics_active = true;
        break;
    }
}

echo "Timetics active: " . ($timetics_active ? "Yes" : "No") . "\n";

// Test database
global $wpdb;
$result = $wpdb->get_var("SELECT 1");
echo "Database connection: " . ($result ? "Working" : "Failed") . "\n";

// Test if occupied slots addon is active
$occupied_slots_active = false;
foreach ($active_plugins as $plugin) {
    if (strpos($plugin, 'timetics-occupied-slots-addon/timetics-occupied-slots-addon.php') !== false) {
        $occupied_slots_active = true;
        break;
    }
}

echo "Occupied Slots Addon active: " . ($occupied_slots_active ? "Yes" : "No") . "\n";

// Test Google Calendar integration
$google_overlap = function_exists('timetics_get_option') ? timetics_get_option('google_calendar_overlap', false) : false;
echo "Google Calendar Overlap: " . ($google_overlap ? "Enabled" : "Disabled") . "\n";

// Test filter hooks
global $wp_filter;
$hooks = [
    'timetics/admin/booking/get_entries',
    'timetics_schedule_data_for_selected_date'
];

foreach ($hooks as $hook) {
    if (isset($wp_filter[$hook])) {
        $callback_count = count($wp_filter[$hook]->callbacks);
        echo "✓ Hook '$hook': $callback_count callbacks\n";
    } else {
        echo "✗ Hook '$hook': Not registered\n";
    }
}

echo "Test complete!\n";
