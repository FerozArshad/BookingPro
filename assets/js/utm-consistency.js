/**
 * UTM Consistency Manager - JavaScript Component
 * Phase 2A: Client-side UTM tracking and synchronization
 */

(function() {
    'use strict';
    
    // Wait for safe integration to be ready
    const waitForSafeIntegration = () => {
        return new Promise((resolve) => {
            const checkIntegration = () => {
                if (window.bspLeadCapture && window.bspLeadCapture.SafeIntegration) {
                    resolve();
                } else {
                    setTimeout(checkIntegration, 100);
                }
            };
            checkIntegration();
        });
    };
    
    const UTMManager = {
        config: null,
        safeIntegration: null,
        currentUTMData: {},
        syncInterval: null,
        lastSyncTime: 0,
        
        init: async function() {
            // Wait for safe integration
            await waitForSafeIntegration();
            
            this.config = window.bspUtmConfig || {};
            this.safeIntegration = window.bspLeadCapture.SafeIntegration;
            
            if (!this.config.ajaxUrl) {
                if (this.config.debug) {
                    console.error('BSP UTM Manager: No AJAX URL configured');
                }
                return;
            }
            
            // Initialize UTM data
            this.initializeUTMData();
            
            // Set up periodic synchronization
            this.startPeriodicSync();
            
            // Monitor for URL changes (SPA support)
            this.monitorURLChanges();
            
            // Expose methods globally
            window.bspLeadCapture.UTMManager = this;
            
            if (this.config.debug) {
                console.log('BSP UTM Manager initialized', {
                    utmData: this.currentUTMData,
                    config: this.config
                });
            }
        },
        
        initializeUTMData: function() {
            // 1. Get UTM data from URL parameters (highest priority)
            const urlUTM = this.extractUTMFromURL();
            
            // 2. Get UTM data from server/cookies (passed via localization)
            const serverUTM = this.config.utmData || {};
            
            // 3. Get UTM data from localStorage (session persistence)
            const storedUTM = this.getStoredUTMData();
            
            // 4. Merge with priority: URL > Server > Stored
            this.currentUTMData = Object.assign({}, storedUTM, serverUTM, urlUTM);
            
            // 5. Store merged data
            this.storeUTMData(this.currentUTMData);
            
            // 6. Update URL parameters if needed (without page reload)
            this.updateURLParameters();
            
            if (this.config.debug) {
                console.log('UTM data initialized', {
                    url: urlUTM,
                    server: serverUTM,
                    stored: storedUTM,
                    merged: this.currentUTMData
                });
            }
        },
        
        extractUTMFromURL: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const utmData = {};
            const utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'];
            
            utmParams.forEach(param => {
                const value = urlParams.get(param);
                if (value) {
                    utmData[param] = value;
                }
            });
            
            // Add referrer if available
            if (document.referrer) {
                utmData.referrer = document.referrer;
            }
            
            return utmData;
        },
        
        getStoredUTMData: function() {
            const storageKey = 'bsp_utm_data';
            const storedData = this.safeIntegration.safeLocalStorage.getItem(storageKey);
            
            if (storedData) {
                try {
                    const parsedData = JSON.parse(storedData);
                    
                    // Check if data is expired
                    const expiryTime = parsedData.expiry || 0;
                    if (Date.now() < expiryTime) {
                        return parsedData.data || {};
                    }
                } catch (e) {
                    if (this.config.debug) {
                        console.warn('BSP UTM: Invalid stored data', e);
                    }
                }
            }
            
            return {};
        },
        
        storeUTMData: function(utmData) {
            const storageKey = 'bsp_utm_data';
            const expiryTime = Date.now() + (this.config.cookieExpiry * 1000);
            
            const dataToStore = {
                data: utmData,
                expiry: expiryTime,
                lastUpdated: Date.now()
            };
            
            this.safeIntegration.safeLocalStorage.setItem(storageKey, JSON.stringify(dataToStore));
        },
        
        updateURLParameters: function() {
            // DISABLED: Don't automatically inject UTM parameters into URL
            // This prevents stored UTM data from appearing in clean URLs
            // UTM tracking will still work through cookies and localStorage
            
            if (this.config.debug) {
                console.log('URL parameter injection disabled - UTM data preserved in storage only', this.currentUTMData);
            }
            
            return; // Early return to disable URL modification
            
            // Original code kept for reference but disabled
            /*
            // Don't modify URL if there's no UTM data
            if (Object.keys(this.currentUTMData).length === 0) {
                return;
            }
            
            // Don't modify URL if UTM params already exist
            const currentURL = new URL(window.location);
            if (currentURL.searchParams.has('utm_source')) {
                return;
            }
            
            // Add UTM parameters to URL for consistency
            const utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'];
            let hasUpdates = false;
            
            utmParams.forEach(param => {
                if (this.currentUTMData[param]) {
                    currentURL.searchParams.set(param, this.currentUTMData[param]);
                    hasUpdates = true;
                }
            });
            
            if (hasUpdates) {
                // Update URL without page reload
                window.history.replaceState({}, '', currentURL.toString());
                
                if (this.config.debug) {
                    console.log('URL updated with UTM parameters', currentURL.toString());
                }
            }
            */
        },
        
        startPeriodicSync: function() {
            // Sync with server every 30 seconds
            this.syncInterval = setInterval(() => {
                this.syncWithServer();
            }, this.config.syncInterval || 30000);
            
            // Also sync on page visibility change
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    this.syncWithServer();
                }
            });
        },
        
        syncWithServer: function() {
            // Avoid too frequent syncing
            const now = Date.now();
            if (now - this.lastSyncTime < 10000) { // Minimum 10 seconds between syncs
                return;
            }
            
            this.lastSyncTime = now;
            
            const requestData = new FormData();
            requestData.append('action', 'bsp_sync_utm_data');
            requestData.append('nonce', this.config.nonce);
            
            // Send current UTM data
            Object.keys(this.currentUTMData).forEach(key => {
                requestData.append(`utm_data[${key}]`, this.currentUTMData[key]);
            });
            
            fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: requestData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (this.config.debug) {
                    console.log('UTM sync response:', data);
                }
                
                if (data.success && data.data.utm_data) {
                    // Update local data with server response
                    this.currentUTMData = data.data.utm_data;
                    this.storeUTMData(this.currentUTMData);
                }
            })
            .catch(error => {
                if (this.config.debug) {
                    console.error('UTM sync error:', error);
                }
            });
        },
        
        monitorURLChanges: function() {
            // Monitor for programmatic URL changes (SPA navigation)
            let lastURL = window.location.href;
            
            const checkURLChange = () => {
                const currentURL = window.location.href;
                if (currentURL !== lastURL) {
                    lastURL = currentURL;
                    this.handleURLChange();
                }
            };
            
            // Check every 500ms for URL changes
            setInterval(checkURLChange, 500);
            
            // Also listen to popstate for browser navigation
            window.addEventListener('popstate', () => {
                this.handleURLChange();
            });
        },
        
        handleURLChange: function() {
            // Re-extract UTM parameters from new URL
            const newUTMData = this.extractUTMFromURL();
            
            if (Object.keys(newUTMData).length > 0) {
                // Merge new UTM data
                this.currentUTMData = Object.assign({}, this.currentUTMData, newUTMData);
                this.storeUTMData(this.currentUTMData);
                
                // Sync with server
                this.syncWithServer();
                
                if (this.config.debug) {
                    console.log('URL change detected, UTM data updated', this.currentUTMData);
                }
            }
        },
        
        getCurrentUTMData: function() {
            return Object.assign({}, this.currentUTMData);
        },
        
        updateUTMData: function(newUTMData) {
            this.currentUTMData = Object.assign({}, this.currentUTMData, newUTMData);
            this.storeUTMData(this.currentUTMData);
            
            if (this.config.debug) {
                console.log('UTM data updated manually', this.currentUTMData);
            }
        },
        
        clearUTMData: function() {
            this.currentUTMData = {};
            const storageKey = 'bsp_utm_data';
            this.safeIntegration.safeLocalStorage.removeItem(storageKey);
            
            if (this.config.debug) {
                console.log('UTM data cleared');
            }
        },
        
        // Integration method for Lead Capture system
        getUTMForLeadCapture: function() {
            return {
                utm_source: this.currentUTMData.utm_source || '',
                utm_medium: this.currentUTMData.utm_medium || '',
                utm_campaign: this.currentUTMData.utm_campaign || '',
                utm_term: this.currentUTMData.utm_term || '',
                utm_content: this.currentUTMData.utm_content || '',
                gclid: this.currentUTMData.gclid || '',
                referrer: this.currentUTMData.referrer || '',
                utm_capture_timestamp: new Date().toISOString()
            };
        },
        
        // Validation method
        validateUTMConsistency: function(formUTMData) {
            const inconsistencies = {};
            const utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'];
            
            utmParams.forEach(param => {
                const currentValue = this.currentUTMData[param] || '';
                const formValue = formUTMData[param] || '';
                
                if (currentValue && formValue && currentValue !== formValue) {
                    inconsistencies[param] = {
                        current: currentValue,
                        form: formValue
                    };
                }
            });
            
            if (Object.keys(inconsistencies).length > 0 && this.config.debug) {
                console.warn('UTM data inconsistencies detected', inconsistencies);
            }
            
            return {
                consistent: Object.keys(inconsistencies).length === 0,
                inconsistencies: inconsistencies
            };
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            UTMManager.init();
        });
    } else {
        UTMManager.init();
    }
    
})();
