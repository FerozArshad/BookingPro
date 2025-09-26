/**
 * Test Appointment Data Flow - Client-Side Test
 * 
 * Open this file in the browser developer console to test appointment data capture
 */

console.log('=== BSP Appointment Data Flow Test ===');

// Test 1: Check if selectedAppointments getter is available
console.log('\n1. Testing selectedAppointments Access...');

if (typeof window.getSelectedAppointments === 'function') {
    console.log('✓ getSelectedAppointments() function is available');
    
    try {
        const appointments = window.getSelectedAppointments();
        console.log('Current appointments:', appointments);
        
        if (appointments && appointments.length > 0) {
            console.log('✓ Appointments data found:', appointments.length, 'appointments');
            appointments.forEach((apt, index) => {
                console.log(`  Appointment ${index + 1}:`, {
                    company: apt.company,
                    date: apt.date,
                    time: apt.time,
                    service: apt.service
                });
            });
        } else {
            console.log('⚠ No appointments currently selected');
        }
    } catch (e) {
        console.error('✗ Error accessing getSelectedAppointments():', e);
    }
} else {
    console.log('✗ getSelectedAppointments() function not available');
}

// Test 2: Check direct window.selectedAppointments access
console.log('\n2. Testing Direct Access...');

if (typeof window.selectedAppointments !== 'undefined') {
    console.log('✓ window.selectedAppointments is defined');
    console.log('Value:', window.selectedAppointments);
} else {
    console.log('⚠ window.selectedAppointments not defined');
}

if (typeof selectedAppointments !== 'undefined') {
    console.log('✓ selectedAppointments global variable is defined');
    console.log('Value:', selectedAppointments);
} else {
    console.log('⚠ selectedAppointments global variable not defined');
}

// Test 3: Check BSP Lead Capture system
console.log('\n3. Testing BSP Lead Capture System...');

if (typeof window.bspLeadCapture !== 'undefined') {
    console.log('✓ BSP Lead Capture system is loaded');
    
    if (typeof window.bspLeadCapture.collectFormData === 'function') {
        console.log('✓ collectFormData method available');
        
        try {
            const formData = window.bspLeadCapture.collectFormData();
            console.log('Current form data:', formData);
            
            // Check for appointment-related fields
            const appointmentFields = ['appointments', 'company', 'selected_date', 'selected_time', 'booking_date', 'booking_time'];
            const foundFields = appointmentFields.filter(field => formData.hasOwnProperty(field));
            
            if (foundFields.length > 0) {
                console.log('✓ Appointment fields found in form data:', foundFields);
                foundFields.forEach(field => {
                    console.log(`  - ${field}: ${formData[field]}`);
                });
            } else {
                console.log('⚠ No appointment fields found in form data');
            }
        } catch (e) {
            console.error('✗ Error calling collectFormData():', e);
        }
    } else {
        console.log('✗ collectFormData method not available');
    }
} else {
    console.log('✗ BSP Lead Capture system not loaded');
}

// Test 4: Check SafeIntegration system
console.log('\n4. Testing SafeIntegration System...');

if (typeof SafeIntegration !== 'undefined') {
    console.log('✓ SafeIntegration system is available');
    
    if (typeof SafeIntegration.safeAccess === 'function') {
        console.log('✓ safeAccess method available');
        
        try {
            const safeAppointments = SafeIntegration.safeAccess(window, 'selectedAppointments');
            console.log('Safe appointments access result:', safeAppointments);
        } catch (e) {
            console.error('✗ Error using SafeIntegration.safeAccess:', e);
        }
    } else {
        console.log('✗ SafeIntegration.safeAccess not available');
    }
} else {
    console.log('⚠ SafeIntegration system not loaded');
}

// Test 5: Simulate appointment data creation (for testing)
console.log('\n5. Simulating Appointment Data...');

const testAppointments = [
    {
        company: 'Test Company 1',
        date: '2024-01-15',
        time: '09:00 AM',
        service: 'roof'
    },
    {
        company: 'Test Company 2',
        date: '2024-01-16',
        time: '10:30 AM',
        service: 'windows'
    }
];

console.log('Test appointments created:', testAppointments);

// Try setting appointments (if setter is available)
if (typeof window.selectedAppointments !== 'undefined' && window.selectedAppointments !== null) {
    try {
        // This should work with the property descriptor we set up
        window.selectedAppointments = testAppointments;
        console.log('✓ Successfully set test appointments');
        
        // Verify they were set
        const verification = window.getSelectedAppointments ? window.getSelectedAppointments() : window.selectedAppointments;
        console.log('Verification - appointments are now:', verification);
        
    } catch (e) {
        console.error('✗ Error setting appointments:', e);
    }
}

// Test 6: Test form data collection with appointments
console.log('\n6. Testing Form Data Collection with Appointments...');

if (typeof window.bspLeadCapture !== 'undefined' && typeof window.bspLeadCapture.collectFormData === 'function') {
    try {
        const dataWithAppointments = window.bspLeadCapture.collectFormData();
        console.log('Form data with appointments:', dataWithAppointments);
        
        if (dataWithAppointments.appointments) {
            console.log('✓ Appointments included in form data');
            console.log('Appointments JSON:', dataWithAppointments.appointments);
            
            try {
                const parsedAppointments = JSON.parse(dataWithAppointments.appointments);
                console.log('✓ Appointments JSON is valid:', parsedAppointments);
            } catch (e) {
                console.error('✗ Invalid appointments JSON:', e);
            }
        } else {
            console.log('⚠ No appointments in form data');
        }
    } catch (e) {
        console.error('✗ Error testing form data collection:', e);
    }
}

console.log('\n=== Test Complete ===');

// Instructions
console.log('\nINSTRUCTIONS:');
console.log('1. Make sure you have selected appointments in the booking form');
console.log('2. Check that selectedAppointments variable contains data');
console.log('3. Verify that lead capture system can access this data');
console.log('4. Test an incomplete lead capture to see if appointment data is sent');

// Summary function
window.bspTestAppointmentCapture = function() {
    console.log('\n=== Quick Appointment Test ===');
    
    const appointments = window.getSelectedAppointments ? window.getSelectedAppointments() : 'N/A';
    const formData = window.bspLeadCapture ? window.bspLeadCapture.collectFormData() : 'N/A';
    
    console.log('Current appointments:', appointments);
    console.log('Form data appointments:', formData.appointments || 'Not found');
    
    return {
        appointments: appointments,
        formDataAppointments: formData.appointments,
        hasAppointments: appointments && appointments.length > 0,
        formHasAppointments: formData && formData.appointments
    };
};

console.log('\nQuick test function available: bspTestAppointmentCapture()');