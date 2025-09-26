<?php
/**
 * Plugin Name:     Booking System Pro
 * Plugin URI:      https://amcomputerstx.com/
 * Description:     Professional booking system with unified admin interface, comprehensive debugging, and complete functionality
 * Version:         2.1.0
 * Author:          AM Computerstx
 * Text Domain:     booking-system-pro
 * Domain Path:     /languages
 * License:         GPL v2 or later
 */

if (!defined('ABSPATH')) exit;

// Plugin Constants
define('BSP_VERSION', '2.1.0');
define('BSP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BSP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BSP_PLUGIN_FILE', __FILE__);
define('BSP_DB_VERSION', '2.1');

// Debug constants
define('BSP_DEBUG_MODE', true); // Enable for Phase 2 development
define('BSP_DEBUG_LOG_FILE', BSP_PLUGIN_DIR . 'debug.log');

/**
 * Enhanced debug logging function for Lead Capture System
 * Logs only to plugin directory, not WordPress core logs
 */
function bsp_debug_log($message, $type = 'INFO', $context = []) {
    // Always log during development - we'll filter later
    $log_file = BSP_PLUGIN_DIR . 'bsp-debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $memory_usage = round(memory_get_usage() / 1024 / 1024, 2) . 'MB';
    
    // Format context data
    $context_str = '';
    if (!empty($context)) {
        if (is_array($context) || is_object($context)) {
            $context_str = ' | DATA: ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        } else {
            $context_str = ' | DATA: ' . $context;
        }
    }
    
    // Add request context for better debugging
    $request_id = isset($_SERVER['REQUEST_URI']) ? md5($_SERVER['REQUEST_URI'] . microtime()) : 'CLI';
    $request_id = substr($request_id, 0, 8);
    
    $log_message = sprintf(
        "[%s] [%s] [%s] [%s] %s%s%s",
        $timestamp,
        $type,
        $memory_usage,
        $request_id,
        $message,
        $context_str,
        PHP_EOL
    );
    
    // Write to plugin log file
    error_log($log_message, 3, $log_file);
    
    // Also log critical errors to PHP error log for immediate attention
    if (in_array($type, ['ERROR', 'FATAL', 'CRITICAL'])) {
        error_log("BSP Plugin [$type]: $message");
    }
}

/**
 * Lead Capture specific logging function
 */
function bsp_lead_log($message, $lead_data = [], $type = 'LEAD_CAPTURE') {
    $formatted_message = "Lead Capture: $message";
    bsp_debug_log($formatted_message, $type, $lead_data);
}

/**
 * Rotate and clean log file when it gets too large
 */
function bsp_rotate_log_file() {
    $log_file = BSP_PLUGIN_DIR . 'bsp-debug.log';
    
    if (!file_exists($log_file)) return;
    
    // Check file size (rotate if > 5MB)
    if (filesize($log_file) > 5 * 1024 * 1024) {
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return;
        
        // Keep last 1000 lines for history
        $keep_lines = array_slice($lines, -1000);
        $rotated_content = "=== LOG ROTATED " . date('Y-m-d H:i:s') . " ===" . PHP_EOL;
        $rotated_content .= implode(PHP_EOL, $keep_lines) . PHP_EOL;
        
        file_put_contents($log_file, $rotated_content);
        bsp_debug_log("Log file rotated, kept last 1000 entries", 'SYSTEM');
    }
}

/**
 * Performance monitoring function
 */
function bsp_performance_log($action, $start_time = null) {
    if (!BSP_DEBUG_MODE) return microtime(true);
    
    if ($start_time !== null) {
        $duration = microtime(true) - $start_time;
        if ($duration > 1.0) { // Only log slow operations
            bsp_debug_log("Performance: {$action} took " . round($duration, 3) . "s", 'PERFORMANCE');
        }
    }
    
    return microtime(true);
}

/**
 * Memory usage logging
 */
function bsp_memory_log($checkpoint) {
    if (!BSP_DEBUG_MODE) return;
    
    $memory = memory_get_usage(true);
    if ($memory > 50 * 1024 * 1024) { // Only log if over 50MB
        bsp_debug_log("Memory at {$checkpoint}: " . round($memory / 1024 / 1024, 2) . "MB", 'PERFORMANCE');
    }
}

/**
 * System info logging
 */
function bsp_system_info_log() {
    if (!BSP_DEBUG_MODE) return;
    
    // Only log system info when plugin is activated
    $last_logged = get_option('bsp_system_info_logged', 0);
    if (time() - $last_logged < 86400) return; // 24 hours
    
    $info = [
        'PHP Version' => PHP_VERSION,
        'WordPress Version' => get_bloginfo('version'),
        'Plugin Version' => BSP_VERSION,
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ];
    
    bsp_debug_log("System info: " . json_encode($info), 'INFO');
    update_option('bsp_system_info_logged', time());
}

// Main Plugin Class
final class Booking_System_Pro_Final {
    
    private static $instance = null;
    private $database = null;
    private $frontend = null;
    private $admin = null;
    private $ajax = null;
    private $email = null;
    private $post_types = null;
    private $taxonomies = null;
    
    // Lead Capture System components
    private $safe_integration = null;
    private $utm_manager = null;
    private $lead_collector = null;
    private $data_processor = null;
    private $conversion_tracker = null;
    private $sheets_integration = null;
    
    private $components_loaded = [];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init'], 10);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Early initialization for debugging
        if (BSP_DEBUG_MODE) {
            add_action('init', [$this, 'early_debug_init'], -999);
        }
    }
    
    public function early_debug_init() {
        if (BSP_DEBUG_MODE) {
            bsp_system_info_log();
        }
    }
    
    public function init() {
        try {
            // Load text domain
            $this->load_textdomain();
            
            // Include core files
            $this->include_files();
            
            // Initialize components
            $this->init_core_components();
            
            // Setup hooks
            $this->setup_hooks();
            
            // Initialize legacy compatibility
            $this->init_legacy_compatibility();
            
            // Initialize Toast Notification System (Always Active)
            $this->init_toast_notifications();
            
        } catch (Exception $e) {
            bsp_debug_log("Exception during init: " . $e->getMessage(), 'ERROR', [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        } catch (Error $e) {
            bsp_debug_log("Fatal error during init: " . $e->getMessage(), 'FATAL', [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
    
    private function load_textdomain() {
        load_plugin_textdomain(
            'booking-system-pro', 
            false, 
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    private function include_files() {
        $includes = [
            'includes/class-utilities.php',
            'includes/class-database-unified.php',
            'includes/class-field-mapper.php', // Centralized field naming - must be loaded early
            // Lead Capture System - Phase 1 components (order matters)
            'includes/class-safe-variable-integration.php',
            'includes/class-lead-data-collector.php', // Must be loaded before data processor
            'includes/class-data-processor-unified.php', // Centralized data processing
            'includes/class-post-types.php',
            'includes/class-taxonomies.php',
            'includes/class-frontend.php',
            'includes/class-admin.php',
            'includes/class-admin-dashboard.php',
            'includes/class-admin-bookings.php',
            'includes/class-admin-companies.php',
            'includes/class-admin-settings.php',
            'includes/class-ajax.php',
            'includes/class-email.php',
            'includes/class-data-manager.php',
            // Phase 2 components
            'includes/class-utm-consistency-manager.php',
            'includes/class-lead-conversion-tracker.php',
            'includes/class-google-sheets-integration.php'
        ];
        
        foreach ($includes as $file) {
            $this->include_file($file);
        }
    }
    
    private function include_file($file) {
        $path = BSP_PLUGIN_DIR . $file;
        
        if (file_exists($path)) {
            require_once $path;
            $this->components_loaded[] = $file;
        } else {
            bsp_debug_log("File not found: {$file}", 'ERROR');
        }
    }
    
    private function init_core_components() {
        try {
            // Initialize database first - everything else depends on it
            if (class_exists('BSP_Database_Unified')) {
                $this->database = BSP_Database_Unified::get_instance();
                $this->database->init_tables();
                $this->components_loaded[] = 'database';
            } else {
                bsp_debug_log("BSP_Database_Unified class not found", 'ERROR');
            }
            
            // Initialize Lead Capture System components (order is critical)
            
            // 1. Safe Variable Integration (must be first)
            if (class_exists('BSP_Safe_Variable_Integration')) {
                $this->safe_integration = BSP_Safe_Variable_Integration::get_instance();
                $this->components_loaded[] = 'safe_integration';
                bsp_debug_log("Safe Variable Integration initialized", 'INIT');
            } else {
                bsp_debug_log("BSP_Safe_Variable_Integration class not found", 'ERROR');
            }
            
            // 2. UTM Consistency Manager (depends on safe integration)  
            if (class_exists('BSP_UTM_Consistency_Manager')) {
                $this->utm_manager = BSP_UTM_Consistency_Manager::get_instance();
                $this->components_loaded[] = 'utm_manager';
                bsp_debug_log("UTM Consistency Manager initialized", 'INIT');
            } else {
                bsp_debug_log("BSP_UTM_Consistency_Manager class not found", 'ERROR');
            }
            
            // 3. Lead Data Collector (depends on both safe integration and UTM manager)
            if (class_exists('BSP_Lead_Data_Collector')) {
                $this->lead_collector = BSP_Lead_Data_Collector::get_instance();
                $this->components_loaded[] = 'lead_collector';
                bsp_debug_log("Lead Data Collector initialized", 'INIT');
            } else {
                bsp_debug_log("BSP_Lead_Data_Collector class not found", 'ERROR');
            }
            
            // 4. Data Processor (depends on lead collector)
            if (class_exists('BSP_Data_Processor_Unified')) {
                $this->data_processor = BSP_Data_Processor_Unified::get_instance();
                $this->components_loaded[] = 'data_processor';
                bsp_debug_log("Unified Data Processor initialized", 'INIT');
            } else {
                bsp_debug_log("BSP_Data_Processor_Unified class not found", 'ERROR');
            }
            
            // 5. Lead Conversion Tracker
            if (class_exists('BSP_Lead_Conversion_Tracker')) {
                $this->conversion_tracker = BSP_Lead_Conversion_Tracker::get_instance();
                $this->components_loaded[] = 'conversion_tracker';
                bsp_debug_log("Lead Conversion Tracker initialized", 'INIT');
            } else {
                bsp_debug_log("BSP_Lead_Conversion_Tracker class not found", 'ERROR');
            }
            
            // 6. Google Sheets Integration
            if (class_exists('BSP_Google_Sheets_Integration')) {
                $this->sheets_integration = BSP_Google_Sheets_Integration::get_instance();
                $this->components_loaded[] = 'sheets_integration';
                bsp_debug_log("Google Sheets Integration initialized", 'INIT');
            } else {
                bsp_debug_log("BSP_Google_Sheets_Integration class not found", 'ERROR');
            }

            // Initialize post types and taxonomies
            if (class_exists('BSP_Post_Types')) {
                $this->post_types = BSP_Post_Types::get_instance();
                $this->components_loaded[] = 'post_types';
            } else {
                bsp_debug_log("BSP_Post_Types class not found", 'ERROR');
            }
            
            // Initialize frontend (only if not in admin)
            if (class_exists('BSP_Frontend')) {
                $this->frontend = BSP_Frontend::get_instance();
                $this->components_loaded[] = 'frontend';
            } else {
                bsp_debug_log("BSP_Frontend class not found", 'ERROR');
            }
            
            // Initialize AJAX handlers (needed for both admin and frontend)
            if (class_exists('BSP_Ajax')) {
                $this->ajax = BSP_Ajax::get_instance();
            } else {
                bsp_debug_log("BSP_Ajax class not found", 'ERROR');
            }
            
            // Initialize email system
            if (class_exists('BSP_Email')) {
                $this->email = BSP_Email::get_instance();
            } else {
                bsp_debug_log("BSP_Email class not found", 'ERROR');
            }
            
            // Initialize admin components (only in admin area)
            if (is_admin() && function_exists('current_user_can')) {
                $this->init_admin_components();
            }
            
        } catch (Exception $e) {
            bsp_debug_log("Error initializing components: " . $e->getMessage(), 'ERROR', [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
    
    private function init_admin_components() {
        // Main admin class
        if (class_exists('BSP_Admin')) {
            $this->admin = BSP_Admin::get_instance();
        } else {
            bsp_debug_log("BSP_Admin class not found", 'ERROR');
        }
        
        // Admin dashboard
        if (class_exists('BSP_Admin_Dashboard')) {
            BSP_Admin_Dashboard::get_instance();
        }
        
        // Admin bookings
        if (class_exists('BSP_Admin_Bookings')) {
            BSP_Admin_Bookings::get_instance();
        }
        
        // Admin companies
        if (class_exists('BSP_Admin_Companies')) {
            BSP_Admin_Companies::get_instance();
        }
        
        // Admin settings
        if (class_exists('BSP_Admin_Settings')) {
            BSP_Admin_Settings::get_instance();
        }
        
        // Data manager
        if (class_exists('BSP_Data_Manager')) {
            BSP_Data_Manager::get_instance();
        }
    }
    
    private function setup_hooks() {
        add_action('init', [$this, 'init_hooks'], 0);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        
        // Register background job handlers early (before they might be triggered)
        $this->register_background_jobs();
        
        // Debug hooks
        if (BSP_DEBUG_MODE) {
            add_action('wp_footer', [$this, 'debug_info_footer']);
            add_action('admin_footer', [$this, 'debug_info_footer']);
            add_action('shutdown', [$this, 'debug_shutdown']);
        }
    }
    
    private function init_legacy_compatibility() {
        // Ensure backward compatibility with old class names
        if (!class_exists('Booking_System_Admin') && class_exists('BSP_Admin')) {
            class_alias('BSP_Admin', 'Booking_System_Admin');
        }
        
        // Legacy shortcode compatibility
        if (!shortcode_exists('booking_system_form') && $this->frontend) {
            add_shortcode('booking_system_form', [$this->frontend, 'booking_form_shortcode']);
        }
    }
    
    /**
     * Register background job handlers for optimized form submission
     */
    private function register_background_jobs() {
        // Always register these action hooks, even if components aren't ready yet
        // The handlers will check for component availability when executed
        
        // Customer email notification handler
        add_action('bsp_send_customer_notification', [$this, 'handle_customer_notification_wrapper'], 10, 2);
        
        // Admin email notification handler  
        add_action('bsp_send_admin_notification', [$this, 'handle_admin_notification_wrapper'], 10, 2);
        
        // Booking extras processing handler
        add_action('bsp_process_booking_extras', [$this, 'handle_booking_extras_wrapper'], 10, 2);
        
        // Google Sheets sync handler
        add_action('bsp_sync_google_sheets', [$this, 'handle_google_sheets_sync'], 10, 2);
        
        // Incomplete lead background processing handler
        add_action('bsp_process_incomplete_lead_background', [$this, 'handle_incomplete_lead_processing'], 10, 2);
    }
    
    /**
     * Wrapper methods to ensure AJAX component is available when handlers are called
     */
    public function handle_customer_notification_wrapper($booking_id, $booking_data) {
        if ($this->ajax && method_exists($this->ajax, 'handle_customer_notification')) {
            $this->ajax->handle_customer_notification($booking_id, $booking_data);
        } else {
            bsp_debug_log("Customer notification handler called but AJAX component not available", 'ERROR');
            // Reschedule if component not ready (faster retry)
            if (function_exists('wp_schedule_single_event')) {
                wp_schedule_single_event(time() + 15, 'bsp_send_customer_notification', [$booking_id, $booking_data]);
            }
        }
    }
    
    public function handle_admin_notification_wrapper($booking_id, $booking_data) {
        if ($this->ajax && method_exists($this->ajax, 'handle_admin_notification')) {
            $this->ajax->handle_admin_notification($booking_id, $booking_data);
        } else {
            bsp_debug_log("Admin notification handler called but AJAX component not available", 'ERROR');
            // Reschedule if component not ready (faster retry)
            if (function_exists('wp_schedule_single_event')) {
                wp_schedule_single_event(time() + 15, 'bsp_send_admin_notification', [$booking_id, $booking_data]);
            }
        }
    }
    
    public function handle_booking_extras_wrapper($booking_id, $booking_data) {
        if ($this->ajax && method_exists($this->ajax, 'handle_booking_extras')) {
            $this->ajax->handle_booking_extras($booking_id, $booking_data);
        } else {
            bsp_debug_log("Booking extras handler called but AJAX component not available", 'ERROR');
            // Reschedule if component not ready (faster retry)
            if (function_exists('wp_schedule_single_event')) {
                wp_schedule_single_event(time() + 15, 'bsp_process_booking_extras', [$booking_id, $booking_data]);
            }
        }
    }
    
    public function init_hooks() {
        do_action('bsp_init');
    }
    
    public function admin_init() {
        do_action('bsp_admin_init');
    }
    
    public function frontend_assets() {
        if ($this->has_booking_shortcode()) {
            // Use minified CSS in production, regular CSS in debug mode
            $css_file = BSP_DEBUG_MODE ? 'booking-system.css' : 'booking-system.min.css';
            
            // Get companies data for the frontend
            $companies = [];
            if ($this->database) {
                $companies_raw = $this->database->get_companies();
                foreach ($companies_raw as $company) {
                    $companies[] = [
                        'id' => $company->id,
                        'name' => $company->name,
                        'phone' => $company->phone,
                        'address' => $company->address,
                        'email' => $company->email,
                        'rating' => $company->rating ?? 4.5,
                        'total_reviews' => $company->total_reviews ?? 0
                    ];
                }
            }
            
            // MOBILE-FIRST OPTIMIZATION: Detect mobile and load accordingly
            $is_mobile = wp_is_mobile();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $is_slow_connection = strpos($user_agent, '2G') !== false || strpos($user_agent, 'slow') !== false;
            
            if ($is_mobile || $is_slow_connection) {
                // MOBILE/SLOW CONNECTION: Maximum optimization with advanced features
                wp_enqueue_script('bsp-mobile-optimized', BSP_PLUGIN_URL . 'assets/js/booking-system-mobile-optimized.js', [], BSP_VERSION, false); // Load in head for immediate execution
                
                // Defer non-critical CSS
                wp_enqueue_style('bsp-frontend', BSP_PLUGIN_URL . 'assets/css/' . $css_file, [], BSP_VERSION, 'all');
                
                // Enhanced mobile configuration with performance settings
                wp_add_inline_script('bsp-mobile-optimized', "
                    window.BSP_MobileConfig = {
                        pluginUrl: '" . BSP_PLUGIN_URL . "',
                        ajaxUrl: '" . admin_url('admin-ajax.php') . "',
                        nonce: '" . wp_create_nonce('bsp_frontend_nonce') . "',
                        performance: {
                            targetLoadTime: 50,
                            enableWorker: true,
                            enablePredictive: true,
                            enableMemoryCleanup: true
                        },
                        deferredScripts: [
                            'zipcode-lookup.js',
                            'video-section-controller.js', 
                            'source-tracker.js',
                            'booking-system.js'
                        ],
                        debug: " . (BSP_DEBUG_MODE ? 'true' : 'false') . "
                    };
                ", 'before');
                
                // Enhanced mobile-specific localization
                wp_localize_script('bsp-mobile-optimized', 'BSP_Ajax', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('bsp_frontend_nonce'),
                    'companies' => $companies,
                    'debug' => BSP_DEBUG_MODE,
                    'isMobile' => true,
                    'strings' => [
                        'loading' => __('Loading...', 'booking-system-pro'),
                        'error' => __('An error occurred. Please try again.', 'booking-system-pro'),
                        'success' => __('Booking submitted successfully!', 'booking-system-pro'),
                        'select_service' => __('Please select a service', 'booking-system-pro'),
                        'select_time' => __('Please select a time slot', 'booking-system-pro'),
                        'fill_required' => __('Please fill in all required fields', 'booking-system-pro'),
                        'no_availability' => __('No availability found for selected dates.', 'booking-system-pro')
                    ]
                ]);
                
            } else {
                // DESKTOP: Normal loading for better performance on fast connections
                wp_enqueue_style('bsp-frontend', BSP_PLUGIN_URL . 'assets/css/' . $css_file, [], BSP_VERSION);
                
                // Enqueue JavaScript files in dependency order
                wp_enqueue_script('bsp-zipcode-lookup', BSP_PLUGIN_URL . 'assets/js/zipcode-lookup.js', ['jquery'], BSP_VERSION, true);
                wp_enqueue_script('bsp-video-controller', BSP_PLUGIN_URL . 'assets/js/video-section-controller.js', ['jquery'], BSP_VERSION, true);
                wp_enqueue_script('bsp-source-tracker', BSP_PLUGIN_URL . 'assets/js/source-tracker.js', ['jquery'], BSP_VERSION, true);
                wp_enqueue_script('bsp-frontend', BSP_PLUGIN_URL . 'assets/js/booking-system.js', ['jquery', 'bsp-zipcode-lookup', 'bsp-video-controller', 'bsp-source-tracker'], BSP_VERSION, true);
                
                // Desktop localization
                wp_localize_script('bsp-frontend', 'BSP_Ajax', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('bsp_frontend_nonce'),
                    'companies' => $companies,
                    'debug' => BSP_DEBUG_MODE,
                    'isMobile' => false,
                    'strings' => [
                        'loading' => __('Loading...', 'booking-system-pro'),
                        'error' => __('An error occurred. Please try again.', 'booking-system-pro'),
                        'success' => __('Booking submitted successfully!', 'booking-system-pro'),
                        'select_service' => __('Please select a service', 'booking-system-pro'),
                        'select_time' => __('Please select a time slot', 'booking-system-pro'),
                        'fill_required' => __('Please fill in all required fields', 'booking-system-pro'),
                        'no_availability' => __('No availability found for selected dates.', 'booking-system-pro')
                    ]
                ]);
            }
        }
    }
    
    public function admin_assets($hook) {
        if (strpos($hook, 'booking-system') !== false || strpos($hook, 'bsp-') !== false) {
            bsp_debug_log("Loading admin assets for hook: {$hook}", 'ASSETS');
            
            wp_enqueue_style('bsp-admin', BSP_PLUGIN_URL . 'assets/css/admin.css', [], BSP_VERSION);
            wp_enqueue_script('bsp-admin', BSP_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], BSP_VERSION, true);
            
            wp_localize_script('bsp-admin', 'BSP_Admin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bsp_admin_nonce'),
                'debug' => BSP_DEBUG_MODE,
                'strings' => [
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'booking-system-pro'),
                    'bulk_action_confirm' => __('Are you sure you want to perform this bulk action?', 'booking-system-pro'),
                    'loading' => __('Loading...', 'booking-system-pro'),
                    'error' => __('An error occurred. Please try again.', 'booking-system-pro'),
                    'success' => __('Operation completed successfully!', 'booking-system-pro')
                ]
            ]);
            
            bsp_debug_log("Admin assets loaded", 'ASSETS');
        }
    }
    
    private function has_booking_shortcode() {
        global $post;
        
        if (is_a($post, 'WP_Post')) {
            $shortcodes = ['booking_form', 'booking_calendar', 'booking_services', 'booking_system_form', 'booking_system_pro'];
            foreach ($shortcodes as $shortcode) {
                if (has_shortcode($post->post_content, $shortcode)) {
                    bsp_debug_log("Found shortcode: {$shortcode}", 'SHORTCODE');
                    return true;
                }
            }
        }
        
        // Also check if we're on a page that might use booking functionality
        if (is_page() || is_front_page()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Debug info in footer
     */
    public function debug_info_footer() {
        if (!BSP_DEBUG_MODE || !current_user_can('manage_options')) return;
        
        bsp_memory_log('footer');
        
        echo '<!-- BSP Debug Info -->';
        echo '<script>console.log("BSP Debug: Components loaded:", ' . json_encode($this->components_loaded) . ');</script>';
        
        if (is_admin()) {
            echo '<script>console.log("BSP Debug: Admin components active");</script>';
        } else {
            echo '<script>console.log("BSP Debug: Frontend mode active");</script>';
        }
    }
    
    /**
     * Debug shutdown hook
     */
    public function debug_shutdown() {
        if (!BSP_DEBUG_MODE) return;
        
        bsp_memory_log('shutdown');
        bsp_debug_log("=== PLUGIN SHUTDOWN ===", 'DEBUG');
        
        // Log any final information
        if (function_exists('wp_get_environment_type')) {
            bsp_debug_log("Environment: " . wp_get_environment_type(), 'DEBUG');
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $perf_timer = bsp_performance_log('activation');
        bsp_debug_log("=== ACTIVATION START ===", 'ACTIVATION');
        bsp_memory_log('activation_start');
        
        try {
            // Check if WordPress is fully loaded
            if (!function_exists('wp_get_current_user')) {
                bsp_debug_log("WordPress not fully loaded, skipping detailed activation", 'ACTIVATION');
                return;
            }
            
            bsp_debug_log("WordPress is loaded, proceeding with full activation", 'ACTIVATION');
            
            // Include only core files needed for activation
            $core_files = [
                'includes/class-utilities.php',
                'includes/class-database-unified.php',
                'includes/class-post-types.php',
                'includes/class-taxonomies.php'
            ];
            
            foreach ($core_files as $file) {
                $path = BSP_PLUGIN_DIR . $file;
                if (file_exists($path)) {
                    require_once $path;
                    bsp_debug_log("Included for activation: {$file}", 'ACTIVATION');
                } else {
                    bsp_debug_log("WARNING: Activation file not found: {$file}", 'WARNING');
                }
            }
            
            // Initialize only database for activation
            if (class_exists('BSP_Database_Unified')) {
                $database = BSP_Database_Unified::get_instance();
                
                // Create database tables
                $tables_created = $database->create_tables();
                bsp_debug_log("Database tables creation result: " . ($tables_created ? 'Success' : 'Failed'), 'ACTIVATION');
                
                // Create default data
                $default_data_created = $database->insert_default_data();
                bsp_debug_log("Default data creation result: " . ($default_data_created ? 'Success' : 'Failed'), 'ACTIVATION');
            } else {
                bsp_debug_log("ERROR: BSP_Database_Unified class not available for activation", 'ERROR');
            }
            
            // Create post types and taxonomies
            if (class_exists('BSP_Post_Types')) {
                BSP_Post_Types::register_post_types();
                bsp_debug_log("Post types registered during activation", 'ACTIVATION');
            }
            
            if (class_exists('BSP_Taxonomies')) {
                BSP_Taxonomies::register_taxonomies();
                bsp_debug_log("Taxonomies registered during activation", 'ACTIVATION');
            }
            
            // Flush rewrite rules
            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules();
                bsp_debug_log("Rewrite rules flushed", 'ACTIVATION');
            }
            
            // Set activation flags and options
            bsp_debug_log("Setting activation flags...", 'ACTIVATION');
            update_option('bsp_activated', true);
            update_option('bsp_activation_time', current_time('timestamp'));
            update_option('bsp_db_version', BSP_DB_VERSION);
            update_option('bsp_plugin_version', BSP_VERSION);
            
            // Set default settings
            $default_general_settings = [
                'enabled' => true,
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
                'default_status' => 'pending',
                'debug_mode' => BSP_DEBUG_MODE
            ];
            add_option('bsp_general_settings', $default_general_settings);
            
            $default_email_settings = [
                'admin_email' => get_option('admin_email'),
                'customer_confirmation' => true,
                'admin_notification' => true
            ];
            add_option('bsp_email_settings', $default_email_settings);
            
            $default_booking_settings = [
                'advance_days' => 30,
                'minimum_notice' => 24,
                'max_bookings_per_day' => 10
            ];
            add_option('bsp_booking_settings', $default_booking_settings);
            
            bsp_debug_log("Default settings created", 'ACTIVATION');
            
            bsp_memory_log('activation_end');
            bsp_debug_log("=== ACTIVATION COMPLETED SUCCESSFULLY ===", 'ACTIVATION');
            
        } catch (Exception $e) {
            bsp_debug_log("EXCEPTION during activation: " . $e->getMessage(), 'ERROR', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        } catch (Error $e) {
            bsp_debug_log("FATAL ERROR during activation: " . $e->getMessage(), 'FATAL', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        bsp_performance_log('activation', $perf_timer);
    }
    
    public function deactivate() {
        bsp_debug_log("=== DEACTIVATION START ===", 'DEACTIVATION');
        
        try {
            // Cleanup tasks
            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules();
                bsp_debug_log("Rewrite rules flushed on deactivation", 'DEACTIVATION');
            }
            
            // Update deactivation time but keep settings
            update_option('bsp_deactivated', true);
            update_option('bsp_deactivation_time', current_time('timestamp'));
            
            bsp_debug_log("=== DEACTIVATION COMPLETED ===", 'DEACTIVATION');
            
        } catch (Exception $e) {
            bsp_debug_log("ERROR during deactivation: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Get debug information for admin display
     */
    public function get_debug_info() {
        if (!BSP_DEBUG_MODE) return [];
        
        $info = [
            'plugin_version' => BSP_VERSION,
            'db_version' => BSP_DB_VERSION,
            'components_loaded' => $this->components_loaded,
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
            'log_file_exists' => file_exists(BSP_DEBUG_LOG_FILE),
            'log_file_size' => file_exists(BSP_DEBUG_LOG_FILE) ? round(filesize(BSP_DEBUG_LOG_FILE) / 1024, 2) . 'KB' : 'N/A'
        ];
        
        return $info;
    }
    
    /**
     * Clear debug log
     */
    public function clear_debug_log() {
        if (BSP_DEBUG_MODE && file_exists(BSP_DEBUG_LOG_FILE)) {
            // Create a clean UTF-8 log file
            $header = "=== BookingPro Debug Log - Cleared on " . date('Y-m-d H:i:s') . " ===" . PHP_EOL;
            
            if (function_exists('wp_filesystem')) {
                global $wp_filesystem;
                if (!$wp_filesystem) {
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    WP_Filesystem();
                }
                
                if ($wp_filesystem) {
                    $wp_filesystem->put_contents(BSP_DEBUG_LOG_FILE, $header, FS_CHMOD_FILE);
                }
            } else {
                file_put_contents(BSP_DEBUG_LOG_FILE, $header, LOCK_EX);
            }
            
            bsp_debug_log("Debug log cleared and reinitialized", 'DEBUG');
            return true;
        }
        return false;
    }
    
    /**
     * Fix corrupted debug log
     */
    public function fix_debug_log_encoding() {
        if (!BSP_DEBUG_MODE || !file_exists(BSP_DEBUG_LOG_FILE)) {
            return false;
        }
        
        // Backup the corrupted file
        $backup_file = BSP_DEBUG_LOG_FILE . '.corrupted.' . date('Y-m-d-H-i-s');
        copy(BSP_DEBUG_LOG_FILE, $backup_file);
        
        // Clear and reinitialize
        $this->clear_debug_log();
        
        bsp_debug_log("Debug log encoding fixed. Corrupted file backed up to: " . basename($backup_file), 'DEBUG');
        return true;
    }
    
    /**
     * Initialize Toast Notification System
     * Always loads to ensure toast notifications work on all pages
     */
    private function init_toast_notifications() {
        $toast_init_file = BSP_PLUGIN_DIR . 'toast-notifications/social-proof-init.php';
        
        if (file_exists($toast_init_file)) {
            require_once $toast_init_file;
            bsp_debug_log("Toast notification system initialized", 'TOAST');
        } else {
            bsp_debug_log("Toast notification init file not found: " . $toast_init_file, 'ERROR');
        }
    }
    
    /**
     * Handle background Google Sheets sync with retry logic
     */
    public function handle_google_sheets_sync($booking_id, $booking_data) {
        bsp_debug_log("Google Sheets background sync started for booking ID: $booking_id", 'INTEGRATION');
        
        // Check if already successfully synced
        $sync_status = get_post_meta($booking_id, '_google_sheets_synced', true);
        if ($sync_status === 'success') {
            bsp_debug_log("Google Sheets sync skipped - booking ID $booking_id already synced successfully", 'INTEGRATION');
            return;
        }
        
        // Check retry attempts (max 3 attempts)
        $attempts = (int)get_post_meta($booking_id, '_google_sheets_sync_attempts', true);
        if ($attempts >= 3) {
            bsp_debug_log("Google Sheets sync abandoned - booking ID $booking_id exceeded max retry attempts ($attempts)", 'INTEGRATION_ERROR');
            return;
        }
        
        // Attempt sync using the proper Google Sheets integration
        if (!$this->sheets_integration || !method_exists($this->sheets_integration, 'sync_converted_lead')) {
            bsp_debug_log("Google Sheets sync failed: Google Sheets component not available", 'INTEGRATION_ERROR');
            
            // Schedule retry in 30 seconds if component not ready (but only if under max attempts)
            if ($attempts < 2 && function_exists('wp_schedule_single_event')) {
                wp_schedule_single_event(time() + 30, 'bsp_sync_google_sheets', [$booking_id, $booking_data]);
                bsp_debug_log("Google Sheets sync rescheduled for 30 seconds due to component unavailability", 'INTEGRATION');
            }
            return;
        }
        
        // Attempt sync using the proper method that handles session continuity
        $sync_result = $this->sheets_integration->sync_converted_lead($booking_id, $booking_data);
        
        // Note: send_to_google_sheets logs its own success/failure, so we don't need to duplicate that here
        bsp_debug_log("Google Sheets background sync completed for booking ID: $booking_id, result: " . ($sync_result ? 'success' : 'failed'), 'INTEGRATION');
    }
    
    /**
     * Handle background processing of incomplete leads
     */
    public function handle_incomplete_lead_processing($lead_id, $lead_data) {
        bsp_debug_log("Background processing started for incomplete lead ID: $lead_id", 'LEAD_PROCESSING');
        
        // Get the Lead Data Collector instance
        $lead_collector = BSP_Lead_Data_Collector::get_instance();
        
        if (!$lead_collector || !method_exists($lead_collector, 'complete_lead_processing')) {
            bsp_debug_log("Lead processing failed: Lead Data Collector not available", 'LEAD_PROCESSING_ERROR');
            
            // Schedule retry in 15 seconds if component not ready
            if (function_exists('wp_schedule_single_event')) {
                wp_schedule_single_event(time() + 15, 'bsp_process_incomplete_lead_background', [$lead_id, $lead_data]);
                bsp_debug_log("Lead processing rescheduled for 15 seconds due to component unavailability", 'LEAD_PROCESSING');
            }
            return;
        }
        
        // Process the lead data completely
        $lead_collector->complete_lead_processing($lead_id, $lead_data);
        
        bsp_debug_log("Background processing completed for incomplete lead ID: $lead_id", 'LEAD_PROCESSING');
    }

    /**
     * Manual cron trigger for testing (admin only)
     */
    public function manual_cron_trigger() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['bsp_trigger_cron'])) {
            bsp_debug_log("Manual cron trigger activated", 'CRON');
            
            // Trigger any pending cron jobs
            if (function_exists('wp_cron')) {
                wp_cron();
                bsp_debug_log("wp_cron() executed manually", 'CRON');
            }
            
            // Also try spawn_cron for immediate execution
            if (function_exists('spawn_cron')) {
                spawn_cron();
                bsp_debug_log("spawn_cron() executed manually", 'CRON');
            }
            
            wp_redirect(admin_url('admin.php?page=booking-system&cron_triggered=1'));
            exit;
        }
    }
}

// Initialize the plugin
function BSP() {
    return Booking_System_Pro_Final::get_instance();
}

// Start the plugin
BSP();

// Additional debugging functions for template use
if (BSP_DEBUG_MODE) {
    /**
     * Template debugging function
     */
    function bsp_template_debug($template_name, $data = []) {
        bsp_debug_log("Template loaded: {$template_name}", 'TEMPLATE', $data);
    }
    
    /**
     * Database query debugging
     */
    function bsp_query_debug($query, $params = []) {
        bsp_debug_log("Database query: {$query}", 'DATABASE', $params);
    }
}

/**
 * Capture UTM parameters and set cookies on page load
 * This ensures marketing tracking data is captured server-side before any JavaScript issues
 */
function bsp_capture_marketing_parameters() {
    // UTM parameters and other marketing data to capture
    $marketing_params = [
        'utm_source',
        'utm_medium', 
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid'
    ];
    
    // Check for each parameter in the URL
    foreach ($marketing_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $cookie_name = 'bsp_' . $param;
            $value = sanitize_text_field($_GET[$param]);
            
            // Only set cookie if it doesn't already exist (preserve original source)
            if (!isset($_COOKIE[$cookie_name])) {
                setcookie($cookie_name, $value, time() + (30 * 24 * 60 * 60), '/');
                bsp_debug_log("Set marketing cookie: {$cookie_name} = {$value}", 'MARKETING');
            }
        }
    }
    
    // Handle referrer separately
    if (!isset($_COOKIE['bsp_referrer']) && isset($_SERVER['HTTP_REFERER'])) {
        $referrer = sanitize_text_field($_SERVER['HTTP_REFERER']);
        // Only set if referrer is from external domain
        if (strpos($referrer, $_SERVER['HTTP_HOST']) === false) {
            setcookie('bsp_referrer', $referrer, time() + (30 * 24 * 60 * 60), '/');
            bsp_debug_log("Set referrer cookie: {$referrer}", 'MARKETING');
        }
    }
    
    // Set direct source if no marketing data exists
    if (!isset($_COOKIE['bsp_utm_source'])) {
        $has_marketing_data = false;
        foreach ($marketing_params as $param) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                $has_marketing_data = true;
                break;
            }
        }
        
        if (!$has_marketing_data && !isset($_COOKIE['bsp_utm_source'])) {
            setcookie('bsp_utm_source', 'direct', time() + (30 * 24 * 60 * 60), '/');
            bsp_debug_log("Set direct source cookie", 'MARKETING');
        }
    }
}

// Hook the marketing capture function to WordPress init
add_action('init', 'bsp_capture_marketing_parameters');
