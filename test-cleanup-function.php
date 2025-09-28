<?php
/**
 * Test script to verify cleanup functionality and trigger it
 */

// Include WordPress functionality
require_once('../../../../wp-config.php');

echo "=== BookingPro Cleanup Function Test ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Get the plugin instance
$bsp = BSP();

if ($bsp) {
    echo "✅ BookingPro plugin instance found\n";
    
    // Test if cleanup method exists
    if (method_exists($bsp, 'cleanup_stuck_incomplete_leads')) {
        echo "✅ cleanup_stuck_incomplete_leads() method exists\n";
        
        // Check current stuck leads
        global $wpdb;
        $table_name = $wpdb->prefix . 'bsp_incomplete_leads';
        $stuck_leads = $wpdb->get_results("
            SELECT id, session_id, created_at, status 
            FROM $table_name 
            WHERE status = 'processing' 
            AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        
        echo "🔍 Found " . count($stuck_leads) . " potentially stuck leads:\n";
        foreach ($stuck_leads as $lead) {
            echo "   Lead ID {$lead->id}: Session {$lead->session_id}, Created: {$lead->created_at}, Status: {$lead->status}\n";
        }
        
        if (count($stuck_leads) > 0) {
            echo "\n🧹 Running cleanup function...\n";
            $stats = $bsp->cleanup_stuck_incomplete_leads();
            
            echo "📊 Cleanup Results:\n";
            echo "   Leads cleaned: " . $stats['leads_cleaned'] . "\n";
            echo "   Sessions affected: " . $stats['sessions_affected'] . "\n";
            echo "   Last cleanup: " . $stats['last_cleanup'] . "\n";
            
            // Check if lead 74 is still stuck
            $lead_74 = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 74");
            if ($lead_74) {
                echo "   Lead 74 status after cleanup: " . $lead_74->status . "\n";
            } else {
                echo "   Lead 74: Not found\n";
            }
        } else {
            echo "ℹ️ No stuck leads found - cleanup not needed\n";
        }
        
    } else {
        echo "❌ cleanup_stuck_incomplete_leads() method NOT found\n";
    }
    
    // Test intelligent blocking method
    if (method_exists($bsp, 'get_stuck_lead_cleanup_stats')) {
        echo "✅ get_stuck_lead_cleanup_stats() method exists\n";
        $stats = $bsp->get_stuck_lead_cleanup_stats();
        echo "📈 Current cleanup stats: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "❌ get_stuck_lead_cleanup_stats() method NOT found\n";
    }
    
} else {
    echo "❌ BookingPro plugin instance not found\n";
}

echo "\n=== Test Complete ===\n";