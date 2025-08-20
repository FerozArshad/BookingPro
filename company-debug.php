<?php
// Check company configurations for time slot differences
require_once(dirname(__FILE__) . '/../../../wp-load.php');

echo "<h2>Company Configuration Analysis</h2>\n";

// Get companies from database
global $wpdb;
BSP_Database_Unified::init_tables();
$tables = BSP_Database_Unified::$tables;

$companies = $wpdb->get_results("SELECT * FROM {$tables['companies']}");

echo "<h3>All Companies in Database:</h3>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>ID</th><th>Name</th><th>Available Hours Start</th><th>Available Hours End</th><th>Time Slot Duration</th><th>Available Days</th></tr>\n";

foreach ($companies as $company) {
    echo "<tr>";
    echo "<td>{$company->id}</td>";
    echo "<td>{$company->name}</td>";
    echo "<td>" . ($company->available_hours_start ?? 'NOT SET') . "</td>";
    echo "<td>" . ($company->available_hours_end ?? 'NOT SET') . "</td>";
    echo "<td>" . ($company->time_slot_duration ?? 'NOT SET') . "</td>";
    echo "<td>" . ($company->available_days ?? 'NOT SET') . "</td>";
    echo "</tr>\n";
}
echo "</table>\n";

// Test booking query with more details
echo "<h3>Booking Query Debug</h3>\n";

$today = '2025-08-20';
$company_ids = [1, 2, 3];
$company_ids_sql = implode(',', array_map('intval', $company_ids));

// Test the exact query our system uses
$query = "
    SELECT 
        pm1.meta_value as booking_date,
        pm2.meta_value as booking_time,
        pm3.meta_value as company_id,
        p.ID as booking_id,
        p.post_status,
        p.post_title
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_booking_date'
    INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_booking_time'
    INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_company_id'
    WHERE p.post_type = 'bsp_booking'
    AND p.post_status IN ('publish', 'pending')
    AND pm3.meta_value IN ({$company_ids_sql})
    AND pm1.meta_value = '{$today}'
";

echo "<p><strong>SQL Query:</strong></p>\n";
echo "<pre style='background: #f9f9f9; padding: 10px; font-size: 12px;'>" . htmlspecialchars($query) . "</pre>\n";

$results = $wpdb->get_results($query);

echo "<p><strong>Results:</strong> " . count($results) . " bookings found</p>\n";

if (!empty($results)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Booking ID</th><th>Title</th><th>Company ID</th><th>Date</th><th>Time</th><th>Status</th></tr>\n";
    
    foreach ($results as $row) {
        echo "<tr>";
        echo "<td>{$row->booking_id}</td>";
        echo "<td>" . htmlspecialchars($row->post_title) . "</td>";
        echo "<td>{$row->company_id}</td>";
        echo "<td>{$row->booking_date}</td>";
        echo "<td>{$row->booking_time}</td>";
        echo "<td>{$row->post_status}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
} else {
    // Check if there are ANY bookings at all
    $all_bookings = $wpdb->get_results("
        SELECT p.ID, p.post_title, p.post_status, p.post_date
        FROM {$wpdb->posts} p 
        WHERE p.post_type = 'bsp_booking' 
        ORDER BY p.ID DESC 
        LIMIT 5
    ");
    
    echo "<p><strong>Last 5 bookings of any kind:</strong></p>\n";
    if (!empty($all_bookings)) {
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Date</th></tr>\n";
        foreach ($all_bookings as $booking) {
            echo "<tr><td>{$booking->ID}</td><td>" . htmlspecialchars($booking->post_title) . "</td><td>{$booking->post_status}</td><td>{$booking->post_date}</td></tr>\n";
        }
        echo "</table>\n";
        
        // Check meta for latest booking
        if (!empty($all_bookings)) {
            $latest = $all_bookings[0];
            $meta = get_post_meta($latest->ID);
            echo "<p><strong>Meta data for booking {$latest->ID}:</strong></p>\n";
            echo "<pre style='background: #f9f9f9; padding: 10px; font-size: 12px;'>";
            foreach ($meta as $key => $value) {
                echo htmlspecialchars($key . ': ' . (is_array($value) ? implode(', ', $value) : $value)) . "\n";
            }
            echo "</pre>\n";
        }
    } else {
        echo "<p><strong>NO BOOKINGS FOUND AT ALL!</strong></p>\n";
    }
}

// Check for multiple appointments in the latest booking
echo "<h3>Multiple Appointments Check</h3>\n";
$latest_booking = get_posts([
    'post_type' => 'bsp_booking',
    'posts_per_page' => 1,
    'post_status' => 'any',
    'orderby' => 'ID',
    'order' => 'DESC'
]);

if (!empty($latest_booking)) {
    $booking_id = $latest_booking[0]->ID;
    $appointments = get_post_meta($booking_id, '_appointments', true);
    echo "<p><strong>Appointments data:</strong> " . htmlspecialchars($appointments) . "</p>\n";
    
    if ($appointments) {
        $appointments_array = json_decode($appointments, true);
        if ($appointments_array) {
            echo "<table border='1' style='border-collapse: collapse;'>\n";
            echo "<tr><th>Company</th><th>Company ID</th><th>Date</th><th>Time</th></tr>\n";
            foreach ($appointments_array as $apt) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($apt['company'] ?? 'MISSING') . "</td>";
                echo "<td>" . ($apt['companyId'] ?? 'MISSING') . "</td>";
                echo "<td>" . ($apt['date'] ?? 'MISSING') . "</td>";
                echo "<td>" . ($apt['time'] ?? 'MISSING') . "</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    }
}
?>
