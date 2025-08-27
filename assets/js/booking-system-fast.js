/* Booking System Pro - Performance Optimized Entry Point */
(function($) {
    'use strict';
    
    // CRITICAL PATH: Immediate DOM setup for <300ms loading
    $(document).ready(function() {
        // Check if booking form exists on page
        if (!$('#booking-form').length) return;
        
        // INSTANT CSS: Apply critical styles immediately
        $('<style>').text(`
            .booking-step{display:none}
            .booking-step.active{display:block!important}
            .booking-system-form{opacity:1;transition:none}
            .loading-spinner{width:20px;height:20px;border:2px solid #f3f3f3;border-top:2px solid #79B62F;border-radius:50%;animation:spin 1s linear infinite}
            @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
        `).appendTo('head');
        
        // PERFORMANCE MARKER
        window.bookingSystemStartTime = performance.now();
        
        // DEFER FULL INITIALIZATION
        if (window.requestIdleCallback) {
            requestIdleCallback(() => {
                loadMainBookingSystem();
            });
        } else {
            setTimeout(loadMainBookingSystem, 50);
        }
        
        // IMMEDIATE SERVICE DETECTION for instant loading
        const urlParams = new URLSearchParams(window.location.search);
        const serviceParam = urlParams.get('service');
        
        if (serviceParam && ['roof', 'windows', 'bathroom', 'siding', 'kitchen', 'decks', 'adu'].includes(serviceParam.toLowerCase())) {
            // PRE-HIDE all steps and show ZIP code step immediately
            $('.booking-step').hide();
            $('.booking-step[data-step="2"]').show().addClass('active');
            
            // Set service title immediately
            const serviceName = serviceParam.charAt(0).toUpperCase() + serviceParam.slice(1);
            $('.booking-step[data-step="2"] .step-title').html(`${serviceName}<br>Replacement`);
            
            // Configure ZIP input mode
            $('#step2-options').hide();
            $('#step2-text-input').show();
            $('#step2-label').text('Enter Zip Code to check eligibility for free estimate');
        }
    });
    
    function loadMainBookingSystem() {
        // Check if main script is already loaded
        if (window.BookingSystem) {
            const loadTime = performance.now() - window.bookingSystemStartTime;
            console.log(`Booking system loaded in ${Math.round(loadTime)}ms`);
            return;
        }
        
        // Load main booking system script
        const script = document.createElement('script');
        script.src = BSP_Ajax.pluginUrl + '/assets/js/booking-system.js';
        script.onload = function() {
            const loadTime = performance.now() - window.bookingSystemStartTime;
            if (loadTime > 300) {
                console.warn(`Total load time: ${Math.round(loadTime)}ms (target: <300ms)`);
            } else {
                console.log(`Total load time: ${Math.round(loadTime)}ms ✓`);
            }
        };
        document.head.appendChild(script);
    }
    
})(jQuery);
