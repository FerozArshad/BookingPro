<?php
/**
 * Simple test for intelligent session management - no complexity, just the fix
 */

// Include WordPress
require_once('../../../../wp-config.php');

echo "=== Simple Fix Test ===\n";
echo "Testing: Intelligent session management for incomplete leads\n";
echo "Goal: Allow incomplete leads created BEFORE session completion\n\n";

// Test the scenario: Lead 74 created before session termination
$session_id = 'session_1759059329233_n2p48pyw';
$lead_id = 74;

echo "Test Case: Lead ID $lead_id with session $session_id\n";
echo "Expected: Should be ALLOWED (created before session ended)\n\n";

// Check if our Google Sheets Integration class exists and has the method
if (class_exists('BSP_Google_Sheets_Integration')) {
    $sheets = BSP_Google_Sheets_Integration::get_instance();
    
    if (method_exists($sheets, 'should_block_incomplete_lead')) {
        echo "✅ Intelligent blocking method exists\n";
        
        // Use reflection to test it
        try {
            $reflection = new ReflectionClass($sheets);
            $method = $reflection->getMethod('should_block_incomplete_lead');
            $method->setAccessible(true);
            
            $result = $method->invoke($sheets, $session_id, [], $lead_id);
            
            echo "🔍 Result: " . ($result['should_block'] ? "BLOCKED" : "ALLOWED") . "\n";
            echo "📝 Reason: " . $result['reason'] . "\n";
            
            if (isset($result['lead_created_time']) && isset($result['termination_time'])) {
                echo "⏰ Lead created: " . $result['lead_created_time'] . "\n";
                echo "⏰ Session ended: " . $result['termination_time'] . "\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "❌ Method not found\n";
    }
} else {
    echo "❌ Class not found\n";
}

echo "\n=== Test Complete ===\n";