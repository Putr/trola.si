<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

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

<body class="bg-white">
    <header class="">
        <div class="mx-auto max-w-7xl px-4 pt-12 pb-6 md:pt-12 md:pb-8">
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

    <main class="mx-auto max-w-7xl px-4 pb-16">
        @yield('content')
    </main>

    @vite('resources/js/app.js')
</body>

</html>
