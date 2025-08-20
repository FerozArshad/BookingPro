<?php
/**
 * Admin Dashboard for Booking System Pro
 */

if (!defined('ABSPATH')) exit;

class BSP_Admin_Dashboard {
    
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
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $stats = $this->get_dashboard_stats();
        $recent_bookings = $this->get_recent_bookings();
        $upcoming_bookings = $this->get_upcoming_bookings();
        ?>
        <div class="wrap">
            <h1><?php _e('Booking System Pro Dashboard', 'booking-system-pro'); ?></h1>
            
            <!-- Dashboard Stats -->
            <div class="bsp-dashboard-stats">
                <div class="bsp-stat-card">
                    <h3><?php _e('Total Bookings', 'booking-system-pro'); ?></h3>
                    <span class="bsp-stat-number"><?php echo esc_html($stats['total_bookings']); ?></span>
                </div>
                <div class="bsp-stat-card">
                    <h3><?php _e('This Month', 'booking-system-pro'); ?></h3>
                    <span class="bsp-stat-number"><?php echo esc_html($stats['this_month']); ?></span>
                </div>
                <div class="bsp-stat-card">
                    <h3><?php _e('Pending', 'booking-system-pro'); ?></h3>
                    <span class="bsp-stat-number pending"><?php echo esc_html($stats['pending']); ?></span>
                </div>
                <div class="bsp-stat-card">
                    <h3><?php _e('Confirmed', 'booking-system-pro'); ?></h3>
                    <span class="bsp-stat-number confirmed"><?php echo esc_html($stats['confirmed']); ?></span>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="bsp-quick-actions">
                <h2><?php _e('Quick Actions', 'booking-system-pro'); ?></h2>
                <div class="bsp-actions-grid">
                    <a href="<?php echo admin_url('post-new.php?post_type=bsp_booking'); ?>" class="button button-primary">
                        <?php _e('Add New Booking', 'booking-system-pro'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=bsp-bookings'); ?>" class="button">
                        <?php _e('View All Bookings', 'booking-system-pro'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=bsp-settings'); ?>" class="button">
                        <?php _e('Settings', 'booking-system-pro'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Dashboard Content Grid -->
            <div class="bsp-dashboard-grid">
                <!-- Recent Bookings -->
                <div class="bsp-dashboard-widget">
                    <h2><?php _e('Recent Bookings', 'booking-system-pro'); ?></h2>
                    <?php if (empty($recent_bookings)): ?>
                        <p><?php _e('No recent bookings found.', 'booking-system-pro'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Customer', 'booking-system-pro'); ?></th>
                                    <th><?php _e('Service', 'booking-system-pro'); ?></th>
                                    <th><?php _e('Date', 'booking-system-pro'); ?></th>
                                    <th><?php _e('Status', 'booking-system-pro'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_bookings as $booking): ?>
                                    <tr>
                                        <td><?php echo esc_html($booking['customer_name']); ?></td>
                                        <td><?php echo esc_html($booking['service_name']); ?></td>
                                        <td><?php echo esc_html(BSP_Utilities::format_date($booking['appointment_date'])); ?></td>
                                        <td>
                                            <span class="bsp-status bsp-status-<?php echo esc_attr($booking['status']); ?>">
                                                <?php echo esc_html(ucfirst($booking['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=bsp-bookings'); ?>">
                                <?php _e('View All Bookings', 'booking-system-pro'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Upcoming Bookings -->
                <div class="bsp-dashboard-widget">
                    <h2><?php _e('Upcoming Bookings', 'booking-system-pro'); ?></h2>
                    <?php if (empty($upcoming_bookings)): ?>
                        <p><?php _e('No upcoming bookings found.', 'booking-system-pro'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Customer', 'booking-system-pro'); ?></th>
                                    <th><?php _e('Service', 'booking-system-pro'); ?></th>
                                    <th><?php _e('Date & Time', 'booking-system-pro'); ?></th>
                                    <th><?php _e('Status', 'booking-system-pro'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_bookings as $booking): ?>
                                    <tr>
                                        <td><?php echo esc_html($booking['customer_name']); ?></td>
                                        <td><?php echo esc_html($booking['service_name']); ?></td>
                                        <td>
                                            <?php echo esc_html(BSP_Utilities::format_date($booking['appointment_date'])); ?>
                                            <?php echo esc_html(BSP_Utilities::format_time($booking['appointment_time'])); ?>
                                        </td>
                                        <td>
                                            <span class="bsp-status bsp-status-<?php echo esc_attr($booking['status']); ?>">
                                                <?php echo esc_html(ucfirst($booking['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Calendar Widget -->
            <div class="bsp-dashboard-widget full-width">
                <h2><?php _e('Booking Calendar', 'booking-system-pro'); ?></h2>
                <div id="bsp-dashboard-calendar"></div>
            </div>
        </div>
        
        <style>
        .bsp-dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .bsp-stat-card {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
        }
        
        .bsp-stat-card h3 {
            margin: 0 0 10px;
            font-size: 14px;
            color: #666;
        }
        
        .bsp-stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2271b1;
        }
        
        .bsp-stat-number.pending {
            color: #dba617;
        }
        
        .bsp-stat-number.confirmed {
            color: #00a32a;
        }
        
        .bsp-quick-actions {
            margin: 20px 0;
        }
        
        .bsp-actions-grid {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .bsp-dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .bsp-dashboard-widget {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .bsp-dashboard-widget.full-width {
            grid-column: 1 / -1;
        }
        
        .bsp-dashboard-widget h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
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
        
        @media (max-width: 768px) {
            .bsp-dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .bsp-actions-grid {
                flex-direction: column;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize dashboard calendar if needed
            if ($('#bsp-dashboard-calendar').length) {
                // Calendar initialization code would go here
            }
        });
        </script>
        <?php
    }
    
    /**
     * Get dashboard statistics
     */
    private function get_dashboard_stats() {
        $today = date('Y-m-d');
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');
        
        return [
            'total_bookings' => $this->db->count_bookings(),
            'this_month' => $this->db->count_bookings([
                'date_from' => $month_start,
                'date_to' => $month_end
            ]),
            'pending' => $this->db->count_bookings(['status' => 'pending']),
            'confirmed' => $this->db->count_bookings(['status' => 'confirmed']),
            'today' => $this->db->count_bookings(['appointment_date' => $today])
        ];
    }
    
    /**
     * Get recent bookings
     */
    private function get_recent_bookings($limit = 5) {
        return $this->db->get_bookings([
            'limit' => $limit,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ]);
    }
    
    /**
     * Get upcoming bookings
     */
    private function get_upcoming_bookings($limit = 5) {
        return $this->db->get_bookings([
            'date_from' => date('Y-m-d'),
            'limit' => $limit,
            'orderby' => 'appointment_date',
            'order' => 'ASC',
            'status' => ['confirmed', 'pending']
        ]);
    }
}
