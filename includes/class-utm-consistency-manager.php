<?php
/**
 * UTM Consistency Manager for Lead Capture System
 * Phase 2A: Ensures consistent UTM tracking across all touchpoints
 */

if (!defined('ABSPATH')) exit;

class BSP_UTM_Consistency_Manager {
    
    private static $instance = null;
    private $utm_parameters = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'];
    private $utm_data_cache = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook into WordPress initialization
        add_action('init', [$this, 'capture_utm_parameters'], 5); // Early priority
        add_action('wp_enqueue_scripts', [$this, 'enqueue_utm_scripts'], 6); // After safe integration
        
        // AJAX endpoint for UTM synchronization
        add_action('wp_ajax_bsp_sync_utm_data', [$this, 'sync_utm_data']);
        add_action('wp_ajax_nopriv_bsp_sync_utm_data', [$this, 'sync_utm_data']);
        
        bsp_debug_log("UTM Consistency Manager initialized", 'UTM_MANAGER');
    }
    
    /**
     * Capture and normalize UTM parameters from multiple sources
     */
    public function capture_utm_parameters() {
        $utm_data = [];
        
        // 1. Get from URL parameters (highest priority)
        foreach ($this->utm_parameters as $param) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                $utm_data[$param] = sanitize_text_field($_GET[$param]);
            }
        }
        
        // 2. Get from existing cookies (medium priority)
        foreach ($this->utm_parameters as $param) {
            if (empty($utm_data[$param]) && isset($_COOKIE[$param]) && !empty($_COOKIE[$param])) {
                $utm_data[$param] = sanitize_text_field($_COOKIE[$param]);
            }
        }
        
        // 3. Get referrer information
        if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
            $utm_data['referrer'] = esc_url_raw($_SERVER['HTTP_REFERER']);
            
            // Parse referrer for additional UTM data if not already present
            $this->parse_referrer_utm($utm_data);
        }
        
        // 4. Detect traffic source if UTM data is missing
        if (empty($utm_data['utm_source'])) {
            $detected_source = $this->detect_traffic_source($utm_data['referrer'] ?? '');
            if ($detected_source) {
                $utm_data = array_merge($utm_data, $detected_source);
            }
        }
        
        // 5. Set cookies for persistence (30 days)
        if (!empty($utm_data)) {
            $this->set_utm_cookies($utm_data);
            $this->utm_data_cache = $utm_data;
            
            bsp_debug_log("UTM parameters captured and set", 'UTM_CAPTURE', [
                'utm_data' => $utm_data,
                'source' => 'multiple_sources'
            ]);
        }
    }
    
    /**
     * Parse UTM parameters from referrer URL
     */
    private function parse_referrer_utm(&$utm_data) {
        if (empty($utm_data['referrer'])) return;
        
        $parsed_url = parse_url($utm_data['referrer']);
        if (!isset($parsed_url['query'])) return;
        
        parse_str($parsed_url['query'], $query_params);
        
        foreach ($this->utm_parameters as $param) {
            if (empty($utm_data[$param]) && isset($query_params[$param])) {
                $utm_data[$param] = sanitize_text_field($query_params[$param]);
            }
        }
    }
    
    /**
     * Detect traffic source from referrer
     */
    private function detect_traffic_source($referrer) {
        if (empty($referrer)) {
            return [
                'utm_source' => 'direct',
                'utm_medium' => 'direct'
            ];
        }
        
        $domain = parse_url($referrer, PHP_URL_HOST);
        if (!$domain) return null;
        
        // Common search engines
        $search_engines = [
            'google.com' => 'google',
            'bing.com' => 'bing',
            'yahoo.com' => 'yahoo',
            'duckduckgo.com' => 'duckduckgo',
            'ask.com' => 'ask'
        ];
        
        foreach ($search_engines as $engine_domain => $engine_name) {
            if (strpos($domain, $engine_domain) !== false) {
                return [
                    'utm_source' => $engine_name,
                    'utm_medium' => 'organic'
                ];
            }
        }
        
        // Social media platforms
        $social_platforms = [
            'facebook.com' => 'facebook',
            'twitter.com' => 'twitter',
            'linkedin.com' => 'linkedin',
            'instagram.com' => 'instagram',
            'youtube.com' => 'youtube',
            'pinterest.com' => 'pinterest',
            'tiktok.com' => 'tiktok'
        ];
        
        foreach ($social_platforms as $social_domain => $social_name) {
            if (strpos($domain, $social_domain) !== false) {
                return [
                    'utm_source' => $social_name,
                    'utm_medium' => 'social'
                ];
            }
        }
        
        // Default for external referrals
        return [
            'utm_source' => $domain,
            'utm_medium' => 'referral'
        ];
    }
    
    /**
     * Set UTM cookies with proper security
     */
    private function set_utm_cookies($utm_data) {
        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
        $secure = is_ssl();
        $httponly = false; // Need JavaScript access
        $samesite = 'Lax';
        
        foreach ($utm_data as $key => $value) {
            if (in_array($key, $this->utm_parameters) || $key === 'referrer') {
                // Use setcookie with all security parameters
                if (PHP_VERSION_ID >= 70300) {
                    setcookie($key, $value, [
                        'expires' => $expiry,
                        'path' => '/',
                        'domain' => '',
                        'secure' => $secure,
                        'httponly' => $httponly,
                        'samesite' => $samesite
                    ]);
                } else {
                    // Fallback for older PHP versions
                    setcookie($key, $value, $expiry, '/', '', $secure, $httponly);
                }
            }
        }
    }
    
    /**
     * Enqueue UTM synchronization scripts
     */
    public function enqueue_utm_scripts() {
        // Only load on pages with booking forms
        if (!$this->is_booking_page()) {
            return;
        }
        
        wp_enqueue_script(
            'bsp-utm-manager',
            plugin_dir_url(__DIR__) . 'assets/js/utm-consistency.js',
            ['bsp-safe-integration'], // Depend on safe integration
            '1.0.0',
            true
        );
        
        // Pass UTM data to JavaScript
        wp_localize_script('bsp-utm-manager', 'bspUtmConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bsp_utm_sync_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'utmData' => $this->get_current_utm_data(),
            'syncInterval' => 30000, // 30 seconds
            'cookieExpiry' => 30 * 24 * 60 * 60 // 30 days in seconds
        ]);
        
        bsp_debug_log("UTM manager scripts enqueued", 'UTM_MANAGER', [
            'utm_data' => $this->get_current_utm_data()
        ]);
    }
    
    /**
     * Check if current page has booking forms
     */
    private function is_booking_page() {
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
     * Get current UTM data from all sources
     */
    public function get_current_utm_data() {
        if ($this->utm_data_cache !== null) {
            return $this->utm_data_cache;
        }
        
        $utm_data = [];
        
        // Get from cookies
        foreach ($this->utm_parameters as $param) {
            if (isset($_COOKIE[$param]) && !empty($_COOKIE[$param])) {
                $utm_data[$param] = sanitize_text_field($_COOKIE[$param]);
            }
        }
        
        // Add referrer
        if (isset($_COOKIE['referrer']) && !empty($_COOKIE['referrer'])) {
            $utm_data['referrer'] = esc_url_raw($_COOKIE['referrer']);
        }
        
        $this->utm_data_cache = $utm_data;
        return $utm_data;
    }
    
    /**
     * AJAX handler for UTM data synchronization
     */
    public function sync_utm_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_utm_sync_nonce')) {
            bsp_debug_log("UTM sync failed: Invalid nonce", 'UTM_ERROR');
            wp_send_json_error('Security check failed.');
        }
        
        $client_utm_data = isset($_POST['utm_data']) ? $_POST['utm_data'] : [];
        $server_utm_data = $this->get_current_utm_data();
        
        // Merge data with priority: client (form) > server (cookies)
        $merged_utm_data = array_merge($server_utm_data, array_filter($client_utm_data));
        
        // Update cookies with merged data
        if (!empty($merged_utm_data)) {
            $this->set_utm_cookies($merged_utm_data);
            $this->utm_data_cache = $merged_utm_data;
        }
        
        bsp_debug_log("UTM data synchronized", 'UTM_SYNC', [
            'client_data' => $client_utm_data,
            'server_data' => $server_utm_data,
            'merged_data' => $merged_utm_data
        ]);
        
        wp_send_json_success([
            'utm_data' => $merged_utm_data,
            'message' => 'UTM data synchronized'
        ]);
    }
    
    /**
     * Get UTM data for lead capture integration
     */
    public function get_utm_for_lead_capture() {
        $utm_data = $this->get_current_utm_data();
        
        // Add session information
        $utm_data['capture_timestamp'] = current_time('mysql');
        $utm_data['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $utm_data['ip_address'] = $this->get_client_ip();
        
        return $utm_data;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated list (proxy chains)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    
    /**
     * Validate UTM data consistency
     */
    public function validate_utm_consistency($form_utm_data) {
        $server_utm_data = $this->get_current_utm_data();
        $inconsistencies = [];
        
        foreach ($this->utm_parameters as $param) {
            $form_value = isset($form_utm_data[$param]) ? $form_utm_data[$param] : '';
            $server_value = isset($server_utm_data[$param]) ? $server_utm_data[$param] : '';
            
            if (!empty($form_value) && !empty($server_value) && $form_value !== $server_value) {
                $inconsistencies[$param] = [
                    'form' => $form_value,
                    'server' => $server_value
                ];
            }
        }
        
        if (!empty($inconsistencies)) {
            bsp_debug_log("UTM data inconsistencies detected", 'UTM_WARNING', [
                'inconsistencies' => $inconsistencies,
                'form_data' => $form_utm_data,
                'server_data' => $server_utm_data
            ]);
        }
        
        return [
            'consistent' => empty($inconsistencies),
            'inconsistencies' => $inconsistencies,
            'resolved_data' => array_merge($server_utm_data, array_filter($form_utm_data))
        ];
    }
    
    /**
     * Clear UTM data (for testing or privacy)
     */
    public function clear_utm_data() {
        foreach ($this->utm_parameters as $param) {
            setcookie($param, '', time() - 3600, '/');
        }
        setcookie('referrer', '', time() - 3600, '/');
        
        $this->utm_data_cache = null;
        
        bsp_debug_log("UTM data cleared", 'UTM_MANAGER');
    }
}

// Initialize the UTM Consistency Manager
BSP_UTM_Consistency_Manager::get_instance();
