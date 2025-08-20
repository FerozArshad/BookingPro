<?php
/**
 * Core Taxonomies for Booking System Pro
 * 
 * Only essential taxonomies for core booking functionality:
 * - Booking Status (pending, confirmed, completed, cancelled)
 */

if (!defined('ABSPATH')) exit;

class BSP_Taxonomies {
    
    public function __construct() {
        add_action('init', [$this, 'register_taxonomies'], 0);
        add_action('admin_init', [$this, 'populate_default_terms']);
    }
    
    /**
     * Register core taxonomies
     */
    public function register_taxonomies() {
        $this->register_booking_status_taxonomy();
    }
    
    /**
     * Register Booking Status taxonomy
     */
    private function register_booking_status_taxonomy() {
        $labels = [
            'name'                       => _x('Booking Status', 'Taxonomy General Name', 'booking-system-pro'),
            'singular_name'              => _x('Status', 'Taxonomy Singular Name', 'booking-system-pro'),
            'menu_name'                  => __('Booking Status', 'booking-system-pro'),
            'all_items'                  => __('All Statuses', 'booking-system-pro'),
            'edit_item'                  => __('Edit Status', 'booking-system-pro'),
            'update_item'                => __('Update Status', 'booking-system-pro'),
            'add_new_item'               => __('Add New Status', 'booking-system-pro'),
            'new_item_name'              => __('New Status Name', 'booking-system-pro'),
            'not_found'                  => __('No statuses found.', 'booking-system-pro'),
        ];
        
        $args = [
            'labels'                     => $labels,
            'hierarchical'               => false,
            'public'                     => false,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => false,
            'show_tagcloud'              => false,
            'show_in_rest'               => false,
            'capabilities'               => [
                'manage_terms'           => 'manage_options',
                'edit_terms'             => 'manage_options',
                'delete_terms'           => 'manage_options',
                'assign_terms'           => 'edit_posts'
            ]
        ];
        
        register_taxonomy('bsp_booking_status', ['bsp_booking'], $args);
    }
    


    /**
     * Register Service Type taxonomy
     */
    private function register_service_type_taxonomy() {
        $labels = [
            'name'                       => _x('Service Types', 'Taxonomy General Name', 'booking-system-pro'),
            'singular_name'              => _x('Service Type', 'Taxonomy Singular Name', 'booking-system-pro'),
            'menu_name'                  => __('Service Types', 'booking-system-pro'),
            'all_items'                  => __('All Service Types', 'booking-system-pro'),
            'new_item_name'              => __('New Service Type Name', 'booking-system-pro'),
            'add_new_item'               => __('Add New Service Type', 'booking-system-pro'),
            'edit_item'                  => __('Edit Service Type', 'booking-system-pro'),
            'update_item'                => __('Update Service Type', 'booking-system-pro'),
            'view_item'                  => __('View Service Type', 'booking-system-pro'),
            'separate_items_with_commas' => __('Separate service types with commas', 'booking-system-pro'),
            'add_or_remove_items'        => __('Add or remove service types', 'booking-system-pro'),
            'choose_from_most_used'      => __('Choose from the most used', 'booking-system-pro'),
            'popular_items'              => __('Popular Service Types', 'booking-system-pro'),
            'search_items'               => __('Search Service Types', 'booking-system-pro'),
            'not_found'                  => __('Not Found', 'booking-system-pro'),
            'no_terms'                   => __('No service types', 'booking-system-pro'),
            'items_list'                 => __('Service types list', 'booking-system-pro'),
            'items_list_navigation'      => __('Service types list navigation', 'booking-system-pro'),
        ];

        $args = [
            'labels'                     => $labels,
            'hierarchical'               => true,
            'public'                     => false,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => false,
            'show_tagcloud'              => false,
            'show_in_rest'               => true,
            'meta_box_cb'                => 'post_categories_meta_box',
            'rewrite'                    => false,
        ];

        register_taxonomy('bsp_service_type', ['bsp_booking'], $args);
    }

    
    /**
     * Register Company Category taxonomy
     */
    private function register_company_category_taxonomy() {
        $labels = [
            'name'                       => _x('Company Categories', 'Taxonomy General Name', 'booking-system-pro'),
            'singular_name'              => _x('Company Category', 'Taxonomy Singular Name', 'booking-system-pro'),
            'menu_name'                  => __('Company Categories', 'booking-system-pro'),
            'all_items'                  => __('All Categories', 'booking-system-pro'),
            'parent_item'                => __('Parent Category', 'booking-system-pro'),
            'parent_item_colon'          => __('Parent Category:', 'booking-system-pro'),
            'new_item_name'              => __('New Category Name', 'booking-system-pro'),
            'add_new_item'               => __('Add New Category', 'booking-system-pro'),
            'edit_item'                  => __('Edit Category', 'booking-system-pro'),
            'update_item'                => __('Update Category', 'booking-system-pro'),
            'view_item'                  => __('View Category', 'booking-system-pro'),
            'separate_items_with_commas' => __('Separate categories with commas', 'booking-system-pro'),
            'add_or_remove_items'        => __('Add or remove categories', 'booking-system-pro'),
            'choose_from_most_used'      => __('Choose from the most used', 'booking-system-pro'),
            'popular_items'              => __('Popular Categories', 'booking-system-pro'),
            'search_items'               => __('Search Categories', 'booking-system-pro'),
            'not_found'                  => __('Not Found', 'booking-system-pro'),
            'no_terms'                   => __('No categories', 'booking-system-pro'),
            'items_list'                 => __('Categories list', 'booking-system-pro'),
            'items_list_navigation'      => __('Categories list navigation', 'booking-system-pro'),
        ];

        $args = [
            'labels'                     => $labels,
            'hierarchical'               => true,
            'public'                     => false,
            'show_ui'                    => true,
            'show_admin_column'          => false,
            'show_in_nav_menus'          => false,
            'show_tagcloud'              => false,
            'show_in_rest'               => true,
            'rewrite'                    => false,
        ];

        register_taxonomy('bsp_company_category', ['bsp_booking'], $args);
    }
    
    /**
     * Populate default taxonomy terms
     */
    public function populate_default_terms() {
        if (get_option('bsp_taxonomies_populated')) {
            return;
        }
        
        // Default service types
        $service_types = [
            ['name' => 'Roofing', 'slug' => 'roofing'],
            ['name' => 'Siding', 'slug' => 'siding'],
            ['name' => 'Windows', 'slug' => 'windows'],
            ['name' => 'Gutters', 'slug' => 'gutters'],
            ['name' => 'Solar', 'slug' => 'solar'],
            ['name' => 'Insulation', 'slug' => 'insulation'],
            ['name' => 'HVAC', 'slug' => 'hvac'],
            ['name' => 'Consultation', 'slug' => 'consultation'],
        ];
        
        foreach ($service_types as $service) {
            if (!term_exists($service['slug'], 'bsp_service_type')) {
                wp_insert_term($service['name'], 'bsp_service_type', [
                    'slug' => $service['slug'],
                ]);
            }
        }
        
        // Default booking statuses
        $statuses = [
            ['name' => 'Pending', 'slug' => 'pending', 'color' => '#ffa500'],
            ['name' => 'Confirmed', 'slug' => 'confirmed', 'color' => '#28a745'],
            ['name' => 'In Progress', 'slug' => 'in-progress', 'color' => '#007bff'],
            ['name' => 'Completed', 'slug' => 'completed', 'color' => '#6c757d'],
            ['name' => 'Cancelled', 'slug' => 'cancelled', 'color' => '#dc3545'],
            ['name' => 'No Show', 'slug' => 'no-show', 'color' => '#e74c3c'],
        ];
        
        foreach ($statuses as $status) {
            if (!term_exists($status['slug'], 'bsp_booking_status')) {
                $term = wp_insert_term($status['name'], 'bsp_booking_status', [
                    'slug' => $status['slug'],
                ]);
                
                if (!is_wp_error($term)) {
                    update_term_meta($term['term_id'], 'status_color', $status['color']);
                }
            }
        }
        
        // Default company categories
        $company_categories = [
            ['name' => 'Primary Contractor', 'slug' => 'primary-contractor'],
            ['name' => 'Subcontractor', 'slug' => 'subcontractor'],
            ['name' => 'Supplier', 'slug' => 'supplier'],
            ['name' => 'Partner', 'slug' => 'partner'],
            ['name' => 'Lead Source', 'slug' => 'lead-source'],
        ];
        
        foreach ($company_categories as $category) {
            if (!term_exists($category['slug'], 'bsp_company_category')) {
                wp_insert_term($category['name'], 'bsp_company_category', [
                    'slug' => $category['slug'],
                ]);
            }
        }
        
        update_option('bsp_taxonomies_populated', true);
    }
    
    /**
     * Custom meta box for booking status with color indicators
     */
    public function booking_status_meta_box($post, $box) {
        $defaults = ['orderby' => 'name', 'hide_empty' => 0];
        if (!isset($box['args']) || !is_array($box['args'])) {
            $args = [];
        } else {
            $args = $box['args'];
        }
        
        $r = wp_parse_args($args, $defaults);
        $tax_name = esc_attr($box['id']);
        $taxonomy = get_taxonomy($tax_name);
        
        ?>
        <div id="taxonomy-<?php echo $tax_name; ?>" class="categorydiv">
            <div id="<?php echo $tax_name; ?>-all" class="tabs-panel">
                <?php
                $name = ($tax_name == 'category') ? 'post_category' : 'tax_input[' . $tax_name . ']';
                $terms = get_terms($tax_name, $r);
                $popular_ids = wp_popular_terms_checklist($tax_name);
                $selected_cats = wp_get_object_terms($post->ID, $tax_name, ['fields' => 'ids']);
                
                foreach ($terms as $term) {
                    $color = get_term_meta($term->term_id, 'status_color', true);
                    $selected = in_array($term->term_id, $selected_cats) ? 'checked="checked"' : '';
                    
                    echo '<label class="selectit" style="display: block; margin-bottom: 8px;">';
                    echo '<input value="' . $term->term_id . '" type="radio" name="' . $name . '[]" id="in-' . $tax_name . '-' . $term->term_id . '" ' . $selected . ' />';
                    
                    if ($color) {
                        echo '<span class="status-indicator" style="display: inline-block; width: 12px; height: 12px; background-color: ' . esc_attr($color) . '; border-radius: 50%; margin-right: 8px; vertical-align: middle;"></span>';
                    }
                    
                    echo esc_html($term->name);
                    echo '</label>';
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add taxonomy meta boxes to booking edit screen
     */
    public function add_taxonomy_meta_boxes() {
        add_meta_box(
            'bsp_service_type_meta_box',
            __('Service Type', 'booking-system-pro'),
            [$this, 'service_type_meta_box'],
            'bsp_booking',
            'side',
            'high'
        );
    }
    
    /**
     * Service type meta box
     */
    public function service_type_meta_box($post) {
        $taxonomy = 'bsp_service_type';
        $tax = get_taxonomy($taxonomy);
        $selected = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids']);
        
        ?>
        <div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">
            <?php
            $terms = get_terms($taxonomy, ['hide_empty' => 0]);
            
            echo '<div style="max-height: 200px; overflow-y: auto;">';
            foreach ($terms as $term) {
                $checked = in_array($term->term_id, $selected) ? 'checked="checked"' : '';
                echo '<label class="selectit" style="display: block;">';
                echo '<input value="' . $term->term_id . '" type="checkbox" name="tax_input[' . $taxonomy . '][]" ' . $checked . ' />';
                echo ' ' . esc_html($term->name);
                echo '</label>';
            }
            echo '</div>';
            ?>
        </div>
        <?php
    }
    
    /**
     * Add taxonomy columns to booking list
     */
    public function add_taxonomy_columns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            
            if ($key === 'title') {
                $new_columns['service_type'] = __('Service Type', 'booking-system-pro');
                $new_columns['booking_status'] = __('Status', 'booking-system-pro');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Populate taxonomy columns
     */
    public function populate_taxonomy_columns($column, $post_id) {
        switch ($column) {
            case 'service_type':
                $terms = get_the_terms($post_id, 'bsp_service_type');
                if ($terms && !is_wp_error($terms)) {
                    $service_links = [];
                    foreach ($terms as $term) {
                        $service_links[] = '<a href="' . admin_url('edit.php?post_type=bsp_booking&bsp_service_type=' . $term->slug) . '">' . esc_html($term->name) . '</a>';
                    }
                    echo implode(', ', $service_links);
                } else {
                    echo '<span style="color: #999;">' . __('No service type', 'booking-system-pro') . '</span>';
                }
                break;
                
            case 'booking_status':
                $terms = get_the_terms($post_id, 'bsp_booking_status');
                if ($terms && !is_wp_error($terms)) {
                    $term = array_shift($terms); // Get first status
                    $color = get_term_meta($term->term_id, 'status_color', true);
                    
                    echo '<span class="booking-status" style="display: inline-flex; align-items: center; gap: 6px;">';
                    if ($color) {
                        echo '<span style="display: inline-block; width: 10px; height: 10px; background-color: ' . esc_attr($color) . '; border-radius: 50%;"></span>';
                    }
                    echo '<a href="' . admin_url('edit.php?post_type=bsp_booking&bsp_booking_status=' . $term->slug) . '">' . esc_html($term->name) . '</a>';
                    echo '</span>';
                } else {
                    echo '<span style="color: #999;">' . __('No status', 'booking-system-pro') . '</span>';
                }
                break;
        }
    }
    
    /**
     * Get all service types
     */
    public static function get_service_types() {
        return get_terms([
            'taxonomy' => 'bsp_service_type',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
    }
    
    /**
     * Get all booking statuses
     */
    public static function get_booking_statuses() {
        return get_terms([
            'taxonomy' => 'bsp_booking_status',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
    }
    
    /**
     * Get company categories
     */
    public static function get_company_categories() {
        return get_terms([
            'taxonomy' => 'bsp_company_category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
    }
}
