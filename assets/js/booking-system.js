// @ts-nocheck
/**
 * BookingPro Booking System
 * Multi-step booking form with appointment scheduling
 */
jQuery(document).ready(function($) {
    'use strict';
    
    const CONFIG = {
        steps: [
            { id: 'service', type: 'single-choice', question: 'Which service are you interested in?', options: ['Roof', 'Windows', 'Bathroom', 'Siding', 'Kitchen', 'Decks', 'ADU'] },
            
            // A single, dynamic ZIP code step that appears after any service is selected.
            { 
                id: 'zip_code', 
                type: 'text', 
                depends_on: ['service'], // Depends on 'service' key being present in formState
                question_template: 'Start Your {service} Remodel Today.<br>Find Local Pros Now.', 
                label: 'Enter Zip Code to check eligibility for free estimate'
            },
            
            // Roof questions
            { id: 'roof_action', depends_on: ['service', 'Roof'], type: 'single-choice', question: 'Are you looking to replace or repair your roof?', options: ['Replace', 'Repair'] },
            { id: 'roof_material', depends_on: ['service', 'Roof'], type: 'single-choice', question: 'What kind of roof material?', options: ['Asphalt', 'Metal', 'Tile', 'Flat'] },
            
            // Windows questions
            { id: 'windows_action', depends_on: ['service', 'Windows'], type: 'single-choice', question: 'Are you replacing or repairing your windows?', options: ['Replace', 'Repair'] },
            { id: 'windows_replace_qty', depends_on: ['windows_action', 'Replace'], type: 'single-choice', question: 'How many windows?', options: ['3–5', '6–9', '10+'] },
            { id: 'windows_repair_needed', depends_on: ['windows_action', 'Repair'], type: 'single-choice', question: 'We don\'t have any window pros who service window repair projects.\nWould you want pricing to fully replace 3 or more window openings?', options: ['Yes', 'No'] },
            
            // Bathroom questions
            { id: 'bathroom_option', depends_on: ['service', 'Bathroom'], type: 'single-choice', question: 'Which bathroom service do you need?', options: ['Replace bath/shower', 'Remove & install new bathroom', 'New walk-in tub'] },
            
            // Siding questions
            { id: 'siding_option', depends_on: ['service', 'Siding'], type: 'single-choice', question: 'What type of siding work?', options: ['Replace existing siding', 'Remove & replace siding', 'Add siding for a new addition', 'Install siding on a new home'] },
            { id: 'siding_material', depends_on: ['service', 'Siding'], type: 'single-choice', question: 'What siding material?', options: ['Wood composite', 'Aluminum', 'Fiber cement'] },
            
            // Kitchen questions
            { id: 'kitchen_action', depends_on: ['service', 'Kitchen'], type: 'single-choice', question: 'Are you upgrading or repairing your kitchen?', options: ['Upgrade', 'Repair'] },
            { id: 'kitchen_component', depends_on: ['service', 'Kitchen'], type: 'single-choice', question: 'Which part of the kitchen?', options: ['Countertops', 'Cabinets', 'Appliances', 'Islands'] },
            
            // Decks questions
            { id: 'decks_action', depends_on: ['service', 'Decks'], type: 'single-choice', question: 'Are you looking to replace or repair your decks?', options: ['Replace', 'Repair'] },
            { id: 'decks_material', depends_on: ['service', 'Decks'], type: 'single-choice', question: 'What material?', options: ['Cedar', 'Redwood'] },
            
            // ADU questions
            { id: 'adu_type', depends_on: ['service', 'ADU'], type: 'single-choice', question: 'What type of ADU project?', options: ['New Build', 'Addition', 'Garage Conversion'] },
            
            // Common steps
            { id: 'full_name', type: 'text', question: 'Please enter your full name' },
            { id: 'address', type: 'text', question: 'What is your street address?' },
            { id: 'contact_info', type: 'form', question: 'We have matching Pros in [city]', fields: [
                { name: 'phone', label: 'Cell Number' },
                { name: 'email', label: 'Email Address' }
            ]},
            { id: 'schedule', type: 'datetime', question: 'Select a date and time' },
            { id: 'confirmation', type: 'summary', question: 'Please review & confirm your booking' }
        ],
        companies: ['Top Remodeling Pro', 'RH Remodeling', 'Eco Green']
    };

    // ─── STATE MANAGEMENT ─────────────────────────────
    let currentStepIndex = 0;
    let formState = {};
    let selectedAppointments = []; // Array to store multiple company/date/time selections
    let isSessionCompleted = false; // Track if session is completed to prevent duplicate submissions
    let isSubmissionInProgress = false; // Track if form submission is in progress to prevent multiple submissions
    
    // Make selectedAppointments accessible globally for lead capture system (non-destructive approach)
    window.getSelectedAppointments = function() {
        return selectedAppointments;
    };
    
    // Also expose it via window for backward compatibility, but keep original declaration intact
    Object.defineProperty(window, 'selectedAppointments', {
        get: function() { return selectedAppointments; },
        set: function(value) { selectedAppointments = value; },
        enumerable: true,
        configurable: true
    });

    // Expose session completion status globally
    Object.defineProperty(window, 'isSessionCompleted', {
        get: function() { return isSessionCompleted; },
        set: function(value) { isSessionCompleted = value; },
        enumerable: true,
        configurable: true
    });

    // Expose submission progress status globally
    Object.defineProperty(window, 'isSubmissionInProgress', {
        get: function() { return isSubmissionInProgress; },
        set: function(value) { isSubmissionInProgress = value; },
        enumerable: true,
        configurable: true
    });

    let bookingFormEntryType = 'unknown';
    (function detectEntryMethod() {
        // If URL already has service parameters or hash when page loads, it's direct access
        if (window.location.search.includes('service=') || window.location.hash.length > 1) {
            bookingFormEntryType = 'direct_url';
        } 
        // If we have a same-origin referrer (service selection page), even if referrer is empty in some cases
        else if (document.referrer && document.referrer.includes(window.location.origin)) {
            bookingFormEntryType = 'from_service_selection';
        }
           else if (!document.referrer || document.referrer === '') {
            // Empty referrer could mean direct access, typing URL, or bookmark
            bookingFormEntryType = 'direct_url';
        }
        // External referrer - definitely direct access
        else {
            bookingFormEntryType = 'direct_url';
        }
        
        // Store globally for access by other functions
        window.bookingFormEntryType = bookingFormEntryType;
    })();

    // Initialize user tracking
    (function initializeUserTracking() {

        // Track page visibility changes
        document.addEventListener('visibilitychange', function() {
            const state = document.visibilityState;
            // Track user engagement via visibility changes
            if (state === 'hidden') {
                // User left page - potential lead capture opportunity
            } else if (state === 'visible') {
                // User returned to page - continued engagement
            }
            }
        });

        // Track when user is about to leave the page
        window.addEventListener('beforeunload', function(event) {
            // CRITICAL SESSION MANAGEMENT: Don't capture if session is completed
            if (isSessionCompleted) {
                return;
            }
            
            // Capture incomplete lead data before leaving
            captureIncompleteLeadData('beforeunload');
        });

        // Track page load time
        window.pageLoadTime = Date.now();
        
        // Track form field interactions and capture lead data
        $(document).on('input change', 'input, select, textarea', function(e) {
            const fieldName = e.target.name || e.target.id || e.target.className;
            const fieldValue = e.target.type === 'password' ? '[HIDDEN]' : e.target.value;
            // Track form field changes for lead capture
            
            // Update form state based on field name/id
            if (fieldName.includes('zip') || e.target.id.includes('zip')) {
                formState.zip_code = fieldValue;
            }
            if (fieldName.includes('name') && !fieldName.includes('company')) {
                formState.full_name = fieldValue;
            }
            if (fieldName.includes('email')) {
                formState.email = fieldValue;
            }
            if (fieldName.includes('phone')) {
                formState.phone = fieldValue;
            }
            if (fieldName.includes('address')) {
                formState.address = fieldValue;
            }
            
            // Debounced lead capture (only after 2 seconds of no activity)
            clearTimeout(window.leadCaptureTimeout);
            window.leadCaptureTimeout = setTimeout(function() {
                // CRITICAL SESSION MANAGEMENT: Don't capture if session is completed
                if (isSessionCompleted) {
                    return;
                }
                captureIncompleteLeadData('form_interaction');
            }, 2000);
        });

        // Track button clicks and capture lead data
        $(document).on('click', 'button, .btn', function(e) {
            const buttonText = $(this).text().trim();
            const buttonClass = $(this).attr('class') || '';
            // Capture lead data on significant button clicks
            if (buttonClass.includes('service-option') || buttonClass.includes('option-btn') || 
                buttonClass.includes('btn-request-estimate') || buttonClass.includes('btn-next')) {
                // CRITICAL SESSION MANAGEMENT: Don't capture if session is completed
                if (isSessionCompleted) {
                    return;
                }
                
                captureIncompleteLeadData('button_click', {button_action: buttonText});
            }
        });

        // Track step changes
        window.originalNextStep = window.nextStep;
        window.nextStep = function() {
            console.log('➡️ Moving to Next Step from:', currentStepIndex);
            if (window.originalNextStep) {
                window.originalNextStep();
            }
            console.log('✅ Now on Step:', currentStepIndex);
        };

        window.originalPreviousStep = window.previousStep;
        window.previousStep = function() {
            console.log('⬅️ Moving to Previous Step from:', currentStepIndex);
            if (window.originalPreviousStep) {
                window.originalPreviousStep();
            }
            console.log('✅ Now on Step:', currentStepIndex);
        };
    })();

    // ─── URL HASH MANAGEMENT (NON-BREAKING) ──────────
    function updateURLHash(hashType) {
        
        try {
            let newHash = '';
            let queryParam = '';
            
            switch(hashType) {
                case 'service-selection':
                    // Dynamic hash and query param based on selected service
                    if (formState.service) {
                        newHash = formState.service.toLowerCase();
                        queryParam = formState.service.toLowerCase();
                    } else {
                        newHash = 'service-selection';
                        queryParam = '';
                    }
                    break;
                case 'scheduling':
                    // Service-specific scheduling hash
                    if (formState.service) {
                        newHash = formState.service.toLowerCase() + '-scheduling';
                        queryParam = formState.service.toLowerCase();
                    } else {
                        newHash = 'scheduling';
                        queryParam = '';
                    }
                    break;
                case 'booking-confirmed':
                    // Service-specific booking confirmation hash
                    if (formState.service) {
                        newHash = formState.service.toLowerCase() + '-booking-confirmed';
                        queryParam = formState.service.toLowerCase();
                    } else {
                        newHash = 'booking-confirmed';
                        queryParam = '';
                    }
                    break;
                case 'waiting-booking-confirmation':
                    // Service-specific waiting confirmation hash
                    if (formState.service) {
                        newHash = formState.service.toLowerCase() + '-waiting-booking-confirmation';
                        queryParam = formState.service.toLowerCase();
                    } else {
                        newHash = 'waiting-booking-confirmation';
                        queryParam = '';
                    }
                    break;
                case 'clear':
                    // Remove hash when back to initial state
                    newHash = '';
                    queryParam = '';
                    break;
                default:
                    newHash = hashType || '';
                    queryParam = formState.service ? formState.service.toLowerCase() : '';
                    break;
            }
            
            // Update URL with both hash and query parameter using history.pushState
            if (window.history && window.history.pushState) {
                const currentURL = new URL(window.location);
                
                // Update query parameter
                if (queryParam) {
                    currentURL.searchParams.set('service', queryParam);
                } else {
                    currentURL.searchParams.delete('service');
                }
                
                // Update hash
                if (newHash) {
                    currentURL.hash = newHash;
                } else {
                    currentURL.hash = '';
                }
            
                const useReplaceState = window.bookingFormEntryType === 'from_service_selection';
                
                // SPECIAL CASE: For direct form access with no service, create initial history entry
                if (window.bookingFormEntryType === 'direct_url' && !formState.service && hashType === 'clear') {
                    // This is the initial service selection step for direct access - create history entry
                    window.history.pushState({ 
                        step: 'service-selection', 
                        stepIndex: 0, 
                        service: null,
                        timestamp: Date.now()
                    }, 'Service Selection', currentURL.toString());
                    return; // Exit early
                }
                
                if (useReplaceState) {
                    // Replace current history entry instead of adding new one
                    window.history.replaceState({ 
                        step: hashType, 
                        stepIndex: currentStepIndex, 
                        service: formState.service,
                        timestamp: Date.now()
                    }, document.title, currentURL.toString());
                } else {
                    // Push new state without reloading (normal behavior for direct URL access)
                    window.history.pushState({ 
                        step: hashType, 
                        stepIndex: currentStepIndex, 
                        service: formState.service,
                        timestamp: Date.now()
                    }, document.title, currentURL.toString());
                }
            }
        } catch (error) {
        }
    }

    // ─── URL SERVICE DETECTION (HYBRID APPROACH) ─────
    function getServiceFromURL() {
        // If server-side already detected a service, use that (highest priority)
        if (window.BOOKING_PRESELECTED_SERVICE) {
            return window.BOOKING_PRESELECTED_SERVICE;
        }
        
        try {
            const validServices = ['roof', 'windows', 'bathroom', 'siding', 'kitchen', 'decks', 'adu'];
            
            // Check query parameter first (?service=roof)
            const urlParams = new URLSearchParams(window.location.search);
            const serviceParam = urlParams.get('service');
            
            if (serviceParam && validServices.includes(serviceParam.toLowerCase())) {
                const detectedService = serviceParam.charAt(0).toUpperCase() + serviceParam.slice(1).toLowerCase();
                return detectedService;
            }
            
            // Check hash second (#roof)
            const hash = window.location.hash.replace('#', '').toLowerCase();
            
            if (hash && validServices.includes(hash)) {
                const detectedService = hash.charAt(0).toUpperCase() + hash.slice(1).toLowerCase();
                return detectedService;
            }
            
            // If invalid service detected, redirect to clean URL
            if ((serviceParam && !validServices.includes(serviceParam.toLowerCase())) ||
                (hash && hash !== '' && !validServices.includes(hash))) {
                redirectToLandingPage();
                return null;
            }
            
            return null;
        } catch (error) {
            return null;
        }
    }

    // ─── REDIRECT TO LANDING PAGE ────────────────────
    function redirectToLandingPage() {
        try {
            if (window.history && window.history.pushState) {
                // Clear URL and go to clean landing page
                const cleanURL = window.location.pathname;
                window.history.pushState('', document.title, cleanURL);
            }
        } catch (error) {
            // Silently fail
        }
    }

    // ─── FIND FIRST SERVICE STEP ─────────────────────
    function findFirstServiceStep(service) {
        // Find the first step that depends on this service (usually the ZIP code step)
        const serviceStepIndex = CONFIG.steps.findIndex(step => 
            step.depends_on && 
            step.depends_on[0] === 'service' && 
            step.depends_on[1] === service
        );
        
        // If found, return that index, otherwise start from service selection (0)
        return serviceStepIndex !== -1 ? serviceStepIndex : 0;
    }

    // ─── FIND FIRST SERVICE-SPECIFIC STEP (SKIP SERVICE SELECTION) ───
    function findFirstServiceSpecificStep(service) {
        // First, look for the generic zip_code step that appears after any service selection
        const zipCodeStepIndex = CONFIG.steps.findIndex(step => 
            step.id === 'zip_code' && 
            step.depends_on && 
            step.depends_on[0] === 'service' && 
            step.depends_on.length === 1
        );
        
        if (zipCodeStepIndex !== -1) {
            return zipCodeStepIndex;
        }
        
        // If no generic zip_code step found, find the first step that depends on this specific service
        const serviceStepIndex = CONFIG.steps.findIndex(step => 
            step.depends_on && 
            step.depends_on[0] === 'service' && 
            step.depends_on[1] === service
        );
        
        return serviceStepIndex !== -1 ? serviceStepIndex : 0;
    }

    // ─── UPDATE STEP URL ──────────────────────────────
    function updateStepURL(step) {
        try {
            // Don't update URL if we're in the service selection step 
            // (handled separately in showServiceStep)
            if (step.id === 'service') {
                return;
            }
            
            // Update URL based on step type and service
            if (step.id === 'schedule') {
                updateURLHash('scheduling');
            } else if (step.id === 'confirmation') {
                // Show waiting-booking-confirmation URL when user reaches confirmation page
                updateURLHash('waiting-booking-confirmation');
            } else if (formState.service) {
                // For other steps, just maintain the service selection hash
                updateURLHash('service-selection');
            }
        } catch (error) {
            // Silently fail to avoid breaking functionality
        }
    }

    // ─── INITIALIZATION ───────────────────────────────
    initBookingSystem();

    function initBookingSystem() {
        // Set up demo data if WordPress data is not available
        if (typeof BSP_Ajax === 'undefined') {
            window.BSP_Ajax = {
                ajaxUrl: '/wp-admin/admin-ajax.php',
                nonce: 'demo-nonce',
                companies: [
                    { id: 1, name: 'Top Remodeling Pro', phone: '(555) 123-4567', address: '123 Main St, Los Angeles, CA' },
                    { id: 2, name: 'RH Remodeling', phone: '(555) 234-5678', address: '456 Oak Ave, Los Angeles, CA' },
                    { id: 3, name: 'Eco Green', phone: '(555) 345-6789', address: '789 Pine St, Los Angeles, CA' }
                ]
            };
        }
        
        // Check for server-side preselected service (NEW - priority over client-side)
        let preselectedService = window.BOOKING_PRESELECTED_SERVICE || getServiceFromURL();
        
        // Store globally for access by other functions
        window.currentPreselectedService = preselectedService;
        
        if (preselectedService || window.BOOKING_SKIP_STEP_1) {
            // Auto-select service and skip service selection step
            formState.service = preselectedService;
            
            // Skip directly to first service-specific step (ZIP code step)
            const newStepIndex = findFirstServiceSpecificStep(preselectedService);
            currentStepIndex = newStepIndex;
            
            // PERFORMANCE: No DOM manipulation here - server-side CSS already handles visibility
            // Just ensure internal state is correct without touching DOM
            
            // Configure step 2 for ZIP input mode if in direct ZIP mode
            if (window.BOOKING_DIRECT_ZIP_MODE) {
                // Use setTimeout 0 to ensure DOM is ready before manipulating
                setTimeout(() => {
                    $('#step2-options').hide();
                    $('#step2-text-input').show();
                    
                    // Set the ZIP step title immediately
                    const zipStep = CONFIG.steps.find(step => step.id === 'zip_code');
                    if (zipStep && zipStep.question_template) {
                        const title = zipStep.question_template.replace('{service}', preselectedService);
                        $('#step2-title').html(title);
                    }
                    
                    // Set ZIP input label
                    $('#step2-label').text('Enter Zip Code to check eligibility for free estimate');
                    
                    // Add verification section for direct ZIP mode
                    const $step2El = $('.booking-step[data-step="2"]');
                    if ($step2El.length > 0) {
                        addVerificationSection($step2El, preselectedService);
                    }
                }, 0);
            }
        }
        
        // Set default background with service context for direct ZIP mode
        if (preselectedService && window.BOOKING_DIRECT_ZIP_MODE) {
            // For direct ZIP mode, ensure proper service background is set
            updateBackground(preselectedService, 'zip_code');
        } else {
            updateBackground();
        }
        
        // Initialize first step (or preselected step)
        renderCurrentStep();
        
        // BROWSER BACK FIX: For direct URL access, ensure proper navigation history
        if (window.bookingFormEntryType === 'direct_url' && preselectedService) {
            // Add a flag to track that we need to create proper history
            window.needsHistorySetup = true;
        }
        // This ensures browser back button has a service selection step to return to
        if (window.bookingFormEntryType === 'direct_url' && preselectedService && window.history) {
            setTimeout(() => {
                // Create a service selection history entry that preserves the service
                const serviceSelectionUrl = window.location.pathname + '?service=' + preselectedService.toLowerCase() + '#service-selection';
                window.history.pushState(
                    { step: 'service-selection', stepIndex: 0, service: preselectedService }, 
                    'Service Selection - ' + preselectedService, 
                    serviceSelectionUrl
                );
                
                // Now restore the current URL
                const currentUrl = window.location.pathname + '?service=' + preselectedService.toLowerCase() + '#' + preselectedService.toLowerCase();
                window.history.pushState(
                    { step: preselectedService.toLowerCase(), stepIndex: currentStepIndex, service: preselectedService }, 
                    preselectedService + ' Form', 
                    currentUrl
                );
            }, 100);
        }
        
        // Bind global events
        bindGlobalEvents();
        
        // Initialize hash navigation support (non-breaking)
        initHashNavigation();
        
        // This ensures immediate form rendering while background loading ZIP data
        if (window.requestIdleCallback) {
            // Use idle time to load ZIP data
            requestIdleCallback(() => {
                if (window.zipLookupService && !window.zipLookupService.isDataLoaded) {
                    window.zipLookupService.ensureDataLoaded().catch(() => {
                        // Silently fail - ZIP validation will work with format-only
                    });
                }
            });
        } else {
            // Fallback: load after short delay
            setTimeout(() => {
                if (window.zipLookupService && !window.zipLookupService.isDataLoaded) {
                    window.zipLookupService.ensureDataLoaded().catch(() => {
                        // Silently fail - ZIP validation will work with format-only
                    });
                }
            }, 1000); // 1 second delay to ensure form is fully rendered
        }
        

    }
    
    // ─── HASH NAVIGATION SUPPORT (NON-BREAKING) ──────
    function initHashNavigation() {
        try {
            function handleBrowserBack(event) {
                // Only handle if we're on a booking form page
                if (!$('#booking-form').length) {
                    return;
                }
                
                try {
                    previousStep();
                    
                } catch (error) {
                    // Silently handle navigation error
                }
            }
            
            // Listen for browser back/forward navigation
            window.addEventListener('popstate', handleBrowserBack);
            
            // Setup history for direct URL access
            if (window.needsHistorySetup && window.currentPreselectedService) {
                setTimeout(() => {
                    const serviceUrl = `${window.location.pathname}?service=${window.currentPreselectedService.toLowerCase()}#service-selection`;
                    window.history.replaceState(
                        { step: 'service-selection', stepIndex: 0, service: window.currentPreselectedService }, 
                        'Service Selection', 
                        serviceUrl
                    );
                    
                    // Push current step
                    const currentUrl = window.location.href;
                    const stepHash = window.currentPreselectedService.toLowerCase();
                    window.history.pushState(
                        { step: stepHash, stepIndex: currentStepIndex, service: window.currentPreselectedService }, 
                        `${window.currentPreselectedService} - Step ${currentStepIndex + 1}`, 
                        currentUrl
                    );
                    
                    window.needsHistorySetup = false;
                }, 150);
            }
            
            // Legacy hash navigation support (simplified)
            function handleLegacyNavigation(hash) {
                if (!hash) return;
                
                if (hash === 'service-selection' || ['roof', 'windows', 'bathroom', 'siding', 'kitchen', 'decks', 'adu'].includes(hash)) {
                    if (hash !== 'service-selection' && currentStepIndex === 0) {
                        formState.service = hash.charAt(0).toUpperCase() + hash.slice(1);
                    }
                }
            }
            
        } catch (error) {
            // Silently handle error
        }
    }

    // ─── DYNAMIC BACKGROUND MANAGEMENT ──────────────
    function updateBackground(service = null, stepId = null) {
        const $form = $('#booking-form');
        
        // Remove existing service and step-specific classes
        $form.removeClass('service-roof service-windows service-bathroom service-siding service-kitchen service-decks service-adu');
        $form.removeClass('roof-step-0 roof-step-1 roof-step-2 windows-step-0 windows-step-1 windows-step-2a windows-step-2b bathroom-step-0 bathroom-step-1 siding-step-0 siding-step-1 siding-step-2 kitchen-step-0 kitchen-step-1 kitchen-step-2 decks-step-0 decks-step-1 decks-step-2 adu-step-0 adu-step-1 adu-step-2');
        $form.removeClass('common-step-last fallback-bg');
        
        // Add service-specific class if service is selected
        if (service) {
            $form.addClass(`service-${service.toLowerCase()}`);
            
            // Check if this is a common step (steps that appear after service-specific steps)
            const commonSteps = ['full_name', 'address', 'contact_info', 'schedule', 'confirmation'];
            const isCommonStep = commonSteps.includes(stepId);
            
            if (isCommonStep) {
                // For common steps, use the last service-specific background
                $form.addClass('common-step-last');
                
                // Add the appropriate last step class for each service
                if (service === 'Roof') {
                    $form.addClass('roof-step-2'); // Last roof step background
                } else if (service === 'Windows') {
                    // Use the last windows step background based on the path taken
                    // Check formState to determine which path was taken
                    if (formState.windows_action === 'Replace') {
                        $form.addClass('windows-step-2a');
                    } else if (formState.windows_action === 'Repair') {
                        $form.addClass('windows-step-2b');
                    } else {
                        // Fallback: if windows_action is not set, use step-1 as default
                        $form.addClass('windows-step-1');
                    }
                } else if (service === 'Bathroom') {
                    $form.addClass('bathroom-step-1'); // Last bathroom step background
                } else if (service === 'Siding') {
                    $form.addClass('siding-step-2'); // Last siding step background
                } else if (service === 'Kitchen') {
                    $form.addClass('kitchen-step-2'); // Last kitchen step background
                } else if (service === 'Decks') {
                    $form.addClass('decks-step-2'); // Last decks step background
                } else if (service === 'ADU') {
                    $form.addClass('adu-step-2'); // Last ADU step background
                }
            } else {
                // For service-specific steps, use step-specific backgrounds
                
                // Handle ZIP code step background when accessed directly
                if (stepId === 'zip_code') {
                    // Use the service's general background for the ZIP code step
                    // Based on actual image files: bathroom-all-step.webp, kitchen-all-steps.webp, etc.
                    const serviceClass = `${service.toLowerCase()}-step-0`;
                    $form.addClass(serviceClass);
                    return; // Exit early to avoid other step logic
                }

                // Add step-specific background for roof service
                if (service === 'Roof' && stepId) {
                    if (stepId === 'roof_action') {
                        $form.addClass('roof-step-1');
                    } else if (stepId === 'roof_material') {
                        $form.addClass('roof-step-2');
                    }
                }
                
                // Add step-specific background for windows service
                if (service === 'Windows' && stepId) {
                    if (stepId === 'windows_action') {
                        $form.addClass('windows-step-1');
                    } else if (stepId === 'windows_replace_qty') {
                        $form.addClass('windows-step-2a');
                    } else if (stepId === 'windows_repair_needed') {
                        $form.addClass('windows-step-2b');
                    }
                }
                
                // Add step-specific background for bathroom service
                if (service === 'Bathroom' && stepId) {
                    if (stepId === 'bathroom_option') {
                        $form.addClass('bathroom-step-1'); // Service type question
                    }
                }
                
                // Add step-specific background for siding service
                if (service === 'Siding' && stepId) {
                    if (stepId === 'siding_option') {
                        $form.addClass('siding-step-1'); // Work type question
                    } else if (stepId === 'siding_material') {
                        $form.addClass('siding-step-2'); // Material question
                    }
                }
                
                // Add step-specific background for kitchen service
                if (service === 'Kitchen' && stepId) {
                    if (stepId === 'kitchen_action') {
                        $form.addClass('kitchen-step-1'); // Upgrade/Repair question
                    } else if (stepId === 'kitchen_component') {
                        $form.addClass('kitchen-step-2'); // Component selection question
                    }
                }
                
                // Add step-specific background for decks service
                if (service === 'Decks' && stepId) {
                    if (stepId === 'decks_action') {
                        $form.addClass('decks-step-1'); // Replace/Repair question
                    } else if (stepId === 'decks_material') {
                        $form.addClass('decks-step-2'); // Material selection question
                    }
                }
                
                // Add step-specific background for ADU service
                if (service === 'ADU' && stepId) {
                    if (stepId === 'adu_action') {
                        $form.addClass('adu-step-1'); // Replace/Repair question
                    } else if (stepId === 'adu_type') {
                        $form.addClass('adu-step-2'); // Type selection question
                    }
                }
            }
        } else {
            // No service selected or error case - use fallback
            $form.addClass('fallback-bg');
        }
    }

    function resetStep2State() {
        $('#step2-text-input').hide();
        $('#step2-options').show();
        $('#step2-options').empty();
        $('#step2-zip-input, #zip-input').val('');
        $('.booking-step[data-step="2"] .btn-next').prop('disabled', true);
        // Remove any verification sections when resetting
        $('.booking-step .verification-section').remove();
    }

    function renderCurrentStep() {
        const step = CONFIG.steps[currentStepIndex];
        
        if (!step) {
            return;
        }

        if (step.depends_on) {
            const [dependsKey, dependsValue] = step.depends_on;
            
            // Handle the generic zip_code step dependency
            if (dependsKey === 'service' && step.depends_on.length === 1) {
                // This is the generic zip_code step - show it if any service is selected
                if (!formState.service) {
                    // Skip zip_code step if no service is selected
                    currentStepIndex++;
                    renderCurrentStep();
                    return;
                }
                // Service is selected, continue to show the zip_code step
            } else if (dependsKey === 'service' && dependsValue) {
                // This is a service-specific step - check if the service matches (case-insensitive)
                const currentService = formState[dependsKey];
                const expectedService = dependsValue;
                const servicesMatch = currentService && expectedService && 
                    currentService.toLowerCase() === expectedService.toLowerCase();
                
                if (!servicesMatch) {
                    // Skip this step if service doesn't match
                    currentStepIndex++;
                    renderCurrentStep();
                    return;
                }
                // Service matches, show step
            } else if (formState[dependsKey] !== dependsValue) {
                // Skip this step for other dependencies
                currentStepIndex++;
                renderCurrentStep();
                return;
            }
        }

        // PERFORMANCE OPTIMIZATION: Skip DOM manipulation if server-side already handled visibility
        // But only for the initial ZIP step render, not for subsequent steps
        if (window.BOOKING_DIRECT_ZIP_MODE && step.id === 'zip_code' && currentStepIndex === 1) {
            // Server-side CSS already made step 2 visible and step 1 hidden
            // Just update the internal state and proceed with minimal DOM operations
            updateBackground(formState.service, step.id);
            updateProgress();
            updateStepURL(step);
            showTextStep(step);
            return;
        }

        // Hide all steps and explicitly clear step 2 dual mode
        $('.booking-step').removeClass('active').hide();
        
        // SAFETY: Remove direct-zip-mode class if we're showing any step other than ZIP code step
        // This ensures CSS doesn't permanently hide other steps
        if (step.id !== 'zip_code') {
            $('#booking-form').removeClass('direct-zip-mode');
        }
        
        // FIX: Remove required attributes from hidden form fields to prevent validation errors
        $('.booking-step:not(.active) input[required], .booking-step:not(.active) select[required], .booking-step:not(.active) textarea[required]').removeAttr('required');
        
        // Reset step 2 state to prevent ZIP/choice conflicts (preserves ZIP values when on ZIP step)
        resetStep2State();
        
        // Update background based on current service and step
        updateBackground(formState.service, step.id);
        
        // Update progress
        updateProgress();
        
        // Update URL based on current step
        updateStepURL(step);
        
        // Show appropriate step based on type
        switch (step.type) {
            case 'single-choice':
                if (step.id === 'service') {
                    showServiceStep();
                } else {
                    showChoiceStep(step);
                }
                break;
            case 'text':
                showTextStep(step);
                break;
            case 'form':
                showFormStep(step);
                break;
            case 'datetime':
                showDateTimeStep();
                break;
            case 'summary':
                showSummaryStep();
                break;
        }
        
        // Pre-fetch calendar data when approaching the datetime step for optimization
        preFetchCalendarDataIfNeeded();
    }

    // ─── PRE-FETCHING OPTIMIZATION ─────────────────────
    function preFetchCalendarDataIfNeeded() {
        // PERFORMANCE: Only pre-fetch if user is likely to reach calendar step
        const currentStep = CONFIG.steps[currentStepIndex];
        
        // Check if next step or step after next is datetime (accounting for dependencies)
        const nextStep = CONFIG.steps[currentStepIndex + 1];
        const stepAfterNext = CONFIG.steps[currentStepIndex + 2];
        
        const shouldPreFetch = (nextStep && nextStep.type === 'datetime') || 
                              (stepAfterNext && stepAfterNext.type === 'datetime');
        
        if (shouldPreFetch && !window.calendarDataPreFetched) {
            // LAZY LOAD: Use requestIdleCallback for non-blocking pre-fetch
            if (window.requestIdleCallback) {
                requestIdleCallback(() => {
                    setupCompanyConfiguration();
                    window.calendarDataPreFetched = true;
                });
            } else {
                // Fallback for browsers without requestIdleCallback
                setTimeout(() => {
                    setupCompanyConfiguration();
                    window.calendarDataPreFetched = true;
                }, 100);
            }
        }
    }
    
    // ─── LAZY COMPANY CONFIGURATION ─────────────────────
    function setupCompanyConfiguration() {
        if (typeof BSP_Ajax !== 'undefined' && BSP_Ajax.companies) {
            CONFIG.companies = BSP_Ajax.companies;
            CONFIG.ajaxUrl = BSP_Ajax.ajaxUrl;
            CONFIG.nonce = BSP_Ajax.nonce;
        } else if (!CONFIG.companies || CONFIG.companies.length === 0) {
            CONFIG.companies = [
                { id: 1, name: 'RH Remodeling', phone: '(555) 123-4567', address: '123 Main St, Los Angeles, CA' },
                { id: 2, name: 'Eco Green', phone: '(555) 234-5678', address: '456 Oak Ave, Los Angeles, CA' },
                { id: 3, name: 'Top Remodeling Pro', phone: '(555) 345-6789', address: '789 Pine St, Los Angeles, CA' }
            ];
            CONFIG.ajaxUrl = '/wp-admin/admin-ajax.php';
            CONFIG.nonce = 'demo-nonce';
        }
    }

    function showServiceStep() {
        $('.booking-step[data-step="1"]').addClass('active').show();
        
        // Clear hash when returning to service selection (unless we have a preselected service)
        if (!formState.service) {
            updateURLHash('clear');
        }
        
        // Show preselected service as selected
        if (formState.service) {
            $(`.service-option[data-service="${formState.service}"]`).addClass('selected');
            updateURLHash('service-selection');
        }
        
        // BROWSER BACK FIX: For direct form access, ensure we create an initial history entry
        // so browser back button has somewhere to go
        if (window.bookingFormEntryType === 'direct_url' && !window.initialHistoryCreated) {
            // Create an initial service selection history entry 
            setTimeout(() => {
                const currentUrl = window.location.href;
                window.history.replaceState(
                    { step: 'service-selection', stepIndex: 0, service: null, isInitial: true }, 
                    'Service Selection', 
                    currentUrl
                );
                window.initialHistoryCreated = true;
            }, 50);
        }
        
        // Bind service selection events
        $('.service-option').off('click').on('click', function() {
            const service = $(this).data('service');
            formState.service = service;
            // Also set hidden field immediately for robustness
            const serviceInput = document.getElementById('service-field');
            if (serviceInput) serviceInput.value = service;
            // Update URL hash with selected service
            updateURLHash('service-selection');
            // Update background based on service
            updateBackground(service);
            // Visual feedback
            $('.service-option').removeClass('selected');
            $(this).addClass('selected');
            // Move to next step after short delay
            setTimeout(() => {
                nextStep();
            }, 300);
        });
    }

    function showChoiceStep(step) {
        // Use the specific step number for this choice question
        const stepNum = getStepNumberForChoice(step.id);
        const $stepEl = $(`.booking-step[data-step="${stepNum}"]`);
        
        // Explicitly show and activate the step
        $stepEl.addClass('active').show();
        
        $stepEl.find(`#step${stepNum}-title`).text(step.question);
        
        // For step 2, we need to show choice mode and hide text input
        if (stepNum === 2) {
            $('#step2-text-input').hide();
            $('#step2-options').show();
        }
        
        // Clear and populate options
        const $options = $stepEl.find(`#step${stepNum}-options`);
        $options.empty();
        
        step.options.forEach(option => {
            $options.append(`<button class="option-btn" data-value="${option}">${option}</button>`);
        });
        
        // Determine if this step should auto-advance
        const autoAdvanceSteps = [
            'roof_action', 'roof_material',
            'windows_action', 'windows_replace_qty', 'windows_repair_needed',
            'bathroom_option',
            'siding_option', 'siding_material',
            'kitchen_action', 'kitchen_component',
            'decks_action', 'decks_material',
            'adu_action', 'adu_type'
        ];
        const shouldAutoAdvance = autoAdvanceSteps.includes(step.id);
        
        // Hide/show Next button based on auto-advance logic
        const $nextBtn = $stepEl.find('.btn-next');
        if (shouldAutoAdvance) {
            $nextBtn.hide();
            $stepEl.find('.form-navigation').addClass('single-button-nav');
        } else {
            $nextBtn.show();
            $stepEl.find('.form-navigation').removeClass('single-button-nav');
        }
        
        // Bind events
        $options.find('.option-btn').off('click').on('click', function() {
            const value = $(this).data('value');
            formState[step.id] = value;
            
            // Visual feedback
            $options.find('.option-btn').removeClass('selected');
            $(this).addClass('selected');
            
            if (shouldAutoAdvance) {
                // Auto-advance after short delay
                setTimeout(() => {
                    nextStep();
                }, 300);
            } else {
                // Enable next button for manual steps
                $stepEl.find('.btn-next').prop('disabled', false);
            }
        });
        
        // Update navigation (this handles back button setup)
        updateNavigation($stepEl);
    }

    function showTextStep(step) {
        const stepNum = getStepNumberForTextInput(step.id);
        const $stepEl = $(`.booking-step[data-step="${stepNum}"]`);
        
        // Always show and configure the step
        $stepEl.addClass('active').show();
        
        // Handle dynamic titles for the unified ZIP code step
        if (step.id === 'zip_code') {
            const $title = $stepEl.find('.step-title');
            // Use the question_template and replace {service} with the actual service
            const question = step.question_template.replace('{service}', formState.service || 'Service');
            $title.html(question);
            
            // Update the label if it exists
            if (step.label) {
                const $label = $stepEl.find('.form-label');
                $label.text(step.label);
            }
            
            // For step 2, we need to show text input mode and hide choice options
            if (stepNum === 2) {
                $('#step2-options').hide();
                $('#step2-text-input').show();
                $('#step2-label').text(step.label || 'Enter your ZIP code');
                // Clear any previous input value
                $('#step2-zip-input, #zip-input').val('');
            }
        } else {
            // For non-ZIP text steps, use the default title
            const $title = $stepEl.find('.step-title');
            $title.text(step.question);
        }
        
        // Always handle special cases and binding, even if already configured
        
        // Special handling for city replacement
        if (step.id === 'contact_info') {
            const city = extractCityFromAddress(formState.address);
            $stepEl.find('#city-name').text(city || '[City]');
        }
        
        // Bind input validation
        const inputSelector = getInputSelectorForStep(step.id);
        const $input = $stepEl.find(inputSelector);
        
        $input.off('input').on('input', function() {
            const value = $(this).val().trim();
            let isValid = !!value;
            
            // NEW ZIP CODE VALIDATION SYSTEM - Override all old logic
            if (step.id === 'zip_code') {
                // Let the ZIP lookup service handle everything
                // This is just for immediate button state feedback
                isValid = value.length === 5 && /^\d{5}$/.test(value);
                
                // The actual validation is handled by ZipCodeLookupService
                // which will update button state and show errors automatically
            }
            
            // For non-ZIP inputs, use standard validation
            if (step.id !== 'zip_code') {
                $stepEl.find('.btn-next').prop('disabled', !isValid);
            }
        });
        
        // Add verification section for ZIP code steps
        if (step.id === 'zip_code') {
            addVerificationSection($stepEl, formState.service);
        }
        
        // Update navigation
        updateNavigation($stepEl);
    }

    // ─── VERIFICATION SECTION FOR ZIP CODE STEP ──────
    function addVerificationSection($stepEl, serviceName) {
        // Remove any existing verification section first
        $stepEl.find('.verification-section').remove();
        
        // Get the service name for the text (fallback to 'Service' if not available)
        const service = serviceName || 'Service';
        
        // Create verification section HTML
        const verificationHTML = `
            <div class="verification-section" style="max-width: 550px; width: 100%; margin-left: auto; margin-right: auto; text-align: center; justify-content:center; align-items: center; gap:22px; margin-top: 20px; display:flex; flex-direction:row; padding: 15px;">
                <img src="${window.location.origin}/wp-content/plugins/BookingPro/assets/images/Verified-icon.webp" 
                     style="width: 30px; max-width: 30px;" 
                     alt="Verified" 
                     onerror="this.style.display='none'">
                <p class="verification-text" style="font-size: 14px; margin:0px; color: #fff; line-height: 1.4; word-wrap: break-word;">
                    IA Remodeling has verified +232 licensed ${service} contractors in your area
                </p>
            </div>
        `;
        
        // Find the form navigation and add verification section after it
        const $navigation = $stepEl.find('.form-navigation');
        if ($navigation.length > 0) {
            $navigation.after(verificationHTML);
        } else {
            // Fallback: add at the end of the step if navigation not found
            $stepEl.append(verificationHTML);
        }
    }

    function showFormStep(step) {
        const $stepEl = $('.booking-step[data-step="7"]');
        $stepEl.addClass('active').show();
        
        // NEW: City display is now handled entirely by ZIP code lookup system
        // Remove old address-based city logic
        const cityElement = $('#city-name');
        
        // Check if ZIP lookup service has already set a city
        if (window.zipLookupService && 
            window.zipLookupService.currentCity && 
            window.zipLookupService.currentState) {
            // Use ZIP-detected city
            const cityText = `${window.zipLookupService.currentCity}, ${window.zipLookupService.currentState}`;
            cityElement.text(cityText);
        } else {
            // Show placeholder until ZIP is entered
            cityElement.text('[City]');
        }
        
        // Bind form validation with US phone number formatting
        const $phone = $('#phone-input');
        const $email = $('#email-input');
        
        // Phone number formatting function
        function formatPhoneNumber(value) {
            // Remove all non-digit characters
            const phoneNumber = value.replace(/\D/g, '');
            
            // Apply formatting based on length
            if (phoneNumber.length >= 6) {
                return `(${phoneNumber.slice(0, 3)}) ${phoneNumber.slice(3, 6)}-${phoneNumber.slice(6, 10)}`;
            } else if (phoneNumber.length >= 3) {
                return `(${phoneNumber.slice(0, 3)}) ${phoneNumber.slice(3)}`;
            } else {
                return phoneNumber;
            }
        }
        
        // Phone number validation function (US 10-digit format)
        function validateUSPhoneNumber(phoneNumber) {
            // Remove all non-digit characters
            const digitsOnly = phoneNumber.replace(/\D/g, '');
            
            // Check if exactly 10 digits and doesn't start with 0 or 1
            if (digitsOnly.length !== 10) {
                return false;
            }
            
            // First digit should not be 0 or 1
            if (digitsOnly[0] === '0' || digitsOnly[0] === '1') {
                return false;
            }
            
            // Area code (first 3 digits) should not start with 0 or 1
            if (digitsOnly[0] === '0' || digitsOnly[0] === '1') {
                return false;
            }
            
            // Exchange code (next 3 digits) should not start with 0 or 1
            if (digitsOnly[3] === '0' || digitsOnly[3] === '1') {
                return false;
            }
            
            return true;
        }
        
        function validateForm() {
            const phoneValue = $phone.val().trim();
            const emailValue = $email.val().trim();
            
            // Phone validation: must be valid 10-digit US number
            const phoneValid = validateUSPhoneNumber(phoneValue);
            
            // Email validation
            const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue);
            
            // Update visual feedback
            if (phoneValue.length > 0) {
                if (phoneValid) {
                    $phone.removeClass('error').addClass('valid');
                } else {
                    $phone.removeClass('valid').addClass('error');
                }
            } else {
                $phone.removeClass('valid error');
            }
            
            if (emailValue.length > 0) {
                if (emailValid) {
                    $email.removeClass('error').addClass('valid');
                } else {
                    $email.removeClass('valid').addClass('error');
                }
            } else {
                $email.removeClass('valid error');
            }
            
            // Enable/disable next button
            $stepEl.find('.btn-next').prop('disabled', !(phoneValid && emailValid));
        }
        
        // Phone input formatting and validation
        $phone.off('input').on('input', function(e) {
            const input = e.target;
            const cursorPosition = input.selectionStart;
            const oldValue = input.value;
            const oldLength = oldValue.length;
            
            // Format the phone number
            const formattedValue = formatPhoneNumber(input.value);
            
            // Update the input value
            input.value = formattedValue;
            
            // Adjust cursor position
            const newLength = formattedValue.length;
            let newCursorPosition = cursorPosition + (newLength - oldLength);
            
            // Ensure cursor doesn't go beyond the input length
            newCursorPosition = Math.min(newCursorPosition, newLength);
            
            // Set cursor position
            setTimeout(() => {
                input.setSelectionRange(newCursorPosition, newCursorPosition);
            }, 0);
            
            // Validate form
            validateForm();
            
            // Update step 8 dynamic content if we're currently on step 8
            if ($('.booking-step[data-step="8"]').hasClass('active')) {
                updateStep8DynamicContent();
            }
        });
        
        // Email validation
        $email.off('input').on('input', validateForm);
        
        // Update navigation
        updateNavigation($stepEl);
    }

    function showDateTimeStep() {

        
        const $stepEl = $('.booking-step[data-step="8"]');
        $stepEl.addClass('active').show();
        
        // Update URL hash for scheduling
        updateURLHash('scheduling');
        

        
        // Set up company configuration but don't initialize calendars automatically

        if (typeof BSP_Ajax !== 'undefined' && BSP_Ajax.companies) {
            CONFIG.companies = BSP_Ajax.companies;
            CONFIG.ajaxUrl = BSP_Ajax.ajaxUrl;
            CONFIG.nonce = BSP_Ajax.nonce;

        } else {


            
            // Ensure we have some company data for testing
            if (!CONFIG.companies || CONFIG.companies.length === 0) {

                // Use display names that match the template
                CONFIG.companies = [
                    { id: 1, name: 'RH Remodeling', phone: '(555) 123-4567', address: '123 Main St, Los Angeles, CA' },
                    { id: 2, name: 'Eco Green', phone: '(555) 234-5678', address: '456 Oak Ave, Los Angeles, CA' },
                    { id: 3, name: 'Top Remodeling Pro', phone: '(555) 345-6789', address: '789 Pine St, Los Angeles, CA' }
                ];
                CONFIG.ajaxUrl = '/wp-admin/admin-ajax.php';
                CONFIG.nonce = 'demo-nonce';

            }
        }
        
        // Bind calendar events globally
        bindCalendarEvents();
        

        $('.company-card').each(function(index) {
            const $card = $(this);
            const companyName = $card.find('.company-name-text').text().trim();

            
            // Check if calendar and time slots exist for this company
            const $calendar = $card.find('.calendar-grid');
            const $timeSlots = $card.find('.time-slots');


        });
        
        // Auto-initialize calendars for all companies for better user experience
        setTimeout(() => {
            const $companyCards = $('.company-card');

            $companyCards.each(function(index) {
                const $card = $(this);
                const companyId = $card.data('company-id');
                const companyName = $card.data('company') || $card.find('.company-name').text().trim();

                if (companyId) {
                    // Use reliable ID-based initialization
                    initializeCompanyCalendar(companyId);
                } else if (companyName && CONFIG.companies) {
                    // Fallback: find company by name and get ID
                    const companyData = CONFIG.companies.find(c => c.name === companyName);
                    if (companyData) {
                        initializeCompanyCalendar(companyData.id);
                    }
                }
            });
        }, 100);
        
        // Update dynamic content for step 8
        updateStep8DynamicContent();

        // Ensure city/state hidden fields are set before form submit
        const bookingForm = document.querySelector('.booking-system-form');
        if (bookingForm) {
            bookingForm.addEventListener('submit', function() {
                // Sync city/state from ZIP lookup
                if (window.zipLookupService) {
                    const city = window.zipLookupService.currentCity || '';
                    const state = window.zipLookupService.currentState || '';
                    const cityInput = bookingForm.querySelector('input[name="city"]');
                    const stateInput = bookingForm.querySelector('input[name="state"]');
                    if (cityInput) cityInput.value = city;
                    if (stateInput) stateInput.value = state;
                }
                // Sync UTM/marketing fields from URL parameters and cookies
                const utmFields = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','referrer'];
                const urlParams = new URLSearchParams(window.location.search);
                
                utmFields.forEach(function(field) {
                    const input = bookingForm.querySelector('input[name="'+field+'"]');
                    if (input && !input.value) {
                        let value = null;
                        
                        // Check URL parameters first
                        if (urlParams.has(field)) {
                            value = urlParams.get(field);
                        }
                        // Then check cookies
                        else if (typeof getCookie === 'function') {
                            const cookieVal = getCookie('bsp_'+field);
                            if (cookieVal) {
                                value = cookieVal;
                            }
                        }
                        
                        if (value) {
                            input.value = value;
                        }
                    }
                });
                // Sync service selection
                if (formState.service) {
                    const serviceInput = bookingForm.querySelector('input[name="service"]');
                    if (serviceInput) serviceInput.value = formState.service;
                }
                // Sync company (for single-company bookings)
                if (typeof selectedAppointments !== 'undefined' && selectedAppointments.length > 0) {
                    const companyInput = bookingForm.querySelector('input[name="company"]');
                    if (companyInput) companyInput.value = selectedAppointments[0].company;
                }
                // Sync appointments JSON
                if (typeof selectedAppointments !== 'undefined') {
                    const appointmentsInput = bookingForm.querySelector('input[name="appointments"]');
                    if (appointmentsInput) appointmentsInput.value = JSON.stringify(selectedAppointments);
                }
                // Sync all service-specific fields from formState to hidden fields
                const serviceFields = [
                    'roof_zip', 'windows_zip', 'bathroom_zip', 'siding_zip', 'kitchen_zip', 'decks_zip', 'adu_zip',
                    'roof_action', 'roof_material',
                    'windows_action', 'windows_replace_qty', 'windows_repair_needed',
                    'bathroom_option',
                    'siding_option', 'siding_material',
                    'kitchen_action', 'kitchen_component',
                    'decks_action', 'decks_material',
                    'adu_action', 'adu_type'
                ];
                serviceFields.forEach(function(field) {
                    if (formState[field] !== undefined) {
                        const input = bookingForm.querySelector('input[name="' + field + '"]');
                        if (input) input.value = formState[field];
                    }
                });
            }, true);
        }
        
        // Update navigation
        updateNavigation($stepEl);
        

    }

    /**
     * Update dynamic content in step 8 (company service location and phone numbers)
     */
    function updateStep8DynamicContent() {
        // Get city/state from our new ZIP lookup service
        let cityState = 'Los Angeles, CA'; // Default fallback
        
        // Try to get dynamic city/state from our ZIP lookup service
        if (window.zipLookupService && 
            window.zipLookupService.currentCity && 
            window.zipLookupService.currentState) {
            cityState = `${window.zipLookupService.currentCity}, ${window.zipLookupService.currentState}`;
        } else if (window.BookingProZipFormIntegration && window.BookingProZipFormIntegration.cityData) {
            // Fallback to old integration if available
            cityState = window.BookingProZipFormIntegration.cityData.displayString;
        } else if (window.BookingProZipFormIntegration && 
                   window.BookingProZipFormIntegration.currentCity && 
                   window.BookingProZipFormIntegration.currentState) {
            cityState = `${window.BookingProZipFormIntegration.currentCity}, ${window.BookingProZipFormIntegration.currentState}`;
        }
        
        // Get phone number from step 7
        let phoneNumber = '[client phone number]'; // Default fallback
        const phoneInput = $('#phone-input');
        if (phoneInput.length && phoneInput.val().trim()) {
            phoneNumber = phoneInput.val().trim();
        }
        
        // Update all company service descriptions
        $('.company-service').each(function() {
            const $serviceEl = $(this);
            let currentText = $serviceEl.html();
            
            // Replace [City, State] placeholder with actual city/state
            const updatedText = currentText.replace('[City, State]', `<strong>${cityState}</strong>`);
            $serviceEl.html(updatedText);

        });
        
        // Update all booking disclaimer phone numbers
        $('.booking-disclaimer').each(function() {
            const $disclaimerEl = $(this);
            let disclaimerText = $disclaimerEl.html();
            
            // Replace [client phone number] with actual phone number
            disclaimerText = disclaimerText.replace('[client phone number]', `<strong>${phoneNumber}</strong>`);
            $disclaimerEl.html(disclaimerText);

        });
        

    }

    // Make updateStep8DynamicContent globally accessible
    window.updateStep8DynamicContent = updateStep8DynamicContent;

    function showSummaryStep() {
        const $stepEl = $('.booking-step[data-step="9"]');
        $stepEl.addClass('active').show();
        
        // Validate that we have appointments before showing summary
        if (!selectedAppointments || selectedAppointments.length === 0) {
            // If no appointments selected, go back to scheduling step
            showErrorMessage('Please select at least one appointment before proceeding to summary.');
            currentStepIndex = CONFIG.steps.findIndex(step => step.id === 'schedule');
            renderCurrentStep();
            return;
        }
        
        // Populate summary
        populateSummary();
        
        // Update navigation and ensure submit button is enabled
        updateNavigation($stepEl);
        
        // Enable the submit button since we have appointments
        $stepEl.find('.btn-submit').prop('disabled', false);
    }

    // ─── CALENDAR AND TIME SLOT MANAGEMENT ──────────
    function initializeCalendars() {
        // First get companies from backend and then initialize calendars
        if (typeof BSP_Ajax !== 'undefined' && BSP_Ajax.companies) {
            CONFIG.companies = BSP_Ajax.companies;
            CONFIG.ajaxUrl = BSP_Ajax.ajaxUrl;
            CONFIG.nonce = BSP_Ajax.nonce;
        }
        
        CONFIG.companies.forEach(companyData => {
            const $calendar = $(`.calendar-grid[data-company-id="${companyData.id}"]`);
            const $timeSlots = $(`.time-slots[data-company-id="${companyData.id}"]`);
            
            // Update company display with phone number
            const $companySection = $calendar.closest('.company-card').find('.calendar-section h4');
            if ($companySection.length && companyData.phone) {
                $companySection.append(`<div class="company-phone">${companyData.phone}</div>`);
            }
            
            // Generate calendar days (next 30 days)
            generateCalendarDays($calendar, companyData.id);
        });
        
        // Bind calendar interactions
        bindCalendarEvents();
    }
    
    function initializeCompanyCalendar(companyId) {
        // Initialize calendar and time slots for a specific company using ID
        const $calendar = $(`.calendar-grid[data-company-id="${companyId}"]`);
        const $timeSlots = $(`.time-slots[data-company-id="${companyId}"]`);
        
        if ($calendar.length === 0 || $timeSlots.length === 0) return;
        
        const companyData = CONFIG.companies ? CONFIG.companies.find(c => c.id == companyId) : null;
        if (!companyData) return;
        
        // Generate calendar days if not already done
        const currentChildren = $calendar.children().length;
        
        if (currentChildren === 0) {
            generateCalendarDays($calendar, companyId);
        }
        
        // Clear any existing time slots
        $timeSlots.empty();
        


    }
    
    function bindCalendarEvents() {
        // Bind date selection for all calendars
        $(document).off('click', '.calendar-day').on('click', '.calendar-day', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Check if date is unavailable or disabled
            if ($(this).hasClass('unavailable') || $(this).hasClass('disabled') || $(this).data('disabled')) {

                return false;
            }
            
            const date = $(this).data('date');
            const companyId = $(this).closest('.calendar-grid').data('company-id');
            const $calendar = $(this).closest('.calendar-grid');
            
            // Find company data using ID
            const companyData = CONFIG.companies ? CONFIG.companies.find(c => c.id == companyId) : null;
            if (!companyData) {
                return false;
            }
            const companyName = companyData.name;
            
            const $timeSlots = $(`.time-slots[data-company-id="${companyId}"]`);
            

            
            // Check if this date is already selected for this company
            const isCurrentlySelected = $(this).hasClass('selected');
            
            // Clear other date selections in this calendar only (only one date per company)
            $calendar.find('.calendar-day').removeClass('selected');
            
            // Clear any time slot selections for this company
            $(`.time-slot[data-company-id="${companyId}"]:not(.disabled)`).removeClass('selected');
            
            // Clear time slots container for this company
            $timeSlots.empty();
            
            if (isCurrentlySelected) {
                // User clicked the same date again - deselect it

                
                // Remove any appointments for this company
                selectedAppointments = selectedAppointments.filter(apt => apt.company !== companyName);
                
                // Refresh calendar to update visual state
                refreshCompanyCalendar(companyName);
                
                updateNextButtonState();
                updateAppointmentSummary();
            } else {
                // User selected a new date - select it

                $(this).addClass('selected');
                
                // Load time slots for selected date and company
                loadTimeSlots($timeSlots, companyName, date);
            }
        });
    }

    function generateCalendarDays($calendar, companyId) {
        $calendar.empty().append('<div class="loading-spinner">Loading availability...</div>');
        
        // Find company data using ID
        const companyData = CONFIG.companies ? CONFIG.companies.find(c => c.id == companyId) : null;
        
        if (!companyData) {
            const errorMsg = '<div class="error-message">Company not found for ID: ' + companyId + '</div>';
            $calendar.html(errorMsg);
            return;
        }
        
        if (!CONFIG.ajaxUrl || !CONFIG.nonce) {
            const errorMsg = '<div class="error-message">Booking system configuration error. Please refresh the page and try again.</div>';
            $calendar.html(errorMsg);
            return;
        }
        
        // Fetch real availability from backend
        $.ajax({
            url: CONFIG.ajaxUrl,
            type: 'POST',
            data: {
                action: 'bsp_get_availability',
                nonce: CONFIG.nonce,
                company_ids: [companyData.id],
                date_from: new Date().toISOString().split('T')[0],
                date_to: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
            },
            success: function(response) {
                if (response.success && response.data[companyData.id]) {
                    renderCalendarDays($calendar, response.data[companyData.id], companyData.name);
                } else {
                    $calendar.html('<div class="error-message">No availability found for this provider</div>');
                }
            },
            error: function(xhr, status, error) {
                let errorMsg = '<div class="error-message">Could not load availability. Please refresh and try again.</div>';
                
                if (xhr.status === 0) {
                    errorMsg = '<div class="error-message">Connection error. Please check your internet connection and try again.</div>';
                } else if (xhr.status >= 500) {
                    errorMsg = '<div class="error-message">Server error. Please try again in a few moments.</div>';
                } else if (xhr.status === 404) {
                    errorMsg = '<div class="error-message">Booking service unavailable. Please contact support.</div>';
                }
                
                $calendar.html(errorMsg);
            }
        });
        

    }

    function renderCalendarDays($calendar, availabilityData, company) {
        const companyData = CONFIG.companies ? CONFIG.companies.find(c => c.name === company) : null;
        if (!companyData) {
            $calendar.html('<div class="error-message">Company not found</div>');
            return;
        }
        
        $calendar.empty();
        
        // Sort dates to ensure chronological order
        const sortedDates = Object.keys(availabilityData).sort();

        // Display all available dates (limited to 3 days by backend)
        sortedDates.forEach(dateStr => {
            const dayData = availabilityData[dateStr];
            
            // Calculate availability metrics with 30-minute buffer
            const totalSlots = dayData.slots.length;
            const originalAvailableSlots = dayData.slots.filter(slot => slot.available).length;
            const bufferFilteredAvailableSlots = calculateAvailableSlotsWithBuffer(dayData, dateStr);
            const unavailableSlots = totalSlots - originalAvailableSlots;
            
            // Enhanced availability logic with buffer consideration:
            // - Disable if NO buffer-filtered available slots (fully booked or all slots too soon)
            // - Disable if 5 or more slots are unavailable AND less than 2 buffer-filtered available (heavily booked)
            // - Otherwise allow selection (user can pick from remaining buffer-filtered slots)
            const isFullyBookedOrTooSoon = bufferFilteredAvailableSlots === 0;
            const isHeavilyBooked = unavailableSlots >= 5 && bufferFilteredAvailableSlots < 2;
            const shouldDisableDay = isFullyBookedOrTooSoon || isHeavilyBooked;
            
            // Check if this company already has an appointment on this date
            const hasExistingAppointment = selectedAppointments.some(apt => 
                apt.company === company && apt.date === dateStr
            );
            
            // Determine CSS classes and availability
            let cssClass, dayTitle;
            if (shouldDisableDay) {
                cssClass = 'unavailable disabled';
                if (isFullyBookedOrTooSoon && originalAvailableSlots === 0) {
                    dayTitle = 'Fully booked - no available time slots';
                } else if (isFullyBookedOrTooSoon && originalAvailableSlots > 0) {
                    dayTitle = 'No available time slots (booking window closed)';
                } else {
                    dayTitle = `Very limited availability - only ${bufferFilteredAvailableSlots} slots remaining`;
                }
            } else {
                cssClass = 'available';
                if (bufferFilteredAvailableSlots <= 2) {
                    dayTitle = `Limited availability - ${bufferFilteredAvailableSlots} slots remaining`;
                } else {
                    dayTitle = `${bufferFilteredAvailableSlots} time slots available - click to select`;
                }
            }
            
            const $day = $(`
                <div class="calendar-day ${cssClass} ${hasExistingAppointment ? 'selected' : ''}" 
                     data-date="${dateStr}" 
                     data-company="${companyData.name}"
                     data-company-id="${companyData.id}"
                     data-available-slots="${bufferFilteredAvailableSlots}"
                     data-total-slots="${totalSlots}"
                     ${shouldDisableDay ? 'data-disabled="true"' : ''}
                     title="${dayTitle}">
                    <div class="day-number">${dayData.day_number}</div>
                    <div class="day-name">${dayData.day_name}</div>
                    ${(bufferFilteredAvailableSlots > 0 && bufferFilteredAvailableSlots <= 3) ? `<div class="slots-indicator">${bufferFilteredAvailableSlots} left</div>` : ''}
                </div>
            `);
            
            $calendar.append($day);
        });
        

    }

    // ─── REFRESH CALENDAR AVAILABILITY ─────────────────
    function refreshCompanyCalendar(companyName) {

        
        const $calendar = $(`.calendar-grid[data-company="${companyName}"]`);
        if ($calendar.length === 0) {

            return;
        }
        
        // Get stored availability data
        const availabilityData = $calendar.data('availability-data');
        if (!availabilityData) {

            return;
        }
        
        // Re-render calendar with updated appointment status
        $calendar.find('.calendar-day').each(function() {
            const $day = $(this);
            const dateStr = $day.data('date');
            
            // Check if this company has an appointment on this date
            const hasExistingAppointment = selectedAppointments.some(apt => 
                apt.company === companyName && apt.date === dateStr
            );
            
            // Update selected class
            if (hasExistingAppointment) {
                $day.addClass('selected');
            } else {
                $day.removeClass('selected');
            }
        });
    }

    // ─── REFRESH ALL COMPANIES AVAILABILITY AFTER BOOKING ─────
    function refreshAllCompanyAvailability(callback) {
        const $allCalendars = $('.calendar-grid[data-company]');
        let calendarsToRefresh = $allCalendars.length;
        
        if (calendarsToRefresh === 0) {
            // No calendars to refresh, execute callback immediately
            if (callback) callback();
            return;
        }
        
        // Refresh availability for updated calendars
        
        $allCalendars.each(function() {
            const $calendar = $(this);
            const companyName = $calendar.data('company');
            const companyData = CONFIG.companies?.find(c => c.name === companyName);
            
            if (!companyData) {
                calendarsToRefresh--;
                if (calendarsToRefresh === 0 && callback) callback();
                return;
            }
            
            // Fetch fresh availability from backend
            $.ajax({
                url: CONFIG.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bsp_get_availability',
                    nonce: CONFIG.nonce,
                    company_ids: [companyData.id],
                    date_from: new Date().toISOString().split('T')[0],
                    date_to: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
                },
                success: function(response) {
                    if (response.success && response.data[companyData.id]) {
                        // Update the calendar with fresh data
                        $calendar.data('availability-data', response.data[companyData.id]);
                        // Re-render the calendar
                        renderCalendarDays($calendar, response.data[companyData.id], companyData.name);
                        
                        console.log(`✅ Refreshed availability for ${companyName}`);
                    }
                },
                error: function(xhr, status, error) {
                    console.warn(`⚠️ Failed to refresh availability for ${companyName}:`, error);
                },
                complete: function() {
                    calendarsToRefresh--;
                    // Execute callback when all calendars are refreshed
                    if (calendarsToRefresh === 0 && callback) {
                        callback();
                    }
                }
            });
        });
    }

    function loadTimeSlots($timeSlots, company, date) {

        $timeSlots.empty().append('<div class="loading-spinner">Loading time slots...</div>');
        
        // Get availability data from the calendar
        const $calendar = $(`.calendar-grid[data-company="${company}"]`);
        const availabilityData = $calendar.data('availability-data');
        



        
        if (availabilityData && availabilityData[date]) {
            const dayData = availabilityData[date];

            $timeSlots.empty();
            
            let slotsAdded = 0;
            let slotsHiddenByBuffer = 0;
            let slotsBookedByBackend = 0;
            
            
            dayData.slots.forEach(slot => {
                // Check if slot is available from backend
                const isBackendAvailable = slot.available;
                
                // Check if slot passes 30-minute buffer filter
                const isBufferBookable = isSlotBookableWithBuffer(slot.time, date);
                
                // Slot is fully available only if both conditions are met
                const isFullyAvailable = isBackendAvailable && isBufferBookable;
                
                // Track filtering reasons
                if (!isBackendAvailable) {
                    slotsBookedByBackend++;
                } else if (!isBufferBookable) {
                    slotsHiddenByBuffer++;
                    return; // Skip this slot entirely
                }
                
                // Determine slot class and title
                let slotClass, slotTitle;
                if (!isBackendAvailable) {
                    // Backend says slot is booked
                    slotClass = 'time-slot disabled';
                    slotTitle = 'This time slot is already booked';
                } else if (!isBufferBookable) {
                    // Backend available but filtered by 30-min buffer - don't show this slot at all
                    return; // Skip this slot entirely
                } else {
                    // Fully available
                    slotClass = 'time-slot';
                    slotTitle = 'Click to select this time';
                }
                
                const $slot = $(`
                    <div class="${slotClass}" 
                         data-time="${slot.time}" 
                         data-company="${company}" 
                         data-date="${date}"
                         ${!isFullyAvailable ? 'data-disabled="true"' : ''}
                         title="${slotTitle}">
                        ${slot.formatted}
                        ${!isBackendAvailable ? '<span class="booked-indicator">Booked</span>' : ''}
                    </div>
                `);
                
                $timeSlots.append($slot);
                if (isFullyAvailable) slotsAdded++;
            });
            
            // Add warning messages based on availability
            if (slotsAdded === 0) {
                $timeSlots.html('<div class="no-slots">❌ All time slots are booked for this date</div>');
            } else if (slotsAdded === 1) {
                $timeSlots.prepend(`<div class="limited-slots-warning">⚠️ Only 1 slot remaining - book now!</div>`);
            } else if (slotsAdded <= 3) {
                $timeSlots.prepend(`<div class="limited-slots-warning">⚠️ Limited availability - only ${slotsAdded} slots remaining</div>`);
            }
        } else {
            // Fallback: fetch from server if not cached
            const companyData = CONFIG.companies.find(c => c.name === company);
            if (!companyData) {
                $timeSlots.html('<div class="error-message">Company not found</div>');
                return;
            }
            
            $.ajax({
                url: CONFIG.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bsp_get_availability',
                    nonce: CONFIG.nonce,
                    company_ids: [companyData.id],
                    date_from: date,
                    date_to: date
                },
                success: function(response) {
                    if (response.success && response.data[companyData.id] && response.data[companyData.id][date]) {
                        const dayData = response.data[companyData.id][date];
                        $timeSlots.empty();
                        
                        let slotsAdded = 0;
                        let slotsHiddenByBuffer = 0;
                        let slotsBookedByBackend = 0;
                        
                        dayData.slots.forEach(slot => {
                            // Check if slot is available from backend
                            const isBackendAvailable = slot.available;
                            
                            // Check if slot passes 30-minute buffer filter
                            const isBufferBookable = isSlotBookableWithBuffer(slot.time, date);
                            
                            // Slot is fully available only if both conditions are met
                            const isFullyAvailable = isBackendAvailable && isBufferBookable;
                            
                            // Track filtering reasons
                            if (!isBackendAvailable) {
                                slotsBookedByBackend++;
                            } else if (!isBufferBookable) {
                                slotsHiddenByBuffer++;
                                return; // Skip this slot entirely
                            }
                            
                            // Determine slot class and title
                            let slotClass, slotTitle;
                            if (!isBackendAvailable) {
                                // Backend says slot is booked
                                slotClass = 'time-slot disabled';
                                slotTitle = 'This time slot is already booked';
                            } else if (!isBufferBookable) {
                                // Backend available but filtered by 30-min buffer - don't show this slot at all
                                return; // Skip this slot entirely
                            } else {
                                // Fully available
                                slotClass = 'time-slot';
                                slotTitle = 'Click to select this time';
                            }
                            
                            const $slot = $(`
                                <div class="${slotClass}" 
                                     data-time="${slot.time}" 
                                     data-company="${company}" 
                                     data-company-id="${companyData.id}" 
                                     data-date="${date}"
                                     ${!isFullyAvailable ? 'data-disabled="true"' : ''}
                                     title="${slotTitle}">
                                    ${slot.formatted}
                                    ${!isBackendAvailable ? '<span class="booked-indicator">Booked</span>' : ''}
                                </div>
                            `);
                            
                            $timeSlots.append($slot);
                            if (isFullyAvailable) slotsAdded++;
                        });
                        
                        // Add warning messages based on availability
                        if (slotsAdded === 0) {
                            $timeSlots.html('<div class="no-slots">❌ All time slots are booked for this date</div>');
                        } else if (slotsAdded === 1) {
                            $timeSlots.prepend(`<div class="limited-slots-warning">⚠️ Only 1 slot remaining - book now!</div>`);
                        } else if (slotsAdded <= 3) {
                            $timeSlots.prepend(`<div class="limited-slots-warning">⚠️ Limited availability - only ${slotsAdded} slots remaining</div>`);
                        }
                    } else {
                        $timeSlots.html('<div class="error-message">No time slots available</div>');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'Failed to load time slots';
                    if (xhr.status === 0) {
                        errorMessage = 'Network error - check your internet connection';
                    } else if (xhr.status >= 500) {
                        errorMessage = 'Server error - please try again later';
                    }
                    
                    $timeSlots.html(`<div class="error-message">${errorMessage}</div>`);
                }
            });
        }
        
        // Bind time selection
        $timeSlots.off('click', '.time-slot').on('click', '.time-slot', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Check if time slot is disabled/booked
            if ($(this).hasClass('disabled') || $(this).data('disabled')) {

                return false;
            }
            
            const time = $(this).data('time');
            const appointmentCompany = $(this).data('company');
            const appointmentCompanyId = $(this).data('company-id');
            const appointmentDate = $(this).data('date');
            
            // Check unique companies currently selected
            const uniqueCompanies = [...new Set(selectedAppointments.map(apt => apt.company))];
            



            
            // Check if we already have 3 unique companies and this is a NEW company
            const isNewCompany = !uniqueCompanies.includes(appointmentCompany);

            
            if (!$(this).hasClass('selected') && uniqueCompanies.length >= 3 && isNewCompany) {

                alert('You can select a maximum of 3 companies. Please remove appointments for an existing company to add a new one.');
                return;
            }
            
            // Remove selection from other time slots for the same company (one appointment per company)
            $(`.time-slot[data-company="${appointmentCompany}"]:not(.disabled)`).removeClass('selected');
            
            // Also clear any calendar selection for this company
            $(`.calendar-day[data-company="${appointmentCompany}"]`).removeClass('selected');
            
            // Toggle selection on clicked slot
            $(this).toggleClass('selected');
            
            if ($(this).hasClass('selected')) {
                // Remove any existing appointments for this company (only one appointment per company)
                selectedAppointments = selectedAppointments.filter(apt => apt.company !== appointmentCompany);
                
                // Add new appointment
                const appointment = {
                    company: appointmentCompany,
                    companyId: appointmentCompanyId,
                    date: appointmentDate,
                    time: time
                };
                
                selectedAppointments.push(appointment);
                
                // Highlight the selected date in the calendar
                $(`.calendar-day[data-company="${appointmentCompany}"][data-date="${appointmentDate}"]`).addClass('selected');
                


            } else {
                // Remove appointment for this company
                selectedAppointments = selectedAppointments.filter(apt => apt.company !== appointmentCompany);
                
                // Refresh calendar to update visual state
                refreshCompanyCalendar(appointmentCompany);
                


            }
            
            // Update next button state
            updateNextButtonState();
            
            // Update visual feedback and company card states
            updateAppointmentSummary();
        });
    }

    // ─── 30-MINUTE BOOKING BUFFER SYSTEM ────────────────
    function isSlotBookableWithBuffer(slotTime, selectedDate) {
        // Get current browser time
        const now = new Date();
        const slotDateTime = new Date(selectedDate + 'T' + slotTime);
        const bufferTime = new Date(now.getTime() + 30 * 60 * 1000); // +30 minutes
        const today = new Date().toISOString().split('T')[0];
        const isToday = selectedDate === today;
        if (!isToday) {
            return true;
        }
        const isBookable = slotDateTime >= bufferTime;     
        return isBookable;
    }
    
    function calculateAvailableSlotsWithBuffer(dayData, dateStr) {
        const originalAvailableSlots = dayData.slots.filter(slot => slot.available).length;
        const today = new Date().toISOString().split('T')[0];
        const isToday = dateStr === today;
        if (!isToday) {
            return originalAvailableSlots;
        }
        const bufferFilteredSlots = dayData.slots.filter(slot => {
            return slot.available && isSlotBookableWithBuffer(slot.time, dateStr);
        }).length;
        
        return bufferFilteredSlots;
    }

    function formatTime(timeStr) {
        const [hours, minutes] = timeStr.split(':');
        const date = new Date();
        date.setHours(parseInt(hours), parseInt(minutes));
        return date.toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true 
        });
    }

    // ─── SUMMARY POPULATION ──────────────────────────
    function populateSummary() {
        // Update service title
        $('#service-schedule-title').text(`${formState.service} Estimate Schedule`);
        
        // Create schedule items for all selected appointments
        const scheduleItems = selectedAppointments.map(apt => `
            <div class="schedule-item">
                <div class="schedule-item-content">
                    <div class="schedule-company">
                        <svg fill="currentColor" viewBox="0 0 20 20" class="schedule-company-icon">
                            <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                        </svg>
                        <span class="schedule-company-text">${apt.company}</span>
                    </div>
                    <div class="schedule-datetime">
                        <svg fill="currentColor" viewBox="0 0 20 20" class="schedule-datetime-icon">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                        </svg>
                        <span class="schedule-datetime-text">${formatDate(apt.date)} at ${formatTime(apt.time)}</span>
                    </div>
                </div>
            </div>
        `).join('');
        
        $('#schedule-items').html(scheduleItems || '<p>No appointments selected</p>');
        
        // Populate project details
        const summaryDetails = `
            <div class="summary-row">
                <span class="summary-label">Homeowner/Decision Maker:</span>
                <span class="summary-value">${formState.full_name || ''}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Home Address:</span>
                <span class="summary-value">${formState.address || ''}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Phone Number:</span>
                <span class="summary-value">${formState.phone || ''}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Email Address:</span>
                <span class="summary-value">${formState.email || ''}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Total Appointments:</span>
                <span class="summary-value">${selectedAppointments.length} appointment${selectedAppointments.length !== 1 ? 's' : ''}</span>
            </div>
        `;
        $('#summary-details').html(summaryDetails);
    }

    function formatDate(dateStr) {
        if (!dateStr) return 'Selected Date';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { 
            weekday: 'short', 
            month: 'short', 
            day: 'numeric' 
        });
    }

    // ─── NAVIGATION MANAGEMENT ───────────────────────
    function updateNavigation($stepEl) {
        const $backBtn = $stepEl.find('.btn-back');
        const $nextBtn = $stepEl.find('.btn-next, .btn-submit');
        
        // Show/hide back button
        const serviceAutoSelected = getServiceFromURL() !== null || window.BOOKING_PRESELECTED_SERVICE;
        const currentStep = CONFIG.steps[currentStepIndex];

        if (currentStepIndex === 0) {
            $backBtn.hide();
        } else if (window.BOOKING_DIRECT_ZIP_MODE && currentStep && currentStep.id === 'zip_code') {
            $backBtn.hide(); // Hide back button when in direct ZIP mode
        } else if (serviceAutoSelected && formState.service && currentStep && 
                   currentStep.depends_on && currentStep.depends_on[0] === 'service') {
            const firstServiceStepIndex = findFirstServiceSpecificStep(formState.service);
            if (currentStepIndex === firstServiceStepIndex) {
                $backBtn.hide(); // Hide back button on first step of URL-based service flow
            } else {
                $backBtn.show();
            }
        } else {
            $backBtn.show();
        }
        
        // Mark buttons as handled to prevent global handler conflicts
        $backBtn.addClass('handled');
        $nextBtn.addClass('handled');
        
        // Bind back button with more specific handling
        $backBtn.off('click.navigation').on('click.navigation', function(e) {
            e.preventDefault();
            e.stopPropagation();

            previousStep();
        });
        
        // Bind next/submit button
        $nextBtn.off('click.navigation').on('click.navigation', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if ($(this).hasClass('btn-submit')) {
                submitBooking();
            } else {
                handleNextStep();
            }
        });
    }

    function nextStep() {
        currentStepIndex++;

        if (window.BOOKING_DIRECT_ZIP_MODE) {
            const previousStep = CONFIG.steps[currentStepIndex - 1];
            if (previousStep && previousStep.id === 'zip_code') {
                window.BOOKING_DIRECT_ZIP_MODE = false;
                // Remove the direct-zip-mode CSS class to allow normal step navigation
                $('#booking-form').removeClass('direct-zip-mode');
            }
        }
        
        renderCurrentStep();
    }

    function previousStep() {
        if (currentStepIndex > 0) {
            // Check if service was auto-selected from URL
            const serviceAutoSelected = getServiceFromURL() !== null;
            const currentStep = CONFIG.steps[currentStepIndex];
 
            if (serviceAutoSelected && formState.service && currentStep && 
                currentStep.depends_on && currentStep.depends_on[0] === 'service') {
                
                // Check if this is the first service-specific step
                const firstServiceStepIndex = findFirstServiceSpecificStep(formState.service);
                if (currentStepIndex === firstServiceStepIndex) {
                    // Go back to landing page
                    clearFormStateAndRedirect();
                    return;
                }
            }
            
            // Normal backward navigation
            currentStepIndex--;
            
            // Check if we need to skip steps due to dependencies when going backward
            let attempts = 0;
            while (currentStepIndex > 0 && attempts < 20) { // Safety limit
                const step = CONFIG.steps[currentStepIndex];
                attempts++;
                
                // If step has dependencies, check if they are still valid
                if (step && step.depends_on) {
                    const [dependsKey, dependsValue] = step.depends_on;
                    
                    // FIXED: Handle generic zip_code step correctly
                    if (dependsKey === 'service' && step.depends_on.length === 1) {
                        // This is the generic zip_code step - always valid if service is selected
                        if (formState.service) {
                            break; // Step is valid, stop skipping
                        } else {
                            currentStepIndex--;
                            continue;
                        }
                    }
                    // Handle service-specific dependencies
                    else if (dependsKey === 'service' && dependsValue) {
                        const currentService = formState[dependsKey];
                        const expectedService = dependsValue;
                        const servicesMatch = currentService && expectedService && 
                            currentService.toLowerCase() === expectedService.toLowerCase();
                        
                        if (!servicesMatch) {
                            currentStepIndex--;
                            continue;
                        }
                    }
                    // Handle other dependencies
                    else if (formState[dependsKey] !== dependsValue) {
                        currentStepIndex--;
                        continue;
                    }
                }
                break;
            }
            
            renderCurrentStep();
        } else {
            renderCurrentStep();
        }
    }

    // ─── CLEAR FORM STATE AND REDIRECT ──────────────
    function clearFormStateAndRedirect() {
        try {
            // Clear form state
            formState = {};
            selectedAppointments = [];
            currentStepIndex = 0;
            
            // Redirect to clean landing page
            redirectToLandingPage();
            
            // Optionally reload the page to completely reset the form
            setTimeout(() => {
                window.location.reload();
            }, 100);
        } catch (error) {
            // Form state clear failed, reloading page
            window.location.reload();
        }
    }

    function handleNextStep() {
        // NEW ZIP CODE VALIDATION - Override old logic
        const step = CONFIG.steps[currentStepIndex];
        
        // Special validation for ZIP code steps (fix: check for 'zip_code' step)
        if (step && (step.id === 'zip_code' || step.id.endsWith('_zip'))) {
            const $currentStep = $('.booking-step.active');
            const zipValue = $currentStep.find('#zip-input, #step2-zip-input, #step4-zip-input').val().trim();
            
            // Check if ZIP code is valid
            if (!zipValue || zipValue.length !== 5 || !/^\d{5}$/.test(zipValue)) {
                showErrorMessage('Please enter a 5-digit ZIP code');
                return;
            }
            
            // Check against ZIP lookup service if available and loaded
            if (window.zipLookupService && 
                window.zipLookupService.isDataLoaded && 
                window.zipLookupService.isValidZipCode && 
                !window.zipLookupService.isValidZipCode(zipValue)) {
                showErrorMessage('Please enter a valid US ZIP code');
                return;
            }
        }
            collectCurrentStepData();
        
        // Move to next step
        nextStep();
    }

    function collectCurrentStepData() {
        const step = CONFIG.steps[currentStepIndex];
        const $currentStep = $('.booking-step.active');
        
        switch (step?.type) {
            case 'text':
                const inputSelector = getInputSelectorForStep(step.id);
                const inputValue = $currentStep.find(inputSelector).val().trim();
                
                console.log('📝 Text step data collection:', {
                    step_id: step.id,
                    input_selector: inputSelector,
                    raw_value: inputValue,
                    is_address_step: step.id === 'address'
                });
                
                // Special mapping for address field
                if (step.id === 'address') {
                    formState.address = inputValue; // Store as 'address' for backend
                    formState.street_address = inputValue; // Also store as street_address for compatibility
                    console.log('🏠 Address field captured:', {
                        address: inputValue,
                        stored_as_address: formState.address,
                        stored_as_street_address: formState.street_address
                    });
                } else {
                    formState[step.id] = inputValue;
                }
                
                // Special handling for ZIP codes - ensure proper data storage
                if (step.id === 'zip_code' || step.id.endsWith('_zip')) {
                    formState.zip_code = inputValue; // Always store as zip_code
                    
                    // Also store in service-specific field
                    if (formState.service) {
                        const serviceKey = formState.service.toLowerCase() + '_zip';
                        formState[serviceKey] = inputValue;
                    }
                    
                    // Store city/state if available from ZIP lookup
                    if (window.zipLookupService) {
                        formState.city = window.zipLookupService.currentCity || '';
                        formState.state = window.zipLookupService.currentState || '';
                    }
                }
                break;
            case 'form':
                // Collect contact form data with explicit field mapping
                const phoneValue = $('#phone-input').val().trim();
                const emailValue = $('#email-input').val().trim();
                
                // CRITICAL ADDRESS FIX: Collect address field with multiple selectors
                let addressValue = '';
                const addressSelectors = [
                    '#address-input',      // Primary address field
                    'input[name="address"]',
                    'input[name="street_address"]',
                    '#street_address',
                    '.address-field'
                ];
                
                for (const selector of addressSelectors) {
                    const addressField = $(selector);
                    if (addressField.length > 0) {
                        addressValue = addressField.val().trim();
                        console.log('🏠 Address field found and captured:', {
                            selector: selector,
                            value: addressValue,
                            field_exists: addressField.length > 0
                        });
                        break;
                    }
                }
                
                // Store multiple variations for backend compatibility
                formState.phone = phoneValue;
                formState.phone_number = phoneValue; // Also store as phone_number for compatibility
                formState.email = emailValue;
                formState.email_address = emailValue; // Also store as email_address for compatibility
                
                // Store address with multiple field names
                if (addressValue) {
                    formState.address = addressValue;
                    formState.street_address = addressValue;
                    formState.customer_address = addressValue;
                    console.log('🏠 Address stored in multiple fields:', {
                        address: formState.address,
                        street_address: formState.street_address,
                        customer_address: formState.customer_address
                    });
                } else {
                    console.warn('⚠️ No address field found in contact form');
                }
                break;
            case 'datetime':
                formState.appointments = selectedAppointments;
                break;
        }
    }

    // ─── OPTIMIZED PROGRESS BAR UPDATE ─────────────────────────
    function updateProgress() {
        // PERFORMANCE: Cache visible steps calculation
        if (!window.cachedVisibleSteps || window.lastFormState !== JSON.stringify(formState)) {
            window.cachedVisibleSteps = calculateVisibleSteps();
            window.lastFormState = JSON.stringify(formState);
        }
        
        const visibleSteps = window.cachedVisibleSteps;
        const currentStepId = CONFIG.steps[currentStepIndex]?.id;
        const currentVisibleIndex = visibleSteps.findIndex(step => step.id === currentStepId);
        
        // FAST PROGRESS CALCULATION
        let progress;
        if (currentVisibleIndex === -1 || visibleSteps.length <= 1) {
            progress = 14;
        } else if (currentStepId === 'schedule') {
            progress = 100;
        } else {
            const serviceAutoSelected = getServiceFromURL() !== null;
            let adjustedIndex = currentVisibleIndex;
            if (serviceAutoSelected && currentVisibleIndex >= 0) {
                adjustedIndex = currentVisibleIndex;
            }
            progress = (adjustedIndex / (visibleSteps.length - 1)) * 100;
        }
        
        // BATCH DOM UPDATE
        requestAnimationFrame(() => {
            $('.progress-fill').css('width', `${Math.min(progress, 100)}%`);
        });
    }
    
    // ─── CACHED VISIBLE STEPS CALCULATION ─────────────────────
    function calculateVisibleSteps() {
        let visibleSteps = [];
        
        CONFIG.steps.forEach(step => {
            if (step.depends_on) {
                const [dependsKey, dependsValue] = step.depends_on;
                
                // Handle service dependencies with case-insensitive matching
                if (dependsKey === 'service' && dependsValue) {
                    const currentService = formState[dependsKey];
                    const expectedService = dependsValue;
                    const servicesMatch = currentService && expectedService && 
                        currentService.toLowerCase() === expectedService.toLowerCase();
                    
                    if (servicesMatch) {
                        visibleSteps.push(step);
                    }
                } else if (dependsKey === 'service' && step.depends_on.length === 1) {
                    // Generic zip_code step - show if any service is selected
                    if (formState.service) {
                        visibleSteps.push(step);
                    }
                } else if (formState[dependsKey] === dependsValue) {
                    // Other dependencies (exact match)
                    visibleSteps.push(step);
                }
            } else {
                visibleSteps.push(step);
            }
        });
        
        // Filter out service selection for URL-based entries
        const serviceAutoSelected = getServiceFromURL() !== null;
        if (serviceAutoSelected && formState.service) {
            visibleSteps = visibleSteps.filter(step => step.id !== 'service');
        }
        
        // Remove confirmation step from progress calculation
        return visibleSteps.filter(step => step.id !== 'confirmation');
    }

    // ─── UTILITY FUNCTIONS ───────────────────────────
    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    function getNextAvailableStepNumber() {
        return $('.booking-step[data-step="3"]').hasClass('active') ? 2 : 3;
    }

    function getStepNumberForTextInput(stepId) {
        switch (stepId) {
            case 'zip_code':             // Generic ZIP code step
            case 'roof_zip':
            case 'windows_zip':
            case 'bathroom_zip':
            case 'siding_zip':
            case 'kitchen_zip':
            case 'decks_zip': return 2;  // ZIP codes use step 2 (dual mode)
            case 'full_name': return 5;    // Use step 5 for full name
            case 'address': return 6;      // Use step 6 for address
            default: return 4;             // Default to step 4 for other text inputs
        }
    }

    function getInputSelectorForStep(stepId) {
        switch (stepId) {
            case 'zip_code':             // Generic ZIP code step
            case 'roof_zip':
            case 'windows_zip':
            case 'bathroom_zip':
            case 'siding_zip':
            case 'kitchen_zip':
            case 'decks_zip':
            case 'adu_zip': return '#step2-zip-input, #step4-zip-input, #zip-input';
            case 'full_name': return '#name-input';
            case 'address': return '#address-input';
            default: return '.form-input';
        }
    }

    function extractCityFromAddress(address) {
        if (!address) return '';
        const parts = address.split(',');
        return parts.length > 1 ? parts[parts.length - 1].trim() : '';
    }

    function getStepNumberForChoice(stepId) {
        // Service-specific questions that come after ZIP codes
        const serviceQuestions = [
            'roof_action', 'roof_material',
            'windows_action', 'windows_replace_qty', 'windows_repair_needed',
            'bathroom_option',
            'siding_option', 'siding_material',
            'kitchen_action', 'kitchen_component',
            'decks_action', 'decks_material',
            'adu_action', 'adu_type'
        ];
        
        if (serviceQuestions.includes(stepId)) {
            return 3; // Always use step 3 for service-specific questions
        }
  
        return 3; // Always use step 3 to avoid conflicts with ZIP code step
    }

    // ─── GLOBAL EVENT BINDING ────────────────────────
    function bindGlobalEvents() {
        // Form submission
        $(document).on('submit', 'form', function(e) {
            e.preventDefault();
        });
        
        // Global back button handler - ensures all back buttons work
        $(document).on('click', '.btn-back:not(.handled)', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (currentStepIndex > 0) {
                previousStep();
            }
        });
        
        // Global next button handler 
        $(document).on('click', '.btn-next:not(.handled)', function(e) {
            e.preventDefault();
            e.stopPropagation();

            handleNextStep();
        });
        
        $(document).on('click', '.date-selection-section, .time-selection-section, .booking-disclaimer, .estimate-button-container', function(e) {
            e.stopPropagation();
        });
    }

    // ─── BOOKING SUBMISSION ──────────────────────────
    function submitBooking() {
        // CRITICAL: Prevent double submissions
        if (isSubmissionInProgress) {
            console.log('🚫 BLOCKED: Submission already in progress');
            return;
        }
        
        isSubmissionInProgress = true;
        
        // Validate that we have at least one appointment
        if (!selectedAppointments || selectedAppointments.length === 0) {
            showErrorMessage('Please select at least one appointment before submitting.');
            isSubmissionInProgress = false; // Reset flag on error
            return;
        }
        
        // For backward compatibility, include the first appointment as the main company/date/time
        const primaryAppointment = selectedAppointments[0];
        
        const bookingData = {
            action: 'bsp_submit_booking',
            nonce: (typeof BSP_Ajax !== 'undefined') ? BSP_Ajax.nonce : 'demo_nonce',
            ...formState,
            // Required fields expected by PHP backend
            company: primaryAppointment.company,
            selected_date: primaryAppointment.date,
            selected_time: primaryAppointment.time,
            // Additional data for multiple appointments
            appointments: JSON.stringify(selectedAppointments),
            total_appointments: selectedAppointments.length,
            // Lead continuity tracking
            session_id: getOrCreateSessionId()
        };

        // DEBUG: Log address data being sent
        console.group('🏠 ADDRESS DEBUG - Submit Booking');
        console.log('formState.address:', formState.address);
        console.log('formState.street_address:', formState.street_address);
        console.log('formState.customer_address:', formState.customer_address);
        console.log('formState.email:', formState.email);
        console.log('bookingData.address:', bookingData.address);
        console.log('bookingData after formState spread:', {
            address: bookingData.address,
            street_address: bookingData.street_address,
            customer_address: bookingData.customer_address,
            email: bookingData.email
        });
        console.groupEnd();

        // Add city and state from zip lookup service or hidden form fields
        if (window.zipLookupService && (window.zipLookupService.currentCity || window.zipLookupService.currentState)) {
            bookingData.city = window.zipLookupService.currentCity || '';
            bookingData.state = window.zipLookupService.currentState || '';
            console.log('🏙️ City/State from zipLookupService:', {
                city: bookingData.city,
                state: bookingData.state,
                serviceLoaded: window.zipLookupService.isDataLoaded
            });
        } else {
            // Fallback: check hidden form fields
            const cityInput = document.querySelector('input[name="city"], #city');
            const stateInput = document.querySelector('input[name="state"], #state');
            bookingData.city = cityInput ? cityInput.value : '';
            bookingData.state = stateInput ? stateInput.value : '';
            console.log('🏙️ City/State from hidden form fields:', {
                city: bookingData.city,
                state: bookingData.state,
                cityInputFound: !!cityInput,
                stateInputFound: !!stateInput
            });
        }

        // Additional fallback: check formState (including detected values from zip lookup)
        if (!bookingData.city && (formState.city || formState.detectedCity)) {
            bookingData.city = formState.city || formState.detectedCity;
        }
        if (!bookingData.state && (formState.state || formState.detectedState)) {
            bookingData.state = formState.state || formState.detectedState;
        }

        // Final debug log for city/state data
        console.log('🏙️ Final City/State values:', {
            city: bookingData.city,
            state: bookingData.state,
            cityEmpty: !bookingData.city,
            stateEmpty: !bookingData.state
        });

        // Add marketing data from cookies and URL parameters
        const utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'referrer'];
        
        // First try to get from URL parameters (current session)
        const urlParams = new URLSearchParams(window.location.search);
        
        utmParams.forEach(function(param) {
            let value = null;
            
            // Check URL parameters first (highest priority)
            if (urlParams.has(param)) {
                value = urlParams.get(param);
            }
            // Then check cookies as fallback
            else {
                const cookieValue = getCookie('bsp_' + param);
                if (cookieValue) {
                    value = cookieValue;
                }
            }
            
            // Set the value if found, otherwise set default for utm_source
            if (value) {
                bookingData[param] = value;
            } else if (param === 'utm_source') {
                // Default to 'direct' only if no source found anywhere
                bookingData[param] = 'direct';
            }
        });

        // Show loading state immediately for user feedback
        $('.btn-submit').prop('disabled', true).html('Processing...');
        
        // Debug logging for booking submission
        console.group('🚀 BSP Booking Submission Debug');
        console.log('📋 Form Data:', formState);
        console.log('📅 Selected Appointments:', selectedAppointments);
        console.log('📊 Complete Booking Data:', bookingData);
        console.log('🌐 Ajax URL:', (typeof BSP_Ajax !== 'undefined') ? BSP_Ajax.ajaxUrl : 'undefined');
        console.groupEnd();
        
        if (typeof BSP_Ajax !== 'undefined' && BSP_Ajax.ajaxUrl) {
            // Submit to WordPress with optimized settings for immediate response
            $.ajax({
                url: BSP_Ajax.ajaxUrl,
                type: 'POST',
                data: bookingData,
                timeout: 60000, // 60 second timeout (increased for backend processing)
                cache: false,
                success: function(response) {
                    console.group('✅ AJAX Success Response');
                    console.log('Response:', response);
                    console.log('Success:', response.success);
                    console.log('Data:', response.data);
                    console.groupEnd();
                    
                    if (response.success) {
                        console.log('🎉 Booking successful! ID:', response.data?.booking_id || 'N/A');
                        
                        // CRITICAL SESSION MANAGEMENT: Terminate session to prevent race conditions
                        isSessionCompleted = true;
                        isSubmissionInProgress = false;
                        
                        // Remove event listeners that could trigger incomplete lead capture
                        terminateSession();
                        
                        console.log('🔒 Session terminated - no more lead capture possible');
                        
                        // Refresh availability data for all companies involved in the booking
                        refreshAllCompanyAvailability(() => {
                            // Show success message after refreshing data
                            showSuccessMessage(response.data);
                        });
                    } else {
                        console.warn('⚠️ Booking failed:', response.data);
                        isSubmissionInProgress = false; // Reset on failure
                        showErrorMessage(response.data || 'Booking failed. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    console.group('❌ AJAX Error');
                    console.error('XHR Status:', xhr.status);
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    console.groupEnd();
                    
                    // Reset submission flag on error
                    isSubmissionInProgress = false;
                    
                    // Keep URL on waiting state since user is still on confirmation page
                    // Don't revert URL - just show error message
                    
                    let errorMessage = 'Connection error. Please check your internet and try again.';
                    
                    // Provide more specific error messages based on status
                    if (xhr.status === 0) {
                        if (status === 'timeout') {
                            errorMessage = 'Request timed out. Your booking may have been processed - please wait a moment and check your email, or contact us to confirm.';
                        } else {
                            errorMessage = 'Network error. Please check your internet connection.';
                        }
                    } else if (xhr.status === 403) {
                        errorMessage = 'Permission denied. Please refresh the page and try again.';
                    } else if (xhr.status === 404) {
                        errorMessage = 'Booking service not found. Please contact support.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error. Please try again or contact support.';
                    } else if (xhr.status >= 400) {
                        errorMessage = `Error ${xhr.status}: Please try again or contact support.`;
                    }
                    
                    showErrorMessage(errorMessage);
                },
                complete: function() {
                    console.log('🏁 AJAX request completed');
                    // Re-enable button regardless of success/failure
                    $('.btn-submit').prop('disabled', false).html('Confirm Booking');
                }
            });
        } else {
            // Demo mode - show success without backend (faster)
            setTimeout(() => {
                showSuccessMessage({ 
                    booking_id: 'DEMO-' + Date.now(),
                    message: 'Booking submitted successfully (Demo Mode)'
                });
            }, 300); // Reduced from 1000ms to 300ms
        }
    }
    
    function showSuccessMessage(data) {
        // Update URL hash for booking confirmation
        updateURLHash('booking-confirmed');
        
        const appointmentsList = selectedAppointments.map(apt => 
            `<li style="margin: 5px 0; padding: 8px; background: #e8f5e8; border-radius: 4px; font-size: 29px !important;">
                <strong>${apt.company}</strong><br>
                ${formatDate(apt.date)} at ${formatTime(apt.time)}
            </li>`
        ).join('');
        
        const $form = $('#booking-form');
        $form.html(`
            <div class="success-message" style="position: relative; z-index: 2; text-align: center; padding: 120px 20px 50px; color: white;">
                <div style="background: rgba(255, 255, 255, 0.95); color: #333; padding: 40px; border-radius: 15px; max-width: 600px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                    <div style="color: #79B62F; font-size: 72px; margin-bottom: 20px;">✅</div>
                    <h2 style="color: #79B62F; margin-bottom: 20px; font-weight: 700; font-size: 52px !important;">Booking${selectedAppointments.length > 1 ? 's' : ''} Confirmed!</h2>
                    <p style="margin-bottom: 20px; line-height: 1.6; font-size: 29px !important;">Thank you for your booking${selectedAppointments.length > 1 ? 's' : ''}. You will receive confirmation emails for each appointment shortly.</p>
                    
                    <div style="background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #79B62F; text-align: left;">
                        <h4 style="color: #79B62F; margin: 0 0 15px 0; font-size: 37.7px !important;">Your Scheduled Appointments:</h4>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            ${appointmentsList}
                        </ul>
                    </div>
                    
                    <div style="background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #79B62F;">
                        <strong style="color: #79B62F; font-size: 29px !important;">Next Steps:</strong><br>
                        <span style="color: #333; font-size: 29px !important;">Each company's team will call you within 24 hours to confirm their respective appointment details.</span>
                    </div>
                    <p style="font-size: 29px !important; color: #666; margin-top: 20px;">
                        Booking ID: #${data.booking_id || 'Generated'}
                    </p>
                </div>
            </div>
        `);
    }
    
    function showErrorMessage(message) {
        $('.btn-submit').prop('disabled', false).html('Confirm Booking');
        
        // Remove any existing error messages
        $('.error-message').remove();
        
        // Special handling for timeout messages
        let displayMessage = message;
        if (message.includes('timed out') || message.includes('timeout')) {
            displayMessage = `
                <strong>Processing Takes Time</strong><br>
                Your booking may have been successful even though this message appeared. 
                Please wait a moment and check your email for confirmation, or contact us to verify your booking status.
                <br><br>
                <small>Original message: ${message}</small>
            `;
        }
        
        // Show error message
        const $error = $(`
            <div class="error-message" style="
                margin: 15px 0; 
                padding: 15px; 
                background: ${message.includes('timed out') ? '#ff9800' : '#ff6b6b'}; 
                color: white; 
                border-radius: 8px; 
                font-weight: 500;
                text-align: center;
            ">${displayMessage}</div>
        `);
        $('.booking-step.active .form-navigation').before($error);
        
        // Remove error after 7 seconds
        setTimeout(() => {
            $error.fadeOut(() => $error.remove());
        }, 7000);
    }

    // ─── MULTIPLE APPOINTMENT MANAGEMENT ─────────────
    function updateNextButtonState() {
        // Enable next button if at least one appointment is selected
        const hasAppointments = selectedAppointments.length > 0;
        $('.booking-step[data-step="8"] .btn-next').prop('disabled', !hasAppointments);
    }
    
    function updateAppointmentSummary() {
        const $stepEl = $('.booking-step[data-step="8"]');
        let $summary = $stepEl.find('.appointment-summary');
        
        if ($summary.length === 0) {
            $summary = $('<div class="appointment-summary" style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; margin-top: 20px; margin-bottom: 20px; color: white;"></div>');
            // Append to the end of step-card instead of prepending to the top
            $stepEl.find('.step-card').append($summary);
        }
        
        if (selectedAppointments.length === 0) {
            $summary.hide();
        } else {
            const summaryHtml = `
                <h4 style="margin: 0 0 10px 0; color: #79B62F;">Selected Appointments (${selectedAppointments.length})</h4>
                ${selectedAppointments.map(apt => `
                    <div style="margin: 5px 0; padding: 8px; background: rgba(255,255,255,0.1); border-radius: 4px;">
                        <strong>${apt.company}</strong> - ${formatDate(apt.date)} at ${formatTime(apt.time)}
                    </div>
                `).join('')}
                <p style="margin: 10px 0 0 0; font-size: 12px; opacity: 0.8;">
                    You can select up to 3 appointments with different companies
                </p>
            `;
            $summary.html(summaryHtml).show();
        }
        
        // Update company card visual states
        updateCompanyCardStates();
    }
    
    function updateCompanyCardStates() {
        // Reset all company cards
        $('.company-card').removeClass('has-appointment');
        
        // Add has-appointment class to companies with selected appointments
        selectedAppointments.forEach(apt => {
            $(`.company-card`).each(function() {
                const $card = $(this);
                const cardCompanyName = $card.find('.company-name-text').text().trim();
                if (cardCompanyName === apt.company) {
                    $card.addClass('has-appointment');
                }
            });
        });
        

    }
    
    // ─── EXPORT FOR GLOBAL ACCESS ────────────────────
    window.BookingSystem = {
        init: initBookingSystem,
        nextStep: nextStep,
        previousStep: previousStep,
        getState: () => formState
    };

    // Add "Request Estimate" button functionality - Updated to work with appointment system
    $(document).on('click', '.btn-request-estimate', function() {
        const $button = $(this);
        const company = $button.data('company');
        const companyId = $button.data('company-id');
        
        // Check if user has any appointments selected (not necessarily for this specific company)
        if (selectedAppointments.length === 0) {
            alert('Please select at least one date and time before requesting an estimate.');
            return;
        }
   
        let companyAppointment = null;
        if (companyId) {
            companyAppointment = selectedAppointments.find(apt => apt.companyId == companyId);
        }
        if (!companyAppointment && company) {
            companyAppointment = selectedAppointments.find(apt => apt.company === company);
        }
        
        if (!companyAppointment) {
            alert('Please select a date and time for ' + company + ' before requesting an estimate.');
            return;
        }
        window.bookingFormData = window.bookingFormData || {};
        window.bookingFormData.selectedAppointments = selectedAppointments;
        
        nextStep();
    });

    // ─── INCOMPLETE LEAD CAPTURE SYSTEM ──────────────────────────
    
    /**
     * Capture incomplete lead data and send to server
     * @param {string} trigger - What triggered this capture
     * @param {object} extraData - Additional data to include
     */
    function captureIncompleteLeadData(trigger, extraData = {}) {
        // CRITICAL SESSION MANAGEMENT: Don't capture if session is completed
        if (isSessionCompleted) {
            console.log('🚫 BLOCKED: Lead capture blocked - session completed');
            return;
        }
        
        // Check if session cookie indicates completion
        const sessionId = getCookie('bsp_session_id');
        if (sessionId && sessionId.includes('_COMPLETED')) {
            console.log('🚫 BLOCKED: Lead capture blocked - session marked as completed');
            isSessionCompleted = true; // Update flag for consistency
            return;
        }
        
        // Don't capture if we don't have enough data
        if (!formState || Object.keys(formState).length === 0) {
            console.log('📊 Skipping lead capture - no form data yet');
            return;
        }

        console.group('📊 Capturing Incomplete Lead Data');
        console.log('Trigger:', trigger);
        console.log('Current Form State:', formState);
        console.log('Current Step:', currentStepIndex);
        console.log('Extra Data:', extraData);

        // Calculate completion percentage
        const completionPercentage = calculateCompletionPercentage();
        const formStep = determineCurrentFormStep();

        // Get current form values directly from DOM to ensure we capture everything
        const getCurrentFieldValue = (selectors) => {
            for (const selector of selectors) {
                const element = document.querySelector(selector);
                if (element && element.value && element.value.trim()) {
                    return element.value.trim();
                }
            }
            return '';
        };

        // Prepare lead data with comprehensive field collection
        const leadData = {
            action: 'bsp_capture_incomplete_lead',
            nonce: (typeof bspLeadConfig !== 'undefined') ? bspLeadConfig.nonce : 
                   (typeof BSP_Ajax !== 'undefined') ? BSP_Ajax.nonce : 'demo_nonce',
            trigger: trigger,
            session_id: getOrCreateSessionId(),
            service_type: formState.service || '',
            zip_code: formState.zip_code || getCurrentFieldValue(['#zip-input', '#step2-zip-input', 'input[name="zip_code"]', 'input[id*="zip"]']),
            customer_name: formState.full_name || getCurrentFieldValue(['#full-name-input', 'input[name="full_name"]', 'input[id*="name"]:not([id*="company"])']),
            customer_email: formState.email_address || formState.email || getCurrentFieldValue(['#email-input', 'input[name="email"]', 'input[type="email"]']),
            customer_phone: formState.phone_number || formState.phone || getCurrentFieldValue(['#phone-input', 'input[name="phone"]', 'input[type="tel"]']),
            customer_address: formState.street_address || formState.address || getCurrentFieldValue(['#address-input', 'input[name="street_address"]', 'input[name="address"]', 'textarea[name="address"]']),
            completion_percentage: completionPercentage,
            form_step: formStep,
            current_step_index: currentStepIndex,
            page_url: window.location.href,
            referrer: document.referrer,
            user_agent: navigator.userAgent,
            timestamp: new Date().toISOString(),
            time_on_page: Math.round((Date.now() - window.pageLoadTime) / 1000),
            ...extraData
        };

        // Add service-specific data
        const serviceFields = [
            'kitchen_action', 'kitchen_component', 'roof_action', 'roof_material',
            'windows_action', 'windows_replace_qty', 'bathroom_option', 
            'siding_option', 'siding_material', 'decks_action', 'decks_material',
            'adu_action', 'adu_type'
        ];
        
        serviceFields.forEach(field => {
            if (formState[field]) {
                leadData[field] = formState[field];
            }
        });

        // Add UTM parameters
        const utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        utmParams.forEach(param => {
            const value = getURLParameter(param) || getCookie('bsp_' + param);
            if (value) {
                leadData[param] = value;
            }
        });

        // Add appointment data if available
        if (selectedAppointments && selectedAppointments.length > 0) {
            console.log('🎯 Adding appointment data to lead capture:', selectedAppointments);
            
            // Convert appointments to JSON string for server
            leadData.appointments = JSON.stringify(selectedAppointments);
            
            // Extract companies, dates, and times for easier server processing
            const companies = selectedAppointments.map(apt => apt.company).join(', ');
            const dates = selectedAppointments.map(apt => apt.date).join(', ');
            const times = selectedAppointments.map(apt => apt.time).join(', ');
            
            leadData.company = companies;
            leadData.booking_date = dates;
            leadData.booking_time = times;
            
            // Add primary appointment for single appointment scenarios
            if (selectedAppointments.length > 0) {
                leadData.selected_date = selectedAppointments[0].date;
                leadData.selected_time = selectedAppointments[0].time;
            }
            
            console.log('📅 Appointment fields added:', {
                appointments: leadData.appointments,
                company: leadData.company,
                booking_date: leadData.booking_date,
                booking_time: leadData.booking_time
            });
        } else {
            console.log('⚠️ No appointments available to add to lead capture');
        }

        console.log('📤 Lead Data to Send:', leadData);

        // Send to server
        if (typeof BSP_Ajax !== 'undefined' && BSP_Ajax.ajaxUrl) {
            $.ajax({
                url: BSP_Ajax.ajaxUrl,
                type: 'POST',
                data: leadData,
                success: function(response) {
                    console.log('✅ Lead data captured successfully:', response);
                },
                error: function(xhr, status, error) {
                    console.error('❌ Failed to capture lead data:', error);
                }
            });
        } else {
            console.warn('⚠️ BSP_Ajax not available - lead data not sent');
        }

        console.groupEnd();
    }

    /**
     * Calculate completion percentage based on form fields
     */
    function calculateCompletionPercentage() {
        const requiredFields = [
            'service', 'zip_code', 'full_name', 'email_address', 
            'phone_number', 'street_address'
        ];
        
        const completedFields = requiredFields.filter(field => {
            const value = formState[field];
            return value && value.toString().trim() !== '';
        });
        
        return Math.round((completedFields.length / requiredFields.length) * 100);
    }

    /**
     * Determine current form step description
     */
    function determineCurrentFormStep() {
        if (currentStepIndex >= 4) return 'Step 5: Confirmation';
        if (currentStepIndex >= 3) return 'Step 4: Date Selection';
        if (currentStepIndex >= 2) return 'Step 3: Contact Info';
        if (currentStepIndex >= 1) return 'Step 2: Service Details';
        return 'Step 1: Service Selection';
    }

    /**
     * CRITICAL SESSION MANAGEMENT: Terminate session to prevent race conditions
     * Remove event listeners and invalidate session after successful booking
     */
    function terminateSession() {
        console.log('🔒 TERMINATING SESSION - Removing lead capture event listeners');
        
        // Clear any pending lead capture timeouts
        if (window.leadCaptureTimeout) {
            clearTimeout(window.leadCaptureTimeout);
            window.leadCaptureTimeout = null;
            console.log('⏰ Cleared leadCaptureTimeout');
        }
        
        // Clear any periodic lead capture if it exists
        if (window.leadCaptureInterval) {
            clearInterval(window.leadCaptureInterval);
            window.leadCaptureInterval = null;
            console.log('⏰ Cleared leadCaptureInterval');
        }
        
        // COMPREHENSIVE CLEANUP: Clear all non-essential intervals and timeouts
        // Store the highest setTimeout/setInterval ID to clear all booking-related timers
        const highestTimeoutId = setTimeout(() => {}, 0);
        for (let i = 1; i < highestTimeoutId; i++) {
            // Only clear timeouts that are likely from booking system (not toast notifications)
            // Toast notifications use specific naming/tracking, so generic cleanup is safer
            if (window.leadCaptureTimeout === i || 
                (window.bookingSystemTimeouts && window.bookingSystemTimeouts.includes(i))) {
                clearTimeout(i);
            }
        }
        clearTimeout(highestTimeoutId);
        
        // Clean up lead capture system if available
        if (window.bspLeadCapture && window.bspLeadCapture.LeadCapture && window.bspLeadCapture.LeadCapture.cleanup) {
            window.bspLeadCapture.LeadCapture.cleanup();
            console.log('🧹 Lead capture system cleaned up');
        }
        
        // Remove beforeunload listener that captures incomplete leads
        $(window).off('beforeunload');
        console.log('🚫 Removed beforeunload listener');
        
        // Remove form interaction listeners to prevent future lead capture
        $(document).off('input change', 'input, select, textarea');
        $(document).off('click', 'button, .btn');
        console.log('🚫 Removed form interaction listeners');
        
        // Clear the session cookie to prevent future requests
        const currentSessionId = getOrCreateSessionId();
        setCookie('bsp_session_id', currentSessionId + '_COMPLETED', 0.1); // Short expire time
        
        console.log('✅ Session terminated successfully - no more incomplete lead webhooks will be sent');
    }

    /**
     * Get or create session ID for tracking
     */
    function getOrCreateSessionId() {
        let sessionId = getCookie('bsp_session_id');
        if (!sessionId) {
            sessionId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 8);
            setCookie('bsp_session_id', sessionId, 1); // 1 day
        }
        return sessionId;
    }

    /**
     * Get URL parameter value
     */
    function getURLParameter(name) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }

    /**
     * Set cookie helper function
     */
    function setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
    }

    (function() {
    
    })();

}); // End of jQuery document ready
