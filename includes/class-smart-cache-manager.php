<?php
/**
 * Smart Cache Manager for Occupied Slots
 * 
 * Provides intelligent caching with automatic invalidation
 * to ensure 100% accuracy while minimizing API calls.
 * 
 * @package Timetics_Occupied_Slots_Addon
 */

class Smart_Cache_Manager {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('timetics_after_booking_schedule', array($this, 'invalidate_booking_cache'), 10, 4);
        add_action('timetics_booking_updated', array($this, 'invalidate_booking_cache'), 10, 2);
        add_action('timetics_booking_deleted', array($this, 'invalidate_booking_cache'), 10, 1);
    }
    
    /**
     * Get cached blocked dates with smart invalidation
     */
    public function get_cached_blocked_dates($start_date, $end_date) {
        $cache_key = $this->generate_cache_key($start_date, $end_date);
        
        // Check if cache exists and is valid
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            // Verify cache is still valid
            if ($this->is_cache_valid($cached_data)) {
                return $cached_data['blocked_dates'];
            } else {
                // Cache is stale, delete it
                delete_transient($cache_key);
            }
        }
        
        return false; // Cache miss
    }
    
    /**
     * Set cached blocked dates with metadata
     */
    public function set_cached_blocked_dates($start_date, $end_date, $blocked_dates) {
        $cache_key = $this->generate_cache_key($start_date, $end_date);
        
        $cache_data = [
            'blocked_dates' => $blocked_dates,
            'timestamp' => time(),
            'version' => $this->get_cache_version(),
            'source' => 'api'
        ];
        
        // Cache for 30 minutes by default
        $cache_duration = apply_filters('timetics_occupied_slots_cache_duration', 30 * MINUTE_IN_SECONDS);
        set_transient($cache_key, $cache_data, $cache_duration);
    }
    
    /**
     * Generate cache key for date range
     */
    private function generate_cache_key($start_date, $end_date) {
        return 'timetics_blocked_dates_' . md5($start_date . '_' . $end_date);
    }
    
    /**
     * Check if cache is still valid
     */
    private function is_cache_valid($cached_data) {
        $current_version = $this->get_cache_version();
        $cache_version = $cached_data['version'] ?? 0;
        
        // Cache is invalid if version changed
        if ($cache_version !== $current_version) {
            return false;
        }
        
        // Cache is invalid if older than 15 minutes
        $max_age = 15 * MINUTE_IN_SECONDS;
        if (time() - $cached_data['timestamp'] > $max_age) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get current cache version (changes when bookings change)
     */
    private function get_cache_version() {
        $version = get_option('timetics_occupied_slots_cache_version', 0);
        return $version;
    }
    
    /**
     * Invalidate cache when bookings change
     */
    public function invalidate_booking_cache($booking_id = null, $customer_id = null, $meeting_id = null, $data = null) {
        // Increment cache version to invalidate all caches
        $current_version = get_option('timetics_occupied_slots_cache_version', 0);
        update_option('timetics_occupied_slots_cache_version', $current_version + 1);
        
        // Clear specific caches
        $this->clear_date_range_caches();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Timetics Occupied Slots: Cache invalidated for booking ' . $booking_id);
        }
    }
    
    /**
     * Clear all date range caches
     */
    private function clear_date_range_caches() {
        global $wpdb;
        
        // Clear all occupied slots caches
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timetics_blocked_dates_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_timetics_blocked_dates_%'");
        
        // Clear Google Calendar caches
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timetics_google_calendar_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_timetics_google_calendar_%'");
    }
    
    /**
     * Force refresh cache for specific date range
     */
    public function force_refresh_cache($start_date, $end_date) {
        $cache_key = $this->generate_cache_key($start_date, $end_date);
        delete_transient($cache_key);
        
        // Also clear Google Calendar cache
        delete_transient('timetics_google_calendar_blocked_dates_' . date('Y-m-d'));
    }
    
    /**
     * Get cache statistics
     */
    public function get_cache_stats() {
        global $wpdb;
        
        $stats = [
            'total_caches' => 0,
            'google_calendar_caches' => 0,
            'booking_caches' => 0,
            'oldest_cache' => null,
            'newest_cache' => null
        ];
        
        // Count different types of caches
        $results = $wpdb->get_results("
            SELECT option_name, option_value 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_timetics_%' 
            AND option_name NOT LIKE '%timeout%'
        ");
        
        foreach ($results as $result) {
            $stats['total_caches']++;
            
            if (strpos($result->option_name, 'google_calendar') !== false) {
                $stats['google_calendar_caches']++;
            } elseif (strpos($result->option_name, 'blocked_dates') !== false) {
                $stats['booking_caches']++;
            }
        }
        
        return $stats;
    }
}
