<?php
/**
 * Enhanced Database Management Class for Unified Booking System
 * Handles all database operations with optimized schema and data integrity
 */

if (!defined('ABSPATH')) exit;

class BSP_Database_Unified {
    
    private static $instance = null;
    private $wpdb;
    
    // Query caching properties
    private static $cache = [];
    private static $cache_expiry = [];
    private static $cache_duration = 300; // 5 minutes
    
    // New unified table structure
    public static $tables = [
        'bookings' => 'bsp_bookings',
        'companies' => 'bsp_companies', 
        'services' => 'bsp_services',
        'customers' => 'bsp_customers',
        'availability' => 'bsp_availability',
        'email_logs' => 'bsp_email_logs',
        'settings' => 'bsp_settings',
        'incomplete_leads' => 'bsp_incomplete_leads'
    ];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("BSP_Database_Unified initialized", 'DATABASE');
        }
        
        // Set table names with prefix
        self::init_tables();
        
        if (function_exists('bsp_debug_log')) {
            bsp_debug_log("Database tables initialized", 'DATABASE', self::$tables);
        }
    }
    
    /**
     * Initialize table names with prefix
     */
    public static function init_tables() {
        global $wpdb;
        
        // Only initialize if not already done
        if (!isset(self::$tables['bookings']) || strpos(self::$tables['bookings'], $wpdb->prefix) === false) {
            $tables = [
                'bookings' => 'bsp_bookings',
                'companies' => 'bsp_companies', 
                'services' => 'bsp_services',
                'customers' => 'bsp_customers',
                'availability' => 'bsp_availability',
                'email_logs' => 'bsp_email_logs',
                'settings' => 'bsp_settings',
                'incomplete_leads' => 'bsp_incomplete_leads'
            ];
            
            foreach ($tables as $key => $table) {
                self::$tables[$key] = $wpdb->prefix . $table;
            }
        }
    }
    
    /**
     * Create all database tables with optimized schema
     */
    public function create_tables() {
        global $wpdb;
        
        // Initialize tables if not already done
        self::init_tables();
        
        // Check if wpdb is available
        if (!$wpdb) {
            return false;
        }
        
        $charset_collate = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        
        // Companies table - centralized company management
        $companies_sql = "CREATE TABLE " . self::$tables['companies'] . " (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(20) DEFAULT NULL,
            address text DEFAULT NULL,
            description text DEFAULT NULL,
            website varchar(255) DEFAULT NULL,
            logo_url varchar(255) DEFAULT NULL,
            rating decimal(3,2) DEFAULT 0.00,
            total_reviews int DEFAULT 0,
            category varchar(100) DEFAULT 'standard',
            status enum('active','inactive','pending') DEFAULT 'active',
            available_days varchar(20) DEFAULT '1,2,3,4,5,6,7',
            available_hours_start time DEFAULT '11:00:00',
            available_hours_end time DEFAULT '19:00:00',
            time_slot_duration int DEFAULT 30,
            max_bookings_per_day int DEFAULT 10,
            advance_booking_days int DEFAULT 30,
            booking_buffer_minutes int DEFAULT 15,
            auto_confirm_bookings tinyint(1) DEFAULT 0,
            send_notifications tinyint(1) DEFAULT 1,
            metadata text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY category (category)
        ) $charset_collate;";
        
        // Services table - service type management
        $services_sql = "CREATE TABLE " . self::$tables['services'] . " (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text DEFAULT NULL,
            duration_minutes int DEFAULT 60,
            price decimal(10,2) DEFAULT 0.00,
            category varchar(100) DEFAULT 'general',
            status enum('active','inactive') DEFAULT 'active',
            metadata text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY category (category)
        ) $charset_collate;";
        
        // Customers table - customer data management
        $customers_sql = "CREATE TABLE " . self::$tables['customers'] . " (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) DEFAULT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(20) DEFAULT NULL,
            address text DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            state varchar(100) DEFAULT NULL,
            zip_code varchar(20) DEFAULT NULL,
            customer_type enum('new','returning','vip') DEFAULT 'new',
            total_bookings int DEFAULT 0,
            total_spent decimal(10,2) DEFAULT 0.00,
            notes text DEFAULT NULL,
            metadata text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY wp_user_id (wp_user_id),
            KEY email (email),
            KEY customer_type (customer_type)
        ) $charset_collate;";
        
        // Bookings table - comprehensive booking management
        $bookings_sql = "CREATE TABLE " . self::$tables['bookings'] . " (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            booking_number varchar(50) NOT NULL,
            customer_id mediumint(9) NOT NULL,
            company_id mediumint(9) NOT NULL,
            service_id mediumint(9) DEFAULT NULL,
            wp_post_id bigint(20) DEFAULT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            duration_minutes int DEFAULT 60,
            status enum('pending','confirmed','in_progress','completed','cancelled','rescheduled') DEFAULT 'pending',
            payment_status enum('unpaid','paid','refunded','partial') DEFAULT 'unpaid',
            amount decimal(10,2) DEFAULT 0.00,
            deposit_amount decimal(10,2) DEFAULT 0.00,
            service_type varchar(255) NOT NULL,
            special_requests text DEFAULT NULL,
            admin_notes text DEFAULT NULL,
            customer_notes text DEFAULT NULL,
            confirmation_sent_at datetime DEFAULT NULL,
            reminder_sent_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            cancelled_at datetime DEFAULT NULL,
            cancellation_reason text DEFAULT NULL,
            rescheduled_from_id mediumint(9) DEFAULT NULL,
            source varchar(50) DEFAULT 'website',
            utm_source varchar(100) DEFAULT NULL,
            utm_medium varchar(100) DEFAULT NULL,
            utm_campaign varchar(100) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            form_data text DEFAULT NULL,
            metadata text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY booking_number (booking_number),
            KEY customer_id (customer_id),
            KEY company_id (company_id),
            KEY service_id (service_id),
            KEY wp_post_id (wp_post_id),
            KEY booking_date (booking_date),
            KEY status (status),
            KEY payment_status (payment_status),
            KEY source (source)
        ) $charset_collate;";
        
        // Availability table - time slot management
        $availability_sql = "CREATE TABLE " . self::$tables['availability'] . " (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            company_id mediumint(9) NOT NULL,
            date date NOT NULL,
            time_slot time NOT NULL,
            duration_minutes int DEFAULT 30,
            is_available tinyint(1) DEFAULT 1,
            is_booked tinyint(1) DEFAULT 0,
            booking_id mediumint(9) DEFAULT NULL,
            blocked_reason varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slot (company_id, date, time_slot),
            KEY company_id (company_id),
            KEY date (date),
            KEY is_available (is_available),
            KEY is_booked (is_booked),
            KEY booking_id (booking_id)
        ) $charset_collate;";
        
        // Email logs table - comprehensive email tracking
        $email_logs_sql = "CREATE TABLE " . self::$tables['email_logs'] . " (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            booking_id mediumint(9) DEFAULT NULL,
            customer_id mediumint(9) DEFAULT NULL,
            company_id mediumint(9) DEFAULT NULL,
            email_type enum('confirmation','reminder','cancellation','rescheduled','admin_notification','follow_up') NOT NULL,
            to_email varchar(255) NOT NULL,
            from_email varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            message text NOT NULL,
            status enum('pending','sent','failed','bounced') DEFAULT 'pending',
            sent_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            open_count int DEFAULT 0,
            click_count int DEFAULT 0,
            metadata text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY customer_id (customer_id),
            KEY company_id (company_id),
            KEY email_type (email_type),
            KEY status (status),
            KEY sent_at (sent_at)
        ) $charset_collate;";
        
        // Settings table - flexible configuration storage
        $settings_sql = "CREATE TABLE " . self::$tables['settings'] . " (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(255) NOT NULL,
            setting_value longtext DEFAULT NULL,
            setting_type enum('string','number','boolean','array','object') DEFAULT 'string',
            category varchar(100) DEFAULT 'general',
            description text DEFAULT NULL,
            is_autoload tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key),
            KEY category (category),
            KEY is_autoload (is_autoload)
        ) $charset_collate;";
        
        // Incomplete leads table - simplified lead capture and conversion tracking
        $incomplete_leads_sql = "CREATE TABLE " . self::$tables['incomplete_leads'] . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            service varchar(50),
            zip_code varchar(10),
            customer_name varchar(255),
            customer_email varchar(255),
            customer_phone varchar(20),
            completion_percentage int(3) DEFAULT 0,
            lead_type varchar(50),
            
            utm_source varchar(255),
            utm_medium varchar(255), 
            utm_campaign varchar(255),
            utm_term varchar(255),
            utm_content varchar(255),
            gclid varchar(255),
            referrer text,
            
            form_data longtext,
            final_form_data longtext,
            
            is_complete tinyint(1) DEFAULT 0,
            converted_to_booking tinyint(1) DEFAULT 0,
            booking_post_id bigint(20),
            conversion_timestamp datetime,
            conversion_session_id varchar(255),
            
            created_at datetime NOT NULL,
            last_updated datetime NOT NULL,
            send_trigger varchar(50),
            data_send_count int(3) DEFAULT 0,
            sheets_sync_timestamp datetime DEFAULT NULL,
            traffic_source varchar(255) DEFAULT '',
            
            PRIMARY KEY (id),
            UNIQUE KEY unique_session_id (session_id),
            KEY customer_email (customer_email),
            KEY booking_post_id (booking_post_id),
            KEY completion_percentage (completion_percentage),
            KEY is_complete (is_complete),
            KEY converted_to_booking (converted_to_booking)
        ) $charset_collate;";
        
        // Execute table creation
        if (!function_exists('dbDelta')) {
            if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            } else {
                // Fallback: use direct wpdb queries if dbDelta is not available
                global $wpdb;
                $wpdb->query($companies_sql);
                $wpdb->query($services_sql);
                $wpdb->query($customers_sql);
                $wpdb->query($bookings_sql);
                $wpdb->query($availability_sql);
                $wpdb->query($email_logs_sql);
                $wpdb->query($settings_sql);
                $wpdb->query($incomplete_leads_sql);
                return;
            }
        }
        
        dbDelta($companies_sql);
        dbDelta($services_sql);
        dbDelta($customers_sql);
        dbDelta($bookings_sql);
        dbDelta($availability_sql);
        dbDelta($email_logs_sql);
        dbDelta($settings_sql);
        dbDelta($incomplete_leads_sql);
        
        // Update database version
        update_option('bsp_unified_db_version', BSP_DB_VERSION);
        
        return true;
    }
    
    /**
     * Fix database table issues (like duplicate key errors)
     */
    public function fix_table_issues() {
        global $wpdb;
        
        // Fix the incomplete_leads table duplicate key issue
        $table_name = self::$tables['incomplete_leads'];
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if ($table_exists) {
            // Drop the problematic table
            $wpdb->query("DROP TABLE IF EXISTS " . $table_name);
            bsp_debug_log("Dropped problematic incomplete_leads table", 'DATABASE');
        }
        
        // Recreate the table with fixed structure
        $charset_collate = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
        
        $incomplete_leads_sql = "CREATE TABLE " . $table_name . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            service varchar(50),
            zip_code varchar(10),
            customer_name varchar(255),
            customer_email varchar(255),
            customer_phone varchar(20),
            completion_percentage int(3) DEFAULT 0,
            lead_type varchar(50),
            
            utm_source varchar(255),
            utm_medium varchar(255), 
            utm_campaign varchar(255),
            utm_term varchar(255),
            utm_content varchar(255),
            gclid varchar(255),
            referrer text,
            
            form_data longtext,
            final_form_data longtext,
            
            is_complete tinyint(1) DEFAULT 0,
            converted_to_booking tinyint(1) DEFAULT 0,
            booking_post_id bigint(20),
            conversion_timestamp datetime,
            conversion_session_id varchar(255),
            
            created_at datetime NOT NULL,
            last_updated datetime NOT NULL,
            send_trigger varchar(50),
            data_send_count int(3) DEFAULT 0,
            sheets_sync_timestamp datetime DEFAULT NULL,
            traffic_source varchar(255) DEFAULT '',
            
            PRIMARY KEY (id),
            UNIQUE KEY unique_session_id (session_id),
            KEY customer_email (customer_email),
            KEY booking_post_id (booking_post_id),
            KEY completion_percentage (completion_percentage),
            KEY is_complete (is_complete),
            KEY converted_to_booking (converted_to_booking)
        ) $charset_collate;";
        
        // Create the fixed table
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        $result = dbDelta($incomplete_leads_sql);
        
        if ($result) {
            bsp_debug_log("Fixed incomplete_leads table structure", 'DATABASE');
            return true;
        } else {
            bsp_debug_log("Failed to fix incomplete_leads table", 'ERROR');
            return false;
        }
    }
    
    /**
     * Insert default data for testing and initial setup
     */
    public function insert_default_data() {
        global $wpdb;
        
        // Initialize tables if not already done
        self::init_tables();
        
        // Check if data already exists
        $existing_companies = $wpdb->get_var("SELECT COUNT(*) FROM " . self::$tables['companies']);
        if ($existing_companies > 0) {
            return; // Data already exists
        }
        
        // Default services
        $default_services = [
            [
                'name' => 'Kitchen Remodeling',
                'slug' => 'kitchen-remodeling',
                'description' => 'Complete kitchen renovation and remodeling services',
                'duration_minutes' => 120,
                'category' => 'remodeling',
                'price' => 0.00
            ],
            [
                'name' => 'Bathroom Renovation',
                'slug' => 'bathroom-renovation', 
                'description' => 'Full bathroom renovation and upgrade services',
                'duration_minutes' => 90,
                'category' => 'remodeling',
                'price' => 0.00
            ],
            [
                'name' => 'Roofing Services',
                'slug' => 'roofing-services',
                'description' => 'Professional roofing installation and repair',
                'duration_minutes' => 180,
                'category' => 'exterior',
                'price' => 0.00
            ],
            [
                'name' => 'Flooring Installation',
                'slug' => 'flooring-installation',
                'description' => 'Professional flooring installation services',
                'duration_minutes' => 120,
                'category' => 'interior',
                'price' => 0.00
            ]
        ];
        
        foreach ($default_services as $service) {
            $wpdb->insert(self::$tables['services'], $service);
        }
        
        // Default companies with comprehensive data
        $default_companies = [
            [
                'name' => 'Top Remodeling Pro',
                'slug' => 'top-remodeling-pro',
                'email' => 'contact@topremodeling.com',
                'phone' => '(555) 123-4567',
                'address' => '123 Main St, Los Angeles, CA 90210',
                'description' => 'Premium remodeling services with 15+ years experience. We specialize in kitchen and bathroom renovations.',
                'website' => 'https://topremodeling.com',
                'rating' => 4.8,
                'total_reviews' => 156,
                'category' => 'premium',
                'available_days' => '1,2,3,4,5,6,7',
                'available_hours_start' => '08:00:00',
                'available_hours_end' => '18:00:00',
                'time_slot_duration' => 30,
                'max_bookings_per_day' => 12,
                'advance_booking_days' => 45,
                'booking_buffer_minutes' => 15,
                'auto_confirm_bookings' => 1
            ],
            [
                'name' => 'Home Improvement Experts',
                'slug' => 'home-improvement-experts',
                'email' => 'info@homeexperts.com',
                'phone' => '(555) 234-5678',
                'address' => '456 Oak Ave, Los Angeles, CA 90211',
                'description' => 'Reliable home improvement services for all your needs. Quality workmanship guaranteed.',
                'website' => 'https://homeexperts.com',
                'rating' => 4.6,
                'total_reviews' => 89,
                'category' => 'standard',
                'available_days' => '1,2,3,4,5,6',
                'available_hours_start' => '09:00:00',
                'available_hours_end' => '17:00:00',
                'time_slot_duration' => 30,
                'max_bookings_per_day' => 8,
                'advance_booking_days' => 30,
                'booking_buffer_minutes' => 30
            ],
            [
                'name' => 'Pro Remodeling Solutions',
                'slug' => 'pro-remodeling-solutions',
                'email' => 'hello@proremodeling.com',
                'phone' => '(555) 345-6789',
                'address' => '789 Pine St, Los Angeles, CA 90212',
                'description' => 'Professional remodeling solutions with modern approach and innovative designs.',
                'website' => 'https://proremodeling.com',
                'rating' => 4.7,
                'total_reviews' => 203,
                'category' => 'premium',
                'available_days' => '1,2,3,4,5',
                'available_hours_start' => '10:00:00',
                'available_hours_end' => '19:00:00',
                'time_slot_duration' => 60,
                'max_bookings_per_day' => 6,
                'advance_booking_days' => 60,
                'booking_buffer_minutes' => 0,
                'auto_confirm_bookings' => 0
            ]
        ];
        
        foreach ($default_companies as $company) {
            $wpdb->insert(self::$tables['companies'], $company);
        }
        
        // Default settings
        $default_settings = [
            [
                'setting_key' => 'general_business_name',
                'setting_value' => 'Home Services Booking',
                'category' => 'general',
                'description' => 'Business name displayed on booking forms'
            ],
            [
                'setting_key' => 'email_from_name',
                'setting_value' => 'Home Services Team',
                'category' => 'email',
                'description' => 'Default from name for emails'
            ],
            [
                'setting_key' => 'email_from_address',
                'setting_value' => 'noreply@homeservices.com',
                'category' => 'email',
                'description' => 'Default from email address'
            ],
            [
                'setting_key' => 'booking_confirmation_auto_send',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'category' => 'email',
                'description' => 'Automatically send booking confirmation emails'
            ],
            [
                'setting_key' => 'booking_reminder_hours_before',
                'setting_value' => '24',
                'setting_type' => 'number',
                'category' => 'email',
                'description' => 'Hours before appointment to send reminder'
            ],
            [
                'setting_key' => 'admin_notification_email',
                'setting_value' => function_exists('get_option') ? get_option('admin_email', 'admin@example.com') : 'admin@example.com',
                'category' => 'email',
                'description' => 'Email address for admin notifications'
            ]
        ];
        
        foreach ($default_settings as $setting) {
            $wpdb->insert(self::$tables['settings'], $setting);
        }
    }
    
    /**
     * Get table name by key
     */
    public static function get_table($key) {
        self::init_tables();
        return isset(self::$tables[$key]) ? self::$tables[$key] : null;
    }
    
    /**
     * Get all companies with enhanced data
     */
    public static function get_companies($args = []) {
        global $wpdb;
        self::init_tables();
        
        $instance = self::get_instance();
        
        $defaults = [
            'status' => 'active',
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => null,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Create cache key based on arguments
        $cache_key = 'companies_' . md5(serialize($args));
        
        return $instance->get_cached($cache_key, function() use ($wpdb, $args) {
            $where = [];
            if ($args['status']) {
                $where[] = $wpdb->prepare("status = %s", $args['status']);
            }
            
            $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            $order_clause = sprintf('ORDER BY %s %s', $args['orderby'], $args['order']);
            $limit_clause = $args['limit'] ? $wpdb->prepare('LIMIT %d OFFSET %d', $args['limit'], $args['offset']) : '';
            
            $sql = "SELECT * FROM " . self::$tables['companies'] . " $where_clause $order_clause $limit_clause";
            
            return $wpdb->get_results($sql);
        });
    }
    
    /**
     * Get company by ID or slug
     */
    public static function get_company($identifier) {
        global $wpdb;
        self::init_tables();
        
        if (is_numeric($identifier)) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . self::$tables['companies'] . " WHERE id = %d",
                $identifier
            ));
        } else {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . self::$tables['companies'] . " WHERE slug = %s",
                $identifier
            ));
        }
    }
    
    /**
     * Create a new company
     */
    public static function create_company($data) {
        global $wpdb;
        self::init_tables();
        
        // Generate slug from name if not provided
        if (empty($data['slug'])) {
            $data['slug'] = sanitize_title($data['name']);
        }
        
        // Ensure unique slug
        $original_slug = $data['slug'];
        $counter = 1;
        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM " . self::$tables['companies'] . " WHERE slug = %s", $data['slug']))) {
            $data['slug'] = $original_slug . '-' . $counter;
            $counter++;
        }
        
        $company_data = [
            'name' => sanitize_text_field($data['name']),
            'slug' => $data['slug'],
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'address' => sanitize_textarea_field($data['address'] ?? ''),
            'city' => sanitize_text_field($data['city'] ?? ''),
            'state' => sanitize_text_field($data['state'] ?? ''),
            'zip_code' => sanitize_text_field($data['zip_code'] ?? ''),
            'website' => esc_url_raw($data['website'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'available_hours' => sanitize_text_field($data['available_hours'] ?? '9:00 AM - 5:00 PM'),
            'service_areas' => sanitize_textarea_field($data['service_areas'] ?? ''),
            'pricing_info' => sanitize_textarea_field($data['pricing_info'] ?? ''),
            'status' => in_array($data['status'] ?? 'active', ['active', 'inactive']) ? $data['status'] : 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert(self::$tables['companies'], $company_data);
        
        if ($result === false) {
            return new WP_Error('db_insert_error', __('Failed to create company.', 'booking-system-pro'), $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update an existing company
     */
    public static function update_company($id, $data) {
        global $wpdb;
        self::init_tables();
        
        // Check if company exists
        $existing_company = self::get_company($id);
        if (!$existing_company) {
            return new WP_Error('company_not_found', __('Company not found.', 'booking-system-pro'));
        }
        
        // Generate slug from name if provided and different
        if (!empty($data['name']) && $data['name'] !== $existing_company->name) {
            if (empty($data['slug'])) {
                $data['slug'] = sanitize_title($data['name']);
            }
        }
        
        // Ensure unique slug if slug is being changed
        if (!empty($data['slug']) && $data['slug'] !== $existing_company->slug) {
            $original_slug = $data['slug'];
            $counter = 1;
            while ($wpdb->get_var($wpdb->prepare("SELECT id FROM " . self::$tables['companies'] . " WHERE slug = %s AND id != %d", $data['slug'], $id))) {
                $data['slug'] = $original_slug . '-' . $counter;
                $counter++;
            }
        }
        
        $company_data = [];
        
        // Only update provided fields
        if (isset($data['name'])) $company_data['name'] = sanitize_text_field($data['name']);
        if (isset($data['slug'])) $company_data['slug'] = $data['slug'];
        if (isset($data['email'])) $company_data['email'] = sanitize_email($data['email']);
        if (isset($data['phone'])) $company_data['phone'] = sanitize_text_field($data['phone']);
        if (isset($data['address'])) $company_data['address'] = sanitize_textarea_field($data['address']);
        if (isset($data['city'])) $company_data['city'] = sanitize_text_field($data['city']);
        if (isset($data['state'])) $company_data['state'] = sanitize_text_field($data['state']);
        if (isset($data['zip_code'])) $company_data['zip_code'] = sanitize_text_field($data['zip_code']);
        if (isset($data['website'])) $company_data['website'] = esc_url_raw($data['website']);
        if (isset($data['description'])) $company_data['description'] = sanitize_textarea_field($data['description']);
        if (isset($data['available_hours'])) $company_data['available_hours'] = sanitize_text_field($data['available_hours']);
        if (isset($data['service_areas'])) $company_data['service_areas'] = sanitize_textarea_field($data['service_areas']);
        if (isset($data['pricing_info'])) $company_data['pricing_info'] = sanitize_textarea_field($data['pricing_info']);
        if (isset($data['status']) && in_array($data['status'], ['active', 'inactive'])) {
            $company_data['status'] = $data['status'];
        }
        
        if (!empty($company_data)) {
            $company_data['updated_at'] = current_time('mysql');
            
            $result = $wpdb->update(
                self::$tables['companies'],
                $company_data,
                ['id' => $id],
                null,
                ['%d']
            );
            
            if ($result === false) {
                return new WP_Error('db_update_error', __('Failed to update company.', 'booking-system-pro'), $wpdb->last_error);
            }
        }
        
        return true;
    }
    
    /**
     * Delete a company
     */
    public static function delete_company($id) {
        global $wpdb;
        self::init_tables();
        
        // Check if company exists
        $company = self::get_company($id);
        if (!$company) {
            return new WP_Error('company_not_found', __('Company not found.', 'booking-system-pro'));
        }
        
        // Check if company has bookings
        $booking_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$tables['bookings'] . " WHERE company_id = %d",
            $id
        ));
        
        if ($booking_count > 0) {
            return new WP_Error('company_has_bookings', __('Cannot delete company with existing bookings. Please reassign or delete bookings first.', 'booking-system-pro'));
        }
        
        // Delete company
        $result = $wpdb->delete(
            self::$tables['companies'],
            ['id' => $id],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_delete_error', __('Failed to delete company.', 'booking-system-pro'), $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Toggle company status
     */
    public static function toggle_company_status($id) {
        global $wpdb;
        
        $company = self::get_company($id);
        if (!$company) {
            return new WP_Error('company_not_found', __('Company not found.', 'booking-system-pro'));
        }
        
        $new_status = $company->status === 'active' ? 'inactive' : 'active';
        
        return self::update_company($id, ['status' => $new_status]);
    }
    
    /**
     * Create or update customer
     */
    public static function create_or_update_customer($data) {
        global $wpdb;
        self::init_tables();
        
        // Check if customer exists by email
        $existing_customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::$tables['customers'] . " WHERE email = %s",
            $data['email']
        ));
        
        $customer_data = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip_code' => $data['zip_code'] ?? null
        ];
        
        if ($existing_customer) {
            // Update existing customer
            $wpdb->update(
                self::$tables['customers'],
                $customer_data,
                ['id' => $existing_customer->id]
            );
            return $existing_customer->id;
        } else {
            // Create new customer
            $result = $wpdb->insert(self::$tables['customers'], $customer_data);
            return $result !== false ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Create comprehensive booking record
     */
    public static function create_booking($data) {
        global $wpdb;
        self::init_tables();
        
        // Generate unique booking number
        $booking_number = self::generate_booking_number();
        
        // Prepare booking data
        $booking_data = [
            'booking_number' => $booking_number,
            'customer_id' => $data['customer_id'],
            'company_id' => $data['company_id'],
            'service_id' => $data['service_id'] ?? null,
            'booking_date' => $data['booking_date'],
            'booking_time' => $data['booking_time'],
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'service_type' => $data['service_type'],
            'special_requests' => $data['special_requests'] ?? '',
            'source' => $data['source'] ?? 'website',
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'ip_address' => $data['ip_address'] ?? self::get_user_ip(),
            'user_agent' => $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            'form_data' => isset($data['form_data']) ? json_encode($data['form_data']) : null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null
        ];
        
        // Insert booking
        $result = $wpdb->insert(self::$tables['bookings'], $booking_data);
        
        if ($result !== false) {
            $booking_id = $wpdb->insert_id;
            
            // Create WordPress post for admin interface
            $post_id = self::create_booking_post($booking_id, $booking_data);
            
            // Update booking with post ID
            if ($post_id) {
                $wpdb->update(
                    self::$tables['bookings'],
                    ['wp_post_id' => $post_id],
                    ['id' => $booking_id]
                );
            }
            
            // Clear dashboard cache when new booking is created
            $instance = self::get_instance();
            $instance->clear_cache('dashboard_stats');
            $instance->clear_cache_by_prefix('bookings_');
            
            return $booking_id;
        }
        
        return false;
    }
    
    /**
     * Get booking by ID (WordPress post)
     */
    public function get_booking($booking_id) {
        $post = get_post($booking_id);
        
        if (!$post || $post->post_type !== 'bsp_booking') {
            return false;
        }
        
        // Get all meta data for the booking
        $booking = [
            'id' => $post->ID,
            'customer_name' => get_post_meta($booking_id, '_customer_name', true),
            'customer_email' => get_post_meta($booking_id, '_customer_email', true),
            'customer_phone' => get_post_meta($booking_id, '_customer_phone', true),
            'service_id' => get_post_meta($booking_id, '_service_type', true),
            'service_name' => get_post_meta($booking_id, '_service_type', true),
            'company_id' => get_post_meta($booking_id, '_company_name', true),
            'company_name' => get_post_meta($booking_id, '_company_name', true),
            'appointment_date' => get_post_meta($booking_id, '_booking_date', true),
            'appointment_time' => get_post_meta($booking_id, '_booking_time', true),
            'customer_address' => get_post_meta($booking_id, '_customer_address', true),
            'zip_code' => get_post_meta($booking_id, '_zip_code', true),
            'appointments' => get_post_meta($booking_id, '_appointments', true),
            'notes' => $post->post_content,
            'created_at' => $post->post_date,
            'status' => 'pending', // Default status
            // Service-specific fields
            'roof_zip' => get_post_meta($booking_id, '_roof_zip', true),
            'windows_zip' => get_post_meta($booking_id, '_windows_zip', true),
            'bathroom_zip' => get_post_meta($booking_id, '_bathroom_zip', true),
            'siding_zip' => get_post_meta($booking_id, '_siding_zip', true),
            'kitchen_zip' => get_post_meta($booking_id, '_kitchen_zip', true),
            'decks_zip' => get_post_meta($booking_id, '_decks_zip', true),
            'adu_zip' => get_post_meta($booking_id, '_adu_zip', true),
            'roof_action' => get_post_meta($booking_id, '_roof_action', true),
            'roof_material' => get_post_meta($booking_id, '_roof_material', true),
            'windows_action' => get_post_meta($booking_id, '_windows_action', true),
            'windows_replace_qty' => get_post_meta($booking_id, '_windows_replace_qty', true),
            'windows_repair_needed' => get_post_meta($booking_id, '_windows_repair_needed', true),
            'bathroom_option' => get_post_meta($booking_id, '_bathroom_option', true),
            'siding_option' => get_post_meta($booking_id, '_siding_option', true),
            'siding_material' => get_post_meta($booking_id, '_siding_material', true),
            'kitchen_action' => get_post_meta($booking_id, '_kitchen_action', true),
            'kitchen_component' => get_post_meta($booking_id, '_kitchen_component', true),
            'decks_action' => get_post_meta($booking_id, '_decks_action', true),
            'decks_material' => get_post_meta($booking_id, '_decks_material', true),
            'adu_action' => get_post_meta($booking_id, '_adu_action', true),
            'adu_type' => get_post_meta($booking_id, '_adu_type', true)
        ];
        
        // Get status from taxonomy
        $status_terms = wp_get_post_terms($booking_id, 'bsp_booking_status');
        if (!is_wp_error($status_terms) && !empty($status_terms)) {
            $booking['status'] = $status_terms[0]->slug;
        }
        
        return $booking;
    }
    
    /**
     * Get available services
     */
    public function get_services() {
        $cache_key = 'services_list';
        
        return $this->get_cached($cache_key, function() {
            // Return predefined services for now
            return [
                ['id' => 'roof', 'name' => 'Roof'],
                ['id' => 'windows', 'name' => 'Windows'],
                ['id' => 'bathroom', 'name' => 'Bathroom'],
                ['id' => 'siding', 'name' => 'Siding'],
                ['id' => 'kitchen', 'name' => 'Kitchen'],
                ['id' => 'decks', 'name' => 'Decks']
            ];
        });
    }
    
    /**
     * Generate unique booking number
     */
    private static function generate_booking_number() {
        $prefix = 'BSP';
        $timestamp = time();
        $random = sprintf('%04d', random_int(1000, 9999));
        return $prefix . '-' . $timestamp . '-' . $random;
    }
    
    /**
     * Create WordPress post for booking (for admin interface)
     */
    private static function create_booking_post($booking_id, $booking_data) {
        $post_data = [
            'post_title' => sprintf('Booking #%s - %s', $booking_data['booking_number'], $booking_data['service_type']),
            'post_type' => 'bsp_booking',
            'post_status' => 'publish',
            'meta_input' => [
                '_bsp_booking_id' => $booking_id,
                '_bsp_booking_number' => $booking_data['booking_number'],
                '_bsp_customer_id' => $booking_data['customer_id'],
                '_bsp_company_id' => $booking_data['company_id'],
                '_bsp_booking_date' => $booking_data['booking_date'],
                '_bsp_booking_time' => $booking_data['booking_time'],
                '_bsp_service_type' => $booking_data['service_type']
            ]
        ];
        
        return wp_insert_post($post_data);
    }
    
    /**
     * Get user IP address
     */
    private static function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '';
        }
    }
    
    /**
     * Get setting by key
     */
    public static function get_setting($key, $default = null) {
        global $wpdb;
        
        $instance = self::get_instance();
        $cache_key = 'setting_' . $key;
        
        return $instance->get_cached($cache_key, function() use ($wpdb, $key, $default) {
            $setting = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . self::$tables['settings'] . " WHERE setting_key = %s",
                $key
            ));
            
            if ($setting) {
                // Handle different data types
                switch ($setting->setting_type) {
                    case 'boolean':
                        return (bool) $setting->setting_value;
                    case 'number':
                        return is_numeric($setting->setting_value) ? (float) $setting->setting_value : $default;
                    case 'array':
                    case 'object':
                        return json_decode($setting->setting_value, true);
                    default:
                        return $setting->setting_value;
                }
            }
            
            return $default;
        });
    }
    
    /**
     * Update setting
     */
    public static function update_setting($key, $value, $type = 'string') {
        global $wpdb;
        
        $instance = self::get_instance();
        
        // Convert value based on type
        switch ($type) {
            case 'boolean':
                $value = $value ? '1' : '0';
                break;
            case 'array':
            case 'object':
                $value = json_encode($value);
                break;
            default:
                $value = (string) $value;
        }
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::$tables['settings'] . " WHERE setting_key = %s",
            $key
        ));
        
        // Clear the specific setting cache
        $instance->clear_cache('setting_' . $key);
        
        if ($existing) {
            return $wpdb->update(
                self::$tables['settings'],
                ['setting_value' => $value, 'setting_type' => $type],
                ['setting_key' => $key]
            );
        } else {
            return $wpdb->insert(
                self::$tables['settings'],
                [
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'setting_type' => $type
                ]
            );
        }
    }
    
    /**
     * Generate availability slots for a company and date range
     */
    public static function generate_availability($company_id, $date_from, $date_to) {
        global $wpdb;
        
        $company = self::get_company($company_id);
        if (!$company) {
            return false;
        }
        
        $availability = [];
        $current_date = new DateTime($date_from);
        $end_date = new DateTime($date_to);
        
        // Parse available days (1=Monday, 7=Sunday)
        $available_days = array_map('intval', explode(',', $company->available_days));
        
        while ($current_date <= $end_date) {
            $day_of_week = (int) $current_date->format('N'); // 1=Monday, 7=Sunday
            
            if (in_array($day_of_week, $available_days)) {
                $date_str = $current_date->format('Y-m-d');
                $slots = self::generate_time_slots($company, $date_str);
                
                if (!empty($slots)) {
                    $availability[$date_str] = [
                        'date' => $date_str,
                        'day_name' => $current_date->format('D'),
                        'day_number' => $current_date->format('j'),
                        'slots' => $slots,
                        'company' => [
                            'id' => $company->id,
                            'name' => $company->name,
                            'phone' => $company->phone
                        ]
                    ];
                }
            }
            
            $current_date->modify('+1 day');
        }
        
        return $availability;
    }
    
    /**
     * Generate time slots for a specific company and date
     */
    private static function generate_time_slots($company, $date) {
        global $wpdb;
        
        $slots = [];
        $start_time = new DateTime($date . ' ' . $company->available_hours_start);
        $end_time = new DateTime($date . ' ' . $company->available_hours_end);
        $duration = $company->time_slot_duration;
        
        // Get booked slots for this date and company
        $booked_slots = $wpdb->get_col($wpdb->prepare(
            "SELECT time_slot FROM " . self::$tables['availability'] . " WHERE company_id = %d AND date = %s AND is_booked = 1",
            $company->id, $date
        ));
        
        // Generate available slots
        while ($start_time < $end_time) {
            $time_slot = $start_time->format('H:i:s');
            $time_display = $start_time->format('H:i');
            
            $slots[] = [
                'time' => $time_display,
                'formatted' => $start_time->format('g:i A'),
                'available' => !in_array($time_slot, $booked_slots)
            ];
            
            $start_time->modify('+' . $duration . ' minutes');
        }
        
        return $slots;
    }
    
    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats() {
        $cache_key = 'dashboard_stats';
        
        // Use shorter cache duration for stats (2 minutes)
        $original_duration = self::$cache_duration;
        self::$cache_duration = 120;
        
        $stats = $this->get_cached($cache_key, function() {
            global $wpdb;
            
            // Get total bookings
            $total_bookings = wp_count_posts('bsp_booking');
            $total_count = 0;
            if ($total_bookings) {
                foreach ($total_bookings as $status => $count) {
                    if ($status !== 'trash') {
                        $total_count += $count;
                    }
                }
            }
            
            // Get this month's bookings
            $this_month_start = date('Y-m-01');
            $this_month_end = date('Y-m-t');
            $this_month_posts = get_posts([
                'post_type' => 'bsp_booking',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => '_booking_date',
                        'value' => [$this_month_start, $this_month_end],
                        'compare' => 'BETWEEN',
                        'type' => 'DATE'
                    ]
                ]
            ]);
            
            // Get pending bookings
            $pending_posts = get_posts([
                'post_type' => 'bsp_booking',
                'posts_per_page' => -1,
                'tax_query' => [
                    [
                        'taxonomy' => 'bsp_booking_status',
                        'field' => 'slug',
                        'terms' => 'pending'
                    ]
                ]
            ]);
            
            // Calculate total revenue
            $revenue_query = new WP_Query([
                'post_type' => 'bsp_booking',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => '_total_cost',
                        'compare' => 'EXISTS'
                    ]
                ]
            ]);
            
            $total_revenue = 0;
            if ($revenue_query->have_posts()) {
                while ($revenue_query->have_posts()) {
                    $revenue_query->the_post();
                    $cost = get_post_meta(get_the_ID(), '_total_cost', true);
                    $total_revenue += floatval($cost);
                }
                wp_reset_postdata();
            }
            
            // Get active companies count
            $active_companies = $wpdb->get_var(
                "SELECT COUNT(*) FROM " . self::$tables['companies'] . " WHERE status = 'active'"
            );
            
            // Get service types count
            $service_types = wp_count_terms([
                'taxonomy' => 'bsp_service_type',
                'hide_empty' => false
            ]);
            
            // Generate chart data for the last 7 days
            $chart_labels = [];
            $chart_bookings = [];
            $chart_revenue = [];
            
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $chart_labels[] = date('M j', strtotime($date));
                
                // Count bookings for this date
                $day_bookings = get_posts([
                    'post_type' => 'bsp_booking',
                    'posts_per_page' => -1,
                    'meta_query' => [
                        [
                            'key' => '_booking_date',
                            'value' => $date,
                            'compare' => '='
                        ]
                    ]
                ]);
                
                $chart_bookings[] = count($day_bookings);
                
                // Calculate revenue for this date
                $day_revenue = 0;
                foreach ($day_bookings as $booking) {
                    $cost = get_post_meta($booking->ID, '_total_cost', true);
                    $day_revenue += floatval($cost);
                }
                $chart_revenue[] = $day_revenue;
            }
            
            return [
                'total_bookings' => $total_count,
                'this_month_bookings' => count($this_month_posts),
                'pending_bookings' => count($pending_posts),
                'total_revenue' => $total_revenue,
                'active_companies' => intval($active_companies),
                'service_types' => intval($service_types),
                'chart_labels' => $chart_labels,
                'chart_bookings' => $chart_bookings,
                'chart_revenue' => $chart_revenue
            ];
        });
        
        // Restore original cache duration
        self::$cache_duration = $original_duration;
        
        return $stats;
    }
    
    /**
     * Get recent bookings for dashboard
     */
    public function get_recent_bookings($limit = 5) {
        $bookings = get_posts([
            'post_type' => 'bsp_booking',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish'
        ]);
        
        return $bookings;
    }
    
    /**
     * Update company availability settings
     */
    public function update_company_availability($company_id, $availability_data) {
        // Map the admin interface data to correct database columns
        $update_data = [
            'status' => isset($availability_data['is_active']) && $availability_data['is_active'] ? 'active' : 'inactive',
            'available_hours_start' => sanitize_text_field($availability_data['start_time']) . ':00',
            'available_hours_end' => sanitize_text_field($availability_data['end_time']) . ':00',
            'updated_at' => current_time('mysql')
        ];

        // Clear cache when updating data
        $this->clear_cache_by_prefix('company_');
        $this->clear_cache_by_prefix('bookings_');

        return $this->wpdb->update(
            self::$tables['companies'],
            $update_data,
            ['id' => intval($company_id)],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * Cache management methods for performance optimization
     */
    
    /**
     * Get cached result or execute query and cache it
     */
    private function get_cached($cache_key, $callback) {
        // Check if cache exists and is not expired
        if (isset(self::$cache[$cache_key]) && 
            isset(self::$cache_expiry[$cache_key]) && 
            time() < self::$cache_expiry[$cache_key]) {
            return self::$cache[$cache_key];
        }
        
        // Execute callback and cache result
        $result = call_user_func($callback);
        $this->set_cache($cache_key, $result);
        
        return $result;
    }
    
    /**
     * Set cache with expiry
     */
    private function set_cache($key, $value) {
        self::$cache[$key] = $value;
        self::$cache_expiry[$key] = time() + self::$cache_duration;
    }
    
    /**
     * Clear specific cache key
     */
    private function clear_cache($key) {
        unset(self::$cache[$key]);
        unset(self::$cache_expiry[$key]);
    }
    
    /**
     * Clear cache by prefix (e.g., 'bookings_', 'company_')
     */
    private function clear_cache_by_prefix($prefix) {
        foreach (array_keys(self::$cache) as $key) {
            if (strpos($key, $prefix) === 0) {
                $this->clear_cache($key);
            }
        }
    }
    
    /**
     * Clear all cache
     */
    public function clear_all_cache() {
        self::$cache = [];
        self::$cache_expiry = [];
    }
}