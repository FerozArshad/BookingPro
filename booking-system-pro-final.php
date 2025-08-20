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
define('BSP_DEBUG_MODE', defined('WP_DEBUG') && WP_DEBUG);
define('BSP_DEBUG_LOG_FILE', BSP_PLUGIN_DIR . 'debug.log');

/**
 * Advanced Debug logging function with multiple levels
 */
function bsp_debug_log($message, $level = 'INFO', $context = []) {
    if (!BSP_DEBUG_MODE) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = isset($backtrace[1]) ? $backtrace[1]['function'] : 'unknown';
    $file = isset($backtrace[0]) ? basename($backtrace[0]['file']) : 'unknown';
    $line = isset($backtrace[0]) ? $backtrace[0]['line'] : 'unknown';
    
    $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $log_message = sprintf(
        "[%s] [%s] [%s:%s:%s] %s%s%s",
        $timestamp,
        $level,
        $file,
        $line,
        $caller,
        $message,
        $context_str,
        PHP_EOL
    );
    
    file_put_contents(BSP_DEBUG_LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
    
    // Also log to WordPress debug.log if available
    if (function_exists('error_log')) {
        error_log("BSP [{$level}]: {$message}");
    }
}

/**
 * Performance monitoring function
 */
function bsp_performance_log($action, $start_time = null) {
    if (!BSP_DEBUG_MODE) return microtime(true);
    
    static $timers = [];
    
    if ($start_time === null) {
        $timers[$action] = microtime(true);
        bsp_debug_log("PERFORMANCE START: {$action}", 'PERF');
        return $timers[$action];
    } else {
        $end_time = microtime(true);
        $duration = $end_time - $start_time;
        bsp_debug_log("PERFORMANCE END: {$action} took {$duration} seconds", 'PERF');
        return $duration;
    }
}

/**
 * Memory usage logging
 */
function bsp_memory_log($checkpoint) {
    if (!BSP_DEBUG_MODE) return;
    
    $memory_mb = round(memory_get_usage(true) / 1024 / 1024, 2);
    $peak_mb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
    bsp_debug_log("MEMORY at {$checkpoint}: {$memory_mb}MB (Peak: {$peak_mb}MB)", 'MEMORY');
}

/**
 * System info logging
 */
function bsp_system_info_log() {
    if (!BSP_DEBUG_MODE) return;
    
    $info = [
        'PHP Version' => PHP_VERSION,
        'WordPress Version' => get_bloginfo('version'),
        'Plugin Version' => BSP_VERSION,
        'Active Theme' => wp_get_theme()->get('Name'),
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'Memory Limit' => ini_get('memory_limit'),
        'Max Execution Time' => ini_get('max_execution_time'),
        'Upload Max Size' => ini_get('upload_max_filesize'),
        'Post Max Size' => ini_get('post_max_size')
    ];
    
    bsp_debug_log("SYSTEM INFO: " . json_encode($info), 'SYSTEM');
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
    private $components_loaded = [];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        bsp_debug_log("=== PLUGIN CONSTRUCTOR START ===", 'INIT');
        bsp_memory_log('constructor_start');
        
        add_action('plugins_loaded', [$this, 'init'], 10);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Early initialization for debugging
        add_action('init', [$this, 'early_debug_init'], -999);
        
        bsp_debug_log("Plugin constructor completed", 'INIT');
        bsp_memory_log('constructor_end');
    }
    
    public function early_debug_init() {
        if (BSP_DEBUG_MODE) {
            bsp_system_info_log();
            bsp_debug_log("=== EARLY DEBUG INITIALIZATION ===", 'DEBUG');
        }
    }
    
    public function init() {
        $perf_timer = bsp_performance_log('plugin_init');
        bsp_debug_log("=== PLUGIN INITIALIZATION START ===", 'INIT');
        bsp_memory_log('init_start');
        
        try {
            // Load text domain
            $this->load_textdomain();
            
            // Include core files
            $this->include_files();
            
            // Initialize components
            $this->init_components();
            
            // Setup hooks
            $this->setup_hooks();
            
            // Initialize legacy compatibility
            $this->init_legacy_compatibility();
            
            // Initialize Toast Notification System (Always Active)
            $this->init_toast_notifications();
            
            bsp_debug_log("Plugin initialization completed successfully", 'INIT');
            bsp_memory_log('init_end');
            
        } catch (Exception $e) {
            bsp_debug_log("EXCEPTION during init: " . $e->getMessage(), 'ERROR', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        } catch (Error $e) {
            bsp_debug_log("FATAL ERROR during init: " . $e->getMessage(), 'FATAL', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        bsp_performance_log('plugin_init', $perf_timer);
    }
    
    private function load_textdomain() {
        $perf_timer = bsp_performance_log('load_textdomain');
        
        $loaded = load_plugin_textdomain(
            'booking-system-pro', 
            false, 
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
        
        bsp_debug_log("Text domain loaded: " . ($loaded ? 'Success' : 'Failed'), 'INIT');
        bsp_performance_log('load_textdomain', $perf_timer);
    }
    
    private function include_files() {
        $perf_timer = bsp_performance_log('include_files');
        bsp_debug_log("Starting file inclusion", 'INIT');
        
        $includes = [
            'includes/class-utilities.php',
            'includes/class-database-unified.php',
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
            'includes/class-data-manager.php'
        ];
        
        foreach ($includes as $file) {
            $this->include_file($file);
        }
        
        bsp_debug_log("File inclusion completed", 'INIT');
        bsp_performance_log('include_files', $perf_timer);
    }
    
    private function include_file($file) {
        $path = BSP_PLUGIN_DIR . $file;
        
        if (file_exists($path)) {
            $file_perf = bsp_performance_log("include_{$file}");
            require_once $path;
            $this->components_loaded[] = $file;
            bsp_debug_log("Included: {$file}", 'INIT');
            bsp_performance_log("include_{$file}", $file_perf);
        } else {
            bsp_debug_log("WARNING: File not found: {$file}", 'WARNING');
        }
    }
    
    private function init_components() {
        $perf_timer = bsp_performance_log('init_components');
        bsp_debug_log("Starting component initialization", 'INIT');
        
        try {
            // Initialize utilities first
            if (class_exists('BSP_Utilities')) {
                bsp_debug_log("BSP_Utilities class available", 'INIT');
            }
            
            // Initialize database first
            if (class_exists('BSP_Database_Unified')) {
                $this->database = BSP_Database_Unified::get_instance();
                bsp_debug_log("Database component initialized", 'INIT');
            } else {
                bsp_debug_log("ERROR: BSP_Database_Unified class not found", 'ERROR');
            }
            
            // Initialize post types and taxonomies
            if (class_exists('BSP_Post_Types')) {
                $this->post_types = BSP_Post_Types::get_instance();
                bsp_debug_log("Post types component initialized", 'INIT');
            } else {
                bsp_debug_log("ERROR: BSP_Post_Types class not found", 'ERROR');
            }
            
            if (class_exists('BSP_Taxonomies')) {
                $this->taxonomies = new BSP_Taxonomies();
                bsp_debug_log("Taxonomies component initialized", 'INIT');
            } else {
                bsp_debug_log("ERROR: BSP_Taxonomies class not found", 'ERROR');
            }
            
            // Initialize frontend (always needed for shortcodes)
            if (class_exists('BSP_Frontend')) {
                $this->frontend = BSP_Frontend::get_instance();
                bsp_debug_log("Frontend component initialized", 'INIT');
            } else {
                bsp_debug_log("ERROR: BSP_Frontend class not found", 'ERROR');
            }
            
            // Initialize AJAX handlers (needed for both admin and frontend)
            if (class_exists('BSP_Ajax')) {
                $this->ajax = BSP_Ajax::get_instance();
                bsp_debug_log("AJAX component initialized", 'INIT');
            } else {
                bsp_debug_log("ERROR: BSP_Ajax class not found", 'ERROR');
            }
            
            // Initialize email system
            if (class_exists('BSP_Email')) {
                $this->email = BSP_Email::get_instance();
                bsp_debug_log("Email component initialized", 'INIT');
            } else {
                bsp_debug_log("ERROR: BSP_Email class not found", 'ERROR');
            }
            
            // Initialize admin components (only in admin area)
            if (is_admin() && function_exists('current_user_can')) {
                $this->init_admin_components();
            }
            
        } catch (Exception $e) {
            bsp_debug_log("ERROR initializing components: " . $e->getMessage(), 'ERROR', [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
        
        bsp_debug_log("Component initialization completed", 'INIT');
        bsp_performance_log('init_components', $perf_timer);
    }
    
    private function init_admin_components() {
        bsp_debug_log("Initializing admin components", 'INIT');
        
        // Main admin class
        if (class_exists('BSP_Admin')) {
            $this->admin = BSP_Admin::get_instance();
            bsp_debug_log("Admin component initialized", 'INIT');
        } else {
            bsp_debug_log("ERROR: BSP_Admin class not found", 'ERROR');
        }
        
        // Admin dashboard
        if (class_exists('BSP_Admin_Dashboard')) {
            BSP_Admin_Dashboard::get_instance();
            bsp_debug_log("Admin dashboard component initialized", 'INIT');
        } else {
            bsp_debug_log("WARNING: BSP_Admin_Dashboard class not found", 'WARNING');
        }
        
        // Admin bookings
        if (class_exists('BSP_Admin_Bookings')) {
            BSP_Admin_Bookings::get_instance();
            bsp_debug_log("Admin bookings component initialized", 'INIT');
        } else {
            bsp_debug_log("WARNING: BSP_Admin_Bookings class not found", 'WARNING');
        }
        
        // Admin companies
        if (class_exists('BSP_Admin_Companies')) {
            BSP_Admin_Companies::get_instance();
            bsp_debug_log("Admin companies component initialized", 'INIT');
        } else {
            bsp_debug_log("WARNING: BSP_Admin_Companies class not found", 'WARNING');
        }
        
        // Admin settings
        if (class_exists('BSP_Admin_Settings')) {
            BSP_Admin_Settings::get_instance();
            bsp_debug_log("Admin settings component initialized", 'INIT');
        } else {
            bsp_debug_log("WARNING: BSP_Admin_Settings class not found", 'WARNING');
        }
        
        // Data manager
        if (class_exists('BSP_Data_Manager')) {
            BSP_Data_Manager::get_instance();
            bsp_debug_log("Data manager component initialized", 'INIT');
        } else {
            bsp_debug_log("WARNING: BSP_Data_Manager class not found", 'WARNING');
        }
    }
    
    private function setup_hooks() {
        $perf_timer = bsp_performance_log('setup_hooks');
        bsp_debug_log("Setting up hooks", 'INIT');
        
        add_action('init', [$this, 'init_hooks'], 0);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        
        // Debug hooks
        if (BSP_DEBUG_MODE) {
            add_action('wp_footer', [$this, 'debug_info_footer']);
            add_action('admin_footer', [$this, 'debug_info_footer']);
            add_action('shutdown', [$this, 'debug_shutdown']);
        }
        
        bsp_debug_log("Hooks setup completed", 'INIT');
        bsp_performance_log('setup_hooks', $perf_timer);
    }
    
    private function init_legacy_compatibility() {
        bsp_debug_log("Initializing legacy compatibility", 'INIT');
        
        // Ensure backward compatibility with old class names
        if (!class_exists('Booking_System_Admin') && class_exists('BSP_Admin')) {
            class_alias('BSP_Admin', 'Booking_System_Admin');
            bsp_debug_log("Created legacy alias: Booking_System_Admin", 'INIT');
        }
        
        // Legacy shortcode compatibility
        if (!shortcode_exists('booking_system_form') && $this->frontend) {
            add_shortcode('booking_system_form', [$this->frontend, 'booking_form_shortcode']);
            bsp_debug_log("Created legacy shortcode: booking_system_form", 'INIT');
        }
        
        bsp_debug_log("Legacy compatibility initialized", 'INIT');
    }
    
    public function init_hooks() {
        bsp_debug_log("Additional initialization hooks", 'HOOK');
        do_action('bsp_init');
    }
    
    public function admin_init() {
        bsp_debug_log("Admin initialization", 'HOOK');
        do_action('bsp_admin_init');
    }
    
    public function frontend_assets() {
        if ($this->has_booking_shortcode()) {
            bsp_debug_log("Loading frontend assets", 'ASSETS');
            
            // Enqueue frontend assets
            wp_enqueue_style('bsp-frontend', BSP_PLUGIN_URL . 'assets/css/booking-system.css', [], BSP_VERSION);
            wp_enqueue_script('bsp-frontend', BSP_PLUGIN_URL . 'assets/js/booking-system.js', ['jquery'], BSP_VERSION, true);
            
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
            
            // Localize script
            wp_localize_script('bsp-frontend', 'BSP_Ajax', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bsp_frontend_nonce'),
                'companies' => $companies,
                'debug' => BSP_DEBUG_MODE,
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
            
            bsp_debug_log("Frontend assets loaded", 'ASSETS');
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
            file_put_contents(BSP_DEBUG_LOG_FILE, '');
            bsp_debug_log("Debug log cleared", 'DEBUG');
            return true;
        }
        return false;
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
