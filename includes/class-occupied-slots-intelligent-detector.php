<?php
/**
 * Timetics Occupied Slots Addon - Intelligent Detector
 * 
 * Senior-level implementation with automatic booked date detection and intelligent caching
 * 
 * @package Timetics_Occupied_Slots_Addon
 * @version 1.6.3
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsIntelligentDetector
{
    /**
     * Plugin version.
     */
    const VERSION = '1.6.3';

    /**
     * Cache duration in seconds (24 hours).
     */
    const CACHE_DURATION = 86400;

    /**
     * Singleton instance.
     */
    private static $instance = null;

    /**
     * Cache manager instance.
     */
    private $cache_manager;

    /**
     * Performance monitor instance.
     */
    private $performance_monitor;

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
        $this->init_dependencies();
        $this->init_hooks();
    }

    /**
     * Initialize dependencies.
     */
    private function init_dependencies()
    {
        // Initialize cache manager
        if (class_exists('OccupiedSlotsCacheManager')) {
            $this->cache_manager = OccupiedSlotsCacheManager::get_instance();
        }

        // Initialize performance monitor
        if (class_exists('OccupiedSlotsPerformanceMonitor')) {
            $this->performance_monitor = OccupiedSlotsPerformanceMonitor::get_instance();
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks()
    {
        // Hook into REST API to enhance responses
        add_action('rest_api_init', [$this, 'enhance_rest_api'], 30);
        
        // Cache invalidation hooks
        add_action('timetics_booking_created', [$this, 'invalidate_cache'], 10, 2);
        add_action('timetics_booking_updated', [$this, 'invalidate_cache'], 10, 2);
        add_action('timetics_booking_deleted', [$this, 'invalidate_cache'], 10, 2);
        
        // Performance monitoring
        add_action('wp_footer', [$this, 'log_performance_metrics']);
    }

    /**
     * Enhance REST API with intelligent detection.
     */
    public function enhance_rest_api()
    {
        // Override our bookings endpoint with intelligent detection
        register_rest_route('timetics-occupied-slots/v1', '/bookings/entries', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_intelligent_bookings_entries'],
            'permission_callback' => '__return_true',
            'args' => [
                'staff_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'meeting_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'start_date' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'date',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'end_date' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'date',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'timezone' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'UTC',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Handle intelligent bookings entries with caching.
     */
    public function handle_intelligent_bookings_entries($request)
    {
        $start_time = microtime(true);
        
        $staff_id = $request->get_param('staff_id');
        $meeting_id = $request->get_param('meeting_id');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        $timezone = $request->get_param('timezone') ?: 'UTC';

        // Generate cache key
        $cache_key = $this->generate_cache_key($staff_id, $meeting_id, $start_date, $end_date, $timezone);

        // Try to get from cache first
        $cached_data = $this->get_cached_data($cache_key);
        if ($cached_data !== false) {
            $this->log_performance('cache_hit', $start_time, $cache_key);
            return new WP_REST_Response($cached_data, 200);
        }

        // Cache miss - get fresh data
        $blocked_dates = $this->get_intelligent_blocked_dates($staff_id, $meeting_id, $start_date, $end_date, $timezone);
        
        // Prepare response
        $response = [
            'success' => true,
            'data' => [
                'days' => [],
                'blocked_dates' => $blocked_dates,
                'cache_info' => [
                    'cached_at' => current_time('mysql'),
                    'cache_duration' => self::CACHE_DURATION,
                    'source' => 'intelligent_detector'
                ]
            ]
        ];

        // Cache the response
        $this->set_cached_data($cache_key, $response, self::CACHE_DURATION);
        
        $this->log_performance('cache_miss', $start_time, $cache_key, count($blocked_dates));
        
        return new WP_REST_Response($response, 200);
    }

    /**
     * Get intelligent blocked dates with automatic detection.
     */
    private function get_intelligent_blocked_dates($staff_id, $meeting_id, $start_date, $end_date, $timezone)
    {
        $blocked_dates = [];

        try {
            // Get base Timetics data
            $base_data = $this->get_base_timetics_data($staff_id, $meeting_id, $start_date, $end_date, $timezone);
            
            if (!is_wp_error($base_data) && isset($base_data['data']['days'])) {
                // Extract unavailable dates from base Timetics
                $unavailable_dates = $this->extract_unavailable_dates($base_data['data']['days']);
                $blocked_dates = array_merge($blocked_dates, $unavailable_dates);
            }

            // Get additional blocked dates from our plugin
            if (class_exists('OccupiedSlotsSimpleBlocker')) {
                $blocker = OccupiedSlotsSimpleBlocker::get_instance();
                $plugin_blocked_dates = $blocker->get_blocked_dates();
                $blocked_dates = array_merge($blocked_dates, $plugin_blocked_dates);
            }

            // Remove duplicates and sort
            $blocked_dates = array_unique($blocked_dates);
            sort($blocked_dates);

        } catch (Exception $e) {
            $this->log_error('Error getting intelligent blocked dates', $e);
        }

        return $blocked_dates;
    }

    /**
     * Get base Timetics data with error handling.
     */
    private function get_base_timetics_data($staff_id, $meeting_id, $start_date, $end_date, $timezone)
    {
        $api_url = get_site_url() . '/wp-json/timetics/v1/bookings/entries';
        $params = [
            'staff_id' => $staff_id,
            'meeting_id' => $meeting_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'timezone' => $timezone
        ];
        
        $api_url .= '?' . http_build_query($params);
        
        $response = wp_remote_get($api_url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'Timetics-Occupied-Slots-Addon/1.6.3'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Extract unavailable dates from base Timetics response.
     */
    private function extract_unavailable_dates($days)
    {
        $unavailable_dates = [];
        
        if (is_array($days)) {
            foreach ($days as $day) {
                if (isset($day['status']) && $day['status'] === 'unavailable' && 
                    isset($day['reason']) && $day['reason'] === 'no_available_slots' &&
                    isset($day['date'])) {
                    $unavailable_dates[] = $day['date'];
                }
            }
        }
        
        return $unavailable_dates;
    }

    /**
     * Generate cache key.
     */
    private function generate_cache_key($staff_id, $meeting_id, $start_date, $end_date, $timezone)
    {
        return 'occupied_slots_' . md5($staff_id . '_' . $meeting_id . '_' . $start_date . '_' . $end_date . '_' . $timezone);
    }

    /**
     * Get cached data.
     */
    private function get_cached_data($cache_key)
    {
        if ($this->cache_manager) {
            return $this->cache_manager->get($cache_key);
        }
        
        // Fallback to WordPress transients
        return get_transient($cache_key);
    }

    /**
     * Set cached data.
     */
    private function set_cached_data($cache_key, $data, $duration)
    {
        if ($this->cache_manager) {
            $this->cache_manager->set($cache_key, $data, $duration);
        } else {
            // Fallback to WordPress transients
            set_transient($cache_key, $data, $duration);
        }
    }

    /**
     * Invalidate cache when bookings change.
     */
    public function invalidate_cache($booking_id, $booking_data)
    {
        // Clear all occupied slots cache
        $this->clear_all_cache();
        
        if (class_exists('OccupiedSlotsLogger')) {
            OccupiedSlotsLogger::info('Cache invalidated due to booking change', [
                'booking_id' => $booking_id,
                'booking_data' => $booking_data
            ]);
        }
    }

    /**
     * Clear all cache.
     */
    private function clear_all_cache()
    {
        global $wpdb;
        
        // Clear WordPress transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_occupied_slots_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_occupied_slots_%'");
        
        // Clear cache manager if available
        if ($this->cache_manager) {
            $this->cache_manager->clear_all();
        }
    }

    /**
     * Log performance metrics.
     */
    private function log_performance($type, $start_time, $cache_key, $blocked_count = 0)
    {
        $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
        
        if ($this->performance_monitor) {
            $this->performance_monitor->log_metric($type, $execution_time, [
                'cache_key' => $cache_key,
                'blocked_dates_count' => $blocked_count
            ]);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("OccupiedSlotsIntelligentDetector: {$type} - {$execution_time}ms - {$cache_key} - {$blocked_count} blocked dates");
        }
    }

    /**
     * Log error.
     */
    private function log_error($message, $exception = null)
    {
        if (class_exists('OccupiedSlotsLogger')) {
            OccupiedSlotsLogger::error($message, [
                'exception' => $exception ? $exception->getMessage() : null,
                'trace' => $exception ? $exception->getTraceAsString() : null
            ]);
        } else {
            error_log("OccupiedSlotsIntelligentDetector Error: {$message}" . ($exception ? " - " . $exception->getMessage() : ''));
        }
    }

    /**
     * Log performance metrics to footer.
     */
    public function log_performance_metrics()
    {
        if ($this->performance_monitor && current_user_can('manage_options')) {
            $metrics = $this->performance_monitor->get_metrics();
            echo "<!-- OccupiedSlots Performance: " . json_encode($metrics) . " -->";
        }
    }

    /**
     * Get cache statistics.
     */
    public function get_cache_stats()
    {
        if ($this->cache_manager) {
            return $this->cache_manager->get_stats();
        }
        
        return [
            'cache_hits' => 0,
            'cache_misses' => 0,
            'cache_size' => 0
        ];
    }
}
