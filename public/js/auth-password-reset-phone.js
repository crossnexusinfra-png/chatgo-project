(function() {
    'use strict';

    function syncCountryCode() {
        var countrySelect = document.getElementById('phone_country');
        var countryCodeDisplay = document.getElementById('country-code-display');
        if (!countrySelect || !countryCodeDisplay) {
            return;
        }

        var selectedOption = countrySelect.options[countrySelect.selectedIndex];
        var countryCode = selectedOption && selectedOption.getAttribute('data-country-code');
        countryCodeDisplay.textContent = countryCode || '+81';
    }

    document.addEventListener('DOMContentLoaded', function() {
        var countrySelect = document.getElementById('phone_country');
        if (!countrySelect) {
            return;
        }

        countrySelect.addEventListener('change', syncCountryCode);
        syncCountryCode();
    });
})();
