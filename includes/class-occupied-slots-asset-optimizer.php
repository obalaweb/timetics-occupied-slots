<?php
/**
 * Timetics Occupied Slots Addon - Asset Optimizer
 * 
 * Optimized asset loading with conditional loading, minification, and performance monitoring
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsAssetOptimizer
{
    /**
     * Plugin version
     */
    const VERSION = '1.6.3';
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Asset configuration
     */
    private $asset_config = [
        'css' => [
            'occupied-slots-react' => [
                'file' => 'assets/css/occupied-slots-react.css',
                'dependencies' => [],
                'version' => self::VERSION,
                'media' => 'all',
                'conditional' => true
            ]
        ],
        'js' => [
            'occupied-slots-core-optimized' => [
                'file' => 'assets/js/occupied-slots-core-optimized.js',
                'dependencies' => ['jquery'],
                'version' => self::VERSION,
                'in_footer' => true,
                'conditional' => true
            ],
            'occupied-slots-react-integration' => [
                'file' => 'assets/js/occupied-slots-react-integration.js',
                'dependencies' => ['occupied-slots-core-optimized'],
                'version' => self::VERSION,
                'in_footer' => true,
                'conditional' => true
            ]
        ]
    ];
    
    /**
     * Performance metrics
     */
    private $performance_metrics = [
        'assets_loaded' => 0,
        'assets_skipped' => 0,
        'load_time' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0
    ];
    
    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Optimized asset enqueuing
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets'], 5);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets'], 5);
        
        // Asset optimization hooks
        add_action('wp_head', [$this, 'add_preload_hints'], 1);
        add_action('wp_footer', [$this, 'add_performance_metrics'], 999);
        
        // Conditional loading
        add_action('wp_enqueue_scripts', [$this, 'conditional_asset_loading'], 10);
        
        // Asset minification
        add_filter('script_loader_tag', [$this, 'optimize_script_loading'], 10, 3);
        add_filter('style_loader_tag', [$this, 'optimize_style_loading'], 10, 3);
        
        // Cache busting
        add_filter('script_loader_src', [$this, 'add_cache_busting'], 10, 2);
        add_filter('style_loader_src', [$this, 'add_cache_busting'], 10, 2);
    }
    
    /**
     * Enqueue frontend assets with optimization
     */
    public function enqueue_frontend_assets()
    {
        if (!$this->should_load_assets()) {
            return;
        }
        
        $start_time = microtime(true);
        
        // Load core optimized JavaScript first
        $this->enqueue_asset('js', 'occupied-slots-core-optimized');
        
        // Load React integration if needed
        if ($this->needs_react_integration()) {
            $this->enqueue_asset('js', 'occupied-slots-react-integration');
        }
        
        // Load CSS with conditional loading
        if ($this->needs_styling()) {
            $this->enqueue_asset('css', 'occupied-slots-react');
        }
        
        // Localize script with optimized data
        $this->localize_scripts();
        
        $this->performance_metrics['load_time'] = microtime(true) - $start_time;
        $this->performance_metrics['assets_loaded']++;
    }
    
    /**
     * Enqueue admin assets with optimization
     */
    public function enqueue_admin_assets()
    {
        if (!$this->should_load_admin_assets()) {
            return;
        }
        
        $start_time = microtime(true);
        
        // Load only essential assets for admin
        $this->enqueue_asset('js', 'occupied-slots-core-optimized');
        
        if ($this->needs_styling()) {
            $this->enqueue_asset('css', 'occupied-slots-react');
        }
        
        $this->performance_metrics['load_time'] = microtime(true) - $start_time;
        $this->performance_metrics['assets_loaded']++;
    }
    
    /**
     * Enqueue individual asset with optimization
     */
    private function enqueue_asset($type, $handle)
    {
        if (!isset($this->asset_config[$type][$handle])) {
            return;
        }
        
        $config = $this->asset_config[$type][$handle];
        
        // Check conditional loading
        if ($config['conditional'] && !$this->should_load_asset($handle)) {
            $this->performance_metrics['assets_skipped']++;
            return;
        }
        
        // Check cache
        $cache_key = $this->get_asset_cache_key($handle);
        $cached_version = get_transient($cache_key);
        
        if ($cached_version === false) {
            $this->performance_metrics['cache_misses']++;
            $version = $this->get_asset_version($config['file']);
            set_transient($cache_key, $version, HOUR_IN_SECONDS);
        } else {
            $this->performance_metrics['cache_hits']++;
            $version = $cached_version;
        }
        
        // Enqueue asset
        if ($type === 'js') {
            wp_enqueue_script(
                $handle,
                plugin_dir_url(TIMETICS_OCCUPIED_SLOTS_FILE) . $config['file'],
                $config['dependencies'],
                $version,
                $config['in_footer']
            );
        } else {
            wp_enqueue_style(
                $handle,
                plugin_dir_url(TIMETICS_OCCUPIED_SLOTS_FILE) . $config['file'],
                $config['dependencies'],
                $version,
                $config['media']
            );
        }
    }
    
    /**
     * Check if assets should be loaded
     */
    private function should_load_assets()
    {
        // Don't load on admin pages unless needed
        if (is_admin()) {
            return false;
        }
        
        // Check if Timetics is active
        if (!class_exists('Timetics\Core\Timetics')) {
            return false;
        }
        
        // Check if we're on a page that might have Timetics components
        global $post;
        if ($post && (
            has_shortcode($post->post_content, 'timetics') ||
            has_shortcode($post->post_content, 'booking') ||
            has_shortcode($post->post_content, 'appointment')
        )) {
            return true;
        }
        
        // Check for Timetics widgets
        if (is_active_widget(false, false, 'timetics_booking_widget')) {
            return true;
        }
        
        // Check for Timetics templates
        if (is_page_template('timetics-booking.php') || 
            is_page_template('timetics-appointment.php')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if admin assets should be loaded
     */
    private function should_load_admin_assets()
    {
        // Only load on Timetics admin pages
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }
        
        return strpos($screen->id, 'timetics') !== false ||
               strpos($screen->id, 'booking') !== false ||
               strpos($screen->id, 'appointment') !== false;
    }
    
    /**
     * Check if specific asset should be loaded
     */
    private function should_load_asset($handle)
    {
        switch ($handle) {
            case 'occupied-slots-react-integration':
                return $this->needs_react_integration();
            case 'occupied-slots-react':
                return $this->needs_styling();
            default:
                return true;
        }
    }
    
    /**
     * Check if React integration is needed
     */
    private function needs_react_integration()
    {
        // Check if React is available
        if (!wp_script_is('react', 'registered') && !wp_script_is('react', 'enqueued')) {
            return false;
        }
        
        // Check if Timetics uses React components
        if (class_exists('Timetics\Core\Frontend\React\TimeticsReactApp')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if styling is needed
     */
    private function needs_styling()
    {
        // Check if custom styling is enabled
        $custom_styling = get_option('timetics_occupied_slots_custom_styling', true);
        if (!$custom_styling) {
            return false;
        }
        
        // Check if theme has custom styles
        if (current_theme_supports('timetics-occupied-slots-styling')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get asset cache key
     */
    private function get_asset_cache_key($handle)
    {
        return 'occupied_slots_asset_' . md5($handle . self::VERSION);
    }
    
    /**
     * Get asset version
     */
    private function get_asset_version($file)
    {
        $file_path = plugin_dir_path(TIMETICS_OCCUPIED_SLOTS_FILE) . $file;
        
        if (file_exists($file_path)) {
            return filemtime($file_path);
        }
        
        return self::VERSION;
    }
    
    /**
     * Localize scripts with optimized data
     */
    private function localize_scripts()
    {
        $config = [
            'apiEndpoint' => rest_url('timetics/v1/bookings/entries'),
            'nonce' => wp_create_nonce('wp_rest'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'blockedDateClass' => 'timetics-date-blocked',
            'blockedDateIcon' => '🚫',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'isAdmin' => is_admin(),
            'performance' => [
                'monitoring' => defined('WP_DEBUG') && WP_DEBUG,
                'metrics' => $this->get_performance_metrics()
            ]
        ];
        
        wp_localize_script('occupied-slots-core-optimized', 'timeticsOccupiedSlotsConfig', $config);
    }
    
    /**
     * Add preload hints for critical assets
     */
    public function add_preload_hints()
    {
        if (!$this->should_load_assets()) {
            return;
        }
        
        $base_url = plugin_dir_url(TIMETICS_OCCUPIED_SLOTS_FILE);
        
        // Preload critical JavaScript
        echo '<link rel="preload" href="' . $base_url . 'assets/js/occupied-slots-core-optimized.js" as="script">';
        
        // Preload critical CSS
        if ($this->needs_styling()) {
            echo '<link rel="preload" href="' . $base_url . 'assets/css/occupied-slots-react.css" as="style">';
        }
        
        // Preload API endpoint
        echo '<link rel="preload" href="' . rest_url('timetics/v1/bookings/entries') . '" as="fetch" crossorigin>';
    }
    
    /**
     * Conditional asset loading based on page content
     */
    public function conditional_asset_loading()
    {
        // Only load on pages that actually need the functionality
        if (!$this->should_load_assets()) {
            return;
        }
        
        // Check for specific Timetics components
        $has_timetics_components = $this->detect_timetics_components();
        
        if (!$has_timetics_components) {
            // Remove unnecessary assets
            wp_dequeue_script('occupied-slots-react-integration');
            wp_dequeue_style('occupied-slots-react');
        }
    }
    
    /**
     * Detect Timetics components on the page
     */
    private function detect_timetics_components()
    {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Check for Timetics shortcodes
        $shortcodes = ['timetics', 'booking', 'appointment', 'calendar'];
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        
        // Check for Timetics widgets
        if (is_active_widget(false, false, 'timetics_booking_widget')) {
            return true;
        }
        
        // Check for Timetics templates
        $templates = ['timetics-booking.php', 'timetics-appointment.php', 'timetics-calendar.php'];
        foreach ($templates as $template) {
            if (is_page_template($template)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Optimize script loading
     */
    public function optimize_script_loading($tag, $handle, $src)
    {
        if (strpos($handle, 'occupied-slots') === false) {
            return $tag;
        }
        
        // Add async loading for non-critical scripts
        if ($handle === 'occupied-slots-react-integration') {
            $tag = str_replace('<script ', '<script async ', $tag);
        }
        
        // Add defer for critical scripts
        if ($handle === 'occupied-slots-core-optimized') {
            $tag = str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Optimize style loading
     */
    public function optimize_style_loading($tag, $handle, $href)
    {
        if (strpos($handle, 'occupied-slots') === false) {
            return $tag;
        }
        
        // Add media query for conditional loading
        if ($handle === 'occupied-slots-react') {
            $tag = str_replace('media="all"', 'media="screen"', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Add cache busting to asset URLs
     */
    public function add_cache_busting($src, $handle)
    {
        if (strpos($handle, 'occupied-slots') === false) {
            return $src;
        }
        
        // Add version parameter for cache busting
        $version = $this->get_asset_version($this->get_asset_file($handle));
        $src = add_query_arg('v', $version, $src);
        
        return $src;
    }
    
    /**
     * Get asset file path
     */
    private function get_asset_file($handle)
    {
        foreach ($this->asset_config as $type => $assets) {
            if (isset($assets[$handle])) {
                return $assets[$handle]['file'];
            }
        }
        
        return '';
    }
    
    /**
     * Add performance metrics to footer
     */
    public function add_performance_metrics()
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $metrics = $this->get_performance_metrics();
        
        echo '<script type="text/javascript">';
        echo 'console.log("[Timetics Occupied Slots Performance]", ' . json_encode($metrics) . ');';
        echo '</script>';
    }
    
    /**
     * Get performance metrics
     */
    private function get_performance_metrics()
    {
        return [
            'assets_loaded' => $this->performance_metrics['assets_loaded'],
            'assets_skipped' => $this->performance_metrics['assets_skipped'],
            'load_time' => round($this->performance_metrics['load_time'] * 1000, 2) . 'ms',
            'cache_hit_rate' => $this->calculate_cache_hit_rate(),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB'
        ];
    }
    
    /**
     * Calculate cache hit rate
     */
    private function calculate_cache_hit_rate()
    {
        $total = $this->performance_metrics['cache_hits'] + $this->performance_metrics['cache_misses'];
        
        if ($total === 0) {
            return '0%';
        }
        
        $hit_rate = ($this->performance_metrics['cache_hits'] / $total) * 100;
        return round($hit_rate, 2) . '%';
    }
    
    /**
     * Get asset loading statistics
     */
    public function get_asset_statistics()
    {
        return [
            'total_assets' => count($this->asset_config['js']) + count($this->asset_config['css']),
            'loaded_assets' => $this->performance_metrics['assets_loaded'],
            'skipped_assets' => $this->performance_metrics['assets_skipped'],
            'load_efficiency' => $this->calculate_load_efficiency(),
            'cache_performance' => $this->get_cache_performance()
        ];
    }
    
    /**
     * Calculate load efficiency
     */
    private function calculate_load_efficiency()
    {
        $total = $this->performance_metrics['assets_loaded'] + $this->performance_metrics['assets_skipped'];
        
        if ($total === 0) {
            return '100%';
        }
        
        $efficiency = ($this->performance_metrics['assets_loaded'] / $total) * 100;
        return round($efficiency, 2) . '%';
    }
    
    /**
     * Get cache performance
     */
    private function get_cache_performance()
    {
        $total = $this->performance_metrics['cache_hits'] + $this->performance_metrics['cache_misses'];
        
        if ($total === 0) {
            return 'No cache activity';
        }
        
        $hit_rate = ($this->performance_metrics['cache_hits'] / $total) * 100;
        
        if ($hit_rate >= 80) {
            return 'Excellent';
        } elseif ($hit_rate >= 60) {
            return 'Good';
        } elseif ($hit_rate >= 40) {
            return 'Fair';
        } else {
            return 'Poor';
        }
    }
    
    /**
     * Clear asset cache
     */
    public function clear_asset_cache()
    {
        // Clear transients
        global $wpdb;
        
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_occupied_slots_asset_%'
        ");
        
        // Clear object cache
        wp_cache_flush();
        
        // Reset metrics
        $this->performance_metrics = [
            'assets_loaded' => 0,
            'assets_skipped' => 0,
            'load_time' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0
        ];
    }
}
