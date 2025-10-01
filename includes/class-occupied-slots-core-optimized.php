<?php
/**
 * Timetics Occupied Slots Addon - Core Functionality (Performance Optimized)
 * 
 * Handles the core occupied slots functionality with performance optimizations
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsCoreOptimized
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
     * Cached settings to avoid repeated database calls.
     */
    private $cached_settings = null;
    
    /**
     * Performance metrics.
     */
    private $performance_metrics = [];

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
        $this->warm_cache();
    }
    
    /**
     * Warm up caches on initialization.
     */
    private function warm_cache()
    {
        // Pre-load commonly used settings with error handling
        try {
            $this->cached_settings = [
                'enabled' => OccupiedSlotsCache::is_plugin_enabled(),
                'google_calendar_overlap' => OccupiedSlotsCache::get_google_calendar_overlap()
            ];
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to warm cache - ' . $e->getMessage());
            // Set default values if cache fails
            $this->cached_settings = [
                'enabled' => true,
                'google_calendar_overlap' => false
            ];
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks()
    {
        // Check if Timetics is active
        add_action('plugins_loaded', [$this, 'check_timetics_dependency']);
        
        // Backend integration hooks - CRITICAL for proper functionality
        add_filter('timetics_booking_slot', [$this, 'modify_slot_status'], 10, 3);
        add_filter('timetics_schedule_data_for_selected_date', [$this, 'process_schedule_data'], 5, 5);
        
        // Additional filter for the API response to ensure it's always processed
        add_filter('timetics/admin/booking/get_entries', [$this, 'process_api_response'], 10, 1);
        
        // Activation and deactivation hooks
        register_activation_hook(TIMETICS_OCCUPIED_SLOTS_FILE, [$this, 'activate']);
        register_deactivation_hook(TIMETICS_OCCUPIED_SLOTS_FILE, [$this, 'deactivate']);
    }

    /**
     * Check if Timetics plugin is active.
     */
    public function check_timetics_dependency()
    {
        if (!class_exists('Timetics\Core\Integrations\Google\Service\Google_Calendar_Sync')) {
            add_action('admin_notices', [$this, 'timetics_missing_notice']);
            return;
        }
        
        // Add activation notice
        add_action('admin_notices', [$this, 'activation_notice']);
    }

    /**
     * Modify slot status based on occupied slots data (Optimized)
     */
    public function modify_slot_status($slot, $appointment, $booked_entry)
    {
        // Start performance timing
        OccupiedSlotsLogger::start_timer('modify_slot_status');
        
        // Only process if addon is enabled (using cached value)
        if (!$this->cached_settings['enabled']) {
            OccupiedSlotsLogger::end_timer('modify_slot_status');
            return $slot;
        }

        // Check if slot is already unavailable
        if ($slot['status'] === 'unavailable') {
            // Mark as occupied for better UX
            $slot['status'] = 'occupied';
            $slot['occupied_reason'] = 'booked';
            OccupiedSlotsLogger::end_timer('modify_slot_status');
            return $slot;
        }

        // Check for Google Calendar conflicts if enabled (using cached value)
        if ($this->cached_settings['google_calendar_overlap']) {
            $is_google_conflict = $this->check_google_calendar_conflict($slot, $appointment);
            if ($is_google_conflict) {
                $slot['status'] = 'occupied';
                $slot['occupied_reason'] = 'google_calendar';
            }
        }
        
        OccupiedSlotsLogger::end_timer('modify_slot_status');
        return $slot;
    }

    /**
     * Process schedule data to filter out occupied slots (Performance Optimized).
     */
    public function process_schedule_data($days, $staff_id, $meeting_id, $timezone, $additional_data = null)
    {
        // Start performance timing
        OccupiedSlotsLogger::start_timer('process_schedule_data');
        
        // Validate input parameters first
        if (!$this->validate_schedule_parameters($days, $staff_id, $meeting_id, $timezone)) {
            OccupiedSlotsLogger::end_timer('process_schedule_data');
            return $days;
        }

        // Only process if addon is enabled (using cached value)
        if (!$this->cached_settings['enabled']) {
            OccupiedSlotsLogger::end_timer('process_schedule_data');
            return $days;
        }

        // Log for debugging (optimized)
        OccupiedSlotsLogger::debug('Processing schedule data', [
            'meeting_id' => $meeting_id,
            'staff_id' => $staff_id,
            'google_calendar_overlap' => $this->cached_settings['google_calendar_overlap']
        ]);

        $total_slots = 0;
        $processed_slots = 0;
        $filtered_slots = 0;

        // Process each day's slots
        foreach ($days as &$day) {
            // Check if day has no slots from the start and mark as unavailable
            if (isset($day['slots']) && is_array($day['slots']) && empty($day['slots'])) {
                $day['status'] = 'unavailable';
                $day['reason'] = 'no_available_slots';
                OccupiedSlotsLogger::debug('Day marked as unavailable', ['date' => $day['date'], 'reason' => 'no_slots_available']);
                continue; // Skip processing this day
            }
            
            if (isset($day['slots']) && is_array($day['slots'])) {
                $day_slots_count = count($day['slots']);
                $total_slots += $day_slots_count;
                
                foreach ($day['slots'] as &$slot) {
                    $processed_slots++;
                    
                    // Validate slot data first
                    if (!$this->validate_slot_data($slot)) {
                        continue;
                    }
                    
                    // Check if slot is already unavailable (booked)
                    if ($slot['status'] === 'unavailable') {
                        $slot['status'] = 'occupied';
                        $slot['is_occupied'] = true;
                        $slot['occupied_reason'] = 'booked';
                        $slot['tooltip_message'] = $this->get_occupied_tooltip_message('booked');
                        $filtered_slots++;
                        OccupiedSlotsLogger::debug('Slot marked as occupied', ['slot' => $slot['start_time'], 'reason' => 'booked']);
                    }
                    // Check if slot has bookings (capacity full)
                    elseif (isset($slot['booked']) && $slot['booked'] > 0) {
                        $slot['status'] = 'occupied';
                        $slot['is_occupied'] = true;
                        $slot['occupied_reason'] = 'capacity';
                        $slot['tooltip_message'] = $this->get_occupied_tooltip_message('capacity');
                        $filtered_slots++;
                        OccupiedSlotsLogger::debug('Slot marked as occupied', ['slot' => $slot['start_time'], 'reason' => 'capacity', 'booked' => $slot['booked']]);
                    }
                    // Check for Google Calendar conflicts if enabled (using cached value)
                    elseif ($this->cached_settings['google_calendar_overlap']) {
                        $day_date = isset($day['date']) ? $day['date'] : date('Y-m-d');
                        
                        $is_google_conflict = $this->check_google_calendar_conflict_for_slot($slot, $meeting_id, $staff_id, $timezone, $day_date);
                        if ($is_google_conflict) {
                            $slot['status'] = 'occupied';
                            $slot['is_occupied'] = true;
                            $slot['occupied_reason'] = 'google_calendar';
                            $slot['tooltip_message'] = $this->get_occupied_tooltip_message('google_calendar');
                            $filtered_slots++;
                            OccupiedSlotsLogger::debug('Slot marked as occupied', ['slot' => $slot['start_time'], 'reason' => 'google_calendar']);
                        }
                    }
                }

                // If no slots are available after processing, mark the day as unavailable
                $available_slots = array_filter($day['slots'], function($slot) {
                    return $slot['status'] !== 'occupied';
                });
                
                if (empty($available_slots)) {
                    $day['status'] = 'unavailable';
                    $day['reason'] = 'no_available_slots';
                    OccupiedSlotsLogger::debug('Day marked as unavailable', ['date' => $day['date'], 'reason' => 'no_available_slots']);
                }
            }
        }

        // Log performance metrics
        OccupiedSlotsLogger::log_slot_processing($total_slots, $processed_slots, $filtered_slots);
        OccupiedSlotsLogger::end_timer('process_schedule_data');
        
        return $days;
    }

    /**
     * Process API response to filter out occupied slots (Performance Optimized).
     */
    public function process_api_response($data)
    {
        // Start performance timing (with fallback)
        if (class_exists('OccupiedSlotsLogger')) {
            OccupiedSlotsLogger::start_timer('process_api_response');
        }
        
        // Only process if addon is enabled (using cached value with fallback)
        $enabled = true;
        if (isset($this->cached_settings['enabled'])) {
            $enabled = $this->cached_settings['enabled'];
        } elseif (class_exists('OccupiedSlotsCache')) {
            $enabled = OccupiedSlotsCache::is_plugin_enabled();
        } else {
            $enabled = get_option('timetics_occupied_slots_enabled', true);
        }
        
        if (!$enabled) {
            if (class_exists('OccupiedSlotsLogger')) {
                OccupiedSlotsLogger::end_timer('process_api_response');
            }
            return $data;
        }

        // Log for debugging (optimized with fallback)
        if (class_exists('OccupiedSlotsLogger')) {
            OccupiedSlotsLogger::debug('Processing API response');
        } else {
            error_log('Timetics Occupied Slots: Processing API response');
        }

        $total_slots = 0;
        $processed_slots = 0;
        $filtered_slots = 0;

        // Add blocked dates to the response
        if (class_exists('OccupiedSlotsSimpleBlocker')) {
            $blocker = OccupiedSlotsSimpleBlocker::get_instance();
            $blocked_dates = $blocker->get_blocked_dates();
            if (!empty($blocked_dates)) {
                $data['blocked_dates'] = $blocked_dates;
                if (class_exists('OccupiedSlotsLogger')) {
                    OccupiedSlotsLogger::debug('Added blocked dates to API response', ['count' => count($blocked_dates)]);
                }
            }
        }

        // Process the days data
        if (isset($data['days']) && is_array($data['days'])) {
            foreach ($data['days'] as &$day) {
                // Check if day has no slots from the start and mark as unavailable
                if (isset($day['slots']) && is_array($day['slots']) && empty($day['slots'])) {
                    $day['status'] = 'unavailable';
                    $day['reason'] = 'no_available_slots';
                    OccupiedSlotsLogger::debug('Day marked as unavailable', ['date' => $day['date'], 'reason' => 'no_slots_available']);
                    continue; // Skip processing this day
                }

                if (isset($day['slots']) && is_array($day['slots'])) {
                    $day_slots_count = count($day['slots']);
                    $total_slots += $day_slots_count;
                    
                    // Create a new array to store only non-conflicting slots
                    $filtered_slots_array = [];

                    foreach ($day['slots'] as $slot) {
                        $processed_slots++;
                        
                        // Check if slot is already unavailable (booked) - filter it out
                        if ($slot['status'] === 'unavailable') {
                            $filtered_slots++;
                            if (class_exists('OccupiedSlotsLogger')) {
                                OccupiedSlotsLogger::debug('Filtering out slot', ['slot' => $slot['start_time'], 'reason' => 'already_unavailable']);
                            }
                            continue; // Skip this slot
                        }
                        // Check if slot has bookings (capacity full) - filter it out
                        elseif (isset($slot['booked']) && $slot['booked'] > 0) {
                            $filtered_slots++;
                            if (class_exists('OccupiedSlotsLogger')) {
                                OccupiedSlotsLogger::debug('Filtering out slot', ['slot' => $slot['start_time'], 'reason' => 'capacity_full']);
                            }
                            continue; // Skip this slot
                        }
                        // Check for Google Calendar conflicts if enabled (using cached value with fallback)
                        $google_calendar_overlap = false;
                        if (isset($this->cached_settings['google_calendar_overlap'])) {
                            $google_calendar_overlap = $this->cached_settings['google_calendar_overlap'];
                        } elseif (class_exists('OccupiedSlotsCache')) {
                            $google_calendar_overlap = OccupiedSlotsCache::get_google_calendar_overlap();
                        } else {
                            $google_calendar_overlap = function_exists('timetics_get_option') ? timetics_get_option('google_calendar_overlap', false) : false;
                        }
                        
                        if ($google_calendar_overlap) {
                            // We need to get the staff_id and meeting_id from the request
                            // For now, we'll use a fallback approach
                            $day_date = isset($day['date']) ? $day['date'] : date('Y-m-d');

                            // Try to get staff_id and meeting_id from global context or request
                            $staff_id = $this->get_current_staff_id();
                            $meeting_id = $this->get_current_meeting_id();
                            $timezone = $this->get_current_timezone();

                            if ($staff_id && $meeting_id) {
                                $is_google_conflict = $this->check_google_calendar_conflict_for_slot($slot, $meeting_id, $staff_id, $timezone, $day_date);
                                if ($is_google_conflict) {
                                    $filtered_slots++;
                                    if (class_exists('OccupiedSlotsLogger')) {
                                        OccupiedSlotsLogger::debug('Filtering out slot', ['slot' => $slot['start_time'], 'reason' => 'google_calendar_conflict']);
                                    }
                                    continue; // Skip this slot
                                }
                            }
                        }

                        // If we reach here, the slot is available - add it to filtered slots
                        $filtered_slots_array[] = $slot;
                    }

                    // Replace the original slots with filtered slots
                    $day['slots'] = $filtered_slots_array;

                    // If no slots are available after filtering, mark the day as unavailable
                    if (empty($filtered_slots_array)) {
                        $day['status'] = 'unavailable';
                        $day['reason'] = 'no_available_slots';
                        if (class_exists('OccupiedSlotsLogger')) {
                            OccupiedSlotsLogger::debug('Day marked as unavailable', ['date' => $day['date'], 'reason' => 'no_available_slots_after_filtering']);
                        }
                    }
                }
            }
        }

        // Log performance metrics (with fallback)
        if (class_exists('OccupiedSlotsLogger')) {
            OccupiedSlotsLogger::log_slot_processing($total_slots, $processed_slots, $filtered_slots);
            OccupiedSlotsLogger::end_timer('process_api_response');
        } else {
            error_log('Timetics Occupied Slots: API response processed - filtered out conflicting slots');
        }
        
        return $data;
    }

    /**
     * Validate schedule parameters (Optimized).
     */
    private function validate_schedule_parameters($days, $staff_id, $meeting_id, $timezone)
    {
        // Quick validation - return early if invalid
        if (!is_array($days) || empty($days)) {
            return false;
        }
        
        if (!is_numeric($staff_id) || $staff_id <= 0) {
            return false;
        }
        
        if (!is_numeric($meeting_id) || $meeting_id <= 0) {
            return false;
        }
        
        if (empty($timezone) || !is_string($timezone)) {
            return false;
        }
        
        return true;
    }

    /**
     * Validate slot data (Optimized).
     */
    private function validate_slot_data($slot)
    {
        // Quick validation - return early if invalid
        if (!is_array($slot)) {
            return false;
        }
        
        if (!isset($slot['start_time']) || empty($slot['start_time'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Get occupied tooltip message.
     */
    private function get_occupied_tooltip_message($reason)
    {
        $messages = [
            'booked' => __('This time slot is already booked', 'timetics-occupied-slots-addon'),
            'capacity' => __('This time slot is at full capacity', 'timetics-occupied-slots-addon'),
            'google_calendar' => __('This time slot conflicts with Google Calendar', 'timetics-occupied-slots-addon')
        ];
        
        return $messages[$reason] ?? __('This time slot is unavailable', 'timetics-occupied-slots-addon');
    }

    /**
     * Check Google Calendar conflict for a specific slot (Optimized).
     */
    private function check_google_calendar_conflict_for_slot($slot, $meeting_id, $staff_id, $timezone, $day_date)
    {
        try {
            // Use the fixed Google Calendar integration class with fallback
            $google_calendar = OccupiedSlotsGoogleCalendarFixed::get_instance();
            return $google_calendar->check_google_calendar_conflict_for_slot($slot, $meeting_id, $staff_id, $timezone, $day_date);
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Google Calendar conflict check failed - ' . $e->getMessage());
        }
        return false;
    }

    /**
     * Check Google Calendar conflict (Optimized).
     */
    private function check_google_calendar_conflict($slot, $appointment)
    {
        try {
            // Use the fixed Google Calendar integration class with fallback
            $google_calendar = OccupiedSlotsGoogleCalendarFixed::get_instance();
            return $google_calendar->check_google_calendar_conflict($slot, $appointment);
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Google Calendar conflict check failed - ' . $e->getMessage());
        }
        return false;
    }

    /**
     * Get current staff ID (Optimized).
     */
    private function get_current_staff_id()
    {
        try {
            $google_calendar = OccupiedSlotsGoogleCalendarFixed::get_instance();
            return $google_calendar->get_current_staff_id();
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to get staff ID - ' . $e->getMessage());
        }
        
        // Fallback to direct request parsing
        if (isset($_GET['staff_id'])) {
            return absint($_GET['staff_id']);
        }
        if (isset($_POST['staff_id'])) {
            return absint($_POST['staff_id']);
        }
        return null;
    }

    /**
     * Get current meeting ID (Optimized).
     */
    private function get_current_meeting_id()
    {
        try {
            $google_calendar = OccupiedSlotsGoogleCalendarFixed::get_instance();
            return $google_calendar->get_current_meeting_id();
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to get meeting ID - ' . $e->getMessage());
        }
        
        // Fallback to direct request parsing
        if (isset($_GET['meeting_id'])) {
            return absint($_GET['meeting_id']);
        }
        if (isset($_POST['meeting_id'])) {
            return absint($_POST['meeting_id']);
        }
        return null;
    }

    /**
     * Get current timezone (Optimized).
     */
    private function get_current_timezone()
    {
        // Use standardized timezone manager
        if (class_exists('OccupiedSlotsTimezoneManager')) {
            $tz_manager = OccupiedSlotsTimezoneManager::get_instance();
            
            // Check request parameters first
            if (isset($_GET['timezone'])) {
                $requested_tz = sanitize_text_field($_GET['timezone']);
                if ($tz_manager->is_valid_timezone($requested_tz)) {
                    return $requested_tz;
                }
            }
            if (isset($_POST['timezone'])) {
                $requested_tz = sanitize_text_field($_POST['timezone']);
                if ($tz_manager->is_valid_timezone($requested_tz)) {
                    return $requested_tz;
                }
            }
            
            // Use standardized timezone
            return $tz_manager->get_standardized_timezone();
        }
        
        // Fallback to original method
        try {
            $google_calendar = OccupiedSlotsGoogleCalendarFixed::get_instance();
            return $google_calendar->get_current_timezone();
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to get timezone - ' . $e->getMessage());
        }
        
        // Fallback to direct request parsing
        if (isset($_GET['timezone'])) {
            return sanitize_text_field($_GET['timezone']);
        }
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
     * Show notice if Timetics is missing.
     */
    public function timetics_missing_notice()
    {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Timetics Occupied Slots Addon requires the Timetics plugin to be active.', 'timetics-occupied-slots-addon'); ?></p>
        </div>
        <?php
    }

    /**
     * Show activation notice.
     */
    public function activation_notice()
    {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Timetics Occupied Slots Addon has been activated successfully!', 'timetics-occupied-slots-addon'); ?></p>
        </div>
        <?php
    }

    /**
     * Plugin activation.
     */
    public function activate()
    {
        // Clear any existing caches
        OccupiedSlotsCache::clear_all();
        
        // Set default options if they don't exist
        add_option('timetics_occupied_slots_enabled', true);
        add_option('timetics_occupied_slots_tooltip', true);
        add_option('timetics_occupied_slots_color', '#666666');
        add_option('timetics_occupied_slots_text_color', '#ffffff');
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate()
    {
        // Clear caches on deactivation
        OccupiedSlotsCache::clear_all();
    }
    
    /**
     * Get performance metrics.
     */
    public function get_performance_metrics()
    {
        return [
            'cached_settings' => $this->cached_settings,
            'logger_metrics' => OccupiedSlotsLogger::get_performance_metrics(),
            'cache_stats' => OccupiedSlotsCache::get_cache_stats()
        ];
    }
}
