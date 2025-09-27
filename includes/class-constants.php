<?php
/**
 * System Constants and Shared Configuration
 * Centralized constants to eliminate code duplication
 */

if (!defined('ABSPATH')) exit;

class BSP_Constants {
    
    /**
     * UTM and tracking parameters used across the system
     */
    const UTM_PARAMETERS = [
        'utm_source',
        'utm_medium', 
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'referrer'
    ];
    
    /**
     * Core customer data fields
     */
    const CUSTOMER_FIELDS = [
        'full_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zip_code'
    ];
    
    /**
     * Service and appointment fields
     */
    const SERVICE_FIELDS = [
        'service',
        'service_type',
        'company',
        'date',
        'time',
        'booking_date',
        'booking_time',
        'appointments'
    ];
    
    /**
     * Database table names (relative to prefix)
     */
    const TABLE_NAMES = [
        'incomplete_leads' => 'bsp_incomplete_leads',
        'bookings' => 'bsp_bookings',
        'companies' => 'bsp_companies'
    ];
    
    /**
     * Log levels for centralized logging
     */
    const LOG_LEVELS = [
        'ERROR' => 1,
        'WARN' => 2,
        'INFO' => 3,
        'DEBUG' => 4
    ];
    
    /**
     * Session and cache timeouts (in seconds)
     */
    const TIMEOUTS = [
        'session_timeout' => 24 * 60 * 60, // 24 hours
        'request_dedup' => 5,              // 5 seconds
        'webhook_cache' => 60,             // 60 seconds
        'background_process' => 5 * 60     // 5 minutes
    ];
    
    /**
     * Data validation patterns
     */
    const VALIDATION_PATTERNS = [
        'email' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
        'phone' => '/^[\d\s\-\(\)\+\.]{10,}$/',
        'zip_code' => '/^\d{5}(-\d{4})?$/'
    ];
    
    /**
     * Get UTM parameters as array
     */
    public static function get_utm_parameters() {
        return self::UTM_PARAMETERS;
    }
    
    /**
     * Get all customer fields
     */
    public static function get_customer_fields() {
        return self::CUSTOMER_FIELDS;
    }
    
    /**
     * Get all service fields
     */
    public static function get_service_fields() {
        return self::SERVICE_FIELDS;
    }
    
    /**
     * Check if field is UTM parameter
     */
    public static function is_utm_parameter($field) {
        return in_array($field, self::UTM_PARAMETERS);
    }
    
    /**
     * Check if field is customer data
     */
    public static function is_customer_field($field) {
        return in_array($field, self::CUSTOMER_FIELDS);
    }
    
    /**
     * Check if field is service/appointment data
     */
    public static function is_service_field($field) {
        return in_array($field, self::SERVICE_FIELDS);
    }
    
    /**
     * Get timeout value by key
     */
    public static function get_timeout($key) {
        return self::TIMEOUTS[$key] ?? 300; // Default 5 minutes
    }
    
    /**
     * Validate data against patterns
     */
    public static function validate($data, $type) {
        if (!isset(self::VALIDATION_PATTERNS[$type])) {
            return false;
        }
        
        return preg_match(self::VALIDATION_PATTERNS[$type], $data);
    }
}