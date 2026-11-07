@props(['value' => null])

@php
    $rounded = $value === null ? null : (int) round($value);
    $classes = match (true) {
        $rounded === null, $rounded === 0 => 'bg-gray-200 text-gray-600',
        $rounded > 0 => 'bg-green-100 text-green-800',
        default => 'bg-red-100 text-red-800',
    };
    $text = match (true) {
        $rounded === null => '—',
        $rounded > 0 => '+'.number_format($rounded, 0, ',', '.').' %',
        $rounded < 0 => '−'.number_format(abs($rounded), 0, ',', '.').' %',
        default => '0 %',
    };
@endphp
<span {{ $attributes->class(['inline-block rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap', $classes]) }}>{{ $text }}</span>
