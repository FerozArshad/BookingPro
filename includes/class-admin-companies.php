<?php
/**
 * Admin Companies Management for Booking System Pro
 */

if (!defined('ABSPATH')) exit;

class BSP_Admin_Companies {
    
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
     * Render companies page
     */
    public function render_companies_page() {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'add':
                $this->render_add_company_page();
                break;
            case 'edit':
                $this->render_edit_company_page();
                break;
            default:
                $this->render_companies_list_page();
                break;
        }
    }
    
    /**
     * Render companies list
     */
    private function render_companies_list_page() {
        if (isset($_POST['action']) && $_POST['action'] !== '-1') {
            $this->handle_bulk_actions();
        }
        
        $companies = $this->db->get_companies();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Companies', 'booking-system-pro'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=bsp-companies&action=add'); ?>" class="page-title-action">
                <?php _e('Add New', 'booking-system-pro'); ?>
            </a>
            
            <form method="post">
                <?php wp_nonce_field('bsp_bulk_companies', 'bsp_nonce'); ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="action">
                            <option value="-1"><?php _e('Bulk Actions', 'booking-system-pro'); ?></option>
                            <option value="activate"><?php _e('Activate', 'booking-system-pro'); ?></option>
                            <option value="deactivate"><?php _e('Deactivate', 'booking-system-pro'); ?></option>
                            <option value="delete"><?php _e('Delete', 'booking-system-pro'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php _e('Apply', 'booking-system-pro'); ?>">
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-1">
                            </td>
                            <th><?php _e('Name', 'booking-system-pro'); ?></th>
                            <th><?php _e('Email', 'booking-system-pro'); ?></th>
                            <th><?php _e('Phone', 'booking-system-pro'); ?></th>
                            <th><?php _e('Location', 'booking-system-pro'); ?></th>
                            <th><?php _e('Services', 'booking-system-pro'); ?></th>
                            <th><?php _e('Status', 'booking-system-pro'); ?></th>
                            <th><?php _e('Actions', 'booking-system-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($companies)): ?>
                            <tr>
                                <td colspan="8" class="no-items"><?php _e('No companies found.', 'booking-system-pro'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($companies as $company): ?>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" name="company_ids[]" value="<?php echo esc_attr($company['id']); ?>">
                                    </th>
                                    <td>
                                        <strong><?php echo esc_html($company['name']); ?></strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo admin_url('admin.php?page=bsp-companies&action=edit&id=' . $company['id']); ?>">
                                                    <?php _e('Edit', 'booking-system-pro'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($company['email']); ?></td>
                                    <td><?php echo esc_html($company['phone']); ?></td>
                                    <td><?php echo esc_html($company['address']); ?></td>
                                    <td><?php echo intval($company['service_count'] ?? 0); ?></td>
                                    <td>
                                        <span class="bsp-status bsp-status-<?php echo esc_attr($company['status'] ?? 'active'); ?>">
                                            <?php echo esc_html(ucfirst($company['status'] ?? 'active')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=bsp-companies&action=edit&id=' . $company['id']); ?>" class="button button-small">
                                            <?php _e('Edit', 'booking-system-pro'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        
        <style>
        .bsp-status {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .bsp-status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .bsp-status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        </style>
        <?php
    }
    
    /**
     * Render add company page
     */
    private function render_add_company_page() {
        if (isset($_POST['submit_company'])) {
            $this->handle_save_company();
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Add New Company', 'booking-system-pro'); ?></h1>
            
            <form method="post" class="bsp-company-form">
                <?php wp_nonce_field('bsp_save_company', 'bsp_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Company Name', 'booking-system-pro'); ?> <span class="required">*</span></th>
                        <td><input type="text" name="name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Email', 'booking-system-pro'); ?> <span class="required">*</span></th>
                        <td><input type="email" name="email" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Phone', 'booking-system-pro'); ?></th>
                        <td><input type="tel" name="phone" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Website', 'booking-system-pro'); ?></th>
                        <td><input type="url" name="website" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Address', 'booking-system-pro'); ?></th>
                        <td><textarea name="address" class="large-text" rows="3"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Description', 'booking-system-pro'); ?></th>
                        <td><textarea name="description" class="large-text" rows="5"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Status', 'booking-system-pro'); ?></th>
                        <td>
                            <select name="status">
                                <option value="active"><?php _e('Active', 'booking-system-pro'); ?></option>
                                <option value="inactive"><?php _e('Inactive', 'booking-system-pro'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Business Hours', 'booking-system-pro'); ?></h2>
                <table class="form-table">
                    <?php 
                    $days = [
                        'monday' => __('Monday', 'booking-system-pro'),
                        'tuesday' => __('Tuesday', 'booking-system-pro'),
                        'wednesday' => __('Wednesday', 'booking-system-pro'),
                        'thursday' => __('Thursday', 'booking-system-pro'),
                        'friday' => __('Friday', 'booking-system-pro'),
                        'saturday' => __('Saturday', 'booking-system-pro'),
                        'sunday' => __('Sunday', 'booking-system-pro')
                    ];
                    
                    foreach ($days as $day => $label): ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($label); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="business_hours[<?php echo $day; ?>][is_open]" value="1" checked>
                                    <?php _e('Open', 'booking-system-pro'); ?>
                                </label>
                                <input type="time" name="business_hours[<?php echo $day; ?>][start]" value="09:00">
                                <?php _e('to', 'booking-system-pro'); ?>
                                <input type="time" name="business_hours[<?php echo $day; ?>][end]" value="17:00">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit_company" class="button-primary" value="<?php _e('Save Company', 'booking-system-pro'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=bsp-companies'); ?>" class="button"><?php _e('Cancel', 'booking-system-pro'); ?></a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render edit company page
     */
    private function render_edit_company_page() {
        $company_id = intval($_GET['id'] ?? 0);
        $company = $this->db->get_company($company_id);
        
        if (!$company) {
            wp_die(__('Company not found.', 'booking-system-pro'));
        }
        
        if (isset($_POST['submit_company'])) {
            $this->handle_save_company($company_id);
        }
        
        $business_hours = $this->db->get_company_business_hours($company_id);
        ?>
        <div class="wrap">
            <h1><?php _e('Edit Company', 'booking-system-pro'); ?></h1>
            
            <form method="post" class="bsp-company-form">
                <?php wp_nonce_field('bsp_save_company', 'bsp_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Company Name', 'booking-system-pro'); ?> <span class="required">*</span></th>
                        <td><input type="text" name="name" class="regular-text" value="<?php echo esc_attr($company['name']); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Email', 'booking-system-pro'); ?> <span class="required">*</span></th>
                        <td><input type="email" name="email" class="regular-text" value="<?php echo esc_attr($company['email']); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Phone', 'booking-system-pro'); ?></th>
                        <td><input type="tel" name="phone" class="regular-text" value="<?php echo esc_attr($company['phone']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Website', 'booking-system-pro'); ?></th>
                        <td><input type="url" name="website" class="regular-text" value="<?php echo esc_attr($company['website']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Address', 'booking-system-pro'); ?></th>
                        <td><textarea name="address" class="large-text" rows="3"><?php echo esc_textarea($company['address']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Description', 'booking-system-pro'); ?></th>
                        <td><textarea name="description" class="large-text" rows="5"><?php echo esc_textarea($company['description']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Status', 'booking-system-pro'); ?></th>
                        <td>
                            <select name="status">
                                <option value="active" <?php selected($company['status'], 'active'); ?>><?php _e('Active', 'booking-system-pro'); ?></option>
                                <option value="inactive" <?php selected($company['status'], 'inactive'); ?>><?php _e('Inactive', 'booking-system-pro'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Business Hours', 'booking-system-pro'); ?></h2>
                <table class="form-table">
                    <?php 
                    $days = [
                        'monday' => __('Monday', 'booking-system-pro'),
                        'tuesday' => __('Tuesday', 'booking-system-pro'),
                        'wednesday' => __('Wednesday', 'booking-system-pro'),
                        'thursday' => __('Thursday', 'booking-system-pro'),
                        'friday' => __('Friday', 'booking-system-pro'),
                        'saturday' => __('Saturday', 'booking-system-pro'),
                        'sunday' => __('Sunday', 'booking-system-pro')
                    ];
                    
                    foreach ($days as $day => $label): 
                        $day_hours = $business_hours[$day] ?? ['is_open' => false, 'start' => '09:00', 'end' => '17:00'];
                    ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($label); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="business_hours[<?php echo $day; ?>][is_open]" value="1" <?php checked($day_hours['is_open']); ?>>
                                    <?php _e('Open', 'booking-system-pro'); ?>
                                </label>
                                <input type="time" name="business_hours[<?php echo $day; ?>][start]" value="<?php echo esc_attr($day_hours['start']); ?>">
                                <?php _e('to', 'booking-system-pro'); ?>
                                <input type="time" name="business_hours[<?php echo $day; ?>][end]" value="<?php echo esc_attr($day_hours['end']); ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit_company" class="button-primary" value="<?php _e('Update Company', 'booking-system-pro'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=bsp-companies'); ?>" class="button"><?php _e('Cancel', 'booking-system-pro'); ?></a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle saving company
     */
    private function handle_save_company($company_id = null) {
        if (!wp_verify_nonce($_POST['bsp_nonce'], 'bsp_save_company')) {
            wp_die(__('Security check failed.', 'booking-system-pro'));
        }
        
        $company_data = [
            'name' => sanitize_text_field($_POST['name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'website' => esc_url_raw($_POST['website']),
            'address' => sanitize_textarea_field($_POST['address']),
            'description' => sanitize_textarea_field($_POST['description']),
            'status' => sanitize_text_field($_POST['status'])
        ];
        
        $business_hours = $_POST['business_hours'] ?? [];
        
        if ($company_id) {
            $result = $this->db->update_company($company_id, $company_data);
            $this->db->update_company_business_hours($company_id, $business_hours);
            $message = __('Company updated successfully.', 'booking-system-pro');
        } else {
            $result = $this->db->insert_company($company_data);
            if ($result) {
                $this->db->update_company_business_hours($result, $business_hours);
            }
            $message = __('Company created successfully.', 'booking-system-pro');
        }
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=bsp-companies&message=' . urlencode($message)));
            exit;
        } else {
            wp_die(__('Failed to save company.', 'booking-system-pro'));
        }
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        if (!wp_verify_nonce($_POST['bsp_nonce'], 'bsp_bulk_companies')) {
            wp_die(__('Security check failed.', 'booking-system-pro'));
        }
        
        $action = sanitize_text_field($_POST['action']);
        $company_ids = array_map('intval', $_POST['company_ids'] ?? []);
        
        if (empty($company_ids)) {
            return;
        }
        
        foreach ($company_ids as $company_id) {
            switch ($action) {
                case 'activate':
                    $this->db->update_company($company_id, ['status' => 'active']);
                    break;
                case 'deactivate':
                    $this->db->update_company($company_id, ['status' => 'inactive']);
                    break;
                case 'delete':
                    $this->db->delete_company($company_id);
                    break;
            }
        }
        
        wp_redirect(admin_url('admin.php?page=bsp-companies&message=' . urlencode('Bulk action completed.')));
        exit;
    }
}
