<?php
/**
 * Admin Settings Template
 */

if (!defined('ABSPATH')) exit;

// Handle form submissions
$message = '';
$error = '';

if (isset($_POST['submit_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'bsp_save_settings')) {
    // General Settings
    if (isset($_POST['general_settings'])) {
        update_option('bsp_general_settings', $_POST['general_settings']);
    }
    
    // Email Settings
    if (isset($_POST['email_settings'])) {
        update_option('bsp_email_settings', $_POST['email_settings']);
    }
    
    // Appearance Settings
    if (isset($_POST['appearance_settings'])) {
        update_option('bsp_appearance_settings', $_POST['appearance_settings']);
    }
    
    $message = __('Settings saved successfully.', 'booking-system-pro');
}

// Get current settings
$general_settings = get_option('bsp_general_settings', []);
$email_settings = get_option('bsp_email_settings', []);
$appearance_settings = get_option('bsp_appearance_settings', []);

// Default values
$general_defaults = [
    'currency' => 'USD',
    'currency_symbol' => '$',
    'time_format' => '12',
    'date_format' => 'Y-m-d',
    'default_duration' => '60',
    'advance_booking_days' => '30',
    'max_bookings_per_day' => '10'
];

$email_defaults = [
    'admin_email' => get_option('admin_email'),
    'from_name' => get_bloginfo('name'),
    'from_email' => get_option('admin_email'),
    'send_customer_confirmation' => '1',
    'send_admin_notification' => '1',
    'send_reminder_emails' => '1',
    'reminder_hours' => '24'
];

$appearance_defaults = [
    'primary_color' => '#0073aa',
    'secondary_color' => '#005177',
    'accent_color' => '#00a0d2',
    'button_style' => 'rounded',
    'form_layout' => 'stacked'
];

$general_settings = array_merge($general_defaults, $general_settings);
$email_settings = array_merge($email_defaults, $email_settings);
$appearance_settings = array_merge($appearance_defaults, $appearance_settings);
?>

<div class="wrap bsp-admin-wrapper">
    <div class="bsp-admin-header">
        <h1><?php _e('Settings', 'booking-system-pro'); ?></h1>
        <p><?php _e('Configure your booking system preferences and global settings.', 'booking-system-pro'); ?></p>
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
    
    <form method="post" action="">
        <?php wp_nonce_field('bsp_save_settings'); ?>
        
        <!-- Settings Tabs -->
        <div class="bsp-settings-tabs">
            <a href="#general" class="active"><?php _e('General', 'booking-system-pro'); ?></a>
            <a href="#email"><?php _e('Email', 'booking-system-pro'); ?></a>
            <a href="#appearance"><?php _e('Appearance', 'booking-system-pro'); ?></a>
            <a href="#advanced"><?php _e('Advanced', 'booking-system-pro'); ?></a>
        </div>
        
        <!-- General Settings -->
        <div id="general" class="bsp-settings-tab-content active">
            <h3><?php _e('General Settings', 'booking-system-pro'); ?></h3>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field half">
                    <label for="currency"><?php _e('Currency', 'booking-system-pro'); ?></label>
                    <select id="currency" name="general_settings[currency]">
                        <option value="USD" <?php selected($general_settings['currency'], 'USD'); ?>>USD - US Dollar</option>
                        <option value="EUR" <?php selected($general_settings['currency'], 'EUR'); ?>>EUR - Euro</option>
                        <option value="GBP" <?php selected($general_settings['currency'], 'GBP'); ?>>GBP - British Pound</option>
                        <option value="CAD" <?php selected($general_settings['currency'], 'CAD'); ?>>CAD - Canadian Dollar</option>
                        <option value="AUD" <?php selected($general_settings['currency'], 'AUD'); ?>>AUD - Australian Dollar</option>
                    </select>
                </div>
                <div class="bsp-form-field half">
                    <label for="currency_symbol"><?php _e('Currency Symbol', 'booking-system-pro'); ?></label>
                    <input type="text" id="currency_symbol" name="general_settings[currency_symbol]" value="<?php echo esc_attr($general_settings['currency_symbol']); ?>" maxlength="3">
                </div>
            </div>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field half">
                    <label for="time_format"><?php _e('Time Format', 'booking-system-pro'); ?></label>
                    <select id="time_format" name="general_settings[time_format]">
                        <option value="12" <?php selected($general_settings['time_format'], '12'); ?>><?php _e('12 Hour (AM/PM)', 'booking-system-pro'); ?></option>
                        <option value="24" <?php selected($general_settings['time_format'], '24'); ?>><?php _e('24 Hour', 'booking-system-pro'); ?></option>
                    </select>
                </div>
                <div class="bsp-form-field half">
                    <label for="date_format"><?php _e('Date Format', 'booking-system-pro'); ?></label>
                    <select id="date_format" name="general_settings[date_format]">
                        <option value="Y-m-d" <?php selected($general_settings['date_format'], 'Y-m-d'); ?>>YYYY-MM-DD</option>
                        <option value="m/d/Y" <?php selected($general_settings['date_format'], 'm/d/Y'); ?>>MM/DD/YYYY</option>
                        <option value="d/m/Y" <?php selected($general_settings['date_format'], 'd/m/Y'); ?>>DD/MM/YYYY</option>
                        <option value="M j, Y" <?php selected($general_settings['date_format'], 'M j, Y'); ?>>Month DD, YYYY</option>
                    </select>
                </div>
            </div>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field third">
                    <label for="default_duration"><?php _e('Default Service Duration (minutes)', 'booking-system-pro'); ?></label>
                    <input type="number" id="default_duration" name="general_settings[default_duration]" value="<?php echo esc_attr($general_settings['default_duration']); ?>" min="15" step="15">
                </div>
                <div class="bsp-form-field third">
                    <label for="advance_booking_days"><?php _e('Advance Booking Days', 'booking-system-pro'); ?></label>
                    <input type="number" id="advance_booking_days" name="general_settings[advance_booking_days]" value="<?php echo esc_attr($general_settings['advance_booking_days']); ?>" min="1">
                </div>
                <div class="bsp-form-field third">
                    <label for="max_bookings_per_day"><?php _e('Max Bookings Per Day', 'booking-system-pro'); ?></label>
                    <input type="number" id="max_bookings_per_day" name="general_settings[max_bookings_per_day]" value="<?php echo esc_attr($general_settings['max_bookings_per_day']); ?>" min="1">
                </div>
            </div>
        </div>
        
        <!-- Email Settings -->
        <div id="email" class="bsp-settings-tab-content">
            <h3><?php _e('Email Settings', 'booking-system-pro'); ?></h3>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field half">
                    <label for="from_name"><?php _e('From Name', 'booking-system-pro'); ?></label>
                    <input type="text" id="from_name" name="email_settings[from_name]" value="<?php echo esc_attr($email_settings['from_name']); ?>">
                </div>
                <div class="bsp-form-field half">
                    <label for="from_email"><?php _e('From Email', 'booking-system-pro'); ?></label>
                    <input type="email" id="from_email" name="email_settings[from_email]" value="<?php echo esc_attr($email_settings['from_email']); ?>">
                </div>
            </div>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field">
                    <label for="admin_email"><?php _e('Admin Notification Email', 'booking-system-pro'); ?></label>
                    <input type="email" id="admin_email" name="email_settings[admin_email]" value="<?php echo esc_attr($email_settings['admin_email']); ?>">
                    <p class="description"><?php _e('Email address to receive booking notifications.', 'booking-system-pro'); ?></p>
                </div>
            </div>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field-group">
                    <h4><?php _e('Email Notifications', 'booking-system-pro'); ?></h4>
                    <label class="bsp-checkbox-label">
                        <input type="checkbox" name="email_settings[send_customer_confirmation]" value="1" <?php checked($email_settings['send_customer_confirmation'], '1'); ?>>
                        <?php _e('Send confirmation emails to customers', 'booking-system-pro'); ?>
                    </label>
                    <label class="bsp-checkbox-label">
                        <input type="checkbox" name="email_settings[send_admin_notification]" value="1" <?php checked($email_settings['send_admin_notification'], '1'); ?>>
                        <?php _e('Send notification emails to admin', 'booking-system-pro'); ?>
                    </label>
                    <label class="bsp-checkbox-label">
                        <input type="checkbox" name="email_settings[send_reminder_emails]" value="1" <?php checked($email_settings['send_reminder_emails'], '1'); ?>>
                        <?php _e('Send reminder emails before appointments', 'booking-system-pro'); ?>
                    </label>
                </div>
            </div>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field half">
                    <label for="reminder_hours"><?php _e('Reminder Email Time (hours before)', 'booking-system-pro'); ?></label>
                    <input type="number" id="reminder_hours" name="email_settings[reminder_hours]" value="<?php echo esc_attr($email_settings['reminder_hours']); ?>" min="1">
                </div>
            </div>
        </div>
        
        <!-- Appearance Settings -->
        <div id="appearance" class="bsp-settings-tab-content">
            <h3><?php _e('Appearance Settings', 'booking-system-pro'); ?></h3>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field third">
                    <label for="primary_color"><?php _e('Primary Color', 'booking-system-pro'); ?></label>
                    <input type="text" id="primary_color" name="appearance_settings[primary_color]" value="<?php echo esc_attr($appearance_settings['primary_color']); ?>" class="bsp-color-picker">
                </div>
                <div class="bsp-form-field third">
                    <label for="secondary_color"><?php _e('Secondary Color', 'booking-system-pro'); ?></label>
                    <input type="text" id="secondary_color" name="appearance_settings[secondary_color]" value="<?php echo esc_attr($appearance_settings['secondary_color']); ?>" class="bsp-color-picker">
                </div>
                <div class="bsp-form-field third">
                    <label for="accent_color"><?php _e('Accent Color', 'booking-system-pro'); ?></label>
                    <input type="text" id="accent_color" name="appearance_settings[accent_color]" value="<?php echo esc_attr($appearance_settings['accent_color']); ?>" class="bsp-color-picker">
                </div>
            </div>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field half">
                    <label for="button_style"><?php _e('Button Style', 'booking-system-pro'); ?></label>
                    <select id="button_style" name="appearance_settings[button_style]">
                        <option value="rounded" <?php selected($appearance_settings['button_style'], 'rounded'); ?>><?php _e('Rounded', 'booking-system-pro'); ?></option>
                        <option value="square" <?php selected($appearance_settings['button_style'], 'square'); ?>><?php _e('Square', 'booking-system-pro'); ?></option>
                        <option value="pill" <?php selected($appearance_settings['button_style'], 'pill'); ?>><?php _e('Pill', 'booking-system-pro'); ?></option>
                    </select>
                </div>
                <div class="bsp-form-field half">
                    <label for="form_layout"><?php _e('Form Layout', 'booking-system-pro'); ?></label>
                    <select id="form_layout" name="appearance_settings[form_layout]">
                        <option value="stacked" <?php selected($appearance_settings['form_layout'], 'stacked'); ?>><?php _e('Stacked', 'booking-system-pro'); ?></option>
                        <option value="inline" <?php selected($appearance_settings['form_layout'], 'inline'); ?>><?php _e('Inline', 'booking-system-pro'); ?></option>
                        <option value="grid" <?php selected($appearance_settings['form_layout'], 'grid'); ?>><?php _e('Grid', 'booking-system-pro'); ?></option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Advanced Settings -->
        <div id="advanced" class="bsp-settings-tab-content">
            <h3><?php _e('Advanced Settings', 'booking-system-pro'); ?></h3>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field">
                    <h4><?php _e('Debug & Development', 'booking-system-pro'); ?></h4>
                    <label class="bsp-checkbox-label">
                        <input type="checkbox" name="advanced_settings[debug_mode]" value="1">
                        <?php _e('Enable debug mode (logs detailed information)', 'booking-system-pro'); ?>
                    </label>
                    <label class="bsp-checkbox-label">
                        <input type="checkbox" name="advanced_settings[disable_cache]" value="1">
                        <?php _e('Disable caching for booking forms', 'booking-system-pro'); ?>
                    </label>
                </div>
            </div>
            
            <div class="bsp-form-row">
                <div class="bsp-form-field">
                    <h4><?php _e('Data Management', 'booking-system-pro'); ?></h4>
                    <p><?php _e('These actions will permanently modify your data. Use with caution.', 'booking-system-pro'); ?></p>
                    <button type="button" class="bsp-btn bsp-btn-secondary" onclick="alert('Feature coming soon')">
                        <?php _e('Export All Data', 'booking-system-pro'); ?>
                    </button>
                    <button type="button" class="bsp-btn bsp-btn-danger" onclick="alert('Feature coming soon')">
                        <?php _e('Reset All Settings', 'booking-system-pro'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <div class="bsp-actions">
            <input type="submit" name="submit_settings" class="bsp-btn" value="<?php _e('Save Settings', 'booking-system-pro'); ?>">
        </div>
    </form>
</div>
