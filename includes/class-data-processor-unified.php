<?php
/**
 * Unified Data Processor - Centralizes all data processing for leads and bookings
 * Extends existing Lead Data Collector to avoid code duplication
 */

if (!defined('ABSPATH')) exit;

class BSP_Data_Processor_Unified {
    
    private static $instance = null;
    private $lead_collector;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Lazy load the Lead Data Collector when needed
        bsp_debug_log("Unified Data Processor initialized", 'DATA_PROCESSOR');
    }
    
    /**
     * Get Lead Data Collector instance (lazy loading)
     */
    private function get_lead_collector() {
        if (null === $this->lead_collector && class_exists('BSP_Lead_Data_Collector')) {
            $this->lead_collector = BSP_Lead_Data_Collector::get_instance();
        }
        return $this->lead_collector;
    }
    
    /**
     * Centralized data sanitization using existing Lead Collector methods
     * This avoids duplicating sanitization logic
     */
    public function sanitize_form_data($raw_data, $data_type = 'lead') {
        $lead_collector = $this->get_lead_collector();
        if (!$lead_collector) {
            bsp_debug_log("Lead collector not available for sanitization", 'ERROR');
            return $this->fallback_sanitize($raw_data);
        }
        
        // Use the existing sanitize_lead_data method via reflection to avoid duplication
        $reflection = new ReflectionClass($lead_collector);
        $method = $reflection->getMethod('sanitize_lead_data');
        $method->setAccessible(true);
        
        $sanitized = $method->invokeArgs($lead_collector, [$raw_data]);
        
        // Add any additional fields specific to bookings if needed
        if ($data_type === 'booking') {
            $sanitized = $this->add_booking_specific_fields($sanitized, $raw_data);
        }
        
        return $sanitized;
    }
    
    /**
     * Fallback sanitization when Lead Collector is not available
     */
    private function fallback_sanitize($raw_data) {
        $sanitized = [];
        
        foreach ($raw_data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = array_map('sanitize_text_field', $value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Add booking-specific fields that aren't in the lead capture
     */
    private function add_booking_specific_fields($sanitized, $raw_data) {
        // Booking-specific fields
        $booking_fields = [
            'selected_date', 'selected_time', 'appointments', 'service_details'
        ];
        
        foreach ($booking_fields as $field) {
            if (isset($raw_data[$field]) && !empty($raw_data[$field])) {
                if ($field === 'appointments') {
                    // Handle appointments JSON specially
                    $sanitized[$field] = $this->sanitize_appointments_data($raw_data[$field]);
                } else {
                    $sanitized[$field] = sanitize_textarea_field($raw_data[$field]);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize appointments JSON data
     */
    private function sanitize_appointments_data($appointments_raw) {
        if (is_string($appointments_raw)) {
            $appointments = json_decode(stripslashes($appointments_raw), true);
        } else {
            $appointments = $appointments_raw;
        }
        
        if (!is_array($appointments)) {
            return '';
        }
        
        $sanitized_appointments = [];
        foreach ($appointments as $appointment) {
            if (is_array($appointment)) {
                $sanitized_appointments[] = [
                    'company' => sanitize_text_field($appointment['company'] ?? ''),
                    'date' => sanitize_text_field($appointment['date'] ?? ''),
                    'time' => sanitize_text_field($appointment['time'] ?? ''),
                    'service' => sanitize_text_field($appointment['service'] ?? '')
                ];
            }
        }
        
        return json_encode($sanitized_appointments);
    }
    
    /**
     * Generate service details using existing Lead Collector method
     */
    public function generate_service_details($data) {
        $lead_collector = $this->get_lead_collector();
        if (!$lead_collector) {
            bsp_debug_log("Lead collector not available for service details", 'ERROR');
            return '';
        }
        
        $reflection = new ReflectionClass($lead_collector);
        $method = $reflection->getMethod('generate_service_details');
        $method->setAccessible(true);
        
        return $method->invokeArgs($lead_collector, [$data]);
    }
    
    /**
     * Centralized data formatting for external systems (Google Sheets, etc.)
     */
    public function format_for_external_system($data, $system_type = 'google_sheets') {
        switch ($system_type) {
            case 'google_sheets':
                return $this->format_for_google_sheets($data);
            case 'webhook':
                return $this->format_for_webhook($data);
            case 'email':
                return $this->format_for_email($data);
            default:
                return $data;
        }
    }
    
    /**
     * Format data specifically for Google Sheets
     */
    private function format_for_google_sheets($data) {
        $timestamp = current_time('Y-m-d H:i:s');
        
        // Base structure for Google Sheets
        $formatted = [
            'timestamp' => $data['capture_timestamp'] ?? $timestamp,
            'session_id' => $data['session_id'] ?? '',
            'lead_type' => $this->determine_lead_type($data),
            'service' => $data['service'] ?? $data['service_type'] ?? '',
            'zip_code' => $data['zip_code'] ?? '',
            'customer_name' => $data['full_name'] ?? $data['customer_name'] ?? '',
            'customer_email' => $data['email'] ?? $data['customer_email'] ?? '',
            'customer_phone' => $data['phone'] ?? $data['customer_phone'] ?? '',
            'customer_address' => $data['customer_address'] ?? $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
            'company' => $data['company'] ?? $data['company_name'] ?? '',
            'booking_date' => $data['booking_date'] ?? $data['selected_date'] ?? '',
            'booking_time' => $data['booking_time'] ?? $data['selected_time'] ?? ''
        ];
        
        // UTM and marketing data
        $utm_fields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'referrer'];
        foreach ($utm_fields as $field) {
            $formatted[$field] = $data[$field] ?? '';
        }
        
        // Service details
        $formatted['service_details'] = $data['service_details'] ?? $this->generate_service_details($data);
        
        // Completion and conversion status
        $formatted['completion_percentage'] = $data['completion_percentage'] ?? $this->calculate_completion_percentage($data);
        $formatted['is_converted'] = isset($data['converted_to_booking']) && $data['converted_to_booking'] ? 'Yes' : 'No';
        
        // Booking ID handling - prioritize actual WordPress post ID for complete bookings
        if (isset($data['booking_id']) && is_numeric($data['booking_id']) && $data['booking_id'] > 0) {
            // Use the actual WordPress booking post ID for complete bookings
            $formatted['booking_id'] = $data['booking_id'];
        } else {
            // Fallback for incomplete leads or legacy data
            $formatted['booking_id'] = $data['booking_post_id'] ?? $data['booking_id'] ?? '';
        }
        
        $formatted['conversion_date'] = $data['conversion_timestamp'] ?? '';
        
        // Debug log to track booking_id handling
        if (isset($data['booking_id'])) {
            bsp_debug_log("Data processor booking_id handling", 'SHEETS_DATA_PROCESSED', [
                'input_booking_id' => $data['booking_id'] ?? 'missing',
                'input_booking_post_id' => $data['booking_post_id'] ?? 'missing',
                'output_booking_id' => $formatted['booking_id'],
                'is_numeric_booking_id' => is_numeric($data['booking_id'] ?? 0),
                'has_converted_flag' => isset($data['converted_to_booking']) && $data['converted_to_booking']
            ]);
        }
        
        // Appointments data (formatted for readability)
        if (!empty($data['appointments'])) {
            $formatted['appointments'] = $this->format_appointments_for_sheets($data['appointments']);
        } else {
            $formatted['appointments'] = $this->format_simple_appointment($data);
        }
        
        // Lead scoring
        $formatted['lead_score'] = $this->calculate_lead_score($data);
        
        // Technical data
        $formatted['traffic_source'] = $data['traffic_source'] ?? $this->determine_traffic_source($data);
        $formatted['ip_address'] = $data['ip_address'] ?? '';
        $formatted['user_agent'] = $data['user_agent'] ?? '';
        $formatted['form_step'] = $data['form_step'] ?? 0;
        $formatted['last_updated'] = $timestamp;
        
        return $formatted;
    }
    
    /**
     * Format appointments for Google Sheets readability
     */
    private function format_appointments_for_sheets($appointments_data) {
        if (is_string($appointments_data)) {
            $appointments = json_decode($appointments_data, true);
        } else {
            $appointments = $appointments_data;
        }
        
        if (!is_array($appointments) || empty($appointments)) {
            return '';
        }
        
        $formatted_appointments = [];
        foreach ($appointments as $appointment) {
            if (is_array($appointment)) {
                $formatted_appointments[] = sprintf(
                    "%s - %s at %s",
                    $appointment['company'] ?? 'Unknown Company',
                    $appointment['date'] ?? 'No Date',
                    $appointment['time'] ?? 'No Time'
                );
            }
        }
        
        return implode('; ', $formatted_appointments);
    }
    
    /**
     * Format simple appointment from basic data
     */
    private function format_simple_appointment($data) {
        $parts = [];
        
        if (!empty($data['company'])) {
            $parts[] = $data['company'];
        }
        
        if (!empty($data['selected_date'])) {
            $parts[] = $data['selected_date'];
        }
        
        if (!empty($data['selected_time'])) {
            $parts[] = $data['selected_time'];
        }
        
        return implode(' - ', $parts);
    }
    
    /**
     * Determine lead type based on data
     */
    private function determine_lead_type($data) {
        if (isset($data['converted_to_booking']) && $data['converted_to_booking']) {
            return 'converted';
        }
        
        if (isset($data['is_complete']) && $data['is_complete']) {
            return 'complete';
        }
        
        if (isset($data['lead_type'])) {
            return $data['lead_type'];
        }
        
        // Determine based on completion percentage
        $completion = $data['completion_percentage'] ?? $this->calculate_completion_percentage($data);
        
        if ($completion >= 100) {
            return 'complete';
        } elseif ($completion >= 50) {
            return 'partial';
        } else {
            return 'incomplete';
        }
    }
    
    /**
     * Calculate completion percentage based on filled fields
     */
    private function calculate_completion_percentage($data) {
        $total_fields = 0;
        $filled_fields = 0;
        
        // Essential fields
        $essential_fields = ['service', 'full_name', 'email', 'phone', 'zip_code'];
        foreach ($essential_fields as $field) {
            $total_fields++;
            if (!empty($data[$field]) || !empty($data[str_replace('full_name', 'customer_name', $field)])) {
                $filled_fields++;
            }
        }
        
        // Optional fields (weighted less)
        $optional_fields = ['address', 'city', 'state', 'company'];
        foreach ($optional_fields as $field) {
            $total_fields += 0.5; // Half weight
            if (!empty($data[$field])) {
                $filled_fields += 0.5;
            }
        }
        
        // Service-specific fields
        $service = $data['service'] ?? $data['service_type'] ?? '';
        $service_fields = $this->get_service_specific_fields($service);
        foreach ($service_fields as $field) {
            $total_fields += 0.3; // Lower weight
            if (!empty($data[$field])) {
                $filled_fields += 0.3;
            }
        }
        
        return $total_fields > 0 ? min(100, round(($filled_fields / $total_fields) * 100)) : 0;
    }
    
    /**
     * Get service-specific fields for completion calculation
     */
    private function get_service_specific_fields($service) {
        $service_fields_map = [
            'Roof' => ['roof_action', 'roof_material'],
            'Windows' => ['windows_action', 'windows_replace_qty'],
            'Bathroom' => ['bathroom_option'],
            'Siding' => ['siding_option', 'siding_material'],
            'Kitchen' => ['kitchen_action', 'kitchen_component'],
            'Decks' => ['decks_action', 'decks_material'],
            'ADU' => ['adu_action', 'adu_type']
        ];
        
        return $service_fields_map[$service] ?? [];
    }
    
    /**
     * Calculate lead score using existing logic
     */
    private function calculate_lead_score($data) {
        $score = 0;
        
        // Base score by service type
        $service_scores = [
            'Roof' => 90, 'ADU' => 95, 'Kitchen' => 85, 'Bathroom' => 80,
            'Siding' => 75, 'Windows' => 70, 'Decks' => 60
        ];
        
        $service = $data['service'] ?? $data['service_type'] ?? '';
        $score += $service_scores[$service] ?? 50;
        
        // UTM source bonus
        $utm_source = strtolower($data['utm_source'] ?? '');
        if (strpos($utm_source, 'google') !== false) {
            $score += 20;
        } elseif (strpos($utm_source, 'facebook') !== false) {
            $score += 15;
        } elseif (!empty($utm_source)) {
            $score += 10;
        }
        
        // Completion percentage bonus
        $completion = $data['completion_percentage'] ?? $this->calculate_completion_percentage($data);
        $score += ($completion / 100) * 20;
        
        // Contact information bonus
        if (!empty($data['email'] ?? $data['customer_email'])) $score += 10;
        if (!empty($data['phone'] ?? $data['customer_phone'])) $score += 10;
        
        return min(100, $score);
    }
    
    /**
     * Determine traffic source from UTM and referrer data
     */
    private function determine_traffic_source($data) {
        $utm_source = strtolower($data['utm_source'] ?? '');
        $utm_medium = strtolower($data['utm_medium'] ?? '');
        $referrer = strtolower($data['referrer'] ?? '');
        
        // Direct UTM classification
        if (!empty($utm_source)) {
            if (strpos($utm_source, 'google') !== false) {
                return strpos($utm_medium, 'cpc') !== false ? 'Google Ads' : 'Google Organic';
            }
            if (strpos($utm_source, 'facebook') !== false) return 'Facebook';
            if (strpos($utm_source, 'bing') !== false) return 'Bing';
            return ucfirst($utm_source);
        }
        
        // Referrer-based classification
        if (!empty($referrer)) {
            if (strpos($referrer, 'google') !== false) return 'Google Organic';
            if (strpos($referrer, 'facebook') !== false) return 'Facebook';
            if (strpos($referrer, 'bing') !== false) return 'Bing';
            return 'Referral';
        }
        
        return 'Direct';
    }
    
    /**
     * Format data for webhook systems
     */
    private function format_for_webhook($data) {
        // Keep original structure but ensure all fields are present
        $formatted = $data;
        $formatted['service_details'] = $formatted['service_details'] ?? $this->generate_service_details($data);
        $formatted['completion_percentage'] = $formatted['completion_percentage'] ?? $this->calculate_completion_percentage($data);
        $formatted['lead_score'] = $this->calculate_lead_score($data);
        $formatted['traffic_source'] = $formatted['traffic_source'] ?? $this->determine_traffic_source($data);
        
        return $formatted;
    }
    
    /**
     * Format data for email notifications
     */
    private function format_for_email($data) {
        return [
            'customer_info' => [
                'name' => $data['full_name'] ?? $data['customer_name'] ?? 'Unknown',
                'email' => $data['email'] ?? $data['customer_email'] ?? 'No email',
                'phone' => $data['phone'] ?? $data['customer_phone'] ?? 'No phone',
                'address' => $data['address'] ?? $data['customer_address'] ?? 'No address'
            ],
            'service_info' => [
                'type' => $data['service'] ?? $data['service_type'] ?? 'Unknown',
                'details' => $data['service_details'] ?? $this->generate_service_details($data),
                'zip_code' => $data['zip_code'] ?? 'Unknown'
            ],
            'marketing_info' => [
                'source' => $data['utm_source'] ?? 'Unknown',
                'medium' => $data['utm_medium'] ?? 'Unknown',
                'campaign' => $data['utm_campaign'] ?? 'Unknown',
                'traffic_source' => $this->determine_traffic_source($data)
            ],
            'lead_info' => [
                'session_id' => $data['session_id'] ?? 'Unknown',
                'completion' => $this->calculate_completion_percentage($data) . '%',
                'score' => $this->calculate_lead_score($data),
                'timestamp' => $data['capture_timestamp'] ?? current_time('Y-m-d H:i:s')
            ]
        ];
    }
    
    /**
     * Get all field definitions for external system mapping
     */
    public function get_field_definitions() {
        return [
            'basic_fields' => [
                'service', 'full_name', 'email', 'phone', 'address', 'city', 'state', 'zip_code', 'company'
            ],
            'service_fields' => [
                'roof_action', 'roof_material', 'windows_action', 'windows_replace_qty', 'windows_repair_needed',
                'bathroom_option', 'siding_option', 'siding_material', 'kitchen_action', 'kitchen_component',
                'decks_action', 'decks_material', 'adu_action', 'adu_type'
            ],
            'utm_fields' => [
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'referrer'
            ],
            'booking_fields' => [
                'selected_date', 'selected_time', 'appointments', 'service_details'
            ],
            'meta_fields' => [
                'session_id', 'capture_timestamp', 'ip_address', 'user_agent', 'form_step'
            ]
        ];
    }
}

// Initialize the Unified Data Processor
BSP_Data_Processor_Unified::get_instance();