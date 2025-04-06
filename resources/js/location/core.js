import { CONFIG } from './constants.js';
import { LocationCompatibility } from './compatibility.js';

export class LocationService {
    constructor(ui) {
        this.ui = ui;
        this.currentLat = null;
        this.currentLon = null;
    }

    async getCurrentPosition() {
        if (!LocationCompatibility.isGeolocationSupported()) {
            throw new Error('Geolocation is not supported by your browser');
        }

        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.currentLat = position.coords.latitude;
                    this.currentLon = position.coords.longitude;
                    resolve(position);
                },
                (error) => {
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
