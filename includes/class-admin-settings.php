<?php
/**
 * Admin Settings for Booking System Pro
 */

if (!defined('ABSPATH')) exit;

class BSP_Admin_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_bsp_send_test_email', [$this, 'handle_test_email']);
        add_action('wp_ajax_bsp_resend_booking_emails', [$this, 'handle_resend_booking_emails']);
        add_action('wp_ajax_bsp_fix_debug_log', [$this, 'handle_fix_debug_log']);
        add_action('wp_ajax_bsp_test_google_sheets', [$this, 'handle_test_google_sheets']);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General Settings
        register_setting('bsp_general_settings', 'bsp_general_settings', [$this, 'sanitize_general_settings']);
        
        add_settings_section(
            'bsp_general_section',
            __('General Settings', 'booking-system-pro'),
            [$this, 'general_section_callback'],
            'bsp_general_settings'
        );
        
        // Email Settings
        register_setting('bsp_email_settings', 'bsp_email_settings', [$this, 'sanitize_email_settings']);
        
        add_settings_section(
            'bsp_email_section',
            __('Email Settings', 'booking-system-pro'),
            [$this, 'email_section_callback'],
            'bsp_email_settings'
        );
        
        // Booking Settings
        register_setting('bsp_booking_settings', 'bsp_booking_settings', [$this, 'sanitize_booking_settings']);
        
        add_settings_section(
            'bsp_booking_section',
            __('Booking Settings', 'booking-system-pro'),
            [$this, 'booking_section_callback'],
            'bsp_booking_settings'
        );

        // Integration Settings
        register_setting('bsp_integration_settings', 'bsp_integration_settings', [$this, 'sanitize_integration_settings']);

        add_settings_section(
            'bsp_integration_section',
            __('Integration Settings', 'booking-system-pro'),
            [$this, 'integration_section_callback'],
            'bsp_integration_settings'
        );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $active_tab = $_GET['tab'] ?? 'general';
        ?>
        <div class="wrap">
            <h1><?php _e('Booking System Pro Settings', 'booking-system-pro'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=bsp-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'booking-system-pro'); ?>
                </a>
                <a href="?page=bsp-settings&tab=email" class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Email', 'booking-system-pro'); ?>
                </a>
                <a href="?page=bsp-settings&tab=booking" class="nav-tab <?php echo $active_tab === 'booking' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Booking', 'booking-system-pro'); ?>
                </a>
                <a href="?page=bsp-settings&tab=integrations" class="nav-tab <?php echo $active_tab === 'integrations' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Integrations', 'booking-system-pro'); ?>
                </a>
                <a href="?page=bsp-settings&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Advanced', 'booking-system-pro'); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'email':
                        $this->render_email_settings();
                        break;
                    case 'booking':
                        $this->render_booking_settings();
                        break;
                    case 'integrations':
                        $this->render_integration_settings();
                        break;
                    case 'advanced':
                        $this->render_advanced_settings();
                        break;
                    default:
                        $this->render_general_settings();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render general settings
     */
    private function render_general_settings() {
        $options = get_option('bsp_general_settings', []);
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('bsp_general_settings');
            do_settings_sections('bsp_general_settings');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Plugin Enabled', 'booking-system-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="bsp_general_settings[enabled]" value="1" <?php checked($options['enabled'] ?? true); ?>>
                            <?php _e('Enable booking system', 'booking-system-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Date Format', 'booking-system-pro'); ?></th>
                    <td>
                        <select name="bsp_general_settings[date_format]">
                            <option value="Y-m-d" <?php selected($options['date_format'] ?? 'Y-m-d', 'Y-m-d'); ?>>YYYY-MM-DD</option>
                            <option value="m/d/Y" <?php selected($options['date_format'] ?? 'Y-m-d', 'm/d/Y'); ?>>MM/DD/YYYY</option>
                            <option value="d/m/Y" <?php selected($options['date_format'] ?? 'Y-m-d', 'd/m/Y'); ?>>DD/MM/YYYY</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Time Format', 'booking-system-pro'); ?></th>
                    <td>
                        <select name="bsp_general_settings[time_format]">
                            <option value="H:i" <?php selected($options['time_format'] ?? 'H:i', 'H:i'); ?>>24 Hour (23:00)</option>
                            <option value="g:i A" <?php selected($options['time_format'] ?? 'H:i', 'g:i A'); ?>>12 Hour (11:00 PM)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Default Status', 'booking-system-pro'); ?></th>
                    <td>
                        <select name="bsp_general_settings[default_status]">
                            <?php foreach (BSP_Utilities::get_booking_statuses() as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($options['default_status'] ?? 'pending', $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Timezone', 'booking-system-pro'); ?></th>
                    <td>
                        <select name="bsp_general_settings[timezone]">
                            <?php echo wp_timezone_choice($options['timezone'] ?? get_option('timezone_string')); ?>
                        </select>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * Render email settings
     */
    private function render_email_settings() {
        $options = get_option('bsp_email_settings', []);
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('bsp_email_settings');
            do_settings_sections('bsp_email_settings');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Email Notifications', 'booking-system-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="bsp_email_settings[enabled]" value="1" <?php checked($options['enabled'] ?? true); ?>>
                            <?php _e('Send email notifications', 'booking-system-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('From Name', 'booking-system-pro'); ?></th>
                    <td>
                        <input type="text" name="bsp_email_settings[from_name]" class="regular-text" 
                               value="<?php echo esc_attr($options['from_name'] ?? get_bloginfo('name')); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('From Email', 'booking-system-pro'); ?></th>
                    <td>
                        <input type="email" name="bsp_email_settings[from_email]" class="regular-text" 
                               value="<?php echo esc_attr($options['from_email'] ?? get_option('admin_email')); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Admin Email', 'booking-system-pro'); ?></th>
                    <td>
                        <input type="email" name="bsp_email_settings[admin_email]" class="regular-text" 
                               value="<?php echo esc_attr($options['admin_email'] ?? get_option('admin_email')); ?>">
                        <p class="description"><?php _e('Primary email address to receive booking notifications', 'booking-system-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Additional Recipients', 'booking-system-pro'); ?></th>
                    <td>
                        <textarea name="bsp_email_settings[additional_recipients]" class="large-text" rows="3" 
                                  placeholder="email1@example.com, email2@example.com"><?php echo esc_textarea($options['additional_recipients'] ?? ''); ?></textarea>
                        <p class="description"><?php _e('Additional email addresses to receive booking notifications. Separate multiple emails with commas.', 'booking-system-pro'); ?></p>
                        <?php
                        // Show current recipients count
                        $admin_email = $options['admin_email'] ?? get_option('admin_email');
                        $additional = $options['additional_recipients'] ?? '';
                        $total_count = 1; // Admin email
                        if (!empty($additional)) {
                            $additional_emails = array_map('trim', explode(',', $additional));
                            $valid_additional = array_filter($additional_emails, 'is_email');
                            $total_count += count($valid_additional);
                        }
                        ?>
                        <p class="description" style="color: #0073aa; font-weight: bold;">
                            <?php printf(__('Total recipients: %d email(s)', 'booking-system-pro'), $total_count); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Customer Confirmation', 'booking-system-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="bsp_email_settings[customer_confirmation]" value="1" <?php checked($options['customer_confirmation'] ?? true); ?>>
                            <?php _e('Send confirmation email to customers', 'booking-system-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Admin Notifications', 'booking-system-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="bsp_email_settings[admin_notifications]" value="1" <?php checked($options['admin_notifications'] ?? true); ?>>
                            <?php _e('Send notifications to admin', 'booking-system-pro'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <h3><?php _e('Test Email System', 'booking-system-pro'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Test Multiple Recipients', 'booking-system-pro'); ?></th>
                    <td>
                        <button type="button" id="send_test_email" class="button"><?php _e('Send Test Email to All Recipients', 'booking-system-pro'); ?></button>
                        <div id="test_email_result"></div>
                        <p class="description"><?php _e('This will send a test email to all configured admin email recipients to verify the email system is working.', 'booking-system-pro'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            $('#send_test_email').on('click', function() {
                var button = $(this);
                var result = $('#test_email_result');
                
                result.html('');
                
                button.prop('disabled', true).text('<?php _e('Sending...', 'booking-system-pro'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bsp_send_test_email',
                        nonce: '<?php echo wp_create_nonce('bsp_test_email'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<p style="color: green;"><strong>✅ ' + response.data.message + '</strong><br><small>Recipients: ' + response.data.recipients.join(', ') + '</small></p>');
                        } else {
                            result.html('<p style="color: red;"><strong>❌ ' + response.data + '</strong></p>');
                        }
                    },
                    error: function() {
                        result.html('<p style="color: red;"><strong>❌ <?php _e('Error sending test email.', 'booking-system-pro'); ?></strong></p>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Send Test Email to All Recipients', 'booking-system-pro'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render booking settings
     */
    private function render_booking_settings() {
        $options = get_option('bsp_booking_settings', []);
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('bsp_booking_settings');
            do_settings_sections('bsp_booking_settings');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Advance Booking Days', 'booking-system-pro'); ?></th>
                    <td>
                        <input type="number" name="bsp_booking_settings[advance_days]" class="small-text" 
                               value="<?php echo esc_attr($options['advance_days'] ?? 30); ?>" min="1" max="365">
                        <p class="description"><?php _e('How many days in advance can customers book', 'booking-system-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Minimum Notice Hours', 'booking-system-pro'); ?></th>
                    <td>
                        <input type="number" name="bsp_booking_settings[minimum_notice]" class="small-text" 
                               value="<?php echo esc_attr($options['minimum_notice'] ?? 24); ?>" min="0">
                        <p class="description"><?php _e('Minimum hours notice required for bookings', 'booking-system-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Default Service Duration', 'booking-system-pro'); ?></th>
                    <td>
                        <input type="number" name="bsp_booking_settings[default_duration]" class="small-text" 
                               value="<?php echo esc_attr($options['default_duration'] ?? 60); ?>" min="15" step="15">
                        <span><?php _e('minutes', 'booking-system-pro'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Time Slot Interval', 'booking-system-pro'); ?></th>
                    <td>
                        <select name="bsp_booking_settings[time_interval]">
                            <option value="15" <?php selected($options['time_interval'] ?? 30, 15); ?>>15 <?php _e('minutes', 'booking-system-pro'); ?></option>
                            <option value="30" <?php selected($options['time_interval'] ?? 30, 30); ?>>30 <?php _e('minutes', 'booking-system-pro'); ?></option>
                            <option value="60" <?php selected($options['time_interval'] ?? 30, 60); ?>>60 <?php _e('minutes', 'booking-system-pro'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Auto Confirmation', 'booking-system-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="bsp_booking_settings[auto_confirm]" value="1" <?php checked($options['auto_confirm'] ?? false); ?>>
                            <?php _e('Automatically confirm bookings', 'booking-system-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Allow Cancellations', 'booking-system-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="bsp_booking_settings[allow_cancellation]" value="1" <?php checked($options['allow_cancellation'] ?? true); ?>>
                            <?php _e('Allow customers to cancel bookings', 'booking-system-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Cancellation Hours', 'booking-system-pro'); ?></th>
                    <td>
                        <input type="number" name="bsp_booking_settings[cancellation_hours]" class="small-text" 
                               value="<?php echo esc_attr($options['cancellation_hours'] ?? 24); ?>" min="0">
                        <p class="description"><?php _e('Hours before appointment when cancellation is allowed', 'booking-system-pro'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render Integration settings
     */
    private function render_integration_settings() {
        $options = get_option('bsp_integration_settings', []);
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('bsp_integration_settings');
            do_settings_sections('bsp_integration_settings');
            ?>
            
            <h3><?php _e('Google Sheets Integration', 'booking-system-pro'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Google Sheets Sync', 'booking-system-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="bsp_integration_settings[google_sheets_enabled]" value="1" <?php checked($options['google_sheets_enabled'] ?? false); ?>>
                            <?php _e('Send new bookings to Google Sheets', 'booking-system-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Google Sheets Webhook URL', 'booking-system-pro'); ?></th>
                    <td>
                        <input type="url" name="bsp_integration_settings[google_sheets_webhook_url]" class="large-text" 
                               value="<?php echo esc_attr($options['google_sheets_webhook_url'] ?? 'https://script.google.com/macros/s/AKfycbzmqDaGnI2yEfclR7PnoPOerY8GbmCGvR7hhBMuLvRLYQ3DCO2ur6j8PZ-MlOucGoxgxA/exec'); ?>">
                        <p class="description"><?php _e('Enter the Web App URL from your Google Apps Script.', 'booking-system-pro'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * Render advanced settings
     */
    private function render_advanced_settings() {
        ?>
        <div class="bsp-advanced-settings">
            <h3><?php _e('System Information', 'booking-system-pro'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Plugin Version', 'booking-system-pro'); ?></th>
                    <td><?php echo BSP_VERSION; ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Database Version', 'booking-system-pro'); ?></th>
                    <td><?php echo get_option('bsp_db_version', 'Not set'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('WordPress Version', 'booking-system-pro'); ?></th>
                    <td><?php echo get_bloginfo('version'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('PHP Version', 'booking-system-pro'); ?></th>
                    <td><?php echo PHP_VERSION; ?></td>
                </tr>
            </table>
            
            <h3><?php _e('Debug System Diagnostics', 'booking-system-pro'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Debug Log Status', 'booking-system-pro'); ?></th>
                    <td>
                        <?php
                        $debug_file = BSP_PLUGIN_DIR . 'debug.log';
                        if (file_exists($debug_file)) {
                            $file_size = filesize($debug_file);
                            $file_encoding = mb_detect_encoding(file_get_contents($debug_file, false, null, 0, 1000));
                            echo sprintf(__('File exists (%s bytes, encoding: %s)', 'booking-system-pro'), 
                                number_format($file_size), 
                                $file_encoding ?: 'Unknown'
                            );
                        } else {
                            echo __('File does not exist', 'booking-system-pro');
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Fix Debug Log', 'booking-system-pro'); ?></th>
                    <td>
                        <button type="button" id="fix_debug_log" class="button button-secondary">
                            <?php _e('Fix Debug Log Encoding', 'booking-system-pro'); ?>
                        </button>
                        <p class="description"><?php _e('This will fix character encoding issues in the debug log.', 'booking-system-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Google Sheets Integration', 'booking-system-pro'); ?></th>
                    <td>
                        <?php
                        $integration_settings = get_option('bsp_integration_settings', []);
                        $enabled = !empty($integration_settings['google_sheets_enabled']);
                        $webhook_url = $integration_settings['google_sheets_webhook_url'] ?? '';
                        
                        if ($enabled && !empty($webhook_url)) {
                            echo '<span style="color: green;">✓ ' . __('Enabled and configured', 'booking-system-pro') . '</span>';
                        } elseif ($enabled) {
                            echo '<span style="color: orange;">⚠ ' . __('Enabled but webhook URL missing', 'booking-system-pro') . '</span>';
                        } else {
                            echo '<span style="color: red;">✗ ' . __('Disabled', 'booking-system-pro') . '</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Test Google Sheets', 'booking-system-pro'); ?></th>
                    <td>
                        <button type="button" id="test_google_sheets" class="button button-secondary" 
                                <?php echo (!$enabled || empty($webhook_url)) ? 'disabled' : ''; ?>>
                            <?php _e('Send Test Data', 'booking-system-pro'); ?>
                        </button>
                        <p class="description"><?php _e('Send a test booking to Google Sheets to verify the integration.', 'booking-system-pro'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h3><?php _e('Database Management', 'booking-system-pro'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Reset Database', 'booking-system-pro'); ?></th>
                    <td>
                        <button type="button" id="reset_database" class="button button-secondary">
                            <?php _e('Reset All Data', 'booking-system-pro'); ?>
                        </button>
                        <p class="description"><?php _e('This will delete all bookings, companies, and services. This action cannot be undone.', 'booking-system-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Export Data', 'booking-system-pro'); ?></th>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=bsp-export'); ?>" class="button">
                            <?php _e('Export Bookings', 'booking-system-pro'); ?>
                        </a>
                    </td>
                </tr>
            </table>
            
            <h3><?php _e('Debug Information', 'booking-system-pro'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Debug Mode', 'booking-system-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="debug_mode" <?php checked(defined('WP_DEBUG') && WP_DEBUG); ?> disabled>
                            <?php _e('Debug mode is controlled by WP_DEBUG constant', 'booking-system-pro'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#fix_debug_log').on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('<?php _e('Fixing...', 'booking-system-pro'); ?>');
                
                $.post(ajaxurl, {
                    action: 'bsp_fix_debug_log',
                    nonce: '<?php echo wp_create_nonce('bsp_fix_debug_log'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Debug log fixed successfully!', 'booking-system-pro'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('Failed to fix debug log: ', 'booking-system-pro'); ?>' + response.data);
                    }
                }).always(function() {
                    $button.prop('disabled', false).text('<?php _e('Fix Debug Log Encoding', 'booking-system-pro'); ?>');
                });
            });
            
            $('#test_google_sheets').on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('<?php _e('Testing...', 'booking-system-pro'); ?>');
                
                $.post(ajaxurl, {
                    action: 'bsp_test_google_sheets',
                    nonce: '<?php echo wp_create_nonce('bsp_test_google_sheets'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Test successful! Check Google Sheets for the test data.', 'booking-system-pro'); ?>');
                    } else {
                        alert('<?php _e('Test failed: ', 'booking-system-pro'); ?>' + response.data);
                    }
                }).always(function() {
                    $button.prop('disabled', false).text('<?php _e('Send Test Data', 'booking-system-pro'); ?>');
                });
            });
            
            $('#reset_database').on('click', function() {
                if (confirm('<?php _e('Are you sure you want to reset all data? This action cannot be undone.', 'booking-system-pro'); ?>')) {
                    if (confirm('<?php _e('This will permanently delete all bookings, companies, and services. Are you absolutely sure?', 'booking-system-pro'); ?>')) {
                        // Implement reset functionality
                        alert('<?php _e('Database reset functionality would be implemented here.', 'booking-system-pro'); ?>');
                    }
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Sanitize general settings
     */
    public function sanitize_general_settings($input) {
        $sanitized = [];
        
        $sanitized['enabled'] = isset($input['enabled']) ? true : false;
        $sanitized['date_format'] = sanitize_text_field($input['date_format'] ?? 'Y-m-d');
        $sanitized['time_format'] = sanitize_text_field($input['time_format'] ?? 'H:i');
        $sanitized['default_status'] = sanitize_text_field($input['default_status'] ?? 'pending');
        $sanitized['timezone'] = sanitize_text_field($input['timezone'] ?? '');
        
        return $sanitized;
    }
    
    /**
     * Sanitize email settings
     */
    public function sanitize_email_settings($input) {
        $sanitized = [];
        
        $sanitized['enabled'] = isset($input['enabled']) ? true : false;
        $sanitized['from_name'] = sanitize_text_field($input['from_name'] ?? '');
        $sanitized['from_email'] = sanitize_email($input['from_email'] ?? '');
        $sanitized['admin_email'] = sanitize_email($input['admin_email'] ?? '');
        $sanitized['customer_confirmation'] = isset($input['customer_confirmation']) ? true : false;
        $sanitized['admin_notifications'] = isset($input['admin_notifications']) ? true : false;
        
        // Sanitize additional recipients
        $additional_recipients = sanitize_textarea_field($input['additional_recipients'] ?? '');
        if (!empty($additional_recipients)) {
            $emails = array_map('trim', explode(',', $additional_recipients));
            $valid_emails = [];
            foreach ($emails as $email) {
                $clean_email = sanitize_email($email);
                if (!empty($clean_email) && is_email($clean_email)) {
                    $valid_emails[] = $clean_email;
                }
            }
            $sanitized['additional_recipients'] = implode(', ', $valid_emails);
        } else {
            $sanitized['additional_recipients'] = '';
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize booking settings
     */
    public function sanitize_booking_settings($input) {
        $sanitized = [];
        
        $sanitized['advance_days'] = absint($input['advance_days'] ?? 30);
        $sanitized['minimum_notice'] = absint($input['minimum_notice'] ?? 24);
        $sanitized['default_duration'] = absint($input['default_duration'] ?? 60);
        $sanitized['time_interval'] = absint($input['time_interval'] ?? 30);
        $sanitized['auto_confirm'] = isset($input['auto_confirm']) ? true : false;
        $sanitized['allow_cancellation'] = isset($input['allow_cancellation']) ? true : false;
        $sanitized['cancellation_hours'] = absint($input['cancellation_hours'] ?? 24);
        
        return $sanitized;
    }
    
    /**
     * Sanitize integration settings
     */
    public function sanitize_integration_settings($input) {
        $sanitized = [];
        
        $sanitized['google_sheets_enabled'] = isset($input['google_sheets_enabled']) ? true : false;
        $sanitized['google_sheets_webhook_url'] = esc_url_raw($input['google_sheets_webhook_url'] ?? '');
        
        return $sanitized;
    }

    /**
     * Section callbacks
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure general plugin settings.', 'booking-system-pro') . '</p>';
    }
    
    public function email_section_callback() {
        echo '<p>' . __('Configure email notification settings.', 'booking-system-pro') . '</p>';
    }
    
    public function booking_section_callback() {
        echo '<p>' . __('Configure booking behavior and restrictions.', 'booking-system-pro') . '</p>';
    }

    public function integration_section_callback() {
        echo '<p>' . __('Configure integrations with third-party services.', 'booking-system-pro') . '</p>';
    }
    
    /**
     * Handle test email AJAX request
     */
    public function handle_test_email() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bsp_test_email')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Get recipients from settings instead of requiring a specific test email
        $recipients = $this->get_admin_recipients_for_test();
        if (empty($recipients)) {
            wp_send_json_error('No valid email recipients found. Please check your email settings.');
        }
        
        // Get current email settings
        $email_settings = get_option('bsp_email_settings', []);
        
        // Use first recipient as customer email for test purposes
        $test_customer_email = $recipients[0];
        
        // Prepare test booking data
        $test_booking = [
            'id' => '999',
            'customer_name' => 'Test Customer',
            'customer_email' => $test_customer_email,
            'customer_phone' => '(555) 123-4567',
            'service' => 'Test Service',
            'company' => 'Test Company',
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'address' => '123 Test Street, Test City, TS 12345',
            'zip_code' => '12345',
            'appointments' => []
        ];
        
        // Test admin notification to all recipients
        $email_class = BSP_Email::get_instance();
        
        $subject = 'Test Email - BookingPro Admin Notification';
        $message = $this->get_test_email_template($test_booking, $recipients);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        $success_count = 0;
        $total_count = count($recipients);
        
        foreach ($recipients as $email) {
            if (wp_mail(trim($email), $subject, $message, $headers)) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            wp_send_json_success([
                'message' => sprintf('Test email sent to %d of %d recipients', $success_count, $total_count),
                'recipients' => $recipients
            ]);
        } else {
            wp_send_json_error('Failed to send test email to any recipients');
        }
    }
    
    /**
     * Get admin recipients for testing
     */
    private function get_admin_recipients_for_test() {
        $email_settings = get_option('bsp_email_settings', []);
        $recipients = [];
        
        // Add primary admin email
        $admin_email = $email_settings['admin_email'] ?? get_option('admin_email');
        if (!empty($admin_email) && is_email($admin_email)) {
            $recipients[] = $admin_email;
        }
        
        // Add additional recipients
        $additional = $email_settings['additional_recipients'] ?? '';
        if (!empty($additional)) {
            $additional_emails = array_map('trim', explode(',', $additional));
            foreach ($additional_emails as $email) {
                if (!empty($email) && is_email($email)) {
                    $recipients[] = $email;
                }
            }
        }
        
        return array_unique($recipients);
    }
    
    /**
     * Get test email template
     */
    private function get_test_email_template($booking, $recipients) {
        $site_name = get_bloginfo('name');
        $recipients_list = implode('<br>', $recipients);
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                <h2 style='color: #4CAF50; margin-bottom: 20px; text-align: center;'>🧪 Test Email - BookingPro</h2>
                
                <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #1976d2;'>✅ Email System Test</h3>
                    <p>This is a test email to verify that your BookingPro email system is working correctly.</p>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #495057;'>📧 Recipients Configuration</h3>
                    <p><strong>Total Recipients:</strong> " . count($recipients) . "</p>
                    <p><strong>Email Addresses:</strong><br>{$recipients_list}</p>
                </div>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='margin-top: 0; color: #856404;'>📋 Sample Booking Data</h4>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 5px 0; border-bottom: 1px solid #ddd;'><strong>Booking ID:</strong></td><td style='padding: 5px 0; border-bottom: 1px solid #ddd;'>#{$booking['id']}</td></tr>
                        <tr><td style='padding: 5px 0; border-bottom: 1px solid #ddd;'><strong>Customer:</strong></td><td style='padding: 5px 0; border-bottom: 1px solid #ddd;'>{$booking['customer_name']}</td></tr>
                        <tr><td style='padding: 5px 0; border-bottom: 1px solid #ddd;'><strong>Service:</strong></td><td style='padding: 5px 0; border-bottom: 1px solid #ddd;'>{$booking['service']}</td></tr>
                        <tr><td style='padding: 5px 0;'><strong>Test Date:</strong></td><td style='padding: 5px 0;'>" . date('F j, Y \a\t g:i A') . "</td></tr>
                    </table>
                </div>
                
                <div style='text-align: center; margin: 30px 0; padding: 20px; background: #e8f5e8; border-radius: 8px;'>
                    <h4 style='margin-top: 0; color: #2e7d32;'>🎉 Email System Status: WORKING</h4>
                    <p style='margin-bottom: 0;'>Your BookingPro email notifications are configured correctly and ready to send real booking alerts!</p>
                </div>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                
                <p style='text-align: center; color: #666; font-size: 14px;'>
                    This test email was sent from <strong>{$site_name}</strong><br>
                    BookingPro Email System Test
                </p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Handle resend booking emails AJAX request
     */
    public function handle_resend_booking_emails() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bsp_resend_emails')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $booking_id = intval($_POST['booking_id'] ?? 0);
        if (empty($booking_id)) {
            wp_send_json_error('Invalid booking ID');
        }
        
        // Get booking data
        $post = get_post($booking_id);
        if (!$post || $post->post_type !== 'bsp_booking') {
            wp_send_json_error('Booking not found');
        }
        
        // Get email class and resend emails
        $email_class = BSP_Email::get_instance();
        $success = $email_class->send_booking_update_emails($booking_id);
        
        if ($success) {
            wp_send_json_success([
                'message' => 'Booking emails resent successfully'
            ]);
        } else {
            wp_send_json_error('Failed to resend booking emails');
        }
    }
    
    /**
     * Handle fix debug log AJAX request
     */
    public function handle_fix_debug_log() {
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_fix_debug_log')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get the main plugin instance to call the fix function
        $plugin = BSP();
        if (method_exists($plugin, 'fix_debug_log_encoding')) {
            $result = $plugin->fix_debug_log_encoding();
            if ($result) {
                wp_send_json_success('Debug log encoding fixed successfully');
            } else {
                wp_send_json_error('Failed to fix debug log encoding');
            }
        } else {
            wp_send_json_error('Fix function not available');
        }
    }
    
    /**
     * Handle test Google Sheets AJAX request
     */
    public function handle_test_google_sheets() {
        if (!wp_verify_nonce($_POST['nonce'], 'bsp_test_google_sheets')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Check if Google Sheets integration is configured
        $integration_settings = get_option('bsp_integration_settings', []);
        if (empty($integration_settings['google_sheets_enabled']) || empty($integration_settings['google_sheets_webhook_url'])) {
            wp_send_json_error('Google Sheets integration not properly configured');
        }
        
        // Create test data
        $test_data = [
            'id' => 999999,
            'booking_id' => 999999,
            'status' => 'test',
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'customer_phone' => '555-123-4567',
            'customer_address' => '123 Test Street',
            'zip_code' => '12345',
            'city' => 'Test City',
            'state' => 'Test State',
            'service_type' => 'Test Service',
            'service_name' => 'Test Service',
            'specifications' => 'Test specifications',
            'company_name' => 'Test Company',
            'formatted_date' => date('Y-m-d'),
            'formatted_time' => date('H:i'),
            'booking_date' => date('Y-m-d'),
            'booking_time' => date('H:i'),
            'formatted_created' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'notes' => 'This is a test booking from the admin panel',
            'utm_source' => 'admin_test',
            'utm_medium' => 'manual',
            'utm_campaign' => 'debug_test',
            'appointments' => '{"test": "data"}'
        ];
        
        $webhook_url = $integration_settings['google_sheets_webhook_url'];
        
        // Send test data (non-blocking)
        $response = wp_remote_post($webhook_url, [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
                'User-Agent' => 'BookingPro/2.1.0-TEST'
            ],
            'body' => wp_json_encode($test_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout' => 30,
            'blocking' => false,
            'sslverify' => true
        ]);
        
        wp_send_json_success([
            'message' => 'Test data sent to Google Sheets. Check Google Sheets for delivery confirmation.',
            'timestamp' => current_time('mysql')
        ]);
    }
}
