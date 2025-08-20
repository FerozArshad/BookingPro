<?php
/**
 * API Integration Class for AI Calling System
 * Handles sending form data to external API endpoint
 * 
 * COMMENTED OUT FOR FUTURE IMPLEMENTATION
 * This file contains the complete API integration system
 * Uncomment and integrate when ready to implement
 */

/*
if (!defined('ABSPATH')) {
    exit;
}

class BSP_API_Integration {
    
    private $api_endpoint;
    private $api_key;
    
    public function __construct() {
        // Load API configuration
        $this->load_api_config();
        
        // Hook into form submission
        add_action('bsp_form_submitted', array($this, 'send_to_api'), 10, 1);
    }
    
    /**
     * Load API configuration from WordPress options or config file
     */
    private function load_api_config() {
        // Try to get from WordPress options first (admin configurable)
        $this->api_endpoint = get_option('bsp_api_endpoint', '');
        $this->api_key = get_option('bsp_api_key', '');
        
        // Fallback to config file if not set in admin
        if (empty($this->api_endpoint) || empty($this->api_key)) {
            $config_file = plugin_dir_path(__FILE__) . 'api-config.php';
            if (file_exists($config_file)) {
                include $config_file;
                $this->api_endpoint = isset($BSP_API_ENDPOINT) ? $BSP_API_ENDPOINT : '';
                $this->api_key = isset($BSP_API_KEY) ? $BSP_API_KEY : '';
            }
        }
    }
    
    /**
     * Send form data to API endpoint
     * 
     * @param array $form_data The form submission data
     */
    public function send_to_api($form_data) {
        // Check if API is configured
        if (empty($this->api_endpoint) || empty($this->api_key)) {
            error_log('BSP API Integration: API endpoint or key not configured');
            return false;
        }
        
        // Prepare the payload
        $payload = $this->prepare_payload($form_data);
        
        // Send the API request
        $response = $this->make_api_request($payload);
        
        // Log the result
        if ($response) {
            error_log('BSP API Integration: Successfully sent data to AI calling system');
        } else {
            error_log('BSP API Integration: Failed to send data to AI calling system');
        }
        
        return $response;
    }
    
    /**
     * Prepare the payload for API request
     * 
     * @param array $form_data
     * @return array
     */
    private function prepare_payload($form_data) {
        // Extract important fields
        $service = isset($form_data['service']) ? $form_data['service'] : '';
        $full_name = isset($form_data['full_name']) ? $form_data['full_name'] : '';
        $phone = isset($form_data['phone']) ? $form_data['phone'] : '';
        $email = isset($form_data['email']) ? $form_data['email'] : '';
        $address = isset($form_data['address']) ? $form_data['address'] : '';
        
        // Extract service-specific fields
        $service_details = $this->extract_service_details($form_data, $service);
        
        // Extract appointment information
        $appointments = isset($form_data['appointments']) ? $form_data['appointments'] : [];
        $appointment_info = $this->format_appointment_info($appointments);
        
        // UTM/source fields to extract
        $utm_params = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'referrer'];
        $utm_data = [];
        foreach ($utm_params as $utm) {
            $utm_data[$utm] = isset($form_data[$utm]) ? $form_data[$utm] : '';
        }

        // Prepare the main payload structure
        $payload = array(
            'lead_source' => 'booking_form',
            'timestamp' => current_time('mysql'),
            // UTM/source fields as top-level keys
            'utm_source' => $utm_data['utm_source'],
            'utm_medium' => $utm_data['utm_medium'],
            'utm_campaign' => $utm_data['utm_campaign'],
            'utm_term' => $utm_data['utm_term'],
            'utm_content' => $utm_data['utm_content'],
            'gclid' => $utm_data['gclid'],
            'referrer' => $utm_data['referrer'],
            'customer' => array(
                'name' => $full_name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address
            ),
            'service' => array(
                'type' => $service,
                'details' => $service_details
            ),
            'appointment' => $appointment_info,
            'form_data' => $form_data // Include all form data for reference
        );
        return $payload;
    }
    
    /**
     * Extract service-specific details
     * 
     * @param array $form_data
     * @param string $service
     * @return array
     */
    private function extract_service_details($form_data, $service) {
        $details = array();
        
        switch (strtolower($service)) {
            case 'roof':
                $details['action'] = isset($form_data['roof_action']) ? $form_data['roof_action'] : '';
                $details['material'] = isset($form_data['roof_material']) ? $form_data['roof_material'] : '';
                $details['zip_code'] = isset($form_data['roof_zip']) ? $form_data['roof_zip'] : '';
                break;
                
            case 'windows':
                $details['action'] = isset($form_data['windows_action']) ? $form_data['windows_action'] : '';
                $details['quantity'] = isset($form_data['windows_replace_qty']) ? $form_data['windows_replace_qty'] : '';
                $details['repair_needed'] = isset($form_data['windows_repair_needed']) ? $form_data['windows_repair_needed'] : '';
                $details['zip_code'] = isset($form_data['windows_zip']) ? $form_data['windows_zip'] : '';
                break;
                
            case 'bathroom':
                $details['option'] = isset($form_data['bathroom_option']) ? $form_data['bathroom_option'] : '';
                $details['zip_code'] = isset($form_data['bathroom_zip']) ? $form_data['bathroom_zip'] : '';
                break;
                
            case 'siding':
                $details['option'] = isset($form_data['siding_option']) ? $form_data['siding_option'] : '';
                $details['material'] = isset($form_data['siding_material']) ? $form_data['siding_material'] : '';
                $details['zip_code'] = isset($form_data['siding_zip']) ? $form_data['siding_zip'] : '';
                break;
                
            case 'kitchen':
                $details['action'] = isset($form_data['kitchen_action']) ? $form_data['kitchen_action'] : '';
                $details['component'] = isset($form_data['kitchen_component']) ? $form_data['kitchen_component'] : '';
                $details['zip_code'] = isset($form_data['kitchen_zip']) ? $form_data['kitchen_zip'] : '';
                break;
                
            case 'decks':
                $details['action'] = isset($form_data['decks_action']) ? $form_data['decks_action'] : '';
                $details['material'] = isset($form_data['decks_material']) ? $form_data['decks_material'] : '';
                $details['zip_code'] = isset($form_data['decks_zip']) ? $form_data['decks_zip'] : '';
                break;
        }
        
        return $details;
    }
    
    /**
     * Format appointment information
     * 
     * @param array $appointments
     * @return array
     */
    private function format_appointment_info($appointments) {
        if (empty($appointments)) {
            return array();
        }
        
        $formatted = array();
        foreach ($appointments as $appointment) {
            $formatted[] = array(
                'company' => isset($appointment['company']) ? $appointment['company'] : '',
                'date' => isset($appointment['date']) ? $appointment['date'] : '',
                'time' => isset($appointment['time']) ? $appointment['time'] : '',
                'formatted_datetime' => isset($appointment['date'], $appointment['time']) 
                    ? $appointment['date'] . ' ' . $appointment['time'] : ''
            );
        }
        
        return $formatted;
    }
    
    /**
     * Make the actual API request
     * 
     * @param array $payload
     * @return bool
     */
    private function make_api_request($payload) {
        $headers = array(
            'Content-Type' => 'application/json',
            'auth' => $this->api_key
        );
        
        $args = array(
            'body' => json_encode($payload),
            'headers' => $headers,
            'timeout' => 30,
            'method' => 'POST'
        );
        
        $response = wp_remote_post($this->api_endpoint, $args);
        
        if (is_wp_error($response)) {
            error_log('BSP API Integration Error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code >= 200 && $response_code < 300) {
            return true;
        } else {
            error_log('BSP API Integration Error: HTTP ' . $response_code . ' - ' . $response_body);
            return false;
        }
    }
    
    /**
     * Test API connection
     * 
     * @return array
     */
    public function test_api_connection() {
        if (empty($this->api_endpoint) || empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'API endpoint or key not configured'
            );
        }
        
        // Send a test payload
        $test_payload = array(
            'test' => true,
            'timestamp' => current_time('mysql'),
            'message' => 'Test connection from BookingPro plugin'
        );
        
        $response = $this->make_api_request($test_payload);
        
        return array(
            'success' => $response,
            'message' => $response ? 'API connection successful' : 'API connection failed'
        );
    }
}

// Initialize the API integration
// new BSP_API_Integration();
*/
