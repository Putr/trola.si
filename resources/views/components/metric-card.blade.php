@props([
    'label',
    'value',
    'trend' => null, // Optional: ['value' => number, 'pct' => number] for trend indicator
])

<div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
    <div class="text-sm font-medium text-gray-600 mb-2">{{ $label }}</div>
    <div class="flex items-baseline justify-between">
        <div class="text-3xl font-bold text-gray-900">{{ number_format($value) }}</div>
        @if($trend && isset($trend['pct']))
        <div class="flex items-center text-sm {{ $trend['pct'] >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
            @if($trend['pct'] >= 0)
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                </svg>
            @else
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
            @endif
            <span>{{ $trend['pct'] >= 0 ? '+' : '' }}{{ number_format($trend['pct'], 1) }}%</span>
        </div>
        @endif
    </div>
</div>



