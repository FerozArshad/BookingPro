<?php
class Booking_System_Pro {

    private static $instance = null;

    // Service configuration
    private $services = ['Roof','Windows','Bathroom','Siding','Kitchen'];
    private $companies = ['Top Remodeling Pro','RH Remodeling','Eco Green'];
    private $hours = [
        '12:00','12:30','13:00','13:30','14:00','14:30',
        '15:00','15:30','16:00','16:30','17:00','17:30',
        '18:00','18:30','19:00'
    ];

    // Get companies from database
    private function get_companies_data() {
        // Include database class if not already loaded
        if (!class_exists('Booking_System_Database')) {
            require_once BSP_PLUGIN_DIR . 'includes/class-database.php';
        }
        
        $companies = Booking_System_Database::get_company_settings();
        $companies_data = array();
        
        foreach ($companies as $company) {
            $companies_data[] = array(
                'id' => $company->id,
                'name' => $company->name,
                'phone' => $company->phone,
                'address' => $company->address
            );
        }
        
        return $companies_data;
    }

    // Step definitions matching JavaScript configuration
    private $steps = [
        ['id'=>'service','type'=>'single-choice','question'=>'Which service are you interested in?','options'=>['Roof','Windows','Bathroom','Siding','Kitchen','Decks']],
        
        // Service-specific ZIP code steps (immediately after service selection)
        ['id'=>'roof_zip','depends_on'=>['service','Roof'],'type'=>'text','question'=>'Roof<br>Replacement','label'=>'Enter the location of your project'],
        ['id'=>'windows_zip','depends_on'=>['service','Windows'],'type'=>'text','question'=>'Windows<br>Replacement','label'=>'Enter the location of your project'],
        ['id'=>'bathroom_zip','depends_on'=>['service','Bathroom'],'type'=>'text','question'=>'Bathroom<br>Replacement','label'=>'Enter the location of your project'],
        ['id'=>'siding_zip','depends_on'=>['service','Siding'],'type'=>'text','question'=>'Siding<br>Replacement','label'=>'Enter the location of your project'],
        ['id'=>'kitchen_zip','depends_on'=>['service','Kitchen'],'type'=>'text','question'=>'Kitchen<br>Replacement','label'=>'Enter the location of your project'],
        ['id'=>'decks_zip','depends_on'=>['service','Decks'],'type'=>'text','question'=>'Decks<br>Replacement','label'=>'Enter the location of your project'],
        
        // Roof
        ['id'=>'roof_action','depends_on'=>['service','Roof'],'type'=>'single-choice','question'=>'Are you looking to replace or repair your roof?','options'=>['Replace','Repair']],
        ['id'=>'roof_material','depends_on'=>['service','Roof'],'type'=>'single-choice','question'=>'What kind of roof material?','options'=>['Asphalt','Metal','Tile','Flat']],
        
        // Windows
        ['id'=>'windows_action','depends_on'=>['service','Windows'],'type'=>'single-choice','question'=>'Are you replacing or repairing your windows?','options'=>['Replace','Repair']],
        ['id'=>'windows_replace_qty','depends_on'=>['windows_action','Replace'],'type'=>'single-choice','question'=>'How many windows?','options'=>['3–5','6–9','10+']],
        ['id'=>'windows_repair_needed','depends_on'=>['windows_action','Repair'],'type'=>'single-choice','question'=>'We don\'t have any window pros who service window repair projects.\nWould you want pricing to fully replace 3 or more window openings?','options'=>['Yes','No']],
        
        // Bathroom
        ['id'=>'bathroom_option','depends_on'=>['service','Bathroom'],'type'=>'single-choice','question'=>'Which bathroom service do you need?','options'=>['Replace bath/shower','Remove & install new bathroom','New walk-in tub']],
        
        // Siding
        ['id'=>'siding_option','depends_on'=>['service','Siding'],'type'=>'single-choice','question'=>'What type of siding work?','options'=>['Replace existing siding','Remove & replace siding','Add siding for a new addition','Install siding on a new home']],
        ['id'=>'siding_material','depends_on'=>['service','Siding'],'type'=>'single-choice','question'=>'What siding material?','options'=>['Wood composite','Aluminum','Fiber cement']],
        
        // Kitchen
        ['id'=>'kitchen_action','depends_on'=>['service','Kitchen'],'type'=>'single-choice','question'=>'Are you upgrading or repairing your kitchen?','options'=>['Upgrade','Repair']],
        ['id'=>'kitchen_component','depends_on'=>['service','Kitchen'],'type'=>'single-choice','question'=>'Which part of the kitchen?','options'=>['Countertops','Cabinets','Appliances','Islands']],
        
        // Decks
        ['id'=>'decks_action','depends_on'=>['service','Decks'],'type'=>'single-choice','question'=>'Are you looking to replace or repair your decks?','options'=>['Replace','Repair']],
        ['id'=>'decks_material','depends_on'=>['service','Decks'],'type'=>'single-choice','question'=>'What material?','options'=>['Cedar','Redwood']],
        
        // Common
        ['id'=>'full_name','type'=>'text','question'=>'Please enter your full name'],
        ['id'=>'address','type'=>'text','question'=>'What is your street address?'],
        ['id'=>'contact_info','type'=>'form','question'=>'We have matching Pros in [city]','fields'=>[
            ['name'=>'phone','label'=>'Cell Number'],
            ['name'=>'email','label'=>'Email Address'],
        ]],
        ['id'=>'schedule','type'=>'datetime','question'=>'Select a date and time'],
        ['id'=>'confirmation','type'=>'summary','question'=>'Please review & confirm your booking'],
    ];

    /** Singleton initialization */
    public static function init(){
        if(self::$instance===null) self::$instance=new self();
        return self::$instance;
    }

    /** Activation hook */
    public static function activate(){
        // Create database tables if needed
        self::create_tables();
        
        // Set default options
        add_option('bsp_version', '1.0.0');
        add_option('bsp_email_notifications', 'yes');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /** Deactivation hook */
    public static function deactivate(){
        // Clean up if needed
        flush_rewrite_rules();
    }

    /** Create custom database tables */
    private static function create_tables(){
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'booking_system_availability';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            company varchar(100) NOT NULL,
            date date NOT NULL,
            time time NOT NULL,
            is_booked tinyint(1) DEFAULT 0,
            booking_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY company_date (company, date),
            KEY booking_id (booking_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function __construct(){
        // Initialize hooks
        add_action( 'init', [ $this,'register_cpt' ] );
        add_action( 'wp_enqueue_scripts', [ $this,'enqueue_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this,'enqueue_admin_assets' ] );
        add_shortcode( 'booking_system_pro', [ $this,'shortcode_form' ] );
        
        // AJAX endpoints
        add_action( 'wp_ajax_bsp_get_slots', [ $this,'ajax_get_slots' ] );
        add_action( 'wp_ajax_nopriv_bsp_get_slots', [ $this,'ajax_get_slots' ] );
        add_action( 'wp_ajax_bsp_get_availability', [ $this,'ajax_get_availability' ] );
        add_action( 'wp_ajax_nopriv_bsp_get_availability', [ $this,'ajax_get_availability' ] );
        add_action( 'wp_ajax_bsp_submit_booking', [ $this,'ajax_submit_booking' ] );
        add_action( 'wp_ajax_nopriv_bsp_submit_booking', [ $this,'ajax_submit_booking' ] );
        
        // Admin customizations
        add_action( 'admin_menu', [ $this,'add_admin_menu' ] );
        add_filter( 'manage_bookings_pro_posts_columns', [ $this,'custom_columns' ] );
        add_action( 'manage_bookings_pro_posts_custom_column', [ $this,'custom_column_content' ], 10, 2 );
        add_action( 'add_meta_boxes', [ $this,'add_meta_boxes' ] );
        add_action( 'save_post', [ $this,'save_meta_boxes' ] );
    }

    /** Register BookingsPro Custom Post Type */
    public function register_cpt(){
        register_post_type( 'bookings_pro', [
            'labels' => [
                'name' => 'Bookings Pro',
                'singular_name' => 'Booking Pro',
                'all_items' => 'All Bookings',
                'add_new' => 'Add New Booking',
                'add_new_item' => 'Add New Booking',
                'edit_item' => 'Edit Booking',
                'new_item' => 'New Booking',
                'view_item' => 'View Booking',
                'search_items' => 'Search Bookings',
                'not_found' => 'No bookings found',
                'not_found_in_trash' => 'No bookings found in trash'
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => ['title', 'custom-fields'],
            'menu_icon' => 'dashicons-calendar-alt',
            'menu_position' => 25,
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false
        ]);
    }

    /** Enqueue frontend assets */
    public function enqueue_assets(){
        // Only enqueue on pages with shortcode
        if( $this->has_shortcode() ){
            // Figtree font
            wp_enqueue_style( 'bsp-fonts', 'https://fonts.googleapis.com/css2?family=Figtree:wght@400;600;700;800&display=swap' );
            
            // CSS
            wp_enqueue_style( 'bsp-css', BSP_PLUGIN_URL . 'assets/css/booking-system.css', [], '1.0.0' );
            
            // JavaScript
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'bsp-js', BSP_PLUGIN_URL . 'assets/js/booking-system.js', ['jquery'], '1.0.0', true );
            wp_enqueue_script( 'bsp-source-tracker', BSP_PLUGIN_URL . 'assets/js/source-tracker.js', [], BSP_VERSION, true );
            
            
            // Localize script
            wp_localize_script( 'bsp-js', 'BSP_Ajax', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce('bsp_booking_nonce'),
                'steps' => $this->steps,
                'companies' => $this->get_companies_data(),
                'hours' => $this->hours,
                'services' => $this->services,
            ]);
        }
    }

    /** Enqueue admin assets */
    public function enqueue_admin_assets($hook){
        if( 'edit.php' === $hook && isset($_GET['post_type']) && 'bookings_pro' === $_GET['post_type'] ){
            wp_enqueue_style( 'bsp-admin-css', BSP_PLUGIN_URL . 'assets/css/admin.css', [], '1.0.0' );
        }
    }

    /** Check if current page has shortcode */
    private function has_shortcode(){
        global $post;
        if( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'booking_system_pro' ) ){
            return true;
        }
        return false;
    }

    /** Shortcode output */
    public function shortcode_form($atts){
        $atts = shortcode_atts([
            'class' => ''
        ], $atts);
        
        ob_start();
        include BSP_PLUGIN_DIR . 'templates/booking-form.php';
        return ob_get_clean();
    }

    /** AJAX: Get available slots */
    public function ajax_get_slots(){
        check_ajax_referer('bsp_booking_nonce','nonce');
        
        $company = sanitize_text_field($_POST['company'] ?? '');
        
        if( empty($company) ){
            wp_send_json_error('Company required');
        }
        
        $slots = $this->calculate_available_slots($company);
        wp_send_json_success($slots);
    }

    /** Calculate available time slots */
    private function calculate_available_slots($company){
        global $wpdb;
        
        // Get booked slots from custom table
        $table_name = $wpdb->prefix . 'booking_system_availability';
        $booked_slots = $wpdb->get_results($wpdb->prepare(
            "SELECT date, time FROM $table_name WHERE company = %s AND is_booked = 1",
            $company
        ));
        
        // Convert to array for easy checking
        $booked_times = [];
        foreach($booked_slots as $slot){
            $booked_times[] = $slot->date . ' ' . $slot->time;
        }
        
        // Generate next 30 days
        $available = [];
        $start_date = new DateTime('today');
        $end_date = (new DateTime('today'))->modify('+30 days');
        
        for($date = clone $start_date; $date <= $end_date; $date->modify('+1 day')){
            $date_str = $date->format('Y-m-d');
            
            // Skip weekends (optional)
            if( in_array($date->format('w'), [0, 6]) ) continue;
            
            foreach($this->hours as $hour){
                $datetime_str = $date_str . ' ' . $hour;
                if( !in_array($datetime_str, $booked_times) ){
                    $available[$date_str][] = $hour;
                }
            }
        }
        
        return $available;
    }

    /** AJAX: Submit booking */
    public function ajax_submit_booking(){
        check_ajax_referer('bsp_booking_nonce','nonce');
        
        // Collect and sanitize data
        $data = [
            'service' => sanitize_text_field($_POST['service'] ?? ''),
            'full_name' => sanitize_text_field($_POST['full_name'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'address' => sanitize_textarea_field($_POST['address'] ?? ''),
            'company' => sanitize_text_field($_POST['company'] ?? ''),
            'selected_date' => sanitize_text_field($_POST['selected_date'] ?? ''),
            'selected_time' => sanitize_text_field($_POST['selected_time'] ?? ''),
            'zip_code' => sanitize_text_field($_POST['roof_zip'] ?? $_POST['windows_zip'] ?? $_POST['bathroom_zip'] ?? $_POST['siding_zip'] ?? $_POST['kitchen_zip'] ?? ''),
        ];
        
        // Add service-specific data
        foreach($this->steps as $step){
            if( isset($_POST[$step['id']]) ){
                $data[$step['id']] = sanitize_text_field($_POST[$step['id']]);
            }
        }
        
        // Validate required fields
        $required = ['service', 'full_name', 'phone', 'email', 'company', 'selected_date', 'selected_time'];
        foreach($required as $field){
            if( empty($data[$field]) ){
                wp_send_json_error("Field '$field' is required");
            }
        }
        
        // Create post
        $post_id = wp_insert_post([
            'post_type' => 'bookings_pro',
            'post_title' => sprintf('%s Booking - %s', $data['service'], $data['full_name']),
            'post_status' => 'publish',
            'post_content' => sprintf('Booking for %s service scheduled with %s on %s at %s', 
                $data['service'], $data['company'], $data['selected_date'], $data['selected_time']
            )
        ]);
        
        if( is_wp_error($post_id) ){
            wp_send_json_error('Failed to create booking');
        }
        
        // Save metadata
        foreach($data as $key => $value){
            update_post_meta($post_id, '_bsp_' . $key, $value);
        }
        
        // Mark slot as booked
        $this->mark_slot_booked($data['company'], $data['selected_date'], $data['selected_time'], $post_id);
        
        // Send notifications
        $this->send_booking_notifications($data, $post_id);
        
        wp_send_json_success([
            'message' => 'Booking confirmed successfully!',
            'booking_id' => $post_id
        ]);
    }

    /** Mark time slot as booked */
    private function mark_slot_booked($company, $date, $time, $booking_id){
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'booking_system_availability';
        
        $wpdb->insert($table_name, [
            'company' => $company,
            'date' => $date,
            'time' => $time,
            'is_booked' => 1,
            'booking_id' => $booking_id
        ]);
    }

    /** Send booking confirmation emails */
    private function send_booking_notifications($data, $booking_id){
        // Admin email
        $admin_email = get_option('admin_email');
        $admin_subject = sprintf('New %s Booking - %s', $data['service'], $data['full_name']);
        $admin_message = $this->get_admin_email_html($data, $booking_id);
        
        // Customer email
        $customer_subject = sprintf('Booking Confirmation - %s Service', $data['service']);
        $customer_message = $this->get_customer_email_html($data, $booking_id);
        
        // Set content type to HTML
        add_filter('wp_mail_content_type', function(){ return 'text/html'; });
        
        // Send emails
        wp_mail($admin_email, $admin_subject, $admin_message);
        wp_mail($data['email'], $customer_subject, $customer_message);
        
        // Reset content type
        remove_filter('wp_mail_content_type', '__return_html');
    }

    /** Generate admin email HTML */
    private function get_admin_email_html($data, $booking_id){
        $brand_color = '#79B62F';
        
        ob_start();
        include BSP_PLUGIN_DIR . 'templates/admin-email.php';
        return ob_get_clean();
    }

    /** Generate customer email HTML */
    private function get_customer_email_html($data, $booking_id){
        $brand_color = '#79B62F';
        
        ob_start();
        include BSP_PLUGIN_DIR . 'templates/customer-email.php';
        return ob_get_clean();
    }

    /** Add admin menu */
    public function add_admin_menu(){
        add_submenu_page(
            'edit.php?post_type=bookings_pro',
            'Booking Settings',
            'Settings',
            'manage_options',
            'bsp-settings',
            [$this, 'admin_settings_page']
        );
        
        add_submenu_page(
            'edit.php?post_type=bookings_pro',
            'Company Availability',
            'Availability',
            'manage_options',
            'bsp-availability',
            [$this, 'admin_availability_page']
        );
    }

    /** Admin settings page */
    public function admin_settings_page(){
        if( isset($_POST['submit']) ){
            update_option('bsp_email_notifications', $_POST['email_notifications'] ?? 'no');
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $email_notifications = get_option('bsp_email_notifications', 'yes');
        
        echo '<div class="wrap">';
        echo '<h1>Booking System Pro Settings</h1>';
        echo '<form method="post">';
        echo '<table class="form-table">';
        echo '<tr><th>Email Notifications</th>';
        echo '<td><label><input type="checkbox" name="email_notifications" value="yes" ' . checked($email_notifications, 'yes', false) . '> Send email notifications</label></td></tr>';
        echo '</table>';
        echo '<p class="submit"><input type="submit" name="submit" class="button-primary" value="Save Changes"></p>';
        echo '</form>';
        echo '</div>';
    }

    /** Admin availability settings page */
    public function admin_availability_page(){
        include BSP_PLUGIN_DIR . 'templates/admin-availability.php';
    }

    /** AJAX: Get real-time availability */
    public function ajax_get_availability(){
        check_ajax_referer('bsp_booking_nonce','nonce');
        
        // Include database class if not already loaded
        if (!class_exists('Booking_System_Database')) {
            require_once BSP_PLUGIN_DIR . 'includes/class-database.php';
        }
        
        $company_ids = array_map('intval', $_POST['company_ids'] ?? []);
        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-d'));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-d', strtotime($date_from . ' +30 days')));
        
        if (empty($company_ids)) {
            wp_send_json_error('No companies specified');
        }
        
        $availability = array();
        
        foreach ($company_ids as $company_id) {
            $company_availability = Booking_System_Database::get_company_availability($company_id, $date_from, $date_to);
            if ($company_availability) {
                $availability[$company_id] = $company_availability;
            }
        }
        
        wp_send_json_success($availability);
    }

    /** Customize admin columns */
    public function custom_columns($columns){
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['service'] = 'Service';
        $new_columns['customer'] = 'Customer';
        $new_columns['company'] = 'Company';
        $new_columns['schedule'] = 'Schedule';
        $new_columns['status'] = 'Status';
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }

    /** Custom column content */
    public function custom_column_content($column, $post_id){
        switch($column){
            case 'service':
                echo get_post_meta($post_id, '_bsp_service', true);
                break;
            case 'customer':
                $name = get_post_meta($post_id, '_bsp_full_name', true);
                $phone = get_post_meta($post_id, '_bsp_phone', true);
                echo $name . '<br><small>' . $phone . '</small>';
                break;
            case 'company':
                echo get_post_meta($post_id, '_bsp_company', true);
                break;
            case 'schedule':
                $date = get_post_meta($post_id, '_bsp_selected_date', true);
                $time = get_post_meta($post_id, '_bsp_selected_time', true);
                echo date('M j, Y', strtotime($date)) . '<br><small>' . date('g:i A', strtotime($time)) . '</small>';
                break;
            case 'status':
                echo '<span style="color: #79B62F; font-weight: bold;">Confirmed</span>';
                break;
        }
    }

    /** Add meta boxes */
    public function add_meta_boxes(){
        add_meta_box(
            'bsp_booking_details',
            'Booking Details',
            [$this, 'booking_details_meta_box'],
            'bookings_pro',
            'normal',
            'high'
        );
    }

    /** Booking details meta box */
    public function booking_details_meta_box($post){
        $data = [];
        $fields = ['service', 'full_name', 'phone', 'email', 'address', 'company', 'selected_date', 'selected_time', 'zip_code'];
        
        foreach($fields as $field){
            $data[$field] = get_post_meta($post->ID, '_bsp_' . $field, true);
        }
        
        echo '<style>
            .bsp-meta-table { width: 100%; }
            .bsp-meta-table th { text-align: left; padding: 10px; background: #f1f1f1; width: 200px; }
            .bsp-meta-table td { padding: 10px; }
            .bsp-meta-table tr:nth-child(even) { background: #f9f9f9; }
        </style>';
        
        echo '<table class="bsp-meta-table">';
        echo '<tr><th>Service</th><td>' . esc_html($data['service']) . '</td></tr>';
        echo '<tr><th>Customer Name</th><td>' . esc_html($data['full_name']) . '</td></tr>';
        echo '<tr><th>Phone</th><td>' . esc_html($data['phone']) . '</td></tr>';
        echo '<tr><th>Email</th><td>' . esc_html($data['email']) . '</td></tr>';
        echo '<tr><th>Address</th><td>' . esc_html($data['address']) . '</td></tr>';
        echo '<tr><th>ZIP Code</th><td>' . esc_html($data['zip_code']) . '</td></tr>';
        echo '<tr><th>Company</th><td>' . esc_html($data['company']) . '</td></tr>';
        echo '<tr><th>Scheduled Date</th><td>' . esc_html($data['selected_date']) . '</td></tr>';
        echo '<tr><th>Scheduled Time</th><td>' . esc_html($data['selected_time']) . '</td></tr>';
        echo '</table>';
    }

    /** Save meta boxes */
    public function save_meta_boxes($post_id){
        if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if( !current_user_can('edit_post', $post_id) ) return;
        if( get_post_type($post_id) !== 'bookings_pro' ) return;
        
        // Meta boxes are read-only, no save needed
    }
}