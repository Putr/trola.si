@extends('layout')

@section('content')
<div class="container mx-auto">
    <div class="">
        <form class="flex justify-center gap-2" action="/search" method="GET">
            <input type="hidden" name="direction" value="{{
                $directionToCenter === true ? 'to' :
                ($directionToCenter === false ? 'from' : 'all')
            }}">
            <input type="text" name="q" value="{{ $station->name }}" id="search-input"
                class="text-3xl font-bold bg-gray-100 shadow-md px-4 py-2 w-full max-w-lg h-16">
            <button type="submit"
                class="bg-gray-100 font-bold hover:bg-emerald-700 hover:text-white transition-colors duration-150 px-6 shadow-md h-16">
                Išči
            </button>
        </form>
    </div>
    <div class="mt-4 mb-12">
        <div class="flex justify-center gap-2 max-w-xs mx-auto">
            <x-direction-button direction="to" :is-active="$directionToCenter === true" :href="$hrefs['to']" />

            <x-direction-button direction="all" :is-active="$directionToCenter === null" :href="$hrefs['all']" />

            <x-direction-button direction="from" :is-active="$directionToCenter === false" :href="$hrefs['from']" />
        </div>
    </div>

    @if(is_null($directionToCenter))
    <x-arrivals-display-multi :arrivals="$arrivals" />
    @else
    <x-arrivals-display :arrivals="$arrivals" :showDirectionNameOnMobile="false" />
    @endif
</div>

<script>
    function setCookie(name, value, seconds) {
        const date = new Date();
        date.setTime(date.getTime() + (seconds * 1000));
        document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/`;
    }

    let reloadTimer;

    function setupAutoReload() {
        clearTimeout(reloadTimer);

        reloadTimer = setTimeout(() => {
            const searchInput = document.getElementById('search-input');
            const hasFocus = document.hasFocus();
            const isSearchFocused = document.activeElement === searchInput;

            if (hasFocus && !isSearchFocused) {
                setCookie('autoreload', '1', 5);
                window.location.reload();
            } else {
                setupAutoReload();
            }
        }, 60000); // Changed to 60 seconds
    }

    // Start the auto-reload timer
    setupAutoReload();

    document.addEventListener('keydown', () => {
        setupAutoReload();
    });
</script>
@endsection