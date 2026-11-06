@props([
    'rank',
    'name',
    'url',
    'views',
    'maxViews' => null, // Optional: for calculating relative bar width
    'change' => null, // Optional: ['diff' => int, 'pct' => float] for week-over-week change (backward compatibility)
    'trends' => null, // Optional: ['wow' => ['type' => 'WoW', 'pct' => float], 'yoy' => ['type' => 'YOY', 'pct' => float]]
])

<div class="bg-white rounded-lg shadow-md p-4 hover:shadow-lg transition-shadow">
    <div class="flex items-center justify-between">
        <div class="flex items-center flex-1 min-w-0">
            <!-- Rank Badge -->
            <div class="shrink-0 w-8 h-8 rounded-full bg-gray-900 text-white flex items-center justify-center text-sm font-bold mr-3">
                {{ $rank }}
            </div>
            
            <!-- Page Name -->
            <div class="flex-1 min-w-0">
                <a href="{{ $url }}" class="text-blue-600 hover:text-blue-800 hover:underline font-medium text-sm md:text-base truncate block">
                    {{ $name }}
                </a>
            </div>
        </div>
        
        <!-- Views Count, Trends, and Bar -->
        <div class="flex items-center ml-4">
            <div class="text-right mr-3">
                <div class="text-lg font-bold text-gray-900">{{ number_format($views) }}</div>
                <div class="flex flex-col items-end gap-1">
                    <div class="text-xs text-gray-500">ogledov</div>
                    @if($trends && is_array($trends))
                        @foreach($trends as $trend)
                            @if(isset($trend['pct']))
                            <div class="text-xs font-medium {{ $trend['pct'] >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                                {{ $trend['pct'] >= 0 ? '+' : '' }}{{ number_format($trend['pct'], 1) }}% {{ $trend['type'] ?? '' }}
                            </div>
                            @endif
                        @endforeach
                    @elseif($change && isset($change['pct']))
                    <div class="text-xs font-medium {{ $change['pct'] >= 0 ? 'text-emerald-700' : 'text-gray-500' }}">
                        {{ $change['pct'] >= 0 ? '+' : '' }}{{ number_format($change['pct'], 1) }}% WoW
                    </div>
                    @endif
                </div>
            </div>
            @if($maxViews && $maxViews > 0)
            <div class="w-16 md:w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                <div class="h-full bg-emerald-700 rounded-full transition-all" style="width: {{ min(100, ($views / $maxViews) * 100) }}%"></div>
            </div>
            @endif
        </div>
    </div>
</div>

