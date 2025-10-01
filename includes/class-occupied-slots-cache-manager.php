<?php
/**
 * Timetics Occupied Slots Addon - Cache Manager
 * 
 * Senior-level intelligent caching system with 24-hour TTL and smart invalidation
 * 
 * @package Timetics_Occupied_Slots_Addon
 * @version 1.6.3
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsCacheManager
{
    /**
     * Plugin version.
     */
    const VERSION = '1.6.3';

    /**
     * Default cache duration (24 hours).
     */
    const DEFAULT_CACHE_DURATION = 86400;

    /**
     * Cache prefix.
     */
    const CACHE_PREFIX = 'occupied_slots_';

    /**
     * Singleton instance.
     */
    private static $instance = null;

    /**
     * Cache statistics.
     */
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];

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
        // Cleanup expired cache entries daily
        add_action('wp_scheduled_delete', [$this, 'cleanup_expired_cache']);
        
        // Clear cache on plugin deactivation
        register_deactivation_hook(TIMETICS_OCCUPIED_SLOTS_FILE, [$this, 'clear_all']);
    }

    /**
     * Get cached data.
     */
    public function get($key)
    {
        $cache_key = $this->get_cache_key($key);
        
        // Try WordPress object cache first
        $data = wp_cache_get($cache_key, 'occupied_slots');
        if ($data !== false) {
            $this->stats['hits']++;
            $this->log_cache_operation('hit', $key);
            return $data;
        }
        
        // Fallback to transients
        $data = get_transient($cache_key);
        if ($data !== false) {
            $this->stats['hits']++;
            $this->log_cache_operation('hit', $key);
            return $data;
        }
        
        $this->stats['misses']++;
        $this->log_cache_operation('miss', $key);
        return false;
    }

    /**
     * Set cached data.
     */
    public function set($key, $data, $duration = self::DEFAULT_CACHE_DURATION)
    {
        $cache_key = $this->get_cache_key($key);
        
        // Store in WordPress object cache
        wp_cache_set($cache_key, $data, 'occupied_slots', $duration);
        
        // Also store in transients as backup
        set_transient($cache_key, $data, $duration);
        
        $this->stats['sets']++;
        $this->log_cache_operation('set', $key, $duration);
        
        return true;
    }

    /**
     * Delete cached data.
     */
    public function delete($key)
    {
        $cache_key = $this->get_cache_key($key);
        
        // Delete from object cache
        wp_cache_delete($cache_key, 'occupied_slots');
        
        // Delete from transients
        delete_transient($cache_key);
        
        $this->stats['deletes']++;
        $this->log_cache_operation('delete', $key);
        
        return true;
    }

    /**
     * Clear all cache.
     */
    public function clear_all()
    {
        global $wpdb;
        
        // Clear object cache
        wp_cache_flush_group('occupied_slots');
        
        // Clear transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_' . self::CACHE_PREFIX . '%',
            '_transient_timeout_' . self::CACHE_PREFIX . '%'
        ));
        
        $this->log_cache_operation('clear_all');
        
        return true;
    }

    /**
     * Get cache statistics.
     */
    public function get_stats()
    {
        $total_requests = $this->stats['hits'] + $this->stats['misses'];
        $hit_rate = $total_requests > 0 ? ($this->stats['hits'] / $total_requests) * 100 : 0;
        
        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'hit_rate' => round($hit_rate, 2),
            'sets' => $this->stats['sets'],
            'deletes' => $this->stats['deletes'],
            'total_requests' => $total_requests
        ];
    }

    /**
     * Warm cache with frequently accessed data.
     */
    public function warm_cache($staff_id, $meeting_id)
    {
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+30 days'));
        $timezone = wp_timezone_string();
        
        $cache_key = $this->generate_cache_key($staff_id, $meeting_id, $start_date, $end_date, $timezone);
        
        // Check if already cached
        if ($this->get($cache_key) !== false) {
            return true;
        }
        
        // Pre-warm cache by calling the intelligent detector
        if (class_exists('OccupiedSlotsIntelligentDetector')) {
            $detector = OccupiedSlotsIntelligentDetector::get_instance();
            $blocked_dates = $detector->get_intelligent_blocked_dates($staff_id, $meeting_id, $start_date, $end_date, $timezone);
            
            $response = [
                'success' => true,
                'data' => [
                    'days' => [],
                    'blocked_dates' => $blocked_dates,
                    'cache_info' => [
                        'cached_at' => current_time('mysql'),
                        'cache_duration' => self::DEFAULT_CACHE_DURATION,
                        'source' => 'cache_warming'
                    ]
                ]
            ];
            
            $this->set($cache_key, $response, self::DEFAULT_CACHE_DURATION);
            
            $this->log_cache_operation('warm', $cache_key);
        }
        
        return true;
    }

    /**
     * Cleanup expired cache entries.
     */
    public function cleanup_expired_cache()
    {
        global $wpdb;
        
        // Clean up expired transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
            '_transient_timeout_' . self::CACHE_PREFIX . '%',
            time()
        ));
        
        $this->log_cache_operation('cleanup');
        
        return true;
    }

    /**
     * Get cache key with prefix.
     */
    private function get_cache_key($key)
    {
        return self::CACHE_PREFIX . $key;
    }

    /**
     * Generate cache key for bookings.
     */
    private function generate_cache_key($staff_id, $meeting_id, $start_date, $end_date, $timezone)
    {
        return md5($staff_id . '_' . $meeting_id . '_' . $start_date . '_' . $end_date . '_' . $timezone);
    }

    /**
     * Log cache operations.
     */
    private function log_cache_operation($operation, $key = '', $duration = 0)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $message = "OccupiedSlotsCacheManager: {$operation}";
            if ($key) {
                $message .= " - {$key}";
            }
            if ($duration > 0) {
                $message .= " - TTL: {$duration}s";
            }
            error_log($message);
        }
    }

    /**
     * Get cache size in bytes.
     */
    public function get_cache_size()
    {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::CACHE_PREFIX . '%'
        ));
        
        return $result ? (int) $result : 0;
    }

    /**
     * Get cache entries count.
     */
    public function get_cache_entries_count()
    {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::CACHE_PREFIX . '%'
        ));
        
        return $result ? (int) $result : 0;
    }

    /**
     * Optimize cache performance.
     */
    public function optimize_cache()
    {
        // Clean up expired entries
        $this->cleanup_expired_cache();
        
        // Clear object cache to free memory
        wp_cache_flush_group('occupied_slots');
        
        $this->log_cache_operation('optimize');
        
        return true;
    }
}
