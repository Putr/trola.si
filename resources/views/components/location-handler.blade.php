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
        <button id="retry-location" class="mt-4 px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">
            Poskusi znova
        </button>
    </div>

    @vite(['resources/js/app.js'])
</div>