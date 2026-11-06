@extends('layout')

@section('title', 'Statistika uporabe | Trola.si')

@section('description', 'Statistika uporabe spletne strani Trola.si. Pregled števila ogledov strani v zadnjih 14 dneh.')

@section('meta')
<meta name="robots" content="noindex, nofollow">
<meta name="googlebot" content="noindex, nofollow">
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Statistika uporabe</h1>
    </div>

    @php
        // Get max views for popular pages bar visualization
        $maxViews = $popularPages && $popularPages->isNotEmpty() ? $popularPages->max('views') : 1;
    @endphp

    <!-- Daily Trends Section -->
    <div class="mb-10">
        <div class="mb-4">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Dnevni pregled</h2>
            <div class="text-xs text-gray-500">
                <span class="font-medium">PRA</span> = razlika od povprečja za ta dan v tednu (%),
                <span class="font-medium">YOY</span> = razlika od istega dneva pred letom (%)
            </div>
        </div>
        @if($stats->count() > 0)
        <!-- Mobile/Tablet: Card view -->
        <div class="lg:hidden grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($stats as $day)
            <x-daily-stat-card
                :date="$day['date']"
                :home="$day['home']"
                :search="$day['search']"
                :geosearch="$day['geosearch']"
                :stations="$day['stations']"
                :pctFromAvg="$day['pct_from_avg'] ?? null"
                :yoy="$day['yoy'] ?? null"
            />
            @endforeach
        </div>

        <!-- Desktop: Table view -->
        <div class="hidden lg:block bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Datum</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domača stran</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Iskanje</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Geoiskanje</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Postaje</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($stats as $day)
                        @php
                            $carbonDate = \Carbon\Carbon::parse($day['date']);
                            $formattedDate = $carbonDate->format('D, M j');
                            $dayOfWeek = $carbonDate->format('D'); // Mon, Tue, etc.

                            // Get percentage from rolling average (PRA)
                            $praHome = isset($day['pct_from_avg']) && is_array($day['pct_from_avg']) ? ($day['pct_from_avg']['home'] ?? null) : null;
                            $praSearch = isset($day['pct_from_avg']) && is_array($day['pct_from_avg']) ? ($day['pct_from_avg']['search'] ?? null) : null;
                            $praGeosearch = isset($day['pct_from_avg']) && is_array($day['pct_from_avg']) ? ($day['pct_from_avg']['geosearch'] ?? null) : null;
                            $praStations = isset($day['pct_from_avg']) && is_array($day['pct_from_avg']) ? ($day['pct_from_avg']['stations'] ?? null) : null;

                            // Get year-over-year (YOY)
                            $yoyHome = isset($day['yoy']) && is_array($day['yoy']) && isset($day['yoy']['home']) && is_array($day['yoy']['home']) ? ($day['yoy']['home']['pct'] ?? null) : null;
                            $yoySearch = isset($day['yoy']) && is_array($day['yoy']) && isset($day['yoy']['search']) && is_array($day['yoy']['search']) ? ($day['yoy']['search']['pct'] ?? null) : null;
                            $yoyGeosearch = isset($day['yoy']) && is_array($day['yoy']) && isset($day['yoy']['geosearch']) && is_array($day['yoy']['geosearch']) ? ($day['yoy']['geosearch']['pct'] ?? null) : null;
                            $yoyStations = isset($day['yoy']) && is_array($day['yoy']) && isset($day['yoy']['stations']) && is_array($day['yoy']['stations']) ? ($day['yoy']['stations']['pct'] ?? null) : null;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="font-medium text-gray-900">{{ $formattedDate }}</div>
                                <div class="text-xs text-gray-400 font-medium">{{ $dayOfWeek }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-3">
                                    <div class="text-2xl font-bold text-gray-900 leading-tight">{{ number_format($day['home']) }}</div>
                                    <div class="flex flex-col justify-center">
                                        @if($praHome !== null)
                                        <div class="text-xs font-medium {{ $praHome >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                                            {{ $praHome >= 0 ? '+' : '' }}{{ number_format($praHome, 1) }}% PRA
                                        </div>
                                        @endif
                                        @if($yoyHome !== null)
                                        <div class="text-xs font-medium {{ $yoyHome >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                                            {{ $yoyHome >= 0 ? '+' : '' }}{{ number_format($yoyHome, 1) }}% YOY
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-3">
                                    <div class="text-2xl font-bold text-gray-900 leading-tight">{{ number_format($day['search']) }}</div>
                                    <div class="flex flex-col justify-center">
                                        @if($praSearch !== null)
                                        <div class="text-xs font-medium {{ $praSearch >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                                            {{ $praSearch >= 0 ? '+' : '' }}{{ number_format($praSearch, 1) }}% PRA
                                        </div>
                                        @endif
                                        @if($yoySearch !== null)
                                        <div class="text-xs font-medium {{ $yoySearch >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                                            {{ $yoySearch >= 0 ? '+' : '' }}{{ number_format($yoySearch, 1) }}% YOY
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-3">
                                    <div class="text-2xl font-bold text-gray-900 leading-tight">{{ number_format($day['geosearch']) }}</div>
                                    <div class="flex flex-col justify-center">
                                        @if($praGeosearch !== null)
                                        <div class="text-xs font-medium {{ $praGeosearch >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                                            {{ $praGeosearch >= 0 ? '+' : '' }}{{ number_format($praGeosearch, 1) }}% PRA
                                        </div>
                                        @endif
                                        @if($yoyGeosearch !== null)
                                        <div class="text-xs font-medium {{ $yoyGeosearch >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                                            {{ $yoyGeosearch >= 0 ? '+' : '' }}{{ number_format($yoyGeosearch, 1) }}% YOY
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-3">
                                    <div class="text-2xl font-bold text-gray-900 leading-tight">{{ number_format($day['stations']) }}</div>
                                    <div class="flex flex-col justify-center">
                                        @if($praStations !== null)
                                        <div class="text-xs font-medium {{ $praStations >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                                            {{ $praStations >= 0 ? '+' : '' }}{{ number_format($praStations, 1) }}% PRA
                                        </div>
                                        @endif
                                        @if($yoyStations !== null)
                                        <div class="text-xs font-medium {{ $yoyStations >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                                            {{ $yoyStations >= 0 ? '+' : '' }}{{ number_format($yoyStations, 1) }}% YOY
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @else
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <p class="text-gray-600">Ni podatkov za prikaz</p>
        </div>
        @endif
    </div>

    <!-- Popular Pages Section -->
    <div>
        <div class="mb-4">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Najbolj obiskani strani (zadnjih 7 dni)</h2>
            <div class="text-xs text-gray-500">
                <span class="font-medium">WoW</span> = sprememba glede na prejšnji teden (%),
                <span class="font-medium">YOY</span> = sprememba glede na isto obdobje pred letom (%)
            </div>
        </div>
        @if($popularPages && $popularPages->count() > 0)
        <!-- Mobile/Tablet: Card view -->
        <div class="lg:hidden grid grid-cols-1 md:grid-cols-2 gap-4">
            @php $rank = 1; @endphp
            @foreach($popularPages as $page)
            @php
                $pageName = is_array($page) ? $page['name'] : (is_object($page) ? $page->name : '');
                $pageUrl = is_array($page) ? $page['url'] : (is_object($page) ? $page->url : '#');
                $pageViews = is_array($page) ? $page['views'] : (is_object($page) ? $page->views : 0);
            @endphp
            <x-popular-page-card
                :rank="$rank++"
                :name="$pageName"
                :url="$pageUrl"
                :views="$pageViews"
                :maxViews="$maxViews"
                :change="is_array($page) && isset($page['change']) ? $page['change'] : null"
                :trends="is_array($page) && isset($page['trends']) ? $page['trends'] : null"
            />
            @endforeach
        </div>

        <!-- Desktop: Table view -->
        <div class="hidden lg:block bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stran</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ogledi</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Trendi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @php $rank = 1; @endphp
                        @foreach($popularPages as $page)
                        @php
                            $pageName = is_array($page) ? $page['name'] : (is_object($page) ? $page->name : '');
                            $pageUrl = is_array($page) ? $page['url'] : (is_object($page) ? $page->url : '#');
                            $pageViews = is_array($page) ? $page['views'] : (is_object($page) ? $page->views : 0);
                            $trends = is_array($page) && isset($page['trends']) ? $page['trends'] : null;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="shrink-0 w-8 h-8 rounded-full bg-gray-900 text-white flex items-center justify-center text-sm font-bold">
                                    {{ $rank++ }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ $pageUrl }}" class="text-blue-600 hover:text-blue-800 hover:underline font-medium">
                                    {{ $pageName }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="text-lg font-bold text-gray-900">{{ number_format($pageViews) }}</div>
                                <div class="text-xs text-gray-500">ogledov</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                @if($trends && is_array($trends))
                                    <div class="flex flex-col items-end gap-1">
                                        @foreach($trends as $trend)
                                            @if(isset($trend['pct']))
                                            <div class="text-xs font-medium {{ $trend['pct'] >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                                                {{ $trend['pct'] >= 0 ? '+' : '' }}{{ number_format($trend['pct'], 1) }}% {{ $trend['type'] ?? '' }}
                                            </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @elseif(is_array($page) && isset($page['change']) && isset($page['change']['pct']))
                                    <div class="text-xs font-medium {{ $page['change']['pct'] >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                                        {{ $page['change']['pct'] >= 0 ? '+' : '' }}{{ number_format($page['change']['pct'], 1) }}% WoW
                                    </div>
                                @else
                                    <span class="text-gray-400 text-xs">-</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @else
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <p class="text-gray-600">Ni podatkov za prikaz</p>
        </div>
        @endif
    </div>
</div>
@endsection
