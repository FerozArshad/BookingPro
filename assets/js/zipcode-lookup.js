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
        
        this.loadZipData();
        this.initializeEventListeners();
        
        console.log('🚀 ZIP Code Lookup Service initialized');
    }

    async loadZipData() {
        if (this.isLoading || this.isDataLoaded) return;
        
        this.isLoading = true;
        console.log('📦 Loading ZIP code data...');
        
        try {
            // Check cache first
            const cachedData = this.getCachedData();
            if (cachedData) {
                this.processZipData(cachedData);
                console.log('⚡ ZIP data loaded from cache:', cachedData.length, 'zip codes');
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
            
            console.log('✅ ZIP data loaded from server:', zipData.length, 'zip codes');
            
        } catch (error) {
            console.error('❌ Failed to load ZIP data:', error);
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
        console.log('🗺️ ZIP data processed:', this.zipDataMap.size, 'entries');
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
            console.warn('⚠️ Cache read error:', error);
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
            console.log('🔍 Detected plugin path:', pluginPath);
            return pluginPath;
        }
        
        // Fallback to common WordPress plugin path
        const fallbackPath = '/wp-content/plugins/BookingPro';
        console.log('⚠️ Using fallback plugin path:', fallbackPath);
        return fallbackPath;
    }
    
    setCachedData(data) {
        try {
            // Check if localStorage has enough space
            const dataString = JSON.stringify(data);
            const dataSize = dataString.length;
            const availableSpace = this.getAvailableLocalStorageSpace();
            
            if (dataSize > availableSpace) {
                console.warn('⚠️ Insufficient localStorage space. ZIP data will not be cached.');
                console.warn(`Data size: ${(dataSize / 1024 / 1024).toFixed(2)}MB, Available: ${(availableSpace / 1024 / 1024).toFixed(2)}MB`);
                return;
            }
            
            // Try to set expiry first (smaller data)
            const expiryTime = Date.now() + (this.cacheExpiryDays * 24 * 60 * 60 * 1000);
            localStorage.setItem(this.cacheExpiry, expiryTime.toString());
            
            // Then try to set the main data
            localStorage.setItem(this.cacheKey, dataString);
            console.log('💾 ZIP data cached for', this.cacheExpiryDays, 'day(s)');
            
        } catch (error) {
            if (error.name === 'QuotaExceededError') {
                console.warn('⚠️ localStorage quota exceeded. Clearing cache and operating without caching.');
                // Clear cache and continue without caching
                this.clearCache();
                // Try to clear some space by removing other localStorage items if needed
                this.tryToFreeUpSpace();
            } else {
                console.warn('⚠️ Cache write error:', error.name || error.message || error);
            }
            // Clear any partial data that might have been written
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
                console.log(`🧹 Cleaned up ${keysToRemove.length} old cache items`);
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
     * Validate ZIP code against US database
     * @param {string} zipCode - ZIP code to validate
     * @returns {boolean} - True if valid US ZIP code
     */
    isValidZipCode(zipCode) {
        // First check basic format
        if (!zipCode || !/^\d{5}(?:-\d{4})?$/.test(zipCode)) {
            console.log('❌ Invalid ZIP format:', zipCode);
            return false;
        }
        
        // If data not loaded yet, only validate format
        if (!this.isDataLoaded) {
            console.log('⏳ ZIP data not loaded, format validation only');
            return /^\d{5}(?:-\d{4})?$/.test(zipCode);
        }
        
        // Clean the zip code (remove any dash and extra digits)
        const cleanZip = zipCode.replace(/[^\d]/g, '').substring(0, 5);
        
        // Use Map for O(1) lookup
        const zipEntry = this.zipDataMap.get(cleanZip);
        
        if (zipEntry) {
            this.currentCity = zipEntry.city;
            this.currentState = zipEntry.state_name;
            console.log('✅ Valid ZIP:', cleanZip, '→', this.currentCity, ',', this.currentState);
            return true;
        }
        
        console.log('❌ Invalid ZIP code:', cleanZip, 'not found in database');
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
                    console.log('🏙️ City display updated:', element.textContent);
                } else if (element.id === 'city-name') {
                    element.textContent = cityText;
                    console.log('🏙️ City name updated:', cityText);
                }
            }
        });
        
        // Update formState if available
        if (typeof formState !== 'undefined') {
            formState.detectedCity = this.currentCity;
            formState.detectedState = this.currentState;
            console.log('📝 FormState updated with detected city/state');
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
        
        console.log('🚨 ZIP error shown:', message);
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
        
        console.log('✅ ZIP success shown');
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
        
        // Handle form submission attempts
        document.addEventListener('click', (e) => {
            if (e.target.matches('.btn-next') || e.target.matches('button[type="submit"]')) {
                this.handleFormNavigation(e);
            }
        }, true); // Use capture phase
    }

    /**
     * Handle ZIP code input with debouncing
     * @param {HTMLElement} inputElement - ZIP code input element
     */
    handleZipInput(inputElement) {
        const value = inputElement.value.trim();
        console.log('📝 ZIP input detected:', inputElement.id, 'value:', value);
        
        // Clear previous debounce timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        
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
     * @param {boolean} isValid - Whether ZIP code is valid
     */
    updateButtonState(inputElement, isValid) {
        // Find the next button in the current step
        const currentStep = inputElement.closest('.booking-step');
        if (!currentStep) return;
        
        const nextButton = currentStep.querySelector('.btn-next');
        if (nextButton) {
            nextButton.disabled = !isValid;
            nextButton.dataset.zipValid = isValid.toString();
            
            if (isValid) {
                nextButton.classList.remove('btn-disabled');
            } else {
                nextButton.classList.add('btn-disabled');
            }
            
            console.log('🔘 Button state updated:', isValid ? 'enabled' : 'disabled');
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
        if (!zipInput) return;
        
        const zipValue = zipInput.value.trim();
        
        // Validate ZIP before allowing navigation
        if (!zipValue || zipValue.length !== 5) {
            e.preventDefault();
            e.stopImmediatePropagation();
            this.showZipError(zipInput, 'Please enter a 5-digit ZIP code');
            console.log('🚫 Navigation blocked: ZIP code required');
            return false;
        }
        
        if (!this.isValidZipCode(zipValue)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            this.showZipError(zipInput, 'Please enter a valid US ZIP code');
            console.log('🚫 Navigation blocked: Invalid ZIP code');
            return false;
        }
        
        console.log('✅ Navigation allowed: Valid ZIP code');
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