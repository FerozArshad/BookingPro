<?php
/**
 * Admin Dashboard Template
 */

if (!defined('ABSPATH')) exit;

// Get dashboard data
$db = BSP_Database_Unified::get_instance();
$stats = $db->get_dashboard_stats();
$recent_bookings = $db->get_recent_bookings(5);
?>

<div class="wrap bsp-admin-wrapper">
    <div class="bsp-admin-header">
        <h1><?php _e('Booking System Pro Dashboard', 'booking-system-pro'); ?></h1>
        <p><?php _e('Welcome to your booking management dashboard. Here you can see an overview of your booking system.', 'booking-system-pro'); ?></p>
    </div>
    
    <!-- Dashboard Widgets -->
    <div class="bsp-dashboard-widgets">
        <div class="bsp-dashboard-widget">
            <h3><?php _e('Total Bookings', 'booking-system-pro'); ?></h3>
            <div class="bsp-stat-number"><?php echo number_format($stats['total_bookings']); ?></div>
            <div class="bsp-stat-label"><?php _e('All time bookings', 'booking-system-pro'); ?></div>
        </div>
        
        <div class="bsp-dashboard-widget">
            <h3><?php _e('This Month', 'booking-system-pro'); ?></h3>
            <div class="bsp-stat-number"><?php echo number_format($stats['this_month_bookings']); ?></div>
            <div class="bsp-stat-label"><?php _e('Bookings this month', 'booking-system-pro'); ?></div>
        </div>
        
        <div class="bsp-dashboard-widget">
            <h3><?php _e('Pending Bookings', 'booking-system-pro'); ?></h3>
            <div class="bsp-stat-number"><?php echo number_format($stats['pending_bookings']); ?></div>
            <div class="bsp-stat-label"><?php _e('Awaiting confirmation', 'booking-system-pro'); ?></div>
        </div>
        
        <div class="bsp-dashboard-widget">
            <h3><?php _e('Total Revenue', 'booking-system-pro'); ?></h3>
            <div class="bsp-stat-number">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
            <div class="bsp-stat-label"><?php _e('All time revenue', 'booking-system-pro'); ?></div>
        </div>
        
        <div class="bsp-dashboard-widget">
            <h3><?php _e('Active Companies', 'booking-system-pro'); ?></h3>
            <div class="bsp-stat-number"><?php echo number_format($stats['active_companies']); ?></div>
            <div class="bsp-stat-label"><?php _e('Service providers', 'booking-system-pro'); ?></div>
        </div>
        
        <div class="bsp-dashboard-widget">
            <h3><?php _e('Service Types', 'booking-system-pro'); ?></h3>
            <div class="bsp-stat-number"><?php echo number_format($stats['service_types']); ?></div>
            <div class="bsp-stat-label"><?php _e('Available services', 'booking-system-pro'); ?></div>
        </div>
    </div>
    
    <!-- Recent Bookings -->
    <div class="bsp-form-section">
        <h3><?php _e('Recent Bookings', 'booking-system-pro'); ?></h3>
        
        <?php if (!empty($recent_bookings)) : ?>
            <table class="bsp-admin-table">
                <thead>
                    <tr>
                        <th><?php _e('Customer', 'booking-system-pro'); ?></th>
                        <th><?php _e('Service', 'booking-system-pro'); ?></th>
                        <th><?php _e('Company', 'booking-system-pro'); ?></th>
                        <th><?php _e('Date', 'booking-system-pro'); ?></th>
                        <th><?php _e('Status', 'booking-system-pro'); ?></th>
                        <th><?php _e('Actions', 'booking-system-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_bookings as $booking) : ?>
                        <?php
                        $customer_name = get_post_meta($booking->ID, '_customer_name', true);
                        $customer_email = get_post_meta($booking->ID, '_customer_email', true);
                        $company_id = get_post_meta($booking->ID, '_company_id', true);
                        $booking_date = get_post_meta($booking->ID, '_booking_date', true);
                        $booking_time = get_post_meta($booking->ID, '_booking_time', true);
                        
                        $service_terms = wp_get_post_terms($booking->ID, 'bsp_service_type');
                        $status_terms = wp_get_post_terms($booking->ID, 'bsp_booking_status');
                        
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
                                <strong><?php echo esc_html($customer_name); ?></strong>
                                <br><small><?php echo esc_html($customer_email); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($service_terms)) : ?>
                                    <?php echo esc_html($service_terms[0]->name); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $company ? esc_html($company->name) : __('Unknown', 'booking-system-pro'); ?>
                            </td>
                            <td>
                                <?php if ($booking_date) : ?>
                                    <?php echo date('M j, Y', strtotime($booking_date)); ?>
                                    <?php if ($booking_time) : ?>
                                        <br><small><?php echo esc_html($booking_time); ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($status_terms)) : ?>
                                    <span class="bsp-status-<?php echo esc_attr($status_terms[0]->slug); ?>">
                                        <?php echo esc_html($status_terms[0]->name); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('post.php?post=' . $booking->ID . '&action=edit'); ?>" 
                                   class="bsp-btn bsp-btn-small"><?php _e('Edit', 'booking-system-pro'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="bsp-actions">
                <a href="<?php echo admin_url('admin.php?page=bsp-bookings'); ?>" 
                   class="bsp-btn"><?php _e('View All Bookings', 'booking-system-pro'); ?></a>
            </div>
        <?php else : ?>
            <p><?php _e('No bookings found.', 'booking-system-pro'); ?></p>
            <div class="bsp-actions">
                <a href="<?php echo admin_url('admin.php?page=bsp-companies'); ?>" 
                   class="bsp-btn"><?php _e('Add Companies', 'booking-system-pro'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=bsp-services'); ?>" 
                   class="bsp-btn bsp-btn-secondary"><?php _e('Manage Services', 'booking-system-pro'); ?></a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="bsp-form-section">
        <h3><?php _e('Quick Actions', 'booking-system-pro'); ?></h3>
        <div class="bsp-actions">
            <a href="<?php echo admin_url('post-new.php?post_type=bsp_booking'); ?>" 
               class="bsp-btn"><?php _e('Add New Booking', 'booking-system-pro'); ?></a>
            <a href="<?php echo admin_url('admin.php?page=bsp-companies'); ?>" 
               class="bsp-btn bsp-btn-secondary"><?php _e('Manage Companies', 'booking-system-pro'); ?></a>
            <a href="<?php echo admin_url('admin.php?page=bsp-reports'); ?>" 
               class="bsp-btn bsp-btn-secondary"><?php _e('View Reports', 'booking-system-pro'); ?></a>
            <a href="<?php echo admin_url('admin.php?page=bsp-settings'); ?>" 
               class="bsp-btn bsp-btn-secondary"><?php _e('Settings', 'booking-system-pro'); ?></a>
        </div>
    </div>
    
    <!-- Chart Section -->
    <div class="bsp-form-section">
        <h3><?php _e('Booking Trends', 'booking-system-pro'); ?></h3>
        <div class="bsp-chart-container">
            <canvas id="bsp-booking-chart" class="bsp-chart"></canvas>
        </div>
    </div>
</div>

<script>
// Chart data for dashboard
window.BSP_Admin = window.BSP_Admin || {};
BSP_Admin.chart_data = {
    labels: <?php echo json_encode($stats['chart_labels']); ?>,
    bookings: <?php echo json_encode($stats['chart_bookings']); ?>,
    revenue: <?php echo json_encode($stats['chart_revenue']); ?>
};
</script>
