<div>
    <div id="location-loading" class="hidden text-center p-6 my-8">
        <div class="animate-pulse mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-emerald-600" fill="none"
                viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </div>
        <p class="text-lg font-medium">Iščemo najbližje postaje...</p>
        <p class="text-sm text-gray-600 mt-2">Prosimo, dovolite dostop do lokacije v vašem brskalniku</p>
    </div>

    <div id="location-error" class="hidden text-center p-6 my-8">
        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-red-500" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <p class="text-lg font-medium mt-4" id="location-error-message">Ne moremo pridobiti vaše lokacije</p>
        <p class="text-sm text-gray-600 mt-2">Poskusite poiskati postajo ročno</p>
    </div>

    <script>
        let currentLat = null;
        let currentLon = null;
        let locationRefreshInterval = null;
        const LOCATION_REFRESH_INTERVAL = 3 * 60 * 1000; // 3 minutes in milliseconds
        const LOCATION_DISTANCE_THRESHOLD = 200; // 200 meters, minimum distance to trigger refresh

        // Parse current location from URL if available
        function parseCurrentLocation() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('lat') && urlParams.has('lon')) {
                currentLat = parseFloat(urlParams.get('lat'));
                currentLon = parseFloat(urlParams.get('lon'));
                return true;
            }
            return false;
        }

        // Calculate distance between two coordinates in meters (Haversine formula)
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371e3; // Earth's radius in meters
            const φ1 = lat1 * Math.PI/180; // φ, λ in radians
            const φ2 = lat2 * Math.PI/180;
            const Δφ = (lat2-lat1) * Math.PI/180;
            const Δλ = (lon2-lon1) * Math.PI/180;

            const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                    Math.cos(φ1) * Math.cos(φ2) *
                    Math.sin(Δλ/2) * Math.sin(Δλ/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

            return R * c; // in meters
        }

        function isUserTyping() {
            // Check if any input element or textarea has focus
            const activeElement = document.activeElement;
            return activeElement && (
                activeElement.tagName === 'INPUT' ||
                activeElement.tagName === 'TEXTAREA' ||
                activeElement.isContentEditable
            );
        }

        function checkLocationChange() {
            if (isUserTyping()) {
                return; // Don't check location if user is typing
            }

            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const newLat = position.coords.latitude;
                        const newLon = position.coords.longitude;

                        // If we have previous coordinates, check if location has changed significantly
                        if (currentLat !== null && currentLon !== null) {
                            const distance = calculateDistance(currentLat, currentLon, newLat, newLon);
                            // If location has changed by more than threshold and user isn't typing, redirect
                            if (distance > LOCATION_DISTANCE_THRESHOLD && !isUserTyping()) {
                                // Add a cookie to indicate this is an auto-reload
                                document.cookie = "autoreload=1; path=/; max-age=5";

                                // Redirect to new location
                                window.location.href = `/geosearch?lat=${newLat}&lon=${newLon}`;
                            }
                        }
                    },
                    function(error) {
                        console.error("Error checking location change:", error);
                    },
                    {
                        timeout: 10000,
                        maximumAge: 60000 // Use cached position if less than 1 minute old
                    }
                );
            }
        }

        function requestLocation() {
            // Don't proceed if user is typing
            if (isUserTyping()) {
                return;
            }

            const loadingEl = document.getElementById('location-loading');
            const errorEl = document.getElementById('location-error');
            const errorMsgEl = document.getElementById('location-error-message');

            // Hide any previous error message
            errorEl.classList.add('hidden');

            if ("geolocation" in navigator) {
                // Show loading indicator
                loadingEl.classList.remove('hidden');

                // Set a timeout to show error message if it takes too long
                const timeoutId = setTimeout(() => {
                    loadingEl.classList.add('hidden');
                    errorEl.classList.remove('hidden');
                    errorMsgEl.textContent = "Iskanje lokacije je poteklo. Poskusite poiskati postajo ročno.";

                    // If we're on the homepage, redirect to the search page to show some stations
                    if (window.location.pathname === '/' && !isUserTyping()) {
                        window.location.href = '/search?locationFailed=true';
                    }
                }, 10000); // 10 second timeout

                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        clearTimeout(timeoutId);
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;

                        // Check again before redirect in case user started typing
                        if (!isUserTyping()) {
                            if (window.location.pathname === '/') {
                                window.location.href = `/geosearch?lat=${lat}&lon=${lon}`;
                            } else {
                                const currentParams = new URLSearchParams(window.location.search);
                                currentParams.set('lat', lat);
                                currentParams.set('lon', lon);
                                window.location.href = `/geosearch?${currentParams.toString()}`;
                            }
                        }
                    },
                    function(error) {
                        clearTimeout(timeoutId);
                        loadingEl.classList.add('hidden');
                        errorEl.classList.remove('hidden');

                        let errorMessage = '';
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage = 'Dostop do lokacije je bil zavrnjen. Omogočite dostop v nastavitvah brskalnika.';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage = 'Podatki o lokaciji niso na voljo.';
                                break;
                            case error.TIMEOUT:
                                errorMessage = 'Zahteva za lokacijo je potekla.';
                                break;
                            default:
                                errorMessage = 'Prišlo je do neznane napake.';
                        }
                        errorMsgEl.textContent = errorMessage;
                        console.error(errorMessage);

                        // If we're on the homepage, redirect to the search page to show some stations
                        if (window.location.pathname === '/' && !isUserTyping()) {
                            window.location.href = '/search?locationFailed=true';
                        }
                    },
                    {
                        timeout: 10000, // 10 second timeout
                        maximumAge: 300000 // Cache location for 5 minutes
                    }
                );
            } else {
                errorEl.classList.remove('hidden');
                errorMsgEl.textContent = 'Vaš brskalnik ne podpira geolokacije.';

                // If we're on the homepage, redirect to the search page
                if (window.location.pathname === '/' && !isUserTyping()) {
                    window.location.href = '/search?locationFailed=true';
                }
            }
        }

        // Handle page visibility changes
        function handleVisibilityChange() {
            // If page becomes visible and we're on the geosearch page, check location
            if (document.visibilityState === 'visible' && window.location.pathname === '/geosearch') {
                checkLocationChange();
            }
        }

        // Setup periodic location check on geosearch page
        function setupLocationRefresh() {
            if (window.location.pathname === '/geosearch') {
                // Parse the current location from URL
                if (parseCurrentLocation()) {
                    // Set up interval to check location changes
                    locationRefreshInterval = setInterval(checkLocationChange, LOCATION_REFRESH_INTERVAL);

                    // Also check when visibility changes (user returns to tab)
                    document.addEventListener('visibilitychange', handleVisibilityChange);
                }
            }
        }

        // Request location automatically when the component loads
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('lat') && !urlParams.has('lon')) {
                // Check if we're on the homepage and lookingForLocation is true
                if (window.location.pathname === '/' && {{ isset($lookingForLocation) && $lookingForLocation ? 'true' : 'false' }}) {
                    // Show the loading indicator immediately
                    document.getElementById('location-loading').classList.remove('hidden');
                }
                requestLocation();
            } else {
                // If we're on a page with location parameters, set up location refresh
                setupLocationRefresh();
            }
        });

        // Clean up when page unloads
        window.addEventListener('beforeunload', function() {
            if (locationRefreshInterval) {
                clearInterval(locationRefreshInterval);
            }
            document.removeEventListener('visibilitychange', handleVisibilityChange);
        });
    </script>
</div>