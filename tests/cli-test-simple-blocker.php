<?php
/**
 * CLI Test Script for Simple Blocker Implementation
 * 
 * Run from command line: php cli-test-simple-blocker.php
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

// Load WordPress
require_once('../../../wp-load.php');

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

echo "🧪 Timetics Simple Blocker - CLI Test Suite\n";
echo "==========================================\n\n";

class TimeticsSimpleBlockerCLITest
{
    private $test_results = [];
    private $debug_mode = true;
    
    public function __construct()
    {
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
    }
    
    /**
     * Run all tests
     */
    public function run_all_tests()
    {
        echo "Starting comprehensive testing...\n\n";
        
        $this->test_environment();
        $this->test_plugin_status();
        $this->test_database_connection();
        $this->test_timetics_tables();
        $this->test_google_calendar_integration();
        $this->test_simple_blocker_class();
        $this->test_api_endpoints();
        $this->test_filter_hooks();
        $this->test_database_queries();
        $this->test_api_response_modification();
        
        $this->display_summary();
    }
    
    /**
     * Test environment setup
     */
    private function test_environment()
    {
        echo "1. Testing Environment Setup\n";
        echo "---------------------------\n";
        
        $tests = [
            'WordPress Version' => get_bloginfo('version'),
            'PHP Version' => PHP_VERSION,
            'Debug Mode' => defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled',
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time'),
            'Current User' => wp_get_current_user()->user_login ?? 'Not logged in'
        ];
        
        foreach ($tests as $test => $value) {
            echo "   ✓ $test: $value\n";
        }
        
        echo "\n";
    }
    
    /**
     * Test plugin status
     */
    private function test_plugin_status()
    {
        echo "2. Testing Plugin Status\n";
        echo "------------------------\n";
        
        $active_plugins = get_option('active_plugins', []);
        $required_plugins = [
            'timetics/timetics.php' => 'Timetics Core',
            'timetics-occupied-slots-addon/timetics-occupied-slots-addon.php' => 'Occupied Slots Addon'
        ];
        
        foreach ($required_plugins as $plugin => $name) {
            $is_active = in_array($plugin, $active_plugins);
            $status = $is_active ? '✓ Active' : '✗ Inactive';
            echo "   $status $name\n";
            
            $this->test_results['plugins'][$name] = $is_active;
        }
        
        echo "\n";
    }
    
    /**
     * Test database connection
     */
    private function test_database_connection()
    {
        echo "3. Testing Database Connection\n";
        echo "-------------------------------\n";
        
        global $wpdb;
        
        try {
            $result = $wpdb->get_var("SELECT 1");
            echo "   ✓ Database connection: Working\n";
            echo "   ✓ Database name: " . DB_NAME . "\n";
            echo "   ✓ Table prefix: " . $wpdb->prefix . "\n";
            
            $this->test_results['database']['connection'] = true;
        } catch (Exception $e) {
            echo "   ✗ Database connection: Failed - " . $e->getMessage() . "\n";
            $this->test_results['database']['connection'] = false;
        }
        
        echo "\n";
    }
    
    /**
     * Test Timetics tables
     */
    private function test_timetics_tables()
    {
        echo "4. Testing Timetics Database Tables\n";
        echo "------------------------------------\n";
        
        global $wpdb;
        
        $tables = [
            'bm_bookings' => 'Bookings table',
            'bm_employees' => 'Employees table',
            'bm_customers' => 'Customers table',
            'bm_payments' => 'Payments table'
        ];
        
        foreach ($tables as $table => $description) {
            $full_table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") == $full_table_name;
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
                echo "   ✓ $description: Exists ($count records)\n";
                $this->test_results['tables'][$table] = true;
            } else {
                echo "   ✗ $description: Not found\n";
                $this->test_results['tables'][$table] = false;
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test Google Calendar integration
     */
    private function test_google_calendar_integration()
    {
        echo "5. Testing Google Calendar Integration\n";
        echo "-------------------------------------\n";
        
        // Check if Google Calendar overlap is enabled
        $google_overlap = function_exists('timetics_get_option') ? timetics_get_option('google_calendar_overlap', false) : false;
        echo "   " . ($google_overlap ? "✓" : "⚠") . " Google Calendar Overlap: " . ($google_overlap ? "Enabled" : "Disabled") . "\n";
        
        // Check if Google Calendar Sync class exists
        $sync_class_exists = class_exists('Timetics\Core\Integrations\Google\Service\Google_Calendar_Sync');
        echo "   " . ($sync_class_exists ? "✓" : "✗") . " Google Calendar Sync Class: " . ($sync_class_exists ? "Available" : "Not found") . "\n";
        
        // Check for users with Google access tokens
        if (function_exists('timetics_get_google_access_token')) {
            $users = get_users(['number' => 5]);
            $users_with_google = 0;
            
            foreach ($users as $user) {
                $token = timetics_get_google_access_token($user->ID);
                if (!empty($token)) {
                    $users_with_google++;
                }
            }
            
            echo "   " . ($users_with_google > 0 ? "✓" : "⚠") . " Users with Google access: $users_with_google\n";
        } else {
            echo "   ✗ Google access token function: Not available\n";
        }
        
        $this->test_results['google_calendar']['overlap'] = $google_overlap;
        $this->test_results['google_calendar']['sync_class'] = $sync_class_exists;
        
        echo "\n";
    }
    
    /**
     * Test simple blocker class
     */
    private function test_simple_blocker_class()
    {
        echo "6. Testing Simple Blocker Class\n";
        echo "-------------------------------\n";
        
        // Check if class exists
        if (class_exists('OccupiedSlotsSimpleBlocker')) {
            echo "   ✓ OccupiedSlotsSimpleBlocker: Class exists\n";
            
            try {
                $blocker = OccupiedSlotsSimpleBlocker::get_instance();
                echo "   ✓ Simple Blocker: Instance created successfully\n";
                
                // Test if hooks are registered
                global $wp_filter;
                $hooks = [
                    'timetics/admin/booking/get_entries',
                    'timetics_schedule_data_for_selected_date'
                ];
                
                foreach ($hooks as $hook) {
                    if (isset($wp_filter[$hook])) {
                        $callback_count = count($wp_filter[$hook]->callbacks);
                        echo "   ✓ Hook '$hook': $callback_count callbacks registered\n";
                    } else {
                        echo "   ⚠ Hook '$hook': Not registered\n";
                    }
                }
                
                $this->test_results['simple_blocker']['class_exists'] = true;
                $this->test_results['simple_blocker']['instance_created'] = true;
                
            } catch (Exception $e) {
                echo "   ✗ Simple Blocker: Failed to create instance - " . $e->getMessage() . "\n";
                $this->test_results['simple_blocker']['instance_created'] = false;
            }
        } else {
            echo "   ✗ OccupiedSlotsSimpleBlocker: Class not found\n";
            $this->test_results['simple_blocker']['class_exists'] = false;
        }
        
        echo "\n";
    }
    
    /**
     * Test API endpoints
     */
    private function test_api_endpoints()
    {
        echo "7. Testing API Endpoints\n";
        echo "------------------------\n";
        
        $routes = rest_get_server()->get_routes();
        $timetics_routes = [];
        
        foreach ($routes as $route => $handlers) {
            if (strpos($route, 'timetics/v1') !== false) {
                $timetics_routes[] = $route;
            }
        }
        
        echo "   ✓ Timetics API Routes: Found " . count($timetics_routes) . " routes\n";
        
        // Check for specific booking endpoint
        $booking_endpoint = '/timetics/v1/bookings/entries';
        if (isset($routes[$booking_endpoint])) {
            echo "   ✓ Booking entries endpoint: Available\n";
            $this->test_results['api']['booking_endpoint'] = true;
        } else {
            echo "   ✗ Booking entries endpoint: Not found\n";
            $this->test_results['api']['booking_endpoint'] = false;
        }
        
        echo "\n";
    }
    
    /**
     * Test filter hooks
     */
    private function test_filter_hooks()
    {
        echo "8. Testing Filter Hooks\n";
        echo "----------------------\n";
        
        global $wp_filter;
        
        $hooks_to_check = [
            'timetics/admin/booking/get_entries' => 'Main API filter',
            'timetics_schedule_data_for_selected_date' => 'Schedule data filter'
        ];
        
        foreach ($hooks_to_check as $hook => $description) {
            if (isset($wp_filter[$hook])) {
                $callback_count = count($wp_filter[$hook]->callbacks);
                echo "   ✓ $description: $callback_count callbacks registered\n";
                $this->test_results['hooks'][$hook] = true;
            } else {
                echo "   ✗ $description: Not registered\n";
                $this->test_results['hooks'][$hook] = false;
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test database queries
     */
    private function test_database_queries()
    {
        echo "9. Testing Database Queries\n";
        echo "--------------------------\n";
        
        global $wpdb;
        
        // Test bookings query
        try {
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime('+30 days'));
            
            $bookings = $wpdb->get_results($wpdb->prepare("
                SELECT DATE(start_date) as booking_date, COUNT(*) as booking_count
                FROM {$wpdb->prefix}bm_bookings 
                WHERE start_date BETWEEN %s AND %s
                AND status IN ('confirmed', 'completed')
                GROUP BY DATE(start_date)
            ", $start_date, $end_date));
            
            echo "   ✓ Bookings query: Successful (" . count($bookings) . " results)\n";
            
            if (!empty($bookings)) {
                echo "   ✓ Sample booking dates:\n";
                foreach (array_slice($bookings, 0, 3) as $booking) {
                    echo "     - {$booking->booking_date}: {$booking->booking_count} bookings\n";
                }
            } else {
                echo "   ⚠ No bookings found in date range\n";
            }
            
            $this->test_results['database']['bookings_query'] = true;
            
        } catch (Exception $e) {
            echo "   ✗ Bookings query: Failed - " . $e->getMessage() . "\n";
            $this->test_results['database']['bookings_query'] = false;
        }
        
        echo "\n";
    }
    
    /**
     * Test API response modification
     */
    private function test_api_response_modification()
    {
        echo "10. Testing API Response Modification\n";
        echo "-------------------------------------\n";
        
        // Create a mock request
        $request = new WP_REST_Request('GET', '/timetics/v1/bookings/entries');
        $request->set_param('staff_id', 1);
        $request->set_param('meeting_id', 1);
        $request->set_param('start_date', date('Y-m-d'));
        $request->set_param('end_date', date('Y-m-d', strtotime('+7 days')));
        $request->set_param('timezone', 'UTC');
        
        // Test the filter hook
        $test_data = [
            'today' => date('Y-m-d'),
            'availability_timezone' => 'UTC',
            'days' => []
        ];
        
        $modified_data = apply_filters('timetics/admin/booking/get_entries', $test_data);
        
        if (isset($modified_data['blocked_dates'])) {
            echo "   ✓ Blocked dates added to response: " . count($modified_data['blocked_dates']) . " dates\n";
            
            if (!empty($modified_data['blocked_dates'])) {
                echo "   ✓ Sample blocked dates:\n";
                foreach (array_slice($modified_data['blocked_dates'], 0, 3) as $date) {
                    echo "     - $date\n";
                }
            } else {
                echo "   ⚠ No blocked dates found\n";
            }
            
            $this->test_results['api']['response_modification'] = true;
        } else {
            echo "   ✗ Blocked dates not added to response\n";
            $this->test_results['api']['response_modification'] = false;
        }
        
        echo "\n";
    }
    
    /**
     * Display test summary
     */
    private function display_summary()
    {
        echo "📊 Test Summary\n";
        echo "===============\n\n";
        
        $total_tests = 0;
        $passed_tests = 0;
        
        foreach ($this->test_results as $category => $tests) {
            foreach ($tests as $test => $result) {
                $total_tests++;
                if ($result) {
                    $passed_tests++;
                }
            }
        }
        
        $success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 1) : 0;
        
        echo "Total Tests: $total_tests\n";
        echo "Passed: $passed_tests\n";
        echo "Success Rate: $success_rate%\n\n";
        
        if ($success_rate >= 80) {
            echo "✅ OVERALL STATUS: EXCELLENT\n";
        } elseif ($success_rate >= 60) {
            echo "⚠️ OVERALL STATUS: GOOD (Some issues to address)\n";
        } else {
            echo "❌ OVERALL STATUS: NEEDS ATTENTION\n";
        }
        
        echo "\n";
        
        // Detailed results
        echo "Detailed Results:\n";
        echo "----------------\n";
        
        foreach ($this->test_results as $category => $tests) {
            echo "\n$category:\n";
            foreach ($tests as $test => $result) {
                $status = $result ? "✓" : "✗";
                echo "  $status $test\n";
            }
        }
        
        echo "\n";
        
        // Next steps
        echo "Next Steps:\n";
        echo "-----------\n";
        echo "1. If success rate < 80%, check the failed tests above\n";
        echo "2. Run the web test script: test-simple-blocker.php\n";
        echo "3. Test the actual API endpoint with a real request\n";
        echo "4. Check debug logs for any error messages\n";
        echo "5. Verify blocked dates appear in the frontend\n\n";
    }
}

// Run the tests
$tester = new TimeticsSimpleBlockerCLITest();
$tester->run_all_tests();

echo "Testing complete! 🎉\n";
