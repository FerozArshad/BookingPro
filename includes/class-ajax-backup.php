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
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("AJAX endpoints registered: bsp_get_availability, bsp_submit_booking, bsp_get_slots", 'AJAX');
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
     * Submit booking from frontend - Enhanced to capture all form data
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
        
        // Sanitize basic data
        $booking_data = [
            'service' => sanitize_text_field($_POST['service']),
            'full_name' => sanitize_text_field($_POST['full_name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'address' => sanitize_textarea_field($_POST['address']),
            'zip_code' => sanitize_text_field($_POST['zip_code'] ?? ''),
            'company' => sanitize_text_field($_POST['company']),
            'selected_date' => sanitize_text_field($_POST['selected_date']),
            'selected_time' => sanitize_text_field($_POST['selected_time']),
            'appointments' => sanitize_text_field($_POST['appointments'] ?? ''),
            'service_details' => sanitize_textarea_field($_POST['service_details'] ?? '')
        ];
        
        // Capture ALL service-specific form selections
        $service_specific_data = [];
        foreach ($_POST as $key => $value) {
            // Capture service-specific fields (roof_, windows_, bathroom_, etc.)
            if (preg_match('/^(roof_|windows_|bathroom_|siding_|kitchen_|decks_)/', $key)) {
                $service_specific_data[$key] = sanitize_text_field($value);
            }
        }
        
        // Validate email
        if (!is_email($booking_data['email'])) {
            wp_send_json_error('Invalid email address.');
        }
        
        // Create comprehensive meta input array
        $meta_input = [
            '_customer_name' => $booking_data['full_name'],
            '_customer_email' => $booking_data['email'],
            '_customer_phone' => $booking_data['phone'],
            '_customer_address' => $booking_data['address'],
            '_zip_code' => $booking_data['zip_code'],
            '_company_name' => $booking_data['company'],
            '_service_type' => $booking_data['service'],
            '_booking_date' => $booking_data['selected_date'],
            '_booking_time' => $booking_data['selected_time'],
            '_appointments' => $booking_data['appointments'],
            '_status' => 'pending',
            '_created_at' => current_time('mysql')
        ];
        
        // Add all service-specific selections to meta
        foreach ($service_specific_data as $key => $value) {
            $meta_input['_bsp_' . $key] = $value;
        }
        
        // Create booking post
        $post_data = [
            'post_title' => sprintf('Booking - %s - %s', $booking_data['full_name'], $booking_data['selected_date']),
            'post_content' => $booking_data['service_details'],
            'post_status' => 'publish',
            'post_type' => 'bsp_booking',
            'meta_input' => $meta_input
        ];
        
        $booking_id = wp_insert_post($post_data);
        
        if (is_wp_error($booking_id)) {
            wp_send_json_error('Failed to create booking: ' . $booking_id->get_error_message());
        }
        
        // Set taxonomies
        wp_set_object_terms($booking_id, 'pending', 'bsp_booking_status');
        wp_set_object_terms($booking_id, $booking_data['service'], 'bsp_service_type');
        
        // Send enhanced notifications with all data
        $this->send_enhanced_booking_notifications($booking_id);
        
        wp_send_json_success([
            'message' => 'Booking submitted successfully!',
            'booking_id' => $booking_id
        ]);
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
     * Send enhanced booking notifications using the new email system
     */
    private function send_enhanced_booking_notifications($booking_id) {
        // Use the enhanced email class which will automatically trigger
        // when the booking post is created via the 'wp_insert_post' hook
        $email_service = BSP_Email::get_instance();
        $email_service->send_booking_emails($booking_id);
    }
    
    /**
     * Send booking notifications (legacy method - kept for backward compatibility)
     */
    private function send_booking_notifications($booking_id, $booking_data) {
        // Fallback to legacy method if enhanced email fails
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
     * Send customer confirmation email (legacy method)
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
     * Send admin notification email (legacy method)
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
}
?>
