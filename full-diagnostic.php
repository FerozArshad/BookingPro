<?php
// Comprehensive diagnostic for booking availability system
require_once(dirname(__FILE__) . '/../../../wp-load.php');

echo "<h2>Complete Booking System Diagnostic</h2>\n";

// 1. Check recent bookings
echo "<h3>1. Recent Bookings (Last 24 hours)</h3>\n";
$recent_bookings = get_posts([
    'post_type' => 'bsp_booking',
    'posts_per_page' => 10,
    'post_status' => ['publish', 'pending', 'draft'],
    'meta_query' => [
        [
            'key' => '_created_at',
            'value' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'compare' => '>='
        ]
    ]
]);

if (empty($recent_bookings)) {
    echo "<p><strong>❌ NO RECENT BOOKINGS FOUND!</strong></p>\n";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>ID</th><th>Created</th><th>Company Name</th><th>Company ID</th><th>Date</th><th>Time</th><th>Status</th></tr>\n";
    
    foreach ($recent_bookings as $booking) {
        $company_name = get_post_meta($booking->ID, '_company_name', true);
        $company_id = get_post_meta($booking->ID, '_company_id', true);
        $booking_date = get_post_meta($booking->ID, '_booking_date', true);
        $booking_time = get_post_meta($booking->ID, '_booking_time', true);
        $created_at = get_post_meta($booking->ID, '_created_at', true);
        
        echo "<tr>";
        echo "<td>{$booking->ID}</td>";
        echo "<td>" . ($created_at ?: $booking->post_date) . "</td>";
        echo "<td>" . ($company_name ?: '<span style="color: red;">MISSING</span>') . "</td>";
        echo "<td>" . ($company_id ?: '<span style="color: red;">MISSING</span>') . "</td>";
        echo "<td>" . ($booking_date ?: '<span style="color: red;">MISSING</span>') . "</td>";
        echo "<td>" . ($booking_time ?: '<span style="color: red;">MISSING</span>') . "</td>";
        echo "<td>{$booking->post_status}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

// 2. Test the actual availability query for today
echo "<h3>2. Test Availability Query for Today (2025-08-20)</h3>\n";

global $wpdb;
$test_date = '2025-08-20';
$company_ids = [1, 2, 3];

echo "<p>Testing for date: {$test_date}, Company IDs: " . implode(', ', $company_ids) . "</p>\n";

// Run the exact same query our availability system uses
$company_ids_sql = implode(',', array_map('intval', $company_ids));
$query = $wpdb->prepare("
    SELECT 
        pm1.meta_value as booking_date,
        pm2.meta_value as booking_time,
        pm3.meta_value as company_id,
        p.ID as booking_id,
        p.post_status
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_booking_date'
    INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_booking_time'
    INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_company_id'
    WHERE p.post_type = 'bsp_booking'
    AND p.post_status IN ('publish', 'pending')
    AND pm3.meta_value IN ({$company_ids_sql})
    AND pm1.meta_value >= %s
    AND pm1.meta_value <= %s
", $test_date, $test_date);

echo "<p><strong>SQL Query:</strong></p>\n";
echo "<pre style='background: #f9f9f9; padding: 10px; font-size: 12px;'>" . htmlspecialchars($query) . "</pre>\n";

$results = $wpdb->get_results($query);

echo "<p><strong>Query Results:</strong></p>\n";
if (empty($results)) {
    echo "<p><strong>❌ NO BOOKED SLOTS FOUND!</strong> This is why all time slots show as available.</p>\n";
    
    // Let's check if there are ANY bookings with _company_id
    $all_company_bookings = $wpdb->get_results("
        SELECT p.ID, pm.meta_value as company_id, p.post_status 
        FROM {$wpdb->posts} p 
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
        WHERE p.post_type = 'bsp_booking' 
        AND pm.meta_key = '_company_id'
    ");
    
    echo "<p>All bookings with _company_id field: " . count($all_company_bookings) . "</p>\n";
    if (!empty($all_company_bookings)) {
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Booking ID</th><th>Company ID</th><th>Status</th></tr>\n";
        foreach ($all_company_bookings as $row) {
            echo "<tr><td>{$row->ID}</td><td>{$row->company_id}</td><td>{$row->post_status}</td></tr>\n";
        }
        echo "</table>\n";
    }
    
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Booking ID</th><th>Company ID</th><th>Date</th><th>Time</th><th>Status</th></tr>\n";
    
    foreach ($results as $row) {
        echo "<tr>";
        echo "<td>{$row->booking_id}</td>";
        echo "<td>{$row->company_id}</td>";
        echo "<td>{$row->booking_date}</td>";
        echo "<td>{$row->booking_time}</td>";
        echo "<td>{$row->post_status}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Organize by company like our system does
    echo "<h4>Organized by Company (like our system does):</h4>\n";
    foreach ($company_ids as $company_id) {
        $company_slots = [];
        foreach ($results as $row) {
            if (intval($row->company_id) === $company_id) {
                $dates = array_map('trim', explode(',', $row->booking_date));
                $times = array_map('trim', explode(',', $row->booking_time));
                foreach ($dates as $date) {
                    foreach ($times as $time) {
                        $company_slots[] = $date . '_' . $time;
                    }
                }
            }
        }
        echo "<p><strong>Company {$company_id}:</strong> " . implode(', ', $company_slots) . "</p>\n";
    }
}

// 3. Check if the booking just submitted is there
echo "<h3>3. Check Latest Booking</h3>\n";
$latest_booking = get_posts([
    'post_type' => 'bsp_booking',
    'posts_per_page' => 1,
    'post_status' => 'any',
    'orderby' => 'ID',
    'order' => 'DESC'
]);

if (!empty($latest_booking)) {
    $booking = $latest_booking[0];
    $all_meta = get_post_meta($booking->ID);
    
    echo "<p><strong>Latest Booking (ID: {$booking->ID}):</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Status: {$booking->post_status}</li>\n";
    echo "<li>Company Name: " . ($all_meta['_company_name'][0] ?? 'MISSING') . "</li>\n";
    echo "<li>Company ID: " . ($all_meta['_company_id'][0] ?? 'MISSING') . "</li>\n";
    echo "<li>Date: " . ($all_meta['_booking_date'][0] ?? 'MISSING') . "</li>\n";
    echo "<li>Time: " . ($all_meta['_booking_time'][0] ?? 'MISSING') . "</li>\n";
    echo "</ul>\n";
    
    echo "<p><strong>All Meta Fields:</strong></p>\n";
    echo "<pre style='background: #f9f9f9; padding: 10px; font-size: 12px;'>";
    foreach ($all_meta as $key => $value) {
        echo htmlspecialchars($key . ': ' . (is_array($value) ? implode(', ', $value) : $value)) . "\n";
    }
    echo "</pre>\n";
}
?>
