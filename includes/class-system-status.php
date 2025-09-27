<?php
/**
 * System Status and Monitoring Page
 * Shows the status of centralized components
 */

if (!defined('ABSPATH')) exit;

class BSP_System_Status {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_bsp_test_system', [$this, 'test_system_components']);
        add_action('wp_ajax_bsp_cleanup_system', [$this, 'cleanup_system_data']);
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=bookings',
            'System Status',
            'System Status',
            'manage_options',
            'bsp-system-status',
            [$this, 'render_status_page']
        );
    }
    
    public function render_status_page() {
        ?>
        <div class="wrap">
            <h1>BSP System Status</h1>
            
            <div class="card">
                <h2>Centralized Components Status</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>BSP_Logger</td>
                            <td><?php echo class_exists('BSP_Logger') ? '✅ Active' : '❌ Missing'; ?></td>
                            <td>Centralized logging system</td>
                        </tr>
                        <tr>
                            <td>BSP_Constants</td>
                            <td><?php echo class_exists('BSP_Constants') ? '✅ Active' : '❌ Missing'; ?></td>
                            <td>System constants and UTM parameters</td>
                        </tr>
                        <tr>
                            <td>BSP_Lead_Manager</td>
                            <td><?php echo class_exists('BSP_Lead_Manager') ? '✅ Active' : '❌ Missing'; ?></td>
                            <td>Unified lead management</td>
                        </tr>
                        <tr>
                            <td>Debug Log File</td>
                            <td><?php 
                                $log_file = BSP_PLUGIN_DIR . 'bsp-debug.log';
                                if (file_exists($log_file)) {
                                    $size = filesize($log_file);
                                    echo $size > 0 ? "📄 {$size} bytes" : "✅ Empty (Clean)";
                                } else {
                                    echo "❌ Missing";
                                }
                            ?></td>
                            <td>Current log file status</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <h2>Lead Management Statistics</h2>
                <?php if (class_exists('BSP_Lead_Manager')): ?>
                    <?php $stats = BSP_Lead_Manager::get_instance()->get_system_stats(); ?>
                    <table class="widefat">
                        <tr>
                            <td><strong>Total Leads:</strong></td>
                            <td><?php echo $stats['total_leads']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Recent Leads (24h):</strong></td>
                            <td><?php echo $stats['recent_leads']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Processing:</strong></td>
                            <td><?php echo $stats['processing_leads']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Complete:</strong></td>
                            <td><?php echo $stats['complete_leads']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Failed:</strong></td>
                            <td><?php echo $stats['failed_leads']; ?></td>
                        </tr>
                    </table>
                <?php else: ?>
                    <p>Lead Manager not available</p>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>Test System Components</h2>
                <button type="button" class="button button-primary" onclick="testSystem()">Test Centralized Logging</button>
                <div id="test-results" style="margin-top: 10px;"></div>
            </div>
            
            <div class="card">
                <h2>System Cleanup</h2>
                <button type="button" class="button" onclick="cleanupSystem()">Clean Expired Data</button>
                <div id="cleanup-results" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <script>
        function testSystem() {
            document.getElementById('test-results').innerHTML = 'Testing...';
            
            jQuery.post(ajaxurl, {
                action: 'bsp_test_system',
                nonce: '<?php echo wp_create_nonce('bsp_test_system'); ?>'
            }, function(response) {
                document.getElementById('test-results').innerHTML = response.data || 'Test completed';
            });
        }
        
        function cleanupSystem() {
            document.getElementById('cleanup-results').innerHTML = 'Cleaning...';
            
            jQuery.post(ajaxurl, {
                action: 'bsp_cleanup_system',
                nonce: '<?php echo wp_create_nonce('bsp_cleanup_system'); ?>'
            }, function(response) {
                document.getElementById('cleanup-results').innerHTML = response.data || 'Cleanup completed';
                location.reload(); // Refresh to show updated stats
            });
        }
        </script>
        <?php
    }
    
    public function test_system_components() {
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_test_system')) {
            wp_die('Security check failed');
        }
        
        $results = [];
        
        // Test centralized logging
        if (class_exists('BSP_Logger')) {
            bsp_log_info("System test initiated", ['timestamp' => current_time('mysql')]);
            bsp_log_debug("Debug level test");
            bsp_log_warn("Warning level test");
            $results[] = "✅ Centralized logging test completed";
        } else {
            $results[] = "❌ BSP_Logger not available";
        }
        
        // Test constants
        if (class_exists('BSP_Constants')) {
            $utm_params = BSP_Constants::get_utm_parameters();
            $results[] = "✅ Constants loaded: " . count($utm_params) . " UTM parameters";
        } else {
            $results[] = "❌ BSP_Constants not available";
        }
        
        // Test lead manager
        if (class_exists('BSP_Lead_Manager')) {
            $manager = BSP_Lead_Manager::get_instance();
            $session_id = $manager->get_or_create_session_id();
            $results[] = "✅ Lead Manager: Generated session " . substr($session_id, 0, 8) . "...";
        } else {
            $results[] = "❌ BSP_Lead_Manager not available";
        }
        
        wp_send_json_success(implode('<br>', $results));
    }
    
    public function cleanup_system_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_cleanup_system')) {
            wp_die('Security check failed');
        }
        
        $results = [];
        
        // Cleanup through Lead Manager
        if (class_exists('BSP_Lead_Manager')) {
            $manager = BSP_Lead_Manager::get_instance();
            $manager->cleanup_expired_data();
            $results[] = "✅ Expired lead data cleaned";
            $results[] = "✅ Old transients removed";
        } else {
            $results[] = "❌ BSP_Lead_Manager not available";
        }
        
        // Clear debug log if requested
        $log_file = BSP_PLUGIN_DIR . 'bsp-debug.log';
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            $results[] = "✅ Debug log cleared";
        }
        
        wp_send_json_success(implode('<br>', $results));
    }
}

// Initialize if in admin
if (is_admin()) {
    BSP_System_Status::get_instance();
}