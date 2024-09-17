whenQuiLoaded().then(() => {
    "use strict";

    setActionUrl();

    function setActionUrl() {

        const ctaUrl = QUIQQER_LANDING_PAGE_CTA_URL;

        if (!ctaUrl) {
            return;
        }

        const actionButtons = document.querySelectorAll('[data-qui-productLandingPage-cta="1"]');

        actionButtons.forEach((Btn) => {
           Btn.href = ctaUrl;
        });
    }
});