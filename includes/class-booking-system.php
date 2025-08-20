<?php
/**
 * Main Booking System Class
 */
class Booking_System {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }
    
    public function init() {
        // Initialize components
        new Booking_System_Database();
        new Booking_System_Form_Handler();
        new Booking_System_Email_Notifications();
        
        // Use new unified admin class if available, fallback to old one
        if (class_exists('BSP_Admin')) {
            BSP_Admin::get_instance();
        } elseif (class_exists('Booking_System_Admin')) {
            new Booking_System_Admin();
        }
        
        new Booking_System_Shortcode();
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style(
            'booking-system-style',
            BOOKING_SYSTEM_PLUGIN_URL . 'assets/css/booking-system.css',
            array(),
            BOOKING_SYSTEM_VERSION
        );
        
        wp_enqueue_script(
            'booking-system-script',
            BOOKING_SYSTEM_PLUGIN_URL . 'assets/js/booking-system.js',
            array('jquery'),
            BOOKING_SYSTEM_VERSION,
            true
        );
        
        wp_localize_script('booking-system-script', 'booking_system_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('booking_system_nonce')
        ));
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'booking-system') !== false) {
            wp_enqueue_style(
                'booking-system-admin-style',
                BOOKING_SYSTEM_PLUGIN_URL . 'assets/css/booking-system.css',
                array(),
                BOOKING_SYSTEM_VERSION
            );
        }
    }
}
?>
