<script>
    function requestLocation() {
    if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;

            // If we're on the home page (/) redirect to search
            // Otherwise, add coordinates to current search
            if (window.location.pathname === '/') {
                window.location.href = `/geosearch?lat=${lat}&lon=${lon}`;
            } else {
                // Preserve existing search parameters
                const currentParams = new URLSearchParams(window.location.search);
                currentParams.set('lat', lat);
                currentParams.set('lon', lon);
                window.location.href = `/geosearch?${currentParams.toString()}`;
            }
        }, function(error) {
            console.error("Error getting location:", error);
        });
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