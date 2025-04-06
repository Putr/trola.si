import { CONFIG } from './constants.js';
import { LocationCompatibility } from './compatibility.js';
import { LocationService } from './core.js';
import { LocationUI } from './ui.js';

export class LocationTracker {
    constructor() {
        this.ui = new LocationUI();
        this.locationService = new LocationService(this.ui);
        this.interval = null;
        this._setupVisibilityHandler();
    }

    start() {
        if (this._shouldTrackLocation()) {
            this._requestInitialLocation();
        }
    }

    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    }

    _shouldTrackLocation() {
        return !LocationCompatibility.isUserTyping() &&
            document.visibilityState === 'visible';
    }

    _setupVisibilityHandler() {
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this._checkLocationChange();
            }
        });
    }

    async _requestInitialLocation() {
        try {
            const position = await this.locationService.getCurrentPosition();
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;

            // If we're on the homepage, redirect to geosearch
            if (window.location.pathname === '/') {
                window.location.href = `/geosearch?lat=${lat}&lon=${lon}`;
            } else {
                // Otherwise start tracking location changes
                this._startLocationTracking();
            }
        } catch (error) {
            console.error('Error getting initial location:', error);
        }
    }

    async _checkLocationChange() {
        if (!this._shouldTrackLocation()) {
            return;
        }

        try {
            const position = await this.locationService.getCurrentPosition();
            const newLat = position.coords.latitude;
            const newLon = position.coords.longitude;

            if (this.locationService.shouldUpdateLocation(newLat, newLon)) {
                this.locationService.setCookie(
                    CONFIG.COOKIES.AUTO_RELOAD,
                    '1',
                    CONFIG.COOKIES.MAX_AGE
                );
                window.location.href = `/geosearch?lat=${newLat}&lon=${newLon}`;
            }
        } catch (error) {
            console.error('Error checking location change:', error);
        }
    }

    _startLocationTracking() {
        this.interval = setInterval(
            () => this._checkLocationChange(),
            CONFIG.INTERVALS.LOCATION_REFRESH
        );
    }
}
