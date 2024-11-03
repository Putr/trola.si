<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>

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
            href="https://github.com/Putr/trola.si" target="_blank" class="underline hover:no-underline">Github</a>
    </footer>

    @vite('resources/js/app.js')
</body>

</html>