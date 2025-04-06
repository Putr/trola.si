import { CONFIG } from './constants.js';
import { LocationCompatibility } from './compatibility.js';

export class LocationService {
    constructor(ui) {
        this.ui = ui;
        this.currentLat = null;
        this.currentLon = null;
        this.lastError = null;
        this._loadFromSession();
    }

    _loadFromSession() {
        try {
            const storedLocation = sessionStorage.getItem('lastLocation');
            if (storedLocation) {
                const { lat, lon, timestamp } = JSON.parse(storedLocation);
                // Check if the stored location is still valid (within timeout period)
                if (Date.now() - timestamp < CONFIG.INTERVALS.LOCATION_TIMEOUT) {
                    this.currentLat = lat;
                    this.currentLon = lon;
                    return true;
                }
            }
        } catch (e) {
            console.error('Error loading location from session:', e);
        }
        return false;
    }

    _saveToSession(lat, lon) {
        try {
            sessionStorage.setItem('lastLocation', JSON.stringify({
                lat,
                lon,
                timestamp: Date.now()
            }));
        } catch (e) {
            console.error('Error saving location to session:', e);
        }
    }

    async getCurrentPosition() {
        if (!LocationCompatibility.isGeolocationSupported()) {
            throw new Error('Geolocation is not supported by your browser');
        }

        // Try to use stored location first
        if (this._loadFromSession()) {
            return {
                coords: {
                    latitude: this.currentLat,
                    longitude: this.currentLon
                }
            };
        }

        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.currentLat = position.coords.latitude;
                    this.currentLon = position.coords.longitude;
                    this._saveToSession(this.currentLat, this.currentLon);
                    this.lastError = null;
                    resolve(position);
                },
                (error) => {
                    console.error('Location error details:', {
                        code: error.code,
                        message: error.message,
                        isWebView: LocationCompatibility.isWebView(),
                        userAgent: navigator.userAgent
                    });
                    this.lastError = error;
                    this.handleLocationError(error);
                    reject(error);
                },
                LocationCompatibility.getLocationOptions()
            );
        });
    }

    handleLocationError(error) {
        let errorMessage = '';
        switch (error.code) {
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

        this.ui.showError(errorMessage);
        console.error('Location error:', errorMessage);
    }

    shouldUpdateLocation(newLat, newLon) {
        if (this.currentLat === null || this.currentLon === null) {
            return true;
        }

        const distance = LocationCompatibility.calculateDistance(
            this.currentLat,
            this.currentLon,
            newLat,
            newLon
        );

        return distance > CONFIG.THRESHOLDS.DISTANCE;
    }

    setCookie(name, value, seconds) {
        const date = new Date();
        date.setTime(date.getTime() + (seconds * 1000));
        document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/`;
    }
}
