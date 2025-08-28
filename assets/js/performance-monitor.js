/**
 * BookingPro Performance Monitor - Production Version
 * Lightweight monitoring for booking form performance
 */

(function() {
    'use strict';
    
    // Minimal performance monitoring
    window.BSP_PerformanceMonitor = {
        startTime: performance.now(),
        events: [],
        
        // Log critical events only
        log: function(event, details) {
            const timestamp = performance.now();
            const timeSinceStart = timestamp - this.startTime;
            
            this.events.push({
                event: event,
                details: details || {},
                timestamp: timestamp,
                timeSinceStart: Math.round(timeSinceStart),
                url: window.location.href
            });
        },
        
        // Get performance report
        getReport: function() {
            return {
                totalTime: Math.round(performance.now() - this.startTime),
                events: this.events,
                pageUrl: window.location.href,
                timestamp: new Date().toISOString()
            };
        }
    };

    // Track critical loading events
    BSP_PerformanceMonitor.log('Script Start', {
        readyState: document.readyState,
        hasService: window.location.search.includes('service=')
    });

    // DOM ready tracking
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            BSP_PerformanceMonitor.log('DOM Ready');
        });
    } else {
        BSP_PerformanceMonitor.log('DOM Ready');
    }

    // Window load tracking
    window.addEventListener('load', function() {
        BSP_PerformanceMonitor.log('Window Load Complete');
    });

    // Track booking form visibility
    function checkBookingForm() {
        const form = document.getElementById('booking-form');
        if (form) {
            BSP_PerformanceMonitor.log('Booking Form Ready');
            return true;
        }
        return false;
    }

    // Check for booking form
    let formCheckInterval = setInterval(function() {
        if (checkBookingForm()) {
            clearInterval(formCheckInterval);
        }
    }, 50);

    // Stop checking after 1 second
    setTimeout(function() {
        clearInterval(formCheckInterval);
    }, 1000);

    // Track CSS loading
    const stylesheets = document.querySelectorAll('link[rel="stylesheet"]');
    stylesheets.forEach(function(link) {
        if (link.href.includes('booking-system')) {
            BSP_PerformanceMonitor.log('CSS Loaded', {
                href: link.href
            });
        }
    });

    // Expose global function for performance reports
    window.BSP_GetPerformanceReport = function() {
        return BSP_PerformanceMonitor.getReport();
    };

})();
