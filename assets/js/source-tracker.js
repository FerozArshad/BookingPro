(function() {
    'use strict';

    function log(message, data) {
        console.log(`BookingPro Source Tracker: ${message}`, data || '');
    }

    log('Executing...');

    const utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'];
    const sessionKey = 'bsp_marketing_params';

    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            let date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = `bsp_${name}=${value || ""}${expires}; path=/; SameSite=Lax`;
    }

    const currentUrlParams = new URLSearchParams(window.location.search);
    let capturedSomethingNew = false;
    const paramsToStore = {};

    utmParams.forEach(param => {
        if (currentUrlParams.has(param)) {
            const value = currentUrlParams.get(param);
            paramsToStore[param] = value;
            capturedSomethingNew = true;
        }
    });

    if (capturedSomethingNew) {
        sessionStorage.setItem(sessionKey, JSON.stringify(paramsToStore));
        log('New marketing params found and SAVED to session storage.', paramsToStore);
    } else {
        log('No new marketing params found in the current URL.');
    }

    const storedParamsRaw = sessionStorage.getItem(sessionKey);
    if (storedParamsRaw) {
        log('Found stored params in session storage. Setting cookies now.', storedParamsRaw);
        const storedParams = JSON.parse(storedParamsRaw);
        
        Object.keys(storedParams).forEach(key => {
            setCookie(key, storedParams[key], 30);
        });

        if (document.referrer && !document.referrer.includes(window.location.hostname)) {
             setCookie('referrer', document.referrer, 30);
        }

    } else {
        log('No marketing params found. Setting source as "direct".');
        setCookie('utm_source', 'direct', 30);
    }

    log('Final cookies after processing:', document.cookie);
})();
