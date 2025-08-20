<?php
/**
 * Admin Companies Template
 */

if (!defined('ABSPATH')) exit;

// Get companies from database
$db = BSP_Database_Unified::get_instance();
$message = '';
$error = '';

// Handle form submissions
if (isset($_POST['submit_company']) && wp_verify_nonce($_POST['_wpnonce'], 'bsp_add_company')) {
    $company_data = [
        'name' => sanitize_text_field($_POST['company_name']),
        'description' => sanitize_textarea_field($_POST['company_description']),
        'email' => sanitize_email($_POST['company_email']),
        'phone' => sanitize_text_field($_POST['company_phone']),
        'address' => sanitize_textarea_field($_POST['company_address']),
        'website' => esc_url_raw($_POST['company_website']),
        'available_hours' => sanitize_text_field($_POST['available_hours']),
        'status' => 'active'
    ];
    
    $result = BSP_Database_Unified::create_company($company_data);
    
    if (is_wp_error($result)) {
        $error = $result->get_error_message();
    } else {
        $message = __('Company added successfully.', 'booking-system-pro');
    }
}

// Handle company deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $company_id = intval($_GET['id']);
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_company_' . $company_id)) {
        $result = BSP_Database_Unified::delete_company($company_id);
        if (is_wp_error($result)) {
            $error = $result->get_error_message();
        } else {
            $message = __('Company deleted successfully.', 'booking-system-pro');
        }
    }
}

// Handle status toggle
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $company_id = intval($_GET['id']);
    if (wp_verify_nonce($_GET['_wpnonce'], 'toggle_status_' . $company_id)) {
        $result = BSP_Database_Unified::toggle_company_status($company_id);
        if (is_wp_error($result)) {
            $error = $result->get_error_message();
        } else {
            $message = __('Company status updated successfully.', 'booking-system-pro');
        }
    }
}

$companies = BSP_Database_Unified::get_companies(['status' => '']);
?>

<div class="wrap bsp-admin-wrapper">
    <div class="bsp-admin-header">
        <h1><?php _e('Companies', 'booking-system-pro'); ?></h1>
        <p><?php _e('Manage service provider companies and their availability settings.', 'booking-system-pro'); ?></p>
    </div>
    
    <?php if ($message) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($error) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Add New Company -->
    <div class="bsp-form-section">
        <h3><?php _e('Add New Company', 'booking-system-pro'); ?></h3>
        <form method="post" action="">
            <?php wp_nonce_field('bsp_add_company'); ?>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field half">
                    <label for="company_name"><?php _e('Company Name *', 'booking-system-pro'); ?></label>
                    <input type="text" id="company_name" name="company_name" required>
                </div>
                <div class="bsp-form-field half">
                    <label for="company_email"><?php _e('Email *', 'booking-system-pro'); ?></label>
                    <input type="email" id="company_email" name="company_email" required>
                </div>
            </div>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field half">
                    <label for="company_phone"><?php _e('Phone', 'booking-system-pro'); ?></label>
                    <input type="text" id="company_phone" name="company_phone">
                </div>
                <div class="bsp-form-field half">
                    <label for="company_website"><?php _e('Website', 'booking-system-pro'); ?></label>
                    <input type="url" id="company_website" name="company_website">
                </div>
            </div>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field half">
                    <label for="company_description"><?php _e('Description', 'booking-system-pro'); ?></label>
                    <textarea id="company_description" name="company_description" rows="3"></textarea>
                </div>
                <div class="bsp-form-field half">
                    <label for="available_hours"><?php _e('Available Hours', 'booking-system-pro'); ?></label>
                    <input type="text" id="available_hours" name="available_hours" placeholder="9:00 AM - 5:00 PM" value="9:00 AM - 5:00 PM">
                </div>
            </div>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field">
                    <label for="company_address"><?php _e('Address', 'booking-system-pro'); ?></label>
                    <textarea id="company_address" name="company_address" rows="3"></textarea>
                </div>
            </div>
            
            <div class="bsp-actions">
                <input type="submit" name="submit_company" class="bsp-btn" value="<?php _e('Add Company', 'booking-system-pro'); ?>">
            </div>
        </form>
    </div>
    
    <!-- Existing Companies -->
    <div class="bsp-form-section">
        <h3><?php _e('Existing Companies', 'booking-system-pro'); ?></h3>
        
        <?php if (!empty($companies)) : ?>
            <table class="bsp-admin-table">
                <thead>
                    <tr>
                        <th><?php _e('Company', 'booking-system-pro'); ?></th>
                        <th><?php _e('Contact', 'booking-system-pro'); ?></th>
                        <th><?php _e('Availability', 'booking-system-pro'); ?></th>
                        <th><?php _e('Status', 'booking-system-pro'); ?></th>
                        <th><?php _e('Bookings', 'booking-system-pro'); ?></th>
                        <th><?php _e('Actions', 'booking-system-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($companies as $company) : ?>
                        <?php
                        // Get booking count for this company
                        $booking_count = get_posts([
                            'post_type' => 'bsp_booking',
                            'posts_per_page' => -1,
                            'meta_query' => [
                                [
                                    'key' => '_company_id',
                                    'value' => $company->id,
                                    'compare' => '='
                                ]
                            ]
                        ]);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($company->name); ?></strong>
                                <?php if ($company->description) : ?>
                                    <br><small><?php echo esc_html(wp_trim_words($company->description, 10)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($company->email) : ?>
                                    <strong><?php echo esc_html($company->email); ?></strong><br>
                                <?php endif; ?>
                                <?php if ($company->phone) : ?>
                                    <small><?php echo esc_html($company->phone); ?></small><br>
                                <?php endif; ?>
                                <?php if ($company->website) : ?>
                                    <small><a href="<?php echo esc_url($company->website); ?>" target="_blank"><?php _e('Website', 'booking-system-pro'); ?></a></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($company->available_hours) : ?>
                                    <strong><?php echo esc_html($company->available_hours); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="bsp-status-<?php echo esc_attr($company->status); ?>">
                                    <?php echo esc_html(ucfirst($company->status)); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo count($booking_count); ?></strong>
                                <?php if (count($booking_count) > 0) : ?>
                                    <br><small><a href="<?php echo admin_url('admin.php?page=bsp-bookings&company=' . $company->id); ?>"><?php _e('View', 'booking-system-pro'); ?></a></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" 
                                        class="bsp-btn bsp-btn-small bsp-edit-company-btn" 
                                        data-company-id="<?php echo esc_attr($company->id); ?>"
                                        data-company-name="<?php echo esc_attr($company->name); ?>"
                                        data-company-email="<?php echo esc_attr($company->email); ?>"
                                        data-company-phone="<?php echo esc_attr($company->phone); ?>"
                                        data-company-website="<?php echo esc_attr($company->website); ?>"
                                        data-company-description="<?php echo esc_attr($company->description); ?>"
                                        data-company-address="<?php echo esc_attr($company->address); ?>"
                                        data-company-hours="<?php echo esc_attr($company->available_hours); ?>">
                                    <?php _e('Edit', 'booking-system-pro'); ?>
                                </button>
                                <a href="<?php echo wp_nonce_url(add_query_arg(['action' => 'toggle_status', 'id' => $company->id]), 'toggle_status_' . $company->id); ?>" 
                                   class="bsp-btn bsp-btn-small <?php echo $company->status === 'active' ? 'bsp-btn-secondary' : 'bsp-btn-success'; ?>">
                                    <?php echo $company->status === 'active' ? __('Deactivate', 'booking-system-pro') : __('Activate', 'booking-system-pro'); ?>
                                </a>
                                <a href="<?php echo wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $company->id]), 'delete_company_' . $company->id); ?>" 
                                   class="bsp-btn bsp-btn-small bsp-btn-danger bsp-delete-btn"><?php _e('Delete', 'booking-system-pro'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php _e('No companies found. Add your first company above.', 'booking-system-pro'); ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Company Modal -->
<div id="bsp-edit-company-modal" class="bsp-modal" style="display: none;">
    <div class="bsp-modal-content">
        <div class="bsp-modal-header">
            <h3><?php _e('Edit Company', 'booking-system-pro'); ?></h3>
            <button type="button" class="bsp-modal-close">&times;</button>
        </div>
        <div class="bsp-modal-body">
            <form id="bsp-edit-company-form">
                <input type="hidden" id="edit_company_id" name="company_id">
                
                <div class="bsp-form-row">
                    <div class="bsp-form-field half">
                        <label for="edit_company_name"><?php _e('Company Name *', 'booking-system-pro'); ?></label>
                        <input type="text" id="edit_company_name" name="company_name" required>
                    </div>
                    <div class="bsp-form-field half">
                        <label for="edit_company_email"><?php _e('Email *', 'booking-system-pro'); ?></label>
                        <input type="email" id="edit_company_email" name="company_email" required>
                    </div>
                </div>
                
                <div class="bsp-form-row">
                    <div class="bsp-form-field half">
                        <label for="edit_company_phone"><?php _e('Phone', 'booking-system-pro'); ?></label>
                        <input type="text" id="edit_company_phone" name="company_phone">
                    </div>
                    <div class="bsp-form-field half">
                        <label for="edit_company_website"><?php _e('Website', 'booking-system-pro'); ?></label>
                        <input type="url" id="edit_company_website" name="company_website">
                    </div>
                </div>
                
                <div class="bsp-form-row">
                    <div class="bsp-form-field half">
                        <label for="edit_company_description"><?php _e('Description', 'booking-system-pro'); ?></label>
                        <textarea id="edit_company_description" name="company_description" rows="3"></textarea>
                    </div>
                    <div class="bsp-form-field half">
                        <label for="edit_available_hours"><?php _e('Available Hours', 'booking-system-pro'); ?></label>
                        <input type="text" id="edit_available_hours" name="available_hours" placeholder="9:00 AM - 5:00 PM">
                    </div>
                </div>
                
                <div class="bsp-form-row">
                    <div class="bsp-form-field">
                        <label for="edit_company_address"><?php _e('Address', 'booking-system-pro'); ?></label>
                        <textarea id="edit_company_address" name="company_address" rows="3"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="bsp-modal-footer">
            <button type="button" class="bsp-btn bsp-btn-secondary bsp-modal-close"><?php _e('Cancel', 'booking-system-pro'); ?></button>
            <button type="button" id="bsp-save-company-btn" class="bsp-btn"><?php _e('Save Changes', 'booking-system-pro'); ?></button>
        </div>
    </div>
</div>
