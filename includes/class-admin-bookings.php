<?php
/**
 * Admin Bookings Management for Booking System Pro
 */

if (!defined('ABSPATH')) exit;

class BSP_Admin_Bookings {
    
    private static $instance = null;
    private $db;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = BSP_Database_Unified::get_instance();
    }
    
    /**
     * Render bookings page
     */
    public function render_bookings_page() {
        // Handle different actions
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'view':
            case 'edit':
                $this->render_edit_booking_page();
                return;
                
            case 'add':
                $this->render_add_booking_page();
                return;
                
            default:
                // Show bookings list (default behavior)
                break;
        }
        
        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] !== '-1') {
            $this->handle_bulk_actions();
        }
        
        // Get bookings from post type
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        $args = [
            'post_type' => 'bsp_booking',
            'post_status' => 'any',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        // Apply status filter
        if (!empty($_GET['status'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'bsp_booking_status',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($_GET['status'])
                ]
            ];
        }
        
        $bookings_query = new WP_Query($args);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Bookings', 'booking-system-pro'); ?></h1>
            <a href="<?php echo admin_url('post-new.php?post_type=bsp_booking'); ?>" class="page-title-action">
                <?php _e('Add New', 'booking-system-pro'); ?>
            </a>
            
            <!-- Filters -->
            <div class="bsp-filters">
                <form method="get">
                    <input type="hidden" name="page" value="bsp-bookings">
                    
                    <select name="status">
                        <option value=""><?php _e('All Statuses', 'booking-system-pro'); ?></option>
                        <?php foreach (BSP_Utilities::get_booking_statuses() as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($_GET['status'] ?? '', $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="text" name="search" value="<?php echo esc_attr($_GET['search'] ?? ''); ?>" placeholder="<?php _e('Search bookings...', 'booking-system-pro'); ?>">
                    
                    <input type="submit" class="button" value="<?php _e('Filter', 'booking-system-pro'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=bsp-bookings'); ?>" class="button"><?php _e('Reset', 'booking-system-pro'); ?></a>
                </form>
            </div>
            
            <!-- Bookings Table -->
            <?php if ($bookings_query->have_posts()): ?>
                <form method="post">
                    <?php wp_nonce_field('bsp_bulk_actions', 'bsp_nonce'); ?>
                    
                    <div class="tablenav top">
                        <div class="alignleft actions bulkactions">
                            <select name="action">
                                <option value="-1"><?php _e('Bulk Actions', 'booking-system-pro'); ?></option>
                                <option value="confirm"><?php _e('Confirm', 'booking-system-pro'); ?></option>
                                <option value="cancel"><?php _e('Cancel', 'booking-system-pro'); ?></option>
                                <option value="complete"><?php _e('Mark Complete', 'booking-system-pro'); ?></option>
                                <option value="delete"><?php _e('Delete', 'booking-system-pro'); ?></option>
                            </select>
                            <input type="submit" class="button action" value="<?php _e('Apply', 'booking-system-pro'); ?>">
                        </div>
                        
                        <?php if ($bookings_query->max_num_pages > 1): ?>
                            <div class="tablenav-pages">
                                <?php
                                echo paginate_links([
                                    'current' => $paged,
                                    'total' => $bookings_query->max_num_pages,
                                    'format' => '?paged=%#%',
                                    'add_args' => array_merge($_GET, ['page' => 'bsp-bookings'])
                                ]);
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="cb-select-all-1">
                                </td>
                                <th><?php _e('ID', 'booking-system-pro'); ?></th>
                                <th><?php _e('Customer', 'booking-system-pro'); ?></th>
                                <th><?php _e('Date & Time', 'booking-system-pro'); ?></th>
                                <th><?php _e('Status', 'booking-system-pro'); ?></th>
                                <th><?php _e('Created', 'booking-system-pro'); ?></th>
                                <th><?php _e('Actions', 'booking-system-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($bookings_query->have_posts()): $bookings_query->the_post(); ?>
                                <?php
                                $booking_id = get_the_ID();
                                $customer_name = get_post_meta($booking_id, '_customer_name', true);
                                $customer_email = get_post_meta($booking_id, '_customer_email', true);
                                $booking_date = get_post_meta($booking_id, '_booking_date', true);
                                $booking_time = get_post_meta($booking_id, '_booking_time', true);
                                
                                // Get status
                                $status_terms = wp_get_post_terms($booking_id, 'bsp_booking_status');
                                
                                // Check for WP_Error and set defaults
                                if (is_wp_error($status_terms)) {
                                    $status_terms = [];
                                }
                                
                                $status = !empty($status_terms) ? $status_terms[0]->slug : 'pending';
                                $status_label = !empty($status_terms) ? $status_terms[0]->name : 'Pending';
                                ?>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" name="booking_ids[]" value="<?php echo esc_attr($booking_id); ?>">
                                    </th>
                                    <td><strong>#<?php echo esc_html($booking_id); ?></strong></td>
                                    <td>
                                        <strong><?php echo esc_html($customer_name ?: 'Unknown'); ?></strong><br>
                                        <small><?php echo esc_html($customer_email); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($booking_date): ?>
                                            <?php echo esc_html(BSP_Utilities::format_date($booking_date)); ?><br>
                                            <small><?php echo esc_html($booking_time ? BSP_Utilities::format_time($booking_time) : 'No time set'); ?></small>
                                        <?php else: ?>
                                            <em><?php _e('No date set', 'booking-system-pro'); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="bsp-status bsp-status-<?php echo esc_attr($status); ?>">
                                            <?php echo esc_html($status_label); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(get_the_date('Y-m-d H:i')); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('post.php?post=' . $booking_id . '&action=edit'); ?>" class="button button-small">
                                            <?php _e('Edit', 'booking-system-pro'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    
                    <div class="tablenav bottom">
                        <?php if ($bookings_query->max_num_pages > 1): ?>
                            <div class="tablenav-pages">
                                <?php
                                echo paginate_links([
                                    'current' => $paged,
                                    'total' => $bookings_query->max_num_pages,
                                    'format' => '?paged=%#%',
                                    'add_args' => array_merge($_GET, ['page' => 'bsp-bookings'])
                                ]);
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php _e('No bookings found.', 'booking-system-pro'); ?> 
                       <a href="<?php echo admin_url('post-new.php?post_type=bsp_booking'); ?>">
                           <?php _e('Create your first booking', 'booking-system-pro'); ?>
                       </a>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php wp_reset_postdata(); ?>
        </div>
        
        <style>
        .bsp-filters {
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .bsp-filters form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .bsp-status {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .bsp-status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .bsp-status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .bsp-status-completed {
            background: #cce7ff;
            color: #004085;
        }
        
        .bsp-status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#cb-select-all-1').on('change', function() {
                $('input[name="booking_ids[]"]').prop('checked', this.checked);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render add booking page
     */
    private function render_add_booking_page() {
        if (isset($_POST['submit_booking'])) {
            $this->handle_save_booking();
        }
        
        $services = $this->db->get_services();
        $companies = BSP_Database_Unified::get_companies();
        ?>
        <div class="wrap">
            <h1><?php _e('Add New Booking', 'booking-system-pro'); ?></h1>
            
            <form method="post" class="bsp-booking-form">
                <?php wp_nonce_field('bsp_save_booking', 'bsp_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Customer Name', 'booking-system-pro'); ?> <span class="required">*</span></th>
                        <td><input type="text" name="customer_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Customer Email', 'booking-system-pro'); ?> <span class="required">*</span></th>
                        <td><input type="email" name="customer_email" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Customer Phone', 'booking-system-pro'); ?></th>
                        <td><input type="tel" name="customer_phone" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Service', 'booking-system-pro'); ?> <span class="required">*</span></th>
                        <td>
                            <select name="service_id" required>
                                <option value=""><?php _e('Select Service', 'booking-system-pro'); ?></option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?php echo esc_attr($service['id']); ?>">
                                        <?php echo esc_html($service['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Company', 'booking-system-pro'); ?></th>
                        <td>
                            <select name="company_id">
                                <option value=""><?php _e('Select Company', 'booking-system-pro'); ?></option>
                                <?php foreach ($companies as $company): ?>
                                    <?php 
                                    // Handle both array and object formats
                                    $company_id = is_array($company) ? $company['id'] : $company->id;
                                    $company_name = is_array($company) ? $company['name'] : $company->name;
                                    ?>
                                    <option value="<?php echo esc_attr($company_id); ?>">
                                        <?php echo esc_html($company_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Appointment Date', 'booking-system-pro'); ?> <span class="required">*</span></th>
                        <td><input type="date" name="appointment_date" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Appointment Time', 'booking-system-pro'); ?> <span class="required">*</span></th>
                        <td><input type="time" name="appointment_time" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Status', 'booking-system-pro'); ?></th>
                        <td>
                            <select name="status">
                                <?php foreach (BSP_Utilities::get_booking_statuses() as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($value, 'pending'); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Notes', 'booking-system-pro'); ?></th>
                        <td><textarea name="notes" class="large-text" rows="5"></textarea></td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit_booking" class="button-primary" value="<?php _e('Save Booking', 'booking-system-pro'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=bsp-bookings'); ?>" class="button"><?php _e('Cancel', 'booking-system-pro'); ?></a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render edit booking page
     */
    private function render_edit_booking_page() {
        $booking_id = intval($_GET['id'] ?? 0);
        $action = $_GET['action'] ?? 'edit';
        $is_view_mode = ($action === 'view');
        
        // Use the centralized data manager to get all booking data
        $booking = BSP_Data_Manager::get_formatted_booking_data($booking_id);
        
        if (!$booking) {
            wp_die(__('Booking not found.', 'booking-system-pro'));
        }
        
        if (isset($_POST['submit_booking']) && !$is_view_mode) {
            $this->handle_save_booking($booking_id);
        }
        
        $services = $this->db->get_services();
        $companies = BSP_Database_Unified::get_companies();
        ?>
        <div class="wrap">
            <!-- BSP Enhanced Booking View v2.0 -->
            <h1>
                <?php 
                if ($is_view_mode) {
                    echo __('View Booking', 'booking-system-pro') . ' #' . esc_html($booking['id']);
                } else {
                    echo __('Edit Booking', 'booking-system-pro') . ' #' . esc_html($booking['id']);
                }
                ?>
            </h1>
            
            <style>
            .bsp-booking-view .form-table th {
                background: #f8f9fa;
                font-weight: 600;
                padding: 15px 10px;
                width: 200px;
                border-bottom: 1px solid #e1e5e9;
            }
            .bsp-booking-view .form-table td {
                padding: 15px 10px;
                border-bottom: 1px solid #e1e5e9;
                background: #fff;
            }
            .bsp-booking-view .form-table input[readonly],
            .bsp-booking-view .form-table select[disabled],
            .bsp-booking-view .form-table textarea[readonly] {
                background: #f8f9fa;
                border: 1px solid #e1e5e9;
                color: #495057;
                font-weight: 500;
            }
            .bsp-section-header {
                background: #007cba;
                color: white;
                padding: 10px 15px;
                margin: 20px 0 0 0;
                border-radius: 5px 5px 0 0;
                font-size: 16px;
                font-weight: 600;
            }
            .bsp-section-table {
                margin-top: 0;
                border: 1px solid #e1e5e9;
                border-radius: 0 0 5px 5px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .bsp-highlight-value {
                background: #e7f3ff;
                padding: 8px 12px;
                border-radius: 4px;
                font-weight: 600;
                color: #0073aa;
            }
            .bsp-json-data {
                background: #f8f9fa;
                border: 1px solid #e1e5e9;
                border-radius: 4px;
                padding: 10px;
                font-family: monospace;
                font-size: 12px;
                max-height: 120px;
                overflow-y: auto;
            }
            </style>
            
            <div class="bsp-booking-view">
            <form method="post" class="bsp-booking-form">
                <?php if (!$is_view_mode): ?>
                    <?php wp_nonce_field('bsp_save_booking', 'bsp_nonce'); ?>
                <?php endif; ?>
                
                <div class="bsp-section-header"><?php _e('Customer Information', 'booking-system-pro'); ?></div>
                <table class="form-table bsp-section-table">
                    <tr>
                        <th scope="row"><?php _e('Customer Name', 'booking-system-pro'); ?> <?php if (!$is_view_mode): ?><span class="required">*</span><?php endif; ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['customer_name']); ?></div></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Customer Email', 'booking-system-pro'); ?> <?php if (!$is_view_mode): ?><span class="required">*</span><?php endif; ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['customer_email']); ?></div></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Customer Phone', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['customer_phone']); ?></div></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Customer Address', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['customer_address']); ?></div></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('ZIP Code', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['zip_code'] ?? ''); ?></div></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('City', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['city'] ?? ''); ?></div></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('State', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['state'] ?? ''); ?></div></td>
                    </tr>
                </table>

                <div class="bsp-section-header"><?php _e('Marketing Source', 'booking-system-pro'); ?></div>
                <table class="form-table bsp-section-table">
                    <?php 
                    $source_data = maybe_unserialize(get_post_meta($booking_id, '_marketing_source', true));
                    if (!empty($source_data) && is_array($source_data)): 
                        foreach ($source_data as $key => $value): ?>
                            <tr>
                                <th scope="row"><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th>
                                <td><div class="bsp-highlight-value"><?php echo esc_html($value); ?></div></td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <th scope="row"><?php _e('Source', 'booking-system-pro'); ?></th>
                            <td><div class="bsp-highlight-value"><?php _e('Not available', 'booking-system-pro'); ?></div></td>
                        </tr>
                    <?php endif; ?>
                </table>
                
                <div class="bsp-section-header"><?php _e('Appointment Details', 'booking-system-pro'); ?></div>
                <table class="form-table bsp-section-table">
                    <tr>
                        <th scope="row"><?php _e('Service', 'booking-system-pro'); ?> <?php if (!$is_view_mode): ?><span class="required">*</span><?php endif; ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['service_name'] ?: 'Not selected'); ?></div></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Company', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['company_name'] ?: 'Not selected'); ?></div></td>
                    </tr>
                    <?php if (!empty($booking['specifications'])): ?>
                    <tr>
                        <th scope="row"><?php _e('Specifications', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo nl2br(esc_html($booking['specifications'])); ?></div></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row"><?php _e('Appointment Date', 'booking-system-pro'); ?> <?php if (!$is_view_mode): ?><span class="required">*</span><?php endif; ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['formatted_date']); ?></div></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Appointment Time', 'booking-system-pro'); ?> <?php if (!$is_view_mode): ?><span class="required">*</span><?php endif; ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['formatted_time']); ?></div></td>
                    </tr>
                    <?php if ($booking['has_multiple_appointments']): ?>
                    <tr>
                        <th scope="row"><?php _e('Multiple Appointments', 'booking-system-pro'); ?></th>
                        <td>
                            <div class="bsp-json-data">
                                <?php 
                                if ($booking['parsed_appointments'] && is_array($booking['parsed_appointments'])) {
                                    echo '<strong>Scheduled Appointments:</strong><br>';
                                    foreach ($booking['parsed_appointments'] as $i => $apt) {
                                        echo ($i + 1) . '. ' . esc_html($apt['company']) . ' - ' . 
                                             date('F j, Y', strtotime($apt['date'])) . ' at ' . 
                                             date('g:i A', strtotime($apt['time'])) . '<br>';
                                    }
                                } else {
                                    echo esc_html($booking['appointments']);
                                }
                                ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <?php 
                // Display service-specific fields if they exist
                $service_fields = $this->get_service_specific_fields($booking);
                if (!empty($service_fields)): 
                ?>
                <div class="bsp-section-header"><?php _e('Service-Specific Details', 'booking-system-pro'); ?></div>
                <table class="form-table bsp-section-table">
                    <?php foreach ($service_fields as $field): ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($field['label']); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($field['value']); ?></div></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
                
                <div class="bsp-section-header"><?php _e('Booking Information', 'booking-system-pro'); ?></div>
                <table class="form-table bsp-section-table">
                    <tr>
                        <th scope="row"><?php _e('Booking Created', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['formatted_created']); ?></div></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Booking ID', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value">#<?php echo esc_html($booking['id']); ?></div></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Status', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html(ucfirst($booking['status'])); ?></div></td>
                    </tr>
                    <?php if (!empty($booking['notes'])): ?>
                    <tr>
                        <th scope="row"><?php _e('Notes', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-json-data"><?php echo nl2br(esc_html($booking['notes'])); ?></div></td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <?php 
                // Display marketing/tracking information if it exists
                $has_marketing_data = !empty($booking['utm_source']) || !empty($booking['utm_medium']) || 
                                     !empty($booking['utm_campaign']) || !empty($booking['referrer']);
                if ($has_marketing_data): 
                ?>
                <div class="bsp-section-header"><?php _e('Marketing & Tracking Information', 'booking-system-pro'); ?></div>
                <table class="form-table bsp-section-table">
                    <?php if (!empty($booking['utm_source'])): ?>
                    <tr>
                        <th scope="row"><?php _e('UTM Source', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['utm_source']); ?></div></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking['utm_medium'])): ?>
                    <tr>
                        <th scope="row"><?php _e('UTM Medium', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['utm_medium']); ?></div></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking['utm_campaign'])): ?>
                    <tr>
                        <th scope="row"><?php _e('UTM Campaign', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['utm_campaign']); ?></div></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking['utm_term'])): ?>
                    <tr>
                        <th scope="row"><?php _e('UTM Term', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['utm_term']); ?></div></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking['utm_content'])): ?>
                    <tr>
                        <th scope="row"><?php _e('UTM Content', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['utm_content']); ?></div></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking['referrer'])): ?>
                    <tr>
                        <th scope="row"><?php _e('Referrer', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['referrer']); ?></div></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking['landing_page'])): ?>
                    <tr>
                        <th scope="row"><?php _e('Landing Page', 'booking-system-pro'); ?></th>
                        <td><div class="bsp-highlight-value"><?php echo esc_html($booking['landing_page']); ?></div></td>
                    </tr>
                    <?php endif; ?>
                </table>
                <?php endif; ?>
                
                <p class="submit">
                    <?php if (!$is_view_mode): ?>
                        <input type="submit" name="submit_booking" class="button-primary" value="<?php _e('Update Booking', 'booking-system-pro'); ?>">
                    <?php else: ?>
                        <a href="<?php echo admin_url('admin.php?page=bsp-bookings&action=edit&id=' . $booking['id']); ?>" class="button-primary"><?php _e('Edit Booking', 'booking-system-pro'); ?></a>
                    <?php endif; ?>
                    <a href="<?php echo admin_url('edit.php?post_type=bsp_booking'); ?>" class="button"><?php _e('Back to All Bookings', 'booking-system-pro'); ?></a>
                </p>
            </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle saving booking
     */
    private function handle_save_booking($booking_id = null) {
        if (!wp_verify_nonce($_POST['bsp_nonce'], 'bsp_save_booking')) {
            wp_die(__('Security check failed.', 'booking-system-pro'));
        }
        
        $booking_data = BSP_Utilities::sanitize_booking_data($_POST);
        
        if ($booking_id) {
            $result = $this->db->update_booking($booking_id, $booking_data);
            $message = __('Booking updated successfully.', 'booking-system-pro');
            
            if ($result) {
                do_action('bsp_booking_updated', $booking_id, $booking_data);
            }
        } else {
            $result = $this->db->insert_booking($booking_data);
            $message = __('Booking created successfully.', 'booking-system-pro');
            
            if ($result) {
                do_action('bsp_booking_created', $result, $booking_data);
                $booking_id = $result;
            }
        }
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=bsp-bookings&message=' . urlencode($message)));
            exit;
        } else {
            wp_die(__('Failed to save booking.', 'booking-system-pro'));
        }
    }
    
    /**
     * Get list filters
     */
    private function get_list_filters() {
        $filters = [];
        
        if (!empty($_GET['status'])) {
            $filters['status'] = sanitize_text_field($_GET['status']);
        }
        
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_GET['date_from']);
        }
        
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_GET['date_to']);
        }
        
        if (!empty($_GET['search'])) {
            $filters['search'] = sanitize_text_field($_GET['search']);
        }
        
        return $filters;
    }
    
    /**
     * Get service-specific fields for display
     */
    private function get_service_specific_fields($booking) {
        $fields = [];
        $service = strtolower($booking['service_name'] ?? '');
        
        // Service-specific ZIP codes - check all ZIP fields
        $zip_fields = [
            'roof_zip' => 'Roof Service ZIP',
            'windows_zip' => 'Windows Service ZIP', 
            'bathroom_zip' => 'Bathroom Service ZIP',
            'siding_zip' => 'Siding Service ZIP',
            'kitchen_zip' => 'Kitchen Service ZIP',
            'decks_zip' => 'Decks Service ZIP'
        ];
        
        foreach ($zip_fields as $field => $label) {
            if (!empty($booking[$field])) {
                $fields[] = [
                    'name' => $field,
                    'label' => $label,
                    'value' => $booking[$field]
                ];
            }
        }
        
        // Service-specific details based on service type
        switch ($service) {
            case 'roof':
                if (!empty($booking['roof_action'])) {
                    $fields[] = ['name' => 'roof_action', 'label' => 'Roof Action', 'value' => $booking['roof_action']];
                }
                if (!empty($booking['roof_material'])) {
                    $fields[] = ['name' => 'roof_material', 'label' => 'Roof Material', 'value' => $booking['roof_material']];
                }
                break;
                
            case 'windows':
                if (!empty($booking['windows_action'])) {
                    $fields[] = ['name' => 'windows_action', 'label' => 'Windows Action', 'value' => $booking['windows_action']];
                }
                if (!empty($booking['windows_replace_qty'])) {
                    $fields[] = ['name' => 'windows_replace_qty', 'label' => 'Windows Quantity', 'value' => $booking['windows_replace_qty']];
                }
                if (!empty($booking['windows_repair_needed'])) {
                    $fields[] = ['name' => 'windows_repair_needed', 'label' => 'Repair Alternative', 'value' => $booking['windows_repair_needed']];
                }
                break;
                
            case 'bathroom':
                if (!empty($booking['bathroom_option'])) {
                    $fields[] = ['name' => 'bathroom_option', 'label' => 'Bathroom Service Type', 'value' => $booking['bathroom_option']];
                }
                break;
                
            case 'siding':
                if (!empty($booking['siding_option'])) {
                    $fields[] = ['name' => 'siding_option', 'label' => 'Siding Work Type', 'value' => $booking['siding_option']];
                }
                if (!empty($booking['siding_material'])) {
                    $fields[] = ['name' => 'siding_material', 'label' => 'Siding Material', 'value' => $booking['siding_material']];
                }
                break;
                
            case 'kitchen':
                if (!empty($booking['kitchen_action'])) {
                    $fields[] = ['name' => 'kitchen_action', 'label' => 'Kitchen Action', 'value' => $booking['kitchen_action']];
                }
                if (!empty($booking['kitchen_component'])) {
                    $fields[] = ['name' => 'kitchen_component', 'label' => 'Kitchen Component', 'value' => $booking['kitchen_component']];
                }
                break;
                
            case 'decks':
                if (!empty($booking['decks_action'])) {
                    $fields[] = ['name' => 'decks_action', 'label' => 'Decks Action', 'value' => $booking['decks_action']];
                }
                if (!empty($booking['decks_material'])) {
                    $fields[] = ['name' => 'decks_material', 'label' => 'Decks Material', 'value' => $booking['decks_material']];
                }
                break;
        }
        
        return $fields;
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk-bookings')) {
            return;
        }
        
        if (!isset($_POST['bulk_ids']) || !is_array($_POST['bulk_ids'])) {
            return;
        }
        
        $action = $_POST['action'];
        $ids = array_map('intval', $_POST['bulk_ids']);
        
        switch ($action) {
            case 'delete':
                foreach ($ids as $id) {
                    wp_delete_post($id, true);
                }
                echo '<div class="notice notice-success"><p>' . sprintf(__('%d bookings deleted.', 'booking-system-pro'), count($ids)) . '</p></div>';
                break;
        }
    }
    
    /**
     * Render pagination
     */
    private function render_pagination($current_page, $total_pages) {
        if ($total_pages <= 1) {
            return;
        }
        
        echo '<div class="tablenav-pages">';
        echo paginate_links([
            'current' => $current_page,
            'total' => $total_pages,
            'format' => '?paged=%#%',
            'add_args' => array_merge($_GET, ['page' => 'bsp-bookings'])
        ]);
        echo '</div>';
    }
}
