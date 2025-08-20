<?php
/**
 * Admin Services Template
 */

if (!defined('ABSPATH')) exit;

// Get database instance
$db = BSP_Database_Unified::get_instance();
$message = '';
$error = '';

// Handle form submissions
if (isset($_POST['submit_service']) && wp_verify_nonce($_POST['_wpnonce'], 'bsp_add_service')) {
    $service_data = [
        'name' => sanitize_text_field($_POST['service_name']),
        'description' => sanitize_textarea_field($_POST['service_description']),
        'duration' => intval($_POST['service_duration']),
        'price' => floatval($_POST['service_price']),
        'category' => sanitize_text_field($_POST['service_category']),
        'status' => 'active'
    ];
    
    // Create service as custom post
    $service_post = wp_insert_post([
        'post_title' => $service_data['name'],
        'post_content' => $service_data['description'],
        'post_status' => 'publish',
        'post_type' => 'bsp_service',
        'meta_input' => [
            '_duration' => $service_data['duration'],
            '_price' => $service_data['price'],
            '_category' => $service_data['category']
        ]
    ]);
    
    if (is_wp_error($service_post)) {
        $error = $service_post->get_error_message();
    } else {
        // Set service type taxonomy
        wp_set_object_terms($service_post, $service_data['category'], 'bsp_service_type');
        $message = __('Service added successfully.', 'booking-system-pro');
    }
}

// Get existing services
$services = get_posts([
    'post_type' => 'bsp_service',
    'posts_per_page' => -1,
    'post_status' => 'publish'
]);

// Get service categories
$service_categories = get_terms([
    'taxonomy' => 'bsp_service_type',
    'hide_empty' => false
]);
?>

<div class="wrap bsp-admin-wrapper">
    <div class="bsp-admin-header">
        <h1><?php _e('Services', 'booking-system-pro'); ?></h1>
        <p><?php _e('Manage your bookable services, pricing, and categories.', 'booking-system-pro'); ?></p>
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
    
    <!-- Add New Service -->
    <div class="bsp-form-section">
        <h3><?php _e('Add New Service', 'booking-system-pro'); ?></h3>
        <form method="post" action="">
            <?php wp_nonce_field('bsp_add_service'); ?>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field half">
                    <label for="service_name"><?php _e('Service Name *', 'booking-system-pro'); ?></label>
                    <input type="text" id="service_name" name="service_name" required>
                </div>
                <div class="bsp-form-field half">
                    <label for="service_category"><?php _e('Category', 'booking-system-pro'); ?></label>
                    <select id="service_category" name="service_category">
                        <option value=""><?php _e('Select Category', 'booking-system-pro'); ?></option>
                        <?php foreach ($service_categories as $category) : ?>
                            <option value="<?php echo esc_attr($category->slug); ?>">
                                <?php echo esc_html($category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field half">
                    <label for="service_duration"><?php _e('Duration (minutes)', 'booking-system-pro'); ?></label>
                    <input type="number" id="service_duration" name="service_duration" min="1" value="60">
                </div>
                <div class="bsp-form-field half">
                    <label for="service_price"><?php _e('Price ($)', 'booking-system-pro'); ?></label>
                    <input type="number" id="service_price" name="service_price" min="0" step="0.01" value="100.00">
                </div>
            </div>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field">
                    <label for="service_description"><?php _e('Description', 'booking-system-pro'); ?></label>
                    <textarea id="service_description" name="service_description" rows="4"></textarea>
                </div>
            </div>
            
            <div class="bsp-actions">
                <input type="submit" name="submit_service" class="bsp-btn" value="<?php _e('Add Service', 'booking-system-pro'); ?>">
            </div>
        </form>
    </div>
    
    <!-- Existing Services -->
    <div class="bsp-form-section">
        <h3><?php _e('Existing Services', 'booking-system-pro'); ?></h3>
        
        <?php if (!empty($services)) : ?>
            <table class="bsp-admin-table">
                <thead>
                    <tr>
                        <th><?php _e('Service', 'booking-system-pro'); ?></th>
                        <th><?php _e('Category', 'booking-system-pro'); ?></th>
                        <th><?php _e('Duration', 'booking-system-pro'); ?></th>
                        <th><?php _e('Price', 'booking-system-pro'); ?></th>
                        <th><?php _e('Bookings', 'booking-system-pro'); ?></th>
                        <th><?php _e('Actions', 'booking-system-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service) : ?>
                        <?php
                        $duration = get_post_meta($service->ID, '_duration', true);
                        $price = get_post_meta($service->ID, '_price', true);
                        $categories = wp_get_post_terms($service->ID, 'bsp_service_type');
                        
                        // Get booking count for this service
                        $booking_count = get_posts([
                            'post_type' => 'bsp_booking',
                            'posts_per_page' => -1,
                            'meta_query' => [
                                [
                                    'key' => '_service_id',
                                    'value' => $service->ID,
                                    'compare' => '='
                                ]
                            ]
                        ]);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($service->post_title); ?></strong>
                                <?php if ($service->post_content) : ?>
                                    <br><small><?php echo esc_html(wp_trim_words($service->post_content, 10)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($categories)) : ?>
                                    <span class="bsp-service-category"><?php echo esc_html($categories[0]->name); ?></span>
                                <?php else : ?>
                                    <span class="bsp-no-category"><?php _e('Uncategorized', 'booking-system-pro'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($duration) : ?>
                                    <strong><?php echo esc_html($duration); ?> <?php _e('min', 'booking-system-pro'); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($price) : ?>
                                    <strong>$<?php echo number_format(floatval($price), 2); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo count($booking_count); ?></strong>
                                <?php if (count($booking_count) > 0) : ?>
                                    <br><small><a href="<?php echo admin_url('admin.php?page=bsp-bookings&service=' . $service->ID); ?>"><?php _e('View', 'booking-system-pro'); ?></a></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo get_edit_post_link($service->ID); ?>" class="bsp-btn bsp-btn-small"><?php _e('Edit', 'booking-system-pro'); ?></a>
                                <a href="<?php echo get_delete_post_link($service->ID); ?>" class="bsp-btn bsp-btn-small bsp-btn-danger bsp-delete-btn"><?php _e('Delete', 'booking-system-pro'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php _e('No services found. Add your first service above.', 'booking-system-pro'); ?></p>
        <?php endif; ?>
    </div>
</div>
