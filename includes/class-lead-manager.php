<?php
/**
 * Centralized Lead Management System
 * Unified session, data processing, and lifecycle management
 */

if (!defined('ABSPATH')) exit;

class BSP_Lead_Manager {
    
    private static $instance = null;
    private $database;
    private $data_processor;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->database = BSP_Database_Unified::get_instance();
        $this->data_processor = BSP_Data_Processor_Unified::get_instance();
    }
    
    /**
     * Generate or retrieve session ID with proper lifecycle
     */
    public function get_or_create_session_id($existing_session = null) {
        // Use existing if valid and recent
        if ($existing_session && $this->is_session_valid($existing_session)) {
            return $existing_session;
        }
        
        // Generate new UUID-based session
        return wp_generate_uuid4();
    }
    
    /**
     * Validate session ID and check if it's still active
     */
    private function is_session_valid($session_id) {
        global $wpdb;
        $table_name = $this->database::$tables['incomplete_leads'];
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE session_id = %s AND created_at > DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $session_id,
            BSP_Constants::get_timeout('session_timeout')
        ));
        
        return !empty($exists);
    }
    
    /**
     * Clean expired sessions and data
     */
    public function cleanup_expired_data() {
        global $wpdb;
        $table_name = $this->database::$tables['incomplete_leads'];
        
        // Remove old incomplete leads (older than 7 days)
        $deleted = $wpdb->query(
            "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        if ($deleted) {
            bsp_log_info("Cleaned up expired lead data", ['deleted_count' => $deleted]);
        }
        
        // Clean up transients
        $this->cleanup_transients();
    }
    
    /**
     * Clean up old transients used for request deduplication
     */
    private function cleanup_transients() {
        global $wpdb;
        
        // Clean up old BSP transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_bsp_%' 
             OR option_name LIKE '_transient_timeout_bsp_%'"
        );
    }
    
    /**
     * Standardize lead data across the system
     */
    public function standardize_lead_data($raw_data) {
        // Use centralized data processor
        return $this->data_processor->process_lead_data($raw_data);
    }
    
    /**
     * Check for duplicate requests using unified approach
     */
    public function is_duplicate_request($session_id, $action = 'capture') {
        $transient_key = "bsp_{$action}_{$session_id}";
        
        if (get_transient($transient_key)) {
            return true;
        }
        
        // Set transient to prevent duplicates
        set_transient($transient_key, true, BSP_Constants::get_timeout('request_dedup'));
        return false;
    }
    
    /**
     * Unified lead saving with proper data flow
     */
    public function save_lead($lead_data, $update_existing = true) {
        global $wpdb;
        $table_name = $this->database::$tables['incomplete_leads'];
        
        // Standardize data
        $standardized_data = $this->standardize_lead_data($lead_data);
        
        // Prepare database fields
        $db_data = [
            'session_id' => $standardized_data['session_id'],
            'service' => $standardized_data['service'] ?? '',
            'zip_code' => $standardized_data['zip_code'] ?? '',
            'customer_name' => $standardized_data['full_name'] ?? '',
            'customer_email' => $standardized_data['email'] ?? '',
            'customer_phone' => $standardized_data['phone'] ?? '',
            'customer_address' => $standardized_data['address'] ?? '',
            'city' => $standardized_data['city'] ?? '',
            'state' => $standardized_data['state'] ?? '',
            'lead_type' => $standardized_data['lead_type'] ?? 'Processing',
            'completion_percentage' => $standardized_data['completion_percentage'] ?? 0,
            'form_data' => json_encode($standardized_data),
            'last_updated' => current_time('mysql')
        ];
        
        // Check for existing lead
        if ($update_existing) {
            $existing_lead = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE session_id = %s AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                $standardized_data['session_id']
            ));
            
            if ($existing_lead) {
                $result = $wpdb->update($table_name, $db_data, ['id' => $existing_lead]);
                return $result !== false ? $existing_lead : false;
            }
        }
        
        // Insert new lead
        $db_data['created_at'] = current_time('mysql');
        $result = $wpdb->insert($table_name, $db_data);
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get lead by session ID
     */
    public function get_lead_by_session($session_id) {
        global $wpdb;
        $table_name = $this->database::$tables['incomplete_leads'];
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE session_id = %s ORDER BY last_updated DESC LIMIT 1",
            $session_id
        ));
    }
    
    /**
     * Schedule background processing with unified approach
     */
    public function schedule_background_processing($lead_id, $lead_data) {
        if (function_exists('wp_schedule_single_event')) {
            wp_schedule_single_event(
                time() + BSP_Constants::get_timeout('background_process'),
                'bsp_process_lead_background',
                [$lead_id, $lead_data]
            );
            return true;
        }
        
        // Fallback - immediate processing
        return $this->process_lead_immediately($lead_id, $lead_data);
    }
    
    /**
     * Immediate processing fallback
     */
    private function process_lead_immediately($lead_id, $lead_data) {
        try {
            // Complete processing
            $enhanced_data = $this->data_processor->enhance_lead_data($lead_data);
            
            // Update database
            $this->save_lead($enhanced_data, true);
            
            // Trigger integrations
            do_action('bsp_incomplete_lead_captured', $lead_id, $enhanced_data);
            
            return true;
        } catch (Exception $e) {
            bsp_log_error("Immediate processing failed", [
                'lead_id' => $lead_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get system statistics for monitoring
     */
    public function get_system_stats() {
        global $wpdb;
        $table_name = $this->database::$tables['incomplete_leads'];
        
        return [
            'total_leads' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"),
            'recent_leads' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"),
            'processing_leads' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE lead_type = 'Processing'"),
            'complete_leads' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE lead_type = 'Complete'"),
            'failed_leads' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE lead_type = 'Failed'")
        ];
    }
}