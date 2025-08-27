/**
 * Performance Monitor - Safe Diagnostic Tool
 * This script tracks loading performance without breaking existing functionality
 */

(function() {
    'use strict';
    
    // Performance monitoring object
    window.BSP_PerformanceMonitor = {
        startTime: performance.now(),
        events: [],
        
        // Log performance events
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
            
            // Console log for real-time monitoring
            console.log(`[BSP Performance] ${Math.round(timeSinceStart)}ms: ${event}`, details);
        },
        
        // Get summary report
        getReport: function() {
            return {
                totalTime: Math.round(performance.now() - this.startTime),
                events: this.events,
                pageUrl: window.location.href,
                userAgent: navigator.userAgent,
                timestamp: new Date().toISOString()
            };
        },
        
        // Send report to console (for copying)
        printReport: function() {
            console.log('=== BSP Performance Report ===');
            console.log('Total Load Time:', this.getReport().totalTime + 'ms');
            console.log('Events:', this.events);
            console.log('Full Report:', JSON.stringify(this.getReport(), null, 2));
        }
    };
    
    // Track initial state
    BSP_PerformanceMonitor.log('Script Start', {
        readyState: document.readyState,
        hasService: new URLSearchParams(window.location.search).has('service')
    });
    
    // Track DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            BSP_PerformanceMonitor.log('DOM Ready');
        });
    } else {
        BSP_PerformanceMonitor.log('DOM Already Ready');
    }
    
    // Track window load
    window.addEventListener('load', function() {
        BSP_PerformanceMonitor.log('Window Load Complete');
    });
    
    // Track Elementor if present
    if (window.elementorFrontend) {
        BSP_PerformanceMonitor.log('Elementor Detected');
        
        window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function() {
            BSP_PerformanceMonitor.log('Elementor Element Ready');
        });
    }
    
    // Track booking form visibility
    function checkBookingForm() {
        const form = document.getElementById('booking-form');
        if (form) {
            const isVisible = form.offsetParent !== null;
            const opacity = window.getComputedStyle(form).opacity;
            const visibility = window.getComputedStyle(form).visibility;
            
            BSP_PerformanceMonitor.log('Booking Form Found', {
                visible: isVisible,
                opacity: opacity,
                visibility: visibility,
                classes: form.className
            });
            
            return true;
        }
        return false;
    }
    
    // Check for booking form periodically - OPTIMIZED: Faster intervals, shorter timeout
    let formCheckInterval = setInterval(function() {
        if (checkBookingForm()) {
            clearInterval(formCheckInterval);
        }
    }, 25); // Reduced from 50ms to 25ms for faster detection
    
    // Stop checking after 1 second for performance
    setTimeout(function() {
        clearInterval(formCheckInterval);
        BSP_PerformanceMonitor.log('Form Check Complete');
    }, 1000); // Reduced from 5000ms to 1000ms
    
    // Track CSS loading
    function trackCSSLoading() {
        const stylesheets = document.querySelectorAll('link[rel="stylesheet"]');
        let loadedCount = 0;
        
        stylesheets.forEach(function(link, index) {
            if (link.href.includes('booking')) {
                BSP_PerformanceMonitor.log('Booking CSS Found', {
                    href: link.href,
                    loaded: link.sheet !== null
                });
                
                link.addEventListener('load', function() {
                    loadedCount++;
                    BSP_PerformanceMonitor.log('CSS Loaded', {
                        href: link.href,
                        loadedCount: loadedCount
                    });
                });
                
                link.addEventListener('error', function() {
                    BSP_PerformanceMonitor.log('CSS Load Error', {
                        href: link.href
                    });
                });
            }
        });
    }
    
    // Track JS loading
    function trackJSLoading() {
        const scripts = document.querySelectorAll('script[src]');
        
        scripts.forEach(function(script) {
            if (script.src.includes('booking')) {
                BSP_PerformanceMonitor.log('Booking JS Found', {
                    src: script.src,
                    loaded: script.readyState || 'unknown'
                });
                
                script.addEventListener('load', function() {
                    BSP_PerformanceMonitor.log('JS Loaded', {
                        src: script.src
                    });
                });
                
                script.addEventListener('error', function() {
                    BSP_PerformanceMonitor.log('JS Load Error', {
                        src: script.src
                    });
                });
            }
        });
    }
    
    // Start tracking
    trackCSSLoading();
    trackJSLoading();
    
    // Track background/white screen issues - OPTIMIZED: Reduced frequency and duration
    function trackScreenState() {
        const body = document.body || document.documentElement;
        const bodyBG = window.getComputedStyle(body).backgroundColor;
        const htmlBG = window.getComputedStyle(document.documentElement).backgroundColor;
        
        BSP_PerformanceMonitor.log('Screen State', {
            bodyBackground: bodyBG,
            htmlBackground: htmlBG,
            bodyClass: body.className
        });
    }
    
    // Check screen state less frequently - OPTIMIZED: Only during critical loading phase
    let screenCheckInterval = setInterval(trackScreenState, 500); // Reduced frequency from 200ms to 500ms
    setTimeout(function() {
        clearInterval(screenCheckInterval);
    }, 1500); // Reduced duration from 3000ms to 1500ms
    
    // Expose global function for manual reporting
    window.BSP_GetPerformanceReport = function() {
        BSP_PerformanceMonitor.printReport();
        return BSP_PerformanceMonitor.getReport();
    };
    
    // OPTIMIZED: Generate final report after 2 seconds instead of 5
    setTimeout(function() {
        BSP_PerformanceMonitor.log('Performance Monitoring Complete');
        console.log('%c=== BSP Performance Monitor Report ===', 'color: #007cba; font-weight: bold; font-size: 14px;');
        console.log('Call BSP_GetPerformanceReport() for detailed analysis');
        // Only print report if explicitly requested to reduce console overhead
        // BSP_PerformanceMonitor.printReport();
    }, 2000); // Reduced from 5000ms to 2000ms
    
})();
