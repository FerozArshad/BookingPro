<?php
/**
 * Lead Data Collector for Incomplete Form Submissions
 * Phase 1: Basic lead capture with safe variable integration
 */

if (!defined('ABSPATH')) exit;

class BSP_Lead_Data_Collector {
    
    private static $instance = null;
    private $safe_integration;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize safe integration first (with defensive check)
        if (class_exists('BSP_Safe_Variable_Integration')) {
            $this->safe_integration = BSP_Safe_Variable_Integration::get_instance();
        } else {
            $this->safe_debug_log("BSP_Safe_Variable_Integration not available, Lead Data Collector will use fallback methods", 'WARNING');
        }
        
        // Hook into WordPress
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 10);
        add_action('wp_ajax_bsp_capture_incomplete_lead', [$this, 'capture_incomplete_lead']);
        add_action('wp_ajax_nopriv_bsp_capture_incomplete_lead', [$this, 'capture_incomplete_lead']);
        
        // TEST ACTION - Remove after debugging
        add_action('wp_ajax_bsp_test_incomplete_lead', [$this, 'test_incomplete_lead_processing']);
        add_action('wp_ajax_nopriv_bsp_test_incomplete_lead', [$this, 'test_incomplete_lead_processing']);
        
        // Initialize database table if needed
        add_action('init', [$this, 'ensure_database_table']);
        
        $this->safe_debug_log("Lead Data Collector initialized", 'LEAD_COLLECTOR');
    }
    
    /**
     * Safe debug logging that doesn't break if bsp_debug_log isn't available
     */
    private function safe_debug_log($message, $type = 'INFO', $context = []) {
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log($message, $type, $context);
        } elseif (function_exists('error_log')) {
            $context_str = empty($context) ? '' : ' | ' . wp_json_encode($context);
            error_log("BSP [{$type}] {$message}{$context_str}");
        }
    }
    
    /**
     * Safe lead logging that doesn't break if bsp_lead_log isn't available
     */
    private function safe_lead_log($message, $lead_data = [], $type = 'LEAD_CAPTURE') {
        if (function_exists('bsp_lead_log')) {
            bsp_lead_log($message, $lead_data, $type);
        } else {
            $this->safe_debug_log("Lead Capture: {$message}", $type, $lead_data);
        }
    }
    
    /**
     * Ensure the incomplete_leads table exists
     */
    public function ensure_database_table() {
        // Initialize unified database system
        BSP_Database_Unified::init_tables();
        
        // Verify table exists
        global $wpdb;
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if (!$table_exists) {
            // Trigger table creation
            $db_instance = BSP_Database_Unified::get_instance();
            $db_instance->create_tables();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("BSP Lead Collector: Created incomplete_leads table");
            }
        }
    }
    
    /**
     * Enqueue lead capture scripts
     */
    public function enqueue_scripts() {
        // Only load on booking pages
        if (!$this->is_booking_page()) {
            bsp_debug_log("Lead capture scripts NOT enqueued - not a booking page", 'LEAD_CAPTURE_ENQUEUE');
            return;
        }
        
        bsp_debug_log("Enqueuing lead capture scripts", 'LEAD_CAPTURE_ENQUEUE');
        
        // Enqueue lead capture script after safe integration
        wp_enqueue_script(
            'bsp-lead-capture',
            plugin_dir_url(__DIR__) . 'assets/js/lead-capture.js',
            ['bsp-safe-integration', 'bsp-utm-manager'], // Depend on both safe integration and UTM manager
            '1.0.0',
            true // Load in footer after form initialization
        );

        // Pass configuration to JavaScript
        wp_localize_script('bsp-lead-capture', 'bspLeadConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bsp_lead_capture_nonce'),
            'debug' => true, // Enable debug for testing
            'captureDelay' => 1000, // Shorter delay for testing
            'minFieldsRequired' => 0, // Temporarily set to 0 for testing
            'storageKey' => 'bsp_incomplete_lead_' . get_current_blog_id(),
            'sessionExpiry' => 24 * 60 * 60 * 1000, // 24 hours in milliseconds
            'utmParams' => $this->get_utm_parameters_enhanced()
        ]);
        
        bsp_debug_log("Lead capture scripts enqueued successfully", 'LEAD_CAPTURE_ENQUEUE');
    }    /**
     * Check if current page has booking forms
     */
    private function is_booking_page() {
        // Don't load on admin pages
        if (is_admin()) {
            bsp_debug_log("Not a booking page - is admin", 'BOOKING_PAGE_CHECK');
            return false;
        }

        global $post;
        
        bsp_debug_log("Checking if booking page", 'BOOKING_PAGE_CHECK', [
            'post_exists' => $post ? 'yes' : 'no',
            'post_content_length' => $post ? strlen($post->post_content) : 0
        ]);
        
        // Check for shortcode in post content
        if ($post && (has_shortcode($post->post_content, 'booking_form') ||
                      has_shortcode($post->post_content, 'booking_system_form') ||
                      has_shortcode($post->post_content, 'booking_system_pro'))) {
            bsp_debug_log("Is booking page - shortcode found", 'BOOKING_PAGE_CHECK', [
                'has_booking_form' => has_shortcode($post->post_content, 'booking_form') ? 'yes' : 'no',
                'has_booking_system_form' => has_shortcode($post->post_content, 'booking_system_form') ? 'yes' : 'no',
                'has_booking_system_pro' => has_shortcode($post->post_content, 'booking_system_pro') ? 'yes' : 'no'
            ]);
            return true;
        }
        
        // Check for booking-related content patterns (more comprehensive)
        if ($this->has_booking_content()) {
            return true;
        }
        
        // Check for specific pages that might need booking functionality
        if (is_page(['booking', 'book-now', 'contact', 'estimate', 'appointment', 'quote', 'schedule'])) {
            return true;
        }
        
        // Check if current page template suggests booking functionality
        $template = get_page_template_slug();
        if (in_array($template, ['booking-template.php', 'contact-template.php'])) {
            return true;
        }
        
        // Check URL parameters for booking-related content
        if (isset($_GET['booking']) || isset($_GET['quote']) || isset($_GET['estimate'])) {
            return true;
        }
        
        // Check post slug/URL patterns
        if ($post) {
            $booking_patterns = ['booking', 'quote', 'estimate', 'schedule', 'appointment', 'contact', 'service'];
            foreach ($booking_patterns as $pattern) {
                if (strpos($post->post_name, $pattern) !== false || 
                    strpos(get_permalink(), $pattern) !== false) {
                    return true;
                }
            }
        }
        
        // Allow on front page for businesses that have booking forms there
        if (is_front_page() || is_home()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if the current page has booking-related content
     */
    private function has_booking_content() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        $content = $post->post_content;
        
        // Check for booking-related shortcodes
        $booking_shortcodes = [
            'booking_system_pro',
            'booking_form', 
            'contact_form',
            'estimate_form',
            'quote_form'
        ];
        
        foreach ($booking_shortcodes as $shortcode) {
            if (has_shortcode($content, $shortcode)) {
                return true;
            }
        }
        
        // Check for booking-related keywords in content
        $booking_keywords = [
            'booking', 'book now', 'schedule', 'appointment', 
            'estimate', 'quote', 'consultation', 'contact form'
        ];
        
        foreach ($booking_keywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get UTM parameters with enhanced consistency (Phase 2)
     */
    private function get_utm_parameters_enhanced() {
        // Get UTM Consistency Manager instance
        $utm_manager = BSP_UTM_Consistency_Manager::get_instance();
        
        // Get consistent UTM data
        $utm_data = $utm_manager->get_utm_for_lead_capture();
        
        $this->safe_lead_log("UTM parameters retrieved for lead capture", $utm_data, 'UTM_INTEGRATION');
        
        return $utm_data;
    }
    
    /**
     * Get UTM parameters from cookies (legacy method - kept for fallback)
     */
    private function get_utm_parameters() {
        $utm_params = [];
        $utm_keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'];
        
        foreach ($utm_keys as $key) {
            if (isset($_COOKIE[$key])) {
                $utm_params[$key] = sanitize_text_field($_COOKIE[$key]);
            }
        }
        
        // Add referrer if available
        if (isset($_SERVER['HTTP_REFERER'])) {
            $utm_params['referrer'] = esc_url_raw($_SERVER['HTTP_REFERER']);
        }
        
        return $utm_params;
    }
    
    /**
     * Handle AJAX request to capture incomplete lead data
     */
    public function capture_incomplete_lead() {
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $trigger_type = sanitize_text_field($_POST['trigger'] ?? 'form_submit');
        
        // REQUEST DEDUPLICATION: Prevent simultaneous requests for same session
        $request_key = "bsp_processing_" . $session_id;
        $current_time = time();
        
        // Check if same session is already being processed (within 5 seconds)
        $existing_processing = get_transient($request_key);
        if ($existing_processing && ($current_time - $existing_processing) < 5) {
            bsp_debug_log("DUPLICATE REQUEST BLOCKED", 'REQUEST_DEDUP', [
                'session_id' => $session_id,
                'trigger' => $trigger_type,
                'existing_processing_time' => $existing_processing,
                'current_time' => $current_time,
                'time_diff' => $current_time - $existing_processing
            ]);
            
            // For beforeunload triggers, silently succeed to prevent browser errors
            if ($trigger_type === 'beforeunload') {
                wp_send_json_success(['message' => 'Request deduped - beforeunload ignored']);
            } else {
                wp_send_json_error('Duplicate request - already processing');
            }
            return;
        }
        
        // Set processing flag for this session
        set_transient($request_key, $current_time, 10); // 10 second timeout
        
        // Log all incoming requests for debugging
        bsp_debug_log("AJAX capture request received", 'AJAX_CAPTURE', [
            'session_id' => $session_id,
            'trigger' => $trigger_type,
            'post_data' => $_POST,
            'has_nonce' => isset($_POST['nonce']),
            'action' => $_POST['action'] ?? 'missing'
        ]);
        
        // Verify nonce - accept both lead capture nonce and general frontend nonce
        $nonce_valid = wp_verify_nonce($_POST['nonce'], 'bsp_lead_capture_nonce') || 
                      wp_verify_nonce($_POST['nonce'], 'bsp_frontend_nonce');
        
        if (!$nonce_valid) {
            $this->safe_lead_log("Lead capture failed: Invalid nonce", [], 'SECURITY_ERROR');
            delete_transient($request_key); // Cleanup processing flag
            wp_send_json_error('Security check failed.');
        }
        
        // Sanitize and validate data
        $lead_data = $this->sanitize_lead_data($_POST);
        
        // Log raw and sanitized data to debug missing fields
        bsp_debug_log("Lead capture data analysis", 'LEAD_DATA_DEBUG', [
            'raw_post_keys' => array_keys($_POST),
            'sanitized_keys' => array_keys($lead_data),
            'service' => $_POST['service'] ?? 'not_in_post',
            'service_sanitized' => $lead_data['service'] ?? 'not_sanitized',
            'adu_fields_raw' => [
                'adu_action' => $_POST['adu_action'] ?? 'not_in_post',
                'adu_type' => $_POST['adu_type'] ?? 'not_in_post'
            ],
            'adu_fields_sanitized' => [
                'adu_action' => $lead_data['adu_action'] ?? 'not_sanitized',
                'adu_type' => $lead_data['adu_type'] ?? 'not_sanitized'
            ],
            'location_fields_raw' => [
                'city' => $_POST['city'] ?? 'not_in_post',
                'state' => $_POST['state'] ?? 'not_in_post',
                'company' => $_POST['company'] ?? 'not_in_post'
            ],
            'location_fields_sanitized' => [
                'city' => $lead_data['city'] ?? 'not_sanitized',
                'state' => $lead_data['state'] ?? 'not_sanitized', 
                'company' => $lead_data['company'] ?? 'not_sanitized'
            ]
        ]);
        
        // Validate minimum required data
        if (!$this->validate_minimum_data($lead_data)) {
            $this->safe_lead_log("Lead validation failed - insufficient data", [
                'session_id' => $lead_data['session_id'] ?? 'unknown',
                'service' => $lead_data['service'] ?? 'none',
                'has_contact_info' => !empty($lead_data['full_name']) || !empty($lead_data['email']) || !empty($lead_data['phone'])
            ], 'VALIDATION_ERROR');
            delete_transient($request_key); // Cleanup processing flag
            wp_send_json_error('Insufficient data for lead capture.');
        }

        // FAST PATH: Save to database quickly without heavy processing
        $lead_id = $this->save_incomplete_lead_fast($lead_data);
        
        if ($lead_id) {
            bsp_debug_log("Lead saved to database", 'DATABASE_SAVE', [
                'lead_id' => $lead_id,
                'session_id' => $lead_data['session_id'] ?? 'unknown'
            ]);
            
            // BACKGROUND PROCESSING - Schedule heavy operations BEFORE sending response
            $this->process_lead_background($lead_id, $lead_data);
            
            bsp_debug_log("Background processing scheduled", 'BACKGROUND_SCHEDULED', [
                'lead_id' => $lead_id,
                'session_id' => $lead_data['session_id'] ?? 'unknown'
            ]);
            
            // IMMEDIATE RESPONSE - Send success to user (this terminates execution)
            delete_transient($request_key); // Cleanup processing flag
            wp_send_json_success([
                'lead_id' => $lead_id,
                'message' => 'Lead data captured successfully'
            ]);
            
        } else {
            $this->safe_lead_log("Database save failed for lead", [
                'session_id' => $lead_data['session_id'] ?? 'unknown',
                'service' => $lead_data['service'] ?? ''
            ], 'DATABASE_ERROR');
            delete_transient($request_key); // Cleanup processing flag
            wp_send_json_error('Failed to save lead data.');
        }
    }
    
    /**
     * Sanitize incoming lead data
     */
    private function sanitize_lead_data($raw_data) {
        $sanitized = [];
        
        // Basic contact fields with form field name variations
        $text_fields = [
            'service', 'service_type', 
            'full_name', 'customer_name', 
            'email', 'customer_email', 'email_address',
            'phone', 'customer_phone', 'phone_number',
            'address', 'customer_address', 'street_address',
            'city', 'state', 
            'zip_code', 'bathroom_zip', 'zip',
            'company'
        ];
        foreach ($text_fields as $field) {
            if (isset($raw_data[$field]) && !empty($raw_data[$field])) {
                $sanitized[$field] = sanitize_text_field($raw_data[$field]);
            }
        }

        // CRITICAL FIX: Extract service from URL if not directly provided
        if (empty($sanitized['service'])) {
            $service_from_url = $this->extract_service_from_request($raw_data);
            if ($service_from_url) {
                $sanitized['service'] = $service_from_url;
                bsp_debug_log("Service extracted from URL parameters", 'SERVICE_EXTRACTION', [
                    'extracted_service' => $service_from_url,
                    'referrer' => $raw_data['referrer'] ?? '',
                    'extraction_method' => 'url_parameter_fallback'
                ]);
            }
        }

        // Map alternate field names to standard ones
        if (empty($sanitized['service']) && !empty($sanitized['service_type'])) {
            $sanitized['service'] = $sanitized['service_type'];
        }
        if (empty($sanitized['full_name']) && !empty($sanitized['customer_name'])) {
            $sanitized['full_name'] = $sanitized['customer_name'];
        }
        if (empty($sanitized['email']) && !empty($sanitized['customer_email'])) {
            $sanitized['email'] = $sanitized['customer_email'];
        }
        if (empty($sanitized['email']) && !empty($sanitized['email_address'])) {
            $sanitized['email'] = $sanitized['email_address'];
        }
        if (empty($sanitized['phone']) && !empty($sanitized['customer_phone'])) {
            $sanitized['phone'] = $sanitized['customer_phone'];
        }
        if (empty($sanitized['phone']) && !empty($sanitized['phone_number'])) {
            $sanitized['phone'] = $sanitized['phone_number'];
        }
        if (empty($sanitized['address']) && !empty($sanitized['customer_address'])) {
            $sanitized['address'] = $sanitized['customer_address'];
        }
        if (empty($sanitized['address']) && !empty($sanitized['street_address'])) {
            $sanitized['address'] = $sanitized['street_address'];
        }
        if (empty($sanitized['zip_code']) && !empty($sanitized['bathroom_zip'])) {
            $sanitized['zip_code'] = $sanitized['bathroom_zip'];
        }
        
        // Service-specific fields
        $service_fields = [
            'roof_action', 'roof_material',
            'windows_action', 'windows_replace_qty', 'windows_repair_needed',
            'bathroom_option',
            'siding_option', 'siding_material',
            'kitchen_action', 'kitchen_component',
            'decks_action', 'decks_material',
            'adu_action', 'adu_type'
        ];
        
        foreach ($service_fields as $field) {
            if (isset($raw_data[$field]) && !empty($raw_data[$field])) {
                $sanitized[$field] = sanitize_text_field($raw_data[$field]);
            }
        }
        
        // CRITICAL FIX: Handle appointment data for incomplete leads on confirmation page
        bsp_debug_log("Processing appointment data in sanitize_lead_data", 'APPOINTMENTS_DEBUG', [
            'has_appointments_key' => isset($raw_data['appointments']),
            'appointments_value' => $raw_data['appointments'] ?? 'NOT_SET',
            'appointments_type' => gettype($raw_data['appointments'] ?? null),
            'appointments_empty' => empty($raw_data['appointments']),
            'all_raw_keys' => array_keys($raw_data),
            'appointment_related_keys' => array_intersect_key($raw_data, array_flip([
                'appointments', 'company', 'booking_date', 'booking_time', 
                'selected_date', 'selected_time', 'date', 'time'
            ]))
        ]);
        
        if (isset($raw_data['appointments']) && !empty($raw_data['appointments'])) {
            bsp_debug_log("Found appointments data", 'APPOINTMENTS_FOUND', [
                'appointments_raw' => $raw_data['appointments']
            ]);
            
            // Handle both JSON string and array formats
            if (is_string($raw_data['appointments'])) {
                $appointments = json_decode(stripslashes($raw_data['appointments']), true);
                bsp_debug_log("Decoded appointments from JSON string", 'APPOINTMENTS_DECODED', [
                    'json_string' => $raw_data['appointments'],
                    'decoded_result' => $appointments,
                    'json_error' => json_last_error_msg()
                ]);
            } else {
                $appointments = $raw_data['appointments'];
                bsp_debug_log("Using appointments as array", 'APPOINTMENTS_ARRAY', [
                    'appointments' => $appointments
                ]);
            }
            
            if (is_array($appointments) && !empty($appointments)) {
                $sanitized['appointments'] = wp_json_encode($appointments);
                
                // Extract company, date, time data from appointments for compatibility
                $companies = [];
                $dates = [];
                $times = [];
                
                foreach ($appointments as $apt) {
                    if (isset($apt['company'])) $companies[] = sanitize_text_field($apt['company']);
                    if (isset($apt['date'])) $dates[] = sanitize_text_field($apt['date']);
                    if (isset($apt['time'])) $times[] = sanitize_text_field($apt['time']);
                }
                
                // Store comma-separated values for Google Sheets compatibility
                if (!empty($companies)) {
                    $sanitized['company'] = implode(', ', array_unique($companies));
                }
                if (!empty($dates)) {
                    $sanitized['booking_date'] = implode(', ', array_unique($dates));
                    $sanitized['selected_date'] = $dates[0]; // Primary date for compatibility
                    $sanitized['date'] = $dates[0]; // CRITICAL: Also set 'date' field for Google Sheets
                }
                if (!empty($times)) {
                    $sanitized['booking_time'] = implode(', ', array_unique($times));
                    $sanitized['selected_time'] = $times[0]; // Primary time for compatibility
                    $sanitized['time'] = $times[0]; // CRITICAL: Also set 'time' field for Google Sheets
                }
                
                bsp_debug_log("Appointment data extracted for incomplete lead", 'LEAD_APPOINTMENTS', [
                    'appointments_count' => count($appointments),
                    'companies' => $companies,
                    'dates' => $dates,
                    'times' => $times,
                    'sanitized_fields_set' => [
                        'company' => $sanitized['company'] ?? 'NOT_SET',
                        'booking_date' => $sanitized['booking_date'] ?? 'NOT_SET',
                        'booking_time' => $sanitized['booking_time'] ?? 'NOT_SET',
                        'selected_date' => $sanitized['selected_date'] ?? 'NOT_SET',
                        'selected_time' => $sanitized['selected_time'] ?? 'NOT_SET',
                        'date' => $sanitized['date'] ?? 'NOT_SET',
                        'time' => $sanitized['time'] ?? 'NOT_SET'
                    ]
                ]);
            } else {
                bsp_debug_log("Appointments data is not valid array", 'APPOINTMENTS_INVALID', [
                    'appointments' => $appointments,
                    'is_array' => is_array($appointments),
                    'is_empty' => empty($appointments)
                ]);
            }
        } else {
            bsp_debug_log("No appointments data found in raw data", 'APPOINTMENTS_MISSING', [
                'has_appointments_key' => isset($raw_data['appointments']),
                'appointments_value' => $raw_data['appointments'] ?? 'KEY_NOT_SET',
                'is_empty' => isset($raw_data['appointments']) ? empty($raw_data['appointments']) : 'KEY_NOT_SET'
            ]);
        }
        
        // Handle individual appointment data (fallback and direct field mapping)
        $appointment_fields = [
            'company', 'selected_date', 'selected_time', 
            'booking_date', 'booking_time',
            'company_name', 'appointment_date', 'appointment_time'
        ];
        foreach ($appointment_fields as $field) {
            if (isset($raw_data[$field]) && !empty($raw_data[$field]) && !isset($sanitized[$field])) {
                $sanitized[$field] = sanitize_text_field($raw_data[$field]);
            }
        }
        
        // Additional mapping for appointment data consistency
        if (!isset($sanitized['company']) && !empty($sanitized['company_name'])) {
            $sanitized['company'] = $sanitized['company_name'];
        }
        if (!isset($sanitized['booking_date']) && !empty($sanitized['selected_date'])) {
            $sanitized['booking_date'] = $sanitized['selected_date'];
        }
        if (!isset($sanitized['booking_time']) && !empty($sanitized['selected_time'])) {
            $sanitized['booking_time'] = $sanitized['selected_time'];
        }
        
        // UTM/Marketing data
        $utm_fields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'referrer'];
        foreach ($utm_fields as $field) {
            if (isset($raw_data[$field]) && !empty($raw_data[$field])) {
                $sanitized[$field] = sanitize_text_field($raw_data[$field]);
            }
        }
        
        // Meta information
        $sanitized['capture_timestamp'] = current_time('mysql');
        $sanitized['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $sanitized['ip_address'] = $this->get_client_ip();
        $sanitized['session_id'] = isset($raw_data['session_id']) ? sanitize_text_field($raw_data['session_id']) : '';
        $sanitized['form_step'] = isset($raw_data['form_step']) ? intval($raw_data['form_step']) : 0;
        
        return $sanitized;
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
     * Validate minimum data requirements for incomplete leads - ULTRA RELAXED for maximum capture
     */
    /**
     * Extract service from URL parameters or referrer
     */
    private function extract_service_from_request($raw_data) {
        // Method 1: Check URL parameters in referrer
        if (!empty($raw_data['referrer'])) {
            $parsed_url = parse_url($raw_data['referrer']);
            if (isset($parsed_url['query'])) {
                parse_str($parsed_url['query'], $params);
                if (!empty($params['service'])) {
                    return ucfirst(strtolower($params['service']));
                }
            }
            
            // Method 2: Check URL path for service names
            if (isset($parsed_url['path'])) {
                $path = strtolower($parsed_url['path']);
                $services = ['roof', 'windows', 'bathroom', 'kitchen', 'siding', 'decks', 'adu'];
                foreach ($services as $service) {
                    if (strpos($path, $service) !== false) {
                        return ucfirst($service);
                    }
                }
            }
        }
        
        // Method 3: Check current $_GET parameters
        if (!empty($_GET['service'])) {
            return ucfirst(strtolower($_GET['service']));
        }
        
        // Method 4: Check current URL path
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if ($current_url) {
            $services = ['roof', 'windows', 'bathroom', 'kitchen', 'siding', 'decks', 'adu'];
            foreach ($services as $service) {
                if (strpos(strtolower($current_url), $service) !== false) {
                    return ucfirst($service);
                }
            }
        }
        
        return null;
    }

    private function validate_minimum_data($lead_data) {
        // Use field mapper for consistent validation
        $mapped_data = BSP_Field_Mapper::map_form_data($lead_data);
        
        // Use field mapper's validation logic
        $is_valid = BSP_Field_Mapper::is_valid_lead_data($mapped_data);
        
        if ($is_valid) {
            $completion_percentage = BSP_Field_Mapper::calculate_completion_percentage($mapped_data);
            bsp_debug_log("Lead validation successful via field mapper", 'VALIDATION_SUCCESS', [
                'completion_percentage' => $completion_percentage,
                'has_contact_info' => !empty($mapped_data['customer_email']) || !empty($mapped_data['customer_phone']),
                'has_service' => !empty($mapped_data['service_type']),
                'has_location' => !empty($mapped_data['zip_code']),
                'has_utm' => !empty($mapped_data['utm_source']),
                'validation_type' => 'field_mapper_validation'
            ]);
            return true;
        }
        
        // Log rejection details for debugging
        bsp_debug_log("Lead validation failed via field mapper", 'VALIDATION_FAILED', [
            'mapped_fields' => array_keys(array_filter($mapped_data)),
            'validation_type' => 'field_mapper_rejection'
        ]);
        
        return false;
    }
    
    /**
     * Fast save - minimal database operation for immediate response
     */
    private function save_incomplete_lead_fast($lead_data) {
        global $wpdb;
        
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        bsp_debug_log("Attempting fast save to database", 'DATABASE_SAVE_ATTEMPT', [
            'table_name' => $table_name,
            'session_id' => $lead_data['session_id'] ?? 'unknown',
            'service' => $lead_data['service'] ?? 'unknown'
        ]);
        
        // Minimal data for fast save - INCLUDE APPOINTMENT DATA
        $db_data = [
            'session_id' => $lead_data['session_id'] ?? wp_generate_uuid4(),
            'service' => $lead_data['service'] ?? '',
            'zip_code' => $lead_data['zip_code'] ?? '',
            'customer_name' => $lead_data['full_name'] ?? '',
            'customer_email' => $lead_data['email'] ?? '',
            'customer_phone' => $lead_data['phone'] ?? '',
            'customer_address' => $lead_data['address'] ?? '',
            'city' => $lead_data['city'] ?? '',
            'state' => $lead_data['state'] ?? '',
            'lead_type' => 'Processing',
            'created_at' => current_time('mysql'),
            'last_updated' => current_time('mysql'),
            // CRITICAL FIX: Include appointment data in fast save
            'form_data' => json_encode($lead_data) // This preserves ALL sanitized data including appointments
        ];
        
        bsp_debug_log("Database data prepared", 'DATABASE_DATA_PREPARED', [
            'db_data' => $db_data,
            'appointment_data_included' => [
                'has_form_data' => isset($db_data['form_data']),
                'form_data_length' => isset($db_data['form_data']) ? strlen($db_data['form_data']) : 0,
                'original_appointment_fields' => [
                    'appointments' => isset($lead_data['appointments']),
                    'company' => isset($lead_data['company']),
                    'date' => isset($lead_data['date']),
                    'time' => isset($lead_data['time'])
                ]
            ]
        ]);
        
        // Quick insert/update without heavy processing
        $existing_lead = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE session_id = %s AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $db_data['session_id']
        ));
        
        if ($existing_lead) {
            bsp_debug_log("Updating existing lead", 'DATABASE_UPDATE', ['existing_lead_id' => $existing_lead]);
            unset($db_data['created_at']);
            $result = $wpdb->update($table_name, $db_data, ['id' => $existing_lead]);
            $lead_id = $result !== false ? $existing_lead : false;
        } else {
            bsp_debug_log("Inserting new lead", 'DATABASE_INSERT');
            $result = $wpdb->insert($table_name, $db_data);
            $lead_id = $result ? $wpdb->insert_id : false;
        }
        
        if ($lead_id) {
            bsp_debug_log("Database save successful", 'DATABASE_SAVE_SUCCESS', ['lead_id' => $lead_id]);
        } else {
            bsp_debug_log("Database save failed", 'DATABASE_SAVE_FAILED', ['error' => $wpdb->last_error]);
        }
        
        return $lead_id;
    }
    
    /**
     * Process lead data in background after immediate response sent
     */
    private function process_lead_background($lead_id, $lead_data) {
        // Schedule the heavy processing using existing WordPress cron system
        if (function_exists('wp_schedule_single_event')) {
            wp_schedule_single_event(time() + 2, 'bsp_process_incomplete_lead_background', [$lead_id, $lead_data]);
            
            $this->safe_lead_log("Scheduled background processing for lead", [
                'lead_id' => $lead_id,
                'session_id' => $lead_data['session_id'] ?? 'unknown'
            ], 'BACKGROUND_SCHEDULED');
        } else {
            // Fallback - do it immediately if cron not available
            $this->complete_lead_processing($lead_id, $lead_data);
        }
    }
    
    /**
     * Complete the full lead processing (called by cron)
     */
    public function complete_lead_processing($lead_id, $lead_data) {
        global $wpdb;
        
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        // CRITICAL: Check if lead has already been converted to booking
        $existing_lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $lead_id
        ));
        
        if ($existing_lead && !empty($existing_lead->booking_post_id)) {
            $this->safe_lead_log("SKIPPING: Lead already converted to booking", [
                'lead_id' => $lead_id,
                'booking_post_id' => $existing_lead->booking_post_id,
                'session_id' => $lead_data['session_id'] ?? 'unknown'
            ], 'PROCESSING_SKIP_CONVERTED');
            return;
        }
        
        // CRITICAL FIX: Merge appointment data from stored form_data
        $stored_form_data = [];
        if ($existing_lead && !empty($existing_lead->form_data)) {
            $stored_form_data = json_decode($existing_lead->form_data, true) ?? [];
            bsp_debug_log("Retrieved stored form data from database", 'BACKGROUND_FORM_DATA', [
                'lead_id' => $lead_id,
                'stored_keys' => array_keys($stored_form_data),
                'has_appointments' => isset($stored_form_data['appointments']),
                'has_date' => isset($stored_form_data['date']),
                'has_time' => isset($stored_form_data['time'])
            ]);
        }
        
        // Merge the stored appointment data with current lead data
        $complete_lead_data = array_merge($lead_data, $stored_form_data);
        
        bsp_debug_log("Merged lead data for background processing", 'BACKGROUND_MERGE', [
            'original_lead_keys' => array_keys($lead_data),
            'stored_form_keys' => array_keys($stored_form_data),
            'merged_keys' => array_keys($complete_lead_data),
            'appointment_fields_present' => [
                'appointments' => isset($complete_lead_data['appointments']),
                'company' => isset($complete_lead_data['company']),
                'date' => isset($complete_lead_data['date']),
                'time' => isset($complete_lead_data['time']),
                'booking_date' => isset($complete_lead_data['booking_date']),
                'booking_time' => isset($complete_lead_data['booking_time'])
            ]
        ]);
        
        // Full data update with all fields and processing - USE MERGED DATA
        $full_db_data = [
            'completion_percentage' => $complete_lead_data['completion_percentage'] ?? 0,
            'lead_type' => $this->determine_lead_type($complete_lead_data),
            'utm_source' => $complete_lead_data['utm_source'] ?? '',
            'utm_medium' => $complete_lead_data['utm_medium'] ?? '',
            'utm_campaign' => $complete_lead_data['utm_campaign'] ?? '',
            'utm_term' => $complete_lead_data['utm_term'] ?? '',
            'utm_content' => $complete_lead_data['utm_content'] ?? '',
            'gclid' => $complete_lead_data['gclid'] ?? '',
            'referrer' => $complete_lead_data['referrer'] ?? '',
            'form_data' => json_encode($this->compile_form_data($complete_lead_data)),
            'last_updated' => current_time('mysql'),
            'send_trigger' => $complete_lead_data['trigger'] ?? 'form_interaction'
        ];
        
        // Update with full data
        $result = $wpdb->update(
            $table_name,
            $full_db_data,
            ['id' => $lead_id],
            $this->get_db_formats($full_db_data),
            ['%d']
        );
        
        if ($result !== false) {
            $this->safe_lead_log("Lead background processing completed", [
                'lead_id' => $lead_id,
                'session_id' => $complete_lead_data['session_id'] ?? 'unknown'
            ], 'BACKGROUND_COMPLETE');
            
            // Trigger Google Sheets sync with COMPLETE data including appointments
            do_action('bsp_incomplete_lead_captured', $lead_id, array_merge($complete_lead_data, $full_db_data));
        } else {
            $this->safe_lead_log("Background processing failed for lead", [
                'lead_id' => $lead_id,
                'error' => $wpdb->last_error
            ], 'BACKGROUND_ERROR');
        }
    }

    /**
     * Save incomplete lead to database
     */
    private function save_incomplete_lead($lead_data) {
        global $wpdb;
        
        // Use field mapper for consistent data handling
        $mapped_data = BSP_Field_Mapper::map_form_data($lead_data);
        
        // CRITICAL FIX: Extract service from URL if not in form data
        if (empty($mapped_data['service_type'])) {
            // Check referrer URL first
            if (!empty($_SERVER['HTTP_REFERER'])) {
                $referrer = $_SERVER['HTTP_REFERER'];
                if (preg_match('/[?&]service=([^&]+)/', $referrer, $matches)) {
                    $service_from_url = urldecode($matches[1]);
                    $mapped_data['service_type'] = ucfirst(strtolower($service_from_url));
                    bsp_debug_log("Service extracted from referrer URL", 'SERVICE_EXTRACTION', [
                        'referrer' => $referrer,
                        'extracted_service' => $mapped_data['service_type']
                    ]);
                }
            }
            
            // Check current REQUEST_URI
            if (empty($mapped_data['service_type']) && !empty($_SERVER['REQUEST_URI'])) {
                $request_uri = $_SERVER['REQUEST_URI'];
                if (preg_match('/[?&]service=([^&]+)/', $request_uri, $matches)) {
                    $service_from_url = urldecode($matches[1]);
                    $mapped_data['service_type'] = ucfirst(strtolower($service_from_url));
                    bsp_debug_log("Service extracted from request URI", 'SERVICE_EXTRACTION', [
                        'request_uri' => $request_uri,
                        'extracted_service' => $mapped_data['service_type']
                    ]);
                }
            }
            
            // Check WordPress query vars
            if (empty($mapped_data['service_type'])) {
                $service_from_query = get_query_var('service');
                if (!empty($service_from_query)) {
                    $mapped_data['service_type'] = ucfirst(strtolower($service_from_query));
                    bsp_debug_log("Service extracted from WordPress query var", 'SERVICE_EXTRACTION', [
                        'query_var' => $service_from_query,
                        'extracted_service' => $mapped_data['service_type']
                    ]);
                }
            }
        }
        
        // Also check if service is in session data
        if (empty($mapped_data['service_type']) && !empty($mapped_data['session_id'])) {
            // Try to get service from existing lead data in database
            global $wpdb;
            $existing_lead = $this->get_incomplete_lead($mapped_data['session_id']);
            if ($existing_lead && !empty($existing_lead->service)) {
                $mapped_data['service_type'] = $existing_lead->service;
                bsp_debug_log("Service retrieved from existing lead", 'SERVICE_RETRIEVAL', [
                    'session_id' => $mapped_data['session_id'],
                    'retrieved_service' => $mapped_data['service_type']
                ]);
            }
        }
        
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        // Calculate completion percentage using field mapper
        $service_type = $mapped_data['service_type'] ?? null;
        $completion_percentage = BSP_Field_Mapper::calculate_completion_percentage($mapped_data, $service_type);
        
        // Prepare data for database insertion - mapped to actual table columns
        $db_data = [
            'session_id' => $mapped_data['session_id'] ?? wp_generate_uuid4(),
            'service' => $mapped_data['service_type'] ?? '', // Matches 'service' column
            'zip_code' => $mapped_data['zip_code'] ?? '',
            'customer_name' => $mapped_data['customer_name'] ?? '',
            'customer_email' => $mapped_data['customer_email'] ?? '',
            'customer_phone' => $mapped_data['customer_phone'] ?? '',
            'city' => $mapped_data['city'] ?? '',
            'state' => $mapped_data['state'] ?? '',
            'customer_address' => $mapped_data['customer_address'] ?? '',
            'completion_percentage' => $completion_percentage,
            'lead_type' => $this->determine_lead_type($mapped_data),
            'utm_source' => $mapped_data['utm_source'] ?? '',
            'utm_medium' => $mapped_data['utm_medium'] ?? '',
            'utm_campaign' => $mapped_data['utm_campaign'] ?? '',
            'utm_term' => $mapped_data['utm_term'] ?? '',
            'utm_content' => $mapped_data['utm_content'] ?? '',
            'gclid' => $mapped_data['gclid'] ?? '',
            'referrer' => $mapped_data['referrer'] ?? '',
            'form_data' => json_encode($mapped_data), // Store complete mapped data
            'created_at' => current_time('mysql'),
            'last_updated' => current_time('mysql'),
            'send_trigger' => $mapped_data['trigger'] ?? 'form_interaction'
        ];
        
        // Use UPSERT logic based on session_id
        $existing_lead = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE session_id = %s AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $db_data['session_id']
        ));
        
        if ($existing_lead) {
            // Update existing lead
            $db_data['last_updated'] = current_time('mysql');
            unset($db_data['created_at']); // Don't update creation time
            
            $result = $wpdb->update(
                $table_name,
                $db_data,
                ['id' => $existing_lead],
                $this->get_db_formats($db_data),
                ['%d']
            );
            
            if ($result === false) {
                bsp_debug_log("Database UPDATE error: " . $wpdb->last_error, 'DATABASE_ERROR', [
                    'table' => $table_name,
                    'existing_lead' => $existing_lead,
                    'data' => $db_data
                ]);
            }
            
            if ($result !== false) {
                // Trigger action for Google Sheets sync with mapped data
                bsp_debug_log("Triggering Google Sheets sync action for updated lead", 'SHEETS_TRIGGER', [
                    'lead_id' => $existing_lead,
                    'session_id' => $db_data['session_id'],
                    'completion_percentage' => $completion_percentage
                ]);
                do_action('bsp_incomplete_lead_captured', $existing_lead, $mapped_data);
            }
            
            return $result !== false ? $existing_lead : false;
        } else {
            // Insert new lead
            $result = $wpdb->insert(
                $table_name,
                $db_data,
                $this->get_db_formats($db_data)
            );
            
            if ($result === false) {
                bsp_debug_log("Database INSERT error: " . $wpdb->last_error, 'DATABASE_ERROR', [
                    'table' => $table_name,
                    'data' => $db_data,
                    'formats' => $this->get_db_formats($db_data)
                ]);
                return false;
            }
            
            if ($result) {
                $lead_id = $wpdb->insert_id;
                // Trigger action for Google Sheets sync with mapped data
                bsp_debug_log("Triggering Google Sheets sync action for new lead", 'SHEETS_TRIGGER', [
                    'lead_id' => $lead_id,
                    'session_id' => $db_data['session_id']
                ]);
                do_action('bsp_incomplete_lead_captured', $lead_id, $db_data);
                return $lead_id;
            }
            
            return false;
        }
    }
    
    /**
     * Generate service details from form data
     */
    private function generate_service_details($lead_data) {
        $details = [];
        $service = $lead_data['service'] ?? '';
        
        switch ($service) {
            case 'Roof':
                if (!empty($lead_data['roof_action'])) {
                    $details[] = 'Action: ' . $lead_data['roof_action'];
                }
                if (!empty($lead_data['roof_material'])) {
                    $details[] = 'Material: ' . $lead_data['roof_material'];
                }
                break;
                
            case 'Windows':
                if (!empty($lead_data['windows_action'])) {
                    $details[] = 'Action: ' . $lead_data['windows_action'];
                }
                if (!empty($lead_data['windows_replace_qty'])) {
                    $details[] = 'Replace Quantity: ' . $lead_data['windows_replace_qty'];
                }
                break;
                
            case 'Bathroom':
                if (!empty($lead_data['bathroom_option'])) {
                    $details[] = 'Option: ' . $lead_data['bathroom_option'];
                }
                break;
                
            case 'Siding':
                if (!empty($lead_data['siding_option'])) {
                    $details[] = 'Option: ' . $lead_data['siding_option'];
                }
                if (!empty($lead_data['siding_material'])) {
                    $details[] = 'Material: ' . $lead_data['siding_material'];
                }
                break;
                
            case 'Kitchen':
                if (!empty($lead_data['kitchen_action'])) {
                    $details[] = 'Action: ' . $lead_data['kitchen_action'];
                }
                if (!empty($lead_data['kitchen_component'])) {
                    $details[] = 'Component: ' . $lead_data['kitchen_component'];
                }
                break;
                
            case 'Decks':
                if (!empty($lead_data['decks_action'])) {
                    $details[] = 'Action: ' . $lead_data['decks_action'];
                }
                if (!empty($lead_data['decks_material'])) {
                    $details[] = 'Material: ' . $lead_data['decks_material'];
                }
                break;
                
            case 'ADU':
                if (!empty($lead_data['adu_action'])) {
                    $details[] = 'Action: ' . $lead_data['adu_action'];
                }
                if (!empty($lead_data['adu_type'])) {
                    $details[] = 'Type: ' . $lead_data['adu_type'];
                }
                break;
        }
        
        return implode('; ', $details);
    }
    
    /**
     * Get database format array for wpdb operations
     */
    private function get_db_formats($data) {
        $formats = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['form_step'])) {
                $formats[] = '%d'; // Integer
            } else {
                $formats[] = '%s'; // String
            }
        }
        
        return $formats;
    }

    /**
     * Determine lead type based on completion and data
     */
    private function determine_lead_type($lead_data) {
        $completion_percentage = intval($lead_data['completion_percentage'] ?? 0);
        
        if ($completion_percentage >= 90) {
            return 'High Quality';
        } elseif ($completion_percentage >= 50) {
            return 'Medium Quality';
        } else {
            return 'Initial Interest';
        }
    }

    /**
     * Compile form data into a structured format for storage
     */
    private function compile_form_data($lead_data) {
        $form_data = [
            'service' => $lead_data['service'] ?? '',
            'customer_info' => [
                'name' => $lead_data['full_name'] ?? '',
                'email' => $lead_data['email'] ?? '',
                'phone' => $lead_data['phone'] ?? '',
                'address' => $lead_data['address'] ?? '',
                'zip_code' => $lead_data['zip_code'] ?? ''
            ],
            'service_details' => $this->generate_service_details($lead_data),
            'form_progress' => [
                'current_step' => $lead_data['form_step'] ?? 0,
                'completion_percentage' => $lead_data['completion_percentage'] ?? 0,
                'trigger' => $lead_data['trigger'] ?? ''
            ],
            'marketing_data' => [
                'utm_source' => $lead_data['utm_source'] ?? '',
                'utm_medium' => $lead_data['utm_medium'] ?? '',
                'utm_campaign' => $lead_data['utm_campaign'] ?? '',
                'utm_term' => $lead_data['utm_term'] ?? '',
                'utm_content' => $lead_data['utm_content'] ?? '',
                'referrer' => $lead_data['referrer'] ?? ''
            ],
            'meta' => [
                'ip_address' => $lead_data['ip_address'] ?? '',
                'user_agent' => $lead_data['user_agent'] ?? '',
                'page_url' => $lead_data['page_url'] ?? '',
                'timestamp' => $lead_data['capture_timestamp'] ?? current_time('mysql')
            ]
        ];

        return $form_data;
    }
    
    /**
     * TEST FUNCTION: Manually trigger incomplete lead processing for debugging
     * Remove this function after debugging is complete
     */
    public function test_incomplete_lead_processing() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Create test lead data
        $test_lead_data = [
            'session_id' => 'test_' . time(),
            'service' => 'Kitchen',
            'full_name' => 'Test User Debug',
            'email' => 'test@debug.local',
            'phone' => '555-123-4567',
            'zip_code' => '90210',
            'city' => 'Beverly Hills',
            'state' => 'CA',
            'company' => 'Debug Testing Co',
            'kitchen_action' => 'Remodel',
            'kitchen_component' => 'Cabinets',
            'utm_source' => 'debug_test',
            'utm_medium' => 'manual',
            'utm_campaign' => 'background_testing',
            'completion_percentage' => 35,
            'form_step' => 2,
            'trigger' => 'manual_test'
        ];
        
        // Test the fast save
        $lead_id = $this->save_incomplete_lead_fast($test_lead_data);
        
        if ($lead_id) {
            $this->safe_lead_log("TEST: Fast save successful", [
                'lead_id' => $lead_id,
                'session_id' => $test_lead_data['session_id']
            ], 'TEST_DEBUG');
            
            // Test background processing
            $this->process_lead_background($lead_id, $test_lead_data);
            
            echo "Test lead created with ID: " . $lead_id . " - Check debug logs for processing details.";
        } else {
            echo "Failed to create test lead - check database connection.";
        }
    }
}

// Initialize the lead data collector
BSP_Lead_Data_Collector::get_instance();
