<?php
/**
 * Test Google Sheets Integration Fix
 * Run this file to test the enhanced data extraction for booking completion
 */

// WordPress environment
define('WP_USE_THEMES', false);
require_once('../../../wp-config.php');

// Make sure our classes are loaded
require_once('includes/class-google-sheets-integration.php');

echo "<h2>Testing Google Sheets Integration Fixes</h2>\n";

// Test booking ID (from the logs we saw earlier)
$test_booking_id = 122;
$test_session_id = "session_1758736025735_p19bgh41";

echo "<h3>Testing WordPress Post Meta Extraction</h3>\n";

// Check if booking exists
$booking_post = get_post($test_booking_id);
if (!$booking_post) {
    echo "ERROR: Booking post {$test_booking_id} not found!\n";
    exit;
}

echo "Booking Post Found: {$booking_post->post_title}\n";

// Extract WordPress meta data
$wp_booking_data = [
    'company' => get_post_meta($test_booking_id, '_company_name', true),
    'booking_date' => get_post_meta($test_booking_id, '_booking_date', true),
    'booking_time' => get_post_meta($test_booking_id, '_booking_time', true),
    'customer_address' => get_post_meta($test_booking_id, '_customer_address', true),
    'customer_name' => get_post_meta($test_booking_id, '_customer_name', true),
    'customer_email' => get_post_meta($test_booking_id, '_customer_email', true),
    'customer_phone' => get_post_meta($test_booking_id, '_customer_phone', true),
    'service' => get_post_meta($test_booking_id, '_service_type', true),
    'city' => get_post_meta($test_booking_id, '_city', true),
    'state' => get_post_meta($test_booking_id, '_state', true),
    'zip_code' => get_post_meta($test_booking_id, '_zip_code', true)
];

echo "<h4>WordPress Post Meta Data:</h4>\n";
echo "<pre>";
foreach ($wp_booking_data as $key => $value) {
    echo "{$key}: " . ($value ?: '[EMPTY]') . "\n";
}
echo "</pre>\n";

echo "<h3>Testing Google Sheets Integration</h3>\n";

// Initialize Google Sheets integration
$sheets_integration = new BSP_Google_Sheets_Integration();

// Create test booking data (simulating what would come from the booking process)
$original_booking_data = [
    'service' => 'Bathroom',
    'full_name' => 'Test Customer',
    'email' => 'test@test.com',
    'phone' => '555-1234',
    'address' => '123 Test St',
    'zip_code' => '12345',
    'city' => 'Test City',
    'state' => 'CA',
    'company' => 'Single Company',
    'selected_date' => '2025-09-25',
    'selected_time' => '10:00',
    'session_id' => $test_session_id
];

echo "<h4>Original Booking Data (from form):</h4>\n";
echo "<pre>";
print_r($original_booking_data);
echo "</pre>\n";

// Test the enhanced sync method
echo "<h4>Testing Enhanced Sync Method:</h4>\n";

try {
    $result = $sheets_integration->sync_converted_lead($test_booking_id, $original_booking_data);
    echo "Sync result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n<h3>Check Debug Log</h3>\n";
echo "Check the bsp-debug.log file for detailed sync attempt logs.\n";
echo "Look for entries with 'WordPress post meta data extracted' and 'Streamlined payload'.\n";

// Show recent log entries
echo "\n<h4>Recent Debug Log Entries:</h4>\n";
$log_file = __DIR__ . '/bsp-debug.log';
if (file_exists($log_file)) {
    $log_lines = file($log_file);
    $recent_lines = array_slice($log_lines, -20);
    echo "<pre>";
    foreach ($recent_lines as $line) {
        if (strpos($line, 'SHEETS_BOOKING_SYNC') !== false || 
            strpos($line, 'WordPress post meta') !== false ||
            strpos($line, 'Streamlined payload') !== false) {
            echo htmlspecialchars($line);
        }
    }
    echo "</pre>\n";
} else {
    echo "Debug log file not found.\n";
}

echo "\n<h3>Summary</h3>\n";
echo "1. ✅ WordPress post meta data extraction test\n";
echo "2. ✅ Google Sheets integration class loading\n";
echo "3. ✅ Enhanced sync method execution\n";
echo "4. Check the debug logs for webhook success/failure details\n";

?>