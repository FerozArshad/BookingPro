/**
 * Lead Capture System for Incomplete Form Submissions
 * Phase 1: Basic capture with safe integration
 */

(function() {
    'use strict';
    
    // Wait for safe integration and UTM manager to be ready
    const waitForDependencies = () => {
        return new Promise((resolve) => {
            const checkDependencies = () => {
                if (window.bspLeadCapture && 
                    window.bspLeadCapture.SafeIntegration && 
                    window.bspLeadCapture.UTMManager) {
                    resolve();
                } else {
                    setTimeout(checkDependencies, 100);
                }
            };
            checkDependencies();
        });
    };
    
    const LeadCapture = {
        config: null,
        safeIntegration: null,
        sessionId: null,
        captureTimer: null,
        periodicTimer: null, // Add periodic timer reference
        lastCaptureData: null,
        isInitialized: false,
        isDestroyed: false, // Add destruction flag
        
        init: async function() {
            // CRITICAL: Prevent multiple initializations
            if (this.isInitialized) {
                console.warn('BSP Lead Capture: Already initialized, skipping');
                return;
            }
            
            // Wait for dependencies
            await waitForDependencies();
            
            this.config = window.bspLeadConfig || {};
            this.safeIntegration = window.bspLeadCapture.SafeIntegration;
            
            if (!this.config.ajaxUrl) {
                if (this.config.debug) {
                    console.error('BSP Lead Capture: No AJAX URL configured');
                }
                return;
            }
            
            this.sessionId = this.generateSessionId();
            this.setupEventListeners();
            this.startPeriodicCapture();
            this.isInitialized = true;
            
            console.log('BSP Lead Capture initialized', {
                sessionId: this.sessionId,
                config: this.config,
                utmData: this.getUTMData(),
                timestamp: new Date().toISOString()
            });
        },
        
        generateSessionId: function() {
            // UNIFIED SESSION ID: Use the same system as booking-system.js
            // First, try to get session ID from booking system (cookie-based)
            const bookingSessionId = this.getCookieValue('bsp_session_id');
            if (bookingSessionId) {
                // Store it in localStorage for consistency
                const storageKey = this.config.storageKey + '_session';
                const expiryTime = Date.now() + this.config.sessionExpiry;
                const sessionData = {
                    sessionId: bookingSessionId,
                    expiry: expiryTime,
                    created: Date.now(),
                    source: 'booking_system_cookie'
                };
                this.safeIntegration.safeLocalStorage.setItem(storageKey, JSON.stringify(sessionData));
                return bookingSessionId;
            }
            
            // Try to get existing session ID from localStorage
            const storageKey = this.config.storageKey + '_session';
            let existingSession = this.safeIntegration.safeLocalStorage.getItem(storageKey);
            
            if (existingSession) {
                try {
                    const sessionData = JSON.parse(existingSession);
                    const expiryTime = sessionData.expiry || 0;
                    
                    if (Date.now() < expiryTime) {
                        return sessionData.sessionId;
                    }
                } catch (e) {
                    if (this.config.debug) {
                        console.warn('BSP: Invalid session data in localStorage', e);
                    }
                }
            }
            
            // Generate new session ID using the same format as booking system
            // Use 'session_' prefix to match booking-system.js format
            const newSessionId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 8);
            const expiryTime = Date.now() + this.config.sessionExpiry;
            
            // Store in localStorage
            const sessionData = {
                sessionId: newSessionId,
                expiry: expiryTime,
                created: Date.now(),
                source: 'lead_capture_generated'
            };
            this.safeIntegration.safeLocalStorage.setItem(storageKey, JSON.stringify(sessionData));
            
            // Also store in cookie to be compatible with booking system
            this.setCookieValue('bsp_session_id', newSessionId, 1); // 1 day
            
            return newSessionId;
        },
        
        // Helper method to get cookie value
        getCookieValue: function(name) {
            const cookies = document.cookie.split(';');
            for (let i = 0; i < cookies.length; i++) {
                let cookie = cookies[i].trim();
                if (cookie.indexOf(name + '=') === 0) {
                    return cookie.substring(name.length + 1);
                }
            }
            return null;
        },
        
        // Helper method to set cookie value
        setCookieValue: function(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = `${name}=${value}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
        },
        
        setupEventListeners: function() {
            // Monitor form field changes
            this.monitorFormChanges();
            
            // Monitor exit intent
            this.setupExitIntent();
            
            // Monitor tab visibility
            this.setupTabVisibilityMonitoring();
        },
        
        monitorFormChanges: function() {
            const formSelectors = [
                'input[type="text"]',
                'input[type="email"]',
                'input[type="tel"]', 
                'input[type="phone"]',
                'select',
                'textarea',
                'input[type="radio"]:checked',
                'input[type="checkbox"]:checked'
            ];
            
            const formElements = document.querySelectorAll(formSelectors.join(', '));
            
            formElements.forEach(element => {
                ['change', 'input', 'blur'].forEach(eventType => {
                    element.addEventListener(eventType, () => {
                        this.scheduleCapture();
                    });
                });
            });
            
            // Monitor for dynamically added form elements
            this.observeFormChanges();
        },
        
        observeFormChanges: function() {
            if (typeof MutationObserver === 'undefined') {
                return;
            }
            
            const observer = new MutationObserver((mutations) => {
                let shouldScheduleCapture = false;
                
                mutations.forEach(mutation => {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach(node => {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                const formElements = node.querySelectorAll('input, select, textarea');
                                if (formElements.length > 0) {
                                    shouldScheduleCapture = true;
                                    // Add event listeners to new elements
                                    this.attachEventListenersToElements(formElements);
                                }
                            }
                        });
                    }
                });
                
                if (shouldScheduleCapture) {
                    this.scheduleCapture();
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },
        
        attachEventListenersToElements: function(elements) {
            elements.forEach(element => {
                ['change', 'input', 'blur'].forEach(eventType => {
                    element.addEventListener(eventType, () => {
                        this.scheduleCapture();
                    });
                });
            });
        },
        
        setupExitIntent: function() {
            let hasTriggered = false;
            
            document.addEventListener('mouseleave', (e) => {
                if (!hasTriggered && e.clientY <= 0) {
                    hasTriggered = true;
                    this.captureLeadData(true); // Force immediate capture
                    
                    // Reset after 5 seconds
                    setTimeout(() => {
                        hasTriggered = false;
                    }, 5000);
                }
            });
        },
        
        setupTabVisibilityMonitoring: function() {
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    // Tab is being hidden - capture current state
                    this.captureLeadData(true);
                }
            });
            
            // CRITICAL FIX: Properly handle page unload and cleanup
            window.addEventListener('beforeunload', () => {
                console.log('BSP Lead Capture: Page unloading - final capture and cleanup');
                this.captureLeadData(true);
                this.destroy(); // Clean up timers and resources
            });
            
            // Also handle page hide (more reliable than beforeunload)
            window.addEventListener('pagehide', () => {
                console.log('BSP Lead Capture: Page hiding - cleanup');
                this.destroy();
            });
            
            // Handle unload as final fallback
            window.addEventListener('unload', () => {
                console.log('BSP Lead Capture: Page unloading - emergency cleanup');
                this.destroy();
            });
        },
        
        scheduleCapture: function() {
            // CRITICAL: Don't schedule if destroyed
            if (this.isDestroyed) {
                return;
            }
            
            // CRITICAL SESSION MANAGEMENT: Don't schedule if session is completed
            if (window.isSessionCompleted) {
                console.log('BSP: Session completed - stopping all scheduled captures');
                this.destroy();
                return;
            }
            
            // Clear existing timer
            if (this.captureTimer) {
                clearTimeout(this.captureTimer);
            }
            
            // Schedule new capture
            this.captureTimer = setTimeout(() => {
                // Double-check destruction state before executing
                if (!this.isDestroyed) {
                    this.captureLeadData();
                }
            }, this.config.captureDelay || 2000);
        },
        
        startPeriodicCapture: function() {
            // CRITICAL FIX: Store interval reference and clear any existing interval
            if (this.periodicTimer) {
                clearInterval(this.periodicTimer);
            }
            
            // Capture every 30 seconds if there's active form interaction
            this.periodicTimer = setInterval(() => {
                // CRITICAL: Check if system is destroyed before running
                if (this.isDestroyed) {
                    clearInterval(this.periodicTimer);
                    return;
                }
                
                // CRITICAL SESSION MANAGEMENT: Check if session is completed
                if (window.isSessionCompleted) {
                    console.log('BSP: Skipping periodic capture - session completed');
                    this.destroy(); // Stop all future captures
                    return;
                }
                
                if (this.hasFormData()) {
                    this.captureLeadData();
                }
            }, 30000);
            
            console.log('BSP Lead Capture: Periodic capture started with timer ID:', this.periodicTimer);
        },
        
        hasFormData: function() {
            const formData = this.collectFormData();
            return Object.keys(formData).length >= this.config.minFieldsRequired;
        },
        
        collectFormData: function() {
            const data = {};
            
            // Safely access existing booking system data
            if (window.bspLeadCapture.existingBookingSystem) {
                const bookingState = this.safeIntegration.safeAccess(
                    window.bspLeadCapture.existingBookingSystem, 
                    'getState', 
                    {}
                );
                
                if (bookingState && typeof bookingState === 'function') {
                    try {
                        const state = bookingState();
                        Object.assign(data, state);
                    } catch (e) {
                        if (this.config.debug) {
                            console.warn('BSP: Could not access booking system state', e);
                        }
                    }
                }
            }
            
            // Collect form field data directly
            const formElements = document.querySelectorAll([
                'input[name*="service"]',
                'input[name*="name"]', 
                'input[name*="email"]',
                'input[name*="phone"]',
                'input[name*="address"]',
                'input[name*="street_address"]',
                'input[name*="city"]',
                'input[name*="state"]',
                'input[name*="zip"]',
                'select[name*="company"]',
                'input[name*="roof"]',
                'input[name*="window"]',
                'input[name*="bathroom"]',
                'input[name*="siding"]',
                'input[name*="kitchen"]',
                'input[name*="deck"]',
                'input[name*="adu"]'
            ].join(', '));
            
            formElements.forEach(element => {
                if (element.value && element.value.trim() !== '') {
                    let fieldName = element.name || element.id || '';
                    
                    // Clean field name
                    fieldName = fieldName.replace(/\[|\]/g, '');
                    
                    if (element.type === 'radio' || element.type === 'checkbox') {
                        if (element.checked) {
                            data[fieldName] = element.value;
                        }
                    } else {
                        data[fieldName] = element.value;
                    }
                }
            });
            
            // Add form step information
            data.form_step = this.getCurrentFormStep();
            
            // Add session ID
            data.session_id = this.sessionId;
            
            // Add city/state from ZIP lookup service if available
            if (window.zipLookupService && (window.zipLookupService.currentCity || window.zipLookupService.currentState)) {
                data.city = window.zipLookupService.currentCity || '';
                data.state = window.zipLookupService.currentState || '';
            }
            
            // Add city/state from formState if available (detected from ZIP)
            if (typeof formState !== 'undefined') {
                if (formState.detectedCity && !data.city) {
                    data.city = formState.detectedCity;
                }
                if (formState.detectedState && !data.state) {
                    data.state = formState.detectedState;
                }
            }
            
            // CRITICAL FIX: Add appointment data for incomplete leads on confirmation page
            // Use the official getter function for safe variable access
            let appointmentsData = null;
            
            // Method 1: Use official getSelectedAppointments() function (preferred)
            if (typeof window.getSelectedAppointments === 'function') {
                try {
                    appointmentsData = window.getSelectedAppointments();
                } catch (e) {
                    console.error('BSP Lead Capture: Error accessing getSelectedAppointments()', e);
                }
            }
            
            // Method 2: Use SafeIntegration for additional security
            if (!appointmentsData && typeof SafeIntegration !== 'undefined' && SafeIntegration.safeAccess) {
                appointmentsData = SafeIntegration.safeAccess(window, 'selectedAppointments');
            }
            
            // Method 3: Direct access fallback
            if (!appointmentsData) {
                if (typeof selectedAppointments !== 'undefined' && selectedAppointments && selectedAppointments.length > 0) {
                    appointmentsData = selectedAppointments;
                } else if (window.selectedAppointments && window.selectedAppointments.length > 0) {
                    appointmentsData = window.selectedAppointments;
                }
            }
            
            // Process appointment data if found
            if (appointmentsData && Array.isArray(appointmentsData) && appointmentsData.length > 0) {
                data.appointments = JSON.stringify(appointmentsData);
                
                // Extract appointment data (structure: {company, companyId, date, time})
                const companies = [];
                const dates = [];
                const times = [];
                
                appointmentsData.forEach(apt => {
                    if (apt.company) companies.push(apt.company);
                    if (apt.date) dates.push(apt.date);
                    if (apt.time) times.push(apt.time);
                });
                
                // Set primary appointment data
                if (companies.length > 0) {
                    data.company = companies.join(', ');
                    data.company_name = companies[0]; // Primary company for compatibility
                }
                
                if (dates.length > 0) {
                    data.booking_date = dates.join(', ');
                    data.selected_date = dates[0]; // Primary date for compatibility
                    data.date = dates[0]; // Alternative field name
                }
                
                if (times.length > 0) {
                    data.booking_time = times.join(', ');
                    data.selected_time = times[0]; // Primary time for compatibility
                    data.time = times[0]; // Alternative field name
                }
                
                // SUCCESS LOG - appointments captured
                console.log('🎉 BSP: Appointments captured successfully', {
                    count: appointmentsData.length,
                    companies: companies,
                    dates: dates,
                    times: times
                });
            } else {
                console.warn('❌ BSP: No appointment data found');
            }
            // Method 4: Check hidden form fields (DOM-based fallback)  
            if (!appointmentsData) {
                console.log('===== METHOD 4: DOM FALLBACK =====');
                console.log('BSP Lead Capture: Trying DOM fallback methods...');
                
                // Check for appointments input field
                const appointmentsInput = document.querySelector('input[name="appointments"]');
                console.log('Appointments input field:', appointmentsInput);
                if (appointmentsInput && appointmentsInput.value) {
                    console.log('Appointments input value:', appointmentsInput.value);
                    try {
                        const appointments = JSON.parse(appointmentsInput.value);
                        if (appointments && appointments.length > 0) {
                            data.appointments = appointmentsInput.value;
                            appointmentsData = appointments; // Set for logging consistency
                            
                            const primary = appointments[0];
                            if (primary) {
                                data.company = primary.company || '';
                                data.selected_date = primary.date || '';
                                data.selected_time = primary.time || '';
                            }
                            
                            console.log('✓ Appointment data collected from form input', {
                                appointments_count: appointments.length,
                                primary_company: data.company
                            });
                        }
                    } catch (e) {
                        console.warn('✗ Could not parse appointments from form input', e);
                    }
                }
                
                // Method 4B: Check individual company/date/time form fields
                console.log('Checking individual form fields...');
                const companyInput = document.querySelector('input[name="company"], select[name="company"]');
                const dateInput = document.querySelector('input[name="selected_date"], input[name="booking_date"]');
                const timeInput = document.querySelector('input[name="selected_time"], input[name="booking_time"]');
                
                console.log('Individual form fields:', { 
                    companyInput: companyInput?.value, 
                    dateInput: dateInput?.value, 
                    timeInput: timeInput?.value 
                });
                
                if (companyInput && companyInput.value) {
                    data.company = companyInput.value;
                    console.log('Set company from form field:', data.company);
                }
                if (dateInput && dateInput.value) {
                    data.selected_date = dateInput.value;
                    data.booking_date = dateInput.value;
                    console.log('Set date from form field:', data.selected_date);
                }
                if (timeInput && timeInput.value) {
                    data.selected_time = timeInput.value;
                    data.booking_time = timeInput.value;
                    console.log('Set time from form field:', data.selected_time);
                }
                
                // Method 4C: EXAMINE THE MYSTERY - Check where multiple companies are coming from
                console.log('===== INVESTIGATING COMPANY SOURCE =====');
                
                // Look for any element containing company names
                const allElements = document.querySelectorAll('*');
                const elementsWithCompanyData = [];
                
                allElements.forEach(element => {
                    const text = element.textContent || '';
                    const value = element.value || '';
                    const innerHTML = element.innerHTML || '';
                    
                    if (text.includes('Home Improvement Experts') || 
                        text.includes('Pro Remodeling Solutions') || 
                        text.includes('Top Remodeling Pro') ||
                        value.includes('Home Improvement Experts') ||
                        innerHTML.includes('Home Improvement Experts')) {
                        
                        elementsWithCompanyData.push({
                            tagName: element.tagName,
                            className: element.className,
                            id: element.id,
                            name: element.name,
                            value: element.value,
                            textContent: text.substring(0, 100),
                            innerHTML: innerHTML.substring(0, 100)
                        });
                    }
                });
                
                console.log('Elements containing company data:', elementsWithCompanyData);
                
                if (data.company || data.selected_date || data.selected_time) {
                    console.log('✓ Some appointment data collected from DOM fallback', {
                        company: data.company,
                        date: data.selected_date,
                        time: data.selected_time
                    });
                } else {
                    console.log('✗ No appointment data found in DOM fallback');
                }
            }
            
            // Add UTM parameters from UTM Manager (Phase 2 enhancement)
            const utmData = this.getUTMData();
            Object.assign(data, utmData);
            
            return data;
        },
        
        getUTMData: function() {
            // Get UTM data from UTM Manager if available
            if (window.bspLeadCapture && window.bspLeadCapture.UTMManager) {
                return window.bspLeadCapture.UTMManager.getUTMForLeadCapture();
            }
            
            // Fallback to config UTM data
            return this.config.utmParams || {};
        },
        
        getCurrentFormStep: function() {
            // Try to detect current step from active elements or existing system
            let step = 0;
            
            // Check for step indicators
            const stepElements = document.querySelectorAll('[class*="step"], [id*="step"], [data-step]');
            stepElements.forEach(element => {
                if (element.classList.contains('active') || element.classList.contains('current')) {
                    const stepMatch = element.className.match(/step-?(\d+)/);
                    if (stepMatch) {
                        step = Math.max(step, parseInt(stepMatch[1]));
                    }
                }
            });
            
            // Fallback: estimate step based on filled fields (avoid circular dependency)
            if (step === 0) {
                // Direct field checks without calling collectFormData to avoid infinite loop
                if (document.querySelector('input[name="service"], select[name="service"]')?.value) step = 1;
                if (document.querySelector('input[name="full_name"], #name-input, #full-name-input')?.value) step = 2;
                if (document.querySelector('input[name="zip_code"], #zip-input, #step2-zip-input')?.value) step = 3;
            }
            
            return step;
        },
        
        captureLeadData: function(forceCapture = false) {
            // CRITICAL: Don't capture if destroyed
            if (this.isDestroyed) {
                console.log('BSP Lead Capture: Skipping capture - system destroyed');
                return;
            }
            
            // CRITICAL SESSION MANAGEMENT: Don't capture if session is completed
            if (window.isSessionCompleted) {
                console.log('BSP Lead Capture: Session completed - stopping all captures');
                this.destroy();
                return;
            }
            
            const formData = this.collectFormData();
            
            // Check if we have minimum required data
            if (!forceCapture && Object.keys(formData).length < this.config.minFieldsRequired) {
                if (this.config.debug) {
                    console.log('BSP: Insufficient data for capture', formData);
                }
                return;
            }
            
            // Check if data has changed since last capture
            const dataString = JSON.stringify(formData);
            if (!forceCapture && this.lastCaptureData === dataString) {
                if (this.config.debug) {
                    console.log('BSP: No data changes since last capture');
                }
                return;
            }
            
            this.lastCaptureData = dataString;
            
            // Send data to server
            this.sendLeadData(formData);
        },
        
        // CRITICAL: Add destroy method to clean up all resources
        destroy: function() {
            console.log('BSP Lead Capture: Destroying and cleaning up resources...');
            
            // Mark as destroyed
            this.isDestroyed = true;
            
            // Clear all timers
            if (this.captureTimer) {
                clearTimeout(this.captureTimer);
                this.captureTimer = null;
                console.log('BSP: Cleared capture timer');
            }
            
            if (this.periodicTimer) {
                clearInterval(this.periodicTimer);
                this.periodicTimer = null;
                console.log('BSP: Cleared periodic timer');
            }
            
            // Clean up event listeners would require storing references
            // For now, the checks for isDestroyed will prevent execution
            
            console.log('BSP Lead Capture: Cleanup complete');
        },
        
        sendLeadData: function(formData) {
            // CRITICAL: Don't send if destroyed
            if (this.isDestroyed) {
                console.log('BSP Lead Capture: Skipping send - system destroyed');
                return;
            }
            
            // CRITICAL SESSION MANAGEMENT: Don't send if session is completed
            if (window.isSessionCompleted) {
                console.log('BSP Lead Capture: Session completed - blocking data send');
                return;
            }
            
            // CRITICAL SESSION MANAGEMENT: Don't send if session is completed
            if (window.isSessionCompleted) {
                console.log('BSP Lead Capture: Session completed - blocking data send');
                return;
            }
            
            // Always log what we're about to send
            console.log('BSP Lead Capture: Sending data to server', {
                formData: formData,
                hasAppointments: !!formData.appointments,
                appointmentsData: formData.appointments,
                company: formData.company,
                booking_date: formData.booking_date,
                booking_time: formData.booking_time,
                all_keys: Object.keys(formData),
                session_id: formData.session_id,
                timestamp: new Date().toISOString()
            });
            
            const requestData = new FormData();
            requestData.append('action', 'bsp_capture_incomplete_lead');
            requestData.append('nonce', this.config.nonce);
            
            // Add form data
            Object.keys(formData).forEach(key => {
                requestData.append(key, formData[key]);
                
                // Log appointment-related fields specifically
                if (['appointments', 'company', 'booking_date', 'booking_time', 'selected_date', 'selected_time'].includes(key)) {
                    console.log(`BSP: Adding ${key} = ${formData[key]}`);
                }
            });
            
            // Make AJAX request
            fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: requestData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (this.config.debug) {
                    console.log('BSP Lead Capture Response:', data);
                }
                
                if (data.success) {
                    // Update localStorage with successful capture
                    this.updateLocalStorage(formData, data.data.lead_id);
                } else {
                    console.warn('BSP Lead Capture Error:', data.data);
                }
            })
            .catch(error => {
                if (this.config.debug) {
                    console.error('BSP Lead Capture Network Error:', error);
                }
                
                // Store in localStorage as fallback
                this.storeLocalBackup(formData);
            });
        },
        
        updateLocalStorage: function(formData, leadId) {
            const storageKey = this.config.storageKey + '_data';
            const storageData = {
                leadId: leadId,
                data: formData,
                timestamp: Date.now(),
                synced: true
            };
            
            this.safeIntegration.safeLocalStorage.setItem(storageKey, JSON.stringify(storageData));
        },
        
        storeLocalBackup: function(formData) {
            const storageKey = this.config.storageKey + '_backup';
            const backupData = {
                data: formData,
                timestamp: Date.now(),
                synced: false
            };
            
            this.safeIntegration.safeLocalStorage.setItem(storageKey, JSON.stringify(backupData));
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            LeadCapture.init();
        });
    } else {
        LeadCapture.init();
    }
    
    // Make LeadCapture available globally for debugging
    window.bspLeadCapture = window.bspLeadCapture || {};
    window.bspLeadCapture.LeadCapture = LeadCapture;
    
})();
