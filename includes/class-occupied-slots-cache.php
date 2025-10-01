<?php
/**
 * Timetics Occupied Slots Addon - Performance Cache
 * 
 * Handles caching for performance optimization
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsCache
{
    /**
     * Cache group name.
     */
    const CACHE_GROUP = 'timetics_occupied_slots';
    
    /**
     * Cache TTL constants.
     */
    const CACHE_TTL_SHORT = 300; // 5 minutes
    const CACHE_TTL_MEDIUM = 1800; // 30 minutes
    const CACHE_TTL_LONG = 3600; // 1 hour
    
    /**
     * Singleton instance.
     */
    private static $instance = null;
    
    /**
     * In-memory cache for frequently accessed data.
     */
    private static $memory_cache = [];
    
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
        // Initialize cache warming
        add_action('init', [$this, 'warm_cache']);
    }
    
    /**
     * Get cached option value.
     */
    public static function get_option($option_name, $default = false, $ttl = self::CACHE_TTL_MEDIUM)
    {
        $cache_key = "option_{$option_name}";
        
        // Check memory cache first
        if (isset(self::$memory_cache[$cache_key])) {
            return self::$memory_cache[$cache_key];
        }
        
        // Check WordPress object cache
        $cached_value = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (false !== $cached_value) {
            self::$memory_cache[$cache_key] = $cached_value;
            return $cached_value;
        }
        
        // Get from database and cache
        $value = get_option($option_name, $default);
        wp_cache_set($cache_key, $value, self::CACHE_GROUP, $ttl);
        self::$memory_cache[$cache_key] = $value;
        
        return $value;
    }
    
    /**
     * Get cached Timetics option value.
     */
    public static function get_timetics_option($option_name, $default = false, $ttl = self::CACHE_TTL_MEDIUM)
    {
        $cache_key = "timetics_option_{$option_name}";
        
        // Check memory cache first
        if (isset(self::$memory_cache[$cache_key])) {
            return self::$memory_cache[$cache_key];
        }
        
        // Check WordPress object cache
        $cached_value = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (false !== $cached_value) {
            self::$memory_cache[$cache_key] = $cached_value;
            return $cached_value;
        }
        
        // Get from Timetics and cache
        $value = function_exists('timetics_get_option') ? timetics_get_option($option_name, $default) : $default;
        wp_cache_set($cache_key, $value, self::CACHE_GROUP, $ttl);
        self::$memory_cache[$cache_key] = $value;
        
        return $value;
    }
    
    /**
     * Cache Google Calendar overlap setting.
     */
    public static function get_google_calendar_overlap()
    {
        return self::get_timetics_option('google_calendar_overlap', false, self::CACHE_TTL_LONG);
    }
    
    /**
     * Cache plugin enabled setting.
     */
    public static function is_plugin_enabled()
    {
        return self::get_option('timetics_occupied_slots_enabled', true, self::CACHE_TTL_LONG);
    }
    
    /**
     * Cache Google Calendar events for a specific date and staff.
     */
    public static function get_google_calendar_events($staff_id, $date, $timezone)
    {
        $cache_key = "google_events_{$staff_id}_{$date}_{$timezone}";
        
        // Check memory cache first
        if (isset(self::$memory_cache[$cache_key])) {
            return self::$memory_cache[$cache_key];
        }
        
        // Check WordPress object cache
        $cached_events = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (false !== $cached_events) {
            self::$memory_cache[$cache_key] = $cached_events;
            return $cached_events;
        }
        
        // This would be populated by the Google Calendar integration
        // For now, return empty array
        $events = [];
        wp_cache_set($cache_key, $events, self::CACHE_GROUP, self::CACHE_TTL_SHORT);
        self::$memory_cache[$cache_key] = $events;
        
        return $events;
    }
    
    /**
     * Cache processed slot data.
     */
    public static function get_processed_slots($staff_id, $meeting_id, $date)
    {
        $cache_key = "processed_slots_{$staff_id}_{$meeting_id}_{$date}";
        
        // Check memory cache first
        if (isset(self::$memory_cache[$cache_key])) {
            return self::$memory_cache[$cache_key];
        }
        
        // Check WordPress object cache
        $cached_slots = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (false !== $cached_slots) {
            self::$memory_cache[$cache_key] = $cached_slots;
            return $cached_slots;
        }
        
        return null; // Not cached yet
    }
    
    /**
     * Set processed slot data cache.
     */
    public static function set_processed_slots($staff_id, $meeting_id, $date, $slots, $ttl = self::CACHE_TTL_SHORT)
    {
        $cache_key = "processed_slots_{$staff_id}_{$meeting_id}_{$date}";
        
        wp_cache_set($cache_key, $slots, self::CACHE_GROUP, $ttl);
        self::$memory_cache[$cache_key] = $slots;
    }
    
    /**
     * Clear all caches.
     */
    public static function clear_all()
    {
        wp_cache_flush_group(self::CACHE_GROUP);
        self::$memory_cache = [];
    }
    
    /**
     * Clear specific cache.
     */
    public static function clear_cache($pattern = '')
    {
        if (empty($pattern)) {
            self::clear_all();
            return;
        }
        
        // Clear memory cache
        foreach (self::$memory_cache as $key => $value) {
            if (strpos($key, $pattern) !== false) {
                unset(self::$memory_cache[$key]);
            }
        }
        
        // Note: WordPress object cache doesn't support pattern-based clearing
        // In production, you might want to use Redis or Memcached with pattern support
    }
    
    /**
     * Warm up frequently accessed caches.
     */
    public function warm_cache()
    {
        // Pre-load commonly accessed options
        self::get_google_calendar_overlap();
        self::is_plugin_enabled();
    }
    
    /**
     * Get cache statistics.
     */
    public static function get_cache_stats()
    {
        return [
            'memory_cache_count' => count(self::$memory_cache),
            'memory_cache_keys' => array_keys(self::$memory_cache),
            'cache_group' => self::CACHE_GROUP
        ];
    }
}
