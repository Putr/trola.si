@extends('layout')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Page Views Statistics (Last 7 Days)</h1>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-300">
            <thead>
                <tr>
                    <th class="px-6 py-3 border-b border-gray-300 bg-gray-100 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 border-b border-gray-300 bg-gray-100 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Home</th>
                    <th class="px-6 py-3 border-b border-gray-300 bg-gray-100 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Search</th>
                    <th class="px-6 py-3 border-b border-gray-300 bg-gray-100 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Geosearch</th>
                    <th class="px-6 py-3 border-b border-gray-300 bg-gray-100 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Station Views</th>
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
</div>
@endsection
