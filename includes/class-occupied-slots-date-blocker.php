<?php
/**
 * Timetics Occupied Slots Addon - Date Blocker
 * 
 * Proactively blocks fully booked dates in Timetics calendar
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsDateBlocker
{
    /**
     * Plugin version.
     */
    const VERSION = '1.6.3';

    /**
     * Singleton instance.
     */
    private static $instance = null;

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
        // Hook into Timetics calendar to block dates
        add_filter('timetics_calendar_disabled_dates', [$this, 'add_occupied_dates'], 10, 2);
        add_filter('timetics_booking_disabled_dates', [$this, 'add_occupied_dates'], 10, 2);
        
        // Add JavaScript to block dates on frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_date_blocker_assets']);
        
        // Add REST API endpoint for checking occupied dates
        // REST API endpoint is handled by the core-optimized class
        
        // Cache occupied dates
        add_action('init', [$this, 'init_date_caching']);
    }

    /**
     * Enqueue date blocker assets
     */
    public function enqueue_date_blocker_assets()
    {
        // Always load - no shortcode detection needed
        wp_enqueue_script(
            'timetics-occupied-slots-date-blocker',
            plugin_dir_url(TIMETICS_OCCUPIED_SLOTS_FILE) . 'assets/js/occupied-slots-date-blocker.js',
            ['jquery'],
            self::VERSION,
            true
        );

        wp_enqueue_style(
            'timetics-occupied-slots-date-blocker',
            plugin_dir_url(TIMETICS_OCCUPIED_SLOTS_FILE) . 'assets/css/occupied-slots-date-blocker.css',
            [],
            self::VERSION
        );

        // Localize script with occupied dates
        wp_localize_script('timetics-occupied-slots-date-blocker', 'timeticsDateBlocker', [
            'ajaxUrl' => rest_url('timetics/v1/occupied-dates'),
            'nonce' => wp_create_nonce('wp_rest'),
            'occupiedDates' => $this->get_occupied_dates(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ]);
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        register_rest_route('timetics/v1', '/occupied-dates', [
            'methods' => 'GET',
            'callback' => [$this, 'get_occupied_dates_api'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Get occupied dates via REST API
     */
    public function get_occupied_dates_api($request)
    {
        $start_date = $request->get_param('start_date') ?: date('Y-m-d');
        $end_date = $request->get_param('end_date') ?: date('Y-m-d', strtotime('+30 days'));
        
        $occupied_dates = $this->get_occupied_dates($start_date, $end_date);
        
        return [
            'success' => true,
            'occupied_dates' => $occupied_dates,
            'total_occupied' => count($occupied_dates)
        ];
    }

    /**
     * Add occupied dates to Timetics disabled dates
     */
    public function add_occupied_dates($disabled_dates, $meeting_id = null)
    {
        if (!is_array($disabled_dates)) {
            $disabled_dates = [];
        }

        // Get occupied dates from Google Calendar
        $occupied_dates = $this->get_occupied_dates();
        
        // Merge with existing disabled dates
        $all_disabled = array_unique(array_merge($disabled_dates, $occupied_dates));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Timetics Date Blocker: Adding ' . count($occupied_dates) . ' occupied dates to disabled dates');
        }
        
        return $all_disabled;
    }

    /**
     * Get occupied dates from Google Calendar
     */
    public function get_occupied_dates($start_date = null, $end_date = null)
    {
        if (!$start_date) {
            $start_date = date('Y-m-d');
        }
        if (!$end_date) {
            $end_date = date('Y-m-d', strtotime('+30 days'));
        }

        // Check cache first
        $cache_key = 'timetics_occupied_dates_' . md5($start_date . $end_date);
        $cached_dates = wp_cache_get($cache_key, 'timetics_occupied_slots');
        
        if ($cached_dates !== false) {
            return $cached_dates;
        }

        $occupied_dates = [];

        try {
            // Get Google Calendar events
            $events = $this->get_google_calendar_events($start_date, $end_date);
            
            if (!empty($events)) {
                foreach ($events as $event) {
                    $event_date = $this->extract_event_date($event);
                    if ($event_date && $this->is_date_fully_booked($event_date, $events)) {
                        $occupied_dates[] = $event_date;
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Timetics Date Blocker: Error getting occupied dates - ' . $e->getMessage());
        }

        // Cache the results for 1 hour
        wp_cache_set($cache_key, $occupied_dates, 'timetics_occupied_slots', 3600);
        
        return $occupied_dates;
    }

    /**
     * Get Google Calendar events
     */
    private function get_google_calendar_events($start_date, $end_date)
    {
        try {
            // Use Timetics Google Calendar integration
            if (class_exists('OccupiedSlotsGoogleCalendarFixed')) {
                $google_calendar = OccupiedSlotsGoogleCalendarFixed::get_instance();
                return $google_calendar->get_events_for_date_range($start_date, $end_date);
            }
            
            // Fallback to direct Google Calendar API
            return $this->get_google_calendar_events_direct($start_date, $end_date);
        } catch (Exception $e) {
            error_log('Timetics Date Blocker: Error getting Google Calendar events - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Google Calendar events directly
     */
    private function get_google_calendar_events_direct($start_date, $end_date)
    {
        // This would integrate with Google Calendar API directly
        // For now, return mock data
        return [];
    }

    /**
     * Extract date from event
     */
    private function extract_event_date($event)
    {
        if (isset($event['start']['date'])) {
            return $event['start']['date'];
        } elseif (isset($event['start']['dateTime'])) {
            return date('Y-m-d', strtotime($event['start']['dateTime']));
        }
        return null;
    }

    /**
     * Check if a date is fully booked
     */
    private function is_date_fully_booked($date, $events)
    {
        // Count events for this date
        $date_events = array_filter($events, function($event) use ($date) {
            $event_date = $this->extract_event_date($event);
            return $event_date === $date;
        });

        // Get available slots for this date from Timetics
        $available_slots = $this->get_available_slots_for_date($date);
        
        // If no available slots, date is fully booked
        return empty($available_slots);
    }

    /**
     * Get available slots for a specific date
     */
    private function get_available_slots_for_date($date)
    {
        // This would integrate with Timetics API to get available slots
        // For now, return mock data
        return [];
    }

    /**
     * Initialize date caching
     */
    public function init_date_caching()
    {
        // Pre-cache occupied dates for the next 30 days
        $this->get_occupied_dates();
    }

    /**
     * Clear occupied dates cache
     */
    public function clear_occupied_dates_cache()
    {
        wp_cache_delete_group('timetics_occupied_slots');
    }
}
