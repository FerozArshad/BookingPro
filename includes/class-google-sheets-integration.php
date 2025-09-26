<?php
/**
 * Google Sheets Integration for Lead Capture System
 * Phase 2C: Syncs lead data and conversions to Google Sheets
 */

if (!defined('ABSPATH')) exit;

class BSP_Google_Sheets_Integration {
    
    private static $instance = null;
    private $webhook_url;
    private $spreadsheet_id;
    private $data_processor; // Use centralized data processor
    private static $webhook_cache = []; // Prevent duplicate webhooks
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Get configuration from WordPress options - webhook approach using admin settings
        $integration_settings = get_option('bsp_integration_settings', []);
        $this->webhook_url = $integration_settings['google_sheets_webhook_url'] ?? 'https://script.google.com/macros/s/AKfycbzmqDaGnI2yEfclR7PnoPOerY8GbmCGvR7hhBMuLvRLYQ3DCO2ur6j8PZ-MlOucGoxgxA/exec';
        $this->spreadsheet_id = '1DnKHkBYHSlgHX3SxYs4YZonB7G3Ru_OICeAsKgOgMQE'; // Your actual spreadsheet ID
        
        // Use the centralized data processor instead of duplicating code
        $this->data_processor = BSP_Data_Processor_Unified::get_instance();
        
        // Hook into lead capture events
        add_action('bsp_incomplete_lead_captured', [$this, 'sync_incomplete_lead'], 10, 2);
        add_action('bsp_booking_created', [$this, 'sync_converted_lead'], 10, 2);
        add_action('bsp_lead_updated', [$this, 'update_lead_in_sheets'], 10, 2);
        
        // AJAX endpoints for manual sync
        add_action('wp_ajax_bsp_sync_to_sheets', [$this, 'manual_sync_to_sheets']);
        add_action('wp_ajax_bsp_test_sheets_connection', [$this, 'test_sheets_connection']);
        
        // Schedule batch sync
        add_action('bsp_batch_sync_to_sheets', [$this, 'batch_sync_leads']);
        if (!wp_next_scheduled('bsp_batch_sync_to_sheets')) {
            wp_schedule_event(time(), 'hourly', 'bsp_batch_sync_to_sheets');
        }
        
        bsp_debug_log("Google Sheets Integration initialized with webhook approach", 'SHEETS_INTEGRATION', [
            'webhook_url' => !empty($this->webhook_url) ? 'configured' : 'missing',
            'webhook_url_value' => $this->webhook_url,
            'spreadsheet_id' => $this->spreadsheet_id
        ]);
    }
    
    /**
     * Initialize webhook connection instead of Google API client
     */
    private function init_webhook_connection() {
        // For webhook approach, we just need the webhook URL (can be empty for testing)
        // Always return true since webhook is available
        return true;
    }
    
    /**
     * Sync incomplete lead to Google Sheets via webhook using CONSISTENT data formatting
     */
    public function sync_incomplete_lead($lead_id, $lead_data) {
        $session_id = $lead_data['session_id'] ?? 'unknown';
        
        // CRITICAL FIX: Improved deduplication - check if lead already converted
        global $wpdb;
        BSP_Database_Unified::init_tables();
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        $existing_lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s ORDER BY id DESC LIMIT 1",
            $session_id
        ));
        
        // If lead is already converted to booking, stop sending incomplete data
        if ($existing_lead && !empty($existing_lead->booking_post_id)) {
            bsp_debug_log("SKIPPING: Lead already converted to booking", 'SHEETS_SKIP_CONVERTED', [
                'session_id' => $session_id,
                'booking_post_id' => $existing_lead->booking_post_id,
                'lead_type' => $existing_lead->lead_type ?? 'unknown'
            ]);
            return true; // Return success but don't send webhook
        }
        
        // Create improved cache key with form step and completion percentage
        $completion = $lead_data['completion_percentage'] ?? 0;
        $form_step = $lead_data['form_step'] ?? 0;
        $cache_key = 'incomplete_' . $session_id . '_step_' . $form_step . '_completion_' . $completion;
        
        // Check recent webhook cache (prevent duplicate sends within 60 seconds)
        if (isset(self::$webhook_cache[$cache_key])) {
            $time_diff = time() - self::$webhook_cache[$cache_key];
            if ($time_diff < 60) {
                bsp_debug_log("SKIPPING: Duplicate webhook within 60 seconds", 'SHEETS_DUPLICATE_SKIP', [
                    'session_id' => $session_id,
                    'cache_key' => $cache_key,
                    'time_since_last' => $time_diff,
                    'form_step' => $form_step,
                    'completion' => $completion
                ]);
                return true;
            }
        }
        
        // Mark this webhook as sent
        self::$webhook_cache[$cache_key] = time();
        
        // Log the sync attempt with real data
        bsp_debug_log("Google Sheets webhook sync attempt", 'SHEETS_SYNC_ATTEMPT', [
            'lead_id' => $lead_id,
            'session_id' => $session_id,
            'service' => $lead_data['service'] ?? 'unknown',
            'spreadsheet_id' => $this->spreadsheet_id,
            'has_webhook_url' => !empty($this->webhook_url),
            'available_lead_data_keys' => array_keys($lead_data),
            'cache_key' => $cache_key
        ]);
        
        // Use field mapper for consistent data formatting
        if (class_exists('BSP_Field_Mapper')) {
            $mapped_data = BSP_Field_Mapper::map_form_data($lead_data);
            bsp_debug_log("Field mapper applied to lead data", 'SHEETS_MAPPING', [
                'original_keys' => array_keys($lead_data),
                'mapped_keys' => array_keys($mapped_data)
            ]);
        } else {
            $mapped_data = $lead_data; // Fallback if mapper not available
        }
        
        // Use centralized data processor to format real lead data
        $sheet_data = $this->data_processor->format_for_external_system($mapped_data, 'google_sheets');
        
        // Log what the data processor returned
        bsp_debug_log("Data processor output for Google Sheets", 'SHEETS_DATA_PROCESSED', [
            'processed_keys' => array_keys($sheet_data),
            'has_city' => !empty($sheet_data['city']),
            'has_state' => !empty($sheet_data['state']),
            'has_company' => !empty($sheet_data['company']),
            'has_address' => !empty($sheet_data['customer_address'])
        ]);

        // CRITICAL DEBUG: Log service-specific fields from original lead_data
        $service_fields_debug = [];
        $all_service_fields = [
            'roof_action', 'roof_material',
            'windows_action', 'windows_replace_qty', 'windows_repair_needed',
            'bathroom_option',
            'siding_option', 'siding_material', 
            'kitchen_action', 'kitchen_component',
            'decks_action', 'decks_material',
            'adu_action', 'adu_type'
        ];
        
        foreach ($all_service_fields as $field) {
            $service_fields_debug[$field] = $lead_data[$field] ?? 'missing';
        }
        
        bsp_debug_log("Service-specific fields analysis", 'SERVICE_FIELDS_DEBUG', [
            'service_detected' => $lead_data['service'] ?? 'no_service',
            'service_fields' => $service_fields_debug,
            'lead_data_keys' => array_keys($lead_data)
        ]);

        // Create a comprehensive payload matching all Google Sheets columns
        $webhook_payload = [
            // REQUIRED: Core identification
            'session_id' => $sheet_data['session_id'] ?? $lead_data['session_id'] ?? ('session_' . time()),
            'action' => 'incomplete_lead',
            'timestamp' => current_time('mysql'),
            
            // Lead classification
            'lead_type' => 'Incomplete Lead',
            'lead_status' => 'In Progress', 
            'status' => 'In Progress',
            
            // Customer information - use field mapper mappings
            'customer_name' => $sheet_data['customer_name'] ?? $lead_data['customer_name'] ?? $lead_data['name'] ?? '',
            'customer_email' => $sheet_data['customer_email'] ?? $lead_data['customer_email'] ?? $lead_data['email'] ?? '',
            'customer_phone' => $sheet_data['customer_phone'] ?? $lead_data['customer_phone'] ?? $lead_data['phone'] ?? '',
            'customer_address' => $sheet_data['customer_address'] ?? $lead_data['customer_address'] ?? $lead_data['address'] ?? '',
            
            // Location data
            'city' => $sheet_data['city'] ?? $lead_data['city'] ?? '',
            'state' => $sheet_data['state'] ?? $lead_data['state'] ?? '',
            'zip_code' => $sheet_data['zip_code'] ?? $lead_data['zip_code'] ?? '',
            
            // Service information
            'service' => $sheet_data['service'] ?? $lead_data['service'] ?? $this->extract_service_from_lead_data($lead_data) ?? '',
            'specifications' => $sheet_data['service_details'] ?? $lead_data['service_details'] ?? $lead_data['specifications'] ?? '',
            
            // Company and booking info (usually empty for incomplete)
            'company' => $sheet_data['company'] ?? $lead_data['company'] ?? '',
            'booking_id' => '', // Empty for incomplete leads
            'date' => '', // Empty for incomplete leads
            'time' => '', // Empty for incomplete leads
            
            // Progress tracking
            'form_step' => $lead_data['form_step'] ?? 0,
            'completion_percentage' => $sheet_data['completion_percentage'] ?? $lead_data['completion_percentage'] ?? 0,
            'lead_score' => $lead_data['lead_score'] ?? 0,
            
            // UTM/Marketing data - essential for tracking
            'utm_source' => $sheet_data['utm_source'] ?? $lead_data['utm_source'] ?? '',
            'utm_medium' => $sheet_data['utm_medium'] ?? $lead_data['utm_medium'] ?? '',
            'utm_campaign' => $sheet_data['utm_campaign'] ?? $lead_data['utm_campaign'] ?? '',
            'utm_term' => $sheet_data['utm_term'] ?? $lead_data['utm_term'] ?? '',
            'utm_content' => $sheet_data['utm_content'] ?? $lead_data['utm_content'] ?? '',
            'gclid' => $sheet_data['gclid'] ?? $lead_data['gclid'] ?? '',
            'referrer' => $sheet_data['referrer'] ?? $lead_data['referrer'] ?? '',
            
            // Service-specific fields - ALL possible fields
            'roof_action' => $lead_data['roof_action'] ?? '',
            'roof_material' => $lead_data['roof_material'] ?? '',
            'windows_action' => $lead_data['windows_action'] ?? '',
            'windows_replace_qty' => $lead_data['windows_replace_qty'] ?? '',
            'windows_repair_needed' => $lead_data['windows_repair_needed'] ?? '',
            'bathroom_option' => $lead_data['bathroom_option'] ?? '',
            'siding_option' => $lead_data['siding_option'] ?? '',
            'siding_material' => $lead_data['siding_material'] ?? '',
            'kitchen_action' => $lead_data['kitchen_action'] ?? '',
            'kitchen_component' => $lead_data['kitchen_component'] ?? '',
            'decks_action' => $lead_data['decks_action'] ?? '',
            'decks_material' => $lead_data['decks_material'] ?? '',
            'adu_action' => $lead_data['adu_action'] ?? '',
            'adu_type' => $lead_data['adu_type'] ?? '',
            
            // Date tracking
            'created_date' => date('m/d/Y'),
            'last_updated' => current_time('mysql'),
            'conversion_time' => '', // Empty for incomplete leads
            'notes' => ''
        ];
        
        // Send to webhook server
        $result = $this->send_webhook_data($webhook_payload);
        
        if ($result['success']) {
            bsp_debug_log("Successfully sent real lead data to Google Sheets webhook", 'SHEETS_SUCCESS', [
                'lead_id' => $lead_id,
                'session_id' => $lead_data['session_id'] ?? 'unknown',
                'data_sent' => [
                    'service' => $webhook_payload['service'] ?? '',
                    'zip_code' => $webhook_payload['zip_code'] ?? '',
                    'customer_name' => $webhook_payload['customer_name'] ?? '',
                    'customer_email' => $webhook_payload['customer_email'] ?? '',
                    'customer_phone' => $webhook_payload['customer_phone'] ?? ''
                ]
            ]);
            return true;
        } else {
            bsp_debug_log("Failed to send real lead data to Google Sheets webhook", 'SHEETS_ERROR', [
                'lead_id' => $lead_id,
                'error' => $result['error'] ?? 'Unknown error',
                'data_attempted' => array_keys($webhook_payload)
            ]);
            return false;
        }
    }
    
    /**
     * Extract service from lead data using multiple fallback methods
     */
    private function extract_service_from_lead_data($lead_data) {
        // Method 1: Direct service field check
        if (!empty($lead_data['service']) || !empty($lead_data['service_type'])) {
            return ucfirst(strtolower($lead_data['service'] ?? $lead_data['service_type']));
        }
        
        // Method 2: Check referrer URL for service parameter
        if (!empty($lead_data['referrer'])) {
            $parsed_url = parse_url($lead_data['referrer']);
            if (isset($parsed_url['query'])) {
                parse_str($parsed_url['query'], $params);
                if (!empty($params['service'])) {
                    return ucfirst(strtolower($params['service']));
                }
            }
            
            // Method 3: Check URL path for service names
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
        
        // Method 4: Check current page URL parameters if referrer doesn't have it
        if (isset($_GET['service']) && !empty($_GET['service'])) {
            return ucfirst(strtolower($_GET['service']));
        }
        
        // Method 5: Check service-specific fields to determine service type
        $service_indicators = [
            'Roof' => ['roof_action', 'roof_material', 'roof_zip'],
            'Windows' => ['windows_action', 'windows_replace_qty', 'windows_repair_needed', 'windows_zip'],
            'Bathroom' => ['bathroom_option', 'bathroom_zip'],
            'Kitchen' => ['kitchen_action', 'kitchen_component', 'kitchen_zip'],
            'Siding' => ['siding_option', 'siding_material', 'siding_zip'],
            'Decks' => ['decks_action', 'decks_material', 'decks_zip'],
            'ADU' => ['adu_action', 'adu_type', 'adu_zip']
        ];
        
        foreach ($service_indicators as $service => $fields) {
            foreach ($fields as $field) {
                if (!empty($lead_data[$field])) {
                    return $service;
                }
            }
        }
        
        return null;
    }

    /**
     * Send data to webhook server for Google Sheets integration
     */
    private function send_webhook_data($payload) {
        // Log the webhook attempt with current configuration
        bsp_debug_log("Webhook data sending attempt", 'SHEETS_WEBHOOK_ATTEMPT', [
            'webhook_url' => !empty($this->webhook_url) ? 'configured' : 'missing',
            'webhook_url_value' => $this->webhook_url,
            'spreadsheet_id' => $this->spreadsheet_id,
            'payload_keys' => array_keys($payload)
        ]);
        
        // If no webhook URL is configured, use a default or log data locally
        if (empty($this->webhook_url)) {
            bsp_debug_log("No webhook URL configured - logging real data for verification", 'SHEETS_WEBHOOK', [
                'payload' => $payload,
                'spreadsheet_id' => $this->spreadsheet_id,
                'note' => 'Configure webhook URL in admin settings under Integrations tab'
            ]);
            
            // For testing, return success so the flow continues
            return ['success' => true, 'message' => 'Data logged locally (no webhook URL)'];
        }
        
        // Debug: Log exact payload being sent
        bsp_debug_log("WEBHOOK DEBUG - Exact payload being sent", 'WEBHOOK_DEBUG', [
            'payload' => $payload,
            'payload_json' => json_encode($payload),
            'payload_count' => count($payload),
            'session_id_type' => gettype($payload['session_id'] ?? 'missing'),
            'action_value' => $payload['action'] ?? 'missing'
        ]);
        
        // Clean and validate payload for Google Apps Script
        $clean_payload = $this->prepare_apps_script_payload($payload);
        
        // Try form-encoded data first (Google Apps Script prefers this for e.parameter access)
        $args = [
            'method' => 'POST',
            'headers' => [
                'User-Agent' => 'BookingPro-WordPress-Plugin/1.0'
            ],
            'body' => $clean_payload, // WordPress will convert array to form data
            'timeout' => 30,
            'blocking' => true,
            'sslverify' => true
        ];
        
        bsp_debug_log("Sending form-encoded payload to Google Apps Script", 'WEBHOOK_SEND', [
            'clean_payload_keys' => array_keys($clean_payload),
            'payload_count' => count($clean_payload),
            'url' => $this->webhook_url,
            'method' => 'form_encoded'
        ]);
        
        // Send to webhook - try form data first, JSON as fallback
        $response = wp_remote_post($this->webhook_url, $args);
        
        if (is_wp_error($response)) {
            // If form data fails, try JSON format as fallback
            bsp_debug_log("Form data failed, trying JSON fallback", 'WEBHOOK_FALLBACK', [
                'error' => $response->get_error_message()
            ]);
            
            $json_args = [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'BookingPro-WordPress-Plugin/1.0'
                ],
                'body' => json_encode($clean_payload),
                'timeout' => 30,
                'blocking' => true,
                'sslverify' => true
            ];
            
            $response = wp_remote_post($this->webhook_url, $json_args);
            
            if (is_wp_error($response)) {
                bsp_debug_log("Both form and JSON requests failed", 'WEBHOOK_WP_ERROR', [
                    'error' => $response->get_error_message(),
                    'error_code' => $response->get_error_code()
                ]);
                return [
                    'success' => false,
                    'error' => $response->get_error_message()
                ];
            }
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        bsp_debug_log("Webhook response received", 'WEBHOOK_RESPONSE', [
            'response_code' => $response_code,
            'response_body_preview' => substr($response_body, 0, 500),
            'response_headers' => wp_remote_retrieve_headers($response)
        ]);
        
        if ($response_code >= 200 && $response_code < 300) {
            return [
                'success' => true,
                'response' => json_decode($response_body, true)
            ];
        } else {
            return [
                'success' => false,
                'error' => "HTTP {$response_code}: {$response_body}"
            ];
        }
    }

    /**
     * Prepare payload specifically for Google Apps Script expectations
     */
    private function prepare_apps_script_payload($payload) {
        // Clean payload - remove any null values and ensure all values are strings or numbers
        $clean_payload = [];
        
        foreach ($payload as $key => $value) {
            // Skip null or empty keys
            if ($key === '' || $key === null) {
                continue;
            }
            
            // Clean the key name - ensure it's Apps Script compatible
            $clean_key = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
            
            // Handle the value - ensure Apps Script compatibility
            if ($value === null) {
                $clean_payload[$clean_key] = '';
            } elseif (is_bool($value)) {
                $clean_payload[$clean_key] = $value ? '1' : '0';
            } elseif (is_array($value) || is_object($value)) {
                $clean_payload[$clean_key] = json_encode($value);
            } else {
                // Convert to string and clean - handle special characters that might break Apps Script
                $string_value = (string) $value;
                // Remove any characters that might cause Apps Script issues
                $string_value = preg_replace('/[\x00-\x1F\x7F]/', '', $string_value); // Remove control characters
                $string_value = trim($string_value);
                $clean_payload[$clean_key] = $string_value;
            }
        }
        
        // Ensure required fields exist for Google Apps Script
        $required_defaults = [
            'timestamp' => current_time('mysql'),
            'session_id' => $clean_payload['session_id'] ?? ('session_' . time()),
            'action' => $clean_payload['action'] ?? 'incomplete_lead',
            'lead_status' => $clean_payload['lead_status'] ?? 'New'
        ];
        
        foreach ($required_defaults as $key => $default) {
            if (!isset($clean_payload[$key]) || $clean_payload[$key] === '') {
                $clean_payload[$key] = $default;
            }
        }
        
        // Log the cleaning process
        bsp_debug_log("Payload cleaned for Google Apps Script", 'WEBHOOK_CLEAN', [
            'original_count' => count($payload),
            'clean_count' => count($clean_payload),
            'required_fields_added' => array_keys($required_defaults),
            'final_keys' => array_keys($clean_payload)
        ]);
        
        return $clean_payload;
    }

    /**
     * Sync converted lead to Google Sheets via webhook with real data
     */
    public function sync_converted_lead($booking_id, $booking_data) {
        $session_id = $booking_data['session_id'] ?? $this->extract_session_id_from_booking($booking_data);
        
        // CRITICAL: Mark the lead as converted in database to stop incomplete webhooks
        if ($session_id) {
            global $wpdb;
            BSP_Database_Unified::init_tables();
            $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
            
            $wpdb->update(
                $table_name,
                [
                    'booking_post_id' => $booking_id,
                    'converted_to_booking' => 1,
                    'lead_type' => 'Complete',
                    'conversion_timestamp' => current_time('mysql')
                ],
                ['session_id' => $session_id],
                ['%d', '%d', '%s', '%s'],
                ['%s']
            );
            
            bsp_debug_log("Marked lead as converted in database", 'CONVERSION_TRACKING', [
                'booking_id' => $booking_id,
                'session_id' => $session_id,
                'update_result' => 'success'
            ]);
        }
        
        $cache_key = 'complete_' . $booking_id . '_' . $session_id;
        
        // Check if we've already sent this booking data recently
        if (isset(self::$webhook_cache[$cache_key])) {
            $time_diff = time() - self::$webhook_cache[$cache_key];
            if ($time_diff < 300) { // 5 minute window for complete bookings
                bsp_debug_log("SKIPPING: Complete booking already sent recently", 'SHEETS_DUPLICATE_SKIP', [
                    'booking_id' => $booking_id,
                    'session_id' => $session_id,
                    'cache_key' => $cache_key,
                    'time_since_last' => $time_diff
                ]);
                return true;
            }
        }
        
        // Mark this webhook as sent
        self::$webhook_cache[$cache_key] = time();
        
        try {
            bsp_debug_log("Complete booking webhook sync attempt", 'SHEETS_BOOKING_SYNC', [
                'booking_id' => $booking_id,
                'session_id' => $session_id,
                'available_booking_data_keys' => array_keys($booking_data),
                'cache_key' => $cache_key
            ]);
            
            // Get the associated lead data
            if (!$session_id) {
                bsp_debug_log("No session ID found for converted lead", 'SHEETS_WARNING', [
                    'booking_id' => $booking_id,
                    'booking_data_keys' => array_keys($booking_data)
                ]);
                return false;
            }
            
            // Get actual booking data from WordPress post meta (this contains the combined company/date/time data)
            $wp_booking_data = [
                'company' => get_post_meta($booking_id, '_company_name', true) ?: ($booking_data['company'] ?? ''),
                'booking_date' => get_post_meta($booking_id, '_booking_date', true) ?: ($booking_data['selected_date'] ?? ''),
                'booking_time' => get_post_meta($booking_id, '_booking_time', true) ?: ($booking_data['selected_time'] ?? ''),
                'customer_address' => get_post_meta($booking_id, '_customer_address', true) ?: ($booking_data['address'] ?? ''),
                'customer_name' => get_post_meta($booking_id, '_customer_name', true) ?: ($booking_data['full_name'] ?? ''),
                'customer_email' => get_post_meta($booking_id, '_customer_email', true) ?: ($booking_data['email'] ?? ''),
                'customer_phone' => get_post_meta($booking_id, '_customer_phone', true) ?: ($booking_data['phone'] ?? ''),
                'service' => get_post_meta($booking_id, '_service_type', true) ?: ($booking_data['service'] ?? ''),
                'city' => get_post_meta($booking_id, '_city', true) ?: ($booking_data['city'] ?? ''),
                'state' => get_post_meta($booking_id, '_state', true) ?: ($booking_data['state'] ?? ''),
                'zip_code' => get_post_meta($booking_id, '_zip_code', true) ?: ($booking_data['zip_code'] ?? ''),
                'specifications' => get_post_meta($booking_id, '_specifications', true) ?: ($booking_data['service_details'] ?? ''),
                
                // CRITICAL: Extract ALL service-specific fields from post meta
                'roof_action' => get_post_meta($booking_id, '_roof_action', true) ?: ($booking_data['roof_action'] ?? ''),
                'roof_material' => get_post_meta($booking_id, '_roof_material', true) ?: ($booking_data['roof_material'] ?? ''),
                'windows_action' => get_post_meta($booking_id, '_windows_action', true) ?: ($booking_data['windows_action'] ?? ''),
                'windows_replace_qty' => get_post_meta($booking_id, '_windows_replace_qty', true) ?: ($booking_data['windows_replace_qty'] ?? ''),
                'windows_repair_needed' => get_post_meta($booking_id, '_windows_repair_needed', true) ?: ($booking_data['windows_repair_needed'] ?? ''),
                'bathroom_option' => get_post_meta($booking_id, '_bathroom_option', true) ?: ($booking_data['bathroom_option'] ?? ''),
                'siding_option' => get_post_meta($booking_id, '_siding_option', true) ?: ($booking_data['siding_option'] ?? ''),
                'siding_material' => get_post_meta($booking_id, '_siding_material', true) ?: ($booking_data['siding_material'] ?? ''),
                'kitchen_action' => get_post_meta($booking_id, '_kitchen_action', true) ?: ($booking_data['kitchen_action'] ?? ''),
                'kitchen_component' => get_post_meta($booking_id, '_kitchen_component', true) ?: ($booking_data['kitchen_component'] ?? ''),
                'decks_action' => get_post_meta($booking_id, '_decks_action', true) ?: ($booking_data['decks_action'] ?? ''),
                'decks_material' => get_post_meta($booking_id, '_decks_material', true) ?: ($booking_data['decks_material'] ?? ''),
                'adu_action' => get_post_meta($booking_id, '_adu_action', true) ?: ($booking_data['adu_action'] ?? ''),
                'adu_type' => get_post_meta($booking_id, '_adu_type', true) ?: ($booking_data['adu_type'] ?? '')
            ];
            
            bsp_debug_log("WordPress post meta data extracted", 'SHEETS_BOOKING_SYNC', [
                'booking_id' => $booking_id,
                'company' => $wp_booking_data['company'],
                'booking_date' => $wp_booking_data['booking_date'],
                'booking_time' => $wp_booking_data['booking_time'],
                'customer_address' => $wp_booking_data['customer_address'],
                'service_fields_extracted' => [
                    'roof_action' => $wp_booking_data['roof_action'] ?: 'empty',
                    'roof_material' => $wp_booking_data['roof_material'] ?: 'empty',
                    'windows_action' => $wp_booking_data['windows_action'] ?: 'empty',
                    'bathroom_option' => $wp_booking_data['bathroom_option'] ?: 'empty',
                    'adu_action' => $wp_booking_data['adu_action'] ?: 'empty',
                    'adu_type' => $wp_booking_data['adu_type'] ?: 'empty'
                ],
                'specifications' => $wp_booking_data['specifications'] ?: 'empty'
            ]);
            
            // Merge WordPress booking data with original booking data and session data
            $enhanced_booking_data = array_merge($booking_data, $wp_booking_data);
            
            // Merge booking data with existing lead data if available
            $complete_data = $this->merge_booking_with_lead_data($booking_id, $enhanced_booking_data, $session_id);
            
            // Use centralized processor for consistent formatting
            $sheet_data = $this->data_processor->format_for_external_system($complete_data, 'google_sheets');
            
            // Add conversion-specific fields and spreadsheet ID with proper booking ID
            $webhook_payload = array_merge($sheet_data, [
                'spreadsheet_id' => $this->spreadsheet_id,
                'action' => 'complete_booking', // Mark as complete booking
                'booking_id' => $booking_id, // Use actual WordPress booking ID
                'session_id' => $session_id,
                'lead_status' => 'Complete',
                'completion_percentage' => 100,
                'converted_to_booking' => 1,
                'conversion_timestamp' => current_time('mysql'),
                'booking_date' => $complete_data['booking_date'] ?: $complete_data['selected_date'] ?: '',
                'booking_time' => $complete_data['booking_time'] ?: $complete_data['selected_time'] ?: '',
                'company' => $complete_data['company'] ?: '',
                'customer_address' => $complete_data['customer_address'] ?: $complete_data['address'] ?: '',
                'city' => $complete_data['city'] ?: '',
                'state' => $complete_data['state'] ?: '',
                'created_date' => date('m/d/Y')
            ]);
            
            // Override with properly formatted data from external system processor
            if (isset($sheet_data['customer_address'])) {
                $webhook_payload['customer_address'] = $sheet_data['customer_address'];
            }
            if (isset($sheet_data['company'])) {
                $webhook_payload['company'] = $sheet_data['company'];
            }
            if (isset($sheet_data['booking_date'])) {
                $webhook_payload['booking_date'] = $sheet_data['booking_date'];
            }
            if (isset($sheet_data['booking_time'])) {
                $webhook_payload['booking_time'] = $sheet_data['booking_time'];
            }
            
            bsp_debug_log("Final Google Sheets payload constructed", 'SHEETS_BOOKING_SYNC', [
                'booking_id' => $booking_id,
                'session_id' => $session_id,
                'payload_keys' => array_keys($webhook_payload),
                'company' => $webhook_payload['company'],
                'booking_date' => $webhook_payload['booking_date'],
                'booking_time' => $webhook_payload['booking_time'],
                'customer_address' => $webhook_payload['customer_address'],
                'lead_status' => $webhook_payload['lead_status']
            ]);
            
            // Create comprehensive complete booking payload matching ALL Google Sheets columns
            $streamlined_payload = [
                // REQUIRED: Core identification
                'session_id' => $session_id,
                'booking_id' => $booking_id, // WordPress post ID
                'action' => 'complete_booking',
                'timestamp' => current_time('mysql'),
                
                // Lead classification for complete booking
                'lead_type' => 'Complete Booking',
                'lead_status' => 'Complete',
                'status' => 'Converted',
                
                // Customer information - comprehensive mapping
                'customer_name' => $webhook_payload['customer_name'] ?? '',
                'customer_email' => $webhook_payload['customer_email'] ?? '',
                'customer_phone' => $webhook_payload['customer_phone'] ?? '',
                'customer_address' => $webhook_payload['customer_address'] ?? '',
                
                // Location information
                'zip_code' => $webhook_payload['zip_code'] ?? '',
                'city' => $webhook_payload['city'] ?? '',
                'state' => $webhook_payload['state'] ?? '',
                
                // Service and booking details
                'service' => $webhook_payload['service'] ?? '',
                'company' => $webhook_payload['company'] ?? '',
                'date' => $webhook_payload['booking_date'] ?? '', // Maps to "Date" column
                'time' => $webhook_payload['booking_time'] ?? '', // Maps to "Time" column
                'specifications' => $webhook_payload['service_details'] ?? $webhook_payload['specifications'] ?? '',
                
                // Conversion tracking
                'converted_to_booking' => 1,
                'completion_percentage' => 100,
                'conversion_time' => current_time('mysql'),
                'created_date' => date('m/d/Y'),
                'last_updated' => current_time('mysql'),
                
                // UTM/Marketing data - ALL fields
                'utm_source' => $webhook_payload['utm_source'] ?? '',
                'utm_medium' => $webhook_payload['utm_medium'] ?? '',
                'utm_campaign' => $webhook_payload['utm_campaign'] ?? '',
                'utm_term' => $webhook_payload['utm_term'] ?? '',
                'utm_content' => $webhook_payload['utm_content'] ?? '',
                'gclid' => $webhook_payload['gclid'] ?? '',
                'referrer' => $webhook_payload['referrer'] ?? '',
                
                // Progress tracking
                'form_step' => $webhook_payload['form_step'] ?? 100, // Complete = step 100
                'lead_score' => $webhook_payload['lead_score'] ?? 100, // Complete = score 100
                
                // Service-specific fields - ALL possible combinations
                'roof_action' => $webhook_payload['roof_action'] ?? '',
                'roof_material' => $webhook_payload['roof_material'] ?? '',
                'windows_action' => $webhook_payload['windows_action'] ?? '',
                'windows_replace_qty' => $webhook_payload['windows_replace_qty'] ?? '',
                'windows_repair_needed' => $webhook_payload['windows_repair_needed'] ?? '',
                'bathroom_option' => $webhook_payload['bathroom_option'] ?? '',
                'siding_option' => $webhook_payload['siding_option'] ?? '',
                'siding_material' => $webhook_payload['siding_material'] ?? '',
                'kitchen_action' => $webhook_payload['kitchen_action'] ?? '',
                'kitchen_component' => $webhook_payload['kitchen_component'] ?? '',
                'decks_action' => $webhook_payload['decks_action'] ?? '',
                'decks_material' => $webhook_payload['decks_material'] ?? '',
                'adu_action' => $webhook_payload['adu_action'] ?? '',
                'adu_type' => $webhook_payload['adu_type'] ?? '',
                
                // Additional tracking
                'notes' => ''
            ];
            
            // Remove empty service-specific fields but keep ALL essential tracking fields
            $essential_fields = [
                'session_id', 'action', 'timestamp', 'lead_type', 'lead_status', 'status', 
                'booking_id', 'completion_percentage', 'created_date', 'last_updated'
            ];
            
            $streamlined_payload = array_filter($streamlined_payload, function($value, $key) use ($essential_fields) {
                // Always keep essential fields even if empty
                if (in_array($key, $essential_fields)) {
                    return true;
                }
                // Remove empty non-essential fields to keep payload clean
                return $value !== '' && $value !== null && $value !== 0;
            }, ARRAY_FILTER_USE_BOTH);
            
            bsp_debug_log("Streamlined payload for Google Sheets", 'SHEETS_BOOKING_SYNC', [
                'booking_id' => $booking_id,
                'session_id' => $session_id,
                'streamlined_keys' => array_keys($streamlined_payload),
                'payload_size' => strlen(json_encode($streamlined_payload)),
                'final_booking_id' => $streamlined_payload['booking_id'],
                'booking_id_matches' => ($streamlined_payload['booking_id'] == $booking_id),
                'is_numeric_booking_id' => is_numeric($streamlined_payload['booking_id'])
            ]);
            
            // Send to webhook server
            $result = $this->send_webhook_data($streamlined_payload);
            
            if ($result['success']) {
                bsp_debug_log("Successfully sent converted lead data to Google Sheets webhook", 'SHEETS_CONVERSION', [
                    'booking_id' => $booking_id,
                    'session_id' => $session_id,
                    'conversion_data' => [
                        'service' => $streamlined_payload['service'] ?? '',
                        'customer_name' => $streamlined_payload['customer_name'] ?? '',
                        'customer_email' => $streamlined_payload['customer_email'] ?? '',
                        'city' => $streamlined_payload['city'] ?? '',
                        'state' => $streamlined_payload['state'] ?? '',
                        'company' => $streamlined_payload['company'] ?? '',
                        'booking_date' => $streamlined_payload['booking_date'] ?? '',
                        'booking_time' => $streamlined_payload['booking_time'] ?? '',
                        'converted' => true
                    ]
                ]);
                return true;
            } else {
                bsp_debug_log("Failed to send converted lead data to Google Sheets webhook", 'SHEETS_ERROR', [
                    'booking_id' => $booking_id,
                    'session_id' => $session_id,
                    'error' => $result['error'] ?? 'Unknown error',
                    'payload_keys_sent' => array_keys($streamlined_payload)
                ]);
                return false;
            }
            
        } catch (Exception $e) {
            bsp_debug_log("Failed to sync converted lead to Google Sheets", 'SHEETS_ERROR', [
                'booking_id' => $booking_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Merge booking data with existing lead data for complete picture
     */
    private function merge_booking_with_lead_data($booking_id, $booking_data, $session_id) {
        global $wpdb;
        BSP_Database_Unified::init_tables();
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        // Get the original lead data
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s ORDER BY created_at DESC LIMIT 1",
            $session_id
        ));
        
        // Start with booking data as base
        $merged_data = $booking_data;
        
        // Add lead data fields if they exist and booking data doesn't have them
        if ($lead) {
            $lead_array = (array) $lead;
            
            // Map database fields to expected field names
            $field_mapping = [
                'service_type' => 'service',
                'customer_name' => 'full_name',
                'customer_email' => 'email',
                'customer_phone' => 'phone',
                'customer_address' => 'address'
            ];
            
            foreach ($lead_array as $key => $value) {
                $mapped_key = $field_mapping[$key] ?? $key;
                
                // Only use lead data if booking data doesn't have this field or it's empty
                if (!isset($merged_data[$mapped_key]) || empty($merged_data[$mapped_key])) {
                    $merged_data[$mapped_key] = $value;
                }
            }
            
            // Ensure conversion tracking fields are set
            $merged_data['converted_to_booking'] = 1;
            $merged_data['booking_post_id'] = $booking_id;
            $merged_data['conversion_timestamp'] = current_time('mysql');
        }
        
        // Ensure session ID is preserved
        $merged_data['session_id'] = $session_id;
        
        return $merged_data;
    }
    
    /**
     * Find lead row in Google Sheets by session ID
     */
    private function find_lead_row($session_id) {
        try {
            $range = 'Leads!A:B'; // Assuming session_id is in column B
            $response = $this->sheets_client->spreadsheets_values->get($this->spreadsheet_id, $range);
            $values = $response->getValues();
            
            if (!$values) {
                return null;
            }
            
            foreach ($values as $index => $row) {
                if (isset($row[1]) && $row[1] === $session_id) {
                    return $index + 1; // Google Sheets rows are 1-indexed
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            bsp_debug_log("Error finding lead row in Google Sheets", 'SHEETS_ERROR', [
                'session_id' => $session_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Add new row to Google Sheets
     */
    private function add_sheet_row($data) {
        try {
            // Prepare values array
            $values = [
                array_values($data)
            ];
            
            $body = new Google_Service_Sheets_ValueRange([
                'values' => $values
            ]);
            
            $params = [
                'valueInputOption' => 'RAW'
            ];
            
            $result = $this->sheets_client->spreadsheets_values->append(
                $this->spreadsheet_id,
                'Leads!A:Z',
                $body,
                $params
            );
            
            bsp_debug_log("Added row to Google Sheets", 'SHEETS_SUCCESS', [
                'updates' => $result->getUpdates()->getUpdatedRows()
            ]);
            
            return true;
            
        } catch (Exception $e) {
            bsp_debug_log("Error adding row to Google Sheets", 'SHEETS_ERROR', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Update existing row in Google Sheets
     */
    private function update_sheet_row($row_number, $data) {
        try {
            $range = "Leads!A{$row_number}:Z{$row_number}";
            
            $values = [
                array_values($data)
            ];
            
            $body = new Google_Service_Sheets_ValueRange([
                'values' => $values
            ]);
            
            $params = [
                'valueInputOption' => 'RAW'
            ];
            
            $result = $this->sheets_client->spreadsheets_values->update(
                $this->spreadsheet_id,
                $range,
                $body,
                $params
            );
            
            bsp_debug_log("Updated row in Google Sheets", 'SHEETS_SUCCESS', [
                'row' => $row_number,
                'updates' => $result->getUpdatedCells()
            ]);
            
            return true;
            
        } catch (Exception $e) {
            bsp_debug_log("Error updating row in Google Sheets", 'SHEETS_ERROR', [
                'row' => $row_number,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Extract session ID from booking data using centralized method
     */
    private function extract_session_id_from_booking($booking_data) {
        // Try various methods to get session ID
        if (isset($booking_data['session_id'])) {
            return $booking_data['session_id'];
        }
        
        // Generate using the same method as Lead Data Collector
        $identifier_parts = [];
        
        $email = $booking_data['email'] ?? $booking_data['customer_email'] ?? '';
        $phone = $booking_data['phone'] ?? $booking_data['customer_phone'] ?? '';
        $service = $booking_data['service'] ?? $booking_data['service_type'] ?? '';
        
        if (!empty($email)) $identifier_parts[] = $email;
        if (!empty($phone)) $identifier_parts[] = $phone;
        if (!empty($service)) $identifier_parts[] = $service;
        
        if (empty($identifier_parts)) {
            return null;
        }
        
        $identifier = implode('|', $identifier_parts);
        return 'bsp_' . hash('sha256', $identifier . date('Y-m-d'));
    }
    
    /**
     * Manual sync to Google Sheets (AJAX endpoint)
     */
    public function manual_sync_to_sheets() {
        check_ajax_referer('bsp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $result = $this->batch_sync_leads(true);
        
        wp_send_json([
            'success' => $result['success'],
            'message' => $result['message'],
            'synced' => $result['synced'] ?? 0,
            'errors' => $result['errors'] ?? 0
        ]);
    }
    
    /**
     * Test Google Sheets connection
     */
    public function test_sheets_connection() {
        check_ajax_referer('bsp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $success = $this->init_sheets_client();
        
        if ($success) {
            try {
                // Try to read the spreadsheet
                $response = $this->sheets_client->spreadsheets_values->get(
                    $this->spreadsheet_id,
                    'Leads!A1'
                );
                
                wp_send_json([
                    'success' => true,
                    'message' => 'Google Sheets connection successful'
                ]);
                
            } catch (Exception $e) {
                wp_send_json([
                    'success' => false,
                    'message' => 'Connection failed: ' . $e->getMessage()
                ]);
            }
        } else {
            wp_send_json([
                'success' => false,
                'message' => 'Failed to initialize Google Sheets client'
            ]);
        }
    }
    
    /**
     * Batch sync leads to Google Sheets
     */
    public function batch_sync_leads($manual = false) {
        if (!$this->init_sheets_client()) {
            return [
                'success' => false,
                'message' => 'Google Sheets client not available'
            ];
        }
        
        global $wpdb;
        BSP_Database_Unified::init_tables();
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        // Get leads that need syncing (not synced in last hour for automatic sync)
        $time_limit = $manual ? '1970-01-01' : date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE (sheets_sync_timestamp IS NULL OR sheets_sync_timestamp < %s)
             ORDER BY created_at DESC 
             LIMIT 50",
            $time_limit
        ));
        
        $synced = 0;
        $errors = 0;
        
        foreach ($leads as $lead) {
            try {
                // Convert lead object to array and use centralized processor
                $lead_array = (array) $lead;
                $sheet_data = $this->data_processor->format_for_external_system($lead_array, 'google_sheets');
                
                $existing_row = $this->find_lead_row($lead->session_id);
                
                if ($existing_row) {
                    $success = $this->update_sheet_row($existing_row, $sheet_data);
                } else {
                    $success = $this->add_sheet_row($sheet_data);
                }
                
                if ($success) {
                    // Update sync timestamp
                    $wpdb->update(
                        $table_name,
                        ['sheets_sync_timestamp' => current_time('mysql')],
                        ['id' => $lead->id],
                        ['%s'],
                        ['%d']
                    );
                    $synced++;
                } else {
                    $errors++;
                }
                
            } catch (Exception $e) {
                bsp_debug_log("Error in batch sync", 'SHEETS_ERROR', [
                    'lead_id' => $lead->id,
                    'error' => $e->getMessage()
                ]);
                $errors++;
            }
        }
        
        $message = sprintf('Synced %d leads, %d errors', $synced, $errors);
        bsp_debug_log("Batch sync completed", 'SHEETS_SYNC', [
            'synced' => $synced,
            'errors' => $errors,
            'manual' => $manual
        ]);
        
        return [
            'success' => true,
            'message' => $message,
            'synced' => $synced,
            'errors' => $errors
        ];
    }
    
    /**
     * Initialize Google Sheets headers using centralized field definitions
     */
    public function initialize_sheets_headers() {
        if (!$this->init_sheets_client()) {
            return false;
        }
        
        // Use centralized processor to get consistent field structure
        $sample_data = []; // Empty data to get field structure
        $formatted_sample = $this->data_processor->format_for_external_system($sample_data, 'google_sheets');
        
        // Create headers from the formatted data structure
        $headers = array_map(function($key) {
            return ucwords(str_replace('_', ' ', $key));
        }, array_keys($formatted_sample));
        
        try {
            $values = [$headers];
            
            $body = new Google_Service_Sheets_ValueRange([
                'values' => $values
            ]);
            
            $params = [
                'valueInputOption' => 'RAW'
            ];
            
            $result = $this->sheets_client->spreadsheets_values->update(
                $this->spreadsheet_id,
                'Leads!A1:Z1',
                $body,
                $params
            );
            
            bsp_debug_log("Google Sheets headers initialized", 'SHEETS_SUCCESS', [
                'headers_count' => count($headers)
            ]);
            return true;
            
        } catch (Exception $e) {
            bsp_debug_log("Error initializing Google Sheets headers", 'SHEETS_ERROR', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

// Initialize the Google Sheets Integration
BSP_Google_Sheets_Integration::get_instance();
