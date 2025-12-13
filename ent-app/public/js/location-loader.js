// Lightweight location selector loader
// Exposes initLocationSelectors(apiBaseUrl)
(function(window){
    function el(id){ return document.getElementById(id); }

    function setOptions(selectEl, items, placeholder, selectedValue) {
        if (!selectEl) return;
        selectEl.innerHTML = '';
        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = placeholder || '-- Select --';
        selectEl.appendChild(placeholderOption);
        (items || []).forEach(function(it){
            const o = document.createElement('option');
            o.value = it;
            o.textContent = it;
            if (selectedValue && String(it).toLowerCase() === String(selectedValue).toLowerCase()) {
                o.selected = true;
                // Also clear placeholder selection
                placeholderOption.selected = false;
            }
            selectEl.appendChild(o);
        });
        // If selectedValue provided but not matched, try to set select.value (may match casing)
        if (selectedValue && selectEl.value === '') {
            try { selectEl.value = selectedValue; } catch (e) {}
        }
    }

    function fetchJson(url) {
        return fetch(url, {cache: 'no-cache'}).then(function(r){
            if (!r.ok) throw new Error('Network error');
            return r.json();
        });
    }

    function populateCountries(apiBase) {
        const profileCountry = el('countrySelect');
        const visitCountry = el('visitCountrySelect');
        const otherCountry = el('countrySelect');
        return fetchJson(apiBase + '?action=countries').then(function(data){
            const countries = (data && data.countries) ? data.countries : [];
            if (profileCountry) setOptions(profileCountry, countries, '-- Select Country --', profileCountry.getAttribute('data-selected') || '');
            if (visitCountry) setOptions(visitCountry, countries, '-- Select Country --', visitCountry.getAttribute('data-selected') || '');
            return countries;
        });
    }

    function populateStatesFor(selectCountryEl, stateSelectEl, apiBase) {
        if (!selectCountryEl || !stateSelectEl) return Promise.resolve();
        const country = selectCountryEl.value || selectCountryEl.getAttribute('data-selected') || '';
        if (!country) { setOptions(stateSelectEl, [], '-- Select State/Province --'); return Promise.resolve(); }
        const url = apiBase + '?action=states&country=' + encodeURIComponent(country);
        return fetchJson(url).then(function(data){
            const states = (data && data.states) ? data.states : [];
            const selectedState = stateSelectEl.getAttribute('data-selected') || '';
            setOptions(stateSelectEl, states, '-- Select State/Province --', selectedState);
        }).catch(function(){ setOptions(stateSelectEl, [], '-- Select State/Province --'); });
    }

    function populateCitiesFor(country, state, citySelectEl, apiBase) {
        if (!citySelectEl) return Promise.resolve();
        if (!country) { setOptions(citySelectEl, [], '-- Select City --'); return Promise.resolve(); }
        const url = apiBase + '?action=cities&country=' + encodeURIComponent(country) + (state ? '&state=' + encodeURIComponent(state) : '');
        return fetchJson(url).then(function(data){
            const cities = (data && data.cities) ? data.cities : [];
            const selectedCity = citySelectEl.getAttribute('data-selected') || '';
            setOptions(citySelectEl, cities, '-- Select City --', selectedCity);
        }).catch(function(){ setOptions(citySelectEl, [], '-- Select City --'); });
    }

    function wireSelectors(apiBase) {
        // Profile selectors
        const profileCountry = el('countrySelect');
        const profileState = el('stateSelect');
        const profileCity = el('citySelect');

        // Visit selectors
        const visitCountry = el('visitCountrySelect');
        const visitState = el('visitStateSelect');
        const visitCity = el('visitCitySelect');

        // populate countries then states/cities if data-selected set
        populateCountries(apiBase).then(function(){
            // profile
            if (profileCountry) {
                populateStatesFor(profileCountry, profileState, apiBase).then(function(){
                    const selState = profileState && profileState.getAttribute('data-selected') ? profileState.getAttribute('data-selected') : '';
                    if (profileCity) populateCitiesFor(profileCountry.value || profileCountry.getAttribute('data-selected') || '', selState, profileCity, apiBase);
                });
                profileCountry.addEventListener('change', function(){
                    populateStatesFor(profileCountry, profileState, apiBase).then(function(){
                        if (profileState) populateCitiesFor(profileCountry.value, profileState.value, profileCity, apiBase);
                    });
                });
                // When the profile state changes (user selects state), populate cities for that state
                if (profileState) {
                    profileState.addEventListener('change', function(){
                        populateCitiesFor(profileCountry ? (profileCountry.value || profileCountry.getAttribute('data-selected') || '') : '', profileState.value, profileCity, apiBase);
                    });
                }
            }

            // visit
            if (visitCountry) {
                visitCountry.addEventListener('change', function(){
                    populateStatesFor(visitCountry, visitState, apiBase).then(function(){
                        if (visitState) populateCitiesFor(visitCountry.value, visitState.value, visitCity, apiBase);
                    });
                });
            }
            if (visitState) {
                visitState.addEventListener('change', function(){
                    populateCitiesFor(visitCountry ? visitCountry.value : '', visitState.value, visitCity, apiBase);
                });
            }

            // If visitCountry has pre-selected value, trigger population
            if (visitCountry && (visitCountry.getAttribute('data-selected') || visitCountry.value)) {
                populateStatesFor(visitCountry, visitState, apiBase).then(function(){
                    const sel = visitState && visitState.getAttribute('data-selected') ? visitState.getAttribute('data-selected') : '';
                    populateCitiesFor(visitCountry.value || visitCountry.getAttribute('data-selected') || '', sel, visitCity, apiBase);
                });
            }
        }).catch(function(e){ console.warn('Failed to load countries', e); });
    }

    // public
    window.initLocationSelectors = function(apiBaseUrl) {
        if (!apiBaseUrl) apiBaseUrl = '/api-locations.php';
        wireSelectors(apiBaseUrl);
    };

})(window);
