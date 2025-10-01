<?php
/**
 * Timetics Occupied Slots Addon - Performance Monitor
 * 
 * Senior-level performance monitoring with real-time metrics and optimization
 * 
 * @package Timetics_Occupied_Slots_Addon
 * @version 1.6.3
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsPerformanceMonitor
{
    /**
     * Plugin version.
     */
    const VERSION = '1.6.3';

    /**
     * Performance thresholds.
     */
    const SLOW_QUERY_THRESHOLD = 200; // milliseconds
    const HIGH_MEMORY_THRESHOLD = 10; // MB
    const HIGH_CPU_THRESHOLD = 80; // percentage

    /**
     * Singleton instance.
     */
    private static $instance = null;

    /**
     * Performance metrics.
     */
    private $metrics = [];

    /**
     * Performance history.
     */
    private $history = [];
    
    /**
     * Get singleton instance.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor.
     */
    private function __construct()
    {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks.
     */
    private function init_hooks()
    {
        // Monitor WordPress performance
        add_action('wp_footer', [$this, 'log_page_performance']);
        add_action('admin_footer', [$this, 'log_page_performance']);
        
        // Monitor memory usage
        add_action('wp_loaded', [$this, 'log_memory_usage']);
        
        // Monitor database queries
        add_action('shutdown', [$this, 'log_database_performance']);
    }

    /**
     * Log performance metric.
     */
    public function log_metric($type, $execution_time, $context = [])
    {
        $metric = [
            'type' => $type,
            'execution_time' => $execution_time,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => current_time('mysql'),
            'context' => $context
        ];

        $this->metrics[] = $metric;
        $this->history[] = $metric;

        // Keep only last 100 metrics in memory
        if (count($this->history) > 100) {
            array_shift($this->history);
        }

        // Log slow queries
        if ($execution_time > self::SLOW_QUERY_THRESHOLD) {
            $this->log_slow_query($metric);
        }

        // Log high memory usage
        $memory_mb = $metric['memory_usage'] / 1024 / 1024;
        if ($memory_mb > self::HIGH_MEMORY_THRESHOLD) {
            $this->log_high_memory_usage($metric);
        }
    }

    /**
     * Get performance metrics.
     */
    public function get_metrics()
    {
        $total_requests = count($this->history);
        $avg_execution_time = $this->get_average_execution_time();
        $avg_memory_usage = $this->get_average_memory_usage();
        $slow_queries = $this->get_slow_queries_count();
        $cache_stats = $this->get_cache_stats();

        return [
            'total_requests' => $total_requests,
            'average_execution_time' => round($avg_execution_time, 2),
            'average_memory_usage' => round($avg_memory_usage, 2),
            'slow_queries' => $slow_queries,
            'cache_hit_rate' => $cache_stats['hit_rate'] ?? 0,
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'current_memory' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ];
    }

    /**
     * Get average execution time.
     */
    private function get_average_execution_time()
    {
        if (empty($this->history)) {
            return 0;
        }

        $total_time = array_sum(array_column($this->history, 'execution_time'));
        return $total_time / count($this->history);
    }

    /**
     * Get average memory usage.
     */
    private function get_average_memory_usage()
    {
        if (empty($this->history)) {
            return 0;
        }

        $total_memory = array_sum(array_column($this->history, 'memory_usage'));
        return ($total_memory / count($this->history)) / 1024 / 1024; // Convert to MB
    }

    /**
     * Get slow queries count.
     */
    private function get_slow_queries_count()
    {
        return count(array_filter($this->history, function($metric) {
            return $metric['execution_time'] > self::SLOW_QUERY_THRESHOLD;
        }));
    }

    /**
     * Get cache statistics.
     */
    private function get_cache_stats()
    {
        if (class_exists('OccupiedSlotsCacheManager')) {
            $cache_manager = OccupiedSlotsCacheManager::get_instance();
            return $cache_manager->get_stats();
        }

        return ['hit_rate' => 0];
    }

    /**
     * Log slow query.
     */
    private function log_slow_query($metric)
    {
        $message = sprintf(
            'Slow query detected: %sms execution time for %s',
            $metric['execution_time'],
            $metric['type']
        );

        if (class_exists('OccupiedSlotsLogger')) {
            OccupiedSlotsLogger::warning($message, $metric);
        } else {
            error_log("OccupiedSlotsPerformanceMonitor: {$message}");
        }
    }
    
    /**
     * Log high memory usage.
     */
    private function log_high_memory_usage($metric)
    {
        $memory_mb = $metric['memory_usage'] / 1024 / 1024;
        $message = sprintf(
            'High memory usage detected: %sMB for %s',
            round($memory_mb, 2),
            $metric['type']
        );

        if (class_exists('OccupiedSlotsLogger')) {
            OccupiedSlotsLogger::warning($message, $metric);
        } else {
            error_log("OccupiedSlotsPerformanceMonitor: {$message}");
        }
    }

    /**
     * Log page performance.
     */
    public function log_page_performance()
    {
        $execution_time = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;
        $memory_usage = memory_get_usage(true) / 1024 / 1024;

        $this->log_metric('page_load', $execution_time, [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'memory_usage_mb' => round($memory_usage, 2)
        ]);
    }

    /**
     * Log memory usage.
     */
    public function log_memory_usage()
    {
        $memory_usage = memory_get_usage(true) / 1024 / 1024;
        
        if ($memory_usage > self::HIGH_MEMORY_THRESHOLD) {
            $this->log_metric('high_memory', 0, [
                'memory_usage_mb' => round($memory_usage, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
            ]);
        }
    }

    /**
     * Log database performance.
     */
    public function log_database_performance()
    {
        global $wpdb;
        
        if (isset($wpdb->queries) && is_array($wpdb->queries)) {
            $slow_queries = array_filter($wpdb->queries, function($query) {
                return $query[1] > self::SLOW_QUERY_THRESHOLD / 1000; // Convert to seconds
            });

            if (!empty($slow_queries)) {
                $this->log_metric('slow_database_queries', 0, [
                    'slow_queries_count' => count($slow_queries),
                    'total_queries' => count($wpdb->queries)
                ]);
            }
        }
    }

    /**
     * Get performance report.
     */
    public function get_performance_report()
    {
        $metrics = $this->get_metrics();
        $recommendations = $this->get_performance_recommendations($metrics);

        return [
            'metrics' => $metrics,
            'recommendations' => $recommendations,
            'timestamp' => current_time('mysql')
        ];
    }

    /**
     * Get performance recommendations.
     */
    private function get_performance_recommendations($metrics)
    {
        $recommendations = [];

        if ($metrics['average_execution_time'] > self::SLOW_QUERY_THRESHOLD) {
            $recommendations[] = 'Consider enabling more aggressive caching to reduce execution time';
        }

        if ($metrics['average_memory_usage'] > self::HIGH_MEMORY_THRESHOLD) {
            $recommendations[] = 'Consider optimizing memory usage or increasing PHP memory limit';
        }

        if ($metrics['cache_hit_rate'] < 80) {
            $recommendations[] = 'Cache hit rate is low. Consider warming cache or increasing cache duration';
        }

        if ($metrics['slow_queries'] > 0) {
            $recommendations[] = 'Slow queries detected. Consider database optimization';
        }
        
        return $recommendations;
    }
    
    /**
     * Clear performance history.
     */
    public function clear_history()
    {
        $this->history = [];
        $this->metrics = [];
    }

    /**
     * Export performance data.
     */
    public function export_performance_data()
    {
        return [
            'metrics' => $this->get_metrics(),
            'history' => $this->history,
            'exported_at' => current_time('mysql')
        ];
    }
}