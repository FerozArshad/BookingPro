<?php
/**
 * Database operations for Booking System
 */
class Booking_System_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Bookings table
        $bookings_table = $wpdb->prefix . 'booking_system_bookings';
        $bookings_sql = "CREATE TABLE $bookings_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            service varchar(50) NOT NULL,
            service_details text,
            full_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            address text NOT NULL,
            zip_code varchar(10) NOT NULL,
            company_id mediumint(9) NOT NULL,
            appointment_date datetime NOT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Companies table
        $companies_table = $wpdb->prefix . 'booking_system_companies';
        $companies_sql = "CREATE TABLE $companies_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            address text,
            available_hours_start varchar(5) DEFAULT '12:00',
            available_hours_end varchar(5) DEFAULT '19:00',
            available_days varchar(20) DEFAULT '1,2,3,4,5',
            time_slot_duration int DEFAULT 30,
            max_bookings_per_day int DEFAULT 8,
            advance_booking_days int DEFAULT 30,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Availability table
        $availability_table = $wpdb->prefix . 'booking_system_availability';
        $availability_sql = "CREATE TABLE $availability_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            company_id mediumint(9) NOT NULL,
            date date NOT NULL,
            time_slot varchar(10) NOT NULL,
            is_booked tinyint(1) DEFAULT 0,
            booking_id mediumint(9) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slot (company_id, date, time_slot)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($bookings_sql);
        dbDelta($companies_sql);
        dbDelta($availability_sql);
        
        // Run migration for existing installations
        self::migrate_database();
        
        // Insert default companies
        self::insert_default_companies();
    }
    
    private static function insert_default_companies() {
        global $wpdb;
        
        $companies_table = $wpdb->prefix . 'booking_system_companies';
        
        $default_companies = array(
            array(
                'name' => 'Top Remodeling Pro',
                'email' => 'contact@topremodeling.com',
                'phone' => '(555) 123-4567',
                'address' => '123 Main St, Los Angeles, CA',
                'available_hours_start' => '12:00',
                'available_hours_end' => '19:00',
                'available_days' => '1,2,3,4,5', // Mon-Fri
                'time_slot_duration' => 30,
                'max_bookings_per_day' => 8,
                'advance_booking_days' => 30
            ),
            array(
                'name' => 'RH Remodeling',
                'email' => 'info@rhremodeling.com',
                'phone' => '(555) 234-5678',
                'address' => '456 Oak Ave, Los Angeles, CA',
                'available_hours_start' => '13:00',
                'available_hours_end' => '18:00',
                'available_days' => '1,2,3,4,5,6', // Mon-Sat
                'time_slot_duration' => 30,
                'max_bookings_per_day' => 6,
                'advance_booking_days' => 30
            ),
            array(
                'name' => 'Eco Green',
                'email' => 'hello@ecogreen.com',
                'phone' => '(555) 345-6789',
                'address' => '789 Pine St, Los Angeles, CA',
                'available_hours_start' => '12:00',
                'available_hours_end' => '19:00',
                'available_days' => '2,3,4,5,6', // Tue-Sat
                'time_slot_duration' => 30,
                'max_bookings_per_day' => 10,
                'advance_booking_days' => 30
            )
        );
        
        foreach ($default_companies as $company) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $companies_table WHERE email = %s",
                $company['email']
            ));
            
            if (!$exists) {
                $wpdb->insert($companies_table, $company);
            }
        }
    }
    
    public static function get_companies() {
        global $wpdb;
        $companies_table = $wpdb->prefix . 'booking_system_companies';
        
        return $wpdb->get_results("SELECT * FROM $companies_table WHERE status = 'active'");
    }
    
    public static function get_company_availability($company_id, $date_from = null, $date_to = null) {
        global $wpdb;
        $companies_table = $wpdb->prefix . 'booking_system_companies';
        $availability_table = $wpdb->prefix . 'booking_system_availability';
        
        // Get company settings
        $company = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $companies_table WHERE id = %d AND status = 'active'",
            $company_id
        ));
        
        if (!$company) {
            return false;
        }
        
        // Parse available days (0=Sunday, 1=Monday, etc.)
        $available_days = array_map('intval', explode(',', $company->available_days));
        
        // Date range
        if (!$date_from) $date_from = date('Y-m-d');
        if (!$date_to) $date_to = date('Y-m-d', strtotime($date_from . ' +' . $company->advance_booking_days . ' days'));
        
        $availability = array();
        $current_date = new DateTime($date_from);
        $end_date = new DateTime($date_to);
        
        while ($current_date <= $end_date) {
            $date_str = $current_date->format('Y-m-d');
            $day_of_week = (int)$current_date->format('w');
            
            // Check if this day is available for this company
            if (in_array($day_of_week, $available_days)) {
                // Generate time slots
                $slots = self::generate_time_slots($company, $date_str);
                if (!empty($slots)) {
                    $availability[$date_str] = array(
                        'date' => $date_str,
                        'day_name' => $current_date->format('D'),
                        'day_number' => $current_date->format('j'),
                        'slots' => $slots,
                        'company' => array(
                            'id' => $company->id,
                            'name' => $company->name,
                            'phone' => $company->phone
                        )
                    );
                }
            }
            
            $current_date->modify('+1 day');
        }
        
        return $availability;
    }
    
    private static function generate_time_slots($company, $date) {
        global $wpdb;
        $availability_table = $wpdb->prefix . 'booking_system_availability';
        
        $slots = array();
        $start_time = new DateTime($date . ' ' . $company->available_hours_start);
        $end_time = new DateTime($date . ' ' . $company->available_hours_end);
        $duration = $company->time_slot_duration;
        
        // Get booked slots for this date and company
        $booked_slots = $wpdb->get_col($wpdb->prepare(
            "SELECT time_slot FROM $availability_table WHERE company_id = %d AND date = %s AND is_booked = 1",
            $company->id, $date
        ));
        
        // Generate available slots
        while ($start_time < $end_time) {
            $time_slot = $start_time->format('H:i');
            
            $slots[] = array(
                'time' => $time_slot,
                'formatted' => $start_time->format('g:i A'),
                'available' => !in_array($time_slot, $booked_slots)
            );
            
            $start_time->modify('+' . $duration . ' minutes');
        }
        
        return $slots;
    }
    
    public static function get_availability($company_id, $date) {
        global $wpdb;
        $availability_table = $wpdb->prefix . 'booking_system_availability';
        
        $slots = array();
        $start_hour = 12;
        $end_hour = 19;
        
        for ($hour = $start_hour; $hour < $end_hour; $hour++) {
            $time_slot = sprintf('%02d:00', $hour);
            $is_booked = $wpdb->get_var($wpdb->prepare(
                "SELECT is_booked FROM $availability_table WHERE company_id = %d AND date = %s AND time_slot = %s",
                $company_id, $date, $time_slot
            ));
            
            $slots[] = array(
                'time' => $time_slot,
                'available' => !$is_booked
            );
        }
        
        return $slots;
    }
    
    public static function book_slot($company_id, $date, $time_slot, $booking_id) {
        global $wpdb;
        $availability_table = $wpdb->prefix . 'booking_system_availability';
        
        return $wpdb->replace($availability_table, array(
            'company_id' => $company_id,
            'date' => $date,
            'time_slot' => $time_slot,
            'is_booked' => 1,
            'booking_id' => $booking_id
        ));
    }
    
    public static function create_booking($data) {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'booking_system_bookings';
        
        $result = $wpdb->insert($bookings_table, $data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    public static function get_bookings($limit = 20, $offset = 0) {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'booking_system_bookings';
        $companies_table = $wpdb->prefix . 'booking_system_companies';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.name as company_name 
             FROM $bookings_table b 
             LEFT JOIN $companies_table c ON b.company_id = c.id 
             ORDER BY b.created_at DESC 
             LIMIT %d OFFSET %d",
            $limit, $offset
        ));
    }
    
    public static function update_company_availability($company_id, $settings) {
        global $wpdb;
        $companies_table = $wpdb->prefix . 'booking_system_companies';
        
        $allowed_fields = array(
            'available_hours_start',
            'available_hours_end', 
            'available_days',
            'time_slot_duration',
            'max_bookings_per_day',
            'advance_booking_days'
        );
        
        $update_data = array();
        foreach ($settings as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $update_data[$key] = $value;
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $companies_table,
            $update_data,
            array('id' => $company_id),
            array_fill(0, count($update_data), '%s'),
            array('%d')
        );
    }
    
    public static function get_company_settings($company_id = null) {
        global $wpdb;
        $companies_table = $wpdb->prefix . 'booking_system_companies';
        
        if ($company_id) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $companies_table WHERE id = %d AND status = 'active'",
                $company_id
            ));
        } else {
            return $wpdb->get_results(
                "SELECT * FROM $companies_table WHERE status = 'active' ORDER BY name"
            );
        }
    }
    
    public static function migrate_database() {
        global $wpdb;
        $companies_table = $wpdb->prefix . 'booking_system_companies';
        
        // Check if new columns exist, if not add them
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $companies_table");
        $column_names = array_column($columns, 'Field');
        
        $new_columns = [
            'available_hours_start' => "ALTER TABLE $companies_table ADD COLUMN available_hours_start varchar(5) DEFAULT '12:00'",
            'available_hours_end' => "ALTER TABLE $companies_table ADD COLUMN available_hours_end varchar(5) DEFAULT '19:00'",
            'time_slot_duration' => "ALTER TABLE $companies_table ADD COLUMN time_slot_duration int DEFAULT 30",
            'max_bookings_per_day' => "ALTER TABLE $companies_table ADD COLUMN max_bookings_per_day int DEFAULT 8",
            'advance_booking_days' => "ALTER TABLE $companies_table ADD COLUMN advance_booking_days int DEFAULT 30"
        ];
        
        foreach ($new_columns as $column => $sql) {
            if (!in_array($column, $column_names)) {
                $wpdb->query($sql);
            }
        }
        
        // Remove old columns if they exist
        if (in_array('available_hours', $column_names)) {
            $wpdb->query("ALTER TABLE $companies_table DROP COLUMN available_hours");
        }
        
        // Update existing records with default values
        $wpdb->query("UPDATE $companies_table SET 
            available_hours_start = COALESCE(available_hours_start, '12:00'),
            available_hours_end = COALESCE(available_hours_end, '19:00'),
            time_slot_duration = COALESCE(time_slot_duration, 30),
            max_bookings_per_day = COALESCE(max_bookings_per_day, 8),
            advance_booking_days = COALESCE(advance_booking_days, 30)
        ");
    }
}
?>
