<?php
/**
 * Performance Testing Script for Timetics Occupied Slots Addon
 * 
 * This script tests the performance improvements of the optimized plugin
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsPerformanceTest
{
    /**
     * Test data for performance testing.
     */
    private $test_data = [];
    
    /**
     * Performance metrics.
     */
    private $metrics = [];
    
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->generate_test_data();
    }
    
    /**
     * Generate test data.
     */
    private function generate_test_data()
    {
        // Generate test days with slots
        $this->test_data = [
            'days' => [],
            'staff_id' => 1,
            'meeting_id' => 1,
            'timezone' => 'UTC'
        ];
        
        // Generate 30 days of test data
        for ($i = 0; $i < 30; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            $slots = [];
            
            // Generate 20 slots per day
            for ($j = 9; $j < 17; $j++) {
                $slots[] = [
                    'start_time' => sprintf('%02d:00:00', $j),
                    'end_time' => sprintf('%02d:00:00', $j + 1),
                    'status' => 'available',
                    'booked' => 0
                ];
            }
            
            $this->test_data['days'][] = [
                'date' => $date,
                'slots' => $slots,
                'status' => 'available'
            ];
        }
    }
    
    /**
     * Run performance tests.
     */
    public function run_tests()
    {
        echo "<h2>🚀 Timetics Occupied Slots Performance Test</h2>\n";
        echo "<p>Testing performance improvements in version 1.3.0...</p>\n";
        
        // Test 1: Cache Performance
        $this->test_cache_performance();
        
        // Test 2: Logging Performance
        $this->test_logging_performance();
        
        // Test 3: Core Processing Performance
        $this->test_core_processing_performance();
        
        // Test 4: Google Calendar Performance
        $this->test_google_calendar_performance();
        
        // Test 5: Memory Usage
        $this->test_memory_usage();
        
        // Display results
        $this->display_results();
    }
    
    /**
     * Test cache performance.
     */
    private function test_cache_performance()
    {
        echo "<h3>📊 Cache Performance Test</h3>\n";
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);
        
        // Test cache operations
        for ($i = 0; $i < 1000; $i++) {
            OccupiedSlotsCache::get_option('timetics_occupied_slots_enabled', true);
            OccupiedSlotsCache::get_google_calendar_overlap();
        }
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        $this->metrics['cache'] = [
            'duration' => round(($end_time - $start_time) * 1000, 2),
            'memory_used' => $end_memory - $start_memory,
            'operations' => 2000
        ];
        
        echo "<p>✅ Cache Performance: {$this->metrics['cache']['duration']}ms for {$this->metrics['cache']['operations']} operations</p>\n";
    }
    
    /**
     * Test logging performance.
     */
    private function test_logging_performance()
    {
        echo "<h3>📝 Logging Performance Test</h3>\n";
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);
        
        // Test logging operations
        for ($i = 0; $i < 100; $i++) {
            OccupiedSlotsLogger::debug("Test log message {$i}", ['iteration' => $i]);
            OccupiedSlotsLogger::info("Test info message {$i}", ['iteration' => $i]);
        }
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        $this->metrics['logging'] = [
            'duration' => round(($end_time - $start_time) * 1000, 2),
            'memory_used' => $end_memory - $start_memory,
            'operations' => 200
        ];
        
        echo "<p>✅ Logging Performance: {$this->metrics['logging']['duration']}ms for {$this->metrics['logging']['operations']} operations</p>\n";
    }
    
    /**
     * Test core processing performance.
     */
    private function test_core_processing_performance()
    {
        echo "<h3>⚙️ Core Processing Performance Test</h3>\n";
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);
        
        // Test core processing
        $core = OccupiedSlotsCoreOptimized::get_instance();
        
        // Process test data multiple times
        for ($i = 0; $i < 10; $i++) {
            $result = $core->process_schedule_data(
                $this->test_data['days'],
                $this->test_data['staff_id'],
                $this->test_data['meeting_id'],
                $this->test_data['timezone']
            );
        }
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        $this->metrics['core_processing'] = [
            'duration' => round(($end_time - $start_time) * 1000, 2),
            'memory_used' => $end_memory - $start_memory,
            'iterations' => 10,
            'total_slots' => count($this->test_data['days']) * 8 * 10 // 30 days * 8 slots * 10 iterations
        ];
        
        echo "<p>✅ Core Processing: {$this->metrics['core_processing']['duration']}ms for {$this->metrics['core_processing']['total_slots']} slots</p>\n";
    }
    
    /**
     * Test Google Calendar performance.
     */
    private function test_google_calendar_performance()
    {
        echo "<h3>📅 Google Calendar Performance Test</h3>\n";
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);
        
        // Test Google Calendar operations
        $google_calendar = OccupiedSlotsGoogleCalendarOptimized::get_instance();
        
        // Test conflict checking
        for ($i = 0; $i < 50; $i++) {
            $slot = [
                'start_time' => '10:00:00',
                'end_time' => '11:00:00'
            ];
            
            $google_calendar->check_google_calendar_conflict_for_slot(
                $slot,
                1,
                1,
                'UTC',
                date('Y-m-d')
            );
        }
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        $this->metrics['google_calendar'] = [
            'duration' => round(($end_time - $start_time) * 1000, 2),
            'memory_used' => $end_memory - $start_memory,
            'operations' => 50
        ];
        
        echo "<p>✅ Google Calendar: {$this->metrics['google_calendar']['duration']}ms for {$this->metrics['google_calendar']['operations']} operations</p>\n";
    }
    
    /**
     * Test memory usage.
     */
    private function test_memory_usage()
    {
        echo "<h3>💾 Memory Usage Test</h3>\n";
        
        $start_memory = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        
        // Get performance metrics from all components
        $cache_stats = OccupiedSlotsCache::get_cache_stats();
        $logger_metrics = OccupiedSlotsLogger::get_performance_metrics();
        
        $this->metrics['memory'] = [
            'current_usage' => $start_memory,
            'peak_usage' => $peak_memory,
            'cache_stats' => $cache_stats,
            'logger_metrics' => $logger_metrics
        ];
        
        echo "<p>✅ Memory Usage: " . round($start_memory / 1024 / 1024, 2) . "MB current, " . round($peak_memory / 1024 / 1024, 2) . "MB peak</p>\n";
    }
    
    /**
     * Display test results.
     */
    private function display_results()
    {
        echo "<h3>📈 Performance Test Results</h3>\n";
        echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
        echo "<tr><th>Component</th><th>Duration (ms)</th><th>Memory (MB)</th><th>Operations</th><th>Performance</th></tr>\n";
        
        foreach ($this->metrics as $component => $data) {
            if (isset($data['duration'])) {
                $memory_mb = round($data['memory_used'] / 1024 / 1024, 2);
                $performance = $this->calculate_performance_score($data);
                
                echo "<tr>";
                echo "<td>" . ucfirst(str_replace('_', ' ', $component)) . "</td>";
                echo "<td>{$data['duration']}</td>";
                echo "<td>{$memory_mb}</td>";
                echo "<td>" . ($data['operations'] ?? $data['iterations'] ?? 'N/A') . "</td>";
                echo "<td>{$performance}/10</td>";
                echo "</tr>\n";
            }
        }
        
        echo "</table>\n";
        
        // Overall performance score
        $overall_score = $this->calculate_overall_performance_score();
        echo "<h3>🎯 Overall Performance Score: {$overall_score}/10</h3>\n";
        
        if ($overall_score >= 8) {
            echo "<p style='color: green; font-weight: bold;'>🚀 Excellent performance! The optimizations are working well.</p>\n";
        } elseif ($overall_score >= 6) {
            echo "<p style='color: orange; font-weight: bold;'>⚡ Good performance! Some optimizations are working.</p>\n";
        } else {
            echo "<p style='color: red; font-weight: bold;'>⚠️ Performance needs improvement. Consider additional optimizations.</p>\n";
        }
    }
    
    /**
     * Calculate performance score for a component.
     */
    private function calculate_performance_score($data)
    {
        $score = 10;
        
        // Penalize for high duration
        if ($data['duration'] > 1000) $score -= 3;
        elseif ($data['duration'] > 500) $score -= 2;
        elseif ($data['duration'] > 100) $score -= 1;
        
        // Penalize for high memory usage
        $memory_mb = $data['memory_used'] / 1024 / 1024;
        if ($memory_mb > 10) $score -= 3;
        elseif ($memory_mb > 5) $score -= 2;
        elseif ($memory_mb > 1) $score -= 1;
        
        return max(0, $score);
    }
    
    /**
     * Calculate overall performance score.
     */
    private function calculate_overall_performance_score()
    {
        $scores = [];
        
        foreach ($this->metrics as $component => $data) {
            if (isset($data['duration'])) {
                $scores[] = $this->calculate_performance_score($data);
            }
        }
        
        return round(array_sum($scores) / count($scores), 1);
    }
}

// Run the performance test if accessed directly
if (isset($_GET['run_performance_test']) && current_user_can('manage_options')) {
    $test = new OccupiedSlotsPerformanceTest();
    $test->run_tests();
}
