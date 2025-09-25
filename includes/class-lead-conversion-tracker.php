<?php
/**
 * Lead Conversion Tracker for Lead Capture System
 * Phase 2B: Tracks conversion from incomplete leads to complete bookings
 */

if (!defined('ABSPATH')) exit;

class BSP_Lead_Conversion_Tracker {
    
    private static $instance = null;
    private $data_processor; // Use centralized data processor
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Use the centralized data processor
        $this->data_processor = BSP_Data_Processor_Unified::get_instance();
        // Hook into booking submission to track conversions
        add_action('wp_ajax_bsp_submit_booking', [$this, 'track_conversion_before_submission'], 5);
        add_action('wp_ajax_nopriv_bsp_submit_booking', [$this, 'track_conversion_before_submission'], 5);
        
        // Hook after successful booking creation
        add_action('bsp_booking_created', [$this, 'complete_conversion_tracking'], 10, 2);
        
        // Clean up old incomplete leads
        add_action('wp_scheduled_cleanup', [$this, 'cleanup_old_leads']);
        if (!wp_next_scheduled('wp_scheduled_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_scheduled_cleanup');
        }
        
        bsp_debug_log("Lead Conversion Tracker initialized", 'CONVERSION_TRACKER');
    }
    
    /**
     * Track conversion before form submission
     * This runs early in the booking submission process
     */
    public function track_conversion_before_submission() {
        if (!isset($_POST['session_id']) || empty($_POST['session_id'])) {
            // Generate session ID from form data if not provided
            $session_data = [
                'email' => $_POST['email'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'service' => $_POST['service'] ?? ''
            ];
            $session_id = $this->generate_session_id_from_data($session_data);
        } else {
            $session_id = sanitize_text_field($_POST['session_id']);
        }
        
        if ($session_id) {
            $this->mark_lead_as_converting($session_id, $_POST);
            
            bsp_lead_log("Lead conversion initiated", [
                'session_id' => $session_id,
                'service' => $_POST['service'] ?? 'unknown',
                'email' => $_POST['email'] ?? 'unknown'
            ], 'CONVERSION_START');
        }
    }
    
    /**
     * Complete conversion tracking after successful booking
     */
    public function complete_conversion_tracking($booking_id, $booking_data) {
        $session_id = null;
        
        // Try to get session ID from multiple sources
        if (isset($booking_data['session_id'])) {
            $session_id = $booking_data['session_id'];
        } elseif (isset($_POST['session_id'])) {
            $session_id = sanitize_text_field($_POST['session_id']);
        } else {
            // Generate from booking data
            $session_data = [
                'email' => $booking_data['email'] ?? $booking_data['customer_email'] ?? '',
                'phone' => $booking_data['phone'] ?? $booking_data['customer_phone'] ?? '',
                'service' => $booking_data['service'] ?? $booking_data['service_type'] ?? ''
            ];
            $session_id = $this->generate_session_id_from_data($session_data);
        }
        
        if ($session_id) {
            $this->complete_lead_conversion($session_id, $booking_id, $booking_data);
            
            bsp_lead_log("Lead conversion completed", [
                'session_id' => $session_id,
                'booking_id' => $booking_id,
                'service' => $booking_data['service'] ?? 'unknown'
            ], 'CONVERSION_COMPLETE');
        }
    }
    
    /**
     * Mark lead as converting (in progress)
     */
    private function mark_lead_as_converting($session_id, $form_data) {
        global $wpdb;
        
        BSP_Database_Unified::init_tables();
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        // Find the incomplete lead
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY created_at DESC LIMIT 1",
            $session_id
        ));
        
        if ($lead) {
            // Update the lead with final form data using centralized processor
            $final_form_data = $this->data_processor->sanitize_form_data($form_data, 'booking');
            
            $update_data = [
                'final_form_data' => json_encode($final_form_data),
                'completion_percentage' => 100,
                'is_complete' => 1,
                'conversion_timestamp' => current_time('mysql'),
                'last_updated' => current_time('mysql')
            ];
            
            $result = $wpdb->update(
                $table_name,
                $update_data,
                ['id' => $lead->id],
                ['%s', '%d', '%d', '%s', '%s'],
                ['%d']
            );
            
            bsp_lead_log("Lead marked as converting", [
                'lead_id' => $lead->id,
                'session_id' => $session_id,
                'update_result' => $result
            ], 'CONVERSION_PROGRESS');
        }
    }
    
    /**
     * Complete the lead conversion process
     */
    private function complete_lead_conversion($session_id, $booking_id, $booking_data) {
        global $wpdb;
        
        BSP_Database_Unified::init_tables();
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        // Find the incomplete lead
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY created_at DESC LIMIT 1",
            $session_id
        ));
        
        if ($lead) {
            // Update with booking information
            $update_data = [
                'converted_to_booking' => 1,
                'booking_post_id' => $booking_id,
                'conversion_timestamp' => current_time('mysql'),
                'conversion_session_id' => $session_id,
                'last_updated' => current_time('mysql')
            ];
            
            $result = $wpdb->update(
                $table_name,
                $update_data,
                ['id' => $lead->id],
                ['%d', '%d', '%s', '%s', '%s'],
                ['%d']
            );
            
            if ($result) {
                // Calculate conversion metrics
                $this->calculate_conversion_metrics($lead, $booking_data);
                
                bsp_lead_log("Lead conversion completed successfully", [
                    'lead_id' => $lead->id,
                    'session_id' => $session_id,
                    'booking_id' => $booking_id,
                    'time_to_conversion' => $this->calculate_time_to_conversion($lead)
                ], 'CONVERSION_SUCCESS');
            }
        } else {
            // Create a new lead record for this conversion (retroactive tracking)
            $this->create_retroactive_lead_record($session_id, $booking_id, $booking_data);
            
            bsp_lead_log("Created retroactive lead record", [
                'session_id' => $session_id,
                'booking_id' => $booking_id
            ], 'RETROACTIVE_TRACKING');
        }
    }
    
    /**
     * Calculate conversion metrics using centralized scoring
     */
    private function calculate_conversion_metrics($lead, $booking_data) {
        // Use centralized data processor for consistent calculations
        $lead_array = (array) $lead;
        $merged_data = array_merge($lead_array, $booking_data);
        
        $metrics = [
            'lead_id' => $lead->id,
            'session_id' => $lead->session_id,
            'time_to_conversion' => $this->calculate_time_to_conversion($lead),
            'form_completion_percentage' => $this->data_processor->format_for_external_system($merged_data, 'google_sheets')['completion_percentage'],
            'service_type' => $lead->service ?? 'unknown',
            'utm_source' => $lead->utm_source ?? 'unknown',
            'conversion_value' => $this->estimate_conversion_value($booking_data),
            'lead_score' => $this->data_processor->format_for_external_system($merged_data, 'google_sheets')['lead_score']
        ];
        
        // Store metrics in WordPress options for reporting
        $existing_metrics = get_option('bsp_conversion_metrics', []);
        $existing_metrics[] = $metrics;
        
        // Keep only last 1000 conversion records
        if (count($existing_metrics) > 1000) {
            $existing_metrics = array_slice($existing_metrics, -1000);
        }
        
        update_option('bsp_conversion_metrics', $existing_metrics);
        
        bsp_lead_log("Conversion metrics calculated", $metrics, 'METRICS');
    }
    
    /**
     * Calculate time to conversion
     */
    private function calculate_time_to_conversion($lead) {
        $lead_created = new DateTime($lead->created_at);
        $conversion_time = new DateTime($lead->conversion_timestamp ?? current_time('mysql'));
        
        $interval = $lead_created->diff($conversion_time);
        
        return [
            'minutes' => ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i,
            'hours' => round((($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i) / 60, 2),
            'days' => $interval->days + ($interval->h / 24)
        ];
    }
    
    /**
     * Estimate conversion value
     */
    private function estimate_conversion_value($booking_data) {
        // This is a placeholder - you can customize based on your business logic
        $service_values = [
            'Roof' => 15000,
            'Windows' => 8000,
            'Bathroom' => 12000,
            'Kitchen' => 18000,
            'Siding' => 10000,
            'Decks' => 5000,
            'ADU' => 25000
        ];
        
        $service = $booking_data['service'] ?? $booking_data['service_type'] ?? 'unknown';
        return $service_values[$service] ?? 0;
    }
    
    /**
     * Create retroactive lead record for bookings without prior lead tracking
     */
    private function create_retroactive_lead_record($session_id, $booking_id, $booking_data) {
        global $wpdb;
        
        BSP_Database_Unified::init_tables();
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        // Get UTM data from UTM Manager
        $utm_manager = BSP_UTM_Consistency_Manager::get_instance();
        $utm_data = $utm_manager->get_utm_for_lead_capture();
        
        $lead_data = [
            'session_id' => $session_id,
            'service' => $booking_data['service'] ?? $booking_data['service_type'] ?? '',
            'zip_code' => $booking_data['zip_code'] ?? '',
            'customer_name' => $booking_data['full_name'] ?? $booking_data['customer_name'] ?? '',
            'customer_email' => $booking_data['email'] ?? $booking_data['customer_email'] ?? '',
            'customer_phone' => $booking_data['phone'] ?? $booking_data['customer_phone'] ?? '',
            'completion_percentage' => 100,
            'lead_type' => 'retroactive',
            'utm_source' => $utm_data['utm_source'] ?? '',
            'utm_medium' => $utm_data['utm_medium'] ?? '',
            'utm_campaign' => $utm_data['utm_campaign'] ?? '',
            'utm_term' => $utm_data['utm_term'] ?? '',
            'utm_content' => $utm_data['utm_content'] ?? '',
            'gclid' => $utm_data['gclid'] ?? '',
            'referrer' => $utm_data['referrer'] ?? '',
            'final_form_data' => json_encode($booking_data),
            'is_complete' => 1,
            'converted_to_booking' => 1,
            'booking_post_id' => $booking_id,
            'conversion_timestamp' => current_time('mysql'),
            'conversion_session_id' => $session_id,
            'created_at' => current_time('mysql'),
            'last_updated' => current_time('mysql')
        ];
        
        $result = $wpdb->insert(
            $table_name,
            $lead_data,
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );
        
        if ($result) {
            bsp_lead_log("Retroactive lead record created", [
                'lead_id' => $wpdb->insert_id,
                'session_id' => $session_id,
                'booking_id' => $booking_id
            ], 'RETROACTIVE_SUCCESS');
        }
    }
    
    /**
     * Generate session ID from form data
     */
    private function generate_session_id_from_data($data) {
        $identifier_parts = [];
        
        if (!empty($data['email'])) {
            $identifier_parts[] = $data['email'];
        }
        if (!empty($data['phone'])) {
            $identifier_parts[] = $data['phone'];
        }
        if (!empty($data['service'])) {
            $identifier_parts[] = $data['service'];
        }
        
        if (empty($identifier_parts)) {
            return null;
        }
        
        $identifier = implode('|', $identifier_parts);
        return 'bsp_' . hash('sha256', $identifier . date('Y-m-d'));
    }
    
    /**
     * Get conversion statistics
     */
    public function get_conversion_stats($days = 30) {
        global $wpdb;
        
        BSP_Database_Unified::init_tables();
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        $date_from = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // Total leads
        $total_leads = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE created_at >= %s",
            $date_from
        ));
        
        // Converted leads
        $converted_leads = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE created_at >= %s AND converted_to_booking = 1",
            $date_from
        ));
        
        // Conversion rate
        $conversion_rate = $total_leads > 0 ? ($converted_leads / $total_leads) * 100 : 0;
        
        // Average time to conversion
        $avg_conversion_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, conversion_timestamp)) 
             FROM $table_name 
             WHERE created_at >= %s AND converted_to_booking = 1",
            $date_from
        ));
        
        return [
            'period_days' => $days,
            'total_leads' => intval($total_leads),
            'converted_leads' => intval($converted_leads),
            'conversion_rate' => round($conversion_rate, 2),
            'avg_conversion_time_minutes' => round($avg_conversion_time ?? 0, 2)
        ];
    }
    
    /**
     * Clean up old incomplete leads
     */
    public function cleanup_old_leads() {
        global $wpdb;
        
        BSP_Database_Unified::init_tables();
        $table_name = BSP_Database_Unified::$tables['incomplete_leads'];
        
        // Delete unconverted leads older than 30 days
        $cleanup_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $deleted_count = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s AND converted_to_booking = 0",
            $cleanup_date
        ));
        
        bsp_debug_log("Old incomplete leads cleaned up", 'CLEANUP', [
            'deleted_count' => $deleted_count,
            'cleanup_date' => $cleanup_date
        ]);
    }
}

// Initialize the Lead Conversion Tracker
BSP_Lead_Conversion_Tracker::get_instance();
