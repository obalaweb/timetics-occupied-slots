<?php
/**
 * Timetics Occupied Slots Addon - Admin Dashboard
 * 
 * Senior-level admin dashboard with performance monitoring and cache management
 * 
 * @package Timetics_Occupied_Slots_Addon
 * @version 1.6.3
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsAdminDashboard
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
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_occupied_slots_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_occupied_slots_warm_cache', [$this, 'ajax_warm_cache']);
        add_action('wp_ajax_occupied_slots_get_stats', [$this, 'ajax_get_stats']);
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'timetics',
            'Occupied Slots Dashboard',
            'Occupied Slots',
            'manage_options',
            'timetics-occupied-slots',
            [$this, 'render_dashboard']
        );
    }

    /**
     * Render admin dashboard.
     */
    public function render_dashboard()
    {
        $performance_monitor = OccupiedSlotsPerformanceMonitor::get_instance();
        $cache_manager = OccupiedSlotsCacheManager::get_instance();
        $intelligent_detector = OccupiedSlotsIntelligentDetector::get_instance();

        $performance_metrics = $performance_monitor->get_metrics();
        $cache_stats = $cache_manager->get_stats();
        $cache_size = $cache_manager->get_cache_size();
        $cache_entries = $cache_manager->get_cache_entries_count();

        ?>
        <div class="wrap">
            <h1>Timetics Occupied Slots - Senior Dashboard</h1>
            
            <div class="notice notice-info">
                <p><strong>Senior-Level Implementation:</strong> Intelligent detection system with 24-hour caching and performance monitoring.</p>
            </div>

            <div class="dashboard-grid">
                <!-- Performance Metrics -->
                <div class="dashboard-card">
                    <h2>Performance Metrics</h2>
                    <div class="metrics-grid">
                        <div class="metric-item">
                            <span class="metric-label">Total Requests:</span>
                            <span class="metric-value"><?php echo esc_html($performance_metrics['total_requests']); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Avg Execution Time:</span>
                            <span class="metric-value"><?php echo esc_html($performance_metrics['average_execution_time']); ?>ms</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Avg Memory Usage:</span>
                            <span class="metric-value"><?php echo esc_html($performance_metrics['average_memory_usage']); ?>MB</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Slow Queries:</span>
                            <span class="metric-value"><?php echo esc_html($performance_metrics['slow_queries']); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Memory Peak:</span>
                            <span class="metric-value"><?php echo esc_html($performance_metrics['memory_peak']); ?>MB</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Current Memory:</span>
                            <span class="metric-value"><?php echo esc_html($performance_metrics['current_memory']); ?>MB</span>
                        </div>
                    </div>
                </div>

                <!-- Cache Statistics -->
                <div class="dashboard-card">
                    <h2>Cache Statistics</h2>
                    <div class="metrics-grid">
                        <div class="metric-item">
                            <span class="metric-label">Cache Hits:</span>
                            <span class="metric-value"><?php echo esc_html($cache_stats['hits']); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Cache Misses:</span>
                            <span class="metric-value"><?php echo esc_html($cache_stats['misses']); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Hit Rate:</span>
                            <span class="metric-value"><?php echo esc_html($cache_stats['hit_rate']); ?>%</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Cache Sets:</span>
                            <span class="metric-value"><?php echo esc_html($cache_stats['sets']); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Cache Deletes:</span>
                            <span class="metric-value"><?php echo esc_html($cache_stats['deletes']); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Cache Size:</span>
                            <span class="metric-value"><?php echo esc_html($this->format_bytes($cache_size)); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Cache Entries:</span>
                            <span class="metric-value"><?php echo esc_html($cache_entries); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="dashboard-card">
                    <h2>Cache Management</h2>
                    <div class="action-buttons">
                        <button type="button" class="button button-primary" id="clear-cache-btn">Clear All Cache</button>
                        <button type="button" class="button button-secondary" id="warm-cache-btn">Warm Cache</button>
                        <button type="button" class="button button-secondary" id="optimize-cache-btn">Optimize Cache</button>
                        <button type="button" class="button button-secondary" id="refresh-stats-btn">Refresh Stats</button>
                    </div>
                    <div id="action-feedback" class="action-feedback"></div>
                </div>

                <!-- System Status -->
                <div class="dashboard-card">
                    <h2>System Status</h2>
                    <div class="status-grid">
                        <div class="status-item">
                            <span class="status-label">Intelligent Detector:</span>
                            <span class="status-value status-active">Active</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Cache Manager:</span>
                            <span class="status-value status-active">Active</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Performance Monitor:</span>
                            <span class="status-value status-active">Active</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">WordPress Version:</span>
                            <span class="status-value"><?php echo esc_html(get_bloginfo('version')); ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">PHP Version:</span>
                            <span class="status-value"><?php echo esc_html(PHP_VERSION); ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Memory Limit:</span>
                            <span class="status-value"><?php echo esc_html(ini_get('memory_limit')); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <style>
                .dashboard-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 20px;
                    margin-top: 20px;
                }
                .dashboard-card {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    border-radius: 4px;
                    padding: 20px;
                    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                }
                .dashboard-card h2 {
                    margin-top: 0;
                    color: #23282d;
                }
                .metrics-grid, .status-grid {
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: 10px;
                }
                .metric-item, .status-item {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 0;
                    border-bottom: 1px solid #f0f0f1;
                }
                .metric-label, .status-label {
                    font-weight: 500;
                    color: #50575e;
                }
                .metric-value, .status-value {
                    font-weight: 600;
                    color: #23282d;
                }
                .status-active {
                    color: #00a32a;
                }
                .action-buttons {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                    margin-bottom: 15px;
                }
                .action-feedback {
                    margin-top: 10px;
                    padding: 10px;
                    border-radius: 4px;
                    display: none;
                }
                .action-feedback.success {
                    background: #d1e7dd;
                    color: #0f5132;
                    border: 1px solid #badbcc;
                }
                .action-feedback.error {
                    background: #f8d7da;
                    color: #842029;
                    border: 1px solid #f5c2c7;
                }
            </style>

            <script>
                jQuery(document).ready(function($) {
                    // Clear cache
                    $('#clear-cache-btn').on('click', function() {
                        $(this).prop('disabled', true).text('Clearing...');
                        $.post(ajaxurl, {
                            action: 'occupied_slots_clear_cache',
                            nonce: '<?php echo wp_create_nonce('occupied_slots_clear_cache'); ?>'
                        }, function(response) {
                            if (response.success) {
                                showFeedback('Cache cleared successfully!', 'success');
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                showFeedback('Error clearing cache: ' + response.data, 'error');
                            }
                        });
                    });

                    // Warm cache
                    $('#warm-cache-btn').on('click', function() {
                        $(this).prop('disabled', true).text('Warming...');
                        $.post(ajaxurl, {
                            action: 'occupied_slots_warm_cache',
                            nonce: '<?php echo wp_create_nonce('occupied_slots_warm_cache'); ?>'
                        }, function(response) {
                            if (response.success) {
                                showFeedback('Cache warmed successfully!', 'success');
                            } else {
                                showFeedback('Error warming cache: ' + response.data, 'error');
                            }
                        });
                    });

                    // Optimize cache
                    $('#optimize-cache-btn').on('click', function() {
                        $(this).prop('disabled', true).text('Optimizing...');
                        $.post(ajaxurl, {
                            action: 'occupied_slots_optimize_cache',
                            nonce: '<?php echo wp_create_nonce('occupied_slots_optimize_cache'); ?>'
                        }, function(response) {
                            if (response.success) {
                                showFeedback('Cache optimized successfully!', 'success');
                            } else {
                                showFeedback('Error optimizing cache: ' + response.data, 'error');
                            }
                        });
                    });

                    // Refresh stats
                    $('#refresh-stats-btn').on('click', function() {
                        location.reload();
                    });

                    function showFeedback(message, type) {
                        $('#action-feedback').removeClass('success error').addClass(type).text(message).show();
                        setTimeout(() => $('#action-feedback').hide(), 3000);
                    }
                });
            </script>
        </div>
        <?php
    }

    /**
     * AJAX clear cache.
     */
    public function ajax_clear_cache()
    {
        check_ajax_referer('occupied_slots_clear_cache', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $cache_manager = OccupiedSlotsCacheManager::get_instance();
        $cache_manager->clear_all();

        wp_send_json_success('Cache cleared successfully');
    }

    /**
     * AJAX warm cache.
     */
    public function ajax_warm_cache()
    {
        check_ajax_referer('occupied_slots_warm_cache', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $cache_manager = OccupiedSlotsCacheManager::get_instance();
        $cache_manager->warm_cache(1, 1); // Default staff and meeting ID

        wp_send_json_success('Cache warmed successfully');
    }

    /**
     * AJAX get stats.
     */
    public function ajax_get_stats()
    {
        check_ajax_referer('occupied_slots_get_stats', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $performance_monitor = OccupiedSlotsPerformanceMonitor::get_instance();
        $cache_manager = OccupiedSlotsCacheManager::get_instance();

        $stats = [
            'performance' => $performance_monitor->get_metrics(),
            'cache' => $cache_manager->get_stats()
        ];

        wp_send_json_success($stats);
    }

    /**
     * Format bytes to human readable format.
     */
    private function format_bytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
