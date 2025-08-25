<?php
/**
 * Email Management for Booking System Pro
 */

if (!defined('ABSPATH')) exit;

class BSP_Email {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_insert_post', [$this, 'on_booking_created'], 10, 3);
        add_filter('wp_mail_content_type', [$this, 'set_html_content_type']);
    }
    
    /**
     * Handle new booking creation
     */
    public function on_booking_created($post_id, $post, $update) {
        // Only process new bookings (not updates)
        if ($update || $post->post_type !== 'bsp_booking') {
            return;
        }
        
        $this->send_booking_emails($post_id);
    }
    
    /**
     * Send booking confirmation and admin notification emails
     */
    public function send_booking_emails($booking_id) {
        $booking_data = [
            'id' => $booking_id,
            'customer_name' => get_post_meta($booking_id, '_customer_name', true),
            'customer_email' => get_post_meta($booking_id, '_customer_email', true),
            'customer_phone' => get_post_meta($booking_id, '_customer_phone', true),
            'service' => get_post_meta($booking_id, '_service_type', true),
            'company' => get_post_meta($booking_id, '_company_name', true),
            'date' => get_post_meta($booking_id, '_booking_date', true),
            'time' => get_post_meta($booking_id, '_booking_time', true),
            'address' => get_post_meta($booking_id, '_customer_address', true),
            'zip_code' => get_post_meta($booking_id, '_zip_code', true),
            'city' => get_post_meta($booking_id, '_city', true),
            'state' => get_post_meta($booking_id, '_state', true),
            'appointments' => get_post_meta($booking_id, '_appointments', true),
            'marketing_source' => get_post_meta($booking_id, '_marketing_source', true),
            // Service-specific fields
            'roof_zip' => get_post_meta($booking_id, '_roof_zip', true),
            'windows_zip' => get_post_meta($booking_id, '_windows_zip', true),
            'bathroom_zip' => get_post_meta($booking_id, '_bathroom_zip', true),
            'siding_zip' => get_post_meta($booking_id, '_siding_zip', true),
            'kitchen_zip' => get_post_meta($booking_id, '_kitchen_zip', true),
            'decks_zip' => get_post_meta($booking_id, '_decks_zip', true),
            'adu_zip' => get_post_meta($booking_id, '_adu_zip', true),
            'roof_action' => get_post_meta($booking_id, '_roof_action', true),
            'roof_material' => get_post_meta($booking_id, '_roof_material', true),
            'windows_action' => get_post_meta($booking_id, '_windows_action', true),
            'windows_replace_qty' => get_post_meta($booking_id, '_windows_replace_qty', true),
            'windows_repair_needed' => get_post_meta($booking_id, '_windows_repair_needed', true),
            'bathroom_option' => get_post_meta($booking_id, '_bathroom_option', true),
            'siding_option' => get_post_meta($booking_id, '_siding_option', true),
            'siding_material' => get_post_meta($booking_id, '_siding_material', true),
            'kitchen_action' => get_post_meta($booking_id, '_kitchen_action', true),
            'kitchen_component' => get_post_meta($booking_id, '_kitchen_component', true),
            'decks_action' => get_post_meta($booking_id, '_decks_action', true),
            'decks_material' => get_post_meta($booking_id, '_decks_material', true),
            'adu_action' => get_post_meta($booking_id, '_adu_action', true),
            'adu_type' => get_post_meta($booking_id, '_adu_type', true)
        ];
        
        // Send to customer
        $this->send_customer_confirmation($booking_data);
        
        // Send to admin
        $this->send_admin_notification($booking_data);
    }
    
    /**
     * Send booking update emails
     */
    public function send_booking_update_emails($booking_id) {
        $db = BSP_Database_Unified::get_instance();
        $booking = $db->get_booking($booking_id);
        
        if (!$booking) {
            return false;
        }
        
        // Send to customer
        $this->send_customer_update($booking);
        
        // Send to admin
        $this->send_admin_notification($booking, 'updated');
        
        return true;
    }
    
    /**
     * Send customer confirmation email
     */
    private function send_customer_confirmation($booking) {
        if (empty($booking['customer_email'])) {
            error_log('BSP: No customer email provided for booking');
            return false;
        }
        
        // Check if customer notifications are enabled
        $email_settings = get_option('bsp_email_settings', []);
        if (!($email_settings['enabled'] ?? true)) {
            error_log('BSP: Email notifications are disabled');
            return false;
        }
        
        if (!($email_settings['customer_confirmation'] ?? true)) {
            error_log('BSP: Customer confirmation emails are disabled');
            return false;
        }
        
        error_log('BSP: Attempting to send customer confirmation email to ' . $booking['customer_email']);
        
        $to = $booking['customer_email'];
        $subject = 'Booking Confirmation - ' . $booking['service'];
        $message = $this->get_customer_email_template($booking);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        $result = wp_mail($to, $subject, $message, $headers);
        
        // Log email result for debugging
        if ($result) {
            error_log('BSP: Customer confirmation email sent to ' . $to);
        } else {
            error_log('BSP: Failed to send customer confirmation email to ' . $to);
        }
        
        return $result;
    }
    
    /**
     * Send admin notification email
     */
    private function send_admin_notification($booking) {
        // Check if admin notifications are enabled
        $email_settings = get_option('bsp_email_settings', []);
        if (!($email_settings['enabled'] ?? true) || !($email_settings['admin_notifications'] ?? true)) {
            return false;
        }
        
        // Get admin email recipients
        $recipients = $this->get_admin_recipients();
        if (empty($recipients)) {
            return false;
        }
        
        $subject = 'New Booking Received - ' . $booking['service'];
        $message = $this->get_admin_email_template($booking);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        $success = true;
        foreach ($recipients as $email) {
            $result = wp_mail(trim($email), $subject, $message, $headers);
            if ($result) {
                error_log('BSP: Admin notification email sent to ' . $email);
            } else {
                error_log('BSP: Failed to send admin notification email to ' . $email);
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get admin email recipients
     */
    private function get_admin_recipients() {
        $email_settings = get_option('bsp_email_settings', []);
        $recipients = [];
        
        // Add primary admin email
        $admin_email = $email_settings['admin_email'] ?? get_option('admin_email');
        if (!empty($admin_email) && is_email($admin_email)) {
            $recipients[] = $admin_email;
        }
        
        // Add additional recipients
        $additional = $email_settings['additional_recipients'] ?? '';
        if (!empty($additional)) {
            $additional_emails = array_map('trim', explode(',', $additional));
            foreach ($additional_emails as $email) {
                if (!empty($email) && is_email($email)) {
                    $recipients[] = $email;
                }
            }
        }
        
        return array_unique($recipients);
    }
    
    /**
     * Get formatted service-specific details
     */
    private function get_service_details($booking) {
        $service = strtolower($booking['service']);
        $details = [];
        
        switch ($service) {
            case 'roof':
                if (!empty($booking['roof_action'])) {
                    $details[] = '<strong>Action:</strong> ' . $booking['roof_action'];
                }
                if (!empty($booking['roof_material'])) {
                    $details[] = '<strong>Material:</strong> ' . $booking['roof_material'];
                }
                break;
                
            case 'windows':
                if (!empty($booking['windows_action'])) {
                    $details[] = '<strong>Action:</strong> ' . $booking['windows_action'];
                }
                if (!empty($booking['windows_replace_qty'])) {
                    $details[] = '<strong>Quantity:</strong> ' . $booking['windows_replace_qty'];
                }
                if (!empty($booking['windows_repair_needed'])) {
                    $details[] = '<strong>Repair Alternative:</strong> ' . $booking['windows_repair_needed'];
                }
                break;
                
            case 'bathroom':
                if (!empty($booking['bathroom_option'])) {
                    $details[] = '<strong>Service Type:</strong> ' . $booking['bathroom_option'];
                }
                break;
                
            case 'siding':
                if (!empty($booking['siding_option'])) {
                    $details[] = '<strong>Service Type:</strong> ' . $booking['siding_option'];
                }
                if (!empty($booking['siding_material'])) {
                    $details[] = '<strong>Material:</strong> ' . $booking['siding_material'];
                }
                break;
                
            case 'kitchen':
                if (!empty($booking['kitchen_action'])) {
                    $details[] = '<strong>Action:</strong> ' . $booking['kitchen_action'];
                }
                if (!empty($booking['kitchen_component'])) {
                    $details[] = '<strong>Component:</strong> ' . $booking['kitchen_component'];
                }
                break;
                
            case 'decks':
                if (!empty($booking['decks_action'])) {
                    $details[] = '<strong>Action:</strong> ' . $booking['decks_action'];
                }
                if (!empty($booking['decks_material'])) {
                    $details[] = '<strong>Material:</strong> ' . $booking['decks_material'];
                }
                break;
        }
        
        return empty($details) ? '' : '<p>' . implode('<br>', $details) . '</p>';
    }

    /**
     * Get ZIP code with fallback to service-specific ZIP codes
     */
    private function get_zip_code($booking) {
        // Check main ZIP field first
        if (!empty($booking['zip_code'])) {
            return $booking['zip_code'];
        }
        
        // Check service-specific ZIP codes
        $zip_fields = ['roof_zip', 'windows_zip', 'bathroom_zip', 'siding_zip', 'kitchen_zip', 'decks_zip'];
        foreach ($zip_fields as $field) {
            if (!empty($booking[$field])) {
                return $booking[$field];
            }
        }
        
        return 'Not provided';
    }

    /**
     * Format appointments data for display
     */
    private function format_appointments($appointments_json) {
        if (empty($appointments_json)) {
            return 'Single appointment scheduled';
        }
        
        $appointments = json_decode($appointments_json, true);

        if (empty($appointments) || !is_array($appointments)) {
            return 'Single appointment scheduled';
        }
        
        $formatted = [];
        foreach ($appointments as $appointment) {
            if (is_array($appointment)) {
                $company = isset($appointment['company']) ? $appointment['company'] : 'Company not specified';
                $date = isset($appointment['date']) ? date('M j, Y', strtotime($appointment['date'])) : 'Date not set';
                $time = isset($appointment['time']) ? date('g:i A', strtotime($appointment['time'])) : 'Time not set';
                $formatted[] = "{$company} - {$date} at {$time}";
            }
        }
        
        return empty($formatted) ? 'Multiple appointments scheduled' : implode('<br>', $formatted);
    }

    /**
     * Get customer email template
     */
    private function get_customer_email_template($booking) {
        $site_name = get_bloginfo('name');
        $formatted_date = date('l, F j, Y', strtotime($booking['date']));
        $formatted_time = date('g:i A', strtotime($booking['time']));
        
        // Get ZIP code with fallback to service-specific ZIP
        $zip_code = $this->get_zip_code($booking);
        $appointments_formatted = $this->format_appointments($booking['appointments']);
        $service_details = $this->get_service_details($booking);
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                <h2 style='color: #4CAF50; margin-bottom: 20px; text-align: center;'>Booking Confirmation</h2>
                
                <p>Dear {$booking['customer_name']},</p>
                
                <p>Thank you for choosing us! Your booking has been confirmed. Here are your appointment details:</p>
                
                <div style='background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4CAF50;'>
                    <h3 style='margin-top: 0; color: #4CAF50;'>Appointment Details</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Service:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$booking['service']}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Company:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$booking['company']}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Date:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$formatted_date}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Time:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$formatted_time}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Address:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$booking['address']}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>ZIP Code:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$zip_code}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>City:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$booking['city']}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>State:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$booking['state']}</td></tr>
                        <tr><td style='padding: 8px 0;'><strong>Appointments:</strong></td><td style='padding: 8px 0;'>{$appointments_formatted}</td></tr>
                    </table>
                </div>
                " . (!empty($service_details) ? "
                <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                    <h4 style='margin-top: 0; color: #856404;'>Service Specifications</h4>
                    {$service_details}
                </div>
                " : "") . "
                <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='margin-top: 0; color: #1976d2;'>What to Expect</h4>
                    <p style='margin-bottom: 0;'>Our professional team will arrive at the scheduled time. Please ensure someone is available at the property address. If you need to reschedule or have any questions, please contact us as soon as possible.</p>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <p style='font-size: 16px; color: #666;'>Need to make changes or have questions?</p>
                    <p style='font-size: 18px; font-weight: bold; color: #4CAF50;'>Contact us at: " . get_option('admin_email') . "</p>
                </div>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                
                <p style='text-align: center; color: #666; font-size: 14px;'>
                    Best regards,<br>
                    <strong>{$site_name} Team</strong><br>
                    Thank you for trusting us with your service needs!
                </p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get admin email template
     */
    private function get_admin_email_template($booking) {
        $site_name = get_bloginfo('name');
        $formatted_date = date('l, F j, Y', strtotime($booking['date']));
        $formatted_time = date('g:i A', strtotime($booking['time']));
        $booking_url = admin_url('post.php?post=' . $booking['id'] . '&action=edit');
        
        // Get ZIP code with fallback to service-specific ZIP
        $zip_code = $this->get_zip_code($booking);
        $appointments_formatted = $this->format_appointments($booking['appointments']);
        $customer_email = !empty($booking['customer_email']) ? $booking['customer_email'] : 'Not provided';
        $customer_phone = !empty($booking['customer_phone']) ? $booking['customer_phone'] : 'Not provided';
        $service_details = $this->get_service_details($booking);
        
        $marketing_source_html = '';
        if (!empty($booking['marketing_source']) && is_array($booking['marketing_source'])) {
            $marketing_source_html .= "<div style='background: #f0f4f8; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #3f51b5;'>";
            $marketing_source_html .= "<h3 style='margin-top: 0; color: #1a237e;'>📈 Marketing Source</h3>";
            $marketing_source_html .= "<table style='width: 100%; border-collapse: collapse;'>";
            foreach ($booking['marketing_source'] as $key => $value) {
                if (!empty($value)) {
                    $marketing_source_html .= "<tr><td style='padding: 8px 0; border-bottom: 1px solid #c5cae9; width: 30%;'><strong>" . esc_html(ucwords(str_replace('_', ' ', $key))) . ":</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #c5cae9;'>" . esc_html($value) . "</td></tr>";
                }
            }
            $marketing_source_html .= "</table></div>";
        }

        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 700px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                <h2 style='color: #ff6b6b; margin-bottom: 20px; text-align: center;'>🆕 New Booking Alert</h2>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                    <h3 style='margin-top: 0; color: #856404;'>Action Required</h3>
                    <p style='margin-bottom: 0;'>A new booking has been submitted and requires your attention. Please review and confirm the appointment details below.</p>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #495057;'>📋 Booking Information</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #ddd; width: 30%;'><strong>Booking ID:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #ddd;'>#{$booking['id']}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #ddd;'><strong>Service:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #ddd;'>{$booking['service']}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #ddd;'><strong>Company:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #ddd;'>{$booking['company']}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #ddd;'><strong>Date:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #ddd;'>{$formatted_date}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #ddd;'><strong>Time:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #ddd;'>{$formatted_time}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #ddd;'><strong>Appointments:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #ddd;'>{$appointments_formatted}</td></tr>
                    </table>
                </div>
                
                <div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #2e7d32;'>👤 Customer Details</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #c8e6c9; width: 30%;'><strong>Name:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #c8e6c9;'>{$booking['customer_name']}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #c8e6c9;'><strong>Email:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #c8e6c9;'><a href='mailto:{$customer_email}' style='color: #1976d2;'>{$customer_email}</a></td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #c8e6c9;'><strong>Phone:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #c8e6c9;'><a href='tel:{$customer_phone}' style='color: #1976d2;'>{$customer_phone}</a></td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #c8e6c9;'><strong>Address:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #c8e6c9;'>{$booking['address']}</td></tr>
                        <tr><td style='padding: 8px 0;'><strong>ZIP Code:</strong></td><td style='padding: 8px 0;'>{$zip_code}</td></tr>
                    </table>
                </div>
                " . $marketing_source_html . "
                " . (!empty($service_details) ? "
                <div style='background: #fff8e1; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ff9800;'>
                    <h3 style='margin-top: 0; color: #e65100;'>🔧 Service Specifications</h3>
                    {$service_details}
                </div>
                " : "") . "
                <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='margin-top: 0; color: #1976d2;'>📅 Next Steps</h4>
                    <ul style='margin-bottom: 0; padding-left: 20px;'>
                        <li>Review the booking details above</li>
                        <li>Confirm availability for the requested date and time</li>
                        <li>Contact the customer if any clarification is needed</li>
                        <li>Update the booking status in the admin dashboard</li>
                    </ul>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$booking_url}' style='display: inline-block; background: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>📊 View Booking Details</a>
                </div>
                
                <div style='text-align: center; margin: 30px 0; padding: 20px; background: #f1f3f4; border-radius: 8px;'>
                    <p style='margin: 0; font-size: 14px; color: #5f6368;'>
                        📊 Manage this booking in your <a href='" . admin_url('admin.php?page=bsp-bookings') . "' style='color: #1976d2; text-decoration: none;'>Admin Dashboard</a>
                    </p>
                </div>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                
                <p style='text-align: center; color: #666; font-size: 14px;'>
                    This is an automated notification from <strong>{$site_name}</strong><br>
                    Booking submitted on " . date('F j, Y \a\t g:i A') . "
                </p>
            </div>
        </body>
        </html>";
    }    
    /**
     * Set HTML content type for emails
     */
    public function set_html_content_type() {
        return 'text/html';
    }
}
