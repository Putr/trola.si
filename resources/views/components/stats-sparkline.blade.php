@props(['values' => [], 'width' => 70, 'height' => 16])

@php
    $count = count($values);
    $min = $count ? min($values) : 0;
    $max = $count ? max($values) : 0;
    $range = max($max - $min, 1);
    $points = [];
    foreach (array_values($values) as $i => $value) {
        $x = $count > 1 ? $i / ($count - 1) * ($width - 2) + 1 : $width / 2;
        $y = $max === $min ? $height / 2 : ($height - 2) - ($value - $min) / $range * ($height - 4) + 1;
        $points[] = round($x, 1).','.round($y, 1);
    }
@endphp
<svg width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" class="block" role="img" aria-label="Ogledi po tednih">
    <polyline fill="none" stroke="#047857" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" points="{{ implode(' ', $points) }}" />
</svg>
