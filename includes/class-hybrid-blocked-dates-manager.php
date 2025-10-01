<?php
/**
 * Hybrid Blocked Dates Manager
 * 
 * Combines multiple data sources for 100% accurate blocked dates:
 * 1. Real-time Timetics bookings (always accurate)
 * 2. Cached Google Calendar events (with smart invalidation)
 * 3. Manual overrides (for special cases)
 * 
 * @package Timetics_Occupied_Slots_Addon
 */

class Hybrid_Blocked_Dates_Manager {
    
    private static $instance = null;
    private $cache_manager;
    
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
        $this->cache_manager = Smart_Cache_Manager::get_instance();
    }
    
    /**
     * Get blocked dates using hybrid approach with Google Calendar priority
     */
    public function get_blocked_dates($start_date, $end_date) {
        // Try cache first
        $cached_dates = $this->cache_manager->get_cached_blocked_dates($start_date, $end_date);
        if ($cached_dates !== false) {
            return $cached_dates;
        }
        
        // Build blocked dates from multiple sources with proper priority
        $blocked_dates = [];
        
        // 1. Google Calendar events (HIGHEST PRIORITY - blocks individual timeslots)
        $google_blocked_dates = $this->get_google_calendar_blocked_dates_with_priority($start_date, $end_date);
        $blocked_dates = array_merge($blocked_dates, $google_blocked_dates);
        
        // 2. Timetics bookings (only blocks if day is fully occupied)
        $timetics_dates = $this->get_timetics_blocked_dates($start_date, $end_date);
        $blocked_dates = array_merge($blocked_dates, $timetics_dates);
        
        // 3. Manual overrides (if any)
        $manual_dates = $this->get_manual_blocked_dates($start_date, $end_date);
        $blocked_dates = array_merge($blocked_dates, $manual_dates);
        
        // Remove duplicates and sort
        $blocked_dates = array_unique($blocked_dates);
        sort($blocked_dates);
        
        // Cache the result
        $this->cache_manager->set_cached_blocked_dates($start_date, $end_date, $blocked_dates);
        
        return $blocked_dates;
    }
    
    /**
     * Get Timetics blocked dates (real-time)
     */
    private function get_timetics_blocked_dates($start_date, $end_date) {
        $blocked_dates = [];
        
        // Get all bookings in date range
        $bookings = get_posts([
            'post_type' => 'timetics-booking',
            'post_status' => ['publish', 'completed', 'pending'],
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
            if ($this->is_date_fully_occupied($date, $count)) {
                $blocked_dates[] = $date;
            }
        }
        
        return $blocked_dates;
    }
    
    /**
     * Get Google Calendar blocked dates with priority (blocks individual timeslots)
     */
    private function get_google_calendar_blocked_dates_with_priority($start_date, $end_date) {
        // Check if Google Calendar integration is enabled
        if (!function_exists('timetics_get_option') || !timetics_get_option('google_calendar_overlap', false)) {
            return [];
        }
        
        // Use cache for Google Calendar (less critical for real-time accuracy)
        $cache_key = 'timetics_google_calendar_priority_' . md5($start_date . '_' . $end_date);
        $cached_dates = get_transient($cache_key);
        
        if ($cached_dates !== false) {
            return $cached_dates;
        }
        
        $blocked_dates = [];
        
        try {
            // Get Google Calendar events
            $calendar = new \Timetics\Core\Integrations\Google\Service\Calendar();
            $events = $calendar->get_events(get_current_user_id(), [
                'timeMin' => rawurlencode(date('c', strtotime($start_date))),
                'timeMax' => rawurlencode(date('c', strtotime($end_date))),
            ]);
            
            if (!is_wp_error($events) && !empty($events)) {
                // Group events by date and check timeslot conflicts
                $dates_with_events = [];
                foreach ($events as $event) {
                    $event_date = $event['start_date'] ?? null;
                    if ($event_date) {
                        if (!isset($dates_with_events[$event_date])) {
                            $dates_with_events[$event_date] = [];
                        }
                        $dates_with_events[$event_date][] = $event;
                    }
                }
                
                // Check each date for timeslot conflicts
                foreach ($dates_with_events as $date => $date_events) {
                    if ($this->has_google_calendar_timeslot_conflicts($date, $date_events)) {
                        $blocked_dates[] = $date;
                    }
                }
            }
            
            // Cache for 10 minutes (shorter than Timetics cache)
            set_transient($cache_key, $blocked_dates, 10 * MINUTE_IN_SECONDS);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Timetics Hybrid Manager: Google Calendar priority error - ' . $e->getMessage());
            }
        }
        
        return $blocked_dates;
    }

    /**
     * Get Google Calendar blocked dates (with smart caching) - DEPRECATED
     */
    private function get_google_calendar_blocked_dates($start_date, $end_date) {
        // Check if Google Calendar integration is enabled
        if (!function_exists('timetics_get_option') || !timetics_get_option('google_calendar_overlap', false)) {
            return [];
        }
        
        // Use cache for Google Calendar (less critical for real-time accuracy)
        $cache_key = 'timetics_google_calendar_' . md5($start_date . '_' . $end_date);
        $cached_dates = get_transient($cache_key);
        
        if ($cached_dates !== false) {
            return $cached_dates;
        }
        
        $blocked_dates = [];
        
        try {
            // Get Google Calendar events
            $calendar = new \Timetics\Core\Integrations\Google\Service\Calendar();
            $events = $calendar->get_events(get_current_user_id(), [
                'timeMin' => rawurlencode(date('c', strtotime($start_date))),
                'timeMax' => rawurlencode(date('c', strtotime($end_date))),
            ]);
            
            if (!is_wp_error($events) && !empty($events)) {
                // Group events by date
                $dates_with_events = [];
                foreach ($events as $event) {
                    $event_date = $event['start_date'] ?? null;
                    if ($event_date) {
                        if (!isset($dates_with_events[$event_date])) {
                            $dates_with_events[$event_date] = 0;
                        }
                        $dates_with_events[$event_date]++;
                    }
                }
                
                // Check which dates are fully occupied by Google events
                foreach ($dates_with_events as $date => $event_count) {
                    if ($this->is_date_fully_occupied_by_google_events($date, $event_count)) {
                        $blocked_dates[] = $date;
                    }
                }
            }
            
            // Cache for 10 minutes (shorter than Timetics cache)
            set_transient($cache_key, $blocked_dates, 10 * MINUTE_IN_SECONDS);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Timetics Hybrid Manager: Google Calendar error - ' . $e->getMessage());
            }
        }
        
        return $blocked_dates;
    }
    
    /**
     * Get manual blocked dates (for special cases)
     */
    private function get_manual_blocked_dates($start_date, $end_date) {
        // This could be used for holidays, maintenance days, etc.
        // For now, return empty array (no manual blocking as requested)
        return [];
    }
    
    /**
     * Check if a date is fully occupied by Timetics bookings
     */
    private function is_date_fully_occupied($date, $booking_count) {
        // Get business hours and slot configuration
        $timetics_settings = get_option('timetics_settings', []);
        $availability = $timetics_settings['availability'] ?? [];
        $slot_interval = $timetics_settings['slot_interval'] ?? 1;
        
        // Get the day of the week for this date
        $day_of_week = date('D', strtotime($date));
        
        // Check if this day is available
        if (!isset($availability[$day_of_week]) || empty($availability[$day_of_week])) {
            return true; // Day is not available
        }
        
        // Calculate total available slots for this day
        $day_availability = $availability[$day_of_week][0];
        $start_time = strtotime($day_availability['start']);
        $end_time = strtotime($day_availability['end']);
        $total_slots = ($end_time - $start_time) / (60 * 60 * $slot_interval);
        
        // If we have more bookings than available slots, the day is fully occupied
        return $booking_count >= $total_slots;
    }
    
    /**
     * Check if a date is fully occupied by Google Calendar events
     */
    private function is_date_fully_occupied_by_google_events($date, $event_count) {
        // Use same logic as Timetics bookings
        return $this->is_date_fully_occupied($date, $event_count);
    }

    /**
     * Check if Google Calendar events have timeslot conflicts (HIGH PRIORITY)
     */
    private function has_google_calendar_timeslot_conflicts($date, $events) {
        // Get business hours and slot configuration
        $timetics_settings = get_option('timetics_settings', []);
        $availability = $timetics_settings['availability'] ?? [];
        $slot_interval = $timetics_settings['slot_interval'] ?? 1;
        
        // Get the day of the week for this date
        $day_of_week = date('D', strtotime($date));
        
        // Check if this day is available
        if (!isset($availability[$day_of_week]) || empty($availability[$day_of_week])) {
            return true; // Day is not available
        }
        
        // Get business hours for this day
        $day_availability = $availability[$day_of_week][0];
        $start_time = strtotime($day_availability['start']);
        $end_time = strtotime($day_availability['end']);
        
        // Generate all available timeslots for this day
        $available_slots = [];
        for ($time = $start_time; $time < $end_time; $time += (60 * 60 * $slot_interval)) {
            $available_slots[] = $time;
        }
        
        // Check if Google Calendar events conflict with any available slots
        $conflicted_slots = 0;
        foreach ($available_slots as $slot_time) {
            $slot_end_time = $slot_time + (60 * 60 * $slot_interval);
            
            // Check if any Google Calendar event overlaps with this slot
            foreach ($events as $event) {
                $event_start = strtotime($event['start_time']);
                $event_end = strtotime($event['end_time']);
                
                // If Google Calendar event overlaps with this slot, mark it as conflicted
                if (($event_start < $slot_end_time) && ($event_end > $slot_time)) {
                    $conflicted_slots++;
                    break; // No need to check more events for this slot
                }
            }
        }
        
        // If Google Calendar events conflict with any available slots, block the date
        // This gives Google Calendar HIGHEST PRIORITY over Timetics bookings
        return $conflicted_slots > 0;
    }
    
    /**
     * Force refresh all caches
     */
    public function force_refresh_all_caches() {
        $this->cache_manager->clear_date_range_caches();
        
        // Clear Google Calendar caches
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timetics_google_calendar_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_timetics_google_calendar_%'");
    }
    
    /**
     * Get accuracy report
     */
    public function get_accuracy_report($start_date, $end_date) {
        $report = [
            'date_range' => ['start' => $start_date, 'end' => $end_date],
            'sources' => [
                'timetics_bookings' => $this->get_timetics_blocked_dates($start_date, $end_date),
                'google_calendar' => $this->get_google_calendar_blocked_dates($start_date, $end_date),
                'manual_overrides' => $this->get_manual_blocked_dates($start_date, $end_date)
            ],
            'cache_status' => $this->cache_manager->get_cache_stats(),
            'last_updated' => current_time('mysql')
        ];
        
        return $report;
    }
}
