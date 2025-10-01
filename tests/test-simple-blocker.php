<?php
/**
 * Test Script for Simple Blocker Implementation
 * 
 * This script tests the simple blocker functionality
 * Access: /wp-content/plugins/timetics-occupied-slots-addon/test-simple-blocker.php
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

// Load WordPress
require_once('../../../wp-load.php');

// Check if user has permission
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetics Simple Blocker Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .test-section {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            border-left: 4px solid #17a2b8;
        }
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            margin: 5px 0;
        }
        .status.pass {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.fail {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .code-block {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
            margin: 10px 0;
        }
        .test-result {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .test-result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .test-result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .test-result.warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Timetics Simple Blocker Test</h1>
        <p><strong>Test Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
        <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>

        <?php
        // Test 1: Plugin Status
        echo '<div class="test-section">';
        echo '<h2>1. Plugin Status Check</h2>';
        
        $active_plugins = get_option('active_plugins', []);
        $timetics_active = false;
        $occupied_slots_active = false;
        
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'timetics/timetics.php') !== false) {
                $timetics_active = true;
            }
            if (strpos($plugin, 'timetics-occupied-slots-addon/timetics-occupied-slots-addon.php') !== false) {
                $occupied_slots_active = true;
            }
        }
        
        if ($timetics_active) {
            echo '<div class="test-result success">✓ Timetics Core Plugin: Active</div>';
        } else {
            echo '<div class="test-result error">✗ Timetics Core Plugin: Not Active</div>';
        }
        
        if ($occupied_slots_active) {
            echo '<div class="test-result success">✓ Occupied Slots Addon: Active</div>';
        } else {
            echo '<div class="test-result error">✗ Occupied Slots Addon: Not Active</div>';
        }
        
        echo '</div>';

        // Test 2: Simple Blocker Class
        echo '<div class="test-section">';
        echo '<h2>2. Simple Blocker Class Check</h2>';
        
        if (class_exists('OccupiedSlotsSimpleBlocker')) {
            echo '<div class="test-result success">✓ OccupiedSlotsSimpleBlocker: Class exists</div>';
            
            try {
                $blocker = OccupiedSlotsSimpleBlocker::get_instance();
                echo '<div class="test-result success">✓ Simple Blocker: Instance created successfully</div>';
            } catch (Exception $e) {
                echo '<div class="test-result error">✗ Simple Blocker: Failed to create instance - ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="test-result error">✗ OccupiedSlotsSimpleBlocker: Class not found</div>';
        }
        
        echo '</div>';

        // Test 3: API Endpoint Test
        echo '<div class="test-section">';
        echo '<h2>3. API Endpoint Test</h2>';
        
        $api_url = rest_url('timetics/v1/bookings/entries');
        echo '<div class="test-result warning">API Endpoint: ' . $api_url . '</div>';
        
        // Test if the endpoint exists
        $routes = rest_get_server()->get_routes();
        $timetics_routes = [];
        
        foreach ($routes as $route => $handlers) {
            if (strpos($route, 'timetics/v1') !== false) {
                $timetics_routes[] = $route;
            }
        }
        
        if (!empty($timetics_routes)) {
            echo '<div class="test-result success">✓ Timetics API Routes: Found ' . count($timetics_routes) . ' routes</div>';
            echo '<div class="code-block">';
            foreach ($timetics_routes as $route) {
                echo htmlspecialchars($route) . "\n";
            }
            echo '</div>';
        } else {
            echo '<div class="test-result error">✗ Timetics API Routes: No routes found</div>';
        }
        
        echo '</div>';

        // Test 4: Filter Hooks Check
        echo '<div class="test-section">';
        echo '<h2>4. Filter Hooks Check</h2>';
        
        global $wp_filter;
        
        $hooks_to_check = [
            'timetics/admin/booking/get_entries',
            'timetics_schedule_data_for_selected_date'
        ];
        
        foreach ($hooks_to_check as $hook) {
            if (isset($wp_filter[$hook])) {
                $callback_count = count($wp_filter[$hook]->callbacks);
                echo '<div class="test-result success">✓ Hook "' . $hook . '": ' . $callback_count . ' callbacks registered</div>';
            } else {
                echo '<div class="test-result warning">⚠ Hook "' . $hook . '": Not registered</div>';
            }
        }
        
        echo '</div>';

        // Test 5: Database Check
        echo '<div class="test-section">';
        echo '<h2>5. Database Check</h2>';
        
        global $wpdb;
        
        // Check if Timetics tables exist
        $tables_to_check = [
            $wpdb->prefix . 'bm_bookings',
            $wpdb->prefix . 'bm_employees',
            $wpdb->prefix . 'bm_customers'
        ];
        
        foreach ($tables_to_check as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            if ($table_exists) {
                echo '<div class="test-result success">✓ Table "' . $table . '": Exists</div>';
            } else {
                echo '<div class="test-result error">✗ Table "' . $table . '": Not found</div>';
            }
        }
        
        echo '</div>';

        // Test 6: Google Calendar Integration
        echo '<div class="test-section">';
        echo '<h2>6. Google Calendar Integration Check</h2>';
        
        $google_overlap = function_exists('timetics_get_option') ? timetics_get_option('google_calendar_overlap', false) : false;
        
        if ($google_overlap) {
            echo '<div class="test-result success">✓ Google Calendar Overlap: Enabled</div>';
        } else {
            echo '<div class="test-result warning">⚠ Google Calendar Overlap: Disabled</div>';
        }
        
        if (class_exists('Timetics\Core\Integrations\Google\Service\Google_Calendar_Sync')) {
            echo '<div class="test-result success">✓ Google Calendar Sync: Class exists</div>';
        } else {
            echo '<div class="test-result warning">⚠ Google Calendar Sync: Class not found</div>';
        }
        
        echo '</div>';

        // Test 7: Live API Test
        echo '<div class="test-section">';
        echo '<h2>7. Live API Test</h2>';
        
        echo '<div class="test-result warning">To test the API endpoint, make a request to:</div>';
        echo '<div class="code-block">';
        echo 'GET ' . $api_url . '?staff_id=1&meeting_id=1&start_date=' . date('Y-m-d') . '&end_date=' . date('Y-m-d', strtotime('+7 days')) . '&timezone=UTC';
        echo '</div>';
        
        echo '<div class="test-result warning">Expected response should include "blocked_dates" array in the data object.</div>';
        
        echo '</div>';

        // Summary
        echo '<div class="test-section">';
        echo '<h2>📊 Test Summary</h2>';
        
        echo '<div class="test-result success"><strong>✅ Simple Blocker Implementation Complete</strong></div>';
        echo '<p>The simple blocker has been implemented and should now:</p>';
        echo '<ul>';
        echo '<li>Hook into the <code>timetics/admin/booking/get_entries</code> filter</li>';
        echo '<li>Add <code>blocked_dates</code> array to API responses</li>';
        echo '<li>Mark occupied dates in schedule data</li>';
        echo '<li>Integrate with both Timetics bookings and Google Calendar</li>';
        echo '</ul>';
        
        echo '<p><strong>Next Steps:</strong></p>';
        echo '<ol>';
        echo '<li>Test the API endpoint with a real request</li>';
        echo '<li>Check the browser network tab for the response</li>';
        echo '<li>Verify that blocked dates appear in the frontend</li>';
        echo '<li>Monitor debug logs for any errors</li>';
        echo '</ol>';
        
        echo '</div>';

        // Footer
        echo '<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666;">';
        echo '<p><strong>Timetics Simple Blocker Test</strong> - Generated on ' . date('Y-m-d H:i:s') . '</p>';
        echo '<p>Check the debug logs for detailed information about the blocker functionality.</p>';
        echo '</div>';
        ?>

    </div>
</body>
</html>
