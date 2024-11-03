@props([
'direction',
'isActive' => false,
'href' => null,
])

@php
$baseClasses = 'w-full hover:bg-emerald-700 hover:text-white transition-colors duration-150 shadow-md p-2 group';
$activeClasses = 'bg-emerald-700 text-white';
$inactiveClasses = 'bg-gray-100';

$images = [
'to' => [
'default' => '/imgs/bullseye-to_small.png',
'hover' => '/imgs/bullseye-to-white_small.png',
],
'all' => [
'default' => '/imgs/all.svg',
],
'from' => [
'default' => '/imgs/bullseye-from_small.png',
'hover' => '/imgs/bullseye-from-white_small.png',
],
];
@endphp

<div class="flex-1">
    @if($isActive)
    <button class="{{ $baseClasses }} {{ $activeClasses }}">
        @if($direction === 'all')
        <img src="{{ $images[$direction]['default'] }}" alt="Vse smeri" class="h-6 w-6 mx-auto brightness-0 invert">
        @else
        <img src="{{ $images[$direction]['hover'] }}"
            alt="{{ $direction === 'to' ? 'V center mesta' : 'Iz centra mesta' }}" class="h-6 mx-auto">
        @endif
    </button>
    @else
    <a href="{{ $href }}" class="block">
        <button class="{{ $baseClasses }} {{ $inactiveClasses }}">
            @if($direction === 'all')
            <img src="{{ $images[$direction]['default'] }}" alt="Vse smeri"
                class="h-6 w-6 mx-auto group-hover:brightness-0 group-hover:invert">
            @else
            <img src="{{ $images[$direction]['default'] }}"
                alt="{{ $direction === 'to' ? 'V center mesta' : 'Iz centra mesta' }}"
                class="h-6 mx-auto block group-hover:hidden">
            <img src="{{ $images[$direction]['hover'] }}"
                alt="{{ $direction === 'to' ? 'V center mesta' : 'Iz centra mesta' }}"
                class="h-6 mx-auto hidden group-hover:block">
            @endif
        </button>
    </a>
    @endif
</div>