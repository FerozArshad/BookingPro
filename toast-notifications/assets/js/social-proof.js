/**
 * Social Proof Toast Notification System
 * Integrates with BookingPro plugin data without modifying core files
 */

(function($) {
    'use strict';

    // ===== CONFIGURATION =====
    const SOCIAL_PROOF_CONFIG = {
        // Timing settings
        minInterval: 3000,      // 4 seconds
        maxInterval: 5000,     // 10 seconds
        toastDuration: 5000,    // 3 seconds
        
        // Animation settings
        animationDuration: 300,
        
        // Queue management
        maxQueue: 3,
        
        // Debug settings
        debug: false,  // Disable debug mode to reduce console logs
        debugPrefix: '[SocialProof]',
        
        // Fallback data
        fallbackNames: [
            'Michael', 'Sarah', 'David', 'Jessica', 'James', 'Ashley', 'Robert', 'Emily',
            'John', 'Amanda', 'William', 'Jennifer', 'Daniel', 'Lisa', 'Christopher', 'Nancy',
            'Matthew', 'Karen', 'Anthony', 'Betty', 'Mark', 'Helen', 'Donald', 'Sandra',
            'Steven', 'Donna', 'Paul', 'Carol', 'Joshua', 'Ruth', 'Kenneth', 'Sharon',
            'Kevin', 'Michelle', 'Brian', 'Laura', 'George', 'Sarah', 'Edward', 'Kimberly'
        ],
        
        services: ['Roof', 'Windows', 'Bathroom', 'Siding', 'Kitchen'],
        
        // ZIP codes with city/state mapping (sample data)
        zipCodes: {
            '90210': { city: 'Beverly Hills', state: 'CA' },
            '10001': { city: 'New York', state: 'NY' },
            '60601': { city: 'Chicago', state: 'IL' },
            '77001': { city: 'Houston', state: 'TX' },
            '85001': { city: 'Phoenix', state: 'AZ' },
            '19101': { city: 'Philadelphia', state: 'PA' },
            '78701': { city: 'Austin', state: 'TX' },
            '98101': { city: 'Seattle', state: 'WA' },
            '33101': { city: 'Miami', state: 'FL' },
            '80201': { city: 'Denver', state: 'CO' },
            '02101': { city: 'Boston', state: 'MA' },
            '30301': { city: 'Atlanta', state: 'GA' },
            '97201': { city: 'Portland', state: 'OR' },
            '63101': { city: 'St. Louis', state: 'MO' },
            '28201': { city: 'Charlotte', state: 'NC' },
            '75201': { city: 'Dallas', state: 'TX' },
            '92101': { city: 'San Diego', state: 'CA' },
            '94101': { city: 'San Francisco', state: 'CA' },
            '89101': { city: 'Las Vegas', state: 'NV' },
            '55401': { city: 'Minneapolis', state: 'MN' }
        }
    };

    // ===== SOCIAL PROOF SYSTEM =====
    const SocialProof = {
        
        // State management
        isInitialized: false,
        isActive: false,
        currentToast: null,
        toastQueue: [],
        nextToastTimer: null,
        pausedTimer: null,
        isSystemDisabled: false,
        
        // Data sources
        recentBookings: [],
        availableZipCodes: [],
        
        // Session management
        sessionStorage: {
            DISABLED_KEY: 'socialProofDisabled',
            
            isDisabled: function() {
                return sessionStorage.getItem(this.DISABLED_KEY) === 'true';
            },
            
            disable: function() {
                sessionStorage.setItem(this.DISABLED_KEY, 'true');
            },
            
            enable: function() {
                sessionStorage.removeItem(this.DISABLED_KEY);
            }
        },

        /**
         * Debug logger
         */
        debug(message, data = null) {
            // Debug disabled for production
            return;
        },

        /**
         * Debug error logger
         */
        debugError(message, error = null) {
            // Debug disabled for production
            return;
        },
        
        /**
         * Initialize the social proof system
         */
        init() {
            this.debug('init() called', {
                isInitialized: this.isInitialized,
                isLoggedIn: this.isLoggedIn(),
                sessionDisabled: this.sessionStorage.isDisabled()
            });
            
            if (this.isInitialized) {
                this.debug('Already initialized, returning');
                return;
            }
            
            // For non-logged-in users, always enable the system
            // Check if system is disabled for this session (only for logged-in users)
            if (this.sessionStorage.isDisabled() && this.isLoggedIn()) {
                this.debug('System disabled for logged-in user');
                this.isSystemDisabled = true;
                return;
            }
            
            this.debug('Proceeding with initialization');
            
            // Wait for DOM to be ready
            $(document).ready(() => {
                try {
                    this.debug('DOM ready, setting up social proof');
                    this.setupContainer();
                    this.verifyCSSLoaded();
                    this.loadPluginData();
                    
                    // Set active state BEFORE starting notification cycle
                    this.isInitialized = true;
                    this.isActive = true;
                    
                    this.startNotificationCycle();
                    
                    this.debug('Social proof system initialized successfully');
                    
                } catch (error) {
                    this.debugError('Initialization failed', error);
                }
            });
        },

        /**
         * Check if user is logged in (WordPress admin bar or other indicators)
         */
        isLoggedIn() {
            return $('#wpadminbar').length > 0 || 
                   document.body.classList.contains('logged-in') ||
                   $('body').hasClass('logged-in');
        },

        /**
         * Setup the toast container in the DOM
         */
        setupContainer() {
            // Check if container already exists
            if ($('#bsp-social-proof-container').length > 0) {
                return;
            }
            
            const $container = $(`
                <div id="bsp-social-proof-container" 
                     role="status" 
                     aria-live="polite" 
                     aria-label="Social proof notifications">
                </div>
            `);
            
            $('body').append($container);
        },

        /**
         * Verify CSS has loaded
         */
        verifyCSSLoaded() {
            // Check if our CSS classes are available
            const testElement = $('<div class="bsp-social-proof-toast"></div>');
            $('body').append(testElement);
            
            // Check computed styles
            const computedStyle = window.getComputedStyle(testElement[0]);
            const hasStyles = computedStyle.position === 'fixed' || computedStyle.position === 'absolute';
            
            testElement.remove();
            
            return hasStyles;
        },

        /**
         * Load data from BookingPro plugin
         */
        loadPluginData() {
            // Check for BSP_Ajax global (BookingPro main plugin)
            if (typeof BSP_Ajax !== 'undefined' && BSP_Ajax.companies) {
                this.debug('Found BSP_Ajax global, loading booking data');
                this.loadBookingData();
            }
            // Also check for BSP_SocialProof global (our social proof integration)
            else if (typeof BSP_SocialProof !== 'undefined' && BSP_SocialProof.ajaxUrl) {
                this.debug('Found BSP_SocialProof global, loading booking data');
                this.loadBookingDataFromSocialProof();
            }
            
            // Load ZIP codes from plugin if available
            this.loadZipCodeData();
        },

        /**
         * Load booking data using BSP_SocialProof global
         */
        loadBookingDataFromSocialProof() {
            if (typeof BSP_SocialProof !== 'undefined' && BSP_SocialProof.ajaxUrl) {
                $.ajax({
                    url: BSP_SocialProof.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'bsp_get_recent_bookings_for_social_proof',
                        nonce: BSP_SocialProof.nonce || 'fallback'
                    },
                    timeout: 3000,
                    success: (response) => {
                        if (response.success && response.data) {
                            // Check if we have valid booking data (not all empty names)
                            const validBookings = response.data.filter(booking => 
                                booking.name && booking.name.trim() !== ''
                            );
                            
                            if (validBookings.length > 0) {
                                this.recentBookings = validBookings;
                                this.debug('Loaded valid booking data from BSP_SocialProof', validBookings);
                            } else {
                                this.debug('BSP_SocialProof returned bookings but all names are empty, using fallback');
                                this.recentBookings = [];
                            }
                        } else {
                            this.debug('BSP_SocialProof returned no booking data, using fallback');
                            this.recentBookings = [];
                        }
                    },
                    error: (xhr, status, error) => {
                        this.debug('BSP_SocialProof AJAX request failed, using fallback data', { xhr, status, error });
                    }
                });
            }
        },

        /**
         * Load recent booking data (simulated or from plugin)
         */
        loadBookingData() {
            // Try to fetch from plugin's AJAX endpoint
            if (typeof BSP_Ajax !== 'undefined' && BSP_Ajax.ajaxUrl) {
                $.ajax({
                    url: BSP_Ajax.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'bsp_get_recent_bookings_for_social_proof',
                        nonce: BSP_Ajax.nonce || BSP_SocialProof?.nonce || 'fallback'
                    },
                    timeout: 3000,
                    success: (response) => {
                        if (response.success && response.data) {
                            // Check if we have valid booking data (not all empty names)
                            const validBookings = response.data.filter(booking => 
                                booking.name && booking.name.trim() !== ''
                            );
                            
                            if (validBookings.length > 0) {
                                this.recentBookings = validBookings;
                                this.debug('Loaded valid booking data from server', validBookings);
                            } else {
                                this.debug('Server returned bookings but all names are empty, using fallback');
                                this.recentBookings = [];
                            }
                        } else {
                            this.debug('Server returned no booking data, using fallback');
                            this.recentBookings = [];
                        }
                    },
                    error: (xhr, status, error) => {
                        this.debug('AJAX request failed, using fallback data', { xhr, status, error });
                    }
                });
            } else {
                this.debug('No AJAX configuration found, using fallback data');
            }
        },

        /**
         * Load ZIP code data from plugin
         */
        loadZipCodeData() {
            // Extract ZIP codes from config or generate sample data
            this.availableZipCodes = Object.keys(SOCIAL_PROOF_CONFIG.zipCodes);
        },

        /**
         * Start the notification cycle
         */
        startNotificationCycle() {
            this.debug('startNotificationCycle() called', {
                isActive: this.isActive,
                isSystemDisabled: this.isSystemDisabled
            });
            
            if (!this.isActive || this.isSystemDisabled) {
                this.debug('Notification cycle not started - system inactive or disabled');
                return;
            }
            
            this.debug('Starting notification cycle');
            this.scheduleNextToast();
        },

        /**
         * Schedule the next toast notification
         */
        scheduleNextToast() {
            if (!this.isActive || this.isSystemDisabled) {
                this.debug('scheduleNextToast() - system inactive or disabled');
                return;
            }
            
            const delay = this.getRandomInterval();
            this.debug('Scheduling next toast', { delay: delay });
            
            this.nextToastTimer = setTimeout(() => {
                this.debug('Timer fired, checking conditions', {
                    isActive: this.isActive,
                    currentToast: !!this.currentToast,
                    isSystemDisabled: this.isSystemDisabled
                });
                
                if (this.isActive && !this.currentToast && !this.isSystemDisabled) {
                    this.debug('Calling showToast()');
                    this.showToast();
                }
                this.scheduleNextToast();
            }, delay);
        },

        /**
         * Generate random interval between min and max
         */
        getRandomInterval() {
            return Math.random() * (SOCIAL_PROOF_CONFIG.maxInterval - SOCIAL_PROOF_CONFIG.minInterval) + SOCIAL_PROOF_CONFIG.minInterval;
        },

        /**
         * Show a toast notification
         */
        showToast() {
            this.debug('showToast() called', {
                currentToast: !!this.currentToast,
                isSystemDisabled: this.isSystemDisabled,
                isActive: this.isActive
            });
            
            if (this.currentToast || this.isSystemDisabled) {
                this.debug('showToast() blocked', {
                    currentToast: !!this.currentToast,
                    isSystemDisabled: this.isSystemDisabled
                });
                return;
            }
            
            try {
                const notification = this.generateNotification();
                this.debug('Generated notification', notification);
                
                const $toast = this.createToastElement(notification);
                
                this.currentToast = $toast;
                
                const $container = $('#bsp-social-proof-container');
                
                if ($container.length === 0) {
                    this.debugError('Toast container not found in DOM');
                    return;
                }
                
                $container.append($toast);
                this.debug('Toast added to container');
                
                // Trigger animation
                setTimeout(() => {
                    $toast.addClass('show');
                    this.debug('Toast animation triggered');
                }, 10);
                
                // Setup auto-hide timer
                this.setupAutoHide($toast);
                
                // Setup interactions
                this.setupToastInteractions($toast);
                
            } catch (error) {
                this.debugError('Error in showToast()', error);
            }
        },

        /**
         * Generate a notification based on available data
         */
        generateNotification() {
            const templates = [
                'recent_booking',
                'time_ago_booking', 
                'daily_total'
            ];
            
            const template = templates[Math.floor(Math.random() * templates.length)];
            
            switch (template) {
                case 'recent_booking':
                    return this.generateRecentBookingNotification();
                case 'time_ago_booking':
                    return this.generateTimeAgoNotification();
                case 'daily_total':
                    return this.generateDailyTotalNotification();
                default:
                    return this.generateRecentBookingNotification();
            }
        },

        /**
         * Generate "just scheduled" notification
         */
        generateRecentBookingNotification() {
            const name = this.getRandomName();
            const service = this.getRandomService();
            const location = this.getRandomLocation();
            
            return {
                type: 'recent_booking',
                message: `${name} just scheduled a ${service} free estimate in ${location.city}, ${location.state}`,
                icon: 'check'
            };
        },

        /**
         * Generate "X minutes ago" notification
         */
        generateTimeAgoNotification() {
            const name = this.getRandomName();
            const service = this.getRandomService();
            const location = this.getRandomLocation();
            const minutes = Math.floor(Math.random() * 59) + 1;
            
            return {
                type: 'time_ago',
                message: `${name} scheduled a ${service} replacement ${minutes} minutes ago in ${location.city}, ${location.state}`,
                icon: 'check'
            };
        },

        /**
         * Generate daily total notification
         */
        generateDailyTotalNotification() {
            const total = Math.floor(Math.random() * 200) + 862; // 862+ homeowners
            
            return {
                type: 'daily_total',
                message: `${total}+ homeowners booked their free estimate in the last 24 hours`,
                icon: 'thumbs-up'
            };
        },

        /**
         * Get random name from recent bookings or fallback
         */
        getRandomName() {
            if (this.recentBookings.length > 0) {
                const booking = this.recentBookings[Math.floor(Math.random() * this.recentBookings.length)];
                return booking.name || booking.customer_name || booking.full_name;
            }
            
            return SOCIAL_PROOF_CONFIG.fallbackNames[Math.floor(Math.random() * SOCIAL_PROOF_CONFIG.fallbackNames.length)];
        },

        /**
         * Get random service
         */
        getRandomService() {
            return SOCIAL_PROOF_CONFIG.services[Math.floor(Math.random() * SOCIAL_PROOF_CONFIG.services.length)];
        },

        /**
         * Get random location
         */
        getRandomLocation() {
            const zipCode = this.availableZipCodes[Math.floor(Math.random() * this.availableZipCodes.length)];
            return SOCIAL_PROOF_CONFIG.zipCodes[zipCode];
        },

        /**
         * Create toast DOM element
         */
        createToastElement(notification) {
            const iconSVG = this.getIconSVG(notification.icon);
            
            const $toast = $(`
                <div class="bsp-social-proof-toast" role="alert" aria-live="assertive">
                    <div class="bsp-toast-content">
                        <div class="bsp-toast-icon">
                            ${iconSVG}
                        </div>
                        <div class="bsp-toast-message">
                            ${notification.message}
                        </div>
                        <button class="bsp-toast-close" aria-label="Close notification" type="button">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                <path d="M13 1L1 13M1 1L13 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `);
            
            return $toast;
        },

        /**
         * Get icon SVG
         */
        getIconSVG(iconType) {
            const icons = {
                'check': `
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M13.5 4.5L6 12L2.5 8.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                `,
                'thumbs-up': `
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M6 9L10.5 4.5L13.5 7.5V13.5H4.5L1.5 10.5V6L6 9Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                `
            };
            
            return icons[iconType] || icons.check;
        },

        /**
         * Setup auto-hide functionality
         */
        setupAutoHide($toast) {
            let hideTimer = setTimeout(() => {
                this.hideToast($toast);
            }, SOCIAL_PROOF_CONFIG.toastDuration);
            
            // Pause on hover
            $toast.on('mouseenter', () => {
                clearTimeout(hideTimer);
            });
            
            // Resume on mouse leave
            $toast.on('mouseleave', () => {
                hideTimer = setTimeout(() => {
                    this.hideToast($toast);
                }, SOCIAL_PROOF_CONFIG.toastDuration);
            });
            
            // Store timer reference
            $toast.data('hideTimer', hideTimer);
        },

        /**
         * Setup toast interactions
         */
        setupToastInteractions($toast) {
            // Close button
            $toast.find('.bsp-toast-close').on('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                // Disable system for this session (only for logged-in users)
                if (this.isLoggedIn()) {
                    this.sessionStorage.disable();
                    this.isSystemDisabled = true;
                    this.stop();
                }
                
                this.hideToast($toast);
            });
            
            // Toast click (optional link to booking page)
            $toast.find('.bsp-toast-content').on('click', (e) => {
                if ($(e.target).closest('.bsp-toast-close').length) return;
                
                // Optional: Link to booking page
                // window.location.href = '#booking-form';
            });
            
            // Keyboard accessibility
            $toast.on('keydown', (e) => {
                if (e.key === 'Escape') {
                    // Disable system for this session (only for logged-in users)
                    if (this.isLoggedIn()) {
                        this.sessionStorage.disable();
                        this.isSystemDisabled = true;
                        this.stop();
                    }
                    
                    this.hideToast($toast);
                }
            });
        },

        /**
         * Hide toast notification
         */
        hideToast($toast) {
            if (!$toast || !$toast.length) return;
            
            // Clear any pending hide timer
            const hideTimer = $toast.data('hideTimer');
            if (hideTimer) {
                clearTimeout(hideTimer);
            }
            
            // Animate out
            $toast.removeClass('show');
            
            // Remove from DOM after animation
            setTimeout(() => {
                $toast.remove();
                if (this.currentToast && this.currentToast.is($toast)) {
                    this.currentToast = null;
                }
            }, SOCIAL_PROOF_CONFIG.animationDuration);
        },

        /**
         * Stop the notification system
         */
        stop() {
            this.isActive = false;
            
            if (this.nextToastTimer) {
                clearTimeout(this.nextToastTimer);
                this.nextToastTimer = null;
            }
            
            if (this.currentToast) {
                this.hideToast(this.currentToast);
            }
        },

        /**
         * Restart the notification system
         */
        restart() {
            this.stop();
            this.isActive = true;
            this.startNotificationCycle();
        }
    };

    // ===== AUTO-INITIALIZATION =====
    
    // Comprehensive initialization with multiple fallbacks
    function initializeSocialProof() {
        try {
            // Check if jQuery is available
            if (typeof $ === 'undefined') {
                return;
            }
            
            // Check if DOM is ready
            if (document.readyState === 'loading') {
                $(document).ready(() => {
                    SocialProof.init();
                });
            } else {
                SocialProof.init();
            }
            
        } catch (error) {
            // Silent fail in production
        }
    }
    
    // Try multiple initialization methods
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initializeSocialProof, 100);
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(initializeSocialProof, 100);
        });
        
        window.addEventListener('load', () => {
            setTimeout(initializeSocialProof, 200);
        });
    }
    
    // jQuery ready as additional fallback
    if (typeof $ !== 'undefined') {
        $(document).ready(() => {
            setTimeout(initializeSocialProof, 300);
        });
    }
    
    // Force initialization after 2 seconds if nothing worked
    setTimeout(() => {
        if (!SocialProof.isInitialized) {
            initializeSocialProof();
        }
    }, 2000);

    // Expose globally for control (minimal interface)
    window.SocialProof = SocialProof;

})(typeof jQuery !== 'undefined' ? jQuery : (typeof $ !== 'undefined' ? $ : null));
