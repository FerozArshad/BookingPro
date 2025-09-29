<?php
/**
 * Centralized Field Mapper for Consistent Data Handling
 * Fixes field naming inconsistencies across the plugin
 */

if (!defined('ABSPATH')) exit;

class BSP_Field_Mapper {
    
    private static $instance = null;
    
    // Master field mapping - single source of truth for ALL plugin data
    private static $field_mappings = [
        // Customer fields
        'customer_name' => ['full_name', 'customer_name', 'name'],
        'customer_email' => ['email', 'customer_email', 'email_address'],
        'customer_phone' => ['phone', 'customer_phone', 'phone_number'],
        'customer_address' => ['address', 'customer_address', 'street_address'],
        
        // Service fields
        'service_type' => ['service', 'service_type', 'selected_service'],
        'service_details' => ['service_details', 'specifications', '_specifications', 'description'],
        
        // Location fields
        'zip_code' => ['zip_code', 'zipcode', 'postal_code', 'bathroom_zip', 'roof_zip', 'windows_zip', 'siding_zip', 'kitchen_zip', 'decks_zip', 'adu_zip'],
        'city' => ['city'],
        'state' => ['state'],
        
        // Booking fields
        'booking_date' => ['selected_date', 'booking_date', 'appointment_date'],
        'booking_time' => ['selected_time', 'booking_time', 'appointment_time'],
        'company_name' => ['company', 'company_name'],
        'company_id' => ['company_id', '_company_id'],
        'appointments' => ['appointments'],
        
        // Lead tracking fields
        'session_id' => ['session_id'],
        'form_step' => ['form_step'],
        'completion_percentage' => ['completion_percentage'],
        'lead_type' => ['lead_type'],
        'is_complete' => ['is_complete'],
        'converted_to_booking' => ['converted_to_booking'],
        'booking_post_id' => ['booking_post_id', 'booking_id'],
        'conversion_timestamp' => ['conversion_timestamp'],
        
        // UTM/Marketing fields
        'utm_source' => ['utm_source'],
        'utm_medium' => ['utm_medium'],
        'utm_campaign' => ['utm_campaign'],
        'utm_term' => ['utm_term'],
        'utm_content' => ['utm_content'],
        'gclid' => ['gclid'],
        'referrer' => ['referrer'],
        'traffic_source' => ['traffic_source'],
        'marketing_source' => ['marketing_source', 'source_data'],
        
        // Service-specific fields - ROOF
        'roof_action' => ['roof_action', '_roof_action'],
        'roof_material' => ['roof_material', '_roof_material'],
        'roof_zip' => ['roof_zip'],
        
        // Service-specific fields - WINDOWS
        'windows_action' => ['windows_action', '_windows_action'],
        'windows_replace_qty' => ['windows_replace_qty', '_windows_replace_qty'],
        'windows_repair_needed' => ['windows_repair_needed', '_windows_repair_needed'],
        'windows_zip' => ['windows_zip'],
        
        // Service-specific fields - BATHROOM
        'bathroom_option' => ['bathroom_option', '_bathroom_option'],
        'bathroom_zip' => ['bathroom_zip'],
        
        // Service-specific fields - SIDING
        'siding_option' => ['siding_option', '_siding_option'],
        'siding_material' => ['siding_material', '_siding_material'],
        'siding_zip' => ['siding_zip'],
        
        // Service-specific fields - KITCHEN
        'kitchen_action' => ['kitchen_action', '_kitchen_action'],
        'kitchen_component' => ['kitchen_component', '_kitchen_component'],
        'kitchen_zip' => ['kitchen_zip'],
        
        // Service-specific fields - DECKS
        'decks_action' => ['decks_action', '_decks_action'],
        'decks_material' => ['decks_material', '_decks_material'],
        'decks_zip' => ['decks_zip'],
        
        // Service-specific fields - ADU
        'adu_action' => ['adu_action', '_adu_action'],
        'adu_type' => ['adu_type', '_adu_type'],
        'adu_zip' => ['adu_zip'],
        
        // Meta fields
        'capture_timestamp' => ['capture_timestamp', 'created_at', '_created_at'],
        'last_updated' => ['last_updated'],
        'user_agent' => ['user_agent'],
        'ip_address' => ['ip_address'],
        'page_url' => ['page_url'],
        
        // Google Sheets specific fields
        'lead_status' => ['lead_status'],
        'created_date' => ['created_date'],
        'lead_score' => ['lead_score']
    ];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Map raw form data to standardized field names
     */
    public static function map_form_data($raw_data) {
        $mapped_data = [];
        
        foreach (self::$field_mappings as $standard_field => $variations) {
            foreach ($variations as $variation) {
                if (isset($raw_data[$variation]) && !empty($raw_data[$variation])) {
                    $mapped_data[$standard_field] = $raw_data[$variation];
                    break; // Use first non-empty match
                }
            }
        }
        
        // Also preserve any unmapped fields that might be important
        foreach ($raw_data as $key => $value) {
            if (!empty($value) && !isset($mapped_data[self::get_standard_field_name($key)])) {
                // If it's not mapped, keep it with original name but log it
                $mapped_data[$key] = $value;
            }
        }
        
        return $mapped_data;
    }
    
    /**
     * Get standardized field name from any variation
     */
    public static function get_standard_field_name($field_name) {
        foreach (self::$field_mappings as $standard => $variations) {
            if (in_array($field_name, $variations)) {
                return $standard;
            }
        }
        return $field_name; // Return original if no mapping found
    }
    
    /**
     * Map data for Google Sheets with all required fields
     */
    public static function map_for_google_sheets($raw_data, $is_complete = false) {
        $mapped = self::map_form_data($raw_data);
        
        // Ensure all Google Sheets required fields are present
        $sheets_data = [
            // Core identification
            'timestamp' => $mapped['capture_timestamp'] ?? date('Y-m-d H:i:s'),
            'session_id' => $mapped['session_id'] ?? '',
            'booking_id' => $mapped['booking_post_id'] ?? '',
            
            // Customer information
            'customer_name' => $mapped['customer_name'] ?? '',
            'customer_email' => $mapped['customer_email'] ?? '',
            'customer_phone' => $mapped['customer_phone'] ?? '',
            'customer_address' => $mapped['customer_address'] ?? '',
            
            // Location
            'zip_code' => $mapped['zip_code'] ?? '',
            'city' => $mapped['city'] ?? '',
            'state' => $mapped['state'] ?? '',
            
            // Service
            'service_type' => $mapped['service_type'] ?? '',
            'service_details' => $mapped['service_details'] ?? '',
            
            // Booking details
            'company_name' => $mapped['company_name'] ?? '',
            'booking_date' => $mapped['booking_date'] ?? '',
            'booking_time' => $mapped['booking_time'] ?? '',
            'appointments' => $mapped['appointments'] ?? '',
            
            // Lead status
            'lead_status' => $is_complete ? 'Complete' : 'In Progress',
            'completion_percentage' => $mapped['completion_percentage'] ?? ($is_complete ? 100 : 0),
            'is_converted' => ($mapped['converted_to_booking'] ?? false) ? 'Yes' : 'No',
            'conversion_date' => $mapped['conversion_timestamp'] ?? '',
            'created_date' => date('m/d/Y'),
            
            // UTM/Marketing
            'utm_source' => $mapped['utm_source'] ?? '',
            'utm_medium' => $mapped['utm_medium'] ?? '',
            'utm_campaign' => $mapped['utm_campaign'] ?? '',
            'utm_term' => $mapped['utm_term'] ?? '',
            'utm_content' => $mapped['utm_content'] ?? '',
            'gclid' => $mapped['gclid'] ?? '',
            'referrer' => $mapped['referrer'] ?? '',
            'traffic_source' => $mapped['traffic_source'] ?? '',
            
            // Service-specific fields
            'roof_action' => $mapped['roof_action'] ?? '',
            'roof_material' => $mapped['roof_material'] ?? '',
            'windows_action' => $mapped['windows_action'] ?? '',
            'windows_replace_qty' => $mapped['windows_replace_qty'] ?? '',
            'windows_repair_needed' => $mapped['windows_repair_needed'] ?? '',
            'bathroom_option' => $mapped['bathroom_option'] ?? '',
            'siding_option' => $mapped['siding_option'] ?? '',
            'siding_material' => $mapped['siding_material'] ?? '',
            'kitchen_action' => $mapped['kitchen_action'] ?? '',
            'kitchen_component' => $mapped['kitchen_component'] ?? '',
            'decks_action' => $mapped['decks_action'] ?? '',
            'decks_material' => $mapped['decks_material'] ?? '',
            'adu_action' => $mapped['adu_action'] ?? '',
            'adu_type' => $mapped['adu_type'] ?? '',
            
            // Meta
            'form_step' => $mapped['form_step'] ?? 0,
            'user_agent' => $mapped['user_agent'] ?? '',
            'ip_address' => $mapped['ip_address'] ?? '',
            'lead_score' => $mapped['lead_score'] ?? 0
        ];
        
        // Remove empty fields to keep payload clean
        return array_filter($sheets_data, function($value) {
            return $value !== '' && $value !== null && $value !== 0;
        });
    }
    
    /**
     * Map WordPress post meta data to standardized format
     */
    public static function map_post_meta_data($post_id) {
        $meta_data = get_post_meta($post_id);
        $mapped_data = [];
        
        foreach ($meta_data as $key => $value) {
            $clean_key = ltrim($key, '_'); // Remove underscore prefix
            $standard_field = self::get_standard_field_name($clean_key);
            $mapped_data[$standard_field] = is_array($value) ? $value[0] : $value;
        }
        
        return $mapped_data;
    }
    
    /**
     * Get all service-specific fields for a given service
     */
    public static function get_service_fields($service_type) {
        $service_fields = [];
        
        switch ($service_type) {
            case 'Roof':
                $service_fields = ['roof_action', 'roof_material', 'roof_zip'];
                break;
            case 'Windows':
                $service_fields = ['windows_action', 'windows_replace_qty', 'windows_repair_needed', 'windows_zip'];
                break;
            case 'Bathroom':
                $service_fields = ['bathroom_option', 'bathroom_zip'];
                break;
            case 'Siding':
                $service_fields = ['siding_option', 'siding_material', 'siding_zip'];
                break;
            case 'Kitchen':
                $service_fields = ['kitchen_action', 'kitchen_component', 'kitchen_zip'];
                break;
            case 'Decks':
                $service_fields = ['decks_action', 'decks_material', 'decks_zip'];
                break;
            case 'ADU':
                $service_fields = ['adu_action', 'adu_type', 'adu_zip'];
                break;
        }
        
        return $service_fields;
    }
    
    /**
     * Validate if data contains minimum meaningful information
     */
    public static function is_valid_lead_data($data) {
        $mapped_data = is_array($data) ? self::map_form_data($data) : $data;
        
        // Ultra-relaxed validation - capture any meaningful interaction
        $meaningful_fields = [
            'customer_email', 'customer_phone', 'customer_name',
            'zip_code', 'service_type',
            'utm_source', 'utm_medium', 'utm_campaign', 'gclid',
            'referrer'
        ];
        
        foreach ($meaningful_fields as $field) {
            if (!empty($mapped_data[$field])) {
                return true;
            }
        }
        
        // Even if just session data exists, it's worth tracking
        if (!empty($mapped_data['session_id'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Calculate completion percentage based on filled fields
     */
    public static function calculate_completion_percentage($data, $service_type = null) {
        $mapped_data = is_array($data) ? self::map_form_data($data) : $data;
        
        // Required fields for complete booking
        $required_fields = [
            'customer_name', 'customer_email', 'customer_phone',
            'zip_code', 'service_type', 'company_name'
        ];
        
        // Add service-specific fields if service type is known
        if ($service_type) {
            $service_fields = self::get_service_fields($service_type);
            $required_fields = array_merge($required_fields, $service_fields);
        }
        
        $filled_count = 0;
        foreach ($required_fields as $field) {
            if (!empty($mapped_data[$field])) {
                $filled_count++;
            }
        }
        
        return round(($filled_count / count($required_fields)) * 100);
    }
    
    /**
     * Get all mapped field names
     */
    public static function get_all_standard_fields() {
        return array_keys(self::$field_mappings);
    }
    
    /**
     * Debug method to show field mapping for troubleshooting
     */
    public static function debug_field_mapping($raw_data) {
        $mapped = self::map_form_data($raw_data);
        
        // Debug information available but not logged in production
        
        return $mapped;
    }
    
    /**
     * Get WordPress post meta key for a standard field
     */
    public static function get_meta_key($standard_field) {
        return '_' . $standard_field;
    }
    
    /**
     * Get Google Sheets field name for a standard field
     */
    public static function get_sheets_field($standard_field) {
        return $standard_field; // Same as standard for simplicity
    }
}

// Make sure the class is available
if (!class_exists('BSP_Field_Mapper')) {
    error_log("BSP_Field_Mapper class not properly loaded!");
}