/**
 * Safe Variable Integration for Lead Capture System
 * Detects existing variables and provides safe namespace
 */

(function() {
    'use strict';
    
    // Create safe namespace early before other scripts load
    window.bspLeadCapture = window.bspLeadCapture || {};
    
    // Variable detection system
    const SafeIntegration = {
        detectedVariables: new Set(),
        safeNamespace: 'bspLeadCapture',
        
        init: function() {
            this.detectExistingVariables();
            this.setupConflictAvoidance();
            
            // Make detection methods available globally
            window.bspLeadCapture.SafeIntegration = this;
            
            if (this.config && this.config.debug) {
                console.log('BSP Safe Integration initialized', {
                    detectedVariables: Array.from(this.detectedVariables),
                    namespace: this.safeNamespace
                });
            }
        },
        
        detectExistingVariables: function() {
            const criticalVariables = [
                'formState',
                'selectedAppointments',
                'BookingSystem',
                'sessionStorage',
                'localStorage'
            ];
            
            criticalVariables.forEach(varName => {
                if (window.hasOwnProperty(varName) || this.isVariableDefined(varName)) {
                    this.detectedVariables.add(varName);
                    this.reportDetectedVariable(varName, 'global');
                }
            });
        },
        
        isVariableDefined: function(varName) {
            try {
                return typeof window[varName] !== 'undefined';
            } catch (e) {
                return false;
            }
        },
        
        reportDetectedVariable: function(varName, context) {
            if (this.config && this.config.debug) {
                console.warn(`BSP: Detected existing variable '${varName}' in ${context} context`);
            }
        },
        
        setupConflictAvoidance: function() {
            // Store references to existing critical functions
            if (typeof window.BookingSystem === 'object') {
                window.bspLeadCapture.existingBookingSystem = window.BookingSystem;
            }
            
            // Monitor for new variable assignments
            this.setupVariableMonitoring();
        },
        
        setupVariableMonitoring: function() {
            // Create proxy to monitor window object changes
            const originalWindow = window;
            const criticalVars = ['formState', 'selectedAppointments'];
            
            criticalVars.forEach(varName => {
                if (originalWindow.hasOwnProperty(varName)) {
                    // Store reference to existing variable
                    window.bspLeadCapture[`existing_${varName}`] = originalWindow[varName];
                }
            });
        },
        
        getSafeVariableName: function(requestedName) {
            if (this.detectedVariables.has(requestedName)) {
                return `${this.safeNamespace}_${requestedName}`;
            }
            return requestedName;
        },
        
        isSafeVariableName: function(varName) {
            return !this.detectedVariables.has(varName);
        },
        
        // Safe variable access with fallbacks
        safeAccess: function(obj, path, defaultValue = null) {
            try {
                const keys = path.split('.');
                let current = obj;
                
                for (const key of keys) {
                    if (current === null || current === undefined) {
                        return defaultValue;
                    }
                    current = current[key];
                }
                
                return current !== undefined ? current : defaultValue;
            } catch (e) {
                if (this.config && this.config.debug) {
                    console.warn('BSP Safe Access Error:', e);
                }
                return defaultValue;
            }
        },
        
        // Safe localStorage with error handling
        safeLocalStorage: {
            setItem: function(key, value) {
                try {
                    if (typeof Storage !== 'undefined' && window.localStorage) {
                        window.localStorage.setItem(key, value);
                        return true;
                    }
                } catch (e) {
                    console.warn('BSP: localStorage setItem failed', e);
                }
                return false;
            },
            
            getItem: function(key) {
                try {
                    if (typeof Storage !== 'undefined' && window.localStorage) {
                        return window.localStorage.getItem(key);
                    }
                } catch (e) {
                    console.warn('BSP: localStorage getItem failed', e);
                }
                return null;
            },
            
            removeItem: function(key) {
                try {
                    if (typeof Storage !== 'undefined' && window.localStorage) {
                        window.localStorage.removeItem(key);
                        return true;
                    }
                } catch (e) {
                    console.warn('BSP: localStorage removeItem failed', e);
                }
                return false;
            }
        },
        
        // Safe sessionStorage with error handling
        safeSessionStorage: {
            setItem: function(key, value) {
                try {
                    if (typeof Storage !== 'undefined' && window.sessionStorage) {
                        window.sessionStorage.setItem(key, value);
                        return true;
                    }
                } catch (e) {
                    console.warn('BSP: sessionStorage setItem failed', e);
                }
                return false;
            },
            
            getItem: function(key) {
                try {
                    if (typeof Storage !== 'undefined' && window.sessionStorage) {
                        return window.sessionStorage.getItem(key);
                    }
                } catch (e) {
                    console.warn('BSP: sessionStorage getItem failed', e);
                }
                return null;
            },
            
            removeItem: function(key) {
                try {
                    if (typeof Storage !== 'undefined' && window.sessionStorage) {
                        window.sessionStorage.removeItem(key);
                        return true;
                    }
                } catch (e) {
                    console.warn('BSP: sessionStorage removeItem failed', e);
                }
                return false;
            }
        }
    };
    
    // Initialize when config is available
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof bspSafeConfig !== 'undefined') {
            SafeIntegration.config = bspSafeConfig;
        }
        SafeIntegration.init();
    });
    
    // Also initialize immediately if config is already available
    if (typeof bspSafeConfig !== 'undefined') {
        SafeIntegration.config = bspSafeConfig;
        SafeIntegration.init();
    }
})();
