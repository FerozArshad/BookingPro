<?php
/**
 * Debug script to test Google Sheets webhook with minimal payload
 */

// Test payload - exactly what Google Apps Script expects
$test_payload = [
    'session_id' => 'test_session_123',
    'customer_name' => 'Test User',
    'service' => 'Windows',
    'action' => 'test'
];

$webhook_url = 'https://script.google.com/macros/s/AKfycbzmqDaGnI2yEfclR7PnoPOerY8GbmCGvR7hhBMuLvRLYQ3DCO2ur6j8PZ-MlOucGoxgxA/exec';

// Send as JSON
$json_payload = json_encode($test_payload);
echo "Testing JSON payload:\n";
echo $json_payload . "\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhook_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_payload)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "JSON Response - HTTP Code: {$http_code}\n";
echo "Response: " . substr($response, 0, 500) . "\n";
if ($error) echo "Error: {$error}\n";

echo "\n" . str_repeat("-", 50) . "\n\n";

// Send as URL-encoded
$url_encoded_payload = http_build_query($test_payload);
echo "Testing URL-encoded payload:\n";
echo $url_encoded_payload . "\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhook_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $url_encoded_payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "URL-encoded Response - HTTP Code: {$http_code}\n";
echo "Response: " . substr($response, 0, 500) . "\n";
if ($error) echo "Error: {$error}\n";