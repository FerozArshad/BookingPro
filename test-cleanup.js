/**
 * BSP Lead Capture Cleanup Test
 * 
 * Test script to verify that timers are properly cleaned up
 */

console.log('=== BSP Lead Capture Cleanup Test ===');

// Function to check active timers
function checkActiveTimers() {
    console.log('Checking BSP Lead Capture system state:');
    
    if (typeof window.bspLeadCapture !== 'undefined' && window.bspLeadCapture.LeadCapture) {
        const leadCapture = window.bspLeadCapture.LeadCapture;
        
        console.log('Lead Capture State:', {
            isInitialized: leadCapture.isInitialized,
            isDestroyed: leadCapture.isDestroyed,
            captureTimer: leadCapture.captureTimer,
            periodicTimer: leadCapture.periodicTimer,
            sessionId: leadCapture.sessionId
        });
        
        return {
            hasActiveTimers: !!(leadCapture.captureTimer || leadCapture.periodicTimer),
            isDestroyed: leadCapture.isDestroyed,
            timers: {
                capture: leadCapture.captureTimer,
                periodic: leadCapture.periodicTimer
            }
        };
    } else {
        console.log('BSP Lead Capture system not found');
        return { error: 'System not found' };
    }
}

// Function to manually destroy the system (for testing)
function manualDestroy() {
    console.log('Manually destroying BSP Lead Capture system...');
    
    if (typeof window.bspLeadCapture !== 'undefined' && window.bspLeadCapture.LeadCapture) {
        window.bspLeadCapture.LeadCapture.destroy();
        console.log('Manual destruction completed');
        return true;
    } else {
        console.log('System not available for destruction');
        return false;
    }
}

// Function to simulate page close events
function simulatePageClose() {
    console.log('Simulating page close events...');
    
    // Trigger beforeunload event
    const beforeUnloadEvent = new Event('beforeunload');
    window.dispatchEvent(beforeUnloadEvent);
    
    // Trigger pagehide event  
    const pageHideEvent = new Event('pagehide');
    window.dispatchEvent(pageHideEvent);
    
    console.log('Page close events dispatched');
}

// Initial check
console.log('1. Initial State:');
checkActiveTimers();

// Wait a bit then check for periodic timer
setTimeout(() => {
    console.log('\n2. After 2 seconds (periodic timer should be active):');
    const state = checkActiveTimers();
    
    if (state.hasActiveTimers) {
        console.log('✓ Timers are active (expected)');
        
        // Now simulate page close
        console.log('\n3. Simulating page close...');
        simulatePageClose();
        
        // Check state after cleanup
        setTimeout(() => {
            console.log('\n4. After cleanup:');
            const cleanupState = checkActiveTimers();
            
            if (cleanupState.isDestroyed) {
                console.log('✅ SUCCESS: System properly destroyed');
                console.log('✅ SUCCESS: Timers should be cleared');
                
                if (!cleanupState.hasActiveTimers) {
                    console.log('✅ SUCCESS: No active timers found');
                } else {
                    console.log('❌ WARNING: Timers still active after cleanup');
                }
            } else {
                console.log('❌ FAIL: System not properly destroyed');
            }
            
        }, 100);
        
    } else {
        console.log('❌ No timers found - system may not be running properly');
    }
}, 2000);

// Make functions available globally
window.bspTestCheckTimers = checkActiveTimers;
window.bspTestManualDestroy = manualDestroy;
window.bspTestSimulatePageClose = simulatePageClose;

console.log('\nTest functions available:');
console.log('- bspTestCheckTimers() - Check current timer state');
console.log('- bspTestManualDestroy() - Manually destroy the system');  
console.log('- bspTestSimulatePageClose() - Simulate page close events');