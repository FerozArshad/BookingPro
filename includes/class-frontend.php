<?php
/**
 * Frontend Interface for Booking System Pro
 */

if (!defined('ABSPATH')) exit;

class BSP_Frontend {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("BSP_Frontend initialized", 'FRONTEND');
        }
        
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        // Register shortcodes immediately instead of waiting for init
        add_action('init', [$this, 'register_shortcodes'], 5); // Earlier priority
        add_filter('the_content', [$this, 'maybe_add_booking_form']);
        
        // Also register shortcodes immediately in constructor for immediate availability
        $this->register_shortcodes();
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only load on pages with booking forms or specific booking-related pages
        if (!$this->should_load_assets()) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'bsp-frontend',
            BSP_PLUGIN_URL . 'assets/css/booking-system.css',
            [],
            BSP_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'bsp-frontend',
            BSP_PLUGIN_URL . 'assets/js/booking-system.js',
            ['jquery', 'jquery-ui-datepicker', 'bsp-zipcode-lookup'],
            BSP_VERSION,
            true
        );
        
        // Enqueue ZIP code lookup service
        wp_enqueue_script(
            'bsp-zipcode-lookup',
            BSP_PLUGIN_URL . 'assets/js/zipcode-lookup.js',
            ['jquery'],
            BSP_VERSION,
            true
        );
        
        // Get companies data for the frontend
        $db = BSP_Database_Unified::get_instance();
        $companies_raw = $db->get_companies(['status' => 'active']);
        
        // Debug logging for company loading
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("Frontend companies loading", 'FRONTEND_COMPANIES', [
                'companies_count' => count($companies_raw),
                'company_names' => array_map(function($c) { return $c->name; }, $companies_raw),
                'company_statuses' => array_map(function($c) { return $c->status ?? 'unknown'; }, $companies_raw)
            ]);
        }
        
        // Format companies for JavaScript
        $companies = [];
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
        
        // Localize script with AJAX data
        wp_localize_script('bsp-frontend', 'BSP_Ajax', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bsp_frontend_nonce'),
            'companies' => $companies,
            'strings' => [
                'loading' => __('Loading...', 'booking-system-pro'),
                'error' => __('An error occurred. Please try again.', 'booking-system-pro'),
                'success' => __('Booking submitted successfully!', 'booking-system-pro'),
                'select_service' => __('Please select a service', 'booking-system-pro'),
                'select_time' => __('Please select a time slot', 'booking-system-pro'),
                'fill_required' => __('Please fill in all required fields', 'booking-system-pro')
            ]
        ]);
        
        // jQuery UI styles
        wp_enqueue_style('jquery-ui-datepicker');
    }
    
    /**
     * Optimized check for when to load assets
     */
    private function should_load_assets() {
        // Don't load on admin pages
        if (is_admin()) {
            return false;
        }
        
        // Check for booking-related content
        if ($this->has_booking_content()) {
            return true;
        }
        
        // Check for specific pages that might need booking functionality
        if (is_page(['booking', 'book-now', 'contact', 'estimate', 'appointment'])) {
            return true;
        }
        
        // Check if current page template suggests booking functionality
        $template = get_page_template_slug();
        if (in_array($template, ['booking-template.php', 'contact-template.php'])) {
            return true;
        }
        
        // Check URL parameters for booking-related content
        if (isset($_GET['service']) || isset($_GET['booking']) || 
            strpos($_SERVER['REQUEST_URI'] ?? '', '#roof') !== false ||
            strpos($_SERVER['REQUEST_URI'] ?? '', '#windows') !== false ||
            strpos($_SERVER['REQUEST_URI'] ?? '', '#bathroom') !== false ||
            strpos($_SERVER['REQUEST_URI'] ?? '', '#siding') !== false ||
            strpos($_SERVER['REQUEST_URI'] ?? '', '#kitchen') !== false ||
            strpos($_SERVER['REQUEST_URI'] ?? '', '#decks') !== false) {
            return true;
        }
        
        // Allow themes/plugins to force loading
        return apply_filters('bsp_should_load_frontend_assets', false);
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("Registering frontend shortcodes", 'FRONTEND');
        }
        
        add_shortcode('booking_form', [$this, 'booking_form_shortcode']);
        add_shortcode('booking_calendar', [$this, 'booking_calendar_shortcode']);
        add_shortcode('booking_services', [$this, 'booking_services_shortcode']);
        
        // Legacy compatibility
        add_shortcode('booking_system_form', [$this, 'booking_form_shortcode']);
        
        // Only log if in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('bsp_debug_log')) {
            bsp_debug_log("Frontend shortcodes registered", 'DEBUG');
        }
    }
    
    /**
     * Display booking form
     */
    
    /**
     * Booking form shortcode
     */
    public function booking_form_shortcode($atts) {
        // Debug at the very start
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("SHORTCODE EXECUTION STARTED", 'SHORTCODE', $atts);
        }
        
        try {
            $atts = shortcode_atts([
                'company_id' => 0,
                'service_id' => 0,
                'style' => 'default',
                'show_calendar' => 'true',
                'show_services' => 'true'
            ], $atts);

            if (function_exists('bsp_debug_log')) {
                bsp_debug_log("Processing booking form shortcode", 'SHORTCODE', $atts);
            }

            ob_start();
            $this->render_booking_form($atts);
            $output = ob_get_clean();

            return $output;        } catch (Exception $e) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log("SHORTCODE ERROR: " . $e->getMessage(), 'ERROR', [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
            return '<div class="bsp-error">Error loading booking form</div>';
        }
    }
    
    /**
     * Booking calendar shortcode
     */
    public function booking_calendar_shortcode($atts) {
        $atts = shortcode_atts([
            'company_id' => 0,
            'service_id' => 0,
            'month' => date('Y-m')
        ], $atts);
        
        ob_start();
        $this->render_booking_calendar($atts);
        return ob_get_clean();
    }
    
    /**
     * Booking services shortcode
     */
    public function booking_services_shortcode($atts) {
        $atts = shortcode_atts([
            'company_id' => 0,
            'layout' => 'grid',
            'columns' => 3
        ], $atts);
        
        ob_start();
        $this->render_services_list($atts);
        return ob_get_clean();
    }
    
    /**
     * Render booking form
     */
    private function render_booking_form($atts) {
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("RENDER METHOD STARTED", 'FRONTEND', $atts);
        }
        
        try {
            // Extract attributes
            $company_id = intval($atts['company_id']);
            $service_id = intval($atts['service_id']);

            // Include complete template
            $template_path = BSP_PLUGIN_DIR . 'templates/booking-form-complete.php';

            if (function_exists('bsp_debug_log')) {
                bsp_debug_log("Template path: " . $template_path, 'FRONTEND');
                bsp_debug_log("Template exists: " . (file_exists($template_path) ? 'Yes' : 'No'), 'FRONTEND');
            }

            if (file_exists($template_path)) {
                include $template_path;
                if (function_exists('bsp_debug_log')) {
                    bsp_debug_log("Template included successfully", 'FRONTEND');
                }
            } else {
                // Fallback: render basic container for JavaScript
                echo '<div id="booking-form" class="booking-system-form">';
                echo '<div class="bsp-loading">Loading booking form...</div>';
                echo '</div>';
                if (function_exists('bsp_debug_log')) {
                    bsp_debug_log("Used fallback form HTML", 'FRONTEND');
                }
            }
        } catch (Exception $e) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log("RENDER ERROR: " . $e->getMessage(), 'ERROR', [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
            echo '<div class="bsp-error">Error rendering booking form</div>';
        }
    }
    
    /**
     * Render booking calendar
     */
    private function render_booking_calendar($atts) {
        $db = BSP_Database_Unified::get_instance();
        $company_id = intval($atts['company_id']);
        $service_id = intval($atts['service_id']);
        
        // Get available dates
        $available_dates = $db->get_available_dates($company_id, $service_id);
        
        echo '<div class="bsp-calendar" data-company="' . esc_attr($company_id) . '" data-service="' . esc_attr($service_id) . '">';
        echo '<div id="bsp-datepicker"></div>';
        echo '<div class="bsp-time-slots"></div>';
        echo '</div>';
        
        // Add available dates to JavaScript
        echo '<script>var bspAvailableDates = ' . json_encode($available_dates) . ';</script>';
    }
    
    /**
     * Render services list
     */
    private function render_services_list($atts) {
        $db = BSP_Database_Unified::get_instance();
        $company_id = intval($atts['company_id']);
        
        $services = $db->get_services($company_id);
        
        echo '<div class="bsp-services-grid columns-' . esc_attr($atts['columns']) . '">';
        foreach ($services as $service) {
            echo '<div class="bsp-service-item">';
            echo '<h3>' . esc_html($service['name']) . '</h3>';
            echo '<p class="description">' . esc_html($service['description']) . '</p>';
            echo '<p class="price">$' . esc_html($service['price']) . '</p>';
            echo '<p class="duration">' . esc_html($service['duration']) . ' minutes</p>';
            echo '<button class="bsp-select-service" data-service="' . esc_attr($service['id']) . '">Select Service</button>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    /**
     * Check if current page/post has booking content
     */
    private function has_booking_content() {
        global $post;
        
        // Always load on admin pages for testing
        if (is_admin()) {
            return false;
        }
        
        // Check for shortcodes in content
        if ($post && $post->post_content) {
            $shortcodes = ['booking_form', 'booking_calendar', 'booking_services'];
            foreach ($shortcodes as $shortcode) {
                if (has_shortcode($post->post_content, $shortcode)) {
                    return true;
                }
            }
        }
        
        // Check for booking post type
        if ($post && get_post_type($post) === 'bsp_booking') {
            return true;
        }
        
        // Load on any page that might use the booking system (for testing)
        // This can be made more specific later
        if (is_page() || is_front_page()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Maybe add booking form to content
     */
    public function maybe_add_booking_form($content) {
        // Auto-add booking form to specific post types or pages
        // This can be customized based on requirements
        return $content;
    }
}
