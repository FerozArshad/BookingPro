<?php
/**
 * Test script to verify Google Sheets webhook payload structure
 */

require_once(__DIR__ . '/../../../../../../wp-load.php');

// Test incomplete lead data
$test_incomplete_data = [
    'session_id' => 'test_session_' . time(),
    'action' => 'bsp_capture_incomplete_lead',
    'form_step' => '1',
    'city' => 'Test City',
    'state' => 'Test State',
    'zip_code' => '90210',
    'service' => 'adu',
    'adu_action' => 'new_construction',
    'adu_type' => 'detached',
    'customer_name' => 'Test Customer',
    'customer_email' => 'test@example.com',
    'customer_phone' => '555-123-4567',
    'utm_source' => 'test',
    'utm_medium' => 'debug',
    'utm_campaign' => 'webhook_test',
    'referrer' => 'http://wordpress.local/?service=adu',
    'nonce' => wp_create_nonce('bsp_frontend_nonce')
];

echo "Testing incomplete lead webhook...\n";

// Simulate the Google Sheets integration
$sheets_integration = BSP_Google_Sheets_Integration::get_instance();

// Test the sync_incomplete_lead method
$result = $sheets_integration->sync_incomplete_lead(999, $test_incomplete_data);

echo "Incomplete lead sync result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

// Test complete booking data
$test_complete_data = [
    'session_id' => 'test_session_' . time(),
    'customer_name' => 'Test Customer Complete',
    'customer_email' => 'complete@example.com',
    'customer_phone' => '555-987-6543',
    'customer_address' => '123 Test Street',
    'city' => 'Complete City',
    'state' => 'Complete State',
    'zip_code' => '12345',
    'service' => 'ADU',
    'company' => 'Top Remodeling Pro',
    'booking_date' => '2025-10-01',
    'booking_time' => '10:00 AM',
    'adu_action' => 'addition',
    'adu_type' => 'attached',
    'specifications' => 'Complete ADU project specifications',
    'utm_source' => 'google',
    'utm_medium' => 'cpc',
    'utm_campaign' => 'adu_campaign',
    'referrer' => 'https://google.com/search?q=adu+construction'
];

echo "\nTesting complete booking webhook...\n";

// Test the sync_converted_lead method
$complete_result = $sheets_integration->sync_converted_lead(1001, $test_complete_data);

echo "Complete booking sync result: " . ($complete_result ? 'SUCCESS' : 'FAILED') . "\n";

echo "\nTest completed. Check debug log for detailed results.\n";
?>