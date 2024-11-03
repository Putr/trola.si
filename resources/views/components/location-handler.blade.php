<div>
    <div id="location-loading" class="hidden text-center">
        <p>Pridobivanje lokacije...</p>
    </div>

    <script>
        function isUserTyping() {
            // Check if any input element or textarea has focus
            const activeElement = document.activeElement;
            return activeElement && (
                activeElement.tagName === 'INPUT' ||
                activeElement.tagName === 'TEXTAREA' ||
                activeElement.isContentEditable
            );
        }

        function requestLocation() {
            // Don't proceed if user is typing
            if (isUserTyping()) {
                return;
            }

            const loadingEl = document.getElementById('location-loading');

            if ("geolocation" in navigator) {
                // Show loading indicator
                loadingEl.classList.remove('hidden');

                // Set a timeout to show error message if it takes too long
                const timeoutId = setTimeout(() => {
                    loadingEl.classList.add('hidden');
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

                        let errorMessage = 'Unable to get your location. ';
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage += 'Please enable location access in your browser settings.';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage += 'Location information is unavailable.';
                                break;
                            case error.TIMEOUT:
                                errorMessage += 'Location request timed out.';
                                break;
                            default:
                                errorMessage += 'An unknown error occurred.';
                        }
                        console.error(errorMessage);
                    },
                    {
                        timeout: 10000, // 10 second timeout
                        maximumAge: 300000 // Cache location for 5 minutes
                    }
                );
            } else {
                alert('Geolocation is not supported by your browser');
            }
        }

        // Request location automatically when the component loads
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('lat') && !urlParams.has('lon')) {
                requestLocation();
            }
        });
    </script>
</div>
