<?php
/**
 * Safe Variable Integration for Lead Capture System
 * Prevents conflicts with existing booking system variables
 */

if (!defined('ABSPATH')) exit;

class BSP_Safe_Variable_Integration {
    
    private static $instance = null;
    private static $detected_variables = [];
    private static $safe_namespace = 'bspLeadCapture';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize safe integration
        add_action('wp_enqueue_scripts', [$this, 'enqueue_safe_integration'], 5); // Early priority
    }
    
    /**
     * Enqueue safe integration script before other booking scripts
     */
    public function enqueue_safe_integration() {
        // Only load on pages with booking forms
        if (!$this->is_booking_page()) {
            return;
        }
        
        wp_enqueue_script(
            'bsp-safe-integration',
            plugin_dir_url(__DIR__) . 'assets/js/safe-integration.js',
            [], // No dependencies to load early
            '1.0.0',
            false // Load in head before other scripts
        );
        
        // Pass configuration to JavaScript
        wp_localize_script('bsp-safe-integration', 'bspSafeConfig', [
            'namespace' => self::$safe_namespace,
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bsp_lead_capture_nonce')
        ]);
    }
    
    /**
     * Check if current page has booking forms
     */
    private function is_booking_page() {
        // Check for booking system shortcode or specific page templates
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Check for shortcode in post content
        if (has_shortcode($post->post_content, 'booking_form') || 
            has_shortcode($post->post_content, 'booking_system_form') ||
            has_shortcode($post->post_content, 'booking_system_pro')) {
            return true;
        }
        
        // Check for booking page template or specific pages
        $booking_pages = ['booking', 'quote', 'estimate', 'schedule'];
        foreach ($booking_pages as $page_slug) {
            if (strpos($post->post_name, $page_slug) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Register detected variables for conflict avoidance
     */
    public static function register_detected_variable($variable_name, $context = '') {
        self::$detected_variables[$variable_name] = [
            'context' => $context,
            'detected_at' => current_time('mysql'),
            'safe_alternative' => self::$safe_namespace . '_' . $variable_name
        ];
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("BSP Safe Integration: Detected variable '$variable_name' in context '$context'");
        }
    }
    
    /**
     * Get safe variable name to avoid conflicts
     */
    public static function get_safe_variable_name($requested_name) {
        if (isset(self::$detected_variables[$requested_name])) {
            return self::$detected_variables[$requested_name]['safe_alternative'];
        }
        
        return self::$safe_namespace . '_' . $requested_name;
    }
    
    /**
     * Check if a variable name is safe to use
     */
    public static function is_safe_variable_name($variable_name) {
        $reserved_names = [
            'formState',
            'selectedAppointments', 
            'BookingSystem',
            'jQuery',
            '$',
            'sessionStorage',
            'localStorage'
        ];
        
        return !in_array($variable_name, $reserved_names) && 
               !isset(self::$detected_variables[$variable_name]);
    }
    
    /**
     * Get all detected variables for debugging
     */
    public static function get_detected_variables() {
        return self::$detected_variables;
    }
}

// Initialize the safe integration
BSP_Safe_Variable_Integration::get_instance();
