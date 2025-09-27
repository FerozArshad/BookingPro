<?php
/**
 * Test Google Sheets webhook with simplified payload to fix HTTP 400 errors
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/wp-config.php');

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Google Sheets Webhook Test - Fix HTTP 400</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f1f1; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 5px; max-width: 1000px; margin: 0 auto; }
        .success { color: #0d7017; }
        .error { color: #d63638; }
        .info { color: #135e96; }
        .warning { color: #b32d2e; }
        pre { background: #f6f7f7; padding: 10px; border-left: 4px solid #ddd; overflow-x: auto; }
        .payload { background: #f9f9f9; border: 1px solid #ccc; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
<div class="container">
<h1>Google Sheets Webhook Test - Fix HTTP 400</h1>

<?php

// Get webhook URL from settings
$integration_settings = get_option('bsp_integration_settings', []);
$webhook_url = $integration_settings['google_sheets_webhook_url'] ?? 'https://script.google.com/macros/s/AKfycbzmqDaGnI2yEfclR7PnoPOerY8GbmCGvR7hhBMuLvRLYQ3DCO2ur6j8PZ-MlOucGoxgxA/exec';

echo "<h2>Webhook URL: <code>" . htmlspecialchars($webhook_url) . "</code></h2>\n";

// Test 1: Very simple payload
echo "<h2>Test 1: Minimal Payload</h2>\n";
$simple_payload = [
    'session_id' => 'test_' . time(),
    'action' => 'test_connection',
    'timestamp' => date('Y-m-d H:i:s'),
    'customer_name' => 'Test User',
    'service' => 'Test Service'
];

echo "<div class='payload'><strong>Payload:</strong><pre>" . json_encode($simple_payload, JSON_PRETTY_PRINT) . "</pre></div>";

$args = [
    'method' => 'POST',
    'headers' => [
        'Content-Type' => 'application/json',
        'User-Agent' => 'BookingPro-Test/1.0'
    ],
    'body' => json_encode($simple_payload),
    'timeout' => 30,
    'blocking' => true,
    'sslverify' => true
];

echo "<p>Sending minimal payload...</p>\n";
$response = wp_remote_post($webhook_url, $args);

if (is_wp_error($response)) {
    echo "<p class='error'>❌ Error: " . $response->get_error_message() . "</p>\n";
} else {
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($code >= 200 && $code < 300) {
        echo "<p class='success'>✅ Success! HTTP $code</p>\n";
        echo "<div class='payload'><strong>Response:</strong><pre>" . htmlspecialchars($body) . "</pre></div>";
    } else {
        echo "<p class='error'>❌ HTTP Error $code</p>\n";
        echo "<div class='payload'><strong>Error Response:</strong><pre>" . htmlspecialchars($body) . "</pre></div>";
    }
}

// Test 2: Form-encoded payload (fallback method)
echo "<h2>Test 2: Form-Encoded Payload</h2>\n";

$form_args = [
    'method' => 'POST',
    'headers' => [
        'User-Agent' => 'BookingPro-Test/1.0'
    ],
    'body' => $simple_payload, // WordPress will encode as form data
    'timeout' => 30,
    'blocking' => true,
    'sslverify' => true
];

echo "<p>Sending form-encoded payload...</p>\n";
$response2 = wp_remote_post($webhook_url, $form_args);

if (is_wp_error($response2)) {
    echo "<p class='error'>❌ Error: " . $response2->get_error_message() . "</p>\n";
} else {
    $code2 = wp_remote_retrieve_response_code($response2);
    $body2 = wp_remote_retrieve_body($response2);
    
    if ($code2 >= 200 && $code2 < 300) {
        echo "<p class='success'>✅ Success! HTTP $code2</p>\n";
        echo "<div class='payload'><strong>Response:</strong><pre>" . htmlspecialchars($body2) . "</pre></div>";
    } else {
        echo "<p class='error'>❌ HTTP Error $code2</p>\n";
        echo "<div class='payload'><strong>Error Response:</strong><pre>" . htmlspecialchars($body2) . "</pre></div>";
    }
}

// Test 3: Complex payload (like actual booking)
echo "<h2>Test 3: Complex Booking Payload</h2>\n";
$complex_payload = [
    'session_id' => 'test_complex_' . time(),
    'action' => 'complete_booking',
    'timestamp' => date('Y-m-d H:i:s'),
    'update_mode' => 'upsert',
    'lead_type' => 'Complete Booking',
    'lead_status' => 'Complete',
    'status' => 'Converted',
    'customer_name' => 'Test Complex User',
    'customer_email' => 'test@test.com',
    'customer_phone' => '(555) 123-4567',
    'customer_address' => '123 Test St',
    'city' => 'Test City',
    'state' => 'Test State',
    'zip_code' => '12345',
    'service' => 'Test Service',
    'company' => 'Test Company',
    'booking_date' => '2025-09-30',
    'booking_time' => '10:00 AM',
    'appointment_count' => 1,
    'booking_id' => 'TEST123',
    'completion_percentage' => 100,
    'converted_to_booking' => 1
];

echo "<div class='payload'><strong>Complex Payload:</strong><pre>" . json_encode($complex_payload, JSON_PRETTY_PRINT) . "</pre></div>";

$complex_args = [
    'method' => 'POST',
    'headers' => [
        'Content-Type' => 'application/json',
        'User-Agent' => 'BookingPro-Test/1.0'
    ],
    'body' => json_encode($complex_payload),
    'timeout' => 30,
    'blocking' => true,
    'sslverify' => true
];

echo "<p>Sending complex booking payload...</p>\n";
$response3 = wp_remote_post($webhook_url, $complex_args);

if (is_wp_error($response3)) {
    echo "<p class='error'>❌ Error: " . $response3->get_error_message() . "</p>\n";
} else {
    $code3 = wp_remote_retrieve_response_code($response3);
    $body3 = wp_remote_retrieve_body($response3);
    
    if ($code3 >= 200 && $code3 < 300) {
        echo "<p class='success'>✅ Success! HTTP $code3</p>\n";
        echo "<div class='payload'><strong>Response:</strong><pre>" . htmlspecialchars($body3) . "</pre></div>";
    } else {
        echo "<p class='error'>❌ HTTP Error $code3</p>\n";
        echo "<div class='payload'><strong>Error Response:</strong><pre>" . htmlspecialchars($body3) . "</pre></div>";
    }
}

echo "<h2>Recommendations:</h2>\n";
echo "<ul>\n";
echo "<li>If Test 1 fails, there's a basic connectivity or authentication issue</li>\n";
echo "<li>If Test 2 works but Test 1 fails, the issue is with JSON format</li>\n";
echo "<li>If Test 3 fails, the issue is with complex data or field names</li>\n";
echo "<li>Check the Google Apps Script logs for detailed error information</li>\n";
echo "</ul>\n";

?>

</div>
</body>
</html>