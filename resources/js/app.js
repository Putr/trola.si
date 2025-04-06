import './bootstrap';

// Import location modules
import { CONFIG } from './location/constants.js';
import { LocationCompatibility } from './location/compatibility.js';
import { LocationUI } from './location/ui.js';
import { LocationService } from './location/core.js';
import { LocationTracker } from './location/tracker.js';

// Initialize location tracker if we're on a page that needs it
document.addEventListener('DOMContentLoaded', () => {
    const locationLoading = document.getElementById('location-loading');
    if (locationLoading) {
        // Show loading indicator immediately if we're on the homepage
        if (window.location.pathname === '/') {
            locationLoading.classList.remove('hidden');
        }

        const tracker = new LocationTracker();
        tracker.start();
    }
});
