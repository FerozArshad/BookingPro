<?php
/**
 * Add missing columns to wp_bsp_incomplete_leads table
 */

// Find WordPress root
$wp_config_paths = [
    __DIR__ . '/../../../wp-config.php',
    __DIR__ . '/../../../../wp-config.php',
    __DIR__ . '/../../../../../wp-config.php'
];

$wp_loaded = false;
foreach ($wp_config_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("Error: Could not find WordPress configuration.\n");
}

global $wpdb;

$table_name = $wpdb->prefix . 'bsp_incomplete_leads';

echo "Adding missing columns to {$table_name}...\n";

// Check if table exists
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

if (!$table_exists) {
    echo "Error: Table {$table_name} does not exist!\n";
    exit(1);
}

// Check current table structure
$columns = $wpdb->get_results("DESCRIBE {$table_name}");
$existing_columns = array_column($columns, 'Field');

echo "Current columns: " . implode(', ', $existing_columns) . "\n";

// Add customer_address column if it doesn't exist
if (!in_array('customer_address', $existing_columns)) {
    $result1 = $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN customer_address TEXT AFTER customer_phone");
    echo "customer_address column: " . ($result1 !== false ? "SUCCESS" : "FAILED: " . $wpdb->last_error) . "\n";
} else {
    echo "customer_address column: ALREADY EXISTS\n";
}

// Add city column if it doesn't exist
if (!in_array('city', $existing_columns)) {
    $result2 = $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN city VARCHAR(100) AFTER customer_address");
    echo "city column: " . ($result2 !== false ? "SUCCESS" : "FAILED: " . $wpdb->last_error) . "\n";
} else {
    echo "city column: ALREADY EXISTS\n";
}

// Add state column if it doesn't exist
if (!in_array('state', $existing_columns)) {
    $result3 = $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN state VARCHAR(100) AFTER city");
    echo "state column: " . ($result3 !== false ? "SUCCESS" : "FAILED: " . $wpdb->last_error) . "\n";
} else {
    echo "state column: ALREADY EXISTS\n";
}

// Show final table structure
echo "\nFinal table structure:\n";
$final_columns = $wpdb->get_results("DESCRIBE {$table_name}");
foreach ($final_columns as $column) {
    echo "  {$column->Field} ({$column->Type})\n";
}

echo "\nDatabase update completed!\n";
?>