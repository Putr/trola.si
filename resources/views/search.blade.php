@extends('layout')

@section('title', 'Iskanje postaj ' . ($query ? '"' . $query . '"' : '') . ' | Trola.si')

@section('description', 'Poiščite avtobusne postaje v Ljubljani. Enostavno iskanje in prikaz realnih časov prihodov
avtobusov LPP.')

@section('content')
<div class="container mx-auto">
    <div class="">
        <form class="flex justify-center gap-2" action="/search" method="GET">
            <input type="hidden" name="direction" value="{{
                $directionToCenter === true ? 'to' :
                ($directionToCenter === false ? 'from' : 'all')
            }}">
            <input type="text" name="q" value="{{ $query ?? '' }}"
                class="text-3xl font-bold bg-gray-100 shadow-md px-4 py-2 w-full max-w-lg h-16"
                placeholder="Ime postaje...">
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

    @if($useLocation)
    @include('components.location-handler')
    @endif

    @if(isset($locationFailed) && $locationFailed)
    <div class="max-w-2xl mx-auto mb-8">
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-center">
            <p class="text-amber-800">Nismo mogli pridobiti vaše lokacije. Prosimo, poiščite postajo ročno ali poskusite
                znova.</p>
        </div>
    </div>
    @endif

    @if(isset($stations))
    <div class="max-w-2xl mx-auto">
        @if($stations->isEmpty())
        <p class="text-center text-gray-600">
            @if(isset($error))
            {{ $error }}
            @else
            Ni najdenih postaj.
            @endif
        </p>
        @else
        <div class="space-y-4">
            @foreach($stations as $station)
            <a href="{{ route('station', ['code' => $station->code]) }}"
                class="block bg-white shadow-md rounded-lg p-4 hover:shadow-lg transition-shadow duration-150">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center w-14 h-14">
                                @if($station->is_direction_to_center === true)
                                <img src="/imgs/bullseye-to_small.png" alt="V center" class="w-8">
                                @elseif($station->is_direction_to_center === false)
                                <img src="/imgs/bullseye-from_small.png" alt="Iz centra" class="w-8">
                                @else
                                <img src="/imgs/all.svg" alt="Vse smeri" class="w-8">
                                @endif
                            </div>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold">{{ $station->name }}</h2>
                            @if(isset($station->distance))
                            <p class="text-gray-600 hidden md:block">Koda postaje: {{ $station->code }}</p>
                            <p class="text-gray-600 block md:hidden">{{ $station->distance }}m</p>
                            @else
                            <p class="text-gray-600">Koda postaje: {{ $station->code }}</p>
                            @endif
                        </div>
                    </div>
                    @if(isset($station->distance))
                    <div class="station-distance font-bold text-gray-400 hidden md:block">{{ $station->distance }}m
                    </div>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </div>
    @endif
</div>
@endsection