export class LocationUI {
    constructor() {
        this.elements = {
            loading: document.getElementById('location-loading'),
            error: document.getElementById('location-error'),
            errorMessage: document.getElementById('location-error-message'),
            retryButton: document.getElementById('retry-location')
        };

        if (this.elements.retryButton) {
            this.elements.retryButton.addEventListener('click', () => {
                this.onRetry && this.onRetry();
            });
        }
    }

    showLoading() {
        if (this.elements.loading) {
            this.elements.loading.classList.remove('hidden');
        }
        if (this.elements.error) {
            this.elements.error.classList.add('hidden');
        }
    }

    showError(message) {
        if (this.elements.error) {
            this.elements.error.classList.remove('hidden');
        }
        if (this.elements.errorMessage) {
            this.elements.errorMessage.textContent = message;
        }
        if (this.elements.loading) {
            this.elements.loading.classList.add('hidden');
        }
    }

    hideAll() {
        if (this.elements.loading) {
            this.elements.loading.classList.add('hidden');
        }
        if (this.elements.error) {
            this.elements.error.classList.add('hidden');
        }
    }

    setRetryHandler(handler) {
        this.onRetry = handler;
    }
}
