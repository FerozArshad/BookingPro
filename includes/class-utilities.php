<?php
/**
 * Utilities for Booking System Pro
 */

if (!defined('ABSPATH')) exit;

class BSP_Utilities {
    
    /**
     * Format date for display
     */
    public static function format_date($date, $format = null) {
        if (!$format) {
            $format = get_option('date_format', 'Y-m-d');
        }
        
        if (is_string($date)) {
            $date = strtotime($date);
        }
        
        return date($format, $date);
    }
    
    /**
     * Format time for display
     */
    public static function format_time($time, $format = null) {
        if (!$format) {
            $format = get_option('time_format', 'H:i');
        }
        
        if (is_string($time)) {
            $time = strtotime($time);
        }
        
        return date($format, $time);
    }
    
    /**
     * Format currency
     */
    public static function format_currency($amount, $currency = 'USD') {
        $currency_symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥'
        ];
        
        $symbol = $currency_symbols[$currency] ?? '$';
        return $symbol . number_format($amount, 2);
    }
    
    /**
     * Generate booking reference
     */
    public static function generate_booking_reference($booking_id = null) {
        $prefix = get_option('bsp_booking_reference_prefix', 'BSP');
        $timestamp = time();
        $random = wp_rand(1000, 9999);
        
        if ($booking_id) {
            return $prefix . '-' . $booking_id . '-' . $random;
        }
        
        return $prefix . '-' . $timestamp . '-' . $random;
    }
    
    /**
     * Validate email address
     */
    public static function validate_email($email) {
        return is_email($email);
    }
    
    /**
     * Validate phone number
     */
    public static function validate_phone($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if phone number has at least 10 digits
        return strlen($phone) >= 10;
    }
    
    /**
     * Sanitize booking data
     */
    public static function sanitize_booking_data($data) {
        $sanitized = [];
        
        $fields = [
            'customer_name' => 'sanitize_text_field',
            'customer_email' => 'sanitize_email',
            'customer_phone' => 'sanitize_text_field',
            'service_id' => 'intval',
            'company_id' => 'intval',
            'appointment_date' => 'sanitize_text_field',
            'appointment_time' => 'sanitize_text_field',
            'status' => 'sanitize_text_field',
            'notes' => 'sanitize_textarea_field'
        ];
        
        foreach ($fields as $field => $sanitize_function) {
            if (isset($data[$field])) {
                $sanitized[$field] = $sanitize_function($data[$field]);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get available time slots
     */
    public static function get_available_time_slots($date, $service_id, $company_id) {
        $db = BSP_Database_Unified::get_instance();
        
        // Get business hours for the company
        $business_hours = $db->get_company_business_hours($company_id);
        
        // Get existing bookings for the date
        $existing_bookings = $db->get_bookings([
            'appointment_date' => $date,
            'service_id' => $service_id,
            'company_id' => $company_id,
            'status' => ['confirmed', 'pending']
        ]);
        
        // Get service duration
        $service = $db->get_service($service_id);
        $duration = $service['duration'] ?? 60; // Default 60 minutes
        
        // Generate time slots
        $slots = [];
        $day_of_week = strtolower(date('l', strtotime($date)));
        
        if (isset($business_hours[$day_of_week])) {
            $start_time = $business_hours[$day_of_week]['start'];
            $end_time = $business_hours[$day_of_week]['end'];
            
            $current_time = strtotime($start_time);
            $end_timestamp = strtotime($end_time);
            
            while ($current_time + ($duration * 60) <= $end_timestamp) {
                $slot_time = date('H:i', $current_time);
                
                // Check if slot is available
                $is_available = true;
                foreach ($existing_bookings as $booking) {
                    if ($booking['appointment_time'] === $slot_time) {
                        $is_available = false;
                        break;
                    }
                }
                
                if ($is_available) {
                    $slots[] = [
                        'time' => $slot_time,
                        'display' => date('g:i A', $current_time),
                        'available' => true
                    ];
                }
                
                $current_time += ($duration * 60);
            }
        }
        
        return $slots;
    }
    
    /**
     * Calculate booking duration
     */
    public static function calculate_duration($start_time, $end_time) {
        $start = strtotime($start_time);
        $end = strtotime($end_time);
        
        return ($end - $start) / 60; // Return in minutes
    }
    
    /**
     * Check if date is available
     */
    public static function is_date_available($date, $company_id) {
        // Check if date is not in the past
        if (strtotime($date) < strtotime('today')) {
            return false;
        }
        
        // Check business hours
        $db = BSP_Database_Unified::get_instance();
        $business_hours = $db->get_company_business_hours($company_id);
        $day_of_week = strtolower(date('l', strtotime($date)));
        
        return isset($business_hours[$day_of_week]) && $business_hours[$day_of_week]['is_open'];
    }
    
    /**
     * Get booking status options
     */
    public static function get_booking_statuses() {
        return apply_filters('bsp_booking_statuses', [
            'pending' => __('Pending', 'booking-system-pro'),
            'confirmed' => __('Confirmed', 'booking-system-pro'),
            'completed' => __('Completed', 'booking-system-pro'),
            'cancelled' => __('Cancelled', 'booking-system-pro'),
            'no_show' => __('No Show', 'booking-system-pro')
        ]);
    }
    
    /**
     * Get service categories
     */
    public static function get_service_categories() {
        $terms = get_terms([
            'taxonomy' => 'bsp_service_category',
            'hide_empty' => false
        ]);
        
        $categories = [];
        foreach ($terms as $term) {
            $categories[$term->term_id] = $term->name;
        }
        
        return $categories;
    }
    
    /**
     * Get company locations
     */
    public static function get_company_locations() {
        $terms = get_terms([
            'taxonomy' => 'bsp_location',
            'hide_empty' => false
        ]);
        
        $locations = [];
        foreach ($terms as $term) {
            $locations[$term->term_id] = $term->name;
        }
        
        return $locations;
    }
    
    /**
     * Log booking activity
     */
    public static function log_activity($message, $booking_id = null, $type = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = sprintf(
                '[%s] BSP: %s (Booking ID: %s)',
                date('Y-m-d H:i:s'),
                $message,
                $booking_id ?: 'N/A'
            );
            
            error_log($log_message);
        }
    }
    
    /**
     * Send webhook notification
     */
    public static function send_webhook($url, $data, $event = 'booking.created') {
        if (empty($url)) {
            return false;
        }
        
        $payload = [
            'event' => $event,
            'timestamp' => time(),
            'data' => $data
        ];
        
        $response = wp_remote_post($url, [
            'body' => json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        return !is_wp_error($response);
    }
    
    /**
     * Generate calendar events
     */
    public static function generate_ical_event($booking) {
        $start_datetime = $booking['appointment_date'] . ' ' . $booking['appointment_time'];
        $start = date('Ymd\THis', strtotime($start_datetime));
        
        // Assume 1 hour duration if not specified
        $duration = $booking['duration'] ?? 60;
        $end = date('Ymd\THis', strtotime($start_datetime . ' +' . $duration . ' minutes'));
        
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//Booking System Pro//EN\r\n";
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . uniqid() . "@bookingsystempro.com\r\n";
        $ical .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $ical .= "DTSTART:" . $start . "\r\n";
        $ical .= "DTEND:" . $end . "\r\n";
        $ical .= "SUMMARY:" . $booking['service_name'] . "\r\n";
        $ical .= "DESCRIPTION:Booking with " . $booking['customer_name'] . "\r\n";
        $ical .= "END:VEVENT\r\n";
        $ical .= "END:VCALENDAR\r\n";
        
        return $ical;
    }
}
