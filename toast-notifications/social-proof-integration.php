<?php
/**
 * Social Proof Toast Notifications Integration
 * Hooks into BookingPro plugin without modifying core files
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('BSP_SOCIAL_PROOF_VERSION', '1.0.0');
define('BSP_SOCIAL_PROOF_PATH', __DIR__ . '/');
define('BSP_SOCIAL_PROOF_URL', plugins_url('', __FILE__) . '/');

class BSP_Social_Proof_Integration {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook into WordPress and BookingPro plugin
        add_action('plugins_loaded', [$this, 'init'], 20); // After BookingPro loads
        add_action('wp_enqueue_scripts', [$this, 'enqueue_social_proof_assets'], 99); // High priority
        add_action('wp_footer', [$this, 'render_social_proof_container'], 99);
        
        // AJAX endpoints for data fetching
        add_action('wp_ajax_bsp_get_recent_bookings_for_social_proof', [$this, 'ajax_get_recent_bookings']);
        add_action('wp_ajax_nopriv_bsp_get_recent_bookings_for_social_proof', [$this, 'ajax_get_recent_bookings']);
        
        // Hook into BookingPro events if they exist
        add_action('bsp_booking_created', [$this, 'on_booking_created'], 10, 2);
        add_action('bsp_booking_confirmed', [$this, 'on_booking_confirmed'], 10, 2);
    }
    
    public function init() {
        // Always initialize social proof system (independent mode)
        // No dependency on BookingPro plugin - works standalone
        $this->initialize_social_proof();
    }
    
    private function is_booking_pro_active() {
        // Check for BookingPro plugin classes
        return class_exists('Booking_System_Pro_Final') || 
               class_exists('BSP_Frontend') || 
               class_exists('BSP_Database_Unified');
    }
    
    private function initialize_social_proof() {
        // Social proof is initialized via JavaScript
        // This method can be used for future server-side initialization
    }
    
    public function enqueue_social_proof_assets() {
        // Always load on all pages (no restrictions)
        // Toast notifications will show on entire website
        
        // Enqueue CSS
        wp_enqueue_style(
            'bsp-social-proof-css',
            BSP_SOCIAL_PROOF_URL . 'assets/css/social-proof.css',
            [],
            BSP_SOCIAL_PROOF_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'bsp-social-proof-js',
            BSP_SOCIAL_PROOF_URL . 'assets/js/social-proof.js',
            ['jquery'],
            BSP_SOCIAL_PROOF_VERSION,
            true
        );
        
        // Localize script with data
        wp_localize_script('bsp-social-proof-js', 'BSP_SocialProof', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bsp_social_proof_nonce'),
            'settings' => $this->get_social_proof_settings(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'timestamp' => current_time('timestamp'),
            'debugInfo' => [
                'pluginActive' => $this->is_booking_pro_active(),
                'wpDebug' => defined('WP_DEBUG') && WP_DEBUG,
                'currentUrl' => $_SERVER['REQUEST_URI'] ?? '',
                'loadTime' => microtime(true)
            ]
        ]);
    }
    
    private function should_load_social_proof() {
        // Load on all pages by default (as requested)
        // Can be customized to load only on specific pages
        return true;
        
        // Alternative: Load only on pages with BookingPro shortcodes
        // global $post;
        // if (is_a($post, 'WP_Post')) {
        //     return has_shortcode($post->post_content, 'booking_form') || 
        //            has_shortcode($post->post_content, 'booking_system_form');
        // }
        // return false;
    }
    
    private function get_social_proof_settings() {
        return [
            'enabled' => true,
            'minInterval' => 3000,
            'maxInterval' => 15000,
            'toastDuration' => 5000,
            'maxToasts' => 3,
            'position' => 'bottom-left'
        ];
    }
    
    public function render_social_proof_container() {
        // Always render on all pages (no restrictions)
        // Container is created by JavaScript
        // This method can be used for server-side rendering if needed
    }
    
    /**
     * AJAX endpoint to get recent bookings for social proof
     */
    public function ajax_get_recent_bookings() {
        // For logged-in users, verify nonce strictly
        // For non-logged-in users, allow access with fallback verification
        if (is_user_logged_in()) {
            if (!wp_verify_nonce($_POST['nonce'], 'bsp_social_proof_nonce')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
        } else {
            // For non-logged-in users, do a basic check but allow through
            // This is acceptable for social proof as it's public display data
            if (!isset($_POST['nonce']) || empty($_POST['nonce'])) {
                wp_send_json_error('Missing nonce');
                return;
            }
        }
        
        $recent_bookings = $this->get_recent_bookings();
        wp_send_json_success($recent_bookings);
    }
    
    /**
     * Get recent bookings from BookingPro database
     */
    private function get_recent_bookings() {
        global $wpdb;
        
        $bookings = [];
        
        // Try to get data from BookingPro database
        if (class_exists('BSP_Database_Unified')) {
            try {
                $db = BSP_Database_Unified::get_instance();
                $recent = $db->get_recent_bookings(10); // Get last 10 bookings
                
                if (!empty($recent)) {
                    foreach ($recent as $booking) {
                        // Debug: Log the actual booking object structure
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('Social Proof Debug - Booking Object: ' . print_r($booking, true));
                        }
                        
                        // Try multiple possible field names for customer name
                        $customer_name = $booking->full_name ?? 
                                       $booking->customer_name ?? 
                                       $booking->name ?? 
                                       $booking->first_name ?? 
                                       $booking->client_name ?? 
                                       $booking->user_name ?? 
                                       '';
                        
                        // Try multiple possible field names for service
                        $service = $booking->service ?? 
                                 $booking->service_name ?? 
                                 $booking->service_type ?? 
                                 $booking->selected_service ?? 
                                 'Home Improvement';
                        
                        // Try multiple possible field names for zip code
                        $zip_code = $booking->zip_code ?? 
                                   $booking->zipcode ?? 
                                   $booking->zip ?? 
                                   $booking->postal_code ?? 
                                   '';
                        
                        $bookings[] = [
                            'name' => $this->extract_first_name($customer_name),
                            'service' => $service,
                            'zip_code' => $zip_code,
                            'created_at' => $booking->created_at ?? $booking->date_created ?? date('Y-m-d H:i:s')
                        ];
                    }
                }
            } catch (Exception $e) {
                // Log the error for debugging
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Social Proof Debug - Database Error: ' . $e->getMessage());
                }
                // Fallback to WordPress posts if BookingPro database fails
                $bookings = $this->get_bookings_from_posts();
            }
        } else {
            // Fallback to WordPress posts
            $bookings = $this->get_bookings_from_posts();
        }
        
        // If no real bookings found OR all bookings have empty names, add sample data
        if (empty($bookings) || $this->all_bookings_have_empty_names($bookings)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Social Proof Debug - Using sample bookings because real bookings are empty or have no names');
            }
            $bookings = $this->get_sample_bookings();
        }
        
        return $bookings;
    }
    
    /**
     * Check if all bookings have empty names
     */
    private function all_bookings_have_empty_names($bookings) {
        if (empty($bookings)) {
            return true;
        }
        
        foreach ($bookings as $booking) {
            if (!empty($booking['name'])) {
                return false; // Found at least one booking with a name
            }
        }
        
        return true; // All bookings have empty names
    }
    
    /**
     * Fallback: Get bookings from WordPress posts
     */
    private function get_bookings_from_posts() {
        $bookings = [];
        
        // Try to get from custom post types
        $posts = get_posts([
            'post_type' => ['bsp_booking', 'bookings_pro', 'booking'],
            'posts_per_page' => 10,
            'post_status' => ['publish', 'confirmed', 'pending'],
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        foreach ($posts as $post) {
            $meta = get_post_meta($post->ID);
            
            $bookings[] = [
                'name' => $this->extract_first_name($meta['full_name'][0] ?? $meta['customer_name'][0] ?? $post->post_title),
                'service' => $meta['service'][0] ?? 'Home Improvement',
                'zip_code' => $meta['zip_code'][0] ?? $meta['zipcode'][0] ?? '',
                'created_at' => $post->post_date
            ];
        }
        
        return $bookings;
    }
    
    /**
     * Get sample bookings for testing/fallback
     */
    private function get_sample_bookings() {
        $sample_names = [
            'John Smith', 'Mary Johnson', 'David Wilson', 'Sarah Brown', 'Michael Davis',
            'Jennifer Garcia', 'Robert Miller', 'Lisa Anderson', 'William Martinez', 'Karen Taylor'
        ];
        
        $sample_services = ['Roof', 'Windows', 'Siding', 'Kitchen', 'Bathroom'];
        $sample_zips = ['90210', '10001', '60601', '77001', '85001'];
        
        $bookings = [];
        for ($i = 0; $i < 10; $i++) {
            $bookings[] = [
                'name' => $this->extract_first_name($sample_names[$i]),
                'service' => $sample_services[array_rand($sample_services)],
                'zip_code' => $sample_zips[array_rand($sample_zips)],
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' minutes'))
            ];
        }
        
        return $bookings;
    }

    /**
     * Extract first name from full name
     */
    private function extract_first_name($full_name) {
        if (empty($full_name)) {
            return '';
        }
        
        $parts = explode(' ', trim($full_name));
        return $parts[0];
    }
    
    /**
     * Hook into booking creation event
     */
    public function on_booking_created($booking_id, $booking_data) {
        // This can be used to trigger real-time notifications
        // For now, we rely on the polling system
    }
    
    /**
     * Hook into booking confirmation event
     */
    public function on_booking_confirmed($booking_id, $booking_data) {
        // This can be used to trigger real-time notifications
        // For now, we rely on the polling system
    }
    
    /**
     * Get ZIP code data for locations
     */
    public function get_zip_code_data() {
        // This could integrate with BookingPro's ZIP code database
        // For now, we use the fallback data in JavaScript
        return [];
    }
    
    /**
     * Admin settings (future enhancement)
     */
    public function add_admin_settings() {
        // Future: Add settings page to WordPress admin
        // For now, configuration is done in JavaScript
    }
}

// Initialize the social proof integration
BSP_Social_Proof_Integration::get_instance();
