<?php
/**
 * Data Manager for Booking System Pro
 */

if (!defined('ABSPATH')) exit;

class BSP_Data_Manager {
    
    private static $instance = null;
    private $db;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = BSP_Database_Unified::get_instance();
    }
    
    /**
     * Get formatted booking data for admin display
     * Centralized method to retrieve and format all booking data
     * 
     * @param int $booking_id
     * @return array|false Formatted booking data or false if not found
     */
    public static function get_formatted_booking_data($booking_id) {
        if (!$booking_id) {
            return false;
        }
        
        $post = get_post($booking_id);
        if (!$post || $post->post_type !== 'bsp_booking') {
            return false;
        }
        
        // Get all meta data for the booking
        $meta_data = get_post_meta($booking_id);
        
        // Helper function to get meta value safely
        $get_meta = function($key) use ($meta_data) {
            return isset($meta_data[$key][0]) ? $meta_data[$key][0] : '';
        };
        
        // Prepare formatted booking data
        $booking_data = [
            // Basic info
            'id' => $post->ID,
            'status' => 'pending', // Default, will be overridden below
            'created_at' => $post->post_date,
            'notes' => $post->post_content,
            
            // Customer information
            'customer_name' => $get_meta('_customer_name'),
            'customer_email' => $get_meta('_customer_email'),
            'customer_phone' => $get_meta('_customer_phone'),
            'customer_address' => $get_meta('_customer_address'),
            
            // Location information (new fields)
            'zip_code' => $get_meta('_zip_code'),
            'city' => $get_meta('_city'),
            'state' => $get_meta('_state'),
            
            // Service information
            'service_type' => $get_meta('_service_type'),
            'service_name' => $get_meta('_service_type'), // Alias for compatibility
            'specifications' => $get_meta('_specifications'),
            
            // Company information
            'company_name' => $get_meta('_company_name'),
            'company_id' => $get_meta('_company_name'), // Alias for compatibility
            
            // Appointment information - FIX DATE/TIME BUG
            'booking_date' => $get_meta('_booking_date'),
            'booking_time' => $get_meta('_booking_time'),
            'appointment_date' => $get_meta('_booking_date'), // Alias for compatibility
            'appointment_time' => $get_meta('_booking_time'), // Alias for compatibility
            'appointments' => $get_meta('_appointments'),
            
            // Marketing/tracking information
            'utm_source' => $get_meta('_utm_source'),
            'utm_medium' => $get_meta('_utm_medium'),
            'utm_campaign' => $get_meta('_utm_campaign'),
            'utm_term' => $get_meta('_utm_term'),
            'utm_content' => $get_meta('_utm_content'),
            'referrer' => $get_meta('_referrer'),
            'landing_page' => $get_meta('_landing_page'),
            
            // Legacy service-specific ZIP codes (for backward compatibility)
            'roof_zip' => $get_meta('_roof_zip'),
            'windows_zip' => $get_meta('_windows_zip'),
            'bathroom_zip' => $get_meta('_bathroom_zip'),
            'siding_zip' => $get_meta('_siding_zip'),
            'kitchen_zip' => $get_meta('_kitchen_zip'),
            'decks_zip' => $get_meta('_decks_zip'),
            
            // Service-specific details
            'roof_action' => $get_meta('_roof_action'),
            'roof_material' => $get_meta('_roof_material'),
            'windows_action' => $get_meta('_windows_action'),
            'windows_replace_qty' => $get_meta('_windows_replace_qty'),
            'windows_repair_needed' => $get_meta('_windows_repair_needed'),
            'bathroom_option' => $get_meta('_bathroom_option'),
            'siding_option' => $get_meta('_siding_option'),
            'siding_material' => $get_meta('_siding_material'),
            'kitchen_action' => $get_meta('_kitchen_action'),
            'kitchen_component' => $get_meta('_kitchen_component'),
            'decks_action' => $get_meta('_decks_action'),
            'decks_material' => $get_meta('_decks_material'),
            'adu_action' => $get_meta('_adu_action'),
            'adu_type' => $get_meta('_adu_type'),
        ];
        
        // Get status from taxonomy
        $status_terms = wp_get_post_terms($booking_id, 'bsp_booking_status');
        if (!is_wp_error($status_terms) && !empty($status_terms)) {
            $booking_data['status'] = $status_terms[0]->slug;
        }
        
        // Format dates and times properly - FIXED to handle multiple appointments
        // Handle comma-separated dates/times from multiple appointments
        $booking_date = $booking_data['booking_date'];
        $booking_time = $booking_data['booking_time'];
        
        // Format ALL dates and times for Google Sheets
        if (!empty($booking_date)) {
            if (strpos($booking_date, ',') !== false) {
                // Multiple dates - format each one
                $dates = array_map('trim', explode(',', $booking_date));
                $formatted_dates = [];
                foreach ($dates as $date) {
                    if (!empty($date)) {
                        $formatted_dates[] = date('F j, Y', strtotime($date));
                    }
                }
                $booking_data['formatted_date'] = implode(', ', $formatted_dates);
            } else {
                // Single date
                $booking_data['formatted_date'] = date('F j, Y', strtotime($booking_date));
            }
        } else {
            $booking_data['formatted_date'] = 'Not set';
        }
        
        if (!empty($booking_time)) {
            if (strpos($booking_time, ',') !== false) {
                // Multiple times - format each one
                $times = array_map('trim', explode(',', $booking_time));
                $formatted_times = [];
                foreach ($times as $time) {
                    if (!empty($time)) {
                        $formatted_times[] = date('g:i A', strtotime($time));
                    }
                }
                $booking_data['formatted_time'] = implode(', ', $formatted_times);
            } else {
                // Single time
                $booking_data['formatted_time'] = date('g:i A', strtotime($booking_time));
            }
        } else {
            $booking_data['formatted_time'] = 'Not set';
        }
        
        if (!empty($booking_data['created_at'])) {
            $booking_data['formatted_created'] = date('F j, Y \a\t g:i A', strtotime($booking_data['created_at']));
        }
        
        // Extract UTM data from _marketing_source if individual UTM fields are empty
        if (empty($booking_data['utm_source'])) {
            $marketing_source = $get_meta('_marketing_source');
            if (!empty($marketing_source)) {
                $marketing_data = maybe_unserialize($marketing_source);
                if (is_array($marketing_data)) {
                    $booking_data['utm_source'] = $marketing_data['utm_source'] ?? '';
                    $booking_data['utm_medium'] = $marketing_data['utm_medium'] ?? '';
                    $booking_data['utm_campaign'] = $marketing_data['utm_campaign'] ?? '';
                    $booking_data['utm_term'] = $marketing_data['utm_term'] ?? '';
                    $booking_data['utm_content'] = $marketing_data['utm_content'] ?? '';
                    $booking_data['referrer'] = $marketing_data['referrer'] ?? '';
                    $booking_data['landing_page'] = $marketing_data['landing_page'] ?? '';
                }
            }
        }
        
        // Parse multiple appointments if they exist
        if (!empty($booking_data['appointments'])) {
            $appointments = json_decode($booking_data['appointments'], true);
            if ($appointments && is_array($appointments)) {
                $booking_data['parsed_appointments'] = $appointments;
                $booking_data['has_multiple_appointments'] = true;
            } else {
                $booking_data['has_multiple_appointments'] = false;
            }
        } else {
            $booking_data['has_multiple_appointments'] = false;
        }
        
        return $booking_data;
    }
    
    /**
     * Export bookings data
     */
    public function export_bookings($format = 'csv', $filters = []) {
        $bookings = $this->db->get_bookings($filters);
        
        switch ($format) {
            case 'csv':
                return $this->export_to_csv($bookings);
            case 'json':
                return $this->export_to_json($bookings);
            case 'xml':
                return $this->export_to_xml($bookings);
            default:
                return false;
        }
    }
    
    /**
     * Import bookings data
     */
    public function import_bookings($file_path, $format = 'csv') {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'Import file not found');
        }
        
        switch ($format) {
            case 'csv':
                return $this->import_from_csv($file_path);
            case 'json':
                return $this->import_from_json($file_path);
            default:
                return new WP_Error('unsupported_format', 'Unsupported import format');
        }
    }
    
    /**
     * Export to CSV
     */
    private function export_to_csv($bookings) {
        $csv_data = [];
        
        // CSV headers
        $headers = [
            'ID',
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Service',
            'Company',
            'Date',
            'Time',
            'Status',
            'Created',
            'Notes'
        ];
        $csv_data[] = $headers;
        
        // Data rows
        foreach ($bookings as $booking) {
            $row = [
                $booking['id'],
                $booking['customer_name'],
                $booking['customer_email'],
                $booking['customer_phone'],
                $booking['service_name'],
                $booking['company_name'],
                $booking['appointment_date'],
                $booking['appointment_time'],
                $booking['status'],
                $booking['created_at'],
                $booking['notes']
            ];
            $csv_data[] = $row;
        }
        
        return $this->array_to_csv($csv_data);
    }
    
    /**
     * Export to JSON
     */
    private function export_to_json($bookings) {
        return json_encode($bookings, JSON_PRETTY_PRINT);
    }
    
    /**
     * Export to XML
     */
    private function export_to_xml($bookings) {
        $xml = new SimpleXMLElement('<bookings></bookings>');
        
        foreach ($bookings as $booking) {
            $booking_node = $xml->addChild('booking');
            foreach ($booking as $key => $value) {
                $booking_node->addChild($key, htmlspecialchars($value));
            }
        }
        
        return $xml->asXML();
    }
    
    /**
     * Import from CSV
     */
    private function import_from_csv($file_path) {
        $csv_data = $this->csv_to_array($file_path);
        
        if (empty($csv_data)) {
            return new WP_Error('empty_file', 'CSV file is empty or invalid');
        }
        
        $headers = array_shift($csv_data); // Remove headers
        $imported = 0;
        $errors = [];
        
        foreach ($csv_data as $row_index => $row) {
            $booking_data = array_combine($headers, $row);
            
            // Validate and sanitize data
            $validated_data = $this->validate_booking_data($booking_data);
            
            if (is_wp_error($validated_data)) {
                $errors[] = "Row " . ($row_index + 2) . ": " . $validated_data->get_error_message();
                continue;
            }
            
            // Insert booking
            $result = $this->db->insert_booking($validated_data);
            
            if ($result) {
                $imported++;
            } else {
                $errors[] = "Row " . ($row_index + 2) . ": Failed to insert booking";
            }
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors,
            'total' => count($csv_data)
        ];
    }
    
    /**
     * Import from JSON
     */
    private function import_from_json($file_path) {
        $json_content = file_get_contents($file_path);
        $bookings = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'Invalid JSON file');
        }
        
        $imported = 0;
        $errors = [];
        
        foreach ($bookings as $index => $booking_data) {
            // Validate and sanitize data
            $validated_data = $this->validate_booking_data($booking_data);
            
            if (is_wp_error($validated_data)) {
                $errors[] = "Record " . ($index + 1) . ": " . $validated_data->get_error_message();
                continue;
            }
            
            // Insert booking
            $result = $this->db->insert_booking($validated_data);
            
            if ($result) {
                $imported++;
            } else {
                $errors[] = "Record " . ($index + 1) . ": Failed to insert booking";
            }
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors,
            'total' => count($bookings)
        ];
    }
    
    /**
     * Validate booking data
     */
    private function validate_booking_data($data) {
        $required_fields = ['customer_name', 'customer_email', 'appointment_date', 'appointment_time'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', "Required field '{$field}' is missing");
            }
        }
        
        // Validate email
        if (!is_email($data['customer_email'])) {
            return new WP_Error('invalid_email', 'Invalid email address');
        }
        
        // Validate date
        if (!$this->validate_date($data['appointment_date'])) {
            return new WP_Error('invalid_date', 'Invalid appointment date');
        }
        
        // Validate time
        if (!$this->validate_time($data['appointment_time'])) {
            return new WP_Error('invalid_time', 'Invalid appointment time');
        }
        
        // Sanitize data
        $sanitized_data = [
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_email' => sanitize_email($data['customer_email']),
            'customer_phone' => sanitize_text_field($data['customer_phone'] ?? ''),
            'service_id' => intval($data['service_id'] ?? 0),
            'company_id' => intval($data['company_id'] ?? 0),
            'appointment_date' => sanitize_text_field($data['appointment_date']),
            'appointment_time' => sanitize_text_field($data['appointment_time']),
            'status' => sanitize_text_field($data['status'] ?? 'pending'),
            'notes' => sanitize_textarea_field($data['notes'] ?? '')
        ];
        
        return $sanitized_data;
    }
    
    /**
     * Generate booking reports
     */
    public function generate_report($type, $date_from, $date_to, $filters = []) {
        switch ($type) {
            case 'bookings_summary':
                return $this->get_bookings_summary($date_from, $date_to, $filters);
            case 'revenue_report':
                return $this->get_revenue_report($date_from, $date_to, $filters);
            case 'service_performance':
                return $this->get_service_performance($date_from, $date_to, $filters);
            case 'customer_analytics':
                return $this->get_customer_analytics($date_from, $date_to, $filters);
            default:
                return new WP_Error('invalid_report_type', 'Invalid report type');
        }
    }
    
    /**
     * Get bookings summary
     */
    private function get_bookings_summary($date_from, $date_to, $filters) {
        $bookings = $this->db->get_bookings(array_merge($filters, [
            'date_from' => $date_from,
            'date_to' => $date_to
        ]));
        
        $summary = [
            'total_bookings' => count($bookings),
            'confirmed_bookings' => 0,
            'pending_bookings' => 0,
            'cancelled_bookings' => 0,
            'completed_bookings' => 0
        ];
        
        foreach ($bookings as $booking) {
            switch ($booking['status']) {
                case 'confirmed':
                    $summary['confirmed_bookings']++;
                    break;
                case 'pending':
                    $summary['pending_bookings']++;
                    break;
                case 'cancelled':
                    $summary['cancelled_bookings']++;
                    break;
                case 'completed':
                    $summary['completed_bookings']++;
                    break;
            }
        }
        
        return $summary;
    }
    
    /**
     * Get revenue report
     */
    private function get_revenue_report($date_from, $date_to, $filters) {
        // This would need to be implemented based on pricing structure
        return [
            'total_revenue' => 0,
            'average_booking_value' => 0,
            'revenue_by_service' => [],
            'revenue_by_month' => []
        ];
    }
    
    /**
     * Validate date format
     */
    private function validate_date($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Validate time format
     */
    private function validate_time($time) {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }
    
    /**
     * Convert array to CSV
     */
    private function array_to_csv($array) {
        $output = fopen('php://temp', 'r+');
        
        foreach ($array as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Convert CSV file to array
     */
    private function csv_to_array($file_path) {
        $csv_data = [];
        
        if (($handle = fopen($file_path, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $csv_data[] = $data;
            }
            fclose($handle);
        }
        
        return $csv_data;
    }
}
