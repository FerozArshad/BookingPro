<?php
/**
 * Post Types Management for Unified Booking System
 * Handles custom post types with enhanced admin functionality
 */

if (!defined('ABSPATH')) exit;

class BSP_Post_Types {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'register_post_types']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_boxes']);
        add_filter('manage_bsp_booking_posts_columns', [$this, 'booking_columns']);
        add_action('manage_bsp_booking_posts_custom_column', [$this, 'booking_column_content'], 10, 2);
        add_filter('post_row_actions', [$this, 'booking_row_actions'], 10, 2);
    }
    
    /**
     * Register custom post types
     */
    public function register_post_types() {
        $this->register_booking_post_type();
    }
    
    /**
     * Register booking post type
     */
    private function register_booking_post_type() {
        $labels = [
            'name' => __('Bookings', 'booking-system-pro'),
            'singular_name' => __('Booking', 'booking-system-pro'),
            'menu_name' => __('Booking System', 'booking-system-pro'),
            'name_admin_bar' => __('Booking', 'booking-system-pro'),
            'add_new' => __('Add New Booking', 'booking-system-pro'),
            'add_new_item' => __('Add New Booking', 'booking-system-pro'),
            'new_item' => __('New Booking', 'booking-system-pro'),
            'edit_item' => __('Edit Booking', 'booking-system-pro'),
            'view_item' => __('View Booking', 'booking-system-pro'),
            'all_items' => __('All Bookings', 'booking-system-pro'),
            'search_items' => __('Search Bookings', 'booking-system-pro'),
            'parent_item_colon' => __('Parent Bookings:', 'booking-system-pro'),
            'not_found' => __('No bookings found.', 'booking-system-pro'),
            'not_found_in_trash' => __('No bookings found in Trash.', 'booking-system-pro'),
            'featured_image' => __('Booking Image', 'booking-system-pro'),
            'set_featured_image' => __('Set booking image', 'booking-system-pro'),
            'remove_featured_image' => __('Remove booking image', 'booking-system-pro'),
            'use_featured_image' => __('Use as booking image', 'booking-system-pro'),
            'archives' => __('Booking Archives', 'booking-system-pro'),
            'insert_into_item' => __('Insert into booking', 'booking-system-pro'),
            'uploaded_to_this_item' => __('Uploaded to this booking', 'booking-system-pro'),
            'filter_items_list' => __('Filter bookings list', 'booking-system-pro'),
            'items_list_navigation' => __('Bookings list navigation', 'booking-system-pro'),
            'items_list' => __('Bookings list', 'booking-system-pro'),
        ];
        
        $args = [
            'labels' => $labels,
            'description' => __('Booking management system', 'booking-system-pro'),
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-calendar-alt',
            'query_var' => true,
            'rewrite' => false,
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'manage_options',
                'edit_posts' => 'manage_options',
                'edit_others_posts' => 'manage_options',
                'publish_posts' => 'manage_options',
                'read_private_posts' => 'manage_options',
                'delete_posts' => 'manage_options',
                'delete_private_posts' => 'manage_options',
                'delete_published_posts' => 'manage_options',
                'delete_others_posts' => 'manage_options',
                'edit_private_posts' => 'manage_options',
                'edit_published_posts' => 'manage_options',
            ],
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title', 'custom-fields'],
            'taxonomies' => ['bsp_service_type', 'bsp_booking_status', 'bsp_company_category'],
            'show_in_rest' => false,
        ];
        
        register_post_type('bsp_booking', $args);
    }
    
    /**
     * Add meta boxes to booking post type
     */
    public function add_meta_boxes() {
        add_meta_box(
            'bsp_booking_details',
            __('Booking Details', 'booking-system-pro'),
            [$this, 'booking_details_meta_box'],
            'bsp_booking',
            'normal',
            'high'
        );
        
        add_meta_box(
            'bsp_customer_details',
            __('Customer Details', 'booking-system-pro'),
            [$this, 'customer_details_meta_box'],
            'bsp_booking',
            'normal',
            'high'
        );
        
        add_meta_box(
            'bsp_company_details',
            __('Company Details', 'booking-system-pro'),
            [$this, 'company_details_meta_box'],
            'bsp_booking',
            'side',
            'default'
        );
        
        add_meta_box(
            'bsp_email_settings',
            __('Email Settings', 'booking-system-pro'),
            [$this, 'email_settings_meta_box'],
            'bsp_booking',
            'side',
            'low'
        );
        
        add_meta_box(
            'bsp_booking_actions',
            __('Booking Actions', 'booking-system-pro'),
            [$this, 'booking_actions_meta_box'],
            'bsp_booking',
            'side',
            'high'
        );
    }
    
    /**
     * Booking details meta box
     */
    public function booking_details_meta_box($post) {
        wp_nonce_field('bsp_booking_meta_box', 'bsp_booking_meta_box_nonce');
        
        $booking_id = get_post_meta($post->ID, '_bsp_booking_id', true);
        $booking_data = null;
        
        if ($booking_id) {
            global $wpdb;
            $table = BSP_Database_Unified::get_table('bookings');
            $booking_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $booking_id));
        }
        
        $booking_number = $booking_data ? $booking_data->booking_number : get_post_meta($post->ID, '_bsp_booking_number', true);
        $booking_date = $booking_data ? $booking_data->booking_date : get_post_meta($post->ID, '_bsp_booking_date', true);
        $booking_time = $booking_data ? $booking_data->booking_time : get_post_meta($post->ID, '_bsp_booking_time', true);
        $service_type = $booking_data ? $booking_data->service_type : get_post_meta($post->ID, '_bsp_service_type', true);
        $special_requests = $booking_data ? $booking_data->special_requests : '';
        $admin_notes = $booking_data ? $booking_data->admin_notes : '';
        $status = $booking_data ? $booking_data->status : 'pending';
        $payment_status = $booking_data ? $booking_data->payment_status : 'unpaid';
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="booking_number"><?php _e('Booking Number', 'booking-system-pro'); ?></label></th>
                <td>
                    <input type="text" id="booking_number" name="booking_number" value="<?php echo esc_attr($booking_number); ?>" readonly class="regular-text" />
                    <p class="description"><?php _e('Unique booking identifier', 'booking-system-pro'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="booking_date"><?php _e('Booking Date', 'booking-system-pro'); ?></label></th>
                <td>
                    <input type="date" id="booking_date" name="booking_date" value="<?php echo esc_attr($booking_date); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="booking_time"><?php _e('Booking Time', 'booking-system-pro'); ?></label></th>
                <td>
                    <input type="time" id="booking_time" name="booking_time" value="<?php echo esc_attr($booking_time); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="service_type"><?php _e('Service Type', 'booking-system-pro'); ?></label></th>
                <td>
                    <input type="text" id="service_type" name="service_type" value="<?php echo esc_attr($service_type); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="status"><?php _e('Status', 'booking-system-pro'); ?></label></th>
                <td>
                    <select id="status" name="status" class="regular-text">
                        <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pending', 'booking-system-pro'); ?></option>
                        <option value="confirmed" <?php selected($status, 'confirmed'); ?>><?php _e('Confirmed', 'booking-system-pro'); ?></option>
                        <option value="in_progress" <?php selected($status, 'in_progress'); ?>><?php _e('In Progress', 'booking-system-pro'); ?></option>
                        <option value="completed" <?php selected($status, 'completed'); ?>><?php _e('Completed', 'booking-system-pro'); ?></option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php _e('Cancelled', 'booking-system-pro'); ?></option>
                        <option value="rescheduled" <?php selected($status, 'rescheduled'); ?>><?php _e('Rescheduled', 'booking-system-pro'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="payment_status"><?php _e('Payment Status', 'booking-system-pro'); ?></label></th>
                <td>
                    <select id="payment_status" name="payment_status" class="regular-text">
                        <option value="unpaid" <?php selected($payment_status, 'unpaid'); ?>><?php _e('Unpaid', 'booking-system-pro'); ?></option>
                        <option value="paid" <?php selected($payment_status, 'paid'); ?>><?php _e('Paid', 'booking-system-pro'); ?></option>
                        <option value="partial" <?php selected($payment_status, 'partial'); ?>><?php _e('Partial', 'booking-system-pro'); ?></option>
                        <option value="refunded" <?php selected($payment_status, 'refunded'); ?>><?php _e('Refunded', 'booking-system-pro'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="special_requests"><?php _e('Special Requests', 'booking-system-pro'); ?></label></th>
                <td>
                    <textarea id="special_requests" name="special_requests" rows="3" class="large-text"><?php echo esc_textarea($special_requests); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="admin_notes"><?php _e('Admin Notes', 'booking-system-pro'); ?></label></th>
                <td>
                    <textarea id="admin_notes" name="admin_notes" rows="4" class="large-text"><?php echo esc_textarea($admin_notes); ?></textarea>
                    <p class="description"><?php _e('Internal notes for admin use only', 'booking-system-pro'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Customer details meta box
     */
    public function customer_details_meta_box($post) {
        $booking_id = get_post_meta($post->ID, '_bsp_booking_id', true);
        $customer_data = null;
        
        if ($booking_id) {
            global $wpdb;
            $bookings_table = BSP_Database_Unified::get_table('bookings');
            $customers_table = BSP_Database_Unified::get_table('customers');
            
            $customer_data = $wpdb->get_row($wpdb->prepare("
                SELECT c.* FROM $customers_table c
                INNER JOIN $bookings_table b ON c.id = b.customer_id
                WHERE b.id = %d
            ", $booking_id));
        }
        
        if ($customer_data) {
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Name', 'booking-system-pro'); ?></th>
                    <td><?php echo esc_html($customer_data->first_name . ' ' . $customer_data->last_name); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Email', 'booking-system-pro'); ?></th>
                    <td><a href="mailto:<?php echo esc_attr($customer_data->email); ?>"><?php echo esc_html($customer_data->email); ?></a></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Phone', 'booking-system-pro'); ?></th>
                    <td><a href="tel:<?php echo esc_attr($customer_data->phone); ?>"><?php echo esc_html($customer_data->phone); ?></a></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Address', 'booking-system-pro'); ?></th>
                    <td><?php echo esc_html($customer_data->address); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('City, State ZIP', 'booking-system-pro'); ?></th>
                    <td><?php echo esc_html($customer_data->city . ', ' . $customer_data->state . ' ' . $customer_data->zip_code); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Customer Type', 'booking-system-pro'); ?></th>
                    <td>
                        <span class="bsp-customer-type bsp-customer-<?php echo esc_attr($customer_data->customer_type); ?>">
                            <?php echo esc_html(ucfirst($customer_data->customer_type)); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Total Bookings', 'booking-system-pro'); ?></th>
                    <td><?php echo esc_html($customer_data->total_bookings); ?></td>
                </tr>
            </table>
            <?php
        } else {
            echo '<p>' . __('No customer data available.', 'booking-system-pro') . '</p>';
        }
    }
    
    /**
     * Company details meta box
     */
    public function company_details_meta_box($post) {
        $company_id = get_post_meta($post->ID, '_bsp_company_id', true);
        $company_data = null;
        
        if ($company_id) {
            $company_data = BSP_Database_Unified::get_company($company_id);
        }
        
        if ($company_data) {
            ?>
            <div class="bsp-company-details">
                <h4><?php echo esc_html($company_data->name); ?></h4>
                <p><strong><?php _e('Rating:', 'booking-system-pro'); ?></strong> 
                   <span class="bsp-rating"><?php echo esc_html($company_data->rating); ?>/5.0</span>
                   (<?php echo esc_html($company_data->total_reviews); ?> reviews)
                </p>
                <p><strong><?php _e('Email:', 'booking-system-pro'); ?></strong> 
                   <a href="mailto:<?php echo esc_attr($company_data->email); ?>"><?php echo esc_html($company_data->email); ?></a>
                </p>
                <p><strong><?php _e('Phone:', 'booking-system-pro'); ?></strong> 
                   <a href="tel:<?php echo esc_attr($company_data->phone); ?>"><?php echo esc_html($company_data->phone); ?></a>
                </p>
                <p><strong><?php _e('Address:', 'booking-system-pro'); ?></strong><br>
                   <?php echo esc_html($company_data->address); ?>
                </p>
                <p><strong><?php _e('Category:', 'booking-system-pro'); ?></strong> 
                   <span class="bsp-category bsp-category-<?php echo esc_attr($company_data->category); ?>">
                       <?php echo esc_html(ucfirst($company_data->category)); ?>
                   </span>
                </p>
            </div>
            <?php
        } else {
            echo '<p>' . __('No company data available.', 'booking-system-pro') . '</p>';
        }
    }
    
    /**
     * Email settings meta box
     */
    public function email_settings_meta_box($post) {
        $email_settings = get_option('bsp_email_settings', []);
        
        // Get admin recipients
        $admin_email = $email_settings['admin_email'] ?? get_option('admin_email');
        $additional_recipients = $email_settings['additional_recipients'] ?? '';
        $recipients = [$admin_email];
        
        if (!empty($additional_recipients)) {
            $additional_emails = array_map('trim', explode(',', $additional_recipients));
            $recipients = array_merge($recipients, array_filter($additional_emails, 'is_email'));
        }
        
        $recipients = array_unique(array_filter($recipients));
        $total_recipients = count($recipients);
        
        ?>
        <div style="padding: 10px;">
            <h4 style="margin-top: 0;"><?php _e('Email Notification Status', 'booking-system-pro'); ?></h4>
            
            <div style="background: #f0f6fc; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                <strong><?php _e('Admin Recipients:', 'booking-system-pro'); ?></strong> <?php echo $total_recipients; ?><br>
                <small style="color: #666;">
                    <?php echo implode('<br>', array_map('esc_html', $recipients)); ?>
                </small>
            </div>
            
            <div style="background: <?php echo ($email_settings['enabled'] ?? true) ? '#e7f7e7' : '#ffe7e7'; ?>; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                <strong><?php _e('Email Notifications:', 'booking-system-pro'); ?></strong> 
                <?php echo ($email_settings['enabled'] ?? true) ? '✅ Enabled' : '❌ Disabled'; ?>
            </div>
            
            <div style="background: <?php echo ($email_settings['customer_confirmation'] ?? true) ? '#e7f7e7' : '#ffe7e7'; ?>; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                <strong><?php _e('Customer Emails:', 'booking-system-pro'); ?></strong> 
                <?php echo ($email_settings['customer_confirmation'] ?? true) ? '✅ Enabled' : '❌ Disabled'; ?>
            </div>
            
            <div style="background: <?php echo ($email_settings['admin_notifications'] ?? true) ? '#e7f7e7' : '#ffe7e7'; ?>; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <strong><?php _e('Admin Emails:', 'booking-system-pro'); ?></strong> 
                <?php echo ($email_settings['admin_notifications'] ?? true) ? '✅ Enabled' : '❌ Disabled'; ?>
            </div>
            
            <div style="text-align: center;">
                <a href="<?php echo admin_url('admin.php?page=bsp-settings&tab=email'); ?>" 
                   class="button button-secondary" target="_blank">
                    <?php _e('⚙️ Configure Email Settings', 'booking-system-pro'); ?>
                </a>
            </div>
            
            <hr style="margin: 15px 0;">
            
            <div style="text-align: center;">
                <button type="button" class="button button-primary" id="bsp-resend-emails" 
                        data-booking-id="<?php echo $post->ID; ?>">
                    <?php _e('📧 Resend Booking Emails', 'booking-system-pro'); ?>
                </button>
                <div id="bsp-resend-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#bsp-resend-emails').click(function() {
                var button = $(this);
                var result = $('#bsp-resend-result');
                var bookingId = button.data('booking-id');
                
                button.prop('disabled', true).text('<?php _e('Sending...', 'booking-system-pro'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bsp_resend_booking_emails',
                        booking_id: bookingId,
                        nonce: '<?php echo wp_create_nonce('bsp_resend_emails'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<p style="color: green; font-size: 12px;"><strong>✅ ' + response.data.message + '</strong></p>');
                        } else {
                            result.html('<p style="color: red; font-size: 12px;"><strong>❌ ' + response.data + '</strong></p>');
                        }
                    },
                    error: function() {
                        result.html('<p style="color: red; font-size: 12px;"><strong>❌ Error resending emails</strong></p>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('📧 Resend Booking Emails', 'booking-system-pro'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Booking actions meta box
     */
    public function booking_actions_meta_box($post) {
        $booking_id = get_post_meta($post->ID, '_bsp_booking_id', true);
        $customer_id = get_post_meta($post->ID, '_bsp_customer_id', true);
        $company_id = get_post_meta($post->ID, '_bsp_company_id', true);
        
        ?>
        <div class="bsp-booking-actions">
            <p class="bsp-action-buttons">
                <button type="button" class="button button-primary" onclick="bspSendConfirmation(<?php echo esc_attr($booking_id); ?>)">
                    <?php _e('Send Confirmation', 'booking-system-pro'); ?>
                </button>
            </p>
            <p class="bsp-action-buttons">
                <button type="button" class="button" onclick="bspSendReminder(<?php echo esc_attr($booking_id); ?>)">
                    <?php _e('Send Reminder', 'booking-system-pro'); ?>
                </button>
            </p>
            <p class="bsp-action-buttons">
                <button type="button" class="button" onclick="bspViewCustomer(<?php echo esc_attr($customer_id); ?>)">
                    <?php _e('View Customer History', 'booking-system-pro'); ?>
                </button>
            </p>
            <p class="bsp-action-buttons">
                <button type="button" class="button" onclick="bspViewCompany(<?php echo esc_attr($company_id); ?>)">
                    <?php _e('Manage Company', 'booking-system-pro'); ?>
                </button>
            </p>
            
            <hr>
            
            <h4><?php _e('Quick Actions', 'booking-system-pro'); ?></h4>
            <p class="bsp-action-buttons">
                <button type="button" class="button" onclick="bspConfirmBooking(<?php echo esc_attr($booking_id); ?>)">
                    <?php _e('Confirm Booking', 'booking-system-pro'); ?>
                </button>
            </p>
            <p class="bsp-action-buttons">
                <button type="button" class="button" onclick="bspCancelBooking(<?php echo esc_attr($booking_id); ?>)">
                    <?php _e('Cancel Booking', 'booking-system-pro'); ?>
                </button>
            </p>
            <p class="bsp-action-buttons">
                <button type="button" class="button" onclick="bspRescheduleBooking(<?php echo esc_attr($booking_id); ?>)">
                    <?php _e('Reschedule', 'booking-system-pro'); ?>
                </button>
            </p>
        </div>
        
        <script type="text/javascript">
        function bspSendConfirmation(bookingId) {
            if (confirm('<?php _e('Send confirmation email to customer?', 'booking-system-pro'); ?>')) {
                // AJAX call to send confirmation
                jQuery.post(ajaxurl, {
                    action: 'bsp_send_confirmation',
                    booking_id: bookingId,
                    nonce: '<?php echo wp_create_nonce('bsp_admin_action'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Confirmation email sent successfully!', 'booking-system-pro'); ?>');
                    } else {
                        alert('<?php _e('Error sending confirmation email.', 'booking-system-pro'); ?>');
                    }
                });
            }
        }
        
        function bspConfirmBooking(bookingId) {
            if (confirm('<?php _e('Confirm this booking?', 'booking-system-pro'); ?>')) {
                jQuery('#status').val('confirmed').trigger('change');
                alert('<?php _e('Booking status updated to confirmed.', 'booking-system-pro'); ?>');
            }
        }
        
        function bspCancelBooking(bookingId) {
            if (confirm('<?php _e('Cancel this booking?', 'booking-system-pro'); ?>')) {
                jQuery('#status').val('cancelled').trigger('change');
                alert('<?php _e('Booking status updated to cancelled.', 'booking-system-pro'); ?>');
            }
        }
        </script>
        <?php
    }
    
    /**
     * Save meta box data
     */
    public function save_meta_boxes($post_id) {
        if (!isset($_POST['bsp_booking_meta_box_nonce']) || !wp_verify_nonce($_POST['bsp_booking_meta_box_nonce'], 'bsp_booking_meta_box')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $booking_id = get_post_meta($post_id, '_bsp_booking_id', true);
        
        if ($booking_id) {
            global $wpdb;
            $table = BSP_Database_Unified::get_table('bookings');
            
            // Update booking data in database
            $update_data = [];
            
            if (isset($_POST['booking_date'])) {
                $update_data['booking_date'] = sanitize_text_field($_POST['booking_date']);
            }
            if (isset($_POST['booking_time'])) {
                $update_data['booking_time'] = sanitize_text_field($_POST['booking_time']);
            }
            if (isset($_POST['service_type'])) {
                $update_data['service_type'] = sanitize_text_field($_POST['service_type']);
            }
            if (isset($_POST['status'])) {
                $update_data['status'] = sanitize_text_field($_POST['status']);
            }
            if (isset($_POST['payment_status'])) {
                $update_data['payment_status'] = sanitize_text_field($_POST['payment_status']);
            }
            if (isset($_POST['special_requests'])) {
                $update_data['special_requests'] = sanitize_textarea_field($_POST['special_requests']);
            }
            if (isset($_POST['admin_notes'])) {
                $update_data['admin_notes'] = sanitize_textarea_field($_POST['admin_notes']);
            }
            
            if (!empty($update_data)) {
                $wpdb->update($table, $update_data, ['id' => $booking_id]);
            }
        }
        
        // Update post meta
        $meta_fields = ['booking_number', 'booking_date', 'booking_time', 'service_type'];
        foreach ($meta_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_bsp_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    /**
     * Customize booking columns
     */
    public function booking_columns($columns) {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = __('Booking', 'booking-system-pro');
        $new_columns['customer'] = __('Customer', 'booking-system-pro');
        $new_columns['company'] = __('Company', 'booking-system-pro');
        $new_columns['service'] = __('Service', 'booking-system-pro');
        $new_columns['schedule'] = __('Schedule', 'booking-system-pro');
        $new_columns['status'] = __('Status', 'booking-system-pro');
        $new_columns['payment'] = __('Payment', 'booking-system-pro');
        $new_columns['date'] = __('Created', 'booking-system-pro');
        
        return $new_columns;
    }
    
    /**
     * Booking column content
     */
    public function booking_column_content($column, $post_id) {
        $booking_id = get_post_meta($post_id, '_bsp_booking_id', true);
        
        if (!$booking_id) {
            echo '—';
            return;
        }
        
        global $wpdb;
        $bookings_table = BSP_Database_Unified::get_table('bookings');
        $customers_table = BSP_Database_Unified::get_table('customers');
        $companies_table = BSP_Database_Unified::get_table('companies');
        
        $booking_data = $wpdb->get_row($wpdb->prepare("
            SELECT b.*, 
                   c.first_name, c.last_name, c.email as customer_email,
                   comp.name as company_name, comp.phone as company_phone
            FROM $bookings_table b
            LEFT JOIN $customers_table c ON b.customer_id = c.id
            LEFT JOIN $companies_table comp ON b.company_id = comp.id
            WHERE b.id = %d
        ", $booking_id));
        
        if (!$booking_data) {
            echo '—';
            return;
        }
        
        switch ($column) {
            case 'customer':
                echo '<strong>' . esc_html($booking_data->first_name . ' ' . $booking_data->last_name) . '</strong><br>';
                echo '<a href="mailto:' . esc_attr($booking_data->customer_email) . '">' . esc_html($booking_data->customer_email) . '</a>';
                break;
                
            case 'company':
                echo '<strong>' . esc_html($booking_data->company_name) . '</strong><br>';
                echo '<a href="tel:' . esc_attr($booking_data->company_phone) . '">' . esc_html($booking_data->company_phone) . '</a>';
                break;
                
            case 'service':
                echo esc_html($booking_data->service_type);
                break;
                
            case 'schedule':
                echo '<strong>' . esc_html(date('M j, Y', strtotime($booking_data->booking_date))) . '</strong><br>';
                echo esc_html(date('g:i A', strtotime($booking_data->booking_time)));
                break;
                
            case 'status':
                $status_class = 'bsp-status-' . esc_attr($booking_data->status);
                echo '<span class="bsp-status ' . $status_class . '">' . esc_html(ucfirst($booking_data->status)) . '</span>';
                break;
                
            case 'payment':
                $payment_class = 'bsp-payment-' . esc_attr($booking_data->payment_status);
                echo '<span class="bsp-payment ' . $payment_class . '">' . esc_html(ucfirst($booking_data->payment_status)) . '</span>';
                break;
        }
    }
    
    /**
     * Add custom row actions
     */
    public function booking_row_actions($actions, $post) {
        if ($post->post_type === 'bsp_booking') {
            $booking_id = get_post_meta($post->ID, '_bsp_booking_id', true);
            
            if ($booking_id) {
                $actions['view_details'] = sprintf(
                    '<a href="%s">%s</a>',
                    esc_url(admin_url('admin.php?page=bsp-booking-details&booking_id=' . $booking_id)),
                    __('View Details', 'booking-system-pro')
                );
                
                $actions['send_confirmation'] = sprintf(
                    '<a href="#" onclick="bspSendConfirmation(%d); return false;">%s</a>',
                    $booking_id,
                    __('Send Confirmation', 'booking-system-pro')
                );
            }
        }
        
        return $actions;
    }
}
