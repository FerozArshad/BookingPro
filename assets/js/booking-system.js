jQuery(document).ready(function($) {
    'use strict';
    
    // ═══════════════════════════════════════════════════════════════════════════════════
    // BOOKING SYSTEM CONFIGURATION
    // Multi-step form configuration with service-specific dependencies and question flow
    // ═══════════════════════════════════════════════════════════════════════════════════

    const CONFIG = {
        steps: [
            { id: 'service', type: 'single-choice', question: 'Which service are you interested in?', options: ['Roof', 'Windows', 'Bathroom', 'Siding', 'Kitchen', 'Decks', 'ADU'] },
            
            // A single, dynamic ZIP code step that appears after any service is selected.
            { 
                id: 'zip_code', 
                type: 'text', 
                depends_on: ['service'], // Depends on 'service' key being present in formState
                question_template: 'Start your {service} remodel today.<br>Find local pros now.', 
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
                    // ...existing code...
                
                // Push new state without reloading
                window.history.pushState('', document.title, currentURL.toString());
            }
        } catch (error) {
            // Silently fail to avoid breaking functionality
        }
    }

    // ─── URL SERVICE DETECTION (HYBRID APPROACH) ─────
    function getServiceFromURL() {
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
        
        // For URL-based service selection, we skip the service selection step entirely
        // If no service-specific step found, something is wrong - go to service selection
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
        
        // Check for URL-based service preselection (hash or query params)
        const preselectedService = getServiceFromURL();
        
        if (preselectedService) {
            // Auto-select service and skip service selection step
            formState.service = preselectedService;
            
            // Skip directly to first service-specific step (ZIP code step)
            const newStepIndex = findFirstServiceSpecificStep(preselectedService);
            currentStepIndex = newStepIndex;
        }
        
        // Set default background
        updateBackground();
        
        // Initialize first step (or preselected step)
        renderCurrentStep();
        
        // Bind global events
        bindGlobalEvents();
        
        // Initialize hash navigation support (non-breaking)
        initHashNavigation();
        

    }
    
    // ─── HASH NAVIGATION SUPPORT (NON-BREAKING) ──────
    function initHashNavigation() {
        try {
            // Listen for browser back/forward navigation
            window.addEventListener('popstate', function(event) {
                // Only handle if we're on a booking form page
                if (!$('#booking-form').length) return;
                
                const hash = window.location.hash.replace('#', '');
                
                // Handle specific hash navigation
                if (!hash) {
                    // No hash - return to service selection if not already there
                    if (currentStepIndex !== 0) {
                        currentStepIndex = 0;
                        renderCurrentStep();
                    }
                } else if (hash === 'service-selection' || ['roof', 'windows', 'bathroom', 'siding', 'kitchen', 'decks', 'adu'].includes(hash)) {
                    // Service selection or specific service - ensure we're in the right flow
                    if (hash !== 'service-selection' && currentStepIndex === 0) {
                        // User came back to a service-specific hash, maintain the selection
                        formState.service = hash.charAt(0).toUpperCase() + hash.slice(1);
                    }
                } else if (hash.endsWith('-scheduling')) {
                    // Service-specific scheduling hash (e.g., "roof-scheduling", "decks-scheduling")
                    const service = hash.replace('-scheduling', '');
                    if (['roof', 'windows', 'bathroom', 'siding', 'kitchen', 'decks', 'adu'].includes(service)) {
                        // Set the service if not already set
                        if (!formState.service) {
                            formState.service = service.charAt(0).toUpperCase() + service.slice(1);
                        }
                        // Navigate to scheduling step if not already there
                        const schedulingStepIndex = CONFIG.steps.findIndex(step => step.id === 'schedule');
                        if (schedulingStepIndex !== -1 && currentStepIndex !== schedulingStepIndex) {
                            currentStepIndex = schedulingStepIndex;
                            renderCurrentStep();
                        }
                    }
                } else if (hash.endsWith('-booking-confirmed')) {
                    // Service-specific booking confirmation hash (e.g., "roof-booking-confirmed", "decks-booking-confirmed")
                    const service = hash.replace('-booking-confirmed', '');
                    if (['roof', 'windows', 'bathroom', 'siding', 'kitchen', 'decks', 'adu'].includes(service)) {
                        // Set the service if not already set
                        if (!formState.service) {
                            formState.service = service.charAt(0).toUpperCase() + service.slice(1);
                        }
                        // Navigate to confirmation step if not already there
                        const confirmationStepIndex = CONFIG.steps.findIndex(step => step.id === 'confirmation');
                        if (confirmationStepIndex !== -1 && currentStepIndex !== confirmationStepIndex) {
                            currentStepIndex = confirmationStepIndex;
                            renderCurrentStep();
                        }
                    }
                } else if (hash.endsWith('-waiting-booking-confirmation')) {
                    // Service-specific waiting confirmation hash (e.g., "roof-waiting-booking-confirmation")
                    const service = hash.replace('-waiting-booking-confirmation', '');
                    if (['roof', 'windows', 'bathroom', 'siding', 'kitchen', 'decks', 'adu'].includes(service)) {
                        // Set the service if not already set
                        if (!formState.service) {
                            formState.service = service.charAt(0).toUpperCase() + service.slice(1);
                        }
                        // Navigate to confirmation step and show waiting state
                        const confirmationStepIndex = CONFIG.steps.findIndex(step => step.id === 'confirmation');
                        if (confirmationStepIndex !== -1 && currentStepIndex !== confirmationStepIndex) {
                            currentStepIndex = confirmationStepIndex;
                            renderCurrentStep();
                        }
                    }
                }
                // For other states, we don't need special handling
                // as these states are maintained by the form itself
            });
        } catch (error) {
            // Silently fail to avoid breaking functionality
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
                
                // NEW: Handle dynamic zip code step background
                if (stepId === 'zip_code') {
                    $form.addClass(`service-${service.toLowerCase()}`);
                    $form.addClass(`${service.toLowerCase()}-step-0`);
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

    // ═══════════════════════════════════════════════════════════════════════════════════
    // STEP STATE MANAGEMENT
    // Handles resetting and managing state between form steps
    // ═══════════════════════════════════════════════════════════════════════════════════
    function resetStep2State() {
        // Reset step 2 to its default state (hide both text and choice elements initially)
        $('#step2-text-input').hide();
        $('#step2-options').show(); // Default to choice mode
        $('#step2-options').empty(); // Clear any previous options
        $('#step2-zip-input, #zip-input').val(''); // Clear ZIP input
        $('.booking-step[data-step="2"] .btn-next').prop('disabled', true); // Disable next button
    }

    // ═══════════════════════════════════════════════════════════════════════════════════
    // STEP NAVIGATION SYSTEM
    // Core functions for moving between form steps and rendering step content
    // ═══════════════════════════════════════════════════════════════════════════════════
    function renderCurrentStep() {
        const step = CONFIG.steps[currentStepIndex];
        
        if (!step) {
            console.error('Step not found:', currentStepIndex);
            return;
        }

        // Check dependencies before rendering step
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
                // This is a service-specific step - check if the service matches
                if (formState[dependsKey] !== dependsValue) {
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



        // Hide all steps
        $('.booking-step').removeClass('active');
        
        // Reset step 2 state (since it can be either text or choice mode)
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
        $('.booking-step[data-step="1"]').addClass('active');
        
        // Clear hash when returning to service selection (unless we have a preselected service)
        if (!formState.service) {
            updateURLHash('clear');
        }
        
        // Show preselected service as selected
        if (formState.service) {
            $(`.service-option[data-service="${formState.service}"]`).addClass('selected');
            updateURLHash('service-selection');
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
        
        $stepEl.addClass('active');
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
        $stepEl.addClass('active');
        
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
        
        // Update navigation
        updateNavigation($stepEl);
    }

    function showFormStep(step) {
        const $stepEl = $('.booking-step[data-step="7"]');
        $stepEl.addClass('active');
        
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
        $stepEl.addClass('active');
        
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
                        console.warn('Company card missing data-company-id attribute, using fallback lookup for:', companyName);
                        initializeCompanyCalendar(companyData.id);
                    } else {
                        console.error('Company card missing data-company-id attribute and could not find company by name:', companyName, $card);
                    }
                } else {
                    console.error('Company card missing both data-company-id and company name:', $card);
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
        $stepEl.addClass('active');
        
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
        
        if ($calendar.length === 0) {
            console.error('ERROR: Calendar element not found for company ID:', companyId);
            console.error('Available company IDs:', $('.calendar-grid').map(function() {
                return $(this).data('company-id'); 
            }).get());
            return;
        }
        
        if ($timeSlots.length === 0) {
            console.error('ERROR: Time slots element not found for company ID:', companyId);
            console.error('Available time slot company IDs:', $('.time-slots').map(function() {
                return $(this).data('company-id'); 
            }).get());
            return;
        }
        
        // Find company data using ID
        const companyData = CONFIG.companies ? CONFIG.companies.find(c => c.id == companyId) : null;
        if (!companyData) {
            console.error('ERROR: Company data not found for ID:', companyId);
            console.error('Available companies:', CONFIG.companies ? CONFIG.companies.map(c => ({id: c.id, name: c.name})) : 'No companies configured');
            return;
        }
        
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
                console.error('Company not found for ID:', companyId);
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
            console.error('Company not found for ID:', companyId, 'Available companies:', CONFIG.companies);
            return;
        }
        
        // Check if we're in demo mode (no real WordPress backend)


        
        // Check if we have real AJAX configuration
        if (!CONFIG.ajaxUrl || !CONFIG.nonce) {
            const errorMsg = '<div class="error-message">Booking system configuration error. Please refresh the page and try again.</div>';
            $calendar.html(errorMsg);
            console.error('Missing AJAX configuration:', { ajaxUrl: CONFIG.ajaxUrl, nonce: CONFIG.nonce });
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
                console.error('AJAX error details:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                // Display clean, user-friendly error message instead of demo fallback
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
        // Find company data by name
        const companyData = CONFIG.companies ? CONFIG.companies.find(c => c.name === company) : null;
        if (!companyData) {
            console.error('Company data not found for:', company);
            $calendar.html('<div class="error-message">Company not found</div>');
            return;
        }
        
        $calendar.empty();
        
        // Sort dates to ensure chronological order
        const sortedDates = Object.keys(availabilityData).sort();

        // Display all available dates (limited to 3 days by backend)
        sortedDates.forEach(dateStr => {
            const dayData = availabilityData[dateStr];
            
            // Calculate availability metrics
            const totalSlots = dayData.slots.length;
            const availableSlots = dayData.slots.filter(slot => slot.available).length;
            const unavailableSlots = totalSlots - availableSlots;
            
            // Enhanced availability logic:
            // - Disable if NO available slots (fully booked)
            // - Disable if 5 or more slots are unavailable AND less than 2 available (heavily booked)
            // - Otherwise allow selection (user can pick from remaining slots)
            const isFullyBooked = availableSlots === 0;
            const isHeavilyBooked = unavailableSlots >= 5 && availableSlots < 2;
            const shouldDisableDay = isFullyBooked || isHeavilyBooked;
            
            // Check if this company already has an appointment on this date
            const hasExistingAppointment = selectedAppointments.some(apt => 
                apt.company === company && apt.date === dateStr
            );
            
            // Determine CSS classes and availability
            let cssClass, dayTitle;
            if (shouldDisableDay) {
                cssClass = 'unavailable disabled';
                if (isFullyBooked) {
                    dayTitle = 'Fully booked - no available time slots';
                } else {
                    dayTitle = `Very limited availability - only ${availableSlots} slots remaining`;
                }
            } else {
                cssClass = 'available';
                if (availableSlots <= 2) {
                    dayTitle = `Limited availability - ${availableSlots} slots remaining`;
                } else {
                    dayTitle = `${availableSlots} time slots available - click to select`;
                }
            }
            
            const $day = $(`
                <div class="calendar-day ${cssClass} ${hasExistingAppointment ? 'selected' : ''}" 
                     data-date="${dateStr}" 
                     data-company="${companyData.name}"
                     data-company-id="${companyData.id}"
                     data-available-slots="${availableSlots}"
                     data-total-slots="${totalSlots}"
                     ${shouldDisableDay ? 'data-disabled="true"' : ''}
                     title="${dayTitle}">
                    <div class="day-number">${dayData.day_number}</div>
                    <div class="day-name">${dayData.day_name}</div>
                    ${(availableSlots > 0 && availableSlots <= 3) ? `<div class="slots-indicator">${availableSlots} left</div>` : ''}
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

    function loadTimeSlots($timeSlots, company, date) {

        $timeSlots.empty().append('<div class="loading-spinner">Loading time slots...</div>');
        
        // Get availability data from the calendar
        const $calendar = $(`.calendar-grid[data-company="${company}"]`);
        const availabilityData = $calendar.data('availability-data');
        



        
        if (availabilityData && availabilityData[date]) {
            const dayData = availabilityData[date];


            $timeSlots.empty();
            
            let slotsAdded = 0;
            dayData.slots.forEach(slot => {
                const isAvailable = slot.available;
                const slotClass = isAvailable ? 'time-slot' : 'time-slot disabled';
                const slotTitle = isAvailable ? 'Click to select this time' : 'This time slot is already booked';
                
                const $slot = $(`
                    <div class="${slotClass}" 
                         data-time="${slot.time}" 
                         data-company="${company}" 
                         data-date="${date}"
                         ${!isAvailable ? 'data-disabled="true"' : ''}
                         title="${slotTitle}">
                        ${slot.formatted}
                        ${!isAvailable ? '<span class="booked-indicator">Booked</span>' : ''}
                    </div>
                `);
                
                $timeSlots.append($slot);
                if (isAvailable) slotsAdded++;
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
                        dayData.slots.forEach(slot => {
                            const isAvailable = slot.available;
                            const slotClass = isAvailable ? 'time-slot' : 'time-slot disabled';
                            const slotTitle = isAvailable ? 'Click to select this time' : 'This time slot is already booked';
                            
                            const $slot = $(`
                                <div class="${slotClass}" 
                                     data-time="${slot.time}" 
                                     data-company="${company}" 
                                     data-company-id="${companyData.id}" 
                                     data-date="${date}"
                                     ${!isAvailable ? 'data-disabled="true"' : ''}
                                     title="${slotTitle}">
                                    ${slot.formatted}
                                    ${!isAvailable ? '<span class="booked-indicator">Booked</span>' : ''}
                                </div>
                            `);
                            
                            $timeSlots.append($slot);
                            if (isAvailable) slotsAdded++;
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
                    console.error('Time slots AJAX error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });
                    
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
        const serviceAutoSelected = getServiceFromURL() !== null;
        const currentStep = CONFIG.steps[currentStepIndex];
        
        // Hide back button if:
        // 1. We're at step 0 (service selection), OR
        // 2. We're at the first service-specific step and service was auto-selected from URL
        if (currentStepIndex === 0) {
            $backBtn.hide();
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
        renderCurrentStep();
    }

    function previousStep() {
        if (currentStepIndex > 0) {
            // Check if service was auto-selected from URL
            const serviceAutoSelected = getServiceFromURL() !== null;
            const currentStep = CONFIG.steps[currentStepIndex];
            
            // If we're on the first service-specific step and service was auto-selected,
            // go back to landing page instead of service selection
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
            // Hide current step
            $('.booking-step').removeClass('active');
            
            // Decrement step index
            currentStepIndex--;
            
            // Check if we need to skip steps due to dependencies when going backward
            let attempts = 0;
            while (currentStepIndex > 0 && attempts < 20) { // Safety limit
                const step = CONFIG.steps[currentStepIndex];
                attempts++;
                
                // If step has dependencies, check if they are still valid
                if (step && step.depends_on) {
                    const [dependsKey, dependsValue] = step.depends_on;
                    if (formState[dependsKey] !== dependsValue) {
                        // Skip this step when going back
                        currentStepIndex--;
                        continue;
                    }
                }
                break;
            }
            

            
            // Render the correct previous step
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
        
        // Special validation for ZIP code steps
        if (step && step.id.endsWith('_zip')) {
            const $currentStep = $('.booking-step.active');
            const zipValue = $currentStep.find('#zip-input, #step2-zip-input, #step4-zip-input').val().trim();
            
            // Check if ZIP code is valid
            if (!zipValue || zipValue.length !== 5) {
                showErrorMessage('Please enter a 5-digit ZIP code');
                return;
            }
            
            // Check against ZIP lookup service
            if (window.zipLookupService && !window.zipLookupService.isValidZipCode(zipValue)) {
                showErrorMessage('Please enter a valid US ZIP code');
                return;
            }
        }
        
        // Collect form data from current step
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
                formState[step.id] = $currentStep.find(inputSelector).val().trim();
                break;
            case 'form':
                formState.phone = $('#phone-input').val().trim();
                formState.email = $('#email-input').val().trim();
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
                if (formState[dependsKey] === dependsValue) {
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
        // For service-specific choice questions, prioritize step 3
        // For other choice questions, use step 2 if available, otherwise step 3
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
            return 3; // Use step 3 for service-specific questions
        }
        
        // For other choice questions, use available step
        return $('.booking-step[data-step="2"]').hasClass('active') ? 3 : 2;
    }

    // ─── GLOBAL EVENT BINDING ────────────────────────
    function bindGlobalEvents() {
        // Handle browser back/forward
        $(window).on('popstate', function() {
            // Prevent default browser navigation
            return false;
        });
        
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
        
        // Company card click handlers removed - no expand/collapse needed
        // All companies show appointment options by default
        
        // Prevent clicks on appointment details from bubbling up
        $(document).on('click', '.date-selection-section, .time-selection-section, .booking-disclaimer, .estimate-button-container', function(e) {
            e.stopPropagation();
        });
    }

    // ─── BOOKING SUBMISSION ──────────────────────────
    function submitBooking() {
        // URL is already showing waiting-booking-confirmation from the confirmation step
        // No need to update URL here anymore
        
        // Validate that we have at least one appointment
        if (!selectedAppointments || selectedAppointments.length === 0) {
            showErrorMessage('Please select at least one appointment before submitting.');
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
            total_appointments: selectedAppointments.length
        };

        // Add city and state from zip lookup service
        if (window.zipLookupService) {
            bookingData.city = window.zipLookupService.currentCity || '';
            bookingData.state = window.zipLookupService.currentState || '';
        }

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
        
        // ═══════════════════════════════════════════════════════════════════════════════════
        // OPTIMIZED FORM SUBMISSION
        // Fast response achieved through background processing - emails, Google Sheets sync,
        // and notifications are deferred to background jobs for millisecond response times
        // ═══════════════════════════════════════════════════════════════════════════════════
        
        // Check if WordPress AJAX is available
        if (typeof BSP_Ajax !== 'undefined' && BSP_Ajax.ajaxUrl) {
            // Submit to WordPress with optimized settings for immediate response
            $.ajax({
                url: BSP_Ajax.ajaxUrl,
                type: 'POST',
                data: bookingData,
                timeout: 10000, // 10 second timeout
                cache: false,
                success: function(response) {
                    if (response.success) {
                        showSuccessMessage(response.data);
                    } else {
                        // Keep URL on waiting state since user is still on confirmation page
                        // Don't revert URL - just show error message
                        showErrorMessage(response.data || 'Booking failed. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    // Keep URL on waiting state since user is still on confirmation page
                    // Don't revert URL - just show error message
                    
                    let errorMessage = 'Connection error. Please check your internet and try again.';
                    
                    // Provide more specific error messages based on status
                    if (xhr.status === 0) {
                        errorMessage = 'Network error. Please check your internet connection.';
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
        
        // Show error message
        const $error = $(`
            <div class="error-message" style="
                margin: 15px 0; 
                padding: 15px; 
                background: #ff6b6b; 
                color: white; 
                border-radius: 8px; 
                font-weight: 500;
                text-align: center;
            ">${message}</div>
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
        
        // Check if user has selected date and time for this specific company
        // Try to match by company ID first (more reliable), then fall back to company name
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
        
        // Store the appointments data globally for the summary step
        window.bookingFormData = window.bookingFormData || {};
        window.bookingFormData.selectedAppointments = selectedAppointments;
        

        
        // Move to the next step (summary) using the existing navigation system
        nextStep();
    });

});
