<?php
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
            </div>
            <!-- Text input (for ZIP codes) -->
            <div class="form-group" id="step2-text-input" style="display: none;">
                <label class="form-label" id="step2-label">Enter your ZIP code</label>
                <input type="text" class="form-input" id="step2-zip-input" placeholder="Enter ZIP code (e.g., 12345)" maxlength="10">
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
                <input type="text" class="form-input" id="step4-zip-input" placeholder="Enter ZIP code (e.g., 12345)" maxlength="10">
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
                <input type="text" class="form-input" id="name-input" placeholder="Enter your full name">
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
                <input type="text" class="form-input" id="address-input" placeholder="Enter your street address">
            </div>
            <div class="form-navigation">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-next" disabled>Next</button>
            </div>
        </div>
    </div>

    <!-- Step 7: Contact Information -->
    <div class="booking-step" data-step="7">
        <div class="step-card">
            <h2 class="step-title">We have matching Pros in <span id="city-name">[City]</span></h2>
            <div class="form-group">
                <label class="form-label">Cell Number</label>
                <input type="tel" class="form-input" id="phone-input" placeholder="(555) 123-4567">
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-input" id="email-input" placeholder="your@email.com">
            </div>
            <div class="form-navigation">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-next" disabled>Next</button>
            </div>
        </div>
    </div>

    <!-- Step 8: Schedule Appointments -->
    <div class="booking-step" data-step="8">
        <div class="step-card">
            <h2 class="step-title">Select a date and time</h2>
            <p class="step-instructions" style="text-align: center; margin-bottom: 20px; color: rgba(255,255,255,0.9); font-size: 14px;">
                Choose your preferred dates and times from the companies below. You can select up to 3 appointments.
            </p>
            <div class="calendar-container">
                <?php
                // Get companies from database for dynamic rendering
                $db = BSP_Database_Unified::get_instance();
                $companies = $db->get_companies();
                
                $company_index = 0;
                foreach ($companies as $company) {
                    // Company-specific badges and details
                    $badges = [
                        'BEST PRICES GUARANTEED',
                        'MOST RECOMMENDED BY CUSTOMERS', 
                        'FEATURED PRO BY GOOGLE'
                    ];
                    
                    $company_names = [
                        'RH Remodeling',
                        'Eco Green',
                        'Top Remodeling Pro'
                    ];
                    
                    $badge_text = isset($badges[$company_index]) ? $badges[$company_index] : 'FEATURED PRO BY GOOGLE';
                    $display_name = isset($company_names[$company_index]) ? $company_names[$company_index] : $company->name;
                    
                    echo '<div class="company-card" data-company="' . esc_attr($display_name) . '" data-company-id="' . esc_attr($company->id) . '">';
                    
                    // Web icon mapping for each company
                    $web_icons = [
                        'web-icons-01.png', // RH Remodeling
                        'web-icons-02.png', // Eco Green
                        'web-icons-03.png'  // Top Remodeling Pro
                    ];
                    $web_icon = isset($web_icons[$company_index]) ? $web_icons[$company_index] : 'web-icons-01.png';
                    $icon_url = plugins_url('BookingPro/assets/images/' . $web_icon);
                    
                    // Featured Pro Badge with icon
                    echo '<div class="featured-badge">';
                    echo '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($badge_text) . '" class="badge-icon">';
                    echo '<span class="badge-text">' . esc_html($badge_text) . '</span>';
                    echo '</div>';
                    
                    // Company Header
                    echo '<div class="company-header-new">';
                    echo '<div class="company-title">';
                    echo '<span class="checkmark-icon">✓</span>';
                    echo '<span class="company-name-text">' . esc_html($display_name) . '</span>';
                    echo '</div>';
                    echo '<div class="company-service">Remodels bathrooms in <strong>[City, State]</strong></div>';
                    echo '</div>';
                    
                    // Date Selection Section
                    echo '<div class="date-selection-section">';
                    echo '<div class="section-header">';
                    echo '<span class="section-title">Choose Your Date</span>';
                    echo '<span class="more-dates-link">more dates ></span>';
                    echo '</div>';
                    echo '<div class="calendar-grid" data-company="' . esc_attr($display_name) . '" data-company-id="' . esc_attr($company->id) . '">';
                    echo '<!-- Calendar days populated by JavaScript -->';
                    echo '</div>';
                    echo '</div>';
                    
                    // Time Selection Section
                    echo '<div class="time-selection-section">';
                    echo '<div class="section-title">Choose Your Time</div>';
                    echo '<div class="time-slots" data-company="' . esc_attr($display_name) . '" data-company-id="' . esc_attr($company->id) . '">';
                    echo '<!-- Time slots populated by JavaScript -->';
                    echo '</div>';
                    echo '</div>';
                    
                    // Footer Disclaimer
                    echo '<div class="booking-disclaimer">';
                    echo 'By clicking "Request Estimate", I am providing my electronic signature and expressed written consent to permit ';
                    echo '<strong>' . esc_html($display_name) . '</strong> and parties calling on their behalf to contact me at [client phone number] for marketing purposes, ';
                    echo 'including through the use of automated technology and text messages. I acknowledge my consent is not required ';
                    echo 'to obtain any good or service.';
                    echo '</div>';
                    
                    // Request Estimate Button
                    echo '<div class="estimate-button-container">';
                    echo '<button class="btn-request-estimate" data-company="' . esc_attr($display_name) . '" data-company-id="' . esc_attr($company->id) . '">REQUEST ESTIMATE</button>';
                    echo '</div>';
                    
                    echo '</div>';
                    
                    $company_index++;
                }
                ?>
            </div>
            <div class="form-navigation" style="display: none;">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-next" disabled>Next</button>
            </div>
        </div>
    </div>

    <!-- Step 9: Summary/Confirmation -->
    <div class="booking-step" data-step="9">
        <div class="step-card">
            <div class="form-navigation" style="margin-bottom: 20px;">
                <button class="btn btn-secondary btn-back">Back</button>
                <button class="btn btn-primary btn-submit">Confirm Booking</button>
            </div>
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

        </div>
    </div>
    
    <!-- Progress Bar -->
    <div class="progress-bar">
        <div class="progress-fill" style="width: 14%"></div>
    </div>
</div>
