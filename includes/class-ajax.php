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
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("BSP_Ajax initialized", 'AJAX');
        }
        
        // Frontend AJAX endpoints
        add_action('wp_ajax_bsp_get_availability', [$this, 'get_availability']);
        add_action('wp_ajax_nopriv_bsp_get_availability', [$this, 'get_availability']);
        
        add_action('wp_ajax_bsp_submit_booking', [$this, 'submit_booking']);
        add_action('wp_ajax_nopriv_bsp_submit_booking', [$this, 'submit_booking']);
        
        add_action('wp_ajax_bsp_get_slots', [$this, 'get_time_slots']);
        add_action('wp_ajax_nopriv_bsp_get_slots', [$this, 'get_time_slots']);
        
        add_action('wp_ajax_bsp_test_webhook', [$this, 'test_webhook']);
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("AJAX endpoints registered: bsp_get_availability, bsp_submit_booking, bsp_get_slots, bsp_test_webhook", 'AJAX');
        }
        
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
            
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log("Using default company mapping", 'DATABASE', [
                    'input_name' => $company_name,
                    'mapped_id' => $company_id
                ]);
            }
        }
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("Company ID lookup result", 'DATABASE', [
                'input_name' => $company_name,
                'found_id' => $company_id
            ]);
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
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("=== AVAILABILITY REQUEST RECEIVED ===", 'AVAILABILITY_DEBUG', [
                'all_POST_data' => $_POST,
                'server_time' => current_time('mysql'),
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'
            ]);
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_frontend_nonce')) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log("AJAX nonce verification failed for get_availability", 'AVAILABILITY_DEBUG', [
                    'received_nonce' => $_POST['nonce'] ?? 'MISSING',
                    'expected_action' => 'bsp_frontend_nonce'
                ]);
            }
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
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log("AJAX get_availability missing parameters", 'AJAX', [
                    'company_ids' => $company_ids,
                    'date_from' => $date_from,
                    'date_to' => $date_to
                ]);
            }
            wp_send_json_error('Missing required parameters.');
        }
        
        // PERFORMANCE OPTIMIZATION: Single database query to fetch ALL booked slots
        $all_booked_slots = $this->fetch_all_booked_slots($company_ids, $date_from, $date_to);
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("Fetched booked slots for all companies", 'PERFORMANCE', [
                'company_ids' => $company_ids,
                'date_range' => $date_from . ' to ' . $date_to,
                'booked_slots_count' => count($all_booked_slots)
            ]);
        }
        
        // Get companies and generate availability data using the fetched booked slots
        $db = BSP_Database_Unified::get_instance();
        $availability_data = [];
        
        foreach ($company_ids as $company_id) {
            $company = $db->get_company($company_id);
            
            if ($company) {
                // Generate availability for date range with pre-fetched booked slots
                $company_booked_slots = isset($all_booked_slots[$company_id]) ? $all_booked_slots[$company_id] : [];
                $availability_data[$company_id] = $this->generate_date_range_availability($company, $date_from, $date_to, $company_booked_slots);
                
                if (function_exists('bsp_debug_log')) {
                    bsp_debug_log("Generated availability for company", 'AVAILABILITY_DEBUG', [
                        'company_id' => $company_id,
                        'company_name' => $company->name ?? 'UNKNOWN',
                        'booked_slots_count' => count($company_booked_slots),
                        'booked_slots' => $company_booked_slots,
                        'availability_days_count' => count($availability_data[$company_id] ?? [])
                    ]);
                }
            } else {
                if (function_exists('bsp_debug_log')) {
                    bsp_debug_log("Company not found in database", 'AVAILABILITY_DEBUG', [
                        'company_id' => $company_id
                    ]);
                }
            }
        }
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("=== FINAL AVAILABILITY RESPONSE ===", 'AVAILABILITY_DEBUG', [
                'response_data_structure' => array_keys($availability_data),
                'total_companies' => count($availability_data)
            ]);
        }
        
        wp_send_json_success($availability_data);
    }
    
    /**
     * PERFORMANCE CRITICAL: Fetch ALL booked slots in ONE database query
     * Returns array like: [company_id => ['2025-08-21_09:00', '2025-08-22_14:30']]
     */
    private function fetch_all_booked_slots($company_ids, $date_from, $date_to) {
        global $wpdb;
        
        if (empty($company_ids)) {
            return [];
        }
        
        // Convert company IDs to a safe SQL IN clause
        $company_ids_sql = implode(',', array_map('intval', $company_ids));
        
        // Single, efficient database query to get ALL booked slots
        $query = $wpdb->prepare("
            SELECT 
                pm1.meta_value as booking_date,
                pm2.meta_value as booking_time,
                pm3.meta_value as company_id,
                pm4.meta_value as company_ids
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_booking_date'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_booking_time'
            INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_company_id'
            LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_company_ids'
            WHERE p.post_type = 'bsp_booking'
            AND p.post_status IN ('publish', 'pending')
            AND (
                pm3.meta_value IN ({$company_ids_sql})
                OR pm4.meta_value REGEXP CONCAT('(^|,)', pm3.meta_value, '(,|$)')
            )
            AND pm1.meta_value >= %s
            AND pm1.meta_value <= %s
        ", $date_from, $date_to);
        
        $results = $wpdb->get_results($query);
        
        // Organize results by company ID for fast lookup
        $booked_slots = [];
        foreach ($results as $row) {
            // Handle both single company_id and comma-separated company_ids
            $company_ids_to_check = [];
            
            // Add primary company ID
            if (!empty($row->company_id)) {
                $company_ids_to_check[] = intval($row->company_id);
            }
            
            // Add all company IDs from comma-separated list
            if (!empty($row->company_ids)) {
                $additional_ids = array_map('trim', explode(',', $row->company_ids));
                foreach ($additional_ids as $id) {
                    if (is_numeric($id)) {
                        $company_ids_to_check[] = intval($id);
                    }
                }
            }
            
            // Remove duplicates
            $company_ids_to_check = array_unique($company_ids_to_check);
            
            // Handle comma-separated dates and times (multiple appointments)
            $dates = array_map('trim', explode(',', $row->booking_date));
            $times = array_map('trim', explode(',', $row->booking_time));
            
            foreach ($company_ids_to_check as $company_id) {
                foreach ($dates as $date) {
                    foreach ($times as $time) {
                        $slot_key = $date . '_' . $time;
                        if (!isset($booked_slots[$company_id])) {
                            $booked_slots[$company_id] = [];
                        }
                        $booked_slots[$company_id][] = $slot_key;
                    }
                }
            }
        }
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("Single query fetched booked slots", 'PERFORMANCE', [
                'query_execution' => 'SUCCESS',
                'total_bookings_found' => count($results),
                'companies_with_bookings' => array_keys($booked_slots)
            ]);
        }
        
        return $booked_slots;
    }
    
    /**
     * Submit booking from frontend
     */
    public function submit_booking() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_frontend_nonce')) {
            wp_send_json_error('Security check failed.');
        }
        
        // Validate required fields
        $required_fields = ['service', 'full_name', 'email', 'phone', 'address', 'company', 'selected_date', 'selected_time'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error("Missing required field: {$field}");
            }
        }
        
        // Sanitize data
        $booking_data = [
            'service' => sanitize_text_field($_POST['service']),
            'full_name' => sanitize_text_field($_POST['full_name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'address' => sanitize_textarea_field($_POST['address']),
            'zip_code' => sanitize_text_field($_POST['zip_code']),
            'city' => isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '',
            'state' => isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '',
            'company' => sanitize_text_field($_POST['company']),
            'selected_date' => sanitize_text_field($_POST['selected_date']),
            'selected_time' => sanitize_text_field($_POST['selected_time']),
            'appointments' => sanitize_text_field($_POST['appointments'] ?? ''),
            'service_details' => sanitize_textarea_field($_POST['service_details'] ?? '')
        ];
        
        // Add service-specific fields from frontend
        $service_fields = [
            'roof_zip', 'windows_zip', 'bathroom_zip', 'siding_zip', 'kitchen_zip', 'decks_zip',
            'roof_action', 'roof_material',
            'windows_action', 'windows_replace_qty', 'windows_repair_needed',
            'bathroom_option',
            'siding_option', 'siding_material', 
            'kitchen_action', 'kitchen_component',
            'decks_action', 'decks_material'
        ];
        
        foreach ($service_fields as $field) {
            if (isset($_POST[$field])) {
                $booking_data[$field] = sanitize_text_field($_POST[$field]);
            }
        }

        // Capture and sanitize marketing source data
        $utm_params = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'referrer'];
        $source_data = [];
        foreach ($utm_params as $param) {
            if (!empty($_POST[$param])) {
                $source_data[$param] = sanitize_text_field($_POST[$param]);
            }
        }
        // Debug log for received UTM/source fields
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('Received UTM/source fields in POST:', 'TRACKING', array_intersect_key($_POST, array_flip($utm_params)));
        }
        $booking_data['source_data'] = $source_data;
        
        
        // Validate email
        if (!is_email($booking_data['email'])) {
            wp_send_json_error('Invalid email address.');
        }
        
        // Process multiple appointments into single booking with comma-separated values
        $appointments_json = $_POST['appointments'] ?? '';
        $appointments = json_decode(stripslashes($appointments_json), true);

        $company_ids = [];
        $company_names = [];
        $booking_dates = [];
        $booking_times = [];
        
        if (!empty($appointments) && is_array($appointments) && count($appointments) > 1) {
            // Multiple appointments - combine all into single booking
            foreach ($appointments as $appointment) {
                $company_name = $appointment['company'] ?? $booking_data['company'];
                $appointment_date = $appointment['date'] ?? $booking_data['selected_date'];
                $appointment_time = $appointment['time'] ?? $booking_data['selected_time'];
                $company_id = $appointment['companyId'] ?? $this->get_company_id_by_name($company_name);
                
                $company_names[] = $company_name;
                $company_ids[] = $company_id;
                $booking_dates[] = $appointment_date;
                $booking_times[] = $appointment_time;
            }
            
            // Store comma-separated values for multiple companies
            $booking_data['company'] = implode(', ', $company_names);
            $booking_data['selected_date'] = implode(', ', $booking_dates);
            $booking_data['selected_time'] = implode(', ', $booking_times);
            
        } else {
            // Single appointment or fallback
            $single_appointment = !empty($appointments) && is_array($appointments) ? $appointments[0] : null;
            if ($single_appointment && isset($single_appointment['companyId'])) {
                $company_ids[] = $single_appointment['companyId'];
            } else {
                $company_ids[] = $this->get_company_id_by_name($booking_data['company']);
            }
            $company_names[] = $booking_data['company'];
            $booking_dates[] = $booking_data['selected_date'];
            $booking_times[] = $booking_data['selected_time'];
        }

        // Get primary company ID for the main _company_id field
        $primary_company_id = !empty($company_ids) ? $company_ids[0] : $this->get_company_id_by_name($booking_data['company']);
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("Creating single consolidated booking", 'BOOKING', [
                'company_names' => $company_names,
                'company_ids' => $company_ids,
                'primary_company_id' => $primary_company_id,
                'booking_dates' => $booking_dates,
                'booking_times' => $booking_times,
                'appointment_count' => count($appointments ?: [])
            ]);
        }

        // Create single booking post with all appointment data consolidated
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
                '_company_name' => $booking_data['company'], // Comma-separated company names
                '_company_id' => $primary_company_id, // Primary company ID for backward compatibility
                '_company_ids' => implode(',', $company_ids), // All company IDs comma-separated
                '_service_type' => $booking_data['service'],
                '_booking_date' => $booking_data['selected_date'], // Comma-separated dates
                '_booking_time' => $booking_data['selected_time'], // Comma-separated times
                '_appointments' => $booking_data['appointments'], // Original JSON data
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
                // Marketing attribution data
                '_marketing_source' => $booking_data['source_data']
            ]
        ];
        
        $booking_id = wp_insert_post($post_data);
        
        if (!$booking_id || is_wp_error($booking_id)) {
            wp_send_json_error('Failed to create booking: ' . (is_wp_error($booking_id) ? $booking_id->get_error_message() : 'Unknown error'));
        }
        
        // Set taxonomies
        wp_set_object_terms($booking_id, 'pending', 'bsp_booking_status');
        wp_set_object_terms($booking_id, $booking_data['service'], 'bsp_service_type');
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("Single consolidated booking created successfully", 'BOOKING', [
                'booking_id' => $booking_id,
                'companies_included' => $booking_data['company'],
                'total_appointments' => count($appointments ?: [])
            ]);
        }
        
        // Send notifications for the booking
        $this->send_booking_notifications($booking_id, $booking_data);
        
        // Schedule background Google Sheets processing (60 seconds delay for better user experience)
        wp_schedule_single_event(time() + 60, 'bsp_send_to_google_sheets_event', array($booking_id));
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("Scheduled background Google Sheets processing", 'GOOGLE_SHEETS', [
                'booking_id' => $booking_id,
                'companies_included' => $booking_data['company'],
                'scheduled_time' => date('Y-m-d H:i:s', time() + 60)
            ]);
        }

        wp_send_json_success([
            'message' => 'Booking submitted successfully!',
            'booking_id' => $booking_id,
            'companies_included' => $booking_data['company']
        ]);
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
                
            default:
                $specifications = '';
                break;
        }
        
        return $specifications;
    }
    
    /**
     * Send booking notifications
     */
    private function send_booking_notifications($booking_id, $booking_data) {
        // Get email settings
        $email_settings = get_option('bsp_email_settings', []);
        
        // Send customer confirmation
        if (!empty($email_settings['send_customer_confirmation'])) {
            $this->send_customer_confirmation($booking_data);
        }
        
        // Send admin notification
        if (!empty($email_settings['send_admin_notification'])) {
            $this->send_admin_notification($booking_id);
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
        
        wp_mail($booking_data['email'], $subject, $message);
    }
    
    /**
     * Send admin notification email using centralized data manager
     */
    private function send_admin_notification($booking_id) {
        // Use centralized data manager to get all formatted booking data
        $data_for_email = BSP_Data_Manager::get_formatted_booking_data($booking_id);
        
        if (!$data_for_email) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Admin notification: Failed to get booking data for ID ' . $booking_id, 'EMAIL_ERROR');
            }
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
        
        if (function_exists('bsp_debug_log')) {
            if ($sent) {
                bsp_debug_log('Admin notification email sent successfully for booking ID: ' . $booking_id, 'EMAIL');
            } else {
                bsp_debug_log('Failed to send admin notification email for booking ID: ' . $booking_id, 'EMAIL_ERROR');
            }
        }
    }

    /**
     * @deprecated 2.1.0 Use background processing with bsp_cron_send_to_google_sheets() instead
     * Send booking data to Google Sheets using centralized data manager
     * 
     * This method is deprecated and kept for backward compatibility.
     * Google Sheets integration now uses background processing via WP-Cron.
     */
    private function send_to_google_sheets($booking_id, $booking_data) {
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('DEPRECATED: send_to_google_sheets() called. Use background processing instead.', 'DEPRECATED', [
                'booking_id' => $booking_id
            ]);
        }
        
        // For backward compatibility, schedule the background event immediately
        wp_schedule_single_event(time() + 5, 'bsp_send_to_google_sheets_event', array($booking_id));
        
        return;
        
        /* DEPRECATED CODE - Moved to background processing
        $integration_settings = get_option('bsp_integration_settings', []);

        /* DEPRECATED CODE - Moved to background processing
        $integration_settings = get_option('bsp_integration_settings', []);

        if (empty($integration_settings['google_sheets_enabled']) || empty($integration_settings['google_sheets_webhook_url'])) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Google Sheets sync disabled or webhook URL not set.', 'INTEGRATION');
            }
            return;
        }

        // Use centralized data manager to get all formatted booking data
        $data_for_sheets = BSP_Data_Manager::get_formatted_booking_data($booking_id);
        
        if (!$data_for_sheets) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Google Sheets Integration: Failed to get booking data for ID ' . $booking_id, 'INTEGRATION_ERROR');
            }
            return;
        }

        $webhook_url = $integration_settings['google_sheets_webhook_url'];

        // Prepare payload for Google Sheets with all the data in the expected format
        // Sanitize all data to prevent Google Sheets API errors
        $payload = [
            'id' => intval($data_for_sheets['id']),
            'booking_id' => intval($data_for_sheets['id']),
            'status' => sanitize_text_field($data_for_sheets['status']),
            'customer_name' => sanitize_text_field($data_for_sheets['customer_name']),
            'customer_email' => sanitize_email($data_for_sheets['customer_email']),
            'customer_phone' => sanitize_text_field($data_for_sheets['customer_phone']),
            'customer_address' => sanitize_text_field($data_for_sheets['customer_address']),
            'zip_code' => sanitize_text_field($data_for_sheets['zip_code']),
            'city' => sanitize_text_field($data_for_sheets['city']),
            'state' => sanitize_text_field($data_for_sheets['state']),
            'service_type' => sanitize_text_field($data_for_sheets['service_type']),
            'service_name' => sanitize_text_field($data_for_sheets['service_name']),
            'specifications' => sanitize_textarea_field($data_for_sheets['specifications']),
            'company_name' => sanitize_text_field($data_for_sheets['company_name']),
            'formatted_date' => sanitize_text_field($data_for_sheets['formatted_date']),
            'formatted_time' => sanitize_text_field($data_for_sheets['formatted_time']),
            'booking_date' => sanitize_text_field($data_for_sheets['booking_date']),
            'booking_time' => sanitize_text_field($data_for_sheets['booking_time']),
            'formatted_created' => sanitize_text_field($data_for_sheets['formatted_created']),
            'created_at' => sanitize_text_field($data_for_sheets['created_at']),
            'notes' => sanitize_textarea_field($data_for_sheets['notes']),
            // Marketing/tracking data
            'utm_source' => sanitize_text_field($data_for_sheets['utm_source']),
            'utm_medium' => sanitize_text_field($data_for_sheets['utm_medium']),
            'utm_campaign' => sanitize_text_field($data_for_sheets['utm_campaign']),
            'utm_term' => sanitize_text_field($data_for_sheets['utm_term']),
            'utm_content' => sanitize_text_field($data_for_sheets['utm_content']),
            'referrer' => sanitize_url($data_for_sheets['referrer']),
            'landing_page' => sanitize_url($data_for_sheets['landing_page']),
            // Service-specific fields
            'roof_action' => sanitize_text_field($data_for_sheets['roof_action']),
            'roof_material' => sanitize_text_field($data_for_sheets['roof_material']),
            'windows_action' => sanitize_text_field($data_for_sheets['windows_action']),
            'windows_replace_qty' => sanitize_text_field($data_for_sheets['windows_replace_qty']),
            'windows_repair_needed' => sanitize_text_field($data_for_sheets['windows_repair_needed']),
            'bathroom_option' => sanitize_text_field($data_for_sheets['bathroom_option']),
            'siding_option' => sanitize_text_field($data_for_sheets['siding_option']),
            'siding_material' => sanitize_text_field($data_for_sheets['siding_material']),
            'kitchen_action' => sanitize_text_field($data_for_sheets['kitchen_action']),
            'kitchen_component' => sanitize_text_field($data_for_sheets['kitchen_component']),
            'decks_action' => sanitize_text_field($data_for_sheets['decks_action']),
            'decks_material' => sanitize_text_field($data_for_sheets['decks_material']),
            // Handle multiple appointments - send as string to avoid JSON parsing issues
            'appointments' => is_string($data_for_sheets['appointments']) ? 
                $data_for_sheets['appointments'] : 
                wp_json_encode($data_for_sheets['appointments'], JSON_UNESCAPED_UNICODE)
        ];

        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('Google Sheets Integration: Sending sanitized data', 'INTEGRATION', $payload);
        }

        // Prepare the JSON payload
        $json_payload = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('Google Sheets Integration: JSON payload length: ' . strlen($json_payload), 'INTEGRATION');
        }

        // Send the request to Google Sheets with proper headers
        $response = wp_remote_post($webhook_url, [
            'method'      => 'POST',
            'headers'     => [
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
                'User-Agent' => 'BookingPro/2.1.0'
            ],
            'body'        => $json_payload,
            'data_format' => 'body',
            'timeout'     => 30,
            'blocking'    => true,
            'sslverify'   => true
        ]);

        // Log the response for debugging
        if (is_wp_error($response)) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Google Sheets API Error: ' . $response->get_error_message(), 'INTEGRATION_ERROR');
            }
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log("Google Sheets API Response Code: $response_code", 'INTEGRATION');
                bsp_debug_log("Google Sheets API Response Body: $response_body", 'INTEGRATION');
            }
            
            if ($response_code === 200) {
                if (function_exists('bsp_debug_log')) {
                    bsp_debug_log('Google Sheets sync successful for booking ID: ' . $booking_id, 'INTEGRATION');
                }
            } else {
                if (function_exists('bsp_debug_log')) {
                    bsp_debug_log("Google Sheets sync failed with code $response_code for booking ID: $booking_id", 'INTEGRATION_ERROR');
                }
            }
        }
        */
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
        
        // CRITICAL: Ensure company ID is available for slot checking
        if (!isset($company_data['id']) && isset($company->id)) {
            $company_data['id'] = $company->id;
        }
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("Generating availability for company", 'AVAILABILITY', [
                'company_id' => $company_data['id'] ?? 'MISSING',
                'company_name' => $company_data['name'] ?? 'MISSING',
                'date_range' => $date_from . ' to ' . $date_to,
                'pre_fetched_slots_count' => count($company_booked_slots)
            ]);
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
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("72-Hour Window Enforced", 'AVAILABILITY', [
                'requested_end' => $date_to,
                'enforced_start' => $current_date->format('Y-m-d'),
                'enforced_end' => $end_date->format('Y-m-d'),
                'company_id' => $company_data['id'] ?? 'unknown'
            ]);
        }
        
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
     */
    private function generate_day_time_slots($company_data, $date, $start_time, $end_time, $company_booked_slots = []) {
        $slots = [];
        
        // Parse start and end times
        list($start_hour, $start_min) = explode(':', $start_time);
        list($end_hour, $end_min) = explode(':', $end_time);
        
        $start_minutes = ($start_hour * 60) + $start_min;
        $end_minutes = ($end_hour * 60) + $end_min;
        
        // Get slot duration (default 30 minutes)
        $slot_duration = isset($company_data['time_slot_duration']) ? 
            intval($company_data['time_slot_duration']) : 30;
        
        // Generate slots
        for ($minutes = $start_minutes; $minutes < $end_minutes; $minutes += $slot_duration) {
            $hour = floor($minutes / 60);
            $min = $minutes % 60;
            
            $time_24 = sprintf('%02d:%02d', $hour, $min);
            $time_12 = date('g:i A', strtotime($time_24));
            
            // Check if slot is booked using pre-fetched data (FAST!)
            $slot_key = $date . '_' . $time_24;
            $is_booked = in_array($slot_key, $company_booked_slots);
            
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log("Fast slot check", 'SLOT_CHECK', [
                    'slot_key' => $slot_key,
                    'is_booked' => $is_booked,
                    'method' => 'pre_fetched_array_lookup'
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
        
        return $slots;
    }
    
    /**
     * Test webhook endpoint for debugging Google Sheets integration
     */
    public function test_webhook() {
        // Only allow admin users to test webhook
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $test_data = json_decode(stripslashes($_POST['test_data']), true);
        if (!$test_data) {
            wp_send_json_error('Invalid test data');
            return;
        }
        
        // Get webhook URL from settings
        $integration_settings = get_option('bsp_integration_settings', []);
        $webhook_url = $integration_settings['google_sheets_webhook_url'] ?? '';
        
        if (empty($webhook_url)) {
            wp_send_json_error('Webhook URL not configured');
            return;
        }
        
        // Send test data to webhook
        $response = wp_remote_post($webhook_url, [
            'method'      => 'POST',
            'headers'     => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'        => json_encode($test_data),
            'data_format' => 'body',
            'timeout'     => 15,
            'blocking'    => true,
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Webhook error: ' . $response->get_error_message());
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            wp_send_json_success([
                'message' => 'Webhook test successful',
                'response_code' => $response_code,
                'response_body' => $response_body
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Webhook returned error',
                'response_code' => $response_code,
                'response_body' => $response_body
            ]);
        }
    }
}
?>
