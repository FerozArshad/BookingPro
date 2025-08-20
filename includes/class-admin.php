<?php
/**
 * Unified Admin Interface for Booking System Pro
 */

if (!defined('ABSPATH')) exit;

// Define fallback constants if not already defined
if (!defined('BSP_VERSION')) {
    define('BSP_VERSION', '2.0.0');
}
if (!defined('BSP_PLUGIN_URL')) {
    define('BSP_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
}
if (!defined('BSP_PLUGIN_DIR')) {
    define('BSP_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
}

class BSP_Admin {
    
    private static $instance = null;
    private $capabilities = 'manage_options';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("BSP_Admin initialized", 'ADMIN');
        }
        
        // Include admin settings class
        if (!class_exists('BSP_Admin_Settings')) {
            require_once BSP_PLUGIN_DIR . 'includes/class-admin-settings.php';
        }
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_filter('post_row_actions', [$this, 'add_booking_row_actions'], 10, 2);
        add_filter('manage_bsp_booking_posts_columns', [$this, 'booking_columns']);
        add_action('manage_bsp_booking_posts_custom_column', [$this, 'booking_column_content'], 10, 2);
        
        // CSV Export functionality
        add_action('admin_init', [$this, 'handle_csv_export']);
        add_action('restrict_manage_posts', [$this, 'add_export_button']);
        
        // AJAX handlers
        add_action('wp_ajax_bsp_update_company', [$this, 'ajax_update_company']);
        add_action('wp_ajax_bsp_bulk_action', [$this, 'ajax_bulk_action']);
        add_action('wp_ajax_bsp_load_calendar', [$this, 'ajax_load_calendar']);
        add_action('wp_ajax_bsp_load_bookings_for_date', [$this, 'ajax_load_bookings_for_date']);
        add_action('wp_ajax_bsp_auto_save_setting', [$this, 'ajax_auto_save_setting']);
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("Admin hooks and AJAX handlers registered", 'ADMIN');
        }
    }
    
    public function admin_init() {
        // Register admin settings
        $this->register_settings();
        
        // Handle bulk actions
        $this->handle_bulk_actions();
    }
    
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Booking System Pro', 'booking-system-pro'),
            __('Booking System', 'booking-system-pro'),
            $this->capabilities,
            'booking-system-pro',
            [$this, 'bookings_page'],
            'dashicons-calendar-alt',
            30
        );
        
        // All Bookings (main page)
        add_submenu_page(
            'booking-system-pro',
            __('All Bookings', 'booking-system-pro'),
            __('All Bookings', 'booking-system-pro'),
            $this->capabilities,
            'booking-system-pro',
            [$this, 'bookings_page']
        );
        
        // Company Availability
        add_submenu_page(
            'booking-system-pro',
            __('Company Availability', 'booking-system-pro'),
            __('Availability', 'booking-system-pro'),
            $this->capabilities,
            'bsp-availability',
            [$this, 'availability_page']
        );
        
        // Settings
        add_submenu_page(
            'booking-system-pro',
            __('Settings', 'booking-system-pro'),
            __('Settings', 'booking-system-pro'),
            $this->capabilities,
            'bsp-settings',
            [$this, 'settings_page']
        );
        
        // Booking Details (hidden submenu for individual booking management)
        add_submenu_page(
            null, // No parent menu - this makes it hidden from sidebar
            __('Booking Details', 'booking-system-pro'),
            __('Booking Details', 'booking-system-pro'),
            $this->capabilities,
            'bsp-bookings',
            [$this, 'bookings_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages - more specific check
        if (!$this->is_bsp_admin_page($hook)) {
            return;
        }
        
        // Conditional loading based on specific pages
        $page_specific_assets = $this->get_page_specific_assets($hook);
        
        // Core WordPress assets (only if needed)
        if ($page_specific_assets['needs_color_picker']) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
        }
        
        if ($page_specific_assets['needs_datepicker']) {
            wp_enqueue_script('jquery-ui-datepicker');
        }
        
        if ($page_specific_assets['needs_sortable']) {
            wp_enqueue_script('jquery-ui-sortable');
        }
        
        // Our admin CSS (always needed)
        wp_enqueue_style(
            'bsp-admin-style',
            BSP_PLUGIN_URL . 'assets/css/admin.css',
            ['wp-color-picker'],
            BSP_VERSION
        );
        
        wp_enqueue_script(
            'bsp-admin-script',
            BSP_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-color-picker', 'jquery-ui-datepicker', 'jquery-ui-sortable'],
            BSP_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('bsp-admin-script', 'BSP_Admin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bsp_admin_nonce'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this item?', 'booking-system-pro'),
                'bulk_confirm' => __('Are you sure you want to perform this bulk action?', 'booking-system-pro'),
                'select_action' => __('Please select an action.', 'booking-system-pro'),
                'select_items' => __('Please select at least one item.', 'booking-system-pro'),
                'company_name_required' => __('Company name is required.', 'booking-system-pro'),
                'company_email_required' => __('Company email is required.', 'booking-system-pro'),
                'invalid_email' => __('Please enter a valid email address.', 'booking-system-pro'),
                'form_validation_error' => __('Please correct the highlighted fields.', 'booking-system-pro'),
                'order_updated' => __('Order updated successfully.', 'booking-system-pro'),
                'loading' => __('Loading...', 'booking-system-pro'),
                'error' => __('An error occurred. Please try again.', 'booking-system-pro'),
                'success' => __('Action completed successfully.', 'booking-system-pro')
            ]
        ]);
    }
    
    /**
     * Check if current page is a BSP admin page
     */
    private function is_bsp_admin_page($hook) {
        // Check for our specific admin pages
        $bsp_pages = [
            'toplevel_page_booking-system-pro',
            'booking-system_page_bsp-availability',
            'booking-system_page_bsp-settings',
            'booking-system_page_bsp-bookings'
        ];
        
        if (in_array($hook, $bsp_pages)) {
            return true;
        }
        
        // Check for booking post type pages
        global $post_type;
        if ($post_type === 'bsp_booking') {
            return true;
        }
        
        // Check hook contains our identifiers
        return (strpos($hook, 'booking-system') !== false || strpos($hook, 'bsp-') !== false);
    }
    
    /**
     * Get page-specific asset requirements
     */
    private function get_page_specific_assets($hook) {
        $assets = [
            'needs_color_picker' => false,
            'needs_datepicker' => false,
            'needs_sortable' => false
        ];
        
        // Settings page needs color picker
        if (strpos($hook, 'bsp-settings') !== false) {
            $assets['needs_color_picker'] = true;
        }
        
        // Booking pages need datepicker
        if (strpos($hook, 'booking-system') !== false || strpos($hook, 'bsp-bookings') !== false) {
            $assets['needs_datepicker'] = true;
        }
        
        // Availability page might need sortable
        if (strpos($hook, 'bsp-availability') !== false) {
            $assets['needs_sortable'] = true;
        }
        
        return $assets;
    }
    
    public function admin_notices() {
        // Check for success/error messages
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $class = 'notice-success';
            
            switch ($message) {
                case 'booking_updated':
                    $text = __('Booking updated successfully.', 'booking-system-pro');
                    break;
                case 'booking_deleted':
                    $text = __('Booking deleted successfully.', 'booking-system-pro');
                    break;
                case 'company_updated':
                    $text = __('Company updated successfully.', 'booking-system-pro');
                    break;
                case 'settings_saved':
                    $text = __('Settings saved successfully.', 'booking-system-pro');
                    break;
                default:
                    $text = __('Action completed successfully.', 'booking-system-pro');
            }
            
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
        }
        
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
        }
    }
    
    // Page handlers
    public function bookings_page() {
        $bookings = BSP_Admin_Bookings::get_instance();
        $bookings->render_bookings_page();
    }
    
    public function availability_page() {
        // Simple company availability management
        $this->render_availability_page();
    }
    
    public function settings_page() {
        $settings = BSP_Admin_Settings::get_instance();
        $settings->render_settings_page();
    }
    
    private function render_availability_page() {
        $companies = BSP_Database_Unified::get_companies();
        
        // If no companies from database, create some default ones
        if (empty($companies)) {
            $companies = [
                ['id' => 1, 'name' => 'Top Remodeling Pro', 'is_active' => 1, 'start_time' => '9:00', 'end_time' => '17:00'],
                ['id' => 2, 'name' => 'Home Improvement Experts', 'is_active' => 1, 'start_time' => '9:00', 'end_time' => '17:00'],
                ['id' => 3, 'name' => 'Pro Remodeling Solutions', 'is_active' => 1, 'start_time' => '10:00', 'end_time' => '19:00']
            ];
        } else {
            // Convert stdClass objects to arrays for consistent handling
            $companies = array_map(function($company) {
                $company_array = (array) $company;
                // Map database columns to admin interface format
                $company_array['is_active'] = ($company_array['status'] ?? 'active') === 'active' ? 1 : 0;
                $company_array['start_time'] = isset($company_array['available_hours_start']) ? 
                    substr($company_array['available_hours_start'], 0, 5) : '9:00';
                $company_array['end_time'] = isset($company_array['available_hours_end']) ? 
                    substr($company_array['available_hours_end'], 0, 5) : '17:00';
                return $company_array;
            }, $companies);
        }
        
        if (isset($_POST['submit_availability'])) {
            $this->handle_availability_save();
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Company Availability Management', 'booking-system-pro'); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field('bsp_availability', 'bsp_nonce'); ?>
                
                <table class="form-table">
                    <?php foreach ($companies as $company): ?>
                    <?php 
                    $company_id = isset($company['id']) ? $company['id'] : 0;
                    $company_name = isset($company['name']) ? $company['name'] : 'Unknown Company';
                    $is_active = isset($company['is_active']) ? $company['is_active'] : 1;
                    $start_time = isset($company['start_time']) ? $company['start_time'] : '9:00';
                    $end_time = isset($company['end_time']) ? $company['end_time'] : '17:00';
                    ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($company_name); ?></th>
                        <td>
                            <p>
                                <label>
                                    <input type="checkbox" name="company_<?php echo esc_attr($company_id); ?>_active" value="1" 
                                           <?php checked(!empty($is_active)); ?>>
                                    <?php _e('Available for bookings', 'booking-system-pro'); ?>
                                </label>
                            </p>
                            <p>
                                <label><?php _e('Business Hours:', 'booking-system-pro'); ?></label><br>
                                <select name="company_<?php echo esc_attr($company_id); ?>_start_time">
                                    <?php for ($i = 8; $i <= 18; $i++): ?>
                                        <option value="<?php echo $i; ?>:00" <?php selected($start_time, $i . ':00'); ?>>
                                            <?php echo date('g:i A', strtotime($i . ':00')); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                -
                                <select name="company_<?php echo esc_attr($company_id); ?>_end_time">
                                    <?php for ($i = 10; $i <= 20; $i++): ?>
                                        <option value="<?php echo $i; ?>:00" <?php selected($end_time, $i . ':00'); ?>>
                                            <?php echo date('g:i A', strtotime($i . ':00')); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </p>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                
                <?php submit_button(__('Save Availability Settings', 'booking-system-pro'), 'primary', 'submit_availability'); ?>
            </form>
        </div>
        <?php
    }
    
    private function handle_availability_save() {
        if (!wp_verify_nonce($_POST['bsp_nonce'], 'bsp_availability')) {
            return;
        }
        
        $companies = BSP_Database_Unified::get_companies();
        
        if (empty($companies)) {
            echo '<div class="notice notice-error"><p>' . __('No companies found to update.', 'booking-system-pro') . '</p></div>';
            return;
        }
        
        foreach ($companies as $company) {
            $company_id = isset($company->id) ? $company->id : (isset($company['id']) ? $company['id'] : 0);
            if (!$company_id) continue;
            
            $is_active = isset($_POST["company_{$company_id}_active"]) ? 1 : 0;
            $start_time = sanitize_text_field($_POST["company_{$company_id}_start_time"] ?? '9:00');
            $end_time = sanitize_text_field($_POST["company_{$company_id}_end_time"] ?? '17:00');
            
            BSP_Database_Unified::get_instance()->update_company_availability($company_id, [
                'is_active' => $is_active,
                'start_time' => $start_time,
                'end_time' => $end_time
            ]);
        }
        
        echo '<div class="notice notice-success"><p>' . __('Availability settings saved.', 'booking-system-pro') . '</p></div>';
    }
    
    public function register_settings() {
        // Register plugin settings
        register_setting('bsp_settings', 'bsp_general_settings');
        register_setting('bsp_settings', 'bsp_email_settings');
        register_setting('bsp_settings', 'bsp_appearance_settings');
    }
    
    public function handle_bulk_actions() {
        if (!isset($_POST['action']) && !isset($_POST['action2'])) {
            return;
        }
        
        $action = $_POST['action'] !== '-1' ? $_POST['action'] : $_POST['action2'];
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk-bookings')) {
            return;
        }
        
        if (!isset($_POST['bulk_ids']) || !is_array($_POST['bulk_ids'])) {
            return;
        }
        
        $ids = array_map('intval', $_POST['bulk_ids']);
        
        switch ($action) {
            case 'delete':
                foreach ($ids as $id) {
                    wp_delete_post($id, true);
                }
                break;
                
            case 'approve':
                foreach ($ids as $id) {
                    wp_set_object_terms($id, 'confirmed', 'bsp_booking_status');
                }
                break;
                
            case 'pending':
                foreach ($ids as $id) {
                    wp_set_object_terms($id, 'pending', 'bsp_booking_status');
                }
                break;
        }
    }
    
    // Custom post type columns for bookings
    public function booking_columns($columns) {
        $new_columns = [
            'cb' => $columns['cb'],
            'title' => __('Booking ID', 'booking-system-pro'),
            'customer' => __('Customer', 'booking-system-pro'),
            'company' => __('Company', 'booking-system-pro'),
            'service' => __('Service', 'booking-system-pro'),
            'booking_date' => __('Booking Date', 'booking-system-pro'),
            'status' => __('Status', 'booking-system-pro'),
            'total' => __('Total', 'booking-system-pro'),
            'date' => __('Created', 'booking-system-pro')
        ];
        
        return $new_columns;
    }
    
    public function booking_column_content($column, $post_id) {
        switch ($column) {
            case 'customer':
                $customer_name = get_post_meta($post_id, '_customer_name', true);
                $customer_email = get_post_meta($post_id, '_customer_email', true);
                echo esc_html($customer_name);
                if ($customer_email) {
                    echo '<br><small>' . esc_html($customer_email) . '</small>';
                }
                break;
                
            case 'company':
                $company_id = get_post_meta($post_id, '_company_id', true);
                if ($company_id) {
                    $db = BSP_Database_Unified::get_instance();
                    $company = $db->get_company($company_id);
                    echo $company ? esc_html($company->name) : __('Unknown', 'booking-system-pro');
                }
                break;
                
            case 'service':
                $terms = wp_get_post_terms($post_id, 'bsp_service_type');
                if (!is_wp_error($terms) && !empty($terms)) {
                    echo esc_html($terms[0]->name);
                } else {
                    // Fallback to post meta if taxonomy is not available
                    $service_type = get_post_meta($post_id, '_service_type', true);
                    echo $service_type ? esc_html($service_type) : __('Unknown', 'booking-system-pro');
                }
                break;
                
            case 'booking_date':
                $booking_date = get_post_meta($post_id, '_booking_date', true);
                $booking_time = get_post_meta($post_id, '_booking_time', true);
                if ($booking_date) {
                    echo esc_html(date('M j, Y', strtotime($booking_date)));
                    if ($booking_time) {
                        echo '<br><small>' . esc_html($booking_time) . '</small>';
                    }
                }
                break;
                
            case 'status':
                $terms = wp_get_post_terms($post_id, 'bsp_booking_status');
                if (!is_wp_error($terms) && !empty($terms)) {
                    $status = $terms[0]->slug;
                    $class = 'bsp-status-' . $status;
                    echo '<span class="' . esc_attr($class) . '">' . esc_html($terms[0]->name) . '</span>';
                } else {
                    // Fallback to post meta if taxonomy is not available
                    $status = get_post_meta($post_id, '_status', true);
                    if ($status) {
                        $class = 'bsp-status-' . $status;
                        echo '<span class="' . esc_attr($class) . '">' . esc_html(ucfirst($status)) . '</span>';
                    } else {
                        echo '<span class="bsp-status-unknown">' . __('Unknown', 'booking-system-pro') . '</span>';
                    }
                }
                break;
                
            case 'total':
                $total = get_post_meta($post_id, '_total_cost', true);
                if ($total) {
                    echo '$' . number_format(floatval($total), 2);
                }
                break;
        }
    }
    
    public function add_booking_row_actions($actions, $post) {
        if ($post->post_type === 'bsp_booking') {
            $actions['view_details'] = '<a href="' . admin_url('admin.php?page=bsp-bookings&action=view&id=' . $post->ID) . '">' . __('View Details', 'booking-system-pro') . '</a>';
        }
        return $actions;
    }
    
    // AJAX Handlers
    public function ajax_update_company() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_admin_nonce')) {
            wp_die(__('Security check failed.', 'booking-system-pro'));
        }
        
        // Check capabilities
        if (!current_user_can($this->capabilities)) {
            wp_die(__('You do not have permission to perform this action.', 'booking-system-pro'));
        }
        
        // Validate required fields
        if (empty($_POST['company_id']) || empty($_POST['company_name']) || empty($_POST['company_email'])) {
            wp_send_json_error(__('Company ID, name, and email are required.', 'booking-system-pro'));
        }
        
        $company_id = intval($_POST['company_id']);
        $company_data = [
            'name' => sanitize_text_field($_POST['company_name']),
            'description' => sanitize_textarea_field($_POST['company_description']),
            'email' => sanitize_email($_POST['company_email']),
            'phone' => sanitize_text_field($_POST['company_phone']),
            'address' => sanitize_textarea_field($_POST['company_address']),
            'website' => esc_url_raw($_POST['company_website']),
            'available_hours' => sanitize_text_field($_POST['available_hours'])
        ];
        
        // Validate email
        if (!is_email($company_data['email'])) {
            wp_send_json_error(__('Please enter a valid email address.', 'booking-system-pro'));
        }
        
        $db = BSP_Database_Unified::get_instance();
        $result = $db->update_company($company_id, $company_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success([
                'message' => __('Company updated successfully.', 'booking-system-pro'),
                'reload' => true
            ]);
        }
    }
    
    public function ajax_bulk_action() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_admin_nonce')) {
            wp_die(__('Security check failed.', 'booking-system-pro'));
        }
        
        // Check capabilities
        if (!current_user_can($this->capabilities)) {
            wp_die(__('You do not have permission to perform this action.', 'booking-system-pro'));
        }
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $items = array_map('intval', $_POST['items']);
        
        if (empty($items)) {
            wp_send_json_error(__('No items selected.', 'booking-system-pro'));
        }
        
        $db = BSP_Database_Unified::get_instance();
        $success_count = 0;
        
        foreach ($items as $item_id) {
            switch ($action) {
                case 'delete':
                    $result = $db->delete_company($item_id);
                    if (!is_wp_error($result)) {
                        $success_count++;
                    }
                    break;
                    
                case 'activate':
                    $result = $db->update_company($item_id, ['status' => 'active']);
                    if (!is_wp_error($result)) {
                        $success_count++;
                    }
                    break;
                    
                case 'deactivate':
                    $result = $db->update_company($item_id, ['status' => 'inactive']);
                    if (!is_wp_error($result)) {
                        $success_count++;
                    }
                    break;
            }
        }
        
        wp_send_json_success([
            'message' => sprintf(__('%d items processed successfully.', 'booking-system-pro'), $success_count)
        ]);
    }
    
    public function ajax_load_calendar() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_admin_nonce')) {
            wp_die(__('Security check failed.', 'booking-system-pro'));
        }
        
        $year = intval($_POST['year']);
        $month = intval($_POST['month']);
        
        // Generate calendar HTML (placeholder for now)
        $html = '<div class="bsp-calendar" data-year="' . $year . '" data-month="' . $month . '">';
        $html .= '<p>Calendar for ' . $month . '/' . $year . ' - Feature coming soon</p>';
        $html .= '</div>';
        
        wp_send_json_success(['html' => $html]);
    }
    
    public function ajax_load_bookings_for_date() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_admin_nonce')) {
            wp_die(__('Security check failed.', 'booking-system-pro'));
        }
        
        $date = sanitize_text_field($_POST['date']);
        
        // Get bookings for the specified date
        $bookings = get_posts([
            'post_type' => 'bsp_booking',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_booking_date',
                    'value' => $date,
                    'compare' => '='
                ]
            ]
        ]);
        
        $html = '<h4>' . sprintf(__('Bookings for %s', 'booking-system-pro'), $date) . '</h4>';
        
        if (empty($bookings)) {
            $html .= '<p>' . __('No bookings found for this date.', 'booking-system-pro') . '</p>';
        } else {
            $html .= '<ul>';
            foreach ($bookings as $booking) {
                $customer_name = get_post_meta($booking->ID, '_customer_name', true);
                $booking_time = get_post_meta($booking->ID, '_booking_time', true);
                $html .= '<li>' . esc_html($customer_name) . ' - ' . esc_html($booking_time) . '</li>';
            }
            $html .= '</ul>';
        }
        
        wp_send_json_success(['html' => $html]);
    }
    
    public function ajax_auto_save_setting() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_admin_nonce')) {
            wp_die(__('Security check failed.', 'booking-system-pro'));
        }
        
        // Check capabilities
        if (!current_user_can($this->capabilities)) {
            wp_die(__('You do not have permission to perform this action.', 'booking-system-pro'));
        }
        
        $setting = sanitize_text_field($_POST['setting']);
        $value = sanitize_text_field($_POST['value']);
        
        // Save the setting
        update_option('bsp_' . $setting, $value);
        
        wp_send_json_success(['message' => __('Setting saved.', 'booking-system-pro')]);
    }
    
    /**
     * Add export button to bookings list page
     */
    public function add_export_button($post_type) {
        if ($post_type === 'bsp_booking') {
            echo '<div style="float: right; margin-left: 10px;">';
            echo '<a href="' . wp_nonce_url(admin_url('edit.php?post_type=bsp_booking&export_csv=1'), 'bsp_export_csv') . '" class="button button-secondary">';
            echo '<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>';
            echo __('Export CSV', 'booking-system-pro');
            echo '</a>';
            echo '</div>';
        }
    }
    
    /**
     * Handle CSV export
     */
    public function handle_csv_export() {
        if (!isset($_GET['export_csv']) || $_GET['export_csv'] !== '1') {
            return;
        }
        
        if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'bsp_booking') {
            return;
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'bsp_export_csv')) {
            wp_die(__('Security check failed.', 'booking-system-pro'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'booking-system-pro'));
        }
        
        $this->export_bookings_csv();
    }
    
    /**
     * Export bookings to CSV
     */
    private function export_bookings_csv() {
        // Get all bookings
        $bookings = get_posts([
            'post_type' => 'bsp_booking',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        // Set headers for CSV download
        $filename = 'bookings-export-' . date('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create file pointer connected to output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        
        // CSV column headers
        $headers = [
            'Booking ID',
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Customer Address',
            'ZIP Code',
            'Service Type',
            'Company',
            'Appointment Date',
            'Appointment Time',
            'Status',
            'Created Date',
            'Service ZIP',
            'Service Details',
            'Multiple Appointments',
            'Notes'
        ];
        
        fputcsv($output, $headers);
        
        // Get database instance
        $db = BSP_Database_Unified::get_instance();
        
        // Export each booking
        foreach ($bookings as $post) {
            $booking = $db->get_booking($post->ID);
            
            if (!$booking) continue;
            
            // Get service-specific details
            $service_details = [];
            $service_zip = '';
            
            $service = strtolower($booking['service_name'] ?? '');
            switch ($service) {
                case 'roof':
                    if ($booking['roof_zip']) $service_zip = $booking['roof_zip'];
                    if ($booking['roof_action']) $service_details[] = 'Action: ' . $booking['roof_action'];
                    if ($booking['roof_material']) $service_details[] = 'Material: ' . $booking['roof_material'];
                    break;
                case 'windows':
                    if ($booking['windows_zip']) $service_zip = $booking['windows_zip'];
                    if ($booking['windows_action']) $service_details[] = 'Action: ' . $booking['windows_action'];
                    if ($booking['windows_replace_qty']) $service_details[] = 'Quantity: ' . $booking['windows_replace_qty'];
                    if ($booking['windows_repair_needed']) $service_details[] = 'Repair: ' . $booking['windows_repair_needed'];
                    break;
                case 'bathroom':
                    if ($booking['bathroom_zip']) $service_zip = $booking['bathroom_zip'];
                    if ($booking['bathroom_option']) $service_details[] = 'Option: ' . $booking['bathroom_option'];
                    break;
                case 'siding':
                    if ($booking['siding_zip']) $service_zip = $booking['siding_zip'];
                    if ($booking['siding_option']) $service_details[] = 'Option: ' . $booking['siding_option'];
                    if ($booking['siding_material']) $service_details[] = 'Material: ' . $booking['siding_material'];
                    break;
                case 'kitchen':
                    if ($booking['kitchen_zip']) $service_zip = $booking['kitchen_zip'];
                    if ($booking['kitchen_action']) $service_details[] = 'Action: ' . $booking['kitchen_action'];
                    if ($booking['kitchen_component']) $service_details[] = 'Component: ' . $booking['kitchen_component'];
                    break;
                case 'decks':
                    if ($booking['decks_zip']) $service_zip = $booking['decks_zip'];
                    if ($booking['decks_action']) $service_details[] = 'Action: ' . $booking['decks_action'];
                    if ($booking['decks_material']) $service_details[] = 'Material: ' . $booking['decks_material'];
                    break;
            }
            
            // Format multiple appointments
            $appointments_text = '';
            if (!empty($booking['appointments'])) {
                $appointments = json_decode($booking['appointments'], true);
                if ($appointments && is_array($appointments)) {
                    $apt_strings = [];
                    foreach ($appointments as $apt) {
                        $apt_strings[] = $apt['company'] . ' - ' . $apt['date'] . ' at ' . $apt['time'];
                    }
                    $appointments_text = implode('; ', $apt_strings);
                } else {
                    $appointments_text = $booking['appointments'];
                }
            }
            
            // CSV row data
            $row = [
                $booking['id'],
                $booking['customer_name'],
                $booking['customer_email'],
                $booking['customer_phone'],
                $booking['customer_address'],
                $booking['zip_code'],
                $booking['service_name'],
                $booking['company_name'],
                $booking['appointment_date'] ? date('Y-m-d', strtotime($booking['appointment_date'])) : '',
                $booking['appointment_time'] ? date('H:i', strtotime($booking['appointment_time'])) : '',
                ucfirst($booking['status']),
                date('Y-m-d H:i:s', strtotime($booking['created_at'])),
                $service_zip,
                implode('; ', $service_details),
                $appointments_text,
                strip_tags($booking['notes'])
            ];
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
}

// Backward compatibility - create alias for old plugin
if (!class_exists('Booking_System_Admin')) {
    class Booking_System_Admin {
        public function __construct() {
            // Initialize the new unified admin if not already done
            if (!did_action('init') || !class_exists('BSP_Admin')) {
                add_action('init', function() {
                    BSP_Admin::get_instance();
                });
            } else {
                BSP_Admin::get_instance();
            }
        }
    }
}
?>
