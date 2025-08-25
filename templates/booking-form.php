<?php
// Booking form template
?>
<form id="booking-form" class="booking-system-form" method="post" autocomplete="off">
    <!-- UTM & Source Tracking Fields (Injected by JS) -->
    <input type="hidden" name="service" id="service-field" value="">
    <input type="hidden" name="company" id="company-field" value="">
    <input type="hidden" name="utm_source" id="utm_source" value="">
    <input type="hidden" name="utm_medium" id="utm_medium" value="">
    <input type="hidden" name="utm_campaign" id="utm_campaign" value="">
    <input type="hidden" name="utm_term" id="utm_term" value="">
    <input type="hidden" name="utm_content" id="utm_content" value="">
    <input type="hidden" name="gclid" id="gclid" value="">
    <input type="hidden" name="referrer" id="referrer" value="">
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
                                <button class="service-option" data-service="Roof">
                                    <div class="service-icon"></div>
                                    <span class="service-name">ROOFING</span>
                                </button>
                                <button class="service-option" data-service="Kitchen">
                                    <div class="service-icon"></div>
                                    <span class="service-name">KITCHEN</span>
                                </button>
                                <button class="service-option" data-service="Windows">
                                    <div class="service-icon"></div>
                                    <span class="service-name">WINDOWS</span>
                                </button>
                                <button class="service-option" data-service="ADU">
                                    <div class="service-icon"></div>
                                    <span class="service-name">ADU</span>
                                </button>
                                <button class="service-option" data-service="Bathroom">
                                    <div class="service-icon"></div>
                                    <span class="service-name">BATHROOM</span>
                                </button>
                                <button class="service-option" data-service="Siding">
                                    <div class="service-icon"></div>
                                    <span class="service-name">SIDING</span>
                                </button>
                                <button class="service-option" data-service="Decks">
                                    <div class="service-icon"></div>
                                    <span class="service-name">DECKS</span>
                                </button>
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
                <!-- Service-specific hidden fields for JS to populate -->
                <input type="hidden" name="roof_zip" id="roof_zip">
                <input type="hidden" name="windows_zip" id="windows_zip">
                <input type="hidden" name="bathroom_zip" id="bathroom_zip">
                <input type="hidden" name="siding_zip" id="siding_zip">
                <input type="hidden" name="kitchen_zip" id="kitchen_zip">
                <input type="hidden" name="decks_zip" id="decks_zip">
                <input type="hidden" name="adu_zip" id="adu_zip">
                <input type="hidden" name="roof_action" id="roof_action">
                <input type="hidden" name="roof_material" id="roof_material">
                <input type="hidden" name="windows_action" id="windows_action">
                <input type="hidden" name="windows_replace_qty" id="windows_replace_qty">
                <input type="hidden" name="windows_repair_needed" id="windows_repair_needed">
                <input type="hidden" name="bathroom_option" id="bathroom_option">
                <input type="hidden" name="siding_option" id="siding_option">
                <input type="hidden" name="siding_material" id="siding_material">
                <input type="hidden" name="kitchen_action" id="kitchen_action">
                <input type="hidden" name="kitchen_component" id="kitchen_component">
                <input type="hidden" name="decks_action" id="decks_action">
                <input type="hidden" name="decks_material" id="decks_material">
                <input type="hidden" name="adu_action" id="adu_action">
                <input type="hidden" name="adu_type" id="adu_type">
                <input type="hidden" name="service_details" id="service_details">
                <input type="hidden" name="appointments" id="appointments">
                <!-- City/State auto-populated from ZIP -->
                <input type="hidden" name="city" id="city">
                <input type="hidden" name="state" id="state">
            </div>
            <!-- Text input (for ZIP codes) -->
            <div class="form-group" id="step2-text-input" style="display: none;">
                <label class="form-label" id="step2-label">Enter your ZIP code</label>
                <input type="text" class="form-input" id="step2-zip-input" name="zip_code" placeholder="Enter ZIP code (e.g., 12345)" maxlength="10" required>
            </div>
            <div class="form-navigation">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-next" disabled>Next</button>
            </div>
        </div>
    </div>

    <!-- Step 3: Additional Service Questions (Dynamic) -->
    <div class="booking-step" data-step="3">
        <div class="step-card">
            <h2 class="step-title" id="step3-title">Additional Details</h2>
            <div class="option-grid" id="step3-options">
                <!-- Options populated by JavaScript -->
            </div>
            <div class="form-navigation">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-next" disabled>Next</button>
            </div>
        </div>
    </div>

    <!-- Step 4: ZIP Code -->
    <div class="booking-step" data-step="4">
        <div class="step-card">
            <h2 class="step-title">Enter your ZIP code</h2>
            <div class="form-group">
                <input type="text" class="form-input" id="step4-zip-input" name="zip_code" placeholder="Enter ZIP code (e.g., 12345)" maxlength="10" required>
            </div>
            <div class="form-navigation">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-next" disabled>Next</button>
            </div>
        </div>
    </div>

    <!-- Step 5: Full Name -->
    <div class="booking-step" data-step="5">
        <div class="step-card">
            <h2 class="step-title">Please enter your full name</h2>
            <div class="form-group">
                <input type="text" class="form-input" id="name-input" name="full_name" placeholder="Enter your full name" required>
            </div>
            <div class="form-navigation">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-next" disabled>Next</button>
            </div>
        </div>
    </div>

    <!-- Step 6: Address -->
    <div class="booking-step" data-step="6">
        <div class="step-card">
            <h2 class="step-title">What is your street address?</h2>
            <div class="form-group">
                <input type="text" class="form-input" id="address-input" name="address" placeholder="Enter your street address" required>
            </div>
            <div class="form-navigation">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-next" disabled>Next</button>
            </div>
        </div>
    </div>

    <!-- Step 7: Contact Info -->
    <div class="booking-step" data-step="7">
        <div class="step-card">
            <h2 class="step-title">We have matching Pros in <span id="city-name">[City]</span></h2>
            <div class="form-group">
                <label class="form-label">Cell Number</label>
                <input type="tel" class="form-input" id="phone-input" name="phone" placeholder="Enter your phone number" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-input" id="email-input" name="email" placeholder="Enter your email address" required>
            </div>
            <div class="form-navigation">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-next" disabled>Next</button>
            </div>
        </div>
    </div>

    <!-- Step 8: Date & Time Selection -->
    <div class="booking-step" data-step="8">
        <div class="step-card">
            <h2 class="step-title">Select dates and times (you can choose up to 3 companies)</h2>
            <div class="company-grid">
                <div class="company-card" data-company="Top Remodeling Pro" data-company-id="1">
                    <div class="company-header">
                        <div class="company-name">Top Remodeling Pro</div>
                        <div class="company-rating">★★★★★ MOST RECOMMENDED BY CUSTOMERS</div>
                        <div class="company-phone">(555) 123-4567</div>
                    </div>
                    <div class="calendar-container">
                        <div class="calendar-section">
                            <h4>Choose a date</h4>
                            <div class="calendar-grid" data-company="Top Remodeling Pro" data-company-id="1">
                                <!-- Calendar days populated by JavaScript -->
                            </div>
                        </div>
                        <div class="time-section">
                            <h4>Choose a time</h4>
                            <div class="time-slots" data-company="Top Remodeling Pro" data-company-id="1">
                                <!-- Time slots populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="company-card" data-company="RH Remodeling" data-company-id="2">
                    <div class="company-header">
                        <div class="company-name">RH Remodeling</div>
                        <div class="company-rating">★★★★★ PREVIOUS GUARANTEED</div>
                        <div class="company-phone">(555) 234-5678</div>
                    </div>
                    <div class="calendar-container">
                        <div class="calendar-section">
                            <h4>Choose a date</h4>
                            <div class="calendar-grid" data-company="RH Remodeling" data-company-id="2">
                                <!-- Calendar days populated by JavaScript -->
                            </div>
                        </div>
                        <div class="time-section">
                            <h4>Choose a time</h4>
                            <div class="time-slots" data-company="RH Remodeling" data-company-id="2">
                                <!-- Time slots populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="company-card" data-company="Eco Green" data-company-id="3">
                    <div class="company-header">
                        <div class="company-name">Eco Green</div>
                        <div class="company-rating">★★★★★ MOST RECOMMENDED BY CUSTOMERS</div>
                        <div class="company-phone">(555) 345-6789</div>
                    </div>
                    <div class="calendar-container">
                        <div class="calendar-section">
                            <h4>Choose a date</h4>
                            <div class="calendar-grid" data-company="Eco Green" data-company-id="3">
                                <!-- Calendar days populated by JavaScript -->
                            </div>
                        </div>
                        <div class="time-section">
                            <h4>Choose a time</h4>
                            <div class="time-slots" data-company="Eco Green" data-company-id="3">
                                <!-- Time slots populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-navigation">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-next" disabled>Next</button>
            </div>
        </div>
    </div>

    <!-- Step 9: Summary & Confirmation -->
    <div class="booking-step" data-step="9">
        <div class="step-card">
            <h2 class="step-title">Please review & confirm your booking</h2>
            <div class="summary-container">
                <div class="summary-left">
                    <h3 class="schedule-summary-title" id="service-schedule-title">Service Estimate Schedule</h3>
                    <div id="schedule-items">
                        <!-- Schedule items populated by JavaScript -->
                    </div>
                    <p style="text-align: center; margin-top: 20px; font-size: 14px; opacity: 0.9; color: #fff;">
                        I'm done booking estimates
                    </p>
                </div>
                
                <div class="summary-right">
                    <div class="summary-card">
                        <h3 class="summary-title">Project Details</h3>
                        <div id="summary-details">
                            <!-- Summary details populated by JavaScript -->
                        </div>
                    </div>
                    
                    <div class="next-steps">
                        <h3 class="next-steps-title">📞 Next Steps: Phone Call</h3>
                        <ul class="next-steps-list">
                            <li>Their service team will need a call to confirm this slot</li>
                            <li>Any questions you have will be answered on that phone call</li>
                        </ul>
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
</form>