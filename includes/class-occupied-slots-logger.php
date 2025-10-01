<?php
/**
 * Timetics Occupied Slots Addon - Performance Logger
 * 
 * Handles optimized logging for performance
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsLogger
{
    /**
     * Log levels.
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    
    /**
     * Singleton instance.
     */
    private static $instance = null;
    
    /**
     * Log buffer for batch processing.
     */
    private static $log_buffer = [];
    
    /**
     * Performance metrics.
     */
    private static $performance_metrics = [];
    
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
        // Flush logs on shutdown
        add_action('shutdown', [$this, 'flush_logs']);
    }
    
    /**
     * Log debug message (only in debug mode).
     */
    public static function debug($message, $context = [])
    {
        if (!self::should_log(self::LEVEL_DEBUG)) {
            return;
        }
        
        self::log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log info message.
     */
    public static function info($message, $context = [])
    {
        if (!self::should_log(self::LEVEL_INFO)) {
            return;
        }
        
        self::log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log warning message.
     */
    public static function warning($message, $context = [])
    {
        if (!self::should_log(self::LEVEL_WARNING)) {
            return;
        }
        
        self::log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log error message.
     */
    public static function error($message, $context = [])
    {
        if (!self::should_log(self::LEVEL_ERROR)) {
            return;
        }
        
        self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Check if we should log at this level.
     */
    private static function should_log($level)
    {
        // Only log in debug mode for debug level
        if ($level === self::LEVEL_DEBUG) {
            return defined('WP_DEBUG') && WP_DEBUG;
        }
        
        // Always log errors and warnings
        if (in_array($level, [self::LEVEL_ERROR, self::LEVEL_WARNING])) {
            return true;
        }
        
        // Log info only in debug mode
        if ($level === self::LEVEL_INFO) {
            return defined('WP_DEBUG') && WP_DEBUG;
        }
        
        return false;
    }
    
    /**
     * Add log entry to buffer.
     */
    private static function log($level, $message, $context = [])
    {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        self::$log_buffer[] = $log_entry;
        
        // Flush immediately for errors
        if ($level === self::LEVEL_ERROR) {
            self::flush_logs();
        }
    }
    
    /**
     * Flush log buffer.
     */
    public function flush_logs()
    {
        if (empty(self::$log_buffer)) {
            return;
        }
        
        foreach (self::$log_buffer as $entry) {
            $formatted_message = self::format_log_entry($entry);
            error_log($formatted_message);
        }
        
        self::$log_buffer = [];
    }
    
    /**
     * Format log entry.
     */
    private static function format_log_entry($entry)
    {
        $context_str = !empty($entry['context']) ? ' ' . json_encode($entry['context']) : '';
        return "[TIMETICS_OCCUPIED_{$entry['level']}] {$entry['message']}{$context_str}";
    }
    
    /**
     * Start performance timing.
     */
    public static function start_timer($operation)
    {
        self::$performance_metrics[$operation] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true)
        ];
    }
    
    /**
     * End performance timing.
     */
    public static function end_timer($operation)
    {
        if (!isset(self::$performance_metrics[$operation])) {
            return;
        }
        
        $start = self::$performance_metrics[$operation];
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        $duration = $end_time - $start['start_time'];
        $memory_used = $end_memory - $start['start_memory'];
        
        self::debug("Performance: {$operation}", [
            'duration_ms' => round($duration * 1000, 2),
            'memory_used_bytes' => $memory_used,
            'memory_used_mb' => round($memory_used / 1024 / 1024, 2)
        ]);
        
        unset(self::$performance_metrics[$operation]);
    }
    
    /**
     * Log slot processing performance.
     */
    public static function log_slot_processing($slots_count, $processed_count, $filtered_count)
    {
        self::info('Slot processing completed', [
            'total_slots' => $slots_count,
            'processed_slots' => $processed_count,
            'filtered_slots' => $filtered_count,
            'processing_rate' => $slots_count > 0 ? round(($processed_count / $slots_count) * 100, 2) . '%' : '0%'
        ]);
    }
    
    /**
     * Log Google Calendar performance.
     */
    public static function log_google_calendar_performance($staff_id, $date, $events_count, $conflicts_found)
    {
        self::debug('Google Calendar processing', [
            'staff_id' => $staff_id,
            'date' => $date,
            'events_count' => $events_count,
            'conflicts_found' => $conflicts_found
        ]);
    }
    
    /**
     * Get performance metrics.
     */
    public static function get_performance_metrics()
    {
        return [
            'active_timers' => count(self::$performance_metrics),
            'log_buffer_size' => count(self::$log_buffer),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
    
    /**
     * Clear performance metrics.
     */
    public static function clear_metrics()
    {
        self::$performance_metrics = [];
        self::$log_buffer = [];
    }
}
