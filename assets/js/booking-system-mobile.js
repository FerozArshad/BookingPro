/*!
 * Booking System Pro - Mobile Optimized (Critical Path)
 * Target: Sub-50ms initial render
 * Size: <5KB minified
 */

(function() {
    'use strict';
    
    // PERFORMANCE TRACKING
    const startTime = performance.now();
    
    // Prevent double initialization
    if (window.BookingProMobile) {
        return;
    }
    
    // Mark as initialized
    window.BookingProMobile = {
        initialized: true,
        startTime: startTime
    };
    
    // Global state management for mobile
    let mobileState = {
        step: 1,
        selectedService: null,
        formData: {},
        isMainSystemLoaded: false,
        userLocation: null
    };
    
    // CRITICAL: DOM Ready Optimization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCriticalPath);
    } else {
        initCriticalPath();
    }
    
    function initCriticalPath() {
        const bookingForm = document.querySelector('.booking-system-form, #booking-form');
        if (!bookingForm) {
            return;
        }
        
        // Add mobile optimization marker
        bookingForm.classList.add('mobile-optimized');
        
        // INSTANT CRITICAL STYLES
        injectCriticalCSS();
        
        // IMMEDIATE SERVICE DETECTION
        const preselectedService = detectServiceFromURL();
        
        if (preselectedService) {
            // INSTANT ZIP CODE STEP
            showZipStepImmediately(preselectedService);
        } else {
            // SHOW SERVICE SELECTION
            showServiceStepImmediately();
        }
        
        // BIND CRITICAL INTERACTIONS
        bindCriticalEvents();
        
        // PERFORMANCE MARKER
        const loadTime = performance.now() - startTime;
        
        // Store performance data
        window.BookingProMobile.loadTime = loadTime;
        
        // LAZY LOAD FULL SYSTEM (only if needed)
        scheduleFullSystemLoad();
    }
    
    function injectCriticalCSS() {
        const style = document.createElement('style');
        style.textContent = `
            .booking-step{display:none!important}
            .booking-step.active{display:block!important;opacity:1}
            .booking-system-form{opacity:1;transition:none}
            .service-option{cursor:pointer;transition:background 150ms ease}
            .service-option:hover,.service-option.selected{background:#79B62F;color:white}
            .form-input{width:100%;padding:12px;border:1px solid #ddd;border-radius:4px}
            .btn-primary{background:#79B62F;color:white;border:none;padding:12px 24px;border-radius:4px;cursor:pointer}
            .loading-minimal{width:20px;height:20px;border:2px solid #f3f3f3;border-top:2px solid #79B62F;border-radius:50%;animation:spin 1s linear infinite}
            @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
            .mobile-optimized{contain:layout style paint}
            .mobile-optimized .booking-step{transform:translateZ(0);will-change:opacity}
            .mobile-optimized .service-option{transform:translateZ(0)}
        `;
        document.head.appendChild(style);
        
        // Preload critical fonts to prevent layout shift
        const fontPreload = document.createElement('link');
        fontPreload.rel = 'preload';
        fontPreload.href = '/wp-content/plugins/BookingPro/assets/sf-pro-display/SFPRODISPLAYREGULAR.OTF';
        fontPreload.as = 'font';
        fontPreload.type = 'font/otf';
        fontPreload.crossOrigin = 'anonymous';
        document.head.appendChild(fontPreload);
    }
    
    function detectServiceFromURL() {
        const validServices = ['roofing', 'siding', 'windows', 'kitchen', 'bathroom', 'decks', 'adu'];
        
        // Check query parameter
        const urlParams = new URLSearchParams(window.location.search);
        const serviceParam = urlParams.get('service');
        
        if (serviceParam && validServices.includes(serviceParam.toLowerCase())) {
            return serviceParam.charAt(0).toUpperCase() + serviceParam.slice(1).toLowerCase();
        }
        
        // Check hash
        const hash = window.location.hash.replace('#', '').toLowerCase();
        if (hash && validServices.includes(hash)) {
            return hash.charAt(0).toUpperCase() + hash.slice(1).toLowerCase();
        }
        
        return null;
    }
    
    function showZipStepImmediately(service) {
        // Hide all steps
        const steps = document.querySelectorAll('.booking-step');
        steps.forEach(step => step.classList.remove('active'));
        
        // Show ZIP step
        const zipStep = document.querySelector('.booking-step[data-step="2"], .booking-step:nth-child(2)');
        if (zipStep) {
            zipStep.classList.add('active');
            
            // Set title immediately with new copy
            const title = zipStep.querySelector('.step-title, .step-question, h2, .question-text');
            if (title) {
                title.innerHTML = `Start Your ${service} Remodel Today.<br>Connect With Trusted Local Pros Now`;
            }
            
            // Configure for text input
            const textInput = zipStep.querySelector('#step2-text-input, input[type="text"]');
            const options = zipStep.querySelector('#step2-options');
            
            if (textInput && options) {
                options.style.display = 'none';
                textInput.style.display = 'block';
            }
            
            // Set label
            const label = zipStep.querySelector('#step2-label, label');
            if (label) {
                label.textContent = 'Enter Zip Code to check eligibility for free estimate';
            }
            
            // Focus on input for better UX (with delay for mobile)
            setTimeout(() => {
                const input = zipStep.querySelector('input[type="text"]');
                if (input) {
                    input.focus();
                }
            }, 100);
        }
        
        // Store service selection and preserve UTM data
        const urlParams = new URLSearchParams(window.location.search);
        const utmData = {};
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'].forEach(param => {
            const value = urlParams.get(param);
            if (value) utmData[param] = value;
        });
        
        mobileState.selectedService = service;
        mobileState.step = 2;
        mobileState.utmData = utmData;
        
        // Store in window for main system
        window.bookingMobileState = mobileState;
    }
    
    function showServiceStepImmediately() {
        const steps = document.querySelectorAll('.booking-step');
        steps.forEach(step => step.classList.remove('active'));
        
        const serviceStep = document.querySelector('.booking-step[data-step="1"], .booking-step:first-child');
        if (serviceStep) {
            serviceStep.classList.add('active');
        }
        
        mobileState.step = 1;
        window.bookingMobileState = mobileState;
    }
    
    function bindCriticalEvents() {
        // Service selection
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('service-option')) {
                const service = e.target.getAttribute('data-service') || e.target.textContent.trim();
                if (service) {
                    handleServiceSelection(service);
                }
            }
            
            // Next button clicks - load full system
            if (e.target.classList.contains('btn-next') || e.target.classList.contains('btn-primary')) {
                e.preventDefault();
                
                // Load full system for navigation
                if (!window.BookingProMobile.fullSystemLoaded) {
                    loadMainSystemNow();
                } else {
                    // Full system is loaded, use it
                    if (typeof nextStep === 'function') {
                        nextStep();
                    }
                }
            }
        });
        
        // ZIP code input handling
        document.addEventListener('input', function(e) {
            if (e.target.type === 'text' && (e.target.id === 'step2-zip-input' || e.target.id === 'zip-input' || e.target.closest('.booking-step[data-step="2"]'))) {
                handleZipInput(e.target);
            }
        });
        
        // Form submission prevention until main system loads
        document.addEventListener('submit', function(e) {
            if (!window.BookingProMobile.fullSystemLoaded) {
                e.preventDefault();
                loadMainSystemNow();
                return false;
            }
        });
    }
    
    function handleServiceSelection(service) {
        // Visual feedback
        document.querySelectorAll('.service-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        if (event && event.target) {
            event.target.classList.add('selected');
        }
        
        // Store selection
        mobileState.selectedService = service;
        window.bookingMobileState = mobileState;
        
        // Move to ZIP step after short delay for better UX
        setTimeout(() => {
            showZipStepImmediately(service);
        }, 200);
    }
    
    function handleZipInput(input) {
        const value = input.value.trim();
        const isValid = value.length === 5 && /^\d{5}$/.test(value);
        
        // Store ZIP code
        if (isValid) {
            mobileState.formData.zipCode = value;
            window.bookingMobileState = mobileState;
        }
        
        // Enable/disable next button
        const nextBtn = input.closest('.booking-step').querySelector('.btn-next, .btn-primary');
        if (nextBtn) {
            nextBtn.disabled = !isValid;
            nextBtn.style.opacity = isValid ? '1' : '0.5';
        }
        
        // Auto-advance on valid ZIP (mobile optimization)
        if (isValid) {
            if (!window.BookingProMobile.fullSystemLoaded) {
                loadMainSystemNow();
            }
        }
    }
    
    function scheduleFullSystemLoad() {
        let scrollPreloaded = false;
        document.addEventListener('scroll', function() {
            if (!scrollPreloaded && !window.BookingProMobile.fullSystemLoaded) {
                scrollPreloaded = true;
                setTimeout(loadMainSystemNow, 1000); // Load after 1 second of scrolling
            }
        });
    }
    
    function loadMainSystemNow() {
        if (window.BookingProMobile.fullSystemLoaded || window.BookingProMobile.isLoading) {
            return;
        }
        
        window.BookingProMobile.isLoading = true;
        
        showLoadingIndicator();
        
        // Get configuration
        const config = window.BSP_MobileConfig || {};
        const pluginUrl = config.pluginUrl || '/wp-content/plugins/BookingPro/';
        
        // Load jQuery first if needed
        if (typeof jQuery === 'undefined') {
            loadScript('/wp-includes/js/jquery/jquery.min.js', () => {
                loadBookingSystemComponents(pluginUrl);
            });
        } else {
            loadBookingSystemComponents(pluginUrl);
        }
    }
    
    function showLoadingIndicator() {
        // Add minimal loading indicator
        let loader = document.querySelector('.mobile-loading-indicator');
        if (!loader) {
            loader = document.createElement('div');
            loader.className = 'mobile-loading-indicator';
            loader.innerHTML = '<div class="loading-minimal"></div><span>Loading...</span>';
            loader.style.cssText = 'position:fixed;top:10px;right:10px;background:rgba(0,0,0,0.8);color:white;padding:8px 12px;border-radius:4px;font-size:12px;z-index:9999;display:flex;align-items:center;gap:8px;';
            document.body.appendChild(loader);
        }
    }
    
    function hideLoadingIndicator() {
        const loader = document.querySelector('.mobile-loading-indicator');
        if (loader) {
            loader.remove();
        }
    }
    
    function loadScript(src, callback) {
        const script = document.createElement('script');
        script.src = src;
        script.onload = callback;
        script.onerror = () => {
            console.warn(`Failed to load ${src}`);
            if (callback) callback();
        };
        document.head.appendChild(script);
    }
    
    function loadBookingSystemComponents(pluginUrl) {
        // Load components in sequence for better reliability
        const components = [
            'zipcode-lookup.js',
            'video-section-controller.js',
            'source-tracker.js',
            'booking-system.js'
        ];
        
        let loadedCount = 0;
        const totalComponents = components.length;
        
        function loadNext() {
            if (loadedCount >= totalComponents) {
                onFullSystemLoaded();
                return;
            }
            
            const component = components[loadedCount];
            
            loadScript(pluginUrl + 'assets/js/' + component, () => {
                loadedCount++;
                loadNext();
            });
        }
        
        loadNext();
    }
    
    function onFullSystemLoaded() {
        window.BookingProMobile.fullSystemLoaded = true;
        window.BookingProMobile.isLoading = false;
        
        hideLoadingIndicator();
        
        // Transfer mobile state to main system
        if (window.bookingMobileState && typeof window.updateFormState === 'function') {
            window.updateFormState(window.bookingMobileState);
        } else if (window.bookingMobileState && window.formState) {
            // Fallback: Direct state transfer
            Object.assign(window.formState, window.bookingMobileState);
            
            // Navigate to appropriate step in main system
            if (typeof renderCurrentStep === 'function') {
                renderCurrentStep();
            }
        }
        
        // Initialize any additional features
        if (typeof initializeBookingSystem === 'function') {
            initializeBookingSystem();
        }
        
        // Log total performance
        const totalTime = performance.now() - window.BookingProMobile.startTime;
        
        // Fire custom event for any listeners
        document.dispatchEvent(new CustomEvent('bookingSystemReady', {
            detail: {
                loadTime: totalTime,
                mobileOptimized: true,
                state: window.bookingMobileState
            }
        }));
    }
    
    // Expose utilities for debugging
    window.BookingProMobile.getState = () => mobileState;
    window.BookingProMobile.loadMainSystem = loadMainSystemNow;
    window.BookingProMobile.getPerformance = () => ({
        initialLoad: window.BookingProMobile.loadTime,
        totalTime: performance.now() - window.BookingProMobile.startTime
    });
    
})();
