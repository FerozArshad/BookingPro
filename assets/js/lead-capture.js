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
        lastCaptureData: null,
        isInitialized: false,
        
        init: async function() {
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
            
            if (this.config.debug) {
                console.log('BSP Lead Capture initialized with UTM integration', {
                    sessionId: this.sessionId,
                    config: this.config,
                    utmData: this.getUTMData()
                });
            }
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
            
            // Also capture on beforeunload
            window.addEventListener('beforeunload', () => {
                this.captureLeadData(true);
            });
        },
        
        scheduleCapture: function() {
            // Clear existing timer
            if (this.captureTimer) {
                clearTimeout(this.captureTimer);
            }
            
            // Schedule new capture
            this.captureTimer = setTimeout(() => {
                this.captureLeadData();
            }, this.config.captureDelay || 2000);
        },
        
        startPeriodicCapture: function() {
            // Capture every 30 seconds if there's active form interaction
            setInterval(() => {
                if (this.hasFormData()) {
                    this.captureLeadData();
                }
            }, 30000);
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
            if (typeof selectedAppointments !== 'undefined' && selectedAppointments && selectedAppointments.length > 0) {
                data.appointments = JSON.stringify(selectedAppointments);
                
                // Also extract primary appointment data for compatibility
                const primary = selectedAppointments[0];
                if (primary) {
                    data.company = primary.company || '';
                    data.selected_date = primary.date || '';
                    data.selected_time = primary.time || '';
                    
                    // For multiple appointments, join with commas
                    if (selectedAppointments.length > 1) {
                        data.company = selectedAppointments.map(apt => apt.company).filter(c => c).join(', ');
                        data.booking_date = selectedAppointments.map(apt => apt.date).filter(d => d).join(', ');
                        data.booking_time = selectedAppointments.map(apt => apt.time).filter(t => t).join(', ');
                    }
                }
                
                if (this.config.debug) {
                    console.log('BSP Lead Capture: Appointment data collected', {
                        appointments_count: selectedAppointments.length,
                        primary_company: data.company,
                        appointments: selectedAppointments
                    });
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
        
        sendLeadData: function(formData) {
            const requestData = new FormData();
            requestData.append('action', 'bsp_capture_incomplete_lead');
            requestData.append('nonce', this.config.nonce);
            
            // Add form data
            Object.keys(formData).forEach(key => {
                requestData.append(key, formData[key]);
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
