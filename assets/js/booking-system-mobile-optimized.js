/**
 * Booking System Mobile Optimization
 */

(function() {
    'use strict';
    
    // Performance tracking with advanced metrics
    const performanceTracker = {
        marks: new Map(),
        metrics: {},
        
        mark(name) {
            const timestamp = performance.now();
            this.marks.set(name, timestamp);
        },
        
        measure(name, startMark, endMark = null) {
            const start = this.marks.get(startMark);
            const end = endMark ? this.marks.get(endMark) : performance.now();
            
            if (start) {
                const duration = end - start;
                this.metrics[name] = duration;
                return duration;
            }
        },
        
        getReport() {
            return {
                metrics: this.metrics,
                memoryUsage: performance.memory ? {
                    used: Math.round(performance.memory.usedJSHeapSize / 1048576) + 'MB',
                    total: Math.round(performance.memory.totalJSHeapSize / 1048576) + 'MB'
                } : null,
                timestamp: new Date().toISOString()
            };
        }
    };
    
    performanceTracker.mark('mobile-init-start');
    
    // Advanced state management with memory optimization
    const StateManager = {
        state: new Map(),
        listeners: new Map(),
        history: [],
        maxHistorySize: 50,
        
        set(key, value) {
            const prevValue = this.state.get(key);
            if (prevValue !== value) {
                this.state.set(key, value);
                
                // Add to history
                this.history.push({ key, value, timestamp: Date.now() });
                if (this.history.length > this.maxHistorySize) {
                    this.history.shift();
                }
                
                // Notify listeners
                const listeners = this.listeners.get(key);
                if (listeners) {
                    listeners.forEach(fn => {
                        try {
                            fn(value, prevValue);
                        } catch (error) {
                            // Silent error handling
                        }
                    });
                }
                
                // Memory cleanup for large objects
                if (prevValue && typeof prevValue === 'object' && 
                    JSON.stringify(prevValue).length > 1000) {
                    prevValue = null;
                }
            }
        },
        
        get(key) {
            return this.state.get(key);
        },
        
        subscribe(key, callback) {
            if (!this.listeners.has(key)) {
                this.listeners.set(key, new Set());
            }
            this.listeners.get(key).add(callback);
            
            return () => {
                const listeners = this.listeners.get(key);
                if (listeners) {
                    listeners.delete(callback);
                    if (listeners.size === 0) {
                        this.listeners.delete(key);
                    }
                }
            };
        },
        
        getMemoryUsage() {
            return {
                stateSize: this.state.size,
                listenersSize: this.listeners.size,
                historySize: this.history.length
            };
        },
        
        cleanup() {
            // Clean up old history entries
            const cutoff = Date.now() - (5 * 60 * 1000); // 5 minutes
            this.history = this.history.filter(entry => entry.timestamp > cutoff);
        }
    };
    
    // Predictive loading system
    const PredictiveLoader = {
        patterns: new Map(),
        preloadQueue: new Set(),
        
        record(action, nextAction) {
            if (!this.patterns.has(action)) {
                this.patterns.set(action, new Map());
            }
            const actionMap = this.patterns.get(action);
            const count = actionMap.get(nextAction) || 0;
            actionMap.set(nextAction, count + 1);
        },
        
        predict(action) {
            const actionMap = this.patterns.get(action);
            if (!actionMap) return null;
            
            let maxCount = 0;
            let prediction = null;
            
            for (const [nextAction, count] of actionMap) {
                if (count > maxCount) {
                    maxCount = count;
                    prediction = nextAction;
                }
            }
            
            return maxCount > 2 ? prediction : null;
        },
        
        preload(resource) {
            if (this.preloadQueue.has(resource)) return;
            this.preloadQueue.add(resource);
            
            requestIdleCallback(() => {
                switch(resource) {
                    case 'zipcode-lookup':
                        this.preloadZipcodeService();
                        break;
                    case 'calendar':
                        this.preloadCalendar();
                        break;
                    case 'email-validation':
                        this.preloadEmailValidator();
                        break;
                }
            });
        },
        
        preloadZipcodeService() {
            if (!window.zipcodeServicePreloaded && !document.querySelector('script[src*="zipcode-lookup.js"]')) {
                const script = document.createElement('script');
                script.src = '/wp-content/plugins/BookingPro/assets/js/zipcode-lookup.js';
                script.async = true;
                script.onload = () => {
                    window.zipcodeServicePreloaded = true;
                    StateManager.set('zipcode-service', 'loaded');
                };
                document.head.appendChild(script);
            }
        },
        
        preloadCalendar() {
            if (!window.calendarPreloaded) {
                window.calendarCache = {
                    today: new Date(),
                    availableDates: [],
                    loaded: true
                };
                window.calendarPreloaded = true;
                StateManager.set('calendar-cache', 'ready');
            }
        },
        
        preloadEmailValidator() {
            if (!window.emailValidatorPreloaded) {
                window.validateEmail = function(email) {
                    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                };
                window.emailValidatorPreloaded = true;
                StateManager.set('email-validator', 'ready');
            }
        }
    };
    
    // WebWorker for heavy operations
    let performanceWorker = null;
    
    function initPerformanceWorker() {
        if ('Worker' in window && !performanceWorker) {
            try {
                const workerScript = `
                    self.onmessage = function(e) {
                        const { type, data, id } = e.data;
                        
                        switch(type) {
                            case 'validate-zipcode':
                                const isValid = /^\\d{5}(-\\d{4})?$/.test(data.zipcode);
                                self.postMessage({ 
                                    type: 'zipcode-result', 
                                    id, 
                                    result: { isValid, zipcode: data.zipcode }
                                });
                                break;
                                
                            case 'calculate-availability':
                                const availability = calculateTimeSlots(data.date);
                                self.postMessage({ 
                                    type: 'availability-result', 
                                    id, 
                                    result: { availability, date: data.date }
                                });
                                break;
                                
                            case 'process-form-data':
                                const processed = processFormData(data);
                                self.postMessage({ 
                                    type: 'form-processed', 
                                    id, 
                                    result: processed
                                });
                                break;
                        }
                    };
                    
                    function calculateTimeSlots(date) {
                        const slots = [];
                        const start = 8;
                        const end = 18;
                        
                        for (let hour = start; hour < end; hour++) {
                            slots.push(hour + ':00');
                            slots.push(hour + ':30');
                        }
                        
                        return slots;
                    }
                    
                    function processFormData(data) {
                        // Simulate form processing
                        return {
                            ...data,
                            processed: true,
                            timestamp: Date.now()
                        };
                    }
                `;
                
                const workerBlob = new Blob([workerScript], { type: 'application/javascript' });
                performanceWorker = new Worker(URL.createObjectURL(workerBlob));
                
                performanceWorker.onmessage = function(e) {
                    const { type, id, result } = e.data;
                    StateManager.set(`worker-${id}`, { type, result, completed: true });
                };
                
                StateManager.set('performance-worker', 'ready');
            } catch (error) {
                // Worker not available, fallback to main thread
            }
        }
    }
    
    // Critical CSS injection with hardware acceleration
    function injectCriticalCSS() {
        const style = document.createElement('style');
        style.textContent = `
            .booking-step{display:none!important}
            .booking-step.active{display:block!important;opacity:1}
            .booking-system-form{opacity:1;transition:none}
            .service-option{cursor:pointer;transition:background 150ms ease;transform:translateZ(0)}
            .service-option:hover,.service-option.selected{background:#79B62F;color:white}
            .form-input{width:100%;padding:12px;border:1px solid #ddd;border-radius:4px;transform:translateZ(0)}
            .btn-primary{background:#79B62F;color:white;border:none;padding:12px 24px;border-radius:4px;cursor:pointer;transform:translateZ(0)}
            .loading-minimal{width:20px;height:20px;border:2px solid #f3f3f3;border-top:2px solid #79B62F;border-radius:50%;animation:spin 1s linear infinite}
            @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
            .mobile-optimized{contain:layout style paint;transform:translateZ(0)}
            .mobile-optimized .booking-step{will-change:opacity;backface-visibility:hidden}
            .mobile-optimized .service-option{will-change:background-color}
            .mobile-optimized .form-input{will-change:border-color}
            @media (max-width: 768px) {
                .booking-system-form{font-size:16px} /* Prevent zoom on iOS */
                .service-option{min-height:44px} /* Touch target */
            }
        `;
        document.head.appendChild(style);
        
        // Preload critical fonts
        const fontPreload = document.createElement('link');
        fontPreload.rel = 'preload';
        fontPreload.href = '/wp-content/plugins/BookingPro/assets/sf-pro-display/SFPRODISPLAYREGULAR.OTF';
        fontPreload.as = 'font';
        fontPreload.type = 'font/otf';
        fontPreload.crossOrigin = 'anonymous';
        document.head.appendChild(fontPreload);
    }
    
    // Service detection with caching
    function detectServiceFromURL() {
        const validServices = ['roofing', 'siding', 'windows', 'kitchen', 'bathroom', 'decks', 'adu'];
        
        // Check cache first
        const cached = sessionStorage.getItem('detected-service');
        if (cached && validServices.includes(cached.toLowerCase())) {
            return cached;
        }
        
        // Check URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const serviceParam = urlParams.get('service');
        
        if (serviceParam && validServices.includes(serviceParam.toLowerCase())) {
            const service = serviceParam.charAt(0).toUpperCase() + serviceParam.slice(1).toLowerCase();
            sessionStorage.setItem('detected-service', service);
            return service;
        }
        
        // Check hash
        const hash = window.location.hash.replace('#', '').toLowerCase();
        if (hash && validServices.includes(hash)) {
            const service = hash.charAt(0).toUpperCase() + hash.slice(1).toLowerCase();
            sessionStorage.setItem('detected-service', service);
            return service;
        }
        
        return null;
    }
    
    // Instant ZIP code step with UTM preservation
    function showZipStepImmediately(service) {
        const steps = document.querySelectorAll('.booking-step');
        steps.forEach(step => step.classList.remove('active'));
        
        const zipStep = document.querySelector('[data-step="2"], .booking-step:nth-child(2)');
        if (zipStep) {
            zipStep.classList.add('active');
            
            // Update ZIP step content with service
            const questionEl = zipStep.querySelector('.step-question, h2, .question-text');
            if (questionEl) {
                questionEl.innerHTML = `Start Your ${service} Remodel Today.<br>Find Local Pros Now.`;
            }
            
            // Focus on input for better UX
            const input = zipStep.querySelector('input[type="text"]');
            if (input) {
                // Delay focus to prevent keyboard issues on mobile
                setTimeout(() => input.focus(), 100);
            }
            
            // Preserve UTM parameters
            const urlParams = new URLSearchParams(window.location.search);
            const utmData = {};
            ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'].forEach(param => {
                const value = urlParams.get(param);
                if (value) utmData[param] = value;
            });
            
            StateManager.set('utm-data', utmData);
            StateManager.set('selected-service', service);
            StateManager.set('current-step', 2);
            
            // Record user action for prediction
            PredictiveLoader.record('service-detected', 'zipcode-input');
            
            // Preload likely next resources
            PredictiveLoader.preload('zipcode-lookup');
        }
        
        performanceTracker.mark('zip-step-shown');
        performanceTracker.measure('Service-to-ZIP', 'mobile-init-start', 'zip-step-shown');
    }
    
    // Instant service step fallback
    function showServiceStepImmediately() {
        const steps = document.querySelectorAll('.booking-step');
        steps.forEach(step => step.classList.remove('active'));
        
        const serviceStep = document.querySelector('[data-step="1"], .booking-step:first-child');
        if (serviceStep) {
            serviceStep.classList.add('active');
            
            // Add click handlers with predictive loading
            const serviceOptions = serviceStep.querySelectorAll('.service-option');
            serviceOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const service = this.textContent.trim();
                    StateManager.set('selected-service', service);
                    PredictiveLoader.record('service-selected', 'zipcode-input');
                    PredictiveLoader.preload('zipcode-lookup');
                });
            });
        }
        
        StateManager.set('current-step', 1);
        performanceTracker.mark('service-step-shown');
        performanceTracker.measure('Init-to-Service', 'mobile-init-start', 'service-step-shown');
    }
    
    // Enhanced main system loader with performance optimization
    function loadMainSystemNow() {
        if (StateManager.get('main-system-loading')) return;
        StateManager.set('main-system-loading', true);
        
        performanceTracker.mark('main-system-load-start');
        
        // Use requestIdleCallback for non-critical loading
        const loadMainSystem = () => {
            if (!window.jQuery && !document.querySelector('script[src*="cash"]')) {
                const jqueryScript = document.createElement('script');
                jqueryScript.src = 'https://cdn.jsdelivr.net/npm/cash-dom@8.1.1/dist/cash.min.js';
                jqueryScript.onload = () => {
                    if (window.cash && !window.jQuery) {
                        window.$ = window.cash;
                    }
                    loadBookingSystemScript();
                };
                jqueryScript.onerror = loadBookingSystemScript;
                document.head.appendChild(jqueryScript);
            } else {
                loadBookingSystemScript();
            }
        };
        
        // Use idle callback for performance optimization
        if ('requestIdleCallback' in window) {
            requestIdleCallback(loadMainSystem, { timeout: 200 });
        } else {
            setTimeout(loadMainSystem, 50); // Reduced timeout for better performance
        }
        
        function loadBookingSystemScript() {
            if (StateManager.get('main-system-loaded') || document.querySelector('script[src*="booking-system.js"]')) return;
            
            const mainScript = document.createElement('script');
            mainScript.src = '/wp-content/plugins/BookingPro/assets/js/booking-system.js';
            mainScript.async = true;
            mainScript.onload = () => {
                StateManager.set('main-system-loaded', true);
                
                setTimeout(() => {
                    transferMobileState();
                    performanceTracker.mark('main-system-loaded');
                    performanceTracker.measure('Main-System-Load', 'main-system-load-start', 'main-system-loaded');
                    
                    requestIdleCallback(() => {
                        initAdditionalFeatures();
                    }, { timeout: 100 });
                }, 0);
            };
            mainScript.onerror = () => {
                StateManager.set('main-system-error', true);
            };
            document.head.appendChild(mainScript);
        }
    }
    
    // Transfer mobile state to main system with validation
    function transferMobileState() {
        const mobileState = {
            selectedService: StateManager.get('selected-service'),
            currentStep: StateManager.get('current-step'),
            utmData: StateManager.get('utm-data'),
            formData: StateManager.get('form-data') || {}
        };
        
        // Validate and transfer state
        if (window.BookingSystem && typeof window.BookingSystem.setState === 'function') {
            try {
                window.BookingSystem.setState(mobileState);
            } catch (error) {
                // State transfer failed, use fallback
            }
        } else {
            window.mobileBookingState = mobileState;
        }
        
        StateManager.set('state-transferred', true);
    }
    
    // Initialize additional performance features
    function initAdditionalFeatures() {
        // Initialize WebWorker
        initPerformanceWorker();
        
        // Set up predictive preloading
        setupPredictivePreloading();
        
        // Initialize memory cleanup
        setupMemoryCleanup();
        
        // Set up performance monitoring
        setupPerformanceMonitoring();
    }
    
    function setupPredictivePreloading() {
        // Preload based on current state
        const currentStep = StateManager.get('current-step');
        if (currentStep === 2) {
            PredictiveLoader.preload('calendar');
            PredictiveLoader.preload('email-validation');
        }
        
        // Set up intersection observer for visible elements
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        if (element.classList.contains('booking-step')) {
                            const stepNumber = Array.from(element.parentNode.children).indexOf(element) + 1;
                            StateManager.set('visible-step', stepNumber);
                        }
                    }
                });
            });
            
            document.querySelectorAll('.booking-step').forEach(step => observer.observe(step));
        }
    }
    
    function setupMemoryCleanup() {
        setInterval(() => {
            StateManager.cleanup();
            
            if (sessionStorage.length > 10) {
                const keys = Object.keys(sessionStorage);
                keys.forEach(key => {
                    if (key.startsWith('temp-') || key.startsWith('cache-')) {
                        const data = sessionStorage.getItem(key);
                        try {
                            const parsed = JSON.parse(data);
                            if (parsed.timestamp && Date.now() - parsed.timestamp > 300000) {
                                sessionStorage.removeItem(key);
                            }
                        } catch (e) {
                            sessionStorage.removeItem(key);
                        }
                    }
                });
            }
        }, 120000);
    }
    
    function setupPerformanceMonitoring() {
        setInterval(() => {
            const report = performanceTracker.getReport();
            const memoryUsage = StateManager.getMemoryUsage();
            
            Object.entries(report.metrics).forEach(([metric, value]) => {
                let threshold = 500;
                
                if (metric.includes('Critical-Path')) {
                    threshold = 100;
                } else if (metric.includes('Main-System-Load')) {
                    threshold = 1000;
                } else if (metric.includes('Service-to-ZIP')) {
                    threshold = 200;
                }
                
                if (value > threshold && typeof gtag === 'function') {
                    gtag('event', 'performance_slow', {
                        'metric_name': metric,
                        'duration': Math.round(value),
                        'threshold': threshold,
                        'device_type': 'mobile'
                    });
                }
            });
        }, 60000);
    }
    
    function initCriticalPath() {
        performanceTracker.mark('critical-path-start');
        
        const bookingForm = document.querySelector('.booking-system-form');
        if (!bookingForm) return;
        
        bookingForm.classList.add('mobile-optimized');
        injectCriticalCSS();
        
        const preselectedService = detectServiceFromURL();
        
        if (preselectedService) {
            showZipStepImmediately(preselectedService);
        } else {
            showServiceStepImmediately();
        }
        
        performanceTracker.mark('critical-path-complete');
        performanceTracker.measure('Critical-Path-Total', 'critical-path-start', 'critical-path-complete');
        
        requestIdleCallback(() => {
            loadMainSystemNow();
        }, { timeout: 300 });
        
        requestIdleCallback(() => {
            initAdditionalFeatures();
        }, { timeout: 500 });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCriticalPath);
    } else {
        initCriticalPath();
    }
    
    window.BookingMobileOptimizer = {
        StateManager,
        PredictiveLoader,
        performanceTracker,
        getReport: () => performanceTracker.getReport(),
        getState: () => StateManager.getMemoryUsage()
    };
    
})();
