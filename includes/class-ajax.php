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
     * Get company availability for frontend calendar
     */
    public function get_availability() {
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("AJAX get_availability called", 'AJAX', $_POST);
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_frontend_nonce')) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log("AJAX nonce verification failed for get_availability", 'AJAX');
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
        
        // Get companies and generate availability data in the format frontend expects
        $db = BSP_Database_Unified::get_instance();
        $availability_data = [];
        
        foreach ($company_ids as $company_id) {
            $company = $db->get_company($company_id);
            
            if ($company) {
                // Generate availability for date range
                $availability_data[$company_id] = $this->generate_date_range_availability($company, $date_from, $date_to);
            }
        }
        
        wp_send_json_success($availability_data);
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
        
        // If multiple appointments, consolidate the data for the main meta fields
        $appointments_json = $_POST['appointments'] ?? '';
        $appointments = json_decode(stripslashes($appointments_json), true);

        if (!empty($appointments) && is_array($appointments) && count($appointments) > 1) {
            $company_names = array_column($appointments, 'company');
            $dates = array_column($appointments, 'date');
            $times = array_column($appointments, 'time');

            $booking_data['company'] = implode(', ', $company_names);
            $booking_data['selected_date'] = implode(', ', $dates);
            $booking_data['selected_time'] = implode(', ', $times);
        }

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
                '_service_type' => $booking_data['service'],
                '_booking_date' => $booking_data['selected_date'],
                '_booking_time' => $booking_data['selected_time'],
                '_appointments' => $booking_data['appointments'],
                '_specifications' => $this->_generate_specifications_string($_POST),
                '_status' => 'pending',
                '_created_at' => current_time('mysql'),
                // Service-specific fields (keep for detailed data)
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
        
        if (is_wp_error($booking_id)) {
            wp_send_json_error('Failed to create booking: ' . $booking_id->get_error_message());
        }
        
        // Set taxonomies
        wp_set_object_terms($booking_id, 'pending', 'bsp_booking_status');
        wp_set_object_terms($booking_id, $booking_data['service'], 'bsp_service_type');
        
        // Send notifications
        $this->send_booking_notifications($booking_id, $booking_data);
        
        // Send to Google Sheets
        $this->send_to_google_sheets($booking_id, $booking_data);

        wp_send_json_success([
            'message' => 'Booking submitted successfully!',
            'booking_id' => $booking_id
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
     * Get time slots for a specific company and date
     */
    public function get_time_slots() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_frontend_nonce')) {
            wp_send_json_error('Security check failed.');
        }
        
        $company_id = intval($_POST['company_id']);
        $date = sanitize_text_field($_POST['date']);
        
        if (!$company_id || !$date) {
            wp_send_json_error('Missing required parameters.');
        }
        
        // Get company
        $db = BSP_Database_Unified::get_instance();
        $company = $db->get_company($company_id);
        
        if (!$company) {
            wp_send_json_error('Company not found.');
        }
        
        // Generate time slots
        $slots = $this->generate_time_slots($company, $date);
        
        wp_send_json_success($slots);
    }
    
    /**
     * Generate availability for a company on a specific date
     */
    private function generate_availability($company, $date) {
        // Parse available hours (e.g., "9:00 AM - 5:00 PM")
        $hours = $company->available_hours ?? '9:00 AM - 5:00 PM';
        
        // Generate hourly slots (simplified)
        $slots = [];
        $start_hour = 9;
        $end_hour = 17;
        
        for ($hour = $start_hour; $hour < $end_hour; $hour++) {
            $time_24 = sprintf('%02d:00', $hour);
            $time_12 = date('g:i A', strtotime($time_24));
            
            // Check if slot is booked
            $is_booked = $this->is_slot_booked($company->id, $date, $time_24);
            
            $slots[] = [
                'time' => $time_24,
                'display' => $time_12,
                'available' => !$is_booked
            ];
        }
        
        return $slots;
    }
    
    /**
     * Generate time slots for company
     */
    private function generate_time_slots($company, $date) {
        return $this->generate_availability($company, $date);
    }
    
    /**
     * Check if a time slot is booked
     */
    private function is_slot_booked($company_id, $date, $time) {
        $bookings = get_posts([
            'post_type' => 'bsp_booking',
            'posts_per_page' => 1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_company_id',
                    'value' => $company_id,
                    'compare' => '='
                ],
                [
                    'key' => '_booking_date',
                    'value' => $date,
                    'compare' => '='
                ],
                [
                    'key' => '_booking_time',
                    'value' => $time,
                    'compare' => '='
                ]
            ]
        ]);
        
        return !empty($bookings);
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
            $this->send_admin_notification($booking_data);
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
     * Send admin notification email
     */
    private function send_admin_notification($booking_data) {
        $email_settings = get_option('bsp_email_settings', []);
        $admin_email = $email_settings['admin_email'] ?? get_option('admin_email');
        
        $subject = 'New Booking - ' . $booking_data['service'];
        $message = sprintf(
            "New booking received!\n\nCustomer: %s\nEmail: %s\nPhone: %s\nService: %s\nDate: %s\nTime: %s\nCompany: %s\nAddress: %s",
            $booking_data['full_name'],
            $booking_data['email'],
            $booking_data['phone'],
            $booking_data['service'],
            $booking_data['selected_date'],
            $booking_data['selected_time'],
            $booking_data['company'],
            $booking_data['address']
        );
        
        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Send booking data to Google Sheets
     */
    private function send_to_google_sheets($booking_id, $booking_data) {
        $integration_settings = get_option('bsp_integration_settings', []);

        if (empty($integration_settings['google_sheets_enabled']) || empty($integration_settings['google_sheets_webhook_url'])) {
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Google Sheets sync disabled or webhook URL not set.', 'INTEGRATION');
            }
            return;
        }

        // --- DEBUG: Log raw POST and booking_data for troubleshooting ---
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('Google Sheets Integration: Raw $_POST', 'INTEGRATION', $_POST);
            bsp_debug_log('Google Sheets Integration: booking_data', 'INTEGRATION', $booking_data);
        }

        // --- Fallback: If source_data is empty, try to extract from $_POST directly ---
        $utm_params = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'referrer'];
        $source_data = $booking_data['source_data'] ?? [];
        if (empty($source_data) || !is_array($source_data)) {
            $source_data = [];
            foreach ($utm_params as $param) {
                if (!empty($_POST[$param])) {
                    $source_data[$param] = sanitize_text_field($_POST[$param]);
                }
            }
            if (function_exists('bsp_debug_log')) {
                bsp_debug_log('Google Sheets Integration: Fallback source_data from $_POST', 'INTEGRATION', $source_data);
            }
        }

        $webhook_url = $integration_settings['google_sheets_webhook_url'];
        $source_data = $booking_data['source_data'] ?? $source_data;

        // Handle multiple appointments - consolidate into single row
        $appointments_data = $booking_data['appointments'] ?? '';
        $company_names = [];
        $appointment_dates = [];
        $appointment_times = [];

        if (!empty($appointments_data)) {
            $appointments = json_decode($appointments_data, true);
            if (is_array($appointments)) {
                foreach ($appointments as $appointment) {
                    if (is_array($appointment)) {
                        $company_names[] = $appointment['company'] ?? 'Unknown Company';
                        $appointment_dates[] = isset($appointment['date']) ? date('Y-m-d', strtotime($appointment['date'])) : '';
                        $appointment_times[] = isset($appointment['time']) ? date('H:i', strtotime($appointment['time'])) : '';
                    }
                }
            }
        }

        // Fallback to single appointment data if no multiple appointments
        if (empty($company_names)) {
            $company_names[] = $booking_data['company'] ?? '';
            $appointment_dates[] = $booking_data['selected_date'] ?? '';
            $appointment_times[] = $booking_data['selected_time'] ?? '';
        }

        // Consolidate multiple appointments into comma-separated strings
        $consolidated_companies = implode(', ', array_filter($company_names));
        $consolidated_dates = implode(', ', array_filter($appointment_dates));
        $consolidated_times = implode(', ', array_filter($appointment_times));

        // ZIP code fallback logic for Google Sheets
        $zip_code = $booking_data['zip_code'] ?? '';
        if (empty($zip_code)) {
            $zip_fields = ['roof_zip', 'windows_zip', 'bathroom_zip', 'siding_zip', 'kitchen_zip', 'decks_zip'];
            foreach ($zip_fields as $field) {
                if (!empty($booking_data[$field])) {
                    $zip_code = $booking_data[$field];
                    break;
                }
            }
        }

        // Debug log for source tracking
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('Source tracking POST data:', 'INTEGRATION', $_POST);
            bsp_debug_log('Source tracking booking_data:', 'INTEGRATION', $booking_data);
        }


        // Map internal field names to readable column names
        $field_map = [
            'booking_id'        => 'Booking ID',
            'status'            => 'Status',
            'customer_name'     => 'Customer Name',
            'customer_email'    => 'Customer Email',
            'customer_phone'    => 'Customer Phone',
            'customer_address'  => 'Customer Address',
            // All zip fields map to one column
            'zip_code'          => 'ZIP Code',
            'roof_zip'          => 'ZIP Code',
            'windows_zip'       => 'ZIP Code',
            'bathroom_zip'      => 'ZIP Code',
            'siding_zip'        => 'ZIP Code',
            'kitchen_zip'       => 'ZIP Code',
            'decks_zip'         => 'ZIP Code',
            'city'              => 'City',
            'state'             => 'State',
            'service'           => 'Service',
            'company'           => 'Company',
            'date'              => 'Date',
            'time'              => 'Time',
            // UTM/marketing fields
            'utm_source'        => 'UTM Source',
            'utm_medium'        => 'UTM Medium',
            'utm_campaign'      => 'UTM Campaign',
            'utm_term'          => 'UTM Term',
            'utm_content'       => 'UTM Content',
            'gclid'             => 'GCLID',
            'referrer'          => 'Referrer',
            // Service-specific fields (clear names)
            'roof_action'           => 'Roof Action',
            'roof_material'         => 'Roof Material',
            'windows_action'        => 'Windows Action',
            'windows_replace_qty'   => 'Windows Replace Qty',
            'windows_repair_needed' => 'Windows Repair Needed',
            'bathroom_option'       => 'Bathroom Option',
            'siding_option'         => 'Siding Option',
            'siding_material'       => 'Siding Material',
            'kitchen_action'        => 'Kitchen Action',
            'kitchen_component'     => 'Kitchen Component',
            'decks_action'          => 'Decks Action',
            'decks_material'        => 'Decks Material',
        ];

        // Gather all possible fields (including all zip fields)
        $all_fields = [
            'booking_id'        => $booking_id,
            'status'            => 'pending',
            'customer_name'     => $booking_data['full_name'] ?? '',
            'customer_email'    => $booking_data['email'] ?? '',
            'customer_phone'    => $booking_data['phone'] ?? '',
            'customer_address'  => $booking_data['address'] ?? '',
            // Use the first non-empty zip field for ZIP Code
            'zip_code'          => $zip_code,
            'roof_zip'          => $booking_data['roof_zip'] ?? '',
            'windows_zip'       => $booking_data['windows_zip'] ?? '',
            'bathroom_zip'      => $booking_data['bathroom_zip'] ?? '',
            'siding_zip'        => $booking_data['siding_zip'] ?? '',
            'kitchen_zip'       => $booking_data['kitchen_zip'] ?? '',
            'decks_zip'         => $booking_data['decks_zip'] ?? '',
            'city'              => $booking_data['city'] ?? '',
            'state'             => $booking_data['state'] ?? '',
            'service'           => $booking_data['service'] ?? '',
            'company'           => $consolidated_companies,
            'date'              => $consolidated_dates,
            'time'              => $consolidated_times,
            // UTM/marketing fields
            'utm_source'        => $source_data['utm_source'] ?? '',
            'utm_medium'        => $source_data['utm_medium'] ?? '',
            'utm_campaign'      => $source_data['utm_campaign'] ?? '',
            'utm_term'          => $source_data['utm_term'] ?? '',
            'utm_content'       => $source_data['utm_content'] ?? '',
            'gclid'             => $source_data['gclid'] ?? '',
            'referrer'          => $source_data['referrer'] ?? '',
            // Service-specific fields
            'roof_action'           => $booking_data['roof_action'] ?? '',
            'roof_material'         => $booking_data['roof_material'] ?? '',
            'windows_action'        => $booking_data['windows_action'] ?? '',
            'windows_replace_qty'   => $booking_data['windows_replace_qty'] ?? '',
            'windows_repair_needed' => $booking_data['windows_repair_needed'] ?? '',
            'bathroom_option'       => $booking_data['bathroom_option'] ?? '',
            'siding_option'         => $booking_data['siding_option'] ?? '',
            'siding_material'       => $booking_data['siding_material'] ?? '',
            'kitchen_action'        => $booking_data['kitchen_action'] ?? '',
            'kitchen_component'     => $booking_data['kitchen_component'] ?? '',
            'decks_action'          => $booking_data['decks_action'] ?? '',
            'decks_material'        => $booking_data['decks_material'] ?? ''
        ];

        // Only include non-empty fields, and map to readable names
        $payload = [];
        $zip_written = false;
        foreach ($all_fields as $key => $value) {
            if ($value === '' || $value === null) continue;
            $column = $field_map[$key] ?? $key;
            // For ZIP Code, only write the first non-empty zip field
            if ($column === 'ZIP Code') {
                if ($zip_written) continue;
                $zip_written = true;
            }
            $payload[$column] = $value;
        }

        if (function_exists('bsp_debug_log')) {
            bsp_debug_log('Google Sheets Payload:', 'INTEGRATION', $payload);
        }

        // Send the single consolidated request
        $response = wp_remote_post($webhook_url, [
            'method'      => 'POST',
            'headers'     => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'        => json_encode($payload),
            'data_format' => 'body',
            'timeout'     => 15,
            'blocking'    => true,
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
                bsp_debug_log('Google Sheets API Response:', 'INTEGRATION', [
                    'code' => $response_code,
                    'body' => $response_body
                ]);
            }
        }
    }
    
    /**
     * Generate availability for a date range in the format expected by frontend
     */
    private function generate_date_range_availability($company, $date_from, $date_to) {
        $availability = [];
        
        // Convert company data to array if it's an object
        $company_data = is_object($company) ? (array) $company : $company;
        
        // Parse start and end times from database format
        $start_time = isset($company_data['available_hours_start']) ? 
            substr($company_data['available_hours_start'], 0, 5) : '09:00';
        $end_time = isset($company_data['available_hours_end']) ? 
            substr($company_data['available_hours_end'], 0, 5) : '17:00';
        
        // Generate dates between date_from and date_to
        $current_date = new DateTime($date_from);
        $end_date = new DateTime($date_to);
        
        while ($current_date <= $end_date) {
            $date_str = $current_date->format('Y-m-d');
            $day_of_week = $current_date->format('N'); // 1 = Monday, 7 = Sunday
            
            // Check if company is available on this day (assuming all days for now)
            $available_days = isset($company_data['available_days']) ? 
                explode(',', $company_data['available_days']) : [1,2,3,4,5,6,7];
            
            if (in_array($day_of_week, $available_days)) {
                $slots = $this->generate_day_time_slots($company_data, $date_str, $start_time, $end_time);
                
                // Format exactly as frontend expects
                $availability[$date_str] = [
                    'slots' => $slots,
                    'day_number' => $current_date->format('j'),
                    'day_name' => $current_date->format('D'),
                    'full_date' => $current_date->format('l, F j, Y')
                ];
            }
            
            $current_date->add(new DateInterval('P1D'));
        }
        
        return $availability;
    }
    
    /**
     * Generate time slots for a specific day
     */
    private function generate_day_time_slots($company_data, $date, $start_time, $end_time) {
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
            
            // Check if slot is booked
            $company_id = isset($company_data['id']) ? $company_data['id'] : 0;
            $is_booked = $this->is_slot_booked($company_id, $date, $time_24);
            
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
