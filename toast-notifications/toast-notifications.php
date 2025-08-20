<?php
/**
 * Toast Notification System for BookingPro
 * 
 * A comprehensive notification system that integrates with the BookingPro plugin
 * to display real-time notifications about bookings, services, and customer activities.
 * 
 * @package BookingPro_Toast_Notifications
 * @version 1.0.0
 * @author BookingPro Extension Team
 */

if (!defined('ABSPATH')) exit;

// Only load if BookingPro is active
if (!class_exists('Booking_System_Pro_Final')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>BookingPro Toast Notifications requires the BookingPro plugin to be active.</p></div>';
    });
    return;
}

// Define constants
define('BSP_TOAST_VERSION', '1.0.0');
define('BSP_TOAST_PATH', plugin_dir_path(__FILE__));
define('BSP_TOAST_URL', plugin_dir_url(__FILE__));

/**
 * Main Toast Notification System Class
 */
class BSP_Toast_Notifications {
    
    private static $instance = null;
    private $db;
    private $notification_types = [];
    private $settings;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Get database instance from main plugin
        $this->db = BSP_Database_Unified::get_instance();
        
        // Load settings
        $this->settings = get_option('bsp_toast_settings', $this->get_default_settings());
        
        // Initialize notification types
        $this->init_notification_types();
        
        // Hook into WordPress and BookingPro events
        $this->setup_hooks();
        
        // Load components
        $this->load_components();
    }
    
    private function setup_hooks() {
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_footer', [$this, 'render_toast_container']);
        
        // BookingPro integration hooks
        add_action('wp_ajax_bsp_toast_get_notifications', [$this, 'ajax_get_notifications']);
        add_action('wp_ajax_nopriv_bsp_toast_get_notifications', [$this, 'ajax_get_notifications']);
        
        // Listen for booking events
        add_action('save_post', [$this, 'on_booking_created'], 10, 2);
        add_action('transition_post_status', [$this, 'on_booking_status_change'], 10, 3);
        
        // Generate sample notifications
        add_action('wp_ajax_bsp_generate_sample_notification', [$this, 'generate_sample_notification']);
        
        // Auto-generate notifications
        if ($this->settings['auto_generate']) {
            add_action('init', [$this, 'schedule_auto_notifications']);
        }
    }
    
    private function load_components() {
        require_once BSP_TOAST_PATH . 'includes/class-notification-generator.php';
        require_once BSP_TOAST_PATH . 'includes/class-location-service.php';
        require_once BSP_TOAST_PATH . 'includes/class-statistics-generator.php';
    }
    
    private function init_notification_types() {
        $this->notification_types = [
            'booking_created' => [
                'label' => 'New Booking',
                'icon' => '📅',
                'color' => '#4CAF50',
                'enabled' => true
            ],
            'booking_confirmed' => [
                'label' => 'Booking Confirmed',
                'icon' => '✅',
                'color' => '#2196F3',
                'enabled' => true
            ],
            'popular_service' => [
                'label' => 'Popular Service',
                'icon' => '🔥',
                'color' => '#FF9800',
                'enabled' => true
            ],
            'customer_review' => [
                'label' => 'Customer Review',
                'icon' => '⭐',
                'color' => '#FFC107',
                'enabled' => true
            ],
            'location_activity' => [
                'label' => 'Location Activity',
                'icon' => '🗺️',
                'color' => '#9C27B0',
                'enabled' => true
            ],
            'company_achievement' => [
                'label' => 'Company Achievement',
                'icon' => '🏆',
                'color' => '#795548',
                'enabled' => true
            ],
            'email_success' => [
                'label' => 'Email Delivered',
                'icon' => '📧',
                'color' => '#607D8B',
                'enabled' => false
            ],
            'system_update' => [
                'label' => 'System Update',
                'icon' => '🔄',
                'color' => '#3F51B5',
                'enabled' => false
            ]
        ];
    }
    
    private function get_default_settings() {
        return [
            'enabled' => true,
            'position' => 'top-right',
            'duration' => 5000,
            'max_notifications' => 3,
            'auto_generate' => true,
            'frequency' => 30, // seconds
            'show_location' => true,
            'show_company' => true,
            'show_service' => true,
            'animation' => 'slide',
            'sound' => false
        ];
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'booking-system-pro',
            'Toast Notifications',
            'Notifications',
            'manage_options',
            'bsp-toast-notifications',
            [$this, 'render_admin_page']
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'bsp-toast-notifications') === false) {
            return;
        }
        
        wp_enqueue_style(
            'bsp-toast-admin',
            BSP_TOAST_URL . 'assets/css/admin.css',
            [],
            BSP_TOAST_VERSION
        );
        
        wp_enqueue_script(
            'bsp-toast-admin',
            BSP_TOAST_URL . 'assets/js/admin.js',
            ['jquery'],
            BSP_TOAST_VERSION,
            true
        );
    }
    
    public function enqueue_frontend_assets() {
        if (!$this->settings['enabled']) {
            return;
        }
        
        wp_enqueue_style(
            'bsp-toast-frontend',
            BSP_TOAST_URL . 'assets/css/toast.css',
            [],
            BSP_TOAST_VERSION
        );
        
        wp_enqueue_script(
            'bsp-toast-frontend',
            BSP_TOAST_URL . 'assets/js/toast.js',
            ['jquery'],
            BSP_TOAST_VERSION,
            true
        );
        
        wp_localize_script('bsp-toast-frontend', 'BSP_Toast', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bsp_toast_nonce'),
            'settings' => $this->settings,
            'types' => $this->notification_types
        ]);
    }
    
    public function render_toast_container() {
        if (!$this->settings['enabled']) {
            return;
        }
        
        echo '<div id="bsp-toast-container" class="bsp-toast-' . esc_attr($this->settings['position']) . '"></div>';
    }
    
    public function ajax_get_notifications() {
        check_ajax_referer('bsp_toast_nonce', 'nonce');
        
        $generator = new BSP_Toast_Notification_Generator($this->db);
        $notifications = $generator->get_random_notifications(1);
        
        wp_send_json_success($notifications);
    }
    
    public function generate_sample_notification() {
        check_ajax_referer('bsp_toast_nonce', 'nonce');
        
        $type = sanitize_text_field($_POST['type'] ?? 'booking_created');
        $generator = new BSP_Toast_Notification_Generator($this->db);
        
        $notification = $generator->generate_notification($type);
        
        wp_send_json_success($notification);
    }
    
    public function on_booking_created($post_id, $post) {
        if ($post->post_type !== 'bsp_booking') {
            return;
        }
        
        // Generate real-time notification for new booking
        $this->create_notification('booking_created', [
            'booking_id' => $post_id,
            'customer_name' => get_post_meta($post_id, '_customer_name', true),
            'service' => get_post_meta($post_id, '_service_type', true),
            'company' => get_post_meta($post_id, '_company_name', true),
            'location' => get_post_meta($post_id, '_zip_code', true)
        ]);
    }
    
    public function on_booking_status_change($new_status, $old_status, $post) {
        if ($post->post_type !== 'bsp_booking') {
            return;
        }
        
        if ($new_status === 'confirmed') {
            $this->create_notification('booking_confirmed', [
                'booking_id' => $post->ID,
                'customer_name' => get_post_meta($post->ID, '_customer_name', true),
                'service' => get_post_meta($post->ID, '_service_type', true)
            ]);
        }
    }
    
    public function schedule_auto_notifications() {
        if (!wp_next_scheduled('bsp_toast_auto_generate')) {
            wp_schedule_event(time(), 'bsp_toast_interval', 'bsp_toast_auto_generate');
        }
        
        add_action('bsp_toast_auto_generate', [$this, 'generate_auto_notification']);
    }
    
    public function generate_auto_notification() {
        $generator = new BSP_Toast_Notification_Generator($this->db);
        $notification = $generator->get_random_notifications(1)[0];
        
        // Store in transient for frontend to pick up
        set_transient('bsp_toast_notification_' . time(), $notification, 60);
    }
    
    private function create_notification($type, $data) {
        $notification = [
            'type' => $type,
            'data' => $data,
            'timestamp' => time()
        ];
        
        set_transient('bsp_toast_notification_' . time(), $notification, 60);
    }
    
    public function render_admin_page() {
        include BSP_TOAST_PATH . 'templates/admin-page.php';
    }
}

// Initialize the toast notification system
BSP_Toast_Notifications::get_instance();

// Add custom cron interval
add_filter('cron_schedules', function($schedules) {
    $schedules['bsp_toast_interval'] = [
        'interval' => 30, // 30 seconds
        'display' => __('Every 30 seconds')
    ];
    return $schedules;
});
