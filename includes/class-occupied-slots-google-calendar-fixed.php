<?php
/**
 * Timetics Occupied Slots Addon - Google Calendar Integration (Fixed)
 * 
 * Handles Google Calendar integration using the correct Timetics API methods
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsGoogleCalendarFixed
{
    /**
     * Singleton instance.
     */
    private static $instance = null;
    
    /**
     * Cached Google Sync instance.
     */
    private $google_sync = null;
    
    /**
     * Cache for Google Calendar events.
     */
    private $events_cache = [];

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
        // Initialize lazy loading
    }
    
    /**
     * Get Google Sync instance (lazy loading).
     */
    private function get_google_sync()
    {
        if ($this->google_sync === null) {
            if (!class_exists('Timetics\Core\Integrations\Google\Service\Google_Calendar_Sync')) {
                error_log('Timetics Occupied Slots: Google Calendar Sync class not found');
                return null;
            }
            
            try {
                $this->google_sync = \Timetics\Core\Integrations\Google\Service\Google_Calendar_Sync::instance();
            } catch (Exception $e) {
                error_log('Timetics Occupied Slots: Failed to initialize Google Calendar Sync - ' . $e->getMessage());
                return null;
            }
        }
        
        return $this->google_sync;
    }

    /**
     * Check Google Calendar conflict (Fixed).
     */
    public function check_google_calendar_conflict($slot, $appointment)
    {
        try {
            // Check if Google Calendar overlap is enabled
            if (!function_exists('timetics_get_option') || !timetics_get_option('google_calendar_overlap', false)) {
                return false;
            }

            $google_sync = $this->get_google_sync();
            if (!$google_sync) {
                return false;
            }

            // Get appointment details (handle both object and array)
            $staff_id = null;
            $meeting_id = null;
            $timezone = 'UTC';
            
            if (is_object($appointment)) {
                // Handle Appointment object
                $staff_id = method_exists($appointment, 'get_staff_id') ? $appointment->get_staff_id() : null;
                $meeting_id = method_exists($appointment, 'get_meeting_id') ? $appointment->get_meeting_id() : null;
                $timezone = method_exists($appointment, 'get_timezone') ? $appointment->get_timezone() : 'UTC';
            } elseif (is_array($appointment)) {
                // Handle array
                $staff_id = isset($appointment['staff_id']) ? $appointment['staff_id'] : null;
                $meeting_id = isset($appointment['meeting_id']) ? $appointment['meeting_id'] : null;
                $timezone = isset($appointment['timezone']) ? $appointment['timezone'] : 'UTC';
            }
            
            $slot_date = isset($slot['date']) ? $slot['date'] : date('Y-m-d');

            if (!$staff_id || !$meeting_id) {
                return false;
            }

            return $this->check_google_calendar_conflict_for_slot($slot, $meeting_id, $staff_id, $timezone, $slot_date);
            
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Google Calendar check failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check Google Calendar conflict for a specific slot (Fixed).
     */
    public function check_google_calendar_conflict_for_slot($slot, $meeting_id, $staff_id, $timezone, $slot_date = null)
    {
        try {
            // Check if Google Calendar overlap is enabled
            if (!function_exists('timetics_get_option') || !timetics_get_option('google_calendar_overlap', false)) {
                return false;
            }

            $google_sync = $this->get_google_sync();
            if (!$google_sync) {
                return false;
            }

            // Use slot date if provided, otherwise use current date
            $slot_date = $slot_date ?: date('Y-m-d');
            
            // Get Google Calendar events for the date using the correct Timetics API
            $google_events = $this->get_google_calendar_events($staff_id, $slot_date, $timezone);

            // Check for conflicts
            $has_conflict = $this->check_slot_against_google_events($slot, $google_events, $slot_date, $timezone);
            
            return $has_conflict;
            
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Google Calendar check failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Google Calendar events using the correct Timetics API.
     */
    private function get_google_calendar_events($staff_id, $date, $timezone)
    {
        // Check cache first
        $cache_key = "google_events_{$staff_id}_{$date}";
        if (isset($this->events_cache[$cache_key])) {
            return $this->events_cache[$cache_key];
        }

        try {
            // Use the Timetics Calendar class directly
            if (!class_exists('Timetics\Core\Integrations\Google\Service\Calendar')) {
                return [];
            }

            $calendar = new \Timetics\Core\Integrations\Google\Service\Calendar();
            
            // Create date range for the specific date
            $date_obj_start = new DateTime($date . ' 00:00:00', new DateTimeZone($timezone));
            $date_obj_end = new DateTime($date . ' 23:59:59', new DateTimeZone($timezone));

            // Use the correct get_events method with proper parameters
            $events = $calendar->get_events($staff_id, [
                'timeMin' => rawurlencode($date_obj_start->format(DateTime::RFC3339)),
                'timeMax' => rawurlencode($date_obj_end->format(DateTime::RFC3339)),
                'orderBy' => 'startTime',
                'singleEvents' => 'true',
                'timeZone' => $timezone,
            ]);

            // Cache the events
            $this->events_cache[$cache_key] = $events;
            
            return $events;
            
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Error getting Google Calendar events - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check slot against Google events (Fixed).
     */
    private function check_slot_against_google_events($slot, $google_events, $slot_date, $timezone)
    {
        if (empty($google_events) || !is_array($google_events)) {
            return false;
        }

        try {
            $slot_start_time = $slot['start_time'];
            $slot_end_time = isset($slot['end_time']) ? $slot['end_time'] : $this->calculate_end_time($slot_start_time);
            
            // Create datetime objects for comparison
            $slot_datetime = $this->create_datetime($slot_date, $slot_start_time, $timezone);
            $slot_end_datetime = $this->create_datetime($slot_date, $slot_end_time, $timezone);
            
            if (!$slot_datetime || !$slot_end_datetime) {
                return false;
            }

            foreach ($google_events as $event) {
                if (!$this->is_valid_event($event)) {
                    continue;
                }

                // Use the correct event structure from Timetics
                $event_start = $this->parse_timetics_event_time($event['start_time'], $event['start_date'], $timezone);
                $event_end = $this->parse_timetics_event_time($event['end_time'], $event['end_date'], $timezone);
                
                if (!$event_start || !$event_end) {
                    continue;
                }

                // Check for time overlap
                if ($this->times_overlap($slot_datetime, $slot_end_datetime, $event_start, $event_end)) {
                    return true;
                }
            }

            return false;
            
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Error checking slot against Google events - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Parse Timetics event time format.
     */
    private function parse_timetics_event_time($time, $date, $timezone)
    {
        try {
            $datetime_string = $date . ' ' . $time;
            $datetime = new DateTime($datetime_string, new DateTimeZone($timezone));
            return $datetime;
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to parse event time - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if times overlap.
     */
    private function times_overlap($start1, $end1, $start2, $end2)
    {
        return $start1 < $end2 && $start2 < $end1;
    }

    /**
     * Create datetime object.
     */
    private function create_datetime($date, $time, $timezone)
    {
        try {
            $datetime_string = $date . ' ' . $time;
            $datetime = new DateTime($datetime_string, new DateTimeZone($timezone));
            return $datetime;
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to create datetime - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if event is valid.
     */
    private function is_valid_event($event)
    {
        return is_array($event) && 
               isset($event['start_time']) && 
               isset($event['start_date']) &&
               !empty($event['start_time']) && 
               !empty($event['start_date']);
    }

    /**
     * Calculate end time from start time.
     */
    private function calculate_end_time($start_time)
    {
        try {
            $start = new DateTime($start_time);
            $end = clone $start;
            $end->add(new DateInterval('PT1H')); // Add 1 hour
            return $end->format('H:i:s');
        } catch (Exception $e) {
            return '23:59:59'; // Fallback
        }
    }

    /**
     * Convert time to 24h format.
     */
    public function convert_to_24h_format($time_12h)
    {
        try {
            $time_24h = date('H:i:s', strtotime($time_12h));
            return $time_24h;
        } catch (Exception $e) {
            return $time_12h; // Return original if conversion fails
        }
    }

    /**
     * Get current staff ID.
     */
    public function get_current_staff_id()
    {
        // Check GET parameter first (most common)
        if (isset($_GET['staff_id'])) {
            return absint($_GET['staff_id']);
        }
        
        // Check POST parameter
        if (isset($_POST['staff_id'])) {
            return absint($_POST['staff_id']);
        }
        
        // Check global context
        global $wp_query;
        if (isset($wp_query->query_vars['staff_id'])) {
            return absint($wp_query->query_vars['staff_id']);
        }
        
        return null;
    }

    /**
     * Get current meeting ID.
     */
    public function get_current_meeting_id()
    {
        // Check GET parameter first (most common)
        if (isset($_GET['meeting_id'])) {
            return absint($_GET['meeting_id']);
        }
        
        // Check POST parameter
        if (isset($_POST['meeting_id'])) {
            return absint($_POST['meeting_id']);
        }
        
        // Check global context
        global $wp_query;
        if (isset($wp_query->query_vars['meeting_id'])) {
            return absint($wp_query->query_vars['meeting_id']);
        }
        
        return null;
    }

    /**
     * Get current timezone.
     */
    public function get_current_timezone()
    {
        // Check GET parameter first
        if (isset($_GET['timezone'])) {
            return sanitize_text_field($_GET['timezone']);
        }
        
        // Check POST parameter
        if (isset($_POST['timezone'])) {
            return sanitize_text_field($_POST['timezone']);
        }
        
        // Use WordPress timezone function for dynamic detection
        if (function_exists('wp_timezone_string')) {
            return wp_timezone_string();
        }
        
        // Fallback to WordPress timezone option
        $timezone = get_option('timezone_string');
        if ($timezone) {
            return $timezone;
        }
        
        // Final fallback to UTC
        return 'UTC';
    }
    
    /**
     * Clear events cache.
     */
    public function clear_events_cache()
    {
        $this->events_cache = [];
    }
    
    /**
     * Get performance metrics.
     */
    public function get_performance_metrics()
    {
        return [
            'cached_events_count' => count($this->events_cache),
            'google_sync_initialized' => $this->google_sync !== null
        ];
    }
}
