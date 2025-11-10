@props([
    'date', // Y-m-d format
    'home',
    'search',
    'geosearch',
    'stations',
    'pctFromAvg' => null, // Optional: ['home' => float, 'search' => float, 'geosearch' => float, 'stations' => float]
    'yoy' => null, // Optional: ['home' => ['diff' => int, 'pct' => float], ...]
])

@php
    $carbonDate = \Carbon\Carbon::parse($date);
    $formattedDate = $carbonDate->format('D, M j'); // e.g., "Mon, Jan 15"
    $dayName = $carbonDate->format('l'); // Full day name
@endphp

<div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
    <div class="mb-4">
        <div class="text-lg font-semibold text-gray-900">{{ $formattedDate }}</div>
        <div class="text-xs text-gray-500">{{ $dayName }}</div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <!-- Home Views -->
        <div class="flex flex-col">
            <div class="text-xs text-gray-600 mb-1">Domača stran</div>
            <div class="flex items-center gap-3">
                <div class="text-xl font-bold text-gray-900">{{ number_format($home) }}</div>
                <div class="flex flex-col justify-center">
                    @if($pctFromAvg && isset($pctFromAvg['home']))
                    <div class="text-xs font-medium {{ $pctFromAvg['home'] >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                        {{ $pctFromAvg['home'] >= 0 ? '+' : '' }}{{ number_format($pctFromAvg['home'], 1) }}% PRA
                    </div>
                    @endif
                    @if($yoy && isset($yoy['home']['pct']))
                    <div class="text-xs font-medium {{ $yoy['home']['pct'] >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                        {{ $yoy['home']['pct'] >= 0 ? '+' : '' }}{{ number_format($yoy['home']['pct'], 1) }}% YOY
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Search Views -->
        <div class="flex flex-col">
            <div class="text-xs text-gray-600 mb-1">Iskanje</div>
            <div class="flex items-center gap-3">
                <div class="text-xl font-bold text-gray-900">{{ number_format($search) }}</div>
                <div class="flex flex-col justify-center">
                    @if($pctFromAvg && isset($pctFromAvg['search']))
                    <div class="text-xs font-medium {{ $pctFromAvg['search'] >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                        {{ $pctFromAvg['search'] >= 0 ? '+' : '' }}{{ number_format($pctFromAvg['search'], 1) }}% PRA
                    </div>
                    @endif
                    @if($yoy && isset($yoy['search']['pct']))
                    <div class="text-xs font-medium {{ $yoy['search']['pct'] >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                        {{ $yoy['search']['pct'] >= 0 ? '+' : '' }}{{ number_format($yoy['search']['pct'], 1) }}% YOY
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Geosearch Views -->
        <div class="flex flex-col">
            <div class="text-xs text-gray-600 mb-1">Geoiskanje</div>
            <div class="flex items-center gap-3">
                <div class="text-xl font-bold text-gray-900">{{ number_format($geosearch) }}</div>
                <div class="flex flex-col justify-center">
                    @if($pctFromAvg && isset($pctFromAvg['geosearch']))
                    <div class="text-xs font-medium {{ $pctFromAvg['geosearch'] >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                        {{ $pctFromAvg['geosearch'] >= 0 ? '+' : '' }}{{ number_format($pctFromAvg['geosearch'], 1) }}% PRA
                    </div>
                    @endif
                    @if($yoy && isset($yoy['geosearch']['pct']))
                    <div class="text-xs font-medium {{ $yoy['geosearch']['pct'] >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                        {{ $yoy['geosearch']['pct'] >= 0 ? '+' : '' }}{{ number_format($yoy['geosearch']['pct'], 1) }}% YOY
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Station Views -->
        <div class="flex flex-col">
            <div class="text-xs text-gray-600 mb-1">Postaje</div>
            <div class="flex items-center gap-3">
                <div class="text-xl font-bold text-gray-900">{{ number_format($stations) }}</div>
                <div class="flex flex-col justify-center">
                    @if($pctFromAvg && isset($pctFromAvg['stations']))
                    <div class="text-xs font-medium {{ $pctFromAvg['stations'] >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                        {{ $pctFromAvg['stations'] >= 0 ? '+' : '' }}{{ number_format($pctFromAvg['stations'], 1) }}% PRA
                    </div>
                    @endif
                    @if($yoy && isset($yoy['stations']['pct']))
                    <div class="text-xs font-medium {{ $yoy['stations']['pct'] >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                        {{ $yoy['stations']['pct'] >= 0 ? '+' : '' }}{{ number_format($yoy['stations']['pct'], 1) }}% YOY
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

