<?php
/**
 * Timetics Occupied Slots Addon - Full Year Manager
 * 
 * Handles full year blocked dates with backward compatibility
 * 
 * @package Timetics_Occupied_Slots_Addon
 */

class OccupiedSlotsFullYearManager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Cache tiers for different date ranges
     */
    const HOT_CACHE_DAYS = 30;      // 0-30 days: Real-time updates
    const WARM_CACHE_DAYS = 90;     // 31-90 days: Daily updates  
    const COLD_CACHE_DAYS = 365;    // 91-365 days: Weekly updates
    
    /**
     * Cache durations (in seconds)
     */
    const HOT_CACHE_TTL = 3600;     // 1 hour
    const WARM_CACHE_TTL = 86400;   // 24 hours
    const COLD_CACHE_TTL = 604800;  // 7 days
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add new API endpoint for full year support
        add_action('rest_api_init', [$this, 'register_full_year_endpoints']);
        
        // Add cache warming for full year
        add_action('wp_loaded', [$this, 'warm_full_year_cache']);
    }
    
    /**
     * Register full year API endpoints
     */
    public function register_full_year_endpoints() {
        // Full year endpoint (new, doesn't affect existing)
        register_rest_route('timetics-occupied-slots/v1', '/bookings/entries/full-year', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_full_year_request'],
            'permission_callback' => '__return_true', // Same as existing endpoints
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
                    'required' => false,
                    'type' => 'string',
                    'format' => 'date',
                    'default' => date('Y-m-d'),
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'end_date' => [
                    'required' => false,
                    'type' => 'string',
                    'format' => 'date',
                    'default' => date('Y-m-d', strtotime('+365 days')),
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'timezone' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'UTC',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'tier' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['hot', 'warm', 'cold', 'auto'],
                    'default' => 'auto',
                    'sanitize_callback' => 'sanitize_text_field',
                ]
            ]
        ]);
    }
    
    /**
     * Handle full year request
     */
    public function handle_full_year_request($request) {
        $staff_id = $request->get_param('staff_id');
        $meeting_id = $request->get_param('meeting_id');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        $timezone = $request->get_param('timezone');
        $tier = $request->get_param('tier');
        
        // Validate date range
        $days_diff = $this->get_days_difference($start_date, $end_date);
        if ($days_diff > 365) {
            return new WP_Error('invalid_range', 'Maximum 365 days supported', ['status' => 400]);
        }
        
        // Get blocked dates using appropriate tier
        $blocked_dates = $this->get_blocked_dates_by_tier($staff_id, $meeting_id, $start_date, $end_date, $timezone, $tier);
        
        return [
            'success' => true,
            'data' => [
                'blocked_dates' => $blocked_dates,
                'cache_info' => [
                    'tier' => $tier,
                    'days_requested' => $days_diff,
                    'cached_at' => current_time('mysql'),
                    'source' => 'full_year_manager'
                ]
            ]
        ];
    }
    
    /**
     * Get blocked dates by tier
     */
    private function get_blocked_dates_by_tier($staff_id, $meeting_id, $start_date, $end_date, $timezone, $tier) {
        $days_diff = $this->get_days_difference($start_date, $end_date);
        
        // Auto-determine tier if not specified
        if ($tier === 'auto') {
            if ($days_diff <= self::HOT_CACHE_DAYS) {
                $tier = 'hot';
            } elseif ($days_diff <= self::WARM_CACHE_DAYS) {
                $tier = 'warm';
            } else {
                $tier = 'cold';
            }
        }
        
        // Get cache key for this tier
        $cache_key = $this->generate_cache_key($staff_id, $meeting_id, $start_date, $end_date, $timezone, $tier);
        
        // Try to get from cache first
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Generate blocked dates
        $blocked_dates = $this->generate_blocked_dates($staff_id, $meeting_id, $start_date, $end_date, $timezone, $tier);
        
        // Cache the results
        $ttl = $this->get_ttl_for_tier($tier);
        set_transient($cache_key, $blocked_dates, $ttl);
        
        return $blocked_dates;
    }
    
    /**
     * Generate blocked dates for the specified range
     */
    private function generate_blocked_dates($staff_id, $meeting_id, $start_date, $end_date, $timezone, $tier) {
        $blocked_dates = [];
        
        // For hot tier, use existing intelligent detector
        if ($tier === 'hot' && class_exists('OccupiedSlotsIntelligentDetector')) {
            $detector = OccupiedSlotsIntelligentDetector::get_instance();
            $response = $detector->handle_intelligent_bookings_entries([
                'staff_id' => $staff_id,
                'meeting_id' => $meeting_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'timezone' => $timezone
            ]);
            
            if (is_array($response) && isset($response['data']['blocked_dates'])) {
                return $response['data']['blocked_dates'];
            }
        }
        
        // For warm and cold tiers, use batch processing
        $date_ranges = $this->split_date_range($start_date, $end_date, $tier);
        
        foreach ($date_ranges as $range) {
            $range_blocked = $this->get_blocked_dates_for_range($staff_id, $meeting_id, $range['start'], $range['end'], $timezone);
            $blocked_dates = array_merge($blocked_dates, $range_blocked);
        }
        
        return array_unique($blocked_dates);
    }
    
    /**
     * Split date range into manageable chunks
     */
    private function split_date_range($start_date, $end_date, $tier) {
        $ranges = [];
        $current_start = $start_date;
        
        $chunk_size = $tier === 'hot' ? 7 : ($tier === 'warm' ? 14 : 30);
        
        while ($current_start < $end_date) {
            $current_end = date('Y-m-d', strtotime($current_start . " +{$chunk_size} days"));
            if ($current_end > $end_date) {
                $current_end = $end_date;
            }
            
            $ranges[] = [
                'start' => $current_start,
                'end' => $current_end
            ];
            
            $current_start = date('Y-m-d', strtotime($current_end . ' +1 day'));
        }
        
        return $ranges;
    }
    
    /**
     * Get blocked dates for a specific range
     */
    private function get_blocked_dates_for_range($staff_id, $meeting_id, $start_date, $end_date, $timezone) {
        // Use existing simple blocker for individual ranges
        if (class_exists('OccupiedSlotsSimpleBlocker')) {
            $blocker = new OccupiedSlotsSimpleBlocker();
            return $blocker->get_blocked_dates($start_date, $end_date);
        }
        
        // Fallback: return empty array
        return [];
    }
    
    /**
     * Warm full year cache
     */
    public function warm_full_year_cache() {
        // Only warm cache in background, don't block page load
        if (!wp_doing_ajax() && !wp_doing_cron()) {
            wp_schedule_single_event(time() + 30, 'warm_full_year_cache_hook');
        }
    }
    
    /**
     * Warm cache hook
     */
    public function warm_cache_hook() {
        // Warm cache for common staff/meeting combinations
        $common_combinations = $this->get_common_staff_meeting_combinations();
        
        foreach ($common_combinations as $combo) {
            $this->warm_cache_for_combination($combo['staff_id'], $combo['meeting_id']);
        }
    }
    
    /**
     * Get common staff/meeting combinations
     */
    private function get_common_staff_meeting_combinations() {
        // Get from URL parameters or database
        $combinations = [];
        
        // Check for common combinations in URL parameters
        if (isset($_GET['staff_id']) && isset($_GET['meeting_id'])) {
            $combinations[] = [
                'staff_id' => absint($_GET['staff_id']),
                'meeting_id' => absint($_GET['meeting_id'])
            ];
        }
        
        // Add default combinations if none found
        if (empty($combinations)) {
            $combinations[] = ['staff_id' => 71, 'meeting_id' => 2315]; // Default from production
        }
        
        return $combinations;
    }
    
    /**
     * Warm cache for specific combination
     */
    private function warm_cache_for_combination($staff_id, $meeting_id) {
        $start_date = date('Y-m-d');
        $timezone = wp_timezone_string();
        
        // Warm hot cache (0-30 days)
        $hot_end = date('Y-m-d', strtotime('+30 days'));
        $this->get_blocked_dates_by_tier($staff_id, $meeting_id, $start_date, $hot_end, $timezone, 'hot');
        
        // Warm warm cache (31-90 days) in background
        wp_schedule_single_event(time() + 60, 'warm_warm_cache_hook', [$staff_id, $meeting_id]);
    }
    
    /**
     * Generate cache key
     */
    private function generate_cache_key($staff_id, $meeting_id, $start_date, $end_date, $timezone, $tier) {
        return "full_year_{$tier}_{$staff_id}_{$meeting_id}_{$start_date}_{$end_date}_{$timezone}";
    }
    
    /**
     * Get TTL for tier
     */
    private function get_ttl_for_tier($tier) {
        switch ($tier) {
            case 'hot': return self::HOT_CACHE_TTL;
            case 'warm': return self::WARM_CACHE_TTL;
            case 'cold': return self::COLD_CACHE_TTL;
            default: return self::WARM_CACHE_TTL;
        }
    }
    
    /**
     * Get days difference between dates
     */
    private function get_days_difference($start_date, $end_date) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        return $start->diff($end)->days;
    }
    
    /**
     * Clear all full year caches
     */
    public function clear_all_caches() {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_full_year_%'
        ));
    }
}

// Initialize the manager
add_action('plugins_loaded', function() {
    OccupiedSlotsFullYearManager::get_instance();
});

// Add cron hooks
add_action('warm_full_year_cache_hook', [OccupiedSlotsFullYearManager::get_instance(), 'warm_cache_hook']);
add_action('warm_warm_cache_hook', function($staff_id, $meeting_id) {
    $manager = OccupiedSlotsFullYearManager::get_instance();
    $start_date = date('Y-m-d', strtotime('+31 days'));
    $end_date = date('Y-m-d', strtotime('+90 days'));
    $timezone = wp_timezone_string();
    $manager->get_blocked_dates_by_tier($staff_id, $meeting_id, $start_date, $end_date, $timezone, 'warm');
});
