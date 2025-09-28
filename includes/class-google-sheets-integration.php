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
    private static $webhook_cache = []; // Runtime cache for current request
    private static $transient_prefix = 'bsp_webhook_cache_'; // Persistent cache prefix
    
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
        
        bsp_log_info("Google Sheets Integration initialized", ['webhook_configured' => !empty($this->webhook_url)]);
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
        
        // Check if lead should be blocked
        $termination_check = $this->should_block_incomplete_lead($session_id, $lead_data, $lead_id);
        if ($termination_check['should_block']) {
            return false;
        }
        
        // CRITICAL FIX: Improved deduplication - check if lead already converted
        global $wpdb;
        BSP_Database_Unified::init_tables();
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        $existing_lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s ORDER BY id DESC LIMIT 1",
            $session_id
        ));
        
        // Check if lead is already converted
        if ($existing_lead && !empty($existing_lead->booking_post_id)) {
            return true;
        }
        
        // Create improved cache key with form step and completion percentage
        $completion = $lead_data['completion_percentage'] ?? 0;
        $form_step = $lead_data['form_step'] ?? 0;
        $cache_key = 'incomplete_' . $session_id . '_step_' . $form_step . '_completion_' . $completion;
        
        // Check for recent webhook cache to prevent duplicates
        $transient_key = self::$transient_prefix . md5($cache_key);
        $last_sent = get_transient($transient_key);
        
        if ($last_sent !== false && (time() - $last_sent) < 20) {
            return true;
        }
        
        // Set cache and process lead
        $current_time = time();
        self::$webhook_cache[$cache_key] = $current_time;
        set_transient($transient_key, $current_time, 60);
        
        // Skip processing leads with no valuable data
        $has_customer_data = !empty($lead_data['customer_name']) || !empty($lead_data['name']) || !empty($lead_data['email']);
        $has_service_data = !empty($lead_data['service']) || !empty($lead_data['service_type']);
        $has_appointment_data = !empty($lead_data['appointments']);
        
        if (!$has_customer_data && !$has_service_data && !$has_appointment_data) {
            return true;
        }
        
        // Use field mapper for consistent data formatting
        if (class_exists('BSP_Field_Mapper')) {
            $mapped_data = BSP_Field_Mapper::map_form_data($lead_data);
        } else {
            $mapped_data = $lead_data;
        }
        
        // Use centralized data processor to format real lead data
        $sheet_data = $this->data_processor->format_for_external_system($mapped_data, 'google_sheets');

        // Process appointment data if available
        $appointments_data = [];
        if (!empty($lead_data['appointments'])) {
            $appointments_json = $lead_data['appointments'];
            if (is_string($appointments_json)) {
                $appointments_array = json_decode($appointments_json, true);
                if ($appointments_array && is_array($appointments_array)) {
                    $appointments_data = $appointments_array;
                }
            }
        }
        

        
        // Build and send webhook payload
        $webhook_payload = $this->build_session_based_payload($sheet_data, $lead_data, $appointments_data);
        $result = $this->send_webhook_data($webhook_payload);
        
        return $result['success'];
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
     * Build webhook payload for single appointment or combined appointments
     */
    private function build_single_appointment_payload($sheet_data, $lead_data, $combined_appointments = null) {
        // Use combined appointment data if provided, otherwise extract from lead data
        $appointment_data = $combined_appointments ?? $this->extract_appointment_data_from_lead($lead_data);
        
        return [
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
            
            // Company and booking info - use combined appointment data if available
            'company' => $appointment_data['company'] ?? $sheet_data['company'] ?? $lead_data['company'] ?? '',
            'booking_id' => $sheet_data['booking_id'] ?? $lead_data['booking_id'] ?? '', 
            'booking_date' => $appointment_data['date'] ?? $sheet_data['date'] ?? $sheet_data['formatted_date'] ?? $lead_data['booking_date'] ?? $lead_data['date'] ?? '',
            'booking_time' => $appointment_data['time'] ?? $sheet_data['time'] ?? $sheet_data['formatted_time'] ?? $lead_data['booking_time'] ?? $lead_data['time'] ?? '',
            
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
    }

    /**
     * Extract appointment data from lead data for single row approach
     */
    private function extract_appointment_data_from_lead($lead_data) {
        $appointment_data = [
            'company' => $lead_data['company'] ?? '',
            'date' => $lead_data['date'] ?? $lead_data['booking_date'] ?? '',
            'time' => $lead_data['time'] ?? $lead_data['booking_time'] ?? '',
            'appointment_count' => 1
        ];
        
        // If we have multiple appointment data, extract it
        if (!empty($lead_data['appointments'])) {
            $appointments = is_string($lead_data['appointments']) 
                ? json_decode($lead_data['appointments'], true) 
                : $lead_data['appointments'];
            
            if (is_array($appointments) && count($appointments) > 1) {
                $companies = [];
                $dates = [];
                $times = [];
                
                foreach ($appointments as $apt) {
                    $companies[] = $apt['company'] ?? '';
                    $dates[] = $apt['date'] ?? '';
                    $times[] = $apt['time'] ?? '';
                }
                
                $appointment_data = [
                    'company' => implode(', ', array_filter($companies)),
                    'date' => implode(', ', array_filter($dates)),
                    'time' => implode(', ', array_filter($times)),
                    'appointment_count' => count($appointments)
                ];
            }
        }
        
        return $appointment_data;
    }

    /**
     * Build session-based payload for Google Sheets (single row per session)
     */
    private function build_session_based_payload($sheet_data, $lead_data, $appointments_data = []) {
        $session_id = $lead_data['session_id'] ?? ('session_' . time());
        
        // If multiple appointments, combine them into comma-separated values
        $appointment_info = $this->combine_appointment_data($appointments_data, $lead_data);
        
        return [
            // REQUIRED: Core identification - consistent session ID
            'session_id' => $session_id,
            'action' => 'incomplete_lead',
            'timestamp' => current_time('mysql'),
            'update_mode' => 'upsert', // Tell Google Sheets to update if exists
            
            // Lead classification
            'lead_type' => $this->determine_lead_status($lead_data),
            'lead_status' => $this->determine_lead_status($lead_data),
            'status' => $this->determine_lead_status($lead_data),
            
            // Customer information
            'customer_name' => $sheet_data['customer_name'] ?? $lead_data['customer_name'] ?? $lead_data['name'] ?? $lead_data['full_name'] ?? '',
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
            
            // COMBINED Appointment information (comma-separated for multiple)
            'company' => $appointment_info['company'],
            'booking_date' => $appointment_info['date'],
            'booking_time' => $appointment_info['time'],
            'appointment_count' => $appointment_info['count'],
            'booking_id' => $sheet_data['booking_id'] ?? $lead_data['booking_id'] ?? '',
            
            // Progress tracking
            'form_step' => $lead_data['form_step'] ?? 'Step 1: Service Selection',
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
            'notes' => $this->build_session_notes($lead_data, $appointments_data)
        ];
    }
    
    /**
     * Combine appointment data into comma-separated values
     */
    private function combine_appointment_data($appointments_data, $lead_data) {

        
        if (!empty($appointments_data)) {
            $companies = [];
            $dates = [];
            $times = [];
            
            foreach ($appointments_data as $appointment) {
                $companies[] = $appointment['company'] ?? '';
                $dates[] = $appointment['date'] ?? '';
                $times[] = $appointment['time'] ?? '';
            }
            
            $combined_company = implode(', ', array_filter($companies));
            $combined_date = implode(', ', array_filter($dates));
            $combined_time = implode(', ', array_filter($times));
            
            // Handle long company field truncation
            if (strlen($combined_company) > 250) {
                $combined_company = substr($combined_company, 0, 247) . '...';
            }
            
            return [
                'company' => $combined_company,
                'date' => $combined_date,
                'time' => $combined_time,
                'count' => count($appointments_data)
            ];
        }
        
        // Single appointment or fallback data
        $fallback_result = [
            'company' => $lead_data['company'] ?? '',
            'date' => $lead_data['date'] ?? $lead_data['booking_date'] ?? '',
            'time' => $lead_data['time'] ?? $lead_data['booking_time'] ?? '',
            'count' => (!empty($lead_data['company']) ? 1 : 0)
        ];
        

        
        return $fallback_result;
    }
    
    /**
     * Determine lead status based on completion and data quality
     */
    private function determine_lead_status($lead_data) {
        $completion = $lead_data['completion_percentage'] ?? 0;
        
        if ($completion >= 100) {
            return 'Complete Booking';
        } elseif ($completion >= 75) {
            return 'Qualified Lead';
        } elseif ($completion >= 50) {
            return 'Potential Lead';
        } else {
            return 'Initial Interest';
        }
    }
    
    /**
     * Build contextual notes for the session
     */
    private function build_session_notes($lead_data, $appointments_data) {
        $notes = [];
        
        if (!empty($appointments_data) && count($appointments_data) > 1) {
            $notes[] = count($appointments_data) . " appointments scheduled";
        }
        
        if (!empty($lead_data['send_trigger'])) {
            $trigger_map = [
                'exit_intent' => 'Captured on exit intent',
                'beforeunload' => 'Captured before page leave',
                'visibility_change' => 'Captured on tab change',
                'form_interaction' => 'Captured during form interaction'
            ];
            $notes[] = $trigger_map[$lead_data['send_trigger']] ?? "Captured via {$lead_data['send_trigger']}";
        }
        
        return implode('; ', $notes);
    }

    /**
     * Send data to webhook server for Google Sheets integration
     * FIXED: Simplified payload format to resolve HTTP 400 errors
     */
    private function send_webhook_data($payload) {
        // Validate webhook configuration
        if (empty($this->webhook_url)) {
            return ['success' => false, 'error' => 'No webhook URL configured'];
        }
        
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

        // CRITICAL FIX: Create simplified, Google Apps Script-friendly payload
        $simplified_payload = $this->create_simplified_payload($payload);
        
        // Debug: Log exact payload being sent
        bsp_debug_log("WEBHOOK DEBUG - Simplified payload being sent", 'WEBHOOK_DEBUG', [
            'original_payload_count' => count($payload),
            'simplified_payload_count' => count($simplified_payload),
            'simplified_payload' => $simplified_payload,
            'session_id' => $simplified_payload['session_id'] ?? 'missing',
            'action_value' => $simplified_payload['action'] ?? 'missing',
            'booking_id_check' => [
                'original_booking_id' => $payload['booking_id'] ?? 'not_in_original',
                'simplified_booking_id' => $simplified_payload['booking_id'] ?? 'not_in_simplified',
                'booking_id_type' => gettype($simplified_payload['booking_id'] ?? null)
            ]
        ]);

        // CRITICAL FIX: Use JSON as primary method for complex payloads (better for long company names)
        // Google Apps Script specific headers to avoid HTTP 400 errors
        $json_args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'User-Agent' => 'BookingPro-WordPress-Plugin/2.0',
                'Accept' => 'application/json,text/html,*/*',
                'Cache-Control' => 'no-cache'
            ],
            'body' => json_encode($simplified_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout' => 45,
            'blocking' => false,
            'sslverify' => true,
            'redirection' => 3
        ];
        
        // Send JSON payload (primary method)
        $response = wp_remote_post($this->webhook_url, $json_args);
        
        // Non-blocking request handling
        if ($json_args['blocking'] === false) {
            return [
                'success' => true,
                'response' => ['status' => 'sent_nonblocking'],
                'http_code' => 'nonblocking'
            ];
        }
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
            // Try form-encoded fallback
            $json_error = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
            
            // Create form-encoded fallback - Google Apps Script compatible
            $form_args = [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
                    'User-Agent' => 'BookingPro-WordPress-Plugin/2.0',
                    'Accept' => 'text/html,application/json,*/*'
                ],
                'body' => http_build_query($simplified_payload, '', '&', PHP_QUERY_RFC3986),
                'timeout' => 30,
                'blocking' => false,
                'sslverify' => true,
                'redirection' => 2
            ];
            
            $response = wp_remote_post($this->webhook_url, $form_args);
            
            // Non-blocking fallback handling
            if ($form_args['blocking'] === false) {
                return [
                    'success' => true,
                    'response' => ['status' => 'sent_nonblocking_fallback'],
                    'http_code' => 'nonblocking'
                ];
            }
            
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                return [
                    'success' => false,
                    'error' => 'Webhook failed: ' . $error_message
                ];
            }
        }

        // Check if request was non-blocking
        $is_nonblocking = ($json_args['blocking'] === false) || (isset($form_args) && $form_args['blocking'] === false);
        
        if ($is_nonblocking) {
            return [
                'success' => true,
                'response' => ['status' => 'sent_nonblocking'],
                'http_code' => 'nonblocking'
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code >= 200 && $response_code < 300) {
            return [
                'success' => true,
                'response' => json_decode($response_body, true),
                'http_code' => $response_code
            ];
        } else {
            return [
                'success' => false,
                'error' => "HTTP {$response_code}: {$response_body}",
                'http_code' => $response_code
            ];
        }
    }

    /**
     * Create a complete payload that Google Apps Script needs for proper lead analysis
     * CRITICAL FIX: Include ALL fields required for lead status, type, and score calculation
     */
    private function create_simplified_payload($payload) {

        
        // Core fields that Google Apps Script expects
        $simplified = [
            'session_id' => (string) ($payload['session_id'] ?? 'session_' . time()),
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => (string) ($payload['action'] ?? 'booking'),
            'update_mode' => 'upsert',
            
            // Customer information - REQUIRED for lead analysis
            'customer_name' => $this->clean_string($payload['customer_name'] ?? ''),
            'customer_email' => $this->clean_string($payload['customer_email'] ?? ''),
            'customer_phone' => $this->clean_string($payload['customer_phone'] ?? ''),
            'customer_address' => $this->clean_string($payload['customer_address'] ?? ''),
            'city' => $this->clean_string($payload['city'] ?? ''),
            'state' => $this->clean_string($payload['state'] ?? ''),
            'zip_code' => $this->clean_string($payload['zip_code'] ?? ''),
            
            // Service information - REQUIRED for lead scoring
            'service' => $this->clean_string($payload['service'] ?? $payload['service_type'] ?? ''),
            'service_type' => $this->clean_string($payload['service'] ?? $payload['service_type'] ?? ''),
            'specifications' => $this->clean_string($payload['specifications'] ?? $payload['service_details'] ?? ''),
            
            // Company and booking info - MULTIPLE FIELD NAMES for compatibility
            'company' => $this->clean_string($payload['company'] ?? $payload['company_name'] ?? ''),
            'company_name' => $this->clean_string($payload['company'] ?? $payload['company_name'] ?? ''),
            
            // Date/Time fields - Google Script expects BOTH formats
            'booking_date' => $this->clean_string($payload['booking_date'] ?? ''),
            'booking_time' => $this->clean_string($payload['booking_time'] ?? ''),
            'formatted_date' => $this->clean_string($payload['booking_date'] ?? $payload['formatted_date'] ?? ''),
            'formatted_time' => $this->clean_string($payload['booking_time'] ?? $payload['formatted_time'] ?? ''),
            'selected_date' => $this->clean_string($payload['booking_date'] ?? $payload['selected_date'] ?? ''),
            'selected_time' => $this->clean_string($payload['booking_time'] ?? $payload['selected_time'] ?? ''),
            
            // CRITICAL: Always preserve booking_id
            'booking_id' => $this->clean_string($payload['booking_id'] ?? ''),
            'id' => $this->clean_string($payload['booking_id'] ?? $payload['id'] ?? ''),
            
            // Status fields - Google Script will recalculate these based on data
            'lead_type' => $this->clean_string($payload['lead_type'] ?? 'Booking'),
            'lead_status' => $this->clean_string($payload['lead_status'] ?? $payload['status'] ?? 'Complete'),
            'status' => $this->clean_string($payload['status'] ?? $payload['lead_status'] ?? 'Active'),
            'completion_percentage' => (int) ($payload['completion_percentage'] ?? 100),
            'appointment_count' => (int) ($payload['appointment_count'] ?? 1),
            'form_step' => (int) ($payload['form_step'] ?? 4), // Default to final step for complete bookings
            'lead_score' => (int) ($payload['lead_score'] ?? 0),
            
            // UTM/Marketing data - ESSENTIAL for lead scoring and analysis
            'utm_source' => $this->clean_string($payload['utm_source'] ?? ''),
            'utm_medium' => $this->clean_string($payload['utm_medium'] ?? ''),
            'utm_campaign' => $this->clean_string($payload['utm_campaign'] ?? ''),
            'utm_term' => $this->clean_string($payload['utm_term'] ?? ''),
            'utm_content' => $this->clean_string($payload['utm_content'] ?? ''),
            'gclid' => $this->clean_string($payload['gclid'] ?? ''),
            'referrer' => $this->clean_string($payload['referrer'] ?? $payload['http_referer'] ?? ''),
            
            // Date tracking for analytics
            'created_date' => $this->clean_string($payload['created_date'] ?? $payload['created_at'] ?? date('m/d/Y')),
            'last_updated' => date('Y-m-d H:i:s'),
            'conversion_time' => ($payload['action'] === 'complete_booking') ? date('Y-m-d H:i:s') : '',
            'notes' => $this->clean_string($payload['notes'] ?? $payload['special_notes'] ?? $payload['message'] ?? '')
        ];
        
        // Add ALL service-specific fields that Google Script expects
        $service_fields = [
            'roof_action', 'roof_material', 'roof_zip',
            'windows_action', 'windows_replace_qty', 'windows_repair_needed', 'windows_zip',
            'bathroom_option', 'bathroom_zip',
            'siding_option', 'siding_material', 'siding_zip',
            'kitchen_action', 'kitchen_component', 'kitchen_zip',
            'decks_action', 'decks_material', 'decks_zip',
            'adu_action', 'adu_type', 'adu_zip'
        ];
        
        foreach ($service_fields as $field) {
            if (isset($payload[$field]) && $payload[$field] !== '') {
                $simplified[$field] = $this->clean_string($payload[$field]);
            }
        }
        
        // Map generic zip_code to service-specific zip codes if available
        $generic_zip = $payload['zip_code'] ?? '';
        $service = strtolower($simplified['service']);
        if ($generic_zip && $service) {
            $service_zip_field = $service . '_zip';
            if (in_array($service_zip_field, $service_fields) && !isset($simplified[$service_zip_field])) {
                $simplified[$service_zip_field] = $this->clean_string($generic_zip);
            }
        }
        
        // Remove ONLY truly empty values, but preserve important fields
        // CRITICAL FIX: Preserve booking_id, session_id, and other key identifiers
        $filtered = array_filter($simplified, function($value, $key) {
            // Always preserve critical identification fields
            $preserve_fields = ['booking_id', 'session_id', 'id', 'action', 'timestamp', 'update_mode'];
            if (in_array($key, $preserve_fields)) {
                return true;
            }
            // Keep non-empty values, but allow numeric 0
            return $value !== '' && $value !== null && $value !== false;
        }, ARRAY_FILTER_USE_BOTH);
        

        
        return $filtered;
    }
    
    /**
     * Clean string for Google Apps Script compatibility
     */
    private function clean_string($value) {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        
        $value = (string) $value;
        // Remove control characters and trim
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        $value = trim($value);
        
        // Limit length to prevent payload bloat - but keep reasonable limit
        if (strlen($value) > 500) {
            $value = substr($value, 0, 500) . '...';
        }
        
        return $value;
    }

    /**
     * Prepare payload specifically for Google Apps Script expectations
     * LEGACY METHOD - kept for backward compatibility
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
        return $clean_payload;
    }

    /**
     * Sync converted lead to Google Sheets via webhook with real data
     * ENHANCED with global deduplication across all call sources
     */
    public function sync_converted_lead($booking_id, $booking_data) {
        // Global deduplication check
        $global_key = 'bsp_converted_processed_' . $booking_id;
        if (get_transient($global_key) !== false) {
            return true;
        }
        set_transient($global_key, current_time('mysql'), 3600);
        
        $session_id = $booking_data['session_id'] ?? $this->extract_session_id_from_booking($booking_data);
        
        // Mark lead as converted in database
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
        }
        
        // Check for recent duplicate syncs
        $cache_key = 'complete_' . $booking_id . '_' . $session_id;
        $booking_cache_key = 'booking_' . $booking_id;
        $booking_transient_key = self::$transient_prefix . md5($booking_cache_key);
        $booking_last_sent = get_transient($booking_transient_key);
        
        $transient_key = self::$transient_prefix . md5($cache_key);
        $last_sent = get_transient($transient_key);
        
        // Skip if recently sent
        if (($booking_last_sent !== false && (time() - $booking_last_sent) < 20) ||
            ($last_sent !== false && (time() - $last_sent) < 20)) {
            return true;
        }
        
        // Set cache to prevent duplicates
        $current_time = time();
        self::$webhook_cache[$cache_key] = $current_time;
        set_transient($transient_key, $current_time, 60);
        self::$webhook_cache[$booking_cache_key] = $current_time;
        set_transient($booking_transient_key, $current_time, 60);
        
        try {
            if (!$session_id) {
                return false;
            }
            
            // Get actual booking data from WordPress post meta (this contains the combined company/date/time data)
            $appointments_from_post_meta = get_post_meta($booking_id, '_appointments', true);
            $appointments_from_booking_data = $booking_data['appointments'] ?? '';
            $final_appointments = $appointments_from_post_meta ?: $appointments_from_booking_data;
            
            // ENHANCED DEBUG: Track appointment data retrieval
            bsp_debug_log("CRITICAL APPOINTMENT DEBUG - Post meta retrieval", 'APPOINTMENT_RETRIEVAL', [
                'booking_id' => $booking_id,
                'appointments_from_post_meta' => $appointments_from_post_meta ?: 'EMPTY',
                'appointments_from_post_meta_type' => gettype($appointments_from_post_meta),
                'appointments_from_post_meta_length' => is_string($appointments_from_post_meta) ? strlen($appointments_from_post_meta) : 'not_string',
                'appointments_from_booking_data' => $appointments_from_booking_data ?: 'EMPTY',
                'appointments_from_booking_data_type' => gettype($appointments_from_booking_data),
                'final_appointments' => $final_appointments ?: 'EMPTY',
                'final_appointments_type' => gettype($final_appointments),
                'final_appointments_length' => is_string($final_appointments) ? strlen($final_appointments) : 'not_string'
            ]);
            
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
                
                // CRITICAL FIX: Get the appointments JSON from post meta (this is the missing piece!)
                'appointments' => $final_appointments,
                
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
                'appointments_retrieved' => !empty($wp_booking_data['appointments']) ? 'YES' : 'NO',
                'appointments_length' => !empty($wp_booking_data['appointments']) ? strlen($wp_booking_data['appointments']) : 0,
                'appointments_preview' => !empty($wp_booking_data['appointments']) ? substr($wp_booking_data['appointments'], 0, 100) . '...' : 'empty',
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
            
            // CRITICAL: Use session-based approach for complete bookings (single row per session)
            $appointments_data = [];
            if (!empty($complete_data['appointments'])) {
                $appointments_json = $complete_data['appointments'];
                bsp_debug_log("APPOINTMENT DEBUG - Raw appointments data", 'APPOINTMENT_DEBUG', [
                    'appointments_json' => $appointments_json,
                    'appointments_type' => gettype($appointments_json),
                    'appointments_length' => is_string($appointments_json) ? strlen($appointments_json) : 'not_string',
                    'is_empty' => empty($appointments_json) ? 'YES' : 'NO'
                ]);
                
                if (is_string($appointments_json)) {
                    $appointments_array = json_decode($appointments_json, true);
                    bsp_debug_log("APPOINTMENT DEBUG - JSON decode result", 'APPOINTMENT_DEBUG', [
                        'decode_success' => is_array($appointments_array) ? 'YES' : 'NO',
                        'json_error' => json_last_error_msg(),
                        'array_count' => is_array($appointments_array) ? count($appointments_array) : 'not_array',
                        'first_appointment' => is_array($appointments_array) && !empty($appointments_array) ? $appointments_array[0] : 'none'
                    ]);
                    
                    if ($appointments_array && is_array($appointments_array)) {
                        $appointments_data = $appointments_array;
                    }
                } else {
                    // Check if appointments is already an array
                    if (is_array($appointments_json)) {
                        $appointments_data = $appointments_json;
                        bsp_debug_log("APPOINTMENT DEBUG - Appointments already array", 'APPOINTMENT_DEBUG', [
                            'count' => count($appointments_data)
                        ]);
                    }
                }
            } else {
                bsp_debug_log("APPOINTMENT DEBUG - No appointments in complete_data", 'APPOINTMENT_DEBUG', [
                    'complete_data_keys' => array_keys($complete_data),
                    'has_appointments_key' => isset($complete_data['appointments']) ? 'YES' : 'NO',
                    'appointments_value' => $complete_data['appointments'] ?? 'not_set'
                ]);
            }
            
            // Build session-based payload for complete booking (same as incomplete but with complete status)
            $webhook_payload = $this->build_session_based_payload($sheet_data, $complete_data, $appointments_data);
            
            // Override with complete booking specifics
            $webhook_payload['action'] = 'complete_booking';
            $webhook_payload['lead_type'] = 'Complete Booking';
            $webhook_payload['lead_status'] = 'Complete';
            $webhook_payload['status'] = 'Converted';
            $webhook_payload['completion_percentage'] = 100;
            $webhook_payload['booking_id'] = $booking_id;
            $webhook_payload['converted_to_booking'] = 1;
            $webhook_payload['conversion_time'] = current_time('mysql');
            
            // CRITICAL SESSION MANAGEMENT: Mark session as completed to prevent future incomplete_lead webhooks
            $webhook_payload['session_completed'] = true;
            $webhook_payload['session_termination_time'] = current_time('mysql');
            
            // Terminate session to prevent race conditions
            $this->terminate_session($webhook_payload['session_id'], $booking_id);
            
            // DEBUG: Log key fields before sending webhook
            bsp_debug_log("WEBHOOK PAYLOAD DEBUG - Key fields", 'WEBHOOK_DEBUG', [
                'booking_id_set' => $webhook_payload['booking_id'] ?? 'NOT_SET',
                'company_field' => substr($webhook_payload['company'] ?? '', 0, 100) . '...',
                'company_length' => strlen($webhook_payload['company'] ?? ''),
                'appointment_count_field' => $webhook_payload['appointment_count'] ?? 'NOT_SET',
                'booking_date' => $webhook_payload['booking_date'] ?? 'NOT_SET',
                'booking_time' => $webhook_payload['booking_time'] ?? 'NOT_SET',
                'update_mode' => $webhook_payload['update_mode'] ?? 'NOT_SET'
            ]);
            
            bsp_debug_log("Session-based complete booking payload constructed", 'SHEETS_BOOKING_SYNC', [
                'booking_id' => $booking_id,
                'session_id' => $session_id,
                'payload_type' => 'session_based_complete',
                'appointment_count' => count($appointments_data),
                'update_mode' => $webhook_payload['update_mode'] ?? 'missing'
            ]);
            
            // Send to webhook server using session-based approach
            $result = $this->send_webhook_data($webhook_payload);
            
            if ($result['success']) {
                bsp_debug_log("Successfully sent converted lead data to Google Sheets webhook", 'SHEETS_CONVERSION', [
                    'booking_id' => $booking_id,
                    'session_id' => $session_id,
                    'approach' => 'session_based_upsert',
                    'conversion_data' => [
                        'service' => $webhook_payload['service'] ?? '',
                        'customer_name' => $webhook_payload['customer_name'] ?? '',
                        'customer_email' => $webhook_payload['customer_email'] ?? '',
                        'city' => $webhook_payload['city'] ?? '',
                        'state' => $webhook_payload['state'] ?? '',
                        'company' => $webhook_payload['company'] ?? '',
                        'booking_date' => $webhook_payload['booking_date'] ?? '',
                        'booking_time' => $webhook_payload['booking_time'] ?? '',
                        'update_mode' => $webhook_payload['update_mode'] ?? 'missing',
                        'converted' => true
                    ]
                ]);
                return true;
            } else {
                bsp_debug_log("Failed to send converted lead data to Google Sheets webhook", 'SHEETS_ERROR', [
                    'booking_id' => $booking_id,
                    'session_id' => $session_id,
                    'approach' => 'session_based_upsert',
                    'error' => $result['error'] ?? 'Unknown error',
                    'payload_keys_sent' => array_keys($webhook_payload)
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
        
        bsp_debug_log("MERGE BOOKING DEBUG - Database lead retrieval", 'BOOKING_MERGE', [
            'session_id' => $session_id,
            'lead_found' => $lead ? 'YES' : 'NO',
            'lead_id' => $lead->id ?? 'none',
            'lead_appointments' => isset($lead->appointments) ? 'HAS_APPOINTMENTS' : 'NO_APPOINTMENTS',
            'lead_form_data' => isset($lead->form_data) ? 'HAS_FORM_DATA' : 'NO_FORM_DATA',
            'lead_final_form_data' => isset($lead->final_form_data) ? 'HAS_FINAL_FORM_DATA' : 'NO_FINAL_FORM_DATA'
        ]);
        
        // Start with booking data as base
        $merged_data = $booking_data;
        
        bsp_debug_log("MERGE BOOKING DEBUG - Initial booking data", 'BOOKING_MERGE', [
            'booking_data_keys' => array_keys($booking_data),
            'booking_appointments' => isset($booking_data['appointments']) ? 'HAS_APPOINTMENTS' : 'NO_APPOINTMENTS',
            'booking_appointments_value' => $booking_data['appointments'] ?? 'not_set'
        ]);
        
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
        
        bsp_debug_log("MERGE BOOKING DEBUG - Final merged data", 'BOOKING_MERGE', [
            'final_keys' => array_keys($merged_data),
            'final_appointments' => isset($merged_data['appointments']) ? 'HAS_APPOINTMENTS' : 'NO_APPOINTMENTS',
            'final_appointments_value' => $merged_data['appointments'] ?? 'not_set',
            'final_appointments_type' => gettype($merged_data['appointments'] ?? null),
            'session_id_preserved' => $merged_data['session_id']
        ]);
        
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
    
    /**
     * CRITICAL SESSION MANAGEMENT: Terminate session to prevent race conditions
     */
    private function terminate_session($session_id, $booking_id) {
        if (empty($session_id)) return;
        
        // Set a transient to mark this session as completed
        $terminated_sessions_key = 'bsp_terminated_sessions';
        $terminated_sessions = get_transient($terminated_sessions_key) ?: [];
        
        $terminated_sessions[$session_id] = [
            'booking_id' => $booking_id,
            'terminated_at' => time(),
            'reason' => 'complete_booking_processed'
        ];
        
        // Keep terminated sessions list for 24 hours to prevent race conditions
        set_transient($terminated_sessions_key, $terminated_sessions, 24 * HOUR_IN_SECONDS);
        
        bsp_debug_log("Session terminated to prevent incomplete_lead race conditions", 'SESSION_MANAGEMENT', [
            'session_id' => $session_id,
            'booking_id' => $booking_id,
            'terminated_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Check if session is terminated (completed booking already processed)
     */
    private function is_session_terminated($session_id) {
        if (empty($session_id)) return false;
        
        $terminated_sessions_key = 'bsp_terminated_sessions';
        $terminated_sessions = get_transient($terminated_sessions_key) ?: [];
        
        return isset($terminated_sessions[$session_id]);
    }

    /**
     * Enhanced session management: Determine if incomplete lead should be blocked
     * Allows processing of leads created before session termination
     */
    private function should_block_incomplete_lead($session_id, $lead_data, $lead_id) {
        if (empty($session_id)) {
            return ['should_block' => false, 'reason' => 'no_session_id'];
        }

        // Check if session is terminated
        $terminated_sessions_key = 'bsp_terminated_sessions';
        $terminated_sessions = get_transient($terminated_sessions_key) ?: [];
        
        if (!isset($terminated_sessions[$session_id])) {
            return ['should_block' => false, 'reason' => 'session_not_terminated'];
        }

        $termination_info = $terminated_sessions[$session_id];
        $termination_timestamp = $termination_info['terminated_at'] ?? time();

        // Get lead creation time from database
        global $wpdb;
        BSP_Database_Unified::init_tables();
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        $lead_creation_time = $wpdb->get_var($wpdb->prepare(
            "SELECT UNIX_TIMESTAMP(created_at) FROM $table_name WHERE id = %d",
            $lead_id
        ));

        if (!$lead_creation_time) {
            // If we can't determine creation time, allow processing but log it
            bsp_debug_log("WARNING: Cannot determine lead creation time - allowing processing", 'SESSION_MANAGEMENT', [
                'session_id' => $session_id,
                'lead_id' => $lead_id
            ]);
            return ['should_block' => false, 'reason' => 'cannot_determine_creation_time'];
        }

        // CRITICAL LOGIC: Allow incomplete leads created BEFORE session termination
        if ($lead_creation_time < $termination_timestamp) {
            bsp_debug_log("ALLOWING: Incomplete lead created before session termination", 'SESSION_MANAGEMENT', [
                'session_id' => $session_id,
                'lead_id' => $lead_id,
                'lead_created_at' => date('Y-m-d H:i:s', $lead_creation_time),
                'session_terminated_at' => date('Y-m-d H:i:s', $termination_timestamp),
                'time_difference_seconds' => $termination_timestamp - $lead_creation_time
            ]);
            return [
                'should_block' => false, 
                'reason' => 'lead_created_before_termination',
                'lead_created_time' => date('Y-m-d H:i:s', $lead_creation_time),
                'termination_time' => date('Y-m-d H:i:s', $termination_timestamp)
            ];
        }

        // Block leads created after session termination
        return [
            'should_block' => true, 
            'reason' => 'lead_created_after_termination',
            'lead_created_time' => date('Y-m-d H:i:s', $lead_creation_time),
            'termination_time' => date('Y-m-d H:i:s', $termination_timestamp)
        ];
    }
}

// Initialize the Google Sheets Integration
BSP_Google_Sheets_Integration::get_instance();
