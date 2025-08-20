<?php
/**
 * Admin Bookings Template
 */

if (!defined('ABSPATH')) exit;

// Handle actions
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get bookings with filters
$args = [
    'post_type' => 'bsp_booking',
    'posts_per_page' => 20,
    'paged' => max(1, get_query_var('paged')),
    'orderby' => 'date',
    'order' => 'DESC'
];

// Apply filters
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $args['tax_query'] = [
        [
            'taxonomy' => 'bsp_booking_status',
            'field' => 'slug',
            'terms' => sanitize_text_field($_GET['status'])
        ]
    ];
}

if (isset($_GET['company']) && !empty($_GET['company'])) {
    $args['meta_query'] = [
        [
            'key' => '_company_id',
            'value' => intval($_GET['company']),
            'compare' => '='
        ]
    ];
}

$bookings_query = new WP_Query($args);
$db = BSP_Database_Unified::get_instance();
$companies = $db->get_companies();
?>

<div class="wrap bsp-admin-wrapper">
    <div class="bsp-admin-header">
        <h1><?php _e('All Bookings', 'booking-system-pro'); ?></h1>
        <p><?php _e('Manage and view all booking requests and appointments.', 'booking-system-pro'); ?></p>
    </div>
    
    <!-- Filters -->
    <div class="bsp-form-section">
        <form method="get" action="">
            <input type="hidden" name="page" value="bsp-bookings">
            <div class="bsp-form-row">
                <div class="bsp-form-field quarter">
                    <label><?php _e('Status', 'booking-system-pro'); ?></label>
                    <select name="status">
                        <option value=""><?php _e('All Statuses', 'booking-system-pro'); ?></option>
                        <option value="pending" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'pending'); ?>><?php _e('Pending', 'booking-system-pro'); ?></option>
                        <option value="confirmed" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'confirmed'); ?>><?php _e('Confirmed', 'booking-system-pro'); ?></option>
                        <option value="completed" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'completed'); ?>><?php _e('Completed', 'booking-system-pro'); ?></option>
                        <option value="cancelled" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'cancelled'); ?>><?php _e('Cancelled', 'booking-system-pro'); ?></option>
                    </select>
                </div>
                <div class="bsp-form-field quarter">
                    <label><?php _e('Company', 'booking-system-pro'); ?></label>
                    <select name="company">
                        <option value=""><?php _e('All Companies', 'booking-system-pro'); ?></option>
                        <?php foreach ($companies as $company) : ?>
                            <option value="<?php echo esc_attr($company->id); ?>" 
                                    <?php selected(isset($_GET['company']) ? $_GET['company'] : '', $company->id); ?>>
                                <?php echo esc_html($company->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bsp-form-field quarter">
                    <label>&nbsp;</label>
                    <input type="submit" class="bsp-btn" value="<?php _e('Filter', 'booking-system-pro'); ?>">
                </div>
            </div>
        </form>
    </div>
    
    <!-- Bulk Actions -->
    <?php if ($bookings_query->have_posts()) : ?>
        <form method="post" action="">
            <?php wp_nonce_field('bulk-bookings'); ?>
            
            <div class="bsp-bulk-actions">
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e('Bulk Actions', 'booking-system-pro'); ?></option>
                    <option value="approve"><?php _e('Mark as Confirmed', 'booking-system-pro'); ?></option>
                    <option value="pending"><?php _e('Mark as Pending', 'booking-system-pro'); ?></option>
                    <option value="complete"><?php _e('Mark as Completed', 'booking-system-pro'); ?></option>
                    <option value="cancel"><?php _e('Cancel', 'booking-system-pro'); ?></option>
                    <option value="delete"><?php _e('Delete', 'booking-system-pro'); ?></option>
                </select>
                <input type="submit" class="bsp-btn bsp-bulk-action-btn" value="<?php _e('Apply', 'booking-system-pro'); ?>">
            </div>
            
            <!-- Bookings Table -->
            <div class="bsp-form-section">
                <table class="bsp-admin-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all-bookings"></th>
                            <th><?php _e('Booking', 'booking-system-pro'); ?></th>
                            <th><?php _e('Customer', 'booking-system-pro'); ?></th>
                            <th><?php _e('Service', 'booking-system-pro'); ?></th>
                            <th><?php _e('Company', 'booking-system-pro'); ?></th>
                            <th><?php _e('Date & Time', 'booking-system-pro'); ?></th>
                            <th><?php _e('Status', 'booking-system-pro'); ?></th>
                            <th><?php _e('Total', 'booking-system-pro'); ?></th>
                            <th><?php _e('Actions', 'booking-system-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($bookings_query->have_posts()) : $bookings_query->the_post(); ?>
                            <?php
                            $booking_id = get_the_ID();
                            $customer_name = get_post_meta($booking_id, '_customer_name', true);
                            $customer_email = get_post_meta($booking_id, '_customer_email', true);
                            $customer_phone = get_post_meta($booking_id, '_customer_phone', true);
                            $company_id = get_post_meta($booking_id, '_company_id', true);
                            $booking_date = get_post_meta($booking_id, '_booking_date', true);
                            $booking_time = get_post_meta($booking_id, '_booking_time', true);
                            $total_cost = get_post_meta($booking_id, '_total_cost', true);
                            
                            $service_terms = wp_get_post_terms($booking_id, 'bsp_service_type');
                            $status_terms = wp_get_post_terms($booking_id, 'bsp_booking_status');
                            
                            // Check for WP_Error objects
                            if (is_wp_error($service_terms)) {
                                $service_terms = [];
                            }
                            if (is_wp_error($status_terms)) {
                                $status_terms = [];
                            }
                            
                            $company = $company_id ? $db->get_company($company_id) : null;
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="bulk_ids[]" value="<?php echo esc_attr($booking_id); ?>" class="bsp-bulk-checkbox">
                                </td>
                                <td>
                                    <strong>#<?php echo esc_html($booking_id); ?></strong>
                                    <br><small><?php echo get_the_date('M j, Y g:i A'); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($customer_name); ?></strong>
                                    <br><small><?php echo esc_html($customer_email); ?></small>
                                    <?php if ($customer_phone) : ?>
                                        <br><small><?php echo esc_html($customer_phone); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($service_terms)) : ?>
                                        <?php echo esc_html($service_terms[0]->name); ?>
                                    <?php else : ?>
                                        <em><?php _e('No service selected', 'booking-system-pro'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($company) : ?>
                                        <strong><?php echo esc_html($company->name); ?></strong>
                                        <?php if ($company->phone) : ?>
                                            <br><small><?php echo esc_html($company->phone); ?></small>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <em><?php _e('Unknown', 'booking-system-pro'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($booking_date) : ?>
                                        <strong><?php echo date('M j, Y', strtotime($booking_date)); ?></strong>
                                        <?php if ($booking_time) : ?>
                                            <br><small><?php echo esc_html($booking_time); ?></small>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <em><?php _e('No date set', 'booking-system-pro'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($status_terms)) : ?>
                                        <span class="bsp-status-<?php echo esc_attr($status_terms[0]->slug); ?>">
                                            <?php echo esc_html($status_terms[0]->name); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="bsp-status-pending"><?php _e('Pending', 'booking-system-pro'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($total_cost) : ?>
                                        <strong>$<?php echo number_format(floatval($total_cost), 2); ?></strong>
                                    <?php else : ?>
                                        <em><?php _e('No cost', 'booking-system-pro'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('post.php?post=' . $booking_id . '&action=edit'); ?>" 
                                       class="bsp-btn bsp-btn-small"><?php _e('Edit', 'booking-system-pro'); ?></a>
                                    <a href="<?php echo add_query_arg(['action' => 'delete', 'id' => $booking_id]); ?>" 
                                       class="bsp-btn bsp-btn-small bsp-btn-danger bsp-delete-btn"><?php _e('Delete', 'booking-system-pro'); ?></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </form>
        
        <!-- Pagination -->
        <?php if ($bookings_query->max_num_pages > 1) : ?>
            <div class="bsp-actions">
                <?php
                $pagination_args = [
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo; Previous', 'booking-system-pro'),
                    'next_text' => __('Next &raquo;', 'booking-system-pro'),
                    'total' => $bookings_query->max_num_pages,
                    'current' => max(1, get_query_var('paged'))
                ];
                echo paginate_links($pagination_args);
                ?>
            </div>
        <?php endif; ?>
        
    <?php else : ?>
        <!-- No Bookings Found -->
        <div class="bsp-form-section">
            <p><?php _e('No bookings found.', 'booking-system-pro'); ?></p>
            <div class="bsp-actions">
                <a href="<?php echo admin_url('post-new.php?post_type=bsp_booking'); ?>" 
                   class="bsp-btn"><?php _e('Add New Booking', 'booking-system-pro'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=bsp-companies'); ?>" 
                   class="bsp-btn bsp-btn-secondary"><?php _e('Manage Companies', 'booking-system-pro'); ?></a>
            </div>
        </div>
    <?php endif; ?>
    
    <?php wp_reset_postdata(); ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Select all checkboxes
    $('#select-all-bookings').change(function() {
        $('.bsp-bulk-checkbox').prop('checked', this.checked);
    });
    
    // Update select all when individual checkboxes change
    $('.bsp-bulk-checkbox').change(function() {
        var allChecked = $('.bsp-bulk-checkbox:checked').length === $('.bsp-bulk-checkbox').length;
        $('#select-all-bookings').prop('checked', allChecked);
    });
});
</script>
                    <td>
                        <span class="status-badge status-<?php echo $booking->status; ?>">
                            <?php echo ucfirst($booking->status); ?>
                        </span>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($booking->created_at)); ?></td>
                    <td>
                        <button type="button" class="button button-small" onclick="viewBooking(<?php echo $booking->id; ?>)">View</button>
                        <button type="button" class="button button-small" onclick="editBooking(<?php echo $booking->id; ?>)">Edit</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="8">No bookings found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.status-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-confirmed {
    background: #d4edda;
    color: #155724;
}

.status-completed {
    background: #cce5ff;
    color: #004085;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}
</style>

<script>
function viewBooking(id) {
    // Implement booking view functionality
    alert('View booking #' + id);
}

function editBooking(id) {
    // Implement booking edit functionality
    alert('Edit booking #' + id);
}
</script>
