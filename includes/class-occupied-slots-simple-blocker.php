<?php
/**
 * Timetics Occupied Slots Addon - Simple Date Blocker
 * 
 * Simple implementation that modifies existing AJAX requests to include blocked dates
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsSimpleBlocker
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
        // Hook into the main Timetics booking entries API
        add_filter('timetics/admin/booking/get_entries', [$this, 'add_blocked_dates_to_response'], 10, 1);
        
        // Hook into the schedule data filter (already used by occupied slots)
        add_filter('timetics_schedule_data_for_selected_date', [$this, 'add_blocked_dates_to_schedule'], 10, 4);
        
        // Add debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('init', [$this, 'log_debug_info']);
        }
    }

    /**
     * Add blocked dates to the main API response
     */
    public function add_blocked_dates_to_response($data)
    {
        if (!is_array($data) || !isset($data['days'])) {
            return $data;
        }

        // Get blocked dates from Timetics and Google Calendar
        $blocked_dates = $this->get_blocked_dates();
        
        if (!empty($blocked_dates)) {
            // Add blocked dates to the response
            $data['blocked_dates'] = $blocked_dates;
            
            // Log for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Timetics Simple Blocker: Added ' . count($blocked_dates) . ' blocked dates to API response');
            }
        }

        return $data;
    }

    /**
     * Add blocked dates to schedule data
     */
    public function add_blocked_dates_to_schedule($days, $staff_id, $meeting_id, $timezone)
    {
        if (!is_array($days)) {
            return $days;
        }

        // Get blocked dates
        $blocked_dates = $this->get_blocked_dates();
        
        if (empty($blocked_dates)) {
            return $days;
        }

        // Mark blocked dates in the schedule
        foreach ($days as $date => $day_data) {
            if (in_array($date, $blocked_dates)) {
                // Mark the entire day as blocked
                if (isset($day_data['slots'])) {
                    foreach ($day_data['slots'] as $slot_key => $slot) {
                        $days[$date]['slots'][$slot_key]['status'] = 'blocked';
                        $days[$date]['slots'][$slot_key]['blocked_reason'] = 'date_fully_occupied';
                        $days[$date]['slots'][$slot_key]['blocked_message'] = 'This date is fully occupied';
                    }
                }
                
                // Add day-level blocking
                $days[$date]['is_blocked'] = true;
                $days[$date]['blocked_reason'] = 'date_fully_occupied';
                $days[$date]['blocked_message'] = 'This date is fully occupied';
            }
        }

        return $days;
    }

    /**
     * Get blocked dates from multiple sources
     */
    public function get_blocked_dates()
    {
        $blocked_dates = [];

        // Get blocked dates from Timetics bookings
        $timetics_blocked = $this->get_timetics_blocked_dates();
        if (!empty($timetics_blocked)) {
            $blocked_dates = array_merge($blocked_dates, $timetics_blocked);
        }

        // Get blocked dates from Google Calendar
        $google_blocked = $this->get_google_calendar_blocked_dates();
        if (!empty($google_blocked)) {
            $blocked_dates = array_merge($blocked_dates, $google_blocked);
        }

        // Remove duplicates and sort
        $blocked_dates = array_unique($blocked_dates);
        sort($blocked_dates);

        return $blocked_dates;
    }

    /**
     * Get blocked dates from Timetics bookings
     */
    private function get_timetics_blocked_dates()
    {
        // Get dates that are fully booked using Timetics post type
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+30 days'));
        
        // Query Timetics bookings using WordPress post type
        $bookings = get_posts([
            'post_type' => 'timetics-booking',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_tt_booking_start_date',
                    'value' => [$start_date, $end_date],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                ]
            ],
            'numberposts' => -1,
            'fields' => 'ids'
        ]);

        $blocked_dates = [];
        
        // Group bookings by date
        $dates_with_bookings = [];
        foreach ($bookings as $booking_id) {
            $booking_date = get_post_meta($booking_id, '_tt_booking_start_date', true);
            if ($booking_date) {
                if (!isset($dates_with_bookings[$booking_date])) {
                    $dates_with_bookings[$booking_date] = 0;
                }
                $dates_with_bookings[$booking_date]++;
            }
        }
        
        // Check which dates are fully occupied
        foreach ($dates_with_bookings as $date => $count) {
            if ($this->is_date_fully_occupied($date)) {
                $blocked_dates[] = $date;
            }
        }

        return $blocked_dates;
    }

    /**
     * Get blocked dates from Google Calendar
     */
    private function get_google_calendar_blocked_dates()
    {
        // Check if Google Calendar integration is enabled
        if (!function_exists('timetics_get_option') || !timetics_get_option('google_calendar_overlap', false)) {
            return [];
        }

        // Use existing Google Calendar integration
        if (class_exists('Timetics\Core\Integrations\Google\Service\Google_Calendar_Sync')) {
            try {
                $google_sync = \Timetics\Core\Integrations\Google\Service\Google_Calendar_Sync::instance();
                
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d', strtotime('+30 days'));
                
                // Get Google Calendar events - use the existing method
                $events = $google_sync->get_events_from_google([]);
                
                $blocked_dates = [];
                
                foreach ($events as $event) {
                    $event_date = $this->extract_date_from_event($event);
                    if ($event_date && $this->is_date_fully_occupied_by_google($event_date, $events)) {
                        $blocked_dates[] = $event_date;
                    }
                }
                
                return $blocked_dates;
                
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Timetics Simple Blocker: Google Calendar error - ' . $e->getMessage());
                }
            }
        }

        return [];
    }

    /**
     * Check if a date is fully occupied by Timetics bookings
     */
    private function is_date_fully_occupied($date)
    {
        // Get all bookings for this date using Timetics post type
        $bookings = get_posts([
            'post_type' => 'timetics-booking',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_tt_booking_start_date',
                    'value' => $date,
                    'compare' => '='
                ]
            ],
            'numberposts' => -1,
            'fields' => 'ids'
        ]);

        $booking_count = count($bookings);
        
        // For now, if there are any bookings, consider the date occupied
        // This can be enhanced later to check if all available slots are taken
        return $booking_count > 0;
    }

    /**
     * Check if a date is fully occupied by Google Calendar events
     */
    private function is_date_fully_occupied_by_google($date, $events)
    {
        // Count events for this date
        $date_events = array_filter($events, function($event) use ($date) {
            $event_date = $this->extract_date_from_event($event);
            return $event_date === $date;
        });

        // If there are events covering the entire day, consider it blocked
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
     * Get available slots for a specific date
     * This is a simplified version - would need to integrate with Timetics slot logic
     */
    private function get_available_slots_for_date($date)
    {
        // This would need to integrate with Timetics' slot generation logic
        // For now, return empty array to indicate no slots available
        return [];
    }

    /**
     * Log debug information
     */
    public function log_debug_info()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Timetics Simple Blocker: Plugin initialized and ready');
        }
    }
}
