@extends('layout')

@section('title', 'Statistika uporabe | Trola.si')

@section('description', 'Statistika uporabe spletne strani Trola.si. Pregled števila ogledov strani v zadnjih 7 dneh.')

@section('meta')
<meta name="robots" content="noindex, nofollow">
<meta name="googlebot" content="noindex, nofollow">
@endsection

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Page Views Statistics (Last 7 Days)</h1>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-300">
            <thead>
                <tr>
                    <th
                        class="px-6 py-3 border-b border-gray-300 bg-gray-100 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                        Date</th>
                    <th
                        class="px-6 py-3 border-b border-gray-300 bg-gray-100 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                        Home</th>
                    <th
                        class="px-6 py-3 border-b border-gray-300 bg-gray-100 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                        Search</th>
                    <th
                        class="px-6 py-3 border-b border-gray-300 bg-gray-100 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                        Geosearch</th>
                    <th
                        class="px-6 py-3 border-b border-gray-300 bg-gray-100 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                        Station Views</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stats as $day)
                <tr>
                    <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-300">{{ $day['date'] }}</td>
                    <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-300">{{ $day['home'] }}</td>
                    <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-300">{{ $day['search'] }}</td>
                    <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-300">{{ $day['geosearch'] }}</td>
                    <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-300">{{ $day['stations'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Most Popular Pages Section -->
    <h2 class="text-xl font-bold mt-10 mb-4">Most Popular Pages (Last 7 Days)</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-300">
            <thead>
                <tr>
                    <th
                        class="px-6 py-3 border-b border-gray-300 bg-gray-100 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                        Rank</th>
                    <th
                        class="px-6 py-3 border-b border-gray-300 bg-gray-100 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                        Page</th>
                    <th
                        class="px-6 py-3 border-b border-gray-300 bg-gray-100 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                        Views</th>
                </tr>
            </thead>
            <tbody>
                @php $rank = 1; @endphp
                @foreach($popularPages as $page)
                <tr>
                    <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-300">{{ $rank++ }}</td>
                    <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-300">
                        <a href="{{ $page['url'] }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                            {{ $page['name'] }}
                        </a>
                    </td>
                    <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-300">{{ $page['views'] }}</td>
                </tr>
                @endforeach
                @if(count($popularPages) === 0)
                <tr>
                    <td colspan="3" class="px-6 py-4 text-center border-b border-gray-300">No data available</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection