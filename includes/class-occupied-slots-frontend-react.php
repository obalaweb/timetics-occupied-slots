<?php
/**
 * Timetics Occupied Slots Addon - React Frontend Integration
 * 
 * Handles frontend integration with React components
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsFrontendReact
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
        // Enqueue scripts and styles with higher priority to ensure proper loading order
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets'], 20);
        
        // DISABLED: React integration scripts (not needed with direct fix)
        // add_action('wp_footer', [$this, 'add_react_integration_script']);
        // add_action('admin_footer', [$this, 'add_react_integration_script']);
        
        // REST API endpoint is handled by the core-optimized class
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets()
    {
        // Always enqueue on frontend to ensure integration runs (avoid missing assets on dynamic pages)

        // PRIMARY SOLUTION: Direct fix script (simple and reliable)
        wp_enqueue_script(
            'timetics-occupied-slots-direct-fix',
            plugin_dir_url(TIMETICS_OCCUPIED_SLOTS_FILE) . 'assets/js/occupied-slots-direct-fix.js',
            ['jquery'],
            self::VERSION,
            true
        );
        
        // FULL YEAR SUPPORT: Progressive loading (backward compatible)
        wp_enqueue_script(
            'timetics-occupied-slots-full-year-loader',
            plugin_dir_url(TIMETICS_OCCUPIED_SLOTS_FILE) . 'assets/js/occupied-slots-full-year-loader-simple.js',
            ['jquery', 'timetics-occupied-slots-direct-fix'],
            self::VERSION,
            true
        );
        
        // TEST SCRIPT: Disabled for production
        // wp_enqueue_script(
        //     'timetics-occupied-slots-test-simple',
        //     plugin_dir_url(TIMETICS_OCCUPIED_SLOTS_FILE) . 'assets/js/test-simple.js',
        //     [],
        //     self::VERSION,
        //     true
        // );

        // DISABLED: Complex React integration (causing conflicts)
        // wp_enqueue_script(
        //     'timetics-occupied-slots-react-integration',
        //     plugin_dir_url(TIMETICS_OCCUPIED_SLOTS_FILE) . 'assets/js/occupied-slots-react-integration.js',
        //     ['jquery'],
        //     self::VERSION,
        //     true
        // );

        // DISABLED: React component wrapper (not needed with direct fix)
        // wp_enqueue_script(
        //     'timetics-occupied-slots-react-component',
        //     plugin_dir_url(TIMETICS_OCCUPIED_SLOTS_FILE) . 'assets/js/timetics-occupied-slots-react-component.js',
        //     ['timetics-occupied-slots-react-integration'],
        //     self::VERSION,
        //     true
        // );

        // Enqueue styles
        wp_enqueue_style(
            'timetics-occupied-slots-react',
            plugin_dir_url(TIMETICS_OCCUPIED_SLOTS_FILE) . 'assets/css/occupied-slots-react.css',
            [],
            self::VERSION
        );

        // DISABLED: Localized script configuration (not needed with direct fix)
        // wp_localize_script('timetics-occupied-slots-react-integration', 'timeticsOccupiedSlotsConfig', [
        //     'apiEndpoint' => rest_url('timetics-occupied-slots/v1/bookings/entries'),
        //     'nonce' => wp_create_nonce('wp_rest'),
        //     'debug' => defined('WP_DEBUG') && WP_DEBUG,
        //     'blockedDateClass' => 'timetics-date-blocked',
        //     'blockedDateIcon' => '🚫',
        //     'ajaxUrl' => admin_url('admin-ajax.php'),
        //     'isAdmin' => is_admin()
        // ]);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets()
    {
        // Enqueue in admin as well for calendar views
        $this->enqueue_assets();
    }

    /**
     * Add React integration script
     */
    public function add_react_integration_script()
    {
        // Always add integration script to improve reliability

        ?>
        <script type="text/javascript">
        (function() {
            'use strict';
            
            // Initialize React integration when ready
            function initReactIntegration() {
                if (window.TimeticsOccupiedSlots) {
                    console.log('[Timetics Occupied Slots] React integration ready');
                    
                    // Set up React component integration
                    if (window.TimeticsOccupiedSlotsReact) {
                        console.log('[Timetics Occupied Slots] React components available');
                    }
                    
                    // Monitor for Timetics React components
                    monitorTimeticsComponents();
                } else {
                    setTimeout(initReactIntegration, 100);
                }
            }
            
            function monitorTimeticsComponents() {
                // Look for Timetics React components
                const timeticsElements = document.querySelectorAll(
                    '[class*="timetics"], ' +
                    '[class*="booking"], ' +
                    '[class*="appointment"], ' +
                    '[class*="calendar"]'
                );
                
                if (timeticsElements.length > 0) {
                    console.log('[Timetics Occupied Slots] Timetics components detected:', timeticsElements.length);
                    
                    // Apply blocked date styling
                    applyBlockedDateStyling();
                }
            }
            
            function applyBlockedDateStyling() {
                // Get blocked dates from the integration
                const blockedDates = window.TimeticsOccupiedSlotsAPI?.getBlockedDates() || [];
                
                if (blockedDates.length === 0) {
                    return;
                }
                
                console.log('[Timetics Occupied Slots] Applying styling for blocked dates:', blockedDates);
                
                // Find and style blocked date elements
                blockedDates.forEach(date => {
                    const dateElements = document.querySelectorAll(
                        `[data-date="${date}"], ` +
                        `[data-value="${date}"], ` +
                        `[title*="${date}"]`
                    );
                    
                    dateElements.forEach(element => {
                        element.classList.add('timetics-date-blocked');
                        element.style.backgroundColor = '#666666';
                        element.style.color = '#ffffff';
                        element.style.cursor = 'not-allowed';
                        element.style.opacity = '0.7';
                        element.style.pointerEvents = 'none';
                        element.title = 'This date is fully occupied';
                        element.setAttribute('data-blocked', 'true');
                        element.setAttribute('data-blocked-reason', 'date_fully_occupied');
                        
                        // Add blocked icon if not already present
                        if (!element.querySelector('.blocked-icon')) {
                            const icon = document.createElement('span');
                            icon.className = 'blocked-icon';
                            icon.textContent = '🚫';
                            icon.style.marginLeft = '4px';
                            icon.style.fontSize = '12px';
                            element.appendChild(icon);
                        }
                    });
                });
            }
            
            // Initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initReactIntegration);
            } else {
                initReactIntegration();
            }
            
            // Monitor for dynamic content changes
            const observer = new MutationObserver(function(mutations) {
                let shouldUpdate = false;
                
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                        shouldUpdate = true;
                    }
                });
                
                if (shouldUpdate) {
                    setTimeout(function() {
                        monitorTimeticsComponents();
                        applyBlockedDateStyling();
                    }, 100);
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
        })();
        </script>
        <?php
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
        
        // Get blocked dates from the simple blocker
        $blocked_dates = [];
        
        if (class_exists('OccupiedSlotsSimpleBlocker')) {
            $blocker = OccupiedSlotsSimpleBlocker::get_instance();
            $blocked_dates = $blocker->get_blocked_dates();
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
    }

    /**
     * Check if assets should be loaded
     */
    private function should_load_assets()
    {
        // Load on all frontend pages (not admin)
        return !is_admin();
    }

    /**
     * Check if admin assets should be loaded
     */
    private function should_load_admin_assets()
    {
        // Load on all admin pages
        return is_admin();
    }
}
