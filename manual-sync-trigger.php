<?php
/**
 * Manual trigger for Google Sheets sync for booking 152
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/wp-config.php');

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

// Clear debug log for clean testing
$log_file = __DIR__ . '/wp-content/plugins/BookingPro/bsp-debug.log';
if (file_exists($log_file)) {
    // Keep last 50 lines for context
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines && count($lines) > 50) {
        $keep_lines = array_slice($lines, -50);
        $new_content = "=== MANUAL SYNC TEST STARTED " . date('Y-m-d H:i:s') . " ===\n";
        $new_content .= implode("\n", $keep_lines) . "\n\n";
        file_put_contents($log_file, $new_content);
    }
}

echo "Manual Google Sheets Sync Test\n";
echo "==============================\n\n";

// Get the plugin instance
$plugin = Booking_System_Pro_Final::get_instance();

// Test booking ID 152
$booking_id = 152;
echo "Testing sync for Booking ID: $booking_id\n";

// Get booking data
$booking_data = [
    'full_name' => get_post_meta($booking_id, '_customer_name', true),
    'email' => get_post_meta($booking_id, '_customer_email', true),
    'phone' => get_post_meta($booking_id, '_customer_phone', true),
    'address' => get_post_meta($booking_id, '_customer_address', true),
    'city' => get_post_meta($booking_id, '_city', true),
    'state' => get_post_meta($booking_id, '_state', true),
    'zip_code' => get_post_meta($booking_id, '_zip_code', true),
    'service' => get_post_meta($booking_id, '_service_type', true),
    'company' => get_post_meta($booking_id, '_company_name', true),
    'selected_date' => get_post_meta($booking_id, '_booking_date', true),
    'selected_time' => get_post_meta($booking_id, '_booking_time', true),
    'session_id' => get_post_meta($booking_id, '_session_id', true),
];

echo "Booking data retrieved:\n";
foreach($booking_data as $key => $value) {
    echo "  $key: " . ($value ?: '[empty]') . "\n";
}

echo "\nManually triggering Google Sheets sync handler...\n";

// Trigger the handler directly
try {
    $result = $plugin->handle_google_sheets_sync($booking_id, $booking_data);
    echo "Handler completed\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
}

echo "\nCheck the debug log for detailed results.\n";
echo "Log file: " . $log_file . "\n";
echo "\n=== Test Complete ===\n";