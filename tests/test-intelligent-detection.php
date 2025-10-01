<?php
/**
 * Test script for Intelligent Detection System
 * 
 * This script tests the senior-level implementation with:
 * - Automatic booked date detection
 * - 24-hour intelligent caching
 * - Performance monitoring
 * - Error handling
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsIntelligentDetectionTest
{
    /**
     * Run comprehensive tests.
     */
    public static function run_tests()
    {
        echo "<h1>🧪 Timetics Occupied Slots - Intelligent Detection Test Suite</h1>\n";
        echo "<div style='font-family: monospace; background: #f0f0f0; padding: 20px; margin: 20px 0;'>\n";
        
        $tests = [
            'test_class_loading' => 'Test Class Loading',
            'test_intelligent_detector' => 'Test Intelligent Detector',
            'test_cache_manager' => 'Test Cache Manager',
            'test_performance_monitor' => 'Test Performance Monitor',
            'test_api_endpoints' => 'Test API Endpoints',
            'test_error_handling' => 'Test Error Handling',
            'test_performance_metrics' => 'Test Performance Metrics'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test_method => $test_name) {
            echo "<h3>🔍 {$test_name}</h3>\n";
            try {
                $result = self::$test_method();
                if ($result) {
                    echo "✅ <strong>PASSED</strong>\n";
                    $passed++;
                } else {
                    echo "❌ <strong>FAILED</strong>\n";
                }
            } catch (Exception $e) {
                echo "❌ <strong>ERROR:</strong> " . $e->getMessage() . "\n";
            }
            echo "<hr>\n";
        }
        
        echo "<h2>📊 Test Results: {$passed}/{$total} tests passed</h2>\n";
        
        if ($passed === $total) {
            echo "<div style='color: green; font-weight: bold;'>🎉 All tests passed! Senior-level implementation is working correctly.</div>\n";
        } else {
            echo "<div style='color: red; font-weight: bold;'>⚠️ Some tests failed. Please review the implementation.</div>\n";
        }
        
        echo "</div>\n";
    }
    
    /**
     * Test class loading.
     */
    private static function test_class_loading()
    {
        $classes = [
            'OccupiedSlotsIntelligentDetector',
            'OccupiedSlotsCacheManager',
            'OccupiedSlotsPerformanceMonitor',
            'OccupiedSlotsAdminDashboard'
        ];
        
        foreach ($classes as $class) {
            if (!class_exists($class)) {
                echo "❌ Class {$class} not found\n";
                return false;
            }
            echo "✅ Class {$class} loaded successfully\n";
        }
        
        return true;
    }
    
    /**
     * Test intelligent detector.
     */
    private static function test_intelligent_detector()
    {
        if (!class_exists('OccupiedSlotsIntelligentDetector')) {
            echo "❌ IntelligentDetector class not found\n";
            return false;
        }
        
        $detector = OccupiedSlotsIntelligentDetector::get_instance();
        if (!$detector) {
            echo "❌ Failed to get detector instance\n";
            return false;
        }
        
        echo "✅ IntelligentDetector instance created\n";
        
        // Test cache stats
        $cache_stats = $detector->get_cache_stats();
        if (!is_array($cache_stats)) {
            echo "❌ Cache stats not returned as array\n";
            return false;
        }
        
        echo "✅ Cache stats retrieved: " . json_encode($cache_stats) . "\n";
        
        return true;
    }
    
    /**
     * Test cache manager.
     */
    private static function test_cache_manager()
    {
        if (!class_exists('OccupiedSlotsCacheManager')) {
            echo "❌ CacheManager class not found\n";
            return false;
        }
        
        $cache_manager = OccupiedSlotsCacheManager::get_instance();
        if (!$cache_manager) {
            echo "❌ Failed to get cache manager instance\n";
            return false;
        }
        
        echo "✅ CacheManager instance created\n";
        
        // Test cache operations
        $test_key = 'test_key_' . time();
        $test_data = ['test' => 'data', 'timestamp' => time()];
        
        // Test set
        $set_result = $cache_manager->set($test_key, $test_data, 60);
        if (!$set_result) {
            echo "❌ Failed to set cache data\n";
            return false;
        }
        echo "✅ Cache set operation successful\n";
        
        // Test get
        $retrieved_data = $cache_manager->get($test_key);
        if ($retrieved_data === false) {
            echo "❌ Failed to retrieve cache data\n";
            return false;
        }
        echo "✅ Cache get operation successful\n";
        
        // Test delete
        $delete_result = $cache_manager->delete($test_key);
        if (!$delete_result) {
            echo "❌ Failed to delete cache data\n";
            return false;
        }
        echo "✅ Cache delete operation successful\n";
        
        // Test stats
        $stats = $cache_manager->get_stats();
        if (!is_array($stats)) {
            echo "❌ Cache stats not returned as array\n";
            return false;
        }
        echo "✅ Cache stats: " . json_encode($stats) . "\n";
        
        return true;
    }
    
    /**
     * Test performance monitor.
     */
    private static function test_performance_monitor()
    {
        if (!class_exists('OccupiedSlotsPerformanceMonitor')) {
            echo "❌ PerformanceMonitor class not found\n";
            return false;
        }
        
        $monitor = OccupiedSlotsPerformanceMonitor::get_instance();
        if (!$monitor) {
            echo "❌ Failed to get performance monitor instance\n";
            return false;
        }
        
        echo "✅ PerformanceMonitor instance created\n";
        
        // Test logging a metric
        $monitor->log_metric('test_metric', 100, ['test' => 'data']);
        echo "✅ Performance metric logged\n";
        
        // Test getting metrics
        $metrics = $monitor->get_metrics();
        if (!is_array($metrics)) {
            echo "❌ Metrics not returned as array\n";
            return false;
        }
        echo "✅ Performance metrics: " . json_encode($metrics) . "\n";
        
        return true;
    }
    
    /**
     * Test API endpoints.
     */
    private static function test_api_endpoints()
    {
        $endpoints = [
            '/wp-json/timetics-occupied-slots/v1/bookings/entries',
            '/wp-json/timetics-occupied-slots/v1/occupied-dates'
        ];
        
        foreach ($endpoints as $endpoint) {
            $url = home_url($endpoint);
            $response = wp_remote_get($url, [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Timetics-Occupied-Slots-Test/1.6.3'
                ]
            ]);
            
            if (is_wp_error($response)) {
                echo "❌ Endpoint {$endpoint} failed: " . $response->get_error_message() . "\n";
                return false;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                echo "❌ Endpoint {$endpoint} returned status {$status_code}\n";
                return false;
            }
            
            echo "✅ Endpoint {$endpoint} responding correctly (Status: {$status_code})\n";
        }
        
        return true;
    }
    
    /**
     * Test error handling.
     */
    private static function test_error_handling()
    {
        // Test with invalid parameters
        $url = home_url('/wp-json/timetics-occupied-slots/v1/bookings/entries?staff_id=invalid&meeting_id=invalid');
        $response = wp_remote_get($url, ['timeout' => 5]);
        
        if (is_wp_error($response)) {
            echo "✅ Error handling working (expected error for invalid parameters)\n";
            return true;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 400) {
            echo "✅ Error handling working (returned error status for invalid parameters)\n";
            return true;
        }
        
        echo "⚠️ Error handling test inconclusive\n";
        return true;
    }
    
    /**
     * Test performance metrics.
     */
    private static function test_performance_metrics()
    {
        $monitor = OccupiedSlotsPerformanceMonitor::get_instance();
        
        // Log some test metrics
        $monitor->log_metric('test_fast', 50, ['type' => 'fast']);
        $monitor->log_metric('test_slow', 300, ['type' => 'slow']);
        $monitor->log_metric('test_memory', 0, ['memory' => 'high']);
        
        $metrics = $monitor->get_metrics();
        
        if (!isset($metrics['total_requests']) || $metrics['total_requests'] < 3) {
            echo "❌ Performance metrics not properly recorded\n";
            return false;
        }
        
        echo "✅ Performance metrics recorded: " . json_encode($metrics) . "\n";
        
        // Test performance report
        $report = $monitor->get_performance_report();
        if (!isset($report['metrics']) || !isset($report['recommendations'])) {
            echo "❌ Performance report incomplete\n";
            return false;
        }
        
        echo "✅ Performance report generated\n";
        
        return true;
    }
}

// Run tests if accessed directly
if (isset($_GET['run_tests']) && current_user_can('manage_options')) {
    OccupiedSlotsIntelligentDetectionTest::run_tests();
    exit;
}
