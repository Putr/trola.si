@props([
    'daily' => [],
    'smooth' => [],
    'dashed' => [],
    'markerIndex' => null,
    'markerLabel' => null,
    'label' => 'Graf ogledov',
])

@php
    $width = 480;
    $height = 140;
    $padTop = 14;
    $padBottom = 6;
    $count = max(count($daily), count($smooth), count($dashed));
    $numbers = array_filter([...$daily, ...$smooth, ...$dashed], fn ($v) => $v !== null);
    $max = $numbers ? max($numbers) : 0;
    $max = $max > 0 ? $max : 1;
    $x = fn (int $i): float => $count > 1 ? $i / ($count - 1) * $width : 0.0;
    $y = fn (float $v): float => $height - $padBottom - $v / $max * ($height - $padTop - $padBottom);

    // Break the line wherever a value is missing so a gap never looks like a drop to zero.
    $segments = function (array $values) use ($x, $y): array {
        $result = [];
        $current = [];
        foreach (array_values($values) as $i => $v) {
            if ($v === null) {
                if (count($current) > 1) {
                    $result[] = implode(' ', $current);
                }
                $current = [];
                continue;
            }
            $current[] = round($x($i), 1).','.round($y((float) $v), 1);
        }
        if (count($current) > 1) {
            $result[] = implode(' ', $current);
        }

        return $result;
    };
@endphp
<svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" class="block w-full h-36" role="img" aria-label="{{ $label }}">
    <line x1="0" y1="{{ $height - $padBottom }}" x2="{{ $width }}" y2="{{ $height - $padBottom }}" stroke="#e5e7eb" vector-effect="non-scaling-stroke" />
    @if($markerIndex !== null && $count > 1)
        <line x1="{{ round($x($markerIndex), 1) }}" y1="0" x2="{{ round($x($markerIndex), 1) }}" y2="{{ $height - $padBottom }}" stroke="#9ca3af" stroke-width="1" stroke-dasharray="1 3" vector-effect="non-scaling-stroke" />
    @endif
    @foreach($segments($dashed) as $points)
        <polyline fill="none" stroke="#9ca3af" stroke-width="2" stroke-dasharray="5 4" vector-effect="non-scaling-stroke" points="{{ $points }}" />
    @endforeach
    @foreach($segments($daily) as $points)
        <polyline fill="none" stroke="#d1d5db" stroke-width="1" vector-effect="non-scaling-stroke" points="{{ $points }}" />
    @endforeach
    @foreach($segments($smooth) as $points)
        <polyline fill="none" stroke="#047857" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" points="{{ $points }}" />
    @endforeach
</svg>
@if($markerIndex !== null && $markerLabel && $count > 1)
    {{-- The label sits in HTML, not SVG, because the stretched SVG would distort text. --}}
    <div class="relative h-4 -mt-1 text-[10px] text-gray-500">
        <span class="absolute whitespace-nowrap" style="left: {{ max(4, min(96, round($markerIndex / ($count - 1) * 100, 1))) }}%; transform: translateX(-50%);">{{ $markerLabel }}</span>
    </div>
@endif
