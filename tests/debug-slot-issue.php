<?php
/**
 * Debug Script for Slot Display Issue
 * 
 * This script helps debug why slots aren't displaying properly
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

// Add this to wp-config.php temporarily for debugging:
// define('TIMETICS_OCCUPIED_SLOTS_DEBUG', true);

if (!defined('TIMETICS_OCCUPIED_SLOTS_DEBUG') || !TIMETICS_OCCUPIED_SLOTS_DEBUG) {
    return;
}

class TimeticsOccupiedSlotsDebugger
{
    /**
     * Initialize debugging.
     */
    public static function init()
    {
        add_action('wp_footer', [__CLASS__, 'add_debug_info']);
        add_action('wp_ajax_debug_slot_issue', [__CLASS__, 'handle_debug_ajax']);
        add_action('wp_ajax_nopriv_debug_slot_issue', [__CLASS__, 'handle_debug_ajax']);
    }

    /**
     * Add debug information to footer.
     */
    public static function add_debug_info()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div id="timetics-debug-info" style="position: fixed; bottom: 10px; left: 10px; background: #000; color: #fff; padding: 10px; z-index: 9999; font-size: 12px; max-width: 300px;">';
        echo '<h4>Timetics Occupied Slots Debug</h4>';
        
        // Check if plugin is enabled
        $plugin_enabled = get_option('timetics_occupied_slots_enabled', true);
        echo '<p><strong>Plugin Enabled:</strong> ' . ($plugin_enabled ? 'Yes' : 'No') . '</p>';
        
        // Check if Google Calendar overlap is enabled
        $google_overlap = function_exists('timetics_get_option') ? timetics_get_option('google_calendar_overlap', false) : false;
        echo '<p><strong>Google Calendar Overlap:</strong> ' . ($google_overlap ? 'Yes' : 'No') . '</p>';
        
        // Check for Timetics shortcodes
        global $post;
        $has_shortcode = false;
        if ($post) {
            $shortcodes = ['timetics_booking', 'timetics_calendar', 'timetics_booking_form'];
            foreach ($shortcodes as $shortcode) {
                if (has_shortcode($post->post_content, $shortcode)) {
                    $has_shortcode = true;
                    break;
                }
            }
        }
        echo '<p><strong>Has Timetics Shortcode:</strong> ' . ($has_shortcode ? 'Yes' : 'No') . '</p>';
        
        // Check for slot containers
        echo '<p><strong>Slot Containers Found:</strong></p>';
        echo '<ul>';
        $selectors = ['.tt-slot-list', '.ant-list', '.slot-container', '.ant-btn'];
        foreach ($selectors as $selector) {
            echo '<li>' . $selector . ': <span id="count-' . str_replace('.', '', $selector) . '">Checking...</span></li>';
        }
        echo '</ul>';
        
        echo '<button onclick="debugSlotIssue()" style="background: #0073aa; color: white; border: none; padding: 5px 10px; cursor: pointer;">Debug Slots</button>';
        echo '</div>';
        
        // Add JavaScript for debugging
        echo '<script>
        function debugSlotIssue() {
            console.log("=== Timetics Occupied Slots Debug ===");
            
            // Check for slot containers
            const selectors = [".tt-slot-list", ".ant-list", ".slot-container", ".ant-btn"];
            selectors.forEach(selector => {
                const elements = document.querySelectorAll(selector);
                console.log("Selector:", selector, "Count:", elements.length);
                document.getElementById("count-" + selector.replace(".", "")).textContent = elements.length;
                
                if (elements.length > 0) {
                    console.log("First element:", elements[0]);
                    console.log("First element text:", elements[0].textContent);
                }
            });
            
            // Check for "No slot available" messages
            const noSlotMessages = document.querySelectorAll(".no-slot-available, .tt-no-slots, .ant-empty, .empty-state");
            console.log("No slot messages found:", noSlotMessages.length);
            
            // Check for hidden slots
            const hiddenSlots = document.querySelectorAll(".ant-btn[style*=\'display: none\'], .ant-btn.hidden, .ant-btn[data-hidden=\'true\']");
            console.log("Hidden slots found:", hiddenSlots.length);
            
            // Check for processed slots
            const processedSlots = document.querySelectorAll(".tt-slot-processed");
            console.log("Processed slots:", processedSlots.length);
            
            // Check for occupied slots
            const occupiedSlots = document.querySelectorAll(".tt-slot-occupied");
            console.log("Occupied slots:", occupiedSlots.length);
            
            // Check for Timetics configuration
            if (typeof timeticsOccupiedSlots !== "undefined") {
                console.log("Timetics Occupied Slots config:", timeticsOccupiedSlots);
            } else {
                console.log("Timetics Occupied Slots config: NOT FOUND");
            }
            
            if (typeof timeticsOccupiedSlotsEnhanced !== "undefined") {
                console.log("Timetics Occupied Slots Enhanced config:", timeticsOccupiedSlotsEnhanced);
            } else {
                console.log("Timetics Occupied Slots Enhanced config: NOT FOUND");
            }
            
            // Check for Timetics global
            if (typeof timetics !== "undefined") {
                console.log("Timetics global:", timetics);
            } else {
                console.log("Timetics global: NOT FOUND");
            }
            
            console.log("=== End Debug ===");
        }
        
        // Auto-run debug on page load
        document.addEventListener("DOMContentLoaded", function() {
            setTimeout(debugSlotIssue, 2000);
        });
        </script>';
    }

    /**
     * Handle debug AJAX request.
     */
    public static function handle_debug_ajax()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $response = [
            'plugin_enabled' => get_option('timetics_occupied_slots_enabled', true),
            'google_calendar_overlap' => function_exists('timetics_get_option') ? timetics_get_option('google_calendar_overlap', false) : false,
            'timetics_loaded' => class_exists('Timetics'),
            'cache_available' => class_exists('OccupiedSlotsCache'),
            'frontend_enhanced_available' => class_exists('OccupiedSlotsFrontendEnhanced'),
            'timestamp' => current_time('mysql')
        ];

        wp_send_json_success($response);
    }
}

// Initialize debugging if enabled
TimeticsOccupiedSlotsDebugger::init();
