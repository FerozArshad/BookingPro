<?php
// Fix company configurations to be consistent
require_once(dirname(__FILE__) . '/../../../wp-load.php');

echo "<h2>Fixing Company Configurations</h2>\n";

global $wpdb;
BSP_Database_Unified::init_tables();
$tables = BSP_Database_Unified::$tables;

// Standardize all companies to have the same configuration
$updates = [
    // Company 1: Top Remodeling Pro - already correct
    [
        'id' => 1,
        'available_days' => '1,2,3,4,5,6', // Monday-Saturday
        'time_slot_duration' => 30
    ],
    // Company 2: Home Improvement Experts - already correct
    [
        'id' => 2, 
        'available_days' => '1,2,3,4,5,6', // Monday-Saturday
        'time_slot_duration' => 30
    ],
    // Company 3: Pro Remodeling Solutions - needs fixing
    [
        'id' => 3,
        'available_days' => '1,2,3,4,5,6', // Monday-Saturday (was only Mon-Fri)
        'time_slot_duration' => 30 // Was 60, now 30
    ]
];

foreach ($updates as $update) {
    $result = $wpdb->update(
        $tables['companies'],
        [
            'available_days' => $update['available_days'],
            'time_slot_duration' => $update['time_slot_duration']
        ],
        ['id' => $update['id']],
        ['%s', '%d'],
        ['%d']
    );
    
    if ($result !== false) {
        echo "<p>✅ Updated Company {$update['id']}: Days = {$update['available_days']}, Duration = {$update['time_slot_duration']} minutes</p>\n";
    } else {
        echo "<p>❌ Failed to update Company {$update['id']}</p>\n";
    }
}

// Verify the changes
echo "<h3>Verification - Updated Company Data:</h3>\n";
$companies = $wpdb->get_results("SELECT id, name, available_hours_start, available_hours_end, time_slot_duration, available_days FROM {$tables['companies']} ORDER BY id");

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>ID</th><th>Name</th><th>Hours</th><th>Duration</th><th>Available Days</th></tr>\n";

foreach ($companies as $company) {
    $days_text = '';
    $days = explode(',', $company->available_days);
    $day_names = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    foreach ($days as $day) {
        $days_text .= $day_names[$day] . ' ';
    }
    
    echo "<tr>";
    echo "<td>{$company->id}</td>";
    echo "<td>{$company->name}</td>";
    echo "<td>{$company->available_hours_start} - {$company->available_hours_end}</td>";
    echo "<td>{$company->time_slot_duration} min</td>";
    echo "<td>{$company->available_days} ({$days_text})</td>";
    echo "</tr>\n";
}
echo "</table>\n";

echo "<p><strong>All companies should now have:</strong></p>\n";
echo "<ul>\n";
echo "<li>Available Days: Monday through Saturday (1,2,3,4,5,6)</li>\n";
echo "<li>Time Slot Duration: 30 minutes</li>\n";
echo "<li>Hours: 10:00 AM - 7:00 PM</li>\n";
echo "</ul>\n";
?>
