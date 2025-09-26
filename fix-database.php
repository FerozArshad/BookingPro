<?php
/**
 * Database fix script - Run this to update the incomplete_leads table structure
 * Add missing columns: customer_address, city, state
 */

// WordPress setup
if (!defined('ABSPATH')) {
    // Find WordPress config
    $wp_config_path = dirname(__FILE__) . '/../../../wp-config.php';
    if (file_exists($wp_config_path)) {
        require_once($wp_config_path);
    } else {
        die('WordPress not found');
    }
}

// Include the database class
require_once(dirname(__FILE__) . '/includes/class-database-unified.php');
require_once(dirname(__FILE__) . '/includes/class-utilities.php');

// Initialize and fix database
echo "Starting database fix...\n";

$database = new BSP_Database_Unified();
$result = $database->fix_table_issues();

if ($result) {
    echo "✅ Database fixed successfully! The incomplete_leads table now has the required columns.\n";
    echo "✅ customer_address, city, and state columns have been added.\n";
} else {
    echo "❌ Database fix failed. Check the logs for more details.\n";
}

echo "Database fix complete.\n";