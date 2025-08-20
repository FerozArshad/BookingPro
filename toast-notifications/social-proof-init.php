<?php
/**
 * Social Proof Toast Notifications for BookingPro
 * 
 * This file provides a self-contained social proof notification system
 * that integrates with the BookingPro plugin without modifying any core files.
 * 
 * Usage: Include this file in your theme's functions.php or as a mu-plugin
 * 
 * @package BookingPro
 * @subpackage SocialProof
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include the integration class
require_once __DIR__ . '/social-proof-integration.php';

/**
 * Initialize Social Proof - Always Active (Independent Mode)
 */
function bsp_init_social_proof_notifications() {
    // ALWAYS initialize the social proof system (independent of BookingPro)
    // This ensures toast notifications work on all pages regardless of main plugin status
    BSP_Social_Proof_Integration::get_instance();
}

// Hook into WordPress initialization
add_action('plugins_loaded', 'bsp_init_social_proof_notifications', 25);

/**
 * Enqueue social proof assets on frontend
 * This is a backup method in case the main integration doesn't work
 */
function bsp_enqueue_social_proof_backup() {
    // Only run if main integration hasn't loaded
    if (class_exists('BSP_Social_Proof_Integration')) {
        return;
    }
    
    // Define paths
    $css_url = plugins_url('toast-notifications/assets/css/social-proof.css', __DIR__);
    $js_url = plugins_url('toast-notifications/assets/js/social-proof.js', __DIR__);
    
    // Check if files exist
    $css_path = __DIR__ . '/assets/css/social-proof.css';
    $js_path = __DIR__ . '/assets/js/social-proof.js';
    
    if (file_exists($css_path) && file_exists($js_path)) {
        wp_enqueue_style('bsp-social-proof-backup', $css_url, [], '1.0.0');
        wp_enqueue_script('bsp-social-proof-backup', $js_url, ['jquery'], '1.0.0', true);
        
        wp_localize_script('bsp-social-proof-backup', 'BSP_SocialProof', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bsp_social_proof_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ]);
    }
}

// Backup hook with lower priority
add_action('wp_enqueue_scripts', 'bsp_enqueue_social_proof_backup', 999);

/**
 * Add social proof settings to BookingPro admin (if admin class exists)
 */
function bsp_add_social_proof_admin_menu() {
    if (!class_exists('BSP_Admin')) {
        return;
    }
    
    add_submenu_page(
        'booking-system-pro',
        'Social Proof Notifications',
        'Social Proof',
        'manage_options',
        'bsp-social-proof',
        'bsp_render_social_proof_admin_page'
    );
}

function bsp_render_social_proof_admin_page() {
    ?>
    <div class="wrap">
        <h1>Social Proof Notifications</h1>
        <div class="notice notice-info">
            <p><strong>Social Proof System Active!</strong></p>
            <p>The social proof notification system is automatically showing real-time notifications on your website.</p>
        </div>
        
        <h2>Features</h2>
        <ul>
            <li>✅ Real-time booking notifications</li>
            <li>✅ Random customer names from recent bookings</li>
            <li>✅ Service-specific messages</li>
            <li>✅ Location-based notifications (city, state)</li>
            <li>✅ Mobile-responsive design</li>
            <li>✅ Accessibility compliant</li>
        </ul>
        
        <h2>Technical Details</h2>
        <table class="form-table">
            <tr>
                <th>Status</th>
                <td>
                    <?php if (file_exists(__DIR__ . '/assets/js/social-proof.js')): ?>
                        <span style="color: green;">✅ Active</span>
                    <?php else: ?>
                        <span style="color: red;">❌ Files Missing</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Integration Method</th>
                <td>Non-invasive hook integration</td>
            </tr>
            <tr>
                <th>Display Frequency</th>
                <td>Every 3-15 seconds (random)</td>
            </tr>
            <tr>
                <th>Toast Duration</th>
                <td>5 seconds (pauses on hover)</td>
            </tr>
            <tr>
                <th>Position</th>
                <td>Bottom-left corner</td>
            </tr>
        </table>
        
        <h2>Sample Notifications</h2>
        <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #79B62F;">
            <p><strong>Examples of what visitors will see:</strong></p>
            <ul>
                <li>"Michael just scheduled a Roof free estimate in Beverly Hills, CA"</li>
                <li>"Sarah scheduled a Kitchen replacement 23 minutes ago in Austin, TX"</li>
                <li>"1,047+ homeowners booked their free estimate in the last 24 hours"</li>
            </ul>
        </div>
        
        <h2>Customization</h2>
        <p>To customize the social proof notifications, edit the configuration in:</p>
        <code><?php echo __DIR__ . '/assets/js/social-proof.js'; ?></code>
    </div>
    <?php
}

// Hook admin menu
add_action('admin_menu', 'bsp_add_social_proof_admin_menu', 99);

/**
 * Debug function to check if everything is working
 */
function bsp_social_proof_debug_info() {
    if (!current_user_can('manage_options') || !isset($_GET['bsp_debug'])) {
        return;
    }
    
    echo '<div style="background: white; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
    echo '<h3>BookingPro Social Proof Debug Info</h3>';
    echo '<p><strong>Plugin Status:</strong> ' . (class_exists('Booking_System_Pro_Final') ? 'Active' : 'Inactive') . '</p>';
    echo '<p><strong>CSS File:</strong> ' . (file_exists(__DIR__ . '/assets/css/social-proof.css') ? 'Found' : 'Missing') . '</p>';
    echo '<p><strong>JS File:</strong> ' . (file_exists(__DIR__ . '/assets/js/social-proof.js') ? 'Found' : 'Missing') . '</p>';
    echo '<p><strong>Integration Class:</strong> ' . (class_exists('BSP_Social_Proof_Integration') ? 'Loaded' : 'Not Loaded') . '</p>';
    echo '</div>';
}

add_action('wp_footer', 'bsp_social_proof_debug_info');
add_action('admin_footer', 'bsp_social_proof_debug_info');
