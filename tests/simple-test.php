<?php
/**
 * Simple Test Script
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

echo "Test complete!\n";
