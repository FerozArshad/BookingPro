<?php
/**
 * Admin Availability Template
 */

if (!defined('ABSPATH')) exit;

// Get database instance
$db = BSP_Database_Unified::get_instance();
$companies = BSP_Database_Unified::get_companies(['status' => 'active']);
?>

<div class="wrap bsp-admin-wrapper">
    <div class="bsp-admin-header">
        <h1><?php _e('Availability Management', 'booking-system-pro'); ?></h1>
        <p><?php _e('Manage company schedules, holidays, and time slot availability.', 'booking-system-pro'); ?></p>
    </div>
    
    <!-- Company Selection -->
    <div class="bsp-form-section">
        <h3><?php _e('Select Company', 'booking-system-pro'); ?></h3>
        <select id="bsp-company-selector" class="bsp-select">
            <option value=""><?php _e('Select a company to manage availability', 'booking-system-pro'); ?></option>
            <?php foreach ($companies as $company) : ?>
                <option value="<?php echo esc_attr($company->id); ?>">
                    <?php echo esc_html($company->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <!-- Calendar View -->
    <div id="bsp-availability-calendar" class="bsp-form-section" style="display: none;">
        <h3><?php _e('Weekly Schedule', 'booking-system-pro'); ?></h3>
        
        <div class="bsp-schedule-grid">
            <div class="bsp-schedule-header">
                <div class="bsp-time-column"><?php _e('Time', 'booking-system-pro'); ?></div>
                <div class="bsp-day-column"><?php _e('Monday', 'booking-system-pro'); ?></div>
                <div class="bsp-day-column"><?php _e('Tuesday', 'booking-system-pro'); ?></div>
                <div class="bsp-day-column"><?php _e('Wednesday', 'booking-system-pro'); ?></div>
                <div class="bsp-day-column"><?php _e('Thursday', 'booking-system-pro'); ?></div>
                <div class="bsp-day-column"><?php _e('Friday', 'booking-system-pro'); ?></div>
                <div class="bsp-day-column"><?php _e('Saturday', 'booking-system-pro'); ?></div>
                <div class="bsp-day-column"><?php _e('Sunday', 'booking-system-pro'); ?></div>
            </div>
            
            <?php
            // Generate time slots from 8 AM to 6 PM
            for ($hour = 8; $hour <= 18; $hour++) {
                $time_12 = ($hour > 12) ? ($hour - 12) . ':00 PM' : (($hour == 12) ? '12:00 PM' : $hour . ':00 AM');
                $time_24 = sprintf('%02d:00', $hour);
                ?>
                <div class="bsp-schedule-row" data-time="<?php echo $time_24; ?>">
                    <div class="bsp-time-column"><?php echo $time_12; ?></div>
                    <?php for ($day = 1; $day <= 7; $day++) : ?>
                        <div class="bsp-day-column">
                            <input type="checkbox" 
                                   class="bsp-availability-slot" 
                                   data-day="<?php echo $day; ?>" 
                                   data-time="<?php echo $time_24; ?>"
                                   id="slot_<?php echo $day; ?>_<?php echo $hour; ?>">
                            <label for="slot_<?php echo $day; ?>_<?php echo $hour; ?>" class="bsp-slot-label">
                                <?php _e('Available', 'booking-system-pro'); ?>
                            </label>
                        </div>
                    <?php endfor; ?>
                </div>
                <?php
            }
            ?>
        </div>
        
        <div class="bsp-actions">
            <button type="button" id="bsp-save-schedule" class="bsp-btn">
                <?php _e('Save Schedule', 'booking-system-pro'); ?>
            </button>
            <button type="button" id="bsp-clear-schedule" class="bsp-btn bsp-btn-secondary">
                <?php _e('Clear All', 'booking-system-pro'); ?>
            </button>
            <button type="button" id="bsp-select-all" class="bsp-btn bsp-btn-secondary">
                <?php _e('Select All', 'booking-system-pro'); ?>
            </button>
        </div>
    </div>
    
    <!-- Holidays/Blackout Dates -->
    <div id="bsp-holidays-section" class="bsp-form-section" style="display: none;">
        <h3><?php _e('Holidays & Blackout Dates', 'booking-system-pro'); ?></h3>
        
        <div class="bsp-form-row">
            <div class="bsp-form-field half">
                <label for="holiday_date"><?php _e('Date', 'booking-system-pro'); ?></label>
                <input type="date" id="holiday_date" name="holiday_date" class="bsp-datepicker">
            </div>
            <div class="bsp-form-field half">
                <label for="holiday_reason"><?php _e('Reason (Optional)', 'booking-system-pro'); ?></label>
                <input type="text" id="holiday_reason" name="holiday_reason" placeholder="<?php _e('Holiday, Vacation, etc.', 'booking-system-pro'); ?>">
            </div>
        </div>
        
        <div class="bsp-actions">
            <button type="button" id="bsp-add-holiday" class="bsp-btn">
                <?php _e('Add Blackout Date', 'booking-system-pro'); ?>
            </button>
        </div>
        
        <div id="bsp-holidays-list">
            <h4><?php _e('Current Blackout Dates', 'booking-system-pro'); ?></h4>
            <div id="bsp-holidays-container">
                <p><?php _e('No blackout dates set. Add dates above.', 'booking-system-pro'); ?></p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Show/hide availability sections based on company selection
    $('#bsp-company-selector').on('change', function() {
        const companyId = $(this).val();
        if (companyId) {
            $('#bsp-availability-calendar, #bsp-holidays-section').show();
            // TODO: Load existing availability data
        } else {
            $('#bsp-availability-calendar, #bsp-holidays-section').hide();
        }
    });
    
    // Select all availability slots
    $('#bsp-select-all').on('click', function() {
        $('.bsp-availability-slot').prop('checked', true);
    });
    
    // Clear all availability slots
    $('#bsp-clear-schedule').on('click', function() {
        $('.bsp-availability-slot').prop('checked', false);
    });
    
    // Save schedule (placeholder)
    $('#bsp-save-schedule').on('click', function() {
        const companyId = $('#bsp-company-selector').val();
        if (!companyId) {
            alert('Please select a company first.');
            return;
        }
        
        const schedule = {};
        $('.bsp-availability-slot:checked').each(function() {
            const day = $(this).data('day');
            const time = $(this).data('time');
            if (!schedule[day]) schedule[day] = [];
            schedule[day].push(time);
        });
        
        // TODO: Implement AJAX save
        console.log('Saving schedule for company', companyId, schedule);
        alert('Schedule saved! (Demo mode - not actually saved)');
    });
    
    // Add holiday (placeholder)
    $('#bsp-add-holiday').on('click', function() {
        const date = $('#holiday_date').val();
        const reason = $('#holiday_reason').val();
        
        if (!date) {
            alert('Please select a date.');
            return;
        }
        
        // TODO: Implement AJAX save
        console.log('Adding holiday:', date, reason);
        alert('Holiday added! (Demo mode - not actually saved)');
        
        // Clear form
        $('#holiday_date, #holiday_reason').val('');
    });
});
</script>
}

$selected_company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : ($companies[0]->id ?? 0);
$selected_company = null;

if ($selected_company_id) {
    $selected_company = Booking_System_Database::get_company_settings($selected_company_id);
}

// If no specific company selected, use the first one
if (!$selected_company && !empty($companies)) {
    $selected_company = $companies[0];
    $selected_company_id = $selected_company->id;
}

$days_of_week = array(
    '1' => 'Monday',
    '2' => 'Tuesday', 
    '3' => 'Wednesday',
    '4' => 'Thursday',
    '5' => 'Friday',
    '6' => 'Saturday',
    '0' => 'Sunday'
);
?>

<div class="wrap">
    <h1>Company Availability Settings</h1>
    
    <div class="availability-admin">
        <!-- Company Selection -->
        <div class="company-selector">
            <h2>Select Company</h2>
            <form method="get" id="company-selector-form">
                <input type="hidden" name="post_type" value="<?php echo esc_attr($_GET['post_type'] ?? 'bookings_pro'); ?>" />
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'bsp-availability'); ?>" />
                <select name="company_id" onchange="submitCompanyForm()">
                    <option value="">Choose a company...</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo esc_attr($company->id); ?>" 
                                <?php selected($selected_company_id, $company->id); ?>>
                            <?php echo esc_html($company->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            
            <script>
            function submitCompanyForm() {
                const form = document.getElementById('company-selector-form');
                const select = form.querySelector('select[name="company_id"]');
                if (select.value !== '') {
                    form.submit();
                }
            }
            </script>
        </div>

        <?php if ($selected_company): ?>
        <!-- Availability Settings Form -->
        <div class="availability-settings">
            <h2>Availability Settings for <?php echo esc_html($selected_company->name); ?></h2>
            
            <form method="post" class="availability-form">
                <?php wp_nonce_field('update_availability_settings'); ?>
                <input type="hidden" name="company_id" value="<?php echo esc_attr($selected_company->id); ?>" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Available Days</th>
                        <td>
                            <?php 
                            $selected_days = explode(',', (isset($selected_company->available_days) && $selected_company->available_days) ? $selected_company->available_days : '1,2,3,4,5');
                            foreach ($days_of_week as $day_num => $day_name): 
                            ?>
                                <label>
                                    <input type="checkbox" name="available_days[]" 
                                           value="<?php echo esc_attr($day_num); ?>"
                                           <?php checked(in_array($day_num, $selected_days)); ?> />
                                    <?php echo esc_html($day_name); ?>
                                </label><br>
                            <?php endforeach; ?>
                            <p class="description">Select which days of the week this company is available for bookings.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Available Hours</th>
                        <td>
                            <label>Start Time:
                                <select name="available_hours_start">
                                    <?php for ($h = 6; $h <= 22; $h++): 
                                        $time = sprintf('%02d:00', $h);
                                        $display = date('g:i A', strtotime($time));
                                        $start_time = (isset($selected_company->available_hours_start) && $selected_company->available_hours_start) ? $selected_company->available_hours_start : '12:00';
                                    ?>
                                        <option value="<?php echo esc_attr($time); ?>" 
                                                <?php selected($start_time, $time); ?>>
                                            <?php echo esc_html($display); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </label>
                            
                            <label style="margin-left: 20px;">End Time:
                                <select name="available_hours_end">
                                    <?php for ($h = 8; $h <= 23; $h++): 
                                        $time = sprintf('%02d:00', $h);
                                        $display = date('g:i A', strtotime($time));
                                        $end_time = (isset($selected_company->available_hours_end) && $selected_company->available_hours_end) ? $selected_company->available_hours_end : '19:00';
                                    ?>
                                        <option value="<?php echo esc_attr($time); ?>" 
                                                <?php selected($end_time, $time); ?>>
                                            <?php echo esc_html($display); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </label>
                            <p class="description">Set the working hours for this company.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Time Slot Duration</th>
                        <td>
                            <select name="time_slot_duration">
                                <?php $duration = (isset($selected_company->time_slot_duration) && $selected_company->time_slot_duration) ? $selected_company->time_slot_duration : 30; ?>
                                <option value="15" <?php selected($duration, 15); ?>>15 minutes</option>
                                <option value="30" <?php selected($duration, 30); ?>>30 minutes</option>
                                <option value="45" <?php selected($duration, 45); ?>>45 minutes</option>
                                <option value="60" <?php selected($duration, 60); ?>>1 hour</option>
                            </select>
                            <p class="description">Duration of each booking time slot.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Max Bookings Per Day</th>
                        <td>
                            <input type="number" name="max_bookings_per_day" 
                                   value="<?php echo esc_attr((isset($selected_company->max_bookings_per_day) && $selected_company->max_bookings_per_day) ? $selected_company->max_bookings_per_day : 8); ?>" 
                                   min="1" max="50" />
                            <p class="description">Maximum number of bookings this company can handle per day.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Advance Booking Days</th>
                        <td>
                            <input type="number" name="advance_booking_days" 
                                   value="<?php echo esc_attr((isset($selected_company->advance_booking_days) && $selected_company->advance_booking_days) ? $selected_company->advance_booking_days : 30); ?>" 
                                   min="1" max="365" />
                            <p class="description">How many days in advance customers can book appointments.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="Update Availability Settings" />
                </p>
            </form>
        </div>
        
        <!-- Current Bookings Preview -->
        <div class="current-bookings">
            <h3>Recent Bookings for <?php echo esc_html($selected_company->name); ?></h3>
            <?php
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'booking_system_bookings';
            $recent_bookings = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $bookings_table WHERE company_id = %d ORDER BY appointment_date DESC LIMIT 10",
                $selected_company->id
            ));
            ?>
            
            <?php if ($recent_bookings): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_bookings as $booking): ?>
                    <tr>
                        <td><?php echo esc_html($booking->full_name); ?></td>
                        <td><?php echo esc_html($booking->service); ?></td>
                        <td><?php echo esc_html(date('M j, Y g:i A', strtotime($booking->appointment_date))); ?></td>
                        <td><span class="status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html(ucfirst($booking->status)); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No recent bookings found for this company.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.availability-admin {
    max-width: 1000px;
}

.company-selector {
    background: #fff;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.company-selector select {
    min-width: 300px;
    padding: 6px 8px;
}

.availability-settings {
    background: #fff;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.availability-form .form-table th {
    width: 200px;
}

.availability-form label {
    display: inline-block;
    margin-right: 10px;
}

.current-bookings {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.status-pending { color: #f56500; }
.status-confirmed { color: #00a32a; }
.status-completed { color: #135e96; }
.status-cancelled { color: #d63638; }
</style>
