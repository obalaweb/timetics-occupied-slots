<?php
/**
 * Timetics Occupied Slots Addon - Admin Interface
 * 
 * Handles admin interface and settings
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsAdmin
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
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks()
    {
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu()
    {
        // Add as a standalone admin page instead of submenu
        add_options_page(
            'Timetics Occupied Slots Settings',
            'Timetics Occupied Slots',
            'manage_options',
            'timetics-occupied-slots',
            [$this, 'admin_page']
        );
    }

    /**
     * Admin page.
     */
    public function admin_page()
    {
        if (isset($_POST['submit'])) {
            // Verify nonce for security
            if (!wp_verify_nonce($_POST['timetics_occupied_slots_nonce'], 'timetics_occupied_slots_settings')) {
                wp_die('Security check failed');
            }

            // Update settings
            update_option('timetics_occupied_slots_enabled', isset($_POST['timetics_occupied_slots_enabled']));
            update_option('timetics_occupied_slots_tooltip', isset($_POST['timetics_occupied_slots_tooltip']));
            update_option('timetics_occupied_slots_color', sanitize_hex_color($_POST['timetics_occupied_slots_color']));
            update_option('timetics_occupied_slots_text_color', sanitize_hex_color($_POST['timetics_occupied_slots_text_color']));

            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }

        $enabled = get_option('timetics_occupied_slots_enabled', true);
        $tooltip = get_option('timetics_occupied_slots_tooltip', true);
        $color = get_option('timetics_occupied_slots_color', '#ff6b6b');
        $text_color = get_option('timetics_occupied_slots_text_color', '#ffffff');
        ?>
        <div class="wrap">
            <h1>Timetics Occupied Slots Settings</h1>
            <form method="post">
                <?php wp_nonce_field('timetics_occupied_slots_settings', 'timetics_occupied_slots_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Occupied Slots Display</th>
                        <td>
                            <label>
                                <input type="checkbox" name="timetics_occupied_slots_enabled" value="1" <?php checked($enabled); ?> />
                                Show occupied slots from Google Calendar on the frontend
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Show Tooltip</th>
                        <td>
                            <label>
                                <input type="checkbox" name="timetics_occupied_slots_tooltip" value="1" <?php checked($tooltip); ?> />
                                Show tooltip when hovering over occupied slots
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Occupied Slot Color</th>
                        <td>
                            <input type="text" name="timetics_occupied_slots_color" value="<?php echo esc_attr($color); ?>" class="color-picker" />
                            <p class="description">Background color for occupied slots</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Text Color</th>
                        <td>
                            <input type="text" name="timetics_occupied_slots_text_color" value="<?php echo esc_attr($text_color); ?>" class="color-picker" />
                            <p class="description">Text color for occupied slots</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr>

            <h3>How It Works</h3>
            <p>This addon works with the Timetics booking system to show occupied slots:</p>
            <ol>
                <li><strong>Frontend Processing:</strong> The addon processes slot data directly in the browser</li>
                <li><strong>Automatic Detection:</strong> Detects occupied slots based on booking data (booked > 0)</li>
                <li><strong>Visual Indicators:</strong> Shows occupied slots with red background and 🚫 icon</li>
                <li><strong>Real-time Updates:</strong> Monitors AJAX calls and DOM changes to update slot status</li>
                <li><strong>Customer Experience:</strong> Customers can see which slots are occupied before attempting to book</li>
            </ol>

            <h3>Current Status</h3>
            <table class="widefat">
                <tr>
                    <td><strong>Timetics Plugin:</strong></td>
                    <td><?php echo class_exists('Timetics\Core\Bookings\Api_Booking') ? 'Available' : 'Not Available'; ?></td>
                </tr>
                <tr>
                    <td><strong>Occupied Slots Addon:</strong></td>
                    <td><?php echo $enabled ? 'Enabled' : 'Disabled'; ?></td>
                </tr>
                <tr>
                    <td><strong>Frontend Processing:</strong></td>
                    <td>Active</td>
                </tr>
            </table>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('.color-picker').wpColorPicker();
            });
        </script>
        <?php
    }

    /**
     * Enqueue admin scripts.
     */
    public function enqueue_admin_scripts($hook)
    {
        if ('settings_page_timetics-occupied-slots' === $hook) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
        }
    }
}
