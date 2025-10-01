<?php
/**
 * Timetics Occupied Slots Addon - Settings Modifier
 * 
 * Modifies Timetics settings to include occupied dates as blocked dates
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsSettingsModifier
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
        // Hook into Timetics settings/availability filters
        add_filter('timetics_meeting_availability', [$this, 'modify_availability_settings'], 10, 2);
        add_filter('timetics_booking_availability', [$this, 'modify_availability_settings'], 10, 2);
        add_filter('timetics_calendar_availability', [$this, 'modify_availability_settings'], 10, 2);
        
        // Hook into REST API responses
        add_filter('rest_prepare_timetics_meeting', [$this, 'modify_meeting_response'], 10, 3);
        add_filter('rest_prepare_timetics_booking', [$this, 'modify_booking_response'], 10, 3);
        
        // Add custom endpoint for occupied dates
        add_action('rest_api_init', [$this, 'register_occupied_dates_endpoint']);
    }

    /**
     * Modify availability settings to include occupied dates
     */
    public function modify_availability_settings($availability, $meeting_id = null)
    {
        if (!is_array($availability)) {
            return $availability;
        }

        // Get occupied dates
        $occupied_dates = $this->get_occupied_dates();
        
        if (empty($occupied_dates)) {
            return $availability;
        }

        // Add occupied dates as blocked dates
        if (!isset($availability['blocked_dates'])) {
            $availability['blocked_dates'] = [];
        }

        // Merge occupied dates with existing blocked dates
        $availability['blocked_dates'] = array_unique(array_merge(
            $availability['blocked_dates'],
            $occupied_dates
        ));

        // Sort dates
        sort($availability['blocked_dates']);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Timetics Settings Modifier: Added ' . count($occupied_dates) . ' occupied dates to blocked dates');
        }

        return $availability;
    }

    /**
     * Modify meeting REST API response
     */
    public function modify_meeting_response($response, $post, $request)
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $data = $response->get_data();
        
        // Add occupied dates to meeting data
        $occupied_dates = $this->get_occupied_dates();
        if (!empty($occupied_dates)) {
            $data['occupied_dates'] = $occupied_dates;
            $data['blocked_dates'] = array_unique(array_merge(
                $data['blocked_dates'] ?? [],
                $occupied_dates
            ));
        }

        $response->set_data($data);
        return $response;
    }

    /**
     * Modify booking REST API response
     */
    public function modify_booking_response($response, $post, $request)
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $data = $response->get_data();
        
        // Add occupied dates to booking data
        $occupied_dates = $this->get_occupied_dates();
        if (!empty($occupied_dates)) {
            $data['occupied_dates'] = $occupied_dates;
        }

        $response->set_data($data);
        return $response;
    }

    /**
     * Register occupied dates endpoint
     */
    public function register_occupied_dates_endpoint()
    {
        register_rest_route('timetics/v1', '/occupied-dates', [
            'methods' => 'GET',
            'callback' => [$this, 'get_occupied_dates_endpoint'],
            'permission_callback' => '__return_true',
            'args' => [
                'start_date' => [
                    'type' => 'string',
                    'format' => 'date',
                    'default' => date('Y-m-d')
                ],
                'end_date' => [
                    'type' => 'string',
                    'format' => 'date',
                    'default' => date('Y-m-d', strtotime('+30 days'))
                ]
            ]
        ]);
    }

    /**
     * Get occupied dates endpoint
     */
    public function get_occupied_dates_endpoint($request)
    {
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        
        $occupied_dates = $this->get_occupied_dates($start_date, $end_date);
        
        return [
            'success' => true,
            'occupied_dates' => $occupied_dates,
            'total_occupied' => count($occupied_dates),
            'date_range' => [
                'start' => $start_date,
                'end' => $end_date
            ]
        ];
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
                // Group events by date
                $events_by_date = [];
                foreach ($events as $event) {
                    $event_date = $this->extract_event_date($event);
                    if ($event_date) {
                        $events_by_date[$event_date][] = $event;
                    }
                }

                // Check each date for full occupancy
                foreach ($events_by_date as $date => $date_events) {
                    if ($this->is_date_fully_occupied($date, $date_events)) {
                        $occupied_dates[] = $date;
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Timetics Settings Modifier: Error getting occupied dates - ' . $e->getMessage());
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
            // Use existing Google Calendar integration
            if (class_exists('OccupiedSlotsGoogleCalendarFixed')) {
                $google_calendar = OccupiedSlotsGoogleCalendarFixed::get_instance();
                return $google_calendar->get_events_for_date_range($start_date, $end_date);
            }
            
            // Fallback to direct Google Calendar API
            return $this->get_google_calendar_events_direct($start_date, $end_date);
        } catch (Exception $e) {
            error_log('Timetics Settings Modifier: Error getting Google Calendar events - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Google Calendar events directly
     */
    private function get_google_calendar_events_direct($start_date, $end_date)
    {
        // This would integrate with Google Calendar API directly
        // For now, return mock data for testing
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
     * Check if a date is fully occupied
     */
    private function is_date_fully_occupied($date, $events)
    {
        // Count total events for this date
        $total_events = count($events);
        
        // Get available slots for this date from Timetics
        $available_slots = $this->get_available_slots_for_date($date);
        
        // If no available slots or events >= available slots, date is fully occupied
        return empty($available_slots) || $total_events >= count($available_slots);
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
     * Clear occupied dates cache
     */
    public function clear_occupied_dates_cache()
    {
        wp_cache_delete_group('timetics_occupied_slots');
    }
}
