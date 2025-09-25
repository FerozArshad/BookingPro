/**
 * Advanced ZIP Code Lookup Service
 * Handles 37,000+ US ZIP codes with O(1) lookup performance
 * Includes smart caching, debounced input, and real-time validation
 */
class ZipCodeLookupService {
    constructor() {
        this.zipDataMap = new Map();
        this.currentCity = '';
        this.currentState = '';
        this.isDataLoaded = false;
        this.isLoading = false;
        this.cacheKey = 'zipcode_data_cache';
        this.cacheExpiry = 'zipcode_cache_expiry';
        this.cacheExpiryDays = 1; // Cache for 1 day
        this.debounceTimer = null;
        this.debounceDelay = 300; // 300ms debounce
        
        // LAZY LOADING: Don't load ZIP data immediately to prevent blocking page load
        // Data will be loaded on-demand when user first interacts with ZIP input
        this.initializeEventListeners();
        
        // ZIP Code Lookup Service initialization  
        // Handles US ZIP code validation and city/state lookup for form eligibility
        // Data loads lazily on first ZIP interaction to prevent blocking page load
    }

    async loadZipData() {
        if (this.isLoading || this.isDataLoaded) return;
        
        this.isLoading = true;
        // Loading ZIP code data from cache or server
        
        try {
            // Check cache first
            const cachedData = this.getCachedData();
            if (cachedData) {
                this.processZipData(cachedData);
                // Debug output removed for production
                return;
            }
            
            // Load from server - use dynamic path detection
            const pluginPath = this.getPluginPath();
            const response = await fetch(`${pluginPath}/assets/US/uszips_filtered.json`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const zipData = await response.json();
            
            // Cache the data
            this.setCachedData(zipData);
            
            // Process the data
            this.processZipData(zipData);
            
            // Debug output removed for production
            
        } catch (error) {
            this.zipDataMap = new Map();
        } finally {
            this.isLoading = false;
        }
    }
    
    processZipData(zipData) {
        // Convert array to Map for O(1) lookup performance
        this.zipDataMap.clear();
        zipData.forEach(entry => {
            this.zipDataMap.set(entry.zip, entry);
        });
        
        this.isDataLoaded = true;
        // Debug output removed for production
    }
    
    getCachedData() {
        try {
            const expiry = localStorage.getItem(this.cacheExpiry);
            if (!expiry || Date.now() > parseInt(expiry)) {
                this.clearCache();
                return null;
            }
            
            const cachedData = localStorage.getItem(this.cacheKey);
            return cachedData ? JSON.parse(cachedData) : null;
        } catch (error) {
            return null;
        }
    }
    
    /**
     * Get plugin path dynamically
     * @returns {string} - Plugin path
     */
    getPluginPath() {
        // Try to detect plugin path from current script
        const scripts = document.querySelectorAll('script[src*="zipcode-lookup.js"]');
        if (scripts.length > 0) {
            const scriptSrc = scripts[0].src;
            const pluginPath = scriptSrc.replace(/\/assets\/js\/zipcode-lookup\.js.*$/, '');
            // Debug output removed for production
            return pluginPath;
        }
        
        // Fallback to common WordPress plugin path
        const fallbackPath = '/wp-content/plugins/BookingPro';
        // Debug output removed for production
        return fallbackPath;
    }
    
    setCachedData(data) {
        try {
            // Check if localStorage has enough space
            const dataString = JSON.stringify(data);
            const dataSize = dataString.length;
            const availableSpace = this.getAvailableLocalStorageSpace();
            
            if (dataSize > availableSpace) {
                return;
            }
            
            // Try to set expiry first (smaller data)
            const expiryTime = Date.now() + (this.cacheExpiryDays * 24 * 60 * 60 * 1000);
            localStorage.setItem(this.cacheExpiry, expiryTime.toString());
            
            // Then try to set the main data
            localStorage.setItem(this.cacheKey, dataString);
            // ZIP data cached for offline use
            
        } catch (error) {
            if (error.name === 'QuotaExceededError') {
                this.clearCache();
                this.tryToFreeUpSpace();
            }
            this.clearCache();
        }
    }
    
    tryToFreeUpSpace() {
        try {
            // Remove old cache items that might be taking up space
            const keysToRemove = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && (key.includes('cache') || key.includes('temp') || key.includes('old'))) {
                    keysToRemove.push(key);
                }
            }
            
            keysToRemove.forEach(key => {
                try {
                    localStorage.removeItem(key);
                } catch (e) {
                    // Ignore individual removal errors
                }
            });
            
            if (keysToRemove.length > 0) {
                // Debug output removed for production
            }
        } catch (error) {
            // Ignore cleanup errors
        }
    }
    
    getAvailableLocalStorageSpace() {
        try {
            const testKey = 'test-storage-space';
            let size = 0;
            
            // Try to determine available space (rough estimate)
            try {
                // Most browsers have 5-10MB limit
                const maxSize = 5 * 1024 * 1024; // 5MB in characters
                const testData = 'x'.repeat(1024); // 1KB test string
                
                // Test if we can store at least 4MB (leaving 1MB buffer)
                const targetSize = 4 * 1024 * 1024;
                localStorage.setItem(testKey, 'x'.repeat(Math.min(targetSize, maxSize)));
                localStorage.removeItem(testKey);
                
                return targetSize;
            } catch (e) {
                // If 4MB fails, try smaller sizes
                const sizes = [2 * 1024 * 1024, 1024 * 1024, 512 * 1024]; // 2MB, 1MB, 512KB
                
                for (const testSize of sizes) {
                    try {
                        localStorage.setItem(testKey, 'x'.repeat(testSize));
                        localStorage.removeItem(testKey);
                        return testSize;
                    } catch (e) {
                        continue;
                    }
                }
                
                return 0; // No space available
            }
        } catch (error) {
            return 0;
        }
    }
    
    clearCache() {
        localStorage.removeItem(this.cacheKey);
        localStorage.removeItem(this.cacheExpiry);
    }

    /**
     * Ensure ZIP data is loaded for validation
     * @returns {Promise} - Promise that resolves when data is loaded
     */
    async ensureDataLoaded() {
        if (this.isDataLoaded) {
            return; // Data already loaded
        }
        
        if (!this.isLoading) {
            // Start loading if not already in progress
            await this.loadZipData();
        } else {
            // Wait for current loading to complete
            while (this.isLoading) {
                await new Promise(resolve => setTimeout(resolve, 100));
            }
        }
    }

    /**
     * Validate ZIP code against US database
     * @param {string} zipCode - ZIP code to validate
     * @returns {boolean} - True if valid US ZIP code
     */
    isValidZipCode(zipCode) {
        // First check basic format
        if (!zipCode || !/^\d{5}(?:-\d{4})?$/.test(zipCode)) {
            // Debug output removed for production
            return false;
        }
        
        // If data not loaded yet, trigger loading and only validate format for now
        if (!this.isDataLoaded && !this.isLoading) {
            // Trigger lazy loading in background
            this.ensureDataLoaded().catch(() => {
                // Silently fail - format validation still works
            });
            // Return format validation for immediate feedback
            return /^\d{5}(?:-\d{4})?$/.test(zipCode);
        }
        
        // If currently loading, return format validation
        if (this.isLoading) {
            return /^\d{5}(?:-\d{4})?$/.test(zipCode);
        }
        
        // Clean the zip code (remove any dash and extra digits)
        const cleanZip = zipCode.replace(/[^\d]/g, '').substring(0, 5);
        
        // Use Map for O(1) lookup
        const zipEntry = this.zipDataMap.get(cleanZip);
        
        if (zipEntry) {
            this.currentCity = zipEntry.city;
            this.currentState = zipEntry.state_name;
            // Debug output removed for production
            return true;
        }
        
        // Debug output removed for production
        return false;
    }

    /**
     * Get city and state for a ZIP code
     * @param {string} zipCode - ZIP code to lookup
     * @returns {Object} - {city, state} object
     */
    getCityAndState(zipCode) {
        if (!this.isDataLoaded || this.zipDataMap.size === 0) {
            return { city: '', state: '' };
        }
        
        const cleanZip = zipCode.replace(/[^\d]/g, '').substring(0, 5);
        const zipEntry = this.zipDataMap.get(cleanZip);
        
        if (zipEntry) {
            return {
                city: zipEntry.city,
                state: zipEntry.state_name
            };
        }
        
        return { city: '', state: '' };
    }

    /**
     * Update city display in step 7
     * @param {string} zipCode - ZIP code to process
     */
    updateCityDisplay(zipCode) {
        if (!this.isValidZipCode(zipCode)) return;
        
        const cityElements = [
            document.getElementById('city-name'),
            document.querySelector('.city-name'),
            document.querySelector('[id*="city"]'),
            document.querySelector('.step-title')
        ];
        
        const cityText = `${this.currentCity}, ${this.currentState}`;
        
        cityElements.forEach(element => {
            if (element) {
                // Replace [City] placeholder or update content
                if (element.textContent.includes('[City]')) {
                    element.textContent = element.textContent.replace('[City]', cityText);
                    // Debug output removed for production
                } else if (element.id === 'city-name') {
                    element.textContent = cityText;
                    // Debug output removed for production
                }
            }
        });
        
        // Update formState if available
        if (typeof formState !== 'undefined') {
            formState.detectedCity = this.currentCity;
            formState.detectedState = this.currentState;
            // Debug output removed for production
        }
        
        // Trigger incomplete lead capture when city/state is detected
        // This ensures that ZIP validation immediately captures the location data
        if (window.bspLeadCapture && window.bspLeadCapture.LeadCapture) {
            // Small delay to ensure DOM updates are complete
            setTimeout(() => {
                window.bspLeadCapture.LeadCapture.scheduleCapture();
            }, 100);
        }
    }

    /**
     * Show error message for invalid ZIP codes
     * @param {HTMLElement} inputElement - Input element to show error for
     * @param {string} message - Error message to display
     */
    showZipError(inputElement, message = 'Please enter a valid US ZIP code') {
        this.clearZipError(inputElement);
        
        // Add error styling
        inputElement.classList.add('zip-error');
        inputElement.classList.remove('zip-success');
        
        // Create error message element
        const errorDiv = document.createElement('div');
        errorDiv.className = 'zip-error-message';
        errorDiv.textContent = message;
        errorDiv.style.cssText = `
            color: #dc3545;
            font-size: 14px;
            width: 60%;
            margin: 10px auto 5px;
            margin-top: 5px;
            padding: 8px 12px;
            background-color: rgba(220, 53, 69, 0.1);
            border-radius: 4px;
            border-left: 3px solid #dc3545;
        `;
        
        // Insert error message after the input
        inputElement.parentNode.insertBefore(errorDiv, inputElement.nextSibling);
        
        // Debug output removed for production
    }

    /**
     * Clear error message and styling
     * @param {HTMLElement} inputElement - Input element to clear error for
     */
    clearZipError(inputElement) {
        inputElement.classList.remove('zip-error');
        
        // Remove any existing error message
        const errorMsg = inputElement.parentNode.querySelector('.zip-error-message');
        if (errorMsg) {
            errorMsg.remove();
        }
    }

    /**
     * Show success styling for valid ZIP codes
     * @param {HTMLElement} inputElement - Input element to show success for
     */
    showZipSuccess(inputElement) {
        this.clearZipError(inputElement);
        inputElement.classList.add('zip-success');
        inputElement.classList.remove('zip-error');
        
        // Remove success class after animation
        setTimeout(() => {
            inputElement.classList.remove('zip-success');
        }, 2000);
        
        // Debug output removed for production
    }

    /**
     * Initialize event listeners for ZIP code inputs
     */
    initializeEventListeners() {
        // Use event delegation for dynamic content
        document.addEventListener('input', (e) => {
            // Match various ZIP input patterns
            if (e.target.id === 'zip-input' || 
                e.target.id === 'step2-zip-input' || 
                e.target.id === 'step4-zip-input' ||
                e.target.matches('input[type="text"][id*="zip"]')) {
                this.handleZipInput(e.target);
            }
        });
        
        // LAZY LOADING: Load ZIP data when user focuses on ZIP input
        document.addEventListener('focus', (e) => {
            if (e.target.id === 'zip-input' || 
                e.target.id === 'step2-zip-input' || 
                e.target.id === 'step4-zip-input' ||
                e.target.matches('input[type="text"][id*="zip"]')) {
                // Trigger data loading on first focus
                this.ensureDataLoaded().catch(() => {
                    // Silently fail - input still works with format validation
                });
            }
        }, true);
        
        // Handle form submission attempts
        document.addEventListener('click', (e) => {
            if (e.target.matches('.btn-next') || e.target.matches('button[type="submit"]')) {
                this.handleFormNavigation(e);
            }
        }, true); // Use capture phase
        
        // DIRECT ZIP MODE: Initialize button state for existing ZIP inputs
        // This handles the case when page loads directly to ZIP step
        setTimeout(() => {
            const zipInputs = document.querySelectorAll('#zip-input, #step2-zip-input, #step4-zip-input, input[id*="zip"]');
            zipInputs.forEach(input => {
                if (input.offsetParent !== null) { // Only visible inputs
                    this.updateButtonState(input);
                }
            });
        }, 100); // Small delay to ensure DOM is ready
    }

    /**
     * Handle ZIP code input with debouncing
     * @param {HTMLElement} inputElement - ZIP code input element
     */
    handleZipInput(inputElement) {
        const value = inputElement.value.trim();
        // Debug output removed for production
        
        // Clear previous debounce timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        
        // IMMEDIATE button state update for better UX
        this.updateButtonState(inputElement);
        
        // Debounce the validation
        this.debounceTimer = setTimeout(() => {
            this.validateZipInput(inputElement, value);
        }, this.debounceDelay);
    }

    /**
     * Validate ZIP code input and update UI
     * @param {HTMLElement} inputElement - ZIP code input element
     * @param {string} value - ZIP code value
     */
    validateZipInput(inputElement, value) {
        if (!value) {
            this.clearZipError(inputElement);
            this.updateButtonState(inputElement, false);
            return;
        }
        
        if (value.length < 5) {
            this.clearZipError(inputElement);
            this.updateButtonState(inputElement, false);
            return;
        }
        
        if (value.length === 5) {
            const isValid = this.isValidZipCode(value);
            
            if (isValid) {
                this.showZipSuccess(inputElement);
                this.updateCityDisplay(value);
                this.updateButtonState(inputElement, true);
            } else {
                this.showZipError(inputElement, 'This ZIP code is not found in our US database');
                this.updateButtonState(inputElement, false);
            }
        }
    }

    /**
     * Update button state based on ZIP validation
     * @param {HTMLElement} inputElement - ZIP code input element
     * @param {boolean} isValid - Whether ZIP code is valid (optional)
     */
    updateButtonState(inputElement, isValid) {
        // Find the next button in the current step
        const currentStep = inputElement.closest('.booking-step');
        if (!currentStep) return;
        
        const nextButton = currentStep.querySelector('.btn-next');
        if (nextButton) {
            // If isValid is not provided, determine it from current input
            if (typeof isValid === 'undefined') {
                const value = inputElement.value.trim();
                isValid = value.length === 5 && /^\d{5}$/.test(value) && this.isValidZipCode(value);
            }
            
            nextButton.disabled = !isValid;
            nextButton.dataset.zipValid = isValid.toString();
            
            if (isValid) {
                nextButton.classList.remove('btn-disabled');
            } else {
                nextButton.classList.add('btn-disabled');
            }
            
            // Debug output removed for production
        }
    }

    /**
     * Handle form navigation and validate ZIP codes
     * @param {Event} e - Click event
     */
    handleFormNavigation(e) {
        const button = e.target;
        const currentStep = button.closest('.booking-step');
        
        if (!currentStep) return;
        
        // Check if current step has ZIP input
        const zipInput = currentStep.querySelector('#zip-input, #step2-zip-input, #step4-zip-input, input[id*="zip"]');
        if (!zipInput) return; // No ZIP input in this step, allow normal navigation
        
        const zipValue = zipInput.value.trim();
        
        // Only prevent navigation if ZIP is invalid
        if (!zipValue || zipValue.length !== 5) {
            e.preventDefault();
            e.stopImmediatePropagation();
            this.showZipError(zipInput, 'Please enter a 5-digit ZIP code');
            // Debug output removed for production
            return false;
        }
        
        if (!this.isValidZipCode(zipValue)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            this.showZipError(zipInput, 'Please enter a valid US ZIP code');
            // Debug output removed for production
            return false;
        }
        
        // ZIP is valid - clear any errors and allow navigation to proceed
        this.clearZipError(zipInput);
        // Do NOT prevent default - let the form handle navigation normally
        // Debug output removed for production
        return true;
    }

    /**
     * Get current ZIP code validation status
     * @returns {Object} - Status object with validation info
     */
    getStatus() {
        return {
            isDataLoaded: this.isDataLoaded,
            isLoading: this.isLoading,
            zipCount: this.zipDataMap.size,
            currentCity: this.currentCity,
            currentState: this.currentState
        };
    }
}

// Initialize the service when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.zipLookupService = new ZipCodeLookupService();
    });
} else {
    window.zipLookupService = new ZipCodeLookupService();
}

// Export for global access
window.ZipCodeLookupService = ZipCodeLookupService;
