/**
 * Booking System Pro - Source Tracker
 *
 * This script captures marketing source data (UTM parameters) from the URL,
 * stores it in cookies, and injects it into the booking form.
 */
document.addEventListener('DOMContentLoaded', function() {

    /**
     * Retrieves a URL parameter by its name.
     * @param {string} name The name of the parameter to retrieve.
     * @returns {string|null} The value of the parameter or null if not found.
     */
    function getUrlParameter(name) {
        name = name.replace(/[\\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    /**
     * Sets a cookie with a given name, value, and expiration in days.
     * @param {string} name The name of the cookie.
     * @param {string} value The value of the cookie.
     * @param {int} days The number of days until the cookie expires.
     */
    function setCookie(name, value, days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
    }

    /**
     * Gets a cookie by its name.
     * @param {string} name The name of the cookie.
     * @returns {string|null} The value of the cookie or null if not found.
     */
    function getCookie(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    // --- Main Logic ---

    const utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'referrer'];
    let sourceDataCaptured = false;

    // 1. Capture UTM parameters from URL and store in cookies if they exist in the URL
    utmParams.forEach(function(param) {
        if (param === 'referrer') return; // Skip referrer for now
        const value = getUrlParameter(param);
        if (value) {
            setCookie('bsp_' + param, value, 30); // Store for 30 days
            sourceDataCaptured = true;
        }
    });

    // Capture referrer separately
    if (document.referrer) {
        setCookie('bsp_referrer', document.referrer, 30);
    }

    // 2. If no UTMs in URL, try to determine source from referrer, but only if no source cookie exists
    if (!sourceDataCaptured && document.referrer && !getCookie('bsp_utm_source')) {
        const referrer = new URL(document.referrer);
        const knownSources = {
            'google.com': { source: 'google', medium: 'organic' },
            'bing.com': { source: 'bing', medium: 'organic' },
            'facebook.com': { source: 'facebook', medium: 'social' },
            'instagram.com': { source: 'instagram', medium: 'social' },
            'twitter.com': { source: 'twitter', medium: 'social' },
            'linkedin.com': { source: 'linkedin', medium: 'social' }
        };

        for (const domain in knownSources) {
            if (referrer.hostname.includes(domain)) {
                setCookie('bsp_utm_source', knownSources[domain].source, 30);
                setCookie('bsp_utm_medium', knownSources[domain].medium, 30);
                sourceDataCaptured = true;
                break;
            }
        }
    }
    
    // 3. If still no source, and no cookie exists, mark as 'direct'
    if (!getCookie('bsp_utm_source')) {
        setCookie('bsp_utm_source', 'direct', 30);
        setCookie('bsp_utm_medium', 'none', 30);
    }

    /**
     * Sets values of existing hidden UTM/source fields in the booking form.
     */
    function setHiddenFieldValues() {
        const bookingForm = document.querySelector('.booking-system-form');
        if (!bookingForm) {
            return; // Exit if form not found
        }
        const fieldsForLog = {};
        utmParams.forEach(function(param) {
            const cookieName = 'bsp_' + param;
            const cookieValue = getCookie(cookieName);
            const input = bookingForm.querySelector(`input[name="${param}"]`);
            if (input) {
                input.value = cookieValue !== null ? cookieValue : '';
                fieldsForLog[param] = input.value;
            }
        });
        console.log('BookingPro Source Tracker: Set hidden field values', fieldsForLog);
    }

    // Use a mutation observer to handle forms loaded via AJAX.
    const observer = new MutationObserver(function(mutations, obs) {
        if (document.querySelector('.booking-system-form')) {
            setHiddenFieldValues();
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Also run once on load in case the form is already present.
    setHiddenFieldValues();

    // Ensure hidden fields are set right before form submission
    document.addEventListener('submit', function(e) {
        if (e.target && e.target.classList.contains('booking-system-form')) {
            setHiddenFieldValues();
            // Log all hidden fields in the form
            const hiddenInputs = e.target.querySelectorAll('input[type="hidden"]');
            const logObj = {};
            hiddenInputs.forEach(function(input) {
                logObj[input.name] = input.value;
            });
            console.log('BookingPro Source Tracker: Hidden fields on submit', logObj);
        }
    }, true);
});
