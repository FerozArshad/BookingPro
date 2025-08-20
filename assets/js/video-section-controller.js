// /**
//  * Video Section Controller
//  * Shows/hides the video section based on active booking step
//  * Shows video on: Step 1 (service selection) and Success screen (after form submission)
//  * Hides video on: All other steps (2-9)
//  */

// (function($) {
//     'use strict';

//     // Video section controller class
//     class VideoSectionController {
//         constructor() {
//             this.videoSection = null;
//             this.targetSteps = [1]; // First step only - success screen is detected separately
//             this.init();
//         }

//         init() {
//             // Wait for DOM to be ready
//             $(document).ready(() => {
//                 this.findVideoSection();
//                 this.setupObserver();
//                 this.checkInitialState();
//             });
//         }

//         findVideoSection() {
//             // Find the video section by ID
//             this.videoSection = $('#video-parent');
            
//             if (this.videoSection.length === 0) {
//                 console.warn('Video section with ID "video-parent" not found');
//                 return;
//             }

//             // Initially hide the video section
//             this.videoSection.hide();
//             console.log('🎬 Video section controller initialized');
//         }

//         setupObserver() {
//             if (!this.videoSection || this.videoSection.length === 0) {
//                 return;
//             }

//             // Method 1: Watch for class changes on booking steps using MutationObserver
//             this.setupMutationObserver();

//             // Method 2: Listen for custom events if they exist
//             this.setupEventListeners();

//             // Method 3: Periodic check as fallback
//             this.setupPeriodicCheck();
//         }

//         setupMutationObserver() {
//             // Watch for changes to the 'active' class on booking steps
//             const observer = new MutationObserver((mutations) => {
//                 mutations.forEach((mutation) => {
//                     if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
//                         const target = mutation.target;
//                         if ($(target).hasClass('booking-step') && $(target).hasClass('active')) {
//                             this.handleStepChange(target);
//                         }
//                     }
//                 });
//             });

//             // Observe all booking steps
//             $('.booking-step').each((index, element) => {
//                 observer.observe(element, {
//                     attributes: true,
//                     attributeFilter: ['class']
//                 });
//             });

//             console.log('🔍 MutationObserver setup complete for', $('.booking-step').length, 'steps');
//         }

//         setupEventListeners() {
//             // Listen for step navigation events if they exist
//             $(document).on('step:changed step:rendered step:active', (event, stepData) => {
//                 this.handleStepChangeEvent(stepData);
//             });

//             // Listen for clicks on navigation buttons to detect step changes
//             $(document).on('click', '.btn-next, .btn-back, .btn-submit', () => {
//                 // Small delay to let the step change complete
//                 setTimeout(() => {
//                     this.checkCurrentStep();
//                 }, 100);
//             });

//             // Listen for form submission events that might trigger success screen
//             $(document).on('click', '.btn-submit', () => {
//                 // Longer delay to allow for form submission and success screen display
//                 setTimeout(() => {
//                     this.checkSuccessScreen();
//                 }, 1000);
//             });

//             // Watch for DOM changes that might indicate success screen appearance
//             const observer = new MutationObserver((mutations) => {
//                 mutations.forEach((mutation) => {
//                     if (mutation.type === 'childList') {
//                         // Check if success message was added to DOM
//                         const addedNodes = Array.from(mutation.addedNodes);
//                         const hasSuccessMessage = addedNodes.some(node => 
//                             node.nodeType === 1 && 
//                             (node.classList?.contains('success-message') || 
//                              node.querySelector?.('.success-message'))
//                         );
                        
//                         if (hasSuccessMessage) {
//                             setTimeout(() => {
//                                 this.checkSuccessScreen();
//                             }, 100);
//                         }
//                     }
//                 });
//             });

//             // Observe the main booking form for changes
//             const bookingForm = document.querySelector('#booking-form, .booking-system-form, .booking-form');
//             if (bookingForm) {
//                 observer.observe(bookingForm, {
//                     childList: true,
//                     subtree: true
//                 });
//             }
//         }

//         setupPeriodicCheck() {
//             // Fallback: Check every 500ms for active step changes
//             setInterval(() => {
//                 this.checkCurrentStep();
//             }, 500);
//         }

//         checkInitialState() {
//             // Check the initial state when the page loads
//             setTimeout(() => {
//                 this.checkCurrentStep();
//             }, 100);
//         }

//         handleStepChange(stepElement) {
//             const stepNumber = parseInt($(stepElement).attr('data-step'));
//             this.updateVideoVisibility(stepNumber);
//         }

//         handleStepChangeEvent(stepData) {
//             if (stepData && stepData.step) {
//                 const stepNumber = parseInt(stepData.step);
//                 this.updateVideoVisibility(stepNumber);
//             }
//         }

//         checkCurrentStep() {
//             // Find the currently active step
//             const activeStep = $('.booking-step.active');
            
//             if (activeStep.length > 0) {
//                 const stepNumber = parseInt(activeStep.attr('data-step'));
//                 this.updateVideoVisibility(stepNumber);
//             }
            
//             // Also check for success message (after form submission)
//             this.checkSuccessScreen();
//         }

//         checkSuccessScreen() {
//             // Check if success message is displayed (form has been submitted)
//             const successMessage = $('.success-message');
//             const isSuccessVisible = successMessage.length > 0 && successMessage.is(':visible');
            
//             if (isSuccessVisible) {
//                 this.showVideoForSuccess();
//             }
//         }

//         showVideoForSuccess() {
//             if (!this.videoSection || this.videoSection.length === 0) {
//                 return;
//             }

//             const isCurrentlyVisible = this.videoSection.is(':visible');

//             if (!isCurrentlyVisible) {
//                 // Show video section with smooth animation
//                 this.videoSection.slideDown(300);
//                 console.log('🎬 Video section shown for success screen');
                
//                 // Trigger custom event
//                 $(document).trigger('video:shown', { step: 'success' });
//             }
//         }

//         updateVideoVisibility(stepNumber) {
//             if (!this.videoSection || this.videoSection.length === 0) {
//                 return;
//             }

//             // Check if we should show video based on step number
//             const shouldShowForStep = this.targetSteps.includes(stepNumber);
            
//             // Also check if success screen is visible
//             const successMessage = $('.success-message');
//             const isSuccessVisible = successMessage.length > 0 && successMessage.is(':visible');
            
//             // Show video if it's step 1 OR if success screen is visible
//             const shouldShow = shouldShowForStep || isSuccessVisible;
//             const isCurrentlyVisible = this.videoSection.is(':visible');

//             if (shouldShow && !isCurrentlyVisible) {
//                 // Show video section with smooth animation
//                 this.videoSection.slideDown(300);
                
//                 if (isSuccessVisible) {
//                     console.log('🎬 Video section shown for success screen');
//                     $(document).trigger('video:shown', { step: 'success' });
//                 } else {
//                     console.log(`🎬 Video section shown for step ${stepNumber}`);
//                     $(document).trigger('video:shown', { step: stepNumber });
//                 }
                
//             } else if (!shouldShow && isCurrentlyVisible) {
//                 // Hide video section with smooth animation
//                 this.videoSection.slideUp(300);
//                 console.log(`🎬 Video section hidden for step ${stepNumber}`);
                
//                 // Trigger custom event
//                 $(document).trigger('video:hidden', { step: stepNumber });
//             }
//         }

//         // Public methods for manual control
//         showVideo() {
//             if (this.videoSection && this.videoSection.length > 0) {
//                 this.videoSection.slideDown(300);
//                 console.log('🎬 Video section manually shown');
//             }
//         }

//         hideVideo() {
//             if (this.videoSection && this.videoSection.length > 0) {
//                 this.videoSection.slideUp(300);
//                 console.log('🎬 Video section manually hidden');
//             }
//         }

//         // Method to update target steps if needed
//         setTargetSteps(steps) {
//             this.targetSteps = steps;
//             console.log('🎬 Target steps updated:', this.targetSteps);
//             this.checkCurrentStep(); // Re-check current state
//         }

//         // Method to manually trigger success screen check
//         checkForSuccessScreen() {
//             this.checkSuccessScreen();
//         }
//     }

//     // Initialize the video section controller
//     window.videoController = new VideoSectionController();

//     // Expose controller to global scope for manual control if needed
//     window.VideoSectionController = VideoSectionController;

//     // jQuery plugin for easy access
//     $.fn.videoSectionController = function(action, ...args) {
//         if (action === 'show') {
//             window.videoController.showVideo();
//         } else if (action === 'hide') {
//             window.videoController.hideVideo();
//         } else if (action === 'setSteps') {
//             window.videoController.setTargetSteps(args[0]);
//         } else if (action === 'checkSuccess') {
//             window.videoController.checkForSuccessScreen();
//         }
//         return this;
//     };

// })(jQuery);

// // Alternative standalone implementation (if jQuery is not available)
// if (typeof jQuery === 'undefined') {
//     document.addEventListener('DOMContentLoaded', function() {
//         const videoSection = document.getElementById('video-parent');
//         const targetSteps = [1]; // Only step 1, success screen is detected separately
        
//         if (!videoSection) {
//             console.warn('Video section with ID "video-parent" not found');
//             return;
//         }

//         function checkActiveStep() {
//             const activeStep = document.querySelector('.booking-step.active');
//             if (activeStep) {
//                 const stepNumber = parseInt(activeStep.getAttribute('data-step'));
//                 const shouldShowForStep = targetSteps.includes(stepNumber);
                
//                 // Also check for success screen
//                 const successMessage = document.querySelector('.success-message');
//                 const isSuccessVisible = successMessage && 
//                     (successMessage.style.display !== 'none') && 
//                     (successMessage.offsetParent !== null);
                
//                 const shouldShow = shouldShowForStep || isSuccessVisible;
                
//                 if (shouldShow) {
//                     videoSection.style.display = 'block';
//                     if (isSuccessVisible) {
//                         console.log('🎬 Video section shown for success screen');
//                     } else {
//                         console.log(`🎬 Video section shown for step ${stepNumber}`);
//                     }
//                 } else {
//                     videoSection.style.display = 'none';
//                     console.log(`🎬 Video section hidden for step ${stepNumber}`);
//                 }
//             }
//         }

//         function checkSuccessScreen() {
//             const successMessage = document.querySelector('.success-message');
//             const isSuccessVisible = successMessage && 
//                 (successMessage.style.display !== 'none') && 
//                 (successMessage.offsetParent !== null);
            
//             if (isSuccessVisible) {
//                 videoSection.style.display = 'block';
//                 console.log('🎬 Video section shown for success screen');
//             }
//         }

//         // Initial check
//         setTimeout(checkActiveStep, 100);

//         // Set up periodic checking
//         setInterval(() => {
//             checkActiveStep();
//             checkSuccessScreen();
//         }, 500);

//         // Listen for clicks on navigation buttons
//         document.addEventListener('click', function(event) {
//             if (event.target.matches('.btn-next, .btn-back, .btn-submit')) {
//                 setTimeout(checkActiveStep, 100);
                
//                 // Extra check for success screen after submit
//                 if (event.target.matches('.btn-submit')) {
//                     setTimeout(checkSuccessScreen, 1000);
//                 }
//             }
//         });
//     });
// }
