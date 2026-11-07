@extends('layout')

@section('title', 'Statistika uporabe | Trola.si')

@section('description', 'Statistika uporabe spletne strani Trola.si: koliko ljudi jo uporablja, kako se to spreminja in katere postaje so najbolj iskane.')

@section('meta')
<meta name="robots" content="noindex, nofollow">
<meta name="googlebot" content="noindex, nofollow">
@endsection

@php
    $num = fn (float|int $n): string => number_format($n, 0, ',', '.');
    $perDay = fn (float $n): string => $n >= 10 ? number_format($n, 0, ',', '.') : number_format($n, 1, ',', '.');
    $pct = fn (float $share): string => number_format($share * 100, 0, ',', '.').' %';
    $dateShort = fn (string $date): string => \Carbon\Carbon::parse($date)->format('j. n. Y');
    $link = fn (array $params = []): string => route('stats', array_filter([
        'obdobje' => $months !== 3 ? $months : null,
        'seznam' => $list !== 'top' ? $list : null,
        'postaja' => $term,
        ...$params,
    ], fn ($v) => $v !== null));
    $pill = 'inline-block rounded-full px-3 py-1 text-sm whitespace-nowrap';
    $pillOn = 'bg-emerald-700 text-white';
    $pillOff = 'bg-gray-100 text-gray-700 hover:bg-gray-200';
    $plural = fn (int $n): string => match (true) {
        $n % 100 === 1 => 'postaja',
        $n % 100 === 2 => 'postaji',
        $n % 100 === 3, $n % 100 === 4 => 'postaje',
        default => 'postaj',
    };
    $lists = ['top' => 'Najbolj uporabljene', 'rising' => 'V porastu', 'falling' => 'V upadu', 'least' => 'Najmanj uporabljene'];
@endphp

@section('content')
<div class="max-w-2xl mx-auto px-1 py-4 space-y-10">
    <h1 class="text-2xl font-bold text-gray-900">Statistika uporabe</h1>

    @if($unavailable)
        <div class="bg-gray-100 rounded-lg p-4 text-gray-700">Statistika trenutno ni na voljo. Poskusite znova čez nekaj minut.</div>
    @else
    {{-- 1. Is it used? --}}
    <section aria-labelledby="uporaba">
        <h2 id="uporaba" class="text-xl font-bold text-gray-900 mb-3">Ali se uporablja? <span class="text-sm font-normal text-gray-500">(zadnjih 28 dni)</span></h2>

        <div class="grid grid-cols-2 gap-3">
            @foreach([['Ogledi na dan', $overview['views']], ['Iskanja po bližini na dan', $overview['geosearch']]] as [$title, $metric])
                <div class="bg-gray-100 rounded-lg p-3">
                    <div class="text-xs text-gray-600">{{ $title }}</div>
                    @if($metric['avg'] === null)
                        <div class="text-sm text-gray-500 mt-2">ni podatkov</div>
                    @else
                        <div class="text-3xl font-bold text-gray-900 leading-tight">{{ $perDay($metric['avg']) }}</div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-gray-600">
                            <x-stats-change-badge :value="$metric['vs_prev']" /> glede na prejšnjih 28 dni
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-gray-600">
                            <x-stats-change-badge :value="$metric['vs_year']" /> glede na lani
                        </div>
                    @endif
                </div>
            @endforeach

            <div class="bg-gray-100 rounded-lg p-3 col-span-2">
                <div class="text-xs text-gray-600">Od kod</div>
                @if($overview['app_share'] === null)
                    <div class="text-sm text-gray-500 mt-1">ni podatkov</div>
                @else
                    <div class="mt-1 text-sm text-gray-900">Splet <b>{{ $pct(1 - $overview['app_share']) }}</b> · Android aplikacija <b>{{ $pct($overview['app_share']) }}</b></div>
                @endif
            </div>
        </div>

        <p class="mt-3 text-sm text-gray-700">
            Ogledi po straneh:
            domača stran {{ $pct($overview['entry']['home']) }},
            iskanje {{ $pct($overview['entry']['search']) }},
            bližina {{ $pct($overview['entry']['geosearch']) }},
            postaje {{ $pct($overview['entry']['stations']) }}.
        </p>

        @php
            $filtered = $overview['filtered'];
        @endphp
        @if(array_sum($filtered) > 0)
            <p class="mt-2 text-xs text-gray-500">
                Izločeno: {{ $num($filtered['reload']) }} samodejnih osvežitev, {{ $num($filtered['bot']) }} botov,
                {{ $num($filtered['datacenter']) }} podatkovnih centrov, {{ $num($filtered['foreign']) }} izven Evrope.
            </p>
        @endif
        @if($overview['views']['straddles'])
            <p class="mt-1 text-xs text-gray-500">Primerjava vključuje dneve pred filtri (od {{ $liveSince }}).</p>
        @endif
    </section>

    {{-- 2. Trend --}}
    <section aria-labelledby="trend">
        <div class="flex items-center justify-between mb-3">
            <h2 id="trend" class="text-xl font-bold text-gray-900">Trend</h2>
            <nav class="flex gap-1.5" aria-label="Obdobje">
                @foreach([3, 6, 12] as $range)
                    <a href="{{ $link(['obdobje' => $range !== 3 ? $range : null]) }}#trend"
                        class="{{ $pill }} {{ $months === $range ? $pillOn : $pillOff }}"
                        @if($months === $range) aria-current="true" @endif>{{ $range }} m</a>
                @endforeach
            </nav>
        </div>

        @php
            $days = $trend['days'];
            $markerIndex = null;
            foreach ($days as $i => $day) {
                if ($day['date'] === $trend['live_since']) {
                    $markerIndex = $i;
                    break;
                }
            }
        @endphp
        @if(count(array_filter(array_column($days, 'views'), fn ($v) => $v !== null)) < 2)
            <div class="bg-gray-100 rounded-lg p-4 text-sm text-gray-600">ni podatkov</div>
        @else
            <x-stats-line-chart
                :daily="array_column($days, 'views')"
                :smooth="array_column($days, 'avg28')"
                :dashed="array_column($days, 'last_year_avg28')"
                :marker-index="$markerIndex"
                marker-label="filtri"
                label="Dnevni ogledi in 28-dnevno povprečje" />
            <div class="mt-1 flex justify-between text-[11px] text-gray-500">
                <span>{{ $dateShort($days[0]['date']) }}</span>
                <span>{{ $dateShort($days[count($days) - 1]['date']) }}</span>
            </div>
            <p class="mt-2 text-xs text-gray-600">
                Siva: dnevni ogledi ·
                <b class="text-emerald-700">zelena: 28-dnevno povprečje</b> ·
                črtkana: isto obdobje lani
            </p>
        @endif
    </section>

    {{-- 3. Stations --}}
    <section aria-labelledby="postaje">
        <h2 id="postaje" class="text-xl font-bold text-gray-900 mb-1">Postaje <span class="text-sm font-normal text-gray-500">(zadnjih 28 dni glede na prejšnjih 28)</span></h2>
        <p class="text-xs text-gray-500 mb-3">Sprememba je sprememba deleža med vsemi ogledi postaj. Obe smeri postaje štejeta skupaj.</p>

        <nav class="flex flex-wrap gap-1.5 mb-3" aria-label="Seznam postaj">
            @foreach($lists as $key => $title)
                <a href="{{ $link(['seznam' => $key !== 'top' ? $key : null]) }}#postaje"
                    class="{{ $pill }} {{ $list === $key ? $pillOn : $pillOff }}"
                    @if($list === $key) aria-current="true" @endif>{{ $title }}</a>
            @endforeach
        </nav>

        @if($list === 'least' && $stations['unused'] > 0)
            <p class="text-sm text-gray-700 mb-2">{{ $num($stations['unused']) }} {{ $plural($stations['unused']) }} brez ogledov.</p>
        @endif

        @if(count($stations['rows']) === 0)
            <div class="bg-gray-100 rounded-lg p-4 text-sm text-gray-600">ni podatkov</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500">
                        <th scope="col" class="text-left font-normal pb-1">Postaja</th>
                        <th scope="col" class="text-right font-normal pb-1">Na dan</th>
                        <th scope="col" class="text-right font-normal pb-1 pl-2">Sprememba</th>
                        <th scope="col" class="text-right font-normal pb-1 pl-2">12 tednov</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($stations['rows'] as $row)
                        <tr>
                            <td class="py-2 pr-1 min-w-0">
                                <a href="{{ $link(['postaja' => $row['name']]) }}#poisci" class="text-gray-900 underline decoration-gray-300 hover:decoration-gray-900 break-words">{{ $row['name'] }}</a>
                            </td>
                            <td class="py-2 text-right tabular-nums">{{ $perDay($row['per_day']) }}</td>
                            <td class="py-2 pl-2 text-right"><x-stats-change-badge :value="$row['change']" /></td>
                            <td class="py-2 pl-2"><div class="flex justify-end"><x-stats-sparkline :values="$row['weekly']" /></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    {{-- 4. Look up a station --}}
    <section aria-labelledby="poisci">
        <h2 id="poisci" class="text-xl font-bold text-gray-900 mb-3">Poišči postajo</h2>
        <form action="{{ route('stats') }}#poisci" method="get" class="flex gap-2">
            @if($months !== 3)<input type="hidden" name="obdobje" value="{{ $months }}">@endif
            @if($list !== 'top')<input type="hidden" name="seznam" value="{{ $list }}">@endif
            <input type="text" name="postaja" value="{{ $term }}" maxlength="100" placeholder="Ime ali koda postaje"
                aria-label="Ime ali koda postaje" autocomplete="off"
                class="flex-1 min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-base focus:outline-none focus:ring-2 focus:ring-emerald-700">
            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-white hover:bg-emerald-800">Poišči</button>
        </form>

        @if($term !== null && $lookup === null)
            <div class="mt-3 bg-gray-100 rounded-lg p-4 text-sm text-gray-700">Postaja ni najdena.</div>
        @elseif($lookup !== null)
            <div class="mt-3 bg-gray-100 rounded-lg p-3">
                <div class="font-bold text-gray-900">{{ $lookup['name'] }}</div>
                <div class="mt-0.5 text-xs text-gray-600 flex flex-wrap gap-x-3">
                    @foreach($lookup['codes'] as $code)
                        <span>{{ $code['code'] }}@if($code['to_center'] !== null) {{ $code['to_center'] ? 'proti centru' : 'iz centra' }}@endif @if($code['share'] !== null)({{ $pct($code['share']) }})@endif</span>
                    @endforeach
                </div>

                @php
                    $lookupDays = $lookup['days'];
                @endphp
                <div class="mt-2">
                    <x-stats-line-chart
                        :daily="array_column($lookupDays, 'views')"
                        :smooth="array_column($lookupDays, 'avg28')"
                        label="Dnevni ogledi postaje in 28-dnevno povprečje" />
                </div>
                <div class="mt-1 flex justify-between text-[11px] text-gray-500">
                    <span>{{ $dateShort($lookupDays[0]['date']) }}</span>
                    <span>{{ $dateShort($lookupDays[count($lookupDays) - 1]['date']) }}</span>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-800">
                    <span>{{ $perDay($lookup['per_day']) }} na dan</span>
                    <x-stats-change-badge :value="$lookup['change']" />
                    <span class="text-xs text-gray-600">glede na prejšnjih 28 dni</span>
                    <span>· mesto #{{ $num($lookup['rank']) }} od {{ $num($lookup['of']) }}</span>
                </div>
            </div>
        @endif
    </section>
    @endif
</div>
@endsection
