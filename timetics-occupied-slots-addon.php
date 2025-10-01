<?php
/**
 * Plugin Name: Timetics Occupied Slots Addon
 * Plugin URI: https://arraytics.com/timetics/
 * Description: Shows occupied slots from Google Calendar on the frontend so customers can see which times are unavailable before attempting to book.
 * Version: 1.6.3
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Author: Obala Joseph Ivan
 * Author URI: https://codprez.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: timetics-occupied-slots-addon
 * Domain Path: /languages
 *
 * @package Timetics_Occupied_Slots_Addon
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

// Define plugin file constant
if (!defined('TIMETICS_OCCUPIED_SLOTS_FILE')) {
    define('TIMETICS_OCCUPIED_SLOTS_FILE', __FILE__);
}

// Include required classes
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-timezone-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-compatibility-layer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-cache.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-logger.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-core-optimized.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-settings-modifier.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-google-calendar-fixed.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-simple-blocker.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-frontend-react.php';

// Performance optimization classes
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-cache-optimized.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-asset-optimizer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-performance-monitor.php';

// Senior-level intelligent detection system
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-intelligent-detector.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-cache-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-admin-dashboard.php';

// Full year support (backward compatible)
require_once plugin_dir_path(__FILE__) . 'includes/class-occupied-slots-full-year-manager.php';

/**
 * Timetics Occupied Slots Addon
 * Shows occupied slots from Google Calendar on the frontend
 */
class Timetics_Occupied_Slots_Addon
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
     * Core functionality instance.
     */
    private $core;

    /**
     * Admin interface instance.
     */
    private $admin;

    /**
     * Frontend assets instance.
     */
    private $frontend;

    /**
     * Google Calendar integration instance.
     */
    private $google_calendar;

    /**
     * Settings modifier instance.
     */
    private $settings_modifier;

    /**
     * React frontend integration instance.
     */
    private $react_frontend;

    /**
     * Performance optimization instances.
     */
    private $cache_optimized;
    private $asset_optimizer;
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
        $this->init_components();
    }

    /**
     * Initialize all components.
     */
    private function init_components()
    {
        // Initialize core functionality
        try {
            $this->core = OccupiedSlotsCoreOptimized::get_instance();
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to initialize core - ' . $e->getMessage());
            // Don't throw exception, continue with other components
        }
        
        // Initialize admin interface
        try {
            $this->admin = OccupiedSlotsAdmin::get_instance();
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to initialize admin - ' . $e->getMessage());
        }
        
        // Initialize frontend assets
        try {
            $this->frontend = OccupiedSlotsFrontendReact::get_instance();
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to initialize frontend - ' . $e->getMessage());
        }
        
        // Initialize Google Calendar integration
        try {
            $this->google_calendar = OccupiedSlotsGoogleCalendarFixed::get_instance();
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to initialize Google Calendar - ' . $e->getMessage());
        }

        // Initialize settings modifier to block occupied dates
        try {
            $this->settings_modifier = OccupiedSlotsSettingsModifier::get_instance();
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to initialize settings modifier - ' . $e->getMessage());
        }

        // Initialize simple blocker for AJAX requests
        try {
            OccupiedSlotsSimpleBlocker::get_instance();
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to initialize simple blocker - ' . $e->getMessage());
        }

        // Initialize React frontend integration
        try {
            $this->react_frontend = OccupiedSlotsFrontendReact::get_instance();
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to initialize React frontend - ' . $e->getMessage());
        }

        // Initialize performance optimization components
        try {
            $this->cache_optimized = OccupiedSlotsCacheOptimized::get_instance();
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to initialize optimized cache - ' . $e->getMessage());
        }

        try {
            $this->asset_optimizer = OccupiedSlotsAssetOptimizer::get_instance();
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to initialize asset optimizer - ' . $e->getMessage());
        }

        try {
            $this->performance_monitor = OccupiedSlotsPerformanceMonitor::get_instance();
        } catch (Exception $e) {
            error_log('Timetics Occupied Slots: Failed to initialize performance monitor - ' . $e->getMessage());
        }
    }

    /**
     * Get core functionality instance.
     */
    public function get_core()
    {
        return $this->core;
    }

    /**
     * Get admin interface instance.
     */
    public function get_admin()
    {
        return $this->admin;
    }

    /**
     * Get frontend assets instance.
     */
    public function get_frontend()
    {
        return $this->frontend;
    }

    /**
     * Get Google Calendar integration instance.
     */
    public function get_google_calendar()
    {
        return $this->google_calendar;
    }

    /**
     * Get settings modifier instance.
     */
    public function get_settings_modifier()
    {
        return $this->settings_modifier;
    }

    /**
     * Get React frontend integration instance.
     */
    public function get_react_frontend()
    {
        return $this->react_frontend;
    }

    /**
     * Get optimized cache instance.
     */
    public function get_cache_optimized()
    {
        return $this->cache_optimized;
    }

    /**
     * Get asset optimizer instance.
     */
    public function get_asset_optimizer()
    {
        return $this->asset_optimizer;
    }

    /**
     * Get performance monitor instance.
     */
    public function get_performance_monitor()
    {
        return $this->performance_monitor;
    }
}

// Initialize the plugin with dependency checks
add_action('plugins_loaded', function () {
    // Check if base Timetics plugin is active
    if (!class_exists('Timetics')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Timetics Occupied Slots Addon:</strong> This plugin requires the base <a href="https://wordpress.org/plugins/timetics/" target="_blank">Timetics plugin</a> to be installed and activated.</p></div>';
        });
        return;
    }
    
    // Check if Timetics Pro is active for enhanced compatibility
    if (class_exists('TimeticsPro')) {
        // Add Pro-specific compatibility layer
        add_action('init', function() {
            // Ensure our hooks run after Pro plugin initialization
            add_action('timetics_pro_loaded', function() {
                // Pro-specific compatibility code here
            }, 20);
        });
    }
    
    // Production environment optimizations
    if (defined('WP_ENV') && WP_ENV === 'production') {
        // Add production-specific optimizations
        add_action('init', function() {
            // Enable caching for production
            if (class_exists('OccupiedSlotsCache')) {
                add_filter('timetics_occupied_slots_enable_caching', '__return_true');
            }
            
            // Enable performance monitoring
            if (class_exists('OccupiedSlotsPerformanceMonitor')) {
                add_filter('timetics_occupied_slots_enable_performance_monitoring', '__return_true');
            }
        });
    }
    
    // Initialize the plugin
    Timetics_Occupied_Slots_Addon::get_instance();
    
    // Initialize compatibility layer
    OccupiedSlotsCompatibilityLayer::get_instance();
    
    // Initialize senior-level intelligent detection system
    OccupiedSlotsIntelligentDetector::get_instance();
    OccupiedSlotsCacheManager::get_instance();
    OccupiedSlotsPerformanceMonitor::get_instance();
    OccupiedSlotsAdminDashboard::get_instance();
    
    // Enqueue slot fix script to resolve last slot population issue
    add_action('wp_enqueue_scripts', function() {
        if (!is_admin()) {
            wp_enqueue_script(
                'timetics-occupied-slots-slot-fix',
                plugin_dir_url(__FILE__) . 'assets/js/occupied-slots-slot-fix.js',
                ['jquery'],
                '1.6.3',
                true
            );
        }
    });
});

// Register REST API routes with unique namespace to avoid conflicts
add_action('rest_api_init', function () {
    // Register occupied dates endpoint with unique namespace
    register_rest_route('timetics-occupied-slots/v1', '/occupied-dates', [
        'methods' => 'GET',
        'callback' => function($request) {
            $start_date = $request->get_param('start_date') ?: date('Y-m-d');
            $end_date = $request->get_param('end_date') ?: date('Y-m-d', strtotime('+30 days'));
            
            // Get blocked dates from the simple blocker
            $blocked_dates = [];
            if (class_exists('OccupiedSlotsSimpleBlocker')) {
                $blocker = OccupiedSlotsSimpleBlocker::get_instance();
                $all_blocked_dates = $blocker->get_blocked_dates();
                
                // Filter blocked dates by the requested date range
                foreach ($all_blocked_dates as $date) {
                    if ($date >= $start_date && $date <= $end_date) {
                        $blocked_dates[] = $date;
                    }
                }
            }
            
            return [
                'success' => true,
                'blocked_dates' => $blocked_dates,
                'total_blocked' => count($blocked_dates),
                'date_range' => [
                    'start' => $start_date,
                    'end' => $end_date
                ]
            ];
        },
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
            ],
            'full_year' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Enable full year support (up to 365 days)'
            ]
        ]
    ]);
    
    // Register bookings entries endpoint with blocked dates integration
    register_rest_route('timetics-occupied-slots/v1', '/bookings/entries', [
        'methods' => 'GET',
        'callback' => function($request) {
            $full_year = $request->get_param('full_year');
            
            // Handle full year requests with new manager
            if ($full_year && class_exists('OccupiedSlotsFullYearManager')) {
                $manager = OccupiedSlotsFullYearManager::get_instance();
                return $manager->handle_full_year_request($request);
            }
            
            // Delegate to intelligent detector for regular requests (backward compatible)
            if (class_exists('OccupiedSlotsIntelligentDetector')) {
                $detector = OccupiedSlotsIntelligentDetector::get_instance();
                return $detector->handle_intelligent_bookings_entries($request);
            }
            
            // Fallback: maintain previous behavior if detector unavailable
            $staff_id = $request->get_param('staff_id');
            $meeting_id = $request->get_param('meeting_id');
            $start_date = $request->get_param('start_date') ?: date('Y-m-d');
            $end_date = $request->get_param('end_date') ?: date('Y-m-d', strtotime('+30 days'));
            $timezone = $request->get_param('timezone') ?: wp_timezone_string();
            $original_response = [
                'success' => true,
                'data' => [
                    'days' => [],
                    'blocked_dates' => []
                ]
            ];
            if (class_exists('OccupiedSlotsSimpleBlocker')) {
                $blocker = OccupiedSlotsSimpleBlocker::get_instance();
                $original_response['data']['blocked_dates'] = $blocker->get_blocked_dates();
            }
            return $original_response;
        },
        'permission_callback' => '__return_true',
        'args' => [
            'staff_id' => [
                'type' => 'integer',
                'required' => true
            ],
            'meeting_id' => [
                'type' => 'integer',
                'required' => true
            ],
            'start_date' => [
                'type' => 'string',
                'format' => 'date',
                'default' => date('Y-m-d')
            ],
            'end_date' => [
                'type' => 'string',
                'format' => 'date',
                'default' => date('Y-m-d', strtotime('+30 days'))
            ],
            'timezone' => [
                'type' => 'string',
                'default' => wp_timezone_string()
            ],
            'full_year' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Enable full year support (up to 365 days)'
            ]
        ]
    ]);
}, 20); // Higher priority to ensure our routes are registered after base plugin
