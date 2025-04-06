<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Trola.si - Naslednji prihodi Ljubljanskih avtobusov')</title>
    <meta name="description"
        content="@yield('description', 'Preverite naslednje prihode Ljubljanskih avtobusov v realnem času. Enostavno iskanje postaj in prikazovanje prihodov avtobusov LPP.')">

    @yield('meta', '')

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/imgs/bus.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('title', 'Trola.si - Naslednji prihodi Ljubljanskih avtobusov')">
    <meta property="og:description"
        content="@yield('description', 'Preverite naslednje prihode Ljubljanskih avtobusov v realnem času. Enostavno iskanje postaj in prikazovanje prihodov avtobusov LPP.')">
    <meta property="og:image" content="{{ url('/og-image.png') }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="@yield('title', 'Trola.si - Naslednji prihodi Ljubljanskih avtobusov')">
    <meta property="twitter:description"
        content="@yield('description', 'Preverite naslednje prihode Ljubljanskih avtobusov v realnem času. Enostavno iskanje postaj in prikazovanje prihodov avtobusov LPP.')">
    <meta property="twitter:image" content="{{ url('/og-image.png') }}">

    <!-- Canonical URL -->
    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Structured Data -->
    <script type="application/ld+json">
        {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "Trola.si",
        "url": "{{ url('/') }}",
        "description": "Preverite naslednje prihode Ljubljanskih avtobusov v realnem času.",
        "applicationCategory": "TransportationApplication",
        "operatingSystem": "Any",
        "author": {
            "@type": "Person",
            "name": "Rok Andrée",
            "url": "https://andree.si"
        },
        "publisher": {
            "@type": "Organization",
            "name": "IP21",
            "url": "https://ip21.si",
            "logo": {
                "@type": "ImageObject",
                "url": "https://ip21.si/ip21_full.png"
            }
        }
    }
    </script>

    @stack('head')

    <style>
        /* Critical CSS */
        .no-fouc {
            visibility: hidden;
        }

        body {
            background-color: gray;
        }

        .max-w-7xl {
            max-width: 80rem;
        }

        .mx-auto {
            margin-left: auto;
            margin-right: auto;
        }
    </style>
    @vite('resources/css/app.css')
    <script>
        // Remove no-fouc class when CSS is loaded
        document.documentElement.classList.add('no-fouc');
        window.addEventListener('load', function() {
            document.documentElement.classList.remove('no-fouc');
        });
    </script>
</head>

<body class="bg-white flex flex-col min-h-full">
    <header class="">
        <div class="mx-auto px-4 pt-12 pb-6 md:pt-12 md:pb-8">
            <div class="flex justify-center">
                <a href="/" class="flex items-center gap-4 hover:opacity-80 transition-opacity">
                    <img src="/imgs/bus.svg" alt="Bus icon" class="w-8 h-8">
                    <div>
                        <h1 class="text-2xl font-bold">Trola.si</h1>
                        <p class="text-gray-600 max-w-56">Naslednji prihodi Ljubljanskih avtobusov</p>
                    </div>
                </a>
                @if(!request()->is('navodila'))
                <a href="{{ route('help') }}" class="absolute top-4 right-4 text-gray-600 hover:text-gray-900"
                    title="Navodila za uporabo">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </a>
                @endif
            </div>
        </div>
    </header>

    <main class="mx-auto px-4 pb-16 flex-grow min-w-full">
        @yield('content')
    </main>

    <footer class="text-center py-4 text-sm text-gray-500">
        Razvil in vzdržuje <a href="https://andree.si" target="_blank" class="underline hover:no-underline">Rok
            Andrée</a> (<a href="https://ip21.si" target="_blank" class="underline hover:no-underline">IP21</a>). <span
            class="hidden sm:inline">|</span><br class="sm:hidden"> Vir podatkov: <a href="https://www.lpp.si/"
            target="_blank" rel="nofollow" class="underline hover:no-underline">LPP</a> | <a
            href="https://github.com/Putr/trola.si" target="_blank" class="underline hover:no-underline">Github</a> | <a
            href="{{ route('help') }}" class="underline hover:no-underline">Navodila za uporabo</a>
    </footer>

    @vite('resources/js/app.js')
</body>

</html>