/* Forms with both a region and a country field: the country list follows the region. */
(function () {
    'use strict';

    const regions = window.cluRegions || {};

    document.addEventListener('DOMContentLoaded', () => {
        const region  = document.getElementById('clu_region');
        const country = document.getElementById('clu_country');

        if (!region || !country) {
            return;
        }

        const placeholder = country.options[0] ? country.options[0].text : '';

        region.addEventListener('change', () => {
            const list = regions[region.value] || [];

            country.length = 0;
            country.add(new Option(placeholder, ''));
            list.forEach((name) => country.add(new Option(name, name)));
            country.disabled = list.length === 0;
        });
    });
}());
