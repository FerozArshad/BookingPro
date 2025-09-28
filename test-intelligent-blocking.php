<?php
/**
 * Test script to verify intelligent session management fixes
 */

// Include WordPress functionality
require_once('../../../../wp-config.php');

echo "=== BookingPro Intelligent Session Management Test ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Test the Google Sheets Integration class
if (class_exists('BSP_Google_Sheets_Integration')) {
    $sheets_integration = new BSP_Google_Sheets_Integration();
    
    // Test if our new method exists
    if (method_exists($sheets_integration, 'should_block_incomplete_lead')) {
        echo "✅ should_block_incomplete_lead() method exists\n";
        
        // Test with lead ID 74 scenario
        $session_id = 'session_1759059329233_n2p48pyw';
        $lead_id = 74;
        
        // Use reflection to access the private method
        $reflection = new ReflectionClass($sheets_integration);
        $method = $reflection->getMethod('should_block_incomplete_lead');
        $method->setAccessible(true);
        
        try {
            $should_block = $method->invoke($sheets_integration, $session_id, $lead_id);
            echo "🔍 Test result for lead ID $lead_id: " . ($should_block ? "BLOCKED" : "ALLOWED") . "\n";
            
            // Let's also check what the database has for this lead
            global $wpdb;
            $table_name = $wpdb->prefix . 'bsp_incomplete_leads';
            $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $lead_id));
            
            if ($lead) {
                echo "📊 Lead ID $lead_id details:\n";
                echo "   Created: " . $lead->created_at . "\n";
                echo "   Session: " . $lead->session_id . "\n";
                echo "   Status: " . $lead->status . "\n";
                
                // Check if there's a termination record
                $termination = get_transient("bsp_session_terminated_$session_id");
                if ($termination) {
                    echo "   Session terminated at: " . $termination . "\n";
                    
                    // Parse timestamps for comparison
                    $lead_time = strtotime($lead->created_at);
                    $term_time = strtotime($termination);
                    
                    echo "   Lead created: " . date('H:i:s', $lead_time) . "\n";
                    echo "   Session ended: " . date('H:i:s', $term_time) . "\n";
                    echo "   Time difference: " . ($term_time - $lead_time) . " seconds\n";
                    echo "   Should be ALLOWED (lead created before termination): " . ($lead_time < $term_time ? "YES" : "NO") . "\n";
                } else {
                    echo "   No session termination record found\n";
                }
            } else {
                echo "❌ Lead ID $lead_id not found in database\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Error testing method: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "❌ should_block_incomplete_lead() method NOT found\n";
    }
} else {
    echo "❌ BSP_Google_Sheets_Integration class not found\n";
}

echo "\n=== Test Complete ===\n";