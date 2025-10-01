<?php
/**
 * Timetics Occupied Slots Addon - Compatibility Layer
 * 
 * Handles compatibility between base Timetics plugin and Pro version
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsCompatibilityLayer
{
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
        $this->init_compatibility_hooks();
    }

    /**
     * Initialize compatibility hooks.
     */
    private function init_compatibility_hooks()
    {
        // Ensure our hooks run after base plugin initialization
        add_action('init', [$this, 'setup_compatibility'], 20);
        
        // Handle script conflicts
        add_action('wp_enqueue_scripts', [$this, 'handle_script_conflicts'], 25);
        
        // Handle REST API conflicts
        add_action('rest_api_init', [$this, 'handle_rest_api_conflicts'], 25);
        
        // Handle database conflicts
        add_action('init', [$this, 'handle_database_conflicts'], 30);
    }

    /**
     * Setup compatibility layer.
     */
    public function setup_compatibility()
    {
        // Check if base Timetics plugin is active
        if (!class_exists('Timetics')) {
            return;
        }

        // Add compatibility filters
        $this->add_compatibility_filters();
        
        // Handle Pro version compatibility
        if (class_exists('TimeticsPro')) {
            $this->handle_pro_compatibility();
        }
    }

    /**
     * Add compatibility filters.
     */
    private function add_compatibility_filters()
    {
        // Filter booking entries to include blocked dates
        add_filter('timetics_schedule_data_for_selected_date', [$this, 'add_blocked_dates_to_schedule'], 10, 4);
        
        // Filter booking validation to check for blocked dates
        add_filter('timetics_booking_validation', [$this, 'validate_against_blocked_dates'], 10, 3);
        
        // Filter frontend data to include blocked dates
        add_filter('timetics_frontned_localize_data', [$this, 'add_blocked_dates_to_frontend_data'], 10, 1);
    }

    /**
     * Handle Pro version compatibility.
     */
    private function handle_pro_compatibility()
    {
        // Add Pro-specific filters
        add_filter('timetics_pro_booking_validation', [$this, 'validate_pro_booking_against_blocked_dates'], 10, 3);
        
        // Handle Pro-specific hooks
        add_action('timetics_pro_booking_created', [$this, 'handle_pro_booking_created'], 10, 2);
    }

    /**
     * Handle script conflicts.
     */
    public function handle_script_conflicts()
    {
        // Ensure our scripts load after base plugin scripts
        if (wp_script_is('timetics-frontend-scripts', 'enqueued')) {
            // Add our blocked dates data to the base plugin's localized data
            add_filter('timetics_frontned_localize_data', [$this, 'add_blocked_dates_to_frontend_data'], 10, 1);
        }
    }

    /**
     * Handle REST API conflicts.
     */
    public function handle_rest_api_conflicts()
    {
        // Ensure our REST API routes don't conflict with base plugin
        // This is handled by using unique namespace in main plugin file
    }

    /**
     * Handle database conflicts.
     */
    public function handle_database_conflicts()
    {
        // Ensure our database queries don't conflict with base plugin
        // Add any necessary database compatibility code here
    }

    /**
     * Add blocked dates to schedule data.
     */
    public function add_blocked_dates_to_schedule($days, $staff_id, $meeting_id, $timezone)
    {
        if (!class_exists('OccupiedSlotsSimpleBlocker')) {
            return $days;
        }

        $blocker = OccupiedSlotsSimpleBlocker::get_instance();
        $blocked_dates = $blocker->get_blocked_dates();

        // Mark blocked dates in the schedule
        foreach ($days as $date => $day_data) {
            if (in_array($date, $blocked_dates)) {
                $days[$date]['blocked'] = true;
                $days[$date]['blocked_reason'] = 'occupied_slot';
            }
        }

        return $days;
    }

    /**
     * Validate booking against blocked dates.
     */
    public function validate_against_blocked_dates($validation_result, $booking_data, $meeting_id)
    {
        if (!class_exists('OccupiedSlotsSimpleBlocker')) {
            return $validation_result;
        }

        $blocker = OccupiedSlotsSimpleBlocker::get_instance();
        $blocked_dates = $blocker->get_blocked_dates();

        $booking_date = isset($booking_data['start_date']) ? $booking_data['start_date'] : '';
        
        if (in_array($booking_date, $blocked_dates)) {
            return new WP_Error(
                'blocked_date',
                __('This date is blocked due to occupied slots.', 'timetics-occupied-slots'),
                ['status' => 400]
            );
        }

        return $validation_result;
    }

    /**
     * Add blocked dates to frontend data.
     */
    public function add_blocked_dates_to_frontend_data($data)
    {
        if (!class_exists('OccupiedSlotsSimpleBlocker')) {
            return $data;
        }

        $blocker = OccupiedSlotsSimpleBlocker::get_instance();
        $blocked_dates = $blocker->get_blocked_dates();

        $data['blocked_dates'] = $blocked_dates;
        $data['occupied_slots_enabled'] = true;

        return $data;
    }

    /**
     * Validate Pro booking against blocked dates.
     */
    public function validate_pro_booking_against_blocked_dates($validation_result, $booking_data, $meeting_id)
    {
        // Same validation as base plugin
        return $this->validate_against_blocked_dates($validation_result, $booking_data, $meeting_id);
    }

    /**
     * Handle Pro booking created.
     */
    public function handle_pro_booking_created($booking_id, $booking_data)
    {
        // Add any Pro-specific handling for occupied slots
        if (class_exists('OccupiedSlotsLogger')) {
            OccupiedSlotsLogger::info('Pro booking created', [
                'booking_id' => $booking_id,
                'booking_data' => $booking_data
            ]);
        }
    }

    /**
     * Get compatibility status.
     */
    public function get_compatibility_status()
    {
        $status = [
            'base_plugin_active' => class_exists('Timetics'),
            'pro_plugin_active' => class_exists('TimeticsPro'),
            'occupied_slots_active' => class_exists('OccupiedSlotsSimpleBlocker'),
            'timezone_manager_active' => class_exists('OccupiedSlotsTimezoneManager'),
            'compatibility_layer_active' => true
        ];

        return $status;
    }

    /**
     * Check for conflicts.
     */
    public function check_for_conflicts()
    {
        $conflicts = [];

        // Check for script conflicts
        if (wp_script_is('timetics-frontend-scripts', 'enqueued') && 
            wp_script_is('timetics-occupied-slots-react-integration', 'enqueued')) {
            // Check if scripts are loaded in correct order
            global $wp_scripts;
            $timetics_priority = isset($wp_scripts->registered['timetics-frontend-scripts']->extra['priority']) ? 
                $wp_scripts->registered['timetics-frontend-scripts']->extra['priority'] : 10;
            $occupied_slots_priority = isset($wp_scripts->registered['timetics-occupied-slots-react-integration']->extra['priority']) ? 
                $wp_scripts->registered['timetics-occupied-slots-react-integration']->extra['priority'] : 10;
            
            if ($occupied_slots_priority <= $timetics_priority) {
                $conflicts[] = 'Script loading order conflict detected';
            }
        }

        return $conflicts;
    }
}
