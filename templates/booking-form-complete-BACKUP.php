<?php
// BACKUP COPY - Original booking form template with all steps
// Created on: July 25, 2025
// This is a backup before restructuring Step 9 layout

// Complete booking form template with all steps
?>
<div id="booking-form" class="booking-system-form">
    <!-- Step 1: Service Selection -->
    <div class="booking-step active" data-step="1">
        <section class="rs-step-1">
            <div class="step-overlay"></div>
            <div class="step-container">
                <div class="step-content">
                    <div class="left-column">
                        <h2 class="main-title">Find & Book<br>Free Estimates</h2>
                        <p class="sub-text">With Top Local Contractors</p>
                    </div>
                    <div class="right-column">
                        <div class="service-card">
                            <h3 class="card-title">GET FREE<br>ESTIMATES</h3>
                            <div class="service-options">
                                <button class="service-option" data-service="Roof">ROOF</button>
                                <button class="service-option" data-service="Windows">WINDOWS</button>
                                <button class="service-option" data-service="Bathroom">BATH</button>
                                <button class="service-option" data-service="Siding">SIDING</button>
                                <button class="service-option" data-service="Kitchen">KITCHEN</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Step 2: Service Specific Questions (Dynamic) OR ZIP Code -->
    <div class="booking-step" data-step="2">
        <div class="step-card">
            <h2 class="step-title" id="step2-title">Service Details</h2>
            <!-- Choice options (for service questions) -->
            <div class="option-grid" id="step2-options">
                <!-- Options populated by JavaScript -->
            </div>
            <!-- Text input (for ZIP codes) -->
            <div class="form-group" id="step2-text-input" style="display: none;">
                <label class="form-label" id="step2-label">Enter your ZIP code</label>
                <input type="text" class="form-input" id="zip-input" placeholder="Enter ZIP code (e.g., 12345)" maxlength="10">
            </div>
            <div class="form-navigation">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-next" disabled>Next</button>
            </div>
        </div>
    </div>

    <!-- Step 3: Personal Details Form -->
    <div class="booking-step" data-step="3">
        <div class="step-card">
            <h2 class="step-title">Your Information</h2>
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" class="form-input" id="full-name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" class="form-input" id="email" required>
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number *</label>
                <input type="tel" class="form-input" id="phone" required>
            </div>
            <div class="form-group">
                <label class="form-label">Service Address *</label>
                <input type="text" class="form-input" id="address" required>
            </div>
            <div class="form-navigation">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-next" disabled>Next</button>
            </div>
        </div>
    </div>

    <!-- Step 4-8: Other steps (abbreviated for backup) -->
    <!-- ... -->

    <!-- ORIGINAL Step 9: Summary/Confirmation (BEFORE CHANGES) -->
    <div class="booking-step" data-step="9">
        <div class="step-card">
            <div class="summary-container">
                <div class="summary-left">
                    <div class="schedule-summary-container">
                        <div class="schedule-summary-inner">
                            <h3 class="schedule-summary-title" id="service-schedule-title">Service Estimate Schedule</h3>
                            <div id="schedule-items">
                                <!-- Schedule items populated by JavaScript -->
                            </div>
                            <div class="done-booking-link">
                                I'm done booking estimates
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="summary-right">
                    <div class="summary-card">
                        <div class="summary-card-inner">
                            <h3 class="summary-title">Project Details</h3>
                            <div id="summary-details">
                                <!-- Summary details populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="next-steps">
                        <div class="next-steps-inner">
                            <h3 class="next-steps-title">
                                <svg class="next-steps-title-icon" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                                </svg>
                                <span>Next Steps:<br>Phone Call</span>
                            </h3>
                            <ul class="next-steps-list">
                                <li>Their service team will need a call to confirm this slot</li>
                                <li>Any questions you have will be answered on that phone call</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-navigation">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-submit">Confirm Booking</button>
            </div>
        </div>
    </div>
    
    <!-- Progress Bar -->
    <div class="progress-bar">
        <div class="progress-fill" style="width: 14%"></div>
    </div>
</div>
