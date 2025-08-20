<?php
/**
 * Test script to create a sample booking and verify the slot detection
 * Add this to your WordPress installation and visit: /wp-content/plugins/BookingPro/test-booking-debug.php
 */

// Include WordPress
require_once '../../../../wp-config.php';

// Create a test booking
$test_booking_data = [
    'post_title' => 'Test Booking - John Doe - 2025-08-21',
    'post_content' => 'Test booking for debugging',
    'post_status' => 'publish',
    'post_type' => 'bsp_booking',
    'meta_input' => [
        '_customer_name' => 'John Doe',
        '_customer_email' => 'john@example.com',
        '_customer_phone' => '555-1234',
        '_customer_address' => '123 Test Street',
        '_company_name' => 'Top Remodeling Pro',
        '_company_id' => '1',
        '_company_ids' => '1',
        '_service_type' => 'Roof',
        '_booking_date' => '2025-08-21',
        '_booking_time' => '10:00',
        '_status' => 'pending',
        '_created_at' => current_time('mysql')
    ]
];

echo "<h1>Booking System Debug Test</h1>";

// Create the test booking
$booking_id = wp_insert_post($test_booking_data);

if (is_wp_error($booking_id)) {
    echo "<p style='color: red;'>Error creating booking: " . $booking_id->get_error_message() . "</p>";
} else {
    echo "<p style='color: green;'>✅ Test booking created with ID: {$booking_id}</p>";
    
    // Test the slot checking
    include_once 'includes/class-ajax.php';
    $ajax_handler = new BSP_Ajax();
    
    // Use reflection to call private method
    $reflection = new ReflectionClass($ajax_handler);
    $method = $reflection->getMethod('is_slot_booked');
    $method->setAccessible(true);
    
    // Test if the slot is detected as booked
    $is_booked = $method->invoke($ajax_handler, 1, '2025-08-21', '10:00');
    
    if ($is_booked) {
        echo "<p style='color: green;'>✅ Slot correctly detected as BOOKED</p>";
    } else {
        echo "<p style='color: red;'>❌ Slot NOT detected as booked (this is the bug)</p>";
    }
    
    // Test different company
    $is_booked_different_company = $method->invoke($ajax_handler, 2, '2025-08-21', '10:00');
    
    if (!$is_booked_different_company) {
        echo "<p style='color: green;'>✅ Same slot available for different company (company separation working)</p>";
    } else {
        echo "<p style='color: red;'>❌ Slot blocked for different company (company separation broken)</p>";
    }
    
    // Show booking details
    echo "<h2>Booking Details:</h2>";
    echo "<pre>";
    foreach ($test_booking_data['meta_input'] as $key => $value) {
        echo "{$key}: {$value}\n";
    }
    echo "</pre>";
    
    // Clean up - remove test booking
    wp_delete_post($booking_id, true);
    echo "<p>Test booking removed.</p>";
}
?>
