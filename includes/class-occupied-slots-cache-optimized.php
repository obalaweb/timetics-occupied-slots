<?php
/**
 * Timetics Occupied Slots Addon - Optimized Cache Manager
 * 
 * High-performance caching system for blocked dates and API responses
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsCacheOptimized
{
    /**
     * Cache group name
     */
    const CACHE_GROUP = 'occupied_slots';
    
    /**
     * Cache duration constants
     */
    const CACHE_DURATION_SHORT = 300;  // 5 minutes
    const CACHE_DURATION_MEDIUM = 1800; // 30 minutes
    const CACHE_DURATION_LONG = 3600;  // 1 hour
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Cache statistics
     */
    private $cache_stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];
    
    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Clear cache when bookings are updated
        add_action('timetics_booking_created', [$this, 'clear_booking_cache']);
        add_action('timetics_booking_updated', [$this, 'clear_booking_cache']);
        add_action('timetics_booking_deleted', [$this, 'clear_booking_cache']);
        
        // Clear cache when Google Calendar events change
        add_action('timetics_google_calendar_sync', [$this, 'clear_calendar_cache']);
        
        // Add cache statistics to admin
        add_action('wp_ajax_occupied_slots_cache_stats', [$this, 'get_cache_stats']);
    }
    
    /**
     * Get blocked dates with caching
     */
    public function get_blocked_dates($start_date, $end_date, $force_refresh = false)
    {
        $cache_key = $this->generate_cache_key('blocked_dates', $start_date, $end_date);
        
        if (!$force_refresh) {
            $cached_data = $this->get($cache_key);
            if (false !== $cached_data) {
                $this->cache_stats['hits']++;
                return $cached_data;
            }
        }
        
        $this->cache_stats['misses']++;
        
        // Get blocked dates from multiple sources
        $blocked_dates = $this->fetch_blocked_dates($start_date, $end_date);
        
        // Cache the results
        $this->set($cache_key, $blocked_dates, self::CACHE_DURATION_MEDIUM);
        
        return $blocked_dates;
    }
    
    /**
     * Get Timetics blocked dates with optimized query
     */
    public function get_timetics_blocked_dates($start_date, $end_date)
    {
        $cache_key = $this->generate_cache_key('timetics_blocked', $start_date, $end_date);
        
        $cached_data = $this->get($cache_key);
        if (false !== $cached_data) {
            $this->cache_stats['hits']++;
            return $cached_data;
        }
        
        global $wpdb;
        
        // Optimized query with proper indexing
        $query = $wpdb->prepare("
            SELECT 
                DATE(start_date) as booking_date,
                COUNT(*) as booking_count,
                MAX(end_date) as last_booking_time
            FROM {$wpdb->prefix}bm_bookings 
            WHERE start_date BETWEEN %s AND %s
            GROUP BY DATE(start_date)
            HAVING booking_count >= (
                SELECT COUNT(*) 
                FROM {$wpdb->prefix}bm_employees 
                WHERE id IS NOT NULL
            )
        ", $start_date, $end_date);
        
        $results = $wpdb->get_results($query);
        
        $blocked_dates = [];
        foreach ($results as $result) {
            if ($this->is_date_fully_occupied($result->booking_date, $result->booking_count)) {
                $blocked_dates[] = $result->booking_date;
            }
        }
        
        // Cache results
        $this->set($cache_key, $blocked_dates, self::CACHE_DURATION_MEDIUM);
        
        return $blocked_dates;
    }
    
    /**
     * Get Google Calendar blocked dates with caching
     */
    public function get_google_calendar_blocked_dates($start_date, $end_date)
    {
        $cache_key = $this->generate_cache_key('google_blocked', $start_date, $end_date);
        
        $cached_data = $this->get($cache_key);
        if (false !== $cached_data) {
            $this->cache_stats['hits']++;
            return $cached_data;
        }
        
        // Check if Google Calendar integration is enabled
        if (!function_exists('timetics_get_option') || 
            !timetics_get_option('google_calendar_overlap', false)) {
            return [];
        }
        
        $blocked_dates = [];
        
        try {
            if (class_exists('Timetics\Core\Integrations\Google\Service\Google_Calendar_Sync')) {
                $google_sync = \Timetics\Core\Integrations\Google\Service\Google_Calendar_Sync::instance();
                
                // Get events with date range
                $events = $google_sync->get_events_from_google([
                    'timeMin' => $start_date . 'T00:00:00Z',
                    'timeMax' => $end_date . 'T23:59:59Z'
                ]);
                
                foreach ($events as $event) {
                    $event_date = $this->extract_date_from_event($event);
                    if ($event_date && $this->is_date_fully_occupied_by_google($event_date, $events)) {
                        $blocked_dates[] = $event_date;
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Timetics Cache: Google Calendar error - ' . $e->getMessage());
        }
        
        // Cache results
        $this->set($cache_key, $blocked_dates, self::CACHE_DURATION_SHORT);
        
        return $blocked_dates;
    }
    
    /**
     * Optimized method to check if date is fully occupied
     */
    private function is_date_fully_occupied($date, $booking_count = null)
    {
        $cache_key = $this->generate_cache_key('date_occupied', $date);
        
        $cached_result = $this->get($cache_key);
        if (false !== $cached_result) {
            return $cached_result;
        }
        
        global $wpdb;
        
        // Get total available slots for this date
        $available_slots = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}bm_employees e
            WHERE e.status = 'active'
            AND EXISTS (
                SELECT 1 FROM {$wpdb->prefix}bm_meetings m
                WHERE m.employee_id = e.id
                AND m.status = 'active'
            )
        "));
        
        // Get booking count if not provided
        if ($booking_count === null) {
            $booking_count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$wpdb->prefix}bm_bookings 
                WHERE DATE(start_date) = %s
            ", $date));
        }
        
        $is_occupied = $booking_count >= $available_slots;
        
        // Cache result for 5 minutes
        $this->set($cache_key, $is_occupied, self::CACHE_DURATION_SHORT);
        
        return $is_occupied;
    }
    
    /**
     * Check if date is fully occupied by Google Calendar events
     */
    private function is_date_fully_occupied_by_google($date, $events)
    {
        $date_events = array_filter($events, function($event) use ($date) {
            $event_date = $this->extract_date_from_event($event);
            return $event_date === $date;
        });
        
        // If there are events covering the entire business day, consider it blocked
        return count($date_events) > 0;
    }
    
    /**
     * Extract date from Google Calendar event
     */
    private function extract_date_from_event($event)
    {
        if (isset($event['start']['date'])) {
            return $event['start']['date'];
        } elseif (isset($event['start']['dateTime'])) {
            return date('Y-m-d', strtotime($event['start']['dateTime']));
        }
        return null;
    }
    
    /**
     * Fetch blocked dates from all sources
     */
    private function fetch_blocked_dates($start_date, $end_date)
    {
        $blocked_dates = [];
        
        // Get Timetics blocked dates
        $timetics_blocked = $this->get_timetics_blocked_dates($start_date, $end_date);
        if (!empty($timetics_blocked)) {
            $blocked_dates = array_merge($blocked_dates, $timetics_blocked);
        }
        
        // Get Google Calendar blocked dates
        $google_blocked = $this->get_google_calendar_blocked_dates($start_date, $end_date);
        if (!empty($google_blocked)) {
            $blocked_dates = array_merge($blocked_dates, $google_blocked);
        }
        
        // Remove duplicates and sort
        $blocked_dates = array_unique($blocked_dates);
        sort($blocked_dates);
        
        return $blocked_dates;
    }
    
    /**
     * Generate cache key
     */
    private function generate_cache_key($prefix, ...$params)
    {
        $key = $prefix . '_' . md5(serialize($params));
        return $key;
    }
    
    /**
     * Get from cache
     */
    private function get($key)
    {
        return wp_cache_get($key, self::CACHE_GROUP);
    }
    
    /**
     * Set cache
     */
    private function set($key, $data, $expiration = self::CACHE_DURATION_MEDIUM)
    {
        $this->cache_stats['sets']++;
        return wp_cache_set($key, $data, self::CACHE_GROUP, $expiration);
    }
    
    /**
     * Delete from cache
     */
    private function delete($key)
    {
        $this->cache_stats['deletes']++;
        return wp_cache_delete($key, self::CACHE_GROUP);
    }
    
    /**
     * Clear booking-related cache
     */
    public function clear_booking_cache()
    {
        $this->clear_cache_by_pattern('timetics_blocked_*');
        $this->clear_cache_by_pattern('blocked_dates_*');
        $this->clear_cache_by_pattern('date_occupied_*');
    }
    
    /**
     * Clear calendar-related cache
     */
    public function clear_calendar_cache()
    {
        $this->clear_cache_by_pattern('google_blocked_*');
        $this->clear_cache_by_pattern('blocked_dates_*');
    }
    
    /**
     * Clear cache by pattern
     */
    private function clear_cache_by_pattern($pattern)
    {
        // WordPress doesn't have built-in pattern deletion
        // This is a simplified implementation
        global $wpdb;
        
        $cache_keys = $wpdb->get_col($wpdb->prepare("
            SELECT option_name 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", '_transient_' . $pattern));
        
        foreach ($cache_keys as $key) {
            $cache_key = str_replace('_transient_', '', $key);
            $this->delete($cache_key);
        }
    }
    
    /**
     * Get cache statistics
     */
    public function get_cache_stats()
    {
        return [
            'hits' => $this->cache_stats['hits'],
            'misses' => $this->cache_stats['misses'],
            'hit_rate' => $this->cache_stats['hits'] / max(1, $this->cache_stats['hits'] + $this->cache_stats['misses']),
            'sets' => $this->cache_stats['sets'],
            'deletes' => $this->cache_stats['deletes']
        ];
    }
    
    /**
     * Warm up cache for common date ranges
     */
    public function warm_up_cache()
    {
        $today = date('Y-m-d');
        $next_30_days = date('Y-m-d', strtotime('+30 days'));
        
        // Pre-cache common date ranges
        $this->get_blocked_dates($today, $next_30_days);
        
        // Pre-cache next week
        $next_week = date('Y-m-d', strtotime('+7 days'));
        $this->get_blocked_dates($today, $next_week);
    }
    
    /**
     * Get cache performance metrics
     */
    public function get_performance_metrics()
    {
        $stats = $this->get_cache_stats();
        
        return [
            'cache_hit_rate' => round($stats['hit_rate'] * 100, 2) . '%',
            'total_requests' => $stats['hits'] + $stats['misses'],
            'cache_efficiency' => $stats['hits'] > 0 ? 'Good' : 'Needs Improvement',
            'memory_usage' => $this->get_memory_usage(),
            'cache_size' => $this->get_cache_size()
        ];
    }
    
    /**
     * Get memory usage
     */
    private function get_memory_usage()
    {
        return round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';
    }
    
    /**
     * Get cache size
     */
    private function get_cache_size()
    {
        global $wpdb;
        
        $cache_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_occupied_slots_%'
        ");
        
        return $cache_count . ' items';
    }
}
