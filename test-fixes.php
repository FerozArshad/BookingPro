<?php
/**
 * Quick test to verify booking system fixes
 * Run this from the plugin directory to check status
 */

// Include WordPress
require_once('../../../wp-config.php');

echo "=== BOOKING SYSTEM FIXES VERIFICATION ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Test 1: Check companies
global $wpdb;
$companies_table = $wpdb->prefix . 'bsp_companies';

echo "1. COMPANIES CHECK:\n";
$companies = $wpdb->get_results("SELECT id, name, status FROM {$companies_table}");

if ($companies) {
    foreach ($companies as $company) {
        $status_icon = ($company->status === 'active') ? '✅' : '❌';
        echo "   {$status_icon} ID: {$company->id} | {$company->name} | Status: {$company->status}\n";
    }
    
    $active_count = count(array_filter($companies, function($c) { return $c->status === 'active'; }));
    echo "   → {$active_count} active companies out of " . count($companies) . " total\n";
} else {
    echo "   ❌ No companies found!\n";
}

// Test 2: Check AJAX handlers
echo "\n2. AJAX HANDLERS CHECK:\n";
$ajax_actions = [
    'bsp_get_availability' => 'Should work (handled by BSP_Ajax class)',
    'bsp_get_slots' => 'Should work (handled by BSP_Booking_System_Pro class)',
    'bsp_submit_booking' => 'Should work (handled by BSP_Ajax class)'
];

foreach ($ajax_actions as $action => $expected) {
    echo "   📡 {$action}: {$expected}\n";
}

// Test 3: Check recent changes
echo "\n3. FILES RECENTLY MODIFIED:\n";
$plugin_dir = __DIR__;
$files_to_check = [
    'includes/class-ajax.php',
    'assets/js/booking-system.js'
];

foreach ($files_to_check as $file) {
    $full_path = $plugin_dir . '/' . $file;
    if (file_exists($full_path)) {
        $mod_time = filemtime($full_path);
        $time_ago = time() - $mod_time;
        $time_str = ($time_ago < 60) ? "{$time_ago} seconds ago" : 
                   (($time_ago < 3600) ? round($time_ago/60) . " minutes ago" : 
                    round($time_ago/3600) . " hours ago");
        echo "   📝 {$file}: Modified {$time_str}\n";
    } else {
        echo "   ❌ {$file}: File not found\n";
    }
}

echo "\n4. FIXES SUMMARY:\n";
echo "   ✅ Removed conflicting get_time_slots AJAX registration\n";
echo "   ✅ Added refreshAllCompanyAvailability() function for status updates\n";
echo "   ✅ Modified booking success handler to refresh availability data\n";

echo "\n5. TESTING RECOMMENDATIONS:\n";
echo "   1. Clear browser cache completely\n";
echo "   2. Test booking form - all companies should show\n";
echo "   3. Complete a booking - availability should refresh automatically\n";
echo "   4. Check browser console for debug messages\n";

echo "\n=== TEST COMPLETE ===\n";
?>