<?php
/**
 * Comprehensive Plugin Debug Test
 */

echo "=== BOOKING SYSTEM PRO DEBUG TEST ===\n";

// Simulate WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

// Mock WordPress functions that the plugin needs
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://localhost/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {
        echo "✓ Action registered: $hook\n";
        return true;
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        echo "✓ Activation hook registered\n";
        return true;
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {
        echo "✓ Deactivation hook registered\n";
        return true;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show) {
        return '6.0';
    }
}

if (!function_exists('wp_get_theme')) {
    function wp_get_theme() {
        return (object)['Name' => 'Test Theme'];
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('error_log')) {
    function error_log($message) {
        echo "LOG: $message\n";
    }
}

if (!function_exists('microtime')) {
    function microtime($get_as_float = false) {
        return $get_as_float ? 1234567890.123 : '0.12345678 1234567890';
    }
}

if (!function_exists('memory_get_usage')) {
    function memory_get_usage($real_usage = false) {
        return 1024 * 1024 * 64; // 64MB
    }
}

if (!function_exists('memory_get_peak_usage')) {
    function memory_get_peak_usage($real_usage = false) {
        return 1024 * 1024 * 128; // 128MB
    }
}

if (!function_exists('ini_get')) {
    function ini_get($varname) {
        return '128M';
    }
}

echo "\n1. Testing main plugin file inclusion...\n";
try {
    require_once 'booking-system-pro-final.php';
    echo "✓ SUCCESS: Main plugin file loaded!\n";
} catch (ParseError $e) {
    echo "✗ PARSE ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    exit(1);
} catch (Error $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n2. Testing class availability...\n";
$required_classes = [
    'Booking_System_Pro_Final',
    'BSP_Database_Unified',
    'BSP_Post_Types',
    'BSP_Taxonomies',
    'BSP_Frontend',
    'BSP_Admin',
    'BSP_Ajax',
    'BSP_Email'
];

foreach ($required_classes as $class) {
    if (class_exists($class)) {
        echo "✓ Class available: $class\n";
    } else {
        echo "✗ Class missing: $class\n";
    }
}

echo "\n3. Testing plugin instance creation...\n";
try {
    if (class_exists('Booking_System_Pro_Final')) {
        $plugin = Booking_System_Pro_Final::get_instance();
        echo "✓ Plugin instance created successfully!\n";
    } else {
        echo "✗ Main plugin class not found!\n";
    }
} catch (Exception $e) {
    echo "✗ Failed to create plugin instance: " . $e->getMessage() . "\n";
}

echo "\n4. Testing debug functions...\n";
if (function_exists('bsp_debug_log')) {
    echo "✓ Debug logging function available\n";
    bsp_debug_log("Test debug message", 'TEST');
} else {
    echo "✗ Debug logging function not available\n";
}

echo "\n5. Testing file structure...\n";
$required_files = [
    'includes/class-ajax.php',
    'includes/class-email.php',
    'includes/class-frontend.php',
    'includes/class-admin.php',
    'assets/js/booking-system.js'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✓ File exists: $file ($size bytes)\n";
    } else {
        echo "✗ File missing: $file\n";
    }
}

echo "\n=== TEST COMPLETED ===\n";
?>
