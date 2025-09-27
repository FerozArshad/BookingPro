<?php
/**
 * AJAX Handler for Booking System Pro
 */

if (!defined('ABSPATH')) exit;

class BSP_Ajax {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Frontend AJAX endpoints
        add_action('wp_ajax_bsp_get_availability', [$this, 'get_availability']);
        add_action('wp_ajax_nopriv_bsp_get_availability', [$this, 'get_availability']);
        
        add_action('wp_ajax_bsp_submit_booking', [$this, 'submit_booking']);
        add_action('wp_ajax_nopriv_bsp_submit_booking', [$this, 'submit_booking']);
        
        // Note: bsp_get_slots is handled by BSP_Booking_System_Pro class to avoid conflicts
        
        add_action('wp_ajax_bsp_test_webhook', [$this, 'test_webhook']);
        
        // Lead Capture AJAX endpoints - Phase 1
        add_action('wp_ajax_bsp_capture_incomplete_lead', [$this, 'capture_incomplete_lead']);
        add_action('wp_ajax_nopriv_bsp_capture_incomplete_lead', [$this, 'capture_incomplete_lead']);
        
        // Admin AJAX endpoints are handled in BSP_Admin class
    }
    
    /**
     * Get company ID by company name for accurate booking storage
     */
    private function get_company_id_by_name($company_name) {
        global $wpdb;
        
        // Handle multiple companies (comma-separated)
        if (strpos($company_name, ',') !== false) {
            $company_names = array_map('trim', explode(',', $company_name));
            $company_name = $company_names[0]; // Use first company for primary ID
        }
        
        // Clean the company name
        $company_name = trim($company_name);
        
        // Initialize database tables
        BSP_Database_Unified::init_tables();
        $tables = BSP_Database_Unified::$tables;
        
        // First try exact match
        $company_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tables['companies']} WHERE name = %s LIMIT 1",
            $company_name
        ));
        
        // If no exact match, try case-insensitive match
        if (!$company_id) {
            $company_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tables['companies']} WHERE LOWER(name) = LOWER(%s) LIMIT 1",
                $company_name
            ));
        }
        
        // If still no match, create default mapping based on common names
        if (!$company_id) {
            $default_mappings = [
                'Top Remodeling Pro' => 1,
                'RH Remodeling' => 2,
                'Eco Green' => 3
            ];
            
            $company_id = isset($default_mappings[$company_name]) ? $default_mappings[$company_name] : 1;
        }
        
        return $company_id ? intval($company_id) : 1; // Default to 1 if nothing found
    }
    
    /**
     * Get company name by company ID for availability checking
     */
    private function get_company_name_by_id($company_id) {
        global $wpdb;
        
        if (!$company_id) {
            return '';
        }
        
        // Initialize database tables
        BSP_Database_Unified::init_tables();
        $tables = BSP_Database_Unified::$tables;
        
        $company_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$tables['companies']} WHERE id = %d LIMIT 1",
            $company_id
        ));
        
        return $company_name ?: '';
    }
    
    /**
     * Get company availability for frontend calendar (REFACTORED for performance and accuracy)
     */
    public function get_availability() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_frontend_nonce')) {
            wp_send_json_error('Security check failed.');
        }
        
        // Handle both old and new parameter formats
        $company_ids = [];
        if (isset($_POST['company_ids']) && is_array($_POST['company_ids'])) {
            $company_ids = array_map('intval', $_POST['company_ids']);
        } elseif (isset($_POST['company_id'])) {
            $company_ids = [intval($_POST['company_id'])];
        }
        
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : (isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '');
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : $date_from;
        
        if (empty($company_ids) || !$date_from) {
            wp_send_json_error('Missing required parameters.');
        }
        
        // PERFORMANCE OPTIMIZATION: Single database query to fetch ALL booked slots
        $all_booked_slots = $this->fetch_all_booked_slots($company_ids, $date_from, $date_to);
        
        // Get companies and generate availability data using the fetched booked slots
        $db = BSP_Database_Unified::get_instance();
        $availability_data = [];
        
        foreach ($company_ids as $company_id) {
            $company = $db->get_company($company_id);
            
            if ($company) {
                // Generate availability for date range with pre-fetched booked slots
                $company_booked_slots = isset($all_booked_slots[$company_id]) ? $all_booked_slots[$company_id] : [];
                $availability_data[$company_id] = $this->generate_date_range_availability($company, $date_from, $date_to, $company_booked_slots);
            }
        }
        
        wp_send_json_success($availability_data);
    }
    
    /**
     * SIMPLIFIED: Fetch booked slots with much simpler query to avoid timeout issues
     * Returns array like: [company_id => ['2025-08-21_09:00', '2025-08-22_14:30']]
     */
    private function fetch_all_booked_slots($company_ids, $date_from, $date_to) {
        global $wpdb;
        
        if (empty($company_ids)) {
            return [];
        }
        
        // SIMPLIFIED: Use basic query to avoid complex LIKE operations that cause timeouts
        $company_ids_str = implode(',', array_map('intval', $company_ids));
        
        $query = $wpdb->prepare("
            SELECT 
                pm1.meta_value as booking_date,
                pm2.meta_value as booking_time,
                pm3.meta_value as company_id,
                p.ID as booking_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_booking_date'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_booking_time'
            INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_company_id'
            WHERE p.post_type = 'bsp_booking'
            AND p.post_status IN ('publish', 'pending')
            AND pm1.meta_value >= %s
            AND pm1.meta_value <= %s
            AND pm3.meta_value IN ({$company_ids_str})
            ORDER BY pm1.meta_value, pm2.meta_value
        ", $date_from, $date_to);
        
        $results = $wpdb->get_results($query);
        
        // Organize results by company ID for fast lookup
        $booked_slots = [];
        foreach ($results as $row) {
            $company_id = intval($row->company_id);
            
            // Only include if this company was requested
            if (in_array($company_id, $company_ids)) {
                $slot_key = trim($row->booking_date) . '_' . trim($row->booking_time);
                
                if (!isset($booked_slots[$company_id])) {
                    $booked_slots[$company_id] = [];
                }
                $booked_slots[$company_id][] = $slot_key;
            }
        }
        
        return $booked_slots;
    }
    
    /**
     * Submit booking from frontend
     */
    public function submit_booking() {
        // Add comprehensive debugging at the start
        bsp_debug_log("=== BOOKING SUBMISSION STARTED ===", 'BOOKING', [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? 'direct'
        ]);
        
        // Log raw POST data for debugging
        bsp_debug_log("Raw POST data received", 'BOOKING', $_POST);
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_frontend_nonce')) {
            bsp_debug_log("SECURITY: Nonce verification failed", 'ERROR');
            wp_send_json_error('Security check failed.');
        }
        
        bsp_debug_log("Security check passed", 'BOOKING');
        
        // Validate required fields
        $required_fields = ['service', 'full_name', 'email', 'phone', 'address', 'company', 'selected_date', 'selected_time'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                bsp_debug_log("VALIDATION: Missing required field: {$field}", 'ERROR');
                wp_send_json_error("Missing required field: {$field}");
            }
        }
        
        bsp_debug_log("Required field validation passed", 'BOOKING');
        
        // Sanitize data with field mapper integration
        bsp_debug_log("Starting data sanitization with field mapper", 'BOOKING');
        
        // Use field mapper to normalize field names before processing
        $normalized_data = BSP_Field_Mapper::map_form_data($_POST);
        
        // Capture session ID for lead continuity
        $session_id = $normalized_data['session_id'] ?? '';
        bsp_debug_log("Session ID captured for lead continuity", 'BOOKING', [
            'session_id' => $session_id,
            'session_id_length' => strlen($session_id),
            'session_id_empty' => empty($session_id)
        ]);
        
        // Enhanced city/state data with ZIP lookup using mapped data
        $city_received = $normalized_data['city'] ?? '';
        $state_received = $normalized_data['state'] ?? '';
        $zip_code = $normalized_data['zip_code'] ?? '';
        
        // If city/state are empty but ZIP is provided, try to get from ZIP lookup cache
        if (empty($city_received) && !empty($zip_code)) {
            $cached_location = get_transient('bsp_zip_' . $zip_code);
            if ($cached_location && is_array($cached_location)) {
                $city_received = $cached_location['city'] ?? '';
                $state_received = $cached_location['state'] ?? '';
                bsp_debug_log("City/State retrieved from ZIP cache via field mapper", 'BOOKING', [
                    'zip_code' => $zip_code,
                    'cached_city' => $city_received,
                    'cached_state' => $state_received
                ]);
            }
        }
        
        bsp_debug_log("Enhanced City/State data capture via field mapper", 'BOOKING', [
            'city_received' => $city_received,
            'state_received' => $state_received,
            'zip_code' => $zip_code,
            'city_empty' => empty($city_received),
            'state_empty' => empty($state_received),
            'zip_lookup_attempted' => !empty($zip_code) && empty($city_received)
        ]);
        
        // Use mapped data for booking creation
        $booking_data = [
            'service' => $normalized_data['service_type'] ?? '',
            'full_name' => $normalized_data['customer_name'] ?? '',
            'email' => $normalized_data['customer_email'] ?? '',
            'phone' => $normalized_data['customer_phone'] ?? '',
            'address' => $normalized_data['customer_address'] ?? '',
            'zip_code' => $zip_code,
            'city' => $city_received,
            'state' => $state_received,
            'company' => $normalized_data['company_name'] ?? '',
            'selected_date' => $normalized_data['booking_date'] ?? '',
            'selected_time' => $normalized_data['booking_time'] ?? '',
            'appointments' => $normalized_data['appointments'] ?? '',
            'service_details' => $normalized_data['service_details'] ?? ''
        ];
        
        bsp_debug_log("Core data mapped and sanitized", 'BOOKING', $booking_data);
        
        // Add service-specific fields from frontend
        $service_fields = [
            'roof_zip', 'windows_zip', 'bathroom_zip', 'siding_zip', 'kitchen_zip', 'decks_zip', 'adu_zip',
            'roof_action', 'roof_material',
            'windows_action', 'windows_replace_qty', 'windows_repair_needed',
            'bathroom_option',
            'siding_option', 'siding_material', 
            'kitchen_action', 'kitchen_component',
            'decks_action', 'decks_material',
            'adu_action', 'adu_type'
        ];
        
        $service_specific_data = [];
        foreach ($service_fields as $field) {
            if (isset($_POST[$field])) {
                $service_specific_data[$field] = sanitize_text_field($_POST[$field]);
                $booking_data[$field] = sanitize_text_field($_POST[$field]);
            }
        }
        
        bsp_debug_log("Service-specific fields added", 'BOOKING', $service_specific_data);

        // Capture and sanitize marketing source data
        $utm_params = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'referrer'];
        $source_data = [];
        foreach ($utm_params as $param) {
            if (!empty($_POST[$param])) {
                $source_data[$param] = sanitize_text_field($_POST[$param]);
            }
        }
        // Debug log for received UTM/source fields
        $booking_data['source_data'] = $source_data;
        
        bsp_debug_log("Marketing source data captured", 'BOOKING', $source_data);
        
        
        // Validate email
        if (!is_email($booking_data['email'])) {
            wp_send_json_error('Invalid email address.');
        }
        
        // If multiple appointments, create separate booking records for each appointment
        $appointments_json = $_POST['appointments'] ?? '';
        $appointments = json_decode(stripslashes($appointments_json), true);

        $created_booking_ids = [];
        
        if (!empty($appointments) && is_array($appointments)) {
            // Create ONE booking with comma-separated company data
            $company_names = [];
            $company_ids = [];
            $booking_dates = [];
            $booking_times = [];
            
            foreach ($appointments as $appointment) {
                $company_names[] = $appointment['company'] ?? $booking_data['company'];
                $company_ids[] = $appointment['companyId'] ?? $this->get_company_id_by_name($appointment['company']);
                $booking_dates[] = $appointment['date'] ?? $booking_data['selected_date'];
                $booking_times[] = $appointment['time'] ?? $booking_data['selected_time'];
            }
            
            // Join all data with commas
            $combined_company_name = implode(', ', $company_names);
            $combined_company_id = implode(', ', $company_ids);
            $combined_booking_date = implode(', ', $booking_dates);
            $combined_booking_time = implode(', ', $booking_times);
            
            // Create single booking post with comma-separated data
            $post_data = [
                'post_title' => sprintf('Booking - %s - %s Companies - %s', 
                    $booking_data['full_name'], 
                    count($appointments), 
                    $booking_dates[0] // Use first date for title
                ),
                'post_content' => $booking_data['service_details'],
                'post_status' => 'publish',
                'post_type' => 'bsp_booking',
                'meta_input' => [
                    '_city' => $booking_data['city'],
                    '_state' => $booking_data['state'],
                    '_zip_code' => $booking_data['zip_code'],
                    '_customer_name' => $booking_data['full_name'],
                    '_customer_email' => $booking_data['email'],
                    '_customer_phone' => $booking_data['phone'],
                    '_customer_address' => $booking_data['address'],
                    '_company_name' => $combined_company_name,  // Comma-separated companies
                    '_company_id' => $combined_company_id,      // Comma-separated IDs
                    '_service_type' => $booking_data['service'],
                    '_booking_date' => $combined_booking_date,  // Comma-separated dates
                    '_booking_time' => $combined_booking_time,  // Comma-separated times
                    '_appointments' => $booking_data['appointments'],
                    '_specifications' => $this->_generate_specifications_string($_POST),
                    '_status' => 'pending',
                    '_created_at' => current_time('mysql'),
                    '_session_id' => $session_id, // For lead continuity tracking
                    // Service-specific fields
                    '_roof_action' => isset($booking_data['roof_action']) ? $booking_data['roof_action'] : '',
                    '_roof_material' => isset($booking_data['roof_material']) ? $booking_data['roof_material'] : '',
                    '_windows_action' => isset($booking_data['windows_action']) ? $booking_data['windows_action'] : '',
                    '_windows_replace_qty' => isset($booking_data['windows_replace_qty']) ? $booking_data['windows_replace_qty'] : '',
                    '_windows_repair_needed' => isset($booking_data['windows_repair_needed']) ? $booking_data['windows_repair_needed'] : '',
                    '_bathroom_option' => isset($booking_data['bathroom_option']) ? $booking_data['bathroom_option'] : '',
                    '_siding_option' => isset($booking_data['siding_option']) ? $booking_data['siding_option'] : '',
                    '_siding_material' => isset($booking_data['siding_material']) ? $booking_data['siding_material'] : '',
                    '_kitchen_action' => isset($booking_data['kitchen_action']) ? $booking_data['kitchen_action'] : '',
                    '_kitchen_component' => isset($booking_data['kitchen_component']) ? $booking_data['kitchen_component'] : '',
                    '_decks_action' => isset($booking_data['decks_action']) ? $booking_data['decks_action'] : '',
                    '_decks_material' => isset($booking_data['decks_material']) ? $booking_data['decks_material'] : '',
                    '_adu_action' => isset($booking_data['adu_action']) ? $booking_data['adu_action'] : '',
                    '_adu_type' => isset($booking_data['adu_type']) ? $booking_data['adu_type'] : '',
                    // Marketing attribution data
                    '_marketing_source' => $booking_data['source_data']
                ]
            ];
            
            bsp_debug_log("=== CREATING MULTIPLE BOOKING POST ===", 'DATABASE', [
                'post_data' => $post_data,
                'company_count' => count($appointments),
                'combined_companies' => $combined_company_name
            ]);
            
            $booking_id = wp_insert_post($post_data);
            
            if ($booking_id && !is_wp_error($booking_id)) {
                $created_booking_ids[] = $booking_id;
                
                bsp_debug_log("MULTIPLE booking post created successfully", 'DATABASE', [
                    'booking_id' => $booking_id,
                    'post_title' => $post_data['post_title']
                ]);
                
                // Set taxonomies
                wp_set_object_terms($booking_id, 'pending', 'bsp_booking_status');
                wp_set_object_terms($booking_id, $booking_data['service'], 'bsp_service_type');
                
                bsp_debug_log("Taxonomies set for multiple booking", 'DATABASE', [
                    'booking_id' => $booking_id,
                    'status' => 'pending',
                    'service' => $booking_data['service']
                ]);
            } else {
                $error_msg = is_wp_error($booking_id) ? $booking_id->get_error_message() : 'Unknown error';
                bsp_debug_log("FAILED to create multiple booking post", 'ERROR', [
                    'error' => $error_msg,
                    'post_data' => $post_data
                ]);
            }
        } else {
            // Single appointment - use the original logic
            $company_id = $this->get_company_id_by_name($booking_data['company']);
            
            // Create booking post
            $post_data = [
                'post_title' => sprintf('Booking - %s - %s', $booking_data['full_name'], $booking_data['selected_date']),
                'post_content' => $booking_data['service_details'],
                'post_status' => 'publish',
                'post_type' => 'bsp_booking',
                'meta_input' => [
                    '_city' => $booking_data['city'],
                    '_state' => $booking_data['state'],
                    '_zip_code' => $booking_data['zip_code'],
                    '_customer_name' => $booking_data['full_name'],
                    '_customer_email' => $booking_data['email'],
                    '_customer_phone' => $booking_data['phone'],
                    '_customer_address' => $booking_data['address'],
                    '_company_name' => $booking_data['company'],
                    '_company_id' => $company_id,
                    '_service_type' => $booking_data['service'],
                    '_booking_date' => $booking_data['selected_date'],
                    '_booking_time' => $booking_data['selected_time'],
                    '_appointments' => $booking_data['appointments'],
                    '_specifications' => $this->_generate_specifications_string($_POST),
                    '_status' => 'pending',
                    '_created_at' => current_time('mysql'),
                    // Service-specific fields
                    '_roof_action' => isset($booking_data['roof_action']) ? $booking_data['roof_action'] : '',
                    '_roof_material' => isset($booking_data['roof_material']) ? $booking_data['roof_material'] : '',
                    '_windows_action' => isset($booking_data['windows_action']) ? $booking_data['windows_action'] : '',
                    '_windows_replace_qty' => isset($booking_data['windows_replace_qty']) ? $booking_data['windows_replace_qty'] : '',
                    '_windows_repair_needed' => isset($booking_data['windows_repair_needed']) ? $booking_data['windows_repair_needed'] : '',
                    '_bathroom_option' => isset($booking_data['bathroom_option']) ? $booking_data['bathroom_option'] : '',
                    '_siding_option' => isset($booking_data['siding_option']) ? $booking_data['siding_option'] : '',
                    '_siding_material' => isset($booking_data['siding_material']) ? $booking_data['siding_material'] : '',
                    '_kitchen_action' => isset($booking_data['kitchen_action']) ? $booking_data['kitchen_action'] : '',
                    '_kitchen_component' => isset($booking_data['kitchen_component']) ? $booking_data['kitchen_component'] : '',
                    '_decks_action' => isset($booking_data['decks_action']) ? $booking_data['decks_action'] : '',
                    '_decks_material' => isset($booking_data['decks_material']) ? $booking_data['decks_material'] : '',
                    '_adu_action' => isset($booking_data['adu_action']) ? $booking_data['adu_action'] : '',
                    '_adu_type' => isset($booking_data['adu_type']) ? $booking_data['adu_type'] : '',
                    '_session_id' => $session_id, // For lead continuity tracking
                    // Marketing attribution data
                    '_marketing_source' => $booking_data['source_data']
                ]
            ];
            
            bsp_debug_log("=== CREATING SINGLE BOOKING POST ===", 'DATABASE', [
                'post_data' => $post_data,
                'company_id' => $company_id
            ]);
            
            $booking_id = wp_insert_post($post_data);
            
            if ($booking_id && !is_wp_error($booking_id)) {
                $created_booking_ids[] = $booking_id;
                
                bsp_debug_log("SINGLE booking post created successfully", 'DATABASE', [
                    'booking_id' => $booking_id,
                    'post_title' => $post_data['post_title']
                ]);
                
                // Set taxonomies
                wp_set_object_terms($booking_id, 'pending', 'bsp_booking_status');
                wp_set_object_terms($booking_id, $booking_data['service'], 'bsp_service_type');
                
                bsp_debug_log("Taxonomies set for single booking", 'DATABASE', [
                    'booking_id' => $booking_id,
                    'status' => 'pending', 
                    'service' => $booking_data['service']
                ]);
            } else {
                $error_msg = is_wp_error($booking_id) ? $booking_id->get_error_message() : 'Unknown error';
                bsp_debug_log("FAILED to create single booking post", 'ERROR', [
                    'error' => $error_msg,
                    'post_data' => $post_data
                ]);
            }
        }
        
        if (empty($created_booking_ids)) {
            bsp_debug_log("CRITICAL: No booking IDs created", 'ERROR');
            wp_send_json_error('Failed to create any bookings.');
        }
        
        $primary_booking_id = $created_booking_ids[0];
        
        // CRITICAL: Schedule all background jobs AFTER the response is sent using shutdown hook
        add_action('shutdown', function() use ($primary_booking_id, $booking_data, $session_id) {
            bsp_debug_log("=== SCHEDULING BACKGROUND JOBS AFTER RESPONSE ===", 'INTEGRATION', [
                'primary_booking_id' => $primary_booking_id,
                'session_id' => $session_id
            ]);
            
            // CRITICAL: Mark lead as converted IMMEDIATELY - MOVED TO BACKGROUND FOR SPEED
            if ($session_id && class_exists('BSP_Lead_Conversion_Tracker')) {
                $conversion_tracker = BSP_Lead_Conversion_Tracker::get_instance();
                if ($conversion_tracker && method_exists($conversion_tracker, 'track_lead_conversion')) {
                    $conversion_tracker->track_lead_conversion($session_id, $primary_booking_id, $booking_data);
                    bsp_debug_log("=== LEAD MARKED AS CONVERTED IN BACKGROUND ===", 'CONVERSION', [
                        'booking_id' => $primary_booking_id,
                        'session_id' => $session_id,
                        'timing' => 'BACKGROUND_AFTER_RESPONSE'
                    ]);
                }
            }
            
            // CRITICAL: Update incomplete lead to complete status for session continuity - MOVED TO BACKGROUND
            if ($session_id) {
                $this->update_incomplete_lead_to_complete($session_id, $primary_booking_id, $booking_data);
            }
            
            // FIXED: Single Google Sheets sync with proper deduplication - FASTER TIMING
            if (function_exists('wp_schedule_single_event')) {
                // Check if sync job already scheduled for this booking to prevent duplicates
                $existing_sync = get_post_meta($primary_booking_id, '_google_sheets_sync_scheduled', true);
                if (!$existing_sync) {
                    // Customer email notification (immediate - 2 seconds)
                    wp_schedule_single_event(time() + 2, 'bsp_send_customer_notification', [$primary_booking_id, $booking_data]);
                    bsp_debug_log("Scheduled customer email notification", 'INTEGRATION', [
                        'booking_id' => $primary_booking_id,
                        'schedule_time' => time() + 2
                    ]);
                    
                    // Admin email notification (immediate - 4 seconds)
                    wp_schedule_single_event(time() + 4, 'bsp_send_admin_notification', [$primary_booking_id, $booking_data]);
                    bsp_debug_log("Scheduled admin email notification", 'INTEGRATION', [
                        'booking_id' => $primary_booking_id,
                        'schedule_time' => time() + 4
                    ]);
                    
                    // Google Sheets sync (single attempt - 8 seconds) - ONLY ONE HANDLER NOW
                    $sheets_sync_data = $booking_data;
                    $sheets_sync_data['session_id'] = $session_id; // Include session_id for lead continuity
                    wp_schedule_single_event(time() + 8, 'bsp_sync_google_sheets', [$primary_booking_id, $sheets_sync_data]);
                    update_post_meta($primary_booking_id, '_google_sheets_sync_scheduled', time());
                    
                    bsp_debug_log("Scheduled Google Sheets sync with session ID", 'INTEGRATION', [
                        'booking_id' => $primary_booking_id,
                        'session_id' => $session_id,
                        'schedule_time' => time() + 8,
                        'sync_scheduled_timestamp' => time()
                    ]);
                } else {
                    bsp_debug_log("Google Sheets sync already scheduled, skipping duplicate", 'INTEGRATION', [
                        'booking_id' => $primary_booking_id,
                        'existing_sync_timestamp' => $existing_sync
                    ]);
                }
            } else {
                bsp_debug_log("wp_schedule_single_event function not available!", 'CRON_ERROR');
            }
        });
        
        // CRITICAL: Ensure success response is sent immediately before any Google Sheets processing
        bsp_debug_log("=== SENDING SUCCESS RESPONSE ===", 'AJAX_RESPONSE', [
            'primary_booking_id' => $primary_booking_id,
            'created_booking_ids' => $created_booking_ids,
            'timestamp' => current_time('mysql')
        ]);
        
        // Immediate success response - GUARANTEED to reach frontend before background jobs
        wp_send_json_success([
            'message' => 'Booking submitted successfully!',
            'booking_ids' => $created_booking_ids,
            'primary_booking_id' => $primary_booking_id,
            'booking_id' => $primary_booking_id  // For backward compatibility
        ]);
        
        // This line should NEVER be reached due to wp_send_json_success() calling wp_die()
        bsp_debug_log("ERROR: Code continued after wp_send_json_success!", 'CRITICAL_ERROR');
    }
    
    /**
     * Generate specifications string from service-specific data
     */
    private function _generate_specifications_string($data) {
        $service = sanitize_text_field($data['service'] ?? '');
        $specifications = '';
        
        switch ($service) {
            case 'Roof':
                $action = sanitize_text_field($data['roof_action'] ?? '');
                $material = sanitize_text_field($data['roof_material'] ?? '');
                $parts = [];
                if (!empty($action)) $parts[] = "Action: {$action}";
                if (!empty($material)) $parts[] = "Material: {$material}";
                $specifications = implode(', ', $parts);
                break;
                
            case 'Windows':
                $action = sanitize_text_field($data['windows_action'] ?? '');
                $qty = sanitize_text_field($data['windows_replace_qty'] ?? '');
                $repair = sanitize_text_field($data['windows_repair_needed'] ?? '');
                $parts = [];
                if (!empty($action)) $parts[] = "Action: {$action}";
                if (!empty($qty)) $parts[] = "Quantity: {$qty}";
                if (!empty($repair)) $parts[] = "Repair Needed: {$repair}";
                $specifications = implode(', ', $parts);
                break;
                
            case 'Bathroom':
                $option = sanitize_text_field($data['bathroom_option'] ?? '');
                if (!empty($option)) {
                    $specifications = "Option: {$option}";
                }
                break;
                
            case 'Siding':
                $option = sanitize_text_field($data['siding_option'] ?? '');
                $material = sanitize_text_field($data['siding_material'] ?? '');
                $parts = [];
                if (!empty($option)) $parts[] = "Option: {$option}";
                if (!empty($material)) $parts[] = "Material: {$material}";
                $specifications = implode(', ', $parts);
                break;
                
            case 'Kitchen':
                $action = sanitize_text_field($data['kitchen_action'] ?? '');
                $component = sanitize_text_field($data['kitchen_component'] ?? '');
                $parts = [];
                if (!empty($action)) $parts[] = "Action: {$action}";
                if (!empty($component)) $parts[] = "Component: {$component}";
                $specifications = implode(', ', $parts);
                break;
                
            case 'Decks':
                $action = sanitize_text_field($data['decks_action'] ?? '');
                $material = sanitize_text_field($data['decks_material'] ?? '');
                $parts = [];
                if (!empty($action)) $parts[] = "Action: {$action}";
                if (!empty($material)) $parts[] = "Material: {$material}";
                $specifications = implode(', ', $parts);
                break;
                
            case 'ADU':
                $action = sanitize_text_field($data['adu_action'] ?? '');
                $type = sanitize_text_field($data['adu_type'] ?? '');
                $parts = [];
                if (!empty($action)) $parts[] = "Action: {$action}";
                if (!empty($type)) $parts[] = "Type: {$type}";
                $specifications = implode(', ', $parts);
                break;
                
            default:
                $specifications = '';
                break;
        }
        
        return $specifications;
    }
    
    /**
     * Update incomplete lead to complete status for session continuity
     * CRITICAL for session-based Google Sheets approach
     */
    private function update_incomplete_lead_to_complete($session_id, $booking_id, $booking_data) {
        if (empty($session_id)) {
            bsp_debug_log("No session ID provided for incomplete lead update", 'CONVERSION_WARNING');
            return false;
        }
        
        global $wpdb;
        BSP_Database_Unified::init_tables();
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        // Find the incomplete lead for this session
        $incomplete_lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s ORDER BY id DESC LIMIT 1",
            $session_id
        ));
        
        if ($incomplete_lead) {
            // Update the incomplete lead to complete status - ONLY USE EXISTING COLUMNS
            $update_data = [
                'lead_type' => 'Complete',
                'booking_post_id' => $booking_id,
                'converted_to_booking' => 1,
                'completion_percentage' => 100,
                'conversion_timestamp' => current_time('mysql'),
                'last_updated' => current_time('mysql'),
                // Update customer data from final form submission - ONLY EXISTING COLUMNS
                'customer_name' => $booking_data['full_name'] ?? '',
                'customer_email' => $booking_data['email'] ?? '',
                'customer_phone' => $booking_data['phone'] ?? '',
                'customer_address' => $booking_data['address'] ?? '',
                'city' => $booking_data['city'] ?? '',
                'state' => $booking_data['state'] ?? '',
                'zip_code' => $booking_data['zip_code'] ?? '',
                'service' => $booking_data['service'] ?? '', // Use 'service' not 'service_type'
                // Store additional data in form_data field as JSON
                'final_form_data' => json_encode([
                    'service_details' => $booking_data['service_details'] ?? '',
                    'company_name' => $booking_data['company'] ?? '',
                    'booking_date' => $booking_data['selected_date'] ?? '',
                    'booking_time' => $booking_data['selected_time'] ?? '',
                    'appointments' => $booking_data['appointments'] ?? ''
                ])
            ];
            
            $result = $wpdb->update(
                $table_name,
                $update_data,
                ['id' => $incomplete_lead->id],
                [
                    '%s', '%d', '%d', '%d', '%s', '%s', // lead_type, booking_post_id, converted_to_booking, completion_percentage, conversion_timestamp, last_updated  
                    '%s', '%s', '%s', '%s', '%s', '%s', '%s', // customer data
                    '%s', '%s'                                 // service and final_form_data
                ],
                ['%d']
            );
            
            if ($result !== false) {
                bsp_debug_log("Incomplete lead updated to complete for session continuity", 'CONVERSION_SUCCESS', [
                    'session_id' => $session_id,
                    'booking_id' => $booking_id,
                    'lead_id' => $incomplete_lead->id,
                    'updated_fields' => array_keys($update_data)
                ]);
                return true;
            } else {
                bsp_debug_log("Failed to update incomplete lead to complete", 'CONVERSION_ERROR', [
                    'session_id' => $session_id,
                    'booking_id' => $booking_id,
                    'lead_id' => $incomplete_lead->id,
                    'db_error' => $wpdb->last_error
                ]);
                return false;
            }
        } else {
            bsp_debug_log("No incomplete lead found for session - creating lead continuity record", 'CONVERSION_INFO', [
                'session_id' => $session_id,
                'booking_id' => $booking_id
            ]);
            
            // Create a complete lead record for session continuity - ONLY USE EXISTING COLUMNS
            $lead_data = [
                'session_id' => $session_id,
                'lead_type' => 'Complete',
                'booking_post_id' => $booking_id,
                'converted_to_booking' => 1,
                'completion_percentage' => 100,
                'customer_name' => $booking_data['full_name'] ?? '',
                'customer_email' => $booking_data['email'] ?? '',
                'customer_phone' => $booking_data['phone'] ?? '',
                'customer_address' => $booking_data['address'] ?? '',
                'city' => $booking_data['city'] ?? '',
                'state' => $booking_data['state'] ?? '',
                'zip_code' => $booking_data['zip_code'] ?? '',
                'service' => $booking_data['service'] ?? '', // Use 'service' not 'service_type'
                // Store additional data in form_data field as JSON
                'final_form_data' => json_encode([
                    'service_details' => $booking_data['service_details'] ?? '',
                    'company_name' => $booking_data['company'] ?? '',
                    'booking_date' => $booking_data['selected_date'] ?? '',
                    'booking_time' => $booking_data['selected_time'] ?? '',
                    'appointments' => $booking_data['appointments'] ?? ''
                ]),
                'created_at' => current_time('mysql'),
                'conversion_timestamp' => current_time('mysql'),
                'last_updated' => current_time('mysql')
            ];
            
            $insert_result = $wpdb->insert(
                $table_name,
                $lead_data,
                [
                    '%s', '%s', '%d', '%d', '%d', // session_id, lead_type, booking_post_id, converted_to_booking, completion_percentage
                    '%s', '%s', '%s', '%s', '%s', '%s', '%s', // customer data
                    '%s', '%s', // service and final_form_data
                    '%s', '%s', '%s' // timestamps
                ]
            );
            
            if ($insert_result) {
                bsp_debug_log("Created complete lead record for session continuity", 'CONVERSION_SUCCESS', [
                    'session_id' => $session_id,
                    'booking_id' => $booking_id,
                    'new_lead_id' => $wpdb->insert_id
                ]);
                return true;
            } else {
                bsp_debug_log("Failed to create complete lead record", 'CONVERSION_ERROR', [
                    'session_id' => $session_id,
                    'booking_id' => $booking_id,
                    'db_error' => $wpdb->last_error
                ]);
                return false;
            }
        }
    }
    
    /**
     * Background job: Send customer notification email
     */
    public function handle_customer_notification($booking_id, $booking_data) {
        try {
            $email_settings = get_option('bsp_email_settings', []);
            
            // Send customer confirmation if enabled
            if (!empty($email_settings['customer_confirmation'])) {
                $sent = $this->send_customer_confirmation($booking_data);
                
                if (!$sent) {
                    // Retry once after 30 seconds if failed (faster retry)
                    wp_schedule_single_event(time() + 30, 'bsp_send_customer_notification', [$booking_id, $booking_data]);
                }
            }
        } catch (Exception $e) {
            // Silent error handling for email failures
        }
    }
    
    /**
     * Background job: Send admin notification email  
     */
    public function handle_admin_notification($booking_id, $booking_data) {
        try {
            $email_settings = get_option('bsp_email_settings', []);
            
            // Send admin notification if enabled
            if (!empty($email_settings['admin_notifications'])) {
                $sent = $this->send_admin_notification($booking_id);
                
                if (!$sent) {
                    // Retry once after 45 seconds if failed (faster retry)
                    wp_schedule_single_event(time() + 45, 'bsp_send_admin_notification', [$booking_id, $booking_data]);
                }
            }
        } catch (Exception $e) {
            // Silent error handling for admin email failures
        }
    }
    
    /**
     * Background job: Process additional booking tasks
     */
    public function handle_booking_extras($booking_id, $booking_data) {
        try {
            // Process toast notifications ONLY - Google Sheets handled separately
            do_action('bsp_booking_extras', $booking_id, $booking_data);
            
            bsp_debug_log("Booking extras processed (without duplicate Google Sheets sync)", 'INTEGRATION', [
                'booking_id' => $booking_id
            ]);
        } catch (Exception $e) {
            bsp_debug_log("Error in booking extras processing", 'ERROR', [
                'booking_id' => $booking_id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Background job: Google Sheets sync
     */
    public function handle_google_sheets($booking_id, $booking_data) {
        try {
            // Check if already successfully synced (prevent duplicates)
            $sync_status = get_post_meta($booking_id, '_google_sheets_synced', true);
            if ($sync_status === 'success') {
                return;
            }
            
            $this->send_to_google_sheets($booking_id, $booking_data);

        } catch (Exception $e) {
            // Silent error handling for Google Sheets sync
        }
    }
    
    /**
     * Send customer confirmation email
     */
    private function send_customer_confirmation($booking_data) {
        $subject = 'Booking Confirmation - ' . $booking_data['service'];
        $message = sprintf(
            "Dear %s,\n\nYour booking has been confirmed!\n\nDetails:\n- Service: %s\n- Date: %s\n- Time: %s\n- Company: %s\n\nThank you!",
            $booking_data['full_name'],
            $booking_data['service'],
            $booking_data['selected_date'],
            $booking_data['selected_time'],
            $booking_data['company']
        );
        
        return wp_mail($booking_data['email'], $subject, $message);
    }
    
    /**
     * Send admin notification email using centralized data manager
     */
    private function send_admin_notification($booking_id) {
        // Use centralized data manager to get all formatted booking data
        $data_for_email = BSP_Data_Manager::get_formatted_booking_data($booking_id);
        
        if (!$data_for_email) {
            return;
        }

        $email_settings = get_option('bsp_email_settings', []);
        $admin_email = $email_settings['admin_email'] ?? get_option('admin_email');
        
        $subject = 'New Booking #' . $data_for_email['id'] . ' - ' . $data_for_email['service_type'];
        
        // Create a comprehensive, well-formatted email message
        $message = "🎉 NEW BOOKING RECEIVED!\n";
        $message .= str_repeat("=", 50) . "\n\n";
        
        // Customer Information
        $message .= "👤 CUSTOMER INFORMATION:\n";
        $message .= "• Name: " . $data_for_email['customer_name'] . "\n";
        $message .= "• Email: " . $data_for_email['customer_email'] . "\n";
        $message .= "• Phone: " . $data_for_email['customer_phone'] . "\n";
        $message .= "• Address: " . $data_for_email['customer_address'] . "\n";
        
        // Location Information
        $message .= "\n📍 LOCATION DETAILS:\n";
        $message .= "• ZIP Code: " . ($data_for_email['zip_code'] ?: 'Not provided') . "\n";
        $message .= "• City: " . ($data_for_email['city'] ?: 'Not provided') . "\n";
        $message .= "• State: " . ($data_for_email['state'] ?: 'Not provided') . "\n";
        
        // Service Information
        $message .= "\n🔧 SERVICE DETAILS:\n";
        $message .= "• Service: " . $data_for_email['service_type'] . "\n";
        $message .= "• Company: " . ($data_for_email['company_name'] ?: 'Not selected') . "\n";
        
        // Add specifications if available
        if (!empty($data_for_email['specifications'])) {
            $message .= "• Specifications: " . $data_for_email['specifications'] . "\n";
        }
        
        // Appointment Information
        $message .= "\n📅 APPOINTMENT DETAILS:\n";
        $message .= "• Date: " . $data_for_email['formatted_date'] . "\n";
        $message .= "• Time: " . $data_for_email['formatted_time'] . "\n";
        
        // Multiple appointments if they exist
        if ($data_for_email['has_multiple_appointments'] && !empty($data_for_email['parsed_appointments'])) {
            $message .= "\n📅 MULTIPLE APPOINTMENTS:\n";
            foreach ($data_for_email['parsed_appointments'] as $i => $apt) {
                $message .= "• Appointment " . ($i + 1) . ": " . $apt['company'] . " - " . 
                           date('F j, Y', strtotime($apt['date'])) . " at " . 
                           date('g:i A', strtotime($apt['time'])) . "\n";
            }
        }
        
        // Marketing Information (if available)
        $has_marketing = !empty($data_for_email['utm_source']) || !empty($data_for_email['utm_medium']) || 
                        !empty($data_for_email['utm_campaign']) || !empty($data_for_email['referrer']);
        
        if ($has_marketing) {
            $message .= "\n📊 MARKETING ATTRIBUTION:\n";
            if (!empty($data_for_email['utm_source'])) {
                $message .= "• UTM Source: " . $data_for_email['utm_source'] . "\n";
            }
            if (!empty($data_for_email['utm_medium'])) {
                $message .= "• UTM Medium: " . $data_for_email['utm_medium'] . "\n";
            }
            if (!empty($data_for_email['utm_campaign'])) {
                $message .= "• UTM Campaign: " . $data_for_email['utm_campaign'] . "\n";
            }
            if (!empty($data_for_email['utm_term'])) {
                $message .= "• UTM Term: " . $data_for_email['utm_term'] . "\n";
            }
            if (!empty($data_for_email['utm_content'])) {
                $message .= "• UTM Content: " . $data_for_email['utm_content'] . "\n";
            }
            if (!empty($data_for_email['referrer'])) {
                $message .= "• Referrer: " . $data_for_email['referrer'] . "\n";
            }
            if (!empty($data_for_email['landing_page'])) {
                $message .= "• Landing Page: " . $data_for_email['landing_page'] . "\n";
            }
        }
        
        // Booking Information
        $message .= "\n📋 BOOKING INFORMATION:\n";
        $message .= "• Booking ID: #" . $data_for_email['id'] . "\n";
        $message .= "• Status: " . ucfirst($data_for_email['status']) . "\n";
        $message .= "• Created: " . $data_for_email['formatted_created'] . "\n";
        
        // Notes if available
        if (!empty($data_for_email['notes'])) {
            $message .= "\n📝 NOTES:\n";
            $message .= $data_for_email['notes'] . "\n";
        }
        
        $message .= "\n" . str_repeat("=", 50) . "\n";
        $message .= "🔗 View booking details: " . admin_url('admin.php?page=bsp-bookings&action=view&id=' . $data_for_email['id']) . "\n";
        
        // Send the email
        $sent = wp_mail($admin_email, $subject, $message);
        
        return $sent;
    }

    /**
     * Send booking data to Google Sheets using centralized data manager
     * Includes duplicate prevention for background sync reliability
     */
    public function send_to_google_sheets($booking_id, $booking_data) {
        // Enhanced debugging for Google Sheets sync process
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('Google Sheets Sync: INITIATED', 'SHEETS_SYNC', [
                'booking_id' => $booking_id,
                'data_keys' => array_keys($booking_data ?? []),
                'current_time' => current_time('mysql'),
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }
        
        // Double-check sync status to prevent race conditions
        $sync_status = get_post_meta($booking_id, '_google_sheets_synced', true);
        if ($sync_status === 'success') {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Google Sheets Sync: SKIPPED - Already Synced', 'SHEETS_SYNC', [
                    'booking_id' => $booking_id,
                    'sync_status' => $sync_status
                ]);
            }
            return true;
        }
        
        $integration_settings = get_option('bsp_integration_settings', []);

        if (empty($integration_settings['google_sheets_enabled']) || empty($integration_settings['google_sheets_webhook_url'])) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Google Sheets Sync: DISABLED or MISSING CONFIG', 'SHEETS_SYNC', [
                    'booking_id' => $booking_id,
                    'enabled' => !empty($integration_settings['google_sheets_enabled']),
                    'webhook_configured' => !empty($integration_settings['google_sheets_webhook_url'])
                ]);
            }
            return false;
        }

        // Use centralized data manager to get all formatted booking data
        if (!class_exists('BSP_Data_Manager')) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Google Sheets Sync: DATA MANAGER NOT FOUND', 'SHEETS_SYNC_ERROR', [
                    'booking_id' => $booking_id
                ]);
            }
            return false;
        }
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('Google Sheets Sync: RETRIEVING DATA', 'SHEETS_SYNC', [
                'booking_id' => $booking_id
            ]);
        }
        
        $data_for_sheets = BSP_Data_Manager::get_formatted_booking_data($booking_id);
        
        if (!$data_for_sheets) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Google Sheets Sync: NO DATA RETRIEVED', 'SHEETS_SYNC_ERROR', [
                    'booking_id' => $booking_id
                ]);
            }
            return false;
        }

        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('Google Sheets Sync: DATA RETRIEVED', 'SHEETS_SYNC', [
                'booking_id' => $booking_id,
                'customer_name' => $data_for_sheets['customer_name'] ?? 'N/A',
                'service_type' => $data_for_sheets['service_type'] ?? 'N/A',
                'data_keys' => array_keys($data_for_sheets)
            ]);
        }

        $webhook_url = $integration_settings['google_sheets_webhook_url'];
        
        // ENHANCED PAYLOAD FORMAT: Prepare properly structured payload for Google Sheets
        $payload = [
            'booking_id' => $data_for_sheets['id'],
            'customer_name' => $data_for_sheets['customer_name'],
            'customer_email' => $data_for_sheets['customer_email'],
            'customer_phone' => $data_for_sheets['customer_phone'],
            'customer_address' => $data_for_sheets['customer_address'],
            'city' => $data_for_sheets['city'],
            'state' => $data_for_sheets['state'],
            'zip_code' => $data_for_sheets['zip_code'],
            'service_type' => $data_for_sheets['service_type'],
            'company_name' => $data_for_sheets['company_name'],
            'booking_date' => $data_for_sheets['booking_date'],
            'booking_time' => $data_for_sheets['booking_time'],
            'formatted_date' => $data_for_sheets['formatted_date'] ?? date('F j, Y', strtotime($data_for_sheets['booking_date'])),
            'formatted_time' => $data_for_sheets['formatted_time'] ?? date('g:i A', strtotime($data_for_sheets['booking_time'])),
            'specifications' => $data_for_sheets['specifications'],
            'status' => $data_for_sheets['status'],
            'created_at' => $data_for_sheets['created_at'],
            'utm_source' => $data_for_sheets['utm_source'] ?? '',
            'utm_medium' => $data_for_sheets['utm_medium'] ?? '',
            'utm_campaign' => $data_for_sheets['utm_campaign'] ?? '',
            'referrer' => $data_for_sheets['referrer'] ?? '',
            'timestamp' => current_time('mysql')
        ];

        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('Google Sheets Sync: SENDING TO WEBHOOK', 'SHEETS_SYNC', [
                'booking_id' => $booking_id,
                'webhook_url' => substr($webhook_url, 0, 50) . '...',
                'payload_keys' => array_keys($payload),
                'customer_email' => $payload['customer_email'] ?? 'N/A'
            ]);
        }
        
        // ENHANCED REQUEST: Use form data format (same as working incomplete leads)
        $response = wp_remote_post($webhook_url, [
            'method' => 'POST',
            'headers' => [
                'User-Agent' => 'BookingSystemPro/1.0'
            ],
            'body' => $payload, // Send as form data instead of JSON
            'timeout' => 45,
            'sslverify' => true,
            'blocking' => true
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Google Sheets Sync: WP_ERROR', 'SHEETS_SYNC_ERROR', [
                    'booking_id' => $booking_id,
                    'error' => $error_message,
                    'customer_email' => $payload['customer_email'] ?? 'N/A'
                ]);
            }
            // Do NOT retry here to avoid duplicates. The job can be run manually if needed.
            update_post_meta($booking_id, '_google_sheets_synced', 'failed_wp_error');
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('Google Sheets Sync: RESPONSE RECEIVED', 'SHEETS_SYNC', [
                'booking_id' => $booking_id,
                'response_code' => $response_code,
                'response_size' => strlen($response_body),
                'response_preview' => mb_strimwidth($response_body, 0, 200, "...")
            ]);
        }

        if ($response_code >= 200 && $response_code < 300) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Google Sheets Sync: SUCCESS', 'SHEETS_SYNC', [
                    'booking_id' => $booking_id,
                    'response_code' => $response_code,
                    'customer_email' => $payload['customer_email'] ?? 'N/A',
                    'sync_time' => current_time('mysql')
                ]);
            }
            // Mark as success to prevent any future duplicates
            update_post_meta($booking_id, '_google_sheets_synced', 'success');
            return true;
        } else {
            // This includes 4xx and 5xx errors.
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Google Sheets Sync: FAILED', 'SHEETS_SYNC_ERROR', [
                    'booking_id' => $booking_id,
                    'response_code' => $response_code,
                    'response_body' => mb_strimwidth($response_body, 0, 500, "..."),
                    'customer_email' => $payload['customer_email'] ?? 'N/A'
                ]);
            }
            // Mark as failed but do NOT reschedule. This prevents duplicate submissions on 400 Bad Request.
            // The sync can be retried manually from the admin panel if necessary.
            update_post_meta($booking_id, '_google_sheets_synced', 'failed_http_' . $response_code);
            return false;
        }
    }
    
    /**
     * Generate availability for a date range in the format expected by frontend
     * Enforces strict 72-hour (3-day) booking window from server time
     * Uses pre-fetched booked slots for optimal performance
     */
    private function generate_date_range_availability($company, $date_from, $date_to, $company_booked_slots = []) {
        $availability = [];
        
        // Convert company data to array if it's an object
        $company_data = is_object($company) ? (array) $company : $company;
        
        // Ensure company ID is available for slot checking
        if (!isset($company_data['id']) && isset($company->id)) {
            $company_data['id'] = $company->id;
        }
        
        // Parse start and end times from database format
        $start_time = isset($company_data['available_hours_start']) ? 
            substr($company_data['available_hours_start'], 0, 5) : '09:00';
        $end_time = isset($company_data['available_hours_end']) ? 
            substr($company_data['available_hours_end'], 0, 5) : '17:00';
        
        // ENFORCE 72-HOUR WINDOW: Ignore frontend $date_to parameter
        // Server is the source of truth for availability window
        $current_date = new DateTime($date_from);
        $end_date = new DateTime($date_from);
        $end_date->add(new DateInterval('P3D')); // Exactly 3 days from start date
        
        // Create a fresh date object for each iteration to prevent reference issues
        while ($current_date <= $end_date) {
            $date_str = $current_date->format('Y-m-d');
            $day_of_week = $current_date->format('N'); // 1 = Monday, 7 = Sunday
            
            // Check if company is available on this day (assuming all days for now)
            $available_days = isset($company_data['available_days']) ? 
                explode(',', $company_data['available_days']) : [1,2,3,4,5,6,7];
            
            // ALWAYS add the calendar day to ensure consistent calendar structure
            // If company isn't available on this day, show empty slots
            if (in_array($day_of_week, $available_days)) {
                $slots = $this->generate_day_time_slots($company_data, $date_str, $start_time, $end_time, $company_booked_slots);
            } else {
                // Company not available on this day - show no slots but keep calendar day
                $slots = [];
            }
            
            // Format exactly as frontend expects
            $availability[$date_str] = [
                'slots' => $slots,
                'day_number' => $current_date->format('j'),
                'day_name' => $current_date->format('D'),
                'full_date' => $current_date->format('l, F j, Y')
            ];
            
            // Create a new DateTime object for the next iteration to avoid reference issues
            $current_date = clone $current_date;
            $current_date->add(new DateInterval('P1D'));
        }
        
        return $availability;
    }
    
    /**
     * Generate time slots for a specific day
     * Uses pre-fetched booked slots for optimal performance
     * IMPROVED: Better slot availability checking and validation
     */
    private function generate_day_time_slots($company_data, $date, $start_time, $end_time, $company_booked_slots = []) {
        $slots = [];
        
        // Validate input parameters
        if (!$date || !$start_time || !$end_time) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log("Invalid parameters for generate_day_time_slots", 'AVAILABILITY_DEBUG', [
                    'date' => $date,
                    'start_time' => $start_time,
                    'end_time' => $end_time
                ]);
            }
            return [];
        }
        
        // Parse start and end times with validation
        if (!preg_match('/^\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log("Invalid time format", 'AVAILABILITY_DEBUG', [
                    'start_time' => $start_time,
                    'end_time' => $end_time
                ]);
            }
            return [];
        }
        
        list($start_hour, $start_min) = explode(':', $start_time);
        list($end_hour, $end_min) = explode(':', $end_time);
        
        $start_minutes = ($start_hour * 60) + $start_min;
        $end_minutes = ($end_hour * 60) + $end_min;
        
        // Validate time range
        if ($start_minutes >= $end_minutes) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log("Invalid time range - start time not before end time", 'AVAILABILITY_DEBUG', [
                    'start_minutes' => $start_minutes,
                    'end_minutes' => $end_minutes
                ]);
            }
            return [];
        }
        
        // Get slot duration (default 30 minutes)
        $slot_duration = isset($company_data['time_slot_duration']) ? 
            intval($company_data['time_slot_duration']) : 30;
        
        // Ensure slot duration is valid
        if ($slot_duration <= 0 || $slot_duration > 480) { // Max 8 hours
            $slot_duration = 30; // Default fallback
        }
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("Generating time slots", 'AVAILABILITY_DEBUG', [
                'date' => $date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'slot_duration' => $slot_duration,
                'company_booked_slots_count' => count($company_booked_slots)
            ]);
        }
        
        // Generate slots
        for ($minutes = $start_minutes; $minutes < $end_minutes; $minutes += $slot_duration) {
            $hour = floor($minutes / 60);
            $min = $minutes % 60;
            
            $time_24 = sprintf('%02d:%02d', $hour, $min);
            $time_12 = date('g:i A', strtotime($time_24));
            
            // Check if slot is booked using pre-fetched data (FAST and ACCURATE!)
            $slot_key = $date . '_' . $time_24;
            $is_booked = in_array($slot_key, $company_booked_slots, true); // Strict comparison
            
            // CRITICAL DEBUG: Log slot checking for ALL slots to identify the issue
            if (function_exists('bsp_debug_log') && $date === '2025-08-29') {
                bsp_debug_log("SLOT CHECK FOR 2025-08-29", 'SLOT_CHECK_DEBUG', [
                    'date' => $date,
                    'time_24' => $time_24,
                    'slot_key' => $slot_key,
                    'is_booked' => $is_booked,
                    'company_booked_slots_count' => count($company_booked_slots),
                    'first_5_slots' => array_slice($company_booked_slots, 0, 5),
                    'contains_18_30' => in_array('2025-08-29_18:30', $company_booked_slots, true),
                    'method' => 'array_lookup'
                ]);
            }
            
            $slots[] = [
                'time' => $time_24,
                'formatted' => $time_12,  // Frontend expects 'formatted' not 'display'
                'display' => $time_12,    // Keep for backward compatibility
                'available' => !$is_booked,
                'date' => $date
            ];
        }
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("Generated slots summary", 'AVAILABILITY_DEBUG', [
                'date' => $date,
                'total_slots' => count($slots),
                'available_slots' => count(array_filter($slots, function($slot) { return $slot['available']; })),
                'booked_slots' => count(array_filter($slots, function($slot) { return !$slot['available']; }))
            ]);
        }
        
        return $slots;
    }
    
    /**
     * Debug availability system (admin only) - ENHANCED for better diagnostics
     */
    public function debug_availability() {
        // Only allow admin users
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        global $wpdb;
        
        $debug_info = [];
        
        // Check recent bookings with more detail
        $bookings = $wpdb->get_results("
            SELECT 
                p.ID,
                p.post_title,
                p.post_status,
                p.post_date,
                pm1.meta_value as company_id,
                pm2.meta_value as booking_date,
                pm3.meta_value as booking_time,
                pm4.meta_value as company_name
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_company_id'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_booking_date'
            LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_booking_time'
            LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_company_name'
            WHERE p.post_type = 'bsp_booking'
            AND p.post_status IN ('publish', 'pending')
            ORDER BY p.ID DESC
            LIMIT 10
        ");
        
        $debug_info['recent_bookings'] = $bookings;
        $debug_info['total_bookings'] = count($bookings);
        
        // Test fetch_all_booked_slots for each company individually
        $company_ids = [1, 2, 3];
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d', strtotime('+7 days'));
        
        $debug_info['individual_company_tests'] = [];
        foreach ($company_ids as $company_id) {
            $booked_slots = $this->fetch_all_booked_slots([$company_id], $date_from, $date_to);
            $debug_info['individual_company_tests'][$company_id] = [
                'company_id' => $company_id,
                'booked_slots_count' => isset($booked_slots[$company_id]) ? count($booked_slots[$company_id]) : 0,
                'booked_slots' => $booked_slots
            ];
        }
        
        // Test all companies together
        $all_booked_slots = $this->fetch_all_booked_slots($company_ids, $date_from, $date_to);
        $debug_info['all_companies_test'] = [
            'company_ids' => $company_ids,
            'date_range' => $date_from . ' to ' . $date_to,
            'result' => $all_booked_slots,
            'companies_with_bookings' => array_keys($all_booked_slots),
            'total_slots_found' => array_sum(array_map('count', $all_booked_slots))
        ];
        
        // Check database consistency
        $debug_info['database_check'] = [
            'wp_posts_bsp_booking_count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'bsp_booking'"),
            'wp_postmeta_company_id_count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_company_id'"),
            'wp_postmeta_booking_date_count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_booking_date'"),
            'wp_postmeta_booking_time_count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_booking_time'")
        ];
        
        // Test specific date/time slot checking
        $test_date = date('Y-m-d', strtotime('+1 day'));
        $test_time = '10:00';
        $test_slot_key = $test_date . '_' . $test_time;
        
        $debug_info['slot_key_test'] = [
            'test_date' => $test_date,
            'test_time' => $test_time,
            'test_slot_key' => $test_slot_key,
            'is_booked_company_1' => isset($all_booked_slots[1]) ? in_array($test_slot_key, $all_booked_slots[1], true) : false,
            'is_booked_company_2' => isset($all_booked_slots[2]) ? in_array($test_slot_key, $all_booked_slots[2], true) : false,
            'is_booked_company_3' => isset($all_booked_slots[3]) ? in_array($test_slot_key, $all_booked_slots[3], true) : false
        ];
        
        // Test individual company availability
        $db = BSP_Database_Unified::get_instance();
        foreach ($company_ids as $company_id) {
            $company = $db->get_company($company_id);
            if ($company) {
                $company_booked_slots = isset($booked_slots[$company_id]) ? $booked_slots[$company_id] : [];
                $debug_info['companies'][$company_id] = [
                    'name' => $company->name ?? 'Unknown',
                    'booked_slots_count' => count($company_booked_slots),
                    'booked_slots' => $company_booked_slots
                ];
            }
        }
        
        wp_send_json_success($debug_info);
    }
    
    /**
     * Test webhook endpoint for debugging Google Sheets integration - ENHANCED
     */
    public function test_webhook() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bsp_test_webhook')) {
            wp_send_json_error('Security check failed.');
            return;
        }
        
        // Only allow admin users to test webhook
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        // Get webhook URL from settings
        $integration_settings = get_option('bsp_integration_settings', []);
        $webhook_url = $integration_settings['google_sheets_webhook_url'] ?? '';
        
        if (empty($webhook_url)) {
            wp_send_json_error('Webhook URL not configured in integration settings');
            return;
        }
        
        // Create comprehensive test data
        $test_data = [
            'test_mode' => true,
            'booking_id' => 'TEST_' . time(),
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'customer_phone' => '(555) 123-4567',
            'customer_address' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'CA',
            'zip_code' => '90210',
            'service_type' => 'roofing',
            'company_name' => 'Test Company',
            'booking_date' => date('Y-m-d'),
            'booking_time' => '10:00',
            'formatted_date' => date('F j, Y'),
            'formatted_time' => '10:00 AM',
            'specifications' => 'Test specifications',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'utm_source' => 'test',
            'utm_medium' => 'webhook_test',
            'timestamp' => current_time('mysql')
        ];
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('Testing webhook endpoint', 'WEBHOOK_TEST', [
                'webhook_url' => $webhook_url,
                'test_data_keys' => array_keys($test_data)
            ]);
        }
        
        // Send test data to webhook with enhanced error handling
        $response = wp_remote_post($webhook_url, [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
                'User-Agent' => 'BookingSystemPro-WebhookTest/1.0'
            ],
            'body' => wp_json_encode($test_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'data_format' => 'body',
            'timeout' => 30,
            'blocking' => true,
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Webhook test failed with WP_Error', 'WEBHOOK_TEST', [
                    'error' => $error_message
                ]);
            }
            wp_send_json_error('Webhook error: ' . $error_message);
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        $test_result = [
            'webhook_url' => $webhook_url,
            'request_data' => $test_data,
            'response_code' => $response_code,
            'response_body' => $response_body,
            'response_headers' => $response_headers,
            'success' => ($response_code >= 200 && $response_code < 300),
            'timestamp' => current_time('mysql')
        ];
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('Webhook test completed', 'WEBHOOK_TEST', [
                'response_code' => $response_code,
                'success' => $test_result['success'],
                'response_length' => strlen($response_body)
            ]);
        }
        
        if ($test_result['success']) {
            wp_send_json_success([
                'message' => 'Webhook test successful',
                'result' => $test_result
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Webhook returned error',
                'result' => $test_result
            ]);
        }
    }
    
    /**
     * Handle incomplete lead capture - Phase 1 Implementation
     * Delegates to the Lead Data Collector class
     */
    public function capture_incomplete_lead() {
        // Ensure Lead Data Collector class is available
        if (!class_exists('BSP_Lead_Data_Collector')) {
            wp_send_json_error('Lead capture system not available.');
            return;
        }
        
        // Get Lead Data Collector instance and handle the request
        $collector = BSP_Lead_Data_Collector::get_instance();
        $collector->capture_incomplete_lead();
    }
}
?>
